<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

// Session variables
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? trim($_SESSION['LINE']) : '';
$group_func      = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$process_group   = isset($_SESSION['PROCESS_GROUP']) ? strtoupper(trim($_SESSION['PROCESS_GROUP'])) : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

$datetime = date('Y-m-d H:i:s');

// =========================================================================
// สิทธิ์พิเศษ: ต้องเป็น CEO, MANAGER, SUPERVISOR และต้องอยู่แผนก PD, IT, TC, MG
// =========================================================================
$bypass_positions    = ['CEO', 'MANAGER', 'SUPERVISOR'];
$allowed_departments = ['PD', 'IT', 'TC', 'MG'];

$is_supervisor_up = in_array($position_func, $bypass_positions) && in_array($department_func, $allowed_departments);

// ตรวจสอบ Line การทำงาน (เฉพาะกลุ่ม CASTER ต้องเป็น Line 1 หรือ 2 เท่านั้น)
$allowed_lines_caster = ['1', '2'];
if (!$is_supervisor_up) {
    if ($process_group !== 'CASTER' || !in_array($line_func, $allowed_lines_caster)) {
        echo "<script>
                alert('เตือน: Line การทำงานของคุณไม่ถูกต้อง ระบบ Caster อนุญาตเฉพาะ Line 1 และ Line 2 เท่านั้น');
                window.location.href = 'dashboard_mats.php';
              </script>";
        exit();
    }
}

