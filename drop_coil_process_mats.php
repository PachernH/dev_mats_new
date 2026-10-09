<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// AJAX Request สำหรับดึงค่า AREA_SIZE ของ PRODUCT_ID + PRODUCT_MODEL
if (isset($_GET['action']) && $_GET['action'] === 'get_area_size') {
    header('Content-Type: application/json');
    $p_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
    $p_model = isset($_GET['product_model']) ? trim($_GET['product_model']) : '';
    $area_size = 0;
    
    if (!empty($p_id) && !empty($p_model)) {
        $sql_area = "SELECT AREA_SIZE FROM PRODMSTR1 WHERE PRODUCT_ID = :pid AND PRODUCT_MODEL = :pmodel";
        $stmt_area = $conn->prepare($sql_area);
        $stmt_area->bindParam(':pid', $p_id, PDO::PARAM_STR);
        $stmt_area->bindParam(':pmodel', $p_model, PDO::PARAM_STR);
        $stmt_area->execute();
        if ($res = $stmt_area->fetch(PDO::FETCH_ASSOC)) {
            $area_size = floatval($res['AREA_SIZE'] ?? 0);
        }
    }
    echo json_encode(['area_size' => $area_size]);
    exit;
}

// Query ดึงข้อมูล Coil Product Details
$row_data = null;
if (!empty($coil_no)) {
    $sql = "SELECT 
            p.COIL_NO, p.PRIMARY_SMELT, p.SECONDARY_SMELT, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, 
            p.ALLOY, p.F_TEMPER, p.TEMPER, p.GRADE, p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
            p.COIL_STARTTIME, p.COIL_ENDTIME, p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
            p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_COLDMILLWEIGHT, p.COIL_BATCHANNEALWEIGHT, p.COIL_PACKWEIGHT, p.ACTUAL_WIDTH, 
            p.COIL_PRODUCEWEIGHT, p.COIL_SCRAPWEIGHT, p.COIL_ADJUSTWEIGHT, p.COIL_BALANCEWEIGHT, p.COIL_COMBINEWEIGHT, p.COIL_OPERATOR1,
            p.COIL_REMARK, p.LINE_PROCESS, p.RECIPE_NO, p.RECIPE_ITEM, p.COIL_TOPWEIGHT, p.COIL_BOTTOMWEIGHT, 
            i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ดึงรายการ PRODUCT_ID และ PRODUCT_MODEL จาก MATS.dbo.PRODMSTR1
$product_master_list = [];
$sql_prod = "SELECT PRODUCT_ID, PRODUCT_MODEL FROM PRODMSTR1 ORDER BY PRODUCT_ID ASC";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->execute();
$product_master_list = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// กำหนด ค่าความหนาจาก COILPROD1
$coil_thickness = floatval($row_data['THICKNESS'] ?? 0);
$coil_actual_weight = floatval($row_data['COIL_ACTUALWEIGHT'] ?? 0);
$coil_produce_weight = floatval($row_data['COIL_PRODUCEWEIGHT'] ?? 0);
$coil_scrap_weight = floatval($row_data['COIL_SCRAPWEIGHT'] ?? 0);
$coil_adjust_weight = floatval($row_data['COIL_ADJUSTWEIGHT'] ?? 0);
$coil_bottom_weight = floatval($row_data['COIL_BOTTOMWEIGHT'] ?? 0);

$current_now_datetime = date('Y-m-d\TH:i');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Drop Coil Process</title>
    <?php include 'include/header.php';?>

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        /* Group Headers */
        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .card-title-drop { 
            color: #0d9488; 
            border-bottom: 2px solid #99f6e4; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        /* Label and Value Typography */
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

        /* Form Controls */
        .form-control-custom {
            width: 100%;
            height: 40px;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            margin-bottom: 18px;
        }
        .form-control-custom:focus {
            border-color: #2563eb;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .form-control-custom[readonly], .form-control-custom[disabled] {
            background-color: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        /* Buttons */
        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            padding: 8px 20px;
            height: 42px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }

        .btn-save-drop {
            background-color: #0d9488;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 28px;
            height: 46px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(13, 148, 136, 0.2);
        }
        .btn-save-drop:hover {
            background-color: #0f766e;
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(13, 148, 136, 0.3);
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="drop_product_mats.php?func=<?php echo $folder_func ?>">Drop Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Drop Coil Process: <span style="color:#2563eb;"><a href="drop_product_mats.php?func=<?php echo $folder_func ?>&search_no=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
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
                        <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">Start Date</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_STARTTIME'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">End Date</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_ENDTIME'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_WIDTH']) : number_format((float)($row_data['F_WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['ACTUAL_WIDTH']) : number_format((float)($row_data['ACTUAL_WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_THICKNESS']) : number_format((float)($row_data['F_THICKNESS'] ?? 0), 3); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">RECIPE NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['RECIPE_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RECIPE ITEM</div><div class="info-value"><?php echo htmlspecialchars($row_data['RECIPE_ITEM'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TOP WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_TOPWEIGHT']) : number_format((float)($row_data['COIL_TOPWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BOTTOM WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BOTTOMWEIGHT']) : number_format((float)($row_data['COIL_BOTTOMWEIGHT'] ?? 0), 2); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL CAST WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_CASTWEIGHT']) : number_format((float)($row_data['COIL_CASTWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUAL WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_COLDMILLWEIGHT']) : number_format((float)($row_data['COIL_COLDMILLWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEALING WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BATCHANNEALWEIGHT']) : number_format((float)($row_data['COIL_BATCHANNEALWEIGHT'] ?? 0), 2); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_PACKWEIGHT']) : number_format((float)($row_data['COIL_PACKWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_PRODUCEWEIGHT']) : number_format((float)($row_data['COIL_PRODUCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SCRAP WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_SCRAPWEIGHT']) : number_format((float)($row_data['COIL_SCRAPWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ADJUST WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ADJUSTWEIGHT']) : number_format((float)($row_data['COIL_ADJUSTWEIGHT'] ?? 0), 2); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">BALANCE WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USER OPERATOR</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_OPERATOR1'] ?? '-'); ?></div></div>
                        <div class="col-md-6 col-sm-12"><div class="info-label">PRODUCT STATUS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_STATUS'] ?? '-'); ?></div></div>

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">COIL REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM: Drop Process Entry Form -->
                <form id="frmDropCoil" name="frmDropCoil" method="POST" action="model/drop_coil_save_mats.php">
                    <input type="hidden" id="coil_no" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" />
                    <input type="hidden" id="txtCOILThickness" name="txtCOILThickness" value="<?php echo $coil_thickness; ?>" />
                    <input type="hidden" id="dblAreaSize" name="dblAreaSize" value="0" />
                    
                    <!-- Hidden Tracking Values for Weight Calculation -->
                    <input type="hidden" id="dblCOILActualWeight" name="dblCOILActualWeight" value="<?php echo $coil_actual_weight; ?>" />
                    <input type="hidden" id="dblCOILProduceWeight" name="dblCOILProduceWeight" value="<?php echo $coil_produce_weight; ?>" />
                    <input type="hidden" id="dblProduceWeight" name="dblProduceWeight" value="0" />
                    <input type="hidden" id="txtCOILScrapWeight" name="txtCOILScrapWeight" value="<?php echo $coil_scrap_weight; ?>" />
                    <input type="hidden" id="txtCOILAdjustWeight" name="txtCOILAdjustWeight" value="<?php echo $coil_adjust_weight; ?>" />
                    <input type="hidden" id="dblCRSHActualWeight" name="dblCRSHActualWeight" value="0" />
                    <input type="hidden" id="dblCRSHActualPiece" name="dblCRSHActualPiece" value="0" />
                    <input type="hidden" id="dblCRSHProduceWeight" name="dblCRSHProduceWeight" value="0" />
                    <input type="hidden" id="dblCRSHProducePiece" name="dblCRSHProducePiece" value="0" />

                    <div class="dashboard-card">
                        <h4 class="card-title-drop">⚙️ Drop Process Entry Form</h4>
                        
                        <div class="row">
                            <!-- 1. Product ID & Product Model -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="cmbProductID">Product ID</label>
                                <select class="form-control-custom" id="cmbProductID" name="cmbProductID" onchange="onProductIDChange()">
                                    <option value="">-- Select Product ID --</option>
                                    <?php foreach ($product_master_list as $prod): 
                                        $val = htmlspecialchars($prod['PRODUCT_ID'] . '|' . $prod['PRODUCT_MODEL']);
                                        $label = htmlspecialchars($prod['PRODUCT_ID'] . ' : ' . $prod['PRODUCT_MODEL']);
                                    ?>
                                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProductModel">Product Model</label>
                                <input type="text" class="form-control-custom" id="txtProductModel" name="txtProductModel" readonly placeholder="Auto Generated" />
                            </div>

                            <!-- 2. Width & Length -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtWidth">Width (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtWidth" name="txtWidth" value="0.000" onblur="onWidthBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtLength">Length (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtLength" name="txtLength" value="0.000" onblur="onLengthBlur()" />
                            </div>

                            <!-- 3. Weight per Piece -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtWeightPiece">Weight per Piece (Kg)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtWeightPiece" name="txtWeightPiece" readonly value="0.0000" />
                            </div>

                            <!-- 4. Cut Sheet Width & Cut Sheet Length -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCutWidth">Cut Sheet Width (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutWidth" name="txtCutWidth" value="0.000" onblur="onCutWidthBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCutLength">Cut Sheet Length (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutLength" name="txtCutLength" value="0.000" onblur="onCutLengthBlur()" />
                            </div>

                            <!-- 5. Cut Sheet Weight per Piece -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtCutWeightPiece">Cut Sheet Weight per Piece (Kg)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutWeightPiece" name="txtCutWeightPiece" readonly value="0.0000" />
                            </div>

                            <!-- 6. Produce Piece (Pc.) & Product Weight (Kg.) -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProducePiece">Produce Piece (Pc.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtProducePiece" name="txtProducePiece" value="0" onblur="onProducePieceBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProduceWeight">Product Weight (Kg.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtProduceWeight" name="txtProduceWeight" readonly value="0" />
                            </div>

                            <!-- 7. Pallet Weight & Reference No -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtBottomWeight">Pallet Weight (Kg.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtBottomWeight" name="txtBottomWeight" value="<?php echo number_format($coil_bottom_weight, 0); ?>" onblur="onBottomWeightBlur()" />
                            </div>

                            <hr style="width: 100%; border-top: 1px dashed #cbd5e1; margin: 10px 15px 20px 15px;" />

                            <!-- 8. Start Date & End Date -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="dtpStartDate">Start Date</label>
                                <input type="datetime-local" class="form-control-custom" id="dtpStartDate" name="dtpStartDate" value="<?php echo $current_now_datetime; ?>" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="dtpEndDate">End Date</label>
                                <input type="datetime-local" class="form-control-custom" id="dtpEndDate" name="dtpEndDate" value="<?php echo $current_now_datetime; ?>" />
                            </div>

                            <!-- 9. Job Reference -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtJobReference">Job Reference</label>
                                <input type="text" class="form-control-custom" id="txtJobReference" name="txtJobReference" placeholder="Enter Job Reference" />
                            </div>

                            <!-- 10. Work Process & Next Process -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="cmbWorkProcess">Work Process</label>
                                <input type="text" class="form-control-custom" id="cmbWorkProcess" name="cmbWorkProcess" value="CL>DP" readonly />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtNextProcess">Next Process</label>
                                <input type="text" class="form-control-custom" id="txtNextProcess" name="txtNextProcess" value="DP" readonly />
                            </div>

                            <!-- 11. Coil Balance Weight Tracker Summary -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCOILProduceWeight">Coil Total Produce Weight (Kg)</label>
                                <input type="text" class="form-control-custom" id="txtCOILProduceWeight" name="txtCOILProduceWeight" readonly value="<?php echo number_format($coil_produce_weight, 0); ?>" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCOILBalanceWeight">Coil Balance Weight (Kg)</label>
                                <input type="text" class="form-control-custom" id="txtCOILBalanceWeight" name="txtCOILBalanceWeight" readonly value="<?php echo number_format($coil_actual_weight - ($coil_produce_weight + $coil_scrap_weight + $coil_adjust_weight), 0); ?>" />
                            </div>
                        </div>

                        <!-- Submit Action Button -->
                        <div style="text-align: right; margin-top: 15px;">
                            <button type="submit" class="btn btn-save-drop">
                                💾 Save Drop Process
                            </button>
                        </div>
                    </div>
                </form>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
// AJAX Form Submission Handle สำหรับแสดง Alert
$('#frmDropCoil').on('submit', function(e) {
    e.preventDefault(); // ป้องกันการReload หน้าปกติ

    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // แสดงการแจ้งเตือนเมื่อสำเร็จ
                alert("✅ " + response.message + "\nProduct No: " + response.product_no);
                
                // ย้ายไปยังหน้า drop_product_result_mats.php ตาม URL ที่ส่งมาจากหลังบ้าน
                window.location.href = response.redirect;
            } else {
                alert("❌ เกิดข้อผิดพลาด: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("❌ ไม่สามารถเชื่อมต่อฐานข้อมูลหรือเกิดข้อผิดพลาดจากระบบ: " + error);
        }
    });
});

function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('drop_product_mats.php?func='+encodeURIComponent(data_fun)); 
}

// Utility: SubStringComboBox (ดึง Substring ก่อนสัญลักษณ์ |)
function subStringComboBox(strVal) {
    if (!strVal) return "";
    var pos = strVal.indexOf("|");
    if (pos !== -1) {
        return strVal.substring(0, pos).trim();
    }
    return strVal.trim();
}

// Weight Calculation Function
function weightPerPiece(strProductID, dblWidth, dblLength, dblThickness, dblAreaSize) {
    var weight = 0;
    if (dblWidth !== 0 && dblThickness !== 0) {
        switch (strProductID) {
            case "CC":
            case "CR":
                weight = (((( (22 / 7) * (dblWidth * dblWidth) ) / 4) * dblThickness) / 1000000) * 2.71;
                break;
            case "SH":
                if (dblLength !== 0) {
                    weight = (((dblWidth * dblLength * dblThickness) / 1000000) * 2.71);
                }
                break;
            case "NC":
                if (dblAreaSize !== 0) {
                    weight = (((dblAreaSize * dblThickness) / 1000000) * 2.71);
                }
                break;
        }
    }
    return weight;
}

// Handle Product ID Change
function onProductIDChange() {
    var cmbProductID = document.getElementById("cmbProductID").value;
    var txtProductModel = document.getElementById("txtProductModel");
    var txtLength = document.getElementById("txtLength");
    var subID = subStringComboBox(cmbProductID);

    document.getElementById("dblAreaSize").value = "0";

    if (subID === "CC") {
        txtProductModel.value = "CIRCLE";
        txtLength.disabled = true;
        txtLength.value = "0.000";
    } else if (subID === "SH") {
        txtProductModel.value = "SHEET";
        txtLength.disabled = false;
    } else if (subID === "NC") {
        var pos = cmbProductID.indexOf("|");
        var modelStr = "";
        if (pos !== -1) {
            modelStr = cmbProductID.substring(pos + 1, pos + 13).trim();
        }
        txtProductModel.value = modelStr;
        txtLength.disabled = false;

        // AJAX Request to fetch AREA_SIZE from DB
        fetch('drop_coil_process_mats.php?action=get_area_size&product_id=' + encodeURIComponent(subID) + '&product_model=' + encodeURIComponent(modelStr))
            .then(response => response.json())
            .then(data => {
                document.getElementById("dblAreaSize").value = data.area_size || 0;
                recalculateWeights();
            })
            .catch(err => console.error('Error fetching Area Size:', err));
    } else {
        if (cmbProductID !== "") {
            alert("รายการนี้ " + cmbProductID + " ไม่สามารถเลือกได้ โปรดเลือกรายการอื่น");
            document.getElementById("cmbProductID").focus();
        }
        txtProductModel.value = "";
        txtLength.disabled = false;
    }

    recalculateWeights();
}

// Recalculate Product Weights
function recalculateWeights() {
    var cmbProductID = document.getElementById("cmbProductID").value;
    var subID = subStringComboBox(cmbProductID);
    var txtWidth = parseFloat(document.getElementById("txtWidth").value) || 0;
    var txtLength = parseFloat(document.getElementById("txtLength").value) || 0;
    var txtCOILThickness = parseFloat(document.getElementById("txtCOILThickness").value) || 0;
    var dblAreaSize = parseFloat(document.getElementById("dblAreaSize").value) || 0;

    var wPiece = weightPerPiece(subID, txtWidth, txtLength, txtCOILThickness, dblAreaSize);
    document.getElementById("txtWeightPiece").value = wPiece.toFixed(4);

    recalculateCutWeights();
}

// Recalculate Cut Weights
function recalculateCutWeights() {
    var txtCutWidth = parseFloat(document.getElementById("txtCutWidth").value) || 0;
    var txtCutLength = parseFloat(document.getElementById("txtCutLength").value) || 0;
    var txtCOILThickness = parseFloat(document.getElementById("txtCOILThickness").value) || 0;
    var dblAreaSize = parseFloat(document.getElementById("dblAreaSize").value) || 0;

    var cutWPiece = weightPerPiece("SH", txtCutWidth, txtCutLength, txtCOILThickness, dblAreaSize);
    document.getElementById("txtCutWeightPiece").value = cutWPiece.toFixed(4);

    recalculateProduceWeights();
}

// Width Blur Logic
function onWidthBlur() {
    var txtWidthElem = document.getElementById("txtWidth");
    var val = txtWidthElem.value.trim();
    var dblWidth = parseFloat(val) || 0;

    if (val === "") {
        txtWidthElem.value = "0.000";
    } else {
        if (dblWidth > 1650) {
            txtWidthElem.value = dblWidth.toFixed(3);
        } else {
            txtWidthElem.value = dblWidth.toFixed(3);
        }
    }
    recalculateWeights();
}

// Length Blur Logic
function onLengthBlur() {
    var txtLengthElem = document.getElementById("txtLength");
    var val = txtLengthElem.value.trim();
    var dblLength = parseFloat(val) || 0;

    if (val === "") {
        txtLengthElem.value = "0.000";
    } else {
        if (dblLength > 4000) {
            txtLengthElem.value = dblLength.toFixed(3);
        } else {
            txtLengthElem.value = dblLength.toFixed(3);
        }
    }
    recalculateWeights();
}

// Cut Width Blur Logic
function onCutWidthBlur() {
    var cmbProductID = document.getElementById("cmbProductID").value;
    var subID = subStringComboBox(cmbProductID);
    var txtCutWidthElem = document.getElementById("txtCutWidth");
    var txtWidthVal = parseFloat(document.getElementById("txtWidth").value) || 0;
    var val = txtCutWidthElem.value.trim();
    var cutW = parseFloat(val) || 0;

    if (val === "") {
        txtCutWidthElem.value = "0.000";
    } else {
        if (cutW < txtWidthVal) {
            switch (subID) {
                case "CC":
                case "NC":
                    var newW = txtWidthVal + 20;
                    if (newW > 1600) newW = 1600;
                    txtCutWidthElem.value = newW.toFixed(3);
                    break;
                case "SH":
                    var newW = txtWidthVal + 3;
                    if (newW > 1600) newW = 1600;
                    txtCutWidthElem.value = newW.toFixed(3);
                    break;
            }
        } else {
            if (cutW > 1600) {
                txtCutWidthElem.value = "0.000";
            } else {
                txtCutWidthElem.value = cutW.toFixed(3);
            }
        }
    }
    // 1. คำนวณน้ำหนัก Cut Sheet ล่าสุด
    recalculateCutWeights();
    // 2. เรียกการตรวจสอบและคำนวณของ Cut Sheet Length ต่อทันที
    onCutLengthBlur();
}

// Cut Length Blur Logic
function onCutLengthBlur() {
    var cmbProductID = document.getElementById("cmbProductID").value;
    var subID = subStringComboBox(cmbProductID);
    var txtCutLengthElem = document.getElementById("txtCutLength");
    var txtWidthVal = parseFloat(document.getElementById("txtWidth").value) || 0;
    var txtLengthVal = parseFloat(document.getElementById("txtLength").value) || 0;
    var val = txtCutLengthElem.value.trim();
    var cutL = parseFloat(val) || 0;

    if (val === "") {
        txtCutLengthElem.value = "0.000";
    } else {
        switch (subID) {
            case "CC":
                if (cutL < txtWidthVal) {
                    var newL = txtWidthVal + 20;
                    if (newL > 1600) newL = 1600;
                    txtCutLengthElem.value = newL.toFixed(3);
                } else {
                    if (cutL > 1600) {
                        var newL = txtWidthVal + 20;
                        if (newL > 1600) newL = 1600;
                        txtCutLengthElem.value = newL.toFixed(3);
                    } else {
                        txtCutLengthElem.value = cutL.toFixed(3);
                    }
                }
                break;
            case "NC":
                if (cutL < txtLengthVal) {
                    var newL = txtLengthVal + 20;
                    if (newL > 4000) newL = 4000;
                    txtCutLengthElem.value = newL.toFixed(3);
                } else {
                    if (cutL > 4000) {
                        var newL = txtLengthVal + 20;
                        if (newL > 4000) newL = 4000;
                        txtCutLengthElem.value = newL.toFixed(3);
                    } else {
                        txtCutLengthElem.value = cutL.toFixed(3);
                    }
                }
                break;
            case "SH":
                if (cutL < txtLengthVal) {
                    var newL = txtLengthVal + 250;
                    if (newL > 4000) newL = 4000;
                    txtCutLengthElem.value = newL.toFixed(3);
                } else {
                    if (cutL > 4000) {
                        var newL = txtLengthVal + 250;
                        if (newL > 4000) newL = 4000;
                        txtCutLengthElem.value = newL.toFixed(3);
                    } else {
                        txtCutLengthElem.value = cutL.toFixed(3);
                    }
                }
                break;
        }
    }
    recalculateCutWeights();
}

// Produce Piece Blur Logic & Weight Calculations
function onProducePieceBlur() {
    recalculateProduceWeights();
}

function recalculateProduceWeights() {
    var txtCutWeightPiece = parseFloat(document.getElementById("txtCutWeightPiece").value) || 0;
    var txtProducePiece = parseFloat(document.getElementById("txtProducePiece").value) || 0;
    var txtProduceWeightElem = document.getElementById("txtProduceWeight");

    var dblProduceWeight = parseFloat(document.getElementById("dblProduceWeight").value) || 0;
    var dblCOILActualWeight = parseFloat(document.getElementById("dblCOILActualWeight").value) || 0;
    var dblCOILProduceWeight = parseFloat(document.getElementById("dblCOILProduceWeight").value) || 0;
    var txtCOILScrapWeight = parseFloat(document.getElementById("txtCOILScrapWeight").value) || 0;
    var txtCOILAdjustWeight = parseFloat(document.getElementById("txtCOILAdjustWeight").value) || 0;

    var txtCOILProduceWeightElem = document.getElementById("txtCOILProduceWeight");
    var txtCOILBalanceWeightElem = document.getElementById("txtCOILBalanceWeight");

    if (txtCutWeightPiece === 0) {
        txtProduceWeightElem.value = "0";
    } else {
        // คำนวณน้ำหนักผลผลิตรวมของแผ่น Drop
        var calculatedProdWeight = Math.round(txtProducePiece * txtCutWeightPiece);
        txtProduceWeightElem.value = calculatedProdWeight.toFixed(0);

        var dblVarProduceWeight = calculatedProdWeight - dblProduceWeight;

        // คำนวณน้ำหนัก Coil Produce Weight และ Balance Weight
        if (dblCOILActualWeight - (dblVarProduceWeight + dblCOILProduceWeight + txtCOILScrapWeight + txtCOILAdjustWeight) < 0) {
            txtProduceWeightElem.value = dblProduceWeight.toFixed(0);
            txtCOILProduceWeightElem.value = dblCOILProduceWeight.toFixed(0);
        } else {
            txtCOILProduceWeightElem.value = (dblCOILProduceWeight + dblVarProduceWeight).toFixed(0);
        }

        var currentProdW = parseFloat(txtCOILProduceWeightElem.value) || 0;
        txtCOILBalanceWeightElem.value = (dblCOILActualWeight - (currentProdW + txtCOILScrapWeight + txtCOILAdjustWeight)).toFixed(0);
    }
}

// Pallet Weight Blur Logic
function onBottomWeightBlur() {
    var txtBottomWeightElem = document.getElementById("txtBottomWeight");
    var val = txtBottomWeightElem.value.trim();
    var dblVal = parseFloat(val);
    var defaultBottomW = parseFloat("<?php echo $coil_bottom_weight; ?>") || 0;

    if (val === "" || isNaN(dblVal) || dblVal < 0 || dblVal > 200) {
        txtBottomWeightElem.value = defaultBottomW.toFixed(0);
    } else {
        txtBottomWeightElem.value = dblVal.toFixed(0);
    }
}
</script>
</html>