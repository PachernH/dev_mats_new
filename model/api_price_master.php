<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['ID'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

include("../dbcon_mats-new.php");

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_customers':
            $sql = "SELECT RTRIM(LTRIM(CSTMSPPL_ID)) AS CSTMSPPL_ID, 
                           RTRIM(LTRIM(CONSIGNEE_COMPANY)) AS CONSIGNEE_COMPANY,
                           RTRIM(LTRIM(CSTMSPPL_TYPE)) AS CSTMSPPL_TYPE
                    FROM CSSPMSTR1 
                    WHERE UPPER(RTRIM(LTRIM(CSTMSPPL_TYPE))) IN ('B', 'C')
                    ORDER BY CSTMSPPL_ID ASC";
            $stmt = $conn->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($data)) {
                $sqlFallback = "SELECT RTRIM(LTRIM(CSTMSPPL_ID)) AS CSTMSPPL_ID, 
                                       RTRIM(LTRIM(CONSIGNEE_COMPANY)) AS CONSIGNEE_COMPANY,
                                       RTRIM(LTRIM(CSTMSPPL_TYPE)) AS CSTMSPPL_TYPE
                                FROM CSSPMSTR1 
                                ORDER BY CSTMSPPL_ID ASC";
                $stmtFallback = $conn->query($sqlFallback);
                $data = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['status' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_currencies':
            $sql = "SELECT RTRIM(LTRIM(CURRENCY_ID)) AS CURRENCY_ID, 
                           RTRIM(LTRIM(DESCRIPTION)) AS DESCRIPTION 
                    FROM CRNCMSTR1 
                    ORDER BY CURRENCY_ID ASC";
            $stmt = $conn->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_dropdown_masters':
            $res = [];
            $st = $conn->query("SELECT DISTINCT RTRIM(LTRIM(ALLOY)) AS ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC");
            $res['alloys'] = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            
            $st = $conn->query("SELECT DISTINCT RTRIM(LTRIM(TEMPER)) AS TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC");
            $res['tempers'] = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            
            $st = $conn->query("SELECT DISTINCT RTRIM(LTRIM(SURFACE_GRADE)) AS SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC");
            $res['surfaces'] = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            
            $st = $conn->query("SELECT DISTINCT RTRIM(LTRIM(METALLURGICAL_GRADE)) AS METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC");
            $res['metallurgicals'] = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : [];
            
            $st = $conn->query("SELECT DISTINCT RTRIM(LTRIM(PRODUCT_ID)) AS PRODUCT_ID FROM PRODMSTR1 ORDER BY PRODUCT_ID ASC");
            $res['products'] = $st ? $st->fetchAll(PDO::FETCH_COLUMN) : ['CC', 'SH', 'NC', 'CO'];

            echo json_encode(['status' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_main_price':
            $price_period = trim($_GET['price_period'] ?? '');
            $coil_from    = trim($_GET['coil_from'] ?? '');
            $customer_id  = trim($_GET['customer_id'] ?? '');

            if (!$price_period || !$coil_from || !$customer_id) {
                echo json_encode(['status' => false, 'message' => 'Missing key parameters']);
                exit();
            }

            $sql = "SELECT * FROM PRICMSTR1 
                    WHERE RTRIM(LTRIM(PRICE_PERIOD)) = :period 
                      AND RTRIM(LTRIM(COIL_FROM)) = :coil 
                      AND RTRIM(LTRIM(CSTMSPPL_ID)) = :cust";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':period' => $price_period, ':coil' => $coil_from, ':cust' => $customer_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                echo json_encode(['status' => true, 'record_status' => 'Old Record', 'data' => $row], JSON_UNESCAPED_UNICODE);
            } else {
                $p_sub = (int)substr($price_period, 7, 2);
                $master_period = ($p_sub >= 1 && $p_sub <= 9) ? '9999' : (($p_sub >= 50 && $p_sub <= 59) ? '5555' : '');

                if (!$master_period) {
                    echo json_encode(['status' => false, 'message' => 'Invalid Price Period Pattern']);
                    exit();
                }

                $sqlMaster = "SELECT * FROM PRICMSTR1 
                              WHERE RTRIM(LTRIM(PRICE_PERIOD)) = :master 
                                AND RTRIM(LTRIM(COIL_FROM)) = :coil 
                                AND RTRIM(LTRIM(CSTMSPPL_ID)) = :cust";
                $stmtM = $conn->prepare($sqlMaster);
                $stmtM->execute([':master' => $master_period, ':coil' => $coil_from, ':cust' => $customer_id]);
                $masterData = $stmtM->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'status' => true, 
                    'record_status' => 'New Record', 
                    'data' => $masterData ?: []
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'save_price':
            $input = json_decode(file_get_contents('php://input'), true);

            $period = trim($input['price_period'] ?? '');
            $coil   = trim($input['coil_from'] ?? '');
            $cust   = trim($input['customer_id'] ?? '');
            $d_from = trim($input['lifetime_from'] ?? '');
            $d_to   = trim($input['lifetime_to'] ?? '');
            $curr   = trim($input['currency_id'] ?? '');
            $exRate = (float)($input['exchange_rate'] ?? 0);
            $lme    = (float)($input['lme_average'] ?? 0);
            $refInt = (float)($input['refund_interest'] ?? 0);

            if (empty($period) || empty($coil) || empty($cust) || empty($d_from) || empty($d_to) || empty($curr)) {
                echo json_encode(['status' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน: Price Period, Coil From, Customer, Lifetime From/To, Currency'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if (strlen($period) < 8 || strlen($period) > 9) {
                echo json_encode(['status' => false, 'message' => 'Price Period format invalid (must be 8 or 9 characters)'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if (strtotime($d_from) > strtotime($d_to)) {
                echo json_encode(['status' => false, 'message' => 'Lifetime From cannot be greater than Lifetime To'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if ($exRate < 1 || $exRate > 150) {
                echo json_encode(['status' => false, 'message' => 'Exchange rate must be between 1.00000 and 150.00000'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $p_sub = (int)substr($period, 7, 2);
            $master_period = ($p_sub >= 1 && $p_sub <= 9) ? '9999' : (($p_sub >= 50 && $p_sub <= 59) ? '5555' : '');

            if (empty($master_period)) {
                echo json_encode(['status' => false, 'message' => 'Period Suffix must be 01-09 (MAT) or 50-59 (MAL)'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $conn->beginTransaction();

            if ($input['record_status'] === 'New Record') {
                $sqlM1 = "SELECT * FROM PRICMSTR1 
                          WHERE RTRIM(LTRIM(PRICE_PERIOD)) = :master 
                            AND RTRIM(LTRIM(COIL_FROM)) = :coil 
                            AND RTRIM(LTRIM(CSTMSPPL_ID)) = :cust";
                $stmtM1 = $conn->prepare($sqlM1);
                $stmtM1->execute([
                    ':master' => $master_period, 
                    ':coil'   => $coil, 
                    ':cust'   => $cust
                ]);
                $m1 = $stmtM1->fetch(PDO::FETCH_ASSOC);

                if (!$m1) {
                    $conn->rollBack();
                    echo json_encode(['status' => false, 'message' => "ไม่พบ Master Template ({$master_period}) สำหรับลูกค้ารายนี้ กรุณาสร้าง Master Template ก่อน"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                $sqlIns = "INSERT INTO PRICMSTR1 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, LIFETIME_FROM, LIFETIME_TO, CURRENCY_ID, EXCHANGE_RATE, 
                           LME_AVERAGE, INGOT_PREMIUM, INGOT_IMPORTDUTY, INGOT_FREIGHT, INGOT_MELTLOSS, PRODUCT_PACKING, 
                           PRODUCT_FREIGHT, FUEL_SURCHARGE, REFUND_INTEREST, BASE_CONVERSIONCIRCLE, BASE_CONVERSIONSHEET, BASE_CONVERSIONCOIL) 
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtIns = $conn->prepare($sqlIns);
                $stmtIns->execute([
                    $period, $coil, $cust,
                    $d_from, $d_to, $curr,
                    $exRate, $lme,
                    round((float)($m1['INGOT_PREMIUM'] ?? 0), 2), round((float)($m1['INGOT_IMPORTDUTY'] ?? 0), 0),
                    round((float)($m1['INGOT_FREIGHT'] ?? 0), 2), round((float)($m1['INGOT_MELTLOSS'] ?? 0), 0),
                    round((float)($m1['PRODUCT_PACKING'] ?? 0), 2), round((float)($m1['PRODUCT_FREIGHT'] ?? 0), 2),
                    round((float)($m1['FUEL_SURCHARGE'] ?? 0), 2), $refInt,
                    round((float)($m1['BASE_CONVERSIONCIRCLE'] ?? 0), 2), round((float)($m1['BASE_CONVERSIONSHEET'] ?? 0), 2),
                    round((float)($m1['BASE_CONVERSIONCOIL'] ?? 0), 2)
                ]);

                $tables = [
                    'PRICMSTR2'  => 'ALLOY, ALLOY_PREMIUM',
                    'PRICMSTR3'  => 'ALLOY, TEMPER, TEMPER_PREMIUM',
                    'PRICMSTR4'  => 'ALLOY, SURFACE_GRADE, SG_PREMIUM',
                    'PRICMSTR5'  => 'METALLURGICAL_GRADE, MG_PREMIUM',
                    'PRICMSTR6'  => 'THICKNESS_FROM, THICKNESS_TO, THICKNESS_PREMIUM',
                    'PRICMSTR7'  => 'PRODUCT_ID, DIAMETER_FROM, DIAMETER_TO, DIAMETER_PREMIUM',
                    'PRICMSTR8'  => 'LENGTH_FROM, LENGTH_TO, LENGTH_PREMIUM',
                    'PRICMSTR9'  => 'QUANTITY_FROM, QUANTITY_TO, QUANTITY_PREMIUM',
                    'PRICMSTR10' => 'TOLERANCE_FROM, TOLERANCE_TO, TOLERANCE_PREMIUM',
                    'PRICMSTR11' => 'FULL_COIL, FULLCOIL_PREMIUM',
                    'PRICMSTR12' => 'FCL_PREMIUM'
                ];

                foreach ($tables as $tbl => $cols) {
                    $sqlCopy = "INSERT INTO {$tbl} (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, {$cols})
                                SELECT :new_period, COIL_FROM, CSTMSPPL_ID, {$cols}
                                FROM {$tbl} 
                                WHERE RTRIM(LTRIM(PRICE_PERIOD)) = :master 
                                  AND RTRIM(LTRIM(COIL_FROM)) = :coil 
                                  AND RTRIM(LTRIM(CSTMSPPL_ID)) = :cust";
                    $stmtCopy = $conn->prepare($sqlCopy);
                    $stmtCopy->execute([
                        ':new_period' => $period,
                        ':master'     => $master_period,
                        ':coil'       => $coil,
                        ':cust'       => $cust
                    ]);
                }
            } else {
                $sqlUpd = "UPDATE PRICMSTR1 
                           SET LIFETIME_FROM = ?, LIFETIME_TO = ?, CURRENCY_ID = ?, EXCHANGE_RATE = ?, LME_AVERAGE = ?, REFUND_INTEREST = ? 
                           WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? 
                             AND RTRIM(LTRIM(COIL_FROM)) = ? 
                             AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?";
                $stmtUpd = $conn->prepare($sqlUpd);
                $stmtUpd->execute([
                    $d_from, $d_to, $curr,
                    $exRate, $lme, $refInt,
                    $period, $coil, $cust
                ]);
            }

            $conn->commit();
            echo json_encode(['status' => true, 'message' => 'Price saved successfully'], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete_price':
            $input = json_decode(file_get_contents('php://input'), true);
            $conn->beginTransaction();

            $sqlDel1 = "DELETE FROM PRICMSTR1 
                        WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? 
                          AND RTRIM(LTRIM(COIL_FROM)) = ? 
                          AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?";
            $conn->prepare($sqlDel1)->execute([$input['price_period'], $input['coil_from'], $input['customer_id']]);

            if (!empty($input['delete_all_premiums'])) {
                for ($i = 2; $i <= 12; $i++) {
                    $sqlDel = "DELETE FROM PRICMSTR{$i} 
                               WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? 
                                 AND RTRIM(LTRIM(COIL_FROM)) = ? 
                                 AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?";
                    $conn->prepare($sqlDel)->execute([$input['price_period'], $input['coil_from'], $input['customer_id']]);
                }
            }

            $conn->commit();
            echo json_encode(['status' => true, 'message' => 'Deleted successfully'], JSON_UNESCAPED_UNICODE);
            break;

        case 'calculate_price':
            $input  = json_decode(file_get_contents('php://input'), true);
            $period = trim($input['price_period']);
            $coil   = trim($input['coil_from']);
            $cust   = trim($input['customer_id']);
            $d_from = $input['lifetime_from'];
            $d_to   = $input['lifetime_to'];

            $sqlBase = "SELECT * FROM PRICMSTR1 
                        WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? 
                          AND RTRIM(LTRIM(COIL_FROM)) = ? 
                          AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? 
                          AND EXCHANGE_RATE > 0";
            $stmtBase = $conn->prepare($sqlBase);
            $stmtBase->execute([$period, $coil, $cust]);
            $base = $stmtBase->fetch(PDO::FETCH_ASSOC);

            if (!$base) {
                echo json_encode(['status' => false, 'message' => 'Base price settings not found.'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $exRate  = (float)$base['EXCHANGE_RATE'];
            $lmeAvg  = (float)$base['LME_AVERAGE'];
            $ingPrem = (float)$base['INGOT_PREMIUM'];
            $ingDuty = (float)$base['INGOT_IMPORTDUTY'];
            $ingFrgt = (float)$base['INGOT_FREIGHT'];
            $meltPct = (float)$base['INGOT_MELTLOSS'];
            $prodPak = (float)$base['PRODUCT_PACKING'];
            $prodFrt = (float)$base['PRODUCT_FREIGHT'];
            $fuelSur = (float)$base['FUEL_SURCHARGE'];
            $refInt  = (float)$base['REFUND_INTEREST'];

            $importDuty = (($lmeAvg + $ingPrem) * $ingDuty) / 100.0;
            $meltLoss1  = (($lmeAvg + $ingPrem + $importDuty + $ingFrgt) * $meltPct) / 100.0;
            $meltLoss2  = (($lmeAvg + $ingPrem + $ingFrgt) * $meltPct) / 100.0;

            $priceCircle1 = $lmeAvg + $ingPrem + $importDuty + $ingFrgt + $meltLoss1 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONCIRCLE'];
            $priceCircle2 = $lmeAvg + $ingPrem + $ingFrgt + $meltLoss2 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONCIRCLE'];
            $priceSheet1  = $lmeAvg + $ingPrem + $importDuty + $ingFrgt + $meltLoss1 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONSHEET'];
            $priceSheet2  = $lmeAvg + $ingPrem + $ingFrgt + $meltLoss2 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONSHEET'];
            $priceCoil1   = $lmeAvg + $ingPrem + $importDuty + $ingFrgt + $meltLoss1 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONCOIL'];
            $priceCoil2   = $lmeAvg + $ingPrem + $ingFrgt + $meltLoss2 + $prodPak + $prodFrt + $fuelSur + $refInt + (float)$base['BASE_CONVERSIONCOIL'];

            $sqlOrders = "SELECT * FROM CSTMORDR2 
                          WHERE SALEORDER_NO LIKE 'S%' 
                            AND RTRIM(LTRIM(CSTMSPPL_ID)) = :cust 
                            AND (CTM2_PODATE BETWEEN :dfrom AND :dto) 
                            AND CTM2_PONO <> '' 
                            AND RTRIM(LTRIM(COIL_FROM)) = :coil 
                            AND (RTRIM(LTRIM(PRICE_PERIOD)) = :period OR PRICE_PERIOD = '' OR PRICE_PERIOD IS NULL)";
            $stmtOrd = $conn->prepare($sqlOrders);
            $stmtOrd->execute([':cust' => $cust, ':dfrom' => $d_from, ':dto' => $d_to, ':coil' => $coil, ':period' => $period]);
            $orders = $stmtOrd->fetchAll(PDO::FETCH_ASSOC);

            $processed = 0;
            $conn->beginTransaction();

            foreach ($orders as $od) {
                $pid = trim($od['PRODUCT_ID'] ?? '');
                if (!in_array($pid, ['CC', 'SH', 'NC', 'CO'])) continue;

                $so_type = substr($od['SALEORDER_NO'], 0, 2);
                if ($so_type === 'SL' && substr(trim($od['CTM2_PONO']), -1) !== '9') {
                    continue;
                }

                $st = $conn->prepare("SELECT ALLOY_PREMIUM FROM PRICMSTR2 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ?");
                $st->execute([$period, $coil, $cust, trim($od['ALLOY'])]);
                $pAlloy = (float)($st->fetchColumn() ?: 0);

                $st = $conn->prepare("SELECT TEMPER_PREMIUM FROM PRICMSTR3 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ? AND RTRIM(LTRIM(TEMPER)) = ?");
                $st->execute([$period, $coil, $cust, trim($od['ALLOY']), trim($od['TEMPER'])]);
                $pTemper = (float)($st->fetchColumn() ?: 0);

                $st = $conn->prepare("SELECT SG_PREMIUM FROM PRICMSTR4 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ? AND RTRIM(LTRIM(SURFACE_GRADE)) = ?");
                $st->execute([$period, $coil, $cust, trim($od['ALLOY']), trim($od['SURFACE_GRADE'])]);
                $pSurface = (float)($st->fetchColumn() ?: 0);

                $st = $conn->prepare("SELECT MG_PREMIUM FROM PRICMSTR5 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(METALLURGICAL_GRADE)) = ?");
                $st->execute([$period, $coil, $cust, trim($od['METALLURGICAL_GRADE'])]);
                $pMeta = (float)($st->fetchColumn() ?: 0);

                $thk = sprintf('%.3f', (float)$od['THICKNESS']);
                $st = $conn->prepare("SELECT THICKNESS_PREMIUM FROM PRICMSTR6 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND THICKNESS_FROM <= ? AND THICKNESS_TO >= ?");
                $st->execute([$period, $coil, $cust, $thk, $thk]);
                $pThick = (float)($st->fetchColumn() ?: 0);

                $diaVal = ($pid === 'NC') ? max((float)$od['WIDTH'], (float)$od['LENGTH']) : (float)$od['WIDTH'];
                $diaPID = ($pid === 'NC') ? 'CC' : $pid;
                $st = $conn->prepare("SELECT DIAMETER_PREMIUM FROM PRICMSTR7 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(PRODUCT_ID)) = ? AND DIAMETER_FROM <= ? AND DIAMETER_TO >= ?");
                $st->execute([$period, $coil, $cust, $diaPID, $diaVal, $diaVal]);
                $pDia = (float)($st->fetchColumn() ?: 0);

                $pLen = 0;
                if ($pid === 'SH') {
                    $st = $conn->prepare("SELECT LENGTH_PREMIUM FROM PRICMSTR8 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND LENGTH_FROM <= ? AND LENGTH_TO >= ?");
                    $st->execute([$period, $coil, $cust, (float)$od['LENGTH'], (float)$od['LENGTH']]);
                    $pLen = (float)($st->fetchColumn() ?: 0);
                }

                $st = $conn->prepare("SELECT QUANTITY_PREMIUM FROM PRICMSTR9 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND QUANTITY_FROM <= ? AND QUANTITY_TO >= ?");
                $st->execute([$period, $coil, $cust, (float)$od['CTM2_ORDERWEIGHT'], (float)$od['CTM2_ORDERWEIGHT']]);
                $pQty = (float)($st->fetchColumn() ?: 0);

                $st = $conn->prepare("SELECT TOLERANCE_PREMIUM FROM PRICMSTR10 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND TOLERANCE_FROM <= ? AND TOLERANCE_TO >= ?");
                $st->execute([$period, $coil, $cust, (int)$od['CTM2_MINTOLERANCE'], (int)$od['CTM2_MINTOLERANCE']]);
                $pTol = $st->fetchColumn();
                if ($pTol === false) {
                    $st->execute([$period, $coil, $cust, (int)$od['CTM2_MAXTOLERANCE'], (int)$od['CTM2_MAXTOLERANCE']]);
                    $pTol = $st->fetchColumn();
                }
                $pTol = (float)($pTol ?: 0);

                $st = $conn->prepare("SELECT FULLCOIL_PREMIUM FROM PRICMSTR11 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(FULL_COIL)) = ?");
                $st->execute([$period, $coil, $cust, trim($od['FULL_COIL'])]);
                $pFull = (float)($st->fetchColumn() ?: 0);

                $st = $conn->prepare("SELECT FCL_PREMIUM FROM PRICMSTR12 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ?");
                $st->execute([$period, $coil, $cust]);
                $pFcl = (float)($st->fetchColumn() ?: 0);

                $targetBase = 0;
                if ($so_type === 'SO') {
                    $targetBase = ($pid === 'SH') ? $priceSheet2 : (($pid === 'CO') ? $priceCoil2 : $priceCircle2);
                } else {
                    $targetBase = ($pid === 'SH') ? $priceSheet1 : (($pid === 'CO') ? $priceCoil1 : $priceCircle1);
                }

                $sumPremiums = $pAlloy + $pTemper + $pSurface + $pMeta + $pThick + $pDia + $pLen + $pQty + $pTol + $pFull + $pFcl;
                $actualPrice = round((($targetBase + $sumPremiums) * $exRate) / 1000.0, 4);

                $conn->prepare("UPDATE CSTMORDR1 SET EXCHANGE_RATE = ? WHERE SALEORDER_NO = ?")
                     ->execute([$exRate, $od['SALEORDER_NO']]);

                $conn->prepare("UPDATE CSTMORDR2 
                                SET PRICE_PERIOD = ?, CTM2_ORDERLME = ?, CTM2_MJPPRICE = ?, CTM2_ACTUALPRICE = ?, CTM2_PROVISIONALPRICE = ? 
                                WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?")
                     ->execute([$period, $lmeAvg, $ingPrem, $actualPrice, $actualPrice, $od['SALEORDER_NO'], $od['SALEORDER_ITEM']]);

                $conn->prepare("UPDATE INVCPRCS2 SET INV2_EXCHANGERATE = ?, INV2_DEBITPRICE = ? WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?")
                     ->execute([$exRate, $actualPrice, $od['SALEORDER_NO'], $od['SALEORDER_ITEM']]);

                $conn->prepare("UPDATE INVCPRCS3 SET INV3_DEBITPRICE = ? WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?")
                     ->execute([$actualPrice, $od['SALEORDER_NO'], $od['SALEORDER_ITEM']]);

                $processed++;
            }

            $conn->commit();
            echo json_encode(['status' => true, 'total' => count($orders), 'processed' => $processed], JSON_UNESCAPED_UNICODE);
            break;

        // อัปเดตเซลล์ตัวเลขแบบ Inline Edit (รองรับ PRICMSTR1 ด้วย)
        case 'update_premium_cell':
            $input = json_decode(file_get_contents('php://input'), true);
            $tbl   = trim($input['table'] ?? '');
            $field = trim($input['field'] ?? '');
            $val   = (float)($input['value'] ?? 0);
            $keys  = $input['keys'] ?? [];

            $allowedTables = [
                'PRICMSTR1'  => ['INGOT_PREMIUM', 'INGOT_IMPORTDUTY', 'INGOT_FREIGHT', 'INGOT_MELTLOSS', 'PRODUCT_PACKING', 'PRODUCT_FREIGHT', 'FUEL_SURCHARGE', 'REFUND_INTEREST', 'BASE_CONVERSIONCIRCLE', 'BASE_CONVERSIONSHEET', 'BASE_CONVERSIONCOIL'],
                'PRICMSTR2'  => 'ALLOY_PREMIUM',
                'PRICMSTR3'  => 'TEMPER_PREMIUM',
                'PRICMSTR4'  => 'SG_PREMIUM',
                'PRICMSTR5'  => 'MG_PREMIUM',
                'PRICMSTR6'  => 'THICKNESS_PREMIUM',
                'PRICMSTR7'  => 'DIAMETER_PREMIUM',
                'PRICMSTR8'  => 'LENGTH_PREMIUM',
                'PRICMSTR9'  => 'QUANTITY_PREMIUM',
                'PRICMSTR10' => 'TOLERANCE_PREMIUM',
                'PRICMSTR11' => 'FULLCOIL_PREMIUM',
                'PRICMSTR12' => 'FCL_PREMIUM'
            ];

            if (!isset($allowedTables[$tbl])) {
                echo json_encode(['status' => false, 'message' => 'Invalid table target'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if ($tbl === 'PRICMSTR1') {
                if (!in_array($field, $allowedTables['PRICMSTR1'])) {
                    echo json_encode(['status' => false, 'message' => 'Invalid field for PRICMSTR1'], JSON_UNESCAPED_UNICODE);
                    exit();
                }
                $targetField = $field;
            } else {
                $targetField = $allowedTables[$tbl];
            }

            $whereParts = ["RTRIM(LTRIM(PRICE_PERIOD)) = :period", "RTRIM(LTRIM(COIL_FROM)) = :coil", "RTRIM(LTRIM(CSTMSPPL_ID)) = :cust"];
            $params = [
                ':val'    => $val,
                ':period' => trim($keys['price_period']),
                ':coil'   => trim($keys['coil_from']),
                ':cust'   => trim($keys['customer_id'])
            ];

            if ($tbl === 'PRICMSTR2' || $tbl === 'PRICMSTR3' || $tbl === 'PRICMSTR4') {
                $whereParts[] = "RTRIM(LTRIM(ALLOY)) = :alloy";
                $params[':alloy'] = trim($keys['alloy']);
            }
            if ($tbl === 'PRICMSTR3') {
                $whereParts[] = "RTRIM(LTRIM(TEMPER)) = :temper";
                $params[':temper'] = trim($keys['temper']);
            }
            if ($tbl === 'PRICMSTR4') {
                $whereParts[] = "RTRIM(LTRIM(SURFACE_GRADE)) = :sg";
                $params[':sg'] = trim($keys['surface_grade']);
            }
            if ($tbl === 'PRICMSTR5') {
                $whereParts[] = "RTRIM(LTRIM(METALLURGICAL_GRADE)) = :mg";
                $params[':mg'] = trim($keys['metallurgical_grade']);
            }
            if ($tbl === 'PRICMSTR6') {
                $whereParts[] = "RTRIM(LTRIM(THICKNESS_FROM)) = :thk_from AND RTRIM(LTRIM(THICKNESS_TO)) = :thk_to";
                $params[':thk_from'] = trim($keys['thickness_from']);
                $params[':thk_to']   = trim($keys['thickness_to']);
            }
            if ($tbl === 'PRICMSTR7') {
                $whereParts[] = "RTRIM(LTRIM(PRODUCT_ID)) = :pid AND DIAMETER_FROM = :dia_from AND DIAMETER_TO = :dia_to";
                $params[':pid']      = trim($keys['product_id']);
                $params[':dia_from'] = (float)$keys['diameter_from'];
                $params[':dia_to']   = (float)$keys['diameter_to'];
            }
            if ($tbl === 'PRICMSTR8') {
                $whereParts[] = "LENGTH_FROM = :len_from AND LENGTH_TO = :len_to";
                $params[':len_from'] = (float)$keys['length_from'];
                $params[':len_to']   = (float)$keys['length_to'];
            }
            if ($tbl === 'PRICMSTR9') {
                $whereParts[] = "QUANTITY_FROM = :qty_from AND QUANTITY_TO = :qty_to";
                $params[':qty_from'] = (float)$keys['quantity_from'];
                $params[':qty_to']   = (float)$keys['quantity_to'];
            }
            if ($tbl === 'PRICMSTR10') {
                $whereParts[] = "TOLERANCE_FROM = :tol_from AND TOLERANCE_TO = :tol_to";
                $params[':tol_from'] = (int)$keys['tolerance_from'];
                $params[':tol_to']   = (int)$keys['tolerance_to'];
            }
            if ($tbl === 'PRICMSTR11') {
                $whereParts[] = "RTRIM(LTRIM(FULL_COIL)) = :full_coil";
                $params[':full_coil'] = trim($keys['full_coil']);
            }

            $sqlUpd = "UPDATE {$tbl} SET {$targetField} = :val WHERE " . implode(' AND ', $whereParts);
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->execute($params);

            echo json_encode(['status' => true, 'message' => 'Updated successfully'], JSON_UNESCAPED_UNICODE);
            break;

        case 'add_premium_row':
            $input = json_decode(file_get_contents('php://input'), true);
            $tbl   = trim($input['table'] ?? '');
            $data  = $input['data'] ?? [];

            $period = trim($data['price_period'] ?? '');
            $coil   = trim($data['coil_from'] ?? '');
            $cust   = trim($data['customer_id'] ?? '');

            if (empty($period) || empty($coil) || empty($cust)) {
                echo json_encode(['status' => false, 'message' => 'Missing period, coil or customer'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            switch ($tbl) {
                case 'PRICMSTR2': // Alloy
                    $alloy = trim($data['alloy'] ?? '');
                    $chk = $conn->prepare("SELECT COUNT(*) FROM PRICMSTR2 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ?");
                    $chk->execute([$period, $coil, $cust, $alloy]);
                    if ($chk->fetchColumn() > 0) {
                        echo json_encode(['status' => false, 'message' => "Alloy {$alloy} มีอยู่ในระบบแล้ว กรุณาแก้ไขตัวเลขบนตารางแทนการเพิ่มใหม่"], JSON_UNESCAPED_UNICODE);
                        exit();
                    }
                    $sql = "INSERT INTO PRICMSTR2 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, ALLOY, ALLOY_PREMIUM) VALUES (?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, $alloy, (float)$data['premium']]);
                    break;

                case 'PRICMSTR3': // Temper
                    $alloy  = trim($data['alloy'] ?? '');
                    $temper = trim($data['temper'] ?? '');
                    $chk = $conn->prepare("SELECT COUNT(*) FROM PRICMSTR3 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ? AND RTRIM(LTRIM(TEMPER)) = ?");
                    $chk->execute([$period, $coil, $cust, $alloy, $temper]);
                    if ($chk->fetchColumn() > 0) {
                        echo json_encode(['status' => false, 'message' => "Alloy {$alloy} - Temper {$temper} มีอยู่แล้ว"], JSON_UNESCAPED_UNICODE);
                        exit();
                    }
                    $sql = "INSERT INTO PRICMSTR3 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, ALLOY, TEMPER, TEMPER_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, $alloy, $temper, (float)$data['premium']]);
                    break;

                case 'PRICMSTR4': // Surface
                    $alloy = trim($data['alloy'] ?? '');
                    $sg    = trim($data['surface_grade'] ?? '');
                    $chk = $conn->prepare("SELECT COUNT(*) FROM PRICMSTR4 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(ALLOY)) = ? AND RTRIM(LTRIM(SURFACE_GRADE)) = ?");
                    $chk->execute([$period, $coil, $cust, $alloy, $sg]);
                    if ($chk->fetchColumn() > 0) {
                        echo json_encode(['status' => false, 'message' => "Alloy {$alloy} - Surface {$sg} มีอยู่แล้ว"], JSON_UNESCAPED_UNICODE);
                        exit();
                    }
                    $sql = "INSERT INTO PRICMSTR4 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, ALLOY, SURFACE_GRADE, SG_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, $alloy, $sg, (float)$data['premium']]);
                    break;

                case 'PRICMSTR5': // Metallurgical
                    $mg = trim($data['metallurgical_grade'] ?? '');
                    $chk = $conn->prepare("SELECT COUNT(*) FROM PRICMSTR5 WHERE RTRIM(LTRIM(PRICE_PERIOD)) = ? AND RTRIM(LTRIM(COIL_FROM)) = ? AND RTRIM(LTRIM(CSTMSPPL_ID)) = ? AND RTRIM(LTRIM(METALLURGICAL_GRADE)) = ?");
                    $chk->execute([$period, $coil, $cust, $mg]);
                    if ($chk->fetchColumn() > 0) {
                        echo json_encode(['status' => false, 'message' => "Grade {$mg} มีอยู่แล้ว"], JSON_UNESCAPED_UNICODE);
                        exit();
                    }
                    $sql = "INSERT INTO PRICMSTR5 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, METALLURGICAL_GRADE, MG_PREMIUM) VALUES (?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, $mg, (float)$data['premium']]);
                    break;

                case 'PRICMSTR6': // Thickness
                    $sql = "INSERT INTO PRICMSTR6 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, THICKNESS_FROM, THICKNESS_TO, THICKNESS_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, sprintf('%.3f', (float)$data['thickness_from']), sprintf('%.3f', (float)$data['thickness_to']), (float)$data['premium']]);
                    break;

                case 'PRICMSTR7': // Diameter
                    $sql = "INSERT INTO PRICMSTR7 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, PRODUCT_ID, DIAMETER_FROM, DIAMETER_TO, DIAMETER_PREMIUM) VALUES (?,?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, trim($data['product_id']), (float)$data['diameter_from'], (float)$data['diameter_to'], (float)$data['premium']]);
                    break;

                case 'PRICMSTR8': // Length
                    $sql = "INSERT INTO PRICMSTR8 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, LENGTH_FROM, LENGTH_TO, LENGTH_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, (float)$data['length_from'], (float)$data['length_to'], (float)$data['premium']]);
                    break;

                case 'PRICMSTR9': // Quantity
                    $sql = "INSERT INTO PRICMSTR9 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, QUANTITY_FROM, QUANTITY_TO, QUANTITY_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, (float)$data['quantity_from'], (float)$data['quantity_to'], (float)$data['premium']]);
                    break;

                case 'PRICMSTR10': // Tolerance
                    $sql = "INSERT INTO PRICMSTR10 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, TOLERANCE_FROM, TOLERANCE_TO, TOLERANCE_PREMIUM) VALUES (?,?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, (int)$data['tolerance_from'], (int)$data['tolerance_to'], (int)$data['premium']]);
                    break;

                case 'PRICMSTR11': // Full Coil
                    $sql = "INSERT INTO PRICMSTR11 (PRICE_PERIOD, COIL_FROM, CSTMSPPL_ID, FULL_COIL, FULLCOIL_PREMIUM) VALUES (?,?,?,?,?)";
                    $conn->prepare($sql)->execute([$period, $coil, $cust, trim($data['full_coil']), (float)$data['premium']]);
                    break;

                default:
                    echo json_encode(['status' => false, 'message' => 'Invalid table'], JSON_UNESCAPED_UNICODE);
                    exit();
            }

            echo json_encode(['status' => true, 'message' => 'Row added successfully'], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete_premium_row':
            $input = json_decode(file_get_contents('php://input'), true);
            $tbl   = trim($input['table'] ?? '');
            $keys  = $input['keys'] ?? [];

            $whereParts = ["RTRIM(LTRIM(PRICE_PERIOD)) = :period", "RTRIM(LTRIM(COIL_FROM)) = :coil", "RTRIM(LTRIM(CSTMSPPL_ID)) = :cust"];
            $params = [
                ':period' => trim($keys['price_period']),
                ':coil'   => trim($keys['coil_from']),
                ':cust'   => trim($keys['customer_id'])
            ];

            if ($tbl === 'PRICMSTR2' || $tbl === 'PRICMSTR3' || $tbl === 'PRICMSTR4') {
                $whereParts[] = "RTRIM(LTRIM(ALLOY)) = :alloy";
                $params[':alloy'] = trim($keys['alloy']);
            }
            if ($tbl === 'PRICMSTR3') {
                $whereParts[] = "RTRIM(LTRIM(TEMPER)) = :temper";
                $params[':temper'] = trim($keys['temper']);
            }
            if ($tbl === 'PRICMSTR4') {
                $whereParts[] = "RTRIM(LTRIM(SURFACE_GRADE)) = :sg";
                $params[':sg'] = trim($keys['surface_grade']);
            }
            if ($tbl === 'PRICMSTR5') {
                $whereParts[] = "RTRIM(LTRIM(METALLURGICAL_GRADE)) = :mg";
                $params[':mg'] = trim($keys['metallurgical_grade']);
            }
            if ($tbl === 'PRICMSTR6') {
                $whereParts[] = "RTRIM(LTRIM(THICKNESS_FROM)) = :thk_from AND RTRIM(LTRIM(THICKNESS_TO)) = :thk_to";
                $params[':thk_from'] = trim($keys['thickness_from']);
                $params[':thk_to']   = trim($keys['thickness_to']);
            }
            if ($tbl === 'PRICMSTR7') {
                $whereParts[] = "RTRIM(LTRIM(PRODUCT_ID)) = :pid AND DIAMETER_FROM = :dia_from AND DIAMETER_TO = :dia_to";
                $params[':pid']      = trim($keys['product_id']);
                $params[':dia_from'] = (float)$keys['diameter_from'];
                $params[':dia_to']   = (float)$keys['diameter_to'];
            }
            if ($tbl === 'PRICMSTR8') {
                $whereParts[] = "LENGTH_FROM = :len_from AND LENGTH_TO = :len_to";
                $params[':len_from'] = (float)$keys['length_from'];
                $params[':len_to']   = (float)$keys['length_to'];
            }
            if ($tbl === 'PRICMSTR9') {
                $whereParts[] = "QUANTITY_FROM = :qty_from AND QUANTITY_TO = :qty_to";
                $params[':qty_from'] = (float)$keys['quantity_from'];
                $params[':qty_to']   = (float)$keys['quantity_to'];
            }
            if ($tbl === 'PRICMSTR10') {
                $whereParts[] = "TOLERANCE_FROM = :tol_from AND TOLERANCE_TO = :tol_to";
                $params[':tol_from'] = (int)$keys['tolerance_from'];
                $params[':tol_to']   = (int)$keys['tolerance_to'];
            }
            if ($tbl === 'PRICMSTR11') {
                $whereParts[] = "RTRIM(LTRIM(FULL_COIL)) = :full_coil";
                $params[':full_coil'] = trim($keys['full_coil']);
            }

            $sqlDel = "DELETE FROM {$tbl} WHERE " . implode(' AND ', $whereParts);
            $conn->prepare($sqlDel)->execute($params);

            echo json_encode(['status' => true, 'message' => 'Row deleted successfully'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['status' => false, 'message' => 'Action not defined']);
            break;
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}