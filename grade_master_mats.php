<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

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
        }

        /* ปุ่มลบข้อมูลสไตล์นุ่มนวลลดความกระด้าง */
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
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
            transform: translateY(-1px);
        }

        /* Modal Design Customization */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-header {
            border-bottom: 1px solid #f1f5f9;
            padding: 20px;
        }
        .modal-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 18px;
        }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
        }

        /* เมนูแท็บด้านบนสำหรับเลือกประเภทมาสเตอร์การผลิต */
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

<?php $menu = 'gp2';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="modal fade" id="data_composition" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <form id="form_grade">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="exampleModalLabel">⚙️ GRADE MASTER DATA</h4>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <div style="margin-bottom: 16px;">
                            <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">GRADE <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="form-control" style="width:100%; text-transform: uppercase;" id="data_grade" name="data_grade" placeholder="เช่น A, B, C" onkeyup="this.value = this.value.toUpperCase()"/>
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">DESCRIPTION</label>
                            <textarea class="form-control" style="width:100%; height:auto;" id="data_desc" name="data_desc" rows="3" placeholder="รายละเอียดของ Grade"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:6px; font-size:14px;">Close</button>
                        <button type="button" class="btn-create-alloy" onclick="submitData()">
                            💾 Save Data
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

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
                    <button onclick="open_composition()" class="btn-tab-item">COMPOSITION</button>
                    <button onclick="open_temper_no()" class="btn-tab-item">TEMPER</button>
                    <button onclick="open_grade_no()" class="btn-tab-item active">GRADE</button>
                    <button onclick="open_surface_no()" class="btn-tab-item">SURFACE</button>
                    <button onclick="open_defect_no()" class="btn-tab-item">DEFECT</button>                    
                    <button onclick="open_mg_grade_no()" class="btn-tab-item">MG Grade</button>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Grade Master Data Records</h4>
                                <button class="btn btn-create-alloy" onclick="open_alloy_no()">
                                    New GRADE (CREATE +)
                                </button>
                            </div>

                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px; text-align: center;">ID</th>
                                            <th style="width: 220px; text-align: center;">GRADE</th>
                                            <th>DESCRIPTION</th>
                                            <th style="width: 150px; text-align: center;">Process</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");
                                        ini_set('max_execution_time', 300);

                                        $params = [];
                                        $i = 0;
                                        
                                        $sql = "SELECT GRADE,DESCRIPTION FROM GRDEMSTR1 ORDER BY GRADE ASC";                                               
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $i++;
                                            $refdata = $row['GRADE']."*".$row['DESCRIPTION'];
                                            ?>
                                            <tr>
                                                <td align="center" style="color:#64748b;"><?php echo number_format($i,0); ?></td>
                                                <td style='text-align: center;' style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($row['GRADE'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($row['DESCRIPTION'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center">
                                                    <button type="button" class="btn-action-del" onclick="delete_Data('<?php echo addslashes($refdata); ?>')">
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

    function open_alloy_no(){
        $("#data_composition").modal('show');
    } 

    function open_composition(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('composition_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }  

    function open_temper_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('temper_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    

    function open_grade_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('grade_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_surface_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('surface_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_defect_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('defect_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function open_mg_grade_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('mg_grade_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }    

    function delete_Data(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete the data Grade: ' + d[0] + ' Yes or No ?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_grade_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    console.log("Response:", response);
                    if(response.message){
                        window.location.assign('grade_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "The data cannot be deleted.";
                        alert("An error occurred: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert("An error occurred while deleting the data: " + error);
                }
            });
        }
    }

    function submitData() {
        var temperObj = document.getElementById('data_grade');
        temperObj.value = temperObj.value.trim().toUpperCase();
        
        var temper = temperObj.value;
        var data_fun = document.getElementById('func').value;

        if (temper === "") {
            alert("Please fill in all the grade information completely.");
            return;
        }

        var formData = $("#form_grade").serialize();

        $.post("model/save_grade_master_mats.php", formData, function(resp) {
            if (resp.message === true) {
                alert("The master specification data has been successfully recorded.");
                $("#data_composition").modal('hide');
                window.location.assign('grade_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Please double-check the information."));
            }
        }, "json")
        .fail(function(xhr, status, error) {
            alert("Technical system malfunction (server unresponsive): " + error);
        });
    }   
</script>
</html>