<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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
        .form-control[readonly] { background-color: #f1f5f9; cursor: not-allowed; font-weight: bold; color: #1e293b; }
        .mb-4 { margin-bottom: 1.5rem; }
        .dim-input { display: inline-block; width: 31% !important; margin-right: 1.5%; }
        .dim-input:last-child { margin-right: 0; }
        
        /* สไตล์ส่วนแสดงผลขนาดเทียบเท่า */
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
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">New Work Process Master</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('material_package_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ Back</button>
                </div>
            </div>
            
            <form id="form_package_process" method="POST" onsubmit="return false;">

            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">➕ Material Package Master Data</h4>
                        <div class="row">        
                            
                            <div class="col-md-6">
                                <div class="info-label">Package Type <span class="text-danger">*</span></div>
                                <select class="form-control" name="pkg_type" id="pkg_type" onchange="generateMaterialCode()">
                                    <option value="">-- Package Type --</option>
                                    <option value="BT">BT : Bottom</option>
                                    <option value="TP">TP : TOP</option>
                                    <option value="BOTH" selected>BOTH : Both (Top & Bottom)</option>
                                </select>                                
                            </div> 
                                 
                            <div class="col-md-6">
                                <div class="info-label">Treatment <span class="text-danger">*</span></div>
                                <select class="form-control" name="treatment" id="treatment" onchange="generateMaterialCode()">
                                    <option value="">-- Treatment --</option>
                                    <option value="NL">NL : Normal</option>
                                    <option value="HT">HT : Heat Treatment</option>
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
                                    <input type="number" step="any" class="form-control dim-input" name="dim_w" id="dim_w" placeholder="W" oninput="generateMaterialCode()" autocomplete="off">
                                    <input type="number" step="any" class="form-control dim-input" name="dim_l" id="dim_l" placeholder="L" oninput="generateMaterialCode()" autocomplete="off">
                                    <input type="number" step="any" class="form-control dim-input" name="dim_h" id="dim_h" placeholder="H" oninput="generateMaterialCode()" autocomplete="off">
                                </div>
                                <div id="mm_display_info" class="mm-badge-large">📏 ขนาดเทียบเท่า: -</div>
                            </div>

                            <!-- ส่วนเพิ่ม: กำหนด Percentage plus/minus (%) ของ W และ L -->
                            <div class="col-md-3">
                                <div class="info-label">Width Tolerance (W +/- %)</div>
                                <div class="input-group" style="margin-bottom: 15px;">
                                    <input type="number" step="any" class="form-control" name="dim_w_tol" id="dim_w_tol" value="5.00" oninput="generateMaterialCode()" placeholder="5.00" autocomplete="off" style="margin-bottom: 0;">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">Length Tolerance (L +/- %)</div>
                                <div class="input-group" style="margin-bottom: 15px;">
                                    <input type="number" step="any" class="form-control" name="dim_l_tol" id="dim_l_tol" value="5.00" oninput="generateMaterialCode()" placeholder="5.00" autocomplete="off" style="margin-bottom: 0;">
                                    <span class="input-group-addon">%</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Material Package Weight (Kg)</div>
                                <input type="number" step="any" class="form-control" name="pkg_weight" id="pkg_weight" placeholder="ระบุน้ำหนัก (Kg)" autocomplete="off">
                            </div>

                            <div class="col-md-12"><hr style="margin: 10px 0 20px 0; border-top: 1px dashed #cbd5e1;"></div>                            

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL CODE (TOP) <span class="text-danger">*</span></div>
                                <input type="text" class="form-control" name="ma_code_top" id="ma_code_top" placeholder="รหัสสร้างให้อัตโนมัติ (เช่น T12X50-HT-1)" readonly autocomplete="off">
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL CODE (BOTTOM) <span class="text-danger">*</span></div>
                                <input type="text" class="form-control" name="ma_code_bottom" id="ma_code_bottom" placeholder="รหัสสร้างให้อัตโนมัติ (เช่น B12X50-HT-1)" readonly autocomplete="off">
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">ITEMNUM MP2 <span class="text-danger">*</span></div>
                                <select class="form-control" name="ma_it" id="ma_it" onchange="autoFillDescription()">
                                    <option value="" data-desc="">-- ITEMNUM MP2 --</option>
                                    <?php
                                        include('dbcon_mp2_mats.php');
                                        $sql = "SELECT ITEMNUM, DESCRIPTION FROM INVY WHERE (ITEMNUM LIKE 'P-P-PW-%' OR ITEMNUM LIKE 'P-P-RB-%' OR ITEMNUM LIKE 'P-P-WO-%')";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $itemnum = htmlspecialchars($row["ITEMNUM"], ENT_QUOTES, 'UTF-8');
                                            $desc    = htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8');
                                            echo "<option value='{$itemnum}' data-desc='{$desc}'>{$itemnum} : {$desc}</option>";
                                        }
                                    ?>
                                </select>
                            </div>    

                            <div class="col-md-6">
                                <div class="info-label">DESCRIPTION</div>
                                <input type="text" class="form-control" name="ma_dec" id="ma_dec" placeholder="ดึงข้อมูลอัตโนมัติจาก ITEMNUM MP2" readonly autocomplete="off">
                            </div>   

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL TYPE</div>
                                <input type="text" class="form-control" name="ma_ty" id="ma_ty" value="PK" readonly autocomplete="off">
                            </div>  
                            
                            <div class="col-md-6">
                                <div class="info-label">QTY ONHAND</div>
                                <input type="number" step="any" class="form-control" name="ma_qty" id="ma_qty" placeholder="ระบุจำนวน QTY ONHAND" autocomplete="off">
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
                    <button type="button" class="btn btn-primary" style="width: 180px; height: 42px; border-radius: 8px; background-color: #2563eb; border: none; color: white; font-weight: 600;" onclick="submit_package_data()">💾 Save Data</button>
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
    var pkgType   = $("#pkg_type").val();      
    var treatment = $("#treatment").val();     
    var unit      = $("#unit_measure").val();  
    var raw_w     = parseFloat($("#dim_w").val()) || 0;
    var raw_l     = parseFloat($("#dim_l").val()) || 0;
    var raw_h     = parseFloat($("#dim_h").val()) || 0;

    var w_tol     = parseFloat($("#dim_w_tol").val()) || 0;
    var l_tol     = parseFloat($("#dim_l_tol").val()) || 0;

    var mm_w = 0, mm_l = 0, mm_h = 0;
    var inch_w = 0, inch_l = 0, inch_h = 0;
    var code_w = 0, code_l = 0;

    if (unit === "inch") {
        code_w = Math.round(raw_w);
        code_l = Math.round(raw_l);

        mm_w = (raw_w * 25.4).toFixed(1);
        mm_l = (raw_l * 25.4).toFixed(1);
        mm_h = (raw_h * 25.4).toFixed(1);

        // คำนวณขอบเขต Min - Max รวม Tolerance (%)
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
        code_w = Math.round(raw_w);
        code_l = Math.round(raw_l);

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

    if (!treatment || raw_w === 0 || raw_l === 0) {
        $("#ma_code_top").val("");
        $("#ma_code_bottom").val("");
        return;
    }

    var autoCount = "1";
    var dimStr = code_w + "X" + code_l;

    var codeTop = "T" + dimStr + "-" + treatment + "-" + autoCount;
    var codeBottom = "B" + dimStr + "-" + treatment + "-" + autoCount;

    if (pkgType === "TP") {
        $("#ma_code_top").val(codeTop);
        $("#ma_code_bottom").val("");
    } else if (pkgType === "BT") {
        $("#ma_code_top").val("");
        $("#ma_code_bottom").val(codeBottom);
    } else {
        $("#ma_code_top").val(codeTop);
        $("#ma_code_bottom").val(codeBottom);
    }
}

function autoFillDescription() {
    var selectedOption = $("#ma_it option:selected");
    var desc = selectedOption.data("desc") || "";
    $("#ma_dec").val(desc);
}

function submit_package_data() {
    var code_top    = $("#ma_code_top").val().trim();
    var code_bottom = $("#ma_code_bottom").val().trim();
    var ma_it       = $("#ma_it").val().trim();
    var data_fun    = $("#func").val();

    if ((code_top === "" && code_bottom === "") || ma_it === "") {
        alert("Please fill in the information to create a MATERIAL CODE and select ITEMNUM MP2 completely.");
        return false;
    }

    var formData = $("#form_package_process").serialize();

    $.ajax({
        url: "model/insert_package_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success" || resp.message === true){
                alert("New package information has been successfully added.");
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