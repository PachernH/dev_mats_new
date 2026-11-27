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

$row2 = [];
$row4 = [];
$found2 = false;
$found4 = false;

$stmt2 = $conn->prepare("SELECT * FROM TRGTMSTR2 WHERE TARGET_YEAR = :year");
$stmt2->execute([':year' => (string)$target_year]);
$db2 = $stmt2->fetch(PDO::FETCH_ASSOC);
if ($db2) {
    $row2 = $db2;
    $found2 = true;
}

$stmt4 = $conn->prepare("SELECT * FROM TRGTMSTR4 WHERE TARGET_YEAR = :year");
$stmt4->execute([':year' => (string)$target_year]);
$db4 = $stmt4->fetch(PDO::FETCH_ASSOC);
if ($db4) {
    $row4 = $db4;
    $found4 = true;
}

$months = [
    'JAN' => 'January', 'FEB' => 'February', 'MAR' => 'March', 'APR' => 'April',
    'MAY' => 'May', 'JUN' => 'June', 'JUL' => 'July', 'AUG' => 'August',
    'SEP' => 'September', 'OCT' => 'October', 'NOV' => 'November', 'DEC' => 'December'
];

$prev_year = (int)$target_year - 1;
$next_year = (int)$target_year + 1;

// อ่านค่าโดยตรงจากคอลัมน์ใน Table (ไม่คำนวณใหม่)
$wd_year2  = isset($row2['WORKDAY_YEAR']) ? (int)$row2['WORKDAY_YEAR'] : 0;
$amt_year2 = isset($row2['AMOUNT_YEAR'])  ? (float)$row2['AMOUNT_YEAR'] : 0.0;
$amt_day2  = isset($row2['AMOUNT_DAY'])   ? (float)$row2['AMOUNT_DAY'] : 0.0;

$wd_year4  = isset($row4['WORKDAY_YEAR']) ? (int)$row4['WORKDAY_YEAR'] : 0;
$amt_year4 = isset($row4['AMOUNT_YEAR'])  ? (float)$row4['AMOUNT_YEAR'] : 0.0;
$amt_day4  = isset($row4['AMOUNT_DAY'])   ? (float)$row4['AMOUNT_DAY'] : 0.0;

