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

        .btn-action-resize {
            background-color: #0284c7;
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
        .btn-action-resize:hover {
            background-color: #0369a1;
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

        /* Modal UI Styling for Resize Dialog */
        .modal-resize .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-resize .modal-header {
            background: #0f172a;
            color: #ffffff;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 16px 24px;
        }
        .modal-resize .modal-title {
            font-weight: 700;
            font-size: 18px;
        }
        .modal-resize .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .alert-error-box {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 8px;
            display: none;
        }
        .form-section-title {
            font-weight: 700;
            font-size: 14px;
            color: #0369a1;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #bae6fd;
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="resize_product_mats.php?func=<?php echo $folder_func ?>">Maintain Product Re-Size</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="pd_no">Product No</label>
                    <input class="form-control-minimal" name="pd_no" id="pd_no" type="text" placeholder="Enter Product No. to search..." value="<?php echo htmlspecialchars($pno, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onkeypress="if(event.keyCode==13){ search_batch_no(); }"/>
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
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Maintain Product Re-Size (Max 500 Records)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Product No</th>
                                        <th style="text-align: center;">Start Date</th>
                                        <th style="text-align: center;">Product ID</th>
                                        <th style="text-align: center;">Alloy</th>
                                        <th style="text-align: center;">Temper</th>
                                        <th style="text-align: center;">Thickness</th>
                                        <th style="text-align: center;">Width</th>
                                        <th style="text-align: center;">Length</th>
                                        <th style="text-align: center;">Actual Weight</th>
                                        <th style="text-align: center;">Actual Piece</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    $params = [];
                                    $where_conditions = [];

                                    // เงื่อนไขหลัก: CRSH_STATUS = 'OP' AND JOB_ORDER = ''
                                    $where_conditions[] = "c.CRSH_STATUS = 'OP'";
                                    $where_conditions[] = "(c.JOB_ORDER IS NULL OR c.JOB_ORDER = '')";

                                    if (!empty($pno)) {
                                        // === แนวทางที่ 2 ===
                                        // ถ้ามีการค้นหาด้วย Product No โดยตรง ให้ข้ามเงื่อนไข F_WIDTH = WIDTH 
                                        // เพื่อดึงชิ้นงานเดิมที่เคย Resize ไปแล้วขึ้นมาแก้ไข (UPDATE) ได้
                                        $where_conditions[] = "c.PRODUCT_NO = :pno";
                                        $params[':pno'] = $pno;
                                    } else {
                                        // ถ้าไม่ได้ค้นหาแบบระบุรหัส ให้กรองเฉพาะรายการที่ยังไม่เคย Resize
                                        $where_conditions[] = "c.F_WIDTH = c.WIDTH";
                                        $where_conditions[] = "c.F_LENGTH = c.LENGTH";

                                        if ($d != date('Y-m-d')) {
                                            $where_conditions[] = "c.CRSH_STARTDATE BETWEEN :date_st AND :date_end";
                                            $params[':date_st'] = $d . " 00:00:00";
                                            $params[':date_end'] = $d . " 23:59:59";
                                        }
                                    }

                                    $where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

                                    $sql = "SELECT TOP 200 
                                                c.PRODUCT_NO, c.PRODUCT_REFERENCE, c.CRSH_STARTDATE, c.SALEORDER_NO, 
                                                c.SALEORDER_ITEM, c.JOB_ORDER, c.ALLOY, c.TEMPER, c.THICKNESS, 
                                                c.WIDTH, c.LENGTH, c.CRSH_ACTUALWEIGHT, c.CRSH_ACTUALPIECE, c.CRSH_STATUS,
                                                c.WEIGHT_PIECE, c.CRSH_BOTTOMWEIGHT, c.PRODUCT_ID, c.PRODUCT_MODEL,
                                                c.ORG_LINEPROCESS, c.CUT_WIDTH, c.CUT_LENGTH, c.CUT_WEIGHTPIECE,
                                                c.CRSH_WORKPROCESS, c.CRSH_NEXTPROCESS,
                                                CONVERT(VARCHAR(10), r.RESZ_STARTDATE, 120) AS RESZ_STARTDATE_FMT,
                                                CONVERT(VARCHAR(8),  r.RESZ_STARTTIME, 108) AS RESZ_STARTTIME_FMT,
                                                CONVERT(VARCHAR(10), r.RESZ_ENDDATE, 120)   AS RESZ_ENDDATE_FMT,
                                                CONVERT(VARCHAR(8),  r.RESZ_ENDTIME, 108)   AS RESZ_ENDTIME_FMT
                                            FROM CRSHPROD1 AS c 
                                            LEFT JOIN RESZPROD1 AS r ON c.PRODUCT_NO = r.PRODUCT_NO
                                            {$where_sql}
                                            ORDER BY c.CRSH_STARTDATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $coils_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($coils_list as $index => $coil): 
                                    $prod_no    = htmlspecialchars($coil['PRODUCT_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $job_order  = htmlspecialchars($coil['JOB_ORDER'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $status     = trim($coil['CRSH_STATUS'] ?? '');

                                    $start_date_formatted = !empty($coil['CRSH_STARTDATE']) ? date('Y-m-d', strtotime($coil['CRSH_STARTDATE'])) : '-';
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center">
                                            <button type="button" class="btn-action-view" onclick="show_detail_product('<?php echo addslashes($coil['PRODUCT_NO']); ?>')">
                                                🔍 <?php echo $prod_no; ?>
                                            </button>
                                        </td>
                                        <td align="center"><?php echo htmlspecialchars($start_date_formatted, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['PRODUCT_ID'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($coil['TEMPER'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo function_exists('fmt3') ? fmt3($coil['THICKNESS']) : number_format((float)($coil['THICKNESS'] ?? 0), 3); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['WIDTH']) : number_format((float)($coil['WIDTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['LENGTH']) : number_format((float)($coil['LENGTH'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt2') ? fmt2($coil['CRSH_ACTUALWEIGHT']) : number_format((float)($coil['CRSH_ACTUALWEIGHT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo function_exists('fmt0') ? fmt0($coil['CRSH_ACTUALPIECE']) : number_format((float)($coil['CRSH_ACTUALPIECE'] ?? 0)); ?></td>
                                        <td align="center"><span class="status-badge"><?php echo htmlspecialchars($status !== '' ? $status : '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        
                                        <!-- ปุ่ม Resize -->
                                        <td align="center">
                                            <button type="button" class="btn-action-resize" onclick='open_resize_modal(<?php echo json_encode($coil, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                                📐 Resize
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

<!-- Modal สำหรับ Resize Product (ออกแบบการทำงานล้อตาม f3) -->
<div class="modal fade modal-resize" id="resizeModal" tabindex="-1" role="dialog" aria-labelledby="resizeModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="resizeModalLabel">📐 Re-Size Product Process (ปรับขนาดผลิตภัณฑ์)</h4>
      </div>
      <div class="modal-body">
        
        <div id="resize_error_box" class="alert-error-box"><i class="fa fa-exclamation-triangle"></i> <span id="resize_error_msg"></span></div>

        <!-- Section 1: Basic Info -->
        <div class="info-card">
            <div class="row">
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Product No:</label>
                    <input type="text" id="rs_ProductNo" class="form-control" readonly style="background:#f1f5f9; font-weight:700; color:#1e293b;"/>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Product ID:</label>
                    <select id="rs_ProductID" class="form-control" onchange="on_change_product_id()">
                        <option value="SH">SH - SHEET</option>
                        <option value="CC">CC - CIRCLE</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Product Model:</label>
                    <input type="text" id="rs_ProductModel" class="form-control" readonly style="background:#f1f5f9; font-weight:600;"/>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Thickness (mm):</label>
                    <input type="text" id="rs_Thickness" class="form-control" readonly style="background:#f1f5f9; font-weight:600;"/>
                </div>
            </div>
            <div class="row" style="margin-top: 10px;">
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Alloy / Temper:</label>
                    <input type="text" id="rs_AlloyTemper" class="form-control" readonly style="background:#f1f5f9;"/>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Original Width (mm):</label>
                    <input type="text" id="rs_OrgWidth" class="form-control" readonly style="background:#f1f5f9;"/>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Original Length (mm):</label>
                    <input type="text" id="rs_OrgLength" class="form-control" readonly style="background:#f1f5f9;"/>
                </div>
                <div class="col-md-3">
                    <label style="font-weight:700; color:#475569;">Line Process:</label>
                    <input type="text" id="rs_LineProcess" class="form-control" readonly style="background:#f1f5f9;"/>
                </div>
            </div>
        </div>

        <!-- Section 2: Re-Size Dimension Inputs -->
        <div class="form-section-title">📏 Re-Size Dimensions & Calculations</div>
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-3">
                <label style="font-weight:700; color:#0f172a;">Resize Width (mm):</label>
                <input type="number" step="0.001" id="rs_ResizeWidth" class="form-control" onblur="recalculate_all()" style="font-weight:700; color:#2563eb;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#0f172a;">Resize Length (mm):</label>
                <input type="number" step="0.001" id="rs_ResizeLength" class="form-control" onblur="recalculate_all()" style="font-weight:700; color:#2563eb;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#475569;">Weight / Piece (kg):</label>
                <input type="text" id="rs_WeightPiece" class="form-control" readonly style="background:#f1f5f9; font-weight:700;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#475569;">Bottom Weight (kg):</label>
                <input type="number" id="rs_BottomWeight" class="form-control" value="0" onblur="recalculate_all()"/>
            </div>
        </div>

        <!-- Section 3: Cut Dimensions -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-4">
                <label style="font-weight:700; color:#334155;">Cut Width (mm):</label>
                <input type="number" step="0.001" id="rs_CutWidth" class="form-control" onblur="recalculate_all()"/>
            </div>
            <div class="col-md-4">
                <label style="font-weight:700; color:#334155;">Cut Length (mm):</label>
                <input type="number" step="0.001" id="rs_CutLength" class="form-control" onblur="recalculate_all()"/>
            </div>
            <div class="col-md-4">
                <label style="font-weight:700; color:#475569;">Cut Weight / Piece (kg):</label>
                <input type="text" id="rs_CutWeightPiece" class="form-control" readonly style="background:#f1f5f9;"/>
            </div>
        </div>

        <!-- Section 4: Production Quantities & Results -->
        <div class="form-section-title">📊 Production Quantities & Weights</div>
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-3">
                <label style="font-weight:700; color:#15803d;">Accept Piece (Pcs):</label>
                <input type="number" id="rs_AcceptPiece" class="form-control" onblur="recalculate_all()" style="font-weight:700; color:#166534;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#15803d;">Accept Weight (Kg):</label>
                <input type="text" id="rs_AcceptWeight" class="form-control" readonly style="background:#f0fdf4; font-weight:700; color:#166534;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#b91c1c;">Reject Piece (Pcs):</label>
                <input type="text" id="rs_RejectPiece" class="form-control" readonly style="background:#fef2f2; color:#991b1b;"/>
            </div>
            <div class="col-md-3">
                <label style="font-weight:700; color:#b91c1c;">Reject Weight (Kg):</label>
                <input type="text" id="rs_RejectWeight" class="form-control" readonly style="background:#fef2f2; color:#991b1b;"/>
            </div>
        </div>

        <!-- Hidden values used for calculation -->
        <input type="hidden" id="rs_InputPiece"/>
        <input type="hidden" id="rs_WorkProcess"/>
        <input type="hidden" id="rs_NextProcess"/>

        <!-- Section 5: Operation Times -->
        <div class="form-section-title">⏱️ Operating DateTime</div>
        <div class="row">
            <div class="col-md-3">
                <label style="font-size:12px; font-weight:700;">Start Date:</label>
                <input type="date" id="rs_StartDate" class="form-control"/>
            </div>
            <div class="col-md-3">
                <label style="font-size:12px; font-weight:700;">Start Time:</label>
                <input type="time" id="rs_StartTime" class="form-control"/>
            </div>
            <div class="col-md-3">
                <label style="font-size:12px; font-weight:700;">End Date:</label>
                <input type="date" id="rs_EndDate" class="form-control"/>
            </div>
            <div class="col-md-3">
                <label style="font-size:12px; font-weight:700;">End Time:</label>
                <input type="time" id="rs_EndTime" class="form-control"/>
            </div>
        </div>

      </div>
      <div class="modal-footer" style="background:#f8fafc; border-bottom-left-radius:12px; border-bottom-right-radius:12px;">
        <button type="button" class="btn btn-primary" onclick="save_resize_data()" style="font-weight:600;"><i class="fa fa-save"></i> Save Re-Size (บันทึกข้อมูล)</button>
        <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight:600;">Close (ปิด)</button>
      </div>
    </div>
  </div>
</div>

</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

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
    window.location.assign('resize_product_mats.php?func='+encodeURIComponent(data_fun)+'&pno='+encodeURIComponent(data_pno)); 
}    

function show_detail_product(prod_no) {
    var data_fun = document.getElementById("func").value;
    window.location.assign('resize_product_detail_mats.php?func=' + encodeURIComponent(data_fun) + '&PRODUCT_NO=' + encodeURIComponent(prod_no));
}

function show_error(msg) {
    document.getElementById("resize_error_msg").innerText = msg;
    document.getElementById("resize_error_box").style.display = "block";
}

function hide_error() {
    document.getElementById("resize_error_msg").innerText = "";
    document.getElementById("resize_error_box").style.display = "none";
}

// ---------------------------------------------------------------------
// ฟังก์ชันคำนวณ WeightPerPiece ตามสูตรใน VB (f3)
// ---------------------------------------------------------------------
function calc_weight_per_piece(product_id, width, length, thickness) {
    var w = parseFloat(width) || 0;
    var l = parseFloat(length) || 0;
    var t = parseFloat(thickness) || 0;
    
    if (w <= 0 || t <= 0) return 0;

    var density = 0.00271; 
    if (product_id === "CC") { // Circle
        var radius = w / 2.0;
        return Math.PI * radius * radius * t * density / 1000.0;
    } else { // Sheet (SH)
        return w * l * t * density / 1000.0;
    }
}

// เปิด Modal และโหลดข้อมูลลง Control
function open_resize_modal(data) {
    hide_error();

    document.getElementById("rs_ProductNo").value = data.PRODUCT_NO || '';
    document.getElementById("rs_ProductID").value = data.PRODUCT_ID || 'SH';
    document.getElementById("rs_ProductModel").value = (data.PRODUCT_ID === 'CC') ? 'CIRCLE' : 'SHEET';
    document.getElementById("rs_Thickness").value = parseFloat(data.THICKNESS || 0).toFixed(3);
    document.getElementById("rs_AlloyTemper").value = (data.ALLOY || '') + ' / ' + (data.TEMPER || '');
    
    document.getElementById("rs_OrgWidth").value = parseFloat(data.WIDTH || 0).toFixed(3);
    document.getElementById("rs_OrgLength").value = parseFloat(data.LENGTH || 0).toFixed(3);
    
    document.getElementById("rs_ResizeWidth").value = parseFloat(data.WIDTH || 0).toFixed(3);
    document.getElementById("rs_ResizeLength").value = parseFloat(data.LENGTH || 0).toFixed(3);
    
    document.getElementById("rs_CutWidth").value = parseFloat(data.CUT_WIDTH || 0).toFixed(3);
    document.getElementById("rs_CutLength").value = parseFloat(data.CUT_LENGTH || 0).toFixed(3);
    
    var line_process = (data.ORG_LINEPROCESS || '').trim().toUpperCase();
    document.getElementById("rs_LineProcess").value = line_process;
    document.getElementById("rs_BottomWeight").value = parseFloat(data.CRSH_BOTTOMWEIGHT || 0);

    var actual_pcs = parseFloat(data.CRSH_ACTUALPIECE || 0);
    document.getElementById("rs_InputPiece").value = actual_pcs;
    document.getElementById("rs_AcceptPiece").value = actual_pcs;

    document.getElementById("rs_WorkProcess").value = data.CRSH_WORKPROCESS || '';
    document.getElementById("rs_NextProcess").value = data.CRSH_NEXTPROCESS || '';

    // ล็อก/เปิด Control ตาม Line Process แบบเดียวกับ EnableField ใน f3
    if (line_process === "A") {
        document.getElementById("rs_ProductID").disabled = false;
        document.getElementById("rs_CutWidth").disabled = false;
        document.getElementById("rs_CutLength").disabled = false;
    } else {
        document.getElementById("rs_ProductID").disabled = true;
        document.getElementById("rs_CutWidth").disabled = true;
        document.getElementById("rs_CutLength").disabled = true;
        document.getElementById("rs_CutWidth").value = "0.000";
        document.getElementById("rs_CutLength").value = "0.000";
        document.getElementById("rs_CutWeightPiece").value = "0.0000";
    }

// ดึงวันเวลาปัจจุบันเตรียมไว้สำหรับกรณี New Record
    var now = new Date();
    var year = now.getFullYear();
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');

    var todayDefault = year + '-' + month + '-' + day; // YYYY-MM-DD
    var timeDefault  = hours + ':' + minutes;         // HH:MM

    // =========================================================
    // ตรวจสอบข้อมูล RESZPROD1 (ถ้ามีค่าให้ใช้ค่าเดิม ถ้าไม่มีให้ใช้ปัจจุบัน)
    // =========================================================
    
    // Start Date & Time
    if (data.RESZ_STARTDATE_FMT && data.RESZ_STARTDATE_FMT !== '') {
        document.getElementById("rs_StartDate").value = data.RESZ_STARTDATE_FMT.substring(0, 10);
    } else {
        document.getElementById("rs_StartDate").value = todayDefault;
    }

    if (data.RESZ_STARTTIME_FMT && data.RESZ_STARTTIME_FMT !== '') {
        document.getElementById("rs_StartTime").value = data.RESZ_STARTTIME_FMT.substring(0, 5);
    } else {
        document.getElementById("rs_StartTime").value = timeDefault;
    }

    // End Date & Time
    if (data.RESZ_ENDDATE_FMT && data.RESZ_ENDDATE_FMT !== '') {
        document.getElementById("rs_EndDate").value = data.RESZ_ENDDATE_FMT.substring(0, 10);
    } else {
        document.getElementById("rs_EndDate").value = todayDefault;
    }

    if (data.RESZ_ENDTIME_FMT && data.RESZ_ENDTIME_FMT !== '') {
        document.getElementById("rs_EndTime").value = data.RESZ_ENDTIME_FMT.substring(0, 5);
    } else {
        document.getElementById("rs_EndTime").value = timeDefault;
    }

    on_change_product_id();
    recalculate_all();

    $('#resizeModal').modal('show');
}

function on_change_product_id() {
    var pid = document.getElementById("rs_ProductID").value;
    var line_process = document.getElementById("rs_LineProcess").value;

    if (pid === "CC") {
        document.getElementById("rs_ProductModel").value = "CIRCLE";
        document.getElementById("rs_ResizeLength").disabled = true;
        document.getElementById("rs_ResizeLength").value = "0.000";
    } else {
        document.getElementById("rs_ProductModel").value = "SHEET";
        document.getElementById("rs_ResizeLength").disabled = false;
    }

    if (line_process !== "A") {
        document.getElementById("rs_CutWidth").disabled = true;
        document.getElementById("rs_CutLength").disabled = true;
        document.getElementById("rs_CutWidth").value = "0.000";
        document.getElementById("rs_CutLength").value = "0.000";
        document.getElementById("rs_CutWeightPiece").value = "0.0000";
    }

    recalculate_all();
}

// ตรวจสอบข้อมูลและคำนวณค่าต่างๆ แบบเดียวกับ f3
function recalculate_all() {
    hide_error();

    var pid = document.getElementById("rs_ProductID").value;
    var resz_w = parseFloat(document.getElementById("rs_ResizeWidth").value) || 0;
    var resz_l = parseFloat(document.getElementById("rs_ResizeLength").value) || 0;
    var cut_w = parseFloat(document.getElementById("rs_CutWidth").value) || 0;
    var cut_l = parseFloat(document.getElementById("rs_CutLength").value) || 0;
    var thickness = parseFloat(document.getElementById("rs_Thickness").value) || 0;
    
    var input_pcs = parseFloat(document.getElementById("rs_InputPiece").value) || 0;
    var accept_pcs = parseFloat(document.getElementById("rs_AcceptPiece").value) || 0;
    var bottom_wt = parseFloat(document.getElementById("rs_BottomWeight").value) || 0;

    // เงื่อนไขตรวจสอบตาม f3
    if (resz_w > 1600) {
        show_error("Resize Width ห้ามเกิน 1,600 mm!");
        return false;
    }

    if (pid === "SH" && resz_l > 4000) {
        show_error("Resize Length สำหรับ SHEET ห้ามเกิน 4,000 mm!");
        return false;
    }

    if (pid === "CC" && resz_l > 0) {
        show_error("Resize Length สำหรับ CIRCLE ต้องเป็น 0!");
        document.getElementById("rs_ResizeLength").value = "0.000";
        resz_l = 0;
    }

    if (accept_pcs > input_pcs) {
        show_error("Accept Piece (" + accept_pcs + ") ห้ามมากกว่า Input Piece (" + input_pcs + ")");
        document.getElementById("rs_AcceptPiece").value = input_pcs;
        accept_pcs = input_pcs;
    }

    if (accept_pcs <= 0) {
        show_error("Accept Piece ต้องมากกว่า 0");
        return false;
    }

    if (bottom_wt >= 200) {
        show_error("Bottom Weight ต้องน้อยกว่า 200 kg!");
        return false;
    }

    // คำนวณน้ำหนักต่อชิ้น
    var weight_piece = calc_weight_per_piece(pid, resz_w, resz_l, thickness);
    document.getElementById("rs_WeightPiece").value = weight_piece.toFixed(4);

    var cut_weight_piece = 0;
    if (cut_l > 0) {
        cut_weight_piece = calc_weight_per_piece("SH", cut_w, cut_l, thickness);
    } else {
        cut_weight_piece = calc_weight_per_piece("CC", cut_w, cut_l, thickness);
    }
    document.getElementById("rs_CutWeightPiece").value = cut_weight_piece.toFixed(4);

    // คำนวณ Reject & Weight รวม
    var reject_pcs = input_pcs - accept_pcs;
    document.getElementById("rs_RejectPiece").value = Math.round(reject_pcs);

    var accept_wt = Math.round(accept_pcs * weight_piece);
    var reject_wt = Math.round(reject_pcs * weight_piece);

    document.getElementById("rs_AcceptWeight").value = accept_wt;
    document.getElementById("rs_RejectWeight").value = reject_wt;

    return true;
}

// ฟังก์ชันส่งข้อมูลบันทึก Re-Size ผ่าน AJAX
function save_resize_data() {
    if (!recalculate_all()) {
        return;
    }

    var payload = {
        product_no: document.getElementById("rs_ProductNo").value,
        product_id: document.getElementById("rs_ProductID").value,
        product_model: document.getElementById("rs_ProductModel").value,
        resize_width: document.getElementById("rs_ResizeWidth").value,
        resize_length: document.getElementById("rs_ResizeLength").value,
        weight_piece: document.getElementById("rs_WeightPiece").value,
        cut_width: document.getElementById("rs_CutWidth").value,
        cut_length: document.getElementById("rs_CutLength").value,
        cut_weight_piece: document.getElementById("rs_CutWeightPiece").value,
        accept_weight: document.getElementById("rs_AcceptWeight").value,
        accept_piece: document.getElementById("rs_AcceptPiece").value,
        reject_weight: document.getElementById("rs_RejectWeight").value,
        reject_piece: document.getElementById("rs_RejectPiece").value,
        bottom_weight: document.getElementById("rs_BottomWeight").value,
        start_date: document.getElementById("rs_StartDate").value,
        start_time: document.getElementById("rs_StartTime").value,
        end_date: document.getElementById("rs_EndDate").value,
        end_time: document.getElementById("rs_EndTime").value
    };

    $.ajax({
        url: 'model/ajax_save_resize_mats.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                alert("บันทึกการ Re-Size ผลิตภัณฑ์เรียบร้อยแล้ว!");
                $('#resizeModal').modal('hide');
                search_batch_no();
            } else {
                show_error(res.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล!");
            }
        },
        error: function(xhr, status, error) {
            show_error("ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ หรือไม่พบไฟล์ AJAX บันทึกข้อมูล (model/ajax_save_resize_mats.php)");
        }
    });
}

$('#resizeModal').on('hidden.bs.modal', function () {
    search_batch_no();
});
</script>
</html>