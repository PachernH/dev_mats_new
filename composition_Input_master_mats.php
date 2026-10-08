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
                    <a class="navbar-brand" href="#">Create Alloy Composition Master</a>
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Add aluminum component specifications (New Alloy Entry)</h4>
                                <p class="category">Please enter the master data and specify the standard value to 3 decimal places</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_composition">
                                    
                                    <div class="form-section-title">1. Product and customer key information (Master Information)</div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" placeholder="Example: AA3105" required onkeyup="this.value = this.value.toUpperCase()">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">CUSTOMER ID / SUPPLIER ID <span class="text-danger">*</span></label>
                                                <select class="form-control" name="inp_cstmsppl" id="inp_cstmsppl">
                                                        <option value=""></option>
                                                        <?php
                                                            include('dbcon_mats-new.php');
                                                            $sql = "SELECT CSTMSPPL_ID, CSTMSPPL_TYPE, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_TYPE = 'B' ORDER BY CSTMSPPL_ID ASC";
                                                            $result = $conn->prepare($sql);
                                                            $result->execute();
                                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                                echo "<option value='".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["CONSIGNEE_COMPANY"], ENT_QUOTES, 'UTF-8')."</option>";
                                                            }
                                                        ?>
                                                </select>  
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">GRAVITY</label>
                                                <input type="number" class="form-control" name="inp_gravity" id="inp_gravity" placeholder="0.000" step="0.001" min="0" value="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALUMINIUM (AL %)</label>
                                                <input type="number" class="form-control" name="inp_al" id="inp_al" placeholder="0.000" step="0.001" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">TITANIUM (TI %)</label>
                                                <input type="number" class="form-control" name="inp_ti" id="inp_ti" placeholder="0.000" step="0.001" min="0" max="100">
                                            </div>
                                        </div>
                                    </div>

                                    <br>
                                    
                                    <div class="form-section-title">2. Chemical elemental range (Chemical Specification Range)</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-input">
                                            <thead>
                                                <tr>
                                                    <th style="width: 15%;">Element</th>
                                                    <th style="width: 28%;">Min</th>
                                                    <th style="width: 28%;">Average<small class="text-muted">(Automatic calculation)</small></th>
                                                    <th style="width: 28%;">Max</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $elements = array(
                                                    'al' => 'Aluminium (AL)', 'fe' => 'Iron (Fe)', 'si' => 'Silicon (Si)', 
                                                    'mn' => 'Manganese (Mn)', 'mg' => 'Magnesium (Mg)', 'cr' => 'Chromium (Cr)', 
                                                    'cu' => 'Copper (Cu)', 'zn' => 'Zinc (Zn)', 'pb' => 'Lead (Pb)'
                                                );
                                                foreach ($elements as $key => $label) {
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $label; ?></strong></td>
                                                    <td><input type="number" class="form-control calc-avg" id="<?php echo $key; ?>_min" name="<?php echo $key; ?>_min" placeholder="0.000" step="0.001" min="0" oninput="calculateAvg('<?php echo $key; ?>')"/></td>
                                                    <td><input type="number" class="form-control bg-readonly" id="<?php echo $key; ?>_avg" name="<?php echo $key; ?>_avg" placeholder="0.000" readonly/></td>
                                                    <td><input type="number" class="form-control calc-avg" id="<?php echo $key; ?>_max" name="<?php echo $key; ?>_max" placeholder="0.000" step="0.001" min="0" oninput="calculateAvg('<?php echo $key; ?>')"/></td>
                                                </tr>
                                                <?php } ?>

                                                <?php 
                                                $max_only_elements = array(
                                                    'as' => 'Arsenic (As)', 'ni' => 'Nickel (Ni)', 'sn' => 'Tin (Sn)', 
                                                    'sb' => 'Antimony (Sb)', 'be' => 'Beryllium (Be)', 'bi' => 'Bismuth (Bi)', 
                                                    'cd' => 'Cadmium (Cd)'
                                                );
                                                foreach ($max_only_elements as $key => $label) {
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $label; ?></strong></td>
                                                    <td><input type="number" class="form-control bg-readonly" id="<?php echo $key; ?>_min" name="<?php echo $key; ?>_min" value="0.000" readonly/></td>
                                                    <td><input type="number" class="form-control bg-readonly" id="<?php echo $key; ?>_avg" name="<?php echo $key; ?>_avg" value="0.000" readonly/></td>
                                                    <td><input type="number" class="form-control" id="<?php echo $key; ?>_max" name="<?php echo $key; ?>_max" placeholder="0.000" step="0.001" min="0"/></td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <hr style="border-top: 1px solid #eee; margin: 30px 0;">
                                    
                                    <div class="row">
                                        <div class="col-md-12 text-right">
                                            <input type="hidden" id="func" name="func" value="<?php echo $folder_func; ?>"/>
                                            <button type="button" class="btn btn-default btn-fill" style="margin-right: 10px; width: 150px;" onclick="window.location.assign('composition_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
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
    // ✨ ฟังก์ชันคำนวณค่าเฉลี่ย (Average) อัตโนมัติและตรึงทศนิยม 3 ตำแหน่ง
    function calculateAvg(elementKey) {
        var minVal = parseFloat(document.getElementById(elementKey + '_min').value);
        var maxVal = parseFloat(document.getElementById(elementKey + '_max').value);
        var avgInput = document.getElementById(elementKey + '_avg');

        if (!isNaN(minVal) && !isNaN(maxVal)) {
            var avg = (minVal + maxVal) / 2;
            avgInput.value = avg.toFixed(3);
        } else if (!isNaN(maxVal)) {
            // กรณีระบุเฉพาะค่า Max ให้ถือว่า Avg เท่ากับค่านั้นไปก่อน หรือตั้งเป็น 0 ตามลอจิกโรงงาน
            avgInput.value = (maxVal / 2).toFixed(3);
        } else {
            avgInput.value = "";
        }
    }

function submitAlloyData() {
    var alloy = document.getElementById('inp_alloy').value.trim();
    var cstmsppl = document.getElementById('inp_cstmsppl').value.trim();
    var data_fun = document.getElementById('func').value;

    if (alloy === "" || cstmsppl === "") {
        alert("Please fill in the Alloy code and Customer/Distributor code completely.");
        return;
    }

    var formData = $("#form_alloy_composition").serialize();

    // ใช้ $.ajax หรือระบุพารามิเตอร์ตัวที่ 4 ของ $.post เป็น "json"
    $.post("model/save_composition_mats.php", formData, function(resp) {
        // ไม่ต้องใช้ JSON.parse(data) ใน try-catch แล้ว เพราะส่งเป็น Object เข้ามาเลย
        if (resp.message === true) {
            alert("The master specification data has been successfully recorded.");
            window.location.assign('composition_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred: " + (resp.error || "Please double-check the information"));
        }
    }, "json") // <--- ระบุส่งกลับแบบ json ตรงนี้
    .fail(function(xhr, status, error) {
        // ดักกรณีไฟล์ดับ เบสล่ม หรือ PHP พ่น Error 500
        alert("Technical system malfunction (server unresponsive): " + error);
    });

    // ❌ ลบโค้ดจำลอง Alert และ window.location.assign เดิมที่อยู่ตรงนี้ออกไปทั้งหมด!
} 
</script>
</html>