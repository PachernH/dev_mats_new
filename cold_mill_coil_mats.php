<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า jno และกำหนดตัวแปรให้ตรงกัน
$jno = !isset($_GET['jno']) ? '' : htmlspecialchars(trim($_GET['jno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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

        .btn-action-edit {
            background-color: #f59e0b;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s;
        }
        .btn-action-edit:hover {
            background-color: #d97706;
            color: #ffffff;
        }

        .btn-action-view {
            background-color: #10b981;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-view:hover {
            background-color: #059669;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Modern Table Styles */
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
        
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }

        .coil-no-text {
            font-weight: 700;
            color: #2563eb;
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

        .btn-action-print-coil {
            background-color: #0284c7;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-print-coil:hover {
            background-color: #0369a1;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        .btn-action-print-scrap {
            background-color: #e11d48;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-print-scrap:hover {
            background-color: #be123c;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A4';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="cold_mill_coil_mats.php?func=<?php echo $folder_func ?>">Cold Rolling Mill Coil Data</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="job_no">Job Process / Coil No.</label>
                    <input class="form-control-minimal" name="job_no" id="job_no" type="text" placeholder="Enter Job Process or Coil No. to search..." value="<?php echo htmlspecialchars($jno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Cold Mill Coil Production Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Action</th>
                                        <th>Job Process</th>
                                        <th>Coil No</th>
                                        <th>Operate Date</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Grade</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Pass (Cur/Total)</th>
                                        <th>Final Thickness</th>
                                        <th>Cold Mill Weight</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Label Coil</th>
                                        <th style="text-align: center;">Label Scrap</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $top_limit = "";

                                    if (!empty($jno)) {
                                        $where_clause = "AND (c.JOB_PROCESS = :jno OR c.COIL_NO = :jno)";
                                        $params[':jno'] = $jno;
                                    } else {
                                        if ($d == date('Y-m-d')) {
                                            $top_limit = "TOP (50)";
                                            $where_clause = "";
                                        } else {
                                            $where_clause = "AND c.COIL_OPERATEDATE BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $sql = "SELECT {$top_limit} 
                                                   c.JOB_PROCESS, c.COIL_NO, c.COIL_OPERATEDATE, c.ALLOY, c.TEMPER, c.GRADE,
                                                   c.THICKNESS, c.WIDTH, c.COIL_NEXTPROCESS, c.CURRENT_PASS, c.TOTAL_PASS,
                                                   c.THICKNESS_FINAL, c.COIL_ACTUALWEIGHT, c.COIL_COLDMILLWEIGHT, c.COIL_STATUS,
                                                   c.F_THICKNESS, c.ACTUAL_WIDTH, c.COIL_REMARK
                                            FROM COILPROD1 AS c 
                                            INNER JOIN CDMLPROD1 c2 ON c.COIL_NO = c2.COIL_NO 
                                            WHERE (c.LINE_PROCESS = 'M' OR c.LINE_PROCESS = 'S') 
                                              AND c.COIL_STATUS = 'AC' 
                                              {$where_clause}
                                            ORDER BY c.COIL_OPERATEDATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($coils_list as $index => $coil): 
                                        $safe_job = htmlspecialchars($coil['JOB_PROCESS'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $safe_coil = htmlspecialchars($coil['COIL_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $json_data = htmlspecialchars(json_encode($coil), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <button type="button" class="btn-action-edit" onclick="openEditModal(event, <?php echo $json_data; ?>)">
                                                ✏️ Edit Data
                                            </button>
                                        </td>
                                        <td align="center">
                                            <!-- ปุ่มสีเขียวสำหรับกดดูรายละเอียด Job Process -->
                                            <button type="button" class="btn-action-view" onclick="show_detail_cold('<?php echo addslashes($coil['JOB_PROCESS']); ?>')">
                                                🔍 <?php echo $safe_job; ?>
                                            </button>
                                        </td>
                                        <td class="coil-no-text"><?php echo $safe_coil; ?></td>
                                        <td><?php echo htmlspecialchars($coil['COIL_OPERATEDATE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($coil['GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)$coil['THICKNESS'], 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['WIDTH']) : number_format((float)$coil['WIDTH'], 3); ?></td>
                                        <td align="center"><?php echo htmlspecialchars(($coil['CURRENT_PASS'] ?? '0') . ' / ' . ($coil['TOTAL_PASS'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS_FINAL']) : number_format((float)$coil['THICKNESS_FINAL'], 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['COIL_COLDMILLWEIGHT']) : number_format((float)$coil['COIL_ACTUALWEIGHT'], 2); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars($coil['COIL_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td align="center">
                                            <a href="print_coil_product_label_mats.php?coilno=<?php echo urlencode($coil['COIL_NO']); ?>" target="_blank" class="btn-action-print-coil">
                                                🏷️ Label Coil
                                            </a>
                                        </td>
                                        <td align="center">
                                            <a href="print_scrap_product_label_mats.php?coilno=<?php echo urlencode($coil['COIL_NO']); ?>" target="_blank" class="btn-action-print-scrap">
                                                🏷️ Label Scrap
                                            </a>
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

<!-- Modal สำหรับแก้ไขข้อมูล -->
<div class="modal fade" id="editCoilModal" tabindex="-1" role="dialog" aria-labelledby="editCoilModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div class="modal-header" style="background-color: #1e293b; color: #fff; padding: 16px 24px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff; opacity: 0.8;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editCoilModalLabel" style="font-weight: 700; color: #ffffff;">✏️ Edit Coil Data (<span id="modal_coil_no_title"></span>)</h4>
            </div>
            <form id="formEditCoil">
                <div class="modal-body" style="padding: 24px;">
                    <input type="hidden" id="edit_coil_no" name="coil_no">
                    <input type="hidden" id="orig_coil_coldmillweight" name="orig_coil_coldmillweight">
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-weight:700; color:#475569;">COIL COLDMILLWEIGHT <span style="color:#ef4444; font-weight:normal; font-size:16px;">( Weight ≤ <span id="lbl_orig_weight">0.00</span>)</span></label>
                            <input type="number" step="0.01" class="form-control" id="edit_coil_coldmillweight" name="coil_coldmillweight" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label style="font-weight:700; color:#475569;">THICKNESS</label>
                            <input type="number" step="0.001" class="form-control" id="edit_f_thickness" name="f_thickness" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label style="font-weight:700; color:#475569;">ACTUAL THICKNESS</label>
                            <input type="number" step="0.001" class="form-control" id="edit_thickness" name="thickness" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-weight:700; color:#475569;">WIDTH</label>
                            <input type="number" step="0.01" class="form-control" id="edit_width" name="width" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-weight:700; color:#475569;">ACTUAL WIDTH</label>
                            <input type="number" step="0.01" class="form-control" id="edit_actual_width" name="actual_width" required>
                        </div>
                    </div>

                    <!-- เพิ่มส่วนเลือก Status Coil (AC, OP) -->
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label style="font-weight:700; color:#475569;">COIL STATUS</label>
                            <select class="form-control" id="edit_coil_status" name="coil_status" required>
                                <option value="AC">AC - Active</option>
                                <option value="OP">OP - Open / Operating</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label style="font-weight:700; color:#475569;">COIL REMARK</label>
                            <textarea class="form-control" id="edit_coil_remark" name="coil_remark" rows="3" style="height: auto; resize: vertical;" placeholder="กรอกหมายเหตุเพิ่มเติม..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight:600;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background-color: #2563eb !important; color: #ffffff !important; font-weight: 700; border: none; padding: 8px 20px; border-radius: 6px;">Save Changes</button>
                </div>
            </form>
        </div>
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
    var data_cno = document.getElementById("job_no").value; 
    window.location.assign('cold_mill_coil_mats.php?func='+encodeURIComponent(data_fun)+'&jno='+encodeURIComponent(data_cno)); 
}    

function show_detail_cold(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('cold_mill_coil_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&jno=' + encodeURIComponent(do_no));
}

// ฟังก์ชันเปิด Pop up Modal สำหรับแก้ไขข้อมูล
function openEditModal(event, coilData) {
    if (event) {
        event.stopPropagation();
    }

    var origWeight = parseFloat(coilData.COIL_COLDMILLWEIGHT || 0);

    $('#edit_coil_no').val(coilData.COIL_NO);
    $('#orig_coil_coldmillweight').val(origWeight);
    $('#lbl_orig_weight').text(origWeight.toFixed(2));
    $('#modal_coil_no_title').text(coilData.COIL_NO);
    
    // จัด Format ทศนิยมให้สวยงาม (2 ตำแหน่งสำหรับ Weight/Width, 3 ตำแหน่งสำหรับ Thickness)
    $('#edit_coil_coldmillweight').val(origWeight.toFixed(2));
    
    var fThick = parseFloat(coilData.F_THICKNESS || coilData.THICKNESS || 0);
    $('#edit_f_thickness').val(fThick.toFixed(3));
    
    var thick = parseFloat(coilData.THICKNESS || 0);
    $('#edit_thickness').val(thick.toFixed(3));
    
    var width = parseFloat(coilData.WIDTH || 0);
    $('#edit_width').val(width.toFixed(2));
    
    var actWidth = parseFloat(coilData.ACTUAL_WIDTH || coilData.WIDTH || 0);
    $('#edit_actual_width').val(actWidth.toFixed(2));
    
    $('#edit_coil_remark').val(coilData.COIL_REMARK || '');

    // แสดงข้อมูล Status เดิม และเลือกให้อัตโนมัติ (ถ้าไม่มีข้อมูลให้ Default เป็น AC)
    var currentStatus = coilData.COIL_STATUS ? coilData.COIL_STATUS.trim().toUpperCase() : 'AC';
    $('#edit_coil_status').val(currentStatus);
    
    $('#editCoilModal').modal('show');
}

// AJAX บันทึกการแก้ไขข้อมูลและสร้าง Auto Scrap
$('#formEditCoil').on('submit', function(e) {
    e.preventDefault();
    
    var newWeight = parseFloat($('#edit_coil_coldmillweight').val() || 0);
    var origWeight = parseFloat($('#orig_coil_coldmillweight').val() || 0);

    // ตรวจสอบเงื่อนไข COIL_COLDMILLWEIGHT ใหม่ ต้องน้อยกว่าหรือเท่ากับ ค่าเดิมเท่านั้น
    if (newWeight > origWeight) {
        alert('⚠️ Recording is not allowed!\n\nWeight COIL_COLDMILLWEIGHT New (' + newWeight + ') It must be less than or equal to the original weight (' + origWeight + ')');
        $('#edit_coil_coldmillweight').focus();
        return false;
    }

    if(!confirm('You confirm that you want to save the changes and create Scrap Record Yes / NO?')) {
        return;
    }

    $.ajax({
        url: 'model/update_coil_scrap_api_mats.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            if(res.status === 'success') {
                alert('Data successfully saved!\n Auto Scrap No: ' + res.scrap_no);
                location.reload();
            } else {
                alert('An error occurred: ' + res.message);
            }
        },
        error: function() {
            alert('Unable to connect to the backend server.');
        }
    });
});
</script>
</html>