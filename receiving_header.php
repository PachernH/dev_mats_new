<?php
require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

$suppliers = [];
try {
    $supStmt = $conn->query("SELECT CSTMSPPL_ID, EN_COMPANY FROM CSSPMSTR22 Where EN_COMPANY <> '' ORDER BY EN_COMPANY ASC");
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

        $supplier_id = $_POST['supplier_id'];
        $supplier_name = "";
        foreach ($suppliers as $sup) {
            if ($sup['CSTMSPPL_ID'] == $supplier_id) {
                $supplier_name = $sup['EN_COMPANY'];
                break;
            }
        }

        $description_fg = "";
        foreach ($materialsList as $mat) {
            if ($mat['material_id'] == ($_POST['description_fg_id'] ?? '')) {
                $description_fg = $mat['item_name'];
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

        // บันทึกเสร็จ ไปที่หน้า Packing List ทันที
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <link rel="icon" type="image/png" href="assets/img/MATS.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .form-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; }
        .form-label { font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 14px; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; background-color: #ffffff !important; color: #1e293b !important; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        .btn-custom-submit { background-color: #2563eb; color: #ffffff; border: none; padding: 12px 28px; font-weight: 600; font-size: 15px; border-radius: 8px; transition: all 0.2s; }
        .btn-custom-submit:hover { background-color: #1d4ed8; transform: translateY(-1px); }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4 py-3">
    <div class="container-fluid px-4">
        <a href="receiving_index.php" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-home"></i></a>
        <span class="navbar-brand fs-5"><i class="fa-solid fa-file-invoice me-2"></i> CREATE PACKING LIST HEADER</span>
        <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-database me-1"></i> SQL Server</span>
    </div>
</nav>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-md-9">
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msgType; ?> shadow" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="form-card shadow">
                <div class="card-title-sub text-primary">
                    <i class="fa-solid fa-file-lines me-2"></i> บันทึกข้อมูล Packing List & Container Header
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Invoice No. (เลขที่ใบกำกับสินค้า)</label>
                                <input type="text" class="form-control" name="invoice_no" onfocus="if(this.value==this.defaultValue)this.value='';">
                            </div>                            
                            <div class="col-md-6">
                                <label class="form-label">Pack List No. (เลขที่แพ็คเกจลิสต์)</label>
                                <input type="text" class="form-control" name="pack_list_no" onfocus="if(this.value==this.defaultValue)this.value='';" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">PO No. (เลขที่ใบสั่งซื้อ)</label>
                                <input type="text" class="form-control" name="po_no" onfocus="if(this.value==this.defaultValue)this.value='';" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Materials (รายละเอียดสินค้า)</label>
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

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date (วันที่รับเข้า)</label>
                                <input type="date" class="form-control" name="receive_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Supplier (ผู้ผลิต)</label>
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

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Container No. (เลขตู้คอนเทนเนอร์)</label>
                                <input type="text" class="form-control" name="container_no" onfocus="if(this.value==this.defaultValue)this.value='';">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Seal No. (เลขซีลตู้)</label>
                                <input type="text" class="form-control" name="seal_no" onfocus="if(this.value==this.defaultValue)this.value='';">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">


                            <div class="col-md-6">
                                <label class="form-label">Country of Origin (PRIMARY_SMELT)</label>
                                <select class="form-select" name="country_of_origin" required>
                                    <option value="">-- เลือก Country --</option>
                                    <option value="MALAYSIA">MALAYSIA</option>
                                    <option value="AUSTRALIA">AUSTRALIA</option>
                                    <option value="INDIA">INDIA</option>
                                    <option value="INDIA">NEW ZEALAND</option>
                                    <option value="INDIA">SOUTH AFRICA</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Country of Secondary (SECONDARY_SMELT)</label>
                                <select class="form-select" name="country_of_secondary" required disabled>
                                    <option value="">-- เลือก Country --</option>
                                    <option value="MALAYSIA">MALAYSIA</option>
                                    <option value="AUSTRALIA">AUSTRALIA</option>
                                    <option value="INDIA">INDIA</option>
                                    <option value="INDIA">NEW ZEALAND</option>
                                    <option value="INDIA">SOUTH AFRICA</option>
                                </select>
                            </div>
                        </div>

                        <hr class="border-secondary my-4">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-custom-submit btn-lg py-3">
                                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> บันทึก และไปรายการ Packing List
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>