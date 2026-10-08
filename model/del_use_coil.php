<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include("../dbcon_mats-new.php");

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';

// ตรวจสอบ Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

// รับค่าจาก AJAX / POST Parameters
$strCoilNo     = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$strJobProcess = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';

if (empty($strCoilNo) || empty($strJobProcess)) {
    echo json_encode(['status' => 'error', 'message' => 'The Coil No. or Job Order is incorrect.']);
    exit;
}

try {
    // -------------------------------------------------------------
    // STEP 1: ตรวจสอบสถานะ COIL_STATUS ใน COILPROD1 ก่อนลบ
    // -------------------------------------------------------------
    $sql_check_coil = "SELECT c.COIL_STATUS, c.COIL_BALANCEWEIGHT, j.JOB_WORKPROCESS 
                       FROM COILPROD1 c
                       LEFT JOIN JOBORDER1 j ON j.JOB_ORDER = :job_order
                       WHERE c.COIL_NO = :coil_no AND c.JOB_PROCESS = :job_order";
    
    $stmt_check = $conn->prepare($sql_check_coil);
    $stmt_check->bindParam(':coil_no', $strCoilNo, PDO::PARAM_STR);
    $stmt_check->bindParam(':job_order', $strJobProcess, PDO::PARAM_STR);
    $stmt_check->execute();
    $coil_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$coil_data) {
        throw new Exception("Not found Coil No: {$strCoilNo} in Job Process: {$strJobProcess}");
    }

    // เงื่อนไข: ถ้า COIL_STATUS เป็น CM จะไม่ให้ลบ
    if (trim($coil_data['COIL_STATUS']) === 'CM') {
        echo json_encode([
            'status' => 'error',
            'message' => "Can not Delete Coil No: [{$strCoilNo}] it status : CM"
        ]);
        exit;
    }

    $dblCoilActualWeight = (float)($coil_data['COIL_BALANCEWEIGHT'] ?? 0);
    $strWorkProcess      = trim($coil_data['JOB_WORKPROCESS'] ?? '');

    // เริ่ม Transaction
    $conn->beginTransaction();

    // -------------------------------------------------------------
    // STEP 2: คำนวณค่า Weight / Piece ที่ต้องลบออกจาก JOBORDER1
    // -------------------------------------------------------------
    $cm_weight = 0; $cm_piece = 0;
    $ba_weight = 0; $ba_piece = 0;

    // ตรวจสอบเงื่อนไขด้วย strpos (คืนค่าเป็นตำแหน่งที่พบ หรือ false หากไม่พบ)

    $hasCM = (strpos($strWorkProcess, 'CM') !== false);

    $hasBA = (strpos($strWorkProcess, 'BA') !== false);


    if ($hasCM && $hasBA) {

        // 1. กรณีที่มีทั้ง CM และ BA

        $cm_weight = $dblCoilActualWeight;

        $cm_piece = 1;

        $ba_weight = $dblCoilActualWeight;

        $ba_piece = 1;

    } elseif ($hasCM) {

        // 2. กรณีที่มีเฉพาะ CM

        $cm_weight = $dblCoilActualWeight;

        $cm_piece = 1;

        $ba_weight = 0;

        $ba_piece = 0;

    } elseif ($hasBA) {

        // 3. กรณีที่มีเฉพาะ BA

        $cm_weight = 0;

        $cm_piece = 0;

        $ba_weight = $dblCoilActualWeight;

        $ba_piece = 1;

    } else {

        // 4. กรณีอื่นๆ

        $cm_weight = 0;

        $cm_piece = 0;

        $ba_weight = 0;

        $ba_piece = 0;

    }

    // -------------------------------------------------------------
    // STEP 3: ROLLBACK COILPROD1 (แก้ไขจาก NULL เป็น Default Values)
    // -------------------------------------------------------------
    $sql_upd_coil = "UPDATE COILPROD1 
                     SET JOB_PROCESS = '', 
                         RSET_NO = '', 
                         RECIPE_NO = '', 
                         RECIPE_ITEM = 0, 
                         TOTAL_PASS = 0, 
                         CURRENT_PASS = 0, 
                         THICKNESS_FINAL = 0, 
                         COIL_WORKPROCESS = 'CS>IS>WS', 
                         COIL_NEXTPROCESS = 'WS', 
                         USE_FORPROCESS = '', 
                         LOCATION_ID = 'NONE', 
                         CDML_STATUS = 'I' 
                     WHERE COIL_NO = :coil_no";

    $stmt_upd_coil = $conn->prepare($sql_upd_coil);
    $stmt_upd_coil->bindParam(':coil_no', $strCoilNo, PDO::PARAM_STR);
    $stmt_upd_coil->execute();

    // -------------------------------------------------------------
    // STEP 4: UPDATE JOBORDER1 (หักลบค่า Weight/Piece ออก)
    // -------------------------------------------------------------
    $sql_upd_job = "UPDATE JOBORDER1 
                    SET JOB_SELECTCOLDMILLWEIGHT = CASE WHEN (ISNULL(JOB_SELECTCOLDMILLWEIGHT, 0) - :cm_weight) < 0 THEN 0 ELSE ISNULL(JOB_SELECTCOLDMILLWEIGHT, 0) - :cm_weight END, 
                        JOB_SELECTCOLDMILLPIECE  = CASE WHEN (ISNULL(JOB_SELECTCOLDMILLPIECE, 0) - :cm_piece) < 0 THEN 0 ELSE ISNULL(JOB_SELECTCOLDMILLPIECE, 0) - :cm_piece END, 
                        JOB_SELECTBATCHANNEALWEIGHT = CASE WHEN (ISNULL(JOB_SELECTBATCHANNEALWEIGHT, 0) - :ba_weight) < 0 THEN 0 ELSE ISNULL(JOB_SELECTBATCHANNEALWEIGHT, 0) - :ba_weight END, 
                        JOB_SELECTBATCHANNEALPIECE  = CASE WHEN (ISNULL(JOB_SELECTBATCHANNEALPIECE, 0) - :ba_piece) < 0 THEN 0 ELSE ISNULL(JOB_SELECTBATCHANNEALPIECE, 0) - :ba_piece END, 
                        JOB_UPDATEDATE = GETDATE(),
                        JOB_UPDATE = :iduser_func
                    WHERE JOB_ORDER = :job_order";

    $stmt_upd_job = $conn->prepare($sql_upd_job);
    $stmt_upd_job->bindParam(':cm_weight', $cm_weight);
    $stmt_upd_job->bindParam(':cm_piece', $cm_piece);
    $stmt_upd_job->bindParam(':ba_weight', $ba_weight);
    $stmt_upd_job->bindParam(':ba_piece', $ba_piece);
    $stmt_upd_job->bindParam(':iduser_func', $iduser_func, PDO::PARAM_STR);
    $stmt_upd_job->bindParam(':job_order', $strJobProcess, PDO::PARAM_STR);
    $stmt_upd_job->execute();

    // Commit Transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Delete Coil [{$strCoilNo}] from Job [{$strJobProcess}] succeed!"
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}