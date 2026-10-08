<?php
session_start();
if (!isset($_SESSION['ID'])) {
    exit('Unauthorized access');
}

include("dbcon_mats-new.php");

$rno = isset($_GET['rno']) ? trim($_GET['rno']) : '';

if (empty($rno)) {
    die("ไม่พบรหัส REQUEST_NO");
}

// Query ดึงข้อมูลจาก SQL
$sql = "SELECT REQUEST_NO, PRODUCT_NO, COIL_NO, PRODUCT_ID, ALLOY, TEMPER, GRADE, 
               SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, [LENGTH], 
               PRODUCT_WEIGHT, REMELT_REASON, RESPONS_TYPE 
        FROM PRODRMLT2 
        WHERE REQUEST_NO = :rno";

$stmt = $conn->prepare($sql);
$stmt->execute([':rno' => $rno]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_weight = 0;
foreach ($rows as $r) {
    $total_weight += (float)($r['PRODUCT_WEIGHT'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Preview - Product Remelt Requisition (<?php echo htmlspecialchars($rno); ?>)</title>
<style>
  @page {
    size: A4 landscape;
    margin: 5mm; /* ตั้ง margin รอบทิศทางเพื่อไม่ให้เส้นถูกตัด */
  }
  
  *, *:before, *:after {
    box-sizing: border-box !important;
  }

  html, body {
    height: 100%;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    background-color: #525659;
  }

  /* Toolbar */
  .preview-toolbar {
    width: 270mm;
    margin: 10px auto;
    background: #323639;
    padding: 8px 15px;
    border-radius: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
  }

  .toolbar-controls {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .zoom-select {
    padding: 4px 8px;
    font-size: 13px;
    border-radius: 4px;
    border: 1px solid #4b5563;
    background-color: #1f2937;
    color: #fff;
    cursor: pointer;
  }

  .btn-print {
    background-color: #2563eb;
    color: #fff;
    border: none;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
  }

  .btn-print:hover { background-color: #1d4ed8; }

  .btn-close {
    background-color: #4b5563;
    color: #fff;
    border: none;
    padding: 6px 14px;
    font-size: 13px;
    border-radius: 4px;
    cursor: pointer;
  }

  /* ปรับขนาดพื้นที่กระดาษให้พอดีเส้นขอบขวา */
  .paper-page {
    width: 270mm;
    height: 190mm;
    background: #fff;
    margin: 0 auto 20px auto;
    padding: 5mm;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    transform-origin: top center;
    transition: transform 0.15s ease;
  }

  .header-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 5px;
    flex-shrink: 0;
  }
  
  .company-title { font-size: 14px; font-weight: bold; }
  .doc-title { font-size: 13px; font-weight: bold; margin-top: 2px; }
  .meta-text { font-size: 10px; text-align: right; white-space: nowrap; }

  .table-container {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    width: 100%;
  }

  .main-table {
    width: 100%;
    height: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .main-table th, 
  .main-table td {
    border: 1px solid #000;
    padding: 3px;
    vertical-align: middle;
    word-wrap: break-word;
  }

  .main-table th {
    font-weight: bold;
    text-align: center;
    font-size: 10px;
    background-color: #f8fafc;
  }

  .center { text-align: center; }
  .right { text-align: right; }
  .bold { font-weight: bold; }

  .spacer-row td {
    height: 100%;
  }

  .footer-container {
    flex-shrink: 0;
    margin-top: 5px;
  }

  .remark-box {
    border: 1px solid #000;
    padding: 5px 8px;
    min-height: 42px;
    font-size: 10px;
  }

  .signature-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: -1px;
  }

  .signature-table td {
    border: 1px solid #000;
    width: 33.33%;
    padding: 10px 10px 6px 10px;
    vertical-align: bottom;
  }

  .sig-container {
    display: table;
    width: 100%;
    margin-bottom: 6px;
  }

  .sig-container:last-child {
    margin-bottom: 0;
  }

  .sig-label {
    display: table-cell;
    white-space: nowrap;
    font-size: 11px;
    padding-right: 5px;
    vertical-align: bottom;
  }

  .sig-dots {
    display: table-cell;
    width: 100%;
    border-bottom: 1px dotted #000;
    vertical-align: bottom;
  }

  /* ปรับปรุง CSS Print ให้เก็บขอบขวาครบทุกเส้น */
  @media print {
    html, body { 
      height: 100%;
      width: 100%;
      background-color: #fff; 
    }
    body { padding: 0; margin: 0; }
    .preview-toolbar { display: none !important; }
    .paper-page { 
      box-shadow: none; 
      padding: 0 !important; 
      width: 100% !important; 
      height: 100% !important;
      margin: 0 !important;
      transform: none !important;
      page-break-after: avoid;
      page-break-inside: avoid;
    }
  }
</style>
</head>
<body>

<div class="preview-toolbar">
  <div><b>Document Preview:</b> <?php echo htmlspecialchars($rno); ?></div>
  <div class="toolbar-controls">
    <label for="zoomSelect">Zoom: </label>
    <select id="zoomSelect" class="zoom-select" onchange="zoomPaper(this.value)">
      <option value="0.5">50%</option>
      <option value="1" selected>100%</option>
      <option value="1.5">150%</option>
      <option value="2">200%</option>
    </select>

    <button type="button" class="btn-print" onclick="window.print()">🖨️ Print document</button>
    <button type="button" class="btn-close" onclick="window.close()">Close</button>
  </div>
</div>

<div class="paper-page" id="paperPage">
  <!-- ส่วนหัวกระดาษ -->
  <table class="header-table">
    <tr>
      <td style="width: 65%;">
        <div class="company-title">MEYER ALUMINIUM (THAILAND) CO.,LTD.</div>
        <div class="doc-title">PRODUCT REMELT REQUISITION</div>
      </td>
      <td style="width: 35%;" class="meta-text">
        REQUEST NO. : <b><?php echo htmlspecialchars($rno); ?></b><br>
        PRINT DATE : <?php echo date('m/d/Y'); ?><br>
        PAGE NO. : Page 1 of 1
      </td>
    </tr>
  </table>

  <!-- ตารางหลักยืดเต็มหน้า -->
  <div class="table-container">
    <table class="main-table">
      <thead>
        <tr>
          <th style="width: 4%;">ITEM</th>
          <th style="width: 12%;">PRODUCT NO.</th>
          <th style="width: 12%;">COIL REFERENCE</th>
          <th style="width: 4%;">PD.</th>
          <th style="width: 5%;">A<br>TP.</th>
          <th style="width: 5%;">SG.<br>MG.</th>
          <th style="width: 5%;">TH.</th>
          <th style="width: 7%;">WIDTH<br>LENGTH</th>
          <th style="width: 8%;">QTY.<br>WEIGHT</th>
          <th style="width: 10%;">RESPONSIBLE</th>
          <th style="width: 22%;">REASON OF REMELT</th>
          <th style="width: 6%;">RESULT</th>
        </tr>
        <tr>
          <th colspan="3" style="text-align: left; padding-left: 15px;">CUSTOMER</th>
          <th colspan="9"></th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $item = 1;
        $remark_reasons = [];
        if (!empty($rows)):
          foreach ($rows as $row): 
            if (!empty($row['REMELT_REASON'])) {
                $remark_reasons[] = $row['REMELT_REASON'];
            }
        ?>
            <tr>
              <td class="center"><?php echo $item++; ?></td>
              <td><?php echo htmlspecialchars($row['PRODUCT_NO'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['COIL_NO'] ?? ''); ?></td>
              <td class="center"><?php echo htmlspecialchars($row['PRODUCT_ID'] ?? ''); ?></td>
              <td class="center">
                <?php echo htmlspecialchars($row['ALLOY'] ?? ''); ?><br>
                <?php echo htmlspecialchars($row['TEMPER'] ?? ''); ?>
              </td>
              <td class="center">
                <?php echo htmlspecialchars($row['SURFACE_GRADE'] ?? ''); ?><br>
                <?php echo htmlspecialchars($row['METALLURGICAL_GRADE'] ?? ''); ?>
              </td>
              <td class="right"><?php echo number_format((float)($row['THICKNESS'] ?? 0), 2); ?></td>
              <td class="right">
                <?php echo number_format((float)($row['WIDTH'] ?? 0), 3); ?><br>
                <?php echo !empty($row['LENGTH']) ? number_format((float)$row['LENGTH'], 3) : ''; ?>
              </td>
              <td class="right"><?php echo number_format((float)($row['PRODUCT_WEIGHT'] ?? 0), 2); ?></td>
              <td><?php echo htmlspecialchars($row['RESPONS_TYPE'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['REMELT_REASON'] ?? ''); ?></td>
              <td></td>
            </tr>
          <?php endforeach; 
        else: ?>
          <tr>
            <td colspan="12" class="center">ไม่พบข้อมูลสำหรับ Request No. นี้</td>
          </tr>
        <?php endif; ?>

        <tr class="spacer-row">
          <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>

        <tr>
          <td colspan="8" class="bold">TOTAL</td>
          <td class="right bold"><?php echo number_format($total_weight, 2); ?></td>
          <td colspan="3"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- ส่วนท้ายหน้ากระดาษ -->
  <div class="footer-container">
    <div class="remark-box">
      <b>REMARK :</b><br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
      <?php echo htmlspecialchars(implode(', ', array_unique($remark_reasons))); ?>
    </div>

    <table class="signature-table">
      <tr>
        <td>
          <div class="sig-container">
            <span class="sig-label">ISSUED BY :</span>
            <span class="sig-dots"></span>
          </div>
          <div class="sig-container">
            <span class="sig-label">DATE :</span>
            <span class="sig-dots"></span>
          </div>
        </td>
        <td>
          <div class="sig-container">
            <span class="sig-label">CHECKED BY :</span>
            <span class="sig-dots"></span>
          </div>
          <div class="sig-container">
            <span class="sig-label">DATE :</span>
            <span class="sig-dots"></span>
          </div>
        </td>
        <td>
          <div class="sig-container">
            <span class="sig-label">APPROVED BY :</span>
            <span class="sig-dots"></span>
          </div>
          <div class="sig-container">
            <span class="sig-label">DATE :</span>
            <span class="sig-dots"></span>
          </div>
        </td>
      </tr>
    </table>
  </div>
</div>

<script>
  function zoomPaper(scale) {
    const paper = document.getElementById('paperPage');
    paper.style.transform = `scale(${scale})`;
  }
</script>

</body>
</html>