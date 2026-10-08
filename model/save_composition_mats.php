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
    $alloy      = isset($_POST['inp_alloy']) ? htmlspecialchars(trim($_POST['inp_alloy']), ENT_QUOTES, 'UTF-8') : '';
    $cstmsppl   = isset($_POST['inp_cstmsppl']) ? htmlspecialchars(trim($_POST['inp_cstmsppl']), ENT_QUOTES, 'UTF-8') : '';
    
    // ตั้งค่าเริ่มต้นตามโครงสร้าง Schema ของ SQL Server
    $product_id  = 'AL'; // Default 'AL' จาก Data Table
    $description = '';   // nvarchar(30)
    $active      = 'Y';  // Default 'Y' จาก Data Table

    // ตรวจสอบฟิลด์บังคับที่ไม่สามารถเป็นค่าว่างได้ (Not Null)
    if (empty($alloy) || empty($cstmsppl)) {
        $response['error'] = 'กรุณากรอกรหัส ALLOY และ CUSTOMER ID ให้ครบถ้วน';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. รับค่าส่วนที่เป็นตัวเลขทศนิยม (Data Type: real) และแปลงเป็น float
    // หากไม่มีการกรอกมา จะปัดให้เป็น 0 ตามค่า Default ของ Table Schema
    $gravity = isset($_POST['inp_gravity']) ? floatval($_POST['inp_gravity']) : 2.700;
    $al      = isset($_POST['inp_al']) ? floatval($_POST['inp_al']) : 0.0;
    $ti      = isset($_POST['inp_ti']) ? floatval($_POST['inp_ti']) : 0.0;

    // แกะสเปกช่วง Min / Avg / Max กลุ่มธาตุหลัก
    $al_min  = isset($_POST['al_min']) ? floatval($_POST['al_min']) : 0.0;
    $al_max  = isset($_POST['al_max']) ? floatval($_POST['al_max']) : 0.0;
    $fe_min  = isset($_POST['fe_min']) ? floatval($_POST['fe_min']) : 0.0;
    $fe_avg  = isset($_POST['fe_avg']) ? floatval($_POST['fe_avg']) : 0.0;
    $fe_max  = isset($_POST['fe_max']) ? floatval($_POST['fe_max']) : 0.0;
    $si_min  = isset($_POST['si_min']) ? floatval($_POST['si_min']) : 0.0;
    $si_avg  = isset($_POST['si_avg']) ? floatval($_POST['si_avg']) : 0.0;
    $si_max  = isset($_POST['si_max']) ? floatval($_POST['si_max']) : 0.0;
    $mn_min  = isset($_POST['mn_min']) ? floatval($_POST['mn_min']) : 0.0;
    $mn_avg  = isset($_POST['mn_avg']) ? floatval($_POST['mn_avg']) : 0.0;
    $mn_max  = isset($_POST['mn_max']) ? floatval($_POST['mn_max']) : 0.0;
    $mg_min  = isset($_POST['mg_min']) ? floatval($_POST['mg_min']) : 0.0;
    $mg_avg  = isset($_POST['mg_avg']) ? floatval($_POST['mg_avg']) : 0.0;
    $mg_max  = isset($_POST['mg_max']) ? floatval($_POST['mg_max']) : 0.0;
    $cr_min  = isset($_POST['cr_min']) ? floatval($_POST['cr_min']) : 0.0;
    $cr_avg  = isset($_POST['cr_avg']) ? floatval($_POST['cr_avg']) : 0.0;
    $cr_max  = isset($_POST['cr_max']) ? floatval($_POST['cr_max']) : 0.0;
    $cu_min  = isset($_POST['cu_min']) ? floatval($_POST['cu_min']) : 0.0;
    $cu_avg  = isset($_POST['cu_avg']) ? floatval($_POST['cu_avg']) : 0.0;
    $cu_max  = isset($_POST['cu_max']) ? floatval($_POST['cu_max']) : 0.0;
    $zn_min  = isset($_POST['zn_min']) ? floatval($_POST['zn_min']) : 0.0;
    $zn_avg  = isset($_POST['zn_avg']) ? floatval($_POST['zn_avg']) : 0.0;
    $zn_max  = isset($_POST['zn_max']) ? floatval($_POST['zn_max']) : 0.0;
    $pb_min  = isset($_POST['pb_min']) ? floatval($_POST['pb_min']) : 0.0;
    $pb_avg  = isset($_POST['pb_avg']) ? floatval($_POST['pb_avg']) : 0.0;
    $pb_max  = isset($_POST['pb_max']) ? floatval($_POST['pb_max']) : 0.0;

    // แกะสเปกกลุ่มธาตุควบคุมที่มีเฉพาะค่าเพดานสูงสุด (Max Only)
    $as_max  = isset($_POST['as_max']) ? floatval($_POST['as_max']) : 0.0;
    $ni_max  = isset($_POST['ni_max']) ? floatval($_POST['ni_max']) : 0.0;
    $sn_max  = isset($_POST['sn_max']) ? floatval($_POST['sn_max']) : 0.0;
    $sb_max  = isset($_POST['sb_max']) ? floatval($_POST['sb_max']) : 0.0;
    $be_max  = isset($_POST['be_max']) ? floatval($_POST['be_max']) : 0.0;
    $bi_max  = isset($_POST['bi_max']) ? floatval($_POST['bi_max']) : 0.0;
    $cd_max  = isset($_POST['cd_max']) ? floatval($_POST['cd_max']) : 0.0;
    $in_max  = isset($_POST['in_max']) ? floatval($_POST['in_max']) : 0.0;

    try {
        // ตรวจสอบรหัส ALLOY ซ้ำในฐานข้อมูลก่อนเพื่อป้องกัน Data Duplicate
        $checkSql = "SELECT COUNT(*) FROM CMPSMSTR1 WHERE ALLOY = :alloy AND CSTMSPPL_ID = :cstmsppl";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':alloy' => $alloy, ':cstmsppl' => $cstmsppl]);
        if ($checkStmt->fetchColumn() > 0) {
            $response['error'] = 'รหัส ALLOY นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // เตรียมประโยคคำสั่ง INSERT ระบุคอลัมน์ครบทั้ง 43 ลำดับ (ยกเว้นตัวแปรประเภทลบวิเคราะห์และระบบวันเวลาอัตโนมัติ)
        $sql = "INSERT INTO CMPSMSTR1 (
                    PRODUCT_ID, ALLOY, CSTMSPPL_ID, DESCRIPTION, DENSITY, AL, TI,
                    AL_MIN, AL_MAX, FE_MIN, FE_AVG, FE_MAX, SI_MIN, SI_AVG, SI_MAX,
                    MN_MIN, MN_AVG, MN_MAX, MG_MIN, MG_AVG, MG_MAX, CR_MIN, CR_AVG, CR_MAX,
                    CU_MIN, CU_AVG, CU_MAX, ZN_MIN, ZN_AVG, ZN_MAX, PB_MIN, PB_AVG, PB_MAX,
                    AS_MAX, NI_MAX, SN_MAX, SB_MAX, BE_MAX, BI_MAX, CD_MAX, IN_MAX,
                    ACTIVE
                ) VALUES (
                    :product_id, :alloy, :cstmsppl, :description, :gravity, :al, :ti,
                    :al_min, :al_max, :fe_min, :fe_avg, :fe_max, :si_min, :si_avg, :si_max,
                    :mn_min, :mn_avg, :mn_max, :mg_min, :mg_avg, :mg_max, :cr_min, :cr_avg, :cr_max,
                    :cu_min, :cu_avg, :cu_max, :zn_min, :zn_avg, :zn_max, :pb_min, :pb_avg, :pb_max,
                    :as_max, :ni_max, :sn_max, :sb_max, :be_max, :bi_max, :cd_max, :in_max,
                    :active
                )";

        $stmt = $conn->prepare($sql);

        // ทำการผูกค่าพารามิเตอร์แบบระบุตัวแปร (Named placeholders) ป้องกัน SQL Injection อย่างเด็ดขาด
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_STR);
        $stmt->bindParam(':alloy', $alloy, PDO::PARAM_STR);
        $stmt->bindParam(':cstmsppl', $cstmsppl, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        // สำหรับข้อมูลประเภท real/float ให้ส่งผ่านรูปแบบสตริงข้อความตัวเลข เพื่อให้ระบบ PDO ของ SQL Server แปลงค่าได้อย่างแม่นยำ
        $stmt->bindValue(':gravity', $gravity);
        $stmt->bindValue(':al', $al);
        $stmt->bindValue(':ti', $ti);
        $stmt->bindValue(':al_min', $al_min);
        $stmt->bindValue(':al_max', $al_max);
        $stmt->bindValue(':fe_min', $fe_min);
        $stmt->bindValue(':fe_avg', $fe_avg);
        $stmt->bindValue(':fe_max', $fe_max);
        $stmt->bindValue(':si_min', $si_min);
        $stmt->bindValue(':si_avg', $si_avg);
        $stmt->bindValue(':si_max', $si_max);
        $stmt->bindValue(':mn_min', $mn_min);
        $stmt->bindValue(':mn_avg', $mn_avg);
        $stmt->bindValue(':mn_max', $mn_max);
        $stmt->bindValue(':mg_min', $mg_min);
        $stmt->bindValue(':mg_avg', $mg_avg);
        $stmt->bindValue(':mg_max', $mg_max);
        $stmt->bindValue(':cr_min', $cr_min);
        $stmt->bindValue(':cr_avg', $cr_avg);
        $stmt->bindValue(':cr_max', $cr_max);
        $stmt->bindValue(':cu_min', $cu_min);
        $stmt->bindValue(':cu_avg', $cu_avg);
        $stmt->bindValue(':cu_max', $cu_max);
        $stmt->bindValue(':zn_min', $zn_min);
        $stmt->bindValue(':zn_avg', $zn_avg);
        $stmt->bindValue(':zn_max', $zn_max);
        $stmt->bindValue(':pb_min', $pb_min);
        $stmt->bindValue(':pb_avg', $pb_avg);
        $stmt->bindValue(':pb_max', $pb_max);
        $stmt->bindValue(':as_max', $as_max);
        $stmt->bindValue(':ni_max', $ni_max);
        $stmt->bindValue(':sn_max', $sn_max);
        $stmt->bindValue(':sb_max', $sb_max);
        $stmt->bindValue(':be_max', $be_max);
        $stmt->bindValue(':bi_max', $bi_max);
        $stmt->bindValue(':cd_max', $cd_max);
        $stmt->bindValue(':in_max', $in_max);
        
        $stmt->bindParam(':active', $active, PDO::PARAM_STR);

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