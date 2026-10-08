<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d        = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$alloy_no = !isset($_GET['alloy_no']) ? '' : htmlspecialchars(trim($_GET['alloy_no']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <!-- เพิ่ม SheetJS Library สำหรับ Export Excel (.xlsx) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

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

        /* Modern Table Styles & Font Scaling Up */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }

        /* สไตล์พิเศษสำหรับการแสดงผลตารางแนวตั้งของ Buffer Coil */
        .table-buffer-total {
            background-color: #eff6ff !important;
            font-weight: 700;
            color: #1d4ed8;
        }

        /* สไตล์สำหรับแถวในตารางที่กดได้ทั้ง Line */
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .clickable-row:hover {
            background-color: #f1f5f9 !important;
        }

        /* ปุ่มคลิกรหัสกระบวนการ */
        .btn-process-link {
            display: inline-block;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 6px;
            text-align: center;
            min-width: 65px;
            background-color: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            transition: all 0.2s ease;
        }
        .clickable-row:hover .btn-process-link {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        /* สไตล์ตารางรายละเอียดใน Modal */
        .modal-xl { 
            width: 98% !important; 
            max-width: 98% !important; 
            margin: 10px auto !important;
        }
        .table-detail th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-size: 13px;
            white-space: nowrap;
        }
        .table-detail td { white-space: nowrap; font-size: 14px; }

        /* ปุ่ม Export */
        .btn-export-excel {
            background-color: #16a34a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            padding: 6px 14px;
            transition: all 0.2s ease;
        }
        .btn-export-excel:hover {
            background-color: #15803d;
            color: #ffffff;
        }
        .btn-export-csv {
            background-color: #0284c7;
            color: #ffffff;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            padding: 6px 14px;
            transition: all 0.2s ease;
        }
        .btn-export-csv:hover {
            background-color: #0369a1;
            color: #ffffff;
        }

        /* แก้ปัญหามุมมองเงาดำทับหน้าต่าง Modal (Fix Backdrop Bug) */
        .modal {
            background: rgba(0, 0, 0, 0.5);
        }
        .modal-backdrop {
            display: none !important;
        }
    </style>
</head>
<body>

<?php $menu = 'coil_buffer';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Buffer Coil Dashboard</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
        
            <?php
            include 'function_mats.php';
            include("dbcon_mats-new.php");
            ini_set('max_execution_time', 300);

            // ดึงข้อมูลยอดคงค้างและรวมน้ำหนักแยกตาม Process
            $sql_buffer = "
            WITH PreparedData AS (
                SELECT 
                    CASE 
                        WHEN COIL_NEXTPROCESS = 'WS' THEN ISNULL(USE_FORPROCESS, '')
                        WHEN COIL_NEXTPROCESS = 'BA' AND COIL_WORKINPROCESS = 'BA' THEN ISNULL(USE_FORPROCESS, '')
                        ELSE ISNULL(COIL_NEXTPROCESS, '')
                    END AS Raw_Process,
                    ISNULL(COIL_BALANCEWEIGHT, 0) AS COIL_BALANCEWEIGHT
                FROM COILPROD1
                WHERE COIL_STATUS IN ('AC', 'OP')
            ),
            MappedData AS (
                SELECT 
                    CASE 
                        WHEN Raw_Process = '' OR Raw_Process = ' ' THEN 'ST'
                        WHEN Raw_Process = 'CTL' THEN 'CL'
                        WHEN Raw_Process = 'PKC' THEN 'PK'
                        WHEN Raw_Process = 'CUT' THEN 'CT'
                        ELSE Raw_Process
                    END AS Target_Process,
                    COIL_BALANCEWEIGHT
                FROM PreparedData
            ),
            SummaryCounts AS (
                SELECT Target_Process, COUNT(*) AS TotalCount, SUM(COIL_BALANCEWEIGHT) AS WeightTotal
                FROM MappedData
                GROUP BY Target_Process
            ),
            ExcelTemplate AS (
                SELECT 1 AS Seq, 'ST'  AS [No], 'Stock On Hand' AS [Description] UNION ALL
                SELECT 2, 'CM' , 'COLD MILL' UNION ALL
                SELECT 3, 'SS' , 'SWISS SLITTER' UNION ALL
                SELECT 4, 'XY5', 'XY CIRCLE SHEAR' UNION ALL
                SELECT 5, 'XYB', 'BLANKING' UNION ALL
                SELECT 6, 'CL' , 'CUT TO LENGTH' UNION ALL
                SELECT 7, 'PK' , 'PACKING' UNION ALL
                SELECT 8, 'KP1', 'K PRESS' UNION ALL
                SELECT 9, 'AP1', 'A PRESS' UNION ALL
                SELECT 10, 'SH' , 'SHEARING' UNION ALL
                SELECT 11, 'SC' , 'STRETCHER' UNION ALL
                SELECT 12, 'BA' , 'BATCH ANNEALING' UNION ALL
                SELECT 13, 'AN' , 'ANNEALING' UNION ALL
                SELECT 14, 'CT' , 'CUT SHEET' UNION ALL
                SELECT 15, 'PH' , 'PUNCH HOLE' UNION ALL
                SELECT 16, 'TW' , 'TRANSFER' UNION ALL
                SELECT 17, 'IS' , 'INSPECTION'
            )
            SELECT 
                t.[No],
                t.[Description],
                ISNULL(s.TotalCount, 0) AS [Total],
                ISNULL(s.WeightTotal, 0) AS [WTotal]
            FROM ExcelTemplate t
            LEFT JOIN SummaryCounts s ON t.[No] = s.Target_Process
            ORDER BY t.Seq;";

            $stmt_buf = $conn->prepare($sql_buffer);
            $stmt_buf->execute();

            $bufferTableData = [];
            $chartLabels = [];
            $chartQtyData = [];
            $chartWeightData = [];
            $grandTotalQty = 0;
            $grandTotalWeight = 0;

            while($row_buf = $stmt_buf->fetch(PDO::FETCH_ASSOC)) {
                $bufferTableData[] = $row_buf;
                $grandTotalQty += intval($row_buf['Total']);
                $grandTotalWeight += floatval($row_buf['WTotal']);
                
                if(intval($row_buf['Total']) > 0) {
                    $chartLabels[] = $row_buf['No'] . " (" . $row_buf['Description'] . ")";
                    $chartQtyData[] = intval($row_buf['Total']);
                    $chartWeightData[] = round(floatval($row_buf['WTotal']), 2);
                }
            }
            ?>

            <div class="row">
                <div class="col-lg-6 col-md-12">
                    <div class="dashboard-card" style="min-height: 480px; display: flex; flex-direction: column;">
                        <div style="margin-bottom: 20px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">📈 The graph shows the number of coils remaining (pieces).</h4>
                        </div>
                        <div style="flex: 1; position: relative; width: 100%;">
                            <canvas id="bufferQtyChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-md-12">
                    <div class="dashboard-card" style="min-height: 480px; display: flex; flex-direction: column;">
                        <div style="margin-bottom: 20px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">⚖️ The graph shows the total remaining weight (Kg).</h4>
                        </div>
                        <div style="flex: 1; position: relative; width: 100%;">
                            <canvas id="bufferWeightChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card" style="padding: 0px; overflow: hidden;">
                        <div style="padding: 20px 24px; border-bottom: 1px solid #e2e8f0;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:17px;">📋 Summary table of buffer coil quantity and weight by process (click on a row for details).</h4>
                        </div>
                        <div class="table-responsive" style="margin-top: 0;">
                            <table class="table table-hover" style="margin-bottom:0;">
                                <thead style="background-color: #f8fafc;">
                                    <tr>
                                        <th style="padding: 14px 16px; border-bottom:1px solid #e2e8f0; width: 12%;">No</th>
                                        <th style="padding: 14px 16px; border-bottom:1px solid #e2e8f0; width: 48%;">Description</th>
                                        <th style="padding: 14px 16px; border-bottom:1px solid #e2e8f0; text-align: right; width: 20%;">Total (pieces)</th>
                                        <th style="padding: 14px 16px; border-bottom:1px solid #e2e8f0; text-align: right; width: 20%;">Total (total weight)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-buffer-total">
                                        <td style="padding: 14px 16px;">Buffer Coil</td>
                                        <td style="padding: 14px 16px;">Next Process</td>
                                        <td style="padding: 14px 16px; text-align: right; font-size:16px;"><?php echo number_format($grandTotalQty); ?></td>
                                        <td style="padding: 14px 16px; text-align: right; font-size:16px;"><?php echo number_format($grandTotalWeight, 2); ?></td>
                                    </tr>
                                    <?php foreach ($bufferTableData as $buf): ?>
                                    <tr class="clickable-row"
                                        data-toggle="modal" 
                                        data-target="#modal_process_details" 
                                        onclick="openProcessDetails('<?php echo htmlspecialchars($buf['No'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($buf['Description'], ENT_QUOTES, 'UTF-8'); ?>')">
                                        
                                        <td style="padding: 8px 16px;">
                                            <span class="btn-process-link">
                                                <?php echo htmlspecialchars($buf['No'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; color:#334155; vertical-align: middle;"><?php echo htmlspecialchars($buf['Description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td style="padding: 12px 16px; text-align: right; font-weight:600; vertical-align: middle; color:<?php echo $buf['Total'] > 0 ? '#0f172a':'#94a3b8'; ?>;">
                                            <?php echo number_format($buf['Total']); ?>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: right; font-weight:600; vertical-align: middle; color:<?php echo $buf['WTotal'] > 0 ? '#1e3a8a':'#94a3b8'; ?>;">
                                            <?php echo number_format($buf['WTotal'], 2); ?>
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

        <!-- Modal แสดงรายละเอียดพร้อมปุ่ม Export CSV / Excel -->
        <div class="modal fade" id="modal_process_details" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="false">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                        <div class="modal-header bg-dark" style="padding: 15px 20px; background-color:#1e293b; display: flex; align-items: center; justify-content: space-between;">
                        <!-- หัวข้ออยู่ซ้ายสุด -->
                        <h4 class="modal-title" id="modal_process_title" style="color: #ffffff; font-weight: 700; margin: 0; text-align: left;">🔍 Details of the coil roll in the process.</h4>
                        
                        <!-- กลุ่มปุ่มขวาสุด -->
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                            <button type="button" class="btn btn-export-excel btn-sm" onclick="exportDetailsToExcel()">
                                <i class="fa fa-file-excel-o"></i> Export Excel
                            </button>
                            <button type="button" class="btn btn-export-csv btn-sm" onclick="exportDetailsToCSV()">
                                <i class="fa fa-file-text-o"></i> Export CSV
                            </button>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#ffffff; opacity:0.8; margin: 0 0 0 10px; line-height: 1;"><span aria-hidden="true">&times;</span></button>
                        </div>
                    </div>
                    <div class="modal-body" style="padding: 20px; max-height: 65vh; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover table-detail" id="tbl_process_detail">
                                <thead>
                                    <tr>
                                        <th>NO</th>
                                        <th>COIL NO</th>
                                        <th>PRODUCT REF.</th>
                                        <th>JOB PROCESS</th>
                                        <th>ALLOY</th>
                                        <th>TEMPER</th>
                                        <th>GRADE</th>
                                        <th>SURFACE GRADE</th>
                                        <th>THICKNESS</th>
                                        <th>ACTUAL WIDTH</th>
                                        
                                        <th>CUSTOMER ID</th>
                                        <th>COIL TYPE</th>
                                        
                                        <th>LINE PROCESS</th>
                                        <th>WORK PROCESS</th>
                                        <th>COIL NEXTPROCESS ID</th>
                                        <th>USE FORPROCESS</th>

                                        <th class="text-end">ACTUAL WEIGHT</th>
                                        <th>COIL REMARK</th>
                                        <th class="text-center">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody id="detail_table_body">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer" style="background-color: #f8fafc;">
                        <button type="button" class="btn btn-export-excel" onclick="exportDetailsToExcel()">
                            <i class="fa fa-file-excel-o"></i> Export Excel (.xlsx)
                        </button>
                        <button type="button" class="btn btn-export-csv" onclick="exportDetailsToCSV()">
                            <i class="fa fa-file-text-o"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:6px; font-weight: 600; padding: 8px 16px;">Close</button>
                    </div>
                </div>
            </div>
        </div>

<input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>" type="hidden"/>
<?php include 'include/content-footer.php';?>
</div>
</div>
</body>

<?php include 'include/footer.php';?>

<script src="assets/js/chart.js"></script>

<script type="text/javascript">       

let currentProcessCode = '';

function openProcessDetails(processCode, processDesc) {
    currentProcessCode = processCode;
    document.getElementById("modal_process_title").innerText = "🔍 Details of outstanding coil process.: " + processCode + " (" + processDesc + ")";
    document.getElementById("detail_table_body").innerHTML = "<tr><td colspan='19' class='text-center py-4'><i class='fa fa-spinner fa-spin'></i> Loading details...</td></tr>";
    
    $.ajax({
        url: "fetch_buffer_details_mats.php",
        type: "POST",
        data: { process: processCode },
        success: function(htmlResponse) {
            document.getElementById("detail_table_body").innerHTML = htmlResponse;
        },
        error: function(xhr, status, error) {
            document.getElementById("detail_table_body").innerHTML = "<tr><td colspan='19' class='text-center text-danger'> technical error occurred while loading the data.</td></tr>";
        }
    });
}

// 1. ฟังก์ชันสำหรับ Export ข้อมูลใน Modal เป็นไฟล์ Excel (.xlsx)
function exportDetailsToExcel() {
    const table = document.getElementById("tbl_process_detail");
    if (!table || table.querySelector("#detail_table_body").children.length === 0 || table.querySelector(".fa-spinner")) {
        alert("ไม่มีข้อมูลสำหรับ Export กรุณารอข้อมูลโหลดเสร็จสมบูรณ์");
        return;
    }
    
    const wb = XLSX.utils.table_to_book(table, { sheet: "Coil Details" });
    const fileName = "Outstanding_Coil_Details_" + (currentProcessCode || "Process") + "_" + new Date().toISOString().slice(0,10) + ".xlsx";
    XLSX.writeFile(wb, fileName);
}

// 2. ฟังก์ชันสำหรับ Export ข้อมูลใน Modal เป็นไฟล์ CSV (รองรับ UTF-8 ภาษาไทย)
function exportDetailsToCSV() {
    const table = document.getElementById("tbl_process_detail");
    if (!table || table.querySelector("#detail_table_body").children.length === 0 || table.querySelector(".fa-spinner")) {
        alert("ไม่มีข้อมูลสำหรับ Export กรุณารอข้อมูลโหลดเสร็จสมบูรณ์");
        return;
    }

    let csvContent = "";
    const rows = table.querySelectorAll("tr");

    rows.forEach(function(row) {
        const cols = row.querySelectorAll("th, td");
        let rowData = [];
        cols.forEach(function(col) {
            let text = col.innerText.replace(/"/g, '""').trim(); // Escape double quotes
            rowData.push('"' + text + '"');
        });
        csvContent += rowData.join(",") + "\n";
    });

    // ใส่ UTF-8 BOM (\uFEFF) เพื่อให้ Excel อ่านภาษาไทย/สัญลักษณ์ได้ถูกต้อง
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    const fileName = "Outstanding_Coil_Details_" + (currentProcessCode || "Process") + "_" + new Date().toISOString().slice(0,10) + ".csv";
    
    link.setAttribute("href", url);
    link.setAttribute("download", fileName);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

document.addEventListener("DOMContentLoaded", function() {
    const labels = <?php echo json_encode($chartLabels); ?>;
    const qtyValues = <?php echo json_encode($chartQtyData); ?>;
    const weightValues = <?php echo json_encode($chartWeightData); ?>;

    const ctxQty = document.getElementById('bufferQtyChart').getContext('2d');
    new Chart(ctxQty, {
        type: 'bar', 
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of coils (pieces)',
                data: qtyValues,
                backgroundColor: 'rgba(37, 99, 235, 0.75)',
                borderColor: 'rgba(29, 78, 216, 1)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Sarabun' } } },
                y: { grid: { display: false }, ticks: { font: { family: 'Sarabun', weight: '600' } } }
            }
        }
    });

    const ctxWeight = document.getElementById('bufferWeightChart').getContext('2d');
    new Chart(ctxWeight, {
        type: 'bar', 
        data: {
            labels: labels,
            datasets: [{
                label: 'Total weight (Kg)',
                data: weightValues,
                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                borderColor: 'rgba(5, 150, 105, 1)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Sarabun' } } },
                y: { grid: { display: false }, ticks: { font: { family: 'Sarabun', weight: '600' } } }
            }
        }
    });
});
</script>
</html>