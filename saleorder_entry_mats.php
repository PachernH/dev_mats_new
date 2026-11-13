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

if (!$is_sale_authorized) {
    echo "<script>alert('Access Denied: Sales authorization required.'); window.location.href='saleorder_list_mats.php?func={$folder_func}';</script>";
    exit();
}

$sono = isset($_GET['sono']) ? htmlspecialchars(trim($_GET['sono']), ENT_QUOTES, 'UTF-8') : '';
$is_edit_mode = !empty($sono);

include("dbcon_mats-new.php");

// 1. ดึง Master Data สำหรับ Header
$categories = $conn->query("SELECT ORDER_CATEGORY, DESCRIPTION FROM ODCTMSTR1 ORDER BY ORDER_CATEGORY ASC")->fetchAll(PDO::FETCH_ASSOC);
$currencies = $conn->query("SELECT CURRENCY_ID, DESCRIPTION FROM CRNCMSTR1 ORDER BY CURRENCY_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$customers  = $conn->query("SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_ID IS NOT NULL ORDER BY CSTMSPPL_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$use_coil_suppliers = $conn->query("SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 WHERE CSTMSPPL_ID IS NOT NULL AND LTRIM(RTRIM(CONSIGNEE_COMPANY)) <> '' ORDER BY CSTMSPPL_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$payments   = $conn->query("SELECT PAYMENT_ID, DESCRIPTION FROM PMTMMSTR1 ORDER BY PAYMENT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$shipments  = $conn->query("SELECT SHIPMENT_ID, DESCRIPTION FROM SPTMMSTR1 ORDER BY SHIPMENT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$ports      = $conn->query("SELECT PORT_ID, DESCRIPTION FROM PORTMSTR1 ORDER BY PORT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$places     = $conn->query("SELECT PLACE_ID, DESCRIPTION FROM PLCEMSTR1 ORDER BY PLACE_ID ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึง Master Data สำหรับ Item Specification
$products     = $conn->query("SELECT PRODUCT_ID, PRODUCT_MODEL, MAX(ISNULL(AREA_SIZE, 0)) AS AREA_SIZE, MAX(ISNULL(CUSTOMER_PARTNO, '')) AS CUSTOMER_PARTNO FROM PRODMSTR1 GROUP BY PRODUCT_ID, PRODUCT_MODEL ORDER BY PRODUCT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
$alloys       = $conn->query("SELECT DISTINCT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC")->fetchAll(PDO::FETCH_ASSOC);
$tempers      = $conn->query("SELECT DISTINCT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC")->fetchAll(PDO::FETCH_ASSOC);
$sgs          = $conn->query("SELECT DISTINCT SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);
$mgs          = $conn->query("SELECT DISTINCT METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC")->fetchAll(PDO::FETCH_ASSOC);
$periods      = $conn->query("SELECT DISTINCT PRICE_PERIOD FROM PRICMSTR1 WHERE PRICE_PERIOD NOT IN ('9999', '5555') ORDER BY PRICE_PERIOD DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <title><?php echo $is_edit_mode ? "Edit Sales Order - {$sono}" : "New Sales Order"; ?></title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f1f5f9; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f1f5f9 !important; }
        .main-panel .content { padding: 15px 20px !important; }
        
        .entry-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            padding: 20px 24px; margin-bottom: 18px; border: 1px solid #e2e8f0;
        }

        .section-title {
            font-weight: 700; color: #0f172a; font-size: 13.5px;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;
        }

        .header-subcard {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;
        }
        .subcard-heading {
            font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 10px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;
        }

        .form-group-custom { margin-bottom: 7px; }
        .form-group-custom label {
            font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 2px;
            text-transform: uppercase; display: block;
        }
        .form-control-sm {
            height: 30px !important; padding: 3px 7px !important; font-size: 12px !important;
            border-radius: 5px !important; border: 1px solid #cbd5e1 !important; color: #1e293b; background: #ffffff;
        }
        .form-control-sm:focus { border-color: #2563eb !important; outline: none; box-shadow: 0 0 0 1px #2563eb; }

        .uom-group-box {
            background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px;
            padding: 8px 10px; margin-bottom: 8px;
        }
        .uom-group-title {
            font-size: 10.5px; font-weight: 700; color: #b45309; text-transform: uppercase; margin-bottom: 4px;
        }

        .vb-display-blue {
            background-color: #00008b !important; color: #ffffff !important; font-weight: 700; text-align: right; border: 1px solid #000066 !important;
        }

        #entry_item_table th {
            background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700;
            text-transform: uppercase; padding: 9px 6px !important; white-space: nowrap; text-align: center;
        }
        #entry_item_table td {
            font-size: 12.5px; padding: 7px 6px !important; vertical-align: middle; white-space: nowrap;
        }
        #entry_item_table tbody tr:hover td { background-color: #f0f9ff; }

        .btn-top-save {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            padding: 7px 20px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s;
            height: 36px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-top-save:hover { background-color: #047857; color: #ffffff; }

        .btn-top-delete {
            background-color: #dc2626; color: #ffffff; font-weight: 700; font-size: 13px;
            padding: 7px 18px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s;
            height: 36px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-top-delete:hover { background-color: #b91c1c; color: #ffffff; }

        .btn-top-cancel {
            background-color: #64748b; color: #ffffff; font-weight: 600; font-size: 13px;
            padding: 7px 18px; border-radius: 6px; border: none; text-decoration: none;
            height: 36px; display: inline-flex; align-items: center;
        }
        .btn-top-cancel:hover { background-color: #475569; color: #ffffff; }

        .checkbox-label {
            font-size: 11.5px; font-weight: 600; color: #475569; cursor: pointer;
            display: inline-flex; align-items: center; gap: 5px; margin-right: 15px; margin-bottom: 4px;
        }
        .checkbox-label input[type="checkbox"] { margin: 0; cursor: pointer; }
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
                        <?php echo $is_edit_mode ? "Edit Sales Order: <span style='color:#2563eb;'>{$sono}</span>" : "Create New Sales Order"; ?>
                    </a>
                </div>
                <div style="float: right; padding-top: 12px; display: flex; gap: 8px; align-items: center;">
                    <a href="saleorder_list_mats.php?func=<?php echo $folder_func; ?>" class="btn-top-cancel">Cancel</a>
                    <?php if ($is_edit_mode): ?>
                        <button type="button" class="btn-top-delete" onclick="delete_current_so()">🗑️ Delete SO</button>
                    <?php endif; ?>
                    <!-- 1. เพิ่ม id="btn_save_so" ที่ปุ่ม Save ด้านบน -->
                    <button type="button" class="btn-top-save" id="btn_save_so" onclick="save_entire_order()">💾 Save Sales Order</button>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 15px;">
            <form id="soForm" onsubmit="return false;">
                
                <!-- SECTION 1: ORDER HEADER -->
                <div class="entry-card">
                    <div class="section-title">
                        <span>📋 Order Header Information</span>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <label class="checkbox-label"><input type="checkbox" id="h_chk_wholeweight"/> Whole Weight</label>
                            <label class="checkbox-label"><input type="checkbox" id="h_chk_markskid"/> Shipping Mark with Skid No.</label>
                            <label class="checkbox-label"><input type="checkbox" id="h_chk_serialcode"/> Serial Code</label>
                            <label class="checkbox-label"><input type="checkbox" id="h_chk_scrapwire"/> Show Scrap Wire</label>
                        </div>
                    </div>

                    <!-- Header Row 1 -->
                    <div class="row">
                        <div class="col-md-6" style="margin-bottom: 16px;">
                            <div class="header-subcard">
                                <div>
                                    <div class="subcard-heading">🏢 Customer & Order Reference</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Order Category</label>
                                                <select class="form-control form-control-sm" id="h_order_cat" onchange="on_change_category()">
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo htmlspecialchars($cat['ORDER_CATEGORY']); ?>">
                                                            <?php echo htmlspecialchars($cat['ORDER_CATEGORY'] . ': ' . $cat['DESCRIPTION']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Sales Order No.</label>
                                                <?php if ($is_edit_mode): ?>
                                                    <input type="text" class="form-control form-control-sm" id="h_so_no" value="<?php echo $sono; ?>" readonly style="background:#f1f5f9; font-weight:700; color:#1d4ed8;"/>
                                                <?php else: ?>
                                                    <div style="display:flex; gap:6px; align-items:center;">
                                                        <input type="text" class="form-control form-control-sm" id="h_so_prefix" maxlength="2" value="TT" 
                                                               style="width: 58px; font-weight:700; text-align:center; text-transform:uppercase; color:#1d4ed8;" 
                                                               placeholder="SO" title="2-character Prefix" oninput="this.value = this.value.toUpperCase()"/>
                                                        <input type="text" class="form-control form-control-sm" value="-26-XXXX (Auto Running on Save)" readonly style="background:#f8fafc; color:#64748b; font-size:11px;"/>
                                                        <input type="hidden" id="h_so_no" value=""/>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="form-group-custom">
                                                <label>Customer</label>
                                                <select class="form-control form-control-sm" id="h_customer" onchange="on_change_customer()">
                                                    <option value="">-- Select Customer --</option>
                                                    <?php foreach ($customers as $c): ?>
                                                        <option value="<?php echo htmlspecialchars($c['CSTMSPPL_ID']); ?>">
                                                            <?php echo htmlspecialchars($c['CSTMSPPL_ID'] . ' : ' . $c['CONSIGNEE_COMPANY']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Supplier MAT Code</label>
                                                <input type="text" class="form-control form-control-sm" id="h_supplier_mat" placeholder="Supplier MAT Code..."/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Purchase Order (PO No)</label>
                                                <input type="text" class="form-control form-control-sm" id="h_po_no" placeholder="PO Number..."/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Customs Code</label>
                                                <input type="text" class="form-control form-control-sm" id="h_customs_code" placeholder="Customs Code..."/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Customer Order</label>
                                                <input type="text" class="form-control form-control-sm" id="h_cust_order" placeholder="Customer Order No..."/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Customer Sale Order</label>
                                                <input type="text" class="form-control form-control-sm" id="h_cust_so" placeholder="Customer Sale Order..."/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom" style="margin-bottom:0;">
                                                <label>End Customer Name</label>
                                                <input type="text" class="form-control form-control-sm" id="h_endcust_name" placeholder="End Customer Name..."/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom" style="margin-bottom:0;">
                                                <label>End Customer PO. No.</label>
                                                <input type="text" class="form-control form-control-sm" id="h_endcust_po" placeholder="End Customer PO..."/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Date & Currency -->
                        <div class="col-md-6" style="margin-bottom: 16px;">
                            <div class="header-subcard">
                                <div>
                                    <div class="subcard-heading">📅 Schedule, Currency & Shipped By</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Sale Order Date</label>
                                                <input type="date" class="form-control form-control-sm" id="h_so_date" value="<?php echo date('Y-m-d'); ?>"/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Intend Ship Date (ETD)</label>
                                                <input type="date" class="form-control form-control-sm" id="h_ship_date" value="<?php echo date('Y-m-d'); ?>"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Currency</label>
                                                <select class="form-control form-control-sm" id="h_currency">
                                                    <?php foreach ($currencies as $cur): ?>
                                                        <option value="<?php echo htmlspecialchars($cur['CURRENCY_ID']); ?>">
                                                            <?php echo htmlspecialchars($cur['CURRENCY_ID']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Exchange Rate</label>
                                                <input type="number" step="0.00001" class="form-control form-control-sm" id="h_exrate" value="1.00000"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Tax Percent (%)</label>
                                                <input type="number" class="form-control form-control-sm" id="h_tax" value="0"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group-custom">
                                        <label>Shipped By</label>
                                        <input type="text" class="form-control form-control-sm" id="h_shippedby" placeholder="e.g. TRUCK, VESSEL, AIR..."/>
                                    </div>

                                    <div class="form-group-custom" style="margin-bottom:0;">
                                        <label>General Remark (CTM1_REMARK1)</label>
                                        <input type="text" class="form-control form-control-sm" id="h_remark1" placeholder="Header Remark 1..."/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Header Row 2 -->
                    <div class="row">
                        <!-- Card 3: Shipping Data & Logistics -->
                        <div class="col-md-6" style="margin-bottom: 12px;">
                            <div class="header-subcard">
                                <div>
                                    <div class="subcard-heading">🚢 Shipping Data & Logistics</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Tolerance</label>
                                                <input type="text" class="form-control form-control-sm" id="h_tolerance" placeholder="Tolerance text (e.g. +/-10%)..."/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Packing</label>
                                                <input type="text" class="form-control form-control-sm" id="h_packing" placeholder="Packing text (e.g. EYE TO SKY)..."/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Payment Term</label>
                                                <select class="form-control form-control-sm" id="h_payment">
                                                    <option value="">-- None --</option>
                                                    <?php foreach ($payments as $p): ?>
                                                        <option value="<?php echo htmlspecialchars($p['PAYMENT_ID']); ?>">
                                                            <?php echo htmlspecialchars($p['PAYMENT_ID'] . ': ' . $p['DESCRIPTION']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Shipment Term</label>
                                                <select class="form-control form-control-sm" id="h_shipment">
                                                    <option value="">-- None --</option>
                                                    <?php foreach ($shipments as $s): ?>
                                                        <option value="<?php echo htmlspecialchars($s['SHIPMENT_ID']); ?>">
                                                            <?php echo htmlspecialchars($s['SHIPMENT_ID'] . ': ' . $s['DESCRIPTION']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group-custom">
                                        <label>Port Load / Port Discharge</label>
                                        <div style="display:flex; gap:6px;">
                                            <select class="form-control form-control-sm" id="h_portload" style="width:50%;">
                                                <option value="">000: NONE</option>
                                                <?php foreach ($ports as $pt): ?>
                                                    <option value="<?php echo htmlspecialchars($pt['PORT_ID']); ?>"><?php echo htmlspecialchars($pt['PORT_ID'] . ': ' . $pt['DESCRIPTION']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="form-control form-control-sm" id="h_portdis" style="width:50%;">
                                                <option value="">000: NONE</option>
                                                <?php foreach ($ports as $pt): ?>
                                                    <option value="<?php echo htmlspecialchars($pt['PORT_ID']); ?>"><?php echo htmlspecialchars($pt['PORT_ID'] . ': ' . $pt['DESCRIPTION']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group-custom">
                                        <label>Place & Country Destination</label>
                                        <div style="display:flex; gap:6px;">
                                            <select class="form-control form-control-sm" id="h_placedest" style="width:50%;" onchange="sync_country_dest()">
                                                <option value="">000: NONE</option>
                                                <?php foreach ($places as $pl): ?>
                                                    <option value="<?php echo htmlspecialchars($pl['PLACE_ID']); ?>"><?php echo htmlspecialchars($pl['PLACE_ID'] . ': ' . $pl['DESCRIPTION']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="form-control form-control-sm" id="h_countrydest" style="width:50%;">
                                                <option value="">000: NONE</option>
                                                <?php foreach ($places as $pl): ?>
                                                    <option value="<?php echo htmlspecialchars($pl['PLACE_ID']); ?>"><?php echo htmlspecialchars($pl['PLACE_ID'] . ': ' . $pl['DESCRIPTION']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group-custom" style="margin-bottom:0;">
                                        <label>Shipping Remarks (CTM1_REMARK2, 3)</label>
                                        <div style="display:flex; gap:6px;">
                                            <input type="text" class="form-control form-control-sm" id="h_remark2" placeholder="Shipping Remark 1..." style="width:50%;"/>
                                            <input type="text" class="form-control form-control-sm" id="h_remark3" placeholder="Shipping Remark 2..." style="width:50%;"/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Pricing Basis & Totals -->
                        <div class="col-md-6" style="margin-bottom: 12px;">
                            <div class="header-subcard" style="background:#f0fdf4; border-color:#bbf7d0;">
                                <div>
                                    <div class="subcard-heading" style="color:#166534; border-color:#86efac;">💰 Pricing Basis & Totals</div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>LME Type</label>
                                                <select class="form-control form-control-sm" id="h_lmetype" onchange="on_change_lme()">
                                                    <option value="D-DAILY">D-DAILY</option>
                                                    <option value="B-WBP LME">B-WBP LME</option>
                                                    <option value="W-WBP">W-WBP</option>
                                                    <option value="M-MONTHLY">M-MONTHLY</option>
                                                    <option value="O-MANUALLY">O-MANUALLY</option>
                                                    <option value="D-DAILY CASH">D-DAILY CASH</option>
                                                    <option value="M-MONTHLY CASH">M-MONTHLY CASH</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>LME Date</label>
                                                <input type="date" class="form-control form-control-sm" id="h_lmedate" value="<?php echo date('Y-m-d'); ?>" onchange="on_change_lme()"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Order LME</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_orderlme" placeholder="LME" value="0.00"/>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label style="color:#047857;">MJP Date</label>
                                                <input type="date" class="form-control form-control-sm" id="h_mjpdate" value="<?php echo date('Y-m-d'); ?>" onchange="on_change_mjp()"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>MJP Price</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_mjpprice" placeholder="MJP" value="0.00"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group-custom">
                                        <label style="color: #1e40af;">Standard Deviation / Factor / STDxFactor</label>
                                        <div style="display:flex; gap:8px;">
                                            <div style="width: 33%;">
                                                <input type="text" class="form-control form-control-sm" id="h_stdv" placeholder="Stdv" value="0.00" readonly style="width: 100%; background: #eff6ff; font-weight: 600; text-align: right; color: #1e3a8a;" title="Standard Deviation"/>
                                            </div>
                                            <div style="width: 33%;">
                                                <input type="text" class="form-control form-control-sm" id="h_factor" placeholder="Factor" value="0.00" readonly style="width: 100%; background: #eff6ff; font-weight: 600; text-align: right; color: #1e3a8a;" title="Factor"/>
                                            </div>
                                            <div style="width: 34%;">
                                                <input type="text" class="form-control form-control-sm" id="h_stdxfactor" placeholder="STDxFactor" value="0.00" readonly style="width: 100%; background: #dbeafe; font-weight: 700; text-align: right; color: #1e40af;" title="STD x Factor"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label style="color:#1d4ed8;">Total Amount (Items)</label>
                                                <input type="text" class="form-control form-control-sm" id="h_total_amount" value="0.00" readonly style="background:#e0e7ff; font-weight:700; color:#1e40af; text-align:right;"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Transportation</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_transport" placeholder="Transport" value="0.00" style="text-align:right;"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Total FOB</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_fob" placeholder="Total FOB" value="0.00" style="text-align:right; font-weight:600;"/>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Oversea Freight</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_freight" placeholder="Freight" value="0.00" oninput="calculate_header_total()" style="text-align:right;"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom">
                                                <label>Insurance</label>
                                                <input type="number" step="0.01" class="form-control form-control-sm" id="h_insurance" placeholder="Insurance" value="0.00" oninput="calculate_header_total()" style="text-align:right;"/>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom" style="margin-bottom:0;">
                                                <label style="color:#047857;">Grand Total</label>
                                                <input type="text" class="form-control form-control-sm" id="h_grand_total" value="0.00" readonly style="background:#dcfce7; font-weight:800; color:#15803d; text-align:right;"/>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: ITEMS GRID -->
                <div class="entry-card">
                    <div class="section-title">
                        <span>📦 Sales Order Items</span>
                        <button type="button" class="btn btn-sm btn-primary" onclick="open_item_modal()" style="font-weight:700;">
                            ➕ Add Item
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="entry_item_table">
                            <thead>
                                <tr>
                                    <th style="width: 45px;">Item</th>
                                    <th style="width: 65px;">Product</th>
                                    <th style="width: 75px;">Alloy</th>
                                    <th style="width: 60px;">Temper</th>
                                    <th style="width: 85px;">Grade</th>
                                    <th style="width: 90px;">Thickness</th>
                                    <th style="width: 90px;">Width</th>
                                    <th style="width: 90px;">Length</th>
                                    <th style="width: 100px;">Weight (kg)</th>
                                    <th style="width: 80px;">Pieces</th>
                                    <th style="width: 90px;">Unit Price</th>
                                    <th style="width: 110px;">Amount</th>
                                    <th style="width: 60px;">Status</th>
                                    <th style="width: 85px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="entry_item_body">
                                <tr><td colspan="14" align="center" style="color:#94a3b8; padding:20px;">No items added yet. Click "+ Add Item" to begin.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </form>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- MODAL: ADD / EDIT ITEM SPECIFICATION -->
<div class="modal fade" id="itemEditModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="width: 95%; max-width: 1350px;" role="document">
        <div class="modal-content" style="border-radius:10px; overflow:hidden;">
            <div class="modal-header" style="background:#f1f5f9; border-bottom:1px solid #cbd5e1; padding:10px 18px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-weight:800; color:#0f172a; font-size:15px;">
                    Sale Order (<span id="item_modal_action_title">Add</span> Item Detail) [ACSTMORDR1 - S1]
                </h4>
            </div>
            
            <div class="modal-body" style="padding:14px 18px; background:#f8fafc;">
                <input type="hidden" id="edit_index" value="-1"/>

                <!-- กล่องที่ 1: Detail Reference -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:12px;">
                    <div style="font-weight:800; font-size:11px; color:#4338ca; text-transform:uppercase; margin-bottom:8px; border-bottom:1px solid #e0e7ff; padding-bottom:3px;">
                        📌 Item Header & Customer Reference
                    </div>
                    
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group-custom">
                                <label style="color:#dc2626;">Sale Order Item</label>
                                <input type="text" class="form-control form-control-sm" id="i_item_no" value="001" readonly style="background:#fef2f2; font-weight:800; color:#dc2626; text-align:center;"/>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group-custom">
                                <label>Status</label>
                                <select class="form-control form-control-sm" id="i_status">
                                    <option value="OP">OP: OPEN</option>
                                    <option value="HL">HL: HOLD</option>
                                    <option value="CP">CP: COMPLETE</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Material In</label>
                                <select class="form-control form-control-sm" id="i_mat_in" onchange="on_change_item_mat_in()">
                                    <option value="BOI-MAT1">BOI-MAT1</option>
                                    <option value="BOI-MAT2">BOI-MAT2</option>
                                    <option value="BOI-GRS1">BOI-GRS1</option>
                                    <option value="BOI-IMP1">BOI-IMP1</option>
                                    <option value="BOI-IMP2">BOI-IMP2</option>
                                    <option value="NONE-BOI">NONE-BOI</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Purchase Order No.</label>
                                <input type="text" class="form-control form-control-sm" id="i_pono" placeholder="PO Number..."/>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group-custom">
                                <label>PO Item</label>
                                <input type="text" class="form-control form-control-sm" id="i_poitem" value="001" placeholder="001"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Customer Order</label>
                                <input type="text" class="form-control form-control-sm" id="i_cust_order" placeholder="Customer Order..."/>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Customer Sale Order</label>
                                <input type="text" class="form-control form-control-sm" id="i_cust_so" placeholder="Customer Sale Order..."/>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>End Customer Name</label>
                                <input type="text" class="form-control form-control-sm" id="i_endcust_name" placeholder="End Customer Name..."/>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>End Customer PO. No.</label>
                                <input type="text" class="form-control form-control-sm" id="i_endcust_po" placeholder="End Customer PO..."/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label style="color:#047857;">PO Date (Auto Lookup Period)</label>
                                <input type="date" class="form-control form-control-sm" id="i_podate" value="<?php echo date('Y-m-d'); ?>" onchange="on_change_item_podate()"/>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label style="color:#1d4ed8;">Price Period (PRICMSTR1)</label>
                                <select class="form-control form-control-sm" id="i_price_period" onchange="on_change_price_period()">
                                    <option value="">-- None --</option>
                                    <?php foreach ($periods as $prd): ?>
                                        <option value="<?php echo htmlspecialchars($prd['PRICE_PERIOD']); ?>"><?php echo htmlspecialchars($prd['PRICE_PERIOD']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Request Date</label>
                                <input type="date" class="form-control form-control-sm" id="i_req_date" value="<?php echo date('Y-m-d'); ?>"/>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group-custom">
                                <label>Confirm Date</label>
                                <input type="date" class="form-control form-control-sm" id="i_conf_date" value="<?php echo date('Y-m-d'); ?>"/>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group-custom" style="margin-bottom:0;">
                                <label style="color:#047857;">Customer Part No. (Auto Lookup PRODMSTR1)</label>
                                <input type="text" class="form-control form-control-sm" id="i_cust_part" placeholder="Enter Customer Part No..." onchange="on_change_customer_partno()" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase()"/>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-custom" style="margin-bottom:0;">
                                <label>Use Coil From</label>
                                <select class="form-control form-control-sm" id="i_coil_from" onchange="on_change_item_coil_from()">
                                    <?php foreach ($use_coil_suppliers as $sup): ?>
                                        <option value="<?php echo htmlspecialchars($sup['CSTMSPPL_ID']); ?>">
                                            <?php echo htmlspecialchars($sup['CSTMSPPL_ID'] . ': ' . $sup['CONSIGNEE_COMPANY']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-custom" style="margin-bottom:0;">
                                <label>Full Coil</label>
                                <select class="form-control form-control-sm" id="i_full_coil" onchange="autoCalculateItemPrice()">
                                    <option value="F">F: FULL COIL</option>
                                    <option value="N">N: NOT FULL COIL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- กล่องที่ 2: 3 คอลัมน์หลัก -->
                <div class="row">
                    <!-- Column 1: Specification Production & UOM Conversion -->
                    <div class="col-md-4">
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; height:100%;">
                            <div style="font-weight:800; font-size:11px; color:#b91c1c; text-transform:uppercase; margin-bottom:8px; border-bottom:1px solid #fee2e2; padding-bottom:3px;">
                                ⚙️ Specification Production
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Product ID</label>
                                        <select class="form-control form-control-sm" id="i_product" onchange="on_change_product(true)">
                                            <?php foreach ($products as $pr): ?>
                                                <?php 
                                                    $pid = trim($pr['PRODUCT_ID']);
                                                    $pmodel = trim($pr['PRODUCT_MODEL']);
                                                    $val = ($pid === 'NC') ? "{$pid}|{$pmodel}" : $pid;
                                                ?>
                                                <option value="<?php echo htmlspecialchars($val); ?>" 
                                                        data-pid="<?php echo htmlspecialchars($pid); ?>"
                                                        data-model="<?php echo htmlspecialchars($pmodel); ?>"
                                                        data-area="<?php echo (float)$pr['AREA_SIZE']; ?>"
                                                        data-partno="<?php echo htmlspecialchars(trim($pr['CUSTOMER_PARTNO'])); ?>">
                                                    <?php echo htmlspecialchars("{$pid} : {$pmodel}"); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Product Model (Area: <span id="lbl_area_size">0</span>)</label>
                                        <input type="text" class="form-control form-control-sm" id="i_prod_model" value="CIRCLE" readonly style="background:#00008b; color:#fff; font-weight:700;"/>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Alloy</label>
                                        <select class="form-control form-control-sm" id="i_alloy" onchange="auto_gen_desc(); autoCalculateItemPrice();">
                                            <?php foreach ($alloys as $a): ?>
                                                <option value="<?php echo htmlspecialchars($a['ALLOY']); ?>"><?php echo htmlspecialchars($a['ALLOY']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Temper</label>
                                        <select class="form-control form-control-sm" id="i_temper" onchange="auto_gen_desc(); autoCalculateItemPrice();">
                                            <?php foreach ($tempers as $t): ?>
                                                <option value="<?php echo htmlspecialchars($t['TEMPER']); ?>"><?php echo htmlspecialchars($t['TEMPER']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Surface Grade (SG)</label>
                                        <select class="form-control form-control-sm" id="i_sg" onchange="autoCalculateItemPrice()">
                                            <?php foreach ($sgs as $sg): ?>
                                                <option value="<?php echo htmlspecialchars($sg['SURFACE_GRADE']); ?>"><?php echo htmlspecialchars($sg['SURFACE_GRADE']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Metallurgical Grade (MG)</label>
                                        <select class="form-control form-control-sm" id="i_mg" onchange="autoCalculateItemPrice()">
                                            <?php foreach ($mgs as $mg): ?>
                                                <option value="<?php echo htmlspecialchars($mg['METALLURGICAL_GRADE']); ?>"><?php echo htmlspecialchars($mg['METALLURGICAL_GRADE']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Thickness (mm)</label>
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_thickness" value="2.800" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Width (mm)</label>
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_width" value="290.000" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Length (mm)</label>
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_length" value="0.000" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>T5 Temperature</label>
                                        <input type="text" class="form-control form-control-sm" id="i_t5" placeholder="T5 Temp..."/>
                                    </div>
                                </div>
                            </div>

                            <!-- For Different UOM of Dimension -->
                            <div class="uom-group-box">
                                <div class="uom-group-title">For Different UOM of Dimension</div>
                                <div class="row">
                                    <div class="col-md-6" style="padding-right:4px;">
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_thk" placeholder="Thk" oninput="sync_dim2_to_metric()" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                    <div class="col-md-6" style="padding-left:4px;">
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_wd" placeholder="Width" oninput="sync_dim2_to_metric()" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                                <div class="row" style="margin-top:4px;">
                                    <div class="col-md-7" style="padding-right:4px;">
                                        <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_len" placeholder="Length" oninput="sync_dim2_to_metric()" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                    <div class="col-md-5" style="padding-left:4px;">
                                        <select class="form-control form-control-sm" id="i_ct2u_uom_dim" onchange="sync_metric_to_dim2()">
                                            <option value="inch">inch</option>
                                            <option value="mm.">mm.</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- For Different UOM of Weight -->
                            <div class="uom-group-box" style="margin-bottom:0;">
                                <div class="uom-group-title">For Different UOM of Weight</div>
                                <div class="row">
                                    <div class="col-md-4" style="padding-right:3px;">
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_ct2u_order_wt" placeholder="Weight" oninput="sync_wt2_to_wt()" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                    <div class="col-md-4" style="padding-left:3px; padding-right:3px;">
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_ct2u_order_pc" placeholder="Pieces" readonly style="background:#f8fafc;"/>
                                    </div>
                                    <div class="col-md-4" style="padding-left:3px;">
                                        <select class="form-control form-control-sm" id="i_ct2u_uom_wt" onchange="sync_wt_to_wt2()">
                                            <option value="lbs.">lbs.</option>
                                            <option value="kg.">kg.</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Column 2: Unit Price -->
                    <div class="col-md-4">
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; height:100%;">
                            <div style="font-weight:800; font-size:11px; color:#15803d; text-transform:uppercase; margin-bottom:8px; border-bottom:1px solid #dcfce7; padding-bottom:3px;">
                                💵 Unit Price & LME Specification
                            </div>

                            <div class="form-group-custom">
                                <label>Actual Price</label>
                                <div style="display:flex; gap:6px;">
                                    <input type="number" step="0.0001" class="form-control form-control-sm" id="i_price" value="0.0000" oninput="calculate_item_amount()" style="font-weight:700;"/>
                                    <button type="button" class="btn btn-xs btn-default" onclick="calc_price_from_backend(true)" title="Calculate Price from S1 Logic">
                                        ⚙️ Calc
                                    </button>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>LME Type</label>
                                        <select class="form-control form-control-sm" id="i_lmetype" onchange="on_change_item_lme()">
                                            <option value="D-DAILY">D-DAILY</option>
                                            <option value="B-WBP LME">B-WBP LME</option>
                                            <option value="W-WBP">W-WBP</option>
                                            <option value="M-MONTHLY">M-MONTHLY</option>
                                            <option value="O-MANUALLY">O-MANUALLY</option>
                                            <option value="D-DAILY CASH">D-DAILY CASH</option>
                                            <option value="M-MONTHLY CASH">M-MONTHLY CASH</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>LME Date</label>
                                        <input type="date" class="form-control form-control-sm" id="i_lmedate" value="<?php echo date('Y-m-d'); ?>" onchange="on_change_item_lme()"/>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-custom">
                                <label>Order LME</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="i_orderlme" value="0.00"/>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>MJP Date</label>
                                        <input type="date" class="form-control form-control-sm" id="i_mjpdate" value="<?php echo date('Y-m-d'); ?>" onchange="on_change_item_mjp()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>MJP Price</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="i_mjpprice" value="0.00"/>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label>Stdv</label>
                                        <input type="text" class="form-control form-control-sm" id="i_stdv" value="0.00" readonly style="background:#eff6ff; text-align:right;"/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label>Factor</label>
                                        <input type="text" class="form-control form-control-sm" id="i_factor" value="0.00" readonly style="background:#eff6ff; text-align:right;"/>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group-custom">
                                        <label>STDxFactor</label>
                                        <input type="text" class="form-control form-control-sm" id="i_stdxfactor" value="0.00" readonly style="background:#dbeafe; font-weight:700; text-align:right;"/>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-custom" style="margin-top:10px; margin-bottom:0;">
                                <label style="color:#047857; font-size:12px;">Amount</label>
                                <input type="text" class="form-control form-control-sm vb-display-blue" id="i_amount" value="0.00" readonly style="height:38px !important; font-size:16px !important;"/>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: Order -->
                    <div class="col-md-4">
                        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; height:100%;">
                            <div style="font-weight:800; font-size:11px; color:#1d4ed8; text-transform:uppercase; margin-bottom:8px; border-bottom:1px solid #dbeafe; padding-bottom:3px;">
                                📦 Order, Tolerance & Quantities
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Weight per Piece (kg)</label>
                                        <input type="number" step="0.0001" class="form-control form-control-sm" id="i_wt_piece" value="0.0000" oninput="calculate_item_qty_from_pcs()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Max - Min Tolerance (%)</label>
                                        <div style="display:flex; gap:4px;">
                                            <input type="number" class="form-control form-control-sm" id="i_maxtol" value="0" placeholder="Max" oninput="update_s1_calculated_totals()" onchange="autoCalculateItemPrice()"/>
                                            <input type="number" class="form-control form-control-sm" id="i_mintol" value="0" placeholder="Min" oninput="update_s1_calculated_totals()" onchange="autoCalculateItemPrice()"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Qty -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Order Wt (kg)</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_weight" value="0" oninput="sync_wt_to_wt2(); calculate_item_pcs_from_wt();" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Order Pc</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_pieces" value="0" oninput="sync_wt_to_wt2(); calculate_item_qty_from_pcs();" onchange="autoCalculateItemPrice()"/>
                                    </div>
                                </div>
                            </div>

                            <!-- Reject Qty -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Reject Wt (kg)</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_reject_wt" value="0" oninput="update_s1_calculated_totals()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Reject Pc</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_reject_pc" value="0" oninput="update_s1_calculated_totals()"/>
                                    </div>
                                </div>
                            </div>

                            <!-- Replace Qty -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Replace Wt (kg)</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_replace_wt" value="0" oninput="update_s1_calculated_totals()"/>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group-custom">
                                        <label>Replace Pc</label>
                                        <input type="number" step="1" class="form-control form-control-sm" id="i_replace_pc" value="0" oninput="update_s1_calculated_totals()"/>
                                    </div>
                                </div>
                            </div>

                            <!-- S1 Calculated Labels -->
                            <div style="background:#00008b; color:#ffffff; border-radius:6px; padding:6px 10px; margin-top:4px;">
                                <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px;">
                                    <span>Max Order Qty:</span>
                                    <span><b id="lbl_max_wt">0</b> kg / <b id="lbl_max_pc">0</b> pc</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px;">
                                    <span>Total Order Qty:</span>
                                    <span><b id="lbl_tot_wt">0</b> kg / <b id="lbl_tot_pc">0</b> pc</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:11px;">
                                    <span>Min Order Qty:</span>
                                    <span><b id="lbl_min_wt">0</b> kg / <b id="lbl_min_pc">0</b> pc</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- กล่องที่ 3: Description, Remark & Special Label -->
                <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; margin-top:12px;">
                    <div class="form-group-custom">
                        <label>Description</label>
                        <input type="text" class="form-control form-control-sm" id="i_desc" placeholder="Product specification description..."/>
                    </div>
                    <div class="form-group-custom">
                        <label>Remark</label>
                        <input type="text" class="form-control form-control-sm" id="i_remark" placeholder="Special item instructions..."/>
                    </div>
                    <div class="form-group-custom" style="margin-bottom:0;">
                        <label style="color:#2563eb;">Special Label (4 lines)</label>
                        <div class="row" style="margin: 0 -4px;">
                            <div class="col-md-6" style="padding: 0 4px; margin-bottom: 4px;">
                                <input type="text" class="form-control form-control-sm" id="i_special_label1" placeholder="Special Label 1..."/>
                            </div>
                            <div class="col-md-6" style="padding: 0 4px; margin-bottom: 4px;">
                                <input type="text" class="form-control form-control-sm" id="i_special_label2" placeholder="Special Label 2..."/>
                            </div>
                            <div class="col-md-6" style="padding: 0 4px;">
                                <input type="text" class="form-control form-control-sm" id="i_special_label3" placeholder="Special Label 3..."/>
                            </div>
                            <div class="col-md-6" style="padding: 0 4px;">
                                <input type="text" class="form-control form-control-sm" id="i_special_label4" placeholder="Special Label 4..."/>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-footer" style="background:#f1f5f9; border-top:1px solid #cbd5e1; padding:10px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="confirm_item_modal()" style="font-weight:700;">
                    ✓ Apply Item
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
var orderItems = [];
var cur_stdv = 0.00;
var cur_factor = 0.00;
var cur_stdxfactor = 0.00;
var currentAreaSize = 0.0;

$(document).ready(function() {
    <?php if ($is_edit_mode): ?>
        load_existing_so('<?php echo $sono; ?>');
    <?php else: ?>
        on_change_category();
        check_mil_customer();
        on_change_lme();
        on_change_mjp();
    <?php endif; ?>
});

function check_mil_customer() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    var is_mil = (cid.substring(0, 3) === 'MIL');

    var lmetype = document.getElementById("h_lmetype");
    var lmedate = document.getElementById("h_lmedate");
    var orderlme = document.getElementById("h_orderlme");
    var mjpdate = document.getElementById("h_mjpdate");
    var mjpprice = document.getElementById("h_mjpprice");

    if (is_mil) {
        lmetype.disabled = true;
        lmedate.disabled = true;
        orderlme.disabled = true;
        mjpdate.disabled = true;
        mjpprice.disabled = true;

        orderlme.value = "0.00";
        mjpprice.value = "0.00";
        document.getElementById("h_stdv").value = "0.00";
        document.getElementById("h_factor").value = "0.00";
        document.getElementById("h_stdxfactor").value = "0.00";
        cur_stdv = 0;
        cur_factor = 0;
        cur_stdxfactor = 0;
    } else {
        lmetype.disabled = false;
        lmedate.disabled = false;
        orderlme.disabled = false;
        mjpdate.disabled = false;
        mjpprice.disabled = false;
    }
}

function on_change_category() {
    var cat = document.getElementById("h_order_cat").value;

    <?php if (!$is_edit_mode): ?>
    var prefixField = document.getElementById("h_so_prefix");
    if (prefixField) {
        if (cat === 'ORSP') prefixField.value = 'TT';
        else if (cat === 'SCRR' || cat === 'SCCC' || cat === 'SCCO' || cat === 'SCSC' || cat === 'SCCS' || cat === 'SCSH' || cat === 'ORSV') prefixField.value = 'RC';
        else prefixField.value = 'SO';
    }
    <?php endif; ?>

    if (cat === 'ORD1') {
        document.getElementById("i_mat_in").value = 'BOI-IMP1';
        document.getElementById("i_coil_from").value = 'MAL';
    } else if (cat === 'ORD2') {
        document.getElementById("i_mat_in").value = 'BOI-IMP2';
        document.getElementById("i_coil_from").value = 'MAL';
    } else if (cat === 'ORDR' || cat === 'OVMI') {
        document.getElementById("i_mat_in").value = 'BOI-MAT1';
        document.getElementById("i_coil_from").value = 'MAT';
    } else if (cat === 'SCCO') {
        document.getElementById("i_mat_in").value = 'NONE-BOI';
        document.getElementById("i_coil_from").value = 'MAT';
    }
}

function sync_country_dest() {
    var place = document.getElementById("h_placedest").value;
    var country = document.getElementById("h_countrydest");
    if (country && !country.value && place) {
        country.value = place;
    }
}

function on_change_customer() {
    var cid = document.getElementById("h_customer").value;
    var cat = document.getElementById("h_order_cat").value;

    if (cat.substring(0, 4) === 'OVMI' && cid.substring(0, 3) !== 'MIL') {
        alert("Order Category: OVMI must be paired with customer starting with MIL");
    }

    check_mil_customer();

    if (!cid) return;

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_customer_info', cust_id: cid },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.customer) {
                var c = res.customer;
                if (c.PAYMENT_ID) document.getElementById("h_payment").value = c.PAYMENT_ID;
                if (c.SHIPMENT_ID) document.getElementById("h_shipment").value = c.SHIPMENT_ID;
                if (c.CURRENCY_ID) document.getElementById("h_currency").value = c.CURRENCY_ID;
                if (c.CSSP_PORTLOADING) document.getElementById("h_portload").value = c.CSSP_PORTLOADING;
                if (c.CSSP_PORTDISCHARGE) document.getElementById("h_portdis").value = c.CSSP_PORTDISCHARGE;
                if (c.CSSP_PLACEDESTINATION) {
                    document.getElementById("h_placedest").value = c.CSSP_PLACEDESTINATION;
                    document.getElementById("h_countrydest").value = c.CSSP_COUNTRYDESTINATION || c.CSSP_PLACEDESTINATION;
                }
                if (c.DEFAULT_EXCHANGE_RATE) document.getElementById("h_exrate").value = Number(c.DEFAULT_EXCHANGE_RATE).toFixed(5);
                if (c.LMETYPE) document.getElementById("h_lmetype").value = c.LMETYPE;

                if (!document.getElementById("h_supplier_mat").value) {
                    document.getElementById("h_supplier_mat").value = c.SUPPLIER_MATCODE || '';
                }
                if (!document.getElementById("h_customs_code").value) {
                    document.getElementById("h_customs_code").value = c.HARMONIZE_CODE || '';
                }

                document.getElementById("i_special_label1").value = c.SPECIAL_LABEL1 || '';
                document.getElementById("i_special_label2").value = c.SPECIAL_LABEL2 || '';
                document.getElementById("i_special_label3").value = c.SPECIAL_LABEL3 || '';
                document.getElementById("i_special_label4").value = c.SPECIAL_LABEL4 || '';

                if (cid.substring(0, 3) !== 'MIL') {
                    on_change_lme();
                    on_change_mjp();
                }
            }
        }
    });
}

function on_change_lme() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    if (cid.substring(0, 3) === 'MIL') return;

    var lme_type = document.getElementById("h_lmetype").value;
    var lme_date = document.getElementById("h_lmedate").value;
    if (!lme_date) return;

    if (lme_type === 'O-MANUALLY' || lme_type === 'D-DAILY CASH' || lme_type === 'M-MONTHLY CASH') {
        document.getElementById("h_stdv").value = "0.00";
        document.getElementById("h_factor").value = "0.00";
        document.getElementById("h_stdxfactor").value = "0.00";
        cur_stdv = 0;
        cur_factor = 0;
        cur_stdxfactor = 0;
        return;
    }

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_lme_rates', type: lme_type, date: lme_date },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("h_orderlme").value = parseFloat(res.order_lme || 0).toFixed(2);
                document.getElementById("h_stdv").value = parseFloat(res.stdev || 0).toFixed(2);
                document.getElementById("h_factor").value = parseFloat(res.factor || 0).toFixed(2);
                document.getElementById("h_stdxfactor").value = parseFloat(res.stdx_factor || 0).toFixed(2);

                cur_stdv = parseFloat(res.stdev || 0);
                cur_factor = parseFloat(res.factor || 0);
                cur_stdxfactor = parseFloat(res.stdx_factor || 0);
            } else {
                document.getElementById("h_orderlme").value = "0.00";
                document.getElementById("h_stdv").value = "0.00";
                document.getElementById("h_factor").value = "0.00";
                document.getElementById("h_stdxfactor").value = "0.00";
                cur_stdv = 0;
                cur_factor = 0;
                cur_stdxfactor = 0;
            }
        }
    });
}

function on_change_mjp() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    if (cid.substring(0, 3) === 'MIL') return;

    var mjp_date = document.getElementById("h_mjpdate").value;
    if (!mjp_date) return;

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_lme_rates', type: 'MJP', date: mjp_date },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("h_mjpprice").value = parseFloat(res.mjp_price || 0).toFixed(2);
            } else {
                document.getElementById("h_mjpprice").value = "0.00";
            }
        }
    });
}

function on_change_item_lme() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    if (cid.substring(0, 3) === 'MIL') return;

    var lme_type = document.getElementById("i_lmetype").value;
    var lme_date = document.getElementById("i_lmedate").value;
    if (!lme_date) return;

    if (lme_type === 'O-MANUALLY' || lme_type === 'D-DAILY CASH' || lme_type === 'M-MONTHLY CASH') {
        document.getElementById("i_stdv").value = "0.00";
        document.getElementById("i_factor").value = "0.00";
        document.getElementById("i_stdxfactor").value = "0.00";
        return;
    }

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_lme_rates', type: lme_type, date: lme_date },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("i_orderlme").value = parseFloat(res.order_lme || 0).toFixed(2);
                document.getElementById("i_stdv").value = parseFloat(res.stdev || 0).toFixed(2);
                document.getElementById("i_factor").value = parseFloat(res.factor || 0).toFixed(2);
                document.getElementById("i_stdxfactor").value = parseFloat(res.stdx_factor || 0).toFixed(2);
            }
        }
    });
}

function on_change_item_mjp() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    if (cid.substring(0, 3) === 'MIL') return;

    var mjp_date = document.getElementById("i_mjpdate").value;
    if (!mjp_date) return;

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_lme_rates', type: 'MJP', date: mjp_date },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("i_mjpprice").value = parseFloat(res.mjp_price || 0).toFixed(2);
            }
        }
    });
}

