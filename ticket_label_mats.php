<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// รับค่า cno และกำหนดตัวแปรให้ตรงกัน
$cno = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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
        
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }

        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 42px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus {
            border-bottom-color: #2563eb !important;
            outline: none;
        }

        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            height: 42px;
            padding: 0 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background-color: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
        }
        
        #user_table tbody tr {
            cursor: default;
        }

        .btn-badge-clickable {
            display: inline-block;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 20px;
            text-align: center;
            width: 100%;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            text-decoration: none !important;
            box-shadow: none;
        }

        .btn-action-group {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
        }
        .btn-print-custom {
            background-color: #2eab87;
            color: #ffffff;
            font-weight: 700;
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            box-shadow: 0 2px 4px rgba(46, 171, 135, 0.2);
            white-space: nowrap;
        }
        .btn-print-custom:hover {
            background-color: #238e6e;
            color: #ffffff;
            box-shadow: 0 4px 6px rgba(46, 171, 135, 0.3);
        }
        .btn-print-custom.printed-active {
            background-color: #059669 !important;
            color: #ffffff !important;
        }

        .btn-print-inside {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-weight: 700;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-print-inside:hover {
            background-color: #10b981;
            color: #ffffff;
            border-color: #10b981;
        }

        .btn-print-outside {
            background-color: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
            font-weight: 700;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-print-outside:hover {
            background-color: #f97316;
            color: #ffffff;
            border-color: #f97316;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'mats';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="ticket_label_mats.php?func=<?php echo $folder_func ?>">Product Ticket Label</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">PRODUCT_NO / Job Number/ Sale Order</label>
                    <input class="form-control-minimal" name="coil_no" id="coil_no" type="text" placeholder="Enter the code you want to search for...." value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_ticket_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_ticket_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Product Ticket Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px; text-align: center;">PRODUCT NO</th>
                                        <th>PACK DATE</th>
                                        <th>CUSTOMER</th>
                                        <th>SALEORDER NO / ITEM</th>
                                        <th>JOB ORDER</th>
                                        <th>MAT IN</th>
                                        <th style="text-align:right;">NET WEIGHT</th>
                                        <th style="width: 320px; text-align: center;">ACTION</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $y = date('Y');

                                    // แก้ไขวันที่สิ้นสุดของปีให้เป็น 31 ธันวาคม อย่างถูกต้อง
                                    $date_st  = $y.'-01-01 00:00:00';
                                    $date_end = $y.'-12-31 23:59:59';

                                    $params = [];

                                    // SQL Base Query จากตาราง PACKPROD1
                                    $base_sql = "SELECT TOP(50) 
                                                    PACK_DATE, 
                                                    PRODUCT_NO, 
                                                    JOB_ORDER, 
                                                    SALEORDER_NO, 
                                                    SALEORDER_ITEM, 
                                                    MATERIAL_IN, 
                                                    PACK_NETWEIGHT,
                                                    PACK_PACKAGEWEIGHT 
                                                FROM PACKPROD1 
                                                WHERE PRODUCT_ID = 'CO'";

                                    if ($d == date('Y-m-d')) {
                                        if ($cno != '') {
                                            $sql = $base_sql . " AND (PRODUCT_NO LIKE :cno OR JOB_ORDER LIKE :cno OR SALEORDER_NO LIKE :cno) ORDER BY PACK_OPERATEDATE DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        } else {
                                            $sql = $base_sql . " AND PACK_OPERATEDATE BETWEEN :date_st AND :date_end ORDER BY PACK_OPERATEDATE DESC";
                                            $params[':date_st']  = $date_st;
                                            $params[':date_end'] = $date_end;
                                        }
                                    } else {
                                        if ($cno != '') {
                                            $sql = $base_sql . " AND (PRODUCT_NO LIKE :cno OR JOB_ORDER LIKE :cno OR SALEORDER_NO LIKE :cno) ORDER BY PACK_OPERATEDATE DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        } else {
                                            $sql = $base_sql . " AND PACK_OPERATEDATE BETWEEN :date_f AND :date_e ORDER BY PACK_OPERATEDATE DESC";
                                            $params[':date_f'] = $d." 00:00:00";
                                            $params[':date_e'] = $d." 23:59:59";
                                        }
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    $records = $stmt->fetchAll(PDO::FETCH_ASSOC); // ดึงข้อมูลล่วงหน้าป้องกัน Pointer ชนกันในขณะลูป

                                    // รายชื่อลูกค้ารหัส MANAKIN สำหรับแสดงปุ่ม Inside/Outside
                                    $man_customers = ['MANC', 'MANI', 'MANS', 'MANT'];

                                    foreach ($records as $row) {
                                        $safe_product_no    = htmlspecialchars($row['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $pack_date          = !empty($row['PACK_DATE']) ? htmlspecialchars(date('Y-m-d', strtotime($row['PACK_DATE'])), ENT_QUOTES, 'UTF-8') : '';
                                        $safe_so_no         = htmlspecialchars($row['SALEORDER_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_so_item       = htmlspecialchars($row['SALEORDER_ITEM'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_job_order     = htmlspecialchars($row['JOB_ORDER'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $safe_material_in   = htmlspecialchars($row['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $pack_netweight     = (float)($row['PACK_NETWEIGHT'] ?? 0);
                                        $pack_pkgweight     = (float)($row['PACK_PACKAGEWEIGHT'] ?? 0);

                                        // -------------------------------------------------------------
                                        // ดึงข้อมูล CSTMSPPL_ID และชื่อลูกค้าจาก CSTMORDR2 และ CSSPMSTR1
                                        // -------------------------------------------------------------
                                        $cust_id   = '-';
                                        $cust_name = '-';
                                        $so_data   = null;
                                        $job_data  = null;

                                        if (!empty($safe_so_no) && !empty($safe_so_item)) {
                                            $sql_so_cust = "SELECT SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, CTM2_CUSTOMERORDER, CTM2_ENDCUSTOMERPO, 
                                                                  CTM2_CUSTOMERPARTNO, CTM2_CUSTOMERSALEORDER, THICKNESS, WIDTH, [LENGTH], CTM2_UOMDIMENSION,
                                                                  CT2U_THICKNESS, CT2U_WIDTH, CT2U_LENGTH, CT2U_UOMDIMENSION, CTM2_PONO, CTM2_POITEM, ALLOY, TEMPER
                                                           FROM CSTMORDR2 
                                                           WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
                                            $stmt_so_cust = $conn->prepare($sql_so_cust);
                                            $stmt_so_cust->bindParam(':so_no', $safe_so_no, PDO::PARAM_STR);
                                            $stmt_so_cust->bindParam(':so_item', $safe_so_item, PDO::PARAM_STR);
                                            $stmt_so_cust->execute();
                                            $so_data = $stmt_so_cust->fetch(PDO::FETCH_ASSOC);

                                            if ($so_data && !empty($so_data['CSTMSPPL_ID'])) {
                                                $cust_id = trim($so_data['CSTMSPPL_ID']);

                                                // Query หาชื่อลูกค้าจาก CSSPMSTR1
                                                $sql_cust = "SELECT CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2 
                                                             FROM CSSPMSTR1 
                                                             WHERE CSTMSPPL_ID = :cust_id";
                                                $stmt_cust = $conn->prepare($sql_cust);
                                                $stmt_cust->bindParam(':cust_id', $cust_id, PDO::PARAM_STR);
                                                $stmt_cust->execute();
                                                $res_cust = $stmt_cust->fetch(PDO::FETCH_ASSOC);
                                                if ($res_cust && !empty($res_cust['CONSIGNEE_COMPANY'])) {
                                                    $cust_name = trim($res_cust['CONSIGNEE_COMPANY']);
                                                }
                                            }
                                        }

                                        if (!empty($safe_job_order)) {
                                            $sql_job_info = "SELECT JOB_ORDER, JOBORDER_DATE, ALLOY, TEMPER FROM JOBORDER1 WHERE JOB_ORDER = :job_order";
                                            $stmt_job_info = $conn->prepare($sql_job_info);
                                            $stmt_job_info->bindParam(':job_order', $safe_job_order, PDO::PARAM_STR);
                                            $stmt_job_info->execute();
                                            $job_data = $stmt_job_info->fetch(PDO::FETCH_ASSOC);
                                        }

                                        $is_man_customer = in_array($cust_id, $man_customers);

                                        echo "<tr>";
                                        echo "<td><span class='btn-badge-clickable'>".$safe_product_no."</span></td>";
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:14px;'>".$pack_date."</td>";
                                        echo "<td><strong style='color:#0284c7;'>".htmlspecialchars($cust_id)."</strong><br><span style='font-size:12px; color:#64748b;'>".htmlspecialchars($cust_name)."</span></td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".$safe_so_no." : ".$safe_so_item."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".$safe_job_order."</td>";
                                        echo "<td>".$safe_material_in."</td>";
                                        echo "<td style='text-align:right; font-weight:700;'>".number_format($pack_netweight, 2)."</td>";
                                        
                                        // ปุ่ม Action แสดงตามเงื่อนไขลูกค้า
                                        echo "<td style='text-align:center;'>";
                                        echo "<div class='btn-action-group'>";
                                        echo "<button type='button' class='btn-print-custom' onclick='previewPrintLabel(\"".$safe_product_no."\", \"".$safe_job_order."\", this)'>🖨️ Ticket Label</button>";

                                        if ($is_man_customer) {
                                            echo "<button type='button' class='btn-print-inside' onclick='openInsideCoilModal(\"".$safe_product_no."\", \"".number_format($pack_netweight, 2)."\", \"".number_format($pack_pkgweight, 2)."\")'>🏷️ Inside</button>";
                                            echo "<button type='button' class='btn-print-outside' onclick='openOutsideCoilModal(\"".$safe_product_no."\")'>🏷️ Outside</button>";
                                        }

                                        echo "</div>";
                                        echo "</td>";
                                        
                                        echo "</tr>";                                                         
                                    }
                                ?>                                              
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Container สำหรับโหลด Modal Print Label ผ่าน AJAX -->
<div id="modalPrintContainer"></div>

<!-- เรียกใช้งาน Modal สำหรับ Inside Coil & Outside Coil Label -->
<?php include 'inside_coil_label_mats.php'; ?>
<?php include 'outside_coil_label_mats.php'; ?>

</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

const PRINT_URL = 'print_product_ticket_label_mats.php'; 

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: false,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[0, 'desc']],
    buttons: []
});

function search_ticket_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no").value; 
    window.location.assign('ticket_label_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}    

function previewPrintLabel(productNo, jobOrder, btnElement) {
    if (!productNo) {
        alert('ไม่พบข้อมูล PRODUCT NO.');
        return;
    }
    
    $.ajax({
        url: PRINT_URL,
        type: 'GET',
        data: {
            productno: productNo,
            joborder: jobOrder
        },
        success: function(response) {
            $('#modalPrintContainer').html(response);
            
            if (btnElement) {
                btnElement.classList.add('printed-active');
                btnElement.style.backgroundColor = '#059669';
                btnElement.style.color = '#ffffff';
            }
        },
        error: function() {
            alert('เกิดข้อผิดพลาดในการโหลดข้อมูล Ticket');
        }
    });
}

function openInsideCoilModal(prodNo, netKg, grossKg) {
    if (document.getElementById('in_lbl_coil_no')) {
        document.getElementById('in_lbl_coil_no').innerText = prodNo;
    }
    
    var nKg = parseFloat(netKg.replace(/,/g, '')) || 0;
    var gKg = parseFloat(grossKg.replace(/,/g, '')) || 0;
    
    var nLbs = Math.round(nKg * 2.20462);
    var gLbs = Math.round(gKg * 2.20462);

    if (document.getElementById('in_lbl_net_kg')) document.getElementById('in_lbl_net_kg').innerText = nKg.toLocaleString();
    if (document.getElementById('in_lbl_gross_kg')) document.getElementById('in_lbl_gross_kg').innerText = gKg.toLocaleString();
    if (document.getElementById('in_lbl_net_lbs')) document.getElementById('in_lbl_net_lbs').innerText = nLbs.toLocaleString();
    if (document.getElementById('in_lbl_gross_lbs')) document.getElementById('in_lbl_gross_lbs').innerText = gLbs.toLocaleString();

    $('#modalInsideCoil').modal('show');
}

function openOutsideCoilModal(prodNo) {
    $('#modalOutsideCoil').modal('show');
}
</script>
</html>