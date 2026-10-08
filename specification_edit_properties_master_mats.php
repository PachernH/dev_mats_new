<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า Composite Keys จากการกด Edit ตารางหน้าหลัก
$g_alloy      = isset($_GET['ALLOY']) ? trim($_GET['ALLOY']) : '';
$g_temper     = isset($_GET['TEMPER_TARGET']) ? trim($_GET['TEMPER_TARGET']) : '';
$g_thicknessf = isset($_GET['THICKNESS_FROM']) ? trim($_GET['THICKNESS_FROM']) : '';
$g_thicknesst = isset($_GET['THICKNESS_TO']) ? trim($_GET['THICKNESS_TO']) : '';

$data_com = array();
if ($g_alloy != '') {
    // ส่งค่าไปค้นหาตรงๆ ตามที่รับมาจากหน้าหลัก
    $data_com = RT_properties_Spec($g_alloy, $g_temper, $g_thicknessf, $g_thicknesst);
}

// ผูกค่าสำหรับการแสดงผลและส่งค่ากลับ (Composite Keys คงรูปแบบ Text จาก Database)
$v_alloy       = isset($data_com[0]) ? htmlspecialchars(trim($data_com[0]), ENT_QUOTES, 'UTF-8') : $g_alloy;
$v_temper      = isset($data_com[1]) ? htmlspecialchars(trim($data_com[1]), ENT_QUOTES, 'UTF-8') : $g_temper;
$v_thicknessf  = isset($data_com[2]) ? htmlspecialchars(trim($data_com[2]), ENT_QUOTES, 'UTF-8') : $g_thicknessf;
$v_thicknesst  = isset($data_com[3]) ? htmlspecialchars(trim($data_com[3]), ENT_QUOTES, 'UTF-8') : $g_thicknesst;

// ฟังก์ชันสำหรับแปลงกลุ่มตัวเลข Mechanical Properties (ประเภท real) ให้แสดงทศนิยม 1 ตำแหน่งตามรูป data.jpg
function formatToRealData($value) {
    if ($value === '' || $value === null || !is_numeric($value)) {
        return '';
    }
    return number_format((float)$value, 1, '.', '');
}

