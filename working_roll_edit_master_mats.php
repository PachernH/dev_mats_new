<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า RSET_NO
$rset_no = isset($_GET['recipe_no']) ? trim($_GET['recipe_no']) : (isset($_GET['rset_no']) ? trim($_GET['rset_no']) : '');

// 1. ดึงข้อมูลเดิมจาก RDMTMSTR3
$data = array();
if ($rset_no !== '') {
    try {
        $sql = "SELECT RSET_NO, RSET_REFERENCE, 
                       TWR_WORKNO, TWR_CAMBER, TWR_CAMBERTYPE, TWR_INDATETIME, TWR_OUTDATETIME,
                       BWR_WORKNO, BWR_CAMBER, BWR_CAMBERTYPE, BWR_INDATETIME, BWR_OUTDATETIME,
                       WRL_RADIUS, BRL_RADIUS, RSET_STATUS,
                       TBR_WORKNO, TBR_INDATETIME, TBR_OUTDATETIME,
                       BBR_WORKNO, BBR_INDATETIME, BBR_OUTDATETIME
                FROM RDMTMSTR3 
                WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:rno))";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':rno' => $rset_no]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $data = array();
    }
}

// 2. ดึงรายการ WORK_NO จาก RDMTMSTR2 (เลือกเฉพาะ WRK_STATUS = 'OP' หรือรายการเดิมที่ผูกไว้)
$workno_options = array();
$existing_wns = array_filter([
    trim($data['TWR_WORKNO'] ?? ''),
    trim($data['BWR_WORKNO'] ?? ''),
    trim($data['TBR_WORKNO'] ?? ''),
    trim($data['BBR_WORKNO'] ?? '')
]);

try {
    // ดึงสถานะ OP หรือตรงกับ Work No. เดิมที่มีอยู่
    $w_sql = "SELECT WORK_NO, ROLL_NO, ROLL_TYPE, USE_FOR, WRK_STATUS,
                     BFR_MAXDIAMETER, BFR_MINDIAMETER, AFT_MAXDIAMETER, AFT_MINDIAMETER, LINE_PROCESS
              FROM RDMTMSTR2
              WHERE WRK_STATUS = 'OP'";
    
    if (!empty($existing_wns)) {
        $in_clause = "'" . implode("','", array_map('addslashes', $existing_wns)) . "'";
        $w_sql .= " OR WORK_NO IN ($in_clause)";
    }
    
    $w_sql .= " ORDER BY WORK_NO DESC";

    $w_stmt = $conn->prepare($w_sql);
    $w_stmt->execute();
    while ($w_row = $w_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($w_row['WORK_NO'])) {
            $workno_options[] = $w_row;
        }
    }
} catch (PDOException $e) {}

// ฟังก์ชันแปลงวันที่สำหรับ input type="datetime-local"
function formatForInputDatetime($datetimeStr) {
    if (empty($datetimeStr)) return '';
    $dt = new DateTime($datetimeStr);
    $formatted = $dt->format('Y-m-d\TH:i');
    if (strpos($formatted, '1900-01-01') !== false) return '';
    return $formatted;
}

// ฟังก์ชันฟอร์แมตตัวเลขให้อยู่ในรูปแบบ ทศนิยม 2 ตำแหน่ง
function formatDecimal2($val) {
    return is_numeric($val) ? number_format((float)$val, 2, '.', '') : '0.00';
}

