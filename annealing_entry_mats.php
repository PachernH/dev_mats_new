<?php
session_start();

$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '2';
$group_func   = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$bypass_positions    = ['CEO', 'MANAGER', 'SUPERVISOR'];
$allowed_departments = ['PD', 'IT', 'TC', 'MG'];
$is_supervisor_up    = in_array($position_func, $bypass_positions) && in_array($department_func, $allowed_departments);

$allowed_lines_an = ['2', '3', '5'];
if (!$is_supervisor_up && !in_array($line_func, $allowed_lines_an)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ (" . $line_func . ") ไม่ใช่เตาอบ Annealing Process (Line 2 หรือ 3)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

$product_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include("function_mats.php");

if (empty($product_no)) {
    echo "<script>alert('ไม่พบรหัส Product No.'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 1. Guard Check ตาม f8.txt (ห้ามทำหากติด Sort หรือ Combine)
$stmt_sort = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SORTPROD1 WHERE PRODUCT_NO = :pno");
$stmt_sort->bindParam(':pno', $product_no);
$stmt_sort->execute();
if ($stmt_sort->fetch()) {
    echo "<script>
            alert('ไม่อนุญาตให้แก้ไข/ทำรายการ เนื่องจากมีรายการในกระบวนการ Sort แล้ว');
            window.location.href = 'annealing_process_mats.php?func={$folder_func}';
          </script>";
    exit();
}

$stmt_cmb = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
$stmt_cmb->bindParam(':pno', $product_no);
$stmt_cmb->execute();
if ($stmt_cmb->fetch()) {
    echo "<script>
            alert('พบผลิตภัณฑ์ : {$product_no} ได้มีรายการอื่นมา Combine อยู่ ต้องปลดหรือลบ Combine ก่อน');
            window.location.href = 'annealing_process_mats.php?func={$folder_func}';
          </script>";
    exit();
}

// 2. ดึงข้อมูล Product และ Job Order (เพิ่มดึง COIL_NO, MATERIAL_IN, SURFACE_GRADE, METALLURGICAL_GRADE ตาม f8.txt)
$sql_prod = "SELECT 
                B.PRODUCT_NO, B.MATERIAL_IN, B.COIL_NO, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE, 
                B.ALLOY, B.TEMPER, B.GRADE, B.SURFACE_GRADE, B.METALLURGICAL_GRADE, B.T5_TEMPERATURE, 
                B.THICKNESS, B.WIDTH, B.LENGTH, B.WEIGHT_PIECE, B.CUT_WIDTH, B.CUT_LENGTH, B.CUT_WEIGHTPIECE, 
                B.CRSH_WORKPROCESS, B.CRSH_NEXTPROCESS, B.CRSH_BOTTOMWEIGHT, B.CRSH_STATUS, 
                B.CRSH_ACTUALWEIGHT, B.CRSH_ACTUALPIECE, B.CRSH_ANNEALWEIGHT, B.CRSH_ANNEALPIECE,
                C.JOB_ORDER, C.TEMPER AS JOBTEMPER, C.SCHEDULE_DATE, C.JOB_RELEASEWEIGHT, C.JOB_RELEASEPIECE,
                C.JOB_ANNEALWEIGHT, C.JOB_ANNEALPIECE, C.SALEORDER_NO, C.SALEORDER_ITEM,
                D.CSTMSPPL_ID, D.CTM2_PONO, D.CTM2_POITEM, D.CONFIRM_DATE, D.CTM2_ORDERWEIGHT, D.CTM2_ORDERPIECE,
                D.CTM2_ANNEALWEIGHT, D.CTM2_ANNEALPIECE
             FROM CRSHPROD1 B 
             INNER JOIN JOBORDER1 C ON B.JOB_ORDER = C.JOB_ORDER 
             LEFT JOIN CSTMORDR2 D ON C.SALEORDER_NO = D.SALEORDER_NO AND C.SALEORDER_ITEM = D.SALEORDER_ITEM 
             WHERE B.PRODUCT_NO = :pno";

$stmt_p = $conn->prepare($sql_prod);
$stmt_p->bindParam(':pno', $product_no);
$stmt_p->execute();
$prod_data = $stmt_p->fetch(PDO::FETCH_ASSOC);

if (!$prod_data) {
    echo "<script>alert('ไม่พบข้อมูลชิ้นงานในระบบ'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 3. ตรวจสอบ Specification เทียบกับ Sale Order
$so_no   = $prod_data['SALEORDER_NO'] ?? '';
$so_item = $prod_data['SALEORDER_ITEM'] ?? '';

if (!empty($so_no) && !empty($so_item)) {
    $sql_spec = "SELECT ALLOY, TEMPER, THICKNESS, WIDTH, LENGTH, PRODUCT_ID FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item";
    $stmt_spec = $conn->prepare($sql_spec);
    $stmt_spec->bindParam(':so', $so_no);
    $stmt_spec->bindParam(':item', $so_item);
    $stmt_spec->execute();
    $so_data = $stmt_spec->fetch(PDO::FETCH_ASSOC);

    if ($so_data) {
        if ($prod_data['ALLOY'] !== $so_data['ALLOY']) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Alloy ของ Job ไม่ตรงกับ Sale Order'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (!in_array($so_data['PRODUCT_ID'], ['WR', 'RM'])) {
            if ($prod_data['JOBTEMPER'] !== $so_data['TEMPER']) {
                echo "<script>alert('Specification ไม่ถูกต้อง: Temper ของ Job ไม่ตรงกับ Sale Order'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
                exit();
            }
        }
        if (round((float)$prod_data['THICKNESS'], 3) != round((float)$so_data['THICKNESS'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Thickness ไม่ตรงกับ Sale Order'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['WIDTH'], 3) != round((float)$so_data['WIDTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Width ไม่ตรงกับ Sale Order'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['LENGTH'], 3) != round((float)$so_data['LENGTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Length ไม่ตรงกับ Sale Order'); window.location.href='annealing_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
    }
}

// 4. คำนวณ Next Process
$work_proc = $prod_data['CRSH_WORKPROCESS'] ?? '';
$pos_an = strpos($work_proc, 'AN>');
$next_process = "NN";
if ($pos_an !== false) {
    $next_process = substr($work_proc, $pos_an + 3, 2);
}

$strCheckAtProcess = "PRODUCT WEIGHT";
if (strpos($work_proc, "SH>") !== false) {
    if (strpos($work_proc, "AN>") > strpos($work_proc, "SH>")) {
        $strCheckAtProcess = "PRODUCT WEIGHT";
    } else {
        $strCheckAtProcess = "SHEET WEIGHT";
    }
}
$wt_piece_to_use = ($strCheckAtProcess === "SHEET WEIGHT") ? (float)$prod_data['CUT_WEIGHTPIECE'] : (float)$prod_data['WEIGHT_PIECE'];
if ($wt_piece_to_use <= 0) { $wt_piece_to_use = 1; }

// 5. คำนวณ Capacity มาตรฐานเตาอบ
$calSCHDWorkMinute = 9;
$sql_wrk = "SELECT ACTUAL_WORKTIME FROM WRKTMSTR1 WHERE PROCESS_ID = 'AN'";
$stmt_wrk = $conn->query($sql_wrk);
$row_wrk = $stmt_wrk ? $stmt_wrk->fetch(PDO::FETCH_ASSOC) : null;
$actual_worktime = $row_wrk ? (int)$row_wrk['ACTUAL_WORKTIME'] : 480;

$sql_cpc = "SELECT LOADING_PIECE, PRODUCE_SPEED, PERCENT_EFFICIENCY 
            FROM CPCTMSTR1 
            WHERE PROCESS_ID = 'AN' AND PRODUCT_ID = 'CC' 
              AND (THICKNESS_TO >= :t1 AND THICKNESS_FROM <= :t2) 
              AND (WIDTH_TO >= :w1 AND WIDTH_FROM <= :w2)";
$stmt_cpc = $conn->prepare($sql_cpc);
$thick_val = (float)$prod_data['THICKNESS'];
$width_val = (float)$prod_data['WIDTH'];
$stmt_cpc->bindParam(':t1', $thick_val);
$stmt_cpc->bindParam(':t2', $thick_val);
$stmt_cpc->bindParam(':w1', $width_val);
$stmt_cpc->bindParam(':w2', $width_val);
$stmt_cpc->execute();
$row_cpc = $stmt_cpc->fetch(PDO::FETCH_ASSOC);

if ($row_cpc) {
    $loading_piece  = (int)$row_cpc['LOADING_PIECE'];
    $produce_speed  = (float)$row_cpc['PRODUCE_SPEED'];
    $pct_efficiency = (int)$row_cpc['PERCENT_EFFICIENCY'];

    $rack_number = (1000 / 50) * $produce_speed;
    $std_cap = round(($rack_number * ($loading_piece * (float)$prod_data['WEIGHT_PIECE'])) * ($actual_worktime / 480), 2);
    if ($std_cap <= 0) $std_cap = 1;
    $out_eff = $std_cap * ($pct_efficiency / 100);
    $input_weight = (float)$prod_data['CRSH_ACTUALWEIGHT'];
    $calSCHDWorkMinute = round($input_weight / $out_eff, 0);
}

// 6. ดึงข้อมูลใน ANNLPROD1
$sql_annl = "SELECT * FROM ANNLPROD1 WHERE PRODUCT_NO = :pno";
$stmt_annl = $conn->prepare($sql_annl);
$stmt_annl->bindParam(':pno', $product_no);
$stmt_annl->execute();
$annl_data = $stmt_annl->fetch(PDO::FETCH_ASSOC);

function format_pcs($value) { return number_format((float)($value ?? 0), 0); }
function format_wt($value)  { return number_format((float)($value ?? 0), 2); }
function format_dim($value) { return number_format((float)($value ?? 0), 3); }
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Annealing Entry (Line <?php echo $line_func; ?>)</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 22px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .info-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .form-control-custom { width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 6px 12px; font-size: 14px; font-weight: 600; height: 38px; }
        .form-control-custom[readonly] { background-color: #f1f5f9; color: #1e293b; font-weight: 700; }
        
        .action-button-group { display: flex; gap: 12px; justify-content: flex-end; align-items: center; margin-top: 20px; }
        .btn-action { font-weight: 700; font-size: 14px; padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-save { background-color: #059669; color: #ffffff; }
        .btn-save:hover { background-color: #047857; }
        .btn-mch { background-color: #0284c7; color: #ffffff; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-mch:hover { background-color: #0369a1; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-back:hover { background-color: #475569; }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'A4';?>
<?php if (!empty($folder_func) && !empty($group_func)) { include 'include/'.$folder_func.'/navigation.php'; } ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="annealing_process_mats.php?func=<?php echo $folder_func ?>">Annealing Process (Line <?php echo $line_func; ?>)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 22px;">
                    🔥 Annealing Entry: <span style="color:#d97706;"><?php echo htmlspecialchars($product_no); ?></span> (Job: <span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?></span>)
                </h3>
                <button type="button" class="btn btn-back" onclick="back_home()">⬅️ Back</button>
            </div>

            <!-- สรุปข้อมูล Job Order และ Product Master (เพิ่ม Coil No, Material IN, SG, MG ตาม f8) -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="margin:0; font-weight:700; color:#1e3a8a; font-size:16px;">📌 Product & Job Summary</h4>
                    <span class="badge" style="background-color:#059669; font-size:12px; font-weight:700;"><?php echo htmlspecialchars($prod_data['CRSH_STATUS']); ?></span>
                </div>

                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Product No. / ID</div>
                        <div style="font-weight:700; color:#d97706; font-size:14px;"><?php echo htmlspecialchars($prod_data['PRODUCT_NO']); ?> (<span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?></span>)</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Coil No.</div>
                        <div style="font-weight:700; color:#0284c7; font-size:14px;"><?php echo htmlspecialchars($prod_data['COIL_NO'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Material In</div>
                        <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($prod_data['MATERIAL_IN'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Alloy / Temper / Grade</div>
                        <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($prod_data['ALLOY'] . ' / ' . $prod_data['TEMPER'] . ' / ' . ($prod_data['GRADE'] ?? 'NONE')); ?></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">SG - MG</div>
                        <div style="font-weight:700; color:#475569; font-size:14px;"><?php echo htmlspecialchars($prod_data['SURFACE_GRADE'] ?? '-'); ?> - <?php echo htmlspecialchars($prod_data['METALLURGICAL_GRADE'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Dimension (T x W x L)</div>
                        <div style="font-weight:700; color:#0284c7; font-size:14px;"><?php echo format_dim($prod_data['THICKNESS']) . ' x ' . format_wt($prod_data['WIDTH']) . ' x ' . format_wt($prod_data['LENGTH']); ?> mm.</div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Routing / Next Process</div>
                        <div style="font-weight:700; color:#475569; font-size:14px;"><?php echo htmlspecialchars($prod_data['CRSH_WORKPROCESS']); ?> ➔ <span style="color:#d97706;"><?php echo $next_process; ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Form บันทึกผลเตาอบ Annealing -->
            <div class="dashboard-card">
                <form id="frm_anneal_entry" onsubmit="save_anneal_product(event)">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="product_no" value="<?php echo htmlspecialchars($product_no); ?>">
                    <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?>">
                    <input type="hidden" name="temper" value="<?php echo htmlspecialchars($prod_data['TEMPER']); ?>">
                    <input type="hidden" name="next_process" value="<?php echo $next_process; ?>">
                    <input type="hidden" id="calc_weight_piece" value="<?php echo $wt_piece_to_use; ?>">
                    <input type="hidden" id="min_std_minute" value="<?php echo $calSCHDWorkMinute - 10; ?>">

                    <!-- 1. วันที่และเวลาการผลิต -->
                    <h4 style="color:#0284c7; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⏰ 1. Production Date/Time</h4>
                    <div class="row" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่เริ่มผลิต *</label>
                            <input type="date" class="form-control-custom" id="dt_start_date" name="start_date" required value="<?php echo !empty($annl_data['ANNL_STARTDATE']) ? date('Y-m-d', strtotime($annl_data['ANNL_STARTDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาเริ่มผลิต *</label>
                            <input type="time" class="form-control-custom" id="dt_start_time" name="start_time" required value="<?php echo !empty($annl_data['ANNL_STARTTIME']) ? date('H:i', strtotime($annl_data['ANNL_STARTTIME'])) : date('H:i'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่ผลิตเสร็จ *</label>
                            <input type="date" class="form-control-custom" id="dt_end_date" name="end_date" required value="<?php echo !empty($annl_data['ANNL_ENDDATE']) ? date('Y-m-d', strtotime($annl_data['ANNL_ENDDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาผลิตเสร็จ *</label>
                            <input type="time" class="form-control-custom" id="dt_end_time" name="end_time" required value="<?php echo !empty($annl_data['ANNL_ENDTIME']) ? date('H:i', strtotime($annl_data['ANNL_ENDTIME'])) : date('H:i'); ?>" />
                        </div>
                        <div class="col-md-12" style="text-align: right; margin-top: 5px;">
                            <button type="button" class="btn-mch" onclick="fetch_machine_data()">⚡ Automatically retrieve data from Annealing (FAF2MCHN1)</button>
                        </div>
                    </div>

                    <!-- 2. ค่าพารามิเตอร์เครื่องจักรเตาอบ -->
                    <h4 style="color:#0284c7; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">🎛 2. Annealing Furnace Parameters</h4>
                    <div class="row" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div class="col-md-3 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">เตาอบ / Line Process</label>
                            <select class="form-control-custom" name="line_process" id="cmb_line">
                                <option value="2" <?php if($line_func=='2') echo 'selected';?>>Line 2</option>
                                <option value="3" <?php if($line_func=='3') echo 'selected';?>>Line 3</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">เวลาทำงานจริง (นาที)</label>
                            <input type="number" class="form-control-custom" id="txt_actl_worktime" name="actl_worktime" readonly value="<?php echo $annl_data['ACTL_WORKTIME'] ?? '0'; ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">SP Belt Speed (m/min)</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_sp_speed" name="sp_speed" value="<?php echo number_format((float)($annl_data['SP_SPEEDBELT'] ?? 0), 2); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Actual Belt Speed (m/min)</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_act_speed" name="act_speed" value="<?php echo number_format((float)($annl_data['ANNL_SPEEDBELT'] ?? 0), 2); ?>" />
                        </div>

                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Setpoint Temp (°C) (Z1 / Z2 / Z3)</label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" class="form-control-custom" id="txt_sp_t1" name="sp_t1" value="<?php echo (int)($annl_data['SP_TEMPERATURE1'] ?? 0); ?>" placeholder="Z1" />
                                <input type="number" class="form-control-custom" id="txt_sp_t2" name="sp_t2" value="<?php echo (int)($annl_data['SP_TEMPERATURE2'] ?? 0); ?>" placeholder="Z2" />
                                <input type="number" class="form-control-custom" id="txt_sp_t3" name="sp_t3" value="<?php echo (int)($annl_data['SP_TEMPERATURE3'] ?? 0); ?>" placeholder="Z3" />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Actual Temp (°C) (Z1 / Z2 / Z3)</label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" class="form-control-custom" id="txt_act_t1" name="act_t1" value="<?php echo (int)($annl_data['ANNL_TEMPERATURE1'] ?? 0); ?>" placeholder="Z1" />
                                <input type="number" class="form-control-custom" id="txt_act_t2" name="act_t2" value="<?php echo (int)($annl_data['ANNL_TEMPERATURE2'] ?? 0); ?>" placeholder="Z2" />
                                <input type="number" class="form-control-custom" id="txt_act_t3" name="act_t3" value="<?php echo (int)($annl_data['ANNL_TEMPERATURE3'] ?? 0); ?>" placeholder="Z3" />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Recycle Fan (Fan 1 / 2 / 3)</label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" class="form-control-custom" id="txt_fan1" name="fan1" value="<?php echo (int)($annl_data['RECYCLE_FAN1'] ?? 0); ?>" placeholder="F1" />
                                <input type="number" class="form-control-custom" id="txt_fan2" name="fan2" value="<?php echo (int)($annl_data['RECYCLE_FAN2'] ?? 0); ?>" placeholder="F2" />
                                <input type="number" class="form-control-custom" id="txt_fan3" name="fan3" value="<?php echo (int)($annl_data['RECYCLE_FAN3'] ?? 0); ?>" placeholder="F3" />
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <label class="info-label">Cooling Fan %</label>
                            <input type="number" class="form-control-custom" id="txt_cooling_fan" name="cooling_fan" value="<?php echo (int)($annl_data['COOLING_FAN'] ?? 0); ?>" />
                        </div>
                    </div>

                    <!-- 3. น้ำหนักและจำนวนชิ้นงานผลิต -->
                    <h4 style="color:#0284c7; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⚖️ 3. Weight & Pieces Entry</h4>
                    <div class="row">
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักนำเข้า (Input Weight kg.)</label>
                            <input type="number" class="form-control-custom" id="txt_input_weight" value="<?php echo (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักงานดี (Accept Weight kg.) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_accept_weight" name="accept_weight" required value="<?php echo (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" oninput="calculate_anneal_weight()" style="border-color:#2563eb;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักของเสีย (Reject Weight kg.)</label>
                            <input type="number" class="form-control-custom" id="txt_reject_weight" name="reject_weight" value="0" readonly />
                        </div>

                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนนำเข้า (Input Pcs)</label>
                            <input type="number" class="form-control-custom" id="txt_input_piece" value="<?php echo (float)$prod_data['CRSH_ACTUALPIECE']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนงานดี (Accept Pcs) [AUTO]</label>
                            <input type="number" class="form-control-custom" id="txt_accept_piece" name="accept_piece" value="<?php echo (float)$prod_data['CRSH_ACTUALPIECE']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนของเสีย (Reject Pcs) [AUTO]</label>
                            <input type="number" class="form-control-custom" id="txt_reject_piece" name="reject_piece" value="0" readonly />
                        </div>

                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Pallet / Bottom Weight (Max 200 kg) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_bottom_weight" name="bottom_weight" required value="<?php echo (float)$prod_data['CRSH_BOTTOMWEIGHT']; ?>" oninput="validate_bottom_weight(this)" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 1 *</label>
                            <input type="text" class="form-control-custom" name="operator1" required value="<?php echo htmlspecialchars($iduser_func); ?>" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 2</label>
                            <input type="text" class="form-control-custom" name="operator2" value="" />
                        </div>
                    </div>

                    <!-- เพิ่ม Hidden Field เก็บ Product No ล่าสุดสำหรับพิมพ์ Label -->
                    <input type="hidden" id="txt_last_product_no" value="<?php echo htmlspecialchars($product_no); ?>" />

                    <!-- กลุ่มปุ่มดำเนินการ (เพิ่มปุ่ม Print Label) -->
                    <div class="action-button-group">
                        <button type="button" class="btn-action btn-print" id="btn_print_label" onclick="print_product_label()">
                            🖨️ Print Label
                        </button>
                        <button type="submit" class="btn-action btn-save" id="btn_submit_entry">
                            💾 บันทึกผลการอบอ่อน (Save Annealing)
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
function calculate_anneal_weight() {
    var inWt   = parseFloat(document.getElementById('txt_input_weight').value) || 0;
    var acWt   = parseFloat(document.getElementById('txt_accept_weight').value) || 0;
    var wtPcs  = parseFloat(document.getElementById('calc_weight_piece').value) || 1;

    if (acWt > inWt) {
        alert("❌ น้ำหนักงานดี (" + acWt + " kg.) ห้ามมากกว่าน้ำหนักนำเข้า (" + inWt + " kg.)");
        document.getElementById('txt_accept_weight').value = inWt;
        acWt = inWt;
    }

    var rjWt = inWt - acWt;
    document.getElementById('txt_reject_weight').value = rjWt.toFixed(0);

    var acPcs = Math.round(acWt / wtPcs);
    var rjPcs = Math.round(rjWt / wtPcs);

    document.getElementById('txt_accept_piece').value = acPcs;
    document.getElementById('txt_reject_piece').value = rjPcs;
}

function validate_bottom_weight(elem) {
    var val = parseFloat(elem.value) || 0;
    if (val >= 200) {
        alert('❌ ระบบไม่อนุญาต: น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg.');
        elem.value = '199';
    }
}

function fetch_machine_data() {
    var sDate = document.getElementById('dt_start_date').value;
    var sTime = document.getElementById('dt_start_time').value;
    var eDate = document.getElementById('dt_end_date').value;
    var eTime = document.getElementById('dt_end_time').value;

    if (!sDate || !sTime || !eDate || !eTime) {
        alert("กรุณาระบุวันและเวลาเริ่มต้น-สิ้นสุดให้ครบถ้วนก่อนดึงค่า");
        return;
    }

    var start = sDate + ' ' + sTime + ':00';
    var end   = eDate + ' ' + eTime + ':00';

    var dt1 = new Date(start);
    var dt2 = new Date(end);
    var diffMinutes = Math.round((dt2 - dt1) / (1000 * 60));

    if (diffMinutes <= 0) {
        alert("❌ ข้อมูลเวลาที่ป้อนไม่ถูกต้อง เวลาในการผลิตต้องมากกว่า 0 นาที");
        return;
    }

    var minStd = parseInt(document.getElementById('min_std_minute').value) || 0;
    if (minStd > 0 && diffMinutes < minStd) {
        alert("⚠️ ข้อมูลเวลาที่ป้อน (" + diffMinutes + " นาที) น้อยกว่าที่คำนวณได้จากความสามารถของเครื่องจักร (" + minStd + " นาที)");
    }

    fetch('get_machine_avg.php?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end))
        .then(function(response) {
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error("Server ส่งผลลัพธ์ไม่ใช่ JSON: " + text);
                }
            });
        })
        .then(function(data) {
            if (data.error) {
                alert("❌ เกิดข้อผิดพลาดจากฐานข้อมูล: " + data.error);
                return;
            }

            if (data.no_data) {
                alert("⚠️ ไม่พบข้อมูลบันทึกของเตาอบในช่วงเวลาดังกล่าว (ค่าถูกเซ็ตเป็น 0)");
            }

            document.getElementById('txt_sp_t1').value = Math.round(data.STPTRZ1 || 0);
            document.getElementById('txt_sp_t2').value = Math.round(data.STPTRZ2 || 0);
            document.getElementById('txt_sp_t3').value = Math.round(data.STPTRZ3 || 0);
            document.getElementById('txt_act_t1').value = Math.round(data.ACTTRZ1 || 0);
            document.getElementById('txt_act_t2').value = Math.round(data.ACTTRZ2 || 0);
            document.getElementById('txt_act_t3').value = Math.round(data.ACTTRZ3 || 0);
            document.getElementById('txt_fan1').value = Math.round(data.ACTRFZ1 || 0);
            document.getElementById('txt_fan2').value = Math.round(data.ACTRFZ2 || 0);
            document.getElementById('txt_fan3').value = Math.round(data.ACTRFZ3 || 0);
            document.getElementById('txt_cooling_fan').value = Math.round(data.COOLING_FAN || 0);
            document.getElementById('txt_sp_speed').value = parseFloat(data.STPSROC || 0).toFixed(2);
            document.getElementById('txt_act_speed').value = parseFloat(data.ACTSROC || 0).toFixed(2);
            document.getElementById('txt_actl_worktime').value = diffMinutes;

            if (!data.no_data) {
                alert("✅ เชื่อมต่อดึงค่าจากเตาอบ (FAF2MCHN1) เรียบร้อยแล้ว");
            }
        })
        .catch(function(err) {
            console.error(err);
            alert("เกิดข้อผิดพลาด: " + err.message);
        });
}

function save_anneal_product(e) {
    e.preventDefault();

    var sDate = document.getElementById('dt_start_date').value;
    var sTime = document.getElementById('dt_start_time').value;
    var eDate = document.getElementById('dt_end_date').value;
    var eTime = document.getElementById('dt_end_time').value;

    var dt1 = new Date(sDate + ' ' + sTime);
    var dt2 = new Date(eDate + ' ' + eTime);
    var diffDays = (dt2 - dt1) / (1000 * 60 * 60 * 24);

    if (dt1 > new Date()) {
        alert("❌ วันที่เริ่มผลิต ห้ามมากกว่า วันที่ปัจจุบัน");
        return false;
    }
    if (dt2 < dt1) {
        alert("❌ วันที่และเวลาเสร็จสิ้น ห้ามน้อยกว่า วันที่และเวลาเริ่มต้น");
        return false;
    }
    if (diffDays > 1) {
        alert("❌ กระบวนการ Annealing เวลาที่ผลิตห้ามมากกว่า 1 วัน");
        return false;
    }

    var bottomWt = parseFloat(document.getElementById('txt_bottom_weight').value) || 0;
    if (bottomWt >= 200) {
        alert("❌ น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg.");
        return false;
    }

    var acWt = parseFloat(document.getElementById('txt_accept_weight').value) || 0;
    if (acWt <= 0) {
        alert("❌ กรุณาระบุน้ำหนักงานดีให้ถูกต้อง");
        return false;
    }

    // เปิด Popup Window รอไว้ล่วงหน้าเพื่อป้องกัน Browser บล็อก Popup
    var printWindow = window.open('', '_blank');

    var btnSave = document.getElementById('btn_submit_entry');
    btnSave.disabled = true;
    btnSave.innerHTML = '⏳ กำลังบันทึกข้อมูล...';

    var formElement = document.getElementById('frm_anneal_entry');
    var formData = new FormData(formElement);

    // ส่งข้อมูลไปที่ model/annealing_save_mats.php
    fetch('model/annealing_save_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            document.getElementById('txt_last_product_no').value = data.pno;

            var alertMsg = '✅ บันทึกผลกระบวนการ Annealing สำเร็จ';
            if (data.scrap_no) {
                alertMsg += '\n♻️ สร้าง Scrap อัตโนมัติ: ' + data.scrap_no + ' (' + data.scrap_weight + ' kg)';
            }
            alert(alertMsg);

            // ส่ง URL พิมพ์ Label เข้าไปยังแท็บที่เปิดเตรียมไว้
            var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";
            var printUrl = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(data.pno) + '&joborder=' + encodeURIComponent(jno);
            
            if (printWindow) {
                printWindow.location.href = printUrl;
            } else {
                window.open(printUrl, '_blank');
            }

            // กลับหน้าหลัก
            setTimeout(function() {
                back_home();
            }, 600);

        } else {
            if (printWindow) { printWindow.close(); }
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 บันทึกผลการอบอ่อน (Save Annealing)';
        }
    })
    .catch(function(error) {
        if (printWindow) { printWindow.close(); }
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        btnSave.innerHTML = '💾 บันทึกผลการอบอ่อน (Save Annealing)';
    });
}

function print_product_label() {
    var pno = document.getElementById('txt_last_product_no').value;
    var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";
    
    if (!pno) {
        alert('ไม่พบรหัส Product No สำหรับพิมพ์สติ๊กเกอร์');
        return;
    }

    var url = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(pno) + '&joborder=' + encodeURIComponent(jno);
    window.open(url, '_blank');
}

function back_home() {
    var data_fun = document.getElementById("func").value;
    window.location.assign('annealing_process_mats.php?func=' + encodeURIComponent(data_fun));
}
</script>
</html>