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

        /* 🎨 คลาสสีสำหรับกล่องใน Modal ที่มีข้อมูลระบุไว้มากกว่า 0 */
        .modal-has-value {
            background-color: #e8f5e9 !important; /* พื้นหลังเขียวอ่อน */
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20 !important;
        }
    </style>
</head>
<body>

<div class="modal fade" id="data_spec_std" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-center" id="exampleModalLabel">🧪 Specification Mechanical Properties</h4>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                <input type="text" class="form-control" style="font-weight:700; color:#2563eb; background-color:#eff6ff; text-align:center;" id="alloy" placeholder="ALLOY CODE" readonly/>
                            </th>
                            <th><center>FROM</center></th>
                            <th><center>TO</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>UTS (Ksi)</strong></td>
                            <td><input type="text" class="form-control text-center" id="uts_fi" placeholder="UTS (Ksi)"/></td>
                            <td><input type="text" class="form-control text-center" id="uts_ti" placeholder="UTS (Ksi)"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Yield Strength (Ksi)</strong></td>
                            <td><input type="text" class="form-control text-center" id="yis_fi" placeholder="Yield Strength (Ksi)"/></td>
                            <td><input type="text" class="form-control text-center" id="yis_ti" placeholder="Yield Strength (Ksi)"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Elongation % (Ksi)</strong></td>
                            <td><input type="text" class="form-control text-center" id="el_fi" placeholder="Elongation % (Ksi)"/></td>
                            <td><input type="text" class="form-control text-center" id="el_ti" placeholder="Elongation % (Ksi)"/></td>
                        </tr>     
                        <tr>
                            <td align="center"><strong>UTS(Kg/mm2)</strong></td>
                            <td><input type="text" class="form-control text-center" id="uts_fk" placeholder="UTS(Kg/mm2)"/></td>
                            <td><input type="text" class="form-control text-center" id="uts_tk" placeholder="UTS(Kg/mm2)"/></td>
                        </tr> 
                        <tr>
                            <td align="center"><strong>Yield Strength(Kg/mm2)</strong></td>
                            <td><input type="text" class="form-control text-center" id="yis_fk" placeholder="Thickness Plus"/></td>
                            <td><input type="text" class="form-control text-center" id="yis_tk" placeholder="Thickness Minus"/></td>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Specification Mechanical Properties Master Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="tab-menu-wrapper">
                    <button onclick="pro_std()" class="btn-tab-item">Product Specification Standard</button>
                    <button onclick="cust_std()" class="btn-tab-item">Customer Specification Standard</button>
                    <button onclick="spec_std()" class="btn-tab-item active">Specification Mechanical Properties</button>
                    <button onclick="prac_std()" class="btn-tab-item">Technical Practice Batch Annealing</button>
                    <button onclick="defect_caster_std()" class="btn-tab-item">Defect Caster Coil</button>
            </div>

        
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="alloy_no">Alloy Code</label>
                    <input class="form-control-minimal" name="alloy_no" id="alloy_no" type="text" placeholder="พิมพ์รหัสแอลลอยด์เพื่อค้นหา..." value="<?php echo htmlspecialchars($alloy_no, ENT_QUOTES, 'UTF-8'); ?>" onchange="search_alloy_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Specification Mechanical Properties Records</h4>
                            <button class='btn btn-create-alloy' id='btn_create_kanban' onclick='open_alloy_no()'>
                                New Specification Mechanical Properties (CREATE +)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">ALLOY</th>
                                        <th style="width: 160px; text-align: center;">Temper</th>
                                        <th style="width: 160px; text-align: center;">THICKNESS FROM</th>
                                        <th style="width: 160px; text-align: center;">THICKNESS TO</th>
                                        <th style="width: 150px; text-align: center;">EDIT</th> <!-- 💡 1. เพิ่มหัวคอลัมน์ EDIT -->
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
                                        $sql = "SELECT TOP(500) ALLOY,TEMPER_TARGET,THICKNESS_FROM,THICKNESS_TO,UTSFROM_KSI,UTSTO_KSI,UTSFROM_KGM,UTSTO_KGM,
                                        YSFROM_KSI,YSTO_KSI,YSFROM_KGM,YSTO_KGM,ELONGFROM,ELONGTO
                                        FROM TCPS0201_6 WHERE ALLOY LIKE :alloy_no ORDER BY ALLOY ASC";
                                        $params[':alloy_no'] = '%'.$alloy_no.'%';
                                    }else{
                                        $sql = "SELECT TOP(500) ALLOY,TEMPER_TARGET,THICKNESS_FROM,THICKNESS_TO,UTSFROM_KSI,UTSTO_KSI,UTSFROM_KGM,UTSTO_KGM,
                                        YSFROM_KSI,YSTO_KSI,YSFROM_KGM,YSTO_KGM,ELONGFROM,ELONGTO
                                        FROM TCPS0201_6 ORDER BY ALLOY ASC";
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_alloy_no = htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8');

                                        $refdata = $row['ALLOY']."*".$row['UTSFROM_KSI']."*".$row['UTSTO_KSI']."*".$row['UTSFROM_KGM']."*".$row['UTSTO_KGM']
                                        ."*".$row['YSFROM_KSI']."*".$row['YSTO_KSI']."*".$row['YSFROM_KGM']."*".$row['YSTO_KGM']."*".$row['ELONGFROM']."*".$row['ELONGTO'];

                                        $refdel = $row['ALLOY']."*".$row['TEMPER_TARGET']."*".$row['THICKNESS_FROM']."*".$row['THICKNESS_TO'];
                                        $refedit = $row['ALLOY']."*".$row['TEMPER_TARGET']."*".$row['THICKNESS_FROM']."*".$row['THICKNESS_TO']; // 💡 สร้าง String สำหรับส่งค่าไปหน้า Edit
                                        
                                        echo "<tr onclick=\"show_spec_std('".addslashes($refdata)."')\">";
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_spec_std('".addslashes($refdata)."')\">".htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['TEMPER_TARGET'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['THICKNESS_FROM'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".htmlspecialchars($row['THICKNESS_TO'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'><button class='btn btn-action-edit' onclick=\"edit_document('".addslashes($refedit)."')\">Edit</button></td>"; // 💡 2. เพิ่มปุ่ม Edit ใน user_table[cite: 7]
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
        window.location.assign('specification_properties_master_mats.php?func='+encodeURIComponent(data_fun)+'&alloy_no='+encodeURIComponent(data_alloy_no)); 
    }    

    function open_alloy_no(){
        var data_fun = document.getElementById("func").value;
        window.location.assign('specification_input_properties_master_mats.php?func='+encodeURIComponent(data_fun));         
    } 

    // ฟังก์ชันจัดการแปลงค่าข้อมูลและบังคับทศนิยมคงที่ 3 ตำแหน่ง (.toFixed(3))
    function formatValue(val) {
        var num = parseFloat(val);
        return isNaN(num) ? 0 : num;
    }

    // 💡 3. ฟังก์ชันปรับปรุงให้เปลี่ยนสีพื้นหลังเป็นสีเขียวอ่อนอัตโนมัติเมื่อค่าตัวเลข > 0[cite: 7]
    function updateRowVisibility(elementId, maxVal, minVal) {
        var maxObj = document.getElementById(elementId + "_fi");
        var minObj = document.getElementById(elementId + "_ti");

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

    // 💡 4. ฟังก์ชันปรับปรุงชุดที่ 2 ให้เปลี่ยนสีพื้นหลังเป็นสีเขียวอ่อนอัตโนมัติเมื่อค่าตัวเลข > 0[cite: 7]
    function updateRowVisibility2(elementId, maxVal, minVal) {
        var maxObj = document.getElementById(elementId + "_fk");
        var minObj = document.getElementById(elementId + "_tk");

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

    function show_spec_std(param){
        var d = param.split("*");
        $("#data_spec_std").modal('show');
        
        document.getElementById("alloy").value = d[0] || "";

        var uts_fi = formatValue(d[1]);  var uts_ti = formatValue(d[2]);  
        var uts_fk = formatValue(d[3]);  var uts_tk = formatValue(d[4]);

        var yis_fi = formatValue(d[5]);  var yis_ti = formatValue(d[6]);
        var yis_fk = formatValue(d[7]);   var yis_tk = formatValue(d[8]);

        var el_fi = formatValue(d[9]); var el_ti = formatValue(d[10]);
  
    
        updateRowVisibility("uts", uts_fi, uts_ti);
        updateRowVisibility("yis", yis_fi, yis_ti);
        updateRowVisibility("el", el_fi, el_ti);

        updateRowVisibility2("uts", uts_fk, uts_tk);
        updateRowVisibility2("yis", yis_fk, yis_tk);

    } 

    // 💡 5. เพิ่มฟังก์ชัน JavaScript สำหรับแกะข้อมูลส่งต่อไปยังหน้า Edit ฟอร์มแก้ไขหลังบ้าน
    function edit_document(refedit) {
        if (!refedit) return;
        var items = refedit.split('*');
        
        var alloy = encodeURIComponent(items[0]);
        var temper = encodeURIComponent(items[1]);
        var thick_from = encodeURIComponent(items[2]);
        var thick_to = encodeURIComponent(items[3]);
        
        var func_folder = document.getElementById('func') ? document.getElementById('func').value : '';

        window.location.assign('specification_edit_properties_master_mats.php?ALLOY=' + alloy + 
                               '&TEMPER_TARGET=' + temper + 
                               '&THICKNESS_FROM=' + thick_from + 
                               '&THICKNESS_TO=' + thick_to + 
                               '&func=' + encodeURIComponent(func_folder));
    }

    function delete_document(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete the data Alloy: ' + d[0] +' Temper: '+ d[1] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_properties_spec_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('specification_properties_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "Unable to delete data";
                        alert("Error occurred: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert("Error occurred while deleting data: " + error);
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