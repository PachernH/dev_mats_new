<?php
// Start session
session_start();

// Retrieve session variables
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

// Redirect if session expired
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// Check authorization (Sale/Marketing has full access; others read-only)
$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$current_dept = strtoupper(trim($department_func));
$is_sale_authorized = ($current_func === 'SALE' || $current_dept === 'MK');

// Search and filter parameters
$so_search = isset($_GET['so_search']) ? htmlspecialchars(trim($_GET['so_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to   = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';
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

        /* Filter Section */
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

        .btn-create-so {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-so:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }

        /* Badges */
        .badge-so {
            display: inline-block;
            background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #a7f3d0;
            transition: all 0.2s ease;
        }
        .badge-so:hover {
            background-color: #047857; color: #ffffff;
        }
        .badge-status {
            font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .badge-status-op { background-color: #dbeafe; color: #1d4ed8; }
        .badge-status-hl { background-color: #fef3c7; color: #b45309; }
        .badge-status-cp { background-color: #dcfce7; color: #15803d; }

        /* Fixed Table Alignment */
        #so_table {
            width: 100% !important;
            margin: 0 !important;
            border-collapse: collapse;
        }
        #so_table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #so_table td {
            padding: 10px 8px !important;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }
        #so_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #so_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* ซ่อนลูกศร Sorting ที่แลบออกมาจากแถวที่ DataTables โคลนไว้สำหรับ scrollX */
        .dataTables_scrollBody thead tr,
        .dataTables_scrollBody thead th {
            height: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            margin: 0 !important;
            border: none !important;
            visibility: hidden !important;
        }

        .dataTables_scrollBody thead th:before,
        .dataTables_scrollBody thead th:after {
            display: none !important;
            content: "" !important;
        }        
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'SO'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="saleorder_list_mats.php?func=<?php echo $folder_func ?>">
                        Sales Order Management
                        <?php if (!$is_sale_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">SALE AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 220px;">
                    <label for="so_search">Search SO / PO / Customer</label>
                    <input class="form-control-minimal" name="so_search" id="so_search" type="text" 
                           placeholder="Enter SO No, PO No, Customer..." value="<?php echo $so_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_so()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_from">Date From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_so()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_to">Date To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_so()"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_so()">
                        SEARCH
                    </button>
                    <?php if ($is_sale_authorized): ?>
                        <a href="saleorder_entry_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-so">
                            + NEW SALES ORDER
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Sales Orders List (Latest 200 Records)</h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="so_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 140px;">Sales Order No</th>
                                        <th style="text-align: center; width: 100px;">Order Date</th>
                                        <th style="text-align: left; width: 120px;">Customer</th>
                                        <th style="text-align: left; width: 140px;">PO Number</th>
                                        <th style="text-align: center; width: 90px;">Category</th>
                                        <th style="text-align: center; width: 100px;">Intend Ship</th>
                                        <th style="text-align: right; width: 80px;">Items</th>
                                        <th style="text-align: right; width: 120px;">Weight (kg)</th>
                                        <th style="text-align: right; width: 130px;">Grand Total</th>
                                        <th style="text-align: center; width: 80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    $params = [];
                                    $where_clauses = ["1=1"];

                                    if (!empty($so_search)) {
                                        $where_clauses[] = "(h.SALEORDER_NO LIKE :kw OR h.CTM1_PONO LIKE :kw OR h.CSTMSPPL_ID LIKE :kw)";
                                        $params[':kw'] = '%' . $so_search . '%';
                                    }
                                    if (!empty($date_from)) {
                                        $where_clauses[] = "h.SALEORDER_DATE >= :date_from";
                                        $params[':date_from'] = $date_from;
                                    }
                                    if (!empty($date_to)) {
                                        $where_clauses[] = "h.SALEORDER_DATE <= :date_to";
                                        $params[':date_to'] = $date_to;
                                    }

                                    $where_sql = implode(' AND ', $where_clauses);

                                    // ดึงจาก CSTMORDR1 เป็นหลัก และจัดเรียงตาม SALEORDER_DATE DESC ตรงตาม DBeaver
                                    $sql = "SELECT TOP 200 
                                                   h.SALEORDER_NO,
                                                   h.SALEORDER_DATE,
                                                   h.CSTMSPPL_ID,
                                                   h.CTM1_PONO,
                                                   h.ORDER_CATEGORY,
                                                   h.INTENDSHIP_DATE,
                                                   h.CURRENCY_ID,
                                                   h.CTM1_TOTALAMOUNT,
                                                   ISNULL(d.ITEM_COUNT, 0) AS ITEM_COUNT,
                                                   ISNULL(d.TOTAL_WEIGHT, 0) AS TOTAL_WEIGHT,
                                                   ISNULL(d.SO_STATUS, 'OP') AS SO_STATUS
                                            FROM CSTMORDR1 h
                                            OUTER APPLY (
                                                SELECT COUNT(d2.SALEORDER_ITEM) AS ITEM_COUNT,
                                                       SUM(ISNULL(d2.CTM2_ORDERWEIGHT, 0)) AS TOTAL_WEIGHT,
                                                       MAX(d2.CTM2_STATUS) AS SO_STATUS
                                                FROM CSTMORDR2 d2
                                                WHERE d2.SALEORDER_NO = h.SALEORDER_NO
                                            ) d
                                            WHERE {$where_sql}
                                            ORDER BY h.SALEORDER_DATE DESC, h.SALEORDER_NO DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $so_no        = htmlspecialchars(trim($row['SALEORDER_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        
                                        // วันที่ Order Date สำหรับ Sort และแสดงผล
                                        $raw_so_date  = !empty($row['SALEORDER_DATE']) ? date('Y-m-d', strtotime($row['SALEORDER_DATE'])) : '';
                                        $so_date      = !empty($row['SALEORDER_DATE']) ? date('d/m/Y', strtotime($row['SALEORDER_DATE'])) : '-';

                                        $customer     = htmlspecialchars(trim($row['CSTMSPPL_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $po_no        = htmlspecialchars(trim($row['CTM1_PONO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $category     = htmlspecialchars(trim($row['ORDER_CATEGORY'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        
                                        // วันที่ Intend Ship Date สำหรับ Sort และแสดงผล
                                        $raw_ship_date= !empty($row['INTENDSHIP_DATE']) ? date('Y-m-d', strtotime($row['INTENDSHIP_DATE'])) : '';
                                        $ship_date    = !empty($row['INTENDSHIP_DATE']) ? date('d/m/Y', strtotime($row['INTENDSHIP_DATE'])) : '-';

                                        $item_count   = (int)$row['ITEM_COUNT'];
                                        $weight       = number_format((float)$row['TOTAL_WEIGHT'], 0);
                                        $currency     = trim($row['CURRENCY_ID'] ?? '');
                                        $amount       = number_format((float)$row['CTM1_TOTALAMOUNT'], 2) . ' ' . $currency;
                                        $status       = strtoupper(trim($row['SO_STATUS']));

                                        $badge_class = 'badge-status-op';
                                        if ($status === 'HL') $badge_class = 'badge-status-hl';
                                        if ($status === 'CP') $badge_class = 'badge-status-cp';

                                        echo "<tr onclick=\"go_to_view('{$so_no}')\">";
                                        echo "<td align='center'><span class='badge-so'>{$so_no}</span></td>";
                                        // ใส่ data-order ให้ DataTables เรียงลำดับตาม YYYY-MM-DD ถูกต้องเสมอ
                                        echo "<td align='center' data-order='{$raw_so_date}' style='font-weight:600; color:#475569;'>{$so_date}</td>";
                                        echo "<td align='left' style='font-weight:600;'>{$customer}</td>";
                                        echo "<td align='left'>{$po_no}</td>";
                                        echo "<td align='center'><span style='font-weight:700; color:#4338ca;'>{$category}</span></td>";
                                        echo "<td align='center' data-order='{$raw_ship_date}'>{$ship_date}</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$item_count}</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$weight}</td>";
                                        echo "<td align='right' style='font-weight:700; color:#0f172a;'>{$amount}</td>";
                                        echo "<td align='center'><span class='badge-status {$badge_class}'>{$status}</span></td>";
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
    var table = $("#so_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']], // คอลัมน์ที่ 1 (Order Date) เรียงจากวันที่ล่าสุดลงไป
        columnDefs: [
            { targets: [0, 1, 4, 5, 9], className: 'text-center' },
            { targets: [6, 7, 8], className: 'text-right' },
            { targets: [2, 3], className: 'text-left' }
        ]
    });

    $(window).on('resize', function () {
        table.columns.adjust();
    });
    setTimeout(function() {
        table.columns.adjust();
    }, 200);
});

function search_so() {
    var func = document.getElementById("func").value;
    var search = document.getElementById("so_search").value;
    var dfrom = document.getElementById("date_from").value;
    var dto = document.getElementById("date_to").value;
    
    var url = 'saleorder_list_mats.php?func=' + encodeURIComponent(func) +
              '&so_search=' + encodeURIComponent(search) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto);
    window.location.assign(url);
}

function go_to_view(so_no) {
    var func = document.getElementById("func").value;
    window.location.assign('saleorder_view_mats.php?func=' + encodeURIComponent(func) + '&sono=' + encodeURIComponent(so_no));
}
</script>
</html>