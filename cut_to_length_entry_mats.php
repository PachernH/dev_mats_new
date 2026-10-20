<?php
// เริ่ม session
session_start();

// ดึงค่า Session
$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func   = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// ถ้าไม่มี Session ให้กลับไปหน้า index.php
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// ตรวจสอบ Line Process (Cut to Length: Line A, B)
$allowed_lines_ctl = ['A', 'B'];
if (!in_array(strtoupper($line_func), $allowed_lines_ctl)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ (" . $line_func . ") ไม่ตรงกับกระบวนการ Cut to Length Process (Line A, B)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

// รับค่า URL Parameters
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '';
$coil_no   = isset($_GET['cno']) ? htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data       = null;
$coil_data      = null;
$history_list   = [];
$wt_piece_to_use = 0;
$wt_type_label  = '';

if (!empty($job_order) && !empty($coil_no)) {
    // 1. ดึงข้อมูล Job Order
    $sql_job = "SELECT 
                    j.JOB_ORDER, j.JOB_STATUS, j.SALEORDER_NO, j.SALEORDER_ITEM, 
                    c.CSTMSPPL_ID, c.CONFIRM_DATE, j.ALLOY, j.TEMPER, j.PRODUCE_TEMPER1, j.PRODUCE_TEMPER2, 
                    j.GRADE, j.PRODUCE_GRADE1, j.PRODUCE_GRADE2, j.SURFACE_GRADE, j.METALLURGICAL_GRADE, 
                    j.T5_TEMPERATURE, j.THICKNESS, j.WIDTH, j.LENGTH, j.WEIGHT_PIECE, 
                    j.CUT_WIDTH, j.CUT_LENGTH, j.CUT_WEIGHTPIECE, j.JOB_WORKPROCESS, 
                    j.JOB_RELEASEWEIGHT, j.JOB_RELEASEPIECE, j.JOB_PRODUCEWEIGHT, j.JOB_PRODUCEPIECE,
                    j.JOB_MAXTOLERANCE, j.JOB_MINTOLERANCE, j.MATERIAL_IN, j.JOB_REMARK, j.PRODUCT_ID
                FROM JOBORDER1 j
                LEFT JOIN CSTMORDR2 c ON j.SALEORDER_NO = c.SALEORDER_NO AND j.SALEORDER_ITEM = c.SALEORDER_ITEM
                WHERE j.JOB_ORDER = :job_order";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if ($job_data) {
        // === LOGIC f4: เช็กเงื่อนไข WorkProcess เพื่อเลือก Weight per Piece ในการคำนวณ ===
        $work_proc = $job_data['JOB_WORKPROCESS'] ?? '';
        $pos_sh = strpos($work_proc, 'SH>');
        $pos_sc = strpos($work_proc, 'SC>');
        $pos_ct = strpos($work_proc, 'CT>');

        // เทียบเท่า InStr(1, txtWorkProcess, "SH>") > 2 Or InStr > 2 Or InStr > 2
        if (($pos_sh !== false && $pos_sh >= 2) || 
            ($pos_sc !== false && $pos_sc >= 2) || 
            ($pos_ct !== false && $pos_ct >= 2)) {
            $wt_piece_to_use = (float)($job_data['CUT_WEIGHTPIECE'] ?? 0);
            $wt_type_label   = 'Cut Sheet Wt. per Piece';
        } else {
            $wt_piece_to_use = (float)($job_data['WEIGHT_PIECE'] ?? 0);
            $wt_type_label   = 'Order Weight per Piece';
        }
    }

    // คำนวณเพดานสูงสุดและยอดคงเหลือที่ผลิตเพิ่มได้อีก
    $rel_wt       = (float)($job_data['JOB_RELEASEWEIGHT'] ?? 0);
    $prod_wt      = (float)($job_data['JOB_PRODUCEWEIGHT'] ?? 0);
    $max_tol_pct  = (float)($job_data['JOB_MAXTOLERANCE'] ?? 0);

    // เพดานน้ำหนักสูงสุดตาม Tolerance
    $max_allow_wt = $rel_wt + (($rel_wt * $max_tol_pct) / 100);

    // น้ำหนักส่วนคงเหลือที่ยังผลิตเพิ่มได้อีกไม่เกินเพดาน
    $remain_allow_wt = $max_allow_wt - $prod_wt;
    if ($remain_allow_wt < 0) { $remain_allow_wt = 0; }

    // 2. ดึงข้อมูล Coil
    $sql_coil = "SELECT 
                    COIL_NO, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH,
                    COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT, COIL_ADJUSTWEIGHT, COIL_BALANCEWEIGHT,
                    LOCATION_ID, MATERIAL_IN, COIL_STATUS, T5_TEMPERATURE, RECIPE_NO, CURRENT_PASS
                 FROM COILPROD1
                 WHERE COIL_NO = :coil_no";

    $stmt_coil = $conn->prepare($sql_coil);
    $stmt_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_coil->execute();
    $coil_data = $stmt_coil->fetch(PDO::FETCH_ASSOC);

    // 3. ดึงประวัติรายการ Pallet ที่ตัดผลิตเสร็จแล้วใน Job Order นี้
    $sql_hist = "SELECT 
                    PRODUCT_NO, COIL_NO, CRSH_PRODUCEPIECE, CRSH_PRODUCEWEIGHT, 
                    CRSH_STARTDATE, CRSH_STARTTIME, CRSH_ENDDATE, CRSH_ENDTIME, 
                    CRSH_OPERATOR1, CRSH_OPERATEDATE
                 FROM CRSHPROD1 
                 WHERE JOB_ORDER = :job_order AND (CRSH_STATUS = 'OP' OR CRSH_STATUS = 'CP')
                 ORDER BY CRSH_OPERATEDATE DESC";

    $stmt_hist = $conn->prepare($sql_hist);
    $stmt_hist->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_hist->execute();
    $history_list = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
}

