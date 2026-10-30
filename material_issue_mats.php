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

// 1. จัดการกระบวนการบันทึกการเบิกจ่ายยกตู้ (Full Container Issue POST Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue_container') {
    $department         = trim($_POST['department'] ?? '');
    $ref_no             = trim($_POST['ref_no'] ?? '');
    $custom_req_date    = trim($_POST['req_date'] ?? date('Y-m-d'));
    $remarks            = trim($_POST['remarks'] ?? '');
    $issued_by          = $_SESSION['ID'] ?? 'System';
    $selected_containers = $_POST['selected_containers'] ?? []; // Array ของ container_no ที่เลือก

    if (empty($department)) {
        $error_msg = 'กรุณาระบุแผนกที่ขอเบิกใช้งาน';
    } elseif (empty($selected_containers)) {
        $error_msg = 'กรุณาเลือกตู้ Container อย่างน้อย 1 ตู้เพื่อทำการตัดจ่ายยกตู้';
    } else {
        try {
            $conn->beginTransaction();

            // กำหนดรูปแบบ Prefix ตามวันที่ที่เลือก เช่น REQ261029
            $date_prefix = 'REQ' . date('ymd', strtotime($custom_req_date));

            // ค้นหาเลข Running Number ล่าสุดจากตาราง REQUISITION_HEADER ของวันนี้
            $sql_running = "SELECT TOP 1 req_no FROM requisition_header WHERE req_no LIKE :prefix ORDER BY req_id DESC";
            $stmt_run = $conn->prepare($sql_running);
            $stmt_run->execute([':prefix' => $date_prefix . '-%']);
            $last_req = $stmt_run->fetch(PDO::FETCH_ASSOC);

            if ($last_req && !empty($last_req['req_no'])) {
                // ตัดเอาตัวเลข 3 หลักสุดท้ายมาบวก 1 (เช่น REQ261029-001 -> 001 -> 2)
                $parts = explode('-', $last_req['req_no']);
                $last_running_num = isset($parts[1]) ? intval($parts[1]) : 0;
                $next_running_num = $last_running_num + 1;
            } else {
                // ถ้ายังไม่มีในวันนี้ ให้เริ่มที่ 1
                $next_running_num = 1;
            }

            // จัดรูปแบบให้เป็น 3 หลัก (เช่น 001, 002)
            $req_no = $date_prefix . '-' . str_pad($next_running_num, 3, '0', STR_PAD_LEFT);

            // วนลูปตามตู้ Container ที่ผู้ใช้เลือก
            foreach ($selected_containers as $container_no) {
                // ดึงรายละเอียดย่อยทั้งหมด พร้อม country_of_origin ในตู้นั้น
                $sql_get_items = "SELECT d.receive_id, d.packing_no, d.net_weight_kg, d.piece_count, d.material_id, h.country_of_origin 
                                  FROM receiving_details d
                                  JOIN RECEIVING_HEADER h ON d.gr_no = h.gr_no
                                  WHERE h.container_no = ? AND (d.status != 'Issued' OR d.status IS NULL)";
                $stmt_items = $conn->prepare($sql_get_items);
                $stmt_items->execute([$container_no]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

                if (count($items) > 0) {
                    // A. บันทึกหัวบิลใบเบิก (requisition_header) พร้อม ref_no และ req_date ที่เลือก
                    $sql_head = "INSERT INTO requisition_header (req_no, req_date, department, ref_no, issued_by, status, remarks) 
                                 VALUES (:req_no, :req_date, :department, :ref_no, :issued_by, 'Issued', :remarks)";
                    $conn->prepare($sql_head)->execute([
                        ':req_no'     => $req_no,
                        ':req_date'   => $custom_req_date . ' ' . date('H:i:s'),
                        ':department' => $department,
                        ':ref_no'     => $ref_no,
                        ':issued_by'  => $issued_by,
                        ':remarks'    => $remarks . ' [จ่ายยกตู้: ' . $container_no . ']'
                    ]);

                    // B. วนลูปบันทึกรายละเอียด Packing ทั้งหมดในตู้ลง requisition_detail (รวม country_of_origin) และตัดสต็อก
                    foreach ($items as $item) {
                        $receive_id  = $item['receive_id'];
                        $packing_no  = $item['packing_no'];
                        $net_weight  = $item['net_weight_kg'];
                        $piece_count = $item['piece_count'];
                        $material_id = $item['material_id'];
                        $country     = $item['country_of_origin'];

                        // บันทึก requisition_detail
                        $sql_req_det = "INSERT INTO requisition_detail (req_no, container_no, packing_no, net_weight_kg, piece_count, country_of_origin, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'Issued')";
                        $conn->prepare($sql_req_det)->execute([$req_no, $container_no, $packing_no, $net_weight, $piece_count, $country]);

                        // บันทึก WIP_ISSUE_TRANSACTIONS
                        //$sql_issue = "INSERT INTO WIP_ISSUE_TRANSACTIONS (issue_no, container_no, packing_no, material_id, net_weight_kg, piece_count, issued_by, issued_date, remarks) 
                        //              VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?)";
                        //$conn->prepare($sql_issue)->execute([$req_no, $container_no, $packing_no, $material_id, $net_weight, $piece_count, $issued_by, $remarks]);

                        // อัปเดตสถานะใน receiving_details เป็น 'Issued'
                        $sql_upd = "UPDATE receiving_details SET status = 'Issued' WHERE receive_id = ?";
                        $conn->prepare($sql_upd)->execute([$receive_id]);
                    }
                }
            }

            $conn->commit();
            $success_msg = "บันทึกการตัดจ่ายยกตู้สำเร็จ! เลขที่ใบเบิก: " . $req_no;
        } catch (Exception $e) {
            $conn->rollBack();
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// 2. ดึงข้อมูลสรุปรายตู้ Container ที่มีสถานะ 'CL' และยังมีสินค้าค้างอยู่ในลาน
$search_container = isset($_GET['container']) ? trim($_GET['container']) : '';
$containerList = [];

try {
    $sql_container = "SELECT h.container_no, h.invoice_no, h.supplier_name, h.country_of_origin, h.description_fg, h.receive_date, h.receive_status,
                             COUNT(d.receive_id) AS total_packing_count,
                             SUM(ISNULL(d.piece_count, 0)) AS total_piece_count,
                             SUM(ISNULL(d.net_weight_kg, 0)) AS total_net_weight
                      FROM RECEIVING_HEADER h
                      JOIN RECEIVING_DETAILS d ON h.gr_no = d.gr_no
                      WHERE h.receive_status = 'CL' AND (d.status != 'Issued' OR d.status IS NULL)";

    $params = [];
    if (!empty($search_container)) {
        $sql_container .= " AND h.container_no LIKE :container";
        $params[':container'] = "%$search_container%";
    }

    $sql_container .= " GROUP BY h.container_no, h.invoice_no, h.supplier_name, h.country_of_origin, h.description_fg, h.receive_date, h.receive_status
                        ORDER BY h.receive_date DESC";

    $stmt_c = $conn->prepare($sql_container);
    $stmt_c->execute($params);
    $containerList = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Full Container Material Issuance</title>
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
        .custom-table-header th { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; color: #ffffff !important; font-weight: 700 !important; font-size: 13.5px; padding: 14px 10px !important; vertical-align: middle; text-align: center !important; border-bottom: none !important; }
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-cl { background-color: #dbeafe; color: #1e40af; font-weight: 700; padding: 4px 8px; border-radius: 4px; }
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
                    <a class="navbar-brand" href="#">ระบบตัดจ่ายวัสดุยกตู้ (Full Container Material Issuance)</a>
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
                    <div class="filter-item">
                        <label class="small fw-bold text-muted">ค้นหา Container No. (เฉพาะสถานะ CL)</label>
                        <input type="text" class="form-control form-control-minimal" name="container" value="<?php echo htmlspecialchars($search_container); ?>" placeholder="ระบุเลขตู้...">
                    </div>
                    <div class="filter-item">
                        <button type="submit" class="btn btn-search"><i class="fa-solid fa-magnifying-glass me-1"></i> ค้นหา</button>
                    </div>
                </form>

                <!-- ฟอร์มเลือกตู้เพื่อตัดจ่ายยกตู้ -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="issue_container">

                    <div class="dashboard-card shadow-sm">
                        <h4 class="card-title-sub text-primary mb-3"><i class="fa-solid fa-file-invoice me-2"></i> ข้อมูลการออกใบเบิกตัดจ่ายยกตู้</h4>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold text-muted mb-1">แผนกที่ขอเบิก (Department) *</label>
                                <input type="text" class="form-control" name="department" placeholder="เช่น Production Line 1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold text-muted mb-1">เลขที่อ้างอิง (Ref No.)</label>
                                <input type="text" class="form-control" name="ref_no" placeholder="ระบุเลขที่อ้างอิงเอกสาร...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="small fw-bold text-muted mb-1">วันที่ตัดจ่าย (Issue Date) *</label>
                                <input type="date" class="form-control" name="req_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="small fw-bold text-muted mb-1">หมายเหตุ (Remarks)</label>
                                <input type="text" class="form-control" name="remarks" placeholder="ระบุเหตุผลหรือหมายเหตุเพิ่มเติม...">
                            </div>
                        </div>

                        <h4 class="card-title-sub text-dark mb-3"><i class="fa-solid fa-truck-container me-2"></i> ตู้ Container ที่มีสถานะ CL พร้อมจ่ายยกตู้</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle text-center mb-0">
                                <thead>
                                    <tr class="custom-table-header">
                                        <th style="width: 50px;"><input type="checkbox" id="selectAll"></th>
                                        <th>วันที่รับเข้า</th>
                                        <th>CONTAINER NO.</th>
                                        <th>INVOICE NO.</th>
                                        <th>SUPPLIER</th>
                                        <th>COUNTRY</th>
                                        <th>DESCRIPTION FG</th>
                                        <th>จำนวนมัด (Packing)</th>
                                        <th>จำนวนชิ้นรวม (Pcs)</th>
                                        <th>น้ำหนักสุทธิรวม (KG)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($containerList) > 0): ?>
                                        <?php foreach ($containerList as $row): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_containers[]" value="<?php echo htmlspecialchars($row['container_no']); ?>">
                                                </td>

                                                <td><?php echo htmlspecialchars($row['receive_date']); ?></td>
                                                <td class="fw-bold text-primary" align="center">
                                                    <a href="material_issue_stock_detail_mats.php?func=<?php echo urlencode($folder_func); ?>&container=<?php echo urlencode($row['container_no']); ?>" class="btn-action-view">
                                                        <?php echo htmlspecialchars($row['container_no']); ?>
                                                    </a>
                                                </td>                                                
                                                <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['country_of_origin']); ?></td>
                                                <td><?php echo htmlspecialchars($row['description_fg']); ?></td>
                                                <td><span class="badge bg-secondary"><?php echo number_format($row['total_packing_count']); ?> มัด</span></td>
                                                <td><?php echo number_format($row['total_piece_count']); ?></td>
                                                <td class="fw-bold text-success"><?php echo number_format($row['total_net_weight'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="11" class="text-muted py-4">ไม่พบตู้ Container ที่มีสถานะ CL และมีสินค้าค้างจ่ายในขณะนี้</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-issue" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะทำการตัดจ่ายสินค้า [ยกตู้ทั้งตู้] ที่เลือก?')">
                                <i class="fa-solid fa-check-to-slot me-2"></i> ยืนยันการตัดจ่ายยกตู้ (Confirm Full Container Issue)
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<script>
    // Script เลือก Checkbox ทั้งหมด
    document.getElementById('selectAll').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_containers[]"]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });
</script>
</body>
<?php include 'include/footer.php';?>
</html>