// =========================================================================
// เงื่อนไข S1: การจัดการ Price Period และตาราง PRICMSTR1
// =========================================================================
function validatePricePeriodConsistency() {
    var cat = document.getElementById("h_order_cat").value.substring(0, 4);
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    var matIn = document.getElementById("i_mat_in").value;
    var coilFrom = document.getElementById("i_coil_from").value.trim().toUpperCase();
    var period = document.getElementById("i_price_period").value.trim();

    if (cat === 'ORDR') {
        if (matIn === 'BOI-IMP1' || matIn === 'BOI-IMP2') {
            alert("ข้อมูล Material In กับ Order Category ไม่สอดคล้องกัน ระบบจะปรับเปลี่ยน Material In เป็น BOI-MAT1 ให้ถูกต้อง");
            document.getElementById("i_mat_in").value = 'BOI-MAT1';
            matIn = 'BOI-MAT1';
            if (coilFrom !== 'MAT') {
                document.getElementById("i_coil_from").value = 'MAT';
            }
        } else if (matIn === 'NONE-BOI') {
            if (cid.substring(0, 3) === 'MIL' && period !== '' && period.length >= 9) {
                var suffix = parseInt(period.substring(7, 9), 10);
                if (!isNaN(suffix) && suffix < 50) {
                    alert("ข้อมูล Material In (NONE-BOI) กับ Order Category กับ Price Period ไม่สอดคล้องกัน (เลขงวดราคาควร >= 50) กรุณาตรวจสอบ");
                }
            }
        } else if (['BOI-MAT1', 'BOI-MAT2', 'BOI-GRS1'].includes(matIn)) {
            if (cid.substring(0, 3) === 'MIL' && period !== '' && period.length >= 9) {
                var suffix = parseInt(period.substring(7, 9), 10);
                if (!isNaN(suffix) && suffix >= 50) {
                    alert("ข้อมูล Material In กับ Order Category กับ Price Period ไม่สอดคล้องกัน (เลขงวดราคาควร < 50) กรุณาตรวจสอบ");
                }
            }
            if (coilFrom !== 'MAT') {
                alert("ข้อมูล Order Category กับ Material In กับ Coil From ไม่สอดคล้องกัน ระบบจะปรับ Coil From เป็น MAT");
                document.getElementById("i_coil_from").value = 'MAT';
            }
        }
    } else if (cat === 'ORD1' || cat === 'ORD2') {
        var expectedMat = (cat === 'ORD1') ? 'BOI-IMP1' : 'BOI-IMP2';
        if (matIn !== expectedMat) {
            alert("ข้อมูล Material In กับ Order Category ไม่สอดคล้องกัน ระบบจะปรับเปลี่ยน Material In เป็น " + expectedMat);
            document.getElementById("i_mat_in").value = expectedMat;
        }
        if (coilFrom === 'MAT') {
            alert("ข้อมูล Order Category กับ Material In กับ Coil From ไม่สอดคล้องกัน ระบบจะปรับ Coil From เป็น MAL");
            document.getElementById("i_coil_from").value = 'MAL';
        }
        if (cid.substring(0, 3) === 'MIL' && period !== '' && period.length >= 9) {
            var suffix = parseInt(period.substring(7, 9), 10);
            if (!isNaN(suffix) && suffix < 50) {
                alert("ข้อมูล Material In กับ Order Category กับ Price Period ไม่สอดคล้องกัน (เลขงวดราคาควร >= 50) กรุณาตรวจสอบ");
            }
        }
    } else if (cat === 'OVMI') {
        if (!['BOI-MAT1', 'BOI-MAT2', 'BOI-GRS1'].includes(matIn)) {
            alert("ข้อมูล Material In กับ Order Category ไม่สอดคล้องกัน ระบบจะปรับเปลี่ยน Material In เป็น BOI-MAT1");
            document.getElementById("i_mat_in").value = 'BOI-MAT1';
        }
        if (coilFrom !== 'MAT') {
            alert("ข้อมูล Order Category กับ Coil From ไม่สอดคล้องกัน ระบบจะปรับ Coil From เป็น MAT");
            document.getElementById("i_coil_from").value = 'MAT';
        }
        if (cid.substring(0, 3) === 'MIL') {
            if (period !== '' && period.length >= 9) {
                var suffix = parseInt(period.substring(7, 9), 10);
                if (!isNaN(suffix) && suffix >= 50) {
                    alert("ข้อมูล Material In กับ Order Category กับ Price Period ไม่สอดคล้องกัน (เลขงวดราคาควร < 50) กรุณาตรวจสอบ");
                }
            }
        } else {
            alert("Order Category OVMI ต้องใช้กับลูกค้ากลุ่ม MIL เท่านั้น");
        }
    }
}

