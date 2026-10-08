<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า BATCH_NO จาก URL (ใช้พารามิเตอร์ search_no)
$batch_no = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$batch_data = null;

if (!empty($batch_no)) {
    // Query ดึงข้อมูลจากตาราง FRNCPRCS1 ตาม BATCH_NO
    $sql_batch = "SELECT BATCH_NO, BATCH_DATE, LINE_PROCESS, PRIMARY_SMELT, SECONDARY_SMELT, MATERIAL_IN, ALLOY, AL_INGOT,
                         COIL_REMELT, CAST_SCRAP, OTHER_SCRAP, SCRAP_WIRE, RECYCLE_SCRAP, MASTER_ALLOY, TOTAL_CHARGE, TOTAL_DROSS,
                         BALANCE_BATCH
                  FROM FRNCPRCS1
                  WHERE BATCH_NO = :batch_no";
            
    $stmt_batch = $conn->prepare($sql_batch);
    $stmt_batch->bindParam(':batch_no', $batch_no, PDO::PARAM_STR);
    $stmt_batch->execute();
    $batch_data = $stmt_batch->fetch(PDO::FETCH_ASSOC);
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

        .dashboard-card {
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
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .info-label { 
            font-size: 11px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value-box { 
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            min-height: 38px;
            margin-bottom: 16px; 
        }
        .info-value-text {
            font-size: 14px; 
            font-weight: 600; 
            color: #0f172a; 
            word-break: break-all;
        }
        .info-unit {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-left: 6px;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'HI';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_coil_log_mats.php?func=<?php echo $folder_func ?>">Coil Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Batch Details: <span style="color:#2563eb;"><a href="history_coil_log_mats.php?func=<?php echo $folder_func ?>&search_no=<?php echo htmlspecialchars($batch_no); ?>"><?php echo htmlspecialchars($batch_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$batch_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Batch No: <strong><?php echo htmlspecialchars($batch_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- Furnace Melting Batch Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">🔥 Furnace Melting Batch Information</h4>

                    <!-- 1. Batch No. -->
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="info-label">BATCH NO.</div>
                            <div class="info-value-box" style="background-color:#eff6ff; border-color:#bfdbfe;">
                                <span class="info-value-text" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($batch_data['BATCH_NO'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 5px 0 20px 0;">

                    <!-- 2. Materials & Charge Quantities -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">AL REMAIN BATCH</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['MATERIAL_IN'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">AL INGOT QTY.</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['AL_INGOT'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">COIL REMELT QTY.</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['COIL_REMELT'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">CASTING SCRAP QTY.</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['CAST_SCRAP'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">OTHER SCRAP QTY.</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['OTHER_SCRAP'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">SCRAP WIRE QTY. (TALON)</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['SCRAP_WIRE'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">RECYCLE SCRAP QTY. (TALON)</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['RECYCLE_SCRAP'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">MASTER ALLOY</div>
                            <div class="info-value-box">
                                <span class="info-value-text"><?php echo fmt2($batch_data['MASTER_ALLOY'] ?? 0); ?></span>
                                <span class="info-unit">Kg.</span>
                            </div>
                        </div>

                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">TOTAL CHARGE QTY.</div>
                            <div class="info-value-box" style="background-color:#f1f5f9; border-color:#cbd5e1;">
                                <span class="info-value-text" style="font-weight:700; color:#1e293b;"><?php echo fmt2($batch_data['TOTAL_CHARGE'] ?? 0); ?></span>
                                <span class="info-unit" style="font-weight:700;">Kg.</span>
                            </div>
                        </div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 5px 0 20px 0;">

                    <!-- 3. Total Dross & Balance Qty. -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">TOTAL DROSS QTY.</div>
                            <div class="info-value-box" style="background-color:#fef2f2; border-color:#fecaca;">
                                <span class="info-value-text" style="font-weight:700; color:#dc2626;"><?php echo fmt2($batch_data['TOTAL_DROSS'] ?? 0); ?></span>
                                <span class="info-unit" style="color:#dc2626;">Kg.</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">BALANCE QTY.</div>
                            <div class="info-value-box" style="background-color:#ecfdf5; border-color:#a7f3d0;">
                                <span class="info-value-text" style="font-weight:700; color:#059669;"><?php echo fmt2($batch_data['BALANCE_BATCH'] ?? 0); ?></span>
                                <span class="info-unit" style="color:#059669;">Kg.</span>
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
function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_coil_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>