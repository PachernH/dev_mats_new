<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['ID'])) { header("Location: index.php"); exit(); }

include 'function_mats.php';
include 'dbcon_mats-new.php';

$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : (isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '');
$selected_year = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? $_GET['year'] : date('Y');

// ดึงรายการปีที่มีอยู่ใน TRGTMSTR3 และ TRGTMSTR5 (เข้ากันได้กับ SQL Server 2005)
$years = [];
$stmtY3 = $conn->query("SELECT DISTINCT TARGET_YEAR FROM TRGTMSTR3");
if ($stmtY3) {
    while ($r = $stmtY3->fetch(PDO::FETCH_ASSOC)) {
        $y = trim((string)$r['TARGET_YEAR']);
        if ($y !== '' && !in_array($y, $years, true)) {
            $years[] = $y;
        }
    }
}
$stmtY5 = $conn->query("SELECT DISTINCT TARGET_YEAR FROM TRGTMSTR5");
if ($stmtY5) {
    while ($r = $stmtY5->fetch(PDO::FETCH_ASSOC)) {
        $y = trim((string)$r['TARGET_YEAR']);
        if ($y !== '' && !in_array($y, $years, true)) {
            $years[] = $y;
        }
    }
}
if (!in_array((string)$selected_year, $years, true)) {
    $years[] = (string)$selected_year;
}
rsort($years);

// โหลดข้อมูลของปีที่เลือกจาก TRGTMSTR3 และ TRGTMSTR5 แยกตาม TARGET_MONTH ('01'..'12')
$map3 = [];
$stmt3 = $conn->prepare("SELECT * FROM TRGTMSTR3 WHERE TARGET_YEAR = :year");
$stmt3->execute([':year' => (string)$selected_year]);
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    $mCode = str_pad(trim((string)$row['TARGET_MONTH']), 2, '0', STR_PAD_LEFT);
    $map3[$mCode] = $row;
}

$map5 = [];
$stmt5 = $conn->prepare("SELECT * FROM TRGTMSTR5 WHERE TARGET_YEAR = :year");
$stmt5->execute([':year' => (string)$selected_year]);
while ($row = $stmt5->fetch(PDO::FETCH_ASSOC)) {
    $mCode = str_pad(trim((string)$row['TARGET_MONTH']), 2, '0', STR_PAD_LEFT);
    $map5[$mCode] = $row;
}

$months_meta = [
    '01' => 'January',   '02' => 'February', '03' => 'March',     '04' => 'April',
    '05' => 'May',       '06' => 'June',     '07' => 'July',      '08' => 'August',
    '09' => 'September', '10' => 'October',  '11' => 'November',  '12' => 'December'
];

$total_wd3 = 0; $total_amt3 = 0; $total_sum_day3 = 0.0;
$total_wd5 = 0; $total_amt5 = 0; $total_sum_day5 = 0.0;

