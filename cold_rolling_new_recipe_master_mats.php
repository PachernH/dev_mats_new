<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// 1. ดึงรายการ ALLOY จาก CMPSMSTR1
$alloy_options = array();
try {
    $al_sql = "SELECT DISTINCT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC";
    $al_stmt = $conn->prepare($al_sql);
    $al_stmt->execute();
    while ($al_row = $al_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($al_row['ALLOY'])) {
            $alloy_options[] = trim($al_row['ALLOY']);
        }
    }
} catch (PDOException $e) {}

// 2. ดึงรายการ TEMPER จาก TMPRMSTR1
$temper_options = array();
try {
    $t_sql = "SELECT TEMPER, DESCRIPTION FROM TMPRMSTR1 ORDER BY TEMPER ASC";
    $t_stmt = $conn->prepare($t_sql);
    $t_stmt->execute();
    while ($t_row = $t_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($t_row['TEMPER'])) {
            $temper_options[] = array(
                'temper' => trim($t_row['TEMPER']),
                'desc'   => trim($t_row['DESCRIPTION'] ?? '')
            );
        }
    }
} catch (PDOException $e) {}

// 3. ดึงรายการ ROLL SET จาก RDMTMSTR3 (เฉพาะ RSET_STATUS = 'OP')
$rset_options = array();
try {
    $rset_sql = "SELECT RSET_NO, RSET_REFERENCE, WRL_RADIUS, BRL_RADIUS, TWR_CAMBER, BWR_CAMBER 
                 FROM RDMTMSTR3 
                 WHERE RSET_STATUS = 'OP' 
                 ORDER BY RSET_NO DESC";
    $rset_stmt = $conn->prepare($rset_sql);
    $rset_stmt->execute();
    while ($r_row = $rset_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($r_row['RSET_NO'])) {
            $rset_options[] = array(
                'rset_no'    => trim($r_row['RSET_NO']),
                'ref'        => trim($r_row['RSET_REFERENCE'] ?? ''),
                'wrl_radius' => $r_row['WRL_RADIUS'],
                'brl_radius' => $r_row['BRL_RADIUS'],
                'twr_camber' => $r_row['TWR_CAMBER'],
                'bwr_camber' => $r_row['BWR_CAMBER']
            );
        }
    }
} catch (PDOException $e) {}
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
        
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .info-label { font-weight: 600; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }

        .recipe-display-box {
            background-color: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 16px;
            font-weight: 700;
            color: #2563eb;
            min-height: 42px;
            display: flex;
            align-items: center;
        }

        .table-responsive-custom {
            width: 100%;
            overflow-x: auto !important;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
        }
        .table-detail {
            width: 100%;
            min-width: 1350px;
            margin-bottom: 0 !important;
        }
        .table-detail th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-align: center;
            vertical-align: middle !important;
            white-space: nowrap;
            padding: 10px 8px !important;
        }
        .table-detail td {
            vertical-align: middle !important;
            padding: 6px !important;
        }
        .table-detail .form-control {
            height: 38px;
            font-size: 13px;
            border-radius: 6px;
        }
        .btn-del-row {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 600;
        }
        .btn-del-row:hover { background-color: #fca5a5; color: #7f1d1d; }
        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover { background-color: #f1f5f9; color: #1e293b; }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'gp3'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">New Cold Rolling Recipe Master</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ กลับไปหน้าหลัก
                    </button>
                </div>
            </div>
            
            <form id="form_new_recipe" method="POST" onsubmit="return false;">

            <!-- การ์ด 1: ข้อมูลหลัก Header Keys (สร้าง RECIPE_NO อัตโนมัติ และเลือก ROLL SET) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">➕ Recipe Header Parameters</h4>
                        
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">ALLOY <span class="text-danger">*</span></div>
                                <select class="form-control key-input" name="alloy" id="alloy" required>
                                    <option value="">-- Alloy --</option>
                                    <?php foreach ($alloy_options as $al): ?>
                                        <option value="<?php echo htmlspecialchars($al, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($al, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">ROLL SET No <span class="text-danger">*</span></div>
                                <select class="form-control" name="rset_no" id="rset_no" required>
                                    <option value="">-- Roll Set --</option>
                                    <?php foreach ($rset_options as $index => $rs): ?>
                                        <option value="<?php echo htmlspecialchars($rs['rset_no'], ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo ($index === 0) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rs['rset_no'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="info-label">WIDTH <span class="text-danger">*</span></div>
                                <input type="number" step="0.01" class="form-control key-input" name="width" id="width" placeholder="1300" required>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="info-label">THICKNESS ORIGINAL <span class="text-danger">*</span></div>
                                <input type="number" step="0.001" class="form-control key-input" name="thickness_original" id="thickness_original" placeholder="4.000" required>
                            </div>

                            <div class="col-md-2 col-sm-6">
                                <div class="info-label">THICKNESS FINAL <span class="text-danger">*</span></div>
                                <input type="number" step="0.001" class="form-control key-input" name="thickness_final" id="thickness_final" placeholder="0.800" required>
                            </div>
                        </div>

                        <!-- แสดงผล RECIPE_NO อัตโนมัติ -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">GENERATED RECIPE NO (ALLOY:WIDTH-THICKNESS ORIGINAL>THICKNESS FINAL)</div>
                                <div class="recipe-display-box" id="recipe_no_preview">-- กรุณากรอกข้อมูล Header ให้ครบถ้วน --</div>
                                <input type="hidden" name="recipe_no" id="recipe_no" value="">
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- การ์ด 2: ตารางรายการลูก (Recipe Items Breakdown) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 class="card-title-sub" style="margin: 0; border: none;">📊 Recipe Items Breakdown</h4>
                            <button type="button" class="btn btn-sm btn-info" style="border-radius: 6px; font-weight:600;" onclick="addRow()">➕ Add Item (Add Row)</button>
                        </div>
                        
                        <div class="table-responsive-custom">
                            <table class="table table-bordered table-detail" id="tbl_recipe_items">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ITEM <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">TEMPER FIN. (TMPRMSTR1)</th>
                                        <th style="width: 120px;">THICKNESS ENTRY</th>
                                        <th style="width: 120px;">THICKNESS EXIT</th>
                                        <th style="width: 120px;">TOLERANCE</th>
                                        <th style="width: 120px;">WORK HARD. 1</th>
                                        <th style="width: 120px;">WORK HARD. 2</th>
                                        <th style="width: 120px;">COEFF. FRICTION</th>
                                        <th style="width: 120px;">YIELD STRESS</th>
                                        <th style="width: 120px;">EMPIRICAL VAL 1</th>
                                        <th style="width: 70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="number" class="form-control text-center recipe-item-input" name="recipe_item[]" value="1" required min="1">
                                        </td>
                                        <td>
                                            <select class="form-control" name="temper_finish[]">
                                                <option value="">-- Temper --</option>
                                                <?php foreach ($temper_options as $top): ?>
                                                    <option value="<?php echo htmlspecialchars($top['temper'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($top['temper'] . ($top['desc'] !== '' ? ' : ' . $top['desc'] : ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="thickness_entry[]" value="0.00"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="thickness_exit[]" value="0.00"></td>
                                        <td><input type="number" class="form-control text-center" name="thickness_telorance[]" value="0"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="work_hardening1[]" value="0.00"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="work_hardening2[]" value="0.00"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="coefficient_friction[]" value="0.00"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="yield_stress[]" value="0.00"></td>
                                        <td><input type="number" step="0.01" class="form-control text-right" name="empirical_value1[]" value="0.00"></td>
                                        <td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">Delete</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 20px 0 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('cold_rolling_recipe_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="width: 180px; height: 42px; border-radius: 8px; background-color: #2563eb; border: none; color: white; font-weight: 600;" onclick="submit_new_recipe_data()">💾 Save Data</button>
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
var temperOptionHtml = '<option value="">-- Temper --</option>';
<?php foreach ($temper_options as $top): ?>
    temperOptionHtml += '<option value="<?php echo addslashes($top['temper']); ?>"><?php echo addslashes($top['temper'] . ($top['desc'] !== '' ? ' : ' . $top['desc'] : '')); ?></option>';
<?php endforeach; ?>

// ฟังก์ชันสร้าง RECIPE_NO อัตโนมัติ: ALLOY:WIDTH-THICKNESS ORIGINAL>THICKNESS FINAL
function generateRecipeNo() {
    var al   = $("#alloy").val().trim();
    var wi   = $("#width").val().trim();
    var thor = $("#thickness_original").val().trim();
    var thfn = $("#thickness_final").val().trim();

    if (al !== "" && wi !== "" && thor !== "" && thfn !== "") {
        // แปลงความหนาให้อยู่ในทศนิยม 3 ตำแหน่ง เช่น 4 -> 4.000
        var f_thor = parseFloat(thor).toFixed(3);
        var f_thfn = parseFloat(thfn).toFixed(3);
        
        var recipeNo = al + ":" + wi + "-" + f_thor + ">" + f_thfn;
        
        $("#recipe_no_preview").text(recipeNo).css("color", "#2563eb");
        $("#recipe_no").val(recipeNo);
    } else {
        $("#recipe_no_preview").text("-- Please fill in all the header information --").css("color", "#94a3b8");
        $("#recipe_no").val("");
    }
}

$(document).ready(function() {
    // ผูก Event ให้คำนวณ RECIPE_NO แบบเรียลไทม์
    $(".key-input").on("input change blur", function() {
        generateRecipeNo();
    });
});

function getNextItemNo() {
    var maxVal = 0;
    $(".recipe-item-input").each(function() {
        var val = parseInt($(this).val());
        if (!isNaN(val) && val > maxVal) {
            maxVal = val;
        }
    });
    return maxVal + 1;
}

function addRow() {
    var nextItem = getNextItemNo();
    var tr = '<tr>' +
        '<td><input type="number" class="form-control text-center recipe-item-input" name="recipe_item[]" value="' + nextItem + '" required min="1"></td>' +
        '<td><select class="form-control" name="temper_finish[]">' + temperOptionHtml + '</select></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="thickness_entry[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="thickness_exit[]" value="0.00"></td>' +
        '<td><input type="number" class="form-control text-center" name="thickness_telorance[]" value="0"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="work_hardening1[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="work_hardening2[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="coefficient_friction[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="yield_stress[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="empirical_value1[]" value="0.00"></td>' +
        '<td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">Delete</button></td>' +
        '</tr>';
    $("#tbl_recipe_items tbody").append(tr);
}

function removeRow(btn) {
    if ($("#tbl_recipe_items tbody tr").length > 1) {
        $(btn).closest('tr').remove();
    } else {
        alert("There must be at least one item listed");
    }
}

function submit_new_recipe_data() {
    var recipe_no = $("#recipe_no").val().trim();
    var rset_no   = $("#rset_no").val().trim();
    var data_fun  = $("#func").val();

    if (recipe_no === "") {
        alert("Please fill in the information Header (ALLOY, WIDTH, THICKNESS ORIGINAL, THICKNESS FINAL) Recipe No.");
        return false;
    }

    if (rset_no === "") {
        alert("Please Select Roll Set (RSET_NO)");
        return false;
    }

    var formData = $("#form_new_recipe").serialize();

    $.ajax({
        url: "model/insert_recipe_master_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if (resp.status === "success" || resp.message === true) {
                alert("Create Cold Rolling Recipe Complete");
                window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("Error : " + (resp.error || "can not save"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while connecting to the server.: " + error);
        }
    });
}
</script>
</html>