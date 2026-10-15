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

// 3. ดึงข้อมูลรายงาน Furnace Charge
$reportData = [];
$errorMessage = '';

if ($dbConnection) {
    try {
        if (strtotime($dtpProduceTo) >= strtotime($dtpProduceFrom)) {
            $strProduceFrom = date('ymd', strtotime($dtpProduceFrom));
            $strProduceTo   = date('ymd', strtotime($dtpProduceTo));

            $bindParams = [];
            $whereConditions = [];

            if ($cmbLineProcess !== "ALL") {
                $whereConditions[] = "(LINE_PROCESS = :line1 AND BATCH_NO BETWEEN :m_from1 AND :m_to1)";
                $whereConditions[] = "(LINE_PROCESS = :line2 AND BATCH_NO BETWEEN :h_from1 AND :h_to1)";
                $whereConditions[] = "(LINE_PROCESS = :line3 AND BATCH_NO BETWEEN :f_from1 AND :f_to1)";

                $bindParams[':line1']  = $cmbLineProcess;
                $bindParams[':line2']  = $cmbLineProcess;
                $bindParams[':line3']  = $cmbLineProcess;
            } else {
                $whereConditions[] = "(BATCH_NO BETWEEN :m_from1 AND :m_to1)";
                $whereConditions[] = "(BATCH_NO BETWEEN :h_from1 AND :h_to1)";
                $whereConditions[] = "(BATCH_NO BETWEEN :f_from1 AND :f_to1)";
            }

            $bindParams[':m_from1'] = 'M' . $strProduceFrom . '-1-01';
            $bindParams[':m_to1']   = 'M' . $strProduceTo . '-2-99';
            
            $bindParams[':h_from1'] = 'H' . $strProduceFrom . '-1-01';
            $bindParams[':h_to1']   = 'H' . $strProduceTo . '-2-99';

            $bindParams[':f_from1'] = 'F' . $strProduceFrom . '-1-HL';
            $bindParams[':f_to1']   = 'F' . $strProduceTo . '-2-ML';

            $whereClause = implode(" OR ", $whereConditions);

            $sql = "
                SELECT 
                    ISNULL(BATCH_NO, '') AS batch_no,
                    ISNULL(BATCH_DATE, '') AS batch_date,
                    ISNULL(FURNACE, '') AS furnace,
                    ISNULL(LINE_PROCESS, '') AS line_process,
                    ISNULL(MATERIAL_IN, '') AS material_in,
                    ISNULL(ALLOY, '') AS alloy,
                    CAST(ISNULL(AL_REMAIN, 0) AS DECIMAL(12,2)) AS al_remain,
                    CAST(ISNULL(AL_INGOT, 0) AS DECIMAL(12,2)) AS al_ingot,
                    CAST(ISNULL(COIL_REMELT, 0) AS DECIMAL(12,2)) AS coil_remelt,
                    CAST(ISNULL(CAST_SCRAP, 0) AS DECIMAL(12,2)) AS cast_scrap,
                    CAST(ISNULL(OTHER_SCRAP, 0) AS DECIMAL(12,2)) AS other_scrap,
                    CAST(ISNULL(SCRAP_WIRE, 0) AS DECIMAL(12,2)) AS scrap_wire,
                    CAST(ISNULL(RECYCLE_SCRAP, 0) AS DECIMAL(12,2)) AS recycle_scrap,
                    CAST(ISNULL(MASTER_ALLOY, 0) AS DECIMAL(12,2)) AS master_alloy,
                    CAST(ISNULL(TOTAL_CHARGE, 0) AS DECIMAL(12,2)) AS total_charge,
                    CAST(ISNULL(TOTAL_DROSS, 0) AS DECIMAL(12,2)) AS total_dross,
                    CAST(ISNULL(BALANCE_BATCH, 0) AS DECIMAL(12,2)) AS balance_batch,
                    ISNULL(FRNC_OPERATOR1, '') AS operator
                FROM dbo.FRNCPRCS1 WITH (NOLOCK)
                WHERE {$whereClause}
                ORDER BY BATCH_NO ASC
            ";

            $stmt = $dbConnection->prepare($sql);
            $stmt->execute($bindParams);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $filename = "Furnace_Material_Charging_Report_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";
    ?>
    <table border="1">
        <thead>
            <tr>
                <th colspan="18" style="font-size: 16px; font-weight: bold; text-align: center;">MEYER ALUMINIUM (THAILAND) CO.,LTD.</th>
            </tr>
            <tr>
                <th colspan="18" style="font-size: 14px; font-weight: bold; text-align: center;">FURNACE MATERIAL CHARGING REPORT</th>
            </tr>
            <tr>
                <th colspan="9" style="text-align: left;">PRINT DATE: <?=date('m/d/Y H:i')?></th>
                <th colspan="9" style="text-align: right;">PRINTING BY: <?=$iduser_func?></th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th rowspan="2">NO.</th>
                <th rowspan="2">BATCH NO.</th>
                <th rowspan="2">FURNACE</th>
                <th rowspan="2">BATCH DATE</th>
                <th rowspan="2">MATERIAL IN</th>
                <th rowspan="2">ALLOY</th>
                <th rowspan="2">AL REMAIN</th>
                <th colspan="7">CHARGING MATERIAL</th>
                <th rowspan="2">TOTAL CHARGE IN FURNACE</th>
                <th rowspan="2">DROSS</th>
                <th rowspan="2">BATCH BALANCE</th>
                <th rowspan="2">OPERATOR</th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th>INGOT</th>
                <th>COIL REMELT</th>
                <th>CASTING SCRAP</th>
                <th>OTHER SCRAP</th>
                <th>SCRAP WIRE</th>
                <th>RECYCLE SCRAP</th>
                <th>MASTER ALLOY</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($reportData)): ?>
            <?php 
            $itemNo = 1;
            $totRemain = $totIngot = $totCoilRemelt = $totCastScrap = $totOtherScrap = 0;
            $totScrapWire = $totRecycle = $totMasterAlloy = $totCharge = $totDross = $totBalance = 0;

            foreach ($reportData as $row): 
                $totRemain      += $row['al_remain'];
                $totIngot       += $row['al_ingot'];
                $totCoilRemelt  += $row['coil_remelt'];
                $totCastScrap   += $row['cast_scrap'];
                $totOtherScrap  += $row['other_scrap'];
                $totScrapWire   += $row['scrap_wire'];
                $totRecycle     += $row['recycle_scrap'];
                $totMasterAlloy += $row['master_alloy'];
                $totCharge      += $row['total_charge'];
                $totDross       += $row['total_dross'];
                $totBalance     += $row['balance_batch'];
            ?>
                <tr>
                    <td style="text-align: center;"><?=$itemNo++?></td>
                    <td style="font-weight: bold; color: #1d4ed8;"><?=$row['batch_no']?></td>
                    <td style="text-align: center;"><?=$row['furnace']?></td>
                    <td style="text-align: center;"><?=$row['batch_date']?></td>
                    <td style="text-align: center;"><?=$row['material_in']?></td>
                    <td style="text-align: center;"><?=$row['alloy']?></td>
                    <td style="text-align: right;"><?=number_format($row['al_remain'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['al_ingot'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['coil_remelt'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['cast_scrap'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['other_scrap'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['scrap_wire'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['recycle_scrap'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['master_alloy'], 0)?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($row['total_charge'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['total_dross'], 0)?></td>
                    <td style="text-align: right;"><?=number_format($row['balance_batch'], 0)?></td>
                    <td><?=$row['operator']?></td>
                </tr>
            <?php endforeach; ?>
            <tr style="background-color: #cbd5e1; font-weight: bold;">
                <td colspan="6" style="text-align: center;">TOTAL</td>
                <td style="text-align: right;"><?=number_format($totRemain, 0)?></td>
                <td style="text-align: right;"><?=number_format($totIngot, 0)?></td>
                <td style="text-align: right;"><?=number_format($totCoilRemelt, 0)?></td>
                <td style="text-align: right;"><?=number_format($totCastScrap, 0)?></td>
                <td style="text-align: right;"><?=number_format($totOtherScrap, 0)?></td>
                <td style="text-align: right;"><?=number_format($totScrapWire, 0)?></td>
                <td style="text-align: right;"><?=number_format($totRecycle, 0)?></td>
                <td style="text-align: right;"><?=number_format($totMasterAlloy, 0)?></td>
                <td style="text-align: right; color: #0f172a;"><?=number_format($totCharge, 0)?></td>
                <td style="text-align: right;"><?=number_format($totDross, 0)?></td>
                <td style="text-align: right;"><?=number_format($totBalance, 0)?></td>
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
    <title>Meyer Aluminium Thailand Co.,LTD. - Furnace Material Charging Report</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <script src="assets/js/tailwindcss.js"></script>

<style>
    .rpt-table-wrapper {
        width: 100%;
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: auto;
        background: #fff;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #475569;
    }

    table.rpt-table { 
        width: 100%; 
        min-width: 1700px; 
        border-collapse: separate !important; 
        border-spacing: 0 !important;
        font-size: 13px; 
        color: #0f172a; 
    }

    table.rpt-table th {
        background-color: #f1f5f9 !important; 
        color: #0f172a; 
        font-weight: 700; 
        padding: 5px 4px;
        border-right: 1px solid #64748b !important;
        border-bottom: 1px solid #64748b !important;
        border-top: 1px solid #64748b !important;
        border-left: none;
        white-space: nowrap; 
        text-align: center; 
        vertical-align: middle;
        box-sizing: border-box;
    }

    table.rpt-table th:first-child {
        border-left: 1px solid #64748b !important;
    }

    table.rpt-table thead tr:nth-child(1) th {
        position: sticky;
        top: 0;
        z-index: 20;
    }

    table.rpt-table thead tr:nth-child(2) th {
        position: sticky;
        top: 31px;
        z-index: 19;
    }

    table.rpt-table td { 
        padding: 5px 6px; 
        border-right: 1px solid #94a3b8 !important;
        border-bottom: 1px solid #94a3b8 !important;
        border-top: none;
        border-left: none;
        white-space: nowrap; 
        vertical-align: middle; 
    }

    table.rpt-table td:first-child {
        border-left: 1px solid #94a3b8 !important;
    }

    table.rpt-table tbody tr:hover { background-color: #f8fafc; }

    body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; font-size: 14px; background-color: #f8fafc; }
    .main-panel .content { padding: 15px 15px !important; }

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

    .rpt-header-screen { margin-bottom: 15px; border-bottom: 2px solid #cbd5e1; padding-bottom: 12px; }
    .rpt-header-screen h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0; }
    .rpt-header-screen h2 { font-size: 17px; font-weight: 700; color: #2563eb; margin: 4px 0 0 0; }
    .rpt-meta-screen { font-size: 14px !important; font-weight: 700 !important; color: #1e293b !important; line-height: 1.5; }

    .grandtotal-row td {
        background-color: #ffffff !important; 
        font-size: 13px !important; 
        font-weight: bold !important; 
        color: #0f172a;
        border-top: 1.5px solid #000000 !important; 
        border-bottom: 2px double #000000 !important;
    }

    @media print {
        .sidebar, .navbar, .footer, .no-print, nav, header, form { 
            display: none !important; 
        }

        .main-panel { width: 100% !important; margin: 0 !important; padding: 0 !important; float: none !important; }
        .content { padding: 0 !important; margin: 0 !important; }
        body { background: #fff !important; color: #000 !important; font-size: 8pt !important; }

        @page { size: A4 landscape; margin: 5mm; }
        .print-container { width: 100% !important; border: none !important; padding: 0 !important; margin: 0 !important; }
        .rpt-table-wrapper { overflow: visible !important; max-height: none !important; border: none !important; }

        .rpt-header-screen { 
            display: flex !important; 
            justify-content: space-between !important; 
            align-items: flex-start !important; 
            margin-bottom: 8px !important; 
            border-bottom: 1.5pt solid #000 !important; 
            padding-bottom: 4px !important; 
        }
        .rpt-header-screen h1 { font-size: 13pt !important; font-weight: bold !important; color: #000 !important; margin: 0 !important; }
        .rpt-header-screen h2 { font-size: 11pt !important; font-weight: bold !important; color: #000 !important; margin: 2px 0 0 0 !important; }
        .rpt-meta-screen { font-size: 8.5pt !important; font-weight: bold !important; color: #000 !important; line-height: 1.3 !important; }

        table.rpt-table { 
            width: 100% !important; 
            min-width: 0 !important; 
            font-size: 6.5pt !important; 
            border-collapse: collapse !important; 
            border-spacing: 0 !important;
        }

        table.rpt-table thead tr:nth-child(1) th,
        table.rpt-table thead tr:nth-child(2) th,
        table.rpt-table th {
            position: static !important;
            top: auto !important;
            box-shadow: none !important;
        }

        table.rpt-table thead { display: table-header-group !important; }
        table.rpt-table tbody { display: table-row-group !important; }
        table.rpt-table tr { page-break-inside: avoid !important; }
        
        table.rpt-table th { 
            background-color: #f1f5f9 !important; 
            color: #000000 !important;
            border: 0.5pt solid #000000 !important; 
            padding: 2px 2px !important; 
            font-size: 6.5pt !important; 
            font-weight: bold !important; 
            text-align: center !important;
            vertical-align: middle !important;
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important;
        }

        table.rpt-table td, .grandtotal-row td { 
            border: 0.5pt solid #000000 !important; 
            padding: 2px 2px !important; 
            font-size: 6.5pt !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">🔥 Furnace Material Charging Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                
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

                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 print-container">
                    
                    <div class="rpt-header-screen flex justify-between items-start">
                        <div>
                            <h1>MEYER ALUMINIUM (THAILAND) CO.,LTD.</h1>
                            <h2>FURNACE MATERIAL CHARGING REPORT</h2>
                        </div>
                        <div class="text-right rpt-meta-screen">
                            <div>PRINT DATE : <?=date('m/d/Y H:i')?></div>
                            <div>PRINTING BY : <?=$iduser_func?></div>
                        </div>
                    </div>

                    <div class="rpt-table-wrapper">
                        <table class="rpt-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 40px;">NO.</th>
                                    <th rowspan="2" style="width: 120px;">BATCH NO.</th>
                                    <th rowspan="2" style="width: 80px;">FURNACE</th>
                                    <th rowspan="2" style="width: 150px;">BATCH DATE</th>
                                    <th rowspan="2" style="width: 90px;">MATERIAL<br>IN</th>
                                    <th rowspan="2" style="width: 70px;">ALLOY</th>
                                    <th rowspan="2" style="width: 85px;">AL<br>REMAIN</th>
                                    <th colspan="7" style="text-align: center;">CHARGING MATERIAL</th>
                                    <th rowspan="2" style="width: 100px;">TOTAL<br>CHARGE<br><span style="font-size:10px; font-weight:normal;">IN FURNACE</span></th>
                                    <th rowspan="2" style="width: 80px;">DROSS</th>
                                    <th rowspan="2" style="width: 90px;">BATCH<br>BALANCE</th>
                                    <th rowspan="2" style="width: 120px;">OPERATOR</th>
                                </tr>
                                <tr>
                                    <th style="width: 80px;">INGOT</th>
                                    <th style="width: 80px;">COIL<br>REMELT</th>
                                    <th style="width: 80px;">CASTING<br>SCRAP</th>
                                    <th style="width: 80px;">OTHER<br>SCRAP</th>
                                    <th style="width: 80px;">SCRAP<br>WIRE</th>
                                    <th style="width: 80px;">RECYCLE<br>SCRAP</th>
                                    <th style="width: 80px;">MASTER<br>ALLOY</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($errorMessage): ?>
                                <tr>
                                    <td colspan="18" class="text-center py-8 text-red-600 font-bold">
                                        ❌ เกิดข้อผิดพลาด: <?=htmlspecialchars($errorMessage)?>
                                    </td>
                                </tr>
                            <?php elseif (empty($reportData)): ?>
                                <tr>
                                    <td colspan="18" class="text-center py-8 text-slate-500 font-semibold">
                                        🚫 ไม่พบข้อมูลรายงานตามเงื่อนไขที่ระบุ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $itemNo = 1;
                                $totRemain = $totIngot = $totCoilRemelt = $totCastScrap = $totOtherScrap = 0;
                                $totScrapWire = $totRecycle = $totMasterAlloy = $totCharge = $totDross = $totBalance = 0;

                                foreach ($reportData as $row):
                                    $totRemain      += $row['al_remain'];
                                    $totIngot       += $row['al_ingot'];
                                    $totCoilRemelt  += $row['coil_remelt'];
                                    $totCastScrap   += $row['cast_scrap'];
                                    $totOtherScrap  += $row['other_scrap'];
                                    $totScrapWire   += $row['scrap_wire'];
                                    $totRecycle     += $row['recycle_scrap'];
                                    $totMasterAlloy += $row['master_alloy'];
                                    $totCharge      += $row['total_charge'];
                                    $totDross       += $row['total_dross'];
                                    $totBalance     += $row['balance_batch'];
                                ?>
                                    <tr>
                                        <td class="text-center font-semibold"><?=$itemNo++?></td>
                                        <td class="font-bold text-blue-700"><?=htmlspecialchars($row['batch_no'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['furnace'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['batch_date'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['material_in'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['alloy'])?></td>
                                        <td class="text-right"><?=number_format($row['al_remain'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['al_ingot'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['coil_remelt'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['cast_scrap'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['other_scrap'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['scrap_wire'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['recycle_scrap'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['master_alloy'], 0)?></td>
                                        <td class="text-right font-bold text-slate-900"><?=number_format($row['total_charge'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['total_dross'], 0)?></td>
                                        <td class="text-right"><?=number_format($row['balance_batch'], 0)?></td>
                                        <td><?=htmlspecialchars($row['operator'])?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="grandtotal-row">
                                    <td colspan="6" style="text-align: left; font-weight: bold;">TOTAL</td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totRemain, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totIngot, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totCoilRemelt, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totCastScrap, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totOtherScrap, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totScrapWire, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totRecycle, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totMasterAlloy, 0)?></td>
                                    <td style="text-align: right; font-weight: bold; color: #0f172a;"><?=number_format($totCharge, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totDross, 0)?></td>
                                    <td style="text-align: right; font-weight: bold;"><?=number_format($totBalance, 0)?></td>
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