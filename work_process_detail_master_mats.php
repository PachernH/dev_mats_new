<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['CIW']) ? trim($_GET['CIW']) : '';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
if ($cs !== '') {
    $data_cs = RT_Workprocess($cs);
}

// ตรวจสอบข้อมูลจากฟังก์ชัน (เช็คทั้งแบบ Index และ ชื่อคอลัมน์)
$v_work_process = (isset($data_cs['WORK_PROCESS']) && $data_cs['WORK_PROCESS'] !== '') ? $data_cs['WORK_PROCESS'] : ((isset($data_cs[0]) && $data_cs[0] !== '') ? $data_cs[0] : '-');
$v_product_id   = (isset($data_cs['PRODUCT_ID']) && $data_cs['PRODUCT_ID'] !== '') ? $data_cs['PRODUCT_ID'] : ((isset($data_cs[1]) && $data_cs[1] !== '') ? $data_cs[1] : '-');
$v_work_type    = (isset($data_cs['WORK_TYPE']) && $data_cs['WORK_TYPE'] !== '') ? $data_cs['WORK_TYPE'] : ((isset($data_cs[2]) && $data_cs[2] !== '') ? $data_cs[2] : '-');
$v_description  = (isset($data_cs['DESCRIPTION']) && $data_cs['DESCRIPTION'] !== '') ? $data_cs['DESCRIPTION'] : ((isset($data_cs[3]) && $data_cs[3] !== '') ? $data_cs[3] : '-');
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
        
        .info-label { font-weight: 500; color: #64748b; margin-bottom: 4px; font-size: 13px; }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 42px;
            padding: 10px 14px;
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Work Process Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('work_process_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Work Process Master Details</h4>
                        <div class="row">                      

                            <div class="col-md-12">
                                <div class="info-label">WORK PROCESS</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7;">
                                    <?php echo htmlspecialchars($v_work_process, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">PRODUCT ID</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($v_product_id, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>    
                            
                            <div class="col-md-12">
                                <div class="info-label">WORK TYPE</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7;">
                                    <?php echo htmlspecialchars($v_work_type, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">DESCRIPTION</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($v_description, ENT_QUOTES, 'UTF-8'); ?>
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