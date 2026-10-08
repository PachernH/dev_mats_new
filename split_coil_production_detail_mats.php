<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// Query ดึงข้อมูล Coil Product Details
$row_data = null;
$job_list = [];
$coil_tree_list = [];

if (!empty($coil_no)) {
    // 1. Query ดึงข้อมูล Coil หลัก
    $sql = "SELECT p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
            p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
            p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
            p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_BALANCEWEIGHT,p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.LINE_PROCESS, 
            p.BLN_SLITWIDTH, p.ACC_SLITWIDTH,p.RSET_NO,p.RECIPE_NO,p.RECIPE_ITEM,p.TOTAL_PASS,p.CURRENT_PASS,p.THICKNESS_FINAL,
            i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Job Order (Group 2) แบบรวมผลลัพธ์จากทั้ง USE_FORPROCESS และ COIL_REMARK
    if ($row_data) {
        $found_jobs_map = []; // Map สำหรับเก็บข้อมูล Job แบบไม่ซ้ำกัน

        // -------------------------------------------------------------------
        // [A] ค้นหาจาก USE_FORPROCESS (ถ้ามีค่า)
        // -------------------------------------------------------------------
        $raw_use_forprocess = !empty($row_data['USE_FORPROCESS']) ? trim($row_data['USE_FORPROCESS']) : '';
        $clean_use_forprocess = preg_replace('/[0-9]/', '', $raw_use_forprocess);

        if (!empty($clean_use_forprocess)) {
            $sql_job1 = "SELECT JOB_ORDER, ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, 
                                GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, SURFACE_GRADE, METALLURGICAL_GRADE, 
                                THICKNESS, WIDTH, JOB_WORKPROCESS, JOB_RELEASEWEIGHT, JOB_PRODUCEWEIGHT, JOB_STATUS 
                         FROM JOBORDER1
                         WHERE ALLOY = :alloy
                           AND (TEMPER = :temper OR PRODUCE_TEMPER1 = :temper OR PRODUCE_TEMPER2 = :temper)
                           AND (GRADE = :grade OR PRODUCE_GRADE1 = :grade OR PRODUCE_GRADE2 = :grade)
                           AND SURFACE_GRADE = :surface_grade
                           AND METALLURGICAL_GRADE = :metallurgical_grade
                           AND THICKNESS = :thickness
                           AND JOB_WORKPROCESS LIKE :workprocess
                           AND JOB_RELEASEWEIGHT > JOB_PRODUCEWEIGHT
                           AND JOB_STATUS = 'OP'
                         ORDER BY JOB_ORDER ASC";

            $stmt_job1 = $conn->prepare($sql_job1);
            $stmt_job1->bindValue(':alloy', $row_data['ALLOY'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':temper', $row_data['TEMPER'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':grade', $row_data['GRADE'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':surface_grade', $row_data['SURFACE_GRADE'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':metallurgical_grade', $row_data['METALLURGICAL_GRADE'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':thickness', $row_data['THICKNESS'] ?? '', PDO::PARAM_STR);
            $stmt_job1->bindValue(':workprocess', '%' . $clean_use_forprocess . '%', PDO::PARAM_STR);
            
            $stmt_job1->execute();
            $jobs1 = $stmt_job1->fetchAll(PDO::FETCH_ASSOC);

            foreach ($jobs1 as $j) {
                $found_jobs_map[$j['JOB_ORDER']] = $j;
            }
        }

        // -------------------------------------------------------------------
        // [B] ค้นหา Job Number จาก COIL_REMARK
        // -------------------------------------------------------------------
        $coil_remark = $row_data['COIL_REMARK'] ?? '';
        preg_match_all('/JB-\d{2}-\d{4}/i', $coil_remark, $matches);
        
        if (!empty($matches[0])) {
            $found_job_nos = array_unique($matches[0]);
            
            $in_placeholders = [];
            $params_job2 = [];
            foreach (array_values($found_job_nos) as $idx => $j_no) {
                $ph = ":job" . $idx;
                $in_placeholders[] = $ph;
                $params_job2[$ph] = $j_no;
            }

            $sql_job2 = "SELECT JOB_ORDER, ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, 
                                GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2, SURFACE_GRADE, METALLURGICAL_GRADE, 
                                THICKNESS, WIDTH, JOB_WORKPROCESS, JOB_RELEASEWEIGHT, JOB_PRODUCEWEIGHT, JOB_STATUS 
                         FROM JOBORDER1
                         WHERE JOB_ORDER IN (" . implode(',', $in_placeholders) . ")
                         ORDER BY JOB_ORDER ASC";

            $stmt_job2 = $conn->prepare($sql_job2);
            $stmt_job2->execute($params_job2);
            $jobs2 = $stmt_job2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($jobs2 as $j) {
                if (!isset($found_jobs_map[$j['JOB_ORDER']])) {
                    $found_jobs_map[$j['JOB_ORDER']] = $j;
                }
            }
        }

        $job_list = array_values($found_jobs_map);

        // -------------------------------------------------------------------
        // 3. Query ดึงข้อมูล Group 3: Coil Split Tree Details (Recursive CTE)
        // -------------------------------------------------------------------
        $sql_tree = "WITH CoilTree AS (
                        SELECT 
                            c.COIL_NO, c.PRODUCT_REFERENCE, c.JOB_PROCESS, c.JOB_ORDER, c.LINE_PROCESS, c.COIL_ENDDATE, 
                            c.COIL_ACTUALWEIGHT, c.CSTMSPPL_ID, c.ALLOY, c.THICKNESS, c.WIDTH, 
                            c.COIL_STATUS, c.COIL_OPERATEDATE,
                            0 AS Level 
                        FROM COILPROD1 AS c 
                        WHERE c.COIL_NO = :coil_no

                        UNION ALL

                        SELECT 
                            child.COIL_NO, child.PRODUCT_REFERENCE, child.JOB_PROCESS, child.JOB_ORDER, child.LINE_PROCESS, child.COIL_ENDDATE, 
                            child.COIL_ACTUALWEIGHT, child.CSTMSPPL_ID, child.ALLOY, child.THICKNESS, child.WIDTH, 
                            child.COIL_STATUS, child.COIL_OPERATEDATE,
                            parent.Level + 1
                        FROM COILPROD1 AS child
                        INNER JOIN CoilTree AS parent ON child.PRODUCT_REFERENCE = parent.COIL_NO
                    )
                    SELECT * FROM CoilTree
                    ORDER BY COIL_OPERATEDATE ASC";

        $stmt_tree = $conn->prepare($sql_tree);
        $stmt_tree->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_tree->execute();
        $coil_tree_list = $stmt_tree->fetchAll(PDO::FETCH_ASSOC);
    }
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

        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        /* Detail Group Header */
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
            color: #047857; 
            border-bottom: 2px solid #a7f3d0; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        /* Label and Value Typography */
        .info-label { 
            font-size: 12px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 15px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 18px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        /* Modern Table Styles */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
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

        .status-badge-op {
            color: #0284c7;
            font-weight: 700;
            background-color: #e0f2fe;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #bae6fd;
            display: inline-block;
        }

        .status-badge-ac {
            color: #059669;
            font-weight: 700;
            background-color: #ecfdf5;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }

        .level-badge {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
        }

        /* ปุ่ม Back */
        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
    </style>

    <!-- Mermaid.js สำหรับวาด Genealogy Tree Chart -->
    <script src="assets/js/mermaid.min.js"></script>
    <script>
        mermaid.initialize({ 
            startOnLoad: true, 
            theme: 'neutral',
            flowchart: { 
                useMaxWidth: false, 
                htmlLabels: true,
                curve: 'basis'
            } 
        });
    </script>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="split_process_mats.php?func=<?php echo $folder_func ?>">Maintain Coil Split at Cold Mill</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Coil Details: <span style="color:#2563eb;"><a href="split_process_mats.php?func=<?php echo $folder_func ?>&jno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_coil()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: Product Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Group 1: Coil Product Details</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MAL CAST NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['MAL_CASTNO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LINE PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['LINE_PROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT STATUS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['WIDTH']) : number_format((float)($row_data['WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_THICKNESS']) : number_format((float)($row_data['F_THICKNESS'] ?? 0), 3); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_WIDTH']) : number_format((float)($row_data['F_WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NEXTPROCESS'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['USE_FORPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUALWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_PRODUCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ROLL SET NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['RSET_NO'] ?? '-'); ?></div></div>
                       
                        <div class="col-md-3 col-sm-6"><div class="info-label">RECIPE : TIEM</div><div class="info-value"><?php echo htmlspecialchars($row_data['RECIPE_NO'] ?? '-')?> : <?php echo number_format((float)($row_data['RECIPE_ITEM'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TOTAL</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['TOTAL_PASS']) : number_format((float)($row_data['TOTAL_PASS'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CURRENT PASS</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['CURRENT_PASS']) : number_format((float)($row_data['CURRENT_PASS'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">FINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['THICKNESS_FINAL']) : number_format((float)($row_data['THICKNESS_FINAL'] ?? 0), 2); ?></div></div>


                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">COIL REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 2: Job Order Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📋 Group 2: Job Order Matching Records</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Job Order</th>
                                    <th>Alloy</th>
                                    <th>Temper</th>
                                    <th>Grade</th>
                                    <th>Surface Grade</th>
                                    <th>Metallurgical Grade</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th>Job Work Process</th>
                                    <th style="text-align: right;">Release Weight</th>
                                    <th style="text-align: right;">Produce Weight</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($job_list)): ?>
                                    <tr>
                                        <td colspan="13" align="center" style="color: #64748b; padding: 20px;">
                                            No matching Job Order records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($job_list as $index => $job): 
                                        $display_temper = implode(' / ', array_filter([
                                            trim($job['TEMPER'] ?? ''),
                                            trim($job['PRODUCE_TEMPER1'] ?? ''),
                                            trim($job['PRODUCE_TEMPER2'] ?? '')
                                        ]));
                                        if (empty($display_temper)) { $display_temper = '-'; }

                                        $display_grade = implode(' / ', array_filter([
                                            trim($job['GRADE'] ?? ''),
                                            trim($job['PRODUCE_GRADE1'] ?? ''),
                                            trim($job['PRODUCE_GRADE2'] ?? '')
                                        ]));
                                        if (empty($display_grade)) { $display_grade = '-'; }
                                    ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($job['JOB_ORDER'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($job['ALLOY'] ?? '-'); ?></td>
                                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($display_temper); ?></td>
                                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($display_grade); ?></td>
                                            <td><?php echo htmlspecialchars($job['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($job['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo function_exists('fmt3') ? fmt3($job['THICKNESS']) : number_format((float)($job['THICKNESS'] ?? 0), 3); ?></td>
                                            <td align="center"><?php echo function_exists('fmt2') ? fmt2($job['WIDTH']) : number_format((float)($job['WIDTH'] ?? 0), 2); ?></td>
                                            <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($job['JOB_WORKPROCESS'] ?? '-'); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($job['JOB_RELEASEWEIGHT']) : number_format((float)($job['JOB_RELEASEWEIGHT'] ?? 0), 2); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($job['JOB_PRODUCEWEIGHT']) : number_format((float)($job['JOB_PRODUCEWEIGHT'] ?? 0), 2); ?></td>
                                            <td align="center"><span class="status-badge-op"><?php echo htmlspecialchars($job['JOB_STATUS'] ?? '-'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GROUP 3: Product Information (Coil Split Tree Details) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">📦 Group 3: Coil Split Product Details</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th style="text-align: center;">Tree Level</th>
                                    <th>Coil No</th>
                                    <th>Product Reference</th>
                                    <th>Job Process</th>
                                    <th>Job Order</th>
                                    <th style="text-align: center;">Line Process</th>
                                    <th>Operate Date</th>
                                    <th>End Date</th>
                                    <th>Alloy</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: right;">Actual Weight</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                                // กรองเฉพาะรายการที่ Level > 0
                                $split_tree_list = array_filter($coil_tree_list, function($item) {
                                    return (isset($item['Level']) && (int)$item['Level'] > 0);
                                });
                            ?>

                            <?php if (empty($split_tree_list)): ?>
                                <tr>
                                    <td colspan="14" align="center" style="color: #64748b; padding: 20px;">
                                        No Split product tree records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php foreach ($split_tree_list as $tree_item): ?>
                                    <tr>
                                        <td align="center"><?php echo $no++; ?></td>
                                        <td align="center">
                                            <span class="level-badge">Level <?php echo htmlspecialchars($tree_item['Level'] ?? '0'); ?></span>
                                        </td>
                                        <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($tree_item['COIL_NO'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tree_item['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tree_item['JOB_PROCESS'] ?? '-'); ?></td>
                                        <td style="font-weight: 600; color: #0284c7;"><?php echo htmlspecialchars($tree_item['JOB_ORDER'] ?? '-'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($tree_item['LINE_PROCESS'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tree_item['COIL_OPERATEDATE'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tree_item['COIL_ENDDATE'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($tree_item['ALLOY'] ?? '-'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($tree_item['THICKNESS']) : number_format((float)($tree_item['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($tree_item['WIDTH']) : number_format((float)($tree_item['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="right"><?php echo function_exists('fmt2') ? fmt2($tree_item['COIL_ACTUALWEIGHT']) : number_format((float)($tree_item['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center">
                                            <span class="status-badge-ac"><?php echo htmlspecialchars($tree_item['COIL_STATUS'] ?? '-'); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GROUP 4: Graph Coil Traceability Genealogy Tree -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1" style="color: #475569; border-bottom-color: #cbd5e1;">
                        📋 Graph Coil Traceability Genealogy Tree
                    </h4>
                    
                    <div style="width: 100%; overflow-x: auto; padding: 20px 0; text-align: center;">
                        <?php if (empty($coil_tree_list)): ?>
                            <div style="color: #64748b; padding: 20px;">No genealogy tree data available.</div>
                        <?php else: 
                            // สร้างโครงสร้าง Mermaid Flowchart Diagram จาก $coil_tree_list
                            $mermaid_syntax = "graph TD\n";
                            
                            // Style สำหรับกล่อง Node
                            $mermaid_syntax .= "    classDef parentNode fill:#f1f5f9,stroke:#94a3b8,stroke-width:1px,color:#1e293b,font-weight:bold;\n";
                            $mermaid_syntax .= "    classDef childNode fill:#fef08a,stroke:#ca8a04,stroke-width:1px,color:#1e293b,font-weight:bold;\n";

                            foreach ($coil_tree_list as $node) {
                                $c_no = trim($node['COIL_NO'] ?? '');
                                $p_ref = trim($node['PRODUCT_REFERENCE'] ?? '');
                                $thick = function_exists('fmt3') ? fmt3($node['THICKNESS']) : number_format((float)($node['THICKNESS'] ?? 0), 2);
                                $width = function_exists('fmt2') ? fmt2($node['WIDTH']) : number_format((float)($node['WIDTH'] ?? 0), 2);
                                $level = (int)($node['Level'] ?? 0);

                                // รหัส Node ID เพื่อป้องกันปัญหาสัญลักษณ์พิเศษใน ID
                                $node_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $c_no);

                                // ข้อความในกล่อง Node
                                if ($level === 0 && empty($p_ref)) {
                                    // Node แม่ด้านบนสุด (กรณีไม่มี Parent)
                                    $label = "<b>{$c_no}</b>";
                                    $mermaid_syntax .= "    {$node_id}[\"{$label}\"]:::parentNode\n";
                                } else {
                                    // Node คอยล์ลูกและโหนดหลัก
                                    $label = "<b>{$c_no}</b><br/><span style='font-size:11px; font-weight:normal;'>{$thick} x {$width}</span>";
                                    $style_class = ($level === 0) ? "parentNode" : "childNode";
                                    $mermaid_syntax .= "    {$node_id}[\"{$label}\"]:::{$style_class}\n";

                                    // สร้างเส้นเชื่อมโยง Parent -> Child
                                    if (!empty($p_ref)) {
                                        $parent_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $p_ref);
                                        $mermaid_syntax .= "    {$parent_id} --> {$node_id}\n";
                                    }
                                }
                            }
                        ?>
                            <div class="mermaid" style="display: inline-block; min-width: 600px;">
                                <?php echo $mermaid_syntax; ?>
                            </div>
                        <?php endif; ?>
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
function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('split_process_mats.php?func='+encodeURIComponent(data_fun)); 
}   
</script>
</html>