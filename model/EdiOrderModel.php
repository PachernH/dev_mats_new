<?php
require_once __DIR__ . '/SimpleXlsxReader.php';

class EdiOrderModel {
    private $pdo;

    const PB_MIN_WT   = 25.0;   // น้ำหนักขั้นต่ำ 25 kg. สำหรับจอง Pallet (AUTO_EDI)
    const PB_BP_WIDTH = 160.0;  // ความกว้างสูงสุด Bottom Plate MIL 1002

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function formatItem3Digits($item): string {
        $clean = trim((string)$item);
        if ($clean === '') return '';
        if (is_numeric($clean)) {
            $clean = (string)(int)$clean;
        }
        return str_pad($clean, 3, '0', STR_PAD_LEFT);
    }

    /**
     * สูตรคำนวณน้ำหนักต่อชิ้น (WeightPerPiece)
     */
    public function calculateWeightPerPiece(string $productId, float $width, float $length, float $thickness, float $areaSize = 0.0): float {
        $productId = strtoupper(trim($productId));
        if ($width == 0.0 || $thickness == 0.0) return 0.0;

        switch ($productId) {
            case 'CC':
            case 'CR':
                $val = (((((22.0 / 7.0) * ($width * $width)) / 4.0) * $thickness) / 1000000.0) * 2.71;
                return round($val, 4);
            case 'SH':
                if ($length == 0.0) return 0.0;
                $val = (($width * $length * $thickness) / 1000000.0) * 2.71;
                return round($val, 4);
            case 'NC':
                if ($areaSize != 0.0) {
                    $val = (($areaSize * $thickness) / 1000000.0) * 2.71;
                    return round($val, 4);
                }
                return 0.0;
            case 'CO':
                return 3500.0;
            default:
                return 0.0;
        }
    }

    // =========================================================================
    // PART A: ระบบ AUTO_EDI (CSV ลูกค้า MILO - หน้า load_edi_data_mats.php)
    // =========================================================================

