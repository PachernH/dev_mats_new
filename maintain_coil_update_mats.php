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

// Query ดึงข้อมูล Coil Product Details
$row_data = null;

// ตัวแปรเก็บ Master Lists สำหรับ Dropdown
$locations = [];
$alloys = [];
$surface_grades = [];
$metallurgical_grades = [];

// 1. ดึงข้อมูล Master Lists สำหรับ Dropdown
try {
    // LOCATION_ID
    $stmt_loc = $conn->query("SELECT LOCATION_ID, DESCRIPTION FROM LCTNMSTR1 ORDER BY LOCATION_ID ASC");
    $locations = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);

    // ALLOY
    $stmt_alloy = $conn->query("SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY");
    $alloys = $stmt_alloy->fetchAll(PDO::FETCH_ASSOC);

    // SURFACE_GRADE
    $stmt_sg = $conn->query("SELECT SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC");
    $surface_grades = $stmt_sg->fetchAll(PDO::FETCH_ASSOC);

    // METALLURGICAL_GRADE
    $stmt_mg = $conn->query("SELECT METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC");
    $metallurgical_grades = $stmt_mg->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Master Master Error: " . $e->getMessage());
}

// 2. Query ดึงข้อมูล Coil
if (!empty($coil_no)) {
    $sql = "SELECT 
                p.COIL_NO, p.PRODUCT_REFERENCE, p.BATCH_NO, p.MATERIAL_IN, p.CSTMSPPL_ID, p.ALLOY, p.TEMPER, p.GRADE, 
                p.SURFACE_GRADE, p.METALLURGICAL_GRADE, p.LOCATION_ID, p.THICKNESS, p.WIDTH, p.F_THICKNESS, p.F_WIDTH, 
                p.T5_TEMPERATURE, p.COIL_STATUS, p.COIL_WORKPROCESS, p.COIL_NEXTPROCESS, p.USE_FORPROCESS,
                p.COIL_CASTWEIGHT, p.COIL_COMBINEWEIGHT, p.COIL_ACTUALWEIGHT, p.COIL_PRODUCEWEIGHT, p.COIL_SCRAPWEIGHT, p.COIL_BALANCEWEIGHT, 
                p.COIL_REMARK, p.LINE_PROCESS,
                i.INSPECTION_DATE, i.MAL_CASTNO
            FROM COILPROD1 p
            LEFT JOIN COILINSP1 i ON p.COIL_NO = i.COIL_NO
            WHERE p.COIL_NO = :coil";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $coil_no, PDO::PARAM_STR);
    $stmt->execute();
    $row_data = $stmt->fetch(PDO::FETCH_ASSOC);
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

        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

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

        .form-control-edit {
            width: 100%;
            height: 40px;
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            background-color: #ffffff;
            border: 1.5px solid #2563eb;
            border-radius: 6px;
            outline: none;
            margin-bottom: 18px;
            transition: all 0.2s ease-in-out;
        }
        .form-control-edit:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        textarea.form-control-edit {
            height: auto;
            min-height: 70px;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 20px;
            height: 42px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }

        .btn-save {
            background-color: #16a34a;
            color: #ffffff;
            font-weight: 700;
            font-size: 15px;
            padding: 10px 24px;
            height: 42px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-save:hover { background-color: #15803d; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'A4';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="maintain_coil_mats.php?func=<?php echo $folder_func ?>">Maintain Coil Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    ✏️ Edit Coil Details: <span style="color:#2563eb;"><a href="maintain_coil_mats.php?func=<?php echo $folder_func ?>&cno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
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

                <!-- Form ปรับแก้ไขข้อมูล -->
                <form action="model/update_maintain_coil_mats.php" method="POST" id="editCoilForm">
                    <input type="hidden" name="COIL_NO" value="<?php echo htmlspecialchars($row_data['COIL_NO'] ?? ''); ?>">
                    <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">

                    <div class="dashboard-card">
                        <h4 class="card-title-g1">📦 Coil Product Information & Edit Parameters</h4>
                        <div class="row">
                            <!-- ข้อมูล Readonly -->
                            <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO.</div><div class="info-value" style="color:#2563eb; font-weight:700;"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT REF</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRODUCT_REFERENCE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">MAL CAST NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['MAL_CASTNO'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">FURNACE BATCH NO.</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN - COIL FROM</div><div class="info-value"><?php echo htmlspecialchars(($row_data['MATERIAL_IN'] ?? '').' '.($row_data['CSTMSPPL_ID'] ? '('.$row_data['CSTMSPPL_ID'].')' : '')); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">INSPECTION DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['INSPECTION_DATE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">LINE PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['LINE_PROCESS'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['GRADE'] ?? '-'); ?></div></div>

                            <!-- ALLOY (Dropdown) -->
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label" style="color:#2563eb;">ALLOY *</div>
                                <select name="ALLOY" class="form-control-edit" required>
                                    <option value="">-- Select ALLOY --</option>
                                    <?php foreach ($alloys as $item): ?>
                                        <?php 
                                            $val = trim($item['ALLOY']); 
                                            $currVal = trim($row_data['ALLOY'] ?? '');
                                        ?>
                                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($val === $currVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($val); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- SURFACE_GRADE (Dropdown) -->
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label" style="color:#2563eb;">SURFACE GRADE (SG) *</div>
                                <select name="SURFACE_GRADE" class="form-control-edit" required>
                                    <option value="">-- Select Surface Grade --</option>
                                    <?php foreach ($surface_grades as $item): ?>
                                        <?php 
                                            $val = trim($item['SURFACE_GRADE']); 
                                            $currVal = trim($row_data['SURFACE_GRADE'] ?? '');
                                        ?>
                                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($val === $currVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($val); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- METALLURGICAL_GRADE (Dropdown) -->
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label" style="color:#2563eb;">METALLURGICAL GRADE (MG) *</div>
                                <select name="METALLURGICAL_GRADE" class="form-control-edit" required>
                                    <option value="">-- Select Metallurgical Grade --</option>
                                    <?php foreach ($metallurgical_grades as $item): ?>
                                        <?php 
                                            $val = trim($item['METALLURGICAL_GRADE']); 
                                            $currVal = trim($row_data['METALLURGICAL_GRADE'] ?? '');
                                        ?>
                                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($val === $currVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($val); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- LOCATION_ID (Dropdown) -->
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label" style="color:#2563eb;">LOCATION ID</div>
                                <select name="LOCATION_ID" class="form-control-edit">
                                    <option value="">-- Select Location --</option>
                                    <?php foreach ($locations as $item): ?>
                                        <?php 
                                            $val = trim($item['LOCATION_ID']); 
                                            $desc = trim($item['DESCRIPTION'] ?? '');
                                            $currVal = trim($row_data['LOCATION_ID'] ?? '');
                                        ?>
                                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($val === $currVal) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($val . ($desc !== '' ? ' - ' . $desc : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- COIL_STATUS (Dropdown: เฉพาะ AC กับ CL) -->
                            <div class="col-md-3 col-sm-6">
                                <div class="info-label" style="color:#2563eb;">PRODUCT STATUS *</div>
                                <?php $currStatus = trim($row_data['COIL_STATUS'] ?? ''); ?>
                                <select name="COIL_STATUS" class="form-control-edit" required>
                                    <option value="AC" <?php echo ($currStatus === 'AC') ? 'selected' : ''; ?>>AC</option>
                                    <option value="CL" <?php echo ($currStatus === 'CL') ? 'selected' : ''; ?>>CL</option>
                                </select>
                            </div>

                            <!-- ขนาดและอุณหภูมิ Readonly -->
                            <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['THICKNESS']) : number_format((float)($row_data['THICKNESS'] ?? 0), 3); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['WIDTH']) : number_format((float)($row_data['WIDTH'] ?? 0), 3); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL THICKNESS</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_THICKNESS']) : number_format((float)($row_data['F_THICKNESS'] ?? 0), 3); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">ORIGINAL WIDTH</div><div class="info-value"><?php echo function_exists('fmt3') ? fmt3($row_data['F_WIDTH']) : number_format((float)($row_data['F_WIDTH'] ?? 0), 3); ?></div></div>

                            <div class="col-md-3 col-sm-6"><div class="info-label">T5 TEMPERATURE</div><div class="info-value"><?php echo htmlspecialchars($row_data['T5_TEMPERATURE'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">WORK PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_WORKPROCESS'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">NEXT PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NEXTPROCESS'] ?? '-'); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">USE FOR PROCESS</div><div class="info-value"><?php echo htmlspecialchars($row_data['USE_FORPROCESS'] ?? '-'); ?></div></div>

                            <!-- ส่วนข้อมูลน้ำหนัก Readonly -->
                            <div class="col-md-3 col-sm-6"><div class="info-label">COIL COMBINEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_COMBINEWEIGHT']) : number_format((float)($row_data['COIL_COMBINEWEIGHT'] ?? 0), 2); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">COIL ACTUALWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_ACTUALWEIGHT']) : number_format((float)($row_data['COIL_ACTUALWEIGHT'] ?? 0), 2); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">COIL PRODUCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_PRODUCEWEIGHT']) : number_format((float)($row_data['COIL_PRODUCEWEIGHT'] ?? 0), 2); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">COIL SCRAPWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_SCRAPWEIGHT']) : number_format((float)($row_data['COIL_SCRAPWEIGHT'] ?? 0), 2); ?></div></div>
                            <div class="col-md-3 col-sm-6"><div class="info-label">COIL BALANCEWEIGHT (KG)</div><div class="info-value"><?php echo function_exists('fmt2') ? fmt2($row_data['COIL_BALANCEWEIGHT']) : number_format((float)($row_data['COIL_BALANCEWEIGHT'] ?? 0), 2); ?></div></div>

                            <!-- Remark ที่เปิดให้แก้ไข -->
                            <div class="col-md-12 col-sm-12">
                                <div class="info-label" style="color:#2563eb;">COIL REMARK</div>
                                <textarea name="COIL_REMARK" class="form-control-edit" maxlength="100" rows="2"><?php echo htmlspecialchars($row_data['COIL_REMARK'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 15px;">
                            <button type="submit" class="btn btn-save" onclick="return confirm('ยืนยันการบันทึกการแก้ไขข้อมูลหรือไม่?');">
                                💾 Save Changes
                            </button>
                        </div>
                    </div>
                </form>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
function back_home_coil(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_coil_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>