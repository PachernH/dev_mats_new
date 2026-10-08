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
    $idcust   = isset($_POST['inp_cust']) ? htmlspecialchars(trim($_POST['inp_cust']), ENT_QUOTES, 'UTF-8') : '';
    $idpro   = isset($_POST['inp_pid']) ? htmlspecialchars(trim($_POST['inp_pid']), ENT_QUOTES, 'UTF-8') : '';
    $alloy   = isset($_POST['inp_alloy']) ? htmlspecialchars(trim($_POST['inp_alloy']), ENT_QUOTES, 'UTF-8') : '';
    $temper  = isset($_POST['inp_temper']) ? htmlspecialchars(trim($_POST['inp_temper']), ENT_QUOTES, 'UTF-8') : '';
    $thickness = isset($_POST['inp_thickness']) ? htmlspecialchars(trim($_POST['inp_thickness']), ENT_QUOTES, 'UTF-8') : '';
    $width     = isset($_POST['inp_width']) ? htmlspecialchars(trim($_POST['inp_width']), ENT_QUOTES, 'UTF-8') : '';
    $length    = isset($_POST['inp_length']) ? htmlspecialchars(trim($_POST['inp_length']), ENT_QUOTES, 'UTF-8') : '';
    $et    = isset($_POST['et']) ? htmlspecialchars(trim($_POST['et']), ENT_QUOTES, 'UTF-8') : '';
    $fxp    = isset($_POST['fxp']) ? htmlspecialchars(trim($_POST['fxp']), ENT_QUOTES, 'UTF-8') : '';

    if ($idcust === '' || $idpro === '' || $alloy === '' || $temper === '' || $_POST['inp_thickness'] === '' || $_POST['inp_width'] === '' || $_POST['inp_length'] === '') {
        $response['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับค่าส่วนที่เป็นตัวเลขทศนิยม (Data Type: real) และแปลงเป็น float
    $th_p  = isset($_POST['th_p']) ? floatval($_POST['th_p']) : 0.0;
    $th_m  = isset($_POST['th_m']) ? floatval($_POST['th_m']) : 0.0;
   
    $wi_p  = isset($_POST['wi_p']) ? floatval($_POST['wi_p']) : 0.0;
    $wi_m  = isset($_POST['wi_m']) ? floatval($_POST['wi_m']) : 0.0;

    $le_p  = isset($_POST['le_p']) ? floatval($_POST['le_p']) : 0.0;
    $le_m  = isset($_POST['le_m']) ? floatval($_POST['le_m']) : 0.0;


    $fl  = isset($_POST['fl']) ? floatval($_POST['fl']) : 0.0;
    $ep  = isset($_POST['ep']) ? floatval($_POST['ep']) : 0.0;



    try {
        // แก้ไขจุดที่ 2: รวม Array ของ Parameter ส่งไปใน execute() ทีเดียวพร้อมกัน
        $checkSql = "SELECT COUNT(*) FROM STNDMSTR6 WHERE CSTMSPPL_ID = :idc AND PRODUCT_ID = :idp AND ALLOY = :alloy AND TEMPER = :temper AND THICKNESS = :thickness AND WIDTH = :width AND LENGTH = :length";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([
            ':idc'     => $idcust,
            ':idp'     => $idpro,
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
        $sql = "INSERT INTO STNDMSTR6 (
                    CSTMSPPL_ID, PRODUCT_ID, ALLOY, TEMPER, THICKNESS, WIDTH, LENGTH, THICKNESS_PTOLERANCE, THICKNESS_MTOLERANCE,
                    WIDTH_PTOLERANCE, WIDTH_MTOLERANCE, LENGTH_PTOLERANCE, LENGTH_MTOLERANCE, FLATNESS, EARING_TYPE, EARING_PERCENT, FIXED_PROCESS
                ) VALUES (
                    :idc, :idp, :alloy, :temper, :thickness, :width, :length, :th_p, :th_m,
                    :wi_p, :wi_m, :le_p, :le_m, :fl, :et, :ep, :fxp 
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์ ป้องกัน SQL Injection
        $stmt->bindParam(':idc', $idcust, PDO::PARAM_STR);
        $stmt->bindParam(':idp', $idpro, PDO::PARAM_STR);        
        $stmt->bindParam(':alloy', $alloy, PDO::PARAM_STR);
        $stmt->bindParam(':temper', $temper, PDO::PARAM_STR);       
        $stmt->bindParam(':thickness', $thickness, PDO::PARAM_STR);  
        $stmt->bindParam(':width', $width, PDO::PARAM_STR);
        $stmt->bindParam(':length', $length, PDO::PARAM_STR);  

        $stmt->bindValue(':th_p', $th_p);
        $stmt->bindValue(':th_m', $th_m);

        $stmt->bindValue(':wi_p', $wi_p);
        $stmt->bindValue(':wi_m', $wi_m);

        $stmt->bindValue(':le_p', $le_p);
        $stmt->bindValue(':le_m', $le_m);

        $stmt->bindValue(':fl', $fl);

        $stmt->bindParam(':et', $et, PDO::PARAM_STR);  
        $stmt->bindValue(':ep', $ep);
        $stmt->bindParam(':fxp', $fxp, PDO::PARAM_STR);       

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