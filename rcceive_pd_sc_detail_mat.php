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
$invoice_no   = isset($_GET['inv']) ? htmlspecialchars(trim($_GET['inv']), ENT_QUOTES, 'UTF-8') : '';

if (empty($invoice_no)) {
    header("Location: receive_pd_sc_process_mats.php?func={$folder_func}&type={$type_param}");
    exit();
}

include("dbcon_mats-new.php");
require_once("model/rc_receive_model.php");

$rcModel = new RcReceiveModel($conn);
$detail  = $rcModel->rc_get_invoice_detail($invoice_no);

if (!$detail) {
    echo "<script>alert('ไม่พบข้อมูล Invoice: {$invoice_no}'); window.location.href='receive_pd_sc_process_mats.php?func={$folder_func}&type={$type_param}';</script>";
    exit();
}

$header = $detail['header'];
$items  = $detail['items'];
$rtype  = strtoupper(trim($header['RECEIVE_TYPE']));

// ตรวจสอบสิทธิ์แผนกตามเงื่อนไข GetHeading ใน rc1
if (in_array($current_dept, ['PP', 'MK']) && $rtype !== 'DR') {
    echo "<script>alert('แผนกของท่านสามารถดูได้เฉพาะรายการ DR เท่านั้น (BWRHS-01)'); window.location.href='receive_pd_sc_process_mats.php?func={$folder_func}&type={$type_param}';</script>";
    exit();
}
if ($current_dept === 'WH' && $rtype === 'DR') {
    echo "<script>alert('แผนกคลังสินค้าไม่สามารถทำรายการ DR ได้ (BWRHS-01)'); window.location.href='receive_pd_sc_process_mats.php?func={$folder_func}&type={$type_param}';</script>";
    exit();
}

