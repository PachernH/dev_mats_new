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
        .btn-create-alloy:hover {
            background-color: #1d4ed8;
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
        #user_table tbody tr:hover td {
            background-color: #f8fafc;
        }

        /* ปุ่มคลิก ALLOY CODE ทรงมนผิวโปร่งแสงสีฟ้า */
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
        .btn-badge-clickable:hover {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        /* ปุ่มลบข้อมูลสีส้มอิฐโปร่งแสง ปรับให้นุ่มนวลสากลขึ้น */
        .btn-action-del {
            background-color: #ffeded;
            color: #b91c1c;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #fca5a5;
            width: 70%;
            transition: all 0.2s;
        }
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
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

        /* Modal Design Customization */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 20px;
        }
        .modal-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 18px;
        }
        .modal-body table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 13px;
        }
        .modal-body table tbody td {
            vertical-align: middle !important;
            font-size: 14px;
        }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
        }

        /* 🎨 คลาสสีสำหรับกล่องใน Modal ที่มีข้อมูลระบุไว้ */
        .modal-has-value {
            background-color: #e8f5e9 !important; /* พื้นหลังเขียวอ่อน */
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20 !important;
        }

        /* สไตล์สำหรับฟิลด์ที่โดนล็อกใน Modal ให้ดูเรียบเนียนขึ้น */
        .modal-body input:readonly {
            background-color: #f1f5f9 !important;
            color: #64748b;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }
        /* เมนูแท็บด้านบนสำหรับหน้าเดี่ยว */
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

<div class="modal fade" id="data_spec_std" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-center" id="exampleModalLabel">🧪 Customer Specification Standard</h4>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                <input type="text" class="form-control" style="font-weight:700; color:#2563eb; background-color:#eff6ff; text-align:center;" id="alloy" placeholder="ALLOY CODE" readonly/>
                            </th>
                            <th><center>Plus</center></th>
                            <th><center>Minus</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>Thickness Tolerane</strong></td>
                            <td><input type="text" class="form-control text-center" id="th_p" placeholder="Thickness Plus"/></td>
                            <td><input type="text" class="form-control text-center" id="th_m" placeholder="Thickness Minus"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Width Tolerane</strong></td>
                            <td><input type="text" class="form-control text-center" id="wi_p" placeholder="Width Plus"/></td>
                            <td><input type="text" class="form-control text-center" id="wi_m" placeholder="Width Minus"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Length Tolerane</strong></td>
                            <td><input type="text" class="form-control text-center" id="le_p" placeholder="Length Plus"/></td>
                            <td><input type="text" class="form-control text-center" id="le_m" placeholder="Length Minus"/></td>
                        </tr>     
                        <tr>
                            <td align="center"><strong>Flatness</strong></td>
                            <td colspan="2"><input type="text" class="form-control text-center" id="fla_p" placeholder="Flatness"/></td>
                        </tr> 
                        <tr>
                            <td align="center"><strong>Earing Type</strong></td>
                            <td colspan="2"><input type="text" class="form-control text-center" id="eat" placeholder="Earing Type"/></td>
                        </tr>                         
                        <tr>
                            <td align="center"><strong>Earing Percentage</strong></td>
                            <td colspan="2"><input type="text" class="form-control text-center" id="etp_p" placeholder="Earing Percentage"/></td>
                        </tr>        
                        <tr>
                            <td align="center"><strong>Fixed Process</strong></td>
                            <td colspan="2"><input type="text" class="form-control text-center" id="fix" placeholder="Fixed Process"/></td>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Customer Specification Standard Master Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="tab-menu-wrapper">
                    <button onclick="pro_std()" class="btn-tab-item">Product Specification Standard</button>
                    <button onclick="cust_std()" class="btn-tab-item active">Customer Specification Standard</button>
                    <button onclick="spec_std()" class="btn-tab-item">Specification Mechanical Properties</button>
                    <button onclick="prac_std()" class="btn-tab-item">Technical Practice Batch Annealing</button>
                    <button onclick="defect_caster_std()" class="btn-tab-item">Defect Caster Coil</button>
            </div>

        
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="alloy_no">Alloy Code</label>
                    <input class="form-control-minimal" name="alloy_no" id="alloy_no" type="text" placeholder="Enter the alloid code to search..." value="<?php echo htmlspecialchars($alloy_no, ENT_QUOTES, 'UTF-8'); ?>" onchange="search_alloy_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Customer Specification Standard Records</h4>
                            <button class='btn btn-create-alloy' id='btn_create_kanban' onclick='open_alloy_no()'>
                                New Customer Specification Standard (CREATE +)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">CUSTOMER</th>
                                        <th style="text-align: center;">PORDUCTION</th>
                                        <th style="text-align: center;">ALLOY</th>
                                        <th style="text-align: center;">TEMPER</th>
                                        <th style="text-align: center;">THICKNESS</th>
                                        <th style="text-align: center;">WIDTH</th>
                                        <th style="text-align: center;">LENGTH</th>
                                        <th style="text-align: center;">EDIT</th>
                                        <th style="width: 150px; text-align: center;">Process</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];

                                    if($alloy_no != ''){
                                        $sql = "SELECT TOP(500) CSTMSPPL_ID,PRODUCT_ID,ALLOY,TEMPER,THICKNESS,WIDTH,LENGTH,THICKNESS_PTOLERANCE,THICKNESS_MTOLERANCE,
                                        WIDTH_PTOLERANCE,WIDTH_MTOLERANCE,LENGTH_PTOLERANCE,LENGTH_MTOLERANCE,FLATNESS,EARING_TYPE,EARING_PERCENT,FIXED_PROCESS 
                                        FROM STNDMSTR6 WHERE ALLOY LIKE :alloy_no ORDER BY ALLOY ASC";
                                        $params[':alloy_no'] = '%'.$alloy_no.'%';
                                    }else{
                                        $sql = "SELECT TOP(500) CSTMSPPL_ID,PRODUCT_ID,ALLOY,TEMPER,THICKNESS,WIDTH,LENGTH,THICKNESS_PTOLERANCE,THICKNESS_MTOLERANCE,
                                        WIDTH_PTOLERANCE,WIDTH_MTOLERANCE,LENGTH_PTOLERANCE,LENGTH_MTOLERANCE,FLATNESS,EARING_TYPE,EARING_PERCENT,FIXED_PROCESS 
                                        FROM STNDMSTR6 ORDER BY ALLOY ASC";
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_alloy_no = htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8');

                                        $refdata = $row['ALLOY']."*".$row['THICKNESS_PTOLERANCE']."*".$row['THICKNESS_MTOLERANCE']."*".$row['WIDTH_PTOLERANCE']
                                        ."*".$row['WIDTH_MTOLERANCE']."*".$row['LENGTH_PTOLERANCE']."*".$row['LENGTH_MTOLERANCE']."*".$row['FLATNESS']
                                        ."*".$row['EARING_TYPE']."*".$row['EARING_PERCENT']."*".$row['FIXED_PROCESS'];

                                        $refdel = $row['CSTMSPPL_ID']."*".$row['PRODUCT_ID']."*".$row['ALLOY']."*".$row['TEMPER']."*".$row['THICKNESS']."*".$row['WIDTH']."*".$row['LENGTH'];
                                        $refedit = $row['CSTMSPPL_ID']."*".$row['PRODUCT_ID']."*".$row['ALLOY']."*".$row['TEMPER']."*".$row['THICKNESS']."*".$row['WIDTH']."*".$row['LENGTH'];
                                        
                                        echo "<tr onclick=\"show_spec_std('".addslashes($refdata)."')\">";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".htmlspecialchars($row['CSTMSPPL_ID'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".$safe_alloy_no."</button></td>";
                                        echo "<td style='text-align: center;' color:#1e293b; font-size:15px;'>".htmlspecialchars($row['TEMPER'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['THICKNESS'] ?? 0), 1)."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['WIDTH'] ?? 0), 0)."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['LENGTH'] ?? 0), 0)."</td>";
                                        echo "<td align='center'><button class='btn btn-action-edit' onclick=\"edit_document('".addslashes($refedit)."')\">Edit</button></td>";
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
        window.location.assign('customer_spec_std_master_mats.php?func='+encodeURIComponent(data_fun)+'&alloy_no='+encodeURIComponent(data_alloy_no)); 
    }    

    function open_alloy_no(){
        var data_fun = document.getElementById("func").value;
        window.location.assign('customer_spec_Input_master_mats.php?func='+encodeURIComponent(data_fun));         
    } 

    // ฟังก์ชันจัดการแปลงค่าข้อมูลและบังคับทศนิยมคงที่ 3 ตำแหน่ง (.toFixed(3))
    function formatValue(val) {
        var num = parseFloat(val);
        return isNaN(num) ? 0 : num;
    }

