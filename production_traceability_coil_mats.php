<?php
// เริ่ม session ก่อน ANY output
session_start();

// รับค่า cno (PRODUCTION NO) จาก URL Parameter
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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
        mermaid.initialize({ startOnLoad: false, theme: 'neutral' });
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

        /* Modern Table Styles & Font Scaling Up */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
            padding: 16px 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
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
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .badge-prod {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-level {
            display: inline-block;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* เมนูแท็บด้านบนสำหรับเลือกประเภทมาสเตอร์การผลิต */
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
                        <tbody>
                        </tbody>
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

<?php   
include 'include/'.$folder_func.'/navigation.php';
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Traceability Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="open_coil_traceability()" class="btn-tab-item">Parent to Child </button>
                <button onclick="open_coil_traceability_child()" class="btn-tab-item">Child to Parent </button>
                <button onclick="open_production_traceability_coil()" class="btn-tab-item active">Production No to Parent Coil</button>
                <button onclick="open_coil_traceability_production()" class="btn-tab-item">Parent Coil to Production No </button>
            </div>
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">PRODUCTION NO</label>
                    <input class="form-control-minimal" name="coil_no" id="coil_no" type="text" placeholder="Enter Production No (e.g., P260316-F-07)" value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>

                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Coil Traceability Genealogy Tree (Child to Parent Path)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;">Level</th>
                                        <th style="width: 150px; text-align: center;">Coil No</th>
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
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    if (!empty($cno)) {
                                        // SQL ปรับแต่งการ CAST เพิ่มความสอดคล้องของชนิดข้อมูล
                                        $sql = "WITH ChildToParent AS (
                                            SELECT 
                                                CAST(c.COIL_NO AS VARCHAR(100)) AS Node_ID,
                                                CAST(c.PRODUCT_REFERENCE AS VARCHAR(100)) AS Parent_Node,
                                                CAST(c.ALLOY AS VARCHAR(50)) AS ALLOY,
                                                CAST(c.TEMPER AS VARCHAR(50)) AS TEMPER,
                                                CAST(c.THICKNESS AS FLOAT) AS THICKNESS,
                                                CAST(c.WIDTH AS FLOAT) AS WIDTH,
                                                CAST(c.COIL_BALANCEWEIGHT AS FLOAT) AS WEIGHT,
                                                CAST(c.COIL_STATUS AS VARCHAR(50)) AS STATUS,
                                                CAST(crsh.PRODUCT_NO AS VARCHAR(100)) AS PRODUCT_NO,
                                                CAST(crsh.CRSH_ACTUALWEIGHT AS FLOAT) AS CRSH_ACTUALWEIGHT,
                                                CAST(crsh.CRSH_STATUS AS VARCHAR(50)) AS CRSH_STATUS,
                                                1 AS PathLevel
                                            FROM CRSHPROD1 crsh
                                            INNER JOIN COILPROD1 c ON crsh.COIL_NO = c.COIL_NO
                                            WHERE crsh.PRODUCT_NO = :target_pd

                                            UNION ALL

                                            SELECT 
                                                CAST(p.COIL_NO AS VARCHAR(100)) AS Node_ID,
                                                CAST(p.PRODUCT_REFERENCE AS VARCHAR(100)) AS Parent_Node,
                                                CAST(p.ALLOY AS VARCHAR(50)) AS ALLOY,
                                                CAST(p.TEMPER AS VARCHAR(50)) AS TEMPER,
                                                CAST(p.THICKNESS AS FLOAT) AS THICKNESS,
                                                CAST(p.WIDTH AS FLOAT) AS WIDTH,
                                                CAST(p.COIL_BALANCEWEIGHT AS FLOAT) AS WEIGHT,
                                                CAST(p.COIL_STATUS AS VARCHAR(50)) AS STATUS,
                                                CAST(NULL AS VARCHAR(100)) AS PRODUCT_NO,
                                                CAST(NULL AS FLOAT) AS CRSH_ACTUALWEIGHT,
                                                CAST(NULL AS VARCHAR(50)) AS CRSH_STATUS,
                                                cp.PathLevel + 1 AS PathLevel
                                            FROM ChildToParent cp
                                            INNER JOIN COILPROD1 p ON cp.Parent_Node = p.COIL_NO
                                            WHERE cp.Parent_Node IS NOT NULL 
                                              AND cp.Parent_Node <> ''
                                              AND cp.Parent_Node <> cp.Node_ID
                                        )
                                        SELECT 
                                            (MAX(PathLevel) OVER() - PathLevel) AS [Level],
                                            Node_ID AS COIL_NO,
                                            Parent_Node AS PRODUCT_REFERENCE,
                                            ALLOY,
                                            TEMPER,
                                            THICKNESS,
                                            WIDTH,
                                            WEIGHT AS COIL_BALANCEWEIGHT,
                                            STATUS AS COIL_STATUS,
                                            PRODUCT_NO,
                                            CRSH_ACTUALWEIGHT,
                                            CRSH_STATUS
                                        FROM ChildToParent
                                        ORDER BY [Level] ASC";

                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute([':target_pd' => $cno]);
                                 
                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $safe_coil_no = htmlspecialchars($row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $safe_parent  = htmlspecialchars($row['PRODUCT_REFERENCE'] ?? '-', ENT_QUOTES, 'UTF-8');
                                            $safe_prod    = htmlspecialchars($row['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8');

                                            $thick = !is_null($row['THICKNESS']) ? number_format((float)$row['THICKNESS'], 2, '.', '') : '0.00';
                                            $width = !is_null($row['WIDTH']) ? number_format((float)$row['WIDTH'], 2, '.', '') : '0.00';
                                            $dim   = $thick . ' x ' . $width;

                                            $c_weight = !is_null($row['COIL_BALANCEWEIGHT']) ? number_format((float)$row['COIL_BALANCEWEIGHT'], 2) : '-';
                                            $p_weight = !is_null($row['CRSH_ACTUALWEIGHT']) ? number_format((float)$row['CRSH_ACTUALWEIGHT'], 2) : '-';

                                            echo "<tr class='clickable-row' data-coil='".$safe_coil_no."'>";
                                            echo "<td style='text-align:center;'><span class='badge-level'>".htmlspecialchars($row['Level'], ENT_QUOTES, 'UTF-8')."</span></td>";
                                            echo "<td style='text-align:center;'><span class='badge-coil'>".$safe_coil_no."</span></td>";
                                            echo "<td style='text-align:center; color:#64748b;'>".$safe_parent."</td>";
                                            echo "<td style='text-align:center;'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td style='text-align:center;'>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td style='text-align:center; font-weight:600;'>".$dim."</td>";
                                            echo "<td style='text-align:right;'>".$c_weight."</td>";
                                            echo "<td style='text-align:center;'>".htmlspecialchars($row['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                            
                                            if ($safe_prod !== '-' && strtoupper($safe_prod) === strtoupper($cno)) {
                                                echo "<td style='text-align:center;'><span class='badge-prod' style='background-color:#fef08a; color:#854d0e; border-color:#fde047;'>".$safe_prod." ★</span></td>";
                                            } else if ($safe_prod !== '-') {
                                                echo "<td style='text-align:center;'><span class='badge-prod'>".$safe_prod."</span></td>";
                                            } else {
                                                echo "<td style='text-align:center;'>-</td>";
                                            }

                                            echo "<td style='text-align:right;'>".$p_weight."</td>";
                                            echo "<td style='text-align:center;'>".htmlspecialchars($row['CRSH_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "</tr>";                                                         
                                        }
                                    }
                                ?>                                              
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Graph Coil Traceability Genealogy Tree (Direct Path)</h4>
                        </div>
                        
                        <?php
                        $mermaid_code = "";
                        if (!empty($cno)) {
                            $sql_graph = "WITH ChildToParent AS (
                                            SELECT 
                                                CAST(c.COIL_NO AS VARCHAR(100)) AS Node_ID,
                                                CAST(c.PRODUCT_REFERENCE AS VARCHAR(100)) AS Parent_Node,
                                                CAST(c.THICKNESS AS FLOAT) AS THICKNESS,
                                                CAST(c.WIDTH AS FLOAT) AS WIDTH,
                                                CAST(crsh.PRODUCT_NO AS VARCHAR(100)) AS PRODUCT_NO,
                                                CAST(crsh.CRSH_STATUS AS VARCHAR(50)) AS CRSH_STATUS,
                                                1 AS PathLevel
                                            FROM CRSHPROD1 crsh
                                            INNER JOIN COILPROD1 c ON crsh.COIL_NO = c.COIL_NO
                                            WHERE crsh.PRODUCT_NO = :target_pd

                                            UNION ALL

                                            SELECT 
                                                CAST(p.COIL_NO AS VARCHAR(100)) AS Node_ID,
                                                CAST(p.PRODUCT_REFERENCE AS VARCHAR(100)) AS Parent_Node,
                                                CAST(p.THICKNESS AS FLOAT) AS THICKNESS,
                                                CAST(p.WIDTH AS FLOAT) AS WIDTH,
                                                CAST(NULL AS VARCHAR(100)) AS PRODUCT_NO,
                                                CAST(NULL AS VARCHAR(50)) AS CRSH_STATUS,
                                                cp.PathLevel + 1 AS PathLevel
                                            FROM ChildToParent cp
                                            INNER JOIN COILPROD1 p ON cp.Parent_Node = p.COIL_NO
                                            WHERE cp.Parent_Node IS NOT NULL 
                                              AND cp.Parent_Node <> ''
                                              AND cp.Parent_Node <> cp.Node_ID
                                        )
                                        SELECT 
                                            (MAX(PathLevel) OVER() - PathLevel) AS [Level],
                                            Node_ID AS COIL_NO,
                                            Parent_Node AS PRODUCT_REFERENCE,
                                            THICKNESS,
                                            WIDTH,
                                            PRODUCT_NO,
                                            CRSH_STATUS
                                        FROM ChildToParent
                                        ORDER BY [Level] ASC";

                            $stmt_graph = $conn->prepare($sql_graph);
                            $stmt_graph->execute([':target_pd' => $cno]);

                            $nodes = [];
                            $edges = [];
                            $yellow_nodes = [];
                            $green_nodes = [];
                            $target_node = "";

                            while ($g_row = $stmt_graph->fetch(PDO::FETCH_ASSOC)) {
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

                                    if (strtoupper($prod) === strtoupper($cno)) {
                                        $target_node = $prod_id;
                                    }

                                    $edge_key = "{$coil_id} --> {$prod_id}";
                                    if (!in_array($edge_key, $edges)) {
                                        $edges[] = $edge_key;
                                    }
                                }
                            }

                            if (!empty($nodes)) {
                                $mermaid_code = "graph TD\n";
                                foreach ($nodes as $n) {
                                    $mermaid_code .= "    {$n}\n";
                                }
                                foreach ($edges as $e) {
                                    $mermaid_code .= "    {$e}\n";
                                }

                                if (!empty($yellow_nodes)) {
                                    $mermaid_code .= "    classDef yellowNode fill:#ffffaa,stroke:#eab308,stroke-width:1px;\n";
                                    $mermaid_code .= "    class " . implode(",", array_keys($yellow_nodes)) . " yellowNode;\n";
                                }
                                if (!empty($green_nodes)) {
                                    $mermaid_code .= "    classDef greenNode fill:#dcfce7,stroke:#22c55e,stroke-width:1px;\n";
                                    $mermaid_code .= "    class " . implode(",", array_keys($green_nodes)) . " greenNode;\n";
                                }
                                if (!empty($target_node)) {
                                    $mermaid_code .= "    classDef targetNode fill:#fef08a,stroke:#ca8a04,stroke-width:3px;\n";
                                    $mermaid_code .= "    class {$target_node} targetNode;\n";
                                }
                            }
                        }
                        ?>

                        <div style="overflow-x: auto; text-align: center; padding: 10px;">
                            <?php if (!empty($mermaid_code)): ?>
                                <div class="mermaid">
                                    <?php echo $mermaid_code; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #94a3b8; padding: 20px;">No graph data found. Please enter the production number to search.</p>
                            <?php endif; ?>
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
    $("#user_table").DataTable({
        scrollY: "500px",
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 25,
        order: [[0, 'asc']],
        buttons: []
    });

    $('#user_table tbody').on('click', 'tr.clickable-row', function () {
        var coilNo = $(this).attr('data-coil');
        if (coilNo) {
            show_detail_coil(coilNo);
        }
    });

    mermaid.run();
});

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
    window.location.assign('production_traceability_coil_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
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
</script>
</html>