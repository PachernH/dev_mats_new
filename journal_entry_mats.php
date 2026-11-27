<?php
// journal_entry_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_dept = strtoupper(trim($department_func));
$is_journal_authorized = ($current_dept === 'FN' || $current_dept === 'IT');

if (!$is_journal_authorized) {
    header("Location: journal_list_mats.php?func=" . $folder_func);
    exit();
}

$mode           = isset($_GET['mode']) ? htmlspecialchars(trim($_GET['mode']), ENT_QUOTES, 'UTF-8') : 'create';
$journal_type   = isset($_GET['type']) ? htmlspecialchars(trim($_GET['type']), ENT_QUOTES, 'UTF-8') : 'PV';
$journal_period = isset($_GET['period']) ? htmlspecialchars(trim($_GET['period']), ENT_QUOTES, 'UTF-8') : date('ym');
$journal_no     = isset($_GET['no']) ? htmlspecialchars(trim($_GET['no']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title><?php echo ($mode === 'edit') ? 'Edit Journal : ' . $journal_no : 'Create New Journal'; ?> - Meyer Aluminium Thailand Co.,LTD.</title>
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

        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 5px;
        }

        .form-control {
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            height: 38px;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }
        .form-control[readonly], .form-control:disabled {
            background-color: #f1f5f9;
            color: #64748b;
        }

        .table-entry th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 10px 6px !important;
            border-bottom: 2px solid #e2e8f0 !important;
            white-space: nowrap;
        }
        .table-entry td {
            padding: 6px 4px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-entry .form-control {
            height: 34px;
            font-size: 13px;
            padding: 4px 8px;
        }

        .btn-dc {
            width: 38px;
            height: 34px;
            font-weight: 800;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-dc-d { background-color: #2563eb; color: #ffffff; }
        .btn-dc-c { background-color: #d97706; color: #ffffff; }

        .btn-add-row {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px dashed #93c5fd;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-row:hover { background-color: #dbeafe; }

        .btn-remove-row {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            width: 30px;
            height: 30px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-remove-row:hover { background-color: #dc2626; color: #ffffff; }

        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 16px 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .summary-row:last-child { border-bottom: none; }

        .btn-save-main {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 26px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.25);
        }
        .btn-save-main:hover:not(:disabled) { background-color: #1d4ed8; }
        .btn-save-main:disabled { background-color: #94a3b8; cursor: not-allowed; }

        .btn-cancel-main {
            background-color: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none !important;
        }
        .btn-cancel-main:hover { background-color: #f1f5f9; color: #0f172a; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="journal_list_mats.php?func=<?php echo $folder_func; ?>">
                        <?php echo ($mode === 'edit') ? 'Edit Journal Voucher : <span style="color:#2563eb;">' . $journal_no . '</span>' : 'Create New Journal Voucher'; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div id="page_alert_area"></div>

            <!-- Datalists สำหรับตัวช่วยพิมพ์ (Autocomplete จากประวัติเก่า + พิมพ์ใหม่เองได้) -->
            <datalist id="supplier_datalist"></datalist>
            <datalist id="department_datalist"></datalist>
            <datalist id="cashflow_datalist"></datalist>
            <datalist id="costcenter_datalist"></datalist>
            <datalist id="account_datalist"></datalist>

            <!-- Section 1: Journal Heading -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>1. ข้อมูลส่วนหัวเอกสาร (Heading Information)</span>
                    <span class="badge" style="background-color: <?php echo ($mode === 'edit') ? '#d97706' : '#059669'; ?>;">
                        <?php echo ($mode === 'edit') ? 'EDIT MODE' : 'NEW RECORD'; ?>
                    </span>
                </div>

                <div class="row">
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Journal Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="j_type" <?php echo ($mode === 'edit') ? 'disabled' : ''; ?>>
                                <option value="PV" <?php echo ($journal_type === 'PV') ? 'selected' : ''; ?>>PV (Payment)</option>
                                <option value="PC" <?php echo ($journal_type === 'PC') ? 'selected' : ''; ?>>PC (Petty Cash)</option>
                                <option value="JV" <?php echo ($journal_type === 'JV') ? 'selected' : ''; ?>>JV (Journal)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Period (YYMM) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="j_period" maxlength="4"
                                   value="<?php echo $journal_period; ?>"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   <?php echo ($mode === 'edit') ? 'readonly' : ''; ?> />
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>Journal No <small class="text-muted">(Auto Run)</small></label>
                            <input type="text" class="form-control" id="j_no" maxlength="9"
                                value="<?php echo $journal_no; ?>"
                                placeholder="ระบบรันเลขที่อัตโนมัติ"
                                readonly tabindex="-1" style="cursor: not-allowed;" />
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Transaction Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="j_trans_date" value="<?php echo date('Y-m-d'); ?>" onchange="syncPeriodFromDate()" />
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="j_due_date" value="<?php echo date('Y-m-d'); ?>" />
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 col-sm-12">
                        <div class="form-group">
                            <label>Pay To / Supplier (CSTMSPPL_ID)</label>
                            <input type="text" class="form-control" id="j_supplier_input" list="supplier_datalist"
                                   placeholder="พิมพ์รหัสหรือชื่อบริษัทเพื่อค้นหา..." onchange="onSupplierChange()" />
                            <input type="hidden" id="j_cstmsppl_id" />
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" class="form-control" id="j_department" list="department_datalist"
                                   maxlength="15" placeholder="เลือกหรือพิมพ์เอง"
                                   oninput="this.value = this.value.toUpperCase()" />
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Cash Flow Type</label>
                            <input type="text" class="form-control" id="j_cash_flow" list="cashflow_datalist"
                                   maxlength="15" placeholder="เลือกหรือพิมพ์เอง"
                                   oninput="this.value = this.value.toUpperCase()" />
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>Cheque No</label>
                            <input type="text" class="form-control" id="j_cheque_no" maxlength="7"
                                   oninput="this.value = this.value.toUpperCase()" />
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>เลขที่หนังสือรับรองหักภาษี ณ ที่จ่าย</label>
                            <input type="text" class="form-control" id="j_taxdeduct_no" readonly placeholder="Auto Run เมื่อมีรายการหักภาษี" />
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>ภ.ง.ด. (Tax Category)</label>
                            <select class="form-control" id="j_tax_category">
                                <option value="">-- ไม่มี --</option>
                                <option value="3">3 (ภ.ง.ด. 3)</option>
                                <option value="53">53 (ภ.ง.ด. 53)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>ผู้จ่ายเงิน (Pay Condition)</label>
                            <select class="form-control" id="j_pay_condition">
                                <option value="0">-- ไม่ระบุ --</option>
                                <option value="1">1 : หักภาษี ณ ที่จ่าย</option>
                                <option value="2">2 : ออกให้ตลอดไป</option>
                                <option value="3">3 : ออกให้ครั้งเดียว</option>
                                <option value="4">4 : อื่นๆ</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-12">
                        <div class="form-group">
                            <label>Remark (หมายเหตุ)</label>
                            <input type="text" class="form-control" id="j_remark" maxlength="250" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Journal Detail Items -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>2. รายการลงบัญชี (Journal Detail Items)</span>
                    <button type="button" class="btn-add-row" onclick="addDetailRow()">
                        + เพิ่มรายการบัญชี (Add Row)
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-entry" id="entry_items_table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:45px;">#</th>
                                <th style="width:140px;">Account Code <span class="text-danger">*</span></th>
                                <th style="min-width:190px;">Account Name</th>
                                <th style="width:130px;">Invoice / Ref.</th>
                                <th style="width:115px;">Cost Center</th>
                                <th style="width:145px;">Expense Category</th>
                                <th class="text-center" style="width:55px;">D/C</th>
                                <th class="text-right" style="width:130px;">Exclude VAT</th>
                                <th class="text-right" style="width:85px;">% Tax</th>
                                <th class="text-right" style="width:130px;">Amount</th>
                                <th class="text-center" style="width:45px;">ลบ</th>
                            </tr>
                        </thead>
                        <tbody id="entry_items_tbody"></tbody>
                    </table>
                </div>

                <!-- Summary & Save Bar -->
                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-6">
                        <div class="alert alert-info" style="font-size:12.5px; border-radius:8px;">
                            <strong>คำแนะนำการบันทึก:</strong>
                            <ul style="margin: 5px 0 0 18px; padding:0;">
                                <li>ช่อง <strong>Account Code, Department, Cost Center, Cash Flow</strong> สามารถเลือกจากรายการเดิมหรือพิมพ์รหัสใหม่เองได้ทันที</li>
                                <li>กดปุ่ม <strong>D</strong> หรือ <strong>C</strong> เพื่อสลับฝั่งเดบิต/เครดิต</li>
                                <li>หากเลือก <strong>Expense Category</strong> ระบบจะกำหนด % ภาษี ณ ที่จ่ายและคำนวณยอดภาษีให้อัตโนมัติ</li>
                                <li>หาก <strong>Balance = 0.00</strong> และรหัสบัญชีขึ้นต้นด้วย <strong>61</strong> ระบบจะตั้งค่า Check Item = C ให้อัตโนมัติ</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="summary-box">
                            <div class="summary-row">
                                <span>Total Debits (ยอดรวมเดบิต):</span>
                                <strong id="sum_debit">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Total Credits (ยอดรวมเครดิต):</span>
                                <strong id="sum_credit">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Balance (ผลต่าง):</span>
                                <strong id="sum_balance">0.00</strong>
                            </div>
                            <div class="summary-row" style="margin-top:8px; padding-top:8px; border-top:1px solid #cbd5e1;">
                                <span>Total Exclude VAT (ฐานภาษีรวม):</span>
                                <strong id="sum_exvat">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Total Tax Amount (ภาษีหัก ณ ที่จ่ายรวม):</span>
                                <strong style="color:#b45309;" id="sum_tax">0.00</strong>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;">
                            <a href="journal_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-cancel-main">
                                ยกเลิก (Cancel)
                            </a>
                            <button type="button" class="btn-save-main" id="btn_save_journal" onclick="saveJournal()">
                                &#128190; บันทึกข้อมูล (SAVE JOURNAL)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>" />
        <input type="hidden" id="h_mode" value="<?php echo $mode; ?>" />
        <input type="hidden" id="h_orig_type" value="<?php echo $journal_type; ?>" />
        <input type="hidden" id="h_orig_period" value="<?php echo $journal_period; ?>" />
        <input type="hidden" id="h_orig_no" value="<?php echo $journal_no; ?>" />
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var currentFunc = document.getElementById('func').value;
var mode        = document.getElementById('h_mode').value;
var origType    = document.getElementById('h_orig_type').value;
var origPeriod  = document.getElementById('h_orig_period').value;
var origNo      = document.getElementById('h_orig_no').value;

var masterSuppliers = [];
var masterAccounts  = [];

const EXPENSE_TAX_MAP = {
    '': 0,
    'ค่าขนส่ง': 1,
    'ค่าประกันภัย': 1,
    'ค่าระวางสินค้า': 1,
    'ค่าโฆษณา': 2,
    'ค่าบริการ': 3,
    'ค่าจ้างทำของ': 3,
    'ค่าเช่า': 5
};

const TAX_PERCENT_OPTIONS = ['0', '1', '1.5', '2', '3', '4', '5', '6', '7', '8'];

$(document).ready(function() {
    loadMasterData().then(function() {
        if (mode === 'edit' && origNo !== '') {
            loadExistingJournal();
        } else {
            addDetailRow({ debit_credit: 'D' });
            addDetailRow({ debit_credit: 'C' });
        }
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
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function syncPeriodFromDate() {
    if (mode === 'edit') return;
    var dt = $('#j_trans_date').val();
    if (dt && dt.length >= 7) {
        var yy = dt.substring(2, 4);
        var mm = dt.substring(5, 7);
        $('#j_period').val(yy + mm);
    }
}

function loadMasterData() {
    return fetch('model/journal_api.php?action=master_data')
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่สามารถโหลดข้อมูล Master Data ได้');
                return;
            }
            var d = resp.data;
            masterSuppliers = d.suppliers || [];
            masterAccounts  = d.accounts || [];

            // 1) Supplier Datalist
            var supHtml = '';
            masterSuppliers.forEach(function(s) {
                var id   = (s.CSTMSPPL_ID || '').trim();
                var name = (s.EN_COMPANY || s.TH_COMPANY || '').trim();
                supHtml += '<option value="' + escapeHtml(id + ' : ' + name) + '"></option>';
            });
            $('#supplier_datalist').html(supHtml);

            // 2) Account Datalist
            var accHtml = '';
            masterAccounts.forEach(function(a) {
                var code = (a.ACCNT_CODE || '').trim();
                var name = (a.ACCNT_NAME || '').trim();
                accHtml += '<option value="' + escapeHtml(code) + '">' + escapeHtml(code + ' - ' + name) + '</option>';
            });
            $('#account_datalist').html(accHtml);

            // 3) Department & Cost Center Datalists
            var deptHtml = '';
            (d.departments || []).forEach(function(dep) {
                var code = (dep.CODE || '').trim();
                deptHtml += '<option value="' + escapeHtml(code) + '"></option>';
            });
            $('#department_datalist').html(deptHtml);
            $('#costcenter_datalist').html(deptHtml);

            // 4) Cash Flow Datalist
            var cfHtml = '';
            (d.cash_flows || []).forEach(function(cf) {
                var code = (cf.CODE || '').trim();
                cfHtml += '<option value="' + escapeHtml(code) + '"></option>';
            });
            $('#cashflow_datalist').html(cfHtml);

            if (mode === 'create') {
                $('#j_cash_flow').val('TAX');
            }
        });
}

function onSupplierChange() {
    var rawVal = $('#j_supplier_input').val().trim();
    var supId  = rawVal.split(':')[0].trim();
    $('#j_cstmsppl_id').val(supId);

    var found = masterSuppliers.find(function(s) {
        return (s.CSTMSPPL_ID || '').trim() === supId;
    });
    if (found && found.TAX_CATEGORY) {
        var taxCat = found.TAX_CATEGORY.trim();
        if (taxCat === '03') taxCat = '3';
        $('#j_tax_category').val(taxCat);
    }
}

function loadExistingJournal() {
    var url = 'model/journal_api.php?action=detail' +
              '&journal_type=' + encodeURIComponent(origType) +
              '&journal_period=' + encodeURIComponent(origPeriod) +
              '&journal_no=' + encodeURIComponent(origNo);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่พบข้อมูลเอกสาร');
                return;
            }
            var h     = resp.data.heading;
            var items = resp.data.items || [];

            $('#j_type').val(h.JOURNAL_TYPE);
            $('#j_period').val(h.JOURNAL_PERIOD);
            $('#j_no').val(h.JOURNAL_NO);
            $('#j_trans_date').val(h.TRANSACTION_DATE_ISO);
            $('#j_due_date').val(h.DUE_DATE_ISO);

            var supId   = (h.CSTMSPPL_ID || '').trim();
            var supName = (h.EN_COMPANY || h.TH_COMPANY || '').trim();
            $('#j_cstmsppl_id').val(supId);
            $('#j_supplier_input').val(supId ? (supId + (supName ? ' : ' + supName : '')) : '');

            $('#j_department').val((h.DEPARTMENT || '').trim());
            $('#j_cash_flow').val((h.CASH_FLOW || '').trim());
            $('#j_cheque_no').val((h.CHEQUE_NO || '').trim());
            $('#j_taxdeduct_no').val((h.TAXDEDUCT_NO || '').trim());

            var taxCat = (h.TAX_CATEGORY || '').trim();
            if (taxCat === '03') taxCat = '3';
            $('#j_tax_category').val(taxCat);

            $('#j_pay_condition').val(h.PAY_CONDITION || 0);
            $('#j_remark').val(h.REMARK || '');

            $('#entry_items_tbody').empty();
            if (items.length > 0) {
                items.forEach(function(it) {
                    addDetailRow(it);
                });
            } else {
                addDetailRow({ debit_credit: 'D' });
            }
            recalculateTotals();
        });
}

function addDetailRow(data) {
    data = data || {};
    var accCode  = (data.ACCOUNT_CODE || data.account_code || '').trim();
    var accName  = (data.ACCOUNT_NAME || data.account_name || '').trim();
    var invNo    = (data.INVOICE_NO || data.invoice_no || '').trim();
    var costCtr  = (data.COST_CENTER || data.cost_center || '').trim();
    var expCat   = (data.EXPENSE_CATEGORY || data.expense_category || '').trim();
    var dc       = (data.DEBIT_CREDIT || data.debit_credit || 'C').trim();
    var exVat    = parseFloat(data.EXCLUDEVAT_AMOUNT || data.excludevat_amount || 0).toFixed(2);
    var taxPct   = String(data.TAX_PERCENT !== undefined ? data.TAX_PERCENT : (data.tax_percent || '0'));
    var amount   = parseFloat(data.AMOUNT || data.amount || 0).toFixed(2);

    var expOptions = '';
    Object.keys(EXPENSE_TAX_MAP).forEach(function(k) {
        var label = (k === '') ? '-- ไม่มี --' : k;
        var sel = (k === expCat) ? 'selected' : '';
        expOptions += '<option value="' + escapeHtml(k) + '" ' + sel + '>' + escapeHtml(label) + '</option>';
    });

    var taxPctOptions = '';
    TAX_PERCENT_OPTIONS.forEach(function(p) {
        var sel = (parseFloat(p) === parseFloat(taxPct)) ? 'selected' : '';
        taxPctOptions += '<option value="' + p + '" ' + sel + '>' + p + '%</option>';
    });

    var dcClass = (dc === 'D') ? 'btn-dc-d' : 'btn-dc-c';
    var taxDisabled = (expCat === '') ? 'disabled' : '';

    var trHtml = '<tr class="detail-row">' +
                 '<td class="text-center row-index" style="font-weight:700; color:#64748b;">1</td>' +
                 '<td><input type="text" class="form-control inp-acc-code" list="account_datalist" value="' + escapeHtml(accCode) + '" ' +
                     'oninput="this.value = this.value.toUpperCase()" onchange="onAccountCodeChange(this)" placeholder="รหัสบัญชี" /></td>' +
                 '<td><input type="text" class="form-control inp-acc-name" value="' + escapeHtml(accName) + '" placeholder="ชื่อบัญชี" /></td>' +
                 '<td><input type="text" class="form-control inp-inv-no" value="' + escapeHtml(invNo) + '" placeholder="Invoice" /></td>' +
                 '<td><input type="text" class="form-control inp-cost-center" list="costcenter_datalist" maxlength="15" value="' + escapeHtml(costCtr) + '" ' +
                     'oninput="this.value = this.value.toUpperCase()" placeholder="Cost Center" /></td>' +
                 '<td><select class="form-control inp-exp-cat" onchange="onExpenseCatChange(this)">' + expOptions + '</select></td>' +
                 '<td class="text-center"><button type="button" class="btn-dc ' + dcClass + '" onclick="toggleDC(this)">' + dc + '</button></td>' +
                 '<td><input type="number" step="0.01" class="form-control text-right inp-exvat" value="' + exVat + '" onchange="onRowAmountChange(this)" /></td>' +
                 '<td><select class="form-control text-right inp-tax-pct" ' + taxDisabled + ' onchange="onRowAmountChange(this)">' + taxPctOptions + '</select></td>' +
                 '<td><input type="text" class="form-control text-right inp-amount" value="' + fmtNum(amount, 2) + '" readonly style="font-weight:700;" /></td>' +
                 '<td class="text-center"><button type="button" class="btn-remove-row" onclick="removeDetailRow(this)" title="ลบแถว">&times;</button></td>' +
                 '</tr>';

    $('#entry_items_tbody').append(trHtml);
    renumberRows();
}

function removeDetailRow(btn) {
    if ($('#entry_items_tbody tr.detail-row').length <= 1) {
        alert('ต้องมีรายการบัญชีอย่างน้อย 1 รายการ');
        return;
    }
    $(btn).closest('tr').remove();
    renumberRows();
    recalculateTotals();
}

function renumberRows() {
    $('#entry_items_tbody tr.detail-row').each(function(idx) {
        $(this).find('.row-index').text(String(idx + 1).padStart(3, '0'));
    });
}

function onAccountCodeChange(input) {
    var code = $(input).val().trim();
    var tr   = $(input).closest('tr');
    var found = masterAccounts.find(function(a) {
        return (a.ACCNT_CODE || '').trim() === code;
    });
    if (found) {
        tr.find('.inp-acc-name').val((found.ACCNT_NAME || '').trim());
    }
}

function toggleDC(btn) {
    var $btn = $(btn);
    if ($btn.text().trim() === 'D') {
        $btn.text('C').removeClass('btn-dc-d').addClass('btn-dc-c');
    } else {
        $btn.text('D').removeClass('btn-dc-c').addClass('btn-dc-d');
    }
    recalculateTotals();
}

function onExpenseCatChange(select) {
    var expCat = $(select).val();
    var tr     = $(select).closest('tr');
    var $pct   = tr.find('.inp-tax-pct');

    if (expCat === '') {
        $pct.val('0').prop('disabled', true);
    } else {
        var defaultPct = EXPENSE_TAX_MAP[expCat] || 0;
        $pct.val(String(defaultPct)).prop('disabled', false);
    }
    onRowAmountChange(select);
}

function onRowAmountChange(el) {
    var tr     = $(el).closest('tr');
    var expCat = tr.find('.inp-exp-cat').val();
    var exVat  = Math.abs(parseFloat(tr.find('.inp-exvat').val()) || 0);
    tr.find('.inp-exvat').val(exVat.toFixed(2));

    var taxPct = parseFloat(tr.find('.inp-tax-pct').val()) || 0;
    var amount = (expCat === '') ? exVat : (exVat * (taxPct / 100));

    tr.find('.inp-amount').val(fmtNum(amount, 2));
    recalculateTotals();
}

function recalculateTotals() {
    var totalDebit  = 0;
    var totalCredit = 0;
    var totalExVat  = 0;
    var totalTax    = 0;

    $('#entry_items_tbody tr.detail-row').each(function() {
        var tr     = $(this);
        var dc     = tr.find('.btn-dc').text().trim();
        var expCat = tr.find('.inp-exp-cat').val();
        var exVat  = Math.abs(parseFloat(tr.find('.inp-exvat').val()) || 0);
        var taxPct = parseFloat(tr.find('.inp-tax-pct').val()) || 0;
        var amount = (expCat === '') ? exVat : (exVat * (taxPct / 100));

        if (expCat !== '') {
            totalExVat += exVat;
            totalTax   += amount;
        }
        if (dc === 'D') {
            totalDebit += amount;
        } else {
            totalCredit += amount;
        }
    });

    var balance = totalDebit - totalCredit;

    $('#sum_debit').text(fmtNum(totalDebit, 2));
    $('#sum_credit').text(fmtNum(totalCredit, 2));
    $('#sum_balance').text(fmtNum(balance, 2)).css('color', Math.abs(balance) > 0.009 ? '#dc2626' : '#059669');
    $('#sum_exvat').text(fmtNum(totalExVat, 2));
    $('#sum_tax').text(fmtNum(totalTax, 2));
}

function saveJournal() {
    var items = [];
    var hasError = false;

    $('#entry_items_tbody tr.detail-row').each(function(idx) {
        var tr      = $(this);
        var accCode = tr.find('.inp-acc-code').val().trim();
        var accName = tr.find('.inp-acc-name').val().trim();
        var invNo   = tr.find('.inp-inv-no').val().trim();
        var costCtr = tr.find('.inp-cost-center').val().trim();
        var expCat  = tr.find('.inp-exp-cat').val();
        var dc      = tr.find('.btn-dc').text().trim();
        var exVat   = parseFloat(tr.find('.inp-exvat').val()) || 0;
        var taxPct  = parseFloat(tr.find('.inp-tax-pct').val()) || 0;

        if (accCode.length <= 3) {
            showPageAlert('error', 'รายการที่ ' + (idx + 1) + ': กรุณาระบุรหัสบัญชีให้ถูกต้อง (มากกว่า 3 ตัวอักษร)');
            hasError = true;
            return false;
        }

        items.push({
            account_code: accCode,
            account_name: accName,
            invoice_no: invNo,
            cost_center: costCtr,
            expense_category: expCat,
            debit_credit: dc,
            excludevat_amount: exVat,
            tax_percent: taxPct
        });
    });

    if (hasError) return;

    var hasTaxItem = items.some(function(i) { return i.expense_category !== ''; });
    var taxCat     = $('#j_tax_category').val();
    if (hasTaxItem && taxCat === '') {
        showPageAlert('error', 'มีรายการหักภาษี ณ ที่จ่าย กรุณาเลือกประเภท ภ.ง.ด. (3 หรือ 53) ที่ส่วนหัวเอกสารด้วยครับ');
        return;
    }

    var payload = {
        action: 'save',
        mode: mode,
        journal_type: $('#j_type').val(),
        journal_period: $('#j_period').val().trim(),
        journal_no: $('#j_no').val().trim(),
        transaction_date: $('#j_trans_date').val(),
        due_date: $('#j_due_date').val(),
        cstmsppl_id: $('#j_cstmsppl_id').val().trim(),
        department: $('#j_department').val().trim(),
        cash_flow: $('#j_cash_flow').val().trim(),
        cheque_no: $('#j_cheque_no').val().trim(),
        taxdeduct_no: $('#j_taxdeduct_no').val().trim(),
        tax_category: taxCat,
        pay_condition: parseInt($('#j_pay_condition').val(), 10) || 0,
        remark: $('#j_remark').val().trim(),
        items: items
    };

    var $btn = $('#btn_save_journal');
    $btn.prop('disabled', true).html('&#8987; กำลังบันทึก...');

    fetch('model/journal_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(resp) {
        $btn.prop('disabled', false).html('&#128190; บันทึกข้อมูล (SAVE JOURNAL)');
        if (resp.status === 'success') {
            var d = resp.data;
            window.location.assign(
                'journal_detail_mats.php?func=' + encodeURIComponent(currentFunc) +
                '&type=' + encodeURIComponent(d.journal_type) +
                '&period=' + encodeURIComponent(d.journal_period) +
                '&no=' + encodeURIComponent(d.journal_no) +
                '&msg=' + encodeURIComponent(resp.message)
            );
        } else {
            showPageAlert('error', resp.message || 'บันทึกข้อมูลไม่สำเร็จ');
        }
    })
    .catch(function(err) {
        $btn.prop('disabled', false).html('&#128190; บันทึกข้อมูล (SAVE JOURNAL)');
        showPageAlert('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message);
    });
}
</script>
</body>
</html>