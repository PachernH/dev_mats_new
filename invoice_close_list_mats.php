<?php
// invoice_close_list_mats.php
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
//$is_invoice_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');
$is_invoice_authorized = ($current_dept === 'FN' || $current_dept === 'IT');

 if (!$is_invoice_authorized) {
    echo "<script>
            alert('เตือน: สิทธิ์การทำงานของคุณ ไม่ตรงกับกระบวนการนี้ กรุณาติดต่อผู้ดูแลระบบ');
            window.location.href = 'dashboard_mats.php';
          </script>";
 } 

?>
<!doctype html>
<html lang="en">
<head>
    <title>Close Invoice Management - Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 40px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.5px;
            height: 40px;
            padding: 0 20px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-close-inv {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
            height: 40px;
            padding: 0 20px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none !important;
        }
        .btn-close-inv:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        .badge-inv {
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 12.5px;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            transition: all 0.2s ease;
        }
        .badge-inv:hover { background-color: #1d4ed8; color: #ffffff; }

        .badge-status {
            font-weight: 700;
            font-size: 11px;
            padding: 4px 9px;
            border-radius: 4px;
            display: inline-block;
        }
        .badge-status-op { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-status-cp { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        /* Table & DataTables Header Fix */
        #close_inv_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #close_inv_table thead th,
        .dataTables_scrollHeadInner table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11.5px !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 10px !important;
            border-bottom: 2px solid #e2e8f0 !important;
            white-space: nowrap !important;
            vertical-align: middle !important;
        }
        #close_inv_table tbody td {
            padding: 10px 10px !important;
            vertical-align: middle !important;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap !important;
        }
        #close_inv_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #close_inv_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* ซ่อน Header ซ้อนใน DataTables ScrollBody */
        .dataTables_scrollBody thead,
        .dataTables_scrollBody thead tr,
        .dataTables_scrollBody thead th {
            height: 0 !important;
            min-height: 0 !important;
            max-height: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin: 0 !important;
            border: none !important;
            line-height: 0 !important;
            font-size: 0 !important;
            visibility: hidden !important;
            overflow: hidden !important;
        }
        .dataTables_scrollBody thead th:before,
        .dataTables_scrollBody thead th:after {
            display: none !important;
            content: "" !important;
        }

        .btn-quick-edit {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-quick-edit:hover { background-color: #059669; color: #ffffff; border-color: #059669; }

        /* Quick Close Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        }
        .modal-header {
            background-color: #1e293b;
            color: #ffffff;
            border-radius: 12px 12px 0 0;
            padding: 16px 24px;
        }
        .modal-header .close { color: #ffffff; opacity: 0.85; font-size: 24px; }
        .modal-body { padding: 24px; }
        .quick-summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .quick-summary-label { font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase; }
        .quick-summary-val { font-size: 14px; color: #0f172a; font-weight: 700; }
        .batch-panel {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px;
            margin-top: 14px;
        }

        /* Solid Button Override for Light Bootstrap Theme */
        .btn-modal-solid {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #2563eb !important;
            color: #ffffff !important;
            border: 1px solid #1d4ed8 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 8px 20px !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-modal-solid:hover:not(:disabled) {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
        }
        .btn-modal-solid:disabled {
            background-color: #94a3b8 !important;
            border-color: #94a3b8 !important;
            color: #ffffff !important;
            cursor: not-allowed;
        }
        .btn-modal-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ffffff !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            text-decoration: none !important;
        }
        .btn-modal-secondary:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="invoice_close_list_mats.php?func=<?php echo $folder_func; ?>">
                        Close Invoice Management
                        <?php if (!$is_invoice_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#2563eb; font-size:11px; margin-left:8px;">CLOSING AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div id="page_alert_area"></div>

            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 230px;">
                    <label for="f_keyword">Invoice No / Customer / Book No</label>
                    <input class="form-control-minimal" id="f_keyword" type="text"
                           placeholder="Search Invoice No, Customer, Book..."
                           oninput="this.value = this.value.toUpperCase()"
                           onkeypress="if(event.key === 'Enter') loadInvoiceList();" />
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 145px;">
                    <label for="f_date_from">Invoice Date From</label>
                    <input class="form-control" id="f_date_from" type="date" onchange="loadInvoiceList()" />
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 145px;">
                    <label for="f_date_to">Invoice Date To</label>
                    <input class="form-control" id="f_date_to" type="date" onchange="loadInvoiceList()" />
                </div>
                <div class="filter-item" style="flex: 0.8; min-width: 140px;">
                    <label for="f_status">Invoice Status</label>
                    <select class="form-control" id="f_status" onchange="loadInvoiceList()">
                        <option value="">ALL STATUS</option>
                        <option value="OP">OP: OPEN</option>
                        <option value="CP">CP: COMPLETE</option>
                    </select>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button type="button" class="btn btn-search" onclick="loadInvoiceList()">
                        SEARCH
                    </button>
                    <?php if ($is_invoice_authorized): ?>
                        <a href="invoice_close_entry_mats.php?func=<?php echo $folder_func; ?>" class="btn-close-inv">
                            + CLOSE INVOICE
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 8px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                Invoice List for Closing <span id="record_count_badge" class="badge" style="background:#e2e8f0; color:#334155; margin-left:6px;">0</span>
                            </h4>                           
                        </div>

                        <div class="table-responsive">
                            <table id="close_inv_table" class="table">
                                <thead>
                                    <tr>
                                        <th class="text-center">Invoice No</th>
                                        <th class="text-center">Invoice Date</th>
                                        <th class="text-center">Ship Date</th>
                                        <th class="text-left">Customer</th>
                                        <th class="text-left">Book No</th>
                                        <th class="text-center">Material In</th>
                                        <th class="text-right">Net Wt (kg)</th>
                                        <th class="text-right">Ex. Rate</th>
                                        <th class="text-center">T3</th>
                                        <th class="text-center">T6</th>
                                        <th class="text-center">Status</th>
                                        <?php if ($is_invoice_authorized): ?>
                                            <th class="text-center">Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody id="close_inv_tbody">
                                    <tr>
                                        <td colspan="<?php echo $is_invoice_authorized ? '12' : '11'; ?>" class="text-center text-muted" style="padding:30px;">
                                            กำลังโหลดข้อมูล...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>" />
        <input type="hidden" id="is_authorized" value="<?php echo $is_invoice_authorized ? '1' : '0'; ?>" />
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- Quick Close / Edit Popup Modal -->
<div class="modal fade" id="quickCloseModal" tabindex="-1" role="dialog" aria-labelledby="quickCloseModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="quickCloseModalLabel" style="font-weight:700; font-size:17px;">
                    Quick Close Invoice : <span id="qc_title_invno" style="color:#93c5fd;">-</span>
                </h4>
            </div>
            <div class="modal-body">
                <div id="qc_warning_area"></div>

                <div class="quick-summary-box">
                    <div class="row">
                        <div class="col-sm-5">
                            <div class="quick-summary-label">Customer</div>
                            <div class="quick-summary-val" id="qc_customer">-</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="quick-summary-label">Material In</div>
                            <div class="quick-summary-val" style="color:#4338ca;" id="qc_material_in">-</div>
                        </div>
                        <div class="col-sm-2">
                            <div class="quick-summary-label">Invoice Date</div>
                            <div class="quick-summary-val" id="qc_inv_date">-</div>
                        </div>
                        <div class="col-sm-2">
                            <div class="quick-summary-label">Ship Date</div>
                            <div class="quick-summary-val" id="qc_ship_date">-</div>
                        </div>
                    </div>
                    <div class="row" style="margin-top:10px; padding-top:10px; border-top:1px dashed #cbd5e1;">
                        <div class="col-sm-3">
                            <div class="quick-summary-label">Package / Net Wt.</div>
                            <div class="quick-summary-val" id="qc_weight">-</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="quick-summary-label">Grand Total</div>
                            <div class="quick-summary-val" style="color:#047857;" id="qc_grand_total">-</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="quick-summary-label">Invoice Items</div>
                            <div class="quick-summary-val" id="qc_item_count">0 Items</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="quick-summary-label">Product Items</div>
                            <div class="quick-summary-val" id="qc_prod_count">0 Pieces</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="qc_invoice_no" />
                <div class="row">
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label style="font-weight:700; color:#334155;">Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="qc_status" style="font-weight:700;">
                                <option value="OP">OP: OPEN</option>
                                <option value="CP">CP: COMPLETE</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label style="font-weight:700; color:#334155;">Exchange Rate <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-right" id="qc_exchange_rate" value="0.00000"
                                   style="font-weight:700;"
                                   oninput="this.value = this.value.replace(/[^0-9.]/g, '')"
                                   onblur="formatQcExchangeRate()" />
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label style="font-weight:700; color:#334155;">T3 <span class="text-danger">*</span></label>
                            <select class="form-control" id="qc_t3" style="font-weight:700;">
                                <option value="B#1">B#1</option>
                                <option value="B#2">B#2</option>
                                <option value="NON">NON</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="form-group">
                            <label style="font-weight:700; color:#334155;">T6</label>
                            <select class="form-control" id="qc_t6" style="font-weight:700;" onchange="applyQcFieldRules()">
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

                <div class="batch-panel">
                    <div class="checkbox" style="margin-top:0; margin-bottom:10px;">
                        <label style="font-weight:700; color:#1e3a8a;">
                            <input type="checkbox" id="qc_update_batch" onchange="toggleQcBatchDates()" disabled />
                            Update T3, T6 and Exchange Rate for all Local Customer (MAT-Z% / LCL)
                        </label>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <label style="font-size:12px; color:#475569;">Period Shipment Date From</label>
                            <input type="date" class="form-control" id="qc_from_date" disabled />
                        </div>
                        <div class="col-sm-6">
                            <label style="font-size:12px; color:#475569;">Period Shipment Date To</label>
                            <input type="date" class="form-control" id="qc_to_date" disabled />
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background-color:#f8fafc; border-radius:0 0 12px 12px; display:flex; justify-content:space-between; align-items:center;">
                <a id="qc_full_entry_link" href="#" class="btn-modal-secondary">
                    &#8599; เปิดหน้า Entry เต็มรูปแบบ
                </a>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn-modal-solid" id="btn_qc_save" onclick="saveQuickClose()">
                        &#128190; บันทึกปิดบิล (SAVE)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var closeInvDataTable = null;
var isAuthorized = document.getElementById('is_authorized').value === '1';
var currentFunc  = document.getElementById('func').value;

$(document).ready(function() {
    loadInvoiceList();
    $(window).on('resize', function () {
        if (closeInvDataTable) closeInvDataTable.columns.adjust();
    });
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

function showPageAlert(type, message) {
    var cls = (type === 'success') ? 'alert-success' : 'alert-danger';
    var html = '<div class="alert ' + cls + ' alert-dismissible" role="alert" style="border-radius:8px; font-weight:600;">' +
               '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>' +
               escapeHtml(message) +
               '</div>';
    $('#page_alert_area').html(html);
}

function loadInvoiceList() {
    var keyword  = $('#f_keyword').val().trim();
    var dateFrom = $('#f_date_from').val();
    var dateTo   = $('#f_date_to').val();
    var status   = $('#f_status').val();

    var url = 'model/invoice_close_api.php?action=list' +
              '&keyword=' + encodeURIComponent(keyword) +
              '&date_from=' + encodeURIComponent(dateFrom) +
              '&date_to=' + encodeURIComponent(dateTo) +
              '&status=' + encodeURIComponent(status);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่สามารถโหลดข้อมูลได้');
                return;
            }
            renderInvoiceTable(resp.data || []);
        })
        .catch(function(err) {
            showPageAlert('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ API: ' + err.message);
        });
}

function renderInvoiceTable(rows) {
    $('#record_count_badge').text(rows.length);

    if (closeInvDataTable) {
        closeInvDataTable.destroy();
        closeInvDataTable = null;
    }

    var tbodyHtml = '';
    rows.forEach(function(r) {
        var invNo    = escapeHtml(r.INVOICE_NO);
        var invDate  = escapeHtml(r.INVOICE_DATE_FMT);
        var shipDate = escapeHtml(r.SHIPMENT_DATE_FMT);
        var custText = escapeHtml(r.CSTMSPPL_ID + (r.CONSIGNEE_COMPANY ? ': ' + r.CONSIGNEE_COMPANY : ''));
        var bookNo   = escapeHtml(r.BOOK_NO || '-');
        var matIn    = escapeHtml(r.INV1_MATERIALIN || '-');
        var netWt    = fmtNum(r.INV1_NETWEIGHT, 2);
        var exRate   = fmtNum(r.INV1_EXCHANGERATE, 5);
        var t3       = escapeHtml(r.T3 || '-');
        var t6       = escapeHtml(r.T6 || '-');
        var st       = escapeHtml(r.INV1_STATUS);
        var stBadge  = (st === 'CP')
            ? '<span class="badge-status badge-status-cp">CP: COMPLETE</span>'
            : '<span class="badge-status badge-status-op">OP: OPEN</span>';

        var actionCell = '';
        if (isAuthorized) {
            actionCell = '<td class="text-center">' +
                         '<button type="button" class="btn-quick-edit" onclick="event.stopPropagation(); openQuickClose(\'' + invNo + '\')">' +
                         '&#9998; Close/Edit</button>' +
                         '</td>';
        }

        tbodyHtml += '<tr onclick="goToDetail(\'' + invNo + '\')">' +
                     '<td class="text-center"><span class="badge-inv">' + invNo + '</span></td>' +
                     '<td class="text-center" data-order="' + escapeHtml(r.INVOICE_DATE) + '" style="font-weight:600;">' + invDate + '</td>' +
                     '<td class="text-center" data-order="' + escapeHtml(r.SHIPMENT_DATE) + '">' + shipDate + '</td>' +
                     '<td class="text-left" style="font-weight:600;">' + custText + '</td>' +
                     '<td class="text-left">' + bookNo + '</td>' +
                     '<td class="text-center"><span style="font-weight:700; color:#4338ca;">' + matIn + '</span></td>' +
                     '<td class="text-right" style="font-weight:600;">' + netWt + '</td>' +
                     '<td class="text-right" style="font-weight:600; color:#0f172a;">' + exRate + '</td>' +
                     '<td class="text-center"><span class="label label-default">' + t3 + '</span></td>' +
                     '<td class="text-center">' + (t6 !== '-' && t6 !== '' ? '<span class="label label-info">' + t6 + '</span>' : '-') + '</td>' +
                     '<td class="text-center">' + stBadge + '</td>' +
                     actionCell +
                     '</tr>';
    });

    $('#close_inv_tbody').html(tbodyHtml);

    var colDefs = [
        { targets: [0, 1, 2, 5, 8, 9, 10], className: 'text-center' },
        { targets: [6, 7], className: 'text-right' },
        { targets: [3, 4], className: 'text-left' }
    ];
    if (isAuthorized) {
        colDefs.push({ targets: [11], className: 'text-center', orderable: false });
    }

    closeInvDataTable = $('#close_inv_table').DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']],
        columnDefs: colDefs
    });

    setTimeout(function() {
        if (closeInvDataTable) closeInvDataTable.columns.adjust();
    }, 200);
}

