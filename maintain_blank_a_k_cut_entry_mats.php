<?php
session_start();

if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : 'mats';
$product_no      = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

$data = [];
$coilData = [];
$speedData = ['XYB_SPEED' => 0, 'PC_MINUTE' => 0];

if (!empty($product_no)) {
    $sql = "SELECT 
                B.PRODUCT_NO, B.MATERIAL_IN, B.COIL_NO, B.CRSH_STATUS, B.LINE_PROCESS, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE,
                B.CRSH_ACTUALWEIGHT, B.CRSH_ACTUALPIECE, B.CRSH_COMBINEWEIGHT, B.CRSH_COMBINEPIECE,
                B.CRSH_PRODUCEWEIGHT, B.CRSH_PRODUCEPIECE, B.CRSH_BOTTOMWEIGHT, B.CRSH_TAKEOUTWEIGHT, B.CRSH_TAKEOUTPIECE,
                B.CUT_WIDTH, B.CUT_LENGTH, B.CUT_WEIGHTPIECE, B.CRSH_STARTDATE, B.CRSH_STARTTIME, 
                B.CRSH_ENDDATE, B.CRSH_ENDTIME, B.CRSH_NEXTPROCESS, B.CRSH_LABEL,
                
                C.JOB_ORDER, C.JOB_STATUS, C.SCHEDULE_DATE, C.ALLOY AS JOB_ALLOY, C.TEMPER AS JOB_TEMPER, 
                C.PRODUCE_TEMPER1, C.PRODUCE_TEMPER2, C.GRADE AS JOB_GRADE, C.PRODUCE_GRADE1, C.PRODUCE_GRADE2,
                C.SURFACE_GRADE AS JOB_SG, C.METALLURGICAL_GRADE AS JOB_MG, C.THICKNESS AS JOB_THICKNESS,
                C.WIDTH AS JOB_WIDTH, C.LENGTH AS JOB_LENGTH, C.T5_TEMPERATURE AS JOB_T5, C.JOB_WORKPROCESS,
                C.WEIGHT_PIECE, C.JOB_MAXTOLERANCE, C.JOB_MINTOLERANCE, C.JOB_RELEASEWEIGHT, C.JOB_RELEASEPIECE,
                C.JOB_PRODUCEWEIGHT, C.JOB_PRODUCEPIECE, C.JOB_REMARK,
                
                D.SALEORDER_NO, D.SALEORDER_ITEM, D.ORDER_CATEGORY, D.CSTMSPPL_ID, D.CTM2_STATUS,
                D.CONFIRM_DATE, D.CTM2_PONO, D.CTM2_POITEM, D.CTM2_MAXTOLERANCE, D.CTM2_MINTOLERANCE,
                D.CTM2_ORDERWEIGHT, D.CTM2_ORDERPIECE, D.CTM2_REJECTWEIGHT, D.CTM2_REJECTPIECE,
                D.CTM2_REPLACEWEIGHT, D.CTM2_REPLACEPIECE, D.CTM2_PRODUCEWEIGHT, D.CTM2_PRODUCEPIECE
            FROM CRSHPROD1 AS B 
            INNER JOIN JOBORDER1 AS C ON B.JOB_ORDER = C.JOB_ORDER 
            INNER JOIN CSTMORDR2 AS D ON C.SALEORDER_NO = D.SALEORDER_NO AND C.SALEORDER_ITEM = D.SALEORDER_ITEM 
            WHERE B.PRODUCT_NO = :pno";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':pno' => $product_no]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!empty($data['COIL_NO'])) {
        $sqlCoil = "SELECT COIL_NO, COIL_STATUS, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, 
                           THICKNESS, WIDTH, T5_TEMPERATURE, COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, 
                           COIL_SCRAPWEIGHT, COIL_ADJUSTWEIGHT 
                    FROM COILPROD1 WHERE COIL_NO = :cno";
        $stmtCoil = $conn->prepare($sqlCoil);
        $stmtCoil->execute([':cno' => $data['COIL_NO']]);
        $coilData = $stmtCoil->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    if (isset($data['JOB_THICKNESS']) && isset($data['JOB_WIDTH'])) {
        $sqlSpeed = "SELECT XYB_SPEED, PC_MINUTE FROM STNDMSTR3 WHERE THICKNESS = :thk AND WIDTH = :wid";
        $stmtSpeed = $conn->prepare($sqlSpeed);
        $stmtSpeed->execute([
            ':thk' => (float)$data['JOB_THICKNESS'],
            ':wid' => (float)$data['JOB_WIDTH']
        ]);
        $speedRow = $stmtSpeed->fetch(PDO::FETCH_ASSOC);
        if ($speedRow) {
            $speedData = $speedRow;
        }
    }
}

