<?php
// เริ่ม session
session_start();

// ดึงค่า Session ต่างๆ ตามแบบฉบับ MATS System
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';
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

// กำหนดสายการผลิตที่อนุญาตสำหรับกระบวนการ Batch Annealing
$allowed_lines_anneal = ['3', '4'];
$is_correct_line      = in_array($line_func, $allowed_lines_anneal);

// ตรวจสอบ Line การทำงาน: ถ้าไม่ใช่ Supervisor ขึ้นไป และ Line ไม่ตรง จะแจ้งเตือนและเด้งกลับ
if (!$is_supervisor_up && !$is_correct_line) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ ไม่ใช่กระบวนการ Batch Annealing Process (Line 3, 4)');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

// รับค่าค้นหาและตัวกรองสถานะ (Default: IN)
$bno    = isset($_GET['bno']) ? htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8') : '';
$status = isset($_GET['status']) ? strtoupper(trim($_GET['status'])) : 'IN';
if ($status !== 'OT') {
    $status = 'IN';
}

include 'function_mats.php';
include 'dbcon_mats-new.php';

// =========================================================================
// ตรรกะการสร้างเลข Batch ใหม่จากฐานข้อมูล BTCHPROD0 โดยตรง
// =========================================================================
$auto_gen_batch_no = '';
$can_create_batch  = ($department_func === 'PD' || $department_func === 'IT' || $is_supervisor_up) && $is_correct_line;

