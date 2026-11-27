<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['ID'])) { header("Location: index.php"); exit(); }

include 'function_mats.php';
include 'dbcon_mats-new.php';

$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'new';
$target_year = isset($_GET['year']) ? (int)$_GET['year'] : (date('Y') + 1); 
$is_edit = ($mode === 'edit');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : (isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '');
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$default_row = [
    'WORKDAY_YEAR' => 0, 'AMOUNT_YEAR' => 0, 'AMOUNT_DAY' => 0,
    'WORKDAY_JAN' => 0, 'AMOUNT_JAN' => 0,
    'WORKDAY_FEB' => 0, 'AMOUNT_FEB' => 0,
    'WORKDAY_MAR' => 0, 'AMOUNT_MAR' => 0,
    'WORKDAY_APR' => 0, 'AMOUNT_APR' => 0,
    'WORKDAY_MAY' => 0, 'AMOUNT_MAY' => 0,
    'WORKDAY_JUN' => 0, 'AMOUNT_JUN' => 0,
    'WORKDAY_JUL' => 0, 'AMOUNT_JUL' => 0,
    'WORKDAY_AUG' => 0, 'AMOUNT_AUG' => 0,
    'WORKDAY_SEP' => 0, 'AMOUNT_SEP' => 0,
    'WORKDAY_OCT' => 0, 'AMOUNT_OCT' => 0,
    'WORKDAY_NOV' => 0, 'AMOUNT_NOV' => 0,
    'WORKDAY_DEC' => 0, 'AMOUNT_DEC' => 0,
];
$data2 = $default_row;
$data4 = $default_row;

if ($is_edit) {
    $stmt2 = $conn->prepare("SELECT * FROM TRGTMSTR2 WHERE TARGET_YEAR = :year");
    $stmt2->execute([':year' => (string)$target_year]);
    $db_data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($db_data2) { $data2 = array_merge($data2, $db_data2); }

    $stmt4 = $conn->prepare("SELECT * FROM TRGTMSTR4 WHERE TARGET_YEAR = :year");
    $stmt4->execute([':year' => (string)$target_year]);
    $db_data4 = $stmt4->fetch(PDO::FETCH_ASSOC);
    if ($db_data4) { $data4 = array_merge($data4, $db_data4); }
} else {
    $prev_year = $target_year - 1;
    
    $stmt2 = $conn->prepare("SELECT * FROM TRGTMSTR2 WHERE TARGET_YEAR = :prev_year");
    $stmt2->execute([':prev_year' => (string)$prev_year]);
    $prev_data2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($prev_data2) { $data2 = array_merge($data2, $prev_data2); }

    $stmt4 = $conn->prepare("SELECT * FROM TRGTMSTR4 WHERE TARGET_YEAR = :prev_year");
    $stmt4->execute([':prev_year' => (string)$prev_year]);
    $prev_data4 = $stmt4->fetch(PDO::FETCH_ASSOC);
    if ($prev_data4) { $data4 = array_merge($data4, $prev_data4); }
}

$months = [
    'JAN' => 'January', 'FEB' => 'February', 'MAR' => 'March', 'APR' => 'April',
    'MAY' => 'May', 'JUN' => 'June', 'JUL' => 'July', 'AUG' => 'August',
    'SEP' => 'September', 'OCT' => 'October', 'NOV' => 'November', 'DEC' => 'December'
];
?>

