<?php
session_start();
include('../dbcon_mats-new.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT TOP(50) p.PRODUCT_NO, p.PRODUCT_ID, p.PRODUCT_WEIGHT, p.RESPONS_TYPE 
            FROM PRODRMLT2 AS p
            WHERE p.REMELT_STATUS = 'RM' AND p.MELT_STATUS <> 'MT'
            ORDER BY p.REMELT_DATE DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $data]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>