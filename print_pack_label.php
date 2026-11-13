<?php
session_start();
include("dbcon_mats-new.php");

$product_no = trim($_GET['product_no'] ?? '');

if (empty($product_no)) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Product No. is required.</div>");
}

$sql = "SELECT p.*, 
               c.PRODUCT_MODEL, c.ALLOY, c.TEMPER, c.SURFACE_GRADE, c.METALLURGICAL_GRADE,
               c.THICKNESS, c.WIDTH, c.LENGTH, c.CRSH_UOMDIMENSION, c.CRSH_UOMWEIGHT,
               c.COIL_NO, c.F_WIDTH,
               c.CRSH_DIMENSIONWIDTH, c.CRSH_DIMENSIONLENGTH, c.CRSH_DIMENSIONHIGH,
               so1.CSTMSPPL_ID, so1.CTM1_REMARK1,
               so2.CTM2_PONO, so2.CTM2_POITEM, so2.CTM2_CUSTOMERPARTNO,
               so2.CONFIRM_DATE, so2.REQUEST_DATE, so2.CTM2_UOMWEIGHT, so2.CTM2_UOMDIMENSION,
               so2.CT2U_THICKNESS, so2.CT2U_WIDTH, so2.CT2U_LENGTH, so2.CT2U_ORDERWEIGHT,
               so2.CT2U_UOMDIMENSION, so2.CT2U_UOMWEIGHT,
               sp.CONSIGNEE_COMPANY, sp.CONSIGNEE_ADDRESS1, sp.CONSIGNEE_ADDRESS2, sp.CONSIGNEE_ADDRESS3,
               sp.UOM_WEIGHT AS CUST_UOM_WEIGHT, sp.UOM_DIMENSION AS CUST_UOM_DIM
        FROM [MATS-NEWII].dbo.PACKPROD1 p
        LEFT JOIN [MATS-NEWII].dbo.CRSHPROD1 c ON p.PRODUCT_NO = c.PRODUCT_NO
        LEFT JOIN [MATS-NEWII].dbo.CSTMORDR2 so2 ON p.SALEORDER_NO = so2.SALEORDER_NO AND p.SALEORDER_ITEM = so2.SALEORDER_ITEM
        LEFT JOIN [MATS-NEWII].dbo.CSTMORDR1 so1 ON p.SALEORDER_NO = so1.SALEORDER_NO
        LEFT JOIN [MATS-NEWII].dbo.CSSPMSTR1 sp ON so1.CSTMSPPL_ID = sp.CSTMSPPL_ID
        WHERE p.PRODUCT_NO = :pno";

$stmt = $conn->prepare($sql);
$stmt->execute([':pno' => $product_no]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$d) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Packaging record not found for: {$product_no}</div>");
}

// ตรวจสอบหน่วย UOM
$uom_weight_raw = strtolower(trim($d['CT2U_UOMWEIGHT'] ?? $d['CTM2_UOMWEIGHT'] ?? $d['CUST_UOM_WEIGHT'] ?? $d['CRSH_UOMWEIGHT'] ?? ''));
$is_lbs = (strpos($uom_weight_raw, 'lb') !== false || strpos($uom_weight_raw, 'pound') !== false);

$uom_dim_raw = strtolower(trim($d['CT2U_UOMDIMENSION'] ?? $d['CTM2_UOMDIMENSION'] ?? $d['CUST_UOM_DIM'] ?? $d['CRSH_UOMDIMENSION'] ?? ''));
$is_inch = (strpos($uom_dim_raw, 'in') !== false || $is_lbs);

// 1. น้ำหนัก (ปัดเศษให้ตรงกับใบ Ticket จริง เช่น 4,017.00 lbs.)
$raw_net_wt = (float)($d['PACK_NETWEIGHT'] ?? 0);
$raw_pkg_wt = (float)($d['PACK_PACKAGEWEIGHT'] ?? 0);

if ($is_lbs) {
    $uom_weight = "lbs.";
    $net_wt = ($raw_net_wt < 2500 && $raw_net_wt > 0) ? round($raw_net_wt * 2.20462262) : round($raw_net_wt);
    $pkg_wt = ($raw_pkg_wt < 200 && $raw_pkg_wt > 0) ? round($raw_pkg_wt * 2.20462262) : round($raw_pkg_wt);
} else {
    $uom_weight = "kg.";
    $net_wt = round($raw_net_wt);
    $pkg_wt = round($raw_pkg_wt);
}
$gross_wt = $net_wt + $pkg_wt;

