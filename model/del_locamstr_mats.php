<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['message' => false, 'error' => 'Session expired. Please login again.']);
    exit();
}

include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loca_id = isset($_POST['loca_id']) ? trim($_POST['loca_id']) : '';

    if ($loca_id === '') {
        echo json_encode(['message' => false, 'error' => 'Invalid Location ID.']);
        exit();
    }

    try {
        $sql = "DELETE FROM LOCAMSTR1 WHERE LOCA_ID = :loca_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':loca_id' => $loca_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => true]);
        } else {
            echo json_encode(['message' => false, 'error' => 'Record not found or already deleted.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['message' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['message' => false, 'error' => 'Invalid request method.']);
}
?>