<?php
header('Content-Type: application/json; charset=utf-8');
include '../dbcon_mats-new.php';

$req_no = $_GET['req_no'] ?? '';

if (!empty($req_no)) {
    $sql = "SELECT REQUEST_NO, PRODUCT_NO, COIL_NO, PRODUCT_ID, ALLOY, TEMPER, GRADE, 
                   SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, [LENGTH], 
                   PRODUCT_WEIGHT, REMELT_REASON, RESPONS_TYPE 
            FROM PRODRMLT2 
            WHERE REQUEST_NO = :req_no";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':req_no' => $req_no]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);
    exit;
}

echo json_encode([]);