$chart_labels = [];
$chart_amt2 = [];
$chart_amt4 = [];
foreach ($months as $k => $m_name) {
    $chart_labels[] = $k;
    $chart_amt2[] = isset($row2['AMOUNT_' . $k]) ? (float)$row2['AMOUNT_' . $k] : 0.0;
    $chart_amt4[] = isset($row4['AMOUNT_' . $k]) ? (float)$row4['AMOUNT_' . $k] : 0.0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target Month Detail (<?php echo htmlspecialchars($target_year, ENT_QUOTES, 'UTF-8'); ?>) | Meyer Aluminium</title>
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
        .year-pill {
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
        .btn-nav-year {
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
        .btn-nav-year:hover {
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
        .summary-card-2::before { background-color: var(--blue-primary); }
        .summary-card-4::before { background-color: var(--orange-primary); }

        .summary-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .summary-heading { margin: 0; font-size: 16px; font-weight: 700; }
        .badge-m2 { background: var(--blue-light); color: var(--blue-primary); border: 1px solid var(--blue-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-m4 { background: var(--orange-light); color: var(--orange-primary); border: 1px solid var(--orange-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .badge-nodata { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; }

        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .kpi-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            text-align: center;
        }
        .kpi-title { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px; text-transform: uppercase; }
        .kpi-num { font-size: 18px; font-weight: 800; }
        .text-m2 { color: var(--blue-primary); }
        .text-m4 { color: var(--orange-primary); }

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
        .section-title { margin: 0; font-size: 17px; font-weight: 700; color: #1e293b; }

        .detail-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .detail-table thead th {
            padding: 11px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            text-align: center;
            white-space: nowrap;
        }
        .th-month { background: #f1f5f9; color: #334155; }
        .th-m2 { background: var(--blue-light); color: #1e3a8a; }
        .th-m4 { background: var(--orange-light); color: #7c2d12; }

        .detail-table tbody td {
            padding: 10px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .detail-table tbody tr:hover td { background-color: #f8fafc; }

        .td-month { font-weight: 700; color: #1e293b; white-space: nowrap; }
        .td-num { text-align: right; font-variant-numeric: tabular-nums; }
        .td-m2-amt { font-weight: 700; color: var(--blue-primary); background: rgba(239, 246, 255, 0.45); }
        .td-m4-amt { font-weight: 700; color: var(--orange-primary); background: rgba(255, 247, 237, 0.45); }

        .detail-table tfoot td {
            padding: 12px 10px;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="target_month_master_mats.php?func=<?php echo $folder_func; ?>">
                        🎯 Target Month Detail
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
                            <span>📌 Target Detail Breakdown</span>
                            <span class="year-pill">Year: <?php echo htmlspecialchars($target_year, ENT_QUOTES, 'UTF-8'); ?></span>
                        </h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">
                            ข้อมูลจากคอลัมน์ในตาราง TRGTMSTR2 และ TRGTMSTR4 โดยตรง
                        </p>
                    </div>
                    <div class="header-actions">
                        <a href="target_month_detail_mats.php?year=<?php echo $prev_year; ?>&func=<?php echo $folder_func; ?>" class="btn-nav-year">
                            ◀ <?php echo $prev_year; ?>
                        </a>
                        <a href="target_month_detail_mats.php?year=<?php echo $next_year; ?>&func=<?php echo $folder_func; ?>" class="btn-nav-year">
                            <?php echo $next_year; ?> ▶
                        </a>
                        <a href="target_month_master_mats.php?func=<?php echo $folder_func; ?>" class="btn-back-master">
                            Back to Master
                        </a>
                        <a href="target_month_form_mats.php?mode=edit&year=<?php echo htmlspecialchars($target_year, ENT_QUOTES, 'UTF-8'); ?>&func=<?php echo $folder_func; ?>" class="btn-edit-target">
                            ✏️ Edit Target
                        </a>
                    </div>
                </div>

                <!-- Yearly Summary Cards (ค่าตรงจาก WORKDAY_YEAR, AMOUNT_YEAR, AMOUNT_DAY) -->
                <div class="summary-grid">
                    <!-- TRGTMSTR2 -->
                    <div class="summary-card summary-card-2">
                        <div class="summary-top">
                            <h5 class="summary-heading text-m2">📊 TRGTMSTR2</h5>
                            <div>
                                <span class="badge-m2">Table: TRGTMSTR2</span>
                                <?php if (!$found2): ?><span class="badge-nodata">ไม่พบข้อมูลปีนี้</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY_YEAR</div>
                                <div class="kpi-num text-m2"><?php echo number_format($wd_year2); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_YEAR</div>
                                <div class="kpi-num text-m2"><?php echo number_format($amt_year2, 2); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_DAY</div>
                                <div class="kpi-num text-m2"><?php echo number_format($amt_day2, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TRGTMSTR4 -->
                    <div class="summary-card summary-card-4">
                        <div class="summary-top">
                            <h5 class="summary-heading text-m4">📊 TRGTMSTR4</h5>
                            <div>
                                <span class="badge-m4">Table: TRGTMSTR4</span>
                                <?php if (!$found4): ?><span class="badge-nodata">ไม่พบข้อมูลปีนี้</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY_YEAR</div>
                                <div class="kpi-num text-m4"><?php echo number_format($wd_year4); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_YEAR</div>
                                <div class="kpi-num text-m4"><?php echo number_format($amt_year4, 2); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_DAY</div>
                                <div class="kpi-num text-m4"><?php echo number_format($amt_day4, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Breakdown Table (ค่าตรงจาก WORKDAY_{M} และ AMOUNT_{M}) -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">🗓️ Monthly Data from Tables (JAN – DEC)</h5>
                        <small style="color:#64748b;">แสดงค่าตามคอลัมน์ในฐานข้อมูลโดยตรง</small>
                    </div>
                    <div class="table-responsive">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th class="th-month" rowspan="2">Month</th>
                                    <th class="th-m2" colspan="2">TRGTMSTR2</th>
                                    <th class="th-m4" colspan="2">TRGTMSTR4</th>
                                </tr>
                                <tr>
                                    <th class="th-m2">WORKDAY</th>
                                    <th class="th-m2">AMOUNT</th>
                                    <th class="th-m4">WORKDAY</th>
                                    <th class="th-m4">AMOUNT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($months as $key => $m_name):
                                    $has_wd2  = array_key_exists('WORKDAY_' . $key, $row2) && $row2['WORKDAY_' . $key] !== null;
                                    $has_amt2 = array_key_exists('AMOUNT_' . $key, $row2)  && $row2['AMOUNT_' . $key] !== null;
                                    $wd2  = $has_wd2  ? number_format((int)$row2['WORKDAY_' . $key]) : '-';
                                    $amt2 = $has_amt2 ? number_format((float)$row2['AMOUNT_' . $key], 2) : '-';

                                    $has_wd4  = array_key_exists('WORKDAY_' . $key, $row4) && $row4['WORKDAY_' . $key] !== null;
                                    $has_amt4 = array_key_exists('AMOUNT_' . $key, $row4)  && $row4['AMOUNT_' . $key] !== null;
                                    $wd4  = $has_wd4  ? number_format((int)$row4['WORKDAY_' . $key]) : '-';
                                    $amt4 = $has_amt4 ? number_format((float)$row4['AMOUNT_' . $key], 2) : '-';
                                ?>
                                <tr>
                                    <td class="td-month"><?php echo $m_name; ?> <span style="color:#94a3b8; font-size:11px;">(<?php echo $key; ?>)</span></td>
                                    <td class="td-num"><?php echo $wd2; ?></td>
                                    <td class="td-num td-m2-amt"><?php echo $amt2; ?></td>
                                    <td class="td-num"><?php echo $wd4; ?></td>
                                    <td class="td-num td-m4-amt"><?php echo $amt4; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Yearly Column Value</td>
                                    <td class="td-num text-m2"><?php echo number_format($wd_year2); ?></td>
                                    <td class="td-num text-m2"><?php echo number_format($amt_year2, 2); ?></td>
                                    <td class="td-num text-m4"><?php echo number_format($wd_year4); ?></td>
                                    <td class="td-num text-m4"><?php echo number_format($amt_year4, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Monthly Chart (ค่าตรงจาก AMOUNT_{M}) 
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="section-title">📈 Monthly Target Comparison Chart</h5>
                    </div>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="targetCompareChart"></canvas>
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
    const ctx = document.getElementById('targetCompareChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'TRGTMSTR2',
                    data: <?php echo json_encode($chart_amt2); ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.78)',
                    borderColor: '#1d4ed8',
                    borderWidth: 1,
                    borderRadius: 6
                },
                {
                    label: 'TRGTMSTR4',
                    data: <?php echo json_encode($chart_amt4); ?>,
                    backgroundColor: 'rgba(234, 88, 12, 0.78)',
                    borderColor: '#c2410c',
                    borderWidth: 1,
                    borderRadius: 6
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