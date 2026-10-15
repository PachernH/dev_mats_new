<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';

// 1. เชื่อมต่อฐานข้อมูล
require_once 'dbcon_mats-new.php';
$dbConnection = $conn ?? $pdoDB ?? $pdo ?? $db ?? null;

// ฟังก์ชันคำนวณจำนวนสัปดาห์ทั้งหมดในปี (ISO-8601)
function getWeeksInYear($yearFull) {
    $date = new DateTime();
    $date->setISODate($yearFull, 53);
    return ($date->format("W") === "53") ? 53 : 52;
}

// 2. รับค่า Filter จากฟอร์มแบบ POST
$selYearFull = !isset($_POST['selYearFull']) ? date('Y') : htmlspecialchars($_POST['selYearFull'], ENT_QUOTES, 'UTF-8');
$txtOnYear   = substr($selYearFull, -2); // ตัดเอาเฉพาะ 2 หลักท้าย (เช่น 2026 -> 26)
$maxWeeksInYear = getWeeksInYear(intval($selYearFull));

$txtWeekFrom = !isset($_POST['txtWeekFrom']) ? '01' : sprintf('%02d', intval($_POST['txtWeekFrom']));
$txtWeekTo   = sprintf('%02d', min($maxWeeksInYear, intval($txtWeekFrom) + 4));
$cmbProcess  = !isset($_POST['cmbProcess'])  ? 'WEEKLY CASTING PRODUCTION REPORT' : htmlspecialchars($_POST['cmbProcess'], ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

$reportData = [];
$weeks = [];
$errorMessage = '';

if ($dbConnection) {
    try {
        // --- ขั้นตอนที่ 1: ดึงช่วงวันที่ของสัปดาห์ทั้ง 5 สัปดาห์จากตาราง WKWKMSTR1 ---
        $weekDates = [];
        for ($i = 0; $i < 5; $i++) {
            $wNo = sprintf('%02d', intval($txtWeekFrom) + $i);
            $weeks[$i] = $wNo;

            $sqlWk = "SELECT WORK_FROM, WORK_TO FROM dbo.WKWKMSTR1 WITH (NOLOCK) WHERE WORK_YY = :yy AND WEEK_NO = :wk";
            $stmtWk = $dbConnection->prepare($sqlWk);
            $stmtWk->execute([':yy' => $txtOnYear, ':wk' => $wNo]);
            $rowWk = $stmtWk->fetch(PDO::FETCH_ASSOC);

            if ($rowWk) {
                $weekDates[$i] = [
                    'from' => !empty($rowWk['WORK_FROM']) ? date('Y-m-d', strtotime($rowWk['WORK_FROM'])) : '1900-01-01',
                    'to'   => !empty($rowWk['WORK_TO'])   ? date('Y-m-d', strtotime($rowWk['WORK_TO']))   : '1900-01-01'
                ];
            } else {
                $weekDates[$i] = ['from' => '1900-01-01', 'to' => '1900-01-01'];
            }
        }

        $dateStart1 = $weekDates[0]['from'];
        $dateEnd5   = $weekDates[4]['to'];

        // --- ขั้นตอนที่ 2: สร้าง Query ตามโปรเซสที่เลือกใน cmbProcess ---
        switch ($cmbProcess) {
            case "WEEKLY CASTING PRODUCTION REPORT":
                $sql = "SELECT COIL_NO, COIL_ENDDATE AS END_DATE, COIL_FIRSTWEIGHT AS WEIGHT, LINE_PROCESS, ALLOY, 
                               F_TEMPER AS TEMPER, F_THICKNESS AS THICKNESS, WIDTH, COIL_UOMDIMENSION, COIL_UOMWEIGHT, COIL_STATUS, 
                               '' AS REJECT_WEIGHT
                        FROM dbo.COILPROD1 WITH (NOLOCK)
                        WHERE COIL_ENDDATE BETWEEN :dt1 AND :dt2 AND (LINE_PROCESS = '1' OR LINE_PROCESS = '2' OR LINE_PROCESS = '3')
                        ORDER BY LINE_PROCESS ASC, ALLOY ASC, F_TEMPER ASC, F_THICKNESS ASC, WIDTH ASC, COIL_ENDDATE ASC";
                break;

            case "WEEKLY COLD MILL PRODUCTION REPORT":
                $sql = "SELECT A.COIL_NO, A.CDML_ENDDATE AS END_DATE, A.CDML_ACCEPTWEIGHT AS WEIGHT, A.CDML_REJECTWEIGHT AS REJECT_WEIGHT,
                               B.LINE_PROCESS, B.ALLOY, B.TEMPER, B.THICKNESS, B.WIDTH, B.COIL_UOMDIMENSION, B.COIL_UOMWEIGHT, '' AS COIL_STATUS
                        FROM dbo.CDMLPROD1 A WITH (NOLOCK) 
                        JOIN dbo.COILPROD1 B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO
                        WHERE A.CDML_ENDDATE BETWEEN :dt1 AND :dt2
                        ORDER BY B.LINE_PROCESS ASC, B.ALLOY ASC, B.TEMPER ASC, B.THICKNESS ASC, B.WIDTH ASC, A.CDML_ENDDATE ASC";
                break;

            case "WEEKLY SWISS SLITTER PRODUCTION REPORT":
                $sql = "SELECT A.COIL_NO, A.SWSL_ENDDATE AS END_DATE, A.SWSL_ACCEPTWEIGHT AS WEIGHT, A.SWSL_REJECTWEIGHT AS REJECT_WEIGHT,
                               B.LINE_PROCESS, B.ALLOY, B.TEMPER, B.THICKNESS, B.WIDTH, B.COIL_UOMDIMENSION, B.COIL_UOMWEIGHT, '' AS COIL_STATUS
                        FROM dbo.SWSLPROD1 A WITH (NOLOCK) 
                        JOIN dbo.COILPROD1 B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO
                        WHERE A.SWSL_ENDDATE BETWEEN :dt1 AND :dt2
                        ORDER BY B.LINE_PROCESS ASC, B.ALLOY ASC, B.TEMPER ASC, B.THICKNESS ASC, B.WIDTH ASC, A.SWSL_ENDDATE ASC";
                break;

            case "WEEKLY BATCH ANNEALING PRODUCTION REPORT":
                $sql = "SELECT A.PRODUCT_NO AS COIL_NO, A.BTCH_ENDDATE AS END_DATE, A.BTCH_ACCEPTWEIGHT AS WEIGHT, A.BTCH_REJECTWEIGHT AS REJECT_WEIGHT,
                               B.LINE_PROCESS, B.ALLOY, B.TEMPER, B.THICKNESS, B.WIDTH, B.COIL_UOMDIMENSION, B.COIL_UOMWEIGHT, '' AS COIL_STATUS
                        FROM dbo.BTCHPROD1 A WITH (NOLOCK) 
                        JOIN dbo.COILPROD1 B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO
                        WHERE A.PRODUCT_ID = 'CO' AND A.BTCH_ENDDATE BETWEEN :dt1 AND :dt2 AND (A.BTCH_RESULT = 'PASS' OR A.BTCH_RESULT = 'REJECT')
                        ORDER BY B.LINE_PROCESS ASC, B.ALLOY ASC, B.TEMPER ASC, B.THICKNESS ASC, B.WIDTH ASC, A.BTCH_ENDDATE ASC";
                break;

            case "WEEKLY PACKING COIL PRODUCTION REPORT":
                $sql = "SELECT A.PRODUCT_NO AS COIL_NO, A.PACK_DATE AS END_DATE, A.PACK_NETWEIGHT AS WEIGHT, 0 AS REJECT_WEIGHT,
                               B.LINE_PROCESS, B.ALLOY, B.TEMPER, B.THICKNESS, B.WIDTH, B.COIL_UOMDIMENSION, B.COIL_UOMWEIGHT, '' AS COIL_STATUS
                        FROM dbo.PACKPROD1 A WITH (NOLOCK) 
                        JOIN dbo.COILPROD1 B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO
                        WHERE A.PRODUCT_ID = 'CO' AND A.PACK_DATE BETWEEN :dt1 AND :dt2
                        ORDER BY B.LINE_PROCESS ASC, B.ALLOY ASC, B.TEMPER ASC, B.THICKNESS ASC, B.WIDTH ASC, A.PACK_DATE ASC";
                break;
        }

        $stmt = $dbConnection->prepare($sql);
        $pStart = ($cmbProcess === "WEEKLY PACKING COIL PRODUCTION REPORT") ? $dateStart1 . ' 00:00:00' : $dateStart1;
        $pEnd   = ($cmbProcess === "WEEKLY PACKING COIL PRODUCTION REPORT") ? $dateEnd5 . ' 23:59:59' : $dateEnd5;
        $stmt->execute([':dt1' => $pStart, ':dt2' => $pEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- ขั้นตอนที่ 3: จัดกลุ่มข้อมูลและสรุปยอด 5 สัปดาห์ ---
        $aggregated = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                $row['LINE_PROCESS'],
                $row['ALLOY'],
                $row['TEMPER'],
                sprintf('%.4f', $row['THICKNESS']),
                sprintf('%.4f', $row['WIDTH'])
            ]);

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'LINE_PROCESS'       => $row['LINE_PROCESS'],
                    'ALLOY'              => $row['ALLOY'],
                    'TEMPER'             => $row['TEMPER'],
                    'THICKNESS'          => $row['THICKNESS'],
                    'WIDTH'              => $row['WIDTH'],
                    'COIL_UOMDIMENSION'  => $row['COIL_UOMDIMENSION'] ?? '',
                    'COIL_UOMWEIGHT'     => $row['COIL_UOMWEIGHT'] ?? '',
                    'wk' => array_fill(0, 5, ['acc_pcs' => 0, 'acc_wt' => 0, 'rej_pcs' => 0, 'rej_wt' => 0])
                ];
            }

            $accWt = 0; $rejWt = 0;
            if ($cmbProcess === "WEEKLY CASTING PRODUCTION REPORT") {
                if (in_array($row['COIL_STATUS'], ['RJ', 'RM', 'MT'])) {
                    $rejWt = floatval($row['WEIGHT']);
                } else {
                    $accWt = floatval($row['WEIGHT']);
                }
            } else {
                $accWt = floatval($row['WEIGHT']);
                $rejWt = floatval($row['REJECT_WEIGHT']);
            }

            $endDate = date('Y-m-d', strtotime($row['END_DATE']));

            for ($i = 0; $i < 5; $i++) {
                if ($endDate >= $weekDates[$i]['from'] && $endDate <= $weekDates[$i]['to']) {
                    if ($accWt > 0) {
                        $aggregated[$key]['wk'][$i]['acc_pcs'] += 1;
                        $aggregated[$key]['wk'][$i]['acc_wt']  += $accWt;
                    }
                    if ($rejWt > 0) {
                        $aggregated[$key]['wk'][$i]['rej_pcs'] += 1;
                        $aggregated[$key]['wk'][$i]['rej_wt']  += $rejWt;
                    }
                }
            }
        }
        $reportData = array_values($aggregated);

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = "ไม่พบตัวแปรเชื่อมต่อฐานข้อมูลในไฟล์ dbcon_mats-new.php";
}

