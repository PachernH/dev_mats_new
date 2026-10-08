<?php
session_start();
include("dbcon_mats-new.php");

// 1. รับค่า Parameter
$product_no = $_GET['productno'] ?? $_GET['p'] ?? '';
$job_order  = $_GET['joborder']  ?? $_GET['j'] ?? '';

if (empty($product_no)) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Error: ไม่พบรหัส PRODUCT NO.</h3>");
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// 2. ดึงข้อมูลหลัก (Main Query)
$sqlMain = "
SELECT TOP(1)
    A.PRODUCT_NO, A.COIL_NO, A.PRODUCT_REFERENCE, A.SALEORDER_NO, A.SALEORDER_ITEM, 
    A.JOB_ORDER, A.MATERIAL_IN, A.CRSH_WORKPROCESS, A.ALLOY, A.TEMPER, A.GRADE, 
    A.SURFACE_GRADE, A.METALLURGICAL_GRADE, A.T5_TEMPERATURE, A.THICKNESS, A.WIDTH, 
    A.LENGTH, A.WEIGHT_PIECE, A.CUT_WIDTH, A.CUT_LENGTH, A.CUT_WEIGHTPIECE, 
    A.CRSH_ACTUALPIECE, A.CRSH_ACTUALWEIGHT, A.CRSH_PRODUCEWEIGHT, A.CRSH_PRODUCEPIECE, 
    A.CRSH_TOPWEIGHT, A.CRSH_BOTTOMWEIGHT, A.CRSH_ENDDATE, A.CRSH_OPERATOR1, 
    A.CRSH_LABEL, A.CRSH_STATUS, B.SCHEDULE_DATE, B.TEMPER AS JOB_TEMPER, 
    B.JOB_REMARK, B.PRD_REMARK1, B.PRD_REMARK2, C.CTM2_PONO, C.CTM2_POITEM, 
    D.CSTMSPPL_ID, D.ORDER_CATEGORY
FROM CRSHPROD1 AS A 
LEFT JOIN JOBORDER1 AS B ON A.JOB_ORDER = B.JOB_ORDER
LEFT JOIN CSTMORDR2 AS C ON B.SALEORDER_NO = C.SALEORDER_NO AND B.SALEORDER_ITEM = C.SALEORDER_ITEM
LEFT JOIN CSTMORDR1 AS D ON C.SALEORDER_NO = D.SALEORDER_NO
  WHERE A.PRODUCT_NO = :product_no
  AND (A.CRSH_STATUS = 'OP' OR A.CRSH_STATUS = 'AC' OR A.CRSH_STATUS = 'RJ' OR A.CRSH_STATUS = 'RM')
";

if (!empty($job_order)) {
    $sqlMain .= " AND A.JOB_ORDER = :job_order ";
}
$sqlMain .= " ORDER BY A.CRSH_ENDDATE DESC";

$stmtMain = $conn->prepare($sqlMain);
$paramsMain = [':product_no' => $product_no];
if (!empty($job_order)) {
    $paramsMain[':job_order'] = $job_order;
}
$stmtMain->execute($paramsMain);
$mainData = $stmtMain->fetch(PDO::FETCH_ASSOC);

if (!$mainData) {
   //die("<h3 style='color:red; text-align:center; margin-top:50px;'>Error: ไม่พบข้อมูล Label</h3>");
   die("<h3 style='color:red; text-align:center; margin-top:50px;'>ไม่พบข้อมูล Product No: " . htmlspecialchars($product_no) . "</h3>"); 
}

// =========================================================================
// 3. ดึง Parent Coil (สาวหาคอยล์แม่ต้นทางด้วย Recursive CTE + หยุดเมื่อเจอ -R-)
// =========================================================================
$parent_coil = '';

