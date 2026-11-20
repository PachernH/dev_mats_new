<?php
// maintain_product_detail_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars((string)$_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$raw_func        = trim((string)($_GET['func'] ?? ($_SESSION['FUNC'] ?? '')));
$folder_func     = preg_replace('/[^A-Za-z0-9_-]/', '', $raw_func);
$department_func = strtoupper(trim((string)($_SESSION['DEPARTMENT'] ?? '')));

if ($iduser_func === '') {
    header("Location: index.php");
    exit();
}

$is_wh_authorized    = ($department_func === 'WH');
$is_dr_authorized    = in_array($department_func, ['MK', 'PP'], true);
$is_it_authorized    = ($department_func === 'IT' || strtoupper($raw_func) === 'IT');
$can_maintain_status = ($is_wh_authorized || $is_dr_authorized || $is_it_authorized);

$pno = isset($_GET['pno']) ? strtoupper(trim((string)$_GET['pno'])) : '';

// ค้นหาไฟล์ navigation.php ที่ตรงกับชื่อโฟลเดอร์จริง (รองรับทั้งตัวพิมพ์เล็ก/ใหญ่ และ fallback)
$nav_file = '';
$include_dir = __DIR__ . '/include';
$search_dirs = array_filter([
    $folder_func !== '' ? $include_dir . '/' . $folder_func : '',
    $folder_func !== '' ? $include_dir . '/' . strtolower($folder_func) : '',
    $folder_func !== '' ? $include_dir . '/' . strtoupper($folder_func) : '',
    $include_dir . '/default',
    $include_dir
]);

if (is_dir($include_dir)) {
    $entries = scandir($include_dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $candidatePath = $include_dir . '/' . $entry . '/navigation.php';
        if ($folder_func !== '' && strcasecmp($entry, $folder_func) === 0 && is_file($candidatePath)) {
            $nav_file = $candidatePath;
            break;
        }
    }
}

if ($nav_file === '') {
    foreach ($search_dirs as $dir) {
        $candidatePath = rtrim($dir, '/') . '/navigation.php';
        if (is_file($candidatePath)) {
            $nav_file = $candidatePath;
            break;
        }
    }
}

