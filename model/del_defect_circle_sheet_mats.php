<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบ Session การเข้าใช้งาน
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include '../function_mats.php'; // ไฟล์เชื่อมต่อ Database ($conn)[cite: 1]

include '../dbcon_mats-new.php';

// 2. รับและทำความสะอาดข้อมูล Input (Primary Key)[cite: 1]
$pd_no   = isset($_POST['pd_no']) ? trim($_POST['pd_no']) : '';
$defect_id = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';

// 3. Validation
if (empty($pd_no) || empty($defect_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required Primary Key fields']);
    exit();
}

try {
    $sql = "DELETE FROM CRSHINSP4 
            WHERE PRODUCT_NO = :pd_no 
            AND DEFECT_ID = :defect_id";

    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':pd_no'   => $pd_no,
        ':defect_id' => $defect_id
    ]);

    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Defect record deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record not found or already deleted']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>