// กำหนด Furnace No. อัตโนมัติจาก Line ที่ Login เข้ามา
$auto_furnace_no = '';
if (in_array($line_func, ['1', '2'])) {
    $auto_furnace_no = $line_func; // Line 1 -> Furnace 1, Line 2 -> Furnace 2
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
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
        
        input:readonly {
            background-color: #f1f5f9 !important;
            color: #64748b;
            cursor: not-allowed;
        }
        input:disabled, select:disabled {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            font-weight: 600;
            cursor: not-allowed;
            opacity: 0.9;
        }
        
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
            transform: translateY(-1px);
        }
        
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-2 { margin-top: 0.5rem; }

        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
        
        /* สไตล์ตาราง Modal WIP Ingot */
        .wip-table thead th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            padding: 10px;
            vertical-align: middle;
        }
        .wip-table tbody td {
            vertical-align: middle;
            padding: 8px 10px;
        }
        .badge-country {
            background-color: #e0f2fe;
            color: #0284c7;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }
    </style>
</head>

<body>
<div class="wrapper">
    
<?php $menu = 'A4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="furnance_mats.php?func=<?php echo $folder_func ?>">Furnace Charging Material (Line <?php echo htmlspecialchars($line_func, ENT_QUOTES, 'UTF-8'); ?>)</a>
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
                                    <label for="furnace_no">Furnace No. <span style="color:#ef4444;">*</span> <small class="text-primary">(Auto from Line)</small></label>
                                    
                                    <!-- ถ้าตรง Line 1 หรือ 2 จะ disabled ไม่ให้เลือกเอง -->
                                    <select class="form-control" id="furnace_no_select" onchange="syncFurnaceNo(this.value)" <?php echo ($auto_furnace_no != '') ? 'disabled' : ''; ?>>
                                        <option value="">-- Select Furnace --</option>
                                        <option value="1" <?php echo ($auto_furnace_no === '1') ? 'selected' : ''; ?>>FURNACE #01 (Line 1)</option>
                                        <option value="2" <?php echo ($auto_furnace_no === '2') ? 'selected' : ''; ?>>FURNACE #02 (Line 2)</option>
                                    </select>
                                    <!-- Hidden Input สำหรับเก็บค่าเตาจริงเพื่อใช้ตอน Submit -->
                                    <input type="hidden" id="furnace_no" name="furnace_no" value="<?php echo htmlspecialchars($auto_furnace_no, ENT_QUOTES, 'UTF-8'); ?>" />
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
                            
                            <!-- Primary & Secondary Smelt Country (Disabled ไม่ให้เลือกเอง) -->
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group">
                                    <label for="primary_smelt">Primary Smelt Country <small class="text-primary">(Auto Calc)</small></label>
                                    <select class="form-control" id="primary_smelt" disabled>
                                        <option value=""></option>
                                        <?php
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
                                    <label for="seconday_smelt">Secondary Smelt Country <small class="text-primary">(Auto Calc)</small></label>
                                    <select class="form-control" id="seconday_smelt" disabled>
                                        <option value=""></option>
                                        <?php
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
                            
                            <!-- Charge AL Ingot (Kg) เป็นปุ่มเลือกจาก WIP Stock ข้ามตู้ -->
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="charge_ingot">Charge AL Ingot (Kg)</label>
                                    <div class="input-group">
                                        <input class="form-control" name="charge_ingot" id="charge_ingot" type="number" readonly placeholder="Click button to select Ingot..." style="background-color: #f8fafc; font-weight: 700; color: #1e40af;"/> 
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-info" onclick="openIngotModal()" style="height: 42px; border-radius: 0 8px 8px 0; font-weight: 600;">
                                                📦 Select WIP
                                            </button>
                                        </span>
                                    </div>
                                    <small id="selected_ingot_count_text" style="color: #64748b; font-size: 11.5px;"></small>
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

        <!-- Hidden Inputs สำหรับส่งค่ารายการที่เลือก -->
        <input type="hidden" id="selected_product_no" name="selected_product_no" value="" />
        <input type="hidden" id="selected_ingot_items" name="selected_ingot_items" value="" />

        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Modal เลือก AL Ingot จาก WIP Stock (ข้ามตู้ได้) -->
<div class="modal fade" id="ingotModal" tabindex="-1" role="dialog" aria-labelledby="ingotModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width: 85%;">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ingotModalLabel" style="font-weight:700; color:#1e293b;">
                    📦 Select Charge AL Ingot from WIP Stock
                </h4>
            </div>
            <div class="modal-body">
                <!-- ฟิลเตอร์ค้นหาใน Modal -->
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-4 col-sm-6">
                        <label style="font-size: 12px; color: #64748b;">Filter by Container No.</label>
                        <input type="text" id="filterContainer" class="form-control" placeholder="Search Container..." onkeyup="renderIngotTable()">
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <label style="font-size: 12px; color: #64748b;">Filter by Packing No.</label>
                        <input type="text" id="filterPacking" class="form-control" placeholder="Search Packing No..." onkeyup="renderIngotTable()">
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <label style="font-size: 12px; color: #64748b;">Filter by Country</label>
                        <select id="filterCountry" class="form-control" onchange="renderIngotTable()">
                            <option value="">All Countries</option>
                        </select>
                    </div>
                </div>

                <!-- สรุปผลการเลือกปัจจุบันใน Modal -->
                <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 16px; border-radius: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 14px; font-weight: 600; color: #166534;">
                            Selected: <span id="modal_selected_count" class="badge" style="background-color: #15803d;">0</span> items
                        </span>
                        <span style="margin-left: 15px; font-size: 14px; font-weight: 700; color: #15803d;">
                            Total Net Weight: <span id="modal_selected_weight">0.00</span> Kg
                        </span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-xs btn-default" onclick="clearAllIngotSelection()">Clear All</button>
                    </div>
                </div>

                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover wip-table" id="tableIngot">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;">
                                    <input type="checkbox" id="check_all_ingot" onclick="toggleSelectAllVisible(this)" style="cursor:pointer;">
                                </th>
                                <th style="text-align: center;">Container No.</th>
                                <th style="text-align: center;">Packing No.</th>
                                <th style="text-align: center;">Pieces</th>
                                <th style="text-align: right;">Net Weight (Kg)</th>
                                <th style="text-align: center;">Country of Origin</th>
                                <th style="text-align: center;">Requisition No.</th>
                            </tr>
                        </thead>
                        <tbody id="ingotListBody">
                            <!-- รายการ Packing No -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                <span id="country_calc_preview" style="font-size:13px; font-weight:600; color:#2563eb;"></span>
                <div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmIngotSelection()" style="font-weight: 600;">
                        ✔️ Confirm Selection
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Remelt Selection (คงเดิม) -->
<div class="modal fade" id="remeltModal" tabindex="-1" role="dialog" aria-labelledby="remeltModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="remeltModalLabel" style="font-weight:600;">📋 Select an item Charge Product Remelt</h4>
            </div>
            <div class="modal-body">
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
                        <tbody id="remeltListBody"></tbody>
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
// ซิงค์ค่าเมื่อมีการเปลี่ยน Furnace (กรณีเป็น Supervisor ที่เปิดให้เลือกเองได้)
function syncFurnaceNo(val) {
    document.getElementById("furnace_no").value = val;
    generateBatchNo();
}

// ==========================================
// ส่วนของการจัดการ WIP Ingot Selection
// ==========================================
var rawIngotData = [];           // เก็บรายการทั้งหมดที่ดึงมาจาก WIP
var selectedIngotMap = {};       // เก็บรายการที่เลือก { req_detail_id: itemObject }

function openIngotModal() {
    $.ajax({
        url: "model/get_wip_ingot_list.php",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                rawIngotData = response.data;
                populateCountryFilter();
                renderIngotTable();
                updateModalSummary();
                $("#ingotModal").modal("show");
            } else {
                alert("Cannot load WIP Ingot: " + response.message);
            }
        },
        error: function() {
            alert("Unable to connect to get WIP Ingot data.");
        }
    });
}

