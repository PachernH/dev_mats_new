<?php
// stretcher_entry_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการ Stretcher Process');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

$product_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include("function_mats.php");

if (empty($product_no)) {
    echo "<script>alert('ไม่พบรหัส Product No.'); window.location.href='stretcher_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 1. Guard Check ตาม f9.txt (CHECK-SR และ CHECK-CB)
$stmt_sr = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SORTPROD1 WHERE PRODUCT_NO = :pno");
$stmt_sr->bindParam(':pno', $product_no);
$stmt_sr->execute();
if ($stmt_sr->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Sort แล้ว ให้ทำการแก้ไขข้อมูลที่กระบวนการ Sort หรือลบข้อมูลในกระบวนการ Sort ก่อนจึงสามารถแก้ไขข้อมูลได้'); window.location.href='stretcher_process_mats.php?func={$folder_func}';</script>";
    exit();
}

$stmt_cb = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
$stmt_cb->bindParam(':pno', $product_no);
$stmt_cb->execute();
if ($stmt_cb->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Combine แล้ว ให้ทำการแก้ไขข้อมูลที่กระบวนการ Combine หรือทำการลบข้อมูลในกระบวนการ Combine ก่อนจึงสามารถแก้ไขข้อมูลได้'); window.location.href='stretcher_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 2. ดึงข้อมูล Product และ Job Order
$sql_prod = "SELECT 
                B.PRODUCT_NO, B.MATERIAL_IN, B.COIL_NO, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE, 
                B.ALLOY, B.TEMPER, B.GRADE, B.SURFACE_GRADE, B.METALLURGICAL_GRADE, B.T5_TEMPERATURE, 
                B.THICKNESS, B.WIDTH, B.LENGTH, B.WEIGHT_PIECE, B.CUT_WIDTH, B.CUT_LENGTH, B.CUT_WEIGHTPIECE, 
                B.CRSH_WORKPROCESS, B.CRSH_NEXTPROCESS, B.CRSH_BOTTOMWEIGHT, B.CRSH_STATUS, 
                B.CRSH_ACTUALWEIGHT, B.CRSH_ACTUALPIECE, B.CRSH_STRETCHERWEIGHT, B.CRSH_STRETCHERPIECE,
                B.CRSH_COMBINEWEIGHT, B.CRSH_COMBINEPIECE, B.CRSH_TAKEOUTWEIGHT, B.CRSH_TAKEOUTPIECE,
                C.JOB_ORDER, C.TEMPER AS JOBTEMPER, C.SCHEDULE_DATE, C.JOB_RELEASEWEIGHT, C.JOB_RELEASEPIECE,
                C.JOB_STRETCHERWEIGHT, C.JOB_STRETCHERPIECE, C.SALEORDER_NO, C.SALEORDER_ITEM,
                D.CSTMSPPL_ID, D.CTM2_PONO, D.CTM2_POITEM, D.CONFIRM_DATE, D.CTM2_ORDERWEIGHT, D.CTM2_ORDERPIECE,
                D.CTM2_STRETCHERWEIGHT, D.CTM2_STRETCHERPIECE
             FROM CRSHPROD1 B 
             INNER JOIN JOBORDER1 C ON B.JOB_ORDER = C.JOB_ORDER 
             LEFT JOIN CSTMORDR2 D ON C.SALEORDER_NO = D.SALEORDER_NO AND C.SALEORDER_ITEM = D.SALEORDER_ITEM 
             WHERE B.PRODUCT_NO = :pno";

$stmt_p = $conn->prepare($sql_prod);
$stmt_p->bindParam(':pno', $product_no);
$stmt_p->execute();
$prod_data = $stmt_p->fetch(PDO::FETCH_ASSOC);

if (!$prod_data) {
    echo "<script>alert('ไม่พบข้อมูลชิ้นงานในระบบ'); window.location.href='stretcher_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 3. ตรวจสอบสายการผลิตต้นทาง (CL, RC, KP) ตาม f9.txt
$work_proc = trim($prod_data['CRSH_WORKPROCESS'] ?? '');
$prefix_proc = substr($work_proc, 0, 2);
if (!in_array($prefix_proc, ['CL', 'RC', 'KP'])) {
    echo "<script>alert('ผลิตภัณฑ์ไม่ได้ผลิตมาจากสายกระบวนการผลิต Cut to Length หรือ K Press จึงไม่สามารถทำกระบวนการดึงยืดแผ่นนี้ได้'); window.location.href='stretcher_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 4. ตรวจสอบสถานะ CRSH_STATUS (BCRSH-06)
$crsh_status = strtoupper(trim($prod_data['CRSH_STATUS'] ?? ''));
if (!in_array($crsh_status, ['OP', 'AC'])) {
    echo "<script>
            alert('ผลิตภัณฑ์มีสถานะภาพเป็น : " . $crsh_status . " ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)');
            window.location.href = 'stretcher_process_mats.php?func=" . urlencode($folder_func) . "';
          </script>";
    exit();
}

// 5. ตรวจสอบข้อมูลเดิมใน STCHPROD1
$sql_stch = "SELECT * FROM STCHPROD1 WHERE PRODUCT_NO = :pno";
$stmt_stch = $conn->prepare($sql_stch);
$stmt_stch->bindParam(':pno', $product_no);
$stmt_stch->execute();
$stch_data = $stmt_stch->fetch(PDO::FETCH_ASSOC);

$is_old_record = ($stch_data !== false);

// 6. ตรวจสอบ Next Process และการล็อกย้อนหลัง
$pos_sc = strpos($work_proc, 'SC>');
$calc_next_process = "NN";
$str_outstanding_after = "";
if ($pos_sc !== false) {
    $calc_next_process = substr($work_proc, $pos_sc + 3, 2);
    $str_outstanding_after = substr($work_proc, $pos_sc + 3);
}

// 7. คำนวณตรรกะ SHEET WEIGHT vs PRODUCT WEIGHT ตาม f9.txt
$check_at_process = "PRODUCT WEIGHT";
if (strpos($str_outstanding_after, 'SH>') !== false || strpos($str_outstanding_after, 'CT>') !== false) {
    $check_at_process = "SHEET WEIGHT";
}

$unit_wt = ($check_at_process === "SHEET WEIGHT") ? (float)$prod_data['CUT_WEIGHTPIECE'] : (float)$prod_data['WEIGHT_PIECE'];
if ($unit_wt <= 0) { $unit_wt = 1; }

function format_pcs($value) { return number_format((float)($value ?? 0), 0); }
function format_wt($value)  { return number_format((float)($value ?? 0), 2); }
function format_dim($value) { return number_format((float)($value ?? 0), 3); }
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Stretcher Entry</title>
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
        .btn-delete { background-color: #dc2626; color: #ffffff; }
        .btn-delete:hover { background-color: #b91c1c; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-back:hover { background-color: #475569; }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'A4';?>
<?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="stretcher_process_mats.php?func=<?php echo $folder_func ?>">Stretcher Process [STCHPROD1]</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 22px;">
                    📏 Stretcher: <span style="color:#059669;"><?php echo htmlspecialchars($product_no); ?></span> (Job: <span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?></span>)
                </h3>
                <button type="button" class="btn btn-back" onclick="back_home()">⬅️ Back</button>
            </div>

            <!-- สรุปข้อมูล Product & Job Summary -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="margin:0; font-weight:700; color:#1e3a8a; font-size:16px;">📌 Product & Job Summary</h4>
                    <span class="badge" style="background-color:<?php echo $is_old_record ? '#2563eb' : '#059669'; ?>; font-size:12px; font-weight:700;">
                        <?php echo $is_old_record ? 'Old Record (โหมดแก้ไข)' : 'New Record (บันทึกใหม่)'; ?>
                    </span>
                </div>

                <div class="row" style="margin-bottom: 12px;">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Product No. / ID</div>
                        <div style="font-weight:700; color:#059669; font-size:14px;"><?php echo htmlspecialchars($prod_data['PRODUCT_NO']); ?> (<span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?></span>)</div>
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
                        <div class="info-label">Dimension (T x W x L)</div>
                        <div style="font-weight:700; color:#0284c7; font-size:14px;">
                            <?php echo format_dim($prod_data['THICKNESS']) . ' x ' . format_wt($prod_data['WIDTH']) . ' x ' . format_wt($prod_data['LENGTH']); ?> mm.
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Calculation Mode</div>
                        <div style="font-weight:700; color:#475569; font-size:14px;">
                            <span class="badge" style="background-color:#0284c7; font-size:12px;"><?php echo $check_at_process; ?></span>
                            (<?php echo number_format($unit_wt, 4); ?> kg/pc)
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Routing / Next Process</div>
                        <div style="font-weight:700; color:#475569; font-size:14px;">
                            <?php echo htmlspecialchars($prod_data['CRSH_WORKPROCESS']); ?> ➔ <span style="color:#059669; font-weight:bold;"><?php echo $calc_next_process; ?></span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Customer / PO No.</div>
                        <div style="font-weight:600; font-size:13px;"><?php echo htmlspecialchars($prod_data['CSTMSPPL_ID'] ?? '-'); ?> / <?php echo htmlspecialchars($prod_data['CTM2_PONO'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Form บันทึกผล Stretcher -->
            <div class="dashboard-card">
                <form id="frm_stretcher_entry" onsubmit="save_stretcher_product(event)">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="product_no" id="txt_last_product_no" value="<?php echo htmlspecialchars($product_no); ?>">
                    <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?>">
                    <input type="hidden" name="temper" value="<?php echo htmlspecialchars($prod_data['TEMPER']); ?>">
                    <input type="hidden" name="next_process" value="<?php echo $calc_next_process; ?>">
                    <input type="hidden" name="is_old_record" value="<?php echo $is_old_record ? '1' : '0'; ?>">
                    <input type="hidden" id="calc_unit_weight" value="<?php echo $unit_wt; ?>">

                    <!-- 1. วันที่ เวลา และไลน์กระบวนการผลิต -->
                    <h4 style="color:#059669; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⏰ 1. Production Details</h4>
                    <div class="row" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div class="col-md-2 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">Line Process *</label>
                            <select class="form-control-custom" name="line_process" id="cmb_line_process">
                                <option value="1" <?php echo (!empty($stch_data['LINE_PROCESS']) && $stch_data['LINE_PROCESS'] == '1') ? 'selected' : ''; ?>>Line 1</option>
                                <option value="2" <?php echo (!empty($stch_data['LINE_PROCESS']) && $stch_data['LINE_PROCESS'] == '2') ? 'selected' : ''; ?>>Line 2</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่เริ่มผลิต *</label>
                            <input type="date" class="form-control-custom" id="dt_start_date" name="start_date" required value="<?php echo !empty($stch_data['STCH_STARTDATE']) ? date('Y-m-d', strtotime($stch_data['STCH_STARTDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาเริ่มผลิต *</label>
                            <input type="time" class="form-control-custom" id="dt_start_time" name="start_time" required value="<?php echo !empty($stch_data['STCH_STARTTIME']) ? date('H:i', strtotime($stch_data['STCH_STARTTIME'])) : date('H:i'); ?>" />
                        </div>
                        <div class="col-md-2 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่ผลิตเสร็จ *</label>
                            <input type="date" class="form-control-custom" id="dt_end_date" name="end_date" required value="<?php echo !empty($stch_data['STCH_ENDDATE']) ? date('Y-m-d', strtotime($stch_data['STCH_ENDDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาผลิตเสร็จ *</label>
                            <input type="time" class="form-control-custom" id="dt_end_time" name="end_time" required value="<?php echo !empty($stch_data['STCH_ENDTIME']) ? date('H:i', strtotime($stch_data['STCH_ENDTIME'])) : date('H:i'); ?>" />
                        </div>
                    </div>

                    <!-- 2. ค่าความเรียบแผ่น (Flatness Inspection & I-Unit) เฉพาะ Stretcher -->
                    <h4 style="color:#059669; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">📐 2. Flatness & I-Unit</h4>
                    <div class="row" style="background:#eff6ff; padding:15px; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:20px;">
                        <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">Flatness High (H) (mm) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_flatness_h" name="flatness_h" required value="<?php echo !empty($stch_data['FLATNESS_HIGH']) ? number_format((float)$stch_data['FLATNESS_HIGH'], 2) : '0.00'; ?>" oninput="calculate_i_unit()" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">Flatness Length (L) (mm) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_flatness_l" name="flatness_l" required value="<?php echo !empty($stch_data['FLATNESS_LENGTH']) ? number_format((float)$stch_data['FLATNESS_LENGTH'], 2) : '0.00'; ?>" oninput="calculate_i_unit()" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">I-Unit [AUTO CALCULATED]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_i_unit" name="i_unit" value="<?php echo !empty($stch_data['I_UNIT']) ? number_format((float)$stch_data['I_UNIT'], 2) : '0.00'; ?>" readonly style="background-color:#ffffff; font-weight:700; color:#1d4ed8;" />
                        </div>
                    </div>

                    <!-- 3. จำนวนแผ่นและน้ำหนักผลิต -->
                    <h4 style="color:#059669; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⚖️ 3. Pieces & Weight Entry</h4>
                    <div class="row">
                        <!-- จำนวนแผ่น (Pieces) -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนนำเข้า (Input Pcs)</label>
                            <input type="number" class="form-control-custom" id="txt_input_piece" value="<?php echo $is_old_record ? ((float)$stch_data['STCH_ACCEPTPIECE'] + (float)$stch_data['STCH_REJECTPIECE']) : (float)$prod_data['CRSH_ACTUALPIECE']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label" style="color:#059669;">จำนวนแผ่นงานดี (Accept Pcs) *</label>
                            <input type="number" class="form-control-custom" id="txt_accept_piece" name="accept_piece" required min="1" value="<?php echo $is_old_record ? (float)$stch_data['STCH_ACCEPTPIECE'] : (float)$prod_data['CRSH_ACTUALPIECE']; ?>" oninput="calculate_stretcher_pieces()" style="border-color:#059669; background-color:#f0fdf4;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนแผ่นของเสีย (Reject Pcs) [AUTO]</label>
                            <input type="number" class="form-control-custom" id="txt_reject_piece" name="reject_piece" value="<?php echo $is_old_record ? (float)$stch_data['STCH_REJECTPIECE'] : '0'; ?>" readonly />
                        </div>

                        <!-- น้ำหนัก (Weight) -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักนำเข้า (Input Weight kg.)</label>
                            <input type="number" class="form-control-custom" id="txt_input_weight" value="<?php echo $is_old_record ? ((float)$stch_data['STCH_ACCEPTWEIGHT'] + (float)$stch_data['STCH_REJECTWEIGHT']) : (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักแผ่นงานดี (Accept Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_accept_weight" name="accept_weight" value="<?php echo $is_old_record ? (float)$stch_data['STCH_ACCEPTWEIGHT'] : (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly style="background-color:#eff6ff; color:#2563eb;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักของเสีย (Reject Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_reject_weight" name="reject_weight" value="<?php echo $is_old_record ? (float)$stch_data['STCH_REJECTWEIGHT'] : '0'; ?>" readonly />
                        </div>

                        <!-- พาเลท และ ผู้ปฏิบัติงาน -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักก้นพาเลท / Bottom Weight (< 200 kg) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_bottom_weight" name="bottom_weight" required value="<?php echo (float)$prod_data['CRSH_BOTTOMWEIGHT']; ?>" oninput="validate_bottom_weight(this)" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 1 *</label>
                            <input type="text" class="form-control-custom" name="operator1" required value="<?php echo !empty($stch_data['STCH_OPERATOR1']) ? htmlspecialchars($stch_data['STCH_OPERATOR1']) : htmlspecialchars($iduser_func); ?>" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 2</label>
                            <input type="text" class="form-control-custom" name="operator2" value="<?php echo !empty($stch_data['STCH_OPERATOR2']) ? htmlspecialchars($stch_data['STCH_OPERATOR2']) : ''; ?>" />
                        </div>
                    </div>

                    <div class="action-button-group">
                        <?php if ($is_old_record): ?>
                            <button type="button" class="btn-action btn-delete" onclick="confirm_delete_stretcher()">
                                🗑️ ยกเลิกผลการดึงยืด (Delete Stretcher)
                            </button>
                        <?php endif; ?>

                        <button type="submit" class="btn-action btn-save" id="btn_submit_entry">
                            💾 บันทึกผลการดึงยืด (Save Stretcher)
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
// คำนวณค่า I-Unit ตามสูตรของ f9.txt: ((3.1416 * H / (2 * L))^2) * 100000
function calculate_i_unit() {
    var h = parseFloat(document.getElementById('txt_flatness_h').value) || 0;
    var l = parseFloat(document.getElementById('txt_flatness_l').value) || 0;

    if (h !== 0 && l !== 0) {
        var term = (3.1416 * h) / (2 * l);
        var iUnit = (term * term) * 100000;
        document.getElementById('txt_i_unit').value = iUnit.toFixed(2);
    } else {
        document.getElementById('txt_i_unit').value = '0.00';
    }
}

function calculate_stretcher_pieces() {
    var inPcs  = parseInt(document.getElementById('txt_input_piece').value) || 0;
    var acPcs  = parseInt(document.getElementById('txt_accept_piece').value) || 0;
    var inWt   = parseFloat(document.getElementById('txt_input_weight').value) || 0;
    var unitWt = parseFloat(document.getElementById('calc_unit_weight').value) || 1;

    // เช็ค BCRSH-82 ใน f9.txt
    if (acPcs > inPcs) {
        alert("❌ จำนวนแผ่นงานดี (" + acPcs + " pcs) ห้ามมากกว่าจำนวนนำเข้า (" + inPcs + " pcs) [BCRSH-82]");
        document.getElementById('txt_accept_piece').value = inPcs;
        acPcs = inPcs;
    }

    var rjPcs = inPcs - acPcs;
    document.getElementById('txt_reject_piece').value = rjPcs;

    var acWt = Math.round(acPcs * unitWt);
    if (acPcs === inPcs) {
        acWt = inWt;
    }
    document.getElementById('txt_accept_weight').value = acWt;

    var rjWt = inWt - acWt;
    if (rjWt < 0) { rjWt = 0; }
    document.getElementById('txt_reject_weight').value = rjWt;
}

function validate_bottom_weight(elem) {
    var val = parseFloat(elem.value) || 0;
    // BCRSH-90 ใน f9.txt
    if (val >= 200) {
        alert('❌ ระบบไม่อนุญาต: น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg. (BCRSH-90)');
        elem.value = '199';
    }
}

function save_stretcher_product(e) {
    e.preventDefault();

    var sDate = document.getElementById('dt_start_date').value;
    var sTime = document.getElementById('dt_start_time').value;
    var eDate = document.getElementById('dt_end_date').value;
    var eTime = document.getElementById('dt_end_time').value;

    var dt1 = new Date(sDate + ' ' + sTime);
    var dt2 = new Date(eDate + ' ' + eTime);

    if (dt1 > new Date()) {
        alert("❌ วันที่เริ่มผลิต ห้ามมากกว่า วันที่ปัจจุบัน");
        return false;
    }
    if (dt2 <= dt1) {
        alert("❌ ข้อมูลเวลาที่ป้อนอาจจะไม่ถูกต้อง ใช้เวลาในการผลิตเป็น 0 หรือน้อยกว่า 0");
        return false;
    }

    var bottomWt = parseFloat(document.getElementById('txt_bottom_weight').value) || 0;
    if (bottomWt >= 200) {
        alert("❌ น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg.");
        return false;
    }

    var acPcs = parseInt(document.getElementById('txt_accept_piece').value) || 0;
    if (acPcs <= 0) {
        alert("❌ ข้อมูลจำนวนงานดีต้องมากกว่า 0 (BCRSH-83)");
        return false;
    }

    var btnSave = document.getElementById('btn_submit_entry');
    btnSave.disabled = true;
    btnSave.innerHTML = '⏳ กำลังบันทึกข้อมูล...';

    var formElement = document.getElementById('frm_stretcher_entry');
    var formData = new FormData(formElement);

    fetch('model/stretcher_save_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            alert('✅ บันทึกผลกระบวนการ Stretcher สำเร็จ');
            setTimeout(function() {
                back_home();
            }, 600);
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 บันทึกผลการดึงยืด (Save Stretcher)';
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        btnSave.innerHTML = '💾 บันทึกผลการดึงยืด (Save Stretcher)';
    });
}

function confirm_delete_stretcher() {
    var pno = document.getElementById('txt_last_product_no').value;
    var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";

    var conf = confirm("⚠️ คำยืนยัน: คุณยืนยันการลบข้อมูลหรือไม่?\n\nProduct No: " + pno + "\n- ข้อมูลในตาราง STCHPROD1 จะถูกลบ\n- ค่าสถานะและน้ำหนักจะ Rollback กลับไปกระบวนการก่อนหน้า");
    if (!conf) {
        return;
    }

    var formData = new FormData();
    formData.append('product_no', pno);
    formData.append('job_order', jno);

    fetch('model/stretcher_delete_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            alert('✅ ' + data.message);
            back_home();
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
        }
    })
    .catch(function(err) {
        console.error(err);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
    });
}

function back_home() {
    var data_fun = document.getElementById("func").value;
    window.location.assign('stretcher_process_mats.php?func=' + encodeURIComponent(data_fun));
}
</script>
</html>