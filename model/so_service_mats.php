<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

include_once __DIR__ . '/../dbcon_mats-new.php';

if (empty($_SESSION['ID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Session expired.']);
    exit();
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

try {
    switch ($action) {
        case 'get_so_details':
            getSODetails($conn);
            break;

        case 'get_customer_info':
            getCustomerInfo($conn);
            break;

        case 'get_lme_rates':
            getLMERates($conn);
            break;

        case 'calculate_price':
            calculatePriceAPI($conn);
            break;

        case 'save_sales_order':
            saveSalesOrder($conn);
            break;

        case 'delete_sales_order':
            deleteSalesOrder($conn);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

// =========================================================================
// Helper: ตรวจสอบและบันทึกลงตาราง Dimension Master อัตโนมัติตาม Logic S1
// =========================================================================
function ensureDimensionMaster($conn, $table, $column, $value) {
    $val = (float)$value;
    if ($val <= 0) return;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :val");
    $stmt->execute([':val' => $val]);
    if ($stmt->fetchColumn() == 0) {
        $ins = $conn->prepare("INSERT INTO {$table} ({$column}) VALUES (:val)");
        $ins->execute([':val' => $val]);
    }
}

// =========================================================================
// 1. Get SO Details
// =========================================================================
function getSODetails($conn) {
    $sono = isset($_GET['sono']) ? trim($_GET['sono']) : '';
    if (empty($sono)) {
        echo json_encode(['success' => false, 'message' => 'SO number is required.']);
        return;
    }

    $sql_h = "SELECT h.SALEORDER_NO, h.ORDER_CATEGORY, h.SALEORDER_DATE, h.INTENDSHIP_DATE, 
                     h.CSTMSPPL_ID, h.CTM1_PONO, h.CTM1_CUSTOMERORDER, h.CTM1_CUSTOMERSALEORDER,
                     h.CTM1_ENDCUSTOMERNAME, h.CTM1_ENDCUSTOMERPO, h.SUPPLIER_MATCODE, h.CUSTOMS_CODE,
                     h.CURRENCY_ID, h.EXCHANGE_RATE, h.PAYMENT_ID, h.SHIPMENT_ID,
                     h.CTM1_PORTLOADING, h.CTM1_PORTDISCHARGE, h.CTM1_PLACEDESTINATION, h.CTM1_COUNTRYDESTINATION,
                     h.CTM1_PACKING, h.CTM1_TOLERANCE, h.CTM1_SHIPPEDBY, h.CTM1_TAXPERCENT,
                     h.CTM1_TRANSPORTATION, h.CTM1_OVERSEAFREIGHT, h.CTM1_INSURANCE,
                     h.CTM1_FOBTOTALAMOUNT, h.CTM1_GRANDTOTALAMOUNT, h.CTM1_TOTALAMOUNT,
                     h.CTM1_REMARK1, h.CTM1_REMARK2, h.CTM1_REMARK3,
                     h.CTM1_LMETYPE, h.CTM1_LMEDATE, h.CTM1_ORDERLME, h.CTM1_MJPDATE, h.CTM1_MJPPRICE,
                     h.CTM1_ORDERSTDV, h.CTM1_ORDERFACTOR, h.CTM1_ORDERSTDXFACTOR,
                     cat.DESCRIPTION  AS CATEGORY_DESC,
                     cust.CONSIGNEE_COMPANY AS CUSTOMER_NAME,
                     pmt.DESCRIPTION  AS PAYMENT_DESC,
                     spt.DESCRIPTION  AS SHIPMENT_DESC,
                     pl.DESCRIPTION   AS PORTLOADING_DESC,
                     pd.DESCRIPTION   AS PORTDISCHARGE_DESC,
                     pld.DESCRIPTION  AS PLACEDEST_DESC,
                     pcd.DESCRIPTION  AS COUNTRYDEST_DESC
              FROM CSTMORDR1 h
              LEFT JOIN ODCTMSTR1 cat  ON cat.ORDER_CATEGORY = h.ORDER_CATEGORY
              LEFT JOIN CSSPMSTR1 cust ON cust.CSTMSPPL_ID = h.CSTMSPPL_ID
              LEFT JOIN PMTMMSTR1 pmt  ON pmt.PAYMENT_ID = h.PAYMENT_ID
              LEFT JOIN SPTMMSTR1 spt  ON spt.SHIPMENT_ID = h.SHIPMENT_ID
              LEFT JOIN PORTMSTR1 pl   ON pl.PORT_ID = h.CTM1_PORTLOADING
              LEFT JOIN PORTMSTR1 pd   ON pd.PORT_ID = h.CTM1_PORTDISCHARGE
              LEFT JOIN PLCEMSTR1 pld  ON pld.PLACE_ID = h.CTM1_PLACEDESTINATION
              LEFT JOIN PLCEMSTR1 pcd  ON pcd.PLACE_ID = h.CTM1_COUNTRYDESTINATION
              WHERE h.SALEORDER_NO = :sono";
    $stmt_h = $conn->prepare($sql_h);
    $stmt_h->bindParam(':sono', $sono, PDO::PARAM_STR);
    $stmt_h->execute();
    $header = $stmt_h->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['success' => false, 'message' => 'Sales Order not found.']);
        return;
    }

    if (!empty($header['SALEORDER_DATE'])) $header['SALEORDER_DATE_FMT'] = date('d/m/Y', strtotime($header['SALEORDER_DATE']));
    if (!empty($header['INTENDSHIP_DATE'])) $header['INTENDSHIP_DATE_FMT'] = date('d/m/Y', strtotime($header['INTENDSHIP_DATE']));
    if (!empty($header['CTM1_LMEDATE'])) $header['CTM1_LMEDATE_FMT'] = date('d/m/Y', strtotime($header['CTM1_LMEDATE']));
    if (!empty($header['CTM1_MJPDATE'])) $header['CTM1_MJPDATE_FMT'] = date('d/m/Y', strtotime($header['CTM1_MJPDATE']));

    $sql_d = "SELECT d.SALEORDER_ITEM, d.PRODUCT_ID, d.PRODUCT_MODEL, d.ALLOY, d.TEMPER, 
                     d.SURFACE_GRADE, d.METALLURGICAL_GRADE, d.THICKNESS, d.WIDTH, d.LENGTH, 
                     d.CTM2_UOMDIMENSION, d.CT2U_THICKNESS, d.CT2U_WIDTH, d.CT2U_LENGTH, d.CT2U_UOMDIMENSION,
                     d.WEIGHT_PIECE, d.CTM2_ORDERWEIGHT, d.CTM2_ORDERPIECE, d.CTM2_UOMWEIGHT,
                     d.CT2U_ORDERWEIGHT, d.CT2U_ORDERPIECE, d.CT2U_UOMWEIGHT,
                     d.CTM2_MAXTOLERANCE, d.CTM2_MINTOLERANCE,
                     d.CTM2_ACTUALPRICE, ROUND(d.CTM2_ORDERWEIGHT * d.CTM2_ACTUALPRICE, 2) AS AMOUNT,
                     d.CTM2_DESCRIPTION, d.CTM2_REMARK, d.CTM2_STATUS, 
                     d.MATERIAL_IN, d.COIL_FROM, d.FULL_COIL, d.PRICE_PERIOD, d.CTM2_CUSTOMERPARTNO,
                     d.T5_TEMPERATURE, d.CTM2_PODATE, d.REQUEST_DATE, d.CONFIRM_DATE,
                     d.CTM2_ORDERLME, d.CTM2_MJPPRICE, d.CTM2_ORDERSTDV, d.CTM2_ORDERFACTOR, d.CTM2_ORDERSTDXFACTOR,
                     d.CTM2_SPECIALLABEL1, d.CTM2_SPECIALLABEL2, d.CTM2_SPECIALLABEL3, d.CTM2_SPECIALLABEL4
              FROM CSTMORDR2 d
              WHERE d.SALEORDER_NO = :sono 
              ORDER BY d.SALEORDER_ITEM ASC";
    $stmt_d = $conn->prepare($sql_d);
    $stmt_d->bindParam(':sono', $sono, PDO::PARAM_STR);
    $stmt_d->execute();
    $raw_items = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($raw_items as $it) {
        if (!empty($it['CTM2_PODATE'])) $it['CTM2_PODATE_FMT'] = date('d/m/Y', strtotime($it['CTM2_PODATE']));
        if (!empty($it['REQUEST_DATE'])) $it['REQUEST_DATE_FMT'] = date('d/m/Y', strtotime($it['REQUEST_DATE']));
        if (!empty($it['CONFIRM_DATE'])) $it['CONFIRM_DATE_FMT'] = date('d/m/Y', strtotime($it['CONFIRM_DATE']));
        $items[] = $it;
    }

    echo json_encode([
        'success' => true,
        'header'  => $header,
        'items'   => $items
    ]);
}

// =========================================================================
// 2. Customer Info & Default Master Data
// =========================================================================
function getCustomerInfo($conn) {
    $cust_id = isset($_GET['cust_id']) ? trim($_GET['cust_id']) : '';
    if (empty($cust_id)) {
        echo json_encode(['success' => false, 'message' => 'Customer ID required.']);
        return;
    }

    $sql = "SELECT CSTMSPPL_ID, HARMONIZE_CODE, SUPPLIER_MATCODE, 
                   CSTMSPPL_LOCATION, UOM_WEIGHT, UOM_DIMENSION, CSSP_TAXPERCENT, 
                   PAYMENT_ID, SHIPMENT_ID, CURRENCY_ID, CSSP_PORTLOADING, 
                   CSSP_PORTDISCHARGE, CSSP_PLACEDESTINATION, CSSP_COUNTRYDESTINATION, 
                   LMETYPE, SPECIAL_LABEL1, SPECIAL_LABEL2, SPECIAL_LABEL3, SPECIAL_LABEL4
            FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :cust_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':cust_id', $cust_id, PDO::PARAM_STR);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $data['DEFAULT_EXCHANGE_RATE'] = (trim($data['CSTMSPPL_LOCATION'] ?? '') === 'O') ? 1.00000 : 60.00000;
        echo json_encode(['success' => true, 'customer' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    }
}

// =========================================================================
// 3. LME and MJP Rates
// =========================================================================
function getLMERates($conn) {
    $lme_date = isset($_GET['date']) ? trim($_GET['date']) : '';
    $lme_type = isset($_GET['type']) ? trim($_GET['type']) : 'D-DAILY';

    if (empty($lme_date)) {
        echo json_encode(['success' => false, 'message' => 'Date is required.']);
        return;
    }

    $sql = "SELECT * FROM LME3MNTH1 WHERE LME_DATE = :lme_date";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':lme_date', $lme_date, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $order_lme = 0.00;
        switch ($lme_type) {
            case 'D-DAILY':        $order_lme = (float)$row['LME3M_DAILY']; break;
            case 'B-WBP LME':      $order_lme = (float)$row['LME3M_WBPLME']; break;
            case 'W-WBP':          $order_lme = (float)$row['LME3M_WBP']; break;
            case 'M-MONTHLY':      $order_lme = (float)$row['LME3M_MONTHLY']; break;
            case 'D-DAILY CASH':   $order_lme = (float)$row['LME3M_DAILY']; break;
            case 'M-MONTHLY CASH': $order_lme = (float)$row['LME3M_MONTHLY']; break;
        }

        echo json_encode([
            'success'     => true,
            'order_lme'   => $order_lme,
            'mjp_price'   => (float)$row['MJP'],
            'stdev'       => (float)$row['STDEV'],
            'factor'      => (float)$row['FACTOR'],
            'stdx_factor' => (float)$row['STDXFACTOR']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "LME data not found for date: {$lme_date}"]);
    }
}

