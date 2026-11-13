<?php
session_start();
include("dbcon_mats-new.php");

$product_no = trim($_GET['main_product_no'] ?? $_GET['product_no'] ?? '');

// รับค่า Sub Product 1 - 5 จากพารามิเตอร์ URL
$sub_1 = trim($_GET['sub_1'] ?? '');
$sub_2 = trim($_GET['sub_2'] ?? '');
$sub_3 = trim($_GET['sub_3'] ?? '');
$sub_4 = trim($_GET['sub_4'] ?? '');
$sub_5 = trim($_GET['sub_5'] ?? '');

if (empty($product_no)) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Product No. is required.</div>");
}

// ดึงข้อมูลสินค้าจาก CRSHPROD1 และคำสั่งซื้อ CSTMORDR1 ตาม Logic เดิม
$sql = "SELECT a.PRODUCT_NO, a.PRODUCT_ID, a.PRODUCT_MODEL, a.ALLOY, a.TEMPER, 
               a.THICKNESS, a.WIDTH, a.LENGTH, b.CSTMSPPL_ID, b.ORDER_CATEGORY
        FROM [MATS-NEWII].dbo.CRSHPROD1 AS a
        LEFT JOIN [MATS-NEWII].dbo.CSTMORDR1 AS b ON a.SALEORDER_NO = b.SALEORDER_NO
        WHERE a.PRODUCT_NO = :pno";

$stmt = $conn->prepare($sql);
$stmt->execute([':pno' => $product_no]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$d) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Product record not found: {$product_no}</div>");
}

$product_type   = strtoupper(trim($d['PRODUCT_MODEL'] ?? $d['PRODUCT_ID'] ?? 'SHEET'));
$product_alloy  = trim($d['ALLOY'] ?? '') . " - " . trim($d['TEMPER'] ?? '');
$customer_code  = trim($d['CSTMSPPL_ID'] ?? '');
$order_category = trim($d['ORDER_CATEGORY'] ?? 'ORDR');

// คำนวณมิติ Size (T x W x L เป็น mm)
$thk = number_format((float)$d['THICKNESS'], 3, '.', '');
$w   = number_format((float)$d['WIDTH'], 2, '.', '');
$l   = number_format((float)$d['LENGTH'], 2, '.', '');

if ((float)$d['LENGTH'] > 0 && in_array($d['PRODUCT_ID'], ['NC', 'SH'])) {
    $product_size = "{$thk} x {$w} x {$l}";
} else {
    $product_size = "{$thk} x {$w}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pre-pack Label - <?php echo htmlspecialchars($product_no); ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 10mm;
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

        .label-container {
            width: 280mm;
            height: 194mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 4mm 8mm;
        }

        /* Product No. ตัวใหญ่พิเศษด้านบนสุด */
        .pno-title {
            font-size: 120pt;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 12px;
            white-space: nowrap;
        }

        .layout-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .layout-table td {
            vertical-align: middle;
            border: none;
            padding: 8px 4px;
        }

        /* คอลัมน์ซ้าย: Sub Product No. 1 ถึง 5 */
        .sub-col {
            width: 32%;
            font-size: 40pt;
            /*font-weight: 800;*/
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 15px !important;
        }

        /* คอลัมน์กลาง: หัวข้อกำกับ */
        .lbl-col {
            width: 22%;
            font-size: 40pt;
            /*font-weight: 700;*/
            white-space: nowrap;
        }

        .colon-col {
            width: 4%;
            font-size: 28pt;
            font-weight: 900;
            text-align: center;
        }

        /* คอลัมน์ขวา: ค่าข้อมูล */
        .val-col {
            width: 42%;
            font-size: 40pt;
            /*font-weight: 800;*/
            white-space: nowrap;
        }

        /* Shipping Mark ตัวใหญ่หนา */
        .val-shipping {
            font-size: 100pt;
            font-weight: 900;
            letter-spacing: 1px;
            font-family: 'Arial Black', Arial, sans-serif;
            line-height: 1;
        }

        .toolbar {
            max-width: 280mm;
            margin: 8px auto;
            text-align: right;
        }
        .btn-print {
            background: #16a34a;
            color: #fff;
            padding: 10px 24px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-print:hover { background: #15803d; }

        @media print {
            .toolbar { display: none !important; }
            html, body {
                width: 297mm;
                height: 210mm;
            }
            .label-container {
                width: 280mm;
                height: 194mm;
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ PRINT PRE-PACK LABEL</button>
    </div>

    <div class="label-container">
        <!-- 1. Product No ด้านบนสุด -->
        <div class="pno-title"><?php echo htmlspecialchars($product_no); ?></div>

        <!-- 2. ตารางรวม Sub Product (ซ้าย) และ Spec Data (ขวา) -->
        <table class="layout-table">
            <!-- Row 1: Sub Product 1 | Shipping Mark -->
            <tr>
                <td class="sub-col"><?php echo htmlspecialchars($sub_1); ?></td>
                <td class="lbl-col" style="line-height: 1.1;">Shipping<br>Mark</td>
                <td class="colon-col">:</td>
                <td class="val-shipping"><?php echo htmlspecialchars($customer_code); ?></td>
            </tr>

            <!-- Row 2: Sub Product 2 | Product -->
            <tr>
                <td class="sub-col"><?php echo htmlspecialchars($sub_2); ?></td>
                <td class="lbl-col">Product</td>
                <td class="colon-col">:</td>
                <td class="val-col"><?php echo $product_type; ?></td>
            </tr>

            <!-- Row 3: Sub Product 3 | Alloy -->
            <tr>
                <td class="sub-col"><?php echo htmlspecialchars($sub_3); ?></td>
                <td class="lbl-col">Alloy</td>
                <td class="colon-col">:</td>
                <td class="val-col"><?php echo $product_alloy; ?></td>
            </tr>

            <!-- Row 4: Sub Product 4 | Size -->
            <tr>
                <td class="sub-col"><?php echo htmlspecialchars($sub_4); ?></td>
                <td class="lbl-col">Size</td>
                <td class="colon-col">:</td>
                <td class="val-col"><?php echo $product_size; ?></td>
            </tr>

            <!-- Row 5: Sub Product 5 | Order Category -->
            <tr>
                <td class="sub-col"><?php echo htmlspecialchars($sub_5); ?></td>
                <td class="lbl-col"></td>
                <td class="colon-col">:</td>
                <td class="val-col"><?php echo htmlspecialchars($order_category); ?></td>
            </tr>
        </table>
    </div>

</body>
</html>