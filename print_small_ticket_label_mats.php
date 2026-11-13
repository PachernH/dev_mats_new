<?php
/**
 * File: print_small_ticket_label_mats.php
 * Product Ticket Label (Circle & Sheet)
 */

include("dbcon_mats-new.php");
include 'function_mats.php';

// รองรับทั้งพารามิเตอร์ product_no (มี _) และ productno (ไม่มี _)
$prod_no   = htmlspecialchars(trim($_GET['product_no'] ?? $_GET['productno'] ?? ''), ENT_QUOTES, 'UTF-8');
$job_order = htmlspecialchars(trim($_GET['job_order'] ?? $_GET['joborder'] ?? ''), ENT_QUOTES, 'UTF-8');

$ticket_data  = null;
$cust_address = null;

if (!empty($prod_no)) {
    $where_job = !empty($job_order) ? " AND P.JOB_ORDER = :job_order" : "";
    
    $sql_ticket = "SELECT P.PRODUCT_NO, P.JOB_ORDER, P.SALEORDER_NO, P.SALEORDER_ITEM, P.PACK_PONO, P.PACK_POITEM,
                          P.PACK_COMBINEWEIGHT, P.PACK_PACKAGEWEIGHT, P.PACK_NETWEIGHT, P.PACK_NETPIECE, P.PACK_DATE,
                          C.PRODUCT_ID, C.PRODUCT_MODEL, C.ALLOY, C.TEMPER, C.SURFACE_GRADE, C.METALLURGICAL_GRADE,
                          C.THICKNESS, C.WIDTH, C.LENGTH, C.F_WIDTH, C.COIL_NO,
                          C.CRSH_DIMENSIONWIDTH, C.CRSH_DIMENSIONLENGTH, C.CRSH_DIMENSIONHIGH,
                          C.CRSH_UOMDIMENSION, C.CRSH_UOMWEIGHT,
                          COALESCE(C.CRSH_ENDDATE, C.CRSH_OPERATEDATE, P.PACK_DATE) AS PRODUCE_DATE,
                          SO1.CSTMSPPL_ID,
                          SO2.CTM2_PONO, SO2.CTM2_POITEM, SO2.CTM2_CUSTOMERPARTNO,
                          SO2.CONFIRM_DATE, SO2.REQUEST_DATE, SO2.CTM2_UOMWEIGHT, SO2.CTM2_UOMDIMENSION,
                          SO2.CT2U_THICKNESS, SO2.CT2U_WIDTH, SO2.CT2U_LENGTH,
                          SO2.CT2U_UOMDIMENSION, SO2.CT2U_UOMWEIGHT
                   FROM [MATS-NEWII].dbo.PACKPROD1 P
                   LEFT JOIN [MATS-NEWII].dbo.CRSHPROD1 C ON P.PRODUCT_NO = C.PRODUCT_NO
                   LEFT JOIN [MATS-NEWII].dbo.CSTMORDR2 SO2 ON P.SALEORDER_NO = SO2.SALEORDER_NO AND P.SALEORDER_ITEM = SO2.SALEORDER_ITEM
                   LEFT JOIN [MATS-NEWII].dbo.CSTMORDR1 SO1 ON P.SALEORDER_NO = SO1.SALEORDER_NO
                   WHERE P.PRODUCT_NO = :prod_no {$where_job}";

    $stmt_ticket = $conn->prepare($sql_ticket);
    $stmt_ticket->bindParam(':prod_no', $prod_no, PDO::PARAM_STR);
    if (!empty($job_order)) {
        $stmt_ticket->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    }
    $stmt_ticket->execute();
    $ticket_data = $stmt_ticket->fetch(PDO::FETCH_ASSOC);

    // ดึงชื่อและที่อยู่ลูกค้าจาก CSSPMSTR1
    $cust_id = trim($ticket_data['CSTMSPPL_ID'] ?? '');
    if (!empty($cust_id)) {
        $sql_cust = "SELECT CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2, CONSIGNEE_ADDRESS3,
                            UOM_WEIGHT AS CUST_UOM_WEIGHT, UOM_DIMENSION AS CUST_UOM_DIM
                     FROM [MATS-NEWII].dbo.CSSPMSTR1 
                     WHERE CSTMSPPL_ID = :cust_id";
        $stmt_cust = $conn->prepare($sql_cust);
        $stmt_cust->bindParam(':cust_id', $cust_id, PDO::PARAM_STR);
        $stmt_cust->execute();
        $cust_address = $stmt_cust->fetch(PDO::FETCH_ASSOC);
    }
}

