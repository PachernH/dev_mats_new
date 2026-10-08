<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '';

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

<?php $menu = 'A3';?>

<?php   
    include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="virtual_millcert_ccsh_sup_mats.php?func=<?php echo $folder_func ?>">Virtual Coil Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                    ✏️ Defect Cold Mill Coil: <span style="color:#2563eb;"><a href="virtual_coil_production_inspec_update_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>
                <div>
                    <a href="virtual_coil_production_inspec_update_mats.php?func=<?php echo $folder_func ?>&coilno=<?php echo $coil_no?>" class="btn-back">⬅️ Back</a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="dashboard-card">
                <div class="row">
                    
                    <!-- Left Form Panel -->
                    <div class="col-md-5">
                        <div class="card-title-g1">➕ Add CM Defect Entry</div>
                        <form id="formAddDefect">
                            <input type="hidden" name="folder_func" value="<?php echo $folder_func; ?>">

                            <div class="form-group">
                                <label>Product No.</label>
                                <input type="text" class="form-control readonly-control" id="coil_no" name="coil_no" value="<?php echo htmlspecialchars($coil_no); ?>" readonly required>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Defect ID</label>
                                        <select class="form-control select-defect-red" id="defect_id" name="defect_id" required>
                                            <option value="" style="color:#334155;">-- Select Defect --</option>
                                            <?php
                                            // 1. Query Defect ID สำหรับ PROCESS = 'CM'
                                            try {
                                                $sql_d = "SELECT d.DEFECT_ID, d.DESCRIPTION FROM DFCTMSTR1 AS d WHERE d.PROCESS = 'CM' ORDER BY d.DEFECT_ID ASC";
                                                $stmt_d = $conn->prepare($sql_d);
                                                $stmt_d->execute();
                                                while ($row_d = $stmt_d->fetch(PDO::FETCH_ASSOC)) {
                                                    echo '<option value="' . htmlspecialchars($row_d['DEFECT_ID']) . '">' 
                                                         . htmlspecialchars($row_d['DEFECT_ID']) . ' - ' . htmlspecialchars($row_d['DESCRIPTION']) 
                                                         . '</option>';
                                                }
                                            } catch (PDOException $e) {
                                                echo '<option value="">Error loading defects</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Pass No.</label>
                                        <select class="form-control" id="pass_no" name="pass_no" required>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="7">7</option>
                                            <option value="8">8</option>
                                            <option value="9">9</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- POSITION HEAD -->
                            <div class="form-group">
                                <label>Position Head</label>
                                <select class="form-control" id="action_head" name="action_head">
                                    <option value="0">-- None --</option>
                                    <?php
                                    try {
                                        $sql_p1 = "SELECT ACTION_ID, DESCRIPTION FROM DFCTMSTR2 ORDER BY ACTION_ID ASC";
                                        $stmt_p1 = $conn->prepare($sql_p1);
                                        $stmt_p1->execute();
                                        while ($p1 = $stmt_p1->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . htmlspecialchars($p1['ACTION_ID']) . '">' 
                                                 . htmlspecialchars($p1['ACTION_ID']) . ' - ' . htmlspecialchars($p1['DESCRIPTION']) 
                                                 . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="0">Error loading options</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- POSITION MID -->
                            <div class="form-group">
                                <label>Position Mid</label>
                                <select class="form-control" id="action_mid" name="action_mid">
                                    <option value="0">-- None --</option>
                                    <?php
                                    try {
                                        $sql_p2 = "SELECT ACTION_ID, DESCRIPTION FROM DFCTMSTR2 ORDER BY ACTION_ID ASC";
                                        $stmt_p2 = $conn->prepare($sql_p2);
                                        $stmt_p2->execute();
                                        while ($p2 = $stmt_p2->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . htmlspecialchars($p2['ACTION_ID']) . '">' 
                                                 . htmlspecialchars($p2['ACTION_ID']) . ' - ' . htmlspecialchars($p2['DESCRIPTION']) 
                                                 . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="0">Error loading options</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- POSITION TAIL -->
                            <div class="form-group">
                                <label>Position Tail</label>
                                <select class="form-control" id="action_tail" name="action_tail">
                                    <option value="0">-- None --</option>
                                    <?php
                                    try {
                                        $sql_p3 = "SELECT ACTION_ID, DESCRIPTION FROM DFCTMSTR2 ORDER BY ACTION_ID ASC";
                                        $stmt_p3 = $conn->prepare($sql_p3);
                                        $stmt_p3->execute();
                                        while ($p3 = $stmt_p3->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . htmlspecialchars($p3['ACTION_ID']) . '">' 
                                                 . htmlspecialchars($p3['ACTION_ID']) . ' - ' . htmlspecialchars($p3['DESCRIPTION']) 
                                                 . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="0">Error loading options</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- POSITION WHOLE -->
                            <div class="form-group">
                                <label>Position Whole</label>
                                <select class="form-control" id="action_whole" name="action_whole">
                                    <option value="0">-- None --</option>
                                    <?php
                                    try {
                                        $sql_p4 = "SELECT ACTION_ID, DESCRIPTION FROM DFCTMSTR2 ORDER BY ACTION_ID ASC";
                                        $stmt_p4 = $conn->prepare($sql_p4);
                                        $stmt_p4->execute();
                                        while ($p4 = $stmt_p4->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . htmlspecialchars($p4['ACTION_ID']) . '">' 
                                                 . htmlspecialchars($p4['ACTION_ID']) . ' - ' . htmlspecialchars($p4['DESCRIPTION']) 
                                                 . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option value="0">Error loading options</option>';
                                    }
                                    ?>
                                </select>
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
                                    <th style="width:8%;">ID.</th>
                                    <th>Description</th>
                                    <th style="width:10%;">Pass No.</th>
                                    <th style="width:8%;">Process</th>
                                    <th style="width:8%;">Head</th>
                                    <th style="width:8%;">Mid</th>
                                    <th style="width:8%;">Tail</th>
                                    <th style="width:8%;">Whole</th>
                                    <th style="width:8%;">Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    // เพิ่ม SELECT ACTION_MID, ACTION_TAIL, ACTION_WHOLE
                                    $sql_list = "SELECT c.COIL_NO, c.DEFECT_ID, c.PASS_NO, c.PROCESS, 
                                                        c.ACTION_HEAD, c.ACTION_MID, c.ACTION_TAIL, c.ACTION_WHOLE
                                                FROM COILINSP2 c
                                                WHERE c.COIL_NO = :coil_no AND c.PROCESS = 'CM'
                                                ORDER BY c.PASS_NO ASC, c.DEFECT_ID ASC";
                                    
                                    $stmt_list = $conn->prepare($sql_list);
                                    $stmt_list->execute([':coil_no' => $coil_no]);
                                    $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($rows) > 0) {
                                        foreach ($rows as $row) {
                                            echo "<tr>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['COIL_NO']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['DEFECT_ID']) . "</td>";
                                            echo "<td>" . htmlspecialchars(RT_DEF_Desc('CM',$row['DEFECT_ID'])) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['PASS_NO']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['PROCESS']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ACTION_HEAD']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ACTION_MID']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ACTION_TAIL']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['ACTION_WHOLE']) . "</td>";
                                            echo "<td class='text-center'>
                                                    <button type='button' class='btn btn-xs btn-danger btn-delete' 
                                                        data-coil='" . htmlspecialchars($row['COIL_NO']) . "' 
                                                        data-id='" . htmlspecialchars($row['DEFECT_ID']) . "' 
                                                        data-pass='" . htmlspecialchars($row['PASS_NO']) . "'>
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
            url: 'model/add_cm_defect_caster_mats.php',
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
    var coil_no = $(this).data('coil');
    var defect_id = $(this).data('id');
    var pass_no = $(this).data('pass');

    if (confirm('You want to delete the Defect ID entry: ' + defect_id + ' (Pass ' + pass_no + ') Yes or No ?')) {
        $.ajax({
            url: 'model/del_cm_defect_caster_mats.php',
            type: 'POST',
            data: {
                coil_no: coil_no,
                defect_id: defect_id,
                pass_no: pass_no
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