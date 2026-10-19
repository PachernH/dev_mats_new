<?php
// เริ่ม session
session_start();

// ดึงค่า Session ต่างๆ
$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func   = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$process_func = isset($_SESSION['PROCESS_GROUP']) ? htmlspecialchars($_SESSION['PROCESS_GROUP'], ENT_QUOTES, 'UTF-8') : '';

// ถ้าไม่มี Session ให้กลับไปหน้า index.php
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// ตรวจสอบ Line Process (Cut to Length: Line A, B)
$allowed_lines_ctl = ['A', 'B'];
if (!in_array(strtoupper($line_func), $allowed_lines_ctl)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ (" . $line_func . ") ไม่ตรงกับกระบวนการ Cut to Length Process (Line A, B)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

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
    <title>Meyer Aluminium Thailand Co.,LTD. - Cut to Length Process</title>
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        .filter-wrapper {
            display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;
            background: #ffffff; padding: 18px 24px; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 8px; }
        .filter-item label {
            font-size: 13px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control {
            border-radius: 8px; border: 1px solid #cbd5e1; height: 42px;
            padding: 8px 12px; font-size: 15px; color: #334155; transition: all 0.2s ease; box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 42px; font-size: 16px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 14px;
            letter-spacing: 0.5px; height: 42px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-green-search {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background-color: #059669; color: #ffffff !important; font-weight: 700; font-size: 13px;
            padding: 6px 14px; border-radius: 8px; border: none; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease; cursor: pointer; text-decoration: none !important;
        }
        .btn-green-search:hover { background-color: #047857; transform: translateY(-1px); }

        .table-responsive { border: none !important; margin-top: 15px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th {
            background-color: #f1f5f9; color: #475569; font-weight: 700;
            text-transform: uppercase; font-size: 14px; letter-spacing: 0.5px;
            padding: 16px 12px; border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 16px 12px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 15px; line-height: 1.6; color: #334155;
        }
        
        #user_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #user_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* ปุ่ม Action "MAP COIL" สีน้ำเงิน */
        .btn-action-map {
            background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 13px;
            padding: 8px 14px; border-radius: 6px; border: none; width: 100%;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15); transition: all 0.2s;
        }
        .btn-action-map:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        /* ปุ่ม Action "WORK" สีเขียว */
        .btn-action-work {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            padding: 8px 14px; border-radius: 6px; border: none; width: 100%;
            box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); transition: all 0.2s;
        }
        .btn-action-work:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="cut_to_length_mats.php?func=<?php echo $folder_func ?>">Cut to Length Process (Line <?php echo $line_func; ?>)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
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
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Cut to Length Job Process Records (Line <?php echo $line_func; ?>)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">Job Process</th>
                                        <th>JOB DATE</th>
                                        <th>SALE ORDER</th>
                                        <th>ALLOY</th>
                                        <th>TEMPER</th>
                                        <th>THICKNESS</th>
                                        <th>WIDTH</th>
                                        <th>WORKPROCESS</th>
                                        <th>RELEASE WEIGHT / PIECE</th>
                                        <th style="text-align: center;">ACTION</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $y = date('Y');
                                    $date_st = $y.'-01-01 00:00:00';
                                    $date_end = $y.'-12-31 23:59:59';

                                    $params = [];
                                    
                                    // ปรับเงื่อนไขให้ยืดหยุ่น: ไม่ตัด Job ออกทันทีเมื่อเจอน้ำหนักสะสมเพิ่มขึ้น[cite: 1]
                                    $where_base = "(j.PRODUCT_ID = 'CC' OR j.PRODUCT_ID = 'SH' OR j.PRODUCT_ID = 'NC') 
                                                   AND j.JOB_STATUS = 'OP' 
                                                   AND j.JOB_WORKPROCESS LIKE 'CL>%'";

                                    $select_fields = "SELECT j.JOB_ORDER, j.JOBORDER_DATE, j.SALEORDER_NO, j.SALEORDER_ITEM, 
                                                             j.ALLOY, j.TEMPER, j.THICKNESS, j.WIDTH, j.JOB_WORKPROCESS, 
                                                             j.JOB_RELEASEWEIGHT, j.JOB_RELEASEPIECE, j.JOB_PRODUCEWEIGHT,
                                                             cw.COIL_NO AS WORK_COIL_NO ";
                                    
                                    // ใช้ OUTER APPLY ดึงข้อมูล COILWORK1 เฉพาะรายการล่าสุด 1 Record ป้องกัน Row ซ้ำ[cite: 1]
                                    $from_sql = " FROM JOBORDER1 j 
                                                  OUTER APPLY (
                                                      SELECT TOP 1 COIL_NO 
                                                      FROM COILWORK1 
                                                      WHERE JOB_ORDER = j.JOB_ORDER AND LINE_PROCESS = :line_process
                                                      ORDER BY WORK_OPERATEDATE DESC
                                                  ) cw ";
                                    
                                    $params[':line_process'] = $line_func;

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
                                        $safe_job_no  = htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_so_no   = htmlspecialchars($row['SALEORDER_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_so_item = htmlspecialchars($row['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $work_coil_no = $row['WORK_COIL_NO'] ?? '';
                                        
                                        $so_display = $safe_so_no . ($safe_so_item !== '' ? '/' . $safe_so_item : '');

                                        echo "<tr onclick=\"show_job_detail_ctl(event, '" . addslashes($row['JOB_ORDER']) . "')\">";
                                        
                                        // 1. ปุ่ม Job Process สีเขียว
                                        echo "<td align='center'>
                                                <button type='button' class='btn-green-search' onclick=\"show_job_detail_ctl(event, '" . addslashes($row['JOB_ORDER']) . "')\">
                                                    🔍 ".$safe_job_no."
                                                </button>
                                              </td>";
                                        
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['JOBORDER_DATE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 2. ปุ่ม Sale Order
                                        echo "<td align='center'>";
                                        if(!empty($safe_so_no)) {
                                            echo "<button type='button' class='btn-green-search' onclick=\"show_so_detail_ctl(event, '" . addslashes($safe_so_no) . "', '" . addslashes($safe_so_item) . "')\">
                                                    🔍 ".$so_display."
                                                  </button>";
                                        } else {
                                            echo "-";
                                        }
                                        echo "</td>";

                                        echo "<td>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars(fmt2($row['THICKNESS']) ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['WIDTH'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['JOB_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['JOB_RELEASEWEIGHT'] ?? '', ENT_QUOTES, 'UTF-8')." / ".htmlspecialchars($row['JOB_RELEASEPIECE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // 3. ปุ่ม Action MAP COIL / WORK (ตามการตรวจสอบ COILWORK1)
                                        if (!empty($work_coil_no)) {
                                            echo "<td align='center'>
                                                    <button type='button' class='btn-action-work' onclick=\"go_to_work(event, '" . addslashes($row['JOB_ORDER']) . "', '" . addslashes($work_coil_no) . "')\">
                                                        ⚙️ WORK
                                                    </button>
                                                  </td>";
                                        } else {
                                            echo "<td align='center'>
                                                    <button type='button' class='btn-action-map' onclick=\"map_coil_no(event, '" . addslashes($row['JOB_ORDER']) . "')\">
                                                        🔗 MAP COIL
                                                    </button>
                                                  </td>";
                                        }

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
    window.location.assign('cut_to_length_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(data_cno)); 
}    

function show_job_detail_ctl(e, do_no) {
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_job_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&jno=' + encodeURIComponent(do_no));
}

function show_so_detail_ctl(e, so_no, so_item) {
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_saleorder_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&sono=' + encodeURIComponent(so_no) + '&soitem=' + encodeURIComponent(so_item));
}

function map_coil_no(e, do_no){
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_map_coil_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(do_no));         
} 

function go_to_work(e, do_no, cno){
    if (e && e.stopPropagation) e.stopPropagation();
    var data_fun = document.getElementById("func").value;
    window.location.assign('cut_to_length_entry_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(do_no)+'&cno='+encodeURIComponent(cno));         
}
</script>
</html>