$v_rset_no = $data['RSET_NO'] ?? $rset_no;
$v_status  = trim($data['RSET_STATUS'] ?? '');
$is_op     = ($v_status === 'OP');
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .info-label { font-weight: 600; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        .form-control[readonly] { background-color: #f1f5f9; color: #64748b; font-weight: 700; }

        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover { background-color: #f1f5f9; color: #1e293b; }

        .btn-save-edit {
            background-color: #16a34a;
            color: white;
            font-weight: 600;
            width: 200px;
            height: 42px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-save-edit:hover { background-color: #15803d; }

        .workno-meta-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        .workno-meta-box .meta-item { display: inline-block; margin-right: 15px; }
        .workno-meta-box .meta-title { font-weight: 600; color: #64748b; }
        .workno-meta-box .meta-value { font-weight: 700; color: #1e293b; }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'gp3'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Working Roll Set Master</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('working_roll_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$is_op): ?>
                <div class="display-card text-center" style="padding: 40px;">
                    <h4 style="color: #dc2626; font-weight: 700;">⛔ Edit is only allowed for items with OP status</h4>
                </div>
            <?php else: ?>

            <form id="form_edit_roll" method="POST" onsubmit="return false;">

            <!-- การ์ด 1: Header Information -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">⚙️ Working Roll Set Header Parameters</h4>
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">Current Roll Set No (RSET_NO)</div>
                                <input type="text" class="form-control" name="rset_no" id="rset_no" value="<?php echo htmlspecialchars($v_rset_no, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">Saved As Ref (RSET_REFERENCE)</div>
                                <input type="text" class="form-control" name="rset_reference" value="<?php echo htmlspecialchars($v_rset_no, ENT_QUOTES, 'UTF-8'); ?>" readonly style="color:#2563eb;">
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WRL Radius (TWR+BWR / 8)</div>
                                <input type="number" step="0.01" class="form-control text-right" name="wrl_radius" id="wrl_radius" value="<?php echo formatDecimal2($data['WRL_RADIUS'] ?? 0); ?>" readonly style="background:#eff6ff; color:#1d4ed8; font-weight:bold;">
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">BRL Radius (TBR+BBR / 8)</div>
                                <input type="number" step="0.01" class="form-control text-right" name="brl_radius" id="brl_radius" value="<?php echo formatDecimal2($data['BRL_RADIUS'] ?? 0); ?>" readonly style="background:#eff6ff; color:#1d4ed8; font-weight:bold;">
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">New Status</div>
                                <input type="text" class="form-control text-center" name="rset_status" value="OP" readonly style="color: #16a34a; font-weight:700;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- การ์ด 2: Work Rolls (TWR & BWR) -->
            <div class="row">
                <!-- TWR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🔄 Top Work Roll (TWR)</h4>
                        <div class="row">
                            <div class="col-md-12" style="margin-bottom: 12px;">
                                <div class="info-label">TWR Work No (RDMTMSTR2: Status OP)</div>
                                <select class="form-control workno-select" name="twr_workno" id="twr_workno" data-target="twr">
                                    <option value="">-- เลือก TWR Work No --</option>
                                    <?php foreach ($workno_options as $w): ?>
                                        <?php 
                                            $wn_val = trim($w['WORK_NO']);
                                            $selected = ($wn_val === trim($data['TWR_WORKNO'] ?? '')) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($wn_val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>
                                            data-rollno="<?php echo htmlspecialchars($w['ROLL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($w['WRK_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bfr-max="<?php echo (float)($w['BFR_MAXDIAMETER'] ?? 0); ?>"
                                            data-bfr-min="<?php echo (float)($w['BFR_MINDIAMETER'] ?? 0); ?>"
                                            data-aft-max="<?php echo (float)($w['AFT_MAXDIAMETER'] ?? 0); ?>"
                                            data-aft-min="<?php echo (float)($w['AFT_MINDIAMETER'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($wn_val . " | Roll No: " . ($w['ROLL_NO'] ?? '-') . " [" . ($w['WRK_STATUS'] ?? '') . "]", ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="workno-meta-box" id="twr_meta">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value" id="twr_rollno">-</span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value" id="twr_status">-</span></span>
                                    <span class="meta-item"><span class="meta-title">BFR (Max/Min):</span> <span class="meta-value" id="twr_bfr_diam">-</span></span>
                                </div>
                            </div>

                            <div class="col-md-6" style="margin-bottom: 12px;">
                                <div class="info-label">TWR Camber</div>
                                <input type="number" step="0.01" class="form-control text-right" name="twr_camber" value="<?php echo formatDecimal2($data['TWR_CAMBER'] ?? 0); ?>">
                            </div>
                            <div class="col-md-6" style="margin-bottom: 12px;">
                                <div class="info-label">TWR Camber Type</div>
                                <input type="text" class="form-control" name="twr_cambertype" value="<?php echo htmlspecialchars($data['TWR_CAMBERTYPE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TWR In DateTime</div>
                                <input type="datetime-local" class="form-control" name="twr_indatetime" value="<?php echo formatForInputDatetime($data['TWR_INDATETIME'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TWR Out DateTime</div>
                                <input type="datetime-local" class="form-control" name="twr_outdatetime" value="<?php echo formatForInputDatetime($data['TWR_OUTDATETIME'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BWR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🔄 Bottom Work Roll (BWR)</h4>
                        <div class="row">
                            <div class="col-md-12" style="margin-bottom: 12px;">
                                <div class="info-label">BWR Work No (RDMTMSTR2: Status OP)</div>
                                <select class="form-control workno-select" name="bwr_workno" id="bwr_workno" data-target="bwr">
                                    <option value="">-- BWR Work No --</option>
                                    <?php foreach ($workno_options as $w): ?>
                                        <?php 
                                            $wn_val = trim($w['WORK_NO']);
                                            $selected = ($wn_val === trim($data['BWR_WORKNO'] ?? '')) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($wn_val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>
                                            data-rollno="<?php echo htmlspecialchars($w['ROLL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($w['WRK_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bfr-max="<?php echo (float)($w['BFR_MAXDIAMETER'] ?? 0); ?>"
                                            data-bfr-min="<?php echo (float)($w['BFR_MINDIAMETER'] ?? 0); ?>"
                                            data-aft-max="<?php echo (float)($w['AFT_MAXDIAMETER'] ?? 0); ?>"
                                            data-aft-min="<?php echo (float)($w['AFT_MINDIAMETER'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($wn_val . " | Roll No: " . ($w['ROLL_NO'] ?? '-') . " [" . ($w['WRK_STATUS'] ?? '') . "]", ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="workno-meta-box" id="bwr_meta">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value" id="bwr_rollno">-</span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value" id="bwr_status">-</span></span>
                                    <span class="meta-item"><span class="meta-title">BFR (Max/Min):</span> <span class="meta-value" id="bwr_bfr_diam">-</span></span>
                                </div>
                            </div>

                            <div class="col-md-6" style="margin-bottom: 12px;">
                                <div class="info-label">BWR Camber</div>
                                <input type="number" step="0.01" class="form-control text-right" name="bwr_camber" value="<?php echo formatDecimal2($data['BWR_CAMBER'] ?? 0); ?>">
                            </div>
                            <div class="col-md-6" style="margin-bottom: 12px;">
                                <div class="info-label">BWR Camber Type</div>
                                <input type="text" class="form-control" name="bwr_cambertype" value="<?php echo htmlspecialchars($data['BWR_CAMBERTYPE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BWR In DateTime</div>
                                <input type="datetime-local" class="form-control" name="bwr_indatetime" value="<?php echo formatForInputDatetime($data['BWR_INDATETIME'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BWR Out DateTime</div>
                                <input type="datetime-local" class="form-control" name="bwr_outdatetime" value="<?php echo formatForInputDatetime($data['BWR_OUTDATETIME'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- การ์ด 3: Backup Rolls (TBR & BBR) -->
            <div class="row">
                <!-- TBR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🛡️ Top Backup Roll (TBR)</h4>
                        <div class="row">
                            <div class="col-md-12" style="margin-bottom: 12px;">
                                <div class="info-label">TBR Work No (RDMTMSTR2: Status OP)</div>
                                <select class="form-control workno-select" name="tbr_workno" id="tbr_workno" data-target="tbr">
                                    <option value="">-- TBR Work No --</option>
                                    <?php foreach ($workno_options as $w): ?>
                                        <?php 
                                            $wn_val = trim($w['WORK_NO']);
                                            $selected = ($wn_val === trim($data['TBR_WORKNO'] ?? '')) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($wn_val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>
                                            data-rollno="<?php echo htmlspecialchars($w['ROLL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($w['WRK_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bfr-max="<?php echo (float)($w['BFR_MAXDIAMETER'] ?? 0); ?>"
                                            data-bfr-min="<?php echo (float)($w['BFR_MINDIAMETER'] ?? 0); ?>"
                                            data-aft-max="<?php echo (float)($w['AFT_MAXDIAMETER'] ?? 0); ?>"
                                            data-aft-min="<?php echo (float)($w['AFT_MINDIAMETER'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($wn_val . " | Roll No: " . ($w['ROLL_NO'] ?? '-') . " [" . ($w['WRK_STATUS'] ?? '') . "]", ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="workno-meta-box" id="tbr_meta">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value" id="tbr_rollno">-</span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value" id="tbr_status">-</span></span>
                                    <span class="meta-item"><span class="meta-title">AFT (Max/Min):</span> <span class="meta-value" id="tbr_aft_diam">-</span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">TBR In DateTime</div>
                                <input type="datetime-local" class="form-control" name="tbr_indatetime" value="<?php echo formatForInputDatetime($data['TBR_INDATETIME'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">TBR Out DateTime</div>
                                <input type="datetime-local" class="form-control" name="tbr_outdatetime" value="<?php echo formatForInputDatetime($data['TBR_OUTDATETIME'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BBR -->
                <div class="col-md-6">
                    <div class="display-card">
                        <h4 class="card-title-sub">🛡️ Bottom Backup Roll (BBR)</h4>
                        <div class="row">
                            <div class="col-md-12" style="margin-bottom: 12px;">
                                <div class="info-label">BBR Work No (RDMTMSTR2: Status OP)</div>
                                <select class="form-control workno-select" name="bbr_workno" id="bbr_workno" data-target="bbr">
                                    <option value="">-- BBR Work No --</option>
                                    <?php foreach ($workno_options as $w): ?>
                                        <?php 
                                            $wn_val = trim($w['WORK_NO']);
                                            $selected = ($wn_val === trim($data['BBR_WORKNO'] ?? '')) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo htmlspecialchars($wn_val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>
                                            data-rollno="<?php echo htmlspecialchars($w['ROLL_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($w['WRK_STATUS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bfr-max="<?php echo (float)($w['BFR_MAXDIAMETER'] ?? 0); ?>"
                                            data-bfr-min="<?php echo (float)($w['BFR_MINDIAMETER'] ?? 0); ?>"
                                            data-aft-max="<?php echo (float)($w['AFT_MAXDIAMETER'] ?? 0); ?>"
                                            data-aft-min="<?php echo (float)($w['AFT_MINDIAMETER'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($wn_val . " | Roll No: " . ($w['ROLL_NO'] ?? '-') . " [" . ($w['WRK_STATUS'] ?? '') . "]", ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="workno-meta-box" id="bbr_meta">
                                    <span class="meta-item"><span class="meta-title">ROLL NO:</span> <span class="meta-value" id="bbr_rollno">-</span></span>
                                    <span class="meta-item"><span class="meta-title">STATUS:</span> <span class="meta-value" id="bbr_status">-</span></span>
                                    <span class="meta-item"><span class="meta-title">AFT (Max/Min):</span> <span class="meta-value" id="bbr_aft_diam">-</span></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">BBR In DateTime</div>
                                <input type="datetime-local" class="form-control" name="bbr_indatetime" value="<?php echo formatForInputDatetime($data['BBR_INDATETIME'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">BBR Out DateTime</div>
                                <input type="datetime-local" class="form-control" name="bbr_outdatetime" value="<?php echo formatForInputDatetime($data['BBR_OUTDATETIME'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 10px 0 25px 0;">
            
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('working_roll_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-save-edit" onclick="submit_update_roll()">Create New Roll Set</button>
                </div>
            </div><br>

            </form>
            <?php endif; ?>

        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">
function updateRollSetCalculations() {
    var twrOpt = $("#twr_workno option:selected");
    var bwrOpt = $("#bwr_workno option:selected");
    var tbrOpt = $("#tbr_workno option:selected");
    var bbrOpt = $("#bbr_workno option:selected");

    updateMetaBox('twr', twrOpt, 'bfr');
    updateMetaBox('bwr', bwrOpt, 'bfr');
    updateMetaBox('tbr', tbrOpt, 'aft');
    updateMetaBox('bbr', bbrOpt, 'aft');

    var twrBfrMax = parseFloat(twrOpt.data("bfr-max")) || 0;
    var twrBfrMin = parseFloat(twrOpt.data("bfr-min")) || 0;
    var bwrBfrMax = parseFloat(bwrOpt.data("bfr-max")) || 0;
    var bwrBfrMin = parseFloat(bwrOpt.data("bfr-min")) || 0;

    var wrlSum = twrBfrMax + twrBfrMin + bwrBfrMax + bwrBfrMin;
    var avgWrl = wrlSum / 8;
    $("#wrl_radius").val(avgWrl.toFixed(2));

    var tbrAftMax = parseFloat(tbrOpt.data("aft-max")) || 0;
    var tbrAftMin = parseFloat(tbrOpt.data("aft-min")) || 0;
    var bbrAftMax = parseFloat(bbrOpt.data("aft-max")) || 0;
    var bbrAftMin = parseFloat(bbrOpt.data("aft-min")) || 0;

    var brlSum = tbrAftMax + tbrAftMin + bbrAftMax + bbrAftMin;
    var avgBrl = brlSum / 8;
    $("#brl_radius").val(avgBrl.toFixed(2));
}

function updateMetaBox(target, opt, type) {
    if (opt.val() !== "") {
        $("#" + target + "_rollno").text(opt.data("rollno") || "-");
        $("#" + target + "_status").text(opt.data("status") || "-");
        if (type === 'bfr') {
            var bfrMax = parseFloat(opt.data("bfr-max")) || 0;
            var bfrMin = parseFloat(opt.data("bfr-min")) || 0;
            $("#" + target + "_bfr_diam").text(bfrMax.toFixed(2) + " / " + bfrMin.toFixed(2));
        } else {
            var aftMax = parseFloat(opt.data("aft-max")) || 0;
            var aftMin = parseFloat(opt.data("aft-min")) || 0;
            $("#" + target + "_aft_diam").text(aftMax.toFixed(2) + " / " + aftMin.toFixed(2));
        }
    } else {
        $("#" + target + "_rollno").text("-");
        $("#" + target + "_status").text("-");
        $("#" + target + "_" + type + "_diam").text("-");
    }
}

$(document).ready(function() {
    $(".workno-select").on("change", function() {
        updateRollSetCalculations();
    });

    updateRollSetCalculations();
});

function submit_update_roll() {
    var rset_no  = $("#rset_no").val().trim();
    var data_fun = $("#func").val();

    if (rset_no === "") {
        alert("Roll Set No. not found.");
        return false;
    }

    var formData = $("#form_edit_roll").serialize();

    $.ajax({
        url: "model/update_roll_master_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if (resp.status === "success" || resp.message === true) {
                alert("Create data Roll Set New (" + (resp.new_rset_no || "") + ") And adjust the original code to: 'CL' finished");
                window.location.assign('working_roll_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Failed to save data"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while connecting to the server: " + error);
        }
    });
}
</script>
</html>