function populateCountryFilter() {
    var countries = {};
    rawIngotData.forEach(function(item) {
        var c = (item.country_of_origin || '').trim();
        if(c !== "") countries[c] = true;
    });
    
    var html = '<option value="">All Countries</option>';
    Object.keys(countries).sort().forEach(function(c) {
        html += '<option value="' + c + '">' + c + '</option>';
    });
    $("#filterCountry").html(html);
}

function renderIngotTable() {
    var searchContainer = $("#filterContainer").val().toUpperCase().trim();
    var searchPacking   = $("#filterPacking").val().toUpperCase().trim();
    var selectedCountry = $("#filterCountry").val().toUpperCase().trim();

    var filtered = rawIngotData.filter(function(item) {
        var matchCont = (searchContainer === "") || ((item.container_no || '').toUpperCase().indexOf(searchContainer) > -1);
        var matchPack = (searchPacking === "") || ((item.packing_no || '').toUpperCase().indexOf(searchPacking) > -1);
        var matchCtry = (selectedCountry === "") || ((item.country_of_origin || '').toUpperCase() === selectedCountry);
        return matchCont && matchPack && matchCtry;
    });

    var rows = "";
    if (filtered.length === 0) {
        rows = "<tr><td colspan='7' class='text-center' style='color:#94a3b8; padding: 20px;'>No WIP Ingot items found.</td></tr>";
    } else {
        filtered.forEach(function(item) {
            var id = item.req_detail_id;
            var isChecked = selectedIngotMap[id] ? "checked" : "";
            var weight = parseFloat(item.net_weight_kg) || 0;
            
            rows += "<tr style='cursor:pointer;' onclick='toggleRowCheckbox(event, " + id + ")'>";
            rows += "<td align='center'><input type='checkbox' class='chk_ingot' value='" + id + "' " + isChecked + " onclick='event.stopPropagation(); handleCheckboxChange(" + id + ")' style='cursor:pointer;'></td>";
            rows += "<td align='center'><strong>" + (item.container_no || '-') + "</strong></td>";
            rows += "<td align='center' style='color:#1e293b; font-weight:600;'>📦 " + (item.packing_no || '-') + "</td>";
            rows += "<td align='center'>" + (item.piece_count || '-') + "</td>";
            rows += "<td align='right' style='font-weight:700; color:#059669;'>" + weight.toFixed(2) + "</td>";
            rows += "<td align='center'><span class='badge-country'>" + (item.country_of_origin || '-') + "</span></td>";
            rows += "<td align='center' style='color:#64748b; font-size:12px;'>" + (item.req_no || '-') + "</td>";
            rows += "</tr>";
        });
    }
    $("#ingotListBody").html(rows);
    updateModalSummary();
}

function toggleRowCheckbox(e, id) {
    handleCheckboxChange(id);
    renderIngotTable();
}

function handleCheckboxChange(id) {
    if (selectedIngotMap[id]) {
        delete selectedIngotMap[id];
    } else {
        var found = rawIngotData.find(function(it) { return it.req_detail_id == id; });
        if (found) {
            selectedIngotMap[id] = found;
        }
    }
    updateModalSummary();
}

