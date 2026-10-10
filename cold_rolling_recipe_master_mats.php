<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่า Parameters การค้นหา 4 ตัวหลักสำหรับ Cold Rolling Recipe
$s_al    = isset($_GET['s_al'])    ? trim($_GET['s_al'])    : '';
$s_wi    = isset($_GET['s_wi'])    ? trim($_GET['s_wi'])    : '';
$s_th_or = isset($_GET['s_th_or']) ? trim($_GET['s_th_or']) : '';
$s_th_fn = isset($_GET['s_th_fn']) ? trim($_GET['s_th_fn']) : '';
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

<?php $menu = 'gp3';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="group_data3_mats.php?func=<?php echo $folder_func ?>">Master Data Management</a>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <div class="tab-menu-wrapper">
                    <button onclick="homo_no()" class="btn-tab-item">Homogenize Process</button>  
                    <button onclick="recipe_no()" class="btn-tab-item active">Cold Rolling Mill Recipe Master Data</button> 
                    <button onclick="working_roll_no()" class="btn-tab-item">Working Roll set at Cold Mill Master Data</button>  
                </div>

                <!-- ตัวกรองค้นหา 4 คอลัมน์หลัก -->
                <div class="filter-wrapper">
                    <div class="filter-item" style="width: 140px;">
                        <label>ALLOY</label>
                        <input class="form-control-minimal" id="s_al" type="text" value="<?php echo htmlspecialchars($s_al, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 140px;">
                        <label>WIDTH</label>
                        <input class="form-control-minimal" id="s_wi" type="text" value="<?php echo htmlspecialchars($s_wi, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 160px;">
                        <label>THICKNESS ORIGINAL</label>
                        <input class="form-control-minimal" id="s_th_or" type="text" value="<?php echo htmlspecialchars($s_th_or, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 160px;">
                        <label>THICKNESS FINAL</label>
                        <input class="form-control-minimal" id="s_th_fn" type="text" value="<?php echo htmlspecialchars($s_th_fn, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="filter-item" style="width: 120px;">
                        <button class="btn btn-search" onclick="search_package_priority()">SEARCH</button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Cold Rolling Recipe Master Data</h4>
                                <button class="btn btn-create-alloy" onclick="window.location.assign('cold_rolling_new_recipe_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">
                                    New Recipe (CREATE +)
                                </button>
                            </div>

                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th>RECIPE NO</th>
                                            <th>ALLOY</th>
                                            <th>WIDTH</th>
                                            <th>THICKNESS ORIGINAL</th>
                                            <th>THICKNESS FINAL</th>
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

                                        if ($s_al != '')    { $where[] = "LTRIM(RTRIM(ALLOY)) LIKE :s_al"; $params[':s_al'] = '%'.$s_al.'%'; }
                                        if ($s_wi != '')    { $where[] = "LTRIM(RTRIM(WIDTH)) LIKE :s_wi"; $params[':s_wi'] = '%'.$s_wi.'%'; }
                                        if ($s_th_or != '') { $where[] = "LTRIM(RTRIM(THICKNESS_ORIGINAL)) LIKE :s_th_or"; $params[':s_th_or'] = '%'.$s_th_or.'%'; }
                                        if ($s_th_fn != '') { $where[] = "LTRIM(RTRIM(THICKNESS_FINAL)) LIKE :s_th_fn"; $params[':s_th_fn'] = '%'.$s_th_fn.'%'; }

                                        $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

                                        // ดึงกลุ่มข้อมูลคีย์หลักพร้อม RECIPE_NO และแปลงแสดงทศนิยม 2 ตำแหน่ง
                                        $sql = "SELECT TOP(500) RECIPE_NO, ALLOY, WIDTH, THICKNESS_ORIGINAL, THICKNESS_FINAL,
                                                       COUNT(*) AS TOTAL_REC
                                                FROM CMRCMSTR1 
                                                $whereSql 
                                                GROUP BY RECIPE_NO, ALLOY, WIDTH, THICKNESS_ORIGINAL, THICKNESS_FINAL
                                                ORDER BY ALLOY ASC, WIDTH ASC";
                                                
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $recipe_no = trim($row['RECIPE_NO']);
                                            $refdata   = urlencode($recipe_no);
                                            $total_rec = (int)($row['TOTAL_REC'] ?? 0);

                                            // จัดรูปแบบทศนิยม 2 ตำแหน่งสำหรับแสดงผล
                                            $disp_width = is_numeric($row['WIDTH']) ? number_format((float)$row['WIDTH'], 2) : $row['WIDTH'];
                                            $disp_th_or = is_numeric($row['THICKNESS_ORIGINAL']) ? number_format((float)$row['THICKNESS_ORIGINAL'], 2) : $row['THICKNESS_ORIGINAL'];
                                            $disp_th_fn = is_numeric($row['THICKNESS_FINAL']) ? number_format((float)$row['THICKNESS_FINAL'], 2) : $row['THICKNESS_FINAL'];
                                            ?>
                                            <tr onclick="detail_data('<?php echo addslashes($recipe_no); ?>')">
                                                <td  style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($recipe_no, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td  style="font-weight:600; color:#2563eb;"><?php echo htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td ><?php echo htmlspecialchars($disp_width, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td ><?php echo htmlspecialchars($disp_th_or, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td ><?php echo htmlspecialchars($disp_th_fn, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td >
                                                    <button type="button" class="btn-action-edit" onclick="event.stopPropagation(); edit_data('<?php echo addslashes($recipe_no); ?>')">Edit</button>
                                                </td>  
                                                <td >
                                                    <button type="button" class="btn-action-del" 
                                                            <?php if ($total_rec > 1) echo 'disabled title="Cannot be deleted because there are more than one sub-record"'; ?>
                                                            onclick="event.stopPropagation(); delete_data('<?php echo addslashes($recipe_no); ?>')">
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
        var sal  = document.getElementById("s_al").value.trim();
        var swi  = document.getElementById("s_wi").value.trim();
        var sthor = document.getElementById("s_th_or").value.trim();
        var sthfn = document.getElementById("s_th_fn").value.trim();

        var url = 'cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent(data_fun)
                + '&s_al='    + encodeURIComponent(sal)
                + '&s_wi='    + encodeURIComponent(swi)
                + '&s_th_or=' + encodeURIComponent(sthor)
                + '&s_th_fn=' + encodeURIComponent(sthfn);
        
        window.location.assign(url); 
    }    

    function homo_no(){ window.location.assign('homogennize_master_mats.php?func='+encodeURIComponent($("#func").val())); }   
    function recipe_no(){ window.location.assign('cold_rolling_recipe_master_mats.php?func='+encodeURIComponent($("#func").val())); }    
    function working_roll_no(){ window.location.assign('working_roll_master_mats.php?func='+encodeURIComponent($("#func").val())); } 
        
    function edit_data(recipe_no){
        window.location.assign('cold_rolling_edit_recipe_master_mats.php?func='+encodeURIComponent($("#func").val())+'&recipe_no='+encodeURIComponent(recipe_no));         
    } 

    function detail_data(recipe_no){
        window.location.assign('cold_rolling_detail_recipe_master_mats.php?func='+encodeURIComponent($("#func").val())+'&recipe_no='+encodeURIComponent(recipe_no));         
    }     

    function delete_data(recipe_no){
        var c = confirm('Do you want to delete the data Cold Rolling Recipe: ' + recipe_no + ' Yes or No ?');
        if(c){
            $.ajax({
                url: "model/del_recipe_master_mats.php",
                type: "POST",
                data: { recipe_no: recipe_no },
                dataType: "json",
                success: function(response) {
                    if(response.message || response.status === 'success'){
                        alert("Data has been successfully deleted.");
                        window.location.assign('cold_rolling_recipe_master_mats.php?func=' + encodeURIComponent($("#func").val()));
                    } else {
                        alert("An error occurred.: " + (response.error || "The data cannot be deleted."));
                    }
                },
                error: function(xhr, status, error) { alert("An error occurred while deleting the data: " + error); }
            });
        }
    } 
</script>
</html>