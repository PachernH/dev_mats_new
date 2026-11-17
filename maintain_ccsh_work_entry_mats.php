<?php
session_start();

// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include("dbcon_mats-new.php");

$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : 'mats';
$pno = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

$data = [];
$job_data = [];
$cst_data = [];
$is_view_only = false;
$alert_message = "";

if (!empty($pno)) {
    $stmt = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt->execute([':pno' => $pno]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!empty($data)) {
        // ตรวจสอบเงื่อนไขตาม w1.txt: Next Process ต้องเป็น AN หรือ BA เท่านั้น
        $next_process = strtoupper(trim($data['CRSH_NEXTPROCESS'] ?? ''));
        if ($next_process !== 'AN' && $next_process !== 'BA') {
            $is_view_only = true;
            $alert_message = "กระบวนการถัดไปไม่ได้เป็น AN: Flash Annealing หรือ BA: Batch Annealing";
        }

        if (!empty($data['JOB_ORDER'])) {
            $stmt_job = $conn->prepare("SELECT * FROM JOBORDER1 WHERE JOB_ORDER = :jno");
            $stmt_job->execute([':jno' => $data['JOB_ORDER']]);
            $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        if (!empty($data['SALEORDER_NO']) && !empty($data['SALEORDER_ITEM'])) {
            $stmt_cst = $conn->prepare("SELECT * FROM CSTMORDR2 WHERE SALEORDER_NO = :sono AND SALEORDER_ITEM = :sitem");
            $stmt_cst->execute([':sono' => $data['SALEORDER_NO'], ':sitem' => $data['SALEORDER_ITEM']]);
            $cst_data = $stmt_cst->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    }
}
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
        .main-panel .content { padding: 20px !important; }
        .card-box { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        .form-group-custom { margin-bottom: 16px; }
        .form-group-custom label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; }
        .form-control-view { background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; height: 38px; padding: 6px 12px; font-size: 13.5px; font-weight: 600; color: #334155; display: flex; align-items: center; width: 100%; }
        .form-control-edit { border: 1px solid #2563eb !important; border-radius: 8px; background-color: #ffffff; height: 40px; padding: 6px 12px; font-size: 14px; font-weight: 600; color: #1e293b; width: 100%; }
        .badge-status { background-color: #dbeafe; color: #1e40af; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }
        .badge-id { background-color: #f3e8ff; color: #6b21a8; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }
        .btn-action-save { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 24px; border-radius: 8px; border: none; }
        .btn-action-save:hover { background-color: #1d4ed8; }
        .btn-action-cancel { background-color: #e2e8f0; color: #475569; font-weight: 600; font-size: 14px; padding: 10px 24px; border-radius: 8px; border: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Maintain Circle or Sheet Work Process
                        <?php if ($is_view_only): ?>
                            <span class="badge" style="background-color:#dc2626; margin-left:10px;">VIEW ONLY MODE</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <form id="workProcessForm" action="model/update_ccsh_work_process_mats.php" method="POST">
                <input type="hidden" name="product_no" value="<?php echo $pno; ?>">
                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($data['PRODUCT_ID'] ?? 'CC', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">

                <div class="row">
                    <!-- Column Left -->
                    <div class="col-md-7">
                        <div class="card-box">
                            <div class="card-title">
                                <span>🔵 Circle or Sheet Product Details</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Product No.</label>
                                    <div class="form-control-view" style="color: #2563eb; font-weight: 700;"><?php echo htmlspecialchars($data['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-3 form-group-custom">
                                    <label>Status</label>
                                    <div class="form-control-view"><span class="badge-status"><?php echo htmlspecialchars($data['CRSH_STATUS'] ?? 'OP', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                </div>
                                <div class="col-md-3 form-group-custom">
                                    <label>Product ID</label>
                                    <div class="form-control-view"><span class="badge-id"><?php echo htmlspecialchars($data['PRODUCT_ID'] ?? 'CC', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 form-group-custom">
                                    <label>Coil No. - Material In</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($data['COIL_NO'] ?? $data['MATERIAL_IN'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-4 form-group-custom">
                                    <label>Plant / Source</label>
                                    <div class="form-control-view">BOI-MAT1</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Alloy - Temper</label>
                                    <div style="display: flex; gap: 8px;">
                                        <div class="form-control-view"><?php echo htmlspecialchars($data['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="form-control-view"><?php echo htmlspecialchars($data['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Grade / SG / MG</label>
                                    <div style="display: flex; gap: 4px;">
                                        <div class="form-control-view" style="font-size: 12px;"><?php echo htmlspecialchars($data['GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="form-control-view" style="font-size: 12px;"><?php echo htmlspecialchars($data['SURFACE_GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="form-control-view" style="font-size: 12px;"><?php echo htmlspecialchars($data['METALLURGICAL_GRADE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 form-group-custom">
                                    <label>Thickness (mm.)</label>
                                    <div class="form-control-view text-right"><?php echo number_format((float)($data['THICKNESS'] ?? 0), 3); ?></div>
                                </div>
                                <div class="col-md-8 form-group-custom">
                                    <label>Width - Length (mm.)</label>
                                    <div style="display: flex; gap: 8px;">
                                        <div class="form-control-view text-right"><?php echo number_format((float)($data['WIDTH'] ?? 0), 3); ?></div>
                                        <div class="form-control-view text-right"><?php echo number_format((float)($data['LENGTH'] ?? 0), 2); ?></div>
                                    </div>
                                </div>
                            </div>

                            <hr style="margin: 20px 0; border-top: 1px dashed #cbd5e1;">

                            <!-- Editable Section -->
                            <div class="row">
                                <div class="col-md-12 form-group-custom">
                                    <label style="color: #2563eb; font-size: 13px;">⚙️ Work Process (Editable)</label>
                                    <select name="work_process" id="work_process" class="form-control-edit">
                                        <?php 
                                        $current_wp = trim($data['CRSH_WORKPROCESS'] ?? '');
                                        $options = [];

                                        // ถอดรหัสเงื่อนไขตาม Select Case 
                                        switch (true) {
                                            case (strpos($current_wp, 'BK>') === 0):
                                                $options = ["BK>AN>IS", "BK>BA>TW>IS"];
                                                break;
                                            case (strpos($current_wp, 'AP>') === 0):
                                                $options = ["AP>AN>IS", "AP>BA>TW>IS"];
                                                break;
                                            case (strpos($current_wp, 'KP>') === 0):
                                                $options = ["KP>AN>IS", "KP>BA>TW>IS"];
                                                break;
                                            case (in_array($current_wp, ["CL>BA>SH>IS", "CL>SH>BA>TW>IS"])):
                                                $options = ["CL>BA>SH>IS", "CL>SH>BA>TW>IS"];
                                                break;
                                            case (in_array($current_wp, ["CL>BA>SH>PH>IS", "CL>SH>PH>BA>TW>IS"])):
                                                $options = ["CL>BA>SH>PH>IS", "CL>SH>PH>BA>TW>IS"];
                                                break;
                                            default:
                                                // ค่าเริ่มต้นเผื่อกรณีอื่นๆ
                                                $options = [$current_wp];
                                                break;
                                        }

                                        // วนลูปสร้างรายการ Option ใน Dropdown
                                        $found = false;
                                        foreach ($options as $opt) {
                                            $sel = ($current_wp === $opt) ? 'selected' : '';
                                            if ($current_wp === $opt) $found = true;
                                            echo "<option value=\"$opt\" $sel>$opt</option>";
                                        }

                                        // ถ้าค่าปัจจุบันไม่อยู่ในกลุ่ม ให้แสดงค่าปัจจุบันสำรองไว้ด้วยเพื่อไม่ให้ข้อมูลหาย
                                        if (!empty($current_wp) && !$found) {
                                            echo "<option value=\"$current_wp\" selected>$current_wp</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label style="color: #2563eb; font-size: 13px;">⚙️ Next Process (Editable)</label>
                                    <input type="text" name="next_process" id="next_process" class="form-control-edit" value="<?php echo htmlspecialchars($data['CRSH_NEXTPROCESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo $is_view_only ? 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"' : ''; ?>>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Column Right -->
                    <div class="col-md-5">
                        <div class="card-box">
                            <div class="card-title"><span>📋 Job Order Information</span></div>
                            <div class="row">
                                <div class="col-md-8 form-group-custom">
                                    <label>Job Order No.</label>
                                    <div class="form-control-view" style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($job_data['JOB_ORDER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-4 form-group-custom">
                                    <label>Status</label>
                                    <div class="form-control-view text-center"><span class="badge-status"><?php echo htmlspecialchars($job_data['JOB_STATUS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Job Alloy - Temper</label>
                                    <div style="display: flex; gap: 8px;">
                                        <div class="form-control-view"><?php echo htmlspecialchars($job_data['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="form-control-view"><?php echo htmlspecialchars($job_data['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Fixed Process</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($job_data['FIXED_PROCESS'] ?? 'NO', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="card-box">
                            <div class="card-title"><span>🏷️ Sale Order Information</span></div>
                            <div class="row">
                                <div class="col-md-8 form-group-custom">
                                    <label>Sale Order No - Item</label>
                                    <div style="display: flex; gap: 8px;">
                                        <div class="form-control-view" style="font-weight: 700; color: #047857;"><?php echo htmlspecialchars($data['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="form-control-view text-center" style="width: 70px;"><?php echo htmlspecialchars($data['SALEORDER_ITEM'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-4 form-group-custom">
                                    <label>Customer ID</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($cst_data['CSTMSPPL_ID'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                            <?php if (!$is_view_only): ?>
                                <button type="submit" class="btn-action-save">💾 Save Changes</button>
                            <?php else: ?>
                                <button type="button" class="btn-action-save" disabled style="background-color: #94a3b8; cursor: not-allowed;" title="ไม่สามารถบันทึกได้เนื่องจากกระบวนการถัดไปไม่ถูกต้อง">🔒 Save Disabled</button>
                            <?php endif; ?>
                            <button type="button" class="btn-action-cancel" onclick="window.location.href='maintain_ccsh_work_process_mats.php?func=<?php echo $folder_func; ?>'">🔙 Back</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
// แสดง Message แจ้งเตือนทันทีเมื่อเข้าหน้าจอ หากติดเงื่อนไข View Only
<?php if ($is_view_only && !empty($alert_message)): ?>
window.onload = function() {
    alert("<?php echo $alert_message; ?>");
};
<?php endif; ?>
</script>
</html>