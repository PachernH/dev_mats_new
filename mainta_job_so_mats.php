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

// รับค่า pno (ใช้รองรับ PRODUCT_NO) และกำหนดตัวแปรให้ตรงกัน
$pno = !isset($_GET['pno']) ? '' : htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8');

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
    <!-- เพิ่ม SweetAlert2 สำหรับป๊อปอัพยืนยันและแจ้งเตือน -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* ปุ่ม ลบ Job (กรณีมี Job) */
        .btn-action-deletejob {
            background-color: #ef4444;
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
        .btn-action-deletejob:hover {
            background-color: #dc2626;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* ปุ่ม ผูก Job (กรณีไม่มี Job) */
        .btn-action-bindjob {
            background-color: #2563eb;
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
        .btn-action-bindjob:hover {
            background-color: #1d4ed8;
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

        .status-badge {
            color: #059669;
            font-weight: 700;
            background-color: #ecfdf5;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A2';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="mainta_job_so_mats.php?func=<?php echo $folder_func ?>">Maintain Job Order and Sale Order of Product</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="pd_no">Product No</label>
                    <input class="form-control-minimal" name="pd_no" id="pd_no" type="text" placeholder="Enter Product No. to search..." value="<?php echo htmlspecialchars($pno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Maintain Job Order and Sale Order of Product Records (Max 500 Records)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Product No</th>
                                        <th style="text-align: center;">Start Date</th>
                                        <th style="text-align: center;">Sale Order No</th>
                                        <th style="text-align: center;">Sale Order Item</th>
                                        <th style="text-align: center;">Job Order</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: center;">Thickness</th>
                                        <th style="text-align: center;">Width</th>
                                        <th style="text-align: center;">Length</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Action Job</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $where_conditions = [];

                                    // เงื่อนไขสถานะ CRSH_STATUS
                                    $strPBCondition = " (c.CRSH_STATUS = 'OP' OR c.CRSH_STATUS = 'AC' OR c.CRSH_STATUS = 'ST' OR c.CRSH_STATUS = 'DS') ";
                                    $where_conditions[] = $strPBCondition;

                                    if (!empty($pno)) {
                                        $where_conditions[] = "(c.PRODUCT_NO = :pno OR c.SALEORDER_NO = :pno OR c.JOB_ORDER = :pno)";
                                        $params[':pno'] = $pno;
                                    } else {
                                        if ($d != date('Y-m-d')) {
                                            $where_conditions[] = "c.CRSH_STARTDATE BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

                                    $sql = "SELECT TOP 500 
                                                   c.PRODUCT_NO, c.PRODUCT_REFERENCE, c.CRSH_STARTDATE, c.SALEORDER_NO, 
                                                   c.SALEORDER_ITEM, c.JOB_ORDER, c.ALLOY, c.TEMPER, c.THICKNESS, 
                                                   c.WIDTH, c.LENGTH, c.CRSH_ACTUALWEIGHT, c.CRSH_ACTUALPIECE, c.CRSH_STATUS,
                                                   c.WEIGHT_PIECE, c.CRSH_BOTTOMWEIGHT
                                            FROM CRSHPROD1 AS c 
                                            {$where_sql}
                                            ORDER BY c.CRSH_STARTDATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $raw_prod_no = trim($coil['PRODUCT_NO'] ?? '');
                                    $raw_job_no  = trim($coil['JOB_ORDER'] ?? '');
                                    
                                    $prod_no    = htmlspecialchars($raw_prod_no !== '' ? $raw_prod_no : '-', ENT_QUOTES, 'UTF-8');
                                    $sale_order = htmlspecialchars($coil['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $job_order  = htmlspecialchars($raw_job_no !== '' ? $raw_job_no : '-', ENT_QUOTES, 'UTF-8');
                                    $status     = trim($coil['CRSH_STATUS'] ?? '');

                                    $start_date_formatted = !empty($coil['CRSH_STARTDATE']) ? date('Y-m-d', strtotime($coil['CRSH_STARTDATE'])) : '-';
                                    
                                    // ตรวจสอบว่ามี Job Order หรือไม่
                                    $has_job = (!empty($raw_job_no) && $raw_job_no !== '-');
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <button type="button" class="btn-action-view" onclick="show_detail_product('<?php echo addslashes($raw_prod_no); ?>')">
                                                🔍 <?php echo $prod_no; ?>
                                            </button>
                                        </td>
                                        <td align="center"><?php echo htmlspecialchars($start_date_formatted, ENT_QUOTES, 'UTF-8'); ?></td>
                                        
                                        <!-- Sale Order No ปุ่มกด -->
                                        <td align="center">
                                            <?php if (!empty($sale_order) && $sale_order !== '-'): ?>
                                                <?php $sale_item = htmlspecialchars($coil['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_saleorder('<?php echo addslashes($sale_order); ?>', '<?php echo addslashes($sale_item); ?>')">
                                                    🔍 <?php echo $sale_order; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td align="center"><?php echo htmlspecialchars($coil['SALEORDER_ITEM'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        
                                        <!-- Job Order ปุ่มกด -->
                                        <td align="center">
                                            <?php if ($has_job): ?>
                                                <button type="button" class="btn-action-view" onclick="show_detail_job('<?php echo addslashes($raw_job_no); ?>')">
                                                    🔍 <?php echo $job_order; ?>
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td align="center"><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['WIDTH']) : number_format((float)($coil['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['LENGTH']) : number_format((float)($coil['LENGTH'] ?? 0), 2); ?></td>
                                        
                                        <td align="center"><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <!-- ปุ่ม ลบ Job / ผูก Job -->
                                        <td align="center">
                                            <?php if ($has_job): ?>
                                                <button type="button" class="btn-action-deletejob" onclick="delete_job_action('<?php echo addslashes($raw_prod_no); ?>', '<?php echo addslashes($raw_job_no); ?>')">
                                                    🗑️ ลบ Job
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-action-bindjob" onclick="bind_job_action('<?php echo addslashes($raw_prod_no); ?>')">
                                                    🔗 ผูก Job
                                                </button>
                                            <?php endif; ?>
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
    var data_pno = document.getElementById("pd_no").value; 
    window.location.assign('mainta_job_so_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(data_pno)); 
}    

function show_detail_product(prod_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('mainta_crsh_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&PRODUCT_NO=' + encodeURIComponent(prod_no));
}

function show_detail_saleorder(so_no, so_item) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('mainta_crsh_saleorder_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(so_no) + '&so_item=' + encodeURIComponent(so_item));
}

function show_detail_job(job_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('mainta_crsh_job_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&search_no=' + encodeURIComponent(job_no));
}

// =========================================================================
// 1. ฟังก์ชันเมื่อกดปุ่ม "🗑️ ลบ Job" -> ดึงข้อมูล Preview มาแสดง Pop-up ก่อน
// =========================================================================
function delete_job_action(prod_no, job_no) {
    // แสดง Pop-up กำลังโหลดข้อมูล Preview
    Swal.fire({
        title: '<span style="font-size:24px; font-weight:800;">กำลังคำนวณยอดเปรียบเทียบ...</span>',
        text: 'กรุณารอสักครู่ ระบบกำลังประมวลผลข้อมูล Preview',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    // เรียก API หลังบ้าน (get_delete_preview.php) เพื่อดึงยอด Before, Deduct, After
    $.ajax({
        url: 'model/get_delete_preview.php',
        type: 'POST',
        data: { 
            product_no: prod_no, 
            job_order: job_no 
        },
        dataType: 'json',
        success: function(res) {
            if (res.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-size:26px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                    html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">${res.message}</span>`
                });
                return;
            }

            const data = res.data;

            // หากมีค่าน้ำหนัก/ชิ้นงานติดลบ (< 0) ไม่อนุญาตให้ลบ
            if (data.is_negative) {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-size:26px; font-weight:800;">ไม่สามารถลบรายการนี้ได้!</span>',
                    html: '<span style="font-size:18px; font-weight:700; color:#ef4444;">การลบรายการนี้จะส่งผลให้มียอดน้ำหนักหรือจำนวนชิ้นงานติดลบ (< 0)</span>',
                    confirmButtonText: 'ตกลง',
                    customClass: { confirmButton: 'swal-btn-confirm' }
                });
                return;
            }

            // รายการกระบวนการการผลิตทั้งหมด 13 รายการ
            const processList = [
                { name: 'Actual (ชิ้นงานจริง)', wtKey: 'totalAct', pcKey: 'totalActP' },
                { name: 'Produce (การผลิต)', wtKey: 'totalPro', pcKey: 'totalProP' },
                { name: 'Stretcher (รีดเหยียด)', wtKey: 'totalStr', pcKey: 'totalStrP' },
                { name: 'Cut Sheet (ตัดแผ่น)', wtKey: 'totalcutsh', pcKey: 'totalcutshP' },
                { name: 'Shear (ตัดซอย)', wtKey: 'totalsher', pcKey: 'totalsherP' },
                { name: 'Punch (ปั๊ม)', wtKey: 'totalPun', pcKey: 'totalPunP' },
                { name: 'Batch Anneal (อบม้วน)', wtKey: 'totalBatch', pcKey: 'totalBatchP' },
                { name: 'Transfer (ย้าย)', wtKey: 'totaltran', pcKey: 'totaltranP' },
                { name: 'Anneal (อบแผ่น)', wtKey: 'totalAnn', pcKey: 'totalAnnP' },
                { name: 'Combine (รวมแผ่น)', wtKey: 'totalCom', pcKey: 'totalComP' },
                { name: 'Sort (คัดแยก)', wtKey: 'TotalSort', pcKey: 'TotalSortP' },
                { name: 'Take Out (เอาออก)', wtKey: 'totalTake', pcKey: 'totalTakeP' },
                { name: 'Pack (บรรจุ)', wtKey: 'totalPack', pcKey: 'totalPackP' }
            ];

            // วนลูปสร้างแถวในตาราง
            let rowsHtml = '';
            processList.forEach(item => {
                let befWt = data.before[item.wtKey] || 0;
                let befPc = data.before[item.pcKey] || 0;
                let dedWt = data.deduct[item.wtKey] || 0;
                let dedPc = data.deduct[item.pcKey] || 0;
                let aftWt = data.after[item.wtKey] || 0;
                let aftPc = data.after[item.pcKey] || 0;

                // ข้ามการแสดงผลกรณีแถวนั้นเป็น 0 ทั้งหมด
                if (befWt === 0 && befPc === 0 && dedWt === 0 && dedPc === 0) return;

                rowsHtml += `
                    <tr>
                        <td style="font-weight:800; text-align:left; color:#1e293b;">${item.name}</td>
                        <td style="font-weight:700;">${numberWithCommas(befWt)} kg / ${numberWithCommas(befPc)} pcs</td>
                        <td style="font-weight:800; color:#dc2626;">-${numberWithCommas(dedWt)} kg / -${numberWithCommas(dedPc)} pcs</td>
                        <td style="font-weight:800; color:#16a34a;">${numberWithCommas(aftWt)} kg / ${numberWithCommas(aftPc)} pcs</td>
                    </tr>
                `;
            });

            // ตกแต่ง Pop-up ด้วย Custom Style ให้ตัวอักษรและปุ่มใหญ่ขึ้น 1 เท่า
            let htmlContent = `
                <style>
                    .swal-custom-container { font-size: 18px !important; font-weight: 700 !important; color: #1e293b; }
                    .preview-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 16px; }
                    .preview-table th { background-color: #334155; color: #ffffff; padding: 10px 6px; font-size: 17px; font-weight: 800; text-align: center; }
                    .preview-table td { border: 1px solid #cbd5e1; padding: 8px 6px; text-align: center; vertical-align: middle; }
                    .swal-btn-confirm { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
                    .swal-btn-cancel { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
                </style>
                <div class="swal-custom-container" style="text-align: left;">
                    <div style="background:#f1f5f9; padding:12px 16px; border-radius:8px; border:2px solid #cbd5e1; margin-bottom:12px; font-size:18px;">
                        <strong>Product No:</strong> <span style="color:#2563eb;">${prod_no}</span> | 
                        <strong>Job Order:</strong> <span style="color:#2563eb;">${job_no}</span>
                    </div>
                    <div style="font-size:19px; font-weight:800; color:#0f172a; margin-bottom:6px;">📊 รายละเอียดการเปรียบเทียบยอดการผลิต (Before / Deduct / After)</div>
                    <div style="max-height: 380px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">กระบวนการ</th>
                                    <th style="width: 24%;">ยอดปัจจุบัน (Before)</th>
                                    <th style="width: 24%; color:#fca5a5;">รายการที่จะลบ (Deduct)</th>
                                    <th style="width: 24%; color:#86efac;">ยอดคงเหลือ (After)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            // เปิด Pop-up แสดง Preview เพื่อให้ผู้ใช้ตรวจสอบ
            Swal.fire({
                title: '<span style="font-size:26px; font-weight:900; color:#0f172a;">🔍 ตรวจสอบรายละเอียดก่อนตัดสินค้า</span>',
                html: htmlContent,
                width: '900px',
                icon: 'warning',
                showCancelButton: true,
                confirmColor: '#ef4444',
                cancelColor: '#64748b',
                confirmButtonText: 'ยืนยันลบข้อมูลจริง',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    confirmButton: 'swal-btn-confirm',
                    cancelButton: 'swal-btn-cancel'
                }
            }).then((result) => {
                // หากผู้ใช้กด "ยืนยันลบข้อมูลจริง" ให้เรียกฟังก์ชันส่งลบจริงไปยังหลังบ้าน
                if (result.isConfirmed) {
                    execute_actual_delete(prod_no, job_no);
                }
            });
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: '<span style="font-size:24px; font-weight:800;">เชื่อมต่อระบบไม่สำเร็จ</span>',
                html: `<span style="font-size:18px; font-weight:700;">${error}</span>`
            });
        }
    });
}

// =========================================================================
// 2. ฟังก์ชันส่งคำสั่งลบจริงไปยัง mainta_del_job_mats.php เมื่อกด "ยืนยันลบข้อมูลจริง"
// =========================================================================
function execute_actual_delete(prod_no, job_no) {
    Swal.fire({
        title: '<span style="font-size:24px; font-weight:800;">กำลังลบข้อมูล...</span>',
        text: 'ระบบกำลังดำเนินการย้อนคืน Transaction และปรับยอดสะสม',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'model/mainta_del_job_mats.php',
        type: 'POST',
        data: { 
            product_no: prod_no, 
            job_order: job_no 
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '<span style="font-size:26px; font-weight:800;">สำเร็จ!</span>',
                    html: `<span style="font-size:18px; font-weight:700;">${response.message}</span>`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // โหลดหน้าใหม่เพื่ออัปเดตข้อมูลในตาราง
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-size:24px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                    html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">${response.message}</span>`
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: '<span style="font-size:24px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">ไม่สามารถเชื่อมต่อระบบหลังบ้านได้ (${error})</span>`
            });
        }
    });
}

// ฟังก์ชันแปลงตัวเลขให้ใส่ Comma (,)
function numberWithCommas(x) {
    if (x === null || x === undefined) return '0';
    return Number(x).toLocaleString('en-US');
}

// =========================================================================
// 1. ฟังก์ชันกดปุ่ม "🔗 ผูก Job" -> ขึ้น Pop-up ให้กรอก Job Order
// =========================================================================
function bind_job_action(prod_no) {
    Swal.fire({
        title: '<span style="font-size:26px; font-weight:900; color:#0f172a;">🔗 ผูก Job Order กับ สินค้า</span>',
        html: `
            <style>
                .swal-bind-input { font-size: 20px !important; font-weight: 700 !important; height: 50px !important; text-align: center; text-transform: uppercase; }
                .swal-btn-confirm { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
                .swal-btn-cancel { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
            </style>
            <div style="text-align: left; font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 15px;">
                <div style="background:#f1f5f9; padding:12px 16px; border-radius:8px; border:2px solid #cbd5e1; margin-bottom:15px;">
                    <strong>Product No:</strong> <span style="color:#2563eb;">${prod_no}</span>
                </div>
                <label for="swal_input_job" style="display:block; margin-bottom:8px;">กรุณากรอก Job Order ที่ต้องการผูก:</label>
                <input id="swal_input_job" class="form-control swal-bind-input" placeholder="เช่น JB-26-3463" oninput="this.value = this.value.toUpperCase()"/>
            </div>
        `,
        width: '600px',
        icon: 'info',
        showCancelButton: true,
        confirmColor: '#2563eb',
        cancelColor: '#64748b',
        confirmButtonText: 'ตรวจสอบยอด (Preview)',
        cancelButtonText: 'ยกเลิก',
        customClass: {
            confirmButton: 'swal-btn-confirm',
            cancelButton: 'swal-btn-cancel'
        },
        preConfirm: () => {
            const jobNo = document.getElementById('swal_input_job').value.trim();
            if (!jobNo) {
                Swal.showValidationMessage('กรุณากรอก Job Order ก่อนทำรายการ');
            }
            return jobNo;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const inputJobNo = result.value;
            // เมื่อกรอก Job Order แล้ว สั่งดึง Preview Before / Add / After
            get_bind_preview_action(prod_no, inputJobNo);
        }
    });
}

// =========================================================================
// 2. ฟังก์ชันดึง Preview ยอด (Before / Add / After) มาแสดงตารางเปรียบเทียบ
// =========================================================================
function get_bind_preview_action(prod_no, job_no) {
    Swal.fire({
        title: '<span style="font-size:24px; font-weight:800;">กำลังคำนวณยอดเปรียบเทียบ...</span>',
        text: 'กรุณารอสักครู่ ระบบกำลังประมวลผลข้อมูล Preview การผูก Job',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'model/get_bind_preview.php',
        type: 'POST',
        data: { product_no: prod_no, job_order: job_no },
        dataType: 'json',
        success: function(res) {
            if (res.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-size:26px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                    html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">${res.message}</span>`
                });
                return;
            }

            const data = res.data;

            const processList = [
                { name: 'Actual (ชิ้นงานจริง)', wtKey: 'totalAct', pcKey: 'totalActP' },
                { name: 'Produce (การผลิต)', wtKey: 'totalPro', pcKey: 'totalProP' },
                { name: 'Stretcher (รีดเหยียด)', wtKey: 'totalStr', pcKey: 'totalStrP' },
                { name: 'Cut Sheet (ตัดแผ่น)', wtKey: 'totalcutsh', pcKey: 'totalcutshP' },
                { name: 'Shear (ตัดซอย)', wtKey: 'totalsher', pcKey: 'totalsherP' },
                { name: 'Punch (ปั๊ม)', wtKey: 'totalPun', pcKey: 'totalPunP' },
                { name: 'Batch Anneal (อบม้วน)', wtKey: 'totalBatch', pcKey: 'totalBatchP' },
                { name: 'Transfer (ย้าย)', wtKey: 'totaltran', pcKey: 'totaltranP' },
                { name: 'Anneal (อบแผ่น)', wtKey: 'totalAnn', pcKey: 'totalAnnP' },
                { name: 'Combine (รวมแผ่น)', wtKey: 'totalCom', pcKey: 'totalComP' },
                { name: 'Sort (คัดแยก)', wtKey: 'TotalSort', pcKey: 'TotalSortP' },
                { name: 'Take Out (เอาออก)', wtKey: 'totalTake', pcKey: 'totalTakeP' },
                { name: 'Pack (บรรจุ)', wtKey: 'totalPack', pcKey: 'totalPackP' }
            ];

            let rowsHtml = '';
            processList.forEach(item => {
                let befWt = data.before[item.wtKey] || 0;
                let befPc = data.before[item.pcKey] || 0;
                let addWt = data.add[item.wtKey] || 0;
                let addPc = data.add[item.pcKey] || 0;
                let aftWt = data.after[item.wtKey] || 0;
                let aftPc = data.after[item.pcKey] || 0;

                if (befWt === 0 && befPc === 0 && addWt === 0 && addPc === 0) return;

                rowsHtml += `
                    <tr>
                        <td style="font-weight:800; text-align:left; color:#1e293b;">${item.name}</td>
                        <td style="font-weight:700;">${numberWithCommas(befWt)} kg / ${numberWithCommas(befPc)} pcs</td>
                        <td style="font-weight:800; color:#2563eb;">+${numberWithCommas(addWt)} kg / +${numberWithCommas(addPc)} pcs</td>
                        <td style="font-weight:800; color:#16a34a;">${numberWithCommas(aftWt)} kg / ${numberWithCommas(aftPc)} pcs</td>
                    </tr>
                `;
            });

            let htmlContent = `
                <style>
                    .swal-custom-container { font-size: 18px !important; font-weight: 700 !important; color: #1e293b; }
                    .preview-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 16px; }
                    .preview-table th { background-color: #334155; color: #ffffff; padding: 10px 6px; font-size: 17px; font-weight: 800; text-align: center; }
                    .preview-table td { border: 1px solid #cbd5e1; padding: 8px 6px; text-align: center; vertical-align: middle; }
                    .swal-btn-confirm { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
                    .swal-btn-cancel { font-size: 20px !important; font-weight: 800 !important; padding: 12px 28px !important; border-radius: 8px !important; }
                </style>
                <div class="swal-custom-container" style="text-align: left;">
                    <div style="background:#f1f5f9; padding:12px 16px; border-radius:8px; border:2px solid #cbd5e1; margin-bottom:12px; font-size:18px;">
                        <strong>Product No:</strong> <span style="color:#2563eb;">${prod_no}</span> | 
                        <strong>จะผูกเข้า Job Order:</strong> <span style="color:#16a34a;">${job_no}</span>
                    </div>
                    <div style="font-size:19px; font-weight:800; color:#0f172a; margin-bottom:6px;">📊 รายละเอียดการเพิ่มยอดการผลิต (Before / Add / After)</div>
                    <div style="max-height: 380px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th style="width: 28%;">กระบวนการ</th>
                                    <th style="width: 24%;">ยอดเดิม (Before)</th>
                                    <th style="width: 24%; color:#93c5fd;">รายการที่จะผูก (+Add)</th>
                                    <th style="width: 24%; color:#86efac;">ยอดรวมใหม่ (After)</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            Swal.fire({
                title: '<span style="font-size:26px; font-weight:900; color:#0f172a;">💾 ตรวจสอบรายละเอียดก่อนบันทึกการผูก Job</span>',
                html: htmlContent,
                width: '900px',
                icon: 'question',
                showCancelButton: true,
                confirmColor: '#16a34a',
                cancelColor: '#64748b',
                confirmButtonText: '💾 บันทึกการผูก Job (Save)',
                cancelButtonText: 'ยกเลิก',
                customClass: {
                    confirmButton: 'swal-btn-confirm',
                    cancelButton: 'swal-btn-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    execute_actual_bind(prod_no, job_no);
                }
            });
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: '<span style="font-size:24px; font-weight:800;">เชื่อมต่อระบบไม่สำเร็จ</span>',
                html: `<span style="font-size:18px; font-weight:700;">${error}</span>`
            });
        }
    });
}

// =========================================================================
// 3. ฟังก์ชันส่งคำสั่งบันทึกการผูก Job ไปยัง mainta_add_job_mats.php
// =========================================================================
function execute_actual_bind(prod_no, job_no) {
    Swal.fire({
        title: '<span style="font-size:24px; font-weight:800;">กำลังบันทึกข้อมูล...</span>',
        text: 'ระบบกำลังทำการผูก Job Order และรวมยอดสะสม',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: 'model/mainta_add_job_mats.php',
        type: 'POST',
        data: { product_no: prod_no, job_order: job_no },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '<span style="font-size:26px; font-weight:800;">บันทึกสำเร็จ!</span>',
                    html: `<span style="font-size:18px; font-weight:700;">${response.message}</span>`,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-size:24px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                    html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">${response.message}</span>`
                });
            }
        },
        error: function(xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: '<span style="font-size:24px; font-weight:800;">เกิดข้อผิดพลาด!</span>',
                html: `<span style="font-size:18px; font-weight:700; color:#ef4444;">ไม่สามารถเชื่อมต่อระบบหลังบ้านได้ (${error})</span>`
            });
        }
    });
}
</script>
</html>