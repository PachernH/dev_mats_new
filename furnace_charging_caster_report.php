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

// 2. รับค่า Filter จากฟอร์มแบบ POST
$dtpProduceFrom = !isset($_POST['dtpProduceFrom']) ? date('Y-m-d') : htmlspecialchars($_POST['dtpProduceFrom'], ENT_QUOTES, 'UTF-8');
$dtpProduceTo   = !isset($_POST['dtpProduceTo'])   ? date('Y-m-d', strtotime('+1 day')) : htmlspecialchars($_POST['dtpProduceTo'], ENT_QUOTES, 'UTF-8');
$cmbLineProcess = !isset($_POST['cmbLineProcess']) ? 'ALL' : htmlspecialchars($_POST['cmbLineProcess'], ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

// 3. ดึงข้อมูลและจัดกลุ่มข้อมูลตาม BATCH_NO
$groupedData = [];
$errorMessage = '';
$grandTotalWeight = 0;

if ($dbConnection) {
    try {
        if (strtotime($dtpProduceTo) >= strtotime($dtpProduceFrom)) {
            // แปลงรูปแบบวันที่เป็น YYMMDD ตามลอจิก VB6 (r5)
            $strProduceFrom = date('ymd', strtotime($dtpProduceFrom));
            $strProduceTo   = date('ymd', strtotime($dtpProduceTo));

            $bindParams = [];
            $whereConditions = [];

            if ($cmbLineProcess !== "ALL") {
                $whereConditions[] = "(F.LINE_PROCESS = :line1 AND F.BATCH_NO BETWEEN :m_from1 AND :m_to1)";
                $whereConditions[] = "(F.LINE_PROCESS = :line2 AND F.BATCH_NO BETWEEN :h_from1 AND :h_to1)";
                $whereConditions[] = "(F.LINE_PROCESS = :line3 AND F.BATCH_NO BETWEEN :f_from1 AND :f_to1)";

                $bindParams[':line1']  = $cmbLineProcess;
                $bindParams[':line2']  = $cmbLineProcess;
                $bindParams[':line3']  = $cmbLineProcess;
            } else {
                $whereConditions[] = "(F.BATCH_NO BETWEEN :m_from1 AND :m_to1)";
                $whereConditions[] = "(F.BATCH_NO BETWEEN :h_from1 AND :h_to1)";
                $whereConditions[] = "(F.BATCH_NO BETWEEN :f_from1 AND :f_to1)";
            }

            $bindParams[':m_from1'] = 'M' . $strProduceFrom . '-1-01';
            $bindParams[':m_to1']   = 'M' . $strProduceTo . '-2-99';
            
            $bindParams[':h_from1'] = 'H' . $strProduceFrom . '-1-01';
            $bindParams[':h_to1']   = 'H' . $strProduceTo . '-2-99';

            $bindParams[':f_from1'] = 'F' . $strProduceFrom . '-1-HL';
            $bindParams[':f_to1']   = 'F' . $strProduceTo . '-2-ML';

            $whereClause = implode(" OR ", $whereConditions);

            // SQL Join ตามโครงสร้าง r5 / FRNCPROD3
            $sql = "
                SELECT 
                    ISNULL(F.BATCH_NO, '') AS batch_no,
                    ISNULL(F.FURNACE, '') AS furnace,
                    ISNULL(F.MATERIAL_IN, '') AS batch_materialin,
                    ISNULL(F.ALLOY, '') AS batch_alloy,
                    CAST(ISNULL(F.AL_REMAIN, 0) AS DECIMAL(12,2)) AS al_remain,
                    CAST(ISNULL(F.AL_INGOT, 0) AS DECIMAL(12,2)) AS al_ingot,
                    CAST(ISNULL(F.COIL_REMELT, 0) AS DECIMAL(12,2)) AS product_remelt,
                    CAST(ISNULL(F.CAST_SCRAP, 0) AS DECIMAL(12,2)) AS cast_scrap,
                    CAST(ISNULL(F.OTHER_SCRAP, 0) AS DECIMAL(12,2)) AS other_scrap,
                    CAST(ISNULL(F.SCRAP_WIRE, 0) AS DECIMAL(12,2)) AS scrap_wire,
                    CAST(ISNULL(F.RECYCLE_SCRAP, 0) AS DECIMAL(12,2)) AS recycle_scrap,
                    CAST(ISNULL(F.MASTER_ALLOY, 0) AS DECIMAL(12,2)) AS master_alloy,
                    CAST(ISNULL(F.TOTAL_CHARGE, 0) AS DECIMAL(12,2)) AS total_charge,
                    CAST(ISNULL(F.TOTAL_DROSS, 0) AS DECIMAL(12,2)) AS total_dross,
                    CAST(ISNULL(F.BALANCE_BATCH, 0) AS DECIMAL(12,2)) AS balance_batch,
                    ISNULL(C.COIL_NO, '') AS coil_no,
                    ISNULL(C.ALLOY, '') AS coil_alloy,
                    CAST(ISNULL(C.COIL_CASTWEIGHT, 0) AS DECIMAL(12,2)) AS coil_weight,
                    ISNULL(C.COIL_STATUS, '') AS coil_status
                FROM dbo.FRNCPRCS1 F WITH (NOLOCK)
                LEFT JOIN dbo.COILPROD1 C WITH (NOLOCK) 
                    ON F.BATCH_NO = C.BATCH_NO AND (C.LINE_PROCESS = '1' OR C.LINE_PROCESS = '2')
                WHERE {$whereClause}
                ORDER BY F.BATCH_NO ASC, C.COIL_NO ASC
            ";

            $stmt = $dbConnection->prepare($sql);
            $stmt->execute($bindParams);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // จัดกลุ่มข้อมูลตาม BATCH_NO
            foreach ($rawRows as $row) {
                $bNo = $row['batch_no'];
                if (!isset($groupedData[$bNo])) {
                    $groupedData[$bNo] = [
                        'info' => [
                            'batch_no'         => $row['batch_no'],
                            'furnace'          => $row['furnace'],
                            'batch_materialin' => $row['batch_materialin'],
                            'batch_alloy'      => $row['batch_alloy'],
                            'al_remain'        => $row['al_remain'],
                            'al_ingot'         => $row['al_ingot'],
                            'product_remelt'   => $row['product_remelt'],
                            'cast_scrap'       => $row['cast_scrap'],
                            'other_scrap'      => $row['other_scrap'],
                            'scrap_wire'       => $row['scrap_wire'],
                            'recycle_scrap'    => $row['recycle_scrap'],
                            'master_alloy'     => $row['master_alloy'],
                            'total_charge'     => $row['total_charge'],
                            'total_dross'      => $row['total_dross'],
                            'balance_batch'    => $row['balance_batch'],
                        ],
                        'items' => []
                    ];
                }
                if (!empty($row['coil_no'])) {
                    $groupedData[$bNo]['items'][] = [
                        'coil_no'     => $row['coil_no'],
                        'coil_alloy'  => $row['coil_alloy'],
                        'coil_weight' => $row['coil_weight'],
                        'coil_status' => $row['coil_status'],
                    ];
                    $grandTotalWeight += $row['coil_weight'];
                }
            }
        } else {
            $errorMessage = "วันที่เริ่มต้นค้นหาต้องน้อยกว่าหรือเท่ากับวันที่สิ้นสุด";
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = "ไม่พบตัวแปรเชื่อมต่อฐานข้อมูลในไฟล์ dbcon_mats-new.php";
}

// 4. Export Excel
if (isset($_POST['export_excel'])) {
    $filename = "Furnace_Charging_Caster_Report_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
    <table border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th colspan="18" style="font-size: 16px; font-weight: bold; text-align: left;">MEYER ALUMINIUM (THAILAND) CO.,LTD.</th>
            </tr>
            <tr>
                <th colspan="14" style="font-size: 14px; font-weight: bold; text-align: left;">FURNACE ALUMINIUM CHARGING AND CASTER OUTPUT REPORT</th>
                <th colspan="4" style="text-align: right;">PRINT DATE : <?=date('m/d/Y')?> &nbsp; Page 1 of 1<br>PRINTING BY : <?=$iduser_func?></th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th rowspan="2">BATCH NO.</th>
                <th rowspan="2">MATERIAL IN</th>
                <th rowspan="2">ALLOY</th>
                <th rowspan="2">REMAIN AL</th>
                <th colspan="7">CHARGING MATERIAL</th>
                <th rowspan="2">TOTAL CHARGE</th>
                <th rowspan="2">DROSS</th>
                <th rowspan="2">BATCH BALANCE</th>
                <th rowspan="2">COIL NO.</th>
                <th rowspan="2">ALLOY</th>
                <th rowspan="2">WEIGHT</th>
                <th rowspan="2">ST.</th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th>INGOT</th>
                <th>PRODUCT REMELT</th>
                <th>CAST SCRAP</th>
                <th>OTHER SCRAP</th>
                <th>SCRAP WIRE</th>
                <th>RECYCLE SCRAP</th>
                <th>MASTER ALLOY</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($groupedData)): ?>
            <?php foreach ($groupedData as $batch): 
                $info = $batch['info'];
                $items = $batch['items'];
                $itemCount = count($items);
                $subWeight = 0;
            ?>
                <?php if ($itemCount > 0): ?>
                    <?php foreach ($items as $idx => $item): 
                        $subWeight += $item['coil_weight'];
                    ?>
                        <tr>
                            <?php if ($idx === 0): ?>
                                <td style="text-align: left; vertical-align: top;"><?=$info['batch_no']?></td>
                                <td style="text-align: center; vertical-align: top;"><?=$info['batch_materialin']?></td>
                                <td style="text-align: center; vertical-align: top;"><?=$info['batch_alloy']?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['al_remain'] > 0 ? number_format($info['al_remain']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['al_ingot'] > 0 ? number_format($info['al_ingot']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['product_remelt'] > 0 ? number_format($info['product_remelt']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['cast_scrap'] > 0 ? number_format($info['cast_scrap']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['other_scrap'] > 0 ? number_format($info['other_scrap']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['scrap_wire'] > 0 ? number_format($info['scrap_wire']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['recycle_scrap'] > 0 ? number_format($info['recycle_scrap']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['master_alloy'] > 0 ? number_format($info['master_alloy']) : ''?></td>
                                <td style="text-align: right; vertical-align: top; font-weight: bold;"><?=$info['total_charge'] > 0 ? number_format($info['total_charge']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['total_dross'] > 0 ? number_format($info['total_dross']) : ''?></td>
                                <td style="text-align: right; vertical-align: top;"><?=$info['balance_batch'] > 0 ? number_format($info['balance_batch']) : ''?></td>
                            <?php else: ?>
                                <td></td>
                                <td style="text-align: center; vertical-align: top;"><?=$info['batch_materialin']?></td>
                                <td style="text-align: center; vertical-align: top;"><?=$info['batch_alloy']?></td>
                                <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                            <?php endif; ?>
                            <td style="text-align: left;"><?=$item['coil_no']?></td>
                            <td style="text-align: center;"><?=$item['coil_alloy']?></td>
                            <td style="text-align: right;"><?=$item['coil_weight'] > 0 ? number_format($item['coil_weight']) : ''?></td>
                            <td style="text-align: center;"><?=$item['coil_status']?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold; background-color: #f8fafc;">
                        <td colspan="14"></td>
                        <td colspan="2" style="text-align: right;">SUBTOTAL</td>
                        <td style="text-align: right;"><?=number_format($subWeight)?></td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr style="font-weight: bold; background-color: #e2e8f0;">
                <td colspan="14"></td>
                <td colspan="2" style="text-align: right;">GRAND TOTAL</td>
                <td style="text-align: right;"><?=number_format($grandTotalWeight)?></td>
                <td></td>
            </tr>
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
    <title>Meyer Aluminium Thailand Co.,LTD. - Furnace Aluminium Charging and Caster Output Report</title>
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

    /* ตารางและเส้นขอบหน้าจอ */
    table.rpt-table { 
        width: 100%; 
        min-width: 1650px; 
        border-collapse: collapse !important; 
        font-size: 12px; 
        color: #1e293b; 
        border: 1px solid #cbd5e1;
    }

    /* Sticky Header สำหรับตาราง */
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

    /* สไตล์แถว Subtotal */
    .subtotal-row td {
        background-color: #f8fafc !important; 
        font-size: 12px !important; 
        font-weight: bold !important; 
        color: #0f172a;
        border: 1px solid #cbd5e1 !important;
    }

    /* สไตล์แถว Grand Total */
    .grandtotal-row td {
        background-color: #e2e8f0 !important; 
        font-size: 13px !important; 
        font-weight: bold !important; 
        color: #0f172a;
        border: 1.5px solid #0f172a !important;
    }

    /* Print CSS */
    @media print {
        .sidebar, .navbar, .footer, .no-print, nav, header, form { 
            display: none !important; 
        }

        .main-panel { width: 100% !important; margin: 0 !important; padding: 0 !important; float: none !important; }
        .content { padding: 0 !important; margin: 0 !important; }
        body { background: #fff !important; color: #000 !important; font-size: 8pt !important; }

        @page { size: A4 landscape; margin: 4mm; }
        .print-container { width: 100% !important; border: none !important; padding: 0 !important; margin: 0 !important; }
        .rpt-table-wrapper { overflow: visible !important; max-height: none !important; border: none !important; }

        .rpt-header-screen { 
            display: flex !important; 
            justify-content: space-between !important; 
            align-items: flex-start !important; 
            margin-bottom: 6px !important; 
            border-bottom: 1.5pt solid #000 !important; 
            padding-bottom: 3px !important; 
        }
        .rpt-header-screen h1 { font-size: 12pt !important; font-weight: bold !important; color: #000 !important; margin: 0 !important; }
        .rpt-header-screen h2 { font-size: 10pt !important; font-weight: bold !important; color: #000 !important; margin: 0 !important; }
        .rpt-meta-screen { font-size: 8pt !important; font-weight: bold !important; color: #000 !important; line-height: 1.2 !important; }

        table.rpt-table { 
            width: 100% !important; 
            min-width: 0 !important; 
            font-size: 6.5pt !important; 
            border-collapse: collapse !important; 
            border: 0.5pt solid #000 !important; 
        }

        table.rpt-table thead { display: table-header-group !important; }
        table.rpt-table tbody { display: table-row-group !important; }
        table.rpt-table tr { page-break-inside: avoid !important; }
        
        table.rpt-table thead tr:nth-child(1) th,
        table.rpt-table thead tr:nth-child(2) th,
        table.rpt-table th { 
            position: static !important;
            top: auto !important;
            box-shadow: none !important;
            background-color: #f1f5f9 !important; 
            color: #000 !important;
            border: 0.5pt solid #000 !important; 
            padding: 2px 2px !important; 
            font-size: 6.5pt !important; 
            font-weight: bold !important; 
            text-align: center !important;
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        /* บังคับวาดเส้นตารางทุกเซลล์เวลาสั่งพิมพ์ */
        table.rpt-table td { 
            border: 0.5pt solid #000 !important; 
            padding: 2px 2px !important; 
            font-size: 6.5pt !important;
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        /* บังคับเส้นตาราง Subtotal เวลาสั่งพิมพ์ */
        .subtotal-row td {
            background-color: #f8fafc !important;
            border: 0.5pt solid #000 !important;
        }

        /* บังคับเส้นตาราง Grand Total เวลาสั่งพิมพ์ */
        .grandtotal-row td {
            background-color: #e2e8f0 !important;
            border: 1pt solid #000 !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">🔥 Furnace Aluminium Charging and Caster Output Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                
                <!-- Filter Form -->
                <div class="bg-white rounded-xl shadow-md border border-slate-200 p-4 mb-4 no-print">
                    <form method="POST" action="" id="reportForm" class="flex flex-col gap-3">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="input-group-custom">
                                <label class="input-label-custom">Produce Date From</label>
                                <input type="date" name="dtpProduceFrom" id="dtpProduceFrom" value="<?=$dtpProduceFrom?>" class="input-field-custom w-full">
                            </div>

                            <div class="input-group-custom">
                                <label class="input-label-custom">Produce Date To</label>
                                <input type="date" name="dtpProduceTo" id="dtpProduceTo" value="<?=$dtpProduceTo?>" class="input-field-custom w-full">
                            </div>

                            <div class="input-group-custom">
                                <label class="input-label-custom">Line Process</label>
                                <select name="cmbLineProcess" class="input-field-custom w-full cursor-pointer shadow-sm">
                                    <option value="ALL" <?=$cmbLineProcess == 'ALL' ? 'selected' : ''?>>ALL</option>
                                    <option value="1" <?=$cmbLineProcess == '1' ? 'selected' : ''?>>1</option>
                                    <option value="2" <?=$cmbLineProcess == '2' ? 'selected' : ''?>>2</option>
                                </select>
                            </div>
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
                            <h2>FURNACE ALUMINIUM CHARGING AND CASTER OUTPUT REPORT</h2>
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
                                    <th rowspan="2" style="width: 100px;">BATCH NO.</th>
                                    <th rowspan="2" style="width: 80px;">MATERIAL<br>IN</th>
                                    <th rowspan="2" style="width: 60px;">ALLOY</th>
                                    <th rowspan="2" style="width: 70px;">REMAIN<br>AL</th>
                                    <th colspan="7">CHARGING MATERIAL</th>
                                    <th rowspan="2" style="width: 75px;">TOTAL<br>CHARGE</th>
                                    <th rowspan="2" style="width: 60px;">DROSS</th>
                                    <th rowspan="2" style="width: 75px;">BATCH<br>BALANCE</th>
                                    <th rowspan="2" style="width: 110px;">COIL NO.</th>
                                    <th rowspan="2" style="width: 65px;">ALLOY</th>
                                    <th rowspan="2" style="width: 85px;">WEIGHT</th>
                                    <th rowspan="2" style="width: 50px;">ST.</th>
                                </tr>
                                <tr>
                                    <th style="width: 65px;">INGOT</th>
                                    <th style="width: 65px;">PRODUCT<br>REMELT</th>
                                    <th style="width: 65px;">CAST<br>SCRAP</th>
                                    <th style="width: 65px;">OTHER<br>SCRAP</th>
                                    <th style="width: 65px;">SCRAP<br>WIRE</th>
                                    <th style="width: 65px;">RECYCLE<br>SCRAP</th>
                                    <th style="width: 65px;">MASTER<br>ALLOY</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($errorMessage): ?>
                                <tr>
                                    <td colspan="18" class="text-center py-8 text-red-600 font-bold">
                                        ❌ เกิดข้อผิดพลาด: <?=htmlspecialchars($errorMessage)?>
                                    </td>
                                </tr>
                            <?php elseif (empty($groupedData)): ?>
                                <tr>
                                    <td colspan="18" class="text-center py-8 text-slate-500 font-semibold">
                                        🚫 ไม่พบข้อมูลรายงานตามเงื่อนไขที่ระบุ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($groupedData as $batch): 
                                    $info = $batch['info'];
                                    $items = $batch['items'];
                                    $itemCount = count($items);
                                    $subWeight = 0;
                                ?>
                                    <?php if ($itemCount > 0): ?>
                                        <?php foreach ($items as $idx => $item): 
                                            $subWeight += $item['coil_weight'];
                                        ?>
                                            <tr>
                                                <?php if ($idx === 0): ?>
                                                    <td class="text-left align-top font-bold text-blue-700"><?=$info['batch_no']?></td>
                                                    <td class="text-center align-top"><?=$info['batch_materialin']?></td>
                                                    <td class="text-center align-top"><?=$info['batch_alloy']?></td>
                                                    <td class="text-right align-top"><?=$info['al_remain'] > 0 ? number_format($info['al_remain']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['al_ingot'] > 0 ? number_format($info['al_ingot']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['product_remelt'] > 0 ? number_format($info['product_remelt']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['cast_scrap'] > 0 ? number_format($info['cast_scrap']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['other_scrap'] > 0 ? number_format($info['other_scrap']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['scrap_wire'] > 0 ? number_format($info['scrap_wire']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['recycle_scrap'] > 0 ? number_format($info['recycle_scrap']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['master_alloy'] > 0 ? number_format($info['master_alloy']) : ''?></td>
                                                    <td class="text-right align-top font-bold text-slate-900"><?=$info['total_charge'] > 0 ? number_format($info['total_charge']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['total_dross'] > 0 ? number_format($info['total_dross']) : ''?></td>
                                                    <td class="text-right align-top"><?=$info['balance_batch'] > 0 ? number_format($info['balance_batch']) : ''?></td>
                                                <?php else: ?>
                                                    <td></td>
                                                    <td class="text-center align-top"><?=$info['batch_materialin']?></td>
                                                    <td class="text-center align-top"><?=$info['batch_alloy']?></td>
                                                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                                                <?php endif; ?>
                                                <td class="text-left font-medium"><?=$item['coil_no']?></td>
                                                <td class="text-center"><?=$item['coil_alloy']?></td>
                                                <td class="text-right"><?=$item['coil_weight'] > 0 ? number_format($item['coil_weight']) : ''?></td>
                                                <td class="text-center"><?=$item['coil_status']?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <!-- แถว SUBTOTAL -->
                                        <tr class="subtotal-row">
                                            <td colspan="14"></td>
                                            <td colspan="2" class="text-right font-bold">SUBTOTAL</td>
                                            <td class="text-right font-bold"><?=number_format($subWeight)?></td>
                                            <td></td>
                                        </tr>

                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <!-- แถว GRAND TOTAL -->
                                <tr class="grandtotal-row">
                                    <td colspan="14"></td>
                                    <td colspan="2" class="text-right font-bold">GRAND TOTAL</td>
                                    <td class="text-right font-bold"><?=number_format($grandTotalWeight)?></td>
                                    <td></td>
                                </tr>
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

</body>
<?php include 'include/footer.php';?>
</html>