<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$recipe_no = isset($_GET['recipe_no']) ? trim($_GET['recipe_no']) : '';

// ดึงข้อมูล Header หลักตัวแรกขึ้นมาแสดงผลในการ์ดบน
$header = array();
if ($recipe_no !== '') {
    $h_sql = "SELECT TOP(1) RECIPE_NO, ALLOY, WIDTH, THICKNESS_ORIGINAL, THICKNESS_FINAL, COOLANT_RECIPE, SPOOL, TEMPER_FINISH 
              FROM CMRCMSTR1 
              WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))";
    $h_stmt = $conn->prepare($h_sql);
    $h_stmt->execute([':rno' => $recipe_no]);
    $header = $h_stmt->fetch(PDO::FETCH_ASSOC);
}

$v_rno   = $header['RECIPE_NO'] ?? $recipe_no;
$v_al    = $header['ALLOY'] ?? '-';
$v_wi    = isset($header['WIDTH']) ? number_format((float)$header['WIDTH'], 2) : '-';
$v_th_or = isset($header['THICKNESS_ORIGINAL']) ? number_format((float)$header['THICKNESS_ORIGINAL'], 2) : '-';
$v_th_fn = isset($header['THICKNESS_FINAL']) ? number_format((float)$header['THICKNESS_FINAL'], 2) : '-';
$v_tm_fn = $header['TEMPER_FINISH'] ?? '-';
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

        .table-responsive-custom {
            width: 100%;
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
        }

        .table-detail {
            width: 100%;
            min-width: 1200px;
            margin-bottom: 0 !important;
        }

        .table-detail th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-align: center;
            vertical-align: middle !important;
            white-space: nowrap;
            padding: 10px 8px !important;
        }
        .table-detail td {
            vertical-align: middle !important;
            font-size: 13px;
            white-space: nowrap;
            padding: 8px 8px !important;
        }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'gp3'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Cold Rolling Recipe Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <!-- การ์ดแสดง Header Information (ข้อมูลหลักที่ไม่ซ้ำ) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📜 Recipe Information</h4>
                        <div class="row">                      

                            <div class="col-md-4 col-sm-6">
                                <div class="info-label">RECIPE NO</div>
                                <div class="info-value" style="color:#0f172a;"><?php echo htmlspecialchars($v_rno, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">ALLOY</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_al, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">WIDTH</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_wi, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>    
                            
                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">THICKNESS ORIGINAL</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_th_or, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">THICKNESS FINAL</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_th_fn, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>   

                        </div>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายการรายละเอียด Recipe ย่อย (ตัดคอลัมน์ที่ซ้ำกับ Header ออก และจัดทศนิยม 2 ตำแหน่ง) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📊 Recipe Details</h4>
                        
                        <div class="table-responsive-custom">
                            <table class="table table-bordered table-striped table-detail">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ITEM</th>
                                        <th style="width: 120px;">TEMPER FINISH</th>
                                        <th style="width: 140px;">THICKNESS ENTRY</th>
                                        <th style="width: 140px;">THICKNESS EXIT</th>
                                        <th style="width: 150px;">THICKNESS TOLERANCE</th>
                                        <th style="width: 140px;">WORK HARDENING 1</th>
                                        <th style="width: 140px;">WORK HARDENING 2</th>
                                        <th style="width: 140px;">COEFF. FRICTION</th>
                                        <th style="width: 130px;">YIELD STRESS</th>
                                        <th style="width: 140px;">EMPIRICAL VAL 1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $detail_sql = "SELECT c.RECIPE_ITEM, c.TEMPER_FINISH, c.THICKNESS_ENTRY, c.THICKNESS_EXIT, 
                                                          c.THICKNESS_TELORANCE, c.WORK_HARDENING1, c.WORK_HARDENING2, 
                                                          c.COEFFICIENT_FRICTION, c.YIELD_STRESS, c.EMPIRICAL_VALUE1 
                                                   FROM CMRCMSTR1 c
                                                   WHERE LTRIM(RTRIM(c.RECIPE_NO)) = LTRIM(RTRIM(:rno))
                                                   ORDER BY c.RECIPE_ITEM ASC";

                                    $stmt = $conn->prepare($detail_sql);
                                    $stmt->execute([':rno' => $recipe_no]);

                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        ?>
                                        <tr>
                                            <td align="center"><span class="badge" style="background-color:#2563eb; font-size:13px;"><?php echo htmlspecialchars($row['RECIPE_ITEM'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td align="center" style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($row['TEMPER_FINISH'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="right"><?php echo is_numeric($row['THICKNESS_ENTRY']) ? number_format((float)$row['THICKNESS_ENTRY'], 2) : '-'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['THICKNESS_EXIT']) ? number_format((float)$row['THICKNESS_EXIT'], 2) : '-'; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['THICKNESS_TELORANCE'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="right"><?php echo is_numeric($row['WORK_HARDENING1']) ? number_format((float)$row['WORK_HARDENING1'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['WORK_HARDENING2']) ? number_format((float)$row['WORK_HARDENING2'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['COEFFICIENT_FRICTION']) ? number_format((float)$row['COEFFICIENT_FRICTION'], 2) : '0.00'; ?></td>
                                            <td align="right" style="font-weight:600; color:#059669;"><?php echo is_numeric($row['YIELD_STRESS']) ? number_format((float)$row['YIELD_STRESS'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['EMPIRICAL_VALUE1']) ? number_format((float)$row['EMPIRICAL_VALUE1'], 2) : '0.00'; ?></td>
                                        </tr>
                                        <?php
                                    }

                                    if(!$has_row) {
                                        echo "<tr><td colspan='10' align='center' style='color:#94a3b8; padding:30px;'>No recipe details were found for this item.</td></tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">📊 Roll Set Number Details</h4>
                        
                        <div class="table-responsive-custom">
                            <table class="table table-bordered table-striped table-detail">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ITEM</th>
                                        <th style="width: 120px;">Roll No</th>
                                        <th style="width: 140px;">Roll Ref No</th>
                                        <th style="width: 140px;">WRL RADIUS</th>
                                        <th style="width: 150px;">BRL RADIUS</th>
                                        <th style="width: 140px;">TWR CAMBER</th>
                                        <th style="width: 140px;">BWR CAMBER</th>
                                        <th style="width: 140px;">RSET STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $Item_roll = 1;
                                    $roll_sql = "SELECT r.RSET_NO,r.RSET_REFERENCE,r.WRL_RADIUS,r.BRL_RADIUS,r.TWR_CAMBER,r.BWR_CAMBER,r.RSET_STATUS 
                                                   FROM RDMTMSTR3 as r
                                                   Where r.RSET_STATUS ='OP'";

                                    $stmt = $conn->prepare($roll_sql);
                                    $stmt->execute();

                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        ?>
                                        <tr>
                                            <td align="center"><span class="badge" style="background-color:#2563eb; font-size:13px;"><?php echo htmlspecialchars($Item_roll ?? '0', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td align="center" style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($row['RSET_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['RSET_REFERENCE'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="right"><?php echo is_numeric($row['WRL_RADIUS']) ? number_format((float)$row['WRL_RADIUS'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['BRL_RADIUS']) ? number_format((float)$row['BRL_RADIUS'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['TWR_CAMBER']) ? number_format((float)$row['TWR_CAMBER'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['BWR_CAMBER']) ? number_format((float)$row['BWR_CAMBER'], 2) : '0.00'; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['RSET_STATUS'] ?? '0', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <?php
                                    }

                                    if(!$has_row) {
                                        echo "<tr><td colspan='10' align='center' style='color:#94a3b8; padding:30px;'>No ROLL SET NO details were found for this item.</td></tr>";
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