// 4. Export Excel
if (isset($_POST['export_excel'])) {
    $filename = "Coil_Weekly_Production_Report_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
    <table border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th colspan="28" style="font-size: 16px; font-weight: bold; text-align: left;">MEYER ALUMINIUM (THAILAND) CO.,LTD.</th>
            </tr>
            <tr>
                <th colspan="20" style="font-size: 14px; font-weight: bold; text-align: left;"><?=strtoupper($cmbProcess)?></th>
                <th colspan="8" style="text-align: right;">PRINT DATE : <?=date('m/d/Y')?> &nbsp; Page 1 of 1<br>PRINTING BY : <?=$iduser_func?></th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th rowspan="2">LINE</th>
                <th rowspan="2">ALLOY</th>
                <th rowspan="2">TEMPER</th>
                <th rowspan="2">THICKNESS</th>
                <th rowspan="2">WIDTH</th>
                <th rowspan="2">UOM DIM</th>
                <th rowspan="2">UOM WT</th>
                <?php for($i=0; $i<5; $i++): ?>
                    <th colspan="4">WEEK <?=$weeks[$i]?> (<?=$weekDates[$i]['from']?> - <?=$weekDates[$i]['to']?>)</th>
                <?php endfor; ?>
                <th colspan="4">TOTAL</th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <?php for($i=0; $i<6; $i++): ?>
                    <th>ACCEPT PCS</th>
                    <th>ACCEPT WT</th>
                    <th>REJECT PCS</th>
                    <th>REJECT WT</th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($reportData)): ?>
            <?php foreach ($reportData as $row): 
                $totAccPcs = 0; $totAccWt = 0; $totRejPcs = 0; $totRejWt = 0;
            ?>
                <tr>
                    <td style="text-align: center;"><?=$row['LINE_PROCESS']?></td>
                    <td style="text-align: center;"><?=$row['ALLOY']?></td>
                    <td style="text-align: center;"><?=$row['TEMPER']?></td>
                    <td style="text-align: right;"><?=number_format($row['THICKNESS'], 3)?></td>
                    <td style="text-align: right;"><?=number_format($row['WIDTH'], 2)?></td>
                    <td style="text-align: center;"><?=$row['COIL_UOMDIMENSION']?></td>
                    <td style="text-align: center;"><?=$row['COIL_UOMWEIGHT']?></td>
                    <?php for($i=0; $i<5; $i++): 
                        $wk = $row['wk'][$i];
                        $totAccPcs += $wk['acc_pcs']; $totAccWt += $wk['acc_wt'];
                        $totRejPcs += $wk['rej_pcs']; $totRejWt += $wk['rej_wt'];
                    ?>
                        <td style="text-align: right;"><?=$wk['acc_pcs'] > 0 ? number_format($wk['acc_pcs']) : ''?></td>
                        <td style="text-align: right;"><?=$wk['acc_wt'] > 0 ? number_format($wk['acc_wt'], 2) : ''?></td>
                        <td style="text-align: right;"><?=$wk['rej_pcs'] > 0 ? number_format($wk['rej_pcs']) : ''?></td>
                        <td style="text-align: right;"><?=$wk['rej_wt'] > 0 ? number_format($wk['rej_wt'], 2) : ''?></td>
                    <?php endfor; ?>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($totAccPcs)?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($totAccWt, 2)?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($totRejPcs)?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($totRejWt, 2)?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Coil Weekly Production Report</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <script src="assets/js/tailwindcss.js"></script>

