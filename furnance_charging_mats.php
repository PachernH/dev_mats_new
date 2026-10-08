<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$datetime = date('Y-m-d H:i:s');

?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        /* Modern UI Style Adjustments */
        body { 
             font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { 
            padding: 20px 20px !important; 
        }
        
        /* Form Card Styling */
        .form-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
        }
        
        /* Form Controls */
        .form-group label {
            font-weight: 500;
            color: #475569;
            margin-bottom: 6px;
            font-size: 14px;
            text-transform: none;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }
        textarea.form-control {
            height: auto;
        }
        
        /* Readonly & Disabled States */
        input:readonly {
            background-color: #f1f5f9 !important;
            color: #64748b;
            cursor: not-allowed;
        }
        input:disabled, select:disabled {
            background-color: #f8fafc !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Highlighted Total Fields */
        .bg-total-charge {
            background-color: #eff6ff !important;
            border-color: #bfdbfe !important;
            color: #1e40af !important;
            font-weight: 700 !important;
            font-size: 16px;
        }
        .bg-balance-furnace {
            background-color: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #065f46 !important;
            font-weight: 700 !important;
            font-size: 16px;
        }
        
        /* Buttons */
        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .btn-custom-submit {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px 28px;
            font-weight: 600;
            font-size: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }
        .btn-custom-submit:hover {
            background-color: #1d4ed8;
            box-shadow: 0 6px 10px -1px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }
        
        /* Utility */
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-2 { margin-top: 0.5rem; }

        /* แก้ปัญหา Modal โดน Overlay บัง */
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .modal {
            z-index: 1050 !important;
        }        
    </style>
</head>

<body>
<div class="wrapper">
    
<?php $menu = 'A4';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="furnance_mats.php?func=<?php echo $folder_func ?>">Furanace Charging Material</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>   

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-search" id="btn_search_kanban" onclick="window.location.assign('furnance_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back to Charging Material
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    
                    <div class="form-card">
                        <h4 class="card-title-sub">📋 General Information</h4>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="Charg_date">Charging Date</label>
                                    <input class="form-control" id="Charg_date" name="Charg_date" value="<?php echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8'); ?>" type="date" readonly/>
                                    <input class="form-control" name="Charg_date_show" id="Charg_date_show" type="hidden" value="<?php echo htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8'); ?>" disabled/>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="furnace_no">Furnace No. <span style="color:#ef4444;">*</span></label>
                                    <select class="form-control" id="furnace_no" onchange="generateBatchNo()">
                                        <option value=""></option>
                                        <option value="1">FURNACE #01</option>
                                        <option value="2">FURNACE #02</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="batch_no">Batch No</label>
                                    <input class="form-control" name="batch_no" id="batch_no" type="text" readonly style="font-weight: 600; color: #1e293b;"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="material_name">Material In</label>
                                    <select class="form-control" id="material_name" onchange="handleMaterialChange()">
                                        <option value=""></option>
                                        <?php
                                            include('dbcon_mats-new.php');
                                            $sql = "SELECT BOI_PROJECT FROM BOIPRJCT1 
                                                    WHERE BOI_PROJECT <> 'BOI-IMP1' and BOI_PROJECT <> 'BOI-IMP2'
                                                    ORDER BY BOIPRJCT1.BOI_PROJECT ASC";
                                            $result = $conn->prepare($sql);
                                            $result->execute();
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='".htmlspecialchars($row["BOI_PROJECT"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["BOI_PROJECT"], ENT_QUOTES, 'UTF-8')."</option>";
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
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="alloy">Alloy</label>
                                    <select class="form-control" id="alloy">
                                        <option value=""></option>
                                        <?php
                                            include('dbcon_mats-new.php');
                                            $sql = "SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY";
                                            $result = $conn->prepare($sql);
                                            $result->execute();
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="primary_smelt">Primary Smelt Country</label>
                                    <select class="form-control" id="primary_smelt">
                                        <option value=""></option>
                                        <?php
                                            include('dbcon_mats-new.php');
                                            $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                            $result = $conn->prepare($sql);
                                            $result->execute();
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="seconday_smelt">Secondary Smelt Country</label>
                                    <select class="form-control" id="seconday_smelt">
                                        <option value=""></option>
                                        <?php
                                            include('dbcon_mats-new.php');
                                            $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                            $result = $conn->prepare($sql);
                                            $result->execute();
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr style="border-top: 1px dashed #e2e8f0; margin: 15px 0;">

                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="remain_form">Remain From Last Batch (Kg)</label>
                                    <input class="form-control" name="remain_form" id="remain_form" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_ingot">Charge AL Ingot (Kg)</label>
                                    <input class="form-control" name="charge_ingot" id="charge_ingot" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_remelt">Charge Product Remelt (Kg)</label>
                                    <div class="input-group">
                                        <input class="form-control" name="charge_remelt" id="charge_remelt" type="number" onkeyup="calculateTotal()"/> 
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-primary" onclick="openRemeltModal()" style="height: 42px; border-radius: 0 8px 8px 0;">
                                                🔍 Product Remelt
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_casting">Charge Casting Process Scrap (Kg)</label>
                                    <input class="form-control" name="charge_casting" id="charge_casting" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_other">Charge Other Process Scrap (Kg)</label>
                                    <input class="form-control" name="charge_other" id="charge_other" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_wire">Charge Scrap Wire (Talon) (Kg)</label>
                                    <input class="form-control" name="charge_wire" id="charge_wire" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_recycle">Charge Recycle Scrap (Recycle Talon) (Kg)</label>
                                    <input class="form-control" name="charge_recycle" id="charge_recycle" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_alloy">Charge Master Alloy (Kg)</label>
                                    <input class="form-control" name="charge_alloy" id="charge_alloy" type="number" onkeyup="calculateTotal()"/> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12">
                    
                    <div class="form-card" style="border-left: 4px solid #2563eb;">
                        <h4 class="card-title-sub">📊 Weight Summary Calculations</h4>
                        
                        <div class="form-group mb-4">
                            <label for="total_charge" style="font-size:14px; font-weight:600;">Total AL Charging (Kg)</label>
                            <input class="form-control bg-total-charge" name="total_charge" id="total_charge" type="number" readonly/> 
                        </div>

                        <div class="form-group mb-4">
                            <label for="total_dross" style="font-size:14px; font-weight:600; color: #b91c1c;">Total Dross (Kg)</label>
                            <input class="form-control" style="border-color:#fca5a5;" name="total_dross" id="total_dross" type="number" onkeyup="calculateBalance()"/> 
                        </div>      
                        
                        <div class="form-group mb-4">
                            <label for="balance_furnace" style="font-size:14px; font-weight:600;">Balance in Furnace (Kg)</label>
                            <input class="form-control bg-balance-furnace" name="balance_furnace" id="balance_furnace" type="number" readonly/> 
                        </div>           
                    </div>

                    <div class="form-card" style="border-left: 4px solid #2563eb;">
                        <h4 class="card-title-sub">✍️ Additional Remarks</h4>
                        <div class="form-group">
                            <label for="select_detail">Remark / Comments</label>
                            <textarea class="form-control" name="select_detail" id="select_detail" rows="4"></textarea>
                        </div>                       

                        <div class="form-group">
                            <input class="form-control" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>" type="hidden"/>
                        </div>
                        
                        <div class="text-right mt-2">
                            <button type="button" class="btn btn-custom-submit" id="create_coderice" onclick="create_code()">
                                💾 Save Data
                            </button>        
                        </div>
                    </div>

                </div>
            </div>
        </div>
<input type="hidden" id="selected_product_no" name="selected_product_no" value="" />
    <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Modal Remelt Selection -->
<div class="modal fade" id="remeltModal" tabindex="-1" role="dialog" aria-labelledby="remeltModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="remeltModalLabel" style="font-weight:600;">📋 Select an item Charge Product Remelt</h4>
            </div>
            <div class="modal-body">
                <!-- ส่วนตัวกรอง (Filter & Sort Control) -->
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-4 col-sm-12">
                        <label for="filterProductID" style="font-size: 12px; color: #64748b;">Product ID</label>
                        <select id="filterProductID" class="form-control" onchange="applyRemeltFilter()">
                            <option value="">Select Product Type</option>
                            <option value="CC">CC</option>
                            <option value="CO">CO</option>
                            <option value="SH">SH</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label for="sortWeight" style="font-size: 12px; color: #64748b;">Order by weight. (Weight)</label>
                        <select id="sortWeight" class="form-control" onchange="applyRemeltFilter()">
                            <option value="default">Latest date (Default)</option>
                            <option value="asc">Weight : Min ➡️ Max</option>
                            <option value="desc">Weight : Max ➡️ Min</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label for="searchProductNo" style="font-size: 12px; color: #64748b;">Search Product No.</label>
                        <input type="text" id="searchProductNo" class="form-control" placeholder="Print Product No..." onkeyup="applyRemeltFilter()">
                    </div>
                </div>

                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover" id="tableRemelt">
                        <thead>
                            <tr style="background-color: #f1f5f9; color: #1e293b;">
                                <th>Product No.</th>
                                <th>Product ID</th>
                                <th class="text-right">Weight (Kg)</th>
                                <th>Respons Type</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="remeltListBody">
                            <!-- Data remelt -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
// ดักจับการคลิกปุ่ม Choose ในตาราง Remelt
$(document).on('click', '.btn-btn-select-remelt', function() {
    var weight = $(this).attr('data-weight');
    var productNo = $(this).attr('data-productno');
    
    // หยอดค่าน้ำหนัก
    $("#charge_remelt").val(weight);
    
    // หยอดค่า Product No ลงใน Hidden Input
    if($("#selected_product_no").length > 0) {
        $("#selected_product_no").val(productNo);
    }
    
    // คำนวณยอดรวมใหม่
    calculateTotal();
    
    // ปิด Modal
    $("#remeltModal").modal("hide");
});

function calculateTotal() {
    var remain_form = parseFloat(document.getElementById("remain_form").value) || 0;
    var charge_ingot = parseFloat(document.getElementById("charge_ingot").value) || 0;
    var charge_remelt = parseFloat(document.getElementById("charge_remelt").value) || 0;
    var charge_casting = parseFloat(document.getElementById("charge_casting").value) || 0;
    var charge_other = parseFloat(document.getElementById("charge_other").value) || 0;
    var charge_wire = parseFloat(document.getElementById("charge_wire").value) || 0;
    var charge_recycle = parseFloat(document.getElementById("charge_recycle").value) || 0;
    var charge_alloy = parseFloat(document.getElementById("charge_alloy").value) || 0;
    
    var total = remain_form + charge_ingot + charge_remelt + charge_casting + charge_other + charge_wire + charge_recycle + charge_alloy;
    document.getElementById("total_charge").value = total.toFixed(2);
    
    calculateBalance();
}

function calculateBalance() {
    var totalCharge = parseFloat(document.getElementById("total_charge").value) || 0;
    var totalDross = parseFloat(document.getElementById("total_dross").value) || 0;
    
    var balance = totalCharge - totalDross;
    document.getElementById("balance_furnace").value = balance.toFixed(2);
}

function handleMaterialChange() {
    var selectedMaterial = document.getElementById("material_name").value;
    
    // ดึง Element ของฟิลด์ต่างๆ
    var remainField = document.getElementById("remain_form");
    var ingotField = document.getElementById("charge_ingot");
    var remeltField = document.getElementById("charge_remelt");
    var castingField = document.getElementById("charge_casting");
    var otherField = document.getElementById("charge_other");
    var wireField = document.getElementById("charge_wire");
    var recycleField = document.getElementById("charge_recycle");
    var alloyField = document.getElementById("charge_alloy");
    
    // รายการ Field ทั้งหมดเพื่อการจัดการที่ง่ายขึ้น
    var allFields = [
        remainField, ingotField, remeltField, 
        castingField, otherField, wireField, 
        recycleField, alloyField
    ];

    // ฟังก์ชันช่วยเปิดการแก้ไข (Editable)
    function enableFields(fields) {
        fields.forEach(function(field) {
            field.readOnly = false;
        });
    }

    // ฟังก์ชันช่วยตั้งค่า Read-only และล้างค่าเป็น 0
    function setReadOnlyFields(fields) {
        fields.forEach(function(field) {
            field.readOnly = true;
            field.value = "0";
        });
    }

    // รีเซ็ตทุกฟิลด์ให้พิมพ์ได้เป็นค่าเริ่มต้นก่อนตรวจเงื่อนไข
    enableFields(allFields);

    if (selectedMaterial === "BOI-MAT1") {
        // ใส่ได้หมด ยกเว้น Scrap Wire และ Recycle Scrap ให้เป็น Read-only
        setReadOnlyFields([wireField, recycleField]);
    } 
    else if (selectedMaterial === "BOI-MAT2") {
        // ใส่ได้หมด (เปิดให้แก้ไขได้ทุกตัว)
        enableFields(allFields);
    } 
    else if (selectedMaterial === "BOI-GRS1") {
        // ใส่ได้เฉพาะ Product Remelt, Scrap Wire, Master Alloy ที่เหลือเป็น Read-only
        var allowedFields = [remeltField, wireField, alloyField];
        var readOnlyFields = [remainField, ingotField, castingField, otherField, recycleField];
        
        enableFields(allowedFields);
        setReadOnlyFields(readOnlyFields);
    } 
    else if (selectedMaterial === "NONE-BOI") {
        // ใส่ได้หมด ยกเว้น Scrap Wire และ Recycle Scrap ให้เป็น Read-only
        setReadOnlyFields([wireField, recycleField]);
    }

    // คำนวณยอดรวมใหม่หลังจากปรับเปลี่ยนค่าในฟิลด์
    calculateTotal();
}

function generateBatchNo() {
    var furnaceNo = document.getElementById("furnace_no").value;
    var chargingDate = document.getElementById("Charg_date").value;
    
    if(furnaceNo == "" || chargingDate == "") {
        return;
    }
    
    var dateObj = new Date(chargingDate);
    var year = dateObj.getFullYear().toString().slice(-2);
    var month = ("0" + (dateObj.getMonth() + 1)).slice(-2);
    var day = ("0" + dateObj.getDate()).slice(-2);
    var dateCode = year + month + day;
    
    $.ajax({
        url: "model/get_last_batch_sequence_mats.php",
        type: "POST",
        data: {
            furnace_no: furnaceNo,
            charging_date: chargingDate
        },
        dataType: "json",
        success: function(response) {
            var sequence = response.last_sequence || 0;
            var nextSeq = parseInt(sequence) + 1;
            var seqFormatted = ("0" + nextSeq).slice(-2);
            var batchNo = "M" + dateCode + "-" + furnaceNo + "-" + seqFormatted;
            document.getElementById("batch_no").value = batchNo;
        },
        error: function() {
            var batchNo = "M" + dateCode + "-" + furnaceNo + "-01";
            document.getElementById("batch_no").value = batchNo;
        }
    });
}

function create_code(){
    var batchNo = document.getElementById("batch_no").value;
    var chargingDate = document.getElementById("Charg_date_show").value;
    var furnaceNo = document.getElementById("furnace_no").value;
    var material = document.getElementById("material_name").value; 
    var alloy = document.getElementById("alloy").value;
    var primarySmelt = document.getElementById("primary_smelt").value;
    var secondaySmelt = document.getElementById("seconday_smelt").value;
    var remainForm = document.getElementById("remain_form").value;
    var chargeIngot = document.getElementById("charge_ingot").value;
    var chargeRemelt = document.getElementById("charge_remelt").value;
    var chargeCasting = document.getElementById("charge_casting").value;
    var chargeOther = document.getElementById("charge_other").value;
    var chargeWire = document.getElementById("charge_wire").value;
    var chargeRecycle = document.getElementById("charge_recycle").value;
    var chargeAlloy = document.getElementById("charge_alloy").value;
    var totalCharge = document.getElementById("total_charge").value;
    var totalDross = document.getElementById("total_dross").value;
    var balanceFurnace = document.getElementById("balance_furnace").value;
    var remark = document.getElementById("select_detail").value;
    var data_fun = document.getElementById("func").value;
    
    var productNo = document.getElementById("selected_product_no") ? document.getElementById("selected_product_no").value : "";

    var data_string = batchNo + "*" + chargingDate + "*" + furnaceNo + "*" + material + "*" + 
                  alloy + "*" + primarySmelt + "*" + secondaySmelt + "*" +
                  remainForm + "*" + chargeIngot + "*" + chargeRemelt + "*" + 
                  chargeCasting + "*" + chargeOther + "*" + chargeWire + "*" + 
                  chargeRecycle + "*" + chargeAlloy + "*" + totalCharge + "*" + 
                  totalDross + "*" + balanceFurnace + "*" + remark + "*" + productNo;              

    
    if(batchNo == "" || furnaceNo == ""){
        alert("Please select a Furnace No. to generate the Batch No. before saving.");    
        return;
    } else {    
        $.ajax({
            url: "model/add_furnace_charging_mats.php",
            type: "POST",
            data: {
                data_tag1: data_string
            },
            dataType: "json",
            success: function(resp) {
                if(resp.message){
                    alert("Data has been successfully.");    
                    window.location.assign('furnance_mats.php?func=' + encodeURIComponent(data_fun));
                } else {
                    var errorMsg = resp.error || "Please check the updated information.";
                    alert("An error occurred: " + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                alert("An error occurred while saving the data: " + error);
            }
        });                              
    }    
}

$(document).ready(function() {
    if($("#furnace_no").val() != "" && $("#Charg_date").val() != "") {
        generateBatchNo();
    }
});

// Remelt Function 

var rawRemeltData = []; // เก็บข้อมูลตั้งต้นจาก Server

function openRemeltModal() {
    $.ajax({
        url: "model/get_remelt_list.php",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                rawRemeltData = response.data; // บันทึกข้อมูลตั้งต้น
                
                // รีเซ็ตค่า ตัวกรอง
                $("#filterProductID").val("");
                $("#sortWeight").val("default");
                $("#searchProductNo").val("");
                
                // ประมวลผลและแสดงตาราง
                applyRemeltFilter();
                $("#remeltModal").modal("show");
            } else {
                alert("An error occurred while retrieving the data: " + response.message);
            }
        },
        error: function() {
            alert("Unable to connect to retrieve data Remelt ได้");
        }
    });
}

// ฟังก์ชันประมวลผลการกรอง ค้นหา และเรียงลำดับข้อมูล
function applyRemeltFilter() {
    var selectedID = $("#filterProductID").val().toUpperCase();
    var sortType = $("#sortWeight").val();
    var searchText = $("#searchProductNo").val().toUpperCase().trim();

    // 1. Filter
    var filteredData = rawRemeltData.filter(function(item) {
        var matchID = (selectedID === "") || (item.PRODUCT_ID && item.PRODUCT_ID.toUpperCase() === selectedID);
        var matchNo = (searchText === "") || (item.PRODUCT_NO && item.PRODUCT_NO.toUpperCase().indexOf(searchText) > -1);
        return matchID && matchNo;
    });

    // 2. Sort
    if (sortType === "asc") {
        filteredData.sort(function(a, b) {
            return (parseFloat(a.PRODUCT_WEIGHT) || 0) - (parseFloat(b.PRODUCT_WEIGHT) || 0);
        });
    } else if (sortType === "desc") {
        filteredData.sort(function(a, b) {
            return (parseFloat(b.PRODUCT_WEIGHT) || 0) - (parseFloat(a.PRODUCT_WEIGHT) || 0);
        });
    }

    // 3. Render Table
    var rows = "";
    if (filteredData.length === 0) {
        rows = "<tr><td colspan='5' class='text-center' style='color:#94a3b8;'>No information was found matching the selected criteria.</td></tr>";
    } else {
        $.each(filteredData, function(index, item) {
            var weight = parseFloat(item.PRODUCT_WEIGHT) || 0;
            var productNo = item.PRODUCT_NO || '';
            
            rows += "<tr>";
            rows += "<td><strong>" + productNo + "</strong></td>";
            rows += "<td>" + (item.PRODUCT_ID || '') + "</td>";
            rows += "<td class='text-right' style='font-weight:600; color:#2563eb;'>" + weight.toFixed(2) + "</td>";
            rows += "<td>" + (item.RESPONS_TYPE || '') + "</td>";
            // ใช้วิธีฝากค่าไว้ที่ data-weight และ data-productno
            rows += "<td class='text-center'><button type='button' class='btn btn-sm btn-success btn-btn-select-remelt' data-weight='" + weight + "' data-productno='" + productNo + "'>Choose</button></td>";
            rows += "</tr>";
        });
    }

    $("#remeltListBody").html(rows);
}

var selectedProductNo = ""; // เพิ่มตัวแปรเก็บ Product No ที่เลือก

function selectRemeltWeight(weight, productNo) {
    document.getElementById("charge_remelt").value = weight;
    
    // บันทึก Product No ลง hidden field
    if(document.getElementById("selected_product_no")){
        document.getElementById("selected_product_no").value = productNo;
    }
    
    calculateTotal(); 
    $("#remeltModal").modal("hide");
}
</script>
</html>