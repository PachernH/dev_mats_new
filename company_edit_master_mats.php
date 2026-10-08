<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php'; // การเชื่อมต่อฐานข้อมูลหลัก

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['CID']) ? htmlspecialchars(trim($_GET['CID']), ENT_QUOTES, 'UTF-8') : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
if(!empty($cs)){
    $data_cs = RT_Company($cs);
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
        .main-panel .content { padding: 20px 20px !important; }
        .display-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .info-label { font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; margin-bottom: 15px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        input[readonly] { background-color: #f1f5f9 !important; cursor: not-allowed; }
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp1';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Company Master</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('company_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ Back</button>
                </div>
            </div>
            
            <form id="form_cust_supp" method="POST">
            <div class="row">

                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Company Profile & Identity</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-label">Company ID</div>
                                <input type="text" class="form-control" name="COMPANY_ID" value="<?php echo htmlspecialchars($data_cs[0] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Company Name</div>
                                <input type="text" class="form-control" name="COMPANY_NAME" value="<?php echo htmlspecialchars($data_cs[1] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            

                            <div class="col-md-12">
                                <div class="info-label">Address (Line 1, 2, 3)</div>
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS1" value="<?php echo htmlspecialchars($data_cs[2] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS2" value="<?php echo htmlspecialchars($data_cs[3] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS3" value="<?php echo htmlspecialchars($data_cs[4] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6"><div class="info-label">Telephone</div><input type="text" class="form-control" name="CONSIGNEE_TELEPHONE" value="<?php echo htmlspecialchars($data_cs[5] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">FAX</div><input type="text" class="form-control" name="CONSIGNEE_FAX" value="<?php echo htmlspecialchars($data_cs[6] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Email</div><input type="email" class="form-control" name="CONSIGNEE_EMAIL" value="<?php echo htmlspecialchars($data_cs[7] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Contact Name</div><input type="text" class="form-control" name="CONSIGNEE_CONTACT" value="<?php echo htmlspecialchars($data_cs[8] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Position</div><input type="text" class="form-control" name="CONSIGNEE_POSITION" value="<?php echo htmlspecialchars($data_cs[9] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                        </div>
                    </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('company_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_custsupp_data()">💾 Save Data</button>
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
function submit_custsupp_data() {
    var data_fun = document.getElementById("func").value;
    var formData = $("#form_cust_supp").serialize();

    $.ajax({
        url: "model/update_company_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success"){
                alert("บันทึกข้อมูลสำเร็จเรียบร้อยแล้ว");
                window.location.assign('company_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "The data could not be saved"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred during data transmission: " + error);
        }
    });
}
</script>
</html>