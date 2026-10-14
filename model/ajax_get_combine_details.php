<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include("../dbcon_mats-new.php");

$product_no = isset($_POST['product_no']) ? trim($_POST['product_no']) : '';

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Product No']);
    exit();
}

try {
    // 1. ดึงข้อมูลสรุปของ Main Product จาก CRSHPROD1
    $stmtMain = $conn->prepare("SELECT CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_COMBINEWEIGHT, CRSH_COMBINEPIECE FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmtMain->execute([':pno' => $product_no]);
    $mainData = $stmtMain->fetch(PDO::FETCH_ASSOC);

    $actual_weight = $mainData ? (float)($mainData['CRSH_ACTUALWEIGHT'] ?? 0) : 0;
    $actual_piece  = $mainData ? (int)($mainData['CRSH_ACTUALPIECE'] ?? 0) : 0;
    $combine_weight = $mainData ? (float)($mainData['CRSH_COMBINEWEIGHT'] ?? 0) : 0;
    $combine_piece  = $mainData ? (int)($mainData['CRSH_COMBINEPIECE'] ?? 0) : 0;

    // 2. ดึงรายการ Palette ที่ผูกรวมจาก CMBNPROD1
    $stmtCmbn = $conn->prepare("SELECT PRODUCT_NO, PRODUCT_REFERENCE, CMBN_WEIGHT, CMBN_PIECE, CMBN_OPERATOR FROM CMBNPROD1 WHERE PRODUCT_NO = :pno ORDER BY CMBN_DATE ASC");
    $stmtCmbn->execute([':pno' => $product_no]);
    $list = $stmtCmbn->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $list,
        'summary' => [
            'actual_weight' => number_format($actual_weight, 2),
            'actual_piece'  => number_format($actual_piece),
            'combine_weight' => number_format($combine_weight, 2),
            'combine_piece'  => number_format($combine_piece)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>