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

$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$position = isset($_SESSION['POSITION']) ? htmlspecialchars($_SESSION['POSITION'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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

        /* ช่องป้อน Batch No ค้นหาแบบไร้ขอบสี่เหลี่ยม */
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
        .btn-create-batch {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }
        .btn-create-batch:hover {
            background-color: #1d4ed8;
            color: #ffffff;
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
            padding: 16px 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 14px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }
        #user_table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* Badge ทรงมนผิวโปร่งแสง */
        .badge-status {
            display: inline-block;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 20px;
            text-align: center;
            width: 100%;
            border: 1px solid transparent;
            box-sizing: border-box;
        }
        .status-used {
            background-color: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }
        .status-processing {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        /* ปุ่ม BATCH_NO */
        .btn-badge-clickable {
            display: inline-block;
            padding: 6px 12px;
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
            cursor: pointer;
        }
        .btn-badge-clickable:hover {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        /* ✨ [ปรับแต่งใหม่] Styling ปุ่ม Delete ทั่วไป และ ปุ่ม Disabled (สีเทา กดไม่ได้) */
        .btn-action-del {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s;
            border: none;
            background-color: #ef4444;
            color: #ffffff;
            cursor: pointer;
        }
        .btn-action-del:hover:not(:disabled) {
            background-color: #dc2626;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        }
        
        /* สไตล์ปุ่มกดไม่ได้ (Disabled - สีเทาเรียบหรู) */
        .btn-action-del:disabled,
        .btn-action-del[disabled] {
            background-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            border: 1px solid #cbd5e1 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            opacity: 0.8;
            transform: none !important;
        }

        /* Modal Details */
        .modal-content {
            border-radius: 14px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
        }
        .modal-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 20px 24px;
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-body {
            padding: 20px 24px !important;
        }
        
        #tbl_batch {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
        }
        #tbl_batch thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 14px 10px !important;
            border-bottom: 2px solid #e2e8f0 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        #tbl_batch tbody td {
            padding: 14px 10px !important;
            font-size: 14px !important;
            color: #334155 !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle;
        }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 15px 24px;
        }
    </style>
</head>
<body>

    <div class="modal fade" id="dataIn_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document" style="width: 85%; max-width: 1200px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">🔍 Batch Details Material Composition</h4>
                </div>
                <div class="modal-body">                
                    <div class="table-responsive table-full-width">
                        <table id="tbl_batch" class="table table-hover">
                            <thead>
                                <tr>   
                                    <th style="text-align: center;">BATCH_NO</th>
                                    <th>AL_REMAIN</th>
                                    <th>AL_INGOT</th>
                                    <th>COIL_REMELT</th>
                                    <th>CAST_SCRAP</th>
                                    <th>OTHER_SCRAP</th>
                                    <th>SCRAP_WIRE</th>          
                                    <th>RECYCLE_SCRAP</th> 
                                    <th>MASTER_ALLOY</th>        
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:8px; font-size:14px; padding: 8px 20px; font-weight:600;">Close</button>
                </div>
            </div>
        </div>
    </div>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="furnance_mats.php?func=<?php echo $folder_func ?>">Furanace Charging Material</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="batch_no">Batch Number</label>
                    <input class="form-control-minimal" name="batch_no" id="batch_no" type="text" placeholder="Enter the code you want to search for..." value="<?php echo htmlspecialchars($bno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_kanban" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Charging Records</h4>
                            <button class='btn btn-create-batch' id='btn_create_kanban' onclick='open_batch_no()'>
                                Charging Material (CREATE BATCH)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">BATCH_NO</th>
                                        <th>MATERIAL_IN</th>
                                        <th>ALLOY</th>
                                        <th>BATCH_DATE</th>
                                        <th>TOTAL_CHARGE</th>
                                        <th>TOTAL_DROSS</th>
                                        <th>BALANCE_BATCH</th>
                                        <th style="width: 120px; text-align: center;">Status</th>
                                        <th style="width: 120px; text-align: center;">Edit</th>
                                        <th style="width: 100px; text-align: center;">Delete</th>
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

                                        if($bno != ''){
                                            $sql = "SELECT TOP(50) A.BATCH_NO, A.BATCH_DATE, A.MATERIAL_IN, A.ALLOY, A.LINE_PROCESS, A.AL_INGOT, A.COIL_REMELT, A.CAST_SCRAP, A.OTHER_SCRAP, A.SCRAP_WIRE, A.RECYCLE_SCRAP, A.MASTER_ALLOY, A.AL_REMAIN, A.TOTAL_CHARGE, A.TOTAL_TRANSFER, A.TOTAL_CASTSCRAP, A.TOTAL_DROSS, A.TOTAL_PRODUCE, A.BALANCE_BATCH, 
                                            B.CAR_CHARGE, B.TRANSFER_DEGREE, B.CTRN_REMARK FROM FRNCPRCS1 AS A INNER JOIN CHRGTRNS1 AS B ON A.BATCH_NO = B.BATCH_NO WHERE A.BATCH_NO LIKE :bno ORDER BY A.BATCH_DATE DESC";
                                            $params[':bno'] = '%'.$bno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.BATCH_NO, A.BATCH_DATE, A.MATERIAL_IN, A.ALLOY, A.LINE_PROCESS, A.AL_INGOT, A.COIL_REMELT, A.CAST_SCRAP, A.OTHER_SCRAP, A.SCRAP_WIRE, A.RECYCLE_SCRAP, A.MASTER_ALLOY, A.AL_REMAIN, A.TOTAL_CHARGE, A.TOTAL_TRANSFER, A.TOTAL_CASTSCRAP, A.TOTAL_DROSS, A.TOTAL_PRODUCE, A.BALANCE_BATCH, 
                                            B.CAR_CHARGE, B.TRANSFER_DEGREE, B.CTRN_REMARK FROM FRNCPRCS1 AS A INNER JOIN CHRGTRNS1 AS B ON A.BATCH_NO = B.BATCH_NO WHERE A.BATCH_DATE BETWEEN :date_st AND :date_end ORDER BY A.BATCH_DATE DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        }                                    

                                    } else {

                                        if($bno != ''){
                                            $sql = "SELECT TOP(0) A.BATCH_NO, A.BATCH_DATE, A.MATERIAL_IN, A.ALLOY, A.LINE_PROCESS, A.AL_INGOT, A.COIL_REMELT, A.CAST_SCRAP, A.OTHER_SCRAP, A.SCRAP_WIRE, A.RECYCLE_SCRAP, A.MASTER_ALLOY, A.AL_REMAIN, A.TOTAL_CHARGE, A.TOTAL_TRANSFER, A.TOTAL_CASTSCRAP, A.TOTAL_DROSS, A.TOTAL_PRODUCE, A.BALANCE_BATCH, 
                                            B.CAR_CHARGE, B.TRANSFER_DEGREE, B.CTRN_REMARK FROM FRNCPRCS1 AS A INNER JOIN CHRGTRNS1 AS B ON A.BATCH_NO = B.BATCH_NO WHERE A.BATCH_NO LIKE :bno ORDER BY A.BATCH_DATE DESC";
                                            $params[':bno'] = '%'.$bno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.BATCH_NO, A.BATCH_DATE, A.MATERIAL_IN, A.ALLOY, A.LINE_PROCESS, A.AL_INGOT, A.COIL_REMELT, A.CAST_SCRAP, A.OTHER_SCRAP, A.SCRAP_WIRE, A.RECYCLE_SCRAP, A.MASTER_ALLOY, A.AL_REMAIN, A.TOTAL_CHARGE, A.TOTAL_TRANSFER, A.TOTAL_CASTSCRAP, A.TOTAL_DROSS, A.TOTAL_PRODUCE, A.BALANCE_BATCH, 
                                            B.CAR_CHARGE, B.TRANSFER_DEGREE, B.CTRN_REMARK FROM FRNCPRCS1 AS A INNER JOIN CHRGTRNS1 AS B ON A.BATCH_NO = B.BATCH_NO WHERE A.BATCH_DATE BETWEEN :date_f AND :date_e ORDER BY A.BATCH_DATE DESC";
                                            $params[':date_f'] = $d." 00:00:00";
                                            $params[':date_e'] = $d." 23:59:59";
                                        }
                                        
                                    }
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute($params);

                                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $safe_batch_no = htmlspecialchars($row['BATCH_NO'], ENT_QUOTES, 'UTF-8');
                                                
                                                // ส่ง $conn เข้าไปในฟังก์ชันที่ปรับปรุงใหม่
                                                $chk_coil = RT_COIL_PRO($row['BATCH_NO']); 

                                                echo "<tr>";
                                                echo "<td align='center'><button type='button' class='btn-badge-clickable' onclick=\"show_detail_batch('" . addslashes($row['BATCH_NO']) . "')\">".$safe_batch_no."</button></td>";
                                                echo "<td>".htmlspecialchars($row['MATERIAL_IN'], ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td>".htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['BATCH_DATE'], ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td style='font-weight:500;'>".number_format($row['TOTAL_CHARGE'], 2)."</td>";
                                                echo "<td style='font-weight:500;'>".number_format($row['TOTAL_DROSS'], 2)."</td>";
                                                echo "<td style='font-weight:600; color:#0f172a;'>".number_format($row['BALANCE_BATCH'], 2)."</td>";
   
                                                // หากถูกใช้งานแล้ว เปลี่ยนปุ่มลบเป็นปุ่ม Edit
                                                if($chk_coil){ 
                                                    //echo "<td align='center'><button type='button' class='btn btn-success btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"use_document('" . addslashes($row['BATCH_NO']) . "')\">Used</button></td>"; 
                                                   if(strtoupper($position)=='OPERATOR' || strtoupper($position)=='STAFF'){
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be used because don't have permission.'>Used</button></td>";
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be edit because don't have permission.'>Edit</button></td>"; 
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be deleted because don't have permission.'>Delete</button></td>";   
                                                    }else{
                                                        echo "<td align='center'><button type='button' class='btn btn-success btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"use_document('" . addslashes($row['BATCH_NO']) . "')\">Used</button></td>";                                                         
                                                        echo "<td align='center'><button type='button' class='btn btn-warning btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"edit_document('" . addslashes($row['BATCH_NO']) . "')\">Edit</button></td>"; 
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be deleted because it is already in use.'>Delete</button></td>";        
                                                    }                                                          
                                                } else {
                                                    //echo "<td align='center'><span class='badge-status status-processing'>In progress.</span></td>";
                                                   if(strtoupper($position)=='OPERATOR' || strtoupper($position)=='STAFF'){
                                                        echo "<td align='center'><span class='badge-status status-processing'>In progress.</span></td>";
                                                        echo "<td align='center'><button type='button' class='btn btn-warning btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"edit_document('" . addslashes($row['BATCH_NO']) . "')\">Edit</button></td>"; 
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be deleted because don't have permission.'>Delete</button></td>";   
                                                    }else{
                                                        echo "<td align='center'><span class='badge-status status-processing'>In progress.</span></td>";
                                                        echo "<td align='center'><button type='button' class='btn btn-warning btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"edit_document('" . addslashes($row['BATCH_NO']) . "')\">Edit</button></td>";  
                                                        echo "<td align='center'><button type='button' class='btn btn-action-del' disabled title='Cannot be deleted because don't have permission.'>Delete</button></td>";     
                                                    }
                                                }

                                                //echo "<td align='center'><button type='button' class='btn btn-warning btn-sm' style='width:100%; font-weight:600; border-radius:6px;' onclick=\"edit_document('" . addslashes($row['BATCH_NO']) . "')\">Edit</button></td>"; 
                                                //echo "<td align='center'><button type='button' class='btn btn-action-del' onclick=\"delete_document('" . addslashes($row['BATCH_NO']) . "')\">Delete</button></td>";    
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

function use_document(ref) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_production_mats.php?func=' + encodeURIComponent(data_fun) + '&bno=' + encodeURIComponent(ref));
}

function edit_document(ref) {
    var data_fun = document.getElementById("func").value;
    // เปลี่ยนไปยังหน้าฟอร์มแก้ไขตามชื่อไฟล์ของคุณ (เช่น furnance_charging_mats_edit.php)
    window.location.assign('furnance_charging_mats_edit.php?func=' + encodeURIComponent(data_fun) + '&bno=' + encodeURIComponent(ref));
}

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_bno = document.getElementById("batch_no").value; 
    window.location.assign('furnance_mats.php?func='+encodeURIComponent(data_fun)+'&bno='+encodeURIComponent(data_bno)); 
}    

