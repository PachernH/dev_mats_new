<?php
// invoice_close_entry_mats.php
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

if (!$is_invoice_authorized) {
    header("Location: invoice_close_list_mats.php?func=" . urlencode($folder_func));
    exit();
}

$init_invno = isset($_GET['invno']) ? htmlspecialchars(strtoupper(trim($_GET['invno'])), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Close Invoice Entry - Meyer Aluminium Thailand Co.,LTD.</title>
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
        .btn-nav-clean {
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
            cursor: pointer;
        }
        .btn-nav-clean:hover {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        .btn-save-solid {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #2563eb !important;
            color: #ffffff !important;
            border: 1px solid #1d4ed8 !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            padding: 10px 24px !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-save-solid:hover:not(:disabled) {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 10px -1px rgba(37, 99, 235, 0.35);
        }
        .btn-save-solid:disabled {
            background-color: #94a3b8 !important;
            border-color: #94a3b8 !important;
            color: #f8fafc !important;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-lookup-solid {
            background-color: #0f172a !important;
            color: #ffffff !important;
            border: 1px solid #0f172a !important;
            font-weight: 700 !important;
            padding: 0 16px !important;
            height: 40px;
        }
        .btn-lookup-solid:hover:not(:disabled) {
            background-color: #1e293b !important;
            color: #ffffff !important;
        }
        .btn-lookup-solid:disabled {
            background-color: #94a3b8 !important;
            border-color: #94a3b8 !important;
            color: #ffffff !important;
        }

        /* Warning Banner */
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

        /* Clean Card Layout (Matching Detail Page) */
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

        /* Closing Highlight Card */
        .closing-card {
            background: #ffffff;
            border-radius: 12px;
            border: 2px solid #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
            margin-bottom: 22px;
        }
        .closing-card-header {
            background-color: #eff6ff;
            padding: 14px 22px;
            border-bottom: 1px solid #bfdbfe;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .info-value-box {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            height: 40px;
            display: flex;
            align-items: center;
        }

        .batch-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
            margin-top: 14px;
        }

        /* Key-Value Info Table (Section 3) */
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

        /* Weight KPI Boxes (Section 4 Left) */
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

        /* Financial Statement Table (Section 4 Right) */
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

        /* Scrollable Data Grid Wrapper (Section 5 & 6 Fix) */
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

        #lookup_table tbody tr { cursor: pointer; }
        #lookup_table tbody tr:hover td { background-color: #dbeafe !important; }
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
                        Close Invoice Entry
                        <span id="record_status_badge" class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">READY</span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Navigation Action Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                <a href="invoice_close_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-nav-clean">
                 ⬅️ กลับหน้ารายการ (Back to List)
                </a>
                <button type="button" class="btn-nav-clean" onclick="clearForm()">
                    &#8635; ล้างหน้าจอ (Clear / Cancel)
                </button>
            </div>

            <!-- Alert & Warning Area -->
            <div id="entry_alert_area"></div>
            <div id="entry_warning_area"></div>

            <!-- Step 1: Search / Select Invoice -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#0f172a;"></span>
                        1. ค้นหาและเรียกข้อมูล Invoice
                    </h4>
                    <span style="font-size:12px; color:#64748b;">พิมพ์เลข Invoice 12 หลักแล้วกด Enter หรือกดปุ่ม Lookup</span>
                </div>
                <div class="clean-card-body">
                    <div class="row" style="display:flex; flex-wrap:wrap; align-items:flex-end;">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-weight:700; font-size:11.5px; color:#64748b;">INVOICE NO. (12 DIGITS) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="txt_invoice_no" maxlength="12"
                                           placeholder="เช่น MAT-Z-260582"
                                           value="<?php echo $init_invno; ?>"
                                           style="font-weight:700; font-size:15px; height:40px; text-transform:uppercase; letter-spacing:0.5px;"
                                           oninput="this.value = this.value.toUpperCase()"
                                           onkeypress="handleInvoiceKeyPress(event)" />
                                    <span class="input-group-btn">
                                        <button class="btn btn-lookup-solid" type="button" id="btn_lookup_inv" onclick="openLookupModal()" title="ค้นหาจากรายการ Invoice">
                                            &#128269; Lookup
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-weight:700; font-size:11.5px; color:#64748b;">MATERIAL IN</label>
                                <div class="info-value-box" id="disp_material_in" style="color:#4338ca;">-</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-8">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-weight:700; font-size:11.5px; color:#64748b;">CUSTOMER</label>
                                <div class="info-value-box" id="disp_customer" style="color:#1d4ed8;">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-4">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-weight:700; font-size:11.5px; color:#64748b;">INVOICE DATE / SHIP DATE</label>
                                <div class="info-value-box" id="disp_dates">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Account Data / Closing Form -->
            <div class="closing-card">
                <div class="closing-card-header">
                    <h4 class="clean-card-title" style="color:#1d4ed8;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#2563eb;"></span>
                        2. ข้อมูลการปิดบิล (Account Data & Closing Control)
                    </h4>
                    <button type="button" class="btn-save-solid" id="btn_save_closing" onclick="saveClosingEntry()" disabled>
                        &#128190; บันทึกข้อมูล (SAVE)
                    </button>
                </div>

                <div class="clean-card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label style="font-weight:700; font-size:12px; color:#1e293b;">STATUS <span class="text-danger">*</span></label>
                                <select class="form-control" id="cmb_status" disabled style="font-weight:700; height:40px;">
                                    <option value="OP">OP: OPEN</option>
                                    <option value="CP">CP: COMPLETE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label style="font-weight:700; font-size:12px; color:#1e293b;">EXCHANGE RATE <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-right" id="txt_exchange_rate" value="0.00000" disabled
                                       style="font-weight:700; font-size:15px; height:40px; font-family:'Consolas', monospace;"
                                       oninput="this.value = this.value.replace(/[^0-9.]/g, '')"
                                       onblur="formatEntryExchangeRate()" />
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label style="font-weight:700; font-size:12px; color:#1e293b;">T3 <span class="text-danger">*</span></label>
                                <select class="form-control" id="cmb_t3" disabled style="font-weight:700; height:40px;">
                                    <option value="B#1">B#1</option>
                                    <option value="B#2">B#2</option>
                                    <option value="NON">NON</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label style="font-weight:700; font-size:12px; color:#1e293b;">T6</label>
                                <select class="form-control" id="cmb_t6" disabled style="font-weight:700; height:40px;" onchange="applyEntryFieldRules()">
                                    <option value=""></option>
                                    <option value="AM1">AM1</option>
                                    <option value="AM2">AM2</option>
                                    <option value="AT1">AT1</option>
                                    <option value="CSN">CSN</option>
                                    <option value="EW1">EW1</option>
                                    <option value="MAB">MAB</option>
                                    <option value="MIL">MIL</option>
                                    <option value="MSB">MSB</option>
                                    <option value="MTM">MTM</option>
                                    <option value="MZQ">MZQ</option>
                                    <option value="NA1">NA1</option>
                                    <option value="NI1">NI1</option>
                                    <option value="OH1">OH1</option>
                                    <option value="OLS">OLS</option>
                                    <option value="OOS">OOS</option>
                                    <option value="SB1">SB1</option>
                                    <option value="SWA">SWA</option>
                                    <option value="TA1">TA1</option>
                                    <option value="TI1">TI1</option>
                                    <option value="TI2">TI2</option>
                                    <option value="TS1">TS1</option>
                                    <option value="UL1">UL1</option>
                                    <option value="WS1">WS1</option>
                                    <option value="NON">NON</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Local Customer Batch Update (OLS) -->
                    <div class="batch-box">
                        <div class="row" style="display:flex; flex-wrap:wrap; align-items:center;">
                            <div class="col-md-6">
                                <div class="checkbox" style="margin:0;">
                                    <label style="font-weight:700; color:#1e3a8a; font-size:13.5px;">
                                        <input type="checkbox" id="chk_update_ex_rate" disabled onchange="toggleEntryBatchDates()" />
                                        Update T3, T6 and Exchange Rate for all Local Customer (MAT-Z% & LCL)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label style="font-size:11px; font-weight:700; color:#475569; margin-bottom:2px;">PERIOD SHIPMENT DATE FROM</label>
                                <input type="date" class="form-control" id="dtp_from_date" disabled />
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label style="font-size:11px; font-weight:700; color:#475569; margin-bottom:2px;">PERIOD SHIPMENT DATE TO</label>
                                <input type="date" class="form-control" id="dtp_to_date" disabled />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Invoice Heading & Shipping (Matching Detail Page Layout) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#2563eb;"></span>
                        3. ข้อมูลหัวบิลและการขนส่ง (Invoice Heading & Shipping Reference)
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

            <!-- Step 4: Financial & Weight Summary (Matching Detail Page Layout) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#059669;"></span>
                        4. สรุปยอดเงินและน้ำหนักสินค้า (Financial & Weight Summary)
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

            <!-- Step 5: Invoice Items (INVCPRCS2) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#4f46e5;"></span>
                        5. รายการสินค้าตามใบสั่งขาย (Invoice Item - INVCPRCS2)
                    </h4>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:11.5px; color:#64748b;">(เลื่อนซ้าย-ขวาเพื่อดูคอลัมน์ทั้งหมด)</span>
                        <span id="badge_inv_item_cnt" class="badge" style="background:#e2e8f0; color:#1e293b;">0 Items</span>
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
                        <tbody id="tbody_invoice_items">
                            <tr><td colspan="12" class="text-center text-muted" style="padding:20px;">ยังไม่มีข้อมูลรายการ</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Step 6: Product Items (INVCPRCS3) -->
            <div class="clean-card">
                <div class="clean-card-header">
                    <h4 class="clean-card-title">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#0284c7;"></span>
                        6. รายการชิ้นสินค้าจริง (Product Item - INVCPRCS3)
                    </h4>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:11.5px; color:#64748b;">(เลื่อนซ้าย-ขวาเพื่อดูทั้ง 26 คอลัมน์)</span>
                        <span id="badge_prod_item_cnt" class="badge" style="background:#e2e8f0; color:#1e293b;">0 Pieces</span>
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
                        <tbody id="tbody_product_items">
                            <tr><td colspan="26" class="text-center text-muted" style="padding:20px;">ยังไม่มีข้อมูลชิ้นสินค้า</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>" />
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- Invoice Lookup Modal (12 Columns matching 1.jpg) -->
<div class="modal fade" id="invoiceLookupModal" tabindex="-1" role="dialog" aria-labelledby="lookupModalLabel">
    <div class="modal-dialog modal-lg" style="width: 92%; max-width: 1350px;" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="background-color:#0f172a; color:#ffffff; border-radius:12px 12px 0 0;">
                <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.85;"><span>&times;</span></button>
                <h4 class="modal-title" id="lookupModalLabel" style="font-weight:700;">
                    &#128269; Look Up Data - เลือกรายการ Invoice (INVCPRCS1)
                </h4>
            </div>
            <div class="modal-body">
                <div class="grid-scroll-wrapper" style="border-radius:8px; border:1px solid #e2e8f0;">
                    <table id="lookup_table" class="table table-hover grid-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>INVOICE_NO</th>
                                <th>INVOICE_DATE</th>
                                <th>SHIPMENT_DATE</th>
                                <th>BOOK_NO</th>
                                <th>CSTMSPPL_ID</th>
                                <th>PAYMENT_ID</th>
                                <th>SHIPMENT_ID</th>
                                <th>CURRENCY_ID</th>
                                <th class="text-right">INV1_PACKAGE</th>
                                <th class="text-right">INV1_NETWEIGHT</th>
                                <th class="text-right">INV1_GROSSWEIGHT</th>
                                <th class="text-center">INV1_STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="lookup_tbody">
                            <tr><td colspan="12" class="text-center" style="padding:20px;">กำลังโหลดข้อมูล...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var lookupDataTable = null;
var isRecordLoaded  = false;

$(document).ready(function() {
    var initialInv = $('#txt_invoice_no').val().trim();
    if (initialInv.length === 12) {
        loadInvoiceHeading(initialInv);
    } else {
        $('#txt_invoice_no').focus();
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

function showEntryAlert(type, msg) {
    if (!msg) {
        $('#entry_alert_area').html('');
        return;
    }
    var cls = (type === 'success') ? 'alert-success' : 'alert-danger';
    $('#entry_alert_area').html(
        '<div class="alert ' + cls + ' alert-dismissible" style="border-radius:8px; font-weight:600;">' +
        '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>' +
        escapeHtml(msg) + '</div>'
    );
}

function handleInvoiceKeyPress(e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
        e.preventDefault();
        var invNo = $('#txt_invoice_no').val().trim().toUpperCase();
        $('#txt_invoice_no').val(invNo);

        if ($('#txt_invoice_no').prop('readonly')) return;

        if (invNo.length === 12) {
            loadInvoiceHeading(invNo);
        } else {
            clearForm('เลขที่ Invoice ไม่ถูกต้อง (ต้องมีความยาว 12 ตัวอักษร)');
        }
    }
}

function clearForm(errorMsg) {
    isRecordLoaded = false;
    $('#record_status_badge').text('READY').css('background-color', '#64748b');
    $('#entry_warning_area').html('');
    showEntryAlert(errorMsg ? 'error' : '', errorMsg || '');

    $('#txt_invoice_no').val('').prop('readonly', false);
    $('#btn_lookup_inv').prop('disabled', false);

    // Clear Top Bar & Heading Table
    $('#disp_material_in, #disp_customer, #disp_dates').text('-');
    $('#hd_currency_badge').text('CURRENCY: -');
    $('#hd_customer, #hd_inv_date, #hd_ship_date, #hd_payment, #hd_shipment, #hd_bank, #hd_lc').text('-');
    $('#hd_book_no, #hd_port_load, #hd_port_disc, #hd_place_dest, #hd_country_dest, #hd_shipped_by, #hd_remarks').text('-');

    // Clear Financial & Weight
    $('#fn_currency_lbl, #fn_currency_gt').text('-');
    $('#fn_tax_pct, #wt_pkg, #wt_net_pc').text('0');
    $('#fn_total, #fn_subtotal, #fn_tax_amt, #fn_grand_total').text('0.000');
    $('#fn_transport, #fn_freight, #fn_insurance, #fn_cert, #fn_m_subtotal, #fn_m_grand, #wt_net_wt, #wt_gross_wt').text('0.00');

    var todayStr = new Date().toISOString().split('T')[0];
    $('#cmb_status').val('OP').prop('disabled', true);
    $('#txt_exchange_rate').val('0.00000').prop('disabled', true);
    $('#cmb_t3').val('B#1').prop('disabled', true);
    $('#cmb_t6').val('').prop('disabled', true);
    $('#chk_update_ex_rate').prop('checked', false).prop('disabled', true);
    $('#dtp_from_date').val(todayStr).prop('disabled', true);
    $('#dtp_to_date').val(todayStr).prop('disabled', true);
    $('#btn_save_closing').prop('disabled', true);

    $('#badge_inv_item_cnt').text('0 Items');
    $('#badge_prod_item_cnt').text('0 Pieces');
    $('#tbody_invoice_items').html('<tr><td colspan="12" class="text-center text-muted" style="padding:20px;">ยังไม่มีข้อมูลรายการ</td></tr>');
    $('#tbody_product_items').html('<tr><td colspan="26" class="text-center text-muted" style="padding:20px;">ยังไม่มีข้อมูลชิ้นสินค้า</td></tr>');

    $('#txt_invoice_no').focus();
}

function loadInvoiceHeading(invoiceNo) {
    showEntryAlert('', '');
    $('#entry_warning_area').html('');

    fetch('model/invoice_close_api.php?action=detail&invoice_no=' + encodeURIComponent(invoiceNo))
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                clearForm(resp.message || 'ไม่พบข้อมูลเลขที่ Invoice นี้');
                return;
            }

            var h     = resp.data.heading;
            var items = resp.data.invoice_items || [];
            var prods = resp.data.product_items || [];

            // แสดง Warning กรณี Material In ไม่ตรงกัน
            if (resp.warnings && resp.warnings.length > 0) {
                var wHtml = '<div class="warning-banner">' +
                            '<strong>&#9888; แจ้งเตือนความสอดคล้องข้อมูล (Material In):</strong>' +
                            '<ul style="margin:6px 0 0 20px;">';
                resp.warnings.forEach(function(w) { wHtml += '<li>' + escapeHtml(w) + '</li>'; });
                wHtml += '</ul></div>';
                $('#entry_warning_area').html(wHtml);
            }

            // Fill Step 1 Summary
            $('#txt_invoice_no').val(h.INVOICE_NO).prop('readonly', true);
            $('#btn_lookup_inv').prop('disabled', true);
            $('#disp_material_in').text(h.INV1_MATERIALIN || '-');
            $('#disp_customer').text(h.CUSTOMER_DISPLAY || '-');
            $('#disp_dates').text(h.INVOICE_DATE_FMT + '  /  ' + h.SHIPMENT_DATE_FMT);

            // Fill Step 3 Heading & Shipping Table
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

            // Fill Step 4 Financial & Weight Summary
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

            // Fill Editable Account Data
            isRecordLoaded = true;
            $('#record_status_badge').text('OLD RECORD').css('background-color', '#059669');
            $('#cmb_status').val(h.INV1_STATUS).prop('disabled', false);
            $('#txt_exchange_rate').val(h.INV1_EXCHANGERATE);
            $('#cmb_t3').val(h.T3).prop('disabled', false);
            $('#cmb_t6').val(h.T6).prop('disabled', false);
            $('#chk_update_ex_rate').prop('checked', false);
            $('#dtp_from_date').val(h.SHIPMENT_DATE);
            $('#dtp_to_date').val(h.SHIPMENT_DATE);
            $('#btn_save_closing').prop('disabled', false);

            applyEntryFieldRules();
            renderEntryItems(items);
            renderEntryProducts(prods);
        })
        .catch(function(err) {
            clearForm('เกิดข้อผิดพลาดในการดึงข้อมูล: ' + err.message);
        });
}

