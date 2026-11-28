<?php
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
$inv_search = isset($_GET['inv_search']) ? htmlspecialchars(trim($_GET['inv_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from  = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to    = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';

$is_invoice_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');


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
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        .filter-wrapper {
            display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;
            background: #ffffff; padding: 18px 24px; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label {
            font-size: 11.5px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 40px; font-size: 14px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-create-inv {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-inv:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }

        .badge-inv {
            display: inline-block;
            background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #a7f3d0;
            transition: all 0.2s ease;
        }
        .badge-inv:hover { background-color: #047857; color: #ffffff; }

        .badge-status {
            font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .badge-status-op { background-color: #dbeafe; color: #1d4ed8; }
        .badge-status-cl { background-color: #e2e8f0; color: #475569; }

        #inv_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #inv_table th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #inv_table td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #inv_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #inv_table tbody tr:hover td { background-color: #f0f9ff !important; }

        .dataTables_scrollBody thead tr, .dataTables_scrollBody thead th {
            height: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important;
            margin: 0 !important; border: none !important; visibility: hidden !important;
        }
        .dataTables_scrollBody thead th:before, .dataTables_scrollBody thead th:after {
            display: none !important; content: "" !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="invoice_list_mats.php?func=<?php echo $folder_func; ?>">
                        Invoice Management
                        <?php if (!$is_invoice_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">INVOICE AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 240px;">
                    <label for="inv_search">Search Invoice No / Customer / Book No</label>
                    <input class="form-control-minimal" name="inv_search" id="inv_search" type="text"
                           placeholder="Enter Invoice No, Customer, Book No..." value="<?php echo $inv_search; ?>"
                           oninput="this.value = this.value.toUpperCase()" onchange="search_inv()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_from">Invoice Date From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_inv()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_to">Invoice Date To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_inv()"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_inv()">SEARCH</button>
                    <?php if ($is_invoice_authorized): ?>
                        <a href="invoice_entry_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-inv">
                            + CREATE NEW INVOICE
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Invoices List (Latest 200 Records)</h4>
                        </div>

                        <div class="table-responsive">
                            <table id="inv_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 140px;">Invoice No</th>
                                        <th style="text-align: center; width: 100px;">Invoice Date</th>
                                        <th style="text-align: left; width: 220px;">Customer</th>
                                        <th style="text-align: left; width: 130px;">Book No</th>
                                        <th style="text-align: center; width: 90px;">BOI Type</th>
                                        <th style="text-align: right; width: 70px;">Pkg</th>
                                        <th style="text-align: right; width: 110px;">Net Wt (kg)</th>
                                        <th style="text-align: right; width: 140px;">Total Amount</th>
                                        <th style="text-align: center; width: 70px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    $params = [];
                                    $where_clauses = ["1=1"];

                                    if (!empty($inv_search)) {
                                        $where_clauses[] = "(h.INVOICE_NO LIKE :kw1 OR h.CSTMSPPL_ID LIKE :kw2 OR h.BOOK_NO LIKE :kw3 OR c.CONSIGNEE_COMPANY LIKE :kw4)";
                                        $kwVal = '%' . $inv_search . '%';
                                        $params[':kw1'] = $kwVal;
                                        $params[':kw2'] = $kwVal;
                                        $params[':kw3'] = $kwVal;
                                        $params[':kw4'] = $kwVal;
                                    }
                                    if (!empty($date_from)) {
                                        $where_clauses[] = "h.INVOICE_DATE >= :date_from";
                                        $params[':date_from'] = $date_from;
                                    }
                                    if (!empty($date_to)) {
                                        $where_clauses[] = "h.INVOICE_DATE <= :date_to";
                                        $params[':date_to'] = $date_to;
                                    }

                                    $where_sql = implode(' AND ', $where_clauses);

                                    $sql = "SELECT TOP 200
                                                   h.INVOICE_NO, h.INVOICE_DATE, h.CSTMSPPL_ID, c.CONSIGNEE_COMPANY,
                                                   h.BOOK_NO, h.INV1_MATERIALINPRN, h.CURRENCY_ID,
                                                   ISNULL(h.INV1_PACKAGE, 0) AS INV1_PACKAGE,
                                                   ISNULL(h.INV1_NETWEIGHT, 0) AS INV1_NETWEIGHT,
                                                   ISNULL(h.INV1_TOTALAMOUNT, 0) AS INV1_TOTALAMOUNT,
                                                   ISNULL(h.INV1_STATUS, 'OP') AS INV1_STATUS
                                            FROM INVCPRCS1 h
                                            LEFT JOIN CSSPMSTR1 c ON h.CSTMSPPL_ID = c.CSTMSPPL_ID
                                            WHERE {$where_sql}
                                            ORDER BY h.INVOICE_DATE DESC, h.INVOICE_NO DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $inv_no     = htmlspecialchars(trim($row['INVOICE_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $raw_date   = !empty($row['INVOICE_DATE']) ? date('Y-m-d', strtotime($row['INVOICE_DATE'])) : '';
                                        $inv_date   = !empty($row['INVOICE_DATE']) ? date('d/m/Y', strtotime($row['INVOICE_DATE'])) : '-';
                                        $cust_id    = htmlspecialchars(trim($row['CSTMSPPL_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $cust_name  = htmlspecialchars(trim($row['CONSIGNEE_COMPANY'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $book_no    = htmlspecialchars(trim($row['BOOK_NO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $mat_in     = htmlspecialchars(trim($row['INV1_MATERIALINPRN'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $pkg        = number_format((int)$row['INV1_PACKAGE'], 0);
                                        $net_wt     = number_format((float)$row['INV1_NETWEIGHT'], 2);
                                        $currency   = trim($row['CURRENCY_ID'] ?? '');
                                        $amount     = number_format((float)$row['INV1_TOTALAMOUNT'], 2) . ' ' . $currency;
                                        $status     = strtoupper(trim($row['INV1_STATUS']));
                                        $badge_cls  = ($status === 'OP') ? 'badge-status-op' : 'badge-status-cl';

                                        echo "<tr onclick=\"go_to_detail('{$inv_no}')\">";
                                        echo "<td align='center'><span class='badge-inv'>{$inv_no}</span></td>";
                                        echo "<td align='center' data-order='{$raw_date}' style='font-weight:600; color:#475569;'>{$inv_date}</td>";
                                        echo "<td align='left' style='font-weight:600;'>{$cust_id}: {$cust_name}</td>";
                                        echo "<td align='left'>{$book_no}</td>";
                                        echo "<td align='center'><span style='font-weight:700; color:#4338ca;'>{$mat_in}</span></td>";
                                        echo "<td align='right'>{$pkg}</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$net_wt}</td>";
                                        echo "<td align='right' style='font-weight:700; color:#0f172a;'>{$amount}</td>";
                                        echo "<td align='center'><span class='badge-status {$badge_cls}'>{$status}</span></td>";
                                        echo "</tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var table = $("#inv_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']],
        columnDefs: [
            { targets: [0, 1, 4, 8], className: 'text-center' },
            { targets: [5, 6, 7], className: 'text-right' },
            { targets: [2, 3], className: 'text-left' }
        ]
    });

    $(window).on('resize', function () { table.columns.adjust(); });
    setTimeout(function() { table.columns.adjust(); }, 200);
});

function search_inv() {
    var func = document.getElementById("func").value;
    var search = document.getElementById("inv_search").value;
    var dfrom = document.getElementById("date_from").value;
    var dto = document.getElementById("date_to").value;

    var url = 'invoice_list_mats.php?func=' + encodeURIComponent(func) +
              '&inv_search=' + encodeURIComponent(search) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto);
    window.location.assign(url);
}

function go_to_detail(inv_no) {
    var func = document.getElementById("func").value;
    window.location.assign('invoice_detail_mats.php?func=' + encodeURIComponent(func) + '&invno=' + encodeURIComponent(inv_no));
}
</script>
</body>
</html>