<?php
require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

$suppliers = [];
try {
    $supStmt = $conn->query("SELECT CSTMSPPL_ID, EN_COMPANY FROM CSSPMSTR22 Where (CSTMSPPL_ID='P-6309' OR CSTMSPPL_ID='P-N022' OR CSTMSPPL_ID='P-T172' OR CSTMSPPL_ID='R-T015') ORDER BY EN_COMPANY ASC");
    $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$materialsList = [];
try {
    $matListStmt = $conn->query("SELECT material_id, item_code, item_name FROM MATERIALS ORDER BY item_name ASC");
    $materialsList = $matListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $todayPrefix = "GR" . date('ymd') . "-";
        $stmtMax = $conn->query("SELECT TOP 1 gr_no FROM RECEIVING_HEADER WHERE gr_no LIKE '$todayPrefix%' ORDER BY header_id DESC");
        $lastGr = $stmtMax->fetch(PDO::FETCH_ASSOC);
        
        if ($lastGr) {
            $runningNo = intval(substr($lastGr['gr_no'], -4)) + 1;
            $grNo = $todayPrefix . str_pad($runningNo, 4, '0', STR_PAD_LEFT);
        } else {
            $grNo = $todayPrefix . "0001";
        }

        $supplier_id = $_POST['supplier_id'] ?? '';
        $supplier_name = "";
        foreach ($suppliers as $sup) {
            if ($sup['CSTMSPPL_ID'] == $supplier_id) {
                $supplier_name = $sup['EN_COMPANY'];
                break;
            }
        }

        $description_fg = "";
        $materials_no = "";
        foreach ($materialsList as $mat) {
            if ($mat['material_id'] == ($_POST['description_fg_id'] ?? '')) {
                $description_fg = $mat['item_code'] .' - '. $mat['item_name'];
                $materials_no = $mat['item_code'];
                break;
            }
        }     

        // บันทึกข้อมูลเข้าตาราง RECEIVING_HEADER
        $sql = "INSERT INTO RECEIVING_HEADER (
                    gr_no, pack_list_no, invoice_no, receive_date, 
                    container_no, seal_no, description_fg, materials_no, material_id, 
                    supplier_id, supplier_name, country_of_origin, country_of_secondary, po_no, receive_status
                ) VALUES (
                    :gr_no, :pack_list_no, :invoice_no, :receive_date, 
                    :container_no, :seal_no, :description_fg, :materials_no, :material_id, 
                    :supplier_id, :supplier_name, :country_of_origin, :country_of_secondary, :po_no, 'OP'
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':gr_no'                => $grNo,
            ':pack_list_no'         => $_POST['pack_list_no'] ?? '',
            ':invoice_no'           => $_POST['invoice_no'] ?? '',
            ':receive_date'         => $_POST['receive_date'] ?? date('Y-m-d'),
            ':container_no'         => $_POST['container_no'] ?? '',
            ':seal_no'              => $_POST['seal_no'] ?? '',
            ':description_fg'       => $description_fg,
            ':materials_no'         => $materials_no,
            ':material_id'          => $_POST['description_fg_id'] ?? '',
            ':supplier_id'          => $supplier_id,
            ':supplier_name'        => $supplier_name,
            ':po_no'                => $_POST['po_no'] ?? '',
            ':country_of_secondary' => $_POST['country_of_secondary'] ?? '',
            ':country_of_origin'    => $_POST['country_of_origin'] ?? ''
        ]);

        header("Location: packing_list.php");
        exit();

    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $msgType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <!-- ปรับ viewport ป้องกันการซูม และรองรับหน้าจอ Handheld -->
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
            font-size: 0.95rem;
            font-weight: 600;
        }
        .form-card { 
            background: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            padding: 16px; 
            margin-bottom: 20px; 
            border: 1px solid #e2e8f0; 
        }
        .card-title-sub { 
            font-size: 15px; 
            font-weight: 600; 
            color: #2563eb; 
            margin-bottom: 14px; 
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
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom border-bottom border-secondary mb-3">
    <div class="container-fluid p-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <a href="receiving_index.php" class="btn btn-outline-light btn-sm me-2 py-1 px-2">
                หน้าแรก
            </a>
            <span class="navbar-brand navbar-title text-wrap m-0">CREATE PACKING LIST</span>
        </div>
        <span class="badge bg-success px-2 py-1" style="font-size: 11px;">SQL</span>
    </div>
</nav>

<div class="container px-2 mb-4">
    <div class="row justify-content-center g-0">
        <div class="col-12 col-md-8">
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msgType; ?> py-2 px-3 mb-3 small shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-info me-1"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-title-sub">
                    บันทึก Packing List Header
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="" id="headerForm">
                        
                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Invoice No.</label>
                                <input type="text" class="form-control" name="invoice_no" placeholder="ระบุเลขที่ใบกำกับสินค้า">
                            </div>                            
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Pack List No. <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="pack_list_no" placeholder="ระบุเลขที่แพ็คเกจลิสต์" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">PO No. <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="po_no" placeholder="ระบุเลขที่ใบสั่งซื้อ" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Materials <span class="text-danger">*</span></label>
                                <select class="form-select" name="description_fg_id" required>
                                    <option value="">-- เลือก Materials --</option>
                                    <?php foreach ($materialsList as $m): ?>
                                        <option value="<?php echo $m['material_id']; ?>">
                                            <?php echo htmlspecialchars($m['item_code'] . ' - ' . $m['item_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Date (วันที่รับเข้า) <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="receive_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Supplier (ผู้ผลิต) <span class="text-danger">*</span></label>
                                <select class="form-select" name="supplier_id" required>
                                    <option value="">-- เลือก Supplier --</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?php echo htmlspecialchars($sup['CSTMSPPL_ID']); ?>">
                                            <?php echo htmlspecialchars($sup['CSTMSPPL_ID'] . ' - ' . $sup['EN_COMPANY']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Container No.</label>
                                <input type="text" class="form-control" name="container_no" placeholder="เลขตู้">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Seal No.</label>
                                <input type="text" class="form-control" name="seal_no" placeholder="เลขซีล">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Country of Origin <span class="text-danger">*</span></label>
                                <select class="form-select" name="country_of_origin" id="country_of_origin" required>
                                    <option value="">-- เลือก Country --</option>
                                    <option value="MALAYSIA">MALAYSIA</option>
                                    <option value="AUSTRALIA">AUSTRALIA</option>
                                    <option value="INDIA">INDIA</option>
                                    <option value="NEW ZEALAND">NEW ZEALAND</option>
                                    <option value="SOUTH AFRICA">SOUTH AFRICA</option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label">Country of Secondary</label>
                                <select class="form-select" name="country_of_secondary" id="country_of_secondary" disabled>
                                    <option value="">-- เลือก Country --</option>
                                    <option value="MALAYSIA">MALAYSIA</option>
                                    <option value="AUSTRALIA">AUSTRALIA</option>
                                    <option value="INDIA">INDIA</option>
                                    <option value="NEW ZEALAND">NEW ZEALAND</option>
                                    <option value="SOUTH AFRICA">SOUTH AFRICA</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn btn-custom-submit shadow-sm">
                                บันทึก และไป Packing List
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="handheld/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const originSelect = document.getElementById('country_of_origin');
    const secondarySelect = document.getElementById('country_of_secondary');
    const form = document.getElementById('headerForm');

    // เมื่อเลือก Country of Origin จะเปิดให้เลือก Country of Secondary ได้
    originSelect.addEventListener('change', function() {
        if (this.value !== '') {
            secondarySelect.disabled = false;
        } else {
            secondarySelect.disabled = true;
            secondarySelect.value = '';
        }
    });

    // ปลด disabled ก่อน submit เพื่อส่งค่าฟอร์มได้ถูกต้อง
    form.addEventListener('submit', function() {
        secondarySelect.disabled = false;
    });
});
</script>
</body>
</html>