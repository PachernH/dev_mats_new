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
    $data_uom       = isset($_POST['data_uom']) ? htmlspecialchars(trim($_POST['data_uom']), ENT_QUOTES, 'UTF-8') : '';
    $data_desc      = isset($_POST['data_desc']) ? htmlspecialchars(trim($_POST['data_desc']), ENT_QUOTES, 'UTF-8') : '';
    $data_uom_rate  = isset($_POST['data_uom_rate']) ? htmlspecialchars(trim($_POST['data_uom_rate']), ENT_QUOTES, 'UTF-8') : 0;
    $data_uom_type  = isset($_POST['data_uom_type']) ? htmlspecialchars(trim($_POST['data_uom_type']), ENT_QUOTES, 'UTF-8') : '';

    switch ($data_uom_type) {
    case "D":
        if(strtoupper($data_uom) == strtoupper('mm')){
            $data_uom_rate = 1;
        }
        break; 
                                        
    case "W":
        if(strtoupper($data_uom) == strtoupper('kg')){
            $data_uom_rate = 1;
        }
        break; 

    case "P":
        $data_uom_rate = 0;
        break;         
    }
 
    
    // ตรวจสอบฟิลด์บังคับที่ไม่สามารถเป็นค่าว่างได้ (Not Null)
    if (empty($data_uom)) {
        $response['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // ตรวจสอบรหัส ALLOY ซ้ำในฐานข้อมูลก่อนเพื่อป้องกัน Data Duplicate
        $checkSql = "SELECT COUNT(*) FROM UOMSMSTR1 WHERE UOM = :data_uom";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':data_uom' => $data_uom]);
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'รหัส นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT ระบุคอลัมน์ครบทั้ง 43 ลำดับ (ยกเว้นตัวแปรประเภทลบวิเคราะห์และระบบวันเวลาอัตโนมัติ)
        $sql = "INSERT INTO UOMSMSTR1 (
                    UOM, DESCRIPTION, CONVERT_RATE, UOM_TYPE
                ) VALUES (
                    :data_uom, :description, :data_uom_rate, :data_uom_type
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์แบบระบุตัวแปร (Named placeholders) ป้องกัน SQL Injection อย่างเด็ดขาด
        $stmt->bindParam(':data_uom', $data_uom, PDO::PARAM_STR);
        $stmt->bindParam(':description', $data_desc, PDO::PARAM_STR);
        $stmt->bindParam(':data_uom_rate', $data_uom_rate, PDO::PARAM_STR);
        $stmt->bindParam(':data_uom_type', $data_uom_type, PDO::PARAM_STR);
        
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