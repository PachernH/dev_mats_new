<?php
session_start();
include("dbcon_mats-new.php");

// รับค่า cno หรือ coilno จาก Parameter
$search_key = isset($_GET['cno']) ? trim($_GET['cno']) : (isset($_GET['coilno']) ? trim($_GET['coilno']) : '');

if (empty($search_key)) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>คำเตือน: ไม่พบการระบุ PRODUCT_NO หรือ COIL_NO</h3>");
}

// 1. ดึงข้อมูลจาก SCRPPROD1 (ค้นหาจาก PRODUCT_NO หรือ COIL_NO)
$sql_scrap = "SELECT PRODUCT_NO, COIL_NO, SCRP_WEIGHT, SCRP_OPERATOR, SCRP_STATUS 
              FROM SCRPPROD1 
              WHERE PRODUCT_NO = :search_key OR COIL_NO = :search_key";
$stmt_scrap = $conn->prepare($sql_scrap);
$stmt_scrap->bindParam(':search_key', $search_key, PDO::PARAM_STR);
$stmt_scrap->execute();
$scrap_data = $stmt_scrap->fetch(PDO::FETCH_ASSOC);

if (!$scrap_data) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>ไม่พบข้อมูลใน SCRPPROD1 สำหรับ: " . htmlspecialchars($search_key, ENT_QUOTES, 'UTF-8') . "</h3>");
}

$coil_no = $scrap_data['COIL_NO'];

// 2. ดึงข้อมูลจาก COILPROD1 ตาม COIL_NO ที่ได้
$sql_coil = "SELECT COIL_NO, ALLOY, CSTMSPPL_ID, MATERIAL_IN 
             FROM COILPROD1 
             WHERE COIL_NO = :coil_no";
$stmt_coil = $conn->prepare($sql_coil);
$stmt_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
$stmt_coil->execute();
$coil_data = $stmt_coil->fetch(PDO::FETCH_ASSOC);

// 3. ดึง Parent Coil ด้วย Recursive CTE
$sql_parent = "WITH ParentTree AS (
                    SELECT 
                        c.COIL_NO, c.MATERIAL_IN, c.LINE_PROCESS, c.PRODUCT_REFERENCE, c.COIL_ENDDATE, 
                        c.COIL_ACTUALWEIGHT, c.CSTMSPPL_ID, c.ALLOY, c.THICKNESS, c.WIDTH, 
                        c.COIL_STATUS, c.COIL_OPERATEDATE,
                        0 AS Level
                    FROM COILPROD1 AS c
                    WHERE c.COIL_NO = :coil_no
                    UNION ALL
                    SELECT 
                        p.COIL_NO, p.MATERIAL_IN, p.LINE_PROCESS, p.PRODUCT_REFERENCE, p.COIL_ENDDATE, 
                        p.COIL_ACTUALWEIGHT, p.CSTMSPPL_ID, p.ALLOY, p.THICKNESS, p.WIDTH, 
                        p.COIL_STATUS, p.COIL_OPERATEDATE,
                        child.Level - 1 
                    FROM COILPROD1 AS p
                    INNER JOIN ParentTree AS child ON p.COIL_NO = child.PRODUCT_REFERENCE
                )
                SELECT COIL_NO FROM ParentTree ORDER BY Level ASC";

$stmt_parent = $conn->prepare($sql_parent);
$stmt_parent->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
$stmt_parent->execute();
$parent_list = $stmt_parent->fetchAll(PDO::FETCH_COLUMN);

// หา Parent Coil
$parent_coil_no = '';
if (count($parent_list) > 1) {
    $parent_coil_no = $parent_list[0];
} else {
    $parent_coil_no = $coil_no;
}

