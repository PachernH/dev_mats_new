<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
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
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

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
        /* Style สำหรับแถวที่สามารถคลิกได้ (Clickable Row) */
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #user_table tbody tr:hover td {
            background-color: #e0f2fe !important;
        }

        .badge-coil {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        /* Modal Vertical Layout Style */
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
            padding: 24px 32px !important;
        }

        #tbl_batch {
            width: 100% !important;
            display: flex;
            flex-direction: row;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        #tbl_batch thead {
            display: block;
            flex: 0 0 45%;
            background-color: #f8fafc;
            border-right: 2px solid #e2e8f0;
        }
        #tbl_batch tbody {
            display: block;
            flex: 1;
            background-color: #ffffff;
        }
        #tbl_batch tr {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        #tbl_batch thead th {
            display: block;
            width: 100% !important;
            padding: 10px 16px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #475569 !important;
            text-align: left !important;
            border-bottom: 1px solid #e2e8f0 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            height: 42px;
            box-sizing: border-box;
        }
        #tbl_batch tbody td {
            display: block;
            width: 100% !important;
            padding: 10px 16px !important;
            font-size: 14px !important;
            font-weight: 500;
            color: #1e293b !important;
            text-align: left !important;
            border-bottom: 1px solid #f1f5f9 !important;
            height: 42px;
            box-sizing: border-box;
        }
        
        #tbl_batch thead th:last-child, 
        #tbl_batch tbody td:last-child {
            border-bottom: none !important;
        }

        .modal-body .dataTables_length,
        .modal-body .dataTables_filter,
        .modal-body .dataTables_info,
        .modal-body .dataTables_paginate {
            display: none !important;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 15px 24px;
        }
    </style>
</head>
<body>

    <div class="modal fade" id="dataIn_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">📋 Coil Production Specifications Details</h4>
                </div>
                <div class="modal-body">                
                    <table id="tbl_batch" class="table">
                        <thead>
                            <tr>   
                                <th>COIL NO</th>
                                <th>PRODUCT REFERENCE</th>
                                <th>COIL TYPE</th>
                                <th>ALLOY</th>
                                <th>TEMPER</th>
                                <th>GRADE</th>
                                <th>THICKNESS</th>
                                <th>WIDTH</th>
                                <th>SURFACE GRADE</th>
                                <th>METALLURGICAL GRADE</th>
                                <th>COIL ACTUAL WEIGHT</th>
                                <th>COIL BALANCE WEIGHT</th>
                                <th>COIL WORK PROCESS</th>
                                <th>COIL NEXT PROCESS</th>
                                <th>COIL STATUS</th>       
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:8px; font-size:14px; padding: 8px 24px; font-weight:600;">Close</button>
                </div>
            </div>
        </div>
    </div>

<div class="wrapper">
    
<?php $menu = 'coil_wk';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Coil Production Working</a>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Coil Production Working</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">COIL NO</th>
                                        <th>JOBORDER NO</th>
                                        <th>SALEORDER NO</th>
                                        <th>SALEORDER ITEM</th>
                                        <th>MOULDSETUP</th>
                                        <th>LINE PROCESS</th>
                                        <th>WORK OPERATOR</th>
                                        <th>WORK OPERATE DATE</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $date_st = $d." 00:00:00";
                                    $date_end = $d." 23:59:59";
                                    $params = [];

                                    if($d == date('Y-m-d')){
                                        if($cno != ''){
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MOULDSETUP_NO, A.LINE_PROCESS, A.WORK_OPERATOR1, A.WORK_OPERATEDATE FROM COILWORK1 AS A WHERE A.COIL_NO LIKE :cno ORDER BY A.WORK_OPERATEDATE DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MOULDSETUP_NO, A.LINE_PROCESS, A.WORK_OPERATOR1, A.WORK_OPERATEDATE FROM COILWORK1 AS A ORDER BY A.WORK_OPERATEDATE DESC";
                                        }
                                    } else {
                                        if($cno != ''){
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MOULDSETUP_NO, A.LINE_PROCESS, A.WORK_OPERATOR1, A.WORK_OPERATEDATE FROM COILWORK1 AS A WHERE A.WORK_OPERATEDATE BETWEEN :date_st AND :date_end AND A.COIL_NO LIKE :cno ORDER BY A.WORK_OPERATEDATE DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                            $params[':cno'] = '%'.$cno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.COIL_NO, A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MOULDSETUP_NO, A.LINE_PROCESS, A.WORK_OPERATOR1, A.WORK_OPERATEDATE FROM COILWORK1 AS A WHERE A.WORK_OPERATEDATE BETWEEN :date_st AND :date_end ORDER BY A.WORK_OPERATEDATE DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        }
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_coil_no = htmlspecialchars($row['COIL_NO'], ENT_QUOTES, 'UTF-8');
                                        // ปรับปรุง <tr> ให้คลิกได้ทั้งแถวด้วย data-coil attribute
                                        echo "<tr class='clickable-row' data-coil='".htmlspecialchars($row['COIL_NO'], ENT_QUOTES, 'UTF-8')."'>";
                                        echo "<td style='text-align:center;'><span class='badge-coil'>".$safe_coil_no."</span></td>";
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['JOB_ORDER'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['SALEORDER_NO'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['SALEORDER_ITEM'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['MOULDSETUP_NO'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['LINE_PROCESS'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['WORK_OPERATOR1'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['WORK_OPERATEDATE'], ENT_QUOTES, 'UTF-8')."</td>";
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

$(document).ready(function() {
    var mainTable = $("#user_table").DataTable({
        scrollY: "500px",
        scrollX: false,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order: [[7, 'desc']], // เรียงตาม WORK_OPERATEDATE
        buttons: []
    });

    // Event Listener สำหรับการคลิกบรรทัด (Row Click)
    $('#user_table tbody').on('click', 'tr.clickable-row', function () {
        var coilNo = $(this).attr('data-coil');
        if (coilNo) {
            show_detail_coil(coilNo);
        }
    });
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no").value; 
    window.location.assign('coil_working_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}    

function show_detail_coil(do_no){     
    $('#tbl_batch').DataTable({
        "pageLength": 1,
        "destroy": true,
        "searching": false,
        "processing": true,
        "serverSide": true,
        "ajax": "model/data_coil_list_order_mats.php?COIL=" + encodeURIComponent(do_no),
        "columnDefs": [
            {
                "targets": 0,
                "render": function(data) {
                    return '<span class="badge" style="background-color:#eff6ff; color:#1d4ed8; font-weight:700; padding:6px 12px; border:1px solid #bfdbfe; border-radius:12px; display:inline-block; font-size:13px;">' + data + '</span>';
                }
            },
            { "targets": "_all", "defaultContent": "-" }
        ]
    });
    $("#dataIn_modal").modal('show');
} 
</script>
</html>