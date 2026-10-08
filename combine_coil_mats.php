<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
//$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า cno และกำหนดตัวแปรให้ตรงกัน
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');

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

        /* Modern Table Styles & Font Scaling Up */
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
            font-size: 14px;
            letter-spacing: 0.5px;
            padding: 16px 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }
        
        /* เพิ่ม Effect ให้คลิกได้ทั้งแถว */
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important; /* เปลี่ยนเป็นสีฟ้าอ่อนเมื่อ Hover */
        }

        /* ปุ่ม Badge แสดง COIL NO */
        .btn-badge-clickable {
            display: inline-block;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 20px;
            text-align: center;
            width: 100%;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            text-decoration: none !important;
            transition: all 0.2s ease;
            box-shadow: none;
        }

        /* ปุ่ม Action "ลงข้อมูล" สีน้ำเงินพรีเมียม */
        .btn-action-input {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            width: 100%;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);
            transition: all 0.2s;
        }
        .btn-action-input:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.25);
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="combine_coil_mats.php?func=<?php echo $folder_func ?>">Combine Coil Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">Coil Number</label>
                    <input class="form-control-minimal" name="coil_no" id="coil_no" type="text" placeholder="Enter the code you want to search for..." value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Coil Production Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">COIL NO</th>
                                        <th>BATCH NO</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>MATERIAL_IN</th>
                                        <th>ALLOY</th>
                                        <th>COIL WEIGHT</th>
                                        <th>Status</th>
                                        <th style="width: 130px; text-align: center;">Input</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $y=date('Y');

                                    $date_st = $y.'-01-01';
                                    $date_st = date('Y-m-01',strtotime($date_st))." 00:00:00";

                                    $date_end = $y.'-12-01';
                                    $date_end = date('Y-m-t',strtotime($date_end))." 23:59:59";

                                    $params = [];

                                    if($d == date('Y-m-d')){

                                        if($cno != ''){
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.COIL_STARTTIME, A.COIL_ENDTIME, A.MATERIAL_IN, A.ALLOY, A.BATCH_NO, A.COIL_ACTUALWEIGHT, A.COIL_STATUS FROM COILPROD1 AS A WHERE A.COIL_NO LIKE :cno AND A.COIL_STATUS='AC' AND A.COIL_NEXTPROCESS='WS' AND A.JOB_ORDER='' ORDER BY A.COIL_ENDTIME DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.COIL_STARTTIME, A.COIL_ENDTIME, A.MATERIAL_IN, A.ALLOY, A.BATCH_NO, A.COIL_ACTUALWEIGHT, A.COIL_STATUS FROM COILPROD1 AS A WHERE A.COIL_STATUS='AC' AND A.COIL_NEXTPROCESS='WS' AND A.JOB_ORDER='' AND A.COIL_ENDTIME BETWEEN :date_st AND :date_end ORDER BY A.COIL_ENDTIME DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        } 

                                    } else {

                                        if($cno != ''){
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.COIL_STARTTIME, A.COIL_ENDTIME, A.MATERIAL_IN, A.ALLOY, A.BATCH_NO, A.COIL_ACTUALWEIGHT, A.COIL_STATUS FROM COILPROD1 AS A WHERE A.COIL_NO LIKE :cno AND A.COIL_STATUS='AC' AND A.COIL_NEXTPROCESS='WS' AND A.JOB_ORDER='' ORDER BY A.COIL_ENDTIME DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.COIL_STARTTIME, A.COIL_ENDTIME, A.MATERIAL_IN, A.ALLOY, A.BATCH_NO, A.COIL_ACTUALWEIGHT, A.COIL_STATUS FROM COILPROD1 AS A WHERE A.COIL_ENDTIME BETWEEN :date_f AND :date_e AND A.COIL_STATUS='AC' AND A.COIL_NEXTPROCESS='WS' AND A.JOB_ORDER='' ORDER BY A.COIL_ENDTIME DESC";
                                            $params[':date_f'] = $d." 00:00:00";
                                            $params[':date_e'] = $d." 23:59:59";    
                                        }

                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_coil_no = htmlspecialchars($row['COIL_NO'], ENT_QUOTES, 'UTF-8');
                                        
                                        // ใส่ onclick ที่ <tr> เพื่อให้คลิกได้ทั้งบรรทัด
                                        echo "<tr onclick=\"show_detail_coil('" . addslashes($row['COIL_NO']) . "')\">";
                                        echo "<td><span class='btn-badge-clickable'>".$safe_coil_no."</span></td>";
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['BATCH_NO'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['COIL_STARTTIME'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['COIL_ENDTIME'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // บังคับแสดงผล ทศนิยม 2 ตำแหน่ง
                                        $act_weight = isset($row['COIL_ACTUALWEIGHT']) ? number_format((float)$row['COIL_ACTUALWEIGHT'], 2) : '0.00';
                                        echo "<td style='font-weight:600; color:#0f172a; font-size:15px;'>".$act_weight."</td>";
                                        
                                        echo "<td>".htmlspecialchars($row['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        
                                        echo "<td align='center'><button class='btn-action-input' onclick=\"input_coil_no(event, '" . addslashes($row['COIL_NO']) . "')\">Combine Coil</button></td>"; 
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
    order:[[3, 'desc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no").value; 
    window.location.assign('combine_coil_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}    

// รับค่า event เพิ่มเติมเพื่อสั่งหยุดการส่งผ่าน Event ไปยัง <tr>
function input_coil_no(e, do_no){
    if (e && e.stopPropagation) {
        e.stopPropagation(); // หยุดไม่ให้ event ซึมไปถึง tr
    }
    var data_fun = document.getElementById("func").value;
    window.location.assign('combine_coil_update_mats.php?func='+encodeURIComponent(data_fun)+'&coil_no='+encodeURIComponent(do_no));         
} 

function show_detail_coil(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('combine_coil_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&COIL=' + encodeURIComponent(do_no));
}
</script>
</html>