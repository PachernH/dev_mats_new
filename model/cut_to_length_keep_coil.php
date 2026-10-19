<?php
// เริ่ม session
session_start();

// ตั้งค่า Response Header ให้ส่งกลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ Login และสิทธิ์
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';

if (empty($iduser_func)) {
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง']);
    exit();
}

// รับค่า GET หรือ POST
$job_order = isset($_REQUEST['jno']) ? trim($_REQUEST['jno']) : (isset($_REQUEST['job_order']) ? trim($_REQUEST['job_order']) : '');
$coil_no   = isset($_REQUEST['cno']) ? trim($_REQUEST['cno']) : (isset($_REQUEST['coil_no']) ? trim($_REQUEST['coil_no']) : '');

if (empty($job_order) || empty($coil_no)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ Job Order และ Coil No.']);
    exit();
}

require_once "../dbcon_mats-new.php";

try {
    $conn->beginTransaction();

    $now_time   = date('Y-m-d H:i:s');
    $today_date = date('Y-m-d');

    // 1. ดึงข้อมูลรายละเอียดของ Coil ปัจจุบัน
    $sql_coil = "SELECT COIL_BALANCEWEIGHT, MATERIAL_IN, ALLOY, LOCATION_ID FROM COILPROD1 WHERE COIL_NO = :coil_no";
    $stmt_coil = $conn->prepare($sql_coil);
    $stmt_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_coil->execute();
    $coil_data = $stmt_coil->fetch(PDO::FETCH_ASSOC);

    if (!$coil_data) {
        throw new Exception("ไม่พบข้อมูล Coil No: " . $coil_no . " ในระบบ");
    }

    $curr_balance_wt = (float)($coil_data['COIL_BALANCEWEIGHT'] ?? 0);
    $material_in     = $coil_data['MATERIAL_IN'] ?? '';
    $alloy           = $coil_data['ALLOY'] ?? '';
    $location_id     = $coil_data['LOCATION_ID'] ?? 'NONE';

    // 2. ดึง Product No ล่าสุดเพื่อใช้อ้างอิงบันทึก Scrap (ถ้ามี)
    $sql_crsh = "SELECT TOP 1 PRODUCT_NO FROM CRSHPROD1 WHERE JOB_ORDER = :job_order AND COIL_NO = :coil_no ORDER BY CRSH_OPERATEDATE DESC";
    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_crsh->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_crsh->execute();
    $crsh_row = $stmt_crsh->fetch(PDO::FETCH_ASSOC);
    $product_no = $crsh_row['PRODUCT_NO'] ?? '';

    $is_auto_closed = false;

    // 3. ตรวจสอบเงื่อนไขน้ำหนักคงเหลือ (< 50 kg. ให้ Auto Close Coil & Scrap)
    if ($curr_balance_wt < 50) {
        $is_auto_closed = true;
        $scrap_weight   = $curr_balance_wt > 0 ? $curr_balance_wt : 0;

        // 3.1 บันทึกเศษเข้า SCRPPROD1 (ตาม f4 AddScrap)
        if ($scrap_weight > 0) {
            $sql_scrp = "INSERT INTO SCRPPROD1 (
                            PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM,
                            LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR,
                            SCRP_OPERATEDATE, SCRP_STATUS
                        ) VALUES (
                            :product_no, :scrp_date, :coil_no, :material_in, :line_process, 'CL01', 'CO',
                            :location_id, :alloy, 0, :scrp_weight, 'AUTO SCRAP KEEP COIL < 50KG', 0, :operator,
                            :operatedate, 'OP'
                        )";
            $stmt_scrp = $conn->prepare($sql_scrp);
            $stmt_scrp->bindParam(':product_no', $product_no, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':scrp_date', $today_date, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':material_in', $material_in, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':line_process', $line_func, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':location_id', $location_id, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':alloy', $alloy, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':scrp_weight', $scrap_weight);
            $stmt_scrp->bindParam(':operator', $iduser_func, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':operatedate', $now_time, PDO::PARAM_STR);
            $stmt_scrp->execute();
        }

        // 3.2 อัปเดต COILPROD1 เป็น Close Coil (STATUS = 'CL', BALANCE = 0)
        $sql_up_coil = "UPDATE COILPROD1 SET 
                            COIL_SCRAPWEIGHT   = ISNULL(COIL_SCRAPWEIGHT, 0) + :scrap_weight,
                            COIL_BALANCEWEIGHT = 0,
                            COIL_STATUS        = 'CL'
                        WHERE COIL_NO = :coil_no";
        $stmt_up_coil = $conn->prepare($sql_up_coil);
        $stmt_up_coil->bindParam(':scrap_weight', $scrap_weight);
        $stmt_up_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_up_coil->execute();

    } else {
        // 3.3 กรณี Balance >= 50 kg. เปลี่ยนสถานะ Coil กลับเป็น 'AC' (Active คืนเข้าคลัง)
        $sql_up_coil = "UPDATE COILPROD1 SET COIL_STATUS = 'AC' WHERE COIL_NO = :coil_no";
        $stmt_up_coil = $conn->prepare($sql_up_coil);
        $stmt_up_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_up_coil->execute();
    }

    // 4. ปลดรายการทำงานออกจาก COILWORK1
    $sql_del_cw = "DELETE FROM COILWORK1 WHERE COIL_NO = :coil_no AND LINE_PROCESS = :line_process";
    $stmt_del_cw = $conn->prepare($sql_del_cw);
    $stmt_del_cw->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_del_cw->bindParam(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_del_cw->execute();

    $conn->commit();

    if ($is_auto_closed) {
        $msg = 'เนื่องจากน้ำหนักคงเหลือของ Coil น้อยกว่า 50 kg. ระบบได้ทำการ ปิดม้วน (Close Coil) และ Auto Scrap เรียบร้อยแล้ว';
    } else {
        $msg = 'ปลดม้วน Coil (' . $coil_no . ') คืนเข้าคลัง (Keep Coil) เรียบร้อยแล้ว';
    }

    echo json_encode([
        'status'       => 'success',
        'message'      => $msg,
        'cno'          => $coil_no,
        'jno'          => $job_order,
        'auto_closed'  => $is_auto_closed
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>