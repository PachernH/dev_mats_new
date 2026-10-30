<?php
session_start();
require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

$gr_no_param = isset($_GET['gr_no']) ? $_GET['gr_no'] : 'GR-202610-0001';
$header_supplier = "";
$header_desc = "";
$header_origin = "Malaysia";

try {
    $headStmt = $conn->prepare("SELECT supplier_name, description_fg, country_of_origin FROM RECEIVING_HEADER WHERE gr_no = ?");
    $headStmt->execute([$gr_no_param]);
    $headData = $headStmt->fetch(PDO::FETCH_ASSOC);
    if ($headData) {
        $header_supplier = $headData['supplier_name'];
        $header_desc = $headData['description_fg'];
        $header_origin = $headData['country_of_origin'];
    }
} catch (Exception $e) {}

// จัดการคำขอสลาย/ลบข้อมูลรายการรับเข้า
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
            
            header("Location: receiving.php?gr_no=" . urlencode($gr_no_param));
            exit();
        }
    } catch (Exception $e) {
        if (isset($conn) && method_exists($conn, 'inTransaction') && $conn->inTransaction()) {
            $conn->rollBack();
        }
    }
}

// บันทึกข้อมูล
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
            ':gross_weight_kg'   => $_POST['net_weight_kg'],
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

        // **บันทึก Zone และ Row ลง Session เพื่อให้จำค่าไว้ใช้ครั้งถัดไปอัตโนมัติ**
        $_SESSION['last_zone'] = $_POST['zone'];
        $_SESSION['last_row_no'] = $_POST['row_no'];

        header("Location: receiving.php?gr_no=" . urlencode($_POST['gr_no']) . "&success=1");
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

