<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$pd_no = isset($_GET['pdno']) ? htmlspecialchars(trim($_GET['pdno']), ENT_QUOTES, 'UTF-8') : '';

include 'function_mats.php'; // ไฟล์เชื่อมต่อ DB ($conn) และ Helper Functions
include 'dbcon_mats-new.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; font-size: 16px; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 8px; font-weight: 700; font-size: 18px; margin-bottom: 18px; }

        .form-group { margin-bottom: 18px; }
        .form-group label { font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; display: block; }
        
        .form-control { border-radius: 6px; border: 1px solid #cbd5e1; font-size: 16px; height: 44px; padding: 8px 12px; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
        
        .readonly-control { background-color: #e2e8f0 !important; color: #1e293b !important; font-weight: 700; cursor: not-allowed; }
        .input-control { background-color: #ffffff !important; color: #1e293b !important; font-weight: 700; cursor: not-allowed; }

        /* Style ปรับแต่งช่องเลือก Defect ID แบบไฮไลต์สีแดงตาม Mockup d1.jpg */
        .select-defect-red { background-color: #dc2626 !important; color: #ffffff !important; font-weight: 700; }
        .select-defect-red option { background-color: #ffffff; color: #334155; font-weight: normal; }

        .comp-table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
        .comp-table th { background-color: #1e293b; color: #ffffff; text-align: center; padding: 10px; font-size: 15px; }
        .comp-table td { padding: 8px 12px; border-bottom: 1px solid #e2e8f0; font-size: 15px; vertical-align: middle; }

        .btn-save { background-color: #2563eb; color: #fff; font-weight: 700; padding: 12px 28px; border-radius: 8px; border: none; font-size: 17px; transition: background 0.2s; }
        .btn-save:hover { background-color: #1d4ed8; color: #fff; }
        .btn-back { background-color: #64748b; color: #fff; font-weight: 700; padding: 10px 20px; border-radius: 8px; border: none; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn-back:hover { background-color: #475569; color: #fff; }
    </style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'A3'; ?>

<?php   
    include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="sheet_production_inspec_mats.php?func=<?php echo $folder_func ?>">Circle or Sheet Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                    ✏️ Defect Circle & Sheet No. : <span style="color:#2563eb;"><a href="sheet_production_inspec_update_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $pd_no?>"><?php echo htmlspecialchars($pd_no); ?></a></span>
                </h3>
                <div>
                    <a href="sheet_production_inspec_update_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $pd_no?>" class="btn-back">⬅️ Back</a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="dashboard-card">
                <div class="row">
                    
                    <!-- Left Form Panel -->
                    <div class="col-md-5">
                        <div class="card-title-g1">➕ Add Circle & Sheet Defect Entry</div>
                        <form id="formAddDefect">
                            <input type="hidden" name="folder_func" value="<?php echo $folder_func; ?>">

                            <div class="form-group">
                                <label>Product No.</label>
                                <input type="text" class="form-control readonly-control" id="pd_no" name="pd_no" value="<?php echo htmlspecialchars($pd_no); ?>" readonly required>
                            </div>

                            <!-- POSITION PRODUCT SIDE -->
                            <div class="form-group">
                                        <label>PRODUCT SIDE</label>
                                        <select class="form-control" id="pass_no" name="pass_no" required>
                                            <option value="">-- ITEM of Inspection --</option>
                                            <option value="T">T (Top)</option>
                                            <option value="B">B (Bottom)</option>
                                        </select>
                            </div>

                            
                            <!-- POSITION ITEM -->
                            <div class="form-group">
                                <label>ITEM</label>
                                <select class="form-control" id="item_no" name="item_no" required>
                                    <option value="">-- ITEM of Inspection --</option>
                                    <?php for ($i = 1; $i <= 200; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- POSITION THICKNESS -->
                            <div class="form-group">
                                <label>THICKNESS (mm)</label>
                                <input type="number" class="form-control input-control" id="DF_THICKNESS" name="DF_THICKNESS" value="0.00">
                            </div>

                            <!-- POSITION DIAMETER -->
                            <div class="form-group">
                                <label>DIAMETER WIDTH A (mm)</label>
                                <input type="number" class="form-control input-control" id="DF_DIMAETER1" name="DF_DIMAETER1" value="0.00">
                            </div>

                            <!-- POSITION DIAMETER -->
                            <div class="form-group">
                                <label>DIAMETER WIDTH B (mm)</label>
                                <input type="number" class="form-control input-control" id="DF_DIMAETER2" name="DF_DIMAETER2" value="0.00">
                            </div>

                            <!-- POSITION DIAMETER -->
                            <div class="form-group">
                                <label>DIAMETER WIDTH C (mm)</label>
                                <input type="number" class="form-control input-control" id="DF_DIMAETER3" name="DF_DIMAETER3" value="0.00">
                            </div>

                            <!-- POSITION FIATNESS -->
                            <div class="form-group">
                                <label>FIATNESS</label>
                                 <input type="number" class="form-control input-control" id="DF_DIMAETER4" name="DF_DIMAETER4" value="0.00">
                            </div>

                            <button type="submit" id="btnSubmit" class="btn btn-save" style="width:100%; margin-top:10px;">💾 Save Defect</button>
                        </form>
                    </div>

<!-- Right Data Table Panel -->
                    <div class="col-md-7">
                        <div class="card-title-g2">📋 Cold Mill Defect List</div>
                        <div class="table-responsive">
                        <table class="comp-table" id="defectTable">
                            <thead>
                                <tr>
                                    <th style="width:20%;">Product No.</th>
                                    <th style="width:8%;">ID</th>
                                    <th style="width:8%;">SIDE</th>
                                    <th style="width:10%;">THICKNESS</th>
                                    <th style="width:10%;">WIDTH_A</th>
                                    <th style="width:8%;">WIDTH_B</th>
                                    <th style="width:8%;">WIDTH_C</th>
                                    <th style="width:8%;">FLATNESS</th>
                                    <th style="width:8%;">Process</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    // เพิ่ม SELECT ACTION_MID, ACTION_TAIL, ACTION_WHOLE
                                    $sql_list = "SELECT c.* FROM CRSHINSP2 AS c
                                                WHERE c.PRODUCT_NO = :pd_no 
                                                ORDER BY c.PRODUCT_ITEM ASC";
                                    
                                    $stmt_list = $conn->prepare($sql_list);
                                    $stmt_list->execute([':pd_no' => $pd_no]);
                                    $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($rows) > 0) {
                                        foreach ($rows as $row) {
                                            echo "<tr>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['PRODUCT_NO']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['PRODUCT_ITEM']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['PRODUCT_SIDE']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['THICKNESS'])) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['WIDTH_A']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['WIDTH_B']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['WIDTH_C']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS'])) . "</td>";
                                            echo "<td class='text-center'>
                                                    <button type='button' class='btn btn-xs btn-danger btn-delete' 
                                                        data-pd='" . htmlspecialchars($row['PRODUCT_NO']) . "' 
                                                        data-sd='" . htmlspecialchars($row['PRODUCT_SIDE']) . "' 
                                                        data-id='" . htmlspecialchars($row['PRODUCT_ITEM']) . "'>
                                                        Delete
                                                    </button>
                                                </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='10' class='text-center' style='color:#94a3b8; padding:20px;'>No defect items found</td></tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='10' class='text-center' style='color:#ef4444;'>Error fetching data</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // จัดการ Event ส่งข้อมูลบันทึกผ่าน AJAX
    $('#formAddDefect').on('submit', function(e) {
        e.preventDefault();

        var btn = $('#btnSubmit');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'model/add_thicknet_diameter_defect_mats.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    btn.prop('disabled', false).text('💾 Save Defect');
                }
            },
            error: function(xhr, status, error) {
                alert('Connection error occurred. Please try again.');
                btn.prop('disabled', false).text('💾 Save Defect');
            }
        });
    });
});

// จัดการ Event กดปุ่ม ลบ (Delete)
$(document).on('click', '.btn-delete', function() {
    var pd_no = $(this).data('pd');
    var defect_id = $(this).data('id');
    var side_id = $(this).data('sd');

    if (confirm('Do you want to delete an item Defect ID: ' + defect_id + ' (Product No: ' + pd_no + ') Yes or No ?')) {
        $.ajax({
            url: 'model/del_defect_thicknet_diameter_mats.php',
            type: 'POST',
            data: {
                pd_no: pd_no,
                defect_id: defect_id,
                side_no: side_id
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Connection error occurred while deleting.');
            }
        });
    }
});
</script>
</body>
<?php include 'include/footer.php';?>
</html>