$coilActualWt  = (float)($coilData['COIL_ACTUALWEIGHT'] ?? 0);
$coilProduceWt = (float)($coilData['COIL_PRODUCEWEIGHT'] ?? 0);
$coilScrapWt   = (float)($coilData['COIL_SCRAPWEIGHT'] ?? 0);
$coilAdjustWt  = (float)($coilData['COIL_ADJUSTWEIGHT'] ?? 0);
$coilBalanceWt = $coilActualWt - ($coilProduceWt + $coilScrapWt + $coilAdjustWt);

$jobRelWt    = (float)($data['JOB_RELEASEWEIGHT'] ?? 0);
$jobRelPc    = (float)($data['JOB_RELEASEPIECE'] ?? 0);
$jobMaxTol   = (float)($data['JOB_MAXTOLERANCE'] ?? 0);
$jobMinTol   = (float)($data['JOB_MINTOLERANCE'] ?? 0);
$weightPiece = (float)($data['WEIGHT_PIECE'] ?? 0);
$cutWtPiece  = (float)($data['CUT_WEIGHTPIECE'] ?? 0);

$jobMaxWt = round($jobRelWt + (($jobRelWt * $jobMaxTol) / 100));
$jobMinWt = round($jobRelWt - (($jobRelWt * $jobMinTol) / 100));
$jobMaxPc = ($weightPiece != 0) ? round($jobMaxWt / $weightPiece) : 0;
$jobMinPc = ($weightPiece != 0) ? round($jobMinWt / $weightPiece) : 0;

$maxSheetWt     = ($cutWtPiece != 0) ? round($jobMaxPc * $cutWtPiece) : 0;
$releaseSheetWt = ($cutWtPiece != 0) ? round($jobRelPc * $cutWtPiece) : 0;
$minSheetWt     = ($cutWtPiece != 0) ? round($jobMinPc * $cutWtPiece) : 0;

$ctmOrderWt   = (float)($data['CTM2_ORDERWEIGHT'] ?? 0);
$ctmReplaceWt = (float)($data['CTM2_REPLACEWEIGHT'] ?? 0);
$ctmTotalWt   = $ctmOrderWt + $ctmReplaceWt;
$ctmMaxWt     = round($ctmTotalWt + (($ctmTotalWt * $jobMaxTol) / 100));
$ctmMinWt     = round($ctmTotalWt - (($ctmTotalWt * $jobMinTol) / 100));
$ctmTotalPc   = ($weightPiece != 0) ? round($ctmTotalWt / $weightPiece) : 0;
$ctmMaxPc     = ($weightPiece != 0) ? round($ctmMaxWt / $weightPiece) : 0;
$ctmMinPc     = ($weightPiece != 0) ? round($ctmMinWt / $weightPiece) : 0;

$xybSpeed = (float)($speedData['XYB_SPEED'] ?? 0);
$pcMinute = (int)($speedData['PC_MINUTE'] ?? 0);
$efficiency = 0;
if ((float)($data['CRSH_PRODUCEPIECE'] ?? 0) > 0 && $xybSpeed > 0 && !empty($data['CRSH_STARTDATE']) && !empty($data['CRSH_ENDDATE'])) {
    $startDT = strtotime(date('Y-m-d', strtotime($data['CRSH_STARTDATE'])) . ' ' . date('H:i:s', strtotime($data['CRSH_STARTTIME'] ?? '00:00:00')));
    $endDT   = strtotime(date('Y-m-d', strtotime($data['CRSH_ENDDATE'])) . ' ' . date('H:i:s', strtotime($data['CRSH_ENDTIME'] ?? '00:00:00')));
    $produceTimeMin = round(($endDT - $startDT) / 60);
    if ($produceTimeMin > 0) {
        $efficiency = round((((float)$data['CRSH_PRODUCEPIECE'] * $xybSpeed) / $produceTimeMin) * 100);
    }
}

