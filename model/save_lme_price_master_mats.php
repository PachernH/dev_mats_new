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
    $data_date      = isset($_POST['data_date']) ? htmlspecialchars(trim($_POST['data_date']), ENT_QUOTES, 'UTF-8') : '';
    $date_date_con = date('Y-m-d', strtotime($data_date));
    $data_price      = isset($_POST['data_price']) ? htmlspecialchars(trim($_POST['data_price']), ENT_QUOTES, 'UTF-8') : 0;
    
    // ตรวจสอบฟิลด์บังคับที่ไม่สามารถเป็นค่าว่างได้ (Not Null)
    if (empty($data_date)) {
        $response['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // ตรวจสอบรหัสซ้ำในฐานข้อมูลก่อนเพื่อป้องกัน Data Duplicate
        $checkSql = "SELECT COUNT(*) FROM LMEPMSTR1 WHERE LME_DATE = :data_date AND LME_PRICE = :data_price";
        $checkStmt = $conn->prepare($checkSql);
        
        //  แก้ไขจุดที่เป็นปัญหาร้ายแรง: ส่งค่า Tokens ทั้งหมดเข้าไปพร้อมกันในคำสั่ง execute เดียว
        $checkStmt->execute([
            ':data_date'  => $date_date_con,
            ':data_price' => $data_price
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'ข้อมูลวันที่และราคานี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT
        $sql = "INSERT INTO LMEPMSTR1 (
                    LME_DATE, LME_PRICE
                ) VALUES (
                    :data_date, :data_price
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์แบบระบุตัวแปร (Named placeholders) ป้องกัน SQL Injection อย่างเด็ดขาด
        $stmt->bindParam(':data_date', $date_date_con, PDO::PARAM_STR);
        $stmt->bindParam(':data_price', $data_price, PDO::PARAM_STR);
        
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