function goToDetail(invNo) {
    window.location.assign('invoice_close_detail_mats.php?func=' + encodeURIComponent(currentFunc) + '&invno=' + encodeURIComponent(invNo));
}

// ================= QUICK CLOSE MODAL LOGIC =================
function openQuickClose(invNo) {
    $('#qc_warning_area').html('');
    $('#qc_title_invno').text(invNo);
    $('#qc_invoice_no').val(invNo);
    $('#qc_full_entry_link').attr('href', 'invoice_close_entry_mats.php?func=' + encodeURIComponent(currentFunc) + '&invno=' + encodeURIComponent(invNo));

    fetch('model/invoice_close_api.php?action=detail&invoice_no=' + encodeURIComponent(invNo))
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่พบข้อมูล Invoice');
                return;
            }
            var h = resp.data.heading;
            var items = resp.data.invoice_items || [];
            var prods = resp.data.product_items || [];

            if (resp.warnings && resp.warnings.length > 0) {
                var wHtml = '<div class="alert alert-warning" style="border-radius:8px; font-weight:600; margin-bottom:15px;">' +
                            '<strong>คำเตือน:</strong><ul style="margin:5px 0 0 18px;">';
                resp.warnings.forEach(function(w) { wHtml += '<li>' + escapeHtml(w) + '</li>'; });
                wHtml += '</ul></div>';
                $('#qc_warning_area').html(wHtml);
            }

            $('#qc_customer').text(h.CUSTOMER_DISPLAY);
            $('#qc_material_in').text(h.INV1_MATERIALIN || '-');
            $('#qc_inv_date').text(h.INVOICE_DATE_FMT);
            $('#qc_ship_date').text(h.SHIPMENT_DATE_FMT);
            $('#qc_weight').text(fmtNum(h.INV1_PACKAGE, 0) + ' Pkg / ' + fmtNum(h.INV1_NETWEIGHT, 2) + ' kg');
            $('#qc_grand_total').text(fmtNum(h.GRAND_TOTAL_AMOUNT, 2) + ' ' + h.CURRENCY_ID);
            $('#qc_item_count').text(items.length + ' Items');
            $('#qc_prod_count').text(prods.length + ' Pieces');

            $('#qc_status').val(h.INV1_STATUS);
            $('#qc_exchange_rate').val(h.INV1_EXCHANGERATE);
            $('#qc_t3').val(h.T3);
            $('#qc_t6').val(h.T6);

            var todayStr = new Date().toISOString().split('T')[0];
            $('#qc_update_batch').prop('checked', false);
            $('#qc_from_date').val(h.SHIPMENT_DATE || todayStr).prop('disabled', true);
            $('#qc_to_date').val(h.SHIPMENT_DATE || todayStr).prop('disabled', true);

            applyQcFieldRules();
            $('#quickCloseModal').modal('show');
        })
        .catch(function(err) {
            showPageAlert('error', 'เกิดข้อผิดพลาด: ' + err.message);
        });
}

