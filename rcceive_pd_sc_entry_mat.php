<?php
// Start session
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$current_dept = strtoupper(trim($department_func));
$type_param   = isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : '';
$init_inv     = isset($_GET['inv']) ? htmlspecialchars(trim($_GET['inv']), ENT_QUOTES, 'UTF-8') : '';

$is_wh = ($current_dept === 'WH');
$is_dr = in_array($current_dept, ['MK', 'PP']);
$is_it = ($current_dept === 'IT' || $current_func === 'IT');

if (!$is_wh && !$is_dr && !$is_it) {
    echo "<script>alert('ท่านไม่มีสิทธิ์ในการสร้างรายการรับสินค้า'); window.location.href='receive_pd_sc_process_mats.php?func={$folder_func}';</script>";
    exit();
}

include("dbcon_mats-new.php");
require_once("model/rc_receive_model.php");
$rcModel = new RcReceiveModel($conn);
$masters = $rcModel->rc_get_master_dropdowns();
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Entry Receive Product & Scrap</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 22px; margin-bottom: 20px; border: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 15px; font-weight: 700; color: #1e293b;
            border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 16px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .form-group label {
            font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px;
            text-transform: uppercase; letter-spacing: 0.3px; display: block;
        }
        /* รหัสสีอ้างอิง UI เดิม: ช่องบังคับกรอก (สีแดงอ่อน) และช่อง Read-only (สีฟ้าพาสเทล) */
        .input-required {
            border: 1.5px solid #f87171 !important;
            background-color: #fef2f2 !important;
            font-weight: 700; color: #1e293b;
        }
        .input-required:focus {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.15) !important;
        }
        .input-readonly-blue {
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1e3a8a !important;
            font-weight: 700;
            cursor: not-allowed;
        }
        .form-control {
            height: 36px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;
        }
        .btn-action {
            height: 38px; padding: 0 18px; border-radius: 8px; font-weight: 700; font-size: 13px;
            display: inline-flex; align-items: center; gap: 6px; border: none; transition: all 0.2s;
        }
        .btn-save { background-color: #059669; color: #fff; }
        .btn-save:hover { background-color: #047857; color: #fff; }
        .btn-back { background-color: #64748b; color: #fff; text-decoration: none; }
        .btn-back:hover { background-color: #475569; color: #fff; text-decoration: none; }


        /* แทนที่ด้วยโค้ดนี้ */
        .btn.btn-check {
            background-color: #2563eb !important;
            color: #ffffff !important;
            height: 36px;
            border-radius: 0 6px 6px 0;
            font-weight: 600;
            border: none !important;
            padding: 0 14px;
            box-shadow: none !important;
            opacity: 1 !important;
        }
        .btn.btn-check:hover,
        .btn.btn-check:focus,
        .btn.btn-check:active,
        .btn.btn-check:active:focus {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .btn.btn-check:disabled {
            background-color: #93c5fd !important;
            color: #ffffff !important;
            cursor: not-allowed;
        }

        .ref-box {
            background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 15px; margin-bottom: 16px;
        }
        .spec-box {
            background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 16px;
        }
        .item-list-table th {
            background-color: #f1f5f9; font-size: 11px; font-weight: 700; color: #475569; padding: 8px !important;
        }
        .item-list-table td {
            font-size: 12.5px; padding: 8px !important; vertical-align: middle;
        }
        .badge-op { background-color: #dbeafe; color: #1d4ed8; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .badge-ac { background-color: #dcfce7; color: #15803d; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 11px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A5'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        New Receive Entry (บันทึกรับสินค้า / เศษซาก)
                        <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">DEPT: <?php echo $current_dept; ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Navigation Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <a href="receive_pd_sc_process_mats.php?func=<?php echo $folder_func; ?>&type=<?php echo $type_param; ?>" class="btn-action btn-back">
                    ⬅️ Back to List
                </a>
                <div>
                    <span id="headingStatusBadge" class="badge" style="background:#f59e0b; font-size:12px; padding:6px 12px;">
                        STATUS: NEW HEADING (กรุณาบันทึกส่วนหัวก่อนเพิ่มรายการสินค้า)
                    </span>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- ส่วนที่ 1: HEADING (RECVPROD1) อ้างอิง Head.png                        -->
            <!-- ===================================================================== -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>1. ข้อมูลส่วนหัวเอกสาร (Receive Heading)</span>
                    <button type="button" class="btn btn-xs btn-default" onclick="$('#supplierContactBox').slideToggle()">
                        แสดง/ซ่อน ข้อมูลผู้ติดต่อ Supplier
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="color:#dc2626;">Invoice No. * </label>
                            <div class="input-group">
                                <input type="text" class="form-control input-required" id="invoice_no" value="<?php echo $init_inv; ?>"
                                       placeholder="ระบุเลขที่ Invoice..." oninput="this.value = this.value.toUpperCase()">
                                <span class="input-group-btn">
                                    <button class="btn btn-check" type="button" onclick="checkExistingInvoice()" title="ตรวจสอบ Invoice">
                                        ตรวจสอบ
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Receive Type (ประเภทการรับ)</label>
                            <select class="form-control" id="receive_type" onchange="onReceiveTypeChange()">
                                <?php if ($is_wh || $is_it): ?>
                                    <option value="RC">RC: RECEIVE PRODUCT</option>
                                    <option value="RS">RS: RECEIVE SCRAP</option>
                                    <option value="RT">RT: RETURN PRODUCT</option>
                                <?php endif; ?>
                                <?php if ($is_dr || $is_it): ?>
                                    <option value="DR">DR: DIRECT SHIPMENT</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Receive Product (ประเภทสินค้าที่รับ)</label>
                            <select class="form-control" id="product_id" onchange="onProductIDChange()"></select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>From Original Product (สินค้าต้นทาง)</label>
                            <select class="form-control" id="org_product_id" onchange="onOrgProductIDChange()"></select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Material In (สิทธิ์ BOI)</label>
                            <select class="form-control" id="material_in">
                                <option value="BOI-MAT1">BOI-MAT1</option>
                                <option value="BOI-MAT2">BOI-MAT2</option>
                                <option value="BOI-IMP1">BOI-IMP1</option>
                                <option value="BOI-IMP2">BOI-IMP2</option>
                                <option value="NONE-BOI">NONE-BOI</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Supplier or Customer</label>
                            <select class="form-control" id="supplier_id" onchange="onSupplierChange()">
                                <option value="">-- เลือก Supplier / Customer --</option>
                                <?php foreach ($masters['suppliers'] as $sup): ?>
                                    <option value="<?php echo htmlspecialchars(trim($sup['CSTMSPPL_ID'])); ?>">
                                        <?php echo htmlspecialchars(trim($sup['CSTMSPPL_ID']) . ' : ' . trim($sup['CONSIGNEE_COMPANY'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Country of Melt</label>
                            <input type="text" class="form-control input-readonly-blue" id="head_country_melt" readonly>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Receive Date</label>
                            <input type="date" class="form-control" id="receive_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <!-- ข้อมูลผู้ติดต่อ Supplier (พับเก็บได้) -->
                <div id="supplierContactBox" style="display:none; background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; margin-top:8px;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label>Address</label>
                                <input type="text" class="form-control input-readonly-blue" id="sup_address" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label>Contact / Position</label>
                                <input type="text" class="form-control input-readonly-blue" id="sup_contact" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group" style="margin-bottom:6px;">
                                <label>Telephone / Email</label>
                                <input type="text" class="form-control input-readonly-blue" id="sup_tel_email" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align:right; margin-top:12px;">
                    <button type="button" class="btn-action btn-save" id="btnSaveHeading" onclick="saveHeading()">
                        <i class="fa fa-save"></i> บันทึกส่วนหัวเอกสาร (Save Heading)
                    </button>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- ส่วนที่ 2: PRODUCT DETAIL & ITEM LIST (แสดงหลัง Save Heading สำเร็จ)    -->
            <!-- ===================================================================== -->
            <div id="detailWorkspace" style="display:none;">
                <div class="row">
                    <!-- คอลัมน์ซ้าย: ตารางรายการสินค้าที่รับเข้า Invoice นี้แล้ว (Product Item) -->
                    <div class="col-md-4">
                        <div class="dashboard-card">
                            <div class="section-title">
                                <span>รายการสินค้าใน Invoice (<span id="itemCountBadge">0</span>)</span>
                                <button type="button" class="btn btn-xs btn-primary" onclick="resetDetailForm()">+ รายการใหม่</button>
                            </div>
                            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                                <table class="table table-bordered table-hover item-list-table">
                                    <thead>
                                        <tr>
                                            <th>Product No</th>
                                            <th>Spec / Ref</th>
                                            <th class="text-right">Net Wt.</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="savedItemsTbody">
                                        <tr><td colspan="4" class="text-center text-muted">ยังไม่มีรายการสินค้า</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- คอลัมน์ขวา: ฟอร์มกรอกข้อมูลสินค้า (อ้างอิง Detail.png) -->
                    <div class="col-md-8">
                        <div class="dashboard-card">
                            <div class="section-title">
                                <span>2. บันทึกรายละเอียดสินค้า (Product Detail)</span>
                                <span id="detailModeLabel" class="badge" style="background:#2563eb;">NEW ITEM (Auto-Run Product No)</span>
                            </div>

                            <!-- กล่องที่ 1: Product No & Refer to Product -->
                            <div class="ref-box">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#dc2626;">Product No </label>
                                            <input type="text" class="form-control input-required" id="product_no" placeholder="Auto Generate" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label style="color:#dc2626;">Refer to Product *</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control input-required" id="reference_no" 
                                                       placeholder="ระบุเลขที่ Coil / Lot อ้างอิง..." oninput="this.value = this.value.toUpperCase()">
                                                <span class="input-group-btn">
                                                    <button class="btn btn-check" type="button" id="btnCheckRef" onclick="checkReferenceProduct()">ตรวจสอบ</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Ref. Status / Material In</label>
                                            <input type="text" class="form-control input-readonly-blue" id="ref_status_display" readonly>
                                            <input type="hidden" id="ref_status" value="">
                                            <input type="hidden" id="ref_coil_no" value="">
                                            <input type="hidden" id="ref_saleorder_no" value="">
                                            <input type="hidden" id="ref_saleorder_item" value="">
                                            <input type="hidden" id="ref_reject_weight_old" value="0">
                                            <input type="hidden" id="ref_reject_piece_old" value="0">
                                        </div>
                                    </div>
                                </div>

                                <!-- ข้อมูลอ้างอิงที่ดึงมาแสดง (ช่องสีน้ำเงิน Read-Only เหมือนใน Detail.png) -->
                                <div class="row" style="margin-top:6px;">
                                    <div class="col-md-3">
                                        <div class="form-group" style="margin-bottom:4px;">
                                            <label>Ref. Spec (Alloy / Temper)</label>
                                            <input type="text" class="form-control input-readonly-blue" id="ref_spec_display" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group" style="margin-bottom:4px;">
                                            <label>Ref. Size (T x W x L)</label>
                                            <input type="text" class="form-control input-readonly-blue" id="ref_size_display" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group" style="margin-bottom:4px;">
                                            <label>Product Weight</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="ref_actual_weight" value="0" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group" style="margin-bottom:4px;">
                                            <label>Usage Weight</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="ref_usage_weight" value="0" readonly>
                                            <input type="hidden" id="ref_usage_weight_old" value="0">
                                            <input type="hidden" id="ref_usage_weight1" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group" style="margin-bottom:4px;">
                                            <label>Balance Weight</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="ref_balance_weight" value="0" readonly>
                                            <input type="hidden" id="ref_balance_weight_old" value="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- กล่องที่ 2: Specification (อ้างอิงกล่อง Specification ด้านขวาบนใน Detail.png) -->
                            <div class="spec-box">
                                <div style="font-weight:700; font-size:12.5px; color:#b91c1c; margin-bottom:10px;">Specification (คุณสมบัติและขนาด)</div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Product Model</label>
                                            <select class="form-control" id="product_model" onchange="onProductModelChange()">
                                                <option value="">-- เลือก Model --</option>
                                                <?php foreach ($masters['models'] as $m): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($m['PRODUCT_MODEL'])); ?>" data-area="<?php echo (float)$m['AREA_SIZE']; ?>">
                                                        <?php echo htmlspecialchars(trim($m['PRODUCT_MODEL'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" id="area_size" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Coil Type</label>
                                            <select class="form-control" id="coil_type">
                                                <option value=""></option>
                                                <option value="DC">DC</option>
                                                <option value="CC">CC</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Alloy</label>
                                            <select class="form-control" id="alloy">
                                                <?php foreach ($masters['alloys'] as $a): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($a['ALLOY'])); ?>"><?php echo htmlspecialchars(trim($a['ALLOY'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Temper</label>
                                            <select class="form-control" id="temper">
                                                <?php foreach ($masters['tempers'] as $t): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($t['TEMPER'])); ?>"><?php echo htmlspecialchars(trim($t['TEMPER'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Grade</label>
                                            <select class="form-control" id="grade">
                                                <option value="NONE">NONE</option>
                                                <?php foreach ($masters['grades'] as $g): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($g['GRADE'])); ?>"><?php echo htmlspecialchars(trim($g['GRADE'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Surface (SG)</label>
                                            <select class="form-control" id="sg">
                                                <option value="SG3">SG3</option>
                                                <?php foreach ($masters['surface_grades'] as $s): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($s['SURFACE_GRADE'])); ?>"><?php echo htmlspecialchars(trim($s['SURFACE_GRADE'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Metal (MG)</label>
                                            <select class="form-control" id="mg">
                                                <option value="MG3">MG3</option>
                                                <?php foreach ($masters['metal_grades'] as $m): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($m['METALLURGICAL_GRADE'])); ?>"><?php echo htmlspecialchars(trim($m['METALLURGICAL_GRADE'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Thickness (mm)</label>
                                            <input type="number" step="0.001" class="form-control" id="thickness" list="list_thickness" value="0.000" onchange="calculateWeights()">
                                            <datalist id="list_thickness">
                                                <?php foreach ($masters['thickness'] as $th): ?>
                                                    <option value="<?php echo number_format((float)$th['THICKNESS'], 3, '.', ''); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Width (mm)</label>
                                            <input type="number" step="0.001" class="form-control" id="width" list="list_width" value="0.000" onchange="calculateWeights()">
                                            <datalist id="list_width">
                                                <?php foreach ($masters['width'] as $w): ?>
                                                    <option value="<?php echo number_format((float)$w['WIDTH'], 3, '.', ''); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Length (mm)</label>
                                            <input type="number" step="0.01" class="form-control" id="length" list="list_length" value="0.00" onchange="calculateWeights()">
                                            <datalist id="list_length">
                                                <?php foreach ($masters['length'] as $l): ?>
                                                    <option value="<?php echo number_format((float)$l['LENGTH'], 2, '.', ''); ?>">
                                                <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Weight / Piece</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="weight_piece" value="0.0000" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- กล่องที่ 3: Detail & Weights (อ้างอิงกล่อง Detail ด้านขวาล่างใน Detail.png) -->
                            <div class="spec-box">
                                <div style="font-weight:700; font-size:12.5px; color:#b91c1c; margin-bottom:10px;">Detail & Weight (น้ำหนักและรายละเอียดการจัดเก็บ)</div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color:#dc2626;">Gross Weight (kg) *</label>
                                            <input type="number" class="form-control input-required text-right" id="gross_weight" value="0" onchange="calculateWeights()">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color:#dc2626;">Net Weight (kg) *</label>
                                            <input type="number" class="form-control input-required text-right" id="net_weight" value="0" onchange="calculateWeights()">
                                            <input type="hidden" id="old_net_weight" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Net Piece (Pc.)</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="net_piece" value="0" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Package Weight (kg)</label>
                                            <input type="text" class="form-control input-readonly-blue text-right" id="package_weight" value="0" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>MAT PO No.</label>
                                            <input type="text" class="form-control" id="mat_po_no" oninput="this.value = this.value.toUpperCase()">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>PO Item</label>
                                            <input type="text" class="form-control" id="mat_po_item" maxlength="3">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>MP2 Item No.</label>
                                            <input type="text" class="form-control" id="mp2_partno" oninput="this.value = this.value.toUpperCase()">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Location (ตำแหน่งจัดเก็บ)</label>
                                            <select class="form-control" id="location_id">
                                                <option value="">-- เลือก Location --</option>
                                                <?php foreach ($masters['locations'] as $loc): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($loc['LOCATION_ID'])); ?>">
                                                        <?php echo htmlspecialchars(trim($loc['LOCATION_ID']) . ' : ' . trim($loc['DESCRIPTION'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Primary Smelt</label>
                                            <select class="form-control" id="primary_smelt">
                                                <option value=""></option>
                                                <?php foreach ($masters['smelts'] as $sm): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($sm['ALUMINIUM_SMELT'])); ?>"><?php echo htmlspecialchars(trim($sm['ALUMINIUM_SMELT'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Secondary Smelt</label>
                                            <select class="form-control" id="secondary_smelt">
                                                <option value=""></option>
                                                <?php foreach ($masters['smelts'] as $sm): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($sm['ALUMINIUM_SMELT'])); ?>"><?php echo htmlspecialchars(trim($sm['ALUMINIUM_SMELT'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Country of Melt</label>
                                            <select class="form-control" id="country_melt">
                                                <option value=""></option>
                                                <?php foreach ($masters['countries'] as $c): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($c['COUNTRY_MELT'])); ?>"><?php echo htmlspecialchars(trim($c['COUNTRY_MELT'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Country of Origin</label>
                                            <select class="form-control" id="country_origin">
                                                <option value=""></option>
                                                <?php foreach ($masters['countries'] as $c): ?>
                                                    <option value="<?php echo htmlspecialchars(trim($c['COUNTRY_MELT'])); ?>"><?php echo htmlspecialchars(trim($c['COUNTRY_MELT'])); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Dim. Width</label>
                                            <input type="number" class="form-control text-right" id="dim_width" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Dim. Length</label>
                                            <input type="number" class="form-control text-right" id="dim_length" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Dim. High</label>
                                            <input type="number" class="form-control text-right" id="dim_high" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Remark</label>
                                            <input type="text" class="form-control" id="remark" placeholder="หมายเหตุเพิ่มเติม...">
                                        </div>
                                    </div>
                                </div>

                                <!-- กล่อง Sale Order for Direct Shipment (แสดงเฉพาะเมื่อ Receive Type = DR) -->
                                <div id="directShipmentBox" style="display:none; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:12px; margin-top:8px;">
                                    <div style="font-weight:700; font-size:12px; color:#b45309; margin-bottom:8px;">Sale Order for Direct Shipment (งานส่งตรงลูกค้า)</div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label style="color:#dc2626;">Sale Order No *</label>
                                                <input type="text" class="form-control input-required" id="saleorder_no" oninput="this.value = this.value.toUpperCase()">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group" style="margin-bottom:0;">
                                                <label style="color:#dc2626;">SO Item *</label>
                                                <input type="text" class="form-control input-required" id="saleorder_item" maxlength="3">
                                            </div>
                                        </div>
                                        <div class="col-md-3" style="padding-top:21px;">
                                            <button type="button" class="btn btn-warning btn-sm" onclick="checkSaleOrderSpec()" style="height:36px; font-weight:700;">
                                                ตรวจสอบสเปก SO
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- แถบพิมพ์ป้ายและปุ่มบันทึก -->
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:14px; border-top:1px solid #e2e8f0;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <label style="margin:0; font-weight:700; cursor:pointer;">
                                            <input type="checkbox" id="chk_print_label" value="1"> Print Label
                                        </label>
                                        <select class="form-control" id="label_size" style="width:110px; height:34px;">
                                            <option value="29x21">29x21</option>
                                            <option value="10x15">10x15</option>
                                            <option value="21x15">21x15</option>
                                        </select>
                                    </div>
                                    <div style="display:flex; gap:10px;">
                                        <button type="button" class="btn btn-default" onclick="resetDetailForm()">ล้างฟอร์ม (Clear)</button>
                                        <button type="button" class="btn-action btn-save" id="btnSaveDetail" onclick="saveProductDetail()">
                                            <i class="fa fa-check-circle"></i> บันทึกรายการสินค้า (Save Item)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
const API_URL = 'model/rc_receive_model.php';

$(document).ready(function() {
    onReceiveTypeChange();

    // ดักจับปุ่ม Enter ในช่อง Invoice และ Reference No (รองรับเครื่องยิงบาร์โค้ด)
    $('#invoice_no').on('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); checkExistingInvoice(); }
    });
    $('#reference_no').on('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); checkReferenceProduct(); }
    });

    // หากมีพารามิเตอร์ inv ส่งมาให้โหลดข้อมูลอัตโนมัติ
    if ($('#invoice_no').val().trim() !== '') {
        checkExistingInvoice();
    }
});

// =========================================================================
// 1. เงื่อนไข ProductControl1 & ProductControl2 
// =========================================================================
function onReceiveTypeChange() {
    const rType = $('#receive_type').val();
    const prodSelect = $('#product_id');
    prodSelect.empty();

    if (rType === 'RS') {
        prodSelect.append('<option value="SC">SC: SCRAP</option>');
    } else {
        prodSelect.append('<option value="CC">CC: CIRCLE</option>');
        prodSelect.append('<option value="SH">SH: SHEET</option>');
        prodSelect.append('<option value="NC">NC: NON-CIRCULAR</option>');
        prodSelect.append('<option value="CO">CO: COIL</option>');
        if (rType === 'RT') {
            prodSelect.append('<option value="RM">RM: RAW MATERIAL</option>');
        }
    }
    onProductIDChange();
}

function onProductIDChange() {
    const rType = $('#receive_type').val();
    const pId   = $('#product_id').val();
    const orgSelect = $('#org_product_id');
    orgSelect.empty();

    if (rType === 'RC' || rType === 'DR') {
        orgSelect.append('<option value="CO">CO: COIL</option>');
        if (pId === 'CC') {
            orgSelect.append('<option value="SH">SH: SHEET</option>');
            orgSelect.append('<option value="CC">CC: CIRCLE</option>');
        } else if (pId === 'SH') {
            orgSelect.append('<option value="SH">SH: SHEET</option>');
        } else if (pId === 'NC') {
            orgSelect.append('<option value="SH">SH: SHEET</option>');
            orgSelect.append('<option value="CC">CC: CIRCLE</option>');
            orgSelect.append('<option value="NC">NC: NON-CIRCULAR</option>');
        }
    } else if (rType === 'RT') {
        const map = { 'CO': 'CO: COIL', 'CC': 'CC: CIRCLE', 'SH': 'SH: SHEET', 'NC': 'NC: NON-CIRCULAR', 'RM': 'RM: RAW MATERIAL' };
        if (map[pId]) orgSelect.append(`<option value="${pId}">${map[pId]}</option>`);
    } else if (rType === 'RS') {
        orgSelect.append('<option value="CO">CO: COIL</option>');
        orgSelect.append('<option value="SH">SH: SHEET</option>');
        orgSelect.append('<option value="CC">CC: CIRCLE</option>');
        orgSelect.append('<option value="NC">NC: NON-CIRCULAR</option>');
        orgSelect.append('<option value="SC">SC: SCRAP</option>');
        orgSelect.append('<option value="WR">WR: WRAP</option>');
    }
    applyFieldRules();
}

function onOrgProductIDChange() {
    applyFieldRules();
}

// เปิด/ปิดช่องกรอกตามเงื่อนไข EnableField ใน rc1
function applyFieldRules() {
    const rType = $('#receive_type').val();
    const pId   = $('#product_id').val();
    const orgId = $('#org_product_id').val();

    // Direct Shipment Box
    if (rType === 'DR') {
        $('#directShipmentBox').show();
        $('#location_id, #chk_print_label').prop('disabled', true);
    } else {
        $('#directShipmentBox').hide();
        $('#location_id, #chk_print_label').prop('disabled', false);
    }

    // Product Model (เฉพาะ NC)
    $('#product_model').prop('disabled', pId !== 'NC');
    if (pId !== 'NC') { $('#product_model').val(''); $('#area_size').val(0); }

    // Length (เฉพาะ SH และ NC)
    $('#length').prop('disabled', !(pId === 'SH' || pId === 'NC'));
    if (!(pId === 'SH' || pId === 'NC')) $('#length').val('0.00');

    // กรณี Scrap (SC)
    if (pId === 'SC') {
        $('#alloy').prop('disabled', orgId !== 'SC');
        $('#temper, #grade, #sg, #mg, #thickness, #width, #length, #location_id, #dim_width, #dim_length, #dim_high, #mp2_partno, #coil_type, #primary_smelt, #secondary_smelt, #country_melt, #country_origin').prop('disabled', true);
        $('#mat_po_no, #mat_po_item').prop('disabled', orgId !== 'WR');
        $('#reference_no, #btnCheckRef').prop('disabled', orgId === 'SC');
    } else {
        $('#alloy, #temper, #grade, #sg, #mg, #thickness, #width, #dim_width, #dim_length, #dim_high, #mat_po_no, #mat_po_item, #mp2_partno, #coil_type, #primary_smelt, #secondary_smelt, #country_melt, #country_origin').prop('disabled', false);
        $('#reference_no, #btnCheckRef').prop('disabled', false);
        if (rType === 'RT') {
            $('#alloy, #temper, #grade, #sg, #mg, #thickness, #width, #length, #product_model').prop('disabled', true);
        }
    }
}

// =========================================================================
// 2. จัดการส่วนหัวเอกสาร (Heading)
// =========================================================================
async function onSupplierChange() {
    const supId = $('#supplier_id').val();
    if (!supId) return;

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_get_supplier', supplier_id: supId })
    });
    const json = await res.json();
    if (json.status === 'success' && json.data) {
        const s = json.data;
        $('#head_country_melt').val(s.COUNTRY_MELT || '');
        $('#sup_address').val([s.CONSIGNEE_ADDRESS1, s.CONSIGNEE_ADDRESS2, s.CONSIGNEE_ADDRESS3].filter(Boolean).join(' '));
        $('#sup_contact').val([s.CONSIGNEE_CONTACT, s.CONSIGNEE_POSITION].filter(Boolean).join(' / '));
        $('#sup_tel_email').val([s.CONSIGNEE_TELEPHONE, s.CONSIGNEE_EMAIL].filter(Boolean).join(' | '));

        // นำค่าเริ่มต้นของ Supplier มาใส่ในส่วน Detail
        if (s.COIL_TYPE) $('#coil_type').val(s.COIL_TYPE.trim());
        if (s.PRIMARY_SMELT) $('#primary_smelt').val(s.PRIMARY_SMELT.trim());
        if (s.SECONDARY_SMELT) $('#secondary_smelt').val(s.SECONDARY_SMELT.trim());
        if (s.COUNTRY_MELT) {
            $('#country_melt').val(s.COUNTRY_MELT.trim());
            $('#country_origin').val(s.COUNTRY_MELT.trim());
        }
    }
}

async function checkExistingInvoice() {
    const inv = $('#invoice_no').val().trim();
    if (!inv) return;

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_get_detail', invoice_no: inv })
    });
    const json = await res.json();

    if (json.status === 'error') {
        alert(json.message);
        $('#invoice_no').val('').focus();
        return;
    }

    if (json.data && json.data.header) {
        const h = json.data.header;
        $('#receive_type').val(h.RECEIVE_TYPE).prop('disabled', true);
        onReceiveTypeChange();
        $('#product_id').val(h.PRODUCT_ID).prop('disabled', true);
        onProductIDChange();
        $('#org_product_id').val(h.ORGPRODUCT_ID).prop('disabled', true);
        $('#material_in').val(h.MATERIAL_IN);
        $('#supplier_id').val(h.CSTMSPPL_ID);
        if (h.RECEIVE_DATE) $('#receive_date').val(h.RECEIVE_DATE.substring(0, 10));
        onSupplierChange();

        $('#invoice_no').prop('readonly', true);
        $('#headingStatusBadge').css('background', '#059669').text('OLD RECORD: ' + inv);
        $('#detailWorkspace').slideDown();
        renderSavedItems(json.data.items || []);
    } else {
        $('#headingStatusBadge').css('background', '#2563eb').text('NEW INVOICE: ' + inv);
    }
}

