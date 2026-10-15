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

// 2. ดึงข้อมูลตัวเลือกสำหรับ Dropdown (ALLOY, TEMPER, GRADE)
$alloyOptions  = [];
$temperOptions = [];
$gradeOptions  = [];

if ($dbConnection) {
    try {
        $stmtAlloy = $dbConnection->query("SELECT ALLOY FROM dbo.CMPSMSTR1 WITH (NOLOCK) GROUP BY ALLOY ORDER BY ALLOY ASC");
        if ($stmtAlloy) $alloyOptions = $stmtAlloy->fetchAll(PDO::FETCH_COLUMN);

        $stmtTemper = $dbConnection->query("SELECT TEMPER FROM dbo.TMPRMSTR1 WITH (NOLOCK) ORDER BY TEMPER ASC");
        if ($stmtTemper) $temperOptions = $stmtTemper->fetchAll(PDO::FETCH_COLUMN);

        $stmtGrade = $dbConnection->query("SELECT GRADE FROM dbo.GRDEMSTR1 WITH (NOLOCK) ORDER BY GRADE ASC");
        if ($stmtGrade) $gradeOptions = $stmtGrade->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}

// 3. รับค่า Filter จากฟอร์มแบบ POST
$dtpProduceFrom = !isset($_POST['dtpProduceFrom']) ? date('Y-m-d') : htmlspecialchars($_POST['dtpProduceFrom'], ENT_QUOTES, 'UTF-8');
$dtpProduceTo   = !isset($_POST['dtpProduceTo'])   ? date('Y-m-d') : htmlspecialchars($_POST['dtpProduceTo'], ENT_QUOTES, 'UTF-8');
$txtProductFrom = !isset($_POST['txtProductFrom']) ? '0' : htmlspecialchars($_POST['txtProductFrom'], ENT_QUOTES, 'UTF-8');
$txtProductTo   = !isset($_POST['txtProductTo'])   ? 'Z' : htmlspecialchars($_POST['txtProductTo'], ENT_QUOTES, 'UTF-8');
$txtJobOrder    = !isset($_POST['txtJobOrder'])    ? 'ALL' : htmlspecialchars($_POST['txtJobOrder'], ENT_QUOTES, 'UTF-8');

$cmbProcess     = !isset($_POST['cmbProcess'])     ? 'CASTING PRODUCTION REPORT' : htmlspecialchars($_POST['cmbProcess'], ENT_QUOTES, 'UTF-8');
$cmbMaterialIn  = !isset($_POST['cmbMaterialIn'])  ? 'ALL' : htmlspecialchars($_POST['cmbMaterialIn'], ENT_QUOTES, 'UTF-8');
$cmbLineProcess = !isset($_POST['cmbLineProcess']) ? 'ALL' : htmlspecialchars($_POST['cmbLineProcess'], ENT_QUOTES, 'UTF-8');
$cmbAlloy       = !isset($_POST['cmbAlloy'])       ? 'ALL' : htmlspecialchars($_POST['cmbAlloy'], ENT_QUOTES, 'UTF-8');
$cmbTemper      = !isset($_POST['cmbTemper'])      ? 'ALL' : htmlspecialchars($_POST['cmbTemper'], ENT_QUOTES, 'UTF-8');
$cmbGrade       = !isset($_POST['cmbGrade'])       ? 'ALL' : htmlspecialchars($_POST['cmbGrade'], ENT_QUOTES, 'UTF-8');
$txtThickness   = !isset($_POST['txtThickness'])   ? '0.000' : htmlspecialchars($_POST['txtThickness'], ENT_QUOTES, 'UTF-8');
$txtWidth       = !isset($_POST['txtWidth'])       ? '0.00' : htmlspecialchars($_POST['txtWidth'], ENT_QUOTES, 'UTF-8');
$cmbStatus      = !isset($_POST['cmbStatus'])      ? 'ALL' : htmlspecialchars($_POST['cmbStatus'], ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

// 4. ดึงข้อมูลรายงานตามประเภท Process ที่เลือก
$reportData = [];
$errorMessage = '';

if ($dbConnection) {
    try {
        $dtpProduceToNextDay = date('Y-m-d', strtotime($dtpProduceTo . ' +1 day'));

        $bindParams = [
            ':dtpProduceFrom'     => $dtpProduceFrom . ' 00:00:00',
            ':dtpProduceToNextDay' => $dtpProduceToNextDay . ' 00:00:00',
            ':txtProductFrom'     => $txtProductFrom,
            ':txtProductTo'       => $txtProductTo
        ];

        switch ($cmbProcess) {
            case "BATCH ANNEALING PRODUCTION REPORT":
                $dateField = "A.BTCH_ENDDATE";
                $tableJoin = "dbo.BTCHPROD1 AS A WITH (NOLOCK) INNER JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO";
                $weightAccept = "CAST(A.BTCH_ACCEPTWEIGHT AS DECIMAL(12,2))";
                $weightReject = "CAST(A.BTCH_REJECTWEIGHT AS DECIMAL(12,2))";
                $extraWhere = "AND A.PRODUCT_ID = 'CO'";
                $productField = "A.PRODUCT_NO";
                break;

            case "PACKING PRODUCTION REPORT":
                $dateField = "A.PACK_DATE";
                $tableJoin = "dbo.PACKPROD1 AS A WITH (NOLOCK) INNER JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.PRODUCT_NO = B.COIL_NO";
                $weightAccept = "CAST(ISNULL(A.PACK_NETWEIGHT, 0) AS DECIMAL(12,2))";
                $weightReject = "0.00";
                $extraWhere = "AND A.PRODUCT_ID = 'CO'";
                $productField = "A.PRODUCT_NO";
                break;

            case "SWISS SLITTER PRODUCTION REPORT":
                $dateField = "A.SWSL_ENDDATE";
                $tableJoin = "dbo.SWSLPROD1 AS A WITH (NOLOCK) INNER JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO";
                $weightAccept = "CAST(A.SWSL_ACCEPTWEIGHT AS DECIMAL(12,2))";
                $weightReject = "CAST(A.SWSL_REJECTWEIGHT AS DECIMAL(12,2))";
                $extraWhere = "";
                $productField = "A.COIL_NO";
                break;

            case "COLD MILL PRODUCTION REPORT":
                $dateField = "A.CDML_ENDDATE";
                $tableJoin = "dbo.CDMLPROD1 AS A WITH (NOLOCK) INNER JOIN dbo.COILPROD1 AS B WITH (NOLOCK) ON A.COIL_NO = B.COIL_NO";
                $weightAccept = "CAST(A.CDML_ACCEPTWEIGHT AS DECIMAL(12,2))";
                $weightReject = "CAST(A.CDML_REJECTWEIGHT AS DECIMAL(12,2))";
                $extraWhere = "";
                $productField = "A.COIL_NO";
                break;

            default: // CASTING PRODUCTION REPORT
                $dateField = "B.COIL_ENDDATE";
                $tableJoin = "dbo.COILPROD1 AS B WITH (NOLOCK)";
                $weightAccept = "CAST(B.COIL_FIRSTWEIGHT AS DECIMAL(12,2))";
                $weightReject = "0.00";
                $extraWhere = "";
                $productField = "B.COIL_NO";
                if ($cmbLineProcess === 'ALL') {
                    $extraWhere .= " AND (B.LINE_PROCESS BETWEEN '1' AND '2')";
                }
                break;
        }

        $conditions = [
            "({$dateField} >= :dtpProduceFrom AND {$dateField} < :dtpProduceToNextDay)",
            "({$productField} BETWEEN :txtProductFrom AND :txtProductTo)"
        ];

        if ($txtJobOrder !== 'ALL' && $txtJobOrder !== '') { $conditions[] = "(B.JOB_PROCESS = :txtJobOrder)"; $bindParams[':txtJobOrder'] = $txtJobOrder; }
        if ($cmbMaterialIn !== 'ALL') { $conditions[] = "(B.MATERIAL_IN = :cmbMaterialIn)"; $bindParams[':cmbMaterialIn'] = $cmbMaterialIn; }
        if ($cmbLineProcess !== 'ALL') { $conditions[] = "(B.LINE_PROCESS = :cmbLineProcess)"; $bindParams[':cmbLineProcess'] = $cmbLineProcess; }
        if ($cmbAlloy !== 'ALL') { $conditions[] = "(B.ALLOY = :cmbAlloy)"; $bindParams[':cmbAlloy'] = $cmbAlloy; }
        if ($cmbTemper !== 'ALL') { $conditions[] = "(B.TEMPER = :cmbTemper)"; $bindParams[':cmbTemper'] = $cmbTemper; }
        if ($cmbGrade !== 'ALL') { $conditions[] = "(B.GRADE = :cmbGrade)"; $bindParams[':cmbGrade'] = $cmbGrade; }
        if ($cmbStatus !== 'ALL') { $conditions[] = "(B.COIL_STATUS = :cmbStatus)"; $bindParams[':cmbStatus'] = $cmbStatus; }
        
        if ((float)$txtThickness > 0) {
            $conditions[] = "(B.THICKNESS = :txtThickness)";
            $bindParams[':txtThickness'] = (float)$txtThickness;
        }
        if ((float)$txtWidth > 0) {
            $conditions[] = "(B.WIDTH = :txtWidth)";
            $bindParams[':txtWidth'] = (float)$txtWidth;
        }

        $whereClause = implode(" AND ", $conditions);

        $thkField = ($cmbProcess === 'CASTING PRODUCTION REPORT') ? "B.F_THICKNESS" : "B.THICKNESS";
        $jobJoinField = ($cmbProcess === 'CASTING PRODUCTION REPORT') ? "B.JOB_ORDER" : "B.JOB_PROCESS";

        $sql = "
            SELECT 
                {$productField} AS product_no,
                B.COIL_STATUS AS status_id,
                CONVERT(VARCHAR(10), {$dateField}, 101) AS produce_date,
                B.SALEORDER_NO AS saleorder_no,
                B.JOB_PROCESS AS job_order,
                ISNULL(B.PRODUCT_REFERENCE, '') AS coil_ref,
                ISNULL(B.PRODUCT_REFERENCE, '') AS caster_coil,
                ISNULL(B.CSTMSPPL_ID, '') AS coil_from,
                B.ALLOY AS alloy,
                B.F_TEMPER AS tp_org,
                B.TEMPER AS tp_fnl,
                B.GRADE AS grade,
                B.SURFACE_GRADE AS surface_grade,
                B.METALLURGICAL_GRADE AS meta_grade,
                ISNULL(B.T5_TEMPERATURE, '') AS t5_temp,
                CAST({$thkField} AS DECIMAL(10,3)) AS thk_act,
                CAST({$thkField} AS DECIMAL(10,3)) AS thk_spec,
                CAST(B.WIDTH AS DECIMAL(10,2)) AS width,
                ISNULL(B.CURRENT_PASS, 0) AS current_pass,
                0 AS coil_slit,
                {$weightAccept} AS accept_weight,
                {$weightReject} AS reject_weight,
                ISNULL(B.COIL_WORKPROCESS, '') AS work_process,
                ISNULL(B.COIL_NEXTPROCESS, '') AS next_process,
                ISNULL(C.TEMPER, '') AS batch_result,
                ISNULL(B.COIL_REMARK, '') AS remark,
                B.LINE_PROCESS AS line_process
            FROM {$tableJoin}
            LEFT OUTER JOIN dbo.JOBORDER1 AS C WITH (NOLOCK) ON {$jobJoinField} = C.JOB_ORDER
            WHERE {$whereClause} {$extraWhere}
            ORDER BY CONVERT(VARCHAR(10), {$dateField}, 120) ASC, B.LINE_PROCESS ASC, {$productField} ASC
        ";

        $stmt = $dbConnection->prepare($sql);
        $stmt->execute($bindParams);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
} else {
    $errorMessage = "ไม่พบตัวแปรเชื่อมต่อฐานข้อมูลในไฟล์ dbcon_mats-new.php";
}

// 5. ส่วนส่งออกไฟล์ EXCEL เมื่อกดปุ่ม "Export Excel"
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
                <th colspan="28" style="font-size: 16px; font-weight: bold; text-align: center;">MEYER ALUMINIUM (THAILAND) CO.,LTD.</th>
            </tr>
            <tr>
                <th colspan="28" style="font-size: 14px; font-weight: bold; text-align: center;"><?=$cmbProcess?></th>
            </tr>
            <tr>
                <th colspan="14" style="text-align: left;">PRINT DATE: <?=date('m/d/Y H:i')?></th>
                <th colspan="14" style="text-align: right;">PRINTING BY: <?=$iduser_func?></th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th rowspan="2">NO.</th>
                <th rowspan="2">PRODUCT NO.</th>
                <th rowspan="2">ST.</th>
                <th rowspan="2">PRODUCE DATE</th>
                <th rowspan="2">SALE ORDER NO.</th>
                <th rowspan="2">JOB ORDER</th>
                <th rowspan="2">COIL REF.</th>
                <th rowspan="2">CASTER COIL</th>
                <th rowspan="2">COIL FROM</th>
                <th rowspan="2">A</th>
                <th colspan="2">TP</th>
                <th rowspan="2">G</th>
                <th rowspan="2">SG</th>
                <th rowspan="2">MG</th>
                <th rowspan="2">T5 TEMP.</th>
                <th colspan="2">THICKNESS</th>
                <th rowspan="2">W</th>
                <th rowspan="2">CRRNT PASS</th>
                <th rowspan="2">COIL SLIT</th>
                <th colspan="2">WEIGHT</th>
                <th rowspan="2">WORK PROCESS</th>
                <th rowspan="2">NEXT ST.</th>
                <th colspan="2">BATCH</th>
                <th rowspan="2">REMARK</th>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <th>ORG</th>
                <th>FNL</th>
                <th>ACT.</th>
                <th>SPEC.</th>
                <th>ACCEPT</th>
                <th>REJECT</th>
                <th>ST.</th>
                <th>RESULT</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($reportData)): ?>
            <?php
            $currentLine = null;
            $currentDate = null;
            
            $dateAcceptSum = 0;
            $dateRejectSum = 0;
            $dateCoilCount = 0;
            
            $lineAcceptSum = 0;
            $lineRejectSum = 0;
            
            $grandAcceptSum = 0;
            $grandRejectSum = 0;
            $grandCoilCount = count($reportData);
            
            $itemNo = 1;

            foreach ($reportData as $row):
                $line = $row['line_process'];
                $date = $row['produce_date'];
                $acceptWeight = (float)$row['accept_weight'];
                $rejectWeight = (float)$row['reject_weight'];

                $lineChanged = ($currentLine !== null && $currentLine !== $line);
                $dateChanged = ($currentDate !== null && $currentDate !== $date);

                if ($lineChanged || $dateChanged):
            ?>
                    <tr style="background-color: #e2e8f0; font-weight: bold; font-size: 15px;">
                        <td colspan="21" style="text-align: left;">SUBTOTAL BY LINE PROCESS &nbsp;&nbsp; <?=$currentLine?></td>
                        <td style="text-align: right; color: #172554;"><?=number_format($lineAcceptSum, 0)?></td>
                        <td style="text-align: right; color: #7f1d1d;"><?=$lineRejectSum > 0 ? number_format($lineRejectSum, 0) : ''?></td>
                        <td colspan="5"></td>
                    </tr>
            <?php
                    $lineAcceptSum = 0;
                    $lineRejectSum = 0;
                endif;

                if ($dateChanged):
            ?>
                    <tr style="background-color: #f8fafc; font-weight: bold; font-size: 15px;">
                        <td colspan="20" style="text-align: left;">SUBTOTAL BY PRODUCE DATE &nbsp;&nbsp; <?=$currentDate?></td>
                        <td style="text-align: center;"><?=$dateCoilCount?> COILS</td>
                        <td style="text-align: right; color: #1e3a8a;"><?=number_format($dateAcceptSum, 0)?></td>
                        <td style="text-align: right; color: #7f1d1d;"><?=$dateRejectSum > 0 ? number_format($dateRejectSum, 0) : ''?></td>
                        <td colspan="5"></td>
                    </tr>
            <?php
                    $dateAcceptSum = 0;
                    $dateRejectSum = 0;
                    $dateCoilCount = 0;
                endif;

                $currentLine = $line;
                $currentDate = $date;

                $dateAcceptSum += $acceptWeight;
                $dateRejectSum += $rejectWeight;
                $dateCoilCount++;
                
                $lineAcceptSum += $acceptWeight;
                $lineRejectSum += $rejectWeight;
                
                $grandAcceptSum += $acceptWeight;
                $grandRejectSum += $rejectWeight;
            ?>
                <tr>
                    <td style="text-align: center;"><?=$itemNo++?></td>
                    <td style="font-weight: bold; color: #1d4ed8;"><?=$row['product_no']?></td>
                    <td style="text-align: center;"><?=$row['status_id']?></td>
                    <td style="text-align: center;"><?=$row['produce_date']?></td>
                    <td style="text-align: center;"><?=$row['saleorder_no'] ?: '-'?></td>
                    <td style="text-align: center;"><?=$row['job_order'] ?: '-'?></td>
                    <td style="text-align: center;"><?=$row['coil_ref']?></td>
                    <td style="text-align: center;"><?=$row['caster_coil']?></td>
                    <td style="text-align: center;"><?=$row['coil_from']?></td>
                    <td style="text-align: center;"><?=$row['alloy']?></td>
                    <td style="text-align: center;"><?=$row['tp_org']?></td>
                    <td style="text-align: center;"><?=$row['tp_fnl']?></td>
                    <td style="text-align: center;"><?=$row['grade']?></td>
                    <td style="text-align: center;"><?=$row['surface_grade']?></td>
                    <td style="text-align: center;"><?=$row['meta_grade']?></td>
                    <td style="text-align: center;"><?=$row['t5_temp']?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['thk_act'], 3)?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['thk_spec'], 3)?></td>
                    <td style="text-align: right;"><?=number_format((float)$row['width'], 2)?></td>
                    <td style="text-align: center;"><?=$row['current_pass']?></td>
                    <td style="text-align: center;"><?=$row['coil_slit'] ?: ''?></td>
                    <td style="text-align: right; font-weight: bold;"><?=number_format($acceptWeight, 0)?></td>
                    <td style="text-align: right;"><?=$rejectWeight > 0 ? number_format($rejectWeight, 0) : ''?></td>
                    <td><?=$row['work_process']?></td>
                    <td><?=$row['next_process']?></td>
                    <td><?=$row['batch_result']?></td>
                    <td><?=$row['batch_result']?></td>
                    <td><?=$row['remark']?></td>
                </tr>
            <?php endforeach; ?>

            <?php if ($currentLine !== null): ?>
                <tr style="background-color: #e2e8f0; font-weight: bold; font-size: 15px;">
                    <td colspan="21" style="text-align: left;">SUBTOTAL BY LINE PROCESS &nbsp;&nbsp; <?=$currentLine?></td>
                    <td style="text-align: right; color: #172554;"><?=number_format($lineAcceptSum, 0)?></td>
                    <td style="text-align: right; color: #7f1d1d;"><?=$lineRejectSum > 0 ? number_format($lineRejectSum, 0) : ''?></td>
                    <td colspan="5"></td>
                </tr>
            <?php endif; ?>

            <?php if ($currentDate !== null): ?>
                <tr style="background-color: #f8fafc; font-weight: bold; font-size: 15px;">
                    <td colspan="20" style="text-align: left;">SUBTOTAL BY PRODUCE DATE &nbsp;&nbsp; <?=$currentDate?></td>
                    <td style="text-align: center;"><?=$dateCoilCount?> COILS</td>
                    <td style="text-align: right; color: #1e3a8a;"><?=number_format($dateAcceptSum, 0)?></td>
                    <td style="text-align: right; color: #7f1d1d;"><?=$dateRejectSum > 0 ? number_format($dateRejectSum, 0) : ''?></td>
                    <td colspan="5"></td>
                </tr>
            <?php endif; ?>

            <!-- GRAND TOTAL ขนาดฟอนต์เท่ากับ SUBTOTAL BY LINE PROCESS (15px) -->
            <tr style="background-color: #cbd5e1; font-weight: bold; font-size: 15px;">
                <td colspan="20" style="text-align: left;">GRAND TOTAL</td>
                <td style="text-align: center;"><?=$grandCoilCount?> COILS</td>
                <td style="text-align: right; color: #172554;"><?=number_format($grandAcceptSum, 0)?></td>
                <td style="text-align: right; color: #7f1d1d;"><?=$grandRejectSum > 0 ? number_format($grandRejectSum, 0) : ''?></td>
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
    <title>Meyer Aluminium Thailand Co.,LTD. - Coil Production Report</title>
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

        .filter-section-title {
            font-size: 15px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px;
            margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #cbd5e1;
        }

        .input-group-custom { display: flex; flex-direction: column; gap: 4px; }
        .input-label-custom { font-size: 13px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 4px; }
        .input-field-custom {
            font-size: 14px; font-weight: 500; color: #0f172a; background-color: #ffffff;
            border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; height: 42px;
            transition: all 0.2s ease;
        }
        .input-field-custom:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }

        .btn-action-lg {
            height: 48px; padding: 0 20px; font-size: 15px; font-weight: 700; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            transition: all 0.2s ease; cursor: pointer;
        }

        .rpt-header-screen { margin-bottom: 15px; border-bottom: 2px solid #cbd5e1; padding-bottom: 12px; }
        .rpt-header-screen h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin: 0; }
        .rpt-header-screen h2 { font-size: 17px; font-weight: 700; color: #2563eb; margin: 4px 0 0 0; }
        .rpt-meta-screen { font-size: 14px !important; font-weight: 700 !important; color: #1e293b !important; line-height: 1.5; }

        table.rpt-table { width: 100%; min-width: 2200px; border-collapse: collapse; font-size: 14px; color: #1e293b; }
        table.rpt-table th {
            background-color: #f1f5f9; color: #0f172a; font-weight: 700; padding: 8px 6px;
            border: 1px solid #cbd5e1; white-space: nowrap; text-align: center; font-size: 13px; vertical-align: middle;
        }
        table.rpt-table td { padding: 8px 8px; border: 1px solid #e2e8f0; white-space: nowrap; vertical-align: middle; }
        table.rpt-table tbody tr:hover { background-color: #f8fafc; }

        /* ปรับฟอนต์แถบสรุปยอดทั้งหมด (PRODUCE DATE, LINE PROCESS, GRAND TOTAL) ให้เท่ากันที่ 15px Bold */
        .subtotal-row td, .line-subtotal-row td, .grandtotal-row td {
            font-weight: bold !important; color: #0f172a; font-size: 15px !important;
            border-top: 2px solid #64748b !important; border-bottom: 2px solid #64748b !important;
        }
        .subtotal-row td { background-color: #f8fafc !important; }
        .line-subtotal-row td { background-color: #e2e8f0 !important; border-top: 2px solid #1e293b !important; border-bottom: 2px solid #1e293b !important; }
        .grandtotal-row td { background-color: #cbd5e1 !important; border-top: 2px solid #0f172a !important; border-bottom: 2px solid #0f172a !important; }

/* สไตล์ พิมพ์ / PDF ปรับขนาดคอลัมน์และป้องกันขอบขวาหาย */
@media print {
    .sidebar, .navbar, .footer, .no-print, nav, header, form, button { 
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
        font-size: 7.5pt !important; 
    }

    @page { 
        size: A4 landscape; 
        margin: 4mm 3mm; 
    }

    .print-container { 
        width: 100% !important; 
        border: none !important; 
        padding: 0 !important; 
        margin: 0 !important;
    }
    
    .rpt-table-wrapper { 
        width: 100% !important;
        max-height: none !important; 
        overflow: visible !important; 
        border: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    table.rpt-table {
        width: 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
        font-size: 5.2pt !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
    }

    table.rpt-table thead {
        display: table-header-group !important;
    }

    table.rpt-table tbody {
        display: table-row-group !important;
    }

    table.rpt-table thead tr th {
        position: static !important;
        background-color: #f1f5f9 !important;
        border: 0.5pt solid #000 !important;
        padding: 1px 0.5px !important;
        font-size: 5.2pt !important;
        word-wrap: break-word !important;
        overflow: hidden !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    table.rpt-table td {
        border: 0.5pt solid #444 !important;
        padding: 1px 0.5px !important;
        word-wrap: break-word !important;
        overflow: hidden !important;
    }

    .subtotal-row td, .line-subtotal-row td, .grandtotal-row td {
        font-size: 5.8pt !important;
        font-weight: bold !important;
    }

    tr { 
        page-break-inside: avoid !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">✏️ Coil Production Report</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                
                <!-- INPUT FORM PANEL -->
                <div class="bg-white rounded-xl shadow-md border border-slate-200 p-5 mb-5 no-print">
                    <form method="POST" action="">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                            
                            <!-- CARD 1 -->
                            <div class="lg:col-span-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <div class="filter-section-title text-blue-700">
                                    <span>📊</span> 1. ประเภทรายงาน & ช่วงเวลา
                                </div>
                                <div class="space-y-3">
                                    <div class="input-group-custom">
                                        <label class="input-label-custom">Process Report</label>
                                        <select name="cmbProcess" class="input-field-custom font-bold text-blue-700 bg-blue-50/50 border-blue-300">
                                            <option value="CASTING PRODUCTION REPORT" <?=$cmbProcess == 'CASTING PRODUCTION REPORT' ? 'selected' : ''?>>CASTING PRODUCTION REPORT</option>
                                            <option value="COLD MILL PRODUCTION REPORT" <?=$cmbProcess == 'COLD MILL PRODUCTION REPORT' ? 'selected' : ''?>>COLD MILL PRODUCTION REPORT</option>
                                            <option value="SWISS SLITTER PRODUCTION REPORT" <?=$cmbProcess == 'SWISS SLITTER PRODUCTION REPORT' ? 'selected' : ''?>>SWISS SLITTER PRODUCTION REPORT</option>
                                            <option value="BATCH ANNEALING PRODUCTION REPORT" <?=$cmbProcess == 'BATCH ANNEALING PRODUCTION REPORT' ? 'selected' : ''?>>BATCH ANNEALING PRODUCTION REPORT</option>
                                            <option value="PACKING PRODUCTION REPORT" <?=$cmbProcess == 'PACKING PRODUCTION REPORT' ? 'selected' : ''?>>PACKING PRODUCTION REPORT</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Produce From</label>
                                            <input type="date" name="dtpProduceFrom" value="<?=$dtpProduceFrom?>" class="input-field-custom">
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Produce To</label>
                                            <input type="date" name="dtpProduceTo" value="<?=$dtpProduceTo?>" class="input-field-custom">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2 -->
                            <div class="lg:col-span-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <div class="filter-section-title text-slate-700">
                                    <span>📦</span> 2. รหัสสินค้า & การผลิต
                                </div>
                                <div class="space-y-3">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Product From</label>
                                            <input type="text" name="txtProductFrom" value="<?=$txtProductFrom?>" class="input-field-custom uppercase font-semibold">
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Product To</label>
                                            <input type="text" name="txtProductTo" value="<?=$txtProductTo?>" class="input-field-custom uppercase font-semibold">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Job Order</label>
                                            <input type="text" name="txtJobOrder" value="<?=$txtJobOrder?>" class="input-field-custom uppercase">
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Line Process</label>
                                            <select name="cmbLineProcess" class="input-field-custom">
                                                <option value="ALL" <?=$cmbLineProcess == 'ALL' ? 'selected' : ''?>>ALL</option>
                                                <option value="1" <?=$cmbLineProcess == '1' ? 'selected' : ''?>>1</option>
                                                <option value="2" <?=$cmbLineProcess == '2' ? 'selected' : ''?>>2</option>
                                            </select>
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Material In</label>
                                            <select name="cmbMaterialIn" class="input-field-custom font-medium">
                                                <option value="ALL" <?=$cmbMaterialIn == 'ALL' ? 'selected' : ''?>>ALL</option>
                                                <option value="BOI-MAT1" <?=$cmbMaterialIn == 'BOI-MAT1' ? 'selected' : ''?>>BOI-MAT1</option>
                                                <option value="BOI-MAT2" <?=$cmbMaterialIn == 'BOI-MAT2' ? 'selected' : ''?>>BOI-MAT2</option>
                                                <option value="BOI-GRS1" <?=$cmbMaterialIn == 'BOI-GRS1' ? 'selected' : ''?>>BOI-GRS1</option>
                                                <option value="BOI-IMP1" <?=$cmbMaterialIn == 'BOI-IMP1' ? 'selected' : ''?>>BOI-IMP1</option>
                                                <option value="BOI-IMP2" <?=$cmbMaterialIn == 'BOI-IMP2' ? 'selected' : ''?>>BOI-IMP2</option>
                                                <option value="NONE-BOI" <?=$cmbMaterialIn == 'NONE-BOI' ? 'selected' : ''?>>NONE-BOI</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 3 -->
                            <div class="lg:col-span-4 bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <div class="filter-section-title text-slate-700">
                                    <span>⚙️</span> 3. สเปกโลหะ & สถานะ
                                </div>
                                <div class="space-y-3">
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Alloy</label>
                                            <select name="cmbAlloy" class="input-field-custom">
                                                <option value="ALL" <?=$cmbAlloy == 'ALL' ? 'selected' : ''?>>ALL</option>
                                                <?php foreach ($alloyOptions as $alloy): ?>
                                                    <option value="<?=$alloy?>" <?=$cmbAlloy == $alloy ? 'selected' : ''?>><?=$alloy?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Temper</label>
                                            <select name="cmbTemper" class="input-field-custom">
                                                <option value="ALL" <?=$cmbTemper == 'ALL' ? 'selected' : ''?>>ALL</option>
                                                <?php foreach ($temperOptions as $temper): ?>
                                                    <option value="<?=$temper?>" <?=$cmbTemper == $temper ? 'selected' : ''?>><?=$temper?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Grade</label>
                                            <select name="cmbGrade" class="input-field-custom">
                                                <option value="ALL" <?=$cmbGrade == 'ALL' ? 'selected' : ''?>>ALL</option>
                                                <?php foreach ($gradeOptions as $grade): ?>
                                                    <option value="<?=$grade?>" <?=$cmbGrade == $grade ? 'selected' : ''?>><?=$grade?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Thickness</label>
                                            <input type="number" step="0.001" min="0" name="txtThickness" value="<?=$txtThickness?>" placeholder="0.000" class="input-field-custom">
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Width</label>
                                            <input type="number" step="0.01" min="0" name="txtWidth" value="<?=$txtWidth?>" placeholder="0.00" class="input-field-custom">
                                        </div>
                                        <div class="input-group-custom">
                                            <label class="input-label-custom">Status</label>
                                            <select name="cmbStatus" class="input-field-custom">
                                                <?php 
                                                $statusList = ["ALL", "OP", "AC", "CB", "CL", "SR", "PK", "TR", "ST", "DS", "IN", "SH", "RJ", "RM", "MT", "NF", "CM", "SS"];
                                                foreach ($statusList as $st): 
                                                ?>
                                                    <option value="<?=$st?>" <?=$cmbStatus == $st ? 'selected' : ''?>><?=$st?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- ACTION BAR -->
                        <div class="flex flex-col sm:flex-row justify-between items-center pt-4 border-t border-slate-200 gap-4 mt-3">
                            <div></div>

                            <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                                <button type="submit" class="btn-action-lg bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg">
                                    🔍 ค้นหาข้อมูล
                                </button>
                                <a href="<?=$_SERVER['PHP_SELF']?>" class="btn-action-lg bg-slate-200 hover:bg-slate-300 text-slate-700 shadow-sm border border-slate-300">
                                    🔄 ล้างค่า
                                </a>                                
                                <button type="submit" name="export_excel" value="1" class="btn-action-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-md hover:shadow-lg">
                                    📊 Export Excel
                                </button>
                                <button type="button" onclick="window.print()" class="btn-action-lg bg-slate-800 hover:bg-slate-900 text-white shadow-md hover:shadow-lg">
                                    🖨️ พิมพ์
                                </button>
                            </div>
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
                            <colgroup>
                                <col style="width: 2.2%;">
                                <col style="width: 5.8%;">
                                <col style="width: 2.2%;">
                                <col style="width: 5.0%;">
                                <col style="width: 5.5%;">
                                <col style="width: 4.8%;">
                                <col style="width: 4.8%;">
                                <col style="width: 4.8%;">
                                <col style="width: 3.2%;">
                                <col style="width: 2.8%;">
                                <col style="width: 2.8%;">
                                <col style="width: 2.8%;">
                                <col style="width: 2.4%;">
                                <col style="width: 2.4%;">
                                <col style="width: 2.4%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.2%;">
                                <col style="width: 3.2%;">
                                <col style="width: 5.2%;">
                                <col style="width: 5.2%;">
                                <col style="width: 6.0%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.8%;">
                                <col style="width: 3.8%;">
                                <col style="width: 7.5%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th rowspan="2">NO.</th>
                                    <th rowspan="2">PRODUCT NO.</th>
                                    <th rowspan="2">ST.</th>
                                    <th rowspan="2">PRODUCE DATE</th>
                                    <th rowspan="2">SALE ORDER NO.</th>
                                    <th rowspan="2">JOB ORDER</th>
                                    <th rowspan="2">COIL REF.</th>
                                    <th rowspan="2">CASTER COIL</th>
                                    <th rowspan="2">COIL FROM</th>
                                    <th rowspan="2">A</th>
                                    <th colspan="2">TP</th>
                                    <th rowspan="2">G</th>
                                    <th rowspan="2">SG</th>
                                    <th rowspan="2">MG</th>
                                    <th rowspan="2">T5 TEMP.</th>
                                    <th colspan="2">THICKNESS</th>
                                    <th rowspan="2">W</th>
                                    <th rowspan="2">CRRNT PASS</th>
                                    <th rowspan="2">COIL SLIT</th>
                                    <th colspan="2">WEIGHT</th>
                                    <th rowspan="2">WORK PROCESS</th>
                                    <th rowspan="2">NEXT ST.</th>
                                    <th colspan="2">BATCH</th>
                                    <th rowspan="2">REMARK</th>
                                </tr>
                                <tr>
                                    <th>ORG</th>
                                    <th>FNL</th>
                                    <th>ACT.</th>
                                    <th>SPEC.</th>
                                    <th>ACCEPT</th>
                                    <th>REJECT</th>
                                    <th>ST.</th>
                                    <th>RESULT</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($errorMessage): ?>
                                <tr>
                                    <td colspan="28" class="text-center py-8 text-red-600 font-bold">
                                        ❌ เกิดข้อผิดพลาดในการดึงข้อมูล: <?=htmlspecialchars($errorMessage)?>
                                    </td>
                                </tr>
                            <?php elseif (empty($reportData)): ?>
                                <tr>
                                    <td colspan="28" class="text-center py-8 text-slate-500 font-semibold">
                                        🚫 ไม่พบข้อมูลรายงานตามเงื่อนไขที่ระบุ
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php
                                $currentLine = null;
                                $currentDate = null;
                                
                                $dateAcceptSum = 0;
                                $dateRejectSum = 0;
                                $dateCoilCount = 0;
                                
                                $lineAcceptSum = 0;
                                $lineRejectSum = 0;
                                
                                $grandAcceptSum = 0;
                                $grandRejectSum = 0;
                                $grandCoilCount = count($reportData);
                                
                                $itemNo = 1;

                                foreach ($reportData as $index => $row):
                                    $line = $row['line_process'];
                                    $date = $row['produce_date'];
                                    $acceptWeight = (float)$row['accept_weight'];
                                    $rejectWeight = (float)$row['reject_weight'];

                                    $lineChanged = ($currentLine !== null && $currentLine !== $line);
                                    $dateChanged = ($currentDate !== null && $currentDate !== $date);

                                    // สรุปยอดกลุ่ม LINE PROCESS
                                    if ($lineChanged || $dateChanged):
                                ?>
                                        <tr class="line-subtotal-row">
                                            <td colspan="21" class="text-left font-bold">SUBTOTAL BY LINE PROCESS &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentLine?></td>
                                            <td class="text-right font-bold text-blue-950"><?=number_format($lineAcceptSum, 0)?></td>
                                            <td class="text-right font-bold text-red-950"><?=$lineRejectSum > 0 ? number_format($lineRejectSum, 0) : ''?></td>
                                            <td colspan="5"></td>
                                        </tr>
                                <?php
                                        $lineAcceptSum = 0;
                                        $lineRejectSum = 0;
                                    endif;

                                    // สรุปยอดกลุ่ม PRODUCE DATE
                                    if ($dateChanged):
                                ?>
                                        <tr class="subtotal-row">
                                            <td colspan="20" class="text-left font-bold">SUBTOTAL BY PRODUCE DATE &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentDate?></td>
                                            <td class="text-center font-bold"><?=$dateCoilCount?> COILS</td>
                                            <td class="text-right font-bold text-blue-900"><?=number_format($dateAcceptSum, 0)?></td>
                                            <td class="text-right font-bold text-red-900"><?=$dateRejectSum > 0 ? number_format($dateRejectSum, 0) : ''?></td>
                                            <td colspan="5"></td>
                                        </tr>
                                <?php
                                        $dateAcceptSum = 0;
                                        $dateRejectSum = 0;
                                        $dateCoilCount = 0;
                                    endif;

                                    $currentLine = $line;
                                    $currentDate = $date;

                                    $dateAcceptSum += $acceptWeight;
                                    $dateRejectSum += $rejectWeight;
                                    $dateCoilCount++;
                                    
                                    $lineAcceptSum += $acceptWeight;
                                    $lineRejectSum += $rejectWeight;
                                    
                                    $grandAcceptSum += $acceptWeight;
                                    $grandRejectSum += $rejectWeight;
                                ?>
                                    <tr>
                                        <td class="text-center font-semibold"><?=$itemNo++?></td>
                                        <td class="font-bold text-blue-700"><?=htmlspecialchars($row['product_no'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['status_id'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['produce_date'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['saleorder_no'] ?: '-')?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['job_order'] ?: '-')?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['coil_ref'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['caster_coil'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['coil_from'])?></td>
                                        <td class="text-center font-medium"><?=htmlspecialchars($row['alloy'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['tp_org'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['tp_fnl'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['grade'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['surface_grade'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['meta_grade'])?></td>
                                        <td class="text-center"><?=htmlspecialchars($row['t5_temp'])?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['thk_act'], 3)?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['thk_spec'], 3)?></td>
                                        <td class="text-right font-medium"><?=number_format((float)$row['width'], 2)?></td>
                                        <td class="text-center"><?=$row['current_pass']?></td>
                                        <td class="text-center"><?=$row['coil_slit'] ?: ''?></td>
                                        <td class="text-right font-bold text-slate-900"><?=number_format($acceptWeight, 0)?></td>
                                        <td class="text-right"><?=$rejectWeight > 0 ? number_format($rejectWeight, 0) : ''?></td>
                                        <td><?=htmlspecialchars($row['work_process'])?></td>
                                        <td><?=htmlspecialchars($row['next_process'])?></td>
                                        <td><?=htmlspecialchars($row['batch_result'])?></td>
                                        <td><?=htmlspecialchars($row['batch_result'])?></td>
                                        <td><?=htmlspecialchars($row['remark'])?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- สรุปยอดกลุ่มสุดท้าย -->
                                <?php if ($currentLine !== null): ?>
                                    <tr class="line-subtotal-row">
                                        <td colspan="21" class="text-left font-bold">SUBTOTAL BY LINE PROCESS &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentLine?></td>
                                        <td class="text-right font-bold text-blue-950"><?=number_format($lineAcceptSum, 0)?></td>
                                        <td class="text-right font-bold text-red-950"><?=$lineRejectSum > 0 ? number_format($lineRejectSum, 0) : ''?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($currentDate !== null): ?>
                                    <tr class="subtotal-row">
                                        <td colspan="20" class="text-left font-bold">SUBTOTAL BY PRODUCE DATE &nbsp;&nbsp;&nbsp;&nbsp; <?=$currentDate?></td>
                                        <td class="text-center font-bold"><?=$dateCoilCount?> COILS</td>
                                        <td class="text-right font-bold text-blue-900"><?=number_format($dateAcceptSum, 0)?></td>
                                        <td class="text-right font-bold text-red-900"><?=$dateRejectSum > 0 ? number_format($dateRejectSum, 0) : ''?></td>
                                        <td colspan="5"></td>
                                    </tr>
                                <?php endif; ?>

                                <!-- GRAND TOTAL ขนาดฟอนต์เท่ากับ SUBTOTAL BY LINE PROCESS (15px) -->
                                <tr class="grandtotal-row">
                                    <td colspan="20" class="text-left font-bold uppercase">GRAND TOTAL</td>
                                    <td class="text-center font-bold"><?=$grandCoilCount?> COILS</td>
                                    <td class="text-right font-bold text-blue-950"><?=number_format($grandAcceptSum, 0)?></td>
                                    <td class="text-right font-bold text-red-950"><?=$grandRejectSum > 0 ? number_format($grandRejectSum, 0) : ''?></td>
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
</body>
<?php include 'include/footer.php';?>
</html>