function formatQcExchangeRate() {
    var val = parseFloat($('#qc_exchange_rate').val());
    if (isNaN(val)) val = 0;
    $('#qc_exchange_rate').val(val.toFixed(5));
}

function applyQcFieldRules() {
    var t6 = $('#qc_t6').val();
    if (t6 === 'MIL') {
        $('#qc_update_batch').prop('checked', false).prop('disabled', true);
        $('#qc_exchange_rate').prop('disabled', true);
        toggleQcBatchDates();
    } else if (t6 === 'OLS') {
        $('#qc_update_batch').prop('disabled', false);
        $('#qc_exchange_rate').prop('disabled', false);
        toggleQcBatchDates();
    } else {
        $('#qc_update_batch').prop('checked', false).prop('disabled', true);
        $('#qc_exchange_rate').prop('disabled', false);
        toggleQcBatchDates();
    }
}

function toggleQcBatchDates() {
    var checked = $('#qc_update_batch').is(':checked');
    $('#qc_from_date').prop('disabled', !checked);
    $('#qc_to_date').prop('disabled', !checked);
}

function saveQuickClose() {
    formatQcExchangeRate();
    var payload = {
        action: 'save',
        invoice_no: $('#qc_invoice_no').val(),
        status: $('#qc_status').val(),
        exchange_rate: parseFloat($('#qc_exchange_rate').val()) || 0,
        t3: $('#qc_t3').val(),
        t6: $('#qc_t6').val(),
        update_exchange_rate: $('#qc_update_batch').is(':checked') ? 1 : 0,
        from_date: $('#qc_from_date').val(),
        to_date: $('#qc_to_date').val()
    };

    var btn = $('#btn_qc_save');
    btn.prop('disabled', true).html('&#8987; กำลังบันทึก...');

    fetch('model/invoice_close_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(resp) {
        btn.prop('disabled', false).html('&#128190; บันทึกปิดบิล (SAVE)');
        if (resp.status === 'success') {
            $('#quickCloseModal').modal('hide');
            showPageAlert('success', resp.message);
            loadInvoiceList();
        } else {
            alert(resp.message || 'บันทึกข้อมูลไม่สำเร็จ');
        }
    })
    .catch(function(err) {
        btn.prop('disabled', false).html('&#128190; บันทึกปิดบิล (SAVE)');
        alert('เกิดข้อผิดพลาด: ' + err.message);
    });
}
</script>
</body>
</html>