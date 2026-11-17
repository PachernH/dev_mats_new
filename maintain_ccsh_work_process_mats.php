<?php
session_start();

// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : 'mats';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';

$product_no = !isset($_GET['pno']) ? '' : htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8');
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
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; width: 100%; padding: 0 4px; }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }
        
        .btn-search { background-color: #1e293b !important; color: #ffffff !important; font-weight: 600; font-size: 13px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-search:hover { background-color: #0f172a !important; transform: translateY(-1px); }

        .table-responsive { border: none !important; margin-top: 10px; overflow-x: auto; }
        #user_table { width: 100% !important; border-collapse: collapse !important; }
        #user_table thead th { background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase; padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        #user_table tbody td { padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; white-space: nowrap; color: #334155; }
        #user_table tbody tr:hover td { background-color: #f0f9ff !important; cursor: pointer; }
        
        .badge-pno { background: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; }
        .btn-action-edit { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-edit:hover { background-color: #1d4ed8; }
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
                        Maintain Circle or Sheet Work Process
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="product_no_search">Search Product No.</label>
                    <input class="form-control-minimal" name="product_no_search" id="product_no_search" type="text" placeholder="Enter Product No..." value="<?php echo htmlspecialchars($product_no, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onkeypress="checkEnter(event)"/>
                </div>
                <div class="filter-item">
                    <button type="button" class="btn-search" onclick="search_product()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b; font-size:17px;">📑 Product List (Circle or Sheet Product)</h4>
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">Product No.</th>
                                        <th style="text-align: center;">Job Order</th>
                                        <th style="text-align: center;">Coil No / Material In</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: right;">Thickness</th>
                                        <th style="text-align: right;">Width</th>
                                        <th style="text-align: center;">Work Process</th>
                                        <th style="text-align: center;">Next Process</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center; width: 160px;">Change Process</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    // ดึงข้อมูลสถานะ OP และตรงตามกลุ่ม Work Process (เพื่อให้กดเข้าไปดูรายละเอียดได้ทั้งหมดตามคำขอ)
                                    $where_sql = "WHERE CRSH_STATUS = 'OP' 
                                                  AND (
                                                      CRSH_WORKPROCESS LIKE '%BK>BA>%' OR 
                                                      CRSH_WORKPROCESS LIKE '%BK>AN>%' OR 
                                                      CRSH_WORKPROCESS LIKE '%CL>BA>SH>%' OR 
                                                      CRSH_WORKPROCESS LIKE '%CL>SH>BA>%' OR 
                                                      CRSH_WORKPROCESS LIKE '%CL>SH>PH>BA>%'
                                                  )";

                                    $params = [];
                                    if (!empty($product_no)) {
                                        $where_sql .= " AND PRODUCT_NO LIKE :pno";
                                        $params[':pno'] = '%' . $product_no . '%';
                                    }

                                    $sql = "SELECT PRODUCT_NO, JOB_ORDER, MATERIAL_IN, COIL_NO, ALLOY, TEMPER, THICKNESS, WIDTH, CRSH_WORKPROCESS, CRSH_NEXTPROCESS, CRSH_STATUS FROM CRSHPROD1 " . $where_sql . " ORDER BY PRODUCT_NO DESC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $pno = htmlspecialchars($row['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        echo "<tr>";
                                        echo "<td align='center'><span class='badge-pno'>".$pno."</span></td>";
                                        echo "<td align='center'>".htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['COIL_NO'] ?? $row['MATERIAL_IN'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='right'>".number_format((float)($row['THICKNESS'] ?? 0), 3)."</td>";
                                        echo "<td align='right'>".number_format((float)($row['WIDTH'] ?? 0), 3)."</td>";
                                        echo "<td align='center' style='color:#d97706; font-weight:600;'>".htmlspecialchars($row['CRSH_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center' style='font-weight:600;'>".htmlspecialchars($row['CRSH_NEXTPROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'><span class='label label-success' style='padding: 3px 8px; border-radius: 4px; background:#10b981; color:#fff;'>".htmlspecialchars($row['CRSH_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</span></td>";
                                        echo "<td align='center'>";
                                        echo "<button type='button' class='btn-action-edit' onclick=\"change_work_process(event, '".$pno."')\">⚙️ Change Process</button>";
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
    if (!$.fn.DataTable.isDataTable('#user_table')) {
        $('#user_table').DataTable({
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

function search_product() {
    var f = document.getElementById("func").value;
    var p = document.getElementById("product_no_search").value;
    window.location.assign('maintain_ccsh_work_process_mats.php?func=' + encodeURIComponent(f) + '&pno=' + encodeURIComponent(p));
}

function checkEnter(e) {
    if (e.keyCode === 13) {
        search_product();
    }
}

function change_work_process(e, pno) {
    if (e && e.stopPropagation) e.stopPropagation();
    var f = document.getElementById("func").value;
    window.location.assign('maintain_ccsh_work_entry_mats.php?func=' + encodeURIComponent(f) + '&pno=' + encodeURIComponent(pno));
}
</script>
</html>