<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$alloy_no = !isset($_GET['alloy_no']) ? '' : htmlspecialchars(trim($_GET['alloy_no']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
        .filter-item { display: flex; flex-direction: column; gap: 8px; }
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
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }
        .btn-create-alloy {
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
        .btn-create-alloy:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        .table-responsive { border: none !important; margin-top: 15px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
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
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }
        #user_table tbody tr:hover td { background-color: #f8fafc; }

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
        }
        .btn-badge-clickable:hover {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        /* เปลี่ยนสไตล์ปุ่มแก้ไขเป็นสีเขียว */
        .btn-action-edit {
            background-color: #f0fdf4;
            color: #15803d;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #bbf7d0;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-action-edit:hover {
            background-color: #dcfce7;
            color: #166534;
            border-color: #86efac;
            transform: translateY(-1px);
        }

        .btn-action-del {
            background-color: #ffeded;
            color: #b91c1c;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #fca5a5;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
            transform: translateY(-1px);
        }

        .modal-content { border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px; }
        .modal-title { font-weight: 700; color: #1e293b; font-size: 18px; }
        .modal-body table thead th { background-color: #f8fafc; color: #64748b; font-weight: 700; font-size: 13px; }
        .modal-body table tbody td { vertical-align: middle !important; font-size: 14px; }
        .modal-footer { border-top: 1px solid #f1f5f9; }
        
        .modal-body input:readonly {
            background-color: #f1f5f9;
            color: #64748b;
            border-color: #e2e8f0;
        }

        /* เพิ่ม Class สีเขียวสำหรับช่องที่มีค่าใน Modal */
        .bg-has-value {
            background-color: #e8f5e9 !important;
            border-color: #a5d6a7 !important;
            color: #1b5e20 !important;
            font-weight: bold !important;
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
        .btn-tab-item:hover { background-color: #f1f5f9; color: #334155; }
        .btn-tab-item.active { background-color: #2563eb; color: #ffffff; font-weight: 600; }        
    </style>
</head>
<body>

<div class="modal fade" id="data_spec_std" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-center" id="exampleModalLabel">🧪 Technical Practice Batch Annealing</h4>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">

                <h4 class="modal-title" id="exampleModalLabel">Control by time setting</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                <input type="text" class="form-control" style="font-weight:700; color:#2563eb; background-color:#eff6ff; text-align:center;" id="alloy" placeholder="ALLOY CODE" readonly/>
                            </th>
                            <th><center>Time(minute)</center></th>
                            <th><center>Temperature(Degree C)</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>Heating Up</strong></td>
                            <td><input type="text" class="form-control text-center spec-field" id="h_tmc" placeholder="Time(minute)" readonly/></td>
                            <td><input type="text" class="form-control text-center spec-field" id="h_tdc" placeholder="Temperature(Degree C)" readonly/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Soaking</strong></td>
                            <td><input type="text" class="form-control text-center spec-field" id="s_tmc" placeholder="Time(minute)" readonly/></td>
                            <td><input type="text" class="form-control text-center spec-field" id="s_tdc" placeholder="Temperature(Degree C)" readonly/></td>
                        </tr>     
                        <tr>
                            <td align="center"><strong>Range Type</strong></td>
                            <td colspan="2"><input type="text" class="form-control spec-field" id="rt" name="rt" placeholder="Range Type" readonly/></td>
                        </tr>                           
                         <tr>
                            <td align="center"><strong>Minimum Weight (KG)</strong></td>
                            <td colspan="2"><input type="text" class="form-control spec-field" id="mw" name="mw" placeholder="Minimum Weight" readonly/></td>
                        </tr>                        
                    </tbody>
                </table>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                &nbsp;
                            </th>
                            <th><center>FROM</center></th>
                            <th><center>TO</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>O2 Purging Time (Minute)</strong></td>
                            <td><input type="text" class="form-control text-center spec-field" id="o2p_f" placeholder="FROM" readonly/></td>
                            <td><input type="text" class="form-control text-center spec-field" id="o2p_t" placeholder="TO" readonly/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>O2 %</strong></td>
                            <td><input type="text" class="form-control text-center spec-field" id="o2_f" placeholder="FROM" readonly/></td>
                            <td><input type="text" class="form-control text-center spec-field" id="o2_t" placeholder="TO" readonly/></td>
                        </tr>    
                        <tr>
                            <td align="center"><strong>Program</strong></td>
                            <td colspan="2"><input type="text" class="form-control spec-field" id="pg" placeholder="Program" readonly/></td>
                        </tr>                                                                                                 
                    </tbody>
                </table>

                <h4 class="modal-title" id="exampleModalLabel">Control by work piece setting</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                <strong>Temperature (Degree C)</strong>
                            </th>
                            <th><center>FROM</center></th>
                            <th><center>TO</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>Work Piece Heating up</strong></td>
                            <td colspan="2"><input type="text" class="form-control spec-field" id="wph" placeholder="Work Piece Heating up" readonly/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Work Piece Soaking</strong></td>
                            <td><input type="text" class="form-control text-center spec-field" id="wps_f" placeholder="Work Piece Soaking" readonly/></td>
                            <td><input type="text" class="form-control text-center spec-field" id="wps_t" placeholder="Work Piece Soaking" readonly/></td>
                        </tr>                                                                                                   
                    </tbody>
                </table>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:6px; font-size:14px;">Close</button>
            </div>
        </div>
    </div>
</div>

<?php $menu = 'gp2';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="group_data2_mats.php?func=<?php echo $folder_func ?>">Master Data Management</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="tab-menu-wrapper">
                    <button onclick="pro_std()" class="btn-tab-item">Product Specification Standard Master Data</button>
                    <button onclick="cust_std()" class="btn-tab-item">Customer Specification Standard Master Data</button>
                    <button onclick="spec_std()" class="btn-tab-item">Specification Mechanical Properties </button>
                    <button onclick="prac_std()" class="btn-tab-item active">Technical Practice Batch Annealing</button>
                    <button onclick="defect_caster_std()" class="btn-tab-item">Defect Caster Master Data </button>
            </div>
        
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="alloy_no">Alloy Code</label>
                    <input class="form-control-minimal" name="alloy_no" id="alloy_no" type="text" placeholder="Type the alloy code to search..." value="<?php echo htmlspecialchars($alloy_no, ENT_QUOTES, 'UTF-8'); ?>" onchange="search_alloy_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <button class="btn btn-search" id="btn_search_kanban" onclick="search_alloy_no()">
                        SEARCH
                    </button>
                </div>
                <div class="filter-item">
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Technical Practice Batch Annealing Records</h4>
                            <button class='btn btn-create-alloy' id='btn_create_kanban' onclick='open_alloy_no()'>
                                New Technical Practice Batch Annealing (CREATE +)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px; text-align: center;">PRODUCT</th>
                                        <th style="text-align: center;">ALLOY</th>
                                        <th style="text-align: center;">TEMPER INITIAL</th>
                                        <th style="text-align: center;">TEMPER TARGET</th>
                                        <th style="text-align: center;">WIDTH FROM</th>
                                        <th style="text-align: center;">WIDTH TO</th>
                                        <th style="width: 90px; text-align: center;">EDIT</th>
                                        <th style="width: 90px; text-align: center;">DELETE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];

                                    if($alloy_no != ''){
                                        $sql = "SELECT TOP(500) PRODUCT_ID,ALLOY,RANGE_FROM,RANGE_TO,TEMPER_INITIAL,TEMPER_TARGET,RANGE_TYPE,MIN_WEIGHT,
                                        O2PURING_FROM,O2PURING_TO,HEATING_TEMPERATURE,HEATING_TIME,SOAKING_TEMPERATURE,SOAKING_TIME,WORKPIECE_HEATING,
                                        WORKPIECE_SOAKINGFROM,WORKPIECE_SOAKINGTO,O2_PERCENTAGEFROM,O2_PERCENTAGETO,PROGRAM
                                        FROM TPMAT1001 WHERE ALLOY LIKE :alloy_no ORDER BY ALLOY ASC";
                                        $params[':alloy_no'] = '%'.$alloy_no.'%';
                                    }else{
                                        $sql = "SELECT TOP(500) PRODUCT_ID,ALLOY,RANGE_FROM,RANGE_TO,TEMPER_INITIAL,TEMPER_TARGET,RANGE_TYPE,MIN_WEIGHT,
                                        O2PURING_FROM,O2PURING_TO,HEATING_TEMPERATURE,HEATING_TIME,SOAKING_TEMPERATURE,SOAKING_TIME,WORKPIECE_HEATING,
                                        WORKPIECE_SOAKINGFROM,WORKPIECE_SOAKINGTO,O2_PERCENTAGEFROM,O2_PERCENTAGETO,PROGRAM
                                        FROM TPMAT1001 ORDER BY ALLOY ASC";
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_alloy_no = htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8');

                                        $refdata = $row['PRODUCT_ID']."*".$row['ALLOY']."*".$row['RANGE_FROM']."*".$row['RANGE_TO']."*".$row['TEMPER_INITIAL']
                                        ."*".$row['TEMPER_TARGET']."*".$row['RANGE_TYPE']."*".$row['MIN_WEIGHT']."*".$row['O2PURING_FROM']."*".$row['O2PURING_TO']."*".$row['HEATING_TEMPERATURE']
                                        ."*".$row['HEATING_TIME']."*".$row['SOAKING_TEMPERATURE']."*".$row['SOAKING_TIME']."*".$row['WORKPIECE_HEATING']."*".$row['WORKPIECE_SOAKINGFROM']."*".$row['WORKPIECE_SOAKINGTO']
                                        ."*".$row['O2_PERCENTAGEFROM']."*".$row['O2_PERCENTAGETO']."*".$row['PROGRAM'];

                                        $refdel = $row['PRODUCT_ID']."*".$row['ALLOY']."*".$row['RANGE_FROM']."*".$row['RANGE_TO']."*".$row['TEMPER_INITIAL']."*".$row['TEMPER_TARGET'];
                                        
                                        // Parameter สำหรับหน้า Edit
                                        $edit_url = "technical_edit_practice_master_mats.php?func=".urlencode($folder_func)
                                                   ."&PRODUCT_ID=".urlencode($row['PRODUCT_ID'])
                                                   ."&ALLOY=".urlencode($row['ALLOY'])
                                                   ."&TEMPER_INITIAL=".urlencode($row['TEMPER_INITIAL'])
                                                   ."&TEMPER_TARGET=".urlencode($row['TEMPER_TARGET'])
                                                   ."&RANGE_FROM=".urlencode($row['RANGE_FROM'])
                                                   ."&RANGE_TO=".urlencode($row['RANGE_TO']);

                                        echo "<tr onclick=\"show_spec_std('".addslashes($refdata)."')\">";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8')."</button></td>";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8')."</button></td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['TEMPER_INITIAL'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['TEMPER_TARGET'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['RANGE_FROM'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['RANGE_TO'], ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        // คอลัมน์ EDIT แยกต่างหาก
                                        echo "<td align='center'><a href='".$edit_url."' class='btn btn-action-edit' style='text-decoration:none;'>Edit</a></td>";
                                        
                                        // คอลัมน์ DELETE
                                        echo "<td align='center'><button class='btn btn-action-del' onclick=\"delete_document('".addslashes($refdel)."')\">Delete</button></td>";                                                                     
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

<input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>" type="hidden"/>
<?php include 'include/content-footer.php';?>
</div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       

    $("#user_table").DataTable({
        scrollY: "500px",
        scrollX: false,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order:[[0, 'asc']],
        buttons: []
    });

    function search_alloy_no(){   
        var data_fun = document.getElementById("func").value;
        var data_alloy_no = document.getElementById("alloy_no").value; 
        window.location.assign('technical_practice_master_mats.php?func='+encodeURIComponent(data_fun)+'&alloy_no='+encodeURIComponent(data_alloy_no)); 
    }    

    function open_alloy_no(){
        var data_fun = document.getElementById("func").value;
        window.location.assign('technical_input_practice_master_mats.php?func='+encodeURIComponent(data_fun));         
    } 

    function show_spec_std(param){
        var d = param.split("*");

        $("#data_spec_std").modal('show');
        
        document.getElementById("alloy").value = d[1] || "";

        document.getElementById("h_tmc").value = d[11] || "";
        document.getElementById("h_tdc").value = d[10] || "";
        
        document.getElementById("s_tmc").value = d[13] || "";
        document.getElementById("s_tdc").value = d[12] || "";

        if(d[6]==='W'){
           document.getElementById("rt").value = "W : Width"; 
        } else if(d[6]==='T'){
            document.getElementById("rt").value = "T : Thickness";
        } else {
            document.getElementById("rt").value = d[6] || "";
        }

        document.getElementById("mw").value = d[7] || "";

        document.getElementById("o2p_f").value = d[8] || "";
        document.getElementById("o2p_t").value = d[9] || "";       
        
        document.getElementById("pg").value = d[19] || "";  

        var o2_from_val = d[17] ? parseFloat(d[17]) : 0;
        document.getElementById("o2_f").value = (!isNaN(o2_from_val) && o2_from_val > 0) ? o2_from_val.toFixed(2) : "";

        var o2_to_val = d[18] ? parseFloat(d[18]) : 0;
        document.getElementById("o2_t").value = (!isNaN(o2_to_val) && o2_to_val > 0) ? o2_to_val.toFixed(2) : "";   

        document.getElementById("wph").value = d[14] || "";
        document.getElementById("wps_f").value = d[15] || "";
        document.getElementById("wps_t").value = d[16] || "";           

        // ตรวจสอบและใส่ไฮไลต์สีเขียวให้อินพุตที่มีค่ามากกว่า 0 หรือมีข้อมูล
        $('.spec-field').each(function() {
            var val = $(this).val().trim();
            if (val !== "" && val !== "0" && val !== "0.0" && val !== "0.00" && !isNaN(val) && parseFloat(val) > 0) {
                $(this).addClass('bg-has-value');
            } else if (val !== "" && isNaN(val)) {
                // กรณีที่เป็นข้อความ String เช่น "W : Width"
                $(this).addClass('bg-has-value');
            } else {
                $(this).removeClass('bg-has-value');
            }
        });
    } 

    function delete_document(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete the data Alloy: ' + d[1] +' Temper Initial: '+ d[4] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_practice_spec_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('technical_practice_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "Failed to delete data";
                        alert("An error occurred: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert("An error occurred while deleting the data.: " + error);
                }
            });
        }
    }

    function pro_std(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('product_spec_std_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    
    
    function cust_std(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('customer_spec_std_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }         

    function spec_std(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('specification_properties_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }  
        
    function prac_std(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('technical_practice_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }   
    
    function defect_caster_std(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('defect_caster_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    
</script>
</html>