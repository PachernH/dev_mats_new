<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า SALEORDER_NO และ JOB_ORDER จาก URL
$saleorder_no = isset($_GET['search_no']) ? htmlspecialchars(trim($_GET['search_no']), ENT_QUOTES, 'UTF-8') : (isset($_GET['SALEORDER_NO']) ? htmlspecialchars(trim($_GET['SALEORDER_NO']), ENT_QUOTES, 'UTF-8') : '');
$job_order    = isset($_GET['JOB_ORDER']) ? htmlspecialchars(trim($_GET['JOB_ORDER']), ENT_QUOTES, 'UTF-8') : (isset($_GET['job_order']) ? htmlspecialchars(trim($_GET['job_order']), ENT_QUOTES, 'UTF-8') : '');

// ป้องกันกรณีไม่มี Session
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");
include 'function_mats.php';

$so_data        = null;
$item_list      = [];
$coil_process_list = [];
$total_coil_count = 0;
$total_coil_weight = 0;

if (!empty($saleorder_no)) {
    // 1. Query ดึงข้อมูล Sale Order Information จาก CSTMORDR1
    $sql_so = "SELECT 
                    SALEORDER_NO, SALEORDER_DATE, ORDER_CATEGORY, CSTMSPPL_ID, CTM1_PONO, CUSTOMS_CODE, CTM1_TOTALAMOUNT 
               FROM CSTMORDR1 
               WHERE SALEORDER_NO = :saleorder_no";
            
    $stmt_so = $conn->prepare($sql_so);
    $stmt_so->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_so->execute();
    $so_data = $stmt_so->fetch(PDO::FETCH_ASSOC);

    if ($so_data) {
        // 1.1 ดึง Name Customer จาก CSSPMSTR1
        if (!empty($so_data['CSTMSPPL_ID'])) {
            $sql_cust = "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :cstm_id";
            $stmt_cust = $conn->prepare($sql_cust);
            $stmt_cust->bindParam(':cstm_id', $so_data['CSTMSPPL_ID'], PDO::PARAM_STR);
            $stmt_cust->execute();
            $cust_row = $stmt_cust->fetch(PDO::FETCH_ASSOC);
            $so_data['CONSIGNEE_COMPANY'] = $cust_row['CONSIGNEE_COMPANY'] ?? '-';
        } else {
            $so_data['CONSIGNEE_COMPANY'] = '-';
        }

        // 1.2 ดึง Description ของ ORDER CATEGORY จาก ODCTMSTR1
        if (!empty($so_data['ORDER_CATEGORY'])) {
            $sql_cat = "SELECT ORDER_CATEGORY, DESCRIPTION FROM ODCTMSTR1 WHERE ORDER_CATEGORY = :order_cat";
            $stmt_cat = $conn->prepare($sql_cat);
            $stmt_cat->bindParam(':order_cat', $so_data['ORDER_CATEGORY'], PDO::PARAM_STR);
            $stmt_cat->execute();
            $cat_row = $stmt_cat->fetch(PDO::FETCH_ASSOC);
            $so_data['DESCRIPTION'] = $cat_row['DESCRIPTION'] ?? '-';
        } else {
            $so_data['DESCRIPTION'] = '-';
        }
    }

    // 2. Query ดึงข้อมูล Sale Order Item List จาก CSTMORDR2
    $sql_item = "SELECT 
                    SALEORDER_NO, PRODUCT_ID, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE,
                    THICKNESS, WIDTH, LENGTH, CTM2_ORDERWEIGHT, CTM2_STATUS
                 FROM [MATS-NEW].dbo.CSTMORDR2 
                 WHERE SALEORDER_NO = :saleorder_no";

    $stmt_item = $conn->prepare($sql_item);
    $stmt_item->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_item->execute();
    $item_list = $stmt_item->fetchAll(PDO::FETCH_ASSOC);

    // 3. Query ดึงข้อมูล History Product Process (Sale Order) แบบ List จาก COILPROD1
    $sql_coil_proc = "SELECT 
                        COIL_NO, PRODUCT_ID, SALEORDER_NO, JOB_ORDER, JOB_PROCESS, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE,
                        THICKNESS, WIDTH, COIL_BALANCEWEIGHT, COIL_STATUS 
                      FROM [MATS-NEW].dbo.COILPROD1 
                      WHERE SALEORDER_NO = :saleorder_no";

    $stmt_coil_proc = $conn->prepare($sql_coil_proc);
    $stmt_coil_proc->bindParam(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_coil_proc->execute();
    $coil_process_list = $stmt_coil_proc->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณหาผลรวมจำนวน และ น้ำหนักรวม
    if (!empty($coil_process_list)) {
        $total_coil_count = count($coil_process_list);
        foreach ($coil_process_list as $c_row) {
            $total_coil_weight += (float)($c_row['COIL_BALANCEWEIGHT'] ?? 0);
        }
    }
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนชิ้น (Pcs)
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

// ฟังก์ชันช่วยจัดรูปแบบขนาด (3 ทศนิยม)
function format_dim($value) {
    if (function_exists('fmt3')) {
        return fmt3($value);
    }
    return number_format((float)($value ?? 0), 3);
}

// ฟังก์ชันช่วยจัดรูปแบบจำนวนเงิน
function format_num($value) {
    if (function_exists('fmt2')) {
        return fmt2($value);
    }
    return number_format((float)($value ?? 0), 2);
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

        .card-title-g3 { 
            color: #047857; 
            border-bottom: 2px solid #a7f3d0; 
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

        .table-responsive {
            border: none !important;
            margin-top: 10px;
        }
        .custom-job-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        .custom-job-table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 12px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .custom-job-table tbody td {
            padding: 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        .custom-job-table tfoot td {
            background-color: #f8fafc;
            font-weight: 700;
            color: #0f172a;
            padding: 12px 10px;
            border-top: 2px solid #cbd5e1;
            white-space: nowrap;
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
                    📋 Sale Order Details: <span style="color:#2563eb;"><a href="history_coil_log_mats.php?func=<?php echo $folder_func ?>&search_no=<?php echo $saleorder_no ?>"><?php echo htmlspecialchars($saleorder_no); ?></a></span>
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
                        <div class="col-md-3 col-sm-6"><div class="info-label">SALE ORDER DATE</div><div class="info-value"><?php echo htmlspecialchars($so_data['SALEORDER_DATE'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUSTOMER ID</div><div class="info-value"><?php echo htmlspecialchars($so_data['CSTMSPPL_ID'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">NAME CUSTOMER</div><div class="info-value" style="color:#0f172a; font-weight:700;"><?php echo htmlspecialchars($so_data['CONSIGNEE_COMPANY'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">ORDER CATEGORY</div><div class="info-value"><?php echo htmlspecialchars($so_data['ORDER_CATEGORY'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">DESCRIPTION</div><div class="info-value"><?php echo htmlspecialchars($so_data['DESCRIPTION'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">PURCHASE ORDER NO.</div><div class="info-value"><?php echo htmlspecialchars($so_data['CTM1_PONO'] ?? '-'); ?></div></div>
                        <div class="col-md-3 col-sm-6"><div class="info-label">CUSTOMS CODE</div><div class="info-value"><?php echo htmlspecialchars($so_data['CUSTOMS_CODE'] ?? '-'); ?></div></div>

                        <div class="col-md-3 col-sm-6"><div class="info-label">TOTAL AMOUNT</div><div class="info-value" style="color:#059669; font-weight:700;"><?php echo format_num($so_data['CTM1_TOTALAMOUNT']); ?></div></div>
                    </div>
                </div>

                <!-- 2. Sale Order Item List -->
                <div class="dashboard-card">
                    <h4 class="card-title-g3">📦 2. Sale Order Item List 
                        <span style="font-size: 14px; font-weight: normal; color: #64748b;">
                            (Refer : <?php echo htmlspecialchars($saleorder_no); ?>)
                        </span>
                    </h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Sale Order No</th>
                                    <th style="text-align: center;">Product ID</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">Temper</th>
                                    <th style="text-align: center;">Surface Grade</th>
                                    <th style="text-align: center;">Metallurgical Grade</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: center;">Length</th>
                                    <th style="text-align: right;">Order Weight</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($item_list)): ?>
                                    <tr>
                                        <td colspan="12" align="center" style="color: #64748b; padding: 20px;">
                                            No item records found for this sale order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($item_list as $index => $item): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #2563eb;"><?php echo htmlspecialchars($item['SALEORDER_NO'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['ALLOY'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['TEMPER'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($item['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo format_dim($item['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($item['WIDTH']); ?></td>
                                            <td align="center"><?php echo format_wt($item['LENGTH']); ?></td>
                                            <td align="right"><?php echo format_wt($item['CTM2_ORDERWEIGHT']); ?> kg.</td>
                                            <td align="center" style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($item['CTM2_STATUS'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. History Product Process (Sale Order) -->
                <div class="dashboard-card">
                    <h4 class="card-title-g2">⚙️ 3. History Product Process (Sale Order)</h4>
                    <div class="table-responsive">
                        <table class="table custom-job-table">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">#</th>
                                    <th>Coil No</th>
                                    <th style="text-align: center;">Product ID</th>
                                    <th>Sale Order No</th>
                                    <th>Job Order</th>
                                    <th style="text-align: center;">Job Process</th>
                                    <th style="text-align: center;">Alloy</th>
                                    <th style="text-align: center;">Temper</th>
                                    <th style="text-align: center;">Surface Grade</th>
                                    <th style="text-align: center;">Metallurgical Grade</th>
                                    <th style="text-align: center;">Thickness</th>
                                    <th style="text-align: center;">Width</th>
                                    <th style="text-align: right;">Coil Balance Weight</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($coil_process_list)): ?>
                                    <tr>
                                        <td colspan="14" align="center" style="color: #64748b; padding: 20px;">
                                            No process history records found for this sale order.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($coil_process_list as $index => $c_item): ?>
                                        <tr>
                                            <td align="center"><?php echo $index + 1; ?></td>
                                            <td style="font-weight: 700; color: #0284c7;"><?php echo htmlspecialchars($c_item['COIL_NO'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['PRODUCT_ID'] ?? '-'); ?></td>
                                            <td style="color: #2563eb;"><?php echo htmlspecialchars($c_item['SALEORDER_NO'] ?? '-'); ?></td>
                                            <td style="color: #0f172a; font-weight: 600;"><?php echo htmlspecialchars($c_item['JOB_ORDER'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['JOB_PROCESS'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['ALLOY'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['TEMPER'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['SURFACE_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo htmlspecialchars($c_item['METALLURGICAL_GRADE'] ?? '-'); ?></td>
                                            <td align="center"><?php echo format_dim($c_item['THICKNESS']); ?></td>
                                            <td align="center"><?php echo format_wt($c_item['WIDTH']); ?></td>
                                            <td align="right" style="font-weight: 600; color: #0369a1;"><?php echo format_wt($c_item['COIL_BALANCEWEIGHT']); ?> kg.</td>
                                            <td align="center" style="font-weight: 700; color: #059669;"><?php echo htmlspecialchars($c_item['COIL_STATUS'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($coil_process_list)): ?>
                            <tfoot>
                                <tr>
                                    <td colspan="12" align="right" style="font-weight: 700; font-size: 14px;">
                                        Total Items / Total Weight:
                                    </td>
                                    <td align="right" style="font-weight: 700; font-size: 15px; color: #0284c7;">
                                        <?php echo format_wt($total_coil_weight); ?> kg.
                                    </td>
                                    <td align="center" style="font-weight: 700; font-size: 14px; color: #059669;">
                                        (<?php echo number_format($total_coil_count); ?> Rolls)
                                    </td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
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