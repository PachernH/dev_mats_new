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
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body {  font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;background-color: #f4f7f6; }
        .main-panel .content { padding: 20px 20px !apply; }
        .card { border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card .header { padding: 20px 20px 0 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 20px; padding-left: 5px; border-left: 4px solid #3498db; }
        .table-input th { background-color: #f8fafc; text-align: center; vertical-align: middle !important; color: #64748b; font-weight: 600; }
        .table-input td { vertical-align: middle !important; }
        .bg-readonly { background-color: #f1f5f9 !important; cursor: not-allowed; }
        
        /* สไตล์คลาสสำหรับเปลี่ยนสีพื้นหลังอินพุตที่มีการคีย์ข้อมูลมากกว่า 0 */
        .bg-has-value { background-color: #e8f5e9 !important; border-color: #a5d6a7 !important; color: #1b5e20 !important; font-weight: bold; }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'gp2'; // ล็อกเมนูให้อยู่กลุ่มเดียวกับหน้ารายงาน ?>

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
                    <a class="navbar-brand" href="#">specification properties Information</a>
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Add standard specifications and requirements information (New specification properties and standards)</h4>
                                <p class="category">Standard specification and requirements data (2 decimal places for thickness and 1 decimal place for mechanical properties)</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec">
                                    
                                    <div class="form-section-title">1. Standard Specification and Requirements Data (Key Criteria)</div>
                                    <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" list="alloy_list" placeholder="-- Type to search or specify ALLOY --" autocomplete="off" required>
                                            <datalist id="alloy_list">
                                                <?php
                                                    include('dbcon_mats-new.php');
                                                    $sql = "SELECT DISTINCT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC";
                                                    $result = $conn->prepare($sql);
                                                    $result->execute();
                                                    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                        echo "<option value='".htmlspecialchars(trim($row["ALLOY"]), ENT_QUOTES, 'UTF-8')."'>";
                                                    }
                                                ?>
                                            </datalist>
                                        </div>
                                    </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Temper <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_temper" id="inp_temper" list="temper_list" placeholder="-- Type to search or specify Temper --" autocomplete="off" required>
                                                <datalist id="temper_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='".htmlspecialchars(trim($row["TEMPER"]), ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Thickness FROM (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control thick-input" name="inp_thick_from" id="inp_thick_from" list="width_list" placeholder="-- Thickness FROM (e.g., 00.30) --" autocomplete="off" required>
                                                <datalist id="width_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT WIDTH FROM DMWDMSTR1 ORDER BY WIDTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // แก้ไขให้เปลี่ยนเป็นทศนิยม 2 ตำแหน่งและเติมเลข 0 นำหน้าให้ตรงตามความกว้างของโมเดลตาราง
                                                            $formatted_width = sprintf("%05.2f", $row["WIDTH"]);
                                                            echo "<option value='".htmlspecialchars($formatted_width, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Thickness TO (mm) <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control thick-input" name="inp_thick_to" id="inp_thick_to" list="length_list" placeholder="-- Thickness TO (เช่น 00.40) --" autocomplete="off" required>
                                                <datalist id="length_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT LENGTH FROM LNGTMSTR1 ORDER BY LENGTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // แก้ไขให้เปลี่ยนเป็นทศนิยม 2 ตำแหน่งให้ตรงกับข้อมูลจริง
                                                            $formatted_length = sprintf("%05.2f", $row["LENGTH"]);
                                                            echo "<option value='".htmlspecialchars($formatted_length, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>

                                    <br>
                                    
                                    <div class="form-section-title">2. Mechanical specifications and characteristics (Mechanical Properties)</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-input">
                                            <thead>
                                                <tr>
                                                    <th style="width: 15%;">specifications</th>
                                                    <th style="width: 28%;">FROM</th>
                                                    <th style="width: 28%;">TO</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $elements = array(
                                                    'uts_i' => 'UTS (Ksi)', 'yis_i' => 'Yield Strength (Ksi)', 'el_i' => 'Elongation % (Ksi)', 'uts_k' => 'UTS(Kg/mm2)', 'yis_k' => 'Yield Strength(Kg/mm2)'
                                                );
                                                foreach ($elements as $key => $label) {
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $label; ?></strong></td>
                                                    <!-- ปรับแก้ไขสเตปตัวเลขเป็น 0.1 และใช้คลาส prop-input เพื่อคุมเรื่องตรวจสอบการไฮไลต์สี -->
                                                    <td><input type="number" class="form-control calc-avg prop-input" id="<?php echo $key; ?>_f" name="<?php echo $key; ?>_f" placeholder="0.0" step="0.1" min="0"/></td>
                                                    <td><input type="number" class="form-control calc-avg prop-input" id="<?php echo $key; ?>_t" name="<?php echo $key; ?>_t" placeholder="0.0" step="0.1" min="0"/></td>
                                                </tr>
                                                <?php } ?>
                                                                                                
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
    // ฟังก์ชันตรวจสอบค่าข้อมูลเพื่อผูกสีพื้นหลังสีเขียว
    function checkInputValueColor(element) {
        var val = $(element).val().trim();
        if (val !== "" && !isNaN(val) && parseFloat(val) > 0) {
            $(element).addClass('bg-has-value');
        } else {
            $(element).removeClass('bg-has-value');
        }
    }

    // เมื่อพิมพ์หรือเลื่อนเมาส์ออกจาก Input ส่วนของคุณสมบัติทางกล ให้แปลงค่าเป็นทศนิยม 1 ตำแหน่งอัตโนมัติ
    $('.prop-input').on('blur', function() {
        var val = $(this).val().trim();
        if (val !== "" && !isNaN(val)) {
            $(this).val(parseFloat(val).toFixed(1));
        }
        checkInputValueColor(this);
    });

    // เมื่อกรอกฟิลด์ Thicknessเสร็จสิ้น ให้จัด Format เติม 0 และจุดทศนิยม 2 ตำแหน่งอัตโนมัติเพื่อให้หาค่าแมตช์เจอใน DB
    $('.thick-input').on('blur', function() {
        var val = $(this).val().trim();
        if (val !== "" && !isNaN(val)) {
            var num = parseFloat(val);
            // แปลงค่าให้อยู่ในรูปแบบติด 0 ด้านหน้าถ้าเลขไม่ถึงสิบ (เช่น 0.3 -> 00.30)
            var formatted = (num < 10 ? "0" : "") + num.toFixed(2);
            $(this).val(formatted);
        }
    });
});

function submitAlloyData() {
    var alloy = document.getElementById('inp_alloy').value.trim();
    var temper = document.getElementById('inp_temper').value.trim();
    var thick_f = document.getElementById('inp_thick_from').value.trim();
    var thick_t = document.getElementById('inp_thick_to').value.trim();
    var data_fun = document.getElementById('func').value;

    if (alloy === "" || temper === "" || thick_f === "" || thick_t === "") {
        alert("Please fill in all the required information.");
        return;
    }

    var formData = $("#form_alloy_spec").serialize();

    $.post("model/save_properties_spec_mats.php", formData, function(resp) {
        if (resp.message === true) {
            alert("Data saved successfully");
            window.location.assign('specification_properties_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("Error occurred: " + (resp.error || "Please check the data again"));
        }
    }, "json")
    .fail(function(xhr, status, error) {
        alert("Technical system malfunction (server unresponsive): " + error);
    });
} 
</script>
</html>