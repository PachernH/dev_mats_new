<?php
session_start();
include('dbcon_mats-new.php');

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$datetime = date('Y-m-d H:i:s');

// ดึงข้อมูลเดิมจาก FRNCPRCS1 และ CHRGTRNS1
$row_data = [];
if(!empty($bno)){
    $sql_fetch = "SELECT A.*, B.CTRN_REMARK 
                  FROM FRNCPRCS1 A 
                  LEFT JOIN CHRGTRNS1 B ON A.BATCH_NO = B.BATCH_NO 
                  WHERE A.BATCH_NO = :bno";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->execute([':bno' => $bno]);
    $row_data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Edit Charging Material - Meyer Aluminium</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .form-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; }
        .btn-custom-submit { background-color: #f59e0b; color: #ffffff; border: none; padding: 12px 28px; font-weight: 600; border-radius: 8px; }
        .btn-custom-submit:hover { background-color: #d97706; }
        .bg-total-charge { background-color: #eff6ff !important; font-weight: 700; color: #1e40af; }
        .bg-balance-furnace { background-color: #ecfdf5 !important; font-weight: 700; color: #065f46; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="furnance_mats.php?func=<?php echo $folder_func ?>">Furanace Charging Material</a>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('furnance_mats.php?func=<?php echo $folder_func; ?>')"> ⬅️ Back to Charging Material</button>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <div class="form-card">
                        <h4 class="card-title-sub">📋 General Information (EDITING)</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Charging Date</label>
                                    <input class="form-control" id="Charg_date" type="date" value="<?php echo isset($row_data['BATCH_DATE']) ? date('Y-m-d', strtotime($row_data['BATCH_DATE'])) : ''; ?>" readonly/>
                                    <input id="Charg_date_show" type="hidden" value="<?php echo $datetime; ?>"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Furnace No.</label>
                                    <input class="form-control" id="furnace_no" value="<?php echo htmlspecialchars($row_data['FURNACE'] ?? ''); ?>" readonly />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Batch No (READ-ONLY)</label>
                                    <input class="form-control" id="batch_no" type="text" value="<?php echo htmlspecialchars($row_data['BATCH_NO'] ?? ''); ?>" readonly style="font-weight: 700; color: #2563eb;"/> 
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Material</label>
                                    <select class="form-control" id="material_name" onchange="handleMaterialChange()">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT BOI_PROJECT FROM BOIPRJCT1 
                                                    WHERE BOI_PROJECT <> 'BOI-IMP1' and BOI_PROJECT <> 'BOI-IMP2'
                                                    ORDER BY BOIPRJCT1.BOI_PROJECT ASC";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['MATERIAL_IN'] ?? '') == $row['BOI_PROJECT'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["BOI_PROJECT"])."' $selected>".htmlspecialchars($row["BOI_PROJECT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h4 class="card-title-sub">🧪 Specifications & Charging Materials</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Alloy</label>
                                    <select class="form-control" id="alloy">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['ALLOY'] ?? '') == $row['ALLOY'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALLOY"])."' $selected>".htmlspecialchars($row["ALLOY"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Primary Smelt</label>
                                    <select class="form-control" id="primary_smelt">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY SEQ_NO ASC";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['PRIMARY_SMELT'] ?? '') == $row['ALUMINIUM_SMELT'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"])."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Secondary Smelt</label>
                                    <select class="form-control" id="seconday_smelt">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY SEQ_NO ASC";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['SECONDARY_SMELT'] ?? '') == $row['ALUMINIUM_SMELT'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"])."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Remain From Last Batch (Kg)</label><input class="form-control" id="remain_form" type="number" value="<?php echo $row_data['AL_REMAIN'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge AL Ingot (Kg)</label><input class="form-control" id="charge_ingot" type="number" value="<?php echo $row_data['AL_INGOT'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Product Remelt (Kg)</label><input class="form-control" id="charge_remelt" type="number" value="<?php echo $row_data['COIL_REMELT'] ?? 0; ?>" onkeyup="calculateTotal()" readonly/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Casting Scrap (Kg)</label><input class="form-control" id="charge_casting" type="number" value="<?php echo $row_data['CAST_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Other Scrap (Kg)</label><input class="form-control" id="charge_other" type="number" value="<?php echo $row_data['OTHER_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Scrap Wire (Kg)</label><input class="form-control" id="charge_wire" type="number" value="<?php echo $row_data['SCRAP_WIRE'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Recycle Scrap (Kg)</label><input class="form-control" id="charge_recycle" type="number" value="<?php echo $row_data['RECYCLE_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Master Alloy (Kg)</label><input class="form-control" id="charge_alloy" type="number" value="<?php echo $row_data['MASTER_ALLOY'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12">
                    <div class="form-card" style="border-left: 4px solid #f59e0b;">
                        <h4 class="card-title-sub">📊 Weight Summary Calculations</h4>
                        <div class="form-group mb-4">
                            <label>Total AL Charging (Kg)</label>
                            <input class="form-control bg-total-charge" id="total_charge" type="number" value="<?php echo $row_data['TOTAL_CHARGE'] ?? 0; ?>" readonly/> 
                        </div>
                        <div class="form-group mb-4">
                            <label>Total Dross (Kg)</label>
                            <input class="form-control" id="total_dross" type="number" value="<?php echo $row_data['TOTAL_DROSS'] ?? 0; ?>" onkeyup="calculateBalance()"/> 
                        </div>      
                        <div class="form-group mb-4">
                            <label>Balance in Furnace (Kg)</label>
                            <input class="form-control bg-balance-furnace" id="balance_furnace" type="number" value="<?php echo $row_data['BALANCE_BATCH'] ?? 0; ?>" readonly/> 
                        </div>           
                    </div>

                    <div class="form-card" style="border-left: 4px solid #f59e0b;">
                        <h4 class="card-title-sub">✍️ Additional Remarks</h4>
                        <div class="form-group">
                            <label>Remark / Comments</label>
                            <textarea class="form-control" id="select_detail" rows="4"><?php echo htmlspecialchars($row_data['CTRN_REMARK'] ?? ''); ?></textarea>
                        </div>                       
                        <input id="func" value="<?php echo $folder_func; ?>" type="hidden"/>
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-custom-submit" onclick="update_code()">
                                Update Data
                            </button>        
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

<script>
function calculateTotal() {
    var remain_form = parseFloat($("#remain_form").val()) || 0;
    var charge_ingot = parseFloat($("#charge_ingot").val()) || 0;
    var charge_remelt = parseFloat($("#charge_remelt").val()) || 0;
    var charge_casting = parseFloat($("#charge_casting").val()) || 0;
    var charge_other = parseFloat($("#charge_other").val()) || 0;
    var charge_wire = parseFloat($("#charge_wire").val()) || 0;
    var charge_recycle = parseFloat($("#charge_recycle").val()) || 0;
    var charge_alloy = parseFloat($("#charge_alloy").val()) || 0;
    
    var total = remain_form + charge_ingot + charge_remelt + charge_casting + charge_other + charge_wire + charge_recycle + charge_alloy;
    $("#total_charge").val(total.toFixed(2));
    calculateBalance();
}

function calculateBalance() {
    var totalCharge = parseFloat($("#total_charge").val()) || 0;
    var totalDross = parseFloat($("#total_dross").val()) || 0;
    var balance = totalCharge - totalDross;
    $("#balance_furnace").val(balance.toFixed(2));
}

function handleMaterialChange() {
    var selectedMaterial = $("#material_name").val();
    
    // ดึง Element
    var remainField = document.getElementById("remain_form");
    var ingotField = document.getElementById("charge_ingot");
    var remeltField = document.getElementById("charge_remelt");
    var castingField = document.getElementById("charge_casting");
    var otherField = document.getElementById("charge_other");
    var wireField = document.getElementById("charge_wire");
    var recycleField = document.getElementById("charge_recycle");
    var alloyField = document.getElementById("charge_alloy");
    
    var allFields = [
        remainField, ingotField, remeltField, 
        castingField, otherField, wireField, 
        recycleField, alloyField
    ];

    // ฟังก์ชันเปิดให้คีย์ข้อมูล
    function enableFields(fields) {
        fields.forEach(function(field) {
            field.readOnly = false;
        });
    }

    // ฟังก์ชันล็อกเป็น Read-only และใส่ค่า 0
    function setReadOnlyFields(fields) {
        fields.forEach(function(field) {
            field.readOnly = true;
            field.value = "0";
        });
    }

    // รีเซ็ตทุกฟิลด์ให้พิมพ์ได้ก่อนตรวจสอบเงื่อนไข
    enableFields(allFields);

    if (selectedMaterial === "BOI-MAT1") {
        // ใส่ได้หมด ยกเว้น Scrap Wire และ Recycle Scrap
        setReadOnlyFields([wireField, recycleField]);
    } 
    else if (selectedMaterial === "BOI-MAT2") {
        // ใส่ได้หมด
        enableFields(allFields);
    } 
    else if (selectedMaterial === "BOI-GRS1") {
        // ใส่ได้เฉพาะ Product Remelt, Scrap Wire, Master Alloy ที่เหลือ Read-only
        var allowedFields = [remeltField, wireField, alloyField];
        var readOnlyFields = [remainField, ingotField, castingField, otherField, recycleField];
        
        enableFields(allowedFields);
        setReadOnlyFields(readOnlyFields);
    } 
    else if (selectedMaterial === "NONE-BOI") {
        // ใส่ได้หมด ยกเว้น Scrap Wire และ Recycle Scrap
        setReadOnlyFields([wireField, recycleField]);
    }

    // คำนวณยอดรวมใหม่หลังเปลี่ยนสถานะฟิลด์
    calculateTotal();
}

function update_code(){
    var batchNo = $("#batch_no").val();
    var chargingDate = $("#Charg_date_show").val();
    var furnaceNo = $("#furnace_no").val();
    var material = $("#material_name").val(); 
    var alloy = $("#alloy").val();
    var primarySmelt = $("#primary_smelt").val();
    var secondaySmelt = $("#seconday_smelt").val();
    var remainForm = $("#remain_form").val();
    var chargeIngot = $("#charge_ingot").val();
    var chargeRemelt = $("#charge_remelt").val();
    var chargeCasting = $("#charge_casting").val();
    var chargeOther = $("#charge_other").val();
    var chargeWire = $("#charge_wire").val();
    var chargeRecycle = $("#charge_recycle").val();
    var chargeAlloy = $("#charge_alloy").val();
    var totalCharge = $("#total_charge").val();
    var totalDross = $("#total_dross").val();
    var balanceFurnace = $("#balance_furnace").val();
    var remark = $("#select_detail").val(); // แก้ไขเป็น .val() สำหรับ jQuery
    var data_fun = $("#func").val();
    
    var data_string = batchNo + "*" + chargingDate + "*" + furnaceNo + "*" + material + "*" + 
                      alloy + "*" + primarySmelt + "*" + secondaySmelt + "*" +
                      remainForm + "*" + chargeIngot + "*" + chargeRemelt + "*" + 
                      chargeCasting + "*" + chargeOther + "*" + chargeWire + "*" + 
                      chargeRecycle + "*" + chargeAlloy + "*" + totalCharge + "*" + 
                      totalDross + "*" + balanceFurnace + "*" + remark;
    
    if(batchNo == ""){
        alert("ไม่พบรหัส Batch No");    
        return;
    }
    
    $.ajax({
        url: "model/update_furnace_charging_mats.php",
        type: "POST",
        data: { data_tag1: data_string },
        dataType: "json",
        success: function(resp) {
            if(resp.message){
                alert("The information has been successfully updated.");    
                window.location.assign('furnance_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Unable to update."));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred in the system: " + error);
        }
    });                              
}

$(document).ready(function(){
    // ตรวจสอบเงื่อนไข Read-only ของ Material เดิมทันทีที่เปิดหน้า Edit
    handleMaterialChange();
});
</script>
</html>