<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า PRODUCT_NO จาก URL (รองรับทั้ง PRODUCT_NO และ search_no)
$product_no = isset($_GET['PRODUCT_NO']) ? htmlspecialchars(trim($_GET['PRODUCT_NO']), ENT_QUOTES, 'UTF-8') : (isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$prod_data   = null;
$crsh_data   = null;
$coil_data   = null;
$btch_status = '-';
$defect_list = [];

if (!empty($product_no)) {
    // 1. Query ดึงข้อมูล Product Information จาก CRSHPROD1
    $sql_prod = "SELECT 
                    PRODUCT_NO, PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, CRSH_STATUS, PRODUCT_ID, PRODUCT_MODEL, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
                    T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, MATERIAL_CODE, CRSH_STACKNUMBER, CRSH_STACKHIGH, CRSH_STARTDATE, CRSH_ENDDATE,
                    CRSH_WORKPROCESS, CRSH_NEXTPROCESS, PAPER_INTERLEAVE, XYB_SPEED, PC_MINUTE, PHSC_REMARK, CRSH_OPERATOR1, CRSH_OPERATEDATE,
                    CRSH_PRODUCEPIECE, COIL_NO
                 FROM CRSHPROD1 
                 WHERE PRODUCT_NO = :product_no";
            
    $stmt_prod = $conn->prepare($sql_prod);
    $stmt_prod->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_prod->execute();
    $prod_data = $stmt_prod->fetch(PDO::FETCH_ASSOC);

    if ($prod_data) {
        // ดึง Batch Status จาก BTCHPROD0 ตาม PRODUCT_NO และ JOB_ORDER
        if (!empty($prod_data['JOB_ORDER'])) {
            $sql_btch = "SELECT BTCH_STATUS 
                         FROM BTCHPROD0 
                         WHERE PRODUCT_NO = :product_no AND JOB_ORDER = :job_order";
            $stmt_btch = $conn->prepare($sql_btch);
            $stmt_btch->bindParam(':product_no', $product_no, PDO::PARAM_STR);
            $stmt_btch->bindParam(':job_order', $prod_data['JOB_ORDER'], PDO::PARAM_STR);
            $stmt_btch->execute();
            $btch_row = $stmt_btch->fetch(PDO::FETCH_ASSOC);
            if ($btch_row && isset($btch_row['BTCH_STATUS'])) {
                $btch_status = $btch_row['BTCH_STATUS'];
            }
        }

        // 2. Query ดึงข้อมูล Coil Information จาก COILPROD1 และเชื่อม Remark จาก COILINSP1, CRSHINSP1
        if (!empty($prod_data['COIL_NO'])) {
            $sql_coil = "SELECT c.COIL_NO, c.PRODUCT_REFERENCE, c.ALLOY, c.TEMPER, c.GRADE, c.SURFACE_GRADE, c.METALLURGICAL_GRADE,
                                c.T5_TEMPERATURE, c.THICKNESS, c.WIDTH, c.COIL_ACTUALWEIGHT, c.COIL_PRODUCEWEIGHT, c.COIL_SCRAPWEIGHT,
                                c.COIL_SBPRODUCEWEIGHT, c.COIL_SBSCRAPWEIGHT, c.COIL_SBADJUSTWEIGHT,
                                c.COIL_ADJUSTWEIGHT, c.COIL_BALANCEWEIGHT,
                                i.OIN1_REMARK AS COIL_INSP_REMARK,
                                p.CIN1_REMARK AS PROD_INSP_REMARK
                         FROM COILPROD1 c
                         LEFT JOIN COILINSP1 i ON c.COIL_NO = i.COIL_NO
                         LEFT JOIN CRSHINSP1 p ON c.COIL_NO = p.COIL_NO
                         WHERE c.COIL_NO = :coil_no";

            $stmt_coil = $conn->prepare($sql_coil);
            $stmt_coil->bindParam(':coil_no', $prod_data['COIL_NO'], PDO::PARAM_STR);
            $stmt_coil->execute();
            $coil_data = $stmt_coil->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 3. Query ดึงข้อมูล History Product Process จาก [MATS-NEW].dbo.CRSHPROD1 (Total Pallet)
    $sql_crsh = "SELECT 
                    sum(CRSH_ACTUALWEIGHT) as totalAct, sum(CRSH_ACTUALPIECE) as totalActP,
                    sum(CRSH_PRODUCEWEIGHT) as totalPro, sum(CRSH_PRODUCEPIECE) as totalProP,
                    sum(CRSH_STRETCHERWEIGHT) as totalStr, sum(CRSH_STRETCHERPIECE) as totalStrP,
                    sum(CRSH_CUTSHEETWEIGHT) as totalcutsh, sum(CRSH_CUTSHEETPIECE) as totalPunP,
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
                WHERE PRODUCT_NO = :product_no";

    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_crsh->execute();
    $crsh_data = $stmt_crsh->fetch(PDO::FETCH_ASSOC);

    // 4. Query ดึงข้อมูล Product Defect จาก CRSHINSP4
    $sql_defect = "SELECT PRODUCT_NO, DEFECT_ID, DEFECT_QTY 
                   FROM CRSHINSP4 
                   WHERE PRODUCT_NO = :product_no";

    $stmt_defect = $conn->prepare($sql_defect);
    $stmt_defect->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_defect->execute();
    $defect_list = $stmt_defect->fetchAll(PDO::FETCH_ASSOC);
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
            color: #b91c1c; 
            border-bottom: 2px solid #fca5a5; 
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
                    📋 Product Details: <span style="color:#2563eb;"><a href="mainta_job_so_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $product_no ?>"><?php echo htmlspecialchars($product_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$prod_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Product No: <strong><?php echo htmlspecialchars($product_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- 1. Product Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 1. Product Information</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($prod_data['PRODUCT_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF.</div><div class="info-value"><?php echo htmlspecialchars($prod_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER NO.</div><div class="info-value"><?php echo htmlspecialchars($prod_data['SALEORDER_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER ITEM</div><div class="info-value"><?php echo htmlspecialchars($prod_data['SALEORDER_ITEM'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value"><?php echo htmlspecialchars($prod_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN</div><div class="info-value"><?php echo htmlspecialchars($prod_data['MATERIAL_IN'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT ID</div><div class="info-value"><?php echo htmlspecialchars($prod_data['PRODUCT_ID'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT MODEL</div><div class="info-value"><?php echo htmlspecialchars($prod_data['PRODUCT_MODEL'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($prod_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($prod_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['SURFACE_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo format_dim($prod_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo format_wt($prod_data['WIDTH']); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo format_wt($prod_data['LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT / PIECE</div><div class="info-value"><?php echo format_dim($prod_data['WEIGHT_PIECE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WIDTH</div><div class="info-value"><?php echo format_wt($prod_data['CUT_WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT LENGTH</div><div class="info-value"><?php echo format_wt($prod_data['CUT_LENGTH']); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WEIGHT PIECE</div><div class="info-value"><?php echo format_dim($prod_data['CUT_WEIGHTPIECE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STACK NUMBER</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_STACKNUMBER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STACK HIGH</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_STACKHIGH'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">START DATE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_STARTDATE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">END DATE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_ENDDATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_NEXTPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PAPER INTERLEAVE</div><div class="info-value"><?php echo htmlspecialchars($prod_data['PAPER_INTERLEAVE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">XYB SPEED</div><div class="info-value"><?php echo format_wt($prod_data['XYB_SPEED']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PC / MINUTE</div><div class="info-value"><?php echo format_wt($prod_data['PC_MINUTE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">OPERATOR1</div><div class="info-value"><?php echo htmlspecialchars($prod_data['CRSH_OPERATOR1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($prod_data['CRSH_STATUS'] ?? '-'); ?></div></div>

                        <div class="col-md-12 col-sm-12"><div class="info-label">REMARK</div><div class="info-value"><?php echo htmlspecialchars($prod_data['PHSC_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- 2. History Product Process -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ 2. History Product Process</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL QTY WT. / PCS.</div><div class="info-value" style="color:#0284c7; font-weight:700;"><?php echo format_wt($crsh_data['totalAct'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalActP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalPro'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalProP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STRETCHER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalStr'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalStrP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT SHEET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalcutsh'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalPunP'] ?? 0); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">SHEARING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalsher'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalsherP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PUNCH HOLE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalPun'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalPunP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalBatch'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalBatchP'] ?? 0); ?> pcs.</div></div>
                        
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">BATCH STATUS</div>
                            <div class="info-value" style="color:#0284c7; font-weight:700; background-color:#f0f9ff; border-color:#bae6fd;">
                                <?php echo htmlspecialchars($btch_status !== '' ? $btch_status : '-'); ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER PALLET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totaltran'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totaltranP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ANNEALING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalAnn'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalAnnP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COMBINE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalCom'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalComP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SORTING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['TotalSort'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['TotalSortP'] ?? 0); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TAKEOUT QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($crsh_data['totalTake'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalTakeP'] ?? 0); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING QTY WT. / PCS.</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_wt($crsh_data['totalPack'] ?? 0); ?> kg. / <?php echo format_pcs($crsh_data['totalPackP'] ?? 0); ?> pcs.</div></div>
                    </div>
                </div>

                <!-- 3. Coil Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">🌀 3. Coil Information</h4>
                    <?php if (!$coil_data): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">ไม่พบข้อมูล Coil อ้างอิงสำหรับ Product นี้</div>
                    <?php else: ?>
                        <div class="row" style="display: flex; flex-wrap: wrap;">
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">COIL NO.</div>
                                <div class="info-value" style="color:#0284c7; font-weight:700; background-color:#f0f9ff; border-color:#bae6fd;"><?php echo htmlspecialchars($coil_data['COIL_NO'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">PRODUCT REF.</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['PRODUCT_REFERENCE'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">ALLOY</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['ALLOY'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">TEMPER</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['TEMPER'] ?? '-'); ?></div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">GRADE</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['GRADE'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">SURFACE GRADE</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['SURFACE_GRADE'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">METALLURGICAL GRADE</div>
                                <div class="info-value"><?php echo htmlspecialchars($coil_data['METALLURGICAL_GRADE'] ?? '-'); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">T5 TEMPERATURE</div>
                                <div class="info-value"><?php echo htmlspecialchars(!empty($coil_data['T5_TEMPERATURE']) ? $coil_data['T5_TEMPERATURE'] : '-'); ?></div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">THICKNESS</div>
                                <div class="info-value"><?php echo format_dim($coil_data['THICKNESS']); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">WIDTH</div>
                                <div class="info-value"><?php echo format_wt($coil_data['WIDTH']); ?></div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">ACTUAL WEIGHT</div>
                                <div class="info-value"><?php echo format_wt($coil_data['COIL_ACTUALWEIGHT']); ?> kg.</div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">PRODUCE WEIGHT</div>
                                <div class="info-value"><?php echo format_wt($coil_data['COIL_PRODUCEWEIGHT']); ?> kg.</div>
                            </div>

                            <div class="col-md-4 col-sm-4">
                                <div class="info-label">SCRAP WEIGHT</div>
                                <div class="info-value" style="color:#b91c1c; font-weight:700; background-color:#fef2f2; border-color:#fecaca;"><?php echo format_wt($coil_data['COIL_SCRAPWEIGHT']); ?> kg.</div>
                            </div>
                            <div class="col-md-4 col-sm-4">
                                <div class="info-label">ADJUST WEIGHT</div>
                                <div class="info-value"><?php echo format_wt($coil_data['COIL_ADJUSTWEIGHT']); ?> kg.</div>
                            </div>
                            <div class="col-md-4 col-sm-4">
                                <div class="info-label">BALANCE WEIGHT</div>
                                <div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo format_wt($coil_data['COIL_BALANCEWEIGHT']); ?> kg.</div>
                            </div>

                            <div class="col-md-12 col-sm-12" style="width: 100%; margin-top: 5px;">
                                <div class="info-label">COIL INSPECT REMARK</div>
                                <div class="info-value" style="background-color:#eff6ff; border-color:#bfdbfe; color:#1e40af; min-height:48px;">
                                    <?php echo htmlspecialchars(!empty($coil_data['COIL_INSP_REMARK']) ? $coil_data['COIL_INSP_REMARK'] : '-'); ?>
                                </div>
                            </div>

                            <div class="col-md-12 col-sm-12" style="width: 100%; margin-top: 5px;">
                                <div class="info-label">PRODUCT INSPECT REMARK</div>
                                <div class="info-value" style="background-color:#f0fdf4; border-color:#bbf7d0; color:#166534; min-height:48px;">
                                    <?php echo htmlspecialchars(!empty($coil_data['PROD_INSP_REMARK']) ? $coil_data['PROD_INSP_REMARK'] : '-'); ?>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Product Defect -->
                <div class="dashboard-card">
                    <h4 class="card-title-g4">⚠️ 4. Product Defect Details</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th style="text-align: center;">Defect ID</th>
                                    <th style="text-align: right;">Defect Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($defect_list)): ?>
                                    <tr>
                                        <td colspan="4" align="center" style="color: #64748b; padding: 20px;">
                                            No defect records found for this product.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($defect_list as $index => $df): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($df['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td align="center" style="font-weight: 700; color: #b91c1c;"><?php echo htmlspecialchars($df['DEFECT_ID'] ?? '-'); ?></td>
                                            <td align="right" style="font-weight: 700; color: #dc2626;"><?php echo format_pcs($df['DEFECT_QTY']); ?></td>
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