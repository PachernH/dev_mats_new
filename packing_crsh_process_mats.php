<?php
// Start session
session_start();

// Retrieve session variables
$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

// Redirect if session expired
if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_dept = strtoupper(trim($department_func));
$is_authorized = ($current_dept === 'PK' || $current_dept === 'PP' || $current_dept === 'IT');

// Tab & Search parameters
$active_tab        = isset($_GET['tab']) ? $_GET['tab'] : 'pending'; // 'pending' or 'packed'
$pack_search       = isset($_GET['pack_search']) ? htmlspecialchars(trim($_GET['pack_search']), ENT_QUOTES, 'UTF-8') : '';
$product_id_filter = isset($_GET['product_id_filter']) ? htmlspecialchars(trim($_GET['product_id_filter']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - CRSH Packaging Process</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        /* Filter Section */
        .filter-wrapper {
            display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;
            background: #ffffff; padding: 18px 24px; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 20px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label {
            font-size: 11.5px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 40px; font-size: 14px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-pack-action {
            background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 12px;
            letter-spacing: 0.3px; padding: 5px 12px; border-radius: 6px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
        }
        .btn-pack-action:hover { background-color: #1d4ed8; color: #ffffff; }

        .btn-view-action {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12px;
            letter-spacing: 0.3px; padding: 5px 12px; border-radius: 6px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
        }
        .btn-view-action:hover { background-color: #047857; color: #ffffff; }

        /* Badges */
        .badge-product {
            display: inline-block;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12px;
            padding: 3px 8px; border-radius: 5px; border: 1px solid #bfdbfe;
        }
        .badge-status-ac {
            background-color: #dcfce7; color: #15803d; font-weight: 700;
            font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .badge-status-st {
            background-color: #f1f5f9; color: #475569; font-weight: 700;
            font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block; border: 1px solid #cbd5e1;
        }

        /* Nav Tabs */
        .process-tab-bar {
            display: flex; gap: 10px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0;
        }
        .process-tab-btn {
            background: transparent; border: none; padding: 10px 20px; font-weight: 700;
            font-size: 13.5px; cursor: pointer; color: #64748b; border-bottom: 3px solid transparent;
            display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;
        }
        .process-tab-btn:hover { color: #2563eb; }
        .process-tab-btn.active {
            color: #2563eb; border-bottom-color: #2563eb; font-weight: 800;
        }

        /* Table */
        .table-custom { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        .table-custom th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        .table-custom td {
            padding: 9px 8px !important; vertical-align: middle; font-size: 12.5px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        .table-custom tbody tr:hover td { background-color: #f0f9ff !important; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A4'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="packing_crsh_process_mats.php?func=<?php echo $folder_func ?>">
                        CRSH Packaging Process
                        <?php if (!$is_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">PACKING AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Tabs Menu -->
            <div class="process-tab-bar">
                <button type="button" class="process-tab-btn <?php echo ($active_tab === 'pending') ? 'active' : ''; ?>" onclick="changeTab('pending')">
                    ⏳ 1. Pending Packing (Ready Queue)
                </button>
                <button type="button" class="process-tab-btn <?php echo ($active_tab === 'packed') ? 'active' : ''; ?>" onclick="changeTab('packed')">
                    📦 2. Packed Records (Packed History)
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 2; min-width: 250px;">
                    <label for="pack_search">Search Product No / Job Order / Sales Order / Alloy</label>
                    <input class="form-control-minimal" name="pack_search" id="pack_search" type="text" 
                           placeholder="Enter Product No, Job Order, SO No..." value="<?php echo $pack_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_packing()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="product_id_filter">Product Type</label>
                    <select class="form-control" name="product_id_filter" id="product_id_filter" onchange="search_packing()">
                        <option value="">ALL (CC / SH / NC / WR)</option>
                        <option value="CC" <?php if($product_id_filter === 'CC') echo 'selected'; ?>>CC (Circle)</option>
                        <option value="SH" <?php if($product_id_filter === 'SH') echo 'selected'; ?>>SH (Sheet)</option>
                        <option value="NC" <?php if($product_id_filter === 'NC') echo 'selected'; ?>>NC</option>
                        <option value="WR" <?php if($product_id_filter === 'WR') echo 'selected'; ?>>WR (Wrap)</option>
                    </select>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_packing()">
                        SEARCH
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <?php if ($active_tab === 'pending'): ?>
                            <!-- ========================================== -->
                            <!-- TAB 1: Pending Items (CRSH_STATUS = 'AC')  -->
                            <!-- ========================================== -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                    Ready to Pack Items (Status: AC - Ready to Pack)
                                </h4>
                            </div>
                            
                            <div class="table-responsive">
                                <table id="tbl_pending" class="table-custom">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center; width: 140px;">Product No</th>
                                            <th style="text-align: center; width: 120px;">Job Order</th>
                                            <th style="text-align: center; width: 130px;">Sales Order</th>
                                            <th style="text-align: center; width: 70px;">Type</th>
                                            <th style="text-align: center; width: 100px;">Alloy / Temper</th>
                                            <th style="text-align: center; width: 130px;">Dimensions (T x W x L)</th>
                                            <th style="text-align: left; width: 110px;">Process</th>
                                            <th style="text-align: right; width: 110px;">Actual Wt (kg)</th>
                                            <th style="text-align: right; width: 90px;">Actual Pcs</th>
                                            <th style="text-align: center; width: 80px;">Status</th>
                                            <th style="text-align: center; width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php

                                        include("dbcon_mats-new.php");

                                        $params = [];
                                        $where_clauses = [
                                            // 1. เพิ่ม 'WR' หรือเปิดรับประเภทผลิตภัณฑ์ตามที่มีในระบบ
                                            "(p.PRODUCT_ID IN ('CC', 'SH', 'NC', 'WR'))",
                                            "p.CRSH_STATUS = 'AC'",
                                            "p.JOB_ORDER <> ''",
                                            "p.SALEORDER_NO <> ''",
                                            // 2. กรองไม่ให้รายการที่ยังค้างอยู่ใน PACKPROD1 ขึ้นมาแสดง
                                            "NOT EXISTS (SELECT 1 FROM [MATS-NEWII].dbo.PACKPROD1 pk WHERE pk.PRODUCT_NO = p.PRODUCT_NO)"
                                        ];

                                        if (!empty($pack_search)) {
                                            $where_clauses[] = "(p.PRODUCT_NO LIKE :kw OR p.JOB_ORDER LIKE :kw OR p.SALEORDER_NO LIKE :kw OR p.ALLOY LIKE :kw)";
                                            $params[':kw'] = '%' . $pack_search . '%';
                                        }
                                        if (!empty($product_id_filter)) {
                                            $where_clauses[] = "p.PRODUCT_ID = :product_id";
                                            $params[':product_id'] = $product_id_filter;
                                        }

                                        $where_sql = implode(' AND ', $where_clauses);

                                        // 3. ปรับ ORDER BY หรือขยาย TOP เพื่อป้องกันข้อมูลตกหล่น
                                        $sql = "SELECT TOP 500 p.* FROM CRSHPROD1 p WHERE {$where_sql} ORDER BY p.CRSH_UPDATEDATE DESC, p.PRODUCT_NO DESC";

                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $prod_no      = htmlspecialchars(trim($row['PRODUCT_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $job_no       = htmlspecialchars(trim($row['JOB_ORDER'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                            $so_display   = htmlspecialchars(trim($row['SALEORDER_NO'] ?? '-'), ENT_QUOTES, 'UTF-8') . ' / ' . htmlspecialchars(trim($row['SALEORDER_ITEM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $product_id   = htmlspecialchars(trim($row['PRODUCT_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $alloy_temper = htmlspecialchars(trim($row['ALLOY'] ?? '') . ' ' . trim($row['TEMPER'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $dim          = number_format((float)$row['THICKNESS'], 2) . ' x ' . number_format((float)$row['WIDTH'], 0);
                                            if ((float)$row['LENGTH'] > 0) $dim .= ' x ' . number_format((float)$row['LENGTH'], 0);
                                            $process      = htmlspecialchars(trim($row['CRSH_WORKPROCESS'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                            $act_weight   = number_format((float)$row['CRSH_ACTUALWEIGHT'], 0);
                                            $act_piece    = number_format((float)$row['CRSH_ACTUALPIECE'], 0);

                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-product'>{$prod_no}</span></td>";
                                            echo "<td align='center' style='font-weight:600;'>{$job_no}</td>";
                                            echo "<td align='center'>{$so_display}</td>";
                                            echo "<td align='center'><span style='font-weight:700; color:#4338ca;'>{$product_id}</span></td>";
                                            echo "<td align='center'>{$alloy_temper}</td>";
                                            echo "<td align='center'>{$dim}</td>";
                                            echo "<td align='left' style='font-size:11.5px; color:#64748b;'>{$process}</td>";
                                            echo "<td align='right' style='font-weight:600;'>{$act_weight}</td>";
                                            echo "<td align='right'>{$act_piece}</td>";
                                            echo "<td align='center'><span class='badge-status-ac'>AC</span></td>";
                                            echo "<td align='center'>";
                                            if ($is_authorized) {
                                                echo "<a href='packing_crsh_entry_mats.php?func={$folder_func}&product_no={$prod_no}' class='btn-pack-action'>📦 PACK</a>";
                                            } else {
                                                echo "<span style='color:#94a3b8; font-size:12px;'>VIEW ONLY</span>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <!-- ========================================== -->
                            <!-- TAB 2: Packed Items (Fetched from PACKPROD1) -->
                            <!-- ========================================== -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                    Packed Records History
                                </h4>
                            </div>

                            <div class="table-responsive">
                                <table id="tbl_packed" class="table-custom">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center; width: 140px;">Product No</th>
                                            <th style="text-align: center; width: 110px;">Pack Date</th>
                                            <th style="text-align: center; width: 120px;">Job Order</th>
                                            <th style="text-align: center; width: 130px;">Sales Order</th>
                                            <th style="text-align: center; width: 140px;">Pallet Code</th>
                                            <th style="text-align: center; width: 60px;">Stack</th>
                                            <th style="text-align: right; width: 100px;">Net Wt (kg)</th>
                                            <th style="text-align: right; width: 80px;">Net Pcs</th>
                                            <th style="text-align: center; width: 90px;">Operator</th>
                                            <th style="text-align: center; width: 70px;">Status</th>
                                            <th style="text-align: center; width: 120px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        include("dbcon_mats-new.php");

                                        $params = [];

                                        $where_clauses = ["pk.PACK_STATUS = 'ST'"];

                                        if (!empty($pack_search)) {
                                            $where_clauses[] = "(pk.PRODUCT_NO LIKE :kw OR pk.JOB_ORDER LIKE :kw OR pk.SALEORDER_NO LIKE :kw OR pk.PACK_MATERIALCODE LIKE :kw)";
                                            $params[':kw'] = '%' . $pack_search . '%';
                                        }
                                        if (!empty($product_id_filter)) {
                                            $where_clauses[] = "pk.PRODUCT_ID = :product_id";
                                            $params[':product_id'] = $product_id_filter;
                                        }

                                        $where_sql = implode(' AND ', $where_clauses);

                                        // Fetch packed items from PACKPROD1
                                        $sql = "SELECT TOP 200 
                                                       pk.PRODUCT_NO,
                                                       pk.JOB_ORDER,
                                                       pk.SALEORDER_NO,
                                                       pk.SALEORDER_ITEM,
                                                       pk.PACK_MATERIALCODE,
                                                       pk.PACK_STACKNUMBER,
                                                       pk.PACK_NETWEIGHT,
                                                       pk.PACK_NETPIECE,
                                                       pk.PACK_OPERATOR1,
                                                       pk.PACK_DATE,
                                                       pk.PACK_STATUS
                                                FROM [MATS-NEWII].dbo.PACKPROD1 pk
                                                WHERE {$where_sql}
                                                ORDER BY pk.PACK_DATE DESC";

                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $prod_no    = htmlspecialchars(trim($row['PRODUCT_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $job_no     = htmlspecialchars(trim($row['JOB_ORDER'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                            $so_display = htmlspecialchars(trim($row['SALEORDER_NO'] ?? '-'), ENT_QUOTES, 'UTF-8') . ' / ' . htmlspecialchars(trim($row['SALEORDER_ITEM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                            $pack_code  = htmlspecialchars(trim($row['PACK_MATERIALCODE'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                            $stack_num  = (int)($row['PACK_STACKNUMBER'] ?? 1);
                                            $net_wt     = number_format((float)($row['PACK_NETWEIGHT'] ?? 0), 0);
                                            $net_pc     = number_format((float)($row['PACK_NETPIECE'] ?? 0), 0);
                                            $operator   = htmlspecialchars(trim($row['PACK_OPERATOR1'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                            $pack_date  = !empty($row['PACK_DATE']) ? date('d/m/Y H:i', strtotime($row['PACK_DATE'])) : '-';
                                            $status     = htmlspecialchars(trim($row['PACK_STATUS'] ?? 'ST'), ENT_QUOTES, 'UTF-8');

                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-product' style='background:#f1f5f9; color:#0f172a; border-color:#cbd5e1;'>{$prod_no}</span></td>";
                                            echo "<td align='center' style='font-size:11.5px; color:#64748b;'>{$pack_date}</td>";
                                            echo "<td align='center' style='font-weight:600;'>{$job_no}</td>";
                                            echo "<td align='center'>{$so_display}</td>";
                                            echo "<td align='center' style='font-weight:700; color:#1e40af;'>{$pack_code}</td>";
                                            echo "<td align='center'>{$stack_num}</td>";
                                            echo "<td align='right' style='font-weight:700; color:#059669;'>{$net_wt}</td>";
                                            echo "<td align='right'>{$net_pc}</td>";
                                            echo "<td align='center'>{$operator}</td>";
                                            echo "<td align='center'><span class='badge-status-st'>{$status}</span></td>";
                                            echo "<td align='center'>";
                                            // View/Edit button redirects to entry page to inspect fields or delete based on permissions
                                            echo "<a href='packing_crsh_entry_mats.php?func={$folder_func}&product_no={$prod_no}' class='btn-view-action'>View / Edit</a>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <input type="hidden" id="current_tab" value="<?php echo $active_tab; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var tableId = '<?php echo ($active_tab === "pending") ? "#tbl_pending" : "#tbl_packed"; ?>';
    var table = $(tableId).DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'desc']]
    });

    $(window).on('resize', function () {
        table.columns.adjust();
    });
    setTimeout(function() {
        table.columns.adjust();
    }, 200);
});

function changeTab(tabName) {
    var func = document.getElementById("func").value;
    var search = document.getElementById("pack_search").value;
    var ptype = document.getElementById("product_id_filter").value;
    
    var url = 'packing_crsh_process_mats.php?func=' + encodeURIComponent(func) +
              '&tab=' + encodeURIComponent(tabName) +
              '&pack_search=' + encodeURIComponent(search) +
              '&product_id_filter=' + encodeURIComponent(ptype);
    window.location.assign(url);
}

function search_packing() {
    var func = document.getElementById("func").value;
    var tab = document.getElementById("current_tab").value;
    var search = document.getElementById("pack_search").value;
    var ptype = document.getElementById("product_id_filter").value;
    
    var url = 'packing_crsh_process_mats.php?func=' + encodeURIComponent(func) +
              '&tab=' + encodeURIComponent(tab) +
              '&pack_search=' + encodeURIComponent(search) +
              '&product_id_filter=' + encodeURIComponent(ptype);
    window.location.assign(url);
}
</script>
</html>