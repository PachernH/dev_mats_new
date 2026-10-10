<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include("../dbcon_mats-new.php");

$product_no = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';
$job_order  = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';

if (empty($product_no) || empty($job_order)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอก Product No และ Job Order ให้ครบถ้วน']);
    exit();
}

try {
    // 0. ตรวจสอบก่อนว่า Job Order มีตัวตนอยู่ในตาราง JOBORDER1 หรือไม่
    $stmtCheckJob = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM FROM [MATS-NEW].dbo.JOBORDER1 WHERE JOB_ORDER = :job_no");
    $stmtCheckJob->execute([':job_no' => $job_order]);
    $jobInfo = $stmtCheckJob->fetch(PDO::FETCH_ASSOC);

    if (!$jobInfo) {
        echo json_encode(['status' => 'error', 'message' => "ไม่พบรหัส Job Order '{$job_order}' ในระบบ JOBORDER1"]);
        exit();
    }

    $sqlSelectFields = "
        SUM(ISNULL(CRSH_ACTUALWEIGHT, 0)) AS totalAct, SUM(ISNULL(CRSH_ACTUALPIECE, 0)) AS totalActP,
        SUM(ISNULL(CRSH_PRODUCEWEIGHT, 0)) AS totalPro, SUM(ISNULL(CRSH_PRODUCEPIECE, 0)) AS totalProP,
        SUM(ISNULL(CRSH_STRETCHERWEIGHT, 0)) AS totalStr, SUM(ISNULL(CRSH_STRETCHERPIECE, 0)) AS totalStrP,
        SUM(ISNULL(CRSH_CUTSHEETWEIGHT, 0)) AS totalcutsh, SUM(ISNULL(CRSH_CUTSHEETPIECE, 0)) AS totalcutshP,
        SUM(ISNULL(CRSH_SHEARWEIGHT, 0)) AS totalsher, SUM(ISNULL(CRSH_SHEARPIECE, 0)) AS totalsherP,
        SUM(ISNULL(CRSH_PUNCHWEIGHT, 0)) AS totalPun, SUM(ISNULL(CRSH_PUNCHPIECE, 0)) AS totalPunP,
        SUM(ISNULL(CRSH_BATCHANNEALWEIGHT, 0)) AS totalBatch, SUM(ISNULL(CRSH_BATCHANNEALPIECE, 0)) AS totalBatchP,
        SUM(ISNULL(CRSH_TRANSFERWEIGHT, 0)) AS totaltran, SUM(ISNULL(CRSH_TRANSFERPIECE, 0)) AS totaltranP,
        SUM(ISNULL(CRSH_ANNEALWEIGHT, 0)) AS totalAnn, SUM(ISNULL(CRSH_ANNEALPIECE, 0)) AS totalAnnP,
        SUM(ISNULL(CRSH_COMBINEWEIGHT, 0)) AS totalCom, SUM(ISNULL(CRSH_COMBINEPIECE, 0)) AS totalComP,
        SUM(ISNULL(CRSH_SORTWEIGHT, 0)) AS TotalSort, SUM(ISNULL(CRSH_SORTPIECE, 0)) AS TotalSortP,
        SUM(ISNULL(CRSH_TAKEOUTWEIGHT, 0)) AS totalTake, SUM(ISNULL(CRSH_TAKEOUTPIECE, 0)) AS totalTakeP,
        SUM(ISNULL(CRSH_PACKWEIGHT, 0)) AS totalPack, SUM(ISNULL(CRSH_PACKPIECE, 0)) AS totalPackP
    ";

    // 1. ดึงยอดปัจจุบันเดิมของ Job Order (Before)
    $sqlBefore = "SELECT {$sqlSelectFields} FROM [MATS-NEW].dbo.CRSHPROD1 WHERE JOB_ORDER = :job_order";
    $stmtBefore = $conn->prepare($sqlBefore);
    $stmtBefore->execute([':job_order' => $job_order]);
    $beforeData = $stmtBefore->fetch(PDO::FETCH_ASSOC);

    // 2. ดึงยอดของ Product No ที่จะนำมาบวกผูกเพิ่ม (Add)
    $sqlAdd = "SELECT {$sqlSelectFields} FROM [MATS-NEW].dbo.CRSHPROD1 WHERE PRODUCT_NO = :product_no";
    $stmtAdd = $conn->prepare($sqlAdd);
    $stmtAdd->execute([':product_no' => $product_no]);
    $addData = $stmtAdd->fetch(PDO::FETCH_ASSOC);

    // 3. คำนวณยอดรวมใหม่ (After = Before + Add)
    $afterData = [];
    foreach ($beforeData as $key => $val) {
        $befVal = (float)($beforeData[$key] ?? 0);
        $addVal = (float)($addData[$key] ?? 0);
        $aftVal = $befVal + $addVal; // บวกยอดเพิ่มเข้าไป

        $afterData[$key]  = round($aftVal);
        $beforeData[$key] = round($befVal);
        $addData[$key]    = round($addVal);
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'before' => $beforeData,
            'add'    => $addData,
            'after'  => $afterData
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}