async function saveHeading() {
    const inv = $('#invoice_no').val().trim();
    const sup = $('#supplier_id').val();
    if (!inv) { alert('กรุณาระบุ Invoice No.'); $('#invoice_no').focus(); return; }
    if (!sup) { alert('กรุณาเลือก Supplier หรือ Customer'); $('#supplier_id').focus(); return; }

    const payload = {
        invoice_no: inv,
        receive_type: $('#receive_type').val(),
        product_id: $('#product_id').val(),
        org_product_id: $('#org_product_id').val(),
        material_in: $('#material_in').val(),
        supplier_id: sup,
        receive_date: $('#receive_date').val()
    };

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_save_heading', payload: payload })
    });
    const json = await res.json();

    if (json.status === 'success') {
        $('#invoice_no').prop('readonly', true);
        $('#receive_type, #product_id, #org_product_id').prop('disabled', true);
        $('#headingStatusBadge').css('background', '#059669').text('SAVED HEADING: ' + inv);
        $('#detailWorkspace').slideDown();
        applyFieldRules();
        $('#reference_no').focus();
    } else {
        alert('Error: ' + json.message);
    }
}

// =========================================================================
// 3. ตรวจสอบ Reference Product & Sale Order และคำนวณน้ำหนัก
// =========================================================================
async function checkReferenceProduct() {
    const refNo = $('#reference_no').val().trim();
    const orgId = $('#org_product_id').val();
    const rType = $('#receive_type').val();

    if (!refNo) return;

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'rc_check_reference',
            ref_no: refNo,
            org_product_id: orgId,
            receive_type: rType
        })
    });
    const json = await res.json();

    if (json.status === 'error') {
        alert(json.message);
        $('#reference_no').val('').focus();
        return;
    }

    if (json.status === 'not_found_nn') {
        $('#ref_status').val('NN');
        $('#ref_status_display').val('NN (New Coil Ref)');
        return;
    }

    const d = json.data;
    const headMatIn = $('#material_in').val();
    if (d.material_in && d.material_in !== headMatIn) {
        alert(`แจ้งเตือน: Material In ในส่วน Heading (${headMatIn}) ไม่ตรงกับสินค้าอ้างอิง (${d.material_in})`);
    }

    $('#ref_status').val(d.ref_status);
    $('#ref_status_display').val(`${d.ref_status} | ${d.material_in || headMatIn}`);
    $('#ref_coil_no').val(d.coil_no);
    $('#ref_spec_display').val(`${d.alloy} / ${d.temper} / ${d.grade}`);
    $('#ref_size_display').val(`${d.thickness.toFixed(3)} x ${d.width.toFixed(3)} x ${d.length.toFixed(2)}`);

    $('#ref_actual_weight').val(d.actual_weight);
    $('#ref_usage_weight').val(d.usage_weight);
    $('#ref_usage_weight_old').val(d.usage_weight);
    $('#ref_usage_weight1').val(d.usage_weight1);
    $('#ref_balance_weight').val(d.balance_weight);
    $('#ref_balance_weight_old').val(d.balance_weight);

    $('#ref_saleorder_no').val(d.saleorder_no);
    $('#ref_saleorder_item').val(d.saleorder_item);
    $('#ref_reject_weight_old').val(d.reject_weight);
    $('#ref_reject_piece_old').val(d.reject_piece);

    // นำสเปกจาก Reference มาเติมลงฟอร์ม
    if (d.product_model) { $('#product_model').val(d.product_model); $('#area_size').val(d.area_size); }
    if (d.alloy) $('#alloy').val(d.alloy);
    if (d.temper) $('#temper').val(d.temper);
    if (d.sg) $('#sg').val(d.sg);
    if (d.mg) $('#mg').val(d.mg);
    if (d.grade) $('#grade').val(d.grade);
    $('#thickness').val(d.thickness.toFixed(3));
    $('#width').val(d.width.toFixed(3));
    if (orgId === 'SH' || orgId === 'NC') $('#length').val(d.length.toFixed(2));

    calculateWeights();
}

