<?php
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '';

include 'function_mats.php';
include 'dbcon_mats-new.php'; // ไฟล์เชื่อมต่อฐานข้อมูล PDO[cite: 5]

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

// --------------------------------------------------------------------------
// 1. ดึงข้อมูลของ Coil ปัจจุบัน (Current Coil)
// --------------------------------------------------------------------------
$current_data = null;
if (!empty($coil_no)) {
    try {
        $stmtCurr = $conn->prepare("SELECT * FROM COILINSP4 WHERE COIL_NO = :coil_no");
        $stmtCurr->execute([':coil_no' => $coil_no]);
        $current_data = $stmtCurr->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $current_data = null;
    }
}

$thickness_profile = isset($current_data['THICKNESS_PROFILE']) ? floatval($current_data['THICKNESS_PROFILE']) : 0.0000;
$thickness_average = isset($current_data['THICKNESS_AVERAGE']) ? floatval($current_data['THICKNESS_AVERAGE']) : 0.0000;

// --------------------------------------------------------------------------
// 2. คำนวณหาหมายเลข Coil ก่อนหน้า (Line เดียวกัน & ย้อนหลังไม่เกิน 1 วัน)
// --------------------------------------------------------------------------
$prev_coil_no = '';
if (!empty($coil_no)) {
    // แยกโครงสร้าง COIL_NO เช่น C260708-1-01
    if (preg_match('/^C(\d{6})-([^-]+)-(\d+)$/', $coil_no, $matches)) {
        $date_str = $matches[1]; // 260708 (YYMMDD)
        $line_no  = $matches[2]; // 1
        $seq      = intval($matches[3]); // 1

        if ($seq > 1) {
            // กรณีเป็น -02, -03 ฯลฯ ให้ลดลำดับคอยล์ลง 1 ในวันเดียวกัน
            $prev_seq = sprintf("%02d", $seq - 1);
            $prev_coil_no = 'C' . $date_str . '-' . $line_no . '-' . $prev_seq;
        } else {
            // กรณีเป็น -01 (เช่น C260708-1-01) -> คำนวณหาวันที่ย้อนหลัง 1 วัน
            // แปลง '260708' เป็น DateTime
            $year  = intval(substr($date_str, 0, 2)) + 2000;
            $month = intval(substr($date_str, 2, 2));
            $day   = intval(substr($date_str, 4, 2));
            
            $current_date = new DateTime("$year-$month-$day");
            $prev_date_obj = clone $current_date;
            $prev_date_obj->modify('-1 day'); // ย้อนหลัง 1 วันเท่านั้น (เช่น 08 -> 07)
            
            $prev_date_str = 'C' . $prev_date_obj->format('ymd'); // จะได้ 'C260707'
            $line_pattern  = $prev_date_str . '-' . $line_no . '-%'; // 'C260707-1-%'

            try {
                // ค้นหาเฉพาะคอยล์ในวันก่อนหน้า (ย้อนหลัง 1 วัน) และ Line เดียวกัน
                $stmtPrevCode = $conn->prepare("SELECT TOP 1 COIL_NO FROM COILINSP4 WHERE COIL_NO LIKE :line_pattern ORDER BY COIL_NO DESC");
                $stmtPrevCode->execute([':line_pattern' => $line_pattern]);
                $prevRow = $stmtPrevCode->fetch(PDO::FETCH_ASSOC);
                
                if ($prevRow) {
                    $prev_coil_no = $prevRow['COIL_NO']; // ได้ C260707-1-XX
                } else {
                    $prev_coil_no = ''; // ถ้าเกิน 1 วัน (เช่น ข้ามไป C260706) หรือหาไม่เจอ จะถือว่าไม่มีข้อมูล
                }
            } catch (PDOException $e) {
                $prev_coil_no = '';
            }
        }
    }
}

// ดึงข้อมูลจากตาราง COILINSP4 ของ Coil ก่อนหน้า (ถ้ามี)
$prev_data = null;
if (!empty($prev_coil_no)) {
    try {
        $stmtPrev = $conn->prepare("SELECT * FROM COILINSP4 WHERE COIL_NO = :prev_coil_no");
        $stmtPrev->execute([':prev_coil_no' => $prev_coil_no]);
        $prev_data = $stmtPrev->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $prev_data = null;
    }
}

// --------------------------------------------------------------------------
// 3. จัดเตรียมค่า S_THICKNESS (Start Coil) และ F_THICKNESS (End Coil)
// --------------------------------------------------------------------------
$start_coil_vals = [];
$end_coil_vals = [];