// ฟังก์ชันสำหรับอัปเดตค่าลง Input Element (ประเภท Plus / Minus) และสลับสีพื้นหลัง
    function updateRowVisibility(elementId, maxVal, minVal) {
        var maxObj = document.getElementById(elementId + "_p");
        var minObj = document.getElementById(elementId + "_m");

        if (maxObj) {
            maxObj.value = maxVal.toFixed(3);
            if (maxVal > 0) $(maxObj).addClass('modal-has-value');
            else $(maxObj).removeClass('modal-has-value');
        }
        if (minObj) {
            minObj.value = minVal.toFixed(3);
            if (minVal > 0) $(minObj).addClass('modal-has-value');
            else $(minObj).removeClass('modal-has-value');
        }

        var targetInput = maxObj || minObj;
        if (targetInput) {
            var row = $(targetInput).closest('tr');
            if (maxVal === 0 && minVal === 0) {
                //row.hide();
            } else {
                row.show();
            }
        }
    }

    // ฟังก์ชันสำหรับอัปเดตค่าลง Input Element (ประเภทช่องเดี่ยว) และสลับสีพื้นหลัง
    function updateRowVisibility2(elementId, maxVal) {
        var maxObj = document.getElementById(elementId + "_p");

        if (maxObj) {
            maxObj.value = maxVal.toFixed(3);
            if (maxVal > 0) $(maxObj).addClass('modal-has-value');
            else $(maxObj).removeClass('modal-has-value');
        }

        var targetInput = maxObj;
        if (targetInput) {
            var row = $(targetInput).closest('tr');
            if (maxVal === 0) {
                //row.hide();
            } else {
                row.show();
            }
        }
    }

    // ฟังก์ชันสั่งการเปิดและ Map ข้อมูลตัวเลข/ข้อความลง Modal
    function show_spec_std(param){
        var d = param.split("*");
        $("#data_spec_std").modal('show');
        
        document.getElementById("alloy").value = d[0] || "";

        var th_p = formatValue(d[1]);  var th_m = formatValue(d[2]);  
        var wi_p = formatValue(d[3]);  var wi_m = formatValue(d[4]);
        var le_p = formatValue(d[5]);  var le_m = formatValue(d[6]);
        var fla_p = formatValue(d[7]);
        var etp_p = formatValue(d[9]);

        // จัดการฟิลด์ประเภท Text สตริง
        var eatObj = document.getElementById("eat");
        var fixObj = document.getElementById("fix");
        
        eatObj.value = d[8] || "";
        fixObj.value = d[10] || "";

        // 🎨 ตรวจสอบสีสำหรับฟิลด์ข้อความ (ถ้าไม่ใช่ค่าว่าง และไม่ใช่ค่าเริ่มต้น 'NO')
        if (eatObj.value.trim() !== "") $(eatObj).addClass('modal-has-value');
        else $(eatObj).removeClass('modal-has-value');

        if (fixObj.value.trim() !== "" && fixObj.value.trim() !== "NO") $(fixObj).addClass('modal-has-value');
        else $(fixObj).removeClass('modal-has-value');
   
        // อัปเดตตัวเลขพร้อมสลับไฮไลต์สีเขียว
        updateRowVisibility("th", th_p, th_m);
        updateRowVisibility("wi", wi_p, wi_m);
        updateRowVisibility("le", le_p, le_m);
        
        updateRowVisibility2("fla", fla_p);
        updateRowVisibility2("etp", etp_p);
    }

    function edit_document(refedit) {
        if(!refedit) return;
        
        // แยกค่าที่ถูกคั่นด้วยเครื่องหมาย *
        var items = refedit.split('*');
        
        var custid = encodeURIComponent(items[0]);
        var proid = encodeURIComponent(items[1]);
        var alloy = encodeURIComponent(items[2]);
        var temper = encodeURIComponent(items[3]);
        var thickness = encodeURIComponent(items[4]);
        var width = encodeURIComponent(items[5]);
        var length = encodeURIComponent(items[6]);
        
        // ดึงค่าโฟลเดอร์จากระบบ (ถ้ามีอยู่ใน js parameter หรือใช้วิธีดึงจากหน้าเว็บ)
        var func_folder = document.getElementById('func') ? document.getElementById('func').value : '';

        // ลิงก์ไปยังหน้าแก้ไขพร้อมส่ง Composite Key ไปทั้งหมด
        window.location.assign('customer_spec_edit_master_mats.php?CSTMSPPL_ID=' + custid + 
                            '&PRODUCT_ID=' + proid + 
                            '&ALLOY=' + alloy + 
                            '&TEMPER=' + temper + 
                            '&THICKNESS=' + thickness + 
                            '&WIDTH=' + width + 
                            '&LENGTH=' + length + 
                            '&func=' + encodeURIComponent(func_folder));
    }    

    function delete_document(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete customer data: ' + d[0] +' Alloy: '+ d[2] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_cust_spec_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('customer_spec_std_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "The data cannot be deleted.";
                        alert("An error occurred.: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert("An error occurred while deleting the data: " + error);
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