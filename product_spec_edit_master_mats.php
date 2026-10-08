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
$g_alloy     = isset($_GET['ALLOY']) ? $_GET['ALLOY'] : '';
$g_temper    = isset($_GET['TEMPER']) ? $_GET['TEMPER'] : '';
$g_thickness = isset($_GET['THICKNESS']) ? $_GET['THICKNESS'] : '';
$g_width     = isset($_GET['WIDTH']) ? $_GET['WIDTH'] : '';
$g_length    = isset($_GET['LENGTH']) ? $_GET['LENGTH'] : '';

$data_com = array();
if ($g_alloy != '') {
    // เรียกฟังก์ชันโดยส่ง Parameter 5 ตัวตามรูป function_2.jpg
    $data_com = RT_Product_Spec($g_alloy, $g_temper, $g_thickness, $g_width, $g_length);
}

// ป้องกันหากไม่พบข้อมูลในตาราง
if (empty($data_com)) {
    echo "<script>alert('ไม่พบข้อมูลข้อกำหนดผลิตภัณฑ์ที่ต้องการแก้ไข'); window.location.assign('product_spec_std_master_mats.php?func=".urlencode($folder_func)."');</script>";
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
        
        /* 🎨 คลาสสีสำหรับกล่องที่มีข้อมูลตัวเลขมากกว่า 0 */
        .has-value {
            background-color: #e8f5e9 !important; /* พื้นหลังเขียวอ่อน */
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20;
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
                    <a class="navbar-brand" href="#">Edit Product Specification</a>
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Revise product specification data (Edit Product specifications)</h4>
                                <p class="category">The green boxes are those where a master value greater than 0 is specified.</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec">
                                    <!-- Hidden Keys ชุดเดิมใช้สำหรับเป็นเงื่อนไข WHERE ตอนอัปเดตลง Database -->
                                    <input type="hidden" name="old_alloy" value="<?php echo htmlspecialchars($data_com[0], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_temper" value="<?php echo htmlspecialchars($data_com[1], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_thickness" value="<?php echo htmlspecialchars($data_com[2], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_width" value="<?php echo htmlspecialchars($data_com[3], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_length" value="<?php echo htmlspecialchars($data_com[4], ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="form-section-title">1. Product information (Product specifications Information)</div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" list="alloy_list" value="<?php echo htmlspecialchars($data_com[0], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required disabled> 
                                                <datalist id="alloy_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT DISTINCT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Temper <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_temper" id="inp_temper" list="temper_list" value="<?php echo htmlspecialchars($data_com[1], ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required disabled>
                                                <datalist id="temper_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='".htmlspecialchars($row["TEMPER"], ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Thickness (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control color-trigger" name="inp_thickness" id="inp_thickness" list="thickness_list" value="<?php echo number_format((float)$data_com[2], 3, '.', ''); ?>" autocomplete="off" disabled>
                                                <datalist id="thickness_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT THICKNESS FROM THCKMSTR1 ORDER BY THICKNESS ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            $formatted_thickness = number_format($row["THICKNESS"], 3, '.', '');
                                                            echo "<option value='".htmlspecialchars($formatted_thickness, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Width (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control color-trigger" name="inp_width" id="inp_width" list="width_list" value="<?php echo number_format((float)$data_com[3], 3, '.', ''); ?>" autocomplete="off" disabled>
                                                <datalist id="width_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT WIDTH FROM DMWDMSTR1 ORDER BY WIDTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            $formatted_width = number_format($row["WIDTH"], 3, '.', '');
                                                            echo "<option value='".htmlspecialchars($formatted_width, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Length (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control color-trigger" name="inp_length" id="inp_length" list="length_list" value="<?php echo number_format((float)$data_com[4], 3, '.', ''); ?>" autocomplete="off" disabled>
                                                <datalist id="length_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT LENGTH FROM LNGTMSTR1 ORDER BY LENGTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            $formatted_length = number_format($row["LENGTH"], 3, '.', '');
                                                            echo "<option value='".htmlspecialchars($formatted_length, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Standard Description</label>
                                                <input type="text" class="form-control" name="inp_desc" id="inp_desc" value="<?php echo htmlspecialchars($data_com[5], ENT_QUOTES, 'UTF-8'); ?>" placeholder="ระบุรายละเอียดมาตรฐาน" disabled>
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
                                                    <th style="width: 33%;">Min</th>
                                                    <th style="width: 33%;">Max</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // จับคู่ Key ตามชื่อตัวแปร และผูก Index ข้อมูลตรงตามรูป function_2.jpg
                                                $elements = array(
                                                    'th'  => array('label' => 'Thickness', 'min' => 7, 'max' => 6), 
                                                    'wi'  => array('label' => 'Width', 'min' => 9, 'max' => 8), 
                                                    'le'  => array('label' => 'Length', 'min' => 11, 'max' => 10), 
                                                    'uts' => array('label' => 'Ultimate Tensile Strength', 'min' => 13, 'max' => 12), 
                                                    'ys'  => array('label' => 'Yield Strength', 'min' => 15, 'max' => 14), 
                                                    'el'  => array('label' => 'Elongation', 'min' => 17, 'max' => 16), 
                                                    'ea'  => array('label' => 'Earing', 'min' => 19, 'max' => 18)
                                                );
                                                foreach ($elements as $key => $info) {
                                                    $val_min = isset($data_com[$info['min']]) ? number_format((float)$data_com[$info['min']], 3, '.', '') : '0.000';
                                                    $val_max = isset($data_com[$info['max']]) ? number_format((float)$data_com[$info['max']], 3, '.', '') : '0.000';
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $info['label']; ?></strong></td>
                                                    <td><input type="number" class="form-control color-trigger" id="<?php echo $key; ?>_min" name="<?php echo $key; ?>_min" value="<?php echo $val_min; ?>" placeholder="0.000" step="0.001" min="0"/></td>
                                                    <td><input type="number" class="form-control color-trigger" id="<?php echo $key; ?>_max" name="<?php echo $key; ?>_max" value="<?php echo $val_max; ?>" placeholder="0.000" step="0.001" min="0"/></td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr style="border-top: 1px solid #eee; margin: 30px 0;">
                                    
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <input type="hidden" id="func" name="func" value="<?php echo $folder_func; ?>"/>
                                            <button type="button" class="btn btn-default btn-fill" style="margin-right: 10px; width: 150px;" onclick="window.location.assign('product_spec_std_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                                            <button type="button" class="btn btn-warning btn-fill" style="width: 180px; background-color: #e67e22; border-color: #e67e22; color: #fff;" onclick="submitAlloyData()">💾 Save (Update)</button>
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
    // 🎨 ฟังก์ชันเช็คค่าเพื่อทำไฮไลต์สีเขียวอ่อนเมื่อตัวเลข > 0
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
        applyColorHighlighting(); // รันตอนโหลดหน้าแรก
        
        // รันแบบ Real-time เมื่อมีการแก้ตัวเลขในฟอร์ม
        $('.color-trigger').on('input change', function() {
            applyColorHighlighting();
        });
    });

function submitAlloyData() {
    var alloy = document.getElementById('inp_alloy').value.trim();
    var temper = document.getElementById('inp_temper').value.trim();
    var thickness = document.getElementById('inp_thickness').value.trim();
    var width = document.getElementById('inp_width').value.trim();
    var length = document.getElementById('inp_length').value.trim();
    var data_fun = document.getElementById('func').value;

    if (alloy === "" || temper === "" || thickness === "" || width === "" || length === "") {
        alert("Please fill in all the required product information.");
        return;
    }

       // ปลุกกลุ่ม disabled ชั่วคราวเพื่อให้ดึงข้อมูลได้ครบถ้วน
    $('#inp_alloy, #inp_temper, #inp_thickness, #inp_width, #inp_length, #inp_desc').prop('disabled', false);

    var formData = $("#form_alloy_spec").serialize();

    $('#inp_alloy, #inp_temper, #inp_thickness, #inp_width, #inp_length, #inp_desc').prop('disabled', true);

    $.post("model/update_product_spec_mats.php", formData, function(resp) {
        if (resp.message === true) {
            alert("The product master specification information has been successfully updated.");
            window.location.assign('product_spec_std_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred: " + (resp.error || "Please double-check the information."));
        }
    }, "json")
    .fail(function(xhr, status, error) {
        alert("Technical system malfunction (server unresponsive): " + error);
    });
} 
</script>
</html>