function format_pcs($value) { return number_format((float)($value ?? 0), 0); }
function format_wt($value) { return number_format((float)($value ?? 0), 2); }
function format_dim($value) { return number_format((float)($value ?? 0), 3); }
function format_dim4($value) { return number_format((float)($value ?? 0), 4); }
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Cut to Length Production Entry</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        .clean-info-table {
            width: 100%; margin-bottom: 0; border: 1px solid #e2e8f0 !important;
        }
        .clean-info-table td, .clean-info-table th {
            padding: 8px 12px !important; vertical-align: middle !important;
            font-size: 13.5px; border: 1px solid #e2e8f0 !important;
        }
        .clean-info-table .lbl-col {
            background-color: #f8fafc; color: #64748b; font-weight: 700;
            font-size: 12px; text-transform: uppercase; width: 35%;
        }
        .clean-info-table .val-col { color: #0f172a; }

        .card-title-hist { color: #0284c7; border-bottom: 2px solid #bae6fd; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 15px; }
        .card-title-g4 { color: #d97706; border-bottom: 2px solid #fde68a; padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }

        .form-control-custom {
            width: 100%; border-radius: 8px; border: 1px solid #cbd5e1;
            padding: 8px 12px; font-size: 14px; font-weight: 600; color: #0f172a;
            transition: all 0.2s; height: 38px;
        }
        .form-control-custom:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); outline: none; }

        .custom-job-table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        .custom-job-table thead th {
            background-color: #f1f5f9; color: #475569; font-weight: 700;
            text-transform: uppercase; font-size: 12px; padding: 10px;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 8px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px; color: #334155; white-space: nowrap;
        }

        .action-button-group {
            display: flex; gap: 12px; justify-content: flex-end; align-items: center; margin-top: 20px; flex-wrap: wrap;
        }
        .btn-action {
            font-weight: 700; font-size: 14px; padding: 10px 22px; border-radius: 8px;
            border: none; transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-save { background-color: #059669; color: #ffffff; }
        .btn-save:hover { background-color: #047857; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2); }
        .btn-save:disabled { background-color: #cbd5e1 !important; color: #94a3b8 !important; cursor: not-allowed; box-shadow: none !important; transform: none !important; }
        
        .btn-keep { background-color: #d97706; color: #ffffff; }
        .btn-keep:hover { background-color: #b45309; transform: translateY(-1px); }

        .btn-print { background-color: #0284c7; color: #ffffff; }
        .btn-print:hover { background-color: #0369a1; transform: translateY(-1px); }
        .btn-print:disabled { background-color: #cbd5e1 !important; color: #94a3b8 !important; cursor: not-allowed; transform: none !important; }

        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; font-size: 15px; padding: 10px 20px; border-radius: 8px; border: none; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="cut_to_length_mats.php?func=<?php echo $folder_func ?>">Cut to Length Process (Line <?php echo $line_func; ?>)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    ⚙️ Cut to Length Entry: <span style="color:#2563eb;"><a href="cut_to_length_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $job_order ?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">⬅️ Back</button>
                </div>
            </div>

            <?php if (!$job_data || !$coil_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-danger" style="margin:0;">
                        ไม่พบข้อมูล Job Order หรือ Coil No. สำหรับทำรายการบันทึกผลผลิต
                    </div>
                </div>
            <?php else: ?>

                <!-- 1. JOB Information -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                        <h4 style="margin:0; font-weight:700; color:#1e3a8a; font-size:17px;">📌 1. Job Order Summary</h4>
                        <span class="badge" style="background-color:#059669; font-size:13px; font-weight:700;"><?php echo htmlspecialchars($job_data['JOB_STATUS']); ?></span>
                    </div>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Job / Sale Order</div>
                            <div style="font-weight:700; color:#2563eb; font-size:14px;"><?php echo htmlspecialchars($job_data['JOB_ORDER']); ?> (<?php echo htmlspecialchars($job_data['SALEORDER_NO'] . '/' . $job_data['SALEORDER_ITEM']); ?>)</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Alloy / Temper / Grade</div>
                            <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($job_data['ALLOY'] . ' / ' . $job_data['TEMPER'] . ' / ' . ($job_data['GRADE'] ?? 'NONE')); ?></div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Cut Size (W x L)</div>
                            <div style="font-weight:700; color:#0284c7; font-size:14px;"><?php echo format_wt($job_data['CUT_WIDTH']) . ' x ' . format_wt($job_data['CUT_LENGTH']); ?> mm.</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Release Qty (Wt / Pcs)</div>
                            <div style="font-weight:700; color:#059669; font-size:14px;"><?php echo format_wt($job_data['JOB_RELEASEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_RELEASEPIECE']); ?> pcs.</div>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #cbd5e1;">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Release Target Wt.</div>
                            <div style="font-weight:700; color:#0369a1; font-size:14px;">
                                <?php echo format_wt($rel_wt); ?> kg.
                            </div>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Max Allowed Wt. (+<?php echo $max_tol_pct; ?>%)</div>
                            <div style="font-weight:700; color:#d97706; font-size:14px;">
                                <?php echo format_wt($max_allow_wt); ?> kg.
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Produced Wt. Actual</div>
                            <div style="font-weight:700; color:#2563eb; font-size:14px;">
                                <?php echo format_wt($prod_wt); ?> kg.
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label" style="color:#059669;">Quota Remaining Wt.</div>
                            <div style="font-weight:700; color:#059669; font-size:15px; background-color:#ecfdf5; padding:2px 8px; border-radius:4px; display:inline-block;">
                                <= <?php echo format_wt($remain_allow_wt); ?> kg.
                            </div>
                        </div>
                    </div>

                </div>

                <!-- 2. Selected Coil Summary -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                        <h4 style="margin:0; font-weight:700; color:#0284c7; font-size:17px;">🌀 2. Working Coil Summary</h4>
                        <span class="badge" style="background-color:#d97706; font-size:13px; font-weight:700;">COIL: <?php echo htmlspecialchars($coil_data['COIL_NO']); ?></span>
                    </div>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Coil No. / Mat In</div>
                            <div style="font-weight:700; color:#0284c7; font-size:14px;"><?php echo htmlspecialchars($coil_data['COIL_NO']); ?> (<?php echo htmlspecialchars($coil_data['MATERIAL_IN']); ?>)</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Coil Dim (T x W)</div>
                            <div style="font-weight:600; font-size:14px;"><?php echo format_dim($coil_data['THICKNESS']) . ' x ' . format_wt($coil_data['WIDTH']); ?> mm.</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Actual Weight</div>
                            <div style="font-weight:600; font-size:14px;"><?php echo format_wt($coil_data['COIL_ACTUALWEIGHT']); ?> kg.</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Balance Weight</div>
                            <div style="font-weight:700; color:#059669; font-size:15px;" id="disp_coil_balance"><?php echo format_wt($coil_data['COIL_BALANCEWEIGHT']); ?> kg.</div>
                        </div>
                    </div>
                </div>

                <!-- 3. JOB Order Item List (Product Pallet History) -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 class="card-title-hist" style="margin:0; border:none; padding:0;">
                            📋 3. Job Order Pallet History (<?php echo htmlspecialchars($job_order); ?>)
                        </h4>
                        <span style="font-size: 13px; font-weight: 700; color: #64748b;">
                            Total Produced: <span style="color:#059669;" id="disp_job_prod_wt"><?php echo format_wt($job_data['JOB_PRODUCEWEIGHT']); ?> kg.</span> / <span style="color:#0284c7;" id="disp_job_prod_pc"><?php echo format_pcs($job_data['JOB_PRODUCEPIECE']); ?> pcs.</span>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table custom-job-table" id="tbl_pallet_history">
                            <thead>
                                <tr>
                                    <th style="text-align:center;">#</th>
                                    <th style="text-align:center;">Product No.</th>
                                    <th style="text-align:center;">Coil No.</th>
                                    <th style="text-align:right;">Produce Pcs</th>
                                    <th style="text-align:right;">Produce Wt. (kg)</th>
                                    <th style="text-align:center;">Start Time</th>
                                    <th style="text-align:center;">End Time</th>
                                    <th style="text-align:center;">Operator</th>
                                    <th style="text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history_list)): ?>
                                    <tr id="tr_no_history">
                                        <td colspan="9" align="center" style="color: #64748b; padding: 15px;">
                                            ยังไม่มีรายการพาเลทที่ลงบันทึกผลิตสำเร็จสำหรับ Job Order นี้
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $seq = 1; foreach ($history_list as $h): ?>
                                        <tr>
                                            <td align="center" style="font-weight:600; color:#64748b;"><?php echo $seq++; ?></td>
                                            <td align="center" style="font-weight:700; color:#0284c7;"><?php echo htmlspecialchars($h['PRODUCT_NO']); ?></td>
                                            <td align="center" style="font-weight:600;"><?php echo htmlspecialchars($h['COIL_NO']); ?></td>
                                            <td align="right" style="font-weight:700; color:#059669;"><?php echo format_pcs($h['CRSH_PRODUCEPIECE']); ?></td>
                                            <td align="right" style="font-weight:700; color:#2563eb;"><?php echo format_wt($h['CRSH_PRODUCEWEIGHT']); ?></td>
                                            <td align="center"><?php echo !empty($h['CRSH_STARTTIME']) ? date('H:i', strtotime($h['CRSH_STARTTIME'])) : '-'; ?></td>
                                            <td align="center"><?php echo !empty($h['CRSH_ENDTIME']) ? date('H:i', strtotime($h['CRSH_ENDTIME'])) : '-'; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($h['CRSH_OPERATOR1'] ?? '-'); ?></td>
                                            <td align="center">
                                                <button type="button" class="btn btn-xs btn-info" style="font-weight:700;" onclick="print_history_label('<?php echo htmlspecialchars($h['PRODUCT_NO']); ?>')">
                                                    🖨️ Print Label
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Product Pallet Input & Entry Form -->
                <div class="dashboard-card">
                    <h4 class="card-title-g4">📦 4. Product Pallet Entry (บันทึกผลผลิตชิ้นงาน)</h4>

                    <form id="frm_entry" onsubmit="save_entry_product(event)">
                        <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($job_order); ?>">
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>">
                        <input type="hidden" id="txt_last_product_no" value="" />
                        <!-- ซ่อนค่า weight per piece ที่ผ่านการเช็ก f4 WorkProcess แล้ว -->
                        <input type="hidden" id="calc_weight_piece" value="<?php echo $wt_piece_to_use; ?>">
                        <input type="hidden" id="coil_balance_wt" value="<?php echo $coil_data['COIL_BALANCEWEIGHT']; ?>">

                        <div class="row">
                            <!-- ฝั่งซ้าย: ข้อมูลสเปกพาเลทผลิต -->
                            <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                                <table class="table table-bordered table-sm clean-info-table">
                                    <tr>
                                        <td class="lbl-col">Product No. - Status</td>
                                        <td class="val-col" style="font-weight:700; color:#d97706;">
                                            <span id="lbl_product_no_disp">&lt;AUTO GENERATE ON SAVE&gt;</span>
                                            <span class="badge" style="background-color:#059669; float:right;">OP</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Line Process - Product</td>
                                        <td class="val-col" style="font-weight:600;">
                                            Line <?php echo $line_func; ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($job_data['PRODUCT_ID'] ?? 'SH'); ?> (SHEET)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Order Weight per Piece</td>
                                        <td class="val-col" style="font-weight:600;"><?php echo format_dim4($job_data['WEIGHT_PIECE']); ?> kg.</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Cut Sheet Wt. per Piece</td>
                                        <td class="val-col" style="font-weight:700; color:#0284c7;"><?php echo format_dim4($job_data['CUT_WEIGHTPIECE']); ?> kg.</td>
                                    </tr>
                                    <tr style="background-color: #f0fdf4;">
                                        <td class="lbl-col" style="color: #047857;">Calculation Weight Mode</td>
                                        <td class="val-col" style="font-weight:700; color: #047857;">
                                            <?php echo format_dim4($wt_piece_to_use); ?> kg./pc
                                            <small style="color: #64748b; font-weight: normal; display: block; font-size: 11px;">
                                                (<?php echo $wt_type_label; ?>)
                                            </small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Next Process</td>
                                        <td class="val-col" style="font-weight:700; color:#d97706;">SC</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Speed - Efficiency</td>
                                        <td class="val-col">
                                            Speed: <strong>0.000</strong> min/pc &nbsp;|&nbsp; Eff: <strong id="lbl_efficiency" style="color:#059669;">0</strong>%
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- ฝั่งขวา: ช่องป้อนข้อมูลชิ้นงาน และ เวลา -->
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-6 col-sm-6" style="margin-bottom:12px;">
                                        <label class="info-label">Product Pieces (จำนวนชิ้น pc.) *</label>
                                        <input type="number" class="form-control-custom" id="txt_produce_piece" name="produce_piece" required min="1" placeholder="0" oninput="calculate_entry_weight()" />
                                    </div>

                                    <div class="col-md-6 col-sm-6" style="margin-bottom:12px;">
                                        <label class="info-label">Calculated Weight (น้ำหนักรวม kg.)</label>
                                        <input type="text" class="form-control-custom" id="txt_produce_weight" name="produce_weight" readonly placeholder="0" style="background-color:#eff6ff; color:#2563eb; font-weight:700;" />
                                    </div>

                                    <div class="col-md-12 col-sm-12" style="margin-bottom:12px;">
                                        <label class="info-label">Pallet Weight (น้ำหนัก Pallet kg.) *</label>
                                        <input type="number" step="0.01" class="form-control-custom" id="txt_pallet_weight" name="pallet_weight" required placeholder="0.00" />
                                    </div>

                                    <div class="col-md-6 col-sm-6" style="margin-bottom:12px;">
                                        <label class="info-label">Start Date - Time *</label>
                                        <input type="datetime-local" class="form-control-custom" id="dt_start" name="start_datetime" required value="<?php echo date('Y-m-d\TH:i'); ?>" />
                                    </div>

                                    <div class="col-md-6 col-sm-6" style="margin-bottom:12px;">
                                        <label class="info-label">End Date - Time *</label>
                                        <input type="datetime-local" class="form-control-custom" id="dt_end" name="end_datetime" required value="<?php echo date('Y-m-d\TH:i'); ?>" onchange="calculate_efficiency()" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- กลุ่มปุ่ม Action ปุ่ม Keep Coil, Print Label, Save Product -->
                        <div class="action-button-group">
                            <button type="button" class="btn-action btn-keep" onclick="process_keep_coil()">
                                📦 Keep Coil
                            </button>
                            <button type="button" class="btn-action btn-print" id="btn_print_label" disabled onclick="print_product_label()">
                                🖨️ Print Label
                            </button>
                            <button type="submit" class="btn-action btn-save" id="btn_submit_entry" disabled>
                                💾 Save Product
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
// คำนวณน้ำหนักผลิตอัตโนมัติ พร้อมเช็ก Tolerance & Balance
function calculate_entry_weight() {
    var pcsInput = document.getElementById('txt_produce_piece');
    var pcs = parseFloat(pcsInput.value) || 0;
    
    var wtPerPiece = parseFloat(document.getElementById('calc_weight_piece').value) || 0;
    var coilBalanceWt = parseFloat(document.getElementById('coil_balance_wt').value) || 0;
    
    // ดึงค่ายอดปล่อยผลิต, ยอดผลิตสะสมเดิม, และ Max Tolerance %
    var releaseWt = parseFloat("<?php echo $job_data['JOB_RELEASEWEIGHT'] ?? 0; ?>") || 0;
    var currentProdWt = parseFloat("<?php echo $job_data['JOB_PRODUCEWEIGHT'] ?? 0; ?>") || 0;
    var maxTolPct = parseFloat("<?php echo $job_data['JOB_MAXTOLERANCE'] ?? 0; ?>") || 0;
    
    // คำนวณขีดจำกัดสูงสุดที่ยอมให้ผลิตได้ (Max Allowed Weight)
    var maxAllowedWt = releaseWt + ((releaseWt * maxTolPct) / 100);
    
    var btnSave = document.getElementById('btn_submit_entry');
    
    // คำนวณน้ำหนักรวมพาเลทใหม่
    var produceWt = Math.round(pcs * wtPerPiece);
    document.getElementById('txt_produce_weight').value = produceWt;

    if (pcs <= 0) {
        btnSave.disabled = true;
        return;
    }

    var totalNewProdWt = currentProdWt + produceWt;

    // 1. Hard Block: ถ้าน้ำหนักพาเลทใหม่ เกินยอดคงเหลือของ Coil (Coil Balance Weight)
    if (coilBalanceWt > 0 && produceWt > coilBalanceWt) {
        alert('❌ ไม่สามารถบันทึกได้: น้ำหนักผลิตรวม (' + produceWt + ' kg.) เกินกว่าน้ำหนักคงเหลือของ Coil (' + coilBalanceWt.toFixed(2) + ' kg.)');
        pcsInput.value = '';
        document.getElementById('txt_produce_weight').value = '0';
        btnSave.disabled = true;
        return false;
    } 
    // 2. Hard Block: ถ้ายอดสะสมรวมพาเลทใหม่ เกินเกณฑ์สูงสุด Max Tolerance (+%' . maxTolPct . ')
    else if (maxAllowedWt > 0 && totalNewProdWt > maxAllowedWt) {
        var overMax = (totalNewProdWt - maxAllowedWt).toFixed(2);
        alert('❌ ไม่สามารถบันทึกได้: ยอดผลิตสะสมรวมพาเลทนี้ (' + totalNewProdWt + ' kg.) เกินพิกัดสูงสุดที่อนุญาต (' + maxAllowedWt.toFixed(2) + ' kg. [+' + maxTolPct + '%]) อยู่ ' + overMax + ' kg.\n\nกรุณาติดต่อฝ่ายวางแผน (Planning) เพื่อปรับขยาย Tolerance ใน Job Order');
        pcsInput.value = '';
        document.getElementById('txt_produce_weight').value = '0';
        btnSave.disabled = true;
        return false;
    } 
    // 3. Soft Warning: เกินยอดสั่งปล่อย (Release Weight) แต่ยังอยู่ในช่วง Tolerance
    else if (releaseWt > 0 && totalNewProdWt > releaseWt) {
        var overRel = (totalNewProdWt - releaseWt).toFixed(2);
        console.warn('⚠️ Soft Warning: ยอดผลิตสะสมจะเกิน Release Target อยู่ ' + overRel + ' kg. (แต่อยู่ในเกณฑ์ Tolerance)');
        btnSave.disabled = false;
    } 
    else {
        btnSave.disabled = false;
    }

    calculate_efficiency();
}

// คำนวณ % ประสิทธิภาพการทำงาน
function calculate_efficiency() {
    var pcs = parseFloat(document.getElementById('txt_produce_piece').value) || 0;
    var dtStart = new Date(document.getElementById('dt_start').value);
    var dtEnd = new Date(document.getElementById('dt_end').value);

    if (pcs > 0 && dtEnd > dtStart) {
        var minutes = (dtEnd - dtStart) / (1000 * 60);
        if (minutes > 0) {
            var eff = Math.round((pcs / minutes) * 100);
            document.getElementById('lbl_efficiency').innerText = eff;
        }
    }
}

// ส่งข้อมูลบันทึกผลผลิต (Save Product) -> สร้าง Pallet ใหม่ลง DB
function save_entry_product(e) {
    e.preventDefault();

    // 1. Guard Check: ตรวจสอบวัน/เวลาจบ ต้องไม่น้อยกว่าวัน/เวลาเริ่ม
    var dtStart = new Date(document.getElementById('dt_start').value);
    var dtEnd   = new Date(document.getElementById('dt_end').value);

    if (dtEnd < dtStart) {
        alert('❌ ไม่สามารถบันทึกได้: วัน/เวลา สิ้นสุดการผลิต ต้องไม่อยู่ก่อนวัน/เวลา เริ่มต้น');
        document.getElementById('dt_end').focus();
        return false;
    }

    // 2. Guard Check: ดึงค่าตรวจสอบยอดผลิตเกิน Max Tolerance
    var pcs = parseFloat(document.getElementById('txt_produce_piece').value) || 0;
    var produceWt = parseFloat(document.getElementById('txt_produce_weight').value) || 0;
    var coilBalanceWt = parseFloat(document.getElementById('coil_balance_wt').value) || 0;
    
    var releaseWt = parseFloat("<?php echo $job_data['JOB_RELEASEWEIGHT'] ?? 0; ?>") || 0;
    var currentProdWt = parseFloat("<?php echo $job_data['JOB_PRODUCEWEIGHT'] ?? 0; ?>") || 0;
    var maxTolPct = parseFloat("<?php echo $job_data['JOB_MAXTOLERANCE'] ?? 0; ?>") || 0;
    var maxAllowedWt = releaseWt + ((releaseWt * maxTolPct) / 100);

    var totalNewProdWt = currentProdWt + produceWt;

    // Guard Check: ยอดชิ้นงาน/น้ำหนักต้องมากกว่า 0
    if (pcs <= 0 && produceWt <= 0) {
        alert('กรุณาระบุจำนวนชิ้นงานหรือน้ำหนักผลิตให้ถูกต้อง');
        return false;
    }

    // Guard Check: น้ำหนักผลิตต้องไม่เกิน Balance Weight ของ Coil
    if (coilBalanceWt > 0 && produceWt > coilBalanceWt) {
        alert('❌ ระบบไม่อนุญาตให้บันทึกเนื่องจากน้ำหนักเกิน Coil Balance Weight');
        return false;
    }

    // Guard Check: ยอดผลิตสะสมรวมต้องไม่เกิน Max Allowed Weight (Hard Block)
    if (maxAllowedWt > 0 && totalNewProdWt > maxAllowedWt) {
        var overMax = (totalNewProdWt - maxAllowedWt).toFixed(2);
        alert('❌ ระบบไม่อนุญาตให้บันทึกเนื่องจากยอดผลิตสะสมรวม (' + totalNewProdWt + ' kg.) เกินพิกัดสูงสุดที่อนุญาต (' + maxAllowedWt.toFixed(2) + ' kg. [+' + maxTolPct + '%]) อยู่ ' + overMax + ' kg.\n\nกรุณาติดต่อฝ่ายวางแผน (PPC)');
        return false;
    }

    // -----------------------------------------------------------------
    // เมื่อผ่านการตรวจ Guard Check ทั้งหมดแล้ว จึงทำการ Disable ปุ่มและส่งข้อมูล AJAX
    // -----------------------------------------------------------------
    var formElement = document.getElementById('frm_entry');
    var formData = new FormData(formElement);

    var btnSave = document.getElementById('btn_submit_entry');
    var btnKeep = document.querySelector('.btn-keep');
    btnSave.disabled = true;
    if (btnKeep) btnKeep.disabled = true;
    btnSave.innerHTML = '⏳ Saving Product...';

    // เลือกใช้ endpoint ตามไฟล์ 
    var targetUrl = 'model/cut_to_length_save_entry_product.php'; 

    fetch(targetUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if (data.auto_closed) {
                alert('✅ บันทึก Pallet ผลผลิตสำเร็จ (Product No: ' + data.pno + ')\n\n🔒 เนื่องจากน้ำหนักคงเหลือของ Coil น้อยกว่าเกณฑ์ (< 50 kg.) ระบบได้ทำการ Close Coil และ Auto Scrap เศษวัตถุดิบให้อัตโนมัติเรียบร้อยแล้ว');
                back_home();
            } else {
                alert('✅ บันทึก Pallet ผลผลิตสำเร็จ (Product No: ' + data.pno + ')');
                
                var jno = "<?php echo $job_order; ?>";
                var printUrl = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(data.pno) + '&joborder=' + encodeURIComponent(jno);
                window.open(printUrl, '_blank');

                window.location.reload();
            }
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            if (btnKeep) btnKeep.disabled = false;
            btnSave.innerHTML = '💾 Save Product';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        if (btnKeep) btnKeep.disabled = false;
        btnSave.innerHTML = '💾 Save Product';
    });
}

// จัดการการปลด/เปลี่ยน Coil (Keep Coil)
function process_keep_coil() {
    var cno = "<?php echo $coil_no; ?>";
    var jno = "<?php echo $job_order; ?>";
    var funcVal = document.getElementById('func').value;
    var coilBalanceWt = parseFloat(document.getElementById('coil_balance_wt').value) || 0;

    var confirmMsg = 'คุณต้องการทำรายการ Keep Coil (พักม้วน Coil ' + cno + ') เพื่อเปลี่ยนม้วนใช้งานใช่หรือไม่?';
    if (coilBalanceWt < 50) {
        confirmMsg = 'เตือน: น้ำหนักคงเหลือของ Coil (' + coilBalanceWt.toFixed(2) + ' kg.) น้อยกว่า 50 kg.\nระบบจะทำการปิดม้วนอัตโนมัติ (Close Coil & Auto Scrap) ยืนยันทำรายการหรือไม่?';
    }

    if (confirm(confirmMsg)) {
        fetch('model/cut_to_length_keep_coil.php?jno=' + encodeURIComponent(jno) + '&cno=' + encodeURIComponent(cno))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                back_home(); // เด้งกลับหน้าหลัก
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        });
    }
}

// ลิงก์กดพิมพ์ Label สติ๊กเกอร์
function print_product_label() {
    var pno = document.getElementById('txt_last_product_no').value;
    var jno = "<?php echo $job_order; ?>";
    
    if (!pno) {
        alert('ไม่พบรหัส Product No สำหรับพิมพ์สติ๊กเกอร์');
        return;
    }

    var url = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(pno) + '&joborder=' + encodeURIComponent(jno);
    window.open(url, '_blank');
}

// พิมพ์ Label ย้อนหลังสำหรับรายการในตารางประวัติ
function print_history_label(pno) {
    var jno = "<?php echo $job_order; ?>";
    var url = 'print_circle_sheet_product_label_mats.php?productno=' + encodeURIComponent(pno) + '&joborder=' + encodeURIComponent(jno);
    window.open(url, '_blank');
}

function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>