<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

include 'function_mats.php';

// Query ดึงข้อมูล COILPROD1, COILINSP1 และ Master Standard จาก CMPSMSTR1
$row_data = null;
if (!empty($coil_no)) {
    $sql = "SELECT 
                -- Group 1: Product Information (COILPROD1 + COILINSP1)
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, 
                p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.EXTRA_DESCRIPTION1, 
                p.EXTRA_DESCRIPTION2, p.EXTRA_DESCRIPTION3, i.INSPECTION_DATE, p.LINE_PROCESS, i.MAL_CASTNO,

                -- Group 2: Inspection & Physical Measurements (COILINSP1 + COILPROD1)
                i.SCD_OS, i.SCD_DS, i.MDF_UPPER, i.MDF_LOWER, i.ACTUAL_THICKNESS, p.ACTUAL_WIDTH, 
                i.MAXIMUM_THICKNESS, i.MINIMUM_THICKNESS, p.ACT_CMMAXTHICK, p.ACT_CMMINTHICK, 
                i.OIN1_APPEARANCE, i.OIN1_DIMENSION, i.PROFILE,

                -- Group 3: Mechanical Properties & Remarks (COILINSP1)
                i.UTS, i.UTS1, i.YIELD_STRENGTH, i.YIELD_STRENGTH1, 
                i.ELONGATION, i.ELONGATION1, i.EARING, i.EARING1, 
                i.EARING_ANGLE, i.EARING_ANGLE1, i.OIN1_REMARK,

                -- Added Fields from COILPROD1 (p.)
                p.EDGE_OPS, p.TPBT_OPS,
                p.EDGE_DRS, p.TPBT_DRS,
                p.BTWN_OPS, p.BTWN_DRS, p.TPBT_BTWN,

                -- Group 4: Chemical Composition Actual Checking & Calibration Flags (COILINSP1)
                i.CHECK_AL, i.CHECK_FE, i.CHECK_SI, i.CHECK_CR, i.CHECK_CU, 
                i.CHECK_MN, i.CHECK_MG, i.CHECK_ZN, i.CHECK_PB, i.CHECK_TI, 
                i.CHECK_AS, i.CHECK_NI, i.CHECK_SN, i.CHECK_SB, i.CHECK_BE, 
                i.CHECK_BI, i.CHECK_CD, i.CHECK_IN, i.GRAIN_SIZE, i.IN_TI, i.DELTA_TI,
                i.CALIBRATIONFLAG_CR, i.CALIBRATIONFLAG_CU, i.CALIBRATIONFLAG_MN, 
                i.CALIBRATIONFLAG_MG, i.CALIBRATIONFLAG_ZN, i.CALIBRATIONFLAG_PB, 
                i.CALIBRATIONFLAG_TI, i.CALIBRATIONFLAG_AS, i.CALIBRATIONFLAG_NI, 
                i.CALIBRATIONFLAG_SN, i.CALIBRATIONFLAG_SB, i.CALIBRATIONFLAG_BE, 
                i.CALIBRATIONFLAG_BI, i.CALIBRATIONFLAG_CD, i.CALIBRATIONFLAG_IN,

                -- Group 5: Alloy Composition Standard (CMPSMSTR1)
                m.AL_MIN, m.AL_MAX,
                m.FE_MIN, m.FE_AVG, m.FE_MAX,
                m.SI_MIN, m.SI_AVG, m.SI_MAX,
                m.CR_MIN, m.CR_AVG, m.CR_MAX,
                m.CU_MIN, m.CU_AVG, m.CU_MAX,
                m.MN_MIN, m.MN_AVG, m.MN_MAX,
                m.MG_MIN, m.MG_AVG, m.MG_MAX,
                m.ZN_MIN, m.ZN_AVG, m.ZN_MAX,
                m.PB_MIN, m.PB_AVG, m.PB_MAX,
                m.TI, m.AS_MAX, m.NI_MAX, m.SN_MAX, m.SB_MAX, m.BE_MAX, m.BI_MAX, m.CD_MAX

            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            LEFT JOIN CMPSMSTR1 m ON p.ALLOY = m.ALLOY 
                                 AND (p.CSTMSPPL_ID = m.CSTMSPPL_ID OR m.CSTMSPPL_ID IS NULL OR m.CSTMSPPL_ID = '')
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
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

// 🔥 ฟังก์ชั่นแสดงเฉพาะ Checking Value (พร้อมเช็คสีนอกเกณฑ์ Min-Max)
function render_check_val_only($check_val, $min = null, $max = null) {
    if ($check_val === null || $check_val === '') {
        return '-';
    }

    $c_val = (float)$check_val;
    $has_min = ($min !== null && $min !== '');
    $has_max = ($max !== null && $max !== '');

    $is_out_of_spec = false;

    if ($has_min && $c_val < (float)$min) {
        $is_out_of_spec = true;
    }
    if ($has_max && $c_val > (float)$max) {
        $is_out_of_spec = true;
    }

    $formatted_val = number_format($c_val, 4);

    if ($is_out_of_spec) {
        return '<span style="color: #dc2626; font-weight: 800; background-color: #fee2e2; padding: 2px 6px; border-radius: 4px;">' . $formatted_val . '</span>';
    } elseif ($has_min || $has_max) {
        return '<span style="color: #16a34a; font-weight: 700;">' . $formatted_val . '</span>';
    }

    return $formatted_val;
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

<?php $menu = 'A3';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="virtual_millcert_ccsh_sup_mats.php?func=<?php echo $folder_func ?>">Virtual Coil Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Coil Inspection Details: <span style="color:#2563eb;"><?php echo htmlspecialchars($coil_no); ?></span>
                </h3>
                <div style="display: flex; align-items: center;">
                    <?php 
                        // ตรวจสอบสถานะ PRODUCT STATUS (COIL_STATUS) หากเท่ากับ OP ให้ disable ปุ่มพิมพ์
                        $is_op_status = (isset($row_data['COIL_STATUS']) && strtoupper(trim($row_data['COIL_STATUS'])) === 'OP');
                        $disabled_attr = $is_op_status ? 'disabled' : '';
                        $disabled_class = $is_op_status ? 'disabled' : '';
                    ?>

                    <!-- ปุ่ม Print Coil Product Label (Preview เสมอ) -->
                    <button type="button" 
                            class="btn btn-print <?php echo $disabled_class; ?>" 
                            id="btnPrintLabel" 
                            <?php echo $disabled_attr; ?>
                            onclick="previewPrintLabel('<?php echo htmlspecialchars($coil_no, ENT_QUOTES, 'UTF-8'); ?>')">
                        🖨️ Print Coil Product Label
                    </button>

                    <button type="button" class="btn btn-back" onclick="back_home_coil()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card" style="border-top: 1px solid #e2e8f0; border-radius: 12px;">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

            <!-- UI Navigation Tabs -->
            <ul class="nav nav-tabs custom-tabs" id="coilDetailTab" role="tablist">
                <!-- แท็บภายในหน้าเดิม (มี data-toggle="tab") -->
                <li class="nav-item active">
                    <a class="nav-link active" id="inspection-tab" data-toggle="tab" href="#tab-inspection" role="tab">Coil Inspection</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="composition-tab" data-toggle="tab" href="#tab-composition" role="tab">Composition Information</a>
                </li>

                <!-- แท็บที่เป็น Link เปลี่ยนหน้า (เอา data-toggle="tab" ออก และเปลี่ยน id ให้ไม่ซ้ำ) -->
                <li class="nav-item">
                    <a class="nav-link" id="profile-tab" href="virtual_coil_production_inspec_detail_profile_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="defect-caster-tab" href="virtual_coil_production_inspec_detail_defect_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Defect Caster</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="defect-cm-tab" href="virtual_coil_production_inspec_detail_cm_defect_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Defect Cold Mill</a>
                </li>                                        
            </ul>

                <!-- Tab Contents Container -->
                <div class="tab-content" id="coilDetailTabContent">
                    
                    <!-- ================= TAB 1: COIL INSPECTION ================= -->
                    <div class="tab-pane fade in active" id="tab-inspection" role="tabpanel">
                        <div class="dashboard-card">
                            
                            <!-- GROUP 1: Product Information -->
                            <h4 class="card-title-g1">📦 Coil Product Details</h4>
                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MAL CAST NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['MAL_CASTNO'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                                
                                <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">LINE PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['LINE_PROCESS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT STATUS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_STATUS'] ?? '-'); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo fmt3($row_data['THICKNESS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt3($row_data['WIDTH']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo fmt3($row_data['F_THICKNESS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo fmt3($row_data['F_WIDTH']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_WORKPROCESS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NEXTPROCESS'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">CAST WEIGHT</div><div class="info-value"><?php echo fmt2($row_data['COIL_CASTWEIGHT']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">COIL WEIGHT (KG)</div><div class="info-value"><?php echo fmt2($row_data['COIL_ACTUALWEIGHT']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE WEIGHT</div><div class="info-value"><?php echo fmt2($row_data['COIL_PRODUCEWEIGHT']); ?></div></div>
                            </div>

                            <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                            <!-- GROUP 2: Inspection & Force Parameters -->
                            <h4 class="card-title-g2">⚙️ Inspection & Force Parameters</h4>
                            <div class="row">
                                <div class="col-md-3 col-sm-6"><div class="info-label">SCREW DOWN FORCE OP</div><div class="info-value"><?php echo fmt2($row_data['SCD_OS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">SCREW DOWN FORCE DR</div><div class="info-value"><?php echo fmt2($row_data['SCD_DS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MDF UPPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['MDF_UPPER'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MDF LOWER</div><div class="info-value"><?php echo htmlspecialchars($row_data['MDF_LOWER'] ?? '-'); ?></div></div>
                                
                                <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL THICKNESS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo fmt3($row_data['ACTUAL_THICKNESS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL WIDTH</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo fmt3($row_data['ACTUAL_WIDTH']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MAX THICKNESS</div><div class="info-value"><?php echo fmt3($row_data['MAXIMUM_THICKNESS']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">MIN THICKNESS</div><div class="info-value"><?php echo fmt3($row_data['MINIMUM_THICKNESS']); ?></div></div>

                                <div class="col-md-3 col-sm-6"><div class="info-label">ACT. COLD MILL MAX THICK</div><div class="info-value"><?php echo fmt3($row_data['ACT_CMMAXTHICK']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">ACT. COLD MILL MIN THICK</div><div class="info-value"><?php echo fmt3($row_data['ACT_CMMINTHICK']); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">APPEARANCE INSPECTION</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['OIN1_APPEARANCE'] ?? '-'); ?></div></div>
                                <div class="col-md-3 col-sm-6"><div class="info-label">DIMENSION INSPECTION</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['OIN1_DIMENSION'] ?? '-'); ?></div></div>
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
                                <div class="col-md-3 col-sm-6"><div class="info-label">PROFILE (TC-TE)</div><div class="info-value"><?php echo fmt2($row_data['PROFILE']); ?></div></div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">PPC INFORMATION (REMARK)</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?></div></div>
                            </div>                    

                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">INSPECTION REMARK</div><div class="info-value"><?php echo htmlspecialchars($row_data['OIN1_REMARK'] ?? '-'); ?></div></div>
                            </div>
                        
                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">EXTRA DESCRIPTION 1</div><div class="info-value"><?php echo htmlspecialchars($row_data['EXTRA_DESCRIPTION1'] ?? '-'); ?></div></div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">EXTRA DESCRIPTION 2</div><div class="info-value"><?php echo htmlspecialchars($row_data['EXTRA_DESCRIPTION2'] ?? '-'); ?></div></div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 col-sm-12"><div class="info-label">EXTRA DESCRIPTION 3</div><div class="info-value"><?php echo htmlspecialchars($row_data['EXTRA_DESCRIPTION3'] ?? '-'); ?></div></div>
                            </div>

                            <!-- ส่วนเพิ่มเติม Edge/Between -->
                            <div class="row" style="margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">EDGE OP. SIDE (MM.)</div>
                                    <div class="info-value"><?php echo fmt_edge_btwn($row_data['EDGE_OPS'], $row_data['TPBT_OPS']); ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">EDGE DR. SIDE (MM.)</div>
                                    <div class="info-value"><?php echo fmt_edge_btwn($row_data['EDGE_DRS'], $row_data['TPBT_DRS']); ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">BETWEEN FROM OP. SIDE (MM.)</div>
                                    <div class="info-value"><?php echo fmt2($row_data['BTWN_OPS']); ?></div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="info-label">BETWEEN TO DR. SIDE (MM.)</div>
                                    <div class="info-value"><?php echo fmt_edge_btwn($row_data['BTWN_DRS'], $row_data['TPBT_BTWN']); ?></div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ================= TAB 2: COMPOSITION INFORMATION ================= -->
                    <div class="tab-pane fade" id="tab-composition" role="tabpanel">
                        <div class="dashboard-card">
                            
                            <!-- Header & Legend Bar -->
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <h4 style="color:#1e40af; font-weight:700; margin:0;">🧪 Composition Standard & Checking Result</h4>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <small><span style="color:#dc2626; font-weight:bold;">■ Out of Spec</span> | <span style="color:#16a34a; font-weight:bold;">■ In Spec</span></small>
                                    <span style="background-color:#e0e7ff; color:#3730a3; padding: 4px 12px; border-radius: 20px; font-weight:700; font-size:13px;">
                                        ALLOY: <?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- ตารางเดียว แสดงผลเต็มหน้า (Full Width Single Table) -->
                            <div class="table-responsive">
                                <table class="table comp-table-full">
                                    <thead>
                                        <tr>
                                            <th>Element</th>
                                            <th class="std-header" style="text-align: right;">Min. Standard</th>
                                            <th class="std-header" style="text-align: right;">Avg. Standard</th>
                                            <th class="std-header" style="text-align: right;">Max. Standard</th>
                                            <th class="symbol-header" style="text-align: center;">Symbol</th>
                                            <th class="chk-header" style="text-align: right;">Checking Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="elem-label">Al</td>
                                            <td class="std-val"><?php echo fmt4($row_data['AL_MIN']); ?></td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['AL_MAX']); ?></td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_AL'], $row_data['AL_MIN'], $row_data['AL_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Fe</td>
                                            <td class="std-val"><?php echo fmt4($row_data['FE_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['FE_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['FE_MAX']); ?></td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_FE'], $row_data['FE_MIN'], $row_data['FE_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Si</td>
                                            <td class="std-val"><?php echo fmt4($row_data['SI_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['SI_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['SI_MAX']); ?></td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_SI'], $row_data['SI_MIN'], $row_data['SI_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Cr</td>
                                            <td class="std-val"><?php echo fmt4($row_data['CR_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['CR_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['CR_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_CR'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_CR'], $row_data['CR_MIN'], $row_data['CR_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Cu</td>
                                            <td class="std-val"><?php echo fmt4($row_data['CU_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['CU_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['CU_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_CU'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_CU'], $row_data['CU_MIN'], $row_data['CU_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Mn</td>
                                            <td class="std-val"><?php echo fmt4($row_data['MN_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['MN_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['MN_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_MN'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_MN'], $row_data['MN_MIN'], $row_data['MN_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Mg</td>
                                            <td class="std-val"><?php echo fmt4($row_data['MG_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['MG_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['MG_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_MG'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_MG'], $row_data['MG_MIN'], $row_data['MG_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Zn</td>
                                            <td class="std-val"><?php echo fmt4($row_data['ZN_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['ZN_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['ZN_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_ZN'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_ZN'], $row_data['ZN_MIN'], $row_data['ZN_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Pb</td>
                                            <td class="std-val"><?php echo fmt4($row_data['PB_MIN']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['PB_AVG']); ?></td>
                                            <td class="std-val"><?php echo fmt4($row_data['PB_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_PB'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_PB'], $row_data['PB_MIN'], $row_data['PB_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Ti</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['TI']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_TI'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_TI'], null, $row_data['TI']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">As</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['AS_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_AS'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_AS'], null, $row_data['AS_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Ni</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['NI_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_NI'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_NI'], null, $row_data['NI_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Sn</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['SN_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_SN'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_SN'], null, $row_data['SN_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Sb</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['SB_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_SB'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_SB'], null, $row_data['SB_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Be</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['BE_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_BE'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_BE'], null, $row_data['BE_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Bi</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['BI_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_BI'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_BI'], null, $row_data['BI_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Cd</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val"><?php echo fmt4($row_data['CD_MAX']); ?></td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_CD'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_CD'], null, $row_data['CD_MAX']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">In</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="std-val">-</td>
                                            <td class="symbol-val"><?php echo htmlspecialchars($row_data['CALIBRATIONFLAG_IN'] ?: '-'); ?></td>
                                            <td class="chk-val"><?php echo render_check_val_only($row_data['CHECK_IN']); ?></td>
                                        </tr>
                                        <tr style="border-top: 2px solid #cbd5e1;">
                                            <td class="elem-label">Grain Size</td>
                                            <td class="std-val" colspan="3" style="text-align:center; color:#94a3b8;">-</td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo fmt2($row_data['GRAIN_SIZE']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">In Ti</td>
                                            <td class="std-val" colspan="3" style="text-align:center; color:#94a3b8;">-</td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo fmt4($row_data['IN_TI']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="elem-label">Delta Ti</td>
                                            <td class="std-val" colspan="3" style="text-align:center; color:#94a3b8;">-</td>
                                            <td class="symbol-val">-</td>
                                            <td class="chk-val"><?php echo fmt4($row_data['DELTA_TI']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
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

function back_home_coil(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('virtual_millcert_ccsh_sup_mats.php?func='+encodeURIComponent(data_fun)); 
}   

function previewPrintLabel(coilNo) {
    if (!coilNo) {
        alert('No information found COIL NO.');
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
</script>
</html>