<?php
// เริ่ม session ก่อน ANY output
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include("dbcon_mats-new.php");

$jno = isset($_GET['jno']) ? trim($_GET['jno']) : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';

// 1. ดึงข้อมูล Job Order
$job = null;
if (!empty($jno)) {
    $sql = "SELECT JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, PRODUCT_ID, ALLOY, TEMPER, 
                   THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, 
                   JOB_WORKPROCESS, JOB_STATUS, JOB_MAXTOLERANCE, PRD_REMARK1, PRD_REMARK2, OTHER_COILWIDTH 
            FROM JOBORDER1 
            WHERE JOB_ORDER = :jno";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':jno' => $jno]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$job) {
    echo "<script>alert('Job Order not found!'); window.history.back();</script>";
    exit();
}

// 2. วิเคราะห์เงื่อนไขตั้งต้นตาม Code VB f1
$work_process   = trim($job['JOB_WORKPROCESS'] ?? '');
$job_status     = trim($job['JOB_STATUS'] ?? 'OP');
$process_prefix = substr($work_process, 0, 3); // 'CL>' หรือ 'BK>'

// เช็คกระบวนการย่อย SH หรือ SC
$strCheckProcess = '';
if (strpos($work_process, 'SC') !== false) {
    $strCheckProcess = 'SC';
} elseif (strpos($work_process, 'SH') !== false) {
    $strCheckProcess = 'SH';
}

// เช็คสิทธิ์การแก้ไข Job Status (เฉพาะ OP, CL)
$status_enabled = in_array($job_status, ['OP', 'CL']);

