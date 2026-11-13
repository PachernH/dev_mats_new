<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$is_authorized = ($department_func === 'MK' || $department_func === 'IT');

$mode   = isset($_GET['mode']) ? htmlspecialchars(trim($_GET['mode']), ENT_QUOTES, 'UTF-8') : 'new';
$period = isset($_GET['period']) ? htmlspecialchars(trim($_GET['period']), ENT_QUOTES, 'UTF-8') : '';
$coil   = isset($_GET['coil']) ? htmlspecialchars(trim($_GET['coil']), ENT_QUOTES, 'UTF-8') : '';
$cust   = isset($_GET['cust']) ? htmlspecialchars(trim($_GET['cust']), ENT_QUOTES, 'UTF-8') : '';

include("dbcon_mats-new.php");

$record_exists = false;
$main_data = [];
$premiums = [];

if (!empty($period) && !empty($coil) && !empty($cust)) {
    $stmt = $conn->prepare("SELECT * FROM PRICMSTR1 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
    $stmt->execute([$period, $coil, $cust]);
    $main_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($main_data) {
        $record_exists = true;
    }
}

if ($record_exists) {
    $premiums['basic'] = [$main_data];
    
    $st = $conn->prepare("SELECT ALLOY, ALLOY_PREMIUM FROM PRICMSTR2 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY ALLOY");
    $st->execute([$period, $coil, $cust]); $premiums['alloy'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT ALLOY, TEMPER, TEMPER_PREMIUM FROM PRICMSTR3 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY ALLOY, TEMPER");
    $st->execute([$period, $coil, $cust]); $premiums['temper'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT ALLOY, SURFACE_GRADE, SG_PREMIUM FROM PRICMSTR4 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY ALLOY, SURFACE_GRADE");
    $st->execute([$period, $coil, $cust]); $premiums['surface'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT METALLURGICAL_GRADE, MG_PREMIUM FROM PRICMSTR5 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
    $st->execute([$period, $coil, $cust]); $premiums['metallurgical'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT THICKNESS_FROM, THICKNESS_TO, THICKNESS_PREMIUM FROM PRICMSTR6 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY THICKNESS_FROM");
    $st->execute([$period, $coil, $cust]); $premiums['thickness'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT PRODUCT_ID, DIAMETER_FROM, DIAMETER_TO, DIAMETER_PREMIUM FROM PRICMSTR7 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY DIAMETER_FROM");
    $st->execute([$period, $coil, $cust]); $premiums['diameter'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT LENGTH_FROM, LENGTH_TO, LENGTH_PREMIUM FROM PRICMSTR8 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY LENGTH_FROM");
    $st->execute([$period, $coil, $cust]); $premiums['length'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT QUANTITY_FROM, QUANTITY_TO, QUANTITY_PREMIUM FROM PRICMSTR9 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? ORDER BY QUANTITY_FROM");
    $st->execute([$period, $coil, $cust]); $premiums['quantity'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT TOLERANCE_FROM, TOLERANCE_TO, TOLERANCE_PREMIUM FROM PRICMSTR10 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
    $st->execute([$period, $coil, $cust]); $premiums['tolerance'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT FULL_COIL, FULLCOIL_PREMIUM FROM PRICMSTR11 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
    $st->execute([$period, $coil, $cust]); $premiums['fullcoil'] = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $st = $conn->prepare("SELECT FCL_PREMIUM FROM PRICMSTR12 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
    $st->execute([$period, $coil, $cust]); $premiums['fcl'] = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Price Period Setup</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card {
            background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        .form-item label {
            font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px;
            letter-spacing: 0.5px; text-transform: uppercase; display: block;
        }
        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding: 4px 6px !important; height: 36px; font-size: 13.5px; font-weight: 600; color: #1e293b;
            width: 100%; transition: border-color 0.2s;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .conversion-box {
            background: #f1f5f9; border-radius: 8px; border: 1px solid #e2e8f0; padding: 14px 16px; margin: 15px 0;
        }
        .conversion-header { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 10px; }
        .conversion-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .conv-card { background: #fff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .conv-card .title { font-size: 11.5px; font-weight: 700; color: #64748b; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #f1f5f9; }
        .conv-card .val1 { font-size: 13px; font-weight: 700; color: #1d4ed8; }
        .conv-card .val2 { font-size: 13px; font-weight: 700; color: #15803d; }

        /* ปรับแถบแท็บให้อยู่บรรทัดเดียว ไม่ขึ้นบรรทัดใหม่ และเลื่อนซ้าย-ขวาได้ */
        .nav-tabs-custom { 
            border-bottom: 2px solid #e2e8f0; 
            display: flex; 
            flex-wrap: nowrap !important; /* บังคับให้อยู่บรรทัดเดียว ห้ามตกบรรทัด */
            overflow-x: auto;             /* ถ้าแท็บยาวเกินหน้าจอ ให้เลื่อนแนวนอนได้ */
            overflow-y: hidden;
            white-space: nowrap;          /* ข้อความในแท็บไม่ตัดบรรทัด */
            padding: 0 0 2px 0; 
            margin-bottom: 16px; 
            list-style: none;
            -webkit-overflow-scrolling: touch;
        }

        /* ซ่อนแถบ Scrollbar เพื่อความสวยงาม สะอาดตา */
        .nav-tabs-custom::-webkit-scrollbar {
            height: 4px; /* แถบเลื่อนเส้นบางๆ */
        }
        .nav-tabs-custom::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }

        .nav-tabs-custom li {
            flex-shrink: 0; /* ป้องกันแท็บถูกบีบจนข้อความเบียดกัน */
        }

        .nav-tabs-custom li a {
            display: inline-block; 
            padding: 7px 11px; /* ลด Padding เล็กน้อยเพื่อให้วางได้กว้างขึ้น */
            font-size: 11.5px; 
            font-weight: 700; 
            color: #64748b;
            border-radius: 6px 6px 0 0; 
            text-decoration: none; 
            border-bottom: 2px solid transparent; 
            transition: all 0.2s;
        }

        .nav-tabs-custom li.active a, 
        .nav-tabs-custom li a:hover {
            color: #2563eb; 
            border-bottom-color: #2563eb; 
            background-color: #eff6ff;
        }

        .table-premium { width: 100%; border-collapse: collapse; }
        .table-premium th {
            background-color: #f8fafc; color: #475569; font-size: 11px; font-weight: 700;
            text-transform: uppercase; padding: 8px 10px; border-bottom: 2px solid #e2e8f0;
        }
        .table-premium td { padding: 8px 10px; font-size: 12.5px; border-bottom: 1px solid #f1f5f9; }

        .editable-cell {
            cursor: pointer; position: relative; padding: 6px 10px !important;
            transition: background-color 0.2s, outline 0.2s; border-radius: 4px;
        }
        .editable-cell:hover {
            background-color: #fef9c3 !important; outline: 1px dashed #ca8a04;
        }
        .editable-cell.saving { background-color: #e0f2fe !important; }
        .inline-edit-input {
            width: 100%; height: 28px; padding: 2px 6px; font-size: 13px;
            font-weight: 700; text-align: right; border: 2px solid #2563eb;
            border-radius: 4px; outline: none; background: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-del-row {
            color: #ef4444; background: none; border: none; font-size: 16px; font-weight: 700;
            cursor: pointer; padding: 2px 8px; border-radius: 4px; line-height: 1;
        }
        .btn-del-row:hover { background-color: #fee2e2; }

        .btn-add-item {
            background-color: #2563eb; color: #fff; font-size: 11.5px; font-weight: 700;
            padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
            margin-bottom: 12px; display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-add-item:hover { background-color: #1d4ed8; }

        .btn-action-save {
            background-color: #059669; color: #fff; font-weight: 700; font-size: 13px;
            height: 38px; padding: 0 20px; border-radius: 6px; border: none; cursor: pointer;
        }
        .btn-action-save:hover { background-color: #047857; }
        .btn-action-delete {
            background-color: #ef4444; color: #fff; font-weight: 700; font-size: 13px;
            height: 38px; padding: 0 18px; border-radius: 6px; border: none; cursor: pointer;
        }
        .btn-action-delete:hover { background-color: #dc2626; }
        .btn-action-calc {
            background-color: #d97706; color: #fff; font-weight: 700; font-size: 13px;
            height: 38px; padding: 0 18px; border-radius: 6px; border: none; cursor: pointer;
        }
        .btn-action-calc:hover { background-color: #b45309; }
        .btn-action-back {
            background-color: #e2e8f0; color: #475569; font-weight: 600; font-size: 12px;
            height: 34px; padding: 0 14px; border-radius: 6px; border: none; display: inline-flex; align-items: center; text-decoration: none;
        }
        .btn-action-back:hover { background-color: #cbd5e1; color: #1e293b; text-decoration: none; }

        /* ป้องกัน Modal โดน Backdrop สีดำทับ */
        .modal { z-index: 1060 !important; }
        .modal-backdrop { z-index: 1050 !important; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'PRICING'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="price_period_list.php?func=<?php echo $folder_func ?>">
                        Price Period Management
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="dashboard-card" style="padding: 16px 20px; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:17px;">
                            Price Period Detail & Surcharge Configuration
                        </h4>
                        <span style="font-size: 12px; color: #64748b;">Period: <strong><?php echo $period ?: '-'; ?></strong> | Customer: <strong><?php echo $cust ?: '-'; ?></strong></span>
                    </div>
                    <div>
                        <a href="price_period_list.php?func=<?php echo $folder_func; ?>" class="btn-action-back"> ⬅️ BACK TO LIST</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- ฝั่งซ้าย: ตั้งค่าราคาหลัก -->
                <div class="col-md-4">
                    <div class="dashboard-card">
                        <h5 style="margin:0 0 16px 0; font-weight:700; color:#1e293b; font-size:14px; text-transform:uppercase;">
                            Standard Price Settings
                        </h5>
                        <form id="priceForm">
                            <input type="hidden" id="record_status" value="<?php echo $record_exists ? 'Old Record' : 'New Record'; ?>"/>

                            <div class="form-item" style="margin-bottom: 14px;">
                                <label>Price Period (YYYYMM-DD)</label>
                                <input type="text" class="form-control-minimal" id="txtCode" value="<?php echo $period; ?>" 
                                       placeholder="202610-50" required oninput="this.value = this.value.toUpperCase()"/>
                            </div>

                            <div class="row" style="margin-bottom: 14px;">
                                <div class="col-xs-6 form-item">
                                    <label>Coil From</label>
                                    <select class="form-control-minimal" id="cmbCoilFrom">
                                        <option value="MAT" <?php echo ($coil==='MAT' || ($main_data['COIL_FROM']??'')==='MAT')?'selected':'';?>>MAT</option>
                                        <option value="MAL" <?php echo ($coil==='MAL' || ($main_data['COIL_FROM']??'')==='MAL')?'selected':'';?>>MAL</option>
                                    </select>
                                </div>
                                <div class="col-xs-6 form-item">
                                    <label>Currency</label>
                                    <select class="form-control-minimal" id="cmbCurrency"></select>
                                </div>
                            </div>

                            <div class="form-item" style="margin-bottom: 14px;">
                                <label>Customer</label>
                                <select class="form-control-minimal" id="cmbCustomerID" required>
                                    <option value="">-- Select Customer --</option>
                                </select>
                            </div>

                            <div class="row" style="margin-bottom: 14px;">
                                <div class="col-xs-6 form-item">
                                    <label>Lifetime From</label>
                                    <input type="date" class="form-control" id="dtpLifetimeFrom" 
                                           value="<?php echo !empty($main_data['LIFETIME_FROM']) ? date('Y-m-d', strtotime($main_data['LIFETIME_FROM'])) : ''; ?>" required/>
                                </div>
                                <div class="col-xs-6 form-item">
                                    <label>Lifetime To</label>
                                    <input type="date" class="form-control" id="dtpLifetimeTo" 
                                           value="<?php echo !empty($main_data['LIFETIME_TO']) ? date('Y-m-d', strtotime($main_data['LIFETIME_TO'])) : ''; ?>" required/>
                                </div>
                            </div>

                            <div class="form-item" style="margin-bottom: 14px;">
                                <label>Exchange Rate</label>
                                <input type="number" step="0.00001" class="form-control-minimal text-right" id="txtExchangeRate" 
                                       value="<?php echo number_format((float)($main_data['EXCHANGE_RATE'] ?? 1), 5, '.', ''); ?>" required/>
                            </div>

                            <div class="row" style="margin-bottom: 14px;">
                                <div class="col-xs-6 form-item">
                                    <label>LME Average (USD)</label>
                                    <input type="number" step="0.01" class="form-control-minimal text-right" id="txtLMEAverage" 
                                           value="<?php echo number_format((float)($main_data['LME_AVERAGE'] ?? 0), 2, '.', ''); ?>" required/>
                                </div>
                                <div class="col-xs-6 form-item">
                                    <label>Refund Interest</label>
                                    <input type="number" step="0.0001" class="form-control-minimal text-right" id="txtRefundInterest" 
                                           value="<?php echo number_format((float)($main_data['REFUND_INTEREST'] ?? 0), 4, '.', ''); ?>" required/>
                                </div>
                            </div>

                            <!-- Base Standard Prices Box -->
                            <div class="conversion-box">
                                <div class="conversion-header">Base Standard Prices</div>
                                <div class="conversion-grid">
                                    <div class="conv-card">
                                        <div class="title">CIRCLE</div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span class="badge" style="background-color: #dbeafe; color: #1d4ed8; font-size: 10px; font-weight: 700; padding: 2px 6px;">Duty</span>
                                            <span class="val1" id="lblCircle1">0.00</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="badge" style="background-color: #dcfce7; color: #15803d; font-size: 10px; font-weight: 700; padding: 2px 6px;">No Duty</span>
                                            <span class="val2" id="lblCircle2">0.00</span>
                                        </div>
                                    </div>

                                    <div class="conv-card">
                                        <div class="title">SHEET</div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span class="badge" style="background-color: #dbeafe; color: #1d4ed8; font-size: 10px; font-weight: 700; padding: 2px 6px;">Duty</span>
                                            <span class="val1" id="lblSheet1">0.00</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="badge" style="background-color: #dcfce7; color: #15803d; font-size: 10px; font-weight: 700; padding: 2px 6px;">No Duty</span>
                                            <span class="val2" id="lblSheet2">0.00</span>
                                        </div>
                                    </div>

                                    <div class="conv-card">
                                        <div class="title">COIL</div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <span class="badge" style="background-color: #dbeafe; color: #1d4ed8; font-size: 10px; font-weight: 700; padding: 2px 6px;">Duty</span>
                                            <span class="val1" id="lblCoil1">0.00</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="badge" style="background-color: #dcfce7; color: #15803d; font-size: 10px; font-weight: 700; padding: 2px 6px;">No Duty</span>
                                            <span class="val2" id="lblCoil2">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; flex-direction:column; gap:8px; margin-top:15px;">
                                <?php if ($is_authorized): ?>
                                    <button type="button" class="btn-action-save" id="btnSave" onclick="saveData()">SAVE CHANGES</button>
                                    
                                    <button type="button" class="btn-action-delete" id="btnDelete" onclick="deleteData()" 
                                            style="<?php echo $record_exists ? 'display:block;' : 'display:none;'; ?>">
                                        DELETE THIS PERIOD
                                    </button>

                                    <button type="button" class="btn-action-calc" id="btnCalcOrders" onclick="calcOrders()" 
                                            style="<?php echo $record_exists ? 'display:block;' : 'display:none;'; ?>">
                                        CALCULATE SALE ORDERS
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ฝั่งขวา: รายการ Basic Cost และ Premiums ทั้งหมด -->
                <div class="col-md-8">
                    <div class="dashboard-card" style="min-height: 580px;">
                        <ul class="nav-tabs-custom" id="premiumTabs">
                            <li class="active"><a href="#tab_basic" data-toggle="tab">Basic Cost</a></li>
                            <li><a href="#tab_alloy" data-toggle="tab">Alloy (<?php echo count($premiums['alloy']??[]); ?>)</a></li>
                            <li><a href="#tab_temper" data-toggle="tab">Temper (<?php echo count($premiums['temper']??[]); ?>)</a></li>
                            <li><a href="#tab_surface" data-toggle="tab">Surface (<?php echo count($premiums['surface']??[]); ?>)</a></li>
                            <li><a href="#tab_meta" data-toggle="tab">Metallurgical (<?php echo count($premiums['metallurgical']??[]); ?>)</a></li>
                            <li><a href="#tab_thickness" data-toggle="tab">Thickness (<?php echo count($premiums['thickness']??[]); ?>)</a></li>
                            <li><a href="#tab_diameter" data-toggle="tab">Diameter (<?php echo count($premiums['diameter']??[]); ?>)</a></li>
                            <li><a href="#tab_length" data-toggle="tab">Length (<?php echo count($premiums['length']??[]); ?>)</a></li>
                            <li><a href="#tab_qty" data-toggle="tab">Quantity (<?php echo count($premiums['quantity']??[]); ?>)</a></li>
                            <li><a href="#tab_tolerance" data-toggle="tab">Tolerance (<?php echo count($premiums['tolerance']??[]); ?>)</a></li>
                            <li><a href="#tab_coil" data-toggle="tab">Full Coil (<?php echo count($premiums['fullcoil']??[]); ?>)</a></li>
                            <li><a href="#tab_fcl" data-toggle="tab">FCL (<?php echo count($premiums['fcl']??[]); ?>)</a></li>
                        </ul>

                        <div class="tab-content" style="max-height: 480px; overflow-y: auto;">
                            <!-- 1. Basic Cost (คลิกแก้ไขตัวเลขได้ทุกช่องตาม p2) -->
                            <div class="tab-pane active" id="tab_basic">
                                <div style="font-size:12px; color:#64748b; margin-bottom:10px;">
                                    <i class="fa fa-info-circle"></i> คลิกที่ตัวเลขเพื่อแก้ไขต้นทุน (Basic Cost) ค่าจะบันทึกและอัปเดต Base Standard Prices ทันที
                                </div>
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Cost Factor</th>
                                            <th class="text-right" style="width: 140px;">Value / Rate</th>
                                            <th>Unit / Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Ingot Premium</td>
                                            <td class="text-right editable-cell font-weight-bold" data-table="PRICMSTR1" data-field="INGOT_PREMIUM">
                                                <?php echo number_format((float)($main_data['INGOT_PREMIUM']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>USD/Ton</td>
                                        </tr>
                                        <tr>
                                            <td>Import Duty (%)</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="INGOT_IMPORTDUTY">
                                                <?php echo number_format((float)($main_data['INGOT_IMPORTDUTY']??0), 0, '.', ''); ?>
                                            </td>
                                            <td>Percent (%)</td>
                                        </tr>
                                        <tr>
                                            <td>Ingot Inland Freight</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="INGOT_FREIGHT">
                                                <?php echo number_format((float)($main_data['INGOT_FREIGHT']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>USD/Ton</td>
                                        </tr>
                                        <tr>
                                            <td>Melt Loss (%)</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="INGOT_MELTLOSS">
                                                <?php echo number_format((float)($main_data['INGOT_MELTLOSS']??0), 0, '.', ''); ?>
                                            </td>
                                            <td>Percent (%)</td>
                                        </tr>
                                        <tr>
                                            <td>Product Packing Cost</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="PRODUCT_PACKING">
                                                <?php echo number_format((float)($main_data['PRODUCT_PACKING']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>USD/Ton</td>
                                        </tr>
                                        <tr>
                                            <td>Product Inland Freight</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="PRODUCT_FREIGHT">
                                                <?php echo number_format((float)($main_data['PRODUCT_FREIGHT']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>USD/Ton</td>
                                        </tr>
                                        <tr>
                                            <td>Fuel Surcharge</td>
                                            <td class="text-right editable-cell" data-table="PRICMSTR1" data-field="FUEL_SURCHARGE">
                                                <?php echo number_format((float)($main_data['FUEL_SURCHARGE']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>USD/Ton</td>
                                        </tr>
                                        <tr>
                                            <td>Base Conversion (Circle)</td>
                                            <td class="text-right editable-cell text-primary font-weight-bold" data-table="PRICMSTR1" data-field="BASE_CONVERSIONCIRCLE">
                                                <?php echo number_format((float)($main_data['BASE_CONVERSIONCIRCLE']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>Circle Base (USD/Ton)</td>
                                        </tr>
                                        <tr>
                                            <td>Base Conversion (Sheet)</td>
                                            <td class="text-right editable-cell text-primary font-weight-bold" data-table="PRICMSTR1" data-field="BASE_CONVERSIONSHEET">
                                                <?php echo number_format((float)($main_data['BASE_CONVERSIONSHEET']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>Sheet Base (USD/Ton)</td>
                                        </tr>
                                        <tr>
                                            <td>Base Conversion (Coil)</td>
                                            <td class="text-right editable-cell text-primary font-weight-bold" data-table="PRICMSTR1" data-field="BASE_CONVERSIONCOIL">
                                                <?php echo number_format((float)($main_data['BASE_CONVERSIONCOIL']??0), 2, '.', ''); ?>
                                            </td>
                                            <td>Coil Base (USD/Ton)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 2. Alloy -->
                            <div class="tab-pane" id="tab_alloy">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR2')">+ Add Alloy</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Alloy</th><th class="text-right">Premium (USD/Ton)</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['alloy']??[] as $r): ?>
                                            <tr>
                                                <td><strong><?php echo $r['ALLOY']; ?></strong></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR2" data-alloy="<?php echo $r['ALLOY']; ?>">
                                                    <?php echo number_format((float)$r['ALLOY_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR2', {alloy: '<?php echo $r['ALLOY']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 3. Temper -->
                            <div class="tab-pane" id="tab_temper">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR3')">+ Add Temper</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Alloy</th><th>Temper</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['temper']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo $r['ALLOY']; ?></td>
                                                <td><strong><?php echo $r['TEMPER']; ?></strong></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR3" data-alloy="<?php echo $r['ALLOY']; ?>" data-temper="<?php echo $r['TEMPER']; ?>">
                                                    <?php echo number_format((float)$r['TEMPER_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR3', {alloy: '<?php echo $r['ALLOY']; ?>', temper: '<?php echo $r['TEMPER']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 4. Surface -->
                            <div class="tab-pane" id="tab_surface">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR4')">+ Add Surface</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Alloy</th><th>Surface Grade</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['surface']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo $r['ALLOY']; ?></td>
                                                <td><strong><?php echo $r['SURFACE_GRADE']; ?></strong></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR4" data-alloy="<?php echo $r['ALLOY']; ?>" data-surface_grade="<?php echo $r['SURFACE_GRADE']; ?>">
                                                    <?php echo number_format((float)$r['SG_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR4', {alloy: '<?php echo $r['ALLOY']; ?>', surface_grade: '<?php echo $r['SURFACE_GRADE']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 5. Metallurgical -->
                            <div class="tab-pane" id="tab_meta">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR5')">+ Add Metallurgical</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Metallurgical Grade</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['metallurgical']??[] as $r): ?>
                                            <tr>
                                                <td><strong><?php echo $r['METALLURGICAL_GRADE']; ?></strong></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR5" data-metallurgical_grade="<?php echo $r['METALLURGICAL_GRADE']; ?>">
                                                    <?php echo number_format((float)$r['MG_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR5', {metallurgical_grade: '<?php echo $r['METALLURGICAL_GRADE']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 6. Thickness -->
                            <div class="tab-pane" id="tab_thickness">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR6')">+ Add Thickness</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Thickness From</th><th>Thickness To</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['thickness']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo $r['THICKNESS_FROM']; ?></td>
                                                <td><?php echo $r['THICKNESS_TO']; ?></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR6" data-thickness_from="<?php echo $r['THICKNESS_FROM']; ?>" data-thickness_to="<?php echo $r['THICKNESS_TO']; ?>">
                                                    <?php echo number_format((float)$r['THICKNESS_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR6', {thickness_from: '<?php echo $r['THICKNESS_FROM']; ?>', thickness_to: '<?php echo $r['THICKNESS_TO']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 7. Diameter -->
                            <div class="tab-pane" id="tab_diameter">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR7')">+ Add Diameter</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Product</th><th>Diameter From</th><th>Diameter To</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['diameter']??[] as $r): ?>
                                            <tr>
                                                <td><strong><?php echo $r['PRODUCT_ID']; ?></strong></td>
                                                <td><?php echo $r['DIAMETER_FROM']; ?></td>
                                                <td><?php echo $r['DIAMETER_TO']; ?></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR7" data-product_id="<?php echo $r['PRODUCT_ID']; ?>" data-diameter_from="<?php echo $r['DIAMETER_FROM']; ?>" data-diameter_to="<?php echo $r['DIAMETER_TO']; ?>">
                                                    <?php echo number_format((float)$r['DIAMETER_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR7', {product_id: '<?php echo $r['PRODUCT_ID']; ?>', diameter_from: '<?php echo $r['DIAMETER_FROM']; ?>', diameter_to: '<?php echo $r['DIAMETER_TO']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 8. Length -->
                            <div class="tab-pane" id="tab_length">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR8')">+ Add Length</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Length From</th><th>Length To</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['length']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo $r['LENGTH_FROM']; ?></td>
                                                <td><?php echo $r['LENGTH_TO']; ?></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR8" data-length_from="<?php echo $r['LENGTH_FROM']; ?>" data-length_to="<?php echo $r['LENGTH_TO']; ?>">
                                                    <?php echo number_format((float)$r['LENGTH_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR8', {length_from: '<?php echo $r['LENGTH_FROM']; ?>', length_to: '<?php echo $r['LENGTH_TO']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 9. Quantity -->
                            <div class="tab-pane" id="tab_qty">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR9')">+ Add Quantity Range</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Qty From (kg)</th><th>Qty To (kg)</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['quantity']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo number_format((float)$r['QUANTITY_FROM']); ?></td>
                                                <td><?php echo number_format((float)$r['QUANTITY_TO']); ?></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR9" data-quantity_from="<?php echo $r['QUANTITY_FROM']; ?>" data-quantity_to="<?php echo $r['QUANTITY_TO']; ?>">
                                                    <?php echo number_format((float)$r['QUANTITY_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR9', {quantity_from: '<?php echo $r['QUANTITY_FROM']; ?>', quantity_to: '<?php echo $r['QUANTITY_TO']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 10. Tolerance -->
                            <div class="tab-pane" id="tab_tolerance">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR10')">+ Add Tolerance</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Tolerance Range</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['tolerance']??[] as $r): ?>
                                            <tr>
                                                <td><?php echo $r['TOLERANCE_FROM']; ?> % ถึง <?php echo $r['TOLERANCE_TO']; ?> %</td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR10" data-tolerance_from="<?php echo $r['TOLERANCE_FROM']; ?>" data-tolerance_to="<?php echo $r['TOLERANCE_TO']; ?>">
                                                    <?php echo number_format((float)$r['TOLERANCE_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR10', {tolerance_from: '<?php echo $r['TOLERANCE_FROM']; ?>', tolerance_to: '<?php echo $r['TOLERANCE_TO']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 11. Full Coil -->
                            <div class="tab-pane" id="tab_coil">
                                <?php if ($record_exists && $is_authorized): ?>
                                    <button type="button" class="btn-add-item" onclick="openAddModal('PRICMSTR11')">+ Add Full Coil</button>
                                <?php endif; ?>
                                <table class="table-premium">
                                    <thead><tr><th>Full Coil Code</th><th class="text-right">Premium</th><th style="width:40px;"></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($premiums['fullcoil']??[] as $r): ?>
                                            <tr>
                                                <td><strong><?php echo $r['FULL_COIL']; ?></strong></td>
                                                <td class="text-right editable-cell" data-table="PRICMSTR11" data-full_coil="<?php echo $r['FULL_COIL']; ?>">
                                                    <?php echo number_format((float)$r['FULLCOIL_PREMIUM'], 2, '.', ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($is_authorized): ?>
                                                        <button type="button" class="btn-del-row" onclick="deletePremiumRow('PRICMSTR11', {full_coil: '<?php echo $r['FULL_COIL']; ?>'})">&times;</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- 12. FCL -->
                            <div class="tab-pane" id="tab_fcl">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Customer</th>
                                            <th>Coil From</th>
                                            <th class="text-right">Premium (USD/Ton)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($premiums['fcl'])): ?>
                                            <?php foreach ($premiums['fcl'] as $r): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($period); ?></td>
                                                    <td><strong><?php echo htmlspecialchars($cust); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($coil); ?></td>
                                                    <td class="text-right editable-cell font-weight-bold" data-table="PRICMSTR12" style="color:#1e3a8a;">
                                                        <?php echo number_format((float)$r['FCL_PREMIUM'], 2, '.', ''); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center" style="color:#94a3b8; padding: 20px;">
                                                    No FCL Premium Data Found for this Period
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- Add Premium Modal (วางอยู่นอก wrapper เพื่อไม่ให้โดน backdrop ทับ) -->
<div class="modal fade" id="addPremiumModal" tabindex="-1" role="dialog" style="z-index: 1060;">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content" style="border-radius:10px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modalAddTitle" style="font-weight:700; font-size:15px; color:#1e293b;">Add Item</h4>
            </div>
            <div class="modal-body" id="modalAddBody"></div>
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddPremiumRow()">Add Record</button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>
<script src="model/price_calculator.js?v=<?php echo time(); ?>"></script>

<script type="text/javascript">
$(document).ready(function() {
    // ย้าย Modal ไปอยู่ต่อท้าย <body> เพื่อแก้ปัญหาฉากหลังสีดำทับหน้าต่าง
    $('#addPremiumModal').appendTo("body");

    initPage("<?php echo $period; ?>", "<?php echo $coil; ?>", "<?php echo $cust; ?>");

    // จัดการ Inline Edit สำหรับทุกตารางรวมถึง Basic Cost (PRICMSTR1)
    $(document).on('click', '.editable-cell', function() {
        const $cell = $(this);
        if ($cell.find('input').length > 0) return;

        const currentVal = parseFloat($cell.text().replace(/,/g, '').trim()) || 0;
        const $input = $('<input type="number" step="0.01" class="inline-edit-input" />').val(currentVal);

        $cell.html($input);
        $input.focus().select();

        function commitChange() {
            const newVal = parseFloat($input.val());
            if (isNaN(newVal) || newVal === currentVal) {
                $cell.text(currentVal.toFixed(2));
                return;
            }

            $cell.addClass('saving').text('Saving...');

            const custVal = document.getElementById('cmbCustomerID') ? document.getElementById('cmbCustomerID').value : '';
            const custId  = custVal.includes(':') ? custVal.split(':')[0].trim() : (custVal.trim() || "<?php echo $cust; ?>");

            const payload = {
                table: $cell.data('table'),
                field: $cell.data('field'), // สำหรับ PRICMSTR1
                value: newVal,
                keys: {
                    price_period: document.getElementById('txtCode') ? document.getElementById('txtCode').value.trim() : "<?php echo $period; ?>",
                    coil_from: document.getElementById('cmbCoilFrom') ? document.getElementById('cmbCoilFrom').value.trim() : "<?php echo $coil; ?>",
                    customer_id: custId,
                    alloy: $cell.data('alloy'),
                    temper: $cell.data('temper'),
                    surface_grade: $cell.data('surface_grade'),
                    metallurgical_grade: $cell.data('metallurgical_grade'),
                    thickness_from: $cell.data('thickness_from'),
                    thickness_to: $cell.data('thickness_to'),
                    product_id: $cell.data('product_id'),
                    diameter_from: $cell.data('diameter_from'),
                    diameter_to: $cell.data('diameter_to'),
                    length_from: $cell.data('length_from'),
                    length_to: $cell.data('length_to'),
                    quantity_from: $cell.data('quantity_from'),
                    quantity_to: $cell.data('quantity_to'),
                    tolerance_from: $cell.data('tolerance_from'),
                    tolerance_to: $cell.data('tolerance_to'),
                    full_coil: $cell.data('full_coil')
                }
            };

            fetch('model/api_price_master.php?action=update_premium_cell', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                $cell.removeClass('saving');
                if (res.status) {
                    $cell.text(newVal.toFixed(2));
                    $cell.css('background-color', '#dcfce7');
                    setTimeout(() => $cell.css('background-color', ''), 800);

                    // หากแก้ไขค่าใน PRICMSTR1 ให้คำนวณราคาหน้าจอใหม่ทันที
                    if ($cell.data('table') === 'PRICMSTR1' && $cell.data('field')) {
                        currentMasterData[$cell.data('field')] = newVal;
                        runLocalCalculation();
                    }
                } else {
                    alert('Update failed: ' + res.message);
                    $cell.text(currentVal.toFixed(2));
                }
            })
            .catch(err => {
                $cell.removeClass('saving').text(currentVal.toFixed(2));
                alert('Error updating: ' + err);
            });
        }

        $input.on('blur', commitChange);
        $input.on('keydown', function(e) {
            if (e.which === 13) {
                commitChange();
            } else if (e.which === 27) {
                $cell.text(currentVal.toFixed(2));
            }
        });
    });
});
</script>
</body>
</html>