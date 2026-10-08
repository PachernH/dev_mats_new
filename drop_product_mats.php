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

$sno = !isset($_GET['search_no']) ? '' : htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8');

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

        .btn-action-coil {
            background-color: #2563eb;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-coil:hover {
            background-color: #1d4ed8;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* ปุ่ม Drop สีแดง */
        .btn-action-drop {
            background-color: #ef4444;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-drop:hover {
            background-color: #dc2626;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="drop_product_mats.php?func=<?php echo $folder_func ?>">Drop Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="search_no">Coil No</label>
                    <input class="form-control-minimal" name="search_no" id="search_no" type="text" placeholder="Enter Coil No. to search..." value="<?php echo htmlspecialchars($sno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Active Coil Records with Balance Weight (Max 500 Records)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Start Time</th>
                                        <th style="text-align: center;">Coil No</th>
                                        <th style="text-align: center;">Batch No</th>
                                        <th style="text-align: center;">Job Process</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: center;">Thickness</th>
                                        <th style="text-align: center;">Width</th>
                                        <th style="text-align: center;">Balance Weight</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    // 1. กำหนดเงื่อนไขหลักบังคับ: COIL_STATUS = 'AC' AND COIL_BALANCEWEIGHT > 0
                                    $params = [];
                                    $where_conditions = [
                                        "COIL_STATUS = 'AC'",
                                        "COIL_BALANCEWEIGHT > 0"
                                    ];

                                    // 2. ตรวจสอบว่ามีการพิมพ์ค้นหา Job Process, Coil No. หรือ Batch No. หรือไม่
                                    if (!empty($sno)) {
                                        $where_conditions[] = "(JOB_PROCESS = :jno OR COIL_NO = :jno OR BATCH_NO = :jno)";
                                        $params[':jno'] = $sno;
                                    } else {
                                        // หากไม่ได้พิมพ์ค้นหา ให้ใช้การกรองตามวันที่ (ถ้าเลือกวันที่ที่ไม่ใช่วันนี้)
                                        if ($d != date('Y-m-d')) {
                                            $where_conditions[] = "COIL_STARTTIME BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    // 3. รวมเงื่อนไข WHERE เข้าด้วยกัน
                                    $where_sql = "WHERE " . implode(" AND ", $where_conditions);

                                    // 4. Query ดึงข้อมูล 500 รายการ
                                    $sql = "SELECT TOP 500 
                                                COIL_STARTTIME, COIL_ENDTIME, COIL_NO, BATCH_NO, JOB_PROCESS, 
                                                ALLOY, TEMPER, THICKNESS, WIDTH, COIL_BALANCEWEIGHT, COIL_STATUS
                                            FROM COILPROD1
                                            {$where_sql}
                                            ORDER BY COIL_STARTTIME DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $job_process = htmlspecialchars($coil['JOB_PROCESS'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $safe_coil   = htmlspecialchars($coil['COIL_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $batch_no    = htmlspecialchars($coil['BATCH_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $status      = trim($coil['COIL_STATUS'] ?? '');
                                ?>
                                    <tr>
                                        <!-- 1. # -->
                                        <td align="center"><?php echo $index + 1; ?></td>

                                        <!-- 2. Start Time -->
                                        <td align="center"><?php echo htmlspecialchars($coil['COIL_STARTTIME'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 3. Coil No -->
                                        <td align="center">
                                            <button type="button" class="btn-action-coil" onclick="show_detail_coil('<?php echo addslashes($coil['COIL_NO']); ?>')">
                                                📦 <?php echo $safe_coil; ?>
                                            </button>
                                        </td>

                                        <!-- 4. Batch No -->
                                        <td align="center">
                                            <?php if (!empty($batch_no) && $batch_no !== '-'): ?>
                                                <button type="button" class="btn-action-coil" onclick="show_detail_batch('<?php echo addslashes($batch_no); ?>')">
                                                    📦 <?php echo $batch_no; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td> 

                                        <!-- 5. Job Process -->
                                        <td align="center">
                                            <?php if (!empty($job_process) && $job_process !== '-'): ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_job('<?php echo addslashes($job_process); ?>')">
                                                    🔍 <?php echo $job_process; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <!-- 6. Alloy -->
                                        <td align="center"><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 7. Temper -->
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>

                                        <!-- 8. Thickness -->
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>

                                        <!-- 9. Width -->
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['WIDTH']) : number_format((float)($coil['WIDTH'] ?? 0), 2); ?></td>

                                        <!-- 10. Balance Weight -->
                                        <td align="center" style="font-weight: 700; color: #2563eb;"><?php echo function_exists('fmt2') ? fmt2($coil['COIL_BALANCEWEIGHT']) : number_format((float)($coil['COIL_BALANCEWEIGHT'] ?? 0), 2); ?> kg.</td>

                                        <!-- 11. Status -->
                                        <td align="center"><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>

                                        <!-- 12. Drop Action Button -->
                                        <td align="center">
                                            <button type="button" class="btn-action-drop" onclick="drop_coil('<?php echo addslashes($coil['COIL_NO']); ?>')">
                                                🗑️ DROP
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
    order: [[0, 'asc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("search_no").value; 
    window.location.assign('drop_product_mats.php?func='+encodeURIComponent(data_fun)+'&search_no='+encodeURIComponent(data_cno)); 
}    

function show_detail_job(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('drop_job_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(do_no));
}

function show_detail_coil(coil_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('drop_coil_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&COIL=' + encodeURIComponent(coil_no));
}

function show_detail_batch(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('drop_batch_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(do_no));
}

function drop_coil(coil_no) {
    var data_fun = document.getElementById("func").value;
    if (confirm("คุณต้องการที่จะ Drop คอยล์หมายเลข " + coil_no + " ใช่หรือไม่?")) {
        window.location.assign('drop_coil_process_mats.php?func=' + encodeURIComponent(data_fun) + '&COIL=' + encodeURIComponent(coil_no));
    }
}
</script>
</html>