// 2. ขนาดมิติ (Thickness, Width, Length)
if ($is_inch) {
    $uom_dim = "inch";
    $thickness = ((float)($d['CT2U_THICKNESS'] ?? 0) > 0) ? (float)$d['CT2U_THICKNESS'] : (float)$d['THICKNESS'];
    $width     = ((float)($d['CT2U_WIDTH'] ?? 0) > 0) ? (float)$d['CT2U_WIDTH'] : (float)$d['WIDTH'];
    $length    = ((float)($d['CT2U_LENGTH'] ?? 0) > 0) ? (float)$d['CT2U_LENGTH'] : (float)$d['LENGTH'];

    if ($width > 150) $width = $width / 25.4;
    if ($length > 150) $length = $length / 25.4;
} else {
    $uom_dim = "mm.";
    $thickness = (float)$d['THICKNESS'];
    $width     = (float)$d['WIDTH'];
    $length    = (float)$d['LENGTH'];
}

// 3. มิติพาเลท PACKAGE DIMENSION
$dw_mm = (float)($d['CRSH_DIMENSIONWIDTH'] ?? 0);
$dl_mm = (float)($d['CRSH_DIMENSIONLENGTH'] ?? 0);
$dh_mm = (float)($d['CRSH_DIMENSIONHIGH'] ?? 0);

if ($is_inch && $dw_mm > 0) {
    $dw = round($dw_mm / 25.4);
    $dl = round($dl_mm / 25.4);
    $dh = round($dh_mm / 25.4);
} else {
    $dw = round($dw_mm);
    $dl = round($dl_mm);
    $dh = round($dh_mm);
}

// Delivery Date
$delivery_date_raw = !empty($d['CONFIRM_DATE']) ? $d['CONFIRM_DATE'] : $d['REQUEST_DATE'];
$delivery_date = !empty($delivery_date_raw) ? date('m/d/Y', strtotime($delivery_date_raw)) : '-';

// Customer Info
$cust_code  = htmlspecialchars(trim($d['CSTMSPPL_ID'] ?? ''));
$cust_name  = htmlspecialchars(trim($d['CONSIGNEE_COMPANY'] ?? 'Specialty Metals Supply, Inc.'));
$cust_addr1 = htmlspecialchars(trim($d['CONSIGNEE_ADDRESS1'] ?? ''));
$cust_addr2 = htmlspecialchars(trim($d['CONSIGNEE_ADDRESS2'] ?? ''));
$cust_addr3 = htmlspecialchars(trim($d['CONSIGNEE_ADDRESS3'] ?? ''));

