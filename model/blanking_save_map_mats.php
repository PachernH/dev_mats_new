<?php
// เริ่ม session
session_start();

// ตั้งค่า Response Header ให้ส่งกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ Login และสิทธิ์
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '';

if (empty($iduser_func)) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง']);
    exit();
}

// ตรวจสอบ Method การส่งข้อมูล
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once "../dbcon_mats-new.php";

// รับค่าจาก Form POST
$job_order      = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$coil_no        = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$mould_setup_no = isset($_POST['mould_setup_no']) ? trim($_POST['mould_setup_no']) : '';
$saleorder_no   = isset($_POST['saleorder_no']) ? trim($_POST['saleorder_no']) : '';
$saleorder_item = isset($_POST['saleorder_item']) ? trim($_POST['saleorder_item']) : '';

// ตรวจสอบความถูกต้องของข้อมูลเบื้องต้น (บังคับทั้ง Coil และ Mold สำหรับ Line 1,2,3,4)
if (empty($job_order) || empty($coil_no) || empty($mould_setup_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน กรุณาเลือก Job Order, Coil และ Mold ให้ถูกต้อง']);
    exit();
}

try {
    // เริ่มต้น Database Transaction
    $conn->beginTransaction();

    $current_now = date('Y-m-d H:i:s');

    // -----------------------------------------------------------------
    // STEP 1: บันทึกข้อมูลลงในตาราง COILWORK1 (ผูก Coil + Mold เข้ากับ Job และ Line)
    // -----------------------------------------------------------------
    // 1.1 ลบรายการเดิมใน COILWORK1 หากมีค้างอยู่สำหรับ Line นี้[cite: 1]
    $sql_del_cw = "DELETE FROM COILWORK1 WHERE (COIL_NO = :coil_no OR LINE_PROCESS = :line_process)";
    $stmt_del_cw = $conn->prepare($sql_del_cw);
    $stmt_del_cw->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_del_cw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_del_cw->execute();

    // 1.2 Insert ข้อมูลใหม่ลงใน COILWORK1 (บันทึกทั้ง Coil และ Mold)[cite: 1]
    $sql_cw = "INSERT INTO COILWORK1 (
                    COIL_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MOULDSETUP_NO,
                    LINE_PROCESS, WORK_OPERATOR1, WORK_OPERATEDATE
               ) VALUES (
                    :coil_no, :job_order, :saleorder_no, :saleorder_item, :mould_setup_no,
                    :line_process, :operator1, :operatedate
               )";

    $stmt_cw = $conn->prepare($sql_cw);
    $stmt_cw->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_cw->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_cw->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_cw->bindParam(':saleorder_item', $saleorder_item, PDO::PARAM_STR);
    $stmt_cw->bindParam(':mould_setup_no', $mould_setup_no, PDO::PARAM_STR);
    $stmt_cw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_cw->bindParam(':operator1', $iduser_func, PDO::PARAM_STR);
    $stmt_cw->bindParam(':operatedate', $current_now);
    $stmt_cw->execute();

    // -----------------------------------------------------------------
    // STEP 2: อัปเดตสถานะ COILPROD1 เป็น 'WK' (Working - กำลังผลิต)[cite: 1]
    // -----------------------------------------------------------------
    $sql_up_coil = "UPDATE COILPROD1 SET COIL_STATUS = 'WK' WHERE COIL_NO = :coil_no";
    $stmt_up_coil = $conn->prepare($sql_up_coil);
    $stmt_up_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_up_coil->execute();

    // -----------------------------------------------------------------
    // STEP 3: บันทึกข้อมูลแม่พิมพ์ลงตาราง MLDSWORK1 และอัปเดต MLDSTRNS2 ตาม f7.txt[cite: 1]
    // -----------------------------------------------------------------
    // 3.1 ดึงข้อมูลรายละเอียดแม่พิมพ์จาก MLDSTRNS2[cite: 1]
    $sql_mld = "SELECT * FROM MLDSTRNS2 WHERE MOULDSETUP_NO = :mould_setup_no";
    $stmt_mld = $conn->prepare($sql_mld);
    $stmt_mld->bindParam(':mould_setup_no', $mould_setup_no, PDO::PARAM_STR);
    $stmt_mld->execute();
    $mld_data = $stmt_mld->fetch(PDO::FETCH_ASSOC);

    if ($mld_data) {
        // 3.2 ลบรายการแม่พิมพ์เดิมที่ตั้งค้างอยู่ใน Line ผลิตนี้ออกก่อน[cite: 1]
        $sql_del_mw = "DELETE FROM MLDSWORK1 WHERE MOULDSETUP_NO = :mould_setup_no OR LINE_PROCESS = :line_process";
        $stmt_del_mw = $conn->prepare($sql_del_mw);
        $stmt_del_mw->bindParam(':mould_setup_no', $mould_setup_no, PDO::PARAM_STR);
        $stmt_del_mw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
        $stmt_del_mw->execute();

        // 3.3 บันทึกแม่พิมพ์เข้าตาราง MLDSWORK1[cite: 1]
        $sql_mw = "INSERT INTO MLDSWORK1 (
                        MOULDSETUP_NO, MAINTENANCE_NO1, DIESET_NO, MAINTENANCE_NO2, DIE_NO, 
                        MAINTENANCE_NO3, PUNCH_NO1, MAINTENANCE_NO4, PUNCH_NO2, LINE_PROCESS, 
                        DIAMETER, DIAMETER_MAX, DIAMETER_MIN, CLEARANCE, THICKNESS_MAX, THICKNESS_MIN
                   ) VALUES (
                        :mould_setup_no, :maint1, :dieset_no, :maint2, :die_no,
                        :maint3, :punch1, :maint4, :punch2, :line_process,
                        :diameter, :diameter_max, :diameter_min, :clearance, :thickness_max, :thickness_min
                   )";

        $stmt_mw = $conn->prepare($sql_mw);
        $stmt_mw->bindParam(':mould_setup_no', $mould_setup_no, PDO::PARAM_STR);
        $stmt_mw->bindParam(':maint1', $mld_data['MAINTENANCE_NO1'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':dieset_no', $mld_data['DIESET_NO'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':maint2', $mld_data['MAINTENANCE_NO2'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':die_no', $mld_data['DIE_NO'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':maint3', $mld_data['MAINTENANCE_NO3'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':punch1', $mld_data['PUNCH_NO1'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':maint4', $mld_data['MAINTENANCE_NO4'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':punch2', $mld_data['PUNCH_NO2'], PDO::PARAM_STR);
        $stmt_mw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
        $stmt_mw->bindParam(':diameter', $mld_data['DIAMETER']);
        $stmt_mw->bindParam(':diameter_max', $mld_data['DIAMETER_MAX']);
        $stmt_mw->bindParam(':diameter_min', $mld_data['DIAMETER_MIN']);
        $stmt_mw->bindParam(':clearance', $mld_data['CLEARANCE']);
        $stmt_mw->bindParam(':thickness_max', $mld_data['THICKNESS_MAX']);
        $stmt_mw->bindParam(':thickness_min', $mld_data['THICKNESS_MIN']);
        $stmt_mw->execute();

        // 3.4 อัปเดตสถานะ MLDSTRNS2 เป็น 'WK' (Working)[cite: 1]
        $sql_up_mld = "UPDATE MLDSTRNS2 SET MSTP_STATUS = 'WK' WHERE MOULDSETUP_NO = :mould_setup_no";
        $stmt_up_mld = $conn->prepare($sql_up_mld);
        $stmt_up_mld->bindParam(':mould_setup_no', $mould_setup_no, PDO::PARAM_STR);
        $stmt_up_mld->execute();
    }

    // ยืนยันการทำรายการทั้งหมด
    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'ทำรายการ Map Coil และ Mold สำเร็จเรียบร้อยแล้ว',
        'jno'     => $job_order,
        'cno'     => $coil_no,
        'mno'     => $mould_setup_no
    ]);

} catch (Exception $e) {
    // หากเกิด Error ให้ Rollback ย้อนกลับทั้งหมด
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>