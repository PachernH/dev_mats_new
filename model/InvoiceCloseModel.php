<?php
// model/InvoiceCloseModel.php

class InvoiceCloseModel
{
    private $conn;

    public function __construct($pdoConnection)
    {
        $this->conn = $pdoConnection;
    }

    /**
     * รายการตัวเลือกมาตรฐานสำหรับ Dropdown (อ้างอิงจาก Form_Load ใน CIV)
     */
    public function getDropdownOptions()
    {
        return [
            'status' => [
                ['value' => 'OP', 'label' => 'OP: OPEN'],
                ['value' => 'CP', 'label' => 'CP: COMPLETE']
            ],
            't3' => ['B#1', 'B#2', 'NON'],
            't6' => [
                '', 'AM1', 'AM2', 'AT1', 'CSN', 'EW1', 'MAB', 'MIL', 'MSB',
                'MTM', 'MZQ', 'NA1', 'NI1', 'OH1', 'OLS', 'OOS', 'SB1',
                'SWA', 'TA1', 'TI1', 'TI2', 'TS1', 'UL1', 'WS1', 'NON'
            ]
        ];
    }

    /**
     * ดึงรายการ Invoice สำหรับหน้าหลัก (invoice_close_list_mats) และหน้าต่าง Lookup (อ้างอิงรูป 1)
     */
    public function getInvoiceList($filters = [], $limit = 300)
    {
        $limit = (int)$limit > 0 ? (int)$limit : 300;
        $params = [];
        $where = ["1=1"];

        $keyword   = isset($filters['keyword']) ? strtoupper(trim($filters['keyword'])) : '';
        $dateFrom  = isset($filters['date_from']) ? trim($filters['date_from']) : '';
        $dateTo    = isset($filters['date_to']) ? trim($filters['date_to']) : '';
        $status    = isset($filters['status']) ? strtoupper(trim($filters['status'])) : '';

        if ($keyword !== '') {
            $where[] = "(h.INVOICE_NO LIKE :kw1 OR h.CSTMSPPL_ID LIKE :kw2 OR h.BOOK_NO LIKE :kw3 OR c.CONSIGNEE_COMPANY LIKE :kw4)";
            $kwVal = '%' . $keyword . '%';
            $params[':kw1'] = $kwVal;
            $params[':kw2'] = $kwVal;
            $params[':kw3'] = $kwVal;
            $params[':kw4'] = $kwVal;
        }

        if ($dateFrom !== '') {
            $where[] = "CONVERT(VARCHAR(10), h.INVOICE_DATE, 120) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[] = "CONVERT(VARCHAR(10), h.INVOICE_DATE, 120) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($status !== '' && in_array($status, ['OP', 'CP'], true)) {
            $where[] = "ISNULL(h.INV1_STATUS, 'OP') = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT TOP {$limit}
                    h.INVOICE_NO,
                    h.INVOICE_DATE,
                    h.SHIPMENT_DATE,
                    h.BOOK_NO,
                    h.CSTMSPPL_ID,
                    c.CONSIGNEE_COMPANY,
                    h.PAYMENT_ID,
                    h.SHIPMENT_ID,
                    h.CURRENCY_ID,
                    h.INV1_MATERIALIN,
                    ISNULL(h.INV1_PACKAGE, 0) AS INV1_PACKAGE,
                    ISNULL(h.INV1_NETWEIGHT, 0) AS INV1_NETWEIGHT,
                    ISNULL(h.INV1_GROSSWEIGHT, 0) AS INV1_GROSSWEIGHT,
                    ISNULL(h.INV1_TOTALAMOUNT, 0) AS INV1_TOTALAMOUNT,
                    ISNULL(h.INV1_EXCHANGERATE, 0) AS INV1_EXCHANGERATE,
                    ISNULL(h.T3, 'B#1') AS T3,
                    ISNULL(h.T6, '') AS T6,
                    ISNULL(h.INV1_STATUS, 'OP') AS INV1_STATUS
                FROM INVCPRCS1 h
                LEFT JOIN CSSPMSTR1 c ON h.CSTMSPPL_ID = c.CSTMSPPL_ID
                WHERE {$whereSql}
                ORDER BY h.INVOICE_DATE DESC, h.INVOICE_NO DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'INVOICE_NO'         => trim($row['INVOICE_NO'] ?? ''),
                'INVOICE_DATE'       => !empty($row['INVOICE_DATE']) ? date('Y-m-d', strtotime($row['INVOICE_DATE'])) : '',
                'INVOICE_DATE_FMT'   => !empty($row['INVOICE_DATE']) ? date('d/m/Y', strtotime($row['INVOICE_DATE'])) : '-',
                'SHIPMENT_DATE'      => !empty($row['SHIPMENT_DATE']) ? date('Y-m-d', strtotime($row['SHIPMENT_DATE'])) : '',
                'SHIPMENT_DATE_FMT'  => !empty($row['SHIPMENT_DATE']) ? date('d/m/Y', strtotime($row['SHIPMENT_DATE'])) : '-',
                'BOOK_NO'            => trim($row['BOOK_NO'] ?? ''),
                'CSTMSPPL_ID'        => trim($row['CSTMSPPL_ID'] ?? ''),
                'CONSIGNEE_COMPANY'  => trim($row['CONSIGNEE_COMPANY'] ?? ''),
                'PAYMENT_ID'         => trim($row['PAYMENT_ID'] ?? ''),
                'SHIPMENT_ID'        => trim($row['SHIPMENT_ID'] ?? ''),
                'CURRENCY_ID'        => trim($row['CURRENCY_ID'] ?? ''),
                'INV1_MATERIALIN'    => trim($row['INV1_MATERIALIN'] ?? ''),
                'INV1_PACKAGE'       => (int)($row['INV1_PACKAGE'] ?? 0),
                'INV1_NETWEIGHT'     => (float)($row['INV1_NETWEIGHT'] ?? 0),
                'INV1_GROSSWEIGHT'   => (float)($row['INV1_GROSSWEIGHT'] ?? 0),
                'INV1_TOTALAMOUNT'   => (float)($row['INV1_TOTALAMOUNT'] ?? 0),
                'INV1_EXCHANGERATE'  => number_format((float)($row['INV1_EXCHANGERATE'] ?? 0), 5, '.', ''),
                'T3'                 => trim($row['T3'] ?? 'B#1'),
                'T6'                 => trim($row['T6'] ?? ''),
                'INV1_STATUS'        => strtoupper(trim($row['INV1_STATUS'] ?? 'OP'))
            ];
        }

        return $result;
    }

