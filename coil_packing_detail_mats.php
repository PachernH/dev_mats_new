<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data  = null;
$so_data   = null;
$pack_list = [];

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล Group 1: JOB Detail จาก JOBORDER1
    $sql_job = "SELECT JOB_ORDER, JOBORDER_DATE, ALLOY, SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2,
                       GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, THICKNESS, WIDTH, JOB_UOMDIMENSION, WEIGHT_PIECE, 
                       T5_TEMPERATURE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_MAXTOLERANCE,
                       JOB_WORKPROCESS, USE_FORPROCESS, JOB_RELEASEWEIGHT, JOB_UOMWEIGHT, JOB_RELEASEPIECE, 
                       JOB_PACKWEIGHT, JOB_PACKPIECE, JOB_STOCKWEIGHT, JOB_STOCKPIECE,
                       JOB_REMARK, JOB_STATUS, JOB_OPERATOR, SALEORDER_NO, SALEORDER_ITEM
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order
                ORDER BY JOBORDER_DATE DESC";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Sale Order จาก CSTMORDR2 โดยเชื่อมกับ JOBORDER1
    if ($job_data && !empty($job_data['SALEORDER_NO']) && !empty($job_data['SALEORDER_ITEM'])) {
        $sql_so = "SELECT CTM2_PONO, CTM2_POITEM, CSTMSPPL_ID, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE,
                          THICKNESS, WIDTH, WEIGHT_PIECE, CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE, CTM2_ORDERWEIGHT, CTM2_ORDERPIECE,
                          CTM2_REJECTWEIGHT, CTM2_REJECTPIECE, CTM2_REPLACEWEIGHT, CTM2_REPLACEPIECE, CTM2_PACKWEIGHT, CTM2_PACKPIECE,
                          CTM2_STOCKWEIGHT, CTM2_STOCKPIECE, CTM2_REMARK
                   FROM CSTMORDR2
                   WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";

        $stmt_so = $conn->prepare($sql_so);
        $stmt_so->bindParam(':so_no', $job_data['SALEORDER_NO'], PDO::PARAM_STR);
        $stmt_so->bindParam(':so_item', $job_data['SALEORDER_ITEM'], PDO::PARAM_STR);
        $stmt_so->execute();
        $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);
    }

    // 3. Query ดึงข้อมูล Packing Coil จาก PACKPROD1 โดยใช้ JOB_ORDER
    $sql_pack = "SELECT PRODUCT_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, PACK_DATE, PACK_PACKAGEWEIGHT, PACK_NETWEIGHT 
                 FROM PACKPROD1 
                 WHERE JOB_ORDER = :job_order
                 ORDER BY PACK_DATE DESC";

    $stmt_pack = $conn->prepare($sql_pack);
    $stmt_pack->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_pack->execute();
    $pack_list = $stmt_pack->fetchAll(PDO::FETCH_ASSOC);
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
            color: #15803d; 
            border-bottom: 2px solid #bbf7d0; 
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

        /* ตารางสไตล์ Clean สำหรับ Packing List */
        .pack-table-container {
            overflow-x: auto;
        }
        .pack-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background-color: #ffffff;
        }
        .pack-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }
        .pack-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .pack-table tbody tr:hover {
            background-color: #f1f5f9;
        }
        .pack-table tfoot td {
            font-weight: 700;
            background-color: #f8fafc;
            border-top: 2px solid #cbd5e1;
            padding: 12px;
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

                    <!-- 1. General & Primary Info -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">OPERATOR</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_OPERATOR'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 2. Product & Material Specifications -->
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

                    <!-- 3. Process & Customer Tolerances -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['USE_FORPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MIN TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MINTOLERANCE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MAX TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MAXTOLERANCE']); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 4. Weight & Quantities -->
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

                    <!-- 5. Remarks -->
                    <div class="row">
                        <div class="col-md-12"><div class="info-label">JOB REMARK</div><div class="info-value" style="min-height:48px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: Sale Order Details (ดึงจาก CSTMORDR2) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📦 Sale Order Details</h4>

                    <?php if (!$so_data): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">
                            ไม่พบข้อมูล Sale Order สำหรับ Job Order นี้ (SO No: <?php echo htmlspecialchars($job_data['SALEORDER_NO'] ?? '-'); ?>, SO Item: <?php echo htmlspecialchars($job_data['SALEORDER_ITEM'] ?? '-'); ?>)
                        </div>
                    <?php else: ?>
                        <?php
                            // การคำนวณค่าต่าง ๆ ตามเงื่อนไข
                            $CTM2_OrderWeight   = (float)($so_data['CTM2_ORDERWEIGHT'] ?? 0);
                            $CTM2_ReplaceWeight = (float)($so_data['CTM2_REPLACEWEIGHT'] ?? 0);
                            $CTM2_OrderPiece    = (float)($so_data['CTM2_ORDERPIECE'] ?? 0);
                            $CTM2_ReplacePiece  = (float)($so_data['CTM2_REPLACEPIECE'] ?? 0);
                            
                            $CTM2_MAXTolerance  = (float)($so_data['CTM2_MAXTOLERANCE'] ?? 0);
                            $CTM2_MINTolerance  = (float)($so_data['CTM2_MINTOLERANCE'] ?? 0);

                            // Total Order Qty & Total Piece
                            $CTM2TotalWeight    = $CTM2_OrderWeight + $CTM2_ReplaceWeight;
                            $CTM2TotalPiece     = $CTM2_OrderPiece + $CTM2_ReplacePiece;

                            // Maximum Qty & Piece
                            $CTM2MaxWeightCalc  = $CTM2TotalWeight + (($CTM2TotalWeight * $CTM2_MAXTolerance) / 100);
                            $CTM2MaxWeight      = number_format($CTM2MaxWeightCalc, 0, '.', '');
                            $CTM2MaxPiece       = $CTM2TotalPiece;

                            // Minimum Qty & Piece
                            $CTM2MinWeightCalc  = $CTM2TotalWeight - (($CTM2TotalWeight * $CTM2_MINTolerance) / 100);
                            $CTM2MinWeight      = number_format($CTM2MinWeightCalc, 0, '.', '');
                            $CTM2MinPiece       = $CTM2TotalPiece;

                            // Total Formatted
                            $CTM2TotalWeightFmt = number_format($CTM2TotalWeight, 0, '.', '');
                            $CTM2TotalPieceFmt  = $CTM2TotalPiece;
                        ?>

                        <!-- Order Specification -->
                        <div class="row">
                            <div class="col-md-3 col-sm-6"><div class="info-label">Purchase Order</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_PONO'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Item</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_POITEM'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Customer ID</div><div class="info-value"><?php echo htmlspecialchars($so_data['CSTMSPPL_ID'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Alloy</div><div class="info-value"><?php echo htmlspecialchars($so_data['ALLOY'] ?? '-'); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">Temper</div><div class="info-value"><?php echo htmlspecialchars($so_data['TEMPER'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">SG</div><div class="info-value"><?php echo htmlspecialchars($so_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">MG</div><div class="info-value"><?php echo htmlspecialchars($so_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Thickness</div><div class="info-value"><?php echo fmt3($so_data['THICKNESS']); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">Width</div><div class="info-value"><?php echo fmt3($so_data['WIDTH']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Weight per piece</div><div class="info-value"><?php echo fmt2($so_data['WEIGHT_PIECE']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Max Tolerance</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_MAXTOLERANCE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Min Tolerance</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_MINTOLERANCE'] ?? '-'); ?></div></div>
                        </div>

                        <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                        <!-- Order Quantities -->
                        <div class="row">
                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Qty</div><div class="info-value"><?php echo fmt2($so_data['CTM2_ORDERWEIGHT']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Piece</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_ORDERPIECE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Reject Qty</div><div class="info-value"><?php echo fmt2($so_data['CTM2_REJECTWEIGHT']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Reject Piece</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_REJECTPIECE'] ?? '-'); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Replace Qty</div><div class="info-value"><?php echo fmt2($so_data['CTM2_REPLACEWEIGHT']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Order Replace Piece</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_REPLACEPIECE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Total Order Qty</div><div class="info-value" style="font-weight:700; background-color:#eff6ff; border-color:#bfdbfe; color:#1d4ed8;"><?php echo htmlspecialchars($CTM2TotalWeightFmt); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Total Piece</div><div class="info-value" style="font-weight:700; background-color:#eff6ff; border-color:#bfdbfe; color:#1d4ed8;"><?php echo htmlspecialchars($CTM2TotalPieceFmt); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">Maximum Qty</div><div class="info-value"><?php echo htmlspecialchars($CTM2MaxWeight); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Maximun Piece</div><div class="info-value"><?php echo htmlspecialchars($CTM2MaxPiece); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Minimum Qty</div><div class="info-value"><?php echo htmlspecialchars($CTM2MinWeight); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Minimum Piece</div><div class="info-value"><?php echo htmlspecialchars($CTM2MinPiece); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">Packing Qty</div><div class="info-value"><?php echo fmt2($so_data['CTM2_PACKWEIGHT']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Packing Piece</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_PACKPIECE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Stock Qty</div><div class="info-value"><?php echo fmt2($so_data['CTM2_STOCKWEIGHT']); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">Stock Piece</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_STOCKPIECE'] ?? '-'); ?></div></div>
                        </div>

                        <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                        <!-- Remark -->
                        <div class="row">
                            <div class="col-md-12"><div class="info-label">Remark</div><div class="info-value" style="min-height:48px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($so_data['CTM2_REMARK'] ?? '-'); ?></div></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- GROUP 3: Packing Coil Details (ดึงจาก PACKPROD1) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">📦 Packing Coil Details (Job Order: <?php echo htmlspecialchars($job_order); ?>)</h4>

                    <?php if (empty($pack_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">No Packing Coil item information found for this Job Order.</div>
                    <?php else: ?>
                        <div class="pack-table-container">
                            <table class="pack-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product No</th>
                                        <th>Sale Order No</th>
                                        <th>Sale Order Item</th>
                                        <th>Job Order</th>
                                        <th>Pack Date</th>
                                        <th style="text-align:right;">Package Weight</th>
                                        <th style="text-align:right;">Net Weight</th>
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
                                            <td><?php echo $idx + 1; ?></td>
                                            <td style="font-weight:700; color:#15803d;"><?php echo htmlspecialchars($pack['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['SALEORDER_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['SALEORDER_ITEM'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['JOB_ORDER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($pack['PACK_DATE'] ?? '-'); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($pack['PACK_PACKAGEWEIGHT']); ?></td>
                                            <td style="text-align:right; font-weight:600; color:#0f172a;"><?php echo fmt2($pack['PACK_NETWEIGHT']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" style="text-align:right; font-weight:700;">Total (<?php echo count($pack_list); ?> items):</td>
                                        <td style="text-align:right; color:#0f172a; font-weight:700;"><?php echo fmt2($sum_pkg_weight); ?></td>
                                        <td style="text-align:right; color:#15803d; font-weight:700;"><?php echo fmt2($sum_net_weight); ?></td>
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
function back_home_cold(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_packing_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>