// Actual Width of Coil
$act_coil_w = (float)($d['F_WIDTH'] ?? 0);
if ($act_coil_w <= 0) {
    $act_coil_w = $is_inch ? ($width * 25.4) : $width;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Ticket - <?php echo htmlspecialchars($product_no); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 6mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
        }

        /* กรอบหลักเต็มพื้นที่ A4 แนวนอน */
        .ticket-wrapper {
            width: 297mm;
            height: 200mm;
            margin: 0 auto;
            border: 2.5px solid #000;
            display: flex;
            flex-direction: column;
        }

        .ticket-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .ticket-table td {
            border: 1.5px solid #000;
            padding: 1px 6px;
            vertical-align: middle;
            overflow: hidden;
            white-space: nowrap;
        }

        /* กำหนดสัดส่วนคอลัมน์แน่นอนด้วย colgroup */
        col.col-label-left  { width: 80mm; }
        col.col-value-left  { width: 90mm; }
        col.col-label-right { width: 45mm; }
        col.col-value-right { width: 70mm; }

        .header-company {
            font-size: 12.5px;
            line-height: 1.35;
            white-space: normal !important;
        }
        .header-company .title {
            font-size: 22px;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .header-customer {
            font-size: 12.5px;
            line-height: 1.35;
            white-space: normal !important;
        }
        .header-customer .title {
            font-size: 22px;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .header-stickers {
            padding: 0 !important;
        }
        .sticker-box-wrapper {
            display: flex;
            height: 100%;
            width: 100%;
        }
        .sticker-box {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
        }
        .sticker-box:first-child {
            border-right: 1.5px solid #000;
        }

        .col-lbl {
            font-size: 20px;
            
            text-transform: uppercase;
        }

        .val-large {
            font-size: 24px;
            font-weight: 900;
        }

        .val-huge {
            font-size: 28px;
            font-weight: 900;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .toolbar {
            max-width: 285mm;
            margin: 6px auto;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-print {
            background: #0f172a;
            color: #fff;
            padding: 8px 22px;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-print:hover { background: #1e293b; }

        @media print {
            .toolbar { display: none !important; }
            html, body {
                width: 297mm;
                height: 200mm;
            }
            .ticket-wrapper {
                width: 297mm;
                height: 200mm;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ PRINT TICKET</button>
    </div>

    <div class="ticket-wrapper">
        <table class="ticket-table">
            <!-- ล็อกขนาดของทั้ง 4 คอลัมน์ให้แน่นอน ไม่ซ้อนทับกัน -->
            <colgroup>
                <col class="col-label-left">
                <col class="col-value-left">
                <col class="col-label-right">
                <col class="col-value-right">
            </colgroup>

            <!-- แถวที่ 1: Header บริษัท, ลูกค้า, สติกเกอร์ -->
            <tr style="height: 17%;">
                <td class="header-company">
                    <div class="title text-center">PRODUCT TICKET</div>
                    <b>MEYER ALUMINIUM (THAILAND) CO.,LTD.</b><br>
                    <b>38/32 Moo. 5, Laem Chabang Industrial Estate,</b><br>
                    <b>Sriracha, Chonburi, 20230 Thailand</b><br>
                    <b>Tel. 6638-400652-62, FAX : 6638-400663</b>
                </td>
                <td class="header-customer">
                    <div class="title">CUSTOMER NAME - <?php echo $cust_code; ?> /</div>
                    <b><?php echo $cust_name; ?></b><br>
                    <b><?php if(!empty($cust_addr1)) echo $cust_addr1 . "<br>"; ?></b>
                    <b><?php if(!empty($cust_addr2)) echo $cust_addr2 . "<br>"; ?></b>
                    <b><?php if(!empty($cust_addr3)) echo $cust_addr3; ?></b>
                </td>
                <td class="header-stickers" colspan="2">
                    <div class="sticker-box-wrapper">
                        <div class="sticker-box">GRS Sticker</div>
                        <div class="sticker-box">QC Sticker</div>
                    </div>
                </td>
            </tr>

            <!-- แถวที่ 2: PRODUCT NO. & BARCODE -->
            <tr style="height: 8.5%;">
                <td class="col-lbl text-center">PRODUCT NO.</td>
                <td class="val-huge text-center">
                    <?php echo htmlspecialchars($d['PRODUCT_NO']); ?>
                </td>
                <td colspan="2" class="text-center" style="padding: 1px 4px;">
                    <svg id="barcode"></svg>
                </td>
            </tr>

            <!-- แถวที่ 3: CUSTOMER PART NO. & PRODUCT -->
            <tr style="height: 7.5%;">
                <td class="col-lbl text-center">CUSTOMER PART NO.</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['CTM2_CUSTOMERPARTNO'] ?? $d['PRODUCT_MODEL'] ?? '-'); ?>
                </td>
                <td class="col-lbl text-center">PRODUCT</td>
                <td class="val-large text-center">
                    <?php echo strtoupper(htmlspecialchars($d['PRODUCT_MODEL'] ?? $d['PRODUCT_ID'] ?? 'SHEET')); ?>
                </td>
            </tr>

            <!-- แถวที่ 4: PURCHASE ORDER & ALLOY -->
            <tr style="height: 7.5%;">
                <td class="col-lbl text-center">PURCHASE ORDER</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['CTM2_PONO'] ?? $d['PACK_PONO'] ?? '-'); ?>
                </td>
                <td class="col-lbl text-center">ALLOY</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['ALLOY'] ?? '-'); ?>
                </td>
            </tr>

            <!-- แถวที่ 5: SALE ORDER & TEMPER -->
            <tr style="height: 7.5%;">
                <td class="col-lbl text-center">SALE ORDER</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['SALEORDER_NO']); ?> : <?php echo htmlspecialchars($d['SALEORDER_ITEM']); ?>
                </td>
                <td class="col-lbl text-center">TEMPER</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['TEMPER'] ?? '-'); ?>
                </td>
            </tr>

            <!-- แถวที่ 6: JOB ORDER & SG / MG -->
            <tr style="height: 7.5%;">
                <td class="col-lbl text-center">JOB ORDER</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['JOB_ORDER'] ?? '-'); ?>
                </td>
                <td class="col-lbl text-center">SG / MG</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['SURFACE_GRADE'] ?? '-'); ?> / <?php echo htmlspecialchars($d['METALLURGICAL_GRADE'] ?? '-'); ?>
                </td>
            </tr>

            <!-- แถวที่ 7: DELIVERY DATE & THICKNESS -->
            <tr style="height: 7.5%;">
                <td class="col-lbl text-center">DELIVERY DATE</td>
                <td class="val-large text-center">
                    <?php echo $delivery_date; ?>
                </td>
                <td class="col-lbl text-center">THICKNESS</td>
                <td class="val-large text-center">
                    <?php echo number_format($thickness, 3); ?> &nbsp;<?php echo $uom_dim; ?>
                </td>
            </tr>

            <!-- แถวที่ 8: NET WEIGHT & WIDTH -->
            <tr style="height: 8%;">
                <td class="col-lbl text-center">NET WEIGHT</td>
                <td class="val-large text-right" style="padding-right: 28px;">
                    <?php echo number_format($net_wt, 2); ?> &nbsp;<?php echo $uom_weight; ?>
                </td>
                <td class="col-lbl text-center">WIDTH</td>
                <td class="val-large text-center">
                    <?php echo number_format($width, 3); ?> &nbsp;<?php echo $uom_dim; ?>
                </td>
            </tr>

            <!-- แถวที่ 9: PACKAGE WEIGHT & LENGTH -->
            <tr style="height: 8%;">
                <td class="col-lbl text-center">PACKAGE WEIGHT</td>
                <td class="val-large text-right" style="padding-right: 28px;">
                    <?php echo number_format($pkg_wt, 2); ?> &nbsp;<?php echo $uom_weight; ?>
                </td>
                <td class="col-lbl text-center">LENGTH</td>
                <td class="val-large text-center">
                    <?php echo number_format($length, 3); ?> &nbsp;<?php echo $uom_dim; ?>
                </td>
            </tr>

            <!-- แถวที่ 10: GROSS WEIGHT & PACKAGE DIMENSION HEADER -->
            <tr style="height: 7%;">
                <td class="col-lbl text-center">GROSS WEIGHT</td>
                <td class="val-large text-right" style="padding-right: 28px;">
                    <?php echo number_format($gross_wt, 2); ?> &nbsp;<?php echo $uom_weight; ?>
                </td>
                <td colspan="2" class="col-lbl text-center">PACKAGE DIMENSION</td>
            </tr>

            <!-- แถวที่ 11: CAST NO. (ด้านซ้าย) & PACKAGE DIMENSION VALUES (ด้านขวา) -->
            <tr style="height: 7%;">
                <td class="col-lbl text-center">CAST NO.</td>
                <td class="val-large text-center">
                    <?php echo htmlspecialchars($d['COIL_NO'] ?? '-'); ?>
                </td>
                <td colspan="2" class="val-huge text-center" style="letter-spacing: 2px;">
                    <?php echo "{$dw} &nbsp;&nbsp;x&nbsp;&nbsp; {$dl} &nbsp;&nbsp;x&nbsp;&nbsp; {$dh}"; ?>
                </td>
            </tr>

            <!-- แถวที่ 12: ACTUAL WIDTH OF COIL (ด้านขวา) & ด้านซ้ายเว้นว่างตามแบบจริง -->
            <tr style="height: 7%;">
                <td colspan="2" style="border-right: 1.5px solid #000; background: #fff;"></td>
                <td class="col-lbl text-center">
                    ACTUAL WIDTH<br>OF COIL
                </td>
                <td class="val-large text-right" style="padding-right: 18px;">
                    <?php echo ($act_coil_w > 0) ? number_format($act_coil_w, 2) . " &nbsp;mm." : "-"; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- บาร์โค้ดปรับขนาดให้พอดีช่อง ไม่ล้นกรอบขวา -->
    <script>
        JsBarcode("#barcode", "<?php echo $product_no; ?>", {
            format: "CODE128",
            width: 1.85,
            height: 35,
            displayValue: false,
            margin: 0
        });
    </script>
</body>
</html>