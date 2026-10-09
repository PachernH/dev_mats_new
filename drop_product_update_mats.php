<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า PRODUCT_NO
$product_no = isset($_GET['PRODUCT_NO']) ? htmlspecialchars(trim($_GET['PRODUCT_NO']), ENT_QUOTES, 'UTF-8') : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// ดึงข้อมูลเดิมของ Drop Product จาก CRSHPROD1 และข้อมูล Coil
$crsh_data = null;
$coil_data = null;

if (!empty($product_no)) {
    // 1. ดึงข้อมูลจาก CRSHPROD1
    $sql_crsh = "SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno";
    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->bindParam(':pno', $product_no, PDO::PARAM_STR);
    $stmt_crsh->execute();
    $crsh_data = $stmt_crsh->fetch(PDO::FETCH_ASSOC);

    if ($crsh_data) {
        $coil_no = $crsh_data['COIL_NO'] ?? '';
        
        // 2. ดึงข้อมูล Coil Details
        if (!empty($coil_no)) {
            $sql_coil = "SELECT p.*, i.INSPECTION_DATE 
                         FROM COILPROD1 p
                         LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
                         WHERE p.COIL_NO = :cno";
            $stmt_coil = $conn->prepare($sql_coil);
            $stmt_coil->bindParam(':cno', $coil_no, PDO::PARAM_STR);
            $stmt_coil->execute();
            $coil_data = $stmt_coil->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// ดึงรายการ PRODUCT_ID และ PRODUCT_MODEL จาก PRODMSTR1
$product_master_list = [];
$sql_prod = "SELECT PRODUCT_ID, PRODUCT_MODEL FROM PRODMSTR1 ORDER BY PRODUCT_ID ASC";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->execute();
$product_master_list = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);

// กำหนดตัวแปรสำหรับคำนวณ
$coil_thickness       = floatval($crsh_data['THICKNESS'] ?? ($coil_data['THICKNESS'] ?? 0));
$coil_actual_weight    = floatval($coil_data['COIL_ACTUALWEIGHT'] ?? 0);
$coil_produce_weight   = floatval($coil_data['COIL_PRODUCEWEIGHT'] ?? 0);
$coil_scrap_weight     = floatval($coil_data['COIL_SCRAPWEIGHT'] ?? 0);
$coil_adjust_weight    = floatval($coil_data['COIL_ADJUSTWEIGHT'] ?? 0);
$coil_bottom_weight    = floatval($crsh_data['CRSH_BOTTOMWEIGHT'] ?? ($coil_data['COIL_BOTTOMWEIGHT'] ?? 0));

// คำนวณค่าน้ำหนักคงเหลือปัจจุบันของ Coil
$coil_balance_weight   = floatval($coil_data['COIL_BALANCEWEIGHT'] ?? ($coil_actual_weight - ($coil_produce_weight + $coil_scrap_weight + $coil_adjust_weight)));

$old_produce_weight    = floatval($crsh_data['CRSH_PRODUCEWEIGHT'] ?? 0);

// จัดรูปแบบวันที่สำหรับ datetime-local
$start_dt_val = !empty($crsh_data['CRSH_STARTDATE']) ? date('Y-m-d\TH:i', strtotime($crsh_data['CRSH_STARTDATE'])) : date('Y-m-d\TH:i');
$end_dt_val   = !empty($crsh_data['CRSH_ENDDATE']) ? date('Y-m-d\TH:i', strtotime($crsh_data['CRSH_ENDDATE'])) : date('Y-m-d\TH:i');

$selected_product_id_value = trim(($crsh_data['PRODUCT_ID'] ?? '') . '|' . ($crsh_data['PRODUCT_MODEL'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Update Drop Product</title>
    <?php include 'include/header.php';?>

    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-bottom: 20px; }
        .card-title-update { color: #0284c7; border-bottom: 2px solid #bae6fd; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-bottom: 20px; }

        .info-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { 
            font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 18px; 
            background-color: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #f1f5f9; min-height: 40px; display: flex; align-items: center; 
        }

        .form-control-custom {
            width: 100%; height: 40px; padding: 6px 12px; font-size: 14px; font-weight: 600; color: #0f172a;
            background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; margin-bottom: 18px;
        }
        .form-control-custom:focus { border-color: #2563eb; outline: 0; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
        .form-control-custom[readonly] { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; }

        .btn-back { background-color: #64748b; color: #fff; font-weight: 700; height: 42px; padding: 0 20px; border-radius: 8px; border: none; }
        .btn-save-update { background-color: #0284c7; color: #fff; font-weight: 700; height: 46px; padding: 0 28px; border-radius: 8px; border: none; }
        .btn-save-update:hover { background-color: #0369a1; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A4';?>
    <?php if (!empty($folder_func) && !empty($group_func)) { include 'include/'.$folder_func.'/navigation.php'; } ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="drop_product_result_mats.php?func=<?php echo $folder_func ?>">Drop Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    ✏️ Update Drop Product: <span style="color:#0284c7;"><?php echo htmlspecialchars($product_no); ?></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_result_page()">⬅️ Back</button>
                </div>
            </div>

            <?php if (!$crsh_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-danger" style="margin:0;">
                        No record found for Product No: <strong><?php echo htmlspecialchars($product_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- Group 1: Coil Details Header -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Coil Information (Coil No: <?php echo htmlspecialchars($crsh_data['COIL_NO']); ?>)</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($crsh_data['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY / TEMPER</div><div class="info-value"><?php echo htmlspecialchars(($crsh_data['ALLOY'] ?? '-').' / '.($crsh_data['TEMPER'] ?? '-')); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo number_format($coil_thickness, 3); ?> mm.</div></div>
                        <!-- เพิ่มคอลัมน์แสดง Coil Balance Weight -->
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCE WEIGHT</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo number_format($coil_balance_weight, 2); ?> Kg.</div></div>
                    </div>
                </div>

                <!-- UPDATE FORM -->
                <form id="frmUpdateDropCoil" name="frmUpdateDropCoil" method="POST" action="model/drop_coil_update_mats.php">
                    <input type="hidden" id="txtProductNo" name="txtProductNo" value="<?php echo htmlspecialchars($product_no); ?>" />
                    <input type="hidden" id="coil_no" name="coil_no" value="<?php echo htmlspecialchars($crsh_data['COIL_NO']); ?>" />
                    <input type="hidden" id="txtCOILThickness" name="txtCOILThickness" value="<?php echo $coil_thickness; ?>" />
                    <input type="hidden" id="dblAreaSize" name="dblAreaSize" value="<?php echo floatval($crsh_data['AREA_SIZE'] ?? 0); ?>" />
                    
                    <!-- Tracking Weights -->
                    <input type="hidden" id="dblCOILActualWeight" name="dblCOILActualWeight" value="<?php echo $coil_actual_weight; ?>" />
                    <input type="hidden" id="dblCOILProduceWeight" name="dblCOILProduceWeight" value="<?php echo $coil_produce_weight; ?>" />
                    <input type="hidden" id="dblOldProduceWeight" name="dblOldProduceWeight" value="<?php echo $old_produce_weight; ?>" />
                    <input type="hidden" id="txtCOILScrapWeight" name="txtCOILScrapWeight" value="<?php echo $coil_scrap_weight; ?>" />
                    <input type="hidden" id="txtCOILAdjustWeight" name="txtCOILAdjustWeight" value="<?php echo $coil_adjust_weight; ?>" />

                    <div class="dashboard-card">
                        <h4 class="card-title-update">✏️ Edit Drop Process Data</h4>
                        
                        <div class="row">
                            <!-- 1. Product ID & Product Model -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="cmbProductID">Product ID</label>
                                <select class="form-control-custom" id="cmbProductID" name="cmbProductID" onchange="onProductIDChange()">
                                    <option value="">-- Select Product ID --</option>
                                    <?php foreach ($product_master_list as $prod): 
                                        $val = htmlspecialchars($prod['PRODUCT_ID'] . '|' . $prod['PRODUCT_MODEL']);
                                        $label = htmlspecialchars($prod['PRODUCT_ID'] . ' : ' . $prod['PRODUCT_MODEL']);
                                        $selected = (trim($prod['PRODUCT_ID']) === trim($crsh_data['PRODUCT_ID'] ?? '')) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $val; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProductModel">Product Model</label>
                                <input type="text" class="form-control-custom" id="txtProductModel" name="txtProductModel" value="<?php echo htmlspecialchars($crsh_data['PRODUCT_MODEL'] ?? ''); ?>" readonly />
                            </div>

                            <!-- 2. Width & Length -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtWidth">Width (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtWidth" name="txtWidth" value="<?php echo number_format(floatval($crsh_data['WIDTH'] ?? 0), 3, '.', ''); ?>" onblur="onWidthBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtLength">Length (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtLength" name="txtLength" value="<?php echo number_format(floatval($crsh_data['LENGTH'] ?? 0), 3, '.', ''); ?>" onblur="onLengthBlur()" />
                            </div>

                            <!-- 3. Weight per Piece -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtWeightPiece">Weight per Piece (Kg)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtWeightPiece" name="txtWeightPiece" readonly value="<?php echo number_format(floatval($crsh_data['WEIGHT_PIECE'] ?? 0), 4, '.', ''); ?>" />
                            </div>

                            <!-- 4. Cut Sheet Width & Length -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCutWidth">Cut Sheet Width (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutWidth" name="txtCutWidth" value="<?php echo number_format(floatval($crsh_data['CUT_WIDTH'] ?? 0), 3, '.', ''); ?>" onblur="onCutWidthBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCutLength">Cut Sheet Length (mm)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutLength" name="txtCutLength" value="<?php echo number_format(floatval($crsh_data['CUT_LENGTH'] ?? 0), 3, '.', ''); ?>" onblur="onCutLengthBlur()" />
                            </div>

                            <!-- 5. Cut Sheet Weight per Piece -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtCutWeightPiece">Cut Sheet Weight per Piece (Kg)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtCutWeightPiece" name="txtCutWeightPiece" readonly value="<?php echo number_format(floatval($crsh_data['CUT_WEIGHTPIECE'] ?? 0), 4, '.', ''); ?>" />
                            </div>

                            <!-- 6. Produce Piece & Product Weight -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProducePiece">Produce Piece (Pc.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtProducePiece" name="txtProducePiece" value="<?php echo floatval($crsh_data['CRSH_ACTUALPIECE'] ?? 0); ?>" onblur="onProducePieceBlur()" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtProduceWeight">Product Weight (Kg.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtProduceWeight" name="txtProduceWeight" readonly value="<?php echo floatval($crsh_data['CRSH_ACTUALWEIGHT'] ?? 0); ?>" />
                            </div>

                            <!-- 7. Pallet Weight -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtBottomWeight">Pallet Weight (Kg.)</label>
                                <input type="number" step="any" class="form-control-custom" id="txtBottomWeight" name="txtBottomWeight" value="<?php echo floatval($crsh_data['CRSH_BOTTOMWEIGHT'] ?? 0); ?>" onblur="onBottomWeightBlur()" />
                            </div>

                            <hr style="width: 100%; border-top: 1px dashed #cbd5e1; margin: 10px 15px 20px 15px;" />

                            <!-- 8. Start Date & End Date -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="dtpStartDate">Start Date</label>
                                <input type="datetime-local" class="form-control-custom" id="dtpStartDate" name="dtpStartDate" value="<?php echo $start_dt_val; ?>" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="dtpEndDate">End Date</label>
                                <input type="datetime-local" class="form-control-custom" id="dtpEndDate" name="dtpEndDate" value="<?php echo $end_dt_val; ?>" />
                            </div>

                            <!-- 9. Job Reference -->
                            <div class="col-md-12 col-sm-12">
                                <label class="info-label" for="txtJobReference">Job Reference</label>
                                <input type="text" class="form-control-custom" id="txtJobReference" name="txtJobReference" value="<?php echo htmlspecialchars($crsh_data['JOB_REFERENCE'] ?? ''); ?>" />
                            </div>

                            <!-- 10. Work Process & Next Process -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="cmbWorkProcess">Work Process</label>
                                <input type="text" class="form-control-custom" id="cmbWorkProcess" name="cmbWorkProcess" value="<?php echo htmlspecialchars($crsh_data['CRSH_WORKPROCESS'] ?? 'CL>DP'); ?>" readonly />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtNextProcess">Next Process</label>
                                <input type="text" class="form-control-custom" id="txtNextProcess" name="txtNextProcess" value="<?php echo htmlspecialchars($crsh_data['CRSH_NEXTPROCESS'] ?? 'DP'); ?>" readonly />
                            </div>

                            <!-- 11. Coil Balance Weight Summary Tracker -->
                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCOILProduceWeight">Coil Total Produce Weight (Kg)</label>
                                <input type="text" class="form-control-custom" id="txtCOILProduceWeight" name="txtCOILProduceWeight" readonly value="<?php echo number_format($coil_produce_weight, 0); ?>" />
                            </div>

                            <div class="col-md-6 col-sm-6">
                                <label class="info-label" for="txtCOILBalanceWeight">Coil Balance Weight (Kg)</label>
                                <input type="text" class="form-control-custom" id="txtCOILBalanceWeight" name="txtCOILBalanceWeight" readonly value="<?php echo number_format($coil_actual_weight - ($coil_produce_weight + $coil_scrap_weight + $coil_adjust_weight), 0); ?>" />
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div style="text-align: right; margin-top: 15px;">
                            <button type="submit" class="btn btn-save-update">
                                💾 Update Drop Process
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
// AJAX Handler สำหรับบันทึกแก้ไข
$('#frmUpdateDropCoil').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: $(this).attr('action'),
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert("✅ " + response.message + "\nProduct No: " + response.product_no);
                window.location.href = response.redirect;
            } else {
                alert("❌ เกิดข้อผิดพลาด: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("❌ เกิดข้อผิดพลาดทางระบบ: " + error);
        }
    });
});

function back_result_page(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('drop_product_result_mats.php?func='+encodeURIComponent(data_fun)); 
}

function subStringComboBox(strVal) {
    if (!strVal) return "";
    var pos = strVal.indexOf("|");
    if (pos !== -1) {
        return strVal.substring(0, pos).trim();
    }
    return strVal.trim();
}

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

        fetch('drop_coil_process_mats.php?action=get_area_size&product_id=' + encodeURIComponent(subID) + '&product_model=' + encodeURIComponent(modelStr))
            .then(response => response.json())
            .then(data => {
                document.getElementById("dblAreaSize").value = data.area_size || 0;
                recalculateWeights();
            });
    }

    recalculateWeights();
}

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

function recalculateCutWeights() {
    var txtCutWidth = parseFloat(document.getElementById("txtCutWidth").value) || 0;
    var txtCutLength = parseFloat(document.getElementById("txtCutLength").value) || 0;
    var txtCOILThickness = parseFloat(document.getElementById("txtCOILThickness").value) || 0;
    var dblAreaSize = parseFloat(document.getElementById("dblAreaSize").value) || 0;

    var cutWPiece = weightPerPiece("SH", txtCutWidth, txtCutLength, txtCOILThickness, dblAreaSize);
    document.getElementById("txtCutWeightPiece").value = cutWPiece.toFixed(4);

    recalculateProduceWeights();
}

function onWidthBlur() {
    var txtWidthElem = document.getElementById("txtWidth");
    var dblWidth = parseFloat(txtWidthElem.value.trim()) || 0;
    txtWidthElem.value = dblWidth.toFixed(3);
    recalculateWeights();
}

function onLengthBlur() {
    var txtLengthElem = document.getElementById("txtLength");
    var dblLength = parseFloat(txtLengthElem.value.trim()) || 0;
    txtLengthElem.value = dblLength.toFixed(3);
    recalculateWeights();
}

function onCutWidthBlur() {
    var txtCutWidthElem = document.getElementById("txtCutWidth");
    var cutW = parseFloat(txtCutWidthElem.value.trim()) || 0;
    txtCutWidthElem.value = cutW.toFixed(3);
    recalculateCutWeights();
    onCutLengthBlur();
}

function onCutLengthBlur() {
    var txtCutLengthElem = document.getElementById("txtCutLength");
    var cutL = parseFloat(txtCutLengthElem.value.trim()) || 0;
    txtCutLengthElem.value = cutL.toFixed(3);
    recalculateCutWeights();
}

function onProducePieceBlur() {
    recalculateProduceWeights();
}

function recalculateProduceWeights() {
    var txtCutWeightPiece = parseFloat(document.getElementById("txtCutWeightPiece").value) || 0;
    var txtProducePiece = parseFloat(document.getElementById("txtProducePiece").value) || 0;
    var txtProduceWeightElem = document.getElementById("txtProduceWeight");

    var dblOldProduceWeight = parseFloat(document.getElementById("dblOldProduceWeight").value) || 0;
    var dblCOILActualWeight = parseFloat(document.getElementById("dblCOILActualWeight").value) || 0;
    var dblCOILProduceWeight = parseFloat(document.getElementById("dblCOILProduceWeight").value) || 0;
    var txtCOILScrapWeight = parseFloat(document.getElementById("txtCOILScrapWeight").value) || 0;
    var txtCOILAdjustWeight = parseFloat(document.getElementById("txtCOILAdjustWeight").value) || 0;

    var txtCOILProduceWeightElem = document.getElementById("txtCOILProduceWeight");
    var txtCOILBalanceWeightElem = document.getElementById("txtCOILBalanceWeight");

    if (txtCutWeightPiece === 0) {
        txtProduceWeightElem.value = "0";
    } else {
        var calculatedProdWeight = Math.round(txtProducePiece * txtCutWeightPiece);
        txtProduceWeightElem.value = calculatedProdWeight.toFixed(0);

        // ส่วนต่างน้ำหนักผลิตใหม่เทียบกับค่าเดิมในฐานข้อมูล
        var dblVarProduceWeight = calculatedProdWeight - dblOldProduceWeight;
        var baseProduceWeightWithoutThisDrop = dblCOILProduceWeight - dblOldProduceWeight;

        var newTotalCoilProduceWeight = baseProduceWeightWithoutThisDrop + calculatedProdWeight;
        var newCoilBalanceWeight = dblCOILActualWeight - (newTotalCoilProduceWeight + txtCOILScrapWeight + txtCOILAdjustWeight);

        if (newCoilBalanceWeight < 0) {
            alert("⚠️ น้ำหนักผลิตใหม่ทำให้น้ำหนักคงเหลือของคอยล์ติดลบ");
        } else {
            txtCOILProduceWeightElem.value = newTotalCoilProduceWeight.toFixed(0);
            txtCOILBalanceWeightElem.value = newCoilBalanceWeight.toFixed(0);
        }
    }
}

function onBottomWeightBlur() {
    var txtBottomWeightElem = document.getElementById("txtBottomWeight");
    var dblVal = parseFloat(txtBottomWeightElem.value.trim()) || 0;
    txtBottomWeightElem.value = dblVal.toFixed(0);
}
</script>
</html>