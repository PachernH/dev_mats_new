<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$recipe_no = isset($_GET['recipe_no']) ? trim($_GET['recipe_no']) : '';

// 1. ดึงข้อมูล Header หลักตัวแรกขึ้นมาแสดงผลในการ์ดบน (รวม COOLANT_RECIPE, SPOOL และ RSET_NO)
$header = array();
if ($recipe_no !== '') {
    $h_sql = "SELECT TOP(1) RECIPE_NO, ALLOY, WIDTH, THICKNESS_ORIGINAL, THICKNESS_FINAL, COOLANT_RECIPE, SPOOL, RSET_NO 
              FROM CMRCMSTR1 
              WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))";
    $h_stmt = $conn->prepare($h_sql);
    $h_stmt->execute([':rno' => $recipe_no]);
    $header = $h_stmt->fetch(PDO::FETCH_ASSOC);
}

$v_rno     = $header['RECIPE_NO'] ?? $recipe_no;
$v_al      = $header['ALLOY'] ?? '';
$v_wi      = isset($header['WIDTH']) ? number_format((float)$header['WIDTH'], 2, '.', '') : '';
$v_th_or   = isset($header['THICKNESS_ORIGINAL']) ? number_format((float)$header['THICKNESS_ORIGINAL'], 2, '.', '') : '';
$v_th_fn   = isset($header['THICKNESS_FINAL']) ? number_format((float)$header['THICKNESS_FINAL'], 2, '.', '') : '';
$v_coolant = $header['COOLANT_RECIPE'] ?? '';
$v_spool   = $header['SPOOL'] ?? 0;
$v_rset_no = trim($header['RSET_NO'] ?? '');

// 2. ดึงรายการ TEMPER จากตาราง TMPRMSTR1 สำหรับทำ Dropdown Select
$temper_options = array();
try {
    $t_sql = "SELECT TEMPER, DESCRIPTION FROM TMPRMSTR1 ORDER BY TEMPER ASC";
    $t_stmt = $conn->prepare($t_sql);
    $t_stmt->execute();
    while ($t_row = $t_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($t_row['TEMPER'])) {
            $temper_options[] = array(
                'temper' => trim($t_row['TEMPER']),
                'desc'   => trim($t_row['DESCRIPTION'] ?? '')
            );
        }
    }
} catch (PDOException $e) {}

