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
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
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
            transition: all 0.2s ease;
            box-shadow: none;
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
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.25);
        }

        .btn-print-custom {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.25), 0 2px 4px -2px rgba(2, 132, 199, 0.15);
            transition: all 0.2s ease-in-out;
            outline: none;
        }

        .btn-print-custom:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            box-shadow: 0 6px 12px -2px rgba(2, 132, 199, 0.35), 0 3px 6px -3px rgba(0, 0, 0, 0.2);
            transform: translateY(-1.5px);
            color: #ffffff;
        }

        /* เพิ่มสไตล์สำหรับปุ่ม Create */
        .btn-create-custom {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.25);
            transition: all 0.2s ease-in-out;
            text-decoration: none !important;
        }
        .btn-create-custom:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            transform: translateY(-1.5px);
            box-shadow: 0 6px 12px -2px rgba(22, 163, 74, 0.35);
        }
        .btn-create-custom:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="remelt_production_mats.php?func=<?php echo $folder_func ?>">Product Remelt Requisition</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">

                    <!-- Row 1: Approve Button -->
                        <div style="margin-bottom: 20px;">
                            <?php
                                if(RT_REMELT_STATUS($rno)){
                                    echo "<button type='button' class='btn btn-primary' data-toggle='modal' data-target='#data_port_detail'>Approve RM ALL (Change Remelt All Status) +</button>";
                                }else{
                                    echo "<button type='button' class='btn btn-default' disabled title='Cannot process'>Approve RM ALL (Change Remelt All Status) +</button>";
                                }
                            ?>
                        </div>

                        <!-- Row 2: Header Title & Action Buttons (วางข้างกันอย่างสวยงาม) -->
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                            <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:18px; display:flex; align-items:center; gap:8px;">
                                📋 Product Remelt Records : <span style="color:#2563eb;"><?php echo htmlspecialchars($rno); ?></span>
                            </h3>    

                            <!-- Action Buttons Group ฝั่งขวา -->
                            <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (RT_REMELT_STATUS($rno)): ?>
                                <a href="plus_create_production_remelt_detail_mats.php?func=<?php echo urlencode($folder_func); ?>&rno=<?php echo urlencode($rno); ?>" class="btn-create-custom">
                                    ➕ Create Remelt Requisition
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn-create-custom" disabled>
                                    ➕ Create Remelt Requisition
                                </button>
                            <?php endif; ?>
                                <?php
                                    echo "<button type='button' class='btn-print-custom' onclick='preview_remelt(\"".$rno."\", this)'>";
                                    echo "🖨️ Print Remelt Document";
                                    echo "</button>";  
                                ?>
                            </div>                
                        </div>
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 160px; text-align: center;">PRODUCT_NO</th>
                                        <th>PRODUCT_ID</th>
                                        <th>CUSTOMER</th>
                                        <th>ALLOY</th>
                                        <th>TEMPER</th>
                                        <th>GRADE</th>
                                        <th>THICKNESS</th>
                                        <th>WIDTH</th>
                                        <th>LENGTH</th>
                                        <th>WEIGHT</th>
                                        <th>STATUS</th>
                                        <th style="width: 130px; text-align: center;">Input</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];

                                    if($rno != ''){
                                        $sql = "SELECT TOP(50) A.PRODUCT_NO, A.SALEORDER_NO, A.CSTMSPPL_ID, A.ALLOY, A.TEMPER, A.GRADE, A.THICKNESS, A.WIDTH, A.LENGTH, A.PRODUCT_WEIGHT, A.ORIGINAL_STATUS, A.REMELT_STATUS, A.RESPONS_TYPE, A.REMELT_REASON FROM PRODRMLT2 AS A WHERE A.REQUEST_NO LIKE :rno ORDER BY A.PRODUCT_NO DESC";
                                        $params[':rno'] = '%'.$rno.'%';
                                    }else{
                                        $sql = "SELECT TOP(50) A.PRODUCT_NO, A.SALEORDER_NO, A.CSTMSPPL_ID, A.ALLOY, A.TEMPER, A.GRADE, A.THICKNESS, A.WIDTH, A.LENGTH, A.PRODUCT_WEIGHT, A.ORIGINAL_STATUS, A.REMELT_STATUS, A.RESPONS_TYPE, A.REMELT_REASON FROM PRODRMLT2 AS A ORDER BY A.PRODUCT_NO DESC";
                                    }

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $safe_remelt_no = htmlspecialchars($row['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                        
                                        echo "<tr onclick=\"show_detail_remelt('" . addslashes($row['PRODUCT_NO']) . "')\">";
                                        echo "<td><span class='btn-badge-clickable'>".$safe_remelt_no."</span></td>";
                                        echo "<td style='font-weight:600; color:#1e293b; font-size:15px;'>".htmlspecialchars($row['SALEORDER_NO'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td style='color:#64748b; font-size:14px;'>".htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['GRADE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars(fmt2($row['THICKNESS']) ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['WIDTH'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['LENGTH'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars(fmt2($row['PRODUCT_WEIGHT']) ?? '', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row['RESPONS_TYPE'] ?? '', ENT_QUOTES, 'UTF-8')."</td>";

                                        $remelt_status = trim((string)($row['REMELT_STATUS'] ?? ''));
                                        
                                        if ($remelt_status === 'RM') {
                                            echo "<td align='center'><button type='button' class='btn btn-default' disabled title='Cannot process'> Approve RM </button></td>";
                                        } else {
                                            echo "<td align='center'><button type='button' class='btn btn-success' onclick=\"remelt_no(event, '" . addslashes($row['PRODUCT_NO']) . "')\"> Approve RM </button></td>";
                                        }
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

<!-- Modal Dialog -->
<div class="modal fade" id="data_port_detail" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <form id="form_remelt_all">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="exampleModalLabel">⚙️ REMELT ALL (Change Status)</h4>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">REQUEST NO <span style="color:#ef4444;">*</span></label>
                        <input type="text" class="form-control" style="width:100%;" id="modal_rno" name="modal_rno" value="<?php echo htmlspecialchars($rno); ?>" readonly />
                    </div>

                    <div>
                        <label style="display:block; font-size:13px; font-weight:700; color:#64748b; margin-bottom:8px;">CONFIRMATION</label>
                        <p style="color: #334155; font-size: 14px;">Do you want to update the status remelt all Yes or No ?</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:6px; font-size:14px;">Close</button>
                    <button type="button" class="btn btn-success" onclick="submitRemeltAll()">
                       💾 Confirm (Save)
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
$(document).ready(function() {
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
});

function submitRemeltAll() {
    var rno = $('#modal_rno').val();

    if (!rno) {
        alert('No information found REQUEST NO');
        return;
    }

    if (!confirm('Do you want to update the status Remelt ALL For the ' + rno + ' Yes or No ?')) {
        return;
    }

    $.ajax({
        url: 'model/save_remelt_all_mats.php',
        type: 'POST',
        data: {
            rno: rno
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#data_port_detail').modal('hide');
                location.reload();
            } else {
                alert('error occurred: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('error occurred while connecting to the server.');
            console.error(error);
        }
    });
}

function input_remelt_no(e, do_no){
    if (e && e.stopPropagation) {
        e.stopPropagation();
    }
    var data_fun = document.getElementById("func").value;
    window.location.assign('casting_input_mats.php?func='+encodeURIComponent(data_fun)+'&rno='+encodeURIComponent(do_no));         
} 

function show_detail_remelt(do_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('production_remelt_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&pno=' + encodeURIComponent(do_no));
}

function preview_remelt(rno, btnObj) {
    if (!rno || rno.trim() === '') {
        alert('Please select or enter the REQUEST NO. you want to print');
        return;
    }

    var width = 1100;
    var height = 750;
    var left = (screen.width - width) / 2;
    var top = (screen.height - height) / 2;

    var url = 'print_remelt_report.php?rno=' + encodeURIComponent(rno);
    var windowFeatures = 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left + ',scrollbars=yes,resizable=yes';

    var previewWin = window.open(url, 'PreviewRemelt_' + rno, windowFeatures);
    if (window.focus) {
        previewWin.focus();
    }
}

function remelt_no(e, product_no) {
    if (e && e.stopPropagation) {
        e.stopPropagation();
    }

    if (!product_no) {
        alert('No information found PRODUCT NO');
        return;
    }

    if (!confirm('Do you want to update the status Remelt For the ' + product_no + ' Yes or No ?')) {
        return;
    }

    $.ajax({
        url: 'model/save_remelt_single_mats.php',
        type: 'POST',
        data: {
            pno: product_no
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                location.reload();
            } else {
                alert('error occurred: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('error occurred while connecting to the server.');
            console.error(error);
        }
    });
}
</script>
</body>
</html>