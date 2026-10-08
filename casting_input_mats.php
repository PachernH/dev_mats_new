<?php
// เริ่ม session ก่อน ANY output
session_start();

include 'function_mats.php';

if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
}

if(!isset($_GET['coilno'])){
    $cno = '';
} else {
    $cno = htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8');
}

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

// Query ดึงข้อมูล COILPROD1 ครบทุกกลุ่มมาแสดงใน Form Input
$row_data = null;
if (!empty($cno)) {
    $sql = "SELECT 
                -- Group 1: Product + Location
                COIL_NO, BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, 
                COUNTRY_ORIGIN, MATERIAL_IN, COIL_CALCULATEWEIGHT, COIL_ACTUALWEIGHT, 
                COIL_STARTTIME, COIL_ENDTIME, LOCATION_ID, COIL_STATUS,
                
                -- Group 2: Specification
                ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, 
                THICKNESS, WIDTH, ACTUAL_WIDTH,
                
                -- Group 3: Edge Curl & Calculate
                COIL_EDGECURLA, COIL_EDGECURLB, COIL_EDGECURLC, 
                COIL_EDGECURLD, COIL_EDGECURLE, TOTAL_TI, IN_TI, DELTA_TI
            FROM COILPROD1 
            WHERE COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $cno, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ฟังก์ชั่น Format DateTime ให้พร้อมสำหรับ <input type="datetime-local">
function fmt_dt($datetime_str) {
    if (empty($datetime_str) || $datetime_str == '-') return '';
    $time = strtotime($datetime_str);
    return ($time !== false) ? date('Y-m-d\TH:i', $time) : '';
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
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { padding: 20px 20px !important; }
        
        /* Form Card Containers */
        .form-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
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

        /* Input Formatting */
        .form-group label {
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #0f172a;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }
        .form-control[readonly] {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
        }
        select.form-control {
            height: 42px !important;
        }

        /* Edge Curl Box Layout */
        .edge-curl-box {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .edge-curl-item {
            flex: 1;
            min-width: 100px;
        }
        .edge-label-badge {
            display: inline-block;
            background-color: #fef3c7;
            color: #92400e;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-bottom: 6px;
        }

        /* Buttons Styling */
        .btn-custom-cancel {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 24px;
            font-weight: 600;
            font-size: 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-cancel:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .btn-custom-submit {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 32px;
            font-weight: 600;
            font-size: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }
        .btn-custom-submit:hover {
            background-color: #1d4ed8;
            box-shadow: 0 6px 10px -1px rgba(37, 99, 235, 0.3);
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="coil_production_mats.php?func=<?php echo $folder_func ?>">Casting Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <form id="form_caster">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0; font-weight:700; color:#1e293b;">
                        📝 Input Coil Production Data: <span style="color:#2563eb;"><?php echo htmlspecialchars($cno ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </h3>
                    <div>
                        <button type="button" class="btn btn-custom-cancel" onclick="window.location.assign('coil_production_mats.php?func=<?php echo urlencode($folder_func); ?>')">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-custom-submit" style="margin-left:8px;" onclick="submitCasterData()">
                            💾 Save Data
                        </button>
                    </div>
                </div>

                <!-- GROUP 1: Product Information -->
                <div class="form-card">
                    <h4 class="card-title-g1">📦 Group 1: Product Information</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="cn_data">COIL NO</label>
                                <input type="text" class="form-control" id="cn_data" name="cn_data" value="<?php echo htmlspecialchars($row_data['COIL_NO'] ?? $cno, ENT_QUOTES, 'UTF-8'); ?>" readonly />
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="bn_data">BATCH NO</label>
                                <input type="text" class="form-control" id="bn_data" name="bn_data" value="<?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly/>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="primary_smelt">PRIMARY OF SMELT</label>
                                <select class="form-control" id="primary_smelt" name="primary_smelt" readonly>
                                    <option value="">-- PRIMARY OF SMELT --</option>
                                    <?php
                                        $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['PRIMARY_SMELT']) && $row_data['PRIMARY_SMELT'] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                    
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="secondary_smelt">SECONDARY OF SMELT</label>
                                <select class="form-control" id="secondary_smelt" name="secondary_smelt" readonly>
                                    <option value="">-- SECONDARY OF SMELT --</option>
                                    <?php
                                        $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['SECONDARY_SMELT']) && $row_data['SECONDARY_SMELT'] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                  
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="country_melt">COUNTRY OF MELT</label>
                                <select class="form-control" id="country_melt" name="country_melt" readonly>
                                    <option value="">-- COUNTRY OF MELT --</option>
                                    <?php
                                        $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['COUNTRY_MELT']) && $row_data['COUNTRY_MELT'] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="country_origin">COUNTRY OF ORIGIN</label>
                                <select class="form-control" id="country_origin" name="country_origin" readonly>
                                    <option value="">-- COUNTRY OF ORIGIN --</option>
                                    <?php
                                        $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.SEQ_NO ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['COUNTRY_ORIGIN']) && $row_data['COUNTRY_ORIGIN'] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="mi_data">MATERIAL IN</label>
                                <select class="form-control" id="mi_data" name="mi_data">
                                    <option value="">-- Material In --</option>
                                    <?php
                                        $sql = "SELECT BOI_PROJECT FROM BOIPRJCT1 ORDER BY BOI_PROJECT ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['MATERIAL_IN']) && $row_data['MATERIAL_IN'] == $row["BOI_PROJECT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["BOI_PROJECT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["BOI_PROJECT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="lc_data">LOCATION ID</label>
                                <select class="form-control" id="lc_data" name="lc_data">
                                    <option value="">-- Location --</option>
                                    <?php
                                        $sql = "SELECT LOCATION_ID, DESCRIPTION FROM LCTNMSTR1 ORDER BY LOCATION_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['LOCATION_ID']) && $row_data['LOCATION_ID'] == $row["LOCATION_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["LOCATION_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["LOCATION_ID"], ENT_QUOTES, 'UTF-8')." : ".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="cw_actual_data">ACTUAL WEIGHT (Kg)</label>
                                <input type="number" step="any" class="form-control" id="cw_actual_data" name="cw_actual_data" value="<?php echo htmlspecialchars($row_data['COIL_ACTUALWEIGHT'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>"/>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="start_date_data">START DATE - TIME</label>
                                <input type="datetime-local" class="form-control" id="start_date_data" name="start_date_data" value="<?php echo fmt_dt($row_data['COIL_STARTTIME'] ?? ''); ?>"/>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="stop_date_data">END DATE - TIME</label>
                                <input type="datetime-local" class="form-control" id="stop_date_data" name="stop_date_data" value="<?php echo fmt_dt($row_data['COIL_ENDTIME'] ?? ''); ?>"/>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 2: Specification -->
                <?php
                // ตรวจสอบเงื่อนไข COIL_STATUS (ถ้าไม่เท่ากับ 'OP' ให้ปิดการแก้ไข Group 2)
                $coil_status = $row_data['COIL_STATUS'] ?? '';
                $is_g2_readonly = ($coil_status !== 'OP') ? 'readonly style="background-color: #f1f5f9;"' : '';
                $is_g2_disabled = ($coil_status !== 'OP') ? 'disabled style="background-color: #f1f5f9;"' : '';
                ?>

                <!-- GROUP 2: Specification -->
                <div class="form-card">
                    <h4 class="card-title-g2">⚙️ Group 2: Specification</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="ay_data">ALLOY</label>
                                <select class="form-control" id="ay_data" name="ay_data" <?php echo $is_g2_disabled; ?>>
                                    <option value="">-- Alloy --</option>
                                    <?php
                                        $sql = "SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['ALLOY']) && $row_data['ALLOY'] == $row["ALLOY"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALLOY"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                                <?php if ($coil_status !== 'OP'): ?>
                                    <!-- ส่งค่าเดิมไปกับฟอร์มกรณีที่ SELECT ถูก Disabled -->
                                    <input type="hidden" name="ay_data" value="<?php echo htmlspecialchars($row_data['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="tm_data">TEMPER</label>
                                <select class="form-control" id="tm_data" name="tm_data" <?php echo $is_g2_disabled; ?>>
                                    <option value="">-- TEMPER --</option>
                                    <?php
                                        $sql = "SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['TEMPER']) && $row_data['TEMPER'] == $row["TEMPER"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["TEMPER"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["TEMPER"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                                <?php if ($coil_status !== 'OP'): ?>
                                    <input type="hidden" name="tm_data" value="<?php echo htmlspecialchars($row_data['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="sf_data">SURFACE GRADE</label>
                                <select class="form-control" id="sf_data" name="sf_data" <?php echo $is_g2_disabled; ?>>
                                    <option value="">-- SURFACE GRADE --</option>
                                    <?php
                                        $sql = "SELECT SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['SURFACE_GRADE']) && $row_data['SURFACE_GRADE'] == $row["SURFACE_GRADE"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["SURFACE_GRADE"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["SURFACE_GRADE"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                                <?php if ($coil_status !== 'OP'): ?>
                                    <input type="hidden" name="sf_data" value="<?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="mg_data">METALLURGICAL GRADE</label>
                                <select class="form-control" id="mg_data" name="mg_data" <?php echo $is_g2_disabled; ?>>
                                    <option value="">-- METALLURGICAL GRADE --</option>
                                    <?php
                                        $sql = "SELECT METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($row_data['METALLURGICAL_GRADE']) && $row_data['METALLURGICAL_GRADE'] == $row["METALLURGICAL_GRADE"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["METALLURGICAL_GRADE"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["METALLURGICAL_GRADE"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>
                                <?php if ($coil_status !== 'OP'): ?>
                                    <input type="hidden" name="mg_data" value="<?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- THICKNESS -->
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="tn_data">THICKNESS (mm)</label>
                                <input type="number" step="any" class="form-control" id="tn_data" name="tn_data" 
                                    value="<?php echo (isset($row_data['THICKNESS']) && $row_data['THICKNESS'] !== '') ? htmlspecialchars(fmt2($row_data['THICKNESS']), ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                    <?php echo $is_g2_readonly; ?>/>
                            </div>
                        </div>

                        <!-- WIDTH -->
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="w_data">WIDTH (mm)</label>
                                <input type="number" step="any" class="form-control" id="w_data" name="w_data" 
                                    value="<?php echo (isset($row_data['WIDTH']) && $row_data['WIDTH'] !== '') ? htmlspecialchars(fmt2($row_data['WIDTH']), ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                    <?php echo $is_g2_readonly; ?>/>
                            </div>
                        </div>

                        <!-- ACTUAL WIDTH -->
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="aw_data">ACTUAL WIDTH (mm)</label>
                                <input type="number" step="any" class="form-control" id="aw_data" name="aw_data" 
                                    value="<?php echo (isset($row_data['ACTUAL_WIDTH']) && $row_data['ACTUAL_WIDTH'] !== '') ? htmlspecialchars(fmt2($row_data['ACTUAL_WIDTH']), ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                    <?php echo $is_g2_readonly; ?>/>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 3: Edge Curl & Calculate (ปรับแก้ระยะห่างและไวยากรณ์ PHP) -->
                <div class="form-card">
                    <h4 class="card-title-g3">📍 Group 3: Edge Curl & Calculate</h4>
                    
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group" style="margin-bottom: 5px;">
                                <label>EDGE CURL MEASUREMENTS</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edge Curl Items จัดโครงสร้าง Grid แบบเดียวกับ Group 2 -->
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="form-group text-center">
                                <span class="edge-label-badge" style="display:block; margin-bottom: 6px;">EDGE CURL A</span>
                                <input class="form-control text-center" name="ec_a" type="number" step="any" value="<?php echo isset($row_data['COIL_EDGECURLA']) ? htmlspecialchars(fmt2($row_data['COIL_EDGECURLA']), ENT_QUOTES, 'UTF-8') : '0'; ?>"/> 
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="form-group text-center">
                                <span class="edge-label-badge" style="display:block; margin-bottom: 6px;">EDGE CURL B</span>
                                <input class="form-control text-center" name="ec_b" type="number" step="any" value="<?php echo isset($row_data['COIL_EDGECURLB']) ? htmlspecialchars(fmt2($row_data['COIL_EDGECURLB']), ENT_QUOTES, 'UTF-8') : '0'; ?>"/> 
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="form-group text-center">
                                <span class="edge-label-badge" style="display:block; margin-bottom: 6px;">EDGE CURL C</span>
                                <input class="form-control text-center" name="ec_c" type="number" step="any" value="<?php echo isset($row_data['COIL_EDGECURLC']) ? htmlspecialchars(fmt2($row_data['COIL_EDGECURLC']), ENT_QUOTES, 'UTF-8') : '0'; ?>"/> 
                            </div>
                        </div>
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="form-group text-center">
                                <span class="edge-label-badge" style="display:block; margin-bottom: 6px;">EDGE CURL D</span>
                                <input class="form-control text-center" name="ec_d" type="number" step="any" value="<?php echo isset($row_data['COIL_EDGECURLD']) ? htmlspecialchars(fmt2($row_data['COIL_EDGECURLD']), ENT_QUOTES, 'UTF-8') : '0'; ?>"/> 
                            </div>
                        </div>                            
                        <div class="col-md-2 col-sm-4 col-xs-6">
                            <div class="form-group text-center">
                                <span class="edge-label-badge" style="display:block; margin-bottom: 6px;">EDGE CURL E</span>
                                <input class="form-control text-center" name="ec_e" type="number" step="any" value="<?php echo isset($row_data['COIL_EDGECURLE']) ? htmlspecialchars(fmt2($row_data['COIL_EDGECURLE']), ENT_QUOTES, 'UTF-8') : '0'; ?>"/> 
                            </div>
                        </div>
                    </div>

                    <!-- TI Section -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="total_ti">TOTAL TI</label>
                                <input type="number" step="any" class="form-control" id="total_ti" name="total_ti" 
                                    value="<?php echo (isset($row_data['TOTAL_TI']) && $row_data['TOTAL_TI'] !== '') ? htmlspecialchars(fmt2($row_data['TOTAL_TI']), ENT_QUOTES, 'UTF-8') : '0.0000'; ?>" 
                                    onchange="calculateDeltaTi()"/>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="in_ti">IN TI</label>
                                <input type="number" step="any" class="form-control" id="in_ti" name="in_ti" 
                                    value="<?php echo (isset($row_data['IN_TI']) && $row_data['IN_TI'] !== '') ? htmlspecialchars(fmt2($row_data['IN_TI']), ENT_QUOTES, 'UTF-8') : '0.0000'; ?>" 
                                    onchange="calculateDeltaTi()"/>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="form-group">
                                <label for="delta_ti">DELTA TI</label>
                                <!-- เพิ่ม readonly เพื่อห้ามแก้ไขค่าตรงๆ -->
                                <input type="text" class="form-control" id="delta_ti" name="delta_ti" 
                                    value="<?php echo (isset($row_data['DELTA_TI']) && $row_data['DELTA_TI'] !== '') ? htmlspecialchars(fmt2($row_data['DELTA_TI']), ENT_QUOTES, 'UTF-8') : '0.0000'; ?>" 
                                    readonly style="background-color: #e2e8f0;"/>
                            </div>
                        </div>
                    </div>

                <div class="row" style="margin-bottom: 40px;">
                    <div class="col-xs-12 text-right">
                        <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                        <button type="button" class="btn btn-custom-cancel" style="margin-right: 12px;" onclick="window.location.assign('coil_production_mats.php?func=<?php echo urlencode($folder_func); ?>')">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-custom-submit" onclick="submitCasterData()">
                            💾 Save Data
                        </button>
                    </div>
                </div>

            </form>    
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
function submitCasterData() {
    var cn_data = document.getElementById('cn_data') ? document.getElementById('cn_data').value : '';
    var ay_data = document.getElementById('ay_data') ? document.getElementById('ay_data').value : '';
    var data_fun = document.getElementById('func') ? document.getElementById('func').value : '';

    if (!cn_data || cn_data.trim() === "" || !ay_data || ay_data.trim() === "") {
        alert("Please fill in the COIL NO and ALLOY codes completely.");
        return;
    }

    var formData = $("#form_caster").serialize();

    $.post("model/save_ecm_system_mats.php", formData, function(resp) {
        if (resp.message === true) {
            alert("Save data has been successfully.");
            window.location.assign('coil_production_mats.php?func=' + encodeURIComponent(data_fun));
        } else {
            alert("An error occurred : " + (resp.error || "Please double-check the information."));
        }
    }, 'json').fail(function() {
        alert("Unable to connect to the server. Please try again.");
    });
}

function calculateDeltaTi() {
    var totalTiElem = document.getElementById('total_ti');
    var inTiElem = document.getElementById('in_ti');
    var deltaTiElem = document.getElementById('delta_ti');

    var totalTiVal = totalTiElem.value.trim();
    var inTiVal = inTiElem.value.trim();

    // กรณี Len(TotalTi) = 0
    if (totalTiVal === "") {
        totalTiElem.value = "0.0000";
        deltaTiElem.value = "0.0000";
        return;
    }

    var totalTi = parseFloat(totalTiVal);
    var inTi = parseFloat(inTiVal) || 0;

    // กรณี TotalTi >= 1
    if (totalTi >= 1) {
        totalTiElem.value = "0.0000";
        deltaTiElem.value = "0.0000";
        return;
    }

    // กรณี TotalTi >= InTi
    if (totalTi >= inTi) {
        var delta = totalTi - inTi;
        deltaTiElem.value = delta.toFixed(4); // แปลงเป็นทศนิยม 4 ตำแหน่ง
    } else {
        // กรณี TotalTi < InTi
        alert("TOTAL TI must be greater than or equal to IN TI");
        totalTiElem.value = "0.0000";
        deltaTiElem.value = "0.0000";
        inTiElem.value = "0.0000";
        totalTiElem.focus(); // ย้าย Focus กลับไปยังช่อง TOTAL TI
    }
}

// เรียกคำนวณ 1 ครั้งเมื่อโหลดหน้าเพื่ออัปเดตค่าเริ่มต้น
document.addEventListener("DOMContentLoaded", function() {
    calculateDeltaTi();
});
</script>
</html>