$rows_view = [];
foreach ($months_meta as $mCode => $mName) {
    // ใช้ date('t', mktime(...)) แทน cal_days_in_month()
    $daysInMonth = (int)date('t', mktime(0, 0, 0, (int)$mCode, 1, (int)$selected_year));

    $r3 = isset($map3[$mCode]) ? $map3[$mCode] : null;
    $r5 = isset($map5[$mCode]) ? $map5[$mCode] : null;

    $wd3 = $r3 ? (int)$r3['WORKDAY_MONTH'] : 0;
    $amt3 = $r3 ? (int)$r3['AMOUNT_MONTH'] : 0;

    $wd5 = $r5 ? (int)$r5['WORKDAY_MONTH'] : 0;
    $amt5 = $r5 ? (int)$r5['AMOUNT_MONTH'] : 0;

    $sumDay3 = 0.0;
    $sumDay5 = 0.0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $col = 'AMOUNT_' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        if ($r3 && isset($r3[$col])) {
            $sumDay3 += (float)$r3[$col];
        }
        if ($r5 && isset($r5[$col])) {
            $sumDay5 += (float)$r5[$col];
        }
    }

    $total_wd3 += $wd3;
    $total_amt3 += $amt3;
    $total_sum_day3 += $sumDay3;

    $total_wd5 += $wd5;
    $total_amt5 += $amt5;
    $total_sum_day5 += $sumDay5;

    $rows_view[] = [
        'code'       => $mCode,
        'name'       => $mName,
        'days'       => $daysInMonth,
        'has3'       => ($r3 !== null),
        'has5'       => ($r5 !== null),
        'wd3'        => $wd3,
        'amt3'       => $amt3,
        'sum_day3'   => $sumDay3,
        'wd5'        => $wd5,
        'amt5'       => $amt5,
        'sum_day5'   => $sumDay5,
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target Day Master | Meyer Aluminium</title>
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

        .master-header-card {
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
        .master-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        .year-select {
            height: 38px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }
        .year-select option {
            color: #1e293b;
            background: #ffffff;
        }
        .btn-create-new {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.28);
            transition: all 0.15s;
        }
        .btn-create-new:hover {
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
            gap: 8px;
            flex-wrap: wrap;
        }
        .summary-heading { margin: 0; font-size: 15px; font-weight: 700; }
        .tag-3 { background: var(--blue-light); color: var(--blue-primary); border: 1px solid var(--blue-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .tag-5 { background: var(--orange-light); color: var(--orange-primary); border: 1px solid var(--orange-border); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }

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

        .table-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }
        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .table-card-title { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }

        .master-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .master-table thead th {
            padding: 11px 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
            text-align: center;
            white-space: nowrap;
        }
        .th-month { background: #f1f5f9; color: #334155; }
        .th-t3 { background: var(--blue-light); color: #1e3a8a; }
        .th-t5 { background: var(--orange-light); color: #7c2d12; }

        .master-table tbody tr.month-row {
            cursor: pointer;
            transition: background-color 0.12s ease;
        }
        .master-table tbody tr.month-row:hover td {
            background-color: #f8fafc;
        }
        .master-table tbody td {
            padding: 11px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .td-month { font-weight: 700; color: #1e293b; white-space: nowrap; }
        .td-num { text-align: right; font-variant-numeric: tabular-nums; }
        .td-t3-amt { font-weight: 700; color: var(--blue-primary); background: rgba(239, 246, 255, 0.45); }
        .td-t5-amt { font-weight: 700; color: var(--orange-primary); background: rgba(255, 247, 237, 0.45); }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .status-ready { background: #dcfce7; color: #15803d; }
        .status-empty { background: #f1f5f9; color: #64748b; }

        .action-links {
            display: inline-flex;
            gap: 6px;
            justify-content: center;
        }
        .btn-row-action {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            transition: all 0.12s;
        }
        .btn-row-action:hover {
            background: #e2e8f0;
            color: #0f172a;
            text-decoration: none;
        }
        .btn-row-edit {
            border-color: #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .btn-row-edit:hover {
            background: #dbeafe;
            color: #1e40af;
        }

        .master-table tfoot td {
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="target_day_master_mats.php?func=<?php echo $folder_func; ?>">
                        🎯 Target Day Master
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">

                <!-- Header Banner -->
                <div class="master-header-card">
                    <div>
                        <h4 class="master-title">
                            <span>📅 Target Day Master Overview</span>
                        </h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">
                            จัดการ Target รายวัน (AMOUNT_01 – AMOUNT_31) และยอดรายเดือนของ TRGTMSTR3 (รวมวันอาทิตย์) และ TRGTMSTR5 (ไม่รวมวันอาทิตย์)
                        </p>
                    </div>
                    <div class="header-controls">
                        <label for="yearFilter" style="margin:0; font-size:13px; color:#e2e8f0; font-weight:600;">ปี:</label>
                        <select id="yearFilter" class="year-select" onchange="changeYear(this.value)">
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo htmlspecialchars($y, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((string)$y === (string)$selected_year) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($y, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="target_day_form_mats.php?mode=new&year=<?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?>&func=<?php echo $folder_func; ?>" class="btn-create-new">
                            ➕ Create Daily Target
                        </a>
                    </div>
                </div>

                <!-- Summary Cards: TRGTMSTR3 & TRGTMSTR5 -->
                <div class="summary-grid">
                    <!-- TRGTMSTR3 -->
                    <div class="summary-card summary-card-3">
                        <div class="summary-top">
                            <h5 class="summary-heading text-t3">📊 TRGTMSTR3 (รวมวันอาทิตย์)</h5>
                            <span class="tag-3">Mon - Sun</span>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY รวมทั้งปี</div>
                                <div class="kpi-num text-t3"><?php echo number_format($total_wd3); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_MONTH รวม</div>
                                <div class="kpi-num text-t3"><?php echo number_format($total_amt3); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">ผลรวมรายวัน (D01-D31)</div>
                                <div class="kpi-num text-t3"><?php echo number_format($total_sum_day3, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TRGTMSTR5 -->
                    <div class="summary-card summary-card-5">
                        <div class="summary-top">
                            <h5 class="summary-heading text-t5">📊 TRGTMSTR5 (ไม่รวมวันอาทิตย์)</h5>
                            <span class="tag-5">Mon - Sat</span>
                        </div>
                        <div class="kpi-grid">
                            <div class="kpi-item">
                                <div class="kpi-title">WORKDAY รวมทั้งปี</div>
                                <div class="kpi-num text-t5"><?php echo number_format($total_wd5); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">AMOUNT_MONTH รวม</div>
                                <div class="kpi-num text-t5"><?php echo number_format($total_amt5); ?></div>
                            </div>
                            <div class="kpi-item">
                                <div class="kpi-title">ผลรวมรายวัน (D01-D31)</div>
                                <div class="kpi-num text-t5"><?php echo number_format($total_sum_day5, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Breakdown Table -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h5 class="table-card-title">🗓️ Monthly Breakdown (Year <?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?>)</h5>
                        <small style="color:#64748b;">คลิกที่แถวเดือนเพื่อดูรายละเอียดรายวัน หรือกดปุ่ม Edit เพื่อแก้ไขรายวัน (AMOUNT_01 – AMOUNT_31)</small>
                    </div>

                    <div class="table-responsive">
                        <table class="master-table">
                            <thead>
                                <tr>
                                    <th class="th-month" rowspan="2">Month</th>
                                    <th class="th-t3" colspan="3">TRGTMSTR3 (รวมวันอาทิตย์)</th>
                                    <th class="th-t5" colspan="3">TRGTMSTR5 (ไม่รวมวันอาทิตย์)</th>
                                    <th class="th-month" rowspan="2">Status</th>
                                    <th class="th-month" rowspan="2">Manage</th>
                                </tr>
                                <tr>
                                    <th class="th-t3">WD</th>
                                    <th class="th-t3">AMOUNT_MONTH</th>
                                    <th class="th-t3">Sum Daily</th>
                                    <th class="th-t5">WD</th>
                                    <th class="th-t5">AMOUNT_MONTH</th>
                                    <th class="th-t5">Sum Daily</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows_view as $item):
                                    $hasAny = ($item['has3'] || $item['has5']);
                                    $detailUrl = "target_day_detail_mats.php?year=" . urlencode($selected_year) . "&month=" . urlencode($item['code']) . "&func=" . urlencode($folder_func);
                                    $editUrl   = "target_day_form_mats.php?mode=edit&year=" . urlencode($selected_year) . "&month=" . urlencode($item['code']) . "&func=" . urlencode($folder_func);
                                ?>
                                <tr class="month-row" onclick="window.location.href='<?php echo $detailUrl; ?>'">
                                    <td class="td-month">
                                        <?php echo $item['name']; ?>
                                        <span style="color:#94a3b8; font-size:11px;">(<?php echo $item['code']; ?>)</span>
                                    </td>
                                    <td class="td-num"><?php echo number_format($item['wd3']); ?></td>
                                    <td class="td-num td-t3-amt"><?php echo number_format($item['amt3']); ?></td>
                                    <td class="td-num"><?php echo number_format($item['sum_day3'], 2); ?></td>

                                    <td class="td-num"><?php echo number_format($item['wd5']); ?></td>
                                    <td class="td-num td-t5-amt"><?php echo number_format($item['amt5']); ?></td>
                                    <td class="td-num"><?php echo number_format($item['sum_day5'], 2); ?></td>

                                    <td style="text-align:center;">
                                        <?php if ($hasAny): ?>
                                            <span class="status-pill status-ready">Configured</span>
                                        <?php else: ?>
                                            <span class="status-pill status-empty">Not Set</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align:center;" onclick="event.stopPropagation();">
                                        <div class="action-links">
                                            <a href="<?php echo $detailUrl; ?>" class="btn-row-action">Detail</a>
                                            <a href="<?php echo $editUrl; ?>" class="btn-row-action btn-row-edit">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total Year <?php echo htmlspecialchars($selected_year, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="td-num text-t3"><?php echo number_format($total_wd3); ?></td>
                                    <td class="td-num text-t3"><?php echo number_format($total_amt3); ?></td>
                                    <td class="td-num text-t3"><?php echo number_format($total_sum_day3, 2); ?></td>

                                    <td class="td-num text-t5"><?php echo number_format($total_wd5); ?></td>
                                    <td class="td-num text-t5"><?php echo number_format($total_amt5); ?></td>
                                    <td class="td-num text-t5"><?php echo number_format($total_sum_day5, 2); ?></td>

                                    <td colspan="2" style="text-align:center; color:#64748b; font-size:12px;">12 Months</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script>
function changeYear(yearVal) {
    const funcVal = <?php echo json_encode($folder_func); ?>;
    window.location.href = 'target_day_master_mats.php?year=' + encodeURIComponent(yearVal) + '&func=' + encodeURIComponent(funcVal);
}
</script>
</html>