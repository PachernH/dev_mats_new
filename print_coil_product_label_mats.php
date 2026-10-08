<?php
session_start();
include("dbcon_mats-new.php");

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '';

if (empty($coil_no)) {
    die("Coil Number is required.");
}

// 1. Query ข้อมูลหลักของ Coil ปัจจุบัน
$sql = "SELECT 
            A.COIL_NO, A.PRODUCT_REFERENCE, A.MATERIAL_IN, A.CSTMSPPL_ID, A.LINE_PROCESS, A.JOB_PROCESS, A.COIL_WORKPROCESS,
            A.ALLOY, A.TEMPER, A.GRADE, A.SURFACE_GRADE, A.METALLURGICAL_GRADE, A.T5_TEMPERATURE, A.THICKNESS, A.F_THICKNESS,
            A.WIDTH, A.ACTUAL_WIDTH, A.COIL_FIRSTWEIGHT, A.COIL_BALANCEWEIGHT, A.COIL_ENDDATE, A.COIL_OPERATOR1, 
            A.COIL_REMARK, A.COIL_LABEL, A.COIL_STATUS, A.COIL_ACTUALWEIGHT, A.COIL_STARTDATE,
            B.JOB_ORDER, B.TEMPER AS JOB_TEMPER, B.SCHEDULE_DATE, B.JOB_REMARK, B.THICKNESS AS JOB_THICKNESS, 
            B.CUST_MAXTOLERANCE, B.CUST_MINTOLERANCE, 
            C.ACTUAL_THICKNESS, C.MINIMUM_THICKNESS, C.MAXIMUM_THICKNESS
        FROM COILPROD1 AS A 
        LEFT JOIN JOBORDER1 AS B ON A.JOB_PROCESS = B.JOB_ORDER 
        LEFT JOIN COILINSP1 AS C ON A.COIL_NO = C.COIL_NO
        WHERE A.COIL_NO = :coil_no AND A.COIL_STATUS IN ('OP', 'AC', 'RJ', 'RM')";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
$stmt->execute();       
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    //die("Data not found for Coil No: " . htmlspecialchars($coil_no));
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>ไม่พบข้อมูล Coil No: " . htmlspecialchars($coil_no) . "</h3>"); 
}

// ==========================================
// 1.1 Query ดึงข้อมูล NUMBER_COILSLIT จาก SWSLPROD1
// ==========================================       
$number_coilslit = '';
$sql_sws = "SELECT NUMBER_COILSLIT FROM SWSLPROD1 WHERE COIL_NO = :coil_no";
$stmt_sws = $conn->prepare($sql_sws);
$stmt_sws->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
$stmt_sws->execute();
$sws_data = $stmt_sws->fetch(PDO::FETCH_ASSOC);
if ($sws_data && isset($sws_data['NUMBER_COILSLIT'])) {
    $number_coilslit = $sws_data['NUMBER_COILSLIT'];
}

// ==========================================
// 2. Logic ดึงข้อมูล Process & ค้นหา Chain ของ Coil Parent
// ==========================================
$immediate_ref = trim($data['PRODUCT_REFERENCE'] ?? '');

$first_data = null;
$crm_data   = null;
$batch_data = null;

$ref1 = '';
$ref2 = '';
$ref3 = '';

