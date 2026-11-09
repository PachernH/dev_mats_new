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

$current_dept = strtoupper(trim($department_func));
$is_authorized = ($current_dept === 'PP' || $current_dept === 'MK' || $current_dept === 'IT');

// Search and filter parameters
$job_search = isset($_GET['job_search']) ? htmlspecialchars(trim($_GET['job_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from  = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to    = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Job Orders</title>
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

        .btn-create-job {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-job:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }

        /* Badges */
        .badge-job {
            display: inline-block;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #bfdbfe;
            transition: all 0.2s ease;
        }
        .badge-job:hover { background-color: #1d4ed8; color: #ffffff; }

        .badge-status {
            font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .badge-status-op { background-color: #dbeafe; color: #1d4ed8; }
        .badge-status-hl { background-color: #fef3c7; color: #b45309; }
        .badge-status-cp { background-color: #dcfce7; color: #15803d; }
        .badge-status-cl { background-color: #f1f5f9; color: #475569; }

        /* Table Alignment */
        #job_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #job_table th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #job_table td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #job_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #job_table tbody tr:hover td { background-color: #f0f9ff !important; }

        .dataTables_scrollBody thead tr, .dataTables_scrollBody thead th {
            height: 0 !important; padding: 0 !important; margin: 0 !important; border: none !important; visibility: hidden !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'JOB'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="job_order_list_mats.php?func=<?php echo $folder_func ?>">
                        Job Orders Management
                        <?php if (!$is_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">PP / MK AUTHORIZED</span>
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
                    <label for="job_search">Search Job / SO / Product / Alloy</label>
                    <input class="form-control-minimal" name="job_search" id="job_search" type="text" 
                           placeholder="Enter Job No, SO No, Alloy..." value="<?php echo $job_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_job()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_from">Date From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_job()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_to">Date To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_job()"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_job()">
                        SEARCH
                    </button>
                    <?php if ($is_authorized): ?>
                        <a href="job_order_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-job">
                            + NEW JOB ORDER
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Job Orders List (Latest 200 Records)</h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="job_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 130px;">Job Order No</th>
                                        <th style="text-align: center; width: 100px;">Job Date</th>
                                        <th style="text-align: center; width: 130px;">Sales Order</th>
                                        <th style="text-align: center; width: 80px;">Product</th>
                                        <th style="text-align: center; width: 90px;">Alloy/Temper</th>
                                        <th style="text-align: center; width: 120px;">Dimensions (T x W x L)</th>
                                        <th style="text-align: left; width: 140px;">Work Process</th>
                                        <th style="text-align: right; width: 110px;">Release Wt (kg)</th>
                                        <th style="text-align: right; width: 90px;">Release Pcs</th>
                                        <th style="text-align: center; width: 90px;">Schedule ETD</th>
                                        <th style="text-align: center; width: 80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    $params = [];
                                    $where_clauses = ["1=1"];

                                    if (!empty($job_search)) {
                                        $where_clauses[] = "(j.JOB_ORDER LIKE :kw OR j.SALEORDER_NO LIKE :kw OR j.ALLOY LIKE :kw OR j.PRODUCT_ID LIKE :kw)";
                                        $params[':kw'] = '%' . $job_search . '%';
                                    }
                                    if (!empty($date_from)) {
                                        $where_clauses[] = "j.JOBORDER_DATE >= :date_from";
                                        $params[':date_from'] = $date_from;
                                    }
                                    if (!empty($date_to)) {
                                        $where_clauses[] = "j.JOBORDER_DATE <= :date_to";
                                        $params[':date_to'] = $date_to;
                                    }

                                    $where_sql = implode(' AND ', $where_clauses);

                                    $sql = "SELECT TOP 200 
                                                   j.JOB_ORDER,
                                                   j.JOBORDER_DATE,
                                                   j.SALEORDER_NO,
                                                   j.SALEORDER_ITEM,
                                                   j.PRODUCT_ID,
                                                   j.ALLOY,
                                                   j.TEMPER,
                                                   j.THICKNESS,
                                                   j.WIDTH,
                                                   j.LENGTH,
                                                   j.JOB_WORKPROCESS,
                                                   j.JOB_RELEASEWEIGHT,
                                                   j.JOB_RELEASEPIECE,
                                                   j.SCHEDULE_DATE,
                                                   j.JOB_STATUS
                                            FROM JOBORDER1 j
                                            WHERE {$where_sql}
                                            ORDER BY j.JOBORDER_DATE DESC, j.JOB_ORDER DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $job_no       = htmlspecialchars(trim($row['JOB_ORDER'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $raw_job_date = !empty($row['JOBORDER_DATE']) ? date('Y-m-d', strtotime($row['JOBORDER_DATE'])) : '';
                                        $job_date     = !empty($row['JOBORDER_DATE']) ? date('d/m/Y', strtotime($row['JOBORDER_DATE'])) : '-';

                                        $so_display   = htmlspecialchars(trim($row['SALEORDER_NO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        if (!empty($row['SALEORDER_ITEM'])) {
                                            $so_display .= ' / ' . htmlspecialchars(trim($row['SALEORDER_ITEM']), ENT_QUOTES, 'UTF-8');
                                        }

                                        $product_id   = htmlspecialchars(trim($row['PRODUCT_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $alloy_temper = htmlspecialchars(trim($row['ALLOY'] ?? '') . ' ' . trim($row['TEMPER'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        
                                        $dim = number_format((float)$row['THICKNESS'], 2) . ' x ' . number_format((float)$row['WIDTH'], 0);
                                        if ((float)$row['LENGTH'] > 0) {
                                            $dim .= ' x ' . number_format((float)$row['LENGTH'], 0);
                                        }

                                        $process      = htmlspecialchars(trim($row['JOB_WORKPROCESS'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $rel_weight   = number_format((float)$row['JOB_RELEASEWEIGHT'], 0);
                                        $rel_piece    = number_format((float)$row['JOB_RELEASEPIECE'], 0);

                                        $raw_etd      = !empty($row['SCHEDULE_DATE']) ? date('Y-m-d', strtotime($row['SCHEDULE_DATE'])) : '';
                                        $etd_date     = !empty($row['SCHEDULE_DATE']) ? date('d/m/Y', strtotime($row['SCHEDULE_DATE'])) : '-';
                                        
                                        $status       = strtoupper(trim($row['JOB_STATUS'] ?? 'OP'));
                                        $badge_class  = 'badge-status-op';
                                        if ($status === 'HL') $badge_class = 'badge-status-hl';
                                        if ($status === 'CP') $badge_class = 'badge-status-cp';
                                        if ($status === 'CL') $badge_class = 'badge-status-cl';

                                        echo "<tr onclick=\"go_to_job('{$job_no}')\">";
                                        echo "<td align='center'><span class='badge-job'>{$job_no}</span></td>";
                                        echo "<td align='center' data-order='{$raw_job_date}' style='font-weight:600; color:#475569;'>{$job_date}</td>";
                                        echo "<td align='center' style='font-weight:600;'>{$so_display}</td>";
                                        echo "<td align='center'><span style='font-weight:700; color:#4338ca;'>{$product_id}</span></td>";
                                        echo "<td align='center' style='font-weight:600;'>{$alloy_temper}</td>";
                                        echo "<td align='center'>{$dim}</td>";
                                        echo "<td align='left' style='font-size:12px; color:#64748b;'>{$process}</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$rel_weight}</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$rel_piece}</td>";
                                        echo "<td align='center' data-order='{$raw_etd}'>{$etd_date}</td>";
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
    var table = $("#job_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']], // เรียงตาม Job Date ล่าสุดลงไป
        columnDefs: [
            { targets: [0, 1, 2, 3, 4, 5, 9, 10], className: 'text-center' },
            { targets: [7, 8], className: 'text-right' },
            { targets: [6], className: 'text-left' }
        ]
    });

    $(window).on('resize', function () {
        table.columns.adjust();
    });
    setTimeout(function() {
        table.columns.adjust();
    }, 200);
});

function search_job() {
    var func = document.getElementById("func").value;
    var search = document.getElementById("job_search").value;
    var dfrom = document.getElementById("date_from").value;
    var dto = document.getElementById("date_to").value;
    
    var url = 'job_order_list_mats.php?func=' + encodeURIComponent(func) +
              '&job_search=' + encodeURIComponent(search) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto);
    window.location.assign(url);
}

function go_to_job(job_no) {
    var func = document.getElementById("func").value;
    window.location.assign('job_order_view_mats.php?func=' + encodeURIComponent(func) + '&jobno=' + encodeURIComponent(job_no));
}
</script>
</html>