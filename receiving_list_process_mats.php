<?php
// receiving_list_process_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG', 'WH', 'RC'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการรับเข้าวัตถุดิบ');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

// รับค่า GR No. จากพารามิเตอร์ URL
$gr_no_param = isset($_GET['gr_no']) ? trim($_GET['gr_no']) : '';
$header_supplier = "";
$header_desc = "";
$header_origin = "Malaysia";

if (!empty($gr_no_param)) {
    try {
        $headStmt = $conn->prepare("SELECT supplier_name, description_fg, country_of_origin, pack_list_no, container_no FROM RECEIVING_HEADER WHERE gr_no = ?");
        $headStmt->execute([$gr_no_param]);
        $headData = $headStmt->fetch(PDO::FETCH_ASSOC);
        if ($headData) {
            $header_supplier = $headData['supplier_name'];
            $header_desc     = $headData['description_fg'];
            $header_origin   = $headData['country_of_origin'];
            $pack_list_no   = $headData['pack_list_no'];
            $container_no   = $headData['container_no'];
            $header_origin   = $headData['country_of_origin'];
        }
    } catch (Exception $e) {}
}

// ตรรกะลบรายการ (Delete)
if (isset($_GET['delete_id'])) {
    try {
        $delId = $_GET['delete_id'];
        $findStmt = $conn->prepare("SELECT packing_no FROM receiving_details WHERE receive_id = ?");
        $findStmt->execute([$delId]);
        $rowDel = $findStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rowDel) {
            $packingNo = $rowDel['packing_no'];
            $conn->beginTransaction();
            $delLoc = $conn->prepare("DELETE FROM STOCK_LOCATIONS WHERE packing_no = ?");
            $delLoc->execute([$packingNo]);

            $delRec = $conn->prepare("DELETE FROM receiving_details WHERE receive_id = ?");
            $delRec->execute([$delId]);
            $conn->commit();     
            
            header("Location: receiving_list_process_mats.php?func=" . urlencode($folder_func) . "&gr_no=" . urlencode($gr_no_param));
            exit();
        }
    } catch (Exception $e) {
        if (isset($conn) && method_exists($conn, 'inTransaction') && $conn->inTransaction()) {
            $conn->rollBack();
        }
    }
}

// ตรรกะการบันทึกข้อมูลรับเข้า (Save Receive)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_receive') {
    try {
        $conn->beginTransaction();

        $sql1 = "INSERT INTO receiving_details (
                    gr_no, material_id, supplier_name, packing_no, 
                    cast_no, net_weight_kg, gross_weight_kg, piece_count, production_date, 
                    country_of_origin, status
                ) VALUES (
                    :gr_no, :material_id, :supplier_name, :packing_no, 
                    :cast_no, :net_weight_kg, :gross_weight_kg, :piece_count, :production_date, 
                    :country_of_origin, 'In Stock'
                )";
        
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([
            ':gr_no'             => $_POST['gr_no'],
            ':material_id'       => $_POST['material_id'],
            ':supplier_name'     => $_POST['supplier_name'],
            ':packing_no'        => $_POST['packing_no'],
            ':cast_no'           => $_POST['cast_no'],
            ':net_weight_kg'     => $_POST['net_weight_kg'],
            ':gross_weight_kg'     => $_POST['gross_weight_kg'],
            ':piece_count'       => $_POST['piece_count'],
            ':production_date'   => $_POST['production_date'],
            ':country_of_origin' => $_POST['country_of_origin']
        ]);

        $sql2 = "INSERT INTO STOCK_LOCATIONS (packing_no, zone, row_no) 
                 VALUES (:packing_no, :zone, :row_no)";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([
            ':packing_no' => $_POST['packing_no'],
            ':zone'       => $_POST['zone'],
            ':row_no'     => $_POST['row_no']
        ]);

        $conn->commit();

        // บันทึก Zone และ Row ลง Session
        $_SESSION['last_zone'] = $_POST['zone'];
        $_SESSION['last_row_no'] = $_POST['row_no'];        

        header("Location: receiving_list_process_mats.php?func=" . urlencode($folder_func) . "&gr_no=" . urlencode($_POST['gr_no']) . "&success=1");
        exit();

    } catch (Exception $e) {
        if (isset($conn) && method_exists($conn, 'inTransaction') && $conn->inTransaction()) {
            $conn->rollBack();
        }
        if (strpos($e->getMessage(), 'Violation of UNIQUE KEY') !== false) {
            $message = "เกิดข้อผิดพลาด: เลขที่ Packing No '" . htmlspecialchars($_POST['packing_no']) . "' นี้มีอยู่ในระบบแล้ว (ห้ามบันทึกซ้ำ)";
        } else {
            $message = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
        }
        $msgType = "danger";
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "บันทึกข้อมูลรับเข้าและอัปเดตพิกัดลานสำเร็จเรียบร้อยแล้ว!";
    $msgType = "success";
}

