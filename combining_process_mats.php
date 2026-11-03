<?php
session_start();
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$jno = isset($_GET['jno']) ? trim($_GET['jno']) : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
$print_url = isset($_GET['print_url']) ? $_GET['print_url'] : '';

include("dbcon_mats-new.php");

// ดึงประวัติรายการที่ Combine แล้ว (สำหรับแท็บ History)
$history_list = $conn->query("SELECT TOP 100 C.PRODUCT_NO, C.JOB_ORDER, C.SALEORDER_NO, C.SALEORDER_ITEM, C.ALLOY, C.CRSH_PRODUCEWEIGHT, C.CRSH_OPERATEDATE, M.PRODUCT_REFERENCE, M.COIL_NO 
                              FROM CRSHPROD1 AS C 
                              LEFT JOIN CMBNPROD1 AS M ON M.PRODUCT_NO = C.PRODUCT_NO 
                              WHERE C.PRODUCT_ID = 'WR' AND C.PRODUCT_MODEL = 'WRAP' 
                              ORDER BY C.CRSH_OPERATEDATE DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, 'Sarabun', sans-serif; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        
        .nav-tabs-custom { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { background: #f1f5f9; color: #64748b; font-weight: 700; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; font-size: 13.5px; transition: all 0.2s; }
        .tab-btn.active { background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2); }
        .tab-btn:hover { background: #e2e8f0; color: #334155; text-decoration: none; }

        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 38px; font-size: 14px; font-weight: 600; color: #1e293b; width: 100%; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; height: 38px; padding: 0 20px; border-radius: 8px; border: none; cursor: pointer; }
        
        .data-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; padding: 10px 8px; border-bottom: 2px solid #cbd5e1; }
        .data-table tbody td { padding: 10px 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bfdbfe; font-size: 12px; display: inline-block; }
        .badge-pno { background-color: #f0fdf4; color: #166534; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bbf7d0; font-size: 12px; display: inline-block; }
        .btn-action-select { background-color: #059669; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-select:hover { background-color: #047857; }
        .btn-action-edit { background-color: #d97706; color: #ffffff; font-weight: 700; padding: 5px 10px; border-radius: 6px; border: none; font-size: 11.5px; text-decoration: none; display: inline-block; }
        .btn-action-print { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 5px 10px; border-radius: 6px; border: none; font-size: 11.5px; text-decoration: none; display: inline-block; }
        .btn-action-delete { background-color: #dc2626; color: #ffffff; font-weight: 700; padding: 5px 10px; border-radius: 6px; border: none; font-size: 11.5px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'Combining';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Combining Warp Product Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="nav-tabs-custom">
                <a href="combining_process_mats.php?func=<?php echo $folder_func; ?>&tab=pending" class="tab-btn <?php echo ($active_tab == 'pending') ? 'active' : ''; ?>">
                    📥 Combining Warp Product (Pending Jobs)
                </a>
                <a href="combining_process_mats.php?func=<?php echo $folder_func; ?>&tab=history" class="tab-btn <?php echo ($active_tab == 'history') ? 'active' : ''; ?>">
                    📋History Combining Warp Product
                </a>
            </div>

            <?php if ($active_tab == 'pending'): ?>
                <div class="filter-wrapper">
                    <div style="flex: 1; min-width: 220px;">
                        <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Search Job Order</label>
                        <input class="form-control-minimal" id="job_no" type="text" placeholder="Enter Job Order..." value="<?php echo $jno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_job()"/>
                    </div>
                    <div>
                        <button class="btn-search" onclick="search_job()">🔍 SEARCH</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="table-responsive table-full-width">
                        <table id="job_table" class="table data-table">
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
                                    echo "<button type='button' class='btn-action-select' onclick=\"go_entry('{$job_order}')\">⚙️ SELECT JOB</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <div class="dashboard-card">
                    <div class="table-responsive table-full-width">
                        <table id="history_table" class="table data-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">Product No</th>
                                    <th style="text-align: center;">Job Order</th>
                                    <th style="text-align: center;">Sale Order</th>
                                    <th style="text-align: center;">Scrap Ref.</th>
                                    <th style="text-align: center;">Coil No</th>
                                    <th style="text-align: right;">Produce Weight</th>
                                    <th style="text-align: center;">Operate Date</th>
                                    <th style="text-align: center;">Action (Edit / Print / Delete)</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($history_list as $h): ?>
                                <?php 
                                    $pno = htmlspecialchars($h['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                    $job = htmlspecialchars($h['JOB_ORDER'], ENT_QUOTES, 'UTF-8');
                                    $so  = htmlspecialchars($h['SALEORDER_NO'] . '/' . $h['SALEORDER_ITEM'], ENT_QUOTES, 'UTF-8');
                                    $ref = htmlspecialchars($h['PRODUCT_REFERENCE'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $coil= htmlspecialchars($h['COIL_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $wt  = number_format((float)$h['CRSH_PRODUCEWEIGHT'], 0);
                                    $dt  = !empty($h['CRSH_OPERATEDATE']) ? date('Y-m-d H:i', strtotime($h['CRSH_OPERATEDATE'])) : '-';
                                    $print_link = "print_circle_sheet_product_label_mats.php?productno=" . urlencode($pno) . "&joborder=" . urlencode($job);
                                    $edit_link = "combining_entry_mats.php?func=" . urlencode($folder_func) . "&jno=" . urlencode($job) . "&pno=" . urlencode($pno);
                                ?>
                                <tr>
                                    <td align='center'><span class='badge-pno'>📦 <?php echo $pno; ?></span></td>
                                    <td align='center'><span class='badge-job'>🔹 <?php echo $job; ?></span></td>
                                    <td align='center'><?php echo $so; ?></td>
                                    <td align='center'><b><?php echo $ref; ?></b></td>
                                    <td align='center'><?php echo $coil; ?></td>
                                    <td align='right' style='font-weight:600; color:#059669;'><?php echo $wt; ?> kg</td>
                                    <td align='center' style='color:#64748b;'><?php echo $dt; ?></td>
                                    <td align='center' style="display: flex; gap: 6px; justify-content: center;">
                                        <a href='<?php echo $edit_link; ?>' class='btn-action-edit'>✏️ Edit</a>
                                        <a href='<?php echo $print_link; ?>' target='_blank' class='btn-action-print'>🖨️ Print</a>
                                        <a href='model/combining_delete_action.php?productno=<?php echo urlencode($pno); ?>&ref=<?php echo urlencode($ref); ?>&jno=<?php echo urlencode($job); ?>&func=<?php echo urlencode($folder_func); ?>' 
                                           class='btn-action-delete' onclick="return confirm('⚠️ Are you sure you want to delete Product No: <?php echo $pno; ?> and revert its status to Scrap ?');">🗑️ Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>
<?php include 'include/footer.php';?>
<script>
$('.data-table').DataTable({ paging: true, pageLength: 10, order: [[0, 'desc']] });

<?php if (!empty($print_url)): ?>
    window.open('<?php echo $print_url; ?>', '_blank');
<?php endif; ?>

function search_job(){
    window.location.assign('combining_process_mats.php?func=' + encodeURIComponent($('#func').val()) + '&tab=pending&jno=' + encodeURIComponent($('#job_no').val()));
}

function go_entry(jno){
    window.location.assign('combining_entry_mats.php?func=' + encodeURIComponent($('#func').val()) + '&jno=' + encodeURIComponent(jno));
}
</script>
</html>