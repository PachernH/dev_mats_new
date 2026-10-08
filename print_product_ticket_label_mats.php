<?php
/**
 * Component: Print Product Ticket Label Modal (Loaded via AJAX)
 * File: print_product_ticket_label_mats.php
 */

include("dbcon_mats-new.php");
include 'function_mats.php';

$prod_no   = isset($_GET['productno']) ? htmlspecialchars(trim($_GET['productno']), ENT_QUOTES, 'UTF-8') : '';$job_order = isset($_GET['joborder']) ? htmlspecialchars(trim($_GET['joborder']), ENT_QUOTES, 'UTF-8') : '';

$ticket_data  = null;
$so_data       = null;
$cust_address  = null;

if (!empty($prod_no) && !empty($job_order)) {
    // 1. ดึงข้อมูล PACKPROD1 ร่วมกับ COILPROD1
    $sql_ticket = "SELECT P.PRODUCT_NO, P.JOB_ORDER, P.SALEORDER_NO, P.SALEORDER_ITEM, P.PACK_PONO, P.PACK_POITEM,
                          P.PACK_COMBINEWEIGHT, P.PACK_PACKAGEWEIGHT, P.PACK_NETWEIGHT, P.PACK_DATE,
                          C.ALLOY, C.TEMPER, C.THICKNESS, C.WIDTH, C.ACTUAL_WIDTH,
                          C.COIL_DIMENSIONWIDTH, C.COIL_DIMENSIONLENGTH, C.COIL_DIMENSIONHIGH,
                          C.COIL_UOMDIMENSION, C.COIL_UOMWEIGHT,
                          COALESCE(C.PRODUCE_DATE, C.COIL_OPERATEDATE) AS PRODUCE_DATE
                   FROM PACKPROD1 P
                   LEFT JOIN COILPROD1 C ON P.PRODUCT_NO = C.COIL_NO
                   WHERE P.PRODUCT_NO = :prod_no AND P.JOB_ORDER = :job_order";
    $stmt_ticket =$conn->prepare($sql_ticket);$stmt_ticket->bindParam(':prod_no', $prod_no, PDO::PARAM_STR);$stmt_ticket->bindParam(':job_order', $job_order, PDO::PARAM_STR);$stmt_ticket->execute();
    $ticket_data =$stmt_ticket->fetch(PDO::FETCH_ASSOC);

    // 2. ดึงข้อมูล SALEORDER เพื่อหา CSTMSPPL_ID
    if ($ticket_data && !empty($ticket_data['SALEORDER_NO'])) {$sql_so = "SELECT CSTMSPPL_ID FROM CSTMORDR2 WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
        $stmt_so =$conn->prepare($sql_so);$stmt_so->bindParam(':so_no', $ticket_data['SALEORDER_NO'], PDO::PARAM_STR);$stmt_so->bindParam(':so_item', $ticket_data['SALEORDER_ITEM'], PDO::PARAM_STR);$stmt_so->execute();
        $so_data =$stmt_so->fetch(PDO::FETCH_ASSOC);
    }

    // 3. ดึงชื่อและที่อยู่ลูกค้าจาก CSSPMSTR1
    $cust_id =$so_data['CSTMSPPL_ID'] ?? '';
    if (!empty($cust_id)) {$sql_cust = "SELECT CONSIGNEE_COMPANY, CONSIGNEE_ADDRESS1, CONSIGNEE_ADDRESS2 
                     FROM CSSPMSTR1 
                     WHERE CSTMSPPL_ID = :cust_id";
        $stmt_cust =$conn->prepare($sql_cust);$stmt_cust->bindParam(':cust_id', $cust_id, PDO::PARAM_STR);$stmt_cust->execute();
        $cust_address =$stmt_cust->fetch(PDO::FETCH_ASSOC);
    }
}

$uom_weight    = !empty($ticket_data['COIL_UOMWEIGHT']) ? htmlspecialchars($ticket_data['COIL_UOMWEIGHT']) : 'lbs.';$uom_dimension = !empty($ticket_data['COIL_UOMDIMENSION']) ? htmlspecialchars($ticket_data['COIL_UOMDIMENSION']) : 'inch';
?>

<!-- CDN สำหรับสร้าง Barcode -->
<script src="assets/js/JsBarcode.all.min.js"></script>

<style>
    /* STYLES แสดงผลบนหน้าจอ Modal (ปรับเพิ่มขนาด Font 10%) */
    .ticket-container {
        width: 100%;
        max-width: 820px;
        margin: 0 auto;
        border: 2px solid #000;
        font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
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
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.4) !important;
        transition: all 0.2s !important;
        cursor: pointer;
    }
    .btn-print-ticket-action:hover {
        background-color: #15803d !important;
    }
</style>

