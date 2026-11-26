<?php
// invoice_close_detail_mats.php
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
$is_invoice_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

$invno = isset($_GET['invno']) ? htmlspecialchars(strtoupper(trim($_GET['invno'])), ENT_QUOTES, 'UTF-8') : '';
if ($invno === '') {
    header("Location: invoice_close_list_mats.php?func=" . urlencode($folder_func));
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Invoice Detail (<?php echo $invno; ?>) - Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f1f5f9 !important; }

        /* Action Buttons (Override Light Bootstrap Theme) */
        .btn-back-list {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #ffffff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 9px 18px !important;
            border-radius: 8px !important;
            text-decoration: none !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }
        .btn-back-list:hover {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        .btn-edit-closing {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #2563eb !important;
            color: #ffffff !important;
            border: 1px solid #1d4ed8 !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            padding: 10px 22px !important;
            border-radius: 8px !important;
            text-decoration: none !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }
        .btn-edit-closing:hover {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 10px -1px rgba(37, 99, 235, 0.35);
        }

        /* Warning Box */
        .warning-banner {
            background-color: #fffbeb;
            border: 1px solid #f59e0b;
            border-left: 5px solid #d97706;
            color: #92400e;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 600;
        }

        /* Account Summary Strip */
        .account-strip {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: 18px 24px;
            margin-bottom: 22px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
        }
        .strip-item {
            border-right: 1px solid rgba(255,255,255,0.1);
            padding-right: 12px;
        }
        .strip-item:last-child { border-right: none; }
        .strip-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .strip-val {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }

        /* Clean Card Layout */
        .clean-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            margin-bottom: 22px;
        }
        .clean-card-header {
            background-color: #f8fafc;
            padding: 14px 22px;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .clean-card-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .clean-card-body {
            padding: 20px 22px;
        }

        /* Key-Value Info Table (Section 1) */
        .kv-table {
            width: 100%;
            border-collapse: collapse;
        }
        .kv-table tr { border-bottom: 1px solid #f1f5f9; }
        .kv-table tr:last-child { border-bottom: none; }
        .kv-table th {
            width: 34%;
            text-align: left;
            padding: 10px 12px 10px 0;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            vertical-align: top;
        }
        .kv-table td {
            padding: 10px 0;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            vertical-align: top;
        }

        /* Weight KPI Boxes (Section 2 Left) */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .kpi-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .kpi-box-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .kpi-box-value {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Consolas', 'Segoe UI', monospace;
        }
        .kpi-box-unit {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-left: 4px;
        }

        /* Financial Statement Table (Section 2 Right) */
        .fin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .fin-table td {
            padding: 8px 10px;
            font-size: 13.5px;
            border-bottom: 1px solid #f1f5f9;
        }
        .fin-table .fin-label { color: #475569; font-weight: 600; }
        .fin-table .fin-val {
            text-align: right;
            font-family: 'Consolas', 'Segoe UI', monospace;
            font-weight: 700;
            color: #0f172a;
        }
        .fin-table tr.fin-subtotal td {
            background-color: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .fin-table tr.fin-grandtotal td {
            background-color: #ecfdf5;
            color: #065f46;
            font-size: 15px;
            font-weight: 800;
            border-top: 2px solid #10b981;
            border-bottom: 2px solid #10b981;
        }
        .fin-table tr.fin-m-row td {
            background-color: #eff6ff;
            color: #1e40af;
            font-weight: 700;
            font-size: 13px;
        }

        /* Scrollable Data Grid Wrapper (Section 3 & 4 Fix) */
        .grid-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 460px;
            border-radius: 0 0 12px 12px;
            -webkit-overflow-scrolling: touch;
        }
        .grid-scroll-wrapper::-webkit-scrollbar {
            height: 10px;
            width: 10px;
        }
        .grid-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 0 0 12px 12px;
        }
        .grid-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 5px;
        }
        .grid-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .grid-table {
            width: 100%;
            min-width: max-content;
            margin-bottom: 0 !important;
            border-collapse: separate;
            border-spacing: 0;
        }
        .grid-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: #e2e8f0 !important;
            color: #1e293b !important;
            font-size: 11.5px !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 11px 10px !important;
            border-bottom: 2px solid #94a3b8 !important;
            border-right: 1px solid #cbd5e1 !important;
            white-space: nowrap !important;
        }
        .grid-table tbody td {
            font-size: 12.5px;
            color: #0f172a;
            padding: 9px 10px !important;
            border-bottom: 1px solid #e2e8f0 !important;
            border-right: 1px solid #f1f5f9 !important;
            white-space: nowrap !important;
            background-color: #ffffff;
        }
        .grid-table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A7'; ?>
    <?php include 'include/' . $folder_func . '/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Invoice Detail : <span style="color:#2563eb;"><?php echo $invno; ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Action Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                <a href="invoice_close_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-back-list">
                 ⬅️ กลับหน้ารายการ (Back to List)
                </a>

                <?php if ($is_invoice_authorized): ?>
                    <a href="invoice_close_entry_mats.php?func=<?php echo $folder_func; ?>&invno=<?php echo $invno; ?>" class="btn-edit-closing">
                        &#9998; EDIT / CLOSE INVOICE
                    </a>
                <?php endif; ?>
            </div>

            <div id="detail_alert_area"></div>
            <div id="detail_warning_area"></div>

            <!-- Account Data Highlight Strip -->
            <div class="account-strip">
                <div class="row">
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">Invoice No.</div>
                        <div class="strip-val" style="color:#60a5fa;" id="st_invno"><?php echo $invno; ?></div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">Status</div>
                        <div class="strip-val" id="st_status">-</div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">Material In</div>
                        <div class="strip-val" style="color:#a5b4fc;" id="st_matin">-</div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">Exchange Rate</div>
                        <div class="strip-val" style="color:#34d399;" id="st_exrate">0.00000</div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">T3 Code</div>
                        <div class="strip-val" id="st_t3">-</div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 strip-item">
                        <div class="strip-label">T6 Code</div>
                        <div class="strip-val" id="st_t6">-</div>
                    </div>
                </div>
            </div>

            <!-- Section 1: Invoice Heading & Shipping (2-Column Structured Table) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#2563eb;"></span>
                        1. ข้อมูลหัวบิลและการขนส่ง (Invoice Heading & Shipping)
                    </h4>
                    <span id="hd_currency_badge" class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:12px; padding:5px 10px;">CURRENCY: -</span>
                </div>
                <div class="clean-card-body">
                    <div class="row">
                        <div class="col-md-6" style="border-right: 1px solid #f1f5f9;">
                            <table class="kv-table">
                                <tr>
                                    <th>Customer</th>
                                    <td style="color:#1d4ed8; font-size:14.5px;" id="hd_customer">-</td>
                                </tr>
                                <tr>
                                    <th>Invoice Date</th>
                                    <td id="hd_inv_date">-</td>
                                </tr>
                                <tr>
                                    <th>Shipment Date</th>
                                    <td id="hd_ship_date">-</td>
                                </tr>
                                <tr>
                                    <th>Payment Term</th>
                                    <td id="hd_payment">-</td>
                                </tr>
                                <tr>
                                    <th>Shipment Term</th>
                                    <td id="hd_shipment">-</td>
                                </tr>
                                <tr>
                                    <th>Bank</th>
                                    <td id="hd_bank">-</td>
                                </tr>
                                <tr>
                                    <th>Letter of Credit No.</th>
                                    <td id="hd_lc">-</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="kv-table">
                                <tr>
                                    <th>Booking No.</th>
                                    <td id="hd_book_no">-</td>
                                </tr>
                                <tr>
                                    <th>Port of Loading</th>
                                    <td id="hd_port_load">-</td>
                                </tr>
                                <tr>
                                    <th>Port of Discharge</th>
                                    <td id="hd_port_disc">-</td>
                                </tr>
                                <tr>
                                    <th>Place Destination</th>
                                    <td id="hd_place_dest">-</td>
                                </tr>
                                <tr>
                                    <th>Country Destination</th>
                                    <td id="hd_country_dest">-</td>
                                </tr>
                                <tr>
                                    <th>Shipped By</th>
                                    <td id="hd_shipped_by">-</td>
                                </tr>
                                <tr>
                                    <th>Remarks</th>
                                    <td id="hd_remarks">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Financial & Weight Summary (Split KPI + Statement) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#059669;"></span>
                        2. สรุปยอดเงินและน้ำหนักสินค้า (Financial & Weight Summary)
                    </h4>
                </div>
                <div class="clean-card-body">
                    <div class="row">
                        <div class="col-md-5" style="margin-bottom: 16px;">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:10px;">
                                ปริมาณและน้ำหนักรวม (Quantity & Weight)
                            </div>
                            <div class="kpi-grid">
                                <div class="kpi-box">
                                    <div class="kpi-box-label">Package Qty.</div>
                                    <div class="kpi-box-value"><span id="wt_pkg">0</span><span class="kpi-box-unit">Pkg</span></div>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-box-label">Net Piece</div>
                                    <div class="kpi-box-value"><span id="wt_net_pc">0</span><span class="kpi-box-unit">Pcs</span></div>
                                </div>
                                <div class="kpi-box" style="background-color:#f0f9ff; border-color:#bae6fd;">
                                    <div class="kpi-box-label" style="color:#0369a1;">Net Weight</div>
                                    <div class="kpi-box-value" style="color:#0c4a6e;"><span id="wt_net_wt">0.00</span><span class="kpi-box-unit">kg</span></div>
                                </div>
                                <div class="kpi-box" style="background-color:#f0f9ff; border-color:#bae6fd;">
                                    <div class="kpi-box-label" style="color:#0369a1;">Gross Weight</div>
                                    <div class="kpi-box-value" style="color:#0c4a6e;"><span id="wt_gross_wt">0.00</span><span class="kpi-box-unit">kg</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:10px;">
                                รายละเอียดการคำนวณยอดเงิน (Amount Breakdown - <span id="fn_currency_lbl">-</span>)
                            </div>
                            <div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
                                <table class="fin-table">
                                    <tr>
                                        <td class="fin-label">Total Amount (ยอดรวมสินค้า)</td>
                                        <td class="fin-val" id="fn_total">0.000</td>
                                    </tr>
                                    <tr>
                                        <td class="fin-label">(+) Transportation (ค่าขนส่งในประเทศ)</td>
                                        <td class="fin-val" id="fn_transport">0.00</td>
                                    </tr>
                                    <tr class="fin-subtotal">
                                        <td>Subtotal Amount</td>
                                        <td class="fin-val" id="fn_subtotal">0.000</td>
                                    </tr>
                                    <tr>
                                        <td class="fin-label">(+) Tax Amount (<span id="fn_tax_pct">0</span>%)</td>
                                        <td class="fin-val" id="fn_tax_amt">0.000</td>
                                    </tr>
                                    <tr>
                                        <td class="fin-label">(-) Oversea Freight (ค่าระวางเรือ)</td>
                                        <td class="fin-val" id="fn_freight">0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="fin-label">(-) Insurance (ค่าประกันภัย)</td>
                                        <td class="fin-val" id="fn_insurance">0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="fin-label">Soncap Certificate</td>
                                        <td class="fin-val" id="fn_cert">0.00</td>
                                    </tr>
                                    <tr class="fin-grandtotal">
                                        <td>Grand Total Amount (<span id="fn_currency_gt">-</span>)</td>
                                        <td class="fin-val" id="fn_grand_total">0.000</td>
                                    </tr>
                                    <tr class="fin-m-row">
                                        <td>M Subtotal Amount / M Grand Total Amount</td>
                                        <td class="fin-val"><span id="fn_m_subtotal">0.00</span> / <span id="fn_m_grand">0.00</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Invoice Item (INVCPRCS2) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#4f46e5;"></span>
                        3. รายการสินค้าตามใบสั่งขาย (Invoice Item - INVCPRCS2)
                    </h4>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:11.5px; color:#64748b;">(เลื่อนซ้าย-ขวาเพื่อดูคอลัมน์ทั้งหมด)</span>
                        <span id="dt_inv_item_badge" class="badge" style="background:#e2e8f0; color:#1e293b;">0 Items</span>
                    </div>
                </div>
                <div class="grid-scroll-wrapper">
                    <table class="table table-hover grid-table">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th class="text-center">I.Item</th>
                                <th>S.O. No.</th>
                                <th class="text-center">S.Item</th>
                                <th class="text-center">Alloy</th>
                                <th class="text-center">Tp</th>
                                <th class="text-center">SG</th>
                                <th class="text-center">MG</th>
                                <th class="text-right">Th</th>
                                <th class="text-right">W</th>
                                <th class="text-right">L</th>
                                <th class="text-right">Weight</th>
                            </tr>
                        </thead>
                        <tbody id="dt_tbody_items">
                            <tr><td colspan="12" class="text-center text-muted" style="padding:20px;">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section 4: Product Item (INVCPRCS3) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#0284c7;"></span>
                        4. รายการชิ้นสินค้าจริง (Product Item - INVCPRCS3)
                    </h4>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:11.5px; color:#64748b;">(เลื่อนซ้าย-ขวาเพื่อดูทั้ง 26 คอลัมน์)</span>
                        <span id="dt_prod_item_badge" class="badge" style="background:#e2e8f0; color:#1e293b;">0 Pieces</span>
                    </div>
                </div>
                <div class="grid-scroll-wrapper">
                    <table class="table table-hover grid-table">
                        <thead>
                            <tr>
                                <th>Product No.</th>
                                <th>Invoice No.</th>
                                <th class="text-center">I.Item</th>
                                <th class="text-center">Skid No.</th>
                                <th>Debit Note</th>
                                <th class="text-center">D.Item</th>
                                <th>Credit Note No.</th>
                                <th class="text-center">C.Item</th>
                                <th>Book No.</th>
                                <th>Customer</th>
                                <th>S.O. No.</th>
                                <th class="text-center">S.Item</th>
                                <th class="text-center">A</th>
                                <th class="text-center">Tp</th>
                                <th class="text-center">SG</th>
                                <th class="text-center">MG</th>
                                <th class="text-right">Th</th>
                                <th class="text-right">W</th>
                                <th class="text-right">L</th>
                                <th class="text-right">Package</th>
                                <th class="text-right">Net Wt.</th>
                                <th class="text-right">Inv. Price</th>
                                <th class="text-right">DB. Price</th>
                                <th class="text-right">CD. Wt.</th>
                                <th class="text-right">CD. Price</th>
                                <th class="text-center">St.</th>
                            </tr>
                        </thead>
                        <tbody id="dt_tbody_products">
                            <tr><td colspan="26" class="text-center text-muted" style="padding:20px;">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <input type="hidden" id="invno" value="<?php echo $invno; ?>" />
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var invNo = $('#invno').val().trim();
    if (invNo) {
        loadInvoiceDetailPage(invNo);
    }
});

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function fmtNum(val, decimals) {
    var num = parseFloat(val);
    if (isNaN(num)) num = 0;
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function loadInvoiceDetailPage(invNo) {
    fetch('model/invoice_close_api.php?action=detail&invoice_no=' + encodeURIComponent(invNo))
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                $('#detail_alert_area').html(
                    '<div class="alert alert-danger" style="border-radius:8px; font-weight:600;">' +
                    escapeHtml(resp.message || 'ไม่พบข้อมูล Invoice') + '</div>'
                );
                return;
            }

            if (resp.warnings && resp.warnings.length > 0) {
                var wHtml = '<div class="warning-banner">' +
                            '<strong>&#9888; แจ้งเตือนความสอดคล้องข้อมูล (Material In):</strong>' +
                            '<ul style="margin:6px 0 0 20px;">';
                resp.warnings.forEach(function(w) { wHtml += '<li>' + escapeHtml(w) + '</li>'; });
                wHtml += '</ul></div>';
                $('#detail_warning_area').html(wHtml);
            }

            var h     = resp.data.heading;
            var items = resp.data.invoice_items || [];
            var prods = resp.data.product_items || [];

            // Account Strip
            $('#st_invno').text(h.INVOICE_NO);
            if (h.INV1_STATUS === 'CP') {
                $('#st_status').html('<span style="background:#10b981; color:#fff; padding:3px 10px; border-radius:5px; font-size:13px;">CP: COMPLETE</span>');
            } else {
                $('#st_status').html('<span style="background:#f59e0b; color:#fff; padding:3px 10px; border-radius:5px; font-size:13px;">OP: OPEN</span>');
            }
            $('#st_matin').text(h.INV1_MATERIALIN || '-');
            $('#st_exrate').text(h.INV1_EXCHANGERATE);
            $('#st_t3').text(h.T3 || '-');
            $('#st_t6').text(h.T6 || '-');

            // Heading & Shipping
            $('#hd_currency_badge').text('CURRENCY: ' + (h.CURRENCY_ID || '-'));
            $('#hd_customer').text(h.CUSTOMER_DISPLAY || '-');
            $('#hd_inv_date').text(h.INVOICE_DATE_FMT);
            $('#hd_ship_date').text(h.SHIPMENT_DATE_FMT);
            $('#hd_book_no').text(h.BOOK_NO || '-');
            $('#hd_port_load').text(h.PORT_LOADING_DISPLAY || '-');
            $('#hd_port_disc').text(h.PORT_DISCHARGE_DISPLAY || '-');
            $('#hd_place_dest').text(h.PLACE_DEST_DISPLAY || '-');
            $('#hd_country_dest').text(h.COUNTRY_DEST_DISPLAY || '-');
            $('#hd_payment').text(h.PAYMENT_DISPLAY || '-');
            $('#hd_shipment').text(h.SHIPMENT_DISPLAY || '-');
            $('#hd_bank').text(h.BANK_DISPLAY || '-');
            $('#hd_lc').text(h.LETTER_CREDIT || '-');
            $('#hd_shipped_by').text(h.INV1_SHIPPEDBY || '-');

            var remarks = [];
            if (h.INV1_REMARK1) remarks.push(h.INV1_REMARK1);
            if (h.INV1_REMARK2) remarks.push(h.INV1_REMARK2);
            $('#hd_remarks').text(remarks.length > 0 ? remarks.join(' | ') : '-');

            // Financial & Weight
            $('#fn_currency_lbl, #fn_currency_gt').text(h.CURRENCY_ID || '-');
            $('#fn_tax_pct').text(fmtNum(h.INV1_TAXPERCENT, 0));
            $('#fn_total').text(fmtNum(h.INV1_TOTALAMOUNT, 3));
            $('#fn_transport').text(fmtNum(h.INV1_TRANSPORTATION, 2));
            $('#fn_subtotal').text(fmtNum(h.SUBTOTAL_AMOUNT, 3));
            $('#fn_tax_amt').text(fmtNum(h.TAX_AMOUNT, 3));
            $('#fn_freight').text(fmtNum(h.INV1_OVERSEAFREIGHT, 2));
            $('#fn_insurance').text(fmtNum(h.INV1_INSURANCE, 2));
            $('#fn_cert').text(fmtNum(h.INV1_CERTIFICATE, 2));
            $('#fn_grand_total').text(fmtNum(h.GRAND_TOTAL_AMOUNT, 3));
            $('#fn_m_subtotal').text(fmtNum(h.INV1_MTOTALAMOUNT, 2));
            $('#fn_m_grand').text(fmtNum(h.INV1_MGRANDTOTALAMOUNT, 2));

            $('#wt_pkg').text(fmtNum(h.INV1_PACKAGE, 0));
            $('#wt_net_pc').text(fmtNum(h.INV1_NETPIECE, 0));
            $('#wt_net_wt').text(fmtNum(h.INV1_NETWEIGHT, 2));
            $('#wt_gross_wt').text(fmtNum(h.INV1_GROSSWEIGHT, 2));

            // Render Invoice Items (12 columns)
            $('#dt_inv_item_badge').text(items.length + ' Items');
            if (items.length === 0) {
                $('#dt_tbody_items').html('<tr><td colspan="12" class="text-center text-muted">ไม่พบรายการสินค้า</td></tr>');
            } else {
                var itemHtml = '';
                items.forEach(function(it) {
                    itemHtml += '<tr>' +
                                '<td style="font-weight:600;">' + escapeHtml(it.INVOICE_NO) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.INVOICE_ITEM) + '</td>' +
                                '<td>' + escapeHtml(it.SALEORDER_NO) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.SALEORDER_ITEM) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.ALLOY) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.TEMPER) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.SURFACE_GRADE) + '</td>' +
                                '<td class="text-center">' + escapeHtml(it.METALLURGICAL_GRADE) + '</td>' +
                                '<td class="text-right">' + escapeHtml(it.THICKNESS) + '</td>' +
                                '<td class="text-right">' + escapeHtml(it.WIDTH) + '</td>' +
                                '<td class="text-right">' + escapeHtml(it.LENGTH) + '</td>' +
                                '<td class="text-right" style="font-weight:700;">' + fmtNum(it.INV2_NETWEIGHT, 0) + '</td>' +
                                '</tr>';
                });
                $('#dt_tbody_items').html(itemHtml);
            }

            // Render Product Items (26 columns)
            $('#dt_prod_item_badge').text(prods.length + ' Pieces');
            if (prods.length === 0) {
                $('#dt_tbody_products').html('<tr><td colspan="26" class="text-center text-muted">ไม่พบข้อมูลชิ้นสินค้า</td></tr>');
            } else {
                var prodHtml = '';
                prods.forEach(function(p) {
                    prodHtml += '<tr>' +
                                '<td style="font-weight:600;">' + escapeHtml(p.PRODUCT_NO) + '</td>' +
                                '<td>' + escapeHtml(p.INVOICE_NO) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.INVOICE_ITEM) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.SKID_NO) + '</td>' +
                                '<td>' + escapeHtml(p.DEBITNOTE_NO || '-') + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.DEBITNOTE_ITEM || '-') + '</td>' +
                                '<td>' + escapeHtml(p.CREDITNOTE_NO || '-') + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.CREDITNOTE_ITEM || '-') + '</td>' +
                                '<td>' + escapeHtml(p.BOOK_NO) + '</td>' +
                                '<td>' + escapeHtml(p.CSTMSPPL_ID) + '</td>' +
                                '<td>' + escapeHtml(p.SALEORDER_NO) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.SALEORDER_ITEM) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.ALLOY) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.TEMPER) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.SURFACE_GRADE) + '</td>' +
                                '<td class="text-center">' + escapeHtml(p.METALLURGICAL_GRADE) + '</td>' +
                                '<td class="text-right">' + escapeHtml(p.THICKNESS) + '</td>' +
                                '<td class="text-right">' + escapeHtml(p.WIDTH) + '</td>' +
                                '<td class="text-right">' + escapeHtml(p.LENGTH) + '</td>' +
                                '<td class="text-right">' + fmtNum(p.INV3_PACKAGE, 0) + '</td>' +
                                '<td class="text-right" style="font-weight:700;">' + fmtNum(p.INV3_NETWEIGHT, 0) + '</td>' +
                                '<td class="text-right">' + fmtNum(p.INV3_INVOICEPRICE, 4) + '</td>' +
                                '<td class="text-right">' + fmtNum(p.INV3_DEBITPRICE, 4) + '</td>' +
                                '<td class="text-right">' + fmtNum(p.INV3_CREDITNETWEIGHT, 0) + '</td>' +
                                '<td class="text-right">' + fmtNum(p.INV3_CREDITPRICE, 4) + '</td>' +
                                '<td class="text-center"><span class="label label-default">' + escapeHtml(p.INV3_STATUS) + '</span></td>' +
                                '</tr>';
                });
                $('#dt_tbody_products').html(prodHtml);
            }
        })
        .catch(function(err) {
            $('#detail_alert_area').html(
                '<div class="alert alert-danger" style="border-radius:8px; font-weight:600;">เกิดข้อผิดพลาด: ' + escapeHtml(err.message) + '</div>'
            );
        });
}
</script>
</body>
</html>