    /**
     * ดึงรายละเอียด Invoice ครบทั้ง Heading, Invoice Items (INVCPRCS2), Product Items (INVCPRCS3)
     * พร้อมตรวจสอบความสอดคล้องของ Material In (อ้างอิง GetHeading ใน CIV)
     */
    public function getInvoiceDetail($invoiceNo)
    {
        $invoiceNo = strtoupper(trim($invoiceNo));
        if ($invoiceNo === '') {
            return null;
        }

        // 1. ดึงข้อมูล Heading พร้อม Join Master จาก MATS และ [MATS-NEWII]
        $sqlHead = "SELECT
                        h.*,
                        c.CONSIGNEE_COMPANY,
                        pl.DESCRIPTION AS PORT_LOADING_DESC,
                        pd.DESCRIPTION AS PORT_DISCHARGE_DESC,
                        plc.DESCRIPTION AS PLACE_DEST_DESC,
                        cnt.DESCRIPTION AS COUNTRY_DEST_DESC,
                        pmt.DESCRIPTION AS PAYMENT_DESC,
                        shp.DESCRIPTION AS SHIPMENT_DESC,
                        bnk.DESCRIPTION AS BANK_DESC
                    FROM INVCPRCS1 h
                    LEFT JOIN CSSPMSTR1 c ON h.CSTMSPPL_ID = c.CSTMSPPL_ID
                    LEFT JOIN [MATS-NEWII].dbo.PORTMSTR1 pl ON h.INV1_PORTLOADING = pl.PORT_ID
                    LEFT JOIN [MATS-NEWII].dbo.PORTMSTR1 pd ON h.INV1_PORTDISCHARGE = pd.PORT_ID
                    LEFT JOIN [MATS-NEWII].dbo.PLCEMSTR1 plc ON h.INV1_PLACEDESTINATION = plc.PLACE_ID
                    LEFT JOIN [MATS-NEWII].dbo.PLCEMSTR1 cnt ON h.INV1_COUNTRYDESTINATION = cnt.PLACE_ID
                    LEFT JOIN [MATS-NEWII].dbo.PMTMMSTR1 pmt ON h.PAYMENT_ID = pmt.PAYMENT_ID
                    LEFT JOIN [MATS-NEWII].dbo.SPTMMSTR1 shp ON h.SHIPMENT_ID = shp.SHIPMENT_ID
                    LEFT JOIN [MATS-NEWII].dbo.BANKMSTR1 bnk ON h.BANK_ID = bnk.BANK_ID
                    WHERE h.INVOICE_NO = :invoice_no";

        $stmtHead = $this->conn->prepare($sqlHead);
        $stmtHead->execute([':invoice_no' => $invoiceNo]);
        $head = $stmtHead->fetch(PDO::FETCH_ASSOC);

        if (!$head) {
            return null;
        }

        // คำนวณยอดเงินตามสูตรของ VB6 CIV (GetHeading)
        $totalAmount     = (float)($head['INV1_TOTALAMOUNT'] ?? 0);
        $transportation  = (float)($head['INV1_TRANSPORTATION'] ?? 0);
        $taxPercent      = (float)($head['INV1_TAXPERCENT'] ?? 0);
        $overseaFreight  = (float)($head['INV1_OVERSEAFREIGHT'] ?? 0);
        $insurance       = (float)($head['INV1_INSURANCE'] ?? 0);
        $certificate     = (float)($head['INV1_CERTIFICATE'] ?? 0);
        $mTotalAmount    = (float)($head['INV1_MTOTALAMOUNT'] ?? 0);
        $mGrandTotal     = (float)($head['INV1_MGRANDTOTALAMOUNT'] ?? 0);

        $subTotalAmount   = $totalAmount + $transportation;
        $taxAmount        = $subTotalAmount * ($taxPercent / 100);
        $grandTotalAmount = $subTotalAmount + $taxAmount - $overseaFreight - $insurance;

        $inv1MatIn = trim($head['INV1_MATERIALIN'] ?? '');

        // 2. ตรวจสอบ Material In ไม่ตรงกันกับ INVCPRCS2 และ INVCPRCS3 (แจ้งเตือนแบบ Non-blocking)
        $warnings = [];

        $sqlChk2 = "SELECT TOP 1 INV2_MATERIALIN
                    FROM INVCPRCS2
                    WHERE INVOICE_NO = :inv_no AND ISNULL(INV2_MATERIALIN, '') <> :mat_in";
        $stmtChk2 = $this->conn->prepare($sqlChk2);
        $stmtChk2->execute([':inv_no' => $invoiceNo, ':mat_in' => $inv1MatIn]);
        if ($rowChk2 = $stmtChk2->fetch(PDO::FETCH_ASSOC)) {
            $inv2MatIn = trim($rowChk2['INV2_MATERIALIN'] ?? '');
            $warnings[] = "ข้อมูล Material In ของ Invoice Heading เป็น {$inv1MatIn} ซึ่งไม่ตรงกับ Material In ของ Invoice Item ที่เป็น {$inv2MatIn}";
        }

        $sqlChk3 = "SELECT TOP 1 INV3_MATERIALIN
                    FROM INVCPRCS3
                    WHERE INVOICE_NO = :inv_no AND ISNULL(INV3_MATERIALIN, '') <> :mat_in";
        $stmtChk3 = $this->conn->prepare($sqlChk3);
        $stmtChk3->execute([':inv_no' => $invoiceNo, ':mat_in' => $inv1MatIn]);
        if ($rowChk3 = $stmtChk3->fetch(PDO::FETCH_ASSOC)) {
            $inv3MatIn = trim($rowChk3['INV3_MATERIALIN'] ?? '');
            $warnings[] = "ข้อมูล Material In ของ Invoice Heading เป็น {$inv1MatIn} ซึ่งไม่ตรงกับ Material In ของ Invoice Product ที่เป็น {$inv3MatIn}";
        }

        // จัดรูปแบบข้อมูล Heading
        $custId   = trim($head['CSTMSPPL_ID'] ?? '');
        $custName = trim($head['CONSIGNEE_COMPANY'] ?? '');

        $headingData = [
            'INVOICE_NO'               => trim($head['INVOICE_NO'] ?? ''),
            'INV1_STATUS'              => strtoupper(trim($head['INV1_STATUS'] ?? 'OP')),
            'INV1_MATERIALIN'          => $inv1MatIn,
            'CSTMSPPL_ID'              => $custId,
            'CONSIGNEE_COMPANY'        => $custName,
            'CUSTOMER_DISPLAY'         => $custName !== '' ? "{$custId}: {$custName}" : $custId,
            'INVOICE_DATE'             => !empty($head['INVOICE_DATE']) ? date('Y-m-d', strtotime($head['INVOICE_DATE'])) : '',
            'INVOICE_DATE_FMT'         => !empty($head['INVOICE_DATE']) ? date('d/m/Y', strtotime($head['INVOICE_DATE'])) : '-',
            'SHIPMENT_DATE'            => !empty($head['SHIPMENT_DATE']) ? date('Y-m-d', strtotime($head['SHIPMENT_DATE'])) : date('Y-m-d'),
            'SHIPMENT_DATE_FMT'        => !empty($head['SHIPMENT_DATE']) ? date('d/m/Y', strtotime($head['SHIPMENT_DATE'])) : '-',
            'CURRENCY_ID'              => trim($head['CURRENCY_ID'] ?? ''),
            'INV1_TAXPERCENT'          => $taxPercent,
            'INV1_TRANSPORTATION'      => $transportation,
            'INV1_OVERSEAFREIGHT'      => $overseaFreight,
            'INV1_CERTIFICATE'         => $certificate,
            'INV1_INSURANCE'           => $insurance,
            'INV1_TOTALAMOUNT'         => $totalAmount,
            'SUBTOTAL_AMOUNT'          => $subTotalAmount,
            'TAX_AMOUNT'               => $taxAmount,
            'GRAND_TOTAL_AMOUNT'       => $grandTotalAmount,
            'INV1_MTOTALAMOUNT'        => $mTotalAmount,
            'INV1_MGRANDTOTALAMOUNT'   => $mGrandTotal,
            'BOOK_NO'                  => trim($head['BOOK_NO'] ?? ''),
            'INV1_PORTLOADING'         => trim($head['INV1_PORTLOADING'] ?? '000'),
            'PORT_LOADING_DISPLAY'     => $this->formatMasterLabel($head['INV1_PORTLOADING'] ?? '000', $head['PORT_LOADING_DESC'] ?? ''),
            'INV1_PORTDISCHARGE'       => trim($head['INV1_PORTDISCHARGE'] ?? '000'),
            'PORT_DISCHARGE_DISPLAY'   => $this->formatMasterLabel($head['INV1_PORTDISCHARGE'] ?? '000', $head['PORT_DISCHARGE_DESC'] ?? ''),
            'INV1_PLACEDESTINATION'    => trim($head['INV1_PLACEDESTINATION'] ?? '000'),
            'PLACE_DEST_DISPLAY'       => $this->formatMasterLabel($head['INV1_PLACEDESTINATION'] ?? '000', $head['PLACE_DEST_DESC'] ?? ''),
            'INV1_COUNTRYDESTINATION'  => trim($head['INV1_COUNTRYDESTINATION'] ?? '000'),
            'COUNTRY_DEST_DISPLAY'     => $this->formatMasterLabel($head['INV1_COUNTRYDESTINATION'] ?? '000', $head['COUNTRY_DEST_DESC'] ?? ''),
            'PAYMENT_ID'               => trim($head['PAYMENT_ID'] ?? '00'),
            'PAYMENT_DISPLAY'          => $this->formatMasterLabel($head['PAYMENT_ID'] ?? '00', $head['PAYMENT_DESC'] ?? ''),
            'SHIPMENT_ID'              => trim($head['SHIPMENT_ID'] ?? '000'),
            'SHIPMENT_DISPLAY'         => $this->formatMasterLabel($head['SHIPMENT_ID'] ?? '000', $head['SHIPMENT_DESC'] ?? ''),
            'BANK_ID'                  => trim($head['BANK_ID'] ?? '00'),
            'BANK_DISPLAY'             => $this->formatMasterLabel($head['BANK_ID'] ?? '00', $head['BANK_DESC'] ?? ''),
            'LETTER_CREDIT'            => trim($head['LETTER_CREDIT'] ?? ''),
            'INV1_SHIPPEDBY'           => trim($head['INV1_SHIPPEDBY'] ?? ''),
            'INV1_REMARK1'             => trim($head['INV1_REMARK1'] ?? ''),
            'INV1_REMARK2'             => trim($head['INV1_REMARK2'] ?? ''),
            'INV1_PACKAGE'             => (int)($head['INV1_PACKAGE'] ?? 0),
            'INV1_NETPIECE'            => (int)($head['INV1_NETPIECE'] ?? 0),
            'INV1_NETWEIGHT'           => (float)($head['INV1_NETWEIGHT'] ?? 0),
            'INV1_GROSSWEIGHT'         => (float)($head['INV1_GROSSWEIGHT'] ?? 0),
            'INV1_EXCHANGERATE'        => number_format((float)($head['INV1_EXCHANGERATE'] ?? 0), 5, '.', ''),
            'T3'                       => trim($head['T3'] ?? '') !== '' ? trim($head['T3']) : 'B#1',
            'T6'                       => trim($head['T6'] ?? '')
        ];

        // 3. ดึงรายการ Invoice Item (INVCPRCS2 - อ้างอิงคอลัมน์ในรูป 111.jpg)
        $sqlItem = "SELECT
                        INVOICE_NO,
                        INVOICE_ITEM,
                        SALEORDER_NO,
                        SALEORDER_ITEM,
                        ALLOY,
                        TEMPER,
                        SURFACE_GRADE,
                        METALLURGICAL_GRADE,
                        THICKNESS,
                        WIDTH,
                        LENGTH,
                        ISNULL(INV2_NETWEIGHT, 0) AS INV2_NETWEIGHT,
                        INV2_MATERIALIN,
                        ISNULL(INV2_EXCHANGERATE, 0) AS INV2_EXCHANGERATE,
                        ISNULL(INV2_INVOICEPRICE, 0) AS INV2_INVOICEPRICE
                    FROM INVCPRCS2
                    WHERE INVOICE_NO = :invoice_no
                    ORDER BY INVOICE_ITEM ASC";

        $stmtItem = $this->conn->prepare($sqlItem);
        $stmtItem->execute([':invoice_no' => $invoiceNo]);
        $rawItems = $stmtItem->fetchAll(PDO::FETCH_ASSOC);

        $invoiceItems = [];
        foreach ($rawItems as $item) {
            $invoiceItems[] = [
                'INVOICE_NO'          => trim($item['INVOICE_NO'] ?? ''),
                'INVOICE_ITEM'        => trim($item['INVOICE_ITEM'] ?? ''),
                'SALEORDER_NO'        => trim($item['SALEORDER_NO'] ?? ''),
                'SALEORDER_ITEM'      => trim($item['SALEORDER_ITEM'] ?? ''),
                'ALLOY'               => trim($item['ALLOY'] ?? ''),
                'TEMPER'              => trim($item['TEMPER'] ?? ''),
                'SURFACE_GRADE'       => trim($item['SURFACE_GRADE'] ?? ''),
                'METALLURGICAL_GRADE' => trim($item['METALLURGICAL_GRADE'] ?? ''),
                'THICKNESS'           => (float)($item['THICKNESS'] ?? 0),
                'WIDTH'               => (float)($item['WIDTH'] ?? 0),
                'LENGTH'              => (float)($item['LENGTH'] ?? 0),
                'INV2_NETWEIGHT'      => (float)($item['INV2_NETWEIGHT'] ?? 0),
                'INV2_MATERIALIN'     => trim($item['INV2_MATERIALIN'] ?? ''),
                'INV2_EXCHANGERATE'   => number_format((float)($item['INV2_EXCHANGERATE'] ?? 0), 5, '.', ''),
                'INV2_INVOICEPRICE'   => (float)($item['INV2_INVOICEPRICE'] ?? 0)
            ];
        }

        // 4. ดึงรายการ Product Item (INVCPRCS3 - อ้างอิงคอลัมน์ในรูป 222.jpg)
        $sqlProd = "SELECT
                        PRODUCT_NO,
                        INVOICE_NO,
                        INVOICE_ITEM,
                        SKID_NO,
                        DEBITNOTE_NO,
                        DEBITNOTE_ITEM,
                        CREDITNOTE_NO,
                        CREDITNOTE_ITEM,
                        BOOK_NO,
                        CSTMSPPL_ID,
                        SALEORDER_NO,
                        SALEORDER_ITEM,
                        ALLOY,
                        TEMPER,
                        SURFACE_GRADE,
                        METALLURGICAL_GRADE,
                        THICKNESS,
                        WIDTH,
                        LENGTH,
                        ISNULL(INV3_PACKAGE, 0) AS INV3_PACKAGE,
                        ISNULL(INV3_NETWEIGHT, 0) AS INV3_NETWEIGHT,
                        ISNULL(INV3_INVOICEPRICE, 0) AS INV3_INVOICEPRICE,
                        ISNULL(INV3_DEBITPRICE, 0) AS INV3_DEBITPRICE,
                        ISNULL(INV3_CREDITNETWEIGHT, 0) AS INV3_CREDITNETWEIGHT,
                        ISNULL(INV3_CREDITPRICE, 0) AS INV3_CREDITPRICE,
                        ISNULL(INV3_STATUS, 'OP') AS INV3_STATUS,
                        INV3_MATERIALIN,
                        INV3_T3,
                        INV3_T6,
                        COIL_NO,
                        PRODUCT_ID
                    FROM INVCPRCS3
                    WHERE INVOICE_NO = :invoice_no
                    ORDER BY INVOICE_ITEM ASC, PRODUCT_NO ASC";

        $stmtProd = $this->conn->prepare($sqlProd);
        $stmtProd->execute([':invoice_no' => $invoiceNo]);
        $rawProds = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

        $productItems = [];
        foreach ($rawProds as $prod) {
            $productItems[] = [
                'PRODUCT_NO'           => trim($prod['PRODUCT_NO'] ?? ''),
                'INVOICE_NO'           => trim($prod['INVOICE_NO'] ?? ''),
                'INVOICE_ITEM'         => trim($prod['INVOICE_ITEM'] ?? ''),
                'SKID_NO'              => trim($prod['SKID_NO'] ?? ''),
                'DEBITNOTE_NO'         => trim($prod['DEBITNOTE_NO'] ?? ''),
                'DEBITNOTE_ITEM'       => trim($prod['DEBITNOTE_ITEM'] ?? ''),
                'CREDITNOTE_NO'        => trim($prod['CREDITNOTE_NO'] ?? ''),
                'CREDITNOTE_ITEM'      => trim($prod['CREDITNOTE_ITEM'] ?? ''),
                'BOOK_NO'              => trim($prod['BOOK_NO'] ?? ''),
                'CSTMSPPL_ID'          => trim($prod['CSTMSPPL_ID'] ?? ''),
                'SALEORDER_NO'         => trim($prod['SALEORDER_NO'] ?? ''),
                'SALEORDER_ITEM'       => trim($prod['SALEORDER_ITEM'] ?? ''),
                'ALLOY'                => trim($prod['ALLOY'] ?? ''),
                'TEMPER'               => trim($prod['TEMPER'] ?? ''),
                'SURFACE_GRADE'        => trim($prod['SURFACE_GRADE'] ?? ''),
                'METALLURGICAL_GRADE'  => trim($prod['METALLURGICAL_GRADE'] ?? ''),
                'THICKNESS'            => (float)($prod['THICKNESS'] ?? 0),
                'WIDTH'                => (float)($prod['WIDTH'] ?? 0),
                'LENGTH'               => (float)($prod['LENGTH'] ?? 0),
                'INV3_PACKAGE'         => (int)($prod['INV3_PACKAGE'] ?? 0),
                'INV3_NETWEIGHT'       => (float)($prod['INV3_NETWEIGHT'] ?? 0),
                'INV3_INVOICEPRICE'    => (float)($prod['INV3_INVOICEPRICE'] ?? 0),
                'INV3_DEBITPRICE'      => (float)($prod['INV3_DEBITPRICE'] ?? 0),
                'INV3_CREDITNETWEIGHT' => (float)($prod['INV3_CREDITNETWEIGHT'] ?? 0),
                'INV3_CREDITPRICE'     => (float)($prod['INV3_CREDITPRICE'] ?? 0),
                'INV3_STATUS'          => strtoupper(trim($prod['INV3_STATUS'] ?? 'OP')),
                'INV3_MATERIALIN'      => trim($prod['INV3_MATERIALIN'] ?? ''),
                'INV3_T3'              => trim($prod['INV3_T3'] ?? ''),
                'INV3_T6'              => trim($prod['INV3_T6'] ?? ''),
                'COIL_NO'              => trim($prod['COIL_NO'] ?? ''),
                'PRODUCT_ID'           => trim($prod['PRODUCT_ID'] ?? '')
            ];
        }

        return [
            'heading'       => $headingData,
            'invoice_items' => $invoiceItems,
            'product_items' => $productItems,
            'warnings'      => $warnings
        ];
    }

