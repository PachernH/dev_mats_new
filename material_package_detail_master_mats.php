<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['IDM']) ? trim($_GET['IDM']) : '';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
if ($cs !== '') {
    $data_cs = RT_Material_Package($cs);
}

// กำหนดตัวแปรจาก DB (รองรับทั้ง Associative Array และ Index Array)
$val_ma_code   = isset($data_cs['MATERIAL_CODE']) ? $data_cs['MATERIAL_CODE'] : (isset($data_cs[0]) ? $data_cs[0] : '-');
$val_ma_it     = isset($data_cs['ITEMNUM']) ? $data_cs['ITEMNUM'] : (isset($data_cs[1]) ? $data_cs[1] : '-');
$val_ma_dec    = isset($data_cs['DESCRIPTION']) ? $data_cs['DESCRIPTION'] : (isset($data_cs[2]) ? $data_cs[2] : '-');
$val_ma_ty     = isset($data_cs['MATERIAL_TYPE']) ? $data_cs['MATERIAL_TYPE'] : (isset($data_cs[3]) ? $data_cs[3] : '-');
$val_ma_qty    = isset($data_cs['QTY_ONHAND']) ? $data_cs['QTY_ONHAND'] : (isset($data_cs[4]) ? $data_cs[4] : '0');

$val_pkg_type  = isset($data_cs['PACKAGE_TYPE']) ? $data_cs['PACKAGE_TYPE'] : '-';
$val_treatment = isset($data_cs['PACKAGE_TREATMENT']) ? $data_cs['PACKAGE_TREATMENT'] : '-';

$val_dim_w_in  = isset($data_cs['WIDTH_INCH']) ? $data_cs['WIDTH_INCH'] : 0;
$val_dim_l_in  = isset($data_cs['LENGTH_INCH']) ? $data_cs['LENGTH_INCH'] : 0;
$val_dim_h_in  = isset($data_cs['HIGH_INCH']) ? $data_cs['HIGH_INCH'] : 0;

$val_dim_w_mm  = isset($data_cs['WIDTH_MM']) ? $data_cs['WIDTH_MM'] : 0;
$val_dim_l_mm  = isset($data_cs['LENGTH_MM']) ? $data_cs['LENGTH_MM'] : 0;
$val_dim_h_mm  = isset($data_cs['HIGH_MM']) ? $data_cs['HIGH_MM'] : 0;

$val_w_plus    = isset($data_cs['WIDTH_PLUS']) ? $data_cs['WIDTH_PLUS'] : 0;
$val_w_minus   = isset($data_cs['WIDTH_MINUS']) ? $data_cs['WIDTH_MINUS'] : 0;
$val_l_plus    = isset($data_cs['LENGTH_PLUS']) ? $data_cs['LENGTH_PLUS'] : 0;
$val_l_minus   = isset($data_cs['LENGTH_MINUS']) ? $data_cs['LENGTH_MINUS'] : 0;

$val_pkg_weight = isset($data_cs['PACKAGE_WEIGHT']) ? $data_cs['PACKAGE_WEIGHT'] : 0;
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
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
        }
        
        .info-label { font-weight: 600; color: #64748b; margin-bottom: 4px; font-size: 13px; }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 42px;
            padding: 10px 14px;
            font-size: 14px;
            color: #0f172a;
            font-weight: 600;
            word-break: break-word;
            margin-bottom: 15px;
        }
        
        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover { background-color: #f1f5f9; color: #1e293b; }
        .mb-4 { margin-bottom: 1.5rem; }

        .badge-type {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            display: inline-block;
        }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'gp4'; ?>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Material Package Master Data Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('material_package_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📋 Material Package Master Data Info</h4>
                        <div class="row">                      

                            <div class="col-md-6">
                                <div class="info-label">MATERIAL CODE</div>
                                <div class="info-value" style="font-weight: 700; color: #2563eb; font-size: 16px;">
                                    <?php echo htmlspecialchars($val_ma_code, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">ITEMNUM MP2</div>
                                <div class="info-value" style="color: #0284c7; font-weight: 700;">
                                    <?php echo htmlspecialchars($val_ma_it, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>    
                            
                            <div class="col-md-6">
                                <div class="info-label">DESCRIPTION</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($val_ma_dec, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">MATERIAL TYPE</div>
                                <div class="info-value">
                                    <span class="badge-type"><?php echo htmlspecialchars($val_ma_ty, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>   

                            <div class="col-md-3">
                                <div class="info-label">QTY ONHAND</div>
                                <div class="info-value" style="color: #059669; font-weight: 700;">
                                    <?php echo htmlspecialchars($val_ma_qty, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                             

                        </div>
                    </div>

                    <!-- ส่วนข้อมูลรายละเอียด มิติขนาด (Dimension & Specification) -->
                    <div class="display-card">
                        <h4 class="card-title-sub">📏 Dimension & Specification Details</h4>
                        <div class="row">

                            <div class="col-md-3">
                                <div class="info-label">Package Type</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($val_pkg_type, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-label">Treatment</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($val_treatment, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Package Weight (Kg)</div>
                                <div class="info-value" style="color: #d97706;">
                                    <?php echo htmlspecialchars($val_pkg_weight, ENT_QUOTES, 'UTF-8'); ?> Kg
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Dimension (Inch) [W x L x H]</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($val_dim_w_in . " x " . $val_dim_l_in . " x " . $val_dim_h_in, ENT_QUOTES, 'UTF-8'); ?> Inch
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Dimension (MM) [W x L x H]</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($val_dim_w_mm . " x " . $val_dim_l_mm . " x " . $val_dim_h_mm, ENT_QUOTES, 'UTF-8'); ?> mm
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Width Tolerance (+ / -)</div>
                                <div class="info-value">
                                    +<?php echo htmlspecialchars($val_w_plus, ENT_QUOTES, 'UTF-8'); ?>% / -<?php echo htmlspecialchars($val_w_minus, ENT_QUOTES, 'UTF-8'); ?>%
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Length Tolerance (+ / -)</div>
                                <div class="info-value">
                                    +<?php echo htmlspecialchars($val_l_plus, ENT_QUOTES, 'UTF-8'); ?>% / -<?php echo htmlspecialchars($val_l_minus, ENT_QUOTES, 'UTF-8'); ?>%
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>
</html>