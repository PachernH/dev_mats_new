<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

include("dbcon_mats-new.php");

$job_no = isset($_GET['jobno']) ? trim($_GET['jobno']) : '';

if (empty($job_no)) {
    die("Job Order Number is required.");
}

// 1. ดึงข้อมูลหลักจาก JOBORDER1
$sql_job = "SELECT j.*, RTRIM(j.PRODUCT_ID) AS PID
            FROM JOBORDER1 j
            WHERE j.JOB_ORDER = :job_no";
$stmt_job = $conn->prepare($sql_job);
$stmt_job->execute([':job_no' => $job_no]);
$job = $stmt_job->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job Order '{$job_no}' not found.");
}

$prod_names = [
    'CC' => 'CIRCLE',
    'CO' => 'COIL',
    'SH' => 'SHEET',
    'NC' => 'CIRCLE',
    'WR' => 'WIRE',
    'RM' => 'REMELT'
];
$pid = $job['PID'] ?? '';
$pname = $prod_names[$pid] ?? $pid;
$product_desc = !empty($pname) ? htmlspecialchars("{$pid}: {$pname}") : htmlspecialchars($pid);

// 2. ดึงข้อมูลจาก CSTMORDR2, CSTMORDR1, CSSPMSTR1
$so_spec = [];
if (!empty($job['SALEORDER_NO']) && !empty($job['SALEORDER_ITEM'])) {
    $sql_so = "SELECT a.*, b.SALEORDER_DATE, c.CONSIGNEE_COMPANY
               FROM CSTMORDR2 a
               LEFT JOIN CSTMORDR1 b ON a.SALEORDER_NO = b.SALEORDER_NO
               LEFT JOIN CSSPMSTR1 c ON a.CSTMSPPL_ID = c.CSTMSPPL_ID
               WHERE a.SALEORDER_NO = :so AND a.SALEORDER_ITEM = :item";
    $stmt_so = $conn->prepare($sql_so);
    $stmt_so->execute([
        ':so'   => $job['SALEORDER_NO'],
        ':item' => $job['SALEORDER_ITEM']
    ]);
    $so_spec = $stmt_so->fetch(PDO::FETCH_ASSOC) ?: [];
}

// 3. ดึงรายการ Coil Reserve จาก COILRSRV1
$sql_rsrv = "SELECT COIL_NO, ALLOY, TEMPER, THICKNESS, WIDTH, COIL_ACTUALWEIGHT
             FROM COILRSRV1
             WHERE JOB_ORDER = :job_no";
$stmt_rsrv = $conn->prepare($sql_rsrv);
$stmt_rsrv->execute([':job_no' => $job_no]);
$reserved_coils = $stmt_rsrv->fetchAll(PDO::FETCH_ASSOC) ?: [];

// แปลงค่าสำหรับแสดงผลในแบบฟอร์ม
$job_order_no   = htmlspecialchars($job['JOB_ORDER'] ?? '');
$schedule_date  = !empty($job['SCHEDULE_DATE']) ? date('d/m/Y', strtotime($job['SCHEDULE_DATE'])) : '';
$work_process   = htmlspecialchars(trim($job['JOB_WORKPROCESS'] ?? ''));
$job_weight     = number_format((float)($job['JOB_RELEASEWEIGHT'] ?? 0), 0);
$job_piece      = number_format((float)($job['JOB_RELEASEPIECE'] ?? 0), 0);
$job_max_tol    = htmlspecialchars(trim((string)($job['JOB_MAXTOLERANCE'] ?? '')));
$job_min_tol    = htmlspecialchars(trim((string)($job['JOB_MINTOLERANCE'] ?? '')));

$alloy          = htmlspecialchars(trim($job['ALLOY'] ?? ''));
$temper         = htmlspecialchars(trim($job['TEMPER'] ?? ''));
$grade          = htmlspecialchars(trim($job['GRADE'] ?? ''));
$sg             = htmlspecialchars(trim($job['SURFACE_GRADE'] ?? ''));
$mg             = htmlspecialchars(trim($job['METALLURGICAL_GRADE'] ?? ''));
$thickness      = number_format((float)($job['THICKNESS'] ?? 0), 3);
$width          = number_format((float)($job['WIDTH'] ?? 0), 3);
$length         = number_format((float)($job['LENGTH'] ?? 0), 3);
$weight_piece   = number_format((float)($job['WEIGHT_PIECE'] ?? 0), 4);

