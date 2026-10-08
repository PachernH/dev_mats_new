<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';
include 'dbcon_mp2_mats.php';

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['IDM']) ? trim($_GET['IDM']) : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// ดึงข้อมูลจากฟังก์ชัน RT_Material_Package
$data_cs = array();
if ($cs !== '') {
    $data_cs = RT_Material_Package($cs);
}

// กำหนดตัวแปรจาก DB
$val_ma_code = isset($data_cs['MATERIAL_CODE']) ? $data_cs['MATERIAL_CODE'] : (isset($data_cs[0]) ? $data_cs[0] : '');
$val_ma_it   = isset($data_cs['ITEMNUM']) ? $data_cs['ITEMNUM'] : (isset($data_cs[1]) ? $data_cs[1] : '');
$val_ma_dec  = isset($data_cs['DESCRIPTION']) ? $data_cs['DESCRIPTION'] : (isset($data_cs[2]) ? $data_cs[2] : '');
$val_ma_ty   = isset($data_cs['MATERIAL_TYPE']) && !empty($data_cs['MATERIAL_TYPE']) ? $data_cs['MATERIAL_TYPE'] : 'PK';
$val_ma_qty  = isset($data_cs['QTY_ONHAND']) ? $data_cs['QTY_ONHAND'] : (isset($data_cs[4]) ? $data_cs[4] : '');

$val_pkg_type  = isset($data_cs['PACKAGE_TYPE']) ? $data_cs['PACKAGE_TYPE'] : 'TP';
$val_treatment = isset($data_cs['PACKAGE_TREATMENT']) ? $data_cs['PACKAGE_TREATMENT'] : 'NL';

$val_dim_w     = isset($data_cs['WIDTH_INCH']) ? $data_cs['WIDTH_INCH'] : 0;
$val_dim_l     = isset($data_cs['LENGTH_INCH']) ? $data_cs['LENGTH_INCH'] : 0;
$val_dim_h     = isset($data_cs['HIGH_INCH']) ? $data_cs['HIGH_INCH'] : 0;

$val_w_tol     = isset($data_cs['WIDTH_PLUS']) ? $data_cs['WIDTH_PLUS'] : 5;
$val_l_tol     = isset($data_cs['LENGTH_PLUS']) ? $data_cs['LENGTH_PLUS'] : 5;

