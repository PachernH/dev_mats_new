<?php
// scrap_produce_mats.php
session_start();
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : 'M';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include 'dbcon_mats-new.php';

$cno        = !isset($_GET['cno']) ? '' : htmlspecialchars(trim($_GET['cno']), ENT_QUOTES, 'UTF-8');
$print_url  = isset($_GET['print_url']) ? $_GET['print_url'] : '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// ดึง Master Data สำหรับ Dropdown ใน Modal
$stmtAlloy = $conn->query("SELECT DISTINCT ALLOY FROM CMPSMSTR1 WHERE ALLOY IS NOT NULL ORDER BY ALLOY ASC");
$alloys = $stmtAlloy->fetchAll(PDO::FETCH_ASSOC);

$stmtLocation = $conn->query("SELECT DISTINCT LOCATION_ID FROM LCTNMSTR1 WHERE LOCATION_ID IS NOT NULL ORDER BY LOCATION_ID ASC");
$locations = $stmtLocation->fetchAll(PDO::FETCH_ASSOC);
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
        
        /* Tabs Styling */
        .nav-tabs-custom { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { background: #f1f5f9; color: #64748b; font-weight: 700; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; font-size: 13.5px; transition: all 0.2s; }
        .tab-btn.active { background: #2563eb; color: #fff; box-shadow: 0 2px 4px rgba(37,99,235,0.2); }
        .tab-btn:hover { background: #e2e8f0; color: #334155; text-decoration: none; }
        .tab-btn.active:hover { background: #1d4ed8; color: #fff; }

        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 38px; font-size: 14px; font-weight: 600; color: #1e293b; width: 100%; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; height: 38px; padding: 0 20px; border-radius: 8px; border: none; cursor: pointer; }
        
        .table-responsive { border: none !important; margin-top: 5px; }
        .data-table thead th { background-color: #f1f5f9; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11.5px; padding: 10px 8px; border-bottom: 2px solid #cbd5e1; }
        .data-table tbody td { padding: 10px 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-table tbody tr:hover td { background-color: #f0f9ff !important; }
        
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bfdbfe; font-size: 12px; display: inline-block; }
        .badge-pno { background-color: #f0fdf4; color: #166534; font-weight: 700; padding: 4px 10px; border-radius: 20px; border: 1px solid #bbf7d0; font-size: 12px; display: inline-block; }
        
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-work:hover { background-color: #047857; }
        .btn-action-u { background-color: #d97706; color: #ffffff; font-weight: 700; padding: 6px 14px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-u:hover { background-color: #b45309; }
        .btn-action-print { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-action-print:hover { background-color: #1d4ed8; color: #fff; text-decoration: none; }
        .btn-action-del { background-color: #dc2626; color: #ffffff; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: none; font-size: 12px; cursor: pointer; }
        .btn-action-del:hover { background-color: #b91c1c; }

        /* Custom Modal Form */
        .custom-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .custom-modal-box {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .modal-header-custom {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-body-custom { padding: 20px; }
        .modal-footer-custom {
            padding: 12px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A4';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Scrap Production Process</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Tabs Menu -->
            <div class="nav-tabs-custom">
                <a href="scrap_produce_mats.php?func=<?php echo $folder_func; ?>&tab=pending" class="tab-btn <?php echo ($active_tab == 'pending') ? 'active' : ''; ?>">
                    📥 Available Coils (Pending Scrap)
                </a>
                <a href="scrap_produce_mats.php?func=<?php echo $folder_func; ?>&tab=history" class="tab-btn <?php echo ($active_tab == 'history') ? 'active' : ''; ?>">
                    📋 History Scrap Product / Reprint Label
                </a>
            </div>

            <!-- Search Bar -->
            <div class="filter-wrapper">
                <div style="flex: 1; min-width: 220px;">
                    <label style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">
                        <?php echo ($active_tab == 'history') ? 'Search Scrap No. / Coil No.' : 'Search Coil No.'; ?>
                    </label>
                    <input class="form-control-minimal" id="coil_search" type="text" placeholder="Enter keyword..." value="<?php echo $cno; ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_data()"/>
                </div>
                <div>
                    <button class="btn-search" onclick="search_data()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <?php if ($active_tab == 'pending'): ?>
                            <!-- TAB 1: AVAILABLE COILS -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">📑 Active Coils (Residual Balance > 0)</h4>
                                <button type="button" class="btn-action-u" onclick="openScrapModal('U')">+ Record Scrap (Type U)</button>
                            </div>
                            <div class="table-responsive table-full-width">
                                <table id="pending_table" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Coil No</th>
                                            <th style="text-align: center;">Material In</th>
                                            <th style="text-align: center;">Alloy</th>
                                            <th style="text-align: right;">Width</th>
                                            <th style="text-align: right;">Actual Weight</th>
                                            <th style="text-align: right;">Scrap Weight</th>
                                            <th style="text-align: right;">Balance Weight</th>
                                            <th style="text-align: center;">Status</th>
                                            <th style="text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $params = [];
                                        $where = "(COIL_STATUS = 'AC' OR COIL_STATUS = 'CL') AND COIL_BALANCEWEIGHT > 0";
                                        if (!empty($cno)) {
                                            $where .= " AND COIL_NO LIKE :cno";
                                            $params[':cno'] = '%' . $cno . '%';
                                        }

                                        $sql = "SELECT TOP 100 COIL_NO, MATERIAL_IN, ALLOY, WIDTH, COIL_ACTUALWEIGHT, COIL_SCRAPWEIGHT, COIL_BALANCEWEIGHT, COIL_STATUS 
                                                FROM COILPROD1 
                                                WHERE {$where} 
                                                ORDER BY COIL_NO DESC";
                                        $stmt = $conn->prepare($sql);
                                        $stmt->execute($params);

                                        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $coil_no = htmlspecialchars($row['COIL_NO'], ENT_QUOTES, 'UTF-8');
                                            $mat_in  = htmlspecialchars($row['MATERIAL_IN'] ?? '-', ENT_QUOTES, 'UTF-8');
                                            $alloy   = htmlspecialchars($row['ALLOY'], ENT_QUOTES, 'UTF-8');
                                            $act_wt  = (float)$row['COIL_ACTUALWEIGHT'];
                                            $bal_wt  = (float)$row['COIL_BALANCEWEIGHT'];

                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-job'>🔹 {$coil_no}</span></td>";
                                            echo "<td align='center'>{$mat_in}</td>";
                                            echo "<td align='center'>{$alloy}</td>";
                                            echo "<td align='right'>".number_format((float)$row['WIDTH'], 2)."</td>";
                                            echo "<td align='right'>".number_format($act_wt, 0)." kg</td>";
                                            echo "<td align='right'>".number_format((float)$row['COIL_SCRAPWEIGHT'], 0)." kg</td>";
                                            echo "<td align='right' style='font-weight:700; color:#dc2626;'>".number_format($bal_wt, 0)." kg</td>";
                                            echo "<td align='center'><span class='label label-success'>".htmlspecialchars($row['COIL_STATUS'], ENT_QUOTES, 'UTF-8')."</span></td>";
                                            echo "<td align='center'>";
                                            echo "<button type='button' class='btn-action-work' onclick=\"selectCoil('{$coil_no}', '{$mat_in}', '{$alloy}', {$act_wt}, {$bal_wt})\">✂️ PRODUCE</button>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <!-- TAB 2: SCRAP HISTORY & REPRINT -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">📦 History Scrap Product / Reprint Label</h4>
                            </div>
                            <div class="table-responsive table-full-width">
                                <table id="history_table" class="table data-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Product No</th>
                                            <th style="text-align: center;">Coil No</th>
                                            <th style="text-align: center;">Scrap Date</th>
                                            <th style="text-align: center;">Material In</th>
                                            <th style="text-align: center;">Alloy</th>
                                            <th style="text-align: right;">Scrap Weight</th>
                                            <th style="text-align: center;">Location</th>
                                            <th style="text-align: center;">Operator</th>
                                            <th style="text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $params_h = [];
                                        $where_h = "1=1";
                                        if (!empty($cno)) {
                                            $where_h .= " AND (PRODUCT_NO LIKE :cno OR COIL_NO LIKE :cno)";
                                            $params_h[':cno'] = '%' . $cno . '%';
                                        }

                                        $sql_h = "SELECT TOP 100 PRODUCT_NO, COIL_NO, SCRP_DATE, MATERIAL_IN, ALLOY, SCRP_WEIGHT, LOCATION_ID, SCRP_OPERATOR 
                                                  FROM SCRPPROD1 
                                                  WHERE {$where_h} 
                                                  ORDER BY SCRP_OPERATEDATE DESC";
                                        $stmt_h = $conn->prepare($sql_h);
                                        $stmt_h->execute($params_h);

                                        while($row_h = $stmt_h->fetch(PDO::FETCH_ASSOC)) {
                                            $pno = htmlspecialchars($row_h['PRODUCT_NO'], ENT_QUOTES, 'UTF-8');
                                            $coil = htmlspecialchars($row_h['COIL_NO'] ?: '-', ENT_QUOTES, 'UTF-8');
                                            $dt   = !empty($row_h['SCRP_DATE']) ? date('Y-m-d', strtotime($row_h['SCRP_DATE'])) : '-';
                                            $wt   = (float)$row_h['SCRP_WEIGHT'];

                                            echo "<tr>";
                                            echo "<td align='center'><span class='badge-pno'>📦 {$pno}</span></td>";
                                            echo "<td align='center'><span class='badge-job'>🔹 {$coil}</span></td>";
                                            echo "<td align='center' style='color:#64748b;'>{$dt}</td>";
                                            echo "<td align='center'>".htmlspecialchars($row_h['MATERIAL_IN'] ?: '-', ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='center'>".htmlspecialchars($row_h['ALLOY'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='right' style='font-weight:700;'>".number_format($wt, 0)." kg</td>";
                                            echo "<td align='center'>".htmlspecialchars($row_h['LOCATION_ID'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='center'>".htmlspecialchars($row_h['SCRP_OPERATOR'], ENT_QUOTES, 'UTF-8')."</td>";
                                            echo "<td align='center'>";
                                            echo "<button type='button' class='btn-action-print' onclick=\"previewPrintLabel('{$pno}')\">🖨️ REPRINT</button> ";
                                            echo "<button type='button' class='btn-action-del' onclick=\"deleteScrap('{$pno}', '{$coil}', {$wt})\">🗑️ DELETE</button>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <input type="hidden" id="active_tab" value="<?php echo $active_tab;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>

<!-- MODAL INPUT SCRAP DATA -->
<div id="modalScrap" class="custom-modal-backdrop">
    <div class="custom-modal-box">
        <div class="modal-header-custom">
            <h4 id="modalTitle" style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">Produce Scrap</h4>
            <span style="font-size:20px; cursor:pointer; font-weight:bold; color:#64748b;" onclick="closeModal()">&times;</span>
        </div>

        <form id="formScrap" onsubmit="event.preventDefault(); saveScrap();">
            <input type="hidden" id="scrapType" name="scrapType">
            <input type="hidden" id="lineProcess" name="lineProcess" value="<?php echo $line_func; ?>">

            <div class="modal-body-custom">
                <div class="row">
                    <div class="col-xs-6" id="boxCoilNo" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">COIL NO</label>
                        <input type="text" class="form-control" id="coilNo" readonly style="background:#f1f5f9; font-weight:700;">
                    </div>
                    <div class="col-xs-6" id="boxMaterialIn" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">MATERIAL IN</label>
                        <input type="text" class="form-control" id="materialIn" readonly style="background:#f1f5f9;">
                    </div>

                    <div class="col-xs-6" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">ALLOY <span style="color:#ef4444;">*</span></label>
                        <select class="form-control" id="alloy" required>
                            <option value="">-- Select Alloy --</option>
                            <?php foreach($alloys as $a): ?>
                                <option value="<?php echo htmlspecialchars($a['ALLOY'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($a['ALLOY'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-xs-6" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">DESTINATION LOCATION <span style="color:#ef4444;">*</span></label>
                        <select class="form-control" id="toLocation" required>
                            <option value="">-- Select Location --</option>
                            <?php foreach($locations as $l): ?>
                                <option value="<?php echo htmlspecialchars($l['LOCATION_ID'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($l['LOCATION_ID'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-xs-12" id="boxBalanceRef" style="margin-bottom:12px;">
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:8px 12px; border-radius:6px; font-size:13px;">
                            Actual Weight: <strong id="lblActual">0</strong> kg | Current Balance: <strong id="lblBalance" style="color:#2563eb;">0</strong> kg
                        </div>
                    </div>

                    <div class="col-xs-6" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">SCRAP WEIGHT (kg) <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="1" class="form-control" id="scrapWeight" required style="font-size:16px; font-weight:700; border-color:#3b82f6;" oninput="validateWeight()">
                        <small id="weightWarning" style="color:#ef4444; font-weight:700; display:none;">* Scrap weight exceeds Balance!</small>
                    </div>

                    <div class="col-xs-6" id="boxNewBalance" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">ESTIMATED BALANCE</label>
                        <input type="text" class="form-control" id="calcBalance" readonly style="background:#f1f5f9; font-weight:700;">
                    </div>

                    <div class="col-xs-12" style="margin-bottom:12px;">
                        <label style="font-size:12px; font-weight:700; color:#64748b;">REMARKS</label>
                        <input type="text" class="form-control" id="remark" maxlength="50" placeholder="Optional notes...">
                    </div>
                </div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" class="btn btn-default" onclick="closeModal()">Cancel</button>
                <button type="submit" id="btnSubmitScrap" class="btn btn-success" style="font-weight:700;">💾 Save Record</button>
            </div>
        </form>
    </div>
</div>

<?php include 'include/footer.php';?>
<script>
let selectedBalance = 0;

$('.data-table').DataTable({
    paging: true,
    pageLength: 10,
    lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, "All"]],
    order: [[0, 'desc']]
});

// เปิด Popup Print อัตโนมัติเมื่อ redirect พร้อม print_url
<?php if (!empty($print_url)): ?>
    window.open('<?php echo $print_url; ?>', '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
<?php endif; ?>

function search_data(){
    var func = $('#func').val();
    var cno = $('#coil_search').val();
    var tab = $('#active_tab').val();
    window.location.assign('scrap_produce_mats.php?func=' + encodeURIComponent(func) + '&tab=' + encodeURIComponent(tab) + '&cno=' + encodeURIComponent(cno));
}

function selectCoil(coilNo, matIn, alloy, actualWt, balanceWt) {
    openScrapModal('S', {
        COIL_NO: coilNo,
        MATERIAL_IN: matIn,
        ALLOY: alloy,
        COIL_ACTUALWEIGHT: actualWt,
        COIL_BALANCEWEIGHT: balanceWt
    });
}

function openScrapModal(type, coilData = null) {
    $('#formScrap')[0].reset();
    document.getElementById('scrapType').value = type;
    $('#weightWarning').hide();
    $('#btnSubmitScrap').prop('disabled', false);

    if (type === 'U') {
        $('#modalTitle').text('Record Scrap (Type U - Unidentified)');
        $('#boxCoilNo, #boxMaterialIn, #boxBalanceRef, #boxNewBalance').hide();
        $('#alloy').prop('disabled', false);
        selectedBalance = 0;
    } else {
        $('#modalTitle').text('Produce Scrap from Coil: ' + coilData.COIL_NO);
        $('#boxCoilNo, #boxMaterialIn, #boxBalanceRef, #boxNewBalance').show();
        
        $('#coilNo').val(coilData.COIL_NO);
        $('#materialIn').val(coilData.MATERIAL_IN || '');
        $('#alloy').val(coilData.ALLOY || '').prop('disabled', true);
        
        selectedBalance = parseFloat(coilData.COIL_BALANCEWEIGHT) || 0;
        $('#lblActual').text(Number(coilData.COIL_ACTUALWEIGHT).toLocaleString());
        $('#lblBalance').text(selectedBalance.toLocaleString());
        $('#calcBalance').val(selectedBalance.toLocaleString() + ' kg');
    }
    $('#modalScrap').css('display', 'flex');
}

function closeModal() {
    $('#modalScrap').hide();
}

function validateWeight() {
    const type = document.getElementById('scrapType').value;
    const wt = parseFloat($('#scrapWeight').val()) || 0;

    if (type === 'S') {
        const newBal = selectedBalance - wt;
        $('#calcBalance').val(newBal.toLocaleString() + ' kg');

        if (newBal < 0 || wt <= 0) {
            $('#weightWarning').show();
            $('#btnSubmitScrap').prop('disabled', true);
        } else {
            $('#weightWarning').hide();
            $('#btnSubmitScrap').prop('disabled', false);
        }
    }
}

function saveScrap() {
    const payload = {
        action: 'save',
        scrapType: $('#scrapType').val(),
        lineProcess: $('#lineProcess').val(),
        coilNo: $('#coilNo').val(),
        materialIn: $('#materialIn').val(),
        alloy: $('#alloy').val(),
        toLocation: $('#toLocation').val(),
        scrapWeight: parseFloat($('#scrapWeight').val()),
        remark: $('#remark').val()
    };

    $('#btnSubmitScrap').prop('disabled', true);

    fetch('model/save_scrap_produce_mats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            closeModal();
            var func = $('#func').val();
            var printUrl = 'print_scrap_product_label_mats.php?coilno=' + encodeURIComponent(data.product_no);
            // Redirect ไปที่แท็บ history พร้อมพารามิเตอร์สั่งพิมพ์
            window.location.assign('scrap_produce_mats.php?func=' + encodeURIComponent(func) + '&tab=history&print_url=' + encodeURIComponent(printUrl));
        } else {
            alert('Error: ' + data.message);
            $('#btnSubmitScrap').prop('disabled', false);
        }
    })
    .catch(err => {
        alert('Server Connection Error.');
        $('#btnSubmitScrap').prop('disabled', false);
    });
}

function previewPrintLabel(productNo) {
    if (!productNo) return;
    const targetUrl = 'print_scrap_product_label_mats.php?coilno=' + encodeURIComponent(productNo);
    window.open(targetUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
}

function deleteScrap(productNo, coilNo, weight) {
    if(!confirm('Are you sure you want to delete scrap [' + productNo + ']?\n(' + weight + ' kg will be restored to Parent Coil)')) {
        return;
    }

    fetch('model/save_scrap_produce_mats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', productNo: productNo, coilNo: coilNo, scrapWeight: weight })
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Scrap record deleted successfully.');
            search_data();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Server connection error.'));
}
</script>
</body>
</html>