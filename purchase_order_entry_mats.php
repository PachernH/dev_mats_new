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
if (!$is_authorized) {
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

// กรณีรับพารามิเตอร์จาก PO (mode = po หรือ poreceiv)
$mode   = $_GET['mode'] ?? '';
$poData = [];
if ($mode === 'po' || $mode === 'poreceiv') {
    $poData = $poModel->po_get_detail($mode, $_GET) ?? [];
}
$hasPO = !empty($poData['PONUM']) && !empty($poData['ITEMNUM']);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - New PO Receiving</title>
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
        .btn-save-rc {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13.5px;
            padding: 10px 28px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-save-rc:hover { background-color: #047857; color: #ffffff; }
        .btn-back {
            background-color: #64748b; color: #ffffff; font-weight: 600; font-size: 13px;
            padding: 8px 18px; border-radius: 8px; text-decoration: none;
        }
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
                        New Purchase Order Receiving (สร้างรายการรับใหม่)
                        <span class="badge" style="background-color:#2563eb; font-size:11px; margin-left:8px;">NEW RECORD</span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                <a href="purchase_order_receive_mats.php?func=<?php echo $folder_func; ?>" class="btn-back">
                     ⬅️ BACK TO LIST
                </a>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                    * เลขที่เอกสาร (RD-<?php echo date('y'); ?>-XXXX) จะถูกสร้างอัตโนมัติเมื่อกดบันทึก
                </span>
            </div>

            <form id="frmPoEntry" onsubmit="submit_po_save(event)">
                <input type="hidden" name="action" value="po_save">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                <input type="hidden" name="receiver_num" value="<?php echo htmlspecialchars($poData['RECEIVERNUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="uom" value="<?php echo htmlspecialchars($poData['UOM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <!-- Card 1: Document & PO Info -->
                <div class="dashboard-card">
                    <div class="section-title">1. ข้อมูลอ้างอิงเอกสารและใบสั่งซื้อ (Document & PO Reference)</div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Record No. (Auto)</label>
                                <input type="text" class="form-control" value="RD-<?php echo date('y'); ?>-XXXX" readonly style="color:#059669; font-weight:700;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>PO Number</label>
                                <input type="text" name="po_num" class="form-control" value="<?php echo htmlspecialchars($poData['PONUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPO ? 'readonly' : ''; ?> oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Seq No.</label>
                                <input type="number" name="seq_num" class="form-control" value="<?php echo htmlspecialchars($poData['SEQNUM'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPO ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Line No.</label>
                                <input type="number" name="line_number" class="form-control" value="<?php echo htmlspecialchars($poData['LINENUMBER'] ?? '1', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPO ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Receipt No. (ครั้งที่รับ)</label>
                                <select name="receipt_num" class="form-control">
                                    <?php 
                                    $defRcp = intval($poData['RECEIPTNUM'] ?? 1);
                                    for ($i = 1; $i <= 100; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i === $defRcp ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Receive Date *</label>
                                <input type="date" name="date_received" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Receive Time *</label>
                                <input type="time" name="time_received" step="1" class="form-control" value="<?php echo date('H:i:s'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Invoice No.</label>
                                <input type="text" name="invoice_num" class="form-control" value="<?php echo htmlspecialchars($poData['INVOICENUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Delivery No.</label>
                                <input type="text" name="delivery_num" class="form-control" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Item & Quantity Info -->
                <div class="dashboard-card">
                    <div class="section-title">2. รายละเอียดสินค้า ผู้ขาย และจำนวนรับ (Item, Vendor & Quantity)</div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Item No.</label>
                                <input type="text" name="item_num" class="form-control" value="<?php echo htmlspecialchars($poData['ITEMNUM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPO ? 'readonly' : ''; ?> oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Description on PO</label>
                                <input type="text" name="description_on_po" class="form-control" value="<?php echo htmlspecialchars($poData['DESCRIPTIONONPO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hasPO ? 'readonly' : ''; ?> oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Vendor Name</label>
                                <select name="vendor_name" id="vendor_name" class="form-control" onchange="syncVendorId()" <?php echo $hasPO ? 'disabled' : ''; ?>>
                                    <option value="">-- SELECT VENDOR --</option>
                                    <?php 
                                    $curVendor = trim($poData['VENDORNAME'] ?? '');
                                    foreach ($master['vendors'] as $v): 
                                        $vName = trim($v['NAME']);
                                        $vId   = trim($v['VENDORID']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($vName, ENT_QUOTES, 'UTF-8'); ?>" data-id="<?php echo htmlspecialchars($vId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($curVendor === $vName) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($vName, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($hasPO): ?>
                                    <input type="hidden" name="vendor_name" value="<?php echo htmlspecialchars($curVendor, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="vendor_id" id="vendor_id" value="<?php echo htmlspecialchars($poData['VENDORID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Qty Requested</label>
                                <input type="number" step="0.01" name="qty_requested" class="form-control text-right" value="<?php echo number_format((float)($poData['QTYREQUESTED'] ?? 0), 2, '.', ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Qty Previous</label>
                                <input type="number" step="0.01" name="qty_previous" class="form-control text-right" value="<?php echo number_format((float)($poData['QTYPREVIOUS'] ?? 0), 2, '.', ''); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label style="color:#059669;">Qty Received *</label>
                                <input type="number" step="0.01" name="qty_received" class="form-control text-right" value="<?php echo number_format((float)($poData['QTYRECEIVED'] ?? 0), 2, '.', ''); ?>" style="border-color:#059669; font-weight:700;" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Unit (UOP)</label>
                                <select name="uop" class="form-control">
                                    <?php 
                                    $curUop = trim($poData['UOP'] ?? '');
                                    foreach ($master['uops'] as $u): 
                                        $unit = trim($u['UNIT']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($curUop === $unit) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Item Type</label>
                                <input type="text" name="item_type" class="form-control" value="<?php echo strtoupper(htmlspecialchars($poData['ITEMTYPE'] ?? '', ENT_QUOTES, 'UTF-8')); ?>" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Warehouse</label>
                                <input type="text" name="receive_warehouse" class="form-control" value="<?php echo htmlspecialchars($poData['RECEIVEWAREHOUSE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>ABC Class</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($poData['WAREHOUSE_INFO']['ABCCLASS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Reorder Point</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($poData['WAREHOUSE_INFO']['REORDERPOINT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Reorder Qty</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($poData['WAREHOUSE_INFO']['REORDERQTY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Destination ID</label>
                                <input type="text" name="dest_id" class="form-control" value="<?php echo htmlspecialchars($poData['DESTID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($poData['DUEDATE_FMT'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Inspection, Comments & Print Options -->
                <div class="dashboard-card">
                    <div class="section-title">3. ผลการตรวจรับ หมายเหตุ และตัวเลือกการพิมพ์ (Inspection & Print Options)</div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Received Result *</label>
                                <select name="received_result" class="form-control" style="font-weight:700;">
                                    <option value="ACCEPT">ACCEPT</option>
                                    <option value="REJECT">REJECT</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Reason Code</label>
                                <select name="received_reason" class="form-control">
                                    <option value="NONE">NONE</option>
                                    <?php foreach ($master['reasons'] as $r): 
                                        $rcode = trim($r['REASON_CODE']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rcode, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Container No. (สำหรับสร้าง Barcode)</label>
                                <input type="text" name="container" class="form-control" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Warehouse Comment</label>
                                <textarea name="warehouse_comment" class="form-control" rows="2" style="height:auto;"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Enduser Comment</label>
                                <textarea name="enduser_comment" class="form-control" rows="2" style="height:auto;"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Purchase Comment</label>
                                <textarea name="purchase_comment" class="form-control" rows="2" style="height:auto;"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <div class="col-md-2">
                            <label class="checkbox-inline" style="font-weight:700; color:#1e293b; margin-top:8px;">
                                <input type="checkbox" name="print_label" value="1"> พิมพ์ Label
                            </label>
                        </div>
                        <div class="col-md-2">
                            <select name="label_form" class="form-control">
                                <option value="7x5">ขนาด 7x5 cm</option>
                                <option value="10x15">ขนาด 10x15 cm</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="no_copy" class="form-control" value="1" min="1" title="จำนวนดวง">
                        </div>
                        <div class="col-md-3">
                            <label class="checkbox-inline" style="font-weight:700; color:#059669; margin-top:8px;">
                                <input type="checkbox" name="print_gate_pass" value="1"> พิมพ์ใบผ่านประตู (Gate Pass)
                            </label>
                        </div>
                        <div class="col-md-3">
                            <label class="checkbox-inline" style="font-weight:700; color:#dc2626; margin-top:8px;">
                                <input type="checkbox" name="print_reject" value="1"> พิมพ์ใบแจ้งคืน (Reject Form)
                            </label>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 25px;">
                        <button type="submit" id="btnSave" class="btn-save-rc">
                            SAVE RECORD (บันทึกข้อมูล)
                        </button>
                    </div>
                </div>
            </form>

            <!-- ประวัติการรับของ PO นี้ (แทน mfgRecord) -->
            <?php if (!empty($poData['HISTORY_LIST'])): ?>
            <div class="dashboard-card">
                <div class="section-title">ประวัติการรับสินค้าของ PO / Item นี้ (Receiving History)</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" style="font-size:13px;">
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
                            <?php foreach ($poData['HISTORY_LIST'] as $h): ?>
                            <tr>
                                <td class="text-center"><strong><?php echo htmlspecialchars($h['RECORDNUM']); ?></strong></td>
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
function syncVendorId() {
    var sel = document.getElementById('vendor_name');
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('vendor_id').value = opt ? (opt.getAttribute('data-id') || '') : '';
}

async function submit_po_save(e) {
    e.preventDefault();
    if (!confirm('คุณยืนยันการบันทึกข้อมูลการรับสินค้าหรือไม่?')) return;

    var btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerText = 'SAVING...';

    var formData = new FormData(document.getElementById('frmPoEntry'));

    try {
        const res = await fetch('model/po_receive_model.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
        if (result.status === 'success') {
            if (result.print_urls && result.print_urls.length > 0) {
                result.print_urls.forEach(url => window.open(url, '_blank'));
            }
            window.location.assign('purchase_order_receive_mats.php?func=<?php echo urlencode($folder_func); ?>&tab=COMPLETED');
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    } finally {
        btn.disabled = false;
        btn.innerText = 'SAVE RECORD (บันทึกข้อมูล)';
    }
}
</script>
</body>
</html>