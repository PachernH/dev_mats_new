<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_dept = strtoupper(trim($department_func));
$is_authorized = ($current_dept === 'PP' || $current_dept === 'MK' || $current_dept === 'IT');

$job_no_param = isset($_GET['jobno']) ? htmlspecialchars(trim($_GET['jobno']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - View Job Order</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f1f5f9; color: #1e293b; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f1f5f9 !important; }
        .main-panel .content { padding: 14px 18px !important; }
        
        .group-card {
            background: #ffffff; border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 12px 16px; margin-bottom: 12px; border: 1px solid #cbd5e1;
        }
        .group-card-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1.5px solid #cbd5e1; padding-bottom: 6px; margin-bottom: 10px;
        }
        .group-card-title {
            font-size: 12.5px; font-weight: 700; color: #1e293b;
            text-transform: uppercase; letter-spacing: 0.5px; margin: 0;
        }

        .f-label {
            font-size: 10.5px; font-weight: 700; color: #64748b; margin-bottom: 2px;
            letter-spacing: 0.3px; text-transform: uppercase; display: block;
        }
        .f-view-box {
            background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;
            padding: 4px 8px; font-size: 12px; font-weight: 700; color: #0f172a;
            min-height: 28px; display: flex; align-items: center;
        }
        .f-view-box-accent {
            background-color: #eff6ff; border-color: #93c5fd; color: #1d4ed8; font-weight: 800;
        }

        .btn-act-edit {
            background-color: #2563eb; color: #fff; font-weight: 700; font-size: 12px;
            height: 32px; padding: 0 16px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-act-edit:hover { background-color: #1d4ed8; color: #fff; }

        .btn-act-back {
            background-color: #475569; color: #fff; font-weight: 600; font-size: 12px;
            height: 32px; padding: 0 14px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-act-back:hover { background-color: #334155; color: #fff; }

        .cmp-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .cmp-table th {
            background-color: #f8fafc; color: #475569; font-size: 10px; font-weight: 700;
            padding: 4px 6px; border: 1px solid #cbd5e1; text-transform: uppercase; text-align: center;
        }
        .cmp-table td { padding: 3px 6px; border: 1px solid #cbd5e1; font-size: 11.5px; font-weight: 600; }

        .remark-line {
            background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;
            padding: 4px 8px; font-family: monospace; font-size: 12px; font-weight: 700;
            min-height: 24px; margin-bottom: 4px; color: #1e293b;
        }

        @media print {
            .wrapper, .navbar, .no-print { display: none !important; }
            .main-panel { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .group-card { border: none !important; box-shadow: none !important; padding: 0 !important; margin-bottom: 8px; }
            body { background: #fff !important; color: #000 !important; }
        }
    </style>
</head>
<body>

<!-- Print Preview Modal ในหน้า job_order_view_mats.php และ job_order_mats.php -->
<div id="printModal" style="display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.7); backdrop-filter: blur(2px);">
    <div style="position: relative; background-color: #ffffff; margin: 1.5vh auto; width: 95%; height: 97vh; border-radius: 8px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); display: flex; flex-direction: column; overflow: hidden;">
        <!-- Header มีปุ่มชุดเดียว -->
        <div style="padding: 10px 18px; background-color: #1e293b; color: #ffffff; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155;">
            <div style="font-weight: 700; font-size: 14px; letter-spacing: 0.5px;">
                JOB ORDER REPORT PREVIEW (FM-07-009)
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="printModalIframe()" style="background-color: #059669; color: #ffffff; border: none; padding: 6px 18px; border-radius: 4px; font-weight: 700; font-size: 12px; cursor: pointer;">
                    PRINT
                </button>
                <button type="button" onclick="closePrintModal()" style="background-color: #dc2626; color: #ffffff; border: none; padding: 6px 14px; border-radius: 4px; font-weight: 700; font-size: 12px; cursor: pointer;">
                    ✕ CLOSE
                </button>
            </div>
        </div>
        <!-- Iframe Body -->
        <div style="flex: 1; width: 100%; height: 100%; background-color: #e2e8f0; padding: 6px;">
            <iframe id="reportIframe" src="" style="width: 100%; height: 100%; border: none; background-color: #ffffff; border-radius: 4px;"></iframe>
        </div>
    </div>
</div>
</div>

<div class="wrapper">
    <?php $menu = 'JOB'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed no-print">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">
                        Job Order Details & Workflow
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <!-- Top Action Bar -->
            <div class="group-card no-print" style="padding: 8px 14px; margin-bottom: 12px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <a href="job_order_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-act-back">
                             ⬅️ BACK TO LIST
                        </a>
                    </div>
                    <div style="display:flex; gap: 8px;">
                        <?php if ($is_authorized): ?>
                            <a href="job_order_mats.php?func=<?php echo $folder_func; ?>&jobno=<?php echo $job_no_param; ?>" class="btn-act-edit">
                                EDIT JOB ORDER
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn-act-back" style="background-color:#1e293b;" onclick="openPrintReport()">
                            PRINT FORM
                        </button>
                    </div>
                </div>
            </div>

            <!-- Two-Column Form Layout -->
            <div class="row">
                <!-- ================= LEFT COLUMN: JOB ORDER WORKFLOW ================= -->
                <div class="col-md-6" style="padding-right: 6px;">
                    <!-- Group 1: Job Identification & Dates -->
                    <div class="group-card">
                        <div class="group-card-header">
                            <h4 class="group-card-title">Job Order Identification</h4>
                            <div id="v_job_status" class="f-view-box f-view-box-accent" style="height:24px; padding:0 8px; font-size:11px;">OP</div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Job Order No</span>
                                <div id="v_job_order_no" class="f-view-box f-view-box-accent"><?php echo $job_no_param; ?></div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Priority</span>
                                <div id="v_priority" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Sale Order No</span>
                                <div id="v_sale_order_no" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Item</span>
                                <div id="v_sale_order_item" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Material In</span>
                                <div id="v_material_in" class="f-view-box">-</div>
                            </div>
                        </div>

                        <!-- แถววันที่ 4 คู่ ตรงตาม UI เดิม -->
                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">S.O. Date - P.O. Date</span>
                                <div style="display:flex; gap:4px;">
                                    <div id="v_so_date" class="f-view-box" style="flex:1;">-</div>
                                    <div id="v_po_date" class="f-view-box" style="flex:1;">-</div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Order Req. - Conf. Date</span>
                                <div style="display:flex; gap:4px;">
                                    <div id="v_req_date" class="f-view-box" style="flex:1;">-</div>
                                    <div id="v_conf_date" class="f-view-box" style="flex:1;">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-6">
                                <span class="f-label">Job Order - ETD Date</span>
                                <div style="display:flex; gap:4px;">
                                    <div id="v_job_order_date" class="f-view-box" style="flex:1;">-</div>
                                    <div id="v_schedule_date" class="f-view-box" style="flex:1;">-</div>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Schedule Start - Finish Date</span>
                                <div style="display:flex; gap:4px;">
                                    <div id="v_start_date" class="f-view-box" style="flex:1;">-</div>
                                    <div id="v_finish_date" class="f-view-box" style="flex:1;">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: Order Specification -->
                    <div class="group-card">
                        <div class="group-card-header">
                            <h4 class="group-card-title">Order Specification</h4>
                            <span id="v_other_width" style="font-size:11px; font-weight:700; color:#dc2626;">Other Coil Width: N</span>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Product ID</span>
                                <div id="v_product_id" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Alloy</span>
                                <div id="v_alloy" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-4">
                                <span class="f-label">Temper</span>
                                <div id="v_temper" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Produce Temper 1</span>
                                <div id="v_produce_temper1" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Produce Temper 2</span>
                                <div id="v_produce_temper2" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-4">
                                <span class="f-label">Grade</span>
                                <div id="v_grade" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Produce Grade 1</span>
                                <div id="v_produce_grade1" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Produce Grade 2</span>
                                <div id="v_produce_grade2" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Surface Grade (SG)</span>
                                <div id="v_surface_grade" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Metallurgical Grade (MG)</span>
                                <div id="v_metallurgical_grade" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-4">
                                <span class="f-label">Thickness (mm)</span>
                                <div id="v_thickness" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Width (mm)</span>
                                <div id="v_width" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Length (mm)</span>
                                <div id="v_length" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Work Process</span>
                                <div id="v_work_process" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Fixed Process</span>
                                <div id="v_fixed_process" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Coil Use For</span>
                                <div id="v_use_for_process" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xs-4">
                                <span class="f-label">T5 Temperature</span>
                                <div id="v_t5_temperature" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Flatness Grade</span>
                                <div id="v_flatness_grade" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-2">
                                <span class="f-label">Suggest W</span>
                                <div id="v_suggest_width" class="f-view-box">0</div>
                            </div>
                            <div class="col-xs-2">
                                <span class="f-label">Recovery</span>
                                <div id="v_recovery" class="f-view-box">0.00%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= RIGHT COLUMN: SALE ORDER REFERENCE & RELEASES ================= -->
                <div class="col-md-6" style="padding-left: 6px;">
                    <!-- Group 3: Sale Order Reference -->
                    <div class="group-card">
                        <div class="group-card-header">
                            <h4 class="group-card-title">Sale Order Reference (Original Order)</h4>
                            <span id="v_badge_so_status" class="f-view-box" style="height:22px; padding:0 8px; font-size:10.5px;">NO SO LOADED</span>
                        </div>

                        <div class="row" style="margin-bottom: 5px;">
                            <div class="col-xs-7">
                                <span class="f-label">P.O. No. - Item</span>
                                <div id="v_dsp_po_no" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-5">
                                <span class="f-label">Customer Part No</span>
                                <div id="v_dsp_cust_part" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 5px;">
                            <div class="col-xs-12">
                                <span class="f-label">Customer</span>
                                <div id="v_dsp_customer" class="f-view-box">-</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 5px;">
                            <div class="col-xs-6">
                                <span class="f-label">Category - Coil From</span>
                                <div id="v_dsp_category_coil" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-6">
                                <span class="f-label">Suggest Width - Recovery</span>
                                <div id="v_dsp_so_suggest_rec" class="f-view-box">0 / 0.00%</div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-4">
                                <span class="f-label">Alloy - Temper</span>
                                <div id="v_dsp_so_alloy_temper" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">SG - MG</span>
                                <div id="v_dsp_so_sg_mg" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <span class="f-label">Thickness - Width - Length</span>
                                <div id="v_dsp_so_dim" class="f-view-box">-</div>
                            </div>
                        </div>

                        <!-- SO Quantity Table -->
                        <table class="cmp-table">
                            <thead>
                                <tr>
                                    <th style="text-align:left;">Order Tolerance & Quantities</th>
                                    <th style="width:110px;">Weight (kg.)</th>
                                    <th style="width:90px;">Pieces (Pc.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="color:#64748b;">Max-Min Tolerance (%)</td>
                                    <td align="center"><span id="v_dsp_so_max_tol">0</span> %</td>
                                    <td align="center"><span id="v_dsp_so_min_tol">0</span> %</td>
                                </tr>
                                <tr>
                                    <td>Order Qty.</td>
                                    <td align="right"><span id="v_dsp_so_order_wt">0</span></td>
                                    <td align="right"><span id="v_dsp_so_order_pc">0</span></td>
                                </tr>
                                <tr>
                                    <td>Reject Qty.</td>
                                    <td align="right"><span id="v_dsp_so_reject_wt">0</span></td>
                                    <td align="right"><span id="v_dsp_so_reject_pc">0</span></td>
                                </tr>
                                <tr>
                                    <td>Replace Qty.</td>
                                    <td align="right"><span id="v_dsp_so_replace_wt">0</span></td>
                                    <td align="right"><span id="v_dsp_so_replace_pc">0</span></td>
                                </tr>
                                <tr style="background-color:#f8fafc; font-weight:700;">
                                    <td>Total Order Qty.</td>
                                    <td align="right" style="color:#0f172a;"><span id="v_dsp_so_total_wt">0</span></td>
                                    <td align="right" style="color:#0f172a;"><span id="v_dsp_so_total_pc">0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Group 4: Job Order Release & Cut Quantities -->
                    <div class="group-card">
                        <div class="group-card-header">
                            <h4 class="group-card-title">Job Order Release</h4>
                        </div>

                        <div class="row" style="margin-bottom: 6px;">
                            <div class="col-xs-6">
                                <span class="f-label">Order Weight per Piece (kg)</span>
                                <div id="v_weight_piece" class="f-view-box" style="color:#047857;">-</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Max Tol (%)</span>
                                <div id="v_job_max_tolerance" class="f-view-box">10%</div>
                            </div>
                            <div class="col-xs-3">
                                <span class="f-label">Min Tol (%)</span>
                                <div id="v_job_min_tolerance" class="f-view-box">10%</div>
                            </div>
                        </div>

                        <!-- Release Matrix Table -->
                        <table class="cmp-table" style="margin-bottom: 8px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;">Release Scale</th>
                                    <th style="width:110px;">Release (kg.)</th>
                                    <th style="width:90px;">Release (Pc.)</th>
                                    <th style="width:110px;">Sheet Wt. of Shear/Stretcher (kg.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="color:#64748b;">
                                    <td>Max Release Qty. (kg. - pc.)</td>
                                    <td align="right"><span id="v_lbl_max_release_wt">0</span></td>
                                    <td align="right"><span id="v_lbl_max_release_pc">0</span></td>
                                    <td align="right"><span id="v_lbl_max_sheet_wt">0</span></td>
                                </tr>
                                <tr style="background-color:#eff6ff; font-weight:700;">
                                    <td style="color:#1d4ed8;">Release Qty. (kg. - pc.)</td>
                                    <td align="right"><span id="v_lbl_target_release_wt">0</span></td>
                                    <td align="right"><span id="v_lbl_target_release_pc">0</span></td>
                                    <td align="right"><span id="v_lbl_target_sheet_wt">0</span></td>
                                </tr>
                                <tr style="color:#64748b;">
                                    <td>Min Release Qty. (kg. - pc.)</td>
                                    <td align="right"><span id="v_lbl_min_release_wt">0</span></td>
                                    <td align="right"><span id="v_lbl_min_release_pc">0</span></td>
                                    <td align="right"><span id="v_lbl_min_sheet_wt">0</span></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Sheet Size for Circle Shear & Stretcher -->
                        <span class="f-label" style="margin-top:6px;">Sheet Size for Circle Shear & Stretcher (W. - L. and Weight per Piece)</span>
                        <div class="row">
                            <div class="col-xs-4">
                                <div id="v_cut_width" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <div id="v_cut_length" class="f-view-box">-</div>
                            </div>
                            <div class="col-xs-4">
                                <div id="v_cut_weight_piece" class="f-view-box" style="color:#1d4ed8;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group 5: Bottom Details (Cold Mill Tolerance & 3-Line Remarks) -->
            <div class="group-card">
                <div class="row">
                    <div class="col-md-3">
                        <span class="f-label">Customer Thickness Tolerance for Cold Mill process</span>
                        <div style="display:flex; gap:6px; margin-top: 4px;">
                            <div id="v_cust_min_tol" class="f-view-box" style="flex:1;">0.000</div>
                            <div id="v_cust_max_tol" class="f-view-box" style="flex:1;">0.000</div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <span class="f-label">Remark</span>
                        <div id="v_remark_line1" class="remark-line"></div>
                        <div id="v_remark_line2" class="remark-line"></div>
                        <div id="v_remark_line3" class="remark-line"></div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const jobNo = "<?php echo $job_no_param; ?>";
    if (jobNo) {
        fetch(`model/get_jobdata_mats.php?action=get_job_order&job_no=${encodeURIComponent(jobNo)}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const d = res.data;
                    document.getElementById("v_job_status").innerText = d.JOB_STATUS || 'OP';
                    document.getElementById("v_priority").innerText = d.JOB_PRIORITY == 3 ? '3: URGENT' : (d.JOB_PRIORITY == 2 ? '2: FAST' : '1: NORMAL');
                    document.getElementById("v_sale_order_no").innerText = d.SALEORDER_NO || '-';
                    document.getElementById("v_sale_order_item").innerText = d.SALEORDER_ITEM || '-';
                    document.getElementById("v_material_in").innerText = d.MATERIAL_IN || '-';
                    
                    document.getElementById("v_job_order_date").innerText = d.JOBORDER_DATE ? d.JOBORDER_DATE.substring(0, 10) : '-';
                    document.getElementById("v_schedule_date").innerText = d.SCHEDULE_DATE ? d.SCHEDULE_DATE.substring(0, 10) : '-';
                    document.getElementById("v_start_date").innerText = d.EXPECT_STARTDATE ? d.EXPECT_STARTDATE.substring(0, 10) : '-';
                    document.getElementById("v_finish_date").innerText = d.EXPECT_FINISHDATE ? d.EXPECT_FINISHDATE.substring(0, 10) : '-';

                    // Product ID + Name
                    document.getElementById("v_product_id").innerText = d.PRODUCT_NAME ? `${d.PRODUCT_ID}: ${d.PRODUCT_NAME}` : d.PRODUCT_ID;
                    document.getElementById("v_alloy").innerText = d.ALLOY || '-';
                    document.getElementById("v_temper").innerText = d.TEMPER || '-';
                    document.getElementById("v_produce_temper1").innerText = d.PRODUCE_TEMPER1 || 'NONE';
                    document.getElementById("v_produce_temper2").innerText = d.PRODUCE_TEMPER2 || 'NONE';
                    document.getElementById("v_grade").innerText = d.GRADE || 'NONE';
                    document.getElementById("v_produce_grade1").innerText = d.PRODUCE_GRADE1 || 'NONE';
                    document.getElementById("v_produce_grade2").innerText = d.PRODUCE_GRADE2 || 'NONE';
                    document.getElementById("v_surface_grade").innerText = d.SURFACE_GRADE || '-';
                    document.getElementById("v_metallurgical_grade").innerText = d.METALLURGICAL_GRADE || '-';
                    document.getElementById("v_flatness_grade").innerText = d.FLATNESS_GRADE || '-';
                    document.getElementById("v_t5_temperature").innerText = d.T5_TEMPERATURE || '-';

                    document.getElementById("v_thickness").innerText = parseFloat(d.THICKNESS).toFixed(3);
                    document.getElementById("v_width").innerText = parseFloat(d.WIDTH).toFixed(3);
                    document.getElementById("v_length").innerText = parseFloat(d.LENGTH).toFixed(3);
                    document.getElementById("v_weight_piece").innerText = parseFloat(d.WEIGHT_PIECE).toFixed(4);

                    document.getElementById("v_work_process").innerText = d.JOB_WORKPROCESS || '-';
                    document.getElementById("v_fixed_process").innerText = d.FIXED_PROCESS || 'NO';
                    document.getElementById("v_use_for_process").innerText = d.USE_FORPROCESS || 'NONE';
                    document.getElementById("v_other_width").innerText = `Other Coil Width: ${d.OTHER_COILWIDTH || 'N'}`;
                    document.getElementById("v_suggest_width").innerText = Math.round(d.SUGGEST_WIDTH || 0);
                    document.getElementById("v_recovery").innerText = parseFloat(d.RECOVERY || 0).toFixed(2) + '%';

                    document.getElementById("v_cut_width").innerText = Math.round(d.CUT_WIDTH || 0);
                    document.getElementById("v_cut_length").innerText = Math.round(d.CUT_LENGTH || 0);
                    document.getElementById("v_cut_weight_piece").innerText = parseFloat(d.CUT_WEIGHTPIECE).toFixed(4);

                    // Cold Mill Tolerance
                    document.getElementById("v_cust_min_tol").innerText = parseFloat(d.CUST_MINTOLERANCE || 0).toFixed(3);
                    document.getElementById("v_cust_max_tol").innerText = parseFloat(d.CUST_MAXTOLERANCE || 0).toFixed(3);

                    // Split Remarks เป็น 3 บรรทัดตามความยาว 65 ตัวอักษรของระบบเดิม
                    const rawRemark = d.JOB_REMARK || '';
                    document.getElementById("v_remark_line1").innerText = rawRemark.substring(0, 65).trim();
                    document.getElementById("v_remark_line2").innerText = rawRemark.substring(65, 130).trim();
                    document.getElementById("v_remark_line3").innerText = rawRemark.substring(130, 195).trim();

                    // Matrix Calculations
                    const rw = Math.round(d.JOB_RELEASEWEIGHT || 0);
                    const rp = Math.round(d.JOB_RELEASEPIECE || 0);
                    const maxTol = parseFloat(d.JOB_MAXTOLERANCE || 10);
                    const minTol = parseFloat(d.JOB_MINTOLERANCE || 10);
                    const wp = parseFloat(d.WEIGHT_PIECE || 0);
                    const cwp = parseFloat(d.CUT_WEIGHTPIECE || 0);

                    document.getElementById("v_job_max_tolerance").innerText = maxTol + '%';
                    document.getElementById("v_job_min_tolerance").innerText = minTol + '%';
                    document.getElementById("v_lbl_target_release_wt").innerText = rw.toLocaleString();
                    document.getElementById("v_lbl_target_release_pc").innerText = rp.toLocaleString();
                    document.getElementById("v_lbl_target_sheet_wt").innerText = Math.round(rp * cwp).toLocaleString();

                    const maxW = Math.round(rw + (rw * maxTol / 100));
                    const minW = Math.round(rw - (rw * minTol / 100));
                    const maxP = wp > 0 ? Math.round(maxW / wp) : 0;
                    const minP = wp > 0 ? Math.round(minW / wp) : 0;

                    document.getElementById("v_lbl_max_release_wt").innerText = maxW.toLocaleString();
                    document.getElementById("v_lbl_min_release_wt").innerText = minW.toLocaleString();
                    document.getElementById("v_lbl_max_release_pc").innerText = maxP.toLocaleString();
                    document.getElementById("v_lbl_min_release_pc").innerText = minP.toLocaleString();
                    document.getElementById("v_lbl_max_sheet_wt").innerText = Math.round(maxP * cwp).toLocaleString();
                    document.getElementById("v_lbl_min_sheet_wt").innerText = Math.round(minP * cwp).toLocaleString();

                    // Sale Order Spec Reference
                    if (res.sale_order_spec) {
                        const so = res.sale_order_spec;
                        document.getElementById("v_badge_so_status").innerText = `SO STATUS: ${so.CTM2_STATUS || 'OP'}`;
                        document.getElementById("v_dsp_po_no").innerText = `${so.CTM2_PONO || '-'} / ${so.CTM2_POITEM || '-'}`;
                        document.getElementById("v_dsp_cust_part").innerText = so.CTM2_CUSTOMERPARTNO || '-';
                        document.getElementById("v_dsp_customer").innerText = so.CONSIGNEE_COMPANY ? `${so.CSTMSPPL_ID}: ${so.CONSIGNEE_COMPANY}` : so.CSTMSPPL_ID;
                        
                        document.getElementById("v_so_date").innerText = so.SALEORDER_DATE ? so.SALEORDER_DATE.substring(0, 10) : '-';
                        document.getElementById("v_po_date").innerText = so.CTM2_PODATE ? so.CTM2_PODATE.substring(0, 10) : '-';
                        document.getElementById("v_req_date").innerText = so.REQUEST_DATE ? so.REQUEST_DATE.substring(0, 10) : '-';
                        document.getElementById("v_conf_date").innerText = so.CONFIRM_DATE ? so.CONFIRM_DATE.substring(0, 10) : '-';

                        document.getElementById("v_dsp_category_coil").innerText = `${so.ORDER_CATEGORY || '-'} / ${so.COIL_FROM || '-'}`;
                        document.getElementById("v_dsp_so_suggest_rec").innerText = `${Math.round(so.SUGGEST_WIDTH || 0)} / ${parseFloat(so.RECOVERY || 0).toFixed(2)}%`;
                        document.getElementById("v_dsp_so_alloy_temper").innerText = `${so.ALLOY || '-'} / ${so.TEMPER || '-'}`;
                        document.getElementById("v_dsp_so_sg_mg").innerText = `${so.SURFACE_GRADE || '-'} / ${so.METALLURGICAL_GRADE || '-'}`;
                        document.getElementById("v_dsp_so_dim").innerText = `${parseFloat(so.THICKNESS).toFixed(3)} x ${parseFloat(so.WIDTH).toFixed(0)} x ${parseFloat(so.LENGTH).toFixed(0)}`;

                        document.getElementById("v_dsp_so_max_tol").innerText = so.CTM2_MAXTOLERANCE || '0';
                        document.getElementById("v_dsp_so_min_tol").innerText = so.CTM2_MINTOLERANCE || '0';
                        document.getElementById("v_dsp_so_order_wt").innerText = Number(so.CTM2_ORDERWEIGHT || 0).toLocaleString();
                        document.getElementById("v_dsp_so_order_pc").innerText = Number(so.CTM2_ORDERPIECE || 0).toLocaleString();
                        document.getElementById("v_dsp_so_reject_wt").innerText = Number(so.CTM2_REJECTWEIGHT || 0).toLocaleString();
                        document.getElementById("v_dsp_so_reject_pc").innerText = Number(so.CTM2_REJECTPIECE || 0).toLocaleString();
                        document.getElementById("v_dsp_so_replace_wt").innerText = Number(so.CTM2_REPLACEWEIGHT || 0).toLocaleString();
                        document.getElementById("v_dsp_so_replace_pc").innerText = Number(so.CTM2_REPLACEPIECE || 0).toLocaleString();

                        const totalWt = Number(so.CTM2_ORDERWEIGHT || 0) + Number(so.CTM2_REPLACEWEIGHT || 0);
                        const totalPc = Number(so.CTM2_ORDERPIECE || 0) + Number(so.CTM2_REPLACEPIECE || 0);
                        document.getElementById("v_dsp_so_total_wt").innerText = totalWt.toLocaleString();
                        document.getElementById("v_dsp_so_total_pc").innerText = totalPc.toLocaleString();
                    }
                } else {
                    alert(res.message);
                }
            })
            .catch(err => console.error("Error loading view data:", err));
    }
});

// ฟังก์ชันเปิด Modal Popup แสดงรายงาน
function openPrintReport() {
    const jobNo = document.getElementById("job_order_no") 
        ? document.getElementById("job_order_no").value.trim() 
        : "<?php echo $job_no_param ?? ''; ?>";

    if (!jobNo) {
        alert("Please select or save Job Order before printing.");
        return;
    }

    const modal = document.getElementById("printModal");
    const iframe = document.getElementById("reportIframe");

    // ใส่ URL ลง Iframe และสั่งเปิด Modal
    iframe.src = 'job_form_report_mats.php?jobno=' + encodeURIComponent(jobNo);
    modal.style.display = 'block';
}

// ฟังก์ชันปิด Popup
function closePrintModal() {
    const modal = document.getElementById("printModal");
    const iframe = document.getElementById("reportIframe");
    modal.style.display = 'none';
    iframe.src = ''; // เคลียร์ src เมื่อปิด
}

// ฟังก์ชันสั่งพิมพ์จากใน Popup Iframe
function printModalIframe() {
    const iframe = document.getElementById("reportIframe");
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
}

// ปิด Modal อัตโนมัติเมื่อกดแป้น Escape
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePrintModal();
    }
});
</script>
</html>