// จับคู่สเปกเชิงกล (Index 4 - 13) และปรับแสดงผลทศนิยม 1 ตำแหน่ง
$v_uts_i_f  = isset($data_com[4]) ? formatToRealData($data_com[4]) : '';
$v_uts_i_t  = isset($data_com[5]) ? formatToRealData($data_com[5]) : '';
$v_uts_k_f  = isset($data_com[6]) ? formatToRealData($data_com[6]) : '';
$v_uts_k_t  = isset($data_com[7]) ? formatToRealData($data_com[7]) : '';
$v_yis_i_f  = isset($data_com[8]) ? formatToRealData($data_com[8]) : '';
$v_yis_i_t  = isset($data_com[9]) ? formatToRealData($data_com[9]) : '';
$v_yis_k_f  = isset($data_com[10]) ? formatToRealData($data_com[10]) : '';
$v_yis_k_t  = isset($data_com[11]) ? formatToRealData($data_com[11]) : '';
$v_el_i_f   = isset($data_com[12]) ? formatToRealData($data_com[12]) : '';
$v_el_i_t   = isset($data_com[13]) ? formatToRealData($data_com[13]) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body {  font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f4f7f6; }
        .card { border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card .header { padding: 20px 20px 0 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 20px; padding-left: 5px; border-left: 4px solid #3498db; }
        .table-input th { background-color: #f8fafc; text-align: center; vertical-align: middle !important; color: #64748b; font-weight: 600; }
        .bg-readonly { background-color: #e2e8f0 !important; cursor: not-allowed; font-weight: bold; color: #334155 !important; }
        .bg-has-value { background-color: #e8f5e9 !important; border-color: #a5d6a7 !important; color: #1b5e20 !important; font-weight: bold; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'gp2'; include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" href="#">specification properties Information</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="header">
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Revise the standard specifications and requirements (Edit specification properties and standards)</h4>
                                <p class="category">Mechanical property data is entered with one decimal place according to the table database structure TCPS0201_6</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec">
                                    
                                    <div class="form-section-title">1. Standard Specification and Requirements Data (Key Criteria)</div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>ALLOY NO</label>
                                                <input type="text" class="form-control bg-readonly" name="inp_alloy" id="inp_alloy" value="<?php echo $v_alloy; ?>" readonly required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Temper</label>
                                                <input type="text" class="form-control bg-readonly" name="inp_temper" id="inp_temper" value="<?php echo $v_temper; ?>" readonly required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Thickness FROM (mm)</label>
                                                <input type="text" class="form-control bg-readonly" name="inp_thick_from" id="inp_thick_from" value="<?php echo $v_thicknessf; ?>" readonly required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Thickness TO (mm)</label>
                                                <input type="text" class="form-control bg-readonly" name="inp_thick_to" id="inp_thick_to" value="<?php echo $v_thicknesst; ?>" readonly required>
                                            </div>
                                        </div>
                                    </div>

                                    <br>
                                    
                                    <div class="form-section-title">2. Standard Specifications and Requirements (Mechanical Properties)</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-input">
                                            <thead>
                                                <tr>
                                                    <th style="width: 20%;">specifications</th>
                                                    <th style="width: 40%;">FROM</th>
                                                    <th style="width: 40%;">TO</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><strong>UTS (Ksi)</strong></td>
                                                    <td><input type="number" class="form-control prop-input" id="uts_i_f" name="uts_i_f" value="<?php echo $v_uts_i_f; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control prop-input" id="uts_i_t" name="uts_i_t" value="<?php echo $v_uts_i_t; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Yield Strength (Ksi)</strong></td>
                                                    <td><input type="number" class="form-control prop-input" id="yis_i_f" name="yis_i_f" value="<?php echo $v_yis_i_f; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control prop-input" id="yis_i_t" name="yis_i_t" value="<?php echo $v_yis_i_t; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Elongation % (Ksi)</strong></td>
                                                    <td><input type="number" class="form-control prop-input" id="el_i_f" name="el_i_f" value="<?php echo $v_el_i_f; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control prop-input" id="el_i_t" name="el_i_t" value="<?php echo $v_el_i_t; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>UTS(Kg/mm2)</strong></td>
                                                    <td><input type="number" class="form-control prop-input" id="uts_k_f" name="uts_k_f" value="<?php echo $v_uts_k_f; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control prop-input" id="uts_k_t" name="uts_k_t" value="<?php echo $v_uts_k_t; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Yield Strength(Kg/mm2)</strong></td>
                                                    <td><input type="number" class="form-control prop-input" id="yis_k_f" name="yis_k_f" value="<?php echo $v_yis_k_f; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control prop-input" id="yis_k_t" name="yis_k_t" value="<?php echo $v_yis_k_t; ?>" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr style="border-top: 1px solid #eee; margin: 30px 0;">
                                    
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <input type="hidden" id="func" name="func" value="<?php echo $folder_func; ?>"/>
                                            <button type="button" class="btn btn-default btn-fill" style="margin-right: 10px; width: 150px;" onclick="window.location.assign('specification_properties_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                                            <button type="button" class="btn btn-success btn-fill" style="width: 180px; background-color: #27ae60;" onclick="submitAlloyData()">💾 Save Data</button>
                                        </div>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
$(document).ready(function() {
    function checkInputValueColor(element) {
        var val = $(element).val().trim();
        if (val !== "" && !isNaN(val) && parseFloat(val) > 0) {
            $(element).addClass('bg-has-value');
        } else {
            $(element).removeClass('bg-has-value');
        }
    }

    // รันตรวจสอบสีตอนเปิดหน้าเว็บ
    $('.prop-input').each(function() {
        checkInputValueColor(this);
    });

    // ปรับทศนิยมเป็น 1 ตำแหน่งตามโครงสร้างชนิดข้อมูล Real ใน SQL Server
    $('.prop-input').on('blur', function() {
        var val = $(this).val().trim();
        if (val !== "" && !isNaN(val)) {
            $(this).val(parseFloat(val).toFixed(1));
        }
        checkInputValueColor(this);
    });
});

function submitAlloyData() {
    var formData = $("#form_alloy_spec").serialize();
    var data_fun = document.getElementById('func').value;

    $.post("model/update_properties_spec_mats.php", formData, function(resp) {
        if (resp.message === true) {
            alert("The data changes have been successfully.");
            window.location.assign('specification_properties_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("Error occurred: " + (resp.error || "Please check the data again"));
        }
    }, "json")
    .fail(function(xhr, status, error) {
        alert("System error: " + error);
    });
} 
</script>
</html>