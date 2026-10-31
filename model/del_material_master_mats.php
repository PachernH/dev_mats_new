<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['message' => false, 'error' => 'Unauthorized access.']);
    exit();
}

include("../dbcon_mats-new.php");

$material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;

if ($material_id <= 0) {
    echo json_encode(['message' => false, 'error' => 'Invalid Material ID.']);
    exit();
}

try {
    $sql = "DELETE FROM MATERIALS WHERE material_id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$material_id]);

    if ($result) {
        echo json_encode(['message' => true]);
    } else {
        echo json_encode(['message' => false, 'error' => 'Failed to delete data.']);
    }
} catch (PDOException $e) {
    echo json_encode(['message' => false, 'error' => $e->getMessage()]);
}
?>