<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า PRODUCT_NO จาก URL (เช่น split_pallet_product_detail_mats.php?func=pd&PRODUCT_NO=P260920-1-01)
$product_no = isset($_GET['PRODUCT_NO']) ? htmlspecialchars(trim($_GET['PRODUCT_NO']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : (isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : '');
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$main_prod        = null;
$split_list       = [];
$split_crsh_list  = [];

if (!empty($product_no)) {
    // 1. Query ดึงข้อมูล Main Product & History Production (เพิ่ม CRSH_TOPWEIGHT, CRSH_BOTTOMWEIGHT)
    $sql_main = "SELECT 
                    /** Main Product **/
                    PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, PRODUCT_ID,    
                    PRODUCT_MODEL, ORG_LINEPROCESS, LINE_PROCESS, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE,
                    FLATNESS_GRADE, THICKNESS, WIDTH, [LENGTH], WEIGHT_PIECE, CRSH_ACTUALPIECE, CRSH_ACTUALWEIGHT, CRSH_ENDDATE, CRSH_ENDTIME,
                    CRSH_WORKPROCESS, CRSH_NEXTPROCESS,
                    /** History Production **/
                    CRSH_TOPWEIGHT, CRSH_BOTTOMWEIGHT,
                    CRSH_COMBINEWEIGHT, CRSH_COMBINEPIECE, CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE,
                    CRSH_STRETCHERWEIGHT, CRSH_STRETCHERPIECE, CRSH_CUTSHEETWEIGHT, CRSH_CUTSHEETPIECE, CRSH_SHEARWEIGHT, CRSH_SHEARPIECE,
                    CRSH_PUNCHWEIGHT, CRSH_PUNCHPIECE, CRSH_BATCHANNEALWEIGHT, CRSH_BATCHANNEALPIECE, CRSH_TRANSFERWEIGHT, CRSH_TRANSFERPIECE,
                    CRSH_ANNEALWEIGHT, CRSH_ANNEALPIECE, CRSH_SORTWEIGHT, CRSH_SORTPIECE
                 FROM CRSHPROD1 
                 WHERE PRODUCT_NO = :product_no";
            
    $stmt_main = $conn->prepare($sql_main);
    $stmt_main->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_main->execute();
    $main_prod = $stmt_main->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Split Detail จาก SPLTPROD1
    $sql_split = "SELECT s.PRODUCT_NO, s.PRODUCT_REFERENCE, s.PRODUCT_ID, s.SPLT_OPERATEDATE, 
                         s.SPLT_ACCEPTWEIGHT, s.SPLT_ACCEPTPIECE, s.SPLT_OPERATOR1, s.ORG_STATUS 
                  FROM SPLTPROD1 AS s
                  WHERE s.PRODUCT_NO = :product_no OR s.PRODUCT_REFERENCE = :product_no";

    $stmt_split = $conn->prepare($sql_split);
    $stmt_split->bindParam(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_split->execute();
    $split_list = $stmt_split->fetchAll(PDO::FETCH_ASSOC);

    // 3. รวบรวม PRODUCT_REFERENCE และ PRODUCT_NO ที่ได้จาก Split เพื่อนำไปค้นหาประวัติใน CRSHPROD1
    $ref_products = [];
    foreach ($split_list as $sp) {
        if (!empty($sp['PRODUCT_REFERENCE'])) {
            $ref_products[] = $sp['PRODUCT_REFERENCE'];
        }
        if (!empty($sp['PRODUCT_NO'])) {
            $ref_products[] = $sp['PRODUCT_NO'];
        }
    }
    $ref_products = array_unique(array_filter($ref_products));

    if (!empty($ref_products)) {
        // ค้นหาข้อมูล Production ของ Split items ใน CRSHPROD1
        $in_clause = implode(',', array_fill(0, count($ref_products), '?'));
        $sql_crsh_split = "SELECT PRODUCT_NO, PRODUCT_REFERENCE,
                                  CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE,
                                  CRSH_STRETCHERWEIGHT, CRSH_STRETCHERPIECE,
                                  CRSH_CUTSHEETWEIGHT, CRSH_CUTSHEETPIECE,
                                  CRSH_SHEARWEIGHT, CRSH_SHEARPIECE,
                                  CRSH_PUNCHWEIGHT, CRSH_PUNCHPIECE,
                                  CRSH_BATCHANNEALWEIGHT, CRSH_BATCHANNEALPIECE,
                                  CRSH_TRANSFERWEIGHT, CRSH_TRANSFERPIECE,
                                  CRSH_ANNEALWEIGHT, CRSH_ANNEALPIECE,
                                  CRSH_SORTWEIGHT, CRSH_SORTPIECE
                           FROM CRSHPROD1
                           WHERE PRODUCT_NO IN ($in_clause) OR PRODUCT_REFERENCE IN ($in_clause)";

        $stmt_crsh_split = $conn->prepare($sql_crsh_split);
        // Bind Parameter สำหรับสองเงื่อนไข (IN Clause x2)
        $bind_values = array_merge(array_values($ref_products), array_values($ref_products));
        $stmt_crsh_split->execute($bind_values);
        $split_crsh_list = $stmt_crsh_split->fetchAll(PDO::FETCH_ASSOC);
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
        .card-title-g4 { 
            color: #b45309; 
            border-bottom: 2px solid #fef3c7; 
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

        /* สไตล์ปุ่มพิมพ์สีเขียว Print Product Label */
        .btn-print-label {
            background-color: #16a34a !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 12px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .btn-print-label:hover {
            background-color: #15803d !important;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="split_pallet_process_mats.php?func=<?php echo $folder_func ?>">Maintain Product Split Pallet</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Product No Details: <span style="color:#2563eb;"><a href="split_pallet_process_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $product_no ?>"><?php echo htmlspecialchars($product_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_cold()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$main_prod): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Product No: <strong><?php echo htmlspecialchars($product_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- SECTION 1: Product Pallet Details -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">🔖 Main Product Details</h4>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Product No.</div>
                            <div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;">
                                <?php echo htmlspecialchars($main_prod['PRODUCT_NO'] ?? '-'); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Material In</div>
                            <div class="info-value"><?php echo htmlspecialchars($main_prod['MATERIAL_IN'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Coil No.</div>
                            <div class="info-value"><?php echo htmlspecialchars($main_prod['COIL_NO'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Sale Order No / Item</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['SALEORDER_NO'] ?? '-') . ' / ' . ($main_prod['SALEORDER_ITEM'] ?? '-')); ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Job Order</div>
                            <div class="info-value"><?php echo htmlspecialchars($main_prod['JOB_ORDER'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Product ID - Model</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['PRODUCT_ID'] ?? '-') . ' - ' . ($main_prod['PRODUCT_MODEL'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Line Process (Org / Current)</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['ORG_LINEPROCESS'] ?? '-') . ' / ' . ($main_prod['LINE_PROCESS'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Alloy - Temper</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['ALLOY'] ?? '-') . ' - ' . ($main_prod['TEMPER'] ?? '-')); ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Grade - SG - MG</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['GRADE'] ?? '-') . ' / ' . ($main_prod['SURFACE_GRADE'] ?? '-') . ' / ' . ($main_prod['METALLURGICAL_GRADE'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">T5 Temp - Flatness G</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['T5_TEMPERATURE'] ?? '-') . ' / ' . ($main_prod['FLATNESS_GRADE'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Thickness - Width - Length</div>
                            <div class="info-value">
                                <?php echo fmt3($main_prod['THICKNESS']) . ' x ' . fmt3($main_prod['WIDTH']) . ' x ' . fmt3($main_prod['LENGTH']); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Weight per Piece</div>
                            <div class="info-value"><?php echo fmt4($main_prod['WEIGHT_PIECE']); ?></div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Org. Actual (Weight - Pc.)</div>
                            <div class="info-value" style="font-weight:700; color:#0284c7;">
                                <?php echo fmt2($main_prod['CRSH_ACTUALWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_ACTUALPIECE'] ?? '0') . ' Pcs'; ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Product Date - Time</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['CRSH_ENDDATE'] ?? '-') . ' ' . ($main_prod['CRSH_ENDTIME'] ?? '')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Work Process / Next Process</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars(($main_prod['CRSH_WORKPROCESS'] ?? '-') . ' ➔ ' . ($main_prod['CRSH_NEXTPROCESS'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Product Ref.</div>
                            <div class="info-value"><?php echo htmlspecialchars($main_prod['PRODUCT_REFERENCE'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: History Production (เพิ่ม Top Weight & Bottom Weight) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📜 Main Product History Production</h4>

                    <div class="row">
                        <!-- รายละเอียดที่เพิ่มตามโจทย์: Top Weight & Bottom Weight -->
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Top Weight</div>
                            <div class="info-value" style="font-weight:700; color:#0f172a; background-color:#f1f5f9;"><?php echo fmt2($main_prod['CRSH_TOPWEIGHT'] ?? 0) . ' kg'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Bottom Weight</div>
                            <div class="info-value" style="font-weight:700; color:#0f172a; background-color:#f1f5f9;"><?php echo fmt2($main_prod['CRSH_BOTTOMWEIGHT'] ?? 0) . ' kg'; ?></div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Combine (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_COMBINEWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_COMBINEPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Produce (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_PRODUCEWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_PRODUCEPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Stretcher (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_STRETCHERWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_STRETCHERPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Cut Sheet (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_CUTSHEETWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_CUTSHEETPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Shear (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_SHEARWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_SHEARPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Punch Hole (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_PUNCHWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_PUNCHPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Batch Anneal (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_BATCHANNEALWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_BATCHANNEALPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Transfer (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_TRANSFERWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_TRANSFERPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Anneal (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_ANNEALWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_ANNEALPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Sort (Wt. - Pc.)</div>
                            <div class="info-value"><?php echo fmt2($main_prod['CRSH_SORTWEIGHT']) . ' kg / ' . htmlspecialchars($main_prod['CRSH_SORTPIECE'] ?? 0) . ' Pcs'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Split Product Production -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">🔀 Split Product Production </h4>

                    <?php if (empty($split_crsh_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">No Split Production history found in CRSHPROD1.</div>
                    <?php else: ?>
                        <div class="pack-table-container">
                            <table class="pack-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product No</th>
                                        <th>Product Ref</th>
                                        <th style="text-align:right;">Produce (Wt/Pc)</th>
                                        <th style="text-align:right;">Stretcher (Wt/Pc)</th>
                                        <th style="text-align:right;">Cut Sheet (Wt/Pc)</th>
                                        <th style="text-align:right;">Shear (Wt/Pc)</th>
                                        <th style="text-align:right;">Punch Hole (Wt/Pc)</th>
                                        <th style="text-align:right;">Batch Anneal (Wt/Pc)</th>
                                        <th style="text-align:right;">Transfer (Wt/Pc)</th>
                                        <th style="text-align:right;">Anneal (Wt/Pc)</th>
                                        <th style="text-align:right;">Sort (Wt/Pc)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($split_crsh_list as $idx => $s): ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td style="font-weight:700; color:#15803d;">
                                                <a href="split_pallet_product_detail_mats.php?func=<?php echo $folder_func; ?>&PRODUCT_NO=<?php echo urlencode($s['PRODUCT_NO']); ?>">
                                                    <?php echo htmlspecialchars($s['PRODUCT_NO'] ?? '-'); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($s['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_PRODUCEWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_PRODUCEPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_STRETCHERWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_STRETCHERPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_CUTSHEETWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_CUTSHEETPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_SHEARWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_SHEARPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_PUNCHWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_PUNCHPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_BATCHANNEALWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_BATCHANNEALPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_TRANSFERWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_TRANSFERPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_ANNEALWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_ANNEALPIECE'] ?? 0); ?></td>
                                            <td style="text-align:right;"><?php echo fmt2($s['CRSH_SORTWEIGHT'] ?? 0) . ' / ' . ($s['CRSH_SORTPIECE'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECTION 4: Split Detail Log (เพิ่มปุ่ม Print Product Label สีเขียว) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g4">📄 Split Detail Log </h4>

                    <?php if (empty($split_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">No Split Detail log found for this Product No.</div>
                    <?php else: ?>
                        <div class="pack-table-container">
                            <table class="pack-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product No</th>
                                        <th>Product Reference</th>
                                        <th>Product ID</th>
                                        <th>Operate Date</th>
                                        <th style="text-align:right;">Accept Weight</th>
                                        <th style="text-align:right;">Accept Piece</th>
                                        <th>Operator 1</th>
                                        <th>Org Status</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $job_order_val = $main_prod['JOB_ORDER'] ?? '';
                                        foreach ($split_list as $idx => $sp): 
                                            $sp_prod_no = !empty($sp['PRODUCT_NO']) ? $sp['PRODUCT_NO'] : ($sp['PRODUCT_REFERENCE'] ?? '');
                                    ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td style="font-weight:700; color:#b45309;">
                                                <a href="split_pallet_product_detail_mats.php?func=<?php echo $folder_func; ?>&PRODUCT_NO=<?php echo urlencode($sp['PRODUCT_NO']); ?>">
                                                    <?php echo htmlspecialchars($sp['PRODUCT_NO'] ?? '-'); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($sp['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($sp['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($sp['SPLT_OPERATEDATE'] ?? '-'); ?></td>
                                            <td style="text-align:right; font-weight:600;"><?php echo fmt2($sp['SPLT_ACCEPTWEIGHT'] ?? 0); ?></td>
                                            <td style="text-align:right; font-weight:600;"><?php echo htmlspecialchars($sp['SPLT_ACCEPTPIECE'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($sp['SPLT_OPERATOR1'] ?? '-'); ?></td>
                                            <td><span class="label label-default"><?php echo htmlspecialchars($sp['ORG_STATUS'] ?? '-'); ?></span></td>
                                            <!-- เพิ่มปุ่มสีเขียว Print Product Label -->
                                            <td align="center">
                                                <a href="print_circle_sheet_product_label_mats.php?productno=<?php echo urlencode($sp_prod_no); ?>&joborder=<?php echo urlencode($job_order_val); ?>" 
                                                   target="_blank" 
                                                   class="btn-print-label">
                                                    🖨️ Print Product Label
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
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
    window.location.assign('split_pallet_process_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>