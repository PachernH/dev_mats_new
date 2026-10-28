<?php
// stretcher_process_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการ Stretcher Process');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

$jno  = !isset($_GET['jno']) ? '' : htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8');
$mode = isset($_GET['mode']) && $_GET['mode'] === 'history' ? 'history' : 'pending';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Stretcher Process</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; }
        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; }
        .badge-prod { background-color: #fef3c7; color: #92400e; font-weight: 700; font-size: 12px; padding: 4px 8px; border-radius: 6px; }
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }
        .btn-action-edit { background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-edit:hover { background-color: #1d4ed8; }

        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Stretcher Process
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="tab-btn-group">
                <a href="stretcher_process_mats.php?func=<?php echo $folder_func; ?>&mode=pending&jno=<?php echo $jno; ?>" 
                   class="tab-btn <?php echo ($mode === 'pending') ? 'active' : ''; ?>">
                   📏 Pending Stretcher
                </a>
                <a href="stretcher_process_mats.php?func=<?php echo $folder_func; ?>&mode=history&jno=<?php echo $jno; ?>" 
                   class="tab-btn <?php echo ($mode === 'history') ? 'active' : ''; ?>">
                   📋 Finished & Edit
                </a>
            </div>

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label style="font-size:12px; font-weight:700; color:#64748b;">ค้นหา JOB ORDER หรือ PRODUCT NO.</label>
                    <input class="form-control-minimal" name="job_no" id="job_no" type="text" placeholder="พิมพ์ Job Order หรือ Product No..." value="<?php echo $jno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_job()"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" onclick="search_job()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="dashboard-card">
                <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                    <?php if ($mode === 'pending'): ?>
                        📏 Pending Stretcher: NEXTPROCESS = 'SC'
                    <?php else: ?>
                        📋 Stretcher Records: Status OP / AC
                    <?php endif; ?>
                </h4>

                <div class="table-responsive">
                    <table id="user_table" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center;">Product No</th>
                                <th style="text-align: center;">Job Order</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Alloy</th>
                                <th style="text-align: center;">Temper</th>
                                <th style="text-align: right;">Thickness (mm)</th>
                                <th style="text-align: right;">Width (mm)</th>
                                <th style="text-align: right;">Length (mm)</th>
                                <th style="text-align: right;"><?php echo ($mode === 'pending') ? 'Input Wt (kg)' : 'Accept Wt (kg)'; ?></th>
                                <th style="text-align: right;"><?php echo ($mode === 'pending') ? 'Input Pcs' : 'Accept Pcs'; ?></th>
                                <th style="text-align: center;">Work Process</th>
                                <th style="text-align: center; width: 140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            include 'function_mats.php';
                            include("dbcon_mats-new.php");

                            $params = [];

                            if ($mode === 'pending') {
                                // เงื่อนไข cmdNewProductLookup ของ STCHPROD1 ใน f9.txt:
                                // CRSH_STATUS = 'OP' AND CRSH_NEXTPROCESS = 'SC' AND (CRSH_WORKPROCESS LIKE 'CL>%' OR 'RC>%' OR 'KP>%')
                                $where = "p.CRSH_STATUS = 'OP' AND p.CRSH_NEXTPROCESS = 'SC' 
                                          AND (p.CRSH_WORKPROCESS LIKE 'CL>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'RC>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'KP>%')";

                                $sql = "SELECT p.PRODUCT_NO, p.JOB_ORDER, p.CRSH_STATUS, p.ALLOY, p.TEMPER, p.THICKNESS, 
                                               p.WIDTH, p.LENGTH, 
                                               p.CRSH_ACTUALWEIGHT AS DISP_WT, p.CRSH_ACTUALPIECE AS DISP_PC, p.CRSH_WORKPROCESS 
                                        FROM CRSHPROD1 p 
                                        WHERE {$where}";

                                if (!empty($jno)) {
                                    $sql .= " AND (p.JOB_ORDER LIKE :jno OR p.PRODUCT_NO LIKE :pno)";
                                    $params[':jno'] = '%' . $jno . '%';
                                    $params[':pno'] = '%' . $jno . '%';
                                }
                                $sql .= " ORDER BY p.PRODUCT_NO ASC";

                            } else {
                                // เงื่อนไข cmdOldProductLookup ของ STCHPROD1 ใน f9.txt:
                                $sql = "SELECT TOP 200 
                                               s.PRODUCT_NO, p.JOB_ORDER, p.CRSH_STATUS, p.ALLOY, p.TEMPER, p.THICKNESS, 
                                               p.WIDTH, p.LENGTH, 
                                               s.STCH_ACCEPTWEIGHT AS DISP_WT, s.STCH_ACCEPTPIECE AS DISP_PC, p.CRSH_WORKPROCESS,
                                               s.STCH_UPDATEDATE, s.STCH_OPERATEDATE
                                        FROM STCHPROD1 s
                                        INNER JOIN CRSHPROD1 p ON s.PRODUCT_NO = p.PRODUCT_NO 
                                        WHERE p.CRSH_STATUS IN ('OP', 'AC')";

                                if (!empty($jno)) {
                                    $sql .= " AND (p.JOB_ORDER LIKE :jno OR s.PRODUCT_NO LIKE :pno)";
                                    $params[':jno'] = '%' . $jno . '%';
                                    $params[':pno'] = '%' . $jno . '%';
                                }
                                $sql .= " ORDER BY COALESCE(s.STCH_UPDATEDATE, s.STCH_OPERATEDATE) DESC";
                            }

                            $stmt = $conn->prepare($sql);
                            $stmt->execute($params);

                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $safe_prod = htmlspecialchars($row['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                $safe_job  = htmlspecialchars($row['JOB_ORDER'], ENT_QUOTES, 'UTF-8');
                                $safe_stat = htmlspecialchars($row['CRSH_STATUS'], ENT_QUOTES, 'UTF-8');

                                echo "<tr>";
                                echo "<td align='center'><span class='badge-job'>📦 {$safe_prod}</span></td>";
                                echo "<td align='center' style='font-weight:600;'>{$safe_job}</td>";
                                echo "<td align='center'><span class='badge' style='background-color:#059669;'>{$safe_stat}</span></td>";
                                echo "<td align='center'>".htmlspecialchars($row['ALLOY'])."</td>";
                                echo "<td align='center'>".htmlspecialchars($row['TEMPER'])."</td>";
                                echo "<td align='right'>".number_format((float)$row['THICKNESS'], 3)."</td>";
                                echo "<td align='right'>".number_format((float)($row['WIDTH'] ?? 0), 3)."</td>";
                                echo "<td align='right'>".number_format((float)($row['LENGTH'] ?? 0), 3)."</td>";
                                echo "<td align='right' style='font-weight:700;'>".number_format((float)$row['DISP_WT'], 0)."</td>";
                                echo "<td align='right'>".number_format((float)$row['DISP_PC'], 0)."</td>";
                                echo "<td align='center'><span class='badge-prod'>".htmlspecialchars($row['CRSH_WORKPROCESS'])."</span></td>";
                                echo "<td align='center'>";
                                
                                if ($mode === 'pending') {
                                    echo "<button type='button' class='btn-action-work' onclick=\"go_to_entry('{$safe_prod}')\">📏 Stretcher</button>";
                                } else {
                                    echo "<button type='button' class='btn-action-edit' onclick=\"go_to_entry('{$safe_prod}')\">✏️ Edit Data</button>";
                                }
                                
                                echo "</td>";
                                echo "</tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <input class="form-control" id="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <input id="current_mode" value="<?php echo $mode;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
$("#user_table").DataTable({
    scrollY: "480px", 
    scrollCollapse: true, 
    paging: true, 
    pageLength: 20, 
    order: [] 
});
function search_job(){   
    var f = document.getElementById("func").value;
    var j = document.getElementById("job_no").value; 
    var m = document.getElementById("current_mode").value;
    window.location.assign('stretcher_process_mats.php?func='+encodeURIComponent(f)+'&mode='+encodeURIComponent(m)+'&jno='+encodeURIComponent(j)); 
}    
function go_to_entry(pno){
    var f = document.getElementById("func").value;
    window.location.assign('stretcher_entry_mats.php?func='+encodeURIComponent(f)+'&pno='+encodeURIComponent(pno));
}
</script>
</html>