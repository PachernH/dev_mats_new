<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['CIW']) ? trim($_GET['CIW']) : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
if ($cs !== '') {
    $data_cs = RT_Workprocess($cs);
}

// ตรวจสอบข้อมูลจากฟังก์ชัน (เช็คทั้งแบบ Index และ ชื่อคอลัมน์)
$v_work_process = (isset($data_cs['WORK_PROCESS']) && $data_cs['WORK_PROCESS'] !== '') ? $data_cs['WORK_PROCESS'] : ((isset($data_cs[0]) && $data_cs[0] !== '') ? $data_cs[0] : '');
$v_product_id   = (isset($data_cs['PRODUCT_ID']) && $data_cs['PRODUCT_ID'] !== '') ? $data_cs['PRODUCT_ID'] : ((isset($data_cs[1]) && $data_cs[1] !== '') ? $data_cs[1] : '');
$v_work_type    = (isset($data_cs['WORK_TYPE']) && $data_cs['WORK_TYPE'] !== '') ? $data_cs['WORK_TYPE'] : ((isset($data_cs[2]) && $data_cs[2] !== '') ? $data_cs[2] : '');
$v_description  = (isset($data_cs['DESCRIPTION']) && $data_cs['DESCRIPTION'] !== '') ? $data_cs['DESCRIPTION'] : ((isset($data_cs[3]) && $data_cs[3] !== '') ? $data_cs[3] : '');
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
        .display-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .info-label { font-weight: 600; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; margin-bottom: 15px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        input[readonly] { background-color: #f1f5f9 !important; cursor: not-allowed; font-weight: bold; color: #334155; }
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp4';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Work Process Master</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('work_process_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ Back</button>
                </div>
            </div>
            
            <form id="form_work_process" method="POST" onsubmit="return false;">

            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Edit Work Process Master Details</h4>
                        <div class="row">                      

                            <div class="col-md-6">
                                <div class="info-label">WORK PROCESS <span class="text-danger">* (Cannot be edited)</span></div>
                                <input type="text" class="form-control" name="work_process" id="work_process" value="<?php echo htmlspecialchars($v_work_process, ENT_QUOTES, 'UTF-8'); ?>" readonly required>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">PRODUCT ID <span class="text-danger">*</span></div>
                                <select class="form-control" name="product_id" id="product_id" required>
                                    <option value="">-- PRODUCT ID --</option>
                                    <option value="CC" <?php if(trim($v_product_id) == 'CC') echo 'selected'; ?>>CC</option>
                                    <option value="SH" <?php if(trim($v_product_id) == 'SH') echo 'selected'; ?>>SH</option>
                                    <option value="CO" <?php if(trim($v_product_id) == 'CO') echo 'selected'; ?>>CO</option>
                                </select>
                            </div>    
                            
                            <div class="col-md-6">
                                <div class="info-label">WORK TYPE <span class="text-danger">*</span></div>
                                <select class="form-control" name="work_type" id="work_type" required>
                                    <option value="">-- WORK TYPE --</option>
                                    <option value="A" <?php if(trim($v_work_type) == 'A') echo 'selected'; ?>>A</option>
                                    <option value="P" <?php if(trim($v_work_type) == 'P') echo 'selected'; ?>>P</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">DESCRIPTION</div>
                                <input type="text" class="form-control" name="description" id="description" value="<?php echo htmlspecialchars($v_description, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ระบุ DESCRIPTION" autocomplete="off">
                            </div>   

                        </div>
                    </div>
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 20px 0 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('work_process_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_work_process_data()">💾 Save Data</button>
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
function submit_work_process_data() {
    var work_process = $("#work_process").val().trim();
    var product_id = $("#product_id").val().trim();
    var work_type = $("#work_type").val().trim();
    var data_fun = $("#func").val();

    if (work_process === "" || product_id === "" || work_type === "") {
        alert("Please fill in all required fields (*).");
        return false;
    }

    var formData = $("#form_work_process").serialize();

    $.ajax({
        url: "model/update_work_process_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success" || resp.message === true){
                alert("Data has been successfully 💾 Saved.");
                window.location.assign('work_process_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Failed to 💾 Save data"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while sending the data: " + error);
        }
    });
}
</script>
</html>