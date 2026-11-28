<?php
// Start session
session_start();

// Retrieve session variables (อ้างอิงตาม saleorder_list_mats.php)
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

$is_authorized = ($folder_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

 if (!$is_authorized) {
    echo "<script>
            alert('เตือน: สิทธิ์การทำงานของคุณ ไม่ตรงกับกระบวนการนี้ กรุณาติดต่อผู้ดูแลระบบ');
            window.location.href = 'dashboard_mats.php';
          </script>";
 } 

// Check authorization ตามเงื่อนไข rc1 (WH, MK, PP, IT)
$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));

$is_wh_authorized = ($current_dept === 'WH');
$is_dr_authorized = in_array($current_dept, ['MK', 'PP', 'FN']);
$is_it_authorized = ($current_dept === 'IT' || $current_func === 'IT');
$can_create_or_edit = ($is_wh_authorized || $is_dr_authorized || $is_it_authorized);
$can_upload_edi     = ($is_wh_authorized || $is_it_authorized);

// Search and filter parameters
$rc_search    = isset($_GET['rc_search']) ? htmlspecialchars(trim($_GET['rc_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from    = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to      = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';
$receive_type = isset($_GET['receive_type']) ? htmlspecialchars(trim($_GET['receive_type']), ENT_QUOTES, 'UTF-8') : '';
$tab_status   = isset($_GET['tab']) && $_GET['tab'] === 'COMPLETED' ? 'COMPLETED' : 'PENDING';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Receive Product & Scrap</title>
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

        .btn-create-rc {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-rc:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); text-decoration: none; }

        .btn-upload-edi {
            background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 18px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
        }
        .btn-upload-edi:hover { background-color: #1d4ed8; color: #ffffff; transform: translateY(-1px); }

        /* Status Tabs (Pending vs Completed) */
        .status-tabs {
            display: flex; gap: 10px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;
        }
        .tab-btn {
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 700;
            text-decoration: none; color: #64748b; background: #f1f5f9; border: 1px solid transparent;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }
        .tab-btn.active-pending {
            background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe;
        }
        .tab-btn.active-completed {
            background: #ecfdf5; color: #047857; border-color: #a7f3d0;
        }

        /* Badges */
        .badge-inv {
            display: inline-block;
            background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #a7f3d0;
            transition: all 0.2s ease;
        }
        .badge-inv:hover { background-color: #047857; color: #ffffff; }

        .badge-type {
            font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .type-rc { background-color: #e0e7ff; color: #3730a3; }
        .type-rs { background-color: #fef3c7; color: #92400e; }
        .type-rt { background-color: #fee2e2; color: #991b1b; }
        .type-dr { background-color: #f3e8ff; color: #6b21a8; }

        .badge-status {
            font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block;
        }
        .badge-status-op { background-color: #dbeafe; color: #1d4ed8; }
        .badge-status-ac { background-color: #dcfce7; color: #15803d; }

        /* Table Alignment */
        #rc_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #rc_table th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #rc_table td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #rc_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #rc_table tbody tr:hover td { background-color: #f0f9ff !important; }

        .dataTables_scrollBody thead tr, .dataTables_scrollBody thead th {
            height: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important;
            margin: 0 !important; border: none !important; visibility: hidden !important;
        }
        .dataTables_scrollBody thead th:before, .dataTables_scrollBody thead th:after {
            display: none !important; content: "" !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A5'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="receive_pd_sc_process_mats.php?func=<?php echo $folder_func; ?>">
                        Receive Product & Scrap Management
                        <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">
                            DEPT: <?php echo htmlspecialchars($current_dept ?: 'VIEW ONLY'); ?>
                        </span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 220px;">
                    <label for="rc_search">Search Invoice / Product / Ref No</label>
                    <input class="form-control-minimal" name="rc_search" id="rc_search" type="text" 
                           placeholder="Enter Invoice No, Product No, Supplier..." value="<?php echo $rc_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_rc()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 140px;">
                    <label for="receive_type">Receive Type</label>
                    <select class="form-control" name="receive_type" id="receive_type" onchange="search_rc()" style="height:40px; font-weight:600;">
                        <option value="">-- ALL TYPES --</option>
                        <?php if ($is_wh_authorized || $is_it_authorized): ?>
                            <option value="RC" <?php echo $receive_type === 'RC' ? 'selected' : ''; ?>>RC: RECEIVE PRODUCT</option>
                            <option value="RS" <?php echo $receive_type === 'RS' ? 'selected' : ''; ?>>RS: RECEIVE SCRAP</option>
                            <option value="RT" <?php echo $receive_type === 'RT' ? 'selected' : ''; ?>>RT: RETURN PRODUCT</option>
                        <?php endif; ?>
                        <?php if ($is_dr_authorized || $is_it_authorized): ?>
                            <option value="DR" <?php echo $receive_type === 'DR' ? 'selected' : ''; ?>>DR: DIRECT SHIPMENT</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 140px;">
                    <label for="date_from">Receive From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_rc()" style="height:40px;"/>
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 140px;">
                    <label for="date_to">Receive To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_rc()" style="height:40px;"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_rc()">SEARCH</button>
                    
                    <?php if ($can_upload_edi): ?>
                        <button type="button" class="btn-upload-edi" data-toggle="modal" data-target="#ediUploadModal">
                            UPLOAD EDI (.CSV)
                        </button>
                    <?php endif; ?>

                    <?php if ($can_create_or_edit): ?>
                        <a href="rcceive_pd_sc_entry_mat.php?func=<?php echo $folder_func; ?>" class="btn-create-rc">
                            + NEW RECEIVE
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <!-- Tabs: รายการที่รอดำเนินการ vs รายการที่ทำไปแล้ว -->
                        <div class="status-tabs">
                            <a href="javascript:void(0)" onclick="switch_tab('PENDING')" 
                               class="tab-btn <?php echo $tab_status === 'PENDING' ? 'active-pending' : ''; ?>">
                                <span>⏳ รายการที่รอดำเนินการ (Pending / Open)</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switch_tab('COMPLETED')" 
                               class="tab-btn <?php echo $tab_status === 'COMPLETED' ? 'active-completed' : ''; ?>">
                                <span>✅ รายการที่ทำไปแล้ว (Completed / Accepted)</span>
                            </a>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                <?php echo $tab_status === 'PENDING' ? 'Pending Receive Invoices (รอดำเนินการ / รอ Inspect)' : 'Completed Receive Invoices (ดำเนินการเสร็จสิ้นแล้ว)'; ?>
                            </h4>
                            <span style="font-size:12px; color:#64748b;">* คลิกที่รายการเพื่อดูรายละเอียด แก้ไข หรือลบข้อมูล</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="rc_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 150px;">Invoice No.</th>
                                        <th style="text-align: center; width: 100px;">Receive Date</th>
                                        <th style="text-align: center; width: 80px;">Type</th>
                                        <th style="text-align: center; width: 90px;">Product</th>
                                        <th style="text-align: center; width: 90px;">From Org.</th>
                                        <th style="text-align: center; width: 100px;">Material In</th>
                                        <th style="text-align: left; width: 180px;">Supplier / Customer</th>
                                        <th style="text-align: right; width: 80px;">Items</th>
                                        <th style="text-align: right; width: 120px;">Net Weight (kg)</th>
                                        <th style="text-align: center; width: 100px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");
                                    require_once("model/rc_receive_model.php");

                                    $rcModel = new RcReceiveModel($conn);
                                    $rows = $rcModel->rc_get_invoice_list($current_dept, [
                                        'search'       => $rc_search,
                                        'date_from'    => $date_from,
                                        'date_to'      => $date_to,
                                        'receive_type' => $receive_type,
                                        'tab_status'   => $tab_status
                                    ]);

                                    foreach ($rows as $row) {
                                        $invNo      = htmlspecialchars(trim($row['INVOICE_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $rawDate    = !empty($row['RECEIVE_DATE']) ? date('Y-m-d', strtotime($row['RECEIVE_DATE'])) : '';
                                        $recDate    = !empty($row['RECEIVE_DATE']) ? date('d/m/Y', strtotime($row['RECEIVE_DATE'])) : '-';
                                        $rType      = strtoupper(trim($row['RECEIVE_TYPE'] ?? ''));
                                        $prodId     = htmlspecialchars(trim($row['PRODUCT_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $orgProdId  = htmlspecialchars(trim($row['ORGPRODUCT_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $matIn      = htmlspecialchars(trim($row['MATERIAL_IN'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $supId      = htmlspecialchars(trim($row['CSTMSPPL_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $supName    = htmlspecialchars(trim($row['CONSIGNEE_COMPANY'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $itemCount  = (int)$row['ITEM_COUNT'];
                                        $netWeight  = number_format((float)$row['TOTAL_NETWEIGHT'], 0);
                                        $openCount  = (int)$row['OPEN_COUNT'];

                                        // สี Badge ตาม Receive Type
                                        $typeClass = 'type-rc';
                                        if ($rType === 'RS') $typeClass = 'type-rs';
                                        if ($rType === 'RT') $typeClass = 'type-rt';
                                        if ($rType === 'DR') $typeClass = 'type-dr';

                                        // สถานะภาพรวมของ Invoice
                                        if ($itemCount === 0) {
                                            $statusText  = 'NO ITEMS';
                                            $statusClass = 'badge-status-op';
                                        } elseif ($openCount > 0) {
                                            $statusText  = "OPEN ({$openCount}/{$itemCount})";
                                            $statusClass = 'badge-status-op';
                                        } else {
                                            $statusText  = 'COMPLETED';
                                            $statusClass = 'badge-status-ac';
                                        }

                                        echo "<tr onclick=\"go_to_detail('{$invNo}')\">";
                                        echo "<td align='center'><span class='badge-inv'>{$invNo}</span></td>";
                                        echo "<td align='center' data-order='{$rawDate}' style='font-weight:600; color:#475569;'>{$recDate}</td>";
                                        echo "<td align='center'><span class='badge-type {$typeClass}'>{$rType}</span></td>";
                                        echo "<td align='center' style='font-weight:700;'>{$prodId}</td>";
                                        echo "<td align='center' style='color:#64748b; font-weight:600;'>{$orgProdId}</td>";
                                        echo "<td align='center' style='font-weight:600;'>{$matIn}</td>";
                                        echo "<td align='left'><strong>{$supId}</strong>" . ($supName ? " : {$supName}" : "") . "</td>";
                                        echo "<td align='right' style='font-weight:600;'>{$itemCount}</td>";
                                        echo "<td align='right' style='font-weight:700; color:#0f172a;'>{$netWeight}</td>";
                                        echo "<td align='center'><span class='badge-status {$statusClass}'>{$statusText}</span></td>";
                                        echo "</tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <input type="hidden" id="tab_status" value="<?php echo $tab_status; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- Modal: Upload EDI (.csv) อ้างอิงจากแท็บ Upload Receive เดิม -->
<?php if ($can_upload_edi): ?>
<div class="modal fade" id="ediUploadModal" tabindex="-1" role="dialog" aria-labelledby="ediModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; border-radius:12px 12px 0 0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ediModalLabel" style="font-weight:700; color:#1e293b;">Upload Receive EDI File (.CSV)</h4>
            </div>
            <div class="modal-body" style="padding:20px;">
                <form id="frmEdiUpload">
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:700; color:#dc2626;">FILE NAME UPLOAD (*.CSV) *</label>
                        <input type="file" class="form-control" id="edi_file" name="edi_file" accept=".csv" required>
                        <small class="text-muted">รองรับไฟล์ CSV รูปแบบ MALS (41 คอลัมน์)</small>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px; font-weight:700; color:#475569;">SUPPLIER OR CUSTOMER</label>
                        <select class="form-control" id="edi_supplier_id" name="supplier_id">
                            <option value="MAL" selected>MAL: MEYER ALUMINIUM LIMITED</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px; font-weight:700; color:#475569;">MATERIAL IN (BOI)</label>
                                <select class="form-control" id="edi_material_in" name="material_in">
                                    <option value="BOI-IMP1" selected>BOI-IMP1</option>
                                    <option value="BOI-IMP2">BOI-IMP2</option>
                                    <option value="NONE-BOI">NONE-BOI</option>
                                    <option value="BOI-MAT1">BOI-MAT1</option>
                                    <option value="BOI-MAT2">BOI-MAT2</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px; font-weight:700; color:#475569;">UPLOAD DATE</label>
                                <input type="date" class="form-control" id="edi_upload_date" name="upload_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 12px 12px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnConfirmUpload" onclick="submit_edi_upload()" style="font-weight:700;">
                    Confirm & Upload
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var table = $("#rc_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']],
        columnDefs: [
            { targets: [0, 1, 2, 3, 4, 5, 9], className: 'text-center' },
            { targets: [7, 8], className: 'text-right' },
            { targets: [6], className: 'text-left' }
        ]
    });

    $(window).on('resize', function () { table.columns.adjust(); });
    setTimeout(function() { table.columns.adjust(); }, 200);
});

function switch_tab(tabName) {
    document.getElementById("tab_status").value = tabName;
    search_rc();
}

function search_rc() {
    var func  = document.getElementById("func").value;
    var kw    = document.getElementById("rc_search").value;
    var rtype = document.getElementById("receive_type").value;
    var dfrom = document.getElementById("date_from").value;
    var dto   = document.getElementById("date_to").value;
    var tab   = document.getElementById("tab_status").value;
    
    var url = 'receive_pd_sc_process_mats.php?func=' + encodeURIComponent(func) +
              '&rc_search=' + encodeURIComponent(kw) +
              '&receive_type=' + encodeURIComponent(rtype) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto) +
              '&tab=' + encodeURIComponent(tab);
    window.location.assign(url);
}

// กดแสดงข้อมูล -> วิ่งไปหน้า rcceive_pd_sc_detail_mat.php
function go_to_detail(invoiceNo) {
    var func = document.getElementById("func").value;
    window.location.assign('rcceive_pd_sc_detail_mat.php?func=' + encodeURIComponent(func) + '&inv=' + encodeURIComponent(invoiceNo));
}

// ส่งไฟล์ CSV ไปประมวลผลที่ model/rc_receive_model.php
async function submit_edi_upload() {
    var fileInput = document.getElementById('edi_file');
    if (!fileInput.files.length) {
        alert('กรุณาเลือกไฟล์ CSV ก่อนอัปโหลด');
        return;
    }
    if (!confirm('คุณยืนยันการนำเข้าข้อมูลเข้าฐานข้อมูลหรือไม่?')) return;

    var btn = document.getElementById('btnConfirmUpload');
    btn.disabled = true;
    btn.innerText = 'Uploading...';

    var formData = new FormData();
    formData.append('action', 'rc_upload_edi');
    formData.append('edi_file', fileInput.files[0]);
    formData.append('supplier_id', document.getElementById('edi_supplier_id').value);
    formData.append('material_in', document.getElementById('edi_material_in').value);
    formData.append('upload_date', document.getElementById('edi_upload_date').value);

    try {
        const res = await fetch('model/rc_receive_model.php', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        alert(result.message);
        if (result.status === 'success') {
            window.location.reload();
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Confirm & Upload';
    }
}
</script>
</html>