function onProductModelChange() {
    const opt = $('#product_model option:selected');
    $('#area_size').val(opt.data('area') || 0);
    calculateWeights();
}

// คำนวณน้ำหนักต่อชิ้น, จำนวนชิ้น, Package Weight และเช็กเกณฑ์ +3%
function calculateWeights() {
    const pId   = $('#product_id').val();
    const rType = $('#receive_type').val();
    const orgId = $('#org_product_id').val();

    const thick = parseFloat($('#thickness').val()) || 0;
    const width = parseFloat($('#width').val()) || 0;
    const len   = parseFloat($('#length').val()) || 0;
    const area  = parseFloat($('#area_size').val()) || 0;

    // สูตรคำนวณน้ำหนักต่อชิ้นอะลูมิเนียม (ความหนาแน่น ~2.71 g/cm3)
    let wPiece = 0;
    if (pId === 'CC') {
        wPiece = (Math.PI * Math.pow(width / 2, 2) * thick * 2.71) / 1000000;
    } else if (pId === 'SH') {
        wPiece = (width * len * thick * 2.71) / 1000000;
    } else if (pId === 'NC') {
        wPiece = (area * thick * 2.71) / 1000000;
    }
    $('#weight_piece').val(wPiece.toFixed(4));

    const grossW = parseFloat($('#gross_weight').val()) || 0;
    const netW   = parseFloat($('#net_weight').val()) || 0;
    const oldNet = parseFloat($('#old_net_weight').val()) || 0;

    if (grossW > 0 && grossW < netW) {
        alert('คำเตือน: Gross Weight ต้องมากกว่าหรือเท่ากับ Net Weight (BWRHS-81)');
    }
    $('#package_weight').val(Math.max(0, grossW - netW).toFixed(0));

    if (['CC', 'SH', 'NC'].includes(pId) && wPiece > 0) {
        $('#net_piece').val(Math.round(netW / wPiece));
    } else if (pId === 'CO') {
        $('#net_piece').val(1);
    } else {
        $('#net_piece').val(0);
    }

    // คำนวณยอดคงเหลือ Reference Real-time
    const refNo     = $('#reference_no').val().trim();
    const refStatus = $('#ref_status').val();
    if (refNo && refStatus !== 'NN' && orgId !== 'SC') {
        const varNet   = netW - oldNet;
        const actW     = parseFloat($('#ref_actual_weight').val()) || 0;
        const usageOld = parseFloat($('#ref_usage_weight_old').val()) || 0;
        const usage1   = parseFloat($('#ref_usage_weight1').val()) || 0;

        let checkBal = 0;
        if (rType === 'RT') {
            checkBal = actW - (usageOld + varNet);
        } else {
            const maxAllowed = Math.round(actW + (actW * 0.03));
            checkBal = maxAllowed - Math.round((usageOld + varNet) + usage1);
        }

        if (checkBal < 0) {
            alert('คำเตือน: น้ำหนักรับเข้าเกินกว่าน้ำหนักคงเหลือของวัตถุดิบอ้างอิง (+3% Max) (BWRHS-80)');
            $('#net_weight').val(oldNet);
            return;
        }
        const newUsage = usageOld + varNet;
        const newBal   = (rType === 'RT') ? (actW - newUsage) : (actW - (newUsage + usage1));
        $('#ref_usage_weight').val(newUsage.toFixed(0));
        $('#ref_balance_weight').val(newBal.toFixed(0));
    }
}

