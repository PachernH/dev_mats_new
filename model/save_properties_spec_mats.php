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

    // 1. รับค่าและทำความสะอาดข้อมูลส่วนที่เป็น String (ดึงค่า String แท้จริงเพื่อนำไปเช็คเงื่อนไขตรวจสอบคีย์)
    $alloy   = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
    $temper  = isset($_POST['inp_temper']) ? trim($_POST['inp_temper']) : '';
    $thick_f = isset($_POST['inp_thick_from']) ? trim($_POST['inp_thick_from']) : '';
    $thick_t = isset($_POST['inp_thick_to']) ? trim($_POST['inp_thick_to']) : '';

    if ($alloy === '' || $temper === '' || $thick_f === '' || $thick_t === '') {
        $response['error'] = 'กรุณากรอกข้อมูลหลักให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับค่าส่วนที่เป็นตัวเลขทศนิยม (Data Type: real) บังคับแปลงเป็น float ทศนิยม 1 ตำแหน่งตามตารางดาต้าเบส
    $uts_if  = (isset($_POST['uts_i_f']) && $_POST['uts_i_f'] !== '') ? round((float)$_POST['uts_i_f'], 1) : 0.0;
    $uts_it  = (isset($_POST['uts_i_t']) && $_POST['uts_i_t'] !== '') ? round((float)$_POST['uts_i_t'], 1) : 0.0; // แก้ไขบั๊กของเดิมเรียบร้อย
   
    $yis_if  = (isset($_POST['yis_i_f']) && $_POST['yis_i_f'] !== '') ? round((float)$_POST['yis_i_f'], 1) : 0.0;
    $yis_it  = (isset($_POST['yis_i_t']) && $_POST['yis_i_t'] !== '') ? round((float)$_POST['yis_i_t'], 1) : 0.0;

    $el_if   = (isset($_POST['el_i_f']) && $_POST['el_i_f'] !== '') ? round((float)$_POST['el_i_f'], 1) : 0.0;
    $el_it   = (isset($_POST['el_i_t']) && $_POST['el_i_t'] !== '') ? round((float)$_POST['el_i_t'], 1) : 0.0;

    $uts_kf  = (isset($_POST['uts_k_f']) && $_POST['uts_k_f'] !== '') ? round((float)$_POST['uts_k_f'], 1) : 0.0;
    $uts_kt  = (isset($_POST['uts_k_t']) && $_POST['uts_k_t'] !== '') ? round((float)$_POST['uts_k_t'], 1) : 0.0;

    $yis_kf  = (isset($_POST['yis_k_f']) && $_POST['yis_k_f'] !== '') ? round((float)$_POST['yis_k_f'], 1) : 0.0;
    $yis_kt  = (isset($_POST['yis_k_t']) && $_POST['yis_k_t'] !== '') ? round((float)$_POST['yis_k_t'], 1) : 0.0;

    try {
        // ใช้ LTRIM(RTRIM()) เพื่อลบช่องว่างแฝงของชนิดข้อมูล nvarchar ป้องกันปัญหาเช็คข้อมูลคีย์ซ้ำไม่เจอ
        $checkSql = "SELECT COUNT(*) FROM TCPS0201_6 
                     WHERE LTRIM(RTRIM(ALLOY)) = LTRIM(RTRIM(:alloy)) 
                       AND LTRIM(RTRIM(TEMPER_TARGET)) = LTRIM(RTRIM(:temper)) 
                       AND LTRIM(RTRIM(THICKNESS_FROM)) = LTRIM(RTRIM(:thick_f)) 
                       AND LTRIM(RTRIM(THICKNESS_TO)) = LTRIM(RTRIM(:thick_t))";
                       
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([
            ':alloy'   => $alloy,
            ':temper'  => $temper,
            ':thick_f' => $thick_f,
            ':thick_t' => $thick_t
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'Specification นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มข้อมูลซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT ข้อมูล
        $sql = "INSERT INTO TCPS0201_6 (
                    ALLOY, TEMPER_TARGET, THICKNESS_FROM, THICKNESS_TO, UTSFROM_KSI, UTSTO_KSI, UTSFROM_KGM, UTSTO_KGM,
                    YSFROM_KSI, YSTO_KSI, YSFROM_KGM, YSTO_KGM, ELONGFROM, ELONGTO
                ) VALUES (
                    :alloy, :temper, :thick_f, :thick_t, :uts_if, :uts_it, :uts_kf,
                    :uts_kt, :yis_if, :yis_it, :yis_kf, :yis_kt, :el_if, :el_it
                )";

        $stmt = $conn->prepare($sql);

        // ผูกค่าพารามิเตอร์ของคีย์หลัก
        $stmt->bindValue(':alloy', $alloy, PDO::PARAM_STR);
        $stmt->bindValue(':temper', $temper, PDO::PARAM_STR);       
        $stmt->bindValue(':thick_f', $thick_f, PDO::PARAM_STR);  
        $stmt->bindValue(':thick_t', $thick_t, PDO::PARAM_STR);

        // ผูกค่าพารามิเตอร์ของคุณสมบัติเชิงกล (Mechanical Properties)
        $stmt->bindValue(':uts_if', $uts_if);
        $stmt->bindValue(':uts_it', $uts_it);
        $stmt->bindValue(':uts_kf', $uts_kf);
        $stmt->bindValue(':uts_kt', $uts_kt);
        $stmt->bindValue(':yis_if', $yis_if);
        $stmt->bindValue(':yis_it', $yis_it);
        $stmt->bindValue(':yis_kf', $yis_kf);
        $stmt->bindValue(':yis_kt', $yis_kt);
        $stmt->bindValue(':el_if', $el_if);
        $stmt->bindValue(':el_it', $el_it); 

        // ประมวลผลเซฟลงตาราง
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

// ส่งออบเจกต์กลับไปแสดงผลฝั่งเบราว์เซอร์
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>