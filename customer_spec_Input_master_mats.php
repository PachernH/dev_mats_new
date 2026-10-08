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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Add standard product specifications and customer requirements (New Customer Specification and standards)</h4>
                                <p class="category">Please enter product information and specify the value to 3 decimal places</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec">
                                    
                                    <div class="form-section-title">1. Customer product information (Customer Specification Information)</div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Customer<span class="text-danger">*</span></label>
                                                <select class="form-control" id="inp_cust" name="inp_cust">
                                                        <option value=""></option>
                                                        <?php
                                                            include('dbcon_mats-new.php');
                                                            $sql = "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_TYPE = 'B' OR CSTMSPPL_TYPE = 'C' ORDER BY CSTMSPPL_ID ASC";
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
                                                <label style="color: #475569; font-weight: 600;">Product ID<span class="text-danger">*</span></label>       
                                                <select class="form-control" id="inp_pid" name="inp_pid">
                                                    <option value=""></option>
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT PRODUCT_ID,PRODUCT_MODEL FROM PRODMSTR1 ORDER BY PRODUCT_ID ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            echo "<option value='".htmlspecialchars($row["PRODUCT_ID"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["PRODUCT_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["PRODUCT_MODEL"], ENT_QUOTES, 'UTF-8')."</option>";
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
                                            
                                            <!-- 1. เปลี่ยนจาก <select> เป็น <input> และเชื่อมโยงกับ datalist ด้วย list="alloy_list" -->
                                            <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" list="alloy_list" placeholder="-- พิมพ์ค้นหาหรือระบุ ALLOY --" autocomplete="off" required>
                                            
                                            <!-- 2. สร้าง <datalist> เพื่อเก็บตัวเลือกรหัส ALLOY ทั้งหมดจากฐานข้อมูลตาราง CMPSMSTR1 -->
                                            <datalist id="alloy_list">
                                                <?php
                                                    include('dbcon_mats-new.php');
                                                    $sql = "SELECT DISTINCT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC";
                                                    $result = $conn->prepare($sql);
                                                    $result->execute();
                                                    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                        // ดึงค่ารหัส ALLOY ออกมาแสดงในแอตทริบิวต์ value
                                                        echo "<option value='".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."'>";
                                                    }
                                                ?>
                                            </datalist>
                                        </div>
                                    </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Temper <span class="text-danger">*</span></label>
                                                
                                                <!-- 1. เปลี่ยนจาก <select> เป็น <input> และเชื่อมโยงกับ datalist ด้วย list="temper_list" -->
                                                <input type="text" class="form-control" name="inp_temper" id="inp_temper" list="temper_list" placeholder="-- พิมพ์ค้นหาหรือระบุ Temper --" autocomplete="off" required>
                                                
                                                <!-- 2. สร้าง <datalist> เพื่อเก็บตัวเลือกรหัส Temper ทั้งหมดจากฐานข้อมูล -->
                                                <datalist id="temper_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // ดึงค่ารหัส Temper ออกมาแสดงในแอตทริบิวต์ value
                                                            echo "<option value='".htmlspecialchars($row["TEMPER"], ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Thickness (mm) <span class="text-danger">*</span></label>
                                                
                                                <!-- 1. เปลี่ยนจาก <select> เป็น <input> และผูกกับ datalist ด้วยแอตทริบิวต์ list -->
                                                <input type="text" class="form-control" name="inp_thickness" id="inp_thickness" list="thickness_list" placeholder="-- พิมพ์ค้นหาหรือเลือกความหนา --" autocomplete="off">
                                                
                                                <!-- 2. สร้าง <datalist> เพื่อเก็บตัวเลือกความหนาทั้งหมดจาก Database ตาราง THCKMSTR1 -->
                                                <datalist id="thickness_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT THICKNESS FROM THCKMSTR1 ORDER BY THICKNESS ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // กำหนดฟอร์แมตทศนิยม 3 ตำแหน่งเพื่อให้สอดคล้องกับการทำงานของระบบ
                                                            $formatted_thickness = number_format($row["THICKNESS"], 3);
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
                                                
                                                <!-- 1. เปลี่ยนจาก <select> เป็น <input> และผูกกับ datalist ด้วยแอตทริบิวต์ list -->
                                                <input type="text" class="form-control" name="inp_width" id="inp_width" list="width_list" placeholder="-- พิมพ์ค้นหาหรือเลือกความกว้าง --" autocomplete="off">
                                                
                                                <!-- 2. สร้าง <datalist> เพื่อเก็บตัวเลือกทั้งหมดจาก Database -->
                                                <datalist id="width_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT WIDTH FROM DMWDMSTR1 ORDER BY WIDTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // กำหนดให้ value เก็บค่าทศนิยม 3 ตำแหน่งเพื่อให้ผู้ใช้ค้นหาและแสดงผลได้สวยงาม
                                                            $formatted_width = number_format($row["WIDTH"], 3);
                                                            echo "<option value='".htmlspecialchars($formatted_width, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">Length (mm) <span class="text-danger">*</span></label>
                                                
                                                <!-- 1. เปลี่ยนจาก <select> เป็น <input> และผูกกับ datalist ด้วยแอตทริบิวต์ list -->
                                                <input type="text" class="form-control" name="inp_length" id="inp_length" list="length_list" placeholder="-- พิมพ์ค้นหาหรือเลือกความยาว --" autocomplete="off">
                                                
                                                <!-- 2. สร้าง <datalist> เพื่อเก็บตัวเลือกความยาวทั้งหมดจาก Database ตาราง LNGTMSTR1 -->
                                                <datalist id="length_list">
                                                    <?php
                                                        include('dbcon_mats-new.php');
                                                        $sql = "SELECT LENGTH FROM LNGTMSTR1 ORDER BY LENGTH ASC";
                                                        $result = $conn->prepare($sql);
                                                        $result->execute();
                                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                            // กำหนดฟอร์แมตทศนิยม 3 ตำแหน่งให้สอดคล้องกับค่าเดิมของระบบ
                                                            $formatted_length = number_format($row["LENGTH"], 3);
                                                            echo "<option value='".htmlspecialchars($formatted_length, ENT_QUOTES, 'UTF-8')."'>";
                                                        }
                                                    ?>
                                                </datalist>
                                            </div>
                                        </div>
                                   

                                    </div>

                                    <br>
                                    
                                    <div class="form-section-title">2. Product specifications and standards (Product specifications)</div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-input">
                                            <thead>
                                                <tr>
                                                    <th style="width: 15%;">specifications</th>
                                                    <th style="width: 28%;">Plus</th>
                                                    <th style="width: 28%;">Minus</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $elements = array(
                                                    'th' => 'Thickness Tolerane', 'wi' => 'Width Tolerane', 'le' => 'Length Tolerane'
                                                );
                                                foreach ($elements as $key => $label) {
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $label; ?></strong></td>
                                                    <td><input type="number" class="form-control calc-avg" id="<?php echo $key; ?>_p" name="<?php echo $key; ?>_p" placeholder="0.000" step="0.001" min="0"/></td>
                                                    <td><input type="number" class="form-control calc-avg" id="<?php echo $key; ?>_m" name="<?php echo $key; ?>_m" placeholder="0.000" step="0.001" min="0"/></td>
                                                </tr>
                                                <?php } ?>

                                                <tr>
                                                    <td><strong>Flatness</strong></td>
                                                    <td colspan="2"><input type="number" class="form-control calc-avg" id="fl" name="fl" placeholder="Flatness"/></td>

                                                </tr>
                                                 <tr>
                                                    <td><strong>Earing Type</strong></td>
                                                    <td colspan="2"><input type="text" class="form-control calc-avg" id="et" name="et" placeholder="Earing Type"/></td>

                                                </tr>   
                                                 <tr>
                                                    <td><strong>Earing Percentage</strong></td>
                                                    <td colspan="2"><input type="number" class="form-control calc-avg" id="ep" name="ep" placeholder="Earing Percentage"/></td>
                                                </tr>                                                  
                                                 <tr>
                                                    <td><strong>Fixed Process</strong></td>
                                                    <td colspan="2">
                                                        <select class="form-control" id="fxp" name="fxp">
                                                            <option value=""></option>
                                                            <option value="NO">NO</option>
                                                            <option value="BA">BA</option>
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
        alert("Please fill in all the required information");
        return;
    }

    var formData = $("#form_alloy_spec").serialize();

    // ใช้ $.ajax หรือระบุพารามิเตอร์ตัวที่ 4 ของ $.post เป็น "json"
    $.post("model/save_cust_spec_mats.php", formData, function(resp) {
        // ไม่ต้องใช้ JSON.parse(data) ใน try-catch แล้ว เพราะส่งเป็น Object เข้ามาเลย
        if (resp.message === true) {
            alert("The master specification data has been successfully recorded.");
            window.location.assign('customer_spec_std_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred.: " + (resp.error || "Please double-check the information."));
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