async function checkSaleOrderSpec() {
    const soNo   = $('#saleorder_no').val().trim();
    const soItem = $('#saleorder_item').val().trim();
    if (!soNo || !soItem) { alert('กรุณาระบุ Sale Order No และ Item'); return; }

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'rc_check_sale_order',
            so_no: soNo,
            so_item: soItem,
            specs: {
                alloy: $('#alloy').val(),
                temper: $('#temper').val(),
                sg: $('#sg').val(),
                mg: $('#mg').val(),
                thickness: $('#thickness').val(),
                width: $('#width').val(),
                length: $('#length').val()
            }
        })
    });
    const json = await res.json();
    if (json.status === 'success') {
        alert('✅ สเปกสินค้าตรงกับ Sale Order เรียบร้อยแล้ว');
    } else {
        alert('❌ ' + json.message);
        $('#saleorder_no, #saleorder_item').val('');
    }
}

// =========================================================================
// 4. บันทึกรายการสินค้า (Save Item) และอัปเดตตารางด้านซ้าย
// =========================================================================
async function saveProductDetail() {
    const netW   = parseFloat($('#net_weight').val()) || 0;
    const grossW = parseFloat($('#gross_weight').val()) || 0;

    if (netW <= 0) { alert('กรุณาระบุ Net Weight มากกว่า 0'); $('#net_weight').focus(); return; }
    if (grossW < netW) { alert('Gross Weight ต้องมากกว่าหรือเท่ากับ Net Weight'); $('#gross_weight').focus(); return; }

    const rType = $('#receive_type').val();
    if (rType === 'DR' && (!$('#saleorder_no').val().trim() || !$('#saleorder_item').val().trim())) {
        alert('งาน Direct Shipment (DR) กรุณาระบุ Sale Order No และ Item');
        return;
    }

    const btn = $('#btnSaveDetail');
    btn.prop('disabled', true).text('Saving...');

    const payload = {
        invoice_no: $('#invoice_no').val().trim(),
        receive_type: rType,
        product_id: $('#product_id').val(),
        org_product_id: $('#org_product_id').val(),
        material_in: $('#material_in').val(),
        supplier_id: $('#supplier_id').val(),
        receive_date: $('#receive_date').val(),

        product_no: $('#product_no').val().trim(),
        reference_no: $('#reference_no').val().trim(),
        ref_status: $('#ref_status').val(),
        coil_no: $('#ref_coil_no').val(),

        product_model: $('#product_model').val(),
        area_size: $('#area_size').val(),
        coil_type: $('#coil_type').val(),
        alloy: $('#alloy').val(),
        temper: $('#temper').val(),
        grade: $('#grade').val(),
        sg: $('#sg').val(),
        mg: $('#mg').val(),
        thickness: $('#thickness').val(),
        width: $('#width').val(),
        length: $('#length').val(),
        weight_piece: $('#weight_piece').val(),

        gross_weight: grossW,
        net_weight: netW,
        old_net_weight: $('#old_net_weight').val(),
        net_piece: $('#net_piece').val(),

        ref_actual_weight: $('#ref_actual_weight').val(),
        ref_usage_weight_old: $('#ref_usage_weight_old').val(),
        ref_usage_weight1: $('#ref_usage_weight1').val(),
        ref_saleorder_no: $('#ref_saleorder_no').val(),
        ref_saleorder_item: $('#ref_saleorder_item').val(),
        ref_reject_weight_old: $('#ref_reject_weight_old').val(),
        ref_reject_piece_old: $('#ref_reject_piece_old').val(),

        mat_po_no: $('#mat_po_no').val().trim(),
        mat_po_item: $('#mat_po_item').val().trim(),
        mp2_partno: $('#mp2_partno').val().trim(),
        location_id: $('#location_id').val(),
        primary_smelt: $('#primary_smelt').val(),
        secondary_smelt: $('#secondary_smelt').val(),
        country_melt: $('#country_melt').val(),
        country_origin: $('#country_origin').val(),
        dim_width: $('#dim_width').val(),
        dim_length: $('#dim_length').val(),
        dim_high: $('#dim_high').val(),
        remark: $('#remark').val().trim(),
        saleorder_no: $('#saleorder_no').val().trim(),
        saleorder_item: $('#saleorder_item').val().trim(),
        print_label: $('#chk_print_label').is(':checked') ? 1 : 0
    };

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'rc_save_detail', payload: payload })
        });
        const json = await res.json();

        if (json.status === 'success') {
            alert('✅ บันทึกสำเร็จ! รหัสสินค้า: ' + json.product_no);
            resetDetailForm();
            checkExistingInvoice(); // โหลดรายการในตารางซ้ายใหม่
        } else {
            alert('❌ เกิดข้อผิดพลาด: ' + json.message);
        }
    } catch (e) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    } finally {
        btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> บันทึกรายการสินค้า (Save Item)');
    }
}

