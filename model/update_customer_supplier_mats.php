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

    // จัดการข้อมูลประเภท Checkbox 
    $include_high = isset($_POST['INCLUDE_HIGH']) ? 1 : 0;
    $include_weight = isset($_POST['INCLUDE_WEIGHT']) ? 1 : 0;
    $serial_code = isset($_POST['SERIAL_CODE']) ? 1 : 0;
    $show_piece = isset($_POST['SHOW_PIECE']) ? 1 : 0;
    $adjust_piece = isset($_POST['ADJUST_PIECE']) ? 1 : 0;
    $show_scrapwire = isset($_POST['SHOW_SCRAPWIRE']) ? 1 : 0;

    $sql = "UPDATE CSSPMSTR1 SET 
                ACCOUNT_CODE = :account_code, CSTMSPPL_BRANCH = :branch, CSTMSPPL_TAXID = :taxid,
                CERTIFICATE_ADDRESS = :cert_addr, CSTMSPPL_TYPE = :cs_type, CSTMSPPL_LOCATION = :location,
                REGION_GROUP = :region, COUNTRY_GROUP = :country, SUPPLIER_MATCODE = :matcode,
                PAYMENT_ID = :payment, SHIPMENT_ID = :shipment, CURRENCY_ID = :currency,
                CSSP_FREIGHT = :freight, CSSP_INSURANCE = :insurance, CSSP_TAXPERCENT = :taxpercent,
                CSSP_PORTLOADING = :portload, CSSP_PORTDISCHARGE = :portdis, CSSP_PLACEDESTINATION = :placedest,
                CSSP_COUNTRYDESTINATION = :countrydest, UOM_WEIGHT = :uom_w, UOM_DIMENSION = :uom_d,
                CONSIGNEE_COMPANY = :c_comp, CONSIGNEE_ADDRESS1 = :c_addr1, CONSIGNEE_ADDRESS2 = :c_addr2,
                CONSIGNEE_ADDRESS3 = :c_addr3, CONSIGNEE_TELEPHONE = :c_tel, CONSIGNEE_FAX = :c_fax,
                CONSIGNEE_EMAIL = :c_email, CONSIGNEE_CONTACT = :c_cont, CONSIGNEE_POSITION = :c_pos,
                NOTIFY_COMPANY = :n_comp, NOTIFY_ADDRESS1 = :n_addr1, NOTIFY_ADDRESS2 = :n_addr2,
                NOTIFY_ADDRESS3 = :n_addr3, NOTIFY_TELEPHONE = :n_tel, NOTIFY_FAX = :n_fax,
                NOTIFY_EMAIL = :n_email, NOTIFY_CONTACT = :n_cont, NOTIFY_POSITION = :n_pos,
                NOTIFY_TAXID = :n_taxid, ALSONOTIFY_COMPANY = :an_comp, ALSONOTIFY_ADDRESS1 = :an_addr1,
                ALSONOTIFY_ADDRESS2 = :an_addr2, ALSONOTIFY_ADDRESS3 = :an_addr3, ALSONOTIFY_TELEPHONE = :an_tel,
                ALSONOTIFY_FAX = :an_fax, ALSONOTIFY_EMAIL = :an_email, ALSONOTIFY_CONTACT = :an_cont,
                ALSONOTIFY_POSITION = :an_pos, HARMONIZE_CODE = :harmonize, PACKAGE_TREATMENT = :pkg_treat,
                PACKAGE_HIGH = :pkg_high, INCLUDE_HIGH = :inc_high, WEIGHTPERPACKAGE = :weight_pkg,
                INCLUDE_WEIGHT = :inc_weight, SERIAL_CODE = :serial, SPECIAL_LABEL1 = :label1,
                SPECIAL_LABEL2 = :label2, SPECIAL_LABEL3 = :label3, SPECIAL_LABEL4 = :label4,
                ORDER_BY = :order_by, ORDER_STARTDATE = :order_date, SHOW_PIECE = :s_piece,
                ADJUST_PIECE = :adj_piece, SHOW_SCRAPWIRE = :s_scrap, PRIMARY_SMELT = :primary_smelt,
                SECONDARY_SMELT = :secondary_smelt, COUNTRY_MELT = :country_melt
            WHERE CSTMSPPL_ID = :id";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':account_code' => !empty($_POST['ACCOUNT_CODE']) ? $_POST['ACCOUNT_CODE'] : null,
            ':branch' => !empty($_POST['CSTMSPPL_BRANCH']) ? $_POST['CSTMSPPL_BRANCH'] : null,
            ':taxid' => !empty($_POST['CSTMSPPL_TAXID']) ? $_POST['CSTMSPPL_TAXID'] : '',
            ':cert_addr' => !empty($_POST['CERTIFICATE_ADDRESS']) ? $_POST['CERTIFICATE_ADDRESS'] : null,
            ':cs_type' => !empty($_POST['CSTMSPPL_TYPE']) ? $_POST['CSTMSPPL_TYPE'] : null,
            ':location' => !empty($_POST['CSTMSPPL_LOCATION']) ? $_POST['CSTMSPPL_LOCATION'] : null,
            ':region' => !empty($_POST['REGION_GROUP']) ? $_POST['REGION_GROUP'] : null,
            ':country' => !empty($_POST['COUNTRY_GROUP']) ? $_POST['COUNTRY_GROUP'] : null,
            ':matcode' => !empty($_POST['SUPPLIER_MATCODE']) ? $_POST['SUPPLIER_MATCODE'] : '',
            ':payment' => !empty($_POST['PMT_ID']) ? $_POST['PMT_ID'] : 00, 
            ':shipment' => !empty($_POST['SHIPMENT_ID']) ? $_POST['SHIPMENT_ID'] : null,
            ':currency' => !empty($_POST['CURRENCY_ID']) ? $_POST['CURRENCY_ID'] : null,
            ':freight' => !empty($_POST['CSSP_FREIGHT']) ? $_POST['CSSP_FREIGHT'] : 0,
            ':insurance' => !empty($_POST['CSSP_INSURANCE']) ? $_POST['CSSP_INSURANCE'] : 0,
            
            // ตรวจสอบชนิดข้อมูลตัวเลข ป้องกัน Error Conversion
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
            ':order_date' => !empty($_POST['ORDER_STARTDATE']) ? $_POST['ORDER_STARTDATE'] : null,
            ':s_piece' => $show_piece,
            ':adj_piece' => $adjust_piece,
            ':s_scrap' => $show_scrapwire,
            ':primary_smelt' => !empty($_POST['PRIMARY_SMELT']) ? $_POST['PRIMARY_SMELT'] : NONE,
            ':secondary_smelt' => !empty($_POST['SECONDARY_SMELT']) ? $_POST['SECONDARY_SMELT'] : NONE,
            ':country_melt' => !empty($_POST['COUNTRY_MELT']) ? $_POST['COUNTRY_MELT'] : NONE
        ]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'error' => 'Invalid Request']);
}