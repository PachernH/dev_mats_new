<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php'; // การเชื่อมต่อฐานข้อมูลหลัก

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['CID']) ? htmlspecialchars(trim($_GET['CID']), ENT_QUOTES, 'UTF-8') : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
if(!empty($cs)){
    $data_cs = RT_Cust_Supp($cs);
}
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        .display-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .info-label { font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; margin-bottom: 15px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        input[readonly] { background-color: #f1f5f9 !important; cursor: not-allowed; }
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp1';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Edit Customer & Supplier Master</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('customer_supplier_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ Back</button>
                </div>
            </div>
            
            <form id="form_cust_supp" method="POST">
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Company Profile & Identity</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-label">Company ID</div>
                                <input type="text" class="form-control" name="CSTMSPPL_ID" value="<?php echo htmlspecialchars($data_cs[0] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Account Code</div>
                                <input type="text" class="form-control" name="ACCOUNT_CODE" value="<?php echo htmlspecialchars($data_cs[1] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Supplier MAT Code</div>
                                <input type="text" class="form-control" name="SUPPLIER_MATCODE" value="<?php echo htmlspecialchars($data_cs[11] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Harmonize Code</div>
                                <input type="text" class="form-control" name="HARMONIZE_CODE" value="<?php echo htmlspecialchars($data_cs[54] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Type</div>
                                <select class="form-control" name="CSTMSPPL_TYPE">
                                    <option value="C" <?php echo ($data_cs[5] ?? '') == 'C' ? 'selected' : ''; ?>>Customer</option>
                                    <option value="S" <?php echo ($data_cs[5] ?? '') == 'S' ? 'selected' : ''; ?>>Supplier</option>
                                    <option value="B" <?php echo ($data_cs[5] ?? '') == 'B' ? 'selected' : ''; ?>>Customer and Supplier</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Head Office / Branch</div>
                                <input type="text" class="form-control" name="CSTMSPPL_BRANCH" value="<?php echo htmlspecialchars($data_cs[2] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Location</div>
                                <select class="form-control" name="CSTMSPPL_LOCATION">
                                    <option value="L" <?php echo ($data_cs[6] ?? '') == 'L' ? 'selected' : ''; ?>>Local</option>
                                    <option value="O" <?php echo ($data_cs[6] ?? '') == 'O' ? 'selected' : ''; ?>>Oversea</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Tax ID</div>
                                <input type="text" class="form-control" name="CSTMSPPL_TAXID" value="<?php echo htmlspecialchars($data_cs[3] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>

                            <div class="col-md-6">
                                <div class="info-label">Region Group</div>
                                <select class="form-control" name="REGION_GROUP">
                                    <option value="">-- Region Group --</option>
                                    <?php
                                        include('dbcon_mats-new.php');
                                        $sql = "SELECT REGION_GROUP, DESCRIPTION FROM RGNSMSTR1 ORDER BY REGION_GROUP ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[7]) && $data_cs[7] == $row["REGION_GROUP"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["REGION_GROUP"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                  
                            </div>
                            
                            <div class="col-md-6">
                                <div class="info-label">Mill Certificate Address</div>
                                <select class="form-control" name="CERTIFICATE_ADDRESS">
                                    <option value="C" <?php echo ($data_cs[4] ?? '') == 'C' ? 'selected' : ''; ?>>Consignee Address</option>
                                    <option value="N" <?php echo ($data_cs[4] ?? '') == 'N' ? 'selected' : ''; ?>>Notify Address</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Country Group</div>
                                <select class="form-control" name="COUNTRY_GROUP">
                                    <option value="">-- Country Group --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT COUNTRY_GROUP, DESCRIPTION FROM CNTRMSTR1 ORDER BY COUNTRY_GROUP ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[8]) && $data_cs[8] == $row["COUNTRY_GROUP"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["COUNTRY_GROUP"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                 
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <input type="text" class="form-control" name="CONSIGNEE_COMPANY" value="<?php echo htmlspecialchars($data_cs[24] ?? '', ENT_QUOTES, 'UTF-8'); ?>" style="font-weight:600;" />
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Address (Line 1, 2, 3)</div>
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS1" value="<?php echo htmlspecialchars($data_cs[25] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS2" value="<?php echo htmlspecialchars($data_cs[26] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="CONSIGNEE_ADDRESS3" value="<?php echo htmlspecialchars($data_cs[27] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6"><div class="info-label">Telephone</div><input type="text" class="form-control" name="CONSIGNEE_TELEPHONE" value="<?php echo htmlspecialchars($data_cs[28] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">FAX</div><input type="text" class="form-control" name="CONSIGNEE_FAX" value="<?php echo htmlspecialchars($data_cs[29] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Email</div><input type="email" class="form-control" name="CONSIGNEE_EMAIL" value="<?php echo htmlspecialchars($data_cs[30] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Contact Name</div><input type="text" class="form-control" name="CONSIGNEE_CONTACT" value="<?php echo htmlspecialchars($data_cs[31] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Position</div><input type="text" class="form-control" name="CONSIGNEE_POSITION" value="<?php echo htmlspecialchars($data_cs[32] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                        </div>
                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Notify Information</h4>
                        <div class="row">
                            <div class="col-md-12"><div class="info-label">Company Name</div><input type="text" class="form-control" name="NOTIFY_COMPANY" value="<?php echo htmlspecialchars($data_cs[33] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <input type="text" class="form-control" name="NOTIFY_ADDRESS1" value="<?php echo htmlspecialchars($data_cs[34] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="NOTIFY_ADDRESS2" value="<?php echo htmlspecialchars($data_cs[35] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="NOTIFY_ADDRESS3" value="<?php echo htmlspecialchars($data_cs[36] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6"><div class="info-label">Telephone</div><input type="text" class="form-control" name="NOTIFY_TELEPHONE" value="<?php echo htmlspecialchars($data_cs[37] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">FAX</div><input type="text" class="form-control" name="NOTIFY_FAX" value="<?php echo htmlspecialchars($data_cs[38] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Email</div><input type="email" class="form-control" name="NOTIFY_EMAIL" value="<?php echo htmlspecialchars($data_cs[39] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Contact Name</div><input type="text" class="form-control" name="NOTIFY_CONTACT" value="<?php echo htmlspecialchars($data_cs[40] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Position</div><input type="text" class="form-control" name="NOTIFY_POSITION" value="<?php echo htmlspecialchars($data_cs[41] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Tax ID</div><input type="text" class="form-control" name="NOTIFY_TAXID" value="<?php echo htmlspecialchars($data_cs[42] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Also Notify Information</h4>
                        <div class="row">
                            <div class="col-md-12"><div class="info-label">Company Name</div><input type="text" class="form-control" name="ALSONOTIFY_COMPANY" value="<?php echo htmlspecialchars($data_cs[43] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <input type="text" class="form-control" name="ALSONOTIFY_ADDRESS1" value="<?php echo htmlspecialchars($data_cs[44] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="ALSONOTIFY_ADDRESS2" value="<?php echo htmlspecialchars($data_cs[45] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="ALSONOTIFY_ADDRESS3" value="<?php echo htmlspecialchars($data_cs[46] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                            <div class="col-md-6"><div class="info-label">Telephone</div><input type="text" class="form-control" name="ALSONOTIFY_TELEPHONE" value="<?php echo htmlspecialchars($data_cs[47] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">FAX</div><input type="text" class="form-control" name="ALSONOTIFY_FAX" value="<?php echo htmlspecialchars($data_cs[48] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Email</div><input type="email" class="form-control" name="ALSONOTIFY_EMAIL" value="<?php echo htmlspecialchars($data_cs[49] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Contact Name</div><input type="text" class="form-control" name="ALSONOTIFY_CONTACT" value="<?php echo htmlspecialchars($data_cs[50] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-6"><div class="info-label">Position</div><input type="text" class="form-control" name="ALSONOTIFY_POSITION" value="<?php echo htmlspecialchars($data_cs[51] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                        </div>
                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">🚢 Shipment Data</h4>
                        <div class="row">
                            <div class="col-md-6"><div class="info-label">Currency</div>
                                <select class="form-control" name="CURRENCY_ID">
                                    <option value="">-- Currency --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT CURRENCY_ID, DESCRIPTION FROM CRNCMSTR1 ORDER BY CURRENCY_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[14]) && $data_cs[14] == $row["CURRENCY_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["CURRENCY_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>        
                            </div>

                            <div class="col-md-6"><div class="info-label">TAX (%)</div>
                               <select class="form-control" name="CSSP_TAXPERCENT">
                                    <option value="0" <?php echo (strval($data_cs[17] ?? '') == '0') ? 'selected' : ''; ?>>0</option>
                                    <option value="7" <?php echo (strval($data_cs[17] ?? '') == '7') ? 'selected' : ''; ?>>7</option>
                                </select>
                            </div>

                            <div class="col-md-6"><div class="info-label">UOM Weight</div>
                               <select class="form-control" name="UOM_WEIGHT">
                                    <option value="kg" <?php echo (($data_cs[22] ?? '') == 'kg') ? 'selected' : ''; ?>>kg</option>
                                    <option value="lbs" <?php echo (($data_cs[22] ?? '') == 'lbs') ? 'selected' : ''; ?>>lbs</option>
                                </select>                            
                            </div>

                            <div class="col-md-6"><div class="info-label">UOM Dimension</div>
                               <select class="form-control" name="UOM_DIMENSION">
                                    <option value="mm" <?php echo (($data_cs[23] ?? '') == 'mm') ? 'selected' : ''; ?>>mm</option>
                                    <option value="inch" <?php echo (($data_cs[23] ?? '') == 'inch') ? 'selected' : ''; ?>>inch</option>
                                </select>                             
                            </div>

                            <div class="col-md-12"><div class="info-label">Payment Term ID</div>
                                <select class="form-control" name="PMT_ID">
                                    <option value="">-- Payment Term ID --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT PAYMENT_ID, DESCRIPTION FROM PMTMMSTR1 ORDER BY PAYMENT_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[12]) && $data_cs[12] == $row["PMT_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["PAYMENT_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                               
                            </div>

                            <div class="col-md-12"><div class="info-label">Shipment Term ID</div>
                                <select class="form-control" name="SHIPMENT_ID">
                                    <option value="">-- Shipment Term ID --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT SHIPMENT_ID, DESCRIPTION FROM SPTMMSTR1 ORDER BY SHIPMENT_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[13]) && $data_cs[13] == $row["SHIPMENT_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["SHIPMENT_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                               
                            </div>

                            <div class="col-md-12"><div class="info-label">Freight</div><input type="text" class="form-control" name="CSSP_FREIGHT" value="<?php echo htmlspecialchars($data_cs[15] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Insurance</div><input type="text" class="form-control" name="CSSP_INSURANCE" value="<?php echo htmlspecialchars($data_cs[16] ?? '', ENT_QUOTES, 'UTF-8'); ?>" /></div>

                            <div class="col-md-12"><div class="info-label">Port Loading</div>
                                <select class="form-control" name="CSSP_PORTLOADING">
                                    <option value="">-- Port Loading --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT PORT_ID, DESCRIPTION FROM PORTMSTR1 ORDER BY PORT_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[18]) && $data_cs[18] == $row["PORT_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["PORT_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                              
                            </div>

                            <div class="col-md-12"><div class="info-label">Port Discharge</div>
                                <select class="form-control" name="CSSP_PORTDISCHARGE">
                                    <option value="">-- Port Discharge --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT PORT_ID, DESCRIPTION FROM PORTMSTR1 ORDER BY PORT_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[19]) && $data_cs[19] == $row["PORT_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["PORT_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                              
                            </div>

                            <div class="col-md-12"><div class="info-label">Place Destination</div>
                                <select class="form-control" name="CSSP_PLACEDESTINATION">
                                    <option value="">-- Place Destination --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT PLACE_ID, DESCRIPTION FROM PLCEMSTR1 ORDER BY PLACE_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[20]) && $data_cs[20] == $row["PLACE_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["PLACE_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                              
                            </div>

                            <div class="col-md-12"><div class="info-label">Country Destination</div>
                                <select class="form-control" name="CSSP_COUNTRYDESTINATION">
                                    <option value="">-- Country Destination --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                        $sql = "SELECT PLACE_ID, DESCRIPTION FROM PLCEMSTR1 ORDER BY PLACE_ID ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[21]) && $data_cs[21] == $row["PLACE_ID"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["PLACE_ID"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["DESCRIPTION"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>                                
                            </div>
                        </div>
                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">📋 Packing Data</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-label">Package Treatment</div>
                                <select class="form-control" name="PACKAGE_TREATMENT">
                                    <option value="NL" <?php echo ($data_cs[63] ?? '') == 'NL' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="HT" <?php echo ($data_cs[63] ?? '') == 'HT' ? 'selected' : ''; ?>>Heat treat.</option>
                                    <option value="FM" <?php echo ($data_cs[63] ?? '') == 'FM' ? 'selected' : ''; ?>>Fumigate.</option>
                                </select>
                            </div>
                            <div class="col-md-6"><div class="info-label">Product High (mm)</div><input type="number" class="form-control" name="PACKAGE_HIGH" value="<?php echo htmlspecialchars($data_cs[64] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            <div class="col-md-12"><div class="info-label">Weight Per Package (Kg)</div><input type="number" class="form-control" name="WEIGHTPERPACKAGE" value="<?php echo htmlspecialchars($data_cs[66] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" /></div>
                            
                            <div class="col-md-12" style="margin-bottom: 15px;">
                                <label><input type="checkbox" name="INCLUDE_HIGH" value="1" <?php echo ($data_cs[65] ?? 0) == 1 ? 'checked' : ''; ?>> Include Top Bottom Pallet Hight</label><br>
                                <label><input type="checkbox" name="INCLUDE_WEIGHT" value="1" <?php echo ($data_cs[67] ?? 0) == 1 ? 'checked' : ''; ?>> Include Top Bottom Pallet Weight</label><br>
                                <label><input type="checkbox" name="SERIAL_CODE" value="1" <?php echo ($data_cs[69] ?? 0) == 1 ? 'checked' : ''; ?>> Serial Code (WW/YY)</label>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Special Shipping Label</div>
                                <input type="text" class="form-control" name="SPECIAL_LABEL1" value="<?php echo htmlspecialchars($data_cs[70] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="SPECIAL_LABEL2" value="<?php echo htmlspecialchars($data_cs[71] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="SPECIAL_LABEL3" value="<?php echo htmlspecialchars($data_cs[72] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                                <input type="text" class="form-control" name="SPECIAL_LABEL4" value="<?php echo htmlspecialchars($data_cs[73] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Primary Country of Smelt</div>
                                <select class="form-control" name="PRIMARY_SMELT">
                                    <option value="">-- Primary Country of Smelt --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                         $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.ALUMINIUM_SMELT ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[80]) && $data_cs[80] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>   
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Secondary Country of Smelt</div>
                                <select class="form-control" name="SECONDARY_SMELT">
                                    <option value="">-- Secondary Country of Smelt --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                         $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALSMMSTR1.ALUMINIUM_SMELT ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[81]) && $data_cs[81] == $row["ALUMINIUM_SMELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>    
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Country of Melt</div>
                                <select class="form-control" name="COUNTRY_MELT">
                                    <option value="">-- Country Group --</option>
                                    <?php
                                        include('dbcon_mast.php');
                                         $sql = "SELECT COUNTRY_MELT FROM CSCTMSTR1 ORDER BY CSCTMSTR1.COUNTRY_MELT ASC";
                                        $result = $conn->prepare($sql);
                                        $result->execute();
                                        while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = (isset($data_cs[82]) && $data_cs[82] == $row["COUNTRY_MELT"]) ? "selected" : "";
                                            echo "<option value='".htmlspecialchars($row["COUNTRY_MELT"], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row["COUNTRY_MELT"], ENT_QUOTES, 'UTF-8')."</option>";
                                        }
                                    ?>
                                </select>   
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Order by</div>
                                <select class="form-control" name="ORDER_BY">
                                    <option value="W" <?php echo ($data_cs[75] ?? '') == 'W' ? 'selected' : ''; ?>>Weight</option>
                                    <option value="P" <?php echo ($data_cs[75] ?? '') == 'P' ? 'selected' : ''; ?>>Piece</option>
                                </select>
                            </div>

                            <div class="col-md-12"><div class="info-label">Order by Start Date</div><input type="text" class="form-control" name="ORDER_STARTDATE" value="<?php echo htmlspecialchars($data_cs[76] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="YYYY-MM-DD" /></div>         
                            
                            <div class="col-md-12">
                                <label><input type="checkbox" name="SHOW_PIECE" value="1" <?php echo ($data_cs[77] ?? 0) == 1 ? 'checked' : ''; ?>> Show Piece and Packing Label</label><br>
                                <label><input type="checkbox" name="ADJUST_PIECE" value="1" <?php echo ($data_cs[78] ?? 0) == 1 ? 'checked' : ''; ?>> Adjust Piece of product sheet at packing</label><br>
                                <label><input type="checkbox" name="SHOW_SCRAPWIRE" value="1" <?php echo ($data_cs[79] ?? 0) == 1 ? 'checked' : ''; ?>> Show Scrap Wire</label>
                            </div> 
                        </div>
                    </div>
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('customer_supplier_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_custsupp_data()">💾 Save Data</button>
                </div>
            </div><br>
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
function submit_custsupp_data() {
    var data_fun = document.getElementById("func").value;
    var formData = $("#form_cust_supp").serialize();

    $.ajax({
        url: "model/update_customer_supplier_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success"){
                alert("Save Data has been successfully.");
                window.location.assign('customer_supplier_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "The data could not be saved."));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred during data transmission: " + error);
        }
    });
}
</script>
</html>