<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include '../dbcon_mats-new.php';

$iduser_func = $_SESSION['ID'] ?? 'SYSTEM';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. เริ่ม Transaction
    $conn->beginTransaction();

    // 2. ล็อกและ Gen Running Number ใหม่
    $year2Digits = date('y');
    $prefix = "RM-" . $year2Digits . "-";

    $sqlSeq = "SELECT MAX(REQUEST_NO) AS max_no 
               FROM PRODRMLT1 WITH (UPDLOCK, HOLDLOCK) 
               WHERE REQUEST_NO LIKE :prefix";
    
    $stmtSeq = $conn->prepare($sqlSeq);
    $stmtSeq->execute([':prefix' => $prefix . '%']);
    $rowSeq = $stmtSeq->fetch(PDO::FETCH_ASSOC);

    if ($rowSeq && $rowSeq['max_no']) {
        $lastSeq = (int)substr($rowSeq['max_no'], -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '0001';
    }

    $generated_request_no = $prefix . $nextSeq;

    // 3. Insert ข้อมูลลงในตารางหลัก PRODRMLT1 (Header)
    $remelt_reason = $_POST['remelt_reason'] ?? '';

    $sqlInsertHeader = "INSERT INTO PRODRMLT1 (REQUEST_NO, REQUEST_DATE, REMARK, RQT1_OPERATOR) 
                        VALUES (:req_no, GETDATE(), :remark, :operator)";
    $stmtHeader = $conn->prepare($sqlInsertHeader);
    $stmtHeader->execute([
        ':req_no'   => $generated_request_no,
        ':remark'   => $remelt_reason,
        ':operator' => $iduser_func
    ]);

    // 4. เตรียมข้อมูลสำหรับ PRODRMLT2 (ดักจับค่าว่างป้องกัน Error Allow Nulls = False)
    $source_type        = $_POST['source_type'] ?? '';
    $product_no         = !empty($_POST['product_no']) ? $_POST['product_no'] : '-';
    $coil_no            = !empty($_POST['coil_no']) ? $_POST['coil_no'] : '-';
    $cstm_id            = !empty($_POST['cstm_id']) ? $_POST['cstm_id'] : '-';
    $po_no              = !empty($_POST['po_no']) ? $_POST['po_no'] : '-';
    $saleorder_no       = !empty($_POST['saleorder_no']) ? $_POST['saleorder_no'] : '-';
    $job_order          = !empty($_POST['job_order']) ? $_POST['job_order'] : '-';
    $product_id         = !empty($_POST['product_id']) ? $_POST['product_id'] : '-';
    $alloy              = !empty($_POST['alloy']) ? $_POST['alloy'] : '-';
    $temper             = !empty($_POST['temper']) ? $_POST['temper'] : '-';
    $grade              = !empty($_POST['grade']) ? $_POST['grade'] : '-';
    $surface_grade      = !empty($_POST['surface_grade']) ? $_POST['surface_grade'] : '-';
    $metallurgical_grade= !empty($_POST['metallurgical_grade']) ? $_POST['metallurgical_grade'] : '-';
    $original_status    = !empty($_POST['original_status']) ? $_POST['original_status'] : '0';

    $order_weight   = is_numeric($_POST['order_weight'] ?? null) ? (float)$_POST['order_weight'] : 0.00;
    $job_weight     = is_numeric($_POST['job_weight'] ?? null) ? (float)$_POST['job_weight'] : 0.00;
    $thickness      = is_numeric($_POST['thickness'] ?? null) ? (float)$_POST['thickness'] : 0.00;
    $width          = is_numeric($_POST['width'] ?? null) ? (float)$_POST['width'] : 0.00;
    $length         = is_numeric($_POST['length'] ?? null) ? (float)$_POST['length'] : 0.00;
    $product_weight = is_numeric($_POST['product_weight'] ?? null) ? (float)$_POST['product_weight'] : 0.00;

    // 5. Insert ข้อมูลลงในตาราง PRODRMLT2 (ชื่อ Column ตรงตาม Database Exact Match)
    $sqlInsertDetail = "INSERT INTO PRODRMLT2 (
                            REQUEST_NO, PRODUCT_NO, COIL_NO, CSTMSPPL_ID, PO_NO, 
                            PO_ITEM, SALEORDER_NO, SALEORDER_ITEM, ORDER_WEIGHT, JOB_ORDER, 
                            JOB_WEIGHT, PRODUCT_ID, ALLOY, TEMPER, GRADE, 
                            SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, LENGTH, 
                            PRODUCT_WEIGHT, REMELT_REASON, RESPONS_TYPE, ORIGINAL_STATUS, REMELT_STATUS, 
                            REMELT_DATE, MELT_STATUS, MELT_DATE, RQT2_OPERATOR
                        ) VALUES (
                            :REQUEST_NO, :PRODUCT_NO, :COIL_NO, :CSTMSPPL_ID, :PO_NO, 
                            '0', :SALEORDER_NO, '0', :ORDER_WEIGHT, :JOB_ORDER, 
                            :JOB_WEIGHT, :PRODUCT_ID, :ALLOY, :TEMPER, :GRADE, 
                            :SURFACE_GRADE, :METALLURGICAL_GRADE, :THICKNESS, :WIDTH, :LENGTH, 
                            :PRODUCT_WEIGHT, :REMELT_REASON, :RESPONS_TYPE, :ORIGINAL_STATUS, '0', 
                            GETDATE(), '0', GETDATE(), :RQT2_OPERATOR
                        )";

    $stmtDetail = $conn->prepare($sqlInsertDetail);
    $stmtDetail->execute([
        ':REQUEST_NO'          => $generated_request_no,
        ':PRODUCT_NO'          => $product_no,
        ':COIL_NO'             => $coil_no,
        ':CSTMSPPL_ID'         => $cstm_id,
        ':PO_NO'               => $po_no,
        ':SALEORDER_NO'        => $saleorder_no,
        ':ORDER_WEIGHT'        => $order_weight,
        ':JOB_ORDER'           => $job_order,
        ':JOB_WEIGHT'          => $job_weight,
        ':PRODUCT_ID'          => $product_id,
        ':ALLOY'               => $alloy,
        ':TEMPER'              => $temper,
        ':GRADE'               => $grade,
        ':SURFACE_GRADE'       => $surface_grade,
        ':METALLURGICAL_GRADE' => $metallurgical_grade,
        ':THICKNESS'           => $thickness,
        ':WIDTH'               => $width,
        ':LENGTH'              => $length,
        ':PRODUCT_WEIGHT'      => $product_weight,
        ':REMELT_REASON'       => $remelt_reason,
        ':RESPONS_TYPE'        => $source_type,
        ':ORIGINAL_STATUS'     => $original_status,
        ':RQT2_OPERATOR'       => $iduser_func
    ]);

    // 6. Commit Transaction
    $conn->commit();

    echo json_encode([
        'status'     => 'success',
        'message'    => 'บันทึกข้อมูลสำเร็จ รหัสเอกสาร: ' . $generated_request_no,
        'request_no' => $generated_request_no
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'status'  => 'error',
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
    ]);
}