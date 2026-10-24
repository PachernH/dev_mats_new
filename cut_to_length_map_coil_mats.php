<?php
// เริ่ม session
session_start();

// ดึงค่า Session
$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : 'A';
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
            alert('เตือน: Line การทำงานของคุณ ไม่ตรงกับกระบวนการ Cut to Length Process (Line A)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

// รับค่า JOB_ORDER
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data     = null;
$coil_list    = [];
$etd_date     = '-';
$max_wt       = 0;
$min_wt       = 0;
$max_pc       = 0;
$min_pc       = 0;
$max_sheet_wt = 0;
$rel_sheet_wt = 0;
$min_sheet_wt = 0;
$product_no   = '';

if (!empty($job_order)) {
    // 1. ดึงข้อมูล Job Order สเปก Cut to Length
    $sql_job = "SELECT 
                    j.JOB_ORDER, j.JOB_STATUS, j.SALEORDER_NO, j.SALEORDER_ITEM, 
                    c.CSTMSPPL_ID, c.CONFIRM_DATE, 
                    j.ALLOY, j.TEMPER, j.PRODUCE_TEMPER1, j.PRODUCE_TEMPER2, 
                    j.GRADE, j.PRODUCE_GRADE1, j.PRODUCE_GRADE2, 
                    j.SURFACE_GRADE, j.METALLURGICAL_GRADE, j.T5_TEMPERATURE, 
                    j.THICKNESS, j.WIDTH, j.LENGTH, j.WEIGHT_PIECE, 
                    j.CUT_WIDTH, j.CUT_LENGTH, j.CUT_WEIGHTPIECE, j.JOB_WORKPROCESS, 
                    j.JOB_RELEASEWEIGHT, j.JOB_RELEASEPIECE, j.JOB_PRODUCEWEIGHT, j.JOB_PRODUCEPIECE,
                    j.JOB_MAXTOLERANCE, j.JOB_MINTOLERANCE, j.MATERIAL_IN, j.JOB_REMARK, j.PRODUCT_ID
                FROM JOBORDER1 j
                LEFT JOIN CSTMORDR2 c ON j.SALEORDER_NO = c.SALEORDER_NO AND j.SALEORDER_ITEM = c.SALEORDER_ITEM
                WHERE j.JOB_ORDER = :job_order 
                  AND (j.PRODUCT_ID = 'CC' OR j.PRODUCT_ID = 'SH' OR j.PRODUCT_ID = 'NC') 
                  AND j.JOB_STATUS = 'OP'";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if ($job_data) {
        if (!empty($job_data['CONFIRM_DATE'])) {
            $etd_date = date('Y-m-d', strtotime($job_data['CONFIRM_DATE']));
        }

        $rel_wt    = (float)($job_data['JOB_RELEASEWEIGHT'] ?? 0);
        $rel_pc    = (float)($job_data['JOB_RELEASEPIECE'] ?? 0);
        $max_tol   = (float)($job_data['JOB_MAXTOLERANCE'] ?? 0);
        $min_tol   = (float)($job_data['JOB_MINTOLERANCE'] ?? 0);
        $wt_pc     = (float)($job_data['WEIGHT_PIECE'] ?? 0);
        $cut_wt_pc = (float)($job_data['CUT_WEIGHTPIECE'] ?? 0);

        $max_wt = round($rel_wt + (($rel_wt * $max_tol) / 100));
        $min_wt = round($rel_wt - (($rel_wt * $min_tol) / 100));

        $max_pc = ($wt_pc > 0) ? round($max_wt / $wt_pc) : 0;
        $min_pc = ($wt_pc > 0) ? round($min_wt / $wt_pc) : 0;

        $max_sheet_wt = round($max_pc * $cut_wt_pc);
        $rel_sheet_wt = round($rel_pc * $cut_wt_pc);
        $min_sheet_wt = round($min_pc * $cut_wt_pc);

        // 2. เจนเนอเรต Product No (รูปแบบ PYYMMDD-Line-XX) จาก CRSHPROD1
        $prefix = "P" . date('ymd') . "-" . strtoupper($line_func) . "-";
        $sql_run = "SELECT COUNT(*) as run_cnt FROM CRSHPROD1 WHERE PRODUCT_NO LIKE :prefix";
        $stmt_run = $conn->prepare($sql_run);
        $search_prefix = $prefix . "%";
        $stmt_run->bindParam(':prefix', $search_prefix, PDO::PARAM_STR);
        $stmt_run->execute();
        $row_run = $stmt_run->fetch(PDO::FETCH_ASSOC);
        $next_no = ((int)($row_run['run_cnt'] ?? 0)) + 1;
        $product_no = $prefix . sprintf("%02d", $next_no);

        // 3. ดึง List Coil จาก COILPROD1
        $p_temper1 = !empty($job_data['PRODUCE_TEMPER1']) ? $job_data['PRODUCE_TEMPER1'] : $job_data['TEMPER'];
        $p_temper2 = !empty($job_data['PRODUCE_TEMPER2']) ? $job_data['PRODUCE_TEMPER2'] : $job_data['TEMPER'];

        $sql_coil = "SELECT 
                        COIL_NO, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH,
                        COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT, COIL_ADJUSTWEIGHT, COIL_BALANCEWEIGHT,
                        LOCATION_ID, MATERIAL_IN, COIL_STATUS, T5_TEMPERATURE, RECIPE_NO, CURRENT_PASS
                     FROM COILPROD1
                     WHERE COIL_STATUS = 'AC' 
                       AND COIL_BALANCEWEIGHT > 50
                       AND ALLOY = :alloy
                       AND (TEMPER = :temper OR TEMPER = :ptemper1 OR TEMPER = :ptemper2)
                       AND SURFACE_GRADE = :sg
                       AND METALLURGICAL_GRADE = :mg
                       AND THICKNESS = :thickness
                     ORDER BY COIL_NO ASC";

        $stmt_coil = $conn->prepare($sql_coil);
        $stmt_coil->bindParam(':alloy', $job_data['ALLOY'], PDO::PARAM_STR);
        $stmt_coil->bindParam(':temper', $job_data['TEMPER'], PDO::PARAM_STR);
        $stmt_coil->bindParam(':ptemper1', $p_temper1, PDO::PARAM_STR);
        $stmt_coil->bindParam(':ptemper2', $p_temper2, PDO::PARAM_STR);
        $stmt_coil->bindParam(':sg', $job_data['SURFACE_GRADE'], PDO::PARAM_STR);
        $stmt_coil->bindParam(':mg', $job_data['METALLURGICAL_GRADE'], PDO::PARAM_STR);
        $stmt_coil->bindParam(':thickness', $job_data['THICKNESS']);
        $stmt_coil->execute();
        $coil_list = $stmt_coil->fetchAll(PDO::FETCH_ASSOC);
    }
}

