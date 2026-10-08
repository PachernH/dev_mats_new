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
$coil_no   = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$defect_id = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
$pass_no   = isset($_POST['pass_no']) ? intval($_POST['pass_no']) : 0;

// 3. Validation
if (empty($coil_no) || empty($defect_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required Primary Key fields']);
    exit();
}

try {
    // 4. ลบข้อมูลโดยใช้ Primary Key: (COIL_NO, DEFECT_ID, PASS_NO) และ PROCESS = 'CD'[cite: 1]
    $sql = "DELETE FROM COILINSP2 
            WHERE COIL_NO = :coil_no 
            AND DEFECT_ID = :defect_id 
            AND PASS_NO = :pass_no 
            AND PROCESS = 'CD'";

    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':coil_no'   => $coil_no,
        ':defect_id' => $defect_id,
        ':pass_no'   => $pass_no
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