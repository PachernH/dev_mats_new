<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL (รองรับทั้ง search_no และ JOB_ORDER)
$job_order = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : (isset($_GET['JOB_ORDER']) ? htmlspecialchars(trim($_GET['JOB_ORDER']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data = null;
$batch_list = [];

if (!empty($job_order)) {
    // 1 & 2. Query ดึงข้อมูล JOB Information & History Job Product จาก JOBORDER1
    $sql_job = "SELECT 
                    /*** JOB Information ***/
                    JOB_ORDER, JOB_STATUS, ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2,
                    SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, FLATNESS_GRADE, THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE,
                    CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, JOB_WORKPROCESS, JOBORDER_DATE, SCHEDULE_DATE,
                    JOB_RELEASEWEIGHT, JOB_RELEASEPIECE, JOB_MAXTOLERANCE, JOB_MINTOLERANCE, JOB_ACTUALWEIGHT, JOB_ACTUALPIECE, JOB_REMARK,
                    /*** History Job Product ***/
                    JOB_PRODUCEWEIGHT, JOB_PRODUCEPIECE, JOB_STRETCHERWEIGHT, JOB_STRETCHERPIECE, JOB_CUTSHEETWEIGHT, JOB_CUTSHEETPIECE,
                    JOB_SHEARWEIGHT, JOB_SHEARPIECE, JOB_PUNCHWEIGHT, JOB_PUNCHPIECE, JOB_BATCHANNEALWEIGHT, JOB_BATCHANNEALPIECE,
                    JOB_TRANSFERWEIGHT, JOB_TRANSFERPIECE, JOB_ANNEALWEIGHT, JOB_ANNEALPIECE, JOB_SORTWEIGHT, JOB_SORTPIECE, JOB_PACKWEIGHT, JOB_PACKPIECE,
                    JOB_STOCKWEIGHT, JOB_STOCKPIECE
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 3. Query ดึงข้อมูล Batch Annealing by Job Order จาก BTCHPROD0
    $sql_batch = "SELECT 
                    PRODUCT_NO, BTCH_NO, PALLET_ITEM, BTCH_STATUS, PRODUCT_ID, ALLOY, TEMPER_INITIAL, TEMPER_TARGET,
                    THICKNESS, WIDTH, LENGTH, BTCH_INPUTWEIGHT, BTCH_INPUTPIECE, 
                    BTCH_STARTDATE AS START_Date, BTCH_INPUTDATE AS OPEN_Date,
                    BTCH_PRODREMARK AS Product_Remark, BTCH_TECHREMARK AS Technical_Remark,
                    BTCH_OPERATOR AS OPERATOR, BTCH_UPDATE AS OPERATOR_UPDATE
                  FROM BTCHPROD0
                  WHERE JOB_ORDER = :job_order
                  ORDER BY BTCH_NO ASC, PALLET_ITEM ASC";

    $stmt_batch = $conn->prepare($sql_batch);
    $stmt_batch->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_batch->execute();
    $batch_list = $stmt_batch->fetchAll(PDO::FETCH_ASSOC);
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนชิ้น (Pcs) ป้องกัน Error
function format_pcs($value) {
    if (function_exists('fmt0')) {
        return fmt0($value);
    }
    return number_format((float)($value ?? 0), 0);
}

// ฟังก์ชันช่วยจัดรูปแบบน้ำหนัก (Weight 2 ทศนิยม)
function format_wt($value) {
    if (function_exists('fmt2')) {
        return fmt2($value);
    }
    return number_format((float)($value ?? 0), 2);
}

// ฟังก์ชันช่วยจัดรูปแบบขนาด (Thickness 3 ทศนิยม)
function format_dim($value) {
    if (function_exists('fmt3')) {
        return fmt3($value);
    }
    return number_format((float)($value ?? 0), 3);
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

        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .card-title-g3 { 
            color: #047857; 
            border-bottom: 2px solid #a7f3d0; 
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
        .info-value { 
            font-size: 14px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 16px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .table-responsive {
            border: none !important;
            margin-top: 10px;
        }
        .custom-job-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        .custom-job-table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Order Details: <span style="color:#2563eb;"><a href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $job_order ?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$job_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Job Order: <strong><?php echo htmlspecialchars($job_order); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- 1. JOB Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 1. JOB Information</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMP / FLATNESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?> / <?php echo htmlspecialchars($job_data['FLATNESS_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo format_dim($job_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo format_wt($job_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo format_wt($job_data['LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT / PIECE</div><div class="info-value"><?php echo format_dim($job_data['WEIGHT_PIECE']); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WIDTH</div><div class="info-value"><?php echo format_wt($job_data['CUT_WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT LENGTH</div><div class="info-value"><?php echo format_wt($job_data['CUT_LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT WEIGHT PIECE</div><div class="info-value"><?php echo format_dim($job_data['CUT_WEIGHTPIECE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SCHEDULE DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SCHEDULE_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-6 col-sm-12"><div class="info-label">JOB REMARK</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- 2. History Job Product Process -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ 2. History Job Product Process</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_RELEASEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_RELEASEPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX - MIN TOLERANCE</div><div class="info-value"><?php echo format_wt($job_data['JOB_MAXTOLERANCE']); ?>% / <?php echo format_wt($job_data['JOB_MINTOLERANCE']); ?>%</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL QTY WT. / PCS.</div><div class="info-value" style="color:#0284c7; font-weight:700;"><?php echo format_wt($job_data['JOB_ACTUALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_ACTUALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PRODUCEWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PRODUCEPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">STRETCHER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_STRETCHERWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_STRETCHERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT SHEET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_CUTSHEETWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_CUTSHEETPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SHEARING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_SHEARWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_SHEARPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PUNCH HOLE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PUNCHWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PUNCHPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_BATCHANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_BATCHANNEALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER PALLET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_TRANSFERWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_TRANSFERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ANNEALING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_ANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_ANNEALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SORTING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_SORTWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_SORTPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($job_data['JOB_PACKWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_PACKPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK QTY WT. / PCS.</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_wt($job_data['JOB_STOCKWEIGHT']); ?> kg. / <?php echo format_pcs($job_data['JOB_STOCKPIECE']); ?> pcs.</div></div>
                    </div>
                </div>

                <!-- 3. Batch Annealing by Job Order -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">🔥 3. Batch Annealing Details by Job Order</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Product No</th>
                                    <th>Batch No</th>
                                    <th style="text-align: center;">Item</th>
                                    <th style="text-align: center;">B St.</th>
                                    <th style="text-align: center;">P.ID.</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">I Temp</th>
                                    <th style="text-align: center;">T Temp</th>
                                    <th style="text-align: center;">Thick</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: center;">Length</th>
                                    <th style="text-align: right;">Input Wt.</th>
                                    <th style="text-align: right;">Input Piece</th>
                                    <th style="text-align: center;">Start Date</th>
                                    <th style="text-align: center;">Open Date</th>
                                    <th style="text-align: center;">Operator</th>
                                    <th style="text-align: center;">Operator Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batch_list)): ?>
                                    <tr>
                                        <td colspan="18" align="center" style="color: #64748b; padding: 20px;">
                                            No batch annealing records found for this job order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($batch_list as $index => $btch): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($btch['PRODUCT_NO'] ?? '-'); ?></td>
                                            <td style="font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($btch['BTCH_NO'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['PALLET_ITEM'] ?? '-'); ?></td>
                                            <td align="center" style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($btch['BTCH_STATUS'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['ALLOY'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['TEMPER_INITIAL'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['TEMPER_TARGET'] ?? '-'); ?></td>
                                            <td align="center"><?php echo format_dim($btch['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($btch['WIDTH']); ?></td>
                                            <td align="center"><?php echo format_wt($btch['LENGTH']); ?></td>
                                            <td align="right"><?php echo format_wt($btch['BTCH_INPUTWEIGHT']); ?></td>
                                            <td align="right"><?php echo format_pcs($btch['BTCH_INPUTPIECE']); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['START_Date'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPEN_Date'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPERATOR'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($btch['OPERATOR_UPDATE'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
    window.location.assign('history_crsh_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>