<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func  = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$current_dept  = strtoupper(trim($department_func));
$is_authorized = ($current_func === 'WH' || $current_dept === 'IT' || $current_dept === 'FN');

$bookNo = isset($_GET['book_no']) ? strtoupper(trim($_GET['book_no'])) : '';
if ($bookNo === '') {
    header("Location: booking_shipment_list_mats.php?func=" . urlencode($folder_func));
    exit();
}

require_once 'dbcon_mats-new.php';
require_once 'model/booking_shipment_model_mats.php';

$model     = new BookingShipmentModel($conn);
$detail    = null;
$loadError = '';

try {
    $detail = $model->getBookingDetail($bookNo);
} catch (Exception $e) {
    $loadError = $e->getMessage();
}

function fmtDate($d) {
    return !empty($d) ? date('d/m/Y', strtotime($d)) : '-';
}
function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Booking Detail <?php echo h($bookNo); ?> - Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155;
        }
        .main-panel { background-color: #f8fafc !important; }

        .detail-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 22px 24px; margin-bottom: 20px; border: 1px solid #e2e8f0;
        }
        .card-header-bar {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 18px;
        }
        .card-header-bar h4 { margin: 0; font-size: 15.5px; font-weight: 700; color: #0f172a; }

        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 14px; margin-bottom: 20px;
        }
        .kpi-box {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px;
            padding: 14px 18px; border-left: 4px solid #2563eb;
        }
        .kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; }
        .kpi-value { font-size: 19px; font-weight: 700; color: #0f172a; margin-top: 4px; }

        .info-item { margin-bottom: 12px; }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; }
        .info-val { font-size: 13.5px; font-weight: 600; color: #1e293b; }

        .addr-panel {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px; height: 100%; font-size: 13px;
        }
        .addr-panel h5 {
            margin: 0 0 8px 0; font-size: 12.5px; font-weight: 700; color: #1d4ed8;
            text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px;
        }

        .table th { background: #f8fafc; color: #475569; font-size: 11.5px; text-transform: uppercase; }
        .table td { font-size: 13px; vertical-align: middle !important; }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="#">
                        Booking Shipment Detail : <span style="color:#2563eb;"><?php echo h($bookNo); ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Top Action Bar -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
                <a href="booking_shipment_list_mats.php?func=<?php echo $folder_func; ?>" class="btn btn-default" style="font-weight:600; border-radius:8px;">
                     ⬅️ Back To Bookings List
                </a>

                <?php if ($detail && $is_authorized): ?>
                    <div style="display:flex; gap:10px;">
                        <a href="booking_shipment_entry_mats.php?func=<?php echo $folder_func; ?>&book_no=<?php echo urlencode($bookNo); ?>" 
                           class="btn btn-warning" style="font-weight:700; border-radius:8px; padding:8px 20px;">
                            EDIT BOOKING
                        </a>
                        <button type="button" class="btn btn-danger" style="font-weight:700; border-radius:8px; padding:8px 20px;" 
                                onclick="deleteBooking('<?php echo h($bookNo); ?>')">
                            DELETE BOOKING
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($loadError !== ''): ?>
                <div class="alert alert-danger"><?php echo h($loadError); ?></div>
            <?php else: 
                $head       = $detail['heading'];
                $addr       = $detail['address'];
                $containers = $detail['containers'];
                $products   = $detail['assigned_products'];
            ?>

            <!-- KPI Summary Strip -->
            <div class="kpi-grid">
                <div class="kpi-box">
                    <div class="kpi-label">Invoice No.</div>
                    <div class="kpi-value" style="color:<?php echo !empty($head['INVOICE_NO']) ? '#059669' : '#64748b'; ?>;">
                        <?php echo !empty($head['INVOICE_NO']) ? h($head['INVOICE_NO']) : 'Pending Invoice'; ?>
                    </div>
                </div>
                <div class="kpi-box" style="border-left-color:#0284c7;">
                    <div class="kpi-label">Total Containers</div>
                    <div class="kpi-value"><?php echo count($containers); ?></div>
                </div>
                <div class="kpi-box" style="border-left-color:#059669;">
                    <div class="kpi-label">Total Packages</div>
                    <div class="kpi-value"><?php echo number_format((float)($head['BSH1_TOTALPACKAGE'] ?? 0), 0); ?></div>
                </div>
                <div class="kpi-box" style="border-left-color:#d97706;">
                    <div class="kpi-label">Total Weight (kg)</div>
                    <div class="kpi-value"><?php echo number_format((float)($head['BSH1_TOTALWEIGHT'] ?? 0), 2); ?></div>
                </div>
                <div class="kpi-box" style="border-left-color:#7c3aed;">
                    <div class="kpi-label">Total CBM (m³)</div>
                    <div class="kpi-value"><?php echo number_format((float)($head['BSH1_TOTALCUBICMETER'] ?? 0), 4); ?></div>
                </div>
            </div>

            <!-- 1. BOOKING HEADING DETAIL -->
            <div class="detail-card">
                <div class="card-header-bar">
                    <h4>1. Booking & Shipment Heading</h4>
                    <span class="badge" style="background:#1e293b;">Type: Oversea (O)</span>
                </div>

                <div class="row">
                    <div class="col-md-3 info-item">
                        <span class="info-label">Booking No.</span>
                        <span class="info-val" style="color:#1d4ed8; font-size:15px;"><?php echo h($head['BOOK_NO']); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Shipper</span>
                        <span class="info-val"><?php echo h($head['COMPANY_ID'] . ($head['SHIPPER_NAME'] ? ' : ' . $head['SHIPPER_NAME'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Consignee (Customer)</span>
                        <span class="info-val"><?php echo h($head['CSTMSPPL_ID'] . ($addr['CONSIGNEE_COMPANY'] ? ' : ' . $addr['CONSIGNEE_COMPANY'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Volume / Attention / Doc From</span>
                        <span class="info-val">
                            <?php echo h($head['VOLUME'] ?: '-'); ?> | Attn: <?php echo h($head['ATTENTION'] ?: '-'); ?> | From: <?php echo h($head['DOC_FROM'] ?: '-'); ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-2 info-item">
                        <span class="info-label">Booking Date</span>
                        <span class="info-val"><?php echo fmtDate($head['BOOK_DATE']); ?></span>
                    </div>
                    <div class="col-md-2 info-item">
                        <span class="info-label">CY Date (From Yard)</span>
                        <span class="info-val"><?php echo fmtDate($head['FROM_YARD']); ?></span>
                    </div>
                    <div class="col-md-2 info-item">
                        <span class="info-label">Return Date</span>
                        <span class="info-val"><?php echo fmtDate($head['RETURN_YARD']); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Expect Delivery (ETD)</span>
                        <span class="info-val"><?php echo fmtDate($head['EXPECT_DELIVERY']); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Expect Arrival (ETA)</span>
                        <span class="info-val"><?php echo fmtDate($head['EXPECT_ARRIVAL']); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 info-item">
                        <span class="info-label">Port of Loading</span>
                        <span class="info-val"><?php echo h($head['BSH1_PORTLOADING'] . ($head['PORT_LOADING_NAME'] ? ' : ' . $head['PORT_LOADING_NAME'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Port of Discharge</span>
                        <span class="info-val"><?php echo h($head['BSH1_PORTDISCHARGE'] . ($head['PORT_DISCHARGE_NAME'] ? ' : ' . $head['PORT_DISCHARGE_NAME'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Place Destination</span>
                        <span class="info-val"><?php echo h($head['BSH1_PLACEDESTINATION'] . ($head['PLACE_DEST_NAME'] ? ' : ' . $head['PLACE_DEST_NAME'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Country Destination</span>
                        <span class="info-val"><?php echo h($head['BSH1_COUNTRYDESTINATION'] . ($head['COUNTRY_DEST_NAME'] ? ' : ' . $head['COUNTRY_DEST_NAME'] : '')); ?></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 info-item">
                        <span class="info-label">Freight</span>
                        <span class="info-val"><?php echo h($head['FREIGHT_ID'] . ($head['FREIGHT_NAME'] ? ' : ' . $head['FREIGHT_NAME'] : '')); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Feeder</span>
                        <span class="info-val"><?php echo h($head['FEEDER'] ?: '-'); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Vessel</span>
                        <span class="info-val"><?php echo h($head['VESSEL'] ?: '-'); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <span class="info-label">Goods Description</span>
                        <span class="info-val"><?php echo h($head['BSH1_DESCRIPTION'] ?: '-'); ?></span>
                    </div>
                </div>

                <!-- Shipping Marks -->
                <div style="background:#f8fafc; padding:12px 16px; border-radius:8px; border:1px solid #e2e8f0; margin-top:6px;">
                    <span class="info-label" style="margin-bottom:6px;">Shipping Marks</span>
                    <?php
                        $marks = array_filter([
                            $head['BSH1_SHIPPINGMARK'] ?? '',
                            $head['BSH1_SHIPPINGMARK1'] ?? '',
                            $head['BSH1_SHIPPINGMARK2'] ?? '',
                            $head['BSH1_SHIPPINGMARK3'] ?? '',
                            $head['BSH1_SHIPPINGMARK4'] ?? '',
                            $head['BSH1_SHIPPINGMARK5'] ?? '',
                            $head['BSH1_SHIPPINGMARK6'] ?? '',
                            $head['BSH1_SHIPPINGMARK7'] ?? ''
                        ], function($m) { return trim($m) !== ''; });
                    ?>
                    <?php if (empty($marks)): ?>
                        <span class="text-muted">-</span>
                    <?php else: ?>
                        <div style="font-family: monospace; font-size:13px; color:#0f172a; line-height:1.6;">
                            <?php foreach ($marks as $m): ?>
                                <div><?php echo h($m); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. CUSTOMER ADDRESSES -->
            <div class="detail-card">
                <div class="card-header-bar">
                    <h4>2. Consignee, Notify & Also Notify Address</h4>
                </div>
                <div class="row">
                    <?php 
                    $parties = [
                        ['title' => 'Consignee', 'prefix' => 'CONSIGNEE'],
                        ['title' => 'Notify Party', 'prefix' => 'NOTIFY'],
                        ['title' => 'Also Notify Party', 'prefix' => 'ALSONOTIFY']
                    ];
                    foreach ($parties as $pt):
                        $p = $pt['prefix'];
                        $comp = $addr[$p . '_COMPANY'] ?? '';
                    ?>
                    <div class="col-md-4">
                        <div class="addr-panel">
                            <h5><?php echo $pt['title']; ?></h5>
                            <?php if ($comp === '' && empty($addr[$p . '_ADDRESS1'])): ?>
                                <span class="text-muted">ไม่มีข้อมูล</span>
                            <?php else: ?>
                                <div style="font-weight:700; color:#0f172a; margin-bottom:4px;"><?php echo h($comp); ?></div>
                                <div><?php echo h($addr[$p . '_ADDRESS1'] ?? ''); ?></div>
                                <div><?php echo h($addr[$p . '_ADDRESS2'] ?? ''); ?></div>
                                <div style="margin-bottom:8px;"><?php echo h($addr[$p . '_ADDRESS3'] ?? ''); ?></div>
                                <?php if (!empty($addr[$p . '_CONTACT'])): ?>
                                    <div><strong>Contact:</strong> <?php echo h($addr[$p . '_CONTACT']); ?> <?php echo !empty($addr[$p . '_POSITION']) ? '(' . h($addr[$p . '_POSITION']) . ')' : ''; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($addr[$p . '_TELEPHONE'])): ?>
                                    <div><strong>Tel:</strong> <?php echo h($addr[$p . '_TELEPHONE']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($addr[$p . '_FAX'])): ?>
                                    <div><strong>Fax:</strong> <?php echo h($addr[$p . '_FAX']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($addr[$p . '_EMAIL'])): ?>
                                    <div><strong>Email:</strong> <?php echo h($addr[$p . '_EMAIL']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. CONTAINER DETAILS -->
            <div class="detail-card">
                <div class="card-header-bar">
                    <h4>3. Container Details (<?php echo count($containers); ?> Containers)</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:50px;">#</th>
                                <th>Container No.</th>
                                <th>Seal No.</th>
                                <th class="text-center">UOM</th>
                                <th class="text-right">Packages</th>
                                <th class="text-right">Net Weight (kg)</th>
                                <th class="text-right">Gross Weight (kg)</th>
                                <th class="text-right">Cubic Meter (m³)</th>
                                <th class="text-right">Diff. UOM Net/Gross</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($containers)): ?>
                                <tr><td colspan="9" class="text-center text-muted">ยังไม่มีข้อมูลตู้คอนเทนเนอร์</td></tr>
                            <?php else: ?>
                                <?php foreach ($containers as $idx => $c): ?>
                                <tr>
                                    <td class="text-center"><?php echo $idx + 1; ?></td>
                                    <td><strong style="color:#1d4ed8;"><?php echo h($c['CONTAINER_NO']); ?></strong></td>
                                    <td><?php echo h($c['SEAL_NO'] ?: '-'); ?></td>
                                    <td class="text-center"><?php echo h($c['BSH2_UOM'] ?: '-'); ?></td>
                                    <td class="text-right" style="font-weight:600;"><?php echo number_format((float)($c['BSH2_PACKAGE'] ?? 0), 0); ?></td>
                                    <td class="text-right"><?php echo number_format((float)($c['BSH2_NETWEIGHT'] ?? 0), 2); ?></td>
                                    <td class="text-right" style="font-weight:600;"><?php echo number_format((float)($c['BSH2_GROSSWEIGHT'] ?? 0), 2); ?></td>
                                    <td class="text-right" style="font-weight:600;"><?php echo number_format((float)($c['BSH2_CUBICMETER'] ?? 0), 4); ?></td>
                                    <td class="text-right">
                                        <?php echo number_format((float)($c['BS2U_NETWEIGHT'] ?? 0), 2) . ' / ' . number_format((float)($c['BS2U_GROSSWEIGHT'] ?? 0), 2) . ' ' . h($c['BS2U_UOMWEIGHT'] ?? ''); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 4. PRODUCT DETAILS -->
            <div class="detail-card">
                <div class="card-header-bar">
                    <h4>4. Stuffed Product Details (<?php echo count($products); ?> Items)</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:50px;">#</th>
                                <th>Container No.</th>
                                <th>Product No.</th>
                                <th class="text-center">Dimension (W × L × H mm)</th>
                                <th class="text-right">Cubic Meter (m³)</th>
                                <th class="text-right">Packages</th>
                                <th class="text-right">Net Weight (kg)</th>
                                <th class="text-right">Gross Weight (kg)</th>
                                <th class="text-center">Stuffed Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="9" class="text-center text-muted">ยังไม่มีรายการสินค้าที่บรรจุลงตู้</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $idx => $p): 
                                    $dim = number_format((float)($p['INV3_DIMENSIONWIDTH'] ?? 0), 0) . ' × ' .
                                           number_format((float)($p['INV3_DIMENSIONLENGTH'] ?? 0), 0) . ' × ' .
                                           number_format((float)($p['INV3_DIMENSIONHIGH'] ?? 0), 0);
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $idx + 1; ?></td>
                                    <td><strong><?php echo h($p['CONTAINER_NO']); ?></strong></td>
                                    <td><span style="color:#047857; font-weight:700;"><?php echo h($p['PRODUCT_NO']); ?></span></td>
                                    <td class="text-center"><?php echo $dim; ?></td>
                                    <td class="text-right"><?php echo number_format((float)($p['CALC_CBM'] ?? 0), 4); ?></td>
                                    <td class="text-right"><?php echo number_format((float)($p['INV3_PACKAGE'] ?? 0), 0); ?></td>
                                    <td class="text-right"><?php echo number_format((float)($p['INV3_NETWEIGHT'] ?? 0), 2); ?></td>
                                    <td class="text-right" style="font-weight:600;"><?php echo number_format((float)($p['INV3_GROSSWEIGHT'] ?? 0), 2); ?></td>
                                    <td class="text-center"><?php echo fmtDate($p['BOOK_DATE'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>">
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
function deleteBooking(bookNo) {
    if (!confirm('คุณยืนยันการลบข้อมูล Booking No. ' + bookNo + ' หรือไม่?')) {
        return;
    }

    $.ajax({
        url: 'model/booking_shipment_api_mats.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'delete_booking',
            book_no: bookNo
        }),
        success: function(res) {
            alert(res.message);
            const func = document.getElementById('func').value;
            window.location.assign('booking_shipment_list_mats.php?func=' + encodeURIComponent(func));
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'ไม่สามารถลบใบจองได้');
        }
    });
}
</script>
</body>
</html>