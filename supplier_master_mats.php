<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

// รับค่าคำค้นหาจาก GET Parameter
$kw = !isset($_GET['kw']) ? '' : trim($_GET['kw']);

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
        .btn-create-alloy {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            transition: all 0.2s;
        }
        .btn-create-alloy:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Modern Table Styles & Font Scaling Up */
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
        #user_table tbody tr:hover td {
            background-color: #f8fafc;
            cursor: pointer;
        }

        /* Action Buttons */
        .btn-action-del {
            background-color: #ffeded;
            color: #b91c1c;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #fca5a5;
            width: 100%;
            max-width: 120px;
            transition: all 0.2s;
        }
        .btn-action-edit {
            background-color: #f7ffed;
            color: #2eb91c;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #d6fca5;
            width: 100%;
            max-width: 120px;
            transition: all 0.2s;
        }
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
            transform: translateY(-1px);
        }

        /* Navigation Tab */
        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php $menu = 'gp1';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Master Data Management</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <div class="tab-menu-wrapper">
                    <button onclick="port_no()" class="btn-tab-item">Post Master</button>  
                    <button onclick="place_no()" class="btn-tab-item">Place Master</button>  
                    <button onclick="freight_no()" class="btn-tab-item">Freight Master</button>        
                    <button onclick="uom_no()" class="btn-tab-item">Unit Measure Master</button>   
                    <button onclick="lme_no()" class="btn-tab-item">LME Price Master</button> 
                    <button onclick="supplier_no()" class="btn-tab-item active">Supplier Master</button> 
                </div>

                <!--ส่วนค้นหาแบบกำหนดเอง-->
                <div class="filter-wrapper">
                    <div class="filter-item" style="flex: 1; min-width: 280px;">
                        <label for="search_keyword">Search Supplier Info</label>
                        <input type="text" class="form-control" id="search_keyword" placeholder="Search by Supplier Code, Account Code, Company Name (TH/EN)..." value="<?php echo htmlspecialchars($kw, ENT_QUOTES, 'UTF-8'); ?>" onkeypress="if(event.keyCode==13) search_supplier();" />
                    </div>
                    <div class="filter-item">
                        <button type="button" class="btn btn-search" onclick="search_supplier()">SEARCH</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Supplier Master Data (Top 100 Records)</h4>
                                <button class="btn btn-create-alloy" onclick="window.location.assign('supplier_new_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">
                                    New Supplier (CREATE +)
                                </button>
                            </div>

                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px; text-align: center;">ID</th>
                                            <th style="width: 180px; text-align: center;">CSTMSPPL_ID</th>
                                            <th style="width: 180px; text-align: center;">ACCOUNT_CODE</th>
                                            <th style="width: 200px;">TAX REGISTRATION</th>
                                            <th style="width: 220px;">COMPANY(THAI)</th>
                                            <th style="width: 220px;">COMPANY(ENG)</th>
                                            <th style="width: 220px;">CONSIGNEE_ADDRESS</th>
                                            <th style="width: 120px; text-align: center;">Edit</th>
                                            <th style="width: 120px; text-align: center;">Process</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");
                                        ini_set('max_execution_time', 300);

                                        $params = [];
                                        
                                        // ปรับ SQL ดึงเฉพาะ TOP 100 รายการเรียงตาม CSTMSPPL_ID ASC
                                        if ($kw != '') {
                                            $sql = "SELECT TOP 100 CSTMSPPL_ID, ACCOUNT_CODE, TAX_REGISTRATION, EN_COMPANY, EN_ADDRESS1, TH_COMPANY 
                                                    FROM CSSPMSTR22 
                                                    WHERE CSTMSPPL_ID LIKE :kw 
                                                       OR ACCOUNT_CODE LIKE :kw 
                                                       OR TH_COMPANY LIKE :kw 
                                                       OR EN_COMPANY LIKE :kw 
                                                    ORDER BY CSTMSPPL_ID ASC";
                                            $params[':kw'] = '%'.$kw.'%';
                                        } else {
                                            $sql = "SELECT TOP 100 CSTMSPPL_ID, ACCOUNT_CODE, TAX_REGISTRATION, EN_COMPANY, EN_ADDRESS1, TH_COMPANY 
                                                    FROM CSSPMSTR22 
                                                    ORDER BY CSTMSPPL_ID ASC";
                                        }

                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        $i = 0;
                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $i++;
                                            $refdata = $row['CSTMSPPL_ID']."*".$row['ACCOUNT_CODE'];
                                            ?>
                                            <tr onclick="cust_suppl('<?php echo addslashes($row['CSTMSPPL_ID']); ?>')">
                                                <td align="center" style="color:#64748b;"><?php echo number_format($i, 0); ?></td>
                                                
                                                <td style="font-weight: 600; color: #2563eb; text-align: center;"><?php echo htmlspecialchars($row['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                
                                                <td style="font-weight: 600; color: #2563eb; text-align: center;"><?php echo htmlspecialchars($row['ACCOUNT_CODE'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($row['TAX_REGISTRATION'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($row['TH_COMPANY'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($row['EN_COMPANY'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($row['EN_ADDRESS1'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                
                                                <td align="center">
                                                    <button type="button" class="btn-action-edit" onclick="event.stopPropagation(); edit_cust_suppl('<?php echo addslashes($row['CSTMSPPL_ID']); ?>')">
                                                        Edit
                                                    </button>
                                                </td>  

                                                <td align="center">
                                                    <button type="button" class="btn-action-del" onclick="event.stopPropagation(); delete_Data('<?php echo addslashes($refdata ?? ''); ?>')">
                                                        Delete
                                                    </button>
                                                </td>                                                                 
                                            </tr>
                                            <?php                                                         
                                        }
                                    ?>                                              
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>

        <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
        <?php include 'include/content-footer.php';?>
    </div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       

    $("#user_table").DataTable({
        scrollY: "500px",
        scrollX: false,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 10,
        order:[[0, 'asc']],
        buttons: []
    });

    function search_supplier(){
        var data_fun = document.getElementById("func").value;
        var kw = document.getElementById("search_keyword").value;
        window.location.assign('supplier_master_mats.php?func=' + encodeURIComponent(data_fun) + '&kw=' + encodeURIComponent(kw));
    }

    function port_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('port_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }   

    function place_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('place_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    
    
    function freight_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('freight_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }     

    function uom_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('unit_measure_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }     
    
    function lme_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('lme_price_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    
    
    function supplier_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('supplier_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function edit_cust_suppl(ci_no){
        var data_fun = document.getElementById("func").value;
        window.location.assign('supplier_edit_master_mats.php?func='+encodeURIComponent(data_fun)+'&CID='+encodeURIComponent(ci_no));         
    } 

    function cust_suppl(ci_no){
        var data_fun = document.getElementById("func").value;
        window.location.assign('supplier_detail_master_mats.php?func='+encodeURIComponent(data_fun)+'&CID='+encodeURIComponent(ci_no));         
    }     

    function delete_Data(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete the data  Supplier ID: ' + d[0] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_sup_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('supplier_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "Failed to delete data";
                        alert("An error occurred: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert("An error occurred while deleting data: " + error);
                }
            });
        }
    } 
</script>
</html>