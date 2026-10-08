<?php
header('Content-Type: application/json; charset=utf-8');
include '../dbcon_mats-new.php';

$year2Digits = date('y'); // 26
$prefix = "RM-" . $year2Digits . "-";

$sql = "SELECT MAX(REQUEST_NO) AS max_no FROM PRODRMLT1 WHERE REQUEST_NO LIKE :prefix";
$stmt = $conn->prepare($sql);
$stmt->execute([':prefix' => $prefix . '%']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['max_no']) {
    $lastSeq = (int)substr($row['max_no'], -4);
    $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
} else {
    $nextSeq = '0001';
}

echo json_encode(['request_no' => $prefix . $nextSeq]);