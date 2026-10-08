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
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f4f7f6; }
        /* แก้ไขจาก !apply เป็น !important เพื่อให้ระยะขอบทำงานถูกต้อง */
        .main-panel .content { padding: 20px 20px !important; }
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Add information Technical Practice Batch Annealing </h4>
                                <p class="category">Standard specifications and requirements, 3 decimal places.</p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_spec" onsubmit="return false;">
                                    
                                    <div class="form-section-title">1. Technical Practice Batch Annealing Information</div>
                                    <div class="modal-body" style="padding: 20px 25px;">    
                                        <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label style="color: #475569; font-weight: 600;">Product<span class="text-danger">*</span></label>
                                                        <select class="form-control" id="in_pro" name="in_pro" required>
                                                            <option value=""></option>
                                                            <option value="CC">CC</option>
                                                            <option value="SH">SH</option>
                                                            <option value="CO">CO</option>
                                                        </select>                                                            
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="in_alloy" id="in_alloy" list="alloy_list" placeholder="-- พิมพ์ค้นหาหรือระบุ ALLOY --" autocomplete="off" required>
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
                                        </div>                                    

                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Temper Initial<span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="in_temper" id="in_temper" list="temper_list" placeholder="-- พิมพ์ค้นหาหรือระบุ Temper --" autocomplete="off" required>
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
                                                        <label style="color: #475569; font-weight: 600;">Temper Target <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" name="in_temper2" id="in_temper2" list="temper_list2" placeholder="-- พิมพ์ค้นหาหรือระบุ Temper --" autocomplete="off" required>
                                                        <datalist id="temper_list2">
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
                                        </div>
                                    
                                    
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Range From (mm) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.001" class="form-control" name="in_range_f" id="in_range_f" placeholder="00.000" required>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label style="color: #475569; font-weight: 600;">Range TO (mm) <span class="text-danger">*</span></label>
                                                    <input type="number" step="0.001" class="form-control" name="in_range_t" id="in_range_t" placeholder="00.000" required>                                          
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
                                                            <th style="width: 25%; vertical-align: middle;">
                                                                &nbsp;
                                                            </th>
                                                            <th><center>Time(minute)</center></th>
                                                            <th><center>Temperature(Degree C)</center></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td align="center"><strong>Heating Up</strong></td>
                                                            <td><input type="number" class="form-control text-center" id="h_tmc" name="h_tmc" placeholder="Time(minute)"/></td>
                                                            <td><input type="number" class="form-control text-center" id="h_tdc" name="h_tdc" placeholder="Temperature(Degree C)"/></td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center"><strong>Soaking</strong></td>
                                                            <td><input type="number" class="form-control text-center" id="s_tmc" name="s_tmc" placeholder="Time(minute)"/></td>
                                                            <td><input type="number" class="form-control text-center" id="s_tdc" name="s_tdc" placeholder="Temperature(Degree C)"/></td>
                                                        </tr>     
                                                        <tr>
                                                            <td align="center"><strong>Range Type</strong></td>
                                                            <!-- เพิ่มคลาส bg-readonly และคำสั่ง readonly เพื่อป้องกันการพิมพ์แก้ไขเอง -->
                                                            <td colspan="2"><input type="text" class="form-control bg-readonly" id="rt" name="rt" placeholder="ระบุอัตโนมัติตามประเภท Product" readonly/></td>
                                                        </tr>                           
                                                        <tr>
                                                            <td align="center"><strong>Minimum Weight (KG)</strong></td>
                                                            <td colspan="2"><input type="number" step="0.01" class="form-control" id="mw" name="mw" placeholder="Minimum Weight"/></td>
                                                        </tr>                        
                                                    </tbody>
                                                </table>
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 25%; vertical-align: middle;">
                                                                &nbsp;
                                                            </th>
                                                            <th><center>FROM</center></th>
                                                            <th><center>TO</center></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td align="center"><strong>O2 Purging Time (Minute)</strong></td>
                                                            <td><input type="number" class="form-control text-center" id="o2p_f" name="o2p_f" placeholder="FROM"/></td>
                                                            <td><input type="number" class="form-control text-center" id="o2p_t" name="o2p_t" placeholder="TO"/></td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center"><strong>O2 %</strong></td>
                                                            <td><input type="number" step="0.01" class="form-control text-center" id="o2_f" name="o2_f" placeholder="0.00" onchange="if(this.value!='') this.value=parseFloat(this.value).toFixed(2);"/></td>
                                                            <td><input type="number" step="0.01" class="form-control text-center" id="o2_t" name="o2_t" placeholder="0.00" onchange="if(this.value!='') this.value=parseFloat(this.value).toFixed(2);"/></td>
                                                        </tr>    
                                                        <tr>
                                                            <td align="center"><strong>Program</strong></td>
                                                            <td colspan="2"><input type="text" class="form-control" id="pg" name="pg" placeholder="Program"/></td>
                                                        </tr>                                                                                                 
                                                    </tbody>
                                                </table>
                                            </div>
                                    <div class="form-section-title">3. Control by work piece setting</div>
                                            <div class="modal-body" style="padding: 20px 25px;">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 25%; vertical-align: middle;">
                                                                &nbsp;
                                                            </th>
                                                            <th><center>FROM</center></th>
                                                            <th><center>TO</center></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td align="center"><strong>Work Piece Heating up</strong></td>
                                                            <td colspan="2"><input type="number" class="form-control" id="wph" name="wph" placeholder="Work Piece Heating up"/></td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center"><strong>Work Piece Soaking</strong></td>
                                                            <td><input type="number" class="form-control text-center" id="wps_f" name="wps_f" placeholder="Work Piece Soaking FROM"/></td>
                                                            <td><input type="number" class="form-control text-center" id="wps_t" name="wps_t" placeholder="Work Piece Soaking TO"/></td>
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
$(document).ajaxStart(function() {
    // โหลด jQuery Ready หรือจัดการระบบร่วมได้หากต้องการ
});

$(document).ready(function() {
    // โค้ดสร้างเงื่อนไขตรวจสอบการเปลี่ยนค่า Product
    $("#in_pro").change(function() {
        var selectedProduct = $(this).val();
        
        if (selectedProduct === "CC" || selectedProduct === "SH") {
            $("#rt").val("W");
        } else if (selectedProduct === "CO") {
            $("#rt").val("T");
        } else {
            $("#rt").val(""); // เคลียร์เป็นค่าว่างหากยังไม่ได้เลือกตัวเลือกใดๆ
        }
    });
});

function submitAlloyData() {
    // 1. ตรวจสอบข้อมูลบังคับกรอก (Validation) เบื้องต้น
    var in_pro = $("#in_pro").val();
    var in_alloy = $("#in_alloy").val();
    var in_temper = $("#in_temper").val();
    var in_temper2 = $("#in_temper2").val();
    var in_range_f = $("#in_range_f").val();
    var in_range_t = $("#in_range_t").val();
    var data_fun = $("#func").val();

    if (in_pro == "" || in_alloy == "" || in_temper == "" || in_temper2 == "" || in_range_f == "" || in_range_t == "") {
        alert("Please fill in all required fields (*).");
        return false;
    }

    // 2. จัดกลุ่ม Data จาก Form ส่งแบบ AJAX POST ไปยังไฟล์บันทึกหลังบ้าน
    var formData = $("#form_alloy_spec").serialize();

    $.ajax({
        url: "model/save_practice_spec_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
            console.log("Response:", response);
            if (response.status === "success" || response.message) {
                alert("Data has been successfully.");
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