<?php
// punch_hole_entry_mats.php
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
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการ Punch Center Hole Process');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

$product_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include("function_mats.php");

if (empty($product_no)) {
    echo "<script>alert('ไม่พบรหัส Product No.'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// =========================================================================
// 1. Guard Check เฉพาะตาม f8.txt (ตรวจห้ามติด 5 กระบวนการ)
// =========================================================================
// Check 1: ห้ามติด Sort
$stmt_sr = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM SORTPROD1 WHERE PRODUCT_NO = :pno");
$stmt_sr->bindParam(':pno', $product_no);
$stmt_sr->execute();
if ($stmt_sr->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Sort แล้ว'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// Check 2: ห้ามติด Combine
$stmt_cb = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
$stmt_cb->bindParam(':pno', $product_no);
$stmt_cb->execute();
if ($stmt_cb->fetch()) {
    echo "<script>alert('พบผลิตภัณฑ์ : {$product_no} ได้มีรายการอื่นมา Combine อยู่ ต้องปลดหรือลบ Combine ก่อน'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// Check 3: ห้ามติด Transfer Product to Pallet
$stmt_tw = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM TPTPPROD1 WHERE PRODUCT_NO = :pno");
$stmt_tw->bindParam(':pno', $product_no);
$stmt_tw->execute();
if ($stmt_tw->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Transfer Product to Pallet แล้ว'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// Check 4: ห้ามติด Batch Annealing (Out/In)
$stmt_bo = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM BTCHPROD1 WHERE PRODUCT_NO = :pno");
$stmt_bo->bindParam(':pno', $product_no);
$stmt_bo->execute();
if ($stmt_bo->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Output Batch Annealing แล้ว'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

$stmt_bi = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM BTCHPROD0 WHERE PRODUCT_NO = :pno");
$stmt_bi->bindParam(':pno', $product_no);
$stmt_bi->execute();
if ($stmt_bi->fetch()) {
    echo "<script>alert('ไม่อนุญาตให้แก้ไขข้อมูล เนื่องจากมีการทำข้อมูลในกระบวนการ Input Batch Annealing แล้ว'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// =========================================================================
// 2. ดึงข้อมูล Product และ Job Order
// =========================================================================
$sql_prod = "SELECT 
                B.PRODUCT_NO, B.MATERIAL_IN, B.COIL_NO, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE, 
                B.ALLOY, B.TEMPER, B.GRADE, B.SURFACE_GRADE, B.METALLURGICAL_GRADE, B.T5_TEMPERATURE, 
                B.THICKNESS, B.WIDTH, B.LENGTH, B.WEIGHT_PIECE, B.CUT_WIDTH, B.CUT_LENGTH, B.CUT_WEIGHTPIECE, 
                B.CRSH_WORKPROCESS, B.CRSH_NEXTPROCESS, B.CRSH_BOTTOMWEIGHT, B.CRSH_STATUS, 
                B.CRSH_ACTUALWEIGHT, B.CRSH_ACTUALPIECE, B.CRSH_PUNCHWEIGHT, B.CRSH_PUNCHPIECE,
                C.JOB_ORDER, C.TEMPER AS JOBTEMPER, C.SCHEDULE_DATE, C.JOB_RELEASEWEIGHT, C.JOB_RELEASEPIECE,
                C.JOB_PUNCHWEIGHT, C.JOB_PUNCHPIECE, C.SALEORDER_NO, C.SALEORDER_ITEM,
                D.CSTMSPPL_ID, D.CTM2_PONO, D.CTM2_POITEM, D.CONFIRM_DATE, D.CTM2_ORDERWEIGHT, D.CTM2_ORDERPIECE,
                D.CTM2_PUNCHWEIGHT, D.CTM2_PUNCHPIECE
             FROM CRSHPROD1 B 
             INNER JOIN JOBORDER1 C ON B.JOB_ORDER = C.JOB_ORDER 
             LEFT JOIN CSTMORDR2 D ON C.SALEORDER_NO = D.SALEORDER_NO AND C.SALEORDER_ITEM = D.SALEORDER_ITEM 
             WHERE B.PRODUCT_NO = :pno";

$stmt_p = $conn->prepare($sql_prod);
$stmt_p->bindParam(':pno', $product_no);
$stmt_p->execute();
$prod_data = $stmt_p->fetch(PDO::FETCH_ASSOC);

if (!$prod_data) {
    echo "<script>alert('ไม่พบข้อมูลชิ้นงานในระบบ'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// =========================================================================
// 3. ตรวจสอบสถานะ CRSH_STATUS ตามเงื่อนไข BCRSH-06 ใน f8.txt
// =========================================================================
$crsh_status = strtoupper(trim($prod_data['CRSH_STATUS'] ?? ''));
if (!in_array($crsh_status, ['OP', 'AC'])) {
    echo "<script>
            alert('ผลิตภัณฑ์มีสถานะภาพเป็น : " . $crsh_status . " ไม่เท่ากับสถานะภาพ : OP: OPEN (BCRSH-06)');
            window.location.href = 'punch_hole_process_mats.php?func=" . urlencode($folder_func) . "';
          </script>";
    exit();
}

// =========================================================================
// 4. ตรวจสอบ Specification เทียบกับ Sale Order
// =========================================================================
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
            echo "<script>alert('Specification ไม่ถูกต้อง: Alloy ของ Job ไม่ตรงกับ Sale Order'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (!in_array($so_data['PRODUCT_ID'], ['WR', 'RM'])) {
            if ($prod_data['JOBTEMPER'] !== $so_data['TEMPER']) {
                echo "<script>alert('Specification ไม่ถูกต้อง: Temper ของ Job ไม่ตรงกับ Sale Order'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
                exit();
            }
        }
        if (round((float)$prod_data['THICKNESS'], 3) != round((float)$so_data['THICKNESS'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Thickness ไม่ตรงกับ Sale Order'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['WIDTH'], 3) != round((float)$so_data['WIDTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Width ไม่ตรงกับ Sale Order'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
        if (round((float)$prod_data['LENGTH'], 3) != round((float)$so_data['LENGTH'], 3)) {
            echo "<script>alert('Specification ไม่ถูกต้อง: Length ไม่ตรงกับ Sale Order'); window.location.href='punch_hole_process_mats.php?func={$folder_func}';</script>";
            exit();
        }
    }
}

// =========================================================================
// 5. คำนวณ Next Process และตรวจสอบการล็อกฟิลด์ตาม EnableField ใน f8.txt
// =========================================================================
$work_proc = $prod_data['CRSH_WORKPROCESS'] ?? '';
$pos_ph = strpos($work_proc, 'PH>');
$calc_next_process = "NN";
if ($pos_ph !== false) {
    $calc_next_process = substr($work_proc, $pos_ph + 3, 2);
}

// ตรวจสอบว่าขั้นตอนถัดไปในปัจจุบันถูกส่งต่อเกินไปแล้วหรือไม่ตาม f8.txt
$curr_next_process = trim($prod_data['CRSH_NEXTPROCESS'] ?? '');
$is_field_locked = false;
if (!empty($curr_next_process) && $curr_next_process !== 'PH') {
    if ($curr_next_process !== $calc_next_process && $curr_next_process !== 'IS') {
        $is_field_locked = true; // ล็อกไม่ให้แก้ไขยอดถ้าข้ามขั้นตอนไปไกลแล้ว
    }
}

// โหมดน้ำหนักชิ้นงานล็อกเป็น PRODUCT WEIGHT ตาม f8.txt
$wt_piece_to_use = (float)$prod_data['WEIGHT_PIECE'];
if ($wt_piece_to_use <= 0) { $wt_piece_to_use = 1; }

// ดึงข้อมูลเดิมใน PCHLPROD1 (ถ้ามี)
$sql_pchl = "SELECT * FROM PCHLPROD1 WHERE PRODUCT_NO = :pno";
$stmt_pchl = $conn->prepare($sql_pchl);
$stmt_pchl->bindParam(':pno', $product_no);
$stmt_pchl->execute();
$pchl_data = $stmt_pchl->fetch(PDO::FETCH_ASSOC);

function format_pcs($value) { return number_format((float)($value ?? 0), 0); }
function format_wt($value)  { return number_format((float)($value ?? 0), 2); }
function format_dim($value) { return number_format((float)($value ?? 0), 3); }
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Punch Center Hole Entry</title>
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
        .btn-save { background-color: #7c3aed; color: #ffffff; }
        .btn-save:hover { background-color: #6d28d9; }
        .btn-print { background-color: #0284c7; color: #ffffff; }
        .btn-print:hover { background-color: #0369a1; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-back:hover { background-color: #475569; }
        .badge-locked { background-color: #fee2e2; color: #b91c1c; border: 1px solid #f87171; font-size: 13px; font-weight: 700; padding: 6px 14px; border-radius: 6px; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="punch_hole_process_mats.php?func=<?php echo $folder_func ?>">Punch Center Hole Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 22px;">
                    🔘 Punch Hole Entry: <span style="color:#7c3aed;"><?php echo htmlspecialchars($product_no); ?></span> (Job: <span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?></span>)
                </h3>
                <button type="button" class="btn btn-back" onclick="back_home()">⬅️ Back</button>
            </div>

            <!-- สรุปข้อมูล Product & Job Summary -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                    <h4 style="margin:0; font-weight:700; color:#1e3a8a; font-size:16px;">📌 Product & Job Summary</h4>
                    <span class="badge" style="background-color:#059669; font-size:12px; font-weight:700;"><?php echo htmlspecialchars($prod_data['CRSH_STATUS']); ?></span>
                </div>

                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Product No. / ID</div>
                        <div style="font-weight:700; color:#7c3aed; font-size:14px;"><?php echo htmlspecialchars($prod_data['PRODUCT_NO']); ?> (<span style="color:#2563eb;"><?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?></span>)</div>
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
                        <div style="font-weight:700; color:#475569; font-size:14px;">
                            <?php echo htmlspecialchars($prod_data['CRSH_WORKPROCESS']); ?> ➔ <span style="color:#7c3aed; font-weight:bold;"><?php echo $calc_next_process; ?></span>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="info-label">Punch Hole Status</div>
                        <div>
                            <span class="badge" style="background-color:#059669; font-size:12px;">✅ เจาะรูเสร็จพิมพ์ฉลากได้ทันที</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_field_locked): ?>
                <div class="badge-locked" style="margin-bottom: 20px;">
                    🔒 ชิ้นงานนี้ได้ถูกส่งต่อไปยังขั้นตอนถัดไปแล้ว (<?php echo htmlspecialchars($curr_next_process); ?>) ระบบไม่อนุญาตให้แก้ไขจำนวนและน้ำหนักตามเงื่อนไข EnableField ของ f8.txt
                </div>
            <?php endif; ?>

            <!-- Form บันทึกผล Punch Hole -->
            <div class="dashboard-card">
                <form id="frm_punch_entry" onsubmit="save_punch_product(event)">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="product_no" id="txt_last_product_no" value="<?php echo htmlspecialchars($product_no); ?>">
                    <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($prod_data['PRODUCT_ID']); ?>">
                    <input type="hidden" name="temper" value="<?php echo htmlspecialchars($prod_data['TEMPER']); ?>">
                    <input type="hidden" name="next_process" value="<?php echo $calc_next_process; ?>">
                    <input type="hidden" name="width" value="<?php echo $prod_data['WIDTH']; ?>">
                    <input type="hidden" id="calc_weight_piece" value="<?php echo $wt_piece_to_use; ?>">

                    <!-- 1. วันที่และเวลาการผลิต -->
                    <h4 style="color:#7c3aed; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⏰ 1. วันที่และเวลาการเจาะรู (Production Date/Time)</h4>
                    <div class="row" style="background:#f8fafc; padding:15px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่เริ่มผลิต *</label>
                            <input type="date" class="form-control-custom" id="dt_start_date" name="start_date" required value="<?php echo !empty($pchl_data['PCHL_STARTDATE']) ? date('Y-m-d', strtotime($pchl_data['PCHL_STARTDATE'])) : date('Y-m-d'); ?>" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาเริ่มผลิต *</label>
                            <input type="time" class="form-control-custom" id="dt_start_time" name="start_time" required value="<?php echo !empty($pchl_data['PCHL_STARTTIME']) ? date('H:i', strtotime($pchl_data['PCHL_STARTTIME'])) : date('H:i'); ?>" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">วันที่ผลิตเสร็จ *</label>
                            <input type="date" class="form-control-custom" id="dt_end_date" name="end_date" required value="<?php echo !empty($pchl_data['PCHL_ENDDATE']) ? date('Y-m-d', strtotime($pchl_data['PCHL_ENDDATE'])) : date('Y-m-d'); ?>" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-3 col-sm-6" style="margin-bottom:10px;">
                            <label class="info-label">เวลาผลิตเสร็จ *</label>
                            <input type="time" class="form-control-custom" id="dt_end_time" name="end_time" required value="<?php echo !empty($pchl_data['PCHL_ENDTIME']) ? date('H:i', strtotime($pchl_data['PCHL_ENDTIME'])) : date('H:i'); ?>" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                    </div>

                    <!-- 2. น้ำหนักและจำนวนชิ้นงานผลิต (กรอกจำนวนชิ้น คำนวณน้ำหนัก Auto) -->
                    <h4 style="color:#7c3aed; font-size:16px; font-weight:700; margin-top:0; margin-bottom:12px;">⚖️ 2. ข้อมูลจำนวนชิ้นและน้ำหนักผลิต (Pieces & Weight Entry)</h4>
                    <div class="row">
                        <!-- แถวที่ 1: ส่วนของจำนวนชิ้น (Pieces) -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนนำเข้า (Input Pcs)</label>
                            <input type="number" class="form-control-custom" id="txt_input_piece" value="<?php echo (float)$prod_data['CRSH_ACTUALPIECE']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label" style="color:#7c3aed;">จำนวนงานดี (Accept Pcs) *</label>
                            <input type="number" class="form-control-custom" id="txt_accept_piece" name="accept_piece" required min="1" value="<?php echo (float)$prod_data['CRSH_ACTUALPIECE']; ?>" oninput="calculate_punch_pieces()" style="border-color:#7c3aed; background-color:#f5f3ff;" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">จำนวนของเสีย (Reject Pcs) [AUTO]</label>
                            <input type="number" class="form-control-custom" id="txt_reject_piece" name="reject_piece" value="0" readonly />
                        </div>

                        <!-- แถวที่ 2: ส่วนของน้ำหนัก (Weight) คำนวณ Auto -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักนำเข้า (Input Weight kg.)</label>
                            <input type="number" class="form-control-custom" id="txt_input_weight" value="<?php echo (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักงานดี (Accept Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_accept_weight" name="accept_weight" value="<?php echo (float)$prod_data['CRSH_ACTUALWEIGHT']; ?>" readonly style="background-color:#eff6ff; color:#2563eb;" />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">น้ำหนักของเสีย (Reject Weight kg.) [AUTO]</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_reject_weight" name="reject_weight" value="0" readonly />
                        </div>

                        <!-- แถวที่ 3: ข้อมูลพาเลทและผู้ปฏิบัติงาน -->
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">Pallet / Bottom Weight (Max 200 kg) *</label>
                            <input type="number" step="0.01" class="form-control-custom" id="txt_bottom_weight" name="bottom_weight" required value="<?php echo (float)$prod_data['CRSH_BOTTOMWEIGHT']; ?>" oninput="validate_bottom_weight(this)" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 1 *</label>
                            <input type="text" class="form-control-custom" name="operator1" required value="<?php echo htmlspecialchars($iduser_func); ?>" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                        <div class="col-md-4 col-sm-6" style="margin-bottom:12px;">
                            <label class="info-label">ผู้ปฏิบัติการ 2</label>
                            <input type="text" class="form-control-custom" name="operator2" value="" <?php echo $is_field_locked ? 'readonly' : ''; ?> />
                        </div>
                    </div>

                    <!-- ค้นหาส่วน action-button-group ใน punch_hole_entry_mats.php แล้วแทนที่ด้วยชุดนี้ -->
                    <div class="action-button-group">
                        <!-- แสดงปุ่ม Delete เฉพาะกรณีเป็นงานที่เคยบันทึกใน PCHLPROD1 ไปแล้ว และยังไม่ถูกล็อกข้ามขั้นตอน -->
                        <?php if ($pchl_data && !$is_field_locked): ?>
                            <button type="button" class="btn-action" style="background-color:#dc2626; color:#ffffff;" onclick="confirm_delete_punch()">
                                🗑️ ยกเลิกผลการเจาะรู (Delete)
                            </button>
                        <?php endif; ?>

                        <button type="button" class="btn-action btn-print" id="btn_print_label" onclick="print_product_label()">
                            🖨️ Print Label
                        </button>

                        <?php if (!$is_field_locked): ?>
                            <button type="submit" class="btn-action btn-save" id="btn_submit_entry">
                                💾 บันทึกผลการเจาะรู (Save Punch Hole)
                            </button>
                        <?php endif; ?>
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
function calculate_punch_pieces() {
    var inPcs  = parseInt(document.getElementById('txt_input_piece').value) || 0;
    var acPcs  = parseInt(document.getElementById('txt_accept_piece').value) || 0;
    var inWt   = parseFloat(document.getElementById('txt_input_weight').value) || 0;
    var wtPcs  = parseFloat(document.getElementById('calc_weight_piece').value) || 1;

    if (acPcs > inPcs) {
        alert("❌ จำนวนงานดี (" + acPcs + " pcs) ห้ามมากกว่าจำนวนนำเข้า (" + inPcs + " pcs)");
        document.getElementById('txt_accept_piece').value = inPcs;
        acPcs = inPcs;
    }

    var rjPcs = inPcs - acPcs;
    document.getElementById('txt_reject_piece').value = rjPcs;

    var acWt = Math.round(acPcs * wtPcs);
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
    if (val >= 200) {
        alert('❌ ระบบไม่อนุญาต: น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg.');
        elem.value = '199';
    }
}

function save_punch_product(e) {
    e.preventDefault();

    var sDate = document.getElementById('dt_start_date').value;
    var sTime = document.getElementById('dt_start_time').value;
    var eDate = document.getElementById('dt_end_date').value;
    var eTime = document.getElementById('dt_end_time').value;

    var dt1 = new Date(sDate + ' ' + sTime);
    var dt2 = new Date(eDate + ' ' + eTime);

    if (dt1 > new Date()) {
        alert("❌ วันที่เริ่ม ห้ามมากกว่า วันที่ปัจจุบัน");
        return false;
    }
    if (dt2 < dt1) {
        alert("❌ วันที่และเวลาเสร็จสิ้น ห้ามน้อยกว่า วันที่และเวลาเริ่มต้น");
        return false;
    }

    var bottomWt = parseFloat(document.getElementById('txt_bottom_weight').value) || 0;
    if (bottomWt >= 200) {
        alert("❌ น้ำหนักก้นพาเลท (Bottom Weight) ต้องน้อยกว่า 200 kg.");
        return false;
    }

    var acPcs = parseInt(document.getElementById('txt_accept_piece').value) || 0;
    if (acPcs <= 0) {
        alert("❌ กรุณาระบุจำนวนงานดีให้ถูกต้อง");
        return false;
    }

    var printWindow = window.open('', '_blank');

    var btnSave = document.getElementById('btn_submit_entry');
    btnSave.disabled = true;
    btnSave.innerHTML = '⏳ กำลังบันทึกข้อมูล...';

    var formElement = document.getElementById('frm_punch_entry');
    var formData = new FormData(formElement);

    fetch('model/punch_hole_save_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.status === 'success') {
            document.getElementById('txt_last_product_no').value = data.pno;

            var alertMsg = '✅ บันทึกผลกระบวนการ Punch Center Hole สำเร็จ';
            if (data.scrap_no) {
                alertMsg += '\n♻️ สร้าง Scrap อัตโนมัติ: ' + data.scrap_no + ' (' + data.scrap_weight + ' kg)';
            }
            alert(alertMsg);

            var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";
            var printUrl = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(data.pno) + '&joborder=' + encodeURIComponent(jno);
            if (printWindow) {
                printWindow.location.href = printUrl;
            } else {
                window.open(printUrl, '_blank');
            }

            setTimeout(function() {
                back_home();
            }, 600);

        } else {
            if (printWindow) { printWindow.close(); }
            alert('❌ เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 บันทึกผลการเจาะรู (Save Punch Hole)';
        }
    })
    .catch(function(error) {
        if (printWindow) { printWindow.close(); }
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        btnSave.innerHTML = '💾 บันทึกผลการเจาะรู (Save Punch Hole)';
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
    window.location.assign('punch_hole_process_mats.php?func=' + encodeURIComponent(data_fun));
}

function confirm_delete_punch() {
    var pno = document.getElementById('txt_last_product_no').value;
    var jno = "<?php echo htmlspecialchars($prod_data['JOB_ORDER']); ?>";

    var conf = confirm("⚠️ คำเตือน: คุณต้องการยกเลิกผลการเจาะรู (Rollback) ของ Product No: " + pno + " ใช่หรือไม่?\n\n- ข้อมูลใน PCHLPROD1 จะถูกลบ\n- สถานะ Next Process จะถูกดึงกลับมาเป็น PH เพื่อรอเจาะใหม่\n- ยอดผลิตสะสมใน Job Order จะถูกคำนวณหักออก");
    if (!conf) {
        return;
    }

    var formData = new FormData();
    formData.append('product_no', pno);
    formData.append('job_order', jno);

    fetch('model/punch_hole_delete_mats.php', {
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
</script>
</html>