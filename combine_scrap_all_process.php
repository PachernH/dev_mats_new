<?php
// combine_scrap_all_process.php
session_start();
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'OPERATOR';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '1';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include 'dbcon_mats-new.php';

$sno        = !isset($_GET['sno']) ? '' : htmlspecialchars(trim($_GET['sno']), ENT_QUOTES, 'UTF-8');
$date_from  = !isset($_GET['date_from']) ? '' : htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8');
$date_to    = !isset($_GET['date_to']) ? '' : htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8');
$print_url  = isset($_GET['print_url']) ? $_GET['print_url'] : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'history';

// 1. ดึง Location Master
$stmtLocation = $conn->query("SELECT DISTINCT LOCATION_ID FROM LCTNMSTR1 WHERE LOCATION_ID IS NOT NULL ORDER BY LOCATION_ID ASC");
$locations = $stmtLocation->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึง Process ทั้งหมดจาก PRSSMSTR2 (ใช้ DESCRIPTION)
$stmtProcess = $conn->query("SELECT PROCESS_ID, DESCRIPTION FROM PRSSMSTR2 ORDER BY PROCESS_ID ASC");
$processes = $stmtProcess->fetchAll(PDO::FETCH_ASSOC);
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
        .main-panel .content { padding: 20px !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        
        .nav-tabs-custom { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { background: #f1f5f9; color: #64748b; font-weight: 700; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; font-size: 13.5px; transition: all 0.2s; }
        .tab-btn.active { background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2); }
        .tab-btn:hover { background: #e2e8f0; color: #334155; text-decoration: none; }
        
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 38px; font-size: 14px; font-weight: 600; color: #1e293b; width: 100%; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; height: 38px; padding: 0 20px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-reset { background-color: #e2e8f0; color: #475569; font-weight: 600; height: 38px; padding: 0 15px; border-radius: 8px; border: none; cursor: pointer; }

        .table-responsive { border: none !important; margin-top: 5px; }
        .data-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; padding: 10px 8px; border-bottom: 2px solid #cbd5e1; }
        .data-table tbody td { padding: 10px 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bfdbfe; font-size: 12px; display: inline-block; }
        .badge-pno { background-color: #f0fdf4; color: #166534; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bbf7d0; font-size: 12px; display: inline-block; cursor: pointer; text-decoration: underline; }
        .badge-pno:hover { background-color: #dcfce7; }
        .badge-line { background-color: #fef3c7; color: #92400e; font-weight: 700; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; border: 1px solid #fde68a; display: inline-block; }

        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; padding: 6px 14px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }
        .btn-action-print { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 6px 14px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-action-print:hover { background-color: #1d4ed8; color: #fff; text-decoration: none; }
        .btn-action-del { background-color: #dc2626; color: #ffffff; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: none; font-size: 11.5px; cursor: pointer; }

        .vb-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .vb-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px; display: block; }
        .vb-input-readonly { background-color: #000080 !important; color: #ffffff !important; font-weight: 700; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'mats';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Combine Scrap for All Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Tabs Menu -->
            <div class="nav-tabs-custom">
                <a href="combine_scrap_all_process.php?func=<?php echo $folder_func; ?>&tab=history" class="tab-btn <?php echo ($active_tab == 'history') ? 'active' : ''; ?>">
                    📋 History Scrap Records / Reprint Label
                </a>
                <a href="combine_scrap_all_process.php?func=<?php echo $folder_func; ?>&tab=combine" class="tab-btn <?php echo ($active_tab == 'combine') ? 'active' : ''; ?>">
                    📥 Combine Scrap Operation
                </a>
            </div>

            <?php if ($active_tab == 'history'): ?>
            <!-- TAB 1: HISTORY (ค้นหาตามคำค้นหา + วันที่ทำ Scrap) -->
            <div class="filter-wrapper">
                <div style="flex:1.5; min-width:200px;">
                    <label style="font-size: 11px; font-weight: 700; color: #64748b;">SEARCH SCRAP NO. / REFERENCE / COIL</label>
                    <input class="form-control-minimal" id="scrap_search" type="text" placeholder="Enter keyword to search..." value="<?php echo $sno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_data()"/>
                </div>
                <div style="width:160px;">
                    <label style="font-size: 11px; font-weight: 700; color: #64748b;">SCRAP DATE FROM</label>
                    <input type="date" class="form-control" id="date_from" value="<?php echo $date_from; ?>" style="height:38px; font-size:13px; font-weight:600;"/>
                </div>
                <div style="width:160px;">
                    <label style="font-size: 11px; font-weight: 700; color: #64748b;">SCRAP DATE TO</label>
                    <input type="date" class="form-control" id="date_to" value="<?php echo $date_to; ?>" style="height:38px; font-size:13px; font-weight:600;"/>
                </div>
                <div>
                    <button class="btn-search" onclick="search_data()">🔍 SEARCH</button>
                    <button class="btn-reset" onclick="reset_search()" title="ล้างค่าการค้นหา">CLEAR</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                📦 History Scrap Records
                            </h4>
                        </div>
                        <div class="table-responsive table-full-width">
                            <table class="table data-table" id="tblHistory">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">Scrap No</th>
                                        <th style="text-align:center;">Date</th>
                                        <th style="text-align:center;">Line</th>
                                        <th style="text-align:center;">Process</th>
                                        <th style="text-align:center;">Scrap From</th>
                                        <th style="text-align:center;">Alloy</th>
                                        <th style="text-align:right;">Total Scrap Wt.</th>
                                        <th style="text-align:center;">Location</th>
                                        <th style="text-align:center;">Operator</th>
                                        <th style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $params_h = [];
                                    $where_h = "1=1";

                                    // 1. ค้นหาด้วยคำค้น (Scrap No / Coil No)
                                    if (!empty($sno)) {
                                        $where_h .= " AND (PRODUCT_NO LIKE :sno OR COIL_NO LIKE :sno)";
                                        $params_h[':sno'] = '%' . $sno . '%';
                                    }

                                    // 2. ค้นหาด้วยวันที่โดยต่อเวลาสำหรับ smalldatetime (รองรับ SQL Server ทุกเวอร์ชั่น)
                                    if (!empty($date_from) && !empty($date_to)) {
                                        $where_h .= " AND (ISNULL(SCRP_DATE, SCRP_OPERATEDATE) BETWEEN :date_from AND :date_to)";
                                        $params_h[':date_from'] = $date_from . ' 00:00:00';
                                        $params_h[':date_to']   = $date_to . ' 23:59:59';
                                    } elseif (!empty($date_from)) {
                                        $where_h .= " AND (ISNULL(SCRP_DATE, SCRP_OPERATEDATE) >= :date_from)";
                                        $params_h[':date_from'] = $date_from . ' 00:00:00';
                                    } elseif (!empty($date_to)) {
                                        $where_h .= " AND (ISNULL(SCRP_DATE, SCRP_OPERATEDATE) <= :date_to)";
                                        $params_h[':date_to']   = $date_to . ' 23:59:59';
                                    }

                                    $sql_h = "SELECT TOP 300 PRODUCT_NO, SCRP_DATE, SCRP_OPERATEDATE, LINE_PROCESS, PROCESS, SCRP_FROM, ALLOY, SCRP_WEIGHT, LOCATION_ID, SCRP_OPERATOR 
                                            FROM SCRPPROD1 
                                            WHERE {$where_h} 
                                            ORDER BY ISNULL(SCRP_DATE, SCRP_OPERATEDATE) DESC, PRODUCT_NO DESC";
                                    $stmt_h = $conn->prepare($sql_h);
                                    $stmt_h->execute($params_h);

                                    while($row_h = $stmt_h->fetch(PDO::FETCH_ASSOC)) {
                                        $pno       = htmlspecialchars($row_h['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                        $line      = htmlspecialchars($row_h['LINE_PROCESS'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        $raw_date  = !empty($row_h['SCRP_DATE']) ? $row_h['SCRP_DATE'] : $row_h['SCRP_OPERATEDATE'];
                                        $disp_date = !empty($raw_date) ? date('Y-m-d', strtotime($raw_date)) : '-';

                                        echo "<tr>";
                                        echo "<td align='center'><span class='badge-pno' onclick=\"viewCombineDetail('{$pno}')\" title='คลิกเพื่อดูรายละเอียดการ Combine'>📦 {$pno}</span></td>";
                                        echo "<td align='center' style='color:#64748b; font-weight:600;'>{$disp_date}</td>";
                                        echo "<td align='center'><span class='badge-line'>Line {$line}</span></td>";
                                        echo "<td align='center'>".htmlspecialchars($row_h['PROCESS'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'><span class='badge-job'>".htmlspecialchars($row_h['SCRP_FROM'] ?? '-', ENT_QUOTES, 'UTF-8')."</span></td>";
                                        echo "<td align='center'>".htmlspecialchars($row_h['ALLOY'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='right' style='font-weight:700;'>".number_format((float)$row_h['SCRP_WEIGHT'], 0)." kg</td>";
                                        echo "<td align='center'>".htmlspecialchars($row_h['LOCATION_ID'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>".htmlspecialchars($row_h['SCRP_OPERATOR'] ?? '-', ENT_QUOTES, 'UTF-8')."</td>";
                                        echo "<td align='center'>";
                                        echo "<button type='button' class='btn-action-print' onclick=\"previewPrintLabel('{$pno}')\">🖨️ REPRINT</button>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- TAB 2: COMBINE SCRAP OPERATION -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <div class="row">
                            <!-- ฝั่งซ้าย: Master Scrap -->
                            <div class="col-md-6">
                                <div class="vb-box">
                                    <h5 style="margin-top:0; font-weight:700; color:#1e293b; border-bottom:1px solid #cbd5e1; padding-bottom:8px;">
                                        🔹 Master Scrap Configuration
                                    </h5>
                                    <div class="row" style="margin-bottom:10px;">
                                        <div class="col-xs-6">
                                            <span class="vb-label">Product No.</span>
                                            <input type="text" class="form-control" id="txtProductNo" value="[ AUTO GENERATE ]" readonly style="background:#fee2e2; color:#991b1b; font-weight:700;">
                                        </div>
                                        <div class="col-xs-6">
                                            <span class="vb-label">Status</span>
                                            <span class="label label-primary" style="font-size:14px; padding:6px 12px; display:inline-block;">OP: OPEN</span>
                                        </div>
                                    </div>
                                    <div style="margin-bottom:10px;">
                                        <span class="vb-label">Scrap at Process *</span>
                                        <select class="form-control" id="cmbProcess" onchange="onProcessChange()" style="font-weight:700; color:#1e293b;">
                                            <option value="">-- กรุณาเลือก Process --</option>
                                            <?php foreach($processes as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p['PROCESS_ID']); ?>">
                                                    <?php echo htmlspecialchars($p['PROCESS_ID'] . ': ' . ($p['DESCRIPTION'] ?? '')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row" style="margin-bottom:10px;">
                                        <div class="col-xs-6">
                                            <span class="vb-label">Scrap From</span>
                                            <input type="text" class="form-control vb-input-readonly" id="txtScrapFrom" readonly>
                                        </div>
                                        <div class="col-xs-6">
                                            <span class="vb-label">Alloy <span style="color:#ef4444;">* (ต้องมีค่า Alloy)</span></span>
                                            <input type="text" class="form-control vb-input-readonly" id="txtAlloy" readonly placeholder="ดึงอัตโนมัติจาก Reference">
                                        </div>
                                    </div>
                                    <div>
                                        <span class="vb-label">Material In</span>
                                        <input type="text" class="form-control vb-input-readonly" id="txtMaterialIn" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- ฝั่งขวา: น้ำหนักและสถานที่จัดเก็บ -->
                            <div class="col-md-6">
                                <div class="vb-box">
                                    <h5 style="margin-top:0; font-weight:700; color:#1e293b; border-bottom:1px solid #cbd5e1; padding-bottom:8px;">
                                        ⚖️ Weight & Location Control
                                    </h5>
                                    <div class="row" style="margin-bottom:10px;">
                                        <div class="col-xs-6">
                                            <span class="vb-label">Total Scrap Weight (kg) *</span>
                                            <input type="number" step="1" class="form-control" id="txtScrapWeight" value="0" style="font-size:18px; font-weight:700; color:#166534; border:2px solid #86efac;">
                                            <small id="lblWeightNotice" class="text-muted" style="font-size:11px;"></small>
                                        </div>
                                        <div class="col-xs-6">
                                            <span class="vb-label">Move to Location *</span>
                                            <select class="form-control" id="cmbToLocation" style="font-weight:600;">
                                                <option value="">-- เลือก Location --</option>
                                                <?php foreach($locations as $l): ?>
                                                    <option value="<?php echo htmlspecialchars($l['LOCATION_ID']); ?>"><?php echo htmlspecialchars($l['LOCATION_ID']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-top:25px;">
                                        <button type="button" class="btn-action-work" id="btnOpenReferenceModal" style="width:100%; height:45px; font-size:14px;" onclick="openSelectReferenceModal()" disabled>
                                            🔍 คลิกเพื่อเลือกรายการ REFERENCE NO จากระบบ (GROUP LIST)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ตารางรายการ Combine Product -->
                        <div style="margin-top:15px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:15px;">📋 Combine Product (รายการที่ถูกเลือกมารวมกัน)</h4>
                                <span class="badge-job" id="lblItemCount">0 Items Selected</span>
                            </div>
                            <div class="table-responsive table-full-width">
                                <table class="table data-table" id="combine_grid">
                                    <thead>
                                        <tr>
                                            <th style="text-align:center;">Product Ref.</th>
                                            <th style="text-align:center;">Ref. No. (Coil/Batch)</th>
                                            <th style="text-align:center;">Material In</th>
                                            <th style="text-align:center;">Alloy</th>
                                            <th style="text-align:right;">Scrap Wt. (kg)</th>
                                            <th style="text-align:center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="combine_body">
                                        <tr><td colspan="6" align="center" style="color:#94a3b8;">ยังไม่มีรายการ Reference ที่เลือก</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #e2e8f0; padding-top:15px;">
                            <button type="button" class="btn btn-default" onclick="location.reload()">🔄 Cancel / Clear</button>
                            <button type="button" class="btn-action-print" style="padding:10px 30px; font-size:14px;" onclick="submitCombineScrap()">
                                💾 Save & Print Label
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>

<!-- MODAL 1: เลือก Reference -->
<div class="modal fade" id="modalSelectRef" tabindex="-1" role="dialog" style="z-index:99999;">
    <div class="modal-dialog modal-lg" role="document" style="width:90%; max-width:1100px;">
        <div class="modal-content" style="border-radius:10px; overflow:hidden;">
            <div class="modal-header" style="background:#f1f5f9; border-bottom:1px solid #cbd5e1; padding:12px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="font-size:24px;">&times;</button>
                <h4 class="modal-title" style="font-weight:700; color:#1e293b; font-size:16px;">
                    📋 เลือก Reference (<span id="lblModalSourceTitle" class="text-primary"></span>)
                </h4>
            </div>
            <div class="modal-body" style="padding:15px 20px;">
                <div class="alert alert-info py-1 mb-2" style="font-size:12px; margin-bottom:12px;">
                    ℹ️ <strong>วิธีค้นหา:</strong> พิมพ์เลข Coil No, Batch No, หรือ Alloy ในช่องค้นหาขวาบน แล้วกดปุ่ม <strong>"เลือก"</strong> รายการที่ต้องการ (เฉพาะรายการที่มี Alloy สมบูรณ์เท่านั้น)[cite: 5]
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="tblCandidateRef" style="width:100% !important;">
                        <thead>
                            <tr style="background:#e2e8f0; color:#334155;">
                                <th style="text-align:center; width:70px;">ACTION</th>
                                <th style="text-align:center;">REFERENCE NO</th>
                                <th style="text-align:center;">COIL / BATCH</th>
                                <th style="text-align:center;">MATERIAL IN</th>
                                <th style="text-align:center;">ALLOY</th>
                                <th style="text-align:right;">SCRAP WEIGHT (kg)</th>
                            </tr>
                        </thead>
                        <tbody id="candidate_body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:10px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 2: ดูรายละเอียดการ Combine -->
<div class="modal fade" id="modalViewDetail" tabindex="-1" role="dialog" style="z-index:99999;">
    <div class="modal-dialog modal-lg" role="document" style="width:85%; max-width:1000px;">
        <div class="modal-content" style="border-radius:10px; overflow:hidden;">
            <div class="modal-header" style="background:#1e293b; color:#fff; padding:14px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8; font-size:24px;">&times;</button>
                <h4 class="modal-title" style="font-weight:700; font-size:16px;">
                    🔍 รายละเอียดการ Combine ของ Scrap No: <span id="lblDetailScrapNo" class="text-warning"></span>
                </h4>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tblViewDetail" style="width:100% !important;">
                        <thead>
                            <tr style="background:#f1f5f9; color:#475569;">
                                <th style="text-align:center;">Reference Product No</th>
                                <th style="text-align:center;">Parent Coil / Batch</th>
                                <th style="text-align:center;">Material In</th>
                                <th style="text-align:center;">Alloy</th>
                                <th style="text-align:right;">Scrap Wt. (kg)</th>
                                <th style="text-align:center;">Operator</th>
                                <th style="text-align:center;">Operate Date</th>
                            </tr>
                        </thead>
                        <tbody id="view_detail_body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:10px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal">ปิดหน้าต่าง (Close)</button>
            </div>
        </div>
    </div>
</div>

<?php include 'include/footer.php';?>
<script>
let selectedReferences = [];
let currentScrapFromCode = "";

$('#tblHistory').DataTable({
    paging: true,
    pageLength: 10,
    order: [[1, 'desc']] // เรียงจากคอลัมน์ที่ 2 (Date) จากล่าสุดไปเก่าสุด
});

// ส่วนตารางอื่นๆ ยังคงใช้ตามปกติ
$('#combine_grid').DataTable({
    paging: true,
    pageLength: 10,
    order: [[0, 'desc']]
});

<?php if (!empty($print_url)): ?>
    window.open('<?php echo $print_url; ?>', '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
<?php endif; ?>

// ฟังก์ชันค้นหา: ส่งค่าคำค้น + วันที่ From-To
function search_data(){
    var func      = $('#func').val();
    var sno       = $('#scrap_search').val();
    var date_from = $('#date_from').val();
    var date_to   = $('#date_to').val();

    var url = 'combine_scrap_all_process.php?func=' + encodeURIComponent(func) + '&tab=history';
    if(sno) url += '&sno=' + encodeURIComponent(sno);
    if(date_from) url += '&date_from=' + encodeURIComponent(date_from);
    if(date_to) url += '&date_to=' + encodeURIComponent(date_to);

    window.location.assign(url);
}

// ล้างค่าค้นหา
function reset_search(){
    var func = $('#func').val();
    window.location.assign('combine_scrap_all_process.php?func=' + encodeURIComponent(func) + '&tab=history');
}

// ดูรายละเอียด Combine
function viewCombineDetail(productNo) {
    $('#lblDetailScrapNo').text(productNo);
    $('#view_detail_body').html('<tr><td colspan="7" align="center" style="padding:25px;">กำลังโหลดรายละเอียด...</td></tr>');
    $('#modalViewDetail').modal('show');

    fetch('model/save_combine_scrap_mats.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'get_combine_detail',
            productNo: productNo
        })
    })
    .then(r => r.json())
    .then(res => {
        var tbody = $('#view_detail_body');
        tbody.empty();
        if(res.status === 'success' && res.data.length > 0) {
            var sum = 0;
            res.data.forEach(item => {
                var wt = parseFloat(item.SCRP_WEIGHT || 0);
                sum += wt;
                tbody.append(`
                    <tr>
                        <td align="center"><span class="badge-job">${item.PRODUCT_REFERENCE}</span></td>
                        <td align="center">${item.REFERENCE_NO || '-'}</td>
                        <td align="center">${item.MATERIAL_IN || '-'}</td>
                        <td align="center"><span class="label label-primary">${item.ALLOY}</span></td>
                        <td align="right" style="font-weight:700;">${wt.toLocaleString()}</td>
                        <td align="center">${item.CMBN_OPERATOR || '-'}</td>
                        <td align="center" style="color:#64748b;">${item.CMBN_OPERATEDATE ? item.CMBN_OPERATEDATE.substring(0, 19) : '-'}</td>
                    </tr>
                `);
            });
            tbody.append(`
                <tr style="background:#fef3c7; font-weight:700;">
                    <td colspan="4" align="right">รวมน้ำหนักสุทธิ:</td>
                    <td align="right" style="color:#92400e;">${sum.toLocaleString()} kg</td>
                    <td colspan="2"></td>
                </tr>
            `);
        } else {
            tbody.html('<tr><td colspan="7" align="center" style="color:#94a3b8; padding:25px;">ไม่พบรายการย่อยในการ Combine (อาจเป็น Scrap ปกติที่ไม่ผ่านการ Combine)</td></tr>');
        }
    })
    .catch(err => {
        $('#view_detail_body').html('<tr><td colspan="7" align="center" style="color:#ef4444; padding:25px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
    });
}

function onProcessChange() {
    var proc = $('#cmbProcess').val().substr(0, 4);
    var fromText = "";
    currentScrapFromCode = "";

    switch(proc) {
        case "FR01": case "FR02": case "CS01": case "CS02": case "TCFB": case "TCCS":
            fromText = "FB: FURNACE BATCH";
            currentScrapFromCode = "FB";
            break;
        case "CM01": case "SS01": case "BK01": case "BK02": case "BK03": case "CL01": case "XC01": case "AP01": case "AP02": case "KP01": case "TCCM":
            fromText = "CO: COIL";
            currentScrapFromCode = "CO";
            break;
        case "AN02": case "BA03": case "BA04": case "CT01": case "SC01": case "SC02": case "SH02": case "SH05": case "SH06": case "SH08": case "PK01": case "TCFS":
            fromText = "CS: CIRCLE/SHEET";
            currentScrapFromCode = "CS";
            break;
        case "CM1X": case "SS1X": case "AP1X": case "AP2X": case "KP1X":
            fromText = "SC: SCRAP";
            currentScrapFromCode = "SC";
            break;
    }

    $('#txtScrapFrom').val(fromText);
    selectedReferences = [];
    renderGrid();
    $('#txtAlloy').val('');
    $('#txtMaterialIn').val('');

    if(currentScrapFromCode !== "") {
        $('#btnOpenReferenceModal').prop('disabled', false);
        if(currentScrapFromCode === "SC") {
            $('#txtScrapWeight').prop('readonly', true).css('background', '#f1f5f9');
            $('#lblWeightNotice').text('* รวมน้ำหนักจาก Scrap ลูกให้อัตโนมัติ');
        } else {
            $('#txtScrapWeight').prop('readonly', false).css('background', '#ffffff');
            $('#lblWeightNotice').text('* กรุณากรอกน้ำหนัก Scrap รวม');
        }
    } else {
        $('#btnOpenReferenceModal').prop('disabled', true);
    }
}

function openSelectReferenceModal() {
    var proc = $('#cmbProcess').val();
    var alloy = $('#txtAlloy').val();

    $('#lblModalSourceTitle').text($('#txtScrapFrom').val());

    if ($.fn.DataTable.isDataTable('#tblCandidateRef')) {
        $('#tblCandidateRef').DataTable().clear().destroy();
    }
    $('#candidate_body').html('<tr><td colspan="6" align="center" style="padding:25px;">กำลังค้นหาข้อมูลที่ตรงเงื่อนไข...</td></tr>');
    $('#modalSelectRef').modal('show');

    fetch('model/save_combine_scrap_mats.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'get_candidate_list',
            scrapFrom: currentScrapFromCode,
            process: proc,
            currentAlloy: alloy
        })
    })
    .then(r => r.json())
    .then(res => {
        var tbody = $('#candidate_body');
        tbody.empty();
        if(res.status === 'success' && res.data.length > 0) {
            var rowsHtml = '';
            res.data.forEach(item => {
                var isSelected = selectedReferences.some(x => x.refNo === item.REF_NO);
                if(!isSelected) {
                    var jsonStr = JSON.stringify(item).replace(/'/g, "&apos;");
                    rowsHtml += `
                        <tr>
                            <td align="center">
                                <button type="button" class="btn btn-xs btn-success" style="font-weight:700;" onclick='pickReference(${jsonStr})'>
                                    ✔ เลือก
                                </button>
                            </td>
                            <td align="center"><strong>${item.REF_NO}</strong></td>
                            <td align="center">${item.COIL_NO || '-'}</td>
                            <td align="center">${item.MATERIAL_IN || '-'}</td>
                            <td align="center"><span class="badge-job">${item.ALLOY}</span></td>
                            <td align="right" style="font-weight:600;">${Number(item.SCRP_WEIGHT || 0).toLocaleString()}</td>
                        </tr>
                    `;
                }
            });
            tbody.html(rowsHtml);
        } else {
            tbody.html('<tr><td colspan="6" align="center" style="color:#94a3b8; padding:20px;">ไม่พบรายการที่พร้อมใช้งานตามเงื่อนไข Process/Alloy</td></tr>');
        }

        $('#tblCandidateRef').DataTable({
            pageLength: 8,
            lengthMenu: [[8, 15, 30, 50], [8, 15, 30, 50]],
            order: [[1, 'desc']],
            autoWidth: false,
            language: {
                search: "🔍 ค้นหาด่วน:",
                searchPlaceholder: "ใส่เลขที่ Coil, Batch, Alloy...",
                lengthMenu: "แสดง _MENU_ รายการ",
                info: "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ",
                zeroRecords: "ไม่พบข้อมูล",
                paginate: { previous: "ก่อนหน้า", next: "ถัดไป" }
            }
        });
    })
    .catch(err => {
        $('#candidate_body').html('<tr><td colspan="6" align="center" style="color:#ef4444; padding:20px;">เกิดข้อผิดพลาดในการดึงข้อมูล</td></tr>');
    });
}

function pickReference(item) {
    var refAlloy = (item.ALLOY || '').trim();

    if (!refAlloy || refAlloy.toUpperCase() === 'NONE') {
        alert("ข้อผิดพลาด: รายการนี้ไม่มีข้อมูล Alloy หรือ Alloy เป็น 'NONE' ซึ่งไม่อนุญาตให้ใช้ทำ Scrap[cite: 5]");
        return;
    }

    var curAlloy = $('#txtAlloy').val().trim();
    if(curAlloy !== "" && curAlloy !== refAlloy) {
        alert("Alloy ไม่ตรงกัน! ไม่สามารถรวมกับ Scrap หลักได้ (ปัจจุบันเป็น " + curAlloy + " แต่ที่เลือกมาเป็น " + refAlloy + ")[cite: 5]");
        return;
    }

    if(selectedReferences.length === 0) {
        $('#txtAlloy').val(refAlloy);
        $('#txtMaterialIn').val(item.MATERIAL_IN || 'NONE-BOI');
    }

    selectedReferences.push({
        refNo: item.REF_NO,
        coilNo: item.COIL_NO || item.REF_NO,
        materialIn: item.MATERIAL_IN || 'NONE-BOI',
        alloy: refAlloy,
        weight: parseFloat(item.SCRP_WEIGHT) || 0
    });

    renderGrid();
    $('#modalSelectRef').modal('hide');
}

function renderGrid() {
    var tbody = $('#combine_body');
    tbody.empty();
    $('#lblItemCount').text(selectedReferences.length + ' Items Selected');

    if(selectedReferences.length === 0) {
        tbody.append('<tr><td colspan="6" align="center" style="color:#94a3b8;">ยังไม่มีรายการ Reference ที่เลือก</td></tr>');
        if(currentScrapFromCode === "SC") $('#txtScrapWeight').val(0);
        return;
    }

    var sumWeight = 0;
    selectedReferences.forEach((item, idx) => {
        sumWeight += item.weight;
        tbody.append(`
            <tr>
                <td align="center"><span class="badge-job">${item.refNo}</span></td>
                <td align="center">${item.coilNo}</td>
                <td align="center">${item.materialIn}</td>
                <td align="center"><span class="label label-primary">${item.alloy}</span></td>
                <td align="right" style="font-weight:700;">${item.weight.toLocaleString()}</td>
                <td align="center">
                    <button type="button" class="btn-action-del" onclick="removeItem(${idx})">🗑️ ลบ</button>
                </td>
            </tr>
        `);
    });

    if(currentScrapFromCode === "SC") {
        $('#txtScrapWeight').val(sumWeight);
    }
}

function removeItem(idx) {
    selectedReferences.splice(idx, 1);
    if(selectedReferences.length === 0) {
        $('#txtAlloy').val('');
        $('#txtMaterialIn').val('');
    }
    renderGrid();
}

function submitCombineScrap() {
    var proc  = $('#cmbProcess').val();
    var loc   = $('#cmbToLocation').val();
    var wt    = parseFloat($('#txtScrapWeight').val()) || 0;
    var alloy = $('#txtAlloy').val().trim();
    var matIn = $('#txtMaterialIn').val();

    if(!proc) { alert('กรุณาเลือก Process'); return; }
    if(!loc)  { alert('กรุณาเลือก Move to Location'); return; }
    if(selectedReferences.length === 0) { alert('กรุณาเลือก Reference No. อย่างน้อย 1 รายการ'); return; }
    if(!alloy || alloy.toUpperCase() === 'NONE') {
        alert('ข้อผิดพลาด: ข้อมูล Alloy ไม่ถูกต้อง หรือมีค่าเป็น NONE (ไม่อนุญาตให้บันทึก Scrap โดยไม่มี Alloy)[cite: 5]');
        return;
    }
    if(wt <= 0) { alert('น้ำหนัก Scrap ต้องมากกว่า 0'); return; }

    fetch('model/save_combine_scrap_mats.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save_combine_full',
            process: proc,
            scrapFrom: currentScrapFromCode,
            locationId: loc,
            totalWeight: wt,
            alloy: alloy,
            materialIn: matIn,
            references: selectedReferences
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            alert('บันทึกสำเร็จ! เลขที่ Scrap: ' + res.product_no);
            var printUrl = 'print_scrap_product_label_mats.php?coilno=' + encodeURIComponent(res.product_no);
            var func = $('#func').val();
            window.location.assign('combine_scrap_all_process.php?func=' + encodeURIComponent(func) + '&tab=history&print_url=' + encodeURIComponent(printUrl));
        } else {
            alert('Error: ' + res.message);
        }
    })
    .catch(err => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ Server'));
}

function previewPrintLabel(productNo) {
    if (!productNo) return;
    const targetUrl = 'print_scrap_product_label_mats.php?coilno=' + encodeURIComponent(productNo);
    window.open(targetUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
}
</script>
</body>
</html>