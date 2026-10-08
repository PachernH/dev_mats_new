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
    // 1. Query ดึงข้อมูล Coil หลัก (เพิ่ม BLN_SLITWIDTH, ACC_SLITWIDTH)
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
                p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT,p.COIL_BALANCEWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.LINE_PROCESS, 
                p.BLN_SLITWIDTH, p.ACC_SLITWIDTH,
                i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Job Order (Group 2) แบบรวมผลลัพธ์จากทั้ง USE_FORPROCESS และ COIL_REMARK
    if ($row_data) {
        $found_jobs_map = [];

        // [A] ค้นหาจาก USE_FORPROCESS (ถ้ามีค่า)
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

        // [B] ค้นหา Job Number จาก COIL_REMARK
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
        // 3. Query ดึงข้อมูล Coil Swiss Slitter Tree Details (Recursive CTE)
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

        /* Form Input Styling */
        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .form-control-custom {
            width: 100%;
            height: 42px;
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
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
        .custom-job-table tbody tr.selected-row {
            background-color: #ecfdf5 !important;
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

        /* Buttons & Dynamic States */
        .btn-select-job {
            background-color: #0284c7;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-select-job:hover {
            background-color: #0369a1;
            transform: translateY(-1px);
        }

        /* ปุ่ม Save Data (สีเขียวชัดเจน) */
        .btn-save {
            background-color: #10b981 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none !important;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
        }
        .btn-save:hover, .btn-save:focus, .btn-save:active {
            background-color: #059669 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        /* ปุ่ม Save เมื่ออยู่ในสถานะ Disabled */
        .btn-save:disabled, .btn-save[disabled] {
            background-color: #94a3b8 !important; /* สีเทา */
            color: #ffffff !important;
            opacity: 0.65;
            cursor: not-allowed !important;
            pointer-events: none;
        }

        /* ปุ่ม Cancel (สีแดงชัดเจน) */
        .btn-cancel {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none !important;
            transition: all 0.2s;
            cursor: pointer;
            margin-left: 10px;
            display: inline-block;
        }
        .btn-cancel:hover, .btn-cancel:focus, .btn-cancel:active {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        /* ปุ่ม Cancel เมื่ออยู่ในสถานะ Disabled */
        .btn-cancel:disabled, .btn-cancel[disabled] {
            background-color: #cbd5e1 !important; /* สีเทาอ่อน */
            color: #64748b !important;
            opacity: 0.65;
            cursor: not-allowed !important;
            pointer-events: none;
        }

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
    <!-- CSS บังคับให้ Node สีเหลืองแสดง Pointer รูปมือ -->
    <style>
        .childNode, .clickable-coil-node {
            cursor: pointer !important;
        }
        .childNode:hover rect, .childNode:hover polygon {
            fill: #fde047 !important;
            stroke: #854d0e !important;
        }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="swiss_slitter_process_mats.php?func=<?php echo $folder_func ?>">Swiss Slitter Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Swiss Slitter Coil Details: <span style="color:#2563eb;"><a href="swiss_sitter_coil_production_detail_mats.php?func=<?php echo $folder_func ?>&COIL=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
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
                        <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['WIDTH']) : number_format((float)($row_data['WIDTH'] ?? 0), 3); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUALWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>

                        <!-- เพิ่มแสดงผล ACC_SLITWIDTH และ BLN_SLITWIDTH -->
                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">ACCUMULATE SLIT WIDTH (MM)</div>
                            <div class="info-value" style="color: #0284c7; font-weight:700;">
                                <?php echo function_exists('fmt2') ? fmt2($row_data['ACC_SLITWIDTH']) : number_format((float)($row_data['ACC_SLITWIDTH'] ?? 0), 2); ?>
                            </div>
                        </div>
                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">BALANCE SLIT WIDTH (MM)</div>
                            <div class="info-value" style="color: #059669; font-weight:700;">
                                <?php echo function_exists('fmt2') ? fmt2($row_data['BLN_SLITWIDTH']) : number_format((float)($row_data['BLN_SLITWIDTH'] ?? 0), 2); ?>
                            </div>
                        </div>                        

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">COIL REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graph Coil Traceability Genealogy Tree -->
                <div class="dashboard-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px; margin-bottom: 15px;">
                        <h4 style="color: #475569; font-weight: 700; font-size: 17px; margin: 0;">
                            📋 Graph Coil Traceability Genealogy Tree
                        </h4>
                        <div style="font-size: 12px; color: #0284c7; background-color: #e0f2fe; padding: 4px 10px; border-radius: 6px; border: 1px solid #bae6fd;">
                            💡 <b>Instructions:</b>  Click the <b>Coil (yellow)</b> box to print the product label.
                        </div>
                    </div>

                    <!-- กล่องแจ้งเตือนข้อมูล / วิธีใช้งาน -->
                    <div class="alert alert-info" style="background-color: #f0fdf4; border-color: #bbf7d0; color: #166534; font-size: 13px; margin-bottom: 15px; border-radius: 8px;">
                        <strong>🖨️ Instructions for printing Coil Labels :</strong>
                        <ul style="margin-bottom: 0; padding-left: 20px; margin-top: 5px;">
                            <li>The yellow node indicates a coil that has been successfully slit. You can click on the Coil box to immediately open the label printing details page.</li>
                        </ul>
                    </div>
                    
                    <div style="width: 100%; overflow-x: auto; padding: 10px 0; text-align: center;">
                        <?php if (empty($coil_tree_list)): ?>
                            <div style="color: #64748b; padding: 20px;">No genealogy tree data available.</div>
                        <?php else: 
                            // สร้างโครงสร้าง Mermaid Flowchart Diagram จาก $coil_tree_list
                            $mermaid_syntax = "graph TD\n";
                            
                            // Style สำหรับกล่อง Node (เพิ่ม cursor:pointer)
                            $mermaid_syntax .= "    classDef parentNode fill:#f1f5f9,stroke:#94a3b8,stroke-width:1px,color:#1e293b,font-weight:bold;\n";
                            $mermaid_syntax .= "    classDef childNode fill:#fef08a,stroke:#ca8a04,stroke-width:2px,color:#1e293b,font-weight:bold;\n";

                            foreach ($coil_tree_list as $node) {
                                $c_no = trim($node['COIL_NO'] ?? '');
                                $p_ref = trim($node['PRODUCT_REFERENCE'] ?? '');
                                $thick = function_exists('fmt3') ? fmt3($node['THICKNESS']) : number_format((float)($node['THICKNESS'] ?? 0), 2);
                                $width = function_exists('fmt2') ? fmt2($node['WIDTH']) : number_format((float)($node['WIDTH'] ?? 0), 2);
                                $level = (int)($node['Level'] ?? 0);

                                // รหัส Node ID
                                $node_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $c_no);

                                // ข้อความในกล่อง Node
                                if ($level === 0 && empty($p_ref)) {
                                    $label = "<b>{$c_no}</b>";
                                    $mermaid_syntax .= "    {$node_id}[\"{$label}\"]:::parentNode\n";
                                } else {
                                    if ($level > 0) {
                                        // ใส่ data-coilno เข้าไปใน HTML Label เพื่อให้ JS ดึงไปใช้ได้ง่ายและถูกต้อง 100%
                                        $label = "<div class='clickable-coil-node' data-coilno='{$c_no}'><b>{$c_no}</b><br/><span style='font-size:11px; font-weight:normal;'>{$thick} x {$width}</span></div>";
                                    } else {
                                        $label = "<b>{$c_no}</b><br/><span style='font-size:11px; font-weight:normal;'>{$thick} x {$width}</span>";
                                    }
                                    
                                    $style_class = ($level === 0) ? "parentNode" : "childNode";
                                    $mermaid_syntax .= "    {$node_id}[\"{$label}\"]:::{$style_class}\n";

                                    if (!empty($p_ref)) {
                                        $parent_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $p_ref);
                                        $mermaid_syntax .= "    {$parent_id} --> {$node_id}\n";
                                    }
                                }
                            }
                        ?>
                            <div class="mermaid" id="genealogy_tree_container" style="display: inline-block; min-width: 600px;">
                                <?php echo $mermaid_syntax; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- GROUP 2: Job Order Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📋 Group 2: Job Order Matching Records (Click to select a job to process the data)</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table" id="job_order_table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">Action</th>
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
                                        <td colspan="14" align="center" style="color: #64748b; padding: 20px;">
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

                                        $job_no_val = htmlspecialchars($job['JOB_ORDER'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $width_val  = function_exists('fmt2') ? fmt2($job['WIDTH']) : number_format((float)($job['WIDTH'] ?? 0), 2);
                                        
                                        // เช็กสถานะว่าใช่ OP หรือ HL หรือไม่
                                        $job_status = strtoupper(trim($job['JOB_STATUS'] ?? ''));
                                        $can_select = in_array($job_status, ['OP', 'HL']);
                                    ?>
                                        <tr id="job_row_<?php echo $index; ?>">
                                            <td align="center">
                                                <?php if ($can_select): ?>
                                                    <!-- แสดงปุ่มปกติสำหรับ Status OP, HL -->
                                                    <button type="button" class="btn-select-job" onclick="select_job_order('<?php echo $job_no_val; ?>', '<?php echo $width_val; ?>', 'job_row_<?php echo $index; ?>')">
                                                        SELECT
                                                    </button>
                                                <?php else: ?>
                                                    <!-- ปิดการใช้งานปุ่ม (Disabled) สำหรับ Status อื่นๆ -->
                                                    <button type="button" class="btn-select-job" style="background-color: #cbd5e1; color: #64748b !important; cursor: not-allowed; opacity: 0.65;" disabled>
                                                        SELECT
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #0284c7;"><?php echo $job_no_val; ?></td>
                                            <td><?php echo htmlspecialchars($job['ALLOY'] ?? '-'); ?></td>
                                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($display_temper); ?></td>
                                            <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($display_grade); ?></td>
                                            <td><?php echo htmlspecialchars($job['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($job['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo function_exists('fmt3') ? fmt3($job['THICKNESS']) : number_format((float)($job['THICKNESS'] ?? 0), 3); ?></td>
                                            <td align="center"><?php echo $width_val; ?></td>
                                            <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($job['JOB_WORKPROCESS'] ?? '-'); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($job['JOB_RELEASEWEIGHT']) : number_format((float)($job['JOB_RELEASEWEIGHT'] ?? 0), 2); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($job['JOB_PRODUCEWEIGHT']) : number_format((float)($job['JOB_PRODUCEWEIGHT'] ?? 0), 2); ?></td>
                                            <td align="center">
                                                <span class="status-badge-op" style="<?php 
                                                    if ($job_status === 'HL') {
                                                        echo 'background-color:#fef3c7; color:#d97706; border-color:#fde68a;';
                                                    } else if ($job_status !== 'OP') {
                                                        echo 'background-color:#f1f5f9; color:#64748b; border-color:#cbd5e1;';
                                                    }
                                                ?>">
                                                    <?php echo htmlspecialchars($job_status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GROUP 3: Slitter Process Input Data -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">⚙️ Group 3: Slitter Process Data Entry</h4>
                    <form id="form_slitter_process" action="model/save_swiss_slitter_process.php" method="POST" onsubmit="return validate_form();">
                        
                        <!-- Hidden Inputs -->
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" />
                        <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>" />
                        <input type="hidden" id="selected_job_order" name="job_order" value="" />

                        <!-- Line 1: Job Order, Number of Coil, Width -->
                        <div class="row">
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label>Selected Job Order</label>
                                    <input type="text" id="display_job_order" class="form-control-custom" style="background-color: #f1f5f9; font-weight:700; color:#0284c7;" placeholder="Please choose from Group 2" readonly />
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="num_coil_slit">Number of Coil Slit (Stand) <span style="color:red;">*</span></label>
                                    <input type="number" step="1" min="1" class="form-control-custom slitter-input" id="num_coil_slit" name="num_coil_slit" placeholder="Enter the number of coils...." required disabled />
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="coil_slit_width">Coil Slit Width (mm) <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control-custom slitter-input" id="coil_slit_width" name="coil_slit_width" placeholder="Enter width (mm)..." required disabled />
                                </div>
                            </div>
                        </div>

                        <!-- Line 2: Start Date time & End Date time -->
                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="start_datetime_slit">Start Date time Slit <span style="color:red;">*</span></label>
                                    <input type="datetime-local" class="form-control-custom slitter-input" id="start_datetime_slit" name="start_datetime_slit" required disabled />
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="end_datetime_slit">End Date time Slit <span style="color:red;">*</span></label>
                                    <input type="datetime-local" class="form-control-custom slitter-input" id="end_datetime_slit" name="end_datetime_slit" required disabled />
                                </div>
                            </div>
                        </div>

                        <!-- Line 3: Coil Remark -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="coil_remark">Coil Remark (Note / Edit text)</label>
                                    <textarea class="form-control-custom slitter-input" id="coil_remark" name="coil_remark" rows="3" style="height: auto; padding: 10px 12px; resize: vertical;" placeholder="Enter additional notes or correct the information..." disabled><?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="margin-top: 15px; text-align: right; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                            <button type="submit" id="btn_save_slitter" class="btn btn-save" disabled="disabled">
                                💾 Save Data
                            </button>
                            <button type="button" id="btn_cancel_slitter" class="btn btn-cancel" onclick="clear_input_form()" disabled="disabled">
                                ❌ Cancel
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
// ดึงค่า จาก PHP
var coilWidth = parseFloat(<?php echo json_encode((float)($row_data['WIDTH'] ?? 0)); ?>);
var blnSlitWidth = parseFloat(<?php echo json_encode((float)($row_data['BLN_SLITWIDTH'] ?? 0)); ?>);
var initialCoilRemark = <?php echo json_encode($row_data['COIL_REMARK'] ?? ''); ?>;

function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('swiss_slitter_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

// ฟังก์ชันดึงค่า วัน-เวลา ปัจจุบันในรูปแบบ YYYY-MM-DDTHH:mm
function getCurrentDateTimeLocal() {
    var now = new Date();
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    
    return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
}

// ฟังก์ชันเมื่อคลิกเลือก Job Order จาก Group 2
function select_job_order(jobOrder, widthVal, rowId) {
    document.getElementById('selected_job_order').value = jobOrder;
    document.getElementById('display_job_order').value = jobOrder;
    
    // ปลดล็อก (Enable) อินพุตทั้งหมด
    var inputs = document.querySelectorAll('.slitter-input');
    inputs.forEach(function(input) {
        input.disabled = false;
    });

    // ปลดล็อก (Enable) ปุ่มกด
    document.getElementById('btn_save_slitter').disabled = false;
    document.getElementById('btn_cancel_slitter').disabled = false;

    // Set Default Values
    document.getElementById('num_coil_slit').value = 1;
    var cleanWidth = widthVal.replace(/,/g, '');
    document.getElementById('coil_slit_width').value = cleanWidth;

    var currentNow = getCurrentDateTimeLocal();
    document.getElementById('start_datetime_slit').value = currentNow;
    document.getElementById('end_datetime_slit').value = currentNow;

    // Hilight แถวที่เลือก
    var rows = document.querySelectorAll('#job_order_table tbody tr');
    rows.forEach(function(r) { r.classList.remove('selected-row'); });
    if (document.getElementById(rowId)) {
        document.getElementById(rowId).classList.add('selected-row');
    }

    document.getElementById('form_slitter_process').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ฟังก์ชันล้างค่า/ยกเลิก (Cancel)
function clear_input_form() {
    document.getElementById('selected_job_order').value = '';
    document.getElementById('display_job_order').value = '';
    document.getElementById('num_coil_slit').value = '';
    document.getElementById('coil_slit_width').value = '';
    document.getElementById('start_datetime_slit').value = '';
    document.getElementById('end_datetime_slit').value = '';
    document.getElementById('coil_remark').value = initialCoilRemark;

    // ล็อก (Disable) อินพุตทั้งหมดกลับตามเดิม
    var inputs = document.querySelectorAll('.slitter-input');
    inputs.forEach(function(input) {
        input.disabled = true;
    });

    // ล็อก (Disable) ปุ่มกดกลับเป็นสีเทา
    document.getElementById('btn_save_slitter').disabled = true;
    document.getElementById('btn_cancel_slitter').disabled = true;

    // ล้าง Hilight แถวในตาราง
    var rows = document.querySelectorAll('#job_order_table tbody tr');
    rows.forEach(function(r) { r.classList.remove('selected-row'); });
}

// ฟังก์ชันตรวจสอบความถูกต้องก่อนบันทึก
function validate_form() {
    var jobOrder = document.getElementById('selected_job_order').value;
    if (!jobOrder) {
        alert('Please select the Job Order from Group 2 before saving the data.');
        return false;
    }

    var numStand = parseFloat(document.getElementById('num_coil_slit').value) || 0;
    var slitWidth = parseFloat(document.getElementById('coil_slit_width').value) || 0;

    if (numStand <= 0 || slitWidth <= 0) {
        alert('Please enter the correct Number of Coil Slits and Coil Slit Width.');
        return false;
    }

    // คำนวณความกว้างรวมของการซอย
    var totalSlitWidth = slitWidth * numStand;
    var maxAllowedWidth = (blnSlitWidth > 0) ? blnSlitWidth : coilWidth;
    var widthTypeLabel = (blnSlitWidth > 0) ? 'Balance Slit Width' : 'Coil Width';

    if (totalSlitWidth > maxAllowedWidth) {
        alert('⚠️ The data could not be saved!\n\n' +
              'Total width after slicing (' + slitWidth.toFixed(2) + ' mm x ' + numStand + ' Stand = ' + totalSlitWidth.toFixed(2) + ' mm)\n' +
              'It is worth more' + widthTypeLabel + ' as specified (' + maxAllowedWidth.toFixed(2) + ' mm)');
        return false;
    }

    // ตรวจสอบวันที่
    var startDt = document.getElementById('start_datetime_slit').value;
    var endDt = document.getElementById('end_datetime_slit').value;

    if (startDt && endDt && new Date(startDt) > new Date(endDt)) {
        alert('The start time (Start Date time Slit) cannot be greater than the end time (End Date time Slit)');
        return false;
    }

    return confirm('Confirm that the Slitter Process data has been saved?');
}

// ฟังก์ชันสำหรับเรียกหน้า Print Coil Product Label
function printCoilLabel(nodeId) {
    // ดึงรหัส Coil จาก ID ของ Node (แปลงตัวขีดล่าง _ กลับเป็นขีด - หากมี)
    // หรือแปลงแบบยืดหยุ่นรองรับรูปแบบ COIL NO
    var rawCoilNo = nodeId.replace(/_/g, '-'); 
    
    // ยืนยันการสั่งพิมพ์
    if (confirm("🖨️ You want to open the label printing page for Coil No: " + rawCoilNo + " Is it right?")) {
        var url = "print_coil_product_label_mats.php?coilno=" + encodeURIComponent(rawCoilNo);
        window.open(url, '_blank'); // เปิดใน Tab ใหม่
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // ดักจับการคลิกบนแผนภูมิ Genealogy Tree
    var treeContainer = document.getElementById("genealogy_tree_container");
    if (treeContainer) {
        treeContainer.addEventListener("click", function(e) {
            // ค้นหา Element ที่ถูกคลิก ว่าอยู่ใน Node ที่มี data-coilno หรือไม่
            var targetNode = e.target.closest(".clickable-coil-node") || e.target.closest(".childNode");
            
            if (targetNode) {
                var coilNo = "";
                
                // กรณีคลิกโดน div ที่มี data-coilno
                if (targetNode.dataset && targetNode.dataset.coilno) {
                    coilNo = targetNode.dataset.coilno;
                } else {
                    // กรณีกดโดนพื้นที่ขอบกล่อง SVG ให้หา element ข้างในที่มี data-coilno
                    var innerData = targetNode.querySelector("[data-coilno]");
                    if (innerData) {
                        coilNo = innerData.dataset.coilno;
                    }
                }

                // ถ้าพบรหัส Coil ให้เปิดหน้า Print Label
                if (coilNo) {
                    if (confirm("🖨️ You want to open the label printing page for Coil No: " + coilNo + " Is it right?")) {
                        var url = "print_coil_product_label_mats.php?coilno=" + encodeURIComponent(coilNo);
                        window.open(url, '_blank');
                    }
                }
            }
        });
    }
});
</script>
</html>