function on_change_item_podate() {
    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    var cat = document.getElementById("h_order_cat").value.substring(0, 4);
    var period = document.getElementById("i_price_period").value;
    var poDate = document.getElementById("i_podate").value;
    var matIn = document.getElementById("i_mat_in").value;

    // เงื่อนไข S1: เมื่อเปลี่ยน PO Date และ Price Period ยังว่าง (สำหรับลูกค้า MIL และไม่ใช่ ORSV) ให้ค้นหาจาก PRICMSTR1 อัตโนมัติ
    if (period === '' && cid.substring(0, 3) === 'MIL' && cat !== 'ORSV' && poDate !== '') {
        $.ajax({
            url: 'model/so_service_mats.php',
            type: 'GET',
            data: { action: 'get_price_period_by_podate', cust_id: cid, mat_in: matIn, po_date: poDate },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.price_period) {
                    document.getElementById("i_price_period").value = res.price_period;
                    autoCalculateItemPrice();
                } else {
                    autoCalculateItemPrice();
                }
            }
        });
    } else {
        autoCalculateItemPrice();
    }
}

function on_change_price_period() {
    validatePricePeriodConsistency();
    autoCalculateItemPrice();
}

function on_change_item_mat_in() {
    auto_gen_desc();
    validatePricePeriodConsistency();
}

