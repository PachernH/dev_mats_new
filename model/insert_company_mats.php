<?php
session_start();
header('Content-Type: application/json');
include("../dbcon_mats-new.php"); // ตรวจสอบชื่อไฟล์และพาธเชื่อมต่อ Database ของคุณให้ถูกต้อง

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['COMPANY_ID']) ? trim($_POST['COMPANY_ID']) : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'error' => 'กรุณากรอก Company ID']);
        exit;
    }

    try {
        // 1. ตรวจสอบก่อนว่า Company ID นี้มีอยู่ในระบบแล้วหรือยังเพื่อป้องกันข้อมูลซ้ำ (Primary Key Violation)
        $check_sql = "SELECT COUNT(*) FROM CMPNMSTR1 WHERE COMPANY_ID = :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':id' => $id]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'error' => 'Company ID นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้']);
            exit;
        }
        // 4. เตรียมคำสั่ง SQL INSERT ครบทุกฟิลด์ตามโครงสร้างมาสเตอร์
        $sql = "INSERT INTO CMPNMSTR1 (
                    COMPANY_ID, COMPANY, ADDRESS1, ADDRESS2,
                    ADDRESS3, TELEPHONE, FAX,
                    EMAIL, CONTACT_PERSON, CONTACT_POSITION
                ) VALUES (
                    :id, :company_name, :c_addr1, :c_addr2,
                    :c_addr3, :c_tel, :c_fax,
                    :c_email, :c_cont, :c_pos
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':company_name' => !empty($_POST['CONSIGNEE_COMPANY']) ? $_POST['CONSIGNEE_COMPANY'] : null,
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