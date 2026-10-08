<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$pd_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : (isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

include("function_mats.php");

// Query ดึงข้อมูล COILPROD1, COILINSP1 และ Master Standard จาก CMPSMSTR1
$row_data = null;
if (!empty($pd_no)) {

  $stre_data = array();

  $stre_data=RT_STRETCHER($pd_no);
    
    $sql = "SELECT 
            c.PRODUCT_NO, c.CRSH_STATUS, c.PRODUCT_ID, c.PRODUCT_MODEL,
            c.MATERIAL_IN, c.COIL_NO, c.PRODUCT_REFERENCE,
            c.ALLOY, c.TEMPER, c.GRADE, c.SURFACE_GRADE, c.METALLURGICAL_GRADE,
            c.THICKNESS, c.WIDTH, c.LENGTH, c.WEIGHT_PIECE,
            c.CRSH_ACTUALPIECE, c.CRSH_ACTUALWEIGHT,
            c.CRSH_WORKPROCESS, c.CRSH_NEXTPROCESS,
            c.CRSH_TAKEOUTPIECE, c.CRSH_TAKEOUTWEIGHT, c.CRSH_REMARK,
            c2.INSPECTION_DATE, c2.UTS, c2.UTS1, c2.ELONGATION, c2.ELONGATION1,
            c2.YIELD_STRENGTH, c2.YIELD_STRENGTH1, c2.EARING, c2.EARING1,
            c2.EARING_ANGLE, c2.EARING_ANGLE1, c2.GRAIN_SIZE, c2.BENDING, c2.BULGE_GRADE,
            c2.ETCH_BURR, c2.ETCH_TOP, c2.USE_SIDE, c2.FLATNESS_GRADE,
            c2.MINIMUM_THICKNESS, c2.MAXIMUM_THICKNESS, c2.CIN1_STATUS,
            c2.CIN1_COMBINE, c2.CIN1_APPEARANCE, c2.CIN1_DIMENSION, c2.CIN1_REMARK
        FROM CRSHPROD1 AS c
        LEFT JOIN CRSHINSP1 AS c2 ON c.PRODUCT_NO = c2.PRODUCT_NO
        WHERE c.PRODUCT_NO = :product_no";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':product_no', $pd_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);
}




// ฟังก์ชั่นสำหรับจัดรูปแบบค่า Edge/Between ร่วมกับตำแหน่ง
function fmt_edge_btwn($val, $tpbt) {
    if (($val === null || $val === '') && empty($tpbt)) return '-';
    $num_str = ($val !== null && $val !== '') ? (float)$val : '-';
    $tpbt_str = !empty($tpbt) ? ' ('.htmlspecialchars($tpbt).')' : '';
    return $num_str . $tpbt_str;
}

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

        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            border-top: none;
        }

        /* Nav Tabs Style */
        .custom-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 0;
            display: flex;
            background-color: #ffffff;
            border-radius: 12px 12px 0 0;
            padding: 10px 15px 0 15px;
            border: 1px solid #e2e8f0;
            border-bottom: none;
        }
        .custom-tabs .nav-item .nav-link {
            border: none;
            color: #64748b;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 24px;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            transition: all 0.2s ease;
        }
        .custom-tabs .nav-item .nav-link:hover {
            color: #1e40af;
            background-color: #f1f5f9;
        }
        .custom-tabs .nav-item .nav-link.active {
            color: #1e40af;
            border-bottom: 3px solid #1e40af;
            background-color: transparent;
        }

        /* Detail Group Cards Header */
        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }
        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }
        .card-title-g3 { 
            color: #b45309; 
            border-bottom: 2px solid #fde68a; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        /* Label and Value Typography */
        .info-label { 
            font-size: 12px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 15px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 18px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        /* Full Width Composition Table Styling */
        .comp-table-full {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            background-color: #ffffff;
        }
        .comp-table-full th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 700;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            border-bottom: 2px solid #0f172a;
        }
        .comp-table-full th.std-header { background-color: #0f172a; }
        .comp-table-full th.symbol-header { background-color: #334155; }
        .comp-table-full th.chk-header { background-color: #1e40af; }
        .comp-table-full td {
            padding: 8px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .comp-table-full tr:hover {
            background-color: #f8fafc;
        }
        .comp-table-full tr:last-child td { border-bottom: none; }
        .comp-table-full .elem-label {
            font-weight: 700;
            color: #1e40af;
            background-color: #f8fafc;
            width: 15%;
            text-align: center;
        }
        .comp-table-full .std-val {
            text-align: right;
            font-weight: 500;
            color: #334155;
            background-color: #f1f5f9;
            font-family: 'Consolas', 'Courier New', monospace;
            width: 20%;
        }
        .comp-table-full .symbol-val {
            text-align: center;
            font-weight: bold;
            color: #64748b;
            background-color: #f8fafc;
            width: 10%;
        }
        .comp-table-full .chk-val {
            text-align: right;
            background-color: #ffffff;
            font-family: 'Consolas', 'Courier New', monospace;
            width: 25%;
        }

        /* ปรับปุ่มให้ขนาดใหญ่ขึ้น เด่นชัด และใช้งานสะดวก */
        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 18px;
            padding: 14px 28px;
            height: 52px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }

        /* สไตล์ปุ่ม Print Coil Product Label ขนาดใหญ่ */
        .btn-print { 
            background-color: #059669; 
            color: #fff; 
            font-weight: 700; 
            padding: 14px 28px; 
            border-radius: 8px; 
            border: none; 
            font-size: 18px; 
            height: 52px;
            margin-right: 8px; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center;
            justify-content: center;
            cursor: pointer; 
            transition: all 0.2s;
        }
        .btn-print:hover { background-color: #047857; color: #fff; }
        .btn-print.disabled, .btn-print[disabled] { 
            background-color: #9ca3af !important; 
            color: #e5e7eb !important; 
            cursor: not-allowed; 
            pointer-events: none; 
            opacity: 0.6; 
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A3'; ?>
    <?php 
    if (!empty($folder_func) && !empty($group_func)) {
    include 'include/'.$folder_func.'/navigation.php'; 
    }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="sheet_production_inspec_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Circle & Sheet Inspection Details: <span style="color:#2563eb;"><?php echo htmlspecialchars($pd_no, ENT_QUOTES, 'UTF-8'); ?></span>
                </h3>
                <div style="display: flex; align-items: center;">
                    <button type="button" class="btn btn-back" onclick="back_home_sheet()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card" style="border-top: 1px solid #e2e8f0; border-radius: 12px;">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for the Product Number: <strong><?php echo htmlspecialchars($pd_no, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
            <?php else: ?>

            <!-- UI Navigation Tabs -->
            <ul class="nav nav-tabs custom-tabs" id="coilDetailTab" role="tablist">
                <!-- แท็บภายในหน้าเดิม (มี data-toggle="tab") -->
                <li class="nav-item">
                    <a class="nav-link" id="profile-tab" href="#">Sheet Inspection</a>
                </li>
                <!-- แท็บที่เป็น Link เปลี่ยนหน้า (เอา data-toggle="tab" ออก และเปลี่ยน id ให้ไม่ซ้ำ) -->
                <li class="nav-item">
                    <a class="nav-link" id="profile-tab" href="sheet_production_inspec_detail_thicknet_diameter_mats.php?func=<?php echo $folder_func ?>&pdno=<?php echo $pd_no ?>">Thickness/Diameter</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="defect-caster-tab" href="sheet_production_inspec_detail_flatness_mats.php?func=<?php echo $folder_func ?>&pdno=<?php echo $pd_no ?>">Length/Sqaureness/Flatness</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="defect-cm-tab" href="sheet_production_inspec_detail_defect_mats.php?func=<?php echo $folder_func ?>&pdno=<?php echo $pd_no ?>">Defect</a>
                </li>                                        
            </ul>

                <!-- Tab Contents Container -->
                <div class="tab-content" id="coilDetailTabContent">
                    
                    <!-- ================= TAB 1: Circle & Sheet INSPECTION ================= -->
                    <div class="tab-pane fade in active" id="tab-inspection" role="tabpanel">
                        <div class="dashboard-card">
                            
                            <!-- GROUP 1: Product Information -->
                            <h4 class="card-title-g1">📦 Circle & Sheet Product Details</h4>
                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['PRODUCT_NO'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT ID</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_ID'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MODEL</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_MODEL'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE.</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN </div><div class="info-value"><?php echo htmlspecialchars($row_data['MATERIAL_IN'] ?? '-'); ?></div></div>
                                
                                <div class="col-md-3 col-sm-6"><div class="info-label">COIL NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">CASTER COIL NO</div><div class="info-value"><?php echo htmlspecialchars(getParentCoil($row_data['COIL_NO'], true), ENT_QUOTES, 'UTF-8'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS    </div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo fmt2($row_data['THICKNESS']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt2($row_data['WIDTH']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo fmt2($row_data['LENGTH']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT PER PIECE (KG)</div><div class="info-value"><?php echo fmt2($row_data['WEIGHT_PIECE']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT ACTUAL PIECE</div><div class="info-value"><?php echo fmt2($row_data['CRSH_ACTUALPIECE']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT ACTUAL WEIGHT (KG)</div><div class="info-value"><?php echo fmt2($row_data['CRSH_ACTUALWEIGHT']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['CRSH_WORKPROCESS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['CRSH_NEXTPROCESS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">TACK OUT QTY PIECE</div><div class="info-value"><?php echo fmt2($row_data['CRSH_TAKEOUTPIECE']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">TACK OUT QTY WEIGHT (KG)</div><div class="info-value"><?php echo fmt2($row_data['CRSH_TAKEOUTWEIGHT']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">Product Status</div><div class="info-value"><?php echo htmlspecialchars($row_data['CRSH_STATUS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">Result of Inspection</div><div class="info-value"><?php echo htmlspecialchars($row_data['CIN1_STATUS'] ?? '-'); ?></div></div>                                
                            </div>

                            <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                            <!-- GROUP 2: Inspection & Force Parameters -->
                            <h4 class="card-title-g2">⚙️ Inspection & Force Parameters</h4>
                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">GRAIN SIZE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRAIN_SIZE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">BENDING</div><div class="info-value"><?php echo htmlspecialchars($row_data['BENDING'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">BULGE G</div><div class="info-value"><?php echo htmlspecialchars($row_data['BULGE_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ETC BURR SIDE</div><div class="info-value"><?php echo htmlspecialchars($row_data['ETCH_BURR'] ?? '-'); ?></div></div>
                                
                                <div class="col-md-3 col-sm-6"><div class="info-label">ETC TOP SIDE</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['ETCH_TOP'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">USE SIDE</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['USE_SIDE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">FLATNESS HIGH</div>
                                    <div class="info-value"><?php echo isset($stre_data[12]) ? $stre_data[12] : '-'; ?></div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">FLATNESS LENGTH</div>
                                    <div class="info-value"><?php echo isset($stre_data[13]) ? $stre_data[13] : '-'; ?></div>
                                </div>

                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">I. UNIT</div>
                                    <div class="info-value"><?php echo isset($stre_data[14]) ? $stre_data[14] : '0.00'; ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">FLATNESS GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['FLATNESS_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MAX THICKNESS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo fmt2($row_data['MAXIMUM_THICKNESS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MIN THICKNESS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo fmt2($row_data['MINIMUM_THICKNESS']); ?></div></div>
                            </div>

                            <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                            <!-- GROUP 3: Mechanical Properties & Remarks -->
                            <h4 class="card-title-g3">📍 Mechanical Properties & Remarks</h4>
                            
                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">UTS 1</div><div class="info-value"><?php echo fmt2($row_data['UTS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">UTS 2</div><div class="info-value"><?php echo fmt2($row_data['UTS1']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">YIELD STRENGTH 1</div><div class="info-value"><?php echo fmt2($row_data['YIELD_STRENGTH']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">YIELD STRENGTH 2</div><div class="info-value"><?php echo fmt2($row_data['YIELD_STRENGTH1']); ?></div></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">ELONGATION 1</div><div class="info-value"><?php echo fmt2($row_data['ELONGATION']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ELONGATION 2</div><div class="info-value"><?php echo fmt2($row_data['ELONGATION1']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">EARING 1</div><div class="info-value"><?php echo fmt2($row_data['EARING']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">EARING 2</div><div class="info-value"><?php echo fmt2($row_data['EARING1']); ?></div></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">EARING ANGLE 1</div><div class="info-value"><?php echo htmlspecialchars($row_data['EARING_ANGLE'] ?: '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">EARING ANGLE 2</div><div class="info-value"><?php echo htmlspecialchars($row_data['EARING_ANGLE1'] ?: '-'); ?></div></div>
                                
                            </div>                 

                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">INSPECTION REMARK</div><div class="info-value"><?php echo htmlspecialchars($row_data['CIN1_REMARK'] ?? '-'); ?></div></div>
                            </div>

                            <!-- ส่วนเพิ่มเติม Edge/Between -->
                            <div class="row" style="margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">Appearance</div>
                                    <div class="info-value"><?php echo htmlspecialchars($row_data['CIN1_APPEARANCE'] ?? '-'); ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">Dimension</div>
                                    <div class="info-value"><?php echo htmlspecialchars($row_data['CIN1_DIMENSION'] ?? '-'); ?></div>
                                </div>

                            </div>

                        </div>
                    </div>

  
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
// ฟังก์ชั่นเปิดหน้าต่าง Preview เพื่อพิมพ์ Label
const PRINT_URL = 'print_coil_product_label_mats.php'; 

$(document).ready(function() {
    $('.custom-tabs a:not([data-toggle="tab"])').on('click', function(e) {
        var url = $(this).attr('href');
        if (url && url !== '#') {
            window.location.href = url;
        }
    });
});

function previewPrintLabel(coilNo) {
    if (!coilNo) {
        alert('ไม่พบข้อมูล COIL NO.');
        return;
    }
    const targetUrl = `${PRINT_URL}?coilno=${encodeURIComponent(coilNo)}`;
    window.open(targetUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');

    // 🔥 สั่งเปลี่ยนสีปุ่มให้เป็นสีเขียวทันทีเมื่อกดพิมพ์ (ไม่ต้องกด Refresh)
    const btn = document.getElementById('btnPrintLabel');
    if (btn) {
        btn.classList.add('printed-active'); // หรือปรับเปลี่ยน style โดยตรง
        btn.style.backgroundColor = '#059669'; // บังคับเป็นสีเขียว
        btn.style.color = '#ffffff';
    }
}

function back_home_sheet(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('sheet_production_inspec_mats.php?func='+encodeURIComponent(data_fun)); 
}   

</script>
</html>