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
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; font-size: 15px; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        
        .card-title-g2 { 
            color: #0369a1; 
            border-bottom: 2px solid #bae6fd; 
            padding-bottom: 10px; 
            font-weight: 700; 
            font-size: 18px; 
            margin-bottom: 18px; 
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive Container */
        .table-responsive-custom {
            max-height: 600px;
            overflow-y: auto;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
        }

        .comp-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        
        /* Header Styling & Grouping */
        .comp-table th { 
            background-color: #1e293b; 
            color: #f8fafc; 
            text-align: center; 
            padding: 10px 14px; 
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid #334155;
            border-right: 1px solid #334155;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .comp-table th.bg-group-width { background-color: #0369a1; }
        
        .comp-table td { 
            padding: 10px 14px; 
            border-bottom: 1px solid #e2e8f0; 
            border-right: 1px solid #f1f5f9;
            font-size: 14px; 
            vertical-align: middle; 
        }

        /* Zebra striping + Hover */
        .comp-table tbody tr:nth-child(even) td { background-color: #f8fafc; }
        .comp-table tbody tr:hover td { background-color: #e0f2fe !important; }

        .badge-pdno {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
        }
    </style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'A3';?>

<?php include 'include/'.$folder_func.'/navigation.php'; ?>

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
                    📋 Sheet Inspection Details: 
                    <span class="badge-pdno">
                        <a href="sheet_production_inspec_detail_mats.php?func=<?php echo $folder_func ?>&pno=<?php echo $pd_no?>" style="color:#1e40af; text-decoration:none;">
                            <?php echo htmlspecialchars($pd_no); ?>
                        </a>
                    </span>
                </h3>
            </div>

            <!-- Content Area -->
            <div class="dashboard-card">
                <div class="card-title-g2">
                    📋 Thickness / Diameter Defect List
                </div>
                
                <div class="table-responsive-custom">
                    <table class="comp-table" id="defectTable">
                        <thead>
                            <!-- Row 1: Main Header & Group Header -->
                            <tr>
                                <th rowspan="2" style="width: 20%;">PRODUCT NO</th>
                                <th rowspan="2" style="width: 10%;">ITEM</th>
                                <th rowspan="2" style="width: 15%;">THICKNESS</th>
                                <th colspan="3" class="bg-group-width">WIDTH (mm)</th>
                                <th rowspan="2" style="width: 15%;">FLATNESS</th>
                            </tr>
                            <!-- Row 2: Sub Header for Width -->
                            <tr>
                                <th class="bg-group-width" style="width: 13%;">A</th>
                                <th class="bg-group-width" style="width: 13%;">B</th>
                                <th class="bg-group-width" style="width: 14%;">C</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $sql_list = "SELECT c.* FROM CRSHINSP2 AS c
                                             WHERE c.PRODUCT_NO = :pd_no 
                                             ORDER BY c.PRODUCT_ITEM ASC";
                                $stmt_list = $conn->prepare($sql_list);
                                $stmt_list->execute([':pd_no' => $pd_no]);
                                $rows = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                                if (count($rows) > 0) {
                                    foreach ($rows as $row) {
                                        echo "<tr>";
                                        echo "<td class='text-center' style='font-weight:600; color:#0f172a;'>" . htmlspecialchars($row['PRODUCT_NO']) . "</td>";
                                        echo "<td class='text-center' style='font-weight:600; color:#2563eb;'>" . htmlspecialchars($row['PRODUCT_ITEM']) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['THICKNESS'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['WIDTH_A'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['WIDTH_B'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['WIDTH_C'])) . "</td>";
                                        echo "<td class='text-center'>" . htmlspecialchars(fmt2($row['FLATNESS'])) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center' style='color:#94a3b8; padding:30px; font-weight:600;'>No defect items found</td></tr>";
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='7' class='text-center' style='color:#ef4444; padding:30px; font-weight:600;'>Error fetching data</td></tr>";
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
</body>
<?php include 'include/footer.php';?>
</html>