function formatEntryExchangeRate() {
    var val = parseFloat($('#txt_exchange_rate').val());
    if (isNaN(val)) val = 0;
    $('#txt_exchange_rate').val(val.toFixed(5));
}

function applyEntryFieldRules() {
    var t6 = $('#cmb_t6').val();
    if (t6 === 'MIL') {
        $('#chk_update_ex_rate').prop('checked', false).prop('disabled', true);
        $('#txt_exchange_rate').prop('disabled', true);
    } else if (t6 === 'OLS') {
        if (!isRecordLoaded) {
            $('#chk_update_ex_rate').prop('checked', false).prop('disabled', true);
            $('#txt_exchange_rate').prop('disabled', true);
        } else {
            $('#chk_update_ex_rate').prop('disabled', false);
            $('#txt_exchange_rate').prop('disabled', false);
        }
    } else {
        $('#chk_update_ex_rate').prop('checked', false).prop('disabled', true);
        $('#txt_exchange_rate').prop('disabled', !isRecordLoaded);
    }
    toggleEntryBatchDates();
}

function toggleEntryBatchDates() {
    var checked = $('#chk_update_ex_rate').is(':checked');
    $('#dtp_from_date').prop('disabled', !checked);
    $('#dtp_to_date').prop('disabled', !checked);
}

