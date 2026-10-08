<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include("dbcon_mats-new.php");

$target_no = isset($_GET['target_no']) ? trim($_GET['target_no']) : '';
$ref_no    = isset($_GET['ref_no']) ? trim($_GET['ref_no']) : '';

$response = [
    'status' => 'success',
    'main'  => ['bottom_weight' => 0, 'net_weight' => 0, 'piece' => 0],
    'split' => ['bottom_weight' => 0, 'net_weight' => 0, 'piece' => 0]
];

if (!empty($target_no) || !empty($ref_no)) {
    // ดึงข้อมูลทั้งสองรายการพร้อมกันจาก CRSHPROD1
    $sql = "SELECT PRODUCT_NO, CRSH_BOTTOMWEIGHT, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE 
            FROM CRSHPROD1 
            WHERE PRODUCT_NO IN (:target_no, :ref_no)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':target_no', $target_no, PDO::PARAM_STR);
    $stmt->bindParam(':ref_no', $ref_no, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $p_no = trim($row['PRODUCT_NO']);
        if ($p_no === $ref_no) {
            // Ref Product คือ Main Product
            $response['main'] = [
                'bottom_weight' => (float)($row['CRSH_BOTTOMWEIGHT'] ?? 0),
                'net_weight'    => (float)($row['CRSH_ACTUALWEIGHT'] ?? 0),
                'piece'         => (int)($row['CRSH_ACTUALPIECE'] ?? 0)
            ];
        } else if ($p_no === $target_no) {
            // Target Product No คือ Split Product
            $response['split'] = [
                'bottom_weight' => (float)($row['CRSH_BOTTOMWEIGHT'] ?? 0),
                'net_weight'    => (float)($row['CRSH_ACTUALWEIGHT'] ?? 0),
                'piece'         => (int)($row['CRSH_ACTUALPIECE'] ?? 0)
            ];
        }
    }
}

echo json_encode($response);