// -------------------------------------------------------------------------
// คำนวณและแปลงหน่วย UOM (รองรับทั้ง lbs./inch และ kg./mm.)
// -------------------------------------------------------------------------
$uom_weight_raw = strtolower(trim($ticket_data['CT2U_UOMWEIGHT'] ?? $ticket_data['CTM2_UOMWEIGHT'] ?? $cust_address['CUST_UOM_WEIGHT'] ?? $ticket_data['CRSH_UOMWEIGHT'] ?? ''));
$is_lbs = (strpos($uom_weight_raw, 'lb') !== false || strpos($uom_weight_raw, 'pound') !== false);

$uom_dim_raw = strtolower(trim($ticket_data['CT2U_UOMDIMENSION'] ?? $ticket_data['CTM2_UOMDIMENSION'] ?? $cust_address['CUST_UOM_DIM'] ?? $ticket_data['CRSH_UOMDIMENSION'] ?? ''));
$is_inch = (strpos($uom_dim_raw, 'in') !== false || $is_lbs);

$raw_net_wt = (float)($ticket_data['PACK_NETWEIGHT'] ?? 0);
$raw_pkg_wt = (float)($ticket_data['PACK_PACKAGEWEIGHT'] ?? 0);

if ($is_lbs) {
    $uom_weight = 'lbs.';
    $net_wt = ($raw_net_wt < 2500 && $raw_net_wt > 0) ? round($raw_net_wt * 2.20462262) : round($raw_net_wt);
    $pkg_wt = ($raw_pkg_wt < 200 && $raw_pkg_wt > 0) ? round($raw_pkg_wt * 2.20462262) : round($raw_pkg_wt);
} else {
    $uom_weight = 'kg.';
    $net_wt = round($raw_net_wt);
    $pkg_wt = round($raw_pkg_wt);
}
$gross_wt = $net_wt + $pkg_wt;

// มิติ Thickness, Width, Length
if ($is_inch) {
    $uom_dimension = 'inch';
    $thickness = ((float)($ticket_data['CT2U_THICKNESS'] ?? 0) > 0) ? (float)$ticket_data['CT2U_THICKNESS'] : (float)($ticket_data['THICKNESS'] ?? 0);
    $width     = ((float)($ticket_data['CT2U_WIDTH'] ?? 0) > 0) ? (float)$ticket_data['CT2U_WIDTH'] : (float)($ticket_data['WIDTH'] ?? 0);
    $length    = ((float)($ticket_data['CT2U_LENGTH'] ?? 0) > 0) ? (float)$ticket_data['CT2U_LENGTH'] : (float)($ticket_data['LENGTH'] ?? 0);

    if ($width > 150) $width = $width / 25.4;
    if ($length > 150) $length = $length / 25.4;
} else {
    $uom_dimension = 'mm.';
    $thickness = (float)($ticket_data['THICKNESS'] ?? 0);
    $width     = (float)($ticket_data['WIDTH'] ?? 0);
    $length    = (float)($ticket_data['LENGTH'] ?? 0);
}

// มิติห่อจริง (Package Dimension)
$dw_mm = (float)($ticket_data['CRSH_DIMENSIONWIDTH'] ?? 0);
$dl_mm = (float)($ticket_data['CRSH_DIMENSIONLENGTH'] ?? 0);
$dh_mm = (float)($ticket_data['CRSH_DIMENSIONHIGH'] ?? 0);

if ($is_inch && $dw_mm > 0) {
    $dw = round($dw_mm / 25.4);
    $dl = round($dl_mm / 25.4);
    $dh = round($dh_mm / 25.4);
} else {
    $dw = round($dw_mm);
    $dl = round($dl_mm);
    $dh = round($dh_mm);
}

// ประเภทผลิตภัณฑ์ (CIRCLE / SHEET / NC)
$prod_id_code = strtoupper(trim($ticket_data['PRODUCT_ID'] ?? ''));
$product_type_label = strtoupper(trim($ticket_data['PRODUCT_MODEL'] ?? ''));
if (empty($product_type_label)) {
    if ($prod_id_code === 'CC') $product_type_label = 'CIRCLE';
    elseif ($prod_id_code === 'SH') $product_type_label = 'SHEET';
    else $product_type_label = $prod_id_code ?: 'SHEET';
}

