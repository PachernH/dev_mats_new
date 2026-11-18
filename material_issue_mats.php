<?php
// เริ่ม session ก่อน ANY output
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
require_once 'dbcon_mats-new.php';

$success_msg = '';
$error_msg = '';

// 1. จัดการกระบวนการบันทึกการเบิกจ่าย
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action             = $_POST['action'];
    $department         = trim($_POST['department'] ?? '');
    $ref_no             = trim($_POST['ref_no'] ?? '');
    $custom_req_date    = trim($_POST['req_date'] ?? date('Y-m-d'));
    $remarks            = trim($_POST['remarks'] ?? '');
    $issued_by          = $_SESSION['ID'] ?? 'System';

    $detail_status = ($department === 'Production') ? 'Issued' : 'use';

    if (empty($department)) {
        $error_msg = 'กรุณาระบุแผนกที่ขอเบิกใช้งาน';
    } else {
        try {
            $conn->beginTransaction();

            $date_prefix = 'REQ' . date('ymd', strtotime($custom_req_date));
            $sql_running = "SELECT TOP 1 req_no FROM requisition_header WHERE req_no LIKE :prefix ORDER BY req_id DESC";
            $stmt_run = $conn->prepare($sql_running);
            $stmt_run->execute([':prefix' => $date_prefix . '-%']);
            $last_req = $stmt_run->fetch(PDO::FETCH_ASSOC);

            if ($last_req && !empty($last_req['req_no'])) {
                $parts = explode('-', $last_req['req_no']);
                $last_running_num = isset($parts[1]) ? intval($parts[1]) : 0;
                $next_running_num = $last_running_num + 1;
            } else {
                $next_running_num = 1;
            }

            $req_no = $date_prefix . '-' . str_pad($next_running_num, 3, '0', STR_PAD_LEFT);

            if ($action === 'issue_container') {
                $selected_containers = $_POST['selected_containers'] ?? [];
                if (empty($selected_containers)) {
                    throw new Exception('กรุณาเลือกตู้ Container อย่างน้อย 1 ตู้เพื่อทำการตัดจ่าย');
                }

                foreach ($selected_containers as $container_no) {
                    $sql_get_items = "SELECT d.receive_id, d.packing_no, d.net_weight_kg, d.piece_count, d.material_id, h.country_of_origin 
                                      FROM receiving_details d
                                      JOIN RECEIVING_HEADER h ON d.gr_no = h.gr_no
                                      WHERE h.container_no = ? 
                                      AND (d.status IS NULL OR LTRIM(RTRIM(d.status)) = '' OR d.status NOT IN ('Issued', 'use'))";
                    $stmt_items = $conn->prepare($sql_get_items);
                    $stmt_items->execute([$container_no]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                    if (count($items) > 0) {
                        $sql_head = "INSERT INTO requisition_header (req_no, req_date, department, ref_no, issued_by, status, remarks) 
                                     VALUES (:req_no, :req_date, :department, :ref_no, :issued_by, :status, :remarks)";
                        $conn->prepare($sql_head)->execute([
                            ':req_no'     => $req_no,
                            ':req_date'   => $custom_req_date . ' ' . date('H:i:s'),
                            ':department' => $department,
                            ':ref_no'     => $ref_no,
                            ':issued_by'  => $issued_by,
                            ':status'     => $detail_status,
                            ':remarks'    => $remarks . ' [จ่ายยกตู้: ' . $container_no . ']'
                        ]);

                        foreach ($items as $item) {
                            $receive_id  = $item['receive_id'];
                            $packing_no  = $item['packing_no'];
                            $net_weight  = $item['net_weight_kg'];
                            $piece_count = $item['piece_count'];
                            $country     = $item['country_of_origin'];

                            $sql_req_det = "INSERT INTO requisition_detail (req_no, container_no, packing_no, net_weight_kg, piece_count, country_of_origin, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $conn->prepare($sql_req_det)->execute([$req_no, $container_no, $packing_no, $net_weight, $piece_count, $country, $detail_status]);

                            $sql_upd = "UPDATE receiving_details SET status = ? WHERE receive_id = ?";
                            $conn->prepare($sql_upd)->execute([$detail_status, $receive_id]);
                        }
                    }
                }
                $success_msg = "บันทึกการตัดจ่ายยกตู้สำเร็จ! เลขที่ใบเบิก: " . $req_no;

            } elseif ($action === 'issue_packing') {
                $selected_packings = $_POST['selected_packings'] ?? [];
                if (empty($selected_packings)) {
                    throw new Exception('กรุณาเลือก Bundle No. อย่างน้อย 1 รายการเพื่อทำการตัดจ่าย');
                }

                $sql_head = "INSERT INTO requisition_header (req_no, req_date, department, ref_no, issued_by, status, remarks) 
                             VALUES (:req_no, :req_date, :department, :ref_no, :issued_by, :status, :remarks)";
                $conn->prepare($sql_head)->execute([
                    ':req_no'     => $req_no,
                    ':req_date'   => $custom_req_date . ' ' . date('H:i:s'),
                    ':department' => $department,
                    ':ref_no'     => $ref_no,
                    ':issued_by'  => $issued_by,
                    ':status'     => $detail_status,
                    ':remarks'    => $remarks . ' [จ่ายแยกราย Bundle]'
                ]);

                foreach ($selected_packings as $receive_id) {
                    $sql_get_item = "SELECT d.receive_id, d.packing_no, d.net_weight_kg, d.piece_count, h.container_no, h.country_of_origin 
                                     FROM receiving_details d
                                     JOIN RECEIVING_HEADER h ON d.gr_no = h.gr_no
                                     WHERE d.receive_id = ? 
                                     AND (d.status IS NULL OR LTRIM(RTRIM(d.status)) = '' OR d.status NOT IN ('Issued', 'use'))";
                    $stmt_item = $conn->prepare($sql_get_item);
                    $stmt_item->execute([$receive_id]);
                    $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

                    if ($item) {
                        $container_no = $item['container_no'];
                        $packing_no   = $item['packing_no'];
                        $net_weight   = $item['net_weight_kg'];
                        $piece_count  = $item['piece_count'];
                        $country      = $item['country_of_origin'];

                        $sql_req_det = "INSERT INTO requisition_detail (req_no, container_no, packing_no, net_weight_kg, piece_count, country_of_origin, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $conn->prepare($sql_req_det)->execute([$req_no, $container_no, $packing_no, $net_weight, $piece_count, $country, $detail_status]);

                        $sql_upd = "UPDATE receiving_details SET status = ? WHERE receive_id = ?";
                        $conn->prepare($sql_upd)->execute([$detail_status, $receive_id]);
                    }
                }
                $success_msg = "บันทึกการตัดจ่ายราย Bundle สำเร็จ! เลขที่ใบเบิก: " . $req_no;
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// 2. ดึงข้อมูล
$search_container = isset($_GET['container']) ? trim($_GET['container']) : '';
$selected_container_packing = isset($_GET['sel_container']) ? trim($_GET['sel_container']) : '';

$containerList = [];
$packingList = [];
$distinctContainers = [];

try {
    // 2.1 ตู้สำหรับแท็บยกตู้
    $sql_container = "SELECT h.container_no, h.invoice_no, h.supplier_name, h.country_of_origin, h.description_fg, h.receive_date, h.receive_status,
                             COUNT(d.receive_id) AS total_packing_count,
                             SUM(ISNULL(d.piece_count, 0)) AS total_piece_count,
                             SUM(ISNULL(d.net_weight_kg, 0)) AS total_net_weight
                      FROM RECEIVING_HEADER h
                      JOIN RECEIVING_DETAILS d ON h.gr_no = d.gr_no
                      WHERE (h.receive_status = 'CL' OR h.receive_status = 'cl')
                      AND (d.status IS NULL OR LTRIM(RTRIM(d.status)) = '' OR d.status NOT IN ('Issued', 'use'))";
    
    $params = [];
    if (!empty($search_container)) {
        $sql_container .= " AND h.container_no LIKE :container";
        $params[':container'] = "%$search_container%";
    }
    $sql_container .= " GROUP BY h.container_no, h.invoice_no, h.supplier_name, h.country_of_origin, h.description_fg, h.receive_date, h.receive_status
                        HAVING SUM(ISNULL(d.net_weight_kg, 0)) > 0 
                        ORDER BY h.receive_date DESC";
    
    $stmt_c = $conn->prepare($sql_container);
    $stmt_c->execute($params);
    $containerList = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

    // 2.2 ดึงรายชื่อตู้ทั้งหมดที่มี Packing ค้างจ่าย
    $sql_dist_c = "SELECT DISTINCT h.container_no 
                   FROM RECEIVING_HEADER h 
                   JOIN RECEIVING_DETAILS d ON h.gr_no = d.gr_no 
                   WHERE (h.receive_status = 'CL' OR h.receive_status = 'cl')
                   AND (d.status IS NULL OR LTRIM(RTRIM(d.status)) = '' OR d.status NOT IN ('Issued', 'use'))
                   ORDER BY h.container_no ASC";
    $stmt_dc = $conn->prepare($sql_dist_c);
    $stmt_dc->execute();
    $distinctContainers = $stmt_dc->fetchAll(PDO::FETCH_COLUMN);

    // 2.3 รายการ Packing
    $sql_packing = "SELECT d.receive_id, h.container_no, h.invoice_no, h.supplier_name, h.country_of_origin, h.description_fg, d.packing_no, d.cast_no, d.piece_count, d.net_weight_kg, h.receive_date
                    FROM receiving_details d
                    JOIN RECEIVING_HEADER h ON d.gr_no = h.gr_no
                    WHERE (h.receive_status = 'CL' OR h.receive_status = 'cl')
                    AND (d.status IS NULL OR LTRIM(RTRIM(d.status)) = '' OR d.status NOT IN ('Issued', 'use'))
                    AND d.net_weight_kg > 0";
    
    $params_p = [];
    if (!empty($selected_container_packing)) {
        $sql_packing .= " AND h.container_no = :sel_cont";
        $params_p[':sel_cont'] = $selected_container_packing;
    } else {
        $sql_packing .= " AND 1 = 0";
    }
    $sql_packing .= " ORDER BY d.packing_no ASC";

    $stmt_p = $conn->prepare($sql_packing);
    $stmt_p->execute($params_p);
    $packingList = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_msg = "Query Error: " . $e->getMessage();
}

$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Material Issuance System</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; }
        .btn-issue { background-color: #059669; color: #ffffff; font-weight: 700; padding: 12px 28px; border-radius: 8px; border: none; font-size: 15px; transition: all 0.2s; }
        .btn-issue:hover { background-color: #047857; color: #fff; }
        .custom-table-header th { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; color: #ffffff !important; font-weight: 700 !important; font-size: 13px; padding: 12px 8px !important; vertical-align: middle; text-align: center !important; border-bottom: none !important; }
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-cl { background-color: #dbeafe; color: #1e40af; font-weight: 700; padding: 4px 8px; border-radius: 4px; }
        
        .nav-tabs { border-bottom: 2px solid #e2e8f0; margin-bottom: 0; }
        .nav-tabs .nav-link { font-weight: 600; color: #64748b; border: 1px solid transparent; padding: 12px 20px; border-radius: 8px 8px 0 0; background: #f1f5f9; margin-right: 4px; cursor: pointer; }
        .nav-tabs .nav-link.active { color: #1e293b; background-color: #ffffff; border-color: #e2e8f0 #e2e8f0 #ffffff #e2e8f0; border-bottom: 3px solid #059669; }
        
        .tab-content { background: #ffffff; border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 12px 12px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block !important; }
        .container-select-box { background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; align-items: center; gap: 16px; }
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
                    <a class="navbar-brand" href="#">Material Issuance System (Container &  Bundle Issue)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        <div class="content">
            <div class="container-fluid">

                <!-- แจ้งเตือนสถานะ -->
                <?php if (!empty($success_msg)): ?>
                    <div class="alert-box alert-success"><i class="fa-solid fa-circle-check me-2"></i> <?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="alert-box alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error_msg; ?></div>
                <?php endif; ?>

                <!-- ฟอร์มค้นหา -->
                <form method="GET" action="" class="filter-wrapper shadow-sm">
                    <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">
                    <input type="hidden" name="tab" id="search_active_tab" value="<?php echo htmlspecialchars($_GET['tab'] ?? 'container'); ?>">
                    <div class="filter-item">
                        <label class="small fw-bold text-muted">Search Container No. (Status CL)</label>
                        <input type="text" class="form-control form-control-minimal" name="container" value="<?php echo htmlspecialchars($search_container); ?>" placeholder="ระบุเลขตู้...">
                    </div>
                    <div class="filter-item">
                        <button type="submit" class="btn btn-search"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button>
                    </div>
                </form>

                <!-- ข้อมูลหัวเอกสารใบเบิก (ใช้ร่วมกัน) -->
                <div class="dashboard-card shadow-sm mb-3">
                    <h4 class="card-title-sub text-primary mb-3"><i class="fa-solid fa-file-invoice me-2"></i> Disbursement Documents Header</h4>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="small fw-bold text-muted mb-1">Department (แผนกที่ขอเบิก) *</label>
                            <select class="form-control" name="department" id="global_department" required>
                                <option value="" disabled selected>-- เลือกแผนก --</option>
                                <option value="Production">Production</option>
                                <option value="NSA">NSA : Nikkei Siam Aluminium Limited</option>
                                <option value="VRPK">VRPK : Varopakorn Public Co.,Ltd.</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="small fw-bold text-muted mb-1">Ref No (เลขที่อ้างอิง)</label>
                            <input type="text" class="form-control" name="ref_no" id="global_ref" placeholder="ระบุเลขที่อ้างอิงเอกสาร...">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="small fw-bold text-muted mb-1">Issue Date (วันที่ตัดจ่าย) *</label>
                            <input type="date" class="form-control" name="req_date" id="global_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="small fw-bold text-muted mb-1">Remarks (หมายเหตุ)</label>
                            <input type="text" class="form-control" name="remarks" id="global_remarks" placeholder="ระบุเหตุผลหรือหมายเหตุเพิ่มเติม...">
                        </div>
                    </div>
                </div>

                <!-- แท็บเลือกรูปแบบการตัดจ่าย (UI Tabs) -->
                <ul class="nav nav-tabs" id="issueTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="container-tab" type="button" onclick="switchTab('container')">
                            <i class="fa-solid fa-truck-container me-1"></i> จ่ายยกตู้ (Full Container)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="packing-tab" type="button" onclick="switchTab('packing')">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> จ่ายราย Bundle No. (By Bundle)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="issueTabContent">
                    
                    <!-- TAB 1: จ่ายยกตู้ -->
                    <div class="tab-pane" id="container-pane">
                        <form method="POST" action="" id="formContainer">
                            <input type="hidden" name="action" value="issue_container">
                            <input type="hidden" name="department" id="h_dept">
                            <input type="hidden" name="ref_no" id="h_ref">
                            <input type="hidden" name="req_date" id="h_date">
                            <input type="hidden" name="remarks" id="h_remarks">

                            <h5 class="text-dark mb-3">Select Containers to Issue (Status: CL)</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle text-center mb-0">
                                    <thead>
                                        <tr class="custom-table-header">
                                            <th style="width: 50px;"><input type="checkbox" id="selectAllContainer"></th>
                                            <th>Date Receive</th>
                                            <th>Status</th>
                                            <th>CONTAINER NO.</th>
                                            <th>INVOICE NO.</th>
                                            <th>SUPPLIER</th>
                                            <th>COUNTRY</th>
                                            <th>DESCRIPTION FG</th>
                                            <th>Lot/Bundle</th>
                                            <th>Total Pieces (Pcs)</th>
                                            <th>Net Weight Total (KG)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($containerList) > 0): ?>
                                            <?php foreach ($containerList as $row): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="selected_containers[]" value="<?php echo htmlspecialchars($row['container_no']); ?>"></td>
                                                    <td><?php echo htmlspecialchars($row['receive_date']); ?></td>
                                                    <td><span class="badge-cl"><?php echo htmlspecialchars($row['receive_status'] ?? '-'); ?></span></td>
                                                    <td class="fw-bold text-primary">
                                                        <a href="material_issue_stock_detail_mats.php?func=<?php echo urlencode($folder_func); ?>&container=<?php echo urlencode($row['container_no']); ?>">
                                                            <?php echo htmlspecialchars($row['container_no']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['country_of_origin']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['description_fg']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo number_format($row['total_packing_count']); ?> Bundle</span></td>
                                                    <td><?php echo number_format($row['total_piece_count']); ?></td>
                                                    <td class="fw-bold text-success"><?php echo number_format($row['total_net_weight'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="11" class="text-muted py-4">ไม่พบตู้ Container สถานะ CL ที่มีสต็อกคงเหลือในระบบขณะนี้</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-issue" onclick="syncHeader(); return confirm('ยืนยันการตัดจ่ายแบบ [ยกตู้]?')">
                                    <i class="fa-solid fa-check-to-slot me-2"></i> Confirm Full Container Issue
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB 2: จ่ายราย Packing No. -->
                    <div class="tab-pane" id="packing-pane">
                        <form method="GET" action="" class="container-select-box">
                            <input type="hidden" name="func" value="<?php echo htmlspecialchars($folder_func); ?>">
                            <input type="hidden" name="tab" value="packing">
                            <div style="flex-grow: 1;">
                                <label class="small fw-bold text-muted mb-1">Select Container No. to view Bundle:</label>
                                <select class="form-control" name="sel_container" onchange="this.form.submit()">
                                    <option value="" disabled selected>-- กรุณาเลือกเลขตู้ Container --</option>
                                    <?php foreach ($distinctContainers as $cont): ?>
                                        <option value="<?php echo htmlspecialchars($cont); ?>" <?php echo ($selected_container_packing === $cont) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cont); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <form method="POST" action="" id="formPacking">
                            <input type="hidden" name="action" value="issue_packing">
                            <input type="hidden" name="department" id="p_dept">
                            <input type="hidden" name="ref_no" id="p_ref">
                            <input type="hidden" name="req_date" id="p_date">
                            <input type="hidden" name="remarks" id="p_remarks">

                            <h5 class="text-dark mb-3">Select Specific Bundle No. from Container: <span class="text-primary"><?php echo htmlspecialchars($selected_container_packing ?: '- เลือกตู้ด้านบน -'); ?></span></h5>
                            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-striped table-bordered align-middle text-center mb-0">
                                    <thead>
                                        <tr class="custom-table-header">
                                            <th style="width: 50px;"><input type="checkbox" id="selectAllPacking"></th>
                                            <th>Date Receive</th>
                                            <th>CONTAINER NO.</th>
                                            <th>BUNDLE NO.</th>
                                            <th>CAST NO.</th>
                                            <th>SUPPLIER</th>
                                            <th>COUNTRY</th>
                                            <th>DESCRIPTION FG</th>
                                            <th>Pieces (Pcs)</th>
                                            <th>Net Weight (KG)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($selected_container_packing) && count($packingList) > 0): ?>
                                            <?php foreach ($packingList as $p): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="selected_packings[]" value="<?php echo $p['receive_id']; ?>"></td>
                                                    <td><?php echo htmlspecialchars($p['receive_date']); ?></td>
                                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($p['container_no']); ?></td>
                                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['packing_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($p['cast_no']); ?></td>
                                                    <td><?php echo htmlspecialchars($p['supplier_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($p['country_of_origin']); ?></td>
                                                    <td><?php echo htmlspecialchars($p['description_fg']); ?></td>
                                                    <td><?php echo number_format($p['piece_count']); ?></td>
                                                    <td class="fw-bold text-success"><?php echo number_format($p['net_weight_kg'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="10" class="text-muted py-4"><?php echo empty($selected_container_packing) ? 'กรุณาเลือกตู้ Container ด้านบนก่อน' : 'ไม่พบรายการ Bundle No. คงเหลือในตู้นี้'; ?></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn btn-issue" onclick="syncHeader(); return confirm('ยืนยันการตัดจ่ายแบบ [เลือกราย Bundle]?')">
                                    <i class="fa-solid fa-check-to-slot me-2"></i> Confirm Selected Bundle Issue
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<script>
    // ตรวจสอบ Tab ปัจจุบันจาก URL Parameter หรือค่าเริ่มต้น
    let urlParams = new URLSearchParams(window.location.search);
    let activeTab = urlParams.get('tab') || 'container';

    function switchTab(tabName) {
        document.getElementById('container-tab').classList.remove('active');
        document.getElementById('packing-tab').classList.remove('active');
        document.getElementById('container-pane').classList.remove('active');
        document.getElementById('packing-pane').classList.remove('active');

        // บันทึกค่า tab ลงใน input ซ่อนเพื่อส่งต่อไปยังฟอร์มค้นหา
        document.getElementById('search_active_tab').value = tabName;

        if (tabName === 'container') {
            document.getElementById('container-tab').classList.add('active');
            document.getElementById('container-pane').classList.add('active');
        } else {
            document.getElementById('packing-tab').classList.add('active');
            document.getElementById('packing-pane').classList.add('active');
        }
    }

    // เปิด Tab ตามค่าที่ค้างอยู่ตอนโหลดหน้าเว็บ
    switchTab(activeTab);

    document.getElementById('selectAllContainer').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_containers[]"]');
        for (var cb of checkboxes) { cb.checked = this.checked; }
    });

    document.getElementById('selectAllPacking').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_packings[]"]');
        for (var cb of checkboxes) { cb.checked = this.checked; }
    });

    function syncHeader() {
        let dept = document.getElementById('global_department').value;
        let ref = document.getElementById('global_ref').value;
        let date = document.getElementById('global_date').value;
        let remarks = document.getElementById('global_remarks').value;

        document.getElementById('h_dept').value = dept;
        document.getElementById('h_ref').value = ref;
        document.getElementById('h_date').value = date;
        document.getElementById('h_remarks').value = remarks;

        document.getElementById('p_dept').value = dept;
        document.getElementById('p_ref').value = ref;
        document.getElementById('p_date').value = date;
        document.getElementById('p_remarks').value = remarks;
    }
</script>
</body>
<?php include 'include/footer.php';?>
</html>