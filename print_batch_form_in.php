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

// ดึงรายการชิ้นงานใน Batch
$stmt_items = $conn->prepare("
    SELECT b0.*, j.SCHEDULE_DATE AS ETD_DATE, j.TEMPER AS JOB_TEMPER
    FROM BTCHPROD0 b0
    LEFT JOIN JOBORDER1 j ON b0.JOB_ORDER = j.JOB_ORDER
    WHERE b0.BTCH_NO = :bno
    ORDER BY b0.PALLET_ITEM ASC, b0.PRODUCT_NO ASC
");
$stmt_items->execute([':bno' => $bno]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$total_pallet = 0;
$total_weight = 0;
foreach ($items as $it) {
    if ((int)$it['PALLET_ITEM'] > $total_pallet) {
        $total_pallet = (int)$it['PALLET_ITEM'];
    }
    $total_weight += (float)($it['BTCH_INPUTWEIGHT'] ?? 0);
}

$control_by = trim($header['CONTROL_BY'] ?? 'T');
$heat_time = (int)($header['AS_HEATTIME'] ?? 0);
$soak_time = (int)($header['AS_SOAKTIME'] ?? 0);
$heat_hr = floor($heat_time / 60);
$heat_min = $heat_time % 60;
$soak_hr = floor($soak_time / 60);
$soak_min = $soak_time % 60;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>BTCHFORM1 - <?php echo $bno; ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, 'Sarabun', sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .report-container { width: 100%; margin: 0 auto; }
        .table-bordered {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .box-title {
            border: 1.5px solid #000;
            font-size: 14px;
            font-weight: bold;
            padding: 5px;
            text-align: center;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .rules-box {
            font-size: 9.5px;
            line-height: 1.25;
            padding: 4px;
        }
        .drawing-area {
            height: 120px;
            border: 1px solid #000;
            position: relative;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
            font-size: 11px;
        }
        .shutter-door {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            border-left: 1px dashed #000;
            padding: 2px 4px;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#f1f5f9; padding:10px 15px; border-bottom:1px solid #cbd5e1; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
    <span style="font-size:13px; font-weight:bold;">📄 พิมพ์แบบฟอร์ม BTCHFORM1: <?php echo $bno; ?></span>
    <div>
        <button onclick="window.print()" style="background:#2563eb; color:#fff; border:none; padding:6px 16px; border-radius:4px; font-weight:bold; cursor:pointer;">🖨️ พิมพ์เอกสาร (Print)</button>
        <button onclick="window.close()" style="background:#64748b; color:#fff; border:none; padding:6px 16px; border-radius:4px; font-weight:bold; cursor:pointer; margin-left:6px;">ปิดหน้าต่าง</button>
    </div>
</div>

<div class="report-container">
    <div class="box-title">INPUT BATCH ANNEALING DAILY PRODUCTION REPORT</div>

    <!-- Header Specs & Approvals -->
    <table class="table-bordered">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-size: 10px; font-weight: bold; margin-bottom: 4px;">
                    Need Provided and Approved Annealing Practice before Arrange to Anneal (ต้องกำหนดค่า Practice การอบและอนุมัติ โดยส่วนงาน Technical ก่อน จึงสามารถดำเนินงานการอบงาน)
                </div>
                <div style="margin-bottom: 2px;">
                    <input type="checkbox" <?php echo ($control_by === 'T' ? 'checked' : ''); ?>> <strong>Control by time (อบแบบควบคุมเวลา)</strong>
                </div>
                <div style="margin-bottom: 4px;">
                    <input type="checkbox" <?php echo ($control_by === 'P' ? 'checked' : ''); ?>> <strong>Control by work pieces temperature. (อบแบบควบคุมอุณหภูมิชิ้นงาน)</strong>
                </div>
                <table style="width:100%; font-size:10.5px; border-collapse:collapse;">
                    <tr>
                        <td>Heat up air temperature set point (ค่าอุณหภูมิลมร้อนขณะอบชิ้นงาน)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['AS_HEATTEMPERATURE'] ?? '0'); ?> °C</td>
                    </tr>
                    <tr>
                        <td>Heat up time (เป้าหมายเวลาอบชิ้นงาน)</td>
                        <td class="text-right font-bold"><?php echo $heat_hr; ?> Hrs <?php echo $heat_min; ?> Min</td>
                    </tr>
                    <tr>
                        <td>Soaking air temperature set point (ค่าอุณหภูมิลมร้อนขณะแช่ชิ้นงาน)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['AS_SOAKTEMPERATURE'] ?? '0'); ?> °C</td>
                    </tr>
                    <tr>
                        <td>Soaking Time (เป้าหมายเวลาแช่ชิ้นงาน)</td>
                        <td class="text-right font-bold"><?php echo $soak_hr; ?> Hrs <?php echo $soak_min; ?> Min</td>
                    </tr>
                    <tr>
                        <td>Expected work pieces Heat up temperature (ค่าคาดหวังอุณหภูมิชิ้นงานขณะอบ)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['AS_WPHEATTEMPERATURE'] ?? '0'); ?> °C</td>
                    </tr>
                    <tr>
                        <td>Expected work pieces Soaking temperature (ค่าคาดหวังอุณหภูมิชิ้นงานขณะแช่)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['AS_WPSOAKTEMPERATURE'] ?? '0'); ?> °C</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding: 0;">
                <table style="width:100%; border-collapse:collapse; height:100%;">
                    <tr>
                        <td style="border-bottom:1px solid #000; padding:3px 6px;">
                            <strong>Date (วันที่):</strong> <?php echo !empty($header['BTCH_STARTDATE']) ? date('d/m/Y', strtotime($header['BTCH_STARTDATE'])) : date('d/m/Y'); ?>
                        </td>
                        <td style="border-bottom:1px solid #000; border-left:1px solid #000; padding:3px 6px;">
                            <strong>Shift (กะ):</strong> _______
                        </td>
                    </tr>
                    <tr>
                        <td style="border-bottom:1px solid #000; padding:3px 6px;">
                            <strong>Batch Number:</strong> <span style="font-size:12px; font-weight:bold;"><?php echo $bno; ?></span>
                        </td>
                        <td style="border-bottom:1px solid #000; border-left:1px solid #000; padding:3px 6px;">
                            <strong>Page:</strong> 1 of 1
                        </td>
                    </tr>
                    <tr>
                        <td class="text-center font-bold" style="border-bottom:1px solid #000; background:#f8fafc; font-size:10px;">REQUEST/RECORD BY</td>
                        <td class="text-center font-bold" style="border-bottom:1px solid #000; border-left:1px solid #000; background:#f8fafc; font-size:10px;">SUPERINTENDENT</td>
                    </tr>
                    <tr style="height: 38px;">
                        <td style="border-bottom:1px solid #000;">&nbsp;</td>
                        <td style="border-bottom:1px solid #000; border-left:1px solid #000;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="text-center font-bold" style="border-bottom:1px solid #000; background:#f8fafc; font-size:10px;">TECHNICAL</td>
                        <td class="text-center font-bold" style="border-bottom:1px solid #000; border-left:1px solid #000; background:#f8fafc; font-size:10px;">PRO.SECTION HEAD</td>
                    </tr>
                    <tr style="height: 38px;">
                        <td>&nbsp;</td>
                        <td style="border-left:1px solid #000;">&nbsp;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- N2 & Drawing Area & Rules -->
    <table class="table-bordered">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <table style="width:100%; font-size:10.5px; border-collapse:collapse; margin-bottom:4px;">
                    <tr>
                        <td>% N₂ in storage tank (ปริมาณในไนโตรเจนในถังเก็บ) (Need up to 35%)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['N2_STORAGE'] ?? '0'); ?> %</td>
                    </tr>
                    <tr>
                        <td>Start Anneal Time (เวลาเริ่มอบ)</td>
                        <td class="text-right font-bold"><?php echo !empty($header['BTCH_STARTDATE']) ? date('d/m/Y H:i', strtotime($header['BTCH_STARTDATE'] . ' ' . $header['BTCH_STARTTIME'])) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td>Expected time open Batch (คาดคะเนเวลาเปิดเตา)</td>
                        <td class="text-right font-bold"><?php echo !empty($header['BTCH_EXPECTDATE']) ? date('d/m/Y H:i', strtotime($header['BTCH_EXPECTDATE'] . ' ' . $header['BTCH_EXPECTTIME'])) : '-'; ?></td>
                    </tr>
                    <tr>
                        <td>Actual time open Batch (เปิดเตาจริงเวลา)</td>
                        <td class="text-right">____________________</td>
                    </tr>
                    <tr>
                        <td>N₂ meter before start anneal (ค่า N₂ มิเตอร์ก่อนอบ)</td>
                        <td class="text-right font-bold"><?php echo htmlspecialchars($header['N2_START'] ?? '0'); ?> m³/h</td>
                    </tr>
                    <tr>
                        <td>N₂ meter after completed anneal (ค่า N₂ มิเตอร์หลังอบ)</td>
                        <td class="text-right"><?php echo htmlspecialchars($header['N2_END'] ?? '_______'); ?> m³/h</td>
                    </tr>
                </table>

                <div class="font-bold text-center" style="font-size:10.5px; margin: 4px 0;">Product arrange drawing รูปการวางงานในเตา</div>
                <div style="display:flex;">
                    <div style="flex:1;">
                        <div style="font-size:9px; text-align:center;">Top บน / Right ขวา</div>
                        <div class="drawing-area">
                            Product Placement Layout
                        </div>
                        <div style="font-size:9px; text-align:center;">Bottom ล่าง / Left ซ้าย</div>
                    </div>
                    <div class="shutter-door">Shutter Door</div>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;" class="rules-box">
                <strong style="text-decoration: underline;">ข้อปฏิบัติ</strong><br>
                - การอบแบบควบคุมเวลา (By Time) น้ำหนักรวมต่อการอบ 1 Batch พยายามอย่าให้ต่ำกว่า 6 ตัน ถ้าต่ำกว่า 6 ตัน ชิ้นงานจะรับอุณหภูมิได้เร็วกว่าปกติมาก อาจทำให้เกิดปัญหา Over Anneal<br>
                - หลีกเลี่ยงการอบผลิตภัณฑ์ที่มีขนาดเส้นผ่าศูนย์กลางต่างกันมากๆ และน้ำหนักที่ต่างกันมาก ในการอบ 1 Batch เพราะว่าชิ้นงานที่เล็กกว่าจะมีโอกาสเกิดปัญหา Over Anneal<br>
                - ถ้ามีไฟฟ้าดับขณะอบให้แจ้งหัวหน้างานทันที<br>
                - การวางชิ้นงานบน Pallet ที่จะอบความสูงต้องไม่เกิน 1500 มิลลิเมตร หรือควรมีระยะห่างจากผนังเตาด้านบนอย่างน้อย 10 ซม. และควรปิดทับด้านบนด้วยแผ่นเหล็ก<br>
                - ขอบ Pallet ต้องไม่เบียดหรือชิดข้างผนังเตา ขอบ Pallet ควรมีระยะห่างจากข้างผนังอย่างน้อย 1 นิ้ว<br>
                - ห้ามอบงานที่ต่างชนิด Alloy และต่างค่าความแข็งเป้าหมาย (Temper) ในเตาเดียวกัน ยกเว้นได้รับความยินยอมจากส่วนงาน Technical
            </td>
        </tr>
    </table>

    <!-- Product Table -->
    <table class="table-bordered" style="font-size: 10px;">
        <thead>
            <tr style="background:#f8fafc;">
                <th rowspan="2" style="width:30px;">Item</th>
                <th rowspan="2">Pallet / Coil No.<br><span style="font-size:9px; font-weight:normal;">หมายเลข Pallet/Coil</span></th>
                <th rowspan="2">Job Card No.<br><span style="font-size:9px; font-weight:normal;">หมายเลขใบสั่งผลิต</span></th>
                <th rowspan="2">ETD Date<br><span style="font-size:9px; font-weight:normal;">วันที่คาดว่าจะส่ง</span></th>
                <th colspan="2">Dimension (ขนาด)</th>
                <th rowspan="2">Weight<br><span style="font-size:9px; font-weight:normal;">น้ำหนัก (Kg)</span></th>
                <th rowspan="2">Alloy<br><span style="font-size:9px; font-weight:normal;">อัลลอย</span></th>
                <th colspan="2">Temper<br><span style="font-size:9px; font-weight:normal;">ค่าความแข็ง</span></th>
                <th colspan="3">Product Location</th>
            </tr>
            <tr style="background:#f8fafc;">
                <th>W x L or Dia</th>
                <th>Thick (mm)</th>
                <th>Before ก่อนอบ</th>
                <th>After หลังอบ</th>
                <th>Left ซ้าย</th>
                <th>Middle กลาง</th>
                <th>Right ขวา</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 0;
            foreach ($items as $row): 
                $count++;
                $dim = number_format((float)$row['WIDTH'], 3);
                if (!empty($row['LENGTH']) && (float)$row['LENGTH'] > 0) {
                    $dim .= ' x ' . number_format((float)$row['LENGTH'], 3);
                }
            ?>
            <tr>
                <td class="text-center"><?php echo $count; ?></td>
                <td class="text-center font-bold"><?php echo htmlspecialchars($row['PALLET_ITEM'] . ' / ' . $row['PRODUCT_NO']); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($row['JOB_ORDER'] ?? '-'); ?></td>
                <td class="text-center"><?php echo !empty($row['ETD_DATE']) ? date('d/m/Y', strtotime($row['ETD_DATE'])) : '-'; ?></td>
                <td class="text-center"><?php echo $dim; ?></td>
                <td class="text-right"><?php echo number_format((float)$row['THICKNESS'], 3); ?></td>
                <td class="text-right font-bold"><?php echo number_format((float)$row['BTCH_INPUTWEIGHT'], 0); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($row['ALLOY']); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($row['TEMPER_INITIAL']); ?></td>
                <td class="text-center font-bold"><?php echo htmlspecialchars($row['TEMPER_TARGET']); ?></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php endforeach; ?>
            <?php for($i=$count+1; $i<=10; $i++): ?>
            <tr>
                <td class="text-center" style="color:#ccc;"><?php echo $i; ?></td>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Actual Temperature Records Table -->
    <table class="table-bordered" style="font-size:10px; margin-top:2px;">
        <tr style="background:#f8fafc; font-weight:bold;">
            <td colspan="3" class="text-center">Air Temperature Set Point</td>
            <td colspan="2" class="text-center">Work Piece Temperature Set Point</td>
            <td colspan="2" class="text-center">Thermocouple Position</td>
        </tr>
        <tr style="background:#f8fafc; font-weight:bold;">
            <td class="text-center" style="width:14%;">Left (°C)</td>
            <td class="text-center" style="width:14%;">Right (°C)</td>
            <td class="text-center" style="width:14%;">Door (°C)</td>
            <td class="text-center" style="width:14%;">Tc1 (°C)</td>
            <td class="text-center" style="width:14%;">Tc2 (°C)</td>
            <td class="text-center" style="width:15%;">Tc1 (°C)</td>
            <td class="text-center" style="width:15%;">Tc2 (°C)</td>
        </tr>
        <tr style="height:22px;">
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
        </tr>
    </table>

    <!-- Remarks -->
    <table class="table-bordered" style="margin-top:2px;">
        <tr>
            <td style="width:50%; vertical-align:top; height:32px;">
                <strong>Production Remark:</strong> <?php echo htmlspecialchars($header['BTCH_PRODREMARK'] ?? ''); ?>
            </td>
            <td style="width:50%; vertical-align:top;">
                <strong>Technical Remark:</strong> <?php echo htmlspecialchars($header['BTCH_TECHREMARK'] ?? ''); ?>
            </td>
        </tr>
    </table>

    <!-- Summary & Signatures Footer -->
    <table class="table-bordered" style="margin-top:2px;">
        <tr>
            <td style="width:15%;" class="text-center font-bold">Total Coil/Pallet</td>
            <td style="width:15%;" class="text-center font-bold">Total Weight</td>
            <td style="width:23%;" class="text-center font-bold">Report By</td>
            <td style="width:23%;" class="text-center font-bold">Checked By</td>
            <td style="width:24%;" class="text-center font-bold">Approved</td>
        </tr>
        <tr style="height: 38px;">
            <td class="text-center font-bold" style="font-size:12px;"><?php echo $total_pallet; ?> Coil/Pallet</td>
            <td class="text-center font-bold" style="font-size:12px;"><?php echo number_format($total_weight, 0); ?> Kgs</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <div style="display:flex; justify-content:space-between; font-size:9.5px; margin-top:2px;">
        <span>FM-02-014</span>
        <span>Rev.05 / 15 Mar. 12</span>
    </div>
</div>

</body>
</html>