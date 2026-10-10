<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL (รองรับทั้ง search_no และ JOB_ORDER)
$job_order = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : (isset($_GET['JOB_ORDER']) ? htmlspecialchars(trim($_GET['JOB_ORDER']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data   = null;
$job_items  = [];
$crsh_data  = null;
$batch_list = [];

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล JOB Information จาก JOBORDER1
    $sql_job = "SELECT 
                    JOB_ORDER, JOB_STATUS, ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2,
                    SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, FLATNESS_GRADE, THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE,
                    CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, JOB_WORKPROCESS, JOBORDER_DATE, SCHEDULE_DATE,
                    JOB_RELEASEWEIGHT, JOB_RELEASEPIECE, JOB_MAXTOLERANCE, JOB_MINTOLERANCE, JOB_REMARK
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล JOB Order Item List จาก [MATS-NEW].dbo.CRSHPROD1
    $sql_item = "SELECT 
                    PRODUCT_NO, SALEORDER_NO, PRODUCT_ID, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
                    THICKNESS, WIDTH, LENGTH, CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE, CRSH_STATUS
                 FROM [MATS-NEW].dbo.CRSHPROD1 
                 WHERE JOB_ORDER = :job_order
                 ORDER BY CRSH_STARTDATE DESC";

    $stmt_item = $conn->prepare($sql_item);
    $stmt_item->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_item->execute();
    $job_items = $stmt_item->fetchAll(PDO::FETCH_ASSOC);

    // 3. Query ดึงข้อมูล History Job Product Process จาก [MATS-NEW].dbo.CRSHPROD1
    $sql_crsh = "SELECT 
                    sum(CRSH_ACTUALWEIGHT) as totalAct, sum(CRSH_ACTUALPIECE) as totalActP,
                    sum(CRSH_PRODUCEWEIGHT) as totalPro, sum(CRSH_PRODUCEPIECE) as totalProP,
                    sum(CRSH_STRETCHERWEIGHT) as totalStr, sum(CRSH_STRETCHERPIECE) as totalStrP,
                    sum(CRSH_CUTSHEETWEIGHT) as totalcutsh, sum(CRSH_CUTSHEETPIECE) as totalcutshP,
                    sum(CRSH_SHEARWEIGHT) as totalsher, sum(CRSH_SHEARPIECE) as totalsherP,
                    sum(CRSH_PUNCHWEIGHT) as totalPun, sum(CRSH_PUNCHPIECE) as totalPunP,
                    sum(CRSH_BATCHANNEALWEIGHT) as totalBatch, sum(CRSH_BATCHANNEALPIECE) as totalBatchP,
                    sum(CRSH_TRANSFERWEIGHT) as totaltran, sum(CRSH_TRANSFERPIECE) as totaltranP,
                    sum(CRSH_ANNEALWEIGHT) as totalAnn, sum(CRSH_ANNEALPIECE) as totalAnnP,
                    sum(CRSH_COMBINEWEIGHT) as totalCom, sum(CRSH_COMBINEPIECE) as totalComP,
                    sum(CRSH_SORTWEIGHT) as TotalSort, sum(CRSH_SORTPIECE) as TotalSortP,
                    sum(CRSH_TAKEOUTWEIGHT) as totalTake, sum(CRSH_TAKEOUTPIECE) as totalTakeP,
                    sum(CRSH_PACKWEIGHT) as totalPack, sum(CRSH_PACKPIECE) as totalPackP
                FROM [MATS-NEW].dbo.CRSHPROD1 
                WHERE JOB_ORDER = :job_order";

    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_crsh->execute();
    $crsh_data = $stmt_crsh->fetch(PDO::FETCH_ASSOC);

    // 4. Query ดึงข้อมูล Batch Annealing by Job Order จาก BTCHPROD0
    $sql_batch = "SELECT 
                    PRODUCT_NO, BTCH_NO, PALLET_ITEM, BTCH_STATUS, PRODUCT_ID, ALLOY, TEMPER_INITIAL, TEMPER_TARGET,
                    THICKNESS, WIDTH, LENGTH, BTCH_INPUTWEIGHT, BTCH_INPUTPIECE, 
                    BTCH_STARTDATE AS START_Date, BTCH_INPUTDATE AS OPEN_Date,
                    BTCH_PRODREMARK AS Product_Remark, BTCH_TECHREMARK AS Technical_Remark,
                    BTCH_OPERATOR AS OPERATOR, BTCH_UPDATE AS OPERATOR_UPDATE
                  FROM BTCHPROD0
                  WHERE JOB_ORDER = :job_order
                  ORDER BY BTCH_NO ASC, PALLET_ITEM ASC";

    $stmt_batch = $conn->prepare($sql_batch);
    $stmt_batch->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_batch->execute();
    $batch_list = $stmt_batch->fetchAll(PDO::FETCH_ASSOC);
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนชิ้น (Pcs) ป้องกัน Error
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

        .card-title-g3 { 
            color: #047857; 
            border-bottom: 2px solid #a7f3d0; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .card-title-g4 { 
            color: #d97706; 
            border-bottom: 2px solid #fde68a; 
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

<?php $menu = 'A2';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="mainta_job_so_mats.php?func=<?php echo $folder_func ?>">Maintain Job Order and Sale Order of Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Order Details: <span style="color:#2563eb;"><a href="mainta_job_so_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $job_order ?>"><?php echo htmlspecialchars($job_order); ?></a></span>
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

                <!-- 1. JOB Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 1. JOB Information</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMP / FLATNESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?> / <?php echo htmlspecialchars($job_data['FLATNESS_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo format_dim($job_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo format_wt($job_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo format_wt($job_data['LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT / PIECE</div><div class="info-value"><?php echo format_dim($job_data['WEIGHT_PIECE']); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WIDTH</div><div class="info-value"><?php echo format_wt($job_data['CUT_WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT LENGTH</div><div class="info-value"><?php echo format_wt($job_data['CUT_LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WEIGHT PIECE</div><div class="info-value"><?php echo format_dim($job_data['CUT_WEIGHTPIECE']); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_RELEASEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_RELEASEPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX - MIN TOLERANCE</div><div class="info-value"><?php echo format_wt($job_data['JOB_MAXTOLERANCE']); ?>% / <?php echo format_wt($job_data['JOB_MINTOLERANCE']); ?>%</div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SCHEDULE DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SCHEDULE_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-6 col-sm-12"><div class="info-label">JOB REMARK</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- 2. JOB Order Item List -->
                <div class="dashboard-card">
                    <h4 class="card-title-g4">📦 2. JOB Order Item List 
                        <span style="font-size: 14px; font-weight: normal; color: #64748b;">
                            (Refer : <?php echo htmlspecialchars($job_order); ?>)
                        </span>
                    </h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th>Sale Order No</th>
                                    <th style="text-align: center;">Product ID</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">Temper</th>
                                    <th style="text-align: center;">Grade</th>
                                    <th style="text-align: center;">Surface Grade</th>
                                    <th style="text-align: center;">Metallurgical Grade</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: center;">Length</th>
                                    <th style="text-align: right;">Produce Wt.</th>
                                    <th style="text-align: right;">Produce Piece</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($job_items)): ?>
                                    <tr>
                                        <td colspan="15" align="center" style="color: #64748b; padding: 20px;">
                                            No item records found for this job order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($job_items as $index => $item): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($item['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td style="font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($item['SALEORDER_NO'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['ALLOY'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['TEMPER'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo format_dim($item['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($item['WIDTH']); ?></td>
                                            <td align="center"><?php echo format_wt($item['LENGTH']); ?></td>
                                            <td align="right"><?php echo format_wt($item['CRSH_PRODUCEWEIGHT']); ?></td>
                                            <td align="right"><?php echo format_pcs($item['CRSH_PRODUCEPIECE']); ?></td>
                                            <td align="center" style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($item['CRSH_STATUS'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. History Job Product Process -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ 3. History Job Product Process</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL QTY WT. / PCS.</div><div class="info-value" style="color:#0284c7; font-weight:700;"><?php echo format_wt($crsh_data['totalAct'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalActP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalPro'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalProP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STRETCHER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalStr'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalStrP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT SHEET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalcutsh'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalcutshP'] ?? 0); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">SHEARING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalsher'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalsherP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PUNCH HOLE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalPun'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalPunP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalBatch'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalBatchP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER PALLET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totaltran'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totaltranP'] ?? 0); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">ANNEALING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalAnn'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalAnnP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COMBINE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalCom'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalComP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SORTING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['TotalSort'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['TotalSortP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TAKEOUT QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalTake'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalTakeP'] ?? 0); ?> pcs.</div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING QTY WT. / PCS.</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_wt($crsh_data['totalPack'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalPackP'] ?? 0); ?> pcs.</div></div>
                    </div>
                </div>

                <!-- 4. Batch Annealing Details by Job Order -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">🔥 4. Batch Annealing Details by Job Order</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th>Batch No</th>
                                    <th style="text-align: center;">Item</th>
                                    <th style="text-align: center;">B St.</th>
                                    <th style="text-align: center;">P.ID.</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">I Temp</th>
                                    <th style="text-align: center;">T Temp</th>
                                    <th style="text-align: center;">Thick</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: center;">Length</th>
                                    <th style="text-align: right;">Input Wt.</th>
                                    <th style="text-align: right;">Input Piece</th>
                                    <th style="text-align: center;">Start Date</th>
                                    <th style="text-align: center;">Open Date</th>
                                    <th style="text-align: center;">Operator</th>
                                    <th style="text-align: center;">Operator Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batch_list)): ?>
                                    <tr>
                                        <td colspan="18" align="center" style="color: #64748b; padding: 20px;">
                                            No batch annealing records found for this job order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($batch_list as $index => $btch): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($btch['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td style="font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($btch['BTCH_NO'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['PALLET_ITEM'] ?? '-'); ?></td>
                                            <td align="center" style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($btch['BTCH_STATUS'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['ALLOY'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['TEMPER_INITIAL'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['TEMPER_TARGET'] ?? '-'); ?></td>
                                            <td align="center"><?php echo format_dim($btch['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($btch['WIDTH']); ?></td>
                                            <td align="center"><?php echo format_wt($btch['LENGTH']); ?></td>
                                            <td align="right"><?php echo format_wt($btch['BTCH_INPUTWEIGHT']); ?></td>
                                            <td align="right"><?php echo format_pcs($btch['BTCH_INPUTPIECE']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['START_Date'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPEN_Date'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPERATOR'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPERATOR_UPDATE'] ?? '-'); ?></td>
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
    window.location.assign('mainta_job_so_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>