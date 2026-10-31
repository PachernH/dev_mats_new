<?php
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
require_once 'dbcon_mats-new.php';

$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to   = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search_container = isset($_GET['container']) ? trim($_GET['container']) : '';
$search_supplier  = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$is_export        = isset($_GET['export']) && $_GET['export'] === 'excel';

$reportData = [];
$sum_pcs = 0;
$sum_net = 0;

try {
    $sql = "SELECT h.receive_date, h.container_no, h.invoice_no, h.pack_list_no, h.po_no, h.description_fg, h.supplier_name, h.country_of_origin, h.receive_status,
                   d.packing_no, d.cast_no, d.piece_count, d.net_weight_kg, d.status AS detail_status
            FROM RECEIVING_HEADER h
            JOIN RECEIVING_DETAILS d ON h.gr_no = d.gr_no
            WHERE h.receive_date >= :date_from AND h.receive_date <= :date_to";

    $params = [
        ':date_from' => $date_from . ' 00:00:00', 
        ':date_to'   => $date_to . ' 23:59:59'
    ];

    if (!empty($search_container)) {
        $sql .= " AND h.container_no LIKE :container";
        $params[':container'] = "%$search_container%";
    }
    if (!empty($search_supplier)) {
        $sql .= " AND h.supplier_name LIKE :supplier";
        $params[':supplier'] = "%$search_supplier%";
    }

    $sql .= " ORDER BY h.receive_date DESC, h.container_no ASC, d.packing_no ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reportData as $row) {
        $sum_pcs += $row['piece_count'];
        $sum_net += $row['net_weight_kg'];
    }

    // Export Excel Handler
    if ($is_export) {
        header("Content-Type: application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=Material_Receiving_Report_" . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo '<table border="1">';
        echo '<tr style="background-color: #1e293b; color: #ffffff; font-weight: bold;">';
        echo '<th>วันที่รับเข้า</th><th>CONTAINER NO.</th><th>INVOICE NO.</th><th>PO NO.</th><th>PACKING NO.</th><th>CAST NO.</th><th>DESCRIPTION FG</th><th>SUPPLIER</th><th>COUNTRY</th><th>จำนวนชิ้น (Pcs)</th><th>Net Weight (KG)</th><th>สถานะ</th>';
        echo '</tr>';
        
        foreach ($reportData as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['receive_date']) . '</td>';
            echo '<td>' . htmlspecialchars($row['container_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['invoice_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['po_no'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($row['packing_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['cast_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['description_fg']) . '</td>';
            echo '<td>' . htmlspecialchars($row['supplier_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['country_of_origin']) . '</td>';
            echo '<td>' . $row['piece_count'] . '</td>';
            echo '<td>' . $row['net_weight_kg'] . '</td>';
            echo '<td>' . htmlspecialchars($row['detail_status'] ?? 'In Stock') . '</td>';
            echo '</tr>';
        }
        
        echo '<tr style="font-weight: bold; background-color: #f1f5f9;">';
        echo '<td colspan="9" style="text-align: right;">รวมทั้งสิ้น (Total):</td>';
        echo '<td>' . $sum_pcs . '</td>';
        echo '<td>' . $sum_net . '</td>';
        echo '<td></td>';
        echo '</tr>';
        echo '</table>';
        exit();
    }

} catch (Exception $e) {}

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-wrapper { background: #ffffff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; width: 100%; }
        .form-control-minimal:focus { border-color: #1e293b !important; box-shadow: none; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13.5px; height: 40px; padding: 0 28px; border-radius: 8px; border: none; transition: all 0.2s; width: 100%; }
        .btn-search:hover { background-color: #0f172a; transform: translateY(-1px); }
        .btn-excel { background-color: #10b981; color: #ffffff; font-weight: 600; font-size: 13.5px; height: 38px; padding: 0 18px; border-radius: 8px; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-excel:hover { background-color: #059669; color: #ffffff; transform: translateY(-1px); }
        .custom-table-header th { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; color: #ffffff !important; font-weight: 700 !important; font-size: 13px; padding: 14px 8px !important; vertical-align: middle; text-align: center !important; border-bottom: none !important; }
        .table-footer-summary { background-color: #f1f5f9; font-weight: bold; color: #0f172a; }
        .badge-status { background-color: #e2e8f0; color: #1e293b; font-weight: 600; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A5';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>
    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" href="#">Report receive material (Detail Packing No.)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        <div class="content">
            <div class="container-fluid">
                
                <!-- ฟอร์มค้นหา จัดสัดส่วน Grid ใหม่ให้เป็นระเบียบ -->
                <form method="GET" action="" class="filter-wrapper">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2 col-sm-6">
                            <label class="small fw-bold text-muted mb-1">Start Date</label>
                            <input type="date" class="form-control form-control-minimal" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small fw-bold text-muted mb-1">End Date</label>
                            <input type="date" class="form-control form-control-minimal" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="small fw-bold text-muted mb-1">Container No.</label>
                            <input type="text" class="form-control form-control-minimal" name="container" value="<?php echo htmlspecialchars($search_container); ?>" placeholder="ระบุเลขตู้...">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="small fw-bold text-muted mb-1">Supplier</label>
                            <input type="text" class="form-control form-control-minimal" name="supplier" value="<?php echo htmlspecialchars($search_supplier); ?>" placeholder="ระบุ Supplier...">
                        </div>
                        <div class="col-md-2 col-sm-12">
                            <button type="submit" class="btn btn-search">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                            </button>
                        </div>
                    </div>
                </form>

                <!-- ตารางแสดงรายงาน พร้อมย้ายปุ่ม Export Excel มาไว้ที่หัวข้อขวาบน -->
                <div class="dashboard-card">
                    <div class="card-header-flex">
                        <h4 class="card-title-sub text-primary m-0">
                            <i class="fa-solid fa-file-lines me-2"></i> List of received materials (Detail Packing No.) from <?php echo htmlspecialchars($date_from); ?> to <?php echo htmlspecialchars($date_to); ?>
                        </h4>
                        <a href="?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&container=<?php echo urlencode($search_container); ?>&supplier=<?php echo urlencode($search_supplier); ?>&export=excel" class="btn btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Export Excel
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle text-center mb-0">
                            <thead>
                                <tr class="custom-table-header">
                                    <th>Date Receive</th>
                                    <th>CONTAINER NO.</th>
                                    <th>INVOICE NO.</th>
                                    <th>PO NO.</th>
                                    <th>PACKING NO.</th>
                                    <th>CAST NO.</th>
                                    <th>DESCRIPTION FG</th>
                                    <th>SUPPLIER</th>
                                    <th>COUNTRY</th>
                                    <th>Total Pieces (Pcs)</th>
                                    <th>Net Weight (KG)</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reportData) > 0): ?>
                                    <?php foreach ($reportData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['receive_date']); ?></td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['container_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['po_no'] ?? '-'); ?></td>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['packing_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['cast_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['description_fg']); ?></td>
                                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['country_of_origin']); ?></td>
                                            <td><?php echo number_format($row['piece_count']); ?></td>
                                            <td class="fw-bold text-success"><?php echo number_format($row['net_weight_kg'], 2); ?></td>
                                            <td><span class="badge-status"><?php echo htmlspecialchars($row['detail_status'] ?? 'In Stock'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- แถวสรุปผลรวม -->
                                    <tr class="table-footer-summary">
                                        <td colspan="9" class="text-end">Total:</td>
                                        <td><?php echo number_format($sum_pcs); ?></td>
                                        <td class="text-success"><?php echo number_format($sum_net, 2); ?></td>
                                        <td></td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="12" class="text-muted py-4">No data found for the selected date range</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
</html>