<!-- Modal Pop-up: PRODUCT TICKET REPORT -->
<div class="modal fade" id="modalPrintTicket" tabindex="-1" role="dialog" aria-labelledby="modalPrintTicketLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="width: 860px;">
        <div class="modal-content" style="border-radius:8px;">
            <div class="modal-header" style="background-color:#0f172a; color:#fff; padding:12px 20px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                <h4 class="modal-title" style="font-weight:700;">🖨️ Product Ticket - <?php echo htmlspecialchars($ticket_data['PRODUCT_NO'] ?? ''); ?></h4>
            </div>

            <div class="modal-body" style="padding:20px; background-color: #f8fafc;">
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
                                            <?php echo htmlspecialchars($cust_address['CONSIGNEE_ADDRESS2']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($cust_id ?: '-'); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td colspan="3" style="vertical-align: top; padding: 4px 6px !important;">
                                <span class="ticket-label-en" style="font-size: 13.2px;">SHIPPING MARK</span>
                                <div style="font-size: 25.3px; font-weight: 900; text-align: center; margin-top: 2px;">
                                    <?php echo htmlspecialchars($ticket_data['PACK_PONO'] ?? '-'); ?>
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

                        <!-- Customer Part No / Product -->
                        <tr>
                            <td>
                                <span class="ticket-label-en">CUSTOMER PART NO.</span>
                                <span class="ticket-label-th">หมายเลขผลิตภัณฑ์ลูกค้า</span>
                            </td>
                            <td></td>
                            <td>
                                <span class="ticket-label-en">PRODUCT</span>
                                <span class="ticket-label-th">ผลิตภัณฑ์</span>
                            </td>
                            <td colspan="2" class="ticket-value-bold">COIL</td>
                        </tr>

                        <!-- Purchase Order / Alloy -->
                        <tr>
                            <td>
                                <span class="ticket-label-en">PURCHASE ORDER</span>
                                <span class="ticket-label-th">เลขที่ใบสั่งซื้อสินค้า</span>
                            </td>
                            <td class="ticket-value-bold">
                                <?php echo htmlspecialchars(($ticket_data['PACK_PONO'] ?? '') . ($ticket_data['PACK_POITEM'] ? '/' .$ticket_data['PACK_POITEM'] : '')); ?>
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
                                <?php echo fmt3($ticket_data['THICKNESS'] ?? 0); ?>
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
                                <?php echo !empty($ticket_data['PACK_DATE']) ? date('m/d/Y', strtotime($ticket_data['PACK_DATE'])) : '-'; ?>
                            </td>
                            <td>
                                <span class="ticket-label-en">WIDTH</span>
                                <span class="ticket-label-th">ความกว้าง</span>
                            </td>
                            <td colspan="2" class="ticket-value-bold">
                                <?php echo fmt3($ticket_data['WIDTH'] ?? 0); ?>
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
                                <?php echo number_format((float)($ticket_data['PACK_NETWEIGHT'] ?? 0), 2); ?> <?php echo$uom_weight; ?>
                            </td>
                            <td>
                                <span class="ticket-label-en">LENGTH</span>
                                <span class="ticket-label-th">ความยาว</span>
                            </td>
                            <td colspan="2" class="ticket-value-bold">
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
                                <?php echo number_format((float)($ticket_data['PACK_PACKAGEWEIGHT'] ?? 0), 2); ?> <?php echo$uom_weight; ?>
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
                                <?php echo number_format((float)(($ticket_data['PACK_NETWEIGHT']+$ticket_data['PACK_PACKAGEWEIGHT']) ?? 0), 2); ?> <?php echo$uom_weight; ?>
                            </td>
                            <td colspan="3" style="text-align: center; vertical-align: middle;">
                                <div style="font-size: 18.7px; font-weight: 900; white-space: nowrap;">
                                    <?php echo (int)($ticket_data['COIL_DIMENSIONWIDTH'] ?? 0); ?> &nbsp; x &nbsp; 
                                    <?php echo (int)($ticket_data['COIL_DIMENSIONLENGTH'] ?? 0); ?> &nbsp; x &nbsp; 
                                    <?php echo (int)($ticket_data['COIL_DIMENSIONHIGH'] ?? 0); ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Produce Date / Actual Width -->
                        <tr>
                            <td>
                                <span class="ticket-label-en">PRODUCE DATE</span>
                                <span class="ticket-label-th">วันที่ผลิต</span>
                            </td>
                            <td style="text-align: center; font-size: 18.7px; font-weight: 900;">
                                <?php echo !empty($ticket_data['PRODUCE_DATE']) ? date('m/d/Y', strtotime($ticket_data['PRODUCE_DATE'])) : date('m/d/Y'); ?>
                            </td>
                            <td>
                                <span class="ticket-label-en" style="text-align: center;">ACTUAL WIDTH OF COIL</span>
                            </td>
                            <td colspan="2" class="ticket-value-bold">
                                <?php echo fmt2($ticket_data['ACTUAL_WIDTH'] ?? $ticket_data['WIDTH'] ?? 0); ?>
                                <span class="ticket-unit"><?php echo $uom_dimension; ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php else: ?>
                    <div style="text-align:center; padding: 40px; color: #ef4444; font-weight: 700;">
                        ⚠️ ไม่พบข้อมูล Ticket สำหรับรายการนี้
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="background-color:#f8fafc; padding:16px 20px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight:600; padding:10px 22px;">ปิด</button>
                <?php if (!empty($ticket_data)): ?>
                <button type="button" class="btn btn-print-ticket-action" onclick="printTicketViaIframe();">
                    🖨️ พิมพ์ Ticket (Print)
                </button>
                <?php endif; ?>
            </div>
        </div>
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
    $('#modalPrintTicket').modal('show');
});

// ฟังก์ชันพิมพ์ผ่าน Hidden iframe (เพิ่ม Font Size +10% ในโหมดการพิมพ์)
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