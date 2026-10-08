<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include 'dbcon_mats-new.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// ดึง รายชื่อ Supplier มาใส่ใน Dropdown Filter
$suppliers = [];
try {
    $stmt_sppl = $conn->query("SELECT DISTINCT CSTMSPPL_ID FROM COILPROD1 WHERE CSTMSPPL_ID IS NOT NULL AND LTRIM(RTRIM(CSTMSPPL_ID)) <> '' ORDER BY CSTMSPPL_ID ASC");
    $suppliers = $stmt_sppl->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $suppliers = [];
}
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Recovery Performance Report</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <script src="assets/js/tailwindcss.js"></script>

    <!-- DataTables CSS for Template -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; }
        .main-panel .content { padding: 15px 15px !important; }
        
        .table-responsive { border: none !important; }
        #recoveryTable thead th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            white-space: nowrap;
            vertical-align: middle;
        }
        #recoveryTable tbody td {
            font-size: 14px;
            vertical-align: middle;
            white-space: nowrap;
        }
        /* Custom Loading Overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ffffff;
        }
        .spinner-border-lg {
            width: 3.5rem;
            height: 3.5rem;
            border-width: 0.35em;
        }
    </style>
</head>

<body>
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
                    <a class="navbar-brand" href="#">Recovery Performance Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <!-- START: Content Area -->
        <div class="content">
            <div class="container-fluid">
                <h3 class="mb-3 font-semibold text-gray-800">📊 Recovery Performance Report</h3>
                
                <!-- Searching Criteria Card -->
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-primary text-white font-bold p-3">
                        Searching Criteria
                    </div>

                    <div class="card-body p-4 bg-white">
                        <form id="searchForm">
                            <!-- แถวที่ 1: ช่องกรอกข้อมูล Searching Criteria -->
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label font-bold text-gray-700">COIL START DATE (FROM):</label>
                                    <input type="date" id="start_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-bold text-gray-700">TO:</label>
                                    <input type="date" id="end_date" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label font-bold text-gray-700">COIL SUPPLIER (CSTMSPPL_ID):</label>
                                    <select id="supplier_id" class="form-control">
                                        <option value="">-- ALL SUPPLIERS --</option>
                                        <?php foreach ($suppliers as $sppl): ?>
                                            <option value="<?php echo htmlspecialchars($sppl, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($sppl, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- แถวที่ 2: ขึ้นบรรทัดใหม่ สำหรับวางปุ่ม Search (จัดชิดขวา) -->
                            <div class="row mt-3">
                                <div class="col-md-12 d-flex justify-content-end">
                                    <button type="button" id="btn_search" onclick="loadReport()" class="btn btn-success font-bold px-4" style="background-color: #2e7d32; border-color: #2e7d32; color: #ffffff; height: 40px; min-width: 140px;">
                                        🔍 SEARCH
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Table Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 bg-white">
                        <div class="table-responsive">
                            <table id="recoveryTable" class="table table-hover table-striped table-bordered w-100 align-middle">
                                <thead>
                                    <tr>
                                        <th>COIL NO</th>
                                        <th>AgedDay</th>
                                        <th>PRODUCT REF.</th>
                                        <th>START DATE</th>
                                        <th>END DATE</th>
                                        <th>SUPPLIER</th>
                                        <th>ALLOY</th>
                                        <th>TEMPER</th>
                                        <th>GRADE</th>
                                        <th>MG</th>
                                        <th>SG</th>
                                        <th>THICKNESS</th>
                                        <th>WIDTH</th>
                                        <th>COIL WEIGHT (kg)</th>
                                        <th>PRODUCT WEIGHT (kg)</th>
                                        <th>% RECOVERY</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END: Content Area -->

<?php include 'include/content-footer.php';?>

    </div>
</div>

<!-- Loading Overlay Element (ซ่อนไว้ก่อนด้วย display: none !important) -->
<div id="loadingOverlay" style="display: none !important;">
    <h4 class="font-bold text-white mb-1" style="letter-spacing: 0.5px;">Loading...</h4>
    <h4 class="font-bold text-white mb-1" style="letter-spacing: 0.5px;">Processing data Traceability...</h4>
    <p class="text-gray-200 text-sm">Please wait a moment; the system is calculating the total for the coil family.</p>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
let dataTable;

$(document).ready(function() {
    dataTable = $('#recoveryTable').DataTable({
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rtip',
        buttons: [
            { extend: 'copy', className: 'btn btn-default btn-sm border' },
            { extend: 'excel', className: 'btn btn-default btn-sm border', title: 'Recovery_Performance_Report' },
            { extend: 'csv', className: 'btn btn-default btn-sm border' }
        ],
        pageLength: 20,
        scrollX: true,
        order: [[3, 'desc']],
        columns: [
            { 
                data: 'COIL_NO', 
                className: 'text-center font-bold',
                render: function(data) {
                    return `<span class="badge" style="background-color:#eff6ff; color:#1d4ed8; font-weight:700; padding:6px 12px; border:1px solid #bfdbfe; border-radius:12px;">${data}</span>`;
                }
            },
            { data: 'AgedDay', className: 'text-center font-bold' },
            { data: 'PRODUCT_REFERENCE', className: 'text-center' },
            { data: 'COIL_STARTDATE', className: 'text-center' },
            { data: 'COIL_ENDDATE', className: 'text-center' },
            { data: 'CSTMSPPL_ID', className: 'text-center' },
            { data: 'ALLOY', className: 'text-center' },
            { data: 'TEMPER', className: 'text-center' },
            { data: 'GRADE', className: 'text-center' },
            { data: 'MG', className: 'text-center' },
            { data: 'SG', className: 'text-center' },
            { data: 'THICKNESS', className: 'text-end', render: $.fn.dataTable.render.number(',', '.', 2) },
            { data: 'WIDTH', className: 'text-end', render: $.fn.dataTable.render.number(',', '.', 2) },
            { data: 'COIL_ACTUALWEIGHT', className: 'text-end', render: $.fn.dataTable.render.number(',', '.', 2) },
            { data: 'TOTAL_PRODUCT_WEIGHT', className: 'text-end', render: $.fn.dataTable.render.number(',', '.', 2) },
            { 
                data: 'Recovery_Pct',
                className: 'text-center font-bold',
                render: function(data) {
                    let badgeClass = data >= 80 ? 'label label-success' : 'label label-danger';
                    return `<span class="${badgeClass}" style="font-size: 13px; padding: 6px 10px; border-radius: 8px;">${data}%</span>`;
                }
            }
        ],
        language: {
            emptyTable: "Please press the SEARCH button to search for information."
        }
    });
    
});

function loadReport() {
    let $btn = $('#btn_search');
    
    // 1. เปิดแสดง Loading Overlay เมื่อเริ่มกดปุ่มค้นหา
    $('#loadingOverlay').removeAttr('style').css('display', 'flex').hide().fadeIn(200);

    // 2. ล็อคปุ่มกด
    $btn.prop('disabled', true).html('⏳ Searching...');

    let params = {
        start_date: $('#start_date').val(),
        end_date: $('#end_date').val(),
        supplier_id: $('#supplier_id').val()
    };

    $.getJSON('model/fetch_recovery_report.php', params, function(response) {
        if(response.status === 'success') {
            dataTable.clear().rows.add(response.data).draw();
        } else {
            alert('Error loading data: ' + response.message);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        alert('server connection error occurred: ' + textStatus);
    }).always(function() {
        // 3. ซ่อน Loading Overlay และคืนค่าปุ่มกดเสมอเมื่อการทำงานเสร็จสิ้น
        $('#loadingOverlay').fadeOut(200, function() {
            $(this).attr('style', 'display: none !important;');
        });
        $btn.prop('disabled', false).html('🔍 SEARCH');
    });
}
</script>

</body>
<?php include 'include/footer.php';?>
</html>