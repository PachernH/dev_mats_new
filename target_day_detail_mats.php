<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['ID'])) { header("Location: index.php"); exit(); }

include 'function_mats.php';
include 'dbcon_mats-new.php';

$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : (isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '');
$target_year = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? $_GET['year'] : date('Y');

$raw_month = isset($_GET['month']) ? trim((string)$_GET['month']) : date('m');
if (preg_match('/^(0[1-9]|1[0-2]|[1-9])$/', $raw_month)) {
    $target_month = str_pad((string)(int)$raw_month, 2, '0', STR_PAD_LEFT);
} else {
    $target_month = date('m');
}

$months_meta = [
    '01' => 'January',   '02' => 'February', '03' => 'March',     '04' => 'April',
    '05' => 'May',       '06' => 'June',     '07' => 'July',      '08' => 'August',
    '09' => 'September', '10' => 'October',  '11' => 'November',  '12' => 'December'
];
$month_name = isset($months_meta[$target_month]) ? $months_meta[$target_month] : $target_month;

// ดึงข้อมูลของปีและเดือนที่เลือกจาก TRGTMSTR3 และ TRGTMSTR5
$row3 = [];
$found3 = false;
$stmt3 = $conn->prepare("SELECT * FROM TRGTMSTR3 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
$stmt3->execute([
    ':year'  => (string)$target_year,
    ':month' => (string)$target_month
]);
$db3 = $stmt3->fetch(PDO::FETCH_ASSOC);
if ($db3) {
    $row3 = $db3;
    $found3 = true;
}

$row5 = [];
$found5 = false;
$stmt5 = $conn->prepare("SELECT * FROM TRGTMSTR5 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
$stmt5->execute([
    ':year'  => (string)$target_year,
    ':month' => (string)$target_month
]);
$db5 = $stmt5->fetch(PDO::FETCH_ASSOC);
if ($db5) {
    $row5 = $db5;
    $found5 = true;
}

$wd_month3  = isset($row3['WORKDAY_MONTH']) ? (int)$row3['WORKDAY_MONTH'] : 0;
$amt_month3 = isset($row3['AMOUNT_MONTH'])  ? (int)$row3['AMOUNT_MONTH']  : 0;

$wd_month5  = isset($row5['WORKDAY_MONTH']) ? (int)$row5['WORKDAY_MONTH'] : 0;
$amt_month5 = isset($row5['AMOUNT_MONTH'])  ? (int)$row5['AMOUNT_MONTH']  : 0;

// จำนวนวันจริงในเดือนและปีที่เลือก (ไม่ใช้ cal_days_in_month)
$days_in_month = (int)date('t', mktime(0, 0, 0, (int)$target_month, 1, (int)$target_year));

$daily_rows = [];
$sum_day3 = 0.0;
$sum_day5 = 0.0;
$chart_labels = [];
$chart_amt3 = [];
$chart_amt5 = [];

for ($d = 1; $d <= 31; $d++) {
    $dCode = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    $col = 'AMOUNT_' . $dCode;
    $isValidDay = ($d <= $days_in_month);

    $ts = $isValidDay ? mktime(0, 0, 0, (int)$target_month, $d, (int)$target_year) : false;
    $dowShort = $ts !== false ? date('D', $ts) : '-';
    $isSunday = ($ts !== false && (int)date('w', $ts) === 0);

    $has3 = array_key_exists($col, $row3) && $row3[$col] !== null;
    $has5 = array_key_exists($col, $row5) && $row5[$col] !== null;

    $val3 = $has3 ? (float)$row3[$col] : 0.0;
    $val5 = $has5 ? (float)$row5[$col] : 0.0;

    if ($isValidDay) {
        $sum_day3 += $val3;
        $sum_day5 += $val5;
        $chart_labels[] = $dCode . ' (' . $dowShort . ')';
        $chart_amt3[] = $val3;
        $chart_amt5[] = $val5;
    }

    $daily_rows[] = [
        'day'        => $d,
        'code'       => $dCode,
        'col'        => $col,
        'is_valid'   => $isValidDay,
        'dow_short'  => $dowShort,
        'is_sunday'  => $isSunday,
        'has3'       => $has3,
        'val3'       => $val3,
        'has5'       => $has5,
        'val5'       => $val5,
    ];
}

// ลิงก์เปลี่ยนเดือนก่อนหน้า / ถัดไป
$prev_ts = mktime(0, 0, 0, (int)$target_month - 1, 1, (int)$target_year);
$next_ts = mktime(0, 0, 0, (int)$target_month + 1, 1, (int)$target_year);
$prev_year = date('Y', $prev_ts);
$prev_month = date('m', $prev_ts);
$next_year = date('Y', $next_ts);
$next_month = date('m', $next_ts);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target Day Detail (<?php echo htmlspecialchars($month_name . ' ' . $target_year, ENT_QUOTES, 'UTF-8'); ?>) | Meyer Aluminium</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        :root {
            --bg-page: #f1f5f9;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --blue-primary: #2563eb;
            --blue-light: #eff6ff;
            --blue-border: #bfdbfe;
            --orange-primary: #ea580c;
            --orange-light: #fff7ed;
            --orange-border: #fed7aa;
        }

        body {
            font-family: 'Segoe UI', 'Sarabun', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
        }

        .main-panel { background-color: var(--bg-page) !important; }
        .main-panel .content { padding: 20px !important; }

        .detail-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 20px 26px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .detail-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .period-pill {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
            padding: 4px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .btn-nav-period {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.22);
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-nav-period:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            text-decoration: none;
        }
        .btn-back-master {
            background: #ffffff;
            color: #1e293b;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-back-master:hover {
            background: #e2e8f0;
            color: #0f172a;
            text-decoration: none;
        }
        .btn-edit-target {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            transition: all 0.15s;
        }
        .btn-edit-target:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: #ffffff;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 22px;
        }
        @media (max-width: 992px) { .summary-grid { grid-template-columns: 1fr; } }

        .summary-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 6px;
        }
        .summary-card-3::before { background-color: var(--blue-primary); }
        .summary-card-5::before { background-color: var(--orange-primary); }

        .summary-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .summary-heading { margin: 0; font-size: 15px; font-weight: 700; }
        .badge-t3 { background: var(--blue-light); color: var(--blue-primary); border: 1px solid var(--blue-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-t5 { background: var(--orange-light); color: var(--orange-primary); border: 1px solid var(--orange-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-nodata { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }

        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .kpi-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        .kpi-title { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px; }
        .kpi-num { font-size: 18px; font-weight: 800; }
        .text-t3 { color: var(--blue-primary); }
        .text-t5 { color: var(--orange-primary); }

        .section-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            margin-bottom: 22px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }

        .daily-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .daily-table thead th {
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            text-align: center;
            white-space: nowrap;
        }
        .th-day { background: #f1f5f9; color: #334155; }
        .th-t3 { background: var(--blue-light); color: #1e3a8a; }
        .th-t5 { background: var(--orange-light); color: #7c2d12; }

        .daily-table tbody td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .daily-table tbody tr:hover td { background-color: #f8fafc; }
        .daily-table tbody tr.row-sunday td { background-color: #fffbeb; }
        .daily-table tbody tr.row-invalid td { background-color: #f8fafc; color: #94a3b8; }

        .td-day { font-weight: 700; color: #1e293b; white-space: nowrap; }
        .td-num { text-align: right; font-variant-numeric: tabular-nums; }
        .td-t3-amt { font-weight: 700; color: var(--blue-primary); background: rgba(239, 246, 255, 0.45); }
        .td-t5-amt { font-weight: 700; color: var(--orange-primary); background: rgba(255, 247, 237, 0.45); }

        .sun-badge {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 6px;
            margin-left: 4px;
        }
        .na-badge {
            display: inline-block;
            background: #e2e8f0;
            color: #64748b;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 6px;
            margin-left: 4px;
        }

        .daily-table tfoot td {
            padding: 12px 12px;
            font-weight: 800;
            border-top: 2px solid #cbd5e1;
            background: #f8fafc;
            font-variant-numeric: tabular-nums;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp7'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="target_day_master_mats.php?year=<?php echo urlencode($target_year); ?>&func=<?php echo $folder_func; ?>">
                        🎯 Target Day Detail
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">

                <!-- Header Banner -->
                <div class="detail-header-card">
                    <div>
                        <h4 class="detail-title">
                            <span>📌 Daily Target Breakdown</span>
                            <span class="period-pill"><?php echo htmlspecialchars($month_name . ' (' . $target_month . ') ' . $target_year, ENT_QUOTES, 'UTF-8'); ?></span>
                        </h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">
                            แสดงค่าตามคอลัมน์ใน TRGTMSTR3 (รวมวันอาทิตย์) และ TRGTMSTR5 (ไม่รวมวันอาทิตย์) โดยตรง (<?php echo $days_in_month; ?> วันในเดือน)
                        </p>
                    </div>
                    <div class="header-actions">
                        <a href="target_day_detail_mats.php?year=<?php echo urlencode($prev_year); ?>&month=<?php echo urlencode($prev_month); ?>&func=<?php echo $folder_func; ?>" class="btn-nav-period">
                            ◀ <?php echo htmlspecialchars($prev_month . '/' . $prev_year, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <a href="target_day_detail_mats.php?year=<?php echo urlencode($next_year); ?>&month=<?php echo urlencode($next_month); ?>&func=<?php echo $folder_func; ?>" class="btn-nav-period">
                            <?php echo htmlspecialchars($next_month . '/' . $next_year, ENT_QUOTES, 'UTF-8'); ?> ▶
                        </a>
                        <a href="target_day_master_mats.php?year=<?php echo urlencode($target_year); ?>&func=<?php echo $folder_func; ?>" class="btn-back-master">
                            ← Back to Master
                        </a>
                        <a href="target_day_form_mats.php?mode=edit&year=<?php echo urlencode($target_year); ?>&month=<?php echo urlencode($target_month); ?>&func=<?php echo $folder_func; ?>" class="btn-edit-target">
                            ✏️ Edit Daily Target
                        </a>
                    </div>
                </div>

                <!-- Summary Cards: TRGTMSTR3 & TRGTMSTR5 -->
                <div class="summary-grid">
                    <!-- TRGTMSTR3 -->
                    <div class="summary-card summary-card-3">
                        <div class="summary-top">
                            <h5 class="summary-heading text-t3">📊 TRGTMSTR3 (รวมวันอาทิตย์)</h5>
                            <div>
                                <span class="badge-t3">Mon - Sun</span>
                                <?php if (!$found3): ?><span class="badge-nodata">ไม่พบข้อมูลเดือนนี้</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY_MONTH</div>
                                <div class="kpi-num text-t3"><?php echo number_format($wd_month3); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_MONTH</div>
                                <div class="kpi-num text-t3"><?php echo number_format($amt_month3); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">ผลรวม AMOUNT_01-<?php echo str_pad((string)$days_in_month, 2, '0', STR_PAD_LEFT); ?></div>
                                <div class="kpi-num text-t3"><?php echo number_format($sum_day3, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TRGTMSTR5 -->
                    <div class="summary-card summary-card-5">
                        <div class="summary-top">
                            <h5 class="summary-heading text-t5">📊 TRGTMSTR5 (ไม่รวมวันอาทิตย์)</h5>
                            <div>
                                <span class="badge-t5">Mon - Sat</span>
                                <?php if (!$found5): ?><span class="badge-nodata">ไม่พบข้อมูลเดือนนี้</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY_MONTH</div>
                                <div class="kpi-num text-t5"><?php echo number_format($wd_month5); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_MONTH</div>
                                <div class="kpi-num text-t5"><?php echo number_format($amt_month5); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">ผลรวม AMOUNT_01-<?php echo str_pad((string)$days_in_month, 2, '0', STR_PAD_LEFT); ?></div>
                                <div class="kpi-num text-t5"><?php echo number_format($sum_day5, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Table (AMOUNT_01 .. AMOUNT_31) -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">🗓️ Daily Target Columns (AMOUNT_01 – AMOUNT_31)</h5>
                        <small style="color:#64748b;">ค่าอ่านตรงจากคอลัมน์ AMOUNT_01 ถึง AMOUNT_31 ในฐานข้อมูล</small>
                    </div>
                    <div class="table-responsive">
                        <table class="daily-table">
                            <thead>
                                <tr>
                                    <th class="th-day">Day / Column</th>
                                    <th class="th-day">Weekday</th>
                                    <th class="th-t3">TRGTMSTR3 (รวมอาทิตย์)</th>
                                    <th class="th-t5">TRGTMSTR5 (ไม่รวมอาทิตย์)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daily_rows as $dr):
                                    $rowClass = '';
                                    if (!$dr['is_valid']) {
                                        $rowClass = 'row-invalid';
                                    } elseif ($dr['is_sunday']) {
                                        $rowClass = 'row-sunday';
                                    }

                                    $disp3 = $dr['has3'] ? number_format($dr['val3'], 2) : '-';
                                    $disp5 = $dr['has5'] ? number_format($dr['val5'], 2) : '-';
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td class="td-day">
                                        Day <?php echo $dr['code']; ?>
                                        <span style="color:#94a3b8; font-size:11px;">(<?php echo $dr['col']; ?>)</span>
                                        <?php if ($dr['is_valid'] && $dr['is_sunday']): ?>
                                            <span class="sun-badge">SUN</span>
                                        <?php elseif (!$dr['is_valid']): ?>
                                            <span class="na-badge">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center; font-weight:600; color:#475569;">
                                        <?php echo htmlspecialchars($dr['dow_short'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="td-num td-t3-amt"><?php echo $disp3; ?></td>
                                    <td class="td-num td-t5-amt"><?php echo $disp5; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">Total Valid Days (D01–D<?php echo str_pad((string)$days_in_month, 2, '0', STR_PAD_LEFT); ?>)</td>
                                    <td class="td-num text-t3"><?php echo number_format($sum_day3, 2); ?></td>
                                    <td class="td-num text-t5"><?php echo number_format($sum_day5, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2">AMOUNT_MONTH Column</td>
                                    <td class="td-num text-t3"><?php echo number_format($amt_month3); ?></td>
                                    <td class="td-num text-t5"><?php echo number_format($amt_month5); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Daily Comparison Chart 
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">📈 Daily Target Comparison Chart (<?php echo htmlspecialchars($month_name . ' ' . $target_year, ENT_QUOTES, 'UTF-8'); ?>)</h5>
                    </div>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="dailyTargetChart"></canvas>
                    </div>
                </div>
                -->
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('dailyTargetChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'TRGTMSTR3 (รวมวันอาทิตย์)',
                    data: <?php echo json_encode($chart_amt3); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.78)',
                    borderColor: '#1d4ed8',
                    borderWidth: 1,
                    borderRadius: 4
                },
                {
                    label: 'TRGTMSTR5 (ไม่รวมวันอาทิตย์)',
                    data: <?php echo json_encode($chart_amt5); ?>,
                    backgroundColor: 'rgba(234, 88, 12, 0.78)',
                    borderColor: '#c2410c',
                    borderWidth: 1,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>
</html>