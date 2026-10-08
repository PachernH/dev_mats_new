<?php
// เริ่ม session ก่อน ANY output
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['d'])) {
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า pno (Product No)
$pno = !isset($_GET['pno']) ? '' : htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8');

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

        .table-responsive { border: none !important; margin-top: 15px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
            padding: 16px 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        #user_table tbody td {
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }
        
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td { background-color: #f0f9ff !important; }

        .btn-badge-clickable {
            display: inline-block;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 20px;
            text-align: center;
            width: 100%;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            text-decoration: none !important;
            transition: all 0.2s ease;
        }

        .btn-action-input {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            width: 100%;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);
            transition: all 0.2s;
        }
        .btn-action-input:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A3';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="sheet_production_inspec_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="product_no">Product Number</label>
                    <input class="form-control-minimal" name="product_no" id="product_no" type="text" placeholder="Enter the Product No. you want to search for..." value="<?php echo htmlspecialchars($pno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_product_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_product" onclick="search_product_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Circle or Sheet Inspection Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">PRODUCT NO</th>
                                        <th style="width: 120px; text-align: center;">DATE</th>
                                        <th>ALLOY / TEMPER</th>
                                        <th>THICKNESS</th>
                                        <th>WIDTH x LENGTH</th>
                                        <th>PIECE</th>
                                        <th>WEIGHT</th>
                                        <th>STATUS</th>
                                        <th style="width: 110px; text-align: center;">Input</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $y=date('Y');

                                    $date_st = $y.'-01-01';
                                    $date_st = date('Y-m-01',strtotime($date_st))." 00:00:00";

                                    $date_end = $y.'-12-01';
                                    $date_end = date('Y-m-t',strtotime($date_end))." 23:59:59";

                                    $params = [];

                                    if($d == date('Y-m-d')){

                                        if($pno != ''){
                                            $sql = "SELECT A.* FROM CRSHPROD1 AS A WHERE A.PRODUCT_NO LIKE :pno ORDER BY A.CRSH_STARTDATE DESC";
                                            $params[':pno'] = '%'.$pno.'%';
                                        }else{
                                            $sql = "SELECT A.* FROM CRSHPROD1 AS A WHERE A.CRSH_STATUS='OP' AND A.CRSH_NEXTPROCESS='IS' AND A.CRSH_STARTDATE BETWEEN :date_st AND :date_end ORDER BY A.CRSH_STARTDATE DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        } 

                                    } else {

                                        if($pno != ''){
                                            $sql = "SELECT A.* FROM CRSHPROD1 AS A WHERE A.PRODUCT_NO LIKE :pno ORDER BY A.CRSH_STARTDATE DESC";
                                            $params[':pno'] = '%'.$pno.'%';
                                        }else{
                                            $sql = "SELECT A.* FROM CRSHPROD1 AS A WHERE A.CRSH_STATUS='OP' AND A.CRSH_NEXTPROCESS='IS' AND A.CRSH_STARTDATE BETWEEN :date_st AND :date_end ORDER BY A.CRSH_STARTDATE DESC";
                                            $params[':date_st'] = $d." 00:00:00";
                                            $params[':date_end'] = $d." 23:59:59";    
                                        } 
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_prod_no = htmlspecialchars($row['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                        $status = $row['CRSH_STATUS'] ?? '';
                                        
                                        // จัดรูปแบบวันที่
                                        $display_date = '-';
                                        if (!empty($row['CRSH_STARTDATE'])) {
                                            $display_date = date('Y-m-d', strtotime($row['CRSH_STARTDATE']));
                                        }

                                        // เช็กสถานะที่อนุญาต (AC, RJ, OP, RM)
                                        $allowed_status = ['AC', 'RJ', 'OP', 'RM'];
                                        $is_allowed = in_array($status, $allowed_status);

                                        // กำหนด Attribute disabled และ Style สีเทาเมื่อไม่อนุญาต
                                        $disabled_attr = !$is_allowed ? 'disabled' : '';
                                        $disabled_style = !$is_allowed ? 'style="background-color: #94a3b8; color: #ffffff; cursor: not-allowed; opacity: 0.7; border: none;"' : '';

                                        echo "<tr onclick=\"show_detail_sheet('" . addslashes($row['PRODUCT_NO']) . "')\">";
                                        echo "<td align='center'><span class='btn-badge-clickable'>".$safe_prod_no."</span></td>";
                                        echo "<td align='center' style='font-size:14px; color:#64748b; font-weight:600;'>".$display_date."</td>";
                                        echo "<td>".htmlspecialchars(($row['ALLOY'] ?? '').' / '.($row['TEMPER'] ?? ''), ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td>".number_format((float)($row['THICKNESS'] ?? 0), 2)."</td>";
                                        echo "<td>".number_format((float)($row['WIDTH'] ?? 0), 2)." x ".number_format((float)($row['LENGTH'] ?? 0), 2)."</td>";
                                        echo "<td style='font-weight:600;'>".number_format((float)($row['CRSH_ACTUALPIECE'] ?? 0))."</td>";
                                        echo "<td style='font-weight:600; color:#0f172a;'>".number_format((float)($row['CRSH_ACTUALWEIGHT'] ?? 0), 2)."</td>";
                                        echo "<td>".htmlspecialchars($status, ENT_QUOTES, 'UTF-8')."</td>";

                                        // แทรก $disabled_attr และ $disabled_style ลงในปุ่ม
                                        echo "<td align='center'><button class='btn-action-input' ".$disabled_attr." ".$disabled_style." onclick=\"input_sheet_no(event, '" . addslashes($row['PRODUCT_NO']) . "')\">Enter Information</button></td>";
                                        
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

        <input type="hidden" id="func" name="func" value="<?php echo $folder_func;?>"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">
var select_date = '<?php echo $d;?>';
if (document.getElementById('pick_date')) {
    document.getElementById('pick_date').value = select_date;
}

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order: [[0, 'desc']],
    buttons: []
});

function search_product_no(){   
    var data_fun = document.getElementById("func").value;
    var data_pno = document.getElementById("product_no").value; 
    window.location.assign('sheet_production_inspec_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(data_pno)); 
}    

function input_sheet_no(e, pno){
    if (e && e.stopPropagation) {
        e.stopPropagation();
    }
    var data_fun = document.getElementById("func").value;
    window.location.assign('sheet_production_inspec_update_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(pno));         
} 

function show_detail_sheet(pno) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('sheet_production_inspec_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&pno=' + encodeURIComponent(pno));
}
</script>
</html>