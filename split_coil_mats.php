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
$coil_tree_list = [];

if (!empty($coil_no)) {
    // 1. Query ดึงข้อมูล Coil หลัก
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
                p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_BALANCEWEIGHT,p.COIL_BATCHANNEALWEIGHT,p.COIL_COLDMILLWEIGHT, 
                p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.LINE_PROCESS, 
                p.BLN_SLITWIDTH, p.ACC_SLITWIDTH, p.RSET_NO, p.RECIPE_NO, p.RECIPE_ITEM, p.TOTAL_PASS, p.CURRENT_PASS, p.THICKNESS_FINAL,
                i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงข้อมูล Coil Swiss Slitter Tree Details (Recursive CTE)
    if ($row_data) {
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

        /* ปุ่ม Save Data */
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

        /* ปุ่ม Cancel */
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
    <style>
        .childNode, .clickable-coil-node {
            cursor: pointer !important;
        }
        .childNode:hover rect, .childNode:hover polygon {
            fill: #fde047 !important;
            stroke: #854d0e !important;
        }
    </style>
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
                    📦 Coil Details: <span style="color:#2563eb;"><a href="split_coil_production_detail_mats.php?func=<?php echo $folder_func ?>&COIL=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
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
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUAL WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCE WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL COLDMILL WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_COLDMILLWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BATCHANNEAL WEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BATCHANNEALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ROLL SET NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['RSET_NO'] ?? '-'); ?></div></div>
                       
                        <div class="col-md-3 col-sm-6"><div class="info-label">RECIPE : ITEM</div><div class="info-value"><?php echo htmlspecialchars($row_data['RECIPE_NO'] ?? '-')?> : <?php echo number_format((float)($row_data['RECIPE_ITEM'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TOTAL PASS</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['TOTAL_PASS']) : number_format((float)($row_data['TOTAL_PASS'] ?? 0), 2); ?></div></div>
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

                <!-- GROUP 3: Split Process Data Entry -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">⚙️ Group 3: Split Process Data Entry</h4>
                    <form id="form_slitter_process" action="model/save_split_process_mats.php" method="POST" onsubmit="return validate_form();">
                        
                        <!-- Hidden Inputs -->
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" />
                        <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>" />

                        <!-- Line 1: Thickness & Weight -->
                        <div class="row">
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="split_thickness">Input Thickness (mm) <span style="color:red;">*</span></label>
                                    <input type="number" step="0.001" min="0" class="form-control-custom" id="split_thickness" name="split_thickness" value="<?php echo (float)($row_data['THICKNESS'] ?? 0); ?>" placeholder="Enter thickness..." required />
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="split_weight">Input Weight (kg) <span style="color:red;">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control-custom" id="split_weight" name="split_weight" placeholder="Enter weight..." required />
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="margin-top: 15px; text-align: right; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                            <button type="submit" id="btn_save_slitter" class="btn btn-save">
                                💾 Save Data
                            </button>
                            <button type="button" id="btn_cancel_slitter" class="btn btn-cancel" onclick="clear_input_form()">
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
// ดึงค่าน้ำหนักหลักจาก PHP (ใช้ BALANCEWEIGHT ถ้ามี หากไม่มีให้ใช้ ACTUALWEIGHT)
var mainCoilWeight = parseFloat(<?php 
    $bal = (float)($row_data['COIL_BALANCEWEIGHT'] ?? 0);
    $act = (float)($row_data['COIL_ACTUALWEIGHT'] ?? 0);
    echo json_encode($bal > 0 ? $bal : $act); 
?>);
var defaultThickness = parseFloat(<?php echo json_encode((float)($row_data['THICKNESS'] ?? 0)); ?>);

function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('split_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

// ฟังก์ชันล้างค่า (Cancel)
function clear_input_form() {
    document.getElementById('split_thickness').value = defaultThickness > 0 ? defaultThickness : '';
    document.getElementById('split_weight').value = '';
}

// ฟังก์ชันตรวจสอบความถูกต้องก่อนบันทึก
function validate_form() {
    var inputThickness = parseFloat(document.getElementById('split_thickness').value) || 0;
    var inputWeight = parseFloat(document.getElementById('split_weight').value) || 0;

    if (inputThickness <= 0) {
        alert('Please enter a valid Thickness.');
        document.getElementById('split_thickness').focus();
        return false;
    }

    if (inputWeight <= 0) {
        alert('Please enter a valid Weight.');
        document.getElementById('split_weight').focus();
        return false;
    }

    // เช็กกรณีใส่ น้ำหนัก : น้ำหนักที่แบ่งต้องไม่มากกว่า น้ำหนัก Coil หลัก
    if (inputWeight > mainCoilWeight) {
        alert('⚠️ Cannot save data!\n\n' +
              'Split Weight (' + inputWeight.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' kg) ' +
              'exceeds Main Coil Weight (' + mainCoilWeight.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' kg).');
        document.getElementById('split_weight').focus();
        return false;
    }

    return confirm('Confirm that the Split Process data has been saved?');
}

// ฟังก์ชันสำหรับเรียกหน้า Print Coil Product Label
function printCoilLabel(nodeId) {
    var rawCoilNo = nodeId.replace(/_/g, '-'); 
    if (confirm("🖨️ You want to open the label printing page for Coil No: " + rawCoilNo + " Is it right?")) {
        var url = "print_coil_product_label_mats.php?coilno=" + encodeURIComponent(rawCoilNo);
        window.open(url, '_blank');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // ดักจับการคลิกบนแผนภูมิ Genealogy Tree
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