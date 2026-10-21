<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : 'K';
$group_func   = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$process_func = isset($_SESSION['PROCESS_GROUP']) ? htmlspecialchars($_SESSION['PROCESS_GROUP'], ENT_QUOTES, 'UTF-8') : '';

$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');

include("dbcon_mats-new.php");
include 'function_mats.php';

$row_data = null;
$coil_tree_list = [];
$is_weight_too_low = false;
$scrap_limit_weight = 0; // ค่าน้ำหนักเกณฑ์เกิด Scrap อัตโนมัติ

if (!empty($coil_no)) {
    // ดึงค่า Scrap Limit Width จากตาราง STNDMSTR14 ( default คือ 200 mm )
    $scrap_limit_width = 200.0;
    $sql_stnd = "SELECT VOLUM FROM STNDMSTR14 WHERE PROCESS = 'SS'";
    $stmt_stnd = $conn->prepare($sql_stnd);
    $stmt_stnd->execute();
    $stnd_data = $stmt_stnd->fetch(PDO::FETCH_ASSOC);
    if (!empty($stnd_data) && is_numeric($stnd_data['VOLUM'])) {
        $scrap_limit_width = (float)$stnd_data['VOLUM'];
    }

    // Query ดึงข้อมูล Coil หลัก
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
                p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_BALANCEWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.LINE_PROCESS, 
                p.BLN_SLITWIDTH, p.ACC_SLITWIDTH,
                i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row_data) {
        $master_width  = (float)($row_data['WIDTH'] ?? 0);
        $actual_weight = (float)($row_data['COIL_ACTUALWEIGHT'] ?? 0);
        $weight_per_mm = ($master_width > 0) ? ($actual_weight / $master_width) : 0;
        
        // คำนวณค่าน้ำหนักเกณฑ์ที่ใช้ทำ Scrap อัตโนมัติ (WeightPerMM * ScrapLimitWidth)
        $scrap_limit_weight = round($weight_per_mm * $scrap_limit_width, 2);

        $balance_weight = (float)($row_data['COIL_BALANCEWEIGHT'] ?? 0);
        
        // เช็คว่าถ้าน้ำหนักคงเหลือ < ค่าน้ำหนักเกณฑ์ทำ Scrap อัตโนมัติ ให้ถือว่าน้ำหนักน้อยเกินไปจนไม่สามารถแบ่งได้
        if ($balance_weight <= $scrap_limit_weight) {
            $is_weight_too_low = true;
        }

        // Query Genealogy Tree
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
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-top: 0; margin-bottom: 20px; }
        .card-title-g3 { color: #047857; border-bottom: 2px solid #a7f3d0; padding-bottom: 10px; font-weight: 700; font-size: 17px; margin-top: 0; margin-bottom: 20px; }
        .info-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .info-value { font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 18px; background-color: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #f1f5f9; min-height: 40px; display: flex; align-items: center; }
        .form-control-custom { width: 100%; height: 42px; padding: 8px 12px; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; transition: all 0.2s; }
        .form-control-custom:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
        .form-control-custom:disabled, .form-control-custom[disabled] { background-color: #f1f5f9 !important; color: #94a3b8 !important; cursor: not-allowed; border-color: #e2e8f0; }
        .custom-job-table { width: 100% !important; border-collapse: separate; border-spacing: 0; }
        .custom-job-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; font-size: 13px; padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .custom-job-table tbody td { padding: 10px; border-bottom: 1px solid #f1f5f9; font-size: 14px; white-space: nowrap; }
        .btn-save { background-color: #10b981 !important; color: #ffffff !important; font-weight: 700; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-save:hover { background-color: #059669 !important; }
        .btn-save:disabled, .btn-save[disabled] { background-color: #cbd5e1 !important; color: #94a3b8 !important; opacity: 0.65; cursor: not-allowed !important; pointer-events: none; }
        .btn-cancel { background-color: #ef4444 !important; color: #ffffff !important; font-weight: 700; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; margin-left: 10px; }
        .btn-cancel:disabled, .btn-cancel[disabled] { background-color: #cbd5e1 !important; color: #94a3b8 !important; opacity: 0.65; cursor: not-allowed !important; pointer-events: none; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 700; padding: 10px 24px; border-radius: 8px; border: none; }
        .btn-print-legacy { background-color: #0284c7; color: #ffffff !important; border-radius: 6px; padding: 3px 10px; font-size: 12px; text-decoration: none !important; }
        .childNode, .clickable-coil-node { cursor: pointer !important; }
    </style>
    <script src="assets/js/mermaid.min.js"></script>
    <script>mermaid.initialize({ startOnLoad: true, theme: 'neutral', flowchart: { useMaxWidth: false, htmlLabels: true, curve: 'basis' } });</script>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="swiss_slitter_process_mats.php?func=<?php echo $folder_func ?>">Swiss Slitter Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Swiss Slitter Coil Details: <span style="color:#2563eb;"><a href="swiss_slitter_process_mats.php?func=<?php echo $folder_func ?>&cno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>
                <button type="button" class="btn btn-back" onclick="back_home_coil()">⬅️ Back</button>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: Product Information (นำ ACC_SLITWIDTH และ BLN_SLITWIDTH ออกแล้ว) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Group 1: Master Coil Details</h4>
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
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value" style="color: <?php echo $is_weight_too_low ? '#ef4444' : '#0284c7'; ?>; font-weight:700;"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>

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
                            💡 <b>Instructions:</b> Click the <b>Coil (yellow)</b> box to print the product label.
                        </div>
                    </div>

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
                            $mermaid_syntax = "graph TD\n";
                            $mermaid_syntax .= "    classDef parentNode fill:#f1f5f9,stroke:#94a3b8,stroke-width:1px,color:#1e293b,font-weight:bold;\n";
                            $mermaid_syntax .= "    classDef childNode fill:#fef08a,stroke:#ca8a04,stroke-width:2px,color:#1e293b,font-weight:bold;\n";

                            foreach ($coil_tree_list as $node) {
                                $c_no = trim($node['COIL_NO'] ?? '');
                                $p_ref = trim($node['PRODUCT_REFERENCE'] ?? '');
                                $thick = function_exists('fmt3') ? fmt3($node['THICKNESS']) : number_format((float)($node['THICKNESS'] ?? 0), 2);
                                $width = function_exists('fmt2') ? fmt2($node['WIDTH']) : number_format((float)($node['WIDTH'] ?? 0), 2);
                                $level = (int)($node['Level'] ?? 0);

                                $node_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $c_no);

                                if ($level === 0 && empty($p_ref)) {
                                    $label = "<b>{$c_no}</b>";
                                    $mermaid_syntax .= "    {$node_id}[\"{$label}\"]:::parentNode\n";
                                } else {
                                    if ($level > 0) {
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

                <!-- GROUP 2: Coil Swiss Slitter Product Details -->
                <div class="dashboard-card">
                    <h4 class="card-title-g-tree">📦 Group 2: Coil Swiss Slitter Product Details</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th style="text-align: center;">PRINT LABEL</th>
                                    <th>COIL NO</th>
                                    <th>PRODUCT REFERENCE</th>
                                    <th>JOB PROCESS</th>
                                    <th>JOB ORDER</th>
                                    <th style="text-align: center;">LINE PROCESS</th>
                                    <th style="text-align: center;">OPERATE DATE</th>
                                    <th style="text-align: center;">END DATE</th>
                                    <th style="text-align: center;">ALLOY</th>
                                    <th style="text-align: right;">THICKNESS</th>
                                    <th style="text-align: right;">WIDTH</th>
                                    <th style="text-align: right;">ACTUAL WEIGHT</th>
                                    <th style="text-align: center;">STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $filtered_table_list = array_values(array_filter($coil_tree_list, function($item) {
                                    return ((int)($item['Level'] ?? 0)) > 0;
                                }));
                                ?>

                                <?php if (empty($filtered_table_list)): ?>
                                    <tr>
                                        <td colspan="14" align="center" style="color: #64748b; padding: 20px;">
                                            No product details found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($filtered_table_list as $t_index => $tree_item): 
                                        $tree_coil_no = trim($tree_item['COIL_NO'] ?? '');
                                        $tree_status  = strtoupper(trim($tree_item['COIL_STATUS'] ?? ''));
                                        
                                        $status_class = 'status-cl';
                                        if ($tree_status === 'AC') {
                                            $status_class = 'status-ac';
                                        } else if ($tree_status === 'SS') {
                                            $status_class = 'status-ss';
                                        }
                                    ?>
                                        <tr>
                                            <td align="center"><?php echo $t_index + 1; ?></td>
                                            
                                            <td align="center" style="width: 100px;">
                                                <?php if (!empty($tree_coil_no)): ?>
                                                    <a href="print_coil_product_label_mats.php?coilno=<?php echo urlencode($tree_coil_no); ?>" 
                                                    target="_blank" 
                                                    class="btn-print-legacy"
                                                    title="Print Label for <?php echo htmlspecialchars($tree_coil_no); ?>">
                                                        🖨️ Print
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>

                                            <td style="font-weight: 700; color: #2563eb;">
                                                <a href="swiss_sitter_coil_production_detail_mats.php?func=<?php echo $folder_func ?>&COIL=<?php echo htmlspecialchars($tree_coil_no); ?>">
                                                    <?php echo htmlspecialchars($tree_coil_no !== '' ? $tree_coil_no : '-'); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($tree_item['PRODUCT_REFERENCE'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($tree_item['JOB_PROCESS'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($tree_item['JOB_ORDER'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($tree_item['LINE_PROCESS'] ?? '-'); ?></td>
                                            <td align="center"><?php echo !empty($tree_item['COIL_OPERATEDATE']) ? date('Y-m-d H:i:s', strtotime($tree_item['COIL_OPERATEDATE'])) : '-'; ?></td>
                                            <td align="center"><?php echo !empty($tree_item['COIL_ENDDATE']) ? date('Y-m-d H:i:s', strtotime($tree_item['COIL_ENDDATE'])) : '-'; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($tree_item['ALLOY'] ?? '-'); ?></td>
                                            <td align="right"><?php echo function_exists('fmt3') ? fmt3($tree_item['THICKNESS']) : number_format((float)($tree_item['THICKNESS'] ?? 0), 3); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($tree_item['WIDTH']) : number_format((float)($tree_item['WIDTH'] ?? 0), 2); ?></td>
                                            <td align="right"><?php echo function_exists('fmt2') ? fmt2($tree_item['COIL_ACTUALWEIGHT']) : number_format((float)($tree_item['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                            <td align="center">
                                                <span class="status-badge-tree <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($tree_status); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GROUP 3: Slitter Process Data Entry -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">⚙️ Group 3: Slitter Process Data Entry</h4>
                    
                    <?php if ($is_weight_too_low): ?>
                        <div class="alert alert-danger" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                            ⚠️ <b>Warning:</b> This coil balance weight (<?php echo number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?> KG) is less than the automatic scrap threshold (<?php echo number_format($scrap_limit_weight, 2); ?> KG). The slitter process for this coil has been disabled.
                        </div>
                    <?php endif; ?>

                    <form id="form_slitter_process" action="model/save_swiss_slitter_process.php" method="POST" onsubmit="return validate_form();">
                        
                        <!-- Hidden Inputs -->
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" />
                        <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>" />

                        <!-- Line 1: Number of Coil, Width, Actual Weight -->
                        <div class="row">
                            <div class="col-md-4 col-sm-4">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="num_coil_slit">Number of Coil Slit (Stands) <span style="color:red;">*</span></label>
                                    <input type="number" step="1" min="1" class="form-control-custom" id="num_coil_slit" name="num_coil_slit" value="1" oninput="calculate_default_weight()" required <?php echo $is_weight_too_low ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-4">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="coil_slit_width">Coil Slit Width (mm) <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control-custom" id="coil_slit_width" name="coil_slit_width" placeholder="Enter width (mm)..." oninput="calculate_default_weight()" required <?php echo $is_weight_too_low ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-4">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="actual_weight">Actual Weight per Piece (KG) <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control-custom" id="actual_weight" name="actual_weight" placeholder="Auto-calculated or enter scale weight..." required <?php echo $is_weight_too_low ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                        </div>

                        <!-- Line 2: Start Date time & End Date time -->
                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="start_datetime_slit">Start Date time Slit <span style="color:red;">*</span></label>
                                    <input type="datetime-local" class="form-control-custom" id="start_datetime_slit" name="start_datetime_slit" required <?php echo $is_weight_too_low ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="end_datetime_slit">End Date time Slit <span style="color:red;">*</span></label>
                                    <input type="datetime-local" class="form-control-custom" id="end_datetime_slit" name="end_datetime_slit" required <?php echo $is_weight_too_low ? 'disabled' : ''; ?> />
                                </div>
                            </div>
                        </div>

                        <!-- Line 3: Coil Remark -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="coil_remark">Coil Remark (Note / Edit text)</label>
                                    <textarea class="form-control-custom" id="coil_remark" name="coil_remark" rows="3" style="height: auto; padding: 10px 12px; resize: vertical;" placeholder="Enter additional notes or correct the information..." <?php echo $is_weight_too_low ? 'disabled' : ''; ?>><?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="margin-top: 15px; text-align: right; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                            <button type="submit" id="btn_save_slitter" class="btn btn-save" <?php echo $is_weight_too_low ? 'disabled="disabled"' : ''; ?>>
                                💾 Save Data
                            </button>
                            <button type="button" class="btn btn-cancel" onclick="back_home_coil()" <?php echo $is_weight_too_low ? 'disabled="disabled"' : ''; ?>>
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
var masterWidth = parseFloat(<?php echo json_encode((float)($row_data['WIDTH'] ?? 0)); ?>);
var masterActualWeight = parseFloat(<?php echo json_encode((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0)); ?>);
var masterBalanceWeight = parseFloat(<?php echo json_encode((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0)); ?>);
var blnSlitWidth = parseFloat(<?php echo json_encode((float)($row_data['BLN_SLITWIDTH'] ?? 0)); ?>);
var isWeightTooLow = <?php echo json_encode($is_weight_too_low); ?>;
var scrapLimitWeight = parseFloat(<?php echo json_encode($scrap_limit_weight); ?>);

function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('swiss_slitter_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

function getCurrentDateTimeLocal() {
    var now = new Date();
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    
    return year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;
}

function calculate_default_weight() {
    if (isWeightTooLow) return;

    var slitWidth = parseFloat(document.getElementById('coil_slit_width').value) || 0;
    if (slitWidth > 0 && masterWidth > 0) {
        var weightPerMM = masterActualWeight / masterWidth;
        var defaultWeight = weightPerMM * slitWidth;
        document.getElementById('actual_weight').value = defaultWeight.toFixed(2);
    } else {
        document.getElementById('actual_weight').value = '';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    if (!isWeightTooLow) {
        var currentNow = getCurrentDateTimeLocal();
        document.getElementById('start_datetime_slit').value = currentNow;
        document.getElementById('end_datetime_slit').value = currentNow;
    }
});

function validate_form() {
    if (isWeightTooLow || masterBalanceWeight <= scrapLimitWeight) {
        alert('⚠️ ไม่สามารถดำเนินการได้! น้ำหนักคงเหลือคอยล์แม่ (' + masterBalanceWeight.toFixed(2) + ' KG) น้อยกว่าค่าน้ำหนักเกณฑ์เกิด Scrap อัตโนมัติ (' + scrapLimitWeight.toFixed(2) + ' KG)');
        return false;
    }

    var numStand = parseFloat(document.getElementById('num_coil_slit').value) || 0;
    var slitWidth = parseFloat(document.getElementById('coil_slit_width').value) || 0;
    var actualWeightPiece = parseFloat(document.getElementById('actual_weight').value) || 0;

    if (numStand <= 0 || slitWidth <= 0 || actualWeightPiece <= 0) {
        alert('⚠️ กรุณาระบุ จำนวนม้วนซอย, ความกว้าง และ น้ำหนักจริง ให้ถูกต้อง');
        return false;
    }

    var totalSlitWidth = slitWidth * numStand;
    var maxAllowedWidth = (blnSlitWidth > 0) ? blnSlitWidth : masterWidth;

    if (totalSlitWidth > maxAllowedWidth) {
        alert('⚠️ ไม่สามารถบันทึกข้อมูลได้!\n\n' +
              'ความกว้างรวมในการซอย (' + totalSlitWidth.toFixed(2) + ' mm x ' + numStand + ' Stand = ' + totalSlitWidth.toFixed(2) + ' mm)\n' +
              'มีค่ามากกว่า Balance Slit Width ที่กำหนดไว้ (' + maxAllowedWidth.toFixed(2) + ' mm)');
        return false;
    }

    var totalSlitWeight = actualWeightPiece * numStand;
    if (totalSlitWeight > masterBalanceWeight) {
        alert('⚠️ ไม่สามารถดำเนินการได้! เนื่องจากน้ำหนักรวมในการซอยครั้งนี้ (' + totalSlitWeight.toFixed(2) + ' KG) เกินกว่าน้ำหนักม้วนคงเหลือ (' + masterBalanceWeight.toFixed(2) + ' KG)');
        return false;
    }

    var startDtVal = document.getElementById('start_datetime_slit').value;
    var endDtVal   = document.getElementById('end_datetime_slit').value;

    if (!startDtVal || !endDtVal) {
        alert('⚠️ กรุณาระบุ Start Date time และ End Date time ให้ครบถ้วน');
        return false;
    }

    var startDt = new Date(startDtVal);
    var endDt   = new Date(endDtVal);

    if (endDt < startDt) {
        alert('⚠️ เวลาสิ้นสุดการซอย (End Date time Slit) น้อยกว่า วัน-เวลาเริ่มต้น (Start Date time Slit)');
        return false;
    }

    if (confirm('ยืนยันการบันทึกข้อมูล Slitter Process หรือไม่?')) {
        var btnSave = document.getElementById('btn_save_slitter');
        btnSave.disabled = true;
        btnSave.innerHTML = '⏳ Saving...';
        return true;
    }

    return false;
}

document.addEventListener("DOMContentLoaded", function() {
    var treeContainer = document.getElementById("genealogy_tree_container");
    if (treeContainer) {
        treeContainer.addEventListener("click", function(e) {
            var targetNode = e.target.closest(".clickable-coil-node") || e.target.closest(".childNode");
            
            if (targetNode) {
                var coilNo = "";
                
                if (targetNode.dataset && targetNode.dataset.coilno) {
                    coilNo = targetNode.dataset.coilno;
                } else {
                    var innerData = targetNode.querySelector("[data-coilno]");
                    if (innerData) {
                        coilNo = innerData.dataset.coilno;
                    }
                }

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