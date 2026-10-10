<?php
session_start();

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
        #user_table tbody tr:hover td { background-color: #f8fafc; }

        /* ปุ่ม Edit & Delete Styles */
        .btn-action-edit {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            transition: all 0.2s;
            margin-right: 4px;
        }
        .btn-action-edit:hover {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        .btn-action-del {
            background-color: #ffeded;
            color: #b91c1c;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #fca5a5;
            transition: all 0.2s;
        }
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
            transform: translateY(-1px);
        }

        /* ปรับแต่งปุ่ม Edit ให้ยาวขึ้น */
        .btn-action-edit {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;     /* เพิ่มระยะขอบด้านใน */
            min-width: 80px;       /* กำหนดความกว้างขั้นต่ำ */
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            transition: all 0.2s;
            margin-right: 6px;
            display: inline-block;
        }
        .btn-action-edit:hover {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        /* ปรับแต่งปุ่ม Delete ให้ยาวขึ้น */
        .btn-action-del {
            background-color: #ffeded;
            color: #b91c1c;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;     /* เพิ่มระยะขอบด้านใน */
            min-width: 80px;       /* กำหนดความกว้างขั้นต่ำ */
            border-radius: 6px;
            border: 1px solid #fca5a5;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-action-del:hover {
            background-color: #fee2e2;
            color: #991b1b;
            border-color: #f87171;
            transform: translateY(-1px);
        }        

        .modal-content { border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px; }
        .modal-title { font-weight: 700; color: #1e293b; font-size: 18px; }
        .modal-footer { border-top: 1px solid #f1f5f9; }

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
        .btn-tab-item:hover { background-color: #f1f5f9; color: #334155; }
        .btn-tab-item.active { background-color: #2563eb; color: #ffffff; font-weight: 600; }
    </style>
</head>
<body>

<?php $menu = 'gp4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

<!-- Modal Create / Edit SCRAP Data -->
<div class="modal fade" id="data_composition" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <form id="form_scrap">
        <!-- Hidden input สำหรับเก็บ ID กรณีแก้ไขข้อมูล -->
        <input type="hidden" id="data_id" name="data_id" value="" />
        
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="modal_title">⚙️ SCRAP MASTER DATA</h4>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">PROCESS <span style="color:#ef4444;">*</span></label>
                        <input type="text" class="form-control" style="width:100%; text-transform: uppercase;" id="data_process" name="data_process" placeholder="เช่น CC, HOT ROLL, COLD ROLL" onkeyup="this.value = this.value.toUpperCase()"/>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">VOLUME</label>
                        <input type="number" step="0.01" class="form-control" style="width:100%;" id="data_volum" name="data_volum" placeholder="เช่น 0.00"/>
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">UNIT</label>
                        <select class="form-control" style="width:100%;" id="data_unit" name="data_unit">
                            <option value="">-- Select Unit --</option>
                            <option value="mm">mm</option>
                            <option value="kg">kg</option>
                            <option value="Inch">Inch</option>
                            <option value="PCS">PCS</option>
                            <option value="lbs">lbs</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">COMMENT</label>
                        <textarea class="form-control" style="width:100%; height:auto;" id="data_comment" name="data_comment" rows="3" placeholder="รายละเอียดเพิ่มเติม"></textarea>
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="group_data4_mats.php?func=<?php echo $folder_func ?>">Master Data Management</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <div class="tab-menu-wrapper">
                    <button onclick="work_process_no()" class="btn-tab-item">Work Process Master Data</button>  
                    <button onclick="location_no()" class="btn-tab-item">Location Master Data</button>  
                    <button onclick="material_no()" class="btn-tab-item">Material Package Master Data</button>
                    <button onclick="material_priority_no()" class="btn-tab-item">Material Package Use Priority Master Data</button>  
                    <button onclick="scrap_no()" class="btn-tab-item active">SCRAP Master Data</button> 
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="dashboard-card">
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 SCRAP Master Data</h4>
                                <button class="btn btn-create-alloy" onclick="open_scrap_no()">
                                    New SCRAP (CREATE +)
                                </button>
                            </div>

                            <div class="table-responsive table-full-width">
                                <table id="user_table" class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 80px; text-align: center;">ID</th>
                                            <th style="width: 180px; text-align: center;">PROCESS</th>
                                            <th style="width: 120px; text-align: right;">VOLUM</th>
                                            <th style="width: 100px; text-align: center;">UNIT</th>
                                            <th>COMMENT</th>
                                            <th style="width: 160px; text-align: center;">Action</th>
                                            <th style="width: 160px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include 'function_mats.php';
                                        include("dbcon_mats-new.php");
                                        ini_set('max_execution_time', 300);

                                        $params = [];
                                        $sql = "SELECT ID, PROCESS, VOLUM, UNIT, COMMENT FROM STNDMSTR14 ORDER BY ID ASC";                                                
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $id      = $row['ID'] ?? '';
                                            $process = $row['PROCESS'] ?? '';
                                            $volum   = $row['VOLUM'] ?? 0;
                                            $unit    = $row['UNIT'] ?? '';
                                            $comment = $row['COMMENT'] ?? '';
                                            
                                            $refdata = $id . "*" . $process;
                                            ?>
                                            <tr>
                                                <td align="center" style="color:#64748b;"><?php echo htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td style="text-align: center; font-weight:600; color:#1e293b;"><?php echo htmlspecialchars((string)$process, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="right"><?php echo htmlspecialchars(number_format((float)$volum, 2), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center"><?php echo htmlspecialchars((string)$unit, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars((string)$comment, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td align="center">
                                                    <button type="button" class="btn-action-edit min-width: 80px;" onclick="edit_Data('<?php echo htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo addslashes(htmlspecialchars((string)$process, ENT_QUOTES, 'UTF-8')); ?>', '<?php echo htmlspecialchars((string)$volum, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo addslashes(htmlspecialchars((string)$unit, ENT_QUOTES, 'UTF-8')); ?>', '<?php echo addslashes(htmlspecialchars((string)$comment, ENT_QUOTES, 'UTF-8')); ?>')">
                                                        Edit
                                                    </button>
                                                </td>                                                   
                                                <td align="center">
                                                    <button type="button" class="btn-action-del min-width: 80px;" onclick="delete_Data('<?php echo addslashes($refdata); ?>')">
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

    // เปิด Modal แบบสร้างใหม่
    function open_scrap_no(){
        document.getElementById('modal_title').innerText = '⚙️ CREATE NEW SCRAP MASTER DATA';
        document.getElementById('data_id').value = '';
        document.getElementById('data_process').value = '';
        document.getElementById('data_volum').value = '';
        document.getElementById('data_unit').value = '';
        document.getElementById('data_comment').value = '';
        $("#data_composition").modal('show');
    } 

    // เปิด Modal แบบแก้ไขข้อมูล
    function edit_Data(id, process, volum, unit, comment) {
        document.getElementById('modal_title').innerText = '⚙️ EDIT SCRAP MASTER DATA (ID: ' + id + ')';
        document.getElementById('data_id').value = id;
        document.getElementById('data_process').value = process;
        document.getElementById('data_volum').value = volum;
        document.getElementById('data_unit').value = unit;
        document.getElementById('data_comment').value = comment;
        $("#data_composition").modal('show');
    }

    function work_process_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('work_process_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }   

    function location_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('location_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 
    
    function scrap_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('scrap_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 

    function material_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('material_package_master_mats.php?func='+encodeURIComponent(data_fun)); 
    } 
    
    function material_priority_no(){   
        var data_fun = document.getElementById("func").value;
        window.location.assign('material_package_priority_master_mats.php?func='+encodeURIComponent(data_fun)); 
    }     
    
    function delete_Data(ref){
        var d = ref.split("*");
        var c = confirm('Do you want to delete SCRAP ID: ' + d[0] + ' (Process: ' + d[1] + ')?');
        
        if(c){
            var data_fun = document.getElementById("func").value;
            
            $.ajax({
                url: "model/del_scrap_master_mats.php",
                type: "POST",
                data: {
                    data_tag: ref
                },
                dataType: "json",
                success: function(response) {
                    if(response.message === true || response.status === 'success'){
                        window.location.assign('scrap_master_mats.php?func=' + encodeURIComponent(data_fun));
                    } else {
                        var errorMsg = response.error || "The data cannot be deleted.";
                        alert("An error occurred: " + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    alert("An error occurred while deleting the data: " + error);
                }
            });
        }
    }

    function submitData() {
        var procObj = document.getElementById('data_process');
        procObj.value = procObj.value.trim().toUpperCase();
        
        if (procObj.value === "") {
            alert("Please fill in the Process field.");
            return false;
        }

        var formData = $("#form_scrap").serialize();

        $.post("model/save_scrap_master_mats.php", formData, function(resp) {
            if (resp.message === true || resp.status === 'success') {
                //alert("SCRAP Master information has been successfully saved.");
                $("#data_composition").modal('hide');
                window.location.assign('scrap_master_mats.php?func=' + encodeURIComponent(document.getElementById('func').value));
            } else {
                alert("An error occurred: " + (resp.error || "Please double-check the information"));
            }
        }, "json")
        .fail(function(xhr, status, error) {
            alert("Technical system malfunction (server unresponsive): " + error);
        });
    }   
</script>
</html>