function toggleSelectAllVisible(chkMaster) {
    var isCheck = chkMaster.checked;
    $(".chk_ingot").each(function() {
        var id = $(this).val();
        if (isCheck) {
            var found = rawIngotData.find(function(it) { return it.req_detail_id == id; });
            if (found) selectedIngotMap[id] = found;
        } else {
            delete selectedIngotMap[id];
        }
    });
    renderIngotTable();
}

function clearAllIngotSelection() {
    selectedIngotMap = {};
    renderIngotTable();
}

function updateModalSummary() {
    var count = 0;
    var totalWeight = 0;
    var countryTotals = {};

    Object.keys(selectedIngotMap).forEach(function(key) {
        var item = selectedIngotMap[key];
        var w = parseFloat(item.net_weight_kg) || 0;
        var c = (item.country_of_origin || 'UNKNOWN').trim().toUpperCase();
        
        count++;
        totalWeight += w;
        countryTotals[c] = (countryTotals[c] || 0) + w;
    });

    $("#modal_selected_count").text(count);
    $("#modal_selected_weight").text(totalWeight.toFixed(2));

    var sortedCountries = Object.keys(countryTotals).sort(function(a, b) {
        return countryTotals[b] - countryTotals[a];
    });

    if (sortedCountries.length === 0) {
        $("#country_calc_preview").text("");
    } else if (sortedCountries.length === 1) {
        $("#country_calc_preview").text("💡 Single Country: " + sortedCountries[0] + " (100%)");
    } else {
        $("#country_calc_preview").text("💡 Primary: " + sortedCountries[0] + " (" + countryTotals[sortedCountries[0]].toFixed(0) + " Kg) | Secondary: " + sortedCountries[1] + " (" + countryTotals[sortedCountries[1]].toFixed(0) + " Kg)");
    }
}

function confirmIngotSelection() {
    var totalWeight = 0;
    var countryTotals = {};
    var selectedIds = [];

    Object.keys(selectedIngotMap).forEach(function(key) {
        var item = selectedIngotMap[key];
        var w = parseFloat(item.net_weight_kg) || 0;
        var c = (item.country_of_origin || '').trim().toUpperCase();
        
        totalWeight += w;
        selectedIds.push(item.req_detail_id);
        if (c !== "") {
            countryTotals[c] = (countryTotals[c] || 0) + w;
        }
    });

    $("#charge_ingot").val(totalWeight > 0 ? totalWeight.toFixed(2) : "");
    $("#selected_ingot_items").val(JSON.stringify(selectedIds));
    $("#selected_ingot_count_text").text("Selected " + selectedIds.length + " packing items from WIP");

    // เรียงลำดับค่าน้ำหนักเพื่อกำหนด Primary / Secondary
    var sortedCountries = Object.keys(countryTotals).sort(function(a, b) {
        return countryTotals[b] - countryTotals[a];
    });

    if (sortedCountries.length === 1) {
        setSmeltCountry("primary_smelt", sortedCountries[0]);
        setSmeltCountry("seconday_smelt", "");
    } else if (sortedCountries.length >= 2) {
        setSmeltCountry("primary_smelt", sortedCountries[0]);   
        setSmeltCountry("seconday_smelt", sortedCountries[1]);  
    } else {
        setSmeltCountry("primary_smelt", "");
        setSmeltCountry("seconday_smelt", "");
    }

    calculateTotal();
    $("#ingotModal").modal("hide");
}

function setSmeltCountry(elementId, countryName) {
    var $select = $("#" + elementId);
    var found = false;
    $select.find("option").each(function() {
        if ($(this).val().toUpperCase() === countryName.toUpperCase()) {
            $select.val($(this).val());
            found = true;
            return false;
        }
    });
    if (!found) {
        $select.val("");
    }
}

