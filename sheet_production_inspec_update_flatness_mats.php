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

        /* หัวตารางจัดกลุ่ม */
        .comp-table th { 
            background-color: #1e293b; 
            color: #f8fafc; 
            text-align: center; 
            padding: 10px 14px; 
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid #334155;
            border-right: 1px solid #334155;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .comp-table th.bg-group-len { background-color: #1e3a8a; }
        .comp-table th.bg-group-sq { background-color: #065f46; }
        .comp-table th.bg-group-flat { background-color: #854d0e; }
        
        .comp-table td { 
            padding: 10px 14px; 
            border-bottom: 1px solid #e2e8f0; 
            border-right: 1px solid #f1f5f9;
            font-size: 14px; 
            vertical-align: middle; 
        }

        /* ตรึงคอลัมน์แรกๆ ฝั่งซ้าย */
        .sticky-col-1 { position: sticky; left: 0; z-index: 5; background-color: #ffffff; }
        .sticky-col-2 { position: sticky; left: 140px; z-index: 5; background-color: #ffffff; border-right: 2px solid #cbd5e1 !important; }
        
        th.sticky-col-1, th.sticky-col-2 { z-index: 15 !important; background-color: #0f172a !important; }

        /* สลับสีแถว + Hover */
        .comp-table tbody tr:nth-child(even) td { background-color: #f8fafc; }
        .comp-table tbody tr:nth-child(even) td.sticky-col-1,
        .comp-table tbody tr:nth-child(even) td.sticky-col-2 { background-color: #f8fafc; }
        
        .comp-table tbody tr:hover td { background-color: #e0f2fe !important; }

        .badge-pdno {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
        }
        
        .comp-table tbody tr:hover td { background-color: #e0f2fe !important; }
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
            <form id="formAddDefect">
                <div class="dashboard-card">
                    <!-- Section: Primary Key Selection -->
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>PRODUCT NO <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="PRODUCT_NO" name="PRODUCT_NO" value="<?php echo htmlspecialchars($pd_no); ?>" readonly style="background-color:#f1f5f9; font-weight:bold;">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>PRODUCT ITEM (1 - 200) <span class="text-danger">*</span></label>
                            <select class="form-control" id="PRODUCT_ITEM" name="PRODUCT_ITEM" required onchange="fetchExistingData()">
                                <option value="">--Item--</option>
                                <?php for($i=1; $i<=200; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>PRODUCT SIDE (T/B) <span class="text-danger">*</span></label>
                            <select class="form-control" id="PRODUCT_SIDE" name="PRODUCT_SIDE" required onchange="fetchExistingData()">
                                <option value="">--Side--</option>
                                <option value="T">T (Top)</option>
                                <option value="B">B (Bottom)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section: Data Input Fields -->
                <div class="dashboard-card">
                    <!-- LENGTH -->
                    <div class="form-section-title section-len"></div>
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label>LENGTH A (mm)</label>
                            <input type="number" step="any" class="form-control form-control-sm input-val" name="LENGTH_A" value="0.00">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>LENGTH B (mm)</label>
                            <input type="number" step="any" class="form-control form-control-sm input-val" name="LENGTH_B" value="0.00">
                        </div>
                    </div>

                    <!-- SQUARENESS -->
                    <div class="form-section-title section-sq"></div>
                    <div class="row mb-3">
                        <div class="col-md-4 form-group">
                            <label>SQUARENESS A (mm)</label>
                            <input type="number" step="any" class="form-control form-control-sm input-val" id="sq_a" name="SQUARENESS_A" value="0.00">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>SQUARENESS B (mm)</label>
                            <input type="number" step="any" class="form-control form-control-sm input-val" id="sq_b" name="SQUARENESS_B" value="0.00">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>SQUARENESS AB (mm)</label>
                            <input type="number" step="any" class="form-control form-control-sm input-val" id="sq_ab" name="SQUARENESS_AB" value="0.00" readonly>
                        </div>
                    </div>

                    <!-- FLATNESS -->
                    <div class="form-section-title section-flat"></div>
                    <div class="row">
                        <div class="col-md-3 form-group"><label>FLATNESS W1 (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_W1" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS W2 (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_W2" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS L1 (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_L1" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS L2 (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_L2" value="0.00"></div>

                        <div class="col-md-3 form-group"><label>FLATNESS A (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_A" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS B (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_B" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS C (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_C" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS D (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_D" value="0.00"></div>

                        <div class="col-md-3 form-group"><label>FLATNESS E (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_E" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS F (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_F" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS G (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_G" value="0.00"></div>
                        <div class="col-md-3 form-group"><label>FLATNESS H (mm)</label><input type="number" step="any" class="form-control form-control-sm input-val" name="FLATNESS_H" value="0.00"></div>
                    </div>

                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" id="btnSubmit" class="btn btn-primary btn-fill" style="padding: 10px 30px; font-weight: bold;">
                            💾 Save Defect
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-responsive-custom">
                    <table class="comp-table">
                        <thead>
                            <!-- Row 1: Main Group Headers -->
                            <tr>
                                <th class="sticky-col-1" rowspan="2" style="min-width: 140px;">PRODUCT NO</th>
                                <th class="sticky-col-2" rowspan="2" style="min-width: 80px;">ITEM</th>
                                <th colspan="2" class="bg-group-len">LENGTH (mm)</th>
                                <th colspan="3" class="bg-group-sq">SQUARENESS (mm)</th>
                                <th colspan="12" class="bg-group-flat">FLATNESS (mm)</th>
                                 <th class="sticky-col-2" rowspan="2" style="min-width: 80px;">Process</th>
                            </tr>
                            <!-- Row 2: Sub Headers -->
                            <tr>
                                <th class="bg-group-len">A</th>
                                <th class="bg-group-len">B</th>
                                <th class="bg-group-sq">A</th>
                                <th class="bg-group-sq">B</th>
                                <th class="bg-group-sq">AB</th>
                                <th class="bg-group-flat">W1</th>
                                <th class="bg-group-flat">W2</th>
                                <th class="bg-group-flat">L1</th>
                                <th class="bg-group-flat">L2</th>
                                <th class="bg-group-flat">A</th>
                                <th class="bg-group-flat">B</th>
                                <th class="bg-group-flat">C</th>
                                <th class="bg-group-flat">D</th>
                                <th class="bg-group-flat">E</th>
                                <th class="bg-group-flat">F</th>
                                <th class="bg-group-flat">G</th>
                                <th class="bg-group-flat">H</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $sql_list = "SELECT c.* FROM CRSHINSP3 AS c
                                             WHERE c.PRODUCT_NO = :pd_no 
                                             ORDER BY c.PRODUCT_ITEM ASC";
                                $stmt_list = $conn->prepare($sql_list);
                                $stmt_list->execute([':pd_no' => $pd_no]);
                                $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                                if (count($rows) > 0) {
                                    foreach ($rows as $row) {
                                        echo "<tr>";
                                        echo "<td class='text-center sticky-col-1' style='font-weight:600; color:#0f172a;'>" . htmlspecialchars($row['PRODUCT_NO']) . "</td>";
                                        echo "<td class='text-center sticky-col-2' style='font-weight:600; color:#2563eb;'>" . htmlspecialchars($row['PRODUCT_ITEM']) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['LENGTH_A'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['LENGTH_B'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['SQUARENESS_A'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['SQUARENESS_B'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['SQUARENESS_AB'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_W1'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_W2'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_L1'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_L2'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_A'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_B'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_C'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_D'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_E'])) . "</td>";     
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_F'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_G'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS_H'])) . "</td>";   
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
                                    echo "<tr><td colspan='19' class='text-center' style='color:#94a3b8; padding:30px; font-weight:600;'>No defect items found</td></tr>";
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='19' class='text-center' style='color:#ef4444; padding:30px; font-weight:600;'>Error fetching data</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#sq_a, #sq_b').on('input', function() {
        let valA = parseFloat($('#sq_a').val()) || 0;
        let valB = parseFloat($('#sq_b').val()) || 0;
        
        let result = valB - valA;
        $('#sq_ab').val(result.toFixed(2));
    });
});
    
$(document).ready(function() {
    // จัดการ Event ส่งข้อมูลบันทึกผ่าน AJAX
    $('#formAddDefect').on('submit', function(e) {
        e.preventDefault();

        var btn = $('#btnSubmit');
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'model/add_save_inspec_flatness.php',
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
            url: 'model/del_defect_flatness_mats.php',
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