function renderSavedItems(items) {
    const tbody = $('#savedItemsTbody');
    tbody.empty();
    $('#itemCountBadge').text(items.length);

    if (!items.length) {
        tbody.append('<tr><td colspan="4" class="text-center text-muted">ยังไม่มีรายการสินค้า</td></tr>');
        return;
    }

    items.forEach(item => {
        const stClass = (item.STATUS_ID === 'AC' || item.STATUS_ID === 'DR') ? 'badge-ac' : 'badge-op';
        tbody.append(`
            <tr>
                <td><strong>${item.PRODUCT_NO}</strong></td>
                <td>${item.ALLOY || ''} ${parseFloat(item.THICKNESS).toFixed(3)}x${parseFloat(item.WIDTH).toFixed(1)}<br>
                    <small class="text-muted">Ref: ${item.PRODUCT_REFERENCE || '-'}</small>
                </td>
                <td class="text-right"><strong>${Number(item.REC2_NETWEIGHT).toLocaleString()}</strong></td>
                <td class="text-center"><span class="${stClass}">${item.STATUS_ID}</span></td>
            </tr>
        `);
    });
}

function resetDetailForm() {
    $('#product_no, #reference_no, #ref_status, #ref_status_display, #ref_coil_no, #ref_spec_display, #ref_size_display').val('');
    $('#ref_actual_weight, #ref_usage_weight, #ref_usage_weight_old, #ref_usage_weight1, #ref_balance_weight, #ref_balance_weight_old').val('0');
    $('#gross_weight, #net_weight, #old_net_weight, #net_piece, #package_weight, #dim_width, #dim_length, #dim_high').val('0');
    $('#remark, #saleorder_no, #saleorder_item').val('');
    $('#chk_print_label').prop('checked', false);
    $('#detailModeLabel').css('background', '#2563eb').text('NEW ITEM (Auto-Run Product No)');
    $('#reference_no').focus();
}
</script>
</html>