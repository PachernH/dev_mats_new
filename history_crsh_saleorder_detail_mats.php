<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า SALEORDER_NO และ SALEORDER_ITEM จาก URL
$so_item = $_GET['so_item'];
$saleorder_no   = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : (isset($_GET['SALEORDER_NO']) ? htmlspecialchars(trim($_GET['SALEORDER_NO']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$so_data = null;

if (!empty($saleorder_no)) {
    // เงื่อนไข SQL ดึงข้อมูลจากตาราง CSTMORDR2
    $where_sql = "WHERE SALEORDER_NO = :saleorder_no";
    $params = [':saleorder_no' => $saleorder_no];

    if (!empty($so_item)) {
        $where_sql .= " AND SALEORDER_ITEM = :saleorder_item";
        $params[':saleorder_item'] = $so_item;
    }

    $sql_so = "SELECT 
                    /*** Sale Order Information ***/
                    SALEORDER_NO, SALEORDER_ITEM, CTM2_STATUS, CSTMSPPL_ID, CTM2_PONO, CTM2_POITEM,
                    ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, LENGTH, CTM2_CUSTOMERPARTNO,
                    REQUEST_DATE, CONFIRM_DATE, CTM2_ORDERWEIGHT, CTM2_ORDERPIECE, CTM2_REJECTWEIGHT, CTM2_REJECTPIECE, CTM2_REPLACEWEIGHT, CTM2_REPLACEPIECE,
                    CTM2_RELEASEWEIGHT, CTM2_RELEASEPIECE, CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE, CTM2_ACTUALWEIGHT, CTM2_ACTUALPIECE,
                    /*** History Product for Sale Order ***/
                    CTM2_PRODUCEWEIGHT, CTM2_PRODUCEPIECE, CTM2_STRETCHERWEIGHT, CTM2_STRETCHERPIECE, CTM2_CUTSHEETWEIGHT, CTM2_CUTSHEETPIECE,
                    CTM2_SHEARWEIGHT, CTM2_SHEARPIECE, CTM2_PUNCHWEIGHT, CTM2_PUNCHPIECE, CTM2_BATCHANNEALWEIGHT, CTM2_BATCHANNEALPIECE,
                    CTM2_TRANSFERWEIGHT, CTM2_TRANSFERPIECE, CTM2_ANNEALWEIGHT, CTM2_ANNEALPIECE, CTM2_SORTWEIGHT, CTM2_SORTPIECE,
                    CTM2_PACKWEIGHT, CTM2_PACKPIECE, CTM2_STOCKWEIGHT, CTM2_INVOICEWEIGHT, CTM2_INVOICEPIECE
               FROM CSTMORDR2
               {$where_sql}";
            
    $stmt_so = $conn->prepare($sql_so);
    $stmt_so->execute($params);
    $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนชิ้น (Pcs) ป้องกัน Error
function format_pcs($value) {
    if (function_exists('fmt0')) {
        return fmt0($value);
    }
    return number_format((float)($value ?? 0), 0);
}

// ฟังก์ชันช่วยจัดรูปแบบน้ำหนัก (Weight 2 ทศนิยม)
function format_wt($value) {
    if (function_exists('fmt2')) {
        return fmt2($value);
    }
    return number_format((float)($value ?? 0), 2);
}

// ฟังก์ชันช่วยจัดรูปแบบขนาด (Thickness 3 ทศนิยม)
function format_dim($value) {
    if (function_exists('fmt3')) {
        return fmt3($value);
    }
    return number_format((float)($value ?? 0), 3);
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

        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Information</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size: 24px;">
                    📋 Sale Order Details: <span style="color:#2563eb;"><a href="history_crsh_log_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $saleorder_no ?>"><?php echo htmlspecialchars($saleorder_no); ?></a></span>
                    <?php if (!empty($so_data['SALEORDER_ITEM'])): ?>
                        <span style="color:#64748b; font-size: 20px;"> (Item: <?php echo htmlspecialchars($so_data['SALEORDER_ITEM']); ?>)</span>
                    <?php endif; ?>
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

                <!-- 1. Sale Order Information -->
                <div class="dashboard-card">
                    <h4 class="card-title-g1">📌 1. Sale Order Information</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER NO.</div><div class="info-value" style="color:#2563eb; font-weight:700; background-color:#eff6ff; border-color:#bfdbfe;"><?php echo htmlspecialchars($so_data['SALEORDER_NO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER ITEM</div><div class="info-value"><?php echo htmlspecialchars($so_data['SALEORDER_ITEM'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STATUS</div><div class="info-value" style="color:#059669; font-weight:700; background-color:#ecfdf5; border-color:#a7f3d0;"><?php echo htmlspecialchars($so_data['CTM2_STATUS'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUSTOMER ID</div><div class="info-value"><?php echo htmlspecialchars($so_data['CSTMSPPL_ID'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">PO NO.</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_PONO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PO ITEM</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_POITEM'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ALLOY</div><div class="info-value"><?php echo htmlspecialchars($so_data['ALLOY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">TEMPER</div><div class="info-value"><?php echo htmlspecialchars($so_data['TEMPER'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">SURFACE GRADE</div><div class="info-value"><?php echo htmlspecialchars($so_data['SURFACE_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">METALLURGICAL GRADE</div><div class="info-value"><?php echo htmlspecialchars($so_data['METALLURGICAL_GRADE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">THICKNESS</div><div class="info-value"><?php echo format_dim($so_data['THICKNESS']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">WIDTH</div><div class="info-value"><?php echo format_wt($so_data['WIDTH']); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">LENGTH</div><div class="info-value"><?php echo format_wt($so_data['LENGTH']); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUSTOMER PART NO.</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM2_CUSTOMERPARTNO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REQUEST DATE</div><div class="info-value"><?php echo htmlspecialchars($so_data['REQUEST_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CONFIRM DATE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CONFIRM_DATE'] ?? '-'); ?></div></div>
                    </div>
                </div>

                <!-- 2. History Product for Sale Order Process -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ 2. History Product Process (Sale Order)</h4>
                    <div class="row" style="display: flex; flex-wrap: wrap;">
                        <div class="col-md-3 col-sm-6"><div class="info-label">ORDER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_ORDERWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_ORDERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REJECT QTY WT. / PCS.</div><div class="info-value" style="color:#b91c1c;"><?php echo format_wt($so_data['CTM2_REJECTWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_REJECTPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">REPLACE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_REPLACEWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_REPLACEPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">RELEASE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_RELEASEWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_RELEASEPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">MAX - MIN TOLERANCE</div><div class="info-value"><?php echo format_wt($so_data['CTM2_MAXTOLERANCE']); ?>% / <?php echo format_wt($so_data['CTM2_MINTOLERANCE']); ?>%</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ACTUAL QTY WT. / PCS.</div><div class="info-value" style="color:#0284c7; font-weight:700;"><?php echo format_wt($so_data['CTM2_ACTUALWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_ACTUALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PRODUCE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_PRODUCEWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_PRODUCEPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">STRETCHER QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_STRETCHERWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_STRETCHERPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">CUT SHEET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_CUTSHEETWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_CUTSHEETPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SHEARING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_SHEARWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_SHEARPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PUNCH HOLE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_PUNCHWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_PUNCHPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">BATCH ANNEAL QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_BATCHANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_BATCHANNEALPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TRANSFER PALLET QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_TRANSFERWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_TRANSFERPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">ANNEALING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_ANNEALWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_ANNEALPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">SORTING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_SORTWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_SORTPIECE']); ?> pcs.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PACKING QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_PACKWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_PACKPIECE']); ?> pcs.</div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">STOCK QTY WT.</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_wt($so_data['CTM2_STOCKWEIGHT']); ?> kg.</div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">INVOICE QTY WT. / PCS.</div><div class="info-value"><?php echo format_wt($so_data['CTM2_INVOICEWEIGHT']); ?> kg. / <?php echo format_pcs($so_data['CTM2_INVOICEPIECE']); ?> pcs.</div></div>
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
    window.location.assign('history_crsh_log_mats.php?func='+encodeURIComponent(data_fun)); 
}
</script>
</html>