<?php
session_start();
if (empty($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'dbcon_mats-new.php';

$bno = isset($_GET['bno']) ? htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8') : '';
if (empty($bno)) {
    die("ไม่พบหมายเลข Batch No.");
}

// ดึงข้อมูล Master Header
$stmt_h = $conn->prepare("SELECT TOP 1 * FROM BTCHPROD0 WHERE BTCH_NO = :bno");
$stmt_h->execute([':bno' => $bno]);
$header = $stmt_h->fetch(PDO::FETCH_ASSOC);

if (!$header) {
    die("ไม่พบข้อมูล Batch นี้ในระบบ");
}

// ดึงรายการชิ้นงานใน Batch พร้อมผลตรวจคุณภาพจาก BTCHPROD1
$stmt_items = $conn->prepare("
    SELECT b0.*, 
           b1.TEMPER AS OUT_TEMPER,
           b1.BTCH_UTS, b1.BTCH_YS, b1.BTCH_ELONGATION, b1.BTCH_RESULT,
           b1.BTCH_ACCEPTWEIGHT, b1.BTCH_ACCEPTPIECE,
           b1.BTCH_STARTDATE AS ACT_STARTDATE, b1.BTCH_STARTTIME AS ACT_STARTTIME,
           b1.BTCH_ENDDATE AS ACT_ENDDATE, b1.BTCH_ENDTIME AS ACT_ENDTIME,
           c.CTM2_ENDCUSTOMERNAME
    FROM BTCHPROD0 b0
    LEFT JOIN BTCHPROD1 b1 ON b0.PRODUCT_NO = b1.PRODUCT_NO AND b0.BTCH_NO = b1.BTCH_NO
    LEFT JOIN CSTMORDR2 c ON b0.SALEORDER_NO = c.SALEORDER_NO AND b0.SALEORDER_ITEM = c.SALEORDER_ITEM
    WHERE b0.BTCH_NO = :bno
    ORDER BY b0.PALLET_ITEM ASC, b0.PRODUCT_NO ASC
");
$stmt_items->execute([':bno' => $bno]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$control_by = ($header['CONTROL_BY'] === 'P') ? 'WORKPIECE' : 'TIME';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>BTCHFORM2 - <?php echo $bno; ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 8mm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, 'Sarabun', sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .report-container { width: 100%; margin: 0 auto; }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .table-data th, .table-data td {
            border: 1px solid #000;
            padding: 2.5px 3px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .header-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .header-sub {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f1f5f9; padding:10px 15px; border-bottom:1px solid #cbd5e1; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
    <span style="font-size:13px; font-weight:bold;">📊 พิมพ์แบบฟอร์ม BTCHFORM2: <?php echo $bno; ?></span>
    <div>
        <button onclick="window.print()" style="background:#0891b2; color:#fff; border:none; padding:6px 16px; border-radius:4px; font-weight:bold; cursor:pointer;">🖨️ พิมพ์เอกสาร (Print)</button>
        <button onclick="window.close()" style="background:#64748b; color:#fff; border:none; padding:6px 16px; border-radius:4px; font-weight:bold; cursor:pointer; margin-left:6px;">ปิดหน้าต่าง</button>
    </div>
</div>

<div class="report-container">
    <!-- Header Title -->
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:65%;">
                <div class="header-title">MEYER ALUMINIUM (THAILAND) CO., LTD.</div>
                <div class="header-sub">BATCH ANNEALING MECHANICAL PROPERTIES REPORT</div>
            </td>
            <td style="width:35%; text-align:right; font-size:10px; line-height:1.4;">
                <strong>BATCH NO. :</strong> <?php echo $bno; ?><br>
                <strong>PRINT DATE :</strong> <?php echo date('d/m/Y'); ?> &nbsp;&nbsp;<strong>Page</strong> 1 of 1<br>
                <strong>UPDATE BY :</strong> <?php echo htmlspecialchars($header['BTCH_UPDATE'] ?? $header['BTCH_OPERATOR'] ?? ''); ?>
            </td>
        </tr>
    </table>

    <!-- Main Data Table -->
    <table class="table-data" style="font-size: 9px;">
        <thead>
            <tr style="background:#f8fafc; text-align:center;">
                <th rowspan="2" style="width:25px;">STEEL<br>PALLET<br>ITEM</th>
                <th rowspan="2">PRODUCT NO.<br>PRO.ID.</th>
                <th rowspan="2">S.O. NO.<br>JOB ORDER<br>CUSTOMER</th>
                <th rowspan="2">ALLOY<br>GRADE<br>SG / MG</th>
                <th rowspan="2">TEMPER<br>INPUT<br>TARGET<br>OUTPUT</th>
                <th rowspan="2">THICKNESS<br>WIDTH<br>LENGTH</th>
                <th rowspan="2">INPUT<br>WEIGHT<br>PIECE</th>
                <th rowspan="2">OUTPUT<br>WEIGHT<br>PIECE</th>
                <th rowspan="2">START DATETIME<br>EXPECT DATETIME<br>OPEN DATETIME</th>
                <th rowspan="2">CONTROL<br>BY</th>
                <th rowspan="2" style="width:40px;">&nbsp;</th>
                <th colspan="2">BY TIME</th>
                <th colspan="2">BY WORK PIECE</th>
                <th colspan="3">MECHANICAL PROPERTIES</th>
                <th rowspan="2" style="width:40px;">PROD.<br>STATUS<br>RESULT</th>
            </tr>
            <tr style="background:#f8fafc; text-align:center;">
                <th>HEATING UP<br>TIME / TEMP<br>(MIN / °C)</th>
                <th>SOAKING<br>TIME / TEMP<br>(MIN / °C)</th>
                <th>HEATING<br>TEMP<br>(°C)</th>
                <th>SOAKING<br>TEMP<br>(°C)</th>
                <th>UTS</th>
                <th>YS</th>
                <th>ELONG.</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): 
                $pno = htmlspecialchars($it['PRODUCT_NO']);
                $dim = number_format((float)$it['THICKNESS'], 3) . '<br>' . number_format((float)$it['WIDTH'], 3);
                if (!empty($it['LENGTH']) && (float)$it['LENGTH'] > 0) {
                    $dim .= '<br>' . number_format((float)$it['LENGTH'], 3);
                }

                $start_dt = !empty($it['BTCH_STARTDATE']) ? date('d/m/Y H:i', strtotime($it['BTCH_STARTDATE'] . ' ' . $it['BTCH_STARTTIME'])) : '-';
                $exp_dt   = !empty($it['BTCH_EXPECTDATE']) ? date('d/m/Y H:i', strtotime($it['BTCH_EXPECTDATE'] . ' ' . $it['BTCH_EXPECTTIME'])) : '-';
                $act_open = !empty($it['ACT_ENDDATE']) ? date('d/m/Y H:i', strtotime($it['ACT_ENDDATE'] . ' ' . $it['ACT_ENDTIME'])) : '-';

                $spec_uts = !empty($it['RANGE_FROM']) ? '>= ' . number_format((float)$it['RANGE_FROM'], 2) : '-';
                $act_uts  = (float)($it['BTCH_UTS'] ?? 0) > 0 ? number_format((float)$it['BTCH_UTS'], 2) : '-';
                $act_ys   = (float)($it['BTCH_YS'] ?? 0) > 0 ? number_format((float)$it['BTCH_YS'], 2) : '-';
                $act_el   = (int)($it['BTCH_ELONGATION'] ?? 0) > 0 ? (int)$it['BTCH_ELONGATION'] : '-';
            ?>
            <!-- 3 Rows per Product Item (SPEC / SETUP / ACTUAL) -->
            <tr>
                <td rowspan="3" class="text-center font-bold"><?php echo htmlspecialchars($it['PALLET_ITEM']); ?></td>
                <td rowspan="3" class="text-center">
                    <strong><?php echo $pno; ?></strong><br>
                    <span style="font-size:8.5px;"><?php echo htmlspecialchars($it['PRODUCT_ID']); ?></span>
                </td>
                <td rowspan="3" class="text-center">
                    <?php echo htmlspecialchars($it['SALEORDER_NO'] ?? '-'); ?><br>
                    <strong><?php echo htmlspecialchars($it['JOB_ORDER'] ?? '-'); ?></strong><br>
                    <span style="font-size:8px;"><?php echo htmlspecialchars($it['CTM2_ENDCUSTOMERNAME'] ?? ''); ?></span>
                </td>
                <td rowspan="3" class="text-center">
                    <strong><?php echo htmlspecialchars($it['ALLOY']); ?></strong><br>
                    - / -
                </td>
                <td rowspan="3" class="text-center">
                    <?php echo htmlspecialchars($it['TEMPER_INITIAL']); ?><br>
                    <strong><?php echo htmlspecialchars($it['TEMPER_TARGET']); ?></strong><br>
                    <?php echo htmlspecialchars($it['OUT_TEMPER'] ?? '-'); ?>
                </td>
                <td rowspan="3" class="text-center"><?php echo $dim; ?></td>
                <td rowspan="3" class="text-right">
                    <?php echo number_format((float)$it['BTCH_INPUTWEIGHT'], 0); ?><br>
                    <?php echo (int)($it['BTCH_INPUTPIECE'] ?? 1); ?>
                </td>
                <td rowspan="3" class="text-right">
                    <?php echo number_format((float)($it['BTCH_ACCEPTWEIGHT'] ?? $it['BTCH_INPUTWEIGHT']), 0); ?><br>
                    <?php echo (int)($it['BTCH_ACCEPTPIECE'] ?? $it['BTCH_INPUTPIECE'] ?? 1); ?>
                </td>
                <td rowspan="3" class="text-center" style="font-size:8.5px;">
                    <?php echo $start_dt; ?><br>
                    <?php echo $exp_dt; ?><br>
                    <?php echo $act_open; ?>
                </td>
                <td rowspan="3" class="text-center font-bold"><?php echo $control_by; ?></td>
                
                <!-- ROW 1: SPEC -->
                <td class="text-center font-bold" style="background:#f8fafc;">SPEC.</td>
                <td class="text-center"><?php echo (int)($it['TP_HEATTIME'] ?? 0); ?> / <?php echo (int)($it['TP_HEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['TP_SOAKTIME'] ?? 0); ?> / <?php echo (int)($it['TP_SOAKTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['TP_WPHEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['TP_WPSOAKTEMPERATUREFROM'] ?? 0); ?>-<?php echo (int)($it['TP_WPSOAKTEMPERATURETO'] ?? 0); ?></td>
                <td class="text-center"><?php echo $spec_uts; ?></td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td rowspan="3" class="text-center font-bold">
                    <?php echo htmlspecialchars($it['BTCH_STATUS'] ?? 'OT'); ?><br>
                    <span style="color:<?php echo ($it['BTCH_RESULT']==='PASS'?'#15803d':'#b91c1c'); ?>">
                        <?php echo htmlspecialchars($it['BTCH_RESULT'] ?? '-'); ?>
                    </span>
                </td>
            </tr>
            <!-- ROW 2: SETUP (AS) -->
            <tr>
                <td class="text-center font-bold" style="background:#f8fafc;">SETUP</td>
                <td class="text-center"><?php echo (int)($it['AS_HEATTIME'] ?? 0); ?> / <?php echo (int)($it['AS_HEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['AS_SOAKTIME'] ?? 0); ?> / <?php echo (int)($it['AS_SOAKTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['AS_WPHEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($it['AS_WPSOAKTEMPERATURE'] ?? 0); ?></td>
                <td rowspan="2" class="text-center font-bold" style="font-size:10px;"><?php echo $act_uts; ?></td>
                <td rowspan="2" class="text-center font-bold" style="font-size:10px;"><?php echo $act_ys; ?></td>
                <td rowspan="2" class="text-center font-bold" style="font-size:10px;"><?php echo $act_el; ?></td>
            </tr>
            <!-- ROW 3: ACTUAL (AC) -->
            <tr>
                <td class="text-center font-bold" style="background:#f8fafc;">ACTUAL</td>
                <td class="text-center"><?php echo (int)($header['AC_HEATTIME'] ?? 0); ?> / <?php echo (int)($header['AC_HEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($header['AC_SOAKTIME'] ?? 0); ?> / <?php echo (int)($header['AC_SOAKTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($header['AC_WPHEATTEMPERATURE'] ?? 0); ?></td>
                <td class="text-center"><?php echo (int)($header['AC_WPSOAKTEMPERATURE'] ?? 0); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signature Footer -->
    <div style="margin-top: 50px; display: flex; justify-content: space-around; text-align: center; font-size: 10px;">
        <div style="width: 20%;">
            ____________________________________<br>
            <strong>OPERATED BY</strong>
        </div>
        <div style="width: 20%;">
            ____________________________________<br>
            <strong>SUPERINTENDENT</strong>
        </div>
        <div style="width: 25%;">
            ____________________________________<br>
            <strong>PRODUCTION SECTION HEAD</strong>
        </div>
        <div style="width: 25%;">
            ____________________________________<br>
            <strong>TECHNICAL SECTION HEAD</strong>
        </div>
    </div>
</div>

</body>
</html>