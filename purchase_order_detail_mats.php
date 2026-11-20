<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func  = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$is_authorized = ($current_func === 'WH' || $department_func === 'EM' || $department_func === 'IT');
$record_num    = trim($_GET['record_num'] ?? '');

if ($record_num === '') {
    header("Location: purchase_order_receive_mats.php?func=" . urlencode($folder_func));
    exit();
}

include("dbcon_mats-new.php");
$conn_mats = $conn; unset($conn);
include("dbcon_mp2_mats.php");
$conn_mp2 = $conn; unset($conn);

require_once("model/po_receive_model.php");
$poModel = new PoReceiveModel($conn_mats, $conn_mp2);
$master  = $poModel->po_get_master_data();
$data    = $poModel->po_get_detail('record', ['record_num' => $record_num]);

if (!$data) {
    echo "<script>alert('ไม่พบข้อมูลเอกสาร {$record_num}'); window.location.assign('purchase_order_receive_mats.php?func=" . urlencode($folder_func) . "');</script>";
    exit();
}

$hasPO   = !empty(trim($data['PONUM'] ?? ''));
$hasItem = !empty(trim($data['ITEMNUM'] ?? ''));
$qtyRecv = (float)($data['QTYRECEIVED'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Record Detail (<?php echo htmlspecialchars($record_num); ?>)</title>
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
            padding: 24px; margin-bottom: 20px; border: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 14px; font-weight: 700; color: #1e293b; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 18px;
        }
        .form-group label {
            font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .form-control {
            border-radius: 6px; border: 1px solid #cbd5e1; height: 38px; font-size: 13.5px; font-weight: 600; color: #1e293b;
        }
        .form-control[readonly], .form-control[disabled] {
            background-color: #f1f5f9 !important; color: #64748b;
        }
        .btn-action {
            font-weight: 700; font-size: 13px; padding: 8px 18px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none; cursor: pointer;
        }
        .btn-edit { background-color: #f59e0b; color: #ffffff; }
        .btn-edit:hover { background-color: #d97706; color: #ffffff; }
        .btn-del { background-color: #dc2626; color: #ffffff; }
        .btn-del:hover { background-color: #b91c1c; color: #ffffff; }
        .btn-save { background-color: #059669; color: #ffffff; }
        .btn-save:hover { background-color: #047857; color: #ffffff; }
        .btn-back { background-color: #64748b; color: #ffffff; }
        .btn-back:hover { background-color: #475569; color: #ffffff; text-decoration: none; }
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
                        Record Detail: <span style="color:#059669;"><?php echo htmlspecialchars($record_num); ?></span>
                        <span class="badge" style="background-color:#f59e0b; font-size:11px; margin-left:8px;">OLD RECORD</span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <a href="purchase_order_receive_mats.php?func=<?php echo $folder_func; ?>&tab=COMPLETED" class="btn-action btn-back">
                     ⬅️ BACK TO LIST
                </a>

                <?php if ($is_authorized): ?>
                <div style="display: flex; gap: 8px;">
                    <button type="button" id="btnEdit" class="btn-action btn-edit" onclick="enable_edit_mode()">
                        EDIT RECORD (แก้ไขข้อมูล)
                    </button>
                    <button type="button" id="btnDelete" class="btn-action btn-del" onclick="delete_record()">
                        DELETE RECORD (ลบข้อมูล)
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <form id="frmPoDetail" onsubmit="submit_po_update(event)">
                <input type="hidden" name="action" value="po_update">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                <input type="hidden" name="record_num" value="<?php echo htmlspecialchars($record_num, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="line_number" value="<?php echo htmlspecialchars($data['LINENUMBER'] ?? '1', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="receiver_num" value="<?php echo htmlspecialchars($data['RECEIVERNUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="uom" value="<?php echo htmlspecialchars($data['UOM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="dest_id" value="<?php echo htmlspecialchars($data['DESTID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="due_date" value="<?php echo htmlspecialchars($data['DUEDATE_FMT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="dashboard-card">
                    <div class="section-title">ข้อมูลการตรวจรับสินค้า (Receiving Record Information)</div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Record No.</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record_num); ?>" readonly style="color:#059669; font-weight:700;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>PO Number</label>
                                <input type="text" name="po_num" class="form-control" value="<?php echo htmlspecialchars($data['PONUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Seq No.</label>
                                <input type="number" name="seq_num" class="form-control" value="<?php echo htmlspecialchars($data['SEQNUM'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Receipt No.</label>
                                <select name="receipt_num" id="receipt_num" class="form-control" disabled>
                                    <?php 
                                    $curRcp = intval($data['RECEIPTNUM'] ?? 1);
                                    for ($i = 1; $i <= 100; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i === $curRcp ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <input type="hidden" name="receipt_num" id="hidden_receipt_num" value="<?php echo $curRcp; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Gate Pass Count</label>
                                <input type="text" name="gate_pass" class="form-control text-center" value="<?php echo intval($data['GATEPASS'] ?? 0); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Receive Date</label>
                                <input type="date" name="date_received" id="date_received" class="form-control" value="<?php echo htmlspecialchars($data['DATERECEIVED_FMT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                <input type="hidden" name="date_received" id="hidden_date_received" value="<?php echo htmlspecialchars($data['DATERECEIVED_FMT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Receive Time</label>
                                <input type="time" name="time_received" id="time_received" step="1" class="form-control editable-field" value="<?php echo htmlspecialchars($data['TIMERECEIVED_FMT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Invoice No.</label>
                                <input type="text" name="invoice_num" class="form-control editable-field" value="<?php echo htmlspecialchars($data['INVOICENUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Delivery No.</label>
                                <input type="text" name="delivery_num" class="form-control editable-field" value="<?php echo htmlspecialchars($data['DELIVERYNUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Item No.</label>
                                <input type="text" name="item_num" class="form-control" value="<?php echo htmlspecialchars($data['ITEMNUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Description on PO</label>
                                <input type="text" name="description_on_po" id="description_on_po" class="form-control" value="<?php echo htmlspecialchars($data['DESCRIPTIONONPO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" disabled>
                                <input type="hidden" name="description_on_po" id="hidden_desc" value="<?php echo htmlspecialchars($data['DESCRIPTIONONPO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Vendor Name</label>
                                <select name="vendor_name" id="vendor_name" class="form-control" onchange="syncVendorId()" disabled>
                                    <option value="">-- SELECT VENDOR --</option>
                                    <?php 
                                    $curVendor = trim($data['VENDORNAME'] ?? '');
                                    foreach ($master['vendors'] as $v): 
                                        $vName = trim($v['NAME']);
                                        $vId   = trim($v['VENDORID']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($vName, ENT_QUOTES, 'UTF-8'); ?>" data-id="<?php echo htmlspecialchars($vId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($curVendor === $vName) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vName, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="vendor_name" id="hidden_vendor_name" value="<?php echo htmlspecialchars($curVendor, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="vendor_id" id="vendor_id" value="<?php echo htmlspecialchars($data['VENDORID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Qty Requested</label>
                                <input type="number" step="0.01" name="qty_requested" class="form-control text-right" value="<?php echo number_format((float)($data['QTYREQUESTED'] ?? 0), 2, '.', ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Qty Previous</label>
                                <input type="number" step="0.01" name="qty_previous" class="form-control text-right" value="<?php echo number_format((float)($data['QTYPREVIOUS'] ?? 0), 2, '.', ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label style="color:#059669;">Qty Received</label>
                                <input type="number" step="0.01" name="qty_received" class="form-control text-right editable-field" value="<?php echo number_format($qtyRecv, 2, '.', ''); ?>" style="font-weight:700;" disabled>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit (UOP)</label>
                                <select name="uop" id="uop" class="form-control" disabled>
                                    <?php 
                                    $curUop = trim($data['UOP'] ?? '');
                                    foreach ($master['uops'] as $u): 
                                        $unit = trim($u['UNIT']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($curUop === $unit) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="uop" id="hidden_uop" value="<?php echo htmlspecialchars($curUop, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Item Type</label>
                                <input type="text" name="item_type" class="form-control editable-field" value="<?php echo htmlspecialchars($data['ITEMTYPE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" disabled>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Warehouse</label>
                                <input type="text" name="receive_warehouse" class="form-control" value="<?php echo htmlspecialchars($data['RECEIVEWAREHOUSE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Received Result</label>
                                <select name="received_result" class="form-control editable-field" style="font-weight:700;" disabled>
                                    <option value="ACCEPT" <?php echo ($data['RECEIVEDRESULT'] ?? '') === 'ACCEPT' ? 'selected' : ''; ?>>ACCEPT</option>
                                    <option value="REJECT" <?php echo ($data['RECEIVEDRESULT'] ?? '') === 'REJECT' ? 'selected' : ''; ?>>REJECT</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Reason Code</label>
                                <select name="received_reason" class="form-control editable-field" disabled>
                                    <option value="NONE">NONE</option>
                                    <?php 
                                    $curReason = trim($data['RECEIVEDREASON'] ?? 'NONE');
                                    foreach ($master['reasons'] as $r): 
                                        $rcode = trim($r['REASON_CODE']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($curReason === $rcode) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Print Documents (พิมพ์เอกสารซ้ำ)</label>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a href="print_po_label.php?record_num=<?php echo urlencode($record_num); ?>&form=7x5&copy=1" target="_blank" class="btn btn-default btn-sm" style="font-weight:700;">
                                        🖨️ Label (7x5)
                                    </a>
                                    <a href="print_po_label.php?record_num=<?php echo urlencode($record_num); ?>&form=10x15&copy=1" target="_blank" class="btn btn-default btn-sm" style="font-weight:700;">
                                        🖨️ Label (10x15)
                                    </a>
                                    <a href="print_po_gatepass.php?record_num=<?php echo urlencode($record_num); ?>" target="_blank" class="btn btn-success btn-sm" style="font-weight:700;">
                                        🖨️ Gate Pass
                                    </a>
                                    <a href="print_po_reject.php?record_num=<?php echo urlencode($record_num); ?>" target="_blank" class="btn btn-danger btn-sm" style="font-weight:700;">
                                        🖨️ Reject Notice
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Warehouse Comment</label>
                                <textarea name="warehouse_comment" class="form-control editable-field" rows="2" style="height:auto;" disabled><?php echo htmlspecialchars($data['WAREHOUSECOMMENT'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Enduser Comment</label>
                                <textarea name="enduser_comment" class="form-control editable-field" rows="2" style="height:auto;" disabled><?php echo htmlspecialchars($data['ENDUSERCOMMENT'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Purchase Comment</label>
                                <textarea name="purchase_comment" class="form-control editable-field" rows="2" style="height:auto;" disabled><?php echo htmlspecialchars($data['PURCHASECOMMENT'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div id="editFooter" style="display:none; text-align:right; margin-top:20px; padding-top:15px; border-top:1px solid #e2e8f0;">
                        <button type="button" class="btn btn-default" onclick="location.reload()" style="margin-right:8px; font-weight:600;">CANCEL</button>
                        <button type="submit" id="btnUpdate" class="btn-action btn-save">UPDATE RECORD (บันทึกการแก้ไข)</button>
                    </div>
                </div>
            </form>

            <!-- ตารางประวัติการรับของ PO นี้ -->
            <?php if (!empty($data['HISTORY_LIST'])): ?>
            <div class="dashboard-card">
                <div class="section-title">ประวัติการรับสินค้าทั้งหมดของ PO / Item นี้</div>
                <div class="table-responsive">
                    <table class="table table-bordered" style="font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th class="text-center">Record No.</th>
                                <th class="text-center">Receipt #</th>
                                <th class="text-center">Receive Date</th>
                                <th class="text-center">Invoice No.</th>
                                <th class="text-center">Delivery No.</th>
                                <th class="text-right">Qty Received</th>
                                <th class="text-center">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['HISTORY_LIST'] as $h): ?>
                            <tr style="<?php echo $h['RECORDNUM'] === $record_num ? 'background:#fef3c7; font-weight:700;' : ''; ?>">
                                <td class="text-center"><?php echo htmlspecialchars($h['RECORDNUM']); ?></td>
                                <td class="text-center"><?php echo $h['RECEIPTNUM']; ?></td>
                                <td class="text-center"><?php echo date('d/m/Y', strtotime($h['DATERECEIVED'])); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($h['INVOICENUM'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($h['DELIVERYNUM'] ?? '-'); ?></td>
                                <td class="text-right"><?php echo number_format((float)$h['QTYRECEIVED'], 2) . ' ' . htmlspecialchars($h['UOP'] ?? ''); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($h['RECEIVEDRESULT']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
const HAS_PO   = <?php echo json_encode($hasPO); ?>;
const HAS_ITEM = <?php echo json_encode($hasItem); ?>;
const QTY_RECV = <?php echo json_encode($qtyRecv); ?>;

function syncVendorId() {
    var sel = document.getElementById('vendor_name');
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('vendor_id').value = opt ? (opt.getAttribute('data-id') || '') : '';
}

function enable_edit_mode() {
    // ปลดล็อกฟิลด์ทั่วไป
    document.querySelectorAll('.editable-field').forEach(el => el.disabled = false);

    // เงื่อนไข EnableField จาก VB6 (Old Record)
    if (!HAS_PO) {
        document.getElementById('date_received').disabled = false;
        document.getElementById('hidden_date_received').disabled = true;
        document.getElementById('receipt_num').disabled = false;
        document.getElementById('hidden_receipt_num').disabled = true;
    }
    if (!(HAS_PO && HAS_ITEM)) {
        document.getElementById('vendor_name').disabled = false;
        document.getElementById('hidden_vendor_name').disabled = true;
        document.getElementById('description_on_po').disabled = false;
        document.getElementById('hidden_desc').disabled = true;
    }
    if (!(HAS_PO && HAS_ITEM && QTY_RECV > 0)) {
        document.getElementById('uop').disabled = false;
        document.getElementById('hidden_uop').disabled = true;
    }

    document.getElementById('btnEdit').style.display = 'none';
    document.getElementById('editFooter').style.display = 'block';
}

async function submit_po_update(e) {
    e.preventDefault();
    if (!confirm('คุณยืนยันการบันทึกการแก้ไขหรือไม่?')) return;

    var btn = document.getElementById('btnUpdate');
    btn.disabled = true;
    btn.innerText = 'UPDATING...';

    var formData = new FormData(document.getElementById('frmPoDetail'));

    try {
        const res = await fetch('model/po_receive_model.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
        if (result.status === 'success') {
            location.reload();
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    } finally {
        btn.disabled = false;
        btn.innerText = 'UPDATE RECORD (บันทึกการแก้ไข)';
    }
}

async function delete_record() {
    if (!confirm('คุณยืนยันการลบข้อมูลเอกสาร <?php echo htmlspecialchars($record_num); ?> หรือไม่?')) return;

    var formData = new FormData();
    formData.append('action', 'po_delete');
    formData.append('func', '<?php echo htmlspecialchars($folder_func); ?>');
    formData.append('record_num', '<?php echo htmlspecialchars($record_num); ?>');

    try {
        const res = await fetch('model/po_receive_model.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
        if (result.status === 'success') {
            window.location.assign('purchase_order_receive_mats.php?func=<?php echo urlencode($folder_func); ?>&tab=COMPLETED');
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    }
}
</script>
</body>
</html>