<?php
// เริ่มต้น Session และตั้งค่า Response Header ให้ส่งกลับเป็น JSON
session_start();
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ Authentication
if (!isset($_SESSION['ID'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unauthorized: กรุณาเข้าสู่ระบบก่อนทำรายการ'
    ]);
    exit();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล PDO
require_once "../dbcon_mats-new.php";

// ตรวจสอบว่าส่งข้อมูลมาแบบ POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // รับค่าและทำความสะอาดข้อมูล (Sanitize)
    $saleorder_no    = isset($_POST['saleorder_no']) ? trim($_POST['saleorder_no']) : '';
    $new_customer_id = isset($_POST['new_customer_id']) ? trim($_POST['new_customer_id']) : '';

    // ตรวจสอบความถูกต้องของข้อมูล
    if (empty($saleorder_no) || empty($new_customer_id)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ Sale Order No และ New Customer ID'
        ]);
        exit();
    }

    try {
        // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล (หากตารางใดตารางหนึ่งมีปัญหาจะ Rollback ทั้งหมด)
        $conn->beginTransaction();

        // 1. อัปเดตตาราง CSTMORDR1
        $sql1 = "UPDATE CSTMORDR1 
                 SET CSTMSPPL_ID = :cust_id 
                 WHERE SALEORDER_NO = :so_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bindParam(':cust_id', $new_customer_id, PDO::PARAM_STR);
        $stmt1->bindParam(':so_no', $saleorder_no, PDO::PARAM_STR);
        $stmt1->execute();

        // 2. อัปเดตตาราง CSTMORDR2
        $sql2 = "UPDATE CSTMORDR2 
                 SET CSTMSPPL_ID = :cust_id 
                 WHERE SALEORDER_NO = :so_no";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bindParam(':cust_id', $new_customer_id, PDO::PARAM_STR);
        $stmt2->bindParam(':so_no', $saleorder_no, PDO::PARAM_STR);
        $stmt2->execute();

        // บันทึกการเปลี่ยนแปลงข้อมูล
        $conn->commit();

        // ส่ง Response สำเร็จกลับไปยัง JavaScript (AJAX)
        echo json_encode([
            'status'  => 'success',
            'message' => 'อัปเดต Customer ID สำเร็จเรียบร้อยแล้ว'
        ]);

    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาดให้ Rollback การแก้ไขกลับทั้งหมด
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        echo json_encode([
            'status'  => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid Request Method'
    ]);
}