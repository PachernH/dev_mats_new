<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// รับค่า rno และกำหนดตัวแปรให้ตรงกัน
$rno = !isset($_GET['rno']) ? '' : htmlspecialchars(trim($_GET['rno']), ENT_QUOTES, 'UTF-8');

include 'function_mats.php';

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
        
        /* เพิ่ม Effect ให้คลิกได้ทั้งแถว */
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important; /* เปลี่ยนเป็นสีฟ้าอ่อนเมื่อ Hover */
        }

        /* ปุ่ม Badge แสดง COIL NO */
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
            box-shadow: none;
        }

        /* ปุ่ม Badge แสดง COIL NO */
        .btn-badge-clickable-2 {
            display: inline-block;
            padding: 8px 14px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 20px;
            text-align: center;
            width: 100%;
            background-color: #eff6ff;
            color: #2dd81d;
            border: 1px solid #bfdbfe;
            text-decoration: none !important;
            transition: all 0.2s ease;
            box-shadow: none;
        }

        /* ปุ่ม Action "ลงข้อมูล" สีน้ำเงินพรีเมียม */
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
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.25);
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A3';?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="remelt_production_mats.php?func=<?php echo $folder_func ?>">Product Remelt Requisition</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="remelt_no">Remelt Number</label>
                    <input class="form-control-minimal" name="remelt_no" id="remelt_no" type="text" placeholder="Enter the code you want to search for...." value="<?php echo htmlspecialchars($rno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_remelt_no()"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_remelt_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <?php
                        echo "<button class='btn btn-success' id='btn_create_kanban' onclick='add_detail_remelt()'>Remelt Requisition (CREATE REMELT) +</button>";
                        ?>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Product Remelt Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">REMELT NO</th>
                                        <th>REMELT Date</th>
                                        <th>REMARK</th>
                                        <th style="text-align: center;">STATUS</th>
                                        <th style="text-align: center;">OPERATOR</th>
                                        <th style="text-align: center;">PROCESS</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];

                                        if($rno != ''){
                                            $sql = "SELECT TOP(50) A.REQUEST_NO, A.REQUEST_DATE, A.REMARK, A.RQT1_OPERATOR FROM PRODRMLT1 AS A WHERE A.REQUEST_NO LIKE :rno ORDER BY A.REQUEST_DATE DESC";
                                            $params[':rno'] = '%'.$rno.'%';
                                        }else{
                                            $sql = "SELECT TOP(50) A.REQUEST_NO, A.REQUEST_DATE, A.REMARK, A.RQT1_OPERATOR FROM PRODRMLT1 AS A ORDER BY A.REQUEST_DATE DESC";
                                        }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_rno = htmlspecialchars($row['REQUEST_NO'], ENT_QUOTES, 'UTF-8');
                                        
                                        // เช็คค่า REMARK หากเป็น NULL, ว่างเปล่า หรือมีแค่ space ให้ใช้ "No Remark Remelt"
                                        $remark_val = trim($row['REMARK'] ?? '');
                                        $display_remark = !empty($remark_val) ? $remark_val : 'No Remark Remelt';

                                        echo "<tr onclick=\"show_detail_rno('" . addslashes($row['REQUEST_NO']) . "')\">";
                                        echo "<td><span class='btn-badge-clickable'>".$safe_rno."</span></td>";
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['REQUEST_DATE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($display_remark, ENT_QUOTES, 'UTF-8')."</td>";
                                        if(RT_REMELT_STATUS($row['REQUEST_NO'])){
                                            echo "<td><span class='btn-badge-clickable'><b>Wait processing.</b></span></td>";
                                        }else{
                                            echo "<td><span class='btn-badge-clickable-2'><b>Approved.</b></span></td>";
                                        }
                                        //echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['REQUEST_NO'], ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center; color:#64748b; font-size:14px;'>".htmlspecialchars($row['RQT1_OPERATOR'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='text-align: center;'>";
                                        echo "<button class='btn btn-bg btn-danger' onclick=\"event.stopPropagation(); delete_rno_data('" . addslashes($row['REQUEST_NO']) . "')\">Delete</button>";
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

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: false,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[1, 'desc']],
    buttons: []
});

function search_remelt_no(){   
    var data_fun = document.getElementById("func").value;
    var data_rno = document.getElementById("remelt_no").value; 
    window.location.assign('remelt_production_mats.php?func='+encodeURIComponent(data_fun)+'&rno='+encodeURIComponent(data_rno)); 
}    

function show_detail_rno(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('remelt_production_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&rno=' + encodeURIComponent(do_no));
}

function add_detail_remelt(do_no) {
    var data_fun = document.getElementById("func").value;
    
    // ถ้ามี do_no และค่าไม่เท่ากับ undefined ให้ส่ง rno ไปด้วย
    if (do_no && do_no !== 'undefined') {
        window.location.assign('create_production_remelt_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&rno=' + encodeURIComponent(do_no));
    } else {
        // ถ้าเป็นการกดสร้างเอกสารใหม่ ไม่ต้องส่ง rno ไป (เพื่อให้ออโต้รันเลขถัดไป)
        window.location.assign('create_production_remelt_detail_mats.php?func=' + encodeURIComponent(data_fun));
    }
}

function delete_rno_data(do_no) {
    // ป้องกันการทำงานซ้อนทับกับ Event ของแถว <tr>
    if (window.event) {
        window.event.stopPropagation();
    }

    var c = confirm('Do you want to delete the data remelt no: ' + do_no + ' Yes or No ?');
    
    if (c) {
        var data_fun = document.getElementById("func").value;
        
        $.ajax({
            url: "model/delete_remelt_no.php",
            type: "POST",
            data: {
                req_no: do_no
            },
            dataType: "json",
            success: function(response) {
                console.log("Response:", response);
                if (response.message) {
                    // ลบสำเร็จ ให้โหลด/รีเฟรชกลับมาที่หน้าเดิม
                    window.location.assign('remelt_production_mats.php?func=' + encodeURIComponent(data_fun));
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
</script>
</html>