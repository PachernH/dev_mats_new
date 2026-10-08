<?php
// เริ่ม session ก่อน ANY output
session_start();

include 'function_mats.php';

// ดึงค่า COIL และตรวจสอบข้อมูลนำเข้า
$coil_no = isset($_GET['COIL']) ? htmlspecialchars(trim($_GET['COIL']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

// Query ดึงข้อมูล COILPROD1 ครบทุกกลุ่ม
$row_data = null;
if (!empty($coil_no)) {
    $sql = "SELECT 
                -- Group 1: Product + Location
                COIL_NO, BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, 
                COUNTRY_ORIGIN, MATERIAL_IN, COIL_CALCULATEWEIGHT, COIL_ACTUALWEIGHT, 
                COIL_STARTTIME, COIL_ENDTIME, LOCATION_ID,
                
                -- Group 2: Specification
                ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, 
                THICKNESS, WIDTH, ACTUAL_WIDTH,
                
                -- Group 3: Edge Curl & Calculate
                COIL_EDGECURLA, COIL_EDGECURLB, COIL_EDGECURLC, 
                COIL_EDGECURLD, COIL_EDGECURLE, TOTAL_TI, IN_TI, DELTA_TI, COIL_UPDATEDATE
            FROM COILPROD1 
            WHERE COIL_NO = :coil";
            
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
        .main-panel {
            background-color: #f8fafc !important;
        }
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

        /* Detail Group Cards Header */
        .card-title-g1 { 
            color: #1e40af; 
            border-bottom: 2px solid #bfdbfe; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }
        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 20px; 
        }
        .card-title-g3 { 
            color: #b45309; 
            border-bottom: 2px solid #fde68a; 
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

        /* Back Button */
        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background-color: #475569;
            color: #ffffff;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
    <!-- เรียก เมนูด้านซ้าย (Sidebar/Navigation) -->
    <?php $menu = 'A4'; ?>
    <?php 
        include 'include/'.$folder_func.'/navigation.php';
    ?>

    <div class="main-panel">
        <!-- Top Navbar -->
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="coil_production_mats.php?func=<?php echo $folder_func ?>">Casting Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <!-- Title Bar & Back Button -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b;">
                    📋 Coil Details: <span style="color:#2563eb;"><a href="coil_production_mats.php?func=<?php echo $folder_func ?>&cno=<?php echo $coil_no ?>"><?php echo $coil_no; ?></a></span>
                </h3>
                <button type="button" class="btn btn-back" onclick="window.history.back();">
                   ⬅️ Back
                </button>
            </div>

            <?php if (!$row_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                       No details were found for Coil Number: <strong><?php echo $coil_no; ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: Product Information (รวม LOCATION ID) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Product Information</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH NO</div><div class="info-value"><?php echo htmlspecialchars($row_data['BATCH_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRIMARY OF SMELT</div><div class="info-value"><?php echo htmlspecialchars($row_data['PRIMARY_SMELT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SECONDARY OF SMELT</div><div class="info-value"><?php echo htmlspecialchars($row_data['SECONDARY_SMELT'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">COUNTRY OF MELT</div><div class="info-value"><?php echo htmlspecialchars($row_data['COUNTRY_MELT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COUNTRY OF ORIGIN</div><div class="info-value"><?php echo htmlspecialchars($row_data['COUNTRY_ORIGIN'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">MATERIAL IN</div><div class="info-value"><?php echo htmlspecialchars($row_data['MATERIAL_IN'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LOCATION ID</div><div class="info-value"><?php echo htmlspecialchars($row_data['LOCATION_ID'] ?? '-'); ?></div></div>

                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL WEIGHT</div><div class="info-value"><?php echo fmt2($row_data['COIL_ACTUALWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">START DATE - TIME</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_STARTTIME'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">END DATE - TIME</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_ENDTIME'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: Specification -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ Specification</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo fmt2($row_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt2($row_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL WIDTH</div><div class="info-value"><?php echo fmt2($row_data['ACTUAL_WIDTH']); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 3: Edge Curl & Calculate -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">📍 Edge Curl & Calculate</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">EDGE CURL A</div><div class="info-value"><?php echo fmt2($row_data['COIL_EDGECURLA']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">EDGE CURL B</div><div class="info-value"><?php echo fmt2($row_data['COIL_EDGECURLB']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">EDGE CURL C</div><div class="info-value"><?php echo fmt2($row_data['COIL_EDGECURLC']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">EDGE CURL D</div><div class="info-value"><?php echo fmt2($row_data['COIL_EDGECURLD']); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">EDGE CURL E</div><div class="info-value"><?php echo fmt2($row_data['COIL_EDGECURLE']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TOTAL TI</div><div class="info-value"><?php echo fmt2($row_data['TOTAL_TI']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">IN TI</div><div class="info-value"><?php echo fmt2($row_data['IN_TI']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">DELTA TI</div><div class="info-value"><?php echo fmt2($row_data['DELTA_TI']); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">UPDATE DATE</div><div class="info-value"><?php echo htmlspecialchars($row_data['COIL_UPDATEDATE'] ?? '-'); ?></div></div>
                    </div>
                </div>

            <?php endif; ?>

        </div>

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>" />
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>
</html>