<?php
session_start();

if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : 'mats';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';

$job_order_search = !isset($_GET['job']) ? '' : htmlspecialchars(trim($_GET['job']), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Transfer Raw Material Process</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; width: 100%; padding: 0 4px; }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }
        
        .btn-search { background-color: #1e293b !important; color: #ffffff !important; font-weight: 600; font-size: 13px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-search:hover { background-color: #0f172a !important; transform: translateY(-1px); }

        .table-responsive { border: none !important; margin-top: 10px; overflow-x: auto; }
        #job_table { width: 100% !important; border-collapse: collapse !important; }
        #job_table thead th { background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase; padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        #job_table tbody td { padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; white-space: nowrap; color: #334155; }
        #job_table tbody tr:hover td { background-color: #f0f9ff !important; cursor: pointer; }
        
        .badge-job { background: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; }
        .btn-select-rw { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 12.5px; padding: 6px 16px; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-select-rw:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Transfer Raw Material Process
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="job_order_search">Search Job Order</label>
                    <input class="form-control-minimal" name="job_order_search" id="job_order_search" type="text" placeholder="Enter Job Order..." value="<?php echo $job_order_search; ?>" oninput="this.value = this.value.toUpperCase()" onkeypress="checkEnter(event)"/>
                </div>
                <div class="filter-item">
                    <button type="button" class="btn-search" onclick="search_job()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b; font-size:17px;">📋 Open Raw Material Job Orders (PRODUCT_ID = 'RM')</h4>
                        <div class="table-responsive table-full-width">
                            <table id="job_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">Job Order</th>
                                        <th style="text-align: center;">Sale Order / Item</th>
                                        <th style="text-align: center;">Customer</th>
                                        <th style="text-align: center;">Material In</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: right;">Release Wt.</th>
                                        <th style="text-align: right;">Produce Wt.</th>
                                        <th style="text-align: right;">Produce Pc.</th>
                                        <th style="text-align: center;">Work Process</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center; width: 140px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    // เงื่อนไขตาม SQL ต้นฉบับ
                                    $where_sql = "WHERE A.PRODUCT_ID = 'RM' AND A.JOB_STATUS = 'OP'";
                                    $params = [];

                                    if (!empty($job_order_search)) {
                                        $where_sql .= " AND A.JOB_ORDER LIKE :job";
                                        $params[':job'] = '%' . $job_order_search . '%';
                                    }

                                    $sql = "SELECT A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MATERIAL_IN, A.ALLOY, 
                                                   A.JOB_RELEASEWEIGHT, A.JOB_PRODUCEWEIGHT, A.JOB_PRODUCEPIECE, 
                                                   A.JOB_WORKPROCESS, A.JOB_STATUS, B.CSTMSPPL_ID 
                                            FROM JOBORDER1 AS A 
                                            LEFT JOIN CSTMORDR2 AS B ON B.SALEORDER_NO = A.SALEORDER_NO AND B.SALEORDER_ITEM = A.SALEORDER_ITEM 
                                            " . $where_sql . " ORDER BY A.JOB_ORDER DESC";
                                    
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $job = htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $so_item = htmlspecialchars(($row['SALEORDER_NO'] ?? '') . ' / ' . ($row['SALEORDER_ITEM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        
                                        echo "<tr onclick=\"select_rw(event, '".$job."')\">";
                                        echo "<td align='center'><span class='badge-job'>".$job."</span></td>";
                                        echo "<td align='center'>".$so_item."</td>";
                                        echo "<td align='center' style='font-weight:600;'>".htmlspecialchars($row['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='right'>".number_format((float)($row['JOB_RELEASEWEIGHT'] ?? 0), 2)."</td>";
                                        echo "<td align='right' style='color:#2563eb; font-weight:700;'>".number_format((float)($row['JOB_PRODUCEWEIGHT'] ?? 0), 2)."</td>";
                                        echo "<td align='right'>".number_format((float)($row['JOB_PRODUCEPIECE'] ?? 0), 0)."</td>";
                                        echo "<td align='center' style='color:#d97706; font-weight:600;'>".htmlspecialchars($row['JOB_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'><span class='label label-success' style='padding: 3px 8px; border-radius: 4px; background:#10b981; color:#fff;'>".htmlspecialchars($row['JOB_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</span></td>";
                                        echo "<td align='center'>";
                                        echo "<button type='button' class='btn-select-rw' onclick=\"select_rw(event, '".$job."')\">📦 Select RW</button>";
                                        echo "</td>";
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
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
$(document).ready(function() {
    if (!$.fn.DataTable.isDataTable('#job_table')) {
        $('#job_table').DataTable({
            scrollY: "450px",
            scrollCollapse: true,
            paging: true,
            pageLength: 20,
            order: [[0, 'desc']],
            dom: 'Bfrtip',
            buttons: []
        });
    }
});

function search_job() {
    var f = document.getElementById("func").value;
    var j = document.getElementById("job_order_search").value;
    window.location.assign('transfer_raw_mat_process_mats.php?func=' + encodeURIComponent(f) + '&job=' + encodeURIComponent(j));
}

function checkEnter(e) {
    if (e.keyCode === 13) {
        search_job();
    }
}

function select_rw(e, job) {
    if (e && e.stopPropagation) e.stopPropagation();
    var f = document.getElementById("func").value;
    window.location.assign('transfer_raw_mat_entry_mats.php?func=' + encodeURIComponent(f) + '&job=' + encodeURIComponent(job));
}
</script>
</html>