// ตรวจสอบเงื่อนไข: หากมี PRODUCT_REFERENCE จึงจะทำการดึงข้อมูล Process และค้นหา Parent
if (!empty($immediate_ref)) {
    // 2.1 ดึงข้อมูล CRM & BATCH (อิงตาม PRODUCT_REFERENCE ของ Coil ปัจจุบัน)
    $sql_crm = "SELECT COIL_NO, THICKNESS_FINAL, F_TEMPER, COIL_ACTUALWEIGHT, COIL_STARTDATE, COIL_OPERATOR1 
                FROM COILPROD1 WHERE COIL_NO = :ref_no";
    $stmt_crm = $conn->prepare($sql_crm);
    $stmt_crm->bindParam(':ref_no', $immediate_ref, PDO::PARAM_STR);
    $stmt_crm->execute();
    $crm_data = $stmt_crm->fetch(PDO::FETCH_ASSOC);

    $sql_batch = "SELECT TEMPER, BTCH_ACCEPTWEIGHT, BTCH_ENDDATE, BTCH_OPERATOR1 
                  FROM BTCHPROD1 WHERE PRODUCT_NO = :ref_no ORDER BY PRODUCT_NO";
    $stmt_batch = $conn->prepare($sql_batch);
    $stmt_batch->bindParam(':ref_no', $immediate_ref, PDO::PARAM_STR);
    $stmt_batch->execute();
    $batch_data = $stmt_batch->fetch(PDO::FETCH_ASSOC);

    // 2.2 วนลูปย้อนหา Root Parent ที่ไม่มี PRODUCT_REFERENCE จริง ๆ
    $ref_chain = []; 
    $search_coil = $immediate_ref;
    $max_depth = 10; 
    $root_parent_no = '';

    while (!empty($search_coil) && $max_depth > 0) {
        $sql_parent = "SELECT COIL_NO, PRODUCT_REFERENCE, F_THICKNESS, TEMPER, COIL_ACTUALWEIGHT, COIL_STARTDATE, COIL_OPERATOR1 
                       FROM COILPROD1 WHERE COIL_NO = :coil_no";
        $stmt_parent = $conn->prepare($sql_parent);
        $stmt_parent->bindParam(':coil_no', $search_coil, PDO::PARAM_STR);
        $stmt_parent->execute();
        $parent_row = $stmt_parent->fetch(PDO::FETCH_ASSOC);

        if ($parent_row) {
            $first_data = $parent_row;
            $next_ref = trim($parent_row['PRODUCT_REFERENCE'] ?? '');

            if (!empty($next_ref)) {
                $ref_chain[] = $parent_row['COIL_NO'];
                $search_coil = $next_ref;
            } else {
                // เจอ Root Parent จริงแล้ว (ไม่มี PRODUCT_REFERENCE) เช่น CP24-001934
                $root_parent_no = $parent_row['COIL_NO'];
                break;
            }
        } else {
            // กรณีไม่เจอ Record ใน COILPROD1 ให้ถือว่าตัวนั้นคือ Root Parent
            $root_parent_no = $search_coil;
            break;
        }
        $max_depth--;
    }

    // ==========================================
    // 3. Logic กรองกรณีรูปแบบ -??- คล้ายกัน ให้เอาตัวลำดับแรกสุด
    // ==========================================
    $filtered_refs = [];
    $seen_patterns = [];

    function get_coil_pattern($coil_str) {
        if (preg_match('/-[A-Z0-9]+-/', $coil_str, $matches)) {
            return $matches[0];
        }
        return $coil_str;
    }

    foreach ($ref_chain as $ref_item) {
        $pat = get_coil_pattern($ref_item);
        if (!isset($seen_patterns[$pat])) {
            $seen_patterns[$pat] = true;
            $filtered_refs[] = $ref_item;
        }
    }

    // จัดลำดับ COIL REF 1, 2, 3 ตามจำนวนรายการ
    if (count($filtered_refs) > 3) {
        // หากรายการมีมากกว่า 3 ให้ใส่ 2 ตัวแรกตามลำดับ และบังคับตัวสุดท้าย (REF3) เป็น Coil Parent
        $ref1 = $filtered_refs[0] ?? '';
        $ref2 = $filtered_refs[1] ?? '';
        $ref3 = $root_parent_no;
    } else {
        // หากไม่เกิน 3 รายการ ให้ใส่ตามปกติ และต่อท้ายด้วย Root Parent (ถ้ายังไม่มีในรายการ)
        if (!empty($root_parent_no) && !in_array($root_parent_no, $filtered_refs)) {
            $filtered_refs[] = $root_parent_no;
        }
        $ref1 = $filtered_refs[0] ?? '';
        $ref2 = $filtered_refs[1] ?? '';
        $ref3 = $filtered_refs[2] ?? '';
    }
}

// ==========================================
// 4. คำนวณ CUST. MAX/MIN THICKNESS TOLERANCE
// ==========================================
$base_thickness = (float)($data['JOB_THICKNESS'] ?? 0);
$max_tol = isset($data['CUST_MAXTOLERANCE']) && $data['CUST_MAXTOLERANCE'] !== '' ? (float)$data['CUST_MAXTOLERANCE'] : null;
$min_tol = isset($data['CUST_MINTOLERANCE']) && $data['CUST_MINTOLERANCE'] !== '' ? (float)$data['CUST_MINTOLERANCE'] : null;

$cust_max_thickness = ($max_tol !== null) ? ($base_thickness + $max_tol) : null;
$cust_min_thickness = ($min_tol !== null) ? ($base_thickness - $min_tol) : null;

// ==========================================
// 5. จัดการข้อมูล Note (เปลี่ยนช่องว่าง 3 ช่องขึ้นไปเป็นขึ้นบรรทัดใหม่)
// ==========================================
function convert_spaces_to_newlines($text) {
    if (empty(trim($text ?? ''))) return '';
    $text = preg_replace('/[ \t]{3,}/', "\n", trim($text));
    return $text;
}