for ($i = 1; $i <= 33; $i++) {
    $idx = sprintf("%02d", $i);

    // --- Start Coil: ดึงจาก S_THICKNESS ของ Coil ก่อนหน้าตามเงื่อนไข ---
    if (isset($prev_data["S_THICKNESS{$idx}"])) {
        $start_coil_vals[$idx] = floatval($prev_data["S_THICKNESS{$idx}"]);
    } else {
        // ไม่มีคอยล์ก่อนหน้า หรือคอยล์ก่อนหน้าย้อนหลังเกิน 1 วัน ให้เป็น 0.000
        $start_coil_vals[$idx] = 0.000;
    }

    // --- End Coil: ดึงจาก F_THICKNESS ของ Coil ปัจจุบัน ---
    if (isset($current_data["F_THICKNESS{$idx}"])) {
        $end_coil_vals[$idx] = floatval($current_data["F_THICKNESS{$idx}"]);
    } else {
        $end_coil_vals[$idx] = 0.000;
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
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; font-size: 16px; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 0 0 12px 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; border-top: none; }
        .custom-tabs { border-bottom: 2px solid #e2e8f0; background-color: #ffffff; border-radius: 12px 12px 0 0; padding: 10px 15px 0 15px; border: 1px solid #e2e8f0; border-bottom: none; }
        .custom-tabs .nav-item .nav-link { border: none; color: #64748b; font-weight: 700; font-size: 17px; padding: 14px 24px; }
        .custom-tabs .nav-item.active .nav-link { color: #1e40af; border-bottom: 4px solid #1e40af; background: transparent; }
        
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }
        .card-title-g3 { color: #b45309; border-bottom: 2px solid #fde68a; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block; }
        
        .form-control { border-radius: 6px; border: 1px solid #cbd5e1; font-size: 16px; height: 44px; padding: 8px 12px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
        
        .readonly-control { background-color: #e2e8f0 !important; color: #475569 !important; font-weight: 600; cursor: not-allowed; }

        .comp-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
        .comp-table th { background-color: #1e293b; color: #ffffff; text-align: center; padding: 12px; font-size: 16px; }
        .comp-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 16px; vertical-align: middle; }
        .comp-table .elem-label { font-weight: 700; color: #1e40af; background-color: #f8fafc; width: 12%; font-size: 16px; }
        .comp-table .std-val { text-align: right; color: #334155; background-color: #f1f5f9; font-family: monospace; width: 14%; font-size: 16px; font-weight: 600; }
        
        .comp-table .sym-display { text-align: center; font-weight: 800; color: #d97706; font-size: 20px; font-family: monospace; width: 12%; background-color: #fffbeb; }
        
        .comp-table .chk-input { width: 22%; }
        .comp-table input.form-control { text-align: right; font-family: monospace; font-weight: 700; font-size: 17px; height: 40px; }

        .btn-save { background-color: #2563eb; color: #fff; font-weight: 700; padding: 12px 28px; border-radius: 8px; border: none; font-size: 17px; }
        .btn-save:hover { background-color: #1d4ed8; color: #fff; }
        .btn-back { background-color: #64748b; color: #fff; font-weight: 700; padding: 12px 20px; border-radius: 8px; border: none; font-size: 16px; }
        .btn-spectro { background-color: #0284c7; color: #ffffff; font-weight: 700; border: none; font-size: 16px; padding: 10px 20px; height: 44px; }
        .btn-spectro:hover { background-color: #0369a1; color: #ffffff; }
    </style>
    <!-- นำเข้า SheetJS Library สำหรับอ่านไฟล์ Excel -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>

<body>
<div class="wrapper">

<?php $menu = 'A3';?>

<?php   
    include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="virtual_millcert_ccsh_sup_mats.php?func=<?php echo $folder_func ?>">Virtual Coil Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                        ✏️ Defect Profile Coil: <span style="color:#2563eb;"><a href="virtual_coil_production_inspec_update_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                    </h3>                    
                    <div>
                        &nbsp;
                    </div>
                </div>


                <div class="tab-content">
                        <!-- แทรกส่วนนี้ใน <div class="tab-content"> ของไฟล์ coil_production_inspec_profile_mats.php -->
                        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<form id="profileForm">
    <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>">
    
    <div class="dashboard-card">
        <!-- Header Info -->
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Product No</label>
                    <input type="text" class="form-control readonly-control" value="<?php echo htmlspecialchars($coil_no); ?>" readonly>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Thickness Profile</label>
                    <input type="number" step="0.0001" id="THICKNESS_PROFILE" name="THICKNESS_PROFILE" class="form-control" value="<?php echo sprintf("%.4f", $thickness_profile); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Average Thickness</label>
                    <input type="number" step="0.0001" id="THICKNESS_AVERAGE" name="THICKNESS_AVERAGE" class="form-control" value="<?php echo sprintf("%.4f", $thickness_average); ?>">
                </div>
            </div>
        </div>

        <!-- Section: Start Coil (นำค่าคอยล์ก่อนหน้ามาแสดง) -->
        <div class="card-title-g1">Start Coil <?php echo !empty($prev_coil_no) ? "<small class='text-muted' style='font-size:12px;'>(Reference for End Coil: $prev_coil_no)</small>" : ""; ?></div>
        <div class="table-responsive" style="margin-bottom: 20px;">
            <table class="table table-bordered text-center" style="font-size: 12px; margin-bottom: 0;">
                <tr class="bg-primary text-white">
                    <?php 
                    $pos1 = [800, 750, 700, 650, 600, 550, 500, 450, 400, 350, 300, 250, 200, 150, 100, 50, 0];
                    foreach($pos1 as $p) echo "<th class='text-center'>$p</th>"; 
                    ?>
                </tr>
                <tr>
                    <?php for($i=1; $i<=17; $i++): $idx = sprintf("%02d", $i); ?>
                        <td style="padding: 2px;">
                            <input type="number" step="0.001" name="S_THICKNESS<?php echo $idx; ?>" class="form-control text-center p-1" style="height:30px; font-size:12px;" value="<?php echo sprintf("%.3f", $start_coil_vals[$idx]); ?>">
                        </td>
                    <?php endfor; ?>
                </tr>
                <tr class="bg-primary text-white">
                    <?php 
                    $pos2 = [50, 100, 150, 200, 250, 300, 350, 400, 450, 500, 550, 600, 650, 700, 750, 800];
                    foreach($pos2 as $p) echo "<th class='text-center'>$p</th>"; 
                    ?>
                    <th class="bg-secondary">-</th>
                </tr>
                <tr>
                    <?php for($i=18; $i<=33; $i++): $idx = sprintf("%02d", $i); ?>
                        <td style="padding: 2px;">
                            <input type="number" step="0.001" name="S_THICKNESS<?php echo $idx; ?>" class="form-control text-center p-1" style="height:30px; font-size:12px;" value="<?php echo sprintf("%.3f", $start_coil_vals[$idx]); ?>">
                        </td>
                    <?php endfor; ?>
                    <td class="bg-light"></td>
                </tr>
            </table>
        </div>

        <!-- Section: End Coil (ข้อมูลของ Coil ปัจจุบัน) -->
        <div class="card-title-g2">End Coil</div>
        <div class="table-responsive" style="margin-bottom: 20px;">
            <table class="table table-bordered text-center" style="font-size: 12px; margin-bottom: 0;">
                <tr class="bg-info text-white">
                    <?php foreach($pos1 as $p) echo "<th class='text-center'>$p</th>"; ?>
                </tr>
                <tr>
                    <?php for($i=1; $i<=17; $i++): $idx = sprintf("%02d", $i); ?>
                        <td style="padding: 2px;">
                            <input type="number" step="0.001" name="F_THICKNESS<?php echo $idx; ?>" class="form-control text-center p-1 end-coil-input" style="height:30px; font-size:12px;" value="<?php echo sprintf("%.3f", $end_coil_vals[$idx]); ?>">
                        </td>
                    <?php endfor; ?>
                </tr>
                <tr class="bg-info text-white">
                    <?php foreach($pos2 as $p) echo "<th class='text-center'>$p</th>"; ?>
                    <th class="bg-secondary">-</th>
                </tr>
                <tr>
                    <?php for($i=18; $i<=33; $i++): $idx = sprintf("%02d", $i); ?>
                        <td style="padding: 2px;">
                            <input type="number" step="0.001" name="F_THICKNESS<?php echo $idx; ?>" class="form-control text-center p-1 end-coil-input" style="height:30px; font-size:12px;" value="<?php echo sprintf("%.3f", $end_coil_vals[$idx]); ?>">
                        </td>
                    <?php endfor; ?>
                    <td class="bg-light"></td>
                </tr>
            </table>
            
        </div>
        <br>
        <div>
        <input type="file" id="excelFile" accept=".xlsx, .xls" style="display: none;" onchange="importExcelData(event)">
        <button type="button" class="btn btn-sm btn-spectro" onclick="document.getElementById('excelFile').click()">
            📂 Load file Excel (Machine Data)
        </button>

        <!-- ปุ่มที่เพิ่มเข้ามาใหม่ -->
        <button type="button" class="btn btn-spectro" onclick="retrieveDataProfile(event)" style="margin-left: 5px;">
            🔄 Retrieve Data Profile
        </button>
        </div>
        <br>
        <!-- Section: Chart -->
        <div class="card-title-g3">Caster Profile Graph</div>
        <div id="casterProfileChart" style="min-height: 250px;"></div>

        <div class="text-right" style="margin-top: 20px;">
            <button type="button" class="btn btn-save" onclick="SaveProfileData()">💾 Save Profile</button>
        </div>
    </div>
</form>
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>">
                        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
                </div>

        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script>
const categoriesX = ['800','750','700','650','600','550','500','450','400','350','300','250','200','150','100','50','0','50','100','150','200','250','300','350','400','450','500','550','600','650','700','750','800'];

var options = {
    series: [
        { name: 'Start Coil', data: [] },
        { name: 'End Coil', data: [] }
    ],
    chart: {
        height: 320,
        type: 'line',
        toolbar: { show: false },
        animations: { enabled: false }
    },
    stroke: {
        curve: 'straight',
        width: [2, 2],
        dashArray: [5, 0]
    },
    colors: ['#2563eb', '#dc2626'],
    xaxis: {
        categories: categoriesX,
        title: { text: 'Position (mm)', style: { fontWeight: 600 } }
    },
    yaxis: {
        min: -10,
        max: 10,
        tickAmount: 10,
        decimalsInFloat: 2,
        title: { text: 'Thickness Difference', style: { fontWeight: 600 } }
    },
    grid: {
        borderColor: '#e2e8f0',
        row: { colors: ['#f8fafc', 'transparent'], opacity: 0.5 }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right'
    }
};

var chart = new ApexCharts(document.querySelector("#casterProfileChart"), options);
chart.render();

// ฟังก์ชันดึงข้อมูลจากตารางมาวาดบนกราฟ
function updateChartData() {
    let startValues = [];
    let endValues = [];

    for (let i = 1; i <= 33; i++) {
        let idx = String(i).padStart(2, '0');
        let sEl = document.querySelector(`input[name="S_THICKNESS${idx}"]`);
        let fEl = document.querySelector(`input[name="F_THICKNESS${idx}"]`);
        
        startValues.push(sEl ? (parseFloat(sEl.value) || 0) : 0);
        endValues.push(fEl ? (parseFloat(fEl.value) || 0) : 0);
    }

    chart.updateSeries([
        { name: 'Start Coil', data: startValues },
        { name: 'End Coil', data: endValues }
    ]);
}

// อัปเดตกราฟครั้งแรกทันทีที่เปิดหน้า
$(document).ready(function() {
    updateChartData();
});

// ผูก Event Listeners เมื่อมีการพิมพ์แก้อยู่บนหน้าเว็บ
document.querySelectorAll('input[name^="S_THICKNESS"], input[name^="F_THICKNESS"]').forEach(input => {
    input.addEventListener('input', updateChartData);
});

// บันทึกข้อมูลผ่าน jQuery $.post
function SaveProfileData() {
    const $form = $('#profileForm');
    
    if ($form.length === 0) {
        alert('Form not found profileForm');
        return;
    }

    $.post('./model/add_profile_insp_mats.php', $form.serialize(), function(res) {
        if (res.status === 'success') {
            alert(res.message);
        } else {
            alert('A system error occurred: ' + res.message);
        }
    }, 'json')
    .fail(function(xhr, status, error) {
        alert('Unable to connect to the server. (Press F12 for details)');
        console.error('jQuery Post Error:', xhr.responseText);
    });
}

// ลำดับตำแหน่งระยะ 33 จุด (800 down to 0, then 50 up to 800)
const positionKeys = [
    "-800", "-750", "-700", "-650", "-600", "-550", "-500", "-450", "-400", "-350", "-300", "-250", "-200", "-150", "-100", "-50", "0",
    "50", "100", "150", "200", "250", "300", "350", "400", "450", "500", "550", "600", "650", "700", "750", "800"
];

function importExcelData(event) {
    const file = event.target.files[0];
    if (!file) return;

    const currentCoilNo = document.querySelector('input[name="coil_no"]').value.trim();
    if (!currentCoilNo) {
        alert('No current Coil No. found');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        
        // อ่าน Sheet แรก
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

        if (jsonData.length < 2) {
            alert('The Excel file structure is incorrect.');
            return;
        }

        // คอลัมน์ที่ 0 ใน Row แรกเก็บ Header ตำแหน่งระยะ เช่น -775, -750, ..., 800
        const headerRow = jsonData[0];
        
        // ทำ Mapping ชื่อตำแหน่งระยะเป็น Index ของคอลัมน์
        const colMap = {};
        headerRow.forEach((val, colIdx) => {
            if (val !== null && val !== undefined) {
                // แปลงค่า เช่น -650.0 เป็น "-650"
                const posStr = String(Math.round(parseFloat(val)));
                colMap[posStr] = colIdx;
            }
        });

        // ค้นหารายการที่มี Coil No. ตรงกัน (คอลัมน์แรก)
        let targetRow = null;
        for (let i = 1; i < jsonData.length; i++) {
            const rowCoilNo = jsonData[i][0] ? String(jsonData[i][0]).trim() : '';
            if (rowCoilNo.toLowerCase() === currentCoilNo.toLowerCase()) {
                targetRow = jsonData[i];
                break;
            }
        }

        if (!targetRow) {
            alert(`No information found Coil No.: ${currentCoilNo} In an Excel file.`);
            return;
        }

        // หอดึงค่าและหยอดลงในช่อง F_THICKNESS01 ถึง F_THICKNESS33
        positionKeys.forEach((posKey, idx) => {
            const fieldIdx = String(idx + 1).padStart(2, '0');
            const inputElem = document.querySelector(`input[name="F_THICKNESS${fieldIdx}"]`);
            
            if (inputElem) {
                const colIdx = colMap[posKey];
                let val = 0.000;
                
                if (colIdx !== undefined && targetRow[colIdx] !== undefined && targetRow[colIdx] !== null) {
                    val = parseFloat(targetRow[colIdx]) || 0.000;
                }
                
                inputElem.value = val.toFixed(3);
            }
        });

        // อัปเดตกราฟทันทีหลังนำเข้าข้อมูลสำเร็จ
        if (typeof updateChartData === 'function') {
            updateChartData();
        }

        alert(`Import End Coil data for ${currentCoilNo} Successfully completed.`);
        
        // ล้างค่า File Input ให้เลือกไฟล์เดิมซ้ำได้
        event.target.value = '';
    };

    reader.readAsArrayBuffer(file);
}


// ข้อควรระวัง: Web Server (เช่น Apache/IIS) ที่รัน PHP ต้องได้รับสิทธิ์ Access/Read Permission

function retrieveDataProfile(e) {
    const currentCoilNo = document.querySelector('input[name="coil_no"]').value.trim();
    if (!currentCoilNo) {
        alert('ไม่พบ Product No / Coil No');
        return;
    }

    // รองรับการดึงปุ่มกดทั้งจาก event parameter หรือ window.event
    const evt = e || window.event;
    const btn = evt ? (evt.currentTarget || evt.target) : null;
    let originalText = '';

    if (btn) {
        originalText = btn.innerHTML;
        btn.innerHTML = '⌛ Retrieving data....';
        btn.disabled = true;
    }

    $.post('./model/get_profile_excel.php', { coil_no: currentCoilNo }, function(res) {
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        if (res.status === 'success') {
            // นำค่าที่ได้มาหยอดลงในช่อง F_THICKNESS01 - F_THICKNESS33
            const data = res.data;
            positionKeys.forEach((posKey, idx) => {
                const fieldIdx = String(idx + 1).padStart(2, '0');
                const inputElem = document.querySelector(`input[name="F_THICKNESS${fieldIdx}"]`);
                if (inputElem && data[posKey] !== undefined) {
                    inputElem.value = parseFloat(data[posKey]).toFixed(3);
                }
            });

            // อัปเดตกราฟ
            if (typeof updateChartData === 'function') {
                updateChartData();
            }

            alert('Retrieving data. Profile succeed');
        } else {
            alert(res.message);
        }
    }, 'json')
    .fail(function(xhr, status, error) {
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
        alert('An error occurred while connecting to the server.');
        console.error(xhr.responseText);
    });
}
</script>
</html>