<style>
    body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; font-size: 13px; background-color: #f8fafc; color: #1e293b; }
    .main-panel .content { padding: 15px 15px !important; }

    .rpt-table-wrapper {
        width: 100%;
        max-height: 72vh;
        overflow-y: auto;
        overflow-x: auto;
        background: #fff;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #cbd5e1;
    }

    /* ตารางบนหน้าจอ */
    table.rpt-table { 
        width: 100%; 
        min-width: 2000px; 
        border-collapse: collapse !important; 
        font-size: 12px; 
        color: #1e293b; 
        border: 1px solid #cbd5e1 !important;
    }

    table.rpt-table th {
        background-color: #f1f5f9 !important; 
        color: #0f172a; 
        font-weight: 700; 
        padding: 6px 4px;
        border: 1px solid #cbd5e1 !important; 
        white-space: nowrap; 
        text-align: center; 
        vertical-align: middle;
    }

    table.rpt-table thead tr:nth-child(1) th {
        position: sticky;
        top: 0;
        z-index: 20;
        box-shadow: inset 0 0 0 1px #cbd5e1;
    }

    table.rpt-table thead tr:nth-child(2) th {
        position: sticky;
        top: 31px;
        z-index: 19;
        box-shadow: inset 0 0 0 1px #cbd5e1;
    }

    table.rpt-table td { 
        padding: 5px 6px; 
        border: 1px solid #cbd5e1 !important; 
        white-space: nowrap; 
        vertical-align: middle; 
    }

    table.rpt-table tbody tr:hover { background-color: #f8fafc; }

    .input-group-custom { display: flex; flex-direction: column; gap: 4px; }
    .input-label-custom { font-size: 13.5px; font-weight: 700; color: #334155; }
    
    .input-field-custom {
        font-size: 15px !important; 
        font-weight: 700 !important; 
        color: #1d4ed8 !important; 
        background-color: #ffffff;
        border: 1.5px solid #a5b4fc; 
        border-radius: 8px; 
        padding: 5px 12px; 
        height: 42px !important;
        transition: all 0.2s ease;
    }
    .input-field-custom:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }

    .btn-action-lg {
        height: 40px; padding: 0 16px; font-size: 14px; font-weight: 700; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        white-space: nowrap; flex-shrink: 0; transition: all 0.2s ease; cursor: pointer;
    }

    .rpt-header-screen { margin-bottom: 12px; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px; }
    .rpt-header-screen h1 { font-size: 20px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: 0.3px; }
    .rpt-header-screen h2 { font-size: 16px; font-weight: 700; color: #2563eb; margin: 3px 0 0 0; letter-spacing: 0.3px; }
    .rpt-meta-screen { font-size: 13px !important; font-weight: 700 !important; color: #1e293b !important; line-height: 1.5; text-align: right; }

    /* ========================================================= */
    /* 🛠️ แก้ไขการพิมพ์ (PRINT CSS) บังคับเส้นขอบ 4 ด้านครบถ้วน */
    /* ========================================================= */
    @media print {
    /* ซ่อนส่วนที่ไม่จำเป็น */
    .sidebar, .navbar, .footer, .no-print, nav, header, form { 
        display: none !important; 
    }

    .main-panel { width: 100% !important; margin: 0 !important; padding: 0 !important; float: none !important; }
    .content { padding: 0 !important; margin: 0 !important; }
    body { background: #fff !important; color: #000 !important; font-size: 7pt !important; }

    @page { 
        size: A4 landscape; 
        margin: 3mm 3mm 3mm 3mm; /* ลดระยะขอบกระดาษเหลือ 3mm */
    }

    .print-container { 
        width: 100% !important; 
        max-width: 100% !important;
        border: none !important; 
        padding: 0 !important; 
        margin: 0 !important; 
        box-shadow: none !important;
    }

    .rpt-table-wrapper { 
        overflow: visible !important; 
        max-height: none !important; 
        border: none !important; 
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* บังคับตารางให้ย่อขนาดพอดีกระดาษ */
    table.rpt-table { 
        width: 100% !important; 
        max-width: 100% !important;
        min-width: 0 !important; 
        font-size: 5.5pt !important; /* ย่อขนาดฟอนต์เพื่อบีบคอลัมน์ */
        border-collapse: collapse !important; 
        border: 1pt solid #000 !important; 
        box-sizing: border-box !important;
        table-layout: auto !important;
        zoom: 92%; /* ย่อขนาดเนื้อหาตารางทั้งหมดลงเล็กน้อย */
    }

    table.rpt-table thead { display: table-header-group !important; }
    table.rpt-table tbody { display: table-row-group !important; }
    table.rpt-table tr { page-break-inside: avoid !important; }
    
    /* บังคับเส้นขอบทุกล็อค */
    table.rpt-table th,
    table.rpt-table td { 
        border: 0.5pt solid #000 !important; 
        padding: 1px 0.5px !important; /* ลดระยะห่างภายในเซลล์ */
        font-size: 5.5pt !important; 
        box-sizing: border-box !important;
        white-space: nowrap !important;
    }

    /* กำหนดเส้นขอบขวาสุดของตารางให้ชัดเจน */
    table.rpt-table th:last-child,
    table.rpt-table td:last-child {
        border-right: 1.5pt solid #000 !important;
    }

    table.rpt-table thead tr:nth-child(1) th,
    table.rpt-table thead tr:nth-child(2) th { 
        position: static !important;
        top: auto !important;
        box-shadow: none !important;
        background-color: #f1f5f9 !important; 
        color: #000 !important;
        font-weight: bold !important; 
        text-align: center !important;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }
}
</style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'r_pd';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

<div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed no-print">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">📊 Weekly Coil Production Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                
                <!-- Filter Form Modernised UI -->
                <div class="bg-white rounded-xl shadow-md border border-slate-200 p-4 mb-4 no-print">
                    <form method="POST" action="" id="reportForm" class="flex flex-col gap-3">
                        
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                            
                            <!-- Process Selection (5 Columns) -->
                            <div class="input-group-custom md:col-span-5">
                                <label class="input-label-custom">Process Report</label>
                                <select name="cmbProcess" class="input-field-custom w-full cursor-pointer shadow-sm">
                                    <option value="WEEKLY CASTING PRODUCTION REPORT" <?=$cmbProcess == 'WEEKLY CASTING PRODUCTION REPORT' ? 'selected' : ''?>>WEEKLY CASTING PRODUCTION REPORT</option>
                                    <option value="WEEKLY COLD MILL PRODUCTION REPORT" <?=$cmbProcess == 'WEEKLY COLD MILL PRODUCTION REPORT' ? 'selected' : ''?>>WEEKLY COLD MILL PRODUCTION REPORT</option>
                                    <option value="WEEKLY SWISS SLITTER PRODUCTION REPORT" <?=$cmbProcess == 'WEEKLY SWISS SLITTER PRODUCTION REPORT' ? 'selected' : ''?>>WEEKLY SWISS SLITTER PRODUCTION REPORT</option>
                                    <option value="WEEKLY BATCH ANNEALING PRODUCTION REPORT" <?=$cmbProcess == 'WEEKLY BATCH ANNEALING PRODUCTION REPORT' ? 'selected' : ''?>>WEEKLY BATCH ANNEALING PRODUCTION REPORT</option>
                                    <option value="WEEKLY PACKING COIL PRODUCTION REPORT" <?=$cmbProcess == 'WEEKLY PACKING COIL PRODUCTION REPORT' ? 'selected' : ''?>>WEEKLY PACKING COIL PRODUCTION REPORT</option>
                                </select>
                            </div>

                            <!-- Year Dropdown (3 Columns) -->
                            <div class="input-group-custom md:col-span-3">
                                <div class="flex justify-between items-center">
                                    <label class="input-label-custom">Select Year</label>
                                    <span id="lblWeekInfo" class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                                        <?=$maxWeeksInYear?> Weeks
                                    </span>
                                </div>
                                <select name="selYearFull" id="selYearFull" onchange="onYearChange()" class="input-field-custom w-full cursor-pointer shadow-sm">
                                    <?php 
                                    $currY = intval(date('Y'));
                                    for ($y = $currY; $y >= $currY - 5; $y--): 
                                        $selected = ($y == intval($selYearFull)) ? 'selected' : '';
                                        $labelExtra = ($y == $currY) ? ' (Current Year)' : '';
                                    ?>
                                        <option value="<?=$y?>" <?=$selected?>><?=$y . $labelExtra?></option>
                                    <?php endfor; ?>
                                </select>
                                <input type="hidden" name="txtOnYear" id="txtOnYear" value="<?=$txtOnYear?>">
                            </div>

                            <!-- Week Range Selection (4 Columns) -->
                            <div class="grid grid-cols-2 gap-2 md:col-span-4">
                                <div class="input-group-custom">
                                    <label class="input-label-custom">Week From</label>
                                    <select name="txtWeekFrom" id="txtWeekFrom" onchange="updateWeekTo()" class="input-field-custom w-full cursor-pointer shadow-sm">
                                        <?php for($w = 1; $w <= $maxWeeksInYear; $w++): 
                                            $wStr = sprintf('%02d', $w);
                                            $wSel = ($wStr === $txtWeekFrom) ? 'selected' : '';
                                        ?>
                                            <option value="<?=$wStr?>" <?=$wSel?>>Week <?=$wStr?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="input-group-custom">
                                    <label class="input-label-custom">Week To (+4 Wks)</label>
                                    <input type="text" name="txtWeekTo" id="txtWeekTo" value="<?=$txtWeekTo?>" readonly class="input-field-custom w-full text-center bg-slate-100 text-slate-500 font-bold">
                                </div>
                            </div>
                        </div>

                        <!-- Date Preview Badge Info -->
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600">
                            <span class="font-bold text-blue-700">🗓️ Date Range Preview:</span>
                            <span id="datePreviewText">Week <b class="text-slate-800"><?=$txtWeekFrom?></b> to Week <b class="text-slate-800"><?=$txtWeekTo?></b> in Year <b class="text-slate-800"><?=$selYearFull?></b> (YY: <b><?=$txtOnYear?></b>)</span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-end gap-2">
                            <button type="submit" class="btn-action-lg bg-blue-600 hover:bg-blue-700 text-white shadow-md font-bold min-w-[110px]">
                                🔍 ค้นหาข้อมูล
                            </button>
                            <a href="<?=$_SERVER['PHP_SELF']?>" class="btn-action-lg bg-slate-200 hover:bg-slate-300 text-slate-700 border border-slate-300 shadow-sm text-decoration-none font-bold min-w-[100px]">
                                🔄 ล้างค่า
                            </a>
                            <button type="submit" name="export_excel" value="1" class="btn-action-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-md font-bold min-w-[110px]">
                                📊 Export Excel
                            </button>
                            <button type="button" onclick="window.print()" class="btn-action-lg bg-slate-800 hover:bg-slate-900 text-white shadow-md font-bold min-w-[100px]">
                                🖨️ พิมพ์
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Report Render Area -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 print-container">
                    
                    <!-- Header Section -->
                    <div class="rpt-header-screen flex justify-between items-start">
                        <div>
                            <h1>MEYER ALUMINIUM (THAILAND) CO.,LTD.</h1>
                            <h2><?=strtoupper($cmbProcess)?></h2>
                        </div>
                        <div class="rpt-meta-screen">
                            <div>PRINT DATE : <?=date('m/d/Y')?> &nbsp;&nbsp; Page 1 of 1</div>
                            <div>PRINTING BY : <?=$iduser_func?></div>
                        </div>
                    </div>

                    <div class="rpt-table-wrapper">
                        <table class="rpt-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 50px;">LINE</th>
                                    <th rowspan="2" style="width: 70px;">ALLOY</th>
                                    <th rowspan="2" style="width: 60px;">TEMPER</th>
                                    <th rowspan="2" style="width: 80px;">THICKNESS</th>
                                    <th rowspan="2" style="width: 80px;">WIDTH</th>
                                    <th rowspan="2" style="width: 70px;">UOM<br>DIM</th>
                                    <th rowspan="2" style="width: 70px;">UOM<br>WT</th>
                                    <?php for($i=0; $i<5; $i++): ?>
                                        <th colspan="4">WEEK <?=$weeks[$i]?> (<?=date('d/m/Y', strtotime($weekDates[$i]['from']))?> - <?=date('d/m/Y', strtotime($weekDates[$i]['to']))?>)</th>
                                    <?php endfor; ?>
                                    <th colspan="4">TOTAL</th>
                                </tr>
                                <tr>
                                    <?php for($i=0; $i<6; $i++): ?>
                                        <th style="width: 60px;">ACCEPT<br>PCS</th>
                                        <th style="width: 80px;">ACCEPT<br>WT</th>
                                        <th style="width: 60px;">REJECT<br>PCS</th>
                                        <th style="width: 80px;">REJECT<br>WT</th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($errorMessage): ?>
                                <tr>
                                    <td colspan="31" class="text-center py-8 text-red-600 font-bold">
                                        ❌ เกิดข้อผิดพลาด: <?=htmlspecialchars($errorMessage)?>
                                    </td>
                                </tr>
                            <?php elseif (empty($reportData)): ?>
                                <tr>
                                    <td colspan="31" class="text-center py-8 text-slate-500 font-semibold">
                                        🚫 ไม่พบข้อมูลรายงานตามเงื่อนไขที่ระบุ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reportData as $row): 
                                    $totAccPcs = 0; $totAccWt = 0; $totRejPcs = 0; $totRejWt = 0;
                                ?>
                                    <tr>
                                        <td class="text-center"><?=$row['LINE_PROCESS']?></td>
                                        <td class="text-center font-bold text-blue-700"><?=$row['ALLOY']?></td>
                                        <td class="text-center"><?=$row['TEMPER']?></td>
                                        <td class="text-right"><?=number_format($row['THICKNESS'], 3)?></td>
                                        <td class="text-right"><?=number_format($row['WIDTH'], 2)?></td>
                                        <td class="text-center"><?=$row['COIL_UOMDIMENSION']?></td>
                                        <td class="text-center"><?=$row['COIL_UOMWEIGHT']?></td>
                                        <?php for($i=0; $i<5; $i++): 
                                            $wk = $row['wk'][$i];
                                            $totAccPcs += $wk['acc_pcs']; $totAccWt += $wk['acc_wt'];
                                            $totRejPcs += $wk['rej_pcs']; $totRejWt += $wk['rej_wt'];
                                        ?>
                                            <td class="text-right"><?=$wk['acc_pcs'] > 0 ? number_format($wk['acc_pcs']) : ''?></td>
                                            <td class="text-right"><?=$wk['acc_wt'] > 0 ? number_format($wk['acc_wt'], 2) : ''?></td>
                                            <td class="text-right text-red-600"><?=$wk['rej_pcs'] > 0 ? number_format($wk['rej_pcs']) : ''?></td>
                                            <td class="text-right text-red-600"><?=$wk['rej_wt'] > 0 ? number_format($wk['rej_wt'], 2) : ''?></td>
                                        <?php endfor; ?>
                                        <td class="text-right font-bold text-slate-900"><?=number_format($totAccPcs)?></td>
                                        <td class="text-right font-bold text-blue-700"><?=number_format($totAccWt, 2)?></td>
                                        <td class="text-right font-bold text-red-600"><?=number_format($totRejPcs)?></td>
                                        <td class="text-right font-bold text-red-600"><?=number_format($totRejWt, 2)?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php';?>
</div>
</div>

<script>
// คำนวณจำนวนสัปดาห์ใน ISO Year
function getWeeksInYearJS(year) {
    let d = new Date(year, 11, 31);
    let week = getWeekNumber(d);
    return (week === 1) ? 52 : week;
}

function getWeekNumber(d) {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    let yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

function onYearChange() {
    let selYear = document.getElementById('selYearFull').value;
    let yy = selYear.substring(2);
    document.getElementById('txtOnYear').value = yy;

    let maxWks = getWeeksInYearJS(parseInt(selYear));
    document.getElementById('lblWeekInfo').innerText = maxWks + " Weeks";

    // Re-populate Week From Dropdown
    let weekSelect = document.getElementById('txtWeekFrom');
    let currVal = weekSelect.value;
    weekSelect.innerHTML = '';
    
    for (let w = 1; w <= maxWks; w++) {
        let wStr = w.toString().padStart(2, '0');
        let opt = document.createElement('option');
        opt.value = wStr;
        opt.innerText = "Week " + wStr;
        if (wStr === currVal) opt.selected = true;
        weekSelect.appendChild(opt);
    }

    updateWeekTo();
}

function updateWeekTo() {
    let selYear = document.getElementById('selYearFull').value;
    let maxWks = getWeeksInYearJS(parseInt(selYear));
    let weekFromInput = document.getElementById('txtWeekFrom');
    let weekToInput = document.getElementById('txtWeekTo');
    
    let val = parseInt(weekFromInput.value) || 1;
    let toVal = Math.min(maxWks, val + 4);
    
    let toValStr = toVal.toString().padStart(2, '0');
    weekToInput.value = toValStr;

    // Update preview
    document.getElementById('datePreviewText').innerHTML = 
        'Week <b class="text-slate-800">' + weekFromInput.value + '</b> to Week <b class="text-slate-800">' + toValStr + '</b> in Year <b class="text-slate-800">' + selYear + '</b> (YY: <b>' + selYear.substring(2) + '</b>)';
}
</script>

</body>
<?php include 'include/footer.php';?>
</html>