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
$is_old_record = !empty($job_no_param);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Job Order Entry</title>
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
        .f-input {
            border: 1px solid #cbd5e1; border-radius: 4px;
            background-color: #ffffff; padding: 4px 6px;
            height: 28px; font-size: 12px; font-weight: 600; color: #0f172a;
            width: 100%; transition: all 0.2s ease;
        }
        .f-input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        .f-input:disabled, .f-input[readonly] { 
            background-color: #f8fafc !important; border-color: #e2e8f0 !important; color: #64748b !important; cursor: not-allowed;
        }
        
        .f-display {
            background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;
            padding: 4px 6px; font-size: 11.5px; font-weight: 700; color: #1e293b;
            min-height: 28px; display: flex; align-items: center;
        }
        .f-display-success { background-color: #ecfdf5; border-color: #a7f3d0; color: #047857; }

        .btn-act-save {
            background-color: #059669; color: #fff; font-weight: 700; font-size: 12px;
            height: 32px; padding: 0 16px; border-radius: 6px; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-act-save:hover { background-color: #047857; }
        .btn-act-del {
            background-color: #dc2626; color: #fff; font-weight: 700; font-size: 12px;
            height: 32px; padding: 0 14px; border-radius: 6px; border: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-act-del:hover { background-color: #b91c1c; }
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

        .alert-bar {
            padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; margin-bottom: 10px; display: none;
        }
        .alert-bar-err { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .alert-bar-warn { background-color: #fffbeb; border: 1px solid #fef3c7; color: #b45309; }
        .alert-bar-info { background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

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

<div class="wrapper">
    <?php $menu = 'JOB'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed no-print">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="job_order_list_mats.php?func=<?php echo $folder_func ?>">
                        Job Order Entry & Work Process
                        <?php if (!$is_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div id="alertBox" class="alert-bar alert-bar-err"></div>
            <div id="warnBox" class="alert-bar alert-bar-warn"></div>
            <div id="mouldBox" class="alert-bar alert-bar-info"></div>

            <!-- Top Action Bar -->
            <div class="group-card no-print" style="padding: 8px 14px; margin-bottom: 12px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <a href="job_order_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-act-back">
                            BACK TO LIST
                        </a>
                    </div>
                    <div style="display:flex; gap: 8px;">
                        <?php if ($is_authorized): ?>
                            <button type="button" class="btn-act-save" onclick="saveJobOrder()">
                                SAVE JOB ORDER
                            </button>
                            <button type="button" id="btnDelete" class="btn-act-del" 
                                    style="<?php echo $is_old_record ? 'display:inline-flex;' : 'display:none;'; ?>" 
                                    onclick="deleteJobOrder()">
                                DELETE
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn-act-back" style="background-color:#1e293b;" onclick="openPrintReport()">
                            PRINT FORM
                        </button>
                        <button type="button" class="btn-act-back" style="background-color:#64748b;" onclick="resetForm()">
                            NEW
                        </button>
                    </div>
                </div>
            </div>

            <form id="frmJobOrder" onsubmit="return false;">
                <!-- Main 2-Column Split Layout -->
                <div class="row">
                    <!-- ================= LEFT COLUMN: JOB ORDER WORKFLOW ================= -->
                    <div class="col-md-6" style="padding-right: 6px;">
                        <!-- Group 1: Job Identification & Dates -->
                        <div class="group-card">
                            <div class="group-card-header">
                                <h4 class="group-card-title">Job Order Identification</h4>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <span class="f-label" style="margin:0;">Status:</span>
                                    <select id="job_status" class="f-input" style="width:70px; height:26px; font-weight:700; color:#1d4ed8;">
                                        <option value="OP">OP</option>
                                        <option value="HL">HL</option>
                                        <option value="CL">CL</option>
                                        <option value="CP">CP</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Job Order No</label>
                                    <input type="text" id="job_order_no" class="f-input" style="font-weight:700; color:#1d4ed8; font-size:13px;" 
                                           placeholder="Auto Generated" value="<?php echo $job_no_param; ?>" readonly />
                                </div>
                                <div class="col-xs-6">
                                    <label class="f-label">Priority</label>
                                    <select id="priority" class="f-input">
                                        <option value="1">1: NORMAL</option>
                                        <option value="2">2: FAST</option>
                                        <option value="3">3: URGENT</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Sale Order No</label>
                                    <input type="text" id="sale_order_no" class="f-input" placeholder="Enter SO" onchange="loadSaleOrderItem()" />
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">SO Item</label>
                                    <input type="text" id="sale_order_item" class="f-input" placeholder="001" onchange="loadSaleOrderItem()" />
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">Material In</label>
                                    <select id="material_in" class="f-input">
                                        <option value="">NONE</option>
                                        <option value="BOI-MAT1">BOI-MAT1</option>
                                        <option value="BOI-MAT2">BOI-MAT2</option>
                                        <option value="BOI-GRS1">BOI-GRS1</option>
                                        <option value="BOI-IMP1">BOI-IMP1</option>
                                        <option value="BOI-IMP2">BOI-IMP2</option>
                                        <option value="NONE-BOI">NONE-BOI</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">S.O. Date - P.O. Date</label>
                                    <div style="display:flex; gap:4px;">
                                        <div id="dsp_so_date" class="f-display" style="flex:1;">-</div>
                                        <div id="dsp_po_date" class="f-display" style="flex:1;">-</div>
                                    </div>
                                </div>
                                <div class="col-xs-6">
                                    <label class="f-label">Order Req. - Conf. Date</label>
                                    <div style="display:flex; gap:4px;">
                                        <div id="dsp_req_date" class="f-display" style="flex:1;">-</div>
                                        <div id="dsp_conf_date" class="f-display" style="flex:1;">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xs-6">
                                    <label class="f-label">Job Order - ETD Date</label>
                                    <div style="display:flex; gap:4px;">
                                        <input type="date" id="job_order_date" class="f-input" style="flex:1;" value="<?php echo date('Y-m-d'); ?>" />
                                        <input type="date" id="schedule_date" class="f-input" style="flex:1;" value="<?php echo date('Y-m-d'); ?>" />
                                    </div>
                                </div>
                                <div class="col-xs-6">
                                    <label class="f-label">Schedule Start - Finish Date</label>
                                    <div style="display:flex; gap:4px;">
                                        <input type="date" id="expect_start_date" class="f-input" style="flex:1;" />
                                        <input type="date" id="expect_finish_date" class="f-input" style="flex:1;" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Order Specification -->
                        <div class="group-card">
                            <div class="group-card-header">
                                <h4 class="group-card-title">Order Specification</h4>
                                <label style="font-size:11px; font-weight:700; color:#dc2626; cursor:pointer; margin:0;">
                                    <input type="checkbox" id="chkOtherWidth" value="1" /> Other Coil Width
                                </label>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Product ID</label>
                                    <select id="product_id" class="f-input" onchange="onDimensionChange()"></select>
                                </div>
                                <div class="col-xs-6">
                                    <label class="f-label">Alloy</label>
                                    <select id="alloy" class="f-input" onchange="onDimensionChange()"></select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-4">
                                    <label class="f-label">Temper</label>
                                    <select id="temper" class="f-input" onchange="onDimensionChange()"></select>
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Produce Temper 1</label>
                                    <select id="produce_temper1" class="f-input"></select>
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Produce Temper 2</label>
                                    <select id="produce_temper2" class="f-input"></select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-4">
                                    <label class="f-label">Grade</label>
                                    <select id="grade" class="f-input"></select>
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Produce Grade 1</label>
                                    <select id="produce_grade1" class="f-input"></select>
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Produce Grade 2</label>
                                    <select id="produce_grade2" class="f-input"></select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Surface Grade (SG)</label>
                                    <select id="surface_grade" class="f-input"></select>
                                </div>
                                <div class="col-xs-6">
                                    <label class="f-label">Metallurgical Grade (MG)</label>
                                    <select id="metallurgical_grade" class="f-input"></select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-4">
                                    <label class="f-label">Thickness (mm)</label>
                                    <input type="number" step="0.001" id="thickness" class="f-input" value="0.000" onchange="onDimensionChange()" />
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Width (mm)</label>
                                    <input type="number" step="0.001" id="width" class="f-input" value="0.000" onchange="onDimensionChange()" />
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Length (mm)</label>
                                    <input type="number" step="0.001" id="length" class="f-input" value="0.000" onchange="onDimensionChange()" />
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Work Process</label>
                                    <select id="work_process" class="f-input" onchange="onProcessChange()">
                                        <option value="">Select Work Process...</option>
                                    </select>
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">Fixed Process</label>
                                    <select id="fixed_process" class="f-input">
                                        <option value="NO">NO</option>
                                        <option value="BA">BA</option>
                                    </select>
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">Coil Use For</label>
                                    <select id="use_for_process" class="f-input">
                                        <option value="">NONE</option>
                                        <option value="XYB">XYB</option>
                                        <option value="XY5">XY5</option>
                                        <option value="CTL">CTL</option>
                                        <option value="AP1">AP1</option>
                                        <option value="KP1">KP1</option>
                                        <option value="PKC">PKC</option>
                                        <option value="STK">STK</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xs-4">
                                    <label class="f-label">T5 Temperature</label>
                                    <input type="text" id="t5_temperature" class="f-input" />
                                </div>
                                <div class="col-xs-4">
                                    <label class="f-label">Flatness Grade</label>
                                    <select id="flatness_grade" class="f-input"></select>
                                </div>
                                <div class="col-xs-2">
                                    <label class="f-label">Suggest Width</label>
                                    <input type="text" id="suggest_width" class="f-input" readonly value="0" />
                                </div>
                                <div class="col-xs-2">
                                    <label class="f-label">Recovery (%)</label>
                                    <input type="text" id="recovery" class="f-input" readonly value="0.00" />
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
                                <span id="badge_so_status" class="f-display" style="height:22px; padding:0 8px; font-size:10.5px;">NO SO LOADED</span>
                            </div>

                            <div class="row" style="margin-bottom: 5px;">
                                <div class="col-xs-7">
                                    <span class="f-label">P.O. No. - Item</span>
                                    <div id="dsp_po_no" class="f-display">-</div>
                                </div>
                                <div class="col-xs-5">
                                    <span class="f-label">Customer Part No</span>
                                    <div id="dsp_cust_part" class="f-display">-</div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 5px;">
                                <div class="col-xs-12">
                                    <span class="f-label">Customer</span>
                                    <div id="dsp_customer" class="f-display">-</div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 5px;">
                                <div class="col-xs-6">
                                    <span class="f-label">Order Category - Coil From</span>
                                    <div id="dsp_category_coil" class="f-display">-</div>
                                </div>
                                <div class="col-xs-6">
                                    <span class="f-label">Suggest Width - Recovery</span>
                                    <div id="dsp_so_suggest_rec" class="f-display">0 / 0.00%</div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-4">
                                    <span class="f-label">Alloy - Temper</span>
                                    <div id="dsp_so_alloy_temper" class="f-display">-</div>
                                </div>
                                <div class="col-xs-4">
                                    <span class="f-label">SG - MG</span>
                                    <div id="dsp_so_sg_mg" class="f-display">-</div>
                                </div>
                                <div class="col-xs-4">
                                    <span class="f-label">Thickness - Width - Length</span>
                                    <div id="dsp_so_dim" class="f-display">-</div>
                                </div>
                            </div>

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
                                        <td align="center"><span id="dsp_so_max_tol">0</span> %</td>
                                        <td align="center"><span id="dsp_so_min_tol">0</span> %</td>
                                    </tr>
                                    <tr>
                                        <td>Order Qty.</td>
                                        <td align="right"><span id="dsp_so_order_wt">0</span></td>
                                        <td align="right"><span id="dsp_so_order_pc">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Reject Qty.</td>
                                        <td align="right"><span id="dsp_so_reject_wt">0</span></td>
                                        <td align="right"><span id="dsp_so_reject_pc">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Replace Qty.</td>
                                        <td align="right"><span id="dsp_so_replace_wt">0</span></td>
                                        <td align="right"><span id="dsp_so_replace_pc">0</span></td>
                                    </tr>
                                    <tr style="background-color:#f8fafc; font-weight:700;">
                                        <td>Total Order Qty.</td>
                                        <td align="right" style="color:#0f172a;"><span id="dsp_so_total_wt">0</span></td>
                                        <td align="right" style="color:#0f172a;"><span id="dsp_so_total_pc">0</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Group 4: Job Order Release & Cut Calculation -->
                        <div class="group-card">
                            <div class="group-card-header">
                                <h4 class="group-card-title">Job Order Release</h4>
                            </div>

                            <div class="row" style="margin-bottom: 6px;">
                                <div class="col-xs-6">
                                    <label class="f-label">Order Weight per Piece (kg)</label>
                                    <input type="number" step="0.0001" id="weight_piece" class="f-input" style="font-weight:700; color:#047857;" readonly />
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">Max Tol (%)</label>
                                    <input type="number" id="job_max_tolerance" class="f-input" value="10" onchange="recalculateJobTolerances()" />
                                </div>
                                <div class="col-xs-3">
                                    <label class="f-label">Min Tol (%)</label>
                                    <input type="number" id="job_min_tolerance" class="f-input" value="10" onchange="recalculateJobTolerances()" />
                                </div>
                            </div>

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
                                        <td align="right"><span id="lbl_max_release_wt">0</span></td>
                                        <td align="right"><span id="lbl_max_release_pc">0</span></td>
                                        <td align="right"><span id="lbl_max_sheet_wt">0</span></td>
                                    </tr>
                                    <tr style="background-color:#eff6ff; font-weight:700;">
                                        <td style="color:#1d4ed8;">Release Qty. (kg. - pc.)</td>
                                        <td>
                                            <input type="number" step="1" id="release_weight" class="f-input" style="height:24px; font-weight:700; text-align:right;" 
                                                   value="0" onchange="onReleaseChange('W')" />
                                        </td>
                                        <td>
                                            <input type="number" step="1" id="release_piece" class="f-input" style="height:24px; font-weight:700; text-align:right;" 
                                                   value="0" onchange="onReleaseChange('P')" />
                                        </td>
                                        <td align="right" style="vertical-align:middle;"><span id="lbl_target_sheet_wt">0</span></td>
                                    </tr>
                                    <tr style="color:#64748b;">
                                        <td>Min Release Qty. (kg. - pc.)</td>
                                        <td align="right"><span id="lbl_min_release_wt">0</span></td>
                                        <td align="right"><span id="lbl_min_release_pc">0</span></td>
                                        <td align="right"><span id="lbl_min_sheet_wt">0</span></td>
                                    </tr>
                                </tbody>
                            </table>

                            <label class="f-label" style="margin-top:6px;">Sheet Size for Circle Shear & Stretcher (W. - L. and Weight per Piece)</label>
                            <div class="row">
                                <div class="col-xs-4">
                                    <input type="number" step="1" id="cut_width" class="f-input" value="0" onchange="recalculateCutWeight()" />
                                </div>
                                <div class="col-xs-4">
                                    <input type="number" step="1" id="cut_length" class="f-input" value="0" onchange="recalculateCutWeight()" />
                                </div>
                                <div class="col-xs-4">
                                    <input type="number" step="0.0001" id="cut_weight_piece" class="f-input" readonly value="0.0000" style="color:#1d4ed8;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Group 5: Bottom Details -->
                <div class="group-card">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="f-label">Customer Thickness Tolerance for Cold Mill process</label>
                            <div style="display:flex; gap:6px; margin-top: 4px;">
                                <input type="text" id="cust_min_tolerance" class="f-input" placeholder="-Min" value="0.000" readonly />
                                <input type="text" id="cust_max_tolerance" class="f-input" placeholder="+Max" value="0.000" readonly />
                            </div>
                        </div>
                        <div class="col-md-9">
                            <label class="f-label">Remark (Line 1 - 2 - 3 / 65 Chars Each)</label>
                            <input type="text" id="txtRemark1" maxlength="65" class="f-input" style="font-family:monospace; margin-bottom:4px;" placeholder="Remark Line 1" />
                            <input type="text" id="txtRemark2" maxlength="65" class="f-input" style="font-family:monospace; margin-bottom:4px;" placeholder="Remark Line 2" />
                            <input type="text" id="txtRemark3" maxlength="65" class="f-input" style="font-family:monospace;" placeholder="Remark Line 3" />
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <input type="hidden" id="customer_id" value="" />
        <input type="hidden" id="area_size" value="0" />
        <input type="hidden" id="product_model" value="" />

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>

let currentLoadedSO = null;
let isJobLoading = false;

document.addEventListener("DOMContentLoaded", async function() {
    await loadInitialMastersAsync();

    const jobParam = document.getElementById("job_order_no").value.trim();
    if (jobParam) {
        await loadJobOrderDataAsync(jobParam);
    } else {
        applyJ1FieldLocking(false, '', '', '', false);
    }
});

// โหลด Masters เข้า Dropdowns
function loadInitialMastersAsync() {
    return fetch('model/get_jobdata_mats.php?action=get_initial_masters')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                populateDropdown('product_id', res.data.products, 'PRODUCT_ID', 'PRODUCT_NAME');
                populateSimpleDropdown('alloy', res.data.alloys);
                populateSimpleDropdown('temper', res.data.tempers);
                populateSimpleDropdown('produce_temper1', res.data.tempers, true);
                populateSimpleDropdown('produce_temper2', res.data.tempers, true);
                populateSimpleDropdown('grade', res.data.grades, true);
                populateSimpleDropdown('produce_grade1', res.data.grades, true);
                populateSimpleDropdown('produce_grade2', res.data.grades, true);
                populateSimpleDropdown('surface_grade', res.data.surface_grades);
                populateSimpleDropdown('metallurgical_grade', res.data.metallurgical_grades);
                populateSimpleDropdown('flatness_grade', res.data.flatness_grades);
            }
        })
        .catch(err => console.error("Error loading masters:", err));
}

async function loadWorkProcessesAsync(productId, selectedVal = '') {
    const el = document.getElementById("work_process");
    if (!el) return;
    el.innerHTML = '<option value="">Loading...</option>';

    try {
        const res = await fetch(`model/get_jobdata_mats.php?action=get_work_processes&product_id=${encodeURIComponent(productId)}`);
        const json = await res.json();

        el.innerHTML = '<option value="">Select Work Process...</option>';
        if (json.status === 'success' && json.data.length > 0) {
            json.data.forEach(proc => {
                const opt = document.createElement('option');
                opt.value = proc.trim();
                opt.text = proc.trim();
                el.appendChild(opt);
            });
        }

        if (selectedVal) {
            setDropdownValue('work_process', selectedVal, selectedVal);
        } else if (el.options.length > 1) {
            el.selectedIndex = 1;
        }

        onProcessChange();
    } catch (err) {
        console.error("Error loading work processes:", err);
    }
}

function populateDropdown(elemId, data, valKey, textKey) {
    const el = document.getElementById(elemId);
    el.innerHTML = '<option value="">Select...</option>';
    data.forEach(item => {
        el.innerHTML += `<option value="${item[valKey]}">${item[textKey]}</option>`;
    });
}

function populateSimpleDropdown(elemId, arr, addNone = false) {
    const el = document.getElementById(elemId);
    el.innerHTML = '<option value="">Select...</option>';
    if (addNone) {
        el.innerHTML += '<option value="NONE">NONE</option>';
    }
    arr.forEach(val => {
        if (val && val !== 'NONE') el.innerHTML += `<option value="${val}">${val}</option>`;
    });
}

// ฟังก์ชันเซ็ตค่า Dropdown พร้อมสร้าง Option อัตโนมัติหากไม่พบ
function setDropdownValue(elemId, val, label) {
    if (val === null || val === undefined) return;
    const cleanVal = String(val).trim();
    if (!cleanVal) return;
    const select = document.getElementById(elemId);
    if (!select) return;

    let found = false;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value.trim().toUpperCase() === cleanVal.toUpperCase()) {
            select.selectedIndex = i;
            found = true;
            break;
        }
    }

    if (!found) {
        const opt = document.createElement('option');
        opt.value = cleanVal;
        opt.text = label ? label : cleanVal;
        opt.selected = true;
        select.appendChild(opt);
        select.value = cleanVal;
    }
}

// กฎการล็อกฟิลด์ตาม J1 EnableField & ControlField
function applyJ1FieldLocking(isOldRecord, soNo, soItem, productId, isSoLocked) {
    const hasSO = (soNo.length === 10 && soItem.length === 3);

    // 1. Sale Order Locking (หาก Pack หรือ Invoice แล้ว)
    if (isSoLocked) {
        document.getElementById("sale_order_no").disabled = true;
        document.getElementById("sale_order_item").disabled = true;
    } else {
        document.getElementById("sale_order_no").disabled = false;
        document.getElementById("sale_order_item").disabled = false;
    }

    // 2. ถ้ามี Sale Order สเปกหลักถูกล็อก
    if (hasSO) {
        document.getElementById("product_id").disabled = true;
        document.getElementById("surface_grade").disabled = true;
        document.getElementById("metallurgical_grade").disabled = true;
        document.getElementById("weight_piece").readOnly = true;

        // ล็อกสเปกยกเว้นกรณีค่าไม่ตรงกับ SO
        if (currentLoadedSO) {
            document.getElementById("alloy").disabled = (document.getElementById("alloy").value === currentLoadedSO.ALLOY);
            document.getElementById("temper").disabled = (document.getElementById("temper").value === currentLoadedSO.TEMPER);
            document.getElementById("thickness").readOnly = (parseFloat(document.getElementById("thickness").value) === parseFloat(currentLoadedSO.THICKNESS));
            document.getElementById("width").readOnly = (parseFloat(document.getElementById("width").value) === parseFloat(currentLoadedSO.WIDTH));
            document.getElementById("length").readOnly = (parseFloat(document.getElementById("length").value) === parseFloat(currentLoadedSO.LENGTH));
        } else {
            document.getElementById("alloy").disabled = true;
            document.getElementById("temper").disabled = true;
            document.getElementById("thickness").readOnly = true;
            document.getElementById("width").readOnly = true;
            document.getElementById("length").readOnly = true;
        }
    } else {
        // New Job ไม่มี SO เปิดให้กรอกอิสระ
        document.getElementById("product_id").disabled = false;
        document.getElementById("alloy").disabled = false;
        document.getElementById("temper").disabled = false;
        document.getElementById("surface_grade").disabled = false;
        document.getElementById("metallurgical_grade").disabled = false;
        document.getElementById("thickness").readOnly = false;
        document.getElementById("width").readOnly = false;
        document.getElementById("length").readOnly = false;
    }

    // 3. กฎตาม Product ID (CO vs CC/SH/NC)
    const pid = (productId || document.getElementById("product_id").value || '').substring(0, 2).toUpperCase();
    if (pid === 'CO') {
        document.getElementById("flatness_grade").disabled = true;
        document.getElementById("job_max_tolerance").disabled = true;
        document.getElementById("job_min_tolerance").disabled = true;
        document.getElementById("release_weight").readOnly = true;
        document.getElementById("release_piece").readOnly = false;
        document.getElementById("length").readOnly = true;
    } else if (pid === 'SH') {
        document.getElementById("flatness_grade").disabled = false;
        document.getElementById("job_max_tolerance").disabled = false;
        document.getElementById("job_min_tolerance").disabled = false;
        document.getElementById("release_weight").readOnly = false;
        document.getElementById("release_piece").readOnly = true;
    } else { // CC, NC
        document.getElementById("flatness_grade").disabled = true;
        document.getElementById("job_max_tolerance").disabled = false;
        document.getElementById("job_min_tolerance").disabled = false;
        document.getElementById("release_weight").readOnly = false;
        document.getElementById("release_piece").readOnly = true;
        document.getElementById("length").readOnly = true;
    }

    // 4. ฟิลด์ที่เปิดให้แก้ไขได้เสมอ (Always Editable)
    document.getElementById("job_status").disabled = false;
    document.getElementById("priority").disabled = false;
    document.getElementById("material_in").disabled = false;
    document.getElementById("job_order_date").readOnly = false;
    document.getElementById("schedule_date").readOnly = false;
    document.getElementById("expect_start_date").readOnly = false;
    document.getElementById("expect_finish_date").readOnly = false;
    document.getElementById("produce_temper1").disabled = false;
    document.getElementById("produce_temper2").disabled = false;
    document.getElementById("grade").disabled = false;
    document.getElementById("produce_grade1").disabled = false;
    document.getElementById("produce_grade2").disabled = false;
    document.getElementById("t5_temperature").readOnly = false;
    document.getElementById("work_process").readOnly = false;
    document.getElementById("fixed_process").disabled = false;
    document.getElementById("use_for_process").disabled = false;
    document.getElementById("chkOtherWidth").disabled = false;
    document.getElementById("txtRemark1").readOnly = false;
    document.getElementById("txtRemark2").readOnly = false;
    document.getElementById("txtRemark3").readOnly = false;
}

// ดึงข้อมูล Job เก่ามาแสดง (Async/Await)
async function loadJobOrderDataAsync(jobNo) {
    isJobLoading = true;
    hideAlerts();

    const btnDel = document.getElementById("btnDelete");
    if (btnDel) btnDel.style.display = 'inline-flex';

    try {
        const response = await fetch(`model/get_jobdata_mats.php?action=get_job_order&job_no=${encodeURIComponent(jobNo)}`);
        const res = await response.json();

        if (res.status === 'success') {
            const d = res.data;

            document.getElementById("job_order_no").value = d.JOB_ORDER || '';
            document.getElementById("sale_order_no").value = d.SALEORDER_NO || '';
            document.getElementById("sale_order_item").value = d.SALEORDER_ITEM || '';
            document.getElementById("material_in").value = (d.MATERIAL_IN || '').trim();
            document.getElementById("priority").value = d.JOB_PRIORITY || 1;
            document.getElementById("job_status").value = (d.JOB_STATUS || 'OP').trim();

            if (d.JOBORDER_DATE) document.getElementById("job_order_date").value = d.JOBORDER_DATE.substring(0, 10);
            if (d.SCHEDULE_DATE) document.getElementById("schedule_date").value = d.SCHEDULE_DATE.substring(0, 10);
            if (d.EXPECT_STARTDATE) document.getElementById("expect_start_date").value = d.EXPECT_STARTDATE.substring(0, 10);
            if (d.EXPECT_FINISHDATE) document.getElementById("expect_finish_date").value = d.EXPECT_FINISHDATE.substring(0, 10);

            const pidNames = { 'CC':'CC: CIRCLE', 'CO':'CO: COIL', 'SH':'SH: SHEET', 'NC':'NC: CIRCLE', 'WR':'WR: WIRE', 'RM':'RM: REMELT' };
            setDropdownValue('product_id', d.PRODUCT_ID, pidNames[d.PRODUCT_ID] || d.PRODUCT_ID);
            setDropdownValue('alloy', d.ALLOY);
            setDropdownValue('temper', d.TEMPER);
            setDropdownValue('produce_temper1', d.PRODUCE_TEMPER1 || 'NONE');
            setDropdownValue('produce_temper2', d.PRODUCE_TEMPER2 || 'NONE');
            setDropdownValue('grade', d.GRADE || 'NONE');
            setDropdownValue('produce_grade1', d.PRODUCE_GRADE1 || 'NONE');
            setDropdownValue('produce_grade2', d.PRODUCE_GRADE2 || 'NONE');
            setDropdownValue('surface_grade', d.SURFACE_GRADE);
            setDropdownValue('metallurgical_grade', d.METALLURGICAL_GRADE);
            setDropdownValue('flatness_grade', d.FLATNESS_GRADE);
            document.getElementById("t5_temperature").value = d.T5_TEMPERATURE || '';

            document.getElementById("thickness").value = parseFloat(d.THICKNESS || 0).toFixed(3);
            document.getElementById("width").value = parseFloat(d.WIDTH || 0).toFixed(3);
            document.getElementById("length").value = parseFloat(d.LENGTH || 0).toFixed(3);
            document.getElementById("weight_piece").value = parseFloat(d.WEIGHT_PIECE || 0).toFixed(4);

            // โหลดและเลือก Work Process Dropdown ตาม Product ID
            await loadWorkProcessesAsync(d.PRODUCT_ID || '', d.JOB_WORKPROCESS || '');

            document.getElementById("fixed_process").value = (d.FIXED_PROCESS || 'NO').trim();
            document.getElementById("use_for_process").value = (d.USE_FORPROCESS || '').trim();
            document.getElementById("suggest_width").value = Math.round(d.SUGGEST_WIDTH || 0);
            document.getElementById("recovery").value = parseFloat(d.RECOVERY || 0).toFixed(2);
            document.getElementById("chkOtherWidth").checked = (d.OTHER_COILWIDTH === 'Y');

            document.getElementById("cut_width").value = Math.round(d.CUT_WIDTH || 0);
            document.getElementById("cut_length").value = Math.round(d.CUT_LENGTH || 0);
            document.getElementById("cut_weight_piece").value = parseFloat(d.CUT_WEIGHTPIECE || 0).toFixed(4);

            document.getElementById("release_weight").value = Math.round(d.JOB_RELEASEWEIGHT || 0);
            document.getElementById("release_piece").value = Math.round(d.JOB_RELEASEPIECE || 0);
            document.getElementById("job_max_tolerance").value = d.JOB_MAXTOLERANCE || 10;
            document.getElementById("job_min_tolerance").value = d.JOB_MINTOLERANCE || 10;

            document.getElementById("cust_max_tolerance").value = parseFloat(d.CUST_MAXTOLERANCE || 0).toFixed(3);
            document.getElementById("cust_min_tolerance").value = parseFloat(d.CUST_MINTOLERANCE || 0).toFixed(3);

            const rawRemark = d.JOB_REMARK || '';
            document.getElementById("txtRemark1").value = rawRemark.substring(0, 65).trim();
            document.getElementById("txtRemark2").value = rawRemark.substring(65, 130).trim();
            document.getElementById("txtRemark3").value = rawRemark.substring(130, 195).trim();

            let isSoLocked = false;
            if (res.sale_order_spec) {
                currentLoadedSO = res.sale_order_spec;
                renderSaleOrderReference(res.sale_order_spec);
                isSoLocked = (parseFloat(res.sale_order_spec.CTM2_PACKWEIGHT) > 0 || parseFloat(res.sale_order_spec.CTM2_INVOICEWEIGHT) > 0);
                if (isSoLocked) {
                    showAlert("Modification disabled for Sale Order: Order has already progressed to Packing or Invoicing.", 'err');
                }
            }

            recalculateJobTolerances();
            applyJ1FieldLocking(true, d.SALEORDER_NO || '', d.SALEORDER_ITEM || '', d.PRODUCT_ID || '', isSoLocked);
        } else {
            showAlert(res.message, 'err');
        }
    } catch (err) {
        console.error(err);
        showAlert("Error loading Job Order data.", 'err');
    } finally {
        isJobLoading = false;
    }
}

function renderSaleOrderReference(d) {
    document.getElementById("badge_so_status").innerText = `SO STATUS: ${(d.CTM2_STATUS || 'OP').trim()}`;
    document.getElementById("badge_so_status").className = 'f-display f-display-success';
    document.getElementById("dsp_po_no").innerText = `${(d.CTM2_PONO || '-').trim()} / ${(d.CTM2_POITEM || '-').trim()}`;
    document.getElementById("dsp_cust_part").innerText = (d.CTM2_CUSTOMERPARTNO || '-').trim();
    document.getElementById("dsp_customer").innerText = d.CONSIGNEE_COMPANY ? `${(d.CSTMSPPL_ID || '').trim()}: ${d.CONSIGNEE_COMPANY.trim()}` : (d.CSTMSPPL_ID || '-').trim();
    document.getElementById("customer_id").value = (d.CSTMSPPL_ID || '').trim();
    
    document.getElementById("dsp_so_date").innerText = d.SALEORDER_DATE ? d.SALEORDER_DATE.substring(0, 10) : '-';
    document.getElementById("dsp_po_date").innerText = d.CTM2_PODATE ? d.CTM2_PODATE.substring(0, 10) : '-';
    document.getElementById("dsp_req_date").innerText = d.REQUEST_DATE ? d.REQUEST_DATE.substring(0, 10) : '-';
    document.getElementById("dsp_conf_date").innerText = d.CONFIRM_DATE ? d.CONFIRM_DATE.substring(0, 10) : '-';

    document.getElementById("dsp_category_coil").innerText = `${(d.ORDER_CATEGORY || '-').trim()} / ${(d.COIL_FROM || '-').trim()}`;
    document.getElementById("dsp_so_suggest_rec").innerText = `${Math.round(d.SUGGEST_WIDTH || 0)} / ${parseFloat(d.RECOVERY || 0).toFixed(2)}%`;
    document.getElementById("dsp_so_alloy_temper").innerText = `${(d.ALLOY || '').trim()} / ${(d.TEMPER || '').trim()}`;
    document.getElementById("dsp_so_sg_mg").innerText = `${(d.SURFACE_GRADE || '').trim()} / ${(d.METALLURGICAL_GRADE || '').trim()}`;
    document.getElementById("dsp_so_dim").innerText = `${parseFloat(d.THICKNESS).toFixed(3)} x ${parseFloat(d.WIDTH).toFixed(0)} x ${parseFloat(d.LENGTH).toFixed(0)}`;

    document.getElementById("dsp_so_max_tol").innerText = d.CTM2_MAXTOLERANCE || '0';
    document.getElementById("dsp_so_min_tol").innerText = d.CTM2_MINTOLERANCE || '0';
    document.getElementById("dsp_so_order_wt").innerText = Number(d.CTM2_ORDERWEIGHT || 0).toLocaleString();
    document.getElementById("dsp_so_order_pc").innerText = Number(d.CTM2_ORDERPIECE || 0).toLocaleString();
    document.getElementById("dsp_so_reject_wt").innerText = Number(d.CTM2_REJECTWEIGHT || 0).toLocaleString();
    document.getElementById("dsp_so_reject_pc").innerText = Number(d.CTM2_REJECTPIECE || 0).toLocaleString();
    document.getElementById("dsp_so_replace_wt").innerText = Number(d.CTM2_REPLACEWEIGHT || 0).toLocaleString();
    document.getElementById("dsp_so_replace_pc").innerText = Number(d.CTM2_REPLACEPIECE || 0).toLocaleString();
    
    const totalWt = Number(d.CTM2_ORDERWEIGHT || 0) + Number(d.CTM2_REPLACEWEIGHT || 0);
    const totalPc = Number(d.CTM2_ORDERPIECE || 0) + Number(d.CTM2_REPLACEPIECE || 0);
    document.getElementById("dsp_so_total_wt").innerText = totalWt.toLocaleString();
    document.getElementById("dsp_so_total_pc").innerText = totalPc.toLocaleString();
}

async function loadSaleOrderItem() {
    const soNo = document.getElementById("sale_order_no").value.trim();
    const soItem = document.getElementById("sale_order_item").value.trim();

    if (!soNo || !soItem) return;

    fetch(`model/get_jobdata_mats.php?action=get_sale_order_item&so_no=${encodeURIComponent(soNo)}&so_item=${encodeURIComponent(soItem)}`)
        .then(res => res.json())
        .then(async res => {
            if (res.status === 'success') {
                hideAlerts();
                currentLoadedSO = res.data;
                const d = res.data;

                renderSaleOrderReference(d);

                const pidNames = { 'CC':'CC: CIRCLE', 'CO':'CO: COIL', 'SH':'SH: SHEET', 'NC':'NC: CIRCLE', 'WR':'WR: WIRE', 'RM':'RM: REMELT' };
                setDropdownValue('product_id', d.PRODUCT_ID, pidNames[d.PRODUCT_ID] || d.PRODUCT_ID);
                setDropdownValue('alloy', d.ALLOY);
                setDropdownValue('temper', d.TEMPER);
                setDropdownValue('surface_grade', d.SURFACE_GRADE);
                setDropdownValue('metallurgical_grade', d.METALLURGICAL_GRADE);
                document.getElementById("thickness").value = parseFloat(d.THICKNESS).toFixed(3);
                document.getElementById("width").value = parseFloat(d.WIDTH).toFixed(3);
                document.getElementById("length").value = parseFloat(d.LENGTH).toFixed(3);
                document.getElementById("material_in").value = d.MATERIAL_IN || '';
                document.getElementById("release_weight").value = Math.round(d.CTM2_ORDERWEIGHT || 0);

                // อัปเดตตัวเลือก Work Process Dropdown ตาม Product ID ของ SO
                await loadWorkProcessesAsync(d.PRODUCT_ID || '', '');

                onDimensionChange();
                loadCustomerRemark();
                applyJ1FieldLocking(false, soNo, soItem, d.PRODUCT_ID, res.is_locked);

                if (res.is_locked) {
                    showAlert(res.lock_msg, 'err');
                }
            } else {
                showAlert(res.message, 'err');
            }
        });
}

function onDimensionChange() {
    if (isJobLoading) return;

    const prodId = document.getElementById("product_id").value;
    
    // โหลด Work Process Dropdown ให้ตรงกลุ่ม Product ID
    loadWorkProcessesAsync(prodId, document.getElementById("work_process").value);

    const w = parseFloat(document.getElementById("width").value) || 0;
    const l = parseFloat(document.getElementById("length").value) || 0;
    const th = parseFloat(document.getElementById("thickness").value) || 0;
    const areaSize = parseFloat(document.getElementById("area_size").value) || 0;

    let wp = 0;
    const density = 2.71;

    if (w > 0 && th > 0) {
        if (prodId === 'CC' || prodId === 'CR') {
            wp = (((22 / 7 * (w * w)) / 4) * th / 1000000) * density;
        } else if (prodId === 'SH' && l > 0) {
            wp = (w * l * th / 1000000) * density;
        } else if (prodId === 'NC' && areaSize > 0) {
            wp = (areaSize * th / 1000000) * density;
        }
    }
    document.getElementById("weight_piece").value = wp.toFixed(4);

    if (currentLoadedSO) {
        if (currentLoadedSO.ALLOY && document.getElementById("alloy").value !== currentLoadedSO.ALLOY) {
            showWarn("The Job Order alloy does not match the Sale Order alloy.");
        }
        if (currentLoadedSO.TEMPER && document.getElementById("temper").value !== currentLoadedSO.TEMPER) {
            showWarn("The Job Order temper does not match the Sale Order temper.");
        }
    }

    recalculateJobTolerances();
}

function onProcessChange() {
    if (isJobLoading) return;

    const proc = (document.getElementById("work_process").value || '').toUpperCase();
    const w = parseFloat(document.getElementById("width").value) || 0;
    const l = parseFloat(document.getElementById("length").value) || 0;

    let cw = 0, cl = 0;
    if (proc.startsWith("CL>")) {
        if (proc.includes("SH")) {
            cw = Math.min(w + 20, 1600);
            cl = Math.min(w + 20, 1600);
        } else if (proc.includes("SC")) {
            cw = Math.min(w + 3, 1600);
            cl = Math.min(l + 250, 4000);
        } else if (proc.includes("CT") || proc.includes("CL")) {
            cw = Math.min(w, 1600);
            cl = Math.min(l, 4000);
        }
    } else if (proc.startsWith("BK>") || proc.startsWith("AP>")) {
        if (proc.includes("SH")) {
            cw = 580; cl = 0;
        }
    } else if (proc.startsWith("KP>")) {
        if (proc.includes("SH")) {
            cw = 744; cl = 0;
        }
    }

    document.getElementById("cut_width").value = Math.round(cw);
    document.getElementById("cut_length").value = Math.round(cl);
    recalculateCutWeight();
}

function recalculateCutWeight() {
    const cw = parseFloat(document.getElementById("cut_width").value) || 0;
    const cl = parseFloat(document.getElementById("cut_length").value) || 0;
    const th = parseFloat(document.getElementById("thickness").value) || 0;

    let cwp = 0;
    if (cw > 0 && th > 0) {
        if (cl > 0) {
            cwp = (cw * cl * th / 1000000) * 2.71;
        } else {
            cwp = (((22 / 7 * (cw * cw)) / 4) * th / 1000000) * 2.71;
        }
    }
    document.getElementById("cut_weight_piece").value = cwp.toFixed(4);
    recalculateJobTolerances();
}

function recalculateJobTolerances() {
    const rw = parseFloat(document.getElementById("release_weight").value) || 0;
    const wp = parseFloat(document.getElementById("weight_piece").value) || 0;
    const cwp = parseFloat(document.getElementById("cut_weight_piece").value) || 0;
    const maxTol = parseFloat(document.getElementById("job_max_tolerance").value) || 0;
    const minTol = parseFloat(document.getElementById("job_min_tolerance").value) || 0;

    let rp = parseInt(document.getElementById("release_piece").value) || 0;
    if (wp > 0 && !isJobLoading) {
        rp = Math.round(rw / wp);
        document.getElementById("release_piece").value = rp;
    }

    const maxW = Math.round(rw + (rw * maxTol / 100));
    const minW = Math.round(rw - (rw * minTol / 100));
    const maxP = wp > 0 ? Math.round(maxW / wp) : 0;
    const minP = wp > 0 ? Math.round(minW / wp) : 0;

    document.getElementById("lbl_max_release_wt").innerText = maxW.toLocaleString();
    document.getElementById("lbl_min_release_wt").innerText = minW.toLocaleString();
    document.getElementById("lbl_max_release_pc").innerText = maxP.toLocaleString();
    document.getElementById("lbl_min_release_pc").innerText = minP.toLocaleString();

    document.getElementById("lbl_target_sheet_wt").innerText = Math.round(rp * cwp).toLocaleString();
    document.getElementById("lbl_max_sheet_wt").innerText = Math.round(maxP * cwp).toLocaleString();
    document.getElementById("lbl_min_sheet_wt").innerText = Math.round(minP * cwp).toLocaleString();
}

function onReleaseChange(type) {
    const wp = parseFloat(document.getElementById("weight_piece").value) || 0;
    if (wp <= 0) return;

    if (type === 'P') {
        const rp = parseFloat(document.getElementById("release_piece").value) || 0;
        document.getElementById("release_weight").value = Math.round(rp * wp);
    }
    recalculateJobTolerances();
}

function loadCustomerRemark() {
    const cust = document.getElementById("customer_id").value;
    const prod = document.getElementById("product_id").value;
    const al = document.getElementById("alloy").value;
    const tm = document.getElementById("temper").value;
    const th = document.getElementById("thickness").value;
    const w = document.getElementById("width").value;
    const l = document.getElementById("length").value;

    if (!cust || !prod) return;

    fetch(`model/get_jobdata_mats.php?action=get_customer_remark&customer_id=${cust}&product_id=${prod}&alloy=${al}&temper=${tm}&thickness=${th}&width=${w}&length=${l}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                const rmk = res.remark.split("\n");
                document.getElementById("txtRemark1").value = rmk[0] || '';
                document.getElementById("txtRemark2").value = rmk[1] || '';
                document.getElementById("txtRemark3").value = rmk[2] || '';
                if (res.fixed_process) {
                    document.getElementById("fixed_process").value = res.fixed_process;
                }
                if (res.pth && res.mth) {
                    document.getElementById("cust_max_tolerance").value = res.pth;
                    document.getElementById("cust_min_tolerance").value = res.mth;
                }
            }
        });
}

function saveJobOrder() {
    hideAlerts();

    const payload = {
        action: 'save',
        job_order_no: document.getElementById("job_order_no").value.trim(),
        sale_order_no: document.getElementById("sale_order_no").value.trim(),
        sale_order_item: document.getElementById("sale_order_item").value.trim(),
        material_in: document.getElementById("material_in").value,
        job_order_date: document.getElementById("job_order_date").value,
        schedule_date: document.getElementById("schedule_date").value,
        expect_start_date: document.getElementById("expect_start_date").value,
        expect_finish_date: document.getElementById("expect_finish_date").value,
        priority: document.getElementById("priority").value,
        job_status: document.getElementById("job_status").value,
        product_id: document.getElementById("product_id").value,
        alloy: document.getElementById("alloy").value,
        temper: document.getElementById("temper").value,
        produce_temper1: document.getElementById("produce_temper1").value,
        produce_temper2: document.getElementById("produce_temper2").value,
        grade: document.getElementById("grade").value,
        produce_grade1: document.getElementById("produce_grade1").value,
        produce_grade2: document.getElementById("produce_grade2").value,
        surface_grade: document.getElementById("surface_grade").value,
        metallurgical_grade: document.getElementById("metallurgical_grade").value,
        flatness_grade: document.getElementById("flatness_grade").value,
        t5_temperature: document.getElementById("t5_temperature").value,
        thickness: parseFloat(document.getElementById("thickness").value),
        width: parseFloat(document.getElementById("width").value),
        length: parseFloat(document.getElementById("length").value),
        work_process: document.getElementById("work_process").value,
        fixed_process: document.getElementById("fixed_process").value,
        use_for_process: document.getElementById("use_for_process").value,
        cut_width: parseFloat(document.getElementById("cut_width").value),
        cut_length: parseFloat(document.getElementById("cut_length").value),
        release_weight: parseFloat(document.getElementById("release_weight").value),
        release_piece: parseFloat(document.getElementById("release_piece").value),
        max_tolerance: parseInt(document.getElementById("job_max_tolerance").value),
        min_tolerance: parseInt(document.getElementById("job_min_tolerance").value),
        cust_max_tolerance: parseFloat(document.getElementById("cust_max_tolerance").value) || 0,
        cust_min_tolerance: parseFloat(document.getElementById("cust_min_tolerance").value) || 0,
        suggest_width: parseFloat(document.getElementById("suggest_width").value) || 0,
        recovery: parseFloat(document.getElementById("recovery").value) || 0,
        other_coil_width: document.getElementById("chkOtherWidth").checked,
        remark1: document.getElementById("txtRemark1").value,
        remark2: document.getElementById("txtRemark2").value,
        remark3: document.getElementById("txtRemark3").value
    };

    fetch('model/jobdata_process_mats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);
            window.location.assign('job_order_view_mats.php?func=' + encodeURIComponent(document.getElementById("func").value) + '&jobno=' + encodeURIComponent(res.job_order_no));
        } else {
            showAlert(res.message, 'err');
        }
    })
    .catch(err => {
        console.error(err);
        showAlert("System error during save operation.", 'err');
    });
}

function deleteJobOrder() {
    const jobNo = document.getElementById("job_order_no").value.trim();
    if (!jobNo) return;

    if (!confirm("Are you sure you want to delete this Job Order?")) return;

    fetch('model/jobdata_process_mats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', job_order_no: jobNo })
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);
            window.location.assign('job_order_list_mats.php?func=' + encodeURIComponent(document.getElementById("func").value));
        } else {
            showAlert(res.message, 'err');
        }
    });
}

// ฟังก์ชันดึง Work Process ตาม Product ID
async function loadWorkProcessesAsync(productId, selectedVal = '') {
    const el = document.getElementById("work_process");
    el.innerHTML = '<option value="">Loading...</option>';

    try {
        const res = await fetch(`model/get_jobdata_mats.php?action=get_work_processes&product_id=${encodeURIComponent(productId)}`);
        const json = await res.json();

        el.innerHTML = '<option value="">Select Work Process...</option>';
        if (json.status === 'success' && json.data.length > 0) {
            json.data.forEach(proc => {
                const opt = document.createElement('option');
                opt.value = proc.trim();
                opt.text = proc.trim();
                el.appendChild(opt);
            });
        }

        // เซ็ตค่าเดิม (ถ้ามี)
        if (selectedVal) {
            setDropdownValue('work_process', selectedVal, selectedVal);
        } else if (el.options.length > 1) {
            el.selectedIndex = 1; // เลือกตัวแรกถ้าไม่มีค่า
        }

        onProcessChange();
    } catch (err) {
        console.error("Error loading work processes:", err);
    }
}

function showAlert(msg, type) {
    const el = document.getElementById("alertBox");
    el.innerText = msg;
    el.style.display = 'block';
}

function showWarn(msg) {
    const el = document.getElementById("warnBox");
    el.innerText = msg;
    el.style.display = 'block';
}

function hideAlerts() {
    document.getElementById("alertBox").style.display = 'none';
    document.getElementById("warnBox").style.display = 'none';
}

function resetForm() {
    window.location.assign('job_order_mats.php?func=' + encodeURIComponent(document.getElementById("func").value));
}

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