<?php
// journal_list_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_dept = strtoupper(trim($department_func));
$is_journal_authorized = ($current_dept === 'FN' || $current_dept === 'IT');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Journal Voucher Management - Meyer Aluminium Thailand Co.,LTD.</title>
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

        .btn-reset {
            background-color: #ffffff;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            height: 40px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            transition: all 0.2s;
        }
        .btn-reset:hover { background-color: #f1f5f9; color: #0f172a; }

        .btn-create-jn {
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
        .btn-create-jn:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        .badge-jn {
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
        .badge-jn:hover { background-color: #1d4ed8; color: #ffffff; }

        .badge-tax {
            display: inline-block;
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #fde68a;
        }

        /* Table & DataTables Header Fix */
        #journal_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #journal_table thead th,
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
        #journal_table tbody td {
            padding: 10px 10px !important;
            vertical-align: middle !important;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap !important;
        }
        #journal_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #journal_table tbody tr:hover td { background-color: #f0f9ff !important; }

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
                        Journal Voucher Management
                        <?php if (!$is_journal_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#2563eb; font-size:11px; margin-left:8px;">FULL ACCESS</span>
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
                <div class="filter-item" style="flex: 1.4; min-width: 220px;">
                    <label for="f_journal_no">Journal No</label>
                    <input class="form-control-minimal" id="f_journal_no" type="text"
                           placeholder="Search Journal No (e.g. PV2611012)..."
                           oninput="this.value = this.value.toUpperCase()"
                           onkeypress="if(event.key === 'Enter') loadJournalList();" />
                </div>
                <div class="filter-item" style="flex: 0.7; min-width: 120px;">
                    <label for="f_journal_type">Type</label>
                    <select class="form-control" id="f_journal_type" onchange="loadJournalList()">
                        <option value="">ALL TYPES</option>
                        <option value="PV">PV: Payment</option>
                        <option value="PC">PC: Petty Cash</option>
                        <option value="JV">JV: Journal</option>
                    </select>
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 145px;">
                    <label for="f_date_from">Transaction Date From</label>
                    <input class="form-control" id="f_date_from" type="date" onchange="loadJournalList()" />
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 145px;">
                    <label for="f_date_to">Transaction Date To</label>
                    <input class="form-control" id="f_date_to" type="date" onchange="loadJournalList()" />
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button type="button" class="btn btn-search" onclick="loadJournalList()">
                        SEARCH
                    </button>
                    <button type="button" class="btn btn-reset" onclick="resetSearch()" title="ล้างค่าค้นหาและกลับไปดู 200 รายการล่าสุด">
                        RESET
                    </button>
                    <?php if ($is_journal_authorized): ?>
                        <a href="journal_entry_mats.php?func=<?php echo $folder_func; ?>&mode=create" class="btn-create-jn">
                            + CREATE NEW JOURNAL
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                    Journal Voucher List <span id="record_count_badge" class="badge" style="background:#e2e8f0; color:#334155; margin-left:4px;">0</span>
                                </h4>
                            </div>
                            
                        </div>

                        <div class="table-responsive">
                            <table id="journal_table" class="table">
                                <thead>
                                    <tr>
                                        <th class="text-center">Journal No</th>
                                        <th class="text-center">Period</th>
                                        <th class="text-center">Tax Deduct No</th>
                                        <th class="text-left">Supplier (CSTMSPPL_ID)</th>
                                        <th class="text-center">Trans. Date</th>
                                        <th class="text-center">Due Date</th>
                                        <th class="text-right">Total Debit</th>
                                        <th class="text-right">Total Credit</th>
                                        <th class="text-right">Exclude VAT</th>
                                        <th class="text-right">Tax Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="journal_tbody">
                                    <tr>
                                        <td colspan="10" class="text-center text-muted" style="padding:30px;">
                                            กำลังโหลดข้อมูลล่าสุด 200 รายการ...
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
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var journalDataTable = null;
var currentFunc = document.getElementById('func').value;

$(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg')) {
        showPageAlert('success', urlParams.get('msg'));
    }

    loadJournalList();
    $(window).on('resize', function () {
        if (journalDataTable) journalDataTable.columns.adjust();
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

function resetSearch() {
    $('#f_journal_no').val('');
    $('#f_journal_type').val('');
    $('#f_date_from').val('');
    $('#f_date_to').val('');
    loadJournalList();
}

function loadJournalList() {
    var journalNo   = $('#f_journal_no').val().trim();
    var journalType = $('#f_journal_type').val();
    var dateFrom    = $('#f_date_from').val();
    var dateTo      = $('#f_date_to').val();

    var url = 'model/journal_api.php?action=list' +
              '&journal_no=' + encodeURIComponent(journalNo) +
              '&journal_type=' + encodeURIComponent(journalType) +
              '&date_from=' + encodeURIComponent(dateFrom) +
              '&date_to=' + encodeURIComponent(dateTo);

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(resp) {
            if (resp.status !== 'success') {
                showPageAlert('error', resp.message || 'ไม่สามารถโหลดข้อมูลได้');
                return;
            }
            updateSearchModeBadge(resp.is_filtered, (resp.data || []).length);
            renderJournalTable(resp.data || []);
        })
        .catch(function(err) {
            showPageAlert('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ API: ' + err.message);
        });
}

function updateSearchModeBadge(isFiltered, count) {
    var badge = $('#search_mode_badge');
    if (isFiltered) {
        badge.removeClass('label-info').addClass('label-warning')
             .text('ผลการค้นหาจากฐานข้อมูลทั้งหมด (' + count + ' รายการ)');
    } else {
        badge.removeClass('label-warning').addClass('label-info')
             .text('แสดงล่าสุด 200 รายการ (หากไม่พบกรุณาระบุเงื่อนไขค้นหาด้านบน)');
    }
}

function renderJournalTable(rows) {
    $('#record_count_badge').text(rows.length);

    if (journalDataTable) {
        journalDataTable.destroy();
        journalDataTable = null;
    }

    var tbodyHtml = '';
    rows.forEach(function(r) {
        var jType   = escapeHtml(r.JOURNAL_TYPE);
        var jPeriod = escapeHtml(r.JOURNAL_PERIOD);
        var jNo     = escapeHtml(r.JOURNAL_NO);
        var taxNo   = escapeHtml(r.TAXDEDUCT_NO || '');
        var supName = r.EN_COMPANY || r.TH_COMPANY || '';
        var supText = escapeHtml(r.CSTMSPPL_ID + (supName ? ' : ' + supName : ''));
        var transFmt= escapeHtml(r.TRANSACTION_DATE_FMT || '-');
        var dueFmt  = escapeHtml(r.DUE_DATE_FMT || '-');

        var debit   = fmtNum(r.TOTAL_DEBITAMOUNT, 2);
        var credit  = fmtNum(r.TOTAL_CREDITAMOUNT, 2);
        var exVat   = fmtNum(r.TOTAL_EXCLUDEVATAMOUNT, 2);
        var taxAmt  = fmtNum(r.TOTAL_TAXAMOUNT, 2);

        tbodyHtml += '<tr onclick="goToDetail(\'' + jType + '\', \'' + jPeriod + '\', \'' + jNo + '\')">' +
                     '<td class="text-center"><span class="badge-jn">' + jNo + '</span></td>' +
                     '<td class="text-center" style="font-weight:600;">' + jPeriod + '</td>' +
                     '<td class="text-center">' + (taxNo !== '' ? '<span class="badge-tax">' + taxNo + '</span>' : '-') + '</td>' +
                     '<td class="text-left" style="font-weight:600;">' + supText + '</td>' +
                     '<td class="text-center" data-order="' + escapeHtml(r.TRANSACTION_DATE) + '">' + transFmt + '</td>' +
                     '<td class="text-center" data-order="' + escapeHtml(r.DUE_DATE) + '">' + dueFmt + '</td>' +
                     '<td class="text-right" style="font-weight:700; color:#0f172a;">' + debit + '</td>' +
                     '<td class="text-right" style="font-weight:700; color:#0f172a;">' + credit + '</td>' +
                     '<td class="text-right" style="color:#475569;">' + exVat + '</td>' +
                     '<td class="text-right" style="font-weight:600; color:#b45309;">' + taxAmt + '</td>' +
                     '</tr>';
    });

    $('#journal_tbody').html(tbodyHtml);

    journalDataTable = $('#journal_table').DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
        order: [[4, 'desc'], [0, 'desc']],
        columnDefs: [
            { targets: [0, 1, 2, 4, 5], className: 'text-center' },
            { targets: [6, 7, 8, 9], className: 'text-right' },
            { targets: [3], className: 'text-left' }
        ],
        language: {
            emptyTable: "ไม่พบข้อมูลในรายการที่แสดง — กรุณาระบุเลขที่เอกสารหรือช่วงวันที่ด้านบนแล้วกด SEARCH"
        }
    });

    setTimeout(function() {
        if (journalDataTable) journalDataTable.columns.adjust();
    }, 200);
}

function goToDetail(jType, jPeriod, jNo) {
    window.location.assign(
        'journal_detail_mats.php?func=' + encodeURIComponent(currentFunc) +
        '&type=' + encodeURIComponent(jType) +
        '&period=' + encodeURIComponent(jPeriod) +
        '&no=' + encodeURIComponent(jNo)
    );
}
</script>
</body>
</html>