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
$current_dept = strtoupper(trim($department_func));
$is_invoice_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');
$invno = isset($_GET['invno']) ? htmlspecialchars(trim($_GET['invno']), ENT_QUOTES, 'UTF-8') : '';

if (empty($invno)) {
    header("Location: invoice_list_mats.php?func=" . urlencode($folder_func));
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Invoice Detail - <?php echo $invno; ?></title>
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
            display: flex; justify-content: space-between; align-items: center;
        }
        .form-group { margin-bottom: 10px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        .form-control { height: 34px; font-size: 12.5px; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; padding: 4px 8px; }
        .form-control[readonly], .form-control:disabled { background-color: #f1f5f9; color: #334155; }
        .calc-box { background-color: #eff6ff !important; color: #1e3a8a !important; font-weight: 700 !important; border-color: #bfdbfe !important; }
        .summary-panel { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px; }
        .quota-panel { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-top: 12px; }
        .addr-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .addr-box h6 { font-size: 11.5px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; }
        .table-custom th { background-color: #f1f5f9; font-size: 11.5px; font-weight: 700; color: #475569; text-transform: uppercase; white-space: nowrap; }
        .table-custom td { font-size: 12.5px; vertical-align: middle !important; white-space: nowrap; }
        .row-selected { background-color: #dbeafe !important; }

        /* Modal Styling & Fix Overflow */
        .custom-modal .modal-content {
            background-color: #ffffff !important;
            border-radius: 10px;
            border: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .custom-modal .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 20px;
        }
        .custom-modal .modal-body {
            background-color: #ffffff;
            padding: 16px 20px;
            max-height: none !important;
            overflow: visible !important;
        }
        .custom-modal .modal-footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 12px 20px;
        }
        .modal-filter-bar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 12px;
        }
        .modal-table-scroll {
            max-height: 52vh;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background-color: #ffffff;
        }
        .modal-table-scroll table {
            margin-bottom: 0 !important;
            width: 100%;
        }
        .modal-table-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #1e293b !important;
            color: #ffffff !important;
            border-bottom: 2px solid #0f172a !important;
        }
        .row-so-added {
            background-color: #f8fafc !important;
            color: #94a3b8;
        }
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
                        Invoice Detail : <span style="color:#047857;"><?php echo $invno; ?></span>
                        <span id="badgeStatus" class="badge" style="font-size:11px; margin-left:8px;">OP</span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- STICKY TOP ACTION BAR -->
            <div class="dashboard-card" style="padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50;">
                <div>
                    <a href="invoice_list_mats.php?func=<?php echo $folder_func; ?>" class="btn btn-default btn-sm" style="font-weight:600;">
                         ⬅️ Back to List
                    </a>
                    <span id="lblCustomerSummary" style="margin-left: 12px; font-weight: 700; color: #1e293b;"></span>
                </div>
                <div id="actionButtonsGroup" style="display: flex; gap: 8px;">
                    <?php if ($is_invoice_authorized): ?>
                        <button type="button" id="btnEditHead" class="btn btn-warning btn-sm" style="font-weight:700;">
                            EDIT HEADING
                        </button>
                        <button type="button" id="btnSaveHead" class="btn btn-primary btn-sm hidden" style="font-weight:700;">
                            SAVE HEADING
                        </button>
                        <button type="button" id="btnCancelHead" class="btn btn-default btn-sm hidden" style="font-weight:700;">
                            CANCEL
                        </button>
                        <button type="button" id="btnDeleteHead" class="btn btn-danger btn-sm" style="font-weight:700;">
                            DEL INVOICE
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- SECTION 1: INVOICE HEADING & FINANCIAL SUMMARY                  -->
            <!-- =============================================================== -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>1. Invoice Heading &amp; Financial Summary</span>
                    <span style="font-size:11px; color:#64748b;">(ส่วนหัวเอกสารและสรุปยอดเงิน)</span>
                </div>
                <div class="row">
                    <!-- Left: General & Shipping Info (8 Cols) -->
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Invoice No.</label>
                                    <input type="text" id="INVOICE_NO" class="form-control" value="<?php echo $invno; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Customer ID</label>
                                    <input type="text" id="CSTMSPPL_ID" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Invoice Date</label>
                                    <input type="date" id="INVOICE_DATE" class="form-control head-editable" disabled>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Shipment Date</label>
                                    <input type="date" id="SHIPMENT_DATE" class="form-control head-editable" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Material In (Print)</label>
                                    <select id="INV1_MATERIALINPRN" class="form-control head-editable" disabled>
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Material In (Item)</label>
                                    <input type="text" id="INV1_MATERIALIN" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Account Code</label>
                                    <input type="text" id="ACCOUNT_CODE" class="form-control head-editable" disabled>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Booking No.</label>
                                    <select id="BOOK_NO" class="form-control head-editable" disabled>
                                        <option value="">-- No Booking --</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select id="CURRENCY_ID" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>VAT (%)</label>
                                    <select id="INV1_TAXPERCENT" class="form-control head-editable" disabled>
                                        <option value="0">0%</option>
                                        <option value="7">7%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Payment Term</label>
                                    <select id="PAYMENT_ID" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Shipment Term</label>
                                    <select id="SHIPMENT_ID" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Bank</label>
                                    <select id="BANK_ID" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Letter of Credit</label>
                                    <input type="text" id="LETTER_CREDIT" class="form-control head-editable" disabled>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Shipped By</label>
                                    <input type="text" id="INV1_SHIPPEDBY" class="form-control head-editable" disabled>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>FOB Value @</label>
                                    <input type="text" id="FOB_VALUE" class="form-control head-editable" disabled>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Port of Loading</label>
                                    <select id="INV1_PORTLOADING" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Port of Discharge</label>
                                    <select id="INV1_PORTDISCHARGE" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Place Destination</label>
                                    <select id="INV1_PLACEDESTINATION" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Country Destination</label>
                                    <select id="INV1_COUNTRYDESTINATION" class="form-control head-editable" disabled></select>
                                </div>
                            </div>
                        </div>

                        <!-- Weight & Package Totals -->
                        <div class="row" style="margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1;">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Package Qty.</label>
                                    <input type="text" id="INV1_PACKAGE" class="form-control text-right calc-box" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Net Piece</label>
                                    <input type="text" id="INV1_NETPIECE" class="form-control text-right calc-box" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Net Weight (kg)</label>
                                    <input type="text" id="INV1_NETWEIGHT" class="form-control text-right calc-box" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Gross Weight (kg)</label>
                                    <input type="text" id="INV1_GROSSWEIGHT" class="form-control text-right calc-box" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Financial Calculation Panel (4 Cols) -->
                    <div class="col-md-4">
                        <div class="summary-panel">
                            <div class="row">
                                <div class="col-xs-6"><label style="font-size:11px;">Total Amount</label></div>
                                <div class="col-xs-6"><input type="text" id="INV1_TOTALAMOUNT" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">(+) Transportation</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_TRANSPORTATION" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">(-) Discount</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_DISCOUNT" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Subtotal Amount</label></div>
                                <div class="col-xs-6"><input type="text" id="SUBTOTAL_AMOUNT" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">VAT Amount</label></div>
                                <div class="col-xs-6"><input type="text" id="TAX_AMOUNT" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Oversea Freight</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_OVERSEAFREIGHT" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Insurance</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_INSURANCE" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Subtotal FOB</label></div>
                                <div class="col-xs-6"><input type="text" id="SUBTOTAL_FOB" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Soncap Certificate</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_CERTIFICATE" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px; color:#1e3a8a;">Grand Total</label></div>
                                <div class="col-xs-6"><input type="text" id="GRAND_TOTAL_AMOUNT" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <hr style="margin:6px 0;">
                            <div class="row">
                                <div class="col-xs-6"><label style="font-size:11px;">M Subtotal</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_MTOTALAMOUNT" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">M Grand Total</label></div>
                                <div class="col-xs-6"><input type="number" step="0.01" id="INV1_MGRANDTOTALAMOUNT" class="form-control input-sm text-right head-editable fin-input" disabled></div>
                            </div>
                            <div class="row" style="margin-top:4px;">
                                <div class="col-xs-6"><label style="font-size:11px;">Cal. Insurance</label></div>
                                <div class="col-xs-6"><input type="text" id="CAL_INSURANCE" class="form-control input-sm text-right calc-box" readonly></div>
                            </div>
                            <div style="margin-top:8px;">
                                <label style="font-size:11.5px; color:#dc2626; font-weight:700; cursor:pointer;">
                                    <input type="checkbox" id="RECAL_FOB" class="head-editable" disabled> Recalculate FOB Price on Save
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- SECTION 2: ITEM DETAIL (INVCPRCS2)                              -->
            <!-- =============================================================== -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>2. Item Detail (คลิกที่แถวเพื่อดูโควตา Sale Order และเลือกตัดสต็อก)</span>
                    <?php if ($is_invoice_authorized): ?>
                        <button type="button" id="btnOpenSOModal" class="btn btn-success btn-sm" style="font-weight:700;">
                            + ADD ITEM FROM SALE ORDER
                        </button>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-custom" id="itemDetailTable">
                        <thead>
                            <tr>
                                <th class="text-center">Item</th>
                                <th>Sale Order</th>
                                <th>PO No.</th>
                                <th class="text-center">Prod</th>
                                <th>Alloy / Temper / SG / MG</th>
                                <th>Size (T x W x L)</th>
                                <th class="text-right">Order Quota</th>
                                <th class="text-right">Inv. Weight</th>
                                <th class="text-right">Inv. Price (Locked)</th>
                                <th class="text-right">FOB Price</th>
                                <th>UOM</th>
                                <th>Skid No.</th>
                                <th>Description</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemTableBody"></tbody>
                    </table>
                </div>

                <!-- Selected Item Sale Order Quota Info -->
                <div id="selectedItemQuotaBox" class="quota-panel hidden">
                    <div style="font-size:12px; font-weight:700; color:#14532d; margin-bottom:6px;">
                        รายละเอียดโควตา Sale Order ของ Item <span id="qItemNo"></span> (<span id="qSONo"></span>)
                    </div>
                    <div class="row" style="font-size:12px;">
                        <div class="col-md-2"><strong>Tolerance:</strong> <span id="qTol"></span></div>
                        <div class="col-md-2"><strong>Order Qty:</strong> <span id="qOrdQty"></span></div>
                        <div class="col-md-2"><strong>Min Require:</strong> <span id="qMinQty"></span></div>
                        <div class="col-md-2"><strong>Max Allowed:</strong> <span id="qMaxQty"></span></div>
                        <div class="col-md-2"><strong>Total Invoiced:</strong> <span id="qInvQty" style="color:#047857; font-weight:700;"></span></div>
                        <div class="col-md-2"><strong>SO Status:</strong> <span id="qStatus" class="badge"></span></div>
                    </div>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- SECTION 3: PRODUCT DETAIL & STOCK DEDUCTION (INVCPRCS3)         -->
            <!-- =============================================================== -->
            <div class="dashboard-card">
                <div class="section-title">
                    <div>
                        <span>3. Product Detail (รายละเอียดม้วน/พาเลทที่ตัดสต็อก)</span>
                        <span id="lblSelectedItemBadge" class="badge" style="background:#2563eb; margin-left:8px;">Showing All Items</span>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" id="btnShowAllProducts" class="btn btn-default btn-xs" style="font-weight:700;">Show All</button>
                        <?php if ($is_invoice_authorized): ?>
                            <button type="button" id="btnOpenStockModal" class="btn btn-primary btn-sm" style="font-weight:700;" disabled>
                                + SELECT STOCK / PRODUCTS
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-custom" id="productDetailTable">
                        <thead>
                            <tr>
                                <th class="text-center">Item</th>
                                <th class="text-center">Skid No.</th>
                                <th>Product No.</th>
                                <th>Coil No.</th>
                                <th class="text-center">Prod</th>
                                <th>Spec (Alloy/Temper/Size)</th>
                                <th class="text-right">Pkg</th>
                                <th class="text-right">Net Pc</th>
                                <th class="text-right">Net Wt (kg)</th>
                                <th class="text-right">Gross Wt (kg)</th>
                                <th class="text-right">CBM (m³)</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="productTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- =============================================================== -->
            <!-- SECTION 4 & 5: ADDRESS (READ-ONLY) & MILL CERTIFICATE REMARKS   -->
            <!-- =============================================================== -->
            <div class="row">
                <!-- Section 4: Address -->
                <div class="col-md-5">
                    <div class="dashboard-card">
                        <div class="section-title">4. Address (Consignee / Notify - Read Only)</div>
                        <div class="addr-box">
                            <h6>CONSIGNEE <span id="lblCustLoc" class="badge" style="background:#1d4ed8;"></span></h6>
                            <div id="viewConsignee" style="font-size:12px; line-height:1.5; color:#1e293b;"></div>
                        </div>
                        <div class="addr-box">
                            <h6>NOTIFY PARTY</h6>
                            <div id="viewNotify" style="font-size:12px; line-height:1.5; color:#1e293b;"></div>
                        </div>
                        <div class="addr-box" style="margin-bottom:0;">
                            <h6>ALSO NOTIFY</h6>
                            <div id="viewAlsoNotify" style="font-size:12px; line-height:1.5; color:#1e293b;"></div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Shipping Mark, Invoice Remark & Mill Certificate Remark -->
                <div class="col-md-7">
                    <div class="dashboard-card">
                        <div class="section-title">5. Mill Certificate Remark &amp; Invoice Remarks</div>
                        <div class="row">
                            <div class="col-md-6">
                                <label style="font-size:11.5px; font-weight:700; color:#0f172a;">Mill Certificate Remark (MC 1 - 8)</label>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <div class="input-group input-group-sm" style="margin-bottom:4px;">
                                        <span class="input-group-addon" style="font-size:11px; font-weight:700;">MC<?php echo $i; ?></span>
                                        <input type="text" id="INV1_MCREMARK<?php echo $i; ?>" class="form-control head-editable" maxlength="160" disabled>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="col-md-6">
                                <label style="font-size:11.5px; font-weight:700; color:#0f172a;">Invoice Remark (1 - 7)</label>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <div class="input-group input-group-sm" style="margin-bottom:4px;">
                                        <span class="input-group-addon" style="font-size:11px; font-weight:700;">R<?php echo $i; ?></span>
                                        <input type="text" id="INV1_REMARK<?php echo $i; ?>" class="form-control head-editable" maxlength="160" disabled>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <hr style="margin:10px 0;">
                        <label style="font-size:11.5px; font-weight:700; color:#0f172a;">Shipping Mark (1 - 8)</label>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="text" id="INV1_SHIPPINGMARK" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK1" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK2" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK3" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                            </div>
                            <div class="col-md-6">
                                <input type="text" id="INV1_SHIPPINGMARK4" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK5" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK6" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                                <input type="text" id="INV1_SHIPPINGMARK7" class="form-control input-sm head-editable" style="margin-bottom:4px;" maxlength="50" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <input type="hidden" id="isAuthorized" value="<?php echo $is_invoice_authorized ? '1' : '0'; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL 1: ADD ITEM FROM SALE ORDER (TOP 200 & SERVER SEARCH)             -->
<!-- ======================================================================= -->
<div class="modal fade custom-modal" id="modalAddSO" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg" style="width: 94%; max-width: 1320px; margin: 25px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" style="font-size: 24px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700; color: #1e293b;">
                    Select Sale Order Item (เลือกรายการสั่งซื้อที่เปิดอยู่)
                    <span id="lblSOCountBadge" class="badge" style="background-color: #2563eb; margin-left: 8px;">0 รายการ</span>
                    <small style="font-size: 12px; color: #64748b; margin-left: 8px;">(แสดงสูงสุด 200 รายการล่าสุด หากไม่พบให้พิมพ์ค้นหาด้านล่าง)</small>
                </h4>
            </div>

            <div class="modal-body">
                <!-- Filter Bar -->
                <div class="modal-filter-bar">
                    <div class="row">
                        <div class="col-md-4">
                            <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">ค้นหาจากฐานข้อมูล (SO No. / PO No. / Alloy / Size)</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="soModalSearch" class="form-control"
                                       placeholder="พิมพ์คำค้นหา เช่น SO-26, 720433, 8011, 2.400...">
                                <span class="input-group-btn">
                                    <button type="button" id="btnSearchSOServer" class="btn btn-primary" style="font-weight: 700;">
                                        ค้นหา
                                    </button>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">ประเภท (Category)</label>
                            <select id="soModalCategoryFilter" class="form-control input-sm">
                                <option value="">-- ทั้งหมด (All) --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 3px;">กลุ่มสินค้า (Prod)</label>
                            <select id="soModalProdFilter" class="form-control input-sm">
                                <option value="">-- ทั้งหมด (All) --</option>
                            </select>
                        </div>
                        <div class="col-md-4" style="padding-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <label style="font-size: 12px; font-weight: 600; color: #334155; cursor: pointer; margin: 0;">
                                <input type="checkbox" id="chkHideAddedSO"> ซ่อนรายการที่เพิ่มแล้ว
                            </label>
                            <button type="button" id="btnResetSOFilter" class="btn btn-default btn-xs" style="font-weight: 600;">
                                ล้างตัวกรอง
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Scrollable Table Container -->
                <div class="modal-table-scroll">
                    <table class="table table-bordered table-hover table-condensed table-custom">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 38px;">
                                    <input type="checkbox" id="chkAllSO" title="เลือกทั้งหมดที่แสดงอยู่">
                                </th>
                                <th style="width: 115px;">SO No.</th>
                                <th class="text-center" style="width: 55px;">Item</th>
                                <th class="text-center" style="width: 65px;">Cat</th>
                                <th style="width: 130px;">PO No.</th>
                                <th class="text-center" style="width: 55px;">Prod</th>
                                <th style="width: 110px;">Alloy / Temper</th>
                                <th style="width: 165px;">Size (T x W x L)</th>
                                <th class="text-right" style="width: 95px;">Order Wt</th>
                                <th class="text-right" style="width: 95px;">Invoiced Wt</th>
                                <th class="text-right" style="width: 95px;">Max Quota</th>
                                <th class="text-right" style="width: 85px;">Price</th>
                                <th class="text-center" style="width: 85px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="soLookupBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 13px; font-weight: 700; color: #1e293b;">
                    เลือกไว้ทั้งหมด: <span id="lblSelectedSOCount" style="color: #059669; font-size: 16px;">0</span> รายการ
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600;">ปิดหน้าต่าง</button>
                    <button type="button" id="btnConfirmMultiSO" class="btn btn-success" style="font-weight: 700;" disabled>
                        + เพิ่มรายการที่เลือกเข้า Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- MODAL 2: MULTI-SELECT STOCK PRODUCTS (PACKPROD1)                        -->
<!-- ======================================================================= -->
<div class="modal fade custom-modal" id="modalSelectStock" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-lg" style="width: 94%; max-width: 1320px; margin: 25px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" style="font-size: 24px;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700; color: #1e293b;">
                    Select Stock Products for Item <span id="modalStockItemNo" class="text-primary"></span>
                </h4>
            </div>

            <div class="modal-body">
                <div class="alert alert-info" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding: 10px 15px;">
                    <div>
                        <strong>Sale Order Quota:</strong> <span id="lblModalQuotaInfo"></span>
                    </div>
                    <div>
                        <strong>Selected:</strong> <span id="lblModalSelectedCount" style="font-size:15px;">0</span> รายการ |
                        <strong>Weight:</strong> <span id="lblModalSelectedWeight" style="font-size:15px;">0.00</span> kg
                    </div>
                </div>

                <div class="modal-filter-bar">
                    <div class="row">
                        <div class="col-md-4">
                            <label style="font-size: 11px; font-weight: 700; color: #475569;">ค้นหา Product No. / Coil No.</label>
                            <input type="text" id="stockModalSearch" class="form-control input-sm" placeholder="พิมพ์เลขม้วนหรือรหัสแพ็ค...">
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 11px; font-weight: 700; color: #475569;">SKID NO. (สำหรับรายการที่เลือก)</label>
                            <input type="text" id="modalSkidNo" class="form-control input-sm" value="00" maxlength="3">
                        </div>
                        <div class="col-md-6" style="padding-top: 22px;">
                            <label style="font-size: 12px; font-weight: 700; color: #047857; cursor: pointer; margin: 0;">
                                <input type="checkbox" id="modalCloseSO"> ปิดสถานะ Sale Order Item นี้เป็น CP (Complete) ทันทีเมื่อบันทึก
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-table-scroll">
                    <table class="table table-bordered table-hover table-condensed table-custom">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" id="chkAllStock"></th>
                                <th>Product No.</th>
                                <th>Coil No.</th>
                                <th class="text-center">Status</th>
                                <th>Alloy / Temper / SG / MG</th>
                                <th>Size (T x W x L)</th>
                                <th class="text-right">Net Pc</th>
                                <th class="text-right">Net Wt (kg)</th>
                                <th class="text-right">Gross Wt (kg)</th>
                                <th>Spec Check</th>
                            </tr>
                        </thead>
                        <tbody id="stockLookupBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="btnConfirmAddProducts" class="btn btn-primary" style="font-weight:700;">
                    CONFIRM &amp; DEDUCT STOCK
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
<script src="model/invoice_detail_mats.js"></script>
</body>
</html>