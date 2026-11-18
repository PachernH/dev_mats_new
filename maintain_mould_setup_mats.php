<?php
session_start();

if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include("dbcon_mats-new.php");

$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : 'mats';
$mould_setup_no = isset($_GET['mno']) ? htmlspecialchars(trim($_GET['mno']), ENT_QUOTES, 'UTF-8') : '';

$data = [];
$is_view_only = false;
$alert_message = "";
$message_code = "";
$record_status = "";

if (!empty($mould_setup_no)) {
    // 1. ดึงข้อมูล Master จากตาราง MLDSTRNS2 (เทียบเคียง TakeData MOULD)
    $stmt = $conn->prepare("SELECT * FROM MLDSTRNS2 WHERE MOULDSETUP_NO = :mno");
    $stmt->execute([':mno' => $mould_setup_no]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!empty($data)) {
        $status_id = strtoupper(trim($data['MSTP_STATUS'] ?? ''));

        // 2. ตรวจสอบเงื่อนไขตาม Select Case ใน m1.txt
        switch ($status_id) {
            case 'WK':
                // ถ้าเป็น WK ให้เช็กตาราง MLDSWORK1 (เทียบเคียง TakeData MOULDWORK)
                $stmt_work = $conn->prepare("SELECT * FROM MLDSWORK1 WHERE MOULDSETUP_NO = :mno");
                $stmt_work->execute([':mno' => $mould_setup_no]);
                $real_work = $stmt_work->fetch(PDO::FETCH_ASSOC);

                if ($real_work) {
                    $is_view_only = true;
                    $alert_message = "There is real work. It can't change status.";
                } else {
                    $record_status = "Old Record";
                }
                break;

            default:
                // กรณีสถานะอื่นๆ (เช่น CL หรืออื่นๆ ที่ไม่ใช่ WK) 
                $is_view_only = true;
                $message_code = "STATUS :";
                $alert_message = "Mould Setup มีสถานะภาพเป็น : {$status_id}\nไม่เท่ากับสถานะภาพ : WK: WORK";
                break;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Maintain Mould Setup Status - Meyer Aluminium Thailand Co.,LTD.</title>
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
        .btn-action-save { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 24px; border-radius: 8px; border: none; }
        .btn-action-save:hover { background-color: #1d4ed8; }
        .btn-action-cancel { background-color: #e2e8f0; color: #475569; font-weight: 600; font-size: 14px; padding: 10px 24px; border-radius: 8px; border: none; }
        .alert-box-m1 { background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; margin-bottom: 16px; white-space: pre-line; }
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
                        Maintain Mould Setup Status
                        <?php if ($is_view_only): ?>
                            <span class="badge" style="background-color:#dc2626; margin-left:10px;">LOCKED / VIEW ONLY</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- แถบค้นหา Mould Setup No. -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card-box" style="padding: 16px 24px;">
                        <form method="GET" action="" style="display: flex; gap: 12px; align-items: flex-end;">
                            <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                            <div style="flex-grow: 1;">
                                <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px; display: block;">MOULD SETUP NO.</label>
                                <input type="text" name="mno" class="form-control-edit" value="<?php echo $mould_setup_no; ?>" placeholder="Enter or scan Mould Setup No..." required>
                            </div>
                            <button type="submit" class="btn-action-save" style="height: 40px;">🔍 Search</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ฟอร์มบันทึกสถานะ -->
            <form id="mouldSetupForm" action="model/update_mould_work_mats.php" method="POST">
                <input type="hidden" name="mould_setup_no" value="<?php echo $mould_setup_no; ?>">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">

                <div class="row">
                    <div class="col-md-7">
                        <div class="card-box">
                            <div class="card-title">
                                <span>📌 Status Record & Control</span>
                                <?php if (!empty($record_status)): ?>
                                    <span style="margin-left: auto; font-size: 12px; color: #047857; font-weight: 600;"><?php echo $record_status; ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- แสดงกล่องแจ้งเตือนจำลองตามหน้าจอ VB6 (กรณีติดเงื่อนไข BCRSH-89) -->
                            <?php if (!empty($message_code)): ?>
                                <div class="alert-box-m1">
                                    <span style="color: #dc2626; font-weight: 700; display: inline-block; margin-bottom: 4px;">[<?php echo $message_code; ?>]</span>
                                    <div><?php echo $alert_message; ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Mould Setup No.</label>
                                    <div class="form-control-view" style="color: #2563eb; font-weight: 700;"><?php echo htmlspecialchars($data['MOULDSETUP_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Mould Setup Status (cmbStatusID)</label>
                                    <select name="status_id" id="status_id" class="form-control-edit" <?php echo ($is_view_only || empty($data)) ? 'disabled style="background-color:#e2e8f0; cursor:not-allowed;"' : ''; ?>>
                                        <option value="WK" <?php echo (strtoupper($data['MSTP_STATUS'] ?? '') === 'WK') ? 'selected' : ''; ?>>WK</option>
                                        <option value="OP" <?php echo (strtoupper($data['MSTP_STATUS'] ?? '') === 'OP') ? 'selected' : ''; ?>>OP</option>
                                    </select>
                                </div>
                            </div>

                            <hr style="margin: 20px 0; border-top: 1px dashed #cbd5e1;">

                            <!-- รายละเอียดฟิลด์ตาม m1.txt -->
                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Maintenance No. 1</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($data['MAINTENANCE_NO1'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Die Set No.</label>
                                    <div class="form-control-view" style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($data['DIESET_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Maintenance No. 2</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($data['MAINTENANCE_NO2'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Die No.</label>
                                    <div class="form-control-view" style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($data['DIE_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Maintenance No. 3</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($data['MAINTENANCE_NO3'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Punch No. 1</label>
                                    <div class="form-control-view" style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($data['PUNCH_NO1'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group-custom">
                                    <label>Maintenance No. 4</label>
                                    <div class="form-control-view"><?php echo htmlspecialchars($data['MAINTENANCE_NO4'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6 form-group-custom">
                                    <label>Punch No. 2</label>
                                    <div class="form-control-view" style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($data['PUNCH_NO2'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนปุ่มจัดการ -->
                    <div class="col-md-5">
                        <div class="card-box">
                            <div class="card-title"><span>⚡ Action Controls</span></div>
                            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                                ทำการตรวจสอบสถานะและบันทึกการเปลี่ยนแปลงผ่านระบบฐานข้อมูลส่วนกลาง
                            </p>

                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php if (!empty($data) && !$is_view_only): ?>
                                    <button type="submit" class="btn-action-save" style="width: 100%; text-align: center;">💾 Save Status Changes</button>
                                <?php else: ?>
                                    <button type="button" class="btn-action-save" disabled style="width: 100%; background-color: #94a3b8; cursor: not-allowed; text-align: center;">🔒 Save Disabled</button>
                                <?php endif; ?>

                                <button type="button" class="btn-action-cancel" onclick="window.location.href='maintain_mould_setup_mats.php?func=<?php echo $folder_func; ?>'" style="width: 100%; text-align: center;">🔄 Cancel / Clear</button>
                            </div>
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
<?php if ($is_view_only && !empty($alert_message) && empty($message_code)): ?>
window.onload = function() {
    alert("<?php echo $alert_message; ?>");
};
<?php endif; ?>
</script>
</html>