// ==========================================
// ฟังก์ชันการคำนวณน้ำหนักหน้าหลัก
// ==========================================
function calculateTotal() {
    var remain_form    = parseFloat(document.getElementById("remain_form").value) || 0;
    var charge_ingot   = parseFloat(document.getElementById("charge_ingot").value) || 0;
    var charge_remelt  = parseFloat(document.getElementById("charge_remelt").value) || 0;
    var charge_casting = parseFloat(document.getElementById("charge_casting").value) || 0;
    var charge_other   = parseFloat(document.getElementById("charge_other").value) || 0;
    var charge_wire    = parseFloat(document.getElementById("charge_wire").value) || 0;
    var charge_recycle = parseFloat(document.getElementById("charge_recycle").value) || 0;
    var charge_alloy   = parseFloat(document.getElementById("charge_alloy").value) || 0;
    
    var total = remain_form + charge_ingot + charge_remelt + charge_casting + charge_other + charge_wire + charge_recycle + charge_alloy;
    document.getElementById("total_charge").value = total.toFixed(2);
    
    calculateBalance();
}

function calculateBalance() {
    var totalCharge = parseFloat(document.getElementById("total_charge").value) || 0;
    var totalDross  = parseFloat(document.getElementById("total_dross").value) || 0;
    
    var balance = totalCharge - totalDross;
    document.getElementById("balance_furnace").value = balance.toFixed(2);
}

function handleMaterialChange() {
    var selectedMaterial = document.getElementById("material_name").value;
    
    var remainField  = document.getElementById("remain_form");
    var remeltField  = document.getElementById("charge_remelt");
    var castingField = document.getElementById("charge_casting");
    var otherField   = document.getElementById("charge_other");
    var wireField    = document.getElementById("charge_wire");
    var recycleField = document.getElementById("charge_recycle");
    var alloyField   = document.getElementById("charge_alloy");
    
    var allFields = [remainField, remeltField, castingField, otherField, wireField, recycleField, alloyField];

    function enableFields(fields) {
        fields.forEach(function(field) { field.readOnly = false; });
    }
    function setReadOnlyFields(fields) {
        fields.forEach(function(field) { field.readOnly = true; field.value = "0"; });
    }

    enableFields(allFields);

    if (selectedMaterial === "BOI-MAT1" || selectedMaterial === "NONE-BOI") {
        setReadOnlyFields([wireField, recycleField]);
    } else if (selectedMaterial === "BOI-GRS1") {
        enableFields([remeltField, wireField, alloyField]);
        setReadOnlyFields([remainField, castingField, otherField, recycleField]);
    }

    calculateTotal();
}

function generateBatchNo() {
    var furnaceNo    = document.getElementById("furnace_no").value;
    var chargingDate = document.getElementById("Charg_date").value;
    
    if(furnaceNo == "" || chargingDate == "") return;
    
    var dateObj = new Date(chargingDate);
    var year    = dateObj.getFullYear().toString().slice(-2);
    var month   = ("0" + (dateObj.getMonth() + 1)).slice(-2);
    var day     = ("0" + dateObj.getDate()).slice(-2);
    var dateCode = year + month + day;
    
    $.ajax({
        url: "model/get_last_batch_sequence_mats.php",
        type: "POST",
        data: { furnace_no: furnaceNo, charging_date: chargingDate },
        dataType: "json",
        success: function(response) {
            var sequence = response.last_sequence || 0;
            var nextSeq = parseInt(sequence) + 1;
            var seqFormatted = ("0" + nextSeq).slice(-2);
            document.getElementById("batch_no").value = "M" + dateCode + "-" + furnaceNo + "-" + seqFormatted;
        },
        error: function() {
            document.getElementById("batch_no").value = "M" + dateCode + "-" + furnaceNo + "-01";
        }
    });
}