<!doctype html>
<html lang="en">
<head>
    <title>Target Month Form | Meyer Aluminium</title>
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

        .page-header-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }
        .page-header-title { font-size: 20px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
        .year-badge { background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 600; border: 1px solid rgba(255,255,255,0.25); }
        .mode-badge-edit { background: #f59e0b; color: #fff; font-size: 12px; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
        .mode-badge-new { background: #10b981; color: #fff; font-size: 12px; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }

        .summary-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
            margin-bottom: 24px; 
        }
        @media (max-width: 992px) { .summary-grid { grid-template-columns: 1fr; } }

        .summary-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
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

        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .summary-title { font-size: 15px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px; }
        .tag-2 { background: var(--blue-light); color: var(--blue-primary); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
        .tag-4 { background: var(--orange-light); color: var(--orange-primary); padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }

        .kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .kpi-box { 
            background: #ffffff; 
            padding: 10px 12px; 
            border-radius: 10px; 
            border: 1px solid #cbd5e1; 
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.15s;
        }
        .kpi-box:focus-within {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        .kpi-label { font-size: 11px; color: var(--text-muted); font-weight: 700; margin-bottom: 4px; }
        .val-blue { color: var(--blue-primary); }
        .val-orange { color: var(--orange-primary); }

        .input-yearly {
            width: 100%;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 17px;
            font-weight: 800;
            padding: 2px 4px;
            border-radius: 6px;
        }
        .input-yearly:focus { outline: none; background: #f8fafc; }
        .input-yearly.val-blue { color: var(--blue-primary); }
        .input-yearly.val-orange { color: var(--orange-primary); }

        .kpi-sub-hint {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 3px;
        }

        .main-form-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .month-grid { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 18px; 
        }
        @media (max-width: 1200px) { .month-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .month-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .month-grid { grid-template-columns: 1fr; } }

        .month-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .month-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
        }

        .month-card-header {
            background: #f8fafc;
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .month-name { font-weight: 700; font-size: 14px; color: #1e293b; letter-spacing: 0.3px; }
        .month-num { font-size: 11px; font-weight: 700; color: #94a3b8; background: #e2e8f0; padding: 2px 7px; border-radius: 10px; }

        .month-card-body { padding: 14px; display: flex; flex-direction: column; gap: 12px; }

        .target-group {
            padding: 10px;
            border-radius: 8px;
        }
        .target-group-2 { background-color: var(--blue-light); border: 1px solid var(--blue-border); }
        .target-group-4 { background-color: var(--orange-light); border: 1px solid var(--orange-border); }

        .target-group-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            gap: 6px;
        }
        .group-title-2 { font-size: 11px; font-weight: 700; color: var(--blue-primary); }
        .group-title-4 { font-size: 11px; font-weight: 700; color: var(--orange-primary); }

        .wd-control {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 2px 6px;
        }
        .wd-label { font-size: 10px; font-weight: 700; color: #64748b; }
        .input-wd {
            width: 48px;
            height: 24px;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            padding: 0;
        }
        .input-wd:focus { outline: none; }

        .input-target {
            width: 100%;
            height: 38px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 6px 10px;
            font-size: 15px;
            font-weight: 700;
            text-align: right;
            color: #1e293b;
            transition: all 0.15s;
        }
        .input-target:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }

        .form-actions {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .btn-save {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 11px 24px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(37, 99, 235, 0.35); }
        .btn-cancel {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            padding: 11px 22px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-cancel:hover { background: #f1f5f9; color: #1e293b; text-decoration: none; }
    </style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'gp7';?>
<?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="target_month_master_mats.php?func=<?php echo $folder_func; ?>">
                        🎯 Target Month Form
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="content" style="padding: 20px;">
            <div class="container-fluid">

                <!-- Header Banner -->
                <div class="page-header-card">
                    <div>
                        <h4 class="page-header-title">
                            <?php echo $is_edit ? '✏️ Edit Monthly Target' : '➕ Create Monthly Target'; ?>
                            <span class="<?php echo $is_edit ? 'mode-badge-edit' : 'mode-badge-new'; ?>">
                                <?php echo $is_edit ? 'Edit Mode' : 'New Mode'; ?>
                            </span>
                        </h4>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">
                            <?php if ($is_edit): ?>
                                แก้ไขข้อมูล Target รายปีและรายเดือนของปี <?php echo $target_year; ?> ได้อิสระ
                            <?php else: ?>
                                ดึงข้อมูลปี <?php echo ($target_year - 1); ?> เป็นค่าเริ่มต้นและแก้ไขได้ก่อน Save
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="year-badge">
                        📅 Target Year: <strong><?php echo $target_year; ?></strong>
                    </div>
                </div>

                <form id="targetForm">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="mode" value="<?php echo $mode; ?>">
                    <input type="hidden" name="target_year" value="<?php echo $target_year; ?>">

                    <!-- Yearly Fields -->
                    <div class="summary-grid">
                        <!-- TRGTMSTR2 Yearly -->
                        <div class="summary-card summary-card-2">
                            <div class="summary-header">
                                <h5 class="summary-title" style="color: #1e3a8a;">📊 Target รายปี แบบรวมอาทิตย์ (TRGTMSTR2)</h5>
                                <span class="tag-2">TRGTMSTR2</span>
                            </div>
                            <div class="kpi-row">
                                <div class="kpi-box">
                                    <div class="kpi-label">WORKDAY_YEAR</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-yearly val-blue"
                                           name="workday_year2" id="workday_year2"
                                           value="<?php echo (int)$data2['WORKDAY_YEAR']; ?>" required>
                                    <div class="kpi-sub-hint"><?php echo $is_edit ? 'อิสระ' : 'แก้แล้วคำนวณลงรายเดือน'; ?></div>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_YEAR</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-yearly val-blue"
                                           name="amount_year2" id="amount_year2"
                                           value="<?php echo (int)$data2['AMOUNT_YEAR']; ?>" required>
                                    <div class="kpi-sub-hint"><?php echo $is_edit ? 'อิสระ' : 'แก้แล้วคำนวณลงรายเดือน'; ?></div>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_DAY</div>
                                    <input type="number" min="0" max="999999.99" step="0.01"
                                           class="input-yearly val-blue"
                                           name="amount_day2" id="amount_day2"
                                           value="<?php echo number_format((float)$data2['AMOUNT_DAY'], 2, '.', ''); ?>" required>
                                    <div class="kpi-sub-hint">ทศนิยม 2 ตำแหน่ง</div>
                                </div>
                            </div>
                        </div>

                        <!-- TRGTMSTR4 Yearly -->
                        <div class="summary-card summary-card-4">
                            <div class="summary-header">
                                <h5 class="summary-title" style="color: #7c2d12;">📊 Target รายปี แบบไม่รวมอาทิตย์ (TRGTMSTR4)</h5>
                                <span class="tag-4">TRGTMSTR4</span>
                            </div>
                            <div class="kpi-row">
                                <div class="kpi-box">
                                    <div class="kpi-label">WORKDAY_YEAR</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-yearly val-orange"
                                           name="workday_year4" id="workday_year4"
                                           value="<?php echo (int)$data4['WORKDAY_YEAR']; ?>" required>
                                    <div class="kpi-sub-hint"><?php echo $is_edit ? 'อิสระ' : 'แก้แล้วคำนวณลงรายเดือน'; ?></div>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_YEAR</div>
                                    <input type="number" min="0" max="32767" step="1"
                                           class="input-yearly val-orange"
                                           name="amount_year4" id="amount_year4"
                                           value="<?php echo (int)$data4['AMOUNT_YEAR']; ?>" required>
                                    <div class="kpi-sub-hint"><?php echo $is_edit ? 'อิสระ' : 'แก้แล้วคำนวณลงรายเดือน'; ?></div>
                                </div>
                                <div class="kpi-box">
                                    <div class="kpi-label">AMOUNT_DAY</div>
                                    <input type="number" min="0" max="999999.99" step="0.01"
                                           class="input-yearly val-orange"
                                           name="amount_day4" id="amount_day4"
                                           value="<?php echo number_format((float)$data4['AMOUNT_DAY'], 2, '.', ''); ?>" required>
                                    <div class="kpi-sub-hint">ทศนิยม 2 ตำแหน่ง</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Fields -->
                    <div class="main-form-card">
                        <div class="toolbar">
                            <div>
                                <h5 style="margin:0; font-weight:700; color:#1e293b;">🗓️ Monthly Targets & Workdays Input</h5>
                                <span style="font-size:12px; color:#64748b;">
                                    <?php if ($is_edit): ?>
                                        * แก้ไขทุกช่องได้อิสระก่อนกด Save
                                    <?php else: ?>
                                        * ค่าเริ่มต้นมาจากปี <?php echo ($target_year - 1); ?> 
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="month-grid">
                            <?php 
                            $m_idx = 0;
                            foreach ($months as $key => $m_name): 
                                $m_idx++;
                                $m_key_low = strtolower($key);
                            ?>
                            <div class="month-card">
                                <div class="month-card-header">
                                    <span class="month-name"><?php echo $m_name; ?></span>
                                    <span class="month-num">M<?php echo str_pad($m_idx, 2, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                
                                <div class="month-card-body">
                                    <!-- TRGTMSTR2 -->
                                    <div class="target-group target-group-2">
                                        <div class="target-group-top">
                                            <span class="group-title-2">TRGTMSTR2</span>
                                            <div class="wd-control">
                                                <span class="wd-label">WD</span>
                                                <input type="number" min="0" max="32767" step="1"
                                                       class="input-wd"
                                                       name="wd2_<?php echo $m_key_low; ?>"
                                                       id="wd2_<?php echo $m_key_low; ?>"
                                                       value="<?php echo (int)$data2['WORKDAY_'.$key]; ?>" required>
                                            </div>
                                        </div>
                                        <input type="number" step="1" min="0" max="32767"
                                               class="input-target" 
                                               name="amount2_<?php echo $m_key_low; ?>" 
                                               id="amount2_<?php echo $m_key_low; ?>" 
                                               value="<?php echo (int)$data2['AMOUNT_'.$key]; ?>" required>
                                    </div>

                                    <!-- TRGTMSTR4 -->
                                    <div class="target-group target-group-4">
                                        <div class="target-group-top">
                                            <span class="group-title-4">TRGTMSTR4</span>
                                            <div class="wd-control">
                                                <span class="wd-label">WD</span>
                                                <input type="number" min="0" max="32767" step="1"
                                                       class="input-wd"
                                                       name="wd4_<?php echo $m_key_low; ?>"
                                                       id="wd4_<?php echo $m_key_low; ?>"
                                                       value="<?php echo (int)$data4['WORKDAY_'.$key]; ?>" required>
                                            </div>
                                        </div>
                                        <input type="number" step="1" min="0" max="32767"
                                               class="input-target" 
                                               name="amount4_<?php echo $m_key_low; ?>" 
                                               id="amount4_<?php echo $m_key_low; ?>" 
                                               value="<?php echo (int)$data4['AMOUNT_'.$key]; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions">
                            <a href="target_month_master_mats.php?func=<?php echo $folder_func; ?>" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-save">💾 Save Target Data</button>
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
const targetYear = <?php echo $target_year; ?>;
const isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;
const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

function clampInt(val, min, max) {
    let n = parseInt(val, 10);
    if (isNaN(n)) n = 0;
    if (n < min) return min;
    if (n > max) return max;
    return n;
}

function getCalendarDaysInMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0).getDate();
}

function recalcNewMonthsFromYearly(prefix) {
    if (isEditMode) return;

    let wdYear = clampInt(document.getElementById('workday_year' + prefix).value, 0, 32767);
    let amtYear = clampInt(document.getElementById('amount_year' + prefix).value, 0, 32767);

    let wdEls = months.map(m => document.getElementById('wd' + prefix + '_' + m));
    let oldWdSum = wdEls.reduce((acc, el) => acc + clampInt(el.value, 0, 32767), 0);

    // ถ้ายอด WD รายเดือนเดิมเป็น 0 ให้ใช้วันตามปฏิทินของปีนั้นเป็นฐานสัดส่วน
    let weights = [];
    if (oldWdSum > 0) {
        weights = wdEls.map(el => clampInt(el.value, 0, 32767));
    } else {
        weights = months.map((_, idx) => getCalendarDaysInMonth(targetYear, idx));
    }
    let weightSum = weights.reduce((acc, v) => acc + v, 0);

    let newWds = [];
    let wdRunning = 0;
    for (let i = 0; i < months.length; i++) {
        let w = 0;
        if (i === months.length - 1) {
            w = Math.max(0, Math.min(32767, wdYear - wdRunning));
        } else if (weightSum > 0) {
            w = Math.round((wdYear * weights[i]) / weightSum);
            w = Math.max(0, Math.min(32767, w));
            wdRunning += w;
        }
        newWds.push(w);
    }

    for (let i = 0; i < months.length; i++) {
        wdEls[i].value = newWds[i];
    }

    // กระจาย AMOUNT_YEAR ลงรายเดือนตามสัดส่วนวันทำงานใหม่ (หรือเฉลี่ย 12 เดือนหากวันรวมเป็น 0)
    let amtEls = months.map(m => document.getElementById('amount' + prefix + '_' + m));
    let totalNewWd = newWds.reduce((acc, v) => acc + v, 0);

    let amtRunning = 0;
    for (let i = 0; i < months.length; i++) {
        let a = 0;
        if (i === months.length - 1) {
            a = Math.max(0, Math.min(32767, amtYear - amtRunning));
        } else if (totalNewWd > 0) {
            a = Math.round((amtYear * newWds[i]) / totalNewWd);
            a = Math.max(0, Math.min(32767, a));
            amtRunning += a;
        } else {
            a = Math.round(amtYear / months.length);
            a = Math.max(0, Math.min(32767, a));
            amtRunning += a;
        }
        amtEls[i].value = a;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('focus', function() {
            this.select();
        });
    });

    // คำนวณแทนที่ในแต่ละเดือนเมื่อเปลี่ยนเสร็จ (change) หรือพิมพ์ตัวเลขที่ถูกต้อง (input เมื่อค่าไม่ว่าง)
    ['2', '4'].forEach(prefix => {
        ['workday_year', 'amount_year'].forEach(field => {
            let el = document.getElementById(field + prefix);
            el.addEventListener('input', function() {
                if (this.value.trim() === '') return;
                recalcNewMonthsFromYearly(prefix);
            });
            el.addEventListener('change', function() {
                recalcNewMonthsFromYearly(prefix);
            });
        });
    });

    document.getElementById('targetForm').addEventListener('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        fetch('model/create_traget_month_data.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('✅ บันทึกข้อมูลสำเร็จ!');
                window.location.href = 'target_month_master_mats.php?func=<?php echo $folder_func; ?>';
            } else {
                alert('❌ เกิดข้อผิดพลาด: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
        });
    });
});
</script>
</html>