$materials = [];
try {
    $matStmt = $conn->query("SELECT material_id, item_code, item_name FROM MATERIALS");
    $materials = $matStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$logs = [];
try {
    $logSql = "SELECT TOP 10 r.receive_id, r.packing_no, r.cast_no, r.piece_count, l.zone, l.row_no 
               FROM receiving_details r 
               LEFT JOIN STOCK_LOCATIONS l ON r.packing_no = l.packing_no 
               WHERE r.gr_no = ?
               ORDER BY r.receive_id DESC";
    $logStmt = $conn->prepare($logSql);
    $logStmt->execute([$gr_no_param]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <link rel="icon" type="image/png" href="assets/img/MATS.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        .form-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; }
        .form-label, .form-group label { font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 14px; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; background-color: #ffffff !important; color: #1e293b !important; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        input:readonly { background-color: #f1f5f9 !important; color: #64748b !important; cursor: not-allowed; }
        .scanner-box { border: 2px dashed #3b82f6; background: #f1f5f9; border-radius: 12px; padding: 20px; text-align: center; }
        .btn-custom-submit { background-color: #2563eb; color: #ffffff; border: none; padding: 12px 28px; font-weight: 600; font-size: 15px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); transition: all 0.2s; }
        .btn-custom-submit:hover { background-color: #1d4ed8; transform: translateY(-1px); }
        .custom-table-header { background-color: #1e293b !important; color: #ffffff !important; font-weight: 600; }
        .badge-location { background-color: #e2e8f0; color: #1e293b; font-weight: 500; padding: 6px 12px; border-radius: 6px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4 py-3">
    <div class="container-fluid px-4">
        <span class="navbar-brand fs-4"><i class="fa-solid fa-boxes-stacked me-2"></i> SCANNER INPUT (GR: <?php echo htmlspecialchars($gr_no_param); ?>)</span>
        <div>
            <a href="packing_list.php" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-arrow-left me-1"></i> กลับไปรายการ Packing List</a>
            <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-database me-1"></i> SQL Server 2005</span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show shadow" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ฝั่งซ้าย: ช่องยิงสแกนบาร์โค้ด -->
        <div class="col-lg-4">
            <div class="form-card h-100">
                <div class="card-title-sub text-primary"><i class="fa-solid fa-barcode me-2"></i> ช่องยิงบาร์โค้ด (Scanner Input)</div>
                <div class="card-body p-0 d-flex flex-column justify-content-between">
                    <div>
                        <div class="scanner-box mb-4">
                            <label class="form-label text-primary fw-bold mb-2"><i class="fa-solid fa-hand-pointer me-1"></i> คลิกแล้วยิงบาร์โค้ดที่นี่</label>
                            <input type="text" id="hardwareScanInput" class="form-control form-control-lg text-center" placeholder="คลิกเพื่อรอสแกน..." onfocus="this.value='';" autofocus>
                            <small class="text-muted d-block mt-2">เครื่อง Handheld / USB จะพิมพ์และกด Enter อัตโนมัติ</small>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border text-secondary small">
                        <i class="fa-solid fa-circle-exclamation text-warning me-1"></i> <strong>คำแนะนำ:</strong> ใช้ปืนสแกนยิงที่ QR Code บนป้ายวัตถุดิบ ระบบจะดึงข้อมูลลงฟอร์มให้อัตโนมัติทันที
                    </div>
                </div>
            </div>
        </div>

        <!-- ฝั่งขวา: ฟอร์มกรอกข้อมูลหลัก -->
        <div class="col-lg-8">
            <div class="form-card">
                <div class="card-title-sub text-warning"><i class="fa-solid fa-pen-to-square me-2"></i> ฟอร์มบันทึกรับเข้าวัตถุดิบ (Material Entry Form)</div>
                <div class="card-body p-0">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save_receive">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">เลขที่ใบรับสินค้า (GR No.)</label>
                                <input type="text" class="form-control" name="gr_no" value="<?php echo htmlspecialchars($gr_no_param); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เลือกประเภทวัตถุดิบ (Material Master / Description FG)</label>
                                <select class="form-select" name="material_id" required>
                                    <option value="">-- เลือกรายการ Material --</option>
                                    <?php foreach ($materials as $mat): ?>
                                        <option value="<?php echo $mat['material_id']; ?>" <?php echo ($header_desc == ($mat['item_code'] . ' - ' . $mat['item_name'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mat['item_code'] . ' - ' . $mat['item_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier (ผู้ผลิต)</label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($header_supplier); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Packing No. (เลขที่มัด)</label>
                                <input type="text" class="form-control" id="packing_no" name="packing_no" placeholder="คลิกเพื่อพิมพ์หรือรอสแกน..." onfocus="this.value='';" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Cast No. (เบ้าหลอม)</label>
                                <input type="text" class="form-control" id="cast_no" name="cast_no" placeholder="คลิกเพื่อพิมพ์..." onfocus="this.value='';" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">น้ำหนักสุทธิ (Nett Weight KG)</label>
                                <input type="number" step="0.01" class="form-control" id="net_weight_kg" name="net_weight_kg" placeholder="0.00" onfocus="if(this.value=='0' || this.value=='0.00')this.value='';" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">จำนวนแท่ง (Pieces)</label>
                                <input type="number" class="form-control" id="piece_count" name="piece_count" value="44" onfocus="if(this.value=='44')this.value='';" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">วันที่ผลิต (Production Date)</label>
                                <input type="date" class="form-control" id="production_date" name="production_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเทศต้นทาง (Country of Origin)</label>
                                <input type="text" class="form-control" id="country_of_origin" name="country_of_origin" value="<?php echo htmlspecialchars($header_origin); ?>" readonly>
                            </div>
                        </div>

                        <hr class="border-secondary my-4">
                        <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-location-dot me-2"></i> พิกัดตำแหน่งกองเก็บลานกลางแจ้ง (Yard Location)</h6>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Zone (โซนลาน)</label>
                                <select class="form-select" name="zone" required>
                                    <option value="">-- เลือก Zone --</option>
                                    <option value="Zone A" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone A') ? 'selected' : ''; ?>>Zone A (ลานอะลูมิเนียม)</option>
                                    <option value="Zone B" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone B') ? 'selected' : ''; ?>>Zone B (ลานสแครป)</option>
                                    <option value="Zone C" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone C') ? 'selected' : ''; ?>>Zone C (ลานรอตรวจสอบ)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Row / Slot (แถวที่วาง)</label>
                                <input type="text" class="form-control" name="row_no" placeholder="เช่น Row 02 / Slot 05" value="<?php echo isset($_SESSION['last_row_no']) ? htmlspecialchars($_SESSION['last_row_no']) : ''; ?>" onfocus="this.value='';" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-custom-submit btn-lg py-3"><i class="fa-solid fa-floppy-disk me-2"></i> บันทึกรับเข้าและอัปเดตพิกัดลาน</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ตารางแสดงผลรายการรับเข้าล่าสุด ปรับคอลัมน์ตามต้องการ -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="form-card">
                <div class="card-title-sub text-success"><i class="fa-solid fa-list-check me-2"></i> รายการรับเข้าล่าสุด (Recent Received Logs)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle text-center mb-0">
                            <thead>
                                <tr class="custom-table-header">
                                    <th class="py-3">No</th>
                                    <th class="py-3">Packing No</th>
                                    <th class="py-3">Cast No</th>
                                    <th class="py-3">Pieces</th>
                                    <th class="py-3">Location</th>
                                    <th class="py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php $no = 1; foreach ($logs as $row): ?>
                                        <tr>
                                            <td class="text-muted"><?php echo $no++; ?></td>
                                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['packing_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['cast_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['piece_count']); ?></td>
                                            <td><span class="badge-location"><?php echo htmlspecialchars($row['zone'] . ' ' . $row['row_no']); ?></span></td>
                                            <td>
                                                <a href="?gr_no=<?php echo urlencode($gr_no_param); ?>&delete_id=<?php echo $row['receive_id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('คุณต้องการลบรายการนี้ใช่หรือไม่?');">
                                                    <i class="fa-solid fa-trash-can"></i> ลบ
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-muted py-4">ยังไม่มีรายการรับเข้าในระบบสำหรับ GR นี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
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
                let packingNo = sub.substring(0, 11);
                packingNo = packingNo.replace(/[^A-Za-z0-9\-]/g, '');
                document.getElementById('packing_no').value = packingNo;
            }
        } catch (err) {}
    } else if (data.startsWith("C") || data.includes("PBIG")) {
        try {
            let castMatch = data.match(/C([0-9]+)/);
            if(castMatch) { document.getElementById('cast_no').value = castMatch[1]; }
            
            let packMatch = data.match(/(PBIG-[0-9]+|BIG-[0-9]+)/);
            if(packMatch) { document.getElementById('packing_no').value = (packMatch[1].replace('P', '')).substring(0, 11); }

            let dateMatch = data.match(/([0-9]{2})-([0-9]{2})-([0-9]{2})/);
            if(dateMatch) {
                document.getElementById('production_date').value = `20${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]}`;
            }

            let weightMatch = data.match(/KG([0-9.]+)/);
            if(weightMatch) { document.getElementById('net_weight_kg').value = weightMatch[1]; }
        } catch (err) {}
    } else {
        document.getElementById('packing_no').value = data;
    }

    // --- ตรวจสอบว่ามีข้อมูลจำเป็นครบถ้วนจริงๆ ถึงค่อย Auto-Submit ---
    let packingNoVal = document.getElementById('packing_no').value.trim();
    let weightVal = document.getElementById('net_weight_kg').value.trim();
    let dateVal = document.getElementById('production_date').value.trim();
    let materialVal = document.querySelector('select[name="material_id"]').value;
    let zoneVal = document.querySelector('select[name="zone"]').value;
    let rowVal = document.querySelector('input[name="row_no"]').value.trim();

    // เช็คว่า Packing No, น้ำหนัก, และวันที่ ไม่เป็นค่าว่าง ถึงจะบันทึกอัตโนมัติ
    if (packingNoVal !== "" && weightVal !== "" && !isNaN(weightVal) && dateVal !== "" && materialVal !== "" && zoneVal !== "" && rowVal !== "") {
        setTimeout(function() {
            document.querySelector('form').submit();
        }, 200);
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>