function on_change_item_coil_from() {
    validatePricePeriodConsistency();
    autoCalculateItemPrice();
}

// ฟังก์ชันคำนวณราคาอัตโนมัติตาม S1 เมื่อมีมิติ น้ำหนัก และ Price Period ครบถ้วน
function autoCalculateItemPrice() {
    var thk = parseFloat(document.getElementById("i_thickness").value) || 0;
    var wd = parseFloat(document.getElementById("i_width").value) || 0;
    var wt = parseFloat(document.getElementById("i_weight").value) || 0;
    var period = document.getElementById("i_price_period").value;
    var cat = document.getElementById("h_order_cat").value.substring(0, 4);

    if (thk > 0 && wd > 0 && wt > 0 && period !== '' && cat !== 'ORSV') {
        calc_price_from_backend(false);
    }
}

// =========================================================================
// ฟังก์ชันค้นหาข้อมูลจาก PRODMSTR1 เมื่อกรอก Customer Part No.
// =========================================================================
function on_change_customer_partno() {
    var partNo = document.getElementById("i_cust_part").value.trim();
    if (!partNo) return;

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_product_by_partno', part_no: partNo },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                var pid = res.product_id;
                var pmodel = res.product_model;
                var targetVal = (pid === 'NC') ? (pid + '|' + pmodel) : pid;

                var prodSelect = document.getElementById("i_product");
                for (var i = 0; i < prodSelect.options.length; i++) {
                    if (prodSelect.options[i].value === targetVal || prodSelect.options[i].getAttribute('data-pid') === pid) {
                        prodSelect.selectedIndex = i;
                        break;
                    }
                }

                document.getElementById("i_prod_model").value = pmodel;
                currentAreaSize = parseFloat(res.area_size || 0);
                document.getElementById("lbl_area_size").innerText = currentAreaSize;

                on_change_product(false);
            }
        }
    });
}

