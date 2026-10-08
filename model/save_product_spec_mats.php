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
    $alloy   = isset($_POST['inp_alloy']) ? htmlspecialchars(trim($_POST['inp_alloy']), ENT_QUOTES, 'UTF-8') : '';
    $temper  = isset($_POST['inp_temper']) ? htmlspecialchars(trim($_POST['inp_temper']), ENT_QUOTES, 'UTF-8') : '';
    $std     = isset($_POST['inp_std']) ? htmlspecialchars(trim($_POST['inp_std']), ENT_QUOTES, 'UTF-8') : '';

    // แก้ไขจุดที่ 1: ลบ Parameter ส่วนเกินของ floatval() ออก
    $thickness = isset($_POST['inp_thickness']) ? floatval(trim($_POST['inp_thickness'])) : 0.0;
    $width     = isset($_POST['inp_width']) ? floatval(trim($_POST['inp_width'])) : 0.0;
    $length    = isset($_POST['inp_length']) ? floatval(trim($_POST['inp_length'])) : 0.0;

    // แก้ไขจุดที่ 3: ปรับการตรวจสอบค่าว่าง (หลีกเลี่ยง empty() กับตัวเลข 0)
    if ($alloy === '' || $temper === '' || $_POST['inp_thickness'] === '' || $_POST['inp_width'] === '' || $_POST['inp_length'] === '') {
        $response['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับค่าส่วนที่เป็นตัวเลขทศนิยม (Data Type: real) และแปลงเป็น float
    $th_max  = isset($_POST['th_max']) ? floatval($_POST['th_max']) : 0.0;
    $th_min  = isset($_POST['th_min']) ? floatval($_POST['th_min']) : 0.0;
   
    $wi_max  = isset($_POST['wi_max']) ? floatval($_POST['wi_max']) : 0.0;
    $wi_min  = isset($_POST['wi_min']) ? floatval($_POST['wi_min']) : 0.0;

    $le_max  = isset($_POST['le_max']) ? floatval($_POST['le_max']) : 0.0;
    $le_min  = isset($_POST['le_min']) ? floatval($_POST['le_min']) : 0.0;

    $uts_max = isset($_POST['uts_max']) ? floatval($_POST['uts_max']) : 0.0;
    $uts_min = isset($_POST['uts_min']) ? floatval($_POST['uts_min']) : 0.0;

    $ys_max  = isset($_POST['ys_max']) ? floatval($_POST['ys_max']) : 0.0;
    $ys_min  = isset($_POST['ys_min']) ? floatval($_POST['ys_min']) : 0.0;

    $el_max  = isset($_POST['el_max']) ? floatval($_POST['el_max']) : 0.0;
    $el_min  = isset($_POST['el_min']) ? floatval($_POST['el_min']) : 0.0;

    $ea_max  = isset($_POST['ea_max']) ? floatval($_POST['ea_max']) : 0.0;
    $ea_min  = isset($_POST['ea_min']) ? floatval($_POST['ea_min']) : 0.0;

    try {
        // แก้ไขจุดที่ 2: รวม Array ของ Parameter ส่งไปใน execute() ทีเดียวพร้อมกัน
        $checkSql = "SELECT COUNT(*) FROM STNDMSTR1 WHERE ALLOY = :alloy AND TEMPER = :temper AND THICKNESS = :thickness AND WIDTH = :width AND LENGTH = :length";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([
            ':alloy'     => $alloy,
            ':temper'    => $temper,
            ':thickness' => $thickness,
            ':width'     => $width,
            ':length'    => $length
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'Specification นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT
        $sql = "INSERT INTO STNDMSTR1 (
                    ALLOY, TEMPER, THICKNESS, WIDTH, LENGTH, DESCRIPTION, STND_MAXTHICKNESS,
                    STND_MINTHICKNESS, STND_MAXWIDTH, STND_MINWIDTH, STND_MAXLENGTH, STND_MINLENGTH, STND_MAXUTS, STND_MINUTS, STND_MAXYIELDSTRENGTH,
                    STND_MINYIELDSTRENGTH, STND_MAXELONGATION, STND_MINELONGATION, STND_MAXEARING, STND_MINEARING
                ) VALUES (
                    :alloy, :temper, :thickness, :width, :length, :description, :th_max, :th_min,
                    :wi_max, :wi_min, :le_max, :le_min, :uts_max, :uts_min, :ys_max, :ys_min,
                    :el_max, :el_min, :ea_max, :ea_min
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์ ป้องกัน SQL Injection
        $stmt->bindParam(':alloy', $alloy, PDO::PARAM_STR);
        $stmt->bindParam(':temper', $temper, PDO::PARAM_STR);
        $stmt->bindParam(':description', $std, PDO::PARAM_STR);
        
        $stmt->bindValue(':thickness', $thickness);
        $stmt->bindValue(':width', $width);
        $stmt->bindValue(':length', $length);

        $stmt->bindValue(':th_max', $th_max);
        $stmt->bindValue(':th_min', $th_min);

        $stmt->bindValue(':wi_max', $wi_max);
        $stmt->bindValue(':wi_min', $wi_min);

        $stmt->bindValue(':le_max', $le_max);
        $stmt->bindValue(':le_min', $le_min);

        $stmt->bindValue(':uts_max', $uts_max);
        $stmt->bindValue(':uts_min', $uts_min);

        $stmt->bindValue(':ys_max', $ys_max);
        $stmt->bindValue(':ys_min', $ys_min);

        $stmt->bindValue(':el_max', $el_max);
        $stmt->bindValue(':el_min', $el_min);

        $stmt->bindValue(':ea_max', $ea_max);
        $stmt->bindValue(':ea_min', $ea_min);

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