if ($can_create_batch) {
    try {
        $prefix_today = 'B' . date('ymd') . '-' . $line_func . '-';
        $sql_max_run  = "SELECT MAX(SUBSTRING(BTCH_NO, 11, 2)) AS MAX_RUN 
                         FROM BTCHPROD0 
                         WHERE BTCH_NO LIKE :prefix";
        $stmt_max = $conn->prepare($sql_max_run);
        $search_pattern = $prefix_today . '%';
        $stmt_max->bindParam(':prefix', $search_pattern, PDO::PARAM_STR);
        $stmt_max->execute();
        $row_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

        $next_run = 1;
        if (!empty($row_max['MAX_RUN'])) {
            $next_run = (int)$row_max['MAX_RUN'] + 1;
        }

        if ($next_run <= 4) {
            $auto_gen_batch_no = $prefix_today . str_pad($next_run, 2, '0', STR_PAD_LEFT);
        } else {
            $auto_gen_batch_no = 'EXCEEDED'; // เกินโควตา 4 Batch ต่อวัน
        }
    } catch (Exception $e) {
        $auto_gen_batch_no = '';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Batch Annealing Process (Line <?php echo $line_func; ?>)</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        /* Filter Section */
        .filter-wrapper {
            display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;
            background: #ffffff; padding: 18px 24px; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label {
            font-size: 12px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 40px; font-size: 15px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-create {
            background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 13px;
            height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-create:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        .btn-action-disabled {
            background-color: #94a3b8 !important; color: #ffffff !important; font-weight: 600; font-size: 12px;
            height: 40px; padding: 0 16px; border-radius: 8px; border: none; cursor: not-allowed !important; opacity: 0.75;
        }

        /* Status Tabs */
        .status-tabs {
            display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;
        }
        .status-tab-btn {
            background: none; border: none; font-weight: 700; font-size: 13.5px; color: #64748b;
            padding: 8px 16px; border-radius: 6px; cursor: pointer; transition: all 0.2s;
        }
        .status-tab-btn.active {
            background-color: #eff6ff; color: #2563eb; border-bottom: 2px solid #2563eb;
        }

        /* Table Styling */
        .table-responsive { border: none !important; margin-top: 10px; }
        #batch_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #batch_table thead th {
            background-color: #f8fafc; color: #475569; font-weight: 700;
            text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;
            padding: 12px 10px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #batch_table tbody td {
            padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px; color: #334155; white-space: nowrap;
        }
        #batch_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* Badges */
        .badge-batch {
            display: inline-flex; align-items: center; gap: 4px;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 13px;
            padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe;
        }
        .badge-status-in { background-color: #fef3c7; color: #d97706; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }
        .badge-status-ot { background-color: #dcfce7; color: #15803d; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }

        /* Action Buttons */
        .btn-action-entry {
            background-color: #0f766e; color: #ffffff; font-weight: 600; font-size: 12.5px;
            padding: 6px 14px; border-radius: 6px; border: none; transition: all 0.2s;
        }
        .btn-action-entry:hover { background-color: #115e59; color: #ffffff; transform: translateY(-1px); }

        .btn-action-print {
            background-color: #475569; color: #ffffff; font-weight: 600; font-size: 12px;
            padding: 6px 12px; border-radius: 6px; border: none; transition: all 0.2s;
        }
        .btn-action-print:hover { background-color: #334155; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="batch_annealing_mats.php?func=<?php echo $folder_func ?>">
                        Batch Annealing Process (Line <?php echo $line_func; ?>)
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
            <!-- Filter Bar & Create Button -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="batch_no">Search Batch No.</label>
                    <input class="form-control-minimal" name="batch_no" id="batch_no" type="text" placeholder="Enter Batch No... (e.g. B26...)" value="<?php echo htmlspecialchars($bno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch()"/>
                </div>
                <div class="filter-item">
                    <button type="button" class="btn btn-search" id="btn_search" onclick="search_batch()">
                        🔍 SEARCH
                    </button>
                </div>

                <div class="filter-item" style="margin-left: auto;">
                    <?php if (!$is_correct_line): ?>
                        <button type="button" class="btn-action-disabled" disabled title="Line ที่ล็อกอิน (Line <?php echo $line_func; ?>) ไม่ตรงกับสายการผลิตนี้ ไม่สามารถสร้างงานได้">
                            🔒 LOCKED (LINE <?php echo $line_func; ?>)
                        </button>
                    <?php elseif ($can_create_batch): ?>
                        <button type="button" class="btn btn-create" onclick="open_create_batch_modal()">
                            ➕ CREATE NEW BATCH
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <!-- Navigation Tabs (IN / OT) -->
                        <div class="status-tabs">
                            <button type="button" class="status-tab-btn <?php echo ($status === 'IN') ? 'active' : ''; ?>" onclick="change_status_tab('IN')">
                                🔥 1. Ongoing / In Furnace (Status: IN)
                            </button>
                            <button type="button" class="status-tab-btn <?php echo ($status === 'OT') ? 'active' : ''; ?>" onclick="change_status_tab('OT')">
                                ✅ 2. Completed / Out Furnace (Status: OT)
                            </button>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="batch_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 150px;">Batch No.</th>
                                        <th style="text-align: center;">Control By</th>
                                        <th style="text-align: center;">Start Date/Time</th>
                                        <th style="text-align: center;">Expected Open</th>
                                        <th style="text-align: center;">Pallet Total</th>
                                        <th style="text-align: right;">Total Weight (kg)</th>
                                        <th style="text-align: center;">N2 Start</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center; width: 220px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $params = [];
                                    $where_sql = "WHERE RTRIM(LTRIM(b.BTCH_STATUS)) = :status";
                                    $params[':status'] = $status;

                                    if (!empty($bno)) {
                                        $where_sql .= " AND b.BTCH_NO LIKE :bno";
                                        $params[':bno'] = '%' . $bno . '%';
                                    }

                                    $sql = "SELECT TOP 200 
                                                   b.BTCH_NO, 
                                                   b.CONTROL_BY,
                                                   MIN(b.BTCH_STARTDATE) AS START_DATE,
                                                   MIN(b.BTCH_STARTTIME) AS START_TIME,
                                                   MIN(b.BTCH_EXPECTDATE) AS EXPECT_DATE,
                                                   MIN(b.BTCH_EXPECTTIME) AS EXPECT_TIME,
                                                   MAX(b.PALLET_ITEM) AS MAX_PALLET,
                                                   SUM(b.BTCH_INPUTWEIGHT) AS TOTAL_WEIGHT,
                                                   MAX(b.N2_START) AS N2_START,
                                                   MAX(b.BTCH_STATUS) AS BTCH_STATUS
                                            FROM BTCHPROD0 b
                                            {$where_sql}
                                            GROUP BY b.BTCH_NO, b.CONTROL_BY
                                            ORDER BY b.BTCH_NO DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_batch_no   = htmlspecialchars($row['BTCH_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $control_by      = ($row['CONTROL_BY'] === 'P') ? 'Workpiece' : 'Time';
                                        
                                        $start_dt_fmt    = !empty($row['START_DATE']) ? date('d/m/Y', strtotime($row['START_DATE'])) : '-';
                                        if (!empty($row['START_TIME'])) {
                                            $start_dt_fmt .= ' ' . date('H:i', strtotime($row['START_TIME']));
                                        }

                                        $expect_dt_fmt   = !empty($row['EXPECT_DATE']) ? date('d/m/Y', strtotime($row['EXPECT_DATE'])) : '-';
                                        if (!empty($row['EXPECT_TIME'])) {
                                            $expect_dt_fmt .= ' ' . date('H:i', strtotime($row['EXPECT_TIME']));
                                        }

                                        $total_pallet    = !empty($row['MAX_PALLET']) ? (int)$row['MAX_PALLET'] : 0;
                                        $total_weight    = !empty($row['TOTAL_WEIGHT']) ? number_format((float)$row['TOTAL_WEIGHT'], 0) : '0';
                                        $n2_val          = !empty($row['N2_START']) ? number_format((float)$row['N2_START'], 0) . ' %' : '-';
                                        $row_status      = trim($row['BTCH_STATUS'] ?? '');

                                        echo "<tr>";
                                        
                                        echo "<td align='center'>
                                                <span class='badge-batch'>🔥 ".$safe_batch_no."</span>
                                              </td>";
                                        
                                        echo "<td align='center' style='font-weight:600; color:#475569;'>".$control_by."</td>";
                                        echo "<td align='center' style='color:#334155;'>".$start_dt_fmt."</td>";
                                        echo "<td align='center' style='color:#334155;'>".$expect_dt_fmt."</td>";
                                        echo "<td align='center' style='font-weight:700;'>".$total_pallet."</td>";
                                        echo "<td align='right' style='font-weight:700; color:#0f172a;'>".$total_weight."</td>";
                                        echo "<td align='center'>".$n2_val."</td>";

                                        echo "<td align='center'>";
                                        if ($row_status === 'IN') {
                                            echo "<span class='badge-status-in'>🟡 IN FURNACE</span>";
                                        } else {
                                            echo "<span class='badge-status-ot'>🟢 COMPLETED</span>";
                                        }
                                        echo "</td>";

                                        echo "<td align='center' style='display:flex; gap:6px; justify-content:center;'>";
                                        
                                        echo "<button type='button' class='btn-action-entry' onclick=\"go_to_entry('".$safe_batch_no."')\" title='Manage Batch'>
                                                ⚙️ MANAGE
                                              </button>";

                                        echo "<button type='button' class='btn-action-print' onclick=\"print_form1('".$safe_batch_no."')\" title='Print BTCHFORM1'>
                                                📄 FORM_IN
                                              </button>";

                                        if ($row_status === 'OT') {
                                            echo "<button type='button' class='btn-action-print' style='background-color:#0891b2;' onclick=\"print_form2('".$safe_batch_no."')\" title='Print BTCHFORM2'>
                                                    📊 FORM_OUT
                                                  </button>";
                                        }

                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func;?>"/>
        <input type="hidden" id="current_status" value="<?php echo $status;?>"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#batch_table')) {
        $('#batch_table').DataTable().destroy();
    }

    $('#batch_table').DataTable({
        scrollY: "500px",
        scrollX: false,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order: [[0, 'desc']],
        buttons: []
    });
});

function search_batch() {   
    var data_fun = document.getElementById("func") ? document.getElementById("func").value : '';
    var batch_elem = document.getElementById("batch_no");
    var data_bno = batch_elem ? batch_elem.value.trim() : '';
    var status_elem = document.getElementById("current_status");
    var data_sta = status_elem ? status_elem.value : 'IN';
    
    window.location.href = 'batch_annealing_mats.php?func=' + encodeURIComponent(data_fun) 
                         + '&bno=' + encodeURIComponent(data_bno) 
                         + '&status=' + encodeURIComponent(data_sta); 
}

function change_status_tab(status_code) {
    var data_fun = document.getElementById("func") ? document.getElementById("func").value : '';
    window.location.href = 'batch_annealing_mats.php?func=' + encodeURIComponent(data_fun) 
                         + '&status=' + encodeURIComponent(status_code);
}

function go_to_entry(batch_no) {
    var data_fun = document.getElementById("func") ? document.getElementById("func").value : '';
    window.location.assign('batch_annealing_entry.php?func=' + encodeURIComponent(data_fun) + '&bno=' + encodeURIComponent(batch_no));
}

function open_popup_window(url, title, w, h) {
    var left = (window.screen.width - w) / 2;
    var top = (window.screen.height - h) / 2;
    var params = 'width=' + w + ',height=' + h + ',top=' + top + ',left=' + left + ',scrollbars=yes,resizable=yes,status=no,toolbar=no,menubar=no,location=no';
    
    var popupWin = window.open(url, title, params);
    if (window.focus && popupWin) {
        popupWin.focus();
    }
    return popupWin;
}

function print_form1(batch_no) {
    var url = 'print_batch_form_in.php?bno=' + encodeURIComponent(batch_no);
    open_popup_window(url, 'PrintForm1_' + batch_no, 900, 850);
}

function print_form2(batch_no) {
    var url = 'print_batch_form_out.php?bno=' + encodeURIComponent(batch_no);
    open_popup_window(url, 'PrintForm2_' + batch_no, 1200, 800);
}

function open_create_batch_modal() {
    var gen_batch = "<?php echo $auto_gen_batch_no; ?>";
    if (gen_batch === 'EXCEEDED') {
        alert('เตือน: หมายเลข Batch ประจำวันนี้เต็มโควตา 4 Batch ต่อวันแล้ว ไม่สามารถสร้างเพิ่มได้');
        return;
    }
    if (gen_batch === '') {
        alert('เกิดข้อผิดพลาดในการคำนวณหมายเลข Batch');
        return;
    }

    if (confirm('คุณต้องการเปิดเตาและสร้าง Batch ใหม่หมายเลข [' + gen_batch + '] ใช่หรือไม่?')) {
        var data_fun = document.getElementById("func") ? document.getElementById("func").value : '';
        window.location.assign('batch_annealing_entry.php?func=' + encodeURIComponent(data_fun) + '&bno=' + encodeURIComponent(gen_batch) + '&is_new=1');
    }
}
</script>
</html>