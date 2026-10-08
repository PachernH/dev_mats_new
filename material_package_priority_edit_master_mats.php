<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$refdata = isset($_GET['refdata']) ? trim($_GET['refdata']) : '';
$keys = explode('*', $refdata);

// รับค่าหัวข้อหลัก 6 ตัว (Composite Primary Key)
$product_id        = isset($keys[0]) ? trim($keys[0]) : '';
$package_treatment = isset($keys[1]) ? trim($keys[1]) : '';
$width_from        = isset($keys[2]) ? trim($keys[2]) : '';
$width_to          = isset($keys[3]) ? trim($keys[3]) : '';
$length_from       = isset($keys[4]) ? trim($keys[4]) : '';
$length_to         = isset($keys[5]) ? trim($keys[5]) : '';

// 1. ดึงรายการ MATERIAL_CODE ทั้งหมดจากตาราง MTRLMSTR1 มาเตรียมไว้สำหรับทำเป็นตัวเลือก
$mat_options = array();
try {
    $sql_mat = "SELECT DISTINCT MATERIAL_CODE, DESCRIPTION FROM MTRLMSTR1 ORDER BY MATERIAL_CODE ASC";
    $stmt_mat = $conn->prepare($sql_mat);
    $stmt_mat->execute();
    while ($r_mat = $stmt_mat->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($r_mat['MATERIAL_CODE'])) {
            $mat_options[] = array(
                'code' => trim($r_mat['MATERIAL_CODE']),
                'desc' => trim($r_mat['DESCRIPTION'] ?? '')
            );
        }
    }
} catch (PDOException $e) {
    // กรณี Query ขัดข้อง
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
            word-break: break-word;
            margin-bottom: 15px;
        }
        
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

        .table-detail th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            vertical-align: middle !important;
        }
        .table-detail td {
            vertical-align: middle !important;
            padding: 6px !important;
        }
        .table-detail .form-control {
            height: 38px;
            font-size: 14px;
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

<!-- Datalist สำหรับใช้พิมพ์ค้นหารหัส Material -->
<datalist id="mat_code_list">
    <?php foreach ($mat_options as $m): ?>
        <option value="<?php echo htmlspecialchars($m['code'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($m['code'] . ($m['desc'] !== '' ? ' - ' . $m['desc'] : ''), ENT_QUOTES, 'UTF-8'); ?>
        </option>
    <?php endforeach; ?>
</datalist>

<div class="wrapper">
    
    <?php $menu = 'gp4'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Material Package Priority Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row" style="margin-bottom:20px;">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('material_package_priority_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <form id="form_pkg_priority" method="POST" onsubmit="return false;">

            <!-- Hidden Keys ส่งกลับไปประมวลผลหลังบ้าน -->
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="package_treatment" value="<?php echo htmlspecialchars($package_treatment, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="width_from" value="<?php echo htmlspecialchars($width_from, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="width_to" value="<?php echo htmlspecialchars($width_to, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="length_from" value="<?php echo htmlspecialchars($length_from, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="length_to" value="<?php echo htmlspecialchars($length_to, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- การ์ดแสดง 6 ข้อมูลหลัก -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📦 Material Package Header Information </h4>
                        <div class="row">                      

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">PRODUCT ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($product_id !== '' ? $product_id : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">PACKAGE TREATMENT</div>
                                <div class="info-value"><?php echo htmlspecialchars($package_treatment !== '' ? $package_treatment : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>    
                            
                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WIDTH FROM</div>
                                <div class="info-value"><?php echo htmlspecialchars($width_from !== '' ? $width_from : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">WIDTH TO</div>
                                <div class="info-value"><?php echo htmlspecialchars($width_to !== '' ? $width_to : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>   

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">LENGTH FROM</div>
                                <div class="info-value"><?php echo htmlspecialchars($length_from !== '' ? $length_from : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="col-md-2 col-sm-4">
                                <div class="info-label">LENGTH TO</div>
                                <div class="info-value"><?php echo htmlspecialchars($length_to !== '' ? $length_to : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- ตารางแก้ไขรายการลูก -->
            <div class="row">
                <div class="col-md-12">
                    <div class="display-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 class="card-title-sub" style="margin: 0; border: none;">📊 Edit Priority Items Breakdown</h4>
                            <button type="button" class="btn btn-sm btn-info" style="border-radius: 6px; font-weight:600;" onclick="addRow()">➕ Add Item (Add Row)</button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-detail" id="tbl_priority">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">PACKAGE PRIORITY <span class="text-danger">*</span></th>
                                        <th>MATERIAL CODE (MTRLMSTR1) <span class="text-danger">*</span></th>
                                        <th style="width: 140px;">STACK ROW</th>
                                        <th style="width: 140px;">STACK COLUMN</th>
                                        <th style="width: 200px;">WEIGHT PER PACKAGE</th>
                                        <th style="width: 80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $detail_sql = "SELECT PACKAGE_PRIORITY, MATERIAL_CODE, STACK_ROW, STACK_COLUMN, WEIGHTPERPACKAGE 
                                                   FROM MTRLSPCT2 
                                                   WHERE LTRIM(RTRIM(PRODUCT_ID))        = LTRIM(RTRIM(:pid))
                                                     AND LTRIM(RTRIM(PACKAGE_TREATMENT)) = LTRIM(RTRIM(:pkg))
                                                     AND LTRIM(RTRIM(WIDTH_FROM))        = LTRIM(RTRIM(:wf))
                                                     AND LTRIM(RTRIM(WIDTH_TO))          = LTRIM(RTRIM(:wt))
                                                     AND LTRIM(RTRIM(LENGTH_FROM))       = LTRIM(RTRIM(:lf))
                                                     AND LTRIM(RTRIM(LENGTH_TO))         = LTRIM(RTRIM(:lt))
                                                   ORDER BY PACKAGE_PRIORITY ASC";

                                    $stmt = $conn->prepare($detail_sql);
                                    $stmt->execute([
                                        ':pid' => $product_id,
                                        ':pkg' => $package_treatment,
                                        ':wf'  => $width_from,
                                        ':wt'  => $width_to,
                                        ':lf'  => $length_from,
                                        ':lt'  => $length_to
                                    ]);

                                    $has_row = false;
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $has_row = true;
                                        $cur_mat = trim($row['MATERIAL_CODE'] ?? '');
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="number" class="form-control text-center pkg-prio-input" name="pkg_priority[]" value="<?php echo htmlspecialchars($row['PACKAGE_PRIORITY'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" required min="1">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control" name="material_code[]" list="mat_code_list" value="<?php echo htmlspecialchars($cur_mat, ENT_QUOTES, 'UTF-8'); ?>" placeholder="-- Select or type your search Material --" autocomplete="off" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-center" name="stack_row[]" value="<?php echo htmlspecialchars($row['STACK_ROW'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" min="0">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-center" name="stack_column[]" value="<?php echo htmlspecialchars($row['STACK_COLUMN'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" min="0">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control text-right" name="weightperpackage[]" value="<?php echo htmlspecialchars($row['WEIGHTPERPACKAGE'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" min="0">
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
                                            <td><input type="number" class="form-control text-center pkg-prio-input" name="pkg_priority[]" value="1" required min="1"></td>
                                            <td>
                                                <input type="text" class="form-control" name="material_code[]" list="mat_code_list" placeholder="-- เลือกหรือพิมพ์ค้นหา Material --" autocomplete="off" required>
                                            </td>
                                            <td><input type="number" class="form-control text-center" name="stack_row[]" value="0" min="0"></td>
                                            <td><input type="number" class="form-control text-center" name="stack_column[]" value="0" min="0"></td>
                                            <td><input type="number" class="form-control text-right" name="weightperpackage[]" value="0" min="0"></td>
                                            <td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">Delete</button></td>
                                        </tr>
                                        <?php
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
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('material_package_priority_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_priority_data()"> 💾 Save Data </button>
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
// ฟังก์ชันคำนวณและ Auto Run ค่า Priority ถัดไป
function getNextPriority() {
    var maxVal = 0;
    $(".pkg-prio-input").each(function() {
        var val = parseInt($(this).val());
        if (!isNaN(val) && val > maxVal) {
            maxVal = val;
        }
    });
    return maxVal + 1;
}

// เพิ่มแถวใหม่ พร้อม Auto Run รหัส Priority (สามารถพิมพ์แก้ไขเองได้)
function addRow() {
    var nextPrio = getNextPriority();
    
    var tr = '<tr>' +
        '<td><input type="number" class="form-control text-center pkg-prio-input" name="pkg_priority[]" value="' + nextPrio + '" required min="1"></td>' +
        '<td><input type="text" class="form-control" name="material_code[]" list="mat_code_list" placeholder="-- Search Material --" autocomplete="off" required></td>' +
        '<td><input type="number" class="form-control text-center" name="stack_row[]" value="0" min="0"></td>' +
        '<td><input type="number" class="form-control text-center" name="stack_column[]" value="0" min="0"></td>' +
        '<td><input type="number" class="form-control text-right" name="weightperpackage[]" value="0" min="0"></td>' +
        '<td align="center"><button type="button" class="btn-del-row" onclick="removeRow(this)">Delete</button></td>' +
        '</tr>';
    $("#tbl_priority tbody").append(tr);
}

// ลบแถว
function removeRow(btn) {
    if ($("#tbl_priority tbody tr").length > 1) {
        $(btn).closest('tr').remove();
    } else {
        alert("At least one item must be included.");
    }
}

function submit_priority_data() {
    var data_fun = $("#func").val();
    var formData = $("#form_pkg_priority").serialize();

    $.ajax({
        url: "model/update_material_package_priority_master_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if (resp.status === "success" || resp.message === true) {
                alert("The Material Package Priority information has been successfully updated.");
                window.location.assign('material_package_priority_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "The data could not be saved."));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while connecting to the server: " + error);
        }
    });
}
</script>
</html>