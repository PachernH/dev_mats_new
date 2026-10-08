<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');

if(!isset($_GET['CID'])){
    $cs = '';
} else {
    $cs = htmlspecialchars(trim($_GET['CID']), ENT_QUOTES, 'UTF-8');
}

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$data_cs = array();
$data_cs = RT_Cust_Supp($cs);
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        /* Modern UI Style Adjustments (สไตล์ชุดเดียวกับหน้า Furnace Charging) */
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { 
            padding: 20px 20px !important; 
        }
        
        /* Form & Display Card Styling */
        .display-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .card-title-sub {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
        }
        
        /* Data Group Presentation */
        .info-label {
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
            font-size: 13px;
        }
        .info-value {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-height: 42px;
            padding: 10px 14px;
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
            word-break: break-word;
            margin-bottom: 15px;
        }
        .highlight-id {
            background-color: #eff6ff !important;
            border-color: #bfdbfe !important;
            color: #1e40af !important;
            font-weight: 700 !important;
        }
        
        /* Buttons */
        .btn-custom-back {
            background-color: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-custom-back:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        
        /* Utility */
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>

<body>
<div class="wrapper">
    
    <?php $menu = 'customer_supplier_master';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">Customer & Supplier Master Details</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-custom-back" onclick="window.location.assign('customer_supplier_master_mats.php?func=' + encodeURIComponent('<?php echo $folder_func; ?>'))">
                        ⬅️ Back
                    </button>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <form id="form_cust_supp">    
                    
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Company Profile & Identity</h4>
                        <div class="row">

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Company ID</div>
                                <div class="info-value highlight-id">
                                    <?php echo htmlspecialchars($data_cs[0] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Account Code</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Supplier MAT</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[11] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Hormonize Code</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[54] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                            
                            <?php 
                                $data_type = "";

                                switch ($data_cs[5]) {
                                    case "C":
                                        $data_type = "Customer";
                                        break; // Stops execution from falling through to the next case
                                        
                                    case "S":
                                        $data_type = "Supplier.";
                                        break;
                                        
                                    case "B":
                                        $data_type = "Customer and Supplier.";
                                        break;
                                        
                                    default:
                                        $data_type = "";
                                        break; // Optional but recommended
                                }
                            ?>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Type</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_type ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Head Office / Branch</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[2] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <?php 
                                $data_location = "";

                                switch ($data_cs[6]) {
                                    case "O":
                                        $data_location = "Oversea";
                                        break; // Stops execution from falling through to the next case
                                        
                                    case "L":
                                        $data_location = "Local.";
                                        break;
                                        
                                    default:
                                        $data_location = "";
                                        break; // Optional but recommended
                                }
                            ?>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Location</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_location ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Tax ID</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[3] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <?php
                                $data_reg = array();
                                $data_reg = RT_Region($data_cs[7]);
                            ?>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Region Group</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_reg[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <?php 
                                $data_cerf = "";

                                switch ($data_cs[4]) {
                                    case "C":
                                        $data_cerf = "Consignee Address";
                                        break; // Stops execution from falling through to the next case
                                        
                                    case "N":
                                        $data_cerf = "Notify Address.";
                                        break;
                                        
                                    default:
                                        $data_cerf = "";
                                        break; // Optional but recommended
                                }
                            ?>
                            
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Mill Certificate Address</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cerf ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>       
                            <?php
                                $data_country = array();
                                $data_country = RT_Country($data_cs[8]);
                            ?>
                            <div class="col-md-12">
                                <div class="info-label">Country Group</div>
                                 <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_country[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <div class="info-value" style="font-weight: 600; color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[24] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[25] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[26] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[27] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>            
                            
                            <div class="col-md-12">
                                <div class="info-label">Telephone</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[28] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>      

                             <div class="col-md-12">
                                <div class="info-label">FAX</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[29] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                              

                             <div class="col-md-12">
                                <div class="info-label">Email</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[30] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   

                            <div class="col-md-12">
                                <div class="info-label">Contract Name</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[31] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                               

                            <div class="col-md-12">
                                <div class="info-label">Position</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[32] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   

                        </div>
                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Notify Information</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[33] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[34] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[35] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[36] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>                                                                
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Telephone</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[37] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">FAX</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[38] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                            

                             <div class="col-md-12">
                                <div class="info-label">Email</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[39] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div> 

                             <div class="col-md-12">
                                <div class="info-label">Contact Name</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[40] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div> 

                             <div class="col-md-12">
                                <div class="info-label">Position</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[41] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                             

                             <div class="col-md-12">
                                <div class="info-label">Tax ID</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[42] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                                   

                        </div>
                    </div>
                </div>


                <div class="col-lg-5 col-md-12">
                    
                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Also Notify Information</h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[43] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Address</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[44] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[45] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[46] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>                                                                
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Telephone</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[47] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">FAX</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[48] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                            

                             <div class="col-md-12">
                                <div class="info-label">Email</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[49] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div> 

                             <div class="col-md-12">
                                <div class="info-label">Contact Name</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[50] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div> 

                             <div class="col-md-12">
                                <div class="info-label">Position</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[51] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>   
                        </div>
                    </div>

                    <div class="display-card">
                        <h4 class="card-title-sub">🚢 Shipment Data</h4>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Currency</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[14] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">TAX (%)</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[17] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">OUM Weight</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[22] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">OUM Dimension</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[23] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Payment Term</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars(RT_Payment($data_cs[12]) ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <?php 
                            $data_ship = array();
                            $data_ship = RT_Shipment($data_cs[13]);
                            ?>
                            <div class="col-md-12">
                                <div class="info-label">Shipment Term</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_ship[1] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="info-label">Freight</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[15] ?? '-', ENT_QUOTES, 'UTF-8'); ?>       :   USD/MT (FREIGHT USD OF 20' OR 40' / 20T OR 40T) 
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Insurance</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[16] ?? '-', ENT_QUOTES, 'UTF-8'); ?>       :   USD/CIF AMOUNT/MT (AMOUNTX110%X0.0215)
                                </div>
                            </div>                            

                            <div class="col-md-12">
                                <div class="info-label">Port Loading</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars(RT_Port($data_cs[18]) ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Port Discharge</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars(RT_Port($data_cs[19]) ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>  

                            <div class="col-md-12">
                                <div class="info-label">Place Destination</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars(RT_Place($data_cs[20]) ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Country Destination</div>
                                <div class="info-value" style="color: #16a34a; font-weight: bold;">
                                    <?php echo htmlspecialchars(RT_Place($data_cs[21]) ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>                              

                        </div>
                    </div>

                    <?php 
                        $data_Package = "";

                        switch ($data_cs[63]) {
                            case "NL":
                                $data_Package = "Normal";
                                break; // Stops execution from falling through to the next case
                                        
                            case "HT":
                                $data_Package = "Heat treat.";
                                break;

                            case "FM":
                                $data_Package = "Fumigate.";
                                break;                                
                                        
                            default:
                                $data_Package = "";
                                break; // Optional but recommended
                        }
                    ?>

                    <div class="display-card">
                        <h4 class="card-title-sub">📋 Packing Data</h4>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Package Treatment</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_Package ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="info-label">Product High</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[64] ?? '-', ENT_QUOTES, 'UTF-8'); ?>  mm
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Weight Per Package</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[66] ?? '-', ENT_QUOTES, 'UTF-8'); ?>  Kg
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <?php if($data_cs[65]==1) { ?>
                                    <input type="checkbox" checked> Include Top Bottom Pallet Hight.  
                                <?php }else{ ?>
                                    <input type="checkbox"> Include Top Bottom Pallet Hight.  
                                <?php }?>                                    
                                <br>
                                <?php if($data_cs[67]==1) { ?>
                                    <input type="checkbox" checked> Include Top Bottom Pallet Weight.  
                                <?php }else{ ?>
                                    <input type="checkbox"> Include Top Bottom Pallet Weight. 
                                <?php }?>                                   

                                <br>
                            </div> 
                            <div class="col-md-12">
                                &nbsp;
                            </div>  
                            <div class="col-md-12">
                                <?php if($data_cs[69]==1) { ?>
                                    <input type="checkbox" checked> Serial Code (WW/YY). 
                                <?php }else{ ?>
                                    <input type="checkbox"> Serial Code (WW/YY). 
                                <?php }?>  
                            </div>                             
                            <div class="col-md-12">
                                &nbsp; 
                            </div>  
                            <div class="col-md-12">
                                <div class="info-label">Special Shipping Label</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[70] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[71] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[72] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[73] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <?php 
                                $data_order = "";

                                switch ($data_cs[75]) {
                                    case "W":
                                        $data_order = "Weight";
                                        break; // Stops execution from falling through to the next case
                                                
                                    case "P":
                                        $data_order = "Piece.";
                                        break;                               
                                                
                                    default:
                                        $data_order = "";
                                        break; // Optional but recommended
                                }
                            ?>

                            <div class="col-md-12">
                                <div class="info-label">Order by</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_order ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Order by State Date</div>
                                <div class="info-value" style="color: #193fe7; font-weight: bold;">
                                    <?php echo htmlspecialchars($data_cs[76] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>         
                            <div class="col-md-12">
                                <?php if($data_cs[77]==1) { ?>
                                    <input type="checkbox" checked> Show Piece and Pakcing Label.  
                                <?php }else{ ?>
                                    <input type="checkbox"> Show Piece and Pakcing Label.  
                                <?php }?>
                                <br>
                                <?php if($data_cs[78]==1) { ?>
                                    <input type="checkbox" checked> Adjust Piece of product sheet at packing.  
                                <?php }else{ ?>
                                    <input type="checkbox"> Adjust Piece of product sheet at packing.  
                                <?php }?>
                                <br>
                                <?php if($data_cs[79]==1) { ?>
                                    <input type="checkbox" checked> Show Scrap Wire.  
                                <?php }else{ ?>
                                    <input type="checkbox"> Show Scrap Wire.  
                                <?php }?>                                
                            </div> 
                                                                         
                        </div>
                    </div>

                </div>
            </div>

                                    
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">

</script>
</html>