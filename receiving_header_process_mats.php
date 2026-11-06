<?php
// receiving_header_process_mats.php
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
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการนี้');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

// ดึงข้อมูล Supplier
$suppliers = [];
try {
    $supStmt = $conn->query("SELECT CSTMSPPL_ID, EN_COMPANY FROM CSSPMSTR22 WHERE (CSTMSPPL_ID='P-6309' OR CSTMSPPL_ID='P-N022' OR CSTMSPPL_ID='P-T172' OR CSTMSPPL_ID='R-T015')
     ORDER BY EN_COMPANY ASC");
    $suppliers = $supStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ดึงข้อมูล Materials
$materialsList = [];
try {
    $matListStmt = $conn->query("SELECT material_id, item_code, item_name FROM MATERIALS ORDER BY item_name ASC");
    $materialsList = $matListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ตรวจสอบการส่งแบบฟอร์ม POST
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

        header("Location: packing_list_process_mats.php?func=" . urlencode($folder_func));
        exit();

    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $msgType = "danger";
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <title>Meyer Aluminium - Create Packing List Header</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { 
            border: none !important; 
            border-bottom: 2px solid #cbd5e1 !important; 
            border-radius: 0 !important; 
            background-color: transparent !important; 
            height: 40px; 
            font-size: 15px; 
            font-weight: 600; 
            color: #1e293b; 
            outline: none; 
            width: 100%;
        }
        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }        
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }

        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }

        /* ปรับ wrapper ให้เต็ม 100% เพื่อให้เส้นใต้กว้างเท่าช่องอื่น */
        .supplier-picker-wrap {
            position: relative;
            width: 100%;
            cursor: pointer;
        }
        .supplier-picker-wrap input {
            cursor: pointer;
            padding-right: 35px;
            width: 100%;
        }
        .supplier-picker-icon {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #64748b;
            pointer-events: none;
        }

        /* ตาราง Supplier ใน Modal */
        .table-supplier-list tbody tr {
            cursor: pointer;
            transition: background 0.15s;
        }
        .table-supplier-list tbody tr:hover {
            background-color: #f1f5f9;
        }
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
                        📦 Receiving & Packing Process
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="tab-btn-group">
                <a href="packing_list_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn">
                   📋 Active OP Lists
                </a>
                <a href="receiving_header_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn active">
                   ➕ Create New
                </a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msgType; ?> shadow-sm mb-3" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-card">
                <h4 style="margin:0 0 20px 0; font-weight:700; color:#1e293b;">
                    📝 Packing List & Container Header
                </h4>

                <form method="POST" action="">
                    <div class="row" style="margin-bottom: 18px;">
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">INVOICE NO. (เลขที่ใบกำกับสินค้า)</label>
                            <input class="form-control-minimal" type="text" name="invoice_no" placeholder="กรอกเลขที่ Invoice..." oninput="this.value = this.value.toUpperCase()" />
                        </div>                        
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">PACK LIST NO. (เลขที่แพ็คเกจลิสต์) *</label>
                            <input class="form-control-minimal" type="text" name="pack_list_no" placeholder="กรอกเลขที่ Pack List..." oninput="this.value = this.value.toUpperCase()" required />
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 18px;">
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">PO NO. (เลขที่ใบสั่งซื้อ) *</label>
                            <input class="form-control-minimal" type="text" name="po_no" placeholder="กรอกเลขที่ PO..." oninput="this.value = this.value.toUpperCase()" required />
                        </div>
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">MATERIALS (รายละเอียดสินค้า) *</label>
                            <select class="form-control-minimal" name="description_fg_id" required>
                                <option value="">-- เลือก Materials --</option>
                                <?php foreach ($materialsList as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['material_id']); ?>">
                                        <?php echo htmlspecialchars($m['item_code'] . ' - ' . $m['item_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 18px;">
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">DATE (วันที่รับเข้า) *</label>
                            <input class="form-control-minimal" type="date" name="receive_date" value="<?php echo date('Y-m-d'); ?>" required />
                        </div>
                        
                        <!-- SUPPLIER POPUP PICKER (แก้เส้นใต้ให้ยาวเต็มช่อง) -->
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">SUPPLIER (ผู้ผลิต) *</label>
                            <div class="supplier-picker-wrap" data-toggle="modal" data-target="#supplierModal">
                                <input type="hidden" name="supplier_id" id="supplier_id" required />
                                <input class="form-control-minimal" type="text" id="supplier_display" placeholder="คลิกเพื่อเลือก Supplier..." readonly required />
                                <span class="supplier-picker-icon">🔍</span>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 18px;">
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">CONTAINER NO. (เลขตู้คอนเทนเนอร์)</label>
                            <input class="form-control-minimal" type="text" name="container_no" placeholder="กรอกเลข Container..." oninput="this.value = this.value.toUpperCase()"/>
                        </div>
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">SEAL NO. (เลขซีลตู้)</label>
                            <input class="form-control-minimal" type="text" name="seal_no" placeholder="กรอกเลข Seal..." oninput="this.value = this.value.toUpperCase()"/>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 24px;">
                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">COUNTRY OF LARGEST SMELT *</label>
                            <select class="form-control-minimal" name="country_of_origin" required>
                                <option value="">-- เลือก Country --</option>
                                <option value="MALAYSIA">MALAYSIA</option>
                                <option value="AUSTRALIA">AUSTRALIA</option>
                                <option value="INDIA">INDIA</option>
                                <option value="NEW ZEALAND">NEW ZEALAND</option>
                                <option value="SOUTH AFRICA">SOUTH AFRICA</option>
                            </select>
                        </div>

                        <div class="col-md-6 filter-item">
                            <label style="font-size:12px; font-weight:700; color:#64748b;">COUNTRY OF SECONDARY LARGEST SMELT *</label>
                            <select class="form-control-minimal" name="country_of_secondary" disabled>
                                <option value="">-- เลือก Country --</option>
                                <option value="MALAYSIA">MALAYSIA</option>
                                <option value="AUSTRALIA">AUSTRALIA</option>
                                <option value="INDIA">INDIA</option>
                                <option value="NEW ZEALAND">NEW ZEALAND</option>
                                <option value="SOUTH AFRICA">SOUTH AFRICA</option>
                            </select>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <a href="packing_list_process_mats.php?func=<?php echo $folder_func; ?>" class="btn btn-search" style="background-color: #64748b; line-height: 40px; text-decoration: none;">
                            Cancel
                        </a>
                        <button type="submit" class="btn-action-work" style="height: 40px; padding: 0 28px; font-size: 14px;">
                            💾 Save and Go to Packing List
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <input class="form-control" id="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Modal Popup สำหรับเลือก Supplier -->
<div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background-color: #1e293b; color: #fff; padding: 16px 20px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="supplierModalLabel" style="font-weight: 700; margin: 0; font-size: 16px;">
                    🔍 ค้นหาและเลือก Supplier (ผู้ผลิต)
                </h4>
            </div>
            <div class="modal-body" style="padding: 20px;">
                
                <!-- กล่องค้นหาแบบ Flexbox ชัดเจน ป้องกันปุ่มถูกซ่อนหรือตกบรรทัด -->
                <div style="display: flex; gap: 8px; margin-bottom: 15px;">
                    <input type="text" id="supplierSearchInput" class="form-control" 
                           placeholder="พิมพ์ รหัส หรือ ชื่อบริษัท แล้วกดค้นหา (หรือกด Enter)..." 
                           style="height: 42px; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; flex: 1;" />
                    
                    <button type="button" id="btnDoSearch" 
                            style="height: 42px; padding: 0 24px; font-weight: 700; font-size: 14px; border-radius: 8px; background-color: #2563eb; color: #ffffff; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;">
                        🔍 ค้นหา
                    </button>
                    
                    <button type="button" id="btnResetSearch" 
                            style="height: 42px; padding: 0 16px; font-weight: 600; font-size: 13px; border-radius: 8px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; cursor: pointer; white-space: nowrap;">
                        ล้างค่า
                    </button>
                </div>
                
                <div style="max-height: 380px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table class="table table-hover table-striped table-supplier-list" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #f8fafc; color: #475569; font-size: 13px;">
                                <th style="width: 25%; padding: 12px 16px;">Supplier ID</th>
                                <th style="padding: 12px 16px;">Company Name</th>
                                <th style="width: 15%; text-align: center; padding: 12px 16px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="supplierTableBody">
                            <?php foreach ($suppliers as $sup): ?>
                                <tr onclick="selectSupplier('<?php echo htmlspecialchars(addslashes($sup['CSTMSPPL_ID'])); ?>', '<?php echo htmlspecialchars(addslashes($sup['EN_COMPANY'])); ?>')">
                                    <td style="font-weight: 700; color: #1e293b; padding: 10px 16px;"><?php echo htmlspecialchars($sup['CSTMSPPL_ID']); ?></td>
                                    <td style="color: #334155; padding: 10px 16px;"><?php echo htmlspecialchars($sup['EN_COMPANY']); ?></td>
                                    <td style="text-align: center; padding: 10px 16px;">
                                        <button type="button" class="btn btn-xs btn-primary" style="border-radius: 4px; padding: 4px 10px;">เลือก</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 6px; font-weight: 600;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php';?>

<script>
// เลือก Supplier และปิด Modal
function selectSupplier(id, name) {
    document.getElementById('supplier_id').value = id;
    document.getElementById('supplier_display').value = id + ' - ' + name;
    $('#supplierModal').modal('hide');
}

// ฟังก์ชันค้นหาเมื่อกดปุ่มหรือกด Enter
function executeSupplierSearch() {
    var keyword = document.getElementById('supplierSearchInput').value.toLowerCase().trim();
    var rows = document.querySelectorAll('#supplierTableBody tr');

    rows.forEach(function(row) {
        var text = row.innerText.toLowerCase();
        row.style.display = (keyword === '' || text.indexOf(keyword) > -1) ? '' : 'none';
    });
}

// กดปุ่มค้นหา
document.getElementById('btnDoSearch').addEventListener('click', executeSupplierSearch);

// กดปุ่มล้างค่า
document.getElementById('btnResetSearch').addEventListener('click', function() {
    document.getElementById('supplierSearchInput').value = '';
    executeSupplierSearch();
    document.getElementById('supplierSearchInput').focus();
});

// กดปุ่ม Enter ในช่อง Input เพื่อค้นหา
document.getElementById('supplierSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        executeSupplierSearch();
    }
});

// เคลียร์และแสดงแถวทั้งหมดเมื่อเปิด Modal
$('#supplierModal').on('shown.bs.modal', function () {
    var searchInput = document.getElementById('supplierSearchInput');
    searchInput.value = '';
    var rows = document.querySelectorAll('#supplierTableBody tr');
    rows.forEach(function(row) { row.style.display = ''; });
    searchInput.focus();
});
</script>
</body>
</html>