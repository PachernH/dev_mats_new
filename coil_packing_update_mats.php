<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER และ print_no จาก URL
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '';
$print_no  = isset($_GET['print_no']) ? htmlspecialchars(trim($_GET['print_no']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data        = null;
$so_data         = null;
$cust_data       = null;
$available_coils = [];
$pack_list       = [];
$ticket_data     = null; // ข้อมูลสำหรับพิมพ์ Product Ticket
$cust_address    = null; // ข้อมูลสำหรับเก็บชื่อและที่อยู่ลูกค้าที่จะพิมพ์ใน Ticket

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล Group 1: JOB Detail จาก JOBORDER1
    $sql_job = "SELECT JOB_ORDER, JOBORDER_DATE, ALLOY, SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2,
                       GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, THICKNESS, WIDTH, JOB_UOMDIMENSION, WEIGHT_PIECE, 
                       T5_TEMPERATURE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_MAXTOLERANCE,
                       JOB_WORKPROCESS, USE_FORPROCESS, JOB_RELEASEWEIGHT, JOB_UOMWEIGHT, JOB_RELEASEPIECE, 
                       JOB_PACKWEIGHT, JOB_PACKPIECE, JOB_STOCKWEIGHT, JOB_STOCKPIECE,
                       JOB_REMARK, JOB_STATUS, JOB_OPERATOR, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order
                ORDER BY JOBORDER_DATE DESC";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Sale Order จาก CSTMORDR2
    if ($job_data && !empty($job_data['SALEORDER_NO']) && !empty($job_data['SALEORDER_ITEM'])) {
        $sql_so = "SELECT SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, CTM2_CUSTOMERORDER, CTM2_ENDCUSTOMERPO, 
                          CTM2_CUSTOMERPARTNO, CTM2_CUSTOMERSALEORDER, THICKNESS, WIDTH, [LENGTH], CTM2_UOMDIMENSION,
                          CT2U_THICKNESS, CT2U_WIDTH, CT2U_LENGTH, CT2U_UOMDIMENSION,
                          CTM2_PONO, CTM2_POITEM, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE,
                          WEIGHT_PIECE, CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE, CTM2_ORDERWEIGHT, CTM2_ORDERPIECE,
                          CTM2_REJECTWEIGHT, CTM2_REJECTPIECE, CTM2_REPLACEWEIGHT, CTM2_REPLACEPIECE, CTM2_PACKWEIGHT, CTM2_PACKPIECE,
                          CTM2_STOCKWEIGHT, CTM2_STOCKPIECE, CTM2_REMARK
                   FROM CSTMORDR2
                   WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";

        $stmt_so = $conn->prepare($sql_so);
        $stmt_so->bindParam(':so_no', $job_data['SALEORDER_NO'], PDO::PARAM_STR);
        $stmt_so->bindParam(':so_item', $job_data['SALEORDER_ITEM'], PDO::PARAM_STR);
        $stmt_so->execute();
        $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);

        // ดึงชื่อลูกค้าจาก CSSPMSTR1
        if ($so_data && !empty($so_data['CSTMSPPL_ID'])) {
            $sql_cust = "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2 
                         FROM CSSPMSTR1 
                         WHERE CSTMSPPL_ID = :cust_id";
            $stmt_cust = $conn->prepare($sql_cust);
            $stmt_cust->bindParam(':cust_id', $so_data['CSTMSPPL_ID'], PDO::PARAM_STR);
            $stmt_cust->execute();
            $cust_data = $stmt_cust->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 3. Query ค้นหา รายการ Coil ใน COILPROD1 ตามเงื่อนไข MATERIAL_IN
    if ($so_data || $job_data) {
        $txtCTM2Alloy     = $so_data['ALLOY'] ?? $job_data['ALLOY'] ?? '';
        $txtCTM2Temper    = $so_data['TEMPER'] ?? $job_data['TEMPER'] ?? '';
        $txtCTM2Thickness = (float)($so_data['THICKNESS'] ?? $job_data['THICKNESS'] ?? 0);
        $txtCTM2Width     = (float)($so_data['WIDTH'] ?? $job_data['WIDTH'] ?? 0);
        $txtMaterialIN    = trim($job_data['MATERIAL_IN'] ?? '');

        $where_clauses = [
            "ALLOY = :alloy",
            "TEMPER = :temper",
            "THICKNESS = :thickness",
            "WIDTH = :width",
            "COIL_STATUS = 'AC'",
            "(JOB_ORDER IS NULL OR JOB_ORDER = '')",
            "(SALEORDER_NO IS NULL OR SALEORDER_NO = '')",
            "COIL_NEXTPROCESS = 'WS'"
        ];

        $params = [
            ':alloy'     => $txtCTM2Alloy,
            ':temper'    => $txtCTM2Temper,
            ':thickness' => $txtCTM2Thickness,
            ':width'     => $txtCTM2Width
        ];

        switch ($txtMaterialIN) {
            case 'BOI-MAT1':
                $where_clauses[] = "MATERIAL_IN IN ('BOI-MAT1', 'BOI-MAT2', 'BOI-GRS1', 'BOI-GRS2', 'NONE-BOI')";
                break;
            case 'BOI-MAT2':
            case 'BOI-GRS1':
            case 'BOI-GRS2':
                $where_clauses[] = "MATERIAL_IN IN ('BOI-MAT2', 'BOI-GRS1', 'BOI-GRS2')";
                break;
            case 'BOI-IMP1':
                $where_clauses[] = "MATERIAL_IN IN ('BOI-IMP1', 'NONE-BOI')";
                break;
            case 'BOI-IMP2':
                $where_clauses[] = "MATERIAL_IN IN ('BOI-IMP2', 'NONE-BOI')";
                break;
            case 'NONE-BOI':
                $where_clauses[] = "MATERIAL_IN IN ('BOI-IMP1', 'BOI-IMP2', 'NONE-BOI')";
                break;
            case '':
                break;
            default:
                $where_clauses[] = "MATERIAL_IN = :mat_in";
                $params[':mat_in'] = $txtMaterialIN;
                break;
        }

        $sql_available_coils = "SELECT COIL_NO, ALLOY, TEMPER, THICKNESS, WIDTH, MATERIAL_IN, 
                                       COIL_BALANCEWEIGHT, COIL_STATUS, COIL_NEXTPROCESS, RECIPE_NO, COIL_OPERATEDATE,
                                       COIL_DIMENSIONWIDTH, COIL_DIMENSIONLENGTH, COIL_DIMENSIONHIGH
                                FROM COILPROD1
                                WHERE " . implode(" AND ", $where_clauses) . "
                                ORDER BY COIL_NO ASC";

        $stmt_avail = $conn->prepare($sql_available_coils);
        foreach ($params as $key => $val) {
            $stmt_avail->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt_avail->execute();
        $available_coils = $stmt_avail->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Query ดึงข้อมูล Packing Coil จาก PACKPROD1
    $sql_pack = "SELECT PRODUCT_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, PACK_DATE, PACK_PACKAGEWEIGHT, PACK_NETWEIGHT 
                 FROM PACKPROD1 
                 WHERE JOB_ORDER = :job_order
                 ORDER BY PACK_DATE DESC";

    $stmt_pack = $conn->prepare($sql_pack);
    $stmt_pack->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_pack->execute();
    $pack_list = $stmt_pack->fetchAll(PDO::FETCH_ASSOC);

    // 5. ดึงข้อมูลสำหรับพิมพ์ Ticket หากมี print_no ส่งมา
    if (!empty($print_no)) {
        $sql_ticket = "SELECT P.PRODUCT_NO, P.JOB_ORDER, P.SALEORDER_NO, P.SALEORDER_ITEM, P.PACK_PONO, P.PACK_POITEM,
                              P.PACK_COMBINEWEIGHT, P.PACK_PACKAGEWEIGHT, P.PACK_NETWEIGHT, P.PACK_DATE,
                              C.ALLOY, C.TEMPER, C.THICKNESS, C.WIDTH, C.ACTUAL_WIDTH,
                              C.COIL_DIMENSIONWIDTH, C.COIL_DIMENSIONLENGTH, C.COIL_DIMENSIONHIGH,
                              c.COIL_UOMDIMENSION,c.COIL_UOMWEIGHT,
                              COALESCE(C.PRODUCE_DATE, C.COIL_OPERATEDATE) AS PRODUCE_DATE
                       FROM PACKPROD1 P
                       LEFT JOIN COILPROD1 C ON P.PRODUCT_NO = C.COIL_NO
                       WHERE P.PRODUCT_NO = :prod_no AND P.JOB_ORDER = :job_order";
        $stmt_ticket = $conn->prepare($sql_ticket);
        $stmt_ticket->bindParam(':prod_no', $print_no, PDO::PARAM_STR);
        $stmt_ticket->bindParam(':job_order', $job_order, PDO::PARAM_STR);
        $stmt_ticket->execute();
        $ticket_data = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

        // ดึงข้อมูล SALEORDER เพิ่มเติมเพื่อหา CSTMSPPL_ID
        $ticket_so_data = null;
        if ($ticket_data && !empty($ticket_data['SALEORDER_NO'])) {
            $sql_so_ticket = "SELECT CSTMSPPL_ID FROM CSTMORDR1 WHERE SALEORDER_NO = :so_no";
            $stmt_so_ticket = $conn->prepare($sql_so_ticket);
            $stmt_so_ticket->bindParam(':so_no', $ticket_data['SALEORDER_NO'], PDO::PARAM_STR);
            $stmt_so_ticket->execute();
            $ticket_so_data = $stmt_so_ticket->fetch(PDO::FETCH_ASSOC);
        }

        // ดึงข้อมูลชื่อและที่อยู่ลูกค้าจาก CSSPMSTR1 โดยใช้ CSTMSPPL_ID
        $cust_id_ticket = $ticket_so_data['CSTMSPPL_ID'] ?? $so_data['CSTMSPPL_ID'] ?? '';
        if (!empty($cust_id_ticket)) {
            $sql_cust_ticket = "SELECT CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2 
                                FROM CSSPMSTR1 
                                WHERE CSTMSPPL_ID = :cust_id";
            $stmt_cust_ticket = $conn->prepare($sql_cust_ticket);
            $stmt_cust_ticket->bindParam(':cust_id', $cust_id_ticket, PDO::PARAM_STR);
            $stmt_cust_ticket->execute();
            $cust_address = $stmt_cust_ticket->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g3 { color: #15803d; border-bottom: 2px solid #bbf7d0; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }

        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 16px; word-break: break-all; background-color: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #f1f5f9; min-height: 38px; display: flex; align-items: center; }

        .pack-table-container { overflow-x: auto; width: 100%; }
        .pack-table { width: 100%; border-collapse: collapse; font-size: 13px; background-color: #ffffff; }
        .pack-table th { background-color: #0f172a; color: #ffffff; font-weight: 600; padding: 10px 8px; text-align: left; white-space: nowrap; }
        .pack-table td { padding: 8px 8px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
        .pack-table tbody tr:hover { background-color: #f1f5f9; }
        .pack-table tfoot td { font-weight: 700; background-color: #f8fafc; border-top: 2px solid #cbd5e1; padding: 12px 8px; }

        .badge-mat { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background-color: #e0f2fe; color: #0369a1; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; font-size: 16px; padding: 10px 24px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-back:hover { background-color: #475569; color: #ffffff; }

        .btn-pack-select { background-color: #2563eb; color: #ffffff; font-weight: 600; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .btn-pack-select:hover { background-color: #1d4ed8; }

        /* จัดระเบียบปุ่มพิมพ์ให้กระทัดรัด ป้องกันตารางล้น */
        .btn-group-print {
            display: inline-flex;
            gap: 4px;
            justify-content: center;
            align-items: center;
        }
        .btn-print-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 5px;
            border: 1px solid transparent;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            text-decoration: none !important;
            white-space: nowrap;
        }
        .btn-print-ticket { background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-print-ticket:hover { background-color: #2563eb; color: #ffffff; border-color: #2563eb; }
        
        .btn-print-inside { background-color: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .btn-print-inside:hover { background-color: #10b981; color: #ffffff; border-color: #10b981; }
        
        .btn-print-outside { background-color: #fff7ed; color: #c2410c; border-color: #fed7aa; }
        .btn-print-outside:hover { background-color: #f97316; color: #ffffff; border-color: #f97316; }

        /* Modern Styling สำหรับ Modal คำนวณ Pack Coil */
        .pack-modal-content {
            border-radius: 16px !important;
            border: none !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden;
        }
        .pack-modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 18px 24px !important;
            border-bottom: 1px solid #334155;
        }
        .pack-modal-section-title {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .pack-input-group {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .pack-input-label {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }
        .pack-control-input {
            width: 100%;
            height: 42px;
            padding: 8px 14px;
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
            background-color: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            box-sizing: border-box;
        }
        .pack-control-input:focus {
            border-color: #2563eb;
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .pack-control-input-read {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            cursor: not-allowed;
        }
        .pack-control-input-net {
            background-color: #ecfdf5 !important;
            border-color: #6ee7b7 !important;
            color: #047857 !important;
            font-size: 18px !important;
            font-weight: 800 !important;
        }
        .pack-control-input-gross {
            background-color: #eff6ff !important;
            border-color: #93c5fd !important;
            color: #1d4ed8 !important;
            font-size: 18px !important;
            font-weight: 800 !important;
        }
        .btn-confirm-pack-submit {
            background-color: #16a34a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            padding: 10px 28px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.3) !important;
            transition: all 0.2s !important;
        }
        .btn-confirm-pack-submit:hover {
            background-color: #15803d !important;
            box-shadow: 0 6px 10px -1px rgba(22, 163, 74, 0.4) !important;
        }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'A4';?>

    <?php 
    if (!empty($folder_func) && !empty($group_func)) {
        include 'include/'.$folder_func.'/navigation.php'; 
    }
    ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="coil_packing_mats.php?func=<?php echo $folder_func ?>">Coil Packing Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Order Details: <span style="color:#2563eb;"><a href="coil_packing_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $job_order?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_cold()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$job_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Job Order: <strong><?php echo htmlspecialchars($job_order); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: JOB Detail -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 Job Order Details</h4>

                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN</div><div class="info-value" style="color:#d97706; font-weight:700; background-color:#fffbeb; border-color:#fde68a;"><?php echo htmlspecialchars($job_data['MATERIAL_IN'] ?? 'NONE-BOI'); ?></div></div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">CUSTOMER ID</div>
                            <div class="info-value" style="color:#0284c7; font-weight:700; background-color:#f0f9ff; border-color:#bae6fd;">
                                <?php echo htmlspecialchars($so_data['CSTMSPPL_ID'] ?? '-'); ?>
                            </div>
                        </div>
                        <div class="col-md-9 col-sm-6">
                            <div class="info-label">CUSTOMER NAME</div>
                            <div class="info-value" style="color:#0f172a; font-weight:700; background-color:#f8fafc; border-color:#e2e8f0;">
                                <?php echo htmlspecialchars($cust_data['CONSIGNEE_COMPANY'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo fmt3($job_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt3($job_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">DIMENSION UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMDIMENSION'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['USE_FORPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MIN TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MINTOLERANCE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MAX TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MAXTOLERANCE']); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <div class="row">
                        <?php
                            $MAXReleaseWeight = ($job_data['JOB_RELEASEWEIGHT']) + ((($job_data['JOB_RELEASEWEIGHT']) * $job_data['JOB_MAXTOLERANCE']) / 100);
                            $MAXReleasePiece = number_format(($MAXReleaseWeight / ($job_data['WEIGHT_PIECE'] ?: 1)), 0);
                        ?>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX RELEASE WEIGHT</div><div class="info-value"><?php echo fmt2($MAXReleaseWeight); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX RELEASE PIECE</div><div class="info-value"><?php echo htmlspecialchars($MAXReleasePiece); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_RELEASEWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_RELEASEPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACK WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_PACKWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACK PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_PACKPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_STOCKWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_STOCKPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>
                     </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <div class="row">
                        <div class="col-md-12"><div class="info-label">JOB REMARK</div><div class="info-value" style="min-height:48px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: List Coil Available for Packing -->
                <div class="dashboard-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #bae6fd; padding-bottom:10px; margin-bottom:20px;">
                        <h4 class="card-title-g2" style="border-bottom:none; margin:0; padding:0;">
                            🌀 List Available Coils for Packing (MATERIAL_IN: <span style="color:#2563eb;"><?php echo htmlspecialchars($job_data['MATERIAL_IN'] ?? 'NONE-BOI'); ?></span>)
                        </h4>
                        <span style="font-size:13px; color:#64748b; font-weight:600;">
                            พบทั้งหมด: <strong><?php echo count($available_coils); ?></strong> รายการ
                        </span>
                    </div>

                    <?php if (empty($available_coils)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">
                            ไม่พบรายการ Coil ที่พร้อมใช้งานตรงตามเงื่อนไข (Alloy: <?php echo htmlspecialchars($so_data['ALLOY'] ?? $job_data['ALLOY'] ?? '-'); ?>, Temper: <?php echo htmlspecialchars($so_data['TEMPER'] ?? $job_data['TEMPER'] ?? '-'); ?>, Thickness: <?php echo fmt3($so_data['THICKNESS'] ?? $job_data['THICKNESS']); ?>, Width: <?php echo fmt3($so_data['WIDTH'] ?? $job_data['WIDTH']); ?>)
                        </div>
                    <?php else: ?>
                        <div class="pack-table-container">
                            <table class="pack-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Coil No</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Material In</th>
                                        <th style="text-align:right;">Balance Weight</th>
                                        <th>Next Process</th>
                                        <th>Status</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_coils as $idx => $coil): ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($coil['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['TEMPER'] ?? '-'); ?></td>
                                            <td><?php echo fmt3($coil['THICKNESS']); ?></td>
                                            <td><?php echo fmt3($coil['WIDTH']); ?></td>
                                            <td><span class="badge-mat"><?php echo htmlspecialchars($coil['MATERIAL_IN'] ?? '-'); ?></span></td>
                                            <td style="text-align:right; font-weight:600;"><?php echo fmt2($coil['COIL_BALANCEWEIGHT'] ?? 0); ?></td>
                                            <td><span style="color:#0284c7; font-weight:700;"><?php echo htmlspecialchars($coil['COIL_NEXTPROCESS'] ?? '-'); ?></span></td>
                                            <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($coil['COIL_STATUS'] ?? '-'); ?></span></td>
                                            <td style="text-align:center;">
                                                <button type="button" class="btn-pack-select" 
                                                        onclick="openPackModal('<?php echo htmlspecialchars($coil['COIL_NO']); ?>', 
                                                                               '<?php echo (float)($coil['COIL_BALANCEWEIGHT'] ?? 0); ?>', 
                                                                               '<?php echo (float)($coil['COIL_DIMENSIONWIDTH'] ?? 0); ?>', 
                                                                               '<?php echo (float)($coil['COIL_DIMENSIONLENGTH'] ?? 0); ?>', 
                                                                               '<?php echo (float)($coil['COIL_DIMENSIONHIGH'] ?? 0); ?>')">
                                                    📦 เลือก
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- GROUP 3: Packing Coil Details -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">📦 Packing Coil Details (Job Order: <?php echo htmlspecialchars($job_order); ?>)</h4>

                    <?php if (empty($pack_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">ไม่พบข้อมูลรายการ Packing Coil ใน Job Order นี้</div>
                    <?php else: ?>
                        <?php 
                            // เช็กรหัสลูกค้าสำหรับแสดงปุ่ม Print ตามเงื่อนไข MANC, MANI, MANS, MANT
                            $man_customers = ['MANC', 'MANI', 'MANS', 'MANT'];
                            $cust_id = trim($so_data['CSTMSPPL_ID'] ?? '');
                            $is_man_customer = in_array($cust_id, $man_customers);
                        ?>
                        <div class="pack-table-container">
                            <table class="pack-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">#</th>
                                        <th>Product No / Coil No</th>
                                        <th>Sale Order No</th>
                                        <th style="text-align: center;">Item</th>
                                        <th>Job Order</th>
                                        <th>Pack Date</th>
                                        <th style="text-align:right;">Package Wt.</th>
                                        <th style="text-align:right;">Net Wt.</th>
                                        <th style="text-align:center; width: 280px;">Print Label</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $sum_pkg_weight = 0;
                                        $sum_net_weight = 0;
                                        foreach ($pack_list as $idx => $pack): 
                                            $sum_pkg_weight += (float)($pack['PACK_PACKAGEWEIGHT'] ?? 0);
                                            $sum_net_weight += (float)($pack['PACK_NETWEIGHT'] ?? 0);
                                    ?>
                                        <tr>
                                            <td style="text-align: center;"><?php echo $idx + 1; ?></td>
                                            <td style="font-weight:700; color:#15803d;"><?php echo htmlspecialchars($pack['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['SALEORDER_NO'] ?? '-'); ?></td>
                                            <td style="text-align: center;"><?php echo htmlspecialchars($pack['SALEORDER_ITEM'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['JOB_ORDER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['PACK_DATE'] ?? '-'); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($pack['PACK_PACKAGEWEIGHT']); ?></td>
                                            <td style="text-align:right; font-weight:600; color:#0f172a;"><?php echo fmt2($pack['PACK_NETWEIGHT']); ?></td>
                                            <td style="text-align:center;">
                                                <div class="btn-group-print">
                                                    <button type="button" class="btn-print-action btn-print-ticket" onclick="openTicketModal('<?php echo $pack['PRODUCT_NO']; ?>')">
                                                        🖨️ PRODUCT TICKET
                                                    </button>

                                                    <?php if ($is_man_customer): ?>
                                                        <button type="button" class="btn-print-action btn-print-inside" onclick="openInsideCoilModal('<?php echo $pack['PRODUCT_NO']; ?>', '<?php echo fmt2($pack['PACK_NETWEIGHT']); ?>', '<?php echo fmt2($pack['PACK_PACKAGEWEIGHT']); ?>')">
                                                            🏷️ Inside Coil
                                                        </button>
                                                        <button type="button" class="btn-print-action btn-print-outside" onclick="openOutsideCoilModal('<?php echo $pack['PRODUCT_NO']; ?>')">
                                                            🏷️ Outside Packaging
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" style="text-align:right; font-weight:700;">Total (<?php echo count($pack_list); ?> items):</td>
                                        <td style="text-align:right; color:#0f172a; font-weight:700;"><?php echo fmt2($sum_pkg_weight); ?></td>
                                        <td style="text-align:right; color:#15803d; font-weight:700;"><?php echo fmt2($sum_net_weight); ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Modal Pop-up 1: คำนวณและยืนยัน Pack Coil (ปรับแต่ง UI ใหม่ให้สวยงาม กว้าง ชัดเจน) -->
<div class="modal fade" id="modalPackCoilDetail" tabindex="-1" role="dialog" aria-labelledby="modalPackCoilDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="width: 750px;">
        <div class="modal-content pack-modal-content">
            <form id="form_confirm_pack" action="model/process_pack_coil_mats.php" method="POST">
                
                <!-- Modal Header -->
                <div class="modal-header pack-modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#ffffff; opacity:0.9; font-size: 28px;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalPackCoilDetailLabel" style="font-weight:700; font-size: 20px; display:flex; align-items:center; gap:8px;">
                        📦 รายละเอียดสำหรับ Pack Coil: <span id="modal_display_coil_no" style="color:#60a5fa; font-weight:800;"></span>
                    </h4>
                </div>

                <!-- Modal Body -->
                <div class="modal-body" style="padding: 28px; background-color: #ffffff;">
                    <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($job_order); ?>">
                    <input type="hidden" name="coil_no" id="modal_input_coil_no">
                    <input type="hidden" name="gross_weight" id="modal_input_gross_weight">

                    <!-- Section 1: Weight Calculation -->
                    <div class="pack-modal-section-title">
                        ⚖️ ข้อมูลน้ำหนักและการคำนวณ (Weight Calculation)
                    </div>
                    
                    <div class="pack-input-group">
                        <div class="row" style="margin-bottom: 16px;">
                            <div class="col-md-6">
                                <label class="pack-input-label">TOP WEIGHT (น้ำหนักส่วนบน)</label>
                                <input type="number" step="any" name="top_weight" id="modal_top_weight" value="0" oninput="calculateNetWeight()" class="pack-control-input" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="pack-input-label">BOTTOM WEIGHT (น้ำหนักส่วนล่าง)</label>
                                <input type="number" step="any" name="bottom_weight" id="modal_bottom_weight" value="0" oninput="calculateNetWeight()" class="pack-control-input" placeholder="0.00">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="pack-input-label" style="color:#047857;">TOTAL NET WEIGHT (น้ำหนักสุทธิ)</label>
                                <input type="text" name="net_weight" id="modal_net_weight" class="pack-control-input pack-control-input-net" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="pack-input-label" style="color:#1d4ed8;">GROSS WEIGHT (น้ำหนักรวม)</label>
                                <input type="text" id="modal_gross_weight_display" class="pack-control-input pack-control-input-gross" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Package Dimensions -->
                    <div class="pack-modal-section-title">
                        📐 ขนาดหีบห่อบรรจุภัณฑ์ (Package Dimensions W x L x H)
                    </div>

                    <div class="pack-input-group" style="margin-bottom: 0;">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="pack-input-label">WIDTH (ความกว้าง)</label>
                                <input type="text" name="dim_w" id="modal_dim_w" class="pack-control-input" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="pack-input-label">LENGTH (ความยาว)</label>
                                <input type="text" name="dim_l" id="modal_dim_l" class="pack-control-input" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="pack-input-label">HEIGHT (ความสูง)</label>
                                <input type="text" name="dim_h" id="modal_dim_h" class="pack-control-input" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer" style="background-color:#f8fafc; padding:18px 28px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:12px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight:600; padding:10px 22px; border-radius:8px; font-size:14px;">ยกเลิก</button>
                    <button type="submit" class="btn btn-confirm-pack-submit">
                        ✅ ยืนยันการ Pack Coil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- เรียกใช้งานไฟล์ Label Component (แยกเป็นไฟล์ๆ)             -->
<!-- ======================================================== -->
<?php include 'product_ticket_label_mats.php'; ?>
<?php include 'inside_coil_label_mats.php'; ?>
<?php include 'outside_coil_label_mats.php'; ?>

</body>
<?php include 'include/footer.php';?>

<script>
function back_home_cold(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_packing_mats.php?func='+encodeURIComponent(data_fun)); 
}

function openPackModal(coilNo, grossWeight, dimW, dimL, dimH) {
    var gross = parseFloat(grossWeight) || 0;

    document.getElementById('modal_display_coil_no').innerText = coilNo;
    document.getElementById('modal_input_coil_no').value = coilNo;
    
    document.getElementById('modal_input_gross_weight').value = gross;
    document.getElementById('modal_gross_weight_display').value = gross.toFixed(2);

    document.getElementById('modal_top_weight').value = 0;
    document.getElementById('modal_bottom_weight').value = 0;

    document.getElementById('modal_dim_w').value = parseFloat(dimW) ? parseFloat(dimW).toFixed(2) : '0.00';
    document.getElementById('modal_dim_l').value = parseFloat(dimL) ? parseFloat(dimL).toFixed(2) : '0.00';
    document.getElementById('modal_dim_h').value = parseFloat(dimH) ? parseFloat(dimH).toFixed(2) : '0.00';

    calculateNetWeight();

    $('#modalPackCoilDetail').modal('show');
}

function calculateNetWeight() {
    var gross  = parseFloat(document.getElementById('modal_input_gross_weight').value) || 0;
    var top    = parseFloat(document.getElementById('modal_top_weight').value) || 0;
    var bottom = parseFloat(document.getElementById('modal_bottom_weight').value) || 0;

    var net = gross - (top + bottom);
    document.getElementById('modal_net_weight').value = net.toFixed(2);
}

function openTicketModal(prodNo) {
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('print_no', prodNo);
    window.location.href = currentUrl.toString();
}

function openInsideCoilModal(prodNo, netKg, grossKg) {
    document.getElementById('in_lbl_coil_no').innerText = prodNo;
    
    var nKg = parseFloat(netKg.replace(/,/g, '')) || 0;
    var gKg = parseFloat(grossKg.replace(/,/g, '')) || 0;
    
    var nLbs = Math.round(nKg * 2.20462);
    var gLbs = Math.round(gKg * 2.20462);

    document.getElementById('in_lbl_net_kg').innerText = nKg.toLocaleString();
    document.getElementById('in_lbl_gross_kg').innerText = gKg.toLocaleString();
    document.getElementById('in_lbl_net_lbs').innerText = nLbs.toLocaleString();
    document.getElementById('in_lbl_gross_lbs').innerText = gLbs.toLocaleString();

    $('#modalInsideCoil').modal('show');
}

function openOutsideCoilModal(prodNo) {
    $('#modalOutsideCoil').modal('show');
}
</script>
</html>