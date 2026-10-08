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
$g_proid  = isset($_GET['PRODUCT_ID']) ? trim($_GET['PRODUCT_ID']) : '';
$g_alloy  = isset($_GET['ALLOY']) ? trim($_GET['ALLOY']) : '';
$g_tem_it = isset($_GET['TEMPER_INITIAL']) ? trim($_GET['TEMPER_INITIAL']) : '';
$g_tem_ta = isset($_GET['TEMPER_TARGET']) ? trim($_GET['TEMPER_TARGET']) : '';
$g_ran_fr = isset($_GET['RANGE_FROM']) ? trim($_GET['RANGE_FROM']) : '';
$g_ran_to = isset($_GET['RANGE_TO']) ? trim($_GET['RANGE_TO']) : '';

$data_com = array();
if ($g_alloy != '') {
    // ลำดับ Parameters ของ RT_Practice_Spec($IDPD, $IDAL, $IDRF, $IDRT, $IDTI, $IDTT) ตามรูป function_2.jpg
    $data_com = RT_Practice_Spec($g_proid, $g_alloy, $g_ran_fr, $g_ran_to, $g_tem_it, $g_tem_ta);
}

// แมปปิ้งค่าจาก Array Index [0 - 19]
$v_proid   = isset($data_com[0]) ? htmlspecialchars(trim($data_com[0]), ENT_QUOTES, 'UTF-8') : $g_proid;
$v_alloy   = isset($data_com[1]) ? htmlspecialchars(trim($data_com[1]), ENT_QUOTES, 'UTF-8') : $g_alloy;
$v_ran_fr  = isset($data_com[2]) ? htmlspecialchars(trim($data_com[2]), ENT_QUOTES, 'UTF-8') : $g_ran_fr;
$v_ran_to  = isset($data_com[3]) ? htmlspecialchars(trim($data_com[3]), ENT_QUOTES, 'UTF-8') : $g_ran_to;
$v_tem_it  = isset($data_com[4]) ? htmlspecialchars(trim($data_com[4]), ENT_QUOTES, 'UTF-8') : $g_tem_it;
$v_tem_ta  = isset($data_com[5]) ? htmlspecialchars(trim($data_com[5]), ENT_QUOTES, 'UTF-8') : $g_tem_ta;
$v_range_type = isset($data_com[6]) ? htmlspecialchars(trim($data_com[6]), ENT_QUOTES, 'UTF-8') : '';
$v_min_weight = isset($data_com[7]) ? $data_com[7] : '';
$v_o2p_from   = isset($data_com[8]) ? $data_com[8] : '';
$v_o2p_to     = isset($data_com[9]) ? $data_com[9] : '';
$v_heat_temp  = isset($data_com[10]) ? $data_com[10] : '';
$v_heat_time  = isset($data_com[11]) ? $data_com[11] : '';
$v_soak_temp  = isset($data_com[12]) ? $data_com[12] : '';
$v_soak_time  = isset($data_com[13]) ? $data_com[13] : '';
$v_wp_heat    = isset($data_com[14]) ? $data_com[14] : '';
$v_wp_soak_f  = isset($data_com[15]) ? $data_com[15] : '';
$v_wp_soak_t  = isset($data_com[16]) ? $data_com[16] : '';
$v_o2_perc_f  = isset($data_com[17]) ? number_format((float)$data_com[17], 2, '.', '') : '';
$v_o2_perc_t  = isset($data_com[18]) ? number_format((float)$data_com[18], 2, '.', '') : '';
$v_program    = isset($data_com[19]) ? htmlspecialchars(trim($data_com[19]), ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f4f7f6; }
        .main-panel .content { padding: 20px 20px !important; }
        .card { border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card .header { padding: 20px 20px 0 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 20px; padding-left: 5px; border-left: 4px solid #3498db; }
        .table-input th { background-color: #f8fafc; text-align: center; vertical-align: middle !important; color: #64748b; font-weight: 600; }
        .table-input td { vertical-align: middle !important; }
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
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#">Technical Practice Batch Annealing Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="header">
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Edit data Technical Practice Batch Annealing</h4>
                                <p class="category">Edit data for Technical Practice Batch Annealing</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec" onsubmit="return false;">
                                    
                                    <div class="form-section-title">1. Technical Practice Batch Annealing Information (Composite Keys)</div>
                                    <div class="modal-body" style="padding: 20px 25px;">    
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Product </label>
                                                    <input type="text" class="form-control bg-readonly" id="in_pro_display" value="<?php echo $v_proid; ?>" readonly>
                                                    <input type="hidden" name="in_pro" id="in_pro" value="<?php echo $v_proid; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">ALLOY NO </label>
                                                    <input type="text" class="form-control bg-readonly" name="in_alloy" id="in_alloy" value="<?php echo $v_alloy; ?>" readonly required>
                                                </div>
                                            </div>
                                        </div>                                    

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Temper Initial </label>
                                                    <input type="text" class="form-control bg-readonly" name="in_temper" id="in_temper" value="<?php echo $v_tem_it; ?>" readonly required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Temper Target </label>
                                                    <input type="text" class="form-control bg-readonly" name="in_temper2" id="in_temper2" value="<?php echo $v_tem_ta; ?>" readonly required>
                                                </div>
                                            </div>
                                        </div>
                                    
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Range From (mm) </label>
                                                    <input type="text" class="form-control bg-readonly" name="in_range_f" id="in_range_f" value="<?php echo $v_ran_fr; ?>" readonly required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Range TO (mm) </label>
                                                    <input type="text" class="form-control bg-readonly" name="in_range_t" id="in_range_t" value="<?php echo $v_ran_to; ?>" readonly required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                    
                                    <div class="form-section-title">2. Control by time setting</div>
                                    <div class="modal-body" style="padding: 20px 25px;">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%; vertical-align: middle;">&nbsp;</th>
                                                    <th><center>Time(minute)</center></th>
                                                    <th><center>Temperature(Degree C)</center></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td align="center"><strong>Heating Up</strong></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="h_tmc" name="h_tmc" value="<?php echo $v_heat_time; ?>" placeholder="Time(minute)"/></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="h_tdc" name="h_tdc" value="<?php echo $v_heat_temp; ?>" placeholder="Temperature(Degree C)"/></td>
                                                </tr>
                                                <tr>
                                                    <td align="center"><strong>Soaking</strong></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="s_tmc" name="s_tmc" value="<?php echo $v_soak_time; ?>" placeholder="Time(minute)"/></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="s_tdc" name="s_tdc" value="<?php echo $v_soak_temp; ?>" placeholder="Temperature(Degree C)"/></td>
                                                </tr>     
                                                <tr>
                                                    <td align="center"><strong>Range Type</strong></td>
                                                    <td colspan="2"><input type="text" class="form-control bg-readonly val-check" id="rt" name="rt" value="<?php echo $v_range_type; ?>" placeholder="ระบุอัตโนมัติตามประเภท Product" readonly/></td>
                                                </tr>                           
                                                <tr>
                                                    <td align="center"><strong>Minimum Weight (KG)</strong></td>
                                                    <td colspan="2"><input type="number" step="0.01" class="form-control val-check" id="mw" name="mw" value="<?php echo $v_min_weight; ?>" placeholder="Minimum Weight"/></td>
                                                </tr>                        
                                            </tbody>
                                        </table>
                                        
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%; vertical-align: middle;">&nbsp;</th>
                                                    <th><center>FROM</center></th>
                                                    <th><center>TO</center></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td align="center"><strong>O2 Purging Time (Minute)</strong></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="o2p_f" name="o2p_f" value="<?php echo $v_o2p_from; ?>" placeholder="FROM"/></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="o2p_t" name="o2p_t" value="<?php echo $v_o2p_to; ?>" placeholder="TO"/></td>
                                                </tr>
                                                <tr>
                                                    <td align="center"><strong>O2 %</strong></td>
                                                    <td><input type="number" step="0.01" class="form-control text-center val-check" id="o2_f" name="o2_f" value="<?php echo $v_o2_perc_f; ?>" placeholder="0.00" onchange="if(this.value!='') this.value=parseFloat(this.value).toFixed(2);"/></td>
                                                    <td><input type="number" step="0.01" class="form-control text-center val-check" id="o2_t" name="o2_t" value="<?php echo $v_o2_perc_t; ?>" placeholder="0.00" onchange="if(this.value!='') this.value=parseFloat(this.value).toFixed(2);"/></td>
                                                </tr>    
                                                <tr>
                                                    <td align="center"><strong>Program</strong></td>
                                                    <td colspan="2"><input type="text" class="form-control val-check" id="pg" name="pg" value="<?php echo $v_program; ?>" placeholder="Program"/></td>
                                                </tr>                                                                                                 
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="form-section-title">3. Control by work piece setting</div>
                                    <div class="modal-body" style="padding: 20px 25px;">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%; vertical-align: middle;">&nbsp;</th>
                                                    <th><center>FROM</center></th>
                                                    <th><center>TO</center></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td align="center"><strong>Work Piece Heating up</strong></td>
                                                    <td colspan="2"><input type="number" class="form-control val-check" id="wph" name="wph" value="<?php echo $v_wp_heat; ?>" placeholder="Work Piece Heating up"/></td>
                                                </tr>
                                                <tr>
                                                    <td align="center"><strong>Work Piece Soaking</strong></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="wps_f" name="wps_f" value="<?php echo $v_wp_soak_f; ?>" placeholder="Work Piece Soaking FROM"/></td>
                                                    <td><input type="number" class="form-control text-center val-check" id="wps_t" name="wps_t" value="<?php echo $v_wp_soak_t; ?>" placeholder="Work Piece Soaking TO"/></td>
                                                </tr>                                                                                                   
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr style="border-top: 1px solid #eee; margin: 30px 0;">
                                    
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                                            <button type="button" class="btn btn-default btn-fill" style="margin-right: 10px; width: 150px;" onclick="window.location.assign('technical_practice_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
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
    // ฟังก์ชันตรวจสอบและไฮไลต์สีเขียวให้ช่องที่มีข้อมูล
    function checkInputValueColor(element) {
        var val = $(element).val().trim();
        if (val !== "" && val !== "0" && val !== "0.0" && val !== "0.00" && !isNaN(val) && parseFloat(val) > 0) {
            $(element).addClass('bg-has-value');
        } else if (val !== "" && isNaN(val)) {
            $(element).addClass('bg-has-value');
        } else {
            $(element).removeClass('bg-has-value');
        }
    }

    // เรียกตรวจสีช่องอินพุตเมื่อเริ่มโหลดหน้าเว็บ
    $('.val-check').each(function() {
        checkInputValueColor(this);
    });

    // เรียกตรวจสีเมื่อมีการแก้ไขข้อมูลในช่องอินพุต
    $('.val-check').on('keyup change blur', function() {
        checkInputValueColor(this);
    });
});

function submitAlloyData() {
    var in_pro = $("#in_pro").val();
    var in_alloy = $("#in_alloy").val();
    var in_temper = $("#in_temper").val();
    var in_temper2 = $("#in_temper2").val();
    var in_range_f = $("#in_range_f").val();
    var in_range_t = $("#in_range_t").val();
    var data_fun = $("#func").val();

    if (in_pro == "" || in_alloy == "" || in_temper == "" || in_temper2 == "" || in_range_f == "" || in_range_t == "") {
        alert("Please fill in all required fields (*)");
        return false;
    }

    var formData = $("#form_alloy_spec").serialize();

    $.ajax({
        url: "model/update_practice_spec_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
            if (response.status === "success" || response.message === true) {
                alert("Data has been successfully updated.");
                window.location.assign('technical_practice_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                var errorMsg = response.error || "An error occurred while saving data";
                alert("Failed to save data: " + errorMsg);
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error Details:", xhr.responseText, status, error);
            alert("An error occurred while connecting to the server: " + error);
        }
    });
}
</script>
</html>