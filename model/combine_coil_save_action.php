<?php
session_start();

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล (ถอยหลัง 1 โฟลเดอร์เพื่อเข้าหาไฟล์ dbcon)
if (file_exists("../dbcon_mats-new.php")) {
    include("../dbcon_mats-new.php");
} else {
    include("dbcon_mats-new.php");
}

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../combine_coil_mats.php");
    exit();
}

// รับค่าจาก Form
$main_coil_no   = isset($_POST['main_coil_no']) ? trim($_POST['main_coil_no']) : '';
$selected_coils = isset($_POST['selected_coils']) ? $_POST['selected_coils'] : [];
$folder_func    = isset($_POST['func']) ? trim($_POST['func']) : '';
$user_operator  = isset($_SESSION['ID']) ? trim($_SESSION['ID']) : 'SYSTEM';

if (empty($main_coil_no) || empty($selected_coils) || !is_array($selected_coils)) {
    echo "<script>alert('ข้อมูลไม่ครบถ้วน กรุณาเลือก Coil ที่ต้องการ Combine'); window.history.back();</script>";
    exit();
}

try {
    // 1. ดึงข้อมูล Coil หลัก
    $sql_main = "SELECT COIL_NO, MATERIAL_IN, COIL_COMBINEWEIGHT, COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT 
                 FROM COILPROD1 WHERE COIL_NO = :main_coil";
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->execute([':main_coil' => $main_coil_no]);
    $main_coil = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$main_coil) {
        throw new Exception("ไม่พบข้อมูล Coil หลัก: " . $main_coil_no);
    }

    // เริ่มต้น Transaction
    $conn->beginTransaction();

    // กำหนดค่าน้ำหนักเริ่มต้นของ Coil หลัก
    $dblCombineWeight = (float)($main_coil['COIL_COMBINEWEIGHT'] ?? 0);
    $dblActualWeight  = (float)($main_coil['COIL_ACTUALWEIGHT'] ?? 0);
    $dblProduceWeight = (float)($main_coil['COIL_PRODUCEWEIGHT'] ?? 0);
    $dblScrapWeight   = (float)($main_coil['COIL_SCRAPWEIGHT'] ?? 0);

    // กำหนดรูปแบบเวลาปัจจุบันสำหรับบันทึก
    $current_datetime = date('Y-m-d H:i:s');

    // 2. Prepare SQL Statements สำหรับบันทึก
    // Command 1: Insert ลง CMBNPROD2
    $sql_ins_cmbn = "INSERT INTO CMBNPROD2 (COIL_NO, PRODUCT_REFERENCE, MATERIAL_IN, CMBN_DATE, CMBN_WEIGHT, CMBN_OPERATOR) 
                     VALUES (:coil_no, :prod_ref, :mat_in, :cmbn_date, :cmbn_weight, :cmbn_operator)";
    $stmt_ins_cmbn = $conn->prepare($sql_ins_cmbn);

    // Command 3: Update สถานะ Coil ลูกเป็น 'CB'
    $sql_upd_sub = "UPDATE COILPROD1 SET COIL_STATUS = 'CB' WHERE COIL_NO = :sub_coil";
    $stmt_upd_sub = $conn->prepare($sql_upd_sub);

    // Query ดึงข้อมูล Coil ลูก
    $sql_get_sub = "SELECT COIL_NO, MATERIAL_IN, COIL_BALANCEWEIGHT FROM COILPROD1 WHERE COIL_NO = :sub_coil";
    $stmt_get_sub = $conn->prepare($sql_get_sub);

    // 3. วนลูป Combine Coil ลูกแต่ละรายการ
    foreach ($selected_coils as $sub_coil_no) {
        $sub_coil_no = trim($sub_coil_no);
        if (empty($sub_coil_no)) continue;

        $stmt_get_sub->execute([':sub_coil' => $sub_coil_no]);
        $sub_coil = $stmt_get_sub->fetch(PDO::FETCH_ASSOC);

        if (!$sub_coil) {
            throw new Exception("ไม่พบข้อมูล Coil ลูก: " . $sub_coil_no);
        }

        // txtCMBNActualWeight = Round(COIL_BALANCEWEIGHT, 0)
        $raw_balance_weight = (float)($sub_coil['COIL_BALANCEWEIGHT'] ?? 0);
        $cmbn_actual_weight = round($raw_balance_weight); 
        $sub_mat_in         = $sub_coil['MATERIAL_IN'] ?? '';

        // Insert ลง CMBNPROD2
        // *** แก้ไขจุดนี้: :coil_no = Coil ที่เอามารวม ($sub_coil_no), :prod_ref = Coil หลัก ($main_coil_no) ***
        $stmt_ins_cmbn->execute([
            ':coil_no'       => $sub_coil_no,
            ':prod_ref'      => $main_coil_no,
            ':mat_in'        => $sub_mat_in,
            ':cmbn_date'     => $current_datetime,
            ':cmbn_weight'   => $cmbn_actual_weight,
            ':cmbn_operator' => $user_operator
        ]);

        // Update สถานะ Coil ลูก
        $stmt_upd_sub->execute([':sub_coil' => $sub_coil_no]);

        // คำนวณสะสมค่าน้ำหนักตามสูตร VB
        $dblCombineWeight = round($dblCombineWeight + $cmbn_actual_weight);
        $dblActualWeight  = round($dblActualWeight + $cmbn_actual_weight);
    }

    // 4. คำนวณ Balance Weight ของ Coil หลักตามสูตร VB
    $dblBalanceWeight = $dblActualWeight - ($dblProduceWeight + $dblScrapWeight);

    // Command 2: Update น้ำหนักของ Coil หลัก
    $sql_upd_main = "UPDATE COILPROD1 
                     SET COIL_COMBINEWEIGHT = :combine_weight, 
                         COIL_ACTUALWEIGHT = :actual_weight, 
                         COIL_BALANCEWEIGHT = :balance_weight 
                     WHERE COIL_NO = :main_coil";
    $stmt_upd_main = $conn->prepare($sql_upd_main);
    $stmt_upd_main->execute([
        ':combine_weight' => $dblCombineWeight,
        ':actual_weight'  => $dblActualWeight,
        ':balance_weight' => $dblBalanceWeight,
        ':main_coil'       => $main_coil_no
    ]);

    // Commit Transaction
    $conn->commit();

    echo "<script>
            alert('รวม Coil (Combine) เรียบร้อยแล้ว');
            window.location.href = '../combine_coil_update_mats.php?COIL=" . urlencode($main_coil_no) . "&func=" . urlencode($folder_func) . "';
          </script>";
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    $err_msg = addslashes($e->getMessage());
    echo "<script>
            alert('เกิดข้อผิดพลาดในการทำ Combine: " . $err_msg . "');
            window.history.back();
          </script>";
    exit();
}
?>