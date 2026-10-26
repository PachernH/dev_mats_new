<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าวันที่ และตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
}

// รับค่าค้นหาถ้ามี
$jno = !isset($_GET['jno']) ? '' : htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { padding: 20px 20px !important; }
        
        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }     

        /* Filter Section Layout */
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
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Form Styling Control */
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }

        /* ช่องป้อน ค้นหาแบบไร้ขอบสี่เหลี่ยม */
        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 42px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus {
            border-bottom-color: #2563eb !important;
            outline: none;
        }

        /* Buttons Styling */
        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            height: 42px;
            padding: 0 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background-color: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-action-view {
            background-color: #10b981;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-view:hover {
            background-color: #059669;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* ปุ่ม Maintain */
        .btn-action-maintain {
            background-color: #2563eb;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-maintain:hover {
            background-color: #1d4ed8;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Modern Table Styles */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        
        #user_table tbody tr {
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }

        .status-badge {
            color: #059669;
            font-weight: 700;
            background-color: #ecfdf5;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A4';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="maintain_job_data_mats.php?func=<?php echo $folder_func ?>">Maintain Job Order Data of Production</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="job_no">Job Order No</label>
                    <input class="form-control-minimal" name="job_no" id="job_no" type="text" placeholder="Job Order No...." value="<?php echo htmlspecialchars($jno, ENT_QUOTES, 'UTF-8'); ?>" oninput="filter_table()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="change_date(this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="filter_table()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">
                                📋 Job Order Data Records <?php echo ($d != date('Y-m-d')) ? "(Date: ".htmlspecialchars($d).")" : "(Top 200 Recent)"; ?>
                            </h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th>Job Order</th>
                                        <th>Job Order Date</th>
                                        <th>Sale Order No</th>
                                        <th>Product ID</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Length</th>
                                        <th>Actual Weight</th>
                                        <th>Actual Piece</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Maintain</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $where_sql = "";

                                    // ถ้ามีการเลือกวันที่แบบเฉพาะเจาะจง (ไม่ใช่วันนี้) ให้กรองตาม JOBORDER_DATE
                                    if ($d != date('Y-m-d')) {
                                        $where_sql = "WHERE JOBORDER_DATE BETWEEN :date_st AND :date_end";
                                        $params[':date_st'] = $d . " 00:00:00";
                                        $params[':date_end'] = $d . " 23:59:59";
                                    }

                                    // Query 500 รายการจาก JOBORDER1
                                    $sql = "SELECT TOP 200 JOB_ORDER, JOBORDER_DATE, SALEORDER_NO, PRODUCT_ID, 
                                                   ALLOY, TEMPER, THICKNESS, WIDTH, LENGTH, JOB_ACTUALWEIGHT, JOB_ACTUALPIECE, JOB_STATUS 
                                            FROM JOBORDER1 
                                            {$where_sql}
                                            ORDER BY JOBORDER_DATE DESC, JOB_ORDER DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $jobs_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($jobs_list as $index => $job): 
                                    $raw_job = trim($job['JOB_ORDER'] ?? '');
                                    $safe_job = htmlspecialchars($raw_job !== '' ? $raw_job : '-', ENT_QUOTES, 'UTF-8');
                                    $status = trim($job['JOB_STATUS'] ?? '');
                                    
                                    // แปลงวันที่
                                    $job_date = !empty($job['JOBORDER_DATE']) ? date('Y-m-d', strtotime($job['JOBORDER_DATE'])) : '-';
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <?php if (!empty($raw_job) && $raw_job !== '-'): ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_job('<?php echo addslashes($raw_job); ?>')">
                                                    🔍 <?php echo $safe_job; ?>
                                                </button>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td align="center"><?php echo htmlspecialchars($job_date, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($job['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($job['PRODUCT_ID'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($job['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($job['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($job['THICKNESS']) : number_format((float)($job['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($job['WIDTH']) : number_format((float)($job['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($job['LENGTH']) : number_format((float)($job['LENGTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($job['JOB_ACTUALWEIGHT']) : number_format((float)($job['JOB_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo number_format((float)($job['JOB_ACTUALPIECE'] ?? 0)); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <!-- ปุ่ม Maintain -->
                                        <td align="center">
                                            <?php if (!empty($raw_job) && $raw_job !== '-'): ?>
                                                <button type="button" class="btn-action-maintain" onclick="maintain_job('<?php echo addslashes($raw_job); ?>')">
                                                    ⚙️ MAINTAIN
                                                </button>
                                            <?php else: ?>
                                                <span>-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">     

var select_date = '<?php echo $d;?>';
document.getElementById("pick_date").value = select_date;

var table = $("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[0, 'asc']],
    buttons: []
});

// ตรวจสอบว่าถ้าส่ง jno มาหลังจาก Save ให้ฟิลเตอร์ตารางอัตโนมัติ
$(document).ready(function() {
    var searchJobNo = '<?php echo $jno; ?>';
    if (searchJobNo !== '') {
        document.getElementById("job_no").value = searchJobNo;
        table.search(searchJobNo).draw();
    }
});

function filter_table() {
    var val = document.getElementById("job_no").value;
    table.search(val).draw();
}

function change_date(val) {
    var data_fun = document.getElementById("func").value;
    var data_jno = document.getElementById("job_no").value;
    window.location.assign('maintain_job_data_mats.php?func=' + encodeURIComponent(data_fun) + '&d=' + encodeURIComponent(val) + '&jno=' + encodeURIComponent(data_jno));
}

function show_detail_job(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_job_coil_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&jno=' + encodeURIComponent(do_no));
}

function maintain_job(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_job_edit_mats.php?func=' + encodeURIComponent(data_fun) + '&jno=' + encodeURIComponent(do_no));
}

</script>
</html>