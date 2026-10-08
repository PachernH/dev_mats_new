<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include("../dbcon_mats-new.php"); // ตรวจสอบชื่อไฟล์และพาธเชื่อมต่อ Database ของคุณให้ถูกต้อง[cite: 18]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['CSTMSPPL_ID']) ? trim($_POST['CSTMSPPL_ID']) : ''; //[cite: 18]
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'error' => 'กรุณากรอก Company ID']); //[cite: 18]
        exit;
    }

    try {
        // 1. ตรวจสอบก่อนว่า Company ID นี้มีอยู่ในระบบแล้วหรือยังเพื่อป้องกันข้อมูลซ้ำ (Primary Key Violation)[cite: 18]
        $check_sql = "SELECT COUNT(*) FROM CSSPMSTR22 WHERE CSTMSPPL_ID = :id"; //[cite: 18]
        $check_stmt = $conn->prepare($check_sql); //[cite: 18]
        $check_stmt->execute([':id' => $id]); //[cite: 18]
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'error' => 'Company ID นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้']); //[cite: 18]
            exit;
        }

        // 2. เตรียมคำสั่ง SQL INSERT ให้จำนวนคอลัมน์ฝั่งซ้าย และพารามิเตอร์ฝั่งขวาเท่ากันแบบ 100%[cite: 18]
        $sql = "INSERT INTO CSSPMSTR22 (
                    CSTMSPPL_ID, ACCOUNT_CODE, TAX_REGISTRATION, TAX_CATEGORY,
                    EN_COMPANY, EN_ADDRESS1, EN_ADDRESS2,
                    TH_COMPANY, TH_ADDRESS1, TH_ADDRESS2,
                    BRANCH, TH_TITLE_COMPANY, TH_COMPANY_NAME,
                    TH_NAME_ADDRESS, TH_ROOM_ADDRESS, TH_CLASS_ADDRESS,
                    TH_NUM_ADDRESS, TH_MOO, TH_SOI,
                    TH_ROAD, TH_TUMBON, TH_AUMPER,
                    TH_CITY, TH_POSTCODE
                ) VALUES (
                    :id, :ACCOUNT_CODE, :TAX_REGISTRATION, :TAX_CATEGORY,
                    :EN_COMPANY, :EN_ADDRESS1, :EN_ADDRESS2,
                    :TH_COMPANY, :TH_ADDRESS1, :TH_ADDRESS2,
                    :BRANCH, :TH_TITLE_COMPANY, :TH_COMPANY_NAME,
                    :TH_NAME_ADDRESS, :TH_ROOM_ADDRESS, :TH_CLASS_ADDRESS,
                    :TH_NUM_ADDRESS, :TH_MOO, :TH_SOI,
                    :TH_ROAD, :TH_TUMBON, :TH_AUMPER,
                    :TH_CITY, :TH_POSTCODE
                )";

        $stmt = $conn->prepare($sql); //[cite: 18]
        
        // 3. ผูกข้อมูลส่งค่าเข้า execute array โดยตัดเรื่อง floatval ออกเพื่อให้รองรับอักขระตัวอักษรได้[cite: 18]
        $stmt->execute([
            ':id'               => $id,
            ':ACCOUNT_CODE'     => !empty($_POST['ACCOUNT_CODE']) ? trim($_POST['ACCOUNT_CODE']) : null,
            ':TAX_REGISTRATION' => !empty($_POST['TAX_REGISTRATION']) ? trim($_POST['TAX_REGISTRATION']) : null,
            ':TAX_CATEGORY'     => !empty($_POST['TAX_CATEGORY']) ? trim($_POST['TAX_CATEGORY']) : '',
            ':EN_COMPANY'       => !empty($_POST['EN_COMPANY']) ? trim($_POST['EN_COMPANY']) : null,
            ':EN_ADDRESS1'      => !empty($_POST['EN_ADDRESS1']) ? trim($_POST['EN_ADDRESS1']) : '',
            ':EN_ADDRESS2'      => !empty($_POST['EN_ADDRESS2']) ? trim($_POST['EN_ADDRESS2']) : null,
            ':TH_COMPANY'       => !empty($_POST['TH_COMPANY']) ? trim($_POST['TH_COMPANY']) : '',
            ':TH_ADDRESS1'      => !empty($_POST['TH_ADDRESS1']) ? trim($_POST['TH_ADDRESS1']) : null,
            ':TH_ADDRESS2'      => !empty($_POST['TH_ADDRESS2']) ? trim($_POST['TH_ADDRESS2']) : '',
            ':BRANCH'           => !empty($_POST['BRANCH']) ? trim($_POST['BRANCH']) : null,
            ':TH_TITLE_COMPANY' => !empty($_POST['TH_TITLE_COMPANY']) ? trim($_POST['TH_TITLE_COMPANY']) : null,
            ':TH_COMPANY_NAME'  => !empty($_POST['TH_COMPANY_NAME']) ? trim($_POST['TH_COMPANY_NAME']) : null,
            ':TH_NAME_ADDRESS'  => !empty($_POST['TH_NAME_ADDRESS']) ? trim($_POST['TH_NAME_ADDRESS']) : null,
            ':TH_ROOM_ADDRESS'  => !empty($_POST['TH_ROOM_ADDRESS']) ? trim($_POST['TH_ROOM_ADDRESS']) : null,        
            ':TH_CLASS_ADDRESS' => !empty($_POST['TH_CLASS_ADDRESS']) ? trim($_POST['TH_CLASS_ADDRESS']) : null,
            ':TH_NUM_ADDRESS'   => !empty($_POST['TH_NUM_ADDRESS']) ? trim($_POST['TH_NUM_ADDRESS']) : null,
            ':TH_MOO'           => !empty($_POST['TH_MOO']) ? trim($_POST['TH_MOO']) : null,
            ':TH_SOI'           => !empty($_POST['TH_SOI']) ? trim($_POST['TH_SOI']) : null,
            ':TH_ROAD'          => !empty($_POST['TH_ROAD']) ? trim($_POST['TH_ROAD']) : null,
            ':TH_TUMBON'        => !empty($_POST['TH_TUMBON']) ? trim($_POST['TH_TUMBON']) : null,
            ':TH_AUMPER'        => !empty($_POST['TH_AUMPER']) ? trim($_POST['TH_AUMPER']) : null,
            ':TH_CITY'          => !empty($_POST['TH_CITY']) ? trim($_POST['TH_CITY']) : null,
            ':TH_POSTCODE'      => !empty($_POST['TH_POSTCODE']) ? trim($_POST['TH_POSTCODE']) : null
        ]);

        echo json_encode(['status' => 'success']); //[cite: 18]
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]); //[cite: 18]
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid Request']); //[cite: 18]
}
?>