function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>SCRAP LABEL - Meyer Aluminium</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        
        body { 
            font-family: 'Tahoma', 'Segoe UI', sans-serif; 
            background-color: #555; 
            margin: 0;
            padding: 0;
            font-weight: bold;
        }

        .no-print {
            position: fixed; top: 20px; right: 20px; padding: 12px 24px;
            background: #28a745; color: white; border: none; border-radius: 5px;
            cursor: pointer; z-index: 1000; font-weight: bold; font-size: 18px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        /* กล่อง A4 ครอบพื้นที่พิมพ์ */
        .print-page {
            width: 210mm; 
            margin: 0 auto; 
            padding: 10mm 10mm 0 10mm;
            background: white;
            min-height: 297mm;
        }

        @media print {
            body { background: none; }
            .no-print { display: none; }
            .print-page { margin: 0; width: 210mm; padding: 10mm 10mm 0 10mm; }
        }

        /* Container กำหนดความสูงครึ่ง A4 (ประมาณ 135mm - 140mm) */
        .label-half-container {
            width: 100%;
            height: 138mm;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        /* โครงสร้าง Master Table */
        .scrap-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 3px solid #000;
        }

        .scrap-table td, .scrap-table th {
            border: 2px solid #000;
            padding: 6px 8px;
            font-size: 20px;
            font-weight: 800;
            color: #000;
            vertical-align: middle;
            height: 52px;
            overflow: hidden;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* หัวตารางขยายตัวโต + ตัวหนาพิเศษ ความสูงรวมเท่ากับ 3 Row (~150px) */
        .header-title-large {
            font-size: 34px !important;
            font-weight: 900 !important;
            line-height: 1.15;
            letter-spacing: 1px;
            height: 150px !important;
            padding: 10px 4px !important;
        }

        .sticky-area-large {
            font-size: 38px !important;
            font-weight: 900 !important;
            letter-spacing: 2px;
            height: 150px !important;
            padding: 10px 4px !important;
        }

        .lbl-title {
            background-color: #ffffff;
            font-weight: 800;
        }

        .auto-fit {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            vertical-align: middle;
        }

        /* บาร์โค้ด Container ด้านล่าง */
        .barcode-container {
            border: 3px solid #000;
            border-top: none;
            padding: 12px 10px 8px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 135px;
        }

        #barcode {
            max-width: 95%;
            height: 90px;
        }
    </style>
</head>
<body>

    <button class="no-print" onclick="window.print()">🖨️ พิมพ์เอกสาร (Print Scrap Label)</button>

    <div class="print-page">
        <!-- Container ควบคุมขนาดให้อยู่ในสัดส่วนครึ่งหน้า A4 -->
        <div class="label-half-container">
            <table class="scrap-table">
                <!-- กำหนดความกว้างคอลัมน์สมดุล 4 ช่อง (22% | 28% | 22% | 28%) -->
                <colgroup>
                    <col style="width: 22%;">
                    <col style="width: 28%;">
                    <col style="width: 22%;">
                    <col style="width: 28%;">
                </colgroup>

                <!-- Row 1: Header ขยายใหญ่ข้ามความสูงเทียบเท่า 3 แถว (150px) -->
                <tr>
                    <td class="text-center header-title-large">
                        SCRAP<br>LABEL
                    </td>
                    <td class="text-center sticky-area-large" colspan="2">
                        พื้นที่ติดเทปกาว
                    </td>
                    <td class="text-center header-title-large">
                        SCRAP<br>LABEL
                    </td>
                </tr>

                <!-- Row 2: SCRAP NO. & STATUS -->
                <tr>
                    <td class="lbl-title">SCRAP NO.</td>
                    <td>
                        <span class="auto-fit"><?php echo h($scrap_data['PRODUCT_NO']); ?></span>
                    </td>
                    <td class="lbl-title">STATUS</td>
                    <td class="text-center">
                        <span class="auto-fit"><?php echo h($scrap_data['SCRP_STATUS']); ?></span>
                    </td>
                </tr>

                <!-- Row 3: COIL NO. & PRINT DATE -->
                <tr>
                    <td class="lbl-title">COIL NO.</td>
                    <td>
                        <span class="auto-fit"><?php echo h($scrap_data['COIL_NO']); ?></span>
                    </td>
                    <td class="lbl-title">PRINT DATE</td>
                    <td class="text-center">
                        <span class="auto-fit" style="font-size: 16px;"><?php echo date('d/m/Y H:i'); ?></span>
                    </td>
                </tr>

                <!-- Row 4: COIL REF. 1 & OPERATOR -->
                <tr>
                    <td class="lbl-title">COIL REF. 1</td>
                    <td>
                        <span class="auto-fit"><?php echo h($parent_coil_no); ?></span>
                    </td>
                    <td class="lbl-title">OPERATOR</td>
                    <td class="text-center">
                        <span class="auto-fit"><?php echo h($scrap_data['SCRP_OPERATOR']); ?></span>
                    </td>
                </tr>

                <!-- Row 5: ALLOY & COIL FROM -->
                <tr>
                    <td class="lbl-title">ALLOY</td>
                    <td class="text-center">
                        <span class="auto-fit"><?php echo h($coil_data['ALLOY'] ?? ''); ?></span>
                    </td>
                    <td class="lbl-title">COIL FROM</td>
                    <td class="text-center">
                        <span class="auto-fit">MAT</span>
                    </td>
                </tr>

                <!-- Row 6: WEIGHT & MATERIAL IN -->
                <tr>
                    <td class="lbl-title">WEIGHT</td>
                    <td class="text-right" style="padding-right: 15px;">
                        <span class="auto-fit">
                            <?php 
                                $weight = isset($scrap_data['SCRP_WEIGHT']) ? number_format((float)$scrap_data['SCRP_WEIGHT'], 0) : '0';
                                echo $weight . ' KG.'; 
                            ?>
                        </span>
                    </td>
                    <td class="lbl-title">MATERIAL IN</td>
                    <td class="text-center">
                        <span class="auto-fit"><?php echo h($coil_data['MATERIAL_IN'] ?? ''); ?></span>
                    </td>
                </tr>
            </table>

            <!-- บาร์โค้ด SCRAP NO. ด้านล่าง -->
            <div class="barcode-container">
                <svg id="barcode"></svg>
            </div>
        </div>
    </div>

    <script>
        // ฟังก์ชั่นย่อขนาดตัวอักษรอัตโนมัติเมื่อข้อความยาวเกินช่องตาราง
        function fitTextToCell() {
            document.querySelectorAll('.auto-fit').forEach(el => {
                const parent = el.parentElement;
                let fontSize = parseFloat(window.getComputedStyle(el).fontSize) || 20;
                
                while (el.scrollWidth > parent.clientWidth - 10 && fontSize > 9) {
                    fontSize -= 0.5;
                    el.style.fontSize = fontSize + 'px';
                }
            });
        }

        window.onload = function() {
            fitTextToCell();

            const scrapNo = "<?php echo h($scrap_data['PRODUCT_NO']); ?>";
            if (scrapNo) {
                JsBarcode("#barcode", scrapNo, {
                    format: "CODE128",
                    width: 3,
                    height: 90,
                    displayValue: true,
                    fontSize: 26,
                    fontOptions: "bold",
                    margin: 4
                });
            }
        };
    </script>
</body>
</html>