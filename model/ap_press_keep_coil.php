<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : 'F';

if (empty($iduser_func)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง']);
    exit();
}

require_once "../dbcon_mats-new.php";

$job_order = isset($_GET['jno']) ? trim($_GET['jno']) : '';
$coil_no   = isset($_GET['cno']) ? trim($_GET['cno']) : '';

if (empty($job_order) || empty($coil_no)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

try {
    $conn->beginTransaction();

    $now_time   = date('Y-m-d H:i:s');
    $today_date = date('Y-m-d');

    // 1. ดึงข้อมูล Coil ปัจจุบัน
    $sql_coil = "SELECT ALLOY, MATERIAL_IN, LOCATION_ID, COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT, COIL_BALANCEWEIGHT 
                 FROM COILPROD1 WHERE COIL_NO = :coil_no";
    $stmt_coil = $conn->prepare($sql_coil);
    $stmt_coil->bindParam(':coil_no', $coil_no);
    $stmt_coil->execute();
    $coil = $stmt_coil->fetch(PDO::FETCH_ASSOC);

    if (!$coil) {
        throw new Exception("ไม่พบข้อมูล Coil: " . $coil_no);
    }

    $bal_wt      = (float)($coil['COIL_BALANCEWEIGHT'] ?? 0);
    $material_in = $coil['MATERIAL_IN'] ?? '';
    $alloy       = $coil['ALLOY'] ?? '';
    $location_id = $coil['LOCATION_ID'] ?? 'NONE';

    // 2. ตรวจสอบเงื่อนไข Balance Weight หากเหลือน้อย (< 50 kg.) ให้ทำการ Close Coil & Auto Scrap
    if ($bal_wt < 50) {
        $scrap_weight = $bal_wt > 0 ? $bal_wt : 0;

        // 2.1 บันทึก Scrap อัตโนมัติใน SCRPPROD1
        if ($scrap_weight > 0) {
            $scrp_cl_prefix = "S" . date('ymd') . "-" . strtoupper($line_func) . "-";
            $sql_cl_run = "SELECT COUNT(*) as run_cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :prefix";
            $stmt_cl_run = $conn->prepare($sql_cl_run);
            $search_cl_prefix = $scrp_cl_prefix . "%";
            $stmt_cl_run->bindParam(':prefix', $search_cl_prefix);
            $stmt_cl_run->execute();
            $row_cl_run = $stmt_cl_run->fetch(PDO::FETCH_ASSOC);
            $next_cl_no = ((int)($row_cl_run['run_cnt'] ?? 0)) + 1;
            $scrap_cl_no = $scrp_cl_prefix . sprintf("%02d", $next_cl_no);

            $sql_scrp_cl = "INSERT INTO SCRPPROD1 (
                                PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM,
                                LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR,
                                SCRP_OPERATEDATE, SCRP_STATUS
                            ) VALUES (
                                :product_no, :scrp_date, :coil_no, :material_in, :line_process, 'AP01', 'CO',
                                :location_id, :alloy, 0, :scrp_weight, 'AUTO SCRAP CLOSE COIL VIA KEEP', 0, :operator,
                                :operatedate, 'OP'
                            )";
            $stmt_scrp_cl = $conn->prepare($sql_scrp_cl);
            $stmt_scrp_cl->bindParam(':product_no', $scrap_cl_no);
            $stmt_scrp_cl->bindParam(':scrp_date', $today_date);
            $stmt_scrp_cl->bindParam(':coil_no', $coil_no);
            $stmt_scrp_cl->bindParam(':material_in', $material_in);
            $stmt_scrp_cl->bindParam(':line_process', $line_func);
            $stmt_scrp_cl->bindParam(':location_id', $location_id);
            $stmt_scrp_cl->bindParam(':alloy', $alloy);
            $stmt_scrp_cl->bindParam(':scrp_weight', $scrap_weight);
            $stmt_scrp_cl->bindParam(':operator', $iduser_func);
            $stmt_scrp_cl->bindParam(':operatedate', $now_time);
            $stmt_scrp_cl->execute();
        }

        // 2.2 อัปเดต COILPROD1 เป็น Close ('CL')
        $sql_coil_up = "UPDATE COILPROD1 SET 
                            COIL_SCRAPWEIGHT   = ISNULL(COIL_SCRAPWEIGHT, 0) + :scrap_wt,
                            COIL_BALANCEWEIGHT = 0,
                            COIL_STATUS        = 'CL'
                         WHERE COIL_NO = :coil_no";
        $stmt_coil_up = $conn->prepare($sql_coil_up);
        $stmt_coil_up->bindParam(':scrap_wt', $scrap_weight);
        $stmt_coil_up->bindParam(':coil_no', $coil_no);
        $stmt_coil_up->execute();

        $msg = "ม้วน Coil (" . $coil_no . ") น้ำหนักเหลือน้อยกว่า 50 kg. ระบบได้ทำการ Close Coil และ Auto Scrap ให้อัตโนมัติเรียบร้อยแล้ว";

    } else {
        // กรณี Balance Weight ยังมีมากกว่า 50 kg. ให้คืนสถานะเป็น Active ('AC')
        $sql_coil_up = "UPDATE COILPROD1 SET COIL_STATUS = 'AC' WHERE COIL_NO = :coil_no";
        $stmt_coil_up = $conn->prepare($sql_coil_up);
        $stmt_coil_up->bindParam(':coil_no', $coil_no);
        $stmt_coil_up->execute();

        $msg = "ทำรายการ Keep Coil (พักม้วน Coil: " . $coil_no . ") สำเร็จเรียบร้อยแล้ว";
    }

    // 3. ลบข้อมูลการทำงานออกจาก COILWORK1
    $sql_del_cw = "DELETE FROM COILWORK1 WHERE COIL_NO = :coil_no AND LINE_PROCESS = :line_process";
    $stmt_del_cw = $conn->prepare($sql_del_cw);
    $stmt_del_cw->bindParam(':coil_no', $coil_no);
    $stmt_del_cw->bindParam(':line_process', $line_func);
    $stmt_del_cw->execute();

    $conn->commit();

    ob_clean();
    echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>