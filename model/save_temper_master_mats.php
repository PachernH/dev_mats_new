<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบและตรวจสอบสิทธิ์
session_start();

// กำหนด Header ส่งคืนค่าเป็นรูปแบบ JSON รองรับอักขระภาษาไทย
header('Content-Type: application/json; charset=utf-8');

// ตั้งค่าโซนเวลามาตรฐาน
date_default_timezone_set("Asia/Bangkok");

$response = array();
$response['message'] = false;

// ตรวจสอบว่าได้รับการส่งค่าแบบ POST มาจริงหรือไม่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ดึงไฟล์เชื่อมต่อฐานข้อมูล SQL Server (PDO)
    if (!file_exists('../dbcon_mats-new.php')) {
        $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    include('../dbcon_mats-new.php');

    // 1. รับค่าและทำความสะอาดข้อมูลส่วนที่เป็น String/มาสเตอร์
    $data_temper      = isset($_POST['data_temper']) ? htmlspecialchars(trim($_POST['data_temper']), ENT_QUOTES, 'UTF-8') : '';
    $data_desc      = isset($_POST['data_desc']) ? htmlspecialchars(trim($_POST['data_desc']), ENT_QUOTES, 'UTF-8') : '';
    
    // ตรวจสอบฟิลด์บังคับที่ไม่สามารถเป็นค่าว่างได้ (Not Null)
    if (empty($data_temper)) {
        $response['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // ตรวจสอบรหัส ALLOY ซ้ำในฐานข้อมูลก่อนเพื่อป้องกัน Data Duplicate
        $checkSql = "SELECT COUNT(*) FROM TMPRMSTR1 WHERE TEMPER = :data_temper";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':data_temper' => $data_temper]);
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'รหัส TEMPER นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT ระบุคอลัมน์ครบทั้ง 43 ลำดับ (ยกเว้นตัวแปรประเภทลบวิเคราะห์และระบบวันเวลาอัตโนมัติ)
        $sql = "INSERT INTO TMPRMSTR1 (
                    TEMPER, DESCRIPTION
                ) VALUES (
                    :data_temper, :description
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์แบบระบุตัวแปร (Named placeholders) ป้องกัน SQL Injection อย่างเด็ดขาด
        $stmt->bindParam(':data_temper', $data_temper, PDO::PARAM_STR);
        $stmt->bindParam(':description', $data_desc, PDO::PARAM_STR);
        
        // ประมวลผลคำสั่งเขียนลงฐานข้อมูล
        if ($stmt->execute()) {
            $response['message'] = true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['error'] = 'ไม่สามารถบันทึกข้อมูลได้: ' . $errorInfo[2];
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'รูปแบบการส่งข้อมูลไม่ถูกต้อง (Invalid Request Mode)';
}

// ส่งค่ากลับไปยังส่วนหน้าจอเบราว์เซอร์
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>