    /**
     * บันทึกการปิด Invoice และอัปเดตสถานะสต็อกทั้งหมดภายใต้ Transaction
     * (อ้างอิง Logic ทั้งหมดจาก cmdSave_Click และ UpdateClosingInvoice ใน CIV)
     */
    public function saveClosingInvoice($payload)
    {
        $invoiceNo      = strtoupper(trim($payload['invoice_no'] ?? ''));
        $status         = strtoupper(trim($payload['status'] ?? ''));
        $exchangeRate   = isset($payload['exchange_rate']) ? (float)$payload['exchange_rate'] : 0.0;
        $t3             = strtoupper(trim($payload['t3'] ?? 'B#1'));
        $t6             = strtoupper(trim($payload['t6'] ?? ''));
        $updateExBatch  = !empty($payload['update_exchange_rate']) ? 1 : 0;
        $fromDate       = trim($payload['from_date'] ?? '');
        $toDate         = trim($payload['to_date'] ?? '');

        if (strlen($invoiceNo) !== 12) {
            throw new Exception("เลขที่ Invoice ต้องมีความยาว 12 ตัวอักษรเท่านั้น");
        }
        if (!in_array($status, ['OP', 'CP'], true)) {
            throw new Exception("สถานะ Invoice ไม่ถูกต้อง (ต้องเป็น OP หรือ CP)");
        }
        if ($updateExBatch === 1 && ($fromDate === '' || $toDate === '')) {
            throw new Exception("กรุณาระบุช่วงวันที่ Shipment Date (From - To) สำหรับอัปเดตอัตราแลกเปลี่ยนแบบกลุ่ม");
        }

        // ตรวจสอบข้อมูลหัวบิลเดิมก่อนเริ่ม Transaction
        $stmtCheck = $this->conn->prepare("SELECT INVOICE_NO, CSTMSPPL_ID FROM INVCPRCS1 WHERE INVOICE_NO = :invoice_no");
        $stmtCheck->execute([':invoice_no' => $invoiceNo]);
        $existingHead = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existingHead) {
            throw new Exception("ไม่พบข้อมูลเลขที่ Invoice: {$invoiceNo} ในระบบ");
        }

