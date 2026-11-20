<?php
// maintain_product_status_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars((string)$_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$raw_func        = trim((string)($_SESSION['FUNC'] ?? ($_GET['func'] ?? '')));
$folder_func     = preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper($raw_func));
$position_func   = strtoupper(trim((string)($_SESSION['POSITION'] ?? '')));
$department_func = strtoupper(trim((string)($_SESSION['DEPARTMENT'] ?? '')));

if ($iduser_func === '') {
    header("Location: index.php");
    exit();
}

$is_wh_authorized    = ($department_func === 'WH');
$is_dr_authorized    = in_array($department_func, ['MK', 'PP'], true);
$is_it_authorized    = ($department_func === 'IT' || strtoupper($raw_func) === 'IT');
$can_maintain_status = ($is_wh_authorized || $is_dr_authorized || $is_it_authorized);

$q_search      = isset($_GET['q']) ? strtoupper(trim((string)$_GET['q'])) : '';
$status_filter = isset($_GET['status']) ? strtoupper(trim((string)$_GET['status'])) : '';
if (!in_array($status_filter, ['ST', 'DS'], true)) {
    $status_filter = '';
}

// เลือกไฟล์ navigation ที่ปลอดภัยและมีอยู่จริง
$nav_candidates = [];
if ($folder_func !== '') {
    $nav_candidates[] = __DIR__ . '/include/' . $folder_func . '/navigation.php';
    $nav_candidates[] = __DIR__ . '/include/' . strtolower($folder_func) . '/navigation.php';
}
$nav_candidates[] = __DIR__ . '/include/default/navigation.php';
$nav_candidates[] = __DIR__ . '/include/navigation.php';

