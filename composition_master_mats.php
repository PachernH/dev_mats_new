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

        .btn-action-edit {
            background-color: #f7ffed;
            color: #2eb91c;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #d6fca5;
            width: 100%;
            max-width: 120px;
            transition: all 0.2s;
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

        /* 🎨 คลาสสีสำหรับกล่องใน Modal ที่มีข้อมูลตัวเลขมากกว่า 0 */
        .modal-has-value {
            background-color: #e8f5e9 !important; /* พื้นหลังเขียวอ่อน */
            border-color: #a5d6a7 !important;
            font-weight: bold;
            color: #1b5e20 !important;
        }
    </style>
</head>
<body>

<div class="modal fade" id="data_composition" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title text-center" id="exampleModalLabel">🧪 Alloy Composition Specifications</h4>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 25%; vertical-align: middle;">
                                <input type="text" class="form-control" style="font-weight:700; color:#2563eb; background-color:#eff6ff; text-align:center;" id="alloy" placeholder="ALLOY CODE" readonly/>
                            </th>
                            <th><center>Max</center></th>
                            <th><center>Average</center></th>
                            <th><center>Min</center></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td align="center"><strong>AL (Aluminium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="al_max" placeholder="AL Max"/></td>
                            <td><input type="text" class="form-control text-center" id="al_avg" placeholder="AL Average"/></td>
                            <td><input type="text" class="form-control text-center" id="al_min" placeholder="AL Min"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Fe (Iron)</strong></td>
                            <td><input type="text" class="form-control text-center" id="fe_max" placeholder="Fe Max"/></td>
                            <td><input type="text" class="form-control text-center" id="fe_avg" placeholder="Fe Average"/></td>
                            <td><input type="text" class="form-control text-center" id="fe_min" placeholder="Fe Min"/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Si (Silicon)</strong></td>
                            <td><input type="text" class="form-control text-center" id="si_max" placeholder="Si Max"/></td>
                            <td><input type="text" class="form-control text-center" id="si_avg" placeholder="Si Average"/></td>
                            <td><input type="text" class="form-control text-center" id="si_min" placeholder="Si Min"/></td>
                        </tr>     
                        <tr>
                            <td align="center"><strong>Mn (Manganese)</strong></td>
                            <td><input type="text" class="form-control text-center" id="mn_max" placeholder="Mn Max"/></td>
                            <td><input type="text" class="form-control text-center" id="mn_avg" placeholder="Mn Average"/></td>
                            <td><input type="text" class="form-control text-center" id="mn_min" placeholder="Mn Min"/></td>
                        </tr> 
                        <tr>
                            <td align="center"><strong>Mg (Magnesium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="mg_max" placeholder="Mg Max"/></td>
                            <td><input type="text" class="form-control text-center" id="mg_avg" placeholder="Mg Average"/></td>
                            <td><input type="text" class="form-control text-center" id="mg_min" placeholder="Mg Min"/></td>
                        </tr>        
                        <tr>
                            <td align="center"><strong>Cr (Chromium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="cr_max" placeholder="Cr Max"/></td>
                            <td><input type="text" class="form-control text-center" id="cr_avg" placeholder="Cr Average"/></td>
                            <td><input type="text" class="form-control text-center" id="cr_min" placeholder="Cr Min"/></td>
                        </tr>       
                        <tr>
                            <td align="center"><strong>Cu (Copper)</strong></td>
                            <td><input type="text" class="form-control text-center" id="cu_max" placeholder="Cu Max"/></td>
                            <td><input type="text" class="form-control text-center" id="cu_avg" placeholder="Cu Average"/></td>
                            <td><input type="text" class="form-control text-center" id="cu_min" placeholder="Cu Min"/></td>
                        </tr>                            
                        <tr>
                            <td align="center"><strong>Zn (Zinc)</strong></td>
                            <td><input type="text" class="form-control text-center" id="zn_max" placeholder="Zn Max"/></td>
                            <td><input type="text" class="form-control text-center" id="zn_avg" placeholder="Zn Average"/></td>
                            <td><input type="text" class="form-control text-center" id="zn_min" placeholder="Zn Min"/></td>
                        </tr>    
                        <tr>
                            <td align="center"><strong>Pb (Lead)</strong></td>
                            <td><input type="text" class="form-control text-center" id="pb_max" placeholder="Pb Max"/></td>
                            <td><input type="text" class="form-control text-center" id="pb_avg" placeholder="Pb Average"/></td>
                            <td><input type="text" class="form-control text-center" id="pb_min" placeholder="Pb Min"/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>Ni (Nickel)</strong></td>
                            <td><input type="text" class="form-control text-center" id="ni_max" placeholder="Ni Max"/></td>
                            <td><input type="text" class="form-control text-center" id="ni_avg" placeholder="Ni Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="ni_min" placeholder="Ni Min" readonly/></td>
                        </tr>
                        <tr>
                            <td align="center"><strong>Sn (Tin)</strong></td>
                            <td><input type="text" class="form-control text-center" id="sn_max" placeholder="Sn Max"/></td>
                            <td><input type="text" class="form-control text-center" id="sn_avg" placeholder="Sn Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="sn_min" placeholder="Sn Min" readonly/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>Sb (Antimony)</strong></td>
                            <td><input type="text" class="form-control text-center" id="sb_max" placeholder="Sb Max"/></td>
                            <td><input type="text" class="form-control text-center" id="sb_avg" placeholder="Sb Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="sb_min" placeholder="Sb Min" readonly/></td>
                        </tr>   
                        <tr>
                            <td align="center"><strong>Be (Beryllium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="be_max" placeholder="Be Max"/></td>
                            <td><input type="text" class="form-control text-center" id="be_avg" placeholder="Be Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="be_min" placeholder="Be Min" readonly/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>Bi (Bismuth)</strong></td>
                            <td><input type="text" class="form-control text-center" id="bi_max" placeholder="Bi Max"/></td>
                            <td><input type="text" class="form-control text-center" id="bi_avg" placeholder="Bi Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="bi_min" placeholder="Bi Min" readonly/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>Cd (Cadmium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="cd_max" placeholder="Cd Max"/></td>
                            <td><input type="text" class="form-control text-center" id="cd_avg" placeholder="Cd Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="cd_min" placeholder="Cd Min" readonly/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>In (Indium)</strong></td>
                            <td><input type="text" class="form-control text-center" id="in_max" placeholder="In Max"/></td>
                            <td><input type="text" class="form-control text-center" id="in_avg" placeholder="In Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="in_min" placeholder="In Min" readonly/></td>
                        </tr>  
                        <tr>
                            <td align="center"><strong>As (Arsenic)</strong></td>
                            <td><input type="text" class="form-control text-center" id="as_max" placeholder="As Max"/></td>
                            <td><input type="text" class="form-control text-center" id="as_avg" placeholder="As Average" readonly/></td>
                            <td><input type="text" class="form-control text-center" id="as_min" placeholder="As Min" readonly/></td>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Composition Master Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="tab-menu-wrapper">
                    <button onclick="open_composition()" class="btn-tab-item active">COMPOSITION</button>
                    <button onclick="open_temper_no()" class="btn-tab-item">TEMPER</button>
                    <button onclick="open_grade_no()" class="btn-tab-item">GRADE</button>
                    <button onclick="open_surface_no()" class="btn-tab-item">SURFACE</button>
                    <button onclick="open_defect_no()" class="btn-tab-item">DEFECT</button>
                    <button onclick="open_mg_grade_no()" class="btn-tab-item">MG Grade</button>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Alloy Specification Records</h4>
                            <button class='btn btn-create-alloy' id='btn_create_kanban' onclick='open_alloy_no()'>
                                New Alloy (CREATE +)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">ALLOY</th>
                                        <th style="text-align: center;">CUSTOMER ID</th>
                                        <th style="text-align: center;">ALUMINIUM</th>
                                        <th style="text-align: center;">TI</th>
                                        <th style="text-align: center;">DENSITY</th>
                                        <th style="width: 150px; text-align: center;">Edit</th>
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
                                        $sql = "SELECT ALLOY,CSTMSPPL_ID,AL,TI,DENSITY,AL_MIN,AL_MAX,FE_MIN,FE_AVG,FE_MAX,SI_MIN,SI_AVG,SI_MAX,MN_MIN,MN_AVG,MN_MAX,MG_MIN,MG_AVG,MG_MAX,CR_MIN,CR_AVG,CR_MAX,CU_MIN,CU_AVG,CU_MAX,ZN_MIN,ZN_AVG,ZN_MAX,PB_MIN,PB_AVG,PB_MAX,
                                        AS_MAX,NI_MAX,SN_MAX,SB_MAX,BE_MAX,BI_MAX,CD_MAX,ACTIVE FROM CMPSMSTR1 WHERE ALLOY LIKE :alloy_no ORDER BY ALLOY ASC";
                                        $params[':alloy_no'] = '%'.$alloy_no.'%';
                                    }else{
                                        $sql = "SELECT ALLOY,CSTMSPPL_ID,AL,TI,DENSITY,AL_MIN,AL_MAX,FE_MIN,FE_AVG,FE_MAX,SI_MIN,SI_AVG,SI_MAX,MN_MIN,MN_AVG,MN_MAX,MG_MIN,MG_AVG,MG_MAX,CR_MIN,CR_AVG,CR_MAX,CU_MIN,CU_AVG,CU_MAX,ZN_MIN,ZN_AVG,ZN_MAX,PB_MIN,PB_AVG,PB_MAX,
                                        AS_MAX,NI_MAX,SN_MAX,SB_MAX,BE_MAX,BI_MAX,CD_MAX,ACTIVE FROM CMPSMSTR1 ORDER BY ALLOY ASC";
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_alloy_no = htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8');
                                        $safe_cst_no = htmlspecialchars($row['CSTMSPPL_ID'], ENT_QUOTES, 'UTF-8');
                                        $refdata = $row['ALLOY']."*".$row['AL_MIN']."*".$row['AL_MAX']."*".$row['FE_MIN']."*".$row['FE_AVG']."*".$row['FE_MAX']."*".$row['SI_MIN']."*".$row['SI_AVG']."*".$row['SI_MAX']."*".$row['MN_MIN']."*".$row['MN_AVG']."*".$row['MN_MAX']."*".$row['MG_MIN']."*".$row['MG_AVG']."*".$row['MG_MAX']."*".$row['CR_MIN']
                                        ."*".$row['CR_AVG']."*".$row['CR_MAX']."*".$row['CU_MIN']."*".$row['CU_AVG']."*".$row['CU_MAX']."*".$row['ZN_MIN']."*".$row['ZN_AVG']."*".$row['ZN_MAX']."*".$row['PB_MIN']."*".$row['PB_AVG']."*".$row['PB_MAX']."*".$row['AS_MAX']."*".$row['NI_MAX']."*".$row['SN_MAX']."*".$row['SB_MAX']."*".$row['BE_MAX']."*".$row['BI_MAX']."*".$row['CD_MAX']
                                        ."*".$row['ACTIVE'];
                                        $refdel = $row['ALLOY']."*".$row['CSTMSPPL_ID'];
                                        
                                        echo "<tr onclick=\"show_composition('".addslashes($refdata)."')\">";
                                        // ปรับเปลี่ยนคอลัมน์แรกให้กลายเป็น Soft Blue Badge ทรงมนที่คลิกเปิดรายละเอียดส่วนผสมสารเคมีได้
                                        echo "<td><button class='btn-badge-clickable' onclick=\"show_composition('".addslashes($refdata)."')\">".$safe_alloy_no."</button></td>";
                                        echo "<td style='text-align: center; font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['CSTMSPPL_ID'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['AL'] ?? 0), 3)."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['TI'] ?? 0), 3)."</td>";
                                        echo "<td style='text-align: center;'>".number_format(floatval($row['DENSITY'] ?? 0), 3)."</td>";
                                        echo "<td align='center'><button class='btn btn-action-edit' onclick=\"edit_document('".$safe_alloy_no."','".$safe_cst_no."')\">Edit</button></td>"; 
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
        window.location.assign('composition_master_mats.php?func='+encodeURIComponent(data_fun)+'&alloy_no='+encodeURIComponent(data_alloy_no)); 
    }    

    function open_alloy_no(){
        var data_fun = document.getElementById("func").value;
        window.location.assign('composition_Input_master_mats.php?func='+encodeURIComponent(data_fun));         
    } 

    // ฟังก์ชันจัดการแปลงค่าข้อมูลและบังคับทศนิยมคงที่ 3 ตำแหน่ง (.toFixed(3))
    function formatValue(val) {
        var num = parseFloat(val);
        return isNaN(num) ? 0 : num;
    }

    // ฟังก์ชันสำหรับอัปเดตค่าลง Input Element และทำการซ่อน/แสดงแถวหากค่าทั้งหมดเป็น 0
    function updateRowVisibility(elementId, maxVal, avgVal, minVal) {
        var maxObj = document.getElementById(elementId + "_max");
        var avgObj = document.getElementById(elementId + "_avg");
        var minObj = document.getElementById(elementId + "_min");

        if (maxObj) {
            maxObj.value = maxVal.toFixed(3);
            if (maxVal > 0) $(maxObj).addClass('modal-has-value');
            else $(maxObj).removeClass('modal-has-value');
        }
        if (avgObj) {
            avgObj.value = avgVal.toFixed(3);
            if (avgVal > 0) $(avgObj).addClass('modal-has-value');
            else $(avgObj).removeClass('modal-has-value');
        }
        if (minObj) {
            minObj.value = minVal.toFixed(3);
            if (minVal > 0) $(minObj).addClass('modal-has-value');
            else $(minObj).removeClass('modal-has-value');
        }

        var targetInput = maxObj || avgObj || minObj;
        if (targetInput) {
            var row = $(targetInput).closest('tr');
            if (maxVal === 0 && avgVal === 0 && minVal === 0) {
                row.hide();
            } else {
                row.show();
            }
        }
    }

    function show_composition(param){
        var d = param.split("*");
        $("#data_composition").modal('show');
        
        document.getElementById("alloy").value = d[0] || "";

        var al_min = formatValue(d[1]);  var al_max = formatValue(d[2]);  var al_avg = formatValue((al_min + al_max) / 2);
        var fe_min = formatValue(d[3]);  var fe_avg = formatValue(d[4]);  var fe_max = formatValue(d[5]);
        var si_min = formatValue(d[6]);  var si_avg = formatValue(d[7]);  var si_max = formatValue(d[8]);
        var mn_min = formatValue(d[9]);  var mn_avg = formatValue(d[10]); var mn_max = formatValue(d[11]);
        var mg_min = formatValue(d[12]); var mg_avg = formatValue(d[13]); var mg_max = formatValue(d[14]);        
        var cr_min = formatValue(d[15]); var cr_avg = formatValue(d[16]); var cr_max = formatValue(d[17]);        
        var cu_min = formatValue(d[18]); var cu_avg = formatValue(d[19]); var cu_max = formatValue(d[20]);        
        var zn_min = formatValue(d[21]); var zn_avg = formatValue(d[22]); var zn_max = formatValue(d[23]);        
        var pb_min = formatValue(d[24]); var pb_avg = formatValue(d[25]); var pb_max = formatValue(d[26]);        
        
        var as_max = formatValue(d[27]); var ni_max = formatValue(d[28]); var sn_max = formatValue(d[29]);
        var sb_max = formatValue(d[30]); var be_max = formatValue(d[31]); var bi_max = formatValue(d[32]);
        var cd_max = formatValue(d[33]); var in_max = 0;

        updateRowVisibility("al", al_max, al_avg, al_min);
        updateRowVisibility("fe", fe_max, fe_avg, fe_min);
        updateRowVisibility("si", si_max, si_avg, si_min);
        updateRowVisibility("mn", mn_max, mn_avg, mn_min);
        updateRowVisibility("mg", mg_max, mg_avg, mg_min);
        updateRowVisibility("cr", cr_max, cr_avg, cr_min);
        updateRowVisibility("cu", cu_max, cu_avg, cu_min);
        updateRowVisibility("zn", zn_max, zn_avg, zn_min);
        updateRowVisibility("pb", pb_max, pb_avg, pb_min);
        
        updateRowVisibility("as", as_max, 0, 0);
        updateRowVisibility("ni", ni_max, 0, 0);
        updateRowVisibility("sn", sn_max, 0, 0);
        updateRowVisibility("sb", sb_max, 0, 0);
        updateRowVisibility("be", be_max, 0, 0);
        updateRowVisibility("bi", bi_max, 0, 0);
        updateRowVisibility("cd", cd_max, 0, 0);
        updateRowVisibility("in", in_max, 0, 0);
    } 

    function edit_document(ci_no,cst_no){
    var data_fun = document.getElementById("func").value;
    window.location.assign('composition_edit_master_mats.php?func='+encodeURIComponent(data_fun)+'&ALLOY='+encodeURIComponent(ci_no)+'&CSTMSPPL_ID='+encodeURIComponent(cst_no));         
    } 

    function delete_document(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete the data Alloy: ' + d[0] +' Customer: '+ d[1] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_composition_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('composition_master_mats.php?func=' + encodeURIComponent(data_fun));
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

    function open_composition(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('composition_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }        
    
    function open_temper_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('temper_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    

    function open_grade_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('grade_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_surface_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('surface_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_defect_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('defect_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_mg_grade_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('mg_grade_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }      
</script>
</html>