// =========================================================================
// 4. Pricing Calculation
// =========================================================================
function calculatePriceAPI($conn) {
    $period     = trim($_GET['price_period'] ?? '');
    $coil_from  = trim($_GET['coil_from'] ?? 'MAT');
    $cust_id    = trim($_GET['cust_id'] ?? '');
    $sono       = trim($_GET['sono'] ?? '');
    $product_id = trim($_GET['product_id'] ?? '');
    $alloy      = trim($_GET['alloy'] ?? '');
    $temper     = trim($_GET['temper'] ?? '');
    $sg         = trim($_GET['sg'] ?? '');
    $mg         = trim($_GET['mg'] ?? '');
    $thickness  = (float)($_GET['thickness'] ?? 0);
    $width      = (float)($_GET['width'] ?? 0);
    $length     = (float)($_GET['length'] ?? 0);
    $weight     = (float)($_GET['weight'] ?? 0);
    $min_tol    = (int)($_GET['min_tol'] ?? 0);
    $full_coil  = trim($_GET['full_coil'] ?? 'F');

    $sql1 = "SELECT * FROM PRICMSTR1 WHERE PRICE_PERIOD = :p AND COIL_FROM = :cf AND CSTMSPPL_ID = :c";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->execute([':p' => $period, ':cf' => $coil_from, ':c' => $cust_id]);
    $p1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    if (!$p1) {
        echo json_encode(['success' => false, 'message' => "No base price data in PRICMSTR1 for Period: {$period}"]);
        return;
    }

    $exchange_rate = round((float)$p1['EXCHANGE_RATE'], 5);
    $lme_avg       = round((float)$p1['LME_AVERAGE'], 2);
    $ingot_premium = round((float)$p1['INGOT_PREMIUM'], 2);
    $duty_pct      = (int)$p1['INGOT_IMPORTDUTY'];
    $duty          = (($lme_avg + $ingot_premium) * $duty_pct) / 100;
    $ingot_freight = (float)$p1['INGOT_FREIGHT'];
    $loss_pct      = (int)$p1['INGOT_MELTLOSS'];
    $melt_loss1    = (($lme_avg + $ingot_premium + $duty + $ingot_freight) * $loss_pct) / 100;
    $melt_loss2    = (($lme_avg + $ingot_premium + $ingot_freight) * $loss_pct) / 100;
    $packing       = (float)$p1['PRODUCT_PACKING'];
    $freight       = (float)$p1['PRODUCT_FREIGHT'];
    $fuel          = (float)$p1['FUEL_SURCHARGE'];
    $interest      = round((float)$p1['REFUND_INTEREST'], 4);

    $base_conv = 0;
    if ($product_id === 'CC' || $product_id === 'NC') $base_conv = (float)$p1['BASE_CONVERSIONCIRCLE'];
    elseif ($product_id === 'SH') $base_conv = (float)$p1['BASE_CONVERSIONSHEET'];
    elseif ($product_id === 'CO') $base_conv = (float)$p1['BASE_CONVERSIONCOIL'];

    $price1 = $lme_avg + $ingot_premium + $duty + $ingot_freight + $melt_loss1 + $packing + $freight + $fuel + $interest + $base_conv;
    $price2 = $lme_avg + $ingot_premium + $ingot_freight + $melt_loss2 + $packing + $freight + $fuel + $interest + $base_conv;

    $alloy_prem = getVal($conn, "SELECT ALLOY_PREMIUM FROM PRICMSTR2 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ?", [$period, $coil_from, $cust_id, $alloy]);
    $temp_prem  = getVal($conn, "SELECT TEMPER_PREMIUM FROM PRICMSTR3 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ? AND TEMPER = ?", [$period, $coil_from, $cust_id, $alloy, $temper]);
    $sg_prem    = getVal($conn, "SELECT SG_PREMIUM FROM PRICMSTR4 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ? AND SURFACE_GRADE = ?", [$period, $coil_from, $cust_id, $alloy, $sg]);
    $mg_prem    = getVal($conn, "SELECT MG_PREMIUM FROM PRICMSTR5 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND METALLURGICAL_GRADE = ?", [$period, $coil_from, $cust_id, $mg]);
    $thk_prem   = getVal($conn, "SELECT THICKNESS_PREMIUM FROM PRICMSTR6 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND THICKNESS_FROM <= ? AND THICKNESS_TO >= ?", [$period, $coil_from, $cust_id, $thickness, $thickness]);

    $dim = ($product_id === 'NC' && $width < $length) ? $length : $width;
    $prod_type = ($product_id === 'NC') ? 'CC' : $product_id;
    $dia_prem   = getVal($conn, "SELECT DIAMETER_PREMIUM FROM PRICMSTR7 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND PRODUCT_ID = ? AND DIAMETER_FROM <= ? AND DIAMETER_TO >= ?", [$period, $coil_from, $cust_id, $prod_type, round($dim), round($dim)]);

    $len_prem   = 0;
    if ($product_id === 'SH') {
        $len_prem = getVal($conn, "SELECT LENGTH_PREMIUM FROM PRICMSTR8 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND LENGTH_FROM <= ? AND LENGTH_TO >= ?", [$period, $coil_from, $cust_id, round($length), round($length)]);
    }

    $qty_prem   = getVal($conn, "SELECT QUANTITY_PREMIUM FROM PRICMSTR9 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND QUANTITY_FROM <= ? AND QUANTITY_TO >= ?", [$period, $coil_from, $cust_id, $weight, $weight]);
    $tol_prem   = getVal($conn, "SELECT TOLERANCE_PREMIUM FROM PRICMSTR10 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND TOLERANCE_FROM <= ? AND TOLERANCE_TO >= ?", [$period, $coil_from, $cust_id, $min_tol, $min_tol]);
    $full_prem  = getVal($conn, "SELECT FULLCOIL_PREMIUM FROM PRICMSTR11 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND FULL_COIL = ?", [$period, $coil_from, $cust_id, $full_coil]);
    $fcl_prem   = getVal($conn, "SELECT FCL_PREMIUM FROM PRICMSTR12 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ?", [$period, $coil_from, $cust_id]);

    $total_prem = $alloy_prem + $temp_prem + $sg_prem + $mg_prem + $thk_prem + $dia_prem + $len_prem + $qty_prem + $tol_prem + $full_prem + $fcl_prem;

    $actual_price = 0.0000;
    if (strpos($sono, 'SL-') === 0) {
        $actual_price = round((($price1 + $total_prem) * $exchange_rate) / 1000, 4);
    } else {
        $actual_price = round((($price2 + $total_prem) * $exchange_rate) / 1000, 4);
    }

    echo json_encode([
        'success'       => true,
        'actual_price'  => $actual_price,
        'exchange_rate' => $exchange_rate,
        'order_lme'     => $lme_avg,
        'ingot_premium' => $ingot_premium
    ]);
}

