<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');
$search_coil = isset($_GET['search_coil']) ? htmlspecialchars(trim($_GET['search_coil']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// Query ดึงข้อมูล Coil Product Details
$row_data = null;
$cmbn_list = [];
$candidate_coils = [];

if (!empty($coil_no)) {
    // 1. Query ดึงข้อมูล Coil หลัก (Group 1)
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
                p.COIL_CASTWEIGHT, p.COIL_COMBINEWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_SCRAPWEIGHT, p.COIL_BALANCEWEIGHT, 
                p.COIL_REMARK, p.LINE_PROCESS,
                i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row_data) {
        // 2. Query ดึงข้อมูลจาก CMBNPROD2 (Group 2) โดยเชื่อมโยง PRODUCT_REFERENCE = Coil หลัก
        $sql_cmbn = "SELECT COIL_NO, PRODUCT_REFERENCE, MATERIAL_IN, CMBN_DATE, CMBN_WEIGHT, CMBN_OPERATOR 
                     FROM CMBNPROD2 
                     WHERE PRODUCT_REFERENCE = :coil
                     ORDER BY COIL_NO ASC";

        $stmt_cmbn = $conn->prepare($sql_cmbn);
        $stmt_cmbn->bindParam(':coil', $coil_no, PDO::PARAM_STR);
        $stmt_cmbn->execute();
        $cmbn_list = $stmt_cmbn->fetchAll(PDO::FETCH_ASSOC);

        // 3. Query ดึงข้อมูล List Coil ที่สามารถนำมา Combine ได้ (Group 3) ตามเงื่อนไข VB + ค้นหา
        $sql_candidate = "SELECT 
                            COIL_NO, BATCH_NO, MATERIAL_IN, ALLOY, TEMPER, GRADE, 
                            SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, 
                            THICKNESS, WIDTH, COIL_ACTUALWEIGHT, COIL_STATUS, JOB_ORDER
                          FROM COILPROD1
                          WHERE ALLOY = :alloy 
                            AND TEMPER = :temper 
                            AND SURFACE_GRADE = :sg 
                            AND METALLURGICAL_GRADE = :mg 
                            AND T5_TEMPERATURE = :t5 
                            AND THICKNESS = :thickness 
                            AND WIDTH = :width 
                            AND COIL_STATUS = 'AC' 
                            AND (JOB_ORDER = '' OR JOB_ORDER IS NULL) 
                            AND COIL_NO <> :main_coil";

        if (!empty($search_coil)) {
            $sql_candidate .= " AND (COIL_NO LIKE :search OR BATCH_NO LIKE :search)";
        }

        $sql_candidate .= " ORDER BY COIL_NO ASC";

        $stmt_cand = $conn->prepare($sql_candidate);
        $stmt_cand->bindValue(':alloy', $row_data['ALLOY'] ?? '', PDO::PARAM_STR);
        $stmt_cand->bindValue(':temper', $row_data['TEMPER'] ?? '', PDO::PARAM_STR);
        $stmt_cand->bindValue(':sg', $row_data['SURFACE_GRADE'] ?? '', PDO::PARAM_STR);
        $stmt_cand->bindValue(':mg', $row_data['METALLURGICAL_GRADE'] ?? '', PDO::PARAM_STR);
        $stmt_cand->bindValue(':t5', $row_data['T5_TEMPERATURE'] ?? '', PDO::PARAM_STR);
        $stmt_cand->bindValue(':thickness', $row_data['THICKNESS'] ?? 0, PDO::PARAM_STR);
        $stmt_cand->bindValue(':width', $row_data['WIDTH'] ?? 0, PDO::PARAM_STR);
        $stmt_cand->bindValue(':main_coil', $coil_no, PDO::PARAM_STR);

        if (!empty($search_coil)) {
            $stmt_cand->bindValue(':search', '%' . $search_coil . '%', PDO::PARAM_STR);
        }
        
        $stmt_cand->execute();
        $candidate_coils = $stmt_cand->fetchAll(PDO::FETCH_ASSOC);
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

        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .info-label { 
            font-size: 12px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 15px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 18px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        .table-responsive {
            border: none !important;
            margin-top: 15px;
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
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }

        .coil-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }

        /* Header Layout สำหรับ Group 3 */
        .card-header-g3 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 14px;
            margin-bottom: 20px;
            border-bottom: 2px solid #a7f3d0;
        }

        .card-title-text {
            color: #047857;
            font-weight: 700;
            font-size: 18px;
            margin: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-box-form {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .search-input {
            height: 38px;
            width: 220px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0 14px;
            font-size: 14px;
            outline: none;
            background-color: #ffffff;
        }

        .search-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-modern {
            height: 38px;
            padding: 0 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none !important;
        }

        .btn-search-m {
            background-color: #0284c7;
            color: #ffffff;
        }
        .btn-search-m:hover { background-color: #0369a1; }

        .btn-reset-m {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
        }
        .btn-reset-m:hover { background-color: #e2e8f0; color: #334155; }

        .btn-combine-m {
            background-color: #059669;
            color: #ffffff;
        }
        .btn-combine-m:hover { background-color: #047857; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="combine_coil_mats.php?func=<?php echo $folder_func ?>">Combine Coil Process Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Coil Details: <span style="color:#2563eb;"><a href="combine_coil_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_coil()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: Product Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Group 1: Coil Product Details</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAL CAST NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['MAL_CASTNO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LINE PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['LINE_PROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT STATUS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_STATUS'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['WIDTH']) : number_format((float)($row_data['WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_THICKNESS']) : number_format((float)($row_data['F_THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_WIDTH']) : number_format((float)($row_data['F_WIDTH'] ?? 0), 3); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NEXTPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['USE_FORPROCESS'] ?? '-'); ?></div></div>

                        <!-- ส่วนข้อมูลน้ำหนัก Weight Details ของ Coil หลัก -->
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL COMBINEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_COMBINEWEIGHT']) : number_format((float)($row_data['COIL_COMBINEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUALWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL PRODUCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_PRODUCEWEIGHT']) : number_format((float)($row_data['COIL_PRODUCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL SCRAPWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_SCRAPWEIGHT']) : number_format((float)($row_data['COIL_SCRAPWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">COIL REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 2: Combine Production Details (CMBNPROD2) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📋 Group 2: Combine Production Records</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Coil No</th>
                                    <th>Product Reference</th>
                                    <th>Material In</th>
                                    <th style="text-align: center;">Combine Date</th>
                                    <th style="text-align: right;">Combine Weight (KG)</th>
                                    <th style="text-align: center;">Combine Operator</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cmbn_list)): ?>
                                    <tr>
                                        <td colspan="7" align="center" style="color: #64748b; padding: 20px;">
                                            No records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cmbn_list as $index => $cmbn): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($cmbn['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($cmbn['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($cmbn['MATERIAL_IN'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($cmbn['CMBN_DATE'] ?? '-'); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($cmbn['CMBN_WEIGHT']) : number_format((float)($cmbn['CMBN_WEIGHT'] ?? 0), 2); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($cmbn['CMBN_OPERATOR'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GROUP 3: Select Coils for Combine -->
                <div class="dashboard-card">
                    <div class="card-header-g3">
                        <h4 class="card-title-text">
                            📋 Group 3: Select Coils to Combine
                        </h4>

                        <div class="header-actions">
                            <form method="GET" action="combine_coil_update_mats.php" class="search-box-form">
                                <input type="hidden" name="COIL" value="<?php echo htmlspecialchars($coil_no); ?>">
                                <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">
                                
                                <input type="text" name="search_coil" class="search-input" placeholder="Search Coil No / Batch..." value="<?php echo htmlspecialchars($search_coil); ?>">
                                
                                <button type="submit" class="btn-modern btn-search-m">
                                    🔍 Search
                                </button>
                                
                                <?php if (!empty($search_coil)): ?>
                                    <a href="combine_coil_update_mats.php?COIL=<?php echo urlencode($coil_no); ?>&func=<?php echo urlencode($folder_func); ?>" class="btn-modern btn-reset-m">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </form>

                            <button type="button" class="btn-modern btn-combine-m" onclick="submitCombineForm();">
                                🔗 Combine Selected Coils
                            </button>
                        </div>
                    </div>

                    <form id="formCombine" method="POST" action="model/combine_coil_save_action.php">
                        <input type="hidden" name="main_coil_no" value="<?php echo htmlspecialchars($coil_no); ?>">
                        <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">

                        <div class="table-responsive">
                            <table class="table custom-job-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 50px;">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="coil-checkbox">
                                        </th>
                                        <th style="text-align: center;">#</th>
                                        <th>Coil No</th>
                                        <th>Batch No</th>
                                        <th>Material In</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>SG</th>
                                        <th>MG</th>
                                        <th style="text-align: center;">T5 Temp</th>
                                        <th style="text-align: center;">Thickness</th>
                                        <th style="text-align: center;">Width</th>
                                        <th style="text-align: right;">Actual Weight (KG)</th>
                                        <th style="text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($candidate_coils)): ?>
                                        <tr>
                                            <td colspan="14" align="center" style="color: #64748b; padding: 24px;">
                                                No matching candidate coils found for combine condition.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($candidate_coils as $index => $cand): ?>
                                            <tr>
                                                <td align="center">
                                                    <input type="checkbox" name="selected_coils[]" value="<?php echo htmlspecialchars($cand['COIL_NO']); ?>" class="coil-checkbox candidate-chk">
                                                </td>
                                                <td align="center"><?php echo $index + 1; ?></td>
                                                <td style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($cand['COIL_NO'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['BATCH_NO'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['MATERIAL_IN'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['ALLOY'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['TEMPER'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['SURFACE_GRADE'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($cand['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($cand['T5_TEMPERATURE'] ?? '-'); ?></td>
                                                <td align="center"><?php echo function_exists('fmt3') ? fmt3($cand['THICKNESS']) : number_format((float)($cand['THICKNESS'] ?? 0), 3); ?></td>
                                                <td align="center"><?php echo function_exists('fmt2') ? fmt2($cand['WIDTH']) : number_format((float)($cand['WIDTH'] ?? 0), 2); ?></td>
                                                <td align="right"><?php echo function_exists('fmt2') ? fmt2($cand['COIL_ACTUALWEIGHT']) : number_format((float)($cand['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                                <td align="center">
                                                    <span style="color: #059669; font-weight: 700; background-color: #ecfdf5; padding: 4px 10px; border-radius: 6px; border: 1px solid #a7f3d0; font-size: 13px;">
                                                        <?php echo htmlspecialchars($cand['COIL_STATUS'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
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
function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('combine_coil_mats.php?func='+encodeURIComponent(data_fun)); 
}

function toggleSelectAll(master) {
    var checkboxes = document.querySelectorAll('.candidate-chk');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = master.checked;
    }
}

// ปรับปรุงการกดปุ่ม Combine ให้สั่ง Submit Form ตรงๆ
function submitCombineForm() {
    var selected = document.querySelectorAll('.candidate-chk:checked');
    if (selected.length === 0) {
        alert('กรุณาเลือก Coil อย่างน้อย 1 รายการเพื่อทำ Combine');
        return false;
    }
    
    if (confirm('คุณต้องการ Combine Coil ที่เลือกจำนวน ' + selected.length + ' รายการ ใช่หรือไม่?')) {
        document.getElementById('formCombine').submit();
    }
}
</script>
</html>