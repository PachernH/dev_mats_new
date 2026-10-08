<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : (isset($_GET['coil_no']) ? htmlspecialchars(trim($_GET['coil_no']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

// Query ดึงข้อมูล Coil Product Details และ ตัวเลือก USE_FORPROCESS
$row_data = null;
$use_forprocess_list = [];

if (!empty($coil_no)) {
    // 1. Query ดึงข้อมูล Coil หลัก ร่วมกับ JOBORDER1 เพื่อเอา JOB_REMARK
    $sql = "SELECT p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, p.JOB_PROCESS,
            p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
            p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
            p.COIL_CASTWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_BALANCEWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_REMARK, p.LINE_PROCESS, 
            p.BLN_SLITWIDTH, p.ACC_SLITWIDTH, p.RSET_NO, p.RECIPE_NO, p.RECIPE_ITEM, p.TOTAL_PASS, p.CURRENT_PASS, p.THICKNESS_FINAL,
            i.JOB_REMARK
            FROM COILPROD1 p 
            LEFT JOIN JOBORDER1 i ON p.JOB_PROCESS = i.JOB_ORDER
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Query ดึงรายการตัวเลือก USE_FORPROCESS จากฐานข้อมูล
    $sql_use_for = "SELECT USE_FORPROCESS 
                    FROM COILPROD1 
                    WHERE USE_FORPROCESS IS NOT NULL AND USE_FORPROCESS <> '' 
                    GROUP BY USE_FORPROCESS 
                    ORDER BY USE_FORPROCESS ASC";
    $stmt_use_for = $conn->prepare($sql_use_for);
    $stmt_use_for->execute();
    $use_forprocess_list = $stmt_use_for->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        /* Detail Group Header */
        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .card-title-g3 { 
            color: #047857; 
            border-bottom: 2px solid #a7f3d0; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        /* Label and Value Typography */
        .info-label { 
            font-size: 12px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 15px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 18px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        /* Form Input Styling */
        .form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .form-control-custom {
            width: 100%;
            height: 42px;
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        /* ปุ่ม Save Data */
        .btn-save {
            background-color: #10b981 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none !important;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
        }
        .btn-save:hover, .btn-save:focus, .btn-save:active {
            background-color: #059669 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        /* ปุ่ม Cancel */
        .btn-cancel {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none !important;
            transition: all 0.2s;
            cursor: pointer;
            margin-left: 10px;
            display: inline-block;
        }
        .btn-cancel:hover, .btn-cancel:focus, .btn-cancel:active {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            box-shadow: none !important;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            height: 44px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'A2';?>

    <?php 
    if (!empty($folder_func) && !empty($group_func)) {
        include 'include/'.$folder_func.'/navigation.php'; 
    }
    ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="coil_remark_process_mats.php?func=<?php echo $folder_func ?>">Coil Remark and Use For Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📦 Coil Details: <span style="color:#2563eb;"><a href="coil_remark_process_mats.php?func=<?php echo $folder_func ?>&COIL=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home_coil()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Coil Number: <strong><?php echo htmlspecialchars($coil_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

               <!-- GROUP 1: Product Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Group 1: Coil Product Details</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['JOB_PROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LINE PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['LINE_PROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE (SG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE (MG)</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT STATUS</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_STATUS'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['WIDTH']) : number_format((float)($row_data['WIDTH'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_THICKNESS']) : number_format((float)($row_data['F_THICKNESS'] ?? 0), 3); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_WIDTH']) : number_format((float)($row_data['F_WIDTH'] ?? 0), 3); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_WORKPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NEXTPROCESS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['USE_FORPROCESS'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUALWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_PRODUCEWEIGHT'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ROLL SET NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['RSET_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RECIPE : ITEM</div><div class="info-value"><?php echo htmlspecialchars($row_data['RECIPE_NO'] ?? '-')?> : <?php echo number_format((float)($row_data['RECIPE_ITEM'] ?? 0), 2); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TOTAL</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['TOTAL_PASS']) : number_format((float)($row_data['TOTAL_PASS'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CURRENT PASS</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['CURRENT_PASS']) : number_format((float)($row_data['CURRENT_PASS'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">FINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['THICKNESS_FINAL']) : number_format((float)($row_data['THICKNESS_FINAL'] ?? 0), 2); ?></div></div>
                        <div class="col-md-3 col-sm-6"></div>

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">COIL REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? '-'); ?>
                            </div>
                        </div>

                        <div class="col-md-12 col-sm-12">
                            <div class="info-label">JOB REMARK</div>
                            <div class="info-value" style="min-height: 48px; font-weight: 500; color: #334155;">
                                <?php echo htmlspecialchars($row_data['JOB_REMARK'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 2: Coil Remark & Process Entry -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">⚙️ Group 2: Update Use For Process & Coil Remark</h4>
                    <form id="form_coil_remark" action="model/save_coil_remark_mats.php" method="POST" onsubmit="return validate_form();">
                        
                        <!-- Hidden Inputs -->
                        <input type="hidden" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" />
                        <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>" />

                        <div class="row">
                            <div class="col-md-12 col-sm-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="use_forprocess">Use For Process</label>
                                    <select class="form-control-custom" id="use_forprocess" name="use_forprocess">
                                        <option value="">-- Select Use For Process --</option>
                                        <?php 
                                        $current_use_for = trim($row_data['USE_FORPROCESS'] ?? '');
                                        foreach ($use_forprocess_list as $item): 
                                            $val = trim($item['USE_FORPROCESS'] ?? '');
                                            if (empty($val)) continue;
                                            $selected = ($val === $current_use_for) ? 'selected="selected"' : '';
                                        ?>
                                            <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12 col-sm-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label for="coil_remark">Coil Remark</label>
                                    <textarea class="form-control-custom" id="coil_remark" name="coil_remark" style="height: 80px; resize: vertical;" placeholder="Enter coil remark..."><?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div style="margin-top: 15px; text-align: right; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
                            <button type="submit" id="btn_save_remark" class="btn btn-save">
                                💾 Save Data
                            </button>
                            <button type="button" id="btn_cancel_remark" class="btn btn-cancel" onclick="clear_input_form()">
                                ❌ Cancel
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
var defaultUseForProcess = <?php echo json_encode($row_data['USE_FORPROCESS'] ?? ''); ?>;
var defaultCoilRemark = <?php echo json_encode($row_data['COIL_REMARK'] ?? ''); ?>;

function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('coil_remark_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

// ฟังก์ชันล้างค่า (Cancel)
function clear_input_form() {
    document.getElementById('use_forprocess').value = defaultUseForProcess;
    document.getElementById('coil_remark').value = defaultCoilRemark;
}

// ฟังก์ชันตรวจสอบความถูกต้องก่อนบันทึก
function validate_form() {
    //return confirm('Confirm saving Use For Process and Coil Remark data?');
}
</script>
</html>