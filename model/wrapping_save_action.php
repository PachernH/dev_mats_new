<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $user_operator = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'SYSTEM';
    $folder_func   = isset($_POST['func']) ? htmlspecialchars($_POST['func'], ENT_QUOTES, 'UTF-8') : 'pd';
    
    $job_order      = trim($_POST['job_order'] ?? '');
    $saleorder_no   = trim($_POST['saleorder_no'] ?? '');
    $saleorder_item = trim($_POST['saleorder_item'] ?? '');
    $material_in    = trim($_POST['material_in'] ?? '');
    $alloy          = trim($_POST['alloy'] ?? '');
    $work_process   = trim($_POST['work_process'] ?? '');
    $produce_weight = floatval($_POST['produce_weight'] ?? 0);
    $bottom_weight  = floatval($_POST['bottom_weight'] ?? 0);
    
    $crsh_status     = 'AC';
    $product_id      = 'WR';
    $product_model   = 'WRAP';
    $line_process    = 'W';
    $org_lineprocess = 'W';
    
    if (empty($job_order)) {
        echo "<script>alert('Error: ไม่พบข้อมูล Job Order'); window.history.back();</script>";
        exit();
    }

    try {
        $conn->beginTransaction();

        // 1. GEN PRODUCT NO. แบบปลอดภัยป้องกันเลขชนกัน (Concurrency Safe)
        $date_prefix = 'P' . date('ymd') . '-W-';
        $sql_gen = "SELECT TOP 1 PRODUCT_NO FROM CRSHPROD1 WITH (UPDLOCK, HOLDLOCK) WHERE PRODUCT_NO LIKE :prefix ORDER BY PRODUCT_NO DESC";
        $stmt_gen = $conn->prepare($sql_gen);
        $stmt_gen->execute([':prefix' => $date_prefix . '%']);
        $row_gen = $stmt_gen->fetch(PDO::FETCH_ASSOC);

        $next_running = 1;
        if ($row_gen && !empty($row_gen['PRODUCT_NO'])) {
            $parts = explode('-', $row_gen['PRODUCT_NO']);
            $last_seq = end($parts);
            if (is_numeric($last_seq)) {
                $next_running = intval($last_seq) + 1;
            }
        }
        $product_no = $date_prefix . str_pad($next_running, 2, '0', STR_PAD_LEFT);

        // 2. INSERT ลงตารางหลัก CRSHPROD1
        $sql_insert_crsh = "INSERT INTO CRSHPROD1 (
            PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, 
            MOULDSETUP_NO, MAINTENANCE_NO1, DIESET_NO, MAINTENANCE_NO2, DIE_NO, MAINTENANCE_NO3, PUNCH_NO1, 
            MAINTENANCE_NO4, PUNCH_NO2, PRODUCT_ID, PRODUCT_MODEL, ORG_LINEPROCESS, LINE_PROCESS, 
            LOCATION_ID, F_ALLOY, F_TEMPER, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, 
            CRSH_WORKPROCESS, CRSH_NEXTPROCESS, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT, 
            CRSH_PRODUCEPIECE, CRSH_BOTTOMWEIGHT, MATERIAL_CODE, CRSH_STARTDATE, CRSH_STARTTIME, 
            CRSH_ENDDATE, CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1, CRSH_OPERATEDATE, CRSH_STATUS
        ) VALUES (
            ?, '', '', ?, ?, ?, ?, 
            '', '', '', '', '', '', '', 
            '', '', ?, ?, ?, ?, 
            '', ?, '', ?, '', '', '', '', 
            ?, 'IS', ?, 1, ?, 
            1, ?, '', GETDATE(), GETDATE(), 
            GETDATE(), GETDATE(), 1, ?, GETDATE(), ?
        )";

        $stmt1 = $conn->prepare($sql_insert_crsh);
        $stmt1->execute([
            $product_no, $saleorder_no, $saleorder_item, $job_order, $material_in, 
            $product_id, $product_model, $org_lineprocess, $line_process, 
            $alloy, $alloy, $work_process, $produce_weight, $produce_weight, 
            $bottom_weight, $user_operator, $crsh_status
        ]);

        // 3. UPDATE JOBORDER1
        $sql_update_job = "UPDATE JOBORDER1 
                           SET JOB_ACTUALWEIGHT = ISNULL(JOB_ACTUALWEIGHT, 0) + ?, 
                               JOB_ACTUALPIECE = ISNULL(JOB_ACTUALPIECE, 0) + 1, 
                               JOB_PRODUCEWEIGHT = ISNULL(JOB_PRODUCEWEIGHT, 0) + ?, 
                               JOB_PRODUCEPIECE = ISNULL(JOB_PRODUCEPIECE, 0) + 1 
                           WHERE JOB_ORDER = ?";
        $stmt2 = $conn->prepare($sql_update_job);
        $stmt2->execute([$produce_weight, $produce_weight, $job_order]);

        // 4. UPDATE CSTMORDR2
        $sql_update_cstm = "UPDATE CSTMORDR2 
                            SET CTM2_ACTUALWEIGHT = ISNULL(CTM2_ACTUALWEIGHT, 0) + ?, 
                                CTM2_ACTUALPIECE = ISNULL(CTM2_ACTUALPIECE, 0) + 1, 
                                CTM2_PRODUCEWEIGHT = ISNULL(CTM2_PRODUCEWEIGHT, 0) + ?, 
                                CTM2_PRODUCEPIECE = ISNULL(CTM2_PRODUCEPIECE, 0) + 1 
                            WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?";
        $stmt3 = $conn->prepare($sql_update_cstm);
        $stmt3->execute([$produce_weight, $produce_weight, $saleorder_no, $saleorder_item]);

        $conn->commit();

        // ส่ง URL พิมพ์ป้าย Label กลับไปเปิดผ่าน Redirect อย่างปลอดภัย
        $print_url = "../print_circle_sheet_product_label_mats.php?productno=" . urlencode($product_no) . "&joborder=" . urlencode($job_order);
        $redirect_url = "../wrapping_process_mats.php?func=" . urlencode($folder_func) . "&print_url=" . urlencode($print_url) . "&saved=1&pno=" . urlencode($product_no);

        header("Location: " . $redirect_url);
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
} else {
    header("Location: ../wrapping_process_mats.php");
    exit();
}
?>