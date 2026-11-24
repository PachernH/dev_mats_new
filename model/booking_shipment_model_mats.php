<?php
class BookingShipmentModel
{
    private $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * ดึงข้อมูล Master ทั้งหมดสำหรับ Dropdown ในหน้า Entry
     */
    public function getMasterData(): array
    {
        $data = [
            'customers' => [],
            'shippers'  => [],
            'ports'     => [],
            'places'    => [],
            'freights'  => [],
            'uoms'      => []
        ];

        // 1. ลูกค้า (CUSTOMER หรือ BOTH)
        $sqlCust = "SELECT CSTMSPPL_ID, CSTMSPPL_TYPE, CSTMSPPL_LOCATION, CONSIGNEE_COMPANY 
                    FROM CSSPMSTR1 
                    WHERE CSTMSPPL_TYPE IN ('B', 'C') 
                    ORDER BY CSTMSPPL_ID ASC";
        $data['customers'] = $this->conn->query($sqlCust)->fetchAll(PDO::FETCH_ASSOC);

        // 2. ผู้ส่งออก / Shipper
        $sqlShipper = "SELECT COMPANY_ID, COMPANY FROM CMPNMSTR1 ORDER BY COMPANY_ID ASC";
        $data['shippers'] = $this->conn->query($sqlShipper)->fetchAll(PDO::FETCH_ASSOC);

        // 3. ท่าเรือ
        $sqlPort = "SELECT PORT_ID, DESCRIPTION FROM PORTMSTR1 ORDER BY PORT_ID ASC";
        $data['ports'] = $this->conn->query($sqlPort)->fetchAll(PDO::FETCH_ASSOC);

        // 4. สถานที่ / ประเทศ
        $sqlPlace = "SELECT PLACE_ID, DESCRIPTION FROM PLCEMSTR1 ORDER BY PLACE_ID ASC";
        $data['places'] = $this->conn->query($sqlPlace)->fetchAll(PDO::FETCH_ASSOC);

        // 5. ประเภทค่าระวาง
        $sqlFreight = "SELECT FREIGHT_ID, DESCRIPTION FROM FRGTMSTR1 ORDER BY FREIGHT_ID ASC";
        $data['freights'] = $this->conn->query($sqlFreight)->fetchAll(PDO::FETCH_ASSOC);

        // 6. หน่วยนับหีบห่อ (UOM_TYPE = 'P')
        $sqlUom = "SELECT UOM, DESCRIPTION FROM UOMSMSTR1 WHERE ISNULL(UOM_TYPE, 'P') = 'P' ORDER BY UOM ASC";
        $data['uoms'] = $this->conn->query($sqlUom)->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * ดึงรายละเอียดที่อยู่ลูกค้า (Consignee / Notify / Also Notify) และค่าเริ่มต้น Port/Place
     */
    public function getCustomerDetail(string $customerId): ?array
    {
        $sql = "SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => trim($customerId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return array_map(function ($val) {
            return is_string($val) ? trim($val) : $val;
        }, $row);
    }

    /**
     * ดึงรายการ Booking (เฉพาะ BOOK_TYPE = 'O') สำหรับหน้า List
     */
    public function getBookingList(string $search = '', string $dateFrom = '', string $dateTo = ''): array
    {
        $params = [];
        $where = ["h.BOOK_TYPE = 'O'"];

        if ($search !== '') {
            $where[] = "(h.BOOK_NO LIKE :kw OR h.INVOICE_NO LIKE :kw OR h.CSTMSPPL_ID LIKE :kw OR c.CONSIGNEE_COMPANY LIKE :kw OR h.VESSEL LIKE :kw)";
            $params[':kw'] = '%' . $search . '%';
        }

        if ($dateFrom !== '') {
            $where[] = "h.BOOK_DATE >= CONVERT(DATETIME, :date_from, 120)";
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = "h.BOOK_DATE <= CONVERT(DATETIME, :date_to, 120)";
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT TOP 200
                    h.BOOK_NO,
                    h.BOOK_DATE,
                    h.INVOICE_NO,
                    h.CSTMSPPL_ID,
                    ISNULL(c.CONSIGNEE_COMPANY, '') AS CONSIGNEE_COMPANY,
                    h.BSH1_PORTLOADING,
                    h.BSH1_PORTDISCHARGE,
                    h.VESSEL,
                    h.FEEDER,
                    ISNULL(h.BSH1_TOTALPACKAGE, 0) AS BSH1_TOTALPACKAGE,
                    ISNULL(h.BSH1_TOTALWEIGHT, 0) AS BSH1_TOTALWEIGHT,
                    ISNULL(h.BSH1_TOTALCUBICMETER, 0) AS BSH1_TOTALCUBICMETER,
                    ISNULL(cnt.CONTAINER_COUNT, 0) AS CONTAINER_COUNT
                FROM BOOKSHIP1 h
                LEFT JOIN CSSPMSTR1 c ON h.CSTMSPPL_ID = c.CSTMSPPL_ID
                OUTER APPLY (
                    SELECT COUNT(b2.CONTAINER_NO) AS CONTAINER_COUNT
                    FROM BOOKSHIP2 b2
                    WHERE b2.BOOK_NO = h.BOOK_NO
                ) cnt
                WHERE {$whereSql}
                ORDER BY h.BOOK_DATE DESC, h.BOOK_NO DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ดึงข้อมูล Booking แบบครบทุกส่วน (Heading, Address, Containers, Products)
     */
    public function getBookingDetail(string $bookNo): array
    {
        $bookNo = strtoupper(trim($bookNo));
        $sqlH = "SELECT h.*,
                        ISNULL(s.COMPANY, '') AS SHIPPER_NAME,
                        ISNULL(pl.DESCRIPTION, '') AS PORT_LOADING_NAME,
                        ISNULL(pd.DESCRIPTION, '') AS PORT_DISCHARGE_NAME,
                        ISNULL(plc.DESCRIPTION, '') AS PLACE_DEST_NAME,
                        ISNULL(cntry.DESCRIPTION, '') AS COUNTRY_DEST_NAME,
                        ISNULL(frg.DESCRIPTION, '') AS FREIGHT_NAME
                 FROM BOOKSHIP1 h
                 LEFT JOIN CMPNMSTR1 s ON h.COMPANY_ID = s.COMPANY_ID
                 LEFT JOIN PORTMSTR1 pl ON h.BSH1_PORTLOADING = pl.PORT_ID
                 LEFT JOIN PORTMSTR1 pd ON h.BSH1_PORTDISCHARGE = pd.PORT_ID
                 LEFT JOIN PLCEMSTR1 plc ON h.BSH1_PLACEDESTINATION = plc.PLACE_ID
                 LEFT JOIN PLCEMSTR1 cntry ON h.BSH1_COUNTRYDESTINATION = cntry.PLACE_ID
                 LEFT JOIN FRGTMSTR1 frg ON h.FREIGHT_ID = frg.FREIGHT_ID
                 WHERE h.BOOK_NO = :book_no";
        $stmtH = $this->conn->prepare($sqlH);
        $stmtH->execute([':book_no' => $bookNo]);
        $heading = $stmtH->fetch(PDO::FETCH_ASSOC);

        if (!$heading) {
            throw new Exception("ไม่พบข้อมูลหมายเลข Booking: {$bookNo}");
        }

        // ตรวจสอบประเภทการจองต้องเป็น 'O' (Oversea) เท่านั้น
        if (strtoupper(trim($heading['BOOK_TYPE'] ?? '')) !== 'O') {
            throw new Exception("Booking No. นี้มีชนิดเป็น Local (L) ไม่ตรงกับชนิด Oversea (O)");
        }

        // Trim ค่า String ทุกฟิลด์
        $heading = array_map(function ($val) {
            return is_string($val) ? trim($val) : $val;
        }, $heading);

        // ดึงข้อมูลลูกค้า & ที่อยู่
        $address = $this->getCustomerDetail($heading['CSTMSPPL_ID'] ?? '');

        // ดึงรายการตู้คอนเทนเนอร์ (BOOKSHIP2)
        $sqlC = "SELECT * FROM BOOKSHIP2 WHERE BOOK_NO = :book_no ORDER BY CONTAINER_NO ASC";
        $stmtC = $this->conn->prepare($sqlC);
        $stmtC->execute([':book_no' => $bookNo]);
        $containers = [];
        while ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
            $containers[] = array_map(function ($val) {
                return is_string($val) ? trim($val) : $val;
            }, $row);
        }

        // ดึงรายการสินค้าทั้งหมดใน Invoice / Booking นี้ (INVCPRCS3)
        $invoiceNo = trim($heading['INVOICE_NO'] ?? '');
        $productsAll = [];
        if ($invoiceNo !== '') {
            $sqlP = "SELECT * FROM INVCPRCS3 WHERE INVOICE_NO = :inv_no ORDER BY PRODUCT_NO ASC";
            $stmtP = $this->conn->prepare($sqlP);
            $stmtP->execute([':inv_no' => $invoiceNo]);
            $productsAll = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sqlP = "SELECT * FROM INVCPRCS3 WHERE BOOK_NO = :book_no ORDER BY PRODUCT_NO ASC";
            $stmtP = $this->conn->prepare($sqlP);
            $stmtP->execute([':book_no' => $bookNo]);
            $productsAll = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        }

        $assignedProducts = [];
        $unassignedProducts = [];

        foreach ($productsAll as $p) {
            $p = array_map(function ($val) {
                return is_string($val) ? trim($val) : $val;
            }, $p);

            // คำนวณ CBM จาก กว้าง x ยาว x สูง (mm) / 10^9 ตามสูตร VB6
            $w = (float)($p['INV3_DIMENSIONWIDTH'] ?? 0);
            $l = (float)($p['INV3_DIMENSIONLENGTH'] ?? 0);
            $h = (float)($p['INV3_DIMENSIONHIGH'] ?? 0);
            $calcCbm = ($w > 0 && $l > 0 && $h > 0) ? round(($w * $l * $h) / 1000000000, 4) : (float)($p['INV3_CUBICMETER'] ?? 0);
            $p['CALC_CBM'] = $calcCbm;

            if (!empty($p['CONTAINER_NO'])) {
                $assignedProducts[] = $p;
            } else {
                $unassignedProducts[] = $p;
            }
        }

        return [
            'heading'             => $heading,
            'address'             => $address,
            'containers'          => $containers,
            'assigned_products'   => $assignedProducts,
            'unassigned_products' => $unassignedProducts
        ];
    }

    /**
     * บันทึกหรือแก้ไขข้อมูลส่วนหัว (BOOKSHIP1 และอัปเดต INVCPRCS1 หากมี Invoice แล้ว)
     */
    public function saveHeading(array $data, string $operator, bool $isNew): bool
    {
        $bookNo = strtoupper(trim($data['BOOK_NO'] ?? ''));
        if ($bookNo === '') {
            throw new Exception("กรุณาระบุหมายเลข Booking No.");
        }
        if (empty($data['CSTMSPPL_ID'])) {
            throw new Exception("กรุณาเลือกลูกค้า (Consignee)");
        }

        $this->conn->beginTransaction();
        try {
            if ($isNew) {
                // ตรวจสอบว่ามีเลข Booking นี้อยู่แล้วหรือไม่
                $chk = $this->conn->prepare("SELECT BOOK_NO, BOOK_TYPE FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
                $chk->execute([':book_no' => $bookNo]);
                $exist = $chk->fetch(PDO::FETCH_ASSOC);
                if ($exist) {
                    $typeMsg = (trim($exist['BOOK_TYPE']) !== 'O') ? " (ชนิด Local)" : "";
                    throw new Exception("หมายเลข Booking No. {$bookNo} มีอยู่ในระบบแล้ว{$typeMsg}");
                }

                $sql = "INSERT INTO BOOKSHIP1 (
                            BOOK_NO, BOOK_DATE, BOOK_TYPE, INVOICE_NO, ATTENTION, DOC_FROM,
                            COMPANY_ID, CSTMSPPL_ID, FROM_YARD, RETURN_YARD, EXPECT_DELIVERY, EXPECT_ARRIVAL,
                            BSH1_PORTLOADING, BSH1_PORTDISCHARGE, BSH1_PLACEDESTINATION, BSH1_COUNTRYDESTINATION,
                            FREIGHT_ID, FEEDER, VESSEL, VOLUME, BSH1_DESCRIPTION,
                            BSH1_TOTALPACKAGE, BSH1_TOTALWEIGHT, BSH1_TOTALCUBICMETER, BS1U_TOTALWEIGHT,
                            BSH1_SHIPPINGMARK, BSH1_SHIPPINGMARK1, BSH1_SHIPPINGMARK2, BSH1_SHIPPINGMARK3,
                            BSH1_SHIPPINGMARK4, BSH1_SHIPPINGMARK5, BSH1_SHIPPINGMARK6, BSH1_SHIPPINGMARK7,
                            BSH1_OPERATOR, BSH1_OPERATEDATE
                        ) VALUES (
                            :book_no, :book_date, 'O', '', :attention, :doc_from,
                            :company_id, :cstmsppl_id, :from_yard, :return_yard, :expect_delivery, :expect_arrival,
                            :port_loading, :port_discharge, :place_dest, :country_dest,
                            :freight_id, :feeder, :vessel, :volume, :description,
                            0, 0, 0, 0,
                            :sm0, :sm1, :sm2, :sm3, :sm4, :sm5, :sm6, :sm7,
                            :operator, DATEADD(dd, 0, DATEDIFF(dd, 0, GETDATE()))
                        )";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':book_no'         => $bookNo,
                    ':book_date'       => $data['BOOK_DATE'] ?: date('Y-m-d'),
                    ':attention'       => strtoupper(trim($data['ATTENTION'] ?? '')),
                    ':doc_from'        => strtoupper(trim($data['DOC_FROM'] ?? '')),
                    ':company_id'      => trim($data['COMPANY_ID'] ?? ''),
                    ':cstmsppl_id'     => trim($data['CSTMSPPL_ID'] ?? ''),
                    ':from_yard'       => $data['FROM_YARD'] ?: date('Y-m-d'),
                    ':return_yard'     => $data['RETURN_YARD'] ?: date('Y-m-d'),
                    ':expect_delivery' => $data['EXPECT_DELIVERY'] ?: date('Y-m-d'),
                    ':expect_arrival'  => $data['EXPECT_ARRIVAL'] ?: date('Y-m-d'),
                    ':port_loading'    => trim($data['BSH1_PORTLOADING'] ?? ''),
                    ':port_discharge'  => trim($data['BSH1_PORTDISCHARGE'] ?? ''),
                    ':place_dest'      => trim($data['BSH1_PLACEDESTINATION'] ?? ''),
                    ':country_dest'    => trim($data['BSH1_COUNTRYDESTINATION'] ?? ''),
                    ':freight_id'      => trim($data['FREIGHT_ID'] ?? ''),
                    ':feeder'          => strtoupper(trim($data['FEEDER'] ?? '')),
                    ':vessel'          => strtoupper(trim($data['VESSEL'] ?? '')),
                    ':volume'          => strtoupper(trim($data['VOLUME'] ?? '')),
                    ':description'     => strtoupper(trim($data['BSH1_DESCRIPTION'] ?? '')),
                    ':sm0'             => strtoupper(trim($data['BSH1_SHIPPINGMARK'] ?? '')),
                    ':sm1'             => strtoupper(trim($data['BSH1_SHIPPINGMARK1'] ?? '')),
                    ':sm2'             => strtoupper(trim($data['BSH1_SHIPPINGMARK2'] ?? '')),
                    ':sm3'             => strtoupper(trim($data['BSH1_SHIPPINGMARK3'] ?? '')),
                    ':sm4'             => strtoupper(trim($data['BSH1_SHIPPINGMARK4'] ?? '')),
                    ':sm5'             => strtoupper(trim($data['BSH1_SHIPPINGMARK5'] ?? '')),
                    ':sm6'             => strtoupper(trim($data['BSH1_SHIPPINGMARK6'] ?? '')),
                    ':sm7'             => strtoupper(trim($data['BSH1_SHIPPINGMARK7'] ?? '')),
                    ':operator'        => $operator
                ]);
            } else {
                // ดึง INVOICE_NO เดิมจากฐานข้อมูล
                $chk = $this->conn->prepare("SELECT INVOICE_NO, BOOK_TYPE FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
                $chk->execute([':book_no' => $bookNo]);
                $exist = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$exist) {
                    throw new Exception("ไม่พบข้อมูล Booking No. {$bookNo}");
                }
                if (trim($exist['BOOK_TYPE']) !== 'O') {
                    throw new Exception("Booking No. นี้มีชนิดเป็น Local ไม่สามารถแก้ไขในหน้านี้ได้");
                }

                $invoiceNo = trim($exist['INVOICE_NO'] ?? '');

                $sql = "UPDATE BOOKSHIP1 SET
                            BOOK_DATE = :book_date,
                            ATTENTION = :attention,
                            DOC_FROM = :doc_from,
                            COMPANY_ID = :company_id,
                            FROM_YARD = :from_yard,
                            RETURN_YARD = :return_yard,
                            EXPECT_DELIVERY = :expect_delivery,
                            EXPECT_ARRIVAL = :expect_arrival,
                            BSH1_PORTLOADING = :port_loading,
                            BSH1_PORTDISCHARGE = :port_discharge,
                            BSH1_PLACEDESTINATION = :place_dest,
                            BSH1_COUNTRYDESTINATION = :country_dest,
                            FREIGHT_ID = :freight_id,
                            FEEDER = :feeder,
                            VESSEL = :vessel,
                            VOLUME = :volume,
                            BSH1_DESCRIPTION = :description,
                            BSH1_SHIPPINGMARK = :sm0,
                            BSH1_SHIPPINGMARK1 = :sm1,
                            BSH1_SHIPPINGMARK2 = :sm2,
                            BSH1_SHIPPINGMARK3 = :sm3,
                            BSH1_SHIPPINGMARK4 = :sm4,
                            BSH1_SHIPPINGMARK5 = :sm5,
                            BSH1_SHIPPINGMARK6 = :sm6,
                            BSH1_SHIPPINGMARK7 = :sm7,
                            BSH1_UPDATE = :operator,
                            BSH1_UPDATEDATE = DATEADD(dd, 0, DATEDIFF(dd, 0, GETDATE()))
                        WHERE BOOK_NO = :book_no";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':book_date'       => $data['BOOK_DATE'] ?: date('Y-m-d'),
                    ':attention'       => strtoupper(trim($data['ATTENTION'] ?? '')),
                    ':doc_from'        => strtoupper(trim($data['DOC_FROM'] ?? '')),
                    ':company_id'      => trim($data['COMPANY_ID'] ?? ''),
                    ':from_yard'       => $data['FROM_YARD'] ?: date('Y-m-d'),
                    ':return_yard'     => $data['RETURN_YARD'] ?: date('Y-m-d'),
                    ':expect_delivery' => $data['EXPECT_DELIVERY'] ?: date('Y-m-d'),
                    ':expect_arrival'  => $data['EXPECT_ARRIVAL'] ?: date('Y-m-d'),
                    ':port_loading'    => trim($data['BSH1_PORTLOADING'] ?? ''),
                    ':port_discharge'  => trim($data['BSH1_PORTDISCHARGE'] ?? ''),
                    ':place_dest'      => trim($data['BSH1_PLACEDESTINATION'] ?? ''),
                    ':country_dest'    => trim($data['BSH1_COUNTRYDESTINATION'] ?? ''),
                    ':freight_id'      => trim($data['FREIGHT_ID'] ?? ''),
                    ':feeder'          => strtoupper(trim($data['FEEDER'] ?? '')),
                    ':vessel'          => strtoupper(trim($data['VESSEL'] ?? '')),
                    ':volume'          => strtoupper(trim($data['VOLUME'] ?? '')),
                    ':description'     => strtoupper(trim($data['BSH1_DESCRIPTION'] ?? '')),
                    ':sm0'             => strtoupper(trim($data['BSH1_SHIPPINGMARK'] ?? '')),
                    ':sm1'             => strtoupper(trim($data['BSH1_SHIPPINGMARK1'] ?? '')),
                    ':sm2'             => strtoupper(trim($data['BSH1_SHIPPINGMARK2'] ?? '')),
                    ':sm3'             => strtoupper(trim($data['BSH1_SHIPPINGMARK3'] ?? '')),
                    ':sm4'             => strtoupper(trim($data['BSH1_SHIPPINGMARK4'] ?? '')),
                    ':sm5'             => strtoupper(trim($data['BSH1_SHIPPINGMARK5'] ?? '')),
                    ':sm6'             => strtoupper(trim($data['BSH1_SHIPPINGMARK6'] ?? '')),
                    ':sm7'             => strtoupper(trim($data['BSH1_SHIPPINGMARK7'] ?? '')),
                    ':operator'        => $operator,
                    ':book_no'         => $bookNo
                ]);

                // หากออก Invoice แล้ว ให้อัปเดต Port & Shipping Mark ที่ตาราง INVCPRCS1 ตามเงื่อนไข VB6
                if ($invoiceNo !== '') {
                    $sqlInv = "UPDATE INVCPRCS1 SET
                                INV1_PORTLOADING = :port_loading,
                                INV1_PORTDISCHARGE = :port_discharge,
                                INV1_PLACEDESTINATION = :place_dest,
                                INV1_COUNTRYDESTINATION = :country_dest,
                                INV1_SHIPPINGMARK = :sm0,
                                INV1_SHIPPINGMARK1 = :sm1,
                                INV1_SHIPPINGMARK2 = :sm2,
                                INV1_SHIPPINGMARK3 = :sm3,
                                INV1_SHIPPINGMARK4 = :sm4,
                                INV1_SHIPPINGMARK5 = :sm5,
                                INV1_SHIPPINGMARK6 = :sm6,
                                INV1_SHIPPINGMARK7 = :sm7
                               WHERE INVOICE_NO = :invoice_no";
                    $stmtInv = $this->conn->prepare($sqlInv);
                    $stmtInv->execute([
                        ':port_loading'   => trim($data['BSH1_PORTLOADING'] ?? ''),
                        ':port_discharge' => trim($data['BSH1_PORTDISCHARGE'] ?? ''),
                        ':place_dest'     => trim($data['BSH1_PLACEDESTINATION'] ?? ''),
                        ':country_dest'   => trim($data['BSH1_COUNTRYDESTINATION'] ?? ''),
                        ':sm0'            => strtoupper(trim($data['BSH1_SHIPPINGMARK'] ?? '')),
                        ':sm1'            => strtoupper(trim($data['BSH1_SHIPPINGMARK1'] ?? '')),
                        ':sm2'            => strtoupper(trim($data['BSH1_SHIPPINGMARK2'] ?? '')),
                        ':sm3'            => strtoupper(trim($data['BSH1_SHIPPINGMARK3'] ?? '')),
                        ':sm4'            => strtoupper(trim($data['BSH1_SHIPPINGMARK4'] ?? '')),
                        ':sm5'            => strtoupper(trim($data['BSH1_SHIPPINGMARK5'] ?? '')),
                        ':sm6'            => strtoupper(trim($data['BSH1_SHIPPINGMARK6'] ?? '')),
                        ':sm7'            => strtoupper(trim($data['BSH1_SHIPPINGMARK7'] ?? '')),
                        ':invoice_no'     => $invoiceNo
                    ]);
                }
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * เพิ่มหรือแก้ไขตู้คอนเทนเนอร์ (BOOKSHIP2)
     */
    public function saveContainer(string $bookNo, string $containerNo, string $sealNo, string $uom, bool $isNew): bool
    {
        $bookNo      = strtoupper(trim($bookNo));
        $containerNo = strtoupper(trim($containerNo));
        $sealNo      = strtoupper(trim($sealNo));
        $uom         = strtoupper(trim($uom));

        if ($bookNo === '') {
            throw new Exception("ไม่พบเลขที่ Booking No.");
        }
        // เงื่อนไขจาก VB6: หมายเลขตู้คอนเทนเนอร์ต้องมีความยาว 11 ตัวอักษรเท่านั้น
        if (strlen($containerNo) !== 11) {
            throw new Exception("หมายเลขตู้คอนเทนเนอร์ (Container No.) ต้องมีความยาว 11 ตัวอักษรเท่านั้น");
        }

        $chk = $this->conn->prepare("SELECT CONTAINER_NO FROM BOOKSHIP2 WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no");
        $chk->execute([':book_no' => $bookNo, ':container_no' => $containerNo]);
        $exists = $chk->fetch(PDO::FETCH_ASSOC);

        if ($isNew) {
            if ($exists) {
                throw new Exception("ตู้คอนเทนเนอร์ {$containerNo} มีอยู่ในใบจองนี้แล้ว");
            }
            $sql = "INSERT INTO BOOKSHIP2 (
                        BOOK_NO, CONTAINER_NO, BOOK_TYPE, SEAL_NO, BSH2_UOM,
                        BSH2_PACKAGE, BSH2_NETWEIGHT, BSH2_GROSSWEIGHT, BSH2_CUBICMETER,
                        BS2U_NETWEIGHT, BS2U_GROSSWEIGHT, BS2U_UOMWEIGHT
                    ) VALUES (
                        :book_no, :container_no, 'O', :seal_no, :uom,
                        0, 0, 0, 0, 0, 0, ''
                    )";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':book_no'      => $bookNo,
                ':container_no' => $containerNo,
                ':seal_no'      => $sealNo,
                ':uom'          => $uom
            ]);
        } else {
            if (!$exists) {
                throw new Exception("ไม่พบตู้คอนเทนเนอร์ {$containerNo} ในใบจองนี้");
            }
            $sql = "UPDATE BOOKSHIP2 SET SEAL_NO = :seal_no, BSH2_UOM = :uom 
                    WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':seal_no'      => $sealNo,
                ':uom'          => $uom,
                ':book_no'      => $bookNo,
                ':container_no' => $containerNo
            ]);
        }
    }

    /**
     * บรรจุสินค้าลงตู้คอนเทนเนอร์ และคำนวณยอดสะสมใหม่ทั้ง BOOKSHIP2 และ BOOKSHIP1
     */
    public function addProductToContainer(string $bookNo, string $containerNo, string $productNo): bool
    {
        $bookNo      = strtoupper(trim($bookNo));
        $containerNo = strtoupper(trim($containerNo));
        $productNo   = strtoupper(trim($productNo));

        if ($bookNo === '' || $containerNo === '' || $productNo === '') {
            throw new Exception("ข้อมูล Booking, Container หรือ Product ไม่ครบถ้วน");
        }

        $this->conn->beginTransaction();
        try {
            // ตรวจสอบข้อมูลหัวใบจองและเลข Invoice
            $stmtH = $this->conn->prepare("SELECT INVOICE_NO FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
            $stmtH->execute([':book_no' => $bookNo]);
            $head = $stmtH->fetch(PDO::FETCH_ASSOC);
            if (!$head) {
                throw new Exception("ไม่พบข้อมูลใบจอง {$bookNo}");
            }
            $invoiceNo = trim($head['INVOICE_NO'] ?? '');
            if ($invoiceNo === '') {
                throw new Exception("ใบจองนี้ยังไม่มีเลขที่ Invoice ไม่สามารถเพิ่มสินค้าลงตู้ได้");
            }

            // ตรวจสอบสินค้าใน INVCPRCS3
            $stmtP = $this->conn->prepare("SELECT PRODUCT_NO, CONTAINER_NO FROM INVCPRCS3 WHERE INVOICE_NO = :inv_no AND PRODUCT_NO = :prod_no");
            $stmtP->execute([':inv_no' => $invoiceNo, ':prod_no' => $productNo]);
            $prod = $stmtP->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("ไม่พบรหัสสินค้า {$productNo} ใน Invoice {$invoiceNo}");
            }
            if (trim($prod['CONTAINER_NO'] ?? '') !== '') {
                throw new Exception("สินค้า {$productNo} ถูกจัดลงตู้ " . trim($prod['CONTAINER_NO']) . " ไปแล้ว (รหัส BBOOK-01)");
            }

            // อัปเดตสินค้าลงตู้
            $stmtUpd = $this->conn->prepare("UPDATE INVCPRCS3 
                                             SET CONTAINER_NO = :container_no, BOOK_CHECK = 'C', BOOK_DATE = GETDATE() 
                                             WHERE INVOICE_NO = :inv_no AND PRODUCT_NO = :prod_no");
            $stmtUpd->execute([
                ':container_no' => $containerNo,
                ':inv_no'       => $invoiceNo,
                ':prod_no'      => $productNo
            ]);

            // คำนวณยอดรวมใหม่ให้ BOOKSHIP2 และ BOOKSHIP1
            $this->recalculateTotals($bookNo, $containerNo, $invoiceNo);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * นำสินค้าออกจากตู้คอนเทนเนอร์ และคำนวณยอดสะสมใหม่
     */
    public function removeProductFromContainer(string $bookNo, string $containerNo, string $productNo): bool
    {
        $bookNo      = strtoupper(trim($bookNo));
        $containerNo = strtoupper(trim($containerNo));
        $productNo   = strtoupper(trim($productNo));

        $this->conn->beginTransaction();
        try {
            $stmtH = $this->conn->prepare("SELECT INVOICE_NO FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
            $stmtH->execute([':book_no' => $bookNo]);
            $head = $stmtH->fetch(PDO::FETCH_ASSOC);
            $invoiceNo = trim($head['INVOICE_NO'] ?? '');

            $sql = "UPDATE INVCPRCS3 SET CONTAINER_NO = '' 
                    WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no AND PRODUCT_NO = :prod_no";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':book_no'      => $bookNo,
                ':container_no' => $containerNo,
                ':prod_no'      => $productNo
            ]);

            $this->recalculateTotals($bookNo, $containerNo, $invoiceNo);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * คำนวณยอดรวมระดับ Container (BOOKSHIP2) และระดับ Booking (BOOKSHIP1) ใหม่ทั้งหมด
     */
    private function recalculateTotals(string $bookNo, string $containerNo, string $invoiceNo): void
    {
        $whereField = ($invoiceNo !== '') ? "INVOICE_NO = :ref_no" : "BOOK_NO = :ref_no";
        $refVal     = ($invoiceNo !== '') ? $invoiceNo : $bookNo;

        // 1. รวมยอดประจำตู้คอนเทนเนอร์จาก INVCPRCS3
        $sqlSumC = "SELECT 
                        CAST(ISNULL(SUM(INV3_PACKAGE), 0) AS INT) AS PKG,
                        ISNULL(SUM(INV3_NETWEIGHT), 0) AS NET_WT,
                        ISNULL(SUM(INV3_GROSSWEIGHT), 0) AS GROSS_WT,
                        ISNULL(SUM(
                            CASE WHEN ISNULL(INV3_DIMENSIONWIDTH, 0) > 0 AND ISNULL(INV3_DIMENSIONLENGTH, 0) > 0 AND ISNULL(INV3_DIMENSIONHIGH, 0) > 0
                            THEN ROUND((CAST(INV3_DIMENSIONWIDTH AS FLOAT) * CAST(INV3_DIMENSIONLENGTH AS FLOAT) * CAST(INV3_DIMENSIONHIGH AS FLOAT)) / 1000000000.0, 4)
                            ELSE ISNULL(INV3_CUBICMETER, 0) END
                        ), 0) AS CBM,
                        ISNULL(SUM(IN3U_NETWEIGHT), 0) AS UNET_WT,
                        ISNULL(SUM(IN3U_GROSSWEIGHT), 0) AS UGROSS_WT,
                        ISNULL(MAX(NULLIF(LTRIM(RTRIM(IN3U_UOMWEIGHT)), '')), '') AS UUOM_WT
                    FROM INVCPRCS3
                    WHERE {$whereField} AND CONTAINER_NO = :container_no";
        $stmtC = $this->conn->prepare($sqlSumC);
        $stmtC->execute([':ref_no' => $refVal, ':container_no' => $containerNo]);
        $cSum = $stmtC->fetch(PDO::FETCH_ASSOC);

        $updC = $this->conn->prepare("UPDATE BOOKSHIP2 SET 
                                        BSH2_PACKAGE = :pkg,
                                        BSH2_NETWEIGHT = :net_wt,
                                        BSH2_GROSSWEIGHT = :gross_wt,
                                        BSH2_CUBICMETER = :cbm,
                                        BS2U_NETWEIGHT = :unet_wt,
                                        BS2U_GROSSWEIGHT = :ugross_wt,
                                        BS2U_UOMWEIGHT = :uuom_wt
                                      WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no");
        $updC->execute([
            ':pkg'          => (int)$cSum['PKG'],
            ':net_wt'       => (float)$cSum['NET_WT'],
            ':gross_wt'     => (float)$cSum['GROSS_WT'],
            ':cbm'          => (float)$cSum['CBM'],
            ':unet_wt'      => (float)$cSum['UNET_WT'],
            ':ugross_wt'    => (float)$cSum['UGROSS_WT'],
            ':uuom_wt'      => $cSum['UUOM_WT'],
            ':book_no'      => $bookNo,
            ':container_no' => $containerNo
        ]);

        // 2. รวมยอดทั้งหมดของใบจองจาก BOOKSHIP2
        $sqlSumB = "SELECT 
                        CAST(ISNULL(SUM(BSH2_PACKAGE), 0) AS INT) AS TOT_PKG,
                        ISNULL(SUM(BSH2_GROSSWEIGHT), 0) AS TOT_WT,
                        ISNULL(SUM(BSH2_CUBICMETER), 0) AS TOT_CBM,
                        ISNULL(SUM(BS2U_GROSSWEIGHT), 0) AS UTOT_WT
                    FROM BOOKSHIP2
                    WHERE BOOK_NO = :book_no";
        $stmtB = $this->conn->prepare($sqlSumB);
        $stmtB->execute([':book_no' => $bookNo]);
        $bSum = $stmtB->fetch(PDO::FETCH_ASSOC);

        $updB = $this->conn->prepare("UPDATE BOOKSHIP1 SET 
                                        BSH1_TOTALPACKAGE = :tot_pkg,
                                        BSH1_TOTALWEIGHT = :tot_wt,
                                        BSH1_TOTALCUBICMETER = :tot_cbm,
                                        BS1U_TOTALWEIGHT = :utot_wt
                                      WHERE BOOK_NO = :book_no");
        $updB->execute([
            ':tot_pkg' => (int)$bSum['TOT_PKG'],
            ':tot_wt'  => (float)$bSum['TOT_WT'],
            ':tot_cbm' => (float)$bSum['TOT_CBM'],
            ':utot_wt' => (float)$bSum['UTOT_WT'],
            ':book_no' => $bookNo
        ]);
    }

    /**
     * ลบตู้คอนเทนเนอร์ (ตรวจสอบก่อนว่าต้องไม่มีสินค้าค้างในตู้)
     */
    public function deleteContainer(string $bookNo, string $containerNo): bool
    {
        $bookNo      = strtoupper(trim($bookNo));
        $containerNo = strtoupper(trim($containerNo));

        $chk = $this->conn->prepare("SELECT TOP 1 PRODUCT_NO FROM INVCPRCS3 WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no");
        $chk->execute([':book_no' => $bookNo, ':container_no' => $containerNo]);
        if ($chk->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("ไม่สามารถลบตู้ {$containerNo} ได้ เนื่องจากยังมีรายการสินค้าบรรจุอยู่ในตู้ กรุณานำสินค้าออกก่อน");
        }

        $del = $this->conn->prepare("DELETE FROM BOOKSHIP2 WHERE BOOK_NO = :book_no AND CONTAINER_NO = :container_no");
        return $del->execute([':book_no' => $bookNo, ':container_no' => $containerNo]);
    }

    /**
     * ลบหัวใบจอง Booking (ตรวจสอบก่อนว่าต้องไม่มีตู้คอนเทนเนอร์ค้างอยู่)
     */
    public function deleteBooking(string $bookNo): bool
    {
        $bookNo = strtoupper(trim($bookNo));

        $chkC = $this->conn->prepare("SELECT TOP 1 CONTAINER_NO FROM BOOKSHIP2 WHERE BOOK_NO = :book_no");
        $chkC->execute([':book_no' => $bookNo]);
        if ($chkC->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("ไม่สามารถลบใบจอง {$bookNo} ได้ เนื่องจากยังมีตู้คอนเทนเนอร์ผูกอยู่ กรุณาลบตู้คอนเทนเนอร์ทั้งหมดก่อน");
        }

        $stmtH = $this->conn->prepare("SELECT INVOICE_NO FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
        $stmtH->execute([':book_no' => $bookNo]);
        $head = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$head) {
            throw new Exception("ไม่พบข้อมูลใบจอง {$bookNo}");
        }

        $invoiceNo = trim($head['INVOICE_NO'] ?? '');

        $this->conn->beginTransaction();
        try {
            if ($invoiceNo !== '') {
                $updInv1 = $this->conn->prepare("UPDATE INVCPRCS1 SET BOOK_NO = '' WHERE INVOICE_NO = :inv_no");
                $updInv1->execute([':inv_no' => $invoiceNo]);

                $updInv3 = $this->conn->prepare("UPDATE INVCPRCS3 SET BOOK_NO = '' WHERE INVOICE_NO = :inv_no");
                $updInv3->execute([':inv_no' => $invoiceNo]);
            }

            $delH = $this->conn->prepare("DELETE FROM BOOKSHIP1 WHERE BOOK_NO = :book_no");
            $delH->execute([':book_no' => $bookNo]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }
}