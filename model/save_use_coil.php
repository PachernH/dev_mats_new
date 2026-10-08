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

// =========================================================================
// 📍 [จุดที่ 3] ใส่โค้ดตรวจสอบเงื่อนไข RELEASE PIECE == COLD MILL PIECE ตรงนี้
// =========================================================================
$sql_check = "SELECT JOB_RELEASEPIECE, JOB_COLDMILLPIECE FROM JOBORDER1 WHERE JOB_ORDER = :job_order";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bindParam(':job_order', $strJobProcess, PDO::PARAM_STR);
$stmt_check->execute();
$job_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

if ($job_check) {
    $rel_piece = (float)($job_check['JOB_RELEASEPIECE'] ?? 0);
    $cm_piece  = (float)($job_check['JOB_COLDMILLPIECE'] ?? 0);
    
    // เช็คว่าถ้าจำนวน Cold Mill Piece มากกว่าหรือเท่ากับ Release Piece ให้ดักไม่ให้ทำต่อ
    if ($rel_piece > 0 && $cm_piece >= $rel_piece) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Unable to save because the number of Cold Mill Pieces has reached the Release Piece limit.'
        ]);
        exit; // หยุดการทำงานทันที ไม่ให้ไปถึงคำสั่ง Insert/Update
    }
}
// =========================================================================