// 3. ดึงรายการ ROLL SET จาก RDMTMSTR3 (เฉพาะ RSET_STATUS = 'OP') สำหรับ Dropdown เลือก Roll Set
$rset_options = array();
try {
    $rset_sql = "SELECT RSET_NO, RSET_REFERENCE, WRL_RADIUS, BRL_RADIUS, TWR_CAMBER, BWR_CAMBER 
                 FROM RDMTMSTR3 
                 WHERE RSET_STATUS = 'OP' 
                 ORDER BY RSET_NO DESC";
    $rset_stmt = $conn->prepare($rset_sql);
    $rset_stmt->execute();
    while ($r_row = $rset_stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($r_row['RSET_NO'])) {
            $rset_options[] = array(
                'rset_no'    => trim($r_row['RSET_NO']),
                'ref'        => trim($r_row['RSET_REFERENCE'] ?? ''),
                'wrl_radius' => $r_row['WRL_RADIUS'],
                'brl_radius' => $r_row['BRL_RADIUS'],
                'twr_camber' => $r_row['TWR_CAMBER'],
                'bwr_camber' => $r_row['BWR_CAMBER']
            );
        }
    }
} catch (PDOException $e) {}
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
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .info-label { font-weight: 600; color: #64748b; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 40px;
            padding: 8px 12px;
            font-size: 15px;
            color: #1d4ed8;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }

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

        .table-responsive-custom {
            width: 100%;
            overflow-x: auto !important;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
        }
        .table-detail {
            width: 100%;
            min-width: 1350px;
            margin-bottom: 0 !important;
        }
        .table-detail th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-align: center;
            vertical-align: middle !important;
            white-space: nowrap;
            padding: 10px 8px !important;
        }
        .table-detail td {
            vertical-align: middle !important;
            padding: 6px !important;
        }
        .table-detail .form-control {
            height: 38px;
            font-size: 13px;
            border-radius: 6px;
        }
        .btn-del-row {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 600;
        }
        .btn-del-row:hover { background-color: #fca5a5; color: #7f1d1d; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Cold Rolling Recipe</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <form id="form_edit_recipe" method="POST" onsubmit="return false;">

            <!-- Hidden Header Parameters -->
            <input type="hidden" name="recipe_no" id="recipe_no" value="<?php echo htmlspecialchars($v_rno, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="alloy" value="<?php echo htmlspecialchars($v_al, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="width" value="<?php echo htmlspecialchars($v_wi, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="thickness_original" value="<?php echo htmlspecialchars($v_th_or, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="thickness_final" value="<?php echo htmlspecialchars($v_th_fn, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="coolant_recipe" value="<?php echo htmlspecialchars($v_coolant, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="spool" value="<?php echo htmlspecialchars($v_spool, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- การ์ด 1: ข้อมูลหลัก Header -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📜 Recipe Header Information</h4>
                        <div class="row">                      

                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">RECIPE NO</div>
                                <div class="info-value" style="color:#0f172a;"><?php echo htmlspecialchars($v_rno !== '' ? $v_rno : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="info-label">ROLL SET No.<span class="text-danger">*</span></div>
                                <select class="form-control" name="rset_no" id="rset_no" required>
                                    <option value="">-- Roll Set --</option>
                                    <?php foreach ($rset_options as $rs): ?>
                                        <option value="<?php echo htmlspecialchars($rs['rset_no'], ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo ($v_rset_no === $rs['rset_no']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rs['rset_no'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">ALLOY</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_al !== '' ? $v_al : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-1 col-sm-3">
                                <div class="info-label">WIDTH</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_wi !== '' ? $v_wi : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>    
                            
                            <div class="col-md-1 col-sm-3">
                                <div class="info-label">THICK ORIG.</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_th_or !== '' ? $v_th_or : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-3">
                                <div class="info-label">THICK FINAL</div>
                                <div class="info-value"><?php echo htmlspecialchars($v_th_fn !== '' ? $v_th_fn : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>   

                        </div>
                    </div>
                </div>
            </div>

            <!-- การ์ด 2: ตารางรายการลูก (Recipe Items Breakdown) -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 class="card-title-sub" style="margin: 0; border: none;">📊 Edit Recipe Items</h4>
                            <button type="button" class="btn btn-sm btn-info" style="border-radius: 6px; font-weight:600;" onclick="addRow()">➕ Add Item (Add Row)</button>
                        </div>
                        
                        <div class="table-responsive-custom">
                            <table class="table table-bordered table-detail" id="tbl_recipe_items">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ITEM <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">TEMPER FIN. (TMPRMSTR1)</th>
                                        <th style="width: 120px;">THICKNESS ENTRY</th>
                                        <th style="width: 120px;">THICKNESS EXIT</th>
                                        <th style="width: 120px;">TOLERANCE</th>
                                        <th style="width: 120px;">WORK HARD. 1</th>
                                        <th style="width: 120px;">WORK HARD. 2</th>
                                        <th style="width: 120px;">COEFF. FRICTION</th>
                                        <th style="width: 120px;">YIELD STRESS</th>
                                        <th style="width: 120px;">EMPIRICAL VAL 1</th>
                                        <th style="width: 70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $detail_sql = "SELECT RECIPE_ITEM, TEMPER_FINISH, THICKNESS_ENTRY, THICKNESS_EXIT, 
                                                          THICKNESS_TELORANCE, WORK_HARDENING1, WORK_HARDENING2, 
                                                          COEFFICIENT_FRICTION, YIELD_STRESS, EMPIRICAL_VALUE1 
                                                   FROM CMRCMSTR1 
                                                   WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))
                                                   ORDER BY RECIPE_ITEM ASC";

                                    $stmt = $conn->prepare($detail_sql);
                                    $stmt->execute([':rno' => $recipe_no]);

                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        $cur_temper = trim($row['TEMPER_FINISH'] ?? '');
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="number" class="form-control text-center recipe-item-input" name="recipe_item[]" value="<?php echo htmlspecialchars($row['RECIPE_ITEM'] ?? '1', ENT_QUOTES, 'UTF-8'); ?>" required min="1">
                                            </td>
                                            <td>
                                                <select class="form-control" name="temper_finish[]">
                                                    <option value="">-- เลือก Temper --</option>
                                                    <?php foreach ($temper_options as $top): ?>
                                                        <option value="<?php echo htmlspecialchars($top['temper'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?php echo ($cur_temper === $top['temper']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($top['temper'] . ($top['desc'] !== '' ? ' : ' . $top['desc'] : ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="thickness_entry[]" value="<?php echo is_numeric($row['THICKNESS_ENTRY']) ? number_format((float)$row['THICKNESS_ENTRY'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="thickness_exit[]" value="<?php echo is_numeric($row['THICKNESS_EXIT']) ? number_format((float)$row['THICKNESS_EXIT'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-center" name="thickness_telorance[]" value="<?php echo htmlspecialchars($row['THICKNESS_TELORANCE'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="work_hardening1[]" value="<?php echo is_numeric($row['WORK_HARDENING1']) ? number_format((float)$row['WORK_HARDENING1'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="work_hardening2[]" value="<?php echo is_numeric($row['WORK_HARDENING2']) ? number_format((float)$row['WORK_HARDENING2'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="coefficient_friction[]" value="<?php echo is_numeric($row['COEFFICIENT_FRICTION']) ? number_format((float)$row['COEFFICIENT_FRICTION'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="yield_stress[]" value="<?php echo is_numeric($row['YIELD_STRESS']) ? number_format((float)$row['YIELD_STRESS'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control text-right" name="empirical_value1[]" value="<?php echo is_numeric($row['EMPIRICAL_VALUE1']) ? number_format((float)$row['EMPIRICAL_VALUE1'], 2, '.', '') : '0.00'; ?>">
                                            </td>
                                            <td align="center">
                                                <button type="button" class="btn-del-row" onclick="removeRow(this)">Delete</button>
                                            </td>
                                        </tr>
                                        <?php
                                    }

                                    if(!$has_row) {
                                        ?>
                                        <tr>
                                            <td><input type="number" class="form-control text-center recipe-item-input" name="recipe_item[]" value="1" required min="1"></td>
                                            <td>
                                                <select class="form-control" name="temper_finish[]">
                                                    <option value="">-- Temper --</option>
                                                    <?php foreach ($temper_options as $top): ?>
                                                        <option value="<?php echo htmlspecialchars($top['temper'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($top['temper'] . ($top['desc'] !== '' ? ' : ' . $top['desc'] : ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="thickness_entry[]" value="0.00"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="thickness_exit[]" value="0.00"></td>
                                            <td><input type="number" class="form-control text-center" name="thickness_telorance[]" value="0"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="work_hardening1[]" value="0.00"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="work_hardening2[]" value="0.00"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="coefficient_friction[]" value="0.00"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="yield_stress[]" value="0.00"></td>
                                            <td><input type="number" step="0.01" class="form-control text-right" name="empirical_value1[]" value="0.00"></td>
                                            <td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">ลบ</button></td>
                                        </tr>
                                        <?php
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">📊 Available Roll Set Numbers (RDMTMSTR3 : RSET_STATUS = 'OP')</h4>
                        
                        <div class="table-responsive-custom">
                            <table class="table table-bordered table-striped table-detail">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ITEM</th>
                                        <th style="width: 120px;">Roll No</th>
                                        <th style="width: 140px;">Roll Ref No</th>
                                        <th style="width: 140px;">WRL RADIUS</th>
                                        <th style="width: 150px;">BRL RADIUS</th>
                                        <th style="width: 140px;">TWR CAMBER</th>
                                        <th style="width: 140px;">BWR CAMBER</th>
                                        <th style="width: 140px;">RSET STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $Item_roll = 1;
                                    $roll_sql = "SELECT r.RSET_NO, r.RSET_REFERENCE, r.WRL_RADIUS, r.BRL_RADIUS, r.TWR_CAMBER, r.BWR_CAMBER, r.RSET_STATUS 
                                                 FROM RDMTMSTR3 as r
                                                 WHERE r.RSET_STATUS = 'OP'
                                                 ORDER BY r.RSET_NO DESC";

                                    $stmt = $conn->prepare($roll_sql);
                                    $stmt->execute();

                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        $is_selected = (trim($row['RSET_NO'] ?? '') === $v_rset_no);
                                        ?>
                                        <tr style="<?php echo $is_selected ? 'background-color: #eff6ff; font-weight: bold;' : ''; ?>">
                                            <td align="center"><span class="badge" style="background-color:<?php echo $is_selected ? '#16a34a' : '#2563eb'; ?>; font-size:13px;"><?php echo $Item_roll++; ?></span></td>
                                            <td align="center" style="color:#1e293b;"><?php echo htmlspecialchars($row['RSET_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <?php echo $is_selected ? '<span class="label label-success">SELECTED</span>' : ''; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['RSET_REFERENCE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td align="right"><?php echo is_numeric($row['WRL_RADIUS']) ? number_format((float)$row['WRL_RADIUS'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['BRL_RADIUS']) ? number_format((float)$row['BRL_RADIUS'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['TWR_CAMBER']) ? number_format((float)$row['TWR_CAMBER'], 2) : '0.00'; ?></td>
                                            <td align="right"><?php echo is_numeric($row['BWR_CAMBER']) ? number_format((float)$row['BWR_CAMBER'], 2) : '0.00'; ?></td>
                                            <td align="center"><?php echo htmlspecialchars($row['RSET_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                        <?php
                                    }

                                    if(!$has_row) {
                                        echo "<tr><td colspan='8' align='center' style='color:#94a3b8; padding:30px;'>No ROLL SET NO data with status OP was found in the system.</td></tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 20px 0 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('cold_rolling_recipe_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_recipe_data()">💾 Save Data</button>
                </div>
            </div><br>
            </form>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">
var temperOptionHtml = '<option value="">-- Temper --</option>';
<?php foreach ($temper_options as $top): ?>
    temperOptionHtml += '<option value="<?php echo addslashes($top['temper']); ?>"><?php echo addslashes($top['temper'] . ($top['desc'] !== '' ? ' : ' . $top['desc'] : '')); ?></option>';
<?php endforeach; ?>

function getNextItemNo() {
    var maxVal = 0;
    $(".recipe-item-input").each(function() {
        var val = parseInt($(this).val());
        if (!isNaN(val) && val > maxVal) {
            maxVal = val;
        }
    });
    return maxVal + 1;
}

function addRow() {
    var nextItem = getNextItemNo();
    var tr = '<tr>' +
        '<td><input type="number" class="form-control text-center recipe-item-input" name="recipe_item[]" value="' + nextItem + '" required min="1"></td>' +
        '<td><select class="form-control" name="temper_finish[]">' + temperOptionHtml + '</select></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="thickness_entry[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="thickness_exit[]" value="0.00"></td>' +
        '<td><input type="number" class="form-control text-center" name="thickness_telorance[]" value="0"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="work_hardening1[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="work_hardening2[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="coefficient_friction[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="yield_stress[]" value="0.00"></td>' +
        '<td><input type="number" step="0.01" class="form-control text-right" name="empirical_value1[]" value="0.00"></td>' +
        '<td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">ลบ</button></td>' +
        '</tr>';
    $("#tbl_recipe_items tbody").append(tr);
}

function removeRow(btn) {
    if ($("#tbl_recipe_items tbody tr").length > 1) {
        $(btn).closest('tr').remove();
    } else {
        alert("There must be at least one item listed.");
    }
}

function submit_recipe_data() {
    var recipe_no = $("#recipe_no").val().trim();
    var rset_no   = $("#rset_no").val().trim();
    var data_fun  = $("#func").val();

    if (recipe_no === "") {
        alert("Code not found Recipe No.");
        return false;
    }

    if (rset_no === "") {
        alert("Please Select Roll Set (RSET_NO)");
        return false;
    }

    var formData = $("#form_edit_recipe").serialize();

    $.ajax({
        url: "model/update_recipe_system.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if (resp.status === "success" || resp.message === true) {
                alert("Update Cold Rolling Recipe Complete");
                window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("Error : " + (resp.error || "can not save"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while connecting to the server : " + error);
        }
    });
}
</script>
</html>