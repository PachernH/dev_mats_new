<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า pno (ใช้รองรับ PRODUCT_NO) และกำหนดตัวแปรให้ตรงกัน
$pno = !isset($_GET['pno']) ? '' : htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8');

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

        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
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

        .btn-action-shopfloor {
            background-color: #8b5cf6;
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
        .btn-action-shopfloor:hover {
            background-color: #7c3aed;
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
            cursor: pointer;
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
        
        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'HI';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="Coil_Information()" class="btn-tab-item">Coil Information</button>
                <button onclick="Circle_Sheet_Information()" class="btn-tab-item active">Circle or Sheet Information</button>
            </div>
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="pd_no">Product No</label>
                    <input class="form-control-minimal" name="pd_no" id="pd_no" type="text" placeholder="Enter Product No. to search..." value="<?php echo htmlspecialchars($pno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Circle or Sheet Information Records (Max 500 Records)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Product No</th>
                                        <th style="text-align: center;">Product Ref</th>
                                        <th style="text-align: center;">Start Date</th>
                                        <th style="text-align: center;">Sale Order No</th>
                                        <th style="text-align: center;">Sale Order Item</th>
                                        <th style="text-align: center;">Job Order</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: center;">Thickness</th>
                                        <th style="text-align: center;">Width</th>
                                        <th style="text-align: center;">Length</th>
                                        <th style="text-align: center;">Actual Weight</th>
                                        <th style="text-align: center;">Actual Piece</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Shop Floor</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $where_conditions = [];

                                    if (!empty($pno)) {
                                        $where_conditions[] = "c.PRODUCT_NO = :pno OR c.SALEORDER_NO = :pno OR c.JOB_ORDER = :pno";
                                        $params[':pno'] = $pno;
                                    } else {
                                        if ($d != date('Y-m-d')) {
                                            $where_conditions[] = "c.CRSH_STARTDATE BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

                                    $sql = "SELECT TOP 500 
                                                   c.PRODUCT_NO, c.PRODUCT_REFERENCE, c.CRSH_STARTDATE, c.SALEORDER_NO, 
                                                   c.SALEORDER_ITEM, c.JOB_ORDER, c.ALLOY, c.TEMPER, c.THICKNESS, 
                                                   c.WIDTH, c.LENGTH, c.CRSH_ACTUALWEIGHT, c.CRSH_ACTUALPIECE, c.CRSH_STATUS,
                                                   c.WEIGHT_PIECE, c.CRSH_BOTTOMWEIGHT
                                            FROM CRSHPROD1 AS c 
                                            {$where_sql}
                                            ORDER BY c.CRSH_STARTDATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $prod_no    = htmlspecialchars($coil['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $prod_ref   = trim($coil['PRODUCT_REFERENCE'] ?? '');
                                    $sale_order = htmlspecialchars($coil['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $job_order  = htmlspecialchars($coil['JOB_ORDER'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $status     = trim($coil['CRSH_STATUS'] ?? '');

                                    $start_date_formatted = !empty($coil['CRSH_STARTDATE']) ? date('Y-m-d', strtotime($coil['CRSH_STARTDATE'])) : '-';
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <button type="button" class="btn-action-view" onclick="show_detail_product('<?php echo addslashes($coil['PRODUCT_NO']); ?>')">
                                                🔍 <?php echo $prod_no; ?>
                                            </button>
                                        </td>
                                        <td align="center"><?php echo htmlspecialchars($prod_ref !== '' ? $prod_ref : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($start_date_formatted, ENT_QUOTES, 'UTF-8'); ?></td>
                                        
                                        <!-- Sale Order No ปุ่มกด -->
                                        <td align="center">
                                            <?php if (!empty($sale_order) && $sale_order !== '-'): ?>
                                                <?php $sale_item = htmlspecialchars($coil['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_saleorder('<?php echo addslashes($sale_order); ?>', '<?php echo addslashes($sale_item); ?>')">
                                                    🔍 <?php echo $sale_order; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td align="center"><?php echo htmlspecialchars($coil['SALEORDER_ITEM'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        
                                        <!-- Job Order ปุ่มกด -->
                                        <td align="center">
                                            <?php if (!empty($job_order) && $job_order !== '-'): ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_job('<?php echo addslashes($job_order); ?>')">
                                                    🔍 <?php echo $job_order; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td align="center"><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['WIDTH']) : number_format((float)($coil['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['LENGTH']) : number_format((float)($coil['LENGTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['CRSH_ACTUALWEIGHT']) : number_format((float)($coil['CRSH_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt0') ? fmt0($coil['CRSH_ACTUALPIECE']) : number_format((float)($coil['CRSH_ACTUALPIECE'] ?? 0)); ?></td>
                                        <td align="center"><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <!-- ปุ่ม Shop Floor -->
                                        <td align="center">
                                            <button type="button" class="btn-action-shopfloor" onclick="show_shopfloor_detail('<?php echo addslashes($coil['PRODUCT_NO']); ?>')">
                                                🏭 Shop Floor
                                            </button>
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
pick_date.value = select_date;   

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[0, 'asc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_pno = document.getElementById("pd_no").value; 
    window.location.assign('history_crsh_log_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(data_pno)); 
}    

function show_detail_product(prod_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&PRODUCT_NO=' + encodeURIComponent(prod_no));
}

function show_detail_saleorder(so_no, so_item) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_saleorder_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(so_no) + '&so_item=' + encodeURIComponent(so_item));
}

function show_detail_job(job_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_job_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(job_no));
}

function show_shopfloor_detail(prod_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_shopfloor_mats.php?func=' + encodeURIComponent(data_fun) + '&PRODUCT_NO=' + encodeURIComponent(prod_no));
}

function Coil_Information(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_coil_log_mats.php?func='+encodeURIComponent(data_fun)); 
}

function Circle_Sheet_Information(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_crsh_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>