// เช็คสิทธิ์การแก้ไข Cut Length (เฉพาะ CL>)
$length_enabled = ($process_prefix === 'CL>');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Maintain Cut Sheet Data - <?php echo htmlspecialchars($jno); ?></title>
    <?php include 'include/header.php';?>
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .edit-card { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .section-title { font-size: 16px; font-weight: 700; color: #1e293b; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; margin-bottom: 20px; }
        .form-label { font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
        .form-control-readonly { background-color: #f1f5f9 !important; border: 1px solid #cbd5e1; font-weight: 600; color: #475569; }
        .btn-save { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 10px 24px; border-radius: 8px; border: none; }
        .btn-save:hover { background-color: #1d4ed8; color: #ffffff; }
        .btn-cancel { background-color: #64748b; color: #ffffff; font-weight: 600; padding: 10px 24px; border-radius: 8px; border: none; }
        .btn-cancel:hover { background-color: #475569; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="maintain_job_data_mats.php?func=<?php echo $folder_func ?>">Maintain Job Order Data of Production</a>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <form id="frmMaintainJob" action="save_job_mats.php" method="POST">
                <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">
                <input type="hidden" id="strCheckProcess" value="<?php echo $strCheckProcess; ?>">
                <input type="hidden" id="txtWorkProcess" value="<?php echo htmlspecialchars($work_process); ?>">

                <div class="edit-card">
                    <!-- 1. Master Job Order Details (Read-Only) -->
                    <div class="section-title">📌 Job Order Specifications</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="form-label">Job Order No.</label>
                            <input type="text" class="form-control form-control-readonly" name="txtJobOrderNo" id="txtJobOrderNo" value="<?php echo htmlspecialchars($job['JOB_ORDER']); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Sale Order / Item</label>
                            <input type="text" class="form-control form-control-readonly" value="<?php echo htmlspecialchars(($job['SALEORDER_NO']??'').' / '.($job['SALEORDER_ITEM']??'')); ?>" readonly>
                            <input type="hidden" name="txtSaleOrderNo" value="<?php echo htmlspecialchars($job['SALEORDER_NO']??''); ?>">
                            <input type="hidden" name="txtSaleOrderItem" value="<?php echo htmlspecialchars($job['SALEORDER_ITEM']??''); ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Product ID</label>
                            <input type="text" class="form-control form-control-readonly" value="<?php echo htmlspecialchars($job['PRODUCT_ID']??''); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Alloy / Temper</label>
                            <input type="text" class="form-control form-control-readonly" value="<?php echo htmlspecialchars(($job['ALLOY']??'').' / '.($job['TEMPER']??'')); ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="form-label">Thickness (mm)</label>
                            <input type="text" class="form-control form-control-readonly" id="txtThickness" value="<?php echo number_format((float)($job['THICKNESS']??0), 3); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Std. Width (mm)</label>
                            <input type="text" class="form-control form-control-readonly" id="txtWidth" value="<?php echo number_format((float)($job['WIDTH']??0), 2); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Std. Length (mm)</label>
                            <input type="text" class="form-control form-control-readonly" id="txtLength" value="<?php echo number_format((float)($job['LENGTH']??0), 2); ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Work Process</label>
                            <input type="text" class="form-control form-control-readonly" value="<?php echo htmlspecialchars($work_process); ?>" readonly>
                        </div>
                    </div>

                    <!-- 2. Maintenance Input Controls -->
                    <div class="section-title" style="margin-top: 20px;">✏️ Cut Sheet Processing Input</div>
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="form-label">Job Status</label>
                            <select class="form-control" name="cmbJobStatus" id="cmbJobStatus" <?php echo $status_enabled ? '' : 'disabled'; ?>>
                                <option value="OP" <?php if($job_status=='OP') echo 'selected'; ?>>OP - Open</option>
                                <option value="CL" <?php if($job_status=='CL') echo 'selected'; ?>>CL - Closed</option>
                                <option value="CP" <?php if($job_status=='CP') echo 'selected'; ?>>CP - Completed</option>
                                <option value="HL" <?php if($job_status=='HL') echo 'selected'; ?>>HL - Hold</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group">
                            <label class="form-label">Cut Width (mm)</label>
                            <input type="number" step="any" class="form-control" name="txtCutWidth" id="txtCutWidth" value="<?php echo (float)($job['CUT_WIDTH']??0); ?>" onblur="calc_cut_width()">
                        </div>

                        <div class="col-md-3 form-group">
                            <label class="form-label">Cut Length (mm)</label>
                            <input type="number" step="any" class="form-control" name="txtCutLength" id="txtCutLength" value="<?php echo (float)($job['CUT_LENGTH']??0); ?>" <?php echo $length_enabled ? '' : 'disabled'; ?> onblur="calc_cut_length()">
                        </div>

                        <div class="col-md-3 form-group">
                            <label class="form-label">Cut Weight / Piece (kg)</label>
                            <input type="text" class="form-control form-control-readonly" name="txtCutWeightPiece" id="txtCutWeightPiece" value="<?php echo number_format((float)($job['CUT_WEIGHTPIECE']??0), 4); ?>" readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="form-label">Max Tolerance</label>
                            <select class="form-control" name="cmbJOBMAXTolerance" id="cmbJOBMAXTolerance" disabled>
                                <?php for($i=0; $i<=100; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php if(($job['JOB_MAXTOLERANCE']??0)==$i) echo 'selected'; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3 form-group" style="padding-top: 28px;">
                            <label style="font-weight:700; cursor:pointer;">
                                <input type="checkbox" name="chkOtherWidth" value="Y" <?php echo (trim($job['OTHER_COILWIDTH']??'')==='Y')?'checked':''; ?>> Other Coil Width Flag
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Production Remark 1</label>
                            <input type="text" class="form-control" name="txtProdRemark1" id="txtProdRemark1" value="<?php echo htmlspecialchars(trim($job['PRD_REMARK1']??'')); ?>" oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Production Remark 2</label>
                            <input type="text" class="form-control" name="txtProdRemark2" id="txtProdRemark2" value="<?php echo htmlspecialchars(trim($job['PRD_REMARK2']??'')); ?>" oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>

                    <!-- เปลี่ยนปุ่ม Save ให้เรียกใช้ ฟังก์ชัน JavaScript confirmSave() -->
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-12 text-right">
                            <button type="button" class="btn btn-cancel" onclick="window.location.href='maintain_job_data_mats.php?func=<?php echo $folder_func; ?>'">CANCEL</button>
                            <button type="button" class="btn btn-save" onclick="confirmSave()">💾 SAVE CHANGES</button>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>

<?php include 'include/footer.php';?>

<script type="text/javascript">
    function confirmSave() {
    var jno = $("#txtJobOrderNo").val();
    var status = $("#cmbJobStatus").val();

    // ตัวอย่าง SweetAlert หรือใช้ Native confirm
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'ยืนยันการบันทึกข้อมูล?',
            text: "ต้องการอัปเดตข้อมูล Cut Sheet สำหรับ Job " + jno + " หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmColor: '#2563eb',
            cancelColor: '#64748b',
            confirmButtonText: 'ใช่, บันทึกข้อมูล!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById("frmMaintainJob").action = "model/maintain_job_save_mats.php";
                document.getElementById("frmMaintainJob").submit();
            }
        });
    } else {
        if (confirm("คุณต้องการบันทึกการแก้ไขข้อมูล Job Order: " + jno + " ใช่หรือไม่?")) {
            document.getElementById("frmMaintainJob").action = "model/maintain_job_save_mats.php";
            document.getElementById("frmMaintainJob").submit();
        }
    }
}

// Logic คำนวณขนาด Cut Width / Length ตามเงื่อนไข VB Form_LostFocus
function calc_cut_width() {
    var workProcess = $("#txtWorkProcess").val();
    var checkProcess = $("#strCheckProcess").val();
    var cutWidth = parseFloat($("#txtCutWidth").val()) || 0;
    var stdWidth = parseFloat($("#txtWidth").val()) || 0;

    var prefix = workProcess.substring(0, 3);

    if (prefix === "CL>") {
        if (cutWidth < stdWidth) {
            if (checkProcess === "SH") {
                cutWidth = stdWidth + 20;
                if (cutWidth > 1600) cutWidth = 1600;
            } else if (checkProcess === "SC") {
                cutWidth = stdWidth + 3;
                if (cutWidth > 1600) cutWidth = 1600;
            }
        } else {
            if (cutWidth > 1600) {
                if (checkProcess === "SH") {
                    cutWidth = stdWidth + 20;
                    if (cutWidth > 1600) cutWidth = 1600;
                } else if (checkProcess === "SC") {
                    cutWidth = stdWidth + 3;
                    if (cutWidth > 1600) cutWidth = 1600;
                }
            }
        }
    } else if (prefix === "BK>") {
        if (cutWidth < stdWidth) {
            cutWidth = (checkProcess === "SH") ? 505 : 0;
        } else {
            if (cutWidth > 505) cutWidth = 505;
        }
    }

    $("#txtCutWidth").val(Math.round(cutWidth));
    calculate_weight();
}

function calc_cut_length() {
    var workProcess = $("#txtWorkProcess").val();
    var checkProcess = $("#strCheckProcess").val();
    var cutLength = parseFloat($("#txtCutLength").val()) || 0;
    var stdWidth = parseFloat($("#txtWidth").val()) || 0;
    var stdLength = parseFloat($("#txtLength").val()) || 0;

    var prefix = workProcess.substring(0, 3);

    if (prefix === "CL>") {
        if (checkProcess === "SH") {
            if (cutLength < stdWidth || cutLength > 1600) {
                cutLength = stdWidth + 20;
                if (cutLength > 1600) cutLength = 1600;
            }
        } else if (checkProcess === "SC") {
            if (cutLength < stdLength || cutLength > 4000) {
                cutLength = stdLength + 250;
                if (cutLength > 4000) cutLength = 4000;
            }
        }
    } else if (prefix === "BK>") {
        cutLength = 0;
    }

    $("#txtCutLength").val(Math.round(cutLength));
    calculate_weight();
}

// ฟังก์ชันคำนวณน้ำหนักตัวอย่าง (Weight per piece)
function calculate_weight() {
    var cutWidth = parseFloat($("#txtCutWidth").val()) || 0;
    var cutLength = parseFloat($("#txtCutLength").val()) || 0;
    var thickness = parseFloat($("#txtThickness").val()) || 0;
    
    // สูตรคำนวณน้ำหนักโลหะแผ่นโดยประมาณ: Volume * Density (2.71 / 1000000)
    var weight = (thickness * cutWidth * cutLength * 2.71) / 1000000;
    $("#txtCutWeightPiece").val(weight.toFixed(4));
}
</script>
</body>
</html>