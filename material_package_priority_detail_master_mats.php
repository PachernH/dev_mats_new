<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$refdata = isset($_GET['refdata']) ? trim($_GET['refdata']) : '';
$keys = explode('*', $refdata);

// รับค่าหัวข้อหลัก 6 ตัว
$product_id        = isset($keys[0]) ? trim($keys[0]) : '';
$package_treatment = isset($keys[1]) ? trim($keys[1]) : '';
$width_from        = isset($keys[2]) ? trim($keys[2]) : '';
$width_to          = isset($keys[3]) ? trim($keys[3]) : '';
$length_from       = isset($keys[4]) ? trim($keys[4]) : '';
$length_to         = isset($keys[5]) ? trim($keys[5]) : '';

?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
        }
        
        .info-label { font-weight: 600; color: #64748b; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 40px;
            padding: 8px 12px;
            font-size: 15px;
            color: #1d4ed8;
            font-weight: 700;
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

        .table-detail th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            vertical-align: middle !important;
        }
        .table-detail td {
            vertical-align: middle !important;
            font-size: 14px;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Material Package Priority Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('material_package_priority_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <!-- การ์ดแสดง 6 ข้อมูลหลัก -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📦 Material Package Header Information</h4>
                        <div class="row">                      

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">PRODUCT ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($product_id !== '' ? $product_id : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">PACKAGE TREATMENT</div>
                                <div class="info-value"><?php echo htmlspecialchars($package_treatment !== '' ? $package_treatment : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>    
                            
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WIDTH FROM</div>
                                <div class="info-value"><?php echo htmlspecialchars($width_from !== '' ? $width_from : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WIDTH TO</div>
                                <div class="info-value"><?php echo htmlspecialchars($width_to !== '' ? $width_to : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>   

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">LENGTH FROM</div>
                                <div class="info-value"><?php echo htmlspecialchars($length_from !== '' ? $length_from : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">LENGTH TO</div>
                                <div class="info-value"><?php echo htmlspecialchars($length_to !== '' ? $length_to : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายการลูก เรียงตาม PACKAGE_PRIORITY -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📊 Priority Items Breakdown (PACKAGE PRIORITY)</h4>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-detail">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">NO.</th>
                                        <th style="width: 160px;">PACKAGE PRIORITY</th>
                                        <th>MATERIAL CODE</th>
                                        <th style="width: 140px;">STACK ROW</th>
                                        <th style="width: 140px;">STACK COLUMN</th>
                                        <th style="width: 200px;">WEIGHT PER PACKAGE (KG)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $detail_sql = "SELECT PACKAGE_PRIORITY, MATERIAL_CODE, STACK_ROW, STACK_COLUMN, WEIGHTPERPACKAGE 
                                                   FROM MTRLSPCT2 
                                                   WHERE LTRIM(RTRIM(PRODUCT_ID))        = LTRIM(RTRIM(:pid))
                                                     AND LTRIM(RTRIM(PACKAGE_TREATMENT)) = LTRIM(RTRIM(:pkg))
                                                     AND LTRIM(RTRIM(WIDTH_FROM))        = LTRIM(RTRIM(:wf))
                                                     AND LTRIM(RTRIM(WIDTH_TO))          = LTRIM(RTRIM(:wt))
                                                     AND LTRIM(RTRIM(LENGTH_FROM))       = LTRIM(RTRIM(:lf))
                                                     AND LTRIM(RTRIM(LENGTH_TO))         = LTRIM(RTRIM(:lt))
                                                   ORDER BY PACKAGE_PRIORITY ASC";

                                    $stmt = $conn->prepare($detail_sql);
                                    $stmt->execute([
                                        ':pid' => $product_id,
                                        ':pkg' => $package_treatment,
                                        ':wf'  => $width_from,
                                        ':wt'  => $width_to,
                                        ':lf'  => $length_from,
                                        ':lt'  => $length_to
                                    ]);

                                    $cnt = 0;
                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        $cnt++;
                                        ?>
                                        <tr>
                                            <td align="center" style="color:#64748b;"><?php echo $cnt; ?></td>
                                            <td align="center"><span class="badge" style="background-color:#2563eb; font-size:14px;"><?php echo htmlspecialchars($row['PACKAGE_PRIORITY'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td style="font-weight: 600; color:#1e293b;"><?php echo htmlspecialchars($row['MATERIAL_CODE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['STACK_ROW'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['STACK_COLUMN'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="right" style="font-weight: 600; color:#059669;"><?php echo number_format((float)($row['WEIGHTPERPACKAGE'] ?? 0), 0); ?></td>
                                        </tr>
                                        <?php
                                    }

                                    if(!$has_row) {
                                        echo "<tr><td colspan='6' align='center' style='color:#94a3b8; padding:30px;'>No sub-priority information was found for this item.</td></tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
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