$remark_list = [];
$job_rmk  = convert_spaces_to_newlines($data['JOB_REMARK'] ?? '');
$coil_rmk = convert_spaces_to_newlines($data['COIL_REMARK'] ?? '');

if (!empty($job_rmk)) {
    $remark_list[] = $job_rmk;
}
if (!empty($coil_rmk)) {
    $remark_list[] = $coil_rmk;
}

$full_remark = implode("\n", $remark_list);

// Helper Functions
function h($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function fmt_num($val, $dec = 3) {
    return ($val !== null && $val !== '') ? number_format((float)$val, $dec, '.', '') : '';
}

function fmt_date_str($val) {
    if (!empty($val)) {
        $ts = strtotime($val);
        if ($ts !== false) return date('m/d/Y', $ts);
    }
    return '';
}

// แปลงสถานะ COIL_STATUS
$status_text = 'ACCEPT';
if (($data['COIL_STATUS'] ?? '') == 'RJ') {
    $status_text = 'REJECT';
} elseif (($data['COIL_STATUS'] ?? '') == 'RM') {
    $status_text = 'REMILL';
} elseif (($data['COIL_STATUS'] ?? '') == 'OP') {
    $status_text = 'OPEN';
}
?>
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
        
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body { 
            font-family: 'Tahoma', sans-serif; 
            background-color: #555; 
            font-size: 22px; 
            font-weight: bold; 
        }

        .no-print {
            position: fixed; top: 20px; right: 20px; padding: 12px 24px;
            background: #28a745; color: white; border: none; border-radius: 5px;
            cursor: pointer; z-index: 1000; font-weight: bold; font-size: 18px;
        }

        .print-page {
            width: 210mm; 
            height: 297mm; 
            margin: 0 auto; 
            padding: 8mm;
            background: white; 
            position: relative; 
            overflow: hidden;
            display: flex; 
            flex-direction: column;
            justify-content: space-between;
        }

        @media print {
            body { background: none; }
            .no-print { display: none; }
            .print-page { margin: 0; width: 210mm; height: 297mm; }
        }

        /* --- 2. Table & Content Layout --- */
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: -1px; }
        td, th {
            border: 1px solid black; 
            padding: 5px 6px; 
            font-size: 22px; 
            font-weight: bold; 
            line-height: 1.15;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .label { width: 23%; background-color: #f2f2f2; font-weight: bold; }
        .val { width: 27%; font-weight: bold; }

        .auto-fit {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            vertical-align: middle;
        }

        .sticky-area { 
            font-size: 32px; 
            font-weight: bold; 
            height: 120px;
            vertical-align: middle;
        }

        .work-proc { 
            border: 1px solid black; 
            border-top: none; 
            padding: 10px 6px; 
            font-weight: bold; 
            font-size: 23px; 
        }

        .note-container {
            border: 1px solid black; 
            border-top: none; 
            padding: 12px;
            flex: 1;
            font-size: 22px; 
            line-height: 1.25;
            font-weight: normal;
            margin-bottom: -1px;
            min-height: 120px;
            overflow: hidden;
            word-wrap: break-word;
        }

        /* --- 3. Footer Barcode & Inspection --- */
        .footer-attached {
            display: flex; 
            border: 1px solid black; 
            height: 175px; 
            background: white;
            width: 100%;
            flex-shrink: 0;
        }
        
        .barcode-area { 
            flex: 0 0 65%;
            border-right: 1px solid black; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            padding: 4px;
            overflow: hidden;
        }
        
        #barcode { 
            max-width: 98%; 
            max-height: 98%; 
            display: block;
        }

        .inspect-area { 
            flex: 1;
            display: flex; 
            flex-direction: column;
            justify-content: flex-end; 
            align-items: center;
            padding-bottom: 12px; 
            font-weight: 900; 
            font-size: 26px; 
            text-align: center;
        }

        .signature-space {
            flex-grow: 1;
            width: 100%;
        }
    </style>
</head>
<body>

    <button class="no-print" onclick="window.print()">Print the Label (Print Label)</button>

    <div class="print-page">
        <div>
            <!-- Master Table รวม Header ให้เส้นตรงกัน -->
            <table>
                <!-- Row 1: Header -->
                <tr>
                    <td class="center" style="width:23%; vertical-align:middle;">COIL<br>PRODUCT<br>LABEL</td>
                    <td class="center sticky-area" colspan="2" style="width:50%;">พื้นที่ติดเทปกาว</td>
                    <td class="center" style="width:27%; vertical-align:middle;">COIL<br>PRODUCT<br>LABEL</td>
                </tr>
                <!-- Row 2: COIL NO. -->
                <tr>
                    <td class="label">COIL NO.</td>
                    <td class="val"><span class="auto-fit"><?php echo h($data['COIL_NO']); ?></span></td>
                    <td class="label">STATUS</td>
                    <td class="val center" style="background:#eee;"><?php echo h($status_text); ?></td>
                </tr>
                <!-- Row 3: COIL REF. 1 -->
                <tr>
                    <td class="label">COIL REF. 1</td>
                    <td><span class="auto-fit"><?php echo h($ref1); ?></span></td>
                    <td class="label">PRINT DATE</td>
                    <td class="center"><span class="auto-fit"><?php echo date('m/d/Y H:i'); ?></span></td>
                </tr>
                <!-- Row 4: COIL REF. 2 -->
                <tr>
                    <td class="label">COIL REF. 2</td>
                    <td><span class="auto-fit"><?php echo h($ref2); ?></span></td>
                    <td class="label">REQ. DATE</td>
                    <td class="center"><span class="auto-fit"><?php echo fmt_date_str($data['SCHEDULE_DATE']); ?></span></td>
                </tr>
                <!-- Row 5: COIL REF. 3 -->
                <tr>
                    <td class="label">COIL REF. 3</td>
                    <td><span class="auto-fit"><?php echo h($ref3); ?></span></td>
                    <td class="label">MATERIAL IN</td>
                    <td class="center"><span class="auto-fit"><?php echo h($data['MATERIAL_IN']); ?></span></td>
                </tr>
                <!-- Row 6: JOB ORDER -->
                <tr>
                    <td class="label">JOB ORDER</td>
                    <td><span class="auto-fit"><?php echo h($data['JOB_ORDER']); ?></span></td>
                    <td class="label">COIL FROM</td>
                    <td class="center"><span class="auto-fit">MAT</span></td>
                </tr>
                <!-- Row 7: JOB TEMPER -->
                <tr>
                    <td class="label">JOB TEMPER</td>
                    <td><span class="auto-fit"><?php echo h($data['JOB_TEMPER']); ?></span></td>
                    <td class="label">COIL SLIT</td>
                    <td class="right"><span class="auto-fit"><?php echo h($number_coilslit); ?> STAND</span></td>
                </tr>
            </table>

            <!-- Work Process -->
            <div class="work-proc">
                <span class="auto-fit">WORK PROC. &nbsp; <?php echo h($data['COIL_WORKPROCESS']); ?></span>
            </div>

            <!-- Specifications Grid -->
            <table>
                <tr class="center" style="background:#f9f9f9;">
                    <th style="width:13%;">ALLOY</th>
                    <th style="width:10%;">TP</th>
                    <th style="width:8%;">G</th>
                    <th style="width:12%;">SG</th>
                    <th style="width:13%;">MG</th>
                    <th style="width:12%;">T5</th>
                    <th style="width:14%;">TH</th>
                    <th style="width:18%;">WIDTH</th>
                </tr>
                <tr class="center">
                    <td><?php echo h($data['ALLOY']); ?></td>
                    <td><?php echo h($data['TEMPER']); ?></td>
                    <td><span class="auto-fit"><?php echo h($data['GRADE']); ?></span></td>
                    <td><?php echo h($data['SURFACE_GRADE']); ?></td>
                    <td><?php echo h($data['METALLURGICAL_GRADE']); ?></td>
                    <td><?php echo h($data['T5_TEMPERATURE']); ?></td>
                    <td style="font-size:25px;"><?php echo fmt_num($data['THICKNESS'], 3); ?></td>
                    <td style="font-size:25px;"><?php echo fmt_num($data['WIDTH'], 2); ?></td>
                </tr>
                <tr class="center">
                    <td colspan="6" style="text-align:right; padding-right:10px;">ACTUAL THICKNESS / WIDTH</td>
                    <td style="font-size:25px;"><?php echo fmt_num($data['ACTUAL_THICKNESS'], 3); ?></td>
                    <td style="font-size:25px;"><?php echo fmt_num($data['ACTUAL_WIDTH'], 2); ?></td>
                </tr>
                <tr class="center">
                    <td colspan="6" style="text-align:right; padding-right:10px;">CUST. MAX/MIN THICKNESS TOLERANCE</td>
                    <td><?php echo fmt_num($cust_max_thickness, 3); ?></td>
                    <td><?php echo fmt_num($cust_min_thickness, 3); ?></td>
                </tr>
            </table>

            <!-- Process Grid -->
            <table>
                <tr class="center" style="background:#f9f9f9;">
                    <th style="width:17%;">PROCESS</th>
                    <th style="width:13%;">TH</th>
                    <th style="width:11%;">TP</th>
                    <th style="width:15%;">QTY.</th>
                    <th style="width:20%;">PRO. DATE</th>
                    <th style="width:24%;">OPERATOR</th>
                </tr>
                <!-- Row 1: FIRST -->
                <tr class="center">
                    <td>FIRST</td>
                    <td><?php echo fmt_num($first_data['F_THICKNESS'] ?? $data['THICKNESS'], 3); ?></td>
                    <td><?php echo h($first_data['TEMPER'] ?? $data['TEMPER']); ?></td>
                    <td><?php echo fmt_num($first_data['COIL_ACTUALWEIGHT'] ?? $data['COIL_ACTUALWEIGHT'], 0); ?></td>
                    <td><span class="auto-fit"><?php echo fmt_date_str($first_data['COIL_STARTDATE'] ?? $data['COIL_STARTDATE']); ?></span></td>
                    <td><span class="auto-fit"><?php echo h($first_data['COIL_OPERATOR1'] ?? $data['COIL_OPERATOR1']); ?></span></td>
                </tr>
                <!-- Row 2: BATCH -->
                <tr class="center">
                    <td>BATCH</td>
                    <td></td>
                    <td><?php echo h($batch_data['TEMPER'] ?? ''); ?></td>
                    <td><?php echo fmt_num($batch_data['BTCH_ACCEPTWEIGHT'] ?? null, 0); ?></td>
                    <td><span class="auto-fit"><?php echo fmt_date_str($batch_data['BTCH_ENDDATE'] ?? ''); ?></span></td>
                    <td><span class="auto-fit"><?php echo h($batch_data['BTCH_OPERATOR1'] ?? ''); ?></span></td>
                </tr>
                <!-- Row 3: CRM -->
                <tr class="center">
                    <td>CRM.</td>
                    <td><?php echo fmt_num($crm_data['THICKNESS_FINAL'] ?? null, 3); ?></td>
                    <td><?php echo h($crm_data['F_TEMPER'] ?? ''); ?></td>
                    <td><?php echo fmt_num($crm_data['COIL_ACTUALWEIGHT'] ?? null, 0); ?></td>
                    <td><span class="auto-fit"><?php echo fmt_date_str($crm_data['COIL_STARTDATE'] ?? ''); ?></span></td>
                    <td><span class="auto-fit"><?php echo h($crm_data['COIL_OPERATOR1'] ?? ''); ?></span></td>
                </tr>
                <tr>
                    <td colspan="3" class="center">COIL BALANCE WT.</td>
                    <td class="center" style="font-size:26px; background:#fefefe;">
                        <?php echo fmt_num($data['COIL_BALANCEWEIGHT'], 0); ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </table>
        </div>

        <!-- Note Section -->
        <div class="note-container" id="noteContainer">
            <?php echo nl2br(h($full_remark)); ?>
        </div>

        <!-- Footer Barcode & Inspection -->
        <div class="footer-attached">
            <div class="barcode-area">
                <svg id="barcode"></svg>
            </div>
            <div class="inspect-area">
                <div class="signature-space"></div>
                <div>INSPECTION</div>
            </div>
        </div>
    </div>

    <script>
        function fitTextToCell() {
            document.querySelectorAll('.auto-fit').forEach(el => {
                const parent = el.parentElement;
                let fontSize = 22;
                el.style.fontSize = fontSize + 'px';
                
                while (el.scrollWidth > parent.clientWidth - 8 && fontSize > 9) {
                    fontSize -= 0.5;
                    el.style.fontSize = fontSize + 'px';
                }
            });
        }

        function fitNoteContainer() {
            const container = document.getElementById('noteContainer');
            if (!container) return;

            let fontSize = 22;
            container.style.fontSize = fontSize + 'px';

            while (container.scrollHeight > container.clientHeight && fontSize > 8) {
                fontSize -= 0.5;
                container.style.fontSize = fontSize + 'px';
            }
        }

        window.onload = () => {
            fitTextToCell();
            fitNoteContainer();
            
            const coilNo = "<?php echo h($data['COIL_NO']); ?>";
            JsBarcode("#barcode", coilNo, {
                format: "CODE128",
                width: 2.7,
                height: 100,
                displayValue: true,
                fontSize: 40,
                fontOptions: "bold",
                margin: 4
            });
        };
    </script>
</body>
</html>