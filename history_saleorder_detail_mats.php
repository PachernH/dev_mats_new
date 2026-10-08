<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า SALEORDER_NO จาก URL (ใช้พารามิเตอร์ search_no)
$saleorder_no = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : '';

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$so_data = null;

if (!empty($saleorder_no)) {
    // Query ดึงข้อมูลจากตาราง CSTMORDR2 ตาม SALEORDER_NO
    $sql_so = "SELECT SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, CTM2_PONO, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE,
                      THICKNESS, WIDTH, REQUEST_DATE, CONFIRM_DATE, CT2U_ORDERWEIGHT, CT2U_ORDERPIECE, CT2U_UOMWEIGHT,
                      CTM2_ORDERWEIGHT, CTM2_ORDERPIECE, CTM2_UOMWEIGHT, CTM2_REJECTWEIGHT, CTM2_REJECTPIECE, CTM2_REPLACEWEIGHT,
                      CTM2_REPLACEPIECE, CTM2_RELEASEWEIGHT, CTM2_RELEASEPIECE, CTM2_COLDMILLWEIGHT, CTM2_COLDMILLPIECE, CTM2_BATCHANNEALWEIGHT,
                      CTM2_BATCHANNEALPIECE, CTM2_PACKWEIGHT, CTM2_PACKPIECE, CTM2_STOCKWEIGHT, CTM2_STOCKPIECE
               FROM CSTMORDR2
               WHERE SALEORDER_NO = :saleorder_no";
            
    $stmt_so = $conn->prepare($sql_so);
    $stmt_so->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_so->execute();
    $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);
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
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 20px; 
        }

        .info-label { 
            font-size: 11px; 
            font-weight: 700; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 4px; 
        }
        .info-value { 
            font-size: 14px; 
            font-weight: 600; 
            color: #0f172a; 
            margin-bottom: 16px; 
            word-break: break-all; 
            background-color: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #f1f5f9;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background-color: #475569; color: #ffffff; }
    </style>
</head>
<body>

<div class="wrapper">

<?php $menu = 'HI';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_coil_log_mats.php?func=<?php echo $folder_func ?>">Coil Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Sale Order Details: <span style="color:#2563eb;"><a href="history_coil_log_mats.php?func=<?php echo $folder_func ?>&search_no=<?php echo htmlspecialchars($saleorder_no); ?>"><?php echo htmlspecialchars($saleorder_no); ?></a></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-back" onclick="back_home()">
                       ⬅️ Back
                    </button>
                </div>
            </div>

            <?php if (!$so_data): ?>
                <div class="dashboard-card">
                    <div class="alert alert-warning" style="margin:0;">
                        No details were found for Sale Order No: <strong><?php echo htmlspecialchars($saleorder_no); ?></strong>
                    </div>
                </div>
            <?php else: ?>

                <!-- Sale Order Details -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 Sale Order Details</h4>

                    <!-- 1. General Info & Customer Reference -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER NO</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($so_data['SALEORDER_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER ITEM</div><div class="info-value"><?php echo htmlspecialchars($so_data['SALEORDER_ITEM'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CSTMSPPL ID</div><div class="info-value"><?php echo htmlspecialchars($so_data['CSTMSPPL_ID'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PO NO (CTM2)</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_PONO'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 2. Specifications & Dates -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($so_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($so_data['TEMPER'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($so_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($so_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo fmt3($so_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo fmt3($so_data['WIDTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REQUEST DATE</div><div class="info-value"><?php echo htmlspecialchars($so_data['REQUEST_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CONFIRM DATE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CONFIRM_DATE'] ?? '-'); ?></div></div>
                    </div>

                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">

                    <!-- 3. Quantities & Weights (CT2U & CTM2) -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">CT2U ORDER WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CT2U_ORDERWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CT2U WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($so_data['CT2U_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CT2U ORDER PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CT2U_ORDERPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">CTM2 ORDER WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_ORDERWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CTM2 WEIGHT UOM</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_UOMWEIGHT'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CTM2 ORDER PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_ORDERPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_RELEASEWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">kg.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_RELEASEPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">UOM</div><div class="info-value">Pcs</div></div>
                    </div>
                    <hr style="border-top: 1px dashed #e2e8f0; margin: 10px 0 20px 0;">
                    <div class="row">
                        <div class="col-md-3 col-sm-6"><div class="info-label">REJECT WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_REJECTWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REJECT PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_REJECTPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REPLACE WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_REPLACEWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REPLACE PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_REPLACEPIECE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_COLDMILLWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">COLD MILL PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_COLDMILLPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_BATCHANNEALWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_BATCHANNEALPIECE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PACK WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_PACKWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACK PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_PACKPIECE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK WEIGHT</div><div class="info-value"><?php echo fmt2($so_data['CTM2_STOCKWEIGHT']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK PIECE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_STOCKPIECE'] ?? '-'); ?></div></div>
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

<script>
function back_home(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('history_coil_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>