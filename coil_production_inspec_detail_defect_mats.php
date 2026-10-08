<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '';

include 'function_mats.php'; // ไฟล์เชื่อมต่อ DB ($conn) และ Helper Functions[cite: 1]

include 'dbcon_mats-new.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning[cite: 1]
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="coil_production_inspec_mats.php?func=<?php echo $folder_func ?>">Coil Inspection</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0; font-weight:700; color:#1e293b; font-size:24px;">
                    ✏️ Defect Caster Coil: <span style="color:#2563eb;"><a href="coil_production_inspec_detail_mats.php?func=<?php echo $folder_func ?>&COIL=<?php echo $coil_no?>"><?php echo htmlspecialchars($coil_no); ?></a></span>
                </h3>

            </div>

            <!-- Content Area -->
            <div class="dashboard-card">
                <div class="row">
                    
                    <div class="col-md-7">
                        <div class="card-title-g2">📋 Defect List</div>
                        <div class="table-responsive">
                        <table class="comp-table" id="defectTable">
                            <thead>
                                <tr>
                                    <th style="width:20%;">Product No.</th>
                                    <th style="width:10%;">ID.</th>
                                    <th style="width:10%;">Pst.</th>
                                    <th>Description</th>
                                    <th style="width:10%;">Qty.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $sql_list = "SELECT c.COIL_NO, c.DEFECT_ID, c.PASS_NO, c.DEFECT_POSITION, c.DEFECT_QTY, m.DESCRIPTION 
                                                FROM COILINSP2 c
                                                LEFT JOIN DFCTMSTR1 m ON c.DEFECT_ID = m.DEFECT_ID AND c.PROCESS = m.PROCESS
                                                WHERE c.COIL_NO = :coil_no AND c.PROCESS = 'CD'
                                                ORDER BY c.DEFECT_ID ASC";
                                    
                                    $stmt_list = $conn->prepare($sql_list);
                                    $stmt_list->execute([':coil_no' => $coil_no]);
                                    $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($rows) > 0) {
                                        foreach ($rows as $row) {
                                            echo "<tr>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['COIL_NO']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['DEFECT_ID']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['DEFECT_POSITION']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['DESCRIPTION']) . "</td>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row['DEFECT_QTY']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center' style='color:#94a3b8; padding:20px;'>No defect items found</td></tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='6' class='text-center' style='color:#ef4444;'>Error fetching data</td></tr>";
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
</body>
<?php include 'include/footer.php';?>
</html>