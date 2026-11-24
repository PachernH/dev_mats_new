<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$is_invoice_authorized = ($current_func === 'WH' || $department_func === 'IT' || $department_func === 'FN');
if (!$is_invoice_authorized) {
    header("Location: invoice_list_mats.php?func=" . urlencode($folder_func));
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 20px 24px; margin-bottom: 20px; border: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 14px; font-weight: 700; color: #1e293b; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 16px;
        }
        .form-group label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .form-control { height: 36px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; }
        .form-control[readonly], .form-control:disabled { background-color: #f1f5f9; color: #475569; }
        .addr-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; height: 100%; }
        .addr-box h6 { font-size: 12px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'A6'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Create New Invoice <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">NEW RECORD</span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Action Bar -->
            <div class="dashboard-card" style="padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <a href="invoice_list_mats.php?func=<?php echo $folder_func; ?>" class="btn btn-default btn-sm" style="font-weight:600;">
                         ⬅️ Back to List
                    </a>
                    <span style="margin-left: 12px; font-weight: 700; color: #475569;">กรอกข้อมูลหัวเอกสาร Invoice Heading เพื่อสร้างเลขที่เอกสาร</span>
                </div>
                <div>
                    <button type="button" id="btnCreateSave" class="btn btn-success" style="font-weight:700; padding: 8px 24px;">
                        SAVE &amp; CONTINUE TO ITEMS 
                    </button>
                </div>
            </div>

            <form id="entryForm">
                <!-- 1. INVOICE HEADING & SHIPPING -->
                <div class="dashboard-card">
                    <div class="section-title">1. Invoice Heading &amp; Shipment Information</div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Invoice Prefix *</label>
                                <select id="PREFIX" class="form-control">
                                    <option value="MAT-Z-">MAT-Z- (Default)</option>
                                    <option value="MAT-C-">MAT-C-</option>
                                    <option value="MAT-E-">MAT-E-</option>
                                    <option value="MAT-V-">MAT-V- (OVMI)</option>
                                    <option value="MAT-H-">MAT-H-</option>
                                    <option value="MAT-N-">MAT-N-</option>
                                    <option value="MAT-T-">MAT-T-</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Customer ID *</label>
                                <select id="CSTMSPPL_ID" class="form-control" required>
                                    <option value="">-- Select Customer --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Invoice Date *</label>
                                <input type="date" id="INVOICE_DATE" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Shipment Date *</label>
                                <input type="date" id="SHIPMENT_DATE" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Material In (BOI)</label>
                                <select id="INV1_MATERIALINPRN" class="form-control">
                                    <option value="">-- Select --</option>
                                    <option value="BOI-MAT1">BOI-MAT1</option>
                                    <option value="BOI-MAT2">BOI-MAT2</option>
                                    <option value="BOI-GRS1">BOI-GRS1</option>
                                    <option value="BOI-IMP1">BOI-IMP1</option>
                                    <option value="BOI-IMP2">BOI-IMP2</option>
                                    <option value="NONE-BOI">NONE-BOI</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Account Code</label>
                                <input type="text" id="ACCOUNT_CODE" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Currency</label>
                                <select id="CURRENCY_ID" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>VAT (%)</label>
                                <select id="INV1_TAXPERCENT" class="form-control">
                                    <option value="0">0%</option>
                                    <option value="7">7%</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Payment Term</label>
                                <select id="PAYMENT_ID" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Shipment Term</label>
                                <select id="SHIPMENT_ID" class="form-control"></select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Booking No. (Export Only)</label>
                                <select id="BOOK_NO" class="form-control" disabled>
                                    <option value="">-- No Booking --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Bank</label>
                                <select id="BANK_ID" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Letter of Credit (L/C)</label>
                                <input type="text" id="LETTER_CREDIT" class="form-control" oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Shipped By</label>
                                <input type="text" id="INV1_SHIPPEDBY" class="form-control" oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Port of Loading</label>
                                <select id="INV1_PORTLOADING" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Port of Discharge</label>
                                <select id="INV1_PORTDISCHARGE" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Place Destination</label>
                                <select id="INV1_PLACEDESTINATION" class="form-control"></select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Country Destination</label>
                                <select id="INV1_COUNTRYDESTINATION" class="form-control"></select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Charges -->
                    <div class="row" style="background:#f8fafc; padding-top:10px; border-radius:8px; margin: 5px 0;">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Transportation</label>
                                <input type="number" step="0.01" id="INV1_TRANSPORTATION" class="form-control text-right" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Discount</label>
                                <input type="number" step="0.01" id="INV1_DISCOUNT" class="form-control text-right" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Oversea Freight</label>
                                <input type="number" step="0.01" id="INV1_OVERSEAFREIGHT" class="form-control text-right" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Insurance</label>
                                <input type="number" step="0.01" id="INV1_INSURANCE" class="form-control text-right" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Soncap Certificate</label>
                                <input type="number" step="0.01" id="INV1_CERTIFICATE" class="form-control text-right" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>FOB Value @</label>
                                <input type="text" id="FOB_VALUE" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. CUSTOMER ADDRESS (READ-ONLY) -->
                <div class="dashboard-card">
                    <div class="section-title">2. Customer Address (Read-Only from Master)</div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="addr-box">
                                <h6>CONSIGNEE <span id="lblLocation" class="badge" style="background:#2563eb;"></span></h6>
                                <input type="text" id="CONSIGNEE_COMPANY" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="CONSIGNEE_ADDRESS1" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="CONSIGNEE_ADDRESS2" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="CONSIGNEE_ADDRESS3" class="form-control input-sm" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="addr-box">
                                <h6>NOTIFY PARTY</h6>
                                <input type="text" id="NOTIFY_COMPANY" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="NOTIFY_ADDRESS1" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="NOTIFY_ADDRESS2" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="NOTIFY_ADDRESS3" class="form-control input-sm" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="addr-box">
                                <h6>ALSO NOTIFY</h6>
                                <input type="text" id="ALSONOTIFY_COMPANY" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="ALSONOTIFY_ADDRESS1" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="ALSONOTIFY_ADDRESS2" class="form-control input-sm" style="margin-bottom:5px;" readonly>
                                <input type="text" id="ALSONOTIFY_ADDRESS3" class="form-control input-sm" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. SHIPPING MARK & REMARKS -->
                <div class="dashboard-card">
                    <div class="section-title">3. Shipping Mark, Invoice Remarks &amp; Mill Certificate Remarks</div>
                    <div class="row">
                        <div class="col-md-4">
                            <label style="font-size:12px; font-weight:700; color:#475569;">Shipping Mark (1 - 8)</label>
                            <input type="text" id="INV1_SHIPPINGMARK" class="form-control input-sm" style="margin-bottom:4px;" maxlength="50" placeholder="Main Mark">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <input type="text" id="INV1_SHIPPINGMARK<?php echo $i; ?>" class="form-control input-sm" style="margin-bottom:4px;" maxlength="50" placeholder="Line <?php echo $i; ?>">
                            <?php endfor; ?>
                        </div>
                        <div class="col-md-4">
                            <label style="font-size:12px; font-weight:700; color:#475569;">Invoice Remark (1 - 7)</label>
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <input type="text" id="INV1_REMARK<?php echo $i; ?>" class="form-control input-sm" style="margin-bottom:4px;" maxlength="160" placeholder="Remark <?php echo $i; ?>">
                            <?php endfor; ?>
                        </div>
                        <div class="col-md-4">
                            <label style="font-size:12px; font-weight:700; color:#475569;">Mill Certificate Remark (MC 1 - 8)</label>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <input type="text" id="INV1_MCREMARK<?php echo $i; ?>" class="form-control input-sm" style="margin-bottom:4px;" maxlength="160" placeholder="MC Remark <?php echo $i; ?>">
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>
<script src="model/invoice_entry_mats.js"></script>
</body>
</html>