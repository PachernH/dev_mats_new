<?php
session_start();
header('Content-Type: application/json');
include("../dbcon_mats-new.php"); // ตรวจสอบพาธไฟล์เชื่อมต่อ Database ของคุณให้ถูกต้องด้วยนะครับ

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['CSTMSPPL_ID']) ? trim($_POST['CSTMSPPL_ID']) : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'error' => 'ไม่พบ Company ID']);
        exit;
    }

    $sql = "UPDATE CSSPMSTR22 SET 

                ACCOUNT_CODE = :ACCOUNT_CODE, TAX_REGISTRATION = :TAX_REGISTRATION, TAX_CATEGORY = :TAX_CATEGORY,

                EN_COMPANY = :EN_COMPANY, EN_ADDRESS1 = :EN_ADDRESS1, EN_ADDRESS2 = :EN_ADDRESS2,

                TH_COMPANY = :TH_COMPANY, TH_ADDRESS1 = :TH_ADDRESS1, TH_ADDRESS2 = :TH_ADDRESS2,

                BRANCH = :BRANCH, TH_TITLE_COMPANY = :TH_TITLE_COMPANY, TH_COMPANY_NAME = :TH_COMPANY_NAME,

                TH_NAME_ADDRESS = :TH_NAME_ADDRESS, TH_ROOM_ADDRESS = :TH_ROOM_ADDRESS, TH_CLASS_ADDRESS = :TH_CLASS_ADDRESS,

                TH_NUM_ADDRESS = :TH_NUM_ADDRESS, TH_MOO = :TH_MOO, TH_SOI = :TH_SOI,

                TH_ROAD = :TH_ROAD, TH_TUMBON = :TH_TUMBON, TH_AUMPER = :TH_AUMPER,

                TH_CITY = :TH_CITY, TH_POSTCODE = :TH_POSTCODE

            WHERE CSTMSPPL_ID = :id";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':ACCOUNT_CODE' => !empty($_POST['ACCOUNT_CODE']) ? $_POST['ACCOUNT_CODE'] : null,
            ':TAX_REGISTRATION' => !empty($_POST['TAX_REGISTRATION']) ? $_POST['TAX_REGISTRATION'] : null,
            ':TAX_CATEGORY' => !empty($_POST['TAX_CATEGORY']) ? $_POST['TAX_CATEGORY'] : '',

            ':EN_COMPANY' => !empty($_POST['EN_COMPANY']) ? $_POST['EN_COMPANY'] : null,
            ':EN_ADDRESS1' => !empty($_POST['EN_ADDRESS1']) ? $_POST['EN_ADDRESS1'] : null,
            ':EN_ADDRESS2' => !empty($_POST['EN_ADDRESS2']) ? $_POST['EN_ADDRESS2'] : null,

            ':TH_COMPANY' => !empty($_POST['TH_COMPANY']) ? $_POST['TH_COMPANY'] : '',
            ':TH_ADDRESS1' => !empty($_POST['TH_ADDRESS1']) ? $_POST['TH_ADDRESS1'] : null,
            ':TH_ADDRESS2' => !empty($_POST['TH_ADDRESS2']) ? $_POST['TH_ADDRESS2'] : null,

            ':BRANCH' => !empty($_POST['BRANCH']) ? $_POST['BRANCH'] : null,
            ':TH_TITLE_COMPANY' => !empty($_POST['TH_TITLE_COMPANY']) ? $_POST['TH_TITLE_COMPANY'] : null,
            ':TH_COMPANY_NAME' => !empty($_POST['TH_COMPANY_NAME']) ? $_POST['TH_COMPANY_NAME'] : '',

            ':TH_NAME_ADDRESS' => !empty($_POST['TH_NAME_ADDRESS']) ? $_POST['TH_NAME_ADDRESS'] : null, 
            ':TH_ROOM_ADDRESS' => !empty($_POST['TH_ROOM_ADDRESS']) ? $_POST['TH_ROOM_ADDRESS'] : null,
            ':TH_CLASS_ADDRESS' => !empty($_POST['TH_CLASS_ADDRESS']) ? $_POST['TH_CLASS_ADDRESS'] : null,

            ':TH_NUM_ADDRESS' => !empty($_POST['TH_NUM_ADDRESS']) ? $_POST['TH_NUM_ADDRESS'] : null,
            ':TH_MOO' => !empty($_POST['TH_MOO']) ? $_POST['TH_MOO'] : null,
            ':TH_SOI' => !empty($_POST['TH_SOI']) ? $_POST['TH_SOI'] : null,
            ':TH_ROAD' => !empty($_POST['TH_ROAD']) ? $_POST['TH_ROAD'] : null,
            ':TH_TUMBON' => !empty($_POST['TH_TUMBON']) ? $_POST['TH_TUMBON'] : null,           
            ':TH_AUMPER' => !empty($_POST['TH_AUMPER']) ? $_POST['TH_AUMPER'] : null,
            ':TH_CITY' => !empty($_POST['TH_CITY']) ? $_POST['TH_CITY'] : null,
            ':TH_POSTCODE' => !empty($_POST['TH_POSTCODE']) ? $_POST['TH_POSTCODE'] : null,
        ]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid Request']);
}