$can_edit = in_array($current_dept, ['WH', 'MK', 'PP', 'IT']) || ($current_func === 'IT');
$masters  = $rcModel->rc_get_master_dropdowns();
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Receive Detail (<?php echo $invoice_no; ?>)</title>
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
            font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px;
            text-transform: uppercase; letter-spacing: 0.3px; display: block;
        }
        .input-readonly-blue {
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1e3a8a !important;
            font-weight: 700;
            cursor: not-allowed;
        }
        .input-required {
            border: 1.5px solid #f87171 !important;
            background-color: #fef2f2 !important;
            font-weight: 700; color: #1e293b;
        }
        .form-control {
            height: 35px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;
        }
        .btn-action {
            height: 36px; padding: 0 16px; border-radius: 8px; font-weight: 700; font-size: 12.5px;
            display: inline-flex; align-items: center; gap: 6px; border: none; transition: all 0.2s;
            text-decoration: none; cursor: pointer;
        }
        .btn-save { background-color: #059669; color: #fff; }
        .btn-save:hover { background-color: #047857; color: #fff; }
        .btn-delete { background-color: #dc2626; color: #fff; }
        .btn-delete:hover { background-color: #b91c1c; color: #fff; }
        .btn-delete:disabled { background-color: #fca5a5; cursor: not-allowed; }
        .btn-add-new { background-color: #2563eb; color: #fff; }
        .btn-add-new:hover { background-color: #1d4ed8; color: #fff; text-decoration: none; }
        .btn-back { background-color: #64748b; color: #fff; }
        .btn-back:hover { background-color: #475569; color: #fff; text-decoration: none; }
        .btn-print { background-color: #0f172a; color: #fff; }
        .btn-print:hover { background-color: #334155; color: #fff; }

        .item-table th {
            background-color: #f1f5f9; font-size: 11px; font-weight: 700; color: #475569; padding: 8px !important;
        }
        .item-table td {
            font-size: 12.5px; padding: 8px !important; vertical-align: middle; cursor: pointer;
        }
        .item-table tbody tr.selected-row td {
            background-color: #dbeafe !important;
            border-top: 1px solid #3b82f6;
            border-bottom: 1px solid #3b82f6;
        }
        .badge-op { background-color: #dbeafe; color: #1d4ed8; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .badge-ac { background-color: #dcfce7; color: #15803d; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .box-group {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 14px;
        }
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
                        Receive Detail & Management
                        <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">INVOICE: <?php echo $invoice_no; ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Action Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <a href="receive_pd_sc_process_mats.php?func=<?php echo $folder_func; ?>&type=<?php echo $type_param; ?>" class="btn-action btn-back">
                    ⬅️ Back to List
                </a>
                <div style="display:flex; gap:8px;">
                    <?php if ($can_edit): ?>
                        <a href="rcceive_pd_sc_entry_mat.php?func=<?php echo $folder_func; ?>&type=<?php echo $type_param; ?>&inv=<?php echo urlencode($invoice_no); ?>" class="btn-action btn-add-new">
                            + เพิ่มรายการสินค้าใน Invoice นี้
                        </a>
                        <?php if (count($items) === 0): ?>
                            <button type="button" class="btn-action btn-delete" onclick="deleteHeading()">
                                ลบ Invoice (Delete Heading)
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- ส่วนที่ 1: HEADING INFORMATION (RECVPROD1)                             -->
            <!-- ===================================================================== -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>1. ข้อมูลส่วนหัวเอกสาร (Receive Heading)</span>
                    <div>
                        <button type="button" class="btn btn-xs btn-default" onclick="$('#contactInfoBox').slideToggle()">ดูข้อมูลผู้ติดต่อ</button>
                        <?php if ($can_edit): ?>
                            <button type="button" class="btn btn-xs btn-success" onclick="updateHeading()" style="font-weight:700; margin-left:6px;">
                                บันทึกการแก้ไข Heading
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Invoice No.</label>
                            <input type="text" class="form-control input-readonly-blue" id="invoice_no" value="<?php echo $invoice_no; ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Receive Type</label>
                            <input type="text" class="form-control input-readonly-blue" id="receive_type" value="<?php echo $rtype; ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Receive Product</label>
                            <input type="text" class="form-control input-readonly-blue" id="product_id" value="<?php echo htmlspecialchars(trim($header['PRODUCT_ID'])); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>From Original Product</label>
                            <input type="text" class="form-control input-readonly-blue" id="org_product_id" value="<?php echo htmlspecialchars(trim($header['ORGPRODUCT_ID'])); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Material In (BOI)</label>
                            <select class="form-control" id="material_in" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <?php foreach (['BOI-MAT1', 'BOI-MAT2', 'BOI-IMP1', 'BOI-IMP2', 'NONE-BOI'] as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php echo trim($header['MATERIAL_IN']) === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Receive Date</label>
                            <input type="date" class="form-control" id="receive_date" 
                                   value="<?php echo !empty($header['RECEIVE_DATE']) ? date('Y-m-d', strtotime($header['RECEIVE_DATE'])) : ''; ?>"
                                   <?php echo !$can_edit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Supplier / Customer</label>
                            <select class="form-control" id="supplier_id" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                <?php foreach ($masters['suppliers'] as $sup): ?>
                                    <?php $sid = trim($sup['CSTMSPPL_ID']); ?>
                                    <option value="<?php echo htmlspecialchars($sid); ?>" <?php echo trim($header['CSTMSPPL_ID']) === $sid ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sid . ' : ' . trim($sup['CONSIGNEE_COMPANY'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Country of Melt</label>
                            <input type="text" class="form-control input-readonly-blue" value="<?php echo htmlspecialchars(trim($header['DEFAULT_COUNTRY_MELT'] ?? '')); ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Operator / Last Update</label>
                            <input type="text" class="form-control input-readonly-blue" value="<?php echo htmlspecialchars(trim($header['REC1_OPERATOR'] ?? '')); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div id="contactInfoBox" style="display:none; margin-top:12px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                    <div class="row">
                        <div class="col-md-6">
                            <label style="font-size:11px; color:#64748b;">ADDRESS</label>
                            <div style="font-size:12.5px; font-weight:600;">
                                <?php echo htmlspecialchars(trim(($header['CONSIGNEE_ADDRESS1'] ?? '') . ' ' . ($header['CONSIGNEE_ADDRESS2'] ?? '') . ' ' . ($header['CONSIGNEE_ADDRESS3'] ?? ''))); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label style="font-size:11px; color:#64748b;">CONTACT / POSITION</label>
                            <div style="font-size:12.5px; font-weight:600;">
                                <?php echo htmlspecialchars(trim(($header['CONSIGNEE_CONTACT'] ?? '-') . ' / ' . ($header['CONSIGNEE_POSITION'] ?? '-'))); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label style="font-size:11px; color:#64748b;">TEL / FAX / EMAIL</label>
                            <div style="font-size:12.5px; font-weight:600;">
                                <?php echo htmlspecialchars(trim(($header['CONSIGNEE_TELEPHONE'] ?? '-') . ' | ' . ($header['CONSIGNEE_EMAIL'] ?? '-'))); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================================== -->
            <!-- ส่วนที่ 2: PRODUCT ITEMS LIST (ซ้าย) & DETAIL EDIT/DELETE (ขวา)        -->
            <!-- ===================================================================== -->
            <div class="row">
                <!-- ตารางรายการสินค้าทั้งหมดใน Invoice -->
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <div class="section-title">
                            <span>รายการสินค้าทั้งหมด (<?php echo count($items); ?> รายการ)</span>
                        </div>
                        <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                            <table class="table table-bordered table-hover item-table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th>Product No</th>
                                        <th>Spec / Ref</th>
                                        <th class="text-right">Net Wt.</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">ไม่มีรายการสินค้าใน Invoice นี้</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $idx => $it): ?>
                                            <?php
                                                $pNo = htmlspecialchars(trim($it['PRODUCT_NO']));
                                                $st  = strtoupper(trim($it['STATUS_ID']));
                                                $stBadge = ($st === 'AC' || $st === 'DR') ? 'badge-ac' : 'badge-op';
                                            ?>
                                            <tr id="row_<?php echo $pNo; ?>" onclick='selectItem(<?php echo json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <td><strong><?php echo $pNo; ?></strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars(trim($it['ALLOY'] ?? '') . ' ' . number_format((float)$it['THICKNESS'], 3) . 'x' . number_format((float)$it['WIDTH'], 1)); ?><br>
                                                    <small class="text-muted">Ref: <?php echo htmlspecialchars(trim($it['PRODUCT_REFERENCE'] ?: '-')); ?></small>
                                                </td>
                                                <td class="text-right"><strong><?php echo number_format((float)$it['REC2_NETWEIGHT'], 0); ?></strong></td>
                                                <td class="text-center"><span class="<?php echo $stBadge; ?>"><?php echo $st; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ฟอร์มแสดงรายละเอียด แก้ไข (Edit) และลบ (Delete) -->
                <div class="col-md-8">
                    <div class="dashboard-card" id="detailEditPanel">
                        <div class="section-title">
                            <span>2. รายละเอียดสินค้า (Product Detail - Edit / Delete)</span>
                            <span id="statusIndicator" class="badge" style="background:#64748b;">กรุณาเลือกรายการทางซ้าย</span>
                        </div>

                        <div id="acWarningAlert" class="alert alert-warning" style="display:none; font-size:12.5px; font-weight:600;">
                            ⚠️ ผลิตภัณฑ์นี้ได้ทำการ Inspect ไปแล้ว (สถานะ AC) ระบบจะล็อกการแก้ไขสเปกและไม่อนุญาตให้ลบข้อมูล (หากจำเป็นต้องลบ กรุณาติดต่อ Technical ยกเลิกการ Inspect ก่อน)
                        </div>

                        <!-- ข้อมูลรหัสสินค้าและ Reference -->
                        <div class="box-group">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Product No</label>
                                        <input type="text" class="form-control input-readonly-blue" id="d_product_no" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Refer to Product</label>
                                        <input type="text" class="form-control input-readonly-blue" id="d_reference_no" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Current Status</label>
                                        <input type="text" class="form-control input-readonly-blue" id="d_status_id" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Ref. Product Weight</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_ref_actual_weight" value="0" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Ref. Usage Weight</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_ref_usage_weight" value="0" readonly>
                                        <input type="hidden" id="d_ref_usage_weight_old" value="0">
                                        <input type="hidden" id="d_ref_usage_weight1" value="0">
                                        <input type="hidden" id="d_ref_status" value="">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Ref. Balance Weight</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_ref_balance_weight" value="0" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Weight / Piece</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_weight_piece" value="0.0000" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- สเปกสินค้า (Specification) -->
                        <div class="box-group" style="background:#fff;">
                            <div style="font-weight:700; font-size:12px; color:#1e293b; margin-bottom:8px;">Specification</div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Product Model</label>
                                        <select class="form-control spec-field" id="d_product_model">
                                            <option value="">-- NONE --</option>
                                            <?php foreach ($masters['models'] as $m): ?>
                                                <option value="<?php echo htmlspecialchars(trim($m['PRODUCT_MODEL'])); ?>" data-area="<?php echo (float)$m['AREA_SIZE']; ?>">
                                                    <?php echo htmlspecialchars(trim($m['PRODUCT_MODEL'])); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" id="d_area_size" value="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Coil Type</label>
                                        <select class="form-control spec-field" id="d_coil_type">
                                            <option value=""></option>
                                            <option value="DC">DC</option>
                                            <option value="CC">CC</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Alloy</label>
                                        <select class="form-control spec-field" id="d_alloy">
                                            <?php foreach ($masters['alloys'] as $a): ?>
                                                <option value="<?php echo htmlspecialchars(trim($a['ALLOY'])); ?>"><?php echo htmlspecialchars(trim($a['ALLOY'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Temper</label>
                                        <select class="form-control spec-field" id="d_temper">
                                            <?php foreach ($masters['tempers'] as $t): ?>
                                                <option value="<?php echo htmlspecialchars(trim($t['TEMPER'])); ?>"><?php echo htmlspecialchars(trim($t['TEMPER'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Grade</label>
                                        <select class="form-control spec-field" id="d_grade">
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
                                        <label>SG</label>
                                        <select class="form-control spec-field" id="d_sg">
                                            <option value="SG3">SG3</option>
                                            <?php foreach ($masters['surface_grades'] as $s): ?>
                                                <option value="<?php echo htmlspecialchars(trim($s['SURFACE_GRADE'])); ?>"><?php echo htmlspecialchars(trim($s['SURFACE_GRADE'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>MG</label>
                                        <select class="form-control spec-field" id="d_mg">
                                            <option value="MG3">MG3</option>
                                            <?php foreach ($masters['metal_grades'] as $m): ?>
                                                <option value="<?php echo htmlspecialchars(trim($m['METALLURGICAL_GRADE'])); ?>"><?php echo htmlspecialchars(trim($m['METALLURGICAL_GRADE'])); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Thickness (mm)</label>
                                        <input type="number" step="0.001" class="form-control spec-field" id="d_thickness" onchange="recalcEditWeights()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Width (mm)</label>
                                        <input type="number" step="0.001" class="form-control spec-field" id="d_width" onchange="recalcEditWeights()">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Length (mm)</label>
                                        <input type="number" step="0.01" class="form-control spec-field" id="d_length" onchange="recalcEditWeights()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- น้ำหนักและยอดการใช้งานสะสม (Detail & Usage Weights จาก Detail.png) -->
                        <div class="box-group" style="background:#fff;">
                            <div style="font-weight:700; font-size:12px; color:#1e293b; margin-bottom:8px;">Weight, Location & Usage Summary</div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label style="color:#dc2626;">Gross Weight (kg)</label>
                                        <input type="number" class="form-control input-required text-right" id="d_gross_weight" onchange="recalcEditWeights()">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label style="color:#dc2626;">Net Weight (kg)</label>
                                        <input type="number" class="form-control input-required text-right" id="d_net_weight" onchange="recalcEditWeights()">
                                        <input type="hidden" id="d_old_net_weight" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Net Piece (Pc.)</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_net_piece" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Package Weight (kg)</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_package_weight" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- ยอดใช้งานสะสมของสินค้าตัวนี้ (Read-Only สีน้ำเงินเหมือน Detail.png) -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Produce / Scrap Wt.</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_prod_scrap_wt" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>SB Produce / Scrap Wt.</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_sb_wt" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Item Balance Weight</label>
                                        <input type="text" class="form-control input-readonly-blue text-right" id="d_item_balance_wt" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Location</label>
                                        <select class="form-control" id="d_location_id">
                                            <option value="">-- NONE --</option>
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
                                        <label>MAT PO No. / Item</label>
                                        <div style="display:flex; gap:4px;">
                                            <input type="text" class="form-control" id="d_mat_po_no" style="width:70%;">
                                            <input type="text" class="form-control" id="d_mat_po_item" style="width:30%;" maxlength="3">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>MP2 Item No.</label>
                                        <input type="text" class="form-control" id="d_mp2_partno">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Remark</label>
                                        <input type="text" class="form-control" id="d_remark">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Primary Smelt</label>
                                        <select class="form-control" id="d_primary_smelt">
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
                                        <select class="form-control" id="d_secondary_smelt">
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
                                        <select class="form-control" id="d_country_melt">
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
                                        <select class="form-control" id="d_country_origin">
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
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Dim. W</label>
                                        <input type="number" class="form-control text-right" id="d_dim_width" value="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Dim. L</label>
                                        <input type="number" class="form-control text-right" id="d_dim_length" value="0">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label>Dim. H</label>
                                        <input type="number" class="form-control text-right" id="d_dim_high" value="0">
                                    </div>
                                </div>
                                <div class="col-md-6" id="d_so_box" style="<?php echo $rtype === 'DR' ? '' : 'display:none;'; ?>">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label style="color:#b45309;">Sale Order No / Item (DR)</label>
                                        <div style="display:flex; gap:6px;">
                                            <input type="text" class="form-control" id="d_saleorder_no" style="width:70%;">
                                            <input type="text" class="form-control" id="d_saleorder_item" style="width:30%;" maxlength="3">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- แถบปุ่มควบคุมด้านล่าง: Print Label / Edit / Delete -->
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid #e2e8f0;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <select class="form-control" id="d_label_size" style="width:100px; height:36px;">
                                    <option value="29x21">29x21</option>
                                    <option value="10x15">10x15</option>
                                    <option value="21x15">21x15</option>
                                </select>
                                <button type="button" class="btn-action btn-print" onclick="printProductLabel()">
                                    พิมพ์สติกเกอร์ (Print Label)
                                </button>
                                <span style="font-size:12px; color:#64748b;">(พิมพ์แล้ว: <strong id="d_printed_count">0</strong> ครั้ง)</span>
                            </div>

                            <?php if ($can_edit): ?>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn-action btn-delete" id="btnDeleteItem" onclick="deleteSelectedItem()" disabled>
                                    ลบรายการ (Delete)
                                </button>
                                <button type="button" class="btn-action btn-save" id="btnUpdateItem" onclick="updateSelectedItem()" disabled>
                                    บันทึกการแก้ไข (Save Edit)
                                </button>
                            </div>
                            <?php endif; ?>
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
const ALL_ITEMS = <?php echo json_encode($items); ?>;
let currentSelectedItem = null;

$(document).ready(function() {
    // เลือกรายการแรกอัตโนมัติถ้ามีข้อมูล
    if (ALL_ITEMS.length > 0) {
        selectItem(ALL_ITEMS[0]);
    }
});

// เลือกรายการจากตารางซ้ายมาแสดงในฟอร์มขวา
async function selectItem(item) {
    currentSelectedItem = item;
    $('#itemsTable tbody tr').removeClass('selected-row');
    $(`#row_${item.PRODUCT_NO}`).addClass('selected-row');

    const status = (item.STATUS_ID || 'OP').trim();
    const isAC   = (status === 'AC');

    $('#d_product_no').val(item.PRODUCT_NO);
    $('#d_reference_no').val(item.PRODUCT_REFERENCE || '');
    $('#d_status_id').val(status);

    $('#statusIndicator').css('background', isAC ? '#059669' : '#2563eb').text(`SELECTED: ${item.PRODUCT_NO} (${status})`);
    $('#acWarningAlert').toggle(isAC);

    // ควบคุมปุ่ม Edit / Delete และช่องสเปกตามเงื่อนไขสถานะ AC ใน rc1
    $('#btnUpdateItem').prop('disabled', false);
    $('#btnDeleteItem').prop('disabled', isAC);
    $('.spec-field').prop('disabled', isAC);

    // เติมข้อมูลสเปก
    $('#d_product_model').val((item.PRODUCT_MODEL || '').trim());
    $('#d_area_size').val(item.AREA_SIZE || 0);
    $('#d_coil_type').val((item.COIL_TYPE || '').trim());
    $('#d_alloy').val((item.ALLOY || '').trim());
    $('#d_temper').val((item.TEMPER || '').trim());
    $('#d_grade').val((item.GRADE || 'NONE').trim());
    $('#d_sg').val((item.SURFACE_GRADE || 'SG3').trim());
    $('#d_mg').val((item.METALLURGICAL_GRADE || 'MG3').trim());
    $('#d_thickness').val(parseFloat(item.THICKNESS || 0).toFixed(3));
    $('#d_width').val(parseFloat(item.WIDTH || 0).toFixed(3));
    $('#d_length').val(parseFloat(item.LENGTH || 0).toFixed(2));
    $('#d_weight_piece').val(parseFloat(item.WEIGHT_PIECE || 0).toFixed(4));

    // เติมข้อมูลน้ำหนัก
    const netW = parseFloat(item.REC2_NETWEIGHT || 0);
    const pkgW = parseFloat(item.REC2_PACKAGEWEIGHT || 0);
    $('#d_gross_weight').val((netW + pkgW).toFixed(0));
    $('#d_net_weight').val(netW.toFixed(0));
    $('#d_old_net_weight').val(netW.toFixed(0));
    $('#d_net_piece').val(parseFloat(item.REC2_NETPIECE || 0).toFixed(0));
    $('#d_package_weight').val(pkgW.toFixed(0));

    // ยอดสะสมของตัวสินค้า
    $('#d_prod_scrap_wt').val(`${parseFloat(item.PRODUCE_WEIGHT || 0).toFixed(0)} / ${parseFloat(item.SCRAP_WEIGHT || 0).toFixed(0)}`);
    $('#d_sb_wt').val(`${parseFloat(item.SB_PRODUCE_WEIGHT || 0).toFixed(0)} / ${parseFloat(item.SB_SCRAP_WEIGHT || 0).toFixed(0)}`);
    $('#d_item_balance_wt').val(parseFloat(item.BALANCE_WEIGHT || 0).toFixed(0));

    // รายละเอียดเพิ่มเติม
    $('#d_location_id').val((item.LOCATION_ID || '').trim());
    $('#d_mat_po_no').val((item.REC2_MATPONO || '').trim());
    $('#d_mat_po_item').val((item.REC2_MATPOITEM || '').trim());
    $('#d_mp2_partno').val((item.MP2_PARTNO || '').trim());
    $('#d_remark').val((item.REMARK || '').trim());
    $('#d_primary_smelt').val((item.PRIMARY_SMELT || '').trim());
    $('#d_secondary_smelt').val((item.SECONDARY_SMELT || '').trim());
    $('#d_country_melt').val((item.COUNTRY_MELT || '').trim());
    $('#d_country_origin').val((item.COUNTRY_ORIGIN || '').trim());
    $('#d_dim_width').val(item.DIM_WIDTH || 0);
    $('#d_dim_length').val(item.DIM_LENGTH || 0);
    $('#d_dim_high').val(item.DIM_HIGH || 0);
    $('#d_saleorder_no').val((item.SO_NO || '').trim());
    $('#d_saleorder_item').val((item.SO_ITEM || '').trim());
    $('#d_printed_count').text(item.PRINT_LABEL || 0);

    // ดึงยอดคงเหลือล่าสุดของ ReferenceNo (ถ้ามี)
    if (item.PRODUCT_REFERENCE) {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'rc_check_reference',
                ref_no: item.PRODUCT_REFERENCE,
                org_product_id: $('#org_product_id').val(),
                receive_type: $('#receive_type').val()
            })
        });
        const json = await res.json();
        if (json.status === 'success' && json.data) {
            $('#d_ref_status').val(json.data.ref_status);
            $('#d_ref_actual_weight').val(json.data.actual_weight);
            $('#d_ref_usage_weight').val(json.data.usage_weight);
            $('#d_ref_usage_weight_old').val(json.data.usage_weight);
            $('#d_ref_usage_weight1').val(json.data.usage_weight1);
            $('#d_ref_balance_weight').val(json.data.balance_weight);
        }
    } else {
        $('#d_ref_status').val('');
        $('#d_ref_actual_weight, #d_ref_usage_weight, #d_ref_usage_weight_old, #d_ref_usage_weight1, #d_ref_balance_weight').val('0');
    }
}

function recalcEditWeights() {
    const pId   = $('#product_id').val();
    const rType = $('#receive_type').val();
    const orgId = $('#org_product_id').val();

    const thick = parseFloat($('#d_thickness').val()) || 0;
    const width = parseFloat($('#d_width').val()) || 0;
    const len   = parseFloat($('#d_length').val()) || 0;
    const area  = parseFloat($('#d_area_size').val()) || 0;

    let wPiece = 0;
    if (pId === 'CC') wPiece = (Math.PI * Math.pow(width / 2, 2) * thick * 2.71) / 1000000;
    else if (pId === 'SH') wPiece = (width * len * thick * 2.71) / 1000000;
    else if (pId === 'NC') wPiece = (area * thick * 2.71) / 1000000;
    $('#d_weight_piece').val(wPiece.toFixed(4));

    const grossW = parseFloat($('#d_gross_weight').val()) || 0;
    const netW   = parseFloat($('#d_net_weight').val()) || 0;
    const oldNet = parseFloat($('#d_old_net_weight').val()) || 0;

    if (grossW < netW) {
        alert('คำเตือน: Gross Weight ต้องมากกว่าหรือเท่ากับ Net Weight (BWRHS-81)');
    }
    $('#d_package_weight').val(Math.max(0, grossW - netW).toFixed(0));

    if (['CC', 'SH', 'NC'].includes(pId) && wPiece > 0) {
        $('#d_net_piece').val(Math.round(netW / wPiece));
    } else if (pId === 'CO') {
        $('#d_net_piece').val(1);
    }

    // เช็กเกณฑ์ +3% เทียบกับ Reference
    const refNo     = $('#d_reference_no').val().trim();
    const refStatus = $('#d_ref_status').val();
    if (refNo && refStatus !== 'NN' && orgId !== 'SC') {
        const varNet   = netW - oldNet;
        const actW     = parseFloat($('#d_ref_actual_weight').val()) || 0;
        const usageOld = parseFloat($('#d_ref_usage_weight_old').val()) || 0;
        const usage1   = parseFloat($('#d_ref_usage_weight1').val()) || 0;

        let checkBal = (rType === 'RT')
            ? (actW - (usageOld + varNet))
            : (Math.round(actW * 1.03) - Math.round((usageOld + varNet) + usage1));

        if (checkBal < 0) {
            alert('คำเตือน: น้ำหนักที่แก้ไขเกินกว่าน้ำหนักคงเหลือของวัตถุดิบอ้างอิง (+3% Max) (BWRHS-80)');
            $('#d_net_weight').val(oldNet);
            return;
        }
        const newUsage = usageOld + varNet;
        const newBal   = (rType === 'RT') ? (actW - newUsage) : (actW - (newUsage + usage1));
        $('#d_ref_usage_weight').val(newUsage.toFixed(0));
        $('#d_ref_balance_weight').val(newBal.toFixed(0));
    }
}

// บันทึกการแก้ไข Heading
async function updateHeading() {
    if (!confirm('ยืนยันการบันทึกแก้ไขส่วนหัวเอกสาร (Heading) หรือไม่?')) return;
    const payload = {
        invoice_no: $('#invoice_no').val(),
        material_in: $('#material_in').val(),
        supplier_id: $('#supplier_id').val(),
        receive_date: $('#receive_date').val()
    };
    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_save_heading', payload: payload })
    });
    const json = await res.json();
    if (json.status === 'success') {
        alert('✅ อัปเดตข้อมูลส่วนหัวเรียบร้อยแล้ว');
        window.location.reload();
    } else {
        alert('❌ ' + json.message);
    }
}

// ลบ Heading (ทำได้เมื่อไม่มีรายการสินค้าลูกเหลืออยู่)
async function deleteHeading() {
    if (!confirm('คุณยืนยันการลบเอกสาร Invoice นี้หรือไม่?')) return;
    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_delete_heading', invoice_no: $('#invoice_no').val() })
    });
    const json = await res.json();
    if (json.status === 'success') {
        alert('✅ ลบเอกสาร Invoice เรียบร้อยแล้ว');
        window.location.href = 'receive_pd_sc_process_mats.php?func=<?php echo $folder_func; ?>&type=<?php echo $type_param; ?>';
    } else {
        alert('❌ ' + json.message);
    }
}

// บันทึกการแก้ไข Detail (Edit)
async function updateSelectedItem() {
    if (!currentSelectedItem) return;
    const netW   = parseFloat($('#d_net_weight').val()) || 0;
    const grossW = parseFloat($('#d_gross_weight').val()) || 0;
    if (grossW < netW) { alert('Gross Weight ต้องมากกว่าหรือเท่ากับ Net Weight'); return; }

    if (!confirm('ยืนยันการบันทึกแก้ไขข้อมูลสินค้า ' + currentSelectedItem.PRODUCT_NO + ' หรือไม่?')) return;

    const payload = {
        invoice_no: $('#invoice_no').val(),
        receive_type: $('#receive_type').val(),
        product_id: $('#product_id').val(),
        org_product_id: $('#org_product_id').val(),
        material_in: $('#material_in').val(),
        supplier_id: $('#supplier_id').val(),
        receive_date: $('#receive_date').val(),

        product_no: currentSelectedItem.PRODUCT_NO,
        status_id: $('#d_status_id').val(),
        reference_no: $('#d_reference_no').val(),
        ref_status: $('#d_ref_status').val(),

        product_model: $('#d_product_model').val(),
        area_size: $('#d_area_size').val(),
        coil_type: $('#d_coil_type').val(),
        alloy: $('#d_alloy').val(),
        temper: $('#d_temper').val(),
        grade: $('#d_grade').val(),
        sg: $('#d_sg').val(),
        mg: $('#d_mg').val(),
        thickness: $('#d_thickness').val(),
        width: $('#d_width').val(),
        length: $('#d_length').val(),
        weight_piece: $('#d_weight_piece').val(),

        gross_weight: grossW,
        net_weight: netW,
        old_net_weight: $('#d_old_net_weight').val(),
        net_piece: $('#d_net_piece').val(),
        balance_weight: $('#d_item_balance_wt').val(),

        ref_actual_weight: $('#d_ref_actual_weight').val(),
        ref_usage_weight_old: $('#d_ref_usage_weight_old').val(),
        ref_usage_weight1: $('#d_ref_usage_weight1').val(),

        mat_po_no: $('#d_mat_po_no').val(),
        mat_po_item: $('#d_mat_po_item').val(),
        mp2_partno: $('#d_mp2_partno').val(),
        location_id: $('#d_location_id').val(),
        primary_smelt: $('#d_primary_smelt').val(),
        secondary_smelt: $('#d_secondary_smelt').val(),
        country_melt: $('#d_country_melt').val(),
        country_origin: $('#d_country_origin').val(),
        dim_width: $('#d_dim_width').val(),
        dim_length: $('#d_dim_length').val(),
        dim_high: $('#d_dim_high').val(),
        remark: $('#d_remark').val(),
        saleorder_no: $('#d_saleorder_no').val(),
        saleorder_item: $('#d_saleorder_item').val(),
        print_label: parseInt($('#d_printed_count').text() || 0)
    };

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_save_detail', payload: payload })
    });
    const json = await res.json();
    if (json.status === 'success') {
        alert('✅ บันทึกการแก้ไขเรียบร้อยแล้ว');
        window.location.reload();
    } else {
        alert('❌ ' + json.message);
    }
}

// ลบรายการสินค้า (Delete) พร้อมคืนยอดสต็อก
async function deleteSelectedItem() {
    if (!currentSelectedItem) return;
    if ($('#d_status_id').val() === 'AC') {
        alert('ผลิตภัณฑ์นี้ได้ทำการ Inspect ไปแล้ว กรุณาติดต่อ Technical ยกเลิกการ Inspect ก่อนจึงจะลบได้');
        return;
    }
    if (!confirm('คุณยืนยันการลบข้อมูลสินค้า ' + currentSelectedItem.PRODUCT_NO + ' หรือไม่?\n(ระบบจะคืนยอดน้ำหนักกลับไปยังสินค้าอ้างอิงอัตโนมัติ)')) return;

    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rc_delete_detail', product_no: currentSelectedItem.PRODUCT_NO })
    });
    const json = await res.json();
    if (json.status === 'success') {
        alert('✅ ลบข้อมูลและคืนยอดสต็อกเรียบร้อยแล้ว');
        window.location.reload();
    } else {
        alert('❌ ' + json.message);
    }
}

// พิมพ์สติกเกอร์ติดสินค้า (Web Print Layout)
function printProductLabel() {
    if (!currentSelectedItem) { alert('กรุณาเลือกรายการสินค้าที่ต้องการพิมพ์ป้าย'); return; }
    const size = $('#d_label_size').val();
    const pNo  = $('#d_product_no').val();
    const ref  = $('#d_reference_no').val();
    const spec = `${$('#d_alloy').val()} - ${$('#d_temper').val()} (${$('#d_thickness').val()} x ${$('#d_width').val()})`;
    const netW = $('#d_net_weight').val();

    const win = window.open('', '_blank', 'width=500,height=400');
    win.document.write(`
        <html><head><title>Print Label ${pNo}</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 15px; }
            .label-box { border: 2px solid #000; padding: 12px; width: ${size === '10x15' ? '300px' : '420px'}; }
            .title { font-size: 14px; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 8px; }
            .barcode { font-size: 22px; font-weight: bold; letter-spacing: 2px; margin: 8px 0; }
            .row { font-size: 13px; margin-bottom: 4px; }
        </style></head>
        <body onload="window.print(); window.close();">
            <div class="label-box">
                <div class="title">MEYER ALUMINIUM THAILAND (${size})</div>
                <div class="barcode">*${pNo}*</div>
                <div class="row"><strong>Product No:</strong> ${pNo}</div>
                <div class="row"><strong>Ref No:</strong> ${ref || '-'}</div>
                <div class="row"><strong>Spec:</strong> ${spec}</div>
                <div class="row"><strong>Net Weight:</strong> ${netW} kg.</div>
                <div class="row"><strong>Location:</strong> ${$('#d_location_id').val() || '-'}</div>
            </div>
        </body></html>
    `);
    win.document.close();
}
</script>
</html>