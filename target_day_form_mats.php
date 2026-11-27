<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['ID'])) { header("Location: index.php"); exit(); }

include 'function_mats.php';
include 'dbcon_mats-new.php';

$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : (isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '');
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'new';
if (!in_array($mode, ['new', 'edit'], true)) {
    $mode = 'new';
}
$is_edit = ($mode === 'edit');

$target_year = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
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

$default_day_row = [
    'TARGET_YEAR'   => (string)$target_year,
    'TARGET_MONTH'  => $target_month,
    'AMOUNT_MONTH'  => 0,
    'WORKDAY_MONTH' => 0,
];
for ($d = 1; $d <= 31; $d++) {
    $default_day_row['AMOUNT_' . str_pad((string)$d, 2, '0', STR_PAD_LEFT)] = 0;
}

$data3 = $default_day_row;
$data5 = $default_day_row;

if ($is_edit) {
    $stmt3 = $conn->prepare("SELECT * FROM TRGTMSTR3 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
    $stmt3->execute([':year' => (string)$target_year, ':month' => $target_month]);
    $db3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($db3) { $data3 = array_merge($data3, $db3); }

    $stmt5 = $conn->prepare("SELECT * FROM TRGTMSTR5 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
    $stmt5->execute([':year' => (string)$target_year, ':month' => $target_month]);
    $db5 = $stmt5->fetch(PDO::FETCH_ASSOC);
    if ($db5) { $data5 = array_merge($data5, $db5); }
} else {
    // โหมด New: ดึงข้อมูลปีก่อนหน้าในเดือนเดียวกันมาเป็นค่าเริ่มต้นหากมี
    $prev_year = (string)($target_year - 1);

    $stmt3 = $conn->prepare("SELECT * FROM TRGTMSTR3 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
    $stmt3->execute([':year' => $prev_year, ':month' => $target_month]);
    $prev3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    if ($prev3) { $data3 = array_merge($data3, $prev3); }

    $stmt5 = $conn->prepare("SELECT * FROM TRGTMSTR5 WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
    $stmt5->execute([':year' => $prev_year, ':month' => $target_month]);
    $prev5 = $stmt5->fetch(PDO::FETCH_ASSOC);
    if ($prev5) { $data5 = array_merge($data5, $prev5); }
}

$days_in_month = (int)date('t', mktime(0, 0, 0, (int)$target_month, 1, $target_year));
$month_name = isset($months_meta[$target_month]) ? $months_meta[$target_month] : $target_month;
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target Day Form | Meyer Aluminium</title>
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

        .page-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 20px 26px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .page-header-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .mode-badge-edit { background: #f59e0b; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
        .mode-badge-new { background: #10b981; color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }

        .period-picker {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.22);
        }
        .period-input {
            height: 34px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #1e293b;
            font-weight: 700;
            font-size: 13px;
            padding: 0 8px;
        }
        .btn-load-period {
            height: 34px;
            border-radius: 6px;
            border: none;
            background: #3b82f6;
            color: #ffffff;
            font-weight: 700;
            font-size: 12px;
            padding: 0 12px;
            cursor: pointer;
        }
        .btn-load-period:hover { background: #2563eb; }

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

        .month-kpi-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .kpi-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
        }
        .kpi-label { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px; }
        .input-month-kpi {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            padding: 4px 6px;
        }
        .input-t3 { color: var(--blue-primary); }
        .input-t5 { color: var(--orange-primary); }

        .btn-helper {
            margin-top: 12px;
            width: 100%;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.12s;
        }
        .btn-helper:hover { background: #e2e8f0; color: #0f172a; }

        .form-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 22px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }
        .form-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border-color);
        }
        .btn-tool {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 7px;
            cursor: pointer;
        }
        .btn-tool:hover { background: #e2e8f0; }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        @media (max-width: 1200px) { .days-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .days-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .days-grid { grid-template-columns: 1fr; } }

        .day-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            overflow: hidden;
        }
        .day-card.day-sunday {
            border-color: #fde68a;
            background: #fffdf5;
        }
        .day-card.day-invalid {
            background: #f8fafc;
            opacity: 0.72;
        }
        .day-card-head {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
        }
        .day-card.day-sunday .day-card-head {
            background: #fef3c7;
            border-bottom-color: #fde68a;
            color: #92400e;
        }
        .day-card-body {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .day-field-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 5px 8px;
            border-radius: 7px;
        }
        .row-bg-3 { background: var(--blue-light); border: 1px solid var(--blue-border); }
        .row-bg-5 { background: var(--orange-light); border: 1px solid var(--orange-border); }
        .row-tag-3 { font-size: 10px; font-weight: 800; color: var(--blue-primary); white-space: nowrap; }
        .row-tag-5 { font-size: 10px; font-weight: 800; color: var(--orange-primary); white-space: nowrap; }

        .input-day-amt {
            width: 100%;
            max-width: 125px;
            height: 30px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            padding: 2px 8px;
            color: #1e293b;
        }
        .input-day-amt:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }

        .form-actions {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-save {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
        }
        .btn-save:hover { transform: translateY(-1px); }
        .btn-cancel {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-cancel:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="target_day_master_mats.php?year=<?php echo $target_year; ?>&func=<?php echo $folder_func; ?>">
                        🎯 Target Day Form
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">

                <!-- Header Banner -->
                <div class="page-header-card">
                    <div>
                        <h4 class="page-header-title">
                            <span><?php echo $is_edit ? '✏️ Edit Daily Target' : '➕ Create Daily Target'; ?></span>
                            <span class="<?php echo $is_edit ? 'mode-badge-edit' : 'mode-badge-new'; ?>">
                                <?php echo $is_edit ? 'Edit Mode' : 'New Mode'; ?>
                            </span>
                        </h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">
                            กำหนด AMOUNT_MONTH, WORKDAY_MONTH และ AMOUNT_01 – AMOUNT_31 ของ <?php echo htmlspecialchars($month_name . ' (' . $target_month . ') ' . $target_year, ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>

                    <div class="period-picker">
                        <label for="pickYear" style="margin:0; font-size:12px; font-weight:700;">ปี:</label>
                        <input type="number" id="pickYear" class="period-input" style="width:86px;" min="2000" max="2099" value="<?php echo $target_year; ?>">
                        <label for="pickMonth" style="margin:0; font-size:12px; font-weight:700;">เดือน:</label>
                        <select id="pickMonth" class="period-input">
                            <?php foreach ($months_meta as $mCode => $mLabel): ?>
                                <option value="<?php echo $mCode; ?>" <?php echo ($mCode === $target_month) ? 'selected' : ''; ?>>
                                    <?php echo $mCode . ' - ' . $mLabel; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-load-period" onclick="reloadPeriod()">โหลดเดือนนี้</button>
                    </div>
                </div>

                <form id="targetDayForm">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="target_year" value="<?php echo $target_year; ?>">
                    <input type="hidden" name="target_month" value="<?php echo $target_month; ?>">

                    <!-- Monthly Header Values -->
                    <div class="summary-grid">
                        <!-- TRGTMSTR3 -->
                        <div class="summary-card summary-card-3">
                            <div class="summary-top">
                                <h5 class="summary-heading input-t3">📊 TRGTMSTR3 (รวมวันอาทิตย์)</h5>
                                <span class="tag-3">Mon - Sun</span>
                            </div>
                            <div class="month-kpi-row">
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_MONTH</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-month-kpi input-t3"
                                           name="amount_month3" id="amount_month3"
                                           value="<?php echo (int)$data3['AMOUNT_MONTH']; ?>" required>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">WORKDAY_MONTH</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-month-kpi input-t3"
                                           name="workday_month3" id="workday_month3"
                                           value="<?php echo (int)$data3['WORKDAY_MONTH']; ?>" required>
                                </div>
                            </div>
                            <button type="button" class="btn-helper" onclick="distributeAmountToDays('3')">
                                ⚡ กระจาย AMOUNT_MONTH (T3) ลงวัน Mon-Sun ของเดือนนี้
                            </button>
                        </div>

                        <!-- TRGTMSTR5 -->
                        <div class="summary-card summary-card-5">
                            <div class="summary-top">
                                <h5 class="summary-heading input-t5">📊 TRGTMSTR5 (ไม่รวมวันอาทิตย์)</h5>
                                <span class="tag-5">Mon - Sat</span>
                            </div>
                            <div class="month-kpi-row">
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_MONTH</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-month-kpi input-t5"
                                           name="amount_month5" id="amount_month5"
                                           value="<?php echo (int)$data5['AMOUNT_MONTH']; ?>" required>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">WORKDAY_MONTH</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-month-kpi input-t5"
                                           name="workday_month5" id="workday_month5"
                                           value="<?php echo (int)$data5['WORKDAY_MONTH']; ?>" required>
                                </div>
                            </div>
                            <button type="button" class="btn-helper" onclick="distributeAmountToDays('5')">
                                ⚡ กระจาย AMOUNT_MONTH (T5) ลงวัน Mon-Sat ของเดือนนี้
                            </button>
                        </div>
                    </div>

                    <!-- Daily Columns AMOUNT_01 .. AMOUNT_31 -->
                    <div class="form-card">
                        <div class="form-toolbar">
                            <div>
                                <h5 style="margin:0; font-weight:700; color:#1e293b;">🗓️ Daily Target Input (AMOUNT_01 – AMOUNT_31)</h5>
                                <span style="font-size:12px; color:#64748b;">เดือน <?php echo htmlspecialchars($month_name . ' ' . $target_year, ENT_QUOTES, 'UTF-8'); ?> มี <?php echo $days_in_month; ?> วัน (วันที่เกินจำนวนวันของเดือนจะทำเครื่องหมาย N/A)</span>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="button" class="btn-tool" onclick="copyT3ToT5Days()">📋 คัดลอกรายวัน T3 → T5</button>
                                <button type="button" class="btn-tool" onclick="clearAllDays()">🧹 ล้างยอดรายวันเป็น 0</button>
                            </div>
                        </div>

                        <div class="days-grid">
                            <?php for ($d = 1; $d <= 31; $d++):
                                $dCode = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                                $col = 'AMOUNT_' . $dCode;
                                $isValidDay = ($d <= $days_in_month);
                                $ts = $isValidDay ? mktime(0, 0, 0, (int)$target_month, $d, $target_year) : false;
                                $dowShort = $ts !== false ? date('D', $ts) : '-';
                                $isSunday = ($ts !== false && (int)date('w', $ts) === 0);

                                $cardClass = 'day-card';
                                if (!$isValidDay) {
                                    $cardClass .= ' day-invalid';
                                } elseif ($isSunday) {
                                    $cardClass .= ' day-sunday';
                                }

                                $val3 = isset($data3[$col]) ? (float)$data3[$col] : 0.0;
                                $val5 = isset($data5[$col]) ? (float)$data5[$col] : 0.0;
                            ?>
                            <div class="<?php echo $cardClass; ?>" data-day="<?php echo $d; ?>" data-valid="<?php echo $isValidDay ? '1' : '0'; ?>" data-sunday="<?php echo $isSunday ? '1' : '0'; ?>">
                                <div class="day-card-head">
                                    <span>Day <?php echo $dCode; ?> <small style="color:#64748b;">(<?php echo $dowShort; ?>)</small></span>
                                    <span>
                                        <?php if (!$isValidDay): ?>
                                            <span style="color:#64748b;">N/A</span>
                                        <?php elseif ($isSunday): ?>
                                            <span style="color:#b45309;">SUN</span>
                                        <?php else: ?>
                                            <span style="color:#64748b;"><?php echo $col; ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="day-card-body">
                                    <div class="day-field-row row-bg-3">
                                        <span class="row-tag-3">T3</span>
                                        <input type="number" step="0.01" min="0"
                                               class="input-day-amt day-amt-3"
                                               name="amt3_<?php echo $dCode; ?>"
                                               id="amt3_<?php echo $dCode; ?>"
                                               value="<?php echo number_format($val3, 2, '.', ''); ?>" required>
                                    </div>
                                    <div class="day-field-row row-bg-5">
                                        <span class="row-tag-5">T5</span>
                                        <input type="number" step="0.01" min="0"
                                               class="input-day-amt day-amt-5"
                                               name="amt5_<?php echo $dCode; ?>"
                                               id="amt5_<?php echo $dCode; ?>"
                                               value="<?php echo number_format($val5, 2, '.', ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="form-actions">
                            <a href="target_day_master_mats.php?year=<?php echo $target_year; ?>&func=<?php echo $folder_func; ?>" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-save">💾 Save Daily Target</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script>
const currentFunc = <?php echo json_encode($folder_func); ?>;
const currentMode = <?php echo json_encode($mode); ?>;

function reloadPeriod() {
    const y = document.getElementById('pickYear').value || <?php echo $target_year; ?>;
    const m = document.getElementById('pickMonth').value || <?php echo json_encode($target_month); ?>;
    window.location.href = 'target_day_form_mats.php?mode=' + encodeURIComponent(currentMode)
        + '&year=' + encodeURIComponent(y)
        + '&month=' + encodeURIComponent(m)
        + '&func=' + encodeURIComponent(currentFunc);
}

function distributeAmountToDays(tablePrefix) {
    const amtMonthEl = document.getElementById('amount_month' + tablePrefix);
    const totalMonth = parseFloat(amtMonthEl.value) || 0;
    if (totalMonth <= 0) {
        alert('กรุณากรอก AMOUNT_MONTH ให้มากกว่า 0 ก่อนครับ');
        return;
    }

    const cards = Array.from(document.querySelectorAll('.day-card'));
    const eligibleInputs = [];

    cards.forEach(card => {
        const isValid = card.dataset.valid === '1';
        const isSunday = card.dataset.sunday === '1';
        const dCode = String(card.dataset.day).padStart(2, '0');
        const input = document.getElementById('amt' + tablePrefix + '_' + dCode);

        if (!isValid) {
            input.value = '0.00';
            return;
        }
        if (tablePrefix === '5' && isSunday) {
            input.value = '0.00';
            return;
        }
        eligibleInputs.push(input);
    });

    if (eligibleInputs.length === 0) {
        alert('ไม่พบวันที่ใช้งานได้ในเดือนนี้');
        return;
    }

    const perDay = Math.floor((totalMonth / eligibleInputs.length) * 100) / 100;
    let allocated = 0;

    eligibleInputs.forEach((inp, idx) => {
        if (idx === eligibleInputs.length - 1) {
            const rem = Math.max(0, Math.round((totalMonth - allocated) * 100) / 100);
            inp.value = rem.toFixed(2);
        } else {
            inp.value = perDay.toFixed(2);
            allocated = Math.round((allocated + perDay) * 100) / 100;
        }
    });
}

function copyT3ToT5Days() {
    if (!confirm('ต้องการคัดลอกค่า AMOUNT_01 – AMOUNT_31 จาก T3 ไปยัง T5 ใช่หรือไม่?')) return;
    for (let d = 1; d <= 31; d++) {
        const dCode = String(d).padStart(2, '0');
        const v3 = document.getElementById('amt3_' + dCode).value;
        document.getElementById('amt5_' + dCode).value = v3;
    }
}

function clearAllDays() {
    if (!confirm('ต้องการล้างยอดรายวัน AMOUNT_01 – AMOUNT_31 ทั้งหมดเป็น 0.00 ใช่หรือไม่?')) return;
    document.querySelectorAll('.input-day-amt').forEach(inp => {
        inp.value = '0.00';
    });
}

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('focus', function() {
            this.select();
        });
    });

    document.getElementById('targetDayForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('model/create_target_day_data.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            try {
                return { ok: response.ok, status: response.status, json: JSON.parse(text), raw: text };
            } catch (err) {
                return { ok: response.ok, status: response.status, json: null, raw: text };
            }
        })
        .then(res => {
            if (res.json && res.json.success) {
                alert('✅ บันทึกข้อมูลสำเร็จ!');
                const y = formData.get('target_year');
                window.location.href = 'target_day_master_mats.php?year=' + encodeURIComponent(y) + '&func=' + encodeURIComponent(currentFunc);
            } else {
                const msg = (res.json && res.json.message)
                    ? res.json.message
                    : ('HTTP ' + res.status + ': ' + res.raw.slice(0, 300));
                alert('❌ เกิดข้อผิดพลาด: ' + msg);
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('❌ เกิดข้อผิดพลาดในการส่งคำขอ: ' + error.message);
        });
    });
});
</script>
</html>