if (!empty($mainData['COIL_NO'])) {
    try {
        $sqlParent = "
            WITH CoilTree AS (
                -- 1. เริ่มจาก Coil ปัจจุบัน
                SELECT 
                    COIL_NO, 
                    PRODUCT_REFERENCE, 
                    1 AS Level
                FROM COILPROD1
                WHERE COIL_NO = :coil_no

                UNION ALL

                -- 2. วนลูปสาวไปหา PRODUCT_REFERENCE ทีละชั้น
                SELECT 
                    c.COIL_NO, 
                    c.PRODUCT_REFERENCE, 
                    t.Level + 1
                FROM COILPROD1 c
                INNER JOIN CoilTree t ON c.COIL_NO = t.PRODUCT_REFERENCE
                WHERE c.PRODUCT_REFERENCE IS NOT NULL 
                  AND LTRIM(RTRIM(c.PRODUCT_REFERENCE)) <> ''
                  AND c.PRODUCT_REFERENCE <> c.COIL_NO
                  -- หยุดสาวลูปขึ้นไปถ้าคอยล์ก่อนหน้า (ตัวแม่ในชั้นก่อน) เป็น -R- แล้ว
                  AND t.COIL_NO NOT LIKE '%-R-%'
            )
            -- 3. เลือก PRODUCT_REFERENCE จากชั้นลึกที่สุด
            SELECT TOP(1) 
                CASE 
                    WHEN LTRIM(RTRIM(PRODUCT_REFERENCE)) <> '' THEN PRODUCT_REFERENCE 
                    ELSE COIL_NO 
                END AS PARENT_REF
            FROM CoilTree 
            ORDER BY Level DESC
        ";

        $stmtParent = $conn->prepare($sqlParent);
        $stmtParent->execute([':coil_no' => $mainData['COIL_NO']]);
        $parentRow = $stmtParent->fetch(PDO::FETCH_ASSOC);

        if ($parentRow && !empty($parentRow['PARENT_REF'])) {
            $parent_coil = trim($parentRow['PARENT_REF']);
        }
    } catch (Exception $e) {
        // Fallback หากเกิด Error
    }
}

// ถ้าหาไม่เจอ ให้ใช้ PRODUCT_REFERENCE จาก CRSHPROD1 เป็นค่าสำรอง
if (empty($parent_coil)) {
    $parent_coil = $mainData['PRODUCT_REFERENCE'] ?? '';
}

// 4. แยกข้อมูล PROCESS ตาม CRSH_WORKPROCESS
$excludedProcesses = ['IS', 'PK', 'ST', 'RC'];
$rawWorkProcess = $mainData['CRSH_WORKPROCESS'] ?? '';
$workProcesses = explode('>', $rawWorkProcess);
$processList = [];

