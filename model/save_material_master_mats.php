<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['message' => false, 'error' => 'Unauthorized access.']);
    exit();
}

include("../dbcon_mats-new.php");

$item_code  = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';
$item_name  = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$category   = isset($_POST['category']) ? trim($_POST['category']) : '';
$smelt_type = isset($_POST['smelt_type']) ? trim($_POST['smelt_type']) : '';
$unit       = isset($_POST['unit']) ? trim($_POST['unit']) : 'KG';

if ($item_code === '' || $item_name === '') {
    echo json_encode(['message' => false, 'error' => 'Please fill in required fields.']);
    exit();
}

try {
    // เช็คว่ามี item_code นี้อยู่แล้วหรือไม่
    $check_sql = "SELECT COUNT(*) FROM MATERIALS WHERE item_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$item_code]);
    if ($check_stmt->fetchColumn() > 0) {
        echo json_encode(['message' => false, 'error' => 'This Item Code already exists in the database.']);
        exit();
    }

    // Insert ข้อมูลลงตาราง MATERIALS
    $sql = "INSERT INTO MATERIALS (item_code, item_name, category, smelt_type, unit) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$item_code, $item_name, $category, $smelt_type, $unit]);

    if ($result) {
        echo json_encode(['message' => true]);
    } else {
        echo json_encode(['message' => false, 'error' => 'Failed to save data.']);
    }
} catch (PDOException $e) {
    echo json_encode(['message' => false, 'error' => $e->getMessage()]);
}
?>