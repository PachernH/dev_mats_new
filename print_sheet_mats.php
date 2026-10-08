<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* --- 1. Page Setup --- */
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { margin: 0; padding: 0; font-family: 'Tahoma', sans-serif; background-color: #555; font-size: 20px; }

        .no-print {
            position: fixed; top: 20px; right: 20px; padding: 12px 24px;
            background: #28a745; color: white; border: none; border-radius: 5px;
            cursor: pointer; z-index: 1000; font-weight: bold; font-size: 16px;
        }

        .print-page {
            width: 210mm; height: 297mm; margin: 10mm auto; padding: 8mm;
            background: white; position: relative; overflow: hidden;
            display: flex; flex-direction: column;
        }

        @media print {
            body { background: none; }
            .no-print { display: none; }
            .print-page { margin: 0; width: 210mm; height: 297mm; }
        }

        /* --- 2. Border & Grid System --- */
        .main-table { 
            width: 100%; 
            border-collapse: collapse; 
            table-layout: fixed; 
        }
        
        .main-table td, .main-table th {
            border: 1px solid black; 
            padding: 5px 6px; 
            font-size: 20px; /* เพิ่มขนาดตัวอักษรหลักในตารางเป็น 20px */
            line-height: 1.15;
            vertical-align: middle;
            white-space: nowrap;  /* ล็อกให้อยู่บรรทัดเดียว */
            overflow: hidden;
        }

        .fit-text {
            display: inline-block;
            width: 100%;
            white-space: nowrap;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .lbl { font-weight: bold; }

        /* --- 3. Note Container & Blanking Process --- */
        .note-container {
            border: 1px solid black; 
            border-top: none;
            flex-grow: 1; 
            margin-bottom: 125px;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
        }

        .blanking-text {
            padding: 15px 10px;
            font-size: 32px; /* ขยายตัวอักษรเขียนมือ */
            color: #1b26a4;
            font-family: 'Segoe Print', 'Comic Sans MS', cursive;
            font-weight: bold;
        }

        .bottom-note {
            border-top: 1px solid black;
            padding: 8px;
            font-size: 19px; /* ขยายข้อความหมายเหตุล่างสุด */
            font-weight: bold;
            line-height: 1.25;
            white-space: normal;
        }

        /* --- 4. Footer Fixed Barcode & Inspection --- */
        .footer-fixed {
            position: absolute; bottom: 8mm; left: 8mm; right: 8mm;
            display: flex; border: 1px solid black; height: 115px; background: white;
        }
        
        .barcode-area { 
            flex: 0 0 52%; 
            border-right: 1px solid black; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            padding: 4px;
        }
        
        #barcode { 
            max-width: 100%; 
            height: auto; 
        }

        .inspect-area { 
            flex: 1; 
            display: flex; 
            align-items: flex-end; 
            justify-content: center; 
            padding-bottom: 12px; 
            font-weight: bold; 
            font-size: 24px; 
            text-align: center;
            letter-spacing: 1.5px;
        }
    </style>
</head>
<body>

    <button class="no-print" onclick="window.print()">Print the Label (Circle/Sheet Label)</button>

    <div class="print-page">
        <!-- Section 1: Main Product Details Table -->
        <table class="main-table">
            <colgroup>
                <col style="width: 22%;">
                <col style="width: 28%;">
                <col style="width: 22%;">
                <col style="width: 28%;">
            </colgroup>

            <!-- Header -->
            <tr>
                <td colspan="1" class="center bold" style="padding: 6px 2px; font-size: 17px;">CIRCLE OR<br>SHEET<br>PRODUCT<br>LABEL</td>
                <td colspan="2" class="center bold" style="font-size: 28px;">พื้นที่ติดเทปกาว</td>
                <td colspan="1" class="center bold" style="padding: 6px 2px; font-size: 17px;">CIRCLE OR<br>SHEET<br>PRODUCT<br>LABEL</td>
            </tr>

            <!-- Main Info -->
            <tr>
                <td class="lbl">PRODUCT NO.</td>
                <td id="productNo" class="bold"><span class="fit-text">P260604-1-05</span></td>
                <td class="lbl">PRINT DATE</td>
                <td class="bold"><span class="fit-text">06/04/2026 13:18</span></td>
            </tr>
            <tr>
                <td class="lbl">PROD. REF.</td>
                <td><span class="fit-text"></span></td>
                <td class="lbl">REQ. DATE</td>
                <td class="bold"><span class="fit-text">06/20/2026</span></td>
            </tr>
            <tr>
                <td class="lbl">COIL NO.</td>
                <td class="bold"><span class="fit-text">C260530-M-16</span></td>
                <td class="lbl">MATERIAL IN</td>
                <td class="bold"><span class="fit-text">BOI-MAT1</span></td>
            </tr>
            <tr>
                <td class="lbl">COIL REF.</td>
                <td class="bold"><span class="fit-text">C260527-1-01</span></td>
                <td class="lbl">CUSTOMER</td>
                <td class="bold"><span class="fit-text">MIL-AP / ORDR</span></td>
            </tr>
            <tr>
                <td class="lbl">SALE ORDER</td>
                <td class="bold"><span class="fit-text">SO-26-0393 : 001</span></td>
                <td class="lbl">JOB ORD. - TP</td>
                <td class="bold"><span class="fit-text">JB-26-2065 &nbsp; H18</span></td>
            </tr>
            <tr>
                <td class="lbl">PO NO.</td>
                <td class="bold"><span class="fit-text">72040430-020M : 001</span></td>
                <td class="lbl">ACT. WEIGH</td>
                <td class="bold right" style="font-size: 22px;"><span class="fit-text">945</span></td>
            </tr>
            <tr>
                <td class="lbl">WORK PROC.</td>
                <td class="bold"><span class="fit-text">BK&gt;IS</span></td>
                <td class="lbl">ACT. PIECE</td>
                <td class="bold right" style="font-size: 22px;"><span class="fit-text">2537</span></td>
            </tr>
        </table>

        <!-- Section 2: Material Specs Matrix -->
        <table class="main-table" style="border-top: none;">
            <colgroup>
                <col style="width: 10%;">
                <col style="width: 8%;">
                <col style="width: 7%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
                <col style="width: 10%;">
                <col style="width: 12%;">
                <col style="width: 14%;">
                <col style="width: 15%;">
            </colgroup>
            <tr class="center bold">
                <th>ALLOY</th>
                <th>TP</th>
                <th>G</th>
                <th>SG</th>
                <th>MG</th>
                <th>T5</th>
                <th>TH</th>
                <th>WIDTH</th>
                <th>LENGTH</th>
                <th>WT/PC</th>
            </tr>
            <tr class="center bold">
                <td><span class="fit-text">8011</span></td>
                <td><span class="fit-text">H18</span></td>
                <td><span class="fit-text">RAA</span></td>
                <td><span class="fit-text">SG3</span></td>
                <td><span class="fit-text">MG3</span></td>
                <td><span class="fit-text"></span></td>
                <td><span class="fit-text">2.400</span></td>
                <td><span class="fit-text">270.00</span></td>
                <td><span class="fit-text"></span></td>
                <td><span class="fit-text">0.3725</span></td>
            </tr>
            <tr>
                <td colspan="7" class="bold"><span class="fit-text">CUT SHEET SIZE FOR SHEAR OR STRETCHER</span></td>
                <td colspan="1"></td>
                <td colspan="1"></td>
                <td colspan="1"></td>
            </tr>
        </table>

        <!-- Section 3: Process Matrix (8 Columns Total) -->
        <table class="main-table" style="border-top: none;">
            <colgroup>
                <col style="width: 23%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
                <col style="width: 11%;">
            </colgroup>
            <tr>
                <td class="bold">PROCESS</td>
                <td class="center bold"><span class="fit-text">XYB</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">QTY. (KG)</td>
                <td class="right bold"><span class="fit-text">945</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">QTY. (PC)</td>
                <td class="right bold"><span class="fit-text">2537</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">PALLET</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">DATE</td>
                <td class="center bold"><span class="fit-text">06/04/2026</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="bold">OPERATOR</td>
                <td class="center bold"><span class="fit-text">PD-SUBCON</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <!-- Section 4: Note Area & Blanking Process Text -->
        <div class="note-container">
            <div class="bottom-note">
                G Roll 3.0 check CM/ No CM/ 8128 to 8011/MIL-AP&gt;385/270/275&gt;JB-26-1731/JB-26-2065_XYB
            </div>
            <div class="bottom-note">
                G Roll 3.0 check CM/ No CM/ 8128 to 8011/MIL-AP&gt;385/270/275&gt;JB-26-1731/JB-26-2065_XYB
            </div>
            <div class="bottom-note">
                G Roll 3.0 check CM/ No CM/ 8128 to 8011/MIL-AP&gt;385/270/275&gt;JB-26-1731/JB-26-2065_XYB
            </div>
        </div>

        <!-- Section 5: Barcode & Inspection Footer -->
        <div class="footer-fixed">
            <div class="barcode-area">
                <svg id="barcode"></svg>
            </div>
            <div class="inspect-area">
                INSPECTION
            </div>
        </div>
    </div>

    <script>
        function adjustFontSize() {
            const elements = document.querySelectorAll('.fit-text');
            elements.forEach(el => {
                const parent = el.parentElement;
                let fontSize = 20; // เพิ่มขนาดตัวอักษรตั้งต้นย่อเป็น 20px
                el.style.fontSize = fontSize + 'px';

                // ค่อยๆ ลดขนาดลงเฉพาะเมื่อข้อความเกินความกว้างกล่อง
                while (el.scrollWidth > parent.clientWidth - 6 && fontSize > 11) {
                    fontSize -= 0.5;
                    el.style.fontSize = fontSize + 'px';
                }
            });
        }

        window.onload = () => {
            adjustFontSize();

            const productNo = document.getElementById('productNo').innerText.trim();
            JsBarcode("#barcode", productNo, {
                format: "CODE128",
                width: 2.1, 
                height: 70,
                displayValue: true,
                fontSize: 25,
                fontOptions: "bold",
                margin: 0
            });
        };
    </script>
</body>
</html>