$cut_width      = (float)($job['CUT_WIDTH'] ?? 0) > 0 ? number_format((float)$job['CUT_WIDTH'], 0) : '';
$cut_length     = (float)($job['CUT_LENGTH'] ?? 0) > 0 ? number_format((float)$job['CUT_LENGTH'], 0) : '';
$cut_dim_str    = ($cut_width && $cut_length) ? "{$cut_width} x {$cut_length}" : ($cut_width ? "W: {$cut_width}" : '');
$cut_weight_pc  = (float)($job['CUT_WEIGHTPIECE'] ?? 0) > 0 ? number_format((float)$job['CUT_WEIGHTPIECE'], 4) : '';

$so_no_item     = htmlspecialchars(trim($job['SALEORDER_NO'] ?? ''));
if (!empty($job['SALEORDER_ITEM'])) {
    $so_no_item .= ' : ' . htmlspecialchars(trim($job['SALEORDER_ITEM']));
}
$so_delivery    = !empty($so_spec['CONFIRM_DATE']) ? date('d/m/Y', strtotime($so_spec['CONFIRM_DATE'])) : (!empty($so_spec['REQUEST_DATE']) ? date('d/m/Y', strtotime($so_spec['REQUEST_DATE'])) : '');
$po_no          = htmlspecialchars(trim(($so_spec['CTM2_PONO'] ?? '') . (!empty($so_spec['CTM2_POITEM']) ? ' - ' . $so_spec['CTM2_POITEM'] : '')));
$so_order_wt    = !empty($so_spec['CTM2_ORDERWEIGHT']) ? number_format((float)$so_spec['CTM2_ORDERWEIGHT'], 0) : '';
$so_order_pc    = !empty($so_spec['CTM2_ORDERPIECE']) ? number_format((float)$so_spec['CTM2_ORDERPIECE'], 0) : '';
$so_max_tol     = !empty($so_spec['CTM2_MAXTOLERANCE']) ? htmlspecialchars((string)$so_spec['CTM2_MAXTOLERANCE']) : '';
$so_min_tol     = !empty($so_spec['CTM2_MINTOLERANCE']) ? htmlspecialchars((string)$so_spec['CTM2_MINTOLERANCE']) : '';

$cust_id        = htmlspecialchars(trim($so_spec['CSTMSPPL_ID'] ?? ''));
$cust_name      = htmlspecialchars(trim($so_spec['CONSIGNEE_COMPANY'] ?? ''));
$material_in    = htmlspecialchars(trim($job['MATERIAL_IN'] ?? ''));

