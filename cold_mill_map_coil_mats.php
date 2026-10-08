<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า JOB_ORDER จาก URL
$job_order = isset($_GET['jno']) ? htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$job_data = null;
$coils_list = [];
$caster_coils_list = [];

if (!empty($job_order)) {
    // 1. Query ดึงข้อมูล Group 1: JOB Detail จาก JOBORDER1
    $sql_job = "SELECT JOB_ORDER, JOBORDER_DATE, ALLOY, SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2,
                       GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, THICKNESS, WIDTH, JOB_UOMDIMENSION,
                       T5_TEMPERATURE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, WEIGHT_PIECE, JOB_MAXTOLERANCE,
                       JOB_WORKPROCESS, USE_FORPROCESS, JOB_RELEASEWEIGHT, JOB_UOMWEIGHT, JOB_RELEASEPIECE,
                       JOB_BATCHANNEALWEIGHT, JOB_BATCHANNEALPIECE, JOB_SELECTBATCHANNEALWEIGHT, JOB_SELECTBATCHANNEALPIECE,
                       JOB_COLDMILLWEIGHT, JOB_COLDMILLPIECE, JOB_SELECTCOLDMILLWEIGHT, JOB_SELECTCOLDMILLPIECE, JOB_REMARK, JOB_STATUS, JOB_OPERATOR
                FROM JOBORDER1
                WHERE JOB_ORDER = :job_order
                ORDER BY JOBORDER_DATE DESC";
            
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Group 2: Coil Detail List จาก COILPROD1
    $sql_coils = "SELECT JOB_PROCESS, COIL_NO, PRODUCT_REFERENCE, PRODUCT_ID, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
                         THICKNESS, WIDTH, COIL_WORKPROCESS, COIL_NEXTPROCESS, RECIPE_NO, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS, THICKNESS_FINAL,
                         COIL_ACTUALWEIGHT, COIL_COLDMILLWEIGHT, PRODUCE_FLAG, COIL_OPERATEDATE, COIL_STATUS
                  FROM COILPROD1
                  WHERE JOB_PROCESS = :job_order AND PRODUCT_REFERENCE = ''";

    $stmt_coils = $conn->prepare($sql_coils);
    $stmt_coils->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_coils->execute();
    $coils_list = $stmt_coils->fetchAll(PDO::FETCH_ASSOC);

    // 3. Query ดึงข้อมูล Coil Caster List + หา RECIPE_NO จาก CMRCMSTR1
    if ($job_data) {
        // แปลง THICKNESS ของ Job ให้เป็น ทศนิยม 3 ตำแหน่ง (เช่น 2.000)
        $job_thickness_fmt = sprintf("%.3f", (float)$job_data['THICKNESS']);

        $sql_caster = "SELECT c.COIL_NO, c.PRODUCT_ID, c.ALLOY, c.TEMPER, c.GRADE, 
                            c.THICKNESS, c.WIDTH, c.COIL_ACTUALWEIGHT, c.COIL_STATUS,
                            (
                                SELECT TOP 1 r.RECIPE_NO 
                                FROM CMRCMSTR1 r 
                                WHERE r.RECIPE_NO = LTRIM(RTRIM(c.ALLOY)) + ':' + 
                                                    LTRIM(RTRIM(STR(c.WIDTH, 10, 0))) + '-' + 
                                                    LTRIM(RTRIM(STR(c.THICKNESS, 10, 3))) + '>' + 
                                                    :job_thickness_fmt
                            ) AS CALC_RECIPE_NO
                    FROM COILPROD1 c
                    WHERE c.ALLOY = :alloy 
                        AND c.THICKNESS > :thickness 
                        AND c.WIDTH >= :width 
                        AND (c.TEMPER = :temper OR c.TEMPER = :produce_temper1 OR c.TEMPER = :produce_temper2)
                        AND (c.GRADE = :grade OR c.GRADE = :produce_grade1 OR c.GRADE = :produce_grade2)
                        AND c.SURFACE_GRADE = :surface_grade 
                        AND c.METALLURGICAL_GRADE = :metallurgical_grade 
                        AND c.COIL_STATUS = 'AC' 
                        AND c.COIL_NEXTPROCESS = 'WS' 
                        AND (c.RECIPE_NO IS NULL OR c.RECIPE_NO = '') 
                        AND (c.JOB_PROCESS IS NULL OR c.JOB_PROCESS = '')";

        $stmt_caster = $conn->prepare($sql_caster);
        
        // Bind parameters
        $stmt_caster->bindParam(':job_thickness_fmt', $job_thickness_fmt, PDO::PARAM_STR);
        $stmt_caster->bindParam(':alloy', $job_data['ALLOY'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':thickness', $job_data['THICKNESS']);
        $stmt_caster->bindParam(':width', $job_data['WIDTH']);
        $stmt_caster->bindParam(':temper', $job_data['TEMPER'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':produce_temper1', $job_data['PRODUCE_TEMPER1'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':produce_temper2', $job_data['PRODUCE_TEMPER2'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':grade', $job_data['GRADE'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':produce_grade1', $job_data['PRODUCE_GRADE1'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':produce_grade2', $job_data['PRODUCE_GRADE2'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':surface_grade', $job_data['SURFACE_GRADE'], PDO::PARAM_STR);
        $stmt_caster->bindParam(':metallurgical_grade', $job_data['METALLURGICAL_GRADE'], PDO::PARAM_STR);
        
        $stmt_caster->execute();
        $caster_coils_list = $stmt_caster->fetchAll(PDO::FETCH_ASSOC);
    }

}

// แยกประเภทข้อมูลเป็น 2 Group: มี Recipe กับ ไม่มี Recipe
$coils_with_recipe = [];
$coils_without_recipe = [];

if (!empty($caster_coils_list)) {
    foreach ($caster_coils_list as $item) {
        if (!empty($item['CALC_RECIPE_NO'])) {
            $coils_with_recipe[] = $item;
        } else {
            $coils_without_recipe[] = $item;
        }
    }
}
?>


<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <!-- DataTables CSS & JS (ใส่ใน header หรือตรงนี้ได้) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

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

        /* กำหนดความสูงและ scrollbar สำหรับตาราง */
        .coil-scroll-box {
            max-height: 420px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        /* ล็อกหัวตารางให้อยู่กับที่เวลา Scroll */
        .coil-table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #0f172a !important;
            color: #ffffff;
        }

        /* สไตล์ปรับแต่ง DataTables Controls */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            padding: 4px 8px;
            margin-left: 8px;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            padding: 4px;
        }
/* Custom Tab Styling */
    .nav-tabs-custom {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 20px;
    }
    .nav-tabs-custom > li > a {
        font-weight: 700;
        font-size: 14px;
        color: #64748b;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 10px 18px;
        transition: all 0.2s;
    }
    .nav-tabs-custom > li.active > a, 
    .nav-tabs-custom > li.active > a:hover {
        color: #2563eb;
        border: none;
        border-bottom: 3px solid #2563eb;
        background: transparent;
    }
    .nav-tabs-custom > li > a:hover {
        color: #1e293b;
        border-bottom: 3px solid #cbd5e1;
    }

    /* Scrollable Table Box */
    .coil-scroll-box {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .coil-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #1e293b !important;
        color: #ffffff;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    /* Modern Buttons Design */
    .btn-use-coil {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff !important;
        font-weight: 600;
        font-size: 12px;
        padding: 5px 14px;
        border: none;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        transition: all 0.2s ease-in-out;
    }
    .btn-use-coil:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
    }
    
    .btn-create-recipe {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff !important;
        font-weight: 600;
        font-size: 12px;
        padding: 5px 12px;
        border: none;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
        transition: all 0.2s ease-in-out;
        text-decoration: none !important;
        display: inline-block;
    }
    .btn-create-recipe:hover {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);
    }

    .btn-disabled-coil {
        background-color: #cbd5e1;
        color: #64748b;
        font-weight: 600;
        font-size: 12px;
        padding: 5px 14px;
        border: none;
        border-radius: 6px;
        cursor: not-allowed;
        opacity: 0.8;
    }

    .btn-del-coil {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff !important;
        font-weight: 600;
        font-size: 12px;
        padding: 4px 10px;
        border: none;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
        transition: all 0.2s ease-in-out;
    }
    .btn-del-coil:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3);
    }    

    /* Badge & Labels */
    .recipe-badge {
        background-color: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-weight: 600;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
    }

    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'A4';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="cold_mill_mats.php?func=<?php echo $folder_func ?>">Select Coil Product for Cold Rolling Mill Process + Other Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Job Details: <span style="color:#2563eb;"><a href="cold_mill_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $job_order?>"><?php echo htmlspecialchars($job_order); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_cold()">
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

<!-- GROUP COIL: Coil CASTER List -->
<div class="dashboard-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 class="card-title-g2" style="margin: 0; border: none; padding: 0;">
            🌀 Coil Caster List (Available Materials)
        </h4>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
        <li role="presentation" class="active">
            <a href="#tab_ready" aria-controls="tab_ready" role="tab" data-toggle="tab">
                ✅ Ready to use (Recipe included)
                <span class="badge" style="background-color: #10b981; margin-left: 5px;"><?php echo count($coils_with_recipe); ?></span>
            </a>
        </li>
        <li role="presentation">
            <a href="#tab_norecipe" aria-controls="tab_norecipe" role="tab" data-toggle="tab">
                ⚠️ Not ready (No Recipe)
                <span class="badge" style="background-color: #f59e0b; margin-left: 5px;"><?php echo count($coils_without_recipe); ?></span>
            </a>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content">
        
        <!-- TAB 1: Coils With Recipe (พร้อมใช้งาน) -->
        <div role="tabpanel" class="tab-pane active" id="tab_ready">
            <?php if (empty($coils_with_recipe)): ?>
                <div class="alert alert-warning" style="margin:0;">No coils with available recipes were found.</div>
            <?php else: ?>
                <div class="coil-scroll-box">
                    <table id="tbl_coils_ready" class="table table-hover coil-table" style="width:100%; margin:0;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Coil No</th>
                                <th>Product ID</th>
                                <th>Alloy</th>
                                <th>Temper</th>
                                <th>Grade</th>
                                <th>Thickness</th>
                                <th>Width</th>
                                <th>Recipe No</th>
                                <th>Actual Weight</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coils_with_recipe as $index => $caster): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($caster['COIL_NO'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['PRODUCT_ID'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['ALLOY'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['TEMPER'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['GRADE'] ?? '-'); ?></td>
                                    <td><?php echo fmt3($caster['THICKNESS']); ?></td>
                                    <td><?php echo fmt3($caster['WIDTH']); ?></td>
                                    <td>
                                        <span class="recipe-badge">
                                            <?php echo htmlspecialchars($caster['CALC_RECIPE_NO']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo fmt2($caster['COIL_ACTUALWEIGHT']); ?></td>
                                    <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($caster['COIL_STATUS'] ?? '-'); ?></span></td>
                                    <?php 
                                        // เช็คเงื่อนไข RELEASE PIECE <= COLD MILL PIECE (แปลงเป็น float เพื่อความแม่นยำในการเปรียบเทียบ)
                                        $release_piece  = (float)($job_data['JOB_RELEASEPIECE'] ?? 0);
                                        $coldmill_piece = (float)($job_data['JOB_SELECTCOLDMILLPIECE'] ?? 0);
                                        $is_limit_reached = ($release_piece > 0 && $coldmill_piece >= $release_piece);
                                    ?>
                                    <td style="text-align: center;">
                                        <?php if ($is_limit_reached): ?>
                                            <button type="button" 
                                                    class="btn-disabled-coil" 
                                                    disabled 
                                                    title="Cannot be added because Release Piece in complete (<?php echo $coldmill_piece; ?>/<?php echo $release_piece; ?>)">
                                                🚫 Piece Limit
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-use-coil" 
                                                    onclick="useCoil('<?php echo htmlspecialchars($caster['COIL_NO']); ?>', '<?php echo htmlspecialchars($job_order); ?>', '<?php echo htmlspecialchars($caster['CALC_RECIPE_NO']); ?>')">
                                                ✅ Use Coil
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: Coils Without Recipe (ต้องสร้าง Recipe ก่อน) -->
        <div role="tabpanel" class="tab-pane" id="tab_norecipe">
            <?php if (empty($coils_without_recipe)): ?>
                <div class="alert alert-success" style="margin:0;">Not found recipe information</div>
            <?php else: ?>
                <div class="coil-scroll-box">
                    <table id="tbl_coils_norecipe" class="table table-hover coil-table" style="width:100%; margin:0;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Coil No</th>
                                <th>Product ID</th>
                                <th>Alloy</th>
                                <th>Temper</th>
                                <th>Grade</th>
                                <th>Thickness</th>
                                <th>Width</th>
                                <th>Recipe No Action</th>
                                <th>Actual Weight</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coils_without_recipe as $index => $caster): ?>
                                <?php 
                                    $c_thick = sprintf("%.3f", (float)$caster['THICKNESS']);
                                    $j_thick = sprintf("%.3f", (float)$job_data['THICKNESS']);
                                    $c_width = sprintf("%.0f", (float)$caster['WIDTH']);
                                    $target_recipe_key = $caster['ALLOY'] . ':' . $c_width . '-' . $c_thick . '>' . $j_thick;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($caster['COIL_NO'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['PRODUCT_ID'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['ALLOY'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['TEMPER'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($caster['GRADE'] ?? '-'); ?></td>
                                    <td><?php echo fmt3($caster['THICKNESS']); ?></td>
                                    <td><?php echo fmt3($caster['WIDTH']); ?></td>
                                    <td>
                                        <a href="cold_rolling_recipe_master_mats.php?func=pd&recipe_no=<?php echo urlencode($target_recipe_key); ?>" 
                                           target="_blank" 
                                           class="btn-create-recipe" 
                                           title="Click to create a new recipe.">
                                            ➕ Create Recipe
                                        </a>
                                    </td>
                                    <td><?php echo fmt2($caster['COIL_ACTUALWEIGHT']); ?></td>
                                    <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($caster['COIL_STATUS'] ?? '-'); ?></span></td>
                                    <td style="text-align: center;">
                                        <button type="button" 
                                                class="btn-disabled-coil" 
                                                disabled
                                                title="Not available until created Recipe">
                                            🚫 Use Coil
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

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

                    <!-- 2. Weight & Quantities -->
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

                    <!-- 3. Remarks -->
                    <div class="row">
                        <div class="col-md-12"><div class="info-label">JOB REMARK</div><div class="info-value" style="min-height:48px; background-color:#fff8f1; border-color:#ffedd5; color:#9a3412;"><?php echo htmlspecialchars($job_data['JOB_REMARK'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: Coil Detail List -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">🌀 Coil Use for Cold Mill List (Job Process: <?php echo htmlspecialchars($job_order); ?>)</h4>
                    
                    <?php if (empty($coils_list)): ?>
                        <div class="alert alert-info" style="margin-bottom:0;">No Coil entry data was found in this Job Process.</div>
                    <?php else: ?>
                        <div class="coil-table-container">
                            <table class="coil-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Coil No</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Grade</th>
                                        <th>Surface Grade</th>
                                        <th>Met Grade</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Recipe No</th>
                                        <th>Total Pass</th>
                                        <th>Current Pass</th>
                                        <th>Final Thick</th>
                                        <th>Actual Weight</th>
                                        <th>Cold Mill Weight</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Action</th> <!-- 🟢 เพิ่มคอลัมน์ Action -->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coils_list as $index => $coil): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td style="font-weight:700; color:#2563eb;"><?php echo htmlspecialchars($coil['COIL_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['TEMPER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo fmt3($coil['THICKNESS']); ?></td>
                                            <td><?php echo fmt3($coil['WIDTH']); ?></td>
                                            <td><?php echo htmlspecialchars($coil['RECIPE_NO'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['TOTAL_PASS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($coil['CURRENT_PASS'] ?? '-'); ?></td>
                                            <td><?php echo fmt3($coil['THICKNESS_FINAL']); ?></td>
                                            <td><?php echo fmt2($coil['COIL_ACTUALWEIGHT']); ?></td>
                                            <td><?php echo fmt2($coil['COIL_COLDMILLWEIGHT']); ?></td>
                                            <td><span style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($coil['COIL_STATUS'] ?? '-'); ?></span></td>
                                            
                                            <!-- 🟢 เพิ่มส่วนเช็คเงื่อนไขแสดงปุ่ม Del -->
                                            <td style="text-align: center;">
                                                <?php if (trim($coil['COIL_STATUS']) === 'CM'): ?>
                                                    <button type="button" class="btn-disabled-coil" disabled title="Cannot be deleted because its status is CM">
                                                        🚫 Del
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-del-coil" 
                                                            onclick="delCoil('<?php echo htmlspecialchars($coil['COIL_NO']); ?>', '<?php echo htmlspecialchars($job_order); ?>')">
                                                        🗑️ Del
                                                    </button>
                                                <?php endif; ?>
                                            </td>
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

$(document).ready(function() {
    var dtOptions = {
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "ทั้งหมด"]],
        "language": {
            "search": "🔍 ค้นหาคอยล์:",
            "lengthMenu": "แสดง _MENU_ รายการ",
            "info": "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
            "paginate": { "next": "ถัดไป", "previous": "ก่อนหน้า" }
        }
    };

    $('#tbl_coils_ready').DataTable(dtOptions);
    $('#tbl_coils_norecipe').DataTable(dtOptions);
});

// ฟังก์ชันเมื่อกดปุ่ม Use Coil
var releasePiece = <?php echo (float)($job_data['JOB_RELEASEPIECE'] ?? 0); ?>;
var coldmillPiece = <?php echo (float)($job_data['JOB_SELECTCOLDMILLPIECE'] ?? 0); ?>;

function useCoil(coilNo, jobOrder, recipeNo) {
    if (!coilNo || !jobOrder) {
        alert('The Coil No. or Job Order information is incomplete.');
        return;
    }

    // เพิ่มเงื่อนไขการตรวจสอบจำนวน Piece ก่อนทำงาน
    if (releasePiece > 0 && coldmillPiece >= releasePiece) {
        alert('It is not possible to add more coils because the release piece is equal to or greater than the cold mill piece.');
        return;
    }

    if (confirm('คุณต้องการเลือกใช้ Coil No: ' + coilNo + ' สำหรับ Job Order: ' + jobOrder + ' ใช่หรือไม่?')) {
        var formData = new FormData();
        formData.append('coil_no', coilNo);
        formData.append('job_order', jobOrder);

        fetch('model/save_use_coil.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                window.location.reload();
            } else {
                alert('An error occurred: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Unable to connect to the server.');
        });
    }
}


function back_home_cold(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('cold_mill_mats.php?func='+encodeURIComponent(data_fun)); 
}

function delCoil(coilNo, jobOrder) {
    if (!coilNo || !jobOrder) {
        alert('Detail Coil No Or Job Order Incomplete');
        return;
    }

    if (confirm('Do you want to delete the Coil No application: ' + coilNo + ' from Job Order: ' + jobOrder + ' Yes/NO?')) {
        var formData = new FormData();
        formData.append('coil_no', coilNo);
        formData.append('job_order', jobOrder);

        fetch('model/del_use_coil.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                window.location.reload();
            } else {
                alert('An error occurred: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Unable to connect to the server');
        });
    }
}

</script>
</html>