<?php
// เริ่ม session
session_start();

// ดึงค่า Session และ Parameter ต่างๆ
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';

// ตรวจสอบแผนกทั้งจาก SESSION และจาก URL (?func=...) เพื่อรองรับทุกตำแหน่งของ PD
$dept_from_session = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';
$dept_from_url     = isset($_GET['func']) ? strtoupper(trim($_GET['func'])) : '';
$dept_from_folder  = strtoupper(trim($folder_func));

$department_func = !empty($dept_from_session) ? $dept_from_session : (!empty($dept_from_url) ? $dept_from_url : $dept_from_folder);

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// ระดับผู้บริหาร/หัวหน้างานขึ้นไป
$bypass_positions = ['CEO', 'MANAGER', 'SUPERVISOR', 'ASST MANAGER', 'FOREMAN'];
$is_supervisor_up = in_array($position_func, $bypass_positions);

// กำหนดสิทธิ์ตามเงื่อนไข f10 / f11
// 1. PD ทุกตำแหน่ง (รวมถึง IT และ Supervisor ขึ้นไป) ป้อนและแก้ไขข้อมูลฝั่งผลิตได้ทั้งหมด
$can_edit_pd = ($department_func === 'PD' || $department_func === 'IT' || $is_supervisor_up);

// 2. เฉพาะ TC (และ IT หรือ Supervisor ขึ้นไปเท่านั้น) ที่สามารถแก้ไขสูตรความร้อนและลงผลตรวจเชิงกลได้
$can_edit_tc = ($department_func === 'TC' || $department_func === 'IT' || ($is_supervisor_up && $department_func !== 'PD'));

include 'function_mats.php';
include 'dbcon_mats-new.php';

$bno    = isset($_GET['bno']) ? htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8') : '';
$is_new = isset($_GET['is_new']) ? (int)$_GET['is_new'] : 0;

if (empty($bno)) {
    echo "<script>alert('ไม่พบหมายเลข Batch No.'); window.location.href='batch_annealing_mats.php?func={$folder_func}';</script>";
    exit();
}

// -------------------------------------------------------------
// ดึงข้อมูล Master Header จาก BTCHPROD0
// -------------------------------------------------------------
$header = [];
$sql_h  = "SELECT TOP 1 * FROM BTCHPROD0 WHERE BTCH_NO = :bno";
$stmt_h = $conn->prepare($sql_h);
$stmt_h->bindParam(':bno', $bno, PDO::PARAM_STR);
$stmt_h->execute();
$header = $stmt_h->fetch(PDO::FETCH_ASSOC);

$batch_status = trim($header['BTCH_STATUS'] ?? ($is_new ? 'IN' : ''));
$control_by   = trim($header['CONTROL_BY'] ?? 'T');

// -------------------------------------------------------------
// ดึงรายการชิ้นงานทั้งหมดใน Batch (BTCHPROD0 JOIN BTCHPROD1)
// -------------------------------------------------------------
$items = [];
$sql_items = "SELECT b0.*, 
                     b1.BTCH_UTS, b1.BTCH_YS, b1.BTCH_ELONGATION, b1.BTCH_RESULT,
                     b1.BTCH_ACCEPTWEIGHT, b1.BTCH_ACCEPTPIECE,
                     b1.BTCH_REJECTWEIGHT, b1.BTCH_REJECTPIECE
              FROM BTCHPROD0 b0
              LEFT JOIN BTCHPROD1 b1 ON b0.PRODUCT_NO = b1.PRODUCT_NO AND b0.BTCH_NO = b1.BTCH_NO
              WHERE b0.BTCH_NO = :bno
              ORDER BY b0.PALLET_ITEM ASC, b0.PRODUCT_NO ASC";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bindParam(':bno', $bno, PDO::PARAM_STR);
$stmt_items->execute();
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// คำนวณยอดรวมของ Batch
$total_batch_weight = 0;
$total_batch_pieces = 0;
foreach ($items as $it) {
    $total_batch_weight += (float)($it['BTCH_INPUTWEIGHT'] ?? 0);
    $total_batch_pieces += (int)($it['BTCH_INPUTPIECE'] ?? 1);
}

