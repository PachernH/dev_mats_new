<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบ Session การใช้งาน
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// 2. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล [MATS-NEW]
require_once '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3. รับค่าและ Sanitize ข้อมูลที่ส่งมาจาก AJAX
    $coil_no   = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $defect_id = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
    $pass_no   = isset($_POST['pass_no']) ? intval($_POST['pass_no']) : 0;
    $process   = 'CM'; // กำหนดให้ลบเฉพาะ Process Cold Mill

    // 4. ตรวจสอบความถูกต้องของ Primary Keys
    if (!empty($coil_no) && !empty($defect_id) && $pass_no > 0) {
        try {
            // เตรียมคำสั่ง SQL DELETE
            $sql = "DELETE FROM COILINSP2 
                    WHERE COIL_NO = :coil_no 
                      AND DEFECT_ID = :defect_id 
                      AND PASS_NO = :pass_no 
                      AND PROCESS = :process";

            $stmt = $conn->prepare($sql);
            
            // Bind Parameters เพื่อป้องกัน SQL Injection
            $stmt->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
            $stmt->bindParam(':defect_id', $defect_id, PDO::PARAM_STR);
            $stmt->bindParam(':pass_no', $pass_no, PDO::PARAM_INT);
            $stmt->bindParam(':process', $process, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // ส่งผลลัพธ์กลับแบบ JSON สอดคล้องกับ AJAX ในหน้า Front-End
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Record deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Failed to delete record'
                ]);
            }

        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid parameters provided'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid request method'
    ]);
}