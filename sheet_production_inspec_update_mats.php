<?php
session_start();

$pd_no = isset($_GET['pno']) ? trim($_GET['pno']) : '';

$iduser_func = $_SESSION['ID'] ?? '';
$folder_func = $_SESSION['FUNC'] ?? '';
$line_func   = $_SESSION['LINE'] ?? '';
$group_func  = $_SESSION['GROUP'] ?? '';

include("dbcon_mats-new.php");
include("function_mats.php");

$row_data = null;
$stre_data = array();

$temper_list = $conn->query("SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC")->fetchAll(PDO::FETCH_ASSOC);
$ecth_list = $conn->query("SELECT DESCRIPTION FROM ETCHMSTR1 ORDER BY DESCRIPTION ASC")->fetchAll(PDO::FETCH_ASSOC);
$flatness_list = $conn->query("SELECT FLATNESS_GRADE FROM STNDMSTR13 ORDER BY FLATNESS_GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($pd_no)) {
    $stre_data = RT_STRETCHER($pd_no);
    
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

$current_date = date('Y-m-d');

// คำนวณค่าตั้งต้นก่อนบวกรวม Takeout กลับมา เพื่อใช้คำนวณย้อนกลับเวลาถูกแก้ไขค่า
$curr_act_piece   = (float)($row_data['CRSH_ACTUALPIECE'] ?? 0);
$curr_take_piece  = (float)($row_data['CRSH_TAKEOUTPIECE'] ?? 0);
$base_total_piece = $curr_act_piece + $curr_take_piece;

$curr_act_weight   = (float)($row_data['CRSH_ACTUALWEIGHT'] ?? 0);
$curr_take_weight  = (float)($row_data['CRSH_TAKEOUTWEIGHT'] ?? 0);
$base_total_weight = $curr_act_weight + $curr_take_weight;
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    
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
            border-radius: 0 0 12px 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            border-top: none;
        }

        .bg-changed {
            background-color: #fff3cd !important;
            border-color: #ffeeba !important;
        }        

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

        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g3 { color: #b45309; border-bottom: 2px solid #fde68a; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-top: 0; margin-bottom: 20px; }

        .info-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .form-control-custom {
            width: 100%;
            height: 40px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 18px;
            transition: border-color 0.2s;
        }
        .form-control-custom:focus { border-color: #2563eb; outline: none; }
        .readonly-val { background-color: #f8fafc !important; color: #64748b; cursor: not-allowed; }

        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; font-size: 16px; padding: 10px 24px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
        .btn-save { background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 16px; padding: 10px 24px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-save:hover { background-color: #1d4ed8; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php 
    $menu = 'A3'; 
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="sheet_production_inspec_mats.php?func=<?php echo urlencode($folder_func); ?>">Circle or Sheet Inspection</a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <?php if (!$row_data): ?>
                <div class="dashboard-card" style="border-top: 1px solid #e2e8f0; border-radius: 12px;">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for the Product Number: <strong><?php echo htmlspecialchars($pd_no, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
            <?php else: ?>

            <form id="updateForm" action="model/update_sheet_production_inspec_mats.php" method="POST">
                <!-- Hidden inputs สำคัญ -->
                <input type="hidden" name="PRODUCT_NO" value="<?php echo htmlspecialchars($row_data['PRODUCT_NO'], ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="PRODUCT_ID" value="<?php echo htmlspecialchars($row_data['PRODUCT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="PRODUCT_MODEL" value="<?php echo htmlspecialchars($row_data['PRODUCT_MODEL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="COIL_NO" value="<?php echo htmlspecialchars($row_data['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>" />

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                        ✏️ Edit Coil: <span style="color:#2563eb;"><?php echo htmlspecialchars($pd_no, ENT_QUOTES, 'UTF-8'); ?></span>
                    </h3>
                    <div>
                        <button type="button" class="btn btn-back" onclick="window.location.assign('sheet_production_inspec_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                        <button type="submit" class="btn btn-save" style="margin-left: 8px;">💾 Save Changes </button>
                    </div>
                </div>

                <!-- UI Navigation Tabs -->
                <ul class="nav nav-tabs custom-tabs" id="coilDetailTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="sheet-inspec-tab" href="#">Sheet Inspection</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="thick-dia-tab" href="sheet_production_inspec_update_thicknet_diameter_mats.php?func=<?php echo urlencode($folder_func); ?>&pdno=<?php echo urlencode($pd_no); ?>">Thickness/Diameter</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="flatness-tab" href="sheet_production_inspec_update_flatness_mats.php?func=<?php echo urlencode($folder_func); ?>&pdno=<?php echo urlencode($pd_no); ?>">Length/Squareness/Flatness</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="defect-tab" href="sheet_production_inspec_update_defect_mats.php?func=<?php echo urlencode($folder_func); ?>&pdno=<?php echo urlencode($pd_no); ?>">Defect</a>
                    </li>                                        
                </ul>

                <div class="dashboard-card">
                    <!-- GROUP 1: Product Information -->
                    <h4 class="card-title-g1">📦 Circle & Sheet Product Details</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">PRODUCT NO.</div>
                            <input type="text" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">PRODUCT ID</div>
                            <input type="text" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['PRODUCT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">MODEL</div>
                            <input type="text" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['PRODUCT_MODEL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">INSPECTION DATE</div>
                            <input type="text" name="INSPECTION_DATE" class="form-control-custom readonly-val" value="<?php echo $current_date; ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">MATERIAL IN</div>
                            <input type="text" name="MATERIAL_IN" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">COIL NO</div>
                            <input type="text" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">ALLOY</div>
                            <input type="text" name="ALLOY" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">TEMPER</div>
                            <select name="TEMPER" class="form-control-custom">
                                <option value="">-- เลือก TEMPER --</option>
                                <?php if (!empty($temper_list) && is_array($temper_list)): ?>
                                    <?php foreach($temper_list as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item['TEMPER'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($row_data['TEMPER'] ?? '') == $item['TEMPER']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['TEMPER'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">GRADE</div>
                            <input type="text" name="GRADE" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">SURFACE GRADE (SG)</div>
                            <input type="text" name="SURFACE_GRADE" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">METALLURGICAL GRADE (MG)</div>
                            <input type="text" name="METALLURGICAL_GRADE" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">THICKNESS</div>
                            <input type="text" name="THICKNESS" class="form-control-custom readonly-val" value="<?php echo number_format((float)(fmt2($row_data['THICKNESS']) ?? 0), 2, '.', ''); ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">WIDTH</div>
                            <input type="text" name="WIDTH" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars(fmt2($row_data['WIDTH']) ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">LENGTH</div>
                            <input type="text" name="LENGTH" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars(fmt2($row_data['LENGTH']) ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">WORK PROCESS</div>
                            <input type="text" name="CRSH_WORKPROCESS" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['CRSH_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">NEXT PROCESS</div>
                            <input type="text" name="CRSH_NEXTPROCESS" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['CRSH_NEXTPROCESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">WEIGHT PER PIECE (KG)</div>
                            <input type="text" id="WEIGHT_PIECE" name="WEIGHT_PIECE" class="form-control-custom readonly-val" value="<?php echo number_format((float)(fmt2($row_data['WEIGHT_PIECE']) ?? 0), 2, '.', ''); ?>" readonly>
                        </div>

                        <!-- เพิ่ม data-base-piece และ data-base-weight บันทึกค่ารวมก่อนถูกหัก Takeout -->
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">PRODUCT ACTUAL PIECE</div>
                            <input type="text" id="CRSH_ACTUALPIECE" name="CRSH_ACTUALPIECE" class="form-control-custom readonly-val" 
                                value="<?php echo htmlspecialchars($row_data['CRSH_ACTUALPIECE'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" 
                                data-base-piece="<?php echo $base_total_piece; ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">PRODUCT ACTUAL WEIGHT (KG)</div>
                            <input type="text" id="CRSH_ACTUALWEIGHT" name="CRSH_ACTUALWEIGHT" class="form-control-custom readonly-val" 
                                value="<?php echo htmlspecialchars($row_data['CRSH_ACTUALWEIGHT'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" 
                                data-base-weight="<?php echo $base_total_weight; ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">TAKEOUT QTY PIECE</div>
                            <input type="number" step="any" id="CRSH_TAKEOUTPIECE" name="CRSH_TAKEOUTPIECE" class="form-control-custom" value="<?php echo htmlspecialchars($row_data['CRSH_TAKEOUTPIECE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" oninput="calculateTakeout()">
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">TAKEOUT QTY WEIGHT (KG)</div>
                            <input type="text" id="CRSH_TAKEOUTWEIGHT" name="CRSH_TAKEOUTWEIGHT" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['CRSH_TAKEOUTWEIGHT'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Product Status</div>
                            <input type="text" name="CRSH_STATUS" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($row_data['CRSH_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Result of Inspection</div>
                            <select name="CIN1_STATUS" class="form-control-custom">
                                <option value="">-- Result of Inspection --</option>
                                <?php if($row_data['CRSH_NEXTPROCESS']!='IS' AND ($row_data['CRSH_STATUS']=='OP' || $row_data['CRSH_STATUS']=='RJ') ){ ?>
                                    <?php foreach (['OP', 'RJ'] as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo (($row_data['CIN1_STATUS'] ?? '') == $status) ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php } else { ?>
                                    <?php foreach (['OP','AC', 'RM', 'RJ'] as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo (($row_data['CIN1_STATUS'] ?? '') == $status) ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                    <!-- GROUP 2: Inspection & Force Parameters -->
                    <h4 class="card-title-g2">⚙️ Inspection & Force Parameters</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">GRAIN SIZE</div>
                            <input type="number" step="any" name="GRAIN_SIZE" class="form-control-custom" value="<?php echo htmlspecialchars($row_data['GRAIN_SIZE'] ?? '0.00', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">BENDING</div>
                            <select name="BENDING" class="form-control-custom">
                                <option value="">-- BENDING --</option>
                                <?php foreach (['PASS', 'FAIL'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo (($row_data['BENDING'] ?? '') == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>                            
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">BULGE G</div>
                            <input type="text" name="BULGE_GRADE" class="form-control-custom" value="<?php echo htmlspecialchars($row_data['BULGE_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">ECTH BURR SIDE</div>
                            <select name="ETCH_BURR" class="form-control-custom">
                                <option value="">-- ECTH BURR SIDE --</option>
                                <?php if (!empty($ecth_list) && is_array($ecth_list)): ?>
                                    <?php foreach($ecth_list as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item['DESCRIPTION'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($row_data['ETCH_BURR'] ?? '') == $item['DESCRIPTION']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['DESCRIPTION'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">ECTH TOP SIDE</div>
                            <select name="ETCH_TOP" class="form-control-custom">
                                <option value="">-- ECTH TOP SIDE --</option>
                                <?php if (!empty($ecth_list) && is_array($ecth_list)): ?>
                                    <?php foreach($ecth_list as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item['DESCRIPTION'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($row_data['ETCH_TOP'] ?? '') == $item['DESCRIPTION']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['DESCRIPTION'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">USE SIDE</div>
                            <select name="USE_SIDE" class="form-control-custom">
                                <option value="">-- USE SIDE --</option>
                                <?php foreach (['TOP', 'BURR', 'SPECAIL'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo (($row_data['USE_SIDE'] ?? '') == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>                               
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">FLATNESS HIGH</div>
                            <input type="number" step="any" id="FLATNESS_HIGH" name="FLATNESS_HIGH" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($stre_data[12]) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>" oninput="calculateIUnit()">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">FLATNESS LENGTH</div>
                            <input type="number" step="any" id="FLATNESS_LENGTH" name="FLATNESS_LENGTH" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($stre_data[13]) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>" oninput="calculateIUnit()">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">I. UNIT</div>
                            <input type="text" id="I_UNIT" name="I_UNIT" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars($stre_data[14] ?? '0.00', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">FLATNESS GRADE</div>
                            <select name="FLATNESS_GRADE" class="form-control-custom">
                                <option value="">-- FLATNESS GRADE --</option>
                                <?php if (!empty($flatness_list) && is_array($flatness_list)): ?>
                                    <?php foreach($flatness_list as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item['FLATNESS_GRADE'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($row_data['FLATNESS_GRADE'] ?? '') == $item['FLATNESS_GRADE']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['FLATNESS_GRADE'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">MAX THICKNESS</div>
                            <input type="text" name="MAXIMUM_THICKNESS" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars(fmt2($row_data['MAXIMUM_THICKNESS']) ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">MIN THICKNESS</div>
                            <input type="text" name="MINIMUM_THICKNESS" class="form-control-custom readonly-val" value="<?php echo htmlspecialchars(fmt2($row_data['MINIMUM_THICKNESS']) ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                    </div>

                    <hr style="border-top: 1px solid #e2e8f0; margin: 20px 0;">

                    <!-- GROUP 3: Mechanical Properties & Remarks -->
                    <h4 class="card-title-g3">📍 Mechanical Properties & Remarks</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">UTS 1</div>
                            <input type="number" step="0.01" name="UTS" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['UTS']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">UTS 2</div>
                            <input type="number" step="0.01" name="UTS1" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['UTS1']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">YIELD STRENGTH 1</div>
                            <input type="number" step="0.01" name="YIELD_STRENGTH" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['YIELD_STRENGTH']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">YIELD STRENGTH 2</div>
                            <input type="number" step="0.01" name="YIELD_STRENGTH1" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['YIELD_STRENGTH1']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">ELONGATION 1</div>
                            <input type="number" step="0.01" name="ELONGATION" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['ELONGATION']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">ELONGATION 2</div>
                            <input type="number" step="0.01" name="ELONGATION1" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['ELONGATION1']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">EARING 1</div>
                            <input type="number" step="0.01" name="EARING" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['EARING']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">EARING 2</div>
                            <input type="number" step="0.01" name="EARING1" class="form-control-custom" value="<?php echo htmlspecialchars(fmt2($row_data['EARING1']) ?? '0.000', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">EARING ANGLE 1</div>
                            <input type="text" name="EARING_ANGLE" class="form-control-custom" value="<?php echo htmlspecialchars($row_data['EARING_ANGLE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">EARING ANGLE 2</div>
                            <input type="text" name="EARING_ANGLE1" class="form-control-custom" value="<?php echo htmlspecialchars($row_data['EARING_ANGLE1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Appearance</div>
                            <select name="CIN1_APPEARANCE" class="form-control-custom">
                                <option value="">-- เลือก Appearance --</option>
                                <?php foreach (['ACCEPT', 'BENDING ACCEPT', 'REJECT', 'SHADOW SURFACE'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo (($row_data['CIN1_APPEARANCE'] ?? '') == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>                            
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Dimension</div>
                            <select name="CIN1_DIMENSION" class="form-control-custom">
                                <option value="">-- Dimension --</option>
                                <?php foreach (['ACCEPT', 'REJECT'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo (($row_data['CIN1_DIMENSION'] ?? '') == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">INSPECTION REMARK</div>
                            <textarea name="CIN1_REMARK" class="form-control-custom" style="height:80px;"><?php echo htmlspecialchars($row_data['CIN1_REMARK'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>

        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
$(document).ready(function() {
    // รันการคำนวณ 1 ครั้งตอนโหลดหน้าจอเพื่อปรับสถานะสีไฮไลต์ให้อย่างถูกต้อง
    calculateTakeout();

    $('#updateForm').on('submit', function(e) {
        var SH_Status = $('select[name="CIN1_STATUS"]').val();

        if (!SH_Status || SH_Status.trim() === '') {
            alert('Please select Result of Inspection before saving the data !');
            $('select[name="CIN1_STATUS"]').focus();
            e.preventDefault();
            return false;
        }

        // ปลดล็อก Readonly ชั่วคราวก่อน Submit เพื่อให้เบราว์เซอร์ยอมส่งค่าเข้า PHP
        $(this).find('input[readonly]').prop('readonly', false);
    });
});

function calculateTakeout() {
    const actPieceElem = document.getElementById('CRSH_ACTUALPIECE');
    const actWeightElem = document.getElementById('CRSH_ACTUALWEIGHT');
    const takeoutPieceElem = document.getElementById('CRSH_TAKEOUTPIECE');
    const takeoutWeightElem = document.getElementById('CRSH_TAKEOUTWEIGHT');

    // อ่านค่าตั้งต้นก่อนถูกหัก Takeout (Base Total)
    const basePiece = parseFloat(actPieceElem.getAttribute('data-base-piece')) || 0;
    const baseWeight = parseFloat(actWeightElem.getAttribute('data-base-weight')) || 0;

    // อ่านค่า Takeout Piece และ Weight Per Piece
    const takeoutPiece = parseFloat(takeoutPieceElem.value) || 0;
    const weightPiece = parseFloat(document.getElementById('WEIGHT_PIECE').value) || 0;

    // คำนวณ Takeout Weight และ Actual Piece/Weight ใหม่
    const takeoutWeight = takeoutPiece * weightPiece;
    const newActPiece = basePiece - takeoutPiece;
    const newActWeight = baseWeight - takeoutWeight;

    // อัปเดตค่าลงในช่อง Element ต่างๆ
    takeoutWeightElem.value = takeoutPiece > 0 ? Math.round(takeoutWeight) : '0';
    actPieceElem.value = newActPiece >= 0 ? newActPiece : '0';
    actWeightElem.value = newActWeight >= 0 ? newActWeight.toFixed(2) : '0.00';

    // ใส่/ถอด ไฮไลต์สีเตือนเมื่อมีการ Takeout
    const changedFields = [takeoutPieceElem, takeoutWeightElem, actPieceElem, actWeightElem];
    if (takeoutPiece > 0) {
        changedFields.forEach(elem => elem.classList.add('bg-changed'));
    } else {
        changedFields.forEach(elem => elem.classList.remove('bg-changed'));
    }
}

function calculateIUnit() {
    const fh = parseFloat(document.getElementById('FLATNESS_HIGH').value) || 0;
    const fl = parseFloat(document.getElementById('FLATNESS_LENGTH').value) || 0;
    let iUnit = 0;

    if (fh !== 0 && fl !== 0) {
        const calc = (Math.PI * fh) / (2 * fl);
        iUnit = Math.pow(calc, 2) * 100000;
    }

    document.getElementById('I_UNIT').value = iUnit.toFixed(2);
}
</script>
</body>
</html>