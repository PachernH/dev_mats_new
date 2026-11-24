<?php
class InvoiceModelMats {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // =========================================================================
    // 1. MASTER DATA & LOOKUPS
    // =========================================================================
    public function getMasterDropdowns() {
        $customers = $this->conn->query("
            SELECT RTRIM(CSTMSPPL_ID) AS CSTMSPPL_ID,
                   RTRIM(ISNULL(CONSIGNEE_COMPANY, '')) AS CONSIGNEE_COMPANY,
                   RTRIM(ISNULL(CSTMSPPL_LOCATION, '')) AS CSTMSPPL_LOCATION
            FROM CSSPMSTR1
            WHERE ISNULL(RTRIM(CSTMSPPL_ID), '') <> ''
            ORDER BY CSTMSPPL_ID ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $currencies = $this->conn->query("SELECT RTRIM(CURRENCY_ID) AS CURRENCY_ID FROM CRNCMSTR1 ORDER BY CURRENCY_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
        $banks      = $this->conn->query("SELECT RTRIM(BANK_ID) AS BANK_ID, RTRIM(ISNULL(DESCRIPTION, '')) AS DESCRIPTION FROM BANKMSTR1 ORDER BY BANK_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
        $payments   = $this->conn->query("SELECT RTRIM(PAYMENT_ID) AS PAYMENT_ID, RTRIM(ISNULL(DESCRIPTION, '')) AS DESCRIPTION FROM PMTMMSTR1 ORDER BY PAYMENT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
        $shipments  = $this->conn->query("SELECT RTRIM(SHIPMENT_ID) AS SHIPMENT_ID, RTRIM(ISNULL(DESCRIPTION, '')) AS DESCRIPTION FROM SPTMMSTR1 ORDER BY SHIPMENT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
        $ports      = $this->conn->query("SELECT RTRIM(PORT_ID) AS PORT_ID, RTRIM(ISNULL(DESCRIPTION, '')) AS DESCRIPTION FROM PORTMSTR1 ORDER BY PORT_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
        $places     = $this->conn->query("SELECT RTRIM(PLACE_ID) AS PLACE_ID, RTRIM(ISNULL(DESCRIPTION, '')) AS DESCRIPTION FROM PLCEMSTR1 ORDER BY PLACE_ID ASC")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'customers'  => $customers,
            'currencies' => $currencies,
            'banks'      => $banks,
            'payments'   => $payments,
            'shipments'  => $shipments,
            'ports'      => $ports,
            'places'     => $places
        ];
    }

    public function getCustomerInfo($customerId) {
        $stmt = $this->conn->prepare("SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :cust_id");
        $stmt->execute([':cust_id' => trim($customerId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("ไม่พบข้อมูลลูกค้าในระบบ ($customerId)");
        }
        return $row;
    }

    public function getAvailableBookings($customerId, $currentInvoiceNo = '') {
        $sql = "SELECT BOOK_NO, BSH1_PORTLOADING, BSH1_PORTDISCHARGE, BSH1_PLACEDESTINATION, BSH1_COUNTRYDESTINATION,
                       BSH1_SHIPPINGMARK, BSH1_SHIPPINGMARK1, BSH1_SHIPPINGMARK2, BSH1_SHIPPINGMARK3,
                       BSH1_SHIPPINGMARK4, BSH1_SHIPPINGMARK5, BSH1_SHIPPINGMARK6, BSH1_SHIPPINGMARK7
                FROM BOOKSHIP1
                WHERE CSTMSPPL_ID = :cust_id
                  AND (ISNULL(INVOICE_NO, '') = '' OR INVOICE_NO = :inv_no)
                ORDER BY BOOK_NO DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cust_id' => trim($customerId),
            ':inv_no'  => trim($currentInvoiceNo)
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingDetail($bookNo, $customerId, $currentInvoiceNo = '') {
        $stmt = $this->conn->prepare("SELECT * FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
        $stmt->execute([':book_no' => trim($bookNo)]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$book) {
            throw new Exception("ไม่พบเลขที่ใบจองเรือ ($bookNo)");
        }
        $usedInv = trim($book['INVOICE_NO'] ?? '');
        if ($usedInv !== '' && $usedInv !== trim($currentInvoiceNo)) {
            throw new Exception("ใบจองเรือ $bookNo ถูกนำไปใช้กับ Invoice เลขที่ $usedInv แล้ว");
        }
        if (trim($book['CSTMSPPL_ID'] ?? '') !== trim($customerId)) {
            throw new Exception("รหัสลูกค้าในใบจองเรือไม่ตรงกับลูกค้าใน Invoice นี้");
        }
        return $book;
    }

    // =========================================================================
    // 2. GENERATE ANNUAL RUNNING INVOICE NO (INVCPRCS1 + BOOKSHIP1)
    // =========================================================================
    public function generateInvoiceNo($prefix) {
        $allowedPrefixes = ['MAT-C-', 'MAT-E-', 'MAT-Z-', 'MAT-H-', 'MAT-V-', 'MAT-N-', 'MAT-T-'];
        $prefix = strtoupper(trim($prefix));
        if (!in_array($prefix, $allowedPrefixes)) {
            $prefix = 'MAT-Z-';
        }

        $yy = date('y');
        $pattern = $prefix . $yy . '%';

        $sql = "SELECT MAX(MAX_RUN) AS LAST_RUN FROM (
                    SELECT MAX(CAST(RIGHT(RTRIM(INVOICE_NO), 4) AS INT)) AS MAX_RUN
                    FROM INVCPRCS1
                    WHERE INVOICE_NO LIKE :pat1
                      AND LEN(RTRIM(INVOICE_NO)) = 12
                      AND RIGHT(RTRIM(INVOICE_NO), 4) NOT LIKE '%[^0-9]%'
                    UNION ALL
                    SELECT MAX(CAST(RIGHT(RTRIM(INVOICE_NO), 4) AS INT)) AS MAX_RUN
                    FROM BOOKSHIP1
                    WHERE INVOICE_NO LIKE :pat2
                      AND LEN(RTRIM(INVOICE_NO)) = 12
                      AND RIGHT(RTRIM(INVOICE_NO), 4) NOT LIKE '%[^0-9]%'
                ) T";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':pat1' => $pattern, ':pat2' => $pattern]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextRun = ((int)($row['LAST_RUN'] ?? 0)) + 1;
        return $prefix . $yy . str_pad($nextRun, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // 3. GET FULL INVOICE DETAIL (ALL 5 SECTIONS)
    // =========================================================================
    public function getInvoiceDetail($invoiceNo) {
        $sqlHead = "SELECT A.*,
                           B.CSTMSPPL_LOCATION,
                           B.CONSIGNEE_COMPANY, B.CONSIGNEE_ADDRESS1, B.CONSIGNEE_ADDRESS2, B.CONSIGNEE_ADDRESS3,
                           B.CONSIGNEE_TELEPHONE, B.CONSIGNEE_FAX, B.CONSIGNEE_EMAIL, B.CONSIGNEE_CONTACT, B.CONSIGNEE_POSITION,
                           B.NOTIFY_COMPANY, B.NOTIFY_ADDRESS1, B.NOTIFY_ADDRESS2, B.NOTIFY_ADDRESS3,
                           B.NOTIFY_TELEPHONE, B.NOTIFY_FAX, B.NOTIFY_EMAIL, B.NOTIFY_CONTACT, B.NOTIFY_POSITION,
                           B.ALSONOTIFY_COMPANY, B.ALSONOTIFY_ADDRESS1, B.ALSONOTIFY_ADDRESS2, B.ALSONOTIFY_ADDRESS3,
                           B.ALSONOTIFY_TELEPHONE, B.ALSONOTIFY_FAX, B.ALSONOTIFY_EMAIL, B.ALSONOTIFY_CONTACT, B.ALSONOTIFY_POSITION
                    FROM INVCPRCS1 A
                    LEFT JOIN CSSPMSTR1 B ON A.CSTMSPPL_ID = B.CSTMSPPL_ID
                    WHERE A.INVOICE_NO = :inv_no";
        $stmtHead = $this->conn->prepare($sqlHead);
        $stmtHead->execute([':inv_no' => trim($invoiceNo)]);
        $heading = $stmtHead->fetch(PDO::FETCH_ASSOC);

        if (!$heading) {
            throw new Exception("ไม่พบข้อมูลใบแจ้งหนี้เลขที่ $invoiceNo");
        }

        $sqlItems = "SELECT A.*,
                            B.CTM2_ORDERWEIGHT, B.CTM2_ORDERPIECE, B.CTM2_REPLACEWEIGHT, B.CTM2_REPLACEPIECE,
                            B.CTM2_REJECTWEIGHT, B.CTM2_REJECTPIECE, B.CTM2_MAXTOLERANCE, B.CTM2_MINTOLERANCE,
                            B.CTM2_INVOICEWEIGHT, B.CTM2_INVOICEPIECE, B.CTM2_INVOICEBUNDLE, B.CTM2_STATUS,
                            B.CTM2_WHOLEWEIGHT, B.CTM2_SCRAPWIRE, B.WEIGHT_PIECE, B.REQUEST_DATE, B.CONFIRM_DATE
                     FROM INVCPRCS2 A
                     LEFT JOIN CSTMORDR2 B ON A.SALEORDER_NO = B.SALEORDER_NO AND A.SALEORDER_ITEM = B.SALEORDER_ITEM
                     WHERE A.INVOICE_NO = :inv_no
                     ORDER BY A.INVOICE_ITEM ASC";
        $stmtItems = $this->conn->prepare($sqlItems);
        $stmtItems->execute([':inv_no' => trim($invoiceNo)]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $sqlProds = "SELECT * FROM INVCPRCS3 WHERE INVOICE_NO = :inv_no ORDER BY INVOICE_ITEM ASC, SKID_NO ASC, PRODUCT_NO ASC";
        $stmtProds = $this->conn->prepare($sqlProds);
        $stmtProds->execute([':inv_no' => trim($invoiceNo)]);
        $products = $stmtProds->fetchAll(PDO::FETCH_ASSOC);

        return [
            'heading'  => $heading,
            'items'    => $items,
            'products' => $products
        ];
    }

    // =========================================================================
    // 4. CREATE / UPDATE INVOICE HEADING (INVCPRCS1)
    // =========================================================================
    public function createInvoiceHeading($data, $userId) {
        try {
            $this->conn->beginTransaction();

            $customerId = trim($data['CSTMSPPL_ID'] ?? '');
            if ($customerId === '') {
                throw new Exception("กรุณาเลือกรหัสลูกค้า (Customer ID)");
            }

            $cust = $this->getCustomerInfo($customerId);
            $isLocal = (trim($cust['CSTMSPPL_LOCATION'] ?? '') === 'L');
            $bookNo = $isLocal ? '' : trim($data['BOOK_NO'] ?? '');

            $prefix = trim($data['PREFIX'] ?? 'MAT-Z-');
            $invoiceNo = $this->generateInvoiceNo($prefix);

            if ($bookNo !== '') {
                $this->getBookingDetail($bookNo, $customerId, $invoiceNo);
            }

            $sql = "INSERT INTO INVCPRCS1 (
                        INVOICE_NO, CSTMSPPL_ID, INV1_MATERIALINPRN, ACCOUNT_CODE, INVOICE_DATE, SHIPMENT_DATE,
                        BOOK_NO, PAYMENT_ID, SHIPMENT_ID, CURRENCY_ID, BANK_ID, LETTER_CREDIT, INV1_SHIPPEDBY,
                        INV1_SHIPPINGMARK, INV1_SHIPPINGMARK1, INV1_SHIPPINGMARK2, INV1_SHIPPINGMARK3,
                        INV1_SHIPPINGMARK4, INV1_SHIPPINGMARK5, INV1_SHIPPINGMARK6, INV1_SHIPPINGMARK7,
                        INV1_PORTLOADING, INV1_PORTDISCHARGE, INV1_PLACEDESTINATION, INV1_COUNTRYDESTINATION,
                        INV1_TAXPERCENT, INV1_CERTIFICATE, INV1_DISCOUNT, INV1_TRANSPORTATION,
                        INV1_OVERSEAFREIGHT, INV1_INSURANCE, INV1_TOTALAMOUNT, INV1_MTOTALAMOUNT, INV1_MGRANDTOTALAMOUNT,
                        FOB_VALUE, INV1_REMARK1, INV1_REMARK2, INV1_REMARK3, INV1_REMARK4, INV1_REMARK5, INV1_REMARK6, INV1_REMARK7,
                        INV1_MCREMARK1, INV1_MCREMARK2, INV1_MCREMARK3, INV1_MCREMARK4,
                        INV1_MCREMARK5, INV1_MCREMARK6, INV1_MCREMARK7, INV1_MCREMARK8,
                        INV1_PACKAGE, INV1_NETPIECE, INV1_NETWEIGHT, INV1_GROSSWEIGHT,
                        INV1_OPERATOR, INV1_OPERATEDATE, INV1_STATUS
                    ) VALUES (
                        :inv_no, :cust_id, :mat_in_prn, :acc_code, :inv_date, :ship_date,
                        :book_no, :pay_id, :ship_id, :curr_id, :bank_id, :lc_no, :shipped_by,
                        :sm0, :sm1, :sm2, :sm3, :sm4, :sm5, :sm6, :sm7,
                        :port_load, :port_dis, :place_dest, :country_dest,
                        :tax_pct, :cert, :disc, :trans, :freight, :insur, 0, :m_tot, :m_gtot,
                        :fob_val, :rem1, :rem2, :rem3, :rem4, :rem5, :rem6, :rem7,
                        :mc1, :mc2, :mc3, :mc4, :mc5, :mc6, :mc7, :mc8,
                        0, 0, 0, 0,
                        :op_user, GETDATE(), 'OP'
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':inv_no'       => $invoiceNo,
                ':cust_id'      => $customerId,
                ':mat_in_prn'   => trim($data['INV1_MATERIALINPRN'] ?? ''),
                ':acc_code'     => trim($data['ACCOUNT_CODE'] ?? ($cust['ACCOUNT_CODE'] ?? '')),
                ':inv_date'     => $data['INVOICE_DATE'],
                ':ship_date'    => $data['SHIPMENT_DATE'],
                ':book_no'      => $bookNo,
                ':pay_id'       => trim($data['PAYMENT_ID'] ?? '00'),
                ':ship_id'      => trim($data['SHIPMENT_ID'] ?? '000'),
                ':curr_id'      => trim($data['CURRENCY_ID'] ?? 'BAHT'),
                ':bank_id'      => trim($data['BANK_ID'] ?? '00'),
                ':lc_no'        => strtoupper(trim($data['LETTER_CREDIT'] ?? '')),
                ':shipped_by'   => strtoupper(trim($data['INV1_SHIPPEDBY'] ?? '')),
                ':sm0'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK'] ?? ''),
                ':sm1'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK1'] ?? ''),
                ':sm2'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK2'] ?? ''),
                ':sm3'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK3'] ?? ''),
                ':sm4'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK4'] ?? ''),
                ':sm5'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK5'] ?? ''),
                ':sm6'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK6'] ?? ''),
                ':sm7'          => $bookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK7'] ?? ''),
                ':port_load'    => $bookNo === '' ? '000' : trim($data['INV1_PORTLOADING'] ?? '000'),
                ':port_dis'     => $bookNo === '' ? '000' : trim($data['INV1_PORTDISCHARGE'] ?? '000'),
                ':place_dest'   => $bookNo === '' ? '000' : trim($data['INV1_PLACEDESTINATION'] ?? '000'),
                ':country_dest' => $bookNo === '' ? '000' : trim($data['INV1_COUNTRYDESTINATION'] ?? '000'),
                ':tax_pct'      => (int)($data['INV1_TAXPERCENT'] ?? 0),
                ':cert'         => (float)($data['INV1_CERTIFICATE'] ?? 0),
                ':disc'         => (float)($data['INV1_DISCOUNT'] ?? 0),
                ':trans'        => (float)($data['INV1_TRANSPORTATION'] ?? 0),
                ':freight'      => (float)($data['INV1_OVERSEAFREIGHT'] ?? 0),
                ':insur'        => (float)($data['INV1_INSURANCE'] ?? 0),
                ':m_tot'        => (float)($data['INV1_MTOTALAMOUNT'] ?? 0),
                ':m_gtot'       => (float)($data['INV1_MGRANDTOTALAMOUNT'] ?? 0),
                ':fob_val'      => trim($data['FOB_VALUE'] ?? ''),
                ':rem1'         => trim($data['INV1_REMARK1'] ?? ''),
                ':rem2'         => trim($data['INV1_REMARK2'] ?? ''),
                ':rem3'         => trim($data['INV1_REMARK3'] ?? ''),
                ':rem4'         => trim($data['INV1_REMARK4'] ?? ''),
                ':rem5'         => trim($data['INV1_REMARK5'] ?? ''),
                ':rem6'         => trim($data['INV1_REMARK6'] ?? ''),
                ':rem7'         => trim($data['INV1_REMARK7'] ?? ''),
                ':mc1'          => trim($data['INV1_MCREMARK1'] ?? ''),
                ':mc2'          => trim($data['INV1_MCREMARK2'] ?? ''),
                ':mc3'          => trim($data['INV1_MCREMARK3'] ?? ''),
                ':mc4'          => trim($data['INV1_MCREMARK4'] ?? ''),
                ':mc5'          => trim($data['INV1_MCREMARK5'] ?? ''),
                ':mc6'          => trim($data['INV1_MCREMARK6'] ?? ''),
                ':mc7'          => trim($data['INV1_MCREMARK7'] ?? ''),
                ':mc8'          => trim($data['INV1_MCREMARK8'] ?? ''),
                ':op_user'      => $userId
            ]);

            if ($bookNo !== '') {
                $this->conn->prepare("UPDATE BOOKSHIP1 SET INVOICE_NO = :inv_no WHERE BOOK_NO = :book_no")
                           ->execute([':inv_no' => $invoiceNo, ':book_no' => $bookNo]);
            }

            $this->conn->commit();
            return ['status' => 'success', 'invoice_no' => $invoiceNo, 'message' => "สร้างใบแจ้งหนี้ $invoiceNo เรียบร้อยแล้ว"];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateInvoiceHeading($data, $userId) {
        try {
            $invoiceNo = trim($data['INVOICE_NO'] ?? '');
            $head = $this->assertInvoiceOpen($invoiceNo);

            $this->conn->beginTransaction();

            $cust = $this->getCustomerInfo($head['CSTMSPPL_ID']);
            $isLocal = (trim($cust['CSTMSPPL_LOCATION'] ?? '') === 'L');
            $newBookNo = $isLocal ? '' : trim($data['BOOK_NO'] ?? '');
            $oldBookNo = trim($head['BOOK_NO'] ?? '');

            if ($newBookNo !== '' && $newBookNo !== $oldBookNo) {
                $this->getBookingDetail($newBookNo, $head['CSTMSPPL_ID'], $invoiceNo);
            }

            $sql = "UPDATE INVCPRCS1 SET
                        INV1_MATERIALINPRN = :mat_in_prn, ACCOUNT_CODE = :acc_code,
                        INVOICE_DATE = :inv_date, SHIPMENT_DATE = :ship_date, BOOK_NO = :book_no,
                        PAYMENT_ID = :pay_id, SHIPMENT_ID = :ship_id, CURRENCY_ID = :curr_id,
                        BANK_ID = :bank_id, LETTER_CREDIT = :lc_no, INV1_SHIPPEDBY = :shipped_by,
                        INV1_SHIPPINGMARK = :sm0, INV1_SHIPPINGMARK1 = :sm1, INV1_SHIPPINGMARK2 = :sm2,
                        INV1_SHIPPINGMARK3 = :sm3, INV1_SHIPPINGMARK4 = :sm4, INV1_SHIPPINGMARK5 = :sm5,
                        INV1_SHIPPINGMARK6 = :sm6, INV1_SHIPPINGMARK7 = :sm7,
                        INV1_PORTLOADING = :port_load, INV1_PORTDISCHARGE = :port_dis,
                        INV1_PLACEDESTINATION = :place_dest, INV1_COUNTRYDESTINATION = :country_dest,
                        INV1_TAXPERCENT = :tax_pct, INV1_CERTIFICATE = :cert, INV1_DISCOUNT = :disc,
                        INV1_TRANSPORTATION = :trans, INV1_OVERSEAFREIGHT = :freight, INV1_INSURANCE = :insur,
                        INV1_MTOTALAMOUNT = :m_tot, INV1_MGRANDTOTALAMOUNT = :m_gtot, FOB_VALUE = :fob_val,
                        INV1_REMARK1 = :rem1, INV1_REMARK2 = :rem2, INV1_REMARK3 = :rem3, INV1_REMARK4 = :rem4,
                        INV1_REMARK5 = :rem5, INV1_REMARK6 = :rem6, INV1_REMARK7 = :rem7,
                        INV1_MCREMARK1 = :mc1, INV1_MCREMARK2 = :mc2, INV1_MCREMARK3 = :mc3, INV1_MCREMARK4 = :mc4,
                        INV1_MCREMARK5 = :mc5, INV1_MCREMARK6 = :mc6, INV1_MCREMARK7 = :mc7, INV1_MCREMARK8 = :mc8,
                        INV1_UPDATE = :upd_user, INV1_UPDATEDATE = GETDATE()
                    WHERE INVOICE_NO = :inv_no";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':mat_in_prn'   => trim($data['INV1_MATERIALINPRN'] ?? ''),
                ':acc_code'     => trim($data['ACCOUNT_CODE'] ?? ''),
                ':inv_date'     => $data['INVOICE_DATE'],
                ':ship_date'    => $data['SHIPMENT_DATE'],
                ':book_no'      => $newBookNo,
                ':pay_id'       => trim($data['PAYMENT_ID'] ?? '00'),
                ':ship_id'      => trim($data['SHIPMENT_ID'] ?? '000'),
                ':curr_id'      => trim($data['CURRENCY_ID'] ?? 'BAHT'),
                ':bank_id'      => trim($data['BANK_ID'] ?? '00'),
                ':lc_no'        => strtoupper(trim($data['LETTER_CREDIT'] ?? '')),
                ':shipped_by'   => strtoupper(trim($data['INV1_SHIPPEDBY'] ?? '')),
                ':sm0'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK'] ?? ''),
                ':sm1'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK1'] ?? ''),
                ':sm2'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK2'] ?? ''),
                ':sm3'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK3'] ?? ''),
                ':sm4'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK4'] ?? ''),
                ':sm5'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK5'] ?? ''),
                ':sm6'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK6'] ?? ''),
                ':sm7'          => $newBookNo === '' ? '' : trim($data['INV1_SHIPPINGMARK7'] ?? ''),
                ':port_load'    => $newBookNo === '' ? '000' : trim($data['INV1_PORTLOADING'] ?? '000'),
                ':port_dis'     => $newBookNo === '' ? '000' : trim($data['INV1_PORTDISCHARGE'] ?? '000'),
                ':place_dest'   => $newBookNo === '' ? '000' : trim($data['INV1_PLACEDESTINATION'] ?? '000'),
                ':country_dest' => $newBookNo === '' ? '000' : trim($data['INV1_COUNTRYDESTINATION'] ?? '000'),
                ':tax_pct'      => (int)($data['INV1_TAXPERCENT'] ?? 0),
                ':cert'         => (float)($data['INV1_CERTIFICATE'] ?? 0),
                ':disc'         => (float)($data['INV1_DISCOUNT'] ?? 0),
                ':trans'        => (float)($data['INV1_TRANSPORTATION'] ?? 0),
                ':freight'      => (float)($data['INV1_OVERSEAFREIGHT'] ?? 0),
                ':insur'        => (float)($data['INV1_INSURANCE'] ?? 0),
                ':m_tot'        => (float)($data['INV1_MTOTALAMOUNT'] ?? 0),
                ':m_gtot'       => (float)($data['INV1_MGRANDTOTALAMOUNT'] ?? 0),
                ':fob_val'      => trim($data['FOB_VALUE'] ?? ''),
                ':rem1'         => trim($data['INV1_REMARK1'] ?? ''),
                ':rem2'         => trim($data['INV1_REMARK2'] ?? ''),
                ':rem3'         => trim($data['INV1_REMARK3'] ?? ''),
                ':rem4'         => trim($data['INV1_REMARK4'] ?? ''),
                ':rem5'         => trim($data['INV1_REMARK5'] ?? ''),
                ':rem6'         => trim($data['INV1_REMARK6'] ?? ''),
                ':rem7'         => trim($data['INV1_REMARK7'] ?? ''),
                ':mc1'          => trim($data['INV1_MCREMARK1'] ?? ''),
                ':mc2'          => trim($data['INV1_MCREMARK2'] ?? ''),
                ':mc3'          => trim($data['INV1_MCREMARK3'] ?? ''),
                ':mc4'          => trim($data['INV1_MCREMARK4'] ?? ''),
                ':mc5'          => trim($data['INV1_MCREMARK5'] ?? ''),
                ':mc6'          => trim($data['INV1_MCREMARK6'] ?? ''),
                ':mc7'          => trim($data['INV1_MCREMARK7'] ?? ''),
                ':mc8'          => trim($data['INV1_MCREMARK8'] ?? ''),
                ':upd_user'     => $userId,
                ':inv_no'       => $invoiceNo
            ]);

            if ($newBookNo !== '') {
                $this->conn->prepare("UPDATE BOOKSHIP1 SET INVOICE_NO = :inv_no WHERE BOOK_NO = :book_no")
                           ->execute([':inv_no' => $invoiceNo, ':book_no' => $newBookNo]);
                $this->conn->prepare("UPDATE INVCPRCS3 SET BOOK_NO = :book_no WHERE INVOICE_NO = :inv_no")
                           ->execute([':book_no' => $newBookNo, ':inv_no' => $invoiceNo]);
            } else {
                $this->conn->prepare("UPDATE INVCPRCS3 SET BOOK_NO = '' WHERE INVOICE_NO = :inv_no")
                           ->execute([':inv_no' => $invoiceNo]);
            }
            if ($newBookNo !== $oldBookNo && $oldBookNo !== '') {
                $this->conn->prepare("UPDATE BOOKSHIP1 SET INVOICE_NO = '' WHERE BOOK_NO = :old_book")
                           ->execute([':old_book' => $oldBookNo]);
            }
            $this->conn->prepare("UPDATE INVCPRCS3 SET SHIPMENT_DATE = :ship_date WHERE INVOICE_NO = :inv_no")
                       ->execute([':ship_date' => $data['SHIPMENT_DATE'], ':inv_no' => $invoiceNo]);

            if (!empty($data['RECAL_FOB']) && (int)$data['RECAL_FOB'] === 1) {
                $netWeight = (float)$head['INV1_NETWEIGHT'];
                $overseaFreight = (float)($data['INV1_OVERSEAFREIGHT'] ?? 0);
                if ($netWeight > 0) {
                    $freightPerUnit = round($overseaFreight / $netWeight, 5);
                    $stmtItems = $this->conn->prepare("SELECT INVOICE_ITEM, INV2_INVOICEPRICE FROM INVCPRCS2 WHERE INVOICE_NO = :inv_no");
                    $stmtItems->execute([':inv_no' => $invoiceNo]);
                    while ($it = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
                        $fobPrice = round((float)$it['INV2_INVOICEPRICE'] - $freightPerUnit, 5);
                        $this->conn->prepare("UPDATE INVCPRCS2 SET INV2_FOBPRICE = :fob, INV2_FREIGHTPERUNIT = :frt WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item")
                                   ->execute([':fob' => $fobPrice, ':frt' => $freightPerUnit, ':inv' => $invoiceNo, ':item' => $it['INVOICE_ITEM']]);
                        $this->conn->prepare("UPDATE INVCPRCS3 SET INV3_FOBPRICE = :fob, INV3_FREIGHTPERUNIT = :frt WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item")
                                   ->execute([':fob' => $fobPrice, ':frt' => $freightPerUnit, ':inv' => $invoiceNo, ':item' => $it['INVOICE_ITEM']]);
                    }
                }
            }

            $this->conn->commit();
            return ['status' => 'success', 'message' => "บันทึกการแก้ไขหัวเอกสาร $invoiceNo เรียบร้อยแล้ว"];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // 5. ITEM DETAIL (LOOKUP SALE ORDER WITH TOP 200 & SERVER-SIDE SEARCH)
    // =========================================================================
    public function getAvailableSaleOrders($invoiceNo, $search = '', $category = '', $prodId = '') {
        $head = $this->assertInvoiceOpen($invoiceNo);
        $isMatV = (substr($invoiceNo, 0, 6) === 'MAT-V-');
        $catCond = $isMatV ? "AND B.ORDER_CATEGORY = 'OVMI'" : "AND B.ORDER_CATEGORY <> 'OVMI'";

        $custId = trim($head['CSTMSPPL_ID']);
        $search = trim($search);
        $category = trim($category);
        $prodId = trim($prodId);

        // 1. ดึงตัวเลือก Category และ Product ID ทั้งหมดที่เปิดอยู่ของลูกค้ารายนี้
        $sqlMeta = "SELECT DISTINCT B.ORDER_CATEGORY, A.PRODUCT_ID
                    FROM CSTMORDR2 A
                    INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                    WHERE B.CSTMSPPL_ID = :cust_id
                      {$catCond}
                      AND A.CTM2_STATUS = 'OP'
                      AND (ISNULL(A.CTM2_INVOICEWEIGHT, 0) < (ISNULL(A.CTM2_ORDERWEIGHT, 0) + ((ISNULL(A.CTM2_ORDERWEIGHT, 0) * ISNULL(A.CTM2_MAXTOLERANCE, 0)) / 100.0) + ISNULL(A.CTM2_REPLACEWEIGHT, 0)))";
        $stmtMeta = $this->conn->prepare($sqlMeta);
        $stmtMeta->execute([':cust_id' => $custId]);
        $metaRows = $stmtMeta->fetchAll(PDO::FETCH_ASSOC);

        $categories = [];
        $products = [];
        foreach ($metaRows as $m) {
            $c = trim($m['ORDER_CATEGORY'] ?? '');
            $p = trim($m['PRODUCT_ID'] ?? '');
            if ($c !== '' && !in_array($c, $categories)) $categories[] = $c;
            if ($p !== '' && !in_array($p, $products)) $products[] = $p;
        }
        sort($categories);
        sort($products);

        // 2. สร้างเงื่อนไขค้นหาฝั่ง SQL Server (ดึง TOP 200 รายการล่าสุด)
        $params = [':cust_id' => $custId];
        $extraWhere = "";

        if ($category !== '') {
            $extraWhere .= " AND B.ORDER_CATEGORY = :cat_filter";
            $params[':cat_filter'] = $category;
        }
        if ($prodId !== '') {
            $extraWhere .= " AND A.PRODUCT_ID = :prod_filter";
            $params[':prod_filter'] = $prodId;
        }
        if ($search !== '') {
            $kw = '%' . $search . '%';
            $extraWhere .= " AND (
                A.SALEORDER_NO LIKE :kw1
                OR B.CTM1_PONO LIKE :kw2
                OR A.ALLOY LIKE :kw3
                OR A.TEMPER LIKE :kw4
                OR CAST(A.THICKNESS AS VARCHAR(20)) LIKE :kw5
                OR CAST(A.WIDTH AS VARCHAR(20)) LIKE :kw6
                OR CAST(A.LENGTH AS VARCHAR(20)) LIKE :kw7
                OR A.CTM2_CUSTOMERPARTNO LIKE :kw8
            )";
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
            $params[':kw5'] = $kw;
            $params[':kw6'] = $kw;
            $params[':kw7'] = $kw;
            $params[':kw8'] = $kw;
        }

        $sql = "SELECT TOP 200
                       A.SALEORDER_NO, A.SALEORDER_ITEM, B.ORDER_CATEGORY, B.CTM1_PONO, B.SALEORDER_DATE,
                       A.PRODUCT_ID, A.PRODUCT_MODEL, A.ALLOY, A.TEMPER, A.SURFACE_GRADE, A.METALLURGICAL_GRADE,
                       A.THICKNESS, A.WIDTH, A.LENGTH, A.CTM2_DESCRIPTION, A.CTM2_CUSTOMERPARTNO,
                       A.CTM2_ORDERWEIGHT, A.CTM2_ORDERPIECE, A.CTM2_REPLACEWEIGHT, A.CTM2_REPLACEPIECE,
                       A.CTM2_MAXTOLERANCE, A.CTM2_INVOICEWEIGHT, A.CTM2_INVOICEPIECE, A.CTM2_ACTUALPRICE, A.MATERIAL_IN
                FROM CSTMORDR2 A
                INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                WHERE B.CSTMSPPL_ID = :cust_id
                  {$catCond}
                  {$extraWhere}
                  AND A.CTM2_STATUS = 'OP'
                  AND (ISNULL(A.CTM2_INVOICEWEIGHT, 0) < (ISNULL(A.CTM2_ORDERWEIGHT, 0) + ((ISNULL(A.CTM2_ORDERWEIGHT, 0) * ISNULL(A.CTM2_MAXTOLERANCE, 0)) / 100.0) + ISNULL(A.CTM2_REPLACEWEIGHT, 0)))
                ORDER BY B.SALEORDER_DATE DESC, A.SALEORDER_NO DESC, A.SALEORDER_ITEM ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows'       => $rows,
            'categories' => $categories,
            'products'   => $products
        ];
    }

    public function addInvoiceItem($invoiceNo, $soNo, $soItem) {
        try {
            $head = $this->assertInvoiceOpen($invoiceNo);
            $this->conn->beginTransaction();

            $res = $this->insertSingleInvoiceItem($head, $soNo, $soItem);

            $this->conn->commit();
            return [
                'status'       => 'success',
                'invoice_item' => $res['invoice_item'],
                'warning'      => $res['warning'],
                'message'      => "เพิ่มรายการ Item {$res['invoice_item']} เรียบร้อยแล้ว"
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function addMultipleInvoiceItems($invoiceNo, $soItemsList) {
        if (empty($soItemsList) || !is_array($soItemsList)) {
            throw new Exception("กรุณาเลือกรายการ Sale Order อย่างน้อย 1 รายการ");
        }

        try {
            $head = $this->assertInvoiceOpen($invoiceNo);
            $this->conn->beginTransaction();

            $warnings = [];
            $lastItem = '';
            $count = 0;

            foreach ($soItemsList as $pair) {
                $soNo = trim($pair['so_no'] ?? '');
                $soItem = trim($pair['so_item'] ?? '');
                if ($soNo === '' || $soItem === '') continue;

                $head = $this->assertInvoiceOpen($invoiceNo);
                $res = $this->insertSingleInvoiceItem($head, $soNo, $soItem);
                $lastItem = $res['invoice_item'];
                $count++;

                if (!empty($res['warning']) && !in_array($res['warning'], $warnings)) {
                    $warnings[] = $res['warning'];
                }
            }

            $this->conn->commit();
            return [
                'status'            => 'success',
                'last_invoice_item' => $lastItem,
                'warning'           => implode("\n", $warnings),
                'message'           => "เพิ่มรายการสินค้าจำนวน {$count} รายการเรียบร้อยแล้ว"
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    private function insertSingleInvoiceItem($head, $soNo, $soItem) {
        $invoiceNo = trim($head['INVOICE_NO']);

        // A.* ดึงคอลัมน์ทั้งหมดจาก CSTMORDR2 (รวม PRICE_PERIOD) ส่วน B ดึงจาก CSTMORDR1
        $sqlSO = "SELECT A.*, B.ORDER_CATEGORY, B.CSTMSPPL_ID, B.CTM1_PLACEDESTINATION
                  FROM CSTMORDR2 A
                  INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                  WHERE A.SALEORDER_NO = :so_no AND A.SALEORDER_ITEM = :so_item";
        $stmtSO = $this->conn->prepare($sqlSO);
        $stmtSO->execute([':so_no' => trim($soNo), ':so_item' => trim($soItem)]);
        $so = $stmtSO->fetch(PDO::FETCH_ASSOC);
        if (!$so) {
            throw new Exception("ไม่พบรายการ Sale Order $soNo-$soItem");
        }

        $isMatV = (substr($invoiceNo, 0, 6) === 'MAT-V-');
        if ($isMatV && trim($so['ORDER_CATEGORY']) !== 'OVMI') {
            throw new Exception("Invoice ประเภท MAT-V- ต้องเลือกรายการที่มี Order Category เป็น OVMI เท่านั้น");
        }
        if (!$isMatV && trim($so['ORDER_CATEGORY']) === 'OVMI') {
            throw new Exception("Invoice ประเภทนี้ไม่สามารถเลือกรายการ OVMI ได้");
        }

        $stmtMax = $this->conn->prepare("SELECT MAX(CAST(INVOICE_ITEM AS INT)) AS MAX_ITEM FROM INVCPRCS2 WHERE INVOICE_NO = :inv_no");
        $stmtMax->execute([':inv_no' => $invoiceNo]);
        $nextItemNum = ((int)($stmtMax->fetch(PDO::FETCH_ASSOC)['MAX_ITEM'] ?? 0)) + 1;
        if ($nextItemNum > 100) {
            throw new Exception("จำนวนรายการใน Invoice เต็ม 100 รายการแล้ว");
        }
        $invoiceItem = str_pad($nextItemNum, 3, '0', STR_PAD_LEFT);

        $exchangeRate = 0;
        $stmtEx = $this->conn->prepare("SELECT EXCHANGE_RATE FROM PRICMSTR1 WHERE PRICE_PERIOD = :pp AND CSTMSPPL_ID = :cust");
        $stmtEx->execute([':pp' => trim($so['PRICE_PERIOD'] ?? ''), ':cust' => trim($head['CSTMSPPL_ID'])]);
        if ($exRow = $stmtEx->fetch(PDO::FETCH_ASSOC)) {
            $exchangeRate = (float)($exRow['EXCHANGE_RATE'] ?? 0);
        }

        $placeDesc = '';
        $stmtPl = $this->conn->prepare("SELECT DESCRIPTION FROM PLCEMSTR1 WHERE PLACE_ID = :pid");
        $stmtPl->execute([':pid' => trim($so['CTM1_PLACEDESTINATION'] ?? '')]);
        if ($plRow = $stmtPl->fetch(PDO::FETCH_ASSOC)) {
            $placeDesc = trim($plRow['DESCRIPTION'] ?? '');
        }

        $prodId = trim($so['PRODUCT_ID'] ?? '');
        $uom = ($prodId === 'CO') ? 'COIL' : 'PALLET';
        $matIn = trim($so['MATERIAL_IN'] ?? '') !== '' ? trim($so['MATERIAL_IN']) : trim($head['INV1_MATERIALIN'] ?? '');

        $warning = '';
        if (trim($head['INV1_MATERIALIN'] ?? '') !== '' && $matIn !== '' && trim($head['INV1_MATERIALIN']) !== $matIn) {
            $warning = "คำเตือน: Material In ของหัวบิล ({$head['INV1_MATERIALIN']}) ไม่ตรงกับ Sale Order ({$matIn})";
        }

        $sqlIns = "INSERT INTO INVCPRCS2 (
                        INVOICE_NO, INVOICE_ITEM, DEBITNOTE_NO, DEBITNOTE_ITEM, SALEORDER_NO, SALEORDER_ITEM, CSTMSPPL_ID,
                        ORDER_CATEGORY, INV2_PONO, INV2_POITEM, CUSTOMS_CODE, INV2_MATERIALIN,
                        INV2_CUSTOMERORDER, INV2_CUSTOMERSALEORDER, INV2_ENDCUSTOMERNAME, INV2_ENDCUSTOMERPO, INV2_PLACEDESTINATION,
                        PRODUCT_ID, PRODUCT_MODEL, ALLOY, TEMPER, METALLURGICAL_GRADE, SURFACE_GRADE,
                        THICKNESS, WIDTH, LENGTH, INV2_UOMDIMENSION, INV2_UOMWEIGHT,
                        INV2_CUSTOMERPARTNO, INV2_DESCRIPTION, INV2_SKIDNO, INV2_UOM,
                        INV2_PACKAGE, INV2_GROSSWEIGHT, INV2_NETWEIGHT, INV2_NETPIECE,
                        INV2_EXCHANGERATE, INV2_FOBPRICE, INV2_INVOICEPRICE, INV2_FREIGHTPERUNIT, INV2_DEBITPRICE,
                        IN2U_THICKNESS, IN2U_WIDTH, IN2U_LENGTH, IN2U_UOMDIMENSION, IN2U_UOMWEIGHT,
                        IN2U_GROSSWEIGHT, IN2U_NETWEIGHT, IN2U_NETPIECE
                   ) VALUES (
                        :inv_no, :inv_item, '-', '-', :so_no, :so_item, :cust_id,
                        :ord_cat, :po_no, :po_item, :cust_code, :mat_in,
                        :cust_ord, :cust_so, :end_cust, :end_po, :place_dest,
                        :prod_id, :prod_model, :alloy, :temper, :mg, :sg,
                        :thick, :width, :length, :uom_dim, :uom_wt,
                        :cust_part, :descr, '', :uom,
                        0, 0, 0, 0,
                        :ex_rate, 0, :act_price, :frt_unit, :deb_price,
                        :u_thick, :u_width, :u_length, :u_uom_dim, :u_uom_wt,
                        0, 0, 0
                   )";

        $this->conn->prepare($sqlIns)->execute([
            ':inv_no'     => $invoiceNo,
            ':inv_item'   => $invoiceItem,
            ':so_no'      => trim($soNo),
            ':so_item'    => trim($soItem),
            ':cust_id'    => trim($head['CSTMSPPL_ID']),
            ':ord_cat'    => trim($so['ORDER_CATEGORY'] ?? ''),
            ':po_no'      => trim($so['CTM2_PONO'] ?? ''),
            ':po_item'    => trim($so['CTM2_POITEM'] ?? ''),
            ':cust_code'  => trim($so['CUSTOMS_CODE'] ?? ''),
            ':mat_in'     => $matIn,
            ':cust_ord'   => trim($so['CTM2_CUSTOMERORDER'] ?? ''),
            ':cust_so'    => trim($so['CTM2_CUSTOMERSALEORDER'] ?? ''),
            ':end_cust'   => trim($so['CTM2_ENDCUSTOMERNAME'] ?? ''),
            ':end_po'     => trim($so['CTM2_ENDCUSTOMERPO'] ?? ''),
            ':place_dest' => $placeDesc,
            ':prod_id'    => $prodId,
            ':prod_model' => trim($so['PRODUCT_MODEL'] ?? ''),
            ':alloy'      => trim($so['ALLOY'] ?? ''),
            ':temper'     => trim($so['TEMPER'] ?? ''),
            ':mg'         => trim($so['METALLURGICAL_GRADE'] ?? ''),
            ':sg'         => trim($so['SURFACE_GRADE'] ?? ''),
            ':thick'      => (float)($so['THICKNESS'] ?? 0),
            ':width'      => (float)($so['WIDTH'] ?? 0),
            ':length'     => (float)($so['LENGTH'] ?? 0),
            ':uom_dim'    => trim($so['CTM2_UOMDIMENSION'] ?? ''),
            ':uom_wt'     => trim($so['CTM2_UOMWEIGHT'] ?? ''),
            ':cust_part'  => trim($so['CTM2_CUSTOMERPARTNO'] ?? ''),
            ':descr'      => trim($so['CTM2_DESCRIPTION'] ?? ''),
            ':uom'        => $uom,
            ':ex_rate'    => $exchangeRate,
            ':act_price'  => (float)($so['CTM2_ACTUALPRICE'] ?? 0),
            ':frt_unit'   => (float)($so['CTM2_FREIGHTPERUNIT'] ?? 0),
            ':deb_price'  => (float)($so['CTM2_ACTUALPRICE'] ?? 0),
            ':u_thick'    => (float)($so['CT2U_THICKNESS'] ?? 0),
            ':u_width'    => (float)($so['CT2U_WIDTH'] ?? 0),
            ':u_length'   => (float)($so['CT2U_LENGTH'] ?? 0),
            ':u_uom_dim'  => trim($so['CT2U_UOMDIMENSION'] ?? ''),
            ':u_uom_wt'   => trim($so['CT2U_UOMWEIGHT'] ?? '')
        ]);

        if (substr(trim($head['CSTMSPPL_ID']), 0, 3) === 'MIL') {
            $this->conn->prepare("UPDATE INVCPRCS1 SET INV1_MATERIALIN = :mat_in, INV1_EXCHANGERATE = :ex WHERE INVOICE_NO = :inv")
                       ->execute([':mat_in' => $matIn, ':ex' => $exchangeRate, ':inv' => $invoiceNo]);
        } else {
            $this->conn->prepare("UPDATE INVCPRCS1 SET INV1_MATERIALIN = :mat_in WHERE INVOICE_NO = :inv")
                       ->execute([':mat_in' => $matIn, ':inv' => $invoiceNo]);
        }

        if (trim($head['INV1_MATERIALINPRN'] ?? '') === '' && $matIn !== '') {
            $this->conn->prepare("UPDATE INVCPRCS1 SET INV1_MATERIALINPRN = :mat_in WHERE INVOICE_NO = :inv AND ISNULL(INV1_MATERIALINPRN, '') = ''")
                       ->execute([':mat_in' => $matIn, ':inv' => $invoiceNo]);
        }

        return ['invoice_item' => $invoiceItem, 'warning' => $warning];
    }

    public function updateInvoiceItem($data) {
        $invoiceNo = trim($data['INVOICE_NO'] ?? '');
        $invoiceItem = trim($data['INVOICE_ITEM'] ?? '');
        $this->assertInvoiceOpen($invoiceNo);

        $sql = "UPDATE INVCPRCS2 SET
                    INV2_FOBPRICE = :fob,
                    INV2_UOM = :uom,
                    INV2_DESCRIPTION = :descr,
                    INV2_SKIDNO = :skid
                WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item";
        $this->conn->prepare($sql)->execute([
            ':fob'   => (float)($data['INV2_FOBPRICE'] ?? 0),
            ':uom'   => strtoupper(trim($data['INV2_UOM'] ?? '')),
            ':descr' => trim($data['INV2_DESCRIPTION'] ?? ''),
            ':skid'  => trim($data['INV2_SKIDNO'] ?? ''),
            ':inv'   => $invoiceNo,
            ':item'  => $invoiceItem
        ]);
        return ['status' => 'success', 'message' => "อัปเดตข้อมูล Item $invoiceItem เรียบร้อยแล้ว"];
    }

    public function deleteInvoiceItem($invoiceNo, $invoiceItem) {
        $this->assertInvoiceOpen($invoiceNo);

        $stmtCheck = $this->conn->prepare("SELECT COUNT(*) AS CNT FROM INVCPRCS3 WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item");
        $stmtCheck->execute([':inv' => trim($invoiceNo), ':item' => trim($invoiceItem)]);
        if ((int)$stmtCheck->fetch(PDO::FETCH_ASSOC)['CNT'] > 0) {
            throw new Exception("ไม่สามารถลบ Item $invoiceItem ได้ เนื่องจากยังมีรายการตัวสินค้า (Product Detail) ค้างอยู่ กรุณาลบตัวสินค้าออกก่อน");
        }

        $this->conn->prepare("DELETE FROM INVCPRCS2 WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item")
                   ->execute([':inv' => trim($invoiceNo), ':item' => trim($invoiceItem)]);
        return ['status' => 'success', 'message' => "ลบรายการ Item $invoiceItem เรียบร้อยแล้ว"];
    }

    // =========================================================================
    // 6. PRODUCT DETAIL (MULTI-SELECT STOCK LOOKUP, VALIDATE, DEDUCT & RESTORE)
    // =========================================================================
    public function getAvailableStockProducts($invoiceNo, $invoiceItem) {
        $this->assertInvoiceOpen($invoiceNo);

        $stmtIt = $this->conn->prepare("SELECT * FROM INVCPRCS2 WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item");
        $stmtIt->execute([':inv' => trim($invoiceNo), ':item' => trim($invoiceItem)]);
        $item = $stmtIt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new Exception("ไม่พบข้อมูล Invoice Item $invoiceItem");
        }

        $prodId = trim($item['PRODUCT_ID']);
        $soNo = trim($item['SALEORDER_NO']);
        $soItem = trim($item['SALEORDER_ITEM']);

        if ($prodId === 'CO') {
            $sql = "SELECT A.PRODUCT_NO, A.PACK_STATUS, A.PACK_NETWEIGHT, A.PACK_PACKAGEWEIGHT,
                           1 AS PACK_NETPIECE, B.COIL_NO, B.PRODUCT_REFERENCE, B.CSTMSPPL_ID AS PROD_OWNER,
                           B.MATERIAL_IN, B.PRODUCT_ID, 'COIL' AS PRODUCT_MODEL, 0 AS AREA_SIZE,
                           B.ALLOY, B.TEMPER, B.SURFACE_GRADE, B.METALLURGICAL_GRADE,
                           B.THICKNESS, B.WIDTH, 0 AS LENGTH, 0 AS WEIGHT_PIECE,
                           B.COIL_UOMDIMENSION AS UOM_DIM, B.COIL_UOMWEIGHT AS UOM_WT,
                           B.COIL_DIMENSIONWIDTH AS DIM_W, B.COIL_DIMENSIONLENGTH AS DIM_L, B.COIL_DIMENSIONHIGH AS DIM_H,
                           B.JOB_ORDER
                    FROM PACKPROD1 A
                    INNER JOIN COILPROD1 B ON A.PRODUCT_NO = B.COIL_NO
                    WHERE A.SALEORDER_NO = :so_no AND A.SALEORDER_ITEM = :so_item
                      AND A.PACK_STATUS IN ('ST', 'DR')
                    ORDER BY A.PRODUCT_NO ASC";
        } else {
            $sql = "SELECT A.PRODUCT_NO, A.PACK_STATUS, A.PACK_NETWEIGHT, A.PACK_PACKAGEWEIGHT,
                           A.PACK_NETPIECE, B.COIL_NO, '' AS PRODUCT_REFERENCE, '' AS PROD_OWNER,
                           B.MATERIAL_IN, B.PRODUCT_ID, B.PRODUCT_MODEL, B.AREA_SIZE,
                           B.ALLOY, J.TEMPER, B.SURFACE_GRADE, B.METALLURGICAL_GRADE,
                           B.THICKNESS, B.WIDTH, B.LENGTH, B.WEIGHT_PIECE,
                           B.CRSH_UOMDIMENSION AS UOM_DIM, B.CRSH_UOMWEIGHT AS UOM_WT,
                           B.CRSH_DIMENSIONWIDTH AS DIM_W, B.CRSH_DIMENSIONLENGTH AS DIM_L, B.CRSH_DIMENSIONHIGH AS DIM_H,
                           B.JOB_ORDER
                    FROM PACKPROD1 A
                    INNER JOIN CRSHPROD1 B ON A.PRODUCT_NO = B.PRODUCT_NO
                    LEFT JOIN JOBORDER1 J ON B.JOB_ORDER = J.JOB_ORDER
                    WHERE A.SALEORDER_NO = :so_no AND A.SALEORDER_ITEM = :so_item
                      AND A.PACK_STATUS IN ('ST', 'DR')
                    ORDER BY A.PRODUCT_NO ASC";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':so_no' => $soNo, ':so_item' => $soItem]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $specCheck = $this->checkSpecification($item, $r);
            $r['SPEC_VALID'] = $specCheck['valid'];
            $r['SPEC_MESSAGE'] = $specCheck['message'];
        }
        return $rows;
    }

    private function checkSpecification($item, $prod) {
        if (trim($prod['MATERIAL_IN'] ?? '') === '') {
            return ['valid' => false, 'message' => 'ไม่พบข้อมูล Material In กรุณาติดต่อแผนก Planning / MIS'];
        }
        if (trim($prod['PRODUCT_ID'] ?? '') !== trim($item['PRODUCT_ID'] ?? '')) {
            return ['valid' => false, 'message' => 'Product ID ไม่ตรงกัน'];
        }
        if (strtoupper(trim($prod['ALLOY'] ?? '')) !== strtoupper(trim($item['ALLOY'] ?? ''))) {
            return ['valid' => false, 'message' => 'Alloy ไม่ตรงกัน'];
        }

        $prodId = trim($item['PRODUCT_ID']);
        if (!in_array($prodId, ['WR', 'RM'])) {
            if (strtoupper(trim($prod['TEMPER'] ?? '')) !== strtoupper(trim($item['TEMPER'] ?? ''))) {
                return ['valid' => false, 'message' => 'Temper ไม่ตรงกัน'];
            }
            if (strtoupper(trim($prod['SURFACE_GRADE'] ?? '')) !== strtoupper(trim($item['SURFACE_GRADE'] ?? ''))) {
                return ['valid' => false, 'message' => 'Surface Grade ไม่ตรงกัน'];
            }
            if (strtoupper(trim($prod['METALLURGICAL_GRADE'] ?? '')) !== strtoupper(trim($item['METALLURGICAL_GRADE'] ?? ''))) {
                return ['valid' => false, 'message' => 'Metallurgical Grade ไม่ตรงกัน'];
            }
            if (abs((float)$prod['THICKNESS'] - (float)$item['THICKNESS']) > 0.001) {
                return ['valid' => false, 'message' => 'Thickness เกินค่าเผื่อ (0.001)'];
            }
            if (abs((float)$prod['WIDTH'] - (float)$item['WIDTH']) > 0.001) {
                return ['valid' => false, 'message' => 'Width เกินค่าเผื่อ (0.001)'];
            }
            if (in_array($prodId, ['CC', 'SH', 'NC']) && abs((float)$prod['LENGTH'] - (float)$item['LENGTH']) > 0.001) {
                return ['valid' => false, 'message' => 'Length เกินค่าเผื่อ (0.001)'];
            }
        }
        return ['valid' => true, 'message' => 'OK'];
    }

    private function calcWeightPerPiece($prodId, $width, $length, $thickness, $areaSize, $netWeight, $netPiece) {
        if (in_array($prodId, ['SH', 'NC'])) {
            return ($thickness * $width * $length * 2.71) / 1000000.0;
        } elseif ($prodId === 'CC') {
            return $areaSize * $thickness * 2.71;
        } else {
            return $netPiece > 0 ? ($netWeight / $netPiece) : $netWeight;
        }
    }

    public function addInvoiceProducts($invoiceNo, $invoiceItem, $productNos, $skidNo = '00', $closeSaleOrder = false, $userId = 'WEB') {
        if (empty($productNos) || !is_array($productNos)) {
            throw new Exception("กรุณาเลือกสินค้าอย่างน้อย 1 รายการ");
        }

        try {
            $head = $this->assertInvoiceOpen($invoiceNo);
            $this->conn->beginTransaction();

            $sqlIt = "SELECT A.*, B.CTM2_ORDERWEIGHT, B.CTM2_ORDERPIECE, B.CTM2_REPLACEWEIGHT, B.CTM2_REPLACEPIECE,
                             B.CTM2_MAXTOLERANCE, B.CTM2_MINTOLERANCE, B.CTM2_INVOICEWEIGHT, B.CTM2_INVOICEPIECE,
                             B.CTM2_INVOICEBUNDLE, B.CTM2_WHOLEWEIGHT, B.CTM2_STATUS
                      FROM INVCPRCS2 A
                      INNER JOIN CSTMORDR2 B ON A.SALEORDER_NO = B.SALEORDER_NO AND A.SALEORDER_ITEM = B.SALEORDER_ITEM
                      WHERE A.INVOICE_NO = :inv AND A.INVOICE_ITEM = :item";
            $stmtIt = $this->conn->prepare($sqlIt);
            $stmtIt->execute([':inv' => $invoiceNo, ':item' => $invoiceItem]);
            $item = $stmtIt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                throw new Exception("ไม่พบรายการ Invoice Item $invoiceItem");
            }

            $convertRate = 1.0;
            if (trim($item['IN2U_UOMWEIGHT']) !== '' && trim($item['IN2U_UOMWEIGHT']) !== trim($item['INV2_UOMWEIGHT'])) {
                $stmtConv = $this->conn->prepare("SELECT CONVERT_RATE FROM UOMSMSTR1 WHERE UOM = :uom");
                $stmtConv->execute([':uom' => trim($item['IN2U_UOMWEIGHT'])]);
                if ($cRow = $stmtConv->fetch(PDO::FETCH_ASSOC)) {
                    $convertRate = (float)($cRow['CONVERT_RATE'] ?? 1.0);
                }
            }

            $dimConvertRate = 1.0;
            if (trim($item['IN2U_UOMDIMENSION']) !== '') {
                $stmtDim = $this->conn->prepare("SELECT CONVERT_RATE FROM UOMSMSTR1 WHERE UOM = :uom");
                $stmtDim->execute([':uom' => trim($item['IN2U_UOMDIMENSION'])]);
                if ($dRow = $stmtDim->fetch(PDO::FETCH_ASSOC)) {
                    $dimConvertRate = (float)($dRow['CONVERT_RATE'] ?? 1.0);
                }
            }

            $availableStock = $this->getAvailableStockProducts($invoiceNo, $invoiceItem);
            $stockMap = [];
            foreach ($availableStock as $st) {
                $stockMap[trim($st['PRODUCT_NO'])] = $st;
            }

            $prodId = trim($item['PRODUCT_ID']);
            $maxTol = (float)($item['CTM2_MAXTOLERANCE'] ?? 0);
            $totWeightQuota = (float)$item['CTM2_ORDERWEIGHT'] + (float)$item['CTM2_REPLACEWEIGHT'];
            $maxWeight = $totWeightQuota + (($totWeightQuota * $maxTol) / 100.0);

            $totPieceQuota = (float)$item['CTM2_ORDERPIECE'] + (float)$item['CTM2_REPLACEPIECE'];
            $maxPiece = $totPieceQuota + (($totPieceQuota * $maxTol) / 100.0);

            $accSOWeight = (float)$item['CTM2_INVOICEWEIGHT'];
            $accSOPiece  = (float)$item['CTM2_INVOICEPIECE'];
            $accSOBundle = (int)$item['CTM2_INVOICEBUNDLE'];

            $addPkg = 0;
            $addNetPiece = 0;
            $addNetWeight = 0;
            $addGrossWeight = 0;
            $addUNetPiece = 0;
            $addUNetWeight = 0;
            $addUGrossWeight = 0;
            $addAmount = 0;

            $skidClean = trim($skidNo) === '' ? '00' : trim($skidNo);
            $wholeWeight = ((int)($item['CTM2_WHOLEWEIGHT'] ?? 0) === 1);

            foreach ($productNos as $pNoRaw) {
                $pNo = trim($pNoRaw);
                if (!isset($stockMap[$pNo])) {
                    throw new Exception("สินค้า $pNo ไม่อยู่ในสถานะพร้อมเบิก (ST/DR) หรือถูกตัดสต็อกไปแล้ว");
                }
                $prod = $stockMap[$pNo];
                if (!$prod['SPEC_VALID']) {
                    throw new Exception("สินค้า $pNo ไม่ผ่านเงื่อนไขสเปก: " . $prod['SPEC_MESSAGE']);
                }

                $netWt = (float)$prod['PACK_NETWEIGHT'];
                $pkgWt = (float)$prod['PACK_PACKAGEWEIGHT'];
                $grossWt = $netWt + $pkgWt;
                $netPc = ($prodId === 'CO') ? 1 : (float)$prod['PACK_NETPIECE'];

                if (in_array($prodId, ['CC', 'SH', 'NC']) && ($accSOWeight + $netWt) > ($maxWeight + 0.01)) {
                    throw new Exception("น้ำหนักรวมเกินโควตาสูงสุดของ Sale Order (สูงสุด " . number_format($maxWeight, 2) . " kg)");
                }
                if ($prodId === 'CO' && ($accSOBundle + 1) > $maxPiece) {
                    throw new Exception("จำนวนม้วนรวมเกินโควตาสูงสุดของ Sale Order (สูงสุด " . number_format($maxPiece, 0) . " ม้วน)");
                }

                $uNetWt = $wholeWeight ? round($netWt * $convertRate, 0) : round($netWt * $convertRate, 2);
                $uGrossWt = $wholeWeight ? round($grossWt * $convertRate, 0) : round($grossWt * $convertRate, 2);
                $uNetPc = $netPc;

                $coilNo = ($prodId === 'CO' && trim($prod['PROD_OWNER']) !== 'MAT')
                          ? trim($prod['PRODUCT_REFERENCE'])
                          : trim($prod['COIL_NO']);

                $lengthVal = in_array($prodId, ['SH', 'NC']) ? (float)$prod['LENGTH'] : 0;
                $wtPerPc = (float)($prod['WEIGHT_PIECE'] ?? 0);
                if ($wtPerPc <= 0) {
                    $wtPerPc = $this->calcWeightPerPiece($prodId, (float)$prod['WIDTH'], $lengthVal, (float)$prod['THICKNESS'], (float)$prod['AREA_SIZE'], $netWt, $netPc);
                }

                $uDimW = $dimConvertRate > 0 ? round((float)$prod['DIM_W'] / $dimConvertRate, 0) : 0;
                $uDimL = $dimConvertRate > 0 ? round((float)$prod['DIM_L'] / $dimConvertRate, 0) : 0;
                $uDimH = $dimConvertRate > 0 ? round((float)$prod['DIM_H'] / $dimConvertRate, 0) : 0;

                // 1. Insert ลง INVCPRCS3
                $sqlIns3 = "INSERT INTO INVCPRCS3 (
                                INVOICE_NO, INVOICE_ITEM, SKID_NO, SHIPMENT_DATE, PRODUCT_NO,
                                DEBITNOTE_NO, DEBITNOTE_ITEM, CREDITNOTE_NO, CREDITNOTE_ITEM,
                                COIL_NO, BOOK_NO, CONTAINER_NO, CSTMSPPL_ID, SALEORDER_NO, SALEORDER_ITEM,
                                INV3_MATERIALIN, INV3_PONO, INV3_POITEM, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE,
                                ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, LENGTH,
                                INV3_UOMDIMENSION, INV3_UOMWEIGHT, INV3_DIMENSIONWIDTH, INV3_DIMENSIONLENGTH, INV3_DIMENSIONHIGH,
                                INV3_CUSTOMERPARTNO, INV3_UOM, INV3_PACKAGE, INV3_NETPIECE, INV3_NETWEIGHT, INV3_GROSSWEIGHT,
                                INV3_TAXPERCENT, INV3_FOBPRICE, INV3_INVOICEPRICE, INV3_FREIGHTPERUNIT, INV3_DEBITPRICE,
                                INV3_CREDITNETWEIGHT, INV3_CREDITPRICE,
                                IN3U_THICKNESS, IN3U_WIDTH, IN3U_LENGTH, IN3U_UOMDIMENSION,
                                IN3U_DIMENSIONWIDTH, IN3U_DIMENSIONLENGTH, IN3U_DIMENSIONHIGH,
                                IN3U_NETPIECE, IN3U_NETWEIGHT, IN3U_GROSSWEIGHT, IN3U_UOMWEIGHT,
                                INV3_OPERATOR, INV3_OPERATEDATE, INV3_STATUS
                            ) VALUES (
                                :inv_no, :inv_item, :skid_no, :ship_date, :prod_no,
                                '-', '-', '-', '-',
                                :coil_no, :book_no, '', :cust_id, :so_no, :so_item,
                                :mat_in, :po_no, :po_item, :prod_id, :prod_model, :area_size,
                                :alloy, :temper, :sg, :mg, :thick, :width, :length,
                                :uom_dim, :uom_wt, :dim_w, :dim_l, :dim_h,
                                :cust_part, :uom, 1, :net_pc, :net_wt, :gross_wt,
                                :tax_pct, :fob_price, :inv_price, :frt_unit, :deb_price,
                                0, 0,
                                :u_thick, :u_width, :u_length, :u_uom_dim,
                                :u_dim_w, :u_dim_l, :u_dim_h,
                                :u_net_pc, :u_net_wt, :u_gross_wt, :u_uom_wt,
                                :op_user, GETDATE(), 'OP'
                            )";

                $this->conn->prepare($sqlIns3)->execute([
                    ':inv_no'     => $invoiceNo,
                    ':inv_item'   => $invoiceItem,
                    ':skid_no'    => $skidClean,
                    ':ship_date'  => $head['SHIPMENT_DATE'],
                    ':prod_no'    => $pNo,
                    ':coil_no'    => $coilNo,
                    ':book_no'    => trim($head['BOOK_NO'] ?? ''),
                    ':cust_id'    => trim($head['CSTMSPPL_ID']),
                    ':so_no'      => trim($item['SALEORDER_NO']),
                    ':so_item'    => trim($item['SALEORDER_ITEM']),
                    ':mat_in'     => trim($prod['MATERIAL_IN']),
                    ':po_no'      => trim($item['INV2_PONO'] ?? ''),
                    ':po_item'    => trim($item['INV2_POITEM'] ?? ''),
                    ':prod_id'    => $prodId,
                    ':prod_model' => trim($prod['PRODUCT_MODEL'] ?? ''),
                    ':area_size'  => (float)($prod['AREA_SIZE'] ?? 0),
                    ':alloy'      => trim($prod['ALLOY'] ?? ''),
                    ':temper'     => trim($prod['TEMPER'] ?? ''),
                    ':sg'         => trim($prod['SURFACE_GRADE'] ?? ''),
                    ':mg'         => trim($prod['METALLURGICAL_GRADE'] ?? ''),
                    ':thick'      => (float)$prod['THICKNESS'],
                    ':width'      => (float)$prod['WIDTH'],
                    ':length'     => $lengthVal,
                    ':uom_dim'    => trim($prod['UOM_DIM'] ?? ''),
                    ':uom_wt'     => trim($prod['UOM_WT'] ?? ''),
                    ':dim_w'      => (int)$prod['DIM_W'],
                    ':dim_l'      => (int)$prod['DIM_L'],
                    ':dim_h'      => (int)$prod['DIM_H'],
                    ':cust_part'  => trim($item['INV2_CUSTOMERPARTNO'] ?? ''),
                    ':uom'        => trim($item['INV2_UOM'] ?? ''),
                    ':net_pc'     => $netPc,
                    ':net_wt'     => $netWt,
                    ':gross_wt'   => $grossWt,
                    ':tax_pct'    => (int)($head['INV1_TAXPERCENT'] ?? 0),
                    ':fob_price'  => (float)$item['INV2_FOBPRICE'],
                    ':inv_price'  => (float)$item['INV2_INVOICEPRICE'],
                    ':frt_unit'   => (float)$item['INV2_FREIGHTPERUNIT'],
                    ':deb_price'  => (float)$item['INV2_DEBITPRICE'],
                    ':u_thick'    => (float)$item['IN2U_THICKNESS'],
                    ':u_width'    => (float)$item['IN2U_WIDTH'],
                    ':u_length'   => (float)$item['IN2U_LENGTH'],
                    ':u_uom_dim'  => trim($item['IN2U_UOMDIMENSION'] ?? ''),
                    ':u_dim_w'    => (int)$uDimW,
                    ':u_dim_l'    => (int)$uDimL,
                    ':u_dim_h'    => (int)$uDimH,
                    ':u_net_pc'   => $uNetPc,
                    ':u_net_wt'   => $uNetWt,
                    ':u_gross_wt' => $uGrossWt,
                    ':u_uom_wt'   => trim($item['IN2U_UOMWEIGHT'] ?? ''),
                    ':op_user'    => $userId
                ]);

                // 2. ตัดสต็อกใน COILPROD1 / CRSHPROD1 และ PACKPROD1
                if (in_array($prodId, ['CO', 'OS'])) {
                    $this->conn->prepare("UPDATE COILPROD1 SET COIL_STATUS = 'IN' WHERE COIL_NO = :pno")->execute([':pno' => $pNo]);
                } else {
                    $this->conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = 'IN' WHERE PRODUCT_NO = :pno")->execute([':pno' => $pNo]);
                }
                $this->conn->prepare("UPDATE PACKPROD1 SET PACK_STATUS = 'IN' WHERE PRODUCT_NO = :pno")->execute([':pno' => $pNo]);

                $accSOWeight += $netWt;
                $accSOPiece  += $netPc;
                $accSOBundle += 1;

                $addPkg         += 1;
                $addNetPiece    += $netPc;
                $addNetWeight   += $netWt;
                $addGrossWeight += $grossWt;
                $addUNetPiece   += $uNetPc;
                $addUNetWeight  += $uNetWt;
                $addUGrossWeight+= $uGrossWt;
                $addAmount      += ($uNetWt * (float)$item['INV2_INVOICEPRICE']);
            }

            // 3. อัปเดตยอดสะสมระดับรายการ (INVCPRCS2)
            $this->conn->prepare("UPDATE INVCPRCS2 SET
                                    INV2_PACKAGE     = ISNULL(INV2_PACKAGE, 0) + :pkg,
                                    INV2_NETPIECE    = ISNULL(INV2_NETPIECE, 0) + :npc,
                                    INV2_GROSSWEIGHT = ISNULL(INV2_GROSSWEIGHT, 0) + :gwt,
                                    INV2_NETWEIGHT   = ISNULL(INV2_NETWEIGHT, 0) + :nwt,
                                    IN2U_NETPIECE    = ISNULL(IN2U_NETPIECE, 0) + :unpc,
                                    IN2U_GROSSWEIGHT = ISNULL(IN2U_GROSSWEIGHT, 0) + :ugwt,
                                    IN2U_NETWEIGHT   = ISNULL(IN2U_NETWEIGHT, 0) + :unwt
                                  WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item")
                       ->execute([
                           ':pkg' => $addPkg, ':npc' => $addNetPiece, ':gwt' => $addGrossWeight, ':nwt' => $addNetWeight,
                           ':unpc' => $addUNetPiece, ':ugwt' => $addUGrossWeight, ':unwt' => $addUNetWeight,
                           ':inv' => $invoiceNo, ':item' => $invoiceItem
                       ]);

            // 4. อัปเดตยอดสะสมระดับหัวบิล (INVCPRCS1)
            $this->conn->prepare("UPDATE INVCPRCS1 SET
                                    INV1_PACKAGE     = ISNULL(INV1_PACKAGE, 0) + :pkg,
                                    INV1_NETPIECE    = ISNULL(INV1_NETPIECE, 0) + :npc,
                                    INV1_NETWEIGHT   = ISNULL(INV1_NETWEIGHT, 0) + :nwt,
                                    INV1_GROSSWEIGHT = ISNULL(INV1_GROSSWEIGHT, 0) + :gwt,
                                    INV1_TOTALAMOUNT = ISNULL(INV1_TOTALAMOUNT, 0) + :amt
                                  WHERE INVOICE_NO = :inv")
                       ->execute([
                           ':pkg' => $addPkg, ':npc' => $addNetPiece, ':nwt' => $addNetWeight,
                           ':gwt' => $addGrossWeight, ':amt' => $addAmount, ':inv' => $invoiceNo
                       ]);

            // 5. อัปเดตยอดส่งมอบและสถานะใน Sale Order (CSTMORDR2)
            $newSOStatus = $closeSaleOrder ? 'CP' : trim($item['CTM2_STATUS']);
            $this->conn->prepare("UPDATE CSTMORDR2 SET
                                    CTM2_INVOICEWEIGHT = :sowt,
                                    CTM2_INVOICEPIECE  = :sopc,
                                    CTM2_INVOICEBUNDLE = :sobnd,
                                    CTM2_STATUS        = :sost
                                  WHERE SALEORDER_NO = :sono AND SALEORDER_ITEM = :soitem")
                       ->execute([
                           ':sowt'   => $accSOWeight,
                           ':sopc'   => $accSOPiece,
                           ':sobnd'  => $accSOBundle,
                           ':sost'   => $newSOStatus,
                           ':sono'   => trim($item['SALEORDER_NO']),
                           ':soitem' => trim($item['SALEORDER_ITEM'])
                       ]);

            $this->conn->commit();
            return ['status' => 'success', 'message' => "ตัดสต็อกเพิ่มสินค้าจำนวน " . count($productNos) . " รายการเรียบร้อยแล้ว"];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateInvoiceProductSkid($invoiceNo, $productNo, $skidNo) {
        $this->assertInvoiceOpen($invoiceNo);
        $this->conn->prepare("UPDATE INVCPRCS3 SET SKID_NO = :skid WHERE INVOICE_NO = :inv AND PRODUCT_NO = :pno")
                   ->execute([':skid' => trim($skidNo), ':inv' => trim($invoiceNo), ':pno' => trim($productNo)]);
        return ['status' => 'success', 'message' => "อัปเดต Skid No. ของ $productNo เรียบร้อยแล้ว"];
    }

    public function deleteInvoiceProduct($invoiceNo, $invoiceItem, $productNo) {
        try {
            $this->assertInvoiceOpen($invoiceNo);
            $this->conn->beginTransaction();

            $stmtP = $this->conn->prepare("SELECT * FROM INVCPRCS3 WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item AND PRODUCT_NO = :pno");
            $stmtP->execute([':inv' => trim($invoiceNo), ':item' => trim($invoiceItem), ':pno' => trim($productNo)]);
            $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                throw new Exception("ไม่พบข้อมูลตัวสินค้า $productNo ในใบแจ้งหนี้นี้");
            }

            $stmtPack = $this->conn->prepare("SELECT LINE_PROCESS FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
            $stmtPack->execute([':pno' => trim($productNo)]);
            $pack = $stmtPack->fetch(PDO::FETCH_ASSOC);
            $restoreStatus = ($pack && trim($pack['LINE_PROCESS']) === 'D') ? 'DR' : 'ST';

            $this->conn->prepare("UPDATE PACKPROD1 SET PACK_STATUS = :st WHERE PRODUCT_NO = :pno")
                       ->execute([':st' => $restoreStatus, ':pno' => trim($productNo)]);

            $prodId = trim($prod['PRODUCT_ID']);
            if (in_array($prodId, ['CO', 'OS'])) {
                $this->conn->prepare("UPDATE COILPROD1 SET COIL_STATUS = :st WHERE COIL_NO = :pno")
                           ->execute([':st' => $restoreStatus, ':pno' => trim($productNo)]);
            } else {
                $this->conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = :st WHERE PRODUCT_NO = :pno")
                           ->execute([':st' => $restoreStatus, ':pno' => trim($productNo)]);
            }

            $this->conn->prepare("UPDATE CSTMORDR2 SET
                                    CTM2_INVOICEWEIGHT = ISNULL(CTM2_INVOICEWEIGHT, 0) - :nwt,
                                    CTM2_INVOICEPIECE  = ISNULL(CTM2_INVOICEPIECE, 0) - :npc,
                                    CTM2_INVOICEBUNDLE = ISNULL(CTM2_INVOICEBUNDLE, 0) - :pkg,
                                    CTM2_STATUS        = 'OP'
                                  WHERE SALEORDER_NO = :sono AND SALEORDER_ITEM = :soitem")
                       ->execute([
                           ':nwt'    => (float)$prod['INV3_NETWEIGHT'],
                           ':npc'    => (float)$prod['INV3_NETPIECE'],
                           ':pkg'    => (int)$prod['INV3_PACKAGE'],
                           ':sono'   => trim($prod['SALEORDER_NO']),
                           ':soitem' => trim($prod['SALEORDER_ITEM'])
                       ]);

            $this->conn->prepare("UPDATE INVCPRCS2 SET
                                    INV2_PACKAGE     = ISNULL(INV2_PACKAGE, 0) - :pkg,
                                    INV2_NETPIECE    = ISNULL(INV2_NETPIECE, 0) - :npc,
                                    INV2_GROSSWEIGHT = ISNULL(INV2_GROSSWEIGHT, 0) - :gwt,
                                    INV2_NETWEIGHT   = ISNULL(INV2_NETWEIGHT, 0) - :nwt,
                                    IN2U_NETPIECE    = ISNULL(IN2U_NETPIECE, 0) - :unpc,
                                    IN2U_GROSSWEIGHT = ISNULL(IN2U_GROSSWEIGHT, 0) - :ugwt,
                                    IN2U_NETWEIGHT   = ISNULL(IN2U_NETWEIGHT, 0) - :unwt
                                  WHERE INVOICE_NO = :inv AND INVOICE_ITEM = :item")
                       ->execute([
                           ':pkg'  => (int)$prod['INV3_PACKAGE'],
                           ':npc'  => (float)$prod['INV3_NETPIECE'],
                           ':gwt'  => (float)$prod['INV3_GROSSWEIGHT'],
                           ':nwt'  => (float)$prod['INV3_NETWEIGHT'],
                           ':unpc' => (float)$prod['IN3U_NETPIECE'],
                           ':ugwt' => (float)$prod['IN3U_GROSSWEIGHT'],
                           ':unwt' => (float)$prod['IN3U_NETWEIGHT'],
                           ':inv'  => trim($invoiceNo),
                           ':item' => trim($invoiceItem)
                       ]);

            $lineAmount = (float)$prod['IN3U_NETWEIGHT'] * (float)$prod['INV3_INVOICEPRICE'];
            $this->conn->prepare("UPDATE INVCPRCS1 SET
                                    INV1_PACKAGE     = ISNULL(INV1_PACKAGE, 0) - :pkg,
                                    INV1_NETPIECE    = ISNULL(INV1_NETPIECE, 0) - :npc,
                                    INV1_NETWEIGHT   = ISNULL(INV1_NETWEIGHT, 0) - :nwt,
                                    INV1_GROSSWEIGHT = ISNULL(INV1_GROSSWEIGHT, 0) - :gwt,
                                    INV1_TOTALAMOUNT = ISNULL(INV1_TOTALAMOUNT, 0) - :amt
                                  WHERE INVOICE_NO = :inv")
                       ->execute([
                           ':pkg' => (int)$prod['INV3_PACKAGE'],
                           ':npc' => (float)$prod['INV3_NETPIECE'],
                           ':nwt' => (float)$prod['INV3_NETWEIGHT'],
                           ':gwt' => (float)$prod['INV3_GROSSWEIGHT'],
                           ':amt' => $lineAmount,
                           ':inv' => trim($invoiceNo)
                       ]);

            $this->conn->prepare("DELETE FROM INVCPRCS3 WHERE INVOICE_NO = :inv AND PRODUCT_NO = :pno")
                       ->execute([':inv' => trim($invoiceNo), ':pno' => trim($productNo)]);

            $this->conn->commit();
            return ['status' => 'success', 'message' => "ลบสินค้า $productNo และคืนสต็อกเรียบร้อยแล้ว"];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // 7. DELETE INVOICE HEADING
    // =========================================================================
    public function deleteInvoiceHeading($invoiceNo) {
        try {
            $head = $this->assertInvoiceOpen($invoiceNo);

            $stmtCheck = $this->conn->prepare("SELECT COUNT(*) AS CNT FROM INVCPRCS2 WHERE INVOICE_NO = :inv");
            $stmtCheck->execute([':inv' => trim($invoiceNo)]);
            if ((int)$stmtCheck->fetch(PDO::FETCH_ASSOC)['CNT'] > 0) {
                throw new Exception("ไม่สามารถลบหัวเอกสารได้ เนื่องจากยังมีรายการสินค้า (Item Detail) ค้างอยู่");
            }

            $this->conn->beginTransaction();

            if (trim($head['BOOK_NO'] ?? '') !== '') {
                $this->conn->prepare("UPDATE BOOKSHIP1 SET INVOICE_NO = '' WHERE BOOK_NO = :book")
                           ->execute([':book' => trim($head['BOOK_NO'])]);
            }

            $this->conn->prepare("DELETE FROM INVCPRCS1 WHERE INVOICE_NO = :inv")
                       ->execute([':inv' => trim($invoiceNo)]);

            $this->conn->commit();
            return ['status' => 'success', 'message' => "ลบใบแจ้งหนี้ $invoiceNo เรียบร้อยแล้ว"];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }

    private function assertInvoiceOpen($invoiceNo) {
        $stmt = $this->conn->prepare("SELECT * FROM INVCPRCS1 WHERE INVOICE_NO = :inv");
        $stmt->execute([':inv' => trim($invoiceNo)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("ไม่พบใบแจ้งหนี้เลขที่ $invoiceNo");
        }
        if (trim($row['INV1_STATUS'] ?? '') !== 'OP') {
            throw new Exception("เอกสารนี้มีสถานะ {$row['INV1_STATUS']} ไม่สามารถแก้ไขหรือลบได้ (แก้ไขได้เฉพาะสถานะ OP)");
        }
        return $row;
    }
}