// ดึงรายการสเปกชิ้นงานตัวแรกมาเป็นตัวแทนข้อมูลในช่องว่างด้านซ้าย (ดึงจาก COILPROD1 หรือ CRSHPROD1)
$first_prod_spec = [];
if (!empty($items[0]['PRODUCT_NO'])) {
    $f_pno = $items[0]['PRODUCT_NO'];
    $f_pid = $items[0]['PRODUCT_ID'];
    if ($f_pid === 'CO') {
        $stmt_fs = $conn->prepare("SELECT GRADE, SURFACE_GRADE AS SG, METALLURGICAL_GRADE AS MG, COIL_WORKPROCESS AS WORKPROCESS, COIL_NEXTPROCESS AS NEXTPROCESS, '0' AS WEIGHT_PIECE FROM COILPROD1 WHERE COIL_NO = :pno");
    } else {
        $stmt_fs = $conn->prepare("SELECT GRADE, SURFACE_GRADE AS SG, METALLURGICAL_GRADE AS MG, CRSH_WORKPROCESS AS WORKPROCESS, CRSH_NEXTPROCESS AS NEXTPROCESS, WEIGHT_PIECE FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    }
    $stmt_fs->execute([':pno' => $f_pno]);
    $first_prod_spec = $stmt_fs->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ดึงรายการ Temper สำหรับ Dropdown จาก TMPRMSTR1
$tempers = [];
try {
    $stmt_tmp = $conn->query("SELECT DISTINCT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER");
    $tempers  = $stmt_tmp->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { $tempers = []; }

// คำนวณเวลาเปรียบเทียบของ Technical Practice (TP) จากนาทีเป็น ชม.:นาที
$tp_heat_total = (int)($header['TP_HEATTIME'] ?? 0);
$tp_heat_hr    = floor($tp_heat_total / 60);
$tp_heat_min   = $tp_heat_total % 60;
$tp_heat_fmt   = sprintf("%d:%02d", $tp_heat_hr, $tp_heat_min);

$tp_soak_total = (int)($header['TP_SOAKTIME'] ?? 0);
$tp_soak_hr    = floor($tp_soak_total / 60);
$tp_soak_min   = $tp_soak_total % 60;
$tp_soak_fmt   = sprintf("%d:%02d", $tp_soak_hr, $tp_soak_min);

// เวลาของ Operator (AS)
$as_heat_total = (int)($header['AS_HEATTIME'] ?? 0);
$as_heat_hr    = floor($as_heat_total / 60);
$as_heat_min   = $as_heat_total % 60;

$as_soak_total = (int)($header['AS_SOAKTIME'] ?? 0);
$as_soak_hr    = floor($as_soak_total / 60);
$as_soak_min   = $as_soak_total % 60;
?>
<!doctype html>
<html lang="en">
<head>
    <title>Batch Annealing Entry - <?php echo $bno; ?></title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; 
            background-color: #f1f5f9; 
            color: #1e293b; 
        }
        .main-panel { background-color: #f1f5f9 !important; }
        .main-panel .content { padding: 15px 20px !important; }
        
        .card-custom {
            background: #ffffff; 
            border-radius: 12px; 
            border: 1px solid #e2e8f0;
            padding: 22px; 
            margin-bottom: 20px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .section-box {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 16px 18px;
            height: 100%;
        }
        .section-title {
            font-size: 15px; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            margin-top: 0;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-group-custom { 
            display: flex; 
            flex-direction: column; 
            gap: 5px; 
            margin-bottom: 12px; 
        }
        .form-group-custom label { 
            font-size: 11.5px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.3px;
            margin: 0; 
        }
        .form-control-custom {
            height: 38px; 
            border-radius: 7px; 
            border: 1px solid #cbd5e1; 
            padding: 6px 12px; 
            font-size: 13.5px;
            color: #1e293b;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .form-control-custom:focus { 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            outline: none; 
        }
        .form-control-custom:read-only, .form-control-custom:disabled { 
            background-color: #f8fafc; 
            color: #64748b; 
            border-color: #e2e8f0;
        }

        /* Nav Tabs */
        .nav-tabs-process { 
            display: flex; 
            gap: 12px; 
            border-bottom: 2px solid #e2e8f0; 
            margin-bottom: 18px; 
        }
        .nav-tab-item {
            padding: 10px 22px; 
            font-weight: 700; 
            font-size: 14px; 
            color: #64748b; 
            cursor: pointer;
            border-bottom: 3px solid transparent; 
            margin-bottom: -2px; 
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-tab-item:hover { color: #2563eb; }
        .nav-tab-item.active { 
            color: #2563eb; 
            border-bottom-color: #2563eb; 
            background: rgba(37, 99, 235, 0.04);
            border-radius: 6px 6px 0 0;
        }

        /* Spec Banner */
        .spec-banner {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 12px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .spec-banner .spec-tag {
            font-size: 10.5px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
        }
        .spec-banner .spec-val {
            font-size: 13.5px;
            font-weight: 700;
            color: #38bdf8;
        }

        /* Comparison Table */
        .table-compare {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .table-compare thead th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 700;
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
        }
        .table-compare tbody td {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 12.5px;
        }
        .table-compare tbody tr:last-child td { border-bottom: none; }
        .cell-practice {
            background-color: #f0fdf4 !important;
            color: #166534 !important;
            font-weight: 700;
            text-align: center;
            border-left: 1px solid #e2e8f0;
            font-size: 12.5px;
        }

        /* Products Table */
        #item_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        #item_table thead th {
            background-color: #f8fafc; 
            font-size: 12px; 
            font-weight: 700; 
            color: #475569;
            padding: 10px 8px; 
            border-bottom: 2px solid #e2e8f0; 
            white-space: nowrap;
        }
        #item_table tbody td { 
            padding: 8px; 
            font-size: 13px; 
            vertical-align: middle; 
            border-bottom: 1px solid #f1f5f9; 
        }
        #item_table tbody tr:hover td { background-color: #f8fafc; }

        /* Action Buttons */
        .btn-save-main { 
            background: linear-gradient(180deg, #10b981 0%, #059669 100%);
            color: #fff; 
            font-weight: 700; 
            height: 38px; 
            padding: 0 20px; 
            border-radius: 7px; 
            border: none;
            box-shadow: 0 1px 2px rgba(16, 185, 129, 0.3);
            transition: all 0.2s;
        }
        .btn-save-main:hover { 
            background: #047857; 
            transform: translateY(-1px);
        }
        .btn-del-item { 
            background-color: #fee2e2; 
            color: #dc2626; 
            border: 1px solid #fecaca; 
            padding: 4px 8px; 
            border-radius: 5px; 
            font-size: 12px;
            font-weight: bold;
            transition: all 0.2s;
        }
        .btn-del-item:hover { 
            background-color: #dc2626; 
            color: #ffffff;
        }

        /* Spec Details Block */
        .spec-detail-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }
        .spec-detail-box .row-spec {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 12px;
        }
        .spec-detail-box .row-spec:last-child { border-bottom: none; }
        .spec-detail-box .lbl { color: #64748b; font-weight: 600; }
        .spec-detail-box .val { color: #0f172a; font-weight: 700; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">
                        🔥 Batch Control: <span style="color:#2563eb;"><?php echo $bno; ?></span>
                        <span class="badge" style="background-color:<?php echo ($batch_status==='OT'?'#059669':'#d97706'); ?>; font-size:11px; margin-left:8px; padding: 4px 8px;">
                            STATUS: <?php echo $batch_status ? $batch_status : 'NEW'; ?>
                        </span>
                    </a>
                </div>
                <div style="float: right; margin-top: 10px; display:flex; gap:8px;">
                    <button class="btn btn-default" style="font-weight:600; border-radius:7px;" onclick="window.location.href='batch_annealing_mats.php?func=<?php echo $folder_func; ?>'">Back to List</button>
                    <button class="btn btn-save-main" onclick="save_batch_master()">💾 Save Changes</button>
                </div>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <form id="form_batch_master">
                <input type="hidden" name="bno" id="bno" value="<?php echo $bno; ?>">
                
                <!-- Master Parameters Card -->
                <div class="card-custom">
                    <div class="nav-tabs-process">
                        <div class="nav-tab-item active" onclick="switch_tab('tab_input')">📥 1. Batch Input & Setup </div>
                        <div class="nav-tab-item" onclick="switch_tab('tab_output')">📤 2. Actual & Output Quality </div>
                    </div>

                    <!-- TAB 1: INPUT SETUP  -->
                    <div id="tab_input">
                        <div class="row">
                            <!-- แผนก PD: ข้อมูลเวลาเตาอบ, N2 และรายละเอียดชิ้นงานที่ดึงมาจาก f10 -->
                            <div class="col-md-5">
                                <div class="section-box">
                                    <h5 class="section-title" style="color:#0f766e;">
                                        <span>📅 Production Setup (PD)</span>
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-6 form-group-custom">
                                            <label>Start Date</label>
                                            <input type="date" class="form-control-custom" name="start_date" id="start_date" 
                                                   value="<?php echo !empty($header['BTCH_STARTDATE']) ? date('Y-m-d', strtotime($header['BTCH_STARTDATE'])) : date('Y-m-d'); ?>" 
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 form-group-custom">
                                            <label>Start Time</label>
                                            <input type="time" class="form-control-custom" name="start_time" id="start_time" 
                                                   value="<?php echo !empty($header['BTCH_STARTTIME']) ? date('H:i', strtotime($header['BTCH_STARTTIME'])) : date('H:i'); ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group-custom">
                                            <label>Expected Open Date</label>
                                            <input type="date" class="form-control-custom" name="expect_date" id="expect_date" 
                                                   value="<?php echo !empty($header['BTCH_EXPECTDATE']) ? date('Y-m-d', strtotime($header['BTCH_EXPECTDATE'])) : date('Y-m-d'); ?>" 
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 form-group-custom">
                                            <label>Expected Open Time</label>
                                            <input type="time" class="form-control-custom" name="expect_time" id="expect_time" 
                                                   value="<?php echo !empty($header['BTCH_EXPECTTIME']) ? date('H:i', strtotime($header['BTCH_EXPECTTIME'])) : '00:00'; ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 form-group-custom">
                                            <label>N2 Storage Tank (%)</label>
                                            <input type="number" step="any" class="form-control-custom" name="n2_storage" id="n2_storage" 
                                                   value="<?php echo htmlspecialchars($header['N2_STORAGE'] ?? '0'); ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-md-6 form-group-custom">
                                            <label>N2 Before Anneal (m³)</label>
                                            <input type="number" step="any" class="form-control-custom" name="n2_start" id="n2_start" 
                                                   value="<?php echo htmlspecialchars($header['N2_START'] ?? '0'); ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="form-group-custom">
                                        <label>Production Remark</label>
                                        <input type="text" class="form-control-custom" name="prod_remark" id="prod_remark" maxlength="50" 
                                               placeholder="หมายเหตุฝ่ายผลิต..."
                                               value="<?php echo htmlspecialchars($header['BTCH_PRODREMARK'] ?? ''); ?>"
                                               <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                    </div>

                                    <!-- ข้อมูลสเปกและสายการผลิตที่เติมเต็มพื้นที่ว่างซ้ายล่าง (อ้างอิงจาก f10) -->
                                    <div class="spec-detail-box">
                                        <div style="font-weight:700; color:#334155; font-size:12px; margin-bottom:6px; display:flex; justify-content:space-between;">
                                            <span>📋 Product & Process Specifications</span>
                                            <span style="color:#0f766e;">(Batch Summary)</span>
                                        </div>
                                        <div class="row-spec">
                                            <span class="lbl">Grade / Surface / Metal:</span>
                                            <span class="val">
                                                <?php echo htmlspecialchars($first_prod_spec['GRADE'] ?? '-'); ?> / 
                                                <?php echo htmlspecialchars($first_prod_spec['SG'] ?? '-'); ?> / 
                                                <?php echo htmlspecialchars($first_prod_spec['MG'] ?? '-'); ?>
                                            </span>
                                        </div>
                                        <div class="row-spec">
                                            <span class="lbl">Total Input Weight / Pieces:</span>
                                            <span class="val" style="color:#2563eb;">
                                                <?php echo number_format($total_batch_weight, 0); ?> kg / <?php echo $total_batch_pieces; ?> pcs
                                            </span>
                                        </div>
                                        <div class="row-spec">
                                            <span class="lbl">Work Process Routing:</span>
                                            <span class="val" style="color:#d97706; font-size:11.5px;">
                                                <?php echo htmlspecialchars($first_prod_spec['WORKPROCESS'] ?? '-'); ?>
                                            </span>
                                        </div>
                                        <div class="row-spec">
                                            <span class="lbl">Next Process:</span>
                                            <span class="val" style="color:#059669;">
                                                <?php echo htmlspecialchars($first_prod_spec['NEXTPROCESS'] ?? 'BA'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- แผนก TC: ตารางเปรียบเทียบ Operator กับ Technical Practice -->
                            <div class="col-md-7">
                                <div class="section-box">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #f1f5f9;">
                                        <h5 class="section-title" style="color:#1d4ed8; margin:0; padding:0; border:none;">
                                            <span>🌡️ Technical Recipe (TC)</span>
                                        </h5>
                                        <div style="background:#eff6ff; padding:4px 10px; border-radius:6px; border:1px solid #bfdbfe;">
                                            <label style="font-weight:600; margin-right:12px; font-size:12px; color:#1e40af; cursor:pointer;">
                                                <input type="radio" name="control_by" value="T" <?php echo ($control_by==='T'?'checked':''); ?> <?php echo !$can_edit_tc ? 'disabled' : ''; ?>> Time Control
                                            </label>
                                            <label style="font-weight:600; font-size:12px; color:#1e40af; cursor:pointer;">
                                                <input type="radio" name="control_by" value="P" <?php echo ($control_by==='P'?'checked':''); ?> <?php echo !$can_edit_tc ? 'disabled' : ''; ?>> Workpiece Temp
                                            </label>
                                        </div>
                                    </div>

                                    <!-- แถบแสดงสเปกชิ้นงานหลัก -->
                                    <div class="spec-banner">
                                        <div>
                                            <div class="spec-tag">Product - Alloy - Range</div>
                                            <div class="spec-val">
                                                <?php echo htmlspecialchars($header['PRODUCT_ID'] ?? '-'); ?> | 
                                                <?php echo htmlspecialchars($header['ALLOY'] ?? '-'); ?> | 
                                                <?php echo number_format((float)($header['RANGE_FROM'] ?? 0), 3); ?> - <?php echo number_format((float)($header['RANGE_TO'] ?? 0), 3); ?>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="spec-tag">Temper Initial ➔ Target</div>
                                            <div class="spec-val" style="color:#fde047;">
                                                <?php echo htmlspecialchars($header['TEMPER_INITIAL'] ?? '-'); ?> ➔ <?php echo htmlspecialchars($header['TEMPER_TARGET'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ตารางเปรียบเทียบค่าพารามิเตอร์ -->
                                    <table class="table-compare">
                                        <thead>
                                            <tr>
                                                <th style="width:38%;">Parameter</th>
                                                <th style="width:34%; text-align:center;">Operator Set (ตั้งค่าจริง)</th>
                                                <th style="width:28%; text-align:center; background:#ecfdf5; color:#065f46;">Practice (เป้าหมาย)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Heating Temp -->
                                            <tr>
                                                <td><strong>Heating Temp (°C)</strong></td>
                                                <td>
                                                    <input type="number" class="form-control-custom text-center" name="as_heat_temp" id="as_heat_temp" 
                                                           value="<?php echo htmlspecialchars($header['AS_HEATTEMPERATURE'] ?? '0'); ?>" 
                                                           onchange="check_temp_diff(this, <?php echo (int)($header['TP_HEATTEMPERATURE'] ?? 0); ?>, 'Heating Temp')"
                                                           <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php echo (int)($header['TP_HEATTEMPERATURE'] ?? 0); ?> °C
                                                </td>
                                            </tr>

                                            <!-- Heating Time -->
                                            <tr>
                                                <td><strong>Heating Time</strong></td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:4px;">
                                                        <input type="number" class="form-control-custom text-center" style="width:50%;" id="as_heat_hr" placeholder="Hr" 
                                                               value="<?php echo $as_heat_hr; ?>" onchange="update_total_mins('heat')" <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                        <span style="font-weight:bold; color:#64748b;">:</span>
                                                        <input type="number" class="form-control-custom text-center" style="width:50%;" id="as_heat_min" placeholder="Min" 
                                                               value="<?php echo $as_heat_min; ?>" onchange="update_total_mins('heat')" <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                        <input type="hidden" name="as_heat_time" id="as_heat_time" value="<?php echo $as_heat_total; ?>">
                                                    </div>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php echo $tp_heat_fmt; ?> <span style="font-size:10.5px; font-weight:normal;">(Hr:Min)</span>
                                                </td>
                                            </tr>

                                            <!-- Soaking Temp -->
                                            <tr>
                                                <td><strong>Soaking Temp (°C)</strong></td>
                                                <td>
                                                    <input type="number" class="form-control-custom text-center" name="as_soak_temp" id="as_soak_temp" 
                                                           value="<?php echo htmlspecialchars($header['AS_SOAKTEMPERATURE'] ?? '0'); ?>" 
                                                           onchange="check_temp_diff(this, <?php echo (int)($header['TP_SOAKTEMPERATURE'] ?? 0); ?>, 'Soaking Temp')"
                                                           <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php echo (int)($header['TP_SOAKTEMPERATURE'] ?? 0); ?> °C
                                                </td>
                                            </tr>

                                            <!-- Soaking Time -->
                                            <tr>
                                                <td><strong>Soaking Time</strong></td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:4px;">
                                                        <input type="number" class="form-control-custom text-center" style="width:50%;" id="as_soak_hr" placeholder="Hr" 
                                                               value="<?php echo $as_soak_hr; ?>" onchange="update_total_mins('soak')" <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                        <span style="font-weight:bold; color:#64748b;">:</span>
                                                        <input type="number" class="form-control-custom text-center" style="width:50%;" id="as_soak_min" placeholder="Min" 
                                                               value="<?php echo $as_soak_min; ?>" onchange="update_total_mins('soak')" <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                        <input type="hidden" name="as_soak_time" id="as_soak_time" value="<?php echo $as_soak_total; ?>">
                                                    </div>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php echo $tp_soak_fmt; ?> <span style="font-size:10.5px; font-weight:normal;">(Hr:Min)</span>
                                                </td>
                                            </tr>

                                            <!-- W.P. Heat Up Temp -->
                                            <tr>
                                                <td><strong>W.P. Heat Up Temp (°C)</strong></td>
                                                <td>
                                                    <input type="number" class="form-control-custom text-center" name="as_wp_heat_temp" id="as_wp_heat_temp" 
                                                           value="<?php echo htmlspecialchars($header['AS_WPHEATTEMPERATURE'] ?? '0'); ?>" 
                                                           <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php echo (int)($header['TP_WPHEATTEMPERATURE'] ?? 0); ?> °C
                                                </td>
                                            </tr>

                                            <!-- W.P. Soaking Temp -->
                                            <tr>
                                                <td><strong>W.P. Soaking Temp (°C)</strong></td>
                                                <td>
                                                    <input type="number" class="form-control-custom text-center" name="as_wp_soak_temp" id="as_wp_soak_temp" 
                                                           value="<?php echo htmlspecialchars($header['AS_WPSOAKTEMPERATURE'] ?? '0'); ?>" 
                                                           <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                                </td>
                                                <td class="cell-practice">
                                                    <?php 
                                                        $s_from = (int)($header['TP_WPSOAKTEMPERATUREFROM'] ?? 0);
                                                        $s_to   = (int)($header['TP_WPSOAKTEMPERATURETO'] ?? 0);
                                                        echo ($s_from === $s_to) ? $s_from . ' °C' : "{$s_from} - {$s_to} °C"; 
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="form-group-custom" style="margin-bottom:0;">
                                        <label>Technical Remark</label>
                                        <input type="text" class="form-control-custom" name="tech_remark" id="tech_remark" maxlength="50" 
                                               placeholder="หมายเหตุฝ่ายเทคนิค..."
                                               value="<?php echo htmlspecialchars($header['BTCH_TECHREMARK'] ?? ''); ?>"
                                               <?php echo (!$can_edit_pd && !$can_edit_tc) ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: ACTUAL & OUTPUT QUALITY  -->
<!-- TAB 2: ACTUAL & OUTPUT QUALITY  -->
                    <div id="tab_output" style="display:none;">
                        <div class="row">
                            <!-- ฝั่งซ้าย: ข้อมูลเตาอบจริง (Actual Furnace Data) -->
                            <div class="col-md-7">
                                <div class="section-box">
                                    <h5 class="section-title" style="color:#0f766e;">
                                        <span>⏱️ Actual Furnace Data </span>
                                    </h5>
                                    
                                    <div class="row">
                                        <div class="col-sm-6 form-group-custom">
                                            <label>Actual Purge Time (Mins)</label>
                                            <input type="number" class="form-control-custom text-center" name="ac_purge_time" id="ac_purge_time" 
                                                   value="<?php echo htmlspecialchars($header['AC_PURGETIME'] ?? '0'); ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                        <div class="col-sm-6 form-group-custom">
                                            <label>N2 End Post-Anneal</label>
                                            <input type="number" step="any" class="form-control-custom text-center" name="n2_end" id="n2_end" 
                                                   value="<?php echo htmlspecialchars($header['N2_END'] ?? '0'); ?>"
                                                   <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6 form-group-custom">
                                            <label>Pre-Heat Temp / Time</label>
                                            <div style="display:flex; gap:6px; width:100%;">
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="°C" name="ac_ph_temp" value="<?php echo htmlspecialchars($header['AC_PREHEATTEMPERATURE'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="Mins" name="ac_ph_time" value="<?php echo htmlspecialchars($header['AC_PREHEATTIME'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 form-group-custom">
                                            <label>Heat Temp / Time</label>
                                            <div style="display:flex; gap:6px; width:100%;">
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="°C" name="ac_heat_temp" value="<?php echo htmlspecialchars($header['AC_HEATTEMPERATURE'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="Mins" name="ac_heat_time" value="<?php echo htmlspecialchars($header['AC_HEATTIME'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6 form-group-custom">
                                            <label>Soak Temp / Time</label>
                                            <div style="display:flex; gap:6px; width:100%;">
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="°C" name="ac_soak_temp" value="<?php echo htmlspecialchars($header['AC_SOAKTEMPERATURE'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="Mins" name="ac_soak_time" value="<?php echo htmlspecialchars($header['AC_SOAKTIME'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 form-group-custom">
                                            <label>Workpiece Temp (°C) (Heat / Soak)</label>
                                            <div style="display:flex; gap:6px; width:100%;">
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="Heat" name="ac_wp_heat_temp" value="<?php echo htmlspecialchars($header['AC_WPHEATTEMPERATURE'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                                <input type="number" class="form-control-custom text-center" style="flex:1; min-width:0;" placeholder="Soak" name="ac_wp_soak_temp" value="<?php echo htmlspecialchars($header['AC_WPSOAKTEMPERATURE'] ?? '0'); ?>" <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ฝั่งขวา: สถานะเตาอบ (Batch Output Status) -->
                            <div class="col-md-5">
                                <div class="section-box">
                                    <h5 class="section-title" style="color:#1d4ed8;">
                                        <span>📊 Batch Output Status</span>
                                    </h5>
                                    <div class="form-group-custom">
                                        <label>Furnace Output Status</label>
                                        <select class="form-control-custom" name="btch_status" id="btch_status" <?php echo !$can_edit_pd ? 'disabled' : ''; ?>>
                                            <option value="IN" <?php echo ($batch_status==='IN'?'selected':''); ?>>🟡 IN: In Process / In Furnace</option>
                                            <option value="OT" <?php echo ($batch_status==='OT'?'selected':''); ?>>🟢 OT: Output Completed</option>
                                        </select>
                                    </div>
                                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:14px; margin-top:16px;">
                                        <span style="font-size:13px; color:#1e40af; line-height:1.5; display:block;">
                                            💡 <strong>คำแนะนำ:</strong> เมื่อทำการอบเสร็จและเปิดเตา ให้ระบุเวลาจริงทางซ้าย และเปลี่ยนสถานะเป็น <strong>OT</strong> จากนั้นกรอกผลการตรวจสอบคุณสมบัติเชิงกลในตารางด้านล่าง
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Card 2: Products in Batch พร้อมปุ่ม Add Product to Batch อยู่ขวาสุด -->
            <div class="card-custom">
                <div class="card-header-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:16px; font-weight:700; color:#1e293b;">
                        📦 Products in Batch (<?php echo count($items); ?> Items)
                    </span>
                    <div>
                        <?php if ($can_edit_pd && $batch_status !== 'OT'): ?>
                        <button class="btn btn-primary btn-sm" style="font-weight:600; border-radius:6px;" onclick="open_lookup_product_modal()">➕ Add Product to Batch</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="item_table" class="table">
                        <thead>
                            <tr>
                                <th style="text-align:center;">Pallet</th>
                                <th style="text-align:center;">Product No.</th>
                                <th style="text-align:center;">Job Order</th>
                                <th style="text-align:center;">Type</th>
                                <th style="text-align:center;">Alloy</th>
                                <th style="text-align:center;">Temper Init</th>
                                <th style="text-align:right;">Thick</th>
                                <th style="text-align:right;">Width/Dia</th>
                                <th style="text-align:right;">Input Wt</th>
                                <!-- Output Quality  -->
                                <th style="text-align:center;">Out Temper</th>
                                <th style="text-align:center;">UTS / YS / Elong</th>
                                <th style="text-align:center;">Result</th>
                                <th style="text-align:right;">Accept Wt</th>
                                <th style="text-align:center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                            <tr><td colspan="14" align="center" style="color:#94a3b8; padding:30px;">ยังไม่มีชิ้นงานใน Batch นี้</td></tr>
                            <?php else: foreach($items as $it): 
                                $pno = htmlspecialchars($it['PRODUCT_NO']);
                                $uts = (float)($it['BTCH_UTS'] ?? 0);
                                $ys  = (float)($it['BTCH_YS'] ?? 0);
                                $el  = (int)($it['BTCH_ELONGATION'] ?? 0);
                                $res = trim($it['BTCH_RESULT'] ?? '');
                            ?>
                            <tr id="row_<?php echo $pno; ?>">
                                <td align="center"><strong><?php echo htmlspecialchars($it['PALLET_ITEM']); ?></strong></td>
                                <td align="center"><span style="color:#2563eb; font-weight:700;"><?php echo $pno; ?></span></td>
                                <td align="center"><?php echo htmlspecialchars($it['JOB_ORDER'] ?? '-'); ?></td>
                                <td align="center"><span class="badge" style="background:#e2e8f0; color:#334155; font-size:11px;"><?php echo htmlspecialchars($it['PRODUCT_ID']); ?></span></td>
                                <td align="center" style="font-weight:600;"><?php echo htmlspecialchars($it['ALLOY']); ?></td>
                                <td align="center"><?php echo htmlspecialchars($it['TEMPER_INITIAL']); ?></td>
                                <td align="right"><?php echo number_format((float)$it['THICKNESS'], 3); ?></td>
                                <td align="right"><?php echo number_format((float)$it['WIDTH'], 3); ?></td>
                                <td align="right" style="font-weight:700; color:#0f172a;"><?php echo number_format((float)$it['BTCH_INPUTWEIGHT'], 0); ?></td>

                                <!-- Quality Input Fields (Save by Item) -->
                                <td align="center">
                                    <select class="form-control-custom" style="width:90px; height:32px; padding:2px 6px;" 
                                            id="out_temper_<?php echo $pno; ?>" <?php echo !$can_edit_tc ? 'disabled' : ''; ?>>
                                        <?php foreach($tempers as $t): ?>
                                        <option value="<?php echo $t; ?>" <?php echo ($t === ($it['TEMPER_TARGET'] ?? '') ? 'selected' : ''); ?>><?php echo $t; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td align="center">
                                    <div style="display:flex; gap:3px;">
                                        <input type="number" step="0.01" placeholder="UTS" class="form-control-custom" style="width:55px; height:32px; padding:2px 4px; text-align:center;" 
                                               id="uts_<?php echo $pno; ?>" value="<?php echo $uts ?: ''; ?>" <?php echo !$can_edit_tc ? 'readonly' : ''; ?>>
                                        <input type="number" step="0.01" placeholder="YS" class="form-control-custom" style="width:55px; height:32px; padding:2px 4px; text-align:center;" 
                                               id="ys_<?php echo $pno; ?>" value="<?php echo $ys ?: ''; ?>" <?php echo !$can_edit_tc ? 'readonly' : ''; ?>>
                                        <input type="number" placeholder="El" class="form-control-custom" style="width:48px; height:32px; padding:2px 4px; text-align:center;" 
                                               id="el_<?php echo $pno; ?>" value="<?php echo $el ?: ''; ?>" <?php echo !$can_edit_tc ? 'readonly' : ''; ?>>
                                    </div>
                                </td>
                                <td align="center">
                                    <select class="form-control-custom" style="width:95px; height:32px; padding:2px 6px;" 
                                            id="res_<?php echo $pno; ?>" <?php echo !$can_edit_tc ? 'disabled' : ''; ?>>
                                        <option value="">-</option>
                                        <option value="PASS" <?php echo ($res==='PASS'?'selected':''); ?>>PASS</option>
                                        <option value="REJECT" <?php echo ($res==='REJECT'?'selected':''); ?>>REJECT</option>
                                        <option value="REANNEAL" <?php echo ($res==='REANNEAL'?'selected':''); ?>>REANNEAL</option>
                                    </select>
                                </td>
                                <td align="right">
                                    <input type="number" class="form-control-custom" style="width:85px; height:32px; text-align:right;" 
                                           id="acc_wt_<?php echo $pno; ?>" 
                                           value="<?php echo htmlspecialchars($it['BTCH_ACCEPTWEIGHT'] ?? $it['BTCH_INPUTWEIGHT']); ?>"
                                           <?php echo !$can_edit_pd ? 'readonly' : ''; ?>>
                                </td>
                                <td align="center" style="white-space:nowrap;">
                                    <?php if ($can_edit_tc || $can_edit_pd): ?>
                                    <button class="btn btn-sm btn-success" style="padding:3px 8px; font-size:12px;" onclick="save_item_quality('<?php echo $pno; ?>')">💾</button>
                                    <?php endif; ?>
                                    <?php if ($can_edit_pd && $batch_status !== 'OT'): ?>
                                    <button class="btn-del-item" onclick="delete_batch_item('<?php echo $pno; ?>')">🗑</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Lookup Product (Coil / Circle) -->
<div class="modal fade" id="modal_product_lookup" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="width: 85%;">
        <div class="modal-content" style="border-radius:12px; overflow:hidden;">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:15px 20px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-weight:700; color:#1e293b;">🔍 Select Product for Batch: <?php echo $bno; ?></h4>
            </div>
            <div class="modal-body modal-body-scroll" style="padding:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:14px;">
                    <div>
                        <label style="margin-right:8px; font-weight:700; color:#475569;">Select Pallet No.:</label>
                        <select id="pallet_select" class="form-control-custom" style="display:inline-block; width:110px;">
                            <?php for($p=1; $p<=30; $p++): $ps = str_pad($p, 2, '0', STR_PAD_LEFT); ?>
                            <option value="<?php echo $ps; ?>">Pallet <?php echo $ps; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <input type="text" id="lookup_kw" class="form-control-custom" style="width:240px;" placeholder="Search Product No..." onkeyup="filter_lookup_table()">
                    </div>
                </div>

                <table class="table table-bordered table-hover" id="lookup_table">
                    <thead>
                        <tr style="background-color:#f1f5f9;">
                            <th style="text-align:center;">Product No.</th>
                            <th style="text-align:center;">Type</th>
                            <th style="text-align:center;">Alloy</th>
                            <th style="text-align:center;">Temper</th>
                            <th style="text-align:right;">Thick</th>
                            <th style="text-align:right;">Width/Dia</th>
                            <th style="text-align:right;">Weight</th>
                            <th style="text-align:center;">Next Proc</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="lookup_body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function switch_tab(tab_id) {
    $('.nav-tab-item').removeClass('active');
    if (tab_id === 'tab_input') {
        $('.nav-tab-item:eq(0)').addClass('active');
        $('#tab_input').show();
        $('#tab_output').hide();
    } else {
        $('.nav-tab-item:eq(1)').addClass('active');
        $('#tab_input').hide();
        $('#tab_output').show();
    }
}

function save_batch_master() {
    var form_data = $('#form_batch_master').serialize();
    $.ajax({
        url: 'batch_annealing_action.php?act=save_master',
        type: 'POST',
        data: form_data,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                alert('บันทึกข้อมูลหลักเรียบร้อยแล้ว');
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + res.message);
            }
        },
        error: function() { alert('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); }
    });
}

function save_item_quality(pno) {
    var out_temper = $('#out_temper_' + pno).val();
    var uts        = $('#uts_' + pno).val();
    var ys         = $('#ys_' + pno).val();
    var el         = $('#el_' + pno).val();
    var res        = $('#res_' + pno).val();
    var acc_wt     = $('#acc_wt_' + pno).val();

    $.ajax({
        url: 'batch_annealing_action.php?act=save_item_quality',
        type: 'POST',
        data: {
            bno: '<?php echo $bno; ?>',
            pno: pno,
            out_temper: out_temper,
            uts: uts,
            ys: ys,
            el: el,
            res: res,
            acc_wt: acc_wt
        },
        dataType: 'json',
        success: function(resp) {
            if (resp.status === 'success') {
                alert('บันทึกผลตรวจสอบชิ้นงาน ' + pno + ' เรียบร้อย');
            } else {
                alert('บันทึกไม่สำเร็จ: ' + resp.message);
            }
        }
    });
}

function open_lookup_product_modal() {
    $('#lookup_body').html('<tr><td colspan="9" align="center">กำลังค้นหารายการ...</td></tr>');
    $('#modal_product_lookup').modal('show');

    $.ajax({
        url: 'batch_annealing_action.php?act=get_available_products&bno=<?php echo $bno; ?>',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            var html = '';
            if (data.length === 0) {
                html = '<tr><td colspan="9" align="center">ไม่พบชิ้นงานที่มีสถานะพร้อมเข้าเตาอบ (BA)</td></tr>';
            } else {
                $.each(data, function(i, row) {
                    html += '<tr>' +
                            '<td align="center"><strong>' + row.PRODUCT_NO + '</strong></td>' +
                            '<td align="center">' + row.PRODUCT_TYPE + '</td>' +
                            '<td align="center">' + row.ALLOY + '</td>' +
                            '<td align="center">' + row.TEMPER + '</td>' +
                            '<td align="right">' + parseFloat(row.THICKNESS).toFixed(3) + '</td>' +
                            '<td align="right">' + parseFloat(row.WIDTH).toFixed(3) + '</td>' +
                            '<td align="right">' + parseFloat(row.WEIGHT).toLocaleString() + '</td>' +
                            '<td align="center">' + row.NEXTPROCESS + '</td>' +
                            '<td align="center">' +
                                '<button class="btn btn-sm btn-primary" onclick="add_product_to_batch(\'' + row.PRODUCT_NO + '\', \'' + row.PRODUCT_TYPE + '\')">เลือก</button>' +
                            '</td>' +
                            '</tr>';
                });
            }
            $('#lookup_body').html(html);
        }
    });
}

function filter_lookup_table() {
    var kw = $('#lookup_kw').val().toUpperCase();
    $('#lookup_body tr').each(function() {
        var text = $(this).text().toUpperCase();
        $(this).toggle(text.indexOf(kw) > -1);
    });
}

function add_product_to_batch(pno, ptype) {
    var pallet = $('#pallet_select').val();
    $.ajax({
        url: 'batch_annealing_action.php?act=add_item',
        type: 'POST',
        data: { bno: '<?php echo $bno; ?>', pno: pno, ptype: ptype, pallet: pallet },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'warn') {
                if (confirm(res.message)) {
                    execute_add_item(pno, ptype, pallet, 1);
                }
            } else if (res.status === 'success') {
                $('#modal_product_lookup').modal('hide');
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + res.message);
            }
        }
    });
}

function execute_add_item(pno, ptype, pallet, force) {
    $.ajax({
        url: 'batch_annealing_action.php?act=add_item',
        type: 'POST',
        data: { bno: '<?php echo $bno; ?>', pno: pno, ptype: ptype, pallet: pallet, force: force },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                $('#modal_product_lookup').modal('hide');
                window.location.reload();
            } else {
                alert(res.message);
            }
        }
    });
}

function delete_batch_item(pno) {
    if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการลบชิ้นงาน [' + pno + '] ออกจากเตาอบนี้?')) return;

    $.ajax({
        url: 'batch_annealing_action.php?act=del_item',
        type: 'POST',
        data: { bno: '<?php echo $bno; ?>', pno: pno },
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                $('#row_' + pno).remove();
                alert('ลบรายการสำเร็จ');
            } else {
                alert('ไม่สามารถลบได้: ' + res.message);
            }
        }
    });
}

function update_total_mins(type) {
    var hr = parseInt($('#as_' + type + '_hr').val()) || 0;
    var min = parseInt($('#as_' + type + '_min').val()) || 0;
    var total_mins = (hr * 60) + min;
    $('#as_' + type + '_time').val(total_mins);
}

function check_temp_diff(elem, target_val, label_name) {
    var val = parseInt($(elem).val()) || 0;
    if (val > 0 && target_val > 0 && val !== target_val) {
        alert("คำเตือน !!! ค่า " + label_name + " ที่คุณป้อน (" + val + " °C) แตกต่างจากเป้าหมาย Technical Practice (" + target_val + " °C)");
    }
}
</script>
</body>
<?php include 'include/footer.php';?>
</html>