function on_change_product(is_manual_change) {
    var prodSelect = document.getElementById("i_product");
    var selectedOpt = prodSelect.options[prodSelect.selectedIndex];
    var pid = selectedOpt ? selectedOpt.getAttribute('data-pid') : prodSelect.value;
    var pmodel = selectedOpt ? selectedOpt.getAttribute('data-model') : '';
    var optArea = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-area') || 0) : 0;
    var optPartNo = selectedOpt ? (selectedOpt.getAttribute('data-partno') || '') : '';

    var lenField = document.getElementById("i_length");
    var lenCtField = document.getElementById("i_ct2u_len");
    var prodModelField = document.getElementById("i_prod_model");
    var maxtol = document.getElementById("i_maxtol");
    var mintol = document.getElementById("i_mintol");

    if (pid === 'CO') {
        prodModelField.value = "COIL";
        currentAreaSize = 0;
        lenField.value = "0.000";
        lenCtField.value = "0.000";
        lenField.disabled = true;
        lenCtField.disabled = true;
        maxtol.disabled = true;
        mintol.disabled = true;
    } else if (pid === 'CC') {
        prodModelField.value = "CIRCLE";
        currentAreaSize = 0;
        lenField.value = "0.000";
        lenCtField.value = "0.000";
        lenField.disabled = true;
        lenCtField.disabled = true;
        maxtol.disabled = false;
        mintol.disabled = false;
    } else if (pid === 'SH') {
        prodModelField.value = "SHEET";
        currentAreaSize = 0;
        lenField.disabled = false;
        lenCtField.disabled = false;
        maxtol.disabled = false;
        mintol.disabled = false;
    } else if (pid === 'NC') {
        prodModelField.value = pmodel;
        if (is_manual_change) {
            currentAreaSize = optArea;
        }
        lenField.disabled = false;
        lenCtField.disabled = false;
        maxtol.disabled = false;
        mintol.disabled = false;

        var cid = document.getElementById("h_customer").value.trim().toUpperCase();
        var custPart = document.getElementById("i_cust_part").value.trim();
        if (is_manual_change && cid.substring(0, 3) === 'MIL' && optPartNo !== '' && custPart !== optPartNo) {
            alert("Customer Part No.: '" + custPart + "' does not match PRODMSTR1: '" + optPartNo + "'");
            document.getElementById("i_cust_part").focus();
        }
    } else {
        prodModelField.value = pmodel || pid;
        currentAreaSize = 0;
        lenField.disabled = false;
        lenCtField.disabled = false;
        maxtol.disabled = false;
        mintol.disabled = false;
    }

    document.getElementById("lbl_area_size").innerText = currentAreaSize;
    calculate_item_weight();
    auto_gen_desc();
    if (is_manual_change) {
        autoCalculateItemPrice();
    }
}

function load_existing_so(sono) {
    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_so_details', sono: sono },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.header) {
                var h = res.header;
                document.getElementById("h_so_no").value = h.SALEORDER_NO;
                document.getElementById("h_order_cat").value = h.ORDER_CATEGORY || 'ORDR';
                document.getElementById("h_customer").value = h.CSTMSPPL_ID || '';
                document.getElementById("h_supplier_mat").value = h.SUPPLIER_MATCODE || '';
                document.getElementById("h_po_no").value = h.CTM1_PONO || '';
                document.getElementById("h_customs_code").value = h.CUSTOMS_CODE || '';
                document.getElementById("h_cust_order").value = h.CTM1_CUSTOMERORDER || '';
                document.getElementById("h_cust_so").value = h.CTM1_CUSTOMERSALEORDER || '';
                document.getElementById("h_endcust_name").value = h.CTM1_ENDCUSTOMERNAME || '';
                document.getElementById("h_endcust_po").value = h.CTM1_ENDCUSTOMERPO || '';

                document.getElementById("h_so_date").value = h.SALEORDER_DATE ? h.SALEORDER_DATE.substring(0, 10) : '';
                document.getElementById("h_ship_date").value = h.INTENDSHIP_DATE ? h.INTENDSHIP_DATE.substring(0, 10) : '';
                document.getElementById("h_currency").value = h.CURRENCY_ID || 'USD';
                document.getElementById("h_exrate").value = Number(h.EXCHANGE_RATE || 1).toFixed(5);
                document.getElementById("h_tax").value = h.CTM1_TAXPERCENT !== null ? h.CTM1_TAXPERCENT : 0;
                document.getElementById("h_shippedby").value = h.CTM1_SHIPPEDBY || '';
                document.getElementById("h_tolerance").value = h.CTM1_TOLERANCE || '';
                document.getElementById("h_packing").value = h.CTM1_PACKING || '';

                document.getElementById("h_payment").value = h.PAYMENT_ID || '';
                document.getElementById("h_shipment").value = h.SHIPMENT_ID || '';
                document.getElementById("h_portload").value = h.CTM1_PORTLOADING || '';
                document.getElementById("h_portdis").value = h.CTM1_PORTDISCHARGE || '';
                document.getElementById("h_placedest").value = h.CTM1_PLACEDESTINATION || '';
                document.getElementById("h_countrydest").value = h.CTM1_COUNTRYDESTINATION || h.CTM1_PLACEDESTINATION || '';

                document.getElementById("h_remark1").value = h.CTM1_REMARK1 || '';
                document.getElementById("h_remark2").value = h.CTM1_REMARK2 || '';
                document.getElementById("h_remark3").value = h.CTM1_REMARK3 || '';

                document.getElementById("h_chk_wholeweight").checked = (h.CTM1_WHOLEWEIGHT == 1);
                document.getElementById("h_chk_markskid").checked = (h.CTM1_MARKSKID == 1);
                document.getElementById("h_chk_serialcode").checked = (h.CTM1_SERIALCODE == 1);
                document.getElementById("h_chk_scrapwire").checked = (h.CTM1_SCRAPWIRE == 1);

                document.getElementById("h_lmetype").value = h.CTM1_LMETYPE || 'M-MONTHLY';
                if (h.CTM1_LMEDATE) {
                    document.getElementById("h_lmedate").value = h.CTM1_LMEDATE.substring(0, 10);
                }
                if (h.CTM1_MJPDATE) {
                    document.getElementById("h_mjpdate").value = h.CTM1_MJPDATE.substring(0, 10);
                }
                
                document.getElementById("h_orderlme").value = Number(h.CTM1_ORDERLME || 0).toFixed(2);
                document.getElementById("h_mjpprice").value = Number(h.CTM1_MJPPRICE || 0).toFixed(2);

                document.getElementById("h_stdv").value = Number(h.CTM1_ORDERSTDV || 0).toFixed(2);
                document.getElementById("h_factor").value = Number(h.CTM1_ORDERFACTOR || 0).toFixed(2);
                document.getElementById("h_stdxfactor").value = Number(h.CTM1_ORDERSTDXFACTOR || 0).toFixed(2);
                cur_stdv = parseFloat(h.CTM1_ORDERSTDV || 0);
                cur_factor = parseFloat(h.CTM1_ORDERFACTOR || 0);
                cur_stdxfactor = parseFloat(h.CTM1_ORDERSTDXFACTOR || 0);

                document.getElementById("h_transport").value = Number(h.CTM1_TRANSPORTATION || 0).toFixed(2);
                document.getElementById("h_fob").value = Number(h.CTM1_FOBTOTALAMOUNT || 0).toFixed(2);
                document.getElementById("h_freight").value = Number(h.CTM1_OVERSEAFREIGHT || 0).toFixed(2);
                document.getElementById("h_insurance").value = Number(h.CTM1_INSURANCE || 0).toFixed(2);

                orderItems = res.items || [];
                render_items_grid();
                check_mil_customer();
            }
        }
    });
}

