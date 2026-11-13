<?php
session_start();
include("dbcon_mats-new.php");

$product_no = trim($_GET['main_product_no'] ?? $_GET['product_no'] ?? '');

if (empty($product_no)) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Product No. is required.</div>");
}

// ดึง SALEORDER_NO และ SALEORDER_ITEM จาก PACKPROD1 (หรือ CRSHPROD1 กรณียังไม่ลง PACKPROD1)
// เพื่อนำไป Join ดึง CTM2_SPECIALLABEL1 - 4 จากตาราง CSTMORDR2
$sql = "SELECT COALESCE(p.SALEORDER_NO, c.SALEORDER_NO) AS SALEORDER_NO,
               COALESCE(p.SALEORDER_ITEM, c.SALEORDER_ITEM) AS SALEORDER_ITEM,
               so2.CTM2_SPECIALLABEL1,
               so2.CTM2_SPECIALLABEL2,
               so2.CTM2_SPECIALLABEL3,
               so2.CTM2_SPECIALLABEL4
        FROM [MATS-NEWII].dbo.CRSHPROD1 AS c
        LEFT JOIN [MATS-NEWII].dbo.PACKPROD1 AS p 
               ON c.PRODUCT_NO = p.PRODUCT_NO
        LEFT JOIN [MATS-NEWII].dbo.CSTMORDR2 AS so2 
               ON COALESCE(p.SALEORDER_NO, c.SALEORDER_NO) = so2.SALEORDER_NO 
              AND COALESCE(p.SALEORDER_ITEM, c.SALEORDER_ITEM) = so2.SALEORDER_ITEM
        WHERE c.PRODUCT_NO = :pno";

$stmt = $conn->prepare($sql);
$stmt->execute([':pno' => $product_no]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$d) {
    exit("<div style='font-family: sans-serif; padding: 20px; color: red;'>Product record not found: " . htmlspecialchars($product_no) . "</div>");
}

$label1 = trim($d['CTM2_SPECIALLABEL1'] ?? '');
$label2 = trim($d['CTM2_SPECIALLABEL2'] ?? '');
$label3 = trim($d['CTM2_SPECIALLABEL3'] ?? '');
$label4 = trim($d['CTM2_SPECIALLABEL4'] ?? '');

// ตรวจสอบว่ามีข้อมูล Special Label อย่างน้อย 1 บรรทัดหรือไม่
$has_special_label = ($label1 !== '' || $label2 !== '' || $label3 !== '' || $label4 !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Special Label - <?php echo htmlspecialchars($product_no); ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm 8mm;
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
            width: 281mm;
            height: 198mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            align-items: center;
            text-align: center;
            padding: 6mm 4mm;
        }

        .special-line {
            width: 100%;
            font-size: 50pt;
            font-weight: 400;
            line-height: 1.05;
            white-space: nowrap;
            overflow: hidden;
            min-height: 105pt;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* บรรทัดที่ 4 ตัวหนาพิเศษตามตัวอย่าง */
        .special-line-bold {
            font-weight: 900;
            font-family: 'Arial Black', Arial, Helvetica, sans-serif;
        }

        /* กรณีไม่มีข้อมูล Special Label */
        .empty-message-box {
            width: 100%;
            height: 80vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 16px;
        }

        .empty-message-text {
            font-size: 28px;
            font-weight: 700;
            color: #dc2626;
            background: #fef2f2;
            border: 2px solid #fecaca;
            padding: 20px 40px;
            border-radius: 10px;
        }

        .btn-close {
            background: #475569;
            color: #fff;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-close:hover { background: #334155; }

        .toolbar {
            max-width: 281mm;
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

        .btn-print:hover {
            background: #15803d;
        }

        @media print {
            .toolbar {
                display: none !important;
            }
            html, body {
                width: 297mm;
                height: 210mm;
            }
            .label-container {
                width: 281mm;
                height: 198mm;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<?php if (!$has_special_label): ?>
    <!-- กรณีไม่มีการแจ้งขอ Special Label -->
    <div class="empty-message-box">
        <div class="empty-message-text">No special label requested.</div>
        <button class="btn-close" onclick="window.close();">Close Window</button>
    </div>
<?php else: ?>
    <!-- กรณีมีข้อมูล Special Label -->
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ PRINT SPECIAL LABEL (A4)</button>
    </div>

    <div class="label-container">
        <div class="special-line"><?php echo htmlspecialchars($label1); ?></div>
        <div class="special-line"><?php echo htmlspecialchars($label2); ?></div>
        <div class="special-line"><?php echo htmlspecialchars($label3); ?></div>
        <div class="special-line special-line-bold"><?php echo htmlspecialchars($label4); ?></div>
    </div>
<?php endif; ?>

</body>
</html>