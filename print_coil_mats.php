<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* --- 1. A4 Setup --- */
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { margin: 0; padding: 0; font-family: 'Tahoma', sans-serif; background-color: #555; font-size: 20px; }

        .no-print {
            position: fixed; top: 20px; right: 20px; padding: 12px 24px;
            background: #28a745; color: white; border: none; border-radius: 5px;
            cursor: pointer; z-index: 1000; font-weight: bold;
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

        /* --- 2. Table & Content (Font 20px) --- */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: -1px; }
        td, th {
            border: 1px solid black; padding: 3px 6px; font-size: 20px;
            word-wrap: break-word; line-height: 1.1;
        }

        .center { text-align: center; }
        .bold { font-weight: bold; }
        .label { width: 22%; background-color: #f2f2f2; font-weight: bold; }
        .val { width: 28%; }

        .header-grid { display: grid; grid-template-columns: 1fr 2fr 1fr; border-top: 1px solid black; }
        .header-box { border: 1px solid black; border-top: none; padding: 5px; display: flex; align-items: center; justify-content: center; text-align: center; }
        .sticky-area { font-size: 24px; font-weight: bold; }

        .work-proc { border: 1px solid black; border-top: none; padding: 6px; font-weight: bold; font-size: 19px; }

        .note-container {
            border: 1px solid black; border-top: none; padding: 8px;
            flex-grow: 1; margin-bottom: 140px; /* เพิ่มระยะห่างกันพลาด */
            font-size: 18px; line-height: 1.2;
        }

        /* --- 3. Footer Barcode & Inspection (Fixed Area) --- */
        .footer-fixed {
            position: absolute; bottom: 8mm; left: 8mm; right: 8mm;
            display: flex; border: 1px solid black; height: 115px; background: white;
        }
        
        .barcode-area { 
            flex: 0 0 65%; /* ล็อกความกว้าง Barcode ไว้ที่ 65% */
            border-right: 1px solid black; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; /* ป้องกัน Barcode ล้นไปเบียดช่องขวา */
        }
        
        #barcode { 
            max-width: 100%; 
            height: auto; 
        }

        .inspect-area { 
            flex: 1; /* พื้นที่ที่เหลือเป็นของ Inspection */
            display: flex; 
            align-items: flex-end; 
            justify-content: center; 
            padding-bottom: 15px; 
            font-weight: bold; 
            font-size: 20px; 
            text-align: center;
        }
    </style>
</head>
<body>

    <button class="no-print" onclick="window.print()">Print the Label (Fixed Inspection)</button>

    <div class="print-page">
        <div class="header-grid">
            <div class="header-box bold">COIL<br>PRODUCT<br>LABEL</div>
            <div class="header-box sticky-area">พื้นที่ติดเทปกาว</div>
            <div class="header-box bold">COIL<br>PRODUCT<br>LABEL</div>
        </div>

        <table>
            <tr>
                <td class="label">COIL NO.</td><td id="coilNo" class="val bold">C231125-T-03</td>
                <td class="label">STATUS</td><td class="val bold center" style="background:#eee;">ACCEPT</td>
            </tr>
            <tr>
                <td class="label">COIL REF. 1</td><td>C231124-M-12</td>
                <td class="label">PRINT DATE</td><td>03/23/2026 17:24</td>
            </tr>
            <tr>
                <td class="label">COIL REF. 2</td><td>C231012-R-10</td>
                <td class="label">REQ. DATE</td><td>11/22/2023</td>
            </tr>
            <tr>
                <td class="label">COIL REF. 3</td><td>CP23-002695</td>
                <td class="label">MATERIAL IN</td><td>BOI-IMP1</td>
            </tr>
            <tr>
                <td class="label">JOB ORDER</td><td>JB-23-5839</td>
                <td class="label">COIL FROM</td><td>MAL</td>
            </tr>
            <tr>
                <td class="label">JOB TEMPER</td><td>H14</td>
                <td class="label">COIL SLIT</td><td>3 STAND</td>
            </tr>
        </table>

        <div class="work-proc">WORK PROC. &nbsp; RC > IS > CM > I2 > SS > WS</div>

        <table>
            <tr class="center bold" style="background:#f9f9f9;">
                <th>ALLOY</th><th>TP</th><th>G</th><th>SG</th><th>MG</th><th>T5</th><th>TH</th><th>WIDTH</th>
            </tr>
            <tr class="center bold">
                <td>1050</td><td>H14</td><td>BA</td><td>SG1</td><td>MG3</td><td></td><td>0.500</td><td>275.00</td>
            </tr>
            <tr class="center">
                <td colspan="6">ACTUAL THICKNESS / WIDTH</td><td class="bold">0.000</td><td class="bold">275.00</td>
            </tr>
            <tr class="center">
                <td colspan="6">CUST. MAX/MIN THICKNESS TOLERANCE</td><td class="bold">0.530</td><td class="bold">0.470</td>
            </tr>
        </table>

        <table>
            <tr class="center bold" style="background:#f9f9f9;">
                <th>PROCESS</th><th>TH</th><th>TP</th><th>QTY.</th><th>PRO. DATE</th><th>OPERATOR</th>
            </tr>
            <tbody id="process-rows"></tbody>
            <tr>
                <td colspan="3" class="bold center">COIL BALANCE WT.</td>
                <td colspan="3" class="bold center" style="font-size:24px;">313</td>
            </tr>
        </table>

        <div class="note-container">
            <b>NOTE:</b><br>
            TTEI->O->CM->0.5H14->SWS->AP1_THK.TOL+/-0.03 MM*AQ_USE_NEW_ROLL*<br>
            COIL HK[CT:0.70_CW:1150_L-COIL_T5-NIL_A-SLAB]<br>
            JB-23-4714/JB-23-5297/JB-23-4717/JB-23-4718<br>
            TTEI->BA->O->CM>0.5H14->SWS>JB-23-4714/JB-23-5297/JB-23-4717/JB-23
        </div>

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
        const processes = [
            { name: "FIRST", th: "3.500", tp: "H18", qty: "3485", date: "10/12/2023", op: "WH-SOMPHOP" },
            { name: "BATCH", th: "", tp: "", qty: "", date: "", op: "" },
            { name: "CRM.", th: "0.500", tp: "H14", qty: "3485", date: "11/24/2023", op: "PD-SOONTHO" }
        ];

        window.onload = () => {
            const container = document.getElementById('process-rows');
            container.innerHTML = processes.map(p => `
                <tr>
                    <td>${p.name}</td>
                    <td class="center">${p.th}</td>
                    <td class="center">${p.tp}</td>
                    <td class="center">${p.qty}</td>
                    <td class="center">${p.date}</td>
                    <td style="font-size:16px;">${p.op}</td>
                </tr>
            `).join('');

            const coilNo = document.getElementById('coilNo').innerText;
            // ลดความกว้าง Barcode (width) ลงเหลือ 1.8 เพื่อคืนพื้นที่ให้ Inspection
            JsBarcode("#barcode", coilNo, {
                format: "CODE128",
                width: 1.8, 
                height: 45,
                displayValue: true,
                fontSize: 18,
                fontOptions: "bold",
                margin: 0
            });
        };
    </script>
</body>
</html>