    public function calculatePriceFromMaster(
        string $period, string $coilFrom, string $customerId, string $saleOrderPrefixOrNo,
        string $poNoWithBuyer, string $product, string $alloy, string $temper,
        string $sg, string $mg, float $thickness, float $width, float $length,
        float $orderWeight, int $minTolerance, string $fullCoil
    ): array {
        $result = [
            'found' => false, 'price' => 0.0, 'exchange_rate' => 0.0, 'lme_average' => 0.0,
            'period' => $period, 'breakdown' => [], 'error_msg' => ''
        ];

        if ($period === '' || $poNoWithBuyer === '') {
            $result['error_msg'] = 'ไม่พบ Price Period สำหรับช่วงวันที่นี้';
            return $result;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM PRICMSTR1 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ?");
        $stmt->execute([$period, $coilFrom, $customerId]);
        $p1 = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p1) {
            $result['error_msg'] = "ไม่พบข้อมูลราคาหลัก PRICMSTR1 ($period / $coilFrom / $customerId)";
            return $result;
        }

        $exchangeRate      = round((float)$p1['EXCHANGE_RATE'], 5);
        $lmeAverage        = round((float)$p1['LME_AVERAGE'], 2);
        $ingotPremium      = round((float)$p1['INGOT_PREMIUM'], 2);
        $importDutyPercent = (int)$p1['INGOT_IMPORTDUTY'];
        $importDuty        = (($lmeAverage + $ingotPremium) * $importDutyPercent) / 100.0;
        $ingotFreight      = (float)$p1['INGOT_FREIGHT'];
        $meltLossPercent   = (int)$p1['INGOT_MELTLOSS'];
        $meltLoss1         = (($lmeAverage + $ingotPremium + $importDuty + $ingotFreight) * $meltLossPercent) / 100.0;
        $meltLoss2         = (($lmeAverage + $ingotPremium + $ingotFreight) * $meltLossPercent) / 100.0;
        $productPacking    = (float)$p1['PRODUCT_PACKING'];
        $productFreight    = (float)$p1['PRODUCT_FREIGHT'];
        $fuelSurcharge     = (float)$p1['FUEL_SURCHARGE'];
        $refundInterest    = round((float)$p1['REFUND_INTEREST'], 4);

        $baseConvCircle = round((float)$p1['BASE_CONVERSIONCIRCLE'], 4);
        $baseConvSheet  = round((float)$p1['BASE_CONVERSIONSHEET'], 4);
        $baseConvCoil   = round((float)$p1['BASE_CONVERSIONCOIL'], 4);

        $commonCost1 = $lmeAverage + $ingotPremium + $importDuty + $ingotFreight + $meltLoss1 + $productPacking + $productFreight + $fuelSurcharge + $refundInterest;
        $commonCost2 = $lmeAverage + $ingotPremium + $ingotFreight + $meltLoss2 + $productPacking + $productFreight + $fuelSurcharge + $refundInterest;

        $priceCircle1 = round($commonCost1 + $baseConvCircle, 4);
        $priceCircle2 = round($commonCost2 + $baseConvCircle, 4);
        $priceSheet1  = round($commonCost1 + $baseConvSheet, 4);
        $priceSheet2  = round($commonCost2 + $baseConvSheet, 4);
        $priceCoil1   = round($commonCost1 + $baseConvCoil, 4);
        $priceCoil2   = round($commonCost2 + $baseConvCoil, 4);

        $stmt = $this->pdo->prepare("SELECT ALLOY_PREMIUM FROM PRICMSTR2 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ?");
        $stmt->execute([$period, $coilFrom, $customerId, $alloy]);
        if (!($p2 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ Alloy Premium ($alloy)"; return $result; }
        $alloyPremium = (float)$p2['ALLOY_PREMIUM'];

        $stmt = $this->pdo->prepare("SELECT TEMPER_PREMIUM FROM PRICMSTR3 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ? AND TEMPER = ?");
        $stmt->execute([$period, $coilFrom, $customerId, $alloy, $temper]);
        if (!($p3 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ Temper Premium ($temper)"; return $result; }
        $temperPremium = (float)$p3['TEMPER_PREMIUM'];

        $stmt = $this->pdo->prepare("SELECT SG_PREMIUM FROM PRICMSTR4 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND ALLOY = ? AND SURFACE_GRADE = ?");
        $stmt->execute([$period, $coilFrom, $customerId, $alloy, $sg]);
        if (!($p4 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ SG Premium ($sg)"; return $result; }
        $surfacePremium = (float)$p4['SG_PREMIUM'];

        $stmt = $this->pdo->prepare("SELECT MG_PREMIUM FROM PRICMSTR5 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND METALLURGICAL_GRADE = ?");
        $stmt->execute([$period, $coilFrom, $customerId, $mg]);
        if (!($p5 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ MG Premium ($mg)"; return $result; }
        $metallurgicalPremium = (float)$p5['MG_PREMIUM'];

        $stmt = $this->pdo->prepare("SELECT THICKNESS_PREMIUM FROM PRICMSTR6 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND THICKNESS_FROM <= ? AND THICKNESS_TO >= ?");
        $stmt->execute([$period, $coilFrom, $customerId, $thickness, $thickness]);
        if (!($p6 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ Thickness Premium ($thickness)"; return $result; }
        $thicknessPremium = (float)$p6['THICKNESS_PREMIUM'];

        $checkProd = ($product === 'NC') ? 'CC' : $product;
        $checkDim  = ($product === 'NC' && $length > $width) ? round($length, 0) : round($width, 0);
        $stmt = $this->pdo->prepare("SELECT DIAMETER_PREMIUM FROM PRICMSTR7 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND PRODUCT_ID = ? AND DIAMETER_FROM <= ? AND DIAMETER_TO >= ?");
        $stmt->execute([$period, $coilFrom, $customerId, $checkProd, $checkDim, $checkDim]);
        if (!($p7 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ Diameter/Width Premium ($checkDim)"; return $result; }
        $diameterPremium = (float)$p7['DIAMETER_PREMIUM'];

        $lengthPremium = 0.0;
        if ($product === 'SH') {
            $checkLen = round($length, 0);
            $stmt = $this->pdo->prepare("SELECT LENGTH_PREMIUM FROM PRICMSTR8 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND LENGTH_FROM <= ? AND LENGTH_TO >= ?");
            $stmt->execute([$period, $coilFrom, $customerId, $checkLen, $checkLen]);
            if ($p8 = $stmt->fetch(PDO::FETCH_ASSOC)) $lengthPremium = (float)$p8['LENGTH_PREMIUM'];
        }

        $stmt = $this->pdo->prepare("SELECT QUANTITY_PREMIUM FROM PRICMSTR9 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND QUANTITY_FROM <= ? AND QUANTITY_TO >= ?");
        $stmt->execute([$period, $coilFrom, $customerId, $orderWeight, $orderWeight]);
        if (!($p9 = $stmt->fetch(PDO::FETCH_ASSOC))) { $result['error_msg'] = "ไม่พบ Quantity Premium ($orderWeight kg)"; return $result; }
        $quantityPremium = (float)$p9['QUANTITY_PREMIUM'];

        $tolerancePremium = 0.0;
        $stmt = $this->pdo->prepare("SELECT TOLERANCE_PREMIUM FROM PRICMSTR10 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND TOLERANCE_FROM <= ? AND TOLERANCE_TO >= ?");
        $stmt->execute([$period, $coilFrom, $customerId, $minTolerance, $minTolerance]);
        if ($p10 = $stmt->fetch(PDO::FETCH_ASSOC)) $tolerancePremium = (float)$p10['TOLERANCE_PREMIUM'];

        $fullCoilPremium = 0.0;
        $stmt = $this->pdo->prepare("SELECT FULLCOIL_PREMIUM FROM PRICMSTR11 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ? AND FULL_COIL = ?");
        $stmt->execute([$period, $coilFrom, $customerId, $fullCoil]);
        if ($p11 = $stmt->fetch(PDO::FETCH_ASSOC)) $fullCoilPremium = (float)$p11['FULLCOIL_PREMIUM'];

        $fclPremium = 0.0;
        $stmt = $this->pdo->prepare("SELECT FCL_PREMIUM FROM PRICMSTR12 WHERE PRICE_PERIOD = ? AND COIL_FROM = ? AND CSTMSPPL_ID = ?");
        $stmt->execute([$period, $coilFrom, $customerId]);
        if ($p12 = $stmt->fetch(PDO::FETCH_ASSOC)) $fclPremium = (float)$p12['FCL_PREMIUM'];

        $totalPremiums = $alloyPremium + $temperPremium + $surfacePremium + $metallurgicalPremium +
                         $thicknessPremium + $diameterPremium + $lengthPremium + $quantityPremium +
                         $tolerancePremium + $fullCoilPremium + $fclPremium;

        $soType = substr($saleOrderPrefixOrNo, 0, 2);
        if ($soType === 'SL') {
            if (substr($poNoWithBuyer, -1) === '9') {
                $base = ($product === 'CC' || $product === 'NC') ? $priceCircle1 : (($product === 'SH') ? $priceSheet1 : $priceCoil1);
                $calcPrice = round((($base + $totalPremiums) * $exchangeRate) / 1000.0, 4);
            } else {
                $result['error_msg'] = "Local Order (SL) แต่รหัส PO-Buyer ($poNoWithBuyer) ไม่ได้ลงท้ายด้วย 9";
                return $result;
            }
        } else {
            $base = ($product === 'CC' || $product === 'NC') ? $priceCircle2 : (($product === 'SH') ? $priceSheet2 : $priceCoil2);
            $calcPrice = round((($base + $totalPremiums) * $exchangeRate) / 1000.0, 4);
        }

        $result['found']         = true;
        $result['price']         = $calcPrice;
        $result['exchange_rate'] = $exchangeRate;
        $result['lme_average']   = $lmeAverage;
        $result['breakdown']     = [
            'Price Period' => $period, 'Base Price ($/MT)' => $base, 'Alloy Premium' => $alloyPremium,
            'Temper Premium' => $temperPremium, 'SG Premium' => $surfacePremium, 'MG Premium' => $metallurgicalPremium,
            'Thickness Premium' => $thicknessPremium, 'Width/Diam Premium' => $diameterPremium, 'Length Premium' => $lengthPremium,
            'Quantity Premium' => $quantityPremium, 'Tolerance Premium' => $tolerancePremium, 'Full Coil Premium' => $fullCoilPremium,
            'FCL Premium' => $fclPremium, 'Total ($/MT)' => round($base + $totalPremiums, 4), 'Exchange Rate' => $exchangeRate
        ];
        return $result;
    }

    public function validateCsvRow(array $col, int $rowNum, string $uploadDate): array {
        $alloy       = trim($col[6] ?? '');
        $currency    = strtoupper(trim($col[20] ?? ''));
        $scrapRaw    = strtoupper(trim($col[21] ?? ''));
        $coilRaw     = strtoupper(trim($col[22] ?? ''));
        $paperRaw    = strtoupper(trim($col[25] ?? ''));
        $lmeRaw      = trim($col[26] ?? '');
        $lmeDateRaw  = trim($col[27] ?? '');
        $etdRaw      = trim($col[14] ?? '');

        $etdTimestamp = strtotime(str_replace('/', '-', $etdRaw));
        $etdDate      = $etdTimestamp ? date('Y-m-d', $etdTimestamp) : $etdRaw;
        $lmeDate      = empty($lmeDateRaw) ? '2000-01-01' : date('Y-m-d', strtotime(str_replace('/', '-', $lmeDateRaw)));

        if (in_array($coilRaw, ['MAL', 'MAT'])) {
            $coilFrom = $coilRaw;
        } else {
            $malAlloys = ['1050', '1070', '3003', '3004', '3105', '4006', '5052', '6061'];
            $coilFrom  = in_array($alloy, $malAlloys) ? 'MAL' : 'MAT';
        }

        $data = [
            'row_num' => $rowNum, 'edited' => false,
            'customer' => trim($col[0] ?? ''), 'buyer_id' => trim($col[1] ?? ''),
            'po_no' => trim($col[2] ?? ''), 'po_item' => $this->formatItem3Digits($col[3] ?? ''),
            'customer_partno' => trim($col[4] ?? ''), 'product' => strtoupper(trim($col[5] ?? '')),
            'alloy' => $alloy, 'temper' => trim($col[7] ?? ''),
            'sg' => trim($col[8] ?? ''), 'mg' => trim($col[9] ?? ''),
            'thickness' => round((float)trim($col[10] ?? 0), 3), 'width' => round((float)trim($col[11] ?? 0), 3),
            'length' => round((float)trim($col[12] ?? 0), 3), 'uom_dim' => strtolower(trim($col[13] ?? '')),
            'etd_date' => $etdDate, 'order_weight' => round((float)trim($col[15] ?? 0), 0),
            'order_piece' => 0, 'uom_weight' => strtolower(trim($col[16] ?? '')),
            'max_tol' => (int)round((float)trim($col[17] ?? 0), 0), 'min_tol' => (int)round((float)trim($col[18] ?? 0), 0),
            'unit_price' => round((float)trim($col[19] ?? 0), 4), 'currency' => $currency,
            'scrap_wire' => in_array($scrapRaw, ['Y', 'N']) ? $scrapRaw : 'N', 'coil_from' => $coilFrom,
            'grade' => trim($col[23] ?? ''), 't5_temp' => trim($col[24] ?? ''),
            'paper_interleave' => in_array($paperRaw, ['Y', 'N']) ? $paperRaw : 'N',
            'lme' => $lmeRaw === '' ? 0 : round((float)$lmeRaw, 2), 'lme_date' => $lmeDate,
            'end_customer_name' => trim($col[28] ?? ''), 'end_customer_po' => trim($col[29] ?? ''),
            'work_process' => trim($col[30] ?? '')
        ];

        return $this->validateMappedRow($data, $uploadDate);
    }

    public function validateMappedRow(array $data, string $uploadDate): array {
        $messages = []; $remarks = [];
        $hasError = false; $isDuplicate = false; $priceMismatch = false;

        $rowNum       = (int)($data['row_num'] ?? 0);
        $isEdited     = !empty($data['edited']);
        $customer     = trim($data['customer'] ?? '');
        $buyerId      = trim($data['buyer_id'] ?? '');
        $poNo         = trim($data['po_no'] ?? '');
        $poItem       = $this->formatItem3Digits($data['po_item'] ?? '');
        $custPartNo   = trim($data['customer_partno'] ?? '');
        $product      = strtoupper(trim($data['product'] ?? ''));
        $alloy        = trim($data['alloy'] ?? '');
        $temper       = trim($data['temper'] ?? '');
        $sg           = trim($data['sg'] ?? '');
        $mg           = trim($data['mg'] ?? '');
        $thickness    = round((float)($data['thickness'] ?? 0), 3);
        $width        = round((float)($data['width'] ?? 0), 3);
        $length       = round((float)($data['length'] ?? 0), 3);
        $uomDim       = strtolower(trim($data['uom_dim'] ?? ''));
        $etdRaw       = trim($data['etd_date'] ?? '');
        $orderQty     = round((float)($data['order_weight'] ?? 0), 0);
        $uomWeight    = strtolower(trim($data['uom_weight'] ?? ''));
        $maxTol       = max(0, min(100, (int)($data['max_tol'] ?? 0)));
        $minTol       = (int)($data['min_tol'] ?? 0);
        $unitPrice    = round((float)($data['unit_price'] ?? 0), 4);
        $currency     = strtoupper(trim($data['currency'] ?? ''));
        $coilFrom     = strtoupper(trim($data['coil_from'] ?? ''));

        $etdTimestamp = strtotime(str_replace('/', '-', $etdRaw));
        $etdDate      = $etdTimestamp ? date('Y-m-d', $etdTimestamp) : '';
        $exchangeRate  = ($currency === 'BAHT') ? 35 : 1;
        $orderCategory = (substr($buyerId, -1) === 'C') ? 'OVMI' : 'ORDR';

        if (!in_array($coilFrom, ['MAL', 'MAT'])) {
            $malAlloys = ['1050', '1070', '3003', '3004', '3105', '4006', '5052', '6061'];
            $coilFrom  = in_array($alloy, $malAlloys) ? 'MAL' : 'MAT';
        }

        if ($customer === '' || !$this->existsInMaster("SELECT 1 FROM CSSPMSTR1 WHERE CSTMSPPL_ID = ?", [$customer])) {
            $hasError = true; $messages[] = "ไม่พบรหัสลูกค้า ($customer)";
        }
        if ($poNo === '' || $poItem === '') {
            $hasError = true; $messages[] = "กรุณาระบุเลขที่ PO และ Item";
        }

        $productModel = ''; $areaSize = 0.0;
        if ($product === 'CO') $productModel = 'COIL';
        elseif ($product === 'CC') $productModel = 'CIRCLE';
        elseif ($product === 'SH' || $product === 'NC') {
            $stmt = $this->pdo->prepare("SELECT PRODUCT_MODEL, AREA_SIZE FROM PRODMSTR1 WHERE CUSTOMER_PARTNO = ?");
            $stmt->execute([$custPartNo]);
            if ($prodRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $product = 'NC';
                $productModel = $prodRow['PRODUCT_MODEL'] ?? '';
                $areaSize = (float)($prodRow['AREA_SIZE'] ?? 0);
            } else {
                if ($product === 'SH') $productModel = 'SHEET';
                else $messages[] = "คำเตือน: ไม่พบ NC Part No ($custPartNo) ใน PRODMSTR1";
            }
        } else {
            $hasError = true; $messages[] = "รหัส Product ($product) ไม่ถูกต้อง";
        }

        if (!$this->existsInMaster("SELECT 1 FROM CMPSMSTR1 WHERE ALLOY = ? AND CSTMSPPL_ID = ?", [$alloy, $coilFrom])) {
            $hasError = true; $messages[] = "ไม่พบ Alloy ($alloy) ของ $coilFrom";
        }
        if (!$this->existsInMaster("SELECT 1 FROM TMPRMSTR1 WHERE TEMPER = ?", [$temper])) {
            $hasError = true; $messages[] = "ไม่พบ Temper ($temper)";
        }
        if (!$this->existsInMaster("SELECT 1 FROM SGRDMSTR1 WHERE SURFACE_GRADE = ?", [$sg])) {
            $hasError = true; $messages[] = "ไม่พบ SG ($sg)";
        }
        if (!$this->existsInMaster("SELECT 1 FROM MGRDMSTR1 WHERE METALLURGICAL_GRADE = ?", [$mg])) {
            $hasError = true; $messages[] = "ไม่พบ MG ($mg)";
        }
        if (!$this->existsInMaster("SELECT 1 FROM UOMSMSTR1 WHERE UOM_TYPE = 'D' AND UOM = ?", [$uomDim])) {
            $hasError = true; $messages[] = "ไม่พบหน่วยขนาด ($uomDim)";
        }
        if (!$this->existsInMaster("SELECT 1 FROM UOMSMSTR1 WHERE UOM_TYPE = 'W' AND UOM = ?", [$uomWeight])) {
            $hasError = true; $messages[] = "ไม่พบหน่วยน้ำหนัก ($uomWeight)";
        }

        if ($minTol < 0 || $minTol > 100) {
            $hasError = true; $messages[] = "Min Tolerance ($minTol) ต้องอยู่ระหว่าง 0-100";
        }
        if (empty($etdDate)) {
            $hasError = true; $messages[] = "รูปแบบวันที่ ETD ไม่ถูกต้อง";
        } elseif ($etdDate <= $uploadDate) {
            $hasError = true; $messages[] = "วันที่ ETD ($etdDate) ต้องมากกว่าวัน Upload ($uploadDate)";
        }

        if ($width < 10) { $messages[] = "คำเตือน: Width ($width) < 10"; $remarks[] = "Incorrect Width : $width"; }
        if (($product === 'SH' || $product === 'NC') && $length < 5) { $messages[] = "คำเตือน: Length ($length) < 5"; $remarks[] = "Incorrect Length : $length"; }

        $systemPrice = null; $priceBreakdown = [];
        if (!$hasError && substr($customer, 0, 3) === 'MIL') {
            $stmtP = $this->pdo->prepare("SELECT TOP 1 PRICE_PERIOD FROM PRICMSTR1 WHERE COIL_FROM = ? AND CSTMSPPL_ID = ? AND LIFETIME_FROM <= ? AND LIFETIME_TO >= ?");
            $stmtP->execute([$coilFrom, $customer, $uploadDate, $uploadDate]);
            $pRow = $stmtP->fetch(PDO::FETCH_ASSOC);
            $pricePeriod = $pRow['PRICE_PERIOD'] ?? '';

            $orderWeightKg = ($uomWeight === 'lbs.') ? round($orderQty / 2.2, 0) : round($orderQty, 0);
            $fullCoil = ($product === 'SH') ? 'F' : (($orderWeightKg % 1200 === 0) ? 'F' : 'N');
            $soPrefix = (substr($buyerId, 2, 1) === '9' || substr($buyerId, -1) === '9') ? 'SL-' : 'SO-';

            $priceCalc = $this->calculatePriceFromMaster(
                $pricePeriod, $coilFrom, $customer, $soPrefix,
                $poNo . '-' . $buyerId, $product, $alloy, $temper,
                $sg, $mg, $thickness, $width, $length,
                $orderWeightKg, $minTol, $fullCoil
            );

            if ($priceCalc['found']) {
                $systemPrice = $priceCalc['price'];
                $priceBreakdown = $priceCalc['breakdown'];
                if (abs($unitPrice - $systemPrice) > 0.0001) {
                    $priceMismatch = true;
                    $messages[] = "ราคาไม่ตรงระบบ (CSV: " . number_format($unitPrice, 4) . " / ระบบ: " . number_format($systemPrice, 4) . ")";
                }
            } elseif ($priceCalc['error_msg'] !== '') {
                $messages[] = "คำเตือนราคา: " . $priceCalc['error_msg'];
            }
        }

        $stmt = $this->pdo->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER FROM EDIORDER1 WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?");
        $stmt->execute([$customer, $poNo, $poItem]);
        if ($dupRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $isDuplicate = true;
            if (!empty($dupRow['JOB_ORDER'])) { $messages[] = "ซ้ำ: สร้าง Job ({$dupRow['JOB_ORDER']}) ไปแล้ว"; $remarks[] = "Sale & Job Order ready"; }
            elseif (!empty($dupRow['SALEORDER_NO'])) { $messages[] = "ซ้ำ: สร้าง SO ({$dupRow['SALEORDER_NO']}) ไปแล้ว"; $remarks[] = "Sale Order ready"; }
            else { $messages[] = "พบ PO-Item ($poNo-$poItem) ซ้ำในระบบ"; }
            $remarks[] = "Duplicate data";
        }
        if ($isEdited) $remarks[] = "Edited on Web";

        $status = $hasError ? 'ERROR' : ($priceMismatch ? 'PRICE_MISMATCH' : ($isDuplicate ? 'DUPLICATE' : 'VALID'));

        return [
            'row_num' => $rowNum, 'edited' => $isEdited, 'status' => $status, 'messages' => $messages,
            'remark_str' => substr(implode(' + ', $remarks), 0, 200),
            'customer' => $customer, 'buyer_id' => $buyerId, 'po_no' => $poNo, 'po_item' => $poItem,
            'order_category' => $orderCategory, 'end_customer_name' => trim($data['end_customer_name'] ?? ''),
            'end_customer_po' => trim($data['end_customer_po'] ?? ''), 'customer_partno' => $custPartNo,
            'product' => $product, 'product_model' => $productModel, 'area_size' => $areaSize,
            'alloy' => $alloy, 'temper' => $temper, 'grade' => trim($data['grade'] ?? ''),
            'sg' => $sg, 'mg' => $mg, 't5_temp' => trim($data['t5_temp'] ?? ''),
            'thickness' => $thickness, 'width' => $width, 'length' => $length, 'uom_dim' => $uomDim,
            'paper_interleave' => $data['paper_interleave'] ?? 'N', 'etd_date' => $etdDate,
            'currency' => $currency, 'unit_price' => $unitPrice, 'system_price' => $systemPrice,
            'price_breakdown' => $priceBreakdown, 'exchange_rate' => $exchangeRate,
            'order_weight' => $orderQty, 'order_piece' => 0, 'uom_weight' => $uomWeight,
            'max_tol' => $maxTol, 'min_tol' => $minTol, 'scrap_wire' => $data['scrap_wire'] ?? 'N',
            'coil_from' => $coilFrom, 'lme' => (float)($data['lme'] ?? 0),
            'lme_date' => $data['lme_date'] ?? '2000-01-01', 'work_process' => trim($data['work_process'] ?? '')
        ];
    }

    public function saveBatchOrders(array $rows, string $uploadDate, bool $overwriteDup, string $userOperate): array {
        try {
            $this->pdo->beginTransaction();

            $sqlInsert = "INSERT INTO EDIORDER1 (
                CSTMSPPL_ID, PO_NO, PO_ITEM, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, ORDER_CATEGORY,
                BUYER_ID, CTM2_ENDCUSTOMERNAME, CTM2_ENDCUSTOMERPO, CUSTOMER_PARTNO, PRODUCT_ID,
                PRODUCT_MODEL, AREA_SIZE, ALLOY, TEMPER, GRACE, SURFACE_GRADE, METALLURGICAL_GRADE,
                T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, UOM_DIMENSION, PAPER_INTERLEAVE, UPLOAD_DATE,
                ETD_DATE, CURRENCY_ID, UNIT_PRICE, EXCHANGE_RATE, ORDER_WEIGHT, ORDER_PIECE,
                UOM_WEIGHT, MAX_TOLERANCE, MIN_TOLERANCE, SCRAP_WIRE, COIL_FROM, LME, LME_DATE,
                WORK_PROCESS, ORDER_REMARK, REMARK, EDI_OPERATOR, EDI_OPERATEDATE, EDI_STATUS, UPLOAD_SOURCE
            ) VALUES (
                ?, ?, ?, '', '', '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, GETDATE(), 'OP', 'AUTO_EDI'
            )";

            $sqlUpdate = "UPDATE EDIORDER1 SET
                ORDER_CATEGORY = ?, BUYER_ID = ?, CTM2_ENDCUSTOMERNAME = ?, CTM2_ENDCUSTOMERPO = ?,
                CUSTOMER_PARTNO = ?, PRODUCT_ID = ?, PRODUCT_MODEL = ?, AREA_SIZE = ?, ALLOY = ?, TEMPER = ?,
                GRACE = ?, SURFACE_GRADE = ?, METALLURGICAL_GRADE = ?, T5_TEMPERATURE = ?, THICKNESS = ?,
                WIDTH = ?, LENGTH = ?, UOM_DIMENSION = ?, PAPER_INTERLEAVE = ?, UPLOAD_DATE = ?, ETD_DATE = ?,
                CURRENCY_ID = ?, UNIT_PRICE = ?, EXCHANGE_RATE = ?, ORDER_WEIGHT = ?, ORDER_PIECE = ?,
                UOM_WEIGHT = ?, MAX_TOLERANCE = ?, MIN_TOLERANCE = ?, SCRAP_WIRE = ?, COIL_FROM = ?, LME = ?,
                LME_DATE = ?, WORK_PROCESS = ?, REMARK = ?, UPLOAD_SOURCE = 'AUTO_EDI'
                WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?";

            $stmtInsert = $this->pdo->prepare($sqlInsert);
            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $inserted = 0; $updated = 0;

            foreach ($rows as $r) {
                if ($r['status'] === 'ERROR') throw new Exception("พบข้อมูลที่ไม่ผ่านการตรวจสอบในแถวที่ {$r['row_num']}");
                $poItem3 = $this->formatItem3Digits($r['po_item']);

                $stmtChk = $this->pdo->prepare("SELECT 1 FROM EDIORDER1 WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?");
                $stmtChk->execute([$r['customer'], $r['po_no'], $poItem3]);
                if ($stmtChk->fetchColumn()) {
                    if (!$overwriteDup) continue;
                    $stmtUpdate->execute([
                        $r['order_category'], $r['buyer_id'], $r['end_customer_name'], $r['end_customer_po'],
                        $r['customer_partno'], $r['product'], $r['product_model'], $r['area_size'], $r['alloy'], $r['temper'],
                        $r['grade'], $r['sg'], $r['mg'], $r['t5_temp'], $r['thickness'],
                        $r['width'], $r['length'], $r['uom_dim'], $r['paper_interleave'], $uploadDate, $r['etd_date'],
                        $r['currency'], $r['unit_price'], $r['exchange_rate'], $r['order_weight'], $r['order_piece'],
                        $r['uom_weight'], $r['max_tol'], $r['min_tol'], $r['scrap_wire'], $r['coil_from'], $r['lme'],
                        $r['lme_date'], $r['work_process'], $r['remark_str'],
                        $r['customer'], $r['po_no'], $poItem3
                    ]);
                    $updated++;
                } else {
                    $stmtInsert->execute([
                        $r['customer'], $r['po_no'], $poItem3, $r['order_category'],
                        $r['buyer_id'], $r['end_customer_name'], $r['end_customer_po'], $r['customer_partno'], $r['product'],
                        $r['product_model'], $r['area_size'], $r['alloy'], $r['temper'], $r['grade'], $r['sg'], $r['mg'],
                        $r['t5_temp'], $r['thickness'], $r['width'], $r['length'], $r['uom_dim'], $r['paper_interleave'], $uploadDate,
                        $r['etd_date'], $r['currency'], $r['unit_price'], $r['exchange_rate'], $r['order_weight'], $r['order_piece'],
                        $r['uom_weight'], $r['max_tol'], $r['min_tol'], $r['scrap_wire'], $r['coil_from'], $r['lme'], $r['lme_date'],
                        $r['work_process'], $r['remark_str'], $userOperate
                    ]);
                    $inserted++;
                }
            }
            $this->pdo->commit();
            return ['inserted' => $inserted, 'updated' => $updated];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // PART B: ระบบ MANUAL_EXCEL (ไฟล์ .xlsx - หน้า load_edi_manual_mats.php)
    // =========================================================================

    /**
     * อ่านไฟล์ .xlsx (Sheet 'master') แปลงข้อมูลและส่งตรวจสอบ
     */
    public function parseAndValidateManualExcel(string $filePath, string $uploadDate): array {
        $rawRows = SimpleXlsxReader::parse($filePath, 'master');
        if (empty($rawRows)) {
            throw new Exception('ไม่พบข้อมูลในไฟล์ Excel');
        }

        // แถวที่ 1 คือ Header -> Map ชื่อหัวคอลัมน์เป็น Index
        $headerRow = reset($rawRows);
        $colMap = [];
        foreach ($headerRow as $idx => $colName) {
            $key = strtoupper(trim((string)$colName));
            if ($key !== '') {
                $colMap[$key] = $idx;
            }
        }

        $getVal = function(array $row, array $possibleNames) use ($colMap): string {
            foreach ($possibleNames as $name) {
                $k = strtoupper($name);
                if (isset($colMap[$k])) {
                    return trim((string)($row[$colMap[$k]] ?? ''));
                }
            }
            return '';
        };

        $validatedRows = [];
        $summary = ['total' => 0, 'valid' => 0, 'duplicate' => 0, 'error' => 0];
        $isFirst = true;

        foreach ($rawRows as $excelRowNum => $r) {
            if ($isFirst) {
                $isFirst = false;
                continue; // ข้ามแถว Header
            }

            $customer = $getVal($r, ['CSTMSPPL_ID']);
            $poNo     = $getVal($r, ['CTM2_PONO', 'PO_NO']);
            $prodId   = $getVal($r, ['PRODUCT_ID']);

            // ข้ามแถวว่างคั่นกลาง (เช่น แถวที่ 31 และ 34 ใน EDI test.xlsx)
            if ($customer === '' && $poNo === '' && $prodId === '') {
                continue;
            }

            $lmeTypeRaw = $getVal($r, ['CTM2_LMETPE', 'CTM2_LMETYPE']);
            if ($lmeTypeRaw === 'D-DIALY') $lmeTypeRaw = 'D-DAILY';

            $mapped = [
                'row_num'            => $excelRowNum,
                'edited'             => false,
                'customer'           => $customer,
                'po_no'              => $poNo,
                'po_item'            => $this->formatItem3Digits($getVal($r, ['CTM2_POITEM', 'PO_ITEM'])),
                'order_category'     => strtoupper($getVal($r, ['ORDER_CATEGORY'])),
                'material_in'        => strtoupper($getVal($r, ['Material in', 'MATERIAL_IN'])),
                'schedule_date'      => SimpleXlsxReader::parseExcelDate($getVal($r, ['SCHEDULE_DATE'])),
                'request_date'       => SimpleXlsxReader::parseExcelDate($getVal($r, ['REQUEST_DATE'])),
                'customer_order'     => $getVal($r, ['CUSTOMER_ORDER']),
                'customer_saleorder' => $getVal($r, ['CUSTOMER_SALEORDER']),
                'end_customer_name'  => $getVal($r, ['CTM2_ENDCUSTOMERNAME']),
                'end_customer_po'    => $getVal($r, ['CTM2_ENDCUSTOMERPO']),
                'customer_partno'    => $getVal($r, ['CTM2_CUSTOMERPARTNO']),
                'product'            => strtoupper($prodId),
                'alloy'              => $getVal($r, ['ALLOY']),
                'temper'             => $getVal($r, ['TEMPER']),
                'produce_temper1'    => $getVal($r, ['PRODUCE_TEMPER1']),
                'produce_temper2'    => $getVal($r, ['PRODUCE_TEMPER2']),
                'grade'              => $getVal($r, ['GRADE']),
                'produce_grade1'     => $getVal($r, ['PRODUCE_GRADE1']),
                'produce_grade2'     => $getVal($r, ['PRODUCE_GRADE2']),
                'sg'                 => $getVal($r, ['SURFACE_GRADE']),
                'mg'                 => $getVal($r, ['METALLURGICAL_GRADE']),
                't5_temp'            => $getVal($r, ['T5_TEMPERATURE']),
                'flatness_grade'     => $getVal($r, ['FLATNESS_GRADE']),
                'thickness'          => round((float)$getVal($r, ['THICKNESS']), 3),
                'width'              => round((float)$getVal($r, ['WIDTH']), 3),
                'length'             => round((float)$getVal($r, ['LENGTH']), 3),
                'uom_dim'            => strtolower($getVal($r, ['CT2U_UOMDIMENSION'])),
                'work_process'       => strtoupper($getVal($r, ['WORK_PROCESS'])),
                'order_weight'       => round((float)$getVal($r, ['CTM2_ORDERWEIGHT']), 0),
                'uom_weight'         => strtolower($getVal($r, ['CT2U_UOMWEIGHT'])),
                'coil_max_weight'    => round((float)$getVal($r, ['COIL_WEIGHTMAXPRICE', 'COIL_WEIGHTMAXPIECE']), 0),
                'max_tol'            => (int)round((float)$getVal($r, ['JOB_MAXTOLERANCE']), 0),
                'min_tol'            => (int)round((float)$getVal($r, ['JOB_MINTOLERANCE']), 0),
                'job_remark'         => $getVal($r, ['JOB_REMARK']),
                'exchange_rate'      => (float)$getVal($r, ['EXCHANGE_RATE']),
                'unit_price'         => round((float)$getVal($r, ['CTM2_ACTUALPRICE']), 4),
                'currency'           => strtoupper($getVal($r, ['CURRENCY_ID'])),
                'lme_type'           => $lmeTypeRaw,
                'lme_date'           => SimpleXlsxReader::parseExcelDate($getVal($r, ['CTM2_LMEDATE'])),
                'lme'                => round((float)$getVal($r, ['CTM2_ORDERLME']), 2),
                'mjp_date'           => SimpleXlsxReader::parseExcelDate($getVal($r, ['CTM2_MJPDATE'])),
                'coil_from'          => strtoupper($getVal($r, ['UseCoilForm', 'COIL_FROM'])),
                'excel_so_no'        => $getVal($r, ['Sale Order']),
                'excel_so_item'      => $getVal($r, ['Sale Item']),
                'excel_job_no'       => $getVal($r, ['Job Order'])
            ];

            $res = $this->validateManualRow($mapped, $uploadDate);
            $summary['total']++;
            if ($res['status'] === 'ERROR') $summary['error']++;
            elseif ($res['status'] === 'DUPLICATE') $summary['duplicate']++;
            else $summary['valid']++;

            $validatedRows[] = $res;
        }

        return ['summary' => $summary, 'rows' => $validatedRows];
    }

    /**
     * ตรวจสอบเงื่อนไข Manual Excel (ใช้ทั้งตอนอ่านไฟล์ครั้งแรก และ Re-validate หลังแก้บนหน้าเว็บ)
     */
    public function validateManualRow(array $data, string $uploadDate): array {
        $messages    = [];
        $remarks     = [];
        $hasError    = false;
        $isDuplicate = false;

        $rowNum        = (int)($data['row_num'] ?? 0);
        $isEdited      = !empty($data['edited']);
        $customer      = trim($data['customer'] ?? '');
        $poNo          = trim($data['po_no'] ?? '');
        $poItem        = $this->formatItem3Digits($data['po_item'] ?? '');
        $orderCategory = strtoupper(trim($data['order_category'] ?? ''));
        $materialIn    = strtoupper(trim($data['material_in'] ?? ''));
        $scheduleDate  = trim($data['schedule_date'] ?? '');
        $requestDate   = trim($data['request_date'] ?? '');
        if ($requestDate === '') $requestDate = $scheduleDate;

        $custPartNo    = trim($data['customer_partno'] ?? '');
        $product       = strtoupper(trim($data['product'] ?? ''));
        $alloy         = trim($data['alloy'] ?? '');
        $temper        = trim($data['temper'] ?? '');
        $prodTemper1   = trim($data['produce_temper1'] ?? '');
        $prodTemper2   = trim($data['produce_temper2'] ?? '');
        $grade         = trim($data['grade'] ?? '');
        $prodGrade1    = trim($data['produce_grade1'] ?? '');
        $prodGrade2    = trim($data['produce_grade2'] ?? '');
        $sg            = trim($data['sg'] ?? '');
        $mg            = trim($data['mg'] ?? '');
        $t5Temp        = trim($data['t5_temp'] ?? '');
        $flatnessGrade = trim($data['flatness_grade'] ?? '');

        $thickness     = round((float)($data['thickness'] ?? 0), 3);
        $width         = round((float)($data['width'] ?? 0), 3);
        $length        = round((float)($data['length'] ?? 0), 3);
        $uomDim        = strtolower(trim($data['uom_dim'] ?? ''));
        $workProcess   = strtoupper(trim($data['work_process'] ?? ''));
        $orderWeight   = round((float)($data['order_weight'] ?? 0), 0);
        $uomWeight     = strtolower(trim($data['uom_weight'] ?? ''));
        $coilMaxWeight = round((float)($data['coil_max_weight'] ?? 0), 0);
        $maxTol        = (int)round((float)($data['max_tol'] ?? 0), 0);
        $minTol        = (int)round((float)($data['min_tol'] ?? 0), 0);
        $jobRemark     = trim($data['job_remark'] ?? '');
        $exchangeRate  = (float)($data['exchange_rate'] ?? 0);
        $unitPrice     = round((float)($data['unit_price'] ?? 0), 4);
        $currency      = strtoupper(trim($data['currency'] ?? ''));
        $lmeType       = trim($data['lme_type'] ?? '');
        $lmeDate       = trim($data['lme_date'] ?? '');
        $lme           = round((float)($data['lme'] ?? 0), 2);
        $mjpDate       = trim($data['mjp_date'] ?? '');
        $coilFrom      = strtoupper(trim($data['coil_from'] ?? ''));

        // 1. ตรวจสอบลูกค้าใน CSSPMSTR1 และดึงค่า Default กรณีใน Excel เว้นว่างไว้
        $stmtC = $this->pdo->prepare("SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = ?");
        $stmtC->execute([$customer]);
        $custRow = $stmtC->fetch(PDO::FETCH_ASSOC);

        if (!$custRow) {
            $hasError = true;
            $messages[] = "ไม่พบรหัสลูกค้า ($customer) ใน CSSPMSTR1";
        } else {
            if ($currency === '') $currency = strtoupper(trim($custRow['CURRENCY_ID'] ?? 'USD'));
            if ($uomDim === '')   $uomDim   = strtolower(trim($custRow['UOM_DIMENSION'] ?? 'mm.'));
            if ($uomWeight === '') $uomWeight = strtolower(trim($custRow['UOM_WEIGHT'] ?? 'kg.'));
        }

        if ($uomDim === 'mm') $uomDim = 'mm.';
        elseif ($uomDim === 'inch.') $uomDim = 'inch';

        if ($uomWeight === 'kg') $uomWeight = 'kg.';
        elseif ($uomWeight === 'lbs') $uomWeight = 'lbs.';

        // 2. ตรวจสอบอัตราแลกเปลี่ยน
        if ($exchangeRate <= 0) {
            if ($currency === 'USD') {
                $exchangeRate = 1.00000;
            } elseif ($currency === 'BAHT') {
                $hasError = true;
                $messages[] = "สกุลเงิน BAHT กรุณาระบุ Exchange Rate (20 - 60)";
            }
        } elseif ($currency === 'BAHT' && ($exchangeRate < 20 || $exchangeRate > 60)) {
            $hasError = true;
            $messages[] = "Exchange Rate ($exchangeRate) ต้องอยู่ระหว่าง 20 ถึง 60";
        }

        // 3. ตรวจสอบ PO / Item และวันที่
        if ($poNo === '' || $poItem === '') {
            $hasError = true;
            $messages[] = "กรุณาระบุ CTM2_PONO และ CTM2_POITEM";
        }
        if ($scheduleDate === '') {
            $hasError = true;
            $messages[] = "กรุณาระบุ SCHEDULE_DATE ให้ถูกต้อง";
        }

        // 4. ตรวจสอบ Product & Part No (PRODMSTR1)
        $productModel = '';
        $areaSize = 0.0;
        if ($product === 'CO') {
            $productModel = 'COIL';
            if ($coilMaxWeight <= 0) {
                $hasError = true;
                $messages[] = "สินค้า COIL ต้องระบุน้ำหนักสูงสุดต่อลูก (COIL_WEIGHTMAXPRICE > 0)";
            }
        } elseif ($product === 'CC') {
            $productModel = 'CIRCLE';
        } elseif ($product === 'WR') {
            $productModel = 'WRAP';
        } elseif ($product === 'SH' || $product === 'NC') {
            $stmtP = $this->pdo->prepare("SELECT PRODUCT_MODEL, AREA_SIZE FROM PRODMSTR1 WHERE CUSTOMER_PARTNO = ?");
            $stmtP->execute([$custPartNo]);
            if ($prodRow = $stmtP->fetch(PDO::FETCH_ASSOC)) {
                $product = 'NC';
                $productModel = $prodRow['PRODUCT_MODEL'] ?? '';
                $areaSize = (float)($prodRow['AREA_SIZE'] ?? 0);
            } else {
                if ($product === 'SH') {
                    $productModel = 'SHEET';
                } else {
                    $hasError = true;
                    $messages[] = "ไม่พบ Customer Part No ($custPartNo) ใน PRODMSTR1 สำหรับสินค้า NC";
                }
            }
        } else {
            $hasError = true;
            $messages[] = "รหัส Product ($product) ไม่ถูกต้อง";
        }

        // 5. กำหนด Coil From และตรวจสอบความสอดคล้องกับ Order Category + Material In
        if (!in_array($coilFrom, ['MAL', 'MAT'])) {
            $malAlloys = ['1050', '1070', '3003', '3004', '3105', '4006', '5052', '6061'];
            $coilFrom  = in_array($alloy, $malAlloys) ? 'MAL' : 'MAT';
        }

        $validMatIn = ['NONE-BOI', 'BOI-IMP1', 'BOI-IMP2', 'BOI-GRS1', 'BOI-MAT1', 'BOI-MAT2'];
        if (!in_array($materialIn, $validMatIn)) {
            $hasError = true;
            $messages[] = "Material In ($materialIn) ไม่ถูกต้อง";
        } else {
            if (in_array($orderCategory, ['ORDR', 'ORD1', 'ORD2'])) {
                if (in_array($materialIn, ['NONE-BOI', 'BOI-IMP1', 'BOI-IMP2']) && $coilFrom === 'MAT') {
                    $hasError = true;
                    $messages[] = "Order Category ($orderCategory), Material In ($materialIn) และ Coil From ($coilFrom) ไม่สอดคล้องกัน";
                } elseif (in_array($materialIn, ['BOI-MAT1', 'BOI-MAT2', 'BOI-GRS1']) && $coilFrom !== 'MAT') {
                    $hasError = true;
                    $messages[] = "Order Category ($orderCategory), Material In ($materialIn) และ Coil From ($coilFrom) ไม่สอดคล้องกัน";
                }
            } elseif ($orderCategory === 'OVMI') {
                if (in_array($materialIn, ['NONE-BOI', 'BOI-IMP1', 'BOI-IMP2'])) {
                    $hasError = true;
                    $messages[] = "OVMI ต้องใช้ Material In กลุ่ม BOI-MAT1/2 หรือ BOI-GRS1 เท่านั้น";
                }
            }
        }

        if ($workProcess === '') {
            $hasError = true;
            $messages[] = "กรุณาระบุ WORK_PROCESS";
        }

        // 6. ดึง LME และ MJP จาก LME3MNTH1 หากในไฟล์ใส่มาเป็น 0
        $mjpPrice = 0.0;
        $lmeFieldMap = [
            'D-DAILY' => 'LME3M_DAILY', 'D-DIALY' => 'LME3M_DAILY',
            'B-WBP LME' => 'LME3M_WBPLME', 'W-WBP' => 'LME3M_WBP', 'M-MONTHLY' => 'LME3M_MONTHLY'
        ];
        if (isset($lmeFieldMap[$lmeType]) && $lmeDate !== '' && $lmeDate > '2000-01-01') {
            $colLme = $lmeFieldMap[$lmeType];
            $stmtL = $this->pdo->prepare("SELECT $colLme AS lme_val FROM LME3MNTH1 WHERE LME_DATE = ?");
            $stmtL->execute([$lmeDate]);
            if ($lRow = $stmtL->fetch(PDO::FETCH_ASSOC)) {
                if ($lme == 0) $lme = round((float)$lRow['lme_val'], 2);
            }
        }
        if ($mjpDate !== '' && $mjpDate > '2000-01-01') {
            $stmtM = $this->pdo->prepare("SELECT MJP FROM LME3MNTH1 WHERE LME_DATE = ?");
            $stmtM->execute([$mjpDate]);
            if ($mRow = $stmtM->fetch(PDO::FETCH_ASSOC)) {
                $mjpPrice = round((float)$mRow['MJP'], 2);
            }
        }

        // 7. สร้าง JOB_REMARK อัตโนมัติจาก STNDMSTR6 หากใน Excel เว้นว่างไว้
        if ($jobRemark === '' && $customer !== '' && $product !== '' && $alloy !== '') {
            $stmtS6 = $this->pdo->prepare("SELECT TOP 1 * FROM STNDMSTR6 WHERE CSTMSPPL_ID = ? AND PRODUCT_ID >= ? AND ALLOY = ? AND TEMPER = ? AND THICKNESS = ? AND WIDTH = ? AND LENGTH = ?");
            $stmtS6->execute([$customer, $product, $alloy, $temper, $thickness, $width, $length]);
            if ($s6 = $stmtS6->fetch(PDO::FETCH_ASSOC)) {
                $pth  = number_format((float)($s6['THICKNESS_PTOLERANCE'] ?? 0), 2);
                $mth  = number_format((float)($s6['THICKNESS_MTOLERANCE'] ?? 0), 2);
                $pw   = number_format((float)($s6['WIDTH_PTOLERANCE'] ?? 0), 2);
                $mw   = number_format((float)($s6['WIDTH_MTOLERANCE'] ?? 0), 2);
                $pl   = number_format((float)($s6['LENGTH_PTOLERANCE'] ?? 0), 2);
                $ml   = number_format((float)($s6['LENGTH_MTOLERANCE'] ?? 0), 2);
                $flt  = number_format((float)($s6['FLATNESS'] ?? 0), 2);
                $et   = trim((string)($s6['EARING_TYPE'] ?? ''));
                $ep   = (int)($s6['EARING_PERCENT'] ?? 0);

                $r1 = "Export Order and use pallet HT/Thickness Tolerance +$pth -$mth mm.";
                if ($ml === '0.00' && $pl === '0.00') {
                    $r2 = "Diameter Tolerance +$pw -$mw mm./Flatness Tolerance <=$flt mm.";
                    $r3 = ($et === '' && $ep == 0) ? '' : "Earing $et=$ep%";
                } else {
                    $r2 = "Diameter Tolerance +$pw -$mw mm./Length Tolerance +$pl -$ml mm.";
                    $r3 = ($et === '' && $ep == 0) ? "Flatness Tolerance <=$flt mm." : "Flatness Tolerance <=$flt mm./Earing $et=$ep%";
                }
                $jobRemark = trim($r1 . ' ' . $r2 . ' ' . $r3);
            }
        }

        // 8. ตรวจสอบข้อมูลซ้ำในตารางจริง (CSTMORDR2 / JOBORDER1) และตารางพัก (EDIORDER1)
        $stmtReal = $this->pdo->prepare("SELECT A.SALEORDER_NO, A.SALEORDER_ITEM, B.JOB_ORDER FROM CSTMORDR2 AS A LEFT JOIN JOBORDER1 AS B ON A.SALEORDER_NO = B.SALEORDER_NO AND A.SALEORDER_ITEM = B.SALEORDER_ITEM WHERE A.CTM2_PONO = ? AND A.CTM2_POITEM = ?");
        $stmtReal->execute([$poNo, $poItem]);
        if ($realRow = $stmtReal->fetch(PDO::FETCH_ASSOC)) {
            $hasError = true;
            $messages[] = "สร้างในระบบแล้ว: SO {$realRow['SALEORDER_NO']}-{$realRow['SALEORDER_ITEM']} / Job {$realRow['JOB_ORDER']}";
        } else {
            $stmtEdi = $this->pdo->prepare("SELECT SALEORDER_NO, JOB_ORDER FROM EDIORDER1 WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?");
            $stmtEdi->execute([$customer, $poNo, $poItem]);
            if ($ediRow = $stmtEdi->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($ediRow['SALEORDER_NO']) || !empty($ediRow['JOB_ORDER'])) {
                    $hasError = true;
                    $messages[] = "ซ้ำใน EDI: ออก SO ({$ediRow['SALEORDER_NO']}) / Job ({$ediRow['JOB_ORDER']}) ไปแล้ว";
                } else {
                    $isDuplicate = true;
                    $messages[] = "พบ PO-Item ($poNo-$poItem) ซ้ำใน EDIORDER1 (สถานะ OP)";
                    $remarks[] = "Duplicate data";
                }
            }
        }

        if ($isEdited) $remarks[] = "Edited on Web";
        $status = $hasError ? 'ERROR' : ($isDuplicate ? 'DUPLICATE' : 'VALID');

        return [
            'row_num'            => $rowNum,
            'edited'             => $isEdited,
            'status'             => $status,
            'messages'           => $messages,
            'remark_str'         => substr(implode(' + ', $remarks), 0, 200),
            'customer'           => $customer,
            'po_no'              => $poNo,
            'po_item'            => $poItem,
            'order_category'     => $orderCategory,
            'material_in'        => $materialIn,
            'schedule_date'      => $scheduleDate,
            'request_date'       => $requestDate,
            'customer_order'     => trim($data['customer_order'] ?? ''),
            'customer_saleorder' => trim($data['customer_saleorder'] ?? ''),
            'end_customer_name'  => trim($data['end_customer_name'] ?? ''),
            'end_customer_po'    => trim($data['end_customer_po'] ?? ''),
            'customer_partno'    => $custPartNo,
            'product'            => $product,
            'product_model'      => $productModel,
            'area_size'          => $areaSize,
            'alloy'              => $alloy,
            'temper'             => $temper,
            'produce_temper1'    => $prodTemper1,
            'produce_temper2'    => $prodTemper2,
            'grade'              => $grade,
            'produce_grade1'     => $prodGrade1,
            'produce_grade2'     => $prodGrade2,
            'sg'                 => $sg,
            'mg'                 => $mg,
            't5_temp'            => $t5Temp,
            'flatness_grade'     => $flatnessGrade,
            'thickness'          => $thickness,
            'width'              => $width,
            'length'             => $length,
            'uom_dim'            => $uomDim,
            'work_process'       => $workProcess,
            'order_weight'       => $orderWeight,
            'uom_weight'         => $uomWeight,
            'coil_max_weight'    => $coilMaxWeight,
            'max_tol'            => $maxTol,
            'min_tol'            => $minTol,
            'job_remark'         => substr($jobRemark, 0, 255),
            'exchange_rate'      => $exchangeRate,
            'unit_price'         => $unitPrice,
            'currency'           => $currency,
            'lme_type'           => $lmeType,
            'lme_date'           => $lmeDate !== '' ? $lmeDate : '1900-01-01',
            'lme'                => $lme,
            'mjp_date'           => $mjpDate !== '' ? $mjpDate : '1900-01-01',
            'mjp_price'          => $mjpPrice,
            'coil_from'          => $coilFrom
        ];
    }

    /**
     * บันทึกข้อมูล Manual Excel ลง EDIORDER1 (UPLOAD_SOURCE = 'MANUAL_EXCEL')
     */
    public function saveManualBatchOrders(array $rows, string $uploadDate, bool $overwriteDup, string $userOperate): array {
        try {
            $this->pdo->beginTransaction();

            $sqlInsert = "INSERT INTO EDIORDER1 (
                CSTMSPPL_ID, PO_NO, PO_ITEM, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, ORDER_CATEGORY,
                BUYER_ID, CTM2_ENDCUSTOMERNAME, CTM2_ENDCUSTOMERPO, CUSTOMER_PARTNO, PRODUCT_ID,
                PRODUCT_MODEL, AREA_SIZE, ALLOY, TEMPER, GRACE, SURFACE_GRADE, METALLURGICAL_GRADE,
                T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, UOM_DIMENSION, PAPER_INTERLEAVE, UPLOAD_DATE,
                ETD_DATE, CURRENCY_ID, UNIT_PRICE, EXCHANGE_RATE, ORDER_BY, ORDER_WEIGHT, ORDER_PIECE,
                UOM_WEIGHT, MAX_TOLERANCE, MIN_TOLERANCE, SCRAP_WIRE, COIL_FROM, LME, LME_DATE,
                WORK_PROCESS, ORDER_REMARK, REMARK, ORDER_STARTDATE, EDI_OPERATOR, EDI_OPERATEDATE, EDI_STATUS,
                UPLOAD_SOURCE, MATERIAL_IN, CUSTOMER_ORDER, CUSTOMER_SALEORDER, REQUEST_DATE,
                PRODUCE_TEMPER1, PRODUCE_TEMPER2, PRODUCE_GRADE1, PRODUCE_GRADE2, FLATNESS_GRADE,
                COIL_MAX_WEIGHT, LMETYPE, MJPDATE, MJPPRICE, JOB_REMARK
            ) VALUES (
                ?, ?, ?, '', '', '', ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'N', ?, ?, ?, ?, ?, 'W', ?, 0, ?, ?, ?, 'N', ?, ?, ?, ?, '', ?, ?, ?, GETDATE(), 'OP',
                'MANUAL_EXCEL', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

            $sqlUpdate = "UPDATE EDIORDER1 SET
                ORDER_CATEGORY = ?, CTM2_ENDCUSTOMERNAME = ?, CTM2_ENDCUSTOMERPO = ?, CUSTOMER_PARTNO = ?,
                PRODUCT_ID = ?, PRODUCT_MODEL = ?, AREA_SIZE = ?, ALLOY = ?, TEMPER = ?, GRACE = ?,
                SURFACE_GRADE = ?, METALLURGICAL_GRADE = ?, T5_TEMPERATURE = ?, THICKNESS = ?, WIDTH = ?, LENGTH = ?,
                UOM_DIMENSION = ?, UPLOAD_DATE = ?, ETD_DATE = ?, CURRENCY_ID = ?, UNIT_PRICE = ?, EXCHANGE_RATE = ?,
                ORDER_WEIGHT = ?, UOM_WEIGHT = ?, MAX_TOLERANCE = ?, MIN_TOLERANCE = ?, COIL_FROM = ?, LME = ?, LME_DATE = ?,
                WORK_PROCESS = ?, REMARK = ?, ORDER_STARTDATE = ?, UPLOAD_SOURCE = 'MANUAL_EXCEL',
                MATERIAL_IN = ?, CUSTOMER_ORDER = ?, CUSTOMER_SALEORDER = ?, REQUEST_DATE = ?,
                PRODUCE_TEMPER1 = ?, PRODUCE_TEMPER2 = ?, PRODUCE_GRADE1 = ?, PRODUCE_GRADE2 = ?, FLATNESS_GRADE = ?,
                COIL_MAX_WEIGHT = ?, LMETYPE = ?, MJPDATE = ?, MJPPRICE = ?, JOB_REMARK = ?
                WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?";

            $stmtInsert = $this->pdo->prepare($sqlInsert);
            $stmtUpdate = $this->pdo->prepare($sqlUpdate);
            $inserted = 0; $updated = 0;

            foreach ($rows as $r) {
                if ($r['status'] === 'ERROR') {
                    throw new Exception("พบข้อมูลที่ไม่ผ่านการตรวจสอบในแถวที่ {$r['row_num']}");
                }
                $poItem3 = $this->formatItem3Digits($r['po_item']);

                $stmtChk = $this->pdo->prepare("SELECT 1 FROM EDIORDER1 WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?");
                $stmtChk->execute([$r['customer'], $r['po_no'], $poItem3]);
                if ($stmtChk->fetchColumn()) {
                    if (!$overwriteDup) continue;

                    $stmtUpdate->execute([
                        $r['order_category'], $r['end_customer_name'], $r['end_customer_po'], $r['customer_partno'],
                        $r['product'], $r['product_model'], $r['area_size'], $r['alloy'], $r['temper'], $r['grade'],
                        $r['sg'], $r['mg'], $r['t5_temp'], $r['thickness'], $r['width'], $r['length'],
                        $r['uom_dim'], $uploadDate, $r['schedule_date'], $r['currency'], $r['unit_price'], $r['exchange_rate'],
                        $r['order_weight'], $r['uom_weight'], $r['max_tol'], $r['min_tol'], $r['coil_from'], $r['lme'], $r['lme_date'],
                        $r['work_process'], $r['remark_str'], $r['schedule_date'],
                        $r['material_in'], $r['customer_order'], $r['customer_saleorder'], $r['request_date'],
                        $r['produce_temper1'], $r['produce_temper2'], $r['produce_grade1'], $r['produce_grade2'], $r['flatness_grade'],
                        $r['coil_max_weight'], $r['lme_type'], $r['mjp_date'], $r['mjp_price'], $r['job_remark'],
                        $r['customer'], $r['po_no'], $poItem3
                    ]);
                    $updated++;
                } else {
                    $stmtInsert->execute([
                        $r['customer'], $r['po_no'], $poItem3, $r['order_category'],
                        $r['end_customer_name'], $r['end_customer_po'], $r['customer_partno'], $r['product'],
                        $r['product_model'], $r['area_size'], $r['alloy'], $r['temper'], $r['grade'],
                        $r['sg'], $r['mg'], $r['t5_temp'], $r['thickness'], $r['width'], $r['length'],
                        $r['uom_dim'], $uploadDate, $r['schedule_date'], $r['currency'], $r['unit_price'], $r['exchange_rate'],
                        $r['order_weight'], $r['uom_weight'], $r['max_tol'], $r['min_tol'], $r['coil_from'], $r['lme'], $r['lme_date'],
                        $r['work_process'], $r['remark_str'], $r['schedule_date'], $userOperate,
                        $r['material_in'], $r['customer_order'], $r['customer_saleorder'], $r['request_date'],
                        $r['produce_temper1'], $r['produce_temper2'], $r['produce_grade1'], $r['produce_grade2'], $r['flatness_grade'],
                        $r['coil_max_weight'], $r['lme_type'], $r['mjp_date'], $r['mjp_price'], $r['job_remark']
                    ]);
                    $inserted++;
                }
            }

            $this->pdo->commit();
            return ['inserted' => $inserted, 'updated' => $updated];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // PART C: ระบบสร้าง SO & Job Order (รองรับทั้ง AUTO_EDI และ MANUAL_EXCEL)
    // =========================================================================

    public function getPendingEdiOrders(string $uploadDate, string $source = ''): array {
        $sql = "SELECT * FROM EDIORDER1 
                WHERE UPLOAD_DATE = ? AND (SALEORDER_NO = '' OR SALEORDER_NO IS NULL) 
                  AND (JOB_ORDER = '' OR JOB_ORDER IS NULL) AND EDI_STATUS = 'OP' ";
        $params = [$uploadDate];

        if ($source !== '') {
            if ($source === 'AUTO_EDI') {
                $sql .= "AND (UPLOAD_SOURCE = 'AUTO_EDI' OR UPLOAD_SOURCE IS NULL OR UPLOAD_SOURCE = '') ";
            } else {
                $sql .= "AND UPLOAD_SOURCE = ? ";
                $params[] = $source;
            }
        }
        $sql .= "ORDER BY CSTMSPPL_ID, PO_NO, PO_ITEM";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNextSaleOrderNo(string $typePrefix): string {
        $yy          = date('y');
        $prefix      = $typePrefix . $yy . '-'; // SO-26-, SL-26-, TT-26-
        $likePattern = $prefix . '[0-9][0-9][0-9][0-9]%';

        $sql = "SELECT MAX(CAST(SUBSTRING(SALEORDER_NO, 7, 10) AS INT)) AS max_no
                FROM CSTMORDR1 WITH (UPDLOCK, HOLDLOCK)
                WHERE SALEORDER_NO LIKE ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$likePattern]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextNo = ((int)($row['max_no'] ?? 0)) + 1;
        $runStr = ($nextNo < 10000) ? str_pad((string)$nextNo, 4, '0', STR_PAD_LEFT) : (string)$nextNo;
        return $prefix . $runStr;
    }

    private function getNextJobOrderNo(string $typePrefix = 'JB-'): string {
        $yy          = date('y');
        $prefix      = $typePrefix . $yy . '-'; // JB-26-
        $likePattern = $prefix . '[0-9][0-9][0-9][0-9]%';

        $sql = "SELECT MAX(CAST(SUBSTRING(JOB_ORDER, 7, 10) AS INT)) AS max_no
                FROM JOBORDER1 WITH (UPDLOCK, HOLDLOCK)
                WHERE JOB_ORDER LIKE ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$likePattern]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextNo = ((int)($row['max_no'] ?? 0)) + 1;
        $runStr = ($nextNo < 10000) ? str_pad((string)$nextNo, 4, '0', STR_PAD_LEFT) : (string)$nextNo;
        return $prefix . $runStr;
    }

    private function ensureDimensionMasters(float $thickness, float $width, float $length): void {
        if ($thickness > 0 && !$this->existsInMaster("SELECT 1 FROM THCKMSTR1 WHERE THICKNESS = ?", [$thickness])) {
            $this->pdo->prepare("INSERT INTO THCKMSTR1 (THICKNESS) VALUES (?)")->execute([$thickness]);
        }
        if ($width > 0 && !$this->existsInMaster("SELECT 1 FROM DMWDMSTR1 WHERE WIDTH = ?", [$width])) {
            $this->pdo->prepare("INSERT INTO DMWDMSTR1 (WIDTH) VALUES (?)")->execute([$width]);
        }
        if ($length > 0 && !$this->existsInMaster("SELECT 1 FROM LNGTMSTR1 WHERE LENGTH = ?", [$length])) {
            $this->pdo->prepare("INSERT INTO LNGTMSTR1 (LENGTH) VALUES (?)")->execute([$length]);
        }
    }

    private function reservePackagingForJob(
        string $jobOrder, string $customer, string $productId, float $thickness,
        float $width, float $length, float $weightPiece, float $maxJobPiece
    ): void {
        if ($weightPiece <= 0 || $thickness <= 0 || $maxJobPiece <= 0) return;

        $stmt = $this->pdo->prepare("SELECT PACKAGE_TREATMENT FROM CSSPMSTR1 WHERE CSTMSPPL_ID = ?");
        $stmt->execute([$customer]);
        $custRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$custRow) return;

        $pkgTreatment = trim($custRow['PACKAGE_TREATMENT'] ?? '');
        $strW = str_pad(number_format($width, 3, '.', ''), 8, '0', STR_PAD_LEFT);
        $strL = str_pad(number_format($length, 3, '.', ''), 8, '0', STR_PAD_LEFT);

        $sql = "SELECT A.MATERIAL_CODE, A.STACK_ROW, A.STACK_COLUMN, A.WEIGHTPERPACKAGE, B.SMATERIAL_CODE, C.ITEMNUM
                FROM MTRLSPCT2 AS A
                INNER JOIN MTRLSPCT1 AS B ON B.MATERIAL_CODE = A.MATERIAL_CODE
                INNER JOIN MTRLMSTR1 AS C ON A.MATERIAL_CODE = C.MATERIAL_CODE
                WHERE A.PRODUCT_ID = ? AND A.PACKAGE_TREATMENT = ? ";

        $params = [$productId, $pkgTreatment];
        if ($productId === 'NC' && $width < $length) {
            $sql .= "AND A.WIDTH_FROM <= ? AND A.WIDTH_TO >= ? AND A.LENGTH_FROM <= ? AND A.LENGTH_TO >= ? ";
            array_push($params, $strL, $strL, $strW, $strW);
        } else {
            $sql .= "AND A.WIDTH_FROM <= ? AND A.WIDTH_TO >= ? AND A.LENGTH_FROM <= ? AND A.LENGTH_TO >= ? ";
            array_push($params, $strW, $strW, $strL, $strL);
        }

        if (substr($customer, 0, 3) === 'MIL') {
            $sql .= "AND A.MATERIAL_CODE = 'B40X48-NL-1' ";
        }
        $sql .= "ORDER BY A.PACKAGE_PRIORITY";

        $stmtPkg = $this->pdo->prepare($sql);
        $stmtPkg->execute($params);
        if (!($pkg = $stmtPkg->fetch(PDO::FETCH_ASSOC))) return;

        $pkgCode     = trim($pkg['MATERIAL_CODE'] ?? '');
        $pkgMp2Item  = trim($pkg['ITEMNUM'] ?? '');
        $topPkgCode  = trim($pkg['SMATERIAL_CODE'] ?? '');
        $wtPerPkg    = (int)($pkg['WEIGHTPERPACKAGE'] ?? 0);
        $stackRow    = (int)($pkg['STACK_ROW'] ?? 0);
        $stackCol    = ((int)($pkg['STACK_COLUMN'] ?? 0) === 0) ? 1 : (int)$pkg['STACK_COLUMN'];
        $totalStack  = max(1, $stackRow * $stackCol);
        if ($wtPerPkg <= 0) return;

        $stmtTop = $this->pdo->prepare("SELECT B.ITEMNUM FROM MTRLSPCT1 AS A INNER JOIN MTRLMSTR1 AS B ON A.MATERIAL_CODE = B.MATERIAL_CODE WHERE A.MATERIAL_CODE = ?");
        $stmtTop->execute([$topPkgCode]);
        if (!($topRow = $stmtTop->fetch(PDO::FETCH_ASSOC))) return;
        $topPkgMp2Item = trim($topRow['ITEMNUM'] ?? '');

        $productHigh = round((($wtPerPkg / $weightPiece) * $thickness) / $totalStack, 0);
        if ($productHigh <= 0) $productHigh = 1;

        $rawPkgNeed = (($maxJobPiece * $thickness) / $totalStack) / $productHigh;
        $pkgNeed = ($rawPkgNeed < 1.0) ? 1 : (int)round($rawPkgNeed, 0);

        $insMtrl = $this->pdo->prepare("INSERT INTO MTRLCTRL1 (JOB_ORDER, MATERIAL_CODE, ITEMNUM, WEIGHTPERPACKAGE, QTY_JOBRESERVE) VALUES (?, ?, ?, ?, ?)");
        $insMtrl->execute([$jobOrder, $pkgCode, $pkgMp2Item, $wtPerPkg, $pkgNeed]);
        if ($topPkgCode !== '') {
            $insMtrl->execute([$jobOrder, $topPkgCode, $topPkgMp2Item, $wtPerPkg, $pkgNeed]);
        }
    }

    /**
     * สร้าง Sale Order และ Job Order จาก EDIORDER1 (รองรับทั้ง AUTO_EDI และ MANUAL_EXCEL)
     */
    public function generateSoAndJob(string $uploadDate, string $userOperate, string $source = ''): array {
        $pendingRows = $this->getPendingEdiOrders($uploadDate, $source);
        if (empty($pendingRows)) {
            throw new Exception("ไม่พบรายการสถานะ OP ที่รอสร้าง Sale Order / Job Order สำหรับวันที่ $uploadDate");
        }

        try {
            $this->pdo->beginTransaction();

            $generatedList    = [];
            $totalPONo        = '';
            $totalSaleOrderNo = '';
            $saleOrderNo      = '';
            $totalAmount      = 0.0;

            foreach ($pendingRows as $row) {
                $rowSource = trim($row['UPLOAD_SOURCE'] ?? 'AUTO_EDI');
                if ($rowSource === '') $rowSource = 'AUTO_EDI';

                if ($rowSource === 'MANUAL_EXCEL') {
                    $itemRes = $this->processSingleManualRow($row, $userOperate, $totalPONo, $totalSaleOrderNo, $saleOrderNo, $totalAmount);
                } else {
                    $itemRes = $this->processSingleAutoEdiRow($row, $userOperate, $totalPONo, $totalSaleOrderNo, $saleOrderNo, $totalAmount);
                }
                $generatedList[] = $itemRes;
            }

            if ($totalPONo !== '') {
                $updTot = $this->pdo->prepare("UPDATE CSTMORDR1 SET CTM1_TOTALAMOUNT = ? WHERE SALEORDER_NO = ?");
                $updTot->execute([round($totalAmount, 2), $totalSaleOrderNo]);
            }

            $this->pdo->commit();
            return $generatedList;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * ประมวลผลสร้าง SO / Job สำหรับ 1 แถวของ MANUAL_EXCEL (ถอดแบบจาก edi2.txt)
     */
    private function processSingleManualRow(
        array $row, string $userOperate,
        string &$totalPONo, string &$totalSaleOrderNo, string &$saleOrderNo, float &$totalAmount
    ): array {
        $customer       = trim($row['CSTMSPPL_ID'] ?? '');
        $poNo           = trim($row['PO_NO'] ?? '');
        $poItem         = $this->formatItem3Digits($row['PO_ITEM'] ?? '');
        $saleOrderItem  = $poItem;
        $orderCategory  = trim($row['ORDER_CATEGORY'] ?? 'ORDR');
        $materialIn     = trim($row['MATERIAL_IN'] ?? 'BOI-MAT1');
        $scheduleDate   = substr((string)$row['ETD_DATE'], 0, 10);
        $requestDate    = !empty($row['REQUEST_DATE']) ? substr((string)$row['REQUEST_DATE'], 0, 10) : $scheduleDate;
        $custOrder      = trim($row['CUSTOMER_ORDER'] ?? '');
        $custSaleOrder  = trim($row['CUSTOMER_SALEORDER'] ?? '');
        $endCustName    = trim($row['CTM2_ENDCUSTOMERNAME'] ?? '');
        $endCustPo      = trim($row['CTM2_ENDCUSTOMERPO'] ?? '');
        $custPartNo     = trim($row['CUSTOMER_PARTNO'] ?? '');
        $product        = strtoupper(trim($row['PRODUCT_ID'] ?? ''));
        $productModel   = trim($row['PRODUCT_MODEL'] ?? '');
        $areaSize       = (float)($row['AREA_SIZE'] ?? 0);
        $alloy          = trim($row['ALLOY'] ?? '');
        $temper         = trim($row['TEMPER'] ?? '');
        $prodTemper1    = trim($row['PRODUCE_TEMPER1'] ?? '');
        $prodTemper2    = trim($row['PRODUCE_TEMPER2'] ?? '');
        $grade          = trim($row['GRACE'] ?? '');
        $prodGrade1     = trim($row['PRODUCE_GRADE1'] ?? '');
        $prodGrade2     = trim($row['PRODUCE_GRADE2'] ?? '');
        $sg             = trim($row['SURFACE_GRADE'] ?? '');
        $mg             = trim($row['METALLURGICAL_GRADE'] ?? '');
        $t5Temp         = trim($row['T5_TEMPERATURE'] ?? '');
        $flatnessGrade  = trim($row['FLATNESS_GRADE'] ?? '');
        $thickness      = round((float)($row['THICKNESS'] ?? 0), 3);
        $width          = round((float)($row['WIDTH'] ?? 0), 3);
        $length         = round((float)($row['LENGTH'] ?? 0), 3);
        $ct2uUomDim     = trim($row['UOM_DIMENSION'] ?? 'mm.');
        $workProcess    = trim($row['WORK_PROCESS'] ?? '');
        $rawOrderWeight = (float)($row['ORDER_WEIGHT'] ?? 0);
        $ct2uUomWeight  = trim($row['UOM_WEIGHT'] ?? 'kg.');
        $coilMaxWeight  = (float)($row['COIL_MAX_WEIGHT'] ?? 0);
        $jobMaxTol      = (int)($row['MAX_TOLERANCE'] ?? 0);
        $jobMinTol      = (int)($row['MIN_TOLERANCE'] ?? 0);
        $jobRemarkRaw   = trim($row['JOB_REMARK'] ?? '');
        $exchangeRate   = (float)($row['EXCHANGE_RATE'] ?? 1);
        $actualPrice    = round((float)($row['UNIT_PRICE'] ?? 0), 4);
        $currency       = trim($row['CURRENCY_ID'] ?? 'USD');
        $lmeType        = trim($row['LMETYPE'] ?? '');
        $lmeDate        = !empty($row['LME_DATE']) ? substr((string)$row['LME_DATE'], 0, 10) : '1900-01-01';
        $orderLme       = (float)($row['LME'] ?? 0);
        $mjpDate        = !empty($row['MJPDATE']) ? substr((string)$row['MJPDATE'], 0, 10) : '1900-01-01';
        $orderMjp       = (float)($row['MJPPRICE'] ?? 0);
        $coilFrom       = trim($row['COIL_FROM'] ?? 'MAT');
        $todayStr       = date('Y-m-d');

        // 1. ออกเลข Job Order (ทุกแถว) และ Sale Order (ตาม PO_NO)
        $jobOrderNo = $this->getNextJobOrderNo('JB-');
        if ($totalPONo !== $poNo) {
            $soPrefix = ($orderCategory === 'ORSP') ? 'TT-' : 'SO-';
            $saleOrderNo = $this->getNextSaleOrderNo($soPrefix);
        }

        // 2. ข้อมูลลูกค้าจาก CSSPMSTR1
        $stmtC = $this->pdo->prepare("SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = ?");
        $stmtC->execute([$customer]);
        $custRow = $stmtC->fetch(PDO::FETCH_ASSOC) ?: [];

        $supplierMatCode    = trim($custRow['SUPPLIER_MATCODE'] ?? '');
        $paymentId          = trim($custRow['PAYMENT_ID'] ?? '');
        $shipmentId         = trim($custRow['SHIPMENT_ID'] ?? '');
        $taxPercent         = (float)($custRow['CSSP_TAXPERCENT'] ?? 0);
        $portLoading        = trim($custRow['CSSP_PORTLOADING'] ?? '');
        $portDischarge      = trim($custRow['CSSP_PORTDISCHARGE'] ?? '');
        $placeDestination   = trim($custRow['CSSP_PLACEDESTINATION'] ?? '');
        $countryDestination = trim($custRow['CSSP_COUNTRYDESTINATION'] ?? '');
        $orderBy            = trim($custRow['ORDER_BY'] ?? 'W');
        $showScrapWire      = (int)($custRow['SHOW_SCRAPWIRE'] ?? 0);

        // 3. แปลงหน่วย CT2U (ถ้าลูกค้าเป็น inch ให้หาร 25.4 / ถ้าเป็น lbs. ให้คูณ 2.2)
        if (strtolower($ct2uUomDim) === 'inch') {
            $ct2uThickness = round($thickness / 25.4, 3);
            $ct2uWidth     = round($width / 25.4, 3);
            $ct2uLength    = round($length / 25.4, 3);
        } else {
            $ct2uThickness = $thickness;
            $ct2uWidth     = $width;
            $ct2uLength    = $length;
        }

        if (strtolower($ct2uUomWeight) === 'lbs.') {
            $orderWeight     = round($rawOrderWeight, 0);
            $ct2uOrderWeight = round($rawOrderWeight * 2.2, 0);
        } else {
            $orderWeight     = round($rawOrderWeight, 0);
            $ct2uOrderWeight = round($rawOrderWeight, 0);
        }

        // 4. เพิ่มขนาดใหม่เข้า Master (THCKMSTR1, DMWDMSTR1, LNGTMSTR1)
        $this->ensureDimensionMasters($thickness, $width, $length);
        $this->ensureDimensionMasters($ct2uThickness, $ct2uWidth, $ct2uLength);

        // 5. คำนวณน้ำหนักต่อชิ้นและจำนวนชิ้น
        $weightPiece = 0.0; $orderPiece = 0.0; $ct2uOrderPiece = 0.0;
        $jobReleaseWeight = $orderWeight; $jobReleasePiece = 0.0;

        if ($product === 'CO') {
            $jobMaxTol = 0; $jobMinTol = 0;
            $jobReleaseWeight = $rawOrderWeight;
            $jobReleasePiece  = ($coilMaxWeight > 0) ? ceil($jobReleaseWeight / $coilMaxWeight) : 1;
            $weightPiece      = ceil($jobReleaseWeight / $jobReleasePiece);
            $orderWeight      = $jobReleaseWeight;
            $orderPiece       = $jobReleasePiece;
            $ct2uOrderPiece   = $jobReleasePiece;
        } elseif ($product === 'CC') {
            $weightPiece = ($thickness > 0 && $width > 0) ? $this->calculateWeightPerPiece('CC', $width, 0, $thickness, $areaSize) : 0.0;
            $orderPiece  = ($weightPiece > 0) ? round($orderWeight / $weightPiece, 0) : 0;
            $ct2uOrderPiece = $orderPiece;
            $jobReleasePiece = $orderPiece;
        } elseif ($product === 'SH') {
            $weightPiece = ($thickness > 0 && $width > 0 && $length > 0) ? $this->calculateWeightPerPiece('SH', $width, $length, $thickness, $areaSize) : 0.0;
            $orderPiece  = ($weightPiece > 0) ? round($orderWeight / $weightPiece, 0) : 0;
            $ct2uOrderPiece = $orderPiece;
            $jobReleasePiece = $orderPiece;
        } elseif ($product === 'NC') {
            $weightPiece = ($thickness > 0 && $width > 0 && $length > 0 && $areaSize > 0) ? $this->calculateWeightPerPiece('NC', $width, $length, $thickness, $areaSize) : 0.0;
            $orderPiece  = ($weightPiece > 0) ? round($orderWeight / $weightPiece, 0) : 0;
            $ct2uOrderPiece = $orderPiece;
            $jobReleasePiece = $orderPiece;
        } elseif ($product === 'WR') {
            $orderPiece = ($orderWeight > 0) ? 1 : 0;
            $ct2uOrderPiece = $orderPiece;
            $jobReleasePiece = $orderPiece;
        }

        // 6. คำนวณขนาดเผื่อตัด (Cut Width / Cut Length) ตาม Work Process
        $checkProcess = '';
        foreach (['SH', 'SC', 'CT', 'CL', 'XC', 'AP', 'KP'] as $st) {
            if (strpos($workProcess, $st) !== false) {
                $checkProcess = $st;
                break;
            }
        }

        $cutWidth = 0.0; $cutLength = 0.0; $cutWeightPiece = 0.0;
        $wpPrefix = substr($workProcess, 0, 3);
        if ($wpPrefix === 'CL>' && $checkProcess !== '') {
            if ($checkProcess === 'SH') {
                $cutWidth = round($width + 20, 0);
                $cutLength = round($width + 20, 0);
                $cutWeightPiece = $this->calculateWeightPerPiece('SH', $cutWidth, $cutLength, $thickness, $areaSize);
            } elseif ($checkProcess === 'SC') {
                $cutWidth = round($width + 3, 0);
                $cutLength = round($length + 250, 0);
                $cutWeightPiece = $this->calculateWeightPerPiece('SH', $cutWidth, $cutLength, $thickness, $areaSize);
            } elseif ($checkProcess === 'CT' || $checkProcess === 'CL') {
                $cutWidth = round($width, 0);
                $cutLength = round($length, 0);
                $cutWeightPiece = $this->calculateWeightPerPiece('SH', $cutWidth, $cutLength, $thickness, $areaSize);
            }
            if ($product === 'SH' && $checkProcess === 'XC') {
                $cutWidth = round($width + 20, 0);
                $cutLength = round($length + 20, 0);
                $cutWeightPiece = $this->calculateWeightPerPiece('SH', $cutWidth, $cutLength, $thickness, $areaSize);
            }
        } elseif (in_array($wpPrefix, ['BK>', 'AP>', 'KP>', 'XC>']) && $checkProcess === 'SH') {
            $cutWidth = 505.0;
            $cutLength = 0.0;
            $cutWeightPiece = $this->calculateWeightPerPiece('CC', $cutWidth, $cutLength, $thickness, $areaSize);
        }

        // 7. สร้าง Description
        $sw = ($showScrapWire == 1) ? 'SW' : '';
        $dc = ($materialIn === 'BOI-IMP1') ? 'DC ALLOY ' : '';
        $tStr = number_format($ct2uThickness, 3, '.', '');
        $wStr = number_format($ct2uWidth, 3, '.', '');
        $lStr = number_format($ct2uLength, 3, '.', '');

        if ($product === 'CC') $description = "ALUMINIUM CIRCLES {$dc}{$sw}{$alloy}-{$temper} {$tStr} x {$wStr} {$ct2uUomDim}";
        elseif ($product === 'SH' || $product === 'NC') $description = "ALUMINIUM SHEETS {$dc}{$sw}{$alloy}-{$temper} {$tStr} x {$wStr} x {$lStr} {$ct2uUomDim}";
        elseif ($product === 'CO') $description = "ALUMINIUM COILS {$dc}{$sw}{$alloy}-{$temper} {$tStr} x {$wStr} {$ct2uUomDim}";
        else $description = "ALUMINIUM SHEETS {$dc}{$sw}{$alloy}";

        // 8. ดึง STDEV / FACTOR / STDXFACTOR จาก LME3MNTH1
        $stdev = 0.0; $factor = 0.0; $stdxFactor = 0.0;
        if ($lmeDate > '2000-01-01') {
            $stmtL = $this->pdo->prepare("SELECT STDEV, FACTOR, STDXFACTOR FROM LME3MNTH1 WHERE LME_DATE = ?");
            $stmtL->execute([$lmeDate]);
            if ($lRow = $stmtL->fetch(PDO::FETCH_ASSOC)) {
                $stdev      = (float)($lRow['STDEV'] ?? 0);
                $factor     = (float)($lRow['FACTOR'] ?? 0);
                $stdxFactor = (float)($lRow['STDXFACTOR'] ?? 0);
            }
        }

        // 9. จัดรูปแบบ Job Remark (3 ท่อน ท่อนละ 65 ตัวอักษร ตาม edi2.txt)
        $parts = array_map('trim', explode(',', $jobRemarkRaw));
        $r1 = str_pad(mb_substr($parts[0] ?? '', 0, 65), 65);
        $r2 = str_pad(mb_substr($parts[1] ?? '', 0, 65), 65);
        $r3 = str_pad(mb_substr($parts[2] ?? '', 0, 65), 65);
        $formattedJobRemark = substr($r1 . $r2 . $r3, 0, 255);

        // 10. สะสมยอดเงินรวมต่อ PO
        $ctm2Amount = round($ct2uOrderWeight * $actualPrice, 2);
        if ($totalPONo !== $poNo) {
            if ($totalPONo !== '') {
                $this->pdo->prepare("UPDATE CSTMORDR1 SET CTM1_TOTALAMOUNT = ? WHERE SALEORDER_NO = ?")
                          ->execute([round($totalAmount, 2), $totalSaleOrderNo]);
            }
            $totalSaleOrderNo = $saleOrderNo;
            $totalPONo = $poNo;
            $totalAmount = $ctm2Amount;
        } else {
            $totalAmount += $ctm2Amount;
        }

        // 11. INSERT JOBORDER1
        $insJob = $this->pdo->prepare("INSERT INTO JOBORDER1 (
            JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, JOBORDER_DATE, SCHEDULE_DATE,
            JOB_PRIORITY, OTHER_COILWIDTH, SUGGEST_WIDTH, RECOVERY, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE,
            ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2,
            SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, FLATNESS_GRADE, THICKNESS, WIDTH,
            LENGTH, JOB_UOMDIMENSION, WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, ORDER_BY,
            ORDER_STARTDATE, CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_WORKPROCESS, FIXED_PROCESS,
            JOB_MAXTOLERANCE, JOB_MINTOLERANCE, JOB_RELEASEWEIGHT, JOB_RELEASEPIECE, JOB_UOMWEIGHT,
            JOB_REMARK, JOB_LABEL, JOB_OPERATOR, JOB_OPERATEDATE, JOB_STATUS
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 1, 'N', 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mm.',
            ?, ?, ?, ?, ?, ?, 0, 0, ?, 'NO', ?, ?, ?, ?, 'kg.', ?, 0, ?, GETDATE(), 'OP'
        )");
        $insJob->execute([
            $jobOrderNo, $saleOrderNo, $saleOrderItem, $materialIn, $todayStr, $scheduleDate,
            $product, $productModel, round($areaSize, 2), $alloy, $temper, $prodTemper1, $prodTemper2,
            $grade, $prodGrade1, $prodGrade2, $sg, $mg, $t5Temp, $flatnessGrade,
            $thickness, $width, $length, $weightPiece, $cutWidth, $cutLength, $cutWeightPiece,
            $orderBy, $scheduleDate, $workProcess, $jobMaxTol, $jobMinTol,
            round($jobReleaseWeight, 2), round($jobReleasePiece, 0), $formattedJobRemark, $userOperate
        ]);

        // 12. INSERT CSTMORDR2
        $insSo2 = $this->pdo->prepare("INSERT INTO CSTMORDR2 (
            SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, ORDER_CATEGORY, MATERIAL_IN, CTM2_PONO,
            CTM2_POITEM, CTM2_PODATE, CTM2_CUSTOMERORDER, CTM2_CUSTOMERSALEORDER, CTM2_ENDCUSTOMERNAME, CTM2_ENDCUSTOMERPO, PRICE_PERIOD,
            COIL_FROM, FULL_COIL, SUGGEST_WIDTH, RECOVERY, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE, SUBCONTACT,
            ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH,
            CTM2_UOMDIMENSION, CT2U_THICKNESS, CT2U_WIDTH, CT2U_LENGTH, CT2U_UOMDIMENSION, WEIGHT_PIECE,
            REQUEST_DATE, CONFIRM_DATE, CTM2_CUSTOMERPARTNO, CTM2_DESCRIPTION, CTM2_PROVISIONALPRICE,
            CTM2_ACTUALPRICE, CTM2_LMETYPE, CTM2_ORDERLME, CTM2_LMEDATE, CTM2_MJPDATE, CTM2_MJPPRICE, CTM2_ORDERSTDV, CTM2_ORDERFACTOR, CTM2_ORDERSTDXFACTOR,
            ORDER_BY, ORDER_STARTDATE, CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE, CTM2_ORDERWEIGHT, CTM2_ORDERPIECE,
            CTM2_REJECTWEIGHT, CTM2_REJECTPIECE, CTM2_REPLACEWEIGHT, CTM2_REPLACEPIECE, CTM2_UOMWEIGHT,
            CT2U_ORDERWEIGHT, CT2U_ORDERPIECE, CT2U_UOMWEIGHT, CTM2_WHOLEWEIGHT, CTM2_MARKSKID, CTM2_SERIALCODE,
            CTM2_SCRAPWIRE, CTM2_SPECIALLABEL1, CTM2_SPECIALLABEL2, CTM2_SPECIALLABEL3, CTM2_SPECIALLABEL4,
            CTM2_REMARK, CTM2_OPERATOR, CTM2_OPERATEDATE, CTM2_STATUS
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, 'F', 0, 0, ?, ?, ?, 'N',
            ?, ?, ?, ?, ?, ?, ?, ?, 'mm.', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 'kg.',
            ?, ?, ?, 0, 0, 0, 0, '', '', '', '', '', ?, GETDATE(), 'OP'
        )");
        $insSo2->execute([
            $saleOrderNo, $saleOrderItem, $customer, $orderCategory, $materialIn, $poNo,
            $poItem, $todayStr, $custOrder, $custSaleOrder, $endCustName, $endCustPo,
            $coilFrom, $product, $productModel, $areaSize, $alloy, $temper, $sg, $mg, $t5Temp,
            $thickness, $width, $length, $ct2uThickness, $ct2uWidth, $ct2uLength, $ct2uUomDim, $weightPiece,
            $requestDate, $scheduleDate, $custPartNo, $description, $actualPrice,
            $actualPrice, $lmeType, $orderLme, $lmeDate, $mjpDate, $orderMjp, $stdev, $factor, $stdxFactor,
            $orderBy, $scheduleDate, $jobMaxTol, $jobMinTol, $orderWeight, $orderPiece,
            $ct2uOrderWeight, $ct2uOrderPiece, $ct2uUomWeight, $userOperate
        ]);

        // 13. INSERT CSTMORDR1 (เฉพาะแถวแรกของ PO_NO)
        $chkSo1 = $this->pdo->prepare("SELECT 1 FROM CSTMORDR1 WHERE SALEORDER_NO = ?");
        $chkSo1->execute([$saleOrderNo]);
        if (!$chkSo1->fetchColumn()) {
            $insSo1 = $this->pdo->prepare("INSERT INTO CSTMORDR1 (
                SALEORDER_NO, CSTMSPPL_ID, ORDER_CATEGORY, SALEORDER_DATE, INTENDSHIP_DATE, CTM1_PONO, SUPPLIER_MATCODE, CUSTOMS_CODE, CTM1_CUSTOMERORDER, CTM1_CUSTOMERSALEORDER,
                CURRENCY_ID, EXCHANGE_RATE, PAYMENT_ID, SHIPMENT_ID, CTM1_PACKING, CTM1_SHIPPEDBY, CTM1_PORTLOADING, CTM1_PORTDISCHARGE, CTM1_PLACEDESTINATION, CTM1_COUNTRYDESTINATION,
                CTM1_TOLERANCE, CTM1_TAXPERCENT, CTM1_TRANSPORTATION, CTM1_OVERSEAFREIGHT, CTM1_INSURANCE, CTM1_TOTALAMOUNT, CTM1_FOBTOTALAMOUNT, CTM1_GRANDTOTALAMOUNT, CTM1_WHOLEWEIGHT,
                CTM1_MARKSKID, CTM1_SERIALCODE, CTM1_SCRAPWIRE, CTM1_LMETYPE, CTM1_LMEDATE, CTM1_ORDERLME, CTM1_MJPDATE, CTM1_MJPPRICE, CTM1_ORDERSTDV, CTM1_ORDERFACTOR, CTM1_ORDERSTDXFACTOR,
                CTM1_REMARK1, CTM1_REMARK2, CTM1_REMARK3, CTM1_OPERATOR1, CTM1_OPERATEDATE
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', ?, GETDATE()
            )");
            $insSo1->execute([
                $saleOrderNo, $customer, $orderCategory, $todayStr, $requestDate, $poNo, $supplierMatCode, $custPartNo, $custOrder, $custSaleOrder,
                $currency, $exchangeRate, $paymentId, $shipmentId, $portLoading, $portDischarge, $placeDestination, $countryDestination,
                (string)$jobMaxTol, $taxPercent, $totalAmount, $showScrapWire,
                $lmeType, $lmeDate, $orderLme, $mjpDate, $orderMjp, $stdev, $factor, $stdxFactor, $userOperate
            ]);
        }

        // 14. ปิดสถานะ EDIORDER1 -> CL
        $this->pdo->prepare("UPDATE EDIORDER1 SET PO_ITEM = ?, SALEORDER_NO = ?, SALEORDER_ITEM = ?, JOB_ORDER = ?, EDI_STATUS = 'CL' WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?")
                  ->execute([$poItem, $saleOrderNo, $saleOrderItem, $jobOrderNo, $customer, $poNo, $row['PO_ITEM']]);

        return [
            'customer'       => $customer,
            'po_no'          => $poNo,
            'po_item'        => $poItem,
            'saleorder_no'   => $saleOrderNo,
            'saleorder_item' => $saleOrderItem,
            'job_order'      => $jobOrderNo,
            'product'        => $product,
            'alloy'          => $alloy,
            'order_weight'   => $orderWeight,
            'order_piece'    => $orderPiece,
            'work_process'   => $workProcess
        ];
    }

    /**
     * ประมวลผลสร้าง SO / Job สำหรับ 1 แถวของ AUTO_EDI (CSV ลูกค้า MILO เดิม)
     */
    private function processSingleAutoEdiRow(
        array $row, string $userOperate,
        string &$totalPONo, string &$totalSaleOrderNo, string &$saleOrderNo, float &$totalAmount
    ): array {
        $customer       = trim($row['CSTMSPPL_ID'] ?? '');
        $poNo           = trim($row['PO_NO'] ?? '');
        $poItem         = $this->formatItem3Digits($row['PO_ITEM'] ?? '');
        $saleOrderItem  = $poItem;
        $orderCategory  = trim($row['ORDER_CATEGORY'] ?? 'ORDR');
        $buyerId        = trim($row['BUYER_ID'] ?? '');
        $endCustName    = trim($row['CTM2_ENDCUSTOMERNAME'] ?? '');
        $endCustPo      = trim($row['CTM2_ENDCUSTOMERPO'] ?? '');
        $custPartNo     = trim($row['CUSTOMER_PARTNO'] ?? '');
        $product        = strtoupper(trim($row['PRODUCT_ID'] ?? ''));
        $productModel   = trim($row['PRODUCT_MODEL'] ?? '');
        $loadAreaSize   = (float)($row['AREA_SIZE'] ?? 0);
        $alloy          = trim($row['ALLOY'] ?? '');
        $temper1        = trim($row['TEMPER'] ?? '');
        $sg             = trim($row['SURFACE_GRADE'] ?? '');
        $mg             = trim($row['METALLURGICAL_GRADE'] ?? '');
        $t5Temp         = trim($row['T5_TEMPERATURE'] ?? '');
        $thickness      = round((float)($row['THICKNESS'] ?? 0), 3);
        $width          = round((float)($row['WIDTH'] ?? 0), 3);
        $length         = round((float)($row['LENGTH'] ?? 0), 3);
        $uomDim         = trim($row['UOM_DIMENSION'] ?? 'mm.');
        $uomWeight      = trim($row['UOM_WEIGHT'] ?? 'kg.');
        $loadDate       = substr((string)$row['UPLOAD_DATE'], 0, 10);
        $etdDate        = substr((string)$row['ETD_DATE'], 0, 10);
        $currency       = trim($row['CURRENCY_ID'] ?? 'BAHT');
        $actualPrice    = round((float)($row['UNIT_PRICE'] ?? 0), 4);
        $exchangeRate   = (float)($row['EXCHANGE_RATE'] ?? 1);
        $orderLme       = (float)($row['LME'] ?? 0);
        $lmeDate        = !empty($row['LME_DATE']) ? substr((string)$row['LME_DATE'], 0, 10) : '2000-01-01';
        $rawOrderWeight = (float)($row['ORDER_WEIGHT'] ?? 0);
        $maxTol         = (int)($row['MAX_TOLERANCE'] ?? 0);
        $minTol         = (int)($row['MIN_TOLERANCE'] ?? 0);
        $orderRemark    = trim($row['ORDER_REMARK'] ?? '');
        $scrapWire      = trim($row['SCRAP_WIRE'] ?? 'N');
        $coilFrom       = trim($row['COIL_FROM'] ?? 'MAT');
        $csvWorkProcess = trim($row['WORK_PROCESS'] ?? '');
        $jobRemark      = '';

        if (strtolower($uomDim) === 'inch') {
            $ctm2Thickness = round($thickness * 25.4, 3);
            $ctm2Width     = round($width * 25.4, 3);
            $ctm2Length    = round($length * 25.4, 3);
        } else {
            $ctm2Thickness = $thickness; $ctm2Width = $width; $ctm2Length = $length;
        }

        if (strtolower($uomWeight) === 'lbs.') {
            $orderWeight = round($rawOrderWeight / 2.2, 0);
            $ct2uOrderWeight = round($orderWeight * 2.2, 0);
        } else {
            $orderWeight = round($rawOrderWeight, 0);
            $ct2uOrderWeight = $orderWeight;
        }

        $materialIn = ($coilFrom === 'MAT') ? (($scrapWire === 'Y') ? 'BOI-GRS1' : 'BOI-MAT1') : 'BOI-IMP1';
        $dimDesc = number_format($thickness, 3, '.', '') . ' x ' . number_format($width, 3, '.', '');
        if ($product === 'SH' || $product === 'NC') $dimDesc .= ' x ' . number_format($length, 3, '.', '');
        $dimDesc .= ' ' . $uomDim;
        $dcPrefix = ($materialIn === 'BOI-IMP1') ? 'DC ALLOY ' : '';
        $prodWord = ($product === 'CO') ? 'ALUMINIUM COILS ' : (($product === 'CC') ? 'ALUMINIUM CIRCLES ' : 'ALUMINIUM SHEETS ');
        $description = $prodWord . $dcPrefix . $alloy . '-' . $temper1 . ' ' . $dimDesc;

        if ($totalPONo !== $poNo) {
            $soPrefix = (substr($buyerId, 2, 1) === '9' || substr($buyerId, -1) === '9') ? 'SL-' : 'SO-';
            $saleOrderNo = $this->getNextSaleOrderNo($soPrefix);
        }
        $jobOrder = $this->getNextJobOrderNo('JB-');

        $orderFrom = (substr($customer, 0, 3) === 'MIL') ? 'MILO' : ((substr($customer, 0, 3) === 'MAB') ? 'MABO' : 'OTHER');
        $pricePeriod = ''; $grade1 = 'NONE'; $grade2 = 'NONE'; $grade3 = 'NONE'; $fullCoil = 'F';
        $workProcess = $csvWorkProcess; $cutWidth = 0.0; $cutLength = 0.0;

        if ($orderFrom === 'MILO') {
            if ($product === 'CC' || $product === 'NC') {
                if ($product === 'CC') $length = 0.0;
                if ($customer === 'MIL-AP') {
                    if ($alloy === '8128') {
                        if ($thickness >= 4 || $thickness < 2) $workProcess = 'BK>BA>TW>IS';
                        elseif ($thickness == 2.5) $workProcess = ($product === 'NC' || $width == 160) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                        else $workProcess = ($width >= 430 || $width <= 200) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                    } elseif ($alloy === '8011') {
                        if ($thickness == 2.5) $workProcess = ($product === 'NC' || $width >= 430 || $width == 160) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                        else $workProcess = ($width >= 430) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                    } else $workProcess = 'BK>IS';

                    if (substr($custPartNo, -1) === 'F') {
                        $jobRemark = 'FOR FRY PAN AND SKILLET USE FOR BRUSHING PROCESS';
                        $grade1 = ($alloy === '1100') ? 'A' : 'RFA';
                    } else {
                        $grade1 = ($alloy === '1100') ? 'A' : 'RAA';
                    }
                } elseif ($customer === 'MIL-SS') {
                    $workProcess = 'BK>IS';
                    $grade1 = ($alloy === '1002') ? 'A' : 'RAA';
                    $grade2 = ($alloy === '1002') ? 'A' : 'RAA';
                } elseif ($customer === 'MIL-CE') {
                    if ($alloy === '8128') $workProcess = ($thickness >= 4 || $thickness < 2 || $width >= 430 || $width <= 200) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                    elseif ($alloy === '8011') $workProcess = ($width >= 430) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                    else $workProcess = 'BK>IS';

                    if ($coilFrom === 'MAL') { $grade1 = 'AM'; $grade2 = 'AN'; } else { $grade1 = 'RCE'; }
                    if ($thickness == 2 && in_array($width, [120, 160])) $grade1 = 'A';
                    elseif ($thickness == 2.5 && $width == 460) $jobRemark = 'FOR CE ORDER SUA MODEL';
                    elseif ($thickness == 3 && in_array($width, [270, 310, 350, 390, 395])) $jobRemark = 'FOR CE ORDER SUA MODEL';
                } elseif ($customer === 'MIL-HA') {
                    if ($alloy === '8128') {
                        $workProcess = ($thickness >= 4 || $thickness < 2 || $width >= 430 || $width <= 200) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                        $grade1 = 'RHA'; $grade2 = 'RAA';
                    } elseif ($alloy === '8011') {
                        $workProcess = ($width >= 430) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                        $grade1 = 'RHA'; $grade2 = 'RAA';
                    } elseif ($alloy === '1002') {
                        $workProcess = 'BK>IS'; $grade1 = 'A'; $grade2 = 'A';
                    } elseif ($alloy === '3003') {
                        $workProcess = ($thickness > 4 || $width >= 430) ? 'BK>BA>TW>IS' : 'BK>AN>IS';
                        if ($coilFrom === 'MAL') { $grade1 = 'AM'; $grade2 = 'AN'; } else { $grade1 = 'RHA'; $grade2 = 'RAA'; }
                    } else {
                        $workProcess = 'BK>IS'; $grade1 = 'RHA'; $grade2 = 'RAA';
                    }
                }
                $fullCoil = ($orderWeight % 1200 === 0) ? 'F' : 'N';
            } elseif ($product === 'SH') {
                $workProcess = 'CL>IS'; $cutWidth = $width; $cutLength = $length;
                if ($customer === 'MIL-AP') { $grade1 = 'RAA'; $grade2 = 'RHA'; }
                elseif ($customer === 'MIL-SS') { $grade1 = 'RAA'; $grade2 = 'RAA'; }
                elseif ($customer === 'MIL-CE') { $grade1 = 'RAA'; $grade2 = 'RHA'; $grade3 = 'RCE'; }
                elseif ($customer === 'MIL-HA') { $grade1 = 'RHA'; $grade2 = 'RAA'; }
            }

            $stmtP = $this->pdo->prepare("SELECT TOP 1 PRICE_PERIOD FROM PRICMSTR1 WHERE COIL_FROM = ? AND CSTMSPPL_ID = ? AND LIFETIME_FROM <= ? AND LIFETIME_TO >= ?");
            $stmtP->execute([$coilFrom, $customer, $loadDate, $loadDate]);
            $pRow = $stmtP->fetch(PDO::FETCH_ASSOC);
            $pricePeriod = $pRow['PRICE_PERIOD'] ?? '';

            $calcRes = $this->calculatePriceFromMaster(
                $pricePeriod, $coilFrom, $customer, $saleOrderNo,
                $poNo . '-' . $buyerId, $product, $alloy, $temper1,
                $sg, $mg, $thickness, $width, $length,
                $orderWeight, $minTol, $fullCoil
            );
            if ($calcRes['found']) {
                $exchangeRate = $calcRes['exchange_rate'];
                $orderLme     = $calcRes['lme_average'];
            }
        } else {
            $grade1 = !empty($row['GRACE']) ? trim($row['GRACE']) : 'RA';
            $grade2 = 'RAA'; $grade3 = 'RCE';
            if ($product === 'CC' || $product === 'NC') {
                if ($ctm2Width >= 500) {
                    $workProcess = 'CL>CT>SH>PH>BA>TW>IS';
                    $cutWidth = $ctm2Width + 20; $cutLength = $ctm2Width + 20;
                } else {
                    $workProcess = ($orderRemark === 'Center Hole') ? 'BK>PH>BA>TW>IS' : 'BK>BA>TW>IS';
                }
                $length = 0.0; $ctm2Length = 0.0;
            } elseif ($product === 'SH') {
                if ($workProcess === '') $workProcess = 'CL>SC>BA>TW>IS';
                $cutWidth = $ctm2Width + 3; $cutLength = $ctm2Length + 250;
            } elseif ($product === 'CO') {
                if ($workProcess === '') $workProcess = 'PK>ST';
            }
        }

        if ($product === 'CC' || $product === 'NC') {
            $cutWeightPiece = ($cutWidth == 0 && $cutLength == 0) ? 0.0 : $this->calculateWeightPerPiece($product, $cutWidth, $cutLength, $ctm2Thickness, $loadAreaSize);
            $weightPiece    = $this->calculateWeightPerPiece($product, $ctm2Width, $ctm2Length, $ctm2Thickness, $loadAreaSize);
        } elseif ($product === 'SH') {
            $cutWeightPiece = $this->calculateWeightPerPiece($product, $cutWidth, $cutLength, $thickness, $loadAreaSize);
            $weightPiece    = $this->calculateWeightPerPiece($product, $width, $length, $thickness, $loadAreaSize);
        } else {
            $cutWeightPiece = 0.0; $weightPiece = 3500.0;
        }

        $orderPiece = ($weightPiece > 0) ? round($orderWeight / $weightPiece, 0) : 0;
        $maxJobPiece = $orderPiece + (($orderPiece * $maxTol) / 100.0);

        $stmtC = $this->pdo->prepare("SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = ?");
        $stmtC->execute([$customer]);
        $custInfo = $stmtC->fetch(PDO::FETCH_ASSOC) ?: [];

        $supplierMatCode    = trim($custInfo['SUPPLIER_MATCODE'] ?? '');
        $customsCode        = trim($custInfo['CUSTOMS_CODE'] ?? '');
        $paymentId          = trim($custInfo['PAYMENT_ID'] ?? '00');
        $shipmentId         = trim($custInfo['SHIPMENT_ID'] ?? '000');
        $portLoading        = trim($custInfo['CSSP_PORTLOADING'] ?? '000');
        $portDischarge      = trim($custInfo['CSSP_PORTDISCHARGE'] ?? '000');
        $placeDestination   = trim($custInfo['CSSP_PLACEDESTINATION'] ?? '000');
        $countryDestination = trim($custInfo['CSSP_COUNTRYDESTINATION'] ?? '000');
        $overseaFreight     = (float)($custInfo['CSSP_TAXPERCENT'] ?? 0);
        $insurance          = (float)($custInfo['CSSP_INSURANCE'] ?? 0);
        $taxPercent         = (int)($custInfo['CSSP_TAXPERCENT'] ?? 0);
        $serialCode         = (int)($custInfo['SERIAL_CODE'] ?? 0);

        if ($supplierMatCode !== '') {
            $jobRemark = ($jobRemark !== '') ? ($jobRemark . ', ' . $supplierMatCode) : $supplierMatCode;
        }

        $suggestWidth = 0.0; $recovery = 0.0;
        if ($coilFrom === 'MAL') {
            $stmtS = $this->pdo->prepare("SELECT COIL_WIDTH, RECOVERY FROM STNDMSTR12 WHERE DIAMETER = ?");
            $stmtS->execute([$width]);
            if ($sRow = $stmtS->fetch(PDO::FETCH_ASSOC)) {
                $suggestWidth = round((float)$sRow['COIL_WIDTH'], 0);
                $recovery     = round((float)$sRow['RECOVERY'], 2);
                $jobRemark    = ($jobRemark !== '') ? ($jobRemark . ', Use coil width: ' . $sRow['COIL_WIDTH']) : ('Use coil width: ' . $sRow['COIL_WIDTH']);
            }
        }

        if ($totalPONo !== $poNo) {
            if ($totalPONo !== '') {
                $this->pdo->prepare("UPDATE CSTMORDR1 SET CTM1_TOTALAMOUNT = ? WHERE SALEORDER_NO = ?")
                          ->execute([round($totalAmount, 2), $totalSaleOrderNo]);
            }
            $totalSaleOrderNo = $saleOrderNo;
            $totalPONo = $poNo;
            $totalAmount = $orderWeight * $actualPrice;
        } else {
            $totalAmount += ($orderWeight * $actualPrice);
        }

        $chkSo1 = $this->pdo->prepare("SELECT 1 FROM CSTMORDR1 WHERE SALEORDER_NO = ?");
        $chkSo1->execute([$saleOrderNo]);
        if (!$chkSo1->fetchColumn()) {
            $this->pdo->prepare("INSERT INTO CSTMORDR1 (
                SALEORDER_NO, ORDER_CATEGORY, SALEORDER_DATE, INTENDSHIP_DATE, CSTMSPPL_ID,
                CURRENCY_ID, EXCHANGE_RATE, CTM1_PONO, SUPPLIER_MATCODE, CUSTOMS_CODE, PAYMENT_ID,
                SHIPMENT_ID, CTM1_PORTLOADING, CTM1_PORTDISCHARGE, CTM1_PLACEDESTINATION,
                CTM1_COUNTRYDESTINATION, CTM1_TAXPERCENT, CTM1_TRANSPORTATION, CTM1_OVERSEAFREIGHT,
                CTM1_INSURANCE, CTM1_TOTALAMOUNT, CTM1_SERIALCODE, CTM1_WHOLEWEIGHT,
                CTM1_MARKSKID, CTM1_SCRAPWIRE, CTM1_OPERATOR1, CTM1_OPERATEDATE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0, 0, 0, ?, GETDATE())")
            ->execute([
                $saleOrderNo, $orderCategory, $loadDate, $etdDate, $customer,
                $currency, $exchangeRate, $poNo, $supplierMatCode, $customsCode, $paymentId,
                $shipmentId, $portLoading, $portDischarge, $placeDestination,
                $countryDestination, $taxPercent, $overseaFreight,
                $insurance, $totalAmount, $serialCode, $userOperate
            ]);
        }

        $ctm2PoNo = ($orderFrom === 'MILO') ? ($poNo . '-' . $buyerId) : $poNo;
        $this->pdo->prepare("INSERT INTO CSTMORDR2 (
            SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID, ORDER_CATEGORY, MATERIAL_IN, CTM2_PONO,
            CTM2_POITEM, CTM2_PODATE, CTM2_ENDCUSTOMERNAME, CTM2_ENDCUSTOMERPO, PRICE_PERIOD, COIL_FROM,
            FULL_COIL, SUGGEST_WIDTH, RECOVERY, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE, SUBCONTACT, ALLOY,
            TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH,
            CTM2_UOMDIMENSION, CT2U_THICKNESS, CT2U_WIDTH, CT2U_LENGTH, CT2U_UOMDIMENSION, WEIGHT_PIECE,
            REQUEST_DATE, CONFIRM_DATE, CTM2_CUSTOMERPARTNO, CTM2_DESCRIPTION,
            CTM2_ACTUALPRICE, CTM2_LMEDATE, CTM2_ORDERLME, CTM2_MAXTOLERANCE, CTM2_MINTOLERANCE,
            CTM2_ORDERWEIGHT, CTM2_ORDERPIECE, CTM2_RELEASEWEIGHT, CTM2_RELEASEPIECE, CTM2_UOMWEIGHT,
            CT2U_ORDERWEIGHT, CT2U_ORDERPIECE, CT2U_UOMWEIGHT, CTM2_WORKPROCESS, CTM2_SCRAPWIRE, CTM2_SERIALCODE,
            CTM2_WHOLEWEIGHT, CTM2_MARKSKID, CTM2_REMARK, CTM2_OPERATOR, CTM2_OPERATEDATE, CTM2_STATUS
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'N', ?,
            ?, ?, ?, ?, ?, ?, ?, 'mm.', ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'kg.', ?, ?, ?, ?, 0, 0,
            0, 0, ?, ?, GETDATE(), 'OP'
        )")->execute([
            $saleOrderNo, $saleOrderItem, $customer, $orderCategory, $materialIn, $ctm2PoNo,
            $poItem, $loadDate, $endCustName, $endCustPo, $pricePeriod, $coilFrom,
            $fullCoil, $suggestWidth, $recovery, $product, $productModel, round($loadAreaSize, 4), $alloy,
            $temper1, $sg, $mg, $t5Temp, $ctm2Thickness, $ctm2Width, $ctm2Length,
            $thickness, $width, $length, $uomDim, $weightPiece,
            $etdDate, $etdDate, $custPartNo, $description,
            $actualPrice, $lmeDate, $orderLme, $maxTol, $minTol,
            $orderWeight, $orderPiece, $orderWeight, $orderPiece,
            $ct2uOrderWeight, $orderPiece, $uomWeight, $workProcess,
            $orderRemark, $userOperate
        ]);

        $this->pdo->prepare("INSERT INTO JOBORDER1 (
            JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, JOBORDER_DATE, SCHEDULE_DATE,
            JOB_PRIORITY, OTHER_COILWIDTH, SUGGEST_WIDTH, RECOVERY, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE,
            ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2,
            SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, JOB_UOMDIMENSION,
            WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, JOB_WORKPROCESS,
            JOB_MAXTOLERANCE, JOB_MINTOLERANCE, JOB_RELEASEWEIGHT, JOB_RELEASEPIECE, JOB_UOMWEIGHT,
            JOB_REMARK, JOB_OPERATOR, JOB_OPERATEDATE, JOB_STATUS
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 1, 'N', ?, ?, ?, ?, ?,
            ?, ?, 'MO', 'H18', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mm.',
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'kg.',
            ?, ?, GETDATE(), 'OP'
        )")->execute([
            $jobOrder, $saleOrderNo, $saleOrderItem, $materialIn, $loadDate, $etdDate,
            $suggestWidth, $recovery, $product, $productModel, $loadAreaSize,
            $alloy, $temper1, $grade1, $grade2, $grade3,
            $sg, $mg, $t5Temp, $thickness, $width, $length,
            $weightPiece, $cutWidth, $cutLength, $cutWeightPiece, $workProcess,
            $maxTol, $minTol, $orderWeight, $orderPiece,
            substr($jobRemark, 0, 255), $userOperate
        ]);

        if ($product !== 'CO' && $orderWeight > self::PB_MIN_WT) {
            if (!($ctm2Width <= self::PB_BP_WIDTH && substr($customer, 0, 3) === 'MIL' && $alloy === '1002')) {
                $this->reservePackagingForJob($jobOrder, $customer, $product, $thickness, $width, $length, $weightPiece, $maxJobPiece);
            }
        }

        $this->pdo->prepare("UPDATE EDIORDER1 SET PO_ITEM = ?, SALEORDER_NO = ?, SALEORDER_ITEM = ?, JOB_ORDER = ?, EDI_STATUS = 'CL' WHERE CSTMSPPL_ID = ? AND PO_NO = ? AND PO_ITEM = ?")
                  ->execute([$poItem, $saleOrderNo, $saleOrderItem, $jobOrder, $customer, $poNo, $row['PO_ITEM']]);

        return [
            'customer' => $customer, 'po_no' => $poNo, 'po_item' => $poItem,
            'saleorder_no' => $saleOrderNo, 'saleorder_item' => $saleOrderItem, 'job_order' => $jobOrder,
            'product' => $product, 'alloy' => $alloy, 'order_weight' => $orderWeight,
            'order_piece' => $orderPiece, 'work_process' => $workProcess
        ];
    }

    private function existsInMaster(string $sql, array $params): bool {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
}