<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL
$job_order = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : (isset($_GET['JOB_ORDER']) ? htmlspecialchars(trim($_GET['JOB_ORDER']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data          = null;
$coils_list        = [];
$total_coil_count  = 0;
$total_cm_weight   = 0;

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล JOB Detail & History Job Product Process จาก JOBORDER1
    $sql_job = "SELECT 
                    JOB_ORDER, JOBORDER_DATE, ALLOY, SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2,
                    GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, THICKNESS, WIDTH, JOB_UOMDIMENSION, WEIGHT_PIECE, 
                    T5_TEMPERATURE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_MAXTOLERANCE, JOB_WORKPROCESS, USE_FORPROCESS,
                    JOB_REMARK, JOB_STATUS, JOB_OPERATOR, JOB_UOMWEIGHT,
                    /*** History Job Product Process ***/
                    JOB_RELEASEWEIGHT, JOB_RELEASEPIECE, JOB_ACTUALWEIGHT, JOB_ACTUALPIECE,
                    JOB_COLDMILLWEIGHT, JOB_COLDMILLPIECE, JOB_PRODUCEWEIGHT, JOB_PRODUCEPIECE,
                    JOB_STRETCHERWEIGHT, JOB_STRETCHERPIECE, JOB_CUTSHEETWEIGHT, JOB_CUTSHEETPIECE,
                    JOB_SHEARWEIGHT, JOB_SHEARPIECE, JOB_PUNCHWEIGHT, JOB_PUNCHPIECE,
                    JOB_BATCHANNEALWEIGHT, JOB_BATCHANNEALPIECE, JOB_TRANSFERWEIGHT, JOB_TRANSFERPIECE,
                    JOB_ANNEALWEIGHT, JOB_ANNEALPIECE, JOB_SORTWEIGHT, JOB_SORTPIECE,
                    JOB_TAKEOUTWEIGHT, JOB_TAKEOUTPIECE, JOB_PACKWEIGHT, JOB_PACKPIECE,
                    JOB_STOCKWEIGHT, JOB_STOCKPIECE
                FROM [MATS-NEW].dbo.JOBORDER1
                WHERE JOB_ORDER = :job_order
                ORDER BY JOBORDER_DATE DESC";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Group 2: Coil Cold Mill Detail List จาก COILPROD1
    $sql_coils = "SELECT JOB_PROCESS, COIL_NO, PRODUCT_REFERENCE, PRODUCT_ID, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
                         THICKNESS, WIDTH, COIL_WORKPROCESS, COIL_NEXTPROCESS, RECIPE_NO, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL,
                         COIL_ACTUALWEIGHT, COIL_COLDMILLWEIGHT, PRODUCE_FLAG, COIL_OPERATEDATE, COIL_STATUS
                  FROM COILPROD1
                  WHERE JOB_PROCESS = :job_order AND PRODUCT_REFERENCE is not null AND PRODUCT_REFERENCE != ''";

    $stmt_coils = $conn->prepare($sql_coils);
    $stmt_coils->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_coils->execute();
    $coils_list = $stmt_coils->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณหาผลรวมจำนวน และ น้ำหนักรวม Cold Mill Weight
    if (!empty($coils_list)) {
        $total_coil_count = count($coils_list);
        foreach ($coils_list as $c_row) {
            $total_cm_weight += (float)($c_row['COIL_COLDMILLWEIGHT'] ?? 0);
        }
    }
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

// ฟังก์ชันช่วยจัดรูปแบบขนาด (Thickness 3 ทศนิยม)
function format_dim($value) {
    if (function_exists('fmt3')) {
        return fmt3($value);
    }
    return number_format((float)($value ?? 0), 3);
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
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }
        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

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

        .coil-table-container {
            overflow-x: auto;
        }
        .coil-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background-color: #ffffff;
        }
        .coil-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }
        .coil-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .coil-table tbody tr:hover {
            background-color: #f1f5f9;
        }
        .coil-table tfoot td {
            background-color: #f8fafc;
            font-weight: 700;
            color: #0f172a;
            padding: 12px 10px;
            border-top: 2px solid #cbd5e1;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_coil_log_mats.php?func=<?php echo $folder_func ?>">Coil Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Details: <span style="color:#2563eb;"><a href="history_coil_log_mats.php?func=<?php echo $folder_func ?>&search_no=<?php echo $job_order?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
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

                <!-- GROUP 1: JOB Order Details -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 Job Order Details</h4>

                    <!-- General & Primary Info -->
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">OPERATOR</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_OPERATOR'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo format_dim($job_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo format_dim($job_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">DIMENSION UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMDIMENSION'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['USE_FORPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MIN / MAX TOLERANCE</div><div class="info-value"><?php echo format_dim($job_data['CUST_MINTOLERANCE']); ?> / <?php echo format_dim($job_data['CUST_MAXTOLERANCE']); ?></div></div>

                        <?php
                            $MAXReleaseWeight = ($job_data['JOB_RELEASEWEIGHT']) + ((($job_data['JOB_RELEASEWEIGHT']) * $job_data['JOB_MAXTOLERANCE']) / 100);
                            $MAXReleasePiece = ($job_data['WEIGHT_PIECE'] > 0) ? number_format(($MAXReleaseWeight / $job_data['WEIGHT_PIECE']), 0) : 0;
                        ?>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX RELEASE WT. / PCS.</div><div class="info-value"><?php echo format_wt($MAXReleaseWeight); ?> kg. / <?php echo htmlspecialchars($MAXReleasePiece); ?> pcs.</div></div>

                        <div class="col-md-9 col-sm-12"><div class="info-label">JOB REMARK</div><div class="info-value" style="min-height:38px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: History Job Product Process -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ History Job Product Process</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_RELEASEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_RELEASEPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL QTY WT. / PCS.</div><div class="info-value" style="color:#0284c7; font-weight:700;"><?php echo format_wt($job_data['JOB_ACTUALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_ACTUALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_COLDMILLWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_COLDMILLPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PRODUCEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PRODUCEPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">STRETCHER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_STRETCHERWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_STRETCHERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT SHEET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_CUTSHEETWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_CUTSHEETPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SHEARING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_SHEARWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_SHEARPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PUNCH HOLE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PUNCHWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PUNCHPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_BATCHANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_BATCHANNEALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER PALLET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_TRANSFERWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_TRANSFERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ANNEALING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_ANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_ANNEALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SORTING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_SORTWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_SORTPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TAKEOUT QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_TAKEOUTWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_TAKEOUTPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PACKWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PACKPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK QTY WT. / PCS.</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_wt($job_data['JOB_STOCKWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_STOCKPIECE']); ?> pcs.</div></div>
                    </div>
                </div>

                <!-- GROUP 3: Coil Cold Mill Detail List -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">🌀 Coil Cold Mill List (Job Process: <?php echo htmlspecialchars($job_order); ?>)</h4>
                    
                    <?php if (empty($coils_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">ไม่พบข้อมูลรายการ Coil ใน Job Process นี้</div>
                    <?php else: ?>
                        <div class="coil-table-container">
                            <table class="coil-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Coil No</th>
                                        <th>Product Ref</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Grade</th>
                                        <th>Surface Grade</th>
                                        <th>Met Grade</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Work Process</th>
                                        <th>Next Process</th>
                                        <th>Recipe No</th>
                                        <th style="text-align: right;">Cold Mill Weight</th>
                                        <th>Operate Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coils_list as $index => $coil): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($coil['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['TEMPER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo format_dim($coil['THICKNESS']); ?></td>
                                            <td><?php echo format_dim($coil['WIDTH']); ?></td>
                                            <td><?php echo htmlspecialchars($coil['COIL_WORKPROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['COIL_NEXTPROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['RECIPE_NO'] ?? '-'); ?></td>
                                            <td align="right" style="font-weight: 600; color: #0369a1;"><?php echo format_wt($coil['COIL_COLDMILLWEIGHT']); ?> kg.</td>
                                            <td><?php echo htmlspecialchars($coil['COIL_OPERATEDATE'] ?? '-'); ?></td>
                                            <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($coil['COIL_STATUS'] ?? '-'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="13" align="right" style="font-weight: 700; font-size: 14px;">
                                            Total Items / Total Cold Mill Weight:
                                        </td>
                                        <td align="right" style="font-weight: 700; font-size: 15px; color: #0284c7;">
                                            <?php echo format_wt($total_cm_weight); ?> kg.
                                        </td>
                                        <td colspan="2" align="left" style="font-weight: 700; font-size: 14px; color: #059669;">
                                            (<?php echo number_format($total_coil_count); ?> Rolls)
                                        </td>
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

</body>
<?php include 'include/footer.php';?>

<script>
function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_coil_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>