<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
require_once 'dbcon_mats-new.php';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// รับค่าฟิลเตอร์ค้นหาเดิมของ material_inventory_stock_mats
$search_container = isset($_GET['container']) ? trim($_GET['container']) : '';
$search_material  = isset($_GET['material']) ? trim($_GET['material']) : '';
$search_supplier  = isset($_GET['supplier']) ? trim($_GET['supplier']) : '';
$search_country   = isset($_GET['country']) ? trim($_GET['country']) : '';

$stockData = [];
try {
    // ปรับ Query โดยตัดส่วน "ยอดยกมา" (beginning_mt) ออก
    $sql = "SELECT h.header_id, h.gr_no, h.container_no, h.invoice_no, h.pack_list_no, h.description_fg, h.supplier_name, h.country_of_origin, h.receive_date,
                   
                   -- 1. ยอดรับเข้า (แปลงเป็น MT)
                   (SELECT ISNULL(SUM(d.net_weight_kg), 0) 
                    FROM RECEIVING_DETAILS d 
                    WHERE d.gr_no = h.gr_no) / 1000.0 AS in_mt,

                   -- 2. ยอดจ่ายออก (แปลงเป็น MT)
                   (SELECT ISNULL(SUM(y.net_weight_kg), 0) 
                    FROM REQUISITION_DETAIL y 
                    WHERE y.container_no = h.container_no) / 1000.0 AS out_mt

            FROM RECEIVING_HEADER h
            WHERE 1=1";
    
    $params = [];

    if (!empty($search_container)) {
        $sql .= " AND h.container_no LIKE :container";
        $params[':container'] = "%$search_container%";
    }
    if (!empty($search_material)) {
        $sql .= " AND h.description_fg LIKE :material";
        $params[':material'] = "%$search_material%";
    }
    if (!empty($search_supplier)) {
        $sql .= " AND h.supplier_name LIKE :supplier";
        $params[':supplier'] = "%$search_supplier%";
    }
    if (!empty($search_country)) {
        $sql .= " AND h.country_of_origin LIKE :country";
        $params[':country'] = "%$search_country%";
    }

    $sql .= " ORDER BY h.receive_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $in_mt      = (float)$row['in_mt'];
        $out_mt     = (float)$row['out_mt'];
        
        // คำนวณคงเหลือ: รับเข้า - จ่ายออก (ไม่มียอดยกมา)
        $balance_mt = $in_mt - $out_mt;

        $stockData[] = [
            'receive_date'      => $row['receive_date'],
            'container_no'      => $row['container_no'],
            'invoice_no'        => $row['invoice_no'],
            'pack_list_no'      => $row['pack_list_no'],
            'description_fg'    => $row['description_fg'],
            'supplier_name'     => $row['supplier_name'],
            'country_of_origin' => $row['country_of_origin'],
            'in_mt'             => $in_mt,
            'out_mt'            => $out_mt,
            'balance_mt'        => $balance_mt
        ];
    }
} catch (Exception $e) {
    // จัดการข้อผิดพลาด
}
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

        /* Form Styling Control Minimal */
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

        .btn-action-view {
            background-color: #10b981;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }

        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }        

        .btn-action-view:hover {
            background-color: #059669;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Modern Table Styles */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }

        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
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
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">📦 Stock Balance & Movement</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="Material_Information()" class="btn-tab-item active">Material Information</button>
                <button onclick="Scrap_Information()" class="btn-tab-item">Scrap Information</button>
            </div>            

            <!-- Form การค้นหาแบบเดิม แต่ปรับดีไซน์ให้อยู่ในโครงสร้าง filter-wrapper -->
            <form method="GET" action="" class="filter-wrapper">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                
                <div class="filter-item" style="flex: 1; min-width: 180px;">
                    <label for="container">Container No.</label>
                    <input class="form-control-minimal" name="container" id="container" type="text" placeholder="Enter Container No..." value="<?php echo htmlspecialchars($search_container, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()"/>
                </div>

                <div class="filter-item" style="flex: 1; min-width: 180px;">
                    <label for="material">Material / FG</label>
                    <input class="form-control-minimal" name="material" id="material" type="text" placeholder="Enter Material..." value="<?php echo htmlspecialchars($search_material, ENT_QUOTES, 'UTF-8'); ?>"/>
                </div>

                <div class="filter-item" style="flex: 1; min-width: 180px;">
                    <label for="supplier">Supplier</label>
                    <input class="form-control-minimal" name="supplier" id="supplier" type="text" placeholder="Enter Supplier..." value="<?php echo htmlspecialchars($search_supplier, ENT_QUOTES, 'UTF-8'); ?>"/>
                </div>

                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="country">Country</label>
                    <input class="form-control-minimal" name="country" id="country" type="text" placeholder="Enter Country..." value="<?php echo htmlspecialchars($search_country, ENT_QUOTES, 'UTF-8'); ?>"/>
                </div>

                <div class="filter-item">
                    <button type="submit" class="btn btn-search">
                        SEARCH
                    </button>
                </div>
            </form>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Summary of Stock Balance Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Receive Date</th>
                                        <th style="text-align: center;">Container No.</th>
                                        <th style="text-align: center;">Invoice No.</th>
                                        <th style="text-align: center;">Pack List No.</th>
                                        <th style="text-align: center;">Description FG</th>
                                        <th style="text-align: center;">Supplier</th>
                                        <th style="text-align: center;">Country</th>
                                        <th style="text-align: center;">In (MT)</th>
                                        <th style="text-align: center;">Out (MT)</th>
                                        <th style="text-align: center;">Balance (MT)</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php foreach ($stockData as $index => $row): 
                                    $receive_date = !empty($row['receive_date']) ? date('Y-m-d', strtotime($row['receive_date'])) : '-';
                                    $container_no = htmlspecialchars($row['container_no'] ?? '-', ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center"><?php echo htmlspecialchars($receive_date, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center">
                                            <a href="material_stock_detail_mats.php?func=<?php echo urlencode($folder_func); ?>&container=<?php echo urlencode($row['container_no']); ?>" class="btn-action-view">
                                                🔍 <?php echo $container_no; ?>
                                            </a>
                                        </td>
                                        <td align="center"><?php echo htmlspecialchars($row['invoice_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['pack_list_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['description_fg'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['supplier_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['country_of_origin'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center" style="font-weight:600; color:#10b981;"><?php echo number_format($row['in_mt'], 4); ?></td>
                                        <td align="center" style="font-weight:600; color:#f59e0b;"><?php echo number_format($row['out_mt'], 4); ?></td>
                                        <td align="center" style="font-weight:700; color:#ef4444;"><?php echo number_format($row['balance_mt'], 4); ?></td>
                                    </tr>
                                <?php endforeach; ?>
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
$(document).ready(function() {
    $("#user_table").DataTable({
        scrollY: "500px",
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order: [[0, 'asc']],
        buttons: []
    });
});

function Material_Information(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('material_inventory_stock_mats.php?func='+encodeURIComponent(data_fun)); 
}

function Scrap_Information(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('scrap_inventory_stock_mats.php?func='+encodeURIComponent(data_fun)); 
}


</script>
</html>