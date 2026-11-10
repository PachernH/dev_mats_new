<?php
session_start();
$iduser_func     = $_SESSION['ID'] ?? '';
$folder_func     = $_SESSION['FUNC'] ?? '';
$department_func = strtoupper(trim($_SESSION['DEPARTMENT'] ?? ''));

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$is_authorized = ($department_func === 'MK' || $department_func === 'IT');

$search_period = htmlspecialchars(trim($_GET['search_period'] ?? ''), ENT_QUOTES, 'UTF-8');
$search_cust   = htmlspecialchars(trim($_GET['search_cust'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Price Period Management</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #fff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background: transparent !important; height: 40px; font-size: 14px; font-weight: 600; color: #1e293b; }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }
        .btn-search { background-color: #1e293b; color: #fff; font-weight: 600; height: 40px; padding: 0 20px; border-radius: 8px; border: none; }
        .btn-search:hover { background-color: #0f172a; color: #fff; }
        .btn-create { background-color: #059669; color: #fff; font-weight: 700; height: 40px; padding: 0 20px; border-radius: 8px; border: none; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-create:hover { background-color: #047857; color: #fff; }
        .badge-period { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #bfdbfe; }

        /* ปุ่ม View / Edit แบบใหม่ */
        .btn-action-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background-color: #ffffff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            font-weight: 600;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 6px;
            text-decoration: none !important;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .btn-action-view:hover {
            background-color: #2563eb;
            color: #ffffff !important;
            border-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'PRICING'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Price Period Management</a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 200px;">
                    <label>Price Period (e.g. 202604-01)</label>
                    <input class="form-control-minimal" id="filter_period" type="text" placeholder="YYYYMM-DD" value="<?php echo $search_period; ?>"/>
                </div>
                <div class="filter-item" style="flex: 1.5; min-width: 200px;">
                    <label>Customer ID</label>
                    <input class="form-control-minimal" id="filter_cust" type="text" placeholder="Enter Customer Code..." value="<?php echo $search_cust; ?>"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" onclick="applySearch()">SEARCH</button>
                    <?php if ($is_authorized): ?>
                        <a href="price_period_detail.php?func=<?php echo $folder_func; ?>&mode=new" class="btn btn-create">+ NEW PRICE PERIOD</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="table-responsive">
                    <table id="period_table" class="table table-hover" style="width:100%">
                        <thead>
                            <tr style="background:#f8fafc; text-transform:uppercase; font-size:11.5px; color:#475569;">
                                <th class="text-center">Price Period</th>
                                <th class="text-center">Coil From</th>
                                <th class="text-center">Customer</th>
                                <th class="text-center">Validity Range</th>
                                <th class="text-center">Currency</th>
                                <th class="text-right">Exchange Rate</th>
                                <th class="text-right">LME Avg</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            include("dbcon_mats-new.php");
                            $params = [];
                            $where = ["PRICE_PERIOD NOT IN ('9999', '5555')"];

                            if (!empty($search_period)) {
                                $where[] = "PRICE_PERIOD LIKE :period";
                                $params[':period'] = "%$search_period%";
                            }
                            if (!empty($search_cust)) {
                                $where[] = "CSTMSPPL_ID LIKE :cust";
                                $params[':cust'] = "%$search_cust%";
                            }

                            $where_sql = implode(' AND ', $where);
                            $sql = "SELECT TOP 200 * FROM PRICMSTR1 WHERE {$where_sql} ORDER BY PRICE_PERIOD DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute($params);

                            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $p_val = htmlspecialchars($r['PRICE_PERIOD']);
                                $c_val = htmlspecialchars($r['COIL_FROM']);
                                $cu_val = htmlspecialchars($r['CSTMSPPL_ID']);
                                $d_range = date('d/m/Y', strtotime($r['LIFETIME_FROM'])).' - '.date('d/m/Y', strtotime($r['LIFETIME_TO']));
                                
                                echo "<tr>";
                                echo "<td align='center'><span class='badge-period'>{$p_val}</span></td>";
                                echo "<td align='center'><strong>{$c_val}</strong></td>";
                                echo "<td align='center'>{$cu_val}</td>";
                                echo "<td align='center'>{$d_range}</td>";
                                echo "<td align='center'>{$r['CURRENCY_ID']}</td>";
                                echo "<td align='right'>".number_format((float)$r['EXCHANGE_RATE'], 5)."</td>";
                                echo "<td align='right'>".number_format((float)$r['LME_AVERAGE'], 2)."</td>";
                                echo "<td align='center'>
                                        <a href='price_period_detail.php?func={$folder_func}&mode=view&period={$p_val}&coil={$c_val}&cust={$cu_val}' 
                                           class='btn-action-view'>
                                            View / Edit
                                        </a>
                                      </td>";
                                echo "</tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>
<?php include 'include/footer.php'; ?>
<script>
$(document).ready(function() {
    $('#period_table').DataTable({
        pageLength: 10, // ตั้งค่าเริ่มต้นเป็น 10 records
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']],
        autoWidth: false,
        columnDefs: [
            { targets: [0, 1, 2, 3, 4, 7], className: 'text-center' },
            { targets: [5, 6], className: 'text-right' }
        ]
    });
});

function applySearch() {
    var p = document.getElementById('filter_period').value;
    var c = document.getElementById('filter_cust').value;
    window.location.assign('price_period_list.php?search_period=' + encodeURIComponent(p) + '&search_cust=' + encodeURIComponent(c));
}
</script>
</body>
</html>