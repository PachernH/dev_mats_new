<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// รับค่า cno จาก URL Parameter (ค้นหาด้วย Parent Coil)
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    
    <!-- Mermaid.js สำหรับวาด Genealogy Tree Graph -->
    <script src="assets/js/mermaid.min.js"></script>
    <script>
        mermaid.initialize({ 
            startOnLoad: false, 
            theme: 'neutral',
            securityLevel: 'loose'
        });
    </script>     

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
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

        /* Filter Section Layout */
        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Form Styling Control */
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }

        /* ช่องป้อน ค้นหาแบบไร้ขอบสี่เหลี่ยม */
        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 42px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus {
            border-bottom-color: #2563eb !important;
            outline: none;
        }

        /* Buttons Styling */
        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            height: 42px;
            padding: 0 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background-color: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Mermaid Container & Controls */
        .mermaid-card-wrapper {
            position: relative;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }

        .mermaid-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .btn-zoom {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-zoom:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .mermaid-container {
            width: 100%;
            min-height: 180px;      /* เมื่อไม่มีข้อมูล จะเตี้ยลงกะทัดรัด */
            max-height: 700px;      /* เมื่อมีข้อมูลกราฟ จะขยายได้สบายๆ ไม่เกิน 700px */
            overflow: auto;
            text-align: center;
            padding: 24px;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #mermaid_output {
            display: inline-block;
            margin: auto;
        }

        /* ควบคุม SVG ให้ไม่ถูกบีบและปรับขนาดตามการ Zoom */
        #mermaid_output svg {
            max-width: none !important;
            height: auto !important;
            transition: width 0.15s ease-out;
            display: block;
            margin: 0 auto;
        }

        /* ขยายขนาดฟอนต์ของ Node ให้อ่านชัดเจน */
        #mermaid_output .node text {
            font-size: 14px !important;
            font-family: 'Segoe UI', Tahoma, sans-serif !important;
            font-weight: 600 !important;
        }
        #mermaid_output .node rect {
            rx: 6px;
            ry: 6px;
            stroke-width: 1.5px !important;
        }

        /* Modern Table Styles & Font Scaling Up */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table, #mat_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th, #mat_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 12px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #user_table tbody td, #mat_table tbody td {
            padding: 14px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            white-space: nowrap;
        }

        /* รองรับการ Click แถว (Line Clickable) */
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        #user_table tbody tr:hover td {
            background-color: #e0f2fe !important;
        }

        /* Badge แสดงข้อมูล */
        .badge-coil {
            display: inline-block;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .badge-prod {
            display: inline-block;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-batch {
            display: inline-block;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-mat {
            display: inline-block;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-level {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        /* Structure Modal แบบ Vertical Table */
        .modal-content {
            border-radius: 14px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
        }
        .modal-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 20px 24px;
        }
        .modal-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        .modal-body {
            padding: 24px 32px !important;
        }

        #tbl_batch {
            width: 100% !important;
            display: flex;
            flex-direction: row;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        #tbl_batch thead {
            display: block;
            flex: 0 0 45%;
            background-color: #f8fafc;
            border-right: 2px solid #e2e8f0;
        }
        #tbl_batch tbody {
            display: block;
            flex: 1;
            background-color: #ffffff;
        }
        #tbl_batch tr {
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        #tbl_batch thead th {
            display: block;
            width: 100% !important;
            padding: 10px 16px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #475569 !important;
            text-align: left !important;
            border-bottom: 1px solid #e2e8f0 !important;
            height: 42px;
            box-sizing: border-box;
        }
        #tbl_batch tbody td {
            display: block;
            width: 100% !important;
            padding: 10px 16px !important;
            font-size: 14px !important;
            font-weight: 500;
            color: #1e293b !important;
            text-align: left !important;
            border-bottom: 1px solid #f1f5f9 !important;
            height: 42px;
            box-sizing: border-box;
        }
        
        #tbl_batch thead th:last-child, 
        #tbl_batch tbody td:last-child {
            border-bottom: none !important;
        }

        .modal-body .dataTables_length,
        .modal-body .dataTables_filter,
        .modal-body .dataTables_info,
        .modal-body .dataTables_paginate {
            display: none !important;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 15px 24px;
        }

        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }        
    </style>
