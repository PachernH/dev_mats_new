<?php
session_start();
header('Content-Type: application/json');
include("../dbcon_mats-new.php"); // ตรวจสอบพาธไฟล์เชื่อมต่อ Database ของคุณให้ถูกต้องด้วยนะครับ

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['COMPANY_ID']) ? trim($_POST['COMPANY_ID']) : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'error' => 'ไม่พบ Company ID']);
        exit;
    }


    $sql = "UPDATE CMPNMSTR1 SET 
                COMPANY = :company_name, ADDRESS1 = :c_addr1, ADDRESS2 = :c_addr2,
                ADDRESS3 = :c_addr3, TELEPHONE = :c_tel, FAX = :c_fax,
                EMAIL = :c_email, CONTACT_PERSON = :c_cont, CONTACT_POSITION = :c_pos
            WHERE COMPANY_ID = :id";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':company_name' => !empty($_POST['COMPANY_NAME']) ? $_POST['COMPANY_NAME'] : null,
            ':c_addr1' => !empty($_POST['CONSIGNEE_ADDRESS1']) ? $_POST['CONSIGNEE_ADDRESS1'] : null,
            ':c_addr2' => !empty($_POST['CONSIGNEE_ADDRESS2']) ? $_POST['CONSIGNEE_ADDRESS2'] : null,
            ':c_addr3' => !empty($_POST['CONSIGNEE_ADDRESS3']) ? $_POST['CONSIGNEE_ADDRESS3'] : null,
            ':c_tel' => !empty($_POST['CONSIGNEE_TELEPHONE']) ? $_POST['CONSIGNEE_TELEPHONE'] : null,
            ':c_fax' => !empty($_POST['CONSIGNEE_FAX']) ? $_POST['CONSIGNEE_FAX'] : null,
            ':c_email' => !empty($_POST['CONSIGNEE_EMAIL']) ? $_POST['CONSIGNEE_EMAIL'] : null,
            ':c_cont' => !empty($_POST['CONSIGNEE_CONTACT']) ? $_POST['CONSIGNEE_CONTACT'] : null,
            ':c_pos' => !empty($_POST['CONSIGNEE_POSITION']) ? $_POST['CONSIGNEE_POSITION'] : null
        ]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid Request']);
}