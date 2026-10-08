<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

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

        .btn-create-alloy { background-color: #2563eb; color: #ffffff; font-weight: 600; font-size: 14px; padding: 10px 20px; border-radius: 8px; border: none; }
        .btn-create-alloy:hover { background-color: #1d4ed8; color: #ffffff; }

        .table-responsive { border: none !important; margin-top: 15px; }
        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 13px; padding: 14px 10px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        #user_table tbody td { padding: 12px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; text-align: center; }
        #user_table tbody tr:hover td { background-color: #f8fafc; cursor: pointer; }

        .btn-action-del { background-color: #ffeded; color: #b91c1c; font-weight: 600; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; width: 100%; transition: all 0.2s; }
        .btn-action-del:hover { background-color: #fee2e2; color: #991b1b; }
        
        .btn-action-edit { background-color: #f0fdf4; color: #15803d; font-weight: 600; font-size: 13px; padding: 6px 12px; border-radius: 6px; border: 1px solid #bbf7d0; width: 100%; transition: all 0.2s; }
        .btn-action-edit:hover { background-color: #dcfce7; color: #166534; }

        .btn-action-del:disabled, .btn-action-del[disabled],
        .btn-action-edit:disabled, .btn-action-edit[disabled] {
            background-color: #f1f5f9 !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        /* Badge สำหรับ Status ให้ขนาดเท่าปุ่ม Edit/Delete */
        .badge-status-op {
            background-color: #f0fdf4;
            color: #15803d;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #bbf7d0;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        .badge-status-cl {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

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
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">Working Roll Set Data Management</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <div class="tab-menu-wrapper">
                    <button onclick="homo_no()" class="btn-tab-item">Homogenize Process</button>  
                    <button onclick="recipe_no()" class="btn-tab-item">Recipe Master</button> 
                    <button onclick="working_roll_no()" class="btn-tab-item active">Work Set Cold Mill</button>  
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th align="center">Roll No</th>
                                            <th align="center">Roll Ref No</th>
                                            <th align="center">WRL RADIUS</th>
                                            <th align="center">BRL RADIUS</th>
                                            <th align="center">TWR CAMBER</th>
                                            <th align="center">BWR CAMBER</th>
                                            <th align="center" style="width: 80px;">RSET STATUS</th>
                                            <th align="center" style="width: 80px;">Edit</th>
                                            <th align="center" style="width: 80px;">Process</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");
                                        ini_set('max_execution_time', 300);

                                        $roll_sql = "SELECT TOP(100) r.RSET_NO, r.RSET_REFERENCE, r.WRL_RADIUS, r.BRL_RADIUS, r.TWR_CAMBER, r.BWR_CAMBER, r.RSET_STATUS 
                                                     FROM RDMTMSTR3 as r
                                                     ORDER BY CASE WHEN r.RSET_STATUS = 'OP' THEN 0 ELSE 1 END, 
                                                              r.RSET_NO DESC";

                                        $stmt = $conn->prepare($roll_sql);
                                        $stmt->execute();

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $roll_no = trim($row['RSET_NO']);
                                            $status  = trim($row['RSET_STATUS'] ?? '');
                                            $is_op   = ($status === 'OP');
                                            ?>
                                            <tr onclick="detail_data('<?php echo addslashes($row['RSET_NO']); ?>')">
                                                <td align="center" style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($row['RSET_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center" style="font-weight:600; color:#2563eb;"><?php echo htmlspecialchars($row['RSET_REFERENCE'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo is_numeric($row['WRL_RADIUS']) ? number_format((float)$row['WRL_RADIUS'], 2) : '0.00'; ?></td>
                                                <td align="center"><?php echo is_numeric($row['BRL_RADIUS']) ? number_format((float)$row['BRL_RADIUS'], 2) : '0.00'; ?></td>
                                                <td align="center"><?php echo is_numeric($row['TWR_CAMBER']) ? number_format((float)$row['TWR_CAMBER'], 2) : '0.00'; ?></td>
                                                <td align="center"><?php echo is_numeric($row['BWR_CAMBER']) ? number_format((float)$row['BWR_CAMBER'], 2) : '0.00'; ?></td>
                                                <td align="center">
                                                    <span class="<?php echo $is_op ? 'badge-status-op' : 'badge-status-cl'; ?>">
                                                        <?php echo htmlspecialchars($status !== '' ? $status : '0', ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </td>                                                
                                                <td align="center">
                                                    <button type="button" class="btn-action-edit" <?php echo !$is_op ? 'disabled title="Only items with OP status can be edited"' : ''; ?> onclick="event.stopPropagation(); edit_data('<?php echo addslashes($roll_no); ?>')">Edit</button>
                                                </td>  
                                                <td align="center">
                                                    <button type="button" class="btn-action-del" <?php echo $is_op ? 'disabled title="Only items with OP status can be deleted"' : ''; ?> onclick="event.stopPropagation(); delete_data('<?php echo addslashes($roll_no); ?>')">Delete</button>
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
        ordering: false,
        buttons: []
    });

    function homo_no(){ window.location.assign('homogennize_master_mats.php?func='+encodeURIComponent($("#func").val())); }   
    function recipe_no(){ window.location.assign('cold_rolling_recipe_master_mats.php?func='+encodeURIComponent($("#func").val())); }    
    function working_roll_no(){ window.location.assign('working_roll_master_mats.php?func='+encodeURIComponent($("#func").val())); } 
        
    function edit_data(recipe_no){
        window.location.assign('working_roll_edit_master_mats.php?func='+encodeURIComponent($("#func").val())+'&recipe_no='+encodeURIComponent(recipe_no));         
    } 

    function detail_data(recipe_no){
        window.location.assign('working_roll_detail_master_mats.php?func='+encodeURIComponent($("#func").val())+'&recipe_no='+encodeURIComponent(recipe_no));         
    }     

    function delete_data(recipe_no){
        var c = confirm('You want to delete the Cold Rolling Recipe data: ' + recipe_no + ' Yes or No ?');
        if(c){
            $.ajax({
                url: "model/del_roll_master_mats.php",
                type: "POST",
                data: { recipe_no: recipe_no },
                dataType: "json",
                success: function(response) {
                    if(response.message || response.status === 'success'){
                        alert("Data has been successfully deleted.");
                        window.location.assign('working_roll_master_mats.php?func=' + encodeURIComponent($("#func").val()));
                    } else {
                        alert("An error occurred: " + (response.error || "Failed to delete data"));
                    }
                },
                error: function(xhr, status, error) { alert("An error occurred while deleting data: " + error); }
            });
        }
    } 
</script>
</html>