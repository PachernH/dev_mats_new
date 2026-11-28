<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['message' => false, 'error' => 'Session expired. Please login again.']);
    exit();
}

include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loca_id     = isset($_POST['loca_id']) ? strtoupper(trim($_POST['loca_id'])) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $department  = isset($_POST['department']) ? strtoupper(trim($_POST['department'])) : '';

    // 1. ตรวจสอบค่าว่างของ Primary Key / Not Null
    if ($loca_id === '') {
        echo json_encode(['message' => false, 'error' => 'LOCATION ID is required.']);
        exit();
    }

    // 2. ตรวจสอบความยาวตามโครงสร้างตาราง LOCAMSTR1
    if (mb_strlen($loca_id, 'UTF-8') > 10) {
        echo json_encode(['message' => false, 'error' => 'LOCATION ID must not exceed 10 characters.']);
        exit();
    }
    if (mb_strlen($description, 'UTF-8') > 50) {
        echo json_encode(['message' => false, 'error' => 'DESCRIPTION must not exceed 50 characters.']);
        exit();
    }
    if (mb_strlen($department, 'UTF-8') > 10) {
        echo json_encode(['message' => false, 'error' => 'DEPARTMENT must not exceed 10 characters.']);
        exit();
    }

    try {
        // 3. ตรวจสอบว่ามี LOCA_ID นี้ในระบบแล้วหรือไม่
        $checkSql = "SELECT COUNT(*) AS CNT FROM LOCAMSTR1 WHERE LOCA_ID = :loca_id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':loca_id' => $loca_id]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['CNT'] > 0) {
            echo json_encode(['message' => false, 'error' => "Location ID '{$loca_id}' already exists."]);
            exit();
        }

        // 4. บันทึกข้อมูล (CREATE_DATETIME ใช้ค่า default getdate() ของ SQL Server อัตโนมัติ)
        $sql = "INSERT INTO LOCAMSTR1 (LOCA_ID, DESCRIPTION, DEPARTMENT) 
                VALUES (:loca_id, :description, :department)";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':loca_id'     => $loca_id,
            ':description' => ($description !== '') ? $description : null,
            ':department'  => ($department !== '') ? $department : null
        ]);

        if ($result) {
            echo json_encode(['message' => true]);
        } else {
            echo json_encode(['message' => false, 'error' => 'Failed to insert record.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['message' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['message' => false, 'error' => 'Invalid request method.']);
}
?>