function render_items_grid() {
    var tbody = '';
    var total_amount = 0;

    if (orderItems.length > 0) {
        orderItems.forEach(function(it, idx) {
            //var amt = parseFloat(it.AMOUNT || (it.CTM2_ORDERWEIGHT * it.CTM2_ACTUALPRICE) || 0);
            //total_amount += amt;
            var base_wt = (it.CT2U_ORDERWEIGHT !== undefined && it.CT2U_ORDERWEIGHT !== null) ? it.CT2U_ORDERWEIGHT : it.CTM2_ORDERWEIGHT;
            var amt = parseFloat(it.AMOUNT || (base_wt * it.CTM2_ACTUALPRICE) || 0);
            total_amount += amt;            

            tbody += '<tr>' +
                     '<td align="center"><b>' + it.SALEORDER_ITEM + '</b></td>' +
                     '<td align="center"><span class="badge" style="background:#e0e7ff; color:#4338ca; font-size:11px;">' + it.PRODUCT_ID + '</span></td>' +
                     '<td align="center" style="font-weight:600;">' + it.ALLOY + '</td>' +
                     '<td align="center">' + it.TEMPER + '</td>' +
                     '<td align="center" style="font-size:11px; color:#64748b;">' + (it.SURFACE_GRADE || '-') + '/' + (it.METALLURGICAL_GRADE || '-') + '</td>' +
                     '<td align="right">' + parseFloat(it.THICKNESS).toFixed(3) + '</td>' +
                     '<td align="right">' + parseFloat(it.WIDTH).toFixed(3) + '</td>' +
                     '<td align="right">' + parseFloat(it.LENGTH).toFixed(3) + '</td>' +
                     '<td align="right" style="font-weight:600;">' + Number(it.CTM2_ORDERWEIGHT).toLocaleString() + '</td>' +
                     '<td align="right">' + Number(it.CTM2_ORDERPIECE || 0).toLocaleString() + '</td>' +
                     '<td align="right">' + parseFloat(it.CTM2_ACTUALPRICE).toFixed(4) + '</td>' +
                     '<td align="right" style="font-weight:700; color:#047857;">' + amt.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>' +
                     '<td align="center"><span class="badge" style="background:#dbeafe; color:#1d4ed8; font-size:10px;">' + (it.CTM2_STATUS || 'OP') + '</span></td>' +
                     '<td align="center">' +
                         '<button type="button" class="btn btn-xs btn-default" onclick="edit_item(' + idx + ')">✏️</button> ' +
                         '<button type="button" class="btn btn-xs btn-danger" onclick="delete_item(' + idx + ')">🗑️</button>' +
                     '</td>' +
                     '</tr>';
        });
    } else {
        tbody = '<tr><td colspan="14" align="center" style="color:#94a3b8; padding:20px;">No items added yet. Click "+ Add Item" to begin.</td></tr>';
    }

    document.getElementById("entry_item_body").innerHTML = tbody;
    document.getElementById("h_total_amount").value = total_amount.toLocaleString(undefined, {minimumFractionDigits: 2});
    calculate_header_total();
}

function calculate_header_total() {
    var raw_total_amount = parseFloat(document.getElementById("h_total_amount").value.replace(/,/g, '')) || 0;
    var freight = parseFloat(document.getElementById("h_freight").value) || 0;
    var insur   = parseFloat(document.getElementById("h_insurance").value) || 0;
    
    var grand = raw_total_amount + freight + insur;
    document.getElementById("h_grand_total").value = grand.toLocaleString(undefined, {minimumFractionDigits: 2});
}

function sync_metric_to_dim2() {
    var uom = document.getElementById("i_ct2u_uom_dim").value;
    var t = parseFloat(document.getElementById("i_thickness").value) || 0;
    var w = parseFloat(document.getElementById("i_width").value) || 0;
    var l = parseFloat(document.getElementById("i_length").value) || 0;

    if (uom === 'inch') {
        document.getElementById("i_ct2u_thk").value = (t / 25.4).toFixed(3);
        document.getElementById("i_ct2u_wd").value  = (w / 25.4).toFixed(3);
        document.getElementById("i_ct2u_len").value = (l / 25.4).toFixed(3);
    } else {
        document.getElementById("i_ct2u_thk").value = t.toFixed(3);
        document.getElementById("i_ct2u_wd").value  = w.toFixed(3);
        document.getElementById("i_ct2u_len").value = l.toFixed(3);
    }
}

function sync_dim2_to_metric() {
    var uom = document.getElementById("i_ct2u_uom_dim").value;
    var t2 = parseFloat(document.getElementById("i_ct2u_thk").value) || 0;
    var w2 = parseFloat(document.getElementById("i_ct2u_wd").value) || 0;
    var l2 = parseFloat(document.getElementById("i_ct2u_len").value) || 0;

    if (uom === 'inch') {
        document.getElementById("i_thickness").value = (t2 * 25.4).toFixed(3);
        document.getElementById("i_width").value     = (w2 * 25.4).toFixed(3);
        document.getElementById("i_length").value    = (l2 * 25.4).toFixed(3);
    } else {
        document.getElementById("i_thickness").value = t2.toFixed(3);
        document.getElementById("i_width").value     = w2.toFixed(3);
        document.getElementById("i_length").value    = l2.toFixed(3);
    }
    calculate_item_weight();
    auto_gen_desc();
}

function sync_wt_to_wt2() {
    var uom = document.getElementById("i_ct2u_uom_wt").value;
    var wt  = parseFloat(document.getElementById("i_weight").value) || 0;
    var pcs = parseInt(document.getElementById("i_pieces").value) || 0;

    if (uom === 'lbs.') {
        document.getElementById("i_ct2u_order_wt").value = Math.round(wt * 2.2);
    } else {
        document.getElementById("i_ct2u_order_wt").value = Math.round(wt);
    }
    document.getElementById("i_ct2u_order_pc").value = pcs;
}

function sync_wt2_to_wt() {
    var uom = document.getElementById("i_ct2u_uom_wt").value;
    var wt2 = parseFloat(document.getElementById("i_ct2u_order_wt").value) || 0;

    if (uom === 'lbs.') {
        document.getElementById("i_weight").value = Math.round(wt2 / 2.2);
    } else {
        document.getElementById("i_weight").value = Math.round(wt2);
    }
    calculate_item_pcs_from_wt();
}

function open_item_modal() {
    document.getElementById("edit_index").value = "-1";
    document.getElementById("item_modal_action_title").innerText = "Add";

    var next_no = (orderItems.length + 1).toString().padStart(3, '0');
    document.getElementById("i_item_no").value = next_no;
    document.getElementById("i_status").value = "OP";
    document.getElementById("i_poitem").value = next_no;
    document.getElementById("i_cust_part").value = "";
    document.getElementById("i_price_period").value = "";
    currentAreaSize = 0;

    document.getElementById("i_pono").value = document.getElementById("h_po_no").value;
    document.getElementById("i_cust_order").value = document.getElementById("h_cust_order").value;
    document.getElementById("i_cust_so").value = document.getElementById("h_cust_so").value;
    document.getElementById("i_endcust_name").value = document.getElementById("h_endcust_name").value;
    document.getElementById("i_endcust_po").value = document.getElementById("h_endcust_po").value;
    document.getElementById("i_podate").value = document.getElementById("h_so_date").value;
    document.getElementById("i_req_date").value = document.getElementById("h_ship_date").value;
    document.getElementById("i_conf_date").value = document.getElementById("h_ship_date").value;

    document.getElementById("i_lmetype").value = document.getElementById("h_lmetype").value;
    document.getElementById("i_lmedate").value = document.getElementById("h_lmedate").value;
    document.getElementById("i_orderlme").value = document.getElementById("h_orderlme").value;
    document.getElementById("i_mjpdate").value = document.getElementById("h_mjpdate").value;
    document.getElementById("i_mjpprice").value = document.getElementById("h_mjpprice").value;
    document.getElementById("i_stdv").value = document.getElementById("h_stdv").value;
    document.getElementById("i_factor").value = document.getElementById("h_factor").value;
    document.getElementById("i_stdxfactor").value = document.getElementById("h_stdxfactor").value;

    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    var is_mil = (cid.substring(0, 3) === 'MIL');
    document.getElementById("i_lmetype").disabled = is_mil;
    document.getElementById("i_lmedate").disabled = is_mil;
    document.getElementById("i_orderlme").disabled = is_mil;
    document.getElementById("i_mjpdate").disabled = is_mil;
    document.getElementById("i_mjpprice").disabled = is_mil;

    $('#itemEditModal').modal('show');
    on_change_product(true);
    sync_metric_to_dim2();
    sync_wt_to_wt2();
    update_s1_calculated_totals();
    on_change_item_podate();
}

function edit_item(idx) {
    if (!orderItems[idx]) return;
    var it = orderItems[idx];

    document.getElementById("edit_index").value = idx;
    document.getElementById("item_modal_action_title").innerText = "Edit";

    document.getElementById("i_item_no").value = it.SALEORDER_ITEM;
    document.getElementById("i_status").value = it.CTM2_STATUS || 'OP';
    document.getElementById("i_mat_in").value = it.MATERIAL_IN || 'BOI-MAT1';
    document.getElementById("i_pono").value = it.CTM2_PONO || document.getElementById("h_po_no").value;
    document.getElementById("i_poitem").value = it.CTM2_POITEM || it.SALEORDER_ITEM;

    document.getElementById("i_cust_order").value = it.CTM2_CUSTOMERORDER || '';
    document.getElementById("i_cust_so").value = it.CTM2_CUSTOMERSALEORDER || '';
    document.getElementById("i_endcust_name").value = it.CTM2_ENDCUSTOMERNAME || '';
    document.getElementById("i_endcust_po").value = it.CTM2_ENDCUSTOMERPO || '';

    if (it.CTM2_PODATE) document.getElementById("i_podate").value = it.CTM2_PODATE.substring(0, 10);
    document.getElementById("i_price_period").value = (it.PRICE_PERIOD && it.PRICE_PERIOD !== 'NONE') ? it.PRICE_PERIOD : '';
    if (it.REQUEST_DATE) document.getElementById("i_req_date").value = it.REQUEST_DATE.substring(0, 10);
    if (it.CONFIRM_DATE) document.getElementById("i_conf_date").value = it.CONFIRM_DATE.substring(0, 10);
    document.getElementById("i_cust_part").value = it.CTM2_CUSTOMERPARTNO || '';
    document.getElementById("i_coil_from").value = it.COIL_FROM || 'MAT';
    document.getElementById("i_full_coil").value = it.FULL_COIL || 'F';

    var targetVal = (it.PRODUCT_ID === 'NC' && it.PRODUCT_MODEL) ? (it.PRODUCT_ID + '|' + it.PRODUCT_MODEL) : it.PRODUCT_ID;
    var prodSelect = document.getElementById("i_product");
    for (var i = 0; i < prodSelect.options.length; i++) {
        if (prodSelect.options[i].value === targetVal || prodSelect.options[i].getAttribute('data-pid') === it.PRODUCT_ID) {
            prodSelect.selectedIndex = i;
            break;
        }
    }
    document.getElementById("i_prod_model").value = it.PRODUCT_MODEL || '';
    currentAreaSize = parseFloat(it.AREA_SIZE || 0);
    document.getElementById("lbl_area_size").innerText = currentAreaSize;

    document.getElementById("i_alloy").value = it.ALLOY;
    document.getElementById("i_temper").value = it.TEMPER;
    document.getElementById("i_sg").value = it.SURFACE_GRADE || 'SG3';
    document.getElementById("i_mg").value = it.METALLURGICAL_GRADE || 'MG3';
    document.getElementById("i_thickness").value = parseFloat(it.THICKNESS).toFixed(3);
    document.getElementById("i_width").value = parseFloat(it.WIDTH).toFixed(3);
    document.getElementById("i_length").value = parseFloat(it.LENGTH).toFixed(3);
    document.getElementById("i_t5").value = it.T5_TEMPERATURE || '';
    
    document.getElementById("i_ct2u_thk").value = parseFloat(it.CT2U_THICKNESS || 0).toFixed(3);
    document.getElementById("i_ct2u_wd").value  = parseFloat(it.CT2U_WIDTH || 0).toFixed(3);
    document.getElementById("i_ct2u_len").value = parseFloat(it.CT2U_LENGTH || 0).toFixed(3);
    document.getElementById("i_ct2u_uom_dim").value = it.CT2U_UOMDIMENSION || 'inch';

    document.getElementById("i_ct2u_order_wt").value = it.CT2U_ORDERWEIGHT || it.CTM2_ORDERWEIGHT;
    document.getElementById("i_ct2u_order_pc").value = it.CT2U_ORDERPIECE || it.CTM2_ORDERPIECE;
    document.getElementById("i_ct2u_uom_wt").value  = it.CT2U_UOMWEIGHT || 'kg.';

    document.getElementById("i_price").value = parseFloat(it.CTM2_ACTUALPRICE || 0).toFixed(4);
    document.getElementById("i_lmetype").value = it.CTM2_LMETYPE || document.getElementById("h_lmetype").value;
    if (it.CTM2_LMEDATE) document.getElementById("i_lmedate").value = it.CTM2_LMEDATE.substring(0, 10);
    document.getElementById("i_orderlme").value = Number(it.CTM2_ORDERLME || 0).toFixed(2);
    if (it.CTM2_MJPDATE) document.getElementById("i_mjpdate").value = it.CTM2_MJPDATE.substring(0, 10);
    document.getElementById("i_mjpprice").value = Number(it.CTM2_MJPPRICE || 0).toFixed(2);
    document.getElementById("i_stdv").value = Number(it.CTM2_ORDERSTDV || 0).toFixed(2);
    document.getElementById("i_factor").value = Number(it.CTM2_ORDERFACTOR || 0).toFixed(2);
    document.getElementById("i_stdxfactor").value = Number(it.CTM2_ORDERSTDXFACTOR || 0).toFixed(2);
    document.getElementById("i_amount").value = Number(it.AMOUNT || 0).toFixed(2);

    document.getElementById("i_wt_piece").value = parseFloat(it.WEIGHT_PIECE || 0).toFixed(4);
    document.getElementById("i_maxtol").value = it.CTM2_MAXTOLERANCE || 0;
    document.getElementById("i_mintol").value = it.CTM2_MINTOLERANCE || 0;
    document.getElementById("i_weight").value = it.CTM2_ORDERWEIGHT || 0;
    document.getElementById("i_pieces").value = it.CTM2_ORDERPIECE || 0;
    document.getElementById("i_reject_wt").value = it.CTM2_REJECTWEIGHT || 0;
    document.getElementById("i_reject_pc").value = it.CTM2_REJECTPIECE || 0;
    document.getElementById("i_replace_wt").value = it.CTM2_REPLACEWEIGHT || 0;
    document.getElementById("i_replace_pc").value = it.CTM2_REPLACEPIECE || 0;

    document.getElementById("i_desc").value = it.CTM2_DESCRIPTION || '';
    document.getElementById("i_remark").value = it.CTM2_REMARK || '';

    document.getElementById("i_special_label1").value = it.CTM2_SPECIALLABEL1 || '';
    document.getElementById("i_special_label2").value = it.CTM2_SPECIALLABEL2 || '';
    document.getElementById("i_special_label3").value = it.CTM2_SPECIALLABEL3 || '';
    document.getElementById("i_special_label4").value = it.CTM2_SPECIALLABEL4 || '';

    var cid = document.getElementById("h_customer").value.trim().toUpperCase();
    var is_mil = (cid.substring(0, 3) === 'MIL');
    document.getElementById("i_lmetype").disabled = is_mil;
    document.getElementById("i_lmedate").disabled = is_mil;
    document.getElementById("i_orderlme").disabled = is_mil;
    document.getElementById("i_mjpdate").disabled = is_mil;
    document.getElementById("i_mjpprice").disabled = is_mil;

    $('#itemEditModal').modal('show');
    on_change_product(false);
    update_s1_calculated_totals();
}