function delete_document(ref){
    var c = confirm('Do you want to delete the data Batch No: ' + ref + ' Yes or No ?');
    
    if(c){
        var data_fun = document.getElementById("func").value;
        
        $.ajax({
            url: "model/delete_batch_no_mats.php",
            type: "POST",
            data: {
                data_tag: ref
            },
            dataType: "json",
            success: function(response) {
                console.log("Response:", response);
                if(response.message){
                    window.location.assign('furnance_mats.php?func=' + encodeURIComponent(data_fun));
                } else {
                    var errorMsg = response.error || "The data cannot be deleted.";
                    alert("An error occurred: " + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                alert("An error occurred while deleting the data: " + error);
            }
        });
    }
}

function open_batch_no(){
    var data_fun = document.getElementById("func").value;
    window.location.assign('furnance_charging_mats.php?func='+encodeURIComponent(data_fun));         
} 

function show_detail_batch(do_no){     
    var table;
    table = $('#tbl_batch').DataTable({
        "pageLength": 100,
        "destroy": true,
        "searching": false,
        "processing": true,
        "serverSide": true,
        "ajax": "model/data_bt_list_order_mats.php?BATCH="+encodeURIComponent(do_no),
        "columnDefs": [
            { 
              "searchable": true, "orderable": true, "targets": 0, "className": "text-center",
              "render": function(data) {
                  return '<span class="badge" style="background-color:#eff6ff; color:#1d4ed8; font-weight:700; padding:6px 12px; border:1px solid #bfdbfe; border-radius:12px;">' + data + '</span>';
              }
            },
            { "searchable": true, "orderable": true, "targets": 1 },
            { "searchable": true, "orderable": true, "targets": 2 },
            { "searchable": true, "orderable": true, "targets": 3 },
            { "searchable": true, "orderable": true, "targets": 4 },
            { "searchable": true, "orderable": true, "targets": 5 },
            { "searchable": true, "orderable": true, "targets": 6 },
            { "searchable": true, "orderable": true, "targets": 7 },
            { "searchable": true, "orderable": true, "targets": 8 },
        ]
    });
    $("#dataIn_modal").modal('show');
} 
</script>
</html>