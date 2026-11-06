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

// บันทึกข้อมูล พร้อมตรวจสอบป้องกันการสแกนซ้ำ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_receive') {
    try {
        $packing_no = trim($_POST['packing_no']);

        // 1. ตรวจสอบว่ามี Packing No นี้บันทึกอยู่ในระบบแล้วหรือยัง
        $checkDup = $conn->prepare("SELECT COUNT(*) FROM receiving_details WHERE packing_no = ?");
        $checkDup->execute([$packing_no]);
        if ($checkDup->fetchColumn() > 0) {
            throw new Exception("DUPLICATE_PACKING");
        }

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
            ':packing_no'        => $packing_no,
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
            ':packing_no' => $packing_no,
            ':zone'       => $_POST['zone'],
            ':row_no'     => $_POST['row_no']
        ]);

        $conn->commit();

        $_SESSION['last_zone'] = $_POST['zone'];
        $_SESSION['last_row_no'] = $_POST['row_no'];

        header("Location: receiving.php?gr_no=" . urlencode($_POST['gr_no']) . "&success=1");
        exit();

    } catch (Exception $e) {
        if (isset($conn) && method_exists($conn, 'inTransaction') && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        if ($e->getMessage() == "DUPLICATE_PACKING" || strpos($e->getMessage(), 'Violation of UNIQUE KEY') !== false) {
            $message = "⚠️ แจ้งเตือน: Packing No '" . htmlspecialchars($_POST['packing_no']) . "' นี้ถูกสแกนบันทึกไปแล้ว (ห้ามบันทึกซ้ำ)";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <link rel="icon" type="image/png" href="assets/img/MATS.ico">
    <link href="handheld/bootstrap.min.css" rel="stylesheet">
    <link href="handheld/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            margin: 0;
            padding: 0;
            min-height: 100dvh;
        }
        .navbar-custom {
            padding: 10px 14px;
            background-color: #0f172a !important;
        }
        .navbar-title {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .form-card { 
            background: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            padding: 16px; 
            margin-bottom: 16px; 
            border: 1px solid #e2e8f0; 
        }
        .card-title-sub { 
            font-size: 14px; 
            font-weight: 600; 
            color: #1e293b; 
            margin-top: 0; 
            margin-bottom: 12px; 
            padding-bottom: 8px; 
            border-bottom: 2px solid #f1f5f9; 
            display: flex; 
            align-items: center; 
        }
        .form-label { 
            font-weight: 600; 
            color: #475569; 
            margin-bottom: 4px; 
            font-size: 13px; 
        }
        .form-control, .form-select { 
            border-radius: 8px; 
            border: 1px solid #cbd5e1; 
            height: 40px; 
            padding: 6px 10px; 
            font-size: 14px; 
            background-color: #ffffff !important; 
            color: #1e293b !important; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #2563eb; 
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); 
            outline: none; 
        }
        input:readonly { 
            background-color: #f1f5f9 !important; 
            color: #64748b !important; 
            cursor: not-allowed; 
        }
        .scanner-box { 
            border: 2px dashed #3b82f6; 
            background: #f8fafc; 
            border-radius: 10px; 
            padding: 14px; 
            text-align: center; 
        }
        .btn-custom-submit { 
            background-color: #2563eb; 
            color: #ffffff; 
            border: none; 
            padding: 12px 16px; 
            font-weight: 600; 
            font-size: 15px; 
            border-radius: 8px; 
            width: 100%;
        }
        .btn-custom-submit:active { 
            background-color: #1d4ed8; 
        }
        .custom-table-header { 
            background-color: #1e293b !important; 
            color: #ffffff !important; 
            font-weight: 600; 
            font-size: 12px;
        }
        .table td {
            font-size: 12px;
            vertical-align: middle;
        }
        .badge-location { 
            background-color: #e2e8f0; 
            color: #1e293b; 
            font-weight: 500; 
            padding: 4px 8px; 
            border-radius: 6px; 
            font-size: 11px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom border-bottom border-secondary mb-3">
    <div class="container-fluid p-0 d-flex justify-content-between align-items-center flex-nowrap">
        <div class="d-flex align-items-center overflow-hidden">
            <a href="packing_list.php" class="btn btn-outline-light btn-sm me-2 py-1 px-2 text-nowrap">
                หน้าแรก
            </a>
            <span class="navbar-brand navbar-title text-truncate m-0">SCAN: <?php echo htmlspecialchars($gr_no_param); ?></span>
        </div>
        <span class="badge bg-success px-2 py-1 text-nowrap ms-1" style="font-size: 10px;">SQL</span>
    </div>
</nav>

<div class="container-fluid px-2 pb-4">

    <?php if (!empty($message)): ?>
        <div id="alertBox" class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show py-2 px-3 small shadow-sm fw-bold mb-3" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- ช่องยิงสแกนบาร์โค้ด -->
        <div class="col-12">
            <div class="form-card mb-3">
                <div class="card-title-sub text-primary mb-2">
                    ช่องยิงบาร์โค้ด (Scanner Input)
                </div>
                <div class="scanner-box">
                    <label class="form-label text-primary fw-bold mb-1" style="font-size: 12px;">
                        คลิกช่องด้านล่าง แล้วยิงบาร์โค้ด
                    </label>
                    <input type="text" id="hardwareScanInput" class="form-control form-control-sm text-center" placeholder="แตะเพื่อรอสแกน..." onfocus="this.value='';" autofocus>
                </div>
            </div>
        </div>

        <!-- ฟอร์มกรอกข้อมูลหลัก -->
        <div class="col-12">
            <div class="form-card">
                <div class="card-title-sub text-warning">
                    บันทึกรับเข้าวัตถุดิบ
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="receiving.php?gr_no=<?php echo urlencode($gr_no_param); ?>">
                        <input type="hidden" name="action" value="save_receive">
                        
                        <div class="mb-2">
                            <label class="form-label">เลขที่ใบรับสินค้า (GR No.)</label>
                            <input type="text" class="form-control" name="gr_no" value="<?php echo htmlspecialchars($gr_no_param); ?>" readonly>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">เลือกประเภทวัตถุดิบ (Material)</label>
                            <select class="form-select" name="material_id" required>
                                <option value="">-- เลือกรายการ Material --</option>
                                <?php foreach ($materials as $mat): ?>
                                    <option value="<?php echo $mat['material_id']; ?>" <?php echo ($header_desc == ($mat['item_code'] . ' - ' . $mat['item_name'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mat['item_code'] . ' - ' . $mat['item_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Supplier (ผู้ผลิต)</label>
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="<?php echo htmlspecialchars($header_supplier); ?>" readonly>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Packing No. (เลขที่มัด) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="packing_no" name="packing_no" placeholder="รหัส Packing..." required>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Cast No. <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cast_no" name="cast_no" placeholder="เบ้าหลอม" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">น้ำหนักสุทธิ (KG) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="net_weight_kg" name="net_weight_kg" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">จำนวนแท่ง (Pieces) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="piece_count" name="piece_count" value="44" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">วันที่ผลิต <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="production_date" name="production_date" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ประเทศต้นทาง</label>
                            <input type="text" class="form-control" id="country_of_origin" name="country_of_origin" value="<?php echo htmlspecialchars($header_origin); ?>" readonly>
                        </div>

                        <hr class="border-secondary my-3">
                        <div class="fw-bold text-primary mb-2" style="font-size: 13px;">
                            พิกัดตำแหน่งลานกองเก็บ (Yard Location)
                        </div>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Zone <span class="text-danger">*</span></label>
                                <select class="form-select" name="zone" required>
                                    <option value="">-- เลือก Zone --</option>
                                    <option value="Zone A" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone A') ? 'selected' : ''; ?>>Zone A</option>
                                    <option value="Zone B" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone B') ? 'selected' : ''; ?>>Zone B</option>
                                    <option value="Zone C" <?php echo (isset($_SESSION['last_zone']) && $_SESSION['last_zone'] == 'Zone C') ? 'selected' : ''; ?>>Zone C</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Row / Slot <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="row_no" placeholder="เช่น Row 02" value="<?php echo isset($_SESSION['last_row_no']) ? htmlspecialchars($_SESSION['last_row_no']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="pt-1">
                            <button type="submit" class="btn btn-custom-submit shadow-sm">
                                บันทึกรับเข้าพิกัดลาน
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ตารางแสดงผลรายการรับเข้าล่าสุด -->
    <div class="row mt-2">
        <div class="col-12">
            <div class="form-card mb-0">
                <div class="card-title-sub text-success mb-2">
                    รายการรับเข้าล่าสุด (Recent Logs)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle text-center mb-0">
                            <thead>
                                <tr class="custom-table-header">
                                    <th class="py-2">Packing No</th>
                                    <th class="py-2">Cast / Pcs</th>
                                    <th class="py-2">Location</th>
                                    <th class="py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $row): ?>
                                        <tr>
                                            <td class="fw-bold text-primary py-2" style="font-size: 11px;"><?php echo htmlspecialchars($row['packing_no']); ?></td>
                                            <td class="py-2" style="font-size: 11px;"><?php echo htmlspecialchars($row['cast_no']); ?> <br>(<?php echo htmlspecialchars($row['piece_count']); ?> pcs)</td>
                                            <td class="py-2"><span class="badge-location"><?php echo htmlspecialchars($row['zone'] . ' ' . $row['row_no']); ?></span></td>
                                            <td class="py-2">
                                                <a href="?gr_no=<?php echo urlencode($gr_no_param); ?>&delete_id=<?php echo $row['receive_id']; ?>" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 11px;" onclick="return confirm('ยืนยันการลบรายการนี้?');">
                                                    Del
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted py-3 small">ยังไม่มีรายการรับเข้าสำหรับ GR นี้</td>
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
const scanInput = document.getElementById('hardwareScanInput');

scanInput.addEventListener('input', function () {
    let rawData = this.value.trim();
    if (rawData.length > 15) {
        setTimeout(() => {
            parseScannedData(rawData);
            this.value = '';
        }, 50);
    }
});

scanInput.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let rawData = this.value.trim();
        if (rawData !== "") {
            parseScannedData(rawData);
            this.value = '';
        }
    }
});

function parseScannedData(data) {
    console.log("Scanned Raw Data:", data);

    try {
        let packingNo = "";
        let castNo = "";
        let weightVal = "";
        let dateVal = "";

        if (data.includes("PBIG") || data.includes("BIG")) {
            let castMatch = data.match(/C([0-9]+)/i);
            if (castMatch) {
                castNo = castMatch[1];
                document.getElementById('cast_no').value = castNo;
            }

            let packMatch = data.match(/(PBIG-[0-9]+|BIG-[0-9]+)/i);
            if (packMatch) {
                packingNo = packMatch[1].replace(/^P/, '');
                document.getElementById('packing_no').value = packingNo;
            }

            let dateMatch = data.match(/([0-9]{2})[\/\-]([0-9]{2})[\/\-]([0-9]{2})/);
            if (dateMatch) {
                dateVal = `20${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]}`;
                document.getElementById('production_date').value = dateVal;
            }

            let weightMatch = data.match(/KG([0-9.]+)/i);
            if (weightMatch) {
                weightVal = weightMatch[1];
                document.getElementById('net_weight_kg').value = weightVal;
            }
        } 
        else if (data.startsWith("01")) {
            let idx10 = data.indexOf("10");
            if (idx10 !== -1) {
                let sub = data.substring(idx10 + 2);
                let cleanPack = sub.split(/[^0-9A-Za-z\-]/)[0];
                if (cleanPack) {
                    packingNo = cleanPack.replace(/^P/, '');
                    document.getElementById('packing_no').value = packingNo;
                }
            }
            let matchWeight = data.match(/30([0-9]+)$/);
            if (matchWeight) {
                weightVal = matchWeight[1];
                document.getElementById('net_weight_kg').value = weightVal;
            }
        } 
        else {
            document.getElementById('packing_no').value = data;
        }

    } catch (err) {
        console.error("Parsing error: ", err);
    }

    let packingNoVal = document.getElementById('packing_no').value.trim();
    let weightValCheck = document.getElementById('net_weight_kg').value.trim();
    let dateValCheck = document.getElementById('production_date').value.trim();
    let materialVal = document.querySelector('select[name="material_id"]').value;
    let zoneVal = document.querySelector('select[name="zone"]').value;
    let rowVal = document.querySelector('input[name="row_no"]').value.trim();

    if (packingNoVal !== "" && weightValCheck !== "" && !isNaN(weightValCheck) && dateValCheck !== "" && materialVal !== "" && zoneVal !== "" && rowVal !== "") {
        setTimeout(function() {
            document.querySelector('form').submit();
        }, 100);
    }
}
</script>

<script src="handheld/bootstrap.bundle.min.js"></script>
</body>
</html>