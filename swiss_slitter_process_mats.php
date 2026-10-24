<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า Session ต่างๆ
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : 'T'; // Default Line T
$group_func      = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
$process_func    = isset($_SESSION['PROCESS_GROUP']) ? htmlspecialchars($_SESSION['PROCESS_GROUP'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

// ถ้าไม่มี Session ให้กลับไปหน้า index.php
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

// =========================================================================
// สิทธิ์พิเศษ: ต้องเป็น CEO, MANAGER, SUPERVISOR และต้องอยู่แผนก PD, IT, TC, MG
// =========================================================================
$bypass_positions    = ['CEO', 'MANAGER', 'SUPERVISOR'];
$allowed_departments = ['PD', 'IT', 'TC', 'MG'];

$is_supervisor_up = in_array($position_func, $bypass_positions) && in_array($department_func, $allowed_departments);

// ตรวจสอบ Line Process (Swiss Slitter Process: Line T เท่านั้น)
$allowed_lines_ss = ['T'];
if (!$is_supervisor_up && !in_array($line_func, $allowed_lines_ss)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ ไม่ตรงกับกระบวนการ Swiss Slitter Process (Line T)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

// เช็กว่า Line ที่ล็อกอินตรงกับสายการผลิตนี้หรือไม่ สำหรับคุมการกดปุ่ม Action
$is_correct_line = in_array($line_func, $allowed_lines_ss);

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า cno (Coil No) สำหรับค้นหา
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Swiss Slitter Process (Line <?php echo $line_func; ?>)</title>
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

        .btn-action-coil {
            background-color: #2563eb;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-coil:hover {
            background-color: #1d4ed8;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

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
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }

        .status-badge {
            color: #059669;
            font-weight: 700;
            background-color: #ecfdf5;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }

        .btn-slitter-disabled {
            background-color: #94a3b8 !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 12px;
            cursor: not-allowed !important;
            opacity: 0.75;
            display: inline-block;
            text-decoration: none !important;
            min-width: 140px;
        }

        .btn-slitter-enabled {
            background-color: #10b981 !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
            min-width: 140px;
        }
        .btn-slitter-enabled:hover {
            background-color: #059669 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }        
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php'; ?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="swiss_slitter_process_mats.php?func=<?php echo $folder_func ?>">
                        Swiss Slitter Process (Line <?php echo $line_func; ?>)
                        <?php if ($is_supervisor_up && !$is_correct_line): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY (LINE <?php echo $line_func; ?>)</span>
                        <?php elseif ($is_supervisor_up): ?>
                            <span class="badge" style="background-color:#d97706; font-size:11px; margin-left:8px;">AUTHORIZED ACCESS</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no_input">Coil No.</label>
                    <input class="form-control-minimal" name="coil_no_input" id="coil_no_input" type="text" placeholder="Enter Coil No. to search..." value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        🔍 SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Swiss Slitter Master Coils (Line <?php echo $line_func; ?>)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th>Coil No</th>
                                        <th>Product Ref</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Grade</th>
                                        <th>Surface Grade</th>
                                        <th>Thickness</th>
                                        <th>Actual Width</th>
                                        <th>Balance Weight</th>
                                        <th>Next Process</th>
                                        <th>Status</th>
                                        <th style="text-align: center; width: 160px;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $extra_where = "";

                                    // กรณีมีการค้นหา Coil No ให้ค้นหาได้ทั้งสถานะ AC และ SS
                                    if (!empty($cno)) {
                                        $extra_where .= " AND COIL_NO = :cno AND COIL_STATUS IN ('AC', 'SS')";
                                        $params[':cno'] = $cno;
                                    } else {
                                        // โหลดหน้าปกติ: ดึงคอยล์หลักตามเงื่อนไข (COIL_NEXTPROCESS = 'SS' AND COIL_STATUS = 'AC')
                                        $extra_where .= " AND COIL_NEXTPROCESS = 'SS' AND COIL_STATUS = 'AC'";
                                        
                                        if ($d != date('Y-m-d')) {
                                            $extra_where .= " AND COIL_STARTTIME BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $sql = "SELECT 
                                                COIL_NO, PRODUCT_REFERENCE, ALLOY, TEMPER, GRADE, 
                                                SURFACE_GRADE, THICKNESS, ACTUAL_WIDTH, COIL_NEXTPROCESS, 
                                                LINE_PROCESS, COIL_BALANCEWEIGHT, COIL_STATUS, COIL_STARTTIME
                                            FROM COILPROD1
                                            WHERE 1=1 {$extra_where}
                                            ORDER BY COIL_STARTTIME DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $safe_coil = htmlspecialchars($coil['COIL_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $status    = trim($coil['COIL_STATUS'] ?? '');

                                    // =========================================================================
                                    // ควบคุมปุ่ม Action: ต้องเป็น Line T และม้วนมีสถานะ AC จึงจะกดตัดแบ่งม้วนได้
                                    // =========================================================================
                                    if (!$is_correct_line) {
                                        $btn_class    = 'btn-slitter-disabled';
                                        $btn_disabled = 'disabled="disabled"';
                                        $btn_onclick  = '';
                                        $btn_text     = '🔒 LOCKED (LINE ' . $line_func . ')';
                                    } elseif ($status === 'AC') {
                                        $btn_class    = 'btn-slitter-enabled';
                                        $btn_disabled = '';
                                        $btn_onclick  = "onclick=\"show_slitter_coil('".addslashes($coil['COIL_NO'])."')\"";
                                        $btn_text     = '✂️ SLITTER COIL';
                                    } else {
                                        $btn_class    = 'btn-slitter-disabled';
                                        $btn_disabled = 'disabled="disabled"';
                                        $btn_onclick  = '';
                                        $btn_text     = '🔒 BUSY / NOT AC';
                                    }
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td>
                                            <button type="button" class="btn-action-coil" onclick="show_swiss_detail('<?php echo addslashes($coil['COIL_NO']); ?>')">
                                                📦 <?php echo $safe_coil; ?>
                                            </button>
                                        </td>
                                        <td><?php echo htmlspecialchars($coil['PRODUCT_REFERENCE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['SURFACE_GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo number_format((float)($coil['ACTUAL_WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo number_format((float)($coil['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['COIL_NEXTPROCESS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <td align="center">
                                            <button type="button" class="<?php echo $btn_class; ?>" <?php echo $btn_disabled; ?> <?php echo $btn_onclick; ?>>
                                                <?php echo $btn_text; ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
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
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[0, 'asc']],
    buttons: []
});

function search_batch_no(){    
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no_input").value; 
    window.location.assign('swiss_slitter_process_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}   

function show_swiss_detail(coil_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('swiss_sitter_coil_production_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&COIL=' + encodeURIComponent(coil_no));
}

function show_slitter_coil(coil_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('swiss_slitter_coil_mats.php?func=' + encodeURIComponent(data_fun) + '&COIL=' + encodeURIComponent(coil_no));
}
</script>
</html>