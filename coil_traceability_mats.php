<?php
// เริ่ม session ก่อน ANY output
session_start();

// แก้ไข Logic Error: รับค่า cno และกำหนดตัวแปรให้ตรงกัน
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
<?php include 'include/'.$folder_func.'/navigation.php';?>

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
                <button onclick="open_coil_traceability()" class="btn-tab-item active">Parent to Child </button>
                <button onclick="open_coil_traceability_child()" class="btn-tab-item">Child to Parent </button>
                <button onclick="open_production_traceability_coil()" class="btn-tab-item">Production No to Parent Coil</button>
                <button onclick="open_coil_traceability_production()" class="btn-tab-item">Parent Coil to Production No </button>
            </div>

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">Coil Number</label>
                    <input class="form-control-minimal" 
                        name="coil_no" 
                        id="coil_no" 
                        type="text" 
                        placeholder="Enter Parent Coil Code (e.g., C260307-2-01)" 
                        value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" 
                        style="text-transform: uppercase;" 
                        oninput="this.value = this.value.toUpperCase()" 
                        onchange="search_batch_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Coil Traceability Genealogy Tree</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;">LVL</th>
                                        <th style="width: 160px; text-align: center;">COIL NO</th>
                                        <th>REF PRODUCT</th>
                                        <th>PROCESS / ORDER</th>
                                        <th>LINE</th>
                                        <th>CUSTOMER</th>
                                        <th>COIL_STATUS</th>
                                        <th>ALLOY</th>
                                        <th>THICKNESS</th>
                                        <th>WIDTH</th>
                                        <th style="width: 130px; text-align: center;">WEIGHT (KG)</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $sql = "WITH CoilTree AS (
                                                SELECT 
                                                    c.COIL_NO, c.PRODUCT_REFERENCE, c.JOB_PROCESS, c.JOB_ORDER, c.LINE_PROCESS, c.COIL_ENDDATE, 
                                                    c.COIL_ACTUALWEIGHT, c.COIL_BALANCEWEIGHT, c.CSTMSPPL_ID, c.ALLOY, c.THICKNESS, c.WIDTH, 
                                                    c.COIL_STATUS, c.COIL_OPERATEDATE,
                                                    0 AS Level 
                                                FROM COILPROD1 AS c
                                                WHERE c.COIL_NO = :target_coil 

                                                UNION ALL

                                                SELECT 
                                                    child.COIL_NO, child.PRODUCT_REFERENCE, child.JOB_PROCESS, child.JOB_ORDER, child.LINE_PROCESS, child.COIL_ENDDATE, 
                                                    child.COIL_ACTUALWEIGHT, child.COIL_BALANCEWEIGHT, child.CSTMSPPL_ID, child.ALLOY, child.THICKNESS, child.WIDTH, 
                                                    child.COIL_STATUS, child.COIL_OPERATEDATE,
                                                    parent.Level + 1
                                                FROM COILPROD1 AS child
                                                INNER JOIN CoilTree AS parent ON child.PRODUCT_REFERENCE = parent.COIL_NO
                                            )
                                            SELECT * FROM CoilTree
                                            ORDER BY COIL_OPERATEDATE ASC;";
                                    
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute([':target_coil' => $cno]);
                             
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        // ใช้ ?? '' เพื่อแปลง null ให้เป็น string ว่าง ป้องกัน Warning ใน PHP 8.1+
                                        $safe_coil_no = htmlspecialchars($row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        
                                        echo "<tr class='clickable-row' data-coil='".$safe_coil_no."'>";
                                        echo "<td style='text-align:center;'><span class='badge-level'>".htmlspecialchars($row['Level'] ?? '', ENT_QUOTES, 'UTF-8')."</span></td>";
                                        echo "<td style='text-align:center;'><span class='badge-coil'>".$safe_coil_no."</span></td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['PRODUCT_REFERENCE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";

                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['JOB_PROCESS'] ?? '', ENT_QUOTES, 'UTF-8')." / ".htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['LINE_PROCESS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='font-weight:600; color:#0f172a; font-size:15px;'>".htmlspecialchars($row['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        
                                        echo "<td style='text-align:center;'>".number_format((float)($row['THICKNESS'] ?? 0), 2)."</td>";
                                        echo "<td style='text-align:center;'>".number_format((float)($row['WIDTH'] ?? 0), 2)."</td>";
                                        echo "<td style='text-align:center;'>".number_format((float)($row['COIL_BALANCEWEIGHT'] ?? 0), 2)."</td>";
                                        echo "</tr>";                                           
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Graph Coil Traceability Genealogy Tree</h4>
                        </div>
                        
                        <?php
                        // สร้างข้อมูลสำหรับ Mermaid Diagram จาก Query เดิม
                        if (!empty($cno)) {
                            $stmt_graph = $conn->prepare($sql);
                            $stmt_graph->execute([':target_coil' => $cno]);
                            $nodes = [];
                            $edges = [];

                            while ($g_row = $stmt_graph->fetch(PDO::FETCH_ASSOC)) {
                                $coil = htmlspecialchars($g_row['COIL_NO'], ENT_QUOTES, 'UTF-8');
                                $parent = htmlspecialchars($g_row['PRODUCT_REFERENCE'], ENT_QUOTES, 'UTF-8');
                                
                                // ปรับ Thickness,WIDTH ให้เป็นทศนิยม 2 ตำแหน่ง
                                $thick = !empty($g_row['THICKNESS']) ? number_format((float)$g_row['THICKNESS'], 2, '.', '') : '0.00';
                                //$width = !empty($g_row['WIDTH']) ? floatval($g_row['WIDTH']) : '0';
                                $width = !empty($g_row['WIDTH']) ? number_format((float)$g_row['WIDTH'], 2, '.', '') : '0.00';

                                // กำหนดข้อความใน Node: COIL_NO และ (Thickness x Width)
                                $node_label = "<b>{$coil}</b><br/>{$thick} x {$width}";
                                
                                // ป้องกัน ID ของ Node มีเครื่องหมายพิเศษ
                                $node_id = preg_replace('/[^A-Za-z0-9_]/', '_', $coil);
                                $nodes[$node_id] = "{$node_id}[\"{$node_label}\"]";

                                // สร้างเส้นเชื่อม Parent -> Child
                                if (!empty($parent) && $parent !== $coil) {
                                    $parent_id = preg_replace('/[^A-Za-z0-9_]/', '_', $parent);
                                    $edges[] = "{$parent_id} --> {$node_id}";
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
                                // กำหนด CSS ให้ Node มีสีเหลืองตรงตามตัวอย่าง
                                $mermaid_code .= "    classDef yellowNode fill:#ffffaa,stroke:#333,stroke-width:1px;\n";
                                $mermaid_code .= "    class " . implode(",", array_keys($nodes)) . " yellowNode;\n";
                            }
                        }
                        ?>

                        <div style="overflow-x: auto; text-align: center; padding: 10px;">
                            <?php if (!empty($mermaid_code)): ?>
                                <div class="mermaid">
                                    <?php echo $mermaid_code; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #94a3b8; padding: 20px;">No graph data found. Please enter the coil number to search.</p>
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
        scrollX: false,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order: [[0, 'asc']],
        buttons: []
    });

    // ดักจับ Event คลิกที่แถว (Line/Row) ของตาราง
    $('#user_table tbody').on('click', 'tr.clickable-row', function () {
        var coilNo = $(this).attr('data-coil');
        if (coilNo) {
            show_detail_coil(coilNo);
        }
    });

    // สั่งให้ Mermaid ทำการ Render Graph
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
    window.location.assign('coil_traceability_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
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