// ดึงรายการ Materials
$materials = [];
try {
    $matStmt = $conn->query("SELECT material_id, item_code, item_name FROM MATERIALS ORDER BY item_name ASC");
    $materials = $matStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ดึงรายการประวัติการรับเข้า
$logs = [];
try {
    $logSql = "SELECT r.receive_id, r.packing_no, r.cast_no, r.piece_count, r.net_weight_kg, r.gross_weight_kg, l.zone, l.row_no 
               FROM receiving_details r 
               LEFT JOIN STOCK_LOCATIONS l ON r.packing_no = l.packing_no 
               WHERE r.gr_no = ?
               ORDER BY r.receive_id DESC";
    $logStmt = $conn->prepare($logSql);
    $logStmt->execute([$gr_no_param]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="th">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; }
        input:read-only { background-color: transparent !important; color: #64748b !important; cursor: not-allowed; }
        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }        
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; }
        .badge-prod { background-color: #fef3c7; color: #92400e; font-weight: 700; font-size: 12px; padding: 4px 8px; border-radius: 6px; }
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }
        .btn-action-edit { background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-edit:hover { background-color: #1d4ed8; }

        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
        .tab-btn-close { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: none; background: #dc2626; color: #fff; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-left: auto; transition: all 0.2s; }
        .tab-btn-close:hover { background: #b91c1c; color: #fff; }

        /* การแต่งกล่องสแกนเนอร์ให้เข้ากับธีม */
        .scanner-card { border: 2px dashed #3b82f6; background-color: #f8fafc; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A5'; ?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        📦 Material Receiving Process (Pack List No: <?php echo htmlspecialchars($pack_list_no); ?>)
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="tab-btn-group">
                <a href="packing_list_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn">
                   ⬅️ Back to Packing List
                </a>
                <a href="receiving_header_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn">
                   ➕ Create New Header
                </a>
                <?php if (!empty($gr_no_param)): ?>
                <!-- ปุ่ม Close Scan เชื่อมโยงไป model/close_scan_receive_mats.php -->
                <a href="model/close_scan_receive_mats.php?func=<?php echo urlencode($folder_func); ?>&gr_no=<?php echo urlencode($gr_no_param); ?>" 
                   class="tab-btn-close"
                   onclick="return confirm('Confirm to stop scanning (Close Scan) for GR No. <?php echo htmlspecialchars($gr_no_param); ?> Is it right ? When closed, the status will be changed to CL');">
                   🔒 Close Scan
                </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msgType; ?> shadow-sm mb-3" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- ส่วน Scanner และ ฟอร์มลงทะเบียนรับเข้า -->
            <div class="row">
                <!-- ฝั่งซ้าย: กล่องยิงสแกนเนอร์ -->
                <div class="col-md-4">
                    <div class="dashboard-card h-100">
                        <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                            📷 Scanner Input
                        </h4>
                        <div class="scanner-card">
                            <label style="font-size:13px; font-weight:700; color:#2563eb; margin-bottom: 10px; display: block;">
                               Click the box below, then scan the barcode.
                            </label>
                            <input type="text" id="hardwareScanInput" class="form-control-minimal" style="text-align:center; font-size:16px;" placeholder="Click to wait for scan..." autofocus />
                            <small style="color:#64748b; display:block; margin-top:10px;">The system will match the data and fill the form automatically</small>
                        </div>
                        <div style="background:#f1f5f9; padding:15px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; color:#475569;">
                            ℹ️ <strong>Usage Instructions:</strong> You can use the barcode scanner to scan product labels to automatically fill in Lot/Packing No., Cast No., Net Weight and Production Date into the form.
                        </div>
                    </div>
                </div>

                <!-- ฝั่งขวา: ฟอร์มบันทึกข้อมูลรับเข้า -->
                <div class="col-md-8">
                    <div class="dashboard-card">
                        <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                            📝 Material Receiving Record (Container No: <?php echo htmlspecialchars($container_no); ?>) 
                        </h4>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="save_receive">
                            
                            <div class="row" style="margin-bottom: 16px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">GR NO. (เลขที่ใบรับสินค้า)</label>
                                    <input class="form-control-minimal" type="text" name="gr_no" value="<?php echo htmlspecialchars($gr_no_param); ?>" readonly />
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">MATERIAL (รายละเอียดสินค้า) *</label>
                                    <select class="form-control-minimal" name="material_id" required>
                                        <option value="">-- เลือก Material --</option>
                                        <?php foreach ($materials as $mat): ?>
                                            <option value="<?php echo htmlspecialchars($mat['material_id']); ?>" <?php echo ($header_desc == ($mat['item_code'] . ' - ' . $mat['item_name'])) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($mat['item_code'] . ' - ' . $mat['item_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 16px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">SUPPLIER (ผู้ผลิต)</label>
                                    <input class="form-control-minimal" type="text" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($header_supplier); ?>" readonly />
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">LOT / PACKING NO. *</label>
                                    <input class="form-control-minimal" type="text" id="packing_no" name="packing_no" placeholder="คลิกพิมพ์หรือรอสแกน..." required oninput="this.value = this.value.toUpperCase()" />
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 16px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">CAST NO. *</label>
                                    <input class="form-control-minimal" type="text" id="cast_no" name="cast_no" placeholder="Cast No..." required />
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">PIECES (จำนวนแท่ง) *</label>
                                    <input class="form-control-minimal" type="number" id="piece_count" name="piece_count" value="44" required />
                                </div>
                            </div>


                            <div class="row" style="margin-bottom: 16px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">NET WEIGHT (KG) *</label>
                                    <input class="form-control-minimal" type="number" step="0.01" id="net_weight_kg" name="net_weight_kg" placeholder="0.00" required />
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">GROSS WEIGHT (KG) *</label>
                                    <input class="form-control-minimal" type="number" id="gross_weight_kg" name="gross_weight_kg" value="0.00" required />
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 16px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">PRODUCTION DATE *</label>
                                    <input class="form-control-minimal" type="date" id="production_date" name="production_date" required />
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">COUNTRY OF ORIGIN</label>
                                    <input class="form-control-minimal" type="text" id="country_of_origin" name="country_of_origin" value="<?php echo htmlspecialchars($header_origin); ?>" readonly />
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 20px;">
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">ZONE (โซนลาน) *</label>
                                    <select class="form-control-minimal" name="zone" required>
                                        <option value="">-- เลือก Zone --</option>
                                        <option value="Zone A" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone A') ? 'selected' : ''; ?>>Zone A (ลานอะลูมิเนียม)</option>
                                        <option value="Zone B" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone B') ? 'selected' : ''; ?>>Zone B (ลานสแครป)</option>
                                        <option value="Zone C" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone C') ? 'selected' : ''; ?>>Zone C (ลานรอตรวจสอบ)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 filter-item">
                                    <label style="font-size:12px; font-weight:700; color:#64748b;">ROW / SLOT (แถวที่วาง) *</label>
                                    <input type="text" class="form-control" name="row_no" placeholder="เช่น Row 02 / Slot 05" value="<?php echo isset($_SESSION['last_row_no']) ? htmlspecialchars($_SESSION['last_row_no']) : ''; ?>" onfocus="this.value='';" required>
                                </div>
                            </div>

                            <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn-action-work" style="height: 40px; padding: 0 28px; font-size: 14px;">
                                    💾 Record arrivals and update coordinates
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ส่วนตารางแสดงประวัติรับเข้าล่าสุด -->
            <div class="dashboard-card">
                <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                    📋 Recent Received Logs
                </h4>

                <div class="table-responsive">
                    <table id="user_table" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center;">No.</th>
                                <th style="text-align: center;">Lot / Packing No</th>
                                <th style="text-align: center;">Cast No</th>
                                <th style="text-align: right;">Pieces</th>
                                <th style="text-align: right;">Net Wt (MT)</th>
                                <th style="text-align: right;">Gross Wt (MT)</th>
                                <th style="text-align: center;">Location</th>
                                <th style="text-align: center; width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            if (count($logs) > 0) {
                                $no = 1;
                                foreach ($logs as $row) {
                                    $net_mt = (float)$row['net_weight_kg'] / 1000;
                                    //$gross_mt = ((float)$row['gross_weight_kg']) / 1000;
                                    $gross_mt = ((float)$row['net_weight_kg'] + 0.5) / 1000;
                                    $safe_pack = htmlspecialchars($row['packing_no'] ?? '', ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td align="center" style="color: #64748b;"><?php echo $no++; ?></td>
                                        <td align="center"><span class="badge-job">📦 <?php echo $safe_pack; ?></span></td>
                                        <td align="center" style="font-weight:600;"><?php echo htmlspecialchars($row['cast_no'] ?? ''); ?></td>
                                        <td align="right"><?php echo number_format((float)($row['piece_count'] ?? 0)); ?></td>
                                        <td align="right" style="font-weight:700; color:#059669;"><?php echo number_format($net_mt, 4); ?></td>
                                        <td align="right" style="font-weight:700; color:#d97706;"><?php echo number_format($gross_mt, 4); ?></td>
                                        <td align="center"><span class="badge-prod"><?php echo htmlspecialchars(($row['zone'] ?? '') . ' ' . ($row['row_no'] ?? '')); ?></span></td>
                                        <td align="center">
                                            <a href="receiving_list_process_mats.php?func=<?php echo urlencode($folder_func); ?>&gr_no=<?php echo urlencode($gr_no_param); ?>&delete_id=<?php echo $row['receive_id']; ?>" 
                                               class="tab-btn-close" 
                                               onclick="return confirm('คุณต้องการลบรายการนี้ใช่หรือไม่?');">
                                               Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <input class="form-control" id="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
$("#user_table").DataTable({
    scrollY: "380px", 
    scrollCollapse: true, 
    paging: true, 
    pageLength: 10, 
    order: [] 
});

document.getElementById('hardwareScanInput').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let rawData = this.value.trim();
        parseScannedData(rawData);
        this.value = '';
    }
});

function parseScannedData(data) {
    if (data.startsWith("01")) {
        try {
            let idx10 = data.indexOf("10");
            if (idx10 !== -1) {
                let sub = data.substring(idx10 + 2);
                let packingNo = sub.substring(0, 13);
                packingNo = packingNo.replace(/[^A-Za-z0-9\-]/g, '');
                document.getElementById('packing_no').value = packingNo;
            }
        } catch (err) {}
    } else if (data.startsWith("C") || data.includes("PBIG")) {
        try {
            let castMatch = data.match(/C([0-9]+)/);
            if(castMatch) { document.getElementById('cast_no').value = castMatch[1]; }
            
            let packMatch = data.match(/(PBIG-[0-9]+|BIG-[0-9]+)/);
            if(packMatch) { document.getElementById('packing_no').value = packMatch[1].replace('P', ''); }

            let dateMatch = data.match(/([0-9]{2})-([0-9]{2})-([0-9]{2})/);
            if(dateMatch) {
                document.getElementById('production_date').value = `20${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]}`;
            }

            let weightMatch = data.match(/KG([0-9.]+)/);
            if(weightMatch) { 
                document.getElementById('net_weight_kg').value = weightMatch[1]; 
                document.getElementById('gross_weight_kg').value = weightMatch[1]; 
            }
        } catch (err) {}
    } else {
        document.getElementById('packing_no').value = data;
    }

    let packingNoVal = document.getElementById('packing_no').value.trim();
    let weightVal = document.getElementById('net_weight_kg').value.trim();
    let dateVal = document.getElementById('production_date').value.trim();
    let materialVal = document.querySelector('select[name="material_id"]').value;
    let zoneVal = document.querySelector('select[name="zone"]').value;
    let rowVal = document.querySelector('input[name="row_no"]').value.trim();

    if (packingNoVal !== "" && weightVal !== "" && !isNaN(weightVal) && dateVal !== "" && materialVal !== "" && zoneVal !== "" && rowVal !== "") {
        setTimeout(function() {
            document.querySelector('form').submit();
        }, 200);
    }
}
</script>
</html>