<?php
// journal_detail_mats.php
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

$journal_type   = isset($_GET['type']) ? htmlspecialchars(trim($_GET['type']), ENT_QUOTES, 'UTF-8') : '';
$journal_period = isset($_GET['period']) ? htmlspecialchars(trim($_GET['period']), ENT_QUOTES, 'UTF-8') : '';
$journal_no     = isset($_GET['no']) ? htmlspecialchars(trim($_GET['no']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Journal Detail : <?php echo $journal_no; ?> - Meyer Aluminium Thailand Co.,LTD.</title>
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .info-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }

        .summary-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 16px 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-row strong { color: #0f172a; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none !important;
        }
        .btn-back { background-color: #ffffff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-back:hover { background-color: #f1f5f9; color: #0f172a; }

        .btn-edit { background-color: #2563eb; color: #ffffff; }
        .btn-edit:hover { background-color: #1d4ed8; color: #ffffff; }

        .btn-delete { background-color: #dc2626; color: #ffffff; }
        .btn-delete:hover { background-color: #b91c1c; color: #ffffff; }

        .btn-print { background-color: #059669; color: #ffffff; }
        .btn-print:hover { background-color: #047857; color: #ffffff; }

        .table-detail th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0 !important;
            white-space: nowrap;
        }
        .table-detail td {
            font-size: 13px;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9;
        }
        .badge-dc-d { background-color: #dbeafe; color: #1d4ed8; padding: 3px 8px; border-radius: 4px; font-weight: 700; }
        .badge-dc-c { background-color: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 4px; font-weight: 700; }
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
                        Journal Voucher Detail : <span style="color:#2563eb;"><?php echo $journal_no; ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div id="page_alert_area"></div>

            <!-- Action Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
                <a href="journal_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-action btn-back">
                    กลับหน้ารายการ (Back to List)
                </a>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <!-- ปุ่มเตรียมรองรับงานพิมพ์ในเฟสถัดไป -->
                    <button type="button" class="btn-action btn-print" onclick="previewPrintForm()">
                        &#128424; Print Voucher / 50 ทวิ
                    </button>

                    <?php if ($is_journal_authorized): ?>
                        <a href="journal_entry_mats.php?func=<?php echo $folder_func; ?>&mode=edit&type=<?php echo $journal_type; ?>&period=<?php echo $journal_period; ?>&no=<?php echo $journal_no; ?>"
                           class="btn-action btn-edit">
                            &#9998; Edit (แก้ไข)
                        </a>
                        <button type="button" class="btn-action btn-delete" onclick="deleteJournal()">
                            &#128465; Delete (ลบ)
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Heading Info Card -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>ข้อมูลส่วนหัวเอกสาร (Journal Heading)</span>
                    <span id="d_journal_badge" class="badge" style="background-color:#2563eb; font-size:12px;">-</span>
                </div>

                <div class="info-grid">
                    <div class="info-box">
                        <div class="info-label">Journal Type / Period</div>
                        <div class="info-value" id="d_type_period">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Journal No</div>
                        <div class="info-value" style="color:#1d4ed8;" id="d_journal_no">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Transaction Date</div>
                        <div class="info-value" id="d_trans_date">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Due Date</div>
                        <div class="info-value" id="d_due_date">-</div>
                    </div>
                    <div class="info-box" style="grid-column: span 2;">
                        <div class="info-label">Pay To / Supplier (CSTMSPPL_ID)</div>
                        <div class="info-value" id="d_supplier">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Department</div>
                        <div class="info-value" id="d_department">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Cash Flow Type</div>
                        <div class="info-value" id="d_cash_flow">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Cheque No</div>
                        <div class="info-value" id="d_cheque_no">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">เลขที่หนังสือรับรองหักภาษี ณ ที่จ่าย</div>
                        <div class="info-value" style="color:#b45309;" id="d_taxdeduct_no">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">ภ.ง.ด. (Tax Category)</div>
                        <div class="info-value" id="d_tax_category">-</div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">ผู้จ่ายเงิน (Pay Condition)</div>
                        <div class="info-value" id="d_pay_condition">-</div>
                    </div>
                    <div class="info-box" style="grid-column: 1 / -1;">
                        <div class="info-label">Remark (หมายเหตุ)</div>
                        <div class="info-value" style="font-weight:600;" id="d_remark">-</div>
                    </div>
                </div>
            </div>

            <!-- Detail Items Card -->
            <div class="dashboard-card">
                <div class="section-title">
                    <span>รายการลงบัญชี (Journal Detail Items)</span>
                    <span id="d_item_count" class="badge" style="background:#e2e8f0; color:#334155;">0 Items</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-detail">
                        <thead>
                            <tr>
                                <th class="text-center">Item</th>
                                <th class="text-center">Account Code</th>
                                <th class="text-left">Account Name</th>
                                <th class="text-left">Invoice / Ref.</th>
                                <th class="text-center">Cost Center</th>
                                <th class="text-left">Expense Category</th>
                                <th class="text-center">D/C</th>
                                <th class="text-right">Exclude VAT</th>
                                <th class="text-right">% Tax</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Check</th>
                            </tr>
                        </thead>
                        <tbody id="d_items_tbody">
                            <tr>
                                <td colspan="11" class="text-center text-muted" style="padding:25px;">กำลังโหลดข้อมูล...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Totals -->
                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-6"></div>
                    <div class="col-md-6">
                        <div class="summary-box">
                            <div class="summary-row">
                                <span>Total Debits (ยอดรวมเดบิต):</span>
                                <strong id="d_total_debit">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Total Credits (ยอดรวมเครดิต):</span>
                                <strong id="d_total_credit">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Balance (ผลต่างเดบิต - เครดิต):</span>
                                <strong id="d_balance">0.00</strong>
                            </div>
                            <div class="summary-row" style="margin-top: 8px; padding-top: 10px; border-top: 1px solid #93c5fd;">
                                <span>Total Exclude VAT (ฐานภาษีหัก ณ ที่จ่ายรวม):</span>
                                <strong id="d_total_exvat">0.00</strong>
                            </div>
                            <div class="summary-row">
                                <span>Total Tax Amount (ภาษีหัก ณ ที่จ่ายรวม):</span>
                                <strong style="color:#b45309;" id="d_total_tax">0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>" />
        <input type="hidden" id="h_type" value="<?php echo $journal_type; ?>" />
        <input type="hidden" id="h_period" value="<?php echo $journal_period; ?>" />
        <input type="hidden" id="h_no" value="<?php echo $journal_no; ?>" />
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var currentFunc = document.getElementById('func').value;
var jType       = document.getElementById('h_type').value;
var jPeriod     = document.getElementById('h_period').value;
var jNo         = document.getElementById('h_no').value;

const PAY_CONDITION_MAP = {
    0: '-',
    1: 'หักภาษี ณ ที่จ่าย',
    2: 'ออกให้ตลอดไป',
    3: 'ออกให้ครั้งเดียว',
    4: 'อื่นๆ'
};

$(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg')) {
        showPageAlert('success', urlParams.get('msg'));
    }
    loadJournalDetail();
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

function loadJournalDetail() {
    var url = 'model/journal_api.php?action=detail' +
              '&journal_type=' + encodeURIComponent(jType) +
              '&journal_period=' + encodeURIComponent(jPeriod) +
              '&journal_no=' + encodeURIComponent(jNo);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่พบข้อมูลเอกสาร');
                return;
            }
            renderDetail(resp.data.heading, resp.data.items || []);
        })
        .catch(function(err) {
            showPageAlert('error', 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + err.message);
        });
}

function renderDetail(h, items) {
    $('#d_journal_badge').text(h.JOURNAL_TYPE + ' | Period ' + h.JOURNAL_PERIOD);
    $('#d_type_period').text(h.JOURNAL_TYPE + ' - ' + h.JOURNAL_PERIOD);
    $('#d_journal_no').text(h.JOURNAL_NO);
    $('#d_trans_date').text(h.TRANSACTION_DATE_FMT || '-');
    $('#d_due_date').text(h.DUE_DATE_FMT || '-');

    var supName = h.EN_COMPANY || h.TH_COMPANY || '';
    $('#d_supplier').text(h.CSTMSPPL_ID + (supName ? ' : ' + supName : ''));
    $('#d_department').text(h.DEPARTMENT || '-');
    $('#d_cash_flow').text(h.CASH_FLOW || '-');
    $('#d_cheque_no').text(h.CHEQUE_NO || '-');
    $('#d_taxdeduct_no').text(h.TAXDEDUCT_NO || '-');
    $('#d_tax_category').text(h.TAX_CATEGORY || '-');

    var payCond = parseInt(h.PAY_CONDITION, 10) || 0;
    $('#d_pay_condition').text(PAY_CONDITION_MAP[payCond] || '-');
    $('#d_remark').text(h.REMARK || '-');

    $('#d_item_count').text(items.length + ' Items');
    var tbodyHtml = '';
    items.forEach(function(it) {
        var dcBadge = (it.DEBIT_CREDIT === 'D')
            ? '<span class="badge-dc-d">D</span>'
            : '<span class="badge-dc-c">C</span>';
        var chkBadge = (it.CHECK_ITEM === 'C')
            ? '<span class="label label-success">C (Cash/Bank)</span>'
            : '<span class="text-muted">N</span>';

        tbodyHtml += '<tr>' +
                     '<td class="text-center" style="font-weight:700;">' + escapeHtml(it.JOURNAL_ITEM) + '</td>' +
                     '<td class="text-center" style="font-weight:700; color:#1d4ed8;">' + escapeHtml(it.ACCOUNT_CODE) + '</td>' +
                     '<td class="text-left" style="font-weight:600;">' + escapeHtml(it.ACCOUNT_NAME) + '</td>' +
                     '<td class="text-left">' + escapeHtml(it.INVOICE_NO || '-') + '</td>' +
                     '<td class="text-center">' + escapeHtml(it.COST_CENTER || '-') + '</td>' +
                     '<td class="text-left">' + escapeHtml(it.EXPENSE_CATEGORY || '-') + '</td>' +
                     '<td class="text-center">' + dcBadge + '</td>' +
                     '<td class="text-right">' + fmtNum(it.EXCLUDEVAT_AMOUNT, 2) + '</td>' +
                     '<td class="text-right">' + fmtNum(it.TAX_PERCENT, 1) + '%</td>' +
                     '<td class="text-right" style="font-weight:700; color:#0f172a;">' + fmtNum(it.AMOUNT, 2) + '</td>' +
                     '<td class="text-center">' + chkBadge + '</td>' +
                     '</tr>';
    });
    $('#d_items_tbody').html(tbodyHtml);

    var debit  = parseFloat(h.TOTAL_DEBITAMOUNT) || 0;
    var credit = parseFloat(h.TOTAL_CREDITAMOUNT) || 0;
    var bal    = debit - credit;

    $('#d_total_debit').text(fmtNum(debit, 2));
    $('#d_total_credit').text(fmtNum(credit, 2));
    $('#d_balance').text(fmtNum(bal, 2)).css('color', Math.abs(bal) > 0.009 ? '#dc2626' : '#059669');
    $('#d_total_exvat').text(fmtNum(h.TOTAL_EXCLUDEVATAMOUNT, 2));
    $('#d_total_tax').text(fmtNum(h.TOTAL_TAXAMOUNT, 2));
}

function deleteJournal() {
    if (!confirm('คุณยืนยันการลบเอกสารเลขที่ ' + jNo + ' ใช่หรือไม่? (ข้อมูลส่วนหัวและรายการบัญชีทั้งหมดจะถูกลบ)')) {
        return;
    }

    fetch('model/journal_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            journal_type: jType,
            journal_period: jPeriod,
            journal_no: jNo
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(resp) {
        if (resp.status === 'success') {
            window.location.assign('journal_list_mats.php?func=' + encodeURIComponent(currentFunc) + '&msg=' + encodeURIComponent(resp.message));
        } else {
            showPageAlert('error', resp.message || 'ลบข้อมูลไม่สำเร็จ');
        }
    })
    .catch(function(err) {
        showPageAlert('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + err.message);
    });
}

function previewPrintForm() {
    var printUrl = 'journal_print_mats.php?type=' + encodeURIComponent(jType) +
                   '&period=' + encodeURIComponent(jPeriod) +
                   '&no=' + encodeURIComponent(jNo);
    window.open(printUrl, '_blank');
}

</script>
</body>
</html>