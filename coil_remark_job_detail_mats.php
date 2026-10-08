<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL (รองรับทั้ง job_order และ job_process)
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : (isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data = null;
$coils_list = [];

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล Group 1: JOB Detail จาก JOBORDER1
    $sql_job = "SELECT JOB_ORDER, JOBORDER_DATE, ALLOY, SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2,
                       GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, THICKNESS, WIDTH, JOB_UOMDIMENSION, WEIGHT_PIECE, 
                       T5_TEMPERATURE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_MAXTOLERANCE,
                       JOB_WORKPROCESS, USE_FORPROCESS, JOB_RELEASEWEIGHT, JOB_UOMWEIGHT, JOB_RELEASEPIECE, JOB_SELECTCOLDMILLWEIGHT, JOB_SELECTCOLDMILLPIECE,
                       JOB_BATCHANNEALWEIGHT, JOB_BATCHANNEALPIECE, JOB_SELECTBATCHANNEALWEIGHT, JOB_SELECTBATCHANNEALPIECE,
                       JOB_COLDMILLWEIGHT, JOB_COLDMILLPIECE, JOB_REMARK, JOB_STATUS, JOB_OPERATOR
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order
                ORDER BY JOBORDER_DATE DESC";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Group 2: Coil Cold Mill Detail List จาก COILPROD1
    $sql_coils = "SELECT JOB_PROCESS, COIL_NO, PRODUCT_REFERENCE, PRODUCT_ID, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
                         THICKNESS, WIDTH, COIL_WORKPROCESS, COIL_NEXTPROCESS, RECIPE_NO, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL,
                         COIL_ACTUALWEIGHT, COIL_COLDMILLWEIGHT, PRODUCE_FLAG, COIL_OPERATEDATE, COIL_STATUS
                  FROM COILPROD1
                  WHERE JOB_PROCESS = :job_order AND PRODUCT_REFERENCE is not null AND PRODUCT_REFERENCE != ''";

    $stmt_coils = $conn->prepare($sql_coils);
    $stmt_coils->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_coils->execute();
    $coils_list = $stmt_coils->fetchAll(PDO::FETCH_ASSOC);

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

        /* ตารางสไตล์ Clean และ Scannable สำหรับรายการคอยล์ */
        .coil-table-container {
            overflow-x: auto;
        }
        .coil-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background-color: #ffffff;
        }
        .coil-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }
        .coil-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .coil-table tbody tr:hover {
            background-color: #f1f5f9;
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

<?php $menu = 'A2';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="coil_remark_process_mats.php?func=<?php echo $folder_func ?>">Coil Remark and Use For Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Details: <span style="color:#2563eb;"><a href="coil_remark_process_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $job_order?>"><?php echo htmlspecialchars($job_order); ?></a></span>
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

                <!-- GROUP 1: JOB Detail (Re-structured Layout) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 Job Order Details</h4>

                    <!-- 1. General & Primary Info -->
                    <div style="font-weight: 700; color: #475569; margin-bottom: 12px; font-size: 13px;"></div>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOBORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">OPERATOR</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_OPERATOR'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 2. Product & Material Specifications -->
                    <div style="font-weight: 700; color: #475569; margin-bottom: 12px; font-size: 13px;"></div>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE TEMPER 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_TEMPER2'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 1</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE1'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE GRADE 2</div><div class="info-value"><?php echo htmlspecialchars($job_data['PRODUCE_GRADE2'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['SURFACE_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($job_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo fmt3($job_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt3($job_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">DIMENSION UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMDIMENSION'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($job_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 3. Process & Customer Tolerances -->
                    <div style="font-weight: 700; color: #475569; margin-bottom: 12px; font-size: 13px;"></div>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($job_data['USE_FORPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MIN TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MINTOLERANCE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUST MAX TOLERANCE</div><div class="info-value"><?php echo fmt3($job_data['CUST_MAXTOLERANCE']); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 4. Weight & Quantities -->
                    <div style="font-weight: 700; color: #475569; margin-bottom: 12px; font-size: 13px;"></div>
                    <div class="row">
                        <?php
                            $MAXReleaseWeight = ($job_data['JOB_RELEASEWEIGHT']) + ((($job_data['JOB_RELEASEWEIGHT']) * $job_data['JOB_MAXTOLERANCE']) / 100);
                            $MAXReleasePiece = number_format(($MAXReleaseWeight / $job_data['WEIGHT_PIECE']), 0);
                        ?>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX RELEASE WEIGHT</div><div class="info-value"><?php echo fmt2($MAXReleaseWeight); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX RELEASE PIECE</div><div class="info-value"><?php echo htmlspecialchars($MAXReleasePiece); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_RELEASEWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_RELEASEPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_SELECTCOLDMILLWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_SELECTCOLDMILLPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL WEIGHT</div><div class="info-value"><?php echo fmt2($job_data['JOB_SELECTBATCHANNEALWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL PIECE</div><div class="info-value"><?php echo htmlspecialchars($job_data['JOB_SELECTBATCHANNEALPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>
                     </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 5. Remarks -->
                    <div class="row">
                        <div class="col-md-12"><div class="info-label">JOB REMARK</div><div class="info-value" style="min-height:48px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: Coil Cold Mill Detail List -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">🌀 Coil Cold Mill List (Job Process: <?php echo htmlspecialchars($job_order); ?>)</h4>
                    
                    <?php if (empty($coils_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">ไม่พบข้อมูลรายการ Coil ใน Job Process นี้</div>
                    <?php else: ?>
                        <div class="coil-table-container">
                            <table class="coil-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Coil No</th>
                                        <th>Product Ref</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Grade</th>
                                        <th>Surface Grade</th>
                                        <th>Met Grade</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Work Process</th>
                                        <th>Next Process</th>
                                        <th>Recipe No</th>
                                        <th>Cold Mill Weight</th>
                                        <th>Operate Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coils_list as $index => $coil): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($coil['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['TEMPER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo fmt3($coil['THICKNESS']); ?></td>
                                            <td><?php echo fmt3($coil['WIDTH']); ?></td>
                                            <td><?php echo htmlspecialchars($coil['COIL_WORKPROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['COIL_NEXTPROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['RECIPE_NO'] ?? '-'); ?></td>
                                            <td><?php echo fmt2($coil['COIL_COLDMILLWEIGHT']); ?></td>
                                            <td><?php echo htmlspecialchars($coil['COIL_OPERATEDATE'] ?? '-'); ?></td>
                                            <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($coil['COIL_STATUS'] ?? '-'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
    window.location.assign('coil_remark_process_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>