$raw_remark     = $job['JOB_REMARK'] ?? '';
$rmk1           = htmlspecialchars(trim(substr($raw_remark, 0, 65)));
$rmk2           = htmlspecialchars(trim(substr($raw_remark, 65, 65)));
$rmk3           = htmlspecialchars(trim(substr($raw_remark, 130, 65)));
$remark_lines   = array_filter([$rmk1, $rmk2, $rmk3]);
$remark_display = implode(' / ', $remark_lines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JOB ORDER - <?php echo $job_order_no; ?></title>
    <!-- Code 39 Barcode Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">

    <style>
        @page {
            size: A4 landscape;
            margin: 4mm 6mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #525659;
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
        }

        .page-sheet {
            width: 285mm;
            height: 198mm;
            margin: 6px auto;
            background-color: #fff;
            padding: 3mm 4mm 2mm 4mm;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            box-shadow: 0 4px 14px rgba(0,0,0,0.35);
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .header-title-left {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .header-subtitle-left {
            font-size: 11.5px;
            font-weight: 800;
            margin-top: 1px;
        }
        .header-info-right {
            text-align: right;
            font-size: 9.5px;
            font-weight: 700;
            line-height: 1.35;
        }

        table.tbl-unified-top {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.5px solid #000 !important;
            margin-bottom: 3px;
        }

        table.tbl-unified-top td, table.tbl-unified-top th {
            border: 1px solid #000 !important;
            padding: 1.5px 3px;
            font-size: 9.5px;
            height: 19.5px;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .th-title {
            background-color: #f2f2f2;
            font-weight: 800;
            text-align: center;
            font-size: 9px;
            text-transform: uppercase;
        }

        .td-val {
            font-weight: 700;
            text-align: center;
            font-size: 9.5px;
        }

        .td-left { text-align: left !important; }
        .td-right { text-align: right !important; }

        .td-routing-box {
            background-color: #fff !important;
            border-left: 1.5px solid #000 !important;
            border-right: 1.5px solid #000 !important;
            border-top: none !important;
            border-bottom: none !important;
            padding: 4px 6px 3px 6px !important;
            vertical-align: top !important;
            height: 100%;
        }

        .routing-wrapper {
            height: 100%;
            min-height: 155px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        .routing-stamp-title {
            font-size: 8.5px;
            font-weight: 800;
            color: #222;
            text-align: center;
            letter-spacing: 0.3px;
            padding-top: 1px;
        }

        .routing-stamp-space {
            flex: 1;
            width: 100%;
            min-height: 55px;
        }

        .barcode-container {
            width: 100%;
            text-align: center;
            margin-top: auto;
            margin-bottom: 0px;
            white-space: nowrap;
            overflow: hidden;
        }

        .barcode-text {
            font-family: 'Libre Barcode 39 Text', cursive;
            font-size: 36px;
            line-height: 1;
            letter-spacing: 1px;
            margin: 0;
            padding: 0;
            white-space: nowrap;
            display: inline-block;
        }

        .stamp-footer-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #666;
            text-align: center;
            margin-top: 2px;
            letter-spacing: 0.5px;
        }

        table.tracking-table-unified {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.5px solid #000 !important;
            flex: 1;
        }

        table.tracking-table-unified th, table.tracking-table-unified td {
            border: 1px solid #000 !important;
            padding: 2px 2px;
            font-size: 9px;
            height: 23px;
            text-align: center;
            vertical-align: middle;
        }

        table.tracking-table-unified th {
            background-color: #f2f2f2;
            font-weight: 800;
            font-size: 9px;
            height: 20px;
        }

        .border-split-right {
            border-right: 2px solid #000 !important;
        }

        .footer-note {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            font-weight: 700;
            margin-top: 2px;
            padding: 0 2px;
        }

        @media print {
            body { 
                background-color: #fff !important; 
                overflow: hidden !important; 
            }
            .page-sheet { 
                width: 100% !important;
                height: 100% !important;
                max-width: 100% !important;
                max-height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .td-routing-box {
                border-right: 1.5px solid #000 !important;
            }
        }
    </style>
</head>
<body>

<div class="page-sheet">
    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="header-title-left">MEYER ALUMINIUM (THAILAND) CO.,LTD.</div>
                <div class="header-subtitle-left">JOB ORDER</div>
            </td>
            <td class="header-info-right" style="vertical-align: top;">
                <div>PRINT DATE : <?php echo date('d/m/Y'); ?> &nbsp;&nbsp;&nbsp;&nbsp; Page 1 of 1</div>
                <div>PRINTING BY : <?php echo htmlspecialchars($iduser_func); ?> &nbsp;&nbsp;&nbsp;&nbsp; TIME : <?php echo date('H:i'); ?></div>
            </td>
        </tr>
    </table>

    <!-- Upper Main Form Frame -->
    <table class="tbl-unified-top">
        <colgroup>
            <!-- สเปกซ้าย 10 คอลัมน์ (รวม 74%) -->
            <col style="width: 10.5%;">
            <col style="width: 7.0%;">
            <col style="width: 7.0%;">
            <col style="width: 9.0%;">
            <col style="width: 5.0%;">
            <col style="width: 5.0%;">
            <col style="width: 6.5%;">
            <col style="width: 8.0%;">
            <col style="width: 8.0%;">
            <col style="width: 8.0%;">
            <!-- ขวาสุด 26% -->
            <col style="width: 26.0%;">
        </colgroup>
        <tbody>
            <!-- Row 1: Job Order Header 1 -->
            <tr>
                <td class="th-title">JOB ORDER</td>
                <td class="th-title" colspan="2">SCHEDULE</td>
                <td class="th-title" colspan="2">PRODUCT</td>
                <td class="th-title" colspan="2">WORK PROCESS</td>
                <td class="th-title">JOB WEIGHT</td>
                <td class="th-title">PIECE</td>
                <td class="th-title">MAX / MIN</td>
                <!-- ปรับเป็น rowspan="9" ครอบคลุมครบทั้ง 9 แถว ไม่ให้มีเส้นแนวนอนตัดผ่าน -->
                <td rowspan="9" class="td-routing-box">
                    <div class="routing-wrapper">
                        <div class="routing-stamp-title">PRODUCT LAYOUT / ROUTING STAMP</div>
                        <div class="routing-stamp-space"></div>
                        <div class="barcode-container">
                            <div class="barcode-text"><?php echo "*{$job_order_no}*"; ?></div>
                        </div>
                        <div class="stamp-footer-label">QA / PRODUCTION STAMP</div>
                    </div>
                </td>
            </tr>
            <!-- Row 2: Job Order Header 2 -->
            <tr>
                <td class="td-val" style="font-size: 11px; font-weight:800;"><?php echo $job_order_no; ?></td>
                <td class="td-val" colspan="2"><?php echo $schedule_date; ?></td>
                <td class="td-val" colspan="2"><?php echo $product_desc; ?></td>
                <td class="td-val td-left" colspan="2" style="font-size: 9.5px;"><?php echo $work_process; ?></td>
                <td class="td-val td-right"><?php echo $job_weight; ?></td>
                <td class="td-val td-right"><?php echo $job_piece; ?></td>
                <td class="td-val"><?php echo ($job_max_tol ? $job_max_tol.'%' : '-') . ' / ' . ($job_min_tol ? $job_min_tol.'%' : '-'); ?></td>
            </tr>

            <!-- Row 3: Produce Specification 1 -->
            <tr>
                <td rowspan="2" class="th-title" style="font-size: 9px; line-height: 1.2;">PRODUCE<br>SPECIFICATION</td>
                <td class="th-title">ALLOY</td>
                <td class="th-title">TEMPER</td>
                <td class="th-title">GRADE</td>
                <td class="th-title">SG</td>
                <td class="th-title">MG</td>
                <td class="th-title">TH.</td>
                <td class="th-title">WIDTH</td>
                <td class="th-title">LENGTH</td>
                <td class="th-title">WEIGHT/PIECE</td>
            </tr>
            <!-- Row 4: Produce Specification 2 -->
            <tr>
                <td class="td-val"><?php echo $alloy; ?></td>
                <td class="td-val"><?php echo $temper; ?></td>
                <td class="td-val"><?php echo $grade; ?></td>
                <td class="td-val"><?php echo $sg; ?></td>
                <td class="td-val"><?php echo $mg; ?></td>
                <td class="td-val"><?php echo $thickness; ?></td>
                <td class="td-val"><?php echo $width; ?></td>
                <td class="td-val"><?php echo $length; ?></td>
                <td class="td-val td-right"><?php echo $weight_piece; ?></td>
            </tr>
            <!-- Row 5: Sheet Size -->
            <tr>
                <td colspan="4" class="th-title" style="text-align: right; padding-right: 6px;">SHEET SIZE FOR CIRCLE SHEAR OR STRETCHER :</td>
                <td colspan="2" class="td-val"><?php echo $cut_dim_str; ?></td>
                <td colspan="3" class="th-title" style="text-align: right; padding-right: 6px;">ESTIMATE CUT SHEET WT. :</td>
                <td class="td-val td-right"><?php echo $cut_weight_pc; ?></td>
            </tr>

            <!-- Row 6: Sale Order Specification 1 -->
            <tr>
                <td class="th-title">SALE ORDER NO.</td>
                <td class="th-title" colspan="2">DELIVERY</td>
                <td class="th-title" colspan="3">P.O. NO.</td>
                <td class="th-title">ORDER WEIGHT</td>
                <td class="th-title">PIECE</td>
                <td class="th-title" colspan="2">MAX / MIN</td>
            </tr>
            <!-- Row 7: Sale Order Specification 2 -->
            <tr>
                <td class="td-val" style="font-weight: 800;"><?php echo $so_no_item; ?></td>
                <td class="td-val" colspan="2"><?php echo $so_delivery; ?></td>
                <td class="td-val td-left" colspan="3"><?php echo $po_no; ?></td>
                <td class="td-val td-right"><?php echo $so_order_wt; ?></td>
                <td class="td-val td-right"><?php echo $so_order_pc; ?></td>
                <td class="td-val" colspan="2"><?php echo ($so_max_tol ? $so_max_tol.'%' : '-') . ' / ' . ($so_min_tol ? $so_min_tol.'%' : '-'); ?></td>
            </tr>

            <!-- Row 8: Customer Details -->
            <tr>
                <td class="th-title">CUSTOMER</td>
                <td class="td-val td-left" colspan="6" style="padding-left: 6px;">
                    <?php echo $cust_id ? "{$cust_id} : {$cust_name}" : ''; ?>
                </td>
                <td class="th-title">MATERIAL IN</td>
                <td class="td-val" colspan="2"><?php echo $material_in; ?></td>
            </tr>

            <!-- Row 9: Customer Specification & Job Remark -->
            <tr>
                <td class="th-title">JOB REMARK</td>
                <td class="td-val td-left" colspan="9" style="font-family: monospace; font-size: 9.5px; padding-left: 6px;">
                    <?php echo $remark_display; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Lower Section: ตารางบันทึกการผลิต -->
    <table class="tracking-table-unified">
        <colgroup>
            <col style="width: 9%;">
            <col style="width: 15%;">
            <col style="width: 6.5%;">
            <col style="width: 6.5%;">
            <col style="width: 6.5%;">
            <col style="width: 6.5%;">
            <col style="width: 7.5%;">
            <col style="width: 7.5%;">
            <col style="width: 6%;">
            <col style="width: 4.5%;">
            <col style="width: 4.5%;">
            <col style="width: 6%;">
            <col style="width: 4.5%;">
            <col style="width: 4.5%;">
            <col style="width: 5%;">
        </colgroup>
        <thead>
            <tr>
                <th colspan="6" class="border-split-right">COIL INPUT & PROCESS BALANCE</th>
                <th colspan="9">OUTPUT PRODUCT & PALLET LOG</th>
            </tr>
            <tr>
                <th>COIL NO.</th>
                <th>SPECIFICATION</th>
                <th>WEIGHT</th>
                <th>PRODUCE</th>
                <th>SCRAP</th>
                <th class="border-split-right">BALANCE</th>
                <th colspan="2">OUTPUT PRODUCT</th>
                <th rowspan="2">DATE<br><span style="font-size:7.5px;">DD/MM/YY</span></th>
                <th colspan="2">TIME</th>
                <th rowspan="2">PRODUCE</th>
                <th colspan="2">PALLET WEIGHT</th>
                <th rowspan="2">OPERATOR</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th class="border-split-right"></th>
                <th>PRODUCT NO.</th>
                <th>COIL NO.</th>
                <th>START</th>
                <th>END</th>
                <th>TOP</th>
                <th>BOTTOM</th>
            </tr>
        </thead>
        <tbody>
        <?php
            $max_rows = 14;
            for ($k = 0; $k < $max_rows; $k++) {
                $has_coil = isset($reserved_coils[$k]);
                $c_no   = $has_coil ? htmlspecialchars($reserved_coils[$k]['COIL_NO'] ?? '') : '&nbsp;';
                $c_spec = $has_coil ? htmlspecialchars(trim($reserved_coils[$k]['ALLOY'] ?? '') . ' ' . trim($reserved_coils[$k]['TEMPER'] ?? '') . ' ' . number_format((float)($reserved_coils[$k]['THICKNESS'] ?? 0), 2) . 'x' . number_format((float)($reserved_coils[$k]['WIDTH'] ?? 0), 0)) : '';
                $c_wt   = $has_coil ? number_format((float)($reserved_coils[$k]['COIL_ACTUALWEIGHT'] ?? 0), 0) : '';

                echo "<tr>";
                echo "<td>{$c_no}</td>";
                echo "<td style='text-align:left; font-size:8px; padding-left:3px;'>{$c_spec}</td>";
                echo "<td style='text-align:right; padding-right:3px;'>{$c_wt}</td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td class='border-split-right'></td>";
                echo "<td>&nbsp;</td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "<td></td>";
                echo "</tr>";
            }
        ?>
        </tbody>
    </table>

    <!-- Revision & Form Code Note -->
    <div class="footer-note">
        <div>FM-07-009</div>
        <div>REV.0 / 5 MAR. 12</div>
    </div>
</div>

</body>
</html>