</head>
<body>

    <div class="modal fade" id="dataIn_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document" style="max-width: 600px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">📋 Coil Production Specifications Details</h4>
                </div>
                <div class="modal-body">                
                    <table id="tbl_batch" class="table">
                        <thead>
                            <tr>   
                                <th>COIL NO</th>
                                <th>PRODUCT REFERENCE</th>
                                <th>COIL TYPE</th>
                                <th>ALLOY</th>
                                <th>TEMPER</th>
                                <th>GRADE</th>
                                <th>THICKNESS</th>
                                <th>WIDTH</th>
                                <th>SURFACE GRADE</th>
                                <th>METALLURGICAL GRADE</th>
                                <th>COIL ACTUAL WEIGHT</th>
                                <th>COIL BALANCE WEIGHT</th>
                                <th>COIL WORK PROCESS</th>
                                <th>COIL NEXT PROCESS</th>
                                <th>COIL STATUS</th>       
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:8px; font-size:14px; padding: 8px 24px; font-weight:600;">Close</button>
                </div>
            </div>
        </div>
    </div>

<div class="wrapper">
<?php $menu = 'mats';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#"> ✏️ Traceability</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="open_coil_traceability()" class="btn-tab-item">Parent to Child </button>
                <button onclick="open_coil_traceability_child()" class="btn-tab-item">Child to Parent </button>
                <button onclick="open_production_traceability_coil()" class="btn-tab-item">Production No to Parent Coil & Raw Material</button>
                <button onclick="open_coil_traceability_production()" class="btn-tab-item active">Parent Coil to Production No </button>
            </div>
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">PARENT COIL NO</label>
                    <input class="form-control-minimal" name="coil_no" id="coil_no" type="text" placeholder="Enter Parent Coil Code (e.g., C260307-2-01)" value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>

                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <?php
                include 'function_mats.php';
                include("dbcon_mats-new.php");
                ini_set('max_execution_time', 300);

                $target_batch_no = "";
                $rawMaterials = [];
                $treeRows = [];

                if (!empty($cno)) {
                    // 1. ดึง BATCH_NO ของ Parent Coil ที่ค้นหาจาก COILPROD1
                    $sqlBatch = "SELECT TOP 1 BATCH_NO FROM COILPROD1 WHERE COIL_NO = :target_coil";
                    $stmtBatch = $conn->prepare($sqlBatch);
                    $stmtBatch->execute([':target_coil' => $cno]);
                    $batchResult = $stmtBatch->fetch(PDO::FETCH_ASSOC);
                    $target_batch_no = trim($batchResult['BATCH_NO'] ?? '');

                    // 2. นำ BATCH_NO ไปค้นหาใน WIP_ISSUE_TRANSACTIONS
                    if (!empty($target_batch_no)) {
                        $sqlMat = "SELECT 
                                        issue_id,
                                        issue_no,
                                        container_no,
                                        packing_no,
                                        materials_no,
                                        pack_list_no,
                                        invoice_no,
                                        country_of_origin,
                                        net_weight_kg,
                                        piece_count,
                                        issued_by,
                                        issued_date
                                   FROM WIP_ISSUE_TRANSACTIONS
                                   WHERE LTRIM(RTRIM(issue_no)) = :batch_no
                                   ORDER BY issue_id ASC";
                        $stmtMat = $conn->prepare($sqlMat);
                        $stmtMat->execute([':batch_no' => $target_batch_no]);
                        $rawMaterials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
                    }

                    // 3. Top-Down Recursive Query ค้นหา Child Coils และ Production Finished Goods
                    $sql = "WITH CoilTree AS (
                        SELECT 
                            c.COIL_NO AS Node_ID,
                            c.PRODUCT_REFERENCE AS Parent_Node,
                            c.BATCH_NO,
                            c.JOB_PROCESS AS Job_Order,
                            c.ALLOY,
                            c.TEMPER,
                            c.THICKNESS,
                            c.WIDTH,
                            c.COIL_BALANCEWEIGHT AS WEIGHT,
                            c.COIL_STATUS AS STATUS,
                            0 AS [Level]
                        FROM COILPROD1 AS c 
                        WHERE c.COIL_NO = :target_coil

                        UNION ALL

                        SELECT 
                            child.COIL_NO AS Node_ID,
                            child.PRODUCT_REFERENCE AS Parent_Node,
                            child.BATCH_NO,
                            child.JOB_PROCESS AS Job_Order,
                            child.ALLOY,
                            child.TEMPER,
                            child.THICKNESS,
                            child.WIDTH,
                            child.COIL_BALANCEWEIGHT AS WEIGHT,
                            child.COIL_STATUS AS STATUS,
                            parent.[Level] + 1 AS [Level]
                        FROM COILPROD1 AS child
                        INNER JOIN CoilTree AS parent ON child.PRODUCT_REFERENCE = parent.Node_ID
                        WHERE child.PRODUCT_REFERENCE IS NOT NULL 
                          AND child.PRODUCT_REFERENCE <> ''
                    )
                    SELECT 
                        t.[Level],
                        t.Node_ID AS COIL_NO,
                        t.Parent_Node AS PRODUCT_REFERENCE,
                        t.BATCH_NO,
                        t.ALLOY,
                        t.TEMPER,
                        t.THICKNESS,
                        t.WIDTH,
                        t.WEIGHT AS COIL_BALANCEWEIGHT,
                        t.STATUS AS COIL_STATUS,
                        crsh.PRODUCT_NO,
                        crsh.PRODUCT_ID,
                        crsh.CRSH_ACTUALWEIGHT,
                        crsh.CRSH_STATUS
                    FROM CoilTree t
                    LEFT JOIN CRSHPROD1 crsh ON t.Node_ID = crsh.COIL_NO
                    ORDER BY t.[Level] ASC, t.Node_ID ASC";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([':target_coil' => $cno]);
                    $treeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            ?>

            <!-- กล่องแสดงรายการวัตถุดิบ (Raw Material Ingot) -->
            <?php if (!empty($cno)): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card" style="border-left: 4px solid #0284c7;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h4 style="margin:0; font-weight:700; color:#0369a1; font-size:17px;">
                                📦 Raw Materials Ingot Used for Parent Coil: <strong><?php echo htmlspecialchars($cno); ?></strong>
                                &nbsp;(Batch: <span class="badge-batch"><?php echo !empty($target_batch_no) ? htmlspecialchars($target_batch_no) : 'Not Found'; ?></span>)
                            </h4>
                            <span style="font-size: 13px; color: #64748b;">Source: WIP_ISSUE_TRANSACTIONS</span>
                        </div>

                        <div class="table-responsive table-full-width">
                            <table id="mat_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Batch No (Issue No)</th>
                                        <th style="text-align: center;">Invoice No.</th>
                                        <th style="text-align: center;">Pack List No.</th>
                                        <th style="text-align: center;">Container No.</th>
                                        <th style="text-align: center;">Packing No.</th>
                                        <th style="text-align: center;">Country of Origin</th>
                                        <th style="text-align: right;">Net Weight (Kg)</th>
                                        <th style="text-align: center;">Piece Count</th>
                                        <th style="text-align: center;">Charge Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($rawMaterials)): ?>
                                        <?php foreach ($rawMaterials as $idx => $m): 
                                            $issue_date = !empty($m['issued_date']) ? date('Y-m-d H:i', strtotime($m['issued_date'])) : '-';
                                        ?>
                                            <tr>
                                                <td align="center"><?php echo $idx + 1; ?></td>
                                                <td align="center"><span class="badge-batch"><?php echo htmlspecialchars($m['issue_no'] ?? '-'); ?></span></td>
                                                <td align="center" style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($m['invoice_no'] ?? '-'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($m['pack_list_no'] ?? '-'); ?></td>
                                                <td align="center"><span class="badge-mat"><?php echo htmlspecialchars($m['container_no'] ?? '-'); ?></span></td>
                                                <td align="center" style="font-weight:700; color:#0369a1;">📦 <?php echo htmlspecialchars($m['packing_no'] ?? '-'); ?></td>
                                                <td align="center" style="font-weight:600; color:#0284c7;"><?php echo htmlspecialchars($m['country_of_origin'] ?? '-'); ?></td>
                                                <td align="right" style="font-weight:700; color:#059669;"><?php echo number_format((float)($m['net_weight_kg'] ?? 0), 2); ?></td>
                                                <td align="center"><?php echo number_format((float)($m['piece_count'] ?? 0)); ?></td>
                                                <td align="center" style="color:#64748b;"><?php echo htmlspecialchars($issue_date); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" align="center" style="color: #94a3b8; padding: 20px;">
                                                <?php echo empty($target_batch_no) ? 'Parent Coil does not have BATCH_NO linked.' : 'No WIP Issue Transactions found for Batch No: ' . htmlspecialchars($target_batch_no); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ตาราง Coil Traceability Table (Top-Down) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Coil Traceability Genealogy Tree (Parent to Production Path)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;">Level</th>
                                        <th style="width: 150px; text-align: center;">Coil No</th>
                                        <th style="width: 140px; text-align: center;">Batch No</th>
                                        <th style="width: 150px; text-align: center;">Parent Coil Ref.</th>
                                        <th style="width: 80px; text-align: center;">Alloy</th>
                                        <th style="width: 80px; text-align: center;">Temper</th>
                                        <th style="width: 90px; text-align: center;">Thick x Width</th>
                                        <th style="width: 100px; text-align: center;">Coil Weight</th>
                                        <th style="width: 100px; text-align: center;">Coil Status</th>
                                        <th style="width: 150px; text-align: center;">Product No (Finished)</th>
                                        <th style="width: 100px; text-align: center;">Product Weight</th>
                                        <th style="width: 110px; text-align: center;">CRSH Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    foreach($treeRows as $row) {
                                        $safe_coil_no  = htmlspecialchars($row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_batch_no = htmlspecialchars($row['BATCH_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $safe_parent   = htmlspecialchars($row['PRODUCT_REFERENCE'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $safe_prod     = htmlspecialchars($row['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8');

                                        $thick = !is_null($row['THICKNESS']) ? number_format((float)$row['THICKNESS'], 2, '.', '') : '0.00';
                                        $width = !is_null($row['WIDTH']) ? number_format((float)$row['WIDTH'], 2, '.', '') : '0.00';
                                        $dim   = $thick . ' x ' . $width;

                                        $c_weight = !is_null($row['COIL_BALANCEWEIGHT']) ? number_format((float)$row['COIL_BALANCEWEIGHT'], 2) : '-';
                                        $p_weight = !is_null($row['CRSH_ACTUALWEIGHT']) ? number_format((float)$row['CRSH_ACTUALWEIGHT'], 2) : '-';

                                        echo "<tr class='clickable-row' data-coil='".$safe_coil_no."'>";
                                        echo "<td style='text-align:center;'><span class='badge-level'>".htmlspecialchars($row['Level'], ENT_QUOTES, 'UTF-8')."</span></td>";
                                        echo "<td style='text-align:center;'><span class='badge-coil'>".$safe_coil_no."</span></td>";
                                        echo "<td style='text-align:center;'><span class='badge-batch'>".$safe_batch_no."</span></td>";
                                        echo "<td style='text-align:center; color:#64748b;'>".$safe_parent."</td>";
                                        echo "<td style='text-align:center;'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align:center;'>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align:center; font-weight:600;'>".$dim."</td>";
                                        echo "<td style='text-align:right;'>".$c_weight."</td>";
                                        echo "<td style='text-align:center;'>".htmlspecialchars($row['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align:center;'>".($safe_prod !== '-' ? "<span class='badge-prod'>".$safe_prod."</span>" : "-")."</td>";
                                        echo "<td style='text-align:right;'>".$p_weight."</td>";
                                        echo "<td style='text-align:center;'>".htmlspecialchars($row['CRSH_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "</tr>";                                                         
                                    }
                                ?>                                              
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- กล่องแสดง Graph Genealogy Tree -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">
                                📋 Graph Coil & Raw Material Traceability Genealogy Tree
                            </h4>
                            <span style="font-size: 12px; color: #64748b;">(เลื่อน Scrollbar ซ้าย-ขวา หรือใช้ปุ่ม Zoom เพื่อดูรายละเอียด)</span>
                        </div>
                        
                        <?php
                        $mermaid_code = "";
                        if (!empty($cno) && !empty($treeRows)) {
                            $nodes = [];
                            $edges = [];
                            $yellow_nodes = [];
                            $green_nodes = [];
                            $blue_nodes = [];

                            // 1. สร้าง Node Coil และ Product
                            foreach ($treeRows as $g_row) {
                                $coil   = htmlspecialchars($g_row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                $parent = htmlspecialchars($g_row['PRODUCT_REFERENCE'] ?? '', ENT_QUOTES, 'UTF-8');
                                $prod   = htmlspecialchars($g_row['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8');

                                $thick = !is_null($g_row['THICKNESS']) ? number_format((float)$g_row['THICKNESS'], 2, '.', '') : '0.00';
                                $width = !is_null($g_row['WIDTH']) ? number_format((float)$g_row['WIDTH'], 2, '.', '') : '0.00';
                                $dim   = "{$thick} x {$width}";

                                $coil_id = preg_replace('/[^A-Za-z0-9_]/', '_', $coil);
                                $nodes[$coil_id] = "{$coil_id}[\"<b>{$coil}</b><br/>{$dim}\"]";
                                $yellow_nodes[$coil_id] = true;

                                if (!empty($parent)) {
                                    $parent_id = preg_replace('/[^A-Za-z0-9_]/', '_', $parent);
                                    if (!isset($nodes[$parent_id])) {
                                        $nodes[$parent_id] = "{$parent_id}[\"<b>{$parent}</b>\"]";
                                        $yellow_nodes[$parent_id] = true;
                                    }
                                    $edge_key = "{$parent_id} --> {$coil_id}";
                                    if (!in_array($edge_key, $edges)) {
                                        $edges[] = $edge_key;
                                    }
                                }

                                if (!empty($prod)) {
                                    $prod_id = preg_replace('/[^A-Za-z0-9_]/', '_', $prod);
                                    $crsh_st = htmlspecialchars($g_row['CRSH_STATUS'] ?? '', ENT_QUOTES, 'UTF-8');
                                    
                                    $nodes[$prod_id] = "{$prod_id}[\"<b>{$prod}</b><br/>{$crsh_st}\"]";
                                    $green_nodes[$prod_id] = true;

                                    $edge_key = "{$coil_id} --> {$prod_id}";
                                    if (!in_array($edge_key, $edges)) {
                                        $edges[] = $edge_key;
                                    }
                                }
                            }

                            // 2. สร้าง Node วัตถุดิบ (กะทัดรัด 2 ชั้น: container_no / country_of_origin) ชี้เข้าหา Target Parent Coil
                            if (!empty($rawMaterials)) {
                                $target_coil_id = preg_replace('/[^A-Za-z0-9_]/', '_', $cno);

                                $uniqueContainers = [];
                                foreach ($rawMaterials as $m) {
                                    $cont = trim($m['container_no'] ?? '-');
                                    $orig = trim($m['country_of_origin'] ?? '-');
                                    if ($cont !== '-' && $cont !== '') {
                                        $uniqueContainers[$cont] = $orig;
                                    }
                                }

                                foreach ($uniqueContainers as $cont => $orig) {
                                    $cont_id = "CONT_" . preg_replace('/[^A-Za-z0-9_]/', '_', $cont);
                                    $nodes[$cont_id] = "{$cont_id}[\"<b>{$cont}</b><br/>{$orig}\"]";
                                    $blue_nodes[$cont_id] = true;

                                    $edge_key = "{$cont_id} --> {$target_coil_id}";
                                    if (!in_array($edge_key, $edges)) {
                                        $edges[] = $edge_key;
                                    }
                                }
                            }

                            // 3. รวม Mermaid Code พร้อมเว้นบรรทัด
                            if (!empty($nodes)) {
                                $mermaid_code = "graph TD\n";
                                foreach ($nodes as $n) {
                                    $mermaid_code .= "    " . $n . "\n";
                                }
                                foreach ($edges as $e) {
                                    $mermaid_code .= "    " . $e . "\n";
                                }

                                if (!empty($blue_nodes)) {
                                    $mermaid_code .= "    classDef blueNode fill:#e0f2fe,stroke:#0284c7,stroke-width:1.5px,color:#0369a1;\n";
                                    $mermaid_code .= "    class " . implode(",", array_keys($blue_nodes)) . " blueNode;\n";
                                }
                                if (!empty($yellow_nodes)) {
                                    $mermaid_code .= "    classDef yellowNode fill:#ffffaa,stroke:#eab308,stroke-width:1.5px;\n";
                                    $mermaid_code .= "    class " . implode(",", array_keys($yellow_nodes)) . " yellowNode;\n";
                                }
                                if (!empty($green_nodes)) {
                                    $mermaid_code .= "    classDef greenNode fill:#dcfce7,stroke:#22c55e,stroke-width:1.5px;\n";
                                    $mermaid_code .= "    class " . implode(",", array_keys($green_nodes)) . " greenNode;\n";
                                }
                            }
                        }
                        ?>

                        <div class="mermaid-card-wrapper">
                            <!-- แถบเครื่องมือควบคุมการย่อ-ขยาย -->
                            <div class="mermaid-toolbar">
                                <span id="zoom_level_text" style="font-size: 13px; font-weight: 700; color: #475569; margin-right: 8px;">100%</span>
                                <button type="button" class="btn-zoom" onclick="zoomGraph(0.15)">🔍 + Zoom In</button>
                                <button type="button" class="btn-zoom" onclick="zoomGraph(-0.15)">🔍 - Zoom Out</button>
                                <button type="button" class="btn-zoom" onclick="resetZoomGraph()">↺ Reset</button>
                            </div>

                            <div class="mermaid-container" id="graph_scroll_box">
                                <?php if (!empty($mermaid_code)): ?>
                                    <textarea id="mermaid_source" style="display:none;"><?php echo htmlspecialchars($mermaid_code, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    <div id="mermaid_output"></div>
                                <?php else: ?>
                                    <p style="color: #94a3b8; margin: 0; font-size: 14px;">No graph data found. Please enter the coil number to search.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
$(document).ready(function() {
    // 1. วาดกราฟ Mermaid ทันที
    renderMermaidGraph();

    // 2. DataTables ของตาราง Coil Traceability
    $("#user_table").DataTable({
        scrollY: "400px",
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 25,
        order: [[0, 'asc']],
        buttons: []
    });

    // 3. DataTables ตารางวัตถุดิบ (รันเฉพาะเมื่อมีข้อมูลจริงเท่านั้น)
    <?php if (!empty($rawMaterials)): ?>
    $("#mat_table").DataTable({
        scrollY: "250px",
        scrollX: true,
        scrollCollapse: true,
        paging: false,
        searching: false,
        dom: 'Bfrtip',
        order: [[0, 'asc']],
        buttons: []
    });
    <?php endif; ?>

    // 4. คลิกแถวเพื่อเปิดดู Specification ของ Coil
    $('#user_table tbody').on('click', 'tr.clickable-row', function () {
        var coilNo = $(this).attr('data-coil');
        if (coilNo) {
            show_detail_coil(coilNo);
        }
    });
});

var currentZoomPercent = 100;
var originalSvgWidth = 0;

// ฟังก์ชันวาดกราฟ Mermaid
function renderMermaidGraph() {
    var sourceElement = document.getElementById("mermaid_source");
    var outputElement = document.getElementById("mermaid_output");
    if (!sourceElement || !outputElement) return;

    var graphDefinition = sourceElement.value.trim();
    if (graphDefinition === "") return;

    try {
        var uniqueId = 'mermaid_chart_' + Math.floor(Math.random() * 1000000);
        mermaid.render(uniqueId, graphDefinition).then(function(result) {
            outputElement.innerHTML = result.svg;

            var svgElement = outputElement.querySelector('svg');
            if (svgElement) {
                svgElement.removeAttribute('style');
                svgElement.style.maxWidth = 'none';

                // เก็บขนาดเริ่มต้นของ SVG เพื่อใช้คำนวณการย่อ-ขยายที่แท้จริง
                originalSvgWidth = svgElement.viewBox.baseVal.width || svgElement.getBoundingClientRect().width;
                applyZoom();
            }
        }).catch(function(err) {
            console.error("Mermaid Render Error:", err);
        });
    } catch (e) {
        console.error("Mermaid Execution Error:", e);
    }
}

// ควบคุมการย่อขยาย Graph แบบปรับความกว้าง SVG แท้จริง (คมชัด ไม่โดนบีบย่อ)
function zoomGraph(delta) {
    currentZoomPercent += (delta * 100);
    if (currentZoomPercent < 40) currentZoomPercent = 40;
    if (currentZoomPercent > 300) currentZoomPercent = 300;
    applyZoom();
}

function resetZoomGraph() {
    currentZoomPercent = 100;
    applyZoom();
}

function applyZoom() {
    var svg = document.querySelector("#mermaid_output svg");
    var zoomText = document.getElementById("zoom_level_text");

    if (svg && originalSvgWidth > 0) {
        svg.style.width = (originalSvgWidth * (currentZoomPercent / 100)) + "px";
    }

    if (zoomText) {
        zoomText.innerText = Math.round(currentZoomPercent) + "%";
    }
}

function show_detail_coil(do_no){     
    $('#tbl_batch').DataTable({
        "pageLength": 1,
        "destroy": true,
        "searching": false,
        "processing": true,
        "serverSide": true,
        "ajax": "model/data_coil_list_order_mats.php?COIL=" + encodeURIComponent(do_no),
        "columnDefs": [
            {
                "targets": 0,
                "render": function(data) {
                    return '<span class="badge" style="background-color:#eff6ff; color:#1d4ed8; font-weight:700; padding:6px 12px; border:1px solid #bfdbfe; border-radius:12px; display:inline-block; font-size:13px;">' + data + '</span>';
                }
            },
            { "targets": "_all", "defaultContent": "-" }
        ]
    });
    $("#dataIn_modal").modal('show');
}

function open_coil_traceability(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_traceability_mats.php?func='+encodeURIComponent(data_fun)); 
}

function open_coil_traceability_child(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_traceability_child_mats.php?func='+encodeURIComponent(data_fun)); 
}

function open_coil_traceability_production(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_traceability_production_mats.php?func='+encodeURIComponent(data_fun)); 
}

function open_production_traceability_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('production_traceability_coil_mats.php?func='+encodeURIComponent(data_fun)); 
}

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no").value; 
    window.location.assign('coil_traceability_production_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}
</script>
</html>