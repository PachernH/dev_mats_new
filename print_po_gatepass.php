<?php
session_start();
require_once 'dbcon_mats-new.php';
$recordNum = trim($_GET['record_num'] ?? '');
$userOperate = $_SESSION['USER_NAME'] ?? $_SESSION['ID'] ?? 'SYSTEM';

$stmt = $conn->prepare("SELECT *, CONVERT(VARCHAR(10), DATERECEIVED, 103) AS DATE_FMT, CONVERT(VARCHAR(8), TIMERECEIVED, 108) AS TIME_FMT FROM PRCHRCRD1 WHERE RECORDNUM = ?");
$stmt->execute([$recordNum]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) die("ไม่พบข้อมูลเอกสาร {$recordNum}");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Gate Pass - <?= htmlspecialchars($recordNum) ?></title>
    <style>
        body { font-family: 'Sarabun', Tahoma, sans-serif; padding: 20px; font-size: 14px; }
        .form-container { max-width: 800px; margin: auto; border: 2px solid #000; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 8px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print" style="text-align:center; margin-bottom:10px;"><button onclick="window.print()">พิมพ์ใบผ่านประตู</button></div>
<div class="form-container">
    <div class="header">
        <h2 style="margin:0;">ใบผ่านประตูรับสินค้า (GATE PASS FORM)</h2>
        <p style="margin:5px 0 0 0;">เลขที่เอกสาร: <b><?= htmlspecialchars($row['RECORDNUM']) ?></b></p>
    </div>
    <table>
        <tr>
            <td width="50%"><b>วันที่รับเข้า:</b> <?= htmlspecialchars($row['DATE_FMT']) ?> เวลา <?= htmlspecialchars($row['TIME_FMT']) ?></td>
            <td width="50%"><b>เลขที่ใบสั่งซื้อ (PO):</b> <?= htmlspecialchars($row['PONUM']) ?></td>
        </tr>
        <tr>
            <td><b>ผู้ส่งสินค้า (Vendor):</b> <?= htmlspecialchars($row['VENDORNAME']) ?></td>
            <td><b>Invoice / Delivery No.:</b> <?= htmlspecialchars($row['INVOICENUM']) ?> / <?= htmlspecialchars($row['DELIVERYNUM']) ?></td>
        </tr>
    </table>
    <table>
        <thead>
            <tr style="background:#eee;">
                <th>รหัสสินค้า</th>
                <th>รายละเอียดสินค้า</th>
                <th width="15%">จำนวนรับ</th>
                <th width="12%">หน่วย</th>
                <th width="18%">คลังปลายทาง</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($row['ITEMNUM']) ?></td>
                <td><?= htmlspecialchars($row['DESCRIPTIONONPO']) ?></td>
                <td style="text-align:right;"><?= number_format($row['QTYRECEIVED'], 2) ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($row['UOP']) ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($row['RECEIVEWAREHOUSE']) ?></td>
            </tr>
        </tbody>
    </table>
    <p><b>หมายเหตุคลังสินค้า:</b> <?= htmlspecialchars($row['WAREHOUSECOMMENT']) ?></p>
    <table style="margin-top:40px; text-align:center;">
        <tr>
            <td width="33%" style="height:70px; vertical-align:bottom;">ผู้ส่งสินค้า<br>วันที่ ____/____/____</td>
            <td width="33%" style="vertical-align:bottom;">เจ้าหน้าที่คลังสินค้า (<?= htmlspecialchars($userOperate) ?>)<br>วันที่ ____/____/____</td>
            <td width="33%" style="vertical-align:bottom;">เจ้าหน้าที่รักษาความปลอดภัย<br>วันที่ ____/____/____</td>
        </tr>
    </table>
</div>
</body>
</html>