$po_display = trim($ticket_data['CTM2_PONO'] ?? $ticket_data['PACK_PONO'] ?? '');
$po_item    = trim($ticket_data['CTM2_POITEM'] ?? $ticket_data['PACK_POITEM'] ?? '');
if (!empty($po_item)) $po_display .= '/' . $po_item;

$cust_part_no = trim($ticket_data['CTM2_CUSTOMERPARTNO'] ?? '');

$delivery_raw  = !empty($ticket_data['CONFIRM_DATE']) ? $ticket_data['CONFIRM_DATE'] : ($ticket_data['REQUEST_DATE'] ?? $ticket_data['PACK_DATE'] ?? '');
$delivery_date = !empty($delivery_raw) ? date('m/d/Y', strtotime($delivery_raw)) : '-';

$act_coil_w = (float)($ticket_data['F_WIDTH'] ?? 0);
if ($act_coil_w <= 0) {
    $act_coil_w = $is_inch ? ($width * 25.4) : $width;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Product Ticket - <?php echo $prod_no; ?></title>
    <!-- โหลด Bootstrap, jQuery และ JsBarcode เพื่อให้เปิดหน้าใหม่ได้สมบูรณ์ -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <script src="assets/js/jquery.3.2.1.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/JsBarcode.all.min.js"></script>

    <style>
        body {
            background-color: #f1f5f9;
            padding: 20px;
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
        }
        .preview-card {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .preview-header {
            background-color: #0f172a;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-body {
            padding: 20px;
        }
        .preview-footer {
            background-color: #f8fafc;
            padding: 14px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* STYLES ตาราง Ticket */
        .ticket-container {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            border: 2px solid #000;
            color: #000;
            background-color: #fff;
            box-sizing: border-box;
        }
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .ticket-table td, .ticket-table th {
            border: 1.5px solid #000 !important;
            padding: 2px 4px !important;
            vertical-align: middle;
            overflow: hidden;
            box-sizing: border-box;
            height: 28px;
        }
        .header-title-en { font-size: 26.4px; font-weight: 900; line-height: 1.1; color: #000; }
        .header-title-th { font-size: 22.4px; font-weight: 900; line-height: 1.1; color: #000; }
        .header-company-en { font-size: 22.4px; font-weight: 900; line-height: 1.15; color: #000; }
        .header-company-th { font-size: 19.8px; font-weight: 900; line-height: 1.15; color: #000; }
        .header-address { font-size: 12.5px; font-weight: 700; line-height: 1.25; color: #000; }
        .ticket-label-en { font-size: 11.5px; font-weight: 900; text-transform: uppercase; display: block; }
        .ticket-label-th { font-size: 11px; font-weight: 800; color: #000; display: block; }
        .ticket-value-main { font-size: 25.3px; font-weight: 900; text-align: center; }
        .ticket-value-bold { font-size: 18.7px; font-weight: 900; text-align: center; }
        .ticket-value-right { font-size: 18.7px; font-weight: 900; text-align: right; }
        .ticket-unit { font-size: 16.5px; font-weight: 800; margin-left: 4px; }

        .btn-print-ticket-action {
            background-color: #16a34a !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 15px !important;
            padding: 10px 24px !important;
            border-radius: 6px !important;
            border: none !important;
            cursor: pointer;
        }
        .btn-print-ticket-action:hover { background-color: #15803d !important; }
    </style>
</head>
<body>

<div class="preview-card">
    <div class="preview-header">
        <h4 style="margin:0; font-weight:700;">🖨️ Product Ticket (<?php echo htmlspecialchars($product_type_label); ?>) - <?php echo htmlspecialchars($ticket_data['PRODUCT_NO'] ?? $prod_no); ?></h4>
    </div>

    <div class="preview-body">
        <?php if (!empty($ticket_data)): ?>
        <div id="printableTicket" class="ticket-container">
            <table class="ticket-table">
                <colgroup>
                    <col style="width: 28%;">
                    <col style="width: 30%;">
                    <col style="width: 17%;">
                    <col style="width: 15%;">
                    <col style="width: 10%;">
                </colgroup>

                <!-- Header บริษัท -->
                <tr>
                    <td style="padding: 6px 8px !important; vertical-align: middle;">
                        <div class="header-title-en">PRODUCT TICKET</div>
                        <div class="header-title-th">ใบกำกับผลิตภัณฑ์</div>
                    </td>
                    <td colspan="3" style="padding: 6px 8px !important; vertical-align: middle;">
                        <div class="header-company-en">MEYER ALUMINIUM (THAILAND) CO.,LTD.</div>
                        <div class="header-company-th">ไมย์เออร์ อลูมิเนียม (ประเทศไทย) จำกัด</div>
                        <div class="header-address">
                            38/32 Moo. 5, Laem Chabang Industrial Estate, Sriracha, Chonburi, 20230 Thailand<br>
                            38/32 หมู่ 5, นิคมอุตสาหกรรมแหลมฉบัง, ศรีราชา, ชลบุรี, ประเทศไทย 20230<br>
                            Tel. 6638-400652-62, FAX : 6638-400663
                        </div>
                    </td>
                    <td style="text-align: center; vertical-align: middle; font-size: 14.5px; font-weight: 800;">
                        QC Label
                    </td>
                </tr>

                <!-- Customer Name & Address / Shipping Mark -->
                <tr>
                    <td colspan="2" style="vertical-align: top; padding: 4px 6px !important;">
                        <span class="ticket-label-en" style="display:inline; font-size: 13.2px;">CUSTOMER NAME / </span>
                        <span class="ticket-label-th" style="display:inline; font-size: 12.65px;">ชื่อลูกค้า</span>
                        <span style="font-size: 15.8px; font-weight: 900;"> - <?php echo htmlspecialchars($cust_id ?? ''); ?></span>
                        
                        <div style="font-size: 13.9px; font-weight: 800; margin-top: 2px; line-height: 1.2; color: #000;">
                            <?php if (!empty($cust_address)): ?>
                                <strong><?php echo htmlspecialchars($cust_address['CONSIGNEE_COMPANY'] ?? ''); ?></strong><br>
                                <?php if(!empty($cust_address['CONSIGNEE_ADDRESS1'])): ?>
                                    <?php echo htmlspecialchars($cust_address['CONSIGNEE_ADDRESS1']); ?><br>
                                <?php endif; ?>
                                <?php if(!empty($cust_address['CONSIGNEE_ADDRESS2'])): ?>
                                    <?php echo htmlspecialchars($cust_address['CONSIGNEE_ADDRESS2']); ?><br>
                                <?php endif; ?>
                                <?php if(!empty($cust_address['CONSIGNEE_ADDRESS3'])): ?>
                                    <?php echo htmlspecialchars($cust_address['CONSIGNEE_ADDRESS3']); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($cust_id ?: '-'); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td colspan="3" style="vertical-align: top; padding: 4px 6px !important;">
                        <span class="ticket-label-en" style="font-size: 13.2px;">SHIPPING MARK</span>
                        <div style="font-size: 25.3px; font-weight: 900; text-align: center; margin-top: 2px;">
                            <?php echo htmlspecialchars($po_display ?: '-'); ?>
                        </div>
                    </td>
                </tr>

                <!-- Product No & Barcode -->
                <tr>
                    <td>
                        <span class="ticket-label-en">PRODUCT NO.</span>
                        <span class="ticket-label-th">หมายเลขผลิตภัณฑ์</span>
                    </td>
                    <td style="text-align: center;">
                        <div class="ticket-value-main"><?php echo htmlspecialchars($ticket_data['PRODUCT_NO'] ?? ''); ?></div>
                    </td>
                    <td colspan="3" style="text-align: center; padding: 1px 2px !important;">
                        <svg id="barcode_canvas" style="width: 200px; height: 32px; display: inline-block;"></svg>
                    </td>
                </tr>

                <!-- Customer Part No / Product (CIRCLE / SHEET) -->
                <tr>
                    <td>
                        <span class="ticket-label-en">CUSTOMER PART NO.</span>
                        <span class="ticket-label-th">หมายเลขผลิตภัณฑ์ลูกค้า</span>
                    </td>
                    <td class="ticket-value-bold">
                        <?php echo htmlspecialchars($cust_part_no); ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">PRODUCT</span>
                        <span class="ticket-label-th">ผลิตภัณฑ์</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo htmlspecialchars($product_type_label); ?>
                    </td>
                </tr>

                <!-- Purchase Order / Alloy -->
                <tr>
                    <td>
                        <span class="ticket-label-en">PURCHASE ORDER</span>
                        <span class="ticket-label-th">เลขที่ใบสั่งซื้อสินค้า</span>
                    </td>
                    <td class="ticket-value-bold">
                        <?php echo htmlspecialchars($po_display ?: '-'); ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">ALLOY</span>
                        <span class="ticket-label-th">อัลลอย</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo htmlspecialchars($ticket_data['ALLOY'] ?? '-'); ?>
                    </td>
                </tr>

                <!-- Sale Order / Temper -->
                <tr>
                    <td>
                        <span class="ticket-label-en">SALE ORDER</span>
                        <span class="ticket-label-th">เลขที่ใบขายสินค้า</span>
                    </td>
                    <td class="ticket-value-bold">
                        <?php echo htmlspecialchars(($ticket_data['SALEORDER_NO'] ?? '') . ' :' . ($ticket_data['SALEORDER_ITEM'] ?? '')); ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">TEMPER</span>
                        <span class="ticket-label-th">เทมเปอร์</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo htmlspecialchars($ticket_data['TEMPER'] ?? '-'); ?>
                    </td>
                </tr>

                <!-- Job Order / Thickness -->
                <tr>
                    <td>
                        <span class="ticket-label-en">JOB ORDER</span>
                        <span class="ticket-label-th">เลขที่ใบสั่งผลิต</span>
                    </td>
                    <td class="ticket-value-bold">
                        <?php echo htmlspecialchars($ticket_data['JOB_ORDER'] ?? ''); ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">THICKNESS</span>
                        <span class="ticket-label-th">ความหนา</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo fmt3($thickness); ?>
                        <span class="ticket-unit"><?php echo $uom_dimension; ?></span>
                    </td>
                </tr>

                <!-- Delivery Date / Width -->
                <tr>
                    <td>
                        <span class="ticket-label-en">DELIVERY DATE</span>
                        <span class="ticket-label-th">วันที่ส่งสินค้า</span>
                    </td>
                    <td class="ticket-value-bold">
                        <?php echo $delivery_date; ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">WIDTH</span>
                        <span class="ticket-label-th">ความกว้าง</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo fmt3($width); ?>
                        <span class="ticket-unit"><?php echo $uom_dimension; ?></span>
                    </td>
                </tr>

                <!-- Net Weight / Length -->
                <tr>
                    <td>
                        <span class="ticket-label-en">NET WEIGHT</span>
                        <span class="ticket-label-th">น.น. สุทธิ</span>
                    </td>
                    <td class="ticket-value-right">
                        <?php echo number_format($net_wt, 2); ?> <?php echo $uom_weight; ?>
                    </td>
                    <td>
                        <span class="ticket-label-en">LENGTH</span>
                        <span class="ticket-label-th">ความยาว</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php if ($length > 0): ?>
                            <?php echo fmt3($length); ?>
                        <?php endif; ?>
                        <span class="ticket-unit" style="margin-left: 0;"><?php echo $uom_dimension; ?></span>
                    </td>
                </tr>

                <!-- Package Weight / Package Dimension Header -->
                <tr>
                    <td>
                        <span class="ticket-label-en">PACKAGE WEIGHT</span>
                        <span class="ticket-label-th">น.น. หีบห่อ</span>
                    </td>
                    <td class="ticket-value-right">
                        <?php echo number_format($pkg_wt, 2); ?> <?php echo $uom_weight; ?>
                    </td>
                    <td colspan="3" style="text-align: center; vertical-align: middle;">
                        <span class="ticket-label-en" style="display:inline;">PACKAGE DIMENSION</span>
                        <span class="ticket-label-th" style="display:inline-block; margin-left: 5px;">ขนาดหีบห่อ</span>
                    </td>
                </tr>

                <!-- Gross Weight / Package Dimension Value -->
                <tr>
                    <td>
                        <span class="ticket-label-en">GROSS WEIGHT</span>
                        <span class="ticket-label-th">น.น. รวม</span>
                    </td>
                    <td class="ticket-value-right">
                        <?php echo number_format($gross_wt, 2); ?> <?php echo $uom_weight; ?>
                    </td>
                    <td colspan="3" style="text-align: center; vertical-align: middle;">
                        <div style="font-size: 18.7px; font-weight: 900; white-space: nowrap;">
                            <?php echo $dw; ?> &nbsp; x &nbsp; 
                            <?php echo $dl; ?> &nbsp; x &nbsp; 
                            <?php echo $dh; ?>
                        </div>
                    </td>
                </tr>

                <!-- Produce Date (Cast No) / Actual Width -->
                <tr>
                    <td>
                        <span class="ticket-label-en">PRODUCE DATE / CAST NO.</span>
                        <span class="ticket-label-th">วันที่ผลิต / เลขที่ม้วน</span>
                    </td>
                    <td style="text-align: center; font-size: 16.5px; font-weight: 900;">
                        <?php 
                            $p_date = !empty($ticket_data['PRODUCE_DATE']) ? date('m/d/Y', strtotime($ticket_data['PRODUCE_DATE'])) : date('m/d/Y');
                            $coil_ref = trim($ticket_data['COIL_NO'] ?? '');
                            echo $p_date . (!empty($coil_ref) ? " ({$coil_ref})" : "");
                        ?>
                    </td>
                    <td>
                        <span class="ticket-label-en" style="text-align: center;">ACTUAL WIDTH OF COIL</span>
                    </td>
                    <td colspan="2" class="ticket-value-bold">
                        <?php echo ($act_coil_w > 0) ? fmt2($act_coil_w) : '-'; ?>
                        <span class="ticket-unit">mm.</span>
                    </td>
                </tr>
            </table>
        </div>
        <?php else: ?>
            <div style="text-align:center; padding: 40px; color: #ef4444; font-weight: 700;">
                ⚠️ ไม่พบข้อมูล Ticket สำหรับรายการนี้ (<?php echo $prod_no; ?>) — กรุณาตรวจสอบว่าได้กดบันทึก Packing (PACKPROD1) แล้ว
            </div>
        <?php endif; ?>
    </div>

    <div class="preview-footer">
        <button type="button" class="btn btn-default" onclick="window.close();" style="font-weight:600; padding:10px 22px;">ปิดหน้าต่าง</button>
        <?php if (!empty($ticket_data)): ?>
        <button type="button" class="btn btn-print-ticket-action" onclick="printTicketViaIframe();">
            🖨️ พิมพ์ Ticket (Print)
        </button>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    if (typeof JsBarcode !== 'undefined' && "<?php echo $ticket_data['PRODUCT_NO'] ?? ''; ?>") {
        JsBarcode("#barcode_canvas", "<?php echo $ticket_data['PRODUCT_NO']; ?>", {
            format: "CODE128",
            displayValue: false,
            height: 38,
            margin: 0
        });
    }
});

// ฟังก์ชันพิมพ์ผ่าน Hidden iframe
function printTicketViaIframe() {
    var ticketHTML = document.getElementById('printableTicket').outerHTML;
    
    var iframe = document.getElementById('print_ticket_iframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'print_ticket_iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
    }

    var doc = iframe.contentWindow.document;
    doc.open();
    doc.write('<html><head><title>Print Product Ticket</title>');
    doc.write('<style>');
    doc.write(`
        @page {
            size: A4 portrait;
            margin: 5mm;
        }
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: "Segoe UI", "Sarabun", sans-serif;
            color: #000000;
        }
        .ticket-container {
            width: 100% !important;
            max-width: 100% !important;
            border: 2px solid #000;
            box-sizing: border-box;
            background: #fff;
        }
        .ticket-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
        }
        .ticket-table td, .ticket-table th {
            border: 1.5px solid #000 !important;
            padding: 2px 4px !important;
            vertical-align: middle;
            overflow: hidden;
            box-sizing: border-box;
            height: 28px;
        }
        .header-title-en { font-size: 24.2px; font-weight: 900; line-height: 1.1; }
        .header-title-th { font-size: 19.8px; font-weight: 900; line-height: 1.1; }
        .header-company-en { font-size: 20.9px; font-weight: 900; line-height: 1.15; }
        .header-company-th { font-size: 17.6px; font-weight: 900; line-height: 1.15; }
        .header-address { font-size: 11.5px; font-weight: 700; line-height: 1.25; }
        .ticket-label-en { font-size: 11px; font-weight: 900; text-transform: uppercase; display: block; }
        .ticket-label-th { font-size: 10.5px; font-weight: 800; display: block; }
        .ticket-value-main { font-size: 23.1px; font-weight: 900; text-align: center; }
        .ticket-value-bold { font-size: 17.6px; font-weight: 900; text-align: center; }
        .ticket-value-right { font-size: 17.6px; font-weight: 900; text-align: right; }
        .ticket-unit { font-size: 15.4px; font-weight: 800; margin-left: 4px; }
        #barcode_canvas { width: 100% !important; max-width: 190px !important; height: 32px !important; }
    `);
    doc.write('</style></head><body>');
    doc.write(ticketHTML);
    doc.write('</body></html>');
    doc.close();

    setTimeout(function() {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }, 200);
}
</script>

</body>
</html>