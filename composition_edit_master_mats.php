<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า Key เพื่อนำมาดึงข้อมูลการแก้ไข
$get_alloy = isset($_GET['ALLOY']) ? trim($_GET['ALLOY']) : '';
$get_cstmsppl = isset($_GET['CSTMSPPL_ID']) ? trim($_GET['CSTMSPPL_ID']) : '';

$data_com = array();
if(!empty($get_alloy) && !empty($get_cstmsppl)){
    // เรียกฟังก์ชันดึงข้อมูลตามภาพ function.jpg
    $data_com = RT_Composition($get_alloy, $get_cstmsppl);
}

// ถ้าไม่มีข้อมูลให้เด้งกลับป้องกัน Error
if(empty($data_com)){
    echo "<script>alert('No information was found to be corrected.'); window.location.assign('composition_master_mats.php?func=".urlencode($folder_func)."');</script>";
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
        .card .header { padding: 20px 20px 0 20px; border-bottom: 1px solid #757373; padding-bottom: 15px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 20px; padding-left: 5px; border-left: 4px solid #e67e22; }
        .table-input th { background-color: #f8fafc; text-align: center; vertical-align: middle !important; color: #64748b; font-weight: 600; }
        .table-input td { vertical-align: middle !important; }
        .bg-readonly { background-color: #f1f5f9 !important; cursor: not-allowed; }
        
        /* 🎨 เพิ่ม Class สำหรับเน้นสีช่องที่มีข้อมูล */
        .has-value {
            background-color: #e8f5e9 !important; /* สีเขียวอ่อน สบายตา */
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20;
        }
        /* สำหรับช่อง Readonly ที่มีค่า */
        .has-value-readonly {
            background-color: #e3f2fd !important; /* สีฟ้าอ่อน สำหรับช่องที่ระบบคำนวณให้ */
            border-color: #90caf9 !important;
            font-weight: bold;
            color: #0d47a1;
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
                    <a class="navbar-brand" href="#">Edit Alloy Composition Master</a>
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
                                <h4 class="title" style="font-weight: 600; color: #1e293b;">Correct the aluminum component specification information. (Edit Alloy Entry)</h4>
                                <p class="category">Edit the master data and specify the standard value to 3 decimal places. <span style="color:#1b5e20; font-weight:bold;">(The green/blue boxes are those that already have information entered.)</span></p>
                            </div>
                            
                            <div class="content" style="padding: 25px;">
                                <form id="form_alloy_composition">
                                    <input type="hidden" name="old_alloy" value="<?php echo htmlspecialchars($data_com[1], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="old_cstmsppl" value="<?php echo htmlspecialchars($data_com[2], ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="form-section-title">1. Product and customer key information (Master Information)</div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALLOY NO <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="inp_alloy" id="inp_alloy" value="<?php echo htmlspecialchars($data_com[1], ENT_QUOTES, 'UTF-8'); ?>" required onkeyup="this.value = this.value.toUpperCase()">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">CUSTOMER ID / SUPPLIER ID <span class="text-danger">*</span></label>
                                                <select class="form-control" name="inp_cstmsppl" id="inp_cstmsppl">
                                                        <?php
                                                            include('dbcon_mats-new.php');
                                                            $sql = "SELECT CSTMSPPL_ID, CSTMSPPL_TYPE, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_TYPE = 'B' ORDER BY CSTMSPPL_ID ASC";
                                                            $result = $conn->prepare($sql);
                                                            $result->execute();
                                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                                $selected = ($row["CSTMSPPL_ID"] == $data_com[2]) ? "selected" : "";
                                                                echo "<option value='".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["CSTMSPPL_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["CONSIGNEE_COMPANY"], ENT_QUOTES, 'UTF-8')."</option>";
                                                            }
                                                        ?>
                                                </select>  
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">GRAVITY (DENSITY)</label>
                                                <input type="number" class="form-control color-trigger" name="inp_gravity" id="inp_gravity" value="<?php echo number_format((float)$data_com[4], 3, '.', ''); ?>" step="0.001" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">ALUMINIUM (AL %)</label>
                                                <input type="number" class="form-control color-trigger" name="inp_al" id="inp_al" value="<?php echo number_format((float)$data_com[5], 3, '.', ''); ?>" step="0.001" min="0" max="100">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color: #475569; font-weight: 600;">TITANIUM (TI %)</label>
                                                <input type="number" class="form-control color-trigger" name="inp_ti" id="inp_ti" value="<?php echo number_format((float)$data_com[6], 3, '.', ''); ?>" step="0.001" min="0" max="100">
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
                                                    'al' => array('label' => 'Aluminium (Al)', 'min' => 7, 'avg' => 41, 'max' => 8), 
                                                    'fe' => array('label' => 'Iron (Fe)', 'min' => 9, 'avg' => 10, 'max' => 11),
                                                    'si' => array('label' => 'Silicon (Si)', 'min' => 12, 'avg' => 13, 'max' => 14), 
                                                    'mn' => array('label' => 'Manganese (Mn)', 'min' => 15, 'avg' => 16, 'max' => 17), 
                                                    'mg' => array('label' => 'Magnesium (Mg)', 'min' => 18, 'avg' => 19, 'max' => 20), 
                                                    'cr' => array('label' => 'Chromium (Cr)', 'min' => 21, 'avg' => 22, 'max' => 23), 
                                                    'cu' => array('label' => 'Copper (Cu)', 'min' => 24, 'avg' => 25, 'max' => 26), 
                                                    'zn' => array('label' => 'Zinc (Zn)', 'min' => 27, 'avg' => 28, 'max' => 29), 
                                                    'pb' => array('label' => 'Lead (Pb)', 'min' => 30, 'avg' => 31, 'max' => 32)
                                                );

                                                foreach ($elements as $key => $info) {
                                                    $val_min = number_format((float)$data_com[$info['min']], 3, '.', '');
                                                    $val_avg = number_format((float)$data_com[$info['avg']], 3, '.', '');
                                                    $val_max = number_format((float)$data_com[$info['max']], 3, '.', '');
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $info['label']; ?></strong></td>
                                                    <td><input type="number" class="form-control calc-avg color-trigger" id="<?php echo $key; ?>_min" name="<?php echo $key; ?>_min" value="<?php echo $val_min; ?>" step="0.001" min="0" oninput="calculateAvg('<?php echo $key; ?>')"/></td>
                                                    <td><input type="number" class="form-control bg-readonly color-trigger-readonly" id="<?php echo $key; ?>_avg" name="<?php echo $key; ?>_avg" value="<?php echo $val_avg; ?>" readonly/></td>
                                                    <td><input type="number" class="form-control calc-avg color-trigger" id="<?php echo $key; ?>_max" name="<?php echo $key; ?>_max" value="<?php echo $val_max; ?>" step="0.001" min="0" oninput="calculateAvg('<?php echo $key; ?>')"/></td>
                                                </tr>
                                                <?php } ?>

                                                <?php 
                                                $max_only_elements = array(
                                                    'as' => array('label' => 'Arsenic (As)', 'max' => 33), 
                                                    'ni' => array('label' => 'Nickel (Ni)', 'max' => 34), 
                                                    'sn' => array('label' => 'Tin (Sn)', 'max' => 35), 
                                                    'sb' => array('label' => 'Antimony (Sb)', 'max' => 36), 
                                                    'be' => array('label' => 'Beryllium (Be)', 'max' => 37), 
                                                    'bi' => array('label' => 'Bismuth (Bi)', 'max' => 38), 
                                                    'cd' => array('label' => 'Cadmium (Cd)', 'max' => 39)
                                                );
                                                foreach ($max_only_elements as $key => $info) {
                                                    $val_max = number_format((float)$data_com[$info['max']], 3, '.', '');
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo $info['label']; ?></strong></td>
                                                    <td><input type="number" class="form-control bg-readonly" id="<?php echo $key; ?>_min" name="<?php echo $key; ?>_min" value="0.000" readonly/></td>
                                                    <td><input type="number" class="form-control bg-readonly" id="<?php echo $key; ?>_avg" name="<?php echo $key; ?>_avg" value="0.000" readonly/></td>
                                                    <td><input type="number" class="form-control color-trigger" id="<?php echo $key; ?>_max" name="<?php echo $key; ?>_max" value="<?php echo $val_max; ?>" step="0.001" min="0"/></td>
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
    // 🎨 ฟังก์ชันสำหรับเช็คและเปลี่ยนสีพื้นหลังอัตโนมัติ
    function checkInputColors() {
        // สำหรับช่องที่กรอกได้ปกติ (เปลี่ยนเป็นสีเขียวอ่อนเมื่อ > 0)
        $('.color-trigger').each(function() {
            var val = parseFloat($(this).val());
            if (!isNaN(val) && val > 0) {
                $(this).addClass('has-value');
            } else {
                $(this).removeClass('has-value');
            }
        });

        // สำหรับช่อง Readonly/Average (เปลี่ยนเป็นสีฟ้าอ่อนเมื่อ > 0)
        $('.color-trigger-readonly').each(function() {
            var val = parseFloat($(this).val());
            if (!isNaN(val) && val > 0) {
                $(this).addClass('has-value-readonly');
            } else {
                $(this).removeClass('has-value-readonly');
            }
        });
    }

    // เรียกทำงานตอนโหลดหน้าเว็บครั้งแรกเพื่อจับคู่สีข้อมูลเก่า
    $(document).ready(function() {
        checkInputColors();
        
        // ผูก Event เมื่อมีการคีย์ข้อมูล ให้เปลี่ยนสีทันทีแบบ Real-time
        $('.color-trigger').on('input change', function() {
            var val = parseFloat($(this).val());
            if (!isNaN(val) && val > 0) {
                $(this).addClass('has-value');
            } else {
                $(this).removeClass('has-value');
            }
        });
    });

    function calculateAvg(elementKey) {
        var minVal = parseFloat(document.getElementById(elementKey + '_min').value);
        var maxVal = parseFloat(document.getElementById(elementKey + '_max').value);
        var avgInput = document.getElementById(elementKey + '_avg');

        if (!isNaN(minVal) && !isNaN(maxVal)) {
            var avg = (minVal + maxVal) / 2;
            avgInput.value = avg.toFixed(3);
        } else if (!isNaN(maxVal)) {
            avgInput.value = (maxVal / 2).toFixed(3);
        } else {
            avgInput.value = "";
        }
        
        // หลังคำนวณเสร็จให้รีเฟรชสีช่องเฉลี่ยวัดผลทันที
        checkInputColors();
    }

function submitAlloyData() {
    var alloy = document.getElementById('inp_alloy').value.trim();
    var cstmsppl = document.getElementById('inp_cstmsppl').value.trim();
    var data_fun = document.getElementById('func').value;

    if (alloy === "" || cstmsppl === "") {
        alert("Please fill in the Alloy code and Customer code completely");
        return;
    }

    var formData = $("#form_alloy_composition").serialize();

    $.post("model/update_composition_system.php", formData, function(resp) {
        if (resp.message === true) {
            alert("The master specification information has been corrected");
            window.location.assign('composition_master_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred: " + (resp.error || "Please double-check the information"));
        }
    }, "json")
    .fail(function(xhr, status, error) {
        alert("Technical system malfunction: " + error);
    });
} 
</script>
</html>