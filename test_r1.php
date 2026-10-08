<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Product Remelt Requisition</title>
<style>
  @page {
    size: A4 landscape;
    margin: 8mm;
  }
  
  body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    color: #000;
    margin: 0;
    padding: 0;
    background-color: #fff;
  }

  .report-container {
    width: 100%;
    box-sizing: border-box;
  }

  /* Header Section */
  .header-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 5px;
  }
  
  .company-title {
    font-size: 14px;
    font-weight: bold;
  }

  .doc-title {
    font-size: 14px;
    font-weight: bold;
    margin-top: 2px;
  }

  .meta-text {
    font-size: 11px;
    text-align: right;
    white-space: nowrap;
  }

  /* Main Table */
  .main-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .main-table th, 
  .main-table td {
    border: 1px solid #000;
    padding: 3px 4px;
    vertical-align: top;
    word-wrap: break-word;
  }

  .main-table th {
    font-weight: normal;
    text-align: center;
    font-size: 10px;
    background-color: #fff;
  }

  .center { text-align: center; }
  .right { text-align: right; }
  .bold { font-weight: bold; }

  .customer-row {
    font-size: 10px;
    color: #333;
  }

  /* Footer & Signatures */
  .remark-box {
    border: 1px solid #000;
    border-top: none;
    padding: 5px;
    min-height: 180px;
    font-size: 11px;
  }

  .signature-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: -1px; /* Join borders */
  }

  .signature-table td {
    border: 1px solid #000;
    width: 33.33%;
    padding: 15px 10px 10px 10px;
    vertical-align: bottom;
  }

  .sig-line {
    display: inline-block;
    width: 170px;
    border-bottom: 1px solid #000;
    margin-left: 5px;
  }

  .sig-row {
    margin-bottom: 8px;
  }

  /* Print Styles */
  @media print {
    body { -webkit-print-color-adjust: exact; }
  }
</style>
</head>
<body>

<div class="report-container">
  <!-- Header -->
  <table class="header-table">
    <tr>
      <td style="width: 70%;">
        <div class="company-title">MEYER ALUMINIUM (THAILAND) CO.,LTD.</div>
        <div class="doc-title">PRODUCT REMELT REQUISITION</div>
      </td>
      <td style="width: 30%;" class="meta-text">
        REQUEST NO. : <b>RM-26-0033</b><br>
        PRINT DATE : 07/15/2026<br>
        PAGE NO. : Page 1 of 1
      </td>
    </tr>
  </table>

  <!-- Data Table -->
  <table class="main-table">
    <thead>
      <tr>
        <th style="width: 3%;">ITEM</th>
        <th style="width: 12%;">PRODUCT NO.</th>
        <th style="width: 12%;">COIL REFERENCE</th>
        <th style="width: 3%;">PD.</th>
        <th style="width: 4%;">A<br>TP.</th>
        <th style="width: 4%;">SG.<br>MG.</th>
        <th style="width: 5%;">TH.</th>
        <th style="width: 6%;">WIDTH<br>LENGTH</th>
        <th style="width: 7%;">QTY.<br>WEIGHT</th>
        <th style="width: 10%;">RESPONSIBLE</th>
        <th style="width: 28%;">REASON OF REMELT</th>
        <th style="width: 6%;">RESULT</th>
      </tr>
      <tr>
        <th colspan="3" style="text-align: left; padding-left: 20px;">CUSTOMER</th>
        <th colspan="9"></th>
      </tr>
    </thead>
    <tbody>
      <!-- Row 1 -->
      <tr>
        <td class="center">1</td>
        <td>C241016-1-05</td>
        <td>C241016-1-05</td>
        <td class="center">CO</td>
        <td class="center">1100<br>MO</td>
        <td class="center">SG3<br>MG3</td>
        <td class="right">5.50</td>
        <td class="right">1552.000</td>
        <td class="right">1,458.00</td>
        <td>FG. STOCK</td>
        <td></td>
        <td></td>
      </tr>
      <tr class="customer-row">
        <td></td>
        <td colspan="2" class="bold">MEYER ALUMINIUM LIMITED</td>
        <td colspan="9"></td>
      </tr>

      <!-- Row 2 -->
      <tr>
        <td class="center">2</td>
        <td>P220210-1-11</td>
        <td></td>
        <td class="center">CC</td>
        <td class="center">1002<br>MO</td>
        <td class="center">SGR<br>MG6</td>
        <td class="right">7.00</td>
        <td class="right">150.000</td>
        <td class="right">581.00</td>
        <td>FG. STOCK</td>
        <td></td>
        <td></td>
      </tr>
      <tr class="customer-row">
        <td></td>
        <td colspan="2" class="bold">MEYER INDUSTRIES LIMITED (SS)</td>
        <td colspan="9"></td>
      </tr>

      <!-- Row 3 -->
      <tr>
        <td class="center">3</td>
        <td>P260702-3-05</td>
        <td></td>
        <td class="center">CC</td>
        <td class="center">1100<br>H18</td>
        <td class="center">SG3<br>MG1</td>
        <td class="right">0.90</td>
        <td class="right">260.000</td>
        <td class="right">550.00</td>
        <td>PROD. BLANK</td>
        <td>Thickness is not specified.</td>
        <td></td>
      </tr>
      <tr class="customer-row">
        <td></td>
        <td colspan="2" class="bold">ATTA INDUSTRIES CO., LTD.</td>
        <td colspan="9"></td>
      </tr>

      <!-- Total Row -->
      <tr>
        <td colspan="8" class="bold">TOTAL</td>
        <td class="right bold">2,589.00</td>
        <td colspan="3"></td>
      </tr>
    </tbody>
  </table>

  <!-- Remark Box -->
  <div class="remark-box">
    <b>REMARK :</b><br>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Thickness is not specified.
  </div>

  <!-- Signatures -->
  <table class="signature-table">
    <tr>
      <td>
        <div class="sig-row">ISSUED BY : <span class="sig-line"></span></div>
        <div>DATE : <span class="sig-line"></span></div>
      </td>
      <td>
        <div class="sig-row">CHECKED BY : <span class="sig-line"></span></div>
        <div>DATE : <span class="sig-line"></span></div>
      </td>
      <td>
        <div class="sig-row">APPROVED BY : <span class="sig-line"></span></div>
        <div>DATE : <span class="sig-line"></span></div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>