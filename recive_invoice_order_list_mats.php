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

// Authorization: Warehouse (WH, FG), IT, Manager, Supervisor
$current_dept = strtoupper(trim($department_func));
$current_pos  = strtoupper(trim($position_func));


$is_authorized = ($folder_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

 if (!$is_authorized) {
    echo "<script>
            alert('เตือน: สิทธิ์การทำงานของคุณ ไม่ตรงกับกระบวนการนี้ กรุณาติดต่อผู้ดูแลระบบ');
            window.location.href = 'dashboard_mats.php';
          </script>";
 } 

$is_authorized = (
    in_array($current_dept, ['WH', 'FG', 'WAREHOUSE', 'IT']) || 
    strpos($current_pos, 'MANAGER') !== false || 
    strpos($current_pos, 'SUPERVISOR') !== false ||
    strpos($current_pos, 'SUPPERVISOR') !== false
);

// Search and filter parameters
$invoice_search = isset($_GET['invoice_search']) ? htmlspecialchars(trim($_GET['invoice_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from      = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to        = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Receive Material Orders</title>
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;
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

        .btn-create-job {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-job:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); text-decoration: none; }

        /* Badges */
        .badge-gr {
            display: inline-block;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #bfdbfe;
        }
        .po-editable {
            color: #0d9488; font-weight: 700; cursor: pointer; text-decoration: underline;
            padding: 4px 8px; border-radius: 4px; transition: background 0.15s;
        }
        .po-editable:hover { background-color: #ccfbf1; color: #0f766e; }

        /* Status Badges - Blue and Green only */
        .badge-status {
            font-weight: 700; font-size: 11px; padding: 3px 10px; border-radius: 4px; display: inline-block;
        }
        .badge-status-blue { background-color: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-status-green { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        /* Table Alignment & Prevent Double Header */
        #receiving_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #receiving_table thead th {
            background-color: #f8fafc !important; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #receiving_table tbody td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #receiving_table tbody tr { transition: background-color 0.15s ease-in-out; }
        #receiving_table tbody tr:hover td { background-color: #f0f9ff !important; }

        /* Modal fixes and high-contrast text styling */
        .modal { z-index: 1055 !important; }
        .modal-backdrop { z-index: 1040 !important; }
        .modal-content .form-control {
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 15px !important;
        }
        .modal-content .form-control[readonly] {
            color: #0f172a !important;
            background-color: #e2e8f0 !important;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="recive_invoice_order_list_mats.php?func=<?php echo $folder_func ?>">
                        Material Receive Management (Ingots)
                        <?php if (!$is_authorized): ?>
                            <span class="badge" style="background-color:#64748b; font-size:11px; margin-left:8px;">VIEW ONLY</span>
                        <?php else: ?>
                            <span class="badge" style="background-color:#059669; font-size:11px; margin-left:8px;">AUTHORIZED</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 220px;">
                    <label for="invoice_search">Invoice No / Supplier</label>
                    <input class="form-control-minimal" name="invoice_search" id="invoice_search" type="text" 
                           placeholder="Enter Invoice No..." value="<?php echo $invoice_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_data()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_from">Date From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_data()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_to">Date To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_data()"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_data()">
                        SEARCH
                    </button>
                    <?php if ($is_authorized): ?>
                        <a href="packing_list_process_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-job">
                            + NEW RECEIVE MATERIAL
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Receiving Header List (Top 200 Records)</h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="receiving_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">GR No</th>
                                        <th style="text-align: center;">Receive Date</th>
                                        <th style="text-align: center;">Invoice No</th>
                                        <th style="text-align: left;">Supplier Name</th>
                                        <th style="text-align: center;">Pack List No</th>
                                        <th style="text-align: center;">Container No</th>
                                        <th style="text-align: center;">Materials No</th>
                                        <th style="text-align: center;">Country</th>
                                        <th style="text-align: center;">PO No</th>
                                        <th style="text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    include("dbcon_mats-new.php");

                                    $params = [];
                                    $where_clauses = ["1=1"];

                                    if (!empty($invoice_search)) {
                                        $where_clauses[] = "(invoice_no LIKE :kw OR supplier_name LIKE :kw OR gr_no LIKE :kw)";
                                        $params[':kw'] = '%' . $invoice_search . '%';
                                    }
                                    if (!empty($date_from)) {
                                        $where_clauses[] = "receive_date >= :date_from";
                                        $params[':date_from'] = $date_from;
                                    }
                                    if (!empty($date_to)) {
                                        $where_clauses[] = "receive_date <= :date_to";
                                        $params[':date_to'] = $date_to;
                                    }

                                    $where_sql = implode(' AND ', $where_clauses);

                                    $sql = "SELECT TOP 200 
                                                   gr_no,
                                                   receive_date,
                                                   invoice_no,
                                                   supplier_name,
                                                   pack_list_no,
                                                   container_no,
                                                   materials_no,
                                                   country_of_origin,
                                                   po_no,
                                                   receive_status
                                            FROM RECEIVING_HEADER
                                            WHERE {$where_sql}
                                            ORDER BY receive_date DESC, gr_no DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);

                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $gr_no          = htmlspecialchars(trim($row['gr_no'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $raw_recv_date  = !empty($row['receive_date']) ? date('Y-m-d', strtotime($row['receive_date'])) : '';
                                        $recv_date      = !empty($row['receive_date']) ? date('d/m/Y', strtotime($row['receive_date'])) : '-';
                                        $invoice_no     = htmlspecialchars(trim($row['invoice_no'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $supplier_name  = htmlspecialchars(trim($row['supplier_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $pack_list_no   = htmlspecialchars(trim($row['pack_list_no'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $container_no   = htmlspecialchars(trim($row['container_no'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $materials_no   = htmlspecialchars(trim($row['materials_no'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $country        = htmlspecialchars(trim($row['country_of_origin'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $po_no          = htmlspecialchars(trim($row['po_no'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        
                                        $receive_status = strtoupper(trim($row['receive_status'] ?? 'ACTIVE'));
                                        
                                        $badge_status_class = 'badge-status-green';
                                        if ($receive_status === 'OP' || $receive_status === 'PENDING' || $receive_status === 'HL') {
                                            $badge_status_class = 'badge-status-blue';
                                        }

                                        echo "<tr>";
                                        echo "<td align='center'><span class='badge-gr'>{$gr_no}</span></td>";
                                        echo "<td align='center' data-order='{$raw_recv_date}' style='font-weight:600; color:#475569;'>{$recv_date}</td>";
                                        echo "<td align='center' style='font-weight:600;'>{$invoice_no}</td>";
                                        echo "<td align='left'>{$supplier_name}</td>";
                                        echo "<td align='center'>{$pack_list_no}</td>";
                                        echo "<td align='center'>{$container_no}</td>";
                                        echo "<td align='center' style='font-weight:600; color:#4338ca;'>{$materials_no}</td>";
                                        echo "<td align='center'>{$country}</td>";
                                        
                                        if ($is_authorized) {
                                            echo "<td align='center'><span class='po-editable' onclick=\"openEditModal('{$gr_no}', '{$invoice_no}', '{$po_no}', '{$supplier_name}')\">{$po_no}</span></td>";
                                        } else {
                                            echo "<td align='center' style='font-weight:600;'>{$po_no}</td>";
                                        }

                                        echo "<td align='center'><span class='badge-status {$badge_status_class}'>{$receive_status}</span></td>";
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
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<div class="modal fade" id="editPoModal" tabindex="-1" role="dialog" aria-labelledby="editPoModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background-color: #1e293b; color: #fff; border-top-left-radius: 12px; border-top-right-radius: 12px; padding: 16px 20px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.9; text-shadow: none; font-size: 24px;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editPoModalLabel" style="font-weight: 700; font-size: 16px; margin:0;">Edit Purchase Order (By Invoice)</h4>
            </div>
            <div class="modal-body" style="padding: 24px; background-color: #ffffff;">
                <form id="editPoForm" onsubmit="event.preventDefault(); savePoNo();">
                    <input type="hidden" id="modal_invoice_no" name="invoice_no">
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-weight: 700; color: #475569; font-size: 12.5px; text-transform: uppercase; display: block; margin-bottom: 6px;">Invoice No (Target Group)</label>
                        <input type="text" id="display_invoice_no" class="form-control" readonly style="background-color: #f8fafc; color: #1e293b; font-weight: 700; font-size: 15px; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="font-weight: 700; color: #475569; font-size: 12.5px; text-transform: uppercase; display: block; margin-bottom: 6px;">Supplier</label>
                        <input type="text" id="display_supplier" class="form-control" readonly style="background-color: #f8fafc; color: #1e293b; font-weight: 700; font-size: 15px; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="modal_po_no" style="font-weight: 700; color: #0f172a; font-size: 13px; text-transform: uppercase; display: block; margin-bottom: 6px;">New PO No (Will update all rows with this Invoice)</label>
                        <input type="text" id="modal_po_no" name="po_no" class="form-control" required placeholder="Enter PO No..." style="color: #0f172a; font-weight: 700; font-size: 16px; border: 2px solid #0d9488 !important; background-color: #f0fdfa !important; height: 45px;">
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="background-color: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; padding: 14px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 8px; font-weight: 600; padding: 8px 16px; color: #475569; background-color: #e2e8f0; border: none;">Cancel</button>
                <button type="button" class="btn btn-success" onclick="savePoNo()" style="border-radius: 8px; font-weight: 700; font-size: 14px; padding: 9px 20px; background-color: #059669; color: #ffffff; border: none; box-shadow: 0 2px 4px rgba(5,150,105,0.2);">Update All by Invoice</button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var table = $("#receiving_table").DataTable({
        autoWidth: false,
        scrollX: false,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']],
        columnDefs: [
            { targets: [0, 1, 2, 4, 5, 6, 7, 8, 9], className: 'text-center' },
            { targets: [3], className: 'text-left' }
        ]
    });

    $('#editPoModal').appendTo('body');
});

function search_data() {
    var func = document.getElementById("func").value;
    var search = document.getElementById("invoice_search").value;
    var dfrom = document.getElementById("date_from").value;
    var dto = document.getElementById("date_to").value;
    
    var url = 'recive_invoice_order_list_mats.php?func=' + encodeURIComponent(func) +
              '&invoice_search=' + encodeURIComponent(search) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto);
    window.location.assign(url);
}

function openEditModal(grNo, invoiceNo, poNo, supplier) {
    $('#modal_invoice_no').val(invoiceNo);
    $('#display_invoice_no').val(invoiceNo);
    $('#display_supplier').val(supplier);
    $('#modal_po_no').val(poNo);
    
    $('#editPoModal').modal('show');
    setTimeout(function() {
        $('#modal_po_no').focus().select();
    }, 300);
}

function savePoNo() {
    var invoiceNo = $('#modal_invoice_no').val();
    var poNo = $('#modal_po_no').val();

    if (!poNo.trim()) {
        alert('Please enter PO No.');
        return;
    }

    $.ajax({
        url: 'model/update_po_receive_mats.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'update_po_by_invoice',
            invoice_no: invoiceNo,
            po_no: poNo
        },
        success: function(response) {
            if (response.status === 'success') {
                $('#editPoModal').modal('hide');
                alert(response.message || 'Updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.message || 'Failed to update'));
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while saving.');
            console.error(error);
        }
    });
}
</script>
</body>
</html>