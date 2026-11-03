<?php
session_start();
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$jno = !isset($_GET['jno']) ? '' : htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8');
$print_url = isset($_GET['print_url']) ? $_GET['print_url'] : '';
$saved_pno = isset($_GET['pno']) ? htmlspecialchars($_GET['pno'], ENT_QUOTES, 'UTF-8') : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        
        /* Tabs Styling */
        .nav-tabs-custom { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { background: #f1f5f9; color: #64748b; font-weight: 700; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; font-size: 13.5px; transition: all 0.2s; }
        .tab-btn.active { background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2); }
        .tab-btn:hover { background: #e2e8f0; color: #334155; text-decoration: none; }
        .tab-btn.active:hover { background: #1d4ed8; color: #fff; }

        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 38px; font-size: 14px; font-weight: 600; color: #1e293b; width: 100%; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; height: 38px; padding: 0 20px; border-radius: 8px; border: none; cursor: pointer; }
        
        .table-responsive { border: none !important; margin-top: 5px; }
        .data-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; padding: 10px 8px; border-bottom: 2px solid #cbd5e1; }
        .data-table tbody td { padding: 10px 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-table tbody tr:hover td { background-color: #f0f9ff !important; }
        
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bfdbfe; font-size: 12px; display: inline-block; }
        .badge-pno { background-color: #f0fdf4; color: #166534; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bbf7d0; font-size: 12px; display: inline-block; }
        
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }
        .btn-action-print { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-action-print:hover { background-color: #1d4ed8; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'Wrapping';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Wrap Product Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Tabs Menu -->
            <div class="nav-tabs-custom">
                <a href="wrapping_process_mats.php?func=<?php echo $folder_func; ?>&tab=pending" class="tab-btn <?php echo ($active_tab == 'pending') ? 'active' : ''; ?>">
                    📥 Wrap Product (Pending Jobs)
                </a>
                <a href="wrapping_process_mats.php?func=<?php echo $folder_func; ?>&tab=history" class="tab-btn <?php echo ($active_tab == 'history') ? 'active' : ''; ?>">
                    📋 History Wrap Product / Reprint Label
                </a>
            </div>

            <!-- Search Bar -->
            <div class="filter-wrapper">
                <div style="flex: 1; min-width: 220px;">
                    <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                        <?php echo ($active_tab == 'history') ? 'Search Product No. / Job Order' : 'Search Job Order'; ?>
                    </label>
                    <input class="form-control-minimal" id="job_no" type="text" placeholder="Enter keyword..." value="<?php echo $jno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_data()"/>
                </div>
                <div>
                    <button class="btn-search" onclick="search_data()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <?php if ($active_tab == 'pending'): ?>
                            <!-- TAB 1: PENDING JOBS -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">📑 Wrap Product (Pending Jobs)</h4>
                            </div>
                            <div class="table-responsive table-full-width">
                                <table id="pending_table" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Job Order</th>
                                            <th style="text-align: center;">Sale Order</th>
                                            <th style="text-align: center;">Product ID</th>
                                            <th style="text-align: center;">Alloy</th>
                                            <th style="text-align: right;">Release Weight</th>
                                            <th style="text-align: right;">Produce Weight</th>
                                            <th style="text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");

                                        $params = [];
                                        $where = "PRODUCT_ID = 'WR' AND JOB_STATUS = 'OP'";
                                        if (!empty($jno)) {
                                            $where .= " AND JOB_ORDER LIKE :jno";
                                            $params[':jno'] = '%' . $jno . '%';
                                        }

                                        $sql = "SELECT JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, PRODUCT_ID, ALLOY, JOB_RELEASEWEIGHT, JOB_PRODUCEWEIGHT FROM JOBORDER1 WHERE {$where} ORDER BY JOB_ORDER DESC";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $job_order = htmlspecialchars($row['JOB_ORDER'], ENT_QUOTES, 'UTF-8');
                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-job'>🔹 {$job_order}</span></td>";
                                            echo "<td align='center'>".htmlspecialchars($row['SALEORDER_NO'].'/'.$row['SALEORDER_ITEM'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='center'>".htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='center'>".htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='right'>".number_format((float)$row['JOB_RELEASEWEIGHT'], 0)." kg</td>";
                                            echo "<td align='right'>".number_format((float)$row['JOB_PRODUCEWEIGHT'], 0)." kg</td>";
                                            echo "<td align='center'>";
                                            echo "<button type='button' class='btn-action-work' onclick=\"go_entry('{$job_order}')\">⚙️ SELECT</button>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <!-- TAB 2: HISTORY & REPRINT -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">📦 History Wrap Product / Reprint Label</h4>
                            </div>
                            <div class="table-responsive table-full-width">
                                <table id="history_table" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Product No</th>
                                            <th style="text-align: center;">Job Order</th>
                                            <th style="text-align: center;">Sale Order</th>
                                            <th style="text-align: center;">Alloy</th>
                                            <th style="text-align: right;">Produce Weight</th>
                                            <th style="text-align: center;">Operate Date</th>
                                            <th style="text-align: center;">Action / Reprint</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");

                                        $params_h = [];
                                        $where_h = "PRODUCT_ID = 'WR' AND PRODUCT_MODEL = 'WRAP'";
                                        if (!empty($jno)) {
                                            $where_h .= " AND (PRODUCT_NO LIKE :jno OR JOB_ORDER LIKE :jno)";
                                            $params_h[':jno'] = '%' . $jno . '%';
                                        }

                                        $sql_h = "SELECT TOP 100 PRODUCT_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, ALLOY, CRSH_PRODUCEWEIGHT, CRSH_OPERATEDATE FROM CRSHPROD1 WHERE {$where_h} ORDER BY CRSH_OPERATEDATE DESC";
                                        $stmt_h = $conn->prepare($sql_h);
                                        $stmt_h->execute($params_h);

                                        while($row_h = $stmt_h->fetch(PDO::FETCH_ASSOC)) {
                                            $pno = htmlspecialchars($row_h['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                            $job = htmlspecialchars($row_h['JOB_ORDER'], ENT_QUOTES, 'UTF-8');
                                            $so  = htmlspecialchars($row_h['SALEORDER_NO'] . '/' . $row_h['SALEORDER_ITEM'], ENT_QUOTES, 'UTF-8');
                                            $dt  = !empty($row_h['CRSH_OPERATEDATE']) ? date('Y-m-d H:i', strtotime($row_h['CRSH_OPERATEDATE'])) : '-';
                                            
                                            $print_link = "print_circle_sheet_product_label_mats.php?productno=" . urlencode($pno) . "&joborder=" . urlencode($job);

                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-pno'>📦 {$pno}</span></td>";
                                            echo "<td align='center'><span class='badge-job'>🔹 {$job}</span></td>";
                                            echo "<td align='center'>{$so}</td>";
                                            echo "<td align='center'>".htmlspecialchars($row_h['ALLOY'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='right' style='font-weight:600;'>".number_format((float)$row_h['CRSH_PRODUCEWEIGHT'], 0)." kg</td>";
                                            echo "<td align='center' style='color:#64748b;'>{$dt}</td>";
                                            echo "<td align='center'>";
                                            echo "<a href='{$print_link}' target='_blank' class='btn-action-print'>🖨️ REPRINT LABEL</a>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <input type="hidden" id="active_tab" value="<?php echo $active_tab;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>
<?php include 'include/footer.php';?>
<script>
$('.data-table').DataTable({
    paging: true,
    pageLength: 10,
    lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "All"]],
    order: [[0, 'desc']]
});

// เปิด Popup Print อัตโนมัติเมื่อ Save สำเร็จโดยไม่ถูก Browser บล็อก
<?php if (!empty($print_url)): ?>
    window.open('<?php echo $print_url; ?>', '_blank');
<?php endif; ?>

function search_data(){
    var func = $('#func').val();
    var jno = $('#job_no').val();
    var tab = $('#active_tab').val();
    window.location.assign('wrapping_process_mats.php?func=' + encodeURIComponent(func) + '&tab=' + encodeURIComponent(tab) + '&jno=' + encodeURIComponent(jno));
}

function go_entry(jno){
    var func = $('#func').val();
    window.location.assign('wrapping_entry_mats.php?func=' + encodeURIComponent(func) + '&jno=' + encodeURIComponent(jno));
}
</script>
</html>