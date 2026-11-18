<?php
// เริ่ม session ก่อน ANY output
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include('dbcon_mats-new.php');

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$datetime = date('Y-m-d H:i:s');

// ดึงข้อมูลเดิมจาก FRNCPRCS1 และ CHRGTRNS1
$row_data = [];
$pre_selected_ingots = [];
if(!empty($bno)){
    $sql_fetch = "SELECT A.*, B.CTRN_REMARK 
                  FROM FRNCPRCS1 A 
                  LEFT JOIN CHRGTRNS1 B ON A.BATCH_NO = B.BATCH_NO 
                  WHERE A.BATCH_NO = :bno";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->execute([':bno' => $bno]);
    $row_data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    // ดึงรายการ Ingot ที่เคยผูกไว้กับ Batch นี้จาก WIP_ISSUE_TRANSACTIONS
    $sql_pre_ingot = "SELECT d.req_detail_id 
                      FROM WIP_ISSUE_TRANSACTIONS w
                      INNER JOIN REQUISITION_DETAIL d 
                        ON LTRIM(RTRIM(w.container_no)) = LTRIM(RTRIM(d.container_no)) 
                        AND LTRIM(RTRIM(w.packing_no)) = LTRIM(RTRIM(d.packing_no))
                      WHERE w.issue_no = :bno";
    $stmt_pre = $conn->prepare($sql_pre_ingot);
    $stmt_pre->execute([':bno' => $bno]);
    while($p_row = $stmt_pre->fetch(PDO::FETCH_ASSOC)){
        $pre_selected_ingots[] = intval($p_row['req_detail_id']);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Edit Charging Material - Meyer Aluminium</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .form-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; }
        .btn-custom-submit { background-color: #f59e0b; color: #ffffff; border: none; padding: 12px 28px; font-weight: 600; border-radius: 8px; }
        .btn-custom-submit:hover { background-color: #d97706; }
        .bg-total-charge { background-color: #eff6ff !important; font-weight: 700; color: #1e40af; }
        .bg-balance-furnace { background-color: #ecfdf5 !important; font-weight: 700; color: #065f46; }
        .wip-table thead th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; padding: 10px; vertical-align: middle; }
        .wip-table tbody td { vertical-align: middle; padding: 8px 10px; }
        .badge-country { background-color: #e0f2fe; color: #0284c7; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="furnance_mats.php?func=<?php echo $folder_func ?>">Furnace Charging Material</a>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('furnance_mats.php?func=<?php echo $folder_func; ?>')"> ⬅️ Back to Charging Material</button>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <div class="form-card">
                        <h4 class="card-title-sub">📋 General Information (EDITING)</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Charging Date</label>
                                    <input class="form-control" id="Charg_date" type="date" value="<?php echo isset($row_data['BATCH_DATE']) ? date('Y-m-d', strtotime($row_data['BATCH_DATE'])) : ''; ?>" readonly/>
                                    <input id="Charg_date_show" type="hidden" value="<?php echo $datetime; ?>"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Furnace No.</label>
                                    <input class="form-control" id="furnace_no" value="<?php echo htmlspecialchars($row_data['FURNACE'] ?? ''); ?>" readonly />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Batch No (READ-ONLY)</label>
                                    <input class="form-control" id="batch_no" type="text" value="<?php echo htmlspecialchars($row_data['BATCH_NO'] ?? ''); ?>" readonly style="font-weight: 700; color: #2563eb;"/> 
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Material</label>
                                    <select class="form-control" id="material_name" onchange="handleMaterialChange()">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT BOI_PROJECT FROM BOIPRJCT1 
                                                    WHERE BOI_PROJECT <> 'BOI-IMP1' and BOI_PROJECT <> 'BOI-IMP2'
                                                    ORDER BY BOIPRJCT1.BOI_PROJECT ASC";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['MATERIAL_IN'] ?? '') == $row['BOI_PROJECT'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["BOI_PROJECT"])."' $selected>".htmlspecialchars($row["BOI_PROJECT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-card">
                        <h4 class="card-title-sub">🧪 Specifications & Charging Materials</h4>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Alloy</label>
                                    <select class="form-control" id="alloy">
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT ALLOY FROM CMPSMSTR1 GROUP BY ALLOY";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = ($row_data['ALLOY'] ?? '') == $row['ALLOY'] ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALLOY"])."' $selected>".htmlspecialchars($row["ALLOY"])."</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Primary Smelt (Disabled + Hidden Input) -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Primary Smelt</label>
                                    <select class="form-control" id="primary_smelt" disabled>
                                        <option value=""></option>
                                        <?php
                                            $sql = "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY SEQ_NO ASC";
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = (!empty($row_data['PRIMARY_SMELT']) && trim($row_data['PRIMARY_SMELT']) == trim($row['ALUMINIUM_SMELT'])) ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"])."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                    <input type="hidden" id="hidden_primary_smelt" value="<?php echo htmlspecialchars($row_data['PRIMARY_SMELT'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Secondary Smelt (Disabled + Hidden Input) -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Secondary Smelt</label>
                                    <select class="form-control" id="seconday_smelt" disabled>
                                        <option value=""></option>
                                        <?php
                                            $result = $conn->query($sql);
                                            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                $selected = (!empty($row_data['SECONDARY_SMELT']) && trim($row_data['SECONDARY_SMELT']) == trim($row['ALUMINIUM_SMELT'])) ? 'selected' : '';
                                                echo "<option value='".htmlspecialchars($row["ALUMINIUM_SMELT"])."' $selected>".htmlspecialchars($row["ALUMINIUM_SMELT"])."</option>";
                                            }
                                        ?>
                                    </select>
                                    <input type="hidden" id="hidden_seconday_smelt" value="<?php echo htmlspecialchars($row_data['SECONDARY_SMELT'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Remain From Last Batch (Kg)</label><input class="form-control" id="remain_form" type="number" value="<?php echo $row_data['AL_REMAIN'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            
                            <!-- Charge AL Ingot เลือกจาก WIP Stock Modal -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Charge AL Ingot (Kg) <span style="color:#ef4444;">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control" id="charge_ingot" type="number" value="<?php echo $row_data['AL_INGOT'] ?? 0; ?>" readonly placeholder="Click to select WIP..." style="background-color:#f8fafc; font-weight:700; color:#1e40af;"/> 
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-info" onclick="openIngotModal()" style="height: 42px; border-radius: 0 8px 8px 0; font-weight: 600;">📦 Select WIP</button>
                                        </span>
                                    </div>
                                    <small id="selected_ingot_count_text" style="color: #64748b; font-size: 11.5px;"></small>
                                </div>
                            </div>

                            <div class="col-md-6"><div class="form-group"><label>Charge Product Remelt (Kg)</label><input class="form-control" id="charge_remelt" type="number" value="<?php echo $row_data['COIL_REMELT'] ?? 0; ?>" onkeyup="calculateTotal()" readonly/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Casting Scrap (Kg)</label><input class="form-control" id="charge_casting" type="number" value="<?php echo $row_data['CAST_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Other Scrap (Kg)</label><input class="form-control" id="charge_other" type="number" value="<?php echo $row_data['OTHER_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Scrap Wire (Kg)</label><input class="form-control" id="charge_wire" type="number" value="<?php echo $row_data['SCRAP_WIRE'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Recycle Scrap (Kg)</label><input class="form-control" id="charge_recycle" type="number" value="<?php echo $row_data['RECYCLE_SCRAP'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Charge Master Alloy (Kg)</label><input class="form-control" id="charge_alloy" type="number" value="<?php echo $row_data['MASTER_ALLOY'] ?? 0; ?>" onkeyup="calculateTotal()"/></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-md-12">
                    <div class="form-card" style="border-left: 4px solid #f59e0b;">
                        <h4 class="card-title-sub">📊 Weight Summary Calculations</h4>
                        <div class="form-group mb-4">
                            <label>Total AL Charging (Kg)</label>
                            <input class="form-control bg-total-charge" id="total_charge" type="number" value="<?php echo $row_data['TOTAL_CHARGE'] ?? 0; ?>" readonly/> 
                        </div>
                        <div class="form-group mb-4">
                            <label>Total Dross (Kg)</label>
                            <input class="form-control" id="total_dross" type="number" value="<?php echo $row_data['TOTAL_DROSS'] ?? 0; ?>" onkeyup="calculateBalance()"/> 
                        </div>      
                        <div class="form-group mb-4">
                            <label>Balance in Furnace (Kg)</label>
                            <input class="form-control bg-balance-furnace" id="balance_furnace" type="number" value="<?php echo $row_data['BALANCE_BATCH'] ?? 0; ?>" readonly/> 
                        </div>           
                    </div>

                    <div class="form-card" style="border-left: 4px solid #f59e0b;">
                        <h4 class="card-title-sub">✍️ Additional Remarks</h4>
                        <div class="form-group">
                            <label>Remark / Comments</label>
                            <textarea class="form-control" id="select_detail" rows="4"><?php echo htmlspecialchars($row_data['CTRN_REMARK'] ?? ''); ?></textarea>
                        </div>                       
                        <input id="func" value="<?php echo $folder_func; ?>" type="hidden"/>
                        <input type="hidden" id="selected_ingot_items" value='<?php echo json_encode($pre_selected_ingots); ?>' />
                        
                        <div class="text-right">
                            <button type="button" class="btn btn-custom-submit" id="btn_update_code" onclick="update_code()">
                                Update Data
                            </button>        
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- Modal เลือก AL Ingot จาก WIP Stock -->
<div class="modal fade" id="ingotModal" tabindex="-1" role="dialog" aria-labelledby="ingotModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width: 85%;">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ingotModalLabel" style="font-weight:700; color:#1e293b;">📦 Select Charge AL Ingot from WIP Stock</h4>
            </div>
            <div class="modal-body">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-4"><label style="font-size: 12px; color: #64748b;">Container No.</label><input type="text" id="filterContainer" class="form-control" placeholder="Search..." onkeyup="renderIngotTable()"></div>
                    <div class="col-md-4"><label style="font-size: 12px; color: #64748b;">Packing No.</label><input type="text" id="filterPacking" class="form-control" placeholder="Search..." onkeyup="renderIngotTable()"></div>
                    <div class="col-md-4"><label style="font-size: 12px; color: #64748b;">Country</label><select id="filterCountry" class="form-control" onchange="renderIngotTable()"><option value="">All Countries</option></select></div>
                </div>
                <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 16px; border-radius: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 14px; font-weight: 600; color: #166534;">Selected: <span id="modal_selected_count" class="badge" style="background-color: #15803d;">0</span> items</span>
                        <span style="margin-left: 15px; font-size: 14px; font-weight: 700; color: #166534;">Total Net Weight: <span id="modal_selected_weight">0.00</span> Kg</span>
                    </div>
                    <button type="button" class="btn btn-xs btn-default" onclick="clearAllIngotSelection()">Clear All</button>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover wip-table" id="tableIngot">
                        <thead>
                            <tr>
                                <th style="text-align: center; width: 50px;"><input type="checkbox" id="check_all_ingot" onclick="toggleSelectAllVisible(this)"></th>
                                <th style="text-align: center;">Container No.</th>
                                <th style="text-align: center;">Packing No.</th>
                                <th style="text-align: center;">Pieces</th>
                                <th style="text-align: right;">Net Weight (Kg)</th>
                                <th style="text-align: center;">Country</th>
                                <th style="text-align: center;">Requisition No.</th>
                            </tr>
                        </thead>
                        <tbody id="ingotListBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
                <span id="country_calc_preview" style="font-size:13px; font-weight:600; color:#2563eb;"></span>
                <div>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmIngotSelection()" style="font-weight: 600;">✔️ Confirm Selection</button>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script>
var preSelectedIngotIds = <?php echo json_encode($pre_selected_ingots); ?>;
var rawIngotData = [];
var selectedIngotMap = {};

function openIngotModal() {
    $("#filterContainer").val("");
    $("#filterPacking").val("");
    $("#filterCountry").val("");

    var currentBatchNo = $("#batch_no").val();

    $.ajax({
        url: "model/get_wip_ingot_list.php",
        type: "GET",
        data: { bno: currentBatchNo },
        dataType: "json",
        success: function(response) {
            if (response.status === "success") {
                var uniqueItems = [];
                var seen = {};
                (response.data || []).forEach(function(item) {
                    var uniqueKey = (item.req_detail_id && String(item.req_detail_id).trim() !== "") ? String(item.req_detail_id) : (item.container_no + '_' + item.packing_no);
                    if (!seen[uniqueKey]) {
                        seen[uniqueKey] = true;
                        item._uniqueKey = uniqueKey;
                        uniqueItems.push(item);
                    }
                });
                rawIngotData = uniqueItems;

                selectedIngotMap = {}; 
                rawIngotData.forEach(function(item) {
                    var itemIdStr = String(item.req_detail_id);
                    var isAssignedToThisBatch = (item.issue_no && item.issue_no.trim() === currentBatchNo.trim());
                    var isMatchId = preSelectedIngotIds.includes(parseInt(itemIdStr)) || preSelectedIngotIds.includes(itemIdStr);

                    if (isAssignedToThisBatch || isMatchId) {
                        selectedIngotMap[item._uniqueKey] = item;
                    }
                });

                populateCountryFilter();
                renderIngotTable();
                updateModalSummary();
                $("#ingotModal").modal("show");
            } else {
                alert("Cannot load WIP Ingot: " + response.message);
            }
        },
        error: function() { alert("Unable to connect to get WIP Ingot data."); }
    });
}

function populateCountryFilter() {
    var countries = {};
    rawIngotData.forEach(function(item) {
        var c = (item.country_of_origin || '').trim();
        if(c !== "") countries[c] = true;
    });
    var html = '<option value="">All Countries</option>';
    Object.keys(countries).sort().forEach(function(c) {
        html += '<option value="' + c + '">' + c + '</option>';
    });
    $("#filterCountry").html(html);
}

function renderIngotTable() {
    var searchContainer = $("#filterContainer").val().toUpperCase().trim();
    var searchPacking   = $("#filterPacking").val().toUpperCase().trim();
    var selectedCountry = $("#filterCountry").val().toUpperCase().trim();

    var filtered = rawIngotData.filter(function(item) {
        var matchCont = (searchContainer === "") || ((item.container_no || '').toUpperCase().indexOf(searchContainer) > -1);
        var matchPack = (searchPacking === "") || ((item.packing_no || '').toUpperCase().indexOf(searchPacking) > -1);
        var matchCtry = (selectedCountry === "") || ((item.country_of_origin || '').toUpperCase() === selectedCountry);
        return matchCont && matchPack && matchCtry;
    });

    var rows = "";
    if (filtered.length === 0) {
        rows = "<tr><td colspan='7' class='text-center' style='color:#94a3b8; padding: 20px;'>No WIP Ingot items found.</td></tr>";
    } else {
        filtered.forEach(function(item) {
            var key = item._uniqueKey;
            var isChecked = selectedIngotMap[key] ? "checked" : "";
            var weight = parseFloat(item.net_weight_kg) || 0;
            
            rows += "<tr style='cursor:pointer;' onclick='toggleRowCheckbox(\"" + key + "\")'>";
            rows += "<td align='center' onclick='event.stopPropagation();'><input type='checkbox' class='chk_ingot' id='chk_ingot_" + key + "' value='" + key + "' " + isChecked + " onchange='onCheckboxChanged(\"" + key + "\", this.checked)'></td>";
            rows += "<td align='center'><strong>" + (item.container_no || '-') + "</strong></td>";
            rows += "<td align='center' style='color:#1e293b; font-weight:600;'>📦 " + (item.packing_no || '-') + "</td>";
            rows += "<td align='center'>" + (item.piece_count || '-') + "</td>";
            rows += "<td align='right' style='font-weight:700; color:#059669;'>" + weight.toFixed(2) + "</td>";
            rows += "<td align='center'><span class='badge-country'>" + (item.country_of_origin || '-') + "</span></td>";
            rows += "<td align='center' style='color:#64748b; font-size:12px;'>" + (item.req_no || '-') + "</td>";
            rows += "</tr>";
        });
    }
    $("#ingotListBody").html(rows);
    updateModalSummary();
}

function toggleRowCheckbox(key) {
    var chk = document.getElementById("chk_ingot_" + key);
    if (chk) {
        chk.checked = !chk.checked;
        onCheckboxChanged(key, chk.checked);
    }
}

function onCheckboxChanged(key, isChecked) {
    if (isChecked) {
        var found = rawIngotData.find(function(it) { return it._uniqueKey === key; });
        if (found) selectedIngotMap[key] = found;
    } else {
        delete selectedIngotMap[key];
    }
    updateModalSummary();
}

function toggleSelectAllVisible(chkMaster) {
    var isCheck = chkMaster.checked;
    $(".chk_ingot").each(function() {
        var key = $(this).val();
        this.checked = isCheck;
        if (isCheck) {
            var found = rawIngotData.find(function(it) { return it._uniqueKey === key; });
            if (found) selectedIngotMap[key] = found;
        } else {
            delete selectedIngotMap[key];
        }
    });
    updateModalSummary();
}

function clearAllIngotSelection() {
    selectedIngotMap = {};
    $(".chk_ingot").prop("checked", false);
    $("#check_all_ingot").prop("checked", false);
    updateModalSummary();
}

function updateModalSummary() {
    var count = 0;
    var totalWeight = 0;
    var countryTotals = {};

    Object.keys(selectedIngotMap).forEach(function(key) {
        var item = selectedIngotMap[key];
        var w = parseFloat(item.net_weight_kg) || 0;
        var c = (item.country_of_origin || 'UNKNOWN').trim().toUpperCase();
        count++;
        totalWeight += w;
        countryTotals[c] = (countryTotals[c] || 0) + w;
    });

    $("#modal_selected_count").text(count);
    $("#modal_selected_weight").text(totalWeight.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

    var sortedCountries = Object.keys(countryTotals).sort(function(a, b) { return countryTotals[b] - countryTotals[a]; });
    if (sortedCountries.length === 1) {
        $("#country_calc_preview").text("💡 Single Country: " + sortedCountries[0] + " (100%)");
    } else if (sortedCountries.length >= 2) {
        $("#country_calc_preview").text("💡 Primary: " + sortedCountries[0] + " | Secondary: " + sortedCountries[1]);
    } else {
        $("#country_calc_preview").text("");
    }
}

function confirmIngotSelection() {
    var totalWeight = 0;
    var countryTotals = {};
    var selectedIds = [];

    Object.keys(selectedIngotMap).forEach(function(key) {
        var item = selectedIngotMap[key];
        var w = parseFloat(item.net_weight_kg) || 0;
        var c = (item.country_of_origin || '').trim().toUpperCase();
        totalWeight += w;
        selectedIds.push(item.req_detail_id || item._uniqueKey);
        if (c !== "") countryTotals[c] = (countryTotals[c] || 0) + w;
    });

    $("#charge_ingot").val(totalWeight > 0 ? totalWeight.toFixed(2) : "0");
    $("#selected_ingot_items").val(JSON.stringify(selectedIds));
    $("#selected_ingot_count_text").text("Selected " + selectedIds.length + " packing items from WIP");

    var sortedCountries = Object.keys(countryTotals).sort(function(a, b) { return countryTotals[b] - countryTotals[a]; });
    if (sortedCountries.length === 1) {
        setSmeltCountry("primary_smelt", sortedCountries[0]);
        $("#hidden_primary_smelt").val(sortedCountries[0]);
        
        setSmeltCountry("seconday_smelt", "");
        $("#hidden_seconday_smelt").val("");
    } else if (sortedCountries.length >= 2) {
        setSmeltCountry("primary_smelt", sortedCountries[0]);   
        $("#hidden_primary_smelt").val(sortedCountries[0]);

        setSmeltCountry("seconday_smelt", sortedCountries[1]);  
        $("#hidden_seconday_smelt").val(sortedCountries[1]);  
    } else {
        setSmeltCountry("primary_smelt", "");
        $("#hidden_primary_smelt").val("");

        setSmeltCountry("seconday_smelt", "");
        $("#hidden_seconday_smelt").val("");
    }

    calculateTotal();
    $("#ingotModal").modal("hide");
}

function setSmeltCountry(elementId, countryName) {
    var $select = $("#" + elementId);
    $select.find("option").each(function() {
        if ($(this).val().toUpperCase() === countryName.toUpperCase()) {
            $select.val($(this).val());
            return false;
        }
    });
}

function calculateTotal() {
    var remain_form    = parseFloat($("#remain_form").val()) || 0;
    var charge_ingot   = parseFloat($("#charge_ingot").val()) || 0;
    var charge_remelt  = parseFloat($("#charge_remelt").val()) || 0;
    var charge_casting = parseFloat($("#charge_casting").val()) || 0;
    var charge_other   = parseFloat($("#charge_other").val()) || 0;
    var charge_wire    = parseFloat($("#charge_wire").val()) || 0;
    var charge_recycle = parseFloat($("#charge_recycle").val()) || 0;
    var charge_alloy   = parseFloat($("#charge_alloy").val()) || 0;
    
    var total = remain_form + charge_ingot + charge_remelt + charge_casting + charge_other + charge_wire + charge_recycle + charge_alloy;
    $("#total_charge").val(total.toFixed(2));
    calculateBalance();
}

function calculateBalance() {
    var totalCharge = parseFloat($("#total_charge").val()) || 0;
    var totalDross  = parseFloat($("#total_dross").val()) || 0;
    var balance = totalCharge - totalDross;
    $("#balance_furnace").val(balance.toFixed(2));
}

function handleMaterialChange() {
    var selectedMaterial = $("#material_name").val();
    var remainField  = document.getElementById("remain_form");
    var remeltField  = document.getElementById("charge_remelt");
    var castingField = document.getElementById("charge_casting");
    var otherField   = document.getElementById("charge_other");
    var wireField    = document.getElementById("charge_wire");
    var recycleField = document.getElementById("charge_recycle");
    var alloyField   = document.getElementById("charge_alloy");
    
    var allFields = [remainField, remeltField, castingField, otherField, wireField, recycleField, alloyField];
    function enableFields(fields) { fields.forEach(function(f) { f.readOnly = false; }); }
    function setReadOnlyFields(fields) { fields.forEach(function(f) { f.readOnly = true; f.value = "0"; }); }

    enableFields(allFields);
    if (selectedMaterial === "BOI-MAT1" || selectedMaterial === "NONE-BOI") {
        setReadOnlyFields([wireField, recycleField]);
    } else if (selectedMaterial === "BOI-GRS1") {
        enableFields([remeltField, wireField, alloyField]);
        setReadOnlyFields([remainField, castingField, otherField, recycleField]);
    }
    calculateTotal();
}

function update_code(){
    var batchNo        = $("#batch_no").val();
    var chargingDate   = $("#Charg_date_show").val();
    var furnaceNo      = $("#furnace_no").val();
    var material       = $("#material_name").val(); 
    var alloy          = $("#alloy").val();
    
    var primarySmelt   = $("#hidden_primary_smelt").val();
    var secondaySmelt  = $("#hidden_seconday_smelt").val();
    
    var remainForm     = $("#remain_form").val();
    var chargeIngot    = $("#charge_ingot").val();
    var chargeRemelt   = $("#charge_remelt").val();
    var chargeCasting  = $("#charge_casting").val();
    var chargeOther    = $("#charge_other").val();
    var chargeWire     = $("#charge_wire").val();
    var chargeRecycle  = $("#charge_recycle").val();
    var chargeAlloy    = $("#charge_alloy").val();
    var totalCharge    = $("#total_charge").val();
    var totalDross     = $("#total_dross").val();
    var balanceFurnace = $("#balance_furnace").val();
    var remark         = $("#select_detail").val();
    var data_fun       = $("#func").val();
    var ingotItems     = $("#selected_ingot_items").val();
    
    if(batchNo == ""){ alert("ไม่พบรหัส Batch No"); return; }
    
    var data_string = batchNo + "*" + chargingDate + "*" + furnaceNo + "*" + material + "*" + 
                      alloy + "*" + primarySmelt + "*" + secondaySmelt + "*" +
                      remainForm + "*" + chargeIngot + "*" + chargeRemelt + "*" + 
                      chargeCasting + "*" + chargeOther + "*" + chargeWire + "*" + 
                      chargeRecycle + "*" + chargeAlloy + "*" + totalCharge + "*" + 
                      totalDross + "*" + balanceFurnace + "*" + remark;
    
    var btnUpdate = $("#btn_update_code");
    btnUpdate.prop("disabled", true).text("⏳ Updating...");

    $.ajax({
        url: "model/update_furnace_charging_mats.php",
        type: "POST",
        data: { 
            data_tag1: data_string,
            ingot_items: ingotItems
        },
        dataType: "json",
        success: function(resp) {
            if(resp.message){
                alert("The information has been successfully updated.");    
                window.location.assign('furnance_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Unable to update."));
                btnUpdate.prop("disabled", false).text("Update Data");
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred in the system: " + error);
            btnUpdate.prop("disabled", false).text("Update Data");
        }
    });                              
}

$(document).ready(function(){
    handleMaterialChange();
    if(preSelectedIngotIds.length > 0){
        $("#selected_ingot_count_text").text("Selected " + preSelectedIngotIds.length + " packing items from WIP");
    }
});
</script>
</html>