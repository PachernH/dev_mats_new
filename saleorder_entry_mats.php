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
$products     = $conn->query("SELECT PRODUCT_ID, PRODUCT_MODEL FROM PRODMSTR1 GROUP BY PRODUCT_ID, PRODUCT_MODEL ORDER BY PRODUCT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
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

        /* Sub-Card Blocks inside Header */
        .header-subcard {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 14px 16px; height: 100%; min-height: 285px; display: flex; flex-direction: column; justify-content: space-between;
        }
        .subcard-heading {
            font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 10px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;
        }

        .form-group-custom { margin-bottom: 8px; }
        .form-group-custom label {
            font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 3px;
            text-transform: uppercase; display: block;
        }
        .form-control-sm {
            height: 32px !important; padding: 4px 8px !important; font-size: 12px !important;
            border-radius: 6px !important; border: 1px solid #cbd5e1 !important; color: #1e293b; background: #ffffff;
        }
        .form-control-sm:focus { border-color: #2563eb !important; outline: none; box-shadow: 0 0 0 1px #2563eb; }

        .uom-group-box {
            background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px;
            padding: 10px 12px; margin-bottom: 10px;
        }
        .uom-group-title {
            font-size: 11px; font-weight: 700; color: #b45309; text-transform: uppercase; margin-bottom: 6px;
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
                    <button type="button" class="btn-top-save" onclick="save_entire_order()">💾 Save Sales Order</button>
                </div>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 15px;">
            <form id="soForm" onsubmit="return false;">
                
<!-- SECTION 1: ORDER HEADER (ปรับ Layout ใหม่แบบ 2 แถว กว้าง โปร่ง สบายตา) -->
                <div class="entry-card">
                    <div class="section-title">
                        <span>📋 Order Header Information</span>
                        <span style="font-size: 11px; color:#64748b; font-weight:normal;">* Fields are structured with clear spacing</span>
                    </div>

                    <!-- แถวที่ 1: ข้อมูลลูกค้าและออร์เดอร์ (ซ้าย) คู่กับ กำหนดเวลาและสกุลเงิน (ขวา) -->
                    <div class="row">
                        <!-- Card 1: Basic & Customer -->
                        <div class="col-md-6" style="margin-bottom: 16px;">
                            <div class="header-subcard" style="min-height: auto;">
                                <div>
                                    <div class="subcard-heading">🏢 Customer & Order Info</div>
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
                                <div class="form-group-custom" style="margin-bottom: 0;">
                                    <label>Purchase Order (PO No)</label>
                                    <input type="text" class="form-control form-control-sm" id="h_po_no" placeholder="Enter PO Number..."/>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Date & Currency -->
                        <div class="col-md-6" style="margin-bottom: 16px;">
                            <div class="header-subcard" style="min-height: auto;">
                                <div>
                                    <div class="subcard-heading">📅 Schedule & Currency</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Order Date</label>
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
                                        <div class="col-md-8">
                                            <div class="form-group-custom">
                                                <label>Currency & Exchange Rate</label>
                                                <div style="display:flex; gap:6px;">
                                                    <select class="form-control form-control-sm" id="h_currency" style="width: 45%;">
                                                        <?php foreach ($currencies as $cur): ?>
                                                            <option value="<?php echo htmlspecialchars($cur['CURRENCY_ID']); ?>">
                                                                <?php echo htmlspecialchars($cur['CURRENCY_ID']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="number" step="0.00001" class="form-control form-control-sm" id="h_exrate" value="1.00000" style="width: 55%;"/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group-custom" style="margin-bottom: 0;">
                                                <label>Tax Percent (%)</label>
                                                <input type="number" class="form-control form-control-sm" id="h_tax" value="0"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- แถวที่ 2: การขนส่งและท่าเรือ (ซ้าย) คู่กับ โครงสร้างราคาและ LME Basis (ขวา) -->
                    <div class="row">
                        <!-- Card 3: Shipping & Logistics -->
                        <div class="col-md-6" style="margin-bottom: 12px;">
                            <div class="header-subcard" style="min-height: auto;">
                                <div>
                                    <div class="subcard-heading">🚢 Shipping & Logistics</div>
                                    <div class="row">
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
                                </div>
                                <div class="form-group-custom" style="margin-bottom: 0;">
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
                            </div>
                        </div>

                        <!-- Card 4: Pricing Basis & Totals -->
                        <div class="col-md-6" style="margin-bottom: 12px;">
                            <div class="header-subcard" style="background:#f0fdf4; border-color:#bbf7d0; min-height: auto;">
                                <div>
                                    <div class="subcard-heading" style="color:#166534; border-color:#86efac;">💰 Pricing Basis & Totals</div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>LME Type & Date</label>
                                                <div style="display:flex; gap:6px;">
                                                    <select class="form-control form-control-sm" id="h_lmetype" style="width: 52%;" onchange="on_change_lme()">
                                                        <option value="D-DAILY">D-DAILY</option>
                                                        <option value="B-WBP LME">B-WBP LME</option>
                                                        <option value="W-WBP">W-WBP</option>
                                                        <option value="M-MONTHLY">M-MONTHLY</option>
                                                        <option value="O-MANUALLY">O-MANUALLY</option>
                                                        <option value="D-DAILY CASH">D-DAILY CASH</option>
                                                        <option value="M-MONTHLY CASH">M-MONTHLY CASH</option>
                                                    </select>
                                                    <input type="date" class="form-control form-control-sm" id="h_lmedate" value="<?php echo date('Y-m-d'); ?>" style="width: 48%;" onchange="on_change_lme()"/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Order LME / MJP Price</label>
                                                <div style="display:flex; gap:6px;">
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="h_orderlme" placeholder="LME" value="0.00" style="width: 50%;"/>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="h_mjpprice" placeholder="MJP" value="0.00" style="width: 50%;"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- แถวแสดง Stdv / Factor / STDxFactor (เต็มกว้าง เห็นตัวเลขชัดเจน) -->
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
                                        <div class="col-md-6">
                                            <div class="form-group-custom">
                                                <label>Freight / Insurance</label>
                                                <div style="display:flex; gap:6px;">
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="h_freight" placeholder="Freight" value="0.00" style="width: 50%;" oninput="calculate_header_total()"/>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="h_insurance" placeholder="Insur." value="0.00" style="width: 50%;" oninput="calculate_header_total()"/>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group-custom" style="margin-bottom: 0;">
                                                <label>FOB / Grand Total</label>
                                                <div style="display:flex; gap:6px;">
                                                    <input type="text" class="form-control form-control-sm" id="h_fob" value="0.00" readonly style="background:#ffffff; width: 50%; font-weight:600;"/>
                                                    <input type="text" class="form-control form-control-sm" id="h_grand_total" value="0.00" readonly style="background:#dcfce7; font-weight:800; color:#15803d; width: 50%;"/>
                                                </div>
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
    <div class="modal-dialog modal-lg" style="width: 90%; max-width: 1300px;" role="document">
        <div class="modal-content" style="border-radius:10px; overflow:hidden;">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:12px 20px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-weight:700; color:#1e293b;">
                    <span id="item_modal_action_title">Add</span> Item Specification
                </h4>
            </div>
            
            <div class="modal-body" style="padding:18px 22px;">
                <input type="hidden" id="edit_index" value="-1"/>

                <div class="row">
                    <!-- Column 1 -->
                    <div class="col-md-3">
                        <div class="form-group-custom">
                            <label>Item No</label>
                            <input type="text" class="form-control form-control-sm" id="i_item_no" value="001" readonly style="background:#f1f5f9; font-weight:700;"/>
                        </div>
                        <div class="form-group-custom">
                            <label>Product ID (PRODMSTR1)</label>
                            <select class="form-control form-control-sm" id="i_product" onchange="on_change_product()">
                                <?php foreach ($products as $pr): ?>
                                    <option value="<?php echo htmlspecialchars($pr['PRODUCT_ID']); ?>">
                                        <?php echo htmlspecialchars($pr['PRODUCT_ID'] . ' : ' . $pr['PRODUCT_MODEL']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label>Alloy & Temper</label>
                            <div style="display:flex; gap:6px;">
                                <select class="form-control form-control-sm" id="i_alloy" style="width:55%;" onchange="auto_gen_desc()">
                                    <?php foreach ($alloys as $a): ?>
                                        <option value="<?php echo htmlspecialchars($a['ALLOY']); ?>"><?php echo htmlspecialchars($a['ALLOY']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-control form-control-sm" id="i_temper" style="width:45%;" onchange="auto_gen_desc()">
                                    <?php foreach ($tempers as $t): ?>
                                        <option value="<?php echo htmlspecialchars($t['TEMPER']); ?>"><?php echo htmlspecialchars($t['TEMPER']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group-custom">
                            <label>Surface / Metallurgical Grade</label>
                            <div style="display:flex; gap:6px;">
                                <select class="form-control form-control-sm" id="i_sg" style="width:50%;">
                                    <?php foreach ($sgs as $sg): ?>
                                        <option value="<?php echo htmlspecialchars($sg['SURFACE_GRADE']); ?>"><?php echo htmlspecialchars($sg['SURFACE_GRADE']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="form-control form-control-sm" id="i_mg" style="width:50%;">
                                    <?php foreach ($mgs as $mg): ?>
                                        <option value="<?php echo htmlspecialchars($mg['METALLURGICAL_GRADE']); ?>"><?php echo htmlspecialchars($mg['METALLURGICAL_GRADE']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group-custom">
                            <label>Full Coil (FULL_COIL)</label>
                            <select class="form-control form-control-sm" id="i_full_coil">
                                <option value="F">F: FULL COIL</option>
                                <option value="N">N: NOT FULL COIL</option>
                            </select>
                        </div>
                    </div>

                    <!-- Column 2 -->
                    <div class="col-md-3">
                        <div class="form-group-custom">
                            <label>Thickness (mm)</label>
                            <input type="number" step="0.001" class="form-control form-control-sm" id="i_thickness" value="2.800" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();"/>
                        </div>
                        <div class="form-group-custom">
                            <label>Width / Diameter (mm)</label>
                            <input type="number" step="0.001" class="form-control form-control-sm" id="i_width" value="290.000" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();"/>
                        </div>
                        <div class="form-group-custom">
                            <label>Length (mm)</label>
                            <input type="number" step="0.001" class="form-control form-control-sm" id="i_length" value="0.000" oninput="sync_metric_to_dim2(); calculate_item_weight(); auto_gen_desc();"/>
                        </div>

                        <div class="uom-group-box">
                            <div class="uom-group-title">Different UOM of Dimension</div>
                            <div style="display:flex; gap:4px; margin-bottom:4px;">
                                <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_thk" placeholder="Thk (inch)" oninput="sync_dim2_to_metric()"/>
                                <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_wd" placeholder="Width (inch)" oninput="sync_dim2_to_metric()"/>
                            </div>
                            <div style="display:flex; gap:4px;">
                                <input type="number" step="0.001" class="form-control form-control-sm" id="i_ct2u_len" placeholder="Len (inch)" style="width:65%;" oninput="sync_dim2_to_metric()"/>
                                <select class="form-control form-control-sm" id="i_ct2u_uom_dim" style="width:35%;" onchange="sync_metric_to_dim2()">
                                    <option value="inch">inch</option>
                                    <option value="mm.">mm.</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3 -->
                    <div class="col-md-3">
                        <div class="form-group-custom">
                            <label>Weight Per Piece (kg)</label>
                            <input type="number" step="0.0001" class="form-control form-control-sm" id="i_wt_piece" value="0.5014" oninput="calculate_item_qty_from_pcs()"/>
                        </div>
                        <div class="form-group-custom">
                            <label>Order Weight (kg)</label>
                            <input type="number" step="1" class="form-control form-control-sm" id="i_weight" value="1000" oninput="sync_wt_to_wt2(); calculate_item_pcs_from_wt();"/>
                        </div>
                        <div class="form-group-custom">
                            <label>Order Pieces (pcs)</label>
                            <input type="number" step="1" class="form-control form-control-sm" id="i_pieces" value="1994" oninput="sync_wt_to_wt2(); calculate_item_qty_from_pcs();"/>
                        </div>

                        <div class="uom-group-box">
                            <div class="uom-group-title">Different UOM of Weight</div>
                            <div style="display:flex; gap:4px; margin-bottom:4px;">
                                <input type="number" step="1" class="form-control form-control-sm" id="i_ct2u_order_wt" placeholder="Order Wt" oninput="sync_wt2_to_wt()"/>
                                <input type="number" step="1" class="form-control form-control-sm" id="i_ct2u_order_pc" placeholder="Order Pc" readonly style="background:#f8fafc;"/>
                            </div>
                            <select class="form-control form-control-sm" id="i_ct2u_uom_wt" onchange="sync_wt_to_wt2()">
                                <option value="lbs.">lbs.</option>
                                <option value="kg.">kg.</option>
                            </select>
                        </div>
                    </div>

                    <!-- Column 4 -->
                    <div class="col-md-3">
                        <div class="form-group-custom">
                            <label>Material In</label>
                            <select class="form-control form-control-sm" id="i_mat_in" onchange="auto_gen_desc()">
                                <option value="BOI-MAT1">BOI-MAT1</option>
                                <option value="BOI-MAT2">BOI-MAT2</option>
                                <option value="BOI-GRS1">BOI-GRS1</option>
                                <option value="BOI-IMP1">BOI-IMP1</option>
                                <option value="BOI-IMP2">BOI-IMP2</option>
                                <option value="NONE-BOI">NONE-BOI</option>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label>Use Coil From (CSSPMSTR1)</label>
                            <select class="form-control form-control-sm" id="i_coil_from">
                                <?php foreach ($use_coil_suppliers as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup['CSTMSPPL_ID']); ?>">
                                        <?php echo htmlspecialchars($sup['CSTMSPPL_ID'] . ': ' . $sup['CONSIGNEE_COMPANY']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label>Price Period</label>
                            <select class="form-control form-control-sm" id="i_price_period">
                                <option value="">-- None --</option>
                                <?php foreach ($periods as $prd): ?>
                                    <option value="<?php echo htmlspecialchars($prd['PRICE_PERIOD']); ?>"><?php echo htmlspecialchars($prd['PRICE_PERIOD']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label>Actual Price / Unit</label>
                            <div style="display:flex; gap:6px;">
                                <input type="number" step="0.0001" class="form-control form-control-sm" id="i_price" value="0.0000" oninput="calculate_item_amount()"/>
                                <button type="button" class="btn btn-xs btn-default" onclick="calc_price_from_backend()" title="Calculate Price from Base & Premiums">
                                    ⚙️ Calc
                                </button>
                            </div>
                        </div>
                        <div class="form-group-custom">
                            <label>Item Amount</label>
                            <input type="text" class="form-control form-control-sm" id="i_amount" value="0.00" readonly style="background:#f1f5f9; font-weight:700; color:#047857;"/>
                        </div>
                    </div>
                </div>

                <!-- Description & Remark -->
                <div class="row" style="margin-top: 6px;">
                    <div class="col-md-6">
                        <div class="form-group-custom">
                            <label>Description</label>
                            <input type="text" class="form-control form-control-sm" id="i_desc" placeholder="Product specification description..."/>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group-custom">
                            <label>Item Remark</label>
                            <input type="text" class="form-control form-control-sm" id="i_remark" placeholder="Special item instructions..."/>
                        </div>
                    </div>
                </div>

                <!-- Special Label 4 ช่อง -->
                <div class="row" style="margin-top: 4px;">
                    <div class="col-md-12">
                        <div class="form-group-custom">
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

            </div>
            
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:10px 20px;">
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

$(document).ready(function() {
    <?php if ($is_edit_mode): ?>
        load_existing_so('<?php echo $sono; ?>');
    <?php else: ?>
        on_change_category();
        on_change_lme();
    <?php endif; ?>
});

function on_change_category() {
    var cat = document.getElementById("h_order_cat").value;

    <?php if (!$is_edit_mode): ?>
    var prefixField = document.getElementById("h_so_prefix");
    if (prefixField) {
        if (cat === 'ORSP') prefixField.value = 'TT';
        else if (cat === 'SCRR') prefixField.value = 'RC';
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

                document.getElementById("i_special_label1").value = c.SPECIAL_LABEL1 || '';
                document.getElementById("i_special_label2").value = c.SPECIAL_LABEL2 || '';
                document.getElementById("i_special_label3").value = c.SPECIAL_LABEL3 || '';
                document.getElementById("i_special_label4").value = c.SPECIAL_LABEL4 || '';

                on_change_lme();
            }
        }
    });
}

function on_change_lme() {
    var lme_type = document.getElementById("h_lmetype").value;
    var lme_date = document.getElementById("h_lmedate").value;
    if (!lme_date || lme_type === 'O-MANUALLY') return;

    $.ajax({
        url: 'model/so_service_mats.php',
        type: 'GET',
        data: { action: 'get_lme_rates', type: lme_type, date: lme_date },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                document.getElementById("h_orderlme").value = parseFloat(res.order_lme || 0).toFixed(2);
                document.getElementById("h_mjpprice").value = parseFloat(res.mjp_price || 0).toFixed(2);

                // แสดงผล Stdv / Factor / STDxFactor บนหน้าจอทันที
                document.getElementById("h_stdv").value = parseFloat(res.stdev || 0).toFixed(2);
                document.getElementById("h_factor").value = parseFloat(res.factor || 0).toFixed(2);
                document.getElementById("h_stdxfactor").value = parseFloat(res.stdx_factor || 0).toFixed(2);

                cur_stdv = parseFloat(res.stdev || 0);
                cur_factor = parseFloat(res.factor || 0);
                cur_stdxfactor = parseFloat(res.stdx_factor || 0);
            } else {
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

function on_change_product() {
    var prod = document.getElementById("i_product").value;
    var lenField = document.getElementById("i_length");
    var lenCtField = document.getElementById("i_ct2u_len");

    if (prod === 'CO' || prod === 'CC') {
        lenField.value = "0.000";
        lenCtField.value = "0.000";
        lenField.disabled = true;
        lenCtField.disabled = true;
    } else {
        lenField.disabled = false;
        lenCtField.disabled = false;
    }

    calculate_item_weight();
    auto_gen_desc();
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
                document.getElementById("h_po_no").value = h.CTM1_PONO || '';
                document.getElementById("h_so_date").value = h.SALEORDER_DATE ? h.SALEORDER_DATE.substring(0, 10) : '';
                document.getElementById("h_ship_date").value = h.INTENDSHIP_DATE ? h.INTENDSHIP_DATE.substring(0, 10) : '';
                document.getElementById("h_currency").value = h.CURRENCY_ID || 'USD';
                document.getElementById("h_exrate").value = Number(h.EXCHANGE_RATE || 1).toFixed(5);
                
                document.getElementById("h_tax").value = h.CTM1_TAXPERCENT !== null ? h.CTM1_TAXPERCENT : 0;
                document.getElementById("h_payment").value = h.PAYMENT_ID || '';
                document.getElementById("h_shipment").value = h.SHIPMENT_ID || '';
                document.getElementById("h_portload").value = h.CTM1_PORTLOADING || '';
                document.getElementById("h_portdis").value = h.CTM1_PORTDISCHARGE || '';
                document.getElementById("h_placedest").value = h.CTM1_PLACEDESTINATION || '';
                document.getElementById("h_countrydest").value = h.CTM1_COUNTRYDESTINATION || h.CTM1_PLACEDESTINATION || '';
                document.getElementById("h_lmetype").value = h.CTM1_LMETYPE || 'M-MONTHLY';
                
                if (h.CTM1_LMEDATE) {
                    document.getElementById("h_lmedate").value = h.CTM1_LMEDATE.substring(0, 10);
                }
                
                document.getElementById("h_orderlme").value = Number(h.CTM1_ORDERLME || 0).toFixed(2);
                document.getElementById("h_mjpprice").value = Number(h.CTM1_MJPPRICE || 0).toFixed(2);

                // โหลดค่า Stdv / Factor / STDxFactor มาแสดงผล
                document.getElementById("h_stdv").value = Number(h.CTM1_ORDERSTDV || 0).toFixed(2);
                document.getElementById("h_factor").value = Number(h.CTM1_ORDERFACTOR || 0).toFixed(2);
                document.getElementById("h_stdxfactor").value = Number(h.CTM1_ORDERSTDXFACTOR || 0).toFixed(2);
                cur_stdv = parseFloat(h.CTM1_ORDERSTDV || 0);
                cur_factor = parseFloat(h.CTM1_ORDERFACTOR || 0);
                cur_stdxfactor = parseFloat(h.CTM1_ORDERSTDXFACTOR || 0);

                document.getElementById("h_freight").value = Number(h.CTM1_OVERSEAFREIGHT || 0).toFixed(2);
                document.getElementById("h_insurance").value = Number(h.CTM1_INSURANCE || 0).toFixed(2);

                orderItems = res.items || [];
                render_items_grid();
            }
        }
    });
}

function render_items_grid() {
    var tbody = '';
    var total_fob = 0;

    if (orderItems.length > 0) {
        orderItems.forEach(function(it, idx) {
            var amt = parseFloat(it.AMOUNT || (it.CTM2_ORDERWEIGHT * it.CTM2_ACTUALPRICE) || 0);
            total_fob += amt;

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
    document.getElementById("h_fob").value = total_fob.toLocaleString(undefined, {minimumFractionDigits: 2});
    calculate_header_total();
}

function calculate_header_total() {
    var raw_fob = parseFloat(document.getElementById("h_fob").value.replace(/,/g, '')) || 0;
    var freight = parseFloat(document.getElementById("h_freight").value) || 0;
    var insur   = parseFloat(document.getElementById("h_insurance").value) || 0;
    var grand   = raw_fob + freight + insur;
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

    $('#itemEditModal').modal('show');
    on_change_product();
    sync_metric_to_dim2();
    sync_wt_to_wt2();
}

function edit_item(idx) {
    if (!orderItems[idx]) return;
    var it = orderItems[idx];

    document.getElementById("edit_index").value = idx;
    document.getElementById("item_modal_action_title").innerText = "Edit";

    document.getElementById("i_item_no").value = it.SALEORDER_ITEM;
    document.getElementById("i_product").value = it.PRODUCT_ID;
    document.getElementById("i_alloy").value = it.ALLOY;
    document.getElementById("i_temper").value = it.TEMPER;
    document.getElementById("i_sg").value = it.SURFACE_GRADE || 'SG3';
    document.getElementById("i_mg").value = it.METALLURGICAL_GRADE || 'MG3';
    document.getElementById("i_full_coil").value = it.FULL_COIL || 'F';
    document.getElementById("i_thickness").value = parseFloat(it.THICKNESS).toFixed(3);
    document.getElementById("i_width").value = parseFloat(it.WIDTH).toFixed(3);
    document.getElementById("i_length").value = parseFloat(it.LENGTH).toFixed(3);
    
    document.getElementById("i_ct2u_thk").value = parseFloat(it.CT2U_THICKNESS || 0).toFixed(3);
    document.getElementById("i_ct2u_wd").value  = parseFloat(it.CT2U_WIDTH || 0).toFixed(3);
    document.getElementById("i_ct2u_len").value = parseFloat(it.CT2U_LENGTH || 0).toFixed(3);
    document.getElementById("i_ct2u_uom_dim").value = it.CT2U_UOMDIMENSION || 'inch';

    document.getElementById("i_wt_piece").value = parseFloat(it.WEIGHT_PIECE || 0).toFixed(4);
    document.getElementById("i_weight").value = it.CTM2_ORDERWEIGHT;
    document.getElementById("i_pieces").value = it.CTM2_ORDERPIECE || 0;

    document.getElementById("i_ct2u_order_wt").value = it.CT2U_ORDERWEIGHT || it.CTM2_ORDERWEIGHT;
    document.getElementById("i_ct2u_order_pc").value = it.CT2U_ORDERPIECE || it.CTM2_ORDERPIECE;
    document.getElementById("i_ct2u_uom_wt").value  = it.CT2U_UOMWEIGHT || 'kg.';

    document.getElementById("i_price_period").value = it.PRICE_PERIOD || '';
    document.getElementById("i_price").value = parseFloat(it.CTM2_ACTUALPRICE || 0).toFixed(4);
    document.getElementById("i_amount").value = Number(it.AMOUNT || 0).toFixed(2);
    document.getElementById("i_mat_in").value = it.MATERIAL_IN || 'BOI-MAT1';
    document.getElementById("i_coil_from").value = it.COIL_FROM || 'MAT';
    document.getElementById("i_desc").value = it.CTM2_DESCRIPTION || '';
    document.getElementById("i_remark").value = it.CTM2_REMARK || '';

    document.getElementById("i_special_label1").value = it.CTM2_SPECIALLABEL1 || '';
    document.getElementById("i_special_label2").value = it.CTM2_SPECIALLABEL2 || '';
    document.getElementById("i_special_label3").value = it.CTM2_SPECIALLABEL3 || '';
    document.getElementById("i_special_label4").value = it.CTM2_SPECIALLABEL4 || '';

    $('#itemEditModal').modal('show');
    on_change_product();
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

function confirm_item_modal() {
    var idx = parseInt(document.getElementById("edit_index").value);
    var wt  = parseFloat(document.getElementById("i_weight").value) || 0;
    var prc = parseFloat(document.getElementById("i_price").value) || 0;
    var amt = wt * prc;

    var itemData = {
        SALEORDER_ITEM: document.getElementById("i_item_no").value,
        PRODUCT_ID: document.getElementById("i_product").value,
        CTM2_UOMWEIGHT: 'kg.',
        CTM2_ORDERWEIGHT: wt,
        CTM2_ORDERPIECE: parseInt(document.getElementById("i_pieces").value) || 0,
        CT2U_UOMWEIGHT: document.getElementById("i_ct2u_uom_wt").value,
        CT2U_ORDERWEIGHT: parseFloat(document.getElementById("i_ct2u_order_wt").value) || wt,
        CT2U_ORDERPIECE: parseInt(document.getElementById("i_ct2u_order_pc").value) || 0,
        PRODUCT_MODEL: (document.getElementById("i_product").value === 'CC' ? 'CIRCLE' : (document.getElementById("i_product").value === 'SH' ? 'SHEET' : 'COIL')),
        ALLOY: document.getElementById("i_alloy").value,
        TEMPER: document.getElementById("i_temper").value,
        SURFACE_GRADE: document.getElementById("i_sg").value,
        METALLURGICAL_GRADE: document.getElementById("i_mg").value,
        FULL_COIL: document.getElementById("i_full_coil").value,
        THICKNESS: parseFloat(document.getElementById("i_thickness").value) || 0,
        WIDTH: parseFloat(document.getElementById("i_width").value) || 0,
        LENGTH: parseFloat(document.getElementById("i_length").value) || 0,
        CTM2_UOMDIMENSION: 'mm.', 
        CT2U_THICKNESS: parseFloat(document.getElementById("i_ct2u_thk").value) || 0,
        CT2U_WIDTH: parseFloat(document.getElementById("i_ct2u_wd").value) || 0,
        CT2U_LENGTH: parseFloat(document.getElementById("i_ct2u_len").value) || 0,
        CT2U_UOMDIMENSION: document.getElementById("i_ct2u_uom_dim").value,
        WEIGHT_PIECE: parseFloat(document.getElementById("i_wt_piece").value) || 0,
        PRICE_PERIOD: document.getElementById("i_price_period").value,
        CTM2_ACTUALPRICE: prc,
        AMOUNT: amt,
        MATERIAL_IN: document.getElementById("i_mat_in").value,
        COIL_FROM: document.getElementById("i_coil_from").value,
        CTM2_DESCRIPTION: document.getElementById("i_desc").value,
        CTM2_REMARK: document.getElementById("i_remark").value,
        CTM2_SPECIALLABEL1: document.getElementById("i_special_label1").value,
        CTM2_SPECIALLABEL2: document.getElementById("i_special_label2").value,
        CTM2_SPECIALLABEL3: document.getElementById("i_special_label3").value,
        CTM2_SPECIALLABEL4: document.getElementById("i_special_label4").value,
        CTM2_STATUS: 'OP'
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
    var prod = document.getElementById("i_product").value;
    var t = parseFloat(document.getElementById("i_thickness").value) || 0;
    var w = parseFloat(document.getElementById("i_width").value) || 0;
    var l = parseFloat(document.getElementById("i_length").value) || 0;
    var wp = 0;

    if (prod === 'CC' && t > 0 && w > 0) {
        wp = (Math.PI / 4) * Math.pow(w, 2) * t * 0.00000271;
    } else if ((prod === 'SH' || prod === 'NC') && t > 0 && w > 0 && l > 0) {
        wp = w * l * t * 0.00000271;
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
}

function calculate_item_qty_from_pcs() {
    var pcs = parseInt(document.getElementById("i_pieces").value) || 0;
    var wp  = parseFloat(document.getElementById("i_wt_piece").value) || 0;
    if (wp > 0) {
        document.getElementById("i_weight").value = Math.round(pcs * wp);
    }
    sync_wt_to_wt2();
    calculate_item_amount();
}

function calculate_item_amount() {
    var wt  = parseFloat(document.getElementById("i_weight").value) || 0;
    var prc = parseFloat(document.getElementById("i_price").value) || 0;
    document.getElementById("i_amount").value = (wt * prc).toFixed(2);
}

function auto_gen_desc() {
    var prod = document.getElementById("i_product").value;
    var alloy = document.getElementById("i_alloy").value;
    var temper = document.getElementById("i_temper").value;
    var mat_in = document.getElementById("i_mat_in").value;
    var uom_dim = document.getElementById("i_ct2u_uom_dim").value;
    
    var t = parseFloat(document.getElementById("i_ct2u_thk").value) || 0;
    var w = parseFloat(document.getElementById("i_ct2u_wd").value) || 0;
    var l = parseFloat(document.getElementById("i_ct2u_len").value) || 0;

    var dc_tag = (mat_in === 'BOI-IMP1') ? ' DC ALLOY' : '';
    var name = 'ALUMINIUM ';
    if (prod === 'CC') name += 'CIRCLES' + dc_tag;
    else if (prod === 'SH' || prod === 'NC') name += 'SHEETS' + dc_tag;
    else name += 'COILS' + dc_tag;

    var dim = t.toFixed(3) + ' x ' + w.toFixed(3) + (l > 0 ? ' x ' + l.toFixed(3) : '') + ' ' + uom_dim;
    document.getElementById("i_desc").value = name + ' ' + alloy + '-' + temper + ' ' + dim;
}

function calc_price_from_backend() {
    var period = document.getElementById("i_price_period").value;
    var cid    = document.getElementById("h_customer").value;
    if (!period || !cid) {
        alert("Please specify Customer and Price Period first.");
        return;
    }

    var params = {
        action: 'calculate_price',
        price_period: period,
        coil_from: document.getElementById("i_coil_from").value,
        cust_id: cid,
        sono: document.getElementById("h_so_no").value,
        pono: document.getElementById("h_po_no").value,
        product_id: document.getElementById("i_product").value,
        alloy: document.getElementById("i_alloy").value,
        temper: document.getElementById("i_temper").value,
        sg: document.getElementById("i_sg").value,
        mg: document.getElementById("i_mg").value,
        thickness: document.getElementById("i_thickness").value,
        width: document.getElementById("i_width").value,
        length: document.getElementById("i_length").value,
        weight: document.getElementById("i_weight").value,
        min_tol: 0,
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
                calculate_item_amount();
            } else {
                alert("Pricing notice: " + res.message);
            }
        },
        error: function() {
            alert("Pricing calculation service error.");
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
        data: { sono: sono, force_delete: 1 },
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

function save_entire_order() {
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

    var raw_fob = parseFloat(document.getElementById("h_fob").value.replace(/,/g, '')) || 0;
    var grand   = parseFloat(document.getElementById("h_grand_total").value.replace(/,/g, '')) || 0;

    var payload = {
        header: {
            SALEORDER_NO: document.getElementById("h_so_no").value,
            SO_PREFIX: prefix,
            ORDER_CATEGORY: cat,
            SALEORDER_DATE: document.getElementById("h_so_date").value,
            INTENDSHIP_DATE: document.getElementById("h_ship_date").value,
            CSTMSPPL_ID: cust,
            CTM1_PONO: pono,
            CURRENCY_ID: document.getElementById("h_currency").value,
            EXCHANGE_RATE: parseFloat(document.getElementById("h_exrate").value) || 1,
            PAYMENT_ID: document.getElementById("h_payment").value,
            SHIPMENT_ID: document.getElementById("h_shipment").value,
            CTM1_PORTLOADING: document.getElementById("h_portload").value,
            CTM1_PORTDISCHARGE: document.getElementById("h_portdis").value,
            CTM1_PLACEDESTINATION: document.getElementById("h_placedest").value,
            CTM1_COUNTRYDESTINATION: document.getElementById("h_countrydest").value,
            CTM1_TAXPERCENT: parseInt(document.getElementById("h_tax").value) || 0,
            CTM1_LMETYPE: document.getElementById("h_lmetype").value,
            CTM1_LMEDATE: document.getElementById("h_lmedate").value,
            CTM1_ORDERLME: parseFloat(document.getElementById("h_orderlme").value) || 0,
            CTM1_MJPDATE: document.getElementById("h_lmedate").value,
            CTM1_MJPPRICE: parseFloat(document.getElementById("h_mjpprice").value) || 0,
            
            // ส่งค่า Standard Deviation / Factor / STDxFactor ไปหลังบ้าน
            CTM1_ORDERSTDV: cur_stdv,
            CTM1_ORDERFACTOR: cur_factor,
            CTM1_ORDERSTDXFACTOR: cur_stdxfactor,

            CTM1_OVERSEAFREIGHT: parseFloat(document.getElementById("h_freight").value) || 0,
            CTM1_INSURANCE: parseFloat(document.getElementById("h_insurance").value) || 0,
            CTM1_TRANSPORTATION: 0,
            CTM1_FOBTOTALAMOUNT: raw_fob,
            CTM1_GRANDTOTALAMOUNT: grand,
            CTM1_TOTALAMOUNT: grand
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
                alert("Sales Order saved successfully! Number: " + res.so_no);
                window.location.href = 'saleorder_view_mats.php?func=<?php echo $folder_func; ?>&sono=' + encodeURIComponent(res.so_no);
            } else {
                alert("Save failed: " + res.message);
            }
        },
        error: function() {
            alert("Server connection error during save.");
        }
    });
}
</script>
</html>