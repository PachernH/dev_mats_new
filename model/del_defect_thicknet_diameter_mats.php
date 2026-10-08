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
    $pd_no   = isset($_POST['pd_no']) ? trim($_POST['pd_no']) : '';
    $defect_id = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
    $side_no = isset($_POST['side_no']) ? trim($_POST['side_no']) : '';

    // 4. ตรวจสอบความถูกต้องของ Primary Keys
    if (!empty($pd_no) && !empty($defect_id) > 0) {
        try {
            // เตรียมคำสั่ง SQL DELETE
            $sql = "DELETE FROM CRSHINSP2 
                    WHERE PRODUCT_NO = :pd_no 
                      AND PRODUCT_SIDE = :side_no
                      AND PRODUCT_ITEM = :defect_id ";

            $stmt = $conn->prepare($sql);
            
            // Bind Parameters เพื่อป้องกัน SQL Injection
            $stmt->bindParam(':pd_no', $pd_no, PDO::PARAM_STR);
            $stmt->bindParam(':side_no', $side_no, PDO::PARAM_STR);
            $stmt->bindParam(':defect_id', $defect_id, PDO::PARAM_STR);

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