<?php
// journal_print_mats.php
session_start();

if (empty($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/dbcon_mats-new.php';
$conn_mats = $conn;

$journal_type   = strtoupper(trim($_GET['type'] ?? ''));
$journal_period = trim($_GET['period'] ?? '');
$journal_no     = strtoupper(trim($_GET['no'] ?? ''));

if ($journal_no === '') {
    die("ไม่พบเลขที่เอกสาร (JOURNAL_NO)");
}

// 1. ดึงข้อมูลส่วนหัว (JRNLVCHR10 + CSSPMSTR22)
$sqlHead = "SELECT 
                A.*,
                CONVERT(VARCHAR(11), A.TRANSACTION_DATE, 106) AS TRANS_DATE_EN,
                CONVERT(VARCHAR(11), A.DUE_DATE, 106) AS DUE_DATE_EN,
                A.DUE_DATE AS DUE_DATE_RAW,
                B.EN_COMPANY,
                B.TH_COMPANY,
                B.TH_ADDRESS1,
                B.TH_ADDRESS2,
                B.TAX_REGISTRATION
            FROM JRNLVCHR10 A
            LEFT JOIN CSSPMSTR22 B ON A.CSTMSPPL_ID = B.CSTMSPPL_ID
            WHERE A.JOURNAL_NO = :j_no ";
$paramsHead = [':j_no' => $journal_no];

if ($journal_type !== '') {
    $sqlHead .= " AND A.JOURNAL_TYPE = :j_type ";
    $paramsHead[':j_type'] = $journal_type;
}
if ($journal_period !== '') {
    $sqlHead .= " AND A.JOURNAL_PERIOD = :j_period ";
    $paramsHead[':j_period'] = $journal_period;
}

$stmtH = $conn_mats->prepare($sqlHead);
$stmtH->execute($paramsHead);
$head = $stmtH->fetch(PDO::FETCH_ASSOC);

if (!$head) {
    die("ไม่พบข้อมูลเอกสารเลขที่ " . htmlspecialchars($journal_no));
}

$journal_type   = trim($head['JOURNAL_TYPE']);
$journal_period = trim($head['JOURNAL_PERIOD']);

// 2. ดึงรายการบัญชี (JRNLVCHR20)
$sqlDet = "SELECT * FROM JRNLVCHR20 
           WHERE JOURNAL_TYPE = :j_type 
             AND JOURNAL_PERIOD = :j_period 
             AND JOURNAL_NO = :j_no 
           ORDER BY JOURNAL_ITEM ASC";
$stmtD = $conn_mats->prepare($sqlDet);
$stmtD->execute([
    ':j_type'   => $journal_type,
    ':j_period' => $journal_period,
    ':j_no'     => $journal_no
]);
$items = $stmtD->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// Helper Functions: แปลงตัวเลขเป็นตัวอักษรภาษาอังกฤษ (SATANG ONLY) และภาษาไทย
// =========================================================================
function numberToEnglishBaht($number) {
    $number = number_format((float)$number, 2, '.', '');
    list($intPart, $decPart) = explode('.', $number);
    $words = [
        0 => '', 1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR', 5 => 'FIVE',
        6 => 'SIX', 7 => 'SEVEN', 8 => 'EIGHT', 9 => 'NINE', 10 => 'TEN',
        11 => 'ELEVEN', 12 => 'TWELVE', 13 => 'THIRTEEN', 14 => 'FOURTEEN',
        15 => 'FIFTEEN', 16 => 'SIXTEEN', 17 => 'SEVENTEEN', 18 => 'EIGHTEEN', 19 => 'NINETEEN'
    ];
    $tens = [
        2 => 'TWENTY', 3 => 'THIRTY', 4 => 'FORTY', 5 => 'FIFTY',
        6 => 'SIXTY', 7 => 'SEVENTY', 8 => 'EIGHTY', 9 => 'NINETY'
    ];
    $scales = ['', 'THOUSAND', 'MILLION', 'BILLION'];

    $convertChunk = function($num) use ($words, $tens) {
        $str = '';
        $num = (int)$num;
        if ($num >= 100) {
            $str .= $words[(int)($num / 100)] . ' HUNDRED ';
            $num %= 100;
        }
        if ($num >= 20) {
            $str .= $tens[(int)($num / 10)] . ' ';
            $num %= 10;
        }
        if ($num > 0) {
            $str .= $words[$num] . ' ';
        }
        return trim($str);
    };

    $convertAll = function($numStr) use ($convertChunk, $scales) {
        if ((int)$numStr === 0) return 'ZERO';
        $chunks = array_reverse(str_split(str_pad($numStr, ceil(strlen($numStr) / 3) * 3, '0', STR_PAD_LEFT), 3));
        $result = [];
        foreach ($chunks as $idx => $chunk) {
            $chunkWord = $convertChunk($chunk);
            if ($chunkWord !== '') {
                $result[] = trim($chunkWord . ' ' . ($scales[$idx] ?? ''));
            }
        }
        return implode(' ', array_reverse($result));
    };

    $bahtText = $convertAll($intPart);
    if ((int)$decPart > 0) {
        $satangText = $convertAll($decPart);
        return trim($bahtText . ' AND SATANG ' . $satangText . ' ONLY');
    }
    return trim($bahtText . ' ONLY');
}

function numberToThaiBaht($number) {
    $number = number_format((float)$number, 2, '.', '');
    list($intPart, $decPart) = explode('.', $number);
    $txtNum  = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $txtUnit = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

    $convertThai = function($numStr) use ($txtNum, $txtUnit) {
        $numStr = ltrim($numStr, '0');
        if ($numStr === '') return '';
        $len = strlen($numStr);
        $res = '';
        for ($i = 0; $i < $len; $i++) {
            $digit = (int)$numStr[$i];
            $pos   = ($len - $i - 1) % 6;
            if ($pos === 0 && $i > 0 && ($len - $i - 1) > 0) {
                $res .= 'ล้าน';
            }
            if ($digit !== 0) {
                if ($pos === 1 && $digit === 1) {
                    $res .= 'สิบ';
                } elseif ($pos === 1 && $digit === 2) {
                    $res .= 'ยี่สิบ';
                } elseif ($pos === 0 && $digit === 1 && $len > 1 && (int)$numStr[$len - 2] !== 0) {
                    $res .= 'เอ็ด';
                } else {
                    $res .= $txtNum[$digit] . $txtUnit[$pos];
                }
            }
        }
        return $res;
    };

    $bahtStr   = $convertThai($intPart);
    $satangStr = $convertThai($decPart);
    if ($bahtStr === '' && $satangStr === '') return 'ศูนย์บาทถ้วน';
    $out = ($bahtStr !== '') ? $bahtStr . 'บาท' : '';
    $out .= ($satangStr !== '') ? $satangStr . 'สตางค์' : 'ถ้วน';
    return $out;
}

function formatDateEnDash($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    return $ts ? date('d-F-Y', $ts) : $dateStr;
}

function formatDateEnSpace($dateStr) {
    if (empty($dateStr)) return '';
    $ts = strtotime($dateStr);
    return $ts ? date('j F Y', $ts) : $dateStr;
}

// =========================================================================
// 3. เตรียมข้อมูลแยกตามฟอร์มตามเงื่อนไข VB (Jn.txt)
// =========================================================================
// เช็กว่ามีรายการหักภาษี ณ ที่จ่ายหรือไม่ (EXPENSE_CATEGORY <> '')
$taxItems = array_values(array_filter($items, function($it) {
    return trim($it['EXPENSE_CATEGORY'] ?? '') !== '';
}));
$hasPMNTFORM2 = (count($taxItems) > 0);

// เตรียมข้อมูลสำหรับ PMNTFORM1 (PV)
$pvLines       = [];
$cashBankCode  = '';
$cashBankName  = '';
$netPayAmount  = 0.0;

foreach ($items as $it) {
    if (trim($it['CHECK_ITEM'] ?? '') === 'C') {
        $cashBankCode = trim($it['ACCOUNT_CODE'] ?? '');
        $cashBankName = trim($it['ACCOUNT_NAME'] ?? '');
        $netPayAmount = (float)($it['AMOUNT'] ?? 0);
    } else {
        $pvLines[] = $it;
    }
}
$amountEnText = ($netPayAmount > 0) ? numberToEnglishBaht($netPayAmount) : '';

// เตรียมข้อมูลสำหรับ PMNTFORM2 (50 ทวิ 4 ฉบับ)
$form2Copies = [
    'ฉบับที่ 1 (สำหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)',
    'ฉบับที่ 2 (สำหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)',
    'ฉบับที่ 3 (สำเนา)',
    'ฉบับที่ 4 (สำเนา)'
];
$taxRegRaw    = preg_replace('/[^0-9]/', '', trim($head['TAX_REGISTRATION'] ?? ''));
$taxRegDigits = str_split(str_pad(substr($taxRegRaw, 0, 13), 13, ' ', STR_PAD_RIGHT));
$pndCode      = trim($head['TAX_CATEGORY'] ?? '');
if ($pndCode === '03') $pndCode = '3';
$payCondition = (int)($head['PAY_CONDITION'] ?? 0);
$thAddress    = trim(($head['TH_ADDRESS1'] ?? '') . ' ' . ($head['TH_ADDRESS2'] ?? ''));
$dueDate50Tawi = formatDateEnSpace($head['DUE_DATE_RAW'] ?? '');
$transDate50Tawi = formatDateEnDash($head['TRANSACTION_DATE'] ?? '');
$totalExVat   = (float)($head['TOTAL_EXCLUDEVATAMOUNT'] ?? 0);
$totalTaxAmt  = (float)($head['TOTAL_TAXAMOUNT'] ?? 0);
$totalTaxThai = numberToThaiBaht($totalTaxAmt);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8" />
    <title>Print <?php echo htmlspecialchars($journal_no); ?> - Meyer Aluminium Thailand Co.,LTD.</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background-color: #525659;
            font-family: 'Tahoma', 'Sarabun', Arial, sans-serif;
            color: #000000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* แถบปุ่มควบคุมด้านบน (ซ่อนตอนสั่งพิมพ์) */
        .print-toolbar {
            position: sticky;
            top: 0;
            z-index: 999;
            background: #1e293b;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        .btn-tool {
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print-now { background: #2563eb; color: #ffffff; }
        .btn-print-now:hover { background: #1d4ed8; }
        .btn-close-win { background: #475569; color: #ffffff; }
        .btn-close-win:hover { background: #334155; }

        /* ตั้งค่าหน้ากระดาษ A4 */
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 15px auto;
            background: #ffffff;
            padding: 10mm 12mm;
            box-shadow: 0 4px 12px rgba(0,0,0,0.35);
            position: relative;
        }

        @media print {
            body { background: #ffffff; }
            .print-toolbar { display: none !important; }
            .a4-page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
            }
            .a4-page:last-child {
                page-break-after: auto;
            }
        }

        /* =========================================================
           CSS: PMNTFORM1 (PAYMENT VOUCHER) & PMNTFORM3 (JOURNAL VOUCHER)
           ========================================================= */
        .vch-header {
            text-align: center;
            margin-bottom: 6px;
        }
        .vch-company {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .vch-title {
            font-size: 14px;
            font-weight: 700;
            margin-top: 2px;
        }
        .vch-meta {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 6px;
            font-size: 12px;
        }
        .vch-meta table td {
            padding: 1px 4px;
            font-weight: 600;
        }
        .vch-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .vch-table th, .vch-table td {
            border: 1px solid #000000;
            padding: 5px 7px;
            vertical-align: top;
        }
        .vch-table th {
            text-align: center;
            font-weight: 700;
            vertical-align: middle;
        }
        .vch-item-row td {
            border-top: none;
            border-bottom: none;
            padding: 4px 7px;
        }
        .vch-filler-row td {
            border-top: none;
            border-bottom: none;
            height: 22px;
        }
        .vch-sign-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: -1px;
        }
        .vch-sign-table td {
            border: 1px solid #000000;
            text-align: center;
            vertical-align: top;
            padding: 5px 4px;
            height: 62px;
        }

        /* =========================================================
           CSS: PMNTFORM2 (หนังสือรับรองการหักภาษี ณ ที่จ่าย 50 ทวิ)
           ========================================================= */
        .wht-wrapper {
            font-size: 11px;
            line-height: 1.32;
        }
        .wht-copy-label {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .wht-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 4px;
        }
        .wht-box {
            border: 1px solid #000000;
            padding: 5px 8px;
            margin-bottom: 3px;
        }
        .wht-tin-grid {
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
        }
        .wht-tin-cell {
            display: inline-block;
            width: 15px;
            height: 17px;
            border: 1px solid #000000;
            text-align: center;
            line-height: 15px;
            font-weight: 700;
            font-size: 11px;
            margin-right: -1px;
        }
        .wht-tin-dash {
            display: inline-block;
            padding: 0 3px;
            font-weight: 700;
        }
        .wht-chk {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #000000;
            text-align: center;
            line-height: 11px;
            font-weight: 700;
            font-size: 11px;
            margin-right: 4px;
            vertical-align: middle;
        }
        .wht-main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 3px;
        }
        .wht-main-table th, .wht-main-table td {
            border: 1px solid #000000;
            padding: 4px 6px;
            vertical-align: top;
        }
        .wht-main-table th {
            text-align: center;
            font-weight: 700;
            vertical-align: middle;
        }
        .wht-income-cell td {
            border-top: none;
            border-bottom: none;
            padding: 2px 6px;
        }
        .wht-dotted {
            border-bottom: 1px dotted #000000;
            display: inline-block;
            padding: 0 4px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
    </style>
</head>
<body>

<div class="print-toolbar">
    <div>
        <strong>Print Preview : <?php echo htmlspecialchars($journal_no); ?></strong>
        <span style="font-size:12px; color:#cbd5e1; margin-left:10px;">
            (เอกสารที่จะพิมพ์:
            <?php
                $forms = [];
                if ($journal_type === 'PV') $forms[] = 'Payment Voucher (PMNTFORM1)';
                if ($journal_type === 'JV') $forms[] = 'Journal Voucher (PMNTFORM3)';
                if ($hasPMNTFORM2) $forms[] = 'หนังสือรับรองหักภาษี ณ ที่จ่าย 4 ฉบับ (PMNTFORM2)';
                echo count($forms) > 0 ? implode(' + ', $forms) : 'ไม่มีฟอร์มที่ตรงเงื่อนไขพิมพ์';
            ?>)
        </span>
    </div>
    <div style="display:flex; gap:8px;">
        <button type="button" class="btn-tool btn-print-now" onclick="window.print()">&#128424; สั่งพิมพ์เอกสาร (Print)</button>
        <button type="button" class="btn-tool btn-close-win" onclick="window.close()">ปิดหน้าต่าง</button>
    </div>
</div>

<?php
// =========================================================================
// FORM 1: PAYMENT VOUCHER (พิมพ์เฉพาะเมื่อ JOURNAL_TYPE = 'PV')
// =========================================================================
if ($journal_type === 'PV'):
    $minRows = 8;
    $emptyRows = max(0, $minRows - count($pvLines));
?>
<div class="a4-page">
    <div class="vch-header">
        <div class="vch-company">MEYER ALUMINIUM (THAILAND) CO., LTD.</div>
        <div class="vch-title">PAYMENT VOUCHER</div>
    </div>

    <div class="vch-meta">
        <table>
            <tr>
                <td>NO.</td>
                <td>:</td>
                <td style="min-width:110px;"><?php echo htmlspecialchars($journal_no); ?></td>
            </tr>
            <tr>
                <td>DATE</td>
                <td>:</td>
                <td><?php echo htmlspecialchars(formatDateEnDash($head['TRANSACTION_DATE'] ?? '')); ?></td>
            </tr>
        </table>
    </div>

    <table class="vch-table">
        <tr>
            <td style="width: 28%; height: 42px;">
                <span class="font-bold">DEPARTMENT :</span> <?php echo htmlspecialchars($head['DEPARTMENT'] ?? ''); ?>
            </td>
            <td colspan="4" rowspan="2" style="width: 72%; vertical-align: top;">
                <div style="margin-bottom: 6px;">
                    <span class="font-bold" style="display:inline-block; width:72px;">PAY TO</span> :
                    <span class="font-bold"><?php echo htmlspecialchars($head['EN_COMPANY'] ?? $head['TH_COMPANY'] ?? $head['CSTMSPPL_ID']); ?></span>
                </div>
                <div style="margin-bottom: 12px;">
                    <span class="font-bold" style="display:inline-block; width:72px;">AMOUNT</span> :
                    <span class="font-bold"><?php echo htmlspecialchars($amountEnText); ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <div>
                        <span class="font-bold">CHEQUE NO. :</span> <?php echo htmlspecialchars($head['CHEQUE_NO'] ?? ''); ?>
                    </div>
                    <div style="padding-right: 25px;">
                        <span class="font-bold">DUE DATE :</span> <?php echo htmlspecialchars(formatDateEnDash($head['DUE_DATE_RAW'] ?? '')); ?>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="height: 36px;">
                <span class="font-bold">CASH FLOW TYPE :</span> <?php echo htmlspecialchars($head['CASH_FLOW'] ?? ''); ?>
            </td>
        </tr>
        <tr>
            <th style="width: 17%;">INVOICE# OR<br>REFERENCE</th>
            <th style="width: 11%;">ACCOUNT<br>CODE</th>
            <th style="width: 10%;">COST<br>CENTER</th>
            <th style="width: 45%;">ACCOUNT NAME</th>
            <th style="width: 17%;">AMOUNT</th>
        </tr>

        <?php foreach ($pvLines as $line): ?>
        <tr class="vch-item-row">
            <td class="text-center"><?php echo htmlspecialchars($line['INVOICE_NO'] ?? ''); ?></td>
            <td class="text-center"><?php echo htmlspecialchars($line['ACCOUNT_CODE'] ?? ''); ?></td>
            <td class="text-center"><?php echo htmlspecialchars($line['COST_CENTER'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($line['ACCOUNT_NAME'] ?? ''); ?></td>
            <td class="text-right"><?php echo number_format((float)$line['AMOUNT'], 2); ?></td>
        </tr>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $emptyRows; $i++): ?>
        <tr class="vch-filler-row">
            <td>&nbsp;</td><td></td><td></td><td></td><td></td>
        </tr>
        <?php endfor; ?>

        <tr>
            <td class="font-bold">NET PAY (CR. BANK A/C)</td>
            <td class="text-center font-bold"><?php echo htmlspecialchars($cashBankCode); ?></td>
            <td></td>
            <td class="font-bold"><?php echo htmlspecialchars($cashBankName); ?></td>
            <td class="text-right font-bold"><?php echo $netPayAmount > 0 ? number_format($netPayAmount, 2) : ''; ?></td>
        </tr>
        <tr>
            <td colspan="5" style="height: 58px;">
                <span class="font-bold">PAYMENT FOR :</span> <?php echo nl2br(htmlspecialchars($head['REMARK'] ?? '')); ?>
            </td>
        </tr>
    </table>

    <table class="vch-sign-table">
        <tr>
            <td style="width: 20%;">PREPARED BY</td>
            <td style="width: 20%;">CHECKED BY</td>
            <td style="width: 20%;">APPROVED BY</td>
            <td style="width: 20%;">PAID BY</td>
            <td style="width: 20%;">RECEIVED BY</td>
        </tr>
    </table>
</div>
<?php endif; ?>

<?php
// =========================================================================
// FORM 3: JOURNAL VOUCHER (พิมพ์เฉพาะเมื่อ JOURNAL_TYPE = 'JV')
// =========================================================================
if ($journal_type === 'JV'):
    $minRowsJV = 10;
    $emptyRowsJV = max(0, $minRowsJV - count($items));
?>
<div class="a4-page">
    <div class="vch-header">
        <div class="vch-company">MEYER ALUMINIUM (THAILAND) CO., LTD.</div>
        <div class="vch-title">JOURNAL VOUCHER</div>
    </div>

    <div class="vch-meta">
        <table>
            <tr>
                <td>NO.</td>
                <td>:</td>
                <td style="min-width:110px;"><?php echo htmlspecialchars($journal_no); ?></td>
            </tr>
            <tr>
                <td>DATE</td>
                <td>:</td>
                <td><?php echo htmlspecialchars(formatDateEnDash($head['TRANSACTION_DATE'] ?? '')); ?></td>
            </tr>
        </table>
    </div>

    <table class="vch-table">
        <tr>
            <th style="width: 13%;">ACCOUNT<br>CODE</th>
            <th style="width: 11%;">COST<br>GROUP</th>
            <th style="width: 44%;">ACCOUNT NAME</th>
            <th style="width: 16%;">DEBIT</th>
            <th style="width: 16%;">CREDIT</th>
        </tr>

        <?php foreach ($items as $line):
            $isD = (strtoupper(trim($line['DEBIT_CREDIT'] ?? '')) === 'D');
            $amt = (float)($line['AMOUNT'] ?? 0);
        ?>
        <tr class="vch-item-row">
            <td class="text-center"><?php echo htmlspecialchars($line['ACCOUNT_CODE'] ?? ''); ?></td>
            <td class="text-center"><?php echo htmlspecialchars($line['COST_CENTER'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($line['ACCOUNT_NAME'] ?? ''); ?></td>
            <td class="text-right"><?php echo $isD ? number_format($amt, 2) : ''; ?></td>
            <td class="text-right"><?php echo !$isD ? number_format($amt, 2) : ''; ?></td>
        </tr>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $emptyRowsJV; $i++): ?>
        <tr class="vch-filler-row">
            <td>&nbsp;</td><td></td><td></td><td></td><td></td>
        </tr>
        <?php endfor; ?>

        <tr>
            <td colspan="3" class="text-right font-bold" style="padding-right: 12px;">TOTAL DEBIT/CREDIT</td>
            <td class="text-right font-bold"><?php echo number_format((float)$head['TOTAL_DEBITAMOUNT'], 2); ?></td>
            <td class="text-right font-bold"><?php echo number_format((float)$head['TOTAL_CREDITAMOUNT'], 2); ?></td>
        </tr>
        <tr>
            <td colspan="5" style="height: 62px;">
                <span class="font-bold">REMARK :</span> <?php echo nl2br(htmlspecialchars($head['REMARK'] ?? '')); ?>
            </td>
        </tr>
    </table>

    <table class="vch-sign-table">
        <tr>
            <td style="width: 33.33%;">PREPARED BY</td>
            <td style="width: 33.33%;">CHECKED BY</td>
            <td style="width: 33.34%;">APPROVED BY</td>
        </tr>
    </table>
</div>
<?php endif; ?>

<?php
// =========================================================================
// FORM 2: หนังสือรับรองการหักภาษี ณ ที่จ่าย 50 ทวิ (พิมพ์ 4 ฉบับเมื่อมี EXPENSE_CATEGORY)
// =========================================================================
if ($hasPMNTFORM2):
    foreach ($form2Copies as $copyTitle):
?>
<div class="a4-page">
    <div class="wht-wrapper">
        <div class="wht-copy-label"><?php echo htmlspecialchars($copyTitle); ?></div>

        <div class="wht-top-bar">
            <div>
                <span style="font-size:14px; font-weight:700;">หนังสือรับรองการหักภาษี ณ ที่จ่าย</span>
                <span style="font-size:11px; margin-left:6px;">ตามมาตรา 50 ทวิ แห่งประมวลรัษฎากร</span>
            </div>
            <div>
                <span class="font-bold">เลขที่</span>
                <span class="font-bold" style="font-size:13px; margin-left:6px;"><?php echo htmlspecialchars($head['TAXDEDUCT_NO'] ?? ''); ?></span>
            </div>
        </div>

        <!-- กล่องผู้มีหน้าที่หักภาษี ณ ที่จ่าย (บริษัทฯ) -->
        <div class="wht-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span class="font-bold">ผู้มีหน้าที่หักภาษี ณ ที่จ่าย :-</span>
                <div>
                    <span class="font-bold" style="margin-right:4px;">เลขที่ประจำตัวผู้เสียภาษีอากร (13 หลัก)</span>
                    <span class="wht-tin-grid">
                        <span class="wht-tin-cell">0</span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell">2</span><span class="wht-tin-cell">0</span><span class="wht-tin-cell">5</span><span class="wht-tin-cell">5</span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell">4</span><span class="wht-tin-cell">0</span><span class="wht-tin-cell">0</span><span class="wht-tin-cell">0</span><span class="wht-tin-cell">5</span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell">6</span><span class="wht-tin-cell">8</span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell">4</span>
                    </span>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                <div>
                    <span class="font-bold">ชื่อ</span>
                    <span class="font-bold" style="margin-left:10px;">บริษัท ไมเออร์ อลูมิเนียม (ประเทศไทย) จำกัด</span>
                    <div style="font-size:9px; color:#333; margin-left:24px;">(ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล)</div>
                </div>
                <div>
                    <span class="font-bold" style="margin-right:4px;">เลขที่ประจำตัวผู้เสียภาษีอากร</span>
                    <span class="wht-tin-grid">
                        <?php for($k=0;$k<10;$k++): ?><span class="wht-tin-cell">&nbsp;</span><?php if(in_array($k,[0,4,8])) echo '<span class="wht-tin-dash">-</span>'; endfor; ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="font-bold">ที่อยู่</span>
                <span class="font-bold" style="margin-left:8px;">38/32 หมู่ 5 ต.ทุ่งสุขลา อ.ศรีราชา จ.ชลบุรี 20230</span>
                <div style="font-size:9px; color:#333; margin-left:24px;">(ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด)</div>
            </div>
        </div>

        <!-- กล่องผู้ถูกหักภาษี ณ ที่จ่าย (Supplier) -->
        <div class="wht-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span class="font-bold">ผู้ถูกหักภาษี ณ ที่จ่าย :-</span>
                <div>
                    <span class="font-bold" style="margin-right:4px;">เลขที่ประจำตัวผู้เสียภาษีอากร (13 หลัก)</span>
                    <span class="wht-tin-grid">
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[0]); ?></span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[1]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[2]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[3]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[4]); ?></span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[5]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[6]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[7]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[8]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[9]); ?></span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[10]); ?></span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[11]); ?></span><span class="wht-tin-dash">-</span>
                        <span class="wht-tin-cell"><?php echo htmlspecialchars($taxRegDigits[12]); ?></span>
                    </span>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                <div>
                    <span class="font-bold">ชื่อ</span>
                    <span class="font-bold" style="margin-left:10px;"><?php echo htmlspecialchars($head['TH_COMPANY'] ?: $head['EN_COMPANY'] ?: ''); ?></span>
                    <div style="font-size:9px; color:#333; margin-left:24px;">(ให้ระบุว่าเป็น บุคคล นิติบุคคล บริษัท สมาคม หรือคณะบุคคล)</div>
                </div>
                <div>
                    <span class="font-bold" style="margin-right:4px;">เลขที่ประจำตัวผู้เสียภาษีอากร</span>
                    <span class="wht-tin-grid">
                        <?php for($k=0;$k<10;$k++): ?><span class="wht-tin-cell">&nbsp;</span><?php if(in_array($k,[0,4,8])) echo '<span class="wht-tin-dash">-</span>'; endfor; ?>
                    </span>
                </div>
            </div>
            <div>
                <span class="font-bold">ที่อยู่</span>
                <span class="font-bold" style="margin-left:8px;"><?php echo htmlspecialchars($thAddress); ?></span>
                <div style="font-size:9px; color:#333; margin-left:24px;">(ให้ระบุ ชื่ออาคาร/หมู่บ้าน ห้องเลขที่ ชั้นที่ เลขที่ ตรอก/ซอย หมู่ที่ ถนน ตำบล/แขวง อำเภอ/เขต จังหวัด)</div>
            </div>
        </div>

        <!-- กล่องลำดับที่ในแบบ ภ.ง.ด. -->
        <div class="wht-box">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <tr>
                    <td style="width:32%; vertical-align:top;">
                        <span class="font-bold">ลำดับที่</span>
                        <span style="display:inline-block; width:55px; border:1px solid #000; height:16px; vertical-align:middle; margin:0 4px;"></span>
                        <span class="font-bold">ในแบบ</span>
                        <div style="font-size:8.5px; margin-top:2px;">(ให้สามารถอ้างอิงหรือสอบยันกันได้ระหว่างลำดับที่ตาม<br>หนังสือรับรองฯ กับแบบยื่นรายการภาษีหักที่จ่าย)</div>
                    </td>
                    <td style="width:17%;"><span class="wht-chk">&nbsp;</span>(1) ภ.ง.ด. 1ก</td>
                    <td style="width:19%;"><span class="wht-chk">&nbsp;</span>(2) ภ.ง.ด. 1ก พิเศษ</td>
                    <td style="width:16%;"><span class="wht-chk">&nbsp;</span>(3) ภ.ง.ด. 2</td>
                    <td style="width:16%;"><span class="wht-chk"><?php echo ($pndCode === '3') ? 'X' : '&nbsp;'; ?></span>(4) ภ.ง.ด. 3</td>
                </tr>
                <tr>
                    <td></td>
                    <td><span class="wht-chk">&nbsp;</span>(5) ภ.ง.ด. 2ก</td>
                    <td><span class="wht-chk">&nbsp;</span>(6) ภ.ง.ด. 3ก</td>
                    <td><span class="wht-chk"><?php echo ($pndCode === '53') ? 'X' : '&nbsp;'; ?></span>(7) ภ.ง.ด. 53</td>
                    <td></td>
                </tr>
            </table>
        </div>

        <!-- ตารางประเภทเงินได้พึงประเมินที่จ่าย -->
        <table class="wht-main-table">
            <tr>
                <th style="width:55%;">ประเภทเงินได้พึงประเมินที่จ่าย</th>
                <th style="width:17%;">วัน เดือน หรือ<br>ปีภาษี ที่จ่าย</th>
                <th style="width:15%;">จำนวนเงินที่จ่าย</th>
                <th style="width:13%;">ภาษีที่หัก<br>และนำส่งไว้</th>
            </tr>
            <tr class="wht-income-cell">
                <td>1. เงินเดือน ค่าจ้าง เบี้ยเลี้ยง โบนัส ฯลฯ ตามมาตรา 40 (1)</td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="wht-income-cell">
                <td>2. ค่าธรรมเนียม ค่านายหน้า ฯลฯ ตามมาตรา 40 (2)</td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="wht-income-cell">
                <td>3. ค่าแห่งลิขสิทธิ์ ฯลฯ ตามมาตรา 40 (3)</td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="wht-income-cell">
                <td>4. (ก) ค่าดอกเบี้ย ฯลฯ ตามมาตรา 40 (4) (ก)</td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="wht-income-cell">
                <td>
                    &nbsp;&nbsp;&nbsp;(ข) เงินปันผล เงินส่วนแบ่งกำไร ฯลฯ ตามมาตรา 40 (4) (ข)<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(1) กรณีผู้ได้รับเงินปันผลได้รับเครดิตภาษี โดยจ่ายจากกำไรสุทธิของกิจการที่ต้องเสียภาษีเงินได้นิติบุคคลในอัตราดังนี้<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(1.1) อัตราร้อยละ 30 ของกำไรสุทธิ<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(1.2) อัตราร้อยละ 25 ของกำไรสุทธิ<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(1.3) อัตราร้อยละ 20 ของกำไรสุทธิ<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(1.4) อัตราอื่นๆ ระบุ..........................................ของกำไรสุทธิ<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2) กรณีผู้ได้รับเงินปันผลไม่ได้รับเครดิตภาษี เนื่องจากจ่ายจาก<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2.1) กำไรสุทธิของกิจการที่ได้รับยกเว้นภาษีเงินได้นิติบุคคล<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2.2) เงินปันผลหรือเงินส่วนแบ่งของกำไรที่ได้รับยกเว้นไม่ต้องนำมารวมคำนวณเป็นรายได้เพื่อเสียภาษีเงินได้นิติบุคคล<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2.3) กำไรสุทธิส่วนที่ได้หักผลขาดทุนสุทธิยกมาไม่เกิน 5 ปี ก่อนรอบระยะเวลาบัญชีปีปัจจุบัน<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2.4) กำไรที่รับรู้ทางบัญชีโดยวิธีส่วนได้เสีย (equity method)<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;(2.5) อื่นๆ (ระบุ) ....................................................................
                </td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="wht-income-cell">
                <td>
                    5. การจ่ายเงินได้ที่ต้องหักภาษี ณ ที่จ่าย ตามคำสั่งกรมสรรพากรที่ออกตาม มาตรา 3 เตรส<br>
                    &nbsp;&nbsp;&nbsp;เช่น รางวัล ส่วนลดหรือประโยชน์ใดๆ เนื่องจากการส่งเสริมการขาย รางวัลในการประกวด<br>
                    &nbsp;&nbsp;&nbsp;การแข่งขัน การชิงโชค ค่าแสดงของนักแสดงสาธารณะ ค่าจ้างทำของ ฯลฯ ระบุ
                </td>
                <td></td><td></td><td></td>
            </tr>

            <!-- แสดงรายการค่าใช้จ่ายที่หักภาษี ณ ที่จ่ายจริงจาก JRNLVCHR20 -->
            <?php foreach ($taxItems as $tItem):
                $expLabel = trim($tItem['EXPENSE_CATEGORY'] ?? '') . ' ' . (float)($tItem['TAX_PERCENT'] ?? 0) . ' %';
            ?>
            <tr class="wht-income-cell">
                <td style="padding-left: 16px;">
                    <span class="wht-dotted font-bold" style="min-width: 180px;"><?php echo htmlspecialchars($expLabel); ?></span>
                </td>
                <td class="text-center font-bold"><?php echo htmlspecialchars($dueDate50Tawi); ?></td>
                <td class="text-right font-bold"><?php echo number_format((float)$tItem['EXCLUDEVAT_AMOUNT'], 2); ?></td>
                <td class="text-right font-bold"><?php echo number_format((float)$tItem['AMOUNT'], 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="wht-income-cell">
                <td style="padding-bottom: 6px;">6. อื่นๆ ............................................................................................................</td>
                <td></td><td></td><td></td>
            </tr>

            <tr>
                <td class="text-right font-bold" style="padding-right: 12px;">รวม เงินที่จ่าย และภาษีที่หักนำส่ง</td>
                <td></td>
                <td class="text-right font-bold"><?php echo number_format($totalExVat, 2); ?></td>
                <td class="text-right font-bold"><?php echo number_format($totalTaxAmt, 2); ?></td>
            </tr>
            <tr>
                <td colspan="4" style="padding: 5px 8px;">
                    <span class="font-bold">รวมเงินภาษีที่หักนำส่ง (ตัวอักษร)</span>
                    <span class="font-bold" style="background:#e2e8f0; padding:2px 10px; margin-left:8px;"><?php echo htmlspecialchars($totalTaxThai); ?></span>
                </td>
            </tr>
        </table>

        <!-- กองทุน -->
        <div class="wht-box" style="font-size:10px;">
            <span class="font-bold">เงินที่จ่ายเข้า</span> กบข./กสจ./กองทุนสงเคราะห์ครูโรงเรียนเอกชน .................... บาท &nbsp; กองทุนประกันสังคม .................... บาท &nbsp; กองทุนสำรองเลี้ยงชีพ .................... บาท
        </div>

        <!-- ผู้จ่ายเงิน -->
        <div class="wht-box" style="font-size:10.5px;">
            <span class="font-bold" style="margin-right:12px;">ผู้จ่ายเงิน</span>
            <span style="margin-right:16px;"><span class="wht-chk"><?php echo ($payCondition === 1) ? 'X' : '&nbsp;'; ?></span>(1) หักภาษี ณ ที่จ่าย</span>
            <span style="margin-right:16px;"><span class="wht-chk"><?php echo ($payCondition === 2) ? 'X' : '&nbsp;'; ?></span>(2) ออกให้ตลอดไป</span>
            <span style="margin-right:16px;"><span class="wht-chk"><?php echo ($payCondition === 3) ? 'X' : '&nbsp;'; ?></span>(3) ออกให้ครั้งเดียว</span>
            <span><span class="wht-chk"><?php echo ($payCondition === 4) ? 'X' : '&nbsp;'; ?></span>(4) อื่นๆ ให้ระบุ ..............................</span>
        </div>

        <!-- คำเตือน และ ส่วนลงชื่อรับรอง -->
        <table style="width:100%; border-collapse:collapse; margin-bottom:3px; font-size:10px;">
            <tr>
                <td style="width:36%; border:1px solid #000; padding:6px; vertical-align:top;">
                    <span class="font-bold">คำเตือน</span> ผู้มีหน้าที่ออกหนังสือรับรองการหักภาษี ณ ที่จ่าย<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;ฝ่าฝืนไม่ปฏิบัติตามมาตรา 50 ทวิ แห่งประมวล<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;รัษฎากร ต้องรับโทษทางอาญาตามมาตรา 35<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;แห่งประมวลรัษฎากร
                </td>
                <td style="width:64%; border:1px solid #000; padding:6px; vertical-align:top; position:relative;">
                    <div class="text-center font-bold" style="margin-bottom:16px;">ขอรับรองว่า ข้อความและตัวเลขดังกล่าวข้างต้นถูกต้องตรงกับความจริงทุกประการ</div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; padding:0 12px;">
                        <div style="width:75%; text-align:center;">
                            <div>(ลงชื่อ) .................................................................... ผู้จ่ายเงิน</div>
                            <div class="font-bold" style="margin-top:6px;"><?php echo htmlspecialchars($transDate50Tawi); ?></div>
                            <div style="border-top:1px dotted #000; width:60%; margin:2px auto;"></div>
                            <div>วัน เดือน ปี ที่ออกหนังสือรับรองฯ</div>
                        </div>
                        <div style="width:52px; height:52px; border:1px solid #000; border-radius:50%; text-align:center; font-size:8.5px; display:flex; align-items:center; justify-content:center;">
                            ประทับตรา<br>นิติบุคคล<br>(ถ้ามี)
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div style="font-size:9px; color:#222;">
            <span class="font-bold">หมายเหตุ เลขประจำตัวผู้เสียภาษีอากร (13 หลัก)* หมายถึง</span>
            1. กรณีบุคคลธรรมดาไทย ให้ใช้เลขประจำตัวประชาชนของกรมการปกครอง &nbsp;
            2. กรณีนิติบุคคล ให้ใช้เลขทะเบียนนิติบุคคลของกรมพัฒนาธุรกิจการค้า &nbsp;
            3. กรณีอื่นๆ นอกเหนือจาก 1. และ 2. ให้ใช้เลขประจำตัวผู้เสียภาษีอากร (13 หลัก) ของกรมสรรพากร
        </div>
    </div>
</div>
<?php
    endforeach;
endif;
?>

</body>
</html>