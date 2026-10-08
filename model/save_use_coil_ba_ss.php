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
    // เริ่ม Transaction
    $conn->beginTransaction();

    // -------------------------------------------------------------
    // STEP 1: ดึงข้อมูลที่จำเป็นจาก JOBORDER1
    // -------------------------------------------------------------
    $sql_job = "SELECT JOB_WORKPROCESS, USE_FORPROCESS, THICKNESS, JOB_RELEASEPIECE, 
                       JOB_SELECTSLITPIECE, JOB_SELECTBATCHANNEALPIECE 
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

    // -------------------------------------------------------------
    // 📍 [ตรวจสอบเงื่อนไข RELEASE PIECE] 
    // เช็คแยกตามประเภท WorkProcess (SS หรือ BA)
    // -------------------------------------------------------------
    $rel_piece = (float)($job['JOB_RELEASEPIECE'] ?? 0);
    $hasSS = (strpos($strWorkProcess, 'SS') !== false);
    $hasBA = (strpos($strWorkProcess, 'BA') !== false);

    $current_piece = 0;
    if ($hasBA) {
        $current_piece = (float)($job['JOB_SELECTBATCHANNEALPIECE'] ?? 0);
    } else if ($hasSS) {
        $current_piece = (float)($job['JOB_SELECTSLITPIECE'] ?? 0);
    }

    if ($rel_piece > 0 && $current_piece >= $rel_piece) {
        throw new Exception('Unable to save because the number of Pieces has reached the Release Piece limit.');
    }

    // -------------------------------------------------------------
    // STEP 2: ดึงข้อมูลคอยล์จาก COILPROD1
    // -------------------------------------------------------------
    $sql_coil = "SELECT c.COIL_BALANCEWEIGHT 
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
                         COIL_WORKPROCESS = :coil_workprocess, 
                         COIL_NEXTPROCESS = :coil_nextprocess, 
                         USE_FORPROCESS = :use_forprocess, 
                         LOCATION_ID = :location_id, 
                         CDML_STATUS = 'I' 
                     WHERE COIL_NO = :coil_no";

    $stmt_upd_coil = $conn->prepare($sql_upd_coil);
    $stmt_upd_coil->bindParam(':job_process', $strJobProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':coil_workprocess', $strWorkProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':coil_nextprocess', $strNextProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':use_forprocess', $strUseForProcess, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':location_id', $strToLocation, PDO::PARAM_STR);
    $stmt_upd_coil->bindParam(':coil_no', $strCoilNo, PDO::PARAM_STR);
    $stmt_upd_coil->execute();

    // -------------------------------------------------------------
    // STEP 4: คำนวณค่า Weight / Piece ตาม เงื่อนไข WorkProcess (SS และ BA)
    // -------------------------------------------------------------
    $ss_weight = 0; $ss_piece = 0;
    $ba_weight = 0; $ba_piece = 0;

    if ($hasSS && $hasBA) {
        $ss_weight = $dblCoilActualWeight;
        $ss_piece  = 1;
        $ba_weight = $dblCoilActualWeight;
        $ba_piece  = 1;
    } elseif ($hasSS) {
        $ss_weight = $dblCoilActualWeight;
        $ss_piece  = 1;
    } elseif ($hasBA) {
        $ba_weight = $dblCoilActualWeight;
        $ba_piece  = 1;
    }

    // -------------------------------------------------------------
    // STEP 5: UPDATE JOBORDER1 (เปลี่ยนเป็น JOB_SELECTSLITWEIGHT และ JOB_SELECTSLITPIECE)
    // -------------------------------------------------------------
    $sql_upd_job = "UPDATE JOBORDER1 
                    SET JOB_SELECTSLITWEIGHT       = ISNULL(JOB_SELECTSLITWEIGHT, 0) + :ss_weight, 
                        JOB_SELECTSLITPIECE        = ISNULL(JOB_SELECTSLITPIECE, 0) + :ss_piece, 
                        JOB_SELECTBATCHANNEALWEIGHT = ISNULL(JOB_SELECTBATCHANNEALWEIGHT, 0) + :ba_weight, 
                        JOB_SELECTBATCHANNEALPIECE  = ISNULL(JOB_SELECTBATCHANNEALPIECE, 0) + :ba_piece, 
                        JOB_UPDATEDATE = GETDATE(),
                        JOB_UPDATE = :iduser_func
                    WHERE JOB_ORDER = :job_order";

    $stmt_upd_job = $conn->prepare($sql_upd_job);
    $stmt_upd_job->bindParam(':ss_weight', $ss_weight);
    $stmt_upd_job->bindParam(':ss_piece', $ss_piece);
    $stmt_upd_job->bindParam(':ba_weight', $ba_weight);
    $stmt_upd_job->bindParam(':ba_piece', $ba_piece);
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