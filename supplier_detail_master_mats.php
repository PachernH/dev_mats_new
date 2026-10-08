<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');

if(!isset($_GET['CID'])){
    $cs = '';
} else {
    $cs = htmlspecialchars(trim($_GET['CID']), ENT_QUOTES, 'UTF-8');
}

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
$data_cs = RT_Supp($cs);
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        /* Modern UI Style Adjustments (สไตล์ชุดเดียวกับหน้า Furnace Charging) */
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { 
            padding: 20px 20px !important; 
        }
        
        /* Form & Display Card Styling */
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
        
        /* Data Group Presentation */
        .info-label {
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
            font-size: 13px;
        }
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
        .highlight-id {
            background-color: #eff6ff !important;
            border-color: #bfdbfe !important;
            color: #1e40af !important;
            font-weight: 700 !important;
        }
        
        /* Buttons */
        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        
        /* Utility */
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'customer_supplier_master';?>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Supplier Master Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('supplier_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <form id="form_cust_supp">    
                    
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Company Profile & Identity</h4>
                        <div class="row">

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Company ID</div>
                                <div class="info-value highlight-id">
                                    <?php echo htmlspecialchars($data_cs[0] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Account Code</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Tax Registration</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[2] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ประเภทภาษีเงินได้หัก ณ ที่จ่าย (ภงด)</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[3] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                            

                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[4] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[5] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[6] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>    
                            
                            <div class="col-md-12">
                                <div class="info-label">ชื่อบริษัท</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[7] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">ที่อยู่</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[8] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[9] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   

                        </div>
                    </div>

                    
                </div>


                <div class="col-lg-5 col-md-12">
                    
                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Notify Information</h4>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">คำนำหน้าชื่อ</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[11] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">สาขา</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[10] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                             
                            
                            <div class="col-md-12">
                                <div class="info-label">ชื่อบริษัท</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[12] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>  

                            <div class="col-md-12">
                                <div class="info-label">หมู่บ้าน/อาคาร/นิคม</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[13] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>          


                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ห้องที่</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[14] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ชั้นที่</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[15] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                              

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">เลขที่</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[16] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">หมู่ที่</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[17] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ตรอก/ซอย</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[18] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ถนน</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[19] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">ตำบล/แขวง</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[20] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">อำเภอ/เขต</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[21] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                 
                            
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">จังหวัด</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[22] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">รหัสไปรษณีย์</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[23] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                                

                        </div>
                    </div>

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

</script>
</html>