foreach ($workProcesses as $procCode) {
    $code = strtoupper(trim($procCode));
    if (empty($code) || in_array($code, $excludedProcesses)) {
        continue;
    }

    $procData = [
        'CODE'     => $code,
        'WEIGHT'   => '',
        'PIECE'    => '',
        'PALLET'   => '',
        'DATE'     => '',
        'OPERATOR' => ''
    ];

    switch ($code) {
        case 'SC':
            $sqlProc = "SELECT TOP(1) STCH_ACCEPTWEIGHT AS WT, STCH_ACCEPTPIECE AS PC, STCH_ENDDATE AS DT, STCH_OPERATOR1 AS OP FROM STCHPROD1 WHERE PRODUCT_NO = :p ORDER BY STCH_ENDDATE DESC";
            break;
        case 'AN':
            $sqlProc = "SELECT TOP(1) ANNL_ACCEPTWEIGHT AS WT, ANNL_ACCEPTPIECE AS PC, ANNL_ENDDATE AS DT, ANNL_OPERATOR1 AS OP FROM ANNLPROD1 WHERE PRODUCT_NO = :p ORDER BY ANNL_ENDDATE DESC";
            break;
        case 'CT':
            $sqlProc = "SELECT TOP(1) CTSH_ACCEPTWEIGHT AS WT, CTSH_ACCEPTPIECE AS PC, CTSH_ENDDATE AS DT, CTSH_OPERATOR1 AS OP FROM CTSHPROD1 WHERE PRODUCT_NO = :p ORDER BY CTSH_ENDDATE DESC";
            break;
        case 'BA':
            $sqlProc = "SELECT TOP(1) BTCH_ACCEPTWEIGHT AS WT, BTCH_ACCEPTPIECE AS PC, BTCH_ENDDATE AS DT, BTCH_OPERATOR1 AS OP FROM BTCHPROD1 WHERE PRODUCT_NO = :p ORDER BY BTCH_ENDDATE DESC";
            break;
        case 'TW':
            $sqlProc = "SELECT TOP(1) TPTP_ACCEPTWEIGHT AS WT, TPTP_ACCEPTPIECE AS PC, TPTP_ENDDATE AS DT, TPTP_OPERATOR1 AS OP FROM TPTPPROD1 WHERE PRODUCT_NO = :p ORDER BY TPTP_ENDDATE DESC";
            break;
        case 'SH':
            $sqlProc = "SELECT TOP(1) SHRS_ACCEPTWEIGHT AS WT, SHRS_ACCEPTPIECE AS PC, SHRS_ENDDATE AS DT, SHRS_OPERATOR1 AS OP FROM SHRSPROD1 WHERE PRODUCT_NO = :p ORDER BY SHRS_ENDDATE DESC";
            break;
        case 'PH':
            $sqlProc = "SELECT TOP(1) PCHL_ACCEPTWEIGHT AS WT, PCHL_ACCEPTPIECE AS PC, PCHL_ENDDATE AS DT, PCHL_OPERATOR1 AS OP FROM PCHLPROD1 WHERE PRODUCT_NO = :p ORDER BY PCHL_ENDDATE DESC";
            break;
        default:
            $sqlProc = "SELECT TOP(1) CRSH_PRODUCEWEIGHT AS WT, CRSH_PRODUCEPIECE AS PC, CRSH_BOTTOMWEIGHT AS BWT, CRSH_ENDDATE AS DT, CRSH_OPERATOR1 AS OP FROM CRSHPROD1 WHERE PRODUCT_NO = :p ORDER BY CRSH_ENDDATE DESC";
            break;
    }

    try {
        $stmtProc = $conn->prepare($sqlProc);
        $stmtProc->execute([':p' => $product_no]);
        $rowP = $stmtProc->fetch(PDO::FETCH_ASSOC);

        if ($rowP) {
            $procData['WEIGHT']   = !empty($rowP['WT']) ? number_format((float)$rowP['WT'], 0, '.', '') : '';
            $procData['PIECE']    = !empty($rowP['PC']) ? number_format((float)$rowP['PC'], 0, '.', '') : '';
            $procData['DATE']     = !empty($rowP['DT']) ? date('m/d/Y', strtotime($rowP['DT'])) : '';
            $procData['OPERATOR'] = $rowP['OP'] ?? '';

            // ดึงค่า BOTTOMWEIGHT สำหรับใส่ใน PALLET
            $bwt = $rowP['BWT'] ?? $mainData['CRSH_BOTTOMWEIGHT'] ?? '';
            $procData['PALLET']   = !empty($bwt) ? number_format((float)$bwt, 0, '.', '') : '';
        } else {
            $bwt = $mainData['CRSH_BOTTOMWEIGHT'] ?? '';
            $procData['PALLET']   = !empty($bwt) ? number_format((float)$bwt, 0, '.', '') : '';
        }
    } catch (Exception $e) {
        $bwt = $mainData['CRSH_BOTTOMWEIGHT'] ?? '';
        $procData['PALLET']   = !empty($bwt) ? number_format((float)$bwt, 0, '.', '') : '';
    }

    $processList[] = $procData;
}
?>
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
            font-size: 20px; 
            line-height: 1.15;
            vertical-align: middle;
            white-space: nowrap;  
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

        /* --- 3. Note Container --- */
        .note-container {
            border: 1px solid black; 
            border-top: none;
            flex-grow: 1; 
            margin-bottom: 170px;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
        }

        .bottom-note {
            flex: 1; 
            display: flex;
            align-items: center; 
            border-bottom: 1px solid black;
            padding: 4px 8px; 
            font-size: 16px;  
            font-weight: bold;
            line-height: 1.2;
            white-space: normal;
        }

        .bottom-note:last-child {
            border-bottom: none;
        }

        /* --- 4. Footer Fixed Barcode & Inspection --- */
        .footer-fixed {
            position: absolute; bottom: 8mm; left: 8mm; right: 8mm;
            display: flex; border: 1px solid black; 
            height: 160px; 
            background: white;
        }
        
        .barcode-area { 
            flex: 0 0 58%; 
            border-right: 1px solid black; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            padding: 4px;
        }
        
        #barcode { 
            width: 100%; 
            height: 100%; 
            max-height: 152px; 
            object-fit: contain;
        }

        .inspect-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column;   
            justify-content: flex-end; 
            align-items: center; 
            padding-bottom: 6px;       
            font-weight: bold; 
            font-size: 22px;          
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

            <tr>
                <td colspan="1" class="center bold" style="padding: 6px 2px; font-size: 17px;">CIRCLE OR<br>SHEET<br>PRODUCT<br>LABEL</td>
                <td colspan="2" class="center bold" style="font-size: 28px;">พื้นที่ติดเทปกาว</td>
                <td colspan="1" class="center bold" style="padding: 6px 2px; font-size: 17px;">CIRCLE OR<br>SHEET<br>PRODUCT<br>LABEL</td>
            </tr>

            <tr>
                <td class="lbl">PRODUCT NO.</td>
                <td id="productNo" class="bold"><span class="fit-text"><?php echo h($mainData['PRODUCT_NO']); ?></span></td>
                <td class="lbl">PRINT DATE</td>
                <td class="bold"><span class="fit-text"><?php echo date('m/d/Y H:i'); ?></span></td>
            </tr>
            <tr>
                <td class="lbl">PROD. REF.</td>
                <td><span class="fit-text"><?php echo h($mainData['PRODUCT_REFERENCE']); ?></span></td>
                <td class="lbl">REQ. DATE</td>
                <td class="bold"><span class="fit-text"><?php echo !empty($mainData['SCHEDULE_DATE']) ? date('m/d/Y', strtotime($mainData['SCHEDULE_DATE'])) : ''; ?></span></td>
            </tr>
            <tr>
                <td class="lbl">COIL NO.</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['COIL_NO']); ?></span></td>
                <td class="lbl">MATERIAL IN</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['MATERIAL_IN']); ?></span></td>
            </tr>
            <tr>
                <td class="lbl">COIL REF.</td>
                <td class="bold"><span class="fit-text"><?php echo h($parent_coil); ?></span></td>
                <td class="lbl">CUSTOMER</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['CSTMSPPL_ID'] . ' / ' . $mainData['ORDER_CATEGORY']); ?></span></td>
            </tr>
            <tr>
                <td class="lbl">SALE ORDER</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['SALEORDER_NO'] . (!empty($mainData['SALEORDER_ITEM']) ? ' : ' . $mainData['SALEORDER_ITEM'] : '')); ?></span></td>
                <td class="lbl">JOB ORD. - TP</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['JOB_ORDER'] . '   ' . $mainData['JOB_TEMPER']); ?></span></td>
            </tr>
            <tr>
                <td class="lbl">PO NO.</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['CTM2_PONO'] . (!empty($mainData['CTM2_POITEM']) ? ' : ' . $mainData['CTM2_POITEM'] : '')); ?></span></td>
                <td class="lbl">ACT. WEIGH</td>
                <td class="bold right" style="font-size: 22px;"><span class="fit-text"><?php echo !empty($mainData['CRSH_ACTUALWEIGHT']) ? number_format((float)$mainData['CRSH_ACTUALWEIGHT'], 0, '.', '') : ''; ?></span></td>
            </tr>
            <tr>
                <td class="lbl">WORK PROC.</td>
                <td class="bold"><span class="fit-text"><?php echo h($mainData['CRSH_WORKPROCESS']); ?></span></td>
                <td class="lbl">ACT. PIECE</td>
                <td class="bold right" style="font-size: 22px;"><span class="fit-text"><?php echo !empty($mainData['CRSH_ACTUALPIECE']) ? number_format((float)$mainData['CRSH_ACTUALPIECE'], 0, '.', '') : ''; ?></span></td>
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
                <td><span class="fit-text"><?php echo h($mainData['ALLOY']); ?></span></td>
                <td><span class="fit-text"><?php echo h($mainData['TEMPER']); ?></span></td>
                <td><span class="fit-text"><?php echo h($mainData['GRADE']); ?></span></td>
                <td><span class="fit-text"><?php echo h($mainData['SURFACE_GRADE']); ?></span></td>
                <td><span class="fit-text"><?php echo h($mainData['METALLURGICAL_GRADE']); ?></span></td>
                <td><span class="fit-text"><?php echo h($mainData['T5_TEMPERATURE']); ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['THICKNESS']) ? number_format((float)$mainData['THICKNESS'], 3, '.', '') : ''; ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['WIDTH']) ? number_format((float)$mainData['WIDTH'], 2, '.', '') : ''; ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['LENGTH']) ? number_format((float)$mainData['LENGTH'], 2, '.', '') : ''; ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['WEIGHT_PIECE']) ? number_format((float)$mainData['WEIGHT_PIECE'], 4, '.', '') : ''; ?></span></td>
            </tr>
            <tr class="center bold">
                <td colspan="7" class="bold" style="text-align: left;"><span class="fit-text">CUT SHEET SIZE FOR SHEAR OR STRETCHER</span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['CUT_WIDTH']) ? number_format((float)$mainData['CUT_WIDTH'], 2, '.', '') : ''; ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['CUT_LENGTH']) ? number_format((float)$mainData['CUT_LENGTH'], 2, '.', '') : ''; ?></span></td>
                <td><span class="fit-text"><?php echo !empty($mainData['CUT_WEIGHTPIECE']) ? number_format((float)$mainData['CUT_WEIGHTPIECE'], 4, '.', '') : ''; ?></span></td>
            </tr>
        </table>

        <!-- Section 3: Process Matrix -->
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
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <td class="center bold"><span class="fit-text"><?php echo h($processList[$i]['CODE'] ?? ''); ?></span></td>
                <?php endfor; ?>
            </tr>
            <tr>
                <td class="bold">QTY. (KG)</td>
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <td class="right bold"><span class="fit-text"><?php echo h($processList[$i]['WEIGHT'] ?? ''); ?></span></td>
                <?php endfor; ?>
            </tr>
            <tr>
                <td class="bold">QTY. (PC)</td>
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <td class="right bold"><span class="fit-text"><?php echo h($processList[$i]['PIECE'] ?? ''); ?></span></td>
                <?php endfor; ?>
            </tr>
            <tr>
                <td class="bold">PALLET</td>
                <?php for ($i = 0; $i < 7; $i++): 
                    $hasQty = !empty($processList[$i]['WEIGHT']) || !empty($processList[$i]['PIECE']);
                ?>
                    <td class="right bold"><span class="fit-text"><?php echo $hasQty ? h($processList[$i]['PALLET'] ?? '') : ''; ?></span></td>
                <?php endfor; ?>
            </tr>
            <tr>
                <td class="bold">DATE</td>
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <td class="center bold"><span class="fit-text"><?php echo h($processList[$i]['DATE'] ?? ''); ?></span></td>
                <?php endfor; ?>
            </tr>
            <tr>
                <td class="bold">OPERATOR</td>
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <td class="center bold"><span class="fit-text"><?php echo h($processList[$i]['OPERATOR'] ?? ''); ?></span></td>
                <?php endfor; ?>
            </tr>
        </table>

        <!-- Section 4: Note Area & Remarks -->
        <div class="note-container">
            <?php if (!empty($mainData['JOB_REMARK'])): ?>
                <div class="bottom-note"><?php echo h($mainData['JOB_REMARK']); ?></div>
            <?php endif; ?>
            <?php if (!empty($mainData['PRD_REMARK1'])): ?>
                <div class="bottom-note"><?php echo h($mainData['PRD_REMARK1']); ?></div>
            <?php endif; ?>
            <?php if (!empty($mainData['PRD_REMARK2'])): ?>
                <div class="bottom-note"><?php echo h($mainData['PRD_REMARK2']); ?></div>
            <?php endif; ?>
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
                let fontSize = 20; 
                el.style.fontSize = fontSize + 'px';

                while (el.scrollWidth > parent.clientWidth - 6 && fontSize > 11) {
                    fontSize -= 0.5;
                    el.style.fontSize = fontSize + 'px';
                }
            });
        }

        window.onload = () => {
            adjustFontSize();

            const proNo = "<?php echo h($mainData['PRODUCT_NO']); ?>";

            JsBarcode("#barcode", proNo, {
                format: "CODE128",
                width: 2.2,
                height: 110,
                displayValue: true,
                fontSize: 34,
                fontOptions: "bold",
                margin: 2,
                textMargin: 4
            });
        };
    </script>
</body>
</html>