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

// Check authorization (WH, IT, FN have full access; others read-only)
$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$current_dept = strtoupper(trim($department_func));
$is_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

 if (!$is_authorized) {
    echo "<script>
            alert('เตือน: สิทธิ์การทำงานของคุณ ไม่ตรงกับกระบวนการนี้ กรุณาติดต่อผู้ดูแลระบบ');
            window.location.href = 'dashboard_mats.php';
          </script>";
 } 

// Search and filter parameters
$book_search = isset($_GET['book_search']) ? htmlspecialchars(trim($_GET['book_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from   = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to     = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';

require_once 'dbcon_mats-new.php';
require_once 'model/booking_shipment_model_mats.php';

$model = new BookingShipmentModel($conn);
$bookings = $model->getBookingList($book_search, $date_from, $date_to);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
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

        .btn-create-booking {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-booking:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); text-decoration: none; }

        /* Badges */
        .badge-book {
            display: inline-block;
            background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #bfdbfe;
            transition: all 0.2s ease;
        }
        .badge-book:hover { background-color: #1d4ed8; color: #ffffff; }

        .badge-inv {
            background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 11.5px;
            padding: 3px 8px; border-radius: 4px; border: 1px solid #a7f3d0;
        }
        .badge-no-inv {
            background-color: #f1f5f9; color: #64748b; font-weight: 600; font-size: 11px;
            padding: 3px 8px; border-radius: 4px;
        }

        /* Fixed Table Alignment */
        #booking_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #booking_table th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #booking_table td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #booking_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #booking_table tbody tr:hover td { background-color: #f0f9ff !important; }

        .dataTables_scrollBody thead tr,
        .dataTables_scrollBody thead th {
            height: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important;
            margin: 0 !important; border: none !important; visibility: hidden !important;
        }
        .dataTables_scrollBody thead th:before,
        .dataTables_scrollBody thead th:after { display: none !important; content: "" !important; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A6'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="booking_shipment_list_mats.php?func=<?php echo $folder_func ?>">
                        Overseas Booking Shipment
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
                <div class="filter-item" style="flex: 1.5; min-width: 240px;">
                    <label for="book_search">Search Booking / Invoice / Customer / Vessel</label>
                    <input class="form-control-minimal" name="book_search" id="book_search" type="text" 
                           placeholder="Enter Booking No, Invoice No, Customer..." value="<?php echo $book_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_booking()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_from">Booking Date From</label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_booking()"/>
                </div>
                <div class="filter-item" style="flex: 1; min-width: 150px;">
                    <label for="date_to">Booking Date To</label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_booking()"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" id="btn_search" onclick="search_booking()">
                        SEARCH
                    </button>
                    <?php if ($is_authorized): ?>
                        <a href="booking_shipment_entry_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-booking">
                            + CREATE NEW BOOKING
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Overseas Bookings List (Latest 200 Records)</h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="booking_table" class="table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center; width: 140px;">Booking No</th>
                                        <th style="text-align: center; width: 100px;">Booking Date</th>
                                        <th style="text-align: center; width: 130px;">Invoice No</th>
                                        <th style="text-align: left; width: 220px;">Customer (Consignee)</th>
                                        <th style="text-align: center; width: 120px;">Route (POL → POD)</th>
                                        <th style="text-align: left; width: 150px;">Vessel</th>
                                        <th style="text-align: center; width: 85px;">Containers</th>
                                        <th style="text-align: right; width: 85px;">Packages</th>
                                        <th style="text-align: right; width: 115px;">Total Wt (kg)</th>
                                        <th style="text-align: right; width: 95px;">CBM (m³)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($bookings as $row): 
                                    $book_no   = htmlspecialchars(trim($row['BOOK_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $raw_date  = !empty($row['BOOK_DATE']) ? date('Y-m-d', strtotime($row['BOOK_DATE'])) : '';
                                    $book_date = !empty($row['BOOK_DATE']) ? date('d/m/Y', strtotime($row['BOOK_DATE'])) : '-';
                                    $inv_no    = htmlspecialchars(trim($row['INVOICE_NO'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $cust_id   = htmlspecialchars(trim($row['CSTMSPPL_ID'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $cust_name = htmlspecialchars(trim($row['CONSIGNEE_COMPANY'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    $pol       = htmlspecialchars(trim($row['BSH1_PORTLOADING'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                    $pod       = htmlspecialchars(trim($row['BSH1_PORTDISCHARGE'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                    $vessel    = htmlspecialchars(trim($row['VESSEL'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                    $cnt_count = (int)($row['CONTAINER_COUNT'] ?? 0);
                                    $pkg       = number_format((float)($row['BSH1_TOTALPACKAGE'] ?? 0), 0);
                                    $wt        = number_format((float)($row['BSH1_TOTALWEIGHT'] ?? 0), 2);
                                    $cbm       = number_format((float)($row['BSH1_TOTALCUBICMETER'] ?? 0), 4);
                                ?>
                                    <tr onclick="go_to_detail('<?php echo $book_no; ?>')">
                                        <td align="center"><span class="badge-book"><?php echo $book_no; ?></span></td>
                                        <td align="center" data-order="<?php echo $raw_date; ?>" style="font-weight:600; color:#475569;"><?php echo $book_date; ?></td>
                                        <td align="center">
                                            <?php if ($inv_no !== ''): ?>
                                                <span class="badge-inv"><?php echo $inv_no; ?></span>
                                            <?php else: ?>
                                                <span class="badge-no-inv">Pending Invoice</span>
                                            <?php endif; ?>
                                        </td>
                                        <td align="left">
                                            <span style="font-weight:700; color:#1e293b;"><?php echo $cust_id; ?></span>
                                            <?php if ($cust_name !== ''): ?>
                                                <span style="color:#64748b; font-size:12px;"> - <?php echo $cust_name; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td align="center" style="font-weight:600; color:#334155;"><?php echo "{$pol} &rarr; {$pod}"; ?></td>
                                        <td align="left"><?php echo $vessel !== '' ? $vessel : '-'; ?></td>
                                        <td align="center"><span class="badge" style="background:#334155;"><?php echo $cnt_count; ?></span></td>
                                        <td align="right" style="font-weight:600;"><?php echo $pkg; ?></td>
                                        <td align="right" style="font-weight:600;"><?php echo $wt; ?></td>
                                        <td align="right" style="font-weight:700; color:#0f172a;"><?php echo $cbm; ?></td>
                                    </tr>
                                <?php endforeach; ?>
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

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var table = $("#booking_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[1, 'desc'], [0, 'desc']],
        columnDefs: [
            { targets: [0, 1, 2, 4, 6], className: 'text-center' },
            { targets: [7, 8, 9], className: 'text-right' },
            { targets: [3, 5], className: 'text-left' }
        ]
    });

    $(window).on('resize', function () {
        table.columns.adjust();
    });
    setTimeout(function() {
        table.columns.adjust();
    }, 200);
});

function search_booking() {
    var func   = document.getElementById("func").value;
    var search = document.getElementById("book_search").value;
    var dfrom  = document.getElementById("date_from").value;
    var dto    = document.getElementById("date_to").value;
    
    var url = 'booking_shipment_list_mats.php?func=' + encodeURIComponent(func) +
              '&book_search=' + encodeURIComponent(search) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto);
    window.location.assign(url);
}

function go_to_detail(book_no) {
    var func = document.getElementById("func").value;
    window.location.assign('booking_shipment_detail_mats.php?func=' + encodeURIComponent(func) + '&book_no=' + encodeURIComponent(book_no));
}
</script>
</body>
</html>