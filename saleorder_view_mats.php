<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$current_dept = strtoupper(trim($department_func));
$is_sale_authorized = ($current_func === 'SALE' || $current_dept === 'MK');

$sono = isset($_GET['sono']) ? htmlspecialchars(trim($_GET['sono']), ENT_QUOTES, 'UTF-8') : '';
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
        .main-panel .content { padding: 15px 20px !important; }
        
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            padding: 20px 24px; margin-bottom: 18px; border: 1px solid #e2e8f0;
        }

        .section-header {
            font-weight: 700; color: #0f172a; font-size: 13.5px;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;
        }

        .view-subcard {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;
        }
        .subcard-heading {
            font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 10px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;
        }

        .info-row { display: flex; margin-bottom: 6px; font-size: 12.5px; line-height: 1.6; }
        .info-label { color: #64748b; font-weight: 600; width: 140px; flex-shrink: 0; }
        .info-val { color: #1e293b; font-weight: 600; flex-grow: 1; word-break: break-word; }

        .item-spec-box {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 12px 14px; margin-bottom: 12px; height: 100%;
        }
        .item-spec-title {
            font-weight: 700; color: #1e293b; font-size: 12px;
            text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }

        #item_list_table th {
            background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700;
            text-transform: uppercase; padding: 9px 6px !important; white-space: nowrap; text-align: center;
        }
        #item_list_table td {
            font-size: 12.5px; padding: 7px 6px !important; vertical-align: middle; white-space: nowrap;
        }
        #item_list_table tbody tr { cursor: pointer; transition: all 0.15s; }
        #item_list_table tbody tr.active-row td { background-color: #dbeafe !important; font-weight: 700; color: #1d4ed8; }
        #item_list_table tbody tr:hover td { background-color: #eff6ff; }

        .btn-top-back {
            background-color: #64748b; color: #ffffff; font-weight: 600; font-size: 13px;
            padding: 7px 18px; border-radius: 6px; border: none; text-decoration: none;
            height: 36px; display: inline-flex; align-items: center;
        }
        .btn-top-back:hover { background-color: #475569; color: #ffffff; }

        .btn-top-edit {
            background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 13px;
            padding: 7px 18px; border-radius: 6px; border: none; text-decoration: none;
            height: 36px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-top-edit:hover { background-color: #1d4ed8; color: #ffffff; }

        .checkbox-badge {
            display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600;
            padding: 3px 8px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; margin-right: 6px;
        }
        .checkbox-badge.checked {
            background: #ecfdf5; border-color: #a7f3d0; color: #047857;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'SO'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header" style="display:flex; align-items:center; gap:16px;">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Sales Order: <span style="color:#2563eb;"><?php echo $sono; ?></span>
                    </a>
                </div>
                <div style="float: right; padding-top: 12px; display: flex; gap: 8px; align-items: center;">
                    <a href="saleorder_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-top-back">
                         ⬅️ Back to List
                    </a>
                    <?php if ($is_sale_authorized): ?>
                        <a href="saleorder_entry_mats.php?func=<?php echo $folder_func; ?>&sono=<?php echo $sono; ?>" class="btn-top-edit">
                            ✏️ Edit Sales Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 15px;">
            
            <!-- SECTION 1: HEADER CARDS (2 แถว แถวละ 2 กล่อง โปร่ง สบายตา ข้อมูลครบ 100%) -->
            <div class="dashboard-card">
                <div class="section-header" style="justify-content: space-between;">
                    <span>📋 Order Header Information</span>
                    <div id="header_checkboxes_container">
                        <span class="checkbox-badge" id="badge_wholeweight">⬜ Whole Weight</span>
                        <span class="checkbox-badge" id="badge_markskid">⬜ Skid Mark</span>
                        <span class="checkbox-badge" id="badge_serialcode">⬜ Serial Code</span>
                        <span class="checkbox-badge" id="badge_scrapwire">⬜ Scrap Wire</span>
                    </div>
                </div>

                <!-- แถวที่ 1 -->
                <div class="row">
                    <!-- Card 1: Customer & References -->
                    <div class="col-md-6" style="margin-bottom: 16px;">
                        <div class="view-subcard">
                            <div>
                                <div class="subcard-heading">🏢 Customer & Order Reference</div>
                                <div class="info-row"><span class="info-label">Category:</span> <span class="info-val" id="h_cat">-</span></div>
                                <div class="info-row"><span class="info-label">Customer:</span> <span class="info-val" id="h_cust" style="color:#1d4ed8; font-weight:700;">-</span></div>
                                <div class="info-row"><span class="info-label">Supplier MAT Code:</span> <span class="info-val" id="h_supplier_mat">-</span></div>
                                <div class="info-row"><span class="info-label">Purchase Order (PO):</span> <span class="info-val" id="h_pono" style="color:#0f172a; font-weight:700;">-</span></div>
                                <div class="info-row"><span class="info-label">Customs Code:</span> <span class="info-val" id="h_customs_code">-</span></div>
                                <div class="info-row"><span class="info-label">Customer Order:</span> <span class="info-val" id="h_cust_order">-</span></div>
                                <div class="info-row"><span class="info-label">Customer Sale Order:</span> <span class="info-val" id="h_cust_so">-</span></div>
                                <div class="info-row"><span class="info-label">End Customer:</span> <span class="info-val" id="h_endcust">-</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Date, Currency & Shipped By -->
                    <div class="col-md-6" style="margin-bottom: 16px;">
                        <div class="view-subcard">
                            <div>
                                <div class="subcard-heading">📅 Schedule, Currency & Shipped By</div>
                                <div class="info-row"><span class="info-label">Order Date:</span> <span class="info-val" id="h_date">-</span></div>
                                <div class="info-row"><span class="info-label">Ship Date (ETD):</span> <span class="info-val" id="h_etd" style="color:#059669; font-weight:700;">-</span></div>
                                <div class="info-row"><span class="info-label">Currency:</span> <span class="info-val" id="h_currency">-</span></div>
                                <div class="info-row"><span class="info-label">Exchange Rate:</span> <span class="info-val" id="h_exrate">-</span></div>
                                <div class="info-row"><span class="info-label">Tax Percent:</span> <span class="info-val" id="h_tax">-</span></div>
                                <div class="info-row"><span class="info-label">Shipped By:</span> <span class="info-val" id="h_shippedby">-</span></div>
                                <div class="info-row"><span class="info-label">General Remark:</span> <span class="info-val" id="h_remark1" style="color:#475569;">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- แถวที่ 2 -->
                <div class="row">
                    <!-- Card 3: Logistics & Shipping Data -->
                    <div class="col-md-6" style="margin-bottom: 8px;">
                        <div class="view-subcard">
                            <div>
                                <div class="subcard-heading">🚢 Shipping Data & Logistics</div>
                                <div class="info-row"><span class="info-label">Tolerance:</span> <span class="info-val" id="h_tolerance">-</span></div>
                                <div class="info-row"><span class="info-label">Packing:</span> <span class="info-val" id="h_packing">-</span></div>
                                <div class="info-row"><span class="info-label">Payment Term:</span> <span class="info-val" id="h_payterm">-</span></div>
                                <div class="info-row"><span class="info-label">Shipment Term:</span> <span class="info-val" id="h_shipterm">-</span></div>
                                <div class="info-row"><span class="info-label">Port of Loading:</span> <span class="info-val" id="h_portload">-</span></div>
                                <div class="info-row"><span class="info-label">Port Discharge:</span> <span class="info-val" id="h_portdis">-</span></div>
                                <div class="info-row"><span class="info-label">Destination:</span> <span class="info-val" id="h_dest">-</span></div>
                                <div class="info-row"><span class="info-label">Shipping Remarks:</span> <span class="info-val" id="h_shipping_remarks" style="color:#475569;">-</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Pricing Basis & Totals (ตาม Logic S1) -->
                    <div class="col-md-6" style="margin-bottom: 8px;">
                        <div class="view-subcard" style="background:#f0fdf4; border-color:#bbf7d0;">
                            <div>
                                <div class="subcard-heading" style="color:#166534; border-color:#86efac;">💰 Pricing Basis & Totals</div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-row"><span class="info-label" style="width: 100px;">LME Type:</span> <span class="info-val" id="h_lmetype">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 100px;">LME Date:</span> <span class="info-val" id="h_lmedate">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 100px;">Order LME:</span> <span class="info-val" id="h_orderlme" style="color:#1d4ed8;">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 100px;">MJP Date:</span> <span class="info-val" id="h_mjpdate">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 100px;">MJP Price:</span> <span class="info-val" id="h_mjpprice">-</span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-row"><span class="info-label" style="width: 110px;">Total Amount:</span> <span class="info-val" id="h_total_amount" style="color:#1e40af; font-weight:700;">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 110px;">Transportation:</span> <span class="info-val" id="h_transport">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 110px;">Total FOB:</span> <span class="info-val" id="h_fob">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 110px;">Oversea Freight:</span> <span class="info-val" id="h_freight">-</span></div>
                                        <div class="info-row"><span class="info-label" style="width: 110px;">Insurance:</span> <span class="info-val" id="h_insurance">-</span></div>
                                    </div>
                                </div>

                                <!-- บล็อกแสดง Stdv / Factor / STDxFactor -->
                                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:6px 12px; margin: 6px 0;">
                                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                                        <span><b>Stdv:</b> <span id="h_stdv" style="color:#1e3a8a;">0.00</span></span>
                                        <span><b>Factor:</b> <span id="h_factor" style="color:#1e3a8a;">0.00</span></span>
                                        <span><b>STDxFactor:</b> <span id="h_stdxfactor" style="color:#1d4ed8; font-weight:700;">0.00</span></span>
                                    </div>
                                </div>

                                <div style="border-top:1px dashed #86efac; margin-top:6px; padding-top:6px; display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:700; color:#166534; font-size:13px;">Grand Total:</span> 
                                    <span class="info-val" id="h_grand_total" style="color:#047857; font-size:16px; font-weight:800; text-align:right;">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: ITEMS LIST TABLE -->
            <div class="dashboard-card" style="padding: 16px 20px;">
                <div class="section-header" style="margin-bottom: 12px;">
                    <span>📑 Order Items List</span>
                    <span id="items_count_badge" class="badge" style="background:#e0e7ff; color:#4338ca; font-size:11px;">0 Items</span>
                    <span style="font-size:11.5px; color:#64748b; margin-left:auto; text-transform:none; font-weight:normal;">
                        👉 Click on any row to view full item specifications below
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="item_list_table">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 45px;">Item</th>
                                <th style="text-align: center; width: 65px;">Product</th>
                                <th style="text-align: center; width: 75px;">Alloy</th>
                                <th style="text-align: center; width: 60px;">Temper</th>
                                <th style="text-align: center; width: 85px;">Grade</th>
                                <th style="text-align: right; width: 80px;">Thickness</th>
                                <th style="text-align: right; width: 80px;">Width</th>
                                <th style="text-align: right; width: 80px;">Length</th>
                                <th style="text-align: right; width: 95px;">Order Wt (kg)</th>
                                <th style="text-align: right; width: 80px;">Pieces</th>
                                <th style="text-align: right; width: 90px;">Unit Price</th>
                                <th style="text-align: right; width: 110px;">Amount</th>
                                <th style="text-align: center; width: 65px;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="item_list_body">
                            <tr><td colspan="13" align="center" style="padding:20px;">Loading order items...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 3: FULL ITEM SPECIFICATIONS (ตรงตามหน้าจอ VB6 S1 Item Detail 100%) -->
            <div class="dashboard-card" style="border-left: 4px solid #2563eb;">
                <div class="section-header" style="color: #2563eb; margin-bottom: 12px; justify-content:space-between;">
                    <span>🔍 Full Item Specifications (Selected Item: <span id="cur_item_no">-</span>)</span>
                    <span id="d_status_badge" class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:12px;">Status: OP</span>
                </div>

                <!-- ข้อมูลอ้างอิงของ Item -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-bottom:12px;">
                    <div class="row">
                        <div class="col-md-3">
                            <div><span style="color:#64748b;">PO Item:</span> <b id="d_poitem">-</b></div>
                            <div><span style="color:#64748b;">Material In:</span> <b id="d_mat_in">-</b></div>
                            <div><span style="color:#64748b;">Coil From:</span> <b id="d_coil_from">-</b></div>
                        </div>
                        <div class="col-md-3">
                            <div><span style="color:#64748b;">Customer Order:</span> <span id="d_cust_order">-</span></div>
                            <div><span style="color:#64748b;">Customer Sale Order:</span> <span id="d_cust_so">-</span></div>
                            <div><span style="color:#64748b;">Customer Part No:</span> <b id="d_cust_part">-</b></div>
                        </div>
                        <div class="col-md-3">
                            <div><span style="color:#64748b;">End Customer:</span> <span id="d_endcust_name">-</span></div>
                            <div><span style="color:#64748b;">End Cust. PO:</span> <span id="d_endcust_po">-</span></div>
                            <div><span style="color:#64748b;">Full Coil:</span> <span id="d_full_coil">-</span></div>
                        </div>
                        <div class="col-md-3">
                            <div><span style="color:#64748b;">PO Date:</span> <span id="d_po_date">-</span></div>
                            <div><span style="color:#64748b;">Request Date:</span> <span id="d_req_date">-</span></div>
                            <div><span style="color:#64748b;">Confirm Date:</span> <span id="d_conf_date" style="color:#059669; font-weight:700;">-</span></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Box 1: Specification Production & Dimensions -->
                    <div class="col-md-4">
                        <div class="item-spec-box">
                            <div class="item-spec-title">⚙️ Specification & Dimensions</div>
                            <div style="font-size:12px; line-height:1.9;">
                                <div><span style="color:#64748b;">Product Model:</span> <b id="d_prod_model">-</b></div>
                                <div><span style="color:#64748b;">Alloy - Temper:</span> <b id="d_alloy_temper">-</b></div>
                                <div><span style="color:#64748b;">Grade (SG / MG):</span> <span id="d_grade">-</span></div>
                                <div><span style="color:#64748b;">Size (mm):</span> <b id="d_size_mm" style="color:#0f172a;">-</b></div>
                                <div><span style="color:#64748b;">Size (inch):</span> <span id="d_size_inch">-</span></div>
                                <div><span style="color:#64748b;">T5 Temperature:</span> <span id="d_t5">-</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Box 2: Unit Price & LME Specific for Item -->
                    <div class="col-md-4">
                        <div class="item-spec-box">
                            <div class="item-spec-title">💵 Item Price & LME Basis</div>
                            <div style="font-size:12px; line-height:1.9;">
                                <div><span style="color:#64748b;">Price Period:</span> <span id="d_price_period">-</span></div>
                                <div><span style="color:#64748b;">Actual Price:</span> <b id="d_price" style="color:#1d4ed8; font-size:13px;">-</b></div>
                                <div><span style="color:#64748b;">Item Amount:</span> <b id="d_amount" style="color:#047857; font-size:13px;">-</b></div>
                                <div><span style="color:#64748b;">Item LME Type/Date:</span> <span id="d_lmetype_date">-</span></div>
                                <div><span style="color:#64748b;">Order LME:</span> <span id="d_item_lme">-</span></div>
                                <div><span style="color:#64748b;">MJP Price / Date:</span> <span id="d_item_mjp">-</span></div>
                                <div><span style="color:#64748b;">Stdv / Factor / STDx:</span> <span id="d_stdv_factor">-</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Box 3: Order, Tolerances & S1 Computed Totals -->
                    <div class="col-md-4">
                        <div class="item-spec-box">
                            <div class="item-spec-title">📦 Order Quantities & Tolerances</div>
                            <div style="font-size:12px; line-height:1.9;">
                                <div><span style="color:#64748b;">Weight per Piece:</span> <span id="d_wt_piece">-</span> kg</div>
                                <div><span style="color:#64748b;">Tolerance Max / Min:</span> <span id="d_tol">-</span></div>
                                <div><span style="color:#64748b;">Order Qty:</span> <b id="d_order_qty" style="color:#0f172a;">-</b></div>
                                <div><span style="color:#64748b;">Reject Qty:</span> <span id="d_reject_qty">-</span></div>
                                <div><span style="color:#64748b;">Replace Qty:</span> <span id="d_replace_qty">-</span></div>
                                
                                <!-- S1 Calculated Box -->
                                <div style="background:#00008b; color:#ffffff; border-radius:6px; padding:6px 10px; margin-top:6px; font-size:11px;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                                        <span>Max Order Qty:</span>
                                        <span><b id="d_max_wt">0</b> kg / <b id="d_max_pc">0</b> pc</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                                        <span>Total Order Qty:</span>
                                        <span><b id="d_tot_wt">0</b> kg / <b id="d_tot_pc">0</b> pc</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>Min Order Qty:</span>
                                        <span><b id="d_min_wt">0</b> kg / <b id="d_min_pc">0</b> pc</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Text Descriptions & Remarks -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; font-size: 12.5px; margin-top:12px;">
                    <div><span style="color:#64748b; font-weight:700;">Description:</span> <span id="d_desc" style="color:#1e293b; font-weight:600;">-</span></div>
                    <div style="margin-top:4px;"><span style="color:#64748b; font-weight:700;">Item Remark:</span> <span id="d_remark">-</span></div>
                    <div style="margin-top:4px;"><span style="color:#64748b; font-weight:700;">Special Labels:</span> <span id="d_labels">-</span></div>
                </div>

            </div>

        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var globalItems = [];

$(document).ready(function() {
    loadOrderDetails('<?php echo $sono; ?>');
});

function loadOrderDetails(sono) {
    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_so_details', sono: sono },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                var h = res.header;
                var curr = h.CURRENCY_ID || '';

                // 1. Fill Header Information
                var cat_display = h.ORDER_CATEGORY || '-';
                if (h.CATEGORY_DESC) cat_display += ' : ' + h.CATEGORY_DESC;
                document.getElementById("h_cat").innerText = cat_display;

                var cust_display = h.CSTMSPPL_ID || '-';
                if (h.CUSTOMER_NAME) cust_display += ' : ' + h.CUSTOMER_NAME;
                document.getElementById("h_cust").innerText = cust_display;

                document.getElementById("h_supplier_mat").innerText = h.SUPPLIER_MATCODE || '-';
                document.getElementById("h_pono").innerText = h.CTM1_PONO || '-';
                document.getElementById("h_customs_code").innerText = h.CUSTOMS_CODE || '-';
                document.getElementById("h_cust_order").innerText = h.CTM1_CUSTOMERORDER || '-';
                document.getElementById("h_cust_so").innerText = h.CTM1_CUSTOMERSALEORDER || '-';
                document.getElementById("h_endcust").innerText = (h.CTM1_ENDCUSTOMERNAME || '-') + (h.CTM1_ENDCUSTOMERPO ? ' (PO: ' + h.CTM1_ENDCUSTOMERPO + ')' : '');

                document.getElementById("h_date").innerText = h.SALEORDER_DATE_FMT || '-';
                document.getElementById("h_etd").innerText = h.INTENDSHIP_DATE_FMT || '-';
                document.getElementById("h_currency").innerText = curr;
                document.getElementById("h_exrate").innerText = Number(h.EXCHANGE_RATE || 1).toFixed(5);
                document.getElementById("h_tax").innerText = (h.CTM1_TAXPERCENT || 0) + '%';
                document.getElementById("h_shippedby").innerText = h.CTM1_SHIPPEDBY || '-';
                document.getElementById("h_remark1").innerText = h.CTM1_REMARK1 || '-';

                // Checkboxes
                updateCheckboxBadge('badge_wholeweight', h.CTM1_WHOLEWEIGHT == 1, 'Whole Weight');
                updateCheckboxBadge('badge_markskid', h.CTM1_MARKSKID == 1, 'Skid Mark');
                updateCheckboxBadge('badge_serialcode', h.CTM1_SERIALCODE == 1, 'Serial Code');
                updateCheckboxBadge('badge_scrapwire', h.CTM1_SCRAPWIRE == 1, 'Scrap Wire');

                // Logistics & Shipping
                document.getElementById("h_tolerance").innerText = h.CTM1_TOLERANCE || '-';
                document.getElementById("h_packing").innerText = h.CTM1_PACKING || '-';
                document.getElementById("h_payterm").innerText = (h.PAYMENT_ID || '-') + (h.PAYMENT_DESC ? ': ' + h.PAYMENT_DESC : '');
                document.getElementById("h_shipterm").innerText = (h.SHIPMENT_ID || '-') + (h.SHIPMENT_DESC ? ': ' + h.SHIPMENT_DESC : '');
                document.getElementById("h_portload").innerText = (h.CTM1_PORTLOADING || '-') + (h.PORTLOADING_DESC ? ': ' + h.PORTLOADING_DESC : '');
                document.getElementById("h_portdis").innerText = (h.CTM1_PORTDISCHARGE || '-') + (h.PORTDISCHARGE_DESC ? ': ' + h.PORTDISCHARGE_DESC : '');
                document.getElementById("h_dest").innerText = (h.CTM1_PLACEDESTINATION || '-') + ' / ' + (h.CTM1_COUNTRYDESTINATION || '-');

                var s_rmk = [];
                if (h.CTM1_REMARK2) s_rmk.push(h.CTM1_REMARK2);
                if (h.CTM1_REMARK3) s_rmk.push(h.CTM1_REMARK3);
                document.getElementById("h_shipping_remarks").innerText = s_rmk.length > 0 ? s_rmk.join(' | ') : '-';

                // Pricing Basis & Totals
                document.getElementById("h_lmetype").innerText = h.CTM1_LMETYPE || 'M-MONTHLY';
                document.getElementById("h_lmedate").innerText = h.CTM1_LMEDATE_FMT || '-';
                document.getElementById("h_orderlme").innerText = h.CTM1_ORDERLME ? Number(h.CTM1_ORDERLME).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + curr : '-';
                document.getElementById("h_mjpdate").innerText = h.CTM1_MJPDATE_FMT || '-';
                document.getElementById("h_mjpprice").innerText = h.CTM1_MJPPRICE ? Number(h.CTM1_MJPPRICE).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-';
                
                document.getElementById("h_stdv").innerText = Number(h.CTM1_ORDERSTDV || 0).toFixed(2);
                document.getElementById("h_factor").innerText = Number(h.CTM1_ORDERFACTOR || 0).toFixed(2);
                document.getElementById("h_stdxfactor").innerText = Number(h.CTM1_ORDERSTDXFACTOR || 0).toFixed(2);

                document.getElementById("h_total_amount").innerText = Number(h.CTM1_TOTALAMOUNT || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + curr;
                document.getElementById("h_transport").innerText = Number(h.CTM1_TRANSPORTATION || 0).toFixed(2);
                document.getElementById("h_fob").innerText = Number(h.CTM1_FOBTOTALAMOUNT || 0).toFixed(2);
                document.getElementById("h_freight").innerText = Number(h.CTM1_OVERSEAFREIGHT || 0).toFixed(2);
                document.getElementById("h_insurance").innerText = Number(h.CTM1_INSURANCE || 0).toFixed(2);
                document.getElementById("h_grand_total").innerText = Number(h.CTM1_GRANDTOTALAMOUNT || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + curr;

                // 2. Render Items Table
                globalItems = res.items || [];
                document.getElementById("items_count_badge").innerText = globalItems.length + ' Items';

                var tbody = '';
                if (globalItems.length > 0) {
                    globalItems.forEach(function(it, idx) {
                        var dim_t = parseFloat(it.THICKNESS || 0).toFixed(3);
                        var dim_w = parseFloat(it.WIDTH || 0).toFixed(3);
                        var dim_l = parseFloat(it.LENGTH || 0).toFixed(3);
                        var grade = (it.SURFACE_GRADE || '-') + ' / ' + (it.METALLURGICAL_GRADE || '-');

                        tbody += '<tr id="row_item_' + idx + '" onclick="selectItem(' + idx + ')">' +
                                 '<td align="center"><b>' + it.SALEORDER_ITEM + '</b></td>' +
                                 '<td align="center"><span class="badge" style="background:#e0e7ff; color:#4338ca; font-size:11px;">' + (it.PRODUCT_ID || '-') + '</span></td>' +
                                 '<td align="center" style="font-weight:600;">' + (it.ALLOY || '-') + '</td>' +
                                 '<td align="center">' + (it.TEMPER || '-') + '</td>' +
                                 '<td align="center" style="font-size:11.5px; color:#64748b;">' + grade + '</td>' +
                                 '<td align="right">' + dim_t + '</td>' +
                                 '<td align="right">' + dim_w + '</td>' +
                                 '<td align="right">' + dim_l + '</td>' +
                                 '<td align="right" style="font-weight:600; color:#0f172a;">' + Number(it.CTM2_ORDERWEIGHT || 0).toLocaleString() + '</td>' +
                                 '<td align="right">' + Number(it.CTM2_ORDERPIECE || 0).toLocaleString() + '</td>' +
                                 '<td align="right">' + parseFloat(it.CTM2_ACTUALPRICE || 0).toFixed(4) + '</td>' +
                                 '<td align="right" style="font-weight:700; color:#047857;">' + Number(it.AMOUNT || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>' +
                                 '<td align="center"><span class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:10.5px;">' + (it.CTM2_STATUS || 'OP') + '</span></td>' +
                                 '</tr>';
                    });
                } else {
                    tbody = '<tr><td colspan="13" align="center" style="color:#94a3b8; padding: 20px;">No items recorded for this sales order</td></tr>';
                }
                document.getElementById("item_list_body").innerHTML = tbody;

                if (globalItems.length > 0) {
                    selectItem(0);
                }
            } else {
                alert('Error: ' + res.message);
            }
        },
        error: function() {
            alert('Server connection error.');
        }
    });
}

function updateCheckboxBadge(id, is_checked, label) {
    var elem = document.getElementById(id);
    if (!elem) return;
    if (is_checked) {
        elem.className = 'checkbox-badge checked';
        elem.innerText = '☑ ' + label;
    } else {
        elem.className = 'checkbox-badge';
        elem.innerText = '⬜ ' + label;
    }
}

function selectItem(idx) {
    if (!globalItems[idx]) return;
    var it = globalItems[idx];

    $('#item_list_table tbody tr').removeClass('active-row');
    $('#row_item_' + idx).addClass('active-row');

    document.getElementById("cur_item_no").innerText = it.SALEORDER_ITEM;
    document.getElementById("d_status_badge").innerText = 'Status: ' + (it.CTM2_STATUS || 'OP');

    document.getElementById("d_poitem").innerText = it.CTM2_POITEM || it.SALEORDER_ITEM;
    document.getElementById("d_mat_in").innerText = it.MATERIAL_IN || '-';
    document.getElementById("d_coil_from").innerText = it.COIL_FROM || '-';
    document.getElementById("d_cust_order").innerText = it.CTM2_CUSTOMERORDER || '-';
    document.getElementById("d_cust_so").innerText = it.CTM2_CUSTOMERSALEORDER || '-';
    document.getElementById("d_cust_part").innerText = it.CTM2_CUSTOMERPARTNO || '-';
    document.getElementById("d_endcust_name").innerText = it.CTM2_ENDCUSTOMERNAME || '-';
    document.getElementById("d_endcust_po").innerText = it.CTM2_ENDCUSTOMERPO || '-';
    document.getElementById("d_full_coil").innerText = (it.FULL_COIL === 'F') ? 'F: FULL COIL' : 'N: NOT FULL COIL';

    document.getElementById("d_po_date").innerText = it.CTM2_PODATE_FMT || '-';
    document.getElementById("d_req_date").innerText = it.REQUEST_DATE_FMT || '-';
    document.getElementById("d_conf_date").innerText = it.CONFIRM_DATE_FMT || '-';

    document.getElementById("d_prod_model").innerText = (it.PRODUCT_ID || '-') + ' : ' + (it.PRODUCT_MODEL || '-');
    document.getElementById("d_alloy_temper").innerText = (it.ALLOY || '-') + ' - ' + (it.TEMPER || '-');
    document.getElementById("d_grade").innerText = (it.SURFACE_GRADE || '-') + ' / ' + (it.METALLURGICAL_GRADE || '-');

    var mm_str = parseFloat(it.THICKNESS || 0).toFixed(3) + ' x ' + parseFloat(it.WIDTH || 0).toFixed(3) + (parseFloat(it.LENGTH || 0) > 0 ? ' x ' + parseFloat(it.LENGTH).toFixed(3) : '') + ' mm.';
    var inch_str = parseFloat(it.CT2U_THICKNESS || 0).toFixed(3) + ' x ' + parseFloat(it.CT2U_WIDTH || 0).toFixed(3) + (parseFloat(it.CT2U_LENGTH || 0) > 0 ? ' x ' + parseFloat(it.CT2U_LENGTH).toFixed(3) : '') + ' in.';
    document.getElementById("d_size_mm").innerText = mm_str;
    document.getElementById("d_size_inch").innerText = inch_str;
    document.getElementById("d_t5").innerText = it.T5_TEMPERATURE || '-';

    document.getElementById("d_price_period").innerText = it.PRICE_PERIOD || '-';
    document.getElementById("d_price").innerText = parseFloat(it.CTM2_ACTUALPRICE || 0).toFixed(4);
    document.getElementById("d_amount").innerText = Number(it.AMOUNT || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById("d_lmetype_date").innerText = (it.CTM2_LMETYPE || '-') + (it.CTM2_LMEDATE ? ' (' + it.CTM2_LMEDATE.substring(0, 10) + ')' : '');
    document.getElementById("d_item_lme").innerText = it.CTM2_ORDERLME ? Number(it.CTM2_ORDERLME).toFixed(2) : '-';
    document.getElementById("d_item_mjp").innerText = (it.CTM2_MJPPRICE ? Number(it.CTM2_MJPPRICE).toFixed(2) : '0.00') + (it.CTM2_MJPDATE ? ' (' + it.CTM2_MJPDATE.substring(0, 10) + ')' : '');
    document.getElementById("d_stdv_factor").innerText = (Number(it.CTM2_ORDERSTDV || 0).toFixed(2)) + ' / ' + (Number(it.CTM2_ORDERFACTOR || 0).toFixed(2)) + ' / ' + (Number(it.CTM2_ORDERSTDXFACTOR || 0).toFixed(2));

    document.getElementById("d_wt_piece").innerText = parseFloat(it.WEIGHT_PIECE || 0).toFixed(4);
    document.getElementById("d_tol").innerText = '+' + (it.CTM2_MAXTOLERANCE || 0) + '% / -' + (it.CTM2_MINTOLERANCE || 0) + '%';
    
    var ord_wt = parseFloat(it.CTM2_ORDERWEIGHT || 0);
    var ord_pc = parseInt(it.CTM2_ORDERPIECE || 0);
    var rej_wt = parseFloat(it.CTM2_REJECTWEIGHT || 0);
    var rej_pc = parseInt(it.CTM2_REJECTPIECE || 0);
    var rep_wt = parseFloat(it.CTM2_REPLACEWEIGHT || 0);
    var rep_pc = parseInt(it.CTM2_REPLACEPIECE || 0);
    var maxtol = parseFloat(it.CTM2_MAXTOLERANCE || 0);
    var mintol = parseFloat(it.CTM2_MINTOLERANCE || 0);

    document.getElementById("d_order_qty").innerText = ord_wt.toLocaleString() + ' kg / ' + ord_pc.toLocaleString() + ' pc';
    document.getElementById("d_reject_qty").innerText = rej_wt.toLocaleString() + ' kg / ' + rej_pc.toLocaleString() + ' pc';
    document.getElementById("d_replace_qty").innerText = rep_wt.toLocaleString() + ' kg / ' + rep_pc.toLocaleString() + ' pc';

    // คำนวณแถบ S1
    var tot_pc = ord_pc + rep_pc;
    var max_pc = Math.round(tot_pc + (tot_pc * maxtol / 100));
    var min_pc = Math.round(tot_pc - (tot_pc * mintol / 100));

    var tot_wt = Math.round(ord_wt + rep_wt);
    var max_wt = Math.round(tot_wt + (tot_wt * maxtol / 100));
    var min_wt = Math.round(tot_wt - (tot_wt * mintol / 100));

    document.getElementById("d_max_wt").innerText = max_wt.toLocaleString();
    document.getElementById("d_max_pc").innerText = max_pc.toLocaleString();
    document.getElementById("d_tot_wt").innerText = tot_wt.toLocaleString();
    document.getElementById("d_tot_pc").innerText = tot_pc.toLocaleString();
    document.getElementById("d_min_wt").innerText = min_wt.toLocaleString();
    document.getElementById("d_min_pc").innerText = min_pc.toLocaleString();

    document.getElementById("d_desc").innerText = it.CTM2_DESCRIPTION || '-';
    document.getElementById("d_remark").innerText = it.CTM2_REMARK || '-';

    var lbls = [];
    if (it.CTM2_SPECIALLABEL1) lbls.push(it.CTM2_SPECIALLABEL1);
    if (it.CTM2_SPECIALLABEL2) lbls.push(it.CTM2_SPECIALLABEL2);
    if (it.CTM2_SPECIALLABEL3) lbls.push(it.CTM2_SPECIALLABEL3);
    if (it.CTM2_SPECIALLABEL4) lbls.push(it.CTM2_SPECIALLABEL4);
    document.getElementById("d_labels").innerText = lbls.length > 0 ? lbls.join(' | ') : '-';
}
</script>
</html>