        $customerId = strtoupper(trim($existingHead['CSTMSPPL_ID'] ?? ''));

        try {
            $this->conn->beginTransaction();

            // 1. อัปเดตหัวบิล INVCPRCS1 (UpdateClosingInvoice)
            $sqlUpdInv1 = "UPDATE INVCPRCS1
                           SET INV1_STATUS = :status,
                               INV1_EXCHANGERATE = :ex_rate,
                               T3 = :t3,
                               T6 = :t6
                           WHERE INVOICE_NO = :invoice_no";
            $stmtUpdInv1 = $this->conn->prepare($sqlUpdInv1);
            $stmtUpdInv1->execute([
                ':status'     => $status,
                ':ex_rate'    => $exchangeRate,
                ':t3'         => $t3,
                ':t6'         => $t6,
                ':invoice_no' => $invoiceNo
            ]);

            // 2. อัปเดต INVCPRCS2 เฉพาะกรณีไม่ได้ติ๊ก Batch Update และลูกค้า 3 ตัวแรกไม่ใช่ MIL
            if ($updateExBatch === 0 && substr($customerId, 0, 3) !== 'MIL') {
                $sqlUpdInv2 = "UPDATE INVCPRCS2
                               SET INV2_EXCHANGERATE = :ex_rate
                               WHERE INVOICE_NO = :invoice_no";
                $stmtUpdInv2 = $this->conn->prepare($sqlUpdInv2);
                $stmtUpdInv2->execute([
                    ':ex_rate'    => $exchangeRate,
                    ':invoice_no' => $invoiceNo
                ]);
            }

            // 3. อัปเดตสถานะในตาราง INVCPRCS3
            $sqlUpdInv3Status = "UPDATE INVCPRCS3
                                 SET INV3_STATUS = :status
                                 WHERE INVOICE_NO = :invoice_no";
            $stmtUpdInv3Status = $this->conn->prepare($sqlUpdInv3Status);
            $stmtUpdInv3Status->execute([
                ':status'     => $status,
                ':invoice_no' => $invoiceNo
            ]);

            // 4. กรณีติ๊ก Update Exchange Rate แบบกลุ่ม (Local Customer & MAT-Z%)
            $batchUpdatedCount = 0;
            if ($updateExBatch === 1) {
                $sqlBatchSelect = "SELECT DISTINCT A.INVOICE_NO
                                   FROM INVCPRCS1 AS A
                                   INNER JOIN INVCPRCS2 AS B ON A.INVOICE_NO = B.INVOICE_NO
                                   INNER JOIN CSSPMSTR1 AS D ON A.CSTMSPPL_ID = D.CSTMSPPL_ID
                                   INNER JOIN RGNSMSTR1 AS C ON C.REGION_GROUP = D.REGION_GROUP
                                   WHERE CONVERT(VARCHAR(10), A.SHIPMENT_DATE, 120) BETWEEN :from_date AND :to_date
                                     AND A.INVOICE_NO LIKE 'MAT-Z%'
                                     AND C.ACCOUNT_REGION = 'LCL'";
                $stmtBatchSelect = $this->conn->prepare($sqlBatchSelect);
                $stmtBatchSelect->execute([
                    ':from_date' => $fromDate,
                    ':to_date'   => $toDate
                ]);
                $batchInvoices = $stmtBatchSelect->fetchAll(PDO::FETCH_COLUMN);

                $stmtBatchUpd2 = $this->conn->prepare("UPDATE INVCPRCS2 SET INV2_EXCHANGERATE = :ex_rate WHERE INVOICE_NO = :inv_no");
                $stmtBatchUpd1 = $this->conn->prepare("UPDATE INVCPRCS1 SET INV1_EXCHANGERATE = :ex_rate, T3 = :t3, T6 = :t6 WHERE INVOICE_NO = :inv_no");

                foreach ($batchInvoices as $batchInvNo) {
                    $batchInvNo = trim($batchInvNo);
                    $stmtBatchUpd2->execute([
                        ':ex_rate' => $exchangeRate,
                        ':inv_no'  => $batchInvNo
                    ]);
                    $stmtBatchUpd1->execute([
                        ':ex_rate' => $exchangeRate,
                        ':t3'      => $t3,
                        ':t6'      => $t6,
                        ':inv_no'  => $batchInvNo
                    ]);
                    $batchUpdatedCount++;
                }
            }

            // 5. อัปเดตสถานะสินค้าในสต็อก (COILPROD1, CRSHPROD1, PACKPROD1) และคำนวณ INV3_T3 รายชิ้น
            $sqlProducts = "SELECT
                                A.PRODUCT_NO,
                                A.INVOICE_NO,
                                A.INVOICE_ITEM,
                                A.COIL_NO,
                                A.INV3_MATERIALIN,
                                A.PRODUCT_ID,
                                B.CSTMSPPL_ID
                            FROM INVCPRCS3 AS A
                            LEFT OUTER JOIN COILPROD1 AS B ON A.COIL_NO = B.COIL_NO
                            WHERE A.INVOICE_NO = :invoice_no";
            $stmtProducts = $this->conn->prepare($sqlProducts);
            $stmtProducts->execute([':invoice_no' => $invoiceNo]);
            $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

            $stockStatus = ($status === 'OP') ? 'IN' : 'SH';

            $stmtUpdCoil = $this->conn->prepare("UPDATE COILPROD1 SET COIL_STATUS = :st WHERE COIL_NO = :prod_no");
            $stmtUpdCrsh = $this->conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = :st WHERE PRODUCT_NO = :prod_no");
            $stmtUpdPack = $this->conn->prepare("UPDATE PACKPROD1 SET PACK_STATUS = :st WHERE PRODUCT_NO = :prod_no");
            $stmtUpdInv3T3 = $this->conn->prepare("UPDATE INVCPRCS3 SET INV3_T3 = :t3, INV3_T6 = :t6 WHERE PRODUCT_NO = :prod_no AND INVOICE_NO = :inv_no");

            $itemT3 = '';
            foreach ($products as $prod) {
                $productNo  = trim($prod['PRODUCT_NO'] ?? '');
                $productId  = strtoupper(trim($prod['PRODUCT_ID'] ?? ''));
                $coilCustId = strtoupper(trim($prod['CSTMSPPL_ID'] ?? ''));
                $inv3MatIn  = strtoupper(trim($prod['INV3_MATERIALIN'] ?? ''));

                if ($productId === 'CO') {
                    $stmtUpdCoil->execute([
                        ':st'      => $stockStatus,
                        ':prod_no' => $productNo
                    ]);
                } elseif (in_array($productId, ['CC', 'SH', 'NC'], true)) {
                    $stmtUpdCrsh->execute([
                        ':st'      => $stockStatus,
                        ':prod_no' => $productNo
                    ]);
                }

                // อัปเดต PACKPROD1 สำหรับสินค้าทุกประเภท
                $stmtUpdPack->execute([
                    ':st'      => $stockStatus,
                    ':prod_no' => $productNo
                ]);

                // คำนวณรหัส INV3_T3 ตามเงื่อนไขของ VB6 CIV
                switch ($coilCustId) {
                    case 'MAT':
                        switch ($inv3MatIn) {
                            case 'BOI-MAT1':
                                $itemT3 = 'B#1';
                                break;
                            case 'NONE-BOI':
                                $itemT3 = (substr($invoiceNo, 0, 5) === 'MAT-T') ? 'NON' : 'B#1';
                                break;
                            case 'BOI-MAT2':
                                $itemT3 = 'SW1';
                                break;
                            case 'BOI-GRS1':
                                $itemT3 = 'GRS';
                                break;
                        }
                        break;
                    case 'ALCA':
                    case 'NSA':
                        $itemT3 = 'NSA';
                        break;
                    case 'UACJ':
                        $itemT3 = 'UAC';
                        break;
                    case 'MAL':
                        $itemT3 = 'B1I';
                        break;
                    default:
                        if ($inv3MatIn === 'BOI-IMP2') {
                            $itemT3 = 'B#2';
                        } elseif ($inv3MatIn === 'NONE-BOI') {
                            $itemT3 = 'NON';
                        } else {
                            $itemT3 = 'XXX';
                        }
                        break;
                }

                $stmtUpdInv3T3->execute([
                    ':t3'      => $itemT3,
                    ':t6'      => $t6,
                    ':prod_no' => $productNo,
                    ':inv_no'  => $invoiceNo
                ]);
            }

            $this->conn->commit();

            return [
                'invoice_no'          => $invoiceNo,
                'status'              => $status,
                'stock_status'        => $stockStatus,
                'product_updated'     => count($products),
                'batch_invoice_count' => $batchUpdatedCount
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    private function formatMasterLabel($id, $desc)
    {
        $id = trim((string)$id);
        $desc = trim((string)$desc);
        if ($desc !== '') {
            return "{$id}: {$desc}";
        }
        return $id;
    }
}