<?php
// เริ่ม session ก่อน ANY output
session_start();

include 'function_mats.php';

// รับค่า REQUEST_NO จาก URL (เช่น rno=RM-26-0033)
$r_no = isset($_GET['pno']) ? htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8') : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

$row_data_g1 = null;
$row_data_g2 = null;

if (!empty($r_no)) {
    // -------------------------------------------------------------
    // GROUP 1: Product Information
    // -------------------------------------------------------------
    $sql_g1 = "SELECT PRODUCT_NO, COIL_NO, PRODUCT_ID, JOB_ORDER, ALLOY, TEMPER, 
                      GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, 
                      [LENGTH], PRODUCT_WEIGHT, REMELT_REASON 
               FROM PRODRMLT2 
               WHERE PRODUCT_NO = :req_no";
               
    $stmt1 = $conn->prepare($sql_g1);
    $stmt1->bindParam(':req_no', $r_no, PDO::PARAM_STR);
    $stmt1->execute();
    $row_data_g1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------
    // GROUP 2: Sale Order & Job Order Information (ใช้ JOB_ORDER จาก Group 1)
    // -------------------------------------------------------------
    if ($row_data_g1 && !empty($row_data_g1['JOB_ORDER'])) {
        $sql_g2 = "SELECT TOP(1) c.CSTMSPPL_ID, c.CTM2_PONO, j.JOB_ORDER, 
                          j.JOB_RELEASEWEIGHT, c.SALEORDER_NO, c.CTM2_ORDERWEIGHT    
                   FROM JOBORDER1 AS j 
                   JOIN CSTMORDR2 c ON j.SALEORDER_NO = c.SALEORDER_NO  
                   WHERE j.JOB_ORDER = :job_order";
                   
        $stmt2 = $conn->prepare($sql_g2);
        $stmt2->bindParam(':job_order', $row_data_g1['JOB_ORDER'], PDO::PARAM_STR);
        $stmt2->execute();
        $row_data_g2 = $stmt2->fetch(PDO::FETCH_ASSOC);
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
        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
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
    <?php $menu = 'A3'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="remelt_production_mats.php?func=<?php echo $folder_func ?>">Product Remelt Requisition</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b;">
                    📋 Detail Remelt No: <span style="color:#2563eb;"><?php echo $r_no; ?></span>
                </h3>
                <button type="button" class="btn btn-back" onclick="window.history.back();">
                   ⬅️ Back
                </button>
            </div>

            <?php if (!$row_data_g1): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No detailed information was found for Request No: <strong><?php echo $r_no; ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- GROUP 1: Product Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📦 Product Information</h4>
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT NO</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['PRODUCT_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COIL NO</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['COIL_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT ID</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['PRODUCT_ID'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['JOB_ORDER'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo isset($row_data_g1['THICKNESS']) ? fmt2($row_data_g1['THICKNESS']) : '-'; ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo isset($row_data_g1['WIDTH']) ? fmt2($row_data_g1['WIDTH']) : '-'; ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo isset($row_data_g1['LENGTH']) ? fmt2($row_data_g1['LENGTH']) : '-'; ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCT WEIGHT</div><div class="info-value"><?php echo isset($row_data_g1['PRODUCT_WEIGHT']) ? fmt2($row_data_g1['PRODUCT_WEIGHT']) : '-'; ?></div></div>
                        <div class="col-md-9 col-sm-12"><div class="info-label">REMELT REASON</div><div class="info-value"><?php echo htmlspecialchars($row_data_g1['REMELT_REASON'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- GROUP 2: Sale Order & Job Order Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">📑 Sale Order & Job Order Information</h4>
                    <div class="row">
                        <div class="col-md-4 col-sm-6"><div class="info-label">CUSTOMER ID (CSTMSPPL_ID)</div><div class="info-value"><?php echo htmlspecialchars($row_data_g2['CSTMSPPL_ID'] ?? '-'); ?></div></div>
                        <div class="col-md-4 col-sm-6"><div class="info-label">CUSTOMER PO NO (CTM2_PONO)</div><div class="info-value"><?php echo htmlspecialchars($row_data_g2['CTM2_PONO'] ?? '-'); ?></div></div>
                        <div class="col-md-4 col-sm-6"><div class="info-label">JOB ORDER</div><div class="info-value"><?php echo htmlspecialchars($row_data_g2['JOB_ORDER'] ?? '-'); ?></div></div>
                        
                        <div class="col-md-4 col-sm-6"><div class="info-label">JOB RELEASE WEIGHT</div><div class="info-value"><?php echo isset($row_data_g2['JOB_RELEASEWEIGHT']) ? fmt2($row_data_g2['JOB_RELEASEWEIGHT']) : '-'; ?></div></div>
                        <div class="col-md-4 col-sm-6"><div class="info-label">SALE ORDER NO</div><div class="info-value"><?php echo htmlspecialchars($row_data_g2['SALEORDER_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-4 col-sm-6"><div class="info-label">ORDER WEIGHT (CTM2_ORDERWEIGHT)</div><div class="info-value"><?php echo isset($row_data_g2['CTM2_ORDERWEIGHT']) ? fmt2($row_data_g2['CTM2_ORDERWEIGHT']) : '-'; ?></div></div>
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