function delete_item(idx) {
    if (confirm("Are you sure you want to remove item #" + orderItems[idx].SALEORDER_ITEM + "?")) {
        orderItems.splice(idx, 1);
        orderItems.forEach(function(it, i) {
            it.SALEORDER_ITEM = (i + 1).toString().padStart(3, '0');
        });
        render_items_grid();
    }
}

function update_s1_calculated_totals() {
    var order_wt = parseFloat(document.getElementById("i_weight").value) || 0;
    var order_pc = parseInt(document.getElementById("i_pieces").value) || 0;
    var replace_wt = parseFloat(document.getElementById("i_replace_wt").value) || 0;
    var replace_pc = parseInt(document.getElementById("i_replace_pc").value) || 0;

    var maxtol = parseFloat(document.getElementById("i_maxtol").value) || 0;
    var mintol = parseFloat(document.getElementById("i_mintol").value) || 0;

    var total_pc = order_pc + replace_pc;
    var max_pc = Math.round(total_pc + (total_pc * maxtol / 100));
    var min_pc = Math.round(total_pc - (total_pc * mintol / 100));

    var total_wt = Math.round(order_wt + replace_wt);
    var max_wt = Math.round(total_wt + (total_wt * maxtol / 100));
    var min_wt = Math.round(total_wt - (total_wt * mintol / 100));

    document.getElementById("lbl_max_wt").innerText = max_wt.toLocaleString();
    document.getElementById("lbl_max_pc").innerText = max_pc.toLocaleString();

    document.getElementById("lbl_tot_wt").innerText = total_wt.toLocaleString();
    document.getElementById("lbl_tot_pc").innerText = total_pc.toLocaleString();

    document.getElementById("lbl_min_wt").innerText = min_wt.toLocaleString();
    document.getElementById("lbl_min_pc").innerText = min_pc.toLocaleString();
}

function confirm_item_modal() {
    //var idx = parseInt(document.getElementById("edit_index").value);
    //var wt  = parseFloat(document.getElementById("i_weight").value) || 0;
    //var prc = parseFloat(document.getElementById("i_price").value) || 0;
    //var amt = wt * prc;
    var idx     = parseInt(document.getElementById("edit_index").value);
    var wt      = parseFloat(document.getElementById("i_weight").value) || 0;
    var ct2u_wt = parseFloat(document.getElementById("i_ct2u_order_wt").value) || wt;
    var prc     = parseFloat(document.getElementById("i_price").value) || 0;
    var amt     = Math.round((ct2u_wt * prc) * 100) / 100; // ปัดเศษ 2 ตำแหน่งตาม S1

    var prodSelect = document.getElementById("i_product");
    var selectedOpt = prodSelect.options[prodSelect.selectedIndex];
    var pid = selectedOpt ? selectedOpt.getAttribute('data-pid') : prodSelect.value;

    var itemData = {
        SALEORDER_ITEM: document.getElementById("i_item_no").value,
        CTM2_STATUS: document.getElementById("i_status").value,
        MATERIAL_IN: document.getElementById("i_mat_in").value,
        CTM2_PONO: document.getElementById("i_pono").value,
        CTM2_POITEM: document.getElementById("i_poitem").value,
        CTM2_CUSTOMERORDER: document.getElementById("i_cust_order").value,
        CTM2_CUSTOMERSALEORDER: document.getElementById("i_cust_so").value,
        CTM2_ENDCUSTOMERNAME: document.getElementById("i_endcust_name").value,
        CTM2_ENDCUSTOMERPO: document.getElementById("i_endcust_po").value,
        CTM2_PODATE: document.getElementById("i_podate").value,
        PRICE_PERIOD: document.getElementById("i_price_period").value,
        REQUEST_DATE: document.getElementById("i_req_date").value,
        CONFIRM_DATE: document.getElementById("i_conf_date").value,
        CTM2_CUSTOMERPARTNO: document.getElementById("i_cust_part").value,
        COIL_FROM: document.getElementById("i_coil_from").value,
        FULL_COIL: document.getElementById("i_full_coil").value,

        PRODUCT_ID: pid,
        PRODUCT_MODEL: document.getElementById("i_prod_model").value,
        AREA_SIZE: currentAreaSize,
        ALLOY: document.getElementById("i_alloy").value,
        TEMPER: document.getElementById("i_temper").value,
        SURFACE_GRADE: document.getElementById("i_sg").value,
        METALLURGICAL_GRADE: document.getElementById("i_mg").value,
        THICKNESS: parseFloat(document.getElementById("i_thickness").value) || 0,
        WIDTH: parseFloat(document.getElementById("i_width").value) || 0,
        LENGTH: parseFloat(document.getElementById("i_length").value) || 0,
        T5_TEMPERATURE: document.getElementById("i_t5").value,
        CTM2_UOMDIMENSION: 'mm.', 

        CT2U_THICKNESS: parseFloat(document.getElementById("i_ct2u_thk").value) || 0,
        CT2U_WIDTH: parseFloat(document.getElementById("i_ct2u_wd").value) || 0,
        CT2U_LENGTH: parseFloat(document.getElementById("i_ct2u_len").value) || 0,
        CT2U_UOMDIMENSION: document.getElementById("i_ct2u_uom_dim").value,

        CT2U_ORDERWEIGHT: parseFloat(document.getElementById("i_ct2u_order_wt").value) || wt,
        CT2U_ORDERPIECE: parseInt(document.getElementById("i_ct2u_order_pc").value) || 0,
        CT2U_UOMWEIGHT: document.getElementById("i_ct2u_uom_wt").value,

        CTM2_ACTUALPRICE: prc,
        CTM2_LMETYPE: document.getElementById("i_lmetype").value,
        CTM2_LMEDATE: document.getElementById("i_lmedate").value,
        CTM2_ORDERLME: parseFloat(document.getElementById("i_orderlme").value) || 0,
        CTM2_MJPDATE: document.getElementById("i_mjpdate").value,
        CTM2_MJPPRICE: parseFloat(document.getElementById("i_mjpprice").value) || 0,
        CTM2_ORDERSTDV: parseFloat(document.getElementById("i_stdv").value) || 0,
        CTM2_ORDERFACTOR: parseFloat(document.getElementById("i_factor").value) || 0,
        CTM2_ORDERSTDXFACTOR: parseFloat(document.getElementById("i_stdxfactor").value) || 0,
        AMOUNT: amt,

        WEIGHT_PIECE: parseFloat(document.getElementById("i_wt_piece").value) || 0,
        CTM2_MAXTOLERANCE: parseInt(document.getElementById("i_maxtol").value) || 0,
        CTM2_MINTOLERANCE: parseInt(document.getElementById("i_mintol").value) || 0,
        CTM2_ORDERWEIGHT: wt,
        CTM2_ORDERPIECE: parseInt(document.getElementById("i_pieces").value) || 0,
        CTM2_REJECTWEIGHT: parseFloat(document.getElementById("i_reject_wt").value) || 0,
        CTM2_REJECTPIECE: parseInt(document.getElementById("i_reject_pc").value) || 0,
        CTM2_REPLACEWEIGHT: parseFloat(document.getElementById("i_replace_wt").value) || 0,
        CTM2_REPLACEPIECE: parseInt(document.getElementById("i_replace_pc").value) || 0,
        CTM2_UOMWEIGHT: 'kg.',

        CTM2_DESCRIPTION: document.getElementById("i_desc").value,
        CTM2_REMARK: document.getElementById("i_remark").value,
        CTM2_SPECIALLABEL1: document.getElementById("i_special_label1").value,
        CTM2_SPECIALLABEL2: document.getElementById("i_special_label2").value,
        CTM2_SPECIALLABEL3: document.getElementById("i_special_label3").value,
        CTM2_SPECIALLABEL4: document.getElementById("i_special_label4").value
    };

    if (idx === -1) {
        orderItems.push(itemData);
    } else {
        orderItems[idx] = itemData;
    }

    $('#itemEditModal').modal('hide');
    render_items_grid();
}

function calculate_item_weight() {
    var prodSelect = document.getElementById("i_product");
    var selectedOpt = prodSelect.options[prodSelect.selectedIndex];
    var pid = selectedOpt ? selectedOpt.getAttribute('data-pid') : prodSelect.value;

    var t = parseFloat(document.getElementById("i_thickness").value) || 0;
    var w = parseFloat(document.getElementById("i_width").value) || 0;
    var l = parseFloat(document.getElementById("i_length").value) || 0;
    var wp = 0;

    if (pid === 'CC' && t > 0 && w > 0) {
        wp = (Math.PI / 4) * Math.pow(w, 2) * t * 0.00000271;
    } else if (pid === 'SH' && t > 0 && w > 0 && l > 0) {
        wp = w * l * t * 0.00000271;
    } else if (pid === 'NC' && t > 0) {
        if (currentAreaSize > 0) {
            wp = currentAreaSize * t * 0.00000271;
        } else if (w > 0 && l > 0) {
            wp = w * l * t * 0.00000271;
        }
    }

    document.getElementById("i_wt_piece").value = wp.toFixed(4);
    calculate_item_pcs_from_wt();
}

function calculate_item_pcs_from_wt() {
    var wt = parseFloat(document.getElementById("i_weight").value) || 0;
    var wp = parseFloat(document.getElementById("i_wt_piece").value) || 0;
    var cust = document.getElementById("h_customer").value;

    if (cust === 'MIL-HA' && wt > 0) {
        if (wt % 1200 === 0) {
            document.getElementById("i_full_coil").value = 'F';
        } else {
            document.getElementById("i_full_coil").value = 'N';
        }
    }

    if (wp > 0) {
        document.getElementById("i_pieces").value = Math.round(wt / wp);
    }
    sync_wt_to_wt2();
    calculate_item_amount();
    update_s1_calculated_totals();
}

