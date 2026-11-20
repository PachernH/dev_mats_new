<?php
session_start();
require_once 'dbcon_mats-new.php';
$conn_mats = $conn; unset($conn);
require_once 'dbcon_mp2_mats.php';
$conn_mp2 = $conn; unset($conn);

$recordNum = trim($_GET['record_num'] ?? '');
$formSize  = $_GET['form'] ?? '7x5';
$copy      = max(1, intval($_GET['copy'] ?? 1));
$container = trim($_GET['container'] ?? '');

$stmt = $conn_mats->prepare("SELECT *, CONVERT(VARCHAR(10), DATERECEIVED, 103) AS PO_DATE FROM PRCHRCRD1 WHERE RECORDNUM = ?");
$stmt->execute([$recordNum]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) die("ไม่พบข้อมูลเอกสาร {$recordNum}");

$stmtWh = $conn_mp2->prepare("SELECT ABCCLASS, REORDERPOINT, REORDERQTY FROM WAREHOUSEINFO WHERE ITEMNUM = ? AND WAREHOUSEID = ?");
$stmtWh->execute([$row['ITEMNUM'], $row['RECEIVEWAREHOUSE']]);
$wh = $stmtWh->fetch(PDO::FETCH_ASSOC) ?: ['ABCCLASS' => '', 'REORDERPOINT' => '', 'REORDERQTY' => ''];

$poNum = trim($row['PONUM'] ?? '');
if ($poNum === '') {
    $barcode = ($container === '') ? $recordNum : "PO0000000:" . $container;
} else {
    $barcode = ($container === '') ? $poNum : $poNum . ":" . $container;
}

$width  = ($formSize === '10x15') ? '15cm' : '7cm';
$height = ($formSize === '10x15') ? '10cm' : '5cm';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Print Label - <?= htmlspecialchars($recordNum) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        @page { size: <?= $width ?> <?= $height ?>; margin: 0; }
        body { font-family: Tahoma, sans-serif; margin: 0; padding: 0; }
        .label-box {
            width: <?= $width ?>; height: <?= $height ?>;
            box-sizing: border-box; padding: 4mm;
            border: 1px dashed #999; page-break-after: always;
            display: flex; flex-direction: column; justify-content: space-between;
            font-size: <?= ($formSize === '10x15') ? '14px' : '10px' ?>;
        }
        @media print { .label-box { border: none; } .no-print { display: none; } }
        .row-flex { display: flex; justify-content: space-between; }
        .bold { font-weight: bold; }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="padding:10px; background:#eee;">
        <button onclick="window.print()">สั่งพิมพ์อีกครั้ง</button>
    </div>
    <?php for ($i = 1; $i <= $copy; $i++): ?>
    <div class="label-box">
        <div class="row-flex">
            <span class="bold">ITEM: <?= htmlspecialchars($row['ITEMNUM']) ?></span>
            <span class="bold">ABC: <?= htmlspecialchars($wh['ABCCLASS']) ?></span>
        </div>
        <div class="bold" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?= htmlspecialchars($row['DESCRIPTIONONPO']) ?>
        </div>
        <div class="row-flex">
            <span>WH: <b><?= htmlspecialchars($row['RECEIVEWAREHOUSE']) ?></b></span>
            <span>DEST: <b><?= htmlspecialchars($row['DESTID']) ?></b></span>
            <span>UOP: <b><?= htmlspecialchars($row['UOP']) ?></b></span>
        </div>
        <div class="row-flex">
            <span>ROP: <?= htmlspecialchars($wh['REORDERPOINT']) ?></span>
            <span>ROQ: <?= htmlspecialchars($wh['REORDERQTY']) ?></span>
            <span>DATE: <?= htmlspecialchars($row['PO_DATE']) ?></span>
        </div>
        <div style="text-align:center;">
            <svg class="barcode" jsvalue-data="<?= htmlspecialchars($barcode) ?>"></svg>
        </div>
    </div>
    <?php endfor; ?>
    <script>
        document.querySelectorAll('.barcode').forEach(el => {
            JsBarcode(el, el.getAttribute('jsvalue-data'), {
                format: "CODE128",
                width: <?= ($formSize === '10x15') ? 2 : 1.3 ?>,
                height: <?= ($formSize === '10x15') ? 45 : 28 ?>,
                fontSize: <?= ($formSize === '10x15') ? 13 : 10 ?>,
                margin: 2
            });
        });
    </script>
</body>
</html>