$nav_file = '';
foreach ($nav_candidates as $candidate) {
    if (is_file($candidate)) {
        $nav_file = $candidate;
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Maintain Product Status</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; }
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; cursor: pointer; }
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; display: inline-block; }
        .badge-vmi { background-color: #f1f5f9; color: #334155; font-weight: 700; font-size: 12px; padding: 4px 9px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block; }
        .badge-st { background-color: #dcfce7; color: #166534; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }
        .badge-ds { background-color: #fef3c7; color: #92400e; font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 6px; }
        .action-group { display: inline-flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: nowrap; }
        .btn-action-update { background-color: #d97706; color: #ffffff; font-weight: 700; font-size: 12px; padding: 6px 11px; border-radius: 6px; border: none; cursor: pointer; white-space: nowrap; }
        .btn-action-update:hover { background-color: #b45309; }
        .btn-action-detail { background-color: #2563eb; color: #ffffff; font-weight: 700; font-size: 12px; padding: 6px 11px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-block; white-space: nowrap; }
        .btn-action-detail:hover { background-color: #1d4ed8; color: #ffffff; }

        /* Popup Modal */
        .status-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
        .status-modal-backdrop.show { display: flex; }
        .status-modal-card { background: #ffffff; width: 100%; max-width: 480px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; }
        .status-modal-header { padding: 16px 20px; background: #1e293b; color: #ffffff; display: flex; justify-content: space-between; align-items: center; }
        .status-modal-body { padding: 20px; }
        .status-modal-footer { padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
        .form-field { margin-bottom: 14px; }
        .form-field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
        .form-field input, .form-field select { width: 100%; height: 38px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 14px; font-weight: 600; color: #1e293b; }
        .vmi-alert-box { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 10px 12px; border-radius: 8px; font-size: 12.5px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="wrapper">
<?php
    $menu = 'A6';
    if ($nav_file !== '') {
        include $nav_file;
    }
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Maintain Product Status
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label style="font-size:12px; font-weight:700; color:#64748b;">SEARCH PRODUCT / JOB / SALE ORDER</label>
                    <input class="form-control-minimal" id="q_input" type="text"
                           placeholder="ระบุ Product No, Job Order หรือ Sale Order..."
                           value="<?php echo htmlspecialchars($q_search, ENT_QUOTES, 'UTF-8'); ?>"
                           oninput="this.value = this.value.toUpperCase()"
                           onchange="applyFilter()" />
                </div>
                <div class="filter-item" style="min-width: 170px;">
                    <label style="font-size:12px; font-weight:700; color:#64748b;">PACK STATUS</label>
                    <select class="form-control-minimal" id="status_filter" onchange="applyFilter()">
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>ALL (ST &amp; DS)</option>
                        <option value="ST" <?php echo $status_filter === 'ST' ? 'selected' : ''; ?>>ST (Stock)</option>
                        <option value="DS" <?php echo $status_filter === 'DS' ? 'selected' : ''; ?>>DS (Dead Stock)</option>
                    </select>
                </div>
                <div class="filter-item">
                    <button type="button" class="btn btn-search" onclick="applyFilter()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="dashboard-card">
                <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                    📦 Packing Stock List (PACK_STATUS IN 'ST', 'DS')
                </h4>

                <div class="table-responsive">
                    <table id="status_table" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center;">Product No</th>
                                <th style="text-align: center;">Type</th>
                                <th style="text-align: center;">Job Order</th>
                                <th style="text-align: center;">Sale Order</th>
                                <th style="text-align: center;">Alloy / Temper</th>
                                <th style="text-align: right;">Net Wt (kg)</th>
                                <th style="text-align: right;">Net Pcs</th>
                                <th style="text-align: center;">VMI</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center; width: 170px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            include_once 'function_mats.php';
                            include_once 'dbcon_mats-new.php';

                            $sql = "SELECT TOP 200
                                        p.PRODUCT_NO, p.PRODUCT_ID, p.JOB_ORDER, p.SALEORDER_NO, p.SALEORDER_ITEM,
                                        p.PACK_NETWEIGHT, p.PACK_NETPIECE, p.VMI_STATUS, p.PACK_STATUS,
                                        COALESCE(c.ALLOY, co.ALLOY) AS ALLOY,
                                        COALESCE(c.TEMPER, co.TEMPER) AS TEMPER
                                    FROM PACKPROD1 p
                                    LEFT JOIN CRSHPROD1 c ON p.PRODUCT_NO = c.PRODUCT_NO
                                    LEFT JOIN COILPROD1 co ON p.PRODUCT_NO = co.COIL_NO
                                    WHERE (p.PACK_STATUS = 'ST' OR p.PACK_STATUS = 'DS')";

                            $params = [];
                            if ($status_filter !== '') {
                                $sql .= " AND p.PACK_STATUS = :status";
                                $params[':status'] = $status_filter;
                            }
                            if ($q_search !== '') {
                                $sql .= " AND (p.PRODUCT_NO LIKE :q OR p.JOB_ORDER LIKE :q OR p.SALEORDER_NO LIKE :q)";
                                $params[':q'] = '%' . $q_search . '%';
                            }
                            $sql .= " ORDER BY p.PRODUCT_NO ASC";

                            $stmt = $conn->prepare($sql);
                            $stmt->execute($params);

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $rawPno = (string)$row['PRODUCT_NO'];
                                $pno    = htmlspecialchars($rawPno, ENT_QUOTES, 'UTF-8');
                                $pid    = htmlspecialchars((string)$row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8');
                                $job    = htmlspecialchars((string)$row['JOB_ORDER'], ENT_QUOTES, 'UTF-8');
                                $so     = htmlspecialchars(trim((string)$row['SALEORDER_NO'] . ' / ' . (string)$row['SALEORDER_ITEM'], ' /'), ENT_QUOTES, 'UTF-8');
                                $at     = htmlspecialchars(trim((string)$row['ALLOY'] . ' ' . (string)$row['TEMPER']), ENT_QUOTES, 'UTF-8');
                                $vmi    = htmlspecialchars((string)$row['VMI_STATUS'], ENT_QUOTES, 'UTF-8');
                                $st     = htmlspecialchars((string)$row['PACK_STATUS'], ENT_QUOTES, 'UTF-8');
                                $badgeClass = ($st === 'ST') ? 'badge-st' : 'badge-ds';
                                $detailUrl = 'maintain_product_detail_mats.php?pno=' . urlencode($rawPno) . ($folder_func !== '' ? '&func=' . urlencode($folder_func) : '');

                                echo "<tr>";
                                echo "<td align='center'><span class='badge-job'>📦 {$pno}</span></td>";
                                echo "<td align='center' style='font-weight:700;'>{$pid}</td>";
                                echo "<td align='center' style='font-weight:600;'>{$job}</td>";
                                echo "<td align='center'>{$so}</td>";
                                echo "<td align='center'>{$at}</td>";
                                echo "<td align='right' style='font-weight:700;'>" . number_format((float)$row['PACK_NETWEIGHT'], 0) . "</td>";
                                echo "<td align='right'>" . number_format((float)$row['PACK_NETPIECE'], 0) . "</td>";
                                echo "<td align='center'><span class='badge-vmi'>" . ($vmi !== '' ? $vmi : '-') . "</span></td>";
                                echo "<td align='center'><span class='{$badgeClass}'>{$st}</span></td>";
                                echo "<td align='center'><div class='action-group'>";
                                echo "<a class='btn-action-detail' href='{$detailUrl}'>🔍 Detail</a>";
                                if ($can_maintain_status) {
                                    echo "<button type='button' class='btn-action-update' onclick=\"openStatusModal('{$pno}', '{$st}', '{$vmi}')\">🔄 Update</button>";
                                }
                                echo "</div></td>";
                                echo "</tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Popup Modal สำหรับเปลี่ยน Status -->
        <div id="statusModal" class="status-modal-backdrop" role="dialog" aria-modal="true">
            <div class="status-modal-card">
                <div class="status-modal-header">
                    <h4 style="margin:0; font-weight:700;">🔄 Update Product Status</h4>
                    <button type="button" onclick="closeStatusModal()" style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
                </div>
                <div class="status-modal-body">
                    <div class="vmi-alert-box">
                        หากเลือก <strong>AC</strong> และสินค้ามีสถานะ VMI เป็น <strong>PP</strong> หรือ <strong>CL</strong> ระบบจะปฏิเสธการบันทึก (กรณี <strong>ST</strong> จะปรับเป็น <strong>PK</strong> อัตโนมัติ)
                    </div>
                    <div class="form-field">
                        <label>PRODUCT NO.</label>
                        <input type="text" id="modal_product_no" readonly style="background:#f1f5f9;" />
                    </div>
                    <div class="form-field">
                        <label>CURRENT STATUS / VMI</label>
                        <input type="text" id="modal_current_info" readonly style="background:#f1f5f9;" />
                    </div>
                    <div class="form-field">
                        <label>NEW STATUS (ST / DS / AC)</label>
                        <select id="modal_new_status">
                            <option value="ST">ST - Stock</option>
                            <option value="DS">DS - Dead Stock</option>
                            <option value="AC">AC - Cancel / Release from Pack</option>
                        </select>
                    </div>
                    <div id="modal_error_msg" style="color:#dc2626; font-size:13px; font-weight:600; display:none;"></div>
                </div>
                <div class="status-modal-footer">
                    <button type="button" onclick="closeStatusModal()" style="padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="button" id="btn_submit_status" onclick="submitStatusUpdate()" style="padding:8px 18px; border-radius:8px; border:none; background:#0284c7; color:#fff; font-weight:700; cursor:pointer;">💾 Save Status</button>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>
</body>
<?php include 'include/footer.php'; ?>
<script type="text/javascript">
$("#status_table").DataTable({
    scrollY: "480px",
    scrollCollapse: true,
    paging: true,
    pageLength: 20,
    order: []
});

function applyFilter() {
    var q = document.getElementById("q_input").value.trim();
    var st = document.getElementById("status_filter").value;
    var url = "maintain_product_status_mats.php?q=" + encodeURIComponent(q) + "&status=" + encodeURIComponent(st);
    window.location.assign(url);
}

function openStatusModal(pno, currentStatus, vmiStatus) {
    document.getElementById("modal_product_no").value = pno;
    document.getElementById("modal_current_info").value = "Status: " + (currentStatus || "-") + " | VMI: " + (vmiStatus || "-");
    document.getElementById("modal_new_status").value = currentStatus || "ST";
    var errEl = document.getElementById("modal_error_msg");
    errEl.style.display = "none";
    errEl.textContent = "";
    document.getElementById("statusModal").classList.add("show");
}

function closeStatusModal() {
    document.getElementById("statusModal").classList.remove("show");
}

function submitStatusUpdate() {
    var btn = document.getElementById("btn_submit_status");
    var errEl = document.getElementById("modal_error_msg");
    var payload = {
        action: "UPDATE_STATUS",
        product_no: document.getElementById("modal_product_no").value,
        new_status: document.getElementById("modal_new_status").value
    };

    btn.disabled = true;
    errEl.style.display = "none";

    fetch("model/maintain_change_status_mats.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json().then(function(data) { return { status: res.status, body: data }; }); })
    .then(function(result) {
        if (result.status >= 200 && result.status < 300 && result.body.ok) {
            alert(result.body.message);
            window.location.reload();
        } else {
            errEl.textContent = result.body.message || "ไม่สามารถบันทึกข้อมูลได้";
            errEl.style.display = "block";
            btn.disabled = false;
        }
    })
    .catch(function() {
        errEl.textContent = "เกิดข้อผิดพลาดในการเชื่อมต่อ API";
        errEl.style.display = "block";
        btn.disabled = false;
    });
}
</script>
</html>