function calculate_item_qty_from_pcs() {
    var pcs = parseInt(document.getElementById("i_pieces").value) || 0;
    var wp  = parseFloat(document.getElementById("i_wt_piece").value) || 0;
    if (wp > 0) {
        document.getElementById("i_weight").value = Math.round(pcs * wp);
    }
    sync_wt_to_wt2();
    calculate_item_amount();
    update_s1_calculated_totals();
}

// 1. แก้ไขฟังก์ชัน calculate_item_amount()
function calculate_item_amount() {
    var ct2u_wt = parseFloat(document.getElementById("i_ct2u_order_wt").value) || 0;
    var prc     = parseFloat(document.getElementById("i_price").value) || 0;
    document.getElementById("i_amount").value = (ct2u_wt * prc).toFixed(2);
}

function auto_gen_desc() {
    var prodSelect = document.getElementById("i_product");
    var selectedOpt = prodSelect.options[prodSelect.selectedIndex];
    var pid = selectedOpt ? selectedOpt.getAttribute('data-pid') : prodSelect.value;

    var alloy = document.getElementById("i_alloy").value;
    var temper = document.getElementById("i_temper").value;
    var mat_in = document.getElementById("i_mat_in").value;
    var uom_dim = document.getElementById("i_ct2u_uom_dim").value;
    
    var t = parseFloat(document.getElementById("i_ct2u_thk").value) || 0;
    var w = parseFloat(document.getElementById("i_ct2u_wd").value) || 0;
    var l = parseFloat(document.getElementById("i_ct2u_len").value) || 0;

    var dc_tag = (mat_in === 'BOI-IMP1') ? ' DC ALLOY' : '';
    var name = 'ALUMINIUM ';
    if (pid === 'CC') name += 'CIRCLES' + dc_tag;
    else if (pid === 'SH' || pid === 'NC') name += 'SHEETS' + dc_tag;
    else name += 'COILS' + dc_tag;

    var dim = t.toFixed(3) + ' x ' + w.toFixed(3) + (l > 0 ? ' x ' + l.toFixed(3) : '') + ' ' + uom_dim;
    document.getElementById("i_desc").value = name + ' ' + alloy + '-' + temper + ' ' + dim;
}

// =========================================================================
// คำนวณราคาจาก PRICMSTR1 - 12 และอัปเดตค่า Exchange Rate, LME, MJP ตาม S1
// =========================================================================
function calc_price_from_backend(show_alert) {
    var period = document.getElementById("i_price_period").value;
    var cid    = document.getElementById("h_customer").value;
    var pono   = document.getElementById("i_pono").value || document.getElementById("h_po_no").value;

    if (!period || !cid) {
        if (show_alert) alert("Please specify Customer and Price Period first.");
        return;
    }

    if (!pono) {
        if (show_alert) alert("Please enter Purchase Order No. (PO No) before calculating price.");
        return;
    }

    var prodSelect = document.getElementById("i_product");
    var selectedOpt = prodSelect.options[prodSelect.selectedIndex];
    var pid = selectedOpt ? selectedOpt.getAttribute('data-pid') : prodSelect.value;

    var params = {
        action: 'calculate_price',
        price_period: period,
        coil_from: document.getElementById("i_coil_from").value,
        cust_id: cid,
        sono: document.getElementById("h_so_no").value,
        pono: pono,
        product_id: pid,
        alloy: document.getElementById("i_alloy").value,
        temper: document.getElementById("i_temper").value,
        sg: document.getElementById("i_sg").value,
        mg: document.getElementById("i_mg").value,
        thickness: document.getElementById("i_thickness").value,
        width: document.getElementById("i_width").value,
        length: document.getElementById("i_length").value,
        weight: document.getElementById("i_weight").value,
        min_tol: document.getElementById("i_mintol").value || 0,
        full_coil: document.getElementById("i_full_coil").value
    };

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: params,
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("i_price").value = parseFloat(res.actual_price).toFixed(4);
                
                // อัปเดต Exchange Rate, Order LME (พร้อมล็อกช่อง) และ MJP Price ตามเงื่อนไข S1
                if (res.exchange_rate !== undefined) {
                    document.getElementById("h_exrate").value = parseFloat(res.exchange_rate).toFixed(5);
                }
                if (res.order_lme !== undefined) {
                    document.getElementById("i_orderlme").value = parseFloat(res.order_lme).toFixed(2);
                    document.getElementById("i_orderlme").disabled = true;
                }
                if (res.ingot_premium !== undefined) {
                    document.getElementById("i_mjpprice").value = parseFloat(res.ingot_premium).toFixed(2);
                }

                calculate_item_amount();
            } else {
                if (show_alert) alert("Pricing notice: " + res.message);
            }
        },
        error: function() {
            if (show_alert) alert("Pricing calculation service error.");
        }
    });
}

function delete_current_so() {
    var sono = document.getElementById("h_so_no").value;
    if (!sono) return;

    if (!confirm("Are you sure you want to delete Sales Order: " + sono + " and all associated records?")) {
        return;
    }

    $.ajax({
        url: 'model/so_service_mats.php?action=delete_sales_order',
        type: 'POST',
        //data: { sono: sono, force_delete: 1 },
        data: { sono: sono }, // เอา force_delete: 1 ออกเพื่อให้ Backend ตรวจสอบความปลอดภัย
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                alert(res.message);
                window.location.href = 'saleorder_list_mats.php?func=<?php echo $folder_func; ?>';
            } else {
                alert("Cannot delete: " + res.message);
            }
        },
        error: function() {
            alert("Error connecting to server.");
        }
    });
}

// 2. เพิ่มตัวแปรป้องกันการกดซ้ำไว้ด้านบนสุดของ <script>
var isSaving = false;

function save_entire_order() {
    // ถ้ากำลังบันทึกอยู่ ให้หยุดทำงานทันที (ป้องกันการกดรัว/ดับเบิลคลิก)
    if (isSaving) {
        return;
    }

    var cust = document.getElementById("h_customer").value;
    var pono = document.getElementById("h_po_no").value;
    var cat  = document.getElementById("h_order_cat").value;

    if (!cust || !pono) {
        alert("Please fill in Customer and PO Number.");
        return;
    }

    <?php if (!$is_edit_mode): ?>
    if (orderItems.length === 0) {
        alert("Please add at least 1 item before saving a new Sales Order.");
        return;
    }
    <?php else: ?>
    if (orderItems.length === 0) {
        if (!confirm("All items have been removed. Saving will empty this Sales Order and allow it to be deleted. Do you want to proceed?")) {
            return;
        }
    }
    <?php endif; ?>

    if (cat.substring(0, 4) === 'OVMI' && cust.substring(0, 3) !== 'MIL') {
        alert("Order Category: OVMI must be paired with customer starting with MIL");
        return;
    }

    var prefix = '';
    var prefixElem = document.getElementById("h_so_prefix");
    if (prefixElem) {
        prefix = prefixElem.value.trim().toUpperCase();
        if (prefix.length !== 2) {
            alert("Please enter exactly 2 characters for Sales Order Prefix (e.g. SO, TT, RC).");
            prefixElem.focus();
            return;
        }
    }

    // ล็อกปุ่มและเปลี่ยนสถานะก่อนส่ง AJAX
    isSaving = true;
    var btnSave = document.getElementById("btn_save_so");
    var originalBtnText = btnSave ? btnSave.innerHTML : '';
    if (btnSave) {
        btnSave.disabled = true;
        btnSave.style.opacity = "0.6";
        btnSave.innerHTML = "⏳ Saving...";
    }

    var raw_total_amount = parseFloat(document.getElementById("h_total_amount").value.replace(/,/g, '')) || 0;
    var grand = parseFloat(document.getElementById("h_grand_total").value.replace(/,/g, '')) || 0;

    var payload = {
        header: {
            SALEORDER_NO: document.getElementById("h_so_no").value,
            SO_PREFIX: prefix,
            ORDER_CATEGORY: cat,
            SALEORDER_DATE: document.getElementById("h_so_date").value,
            INTENDSHIP_DATE: document.getElementById("h_ship_date").value,
            CSTMSPPL_ID: cust,
            SUPPLIER_MATCODE: document.getElementById("h_supplier_mat").value,
            CTM1_PONO: pono,
            CUSTOMS_CODE: document.getElementById("h_customs_code").value,
            CTM1_CUSTOMERORDER: document.getElementById("h_cust_order").value,
            CTM1_CUSTOMERSALEORDER: document.getElementById("h_cust_so").value,
            CTM1_ENDCUSTOMERNAME: document.getElementById("h_endcust_name").value,
            CTM1_ENDCUSTOMERPO: document.getElementById("h_endcust_po").value,
            CURRENCY_ID: document.getElementById("h_currency").value,
            EXCHANGE_RATE: parseFloat(document.getElementById("h_exrate").value) || 1,
            PAYMENT_ID: document.getElementById("h_payment").value,
            SHIPMENT_ID: document.getElementById("h_shipment").value,
            CTM1_SHIPPEDBY: document.getElementById("h_shippedby").value,
            CTM1_TOLERANCE: document.getElementById("h_tolerance").value,
            CTM1_PACKING: document.getElementById("h_packing").value,
            CTM1_PORTLOADING: document.getElementById("h_portload").value,
            CTM1_PORTDISCHARGE: document.getElementById("h_portdis").value,
            CTM1_PLACEDESTINATION: document.getElementById("h_placedest").value,
            CTM1_COUNTRYDESTINATION: document.getElementById("h_countrydest").value,
            CTM1_TAXPERCENT: parseInt(document.getElementById("h_tax").value) || 0,
            
            CTM1_REMARK1: document.getElementById("h_remark1").value,
            CTM1_REMARK2: document.getElementById("h_remark2").value,
            CTM1_REMARK3: document.getElementById("h_remark3").value,

            CTM1_WHOLEWEIGHT: document.getElementById("h_chk_wholeweight").checked ? 1 : 0,
            CTM1_MARKSKID: document.getElementById("h_chk_markskid").checked ? 1 : 0,
            CTM1_SERIALCODE: document.getElementById("h_chk_serialcode").checked ? 1 : 0,
            CTM1_SCRAPWIRE: document.getElementById("h_chk_scrapwire").checked ? 1 : 0,

            CTM1_LMETYPE: document.getElementById("h_lmetype").value,
            CTM1_LMEDATE: document.getElementById("h_lmedate").value,
            CTM1_ORDERLME: parseFloat(document.getElementById("h_orderlme").value) || 0,
            CTM1_MJPDATE: document.getElementById("h_mjpdate").value,
            CTM1_MJPPRICE: parseFloat(document.getElementById("h_mjpprice").value) || 0,
            CTM1_ORDERSTDV: cur_stdv,
            CTM1_ORDERFACTOR: cur_factor,
            CTM1_ORDERSTDXFACTOR: cur_stdxfactor,

            CTM1_TOTALAMOUNT: raw_total_amount,
            CTM1_TRANSPORTATION: parseFloat(document.getElementById("h_transport").value) || 0,
            CTM1_FOBTOTALAMOUNT: parseFloat(document.getElementById("h_fob").value) || 0,
            CTM1_OVERSEAFREIGHT: parseFloat(document.getElementById("h_freight").value) || 0,
            CTM1_INSURANCE: parseFloat(document.getElementById("h_insurance").value) || 0,
            CTM1_GRANDTOTALAMOUNT: grand
        },
        items: orderItems
    };

    $.ajax({
        url: 'model/so_service_mats.php?action=save_sales_order',
        type: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json; charset=utf-8',
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                // สำเร็จแล้วไม่ต้องปลดล็อกปุ่ม รอ Redirect ไปหน้า View ทันที
                alert("Sales Order saved successfully! Number: " + res.so_no);
                window.location.href = 'saleorder_view_mats.php?func=<?php echo $folder_func; ?>&sono=' + encodeURIComponent(res.so_no);
            } else {
                alert("Save failed: " + res.message);
                resetSaveButton(btnSave, originalBtnText);
            }
        },
        error: function() {
            alert("Server connection error during save.");
            resetSaveButton(btnSave, originalBtnText);
        }
    });
}

function resetSaveButton(btn, text) {
    isSaving = false;
    if (btn) {
        btn.disabled = false;
        btn.style.opacity = "1";
        btn.innerHTML = text;
    }
}
</script>
</html>