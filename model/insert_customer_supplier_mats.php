<?php
session_start();
header('Content-Type: application/json');
include("../dbcon_mats-new.php"); // ตรวจสอบชื่อไฟล์และพาธเชื่อมต่อ Database ของคุณให้ถูกต้อง

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['CSTMSPPL_ID']) ? trim($_POST['CSTMSPPL_ID']) : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'error' => 'กรุณากรอก Company ID']);
        exit;
    }

    try {
        // 1. ตรวจสอบก่อนว่า Company ID นี้มีอยู่ในระบบแล้วหรือยังเพื่อป้องกันข้อมูลซ้ำ (Primary Key Violation)
        $check_sql = "SELECT COUNT(*) FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':id' => $id]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode(['status' => 'error', 'error' => 'Company ID นี้มีอยู่ในระบบเรียบร้อยแล้ว ไม่สามารถเพิ่มซ้ำได้']);
            exit;
        }

        // 2. จัดการข้อมูลประเภท Checkbox (ถ้าไม่ได้ติ๊ก ค่าจะไม่ถูกส่งมา ให้บันทึกเป็น 0)
        $include_high = isset($_POST['INCLUDE_HIGH']) ? 1 : 0;
        $include_weight = isset($_POST['INCLUDE_WEIGHT']) ? 1 : 0;
        $serial_code = isset($_POST['SERIAL_CODE']) ? 1 : 0;
        $show_piece = isset($_POST['SHOW_PIECE']) ? 1 : 0;
        $adjust_piece = isset($_POST['ADJUST_PIECE']) ? 1 : 0;
        $show_scrapwire = isset($_POST['SHOW_SCRAPWIRE']) ? 1 : 0;

        // 3. การจัดการเรื่องจัดฟอร์แมตเวลาเพื่อป้องกันการพังของ SQL Server DataType
        $order_date = null;
        if (!empty($_POST['ORDER_STARTDATE'])) {
            $parsed_time = strtotime($_POST['ORDER_STARTDATE']);
            if ($parsed_time !== false) {
                $order_date = date('Y-m-d H:i:s', $parsed_time); // แปลงเป็นรูปแบบมาตรฐานที่ SQL Server ยอมรับ
            }
        }

        // 4. เตรียมคำสั่ง SQL INSERT ครบทุกฟิลด์ตามโครงสร้างมาสเตอร์
        $sql = "INSERT INTO CSSPMSTR1 (
                    CSTMSPPL_ID, ACCOUNT_CODE, CSTMSPPL_BRANCH, CSTMSPPL_TAXID,
                    CERTIFICATE_ADDRESS, CSTMSPPL_TYPE, CSTMSPPL_LOCATION,
                    REGION_GROUP, COUNTRY_GROUP, SUPPLIER_MATCODE,
                    PAYMENT_ID, SHIPMENT_ID, CURRENCY_ID,
                    CSSP_FREIGHT, CSSP_INSURANCE, CSSP_TAXPERCENT,
                    CSSP_PORTLOADING, CSSP_PORTDISCHARGE, CSSP_PLACEDESTINATION,
                    CSSP_COUNTRYDESTINATION, UOM_WEIGHT, UOM_DIMENSION,
                    CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2,
                    CONSIGNEE_ADDRESS3, CONSIGNEE_TELEPHONE, CONSIGNEE_FAX,
                    CONSIGNEE_EMAIL, CONSIGNEE_CONTACT, CONSIGNEE_POSITION,
                    NOTIFY_COMPANY, NOTIFY_ADDRESS1, NOTIFY_ADDRESS2,
                    NOTIFY_ADDRESS3, NOTIFY_TELEPHONE, NOTIFY_FAX,
                    NOTIFY_EMAIL, NOTIFY_CONTACT, NOTIFY_POSITION,
                    NOTIFY_TAXID, ALSONOTIFY_COMPANY, ALSONOTIFY_ADDRESS1,
                    ALSONOTIFY_ADDRESS2, ALSONOTIFY_ADDRESS3, ALSONOTIFY_TELEPHONE,
                    ALSONOTIFY_FAX, ALSONOTIFY_EMAIL, ALSONOTIFY_CONTACT,
                    ALSONOTIFY_POSITION, HARMONIZE_CODE, PACKAGE_TREATMENT,
                    PACKAGE_HIGH, INCLUDE_HIGH, WEIGHTPERPACKAGE,
                    INCLUDE_WEIGHT, SERIAL_CODE, SPECIAL_LABEL1,
                    SPECIAL_LABEL2, SPECIAL_LABEL3, SPECIAL_LABEL4,
                    ORDER_BY, ORDER_STARTDATE, SHOW_PIECE,
                    ADJUST_PIECE, SHOW_SCRAPWIRE, PRIMARY_SMELT, SECONDARY_SMELT,
                    COUNTRY_MELT
                ) VALUES (
                    :id, :account_code, :branch, :taxid,
                    :cert_addr, :cs_type, :location,
                    :region, :country, :matcode,
                    :payment, :shipment, :currency,
                    :freight, :insurance, :taxpercent,
                    :portload, :portdis, :placedest,
                    :countrydest, :uom_w, :uom_d,
                    :c_comp, :c_addr1, :c_addr2,
                    :c_addr3, :c_tel, :c_fax,
                    :c_email, :c_cont, :c_pos,
                    :n_comp, :n_addr1, :n_addr2,
                    :n_addr3, :n_tel, :n_fax,
                    :n_email, :n_cont, :n_pos,
                    :n_taxid, :an_comp, :an_addr1,
                    :an_addr2, :an_addr3, :an_tel,
                    :an_fax, :an_email, :an_cont,
                    :an_pos, :harmonize, :pkg_treat,
                    :pkg_high, :inc_high, :weight_pkg,
                    :inc_weight, :serial, :label1,
                    :label2, :label3, :label4,
                    :order_by, :order_date, :s_piece,
                    :adj_piece, :s_scrap, :primary_smelt,
                    :secondary_smelt, :country_melt
                )";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':account_code' => !empty($_POST['ACCOUNT_CODE']) ? $_POST['ACCOUNT_CODE'] : null,
            ':branch' => !empty($_POST['CSTMSPPL_BRANCH']) ? $_POST['CSTMSPPL_BRANCH'] : null,
            ':taxid' => !empty($_POST['CSTMSPPL_TAXID']) ? $_POST['CSTMSPPL_TAXID'] : '',
            ':cert_addr' => !empty($_POST['CERTIFICATE_ADDRESS']) ? $_POST['CERTIFICATE_ADDRESS'] : null,
            ':cs_type' => !empty($_POST['CSTMSPPL_TYPE']) ? $_POST['CSTMSPPL_TYPE'] : '',
            ':location' => !empty($_POST['CSTMSPPL_LOCATION']) ? $_POST['CSTMSPPL_LOCATION'] : null,
            ':region' => !empty($_POST['REGION_GROUP']) ? $_POST['REGION_GROUP'] : null,
            ':country' => !empty($_POST['COUNTRY_GROUP']) ? $_POST['COUNTRY_GROUP'] : null,
            ':matcode' => !empty($_POST['SUPPLIER_MATCODE']) ? $_POST['SUPPLIER_MATCODE'] : '',
            ':payment' => !empty($_POST['PAYMENT_ID']) ? $_POST['PAYMENT_ID'] : null,
            ':shipment' => !empty($_POST['SHIPMENT_ID']) ? $_POST['SHIPMENT_ID'] : null,
            ':currency' => !empty($_POST['CURRENCY_ID']) ? $_POST['CURRENCY_ID'] : null,
            ':freight' => !empty($_POST['CSSP_FREIGHT']) ? floatval($_POST['CSSP_FREIGHT']) : 0,
            ':insurance' => !empty($_POST['CSSP_INSURANCE']) ? floatval($_POST['CSSP_INSURANCE']) : 0,
            ':taxpercent' => (!isset($_POST['CSSP_TAXPERCENT']) || $_POST['CSSP_TAXPERCENT'] === '') ? 0 : intval($_POST['CSSP_TAXPERCENT']),
            ':portload' => !empty($_POST['CSSP_PORTLOADING']) ? $_POST['CSSP_PORTLOADING'] : null,
            ':portdis' => !empty($_POST['CSSP_PORTDISCHARGE']) ? $_POST['CSSP_PORTDISCHARGE'] : null,
            ':placedest' => !empty($_POST['CSSP_PLACEDESTINATION']) ? $_POST['CSSP_PLACEDESTINATION'] : null,
            ':countrydest' => !empty($_POST['CSSP_COUNTRYDESTINATION']) ? $_POST['CSSP_COUNTRYDESTINATION'] : null,
            ':uom_w' => !empty($_POST['UOM_WEIGHT']) ? $_POST['UOM_WEIGHT'] : null,
            ':uom_d' => !empty($_POST['UOM_DIMENSION']) ? $_POST['UOM_DIMENSION'] : null,
            ':c_comp' => !empty($_POST['CONSIGNEE_COMPANY']) ? $_POST['CONSIGNEE_COMPANY'] : null,
            ':c_addr1' => !empty($_POST['CONSIGNEE_ADDRESS1']) ? $_POST['CONSIGNEE_ADDRESS1'] : null,
            ':c_addr2' => !empty($_POST['CONSIGNEE_ADDRESS2']) ? $_POST['CONSIGNEE_ADDRESS2'] : null,
            ':c_addr3' => !empty($_POST['CONSIGNEE_ADDRESS3']) ? $_POST['CONSIGNEE_ADDRESS3'] : null,
            ':c_tel' => !empty($_POST['CONSIGNEE_TELEPHONE']) ? $_POST['CONSIGNEE_TELEPHONE'] : null,
            ':c_fax' => !empty($_POST['CONSIGNEE_FAX']) ? $_POST['CONSIGNEE_FAX'] : null,
            ':c_email' => !empty($_POST['CONSIGNEE_EMAIL']) ? $_POST['CONSIGNEE_EMAIL'] : null,
            ':c_cont' => !empty($_POST['CONSIGNEE_CONTACT']) ? $_POST['CONSIGNEE_CONTACT'] : null,
            ':c_pos' => !empty($_POST['CONSIGNEE_POSITION']) ? $_POST['CONSIGNEE_POSITION'] : null,
            ':n_comp' => !empty($_POST['NOTIFY_COMPANY']) ? $_POST['NOTIFY_COMPANY'] : null,
            ':n_addr1' => !empty($_POST['NOTIFY_ADDRESS1']) ? $_POST['NOTIFY_ADDRESS1'] : null,
            ':n_addr2' => !empty($_POST['NOTIFY_ADDRESS2']) ? $_POST['NOTIFY_ADDRESS2'] : null,
            ':n_addr3' => !empty($_POST['NOTIFY_ADDRESS3']) ? $_POST['NOTIFY_ADDRESS3'] : null,
            ':n_tel' => !empty($_POST['NOTIFY_TELEPHONE']) ? $_POST['NOTIFY_TELEPHONE'] : null,
            ':n_fax' => !empty($_POST['NOTIFY_FAX']) ? $_POST['NOTIFY_FAX'] : null,
            ':n_email' => !empty($_POST['NOTIFY_EMAIL']) ? $_POST['NOTIFY_EMAIL'] : null,
            ':n_cont' => !empty($_POST['NOTIFY_CONTACT']) ? $_POST['NOTIFY_CONTACT'] : null,
            ':n_pos' => !empty($_POST['NOTIFY_POSITION']) ? $_POST['NOTIFY_POSITION'] : null,
            ':n_taxid' => !empty($_POST['NOTIFY_TAXID']) ? $_POST['NOTIFY_TAXID'] : '',
            ':an_comp' => !empty($_POST['ALSONOTIFY_COMPANY']) ? $_POST['ALSONOTIFY_COMPANY'] : null,
            ':an_addr1' => !empty($_POST['ALSONOTIFY_ADDRESS1']) ? $_POST['ALSONOTIFY_ADDRESS1'] : null,
            ':an_addr2' => !empty($_POST['ALSONOTIFY_ADDRESS2']) ? $_POST['ALSONOTIFY_ADDRESS2'] : null,
            ':an_addr3' => !empty($_POST['ALSONOTIFY_ADDRESS3']) ? $_POST['ALSONOTIFY_ADDRESS3'] : null,
            ':an_tel' => !empty($_POST['ALSONOTIFY_TELEPHONE']) ? $_POST['ALSONOTIFY_TELEPHONE'] : null,
            ':an_fax' => !empty($_POST['ALSONOTIFY_FAX']) ? $_POST['ALSONOTIFY_FAX'] : null,
            ':an_email' => !empty($_POST['ALSONOTIFY_EMAIL']) ? $_POST['ALSONOTIFY_EMAIL'] : null,
            ':an_cont' => !empty($_POST['ALSONOTIFY_CONTACT']) ? $_POST['ALSONOTIFY_CONTACT'] : null,
            ':an_pos' => !empty($_POST['ALSONOTIFY_POSITION']) ? $_POST['ALSONOTIFY_POSITION'] : null,
            ':harmonize' => !empty($_POST['HARMONIZE_CODE']) ? $_POST['HARMONIZE_CODE'] : '',
            ':pkg_treat' => !empty($_POST['PACKAGE_TREATMENT']) ? $_POST['PACKAGE_TREATMENT'] : null,
            ':pkg_high' => (!isset($_POST['PACKAGE_HIGH']) || $_POST['PACKAGE_HIGH'] === '') ? 0 : floatval($_POST['PACKAGE_HIGH']),
            ':inc_high' => $include_high,
            ':weight_pkg' => (!isset($_POST['WEIGHTPERPACKAGE']) || $_POST['WEIGHTPERPACKAGE'] === '') ? 0 : floatval($_POST['WEIGHTPERPACKAGE']),
            ':inc_weight' => $include_weight,
            ':serial' => $serial_code,
            ':label1' => !empty($_POST['SPECIAL_LABEL1']) ? $_POST['SPECIAL_LABEL1'] : null,
            ':label2' => !empty($_POST['SPECIAL_LABEL2']) ? $_POST['SPECIAL_LABEL2'] : null,
            ':label3' => !empty($_POST['SPECIAL_LABEL3']) ? $_POST['SPECIAL_LABEL3'] : null,
            ':label4' => !empty($_POST['SPECIAL_LABEL4']) ? $_POST['SPECIAL_LABEL4'] : null,
            ':order_by' => !empty($_POST['ORDER_BY']) ? $_POST['ORDER_BY'] : null,
            ':order_date' => $order_date,
            ':s_piece' => $show_piece,
            ':adj_piece' => $adjust_piece,
            ':s_scrap' => $show_scrapwire,
            ':primary_smelt' => !empty($_POST['PRIMARY_SMELT']) ? $_POST['PRIMARY_SMELT'] : null,
            ':secondary_smelt' => !empty($_POST['SECONDARY_SMELT']) ? $_POST['SECONDARY_SMELT'] : null,
            ':country_melt' => !empty($_POST['COUNTRY_MELT']) ? $_POST['COUNTRY_MELT'] : null
        ]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid Request']);
}