function renderEntryItems(items) {
    $('#badge_inv_item_cnt').text(items.length + ' Items');
    if (items.length === 0) {
        $('#tbody_invoice_items').html('<tr><td colspan="12" class="text-center text-muted" style="padding:20px;">ไม่พบรายการสินค้า</td></tr>');
        return;
    }
    var html = '';
    items.forEach(function(it) {
        html += '<tr>' +
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
    $('#tbody_invoice_items').html(html);
}

function renderEntryProducts(prods) {
    $('#badge_prod_item_cnt').text(prods.length + ' Pieces');
    if (prods.length === 0) {
        $('#tbody_product_items').html('<tr><td colspan="26" class="text-center text-muted" style="padding:20px;">ไม่พบข้อมูลชิ้นสินค้า</td></tr>');
        return;
    }
    var html = '';
    prods.forEach(function(p) {
        html += '<tr>' +
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
    $('#tbody_product_items').html(html);
}

// ================= LOOKUP MODAL (12 COLUMNS) =================
function openLookupModal() {
    $('#invoiceLookupModal').modal('show');
    fetch('model/invoice_close_api.php?action=list&limit=300')
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') return;
            if (lookupDataTable) {
                lookupDataTable.destroy();
                lookupDataTable = null;
            }
            var html = '';
            (resp.data || []).forEach(function(r) {
                var invNo = escapeHtml(r.INVOICE_NO);
                html += '<tr onclick="selectLookupInvoice(\'' + invNo + '\')">' +
                        '<td style="font-weight:700; color:#1d4ed8;">' + invNo + '</td>' +
                        '<td>' + escapeHtml(r.INVOICE_DATE_FMT) + '</td>' +
                        '<td>' + escapeHtml(r.SHIPMENT_DATE_FMT) + '</td>' +
                        '<td>' + escapeHtml(r.BOOK_NO) + '</td>' +
                        '<td>' + escapeHtml(r.CSTMSPPL_ID) + '</td>' +
                        '<td>' + escapeHtml(r.PAYMENT_ID) + '</td>' +
                        '<td>' + escapeHtml(r.SHIPMENT_ID) + '</td>' +
                        '<td>' + escapeHtml(r.CURRENCY_ID) + '</td>' +
                        '<td class="text-right">' + fmtNum(r.INV1_PACKAGE, 0) + '</td>' +
                        '<td class="text-right">' + fmtNum(r.INV1_NETWEIGHT, 0) + '</td>' +
                        '<td class="text-right">' + fmtNum(r.INV1_GROSSWEIGHT, 0) + '</td>' +
                        '<td class="text-center"><strong>' + escapeHtml(r.INV1_STATUS) + '</strong></td>' +
                        '</tr>';
            });
            $('#lookup_tbody').html(html);
            lookupDataTable = $('#lookup_table').DataTable({
                pageLength: 15,
                order: [[0, 'desc']]
            });
        });
}

function selectLookupInvoice(invNo) {
    $('#invoiceLookupModal').modal('hide');
    $('#txt_invoice_no').val(invNo);
    loadInvoiceHeading(invNo);
}

// ================= SAVE CLOSING INVOICE =================
function saveClosingEntry() {
    if (!isRecordLoaded) return;
    formatEntryExchangeRate();

    var payload = {
        action: 'save',
        invoice_no: $('#txt_invoice_no').val().trim(),
        status: $('#cmb_status').val(),
        exchange_rate: parseFloat($('#txt_exchange_rate').val()) || 0,
        t3: $('#cmb_t3').val(),
        t6: $('#cmb_t6').val(),
        update_exchange_rate: $('#chk_update_ex_rate').is(':checked') ? 1 : 0,
        from_date: $('#dtp_from_date').val(),
        to_date: $('#dtp_to_date').val()
    };

    var btn = $('#btn_save_closing');
    btn.prop('disabled', true).html('&#8987; กำลังบันทึกข้อมูล...');

    fetch('model/invoice_close_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(resp) {
        btn.html('&#128190; บันทึกข้อมูล (SAVE)');
        if (resp.status === 'success') {
            clearForm('');
            showEntryAlert('success', resp.message);
        } else {
            btn.prop('disabled', false);
            showEntryAlert('error', resp.message || 'บันทึกข้อมูลไม่สำเร็จ');
        }
    })
    .catch(function(err) {
        btn.prop('disabled', false).html('&#128190; บันทึกข้อมูล (SAVE)');
        showEntryAlert('error', 'เกิดข้อผิดพลาดในการบันทึก: ' + err.message);
    });
}
</script>
</body>
</html>