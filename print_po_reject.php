<?php
session_start();
require_once 'dbcon_mats-new.php';
$recordNum = trim($_GET['record_num'] ?? '');
$userOperate = $_SESSION['USER_NAME'] ?? $_SESSION['ID'] ?? 'SYSTEM';

$stmt = $conn->prepare("SELECT *, CONVERT(VARCHAR(10), DATERECEIVED, 103) AS DATE_FMT FROM PRCHRCRD1 WHERE RECORDNUM = ?");
$stmt->execute([$recordNum]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) die("ไม่พบข้อมูลเอกสาร {$recordNum}");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Reject Notice - <?= htmlspecialchars($recordNum) ?></title>
    <style>
        body { font-family: 'Sarabun', Tahoma, sans-serif; padding: 20px; font-size: 14px; }
        .form-container { max-width: 800px; margin: auto; border: 2px solid #b00; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 8px; }
        .header { text-align: center; border-bottom: 2px solid #b00; padding-bottom: 10px; color: #b00; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
<div class="no-print" style="text-align:center; margin-bottom:10px;"><button onclick="window.print()">พิมพ์ใบแจ้งคืนสินค้า</button></div>
<div class="form-container">
    <div class="header">
        <h2 style="margin:0;">ใบแจ้งปฏิเสธการรับสินค้า (MATERIAL REJECT NOTICE)</h2>
        <p style="margin:5px 0 0 0; color:#000;">เลขที่เอกสาร: <b><?= htmlspecialchars($row['RECORDNUM']) ?></b> | วันที่: <?= htmlspecialchars($row['DATE_FMT']) ?></p>
    </div>
    <table>
        <tr>
            <td width="50%"><b>เลขที่ PO:</b> <?= htmlspecialchars($row['PONUM']) ?> (Seq: <?= htmlspecialchars($row['SEQNUM']) ?>)</td>
            <td width="50%"><b>ผู้ขาย (Vendor):</b> <?= htmlspecialchars($row['VENDORNAME']) ?></td>
        </tr>
        <tr>
            <td><b>Invoice No.:</b> <?= htmlspecialchars($row['INVOICENUM']) ?></td>
            <td><b>Delivery No.:</b> <?= htmlspecialchars($row['DELIVERYNUM']) ?></td>
        </tr>
    </table>
    <table>
        <thead>
            <tr style="background:#ffe6e6;">
                <th>รหัสสินค้า</th>
                <th>รายละเอียดสินค้า</th>
                <th width="15%">จำนวน</th>
                <th width="12%">หน่วย</th>
                <th width="20%">รหัสสาเหตุ (Reason)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($row['ITEMNUM']) ?></td>
                <td><?= htmlspecialchars($row['DESCRIPTIONONPO']) ?></td>
                <td style="text-align:right;"><?= number_format($row['QTYRECEIVED'], 2) ?></td>
                <td style="text-align:center;"><?= htmlspecialchars($row['UOP']) ?></td>
                <td style="text-align:center; font-weight:bold; color:#b00;"><?= htmlspecialchars($row['RECEIVEDREASON']) ?></td>
            </tr>
        </tbody>
    </table>
    <div style="margin-top:15px; border:1px solid #000; padding:10px;">
        <p style="margin:0 0 5px 0;"><b>หมายเหตุคลังสินค้า:</b> <?= htmlspecialchars($row['WAREHOUSECOMMENT']) ?></p>
        <p style="margin:0 0 5px 0;"><b>หมายเหตุผู้ขอซื้อ:</b> <?= htmlspecialchars($row['ENDUSERCOMMENT']) ?></p>
        <p style="margin:0;"><b>หมายเหตุจัดซื้อ:</b> <?= htmlspecialchars($row['PURCHASECOMMENT']) ?></p>
    </div>
    <table style="margin-top:40px; text-align:center;">
        <tr>
            <td width="33%" style="height:70px; vertical-align:bottom;">ผู้ตรวจสอบคลังสินค้า (<?= htmlspecialchars($userOperate) ?>)<br>วันที่ ____/____/____</td>
            <td width="33%" style="vertical-align:bottom;">ผู้ขอซื้อ / หน่วยงานควบคุมคุณภาพ<br>วันที่ ____/____/____</td>
            <td width="33%" style="vertical-align:bottom;">ฝ่ายจัดซื้อ / ผู้รับคืนสินค้า<br>วันที่ ____/____/____</td>
        </tr>
    </table>
</div>
</body>
</html>