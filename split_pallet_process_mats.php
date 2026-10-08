<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า pno (ใช้รองรับ PRODUCT_NO) และกำหนดตัวแปรให้ตรงกัน
$pno = !isset($_GET['pno']) ? '' : htmlspecialchars(trim($_GET['pno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { padding: 20px 20px !important; }
        
        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }        

        /* Filter Section Layout */
        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Form Styling Control */
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }

        /* ช่องป้อน ค้นหาแบบไร้ขอบสี่เหลี่ยม */
        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 42px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus {
            border-bottom-color: #2563eb !important;
            outline: none;
        }

        /* Buttons Styling */
        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            height: 42px;
            padding: 0 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background-color: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-action-view {
            background-color: #10b981;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-action-view:hover {
            background-color: #059669;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        /* Modern Table Styles */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }

        .status-badge {
            color: #059669;
            font-weight: 700;
            background-color: #ecfdf5;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #a7f3d0;
            display: inline-block;
        }

        .btn-split-pallet {
            background-color: #10b981 !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-split-pallet:hover {
            background-color: #059669 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        .btn-update-pallet {
            background-color: #2563eb !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-update-pallet:hover {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }     
        
        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }        

        /* MODAL STYLES */
        .modal-split-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 18px 24px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-split-header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-split-header .close {
            color: #ffffff !important;
            opacity: 0.9 !important;
            font-size: 28px !important;
            text-shadow: none;
            transition: all 0.2s;
            outline: none;
        }
        .modal-split-header .close:hover {
            opacity: 1 !important;
            transform: scale(1.1);
        }

        .target-product-bar {
            background-color: #ffffff;
            padding: 14px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
            font-size: 15px;
            color: #334155;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .modal-split-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background-color: #ffffff;
            padding: 20px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .modal-split-card.main-card {
            border-top: 4px solid #dc2626;
        }
        .modal-split-card.split-card {
            border-top: 4px solid #2563eb;
        }

        .modal-split-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 17px;
            margin-top: 0;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .flex-input-group {
            display: flex;
            align-items: center;
            width: 100%;
        }
        .flex-input-group .form-control {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            border-right: none !important;
            flex: 1;
        }
        .flex-input-group .unit-addon {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            font-size: 14px;
            padding: 0 16px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            border-left: none;
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            white-space: nowrap;
        }

        .split-readonly-box {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 18px !important;
            text-align: center;
            border: 1px solid #1e3a8a !important;
            border-radius: 8px !important;
            height: 44px;
        }

        .split-input-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            font-weight: 700;
            font-size: 16px;
            color: #0f172a;
            text-align: right;
            height: 44px;
        }

        .field-sublabel {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }

        .btn-save-modal {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            height: 46px !important;
            padding: 0 28px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(29, 78, 216, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        .btn-save-modal:hover {
            background-color: #1e40af !important;
            box-shadow: 0 4px 6px rgba(30, 64, 175, 0.4) !important;
            transform: translateY(-1px);
        }

        .btn-cancel-modal {
            background-color: #ffffff !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            height: 46px !important;
            padding: 0 22px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            transition: all 0.2s ease !important;
        }
        .btn-cancel-modal:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A4';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="split_pallet_process_mats.php?func=<?php echo $folder_func ?>">Maintain Product Split Pallet</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="open_coil_split_coldmill()" class="btn-tab-item">Maintain Coil Split at Cold Mill </button>
                <button onclick="open_coil_split_pallet()" class="btn-tab-item active">Maintain Product Split Pallet</button>
            </div>
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="pd_no">Product No</label>
                    <input class="form-control-minimal" name="pd_no" id="pd_no" type="text" placeholder="Enter Product No. to search..." value="<?php echo htmlspecialchars($pno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Product Split Pallet Records</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th>Product No</th>
                                        <th>Product Ref</th>
                                        <th>Start Date</th>
                                        <th>Sale Order No</th>
                                        <th>Sale Order Item</th>
                                        <th>Job Order</th>
                                        <th>Alloy</th>
                                        <th>Temper</th>
                                        <th>Thickness</th>
                                        <th>Width</th>
                                        <th>Length</th>
                                        <th>Actual Weight</th>
                                        <th>Actual Piece</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $extra_where = "";

                                    if (!empty($pno)) {
                                        $extra_where .= " AND c.PRODUCT_NO = :pno";
                                        $params[':pno'] = $pno;
                                    } else {
                                        if ($d != date('Y-m-d')) {
                                            $extra_where .= " AND c.CRSH_STARTDATE BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $sql = "SELECT TOP(100) c.PRODUCT_NO, c.PRODUCT_REFERENCE, c.CRSH_STARTDATE, c.SALEORDER_NO, 
                                                   c.SALEORDER_ITEM, c.JOB_ORDER, c.ALLOY, c.TEMPER, c.THICKNESS, 
                                                   c.WIDTH, c.LENGTH, c.CRSH_ACTUALWEIGHT, c.CRSH_ACTUALPIECE, c.CRSH_STATUS,
                                                   c.WEIGHT_PIECE, c.CRSH_BOTTOMWEIGHT
                                            FROM CRSHPROD1 AS c 
                                            LEFT JOIN SPLTPROD1 s ON c.PRODUCT_NO = s.PRODUCT_NO 
                                            WHERE (c.CRSH_STATUS = 'OP' OR c.CRSH_STATUS = 'AC') {$extra_where}
                                            ORDER BY c.CRSH_STARTDATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $prod_no    = htmlspecialchars($coil['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $prod_ref   = trim($coil['PRODUCT_REFERENCE'] ?? '');
                                    $status     = trim($coil['CRSH_STATUS'] ?? '');
                                    $act_weight = (float)($coil['CRSH_ACTUALWEIGHT'] ?? 0);
                                    $act_piece  = (int)($coil['CRSH_ACTUALPIECE'] ?? 0);
                                    $weight_pc  = (float)($coil['WEIGHT_PIECE'] ?? 0);
                                    $bottom_wt  = (float)($coil['CRSH_BOTTOMWEIGHT'] ?? 0);

                                    $start_date_formatted = !empty($coil['CRSH_STARTDATE']) ? date('Y-m-d', strtotime($coil['CRSH_STARTDATE'])) : '-';

                                    if (!empty($prod_ref)) {
                                        $btn_label = 'UPDATE PALLET';
                                        $btn_class = 'btn-update-pallet'; 
                                        $mode      = 'UPDATE';
                                    } else {
                                        $btn_label = 'SPLIT PALLET';
                                        $btn_class = 'btn-split-pallet';  
                                        $mode      = 'SPLIT';
                                    }
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <button type="button" class="btn-action-view" onclick="show_detail_product('<?php echo addslashes($coil['PRODUCT_NO']); ?>')">
                                                🔍 <?php echo $prod_no; ?>
                                            </button>
                                        </td>
                                        <td><?php echo htmlspecialchars($prod_ref !== '' ? $prod_ref : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($start_date_formatted, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($coil['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['SALEORDER_ITEM'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['JOB_ORDER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['WIDTH']) : number_format((float)($coil['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['LENGTH']) : number_format((float)($coil['LENGTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['CRSH_ACTUALWEIGHT']) : number_format((float)($coil['CRSH_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt0') ? fmt0($coil['CRSH_ACTUALPIECE']) : number_format((float)($coil['CRSH_ACTUALPIECE'] ?? 0)); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <td align="center">
                                            <button type="button" class="<?php echo $btn_class; ?>" 
                                                    onclick="open_split_modal('<?php echo addslashes($prod_no); ?>', '<?php echo addslashes($prod_ref); ?>', <?php echo $act_weight; ?>, <?php echo $act_piece; ?>, <?php echo $weight_pc; ?>, <?php echo $bottom_wt; ?>, '<?php echo $mode; ?>')">
                                                <?php echo $btn_label; ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- ==========================================
     POP-UP MODAL: SPLIT / UPDATE PALLET
=========================================== -->
<div class="modal fade" id="splitPalletModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document" style="margin-top: 50px;">
    <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); overflow: hidden;">
      
      <div class="modal-split-header">
        <h4>✂️ Product Split Pallet Process</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body" style="padding: 24px; background-color: #f8fafc;">
        <input type="hidden" id="modal_product_no" />
        <input type="hidden" id="modal_product_ref" />
        <input type="hidden" id="modal_weight_piece" />
        <input type="hidden" id="modal_mode" />

        <div class="target-product-bar">
            <div>Target Product No: <strong id="lbl_prod_no" style="color: #2563eb; font-size: 16px;">-</strong></div>
            <div style="border-left: 2px solid #cbd5e1; height: 18px;"></div>
            <div>Ref Product: <strong id="lbl_prod_ref" style="color: #059669; font-size: 16px;">-</strong></div>
        </div>

        <div class="row">
            <!-- Left Card: Main Product -->
            <div class="col-md-6">
                <div class="modal-split-card main-card">
                    <div class="modal-split-title">
                        <span>Main Product <span id="title_main_prod_no" style="color: #dc2626; font-size: 15px;"></span></span>
                        <span class="badge" style="background-color: #fee2e2; color: #dc2626; font-weight: 700; padding: 6px 10px; font-size: 12px;">Primary</span>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="field-sublabel">Bottom Weight</label>
                        <div class="flex-input-group">
                            <input type="number" step="any" id="main_bottom_weight" class="form-control split-input-box" value="0" />
                            <div class="unit-addon">kg.</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="field-sublabel">NET WEIGHT - PIECE</label>
                        <div class="row" style="margin-left: -4px; margin-right: -4px;">
                            <div class="col-xs-6" style="padding-left: 4px; padding-right: 4px;">
                                <input type="number" step="any" id="main_net_weight" class="form-control split-input-box" style="text-align: center;" value="0" oninput="calculate_split('main')" />
                                <div style="text-align: center; font-size: 12px; color: #64748b; font-weight: 700; margin-top: 6px;">kg.</div>
                            </div>
                            <div class="col-xs-6" style="padding-left: 4px; padding-right: 4px;">
                                <input type="number" id="main_piece" class="form-control split-readonly-box" readonly value="0" />
                                <div style="text-align: center; font-size: 12px; color: #64748b; font-weight: 700; margin-top: 6px;">Pcs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Card: Split Product -->
            <div class="col-md-6">
                <div class="modal-split-card split-card">
                    <div class="modal-split-title">
                        <span>Split Product <span id="title_split_prod_no" style="color: #2563eb; font-size: 15px;"></span></span>
                        <span class="badge" style="background-color: #dbeafe; color: #2563eb; font-weight: 700; padding: 6px 10px; font-size: 12px;">Splitted</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="field-sublabel">Bottom Weight</label>
                        <div class="flex-input-group">
                            <input type="number" step="any" id="split_bottom_weight" class="form-control split-input-box" value="0" />
                            <div class="unit-addon">kg.</div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="field-sublabel">NET WEIGHT - PIECE</label>
                        <div class="row" style="margin-left: -4px; margin-right: -4px;">
                            <div class="col-xs-6" style="padding-left: 4px; padding-right: 4px;">
                                <input type="number" step="any" id="split_net_weight" class="form-control split-input-box" style="text-align: center;" value="0" oninput="calculate_split('split')" />
                                <div style="text-align: center; font-size: 12px; color: #64748b; font-weight: 700; margin-top: 6px;">kg.</div>
                            </div>
                            <div class="col-xs-6" style="padding-left: 4px; padding-right: 4px;">
                                <input type="number" id="split_piece" class="form-control split-readonly-box" readonly value="0" />
                                <div style="text-align: center; font-size: 12px; color: #64748b; font-weight: 700; margin-top: 6px;">Pcs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      </div>

      <div class="modal-footer" style="background-color: #ffffff; border-top: 1px solid #e2e8f0; padding: 18px 24px; display: flex; justify-content: flex-end; gap: 12px;">
        <button type="button" class="btn btn-cancel-modal" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-save-modal" onclick="submit_split_pallet()">💾 Save Changes</button>
      </div>

    </div>
  </div>
</div>

</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

var total_actual_weight = 0;
var total_actual_piece  = 0;
var current_weight_pc   = 0;

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[0, 'asc']],
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_pno = document.getElementById("pd_no").value; 
    window.location.assign('split_pallet_process_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(data_pno)); 
}    

function show_detail_product(prod_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('split_pallet_product_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&PRODUCT_NO=' + encodeURIComponent(prod_no));
}

function open_coil_split_coldmill(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('split_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

function open_coil_split_pallet(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('split_pallet_process_mats.php?func='+encodeURIComponent(data_fun)); 
}

/* ========================================================
   FUNCTIONS FOR SPLIT/UPDATE PALLET POP-UP MODAL
======================================================== */
function open_split_modal(prod_no, prod_ref, act_weight, act_piece, weight_pc, bottom_wt, mode) {
    total_actual_weight = parseFloat(act_weight) || 0;
    total_actual_piece  = parseInt(act_piece) || 0;
    current_weight_pc   = parseFloat(weight_pc) || 0;
    var default_bottom_wt = parseFloat(bottom_wt) || 0;

    $('#modal_product_no').val(prod_no);
    $('#modal_product_ref').val(prod_ref);
    $('#modal_weight_piece').val(current_weight_pc);
    $('#modal_mode').val(mode);

    $('#lbl_prod_no').text(prod_no);
    $('#lbl_prod_ref').text(prod_ref !== '' ? prod_ref : '-');

    if (mode === 'UPDATE' && prod_ref !== '') {
        // กรณี UPDATE PALLET: Main Product คือ Ref Product และ Split Product คือ Target Product
        $('#title_main_prod_no').text('(' + prod_ref + ')');
        $('#title_split_prod_no').text('(' + prod_no + ')');

        $.ajax({
            url: 'get_split_ref_detail_mats.php',
            type: 'GET',
            data: { target_no: prod_no, ref_no: prod_ref },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    // Main Product (Ref Product)
                    $('#main_bottom_weight').val(res.main.bottom_weight);
                    $('#main_net_weight').val(res.main.net_weight);
                    $('#main_piece').val(res.main.piece);

                    // Split Product (Target Product No)
                    $('#split_bottom_weight').val(res.split.bottom_weight);
                    $('#split_net_weight').val(res.split.net_weight);
                    $('#split_piece').val(res.split.piece);

                    // รวมค่าน้ำหนักและจำนวนชิ้นทั้งหมดของทั้งสอง Pallet
                    total_actual_weight = res.main.net_weight + res.split.net_weight;
                    total_actual_piece  = res.main.piece + res.split.piece;
                }
            },
            error: function() {
                console.log('Error fetching UPDATE pallet details');
            }
        });

    } else {
        // กรณี SPLIT PALLET (สร้าง Pallet ใหม่): Main Product คือ Target Product
        $('#title_main_prod_no').text('(' + prod_no + ')');
        $('#title_split_prod_no').text('');

        $('#main_bottom_weight').val(default_bottom_wt);
        $('#main_net_weight').val(Math.round(total_actual_weight));
        $('#main_piece').val(total_actual_piece);

        $('#split_bottom_weight').val(0);
        $('#split_net_weight').val(0);
        $('#split_piece').val(0);
    }

    // เปิด Modal Pop-up
    $('#splitPalletModal').modal('show');
}

function calculate_split(changed_from) {
    if (changed_from === 'split') {
        // แก้ไขน้ำหนักฝั่ง Split Product -> คำนวณฝั่ง Main ย้อนกลับ
        var split_net_wt = parseFloat($('#split_net_weight').val()) || 0;

        if (split_net_wt > total_actual_weight) {
            split_net_wt = total_actual_weight;
            $('#split_net_weight').val(split_net_wt);
        }
        if (split_net_wt < 0) {
            split_net_wt = 0;
            $('#split_net_weight').val(split_net_wt);
        }

        var split_pc = 0;
        if (current_weight_pc > 0) {
            split_pc = Math.round(split_net_wt / current_weight_pc);
        } else if (total_actual_weight > 0) {
            split_pc = Math.round((split_net_wt / total_actual_weight) * total_actual_piece);
        }

        if (split_pc > total_actual_piece) split_pc = total_actual_piece;

        var main_net_wt = Math.round(total_actual_weight - split_net_wt);
        var main_pc     = total_actual_piece - split_pc;

        $('#split_piece').val(split_pc);
        $('#main_net_weight').val(main_net_wt);
        $('#main_piece').val(main_pc);

    } else if (changed_from === 'main') {
        // แก้ไขน้ำหนักฝั่ง Main Product -> คำนวณฝั่ง Split ย้อนกลับ
        var main_net_wt = parseFloat($('#main_net_weight').val()) || 0;

        if (main_net_wt > total_actual_weight) {
            main_net_wt = total_actual_weight;
            $('#main_net_weight').val(main_net_wt);
        }
        if (main_net_wt < 0) {
            main_net_wt = 0;
            $('#main_net_weight').val(main_net_wt);
        }

        var main_pc = 0;
        if (current_weight_pc > 0) {
            main_pc = Math.round(main_net_wt / current_weight_pc);
        } else if (total_actual_weight > 0) {
            main_pc = Math.round((main_net_wt / total_actual_weight) * total_actual_piece);
        }

        if (main_pc > total_actual_piece) main_pc = total_actual_piece;

        var split_net_wt = Math.round(total_actual_weight - main_net_wt);
        var split_pc     = total_actual_piece - main_pc;

        $('#main_piece').val(main_pc);
        $('#split_net_weight').val(split_net_wt);
        $('#split_piece').val(split_pc);
    }
}

function submit_split_pallet() {
    var p_no        = $('#modal_product_no').val();
    var p_ref       = $('#modal_product_ref').val();
    var mode        = $('#modal_mode').val();
    
    var main_btm    = $('#main_bottom_weight').val();
    var main_pc     = $('#main_piece').val();
    var main_net    = $('#main_net_weight').val();

    var split_btm   = $('#split_bottom_weight').val();
    var split_pc    = $('#split_piece').val();
    var split_net   = $('#split_net_weight').val();

    if (parseFloat(split_net) <= 0) {
        alert('กรุณาระบุน้ำหนัก (Split Weight) ที่ต้องการ Split');
        return;
    }

    if (confirm('คุณต้องการบันทึกรายการ ' + mode + ' สำหรับ Product No: ' + p_no + ' ใช่หรือไม่?')) {
        $.ajax({
            url: 'model/save_split_pallet_mats.php',
            type: 'POST',
            data: {
                product_no: p_no,
                product_ref: p_ref,
                mode: mode,
                main_bottom_weight: main_btm,
                main_piece: main_pc,
                main_net_weight: main_net,
                split_bottom_weight: split_btm,
                split_piece: split_pc,
                split_net_weight: split_net
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('บันทึกข้อมูลเรียบร้อยแล้ว');
                    $('#splitPalletModal').modal('hide');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + response.message);
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        });
    }
}
</script>
</html>