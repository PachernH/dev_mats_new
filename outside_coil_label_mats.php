<!-- Outside Coil Label Modal Component -->
<style>
    /* สไตล์ Label หน้าจอปกติ */
    .outside-label-wrapper {
        width: 100%;
        max-width: 680px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    .outside-label-table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid #000000;
        background: #ffffff;
        font-family: Arial, sans-serif;
        color: #000000;
        table-layout: fixed; /* ล็อกขนาดตารางไม่ให้ล้นขอบ */
    }
    .outside-label-table td {
        border: 2px solid #000000;
        padding: 10px 12px;
        font-weight: bold;
        font-size: 20px;
        vertical-align: middle;
        color: #000000;
        line-height: 1.25;
        word-wrap: break-word;
        box-sizing: border-box;
    }
    /* หัวตารางจัดให้อยู่ในบรรทัดเดียวและพอดีกับขอบ */
    .outside-label-header-td {
        text-align: center;
        font-size: 17px !important;
        font-weight: 900;
        padding: 10px 2px !important;
        letter-spacing: -0.3px;
        white-space: nowrap !important;
    }
    .outside-label-tag {
        text-align: right;
        font-size: 14px;
        font-weight: normal;
        padding: 4px 2px 0 0;
        color: #000000;
    }

    /* สไตล์สั่งพิมพ์ */
    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        body * {
            visibility: hidden !important;
        }
        #print_outside_box, #print_outside_box * {
            visibility: visible !important;
        }
        #print_outside_box {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #print_outside_box .outside-label-table {
            width: 100% !important;
            border: 2px solid #000000 !important;
            table-layout: fixed !important;
        }
        #print_outside_box .outside-label-header-td {
            font-size: 19px !important;
            font-weight: 900 !important;
            padding: 12px 2px !important;
            white-space: nowrap !important;
            letter-spacing: -0.5px !important;
        }
        #print_outside_box .outside-label-table td {
            font-size: 22px !important;
            font-weight: 800 !important;
            padding: 12px 14px !important;
            border: 2px solid #000000 !important;
            line-height: 1.3 !important;
            box-sizing: border-box !important;
        }
        #print_outside_box .outside-label-tag {
            font-size: 15px !important;
            padding-top: 6px !important;
        }
        .modal-footer, .modal-header, .close {
            display: none !important;
        }
    }
</style>

<div class="modal fade" id="modalOutsideCoil" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header" style="background-color: #0f172a; color: #ffffff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700;">🏷️ Outside Coil Ticket</h4>
            </div>
            <div class="modal-body" style="padding: 24px; background-color: #f8fafc;">
                <div class="outside-label-wrapper" id="print_outside_box">
                    <table class="outside-label-table">
                        <tr>
                            <td colspan="2" class="outside-label-header-td">MANAKIN INDUSTRIES, LLC (MADE IN THAILAND)</td>
                        </tr>
                        <tr>
                            <td style="width: 42%;">PURCHASE ORDER</td>
                            <td style="width: 58%;"><?php echo htmlspecialchars($so_data['CTM2_CUSTOMERORDER'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>SALE ORDER #</td>
                            <td>#<?php echo htmlspecialchars($so_data['CTM2_CUSTOMERSALEORDER'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>CUSTOMER PO #</td>
                            <td><?php echo htmlspecialchars($so_data['CTM2_ENDCUSTOMERPO'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>CUSTMER PART #</td>
                            <td><?php echo htmlspecialchars($so_data['CTM2_CUSTOMERPARTNO'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>MFG DATE</td>
                            <td>
                                <?php 
                                    $mfg_raw = $job_data['JOBORDER_DATE'] ?? '';
                                    echo htmlspecialchars(!empty($mfg_raw) ? date('Y-m-d', strtotime($mfg_raw)) : date('Y-m-d')); 
                                ?>
                            </td>
                        </tr>
                    </table>
                    <div class="outside-label-tag">Outside Packaging</div>
                </div>
            </div>

            <!-- ปรับแต่งสไตล์ปุ่มกดตรง Modal Footer ให้สวยงาม มีมิติ -->
            <div class="modal-footer" style="background-color: #ffffff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 16px 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color: #ffffff; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; padding: 10px 22px; border-radius: 8px; font-size: 14px; transition: all 0.2s;">
                    ปิด
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print();" style="background-color: #f97316; color: #ffffff; border: none; font-weight: 700; padding: 10px 26px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(249, 115, 22, 0.3); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s;">
                    🖨️ พิมพ์ Outside Coil
                </button>
            </div>
        </div>
    </div>
</div>