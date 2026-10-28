<?php
// transfer_pallet_process_bk_mats.php (อ้างอิงเงื่อนไข f8 - ACRSHPROD2: สาย Blanking/Circle)
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการ Transfer Product to Pallet Process');
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
    <title>Meyer Aluminium - Transfer Product to Pallet Process [f8]</title>
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
        .btn-action-work { background-color: #0891b2; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-work:hover { background-color: #0e7490; }
        .btn-action-edit { background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-edit:hover { background-color: #1d4ed8; }

        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #0891b2; color: #fff; border-color: #0891b2; box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2); }
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
                        Transfer Product to Pallet Process (Blanking / Circle)
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="tab-btn-group">
                <a href="transfer_pallet_process_bk_mats.php?func=<?php echo $folder_func; ?>&mode=pending&jno=<?php echo $jno; ?>" 
                   class="tab-btn <?php echo ($mode === 'pending') ? 'active' : ''; ?>">
                   📦 Pending Transfer Pallet
                </a>
                <a href="transfer_pallet_process_bk_mats.php?func=<?php echo $folder_func; ?>&mode=history&jno=<?php echo $jno; ?>" 
                   class="tab-btn <?php echo ($mode === 'history') ? 'active' : ''; ?>">
                   📋 Finished & Edit
                </a>
            </div>

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label style="font-size:12px; font-weight:700; color:#64748b;">SEARCH JOB / PRODUCT NO.</label>
                    <input class="form-control-minimal" name="job_no" id="job_no" type="text" placeholder="ระบุ Job หรือ Product No..." value="<?php echo $jno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_job()"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" onclick="search_job()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="dashboard-card">
                <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                    <?php if ($mode === 'pending'): ?>
                        📦 รายการชิ้นงานรอถ่ายลงพาเลท (Pending: NEXTPROCESS = 'TW')
                    <?php else: ?>
                        📋 ประวัติบันทึกผลการถ่ายลงพาเลท (Transfer to Pallet Records: Status OP / AC)
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
                                <th style="text-align: right;">Thickness</th>
                                <th style="text-align: right;">Width</th>
                                <th style="text-align: right;">Length</th>
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
                                // โหมด 1: งานใหม่รอลงพาเลท (CRSH_NEXTPROCESS = 'TW') ตาม cmdNewProductLookup ใน f8.txt
                                $where = "p.CRSH_STATUS = 'OP' AND p.CRSH_NEXTPROCESS = 'TW' 
                                          AND (p.CRSH_WORKPROCESS LIKE 'BK>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'AP>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'KP>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'XC>%' 
                                               OR p.CRSH_WORKPROCESS LIKE 'RC>%')";

                                $sql = "SELECT p.PRODUCT_NO, p.JOB_ORDER, p.CRSH_STATUS, p.ALLOY, p.TEMPER, p.THICKNESS, p.WIDTH, p.LENGTH, 
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
                                // โหมด 2: ดึงงานเก่าจาก TPTPPROD1 ตาม cmdOldProductLookup ใน f8.txt
                                // กรองเฉพาะสายงาน BK, AP, KP, XC, RC และสถานะ OP, AC (BCRSH-06)
                                $sql = "SELECT TOP 200 
                                               t.PRODUCT_NO, p.JOB_ORDER, p.CRSH_STATUS, p.ALLOY, p.TEMPER, p.THICKNESS, p.WIDTH, p.LENGTH, 
                                               t.TPTP_ACCEPTWEIGHT AS DISP_WT, t.TPTP_ACCEPTPIECE AS DISP_PC, p.CRSH_WORKPROCESS,
                                               t.TPTP_UPDATEDATE, t.TPTP_OPERATEDATE
                                        FROM TPTPPROD1 t
                                        INNER JOIN CRSHPROD1 p ON t.PRODUCT_NO = p.PRODUCT_NO 
                                        WHERE p.CRSH_STATUS IN ('OP', 'AC')
                                        AND (p.CRSH_WORKPROCESS LIKE 'BK>%' 
                                             OR p.CRSH_WORKPROCESS LIKE 'AP>%' 
                                             OR p.CRSH_WORKPROCESS LIKE 'KP>%' 
                                             OR p.CRSH_WORKPROCESS LIKE 'XC>%' 
                                             OR p.CRSH_WORKPROCESS LIKE 'RC>%')";

                                if (!empty($jno)) {
                                    $sql .= " AND (p.JOB_ORDER LIKE :jno OR t.PRODUCT_NO LIKE :pno)";
                                    $params[':jno'] = '%' . $jno . '%';
                                    $params[':pno'] = '%' . $jno . '%';
                                }
                                $sql .= " ORDER BY COALESCE(t.TPTP_UPDATEDATE, t.TPTP_OPERATEDATE) DESC";
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
                                echo "<td align='right'>".number_format((float)$row['WIDTH'], 3)."</td>";
                                echo "<td align='right'>".number_format((float)$row['LENGTH'], 3)."</td>";
                                echo "<td align='right' style='font-weight:700;'>".number_format((float)$row['DISP_WT'], 0)."</td>";
                                echo "<td align='right'>".number_format((float)$row['DISP_PC'], 0)."</td>";
                                echo "<td align='center'><span class='badge-prod'>".htmlspecialchars($row['CRSH_WORKPROCESS'])."</span></td>";
                                echo "<td align='center'>";
                                
                                if ($mode === 'pending') {
                                    echo "<button type='button' class='btn-action-work' onclick=\"go_to_entry('{$safe_prod}')\">📦 Transfer Pallet</button>";
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
    window.location.assign('transfer_pallet_process_bk_mats.php?func='+encodeURIComponent(f)+'&mode='+encodeURIComponent(m)+'&jno='+encodeURIComponent(j)); 
}    
function go_to_entry(pno){
    var f = document.getElementById("func").value;
    // ส่งต่อไปยังหน้า transfer_pallet_entry_bk_mats.php
    window.location.assign('transfer_pallet_entry_bk_mats.php?func='+encodeURIComponent(f)+'&pno='+encodeURIComponent(pno));
}
</script>
</html>