$backUrl = 'maintain_product_status_mats.php' . ($folder_func !== '' ? '?func=' . urlencode($folder_func) : '');
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Product Detail &amp; Status</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .page-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 18px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            flex-wrap: wrap;
            gap: 10px;
        }
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pno-pill {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-weight: 700;
            font-size: 13.5px;
            padding: 5px 12px;
            border-radius: 8px;
        }
        .section-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 18px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);
        }
        .section-header {
            background-color: #dbeafe;
            color: #1e3a8a;
            border-bottom: 1px solid #bfdbfe;
            padding: 10px 16px;
            font-size: 14.5px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-sub {
            font-size: 12px;
            font-weight: 600;
            color: #3b82f6;
        }
        .two-col-table {
            width: 100%;
            border-collapse: collapse;
        }
        .two-col-table td.half-cell {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .two-col-table td.half-cell:first-child {
            border-right: 2px solid #e2e8f0;
        }
        @media (max-width: 992px) {
            .two-col-table,
            .two-col-table tbody,
            .two-col-table tr,
            .two-col-table td.half-cell {
                display: block;
                width: 100%;
            }
            .two-col-table td.half-cell:first-child {
                border-right: none;
                border-bottom: 2px solid #e2e8f0;
            }
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .data-table thead th {
            background-color: #eff6ff;
            color: #1e40af;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 12px;
            border-bottom: 1px solid #bfdbfe;
        }
        .data-table th.field-lbl {
            width: 44%;
            text-align: left;
            background-color: #f8fafc;
            color: #334155;
            font-weight: 700;
            padding: 8px 14px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }
        .data-table td.field-val {
            padding: 8px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            color: #0f172a;
        }
        .data-table td.qty-wt {
            width: 28%;
            text-align: right;
            font-weight: 700;
            color: #0f172a;
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #f1f5f9;
            font-variant-numeric: tabular-nums;
        }
        .data-table td.qty-pc {
            width: 28%;
            text-align: right;
            font-weight: 700;
            color: #1d4ed8;
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-variant-numeric: tabular-nums;
        }
        .data-table tr:hover th.field-lbl,
        .data-table tr:hover td.field-val,
        .data-table tr:hover td.qty-wt,
        .data-table tr:hover td.qty-pc {
            background-color: #f0f9ff;
        }
        .data-table tr.row-pack th.field-lbl,
        .data-table tr.row-pack td.qty-wt,
        .data-table tr.row-pack td.qty-pc {
            background-color: #fefce8;
        }
        .data-table tr.row-stock th.field-lbl,
        .data-table tr.row-stock td.qty-wt,
        .data-table tr.row-stock td.qty-pc {
            background-color: #eff6ff;
        }
        .unit-muted {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-left: 3px;
        }
        .tag-chip {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-weight: 700;
            font-size: 11.5px;
            padding: 2px 8px;
            border-radius: 5px;
            margin-left: 6px;
        }
        .st-ok {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 5px;
        }
        .st-warn {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            font-weight: 700;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 5px;
        }
        .remark-box {
            padding: 12px 16px;
            min-height: 56px;
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            background: #ffffff;
            white-space: pre-wrap;
        }
        .btn-back {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-weight: 700;
            font-size: 13px;
            padding: 7px 14px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover { background: #f8fafc; color: #0f172a; }
        .btn-upd {
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
        }
        .btn-upd:hover { background: #1d4ed8; }

        /* Popup Modal */
        .status-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .status-modal-backdrop.show { display: flex; }
        .status-modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 460px;
            border-radius: 12px;
            border: 1px solid #bfdbfe;
            overflow: hidden;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.18);
        }
        .status-modal-header {
            padding: 12px 18px;
            background-color: #dbeafe;
            color: #1e3a8a;
            border-bottom: 1px solid #bfdbfe;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-modal-body { padding: 18px; }
        .status-modal-footer {
            padding: 12px 18px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .form-field { margin-bottom: 14px; }
        .form-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-field input, .form-field select {
            width: 100%;
            height: 38px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
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
                    <a class="navbar-brand" style="font-weight:700; color:#1e3a8a; font-size: 20px;" href="#">
                        Product Detail &amp; Status
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 18px;">
            <div class="page-toolbar">
                <div class="toolbar-left">
                    <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back"> ⬅️ Back to List</a>
                    <span class="pno-pill">📦 <?php echo htmlspecialchars($pno !== '' ? $pno : '-', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($can_maintain_status && $pno !== ''): ?>
                    <button type="button" id="btn_open_detail_modal" class="btn-upd" onclick="openDetailModal()">🔄 Update Status</button>
                <?php endif; ?>
            </div>

            <div id="detail_loading" class="section-card" style="padding: 18px;">กำลังโหลดข้อมูลรายละเอียดสินค้า...</div>

            <div id="detail_content" style="display:none;">
                <!-- 1. Maintain Product (การ์ดเดียว แบ่ง 2 คอลัมน์ด้านใน) -->
                <div class="section-card">
                    <div class="section-header">
                        <span>📦 Maintain Product</span>
                        <span class="section-sub">Product &amp; Specification</span>
                    </div>
                    <table class="two-col-table">
                        <tbody>
                            <tr>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_prod_info"></table>
                                </td>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_prod_size"></table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. Job Order (การ์ดเดียว ต่อเนื่องครบทุกแถว) -->
                <div class="section-card">
                    <div class="section-header">
                        <span>🏭 Job Order</span>
                        <span class="section-sub">Production &amp; Balance</span>
                    </div>
                    <table class="two-col-table">
                        <tbody>
                            <tr>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_job_top"></table>
                                </td>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_job_qty"></table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. Sale Order (การ์ดเดียว ต่อเนื่องครบทุกแถว) -->
                <div class="section-card">
                    <div class="section-header">
                        <span>📄 Sale Order</span>
                        <span class="section-sub">Customer Order &amp; Tolerance</span>
                    </div>
                    <table class="two-col-table">
                        <tbody>
                            <tr>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_so_top"></table>
                                </td>
                                <td class="half-cell">
                                    <table class="data-table" id="tbl_so_qty"></table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. Remark (ล่างสุด) -->
                <div class="section-card">
                    <div class="section-header">
                        <span>📝 Remark</span>
                        <span class="section-sub">Pack Remark</span>
                    </div>
                    <div class="remark-box" id="txt_remark">-</div>
                </div>
            </div>
        </div>

        <!-- Popup Modal -->
        <div id="detailStatusModal" class="status-modal-backdrop" role="dialog" aria-modal="true">
            <div class="status-modal-card">
                <div class="status-modal-header">
                    <h4 style="margin:0; font-weight:700; font-size:15px;">🔄 Update Product Status</h4>
                    <button type="button" onclick="closeDetailModal()" style="background:none; border:none; color:#1e3a8a; font-size:20px; font-weight:700; cursor:pointer;">&times;</button>
                </div>
                <div class="status-modal-body">
                    <div class="form-field">
                        <label>PRODUCT NO.</label>
                        <input type="text" id="d_modal_pno" readonly style="background:#f1f5f9;" />
                    </div>
                    <div class="form-field">
                        <label>NEW STATUS (ST / DS / AC)</label>
                        <select id="d_modal_status">
                            <option value="ST">ST - Stock</option>
                            <option value="DS">DS - Dead Stock</option>
                            <option value="AC">AC - Cancel / Release from Pack</option>
                        </select>
                    </div>
                    <div id="d_modal_err" style="color:#dc2626; font-size:13px; font-weight:600; display:none;"></div>
                </div>
                <div class="status-modal-footer">
                    <button type="button" onclick="closeDetailModal()" style="padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; font-weight:600; cursor:pointer;">Cancel</button>
                    <button type="button" id="d_btn_save" onclick="saveDetailStatus()" style="padding:8px 18px; border-radius:8px; border:none; background:#2563eb; color:#fff; font-weight:700; cursor:pointer;">💾 Save</button>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>
</body>
<?php include 'include/footer.php'; ?>
<script type="text/javascript">
var currentDetailData = null;

function esc(v) {
    if (v === null || v === undefined || String(v).trim() === '') return '-';
    return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmtNum(v, dec) {
    var n = parseFloat(v || 0);
    if (isNaN(n)) n = 0;
    return n.toLocaleString('en-US', {
        minimumFractionDigits: dec || 0,
        maximumFractionDigits: dec || 0
    });
}

function fmtDate(v) {
    if (!v) return '-';
    var s = String(v).trim();
    if (!s) return '-';
    return s.split(' ')[0];
}

function headDual(col1, col2) {
    return '<thead><tr>' +
           '<th style="text-align:left;">Item</th>' +
           '<th style="text-align:right;">' + col1 + '</th>' +
           '<th style="text-align:right;">' + col2 + '</th>' +
           '</tr></thead>';
}

function rowInfo(label, valHtml, extraClass) {
    return '<tr class="' + (extraClass || '') + '">' +
           '<th class="field-lbl">' + label + '</th>' +
           '<td class="field-val" colspan="2">' + valHtml + '</td>' +
           '</tr>';
}

function rowDual(label, v1, u1, v2, u2, extraClass) {
    return '<tr class="' + (extraClass || '') + '">' +
           '<th class="field-lbl">' + label + '</th>' +
           '<td class="qty-wt">' + v1 + '<span class="unit-muted">' + (u1 || '') + '</span></td>' +
           '<td class="qty-pc">' + v2 + '<span class="unit-muted">' + (u2 || '') + '</span></td>' +
           '</tr>';
}

function loadProductDetail() {
    var pno = <?php echo json_encode($pno); ?>;
    var loadingEl = document.getElementById("detail_loading");
    if (!pno) {
        loadingEl.textContent = "ไม่พบพารามิเตอร์ Product No";
        return;
    }

    fetch("model/maintain_change_status_mats.php?action=DETAIL&pno=" + encodeURIComponent(pno))
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok || !res.data) {
                loadingEl.textContent = res.message || "ไม่พบข้อมูล";
                return;
            }
            currentDetailData = res.data;
            var s = res.data.stock || {};
            var sp = res.data.spec || {};
            var c = res.data.calculated || {};
            var pid = String(s.PRODUCT_ID || "").toUpperCase();
            var isCoil = (pid === "CO" || pid === "OS");
            var stCode = String(s.PACK_STATUS || "").toUpperCase();
            var stBadge = '<span class="' + (stCode === 'DS' ? 'st-warn' : 'st-ok') + '">' + esc(stCode || '-') + '</span>' +
                          '<span class="tag-chip">VMI: ' + esc(s.VMI_STATUS) + '</span>';

            // 1. Maintain Product (ซ้าย: ข้อมูลหลัก, ขวา: ขนาดและน้ำหนัก)
            document.getElementById("tbl_prod_info").innerHTML = '<tbody>' + [
                rowInfo("Product No.", esc(s.PRODUCT_NO) + '<span class="tag-chip">' + esc(pid) + '</span>'),
                rowInfo("Status - VMI St.", stBadge),
                rowInfo("Alloy - Temper", esc(sp.ALLOY) + '<span class="tag-chip">' + esc(sp.TEMPER) + '</span>'),
                rowInfo("SG - MG", esc(sp.SURFACE_GRADE) + '<span class="tag-chip">' + esc(sp.METALLURGICAL_GRADE) + '</span>')
            ].join('') + '</tbody>';

            document.getElementById("tbl_prod_size").innerHTML = headDual("Weight / Size", "Piece") + '<tbody>' + [
                rowInfo("Thickness", fmtNum(sp.THICKNESS, 3) + '<span class="unit-muted">mm.</span>'),
                rowInfo("Width - Length", fmtNum(sp.WIDTH, 3) + ' / ' + (isCoil ? '0.000' : fmtNum(sp.LENGTH, 3)) + '<span class="unit-muted">mm.</span>'),
                rowInfo("Weight Piece", isCoil ? '0.0000' : fmtNum(sp.WEIGHT_PIECE, 4)),
                rowDual("Net Qty. (Wt. - Pc.)", fmtNum(s.PACK_NETWEIGHT, 0), "Kg.", fmtNum(s.PACK_NETPIECE, 0), "Pc.", "row-stock")
            ].join('') + '</tbody>';

            // 2. Job Order (ซ้าย: Job & ETD, ขวา: Tolerance & Qty)
            document.getElementById("tbl_job_top").innerHTML = '<tbody>' + [
                rowInfo("Job Order", esc(s.JOB_ORDER)),
                rowInfo("ETD Date", esc(fmtDate(s.SCHEDULE_DATE)))
            ].join('') + '</tbody>';

            document.getElementById("tbl_job_qty").innerHTML = headDual("Kg.", "Pc.") + '<tbody>' + [
                rowDual("Max-Min Tolerance", fmtNum(s.JOB_MAXTOLERANCE, 0), "% Max", fmtNum(s.JOB_MINTOLERANCE, 0), "% Min"),
                rowDual("Maximum Qty.", fmtNum(c.job_max_weight, 0), "Kg.", fmtNum(c.job_max_piece, 0), "Pc."),
                rowDual("Release Qty.", fmtNum(s.JOB_RELEASEWEIGHT, 0), "Kg.", fmtNum(s.JOB_RELEASEPIECE, 0), "Pc."),
                rowDual("Minimum Qty.", fmtNum(c.job_min_weight, 0), "Kg.", fmtNum(c.job_min_piece, 0), "Pc."),
                rowDual("Packing Qty.", fmtNum(s.JOB_PACKWEIGHT, 0), "Kg.", fmtNum(s.JOB_PACKPIECE, 0), "Pc.", "row-pack"),
                rowDual("Stock Qty.", fmtNum(s.JOB_STOCKWEIGHT, 0), "Kg.", fmtNum(s.JOB_STOCKPIECE, 0), "Pc.", "row-stock")
            ].join('') + '</tbody>';

            // 3. Sale Order (ซ้าย: SO / PO / Confirm Date, ขวา: Order / Limit / Pack / Stock Qty)
            document.getElementById("tbl_so_top").innerHTML = '<tbody>' + [
                rowInfo("Sale Order No", esc(s.SALEORDER_NO) + '<span class="tag-chip">Item ' + esc(s.SALEORDER_ITEM) + '</span>'),
                rowInfo("Purchase Order", esc(s.CTM2_PONO) + '<span class="tag-chip">Item ' + esc(s.CTM2_POITEM) + '</span>'),
                rowInfo("Confirm Date", esc(fmtDate(s.CONFIRM_DATE)))
            ].join('') + '</tbody>';

            document.getElementById("tbl_so_qty").innerHTML = headDual("kg.", "Pc.") + '<tbody>' + [
                rowDual("Max-Min Tolerance", fmtNum(s.CTM2_MAXTOLERANCE, 0), "% Max", fmtNum(s.CTM2_MINTOLERANCE, 0), "% Min"),
                rowDual("Order Qty. (Wt. - Pc.)", fmtNum(s.CTM2_ORDERWEIGHT, 0), "kg.", fmtNum(s.CTM2_ORDERPIECE, 0), "Pc."),
                rowDual("Order Reject Qty. (Wt. - Pc.)", fmtNum(s.CTM2_REJECTWEIGHT, 0), "kg.", fmtNum(s.CTM2_REJECTPIECE, 0), "Pc."),
                rowDual("Order Replace Qty. (Wt. - Pc.)", fmtNum(s.CTM2_REPLACEWEIGHT, 0), "kg.", fmtNum(s.CTM2_REPLACEPIECE, 0), "Pc."),
                rowDual("Maximum Qty. (Wt. - Pc.)", fmtNum(c.ctm2_max_weight, 0), "kg.", fmtNum(c.ctm2_max_piece, 0), "Pc."),
                rowDual("Total Order Qty. (Wt. - Pc.)", fmtNum(c.ctm2_total_weight, 0), "kg.", fmtNum(c.ctm2_total_piece, 0), "Pc."),
                rowDual("Minimum Qty. (Wt. - Pc.)", fmtNum(c.ctm2_min_weight, 0), "kg.", fmtNum(c.ctm2_min_piece, 0), "Pc."),
                rowDual("Packing Qty. (Wt. - Pc.)", fmtNum(s.CTM2_PACKWEIGHT, 0), "kg.", fmtNum(s.CTM2_PACKPIECE, 0), "Pc.", "row-pack"),
                rowDual("Stock Qty. (Wt. - Pc.)", fmtNum(s.CTM2_STOCKWEIGHT, 0), "kg.", fmtNum(s.CTM2_STOCKPIECE, 0), "Pc.", "row-stock")
            ].join('') + '</tbody>';

            // 4. Remark
            document.getElementById("txt_remark").textContent =
                (s.PACK_REMARK && String(s.PACK_REMARK).trim() !== '') ? s.PACK_REMARK : '-';

            loadingEl.style.display = "none";
            document.getElementById("detail_content").style.display = "block";
        })
        .catch(function() {
            loadingEl.textContent = "เกิดข้อผิดพลาดในการดึงข้อมูลรายละเอียด";
        });
}

function openDetailModal() {
    if (!currentDetailData) return;
    var s = currentDetailData.stock;
    document.getElementById("d_modal_pno").value = s.PRODUCT_NO;
    document.getElementById("d_modal_status").value = s.PACK_STATUS || "ST";
    document.getElementById("d_modal_err").style.display = "none";
    document.getElementById("detailStatusModal").classList.add("show");
}

function closeDetailModal() {
    document.getElementById("detailStatusModal").classList.remove("show");
}

function saveDetailStatus() {
    var btn = document.getElementById("d_btn_save");
    var errEl = document.getElementById("d_modal_err");
    var backUrl = <?php echo json_encode($backUrl); ?>;
    btn.disabled = true;
    errEl.style.display = "none";

    fetch("model/maintain_change_status_mats.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            action: "UPDATE_STATUS",
            product_no: document.getElementById("d_modal_pno").value,
            new_status: document.getElementById("d_modal_status").value,
            pack_remark: (currentDetailData && currentDetailData.stock && currentDetailData.stock.PACK_REMARK) ? currentDetailData.stock.PACK_REMARK : ""
        })
    })
    .then(function(r) { return r.json().then(function(b) { return { status: r.status, body: b }; }); })
    .then(function(res) {
        if (res.status >= 200 && res.status < 300 && res.body.ok) {
            alert(res.body.message);
            window.location.href = backUrl;
        } else {
            errEl.textContent = res.body.message || "บันทึกไม่สำเร็จ";
            errEl.style.display = "block";
            btn.disabled = false;
        }
    })
    .catch(function() {
        errEl.textContent = "เชื่อมต่อ API ไม่สำเร็จ";
        errEl.style.display = "block";
        btn.disabled = false;
    });
}

loadProductDetail();
</script>
</html>