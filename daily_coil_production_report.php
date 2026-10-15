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

// 1. เชื่อมต่อฐานข้อมูลโดยตรง
require_once 'dbcon_mats-new.php';
$dbConnection = $conn ?? $pdoDB ?? $pdo ?? $db ?? null;

// 2. รับค่า Filter จากฟอร์มแบบ POST (ตรงตาม VB6 r2)
$dtpProduceFrom = !isset($_POST['dtpProduceFrom']) ? date('Y-m-d', strtotime('-1 day')) : htmlspecialchars($_POST['dtpProduceFrom'], ENT_QUOTES, 'UTF-8');
$dtpProduceTo   = date('Y-m-d', strtotime($dtpProduceFrom . ' +1 day'));

$cmbProcess     = !isset($_POST['cmbProcess']) ? 'DAILY CASTING PRODUCTION REPORT' : htmlspecialchars($_POST['cmbProcess'], ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

// 3. ดึงข้อมูลรายงานตามประเภท Process ที่เลือก
$reportData = [];
$errorMessage = '';

if ($dbConnection) {
    try {
        $bindParams = [];
        $lineProcessExpression = "ISNULL(B.LINE_PROCESS, '1')";
        $jobOrderExpression    = "ISNULL(B.JOB_ORDER, ISNULL(B.JOB_PROCESS, ''))";

        switch ($cmbProcess) {
            case "DAILY COLD MILL PRODUCTION REPORT":
                $dateField = "A.CDML_ENDDATE";
                $timeField = "A.CDML_ENDTIME";
                $tableJoin = "dbo.CDMLPROD1 AS A WITH (NOLOCK) LEFT JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO";
                $weightAccept = "CAST(ISNULL(A.CDML_ACCEPTWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "CAST(ISNULL(A.CDML_REJECTWEIGHT, 0) AS DECIMAL(12,2))";
                $operatorField = "ISNULL(A.CDML_OPERATOR1, '')";
                $totalPassField = "ISNULL(B.TOTAL_PASS, 0)";
                $productField = "A.COIL_NO";
                $jobOrderExpression = "ISNULL(B.JOB_PROCESS, ISNULL(B.JOB_ORDER, ''))";
                
                $dateWhereCondition = "(A.CDML_ENDDATE BETWEEN :dtpProduceFrom AND :dtpProduceTo)";
                $bindParams[':dtpProduceFrom'] = $dtpProduceFrom;
                $bindParams[':dtpProduceTo']   = $dtpProduceTo;
                $extraWhere = "";
                $orderBy = "ORDER BY A.CDML_ENDDATE ASC, A.CDML_ENDTIME ASC, A.COIL_NO ASC";
                break;

            case "DAILY SWISS SLITTER PRODUCTION REPORT":
                $dateField = "A.SWSL_ENDDATE";
                $timeField = "A.SWSL_ENDTIME";
                $tableJoin = "dbo.SWSLPROD1 AS A WITH (NOLOCK) LEFT JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO";
                $weightAccept = "CAST(ISNULL(A.SWSL_ACCEPTWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "CAST(ISNULL(A.SWSL_REJECTWEIGHT, 0) AS DECIMAL(12,2))";
                $operatorField = "ISNULL(A.SWSL_OPERATOR1, '')";
                $totalPassField = "0";
                $productField = "A.COIL_NO";
                $jobOrderExpression = "ISNULL(B.JOB_PROCESS, ISNULL(B.JOB_ORDER, ''))";
                
                $dateWhereCondition = "(A.SWSL_ENDDATE BETWEEN :dtpProduceFrom AND :dtpProduceTo)";
                $bindParams[':dtpProduceFrom'] = $dtpProduceFrom;
                $bindParams[':dtpProduceTo']   = $dtpProduceTo;
                $extraWhere = "";
                $orderBy = "ORDER BY A.SWSL_ENDDATE ASC, A.SWSL_ENDTIME ASC, A.COIL_NO ASC";
                break;

            case "DAILY BATCH ANNEALING PRODUCTION REPORT":
                $dateField = "A.BTCH_ENDDATE";
                $timeField = "A.BTCH_ENDTIME";
                $tableJoin = "dbo.BTCHPROD1 AS A WITH (NOLOCK) LEFT JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO";
                $weightAccept = "CAST(ISNULL(A.BTCH_ACCEPTWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "CAST(ISNULL(A.BTCH_REJECTWEIGHT, 0) AS DECIMAL(12,2))";
                $operatorField = "ISNULL(A.BTCH_OPERATOR1, '')";
                $totalPassField = "0";
                $productField = "A.PRODUCT_NO";
                
                $jobOrderExpression = "ISNULL(B.JOB_PROCESS, ISNULL(B.JOB_ORDER, ''))";
                $lineProcessExpression = "SUBSTRING(A.BTCH_NO, 9, 1)";
                
                $dateWhereCondition = "(A.BTCH_ENDDATE BETWEEN :dtpProduceFrom AND :dtpProduceTo)";
                $bindParams[':dtpProduceFrom'] = $dtpProduceFrom;
                $bindParams[':dtpProduceTo']   = $dtpProduceTo;
                $extraWhere = "AND B.PRODUCT_ID = 'CO'";
                $orderBy = "ORDER BY A.BTCH_ENDDATE ASC, A.BTCH_ENDTIME ASC, A.PRODUCT_NO ASC";
                break;

            case "DAILY PACKING PRODUCTION REPORT":
                $dateField = "A.PACK_DATE";
                $timeField = "A.PACK_DATE";
                $tableJoin = "dbo.PACKPROD1 AS A WITH (NOLOCK) LEFT JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO";
                $weightAccept = "CAST(ISNULL(A.PACK_NETWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "0.00";
                $operatorField = "ISNULL(A.PACK_OPERATOR1, '')";
                $totalPassField = "0";
                $productField = "A.PRODUCT_NO";
                $jobOrderExpression = "ISNULL(B.JOB_ORDER, ISNULL(B.JOB_PROCESS, ''))";
                
                $dateWhereCondition = "(A.PACK_DATE BETWEEN :dtpProduceFrom AND :dtpProduceTo)";
                $bindParams[':dtpProduceFrom'] = $dtpProduceFrom . ' 08:00:00';
                $bindParams[':dtpProduceTo']   = $dtpProduceTo . ' 07:59:59';
                $extraWhere = "AND A.PRODUCT_ID = 'CO'";
                $orderBy = "ORDER BY A.PACK_DATE ASC, A.PRODUCT_NO ASC";
                break;

            default: // DAILY CASTING PRODUCTION REPORT
                $dateField = "B.COIL_ENDDATE";
                $timeField = "B.COIL_ENDTIME";
                $tableJoin = "dbo.COILPROD1 AS B WITH (NOLOCK)";
                $weightAccept = "CAST(ISNULL(B.COIL_ACTUALWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "0.00";
                $operatorField = "ISNULL(B.COIL_OPERATOR1, '')";
                $totalPassField = "0";
                $productField = "B.COIL_NO";
                $jobOrderExpression = "ISNULL(B.JOB_ORDER, ISNULL(B.JOB_PROCESS, ''))";

                $dateWhereCondition = "(B.COIL_ENDDATE BETWEEN :dtpProduceFrom AND :dtpProduceTo)";
                $bindParams[':dtpProduceFrom'] = $dtpProduceFrom;
                $bindParams[':dtpProduceTo']   = $dtpProduceTo;
                
                $extraWhere = "AND (B.LINE_PROCESS = '1' OR B.LINE_PROCESS = '2' OR B.LINE_PROCESS = '3')";
                $orderBy = "ORDER BY B.COIL_ENDDATE ASC, B.COIL_ENDTIME ASC, B.COIL_NO ASC";
                break;
        }

        $sql = "
            SELECT 
                {$productField} AS product_no,
                ISNULL(B.COIL_STATUS, '') AS status_id,
                CONVERT(VARCHAR(10), {$dateField}, 101) AS produce_date,
                CONVERT(VARCHAR(10), {$dateField}, 120) AS produce_date_raw,
                CONVERT(VARCHAR(8), {$timeField}, 108) AS produce_time,
                ISNULL(B.SALEORDER_NO, '') AS saleorder_no,
                ISNULL(B.SALEORDER_ITEM, '') AS saleorder_item,
                {$jobOrderExpression} AS job_order,
                ISNULL(B.PRODUCT_REFERENCE, '') AS coil_ref,
                ISNULL(B.PRODUCT_REFERENCE, '') AS caster_coil,
                ISNULL(B.CSTMSPPL_ID, '') AS coil_from,
                ISNULL(B.ALLOY, '') AS alloy,
                ISNULL(B.F_TEMPER, ISNULL(B.TEMPER, '')) AS tp_org,
                ISNULL(B.TEMPER, '') AS tp_fnl,
                ISNULL(B.GRADE, '') AS grade,
                ISNULL(B.SURFACE_GRADE, '') AS surface_grade,
                ISNULL(B.METALLURGICAL_GRADE, '') AS meta_grade,
                ISNULL(B.T5_TEMPERATURE, '') AS t5_temp,
                CAST(ISNULL(B.THICKNESS, 0) AS DECIMAL(10,3)) AS thk_act,
                CAST(ISNULL(B.THICKNESS, 0) AS DECIMAL(10,3)) AS thk_spec,
                CAST(ISNULL(B.WIDTH, 0) AS DECIMAL(10,2)) AS width,
                ISNULL(B.COIL_UOMDIMENSION, 'mm') AS uom_dim,
                ISNULL(B.COIL_UOMWEIGHT, 'kg') AS uom_wt,
                {$totalPassField} AS total_pass,
                0 AS coil_slit,
                {$weightAccept} AS accept_weight,
                {$weightReject} AS reject_weight,
                ISNULL(B.COIL_WORKPROCESS, '') AS work_process,
                ISNULL(B.COIL_NEXTPROCESS, '') AS next_process,
                {$operatorField} AS operator,
                ISNULL(B.COIL_REMARK, '') AS remark,
                {$lineProcessExpression} AS line_process
            FROM {$tableJoin}
            WHERE {$dateWhereCondition} {$extraWhere}
            {$orderBy}
        ";

        $stmt = $dbConnection->prepare($sql);
        $stmt->execute($bindParams);
        $rawReportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $checkStartTS = strtotime($dtpProduceFrom . ' 08:00:00');
        $checkEndTS   = strtotime($dtpProduceTo . ' 08:00:00');

        foreach ($rawReportData as $row) {
            $timeStr = $row['produce_time'] ?: '00:00:00';
            $dateStr = $row['produce_date_raw'] ?: $dtpProduceFrom;
            $endDateTimeTS = strtotime($dateStr . ' ' . $timeStr);

            if ($cmbProcess !== 'DAILY PACKING PRODUCTION REPORT') {
                if ($endDateTimeTS <= $checkStartTS || $endDateTimeTS > $checkEndTS) {
                    continue; 
                }
            }

            $shift = 'X';
            $shiftPriority = 99;

            if ($timeStr >= '00:00:00' && $timeStr <= '08:00:00') {
                $shift = 'N';          // กะดึก (00:00:00 - 08:00:00)
                $shiftPriority = 3;
            } elseif ($timeStr <= '16:00:00') {
                $shift = 'M';          // กะเช้า (08:00:01 - 16:00:00)
                $shiftPriority = 2;
            } elseif ($timeStr <= '23:59:59') {
                $shift = 'E';          // กะบ่าย (16:00:01 - 23:59:59)
                $shiftPriority = 1;
            }

            $row['shift']          = $shift;
            $row['shift_priority'] = $shiftPriority;
            $reportData[]          = $row;
        }

        if ($cmbProcess === 'DAILY CASTING PRODUCTION REPORT' && !empty($reportData)) {
            usort($reportData, function($a, $b) {
                if ($a['produce_date_raw'] !== $b['produce_date_raw']) {
                    return strcmp($a['produce_date_raw'], $b['produce_date_raw']);
                }
                if ($a['shift_priority'] !== $b['shift_priority']) {
                    return $a['shift_priority'] <=> $b['shift_priority'];
                }
                return strcmp($a['product_no'], $b['product_no']);
            });
        }

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = "ไม่พบตัวแปรเชื่อมต่อฐานข้อมูลในไฟล์ dbcon_mats-new.php";
}

// 4. ส่วนส่งออกไฟล์ EXCEL เมื่อกดปุ่ม "Export Excel"
if (isset($_POST['export_excel'])) {
    $filename = str_replace(' ', '_', $cmbProcess) . "_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <table border="1">
        <thead>
            <tr>
                <th colspan="26" style="font-size: 16px; font-weight: bold; text-align: center;">MEYER ALUMINIUM (THAILAND) CO.,LTD.</th>
            </tr>
            <tr>
                <th colspan="26" style="font-size: 14px; font-weight: bold; text-align: center;"><?=$cmbProcess?></th>
            </tr>
            <tr>
                <th colspan="13" style="text-align: left;">PRINT DATE: <?=date('m/d/Y H:i')?></th>
                <th colspan="13" style="text-align: right;">PRINTING BY: <?=$iduser_func?></th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th rowspan="2">NO.</th>
                <th rowspan="2">SHIFT</th>
                <th rowspan="2">PRODUCT NO.</th>
                <th rowspan="2">LINE</th>
                <th rowspan="2">ST.</th>
                <th rowspan="2">S.O. NO.</th>
                <th rowspan="2">JOB ORDER</th>
                <th rowspan="2">COIL REF.</th>
                <th rowspan="2">A</th>
                <th colspan="2">TP</th>
                <th rowspan="2">G</th>
                <th rowspan="2">SG</th>
                <th rowspan="2">MG</th>
                <th colspan="2">THICKNESS</th>
                <th rowspan="2">W</th>
                <th rowspan="2">UOM DIM</th>
                <th rowspan="2">PASS</th>
                <th colspan="2">WEIGHT</th>
                <th rowspan="2">UOM WT.</th>
                <th rowspan="2">WORK PROCESS</th>
                <th rowspan="2">NEXT ST.</th>
                <th rowspan="2">OPERATOR</th>
                <th rowspan="2">REMARK</th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th>ORG</th>
                <th>FNL</th>
                <th>ACT.</th>
                <th>SPEC.</th>
                <th>ACCEPT</th>
                <th>REJECT</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($reportData)): ?>
            <?php
            $currentShift = null;
            $currentDate  = null;

            $shiftAcceptSum = 0; $shiftCoils = 0;
            $dateAcceptSum  = 0; $dateCoils  = 0;
            $grandAcceptSum = 0; $grandCoils = 0;
            $itemNo = 1;

            foreach ($reportData as $row):
                $shift = $row['shift'];
                $date  = $row['produce_date'];
                $acceptWeight = (float)$row['accept_weight'];

                if ($currentShift !== null && $currentShift !== $shift):
            ?>
                    <tr style="background-color: #f8fafc; font-weight: bold;">
                        <td colspan="19" style="text-align: left;">SUBTOTAL SHIFT &nbsp;&nbsp; <?=$currentShift?></td>
                        <td style="text-align: center;"><?=$shiftCoils?> COILS</td>
                        <td style="text-align: right; color: #1e3a8a;"><?=number_format($shiftAcceptSum)?></td>
                        <td colspan="5"></td>
                    </tr>
            <?php
                    if ($currentDate !== null && $currentDate !== $date):
            ?>
                        <tr style="background-color: #e2e8f0; font-weight: bold;">
                            <td colspan="19" style="text-align: left;">SUBTOTAL PRODUCE DATE &nbsp;&nbsp; <?=$currentDate?></td>
                            <td style="text-align: center;"><?=$dateCoils?> COILS</td>
                            <td style="text-align: right; color: #172554;"><?=number_format($dateAcceptSum)?></td>
                            <td colspan="5"></td>
                        </tr>
            <?php
                        $dateAcceptSum = 0; $dateCoils = 0;
                    endif;

                    $shiftAcceptSum = 0; $shiftCoils = 0;
                endif;

                $currentShift = $shift;
                $currentDate  = $date;
                $shiftAcceptSum += $acceptWeight; $shiftCoils++;
                $dateAcceptSum  += $acceptWeight; $dateCoils++;
                $grandAcceptSum += $acceptWeight; $grandCoils++;

                $soDisplay = $row['saleorder_no'];
                if (!empty($row['saleorder_item']) && $row['saleorder_item'] !== '0') {
                    $soDisplay .= '-' . $row['saleorder_item'];
                }
            ?>
                <tr>
                    <td style="text-align: center;"><?=$itemNo++?></td>
                    <td style="text-align: center; font-weight: bold;"><?=$row['shift']?></td>
                    <td style="font-weight: bold; color: #1d4ed8;"><?=$row['product_no']?></td>
                    <td style="text-align: center;"><?=$row['line_process']?></td>
                    <td style="text-align: center;"><?=$row['status_id']?></td>
                    <td style="text-align: center;"><?=$soDisplay ?: '-'?></td>
                    <td style="text-align: center;"><?=$row['job_order'] ?: '-'?></td>
                    <td style="text-align: center;"><?=$row['coil_ref']?></td>
                    <td style="text-align: center;"><?=$row['alloy']?></td>
                    <td style="text-align: center;"><?=$row['tp_org']?></td>
                    <td style="text-align: center;"><?=$row['tp_fnl']?></td>
                    <td style="text-align: center;"><?=$row['grade']?></td>
                    <td style="text-align: center;"><?=$row['surface_grade']?></td>
                    <td style="text-align: center;"><?=$row['meta_grade']?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['thk_act'], 3)?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['thk_spec'], 3)?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['width'], 2)?></td>
                    <td style="text-align: center;"><?=$row['uom_dim']?></td>
                    <td style="text-align: center;"><?=$row['total_pass']?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($acceptWeight)?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['reject_weight'])?></td>
                    <td style="text-align: center;"><?=$row['uom_wt']?></td>
                    <td><?=$row['work_process']?></td>
                    <td><?=$row['next_process']?></td>
                    <td><?=$row['operator']?></td>
                    <td><?=$row['remark']?></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($currentShift !== null): ?>
                <tr style="background-color: #f8fafc; font-weight: bold;">
                    <td colspan="19" style="text-align: left;">SUBTOTAL SHIFT &nbsp;&nbsp; <?=$currentShift?></td>
                    <td style="text-align: center;"><?=$shiftCoils?> COILS</td>
                    <td style="text-align: right; color: #1e3a8a;"><?=number_format($shiftAcceptSum)?></td>
                    <td colspan="5"></td>
                </tr>
            <?php endif; ?>

            <?php if ($currentDate !== null): ?>
                <tr style="background-color: #e2e8f0; font-weight: bold;">
                    <td colspan="19" style="text-align: left;">SUBTOTAL PRODUCE DATE &nbsp;&nbsp; <?=$currentDate?></td>
                    <td style="text-align: center;"><?=$dateCoils?> COILS</td>
                    <td style="text-align: right; color: #172554;"><?=number_format($dateAcceptSum)?></td>
                    <td colspan="5"></td>
                </tr>
            <?php endif; ?>

            <tr style="background-color: #cbd5e1; font-weight: bold; font-size: 15px;">
                <td colspan="19" style="text-align: left;">GRAND TOTAL</td>
                <td style="text-align: center;"><?=$grandCoils?> COILS</td>
                <td style="text-align: right; color: #0f172a;"><?=number_format($grandAcceptSum)?></td>
                <td colspan="5"></td>
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
    <title>Meyer Aluminium Thailand Co.,LTD. - Daily Coil Production Report</title>
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
            border: 1px solid #cbd5e1;
        }

        table.rpt-table thead tr:nth-child(1) th {
            position: sticky;
            top: 0;
            z-index: 20;
            background-color: #f1f5f9 !important;
            box-shadow: inset 0 -1px 0 #cbd5e1;
        }

        table.rpt-table thead tr:nth-child(2) th {
            position: sticky;
            top: 35px;
            z-index: 19;
            background-color: #f1f5f9 !important;
            box-shadow: inset 0 -1px 0 #cbd5e1;
        }

        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; font-size: 15px; background-color: #f8fafc; }
        .main-panel .content { padding: 15px 15px !important; }

        .input-group-custom { display: flex; flex-direction: column; gap: 6px; }
        .input-label-custom { font-size: 15px; font-weight: 800; color: #1e293b; }
        
/* ปรับขนาด Font ช่อง Process, วันที่ และช่วงเวลา ให้ใหญ่ขึ้นกว่าปกติ 0.5 เท่า (พอดีสายตา) */
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

        table.rpt-table { width: 100%; min-width: 2300px; border-collapse: collapse; font-size: 14px; color: #1e293b; }
        table.rpt-table th {
            background-color: #f1f5f9; color: #0f172a; font-weight: 700; padding: 8px 6px;
            border: 1px solid #cbd5e1; white-space: nowrap; text-align: center; font-size: 13px; vertical-align: middle;
        }
        table.rpt-table td { padding: 8px 8px; border: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
        table.rpt-table tbody tr:hover { background-color: #f8fafc; }

        .subtotal-row td {
            background-color: #ffffff !important; font-weight: bold; color: #0f172a; font-size: 14px;
            border-top: 1.5px solid #000000 !important; border-bottom: 1.5px solid #000000 !important;
        }
        .line-subtotal-row td {
            background-color: #ffffff !important; font-weight: bold; color: #0f172a; font-size: 14px;
            border-top: 1.5px solid #000000 !important; border-bottom: 1.5px solid #000000 !important;
        }
        .grandtotal-row td {
            background-color: #ffffff !important; font-weight: bold; color: #0f172a; font-size: 15px;
            border-top: 2px solid #000000 !important; border-bottom: 2px double #000000 !important;
        }

        @media print {
            .sidebar, .navbar, .footer, .no-print, nav, header, form { 
                display: none !important; 
            }

            .main-panel { 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                float: none !important; 
            }
            .content { 
                padding: 0 !important; 
                margin: 0 !important; 
            }
            body { 
                background: #fff !important; 
                color: #000 !important; 
                font-size: 8pt !important; 
            }

            @page { 
                size: A4 landscape; 
                margin: 5mm; 
            }
            .print-container { 
                width: 100% !important; 
                border: none !important; 
                padding: 0 !important; 
                margin: 0 !important;
            }
            .rpt-table-wrapper { 
                overflow: visible !important; 
                max-height: none !important; 
                border: none !important;
            }

            .rpt-header-screen { 
                display: flex !important; 
                justify-content: space-between !important; 
                align-items: flex-start !important; 
                margin-bottom: 10px !important; 
                border-bottom: 1.5pt solid #000 !important; 
                padding-bottom: 5px !important; 
            }
            .rpt-header-screen h1 { 
                font-size: 14pt !important; 
                font-weight: bold !important; 
                color: #000 !important; 
                margin: 0 !important;
            }
            .rpt-header-screen h2 { 
                font-size: 12pt !important; 
                font-weight: bold !important; 
                color: #000 !important; 
                margin: 2px 0 0 0 !important;
            }
            .rpt-meta-screen { 
                font-size: 9pt !important; 
                font-weight: bold !important; 
                color: #000 !important; 
                line-height: 1.3 !important;
            }

            table.rpt-table { 
                width: 100% !important; 
                min-width: 0 !important; 
                font-size: 6.5pt !important; 
                border-collapse: collapse !important;
            }
            thead { 
                display: table-header-group !important; 
            }
            tbody { 
                display: table-row-group !important; 
            }
            tr { 
                page-break-inside: avoid !important; 
            }
            
            table.rpt-table th { 
                background-color: #f1f5f9 !important; 
                color: #000 !important;
                border: 0.5pt solid #000 !important; 
                padding: 3px 2px !important; 
                font-size: 6.5pt !important; 
                font-weight: bold !important;
                text-align: center !important;
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
            table.rpt-table td { 
                border: 0.5pt solid #000 !important; 
                padding: 2px 2px !important; 
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">✏️ Daily Coil Production Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                
                <!-- INPUT FORM PANEL (แบ่งครึ่ง 50/50 Font ใหญ่ 2 เท่า คมชัด ชัดเจน) -->
                <div class="bg-white rounded-xl shadow-md border border-slate-200 p-5 mb-4 no-print">
                    <form method="POST" action="" id="reportForm" class="flex flex-col gap-4">
                        
<!-- INPUT FORM PANEL (แบ่งครึ่ง 50/50 Font ใหญ่กว่าเดิม 0.5 เท่า กำลังพอดี) -->
<div class="bg-white rounded-xl shadow-md border border-slate-200 p-4 mb-4 no-print">
    <form method="POST" action="" id="reportForm" class="flex flex-col gap-3">
        
        <!-- บรรทัดที่ 1: แบ่งครึ่งสมดุล 50 / 50 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
            
            <!-- Process Selection (ฝั่งซ้าย 50%) -->
            <div class="input-group-custom">
                <label class="input-label-custom">Process</label>
                <select name="cmbProcess" onchange="this.form.submit()" class="input-field-custom w-full cursor-pointer shadow-sm">
                    <option value="DAILY CASTING PRODUCTION REPORT" <?=$cmbProcess == 'DAILY CASTING PRODUCTION REPORT' ? 'selected' : ''?>>DAILY CASTING PRODUCTION REPORT</option>
                    <option value="DAILY COLD MILL PRODUCTION REPORT" <?=$cmbProcess == 'DAILY COLD MILL PRODUCTION REPORT' ? 'selected' : ''?>>DAILY COLD MILL PRODUCTION REPORT</option>
                    <option value="DAILY SWISS SLITTER PRODUCTION REPORT" <?=$cmbProcess == 'DAILY SWISS SLITTER PRODUCTION REPORT' ? 'selected' : ''?>>DAILY SWISS SLITTER PRODUCTION REPORT</option>
                    <option value="DAILY BATCH ANNEALING PRODUCTION REPORT" <?=$cmbProcess == 'DAILY BATCH ANNEALING PRODUCTION REPORT' ? 'selected' : ''?>>DAILY BATCH ANNEALING PRODUCTION REPORT</option>
                    <option value="DAILY PACKING PRODUCTION REPORT" <?=$cmbProcess == 'DAILY PACKING PRODUCTION REPORT' ? 'selected' : ''?>>DAILY PACKING PRODUCTION REPORT</option>
                </select>
            </div>

            <!-- Produce Date From Selection (ฝั่งขวา 50%) -->
            <div class="input-group-custom">
                <label class="input-label-custom">Produce Date From</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="dtpProduceFrom" id="dtpProduceFrom" value="<?=$dtpProduceFrom?>" class="input-field-custom w-1/2">
<span class="text-2xl sm:text-3xl font-black text-blue-900 bg-blue-100/90 px-4 py-2.5 rounded-xl whitespace-nowrap border-2 border-blue-400 shadow-sm w-1/2 text-center shrink-0">
    - 8:00 To <span id="lblDateTo" class="text-blue-700 underline underline-offset-4 decoration-blue-500"><?=date('m/d/Y', strtotime($dtpProduceTo))?></span> - 8:00
</span>
                </div>
            </div>

        </div>

        <!-- บรรทัดที่ 2: กลุ่มปุ่มคำสั่งหลัก -->
        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-start gap-2">
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

                <!-- REPORT CONTAINER -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 print-container">
                    
                    <div class="rpt-header-screen flex justify-between items-start">
                        <div>
                            <h1>MEYER ALUMINIUM (THAILAND) CO.,LTD.</h1>
                            <h2><?=$cmbProcess?></h2>
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
                                    <th rowspan="2" style="width: 50px;">SHIFT</th>
                                    <th rowspan="2" style="width: 120px;">PRODUCT NO.</th>
                                    <th rowspan="2" style="width: 45px;">LINE</th>
                                    <th rowspan="2" style="width: 45px;">ST.</th>
                                    <th rowspan="2" style="width: 110px;">S.O. NO.</th>
                                    <th rowspan="2" style="width: 100px;">JOB ORDER</th>
                                    <th rowspan="2" style="width: 100px;">COIL REF.</th>
                                    <th rowspan="2" style="width: 50px;">A</th>
                                    <th colspan="2" style="width: 100px;">TP</th>
                                    <th rowspan="2" style="width: 45px;">G</th>
                                    <th rowspan="2" style="width: 45px;">SG</th>
                                    <th rowspan="2" style="width: 45px;">MG</th>
                                    <th colspan="2" style="width: 150px;">THICKNESS</th>
                                    <th rowspan="2" style="width: 80px;">W</th>
                                    <th rowspan="2" style="width: 70px;">UOM DIM</th>
                                    <th rowspan="2" style="width: 50px;">PASS</th>
                                    <th colspan="2" style="width: 200px;">WEIGHT</th>
                                    <th rowspan="2" style="width: 65px;">UOM WT.</th>
                                    <th rowspan="2" style="width: 130px;">WORK PROCESS</th>
                                    <th rowspan="2" style="width: 70px;">NEXT ST.</th>
                                    <th rowspan="2" style="width: 110px;">OPERATOR</th>
                                    <th rowspan="2">REMARK</th>
                                </tr>
                                <tr>
                                    <th style="width: 50px;">ORG</th>
                                    <th style="width: 50px;">FNL</th>
                                    <th style="width: 75px;">ACT.</th>
                                    <th style="width: 75px;">SPEC.</th>
                                    <th style="width: 100px;">ACCEPT</th>
                                    <th style="width: 100px;">REJECT</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($errorMessage): ?>
                                <tr>
                                    <td colspan="26" class="text-center py-8 text-red-600 font-bold">
                                        ❌ เกิดข้อผิดพลาดในการดึงข้อมูล: <?=htmlspecialchars($errorMessage)?>
                                    </td>
                                </tr>
                            <?php elseif (empty($reportData)): ?>
                                <tr>
                                    <td colspan="26" class="text-center py-8 text-slate-500 font-semibold">
                                        🚫 ไม่พบข้อมูลรายงานตามเงื่อนไขที่ระบุ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $currentShift = null;
                                $currentDate  = null;

                                $shiftAcceptSum = 0; $shiftCoils = 0;
                                $dateAcceptSum  = 0; $dateCoils  = 0;
                                $grandAcceptSum = 0; $grandCoils = 0;
                                $itemNo = 1;

                                foreach ($reportData as $row):
                                    $shift = $row['shift'];
                                    $date  = $row['produce_date'];
                                    $acceptWeight = (float)$row['accept_weight'];

                                    if ($currentShift !== null && $currentShift !== $shift):
                                ?>
                                        <tr class="subtotal-row">
                                            <td colspan="19" style="text-align: left;">SUBTOTAL SHIFT &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentShift?></td>
                                            <td class="text-center font-bold"><?=$shiftCoils?> COILS</td>
                                            <td class="text-right font-bold"><?=number_format($shiftAcceptSum)?></td>
                                            <td colspan="5"></td>
                                        </tr>
                                <?php
                                        if ($currentDate !== null && $currentDate !== $date):
                                ?>
                                            <tr class="line-subtotal-row">
                                                <td colspan="19" style="text-align: left;">SUBTOTAL PRODUCE DATE &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentDate?></td>
                                                <td class="text-center font-bold"><?=$dateCoils?> COILS</td>
                                                <td class="text-right font-bold"><?=number_format($dateAcceptSum)?></td>
                                                <td colspan="5"></td>
                                            </tr>
                                <?php
                                            $dateAcceptSum = 0; $dateCoils = 0;
                                        endif;

                                        $shiftAcceptSum = 0; $shiftCoils = 0;
                                    endif;

                                    $currentShift = $shift;
                                    $currentDate  = $date;
                                    $shiftAcceptSum += $acceptWeight; $shiftCoils++;
                                    $dateAcceptSum  += $acceptWeight; $dateCoils++;
                                    $grandAcceptSum += $acceptWeight; $grandCoils++;

                                    $soDisplay = $row['saleorder_no'];
                                    if (!empty($row['saleorder_item']) && $row['saleorder_item'] !== '0') {
                                        $soDisplay .= '-' . $row['saleorder_item'];
                                    }
                                ?>
                                    <tr>
                                        <td class="text-center font-semibold"><?=$itemNo++?></td>
                                        <td class="text-center font-bold"><?=$row['shift']?></td>
                                        <td class="font-bold text-blue-700"><?=htmlspecialchars($row['product_no'])?></td>
                                        <td class="text-center"><?=$row['line_process']?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['status_id'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($soDisplay ?: '-')?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['job_order'] ?: '-')?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['coil_ref'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['alloy'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['tp_org'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['tp_fnl'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['grade'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['surface_grade'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['meta_grade'])?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['thk_act'], 3)?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['thk_spec'], 3)?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['width'], 2)?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['uom_dim'])?></td>
                                        <td class="text-center"><?=$row['total_pass']?></td>
                                        <td class="text-right font-bold text-slate-900"><?=number_format($acceptWeight)?></td>
                                        <td class="text-right"><?=number_format((float)$row['reject_weight'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['uom_wt'])?></td>
                                        <td><?=htmlspecialchars($row['work_process'])?></td>
                                        <td><?=htmlspecialchars($row['next_process'])?></td>
                                        <td><?=htmlspecialchars($row['operator'])?></td>
                                        <td><?=htmlspecialchars($row['remark'])?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if ($currentShift !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="19" style="text-align: left;">SUBTOTAL SHIFT &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentShift?></td>
                                        <td class="text-center font-bold"><?=$shiftCoils?> COILS</td>
                                        <td class="text-right font-bold"><?=number_format($shiftAcceptSum)?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($currentDate !== null): ?>
                                    <tr class="line-subtotal-row">
                                        <td colspan="19" style="text-left font-bold">SUBTOTAL PRODUCE DATE &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentDate?></td>
                                        <td style="text-align: center;"><?=$dateCoils?> COILS</td>
                                        <td style="text-align: right; color: #172554;"><?=number_format($dateAcceptSum)?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <tr class="grandtotal-row">
                                    <td colspan="19" style="text-left font-bold">GRAND TOTAL</td>
                                    <td style="text-align: center;"><?=$grandCoils?> COILS</td>
                                    <td style="text-align: right; color: #0f172a;"><?=number_format($grandAcceptSum)?></td>
                                    <td colspan="5"></td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dtpProduceFrom = document.getElementById('dtpProduceFrom');
    const lblDateTo = document.getElementById('lblDateTo');

    dtpProduceFrom.addEventListener('change', function() {
        if (this.value) {
            let selectedDate = new Date(this.value);
            selectedDate.setDate(selectedDate.getDate() + 1);
            
            let month = String(selectedDate.getMonth() + 1).padStart(2, '0');
            let day = String(selectedDate.getDate()).padStart(2, '0');
            let year = selectedDate.getFullYear();
            
            lblDateTo.textContent = `${month}/${day}/${year}`;
        }
    });
});
</script>
</body>
<?php include 'include/footer.php';?>
</html>