<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า Parameters การค้นหา 6 ตัวหลัก
$s_proid  = isset($_GET['s_proid']) ? trim($_GET['s_proid']) : '';
$s_pkgtr  = isset($_GET['s_pkgtr']) ? trim($_GET['s_pkgtr']) : '';
$s_wf     = isset($_GET['s_wf'])    ? trim($_GET['s_wf'])    : '';
$s_wt     = isset($_GET['s_wt'])    ? trim($_GET['s_wt'])    : '';
$s_lf     = isset($_GET['s_lf'])    ? trim($_GET['s_lf'])    : '';
$s_lt     = isset($_GET['s_lt'])    ? trim($_GET['s_lt'])    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }

        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label { font-size: 12px; font-weight: 700; color: #64748b; margin: 0; text-transform: uppercase; }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important;
            background-color: transparent !important; padding: 4px 6px !important; height: 38px; font-size: 14px; font-weight: 600; color: #1e293b;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 14px; height: 38px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; }
        
        .btn-create-alloy { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 20px; border-radius: 8px; border: none; }
        .btn-create-alloy:hover { background-color: #1d4ed8; color: #ffffff; }

        .table-responsive { border: none !important; margin-top: 15px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 13px; padding: 14px 10px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        #user_table tbody td { padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        #user_table tbody tr:hover td { background-color: #f8fafc; cursor: pointer; }

        .btn-action-del { background-color: #ffeded; color: #b91c1c; font-weight: 600; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; width: 100%; transition: all 0.2s; }
        .btn-action-del:hover { background-color: #fee2e2; color: #991b1b; }
        
        /* สไตล์ปุ่ม Delete เมื่อถูก Disable */
        .btn-action-del:disabled, .btn-action-del[disabled] {
            background-color: #f1f5f9 !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .btn-action-edit { background-color: #f0fdf4; color: #15803d; font-weight: 600; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid #bbf7d0; width: 100%; transition: all 0.2s; }
        .btn-action-edit:hover { background-color: #dcfce7; color: #166534; }

        .tab-menu-wrapper { display: flex; flex-wrap: wrap; gap: 8px; background: #ffffff; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .btn-tab-item { padding: 10px 20px; font-size: 14px; font-weight: 500; color: #64748b; border-radius: 8px; border: none; background: transparent; }
        .btn-tab-item.active { background-color: #2563eb; color: #ffffff; font-weight: 600; }
    </style>
</head>
<body>

<?php $menu = 'gp4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="group_data4_mats.php?func=<?php echo $folder_func ?>">Master Data Management</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <div class="tab-menu-wrapper">
                    <button onclick="work_process_no()" class="btn-tab-item">Work Process Master Data</button>  
                    <button onclick="location_no()" class="btn-tab-item">Location Master Data</button>  
                    <button onclick="material_no()" class="btn-tab-item">Material Package Master Data</button>
                    <button onclick="material_priority_no()" class="btn-tab-item active">Material Package Use Priority Master Data</button>  
                    <button onclick="scrap_no()" class="btn-tab-item">SCRAP Master Data</button> 
                </div>

                <!-- ตัวกรองค้นหา 6 คอลัมน์หลัก -->
                <div class="filter-wrapper">
                    <div class="filter-item" style="width: 120px;">
                        <label>PRODUCT ID</label>
                        <input class="form-control-minimal" id="s_proid" type="text" value="<?php echo htmlspecialchars($s_proid, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 150px;">
                        <label>PACKAGE TREATMENT</label>
                        <input class="form-control-minimal" id="s_pkgtr" type="text" value="<?php echo htmlspecialchars($s_pkgtr, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 110px;">
                        <label>WIDTH FROM</label>
                        <input class="form-control-minimal" id="s_wf" type="text" value="<?php echo htmlspecialchars($s_wf, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 110px;">
                        <label>WIDTH TO</label>
                        <input class="form-control-minimal" id="s_wt" type="text" value="<?php echo htmlspecialchars($s_wt, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 110px;">
                        <label>LENGTH FROM</label>
                        <input class="form-control-minimal" id="s_lf" type="text" value="<?php echo htmlspecialchars($s_lf, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 110px;">
                        <label>LENGTH TO</label>
                        <input class="form-control-minimal" id="s_lt" type="text" value="<?php echo htmlspecialchars($s_lt, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 120px;">
                        <button class="btn btn-search" onclick="search_package_priority()">SEARCH</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Material Package Use Priority Master Data</h4>
                                <button class="btn btn-create-alloy" onclick="window.location.assign('material_package_priority_new_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">
                                    New Material Package (CREATE +)
                                </button>
                            </div>

                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th>PRODUCT ID</th>
                                            <th>PACKAGE TREATMENT</th>
                                            <th>WIDTH FROM</th>
                                            <th>WIDTH TO</th>
                                            <th>LENGTH FROM</th>
                                            <th>LENGTH TO</th>
                                            <th style="width: 80px;">Edit</th>
                                            <th style="width: 80px;">Process</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");
                                        ini_set('max_execution_time', 300);

                                        $where = [];
                                        $params = [];

                                        if ($s_proid != '') { $where[] = "LTRIM(RTRIM(PRODUCT_ID)) LIKE :s_proid"; $params[':s_proid'] = '%'.$s_proid.'%'; }
                                        if ($s_pkgtr != '') { $where[] = "LTRIM(RTRIM(PACKAGE_TREATMENT)) LIKE :s_pkgtr"; $params[':s_pkgtr'] = '%'.$s_pkgtr.'%'; }
                                        if ($s_wf != '')    { $where[] = "LTRIM(RTRIM(WIDTH_FROM)) LIKE :s_wf"; $params[':s_wf'] = '%'.$s_wf.'%'; }
                                        if ($s_wt != '')    { $where[] = "LTRIM(RTRIM(WIDTH_TO)) LIKE :s_wt"; $params[':s_wt'] = '%'.$s_wt.'%'; }
                                        if ($s_lf != '')    { $where[] = "LTRIM(RTRIM(LENGTH_FROM)) LIKE :s_lf"; $params[':s_lf'] = '%'.$s_lf.'%'; }
                                        if ($s_lt != '')    { $where[] = "LTRIM(RTRIM(LENGTH_TO)) LIKE :s_lt"; $params[':s_lt'] = '%'.$s_lt.'%'; }

                                        $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

                                        // ดึงข้อมูลกลุ่มคีย์หลัก 6 ตัว พร้อมนับจำนวน Record (TOTAL_REC) ของคีย์ชุดนั้น
                                        $sql = "SELECT PRODUCT_ID, PACKAGE_TREATMENT, WIDTH_FROM, WIDTH_TO, LENGTH_FROM, LENGTH_TO,
                                                       COUNT(*) AS TOTAL_REC
                                                FROM MTRLSPCT2 
                                                $whereSql 
                                                GROUP BY PRODUCT_ID, PACKAGE_TREATMENT, WIDTH_FROM, WIDTH_TO, LENGTH_FROM, LENGTH_TO
                                                ORDER BY PRODUCT_ID ASC, PACKAGE_TREATMENT ASC";
                                                
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $refdata = trim($row['PRODUCT_ID'])."*".trim($row['PACKAGE_TREATMENT'])."*".trim($row['WIDTH_FROM'])."*".trim($row['WIDTH_TO'])."*".trim($row['LENGTH_FROM'])."*".trim($row['LENGTH_TO']);
                                            $total_rec = (int)($row['TOTAL_REC'] ?? 0);
                                            ?>
                                            <tr onclick="detail_data('<?php echo addslashes($refdata); ?>')">
                                                <td align="center" style="font-weight: 600; color: #2563eb;"><?php echo htmlspecialchars($row['PRODUCT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center" style="font-weight: 600; color: #2563eb;"><?php echo htmlspecialchars($row['PACKAGE_TREATMENT'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($row['WIDTH_FROM'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($row['WIDTH_TO'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($row['LENGTH_FROM'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars($row['LENGTH_TO'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center">
                                                    <button type="button" class="btn-action-edit" onclick="event.stopPropagation(); edit_data('<?php echo addslashes($refdata); ?>')">Edit</button>
                                                </td>  
                                                <td align="center">
                                                    <!-- ปุ่ม Delete จะ Enable ก็ต่อเมื่อ $total_rec == 1 เท่านั้น ถ้า $total_rec > 1 จะถูก Disabled -->
                                                    <button type="button" class="btn-action-del" 
                                                            <?php if ($total_rec > 1) echo 'disabled title="Cannot be deleted because there are more than one sub-record."'; ?>
                                                            onclick="event.stopPropagation(); delete_data('<?php echo addslashes($refdata); ?>')">
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

    function search_package_priority(){   
        var data_fun = document.getElementById("func").value;
        var spro = document.getElementById("s_proid").value.trim();
        var spkg = document.getElementById("s_pkgtr").value.trim();
        var swf  = document.getElementById("s_wf").value.trim();
        var swt  = document.getElementById("s_wt").value.trim();
        var slf  = document.getElementById("s_lf").value.trim();
        var slt  = document.getElementById("s_lt").value.trim();

        var url = 'material_package_priority_master_mats.php?func=' + encodeURIComponent(data_fun)
                + '&s_proid=' + encodeURIComponent(spro)
                + '&s_pkgtr=' + encodeURIComponent(spkg)
                + '&s_wf='    + encodeURIComponent(swf)
                + '&s_wt='    + encodeURIComponent(swt)
                + '&s_lf='    + encodeURIComponent(slf)
                + '&s_lt='    + encodeURIComponent(slt);
        
        window.location.assign(url); 
    }    

    function work_process_no(){ window.location.assign('work_process_master_mats.php?func='+encodeURIComponent($("#func").val())); }   
    function location_no(){ window.location.assign('location_master_mats.php?func='+encodeURIComponent($("#func").val())); }    
    function scrap_no(){ window.location.assign('scrap_master_mats.php?func='+encodeURIComponent($("#func").val())); }  
    function material_no(){ window.location.assign('material_package_master_mats.php?func='+encodeURIComponent($("#func").val())); } 
    function material_priority_no(){ window.location.assign('material_package_priority_master_mats.php?func='+encodeURIComponent($("#func").val())); }     
        
    function edit_data(refdata){
        window.location.assign('material_package_priority_edit_master_mats.php?func='+encodeURIComponent($("#func").val())+'&refdata='+encodeURIComponent(refdata));         
    } 

    function detail_data(refdata){
        window.location.assign('material_package_priority_detail_master_mats.php?func='+encodeURIComponent($("#func").val())+'&refdata='+encodeURIComponent(refdata));         
    }     

    function delete_data(ref){
        var c = confirm('Do you want to delete this Material Package Priority information ?');
        if(c){
            $.ajax({
                url: "model/del_package_priority_master_mats.php",
                type: "POST",
                data: { data_tag: ref },
                dataType: "json",
                success: function(response) {
                    if(response.message || response.status === 'success'){
                        alert("Data has been successfully deleted.");
                        window.location.assign('material_package_priority_master_mats.php?func=' + encodeURIComponent($("#func").val()));
                    } else {
                        alert("An error occurred: " + (response.error || "The data cannot be deleted."));
                    }
                },
                error: function(xhr, status, error) { alert("An error occurred while deleting the data: " + error); }
            });
        }
    } 
</script>
</html>