function create_code(){
    var batchNo       = document.getElementById("batch_no").value;
    var chargingDate  = document.getElementById("Charg_date_show").value;
    var furnaceNo     = document.getElementById("furnace_no").value;
    var material      = document.getElementById("material_name").value; 
    var alloy         = document.getElementById("alloy").value;
    
    var primarySmelt  = $("#primary_smelt").val();
    var secondaySmelt = $("#seconday_smelt").val();
    
    var remainForm     = document.getElementById("remain_form").value;
    var chargeIngot    = document.getElementById("charge_ingot").value;
    var chargeRemelt   = document.getElementById("charge_remelt").value;
    var chargeCasting  = document.getElementById("charge_casting").value;
    var chargeOther    = document.getElementById("charge_other").value;
    var chargeWire     = document.getElementById("charge_wire").value;
    var chargeRecycle  = document.getElementById("charge_recycle").value;
    var chargeAlloy    = document.getElementById("charge_alloy").value;
    var totalCharge    = document.getElementById("total_charge").value;
    var totalDross     = document.getElementById("total_dross").value;
    var balanceFurnace = document.getElementById("balance_furnace").value;
    var remark         = document.getElementById("select_detail").value;
    var data_fun       = document.getElementById("func").value;
    
    var productNo  = document.getElementById("selected_product_no") ? document.getElementById("selected_product_no").value : "";
    var ingotItems = document.getElementById("selected_ingot_items") ? document.getElementById("selected_ingot_items").value : "";

    var data_string = batchNo + "*" + chargingDate + "*" + furnaceNo + "*" + material + "*" + 
                      alloy + "*" + primarySmelt + "*" + secondaySmelt + "*" +
                      remainForm + "*" + chargeIngot + "*" + chargeRemelt + "*" + 
                      chargeCasting + "*" + chargeOther + "*" + chargeWire + "*" + 
                      chargeRecycle + "*" + chargeAlloy + "*" + totalCharge + "*" + 
                      totalDross + "*" + balanceFurnace + "*" + remark + "*" + productNo;              

    if(batchNo == "" || furnaceNo == ""){
        alert("Please ensure Furnace No. and Batch No. are properly set before saving.");    
        return;
    }

    $.ajax({
        url: "model/add_furnace_charging_mats.php",
        type: "POST",
        data: {
            data_tag1: data_string,
            ingot_items: ingotItems
        },
        dataType: "json",
        success: function(resp) {
            if(resp.message){
                alert("Data has been saved successfully.");    
                window.location.assign('furnance_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Please check the updated information."));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while saving the data: " + error);
        }
    });                              
}

$(document).ready(function() {
    // โหลดสร้าง Batch No ทันทีตามค่า Furnace ที่ Auto กำหนดไว้
    if($("#furnace_no").val() != "" && $("#Charg_date").val() != "") {
        generateBatchNo();
    }
});

// ==========================================
// Remelt Logic
// ==========================================
var rawRemeltData = [];

$(document).on('click', '.btn-btn-select-remelt', function() {
    var weight = $(this).attr('data-weight');
    var productNo = $(this).attr('data-productno');
    
    $("#charge_remelt").val(weight);
    if($("#selected_product_no").length > 0) {
        $("#selected_product_no").val(productNo);
    }
    calculateTotal();
    $("#remeltModal").modal("hide");
});

function openRemeltModal() {
    $.ajax({
        url: "model/get_remelt_list.php",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                rawRemeltData = response.data;
                $("#filterProductID").val("");
                $("#sortWeight").val("default");
                $("#searchProductNo").val("");
                applyRemeltFilter();
                $("#remeltModal").modal("show");
            } else {
                alert("Error: " + response.message);
            }
        },
        error: function() {
            alert("Unable to connect to retrieve Remelt data.");
        }
    });
}

function applyRemeltFilter() {
    var selectedID = $("#filterProductID").val().toUpperCase();
    var sortType = $("#sortWeight").val();
    var searchText = $("#searchProductNo").val().toUpperCase().trim();

    var filteredData = rawRemeltData.filter(function(item) {
        var matchID = (selectedID === "") || (item.PRODUCT_ID && item.PRODUCT_ID.toUpperCase() === selectedID);
        var matchNo = (searchText === "") || (item.PRODUCT_NO && item.PRODUCT_NO.toUpperCase().indexOf(searchText) > -1);
        return matchID && matchNo;
    });

    if (sortType === "asc") {
        filteredData.sort(function(a, b) {
            return (parseFloat(a.PRODUCT_WEIGHT) || 0) - (parseFloat(b.PRODUCT_WEIGHT) || 0);
        });
    } else if (sortType === "desc") {
        filteredData.sort(function(a, b) {
            return (parseFloat(b.PRODUCT_WEIGHT) || 0) - (parseFloat(a.PRODUCT_WEIGHT) || 0);
        });
    }

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
            rows += "<td class='text-center'><button type='button' class='btn btn-sm btn-success btn-btn-select-remelt' data-weight='" + weight + "' data-productno='" + productNo + "'>Choose</button></td>";
            rows += "</tr>";
        });
    }
    $("#remeltListBody").html(rows);
}
</script>
</html>