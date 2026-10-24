<?php
// เริ่ม session
session_start();

// ดึงค่า Session ต่างๆ
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : 'K'; // Default Line K
$group_func      = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$process_func    = isset($_SESSION['PROCESS_GROUP']) ? htmlspecialchars($_SESSION['PROCESS_GROUP'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

// ถ้าไม่มี Session ให้กลับไปหน้า index.php
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// =========================================================================
// สิทธิ์พิเศษ: ต้องเป็น CEO, MANAGER, SUPERVISOR และต้องอยู่แผนก PD, IT, TC, MG
// =========================================================================
$bypass_positions    = ['CEO', 'MANAGER', 'SUPERVISOR'];
$allowed_departments = ['PD', 'IT', 'TC', 'MG'];

$is_supervisor_up = in_array($position_func, $bypass_positions) && in_array($department_func, $allowed_departments);

// ตรวจสอบ Line Process (Circle Press: Line K, L)
$allowed_lines_kp = ['K', 'L'];
if (!$is_supervisor_up && !in_array($line_func, $allowed_lines_kp)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ ไม่ตรงกับกระบวนการ Circle Press Process (Line K, L)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

// เช็กว่า Line ที่ล็อกอินตรงกับสายการผลิตนี้หรือไม่ สำหรับคุมการกดปุ่ม Action
$is_correct_line = in_array($line_func, $allowed_lines_kp);

// ดึงค่าและตรวจสอบข้อมูลนำเข้า
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

$jno = !isset($_GET['jno']) ? '' : htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Circle Press Process (Line <?php echo $line_func; ?>)</title>
    <?php include 'include/header.php';?>
    
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
            font-size: 12px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control {
            border-radius: 8px; border: 1px solid #cbd5e1; height: 40px;
            padding: 8px 12px; font-size: 14px; color: #334155; transition: all 0.2s ease; box-shadow: none;
        }
        .form-control:focus {
            border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); outline: none;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 40px; font-size: 15px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        /* Table Styling */
        .table-responsive { border: none !important; margin-top: 10px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th {
            background-color: #f8fafc; color: #475569; font-weight: 700;
            text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;
            padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #user_table tbody td {
            padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px; color: #334155; white-space: nowrap;
        }
        
        #user_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #user_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* Badges for Job & Sale Order */
        .badge-job {
            display: inline-flex; align-items: center; gap: 4px;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px;
            padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; transition: all 0.2s;
        }
        .badge-job:hover { background-color: #dbeafe; color: #1e40af; }

        .badge-so {
            display: inline-flex; align-items: center; gap: 4px;
            background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12.5px;
            padding: 5px 12px; border-radius: 20px; border: 1px solid #a7f3d0; transition: all 0.2s;
        }
        .badge-so:hover { background-color: #d1fae5; color: #065f46; }

        .badge-product-id {
            font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; display: inline-block;
        }
        .badge-prod-sh { background-color: #fef3c7; color: #b45309; }
        .badge-prod-cc { background-color: #e0e7ff; color: #4338ca; }
        .badge-prod-nc { background-color: #f3e8ff; color: #6b21a8; }

        /* Action Buttons */
        .btn-action-map {
            background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 12.5px;
            padding: 6px 14px; border-radius: 6px; border: none; min-width: 130px;
            box-shadow: 0 1px 2px rgba(37, 99, 235, 0.15); transition: all 0.2s;
        }
        .btn-action-map:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        .btn-action-work {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12.5px;
            padding: 6px 14px; border-radius: 6px; border: none; min-width: 130px;
            box-shadow: 0 1px 2px rgba(5, 150, 105, 0.2); transition: all 0.2s;
        }
        .btn-action-work:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }

        /* Lock / Busy Buttons Status Styles */
        .btn-action-locked {
            background-color: #d97706 !important; color: #ffffff !important; font-weight: 700; font-size: 12px;
            padding: 6px 14px; border-radius: 6px; border: none; min-width: 130px; cursor: not-allowed; opacity: 0.85;
        }
        .btn-action-disabled {
            background-color: #94a3b8 !important; color: #ffffff !important; font-weight: 600; font-size: 12px;
            padding: 6px 14px; border-radius: 6px; border: none; min-width: 130px; cursor: not-allowed !important; opacity: 0.75;
        }
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
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="k_press_mats.php?func=<?php echo $folder_func ?>">
                        Circle Press Process (Line <?php echo $line_func; ?>)
                        <?php if ($is_supervisor_up && !$is_correct_line): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY (LINE <?php echo $line_func; ?>)</span>
                        <?php elseif ($is_supervisor_up): ?>
                            <span class="badge" style="background-color:#d97706; font-size:11px; margin-left:8px;">AUTHORIZED ACCESS</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="job_no">Job Process No.</label>
                    <input class="form-control-minimal" name="job_no" id="job_no" type="text" placeholder="Enter Job Order..." value="<?php echo htmlspecialchars($jno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        🔍 SEARCH
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:17px;">📑 Circle Press Job Process Records (Line <?php echo $line_func; ?>)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 140px;">Job Process</th>
                                        <th style="text-align: center;">Job Date</th>
                                        <th style="text-align: center; width: 140px;">Sale Order</th>
                                        <th style="text-align: center;">Prod ID</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: right;">Thickness</th>
                                        <th style="text-align: right;">Width / Diam.</th>
                                        <th style="text-align: center;">Workprocess</th>
                                        <th style="text-align: center;">Release Wt / Pcs</th>
                                        <th style="text-align: center; width: 160px;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");

                                    // ตรวจสอบว่า Line ปัจจุบัน ($line_func) กำลังเปิดใช้งาน Coil อยู่หรือไม่
                                    $active_job_in_current_line = '';
                                    $sql_chk_line = "SELECT TOP 1 JOB_ORDER FROM COILWORK1 WHERE LINE_PROCESS = :line_process ORDER BY WORK_OPERATEDATE DESC";
                                    $stmt_chk_line = $conn->prepare($sql_chk_line);
                                    $stmt_chk_line->bindParam(':line_process', $line_func, PDO::PARAM_STR);
                                    $stmt_chk_line->execute();
                                    $row_chk_line = $stmt_chk_line->fetch(PDO::FETCH_ASSOC);
                                    if ($row_chk_line) {
                                        $active_job_in_current_line = trim($row_chk_line['JOB_ORDER'] ?? '');
                                    }

                                    $y = date('Y');
                                    $date_st = $y.'-01-01 00:00:00';
                                    $date_end = $y.'-12-31 23:59:59';

                                    $params = [];
                                    
                                    // เงื่อนไขสอดคล้องตาม Line K, L
                                    $where_base = "(j.PRODUCT_ID = 'CC' OR j.PRODUCT_ID = 'SH' OR j.PRODUCT_ID = 'NC') 
                                                   AND j.JOB_STATUS = 'OP' 
                                                   AND j.JOB_WORKPROCESS LIKE 'KP>%'";

                                    $select_fields = "SELECT j.JOB_ORDER, j.JOBORDER_DATE, j.SALEORDER_NO, j.SALEORDER_ITEM, j.PRODUCT_ID,
                                                             j.ALLOY, j.TEMPER, j.THICKNESS, j.WIDTH, j.JOB_WORKPROCESS, 
                                                             j.JOB_RELEASEWEIGHT, j.JOB_RELEASEPIECE, j.JOB_PRODUCEWEIGHT,
                                                             cw.COIL_NO AS WORK_COIL_NO,
                                                             cw.LINE_PROCESS AS WORK_LINE_PROCESS ";
                                    
                                    // JOIN เช็กสถานะการใช้งาน Coil ทั้งใน Line ปัจจุบัน และ Line อื่นๆ
                                    $from_sql = " FROM JOBORDER1 j 
                                                  OUTER APPLY (
                                                      SELECT TOP 1 COIL_NO, LINE_PROCESS 
                                                      FROM COILWORK1 
                                                      WHERE JOB_ORDER = j.JOB_ORDER 
                                                      ORDER BY WORK_OPERATEDATE DESC
                                                  ) cw ";

                                    if($d == date('Y-m-d')){
                                        if($jno != ''){
                                            $sql = $select_fields . $from_sql . " WHERE {$where_base} AND j.JOB_ORDER = :jno";
                                            $params[':jno'] = $jno;     
                                        } else {
                                            $sql = $select_fields . $from_sql . " WHERE {$where_base} AND j.JOBORDER_DATE BETWEEN :date_st AND :date_end ORDER BY j.JOB_ORDER DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        } 
                                    } else {
                                        if($jno != ''){
                                            $sql = $select_fields . $from_sql . " WHERE {$where_base} AND j.JOB_ORDER = :jno";
                                            $params[':jno'] = $jno;   
                                        } else {
                                            $sql = $select_fields . $from_sql . " WHERE {$where_base} AND j.JOBORDER_DATE BETWEEN :date_st AND :date_end ORDER BY j.JOB_ORDER DESC";
                                            $params[':date_st'] = $d." 00:00:00";
                                            $params[':date_end'] = $d." 23:59:59";    
                                        }
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_job_no    = htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_so_no     = htmlspecialchars($row['SALEORDER_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_so_item   = htmlspecialchars($row['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $product_id     = strtoupper(trim($row['PRODUCT_ID'] ?? 'SH'));
                                        $work_coil_no   = $row['WORK_COIL_NO'] ?? '';
                                        $work_line_proc = strtoupper(trim($row['WORK_LINE_PROCESS'] ?? ''));
                                        
                                        $job_date_fmt = !empty($row['JOBORDER_DATE']) ? date('Y-m-d', strtotime($row['JOBORDER_DATE'])) : '-';
                                        $so_display   = $safe_so_no . ($safe_so_item !== '' ? '/' . $safe_so_item : '');

                                        $prod_class = 'badge-prod-sh';
                                        if ($product_id === 'CC') $prod_class = 'badge-prod-cc';
                                        if ($product_id === 'NC') $prod_class = 'badge-prod-nc';

                                        echo "<tr onclick=\"show_job_detail_kp(event, '" . addslashes($row['JOB_ORDER']) . "')\">";
                                        
                                        // 1. Job Process
                                        echo "<td align='center'>
                                                <span class='badge-job' onclick=\"show_job_detail_kp(event, '" . addslashes($row['JOB_ORDER']) . "')\">
                                                    🔹 ".$safe_job_no."
                                                </span>
                                              </td>";
                                        
                                        // 2. Job Date
                                        echo "<td align='center' style='font-weight:600; color:#475569;'>".$job_date_fmt."</td>";
                                        
                                        // 3. Sale Order
                                        echo "<td align='center'>";
                                        if(!empty($safe_so_no)) {
                                            echo "<span class='badge-so' onclick=\"show_so_detail_kp(event, '" . addslashes($safe_so_no) . "', '" . addslashes($safe_so_item) . "')\">
                                                    🏷️ ".$so_display."
                                                  </span>";
                                        } else {
                                            echo "<span style='color:#94a3b8;'>-</span>";
                                        }
                                        echo "</td>";

                                        // 4. Product ID
                                        echo "<td align='center'><span class='badge-product-id {$prod_class}'>".$product_id."</span></td>";
                                        
                                        // 5. Alloy
                                        echo "<td align='center' style='font-weight:600;'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 6. Temper
                                        echo "<td align='center'>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 7. Thickness
                                        echo "<td align='right' style='font-weight:600;'>".htmlspecialchars(fmt2($row['THICKNESS']) ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 8. Width / Diameter
                                        echo "<td align='right' style='font-weight:600;'>".htmlspecialchars(fmt2($row['WIDTH']) ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 9. Workprocess
                                        echo "<td align='center' style='font-weight:600; color:#d97706;'>".htmlspecialchars($row['JOB_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 10. Release Wt / Pcs
                                        echo "<td align='center' style='font-weight:600; color:#0f172a;'>".number_format((float)($row['JOB_RELEASEWEIGHT'] ?? 0), 0)." / ".number_format((float)($row['JOB_RELEASEPIECE'] ?? 0), 0)."</td>";
                                        
                                        // =========================================================================
                                        // 11. Action: ล็อกปุ่มหาก Line ที่ล็อกอินไม่ตรงกับ Line K, L
                                        // =========================================================================
                                        echo "<td align='center'>";

                                        // เงื่อนไขล็อก: ถ้า Line ปัจจุบันไม่ใช่ Line K หรือ L จะไม่สามารถกดแก้ไขหรือสร้างงานได้
                                        if (!$is_correct_line) {
                                            echo "<button type='button' class='btn-action-disabled' disabled title='Line ที่ล็อกอิน (" . $line_func . ") ไม่ตรงกับสายการผลิตนี้ ไม่สามารถแก้ไขข้อมูลได้'>
                                                    🔒 LOCKED (LINE {$line_func})
                                                  </button>";
                                        }
                                        // กรณีที่ 1: กำลังถูกเปิดผลิตอยู่ที่ Line อื่น
                                        else if (!empty($work_coil_no) && $work_line_proc !== $line_func) {
                                            echo "<button type='button' class='btn-action-locked' disabled title='Job นี้กำลังผลิตค้างอยู่ที่ Line {$work_line_proc}'>
                                                    🔒 IN USE (LINE {$work_line_proc})
                                                  </button>";
                                        } 
                                        // กรณีที่ 2: Map ตรงกับ Line ปัจจุบัน
                                        else if (!empty($work_coil_no) && $work_line_proc === $line_func) {
                                            echo "<button type='button' class='btn-action-work' onclick=\"go_to_work(event, '" . addslashes($row['JOB_ORDER']) . "', '" . addslashes($work_coil_no) . "')\">
                                                    ⚙️ WORK
                                                  </button>";
                                        } 
                                        // กรณีที่ 3: Line ปัจจุบันมี Job อื่นค้างอยู่
                                        else if (!empty($active_job_in_current_line) && $active_job_in_current_line !== $row['JOB_ORDER']) {
                                            echo "<button type='button' class='btn-action-disabled' disabled title='Line {$line_func} กำลังใช้งาน Job {$active_job_in_current_line} อยู่ ต้อง Keep/Close Coil เดิมก่อน'>
                                                    🔒 BUSY (LINE {$line_func})
                                                  </button>";
                                        } 
                                        // กรณีที่ 4: Line ว่าง พร้อมสำหรับการ Map Coil ใหม่
                                        else {
                                            echo "<button type='button' class='btn-action-map' onclick=\"map_coil_no(event, '" . addslashes($row['JOB_ORDER']) . "')\">
                                                    🔗 MAP COIL
                                                  </button>";
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
    scrollX: false,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[1, 'desc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("job_no").value; 
    window.location.assign('k_press_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(data_cno)); 
}   

function show_job_detail_kp(e, do_no) {
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('k_press_job_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&jno=' + encodeURIComponent(do_no));
}

function show_so_detail_kp(e, so_no, so_item) {
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('k_press_saleorder_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&sono=' + encodeURIComponent(so_no) + '&soitem=' + encodeURIComponent(so_item));
}

function map_coil_no(e, do_no){
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('k_press_map_coil_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(do_no));         
} 

function go_to_work(e, do_no, cno){
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('k_press_entry_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(do_no)+'&cno='+encodeURIComponent(cno));        
}
</script>
</html>