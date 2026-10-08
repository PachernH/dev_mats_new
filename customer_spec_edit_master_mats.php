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
$g_custid     = isset($_GET['CSTMSPPL_ID']) ? trim($_GET['CSTMSPPL_ID']) : '';
$g_proid      = isset($_GET['PRODUCT_ID']) ? trim($_GET['PRODUCT_ID']) : '';
$g_alloy      = isset($_GET['ALLOY']) ? trim($_GET['ALLOY']) : '';
$g_temper     = isset($_GET['TEMPER']) ? trim($_GET['TEMPER']) : '';
$g_thickness  = isset($_GET['THICKNESS']) ? trim($_GET['THICKNESS']) : '';
$g_width      = isset($_GET['WIDTH']) ? trim($_GET['WIDTH']) : '';
$g_length     = isset($_GET['LENGTH']) ? trim($_GET['LENGTH']) : '';

$data_com = array();
if ($g_custid != '' || $g_proid != ''|| $g_alloy != '' || $g_temper != '' || $g_thickness != '' || $g_width != '' || $g_length != '') {
    $data_com = RT_Cust_Spec($g_custid, $g_proid, $g_alloy, $g_temper, $g_thickness, $g_width, $g_length);
}

// ป้องกันหากไม่พบข้อมูลในตาราง
if (empty($data_com)) {
    echo "<script>alert('No product specification information was found for the customer requiring modification.'); window.location.assign('customer_spec_std_master_mats.php?func=".urlencode($folder_func)."');</script>";
    exit;
}
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
        .main-panel .content { padding: 20px 20px; }
        .card { border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card .header { padding: 20px 20px 0 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 20px; padding-left: 5px; border-left: 4px solid #e67e22; }
        .table-input th { background-color: #f8fafc; text-align: center; vertical-align: middle !important; color: #64748b; font-weight: 600; }
        .table-input td { vertical-align: middle !important; }
        
        .has-value {
            background-color: #e8f5e9 !important; 
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20;
        }
        .form-control[disabled] {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'gp2'; ?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#">Customer Specification Information</a>
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Update standard product specifications for customers (Edit Customer Specification)</h4>
                                <p class="category">The green boxes are those where a master value greater than 0 is specified.</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec">
                                    <!-- Hidden Keys สำหรับใช้ใน WHERE: ส่งค่าดิบที่เป็นข้อความ nvarchar ออกไปโดยตรง ไม่ต้องทำ number_format เพื่อให้แมตช์ข้อมูลใน DB -->
                                    <input type="hidden" name="old_cust" value="<?php echo htmlspecialchars($data_com[0], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_pid" value="<?php echo htmlspecialchars($data_com[1], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_alloy" value="<?php echo htmlspecialchars($data_com[2], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_temper" value="<?php echo htmlspecialchars($data_com[3], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_thickness" value="<?php echo htmlspecialchars($data_com[4], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_width" value="<?php echo htmlspecialchars($data_com[5], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_length" value="<?php echo htmlspecialchars($data_com[6], ENT_QUOTES, 'UTF-8'); ?>">
                                    
                                    <div class="form-section-title">1. Customer product information (Customer Specification Information)</div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Customer<span class="text-danger">*</span></label>
                                                <select class="form-control" id="inp_cust" name="inp_cust" disabled>
                                                        <?php
                                                            include('dbcon_mats-new.php');
                                                            $sql = "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_TYPE = 'B' OR CSTMSPPL_TYPE = 'C' ORDER BY CSTMSPPL_ID ASC";
                                                            $result = $conn->prepare($sql);
                                                            $result->execute();
                                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                                $selected = ($row["CSTMSPPL_ID"] == $data_com[0]) ? "selected" : "";
                                                                echo "<option value='".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["CONSIGNEE_COMPANY"], ENT_QUOTES, 'UTF-8')."</option>";
                                                            }
                                                        ?>
                                                </select>      
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Product ID<span class="text-danger">*</span></label>       
                                                <select class="form-control" id="inp_pid" name="inp_pid" disabled>
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT PRODUCT_ID, PRODUCT_MODEL FROM PRODMSTR1 ORDER BY PRODUCT_ID ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            $selected = ($row["PRODUCT_ID"] == $data_com[1]) ? "selected" : "";
                                                            echo "<option value='".htmlspecialchars($row["PRODUCT_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["PRODUCT_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["PRODUCT_MODEL"], ENT_QUOTES, 'UTF-8')."</option>";
                                                        }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" value="<?php echo htmlspecialchars($data_com[2], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Temper <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_temper" id="inp_temper" value="<?php echo htmlspecialchars($data_com[3], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Thickness (mm) <span class="text-danger">*</span></label>
                                                <!-- หน้าจอแสดงผลใช้ค่าที่ดึงมาจากต้นทางโดยตรงเพื่อความแม่นยำของตัวอักษร -->
                                                <input type="text" class="form-control" name="inp_thickness" id="inp_thickness" value="<?php echo htmlspecialchars($data_com[4], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" disabled>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Width (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_width" id="inp_width" value="<?php echo htmlspecialchars($data_com[5], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" disabled>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Length (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_length" id="inp_length" value="<?php echo htmlspecialchars($data_com[6], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" disabled> 
                                            </div>
                                        </div>
                                    </div>

                                    <br>
                                    
                                    <div class="form-section-title">2. Product specifications and standards (Product specifications)</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-input">
                                            <thead>
                                                <tr>
                                                    <th style="width: 34%;">specifications</th>
                                                    <th style="width: 33%;">Plus</th>
                                                    <th style="width: 33%;">Minus</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $elements = array(
                                                    'th' => array('label' => 'Thickness Tolerance', 'p' => 7, 'm' => 8),
                                                    'wi' => array('label' => 'Width Tolerance', 'p' => 9, 'm' => 10),
                                                    'le' => array('label' => 'Length Tolerance', 'p' => 11, 'm' => 12)
                                                );
                                                foreach ($elements as $key => $info) {
                                                    $val_p = isset($data_com[$info['p']]) ? number_format((float)$data_com[$info['p']], 3, '.', '') : '0.000';
                                                    $val_m = isset($data_com[$info['m']]) ? number_format((float)$data_com[$info['m']], 3, '.', '') : '0.000';
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $info['label']; ?></strong></td>
                                                    <td><input type="number" class="form-control color-trigger" id="<?php echo $key; ?>_p" name="<?php echo $key; ?>_p" value="<?php echo $val_p; ?>" placeholder="0.000" step="0.001" min="0"/></td>
                                                    <td><input type="number" class="form-control color-trigger" id="<?php echo $key; ?>_m" name="<?php echo $key; ?>_m" value="<?php echo $val_m; ?>" placeholder="0.000" step="0.001" min="0"/></td>
                                                </tr>
                                                <?php } ?>

                                                <tr>
                                                    <td><strong>Flatness</strong></td>
                                                    <td colspan="2"><input type="number" class="form-control color-trigger" id="fl" name="fl" value="<?php echo number_format((float)$data_com[13], 3, '.', ''); ?>" placeholder="Flatness" step="0.001"/></td>
                                                </tr>
                                                 <tr>
                                                    <td><strong>Earing Type</strong></td>
                                                    <td colspan="2"><input type="text" class="form-control" id="et" name="et" value="<?php echo htmlspecialchars($data_com[14], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Earing Type"/></td>
                                                </tr>   
                                                 <tr>
                                                    <td><strong>Earing Percentage</strong></td>
                                                    <td colspan="2"><input type="number" class="form-control color-trigger" id="ep" name="ep" value="<?php echo number_format((float)$data_com[15], 3, '.', ''); ?>" placeholder="Earing Percentage" step="0.001"/></td>
                                                </tr>                                                  
                                                 <tr>
                                                    <td><strong>Fixed Process</strong></td>
                                                    <td colspan="2">
                                                        <select class="form-control" id="fxp" name="fxp">
                                                            <option value=""  <?php if($data_com[16] == '') echo 'selected'; ?>></option>
                                                            <option value="NO" <?php if($data_com[16] == 'NO') echo 'selected'; ?>>NO</option>
                                                            <option value="BA" <?php if($data_com[16] == 'BA') echo 'selected'; ?>>BA</option>
                                                        </select>                                                        
                                                    </td>
                                                </tr>       
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr style="border-top: 1px solid #eee; margin: 30px 0;">
                                    
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <input type="hidden" id="func" name="func" value="<?php echo $folder_func; ?>"/>
                                            <button type="button" class="btn btn-default btn-fill" style="margin-right: 10px; width: 150px;" onclick="window.location.assign('customer_spec_std_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                                            <button type="button" class="btn btn-warning btn-fill" style="width: 180px; background-color: #e67e22; border-color: #e67e22; color: #fff;" onclick="submitAlloyData()">💾 Save and Update</button>
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
    function applyColorHighlighting() {
        $('.color-trigger').each(function() {
            var val = parseFloat($(this).val());
            if (!isNaN(val) && val > 0) {
                $(this).addClass('has-value');
            } else {
                $(this).removeClass('has-value');
            }
        });
    }

    $(document).ready(function() {
        applyColorHighlighting();
        
        $('.color-trigger').on('input change', function() {
            applyColorHighlighting();
        });
    });

function submitAlloyData() {
    var idcust = document.getElementById('inp_cust').value.trim();
    var idpro = document.getElementById('inp_pid').value.trim();
    var alloy = document.getElementById('inp_alloy').value.trim();
    var temper = document.getElementById('inp_temper').value.trim();
    var thickness = document.getElementById('inp_thickness').value.trim();
    var width = document.getElementById('inp_width').value.trim();
    var length = document.getElementById('inp_length').value.trim();
    var data_fun = document.getElementById('func').value;

    if (idcust === "" || idpro === "" || alloy === "" || temper === "" || thickness === "" || width === "" || length === "") {
        alert("Please fill in all the required customer product information.");
        return;
    }

    // ปลุกกลุ่ม disabled ชั่วคราวเพื่อให้ดึงข้อมูลได้ครบถ้วน
    $('#inp_cust, #inp_pid, #inp_alloy, #inp_temper, #inp_thickness, #inp_width, #inp_length').prop('disabled', false);

    var formData = $("#form_alloy_spec").serialize();

    // สั่งล็อกกลับคืนทันที
    $('#inp_cust, #inp_pid, #inp_alloy, #inp_temper, #inp_thickness, #inp_width, #inp_length').prop('disabled', true);

    $.post("model/update_cust_spec_mats.php", formData, function(resp) {
        if (resp.message === true) {
            alert("The customer's master specification information has been successfully updated.");
            window.location.assign('customer_spec_std_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred.: " + (resp.error || "Please double-check the information."));
        }
    }, "json")
    .fail(function(xhr, status, error) {
        alert("Technical system malfunction (server unresponsive): " + error);
    });
}
</script>
</html>