function getVal($conn, $sql, $params) {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $val = $stmt->fetchColumn();
    return ($val !== false) ? (float)$val : 0.0;
}

// =========================================================================
// 5. Save Sales Order (ปรับปรุง Logic เต็มตาม S1)
// =========================================================================
function saveSalesOrder($conn) {
    $raw_input = file_get_contents('php://input');
    $payload   = json_decode($raw_input, true);

    if (!$payload || empty($payload['header'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data payload.']);
        return;
    }

    $h = $payload['header'];
    $items = $payload['items'] ?? [];
    $user  = $_SESSION['ID'];
    $sono  = trim($h['SALEORDER_NO'] ?? '');

    $exrate = (float)($h['EXCHANGE_RATE'] ?? 1);
    if ($exrate >= 60 || $exrate <= 0) {
        echo json_encode(['success' => false, 'message' => 'Incorrect Exchange Rate: Must be between 0.01 and 59.99999']);
        return;
    }

    $cust_id = strtoupper(trim($h['CSTMSPPL_ID'] ?? ''));
    $order_cat = strtoupper(trim($h['ORDER_CATEGORY'] ?? ''));
    if (substr($order_cat, 0, 4) === 'OVMI' && substr($cust_id, 0, 3) !== 'MIL') {
        echo json_encode(['success' => false, 'message' => 'Order Category OVMI allows only customer starting with MIL.']);
        return;
    }

    $conn->beginTransaction();

    try {
        // 1. ตรวจสอบและบันทึก Dimension Master (THCKMSTR1, DMWDMSTR1, LNGTMSTR1) อัตโนมัติตาม S1
        foreach ($items as $it) {
            ensureDimensionMaster($conn, 'THCKMSTR1', 'THICKNESS', $it['THICKNESS'] ?? 0);
            ensureDimensionMaster($conn, 'DMWDMSTR1', 'WIDTH', $it['WIDTH'] ?? 0);
            ensureDimensionMaster($conn, 'LNGTMSTR1', 'LENGTH', $it['LENGTH'] ?? 0);
            ensureDimensionMaster($conn, 'THCKMSTR1', 'THICKNESS', $it['CT2U_THICKNESS'] ?? 0);
            ensureDimensionMaster($conn, 'DMWDMSTR1', 'WIDTH', $it['CT2U_WIDTH'] ?? 0);
            ensureDimensionMaster($conn, 'LNGTMSTR1', 'LENGTH', $it['CT2U_LENGTH'] ?? 0);
        }

        $is_new = empty($sono) || ($sono === '(Auto Generated on Save)');

        if ($is_new) {
            $prefix = strtoupper(trim($h['SO_PREFIX'] ?? ''));
            if (strlen($prefix) !== 2) {
                if ($order_cat === 'ORSP') $prefix = 'TT';
                elseif ($order_cat === 'SCRR') $prefix = 'RC';
                else $prefix = 'SO';
            }

            $year2d  = date('y');
            $pattern = "{$prefix}-{$year2d}-%";

            $sql_max = "SELECT TOP 1 SALEORDER_NO 
                        FROM CSTMORDR1 WITH (UPDLOCK, TABLOCKX)
                        WHERE SALEORDER_NO LIKE :pattern 
                        ORDER BY SALEORDER_NO DESC";
            $stmt_max = $conn->prepare($sql_max);
            $stmt_max->bindParam(':pattern', $pattern, PDO::PARAM_STR);
            $stmt_max->execute();
            $row_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

            $next_seq = 1;
            if ($row_max && !empty($row_max['SALEORDER_NO'])) {
                $parts = explode('-', trim($row_max['SALEORDER_NO']));
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    $next_seq = intval($parts[2]) + 1;
                }
            }

            $sono = sprintf("%s-%s-%04d", $prefix, $year2d, $next_seq);

            $country_dest = !empty($h['CTM1_COUNTRYDESTINATION']) ? trim($h['CTM1_COUNTRYDESTINATION']) : (!empty($h['CTM1_PLACEDESTINATION']) ? trim($h['CTM1_PLACEDESTINATION']) : '');
            $place_dest   = !empty($h['CTM1_PLACEDESTINATION']) ? trim($h['CTM1_PLACEDESTINATION']) : '';

            $sql_ins_h = "INSERT INTO CSTMORDR1 (
                            SALEORDER_NO, ORDER_CATEGORY, ORDER_TYPE, SALEORDER_DATE, INTENDSHIP_DATE, 
                            CSTMSPPL_ID, CURRENCY_ID, EXCHANGE_RATE, CTM1_PONO, SUPPLIER_MATCODE, 
                            CUSTOMS_CODE, PAYMENT_ID, SHIPMENT_ID, CTM1_PORTLOADING, CTM1_PORTDISCHARGE, 
                            CTM1_PLACEDESTINATION, CTM1_COUNTRYDESTINATION, CTM1_TAXPERCENT, 
                            CTM1_TRANSPORTATION, CTM1_OVERSEAFREIGHT, CTM1_INSURANCE, 
                            CTM1_FOBTOTALAMOUNT, CTM1_GRANDTOTALAMOUNT, CTM1_TOTALAMOUNT, 
                            CTM1_SERIALCODE, CTM1_WHOLEWEIGHT, CTM1_MARKSKID, CTM1_SCRAPWIRE,
                            CTM1_LMETYPE, CTM1_LMEDATE, CTM1_ORDERLME, CTM1_MJPDATE, CTM1_MJPPRICE,
                            CTM1_ORDERSTDV, CTM1_ORDERFACTOR, CTM1_ORDERSTDXFACTOR,
                            CTM1_OPERATOR1, CTM1_OPERATEDATE
                          ) VALUES (
                            :sono, :order_cat, 'D', :so_date, :ship_date,
                            :cust_id, :currency, :exrate, :pono, :supplier_mat,
                            :customs_code, :payment, :shipment, :portload, :portdis,
                            :placedest, :countrydest, :tax,
                            :transport, :freight, :insurance,
                            :fob, :grand, :total,
                            0, 0, 0, 0,
                            :lmetype, :lmedate, :orderlme, :mjpdate, :mjpprice,
                            :stdv, :factor, :stdxfactor,
                            :user, :opdate
                          )";
            
            $stmt = $conn->prepare($sql_ins_h);
            $stmt->execute([
                ':sono'         => $sono,
                ':order_cat'    => $h['ORDER_CATEGORY'],
                ':so_date'      => $h['SALEORDER_DATE'],
                ':ship_date'    => $h['INTENDSHIP_DATE'],
                ':cust_id'      => $h['CSTMSPPL_ID'],
                ':currency'     => $h['CURRENCY_ID'],
                ':exrate'       => $exrate,
                ':pono'         => $h['CTM1_PONO'],
                ':supplier_mat' => $h['SUPPLIER_MATCODE'] ?? '',
                ':customs_code' => $h['CUSTOMS_CODE'] ?? '',
                ':payment'      => $h['PAYMENT_ID'] ?? '',
                ':shipment'     => $h['SHIPMENT_ID'] ?? '',
                ':portload'     => $h['CTM1_PORTLOADING'] ?? '',
                ':portdis'      => $h['CTM1_PORTDISCHARGE'] ?? '',
                ':placedest'    => $place_dest,
                ':countrydest'  => $country_dest,
                ':tax'          => $h['CTM1_TAXPERCENT'] ?? 0,
                ':transport'    => $h['CTM1_TRANSPORTATION'] ?? 0,
                ':freight'      => $h['CTM1_OVERSEAFREIGHT'] ?? 0,
                ':insurance'    => $h['CTM1_INSURANCE'] ?? 0,
                ':fob'          => $h['CTM1_FOBTOTALAMOUNT'] ?? 0,
                ':grand'        => $h['CTM1_GRANDTOTALAMOUNT'] ?? 0,
                ':total'        => $h['CTM1_TOTALAMOUNT'] ?? 0,
                ':lmetype'      => $h['CTM1_LMETYPE'] ?? 'M-MONTHLY',
                ':lmedate'      => !empty($h['CTM1_LMEDATE']) ? $h['CTM1_LMEDATE'] : date('Y-m-d'),
                ':orderlme'     => $h['CTM1_ORDERLME'] ?? 0,
                ':mjpdate'      => !empty($h['CTM1_MJPDATE']) ? $h['CTM1_MJPDATE'] : date('Y-m-d'),
                ':mjpprice'     => $h['CTM1_MJPPRICE'] ?? 0,
                ':stdv'         => (float)($h['CTM1_ORDERSTDV'] ?? 0),
                ':factor'       => (float)($h['CTM1_ORDERFACTOR'] ?? 0),
                ':stdxfactor'   => (float)($h['CTM1_ORDERSTDXFACTOR'] ?? 0),
                ':user'         => $user,
                ':opdate'       => date('Y-m-d H:i:s')
            ]);
        } else {
            $sql_upd_h = "UPDATE CSTMORDR1 SET 
                            ORDER_CATEGORY          = :order_cat, 
                            CSTMSPPL_ID             = :cust_id,
                            CURRENCY_ID             = :currency,
                            SALEORDER_DATE          = :so_date, 
                            INTENDSHIP_DATE         = :ship_date, 
                            EXCHANGE_RATE           = :exrate, 
                            CTM1_PONO               = :pono, 
                            PAYMENT_ID              = :payment, 
                            SHIPMENT_ID             = :shipment,
                            CTM1_PORTLOADING        = :portload, 
                            CTM1_PORTDISCHARGE      = :portdis, 
                            CTM1_PLACEDESTINATION   = :placedest,
                            CTM1_COUNTRYDESTINATION = :countrydest,
                            CTM1_TAXPERCENT         = :tax, 
                            CTM1_LMETYPE            = :lmetype,
                            CTM1_LMEDATE            = :lmedate,
                            CTM1_ORDERLME           = :orderlme,
                            CTM1_MJPDATE            = :mjpdate,
                            CTM1_MJPPRICE           = :mjpprice,
                            CTM1_ORDERSTDV          = :stdv,
                            CTM1_ORDERFACTOR        = :factor,
                            CTM1_ORDERSTDXFACTOR    = :stdxfactor,
                            CTM1_TRANSPORTATION     = :transport,
                            CTM1_OVERSEAFREIGHT     = :freight, 
                            CTM1_INSURANCE          = :insurance,
                            CTM1_FOBTOTALAMOUNT     = :fob, 
                            CTM1_GRANDTOTALAMOUNT   = :grand, 
                            CTM1_TOTALAMOUNT        = :total,
                            CTM1_UPDATE             = :user,
                            CTM1_UPDATEDATE         = :opdate
                          WHERE SALEORDER_NO = :sono";
            $stmt = $conn->prepare($sql_upd_h);
            $stmt->execute([
                ':order_cat'   => $h['ORDER_CATEGORY'],
                ':cust_id'     => $h['CSTMSPPL_ID'],
                ':currency'    => $h['CURRENCY_ID'],
                ':so_date'     => $h['SALEORDER_DATE'],
                ':ship_date'   => $h['INTENDSHIP_DATE'],
                ':exrate'      => $exrate,
                ':pono'        => $h['CTM1_PONO'],
                ':payment'     => $h['PAYMENT_ID'] ?? '',
                ':shipment'    => $h['SHIPMENT_ID'] ?? '',
                ':portload'    => $h['CTM1_PORTLOADING'] ?? '',
                ':portdis'     => $h['CTM1_PORTDISCHARGE'] ?? '',
                ':placedest'   => $h['CTM1_PLACEDESTINATION'] ?? '',
                ':countrydest' => $h['CTM1_COUNTRYDESTINATION'] ?? $h['CTM1_PLACEDESTINATION'] ?? '',
                ':tax'         => $h['CTM1_TAXPERCENT'] ?? 0,
                ':lmetype'     => $h['CTM1_LMETYPE'] ?? 'M-MONTHLY',
                ':lmedate'     => !empty($h['CTM1_LMEDATE']) ? $h['CTM1_LMEDATE'] : date('Y-m-d'),
                ':orderlme'    => $h['CTM1_ORDERLME'] ?? 0,
                ':mjpdate'     => !empty($h['CTM1_MJPDATE']) ? $h['CTM1_MJPDATE'] : date('Y-m-d'),
                ':mjpprice'    => $h['CTM1_MJPPRICE'] ?? 0,
                ':stdv'        => (float)($h['CTM1_ORDERSTDV'] ?? 0),
                ':factor'      => (float)($h['CTM1_ORDERFACTOR'] ?? 0),
                ':stdxfactor'  => (float)($h['CTM1_ORDERSTDXFACTOR'] ?? 0),
                ':transport'   => $h['CTM1_TRANSPORTATION'] ?? 0,
                ':freight'     => $h['CTM1_OVERSEAFREIGHT'] ?? 0,
                ':insurance'   => $h['CTM1_INSURANCE'] ?? 0,
                ':fob'         => $h['CTM1_FOBTOTALAMOUNT'] ?? 0,
                ':grand'       => $h['CTM1_GRANDTOTALAMOUNT'] ?? 0,
                ':total'       => $h['CTM1_TOTALAMOUNT'] ?? 0,
                ':user'        => $user,
                ':opdate'      => date('Y-m-d H:i:s'),
                ':sono'        => $sono
            ]);
        }

        // ลบ Items ที่ไม่อยู่ในฟอร์มออก
        $current_item_nos = array_column($items, 'SALEORDER_ITEM');
        if (!empty($current_item_nos)) {
            $placeholders = implode(',', array_fill(0, count($current_item_nos), '?'));
            $conn->prepare("DELETE FROM INVCPRCS2 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM NOT IN ($placeholders)")->execute(array_merge([$sono], $current_item_nos));
            $conn->prepare("DELETE FROM INVCPRCS3 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM NOT IN ($placeholders)")->execute(array_merge([$sono], $current_item_nos));
            $conn->prepare("DELETE FROM PACKPROD1 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM NOT IN ($placeholders)")->execute(array_merge([$sono], $current_item_nos));
            $conn->prepare("DELETE FROM PRODSCHD1 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM NOT IN ($placeholders)")->execute(array_merge([$sono], $current_item_nos));
            $conn->prepare("DELETE FROM CSTMORDR2 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM NOT IN ($placeholders)")->execute(array_merge([$sono], $current_item_nos));
        } else {
            $conn->prepare("DELETE FROM INVCPRCS2 WHERE SALEORDER_NO = ?")->execute([$sono]);
            $conn->prepare("DELETE FROM INVCPRCS3 WHERE SALEORDER_NO = ?")->execute([$sono]);
            $conn->prepare("DELETE FROM PACKPROD1 WHERE SALEORDER_NO = ?")->execute([$sono]);
            $conn->prepare("DELETE FROM PRODSCHD1 WHERE SALEORDER_NO = ?")->execute([$sono]);
            $conn->prepare("DELETE FROM CSTMORDR2 WHERE SALEORDER_NO = ?")->execute([$sono]);
        }

        // บันทึก/อัปเดตแต่ละ Item พร้อม SUBCONTACT = 'N' และบันทึก STDV/Factor
        foreach ($items as $it) {
            $so_item = trim($it['SALEORDER_ITEM']);
            $order_wt = (float)($it['CTM2_ORDERWEIGHT'] ?? 0);
            $order_pc = (int)($it['CTM2_ORDERPIECE'] ?? 0);
            $actual_price = (float)($it['CTM2_ACTUALPRICE'] ?? 0);
            $prod_id = trim($it['PRODUCT_ID'] ?? '');
            $prod_model = ($prod_id === 'CC') ? 'CIRCLE' : (($prod_id === 'SH') ? 'SHEET' : 'COIL');

            $item_stdv        = (float)($h['CTM1_ORDERSTDV'] ?? 0);
            $item_factor      = (float)($h['CTM1_ORDERFACTOR'] ?? 0);
            $item_stdx_factor = (float)($h['CTM1_ORDERSTDXFACTOR'] ?? 0);

            $chk = $conn->prepare("SELECT COUNT(*) FROM CSTMORDR2 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?");
            $chk->execute([$sono, $so_item]);

            if ($chk->fetchColumn() == 0) {
                $sql_ins_d = "INSERT INTO CSTMORDR2 (
                                SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, ORDER_CATEGORY, ORDER_TYPE,
                                MATERIAL_IN, COIL_FROM, FULL_COIL, CTM2_PONO, CTM2_POITEM, CTM2_PODATE,
                                PRICE_PERIOD, PRODUCT_ID, PRODUCT_MODEL, SUBCONTACT, ALLOY, TEMPER, 
                                SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE,
                                THICKNESS, WIDTH, LENGTH, CTM2_UOMDIMENSION,
                                CT2U_THICKNESS, CT2U_WIDTH, CT2U_LENGTH, CT2U_UOMDIMENSION,
                                WEIGHT_PIECE, REQUEST_DATE, CONFIRM_DATE, CTM2_CUSTOMERPARTNO,
                                CTM2_UOMWEIGHT, CTM2_ORDERWEIGHT, CTM2_ORDERPIECE, 
                                CT2U_UOMWEIGHT, CT2U_ORDERWEIGHT, CT2U_ORDERPIECE, 
                                CTM2_ACTUALPRICE, CTM2_LMETYPE, CTM2_LMEDATE, CTM2_ORDERLME,
                                CTM2_MJPDATE, CTM2_MJPPRICE, CTM2_ORDERSTDV, CTM2_ORDERFACTOR, CTM2_ORDERSTDXFACTOR,
                                CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE,
                                CTM2_DESCRIPTION, CTM2_REMARK,
                                CTM2_SPECIALLABEL1, CTM2_SPECIALLABEL2, CTM2_SPECIALLABEL3, CTM2_SPECIALLABEL4,
                                CTM2_STATUS, CTM2_OPERATOR, CTM2_OPERATEDATE
                              ) VALUES (
                                :sono, :item, :cust, :cat, 'D',
                                :mat_in, :coil_from, :full_coil, :pono, :poitem, :podate,
                                :period, :prod, :model, :subcontact, :alloy, :temper,
                                :sg, :mg, :t5,
                                :thk, :wd, :len, 'mm.',
                                :ct_thk, :ct_wd, :ct_len, :ct_dim,
                                :wt_pc, :req_d, :conf_d, :part_no,
                                'kg.', :ord_wt, :ord_pc,
                                :ct_uom_wt, :ct_wt, :ct_pc,
                                :price, :lmetype, :lmedate, :orderlme,
                                :mjpdate, :mjpprice, :stdv, :factor, :stdxfactor,
                                :maxtol, :mintol,
                                :desc, :remark,
                                :lbl1, :lbl2, :lbl3, :lbl4,
                                'OP', :user, :opdate
                              )";
                $stmt_d = $conn->prepare($sql_ins_d);
                $stmt_d->execute([
                    ':sono'      => $sono,
                    ':item'      => $so_item,
                    ':cust'      => $h['CSTMSPPL_ID'],
                    ':cat'       => $h['ORDER_CATEGORY'],
                    ':mat_in'    => $it['MATERIAL_IN'] ?? 'BOI-MAT1',
                    ':coil_from' => $it['COIL_FROM'] ?? 'MAT',
                    ':full_coil' => $it['FULL_COIL'] ?? 'F',
                    ':pono'      => $h['CTM1_PONO'],
                    ':poitem'    => $so_item,
                    ':podate'    => $h['SALEORDER_DATE'],
                    ':period'    => !empty($it['PRICE_PERIOD']) ? $it['PRICE_PERIOD'] : 'NONE',
                    ':prod'      => $prod_id,
                    ':model'     => $prod_model,
                    ':subcontact'=> 'N', // บันทึก N แก้ไขปัญหา Cannot insert NULL
                    ':alloy'     => $it['ALLOY'],
                    ':temper'    => $it['TEMPER'],
                    ':sg'        => $it['SURFACE_GRADE'] ?? 'SG3',
                    ':mg'        => $it['METALLURGICAL_GRADE'] ?? 'MG3',
                    ':t5'        => $it['T5_TEMPERATURE'] ?? '',
                    ':thk'       => $it['THICKNESS'],
                    ':wd'        => $it['WIDTH'],
                    ':len'       => $it['LENGTH'],
                    ':ct_thk'    => $it['CT2U_THICKNESS'] ?? 0,
                    ':ct_wd'     => $it['CT2U_WIDTH'] ?? 0,
                    ':ct_len'    => $it['CT2U_LENGTH'] ?? 0,
                    ':ct_dim'    => $it['CT2U_UOMDIMENSION'] ?? 'inch',
                    ':wt_pc'     => $it['WEIGHT_PIECE'] ?? 0,
                    ':req_d'     => $h['INTENDSHIP_DATE'],
                    ':conf_d'    => $h['INTENDSHIP_DATE'],
                    ':part_no'   => $it['CTM2_CUSTOMERPARTNO'] ?? '',
                    ':ord_wt'    => $order_wt,
                    ':ord_pc'    => $order_pc,
                    ':ct_uom_wt' => $it['CT2U_UOMWEIGHT'] ?? 'lbs.',
                    ':ct_wt'     => $it['CT2U_ORDERWEIGHT'] ?? $order_wt,
                    ':ct_pc'     => $it['CT2U_ORDERPIECE'] ?? $order_pc,
                    ':price'     => $actual_price,
                    ':lmetype'   => $h['CTM1_LMETYPE'] ?? 'M-MONTHLY',
                    ':lmedate'   => !empty($h['CTM1_LMEDATE']) ? $h['CTM1_LMEDATE'] : date('Y-m-d'),
                    ':orderlme'  => $h['CTM1_ORDERLME'] ?? 0,
                    ':mjpdate'   => !empty($h['CTM1_MJPDATE']) ? $h['CTM1_MJPDATE'] : date('Y-m-d'),
                    ':mjpprice'  => $h['CTM1_MJPPRICE'] ?? 0,
                    ':stdv'      => $item_stdv,
                    ':factor'    => $item_factor,
                    ':stdxfactor'=> $item_stdx_factor,
                    ':maxtol'    => (int)($it['CTM2_MAXTOLERANCE'] ?? 0),
                    ':mintol'    => (int)($it['CTM2_MINTOLERANCE'] ?? 0),
                    ':desc'      => $it['CTM2_DESCRIPTION'] ?? '',
                    ':remark'    => $it['CTM2_REMARK'] ?? '',
                    ':lbl1'      => $it['CTM2_SPECIALLABEL1'] ?? '',
                    ':lbl2'      => $it['CTM2_SPECIALLABEL2'] ?? '',
                    ':lbl3'      => $it['CTM2_SPECIALLABEL3'] ?? '',
                    ':lbl4'      => $it['CTM2_SPECIALLABEL4'] ?? '',
                    ':user'      => $user,
                    ':opdate'    => date('Y-m-d H:i:s')
                ]);
            } else {
                $sql_upd_d = "UPDATE CSTMORDR2 SET 
                                CSTMSPPL_ID       = :cust,
                                ORDER_CATEGORY    = :cat,
                                CTM2_PONO         = :pono,
                                MATERIAL_IN       = :mat_in,
                                COIL_FROM         = :coil_from,
                                FULL_COIL         = :full_coil,
                                PRICE_PERIOD      = :period,
                                PRODUCT_ID        = :prod,
                                PRODUCT_MODEL     = :model,
                                ALLOY             = :alloy,
                                TEMPER            = :temper,
                                SURFACE_GRADE     = :sg,
                                METALLURGICAL_GRADE = :mg,
                                THICKNESS         = :thk,
                                WIDTH             = :wd,
                                LENGTH            = :len,
                                CT2U_THICKNESS    = :ct_thk,
                                CT2U_WIDTH        = :ct_wd,
                                CT2U_LENGTH       = :ct_len,
                                CT2U_UOMDIMENSION = :ct_dim,
                                WEIGHT_PIECE      = :wt_pc,
                                CTM2_ORDERWEIGHT  = :ord_wt,
                                CTM2_ORDERPIECE   = :ord_pc,
                                CT2U_UOMWEIGHT    = :ct_uom_wt,
                                CT2U_ORDERWEIGHT  = :ct_wt,
                                CT2U_ORDERPIECE   = :ct_pc,
                                CTM2_ACTUALPRICE  = :price,
                                CTM2_LMETYPE      = :lmetype,
                                CTM2_LMEDATE      = :lmedate,
                                CTM2_ORDERLME     = :orderlme,
                                CTM2_MJPDATE      = :mjpdate,
                                CTM2_MJPPRICE     = :mjpprice,
                                CTM2_ORDERSTDV    = :stdv,
                                CTM2_ORDERFACTOR  = :factor,
                                CTM2_ORDERSTDXFACTOR = :stdxfactor,
                                CTM2_MAXTOLERANCE = :maxtol,
                                CTM2_MINTOLERANCE = :mintol,
                                CTM2_DESCRIPTION  = :desc,
                                CTM2_REMARK       = :remark,
                                CTM2_SPECIALLABEL1 = :lbl1,
                                CTM2_SPECIALLABEL2 = :lbl2,
                                CTM2_SPECIALLABEL3 = :lbl3,
                                CTM2_SPECIALLABEL4 = :lbl4,
                                CTM2_UPDATE       = :user,
                                CTM2_UPDATEDATE   = :opdate
                              WHERE SALEORDER_NO = :sono AND SALEORDER_ITEM = :item";
                $stmt_d = $conn->prepare($sql_upd_d);
                $stmt_d->execute([
                    ':cust'      => $h['CSTMSPPL_ID'],
                    ':cat'       => $h['ORDER_CATEGORY'],
                    ':pono'      => $h['CTM1_PONO'],
                    ':mat_in'    => $it['MATERIAL_IN'] ?? 'BOI-MAT1',
                    ':coil_from' => $it['COIL_FROM'] ?? 'MAT',
                    ':full_coil' => $it['FULL_COIL'] ?? 'F',
                    ':period'    => !empty($it['PRICE_PERIOD']) ? $it['PRICE_PERIOD'] : 'NONE',
                    ':prod'      => $prod_id,
                    ':model'     => $prod_model,
                    ':alloy'     => $it['ALLOY'],
                    ':temper'    => $it['TEMPER'],
                    ':sg'        => $it['SURFACE_GRADE'] ?? 'SG3',
                    ':mg'        => $it['METALLURGICAL_GRADE'] ?? 'MG3',
                    ':thk'       => $it['THICKNESS'],
                    ':wd'        => $it['WIDTH'],
                    ':len'       => $it['LENGTH'],
                    ':ct_thk'    => $it['CT2U_THICKNESS'] ?? 0,
                    ':ct_wd'     => $it['CT2U_WIDTH'] ?? 0,
                    ':ct_len'    => $it['CT2U_LENGTH'] ?? 0,
                    ':ct_dim'    => $it['CT2U_UOMDIMENSION'] ?? 'inch',
                    ':wt_pc'     => $it['WEIGHT_PIECE'] ?? 0,
                    ':ord_wt'    => $order_wt,
                    ':ord_pc'    => $order_pc,
                    ':ct_uom_wt' => $it['CT2U_UOMWEIGHT'] ?? 'lbs.',
                    ':ct_wt'     => $it['CT2U_ORDERWEIGHT'] ?? $order_wt,
                    ':ct_pc'     => $it['CT2U_ORDERPIECE'] ?? $order_pc,
                    ':price'     => $actual_price,
                    ':lmetype'   => $h['CTM1_LMETYPE'] ?? 'M-MONTHLY',
                    ':lmedate'   => !empty($h['CTM1_LMEDATE']) ? $h['CTM1_LMEDATE'] : date('Y-m-d'),
                    ':orderlme'  => $h['CTM1_ORDERLME'] ?? 0,
                    ':mjpdate'   => !empty($h['CTM1_MJPDATE']) ? $h['CTM1_MJPDATE'] : date('Y-m-d'),
                    ':mjpprice'  => $h['CTM1_MJPPRICE'] ?? 0,
                    ':stdv'      => $item_stdv,
                    ':factor'    => $item_factor,
                    ':stdxfactor'=> $item_stdx_factor,
                    ':maxtol'    => (int)($it['CTM2_MAXTOLERANCE'] ?? 0),
                    ':mintol'    => (int)($it['CTM2_MINTOLERANCE'] ?? 0),
                    ':desc'      => $it['CTM2_DESCRIPTION'] ?? '',
                    ':remark'    => $it['CTM2_REMARK'] ?? '',
                    ':lbl1'      => $it['CTM2_SPECIALLABEL1'] ?? '',
                    ':lbl2'      => $it['CTM2_SPECIALLABEL2'] ?? '',
                    ':lbl3'      => $it['CTM2_SPECIALLABEL3'] ?? '',
                    ':lbl4'      => $it['CTM2_SPECIALLABEL4'] ?? '',
                    ':user'      => $user,
                    ':opdate'    => date('Y-m-d H:i:s'),
                    ':sono'      => $sono,
                    ':item'      => $so_item
                ]);
            }

            // Cascading Updates ตาม S1
            $conn->prepare("UPDATE INVCPRCS2 SET 
                            CUSTOMS_CODE = :cust_code, INV2_EXCHANGERATE = :ex, INV2_DEBITPRICE = :prc, 
                            INV2_PONO = :po, INV2_POITEM = :item, INV2_UOMDIMENSION = 'mm.', 
                            IN2U_UOMDIMENSION = :ct_dim, INV2_UOMWEIGHT = 'kg.', IN2U_UOMWEIGHT = :ct_uom_wt 
                            WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :it")
                 ->execute([
                     ':cust_code' => $h['CUSTOMS_CODE'] ?? '',
                     ':ex'        => $exrate,
                     ':prc'       => $actual_price,
                     ':po'        => $h['CTM1_PONO'],
                     ':item'      => $so_item,
                     ':ct_dim'    => $it['CT2U_UOMDIMENSION'] ?? 'inch',
                     ':ct_uom_wt' => $it['CT2U_UOMWEIGHT'] ?? 'lbs.',
                     ':so'        => $sono,
                     ':it'        => $so_item
                 ]);

            $conn->prepare("UPDATE INVCPRCS3 SET 
                            INV3_DEBITPRICE = :prc, INV3_PONO = :po, INV3_POITEM = :item 
                            WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :it")
                 ->execute([':prc' => $actual_price, ':po' => $h['CTM1_PONO'], ':item' => $so_item, ':so' => $sono, ':it' => $so_item]);

            $conn->prepare("UPDATE PACKPROD1 SET PACK_PONO = :po, PACK_POITEM = :item WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :it")
                 ->execute([':po' => $h['CTM1_PONO'], ':item' => $so_item, ':so' => $sono, ':it' => $so_item]);

            $conn->prepare("UPDATE PRODSCHD1 SET ETD_DATE = :etd WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :it")
                 ->execute([':etd' => $h['INTENDSHIP_DATE'], ':so' => $sono, ':it' => $so_item]);

            $conn->prepare("UPDATE JOBORDER1 SET 
                            MATERIAL_IN = :mat, PRODUCT_ID = :prod, PRODUCT_MODEL = :model, 
                            SURFACE_GRADE = :sg, METALLURGICAL_GRADE = :mg, SCHEDULE_DATE = :etd 
                            WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :it")
                 ->execute([
                     ':mat'   => $it['MATERIAL_IN'] ?? 'BOI-MAT1',
                     ':prod'  => $prod_id,
                     ':model' => $prod_model,
                     ':sg'    => $it['SURFACE_GRADE'] ?? 'SG3',
                     ':mg'    => $it['METALLURGICAL_GRADE'] ?? 'MG3',
                     ':etd'   => $h['INTENDSHIP_DATE'],
                     ':so'    => $sono,
                     ':it'    => $so_item
                 ]);
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Sales Order saved successfully.", 
            'so_no'   => $sono
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
}

// =========================================================================
// 6. Delete Sales Order
// =========================================================================
function deleteSalesOrder($conn) {
    $sono = isset($_POST['sono']) ? trim($_POST['sono']) : '';
    $force_delete = isset($_POST['force_delete']) ? (int)$_POST['force_delete'] : 0;

    if (empty($sono)) {
        echo json_encode(['success' => false, 'message' => 'SO number required.']);
        return;
    }

    if ($force_delete !== 1) {
        $chk = $conn->prepare("SELECT COUNT(*) FROM CSTMORDR2 WHERE SALEORDER_NO = :s");
        $chk->execute([':s' => $sono]);
        $item_count = (int)$chk->fetchColumn();

        if ($item_count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete Sales Order {$sono} because it still contains {$item_count} item(s)."
            ]);
            return;
        }
    }

    $conn->beginTransaction();
    try {
        $conn->prepare("DELETE FROM INVCPRCS2 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);
        $conn->prepare("DELETE FROM INVCPRCS3 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);
        $conn->prepare("DELETE FROM PACKPROD1 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);
        $conn->prepare("DELETE FROM PRODSCHD1 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);
        $conn->prepare("DELETE FROM CSTMORDR2 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);
        $conn->prepare("DELETE FROM CSTMORDR1 WHERE SALEORDER_NO = :s")->execute([':s' => $sono]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Sales Order {$sono} deleted successfully."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
}