try {
    // เริ่ม Transaction
    $conn->beginTransaction();

    // -------------------------------------------------------------
    // STEP 1: ดึงข้อมูลที่จำเป็นจาก JOBORDER1
    // -------------------------------------------------------------
    $sql_job = "SELECT JOB_WORKPROCESS, USE_FORPROCESS, THICKNESS 
                FROM JOBORDER1 
                WHERE JOB_ORDER = :job_order";
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $strJobProcess, PDO::PARAM_STR);
    $stmt_job->execute();
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("ไม่พบข้อมูล Job Order: {$strJobProcess}");
    }

    $strWorkProcess   = trim($job['JOB_WORKPROCESS'] ?? '');
    $strUseForProcess = trim($job['USE_FORPROCESS'] ?? '');
    $dblThicknessFinal = (float)($job['THICKNESS'] ?? 0);

    // -------------------------------------------------------------
    // STEP 2: ดึงข้อมูลคอยล์จาก COILPROD1 และหา RECIPE จาก CMRCMSTR1
    // -------------------------------------------------------------
    $sql_coil = "SELECT c.ALLOY, c.WIDTH, c.THICKNESS, c.COIL_BALANCEWEIGHT 
                 FROM COILPROD1 c 
                 WHERE c.COIL_NO = :coil_no";
    $stmt_coil = $conn->prepare($sql_coil);
    $stmt_coil->bindParam(':coil_no', $strCoilNo, PDO::PARAM_STR);
    $stmt_coil->execute();
    $coil = $stmt_coil->fetch(PDO::FETCH_ASSOC);

    if (!$coil) {
        throw new Exception("ไม่พบข้อมูล Coil No: {$strCoilNo}");
    }

    $dblCoilActualWeight = (float)($coil['COIL_BALANCEWEIGHT'] ?? 0);

    // คำนวณสตริง Recipe Key
    $c_alloy = trim($coil['ALLOY']);
    $c_width = sprintf("%.0f", (float)$coil['WIDTH']);
    $c_thick = sprintf("%.3f", (float)$coil['THICKNESS']);
    $j_thick = sprintf("%.3f", $dblThicknessFinal);

    $target_recipe_key = $c_alloy . ':' . $c_width . '-' . $c_thick . '>' . $j_thick;

    // ค้นหา RECIPE Detail และ RSET_NO จาก CMRCMSTR1
    $sql_rcp = "SELECT TOP 1 RECIPE_NO, RECIPE_ITEM, RSET_NO , TOTAL_PASS
                FROM CMRCMSTR1 
                WHERE RECIPE_NO = :recipe_key 
                ORDER BY RECIPE_ITEM ASC";
    $stmt_rcp = $conn->prepare($sql_rcp);
    $stmt_rcp->bindParam(':recipe_key', $target_recipe_key, PDO::PARAM_STR);
    $stmt_rcp->execute();
    $rcp = $stmt_rcp->fetch(PDO::FETCH_ASSOC);

    $strRecipeNo   = $rcp['RECIPE_NO'] ?? $target_recipe_key;
    
    // ดึงค่า RECIPE_ITEM มาใช้กับทั้ง 3 ตัวแปร (RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS)
    $intRecipeItem = (int)($rcp['RECIPE_ITEM'] ?? 1);
    $intTotalPass  = (int)($rcp['TOTAL_PASS'] ?? $intRecipeItem);
    $intCurrentPass= 0; // เริ่มต้น Current Pass เป็น 0  

    $strRollSetNo  = trim($rcp['RSET_NO'] ?? '');


    // -------------------------------------------------------------
    // 📍 หาค่า COIL_NEXTPROCESS จาก JOB_WORKPROCESS
    // ตัวอย่าง $strWorkProcess: "CS>IS>BA>SS>WS" -> ตัดหลัง "CS>IS>" ได้ "BA"
    // -------------------------------------------------------------
    $strNextProcess = '';
    
    // ค้นหาตำแหน่งหลังจากคำว่า CS>IS>
    $prefix = 'CS>IS>';
    $pos = strpos($strWorkProcess, $prefix);

    if ($pos !== false) {
        // ดึงเฉพาะ String หลัง CS>IS>
        $afterPrefix = substr($strWorkProcess, $pos + strlen($prefix));
        
        // แยก String ด้วยตัวแบ่ง > แล้วเอาค่าแรกสุดมาใช้
        $processArray = explode('>', $afterPrefix);
        $strNextProcess = trim($processArray[0] ?? '');
    } else {
        // Fallback: หากรูปแบบไม่ตรง ให้แยก > ทั้งหมดแล้วเอาตัวแรกที่ไม่ใช่ CS หรือ IS
        $processArray = explode('>', $strWorkProcess);
        foreach ($processArray as $proc) {
            $proc = trim($proc);
            if ($proc !== '' && $proc !== 'CS' && $proc !== 'IS') {
                $strNextProcess = $proc;
                break;
            }
        }
    }

    $strToLocation = $strNextProcess;    

    // -------------------------------------------------------------
    // STEP 3: UPDATE COILPROD1
    // -------------------------------------------------------------
    $sql_upd_coil = "UPDATE COILPROD1 
                     SET JOB_PROCESS = :job_process, 
                         RSET_NO = :rset_no, 
                         RECIPE_NO = :recipe_no, 
                         RECIPE_ITEM = :recipe_item, 
                         TOTAL_PASS = :total_pass, 
                         CURRENT_PASS = :current_pass, 
                         THICKNESS_FINAL = :thickness_final, 
                         COIL_WORKPROCESS = :coil_workprocess, 
                         COIL_NEXTPROCESS = :coil_nextprocess, 
                         USE_FORPROCESS = :use_forprocess, 
                         LOCATION_ID = :location_id, 
                         CDML_STATUS = 'I' 
                     WHERE COIL_NO = :coil_no";

    $stmt_upd_coil = $conn->prepare($sql_upd_coil);
    $stmt_upd_coil->bindParam(':job_process', $strJobProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':rset_no', $strRollSetNo, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':recipe_no', $strRecipeNo, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':recipe_item', $intRecipeItem, PDO::PARAM_INT);
    $stmt_upd_coil->bindParam(':total_pass', $intTotalPass, PDO::PARAM_INT);
    $stmt_upd_coil->bindParam(':current_pass', $intCurrentPass, PDO::PARAM_INT);
    $stmt_upd_coil->bindParam(':thickness_final', $dblThicknessFinal);
    $stmt_upd_coil->bindParam(':coil_workprocess', $strWorkProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':coil_nextprocess', $strNextProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':use_forprocess', $strUseForProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':location_id', $strToLocation, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':coil_no', $strCoilNo, PDO::PARAM_STR);
    $stmt_upd_coil->execute();

    // -------------------------------------------------------------
    // STEP 4: คำนวณค่า Weight / Piece ตาม เงื่อนไข WorkProcess
    // -------------------------------------------------------------
    $cm_weight = 0; $cm_piece = 0;
    $ba_weight = 0; $ba_piece = 0;

    // ตรวจสอบเงื่อนไขด้วยการเช็คการมีอยู่ของคำ

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
    // STEP 5: UPDATE JOBORDER1 (สะสมค่า Weight/Piece)
    // -------------------------------------------------------------
    $sql_upd_job = "UPDATE JOBORDER1 
                    SET JOB_SELECTCOLDMILLWEIGHT = ISNULL(JOB_SELECTCOLDMILLWEIGHT, 0) + :cm_weight, 
                        JOB_SELECTCOLDMILLPIECE  = ISNULL(JOB_SELECTCOLDMILLPIECE, 0) + :cm_piece, 
                        JOB_SELECTBATCHANNEALWEIGHT = ISNULL(JOB_SELECTBATCHANNEALWEIGHT, 0) + :ba_weight, 
                        JOB_SELECTBATCHANNEALPIECE  = ISNULL(JOB_SELECTBATCHANNEALPIECE, 0) + :ba_piece, 
                        JOB_UPDATEDATE = GETDATE(),
                        JOB_UPDATE = :iduser_func
                    WHERE JOB_ORDER = :job_order";

    $stmt_upd_job = $conn->prepare($sql_upd_job);
    $stmt_upd_job->bindParam(':cm_weight', $cm_weight);
    $stmt_upd_job->bindParam(':cm_piece', $cm_piece);
    $stmt_upd_job->bindParam(':ba_weight', $cm_weight);
    $stmt_upd_job->bindParam(':ba_piece', $cm_piece);
    $stmt_upd_job->bindParam(':iduser_func', $iduser_func, PDO::PARAM_STR);
    $stmt_upd_job->bindParam(':job_order', $strJobProcess, PDO::PARAM_STR);
    $stmt_upd_job->execute();

    // Commit การทำงานทั้งหมด
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Usage record Coil [{$strCoilNo}] in Job [{$strJobProcess}] succeed!"
    ]);

} catch (Exception $e) {
    // ย้อนคืนข้อมูลกรณีเกิดข้อผิดพลาด
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}