$val_pkg_weight = isset($data_cs['PACKAGE_WEIGHT']) ? $data_cs['PACKAGE_WEIGHT'] : 0;
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        .display-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .info-label { font-weight: 600; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; margin-bottom: 15px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        .form-control[readonly] { background-color: #f1f5f9 !important; cursor: not-allowed; font-weight: bold; color: #1e293b; }
        .mb-4 { margin-bottom: 1.5rem; }
        .dim-input { display: inline-block; width: 31% !important; margin-right: 1.5%; }
        .dim-input:last-child { margin-right: 0; }
        
        .mm-badge-large { 
            font-size: 15px; 
            color: #1e3a8a; 
            font-weight: 700; 
            background-color: #eff6ff;
            border: 1.5px solid #bfdbfe;
            padding: 8px 14px;
            border-radius: 8px;
            margin-top: 5px;
            margin-bottom: 15px; 
            display: block; 
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp4';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Material Package Master Data Details</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('material_package_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ Back</button>
                </div>
            </div>
            
            <form id="form_work_process" method="POST" onsubmit="return false;">

            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">✏️ Edit Material Package Master Data</h4>
                        <div class="row">        

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL CODE <span class="text-danger">*</span></div>
                                <input type="text" class="form-control" name="ma_code" id="ma_code" value="<?php echo htmlspecialchars($val_ma_code, ENT_QUOTES, 'UTF-8'); ?>" readonly required autocomplete="off">
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">Package Type <span class="text-danger">*</span></div>
                                <select class="form-control" name="pkg_type" id="pkg_type" onchange="generateMaterialCode()">
                                    <option value="TP" <?php echo ($val_pkg_type == 'TP') ? 'selected' : ''; ?>>TP : TOP</option>
                                    <option value="BT" <?php echo ($val_pkg_type == 'BT') ? 'selected' : ''; ?>>BT : Bottom</option>
                                </select>                                
                            </div> 
                                 
                            <div class="col-md-3">
                                <div class="info-label">Treatment <span class="text-danger">*</span></div>
                                <select class="form-control" name="treatment" id="treatment" onchange="generateMaterialCode()">
                                    <option value="NL" <?php echo ($val_treatment == 'NL') ? 'selected' : ''; ?>>NL : Normal</option>
                                    <option value="HT" <?php echo ($val_treatment == 'HT') ? 'selected' : ''; ?>>HT : Heat Treatment</option>
                                </select>                                
                            </div>                            

                            <div class="col-md-6">
                                <div class="info-label">Units of measurement <span class="text-danger">*</span></div>
                                <select class="form-control" name="unit_measure" id="unit_measure" onchange="onUnitChange()">
                                    <option value="inch" selected>Inch (นิ้ว)</option>
                                    <option value="mm">mm (มิลลิเมตร)</option>
                                </select>                                
                            </div>   

                            <div class="col-md-6">
                                <div class="info-label">Dimension (W - L - H) <span class="text-danger">*</span></div>
                                <div>
                                    <input type="number" step="any" class="form-control dim-input" name="dim_w" id="dim_w" value="<?php echo htmlspecialchars($val_dim_w, ENT_QUOTES, 'UTF-8'); ?>" placeholder="W" oninput="generateMaterialCode()" autocomplete="off">
                                    <input type="number" step="any" class="form-control dim-input" name="dim_l" id="dim_l" value="<?php echo htmlspecialchars($val_dim_l, ENT_QUOTES, 'UTF-8'); ?>" placeholder="L" oninput="generateMaterialCode()" autocomplete="off">
                                    <input type="number" step="any" class="form-control dim-input" name="dim_h" id="dim_h" value="<?php echo htmlspecialchars($val_dim_h, ENT_QUOTES, 'UTF-8'); ?>" placeholder="H" oninput="generateMaterialCode()" autocomplete="off">
                                </div>
                                <div id="mm_display_info" class="mm-badge-large">📏 ขนาดเทียบเท่า: -</div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">Width Tolerance (W +/- %)</div>
                                <div class="input-group" style="margin-bottom: 15px;">
                                    <input type="number" step="any" class="form-control" name="dim_w_tol" id="dim_w_tol" value="<?php echo htmlspecialchars($val_w_tol, ENT_QUOTES, 'UTF-8'); ?>" oninput="generateMaterialCode()" placeholder="5.00" autocomplete="off" style="margin-bottom: 0;">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">Length Tolerance (L +/- %)</div>
                                <div class="input-group" style="margin-bottom: 15px;">
                                    <input type="number" step="any" class="form-control" name="dim_l_tol" id="dim_l_tol" value="<?php echo htmlspecialchars($val_l_tol, ENT_QUOTES, 'UTF-8'); ?>" oninput="generateMaterialCode()" placeholder="5.00" autocomplete="off" style="margin-bottom: 0;">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Material Package Weight (Kg)</div>
                                <input type="number" step="any" class="form-control" name="pkg_weight" id="pkg_weight" value="<?php echo htmlspecialchars($val_pkg_weight, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ระบุน้ำหนัก (Kg)" autocomplete="off">
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">ITEMNUM MP2 <span class="text-danger">*</span></div>
                                <select class="form-control" name="ma_it" id="ma_it" onchange="autoFillDescription()">
                                    <option value="" data-desc="">-- เลือก ITEMNUM MP2 --</option>
                                    <?php
                                        include('dbcon_mp2_mats.php');
                                        $sql = "SELECT ITEMNUM, DESCRIPTION FROM INVY WHERE (ITEMNUM LIKE 'P-P-PW-%' OR ITEMNUM LIKE 'P-P-RB-%' OR ITEMNUM LIKE 'P-P-WO-%')";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $itemnum = htmlspecialchars($row["ITEMNUM"], ENT_QUOTES, 'UTF-8');
                                            $desc = htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8');
                                            $selected = ($row["ITEMNUM"] === $val_ma_it) ? "selected" : "";
                                            echo "<option value='{$itemnum}' data-desc='{$desc}' {$selected}>{$itemnum} : {$desc}</option>";
                                        }
                                    ?>
                                </select>
                            </div>    

                            <div class="col-md-6">
                                <div class="info-label">DESCRIPTION</div>
                                <input type="text" class="form-control" name="ma_dec" id="ma_dec" value="<?php echo htmlspecialchars($val_ma_dec, ENT_QUOTES, 'UTF-8'); ?>" readonly autocomplete="off">
                            </div>   

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL TYPE</div>
                                <input type="text" class="form-control" name="ma_ty" id="ma_ty" value="<?php echo htmlspecialchars($val_ma_ty, ENT_QUOTES, 'UTF-8'); ?>" readonly autocomplete="off">
                            </div>  
                            
                            <div class="col-md-6">
                                <div class="info-label">QTY ONHAND</div>
                                <input type="number" step="any" class="form-control" name="ma_qty" id="ma_qty" value="<?php echo htmlspecialchars($val_ma_qty, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ระบุ QTY ONHAND" autocomplete="off">
                            </div>                              

                        </div>
                    </div>
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 20px 0 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('material_package_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_work_process_data()">💾 Save Data</button>
                </div>
            </div><br>
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">

var previousUnit = "inch";

$(document).ready(function() {
    generateMaterialCode();
});

function onUnitChange() {
    var currentUnit = $("#unit_measure").val();
    var w = parseFloat($("#dim_w").val()) || 0;
    var l = parseFloat($("#dim_l").val()) || 0;
    var h = parseFloat($("#dim_h").val()) || 0;

    if (w > 0 || l > 0 || h > 0) {
        if (previousUnit === "inch" && currentUnit === "mm") {
            if (w > 0) $("#dim_w").val(Math.round(w * 25.4));
            if (l > 0) $("#dim_l").val(Math.round(l * 25.4));
            if (h > 0) $("#dim_h").val(Math.round(h * 25.4));
        } else if (previousUnit === "mm" && currentUnit === "inch") {
            if (w > 0) $("#dim_w").val(Math.round(w / 25.4));
            if (l > 0) $("#dim_l").val(Math.round(l / 25.4));
            if (h > 0) $("#dim_h").val(Math.round(h / 25.4));
        }
    }

    previousUnit = currentUnit;
    generateMaterialCode();
}

function generateMaterialCode() {
    var unit      = $("#unit_measure").val();  
    var raw_w     = parseFloat($("#dim_w").val()) || 0;
    var raw_l     = parseFloat($("#dim_l").val()) || 0;
    var raw_h     = parseFloat($("#dim_h").val()) || 0;

    var w_tol     = parseFloat($("#dim_w_tol").val()) || 0;
    var l_tol     = parseFloat($("#dim_l_tol").val()) || 0;

    var mm_w = 0, mm_l = 0, mm_h = 0;
    var inch_w = 0, inch_l = 0, inch_h = 0;

    if (unit === "inch") {
        mm_w = (raw_w * 25.4).toFixed(1);
        mm_l = (raw_l * 25.4).toFixed(1);
        mm_h = (raw_h * 25.4).toFixed(1);

        var w_min = (raw_w * (1 - w_tol / 100)).toFixed(2);
        var w_max = (raw_w * (1 + w_tol / 100)).toFixed(2);
        var l_min = (raw_l * (1 - l_tol / 100)).toFixed(2);
        var l_max = (raw_l * (1 + l_tol / 100)).toFixed(2);

        if (raw_w > 0 || raw_l > 0 || raw_h > 0) {
            $("#mm_display_info").html(
                "📏 Equivalent: <b>" + mm_w + " x " + mm_l + " x " + mm_h + " mm</b><br>" +
                "<small style='font-weight:400; color:#475569;'>" +
                "Acceptance period W (" + w_tol + "%): " + w_min + " ~ " + w_max + " in | " +
                "L (" + l_tol + "%): " + l_min + " ~ " + l_max + " in</small>"
            );
        } else {
            $("#mm_display_info").html("📏 Equivalent size: -");
        }
    } else {
        inch_w = (raw_w / 25.4).toFixed(1);
        inch_l = (raw_l / 25.4).toFixed(1);
        inch_h = (raw_h / 25.4).toFixed(1);

        var w_min_mm = (raw_w * (1 - w_tol / 100)).toFixed(1);
        var w_max_mm = (raw_w * (1 + w_tol / 100)).toFixed(1);
        var l_min_mm = (raw_l * (1 - l_tol / 100)).toFixed(1);
        var l_max_mm = (raw_l * (1 + l_tol / 100)).toFixed(1);

        if (raw_w > 0 || raw_l > 0 || raw_h > 0) {
            $("#mm_display_info").html(
                "📏 Equivalent: <b>" + inch_w + " x " + inch_l + " x " + inch_h + " inch</b><br>" +
                "<small style='font-weight:400; color:#475569;'>" +
                "Acceptance period W (" + w_tol + "%): " + w_min_mm + " ~ " + w_max_mm + " mm | " +
                "L (" + l_tol + "%): " + l_min_mm + " ~ " + l_max_mm + " mm</small>"
            );
        } else {
            $("#mm_display_info").html("📏 Equivalent size: -");
        }
    }
}

function autoFillDescription() {
    var selectedOption = $("#ma_it option:selected");
    var desc = selectedOption.data("desc") || "";
    $("#ma_dec").val(desc);
}

function submit_work_process_data() {
    var ma_code = $("#ma_code").val().trim();
    var ma_it   = $("#ma_it").val().trim();
    var data_fun = $("#func").val();

    if (ma_code === "" || ma_it === "") {
        alert("Please fill in all required fields (*).");
        return false;
    }

    var formData = $("#form_work_process").serialize();

    $.ajax({
        url: "model/update_material_package_master_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success" || resp.message === true){
                alert("The edited information has been successfully.");
                window.location.assign('material_package_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "The data could not be saved."));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred during data transmission: " + error);
        }
    });
}
</script>
</html>