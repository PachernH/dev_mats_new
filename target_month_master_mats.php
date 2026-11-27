<?php
session_start();
if (!isset($_SESSION['ID'])) { header("Location: index.php"); exit(); }

// รับค่า func จาก URL (เหมือน furnance_mats)
$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : (isset($_SESSION['FUNC']) ? $_SESSION['FUNC'] : '');

include("dbcon_mats-new.php");

// หาปีล่าสุดที่มีในตารางเพื่อแนะนำปีถัดไปเป็นค่าเริ่มต้น
$stmtMaxYear = $conn->query("SELECT MAX(CAST(TARGET_YEAR AS INT)) AS MAX_YEAR FROM TRGTMSTR2");
$maxRow = $stmtMaxYear->fetch(PDO::FETCH_ASSOC);
$defaultNewYear = ($maxRow && !empty($maxRow['MAX_YEAR'])) ? ((int)$maxRow['MAX_YEAR'] + 1) : ((int)date('Y') + 1);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target Month Master | Meyer Aluminium</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .filter-item { display: flex; flex-direction: column; gap: 8px; }
        .filter-item label { font-size: 13px; font-weight: 700; color: #64748b; margin: 0; letter-spacing: 0.5px; text-transform: uppercase; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 15px; color: #334155; transition: all 0.2s ease; box-shadow: none; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }

        /* ปุ่ม Create New Target แบบใหม่ */
        .btn-create-target {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            padding: 0 22px;
            border-radius: 8px;
            border: none;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: 0 4px 10px -1px rgba(37, 99, 235, 0.3);
            transition: all 0.2s ease;
        }
        .btn-create-target:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px -2px rgba(37, 99, 235, 0.4);
        }
        .btn-create-target:active {
            transform: translateY(0);
        }
        .btn-create-target .plus-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.22);
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
        }

        #user_table { border-collapse: separate; border-spacing: 0; width: 100% !important; }
        #user_table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; padding: 16px 10px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        #user_table tbody td { padding: 14px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; text-align: center; }
        #user_table tbody tr { cursor: pointer; transition: background-color 0.2s; }
        #user_table tbody tr:hover td { background-color: #eff6ff; }
        .badge-year { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 6px 16px; border-radius: 20px; border: 1px solid #bfdbfe; font-size: 14px; }
        .target-month-cell { font-size: 13px; color: #475569; }
        .target-total { font-weight: 700; color: #0f172a; font-size: 15px; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp7'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>
    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="target_month_master_mats.php?func=<?php echo $folder_func; ?>">
                        🎯 Target Month Master
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="search_year">Search Year</label>
                    <input class="form-control" name="search_year" id="search_year" type="number" placeholder="e.g. 2026" onkeyup="filterTable()"/>
                </div>
                <div class="filter-item">
                    <button type="button" class="btn-create-target" onclick="goToCreateTarget()">
                        <span class="plus-icon">+</span>
                        <span>Create New Target (<?php echo $defaultNewYear; ?>)</span>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📅 Target History Records</h4>
                            <small style="color:#64748b;"><i class="fa fa-info-circle"></i> Click on a row to view details</small>
                        </div>
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">YEAR</th>
                                        <th style="width: 80px;">TOTAL<br>WORKDAY</th>
                                        <th style="width: 100px;">TARGET<br>YEARLY</th>
                                        <th>JAN</th><th>FEB</th><th>MAR</th><th>APR</th><th>MAY</th><th>JUN</th>
                                        <th>JUL</th><th>AUG</th><th>SEP</th><th>OCT</th><th>NOV</th><th>DEC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $sql = "SELECT * FROM TRGTMSTR2 ORDER BY TARGET_YEAR DESC";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $year = htmlspecialchars($row['TARGET_YEAR'], ENT_QUOTES, 'UTF-8');
                                        $workday = htmlspecialchars($row['WORKDAY_YEAR'], ENT_QUOTES, 'UTF-8');
                                        $amount_year = number_format($row['AMOUNT_YEAR'], 2);
                                        
                                        echo "<tr onclick=\"window.location.href='target_month_detail_mats.php?year=".$year."&func=".$folder_func."'\">";
                                        echo "<td align='center'><span class='badge-year'>".$year."</span></td>";
                                        echo "<td style='font-weight:500;'>".$workday." Days</td>";
                                        echo "<td class='target-total'>".$amount_year."</td>";
                                        $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
                                        foreach($months as $m) {
                                            $val = isset($row['AMOUNT_'.$m]) ? number_format($row['AMOUNT_'.$m], 2) : '0.00';
                                            echo "<td class='target-month-cell'>".$val."</td>";
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
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
    function goToCreateTarget() {
        const defaultYear = <?php echo $defaultNewYear; ?>;
        const folderFunc = "<?php echo $folder_func; ?>";
        window.location.href = `target_month_form_mats.php?mode=new&year=${defaultYear}&func=${encodeURIComponent(folderFunc)}`;
    }

    $(document).ready(function() {
        $("#user_table").DataTable({
            scrollY: "500px", scrollX: true, scrollCollapse: true, paging: true,
            dom: 'Bfrtip', pageLength: 20, order:[[0, 'desc']], buttons: []
        });
    });

    function filterTable() {
        var input = document.getElementById("search_year");
        var filter = input.value;
        var table = document.getElementById("user_table");
        var tr = table.getElementsByTagName("tr");
        for (var i = 1; i < tr.length; i++) {
            var td = tr[i].getElementsByTagName("td")[0];
            if (td) {
                var txtValue = td.textContent || td.innerText;
                if (txtValue.indexOf(filter) > -1) { tr[i].style.display = ""; } else { tr[i].style.display = "none"; }
            }
        }
    }
</script>
</html>