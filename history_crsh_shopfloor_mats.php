<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า PRODUCT_NO และ COIL_NO จาก URL (รองรับทั้ง PRODUCT_NO และ search_no)
$product_no = isset($_GET['PRODUCT_NO']) ? htmlspecialchars(trim($_GET['PRODUCT_NO']), ENT_QUOTES, 'UTF-8') : (isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : '');
$coil_no    = isset($_GET['COIL_NO']) ? htmlspecialchars(trim($_GET['COIL_NO']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// ตัวแปรสำหรับเก็บข้อมูลทั้ง 5 ส่วน
$pack_data        = null; // 1. Packing Information
$pallet_combine   = [];   // 2. List Pallet Combine
$pallet_split     = [];   // 3. List Pallet Split
$sort_data        = null; // 4. List Sort
$scrap_list       = [];   // 5. List Scrap

// ตัวแปรเช็คการมีอยู่ของแต่ละ Process ใน Pallet Split
$has_split_cols = [
    'actual'       => false,
    'produce'      => false,
    'combine'      => false,
    'anneal'       => false,
    'stretcher'    => false,
    'cutsheet'     => false,
    'shear'        => false,
    'punch'        => false,
    'batch_anneal' => false,
    'transfer'     => false,
    'sort'         => false,
    'pack'         => false,
];

if (!empty($product_no)) {

    /*** 0. ดึง WEIGHT_PIECE จาก CRSHPROD1 เพื่อใช้คำนวณ PACK_NETPIECE ***/
    $weight_piece = 0;
    $sql_crsh_base = "SELECT WEIGHT_PIECE, COIL_NO FROM CRSHPROD1 WHERE PRODUCT_NO = :product_no";
    $stmt_crsh_base = $conn->prepare($sql_crsh_base);
    $stmt_crsh_base->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_crsh_base->execute();
    $crsh_base = $stmt_crsh_base->fetch(PDO::FETCH_ASSOC);
    if ($crsh_base) {
        $weight_piece = floatval($crsh_base['WEIGHT_PIECE'] ?? 0);
        if (empty($coil_no) && !empty($crsh_base['COIL_NO'])) {
            $coil_no = $crsh_base['COIL_NO'];
        }
    }

    /*** 1. Packing Information (PACKPROD1) ***/
    $sql_pack = "SELECT TRANSFER_NO, TRANSFER_DATE, PACK_DATE, PACK_NETWEIGHT, PACK_PACKAGEWEIGHT, 
                        PACK_REMARK, PACK_OPERATOR1, PACK_OPERATEDATE, PACK_UPDATE, PACK_UPDATEDATE 
                 FROM PACKPROD1 WHERE PRODUCT_NO = :product_no";
    $stmt_pack = $conn->prepare($sql_pack);
    $stmt_pack->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_pack->execute();
    $row_pack = $stmt_pack->fetch(PDO::FETCH_ASSOC);

    if ($row_pack) {
        $net_weight     = floatval($row_pack['PACK_NETWEIGHT'] ?? 0);
        $package_weight = floatval($row_pack['PACK_PACKAGEWEIGHT'] ?? 0);
        
        $net_piece = ($weight_piece != 0) ? round($net_weight / $weight_piece) : 0;
        $gross_weight = $net_weight + $package_weight;

        $pack_data = [
            'TRANSFER_NO'      => $row_pack['TRANSFER_NO'] ?? '-',
            'TRANSFER_DATE'    => $row_pack['TRANSFER_DATE'] ?? '-',
            'PACK_DATE'        => $row_pack['PACK_DATE'] ?? '-',
            'PACK_NETWEIGHT'   => $net_weight,
            'PACK_PACKAGEWEIGHT' => $package_weight,
            'PACK_NETPIECE'    => $net_piece,
            'PACK_GROSSWEIGHT' => $gross_weight,
            'PACK_REMARK'      => $row_pack['PACK_REMARK'] ?? '-',
            'PACK_OPERATOR'    => trim(($row_pack['PACK_OPERATOR1'] ?? '') . " " . ($row_pack['PACK_OPERATEDATE'] ?? '')),
            'PACK_UPDATE'      => trim(($row_pack['PACK_UPDATE'] ?? '') . " " . ($row_pack['PACK_UPDATEDATE'] ?? ''))
        ];
    }

    /*** 2. List Pallet Combine (CMBNPROD1) ***/
    $sql_cmb = "SELECT PRODUCT_NO, CMBN_WEIGHT FROM CMBNPROD1 WHERE PRODUCT_NO = :product_no";
    $stmt_cmb = $conn->prepare($sql_cmb);
    $stmt_cmb->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_cmb->execute();
    $pallet_combine = $stmt_cmb->fetchAll(PDO::FETCH_ASSOC);

    /*** 3. List Pallet Split (CRSHPROD1) ***/
    $sql_split = "SELECT PRODUCT_NO, CRSH_WORKPROCESS, CRSH_NEXTPROCESS, 
                         WEIGHT_PIECE, CUT_WEIGHTPIECE, 
                         CRSH_ACTUALPIECE, CRSH_ACTUALWEIGHT, 
                         CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE, 
                         CRSH_COMBINEPIECE, CRSH_COMBINEWEIGHT, 
                         CRSH_ANNEALWEIGHT, CRSH_ANNEALPIECE, 
                         CRSH_STRETCHERWEIGHT, CRSH_STRETCHERPIECE, 
                         CRSH_CUTSHEETWEIGHT, CRSH_CUTSHEETPIECE, 
                         CRSH_SHEARWEIGHT, CRSH_SHEARPIECE, 
                         CRSH_PUNCHWEIGHT, CRSH_PUNCHPIECE, 
                         CRSH_BATCHANNEALWEIGHT, CRSH_BATCHANNEALPIECE, 
                         CRSH_TRANSFERWEIGHT, CRSH_TRANSFERPIECE, 
                         CRSH_SORTWEIGHT, CRSH_SORTPIECE, 
                         CRSH_PACKWEIGHT, CRSH_PACKPIECE 
                  FROM CRSHPROD1 WHERE PRODUCT_REFERENCE = :product_no";
    $stmt_split = $conn->prepare($sql_split);
    $stmt_split->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_split->execute();
    $pallet_split = $stmt_split->fetchAll(PDO::FETCH_ASSOC);

    // ตรวจสอบว่ามีข้อมูลในแต่ละคอลัมน์ของ Split หรือไม่ เพื่อแสดงเฉพาะคอลัมน์ที่มีข้อมูล
    foreach ($pallet_split as $r) {
        if (floatval($r['CRSH_ACTUALWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_ACTUALPIECE'] ?? 0) > 0) $has_split_cols['actual'] = true;
        if (floatval($r['CRSH_PRODUCEWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_PRODUCEPIECE'] ?? 0) > 0) $has_split_cols['produce'] = true;
        if (floatval($r['CRSH_COMBINEWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_COMBINEPIECE'] ?? 0) > 0) $has_split_cols['combine'] = true;
        if (floatval($r['CRSH_ANNEALWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_ANNEALPIECE'] ?? 0) > 0) $has_split_cols['anneal'] = true;
        if (floatval($r['CRSH_STRETCHERWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_STRETCHERPIECE'] ?? 0) > 0) $has_split_cols['stretcher'] = true;
        if (floatval($r['CRSH_CUTSHEETWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_CUTSHEETPIECE'] ?? 0) > 0) $has_split_cols['cutsheet'] = true;
        if (floatval($r['CRSH_SHEARWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_SHEARPIECE'] ?? 0) > 0) $has_split_cols['shear'] = true;
        if (floatval($r['CRSH_PUNCHWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_PUNCHPIECE'] ?? 0) > 0) $has_split_cols['punch'] = true;
        if (floatval($r['CRSH_BATCHANNEALWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_BATCHANNEALPIECE'] ?? 0) > 0) $has_split_cols['batch_anneal'] = true;
        if (floatval($r['CRSH_TRANSFERWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_TRANSFERPIECE'] ?? 0) > 0) $has_split_cols['transfer'] = true;
        if (floatval($r['CRSH_SORTWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_SORTPIECE'] ?? 0) > 0) $has_split_cols['sort'] = true;
        if (floatval($r['CRSH_PACKWEIGHT'] ?? 0) > 0 || floatval($r['CRSH_PACKPIECE'] ?? 0) > 0) $has_split_cols['pack'] = true;
    }

    /*** 4. List Sort (SORTPROD1) ***/
    $sql_sort = "SELECT SORT_ACCEPTWEIGHT, SORT_REJECTWEIGHT FROM SORTPROD1 WHERE PRODUCT_NO = :product_no";
    $stmt_sort = $conn->prepare($sql_sort);
    $stmt_sort->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_sort->execute();
    $sort_data = $stmt_sort->fetch(PDO::FETCH_ASSOC);

    /*** 5. List Scrap (CMBNSCRP1 & SCRPPROD1) ***/
    $sql_scrap = "SELECT B.PRODUCT_NO, A.PRODUCT_REFERENCE, A.REFERENCE_NO, B.COIL_NO, B.MATERIAL_IN, B.LINE_PROCESS, B.PROCESS,
                         B.SCRP_FROM, B.ALLOY, B.SCRP_WEIGHT, B.LOCATION_ID, B.SCRP_OPERATOR, B.SCRP_OPERATEDATE 
                  FROM CMBNSCRP1 AS A RIGHT OUTER JOIN SCRPPROD1 AS B ON A.PRODUCT_NO = B.PRODUCT_NO 
                  WHERE (A.PRODUCT_REFERENCE = :product_no) OR (B.COIL_NO = :coil_no AND :coil_no_check != '')";
    $stmt_scrap = $conn->prepare($sql_scrap);
    $stmt_scrap->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_scrap->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_scrap->bindParam(':coil_no_check', $coil_no, PDO::PARAM_STR);
    $stmt_scrap->execute();
    $scrap_list = $stmt_scrap->fetchAll(PDO::FETCH_ASSOC);
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนชิ้น (Pcs)
function format_pcs($value) {
    if (function_exists('fmt0')) {
        return fmt0($value);
    }
    return number_format((float)($value ?? 0), 0);
}

// ฟังก์ชันช่วยจัดรูปแบบน้ำหนัก (Weight 2 ทศนิยม)
function format_wt($value) {
    if (function_exists('fmt2')) {
        return fmt2($value);
    }
    return number_format((float)($value ?? 0), 2);
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

        /* หัวข้อทั้ง 5 ส่วน */
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g3 { color: #047857; border-bottom: 2px solid #a7f3d0; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g4 { color: #d97706; border-bottom: 2px solid #fde68a; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g5 { color: #b91c1c; border-bottom: 2px solid #fca5a5; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }

        .info-label { 
            font-size: 11px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 14px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 16px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .table-responsive {
            border: none !important;
            margin-top: 10px;
        }
        .custom-job-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        .custom-job-table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'HI';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Shop Floor Details: <span style="color:#2563eb;"><a href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $product_no ?>"><?php echo htmlspecialchars($product_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (empty($product_no)): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        Please specify a valid <strong>PRODUCT_NO</strong>.
                    </div>
                </div>
            <?php else: ?>

                <!-- 1. Packing Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 1. Packing Information</h4>
                    <?php if (!$pack_data): ?>
                        <div class="alert alert-info" style="margin:0;">ไม่พบข้อมูล Packing Information สำหรับ Product No. นี้</div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER NO.</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($pack_data['TRANSFER_NO']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER DATE</div><div class="info-value"><?php echo htmlspecialchars($pack_data['TRANSFER_DATE']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">PACK DATE</div><div class="info-value"><?php echo htmlspecialchars($pack_data['PACK_DATE']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">PACKAGE WEIGHT</div><div class="info-value"><?php echo format_wt($pack_data['PACK_PACKAGEWEIGHT']); ?> kg.</div></div>

                            <div class="col-md-4 col-sm-4"><div class="info-label">NET WEIGHT</div><div class="info-value"><?php echo format_wt($pack_data['PACK_NETWEIGHT']); ?> kg.</div></div>
                            <div class="col-md-4 col-sm-4"><div class="info-label">NET PIECE</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_pcs($pack_data['PACK_NETPIECE']); ?> pcs.</div></div>
                            <div class="col-md-4 col-sm-4"><div class="info-label">GROSS WEIGHT</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo format_wt($pack_data['PACK_GROSSWEIGHT']); ?> kg.</div></div>

                            <div class="col-md-6 col-sm-6"><div class="info-label">PACKING OPERATOR</div><div class="info-value"><?php echo htmlspecialchars($pack_data['PACK_OPERATOR'] !== '' ? $pack_data['PACK_OPERATOR'] : '-'); ?></div></div>
                            <div class="col-md-6 col-sm-6"><div class="info-label">USER UPDATE</div><div class="info-value"><?php echo htmlspecialchars($pack_data['PACK_UPDATE'] !== '' ? $pack_data['PACK_UPDATE'] : '-'); ?></div></div>

                            <div class="col-md-12 col-sm-12" style="width: 100%;">
                                <div class="info-label">PACK REMARK</div>
                                <div class="info-value" style="background-color:#eff6ff; border-color:#bfdbfe; color:#1e40af; min-height:42px;">
                                    <?php echo htmlspecialchars($pack_data['PACK_REMARK'] !== '' ? $pack_data['PACK_REMARK'] : '-'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. List Pallet Combine -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">🔗 2. Pallet Combine List</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center; width: 60px;">#</th>
                                    <th>Product No</th>
                                    <th style="text-align: right;">Combine Weight (kg.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pallet_combine)): ?>
                                    <tr>
                                        <td colspan="3" align="center" style="color: #64748b; padding: 15px;">
                                            No pallet combine records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pallet_combine as $idx => $row): ?>
                                        <tr>
                                            <td align="center"><?php echo $idx + 1; ?></td>
                                            <td style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($row['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td align="right" style="font-weight: 700;"><?php echo format_wt($row['CMBN_WEIGHT']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. List Pallet Split -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">✂️ 3. Pallet Split List</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th style="text-align: center;">Work Process</th>
                                    <th style="text-align: center;">Next Process</th>
                                    <th style="text-align: right;">Wt. / Piece</th>
                                    <th style="text-align: right;">Cut Wt. Piece</th>
                                    
                                    <!-- แสดง Header เฉพาะคอลัมน์ที่มีข้อมูลมากกว่า 0 -->
                                    <?php if ($has_split_cols['actual']): ?><th style="text-align: right;">Actual (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['produce']): ?><th style="text-align: right;">Produce (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['combine']): ?><th style="text-align: right;">Combine (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['anneal']): ?><th style="text-align: right;">Anneal (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['stretcher']): ?><th style="text-align: right;">Stretcher (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['cutsheet']): ?><th style="text-align: right;">Cut Sheet (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['shear']): ?><th style="text-align: right;">Shear (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['punch']): ?><th style="text-align: right;">Punch (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['batch_anneal']): ?><th style="text-align: right;">Batch Anneal (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['transfer']): ?><th style="text-align: right;">Transfer (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['sort']): ?><th style="text-align: right;">Sort (Wt / Pcs)</th><?php endif; ?>
                                    <?php if ($has_split_cols['pack']): ?><th style="text-align: right;">Pack (Wt / Pcs)</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pallet_split)): ?>
                                    <tr>
                                        <td colspan="18" align="center" style="color: #64748b; padding: 15px;">
                                            No pallet split records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pallet_split as $idx => $row): ?>
                                        <tr>
                                            <td align="center"><?php echo $idx + 1; ?></td>
                                            <td style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($row['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td align="center"><span class="label label-default"><?php echo htmlspecialchars($row['CRSH_WORKPROCESS'] ?? '-'); ?></span></td>
                                            <td align="center"><span class="label label-info"><?php echo htmlspecialchars($row['CRSH_NEXTPROCESS'] ?? '-'); ?></span></td>
                                            <td align="right"><?php echo format_wt($row['WEIGHT_PIECE']); ?></td>
                                            <td align="right"><?php echo format_wt($row['CUT_WEIGHTPIECE']); ?></td>

                                            <!-- แสดง Row Data เฉพาะคอลัมน์ที่ถูกเปิดการแสดงผล -->
                                            <?php if ($has_split_cols['actual']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_ACTUALWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_ACTUALPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['produce']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_PRODUCEWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_PRODUCEPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['combine']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_COMBINEWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_COMBINEPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['anneal']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_ANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_ANNEALPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['stretcher']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_STRETCHERWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_STRETCHERPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['cutsheet']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_CUTSHEETWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_CUTSHEETPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['shear']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_SHEARWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_SHEARPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['punch']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_PUNCHWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_PUNCHPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['batch_anneal']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_BATCHANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_BATCHANNEALPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['transfer']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_TRANSFERWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_TRANSFERPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['sort']): ?>
                                                <td align="right"><?php echo format_wt($row['CRSH_SORTWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_SORTPIECE']); ?> pcs.</td>
                                            <?php endif; ?>

                                            <?php if ($has_split_cols['pack']): ?>
                                                <td align="right" style="font-weight:700; color:#2563eb;"><?php echo format_wt($row['CRSH_PACKWEIGHT']); ?> kg. / <?php echo format_pcs($row['CRSH_PACKPIECE']); ?> pcs.</td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. List Pallet Scrap from Sort -->
                <div class="dashboard-card">
                    <h4 class="card-title-g4">⚖️ 4. Pallet Scrap from Sort</h4>
                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <div class="info-label">ACCEPT WEIGHT</div>
                            <div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;">
                                <?php echo format_wt($sort_data['SORT_ACCEPTWEIGHT'] ?? 0); ?> kg.
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="info-label">REJECT WEIGHT (SCRAP)</div>
                            <div class="info-value" style="color:#dc2626; font-weight:700; background-color:#fef2f2; border-color:#fecaca;">
                                <?php echo format_wt($sort_data['SORT_REJECTWEIGHT'] ?? 0); ?> kg.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. List Scrap -->
                <div class="dashboard-card">
                    <h4 class="card-title-g5">🗑️ 5. Scrap Information</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th>Product Ref</th>
                                    <th>Ref No</th>
                                    <th>Coil No</th>
                                    <th>Line Process</th>
                                    <th>Process</th>
                                    <th>Scrap From</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: right;">Scrap Weight (kg.)</th>
                                    <th style="text-align: center;">Location</th>
                                    <th>Operator / Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($scrap_list)): ?>
                                    <tr>
                                        <td colspan="12" align="center" style="color: #64748b; padding: 15px;">
                                            No scrap records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($scrap_list as $idx => $row): ?>
                                        <tr>
                                            <td align="center"><?php echo $idx + 1; ?></td>
                                            <td style="font-weight: 700; color: #b91c1c;"><?php echo htmlspecialchars($row['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['REFERENCE_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['LINE_PROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['PROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['SCRP_FROM'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['ALLOY'] ?? '-'); ?></td>
                                            <td align="right" style="font-weight: 700; color: #dc2626;"><?php echo format_wt($row['SCRP_WEIGHT']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['LOCATION_ID'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars(trim(($row['SCRP_OPERATOR'] ?? '') . " " . ($row['SCRP_OPERATEDATE'] ?? ''))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>