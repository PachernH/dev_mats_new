<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

include '../dbcon_mats-new.php';

function getResponsType($originalStatus, $productId, $productNo) {
    $originalStatus = strtoupper(trim($originalStatus));
    $productId      = strtoupper(trim($productId));
    $productNo      = trim($productNo);

    $isR = (strlen($productNo) >= 9 && strtoupper(substr($productNo, 8, 1)) === 'R');

    switch ($originalStatus) {
        case 'ST':
        case 'DS':
        case 'TR':
            return 'FG. STOCK';
        case 'PK':
            return 'PROD. PACK';
        default:
            switch ($productId) {
                case 'CO':
                    return $isR ? 'FG. RETURN OR RECEIVE' : 'PROD. CASTER OR COLD MILL OR BLANK OR CUT TO LENGTH';
                case 'CC':
                case 'NC':
                    return $isR ? 'FG. RECEIVE OR RETURN' : 'PROD. BLANK OR CUT TO LENGTH OR CUT SHHET OR SHEAR OR BATCH ANNEAL OR FLASH ANNEAL';
                case 'SH':
                    return $isR ? 'FG. RECEIVE OR RETURN' : 'PROD. CUT TO LENGTH OR CUT SHHET OR STRETCHER OR BATCH ANNEAL OR FLASH ANNEAL';
                default:
                    return '-';
            }
    }
}

function saveRemeltRequisition($conn, $data, $operator) {
    try {
        $conn->beginTransaction();

        $responsType = getResponsType($data['original_status'], $data['product_id'], $data['product_no']);

        // 1. ตรวจสอบว่ามี REQUEST_NO ใน PRODRMLT1 แล้วหรือไม่
        $checkSql = "SELECT COUNT(*) FROM PRODRMLT1 WHERE REQUEST_NO = :req_no";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':req_no' => $data['request_no']]);
        $exists = $checkStmt->fetchColumn();

        // 2. ถ้ายังไม่มี Header ให้ทำการ INSERT PRODRMLT1
        if ($exists == 0) {
            $sql1 = "INSERT INTO PRODRMLT1 (REQUEST_NO, REQUEST_DATE, REMARK, RQT1_OPERATOR) 
                     VALUES (:req_no, GETDATE(), :remark, :operator)";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute([
                ':req_no'   => $data['request_no'],
                ':remark'   => $data['remelt_reason'],
                ':operator' => $operator
            ]);
        }

        // 3. INSERT PRODRMLT2 (Detail รายการสินค้า)
        $sql2 = "INSERT INTO PRODRMLT2 (
                    REQUEST_NO, PRODUCT_NO, COIL_NO, CSTMSPPL_ID, PO_NO, PO_ITEM, 
                    SALEORDER_NO, SALEORDER_ITEM, ORDER_WEIGHT, JOB_ORDER, JOB_WEIGHT, 
                    PRODUCT_ID, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, 
                    THICKNESS, WIDTH, [LENGTH], PRODUCT_WEIGHT, REMELT_REASON, 
                    RESPONS_TYPE, ORIGINAL_STATUS, REMELT_STATUS, REMELT_DATE, MELT_STATUS, MELT_DATE, 
                    RQT2_OPERATOR, RQT2_UPDATE
                 ) VALUES (
                    :req_no, :prod_no, :coil_no, :cstm_id, :po_no, :po_item, 
                    :so_no, :so_item, :ord_w, :job_order, :job_w, 
                    :prod_id, :alloy, :temper, :grade, :surf_g, :meta_g, 
                    :thick, :width, :length, :prod_w, :reason, 
                    :respons_type, :original_status, :remelt_status, GETDATE(), :melt_status, GETDATE(), 
                    :operator, :operator
                 )";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([
            ':req_no'          => $data['request_no'],
            ':prod_no'         => $data['product_no'],
            ':coil_no'         => $data['coil_no'],
            ':cstm_id'         => $data['cstm_id'],
            ':po_no'           => $data['po_no'],
            ':po_item'         => !empty($data['po_item']) ? $data['po_item'] : '-',
            ':so_no'           => $data['so_no'],
            ':so_item'         => !empty($data['so_item']) ? $data['so_item'] : '-',
            ':ord_w'           => (float)$data['order_weight'],
            ':job_order'       => $data['job_order'],
            ':job_w'           => (float)$data['job_weight'],
            ':prod_id'         => $data['product_id'],
            ':alloy'           => $data['alloy'],
            ':temper'          => $data['temper'],
            ':grade'           => $data['grade'],
            ':surf_g'          => $data['surface_g'],
            ':meta_g'          => $data['meta_g'],
            ':thick'           => (float)$data['thickness'],
            ':width'           => (float)$data['width'],
            ':length'          => (float)$data['length'],
            ':prod_w'          => (float)$data['prod_weight'],
            ':reason'          => $data['remelt_reason'],
            ':respons_type'    => $responsType,
            ':original_status' => !empty($data['original_status']) ? $data['original_status'] : '-',
            ':remelt_status'   => 'N',
            ':melt_status'     => 'N',
            ':operator'        => $operator
        ]);

        $conn->commit();
        return [
            'status'     => 'success', 
            'message'    => 'บันทึกข้อมูลสำเร็จเรียบร้อยแล้ว!',
            'request_no' => $data['request_no']
        ];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iduser_func   = $_SESSION['ID'] ?? 'SYSTEM';
    $requestNo     = !empty($_POST['request_no']) ? trim($_POST['request_no']) : '';
    $remeltReason  = trim($_POST['remelt_reason'] ?? '');

    if (empty($remeltReason) || $remeltReason === '-') {
        echo json_encode([
            'status'  => 'error',
            'message' => 'กรุณากรอก Remelt Reason ก่อนสั่งบันทึกข้อมูล!'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formData = [
        'request_no'      => $requestNo,
        'product_no'      => trim($_POST['product_no'] ?? '-'),
        'coil_no'         => trim($_POST['coil_no'] ?? '-'),
        'product_id'      => trim($_POST['product_id'] ?? '-'),
        'job_order'       => trim($_POST['job_order'] ?? '-'),
        'alloy'           => trim($_POST['alloy'] ?? '-'),
        'temper'          => trim($_POST['temper'] ?? '-'),
        'grade'           => trim($_POST['grade'] ?? '-'),
        'surface_g'       => trim($_POST['surface_grade'] ?? '-'),
        'meta_g'          => trim($_POST['metallurgical_grade'] ?? '-'),
        'thickness'       => (float)($_POST['thickness'] ?? 0),
        'width'           => (float)($_POST['width'] ?? 0),
        'length'          => (float)($_POST['length'] ?? 0),
        'prod_weight'     => (float)($_POST['product_weight'] ?? 0),
        'remelt_reason'   => $remeltReason,
        'cstm_id'         => trim($_POST['cstm_id'] ?? '-'),
        'po_no'           => trim($_POST['po_no'] ?? '-'),
        'po_item'         => trim($_POST['po_item'] ?? '-'),
        'so_no'           => trim($_POST['saleorder_no'] ?? '-'),
        'so_item'         => trim($_POST['saleorder_item'] ?? '-'),
        'job_weight'      => (float)($_POST['job_weight'] ?? 0),
        'order_weight'    => (float)($_POST['order_weight'] ?? 0),
        'original_status' => trim($_POST['original_status'] ?? '-')
    ];

    $response = saveRemeltRequisition($conn, $formData, $iduser_func);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}