function format_pcs($value) { return number_format((float)($value ?? 0), 0); }
function format_wt($value) { return number_format((float)($value ?? 0), 2); }
function format_dim($value) { return number_format((float)($value ?? 0), 3); }
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Map Coil Cut to Length (Line <?php echo $line_func; ?>)</title>
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
            font-size: 12px; text-transform: uppercase; width: 30%;
        }
        .clean-info-table .val-col { color: #0f172a; }

        .card-title-g2 { 
            color: #0284c7; border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; font-weight: 700; font-size: 18px; margin-top: 0; margin-bottom: 20px; 
        }

        .table-responsive { border: none !important; margin-top: 10px; }
        
        .custom-job-table {
            border-collapse: separate; border-spacing: 0; width: 100% !important;
        }
        .custom-job-table thead th {
            background-color: #f1f5f9; color: #475569; font-weight: 700;
            text-transform: uppercase; font-size: 13px; padding: 12px 10px;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 14px; color: #334155; white-space: nowrap;
        }
        
        .tr-selectable { cursor: pointer; transition: background-color 0.15s; }
        .tr-selectable:hover { background-color: #f0f9ff !important; }
        .tr-selected { background-color: #e0f2fe !important; border-left: 4px solid #0284c7; }

        .action-button-group {
            display: flex; gap: 12px; justify-content: flex-end; align-items: center; margin-top: 20px;
        }
        .btn-action {
            font-weight: 700; font-size: 15px; padding: 12px 32px; border-radius: 8px;
            border: none; transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-save { background-color: #059669; color: #ffffff; }
        .btn-save:hover { background-color: #047857; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2); }
        .btn-save:disabled { background-color: #cbd5e1 !important; color: #94a3b8 !important; cursor: not-allowed !important; transform: none !important; box-shadow: none !important; }

        .btn-back {
            background-color: #64748b; color: #ffffff; font-weight: 700;
            font-size: 15px; padding: 10px 20px; border-radius: 8px; border: none;
        }
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
                    🌀 Map Coil to Job Order: <span style="color:#2563eb;"><a href="cut_to_length_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $job_order ?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">⬅️ Back</button>
                </div>
            </div>

            <?php if (!$job_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-danger" style="margin:0;">
                        ไม่พบข้อมูล Job Order หรือ Job Order นี้ไม่อยู่ในสถานะพร้อมแปรรูป (OP): <strong><?php echo htmlspecialchars($job_order); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- 1. JOB Information -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                        <h4 style="margin:0; font-weight:700; color:#1e3a8a; font-size:18px;">
                            📌 Job Order Information
                        </h4>
                        <span class="badge" style="background-color:#059669; font-size:14px; padding:6px 14px; font-weight:700;">STATUS: <?php echo htmlspecialchars($job_data['JOB_STATUS']); ?></span>
                    </div>

                    <div class="row">
                        <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                            <h5 style="font-weight:700; color:#0369a1; margin-top:0; margin-bottom:15px; font-size:15px;">🔹 Specification & Order Info</h5>
                            <table class="table table-bordered table-sm clean-info-table">
                                <tr>
                                    <td class="lbl-col">Customer</td>
                                    <td class="val-col" style="font-weight:700; color:#1e293b;"><?php echo htmlspecialchars($job_data['CSTMSPPL_ID'] ?? '-'); ?></td>
                                    <td class="lbl-col">ETD Date</td>
                                    <td class="val-col"><?php echo $etd_date; ?></td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Material In</td>
                                    <td class="val-col"><?php echo htmlspecialchars($job_data['MATERIAL_IN'] ?? '-'); ?></td>
                                    <td class="lbl-col">Alloy</td>
                                    <td class="val-col" style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($job_data['ALLOY']); ?></td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Temper (Prod)</td>
                                    <td class="val-col"><?php echo htmlspecialchars($job_data['TEMPER']); ?> <small style="color:#64748b;">(<?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?>, <?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?>)</small></td>
                                    <td class="lbl-col">Grade (Prod)</td>
                                    <td class="val-col"><?php echo htmlspecialchars($job_data['GRADE'] ?? 'NONE'); ?> <small style="color:#64748b;">(<?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? 'NONE'); ?>)</small></td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Surface Grade</td>
                                    <td class="val-col"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></td>
                                    <td class="lbl-col">Metallurgical</td>
                                    <td class="val-col"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">T5 Temp.</td>
                                    <td class="val-col" colspan="3"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Raw Dim. (T x W x L)</td>
                                    <td class="val-col" colspan="3" style="font-weight:600;"><?php echo format_dim($job_data['THICKNESS']); ?> x <?php echo format_wt($job_data['WIDTH']); ?> x <?php echo format_wt($job_data['LENGTH']); ?> mm.</td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Cut Size (W x L)</td>
                                    <td class="val-col" colspan="3" style="font-weight:700; color:#0284c7;"><?php echo format_wt($job_data['CUT_WIDTH']); ?> x <?php echo format_wt($job_data['CUT_LENGTH']); ?> mm.</td>
                                </tr>
                                <tr>
                                    <td class="lbl-col">Work Process</td>
                                    <td class="val-col" colspan="3" style="font-weight:700; color:#d97706;">
                                        <?php echo htmlspecialchars($job_data['JOB_WORKPROCESS']); ?> 
                                        <span style="font-size:12px; font-weight:normal; color:#64748b; float:right;">Tolerance: +<?php echo format_pcs($job_data['JOB_MAXTOLERANCE']); ?>% / -<?php echo format_pcs($job_data['JOB_MINTOLERANCE']); ?>%</span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5 style="font-weight:700; color:#047857; margin-top:0; margin-bottom:15px; font-size:15px;">🎯 Production Targets & Status</h5>
                            <table class="table table-bordered table-sm clean-info-table">
                                <thead>
                                    <tr style="background-color:#f8fafc; font-size:12px; color:#64748b;">
                                        <th>Target Level</th>
                                        <th style="text-align:right;">Pieces (pcs)</th>
                                        <th style="text-align:right;">Weight (kg)</th>
                                        <th style="text-align:right;">Sheet Wt. (kg)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="font-weight:600; color:#64748b;">Maximum Target</td>
                                        <td align="right"><?php echo format_pcs($max_pc); ?></td>
                                        <td align="right"><?php echo format_pcs($max_wt); ?></td>
                                        <td align="right" style="color:#64748b;"><?php echo format_pcs($max_sheet_wt); ?></td>
                                    </tr>
                                    <tr style="background-color:#f0f9ff; font-weight:700; color:#0369a1;">
                                        <td>Release Target</td>
                                        <td align="right"><?php echo format_pcs($job_data['JOB_RELEASEPIECE']); ?></td>
                                        <td align="right"><?php echo format_pcs($job_data['JOB_RELEASEWEIGHT']); ?></td>
                                        <td align="right" style="color:#0284c7;"><?php echo format_pcs($rel_sheet_wt); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="font-weight:600; color:#64748b;">Minimum Target</td>
                                        <td align="right"><?php echo format_pcs($min_pc); ?></td>
                                        <td align="right"><?php echo format_pcs($min_wt); ?></td>
                                        <td align="right" style="color:#64748b;"><?php echo format_pcs($min_sheet_wt); ?></td>
                                    </tr>
                                    <tr style="background-color:#ecfdf5; font-weight:700; color:#047857;">
                                        <td>Produce Actual</td>
                                        <td align="right"><?php echo format_pcs($job_data['JOB_PRODUCEPIECE']); ?></td>
                                        <td align="right"><?php echo format_pcs($job_data['JOB_PRODUCEWEIGHT']); ?></td>
                                        <td align="right">-</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div style="margin-top: 15px;">
                                <label style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase;">Job Remark:</label>
                                <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px; font-size:13px; color:#334155; min-height:55px;">
                                    <?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. List Coil in Database -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">🌀 2. Select Coil for Production (Coil List Match Specification)</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table" id="tbl_coil_list">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">Action</th>
                                    <th>Coil No</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">Temper</th>
                                    <th style="text-align: center;">Surface Grade</th>
                                    <th style="text-align: center;">Metallurgical Grade</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: right;">Actual Weight</th>
                                    <th style="text-align: right;">Balance Weight</th>
                                    <th style="text-align: center;">Location</th>
                                    <th style="text-align: center;">Material In</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($coil_list)): ?>
                                    <tr>
                                        <td colspan="12" align="center" style="color: #dc2626; padding: 20px; font-weight: 600;">
                                            ❌ ไม่พบ Coil ที่มีสเปก (Alloy, Temper, Grade, Thickness) ตรงกับ Job Order นี้ในระบบ
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($coil_list as $coil): ?>
                                        <tr class="tr-selectable" onclick="select_coil(<?php echo htmlspecialchars(json_encode($coil)); ?>, this)">
                                            <td align="center">
                                                <button type="button" class="btn btn-xs btn-primary" style="font-weight:700;">เลือก Coil</button>
                                            </td>
                                            <td style="font-weight:700; color:#0284c7;"><?php echo htmlspecialchars($coil['COIL_NO']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['ALLOY']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['TEMPER']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['SURFACE_GRADE']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['METALLURGICAL_GRADE']); ?></td>
                                            <td align="center"><?php echo format_dim($coil['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($coil['WIDTH']); ?></td>
                                            <td align="right"><?php echo format_wt($coil['COIL_ACTUALWEIGHT']); ?></td>
                                            <td align="right" style="font-weight:700; color:#059669;"><?php echo format_wt($coil['COIL_BALANCEWEIGHT']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['LOCATION_ID'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($coil['MATERIAL_IN']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Coil Information & Save Production -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                        <h4 style="margin:0; font-weight:700; color:#059669; font-size:18px;">
                            📝 3. Selected Coil Information & Save Map
                        </h4>
                        <span style="font-weight:700; font-size:14px; color:#2563eb;">
                            New Product No: <span id="lbl_product_no_disp" style="color:#d97706;">AUTO GENERATE ON SAVE</span>
                        </span>
                    </div>
                    
                    <form id="frm_production" onsubmit="save_production(event)">
                        <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($job_order); ?>">
                        <input type="hidden" name="saleorder_no" value="<?php echo htmlspecialchars($job_data['SALEORDER_NO'] ?? ''); ?>">
                        <input type="hidden" name="saleorder_item" value="<?php echo htmlspecialchars($job_data['SALEORDER_ITEM'] ?? ''); ?>">
                        <input type="hidden" name="product_no" value="<?php echo $product_no; ?>">
                        <input type="hidden" id="txt_coil_no" name="coil_no" required />
                        <input type="hidden" id="txt_material_in" name="material_in" />

                        <div class="row">
                            <!-- ฝั่งซ้าย: ข้อมูลรายละเอียด Coil -->
                            <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                                <h5 style="font-weight:700; color:#0284c7; margin-top:0; margin-bottom:15px; font-size:15px;">🌀 Selected Coil Specifications</h5>
                                
                                <table class="table table-bordered table-sm clean-info-table">
                                    <tr>
                                        <td class="lbl-col">Coil No. / Status</td>
                                        <td class="val-col" style="font-weight:700; color:#0284c7;">
                                            <span id="lbl_coil_no">โปรดเลือก Coil จากลิสต์ด้านบน</span>
                                            <span class="badge" id="lbl_coil_status" style="background-color:#059669; float:right;">-</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Material In</td>
                                        <td class="val-col" id="lbl_material_in">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Alloy - Temper</td>
                                        <td class="val-col" id="lbl_alloy_temper" style="font-weight:600;">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Grade / SG / MG</td>
                                        <td class="val-col" id="lbl_grade_sg_mg">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Thickness x Width</td>
                                        <td class="val-col" id="lbl_thick_width" style="font-weight:600;">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">T5 Temperature</td>
                                        <td class="val-col" id="lbl_t5_temp">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Recipe / Pass No.</td>
                                        <td class="val-col" id="lbl_recipe_pass">-</td>
                                    </tr>
                                    <tr>
                                        <td class="lbl-col">Storage Location</td>
                                        <td class="val-col" id="lbl_location" style="font-weight:700; color:#1e293b;">-</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- ฝั่งขวา: สรุปน้ำหนัก Coil & ปุ่ม Save -->
                            <div class="col-md-6" style="display: flex; flex-direction: column; justify-content: space-between;">
                                <div>
                                    <h5 style="font-weight:700; color:#059669; margin-top:0; margin-bottom:15px; font-size:15px;">⚖️ Coil Weights Overview</h5>
                                    
                                    <table class="table table-bordered table-sm clean-info-table">
                                        <tr>
                                            <td class="lbl-col">Actual / Produce Wt.</td>
                                            <td class="val-col" id="lbl_act_prod_wt">-</td>
                                        </tr>
                                        <tr>
                                            <td class="lbl-col">Scrap / Adjust Wt.</td>
                                            <td class="val-col" id="lbl_scrap_adj_wt">-</td>
                                        </tr>
                                        <tr style="background-color:#ecfdf5;">
                                            <td class="lbl-col" style="color:#047857;">Balance Weight</td>
                                            <td class="val-col" id="lbl_balance_wt" style="color:#059669; font-weight:700; font-size:16px;">0.00 kg.</td>
                                        </tr>
                                    </table>
                                </div>

                                <div style="margin-top: 20px; text-align: right;">
                                    <!-- กำหนด disabled เป็นค่าเริ่มต้น -->
                                    <button type="submit" class="btn-action btn-save" id="btn_submit_save" disabled style="width: 100%; justify-content: center; height: 46px; font-size: 16px;">
                                        💾 Save Production
                                    </button>
                                </div>
                            </div>
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
// เลือก Coil -> อัปเดตข้อมูล UI และปลดล็อกปุ่ม Save (btn_submit_save)
function select_coil(coilObj, trElement) {
    document.getElementById('txt_coil_no').value    = coilObj.COIL_NO;
    document.getElementById('txt_material_in').value = coilObj.MATERIAL_IN || '';

    document.getElementById('lbl_coil_no').innerText       = coilObj.COIL_NO;
    document.getElementById('lbl_coil_status').innerText   = coilObj.COIL_STATUS || 'AC';
    document.getElementById('lbl_material_in').innerText   = coilObj.MATERIAL_IN || '-';
    document.getElementById('lbl_alloy_temper').innerText  = (coilObj.ALLOY || '-') + ' - ' + (coilObj.TEMPER || '-');
    document.getElementById('lbl_grade_sg_mg').innerText   = (coilObj.GRADE || '-') + ' / ' + (coilObj.SURFACE_GRADE || '-') + ' / ' + (coilObj.METALLURGICAL_GRADE || '-');
    document.getElementById('lbl_thick_width').innerText   = (parseFloat(coilObj.THICKNESS)||0).toFixed(3) + ' x ' + (parseFloat(coilObj.WIDTH)||0).toFixed(2) + ' mm.';
    document.getElementById('lbl_t5_temp').innerText       = coilObj.T5_TEMPERATURE || '-';
    document.getElementById('lbl_recipe_pass').innerText   = (coilObj.RECIPE_NO || '-') + ' (Pass: ' + (coilObj.CURRENT_PASS || '0') + ')';
    document.getElementById('lbl_location').innerText      = coilObj.LOCATION_ID || 'NONE';
    
    document.getElementById('lbl_act_prod_wt').innerText   = (parseFloat(coilObj.COIL_ACTUALWEIGHT)||0).toFixed(2) + ' / ' + (parseFloat(coilObj.COIL_PRODUCEWEIGHT)||0).toFixed(2) + ' kg.';
    document.getElementById('lbl_scrap_adj_wt').innerText  = (parseFloat(coilObj.COIL_SCRAPWEIGHT)||0).toFixed(2) + ' / ' + (parseFloat(coilObj.COIL_ADJUSTWEIGHT)||0).toFixed(2) + ' kg.';
    document.getElementById('lbl_balance_wt').innerText    = (parseFloat(coilObj.COIL_BALANCEWEIGHT)||0).toFixed(2) + ' kg.';

    // ปลดล็อกปุ่ม Save Production ให้ Active
    document.getElementById('btn_submit_save').disabled = false;

    var rows = document.querySelectorAll('#tbl_coil_list tbody tr');
    rows.forEach(function(row) { row.classList.remove('tr-selected'); });
    trElement.classList.add('tr-selected');
}

// ส่งข้อมูลด้วย AJAX
function save_production(e) {
    e.preventDefault();

    var formElement = document.getElementById('frm_production');
    var formData = new FormData(formElement);
    var funcVal = document.getElementById('func').value;
    var jobOrder = "<?php echo $job_order; ?>";

    var btnSave = document.getElementById('btn_submit_save');
    btnSave.disabled = true;
    btnSave.innerHTML = '⏳ Saving...';

    fetch('model/cut_to_length_save_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            var redirectUrl = 'cut_to_length_mats.php?func=' + encodeURIComponent(funcVal) + '&jno=' + encodeURIComponent(jobOrder);
            window.location.assign(redirectUrl);
        } else {
            alert('เกิดข้อผิดพลาด: ' + data.message);
            btnSave.disabled = false;
            btnSave.innerHTML = '💾 Save Production';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อระบบหลังบ้าน');
        btnSave.disabled = false;
        btnSave.innerHTML = '💾 Save Production';
    });
}

function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>