$workProcess = $data['JOB_WORKPROCESS'] ?? '';
$isCLProcess = (substr($workProcess, 0, 2) === 'CL');
$canEditCutSize = (strpos($workProcess, 'CL>') !== false && (float)($data['CUT_WIDTH'] ?? 0) > 0);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Maintain Blank / A / K / Cut Entry</title>
    <?php include 'include/header.php';?>
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .card-section { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card-section h4 { margin: 0 0 15px 0; font-weight: 700; color: #1e293b; font-size: 16px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        
        .form-group { margin-bottom: 12px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 3px; display: block; }
        .form-control { border-radius: 6px; border: 1px solid #cbd5e1; height: 34px; font-size: 13px; font-weight: 600; color: #1e293b; padding: 4px 10px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .readonly-box { background-color: #f1f5f9 !important; color: #334155; cursor: not-allowed; }
        .locked-field { background-color: #e2e8f0 !important; color: #475569 !important; cursor: not-allowed; }
        .editable-field { border-color: #3b82f6 !important; background-color: #ffffff !important; color: #1d4ed8 !important; }
        .input-group-row { display: flex; gap: 6px; align-items: center; }
        .unit-badge { font-size: 11.5px; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 6px 8px; border-radius: 5px; white-space: nowrap; }
        
        .alert-banner { display: none; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-weight: 600; font-size: 13.5px; }
        .alert-error { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-warn { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

        .btn-toolbar-custom { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 25px; justify-content: flex-end; align-items: center; background: #ffffff; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .btn-save { background-color: #2563eb; color: #fff; font-weight: 600; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-save:hover { background-color: #1d4ed8; }
        .btn-delete { background-color: #dc2626; color: #fff; font-weight: 600; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-delete:hover { background-color: #b91c1c; }
        .btn-cancel { background-color: #64748b; color: #fff; font-weight: 600; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-cancel:hover { background-color: #475569; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Maintain Blanking, A Press, K Press, XY Circle Shear
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div id="validationAlert" class="alert-banner alert-error"></div>
            <div id="warningAlert" class="alert-banner alert-warn"></div>

            <form id="entryForm" action="model/update_blank_a_k_cut_process.php" method="POST" onsubmit="return validateBeforeSubmit()">
                <input type="hidden" name="action_type" id="action_type" value="UPDATE">
                <input type="hidden" name="produce_time_min" id="produce_time_min" value="0">
                
                <div class="row">
                    <!-- LEFT COLUMN: Product Pallet & Sale Order -->
                    <div class="col-md-6">
                        <!-- 1. Product Pallet -->
                        <div class="card-section">
                            <h4>📦 Product Pallet</h4>
                            <div class="row">
                                <div class="form-group col-md-8">
                                    <label>Product No. - Status</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" name="product_no" id="product_no" value="<?php echo htmlspecialchars($data['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" name="crsh_status" style="width: 70px; text-align:center;" value="<?php echo htmlspecialchars($data['CRSH_STATUS'] ?? 'OP', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Material In</label>
                                    <input type="text" class="form-control readonly-box" name="material_in" value="<?php echo htmlspecialchars($data['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label>Line Process - Product ID - Model</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" style="width: 25%;" value="<?php echo htmlspecialchars($data['LINE_PROCESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="product_id" style="width: 25%;" value="<?php echo htmlspecialchars($data['PRODUCT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" style="width: 50%;" value="<?php echo htmlspecialchars($data['PRODUCT_MODEL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Actual Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" name="crsh_actual_weight" id="crsh_actual_weight" value="<?php echo number_format((float)($data['CRSH_ACTUALWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" name="crsh_actual_piece" id="crsh_actual_piece" value="<?php echo number_format((float)($data['CRSH_ACTUALPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Combine Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CRSH_COMBINEWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CRSH_COMBINEPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label style="color:#2563eb;">Produce Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="number" step="1" class="form-control <?php echo $isCLProcess ? 'locked-field' : 'editable-field'; ?>" name="crsh_produce_weight" id="crsh_produce_weight" value="<?php echo number_format((float)($data['CRSH_PRODUCEWEIGHT'] ?? 0), 0, '.', ''); ?>" <?php echo $isCLProcess ? 'readonly' : ''; ?>>
                                        <input type="number" step="1" class="form-control <?php echo $isCLProcess ? 'editable-field' : 'locked-field'; ?>" name="crsh_produce_piece" id="crsh_produce_piece" value="<?php echo number_format((float)($data['CRSH_PRODUCEPIECE'] ?? 0), 0, '.', ''); ?>" <?php echo $isCLProcess ? '' : 'readonly'; ?>>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label style="color:#2563eb;">Pallet / Bottom Weight (kg.) *</label>
                                    <input type="number" step="any" class="form-control editable-field" name="crsh_bottom_weight" id="crsh_bottom_weight" value="<?php echo number_format((float)($data['CRSH_BOTTOMWEIGHT'] ?? 0), 0, '.', ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Take Out Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="crsh_takeout_weight" value="<?php echo number_format((float)($data['CRSH_TAKEOUTWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="crsh_takeout_piece" value="<?php echo number_format((float)($data['CRSH_TAKEOUTPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Order Weight per Piece</label>
                                    <input type="text" class="form-control readonly-box" id="weight_piece" value="<?php echo number_format($weightPiece, 4, '.', ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Cut Sheet Size of SH/SC (W x L)</label>
                                    <div class="input-group-row">
                                        <input type="number" step="any" class="form-control <?php echo $canEditCutSize ? 'editable-field' : 'readonly-box'; ?>" name="cut_width" id="cut_width" value="<?php echo number_format((float)($data['CUT_WIDTH'] ?? 0), 3, '.', ''); ?>" <?php echo $canEditCutSize ? '' : 'readonly'; ?>>
                                        <input type="number" step="any" class="form-control <?php echo $canEditCutSize ? 'editable-field' : 'readonly-box'; ?>" name="cut_length" id="cut_length" value="<?php echo number_format((float)($data['CUT_LENGTH'] ?? 0), 3, '.', ''); ?>" <?php echo $canEditCutSize ? '' : 'readonly'; ?>>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Cut Sheet Weight per Piece</label>
                                    <input type="text" class="form-control readonly-box" name="cut_weight_piece" id="cut_weight_piece" value="<?php echo number_format($cutWtPiece, 4, '.', ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label style="color:#2563eb;">Start Date - Time *</label>
                                    <div class="input-group-row">
                                        <input type="date" class="form-control editable-field" name="start_date" id="start_date" value="<?php echo !empty($data['CRSH_STARTDATE']) ? date('Y-m-d', strtotime($data['CRSH_STARTDATE'])) : date('Y-m-d'); ?>">
                                        <input type="time" step="1" class="form-control editable-field" name="start_time" id="start_time" value="<?php echo !empty($data['CRSH_STARTTIME']) ? date('H:i:s', strtotime($data['CRSH_STARTTIME'])) : date('H:i:s'); ?>">
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label style="color:#2563eb;">End Date - Time *</label>
                                    <div class="input-group-row">
                                        <input type="date" class="form-control editable-field" name="end_date" id="end_date" value="<?php echo !empty($data['CRSH_ENDDATE']) ? date('Y-m-d', strtotime($data['CRSH_ENDDATE'])) : date('Y-m-d'); ?>">
                                        <input type="time" step="1" class="form-control editable-field" name="end_time" id="end_time" value="<?php echo !empty($data['CRSH_ENDTIME']) ? date('H:i:s', strtotime($data['CRSH_ENDTIME'])) : date('H:i:s'); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Next Process</label>
                                    <input type="text" class="form-control readonly-box" name="crsh_next_process" id="crsh_next_process" value="<?php echo htmlspecialchars($data['CRSH_NEXTPROCESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Sale Order -->
                        <div class="card-section">
                            <h4>🛒 Sale Order</h4>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Sale Order No - Item</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['SALEORDER_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" style="width:70px;" value="<?php echo htmlspecialchars($data['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Order Category</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['ORDER_CATEGORY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Status</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['CTM2_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Confirm Date</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo !empty($data['CONFIRM_DATE']) ? date('m/d/Y', strtotime($data['CONFIRM_DATE'])) : ''; ?>" readonly>
                                </div>
                                <div class="form-group col-md-8">
                                    <label>Purchase Order - Item</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['CTM2_PONO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" style="width:70px;" value="<?php echo htmlspecialchars($data['CTM2_POITEM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Max - Min Tolerance (%)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobMaxTol, 0); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobMinTol, 0); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Order Qty. (kg. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($ctmOrderWt, 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CTM2_ORDERPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Order Reject Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CTM2_REJECTWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CTM2_REJECTPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Order Replace Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($ctmReplaceWt, 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($data['CTM2_REPLACEPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Maximum Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmMaxWt; ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmMaxPc; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Total Order Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmTotalWt; ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmTotalPc; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Minimum Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmMinWt; ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo $ctmMinPc; ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Sale Order Produce Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="ctm2_produce_weight" value="<?php echo number_format((float)($data['CTM2_PRODUCEWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="ctm2_produce_piece" value="<?php echo number_format((float)($data['CTM2_PRODUCEPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Toolbar: Print Label + Cancel + Delete + Save -->
                        <div class="btn-toolbar-custom">
                            <div style="display:flex; align-items:center; gap:8px; margin-right:auto;">
                                <label style="margin:0; cursor:pointer; font-weight:700; color:#334155;">
                                    <input type="checkbox" name="print_label" id="print_label" value="1"> Print Label
                                </label>
                                <select class="form-control" name="label_size" style="width:95px; height:36px;">
                                    <option value="29x21">29x21</option>
                                    <option value="10x15">10x15</option>
                                    <option value="21x15">21x15</option>
                                </select>
                                <input type="text" class="form-control readonly-box" name="crsh_label_count" id="crsh_label_count" style="width:50px; height:36px; text-align:center;" value="<?php echo htmlspecialchars($data['CRSH_LABEL'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                            <button type="button" class="btn-cancel" onclick="window.location.assign('maintain_blank_a_k_cut_process_mats.php?func=<?php echo $folder_func;?>')">Cancel</button>
                            <button type="button" class="btn-delete" onclick="confirmDelete()">🗑️ Delete</button>
                            <button type="submit" class="btn-save" onclick="document.getElementById('action_type').value='UPDATE';">💾 Save & Update</button>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Job Order & Coil Information -->
                    <div class="col-md-6">
                        <!-- 3. Job Order -->
                        <div class="card-section">
                            <h4>📋 Job Order</h4>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Job Order - Status</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" name="job_order" id="job_order" value="<?php echo htmlspecialchars($data['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" style="width:65px; text-align:center;" value="<?php echo htmlspecialchars($data['JOB_STATUS'] ?? 'OP', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Customer - ETD Date</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo !empty($data['CONFIRM_DATE']) ? date('m/d/Y', strtotime($data['CONFIRM_DATE'])) : ''; ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label>Alloy</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_ALLOY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="form-group col-md-8">
                                    <label>Temper - Produce Temper</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_TEMPER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['PRODUCE_TEMPER1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['PRODUCE_TEMPER2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-8">
                                    <label>Grade - Produce Grade</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['PRODUCE_GRADE1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['PRODUCE_GRADE2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Surface - Metallurgical Grade</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_SG'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_MG'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-8">
                                    <label>Thickness - Width - Length</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="job_thickness" value="<?php echo number_format((float)($data['JOB_THICKNESS'] ?? 0), 3, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="job_width" value="<?php echo number_format((float)($data['JOB_WIDTH'] ?? 0), 3, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="job_length" value="<?php echo number_format((float)($data['JOB_LENGTH'] ?? 0), 3, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>T5 Temperature</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_T5'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-8">
                                    <label>Work Process</label>
                                    <input type="text" class="form-control readonly-box" id="work_process" value="<?php echo htmlspecialchars($workProcess, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Max - Min Tolerance</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobMaxTol, 0); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobMinTol, 0); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-7">
                                    <label>Maximum Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="job_max_weight" value="<?php echo $jobMaxWt; ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="job_max_piece" value="<?php echo $jobMaxPc; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Max. Sheet Weight</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo $maxSheetWt; ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-7">
                                    <label>Job Release Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobRelWt, 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" value="<?php echo number_format($jobRelPc, 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Release Sheet Weight</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo $releaseSheetWt; ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-7">
                                    <label>Minimum Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="job_min_weight" value="<?php echo $jobMinWt; ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="job_min_piece" value="<?php echo $jobMinPc; ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Min. Sheet Weight</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo $minSheetWt; ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-7">
                                    <label>Produce Qty. (Wt. - Pc.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="job_produce_weight" value="<?php echo number_format((float)($data['JOB_PRODUCEWEIGHT'] ?? 0), 0, '.', ''); ?>" readonly>
                                        <input type="text" class="form-control readonly-box" id="job_produce_piece" value="<?php echo number_format((float)($data['JOB_PRODUCEPIECE'] ?? 0), 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label>Remark</label>
                                    <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($data['JOB_REMARK'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-8">
                                    <label>Speed (min./pcs & pcs/min.)</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" name="xyb_speed" id="xyb_speed" value="<?php echo number_format($xybSpeed, 3, '.', ''); ?>" readonly>
                                        <span class="unit-badge">min./pcs</span>
                                        <input type="text" class="form-control readonly-box" name="pc_minute" id="pc_minute" value="<?php echo $pcMinute; ?>" readonly>
                                        <span class="unit-badge">pcs/min.</span>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Efficiency</label>
                                    <div class="input-group-row">
                                        <input type="text" class="form-control readonly-box" id="efficiency" value="<?php echo $efficiency; ?>" readonly>
                                        <span class="unit-badge">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Coil Information -->
                        <div class="card-section">
                            <h4>🌀 Coil</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Coil No. - Status</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" name="coil_no" value="<?php echo htmlspecialchars($coilData['COIL_NO'] ?? ($data['COIL_NO'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" style="width:65px; text-align:center;" value="<?php echo htmlspecialchars($coilData['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Alloy - Temper</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Grade - SG - MG</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['SURFACE_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['METALLURGICAL_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Thickness - Width</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" id="coil_thickness" value="<?php echo number_format((float)($coilData['THICKNESS'] ?? 0), 3, '.', ''); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" value="<?php echo number_format((float)($coilData['WIDTH'] ?? 0), 3, '.', ''); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>T5 Temperature</label>
                                        <input type="text" class="form-control readonly-box" value="<?php echo htmlspecialchars($coilData['T5_TEMPERATURE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Coil Weight</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" id="coil_actual_weight" value="<?php echo number_format($coilActualWt, 0, '.', ''); ?>" readonly>
                                            <span class="unit-badge">kg.</span>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Produce Weight</label>
                                        <input type="text" class="form-control readonly-box" name="coil_produce_weight" id="coil_produce_weight" value="<?php echo number_format($coilProduceWt, 0, '.', ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Scrap - Adjust Weight</label>
                                        <div class="input-group-row">
                                            <input type="text" class="form-control readonly-box" id="coil_scrap_weight" value="<?php echo number_format($coilScrapWt, 0, '.', ''); ?>" readonly>
                                            <input type="text" class="form-control readonly-box" id="coil_adjust_weight" value="<?php echo number_format($coilAdjustWt, 0, '.', ''); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Balance Weight</label>
                                        <input type="text" class="form-control readonly-box" name="coil_balance_weight" id="coil_balance_weight" style="color:#059669; font-weight:700;" value="<?php echo number_format($coilBalanceWt, 0, '.', ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'include/content-footer.php';?>
   </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
const initCRSHActualWeight  = <?php echo (float)($data['CRSH_ACTUALWEIGHT'] ?? 0); ?>;
const initCRSHActualPiece   = <?php echo (float)($data['CRSH_ACTUALPIECE'] ?? 0); ?>;
const initCRSHProduceWeight = <?php echo (float)($data['CRSH_PRODUCEWEIGHT'] ?? 0); ?>;
const initCRSHProducePiece  = <?php echo (float)($data['CRSH_PRODUCEPIECE'] ?? 0); ?>;
const initCRSHTakeOutWeight = <?php echo (float)($data['CRSH_TAKEOUTWEIGHT'] ?? 0); ?>;
const initCRSHTakeOutPiece  = <?php echo (float)($data['CRSH_TAKEOUTPIECE'] ?? 0); ?>;
const initCRSHBottomWeight  = <?php echo (float)($data['CRSH_BOTTOMWEIGHT'] ?? 0); ?>;
const initCOILProduceWeight = <?php echo $coilProduceWt; ?>;
const initJOBProduceWeight  = <?php echo (float)($data['JOB_PRODUCEWEIGHT'] ?? 0); ?>;
const initJOBProducePiece   = <?php echo (float)($data['JOB_PRODUCEPIECE'] ?? 0); ?>;
const initCTM2ProduceWeight = <?php echo (float)($data['CTM2_PRODUCEWEIGHT'] ?? 0); ?>;
const initCTM2ProducePiece  = <?php echo (float)($data['CTM2_PRODUCEPIECE'] ?? 0); ?>;
const initCRSHLabel         = <?php echo (int)($data['CRSH_LABEL'] ?? 0); ?>;
const areaSize              = <?php echo (float)($data['AREA_SIZE'] ?? 0); ?>;

const isCLProcess = <?php echo $isCLProcess ? 'true' : 'false'; ?>;
const workProcess = "<?php echo addslashes($workProcess); ?>";
const productID   = "<?php echo addslashes($data['PRODUCT_ID'] ?? ''); ?>";

function calcWeightPerPiece(prodId, width, length, thickness, area) {
    if (thickness <= 0) return 0;
    const density = 2.71;
    if (prodId === "SH") {
        return (width * length * thickness * density) / 1000000;
    } else if (prodId === "CC") {
        let r = width / 2;
        return (Math.PI * r * r * thickness * density) / 1000000;
    } else if (area > 0) {
        return (area * thickness * density) / 1000000;
    }
    return parseFloat(document.getElementById("weight_piece").value) || 0;
}

function showAlert(type, msg) {
    const errBox = document.getElementById("validationAlert");
    const warnBox = document.getElementById("warningAlert");
    if (type === "error") {
        errBox.innerText = "❌ " + msg;
        errBox.style.display = msg ? "block" : "none";
    } else {
        warnBox.innerText = "⚠️ " + msg;
        warnBox.style.display = msg ? "block" : "none";
    }
}

function verifyValue() {
    showAlert("error", "");
    showAlert("warn", "");
    let isValid = true;

    const prodWtInput   = document.getElementById("crsh_produce_weight");
    const prodPcInput   = document.getElementById("crsh_produce_piece");
    const bottomWtInput = document.getElementById("crsh_bottom_weight");
    const cutWidth      = parseFloat(document.getElementById("cut_width").value) || 0;
    const cutLength     = parseFloat(document.getElementById("cut_length").value) || 0;
    const coilThickness = parseFloat(document.getElementById("coil_thickness").value) || 0;
    const weightPiece   = parseFloat(document.getElementById("weight_piece").value) || 0;
    let cutWeightPiece  = parseFloat(document.getElementById("cut_weight_piece").value) || 0;

    let pos = workProcess.indexOf("CT>");
    if (pos === -1) pos = workProcess.indexOf("SC>");
    if (pos === -1) pos = workProcess.indexOf("SH>");

    if (isCLProcess && cutWidth > 0 && cutLength > 0 && coilThickness > 0) {
        cutWeightPiece = calcWeightPerPiece("SH", cutWidth, cutLength, coilThickness, areaSize);
        document.getElementById("cut_weight_piece").value = cutWeightPiece.toFixed(4);
    } else if (workProcess.indexOf("SH>") > 0 && cutWidth > 0 && coilThickness > 0) {
        cutWeightPiece = calcWeightPerPiece("CC", cutWidth, 0, coilThickness, areaSize);
        document.getElementById("cut_weight_piece").value = cutWeightPiece.toFixed(4);
    }

    const activeUnitWeight = (pos > 0 && cutWeightPiece > 0) ? cutWeightPiece : weightPiece;
    let varProduceWeight = 0;
    let varProducePiece  = 0;

    if (isCLProcess) {
        let currentPc = parseFloat(prodPcInput.value) || 0;
        varProducePiece = currentPc - initCRSHProducePiece;
        if (activeUnitWeight > 0) {
            varProduceWeight = Math.round(varProducePiece * activeUnitWeight);
        }
        prodWtInput.value = Math.round(initCRSHProduceWeight + varProduceWeight);
    } else {
        let currentWt = parseFloat(prodWtInput.value) || 0;
        varProduceWeight = currentWt - initCRSHProduceWeight;
        if (activeUnitWeight > 0) {
            varProducePiece = Math.round(varProduceWeight / activeUnitWeight);
        }
        prodPcInput.value = Math.round(initCRSHProducePiece + varProducePiece);
    }

    const jobMaxWt = parseFloat(document.getElementById("job_max_weight").value) || 0;
    if ((initJOBProduceWeight + varProduceWeight) > jobMaxWt && jobMaxWt > 0) {
        showAlert("warn", "แจ้งเตือน: ได้ผลิตผลิตภัณฑ์ตามใบสั่งผลิตมากกว่าปริมาณสูงสุดหรือสมบูรณ์แล้ว แต่ยังสามารถทำข้อมูลต่อไปได้");
    }

    const coilActualWt = parseFloat(document.getElementById("coil_actual_weight").value) || 0;
    const coilScrapWt  = parseFloat(document.getElementById("coil_scrap_weight").value) || 0;
    const coilAdjustWt = parseFloat(document.getElementById("coil_adjust_weight").value) || 0;

    if ((initCOILProduceWeight + varProduceWeight + coilScrapWt + coilAdjustWt) > coilActualWt && coilActualWt > 0) {
        prodWtInput.value = Math.round(initCRSHProduceWeight);
        prodPcInput.value = Math.round(initCRSHProducePiece);
        showAlert("error", "น้ำหนักผลิตรวมของ Coil เกินกว่าน้ำหนักจริงของ Coil (" + coilActualWt + " kg.)");
        return false;
    } else if (coilActualWt > 0) {
        let prefix3 = workProcess.substring(0, 3);
        if (productID === "CC" || productID === "NC") {
            if (["BK>", "AP>", "KP>"].includes(prefix3)) {
                if ((initCOILProduceWeight + varProduceWeight) > (coilActualWt * 0.85)) {
                    prodWtInput.value = Math.round(initCRSHProduceWeight);
                    prodPcInput.value = Math.round(initCRSHProducePiece);
                    showAlert("error", "น้ำหนักผลิตเกิน 85% ของน้ำหนัก Coil จริง");
                    return false;
                }
            } else if (prefix3 === "CL>") {
                if ((initCOILProduceWeight + varProduceWeight) > (coilActualWt * 0.95)) {
                    prodWtInput.value = Math.round(initCRSHProduceWeight);
                    prodPcInput.value = Math.round(initCRSHProducePiece);
                    showAlert("error", "น้ำหนักผลิตเกิน 95% ของน้ำหนัก Coil จริง");
                    return false;
                }
            }
        } else if (productID === "SH") {
            if ((initCOILProduceWeight + varProduceWeight) > (coilActualWt * 0.95)) {
                prodWtInput.value = Math.round(initCRSHProduceWeight);
                prodPcInput.value = Math.round(initCRSHProducePiece);
                showAlert("error", "น้ำหนักผลิตเกิน 95% ของน้ำหนัก Coil จริง");
                return false;
            }
        }
    }

    const nextProc = document.getElementById("crsh_next_process").value;
    if (nextProc === "IS") {
        document.getElementById("crsh_actual_weight").value = Math.round(initCRSHActualWeight + varProduceWeight);
        document.getElementById("crsh_actual_piece").value  = Math.round(initCRSHActualPiece + varProducePiece);
    } else {
        document.getElementById("crsh_actual_weight").value = Math.round(initCRSHActualWeight + varProduceWeight + initCRSHTakeOutWeight);
        document.getElementById("crsh_actual_piece").value  = Math.round(initCRSHActualPiece + varProducePiece + initCRSHTakeOutPiece);
    }

    document.getElementById("job_produce_weight").value  = Math.round(initJOBProduceWeight + varProduceWeight);
    document.getElementById("job_produce_piece").value   = Math.round(initJOBProducePiece + varProducePiece);
    document.getElementById("ctm2_produce_weight").value = Math.round(initCTM2ProduceWeight + varProduceWeight);
    document.getElementById("ctm2_produce_piece").value  = Math.round(initCTM2ProducePiece + varProducePiece);

    let newCoilProduceWt = initCOILProduceWeight + varProduceWeight;
    document.getElementById("coil_produce_weight").value = Math.round(newCoilProduceWt);
    document.getElementById("coil_balance_weight").value = Math.round(coilActualWt - (newCoilProduceWt + coilScrapWt + coilAdjustWt));

    let bottomWt = parseFloat(bottomWtInput.value) || 0;
    if (bottomWt > 200) {
        bottomWtInput.value = Math.round(initCRSHBottomWeight);
        showAlert("error", "น้ำหนักก้นคอยล์ (Bottom Weight) ต้องไม่เกิน 200 kg.");
        return false;
    }

    calcEfficiency();
    return isValid;
}

function calcEfficiency() {
    const startDate = document.getElementById("start_date").value;
    const startTime = document.getElementById("start_time").value;
    const endDate   = document.getElementById("end_date").value;
    const endTime   = document.getElementById("end_time").value;
    const prodPc    = parseFloat(document.getElementById("crsh_produce_piece").value) || 0;
    const speed     = parseFloat(document.getElementById("xyb_speed").value) || 0;

    if (startDate && startTime && endDate && endTime) {
        const startDT = new Date(startDate + "T" + startTime);
        const endDT   = new Date(endDate + "T" + endTime);
        const diffMin = Math.round((endDT - startDT) / 60000);
        document.getElementById("produce_time_min").value = diffMin > 0 ? diffMin : 0;

        if (prodPc > 0 && speed > 0 && diffMin > 0) {
            document.getElementById("efficiency").value = Math.round(((prodPc * speed) / diffMin) * 100);
        } else {
            document.getElementById("efficiency").value = 0;
        }
    }
}

function validateBeforeSubmit() {
    if (document.getElementById("action_type").value === "DELETE") {
        return true;
    }

    const startDate = document.getElementById("start_date").value;
    const startTime = document.getElementById("start_time").value;
    const endDate   = document.getElementById("end_date").value;
    const endTime   = document.getElementById("end_time").value;
    const todayStr  = new Date().toISOString().split('T')[0];

    if (startDate > todayStr) {
        alert("วันที่เริ่มผลิต (" + startDate + ") ไม่ถูกต้อง มากกว่าวันที่ปัจจุบัน (" + todayStr + ")");
        document.getElementById("start_date").focus();
        return false;
    }

    if (endDate < startDate) {
        alert("วันที่ผลิตเสร็จ (" + endDate + ") ไม่ถูกต้อง น้อยกว่าวันที่เริ่มผลิต (" + startDate + ")");
        document.getElementById("end_date").focus();
        return false;
    }

    const startDT = new Date(startDate + "T" + startTime);
    const endDT   = new Date(endDate + "T" + endTime);
    const diffMin = Math.round((endDT - startDT) / 60000);

    if (diffMin <= 0) {
        alert("ข้อมูลเวลาที่ป้อนไม่ถูกต้อง ใช้เวลาในการผลิตเป็น 0 หรือน้อยกว่า 0 นาที");
        document.getElementById("end_time").focus();
        return false;
    }

    if (!verifyValue()) {
        return false;
    }

    const prodWt = parseFloat(document.getElementById("crsh_produce_weight").value) || 0;
    if (prodWt <= 0) {
        alert("กรุณาระบุน้ำหนักผลิต (Produce Weight) ให้มากกว่า 0");
        return false;
    }

    return true;
}

function confirmDelete() {
    if (confirm("คุณต้องการยืนยันการลบข้อมูล (Delete Produce) ใช่หรือไม่?")) {
        document.getElementById('action_type').value = 'DELETE';
        document.getElementById('entryForm').submit();
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const watchIds = [
        "crsh_produce_weight", "crsh_produce_piece", "crsh_bottom_weight",
        "cut_width", "cut_length", "start_date", "start_time", "end_date", "end_time"
    ];
    watchIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("change", verifyValue);
            el.addEventListener("input", verifyValue);
        }
    });

    const chkPrint = document.getElementById("print_label");
    if (chkPrint) {
        chkPrint.addEventListener("change", function() {
            document.getElementById("crsh_label_count").value = this.checked ? (initCRSHLabel + 1) : initCRSHLabel;
        });
    }
});
</script>
</html>