<?php
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

// -------------------------------------------------------------
// ดึงข้อมูล Master Data สำหรับสร้าง Dropdown List ทั้งหมด
// -------------------------------------------------------------
$mat_in_list = $conn->query("SELECT BOI_PROJECT FROM BOIPRJCT1 ORDER BY BOI_PROJECT ASC")->fetchAll(PDO::FETCH_ASSOC);
$alloy_list  = $conn->query("SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY ORDER BY ALLOY ASC")->fetchAll(PDO::FETCH_ASSOC);
$temper_list = $conn->query("SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC")->fetchAll(PDO::FETCH_ASSOC);
$grade_list  = $conn->query("SELECT GRADE FROM GRDEMSTR1 ORDER BY GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);
$sgrd_list   = $conn->query("SELECT SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);
$mgrd_list   = $conn->query("SELECT METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);

$row_data = null;
if (!empty($coil_no)) {
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, 
                p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.EXTRA_DESCRIPTION1, 
                p.EXTRA_DESCRIPTION2, p.EXTRA_DESCRIPTION3, i.INSPECTION_DATE, p.LINE_PROCESS, i.MAL_CASTNO,

                i.SCD_OS, i.SCD_DS, i.MDF_UPPER, i.MDF_LOWER, i.ACTUAL_THICKNESS, p.ACTUAL_WIDTH, 
                i.MAXIMUM_THICKNESS, i.MINIMUM_THICKNESS, p.ACT_CMMAXTHICK, p.ACT_CMMINTHICK, 
                i.OIN1_APPEARANCE, i.OIN1_DIMENSION, i.PROFILE,

                i.UTS, i.UTS1, i.YIELD_STRENGTH, i.YIELD_STRENGTH1, 
                i.ELONGATION, i.ELONGATION1, i.EARING, i.EARING1, 
                i.EARING_ANGLE, i.EARING_ANGLE1, i.OIN1_REMARK,

                p.EDGE_OPS, p.TPBT_OPS,
                p.EDGE_DRS, p.TPBT_DRS,
                p.BTWN_OPS, p.BTWN_DRS, p.TPBT_BTWN,

                i.CHECK_AL, i.CHECK_FE, i.CHECK_SI, i.CHECK_CR, i.CHECK_CU, 
                i.CHECK_MN, i.CHECK_MG, i.CHECK_ZN, i.CHECK_PB, i.CHECK_TI, 
                i.CHECK_AS, i.CHECK_NI, i.CHECK_SN, i.CHECK_SB, i.CHECK_BE, 
                i.CHECK_BI, i.CHECK_CD, i.CHECK_IN, i.GRAIN_SIZE, i.IN_TI, i.DELTA_TI,

                i.CALIBRATIONFLAG_CR, i.CALIBRATIONFLAG_CU, i.CALIBRATIONFLAG_MN, i.CALIBRATIONFLAG_MG, i.CALIBRATIONFLAG_ZN,
                i.CALIBRATIONFLAG_PB, i.CALIBRATIONFLAG_TI, i.CALIBRATIONFLAG_AS, i.CALIBRATIONFLAG_NI, i.CALIBRATIONFLAG_SN,
                i.CALIBRATIONFLAG_SB, i.CALIBRATIONFLAG_BE, i.CALIBRATIONFLAG_BI, i.CALIBRATIONFLAG_CD, i.CALIBRATIONFLAG_IN,

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

function fmt_input($val) {
    return ($val !== null && $val !== '') ? htmlspecialchars($val) : '';
}

function fmt_4dec($val) {
    if ($val !== null && $val !== '') {
        return number_format((float)$val, 4, '.', '');
    }
    return '';
}

function fmt_date($val) {
    if ($val !== null && $val !== '') {
        $timestamp = strtotime($val);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
    }
    return '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; font-size: 16px; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 0 0 12px 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; border-top: none; }
        .custom-tabs { border-bottom: 2px solid #e2e8f0; background-color: #ffffff; border-radius: 12px 12px 0 0; padding: 10px 15px 0 15px; border: 1px solid #e2e8f0; border-bottom: none; }
        .custom-tabs .nav-item .nav-link { border: none; color: #64748b; font-weight: 700; font-size: 17px; padding: 14px 24px; }
        .custom-tabs .nav-item.active .nav-link { color: #1e40af; border-bottom: 4px solid #1e40af; background: transparent; }
        
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }
        .card-title-g3 { color: #b45309; border-bottom: 2px solid #fde68a; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block; }
        
        .form-control { border-radius: 6px; border: 1px solid #cbd5e1; font-size: 16px; height: 44px; padding: 8px 12px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
        
        .readonly-control { background-color: #e2e8f0 !important; color: #475569 !important; font-weight: 600; cursor: not-allowed; }

        .comp-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
        .comp-table th { background-color: #1e293b; color: #ffffff; text-align: center; padding: 12px; font-size: 16px; }
        .comp-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 16px; vertical-align: middle; }
        .comp-table .elem-label { font-weight: 700; color: #1e40af; background-color: #f8fafc; width: 12%; font-size: 16px; }
        .comp-table .std-val { text-align: right; color: #334155; background-color: #f1f5f9; font-family: monospace; width: 14%; font-size: 16px; font-weight: 600; }
        
        .comp-table .sym-display { text-align: center; font-weight: 800; color: #d97706; font-size: 20px; font-family: monospace; width: 12%; background-color: #fffbeb; }
        
        .comp-table .chk-input { width: 22%; }
        .comp-table input.form-control { text-align: right; font-family: monospace; font-weight: 700; font-size: 17px; height: 40px; }

        .btn-save { background-color: #2563eb; color: #fff; font-weight: 700; padding: 12px 28px; border-radius: 8px; border: none; font-size: 17px; }
        .btn-save:hover { background-color: #1d4ed8; color: #fff; }
        .btn-back { background-color: #64748b; color: #fff; font-weight: 700; padding: 12px 20px; border-radius: 8px; border: none; font-size: 16px; }
        .btn-spectro { background-color: #0284c7; color: #ffffff; font-weight: 700; border: none; font-size: 16px; padding: 10px 20px; height: 44px; }
        .btn-spectro:hover { background-color: #0369a1; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

    <?php $menu = 'A3';?>

    <?php if (!empty($folder_func) && !empty($group_func)) { include 'include/'.$folder_func.'/navigation.php';} ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="virtual_millcert_ccsh_sup_mats.php?func=<?php echo $folder_func ?>">Virtual Coil Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <form id="updateForm" action="model/update_coil_production_inspec_mats.php" method="POST">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                        ✏️ Edit Coil: <span style="color:#2563eb;"><?php echo htmlspecialchars($coil_no); ?></span>
                    </h3>
                    <div>
                        <button type="button" class="btn btn-back" onclick="window.location.assign('virtual_millcert_ccsh_sup_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                        <button type="submit" class="btn btn-save" style="margin-left: 8px;">💾 Save Changes</button>
                    </div>
                </div>

                <?php if (!$row_data): ?>
                    <div class="dashboard-card" style="border-radius: 12px;">
                        <div class="alert alert-warning" style="margin:0; font-size:16px;">No information found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong></div>
                    </div>
                <?php else: ?>

                    <input type="hidden" name="COIL_NO" id="COIL_NO" value="<?php echo htmlspecialchars($row_data['COIL_NO']); ?>">
                    <input type="hidden" name="PRODUCT_REFERENCE" value="<?php echo fmt_input($row_data['PRODUCT_REFERENCE']); ?>">
                    <input type="hidden" name="MAL_CASTNO" value="<?php echo fmt_input($row_data['MAL_CASTNO']); ?>">
                    <input type="hidden" name="BATCH_NO" value="<?php echo fmt_input($row_data['BATCH_NO']); ?>">
                    <input type="hidden" name="IDUSER_FUNC" value="<?php echo htmlspecialchars($iduser_func); ?>">

                    <ul class="nav nav-tabs custom-tabs" id="coilDetailTab">
                        <li class="active"><a data-toggle="tab" href="#tab-inspection">Coil Inspection Info</a></li>
                        <li><a data-toggle="tab" href="#tab-composition">Composition Details</a></li>
                        <li><a href="virtual_coil_production_inspec_profile_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Profile</a></li>
                        <li><a href="virtual_coil_production_inspec_defect_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Defect Caster</a></li>
                        <li><a href="virtual_coil_production_inspec_cm_defect_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no ?>">Defect Cold Mill</a></li>
                    </ul>

                    <div class="tab-content">
                        <!-- TAB 1: Inspection Info -->
                        <div class="tab-pane fade in active" id="tab-inspection">
                            <div class="dashboard-card">
                                
                                <h4 class="card-title-g1">📦 Coil Product Details</h4>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>PRODUCT NO.</label>
                                            <input type="text" class="form-control readonly-control" value="<?php echo fmt_input($row_data['COIL_NO']); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>PRODUCT REF</label>
                                            <input type="text" class="form-control readonly-control" value="<?php echo fmt_input($row_data['PRODUCT_REFERENCE']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>MAL CAST NO</label>
                                            <input type="text" class="form-control readonly-control" value="<?php echo fmt_input($row_data['MAL_CASTNO']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>FURNACE BATCH NO.</label>
                                            <input type="text" class="form-control readonly-control" value="<?php echo fmt_input($row_data['BATCH_NO']); ?>" readonly>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>MATERIAL IN</label>
                                            <select name="MATERIAL_IN" class="form-control">
                                                <option value="">-- MATERIAL IN --</option>
                                                <?php foreach ($mat_in_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['BOI_PROJECT']); ?>" <?php echo ($row_data['MATERIAL_IN'] == $item['BOI_PROJECT']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['BOI_PROJECT']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>CUSTOMER SUPPLIER ID</label>
                                            <input type="text" name="CSTMSPPL_ID" class="form-control readonly-control" value="<?php echo fmt_input($row_data['CSTMSPPL_ID']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>INSPECTION DATE</label>
                                            <input type="date" name="INSPECTION_DATE" class="form-control" value="<?php echo fmt_date($row_data['INSPECTION_DATE']); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>LINE PROCESS</label>
                                            <input type="text" name="LINE_PROCESS" class="form-control readonly-control" value="<?php echo fmt_input($row_data['LINE_PROCESS']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>ALLOY</label>
                                            <select name="ALLOY" class="form-control">
                                                <option value="">-- ALLOY --</option>
                                                <?php foreach ($alloy_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['ALLOY']); ?>" <?php echo ($row_data['ALLOY'] == $item['ALLOY']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['ALLOY']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>TEMPER</label>
                                            <select name="TEMPER" class="form-control">
                                                <option value="">-- TEMPER --</option>
                                                <?php foreach ($temper_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['TEMPER']); ?>" <?php echo ($row_data['TEMPER'] == $item['TEMPER']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['TEMPER']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>GRADE</label>
                                            <select name="GRADE" class="form-control">
                                                <option value="">-- GRADE --</option>
                                                <?php foreach ($grade_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['GRADE']); ?>" <?php echo ($row_data['GRADE'] == $item['GRADE']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['GRADE']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>SURFACE GRADE (SG)</label>
                                            <select name="SURFACE_GRADE" class="form-control">
                                                <option value="">-- SURFACE GRADE --</option>
                                                <?php foreach ($sgrd_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['SURFACE_GRADE']); ?>" <?php echo ($row_data['SURFACE_GRADE'] == $item['SURFACE_GRADE']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['SURFACE_GRADE']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>METALLURGICAL GRADE (MG)</label>
                                            <select name="METALLURGICAL_GRADE" class="form-control">
                                                <option value="">-- METALLURGICAL GRADE --</option>
                                                <?php foreach ($mgrd_list as $item): ?>
                                                    <option value="<?php echo htmlspecialchars($item['METALLURGICAL_GRADE']); ?>" <?php echo ($row_data['METALLURGICAL_GRADE'] == $item['METALLURGICAL_GRADE']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['METALLURGICAL_GRADE']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>PRODUCT STATUS</label>
                                            <select name="COIL_STATUS" class="form-control">
                                                <option value="">-- STATUS --</option>
                                                <option value="NN" selected>NN </option>
                                                <?php foreach (['AC', 'RM', 'RJ'] as $status): ?>
                                                    <option value="<?php echo $status; ?>" <?php echo ($row_data['COIL_STATUS'] == $status) ? 'selected' : ''; ?>>
                                                        <?php echo $status; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3"><div class="form-group"><label>THICKNESS</label><input type="number" step="0.001" name="THICKNESS" class="form-control" value="<?php echo fmt_input($row_data['THICKNESS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>WIDTH</label><input type="number" step="0.001" name="WIDTH" class="form-control" value="<?php echo fmt_input($row_data['WIDTH']); ?>"></div></div>

                                    <div class="col-md-3"><div class="form-group"><label>ORIGINAL THICKNESS</label><input type="number" step="0.001" name="F_THICKNESS" class="form-control" value="<?php echo fmt_input($row_data['F_THICKNESS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>ORIGINAL WIDTH</label><input type="number" step="0.001" name="F_WIDTH" class="form-control" value="<?php echo fmt_input($row_data['F_WIDTH']); ?>"></div></div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>T5 TEMPERATURE</label>
                                            <input type="text" name="T5_TEMPERATURE" class="form-control readonly-control" value="<?php echo fmt_input($row_data['T5_TEMPERATURE']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>WORK PROCESS</label>
                                            <input type="text" name="COIL_WORKPROCESS" class="form-control readonly-control" value="<?php echo fmt_input($row_data['COIL_WORKPROCESS']); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>NEXT PROCESS</label>
                                            <input type="text" name="COIL_NEXTPROCESS" class="form-control readonly-control" value="<?php echo fmt_input($row_data['COIL_NEXTPROCESS']); ?>" readonly>
                                        </div>
                                    </div>   
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>CAST WEIGHT</label>
                                            <input type="number" step="0.01" name="COIL_CASTWEIGHT" class="form-control readonly-control" value="<?php echo fmt_input($row_data['COIL_CASTWEIGHT']); ?>" readonly>
                                        </div>
                                    </div> 

                                    <div class="col-md-3"><div class="form-group"><label>COIL WEIGHT (KG)</label><input type="number" step="0.01" name="COIL_ACTUALWEIGHT" class="form-control" value="<?php echo fmt_input($row_data['COIL_ACTUALWEIGHT']); ?>"></div></div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>PRODUCE WEIGHT</label>
                                            <input type="number" step="0.01" name="COIL_PRODUCEWEIGHT" class="form-control readonly-control" value="<?php echo fmt_input($row_data['COIL_PRODUCEWEIGHT']); ?>" readonly>
                                        </div>
                                    </div>    
                                </div>

                                <hr>

                                <h4 class="card-title-g2">⚙️ Inspection & Force Parameters</h4>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label>SCREW DOWN FORCE OP</label><input type="number" step="0.01" name="SCD_OS" class="form-control" value="<?php echo fmt_input($row_data['SCD_OS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>SCREW DOWN FORCE DR</label><input type="number" step="0.01" name="SCD_DS" class="form-control" value="<?php echo fmt_input($row_data['SCD_DS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>MDF UPPER</label><input type="text" name="MDF_UPPER" class="form-control" value="<?php echo fmt_input($row_data['MDF_UPPER']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>MDF LOWER</label><input type="text" name="MDF_LOWER" class="form-control" value="<?php echo fmt_input($row_data['MDF_LOWER']); ?>"></div></div>

                                    <div class="col-md-3"><div class="form-group"><label>ACTUAL THICKNESS</label><input type="number" step="0.001" name="ACTUAL_THICKNESS" class="form-control" value="<?php echo fmt_input($row_data['ACTUAL_THICKNESS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>ACTUAL WIDTH</label><input type="number" step="0.001" name="ACTUAL_WIDTH" class="form-control" value="<?php echo fmt_input($row_data['ACTUAL_WIDTH']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>MAX THICKNESS</label><input type="number" step="0.001" name="MAXIMUM_THICKNESS" class="form-control" value="<?php echo fmt_input($row_data['MAXIMUM_THICKNESS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>MIN THICKNESS</label><input type="number" step="0.001" name="MINIMUM_THICKNESS" class="form-control" value="<?php echo fmt_input($row_data['MINIMUM_THICKNESS']); ?>"></div></div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>ACT. COLD MILL MAX THICK</label>
                                            <input type="number" step="0.001" name="ACT_CMMAXTHICK" class="form-control readonly-control" value="<?php echo fmt_input($row_data['ACT_CMMAXTHICK']); ?>" readonly>
                                        </div>
                                    </div>    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>ACT. COLD MILL MIN THICK</label>
                                            <input type="number" step="0.001" name="ACT_CMMINTHICK" class="form-control readonly-control" value="<?php echo fmt_input($row_data['ACT_CMMINTHICK']); ?>" readonly>
                                        </div>
                                    </div>                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>APPEARANCE INSPECTION</label>
                                            <select name="OIN1_APPEARANCE" class="form-control">
                                                <option value="">-- APPEARANCE --</option>
                                                <?php foreach (['ACCEPT', 'BENDING ACCEPT', 'REJECT'] as $app): ?>
                                                    <option value="<?php echo $app; ?>" <?php echo ($row_data['OIN1_APPEARANCE'] == $app) ? 'selected' : ''; ?>>
                                                        <?php echo $app; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>DIMENSION INSPECTION</label>
                                            <select name="OIN1_DIMENSION" class="form-control">
                                                <option value="">-- DIMENSION --</option>
                                                <?php foreach (['ACCEPT', 'REJECT'] as $dim): ?>
                                                    <option value="<?php echo $dim; ?>" <?php echo ($row_data['OIN1_DIMENSION'] == $dim) ? 'selected' : ''; ?>>
                                                        <?php echo $dim; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <h4 class="card-title-g3">📍 Mechanical Properties & Remarks</h4>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label>UTS 1</label><input type="number" step="0.01" name="UTS" class="form-control" value="<?php echo fmt_input($row_data['UTS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>UTS 2</label><input type="number" step="0.01" name="UTS1" class="form-control" value="<?php echo fmt_input($row_data['UTS1']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>YIELD STRENGTH 1</label><input type="number" step="0.01" name="YIELD_STRENGTH" class="form-control" value="<?php echo fmt_input($row_data['YIELD_STRENGTH']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>YIELD STRENGTH 2</label><input type="number" step="0.01" name="YIELD_STRENGTH1" class="form-control" value="<?php echo fmt_input($row_data['YIELD_STRENGTH1']); ?>"></div></div>

                                    <div class="col-md-3"><div class="form-group"><label>ELONGATION 1</label><input type="number" step="0.01" name="ELONGATION" class="form-control" value="<?php echo fmt_input($row_data['ELONGATION']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>ELONGATION 2</label><input type="number" step="0.01" name="ELONGATION1" class="form-control" value="<?php echo fmt_input($row_data['ELONGATION1']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>EARING 1</label><input type="number" step="0.01" name="EARING" class="form-control" value="<?php echo fmt_input($row_data['EARING']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>EARING 2</label><input type="number" step="0.01" name="EARING1" class="form-control" value="<?php echo fmt_input($row_data['EARING1']); ?>"></div></div>

                                    <div class="col-md-3"><div class="form-group"><label>EARING ANGLE 1</label><input type="text" name="EARING_ANGLE" class="form-control" value="<?php echo fmt_input($row_data['EARING_ANGLE']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>EARING ANGLE 2</label><input type="text" name="EARING_ANGLE1" class="form-control" value="<?php echo fmt_input($row_data['EARING_ANGLE1']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>PROFILE (TC-TE)</label><input type="number" step="0.01" name="PROFILE" class="form-control" value="<?php echo fmt_input($row_data['PROFILE']); ?>"></div></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12"><div class="form-group"><label>PPC INFORMATION (REMARK)</label><input type="text" name="COIL_REMARK" class="form-control" value="<?php echo fmt_input($row_data['COIL_REMARK']); ?>"></div></div>
                                    <div class="col-md-12"><div class="form-group"><label>INSPECTION REMARK</label><input type="text" name="OIN1_REMARK" class="form-control" value="<?php echo fmt_input($row_data['OIN1_REMARK']); ?>"></div></div>
                                    <div class="col-md-12"><div class="form-group"><label>EXTRA DESCRIPTION 1</label><input type="text" name="EXTRA_DESCRIPTION1" class="form-control" value="<?php echo fmt_input($row_data['EXTRA_DESCRIPTION1']); ?>"></div></div>
                                    <div class="col-md-12"><div class="form-group"><label>EXTRA DESCRIPTION 2</label><input type="text" name="EXTRA_DESCRIPTION2" class="form-control" value="<?php echo fmt_input($row_data['EXTRA_DESCRIPTION2']); ?>"></div></div>
                                    <div class="col-md-12"><div class="form-group"><label>EXTRA DESCRIPTION 3</label><input type="text" name="EXTRA_DESCRIPTION3" class="form-control" value="<?php echo fmt_input($row_data['EXTRA_DESCRIPTION3']); ?>"></div></div>
                                </div>

                                <div class="row" style="margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
                                    <div class="col-md-3"><div class="form-group"><label>EDGE OP. SIDE (MM.)</label><input type="number" step="0.01" name="EDGE_OPS" class="form-control" value="<?php echo fmt_input($row_data['EDGE_OPS']); ?>"></div></div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>EDGE OP. POSITION</label>
                                            <select name="TPBT_OPS" class="form-control">
                                                <option value="">-- POSITION --</option>
                                                <?php foreach (['TOP', 'BOTTOM'] as $pos): ?>
                                                    <option value="<?php echo $pos; ?>" <?php echo ($row_data['TPBT_OPS'] == $pos) ? 'selected' : ''; ?>>
                                                        <?php echo $pos; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3"><div class="form-group"><label>EDGE DR. SIDE (MM.)</label><input type="number" step="0.01" name="EDGE_DRS" class="form-control" value="<?php echo fmt_input($row_data['EDGE_DRS']); ?>"></div></div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>EDGE DR. POSITION</label>
                                            <select name="TPBT_DRS" class="form-control">
                                                <option value="">-- POSITION --</option>
                                                <?php foreach (['TOP', 'BOTTOM'] as $pos): ?>
                                                    <option value="<?php echo $pos; ?>" <?php echo ($row_data['TPBT_DRS'] == $pos) ? 'selected' : ''; ?>>
                                                        <?php echo $pos; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3"><div class="form-group"><label>BETWEEN FROM OP. SIDE</label><input type="number" step="0.01" name="BTWN_OPS" class="form-control" value="<?php echo fmt_input($row_data['BTWN_OPS']); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>BETWEEN TO DR. SIDE</label><input type="number" step="0.01" name="BTWN_DRS" class="form-control" value="<?php echo fmt_input($row_data['BTWN_DRS']); ?>"></div></div>
                                    
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>BETWEEN POSITION</label>
                                            <select name="TPBT_BTWN" class="form-control">
                                                <option value="">-- POSITION --</option>
                                                <?php foreach (['TOP', 'BOTTOM'] as $pos): ?>
                                                    <option value="<?php echo $pos; ?>" <?php echo ($row_data['TPBT_BTWN'] == $pos) ? 'selected' : ''; ?>>
                                                        <?php echo $pos; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- TAB 2: Chemical Composition Details -->
                        <div class="tab-pane fade" id="tab-composition">
                            <div class="dashboard-card">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h4 style="color:#1e40af; font-weight:700; font-size:20px; margin: 0;">🧪 Chemical Composition Checking Values</h4>
                                    
                                    <div class="form-inline">
                                        <div class="form-group" style="margin-bottom:0; margin-right:8px;">
                                            <label style="display:inline; margin-right:8px; font-size:16px;">SAMPLE NO:</label>
                                            <input type="text" id="spectro_sampleno" class="form-control readonly-control" style="width:180px; font-weight:bold; font-size:16px; text-transform:uppercase;" value="<?php echo !empty($row_data['MAL_CASTNO']) ? fmt_input($row_data['MAL_CASTNO']) : fmt_input($row_data['COIL_NO']); ?>" readonly>
                                        </div>
                                        <button type="button" class="btn btn-spectro" id="btnLoadSpectro">
                                             ⚡ Retrieve data Spectro
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <table class="table comp-table">
                                            <thead>
                                                <tr>
                                                    <th>Element</th>
                                                    <th style="text-align: right;">Min. Std</th>
                                                    <th style="text-align: right;">Avg. Std</th>
                                                    <th style="text-align: right;">Max. Std</th>
                                                    <th style="text-align: center;">Symbol</th>
                                                    <th style="text-align: center;">Checking Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $elements = [
                                                    'AL' => 'CHECK_AL', 'FE' => 'CHECK_FE', 'SI' => 'CHECK_SI', 
                                                    'CR' => 'CHECK_CR', 'CU' => 'CHECK_CU', 'MN' => 'CHECK_MN', 
                                                    'MG' => 'CHECK_MG', 'ZN' => 'CHECK_ZN', 'PB' => 'CHECK_PB', 
                                                    'TI' => 'CHECK_TI', 'AS' => 'CHECK_AS', 'NI' => 'CHECK_NI', 
                                                    'SN' => 'CHECK_SN', 'SB' => 'CHECK_SB', 'BE' => 'CHECK_BE', 
                                                    'BI' => 'CHECK_BI', 'CD' => 'CHECK_CD', 'IN' => 'CHECK_IN'
                                                ];

                                                $calib_elements = [
                                                    'CR' => 'CALIBRATIONFLAG_CR', 'CU' => 'CALIBRATIONFLAG_CU', 
                                                    'MN' => 'CALIBRATIONFLAG_MN', 'MG' => 'CALIBRATIONFLAG_MG', 
                                                    'ZN' => 'CALIBRATIONFLAG_ZN', 'PB' => 'CALIBRATIONFLAG_PB', 
                                                    'TI' => 'CALIBRATIONFLAG_TI', 'AS' => 'CALIBRATIONFLAG_AS', 
                                                    'NI' => 'CALIBRATIONFLAG_NI', 'SN' => 'CALIBRATIONFLAG_SN', 
                                                    'SB' => 'CALIBRATIONFLAG_SB', 'BE' => 'CALIBRATIONFLAG_BE', 
                                                    'BI' => 'CALIBRATIONFLAG_BI', 'CD' => 'CALIBRATIONFLAG_CD', 
                                                    'IN' => 'CALIBRATIONFLAG_IN'
                                                ];

                                                foreach ($elements as $elem => $field) {
                                                    $min = $row_data[$elem.'_MIN'] ?? '-';
                                                    $avg = $row_data[$elem.'_AVG'] ?? '-';
                                                    $max = $row_data[$elem.'_MAX'] ?? ($row_data[$elem] ?? '-');
                                                    if ($elem == 'TI') $max = $row_data['TI'] ?? '-';

                                                    echo '<tr>';
                                                    echo '<td class="elem-label">'.$elem.'</td>';
                                                    echo '<td class="std-val">'.($min !== '-' && $min !== null ? number_format((float)$min, 4) : '-').'</td>';
                                                    echo '<td class="std-val">'.($avg !== '-' && $avg !== null ? number_format((float)$avg, 4) : '-').'</td>';
                                                    echo '<td class="std-val">'.($max !== '-' && $max !== null ? number_format((float)$max, 4) : '-').'</td>';
                                                    
                                                    echo '<td class="sym-display">';
                                                    if (array_key_exists($elem, $calib_elements)) {
                                                        $flag_field = $calib_elements[$elem];
                                                        $curr_flag = $row_data[$flag_field] ?? '';
                                                        echo '<span id="disp_sym_'.$elem.'">'.htmlspecialchars($curr_flag).'</span>';
                                                        echo '<input type="hidden" name="'.$flag_field.'" id="val_sym_'.$elem.'" value="'.htmlspecialchars($curr_flag).'">';
                                                    } else {
                                                        echo '-';
                                                    }
                                                    echo '</td>';

                                                    echo '<td class="chk-input"><input type="number" step="0.0001" class="form-control elem-field" data-elem="'.$elem.'" name="'.$field.'" value="'.fmt_4dec($row_data[$field]).'"></td>';
                                                    echo '</tr>';
                                                }
                                                ?>
                                                <tr style="border-top: 2px solid #cbd5e1;">
                                                    <td class="elem-label">Grain Size</td>
                                                    <td class="std-val" colspan="3" style="text-align:center;">-</td>
                                                    <td class="sym-display">-</td>
                                                    <td class="chk-input"><input type="number" step="0.01" class="form-control" name="GRAIN_SIZE" value="<?php echo fmt_input($row_data['GRAIN_SIZE']); ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="elem-label">In Ti</td>
                                                    <td class="std-val" colspan="3" style="text-align:center;">-</td>
                                                    <td class="sym-display">-</td>
                                                    <td class="chk-input"><input type="number" step="0.0001" class="form-control" name="IN_TI" value="<?php echo fmt_4dec($row_data['IN_TI']); ?>"></td>
                                                </tr>
                                                <tr>
                                                    <td class="elem-label">Delta Ti</td>
                                                    <td class="std-val" colspan="3" style="text-align:center;">-</td>
                                                    <td class="sym-display">-</td>
                                                    <td class="chk-input"><input type="number" step="0.0001" class="form-control" name="DELTA_TI" value="<?php echo fmt_4dec($row_data['DELTA_TI']); ?>"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </form>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
$(document).ready(function() {

    // -------------------------------------------------------------
    // ตรวจสอบ Validation ตอนกด บันทึกข้อมูล
    // -------------------------------------------------------------
    $('#updateForm').on('submit', function(e) {
        var coilStatus = $('select[name="COIL_STATUS"]').val();
        var coilapp = $('select[name="OIN1_APPEARANCE"]').val();
        var coildim = $('select[name="OIN1_DIMENSION"]').val();

        if (!coilStatus || coilStatus.trim() === '') {
            alert('Please select PRODUCT STATUS Before saving the data.!');
            $('select[name="COIL_STATUS"]').focus();
            e.preventDefault();
            return false;
        }
        if (!coilapp || coilapp.trim() === '') {
            alert('Please select APPEARANCE INSPECTION Before saving the data.!');
            $('select[name="OIN1_APPEARANCE"]').focus();
            e.preventDefault();
            return false;
        }
        if (!coildim || coildim.trim() === '') {
            alert('Please select DIMENSION INSPECTION Before saving the data.!');
            $('select[name="OIN1_DIMENSION"]').focus();
            e.preventDefault();
            return false;
        }
    });

    // -------------------------------------------------------------
    // ดึงข้อมูล Spectro
    // -------------------------------------------------------------
    $('#btnLoadSpectro').on('click', function(e) {
        e.preventDefault();
        
        var sampleNo = $('#spectro_sampleno').val().trim();
        if(!sampleNo) {
            alert('No information found Sample No.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('Loading....');

        $.ajax({
            url: 'model/get_spectro_data.php',
            type: 'GET',
            data: { sampleno: sampleNo },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html('⚡ Retrieve data Spectro');
                
                if(response.success) {
                    var spectroData = response.data;
                    var flagsData   = response.flags || {};
                    var matchCount  = 0;

                    $('.elem-field').each(function() {
                        var elemName = $(this).data('elem');
                        if(spectroData.hasOwnProperty(elemName)) {
                            var rawVal = parseFloat(spectroData[elemName]);
                            if(!isNaN(rawVal)) {
                                $(this).val(rawVal.toFixed(4));
                            } else {
                                $(this).val('');
                            }
                            matchCount++;
                        }
                    });

                    $.each(flagsData, function(elemName, flagVal) {
                        var $span = $('#disp_sym_' + elemName);
                        var $input = $('#val_sym_' + elemName);
                        if($span.length > 0) {
                            $span.text(flagVal || '');
                            $input.val(flagVal || '');
                        }
                    });

                    alert('Spectro data retrieval successful! All data found' + matchCount + ' element');
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('⚡ Retrieving Spectro data.');
                alert('An error occurred while connecting to the server: ' + error);
            }
        });
    });
});
</script>
</html>