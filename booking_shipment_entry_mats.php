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

if (!$is_authorized) {
    header("Location: booking_shipment_list_mats.php?func=" . urlencode($folder_func));
    exit();
}

require_once 'dbcon_mats-new.php';
require_once 'model/booking_shipment_model_mats.php';

$model   = new BookingShipmentModel($conn);
$masters = $model->getMasterData();

$editBookNo  = isset($_GET['book_no']) ? strtoupper(trim($_GET['book_no'])) : '';
$initialData = null;
$loadError   = '';

if ($editBookNo !== '') {
    try {
        $initialData = $model->getBookingDetail($editBookNo);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Booking Shipment Entry - Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155;
        }
        .main-panel { background-color: #f8fafc !important; }

        /* Sticky Summary Header */
        .sticky-summary {
            position: sticky; top: 0; z-index: 99;
            background: #1e293b; color: #ffffff;
            padding: 12px 20px; border-radius: 10px;
            margin-bottom: 20px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;
        }
        .summary-metrics { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; }
        .metric-box { display: flex; flex-direction: column; }
        .metric-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; color: #94a3b8; font-weight: 700; }
        .metric-val { font-size: 15px; font-weight: 700; color: #f8fafc; }

        .section-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 20px 24px; margin-bottom: 20px; border: 1px solid #e2e8f0;
        }
        .section-title {
            font-size: 15px; font-weight: 700; color: #0f172a;
            border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 16px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .step-badge {
            background: #2563eb; color: #fff; font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: 999px; margin-right: 8px;
        }

        .form-group label {
            font-size: 11.5px; font-weight: 700; color: #475569;
            text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 4px;
        }
        .form-control {
            height: 36px; font-size: 13px; border-radius: 6px; border: 1px solid #cbd5e1;
        }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12); }
        .form-control[readonly], .form-control:disabled { background-color: #f1f5f9; color: #475569; font-weight: 600; }

        /* Address Panel */
        .address-drawer {
            background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 15px; margin-top: 12px; margin-bottom: 16px;
        }
        .addr-box {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 12px; height: 100%; font-size: 12.5px;
        }
        .addr-box h5 {
            margin: 0 0 8px 0; font-size: 12px; font-weight: 700;
            color: #1d4ed8; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px;
        }

        /* Split View Container & Product */
        .split-locked { opacity: 0.55; pointer-events: none; position: relative; }
        .table-sm th { background: #f8fafc; font-size: 11px; text-transform: uppercase; color: #475569; padding: 8px !important; }
        .table-sm td { font-size: 12.5px; padding: 8px !important; vertical-align: middle !important; }
        .tr-container { cursor: pointer; transition: background 0.15s; }
        .tr-container:hover { background-color: #f0f9ff !important; }
        .tr-container.active-cnt { background-color: #dbeafe !important; font-weight: 600; }

        .btn-primary-custom { background: #2563eb; color: #fff; border: none; font-weight: 600; border-radius: 6px; padding: 8px 18px; }
        .btn-primary-custom:hover { background: #1d4ed8; color: #fff; }
        .btn-success-custom { background: #059669; color: #fff; border: none; font-weight: 600; border-radius: 6px; padding: 7px 14px; }
        .btn-success-custom:hover { background: #047857; color: #fff; }

        .btn-view-detail {
            background-color: #0284c7 !important; color: #ffffff !important;
            border: 1px solid #0284c7 !important; font-weight: 600; font-size: 13px;
            padding: 7px 15px; border-radius: 6px; text-decoration: none;
            display: inline-block; transition: all 0.2s;
        }
        .btn-view-detail:hover {
            background-color: #0369a1 !important; border-color: #0369a1 !important;
            color: #ffffff !important; text-decoration: none;
        }

        .btn-back-list {
            background-color: #ffffff !important; color: #0f172a !important;
            border: 1px solid #ffffff !important; font-weight: 700; font-size: 13px;
            padding: 7px 15px; border-radius: 6px; text-decoration: none;
            display: inline-block; transition: all 0.2s;
        }
        .btn-back-list:hover {
            background-color: #e2e8f0 !important; border-color: #e2e8f0 !important;
            color: #0f172a !important; text-decoration: none;
        }        

        .action-btn-group {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }
        .btn-cnt-edit {
            background-color: #f59e0b;
            color: #ffffff;
            border: none;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 5px;
            cursor: pointer;
            line-height: 1.4;
            transition: all 0.15s ease;
        }
        .btn-cnt-edit:hover {
            background-color: #d97706;
            transform: translateY(-1px);
        }
        .btn-cnt-del {
            background-color: #ef4444;
            color: #ffffff;
            border: none;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 5px;
            cursor: pointer;
            line-height: 1.4;
            transition: all 0.15s ease;
        }
        .btn-cnt-del:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
        }        
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
                        <span id="page_mode_title"><?php echo $editBookNo ? 'Edit Overseas Booking' : 'Create New Overseas Booking'; ?></span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <?php if ($loadError !== ''): ?>
                <div class="alert alert-danger">
                    <strong>ข้อผิดพลาด:</strong> <?php echo htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?>
                    <a href="booking_shipment_list_mats.php?func=<?php echo $folder_func; ?>" class="btn btn-default btn-xs" style="margin-left:10px;">กลับหน้ารายการ</a>
                </div>
            <?php endif; ?>

            <!-- Sticky Summary Bar -->
            <div class="sticky-summary">
                <div class="summary-metrics">
                    <div class="metric-box">
                        <span class="metric-label">Booking No.</span>
                        <span class="metric-val" id="sum_book_no" style="color:#38bdf8;">-</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Invoice No.</span>
                        <span class="metric-val" id="sum_inv_no" style="color:#fbbf24;">Pending Invoice</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Containers</span>
                        <span class="metric-val" id="sum_cnt_count">0</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Total Package</span>
                        <span class="metric-val" id="sum_pkg">0</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Total Weight (kg)</span>
                        <span class="metric-val" id="sum_wt">0.00</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Total CBM (m³)</span>
                        <span class="metric-val" id="sum_cbm">0.0000</span>
                    </div>
                    <div class="metric-box">
                        <span class="metric-label">Diff. UOM Wt</span>
                        <span class="metric-val" id="sum_uwt">0.00</span>
                    </div>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <a id="btn_view_detail" href="#" class="btn-view-detail" style="display:none;">View Detail</a>
                    <a href="booking_shipment_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-back-list"> ⬅️ Back to List</a>
                </div>
            </div>

            <!-- SECTION 1: HEADING & CUSTOMER ADDRESS -->
            <div class="section-card">
                <div class="section-title">
                    <div><span class="step-badge">STEP 1</span> Booking Heading, Route & Customer Information</div>
                    <button type="button" class="btn btn-default btn-xs" onclick="toggleAddressDrawer()">
                        <span id="addr_toggle_text">Show / Hide Address (Consignee / Notify)</span>
                    </button>
                </div>

                <form id="frm_heading" onsubmit="saveHeading(event)">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Booking No. <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="BOOK_NO" maxlength="20" required
                                       placeholder="Enter Booking No..." oninput="this.value = this.value.toUpperCase(); updateSummaryBookNo();">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Invoice No. (Auto-linked)</label>
                                <input type="text" class="form-control" id="INVOICE_NO" readonly placeholder="Linked after Invoice issued">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Shipper <span class="text-danger">*</span></label>
                                <select class="form-control" id="COMPANY_ID" required>
                                    <option value="">-- Select Shipper --</option>
                                    <?php foreach ($masters['shippers'] as $s): 
                                        $sid = trim($s['COMPANY_ID']);
                                        $sname = trim($s['COMPANY']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($sid); ?>"><?php echo htmlspecialchars("{$sid} : {$sname}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Consignee (Customer) <span class="text-danger">*</span></label>
                                <select class="form-control" id="CSTMSPPL_ID" required onchange="onCustomerChange(true)">
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($masters['customers'] as $c): 
                                        $cid = trim($c['CSTMSPPL_ID']);
                                        $cname = trim($c['CONSIGNEE_COMPANY']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($cid); ?>"><?php echo htmlspecialchars("{$cid} : {$cname}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible 3-Column Address Preview -->
                    <div id="address_drawer" class="address-drawer" style="display:none;">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="addr-box">
                                    <h5>Consignee Address</h5>
                                    <div id="addr_consignee" style="color:#334155;">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="addr-box">
                                    <h5>Notify Party</h5>
                                    <div id="addr_notify" style="color:#334155;">-</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="addr-box">
                                    <h5>Also Notify Party</h5>
                                    <div id="addr_alsonotify" style="color:#334155;">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dates Row -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Booking Date</label>
                                <input type="date" class="form-control" id="BOOK_DATE" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>CY Date (From Yard)</label>
                                <input type="date" class="form-control" id="FROM_YARD" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Return Date</label>
                                <input type="date" class="form-control" id="RETURN_YARD" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Expect Delivery (ETD)</label>
                                <input type="date" class="form-control" id="EXPECT_DELIVERY" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Expect Arrival (ETA)</label>
                                <input type="date" class="form-control" id="EXPECT_ARRIVAL" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Ports & Destinations Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Port of Loading</label>
                                <select class="form-control" id="BSH1_PORTLOADING">
                                    <option value="">-- Select Port --</option>
                                    <?php foreach ($masters['ports'] as $p): 
                                        $pid = trim($p['PORT_ID']);
                                        $pdesc = trim($p['DESCRIPTION']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($pid); ?>"><?php echo htmlspecialchars("{$pid} : {$pdesc}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Port of Discharge</label>
                                <select class="form-control" id="BSH1_PORTDISCHARGE">
                                    <option value="">-- Select Port --</option>
                                    <?php foreach ($masters['ports'] as $p): 
                                        $pid = trim($p['PORT_ID']);
                                        $pdesc = trim($p['DESCRIPTION']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($pid); ?>"><?php echo htmlspecialchars("{$pid} : {$pdesc}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Place Destination</label>
                                <select class="form-control" id="BSH1_PLACEDESTINATION">
                                    <option value="">-- Select Place --</option>
                                    <?php foreach ($masters['places'] as $pl): 
                                        $plid = trim($pl['PLACE_ID']);
                                        $pldesc = trim($pl['DESCRIPTION']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($plid); ?>"><?php echo htmlspecialchars("{$plid} : {$pldesc}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Country Destination</label>
                                <select class="form-control" id="BSH1_COUNTRYDESTINATION">
                                    <option value="">-- Select Country --</option>
                                    <?php foreach ($masters['places'] as $pl): 
                                        $plid = trim($pl['PLACE_ID']);
                                        $pldesc = trim($pl['DESCRIPTION']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($plid); ?>"><?php echo htmlspecialchars("{$plid} : {$pldesc}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Vessel, Freight & Contacts Row -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Freight</label>
                                <select class="form-control" id="FREIGHT_ID">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($masters['freights'] as $f): 
                                        $fid = trim($f['FREIGHT_ID']);
                                        $fdesc = trim($f['DESCRIPTION']);
                                    ?>
                                        <option value="<?php echo htmlspecialchars($fid); ?>"><?php echo htmlspecialchars("{$fid} : {$fdesc}"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Volume / Size</label>
                                <input type="text" class="form-control" id="VOLUME" maxlength="25" placeholder="e.g. 1X40HC" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Feeder</label>
                                <input type="text" class="form-control" id="FEEDER" maxlength="50" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Vessel</label>
                                <input type="text" class="form-control" id="VESSEL" maxlength="50" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Attention</label>
                                <input type="text" class="form-control" id="ATTENTION" maxlength="30" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Doc From</label>
                                <input type="text" class="form-control" id="DOC_FROM" maxlength="30" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>

                    <!-- Description & Shipping Marks -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Goods Description</label>
                                <input type="text" class="form-control" id="BSH1_DESCRIPTION" maxlength="80" oninput="this.value = this.value.toUpperCase()">
                            </div>
                            <div class="form-group">
                                <label>Shipping Mark (Main)</label>
                                <input type="text" class="form-control" id="BSH1_SHIPPINGMARK" maxlength="50" oninput="this.value = this.value.toUpperCase()">
                            </div>
                            <div class="row">
                                <div class="col-md-6"><input type="text" class="form-control" id="BSH1_SHIPPINGMARK1" maxlength="50" placeholder="Line 1" style="margin-bottom:6px;" oninput="this.value = this.value.toUpperCase()"></div>
                                <div class="col-md-6"><input type="text" class="form-control" id="BSH1_SHIPPINGMARK2" maxlength="50" placeholder="Line 2" style="margin-bottom:6px;" oninput="this.value = this.value.toUpperCase()"></div>
                                <div class="col-md-6"><input type="text" class="form-control" id="BSH1_SHIPPINGMARK3" maxlength="50" placeholder="Line 3" style="margin-bottom:6px;" oninput="this.value = this.value.toUpperCase()"></div>
                                <div class="col-md-6"><input type="text" class="form-control" id="BSH1_SHIPPINGMARK4" maxlength="50" placeholder="Line 4" style="margin-bottom:6px;" oninput="this.value = this.value.toUpperCase()"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:4px;">Shipping Mark (Lines 5 - 7)</label>
                            <input type="text" class="form-control" id="BSH1_SHIPPINGMARK5" maxlength="50" placeholder="Line 5" style="margin-bottom:8px;" oninput="this.value = this.value.toUpperCase()">
                            <input type="text" class="form-control" id="BSH1_SHIPPINGMARK6" maxlength="50" placeholder="Line 6" style="margin-bottom:8px;" oninput="this.value = this.value.toUpperCase()">
                            <input type="text" class="form-control" id="BSH1_SHIPPINGMARK7" maxlength="50" placeholder="Line 7" style="margin-bottom:14px;" oninput="this.value = this.value.toUpperCase()">

                            <div style="text-align: right; margin-top: 10px;">
                                <button type="submit" class="btn btn-primary-custom" id="btn_save_heading">
                                    SAVE BOOKING HEADING
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- SECTION 2: SPLIT VIEW (CONTAINERS & PRODUCT STUFFING) -->
            <div id="step2_wrapper" class="split-locked">
                <div id="step2_lock_notice" class="alert alert-warning" style="font-weight:600;">
                    กรุณาบันทึกข้อมูล <strong>Step 1 (Booking Heading)</strong> ด้านบนก่อน เพื่อปลดล็อกการเพิ่มตู้คอนเทนเนอร์และจัดสินค้าลงตู้
                </div>

                <div class="row">
                    <!-- LEFT (45% -> col-md-5): CONTAINERS -->
                    <div class="col-md-5">
                        <div class="section-card" style="height: 100%;">
                            <div class="section-title">
                                <div><span class="step-badge">STEP 2</span> Containers (BOOKSHIP2)</div>
                            </div>

                            <!-- Container Input Form -->
                            <div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:14px;">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group" style="margin-bottom:8px;">
                                            <label>Container No. (11 Chars) *</label>
                                            <input type="text" class="form-control" id="CNT_NO" maxlength="11" placeholder="e.g. YMLU8773459" oninput="this.value = this.value.toUpperCase()">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group" style="margin-bottom:8px;">
                                            <label>Seal No.</label>
                                            <input type="text" class="form-control" id="SEAL_NO" maxlength="15" placeholder="Seal No." oninput="this.value = this.value.toUpperCase()">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group" style="margin-bottom:8px;">
                                            <label>UOM</label>
                                            <select class="form-control" id="BSH2_UOM">
                                                <option value="">-- UOM --</option>
                                                <?php foreach ($masters['uoms'] as $u): 
                                                    $uom = trim($u['UOM']);
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($uom); ?>"><?php echo htmlspecialchars($uom); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div style="display:flex; justify-content:flex-end; gap:6px;">
                                    <button type="button" class="btn btn-default btn-xs" id="btn_cancel_cnt" style="display:none;" onclick="resetContainerForm()">Cancel Edit</button>
                                    <button type="button" class="btn btn-success-custom btn-sm" id="btn_save_cnt" onclick="saveContainer()">+ Add Container</button>
                                </div>
                            </div>

                            <!-- Container List Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tbl_containers">
                                    <thead>
                                        <tr>
                                            <th>Container No.</th>
                                            <th>Seal No.</th>
                                            <th>UOM</th>
                                            <th class="text-right">Pkg</th>
                                            <th class="text-right">Gross Wt</th>
                                            <th class="text-center" style="width:110px; white-space:nowrap;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="6" class="text-center text-muted">ยังไม่มีข้อมูลตู้คอนเทนเนอร์</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">* คลิกที่แถวตู้คอนเทนเนอร์เพื่อเลือกและดูรายการสินค้าภายในตู้ทางด้านขวา</small>
                        </div>
                    </div>

                    <!-- RIGHT (55% -> col-md-7): PRODUCT STUFFING -->
                    <div class="col-md-7">
                        <div class="section-card" style="height: 100%;">
                            <div class="section-title">
                                <div>
                                    <span class="step-badge" style="background:#059669;">STEP 3</span> 
                                    Product Stuffing <span id="active_cnt_badge" class="badge" style="background:#1e293b; margin-left:6px;">No Container Selected</span>
                                </div>
                            </div>

                            <!-- Notice when Invoice is not yet linked -->
                            <div id="no_invoice_notice" class="alert alert-info" style="font-size:13px;">
                                <strong>หมายเหตุ:</strong> ใบจองนี้ยังไม่มีเลขที่ <strong>Invoice No.</strong> ระบบจึงยังไม่แสดงรายการสินค้า<br>
                                สามารถบันทึกหัวใบจองและหมายเลขตู้คอนเทนเนอร์ไว้ก่อน แล้วกลับมาแก้ไขหน้านี้เพื่อจัดสินค้าลงตู้ได้หลังจากออก Invoice แล้ว
                            </div>

                            <!-- Product Stuffing Controls (Shown when Invoice exists & Container is selected) -->
                            <div id="product_assign_box" style="display:none; background:#f0fdf4; padding:12px; border-radius:8px; border:1px solid #bbf7d0; margin-bottom:14px;">
                                <div class="row" style="align-items:flex-end;">
                                    <div class="col-md-8">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label>Select Unassigned Product from Invoice (CONTAINER_NO = '')</label>
                                            <select class="form-control" id="sel_unassigned_product">
                                                <option value="">-- Select Product to Load into Container --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-success-custom btn-block" style="height:36px;" onclick="addProductToContainer()">
                                            + Load into Container
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Stuffed Products Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tbl_products">
                                    <thead>
                                        <tr>
                                            <th>Product No.</th>
                                            <th class="text-center">Dimension (W×L×H mm)</th>
                                            <th class="text-right">CBM</th>
                                            <th class="text-right">Pkg</th>
                                            <th class="text-right">Net Wt</th>
                                            <th class="text-right">Gross Wt</th>
                                            <th class="text-center" style="width:55px;">Del</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="7" class="text-center text-muted">กรุณาเลือกตู้คอนเทนเนอร์ทางด้านซ้าย</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>">
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script>
const API_URL = 'model/booking_shipment_api_mats.php';
const INITIAL_DATA = <?php echo json_encode($initialData, JSON_UNESCAPED_UNICODE); ?>;

let state = {
    isNewHeading: true,
    isNewContainer: true,
    selectedContainerNo: '',
    bookingData: null
};

$(document).ready(function() {
    if (INITIAL_DATA && INITIAL_DATA.heading) {
        state.isNewHeading = false;
        applyBookingData(INITIAL_DATA);
    } else {
        updateSummaryBookNo();
    }
});

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
    });
}

function updateSummaryBookNo() {
    const val = $('#BOOK_NO').val().trim();
    $('#sum_book_no').text(val || '-');
}

function toggleAddressDrawer() {
    $('#address_drawer').slideToggle(180);
}

function renderAddressBoxes(addr) {
    if (!addr) {
        $('#addr_consignee, #addr_notify, #addr_alsonotify').html('-');
        return;
    }
    const buildHtml = (prefix) => {
        const comp = addr[prefix + '_COMPANY'] || '';
        const a1   = addr[prefix + '_ADDRESS1'] || '';
        const a2   = addr[prefix + '_ADDRESS2'] || '';
        const a3   = addr[prefix + '_ADDRESS3'] || '';
        const ct   = addr[prefix + '_CONTACT'] || '';
        const pos  = addr[prefix + '_POSITION'] || '';
        const tel  = addr[prefix + '_TELEPHONE'] || '';
        const fax  = addr[prefix + '_FAX'] || '';
        const mail = addr[prefix + '_EMAIL'] || '';

        if (!comp && !a1) return '<span class="text-muted">ไม่มีข้อมูล</span>';

        return `
            <div style="font-weight:700; color:#0f172a; margin-bottom:4px;">${escapeHtml(comp)}</div>
            <div>${escapeHtml(a1)}</div>
            <div>${escapeHtml(a2)}</div>
            <div style="margin-bottom:6px;">${escapeHtml(a3)}</div>
            ${ct ? `<div><strong>Contact:</strong> ${escapeHtml(ct)} ${pos ? '('+escapeHtml(pos)+')' : ''}</div>` : ''}
            ${tel ? `<div><strong>Tel:</strong> ${escapeHtml(tel)}</div>` : ''}
            ${fax ? `<div><strong>Fax:</strong> ${escapeHtml(fax)}</div>` : ''}
            ${mail ? `<div><strong>Email:</strong> ${escapeHtml(mail)}</div>` : ''}
        `;
    };

    $('#addr_consignee').html(buildHtml('CONSIGNEE'));
    $('#addr_notify').html(buildHtml('NOTIFY'));
    $('#addr_alsonotify').html(buildHtml('ALSONOTIFY'));
}

function onCustomerChange(applyDefaults) {
    const custId = $('#CSTMSPPL_ID').val();
    if (!custId) {
        renderAddressBoxes(null);
        return;
    }
    $.getJSON(API_URL, { action: 'get_customer', customer_id: custId }, function(res) {
        if (res.success && res.data) {
            renderAddressBoxes(res.data);
            $('#address_drawer').slideDown(180);

            // หากเป็นใบจองใหม่ ให้ตั้งค่าเริ่มต้น Port & Destination ตามลูกค้าเหมือนใน VB6
            if (applyDefaults && state.isNewHeading) {
                if (res.data.CSSP_PORTLOADING) $('#BSH1_PORTLOADING').val(res.data.CSSP_PORTLOADING);
                if (res.data.CSSP_PORTDISCHARGE) $('#BSH1_PORTDISCHARGE').val(res.data.CSSP_PORTDISCHARGE);
                if (res.data.CSSP_PLACEDESTINATION) $('#BSH1_PLACEDESTINATION').val(res.data.CSSP_PLACEDESTINATION);
                if (res.data.CSSP_COUNTRYDESTINATION) $('#BSH1_COUNTRYDESTINATION').val(res.data.CSSP_COUNTRYDESTINATION);
            }
        }
    }).fail(function(xhr) {
        alert(xhr.responseJSON?.message || 'ไม่พบข้อมูลลูกค้า');
    });
}

function formatDateInput(val) {
    if (!val) return '';
    return val.substring(0, 10);
}

function applyBookingData(data) {
    state.bookingData = data;
    const h = data.heading;

    // เติมข้อมูล Heading
    $('#BOOK_NO').val(h.BOOK_NO).prop('readonly', true);
    $('#INVOICE_NO').val(h.INVOICE_NO || '');
    $('#COMPANY_ID').val(h.COMPANY_ID || '');
    $('#CSTMSPPL_ID').val(h.CSTMSPPL_ID || '').prop('disabled', true);
    $('#BOOK_DATE').val(formatDateInput(h.BOOK_DATE));
    $('#FROM_YARD').val(formatDateInput(h.FROM_YARD));
    $('#RETURN_YARD').val(formatDateInput(h.RETURN_YARD));
    $('#EXPECT_DELIVERY').val(formatDateInput(h.EXPECT_DELIVERY));
    $('#EXPECT_ARRIVAL').val(formatDateInput(h.EXPECT_ARRIVAL));
    $('#BSH1_PORTLOADING').val(h.BSH1_PORTLOADING || '');
    $('#BSH1_PORTDISCHARGE').val(h.BSH1_PORTDISCHARGE || '');
    $('#BSH1_PLACEDESTINATION').val(h.BSH1_PLACEDESTINATION || '');
    $('#BSH1_COUNTRYDESTINATION').val(h.BSH1_COUNTRYDESTINATION || '');
    $('#FREIGHT_ID').val(h.FREIGHT_ID || '');
    $('#VOLUME').val(h.VOLUME || '');
    $('#FEEDER').val(h.FEEDER || '');
    $('#VESSEL').val(h.VESSEL || '');
    $('#ATTENTION').val(h.ATTENTION || '');
    $('#DOC_FROM').val(h.DOC_FROM || '');
    $('#BSH1_DESCRIPTION').val(h.BSH1_DESCRIPTION || '');
    $('#BSH1_SHIPPINGMARK').val(h.BSH1_SHIPPINGMARK || '');
    $('#BSH1_SHIPPINGMARK1').val(h.BSH1_SHIPPINGMARK1 || '');
    $('#BSH1_SHIPPINGMARK2').val(h.BSH1_SHIPPINGMARK2 || '');
    $('#BSH1_SHIPPINGMARK3').val(h.BSH1_SHIPPINGMARK3 || '');
    $('#BSH1_SHIPPINGMARK4').val(h.BSH1_SHIPPINGMARK4 || '');
    $('#BSH1_SHIPPINGMARK5').val(h.BSH1_SHIPPINGMARK5 || '');
    $('#BSH1_SHIPPINGMARK6').val(h.BSH1_SHIPPINGMARK6 || '');
    $('#BSH1_SHIPPINGMARK7').val(h.BSH1_SHIPPINGMARK7 || '');

    renderAddressBoxes(data.address);

    // อัปเดตแถบสรุปยอดด้านบน
    $('#sum_book_no').text(h.BOOK_NO);
    $('#sum_inv_no').text(h.INVOICE_NO ? h.INVOICE_NO : 'Pending Invoice');
    $('#sum_cnt_count').text(data.containers.length);
    $('#sum_pkg').text(Number(h.BSH1_TOTALPACKAGE || 0).toLocaleString());
    $('#sum_wt').text(Number(h.BSH1_TOTALWEIGHT || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#sum_cbm').text(Number(h.BSH1_TOTALCUBICMETER || 0).toLocaleString('en-US', {minimumFractionDigits: 4, maximumFractionDigits: 4}));
    $('#sum_uwt').text(Number(h.BS1U_TOTALWEIGHT || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

    // ปลดล็อก Step 2
    $('#step2_wrapper').removeClass('split-locked');
    $('#step2_lock_notice').hide();
    $('#btn_save_heading').text('UPDATE BOOKING HEADING');

    const func = $('#func').val();
    $('#btn_view_detail').attr('href', 'booking_shipment_detail_mats.php?func=' + encodeURIComponent(func) + '&book_no=' + encodeURIComponent(h.BOOK_NO)).show();

    // เลือก Container อัตโนมัติหากมีตู้และยังไม่ได้เลือก
    if (data.containers.length > 0) {
        const stillExists = data.containers.some(c => c.CONTAINER_NO === state.selectedContainerNo);
        if (!stillExists) {
            state.selectedContainerNo = data.containers[0].CONTAINER_NO;
        }
    } else {
        state.selectedContainerNo = '';
    }

    renderContainersTable();
    renderProductSection();
}

function saveHeading(e) {
    e.preventDefault();
    const payload = {
        action: 'save_heading',
        is_new: state.isNewHeading,
        BOOK_NO: $('#BOOK_NO').val(),
        COMPANY_ID: $('#COMPANY_ID').val(),
        CSTMSPPL_ID: $('#CSTMSPPL_ID').val(),
        BOOK_DATE: $('#BOOK_DATE').val(),
        FROM_YARD: $('#FROM_YARD').val(),
        RETURN_YARD: $('#RETURN_YARD').val(),
        EXPECT_DELIVERY: $('#EXPECT_DELIVERY').val(),
        EXPECT_ARRIVAL: $('#EXPECT_ARRIVAL').val(),
        BSH1_PORTLOADING: $('#BSH1_PORTLOADING').val(),
        BSH1_PORTDISCHARGE: $('#BSH1_PORTDISCHARGE').val(),
        BSH1_PLACEDESTINATION: $('#BSH1_PLACEDESTINATION').val(),
        BSH1_COUNTRYDESTINATION: $('#BSH1_COUNTRYDESTINATION').val(),
        FREIGHT_ID: $('#FREIGHT_ID').val(),
        VOLUME: $('#VOLUME').val(),
        FEEDER: $('#FEEDER').val(),
        VESSEL: $('#VESSEL').val(),
        ATTENTION: $('#ATTENTION').val(),
        DOC_FROM: $('#DOC_FROM').val(),
        BSH1_DESCRIPTION: $('#BSH1_DESCRIPTION').val(),
        BSH1_SHIPPINGMARK: $('#BSH1_SHIPPINGMARK').val(),
        BSH1_SHIPPINGMARK1: $('#BSH1_SHIPPINGMARK1').val(),
        BSH1_SHIPPINGMARK2: $('#BSH1_SHIPPINGMARK2').val(),
        BSH1_SHIPPINGMARK3: $('#BSH1_SHIPPINGMARK3').val(),
        BSH1_SHIPPINGMARK4: $('#BSH1_SHIPPINGMARK4').val(),
        BSH1_SHIPPINGMARK5: $('#BSH1_SHIPPINGMARK5').val(),
        BSH1_SHIPPINGMARK6: $('#BSH1_SHIPPINGMARK6').val(),
        BSH1_SHIPPINGMARK7: $('#BSH1_SHIPPINGMARK7').val()
    };

    $.ajax({
        url: API_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        success: function(res) {
            alert(res.message);
            if (state.isNewHeading) {
                state.isNewHeading = false;
                $('#page_mode_title').text('Edit Overseas Booking');
                const func = $('#func').val();
                const newUrl = 'booking_shipment_entry_mats.php?func=' + encodeURIComponent(func) + '&book_no=' + encodeURIComponent(payload.BOOK_NO);
                window.history.replaceState({}, '', newUrl);
            }
            applyBookingData(res.data);
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
        }
    });
}

/* ==================== CONTAINER FUNCTIONS ==================== */
function renderContainersTable() {
    const list = state.bookingData?.containers || [];
    const tbody = $('#tbl_containers tbody');
    tbody.empty();

    if (list.length === 0) {
        tbody.html('<tr><td colspan="6" class="text-center text-muted">ยังไม่มีข้อมูลตู้คอนเทนเนอร์</td></tr>');
        return;
    }

    list.forEach(c => {
        const isActive = (c.CONTAINER_NO === state.selectedContainerNo) ? 'active-cnt' : '';
        const tr = $(`
            <tr class="tr-container ${isActive}">
                <td><strong>${escapeHtml(c.CONTAINER_NO)}</strong></td>
                <td>${escapeHtml(c.SEAL_NO || '-')}</td>
                <td>${escapeHtml(c.BSH2_UOM || '-')}</td>
                <td class="text-right">${Number(c.BSH2_PACKAGE || 0).toLocaleString()}</td>
                <td class="text-right">${Number(c.BSH2_GROSSWEIGHT || 0).toFixed(2)}</td>
                <td class="text-center" onclick="event.stopPropagation()">
                    <div class="action-btn-group">
                        <button type="button" class="btn-cnt-edit" title="Edit Seal/UOM" onclick="editContainer('${escapeHtml(c.CONTAINER_NO)}')">Edit</button>
                        <button type="button" class="btn-cnt-del" title="Delete Container" onclick="deleteContainer('${escapeHtml(c.CONTAINER_NO)}')">Del</button>
                    </div>
                </td>
            </tr>
        `);
        tr.on('click', function() {
            state.selectedContainerNo = c.CONTAINER_NO;
            renderContainersTable();
            renderProductSection();
        });
        tbody.append(tr);
    });
}

function editContainer(containerNo) {
    const c = (state.bookingData?.containers || []).find(x => x.CONTAINER_NO === containerNo);
    if (!c) return;
    state.isNewContainer = false;
    $('#CNT_NO').val(c.CONTAINER_NO).prop('readonly', true);
    $('#SEAL_NO').val(c.SEAL_NO || '');
    $('#BSH2_UOM').val(c.BSH2_UOM || '');
    $('#btn_save_cnt').text('Update Container');
    $('#btn_cancel_cnt').show();
}

function resetContainerForm() {
    state.isNewContainer = true;
    $('#CNT_NO').val('').prop('readonly', false);
    $('#SEAL_NO').val('');
    $('#BSH2_UOM').val('');
    $('#btn_save_cnt').text('+ Add Container');
    $('#btn_cancel_cnt').hide();
}

function saveContainer() {
    const bookNo = $('#BOOK_NO').val().trim();
    const containerNo = $('#CNT_NO').val().trim();
    const sealNo = $('#SEAL_NO').val().trim();
    const uom = $('#BSH2_UOM').val();

    if (containerNo.length !== 11) {
        alert('หมายเลขตู้คอนเทนเนอร์ (Container No.) ต้องมีความยาว 11 ตัวอักษรเท่านั้น');
        $('#CNT_NO').focus();
        return;
    }

    $.ajax({
        url: API_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'save_container',
            is_new: state.isNewContainer,
            book_no: bookNo,
            container_no: containerNo,
            seal_no: sealNo,
            uom: uom
        }),
        success: function(res) {
            state.selectedContainerNo = containerNo;
            resetContainerForm();
            applyBookingData(res.data);
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'ไม่สามารถบันทึกตู้คอนเทนเนอร์ได้');
        }
    });
}

function deleteContainer(containerNo) {
    if (!confirm('ยืนยันการลบตู้คอนเทนเนอร์ ' + containerNo + ' ?')) return;
    const bookNo = $('#BOOK_NO').val().trim();

    $.ajax({
        url: API_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'delete_container',
            book_no: bookNo,
            container_no: containerNo
        }),
        success: function(res) {
            if (state.selectedContainerNo === containerNo) {
                state.selectedContainerNo = '';
            }
            resetContainerForm();
            applyBookingData(res.data);
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'ไม่สามารถลบตู้คอนเทนเนอร์ได้');
        }
    });
}

/* ==================== PRODUCT STUFFING FUNCTIONS ==================== */
function renderProductSection() {
    const data = state.bookingData;
    const tbody = $('#tbl_products tbody');
    tbody.empty();

    if (!data || !data.heading) return;

    const hasInvoice = Boolean(data.heading.INVOICE_NO && data.heading.INVOICE_NO.trim() !== '');
    const selectedCnt = state.selectedContainerNo;

    $('#active_cnt_badge').text(selectedCnt ? ('Container: ' + selectedCnt) : 'No Container Selected');

    if (!hasInvoice) {
        $('#no_invoice_notice').show();
        $('#product_assign_box').hide();
        tbody.html('<tr><td colspan="7" class="text-center text-muted">รอการออก Invoice เพื่อดึงรายการสินค้า</td></tr>');
        return;
    }

    $('#no_invoice_notice').hide();

    if (!selectedCnt) {
        $('#product_assign_box').hide();
        tbody.html('<tr><td colspan="7" class="text-center text-muted">กรุณาเพิ่มหรือเลือกตู้คอนเทนเนอร์ทางด้านซ้าย</td></tr>');
        return;
    }

    // แสดงกล่องเลือกสินค้าที่ยังไม่ลงตู้
    $('#product_assign_box').show();
    const sel = $('#sel_unassigned_product');
    sel.empty().append('<option value="">-- Select Unassigned Product (' + data.unassigned_products.length + ' items available) --</option>');
    data.unassigned_products.forEach(p => {
        const label = `${p.PRODUCT_NO} | Pkg: ${p.INV3_PACKAGE} | Gross: ${Number(p.INV3_GROSSWEIGHT || 0).toFixed(2)} kg | CBM: ${Number(p.CALC_CBM || 0).toFixed(4)}`;
        sel.append(`<option value="${escapeHtml(p.PRODUCT_NO)}">${escapeHtml(label)}</option>`);
    });

    // กรองเฉพาะสินค้าในตู้ที่เลือก
    const cntProducts = (data.assigned_products || []).filter(p => p.CONTAINER_NO === selectedCnt);
    if (cntProducts.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center text-muted">ยังไม่มีสินค้าในตู้ ' + escapeHtml(selectedCnt) + '</td></tr>');
        return;
    }

    cntProducts.forEach(p => {
        const dim = `${Number(p.INV3_DIMENSIONWIDTH || 0)} × ${Number(p.INV3_DIMENSIONLENGTH || 0)} × ${Number(p.INV3_DIMENSIONHIGH || 0)}`;
        tbody.append(`
            <tr>
                <td><strong>${escapeHtml(p.PRODUCT_NO)}</strong></td>
                <td class="text-center">${dim}</td>
                <td class="text-right">${Number(p.CALC_CBM || 0).toFixed(4)}</td>
                <td class="text-right">${Number(p.INV3_PACKAGE || 0).toLocaleString()}</td>
                <td class="text-right">${Number(p.INV3_NETWEIGHT || 0).toFixed(2)}</td>
                <td class="text-right">${Number(p.INV3_GROSSWEIGHT || 0).toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-xs" onclick="removeProductFromContainer('${escapeHtml(p.PRODUCT_NO)}')">&times;</button>
                </td>
            </tr>
        `);
    });
}

function addProductToContainer() {
    const bookNo = $('#BOOK_NO').val().trim();
    const containerNo = state.selectedContainerNo;
    const productNo = $('#sel_unassigned_product').val();

    if (!containerNo) {
        alert('กรุณาเลือกตู้คอนเทนเนอร์ก่อน');
        return;
    }
    if (!productNo) {
        alert('กรุณาเลือกรายการสินค้าที่ต้องการบรรจุลงตู้');
        return;
    }

    $.ajax({
        url: API_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'add_product',
            book_no: bookNo,
            container_no: containerNo,
            product_no: productNo
        }),
        success: function(res) {
            applyBookingData(res.data);
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'ไม่สามารถเพิ่มสินค้าลงตู้ได้');
        }
    });
}

function removeProductFromContainer(productNo) {
    if (!confirm('ยืนยันการนำสินค้า ' + productNo + ' ออกจากตู้ ' + state.selectedContainerNo + ' ?')) return;
    const bookNo = $('#BOOK_NO').val().trim();

    $.ajax({
        url: API_URL,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'remove_product',
            book_no: bookNo,
            container_no: state.selectedContainerNo,
            product_no: productNo
        }),
        success: function(res) {
            applyBookingData(res.data);
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'ไม่สามารถลบสินค้าออกจากตู้ได้');
        }
    });
}
</script>
</body>
</html>