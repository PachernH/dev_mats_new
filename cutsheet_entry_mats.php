<?php
// cutsheet_entry_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการ Cut Sheet Process');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

$product_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include("function_mats.php");

if (empty($product_no)) {
    echo "<script>alert('ไม่พบรหัส Product No.'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 1. Guard Check ตาม f9.txt (CHECK-SR และ CHECK-CB)
$stmt_sr = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SORTPROD1 WHERE PRODUCT_NO = :pno");
$stmt_sr->bindParam(':pno', $product_no);
$stmt_sr->execute();
if ($stmt_sr->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Sort แล้ว ให้ทำการแก้ไขข้อมูลที่กระบวนการ Sort หรือลบข้อมูลในกระบวนการ Sort ก่อนจึงสามารถแก้ไขข้อมูลได้'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

$stmt_cb = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
$stmt_cb->bindParam(':pno', $product_no);
$stmt_cb->execute();
if ($stmt_cb->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Combine แล้ว ให้ทำการแก้ไขข้อมูลที่กระบวนการ Combine หรือทำการลบข้อมูลในกระบวนการ Combine ก่อนจึงสามารถแก้ไขข้อมูลได้'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 2. ดึงข้อมูล Product และ Job Order
$sql_prod = "SELECT 
                B.PRODUCT_NO, B.MATERIAL_IN, B.COIL_NO, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE, 
                B.ALLOY, B.TEMPER, B.GRADE, B.SURFACE_GRADE, B.METALLURGICAL_GRADE, B.T5_TEMPERATURE, 
                B.THICKNESS, B.WIDTH, B.LENGTH, B.WEIGHT_PIECE, B.CUT_WIDTH, B.CUT_LENGTH, B.CUT_WEIGHTPIECE, 
                B.CRSH_WORKPROCESS, B.CRSH_NEXTPROCESS, B.CRSH_BOTTOMWEIGHT, B.CRSH_STATUS, 
                B.CRSH_ACTUALWEIGHT, B.CRSH_ACTUALPIECE, B.CRSH_CUTSHEETWEIGHT, B.CRSH_CUTSHEETPIECE,
                B.CRSH_COMBINEWEIGHT, B.CRSH_COMBINEPIECE, B.CRSH_TAKEOUTWEIGHT, B.CRSH_TAKEOUTPIECE,
                C.JOB_ORDER, C.TEMPER AS JOBTEMPER, C.SCHEDULE_DATE, C.JOB_RELEASEWEIGHT, C.JOB_RELEASEPIECE,
                C.JOB_CUTSHEETWEIGHT, C.JOB_CUTSHEETPIECE, C.SALEORDER_NO, C.SALEORDER_ITEM,
                D.CSTMSPPL_ID, D.CTM2_PONO, D.CTM2_POITEM, D.CONFIRM_DATE, D.CTM2_ORDERWEIGHT, D.CTM2_ORDERPIECE,
                D.CTM2_CUTSHEETWEIGHT, D.CTM2_CUTSHEETPIECE
             FROM CRSHPROD1 B 
             INNER JOIN JOBORDER1 C ON B.JOB_ORDER = C.JOB_ORDER 
             LEFT JOIN CSTMORDR2 D ON C.SALEORDER_NO = D.SALEORDER_NO AND C.SALEORDER_ITEM = D.SALEORDER_ITEM 
             WHERE B.PRODUCT_NO = :pno";

$stmt_p = $conn->prepare($sql_prod);
$stmt_p->bindParam(':pno', $product_no);
$stmt_p->execute();
$prod_data = $stmt_p->fetch(PDO::FETCH_ASSOC);

if (!$prod_data) {
    echo "<script>alert('ไม่พบข้อมูลชิ้นงานในระบบ'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 3. ตรวจสอบสายการผลิตต้นทาง (CL, RC, KP) ตาม f9.txt
$work_proc = trim($prod_data['CRSH_WORKPROCESS'] ?? '');
$prefix_proc = substr($work_proc, 0, 2);
if (!in_array($prefix_proc, ['CL', 'RC', 'KP'])) {
    echo "<script>alert('ผลิตภัณฑ์ไม่ได้ผลิตมาจากสายกระบวนการผลิต Cut to Length หรือ K Press จึงไม่สามารถทำกระบวนการตัดแผ่น (Cut Sheet) นี้ได้'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 4. ตรวจสอบสถานะ CRSH_STATUS (BCRSH-06)
$crsh_status = strtoupper(trim($prod_data['CRSH_STATUS'] ?? ''));
if (!in_array($crsh_status, ['OP', 'AC'])) {
    echo "<script>
            alert('ผลิตภัณฑ์มีสถานะภาพเป็น : " . $crsh_status . " ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)');
            window.location.href = 'cutsheet_process_mats.php?func=" . urlencode($folder_func) . "';
          </script>";
    exit();
}

// 5. ตรวจสอบ Product ID (BCRSH-09: ห้ามเป็น SC หรือ CO)
$product_id = trim($prod_data['PRODUCT_ID'] ?? '');
if (in_array($product_id, ['SC', 'CO', ''])) {
    echo "<script>alert('Product ID ({$product_id}) ไม่ถูกต้องสำหรับกระบวนการนี้ (BCRSH-09)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 6. ตรวจสอบ Specification เทียบกับ Sale Order (STNDD-05)
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
            echo "<script>alert('Specification ไม่ถูกต้อง: Alloy ของ Job ไม่ตรงกับ Sale Order (STNDD-05)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (!in_array($so_data['PRODUCT_ID'], ['WR', 'RM'])) {
            if ($prod_data['JOBTEMPER'] !== $so_data['TEMPER']) {
                echo "<script>alert('Specification ไม่ถูกต้อง: Temper ของ Job ไม่ตรงกับ Sale Order (STNDD-05)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
                exit();
            }
        }
        if (round((float)$prod_data['THICKNESS'], 3) != round((float)$so_data['THICKNESS'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Thickness ไม่ตรงกับ Sale Order (STNDD-05)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['WIDTH'], 3) != round((float)$so_data['WIDTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Width ไม่ตรงกับ Sale Order (STNDD-05)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['LENGTH'], 3) != round((float)$so_data['LENGTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Length ไม่ตรงกับ Sale Order (STNDD-05)'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
    }
}

// 7. ตรวจสอบข้อมูลเดิมใน CTSHPROD1
$sql_ctsh = "SELECT * FROM CTSHPROD1 WHERE PRODUCT_NO = :pno";
$stmt_ctsh = $conn->prepare($sql_ctsh);
$stmt_ctsh->bindParam(':pno', $product_no);
$stmt_ctsh->execute();
$ctsh_data = $stmt_ctsh->fetch(PDO::FETCH_ASSOC);

$is_old_record = ($ctsh_data !== false);

// 8. ตรวจสอบ Next Process และเช็คการล็อกย้อนหลัง
$pos_ct = strpos($work_proc, 'CT>');
$calc_next_process = "NN";
$str_outstanding_after = "";
if ($pos_ct !== false) {
    $calc_next_process = substr($work_proc, $pos_ct + 3, 2);
    $str_outstanding_after = substr($work_proc, $pos_ct + 3);
}

$next_process_locked_reason = "";
if ($is_old_record && !empty($calc_next_process)) {
    switch ($calc_next_process) {
        case 'SH':
            $chk = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SHRSPROD1 WHERE PRODUCT_NO = :pno");
            $chk->execute([':pno' => $product_no]);
            if ($chk->fetch()) $next_process_locked_reason = "กระบวนการ Shear (SH) แล้ว";
            break;
        case 'PH':
            $chk = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM PCHLPROD1 WHERE PRODUCT_NO = :pno");
            $chk->execute([':pno' => $product_no]);
            if ($chk->fetch()) $next_process_locked_reason = "กระบวนการ Punch Center Hole (PH) แล้ว";
            break;
        case 'BA':
            $chk = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM BTCHPROD1 WHERE PRODUCT_NO = :pno");
            $chk->execute([':pno' => $product_no]);
            if ($chk->fetch()) {
                $next_process_locked_reason = "กระบวนการ Batch Annealing Output (BA) แล้ว";
            } else {
                $chk2 = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM BTCHPROD0 WHERE PRODUCT_NO = :pno");
                $chk2->execute([':pno' => $product_no]);
                if ($chk2->fetch()) $next_process_locked_reason = "กระบวนการ Batch Annealing Input (BA) แล้ว";
            }
            break;
        case 'TW':
            $chk = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM TPTPPROD1 WHERE PRODUCT_NO = :pno");
            $chk->execute([':pno' => $product_no]);
            if ($chk->fetch()) $next_process_locked_reason = "กระบวนการ Transfer to Pallet (TW) แล้ว";
            break;
    }
}

if (!empty($next_process_locked_reason)) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลใน{$next_process_locked_reason} ให้ทำการลบข้อมูลในกระบวนการนั้นก่อนจึงสามารถแก้ไขข้อมูลได้'); window.location.href='cutsheet_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// 9. คำนวณตรรกะ SHEET WEIGHT vs PRODUCT WEIGHT ตาม f9.txt
$check_at_process = "PRODUCT WEIGHT";
if (strpos($str_outstanding_after, 'SH>') !== false || strpos($str_outstanding_after, 'SC>') !== false) {
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
    <title>Meyer Aluminium - Cut Sheet Entry</title>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="cutsheet_process_mats.php?func=<?php echo $folder_func ?>">Cut Sheet Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 22px;">
                    📄 Cut Sheet: <span style="color:#059669;"><?php echo htmlspecialchars($product_no); ?></span> (Job: <span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?></span>)
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
                        <div class="info-label">Cut Dimension (T x Cut-W x Cut-L)</div>
                        <div style="font-weight:700; color:#0284c7; font-size:14px;">
                            <?php echo format_dim($prod_data['THICKNESS']) . ' x ' . format_wt($prod_data['CUT_WIDTH']) . ' x ' . format_wt($prod_data['CUT_LENGTH']); ?> mm.
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Calculation Mode (การคำนวณ)</div>
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

            <!-- Form บันทึกผล Cut Sheet -->
            <div class="dashboard-card">
                <form id="frm_cutsheet_entry" onsubmit="save_cutsheet_product(event)">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="product_no" id="txt_last_product_no" value="<?php echo htmlspecialchars($product_no); ?>">
                    <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?>">
                    <input type="hidden" name="temper" value="<?php echo htmlspecialchars($prod_data['TEMPER']); ?>">
                    <input type="hidden" name="next_process" value="<?php echo $calc_next_process; ?>">
                    <input type="hidden" name="is_old_record" value="<?php echo $is_old_record ? '1' : '0'; ?>">
                    <input type="hidden" id="calc_unit_weight" value="<?php echo $unit_wt; ?>">

                    <!-- 1. วันที่และเวลาการตัดแผ่น -->
                    <h4 style="color:#059669; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⏰ 1. Cut Sheet Date/Time</h4>
                    <div class="row" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่เริ่มผลิต *</label>
                            <input type="date" class="form-control-custom" id="dt_start_date" name="start_date" required value="<?php echo !empty($ctsh_data['CTSH_STARTDATE']) ? date('Y-m-d', strtotime($ctsh_data['CTSH_STARTDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาเริ่มผลิต *</label>
                            <input type="time" class="form-control-custom" id="dt_start_time" name="start_time" required value="<?php echo !empty($ctsh_data['CTSH_STARTTIME']) ? date('H:i', strtotime($ctsh_data['CTSH_STARTTIME'])) : date('H:i'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่ผลิตเสร็จ *</label>
                            <input type="date" class="form-control-custom" id="dt_end_date" name="end_date" required value="<?php echo !empty($ctsh_data['CTSH_ENDDATE']) ? date('Y-m-d', strtotime($ctsh_data['CTSH_ENDDATE'])) : date('Y-m-d'); ?>" />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาผลิตเสร็จ *</label>
                            <input type="time" class="form-control-custom" id="dt_end_time" name="end_time" required value="<?php echo !empty($ctsh_data['CTSH_ENDTIME']) ? date('H:i', strtotime($ctsh_data['CTSH_ENDTIME'])) : date('H:i'); ?>" />
                        </div>
                    </div>

                    <!-- 2. จำนวนชิ้นและน้ำหนักผลิต (เน้นคีย์ Accept Piece ตาม f9) -->
                    <h4 style="color:#059669; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⚖️ 2. Cut Sheet Pieces & Weight Entry</h4>
                    <div class="row">
                        <!-- แถวที่ 1: ส่วนของจำนวนชิ้น/แผ่น (Pieces) -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนแผ่นนำเข้า (Input Pcs)</label>
                            <input type="number" class="form-control-custom" id="txt_input_piece" value="<?php echo $is_old_record ? ((float)$ctsh_data['CTSH_ACCEPTPIECE'] + (float)$ctsh_data['CTSH_REJECTPIECE']) : (float)$prod_data['CRSH_ACTUALPIECE']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label" style="color:#059669;">จำนวนแผ่นงานดี (Accept Pcs) *</label>
                            <input type="number" class="form-control-custom" id="txt_accept_piece" name="accept_piece" required min="1" value="<?php echo $is_old_record ? (float)$ctsh_data['CTSH_ACCEPTPIECE'] : (float)$prod_data['CRSH_ACTUALPIECE']; ?>" oninput="calculate_cutsheet_pieces()" style="border-color:#059669; background-color:#f0fdf4;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนแผ่นของเสีย (Reject Pcs) [AUTO]</label>
                            <input type="number" class="form-control-custom" id="txt_reject_piece" name="reject_piece" value="<?php echo $is_old_record ? (float)$ctsh_data['CTSH_REJECTPIECE'] : '0'; ?>" readonly />
                        </div>

                        <!-- แถวที่ 2: ส่วนของน้ำหนัก (Weight) คำนวณ Auto -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักนำเข้า (Input Weight kg.)</label>
                            <input type="number" class="form-control-custom" id="txt_input_weight" value="<?php echo $is_old_record ? ((float)$ctsh_data['CTSH_ACCEPTWEIGHT'] + (float)$ctsh_data['CTSH_REJECTWEIGHT']) : (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักแผ่นงานดี (Accept Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_accept_weight" name="accept_weight" value="<?php echo $is_old_record ? (float)$ctsh_data['CTSH_ACCEPTWEIGHT'] : (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly style="background-color:#eff6ff; color:#2563eb;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักของเสีย (Reject Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_reject_weight" name="reject_weight" value="<?php echo $is_old_record ? (float)$ctsh_data['CTSH_REJECTWEIGHT'] : '0'; ?>" readonly />
                        </div>

                        <!-- แถวที่ 3: พาเลท และผู้ปฏิบัติงาน -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักก้นพาเลท / Bottom Weight (สูงสุด < 200 kg) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_bottom_weight" name="bottom_weight" required value="<?php echo (float)$prod_data['CRSH_BOTTOMWEIGHT']; ?>" oninput="validate_bottom_weight(this)" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 1 (Operator 1) *</label>
                            <input type="text" class="form-control-custom" name="operator1" required value="<?php echo !empty($ctsh_data['CTSH_OPERATOR1']) ? htmlspecialchars($ctsh_data['CTSH_OPERATOR1']) : htmlspecialchars($iduser_func); ?>" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 2 (Operator 2)</label>
                            <input type="text" class="form-control-custom" name="operator2" value="<?php echo !empty($ctsh_data['CTSH_OPERATOR2']) ? htmlspecialchars($ctsh_data['CTSH_OPERATOR2']) : ''; ?>" />
                        </div>
                    </div>

                    <div class="action-button-group">
                        <!-- ปุ่ม Delete: แสดงเฉพาะกรณีเป็นข้อมูลเก่าใน CTSHPROD1 -->
                        <?php if ($is_old_record): ?>
                            <button type="button" class="btn-action btn-delete" onclick="confirm_delete_cutsheet()">
                                🗑️ ยกเลิกผลการตัดแผ่น (Delete Cut Sheet)
                            </button>
                        <?php endif; ?>

                        <button type="submit" class="btn-action btn-save" id="btn_submit_entry">
                            💾 บันทึกผลการตัดแผ่น (Save Cut Sheet)
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
function calculate_cutsheet_pieces() {
    var inPcs  = parseInt(document.getElementById('txt_input_piece').value) || 0;
    var acPcs  = parseInt(document.getElementById('txt_accept_piece').value) || 0;
    var inWt   = parseFloat(document.getElementById('txt_input_weight').value) || 0;
    var unitWt = parseFloat(document.getElementById('calc_unit_weight').value) || 1;

    // เงื่อนไข BCRSH-82 ใน f9: จำนวนงานดีต้องไม่เกินจำนวนนำเข้า
    if (acPcs > inPcs) {
        alert("❌ จำนวนแผ่นงานดี (" + acPcs + " pcs) ห้ามมากกว่าจำนวนนำเข้า (" + inPcs + " pcs) [BCRSH-82]");
        document.getElementById('txt_accept_piece').value = inPcs;
        acPcs = inPcs;
    }

    // คำนวณของเสีย
    var rjPcs = inPcs - acPcs;
    document.getElementById('txt_reject_piece').value = rjPcs;

    // คำนวณ Accept Weight ตามอัตราส่วนน้ำหนักต่อแผ่น
    var acWt = Math.round(acPcs * unitWt);
    if (acPcs === inPcs) {
        acWt = inWt;
    }
    document.getElementById('txt_accept_weight').value = acWt;

    // คำนวณ Reject Weight
    var rjWt = inWt - acWt;
    if (rjWt < 0) { rjWt = 0; }
    document.getElementById('txt_reject_weight').value = rjWt;
}

function validate_bottom_weight(elem) {
    var val = parseFloat(elem.value) || 0;
    // เงื่อนไข BCRSH-90 ใน f9: น้ำหนักก้นพาเลทต้องน้อยกว่า 200 kg
    if (val >= 200) {
        alert('❌ ระบบไม่อนุญาต: น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg. (BCRSH-90)');
        elem.value = '199';
    }
}

function save_cutsheet_product(e) {
    e.preventDefault();

    var sDate = document.getElementById('dt_start_date').value;
    var sTime = document.getElementById('dt_start_time').value;
    var eDate = document.getElementById('dt_end_date').value;
    var eTime = document.getElementById('dt_end_time').value;

    var dt1 = new Date(sDate + ' ' + sTime);
    var dt2 = new Date(eDate + ' ' + eTime);

    // ตรวจสอบเงื่อนไขวันเวลาตาม f9
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

    var formElement = document.getElementById('frm_cutsheet_entry');
    var formData = new FormData(formElement);

    fetch('model/cutsheet_save_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            alert('✅ บันทึกผลกระบวนการ Cut Sheet สำเร็จ');
            setTimeout(function() {
                back_home();
            }, 600);
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 บันทึกผลการตัดแผ่น (Save Cut Sheet)';
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        btnSave.innerHTML = '💾 บันทึกผลการตัดแผ่น (Save Cut Sheet)';
    });
}

function confirm_delete_cutsheet() {
    var pno = document.getElementById('txt_last_product_no').value;
    var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";

    var conf = confirm("⚠️ คำยืนยัน: คุณยืนยันการลบข้อมูลหรือไม่?\n\nProduct No: " + pno + "\n- ข้อมูลในตาราง CTSHPROD1 จะถูกลบ\n- ค่าสถานะ Next Process และน้ำหนักจะ Rollback กลับไปกระบวนการก่อนหน้า");
    if (!conf) {
        return;
    }

    var formData = new FormData();
    formData.append('product_no', pno);
    formData.append('job_order', jno);

    fetch('model/cutsheet_delete_mats.php', {
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
    window.location.assign('cutsheet_process_mats.php?func=' + encodeURIComponent(data_fun));
}
</script>
</html>