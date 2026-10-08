<!-- Inside Coil Label Modal Component -->
<style>
    /* สไตล์ Label หน้าจอปกติ (ลด Font ลง 5%) */
    .inside-label-wrapper {
        width: 100%;
        max-width: 700px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    .inside-label-table {
        width: 100%;
        border-collapse: collapse;
        border: 2px solid #000000;
        background: #ffffff;
        font-family: Arial, sans-serif;
        color: #000000;
        table-layout: fixed;
    }
    .inside-label-table td {
        border: 2px solid #000000;
        padding: 8px 11px;
        font-weight: bold;
        font-size: 20px; /* ลดลง 5% จาก 21px */
        vertical-align: middle;
        color: #000000;
        line-height: 1.25;
        word-wrap: break-word;
        box-sizing: border-box;
    }
    .inside-label-header-td {
        text-align: center;
        font-size: 18px !important; /* ลดลง 5% จาก 19px */
        font-weight: 900;
        padding: 10px 2px !important;
        letter-spacing: -0.2px;
        white-space: nowrap !important;
    }
    .inside-label-tag {
        text-align: right;
        font-size: 14px; /* ลดลง 5% จาก 15px */
        font-weight: normal;
        padding: 5px 2px 0 0;
        color: #000000;
    }

    /* สไตล์สั่งพิมพ์ (ลด Font ลง 5% เช่นเดียวกัน) */
    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        body * {
            visibility: hidden !important;
        }
        #print_inside_box, #print_inside_box * {
            visibility: visible !important;
        }
        #print_inside_box {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #print_inside_box .inside-label-table {
            width: 100% !important;
            border: 2px solid #000000 !important;
            table-layout: fixed !important;
        }
        #print_inside_box .inside-label-header-td {
            font-size: 20px !important; /* ลดลง 5% จาก 21px */
            font-weight: 900 !important;
            padding: 11px 2px !important;
            white-space: nowrap !important;
            letter-spacing: -0.3px !important;
        }
        #print_inside_box .inside-label-table td {
            font-size: 23px !important; /* ลดลง 5% จาก 24px */
            font-weight: 800 !important;
            padding: 10px 12px !important;
            border: 2px solid #000000 !important;
            line-height: 1.3 !important;
            box-sizing: border-box !important;
        }
        #print_inside_box .inside-label-tag {
            font-size: 16px !important; /* ลดลง 5% จาก 17px */
            padding-top: 6px !important;
        }
        .modal-footer, .modal-header, .close {
            display: none !important;
        }
    }
</style>

<div class="modal fade" id="modalInsideCoil" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header" style="background-color: #0f172a; color: #ffffff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #ffffff; opacity: 0.8;">&times;</button>
                <h4 class="modal-title" style="font-weight: 700;">🏷️ Inside Coil Ticket</h4>
            </div>
            <div class="modal-body" style="padding: 24px; background-color: #f8fafc;">
                <div class="inside-label-wrapper" id="print_inside_box">
                    <table class="inside-label-table">
                        <tr>
                            <td colspan="2" class="inside-label-header-td">MANAKIN INDUSTRIES, LLC (MADE IN THAILAND)</td>
                        </tr>
                        <tr>
                            <td style="width: 42%;">COIL NUMBER</td>
                            <td style="width: 58%;" id="in_lbl_coil_no">-</td>
                        </tr>
                        <tr>
                            <td>ALLOY & TEMPER</td>
                            <td><?php echo htmlspecialchars(($so_data['ALLOY'] ?? $job_data['ALLOY'] ?? '') . ' - ' . ($so_data['TEMPER'] ?? $job_data['TEMPER'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <td>THICKNESS X<br>WIDTH</td>
                            <td>
                                <div><?php echo fmt3($so_data['THICKNESS'] ?? 0); ?> X <?php echo fmt3($so_data['WIDTH'] ?? 0); ?> <?php echo htmlspecialchars($so_data['CTM2_UOMDIMENSION'] ?? 'mm.'); ?></div>
                                <div><?php echo fmt3($so_data['CT2U_THICKNESS'] ?? 0); ?> X <?php echo fmt3($so_data['CT2U_WIDTH'] ?? 0); ?> <?php echo htmlspecialchars($so_data['CT2U_UOMDIMENSION'] ?? 'inch'); ?></div>
                            </td>
                        </tr>
                        <tr>
                            <td>NET WEIGHT &<br>GROSS WEIGHT</td>
                            <td>
                                <div><span id="in_lbl_net_kg">0</span> - <span id="in_lbl_gross_kg">0</span> kg.</div>
                                <div><span id="in_lbl_net_lbs">0</span> - <span id="in_lbl_gross_lbs">0</span> lbs.</div>
                            </td>
                        </tr>
                        <tr>
                            <td>ORDER REFS</td>
                            <td>MANAKIN SALE ORDER<br>#<?php echo htmlspecialchars($so_data['CTM2_CUSTOMERSALEORDER'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td>CUSTOMER PO #</td>
                            <td><?php echo htmlspecialchars($so_data['CTM2_ENDCUSTOMERPO'] ?? '-'); ?></td>
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
                    <div class="inside-label-tag">Inside Coil</div>
                </div>
            </div>
            
            <!-- ปรับแต่งสไตล์ปุ่มกดตรง Modal Footer ให้สวยงาม มีมิติ -->
            <div class="modal-footer" style="background-color: #ffffff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 16px 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="background-color: #ffffff; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; padding: 10px 22px; border-radius: 8px; font-size: 14px; transition: all 0.2s;">
                    ปิด
                </button>
                <button type="button" class="btn btn-primary" onclick="window.print();" style="background-color: #10b981; color: #ffffff; border: none; font-weight: 700; padding: 10px 26px; border-radius: 8px; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s;">
                    🖨️ พิมพ์ Inside Coil
                </button>
            </div>
        </div>
    </div>
</div>