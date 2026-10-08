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
        
        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        /* Filter Section Layout */
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

        /* Form Styling Control */
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

        /* ช่องป้อน ค้นหาแบบไร้ขอบสี่เหลี่ยม */
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

        /* Buttons Styling */
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

        /* Modern Table Styles */
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
            cursor: default;
        }

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
            box-shadow: none;
        }

        /* ปุ่ม Print สไตล์สีเขียวตามรูปภาพ */
        .btn-print-custom {
            background-color: #2eab87;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(46, 171, 135, 0.2);
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="scrap_product_label_mats.php?func=<?php echo $folder_func ?>">Scrap Production</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="coil_no">Scrap Number / Coil Number</label>
                    <input class="form-control-minimal" name="coil_no" id="coil_no" type="text" placeholder="Enter the code you want to search for..." value="<?php echo htmlspecialchars($cno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋Coil Production Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                            <thead>
                                <tr>
                                    <th style="width: 160px; text-align: center;">SCRAP NO</th>
                                    <th style="width: 160px; text-align: center;">COIL NO</th>
                                    <th>SCRAP DATE</th>
                                    <th>MATERIAL IN</th>
                                    <th>ALLOY</th>
                                    <th>SCRAP WEIGHT</th>
                                    <th>OPERATOR</th>
                                    <th>STATUS</th>
                                    <th style="width: 220px; text-align: center;">ACTION</th>
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

                                    if ($d == date('Y-m-d')) {
                                        if ($cno != '') {
                                            $sql = "SELECT TOP(50) PRODUCT_NO, COIL_NO, SCRP_DATE, MATERIAL_IN, ALLOY, SCRP_WEIGHT, SCRP_OPERATOR, SCRP_STATUS 
                                                    FROM SCRPPROD1 
                                                    WHERE PRODUCT_NO LIKE :cno OR COIL_NO LIKE :cno 
                                                    ORDER BY SCRP_DATE DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        } else {
                                            $sql = "SELECT TOP(50) PRODUCT_NO, COIL_NO, SCRP_DATE, MATERIAL_IN, ALLOY, SCRP_WEIGHT, SCRP_OPERATOR, SCRP_STATUS 
                                                    FROM SCRPPROD1 
                                                    WHERE SCRP_DATE BETWEEN :date_st AND :date_end 
                                                    ORDER BY SCRP_DATE DESC";
                                            $params[':date_st'] = $date_st;
                                            $params[':date_end'] = $date_end;
                                        }
                                    } else {
                                        if ($cno != '') {
                                            $sql = "SELECT TOP(50) PRODUCT_NO, COIL_NO, SCRP_DATE, MATERIAL_IN, ALLOY, SCRP_WEIGHT, SCRP_OPERATOR, SCRP_STATUS 
                                                    FROM SCRPPROD1 
                                                    WHERE PRODUCT_NO LIKE :cno OR COIL_NO LIKE :cno 
                                                    ORDER BY SCRP_DATE DESC";
                                            $params[':cno'] = '%'.$cno.'%';
                                        } else {
                                            $sql = "SELECT TOP(50) PRODUCT_NO, COIL_NO, SCRP_DATE, MATERIAL_IN, ALLOY, SCRP_WEIGHT, SCRP_OPERATOR, SCRP_STATUS 
                                                    FROM SCRPPROD1 
                                                    WHERE SCRP_DATE BETWEEN :date_f AND :date_e 
                                                    ORDER BY SCRP_DATE DESC";
                                            $params[':date_f'] = $d." 00:00:00";
                                            $params[':date_e'] = $d." 23:59:59";
                                        }
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $safe_prod_no = htmlspecialchars($row['PRODUCT_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $safe_coil_no = htmlspecialchars($row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $scrp_status  = htmlspecialchars($row['SCRP_STATUS'] ?? '', ENT_QUOTES, 'UTF-8');
                                                $scrp_weight  = isset($row['SCRP_WEIGHT']) ? number_format((float)$row['SCRP_WEIGHT'], 2) : '0.00';
                                                
                                                echo "<tr>";
                                                echo "<td><span class='btn-badge-clickable'>".$safe_prod_no."</span></td>";
                                                echo "<td style='font-weight:600; color:#1e293b; font-size:15px; text-align:center;'>".$safe_coil_no."</td>";
                                                echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['SCRP_DATE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td>".htmlspecialchars($row['MATERIAL_IN'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td style='font-weight:600; color:#0f172a; font-size:15px;'>".$scrp_weight."</td>";
                                                echo "<td>".htmlspecialchars($row['SCRP_OPERATOR'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                                echo "<td>".$scrp_status."</td>";
                                                echo "<td style='text-align:center;'>";
                                                echo "<button type='button' class='btn-print-custom' onclick='previewPrintLabel(\"".$safe_prod_no."\", this)'>🖨️ Print Scrap Product Label</button>";
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
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

const PRINT_URL = 'print_scrap_product_label_mats.php'; 

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: false,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[3, 'desc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_cno = document.getElementById("coil_no").value; 
    window.location.assign('scrap_product_label_mats.php?func='+encodeURIComponent(data_fun)+'&cno='+encodeURIComponent(data_cno)); 
}    

function previewPrintLabel(coilNo, btnElement) {
    if (!coilNo) {
        alert('COIL not found.');
        return;
    }
    const targetUrl = `${PRINT_URL}?coilno=${encodeURIComponent(coilNo)}`;
    window.open(targetUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');

    if (btnElement) {
        btnElement.classList.add('printed-active');
        btnElement.style.backgroundColor = '#059669';
        btnElement.style.color = '#ffffff';
    }
}
</script>
</html>