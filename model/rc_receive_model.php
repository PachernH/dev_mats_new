<?php
// model/rc_receive_model.php

class RcReceiveModel {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    // =========================================================================
    // 1. ดึงข้อมูล Master Dropdowns ทั้งหมดในครั้งเดียว
    // =========================================================================
    public function rc_get_master_dropdowns(): array {
        $masters = [];
        $queries = [
            'suppliers'     => "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY FROM CSSPMSTR1 ORDER BY CSTMSPPL_ID ASC",
            'locations'     => "SELECT LOCATION_ID, DESCRIPTION FROM LCTNMSTR1 ORDER BY LOCATION_ID ASC",
            'alloys'        => "SELECT ALLOY FROM CMPSMSTR1 ORDER BY ALLOY ASC",
            'tempers'       => "SELECT TEMPER FROM TMPRMSTR1 ORDER BY TEMPER ASC",
            'grades'        => "SELECT GRADE FROM GRDEMSTR1 ORDER BY GRADE ASC",
            'surface_grades'=> "SELECT SURFACE_GRADE FROM SGRDMSTR1 ORDER BY SURFACE_GRADE ASC",
            'metal_grades'  => "SELECT METALLURGICAL_GRADE FROM MGRDMSTR1 ORDER BY METALLURGICAL_GRADE ASC",
            'thickness'     => "SELECT THICKNESS FROM THCKMSTR1 ORDER BY THICKNESS ASC",
            'width'         => "SELECT WIDTH FROM DMWDMSTR1 ORDER BY WIDTH ASC",
            'length'        => "SELECT LENGTH FROM LNGTMSTR1 ORDER BY LENGTH ASC",
            'smelts'        => "SELECT ALUMINIUM_SMELT FROM ALSMMSTR1 ORDER BY ALUMINIUM_SMELT ASC",
            'countries'     => "SELECT COUNTRY_MELT FROM CSCTMSTR1 ORDER BY COUNTRY_MELT ASC",
            'models'        => "SELECT PRODUCT_MODEL, AREA_SIZE FROM PRODMSTR1 ORDER BY PRODUCT_MODEL ASC"
        ];

        foreach ($queries as $key => $sql) {
            try {
                $stmt = $this->conn->query($sql);
                $masters[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $masters[$key] = [];
            }
        }
        return $masters;
    }

    // =========================================================================
    // 2. ดึงรายการสำหรับหน้าหลัก (receive_pd_sc_process_mats.php)
    // =========================================================================
    public function rc_get_invoice_list(string $dept, array $filters = []): array {
        $params = [];
        $where = ["1=1"];

        // กรองตามสิทธิ์แผนก (อ้างอิง Form_Load & GetHeading ใน rc1)
        if ($dept === 'WH') {
            $where[] = "h.RECEIVE_TYPE <> 'DR'";
        } elseif (in_array($dept, ['MK', 'PP'])) {
            $where[] = "h.RECEIVE_TYPE = 'DR'";
        }

        if (!empty($filters['search'])) {
            $where[] = "(h.INVOICE_NO LIKE :kw OR h.CSTMSPPL_ID LIKE :kw OR EXISTS (
                SELECT 1 FROM RECVPROD2 r2s WHERE r2s.INVOICE_NO = h.INVOICE_NO AND (r2s.PRODUCT_NO LIKE :kw OR r2s.PRODUCT_REFERENCE LIKE :kw)
            ))";
            $params[':kw'] = '%' . trim($filters['search']) . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = "h.RECEIVE_DATE >= :dfrom";
            $params[':dfrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "h.RECEIVE_DATE <= :dto";
            $params[':dto'] = $filters['date_to'];
        }
        if (!empty($filters['receive_type'])) {
            $where[] = "h.RECEIVE_TYPE = :rtype";
            $params[':rtype'] = $filters['receive_type'];
        }

        // แยกรายการที่รอดำเนินการ (PENDING) vs ทำไปแล้ว (COMPLETED)
        $tabStatus = $filters['tab_status'] ?? 'PENDING';
        if ($tabStatus === 'PENDING') {
            $where[] = "(ISNULL(d.ITEM_COUNT, 0) = 0 OR ISNULL(d.OPEN_COUNT, 0) > 0)";
        } else {
            $where[] = "(ISNULL(d.ITEM_COUNT, 0) > 0 AND ISNULL(d.OPEN_COUNT, 0) = 0)";
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT TOP 300
                    h.INVOICE_NO,
                    h.RECEIVE_DATE,
                    h.RECEIVE_TYPE,
                    h.PRODUCT_ID,
                    h.ORGPRODUCT_ID,
                    h.MATERIAL_IN,
                    h.CSTMSPPL_ID,
                    s.CONSIGNEE_COMPANY,
                    ISNULL(d.ITEM_COUNT, 0) AS ITEM_COUNT,
                    ISNULL(d.TOTAL_NETWEIGHT, 0) AS TOTAL_NETWEIGHT,
                    ISNULL(d.TOTAL_NETPIECE, 0) AS TOTAL_NETPIECE,
                    ISNULL(d.OPEN_COUNT, 0) AS OPEN_COUNT,
                    ISNULL(d.ACCEPT_COUNT, 0) AS ACCEPT_COUNT
                FROM RECVPROD1 h
                LEFT JOIN CSSPMSTR1 s ON h.CSTMSPPL_ID = s.CSTMSPPL_ID
                OUTER APPLY (
                    SELECT 
                        COUNT(r2.PRODUCT_NO) AS ITEM_COUNT,
                        SUM(ISNULL(r2.REC2_NETWEIGHT, 0)) AS TOTAL_NETWEIGHT,
                        SUM(ISNULL(r2.REC2_NETPIECE, 0)) AS TOTAL_NETPIECE,
                        SUM(CASE WHEN COALESCE(c.COIL_STATUS, cr.CRSH_STATUS, sc.SCRP_STATUS, 'OP') = 'OP' THEN 1 ELSE 0 END) AS OPEN_COUNT,
                        SUM(CASE WHEN COALESCE(c.COIL_STATUS, cr.CRSH_STATUS, sc.SCRP_STATUS, 'OP') IN ('AC', 'DR') THEN 1 ELSE 0 END) AS ACCEPT_COUNT
                    FROM RECVPROD2 r2
                    LEFT JOIN COILPROD1 c ON r2.PRODUCT_NO = c.COIL_NO
                    LEFT JOIN CRSHPROD1 cr ON r2.PRODUCT_NO = cr.PRODUCT_NO
                    LEFT JOIN SCRPPROD1 sc ON r2.PRODUCT_NO = sc.PRODUCT_NO
                    WHERE r2.INVOICE_NO = h.INVOICE_NO
                ) d
                WHERE {$whereSql}
                ORDER BY h.RECEIVE_DATE DESC, h.INVOICE_NO DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // 3. ดึงข้อมูลรายละเอียดครบชุดของ Invoice (Heading + Supplier + Items)
    // =========================================================================
    public function rc_get_invoice_detail(string $invoiceNo): ?array {
        $sqlHead = "SELECT h.*, 
                        s.CONSIGNEE_COMPANY, s.CONSIGNEE_ADDRESS1, s.CONSIGNEE_ADDRESS2, s.CONSIGNEE_ADDRESS3,
                        s.CONSIGNEE_CONTACT, s.CONSIGNEE_POSITION, s.CONSIGNEE_TELEPHONE, s.CONSIGNEE_FAX, s.CONSIGNEE_EMAIL,
                        s.COIL_TYPE AS DEFAULT_COIL_TYPE, s.PRIMARY_SMELT AS DEFAULT_PRIMARY_SMELT, 
                        s.SECONDARY_SMELT AS DEFAULT_SECONDARY_SMELT, s.COUNTRY_MELT AS DEFAULT_COUNTRY_MELT
                    FROM RECVPROD1 h
                    LEFT JOIN CSSPMSTR1 s ON h.CSTMSPPL_ID = s.CSTMSPPL_ID
                    WHERE h.INVOICE_NO = :inv";
        $stmt = $this->conn->prepare($sqlHead);
        $stmt->execute([':inv' => $invoiceNo]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$header) return null;

        $sqlItems = "SELECT r2.*,
                        COALESCE(c.COIL_STATUS, cr.CRSH_STATUS, sc.SCRP_STATUS, 'OP') AS STATUS_ID,
                        COALESCE(c.ALLOY, cr.ALLOY, sc.ALLOY) AS ALLOY,
                        COALESCE(c.TEMPER, cr.TEMPER) AS TEMPER,
                        COALESCE(c.GRADE, cr.GRADE) AS GRADE,
                        COALESCE(c.SURFACE_GRADE, cr.SURFACE_GRADE) AS SURFACE_GRADE,
                        COALESCE(c.METALLURGICAL_GRADE, cr.METALLURGICAL_GRADE) AS METALLURGICAL_GRADE,
                        COALESCE(c.THICKNESS, cr.THICKNESS, 0) AS THICKNESS,
                        COALESCE(c.WIDTH, cr.WIDTH, 0) AS WIDTH,
                        ISNULL(cr.LENGTH, 0) AS LENGTH,
                        ISNULL(cr.WEIGHT_PIECE, 0) AS WEIGHT_PIECE,
                        cr.PRODUCT_MODEL,
                        ISNULL(cr.AREA_SIZE, 0) AS AREA_SIZE,
                        c.COIL_TYPE, c.PRIMARY_SMELT, c.SECONDARY_SMELT, c.COUNTRY_MELT, c.COUNTRY_ORIGIN,
                        COALESCE(c.MP2_PARTNO, cr.MP2_PARTNO) AS MP2_PARTNO,
                        COALESCE(c.LOCATION_ID, cr.LOCATION_ID) AS LOCATION_ID,
                        COALESCE(c.COIL_DIMENSIONWIDTH, cr.CRSH_DIMENSIONWIDTH, 0) AS DIM_WIDTH,
                        COALESCE(c.COIL_DIMENSIONLENGTH, cr.CRSH_DIMENSIONLENGTH, 0) AS DIM_LENGTH,
                        COALESCE(c.COIL_DIMENSIONHIGH, cr.CRSH_DIMENSIONHIGH, 0) AS DIM_HIGH,
                        COALESCE(c.COIL_REMARK, cr.CRSH_REMARK, sc.SCRP_REMARK) AS REMARK,
                        COALESCE(c.COIL_LABEL, cr.CRSH_LABEL, sc.SCRP_LABEL, 0) AS PRINT_LABEL,
                        COALESCE(c.SALEORDER_NO, cr.SALEORDER_NO) AS SO_NO,
                        COALESCE(c.SALEORDER_ITEM, cr.SALEORDER_ITEM) AS SO_ITEM,
                        ISNULL(c.COIL_PRODUCEWEIGHT, 0) AS PRODUCE_WEIGHT,
                        ISNULL(c.COIL_SCRAPWEIGHT, 0) AS SCRAP_WEIGHT,
                        ISNULL(c.COIL_SBPRODUCEWEIGHT, 0) AS SB_PRODUCE_WEIGHT,
                        ISNULL(c.COIL_SBSCRAPWEIGHT, 0) AS SB_SCRAP_WEIGHT,
                        ISNULL(c.COIL_BALANCEWEIGHT, 0) AS BALANCE_WEIGHT
                     FROM RECVPROD2 r2
                     LEFT JOIN COILPROD1 c ON r2.PRODUCT_NO = c.COIL_NO
                     LEFT JOIN CRSHPROD1 cr ON r2.PRODUCT_NO = cr.PRODUCT_NO
                     LEFT JOIN SCRPPROD1 sc ON r2.PRODUCT_NO = sc.PRODUCT_NO
                     WHERE r2.INVOICE_NO = :inv
                     ORDER BY r2.PRODUCT_NO ASC";
        $stmtItems = $this->conn->prepare($sqlItems);
        $stmtItems->execute([':inv' => $invoiceNo]);

        return [
            'header' => $header,
            'items'  => $stmtItems->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // =========================================================================
    // 4. ดึงข้อมูล Supplier (GetSupplier)
    // =========================================================================
    public function rc_get_supplier(string $supplierId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM CSSPMSTR1 WHERE CSTMSPPL_ID = :id");
        $stmt->execute([':id' => $supplierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    // 5. ตรวจสอบ Reference Product และคำนวณยอดคงเหลือ (GetReference)
    // =========================================================================
    public function rc_check_reference(string $refNo, string $orgProductId, string $receiveType): array {
        if ($orgProductId === 'CO') {
            $stmt = $this->conn->prepare("SELECT * FROM COILPROD1 WHERE COIL_NO = :ref");
            $stmt->execute([':ref' => $refNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                if (in_array($receiveType, ['DR', 'RC'])) {
                    return ['status' => 'not_found_nn', 'ref_status' => 'NN'];
                }
                return ['status' => 'error', 'message' => 'ไม่พบข้อมูลรหัสอ้างอิงในระบบ (STNDD-06)'];
            }

            $validStatuses = ['IN', 'SH', 'NN', 'DR'];
            if (!in_array($row['COIL_STATUS'], $validStatuses)) {
                return ['status' => 'error', 'message' => "สถานะสินค้าอ้างอิงไม่ถูกต้อง ({$row['COIL_STATUS']}) ต้องเป็น IN, SH, NN, DR (BWRHS-03)"];
            }
            if ($row['PRODUCT_ID'] !== $orgProductId) {
                return ['status' => 'error', 'message' => "ประเภทสินค้าอ้างอิงไม่ตรงกัน ({$row['PRODUCT_ID']} # {$orgProductId}) (BWRHS-04)"];
            }

            $actualWeight = (float)$row['COIL_ACTUALWEIGHT'];
            if ($receiveType === 'RS') {
                $usageWeight  = (float)$row['COIL_SBSCRAPWEIGHT'];
                $usageWeight1 = (float)$row['COIL_SBPRODUCEWEIGHT'];
            } elseif ($receiveType === 'RT') {
                $usageWeight  = (float)$row['COIL_RETURNWEIGHT'];
                $usageWeight1 = 0;
            } else {
                $usageWeight  = (float)$row['COIL_SBPRODUCEWEIGHT'];
                $usageWeight1 = (float)$row['COIL_SBSCRAPWEIGHT'];
            }

            $balanceWeight = $actualWeight - ($usageWeight + $usageWeight1);
            $soInfo = $this->rc_get_saleorder_reject($row['SALEORDER_NO'] ?? '', $row['SALEORDER_ITEM'] ?? '');

            return [
                'status' => 'success',
                'data' => [
                    'ref_status'       => $row['COIL_STATUS'],
                    'coil_no'          => $row['COIL_NO'] ?? '',
                    'material_in'      => $row['MATERIAL_IN'] ?? '',
                    'product_model'    => '',
                    'area_size'        => 0,
                    'alloy'            => $row['ALLOY'] ?? '',
                    'temper'           => $row['TEMPER'] ?? 'NONE',
                    'sg'               => $row['SURFACE_GRADE'] ?? 'SG3',
                    'mg'               => $row['METALLURGICAL_GRADE'] ?? 'MG3',
                    'grade'            => $row['GRADE'] ?? 'NONE',
                    'thickness'        => (float)($row['THICKNESS'] ?? 0),
                    'width'            => (float)($row['WIDTH'] ?? 0),
                    'length'           => 0,
                    'actual_weight'    => $actualWeight,
                    'usage_weight'     => $usageWeight,
                    'usage_weight1'    => $usageWeight1,
                    'balance_weight'   => $balanceWeight,
                    'saleorder_no'     => $row['SALEORDER_NO'] ?? '',
                    'saleorder_item'   => $row['SALEORDER_ITEM'] ?? '',
                    'reject_weight'    => $soInfo['reject_weight'],
                    'reject_piece'     => $soInfo['reject_piece']
                ]
            ];
        } else {
            // CC, SH, NC, WR, RM
            $stmt = $this->conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :ref");
            $stmt->execute([':ref' => $refNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return ['status' => 'error', 'message' => 'ไม่พบข้อมูลรหัสอ้างอิงในระบบ (STNDD-06)'];
            }
            if (!in_array($row['CRSH_STATUS'], ['IN', 'SH'])) {
                return ['status' => 'error', 'message' => "สถานะสินค้าอ้างอิงไม่ถูกต้อง ({$row['CRSH_STATUS']}) ต้องเป็น IN หรือ SH (BWRHS-03)"];
            }
            if ($row['PRODUCT_ID'] !== $orgProductId) {
                return ['status' => 'error', 'message' => "ประเภทสินค้าอ้างอิงไม่ตรงกัน ({$row['PRODUCT_ID']} # {$orgProductId}) (BWRHS-04)"];
            }

            $actualWeight  = (float)$row['CRSH_ACTUALWEIGHT'];
            $usageWeight   = (float)$row['CRSH_RETURNWEIGHT'];
            $balanceWeight = $actualWeight - $usageWeight;
            $soInfo = $this->rc_get_saleorder_reject($row['SALEORDER_NO'] ?? '', $row['SALEORDER_ITEM'] ?? '');

            return [
                'status' => 'success',
                'data' => [
                    'ref_status'       => $row['CRSH_STATUS'],
                    'coil_no'          => $row['COIL_NO'] ?? '',
                    'material_in'      => $row['MATERIAL_IN'] ?? '',
                    'product_model'    => $row['PRODUCT_MODEL'] ?? '',
                    'area_size'        => (float)($row['AREA_SIZE'] ?? 0),
                    'alloy'            => $row['ALLOY'] ?? '',
                    'temper'           => $row['TEMPER'] ?? 'NONE',
                    'sg'               => $row['SURFACE_GRADE'] ?? 'SG3',
                    'mg'               => $row['METALLURGICAL_GRADE'] ?? 'MG3',
                    'grade'            => $row['GRADE'] ?? 'NONE',
                    'thickness'        => (float)($row['THICKNESS'] ?? 0),
                    'width'            => (float)($row['WIDTH'] ?? 0),
                    'length'           => (float)($row['LENGTH'] ?? 0),
                    'actual_weight'    => $actualWeight,
                    'usage_weight'     => $usageWeight,
                    'usage_weight1'    => 0,
                    'balance_weight'   => $balanceWeight,
                    'saleorder_no'     => $row['SALEORDER_NO'] ?? '',
                    'saleorder_item'   => $row['SALEORDER_ITEM'] ?? '',
                    'reject_weight'    => $soInfo['reject_weight'],
                    'reject_piece'     => $soInfo['reject_piece']
                ]
            ];
        }
    }

    private function rc_get_saleorder_reject(string $soNo, string $soItem): array {
        if (empty($soNo) || empty($soItem)) return ['reject_weight' => 0, 'reject_piece' => 0];
        $stmt = $this->conn->prepare("SELECT CTM2_REJECTWEIGHT, CTM2_REJECTPIECE FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
        $stmt->execute([':so' => $soNo, ':item' => $soItem]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'reject_weight' => (float)($row['CTM2_REJECTWEIGHT'] ?? 0),
            'reject_piece'  => (float)($row['CTM2_REJECTPIECE'] ?? 0)
        ];
    }

    // =========================================================================
    // 6. ตรวจสอบ Sale Order สำหรับงาน Direct Shipment (GetSaleOrder)
    // =========================================================================
    public function rc_check_sale_order(string $soNo, string $soItem, array $specs): array {
        $stmt = $this->conn->prepare("SELECT * FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
        $stmt->execute([':so' => $soNo, ':item' => $soItem]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['status' => 'error', 'message' => 'ไม่พบข้อมูล Sale Order Item นี้ (STNDD-06)'];
        }

        $checks = [
            'ALLOY'               => [$row['ALLOY'], $specs['alloy']],
            'TEMPER'              => [$row['TEMPER'], $specs['temper']],
            'SURFACE_GRADE'       => [$row['SURFACE_GRADE'], $specs['sg']],
            'METALLURGICAL_GRADE' => [$row['METALLURGICAL_GRADE'], $specs['mg']],
        ];
        foreach ($checks as $field => [$soVal, $prodVal]) {
            if (trim((string)$soVal) !== trim((string)$prodVal)) {
                return ['status' => 'error', 'message' => "ข้อมูล {$field} ไม่ตรงกับ Sale Order ({$soVal} # {$prodVal}) (BWRHS-05)"];
            }
        }

        if ((float)$row['THICKNESS'] != (float)$specs['thickness']) {
            return ['status' => 'error', 'message' => "Thickness ไม่ตรงกับ Sale Order ({$row['THICKNESS']} # {$specs['thickness']}) (BWRHS-05)"];
        }
        if ((float)$row['WIDTH'] != (float)$specs['width']) {
            return ['status' => 'error', 'message' => "Width ไม่ตรงกับ Sale Order ({$row['WIDTH']} # {$specs['width']}) (BWRHS-05)"];
        }
        if ((float)$row['LENGTH'] != (float)$specs['length']) {
            return ['status' => 'error', 'message' => "Length ไม่ตรงกับ Sale Order ({$row['LENGTH']} # {$specs['length']}) (BWRHS-05)"];
        }

        return ['status' => 'success', 'data' => $row];
    }

    // =========================================================================
    // 7. บันทึกและลบ Heading (RECVPROD1)
    // =========================================================================
    public function rc_save_heading(array $data, string $operator): array {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT INVOICE_NO FROM RECVPROD1 WHERE INVOICE_NO = :inv");
            $stmt->execute([':inv' => $data['invoice_no']]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$exists) {
                $sql = "INSERT INTO RECVPROD1 (INVOICE_NO, CSTMSPPL_ID, RECEIVE_TYPE, PRODUCT_ID, ORGPRODUCT_ID, MATERIAL_IN, RECEIVE_DATE, REC1_OPERATOR, REC1_OPERATEDATE)
                        VALUES (:inv, :sup, :rtype, :pid, :orgpid, :matin, :rdate, :op, GETDATE())";
                $this->conn->prepare($sql)->execute([
                    ':inv'    => $data['invoice_no'],
                    ':sup'    => $data['supplier_id'],
                    ':rtype'  => $data['receive_type'],
                    ':pid'    => $data['product_id'],
                    ':orgpid' => $data['org_product_id'],
                    ':matin'  => $data['material_in'],
                    ':rdate'  => $data['receive_date'],
                    ':op'     => $operator
                ]);
            } else {
                $sql1 = "UPDATE RECVPROD1 SET MATERIAL_IN = :matin, CSTMSPPL_ID = :sup, RECEIVE_DATE = :rdate WHERE INVOICE_NO = :inv";
                $this->conn->prepare($sql1)->execute([
                    ':matin' => $data['material_in'],
                    ':sup'   => $data['supplier_id'],
                    ':rdate' => $data['receive_date'],
                    ':inv'   => $data['invoice_no']
                ]);

                $sql2 = "UPDATE RECVPROD2 SET MATERIAL_IN = :matin, RECEIVE_DATE = :rdate WHERE INVOICE_NO = :inv";
                $this->conn->prepare($sql2)->execute([
                    ':matin' => $data['material_in'],
                    ':rdate' => $data['receive_date'],
                    ':inv'   => $data['invoice_no']
                ]);
            }

            $this->conn->commit();
            return ['status' => 'success', 'invoice_no' => $data['invoice_no']];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function rc_delete_heading(string $invoiceNo): array {
        $stmt = $this->conn->prepare("SELECT TOP 1 PRODUCT_NO FROM RECVPROD2 WHERE INVOICE_NO = :inv");
        $stmt->execute([':inv' => $invoiceNo]);
        if ($stmt->fetch()) {
            return ['status' => 'error', 'message' => 'ไม่สามารถลบ Invoice ได้ เนื่องจากยังมีรายการสินค้าค้างอยู่'];
        }
        $this->conn->prepare("DELETE FROM RECVPROD1 WHERE INVOICE_NO = :inv")->execute([':inv' => $invoiceNo]);
        return ['status' => 'success'];
    }

    // =========================================================================
    // 8. รันรหัสสินค้าอัตโนมัติด้วย MAX() (ไม่ใช้ AUTOMSTR1)
    // =========================================================================
    private function rc_generate_product_no(string $productId): string {
        $prefixMap = [
            'CO' => 'C',
            'CC' => 'P', 'SH' => 'P', 'NC' => 'P',
            'RM' => 'R',
            'SC' => 'S'
        ];
        $char = $prefixMap[$productId] ?? 'X';
        $basePrefix = $char . date('ymd') . '-R-';

        $sql = "SELECT MAX(PRODUCT_NO) AS max_no FROM RECVPROD2 WITH (UPDLOCK, HOLDLOCK) WHERE PRODUCT_NO LIKE :prefix";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':prefix' => $basePrefix . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $nextSeq = 1;
        if (!empty($row['max_no'])) {
            $parts = explode('-', $row['max_no']);
            $nextSeq = (int)end($parts) + 1;
        }
        return $basePrefix . str_pad((string)$nextSeq, 2, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // 9. บันทึกรายการสินค้า (Add / Update Detail) พร้อมตัดสต็อกใน Transaction
    // =========================================================================
    public function rc_save_detail(array $data, string $operator): array {
        try {
            $this->conn->beginTransaction();

            $netWeight   = (float)$data['net_weight'];
            $grossWeight = (float)$data['gross_weight'];
            if ($grossWeight < $netWeight) {
                throw new Exception("Gross Weight ต้องมากกว่าหรือเท่ากับ Net Weight (BWRHS-81)");
            }
            $packageWeight = $grossWeight - $netWeight;

            $oldNetWeight = (float)($data['old_net_weight'] ?? 0);
            $varNetWeight = $netWeight - $oldNetWeight;

            $refNo      = trim($data['reference_no'] ?? '');
            $refStatus  = trim($data['ref_status'] ?? '');
            $recType    = $data['receive_type'];
            $orgProdId  = $data['org_product_id'];
            $prodId     = $data['product_id'];

            $refActualWeight  = (float)($data['ref_actual_weight'] ?? 0);
            $refUsageWeightOld= (float)($data['ref_usage_weight_old'] ?? 0);
            $refUsageWeight1  = (float)($data['ref_usage_weight1'] ?? 0);

            $newRefUsageWeight   = 0;
            $newRefBalanceWeight = 0;

            if (!empty($refNo) && $refStatus !== 'NN' && $orgProdId !== 'SC') {
                if ($recType === 'RT') {
                    $checkBal = $refActualWeight - ($refUsageWeightOld + $varNetWeight);
                } else {
                    $maxAllowed = round($refActualWeight + ($refActualWeight * 0.03));
                    $checkBal   = $maxAllowed - round(($refUsageWeightOld + $varNetWeight) + $refUsageWeight1);
                }
                if ($checkBal < 0) {
                    throw new Exception("น้ำหนักรับเข้าเกินกว่าน้ำหนักคงเหลือของสินค้าอ้างอิง (BWRHS-80)");
                }
                $newRefUsageWeight = $refUsageWeightOld + $varNetWeight;
                $newRefBalanceWeight = ($recType === 'RT') 
                    ? ($refActualWeight - $newRefUsageWeight) 
                    : ($refActualWeight - ($newRefUsageWeight + $refUsageWeight1));
            }

            $this->rc_ensure_dimension_masters((float)$data['thickness'], (float)$data['width'], (float)$data['length']);

            $workProcess = '';
            $nextProcess = '';
            if (in_array($recType, ['RC', 'RT'])) {
                $workProcess = ($prodId === 'CO') ? 'RC>IS>WS' : 'RC>IS';
                $nextProcess = 'IS';
            } elseif ($recType === 'DR') {
                $workProcess = 'RC>ST';
                $nextProcess = 'DR';
            }

            $isNew = empty(trim($data['product_no'] ?? ''));
            $productNo = $isNew ? $this->rc_generate_product_no($prodId) : trim($data['product_no']);
            $statusId  = $isNew ? (($recType === 'DR') ? 'DR' : 'OP') : ($data['status_id'] ?? 'OP');

            $weightPiece = (float)($data['weight_piece'] ?? 0);
            $netPiece    = (float)($data['net_piece'] ?? 0);
            $refUsagePiece = ($recType === 'RT' && $weightPiece > 0) ? round($newRefUsageWeight / $weightPiece) : 0;

            if ($isNew) {
                $sqlRec2 = "INSERT INTO RECVPROD2 (PRODUCT_NO, RECEIVE_DATE, RECEIVE_TYPE, PRODUCT_ID, ORGPRODUCT_ID, MATERIAL_IN, INVOICE_NO,
                                PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, REC2_NETWEIGHT, REC2_NETPIECE, REC2_PACKAGEWEIGHT,
                                REC2_MATPONO, REC2_MATPOITEM, REC2_OPERATOR, REC2_OPERATEDATE)
                            VALUES (:pno, :rdate, :rtype, :pid, :orgpid, :matin, :inv, :ref, :rsono, :rsoitem, :netw, :netp, :pkgw, :pono, :poitem, :op, GETDATE())";
                $this->conn->prepare($sqlRec2)->execute([
                    ':pno'     => $productNo,
                    ':rdate'   => $data['receive_date'],
                    ':rtype'   => $recType,
                    ':pid'     => $prodId,
                    ':orgpid'  => $orgProdId,
                    ':matin'   => $data['material_in'],
                    ':inv'     => $data['invoice_no'],
                    ':ref'     => $refNo,
                    ':rsono'   => $data['ref_saleorder_no'] ?? '',
                    ':rsoitem' => $data['ref_saleorder_item'] ?? '',
                    ':netw'    => $netWeight,
                    ':netp'    => $netPiece,
                    ':pkgw'    => $packageWeight,
                    ':pono'    => $data['mat_po_no'] ?? '',
                    ':poitem'  => $data['mat_po_item'] ?? '',
                    ':op'      => $operator
                ]);

                if ($recType === 'DR') {
                    $sqlPack = "INSERT INTO PACKPROD1 (PRODUCT_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, TRANSFER_NO, LINE_PROCESS, PRODUCT_ID,
                                    PACK_PONO, PACK_POITEM, PACK_DATE, PACK_COMBINEWEIGHT, PACK_PACKAGEWEIGHT, PACK_NETWEIGHT, PACK_NETPIECE,
                                    PACK_LABEL, PACK_OPERATOR1, PACK_OPERATEDATE, PACK_STATUS)
                                VALUES (:pno, :sono, :soitem, '', '', 'D', :pid, '', '', GETDATE(), 0, :pkgw, :netw, :netp, 0, :op, GETDATE(), :st)";
                    $this->conn->prepare($sqlPack)->execute([
                        ':pno'    => $productNo,
                        ':sono'   => $data['saleorder_no'] ?? '',
                        ':soitem' => $data['saleorder_item'] ?? '',
                        ':pid'    => $prodId,
                        ':pkgw'   => $packageWeight,
                        ':netw'   => $netWeight,
                        ':netp'   => $netPiece,
                        ':op'     => $operator,
                        ':st'     => $statusId
                    ]);
                }

                $this->rc_insert_product_master($productNo, $data, $netWeight, $netPiece, $packageWeight, $workProcess, $nextProcess, $statusId, $operator);
            } else {
                $sqlRec2 = "UPDATE RECVPROD2 SET RECEIVE_DATE = :rdate, PRODUCT_REFERENCE = :ref, SALEORDER_NO = :rsono, SALEORDER_ITEM = :rsoitem,
                                REC2_NETWEIGHT = :netw, REC2_NETPIECE = :netp, REC2_PACKAGEWEIGHT = :pkgw, REC2_MATPONO = :pono,
                                REC2_MATPOITEM = :poitem, REC2_UPDATE = :op, REC2_UPDATEDATE = GETDATE()
                            WHERE PRODUCT_NO = :pno";
                $this->conn->prepare($sqlRec2)->execute([
                    ':rdate'   => $data['receive_date'],
                    ':ref'     => $refNo,
                    ':rsono'   => $data['ref_saleorder_no'] ?? '',
                    ':rsoitem' => $data['ref_saleorder_item'] ?? '',
                    ':netw'    => $netWeight,
                    ':netp'    => $netPiece,
                    ':pkgw'    => $packageWeight,
                    ':pono'    => $data['mat_po_no'] ?? '',
                    ':poitem'  => $data['mat_po_item'] ?? '',
                    ':op'      => $operator,
                    ':pno'     => $productNo
                ]);

                if ($recType === 'DR') {
                    $sqlPack = "UPDATE PACKPROD1 SET SALEORDER_NO = :sono, SALEORDER_ITEM = :soitem, PACK_PACKAGEWEIGHT = :pkgw,
                                    PACK_NETWEIGHT = :netw, PACK_NETPIECE = :netp, PACK_UPDATE = :op, PACK_UPDATEDATE = GETDATE()
                                WHERE PRODUCT_NO = :pno";
                    $this->conn->prepare($sqlPack)->execute([
                        ':sono'   => $data['saleorder_no'] ?? '',
                        ':soitem' => $data['saleorder_item'] ?? '',
                        ':pkgw'   => $packageWeight,
                        ':netw'   => $netWeight,
                        ':netp'   => $netPiece,
                        ':op'     => $operator,
                        ':pno'    => $productNo
                    ]);
                }

                $this->rc_update_product_master($productNo, $data, $netWeight, $netPiece, $packageWeight, $operator);
            }

            $this->rc_update_reference_and_so($data, $varNetWeight, $newRefUsageWeight, $refUsagePiece, $newRefBalanceWeight, $operator);

            $this->conn->commit();
            return ['status' => 'success', 'product_no' => $productNo];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // 10. ลบรายการสินค้า (Delete Detail) พร้อมคืนยอดสต็อกใน Transaction
    // =========================================================================
    public function rc_delete_detail(string $productNo, string $operator): array {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT r2.*, COALESCE(c.COIL_STATUS, cr.CRSH_STATUS, sc.SCRP_STATUS, 'OP') AS STATUS_ID,
                                                 COALESCE(cr.WEIGHT_PIECE, 0) AS WEIGHT_PIECE
                                          FROM RECVPROD2 r2
                                          LEFT JOIN COILPROD1 c ON r2.PRODUCT_NO = c.COIL_NO
                                          LEFT JOIN CRSHPROD1 cr ON r2.PRODUCT_NO = cr.PRODUCT_NO
                                          LEFT JOIN SCRPPROD1 sc ON r2.PRODUCT_NO = sc.PRODUCT_NO
                                          WHERE r2.PRODUCT_NO = :pno");
            $stmt->execute([':pno' => $productNo]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception("ไม่พบข้อมูลสินค้า {$productNo}");
            }
            if ($item['STATUS_ID'] === 'AC') {
                throw new Exception("ผลิตภัณฑ์นี้ได้ทำการ Inspect ไปแล้ว (AC) กรุณาติดต่อ Technical ยกเลิกการ Inspect ก่อนจึงจะลบได้");
            }

            $refNo     = trim($item['PRODUCT_REFERENCE'] ?? '');
            $orgProdId = trim($item['ORGPRODUCT_ID'] ?? '');
            $recType   = trim($item['RECEIVE_TYPE'] ?? '');
            $netWeight = (float)$item['REC2_NETWEIGHT'];

            if (!empty($refNo) && $orgProdId !== 'SC') {
                if ($orgProdId === 'CO') {
                    $stmtRef = $this->conn->prepare("SELECT * FROM COILPROD1 WHERE COIL_NO = :ref");
                    $stmtRef->execute([':ref' => $refNo]);
                    $refRow = $stmtRef->fetch(PDO::FETCH_ASSOC);

                    if ($refRow && $refRow['COIL_STATUS'] === 'NN') {
                        $this->conn->prepare("DELETE FROM COILPROD1 WHERE COIL_NO = :ref")->execute([':ref' => $refNo]);
                        $this->conn->prepare("DELETE FROM COILINSP1 WHERE COIL_NO = :ref")->execute([':ref' => $refNo]);
                    } elseif ($refRow) {
                        if ($recType === 'RT') {
                            $newRet = max(0, (float)$refRow['COIL_RETURNWEIGHT'] - $netWeight);
                            $this->conn->prepare("UPDATE COILPROD1 SET COIL_RETURNWEIGHT = :w, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                       ->execute([':w' => $newRet, ':op' => $operator, ':ref' => $refNo]);
                        } elseif (in_array($recType, ['RC', 'DR'])) {
                            $newSb  = max(0, (float)$refRow['COIL_SBPRODUCEWEIGHT'] - $netWeight);
                            $newBal = (float)$refRow['COIL_ACTUALWEIGHT'] - ($newSb + (float)$refRow['COIL_SBSCRAPWEIGHT']);
                            $this->conn->prepare("UPDATE COILPROD1 SET COIL_SBPRODUCEWEIGHT = :w, COIL_BALANCEWEIGHT = :bal, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                       ->execute([':w' => $newSb, ':bal' => $newBal, ':op' => $operator, ':ref' => $refNo]);
                        } elseif ($recType === 'RS') {
                            $newSc  = max(0, (float)$refRow['COIL_SBSCRAPWEIGHT'] - $netWeight);
                            $newBal = (float)$refRow['COIL_ACTUALWEIGHT'] - ($newSc + (float)$refRow['COIL_SBPRODUCEWEIGHT']);
                            $this->conn->prepare("UPDATE COILPROD1 SET COIL_SBSCRAPWEIGHT = :w, COIL_BALANCEWEIGHT = :bal, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                       ->execute([':w' => $newSc, ':bal' => $newBal, ':op' => $operator, ':ref' => $refNo]);
                        }
                    }
                } elseif (in_array($orgProdId, ['CC', 'SH', 'NC'])) {
                    $stmtRef = $this->conn->prepare("SELECT CRSH_RETURNWEIGHT FROM CRSHPROD1 WHERE PRODUCT_NO = :ref");
                    $stmtRef->execute([':ref' => $refNo]);
                    $refRow = $stmtRef->fetch(PDO::FETCH_ASSOC);
                    if ($refRow) {
                        $newRet = max(0, (float)$refRow['CRSH_RETURNWEIGHT'] - $netWeight);
                        $wPiece = (float)$item['WEIGHT_PIECE'];
                        $newRetPiece = ($wPiece > 0) ? round($newRet / $wPiece) : 0;
                        $this->conn->prepare("UPDATE CRSHPROD1 SET CRSH_RETURNWEIGHT = :w, CRSH_RETURNPIECE = :p, CRSH_UPDATE = :op, CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :ref")
                                   ->execute([':w' => $newRet, ':p' => $newRetPiece, ':op' => $operator, ':ref' => $refNo]);
                    }
                }
            }

            if ($recType === 'RT' && !empty($item['SALEORDER_NO']) && !empty($item['SALEORDER_ITEM'])) {
                $soReject = $this->rc_get_saleorder_reject($item['SALEORDER_NO'], $item['SALEORDER_ITEM']);
                $newRejW  = max(0, $soReject['reject_weight'] - $netWeight);
                $wPiece   = (float)$item['WEIGHT_PIECE'];
                $newRejP  = in_array($orgProdId, ['CO', 'RM']) ? max(0, $soReject['reject_piece'] - 1) : (($wPiece > 0) ? round($newRejW / $wPiece) : 0);

                $this->conn->prepare("UPDATE CSTMORDR2 SET CTM2_REJECTWEIGHT = :w, CTM2_REJECTPIECE = :p WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item")
                           ->execute([':w' => $newRejW, ':p' => $newRejP, ':so' => $item['SALEORDER_NO'], ':item' => $item['SALEORDER_ITEM']]);
            }

            $this->conn->prepare("DELETE FROM RECVPROD2 WHERE PRODUCT_NO = :pno")->execute([':pno' => $productNo]);
            $this->conn->prepare("DELETE FROM COILPROD1 WHERE COIL_NO = :pno")->execute([':pno' => $productNo]);
            $this->conn->prepare("DELETE FROM CRSHPROD1 WHERE PRODUCT_NO = :pno")->execute([':pno' => $productNo]);
            $this->conn->prepare("DELETE FROM SCRPPROD1 WHERE PRODUCT_NO = :pno")->execute([':pno' => $productNo]);
            $this->conn->prepare("DELETE FROM CRSHINSP1 WHERE PRODUCT_NO = :pno")->execute([':pno' => $productNo]);
            if ($recType === 'DR') {
                $this->conn->prepare("DELETE FROM PACKPROD1 WHERE PRODUCT_NO = :pno")->execute([':pno' => $productNo]);
            }

            $this->conn->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    // Helper Functions ภายใน Model
    // =========================================================================
    private function rc_ensure_dimension_masters(float $thickness, float $width, float $length): void {
        if ($thickness > 0) {
            $stmt = $this->conn->prepare("SELECT THICKNESS FROM THCKMSTR1 WHERE THICKNESS = :v");
            $stmt->execute([':v' => $thickness]);
            if (!$stmt->fetch()) {
                $this->conn->prepare("INSERT INTO THCKMSTR1 (THICKNESS) VALUES (:v)")->execute([':v' => $thickness]);
            }
        }
        if ($width > 0) {
            $stmt = $this->conn->prepare("SELECT WIDTH FROM DMWDMSTR1 WHERE WIDTH = :v");
            $stmt->execute([':v' => $width]);
            if (!$stmt->fetch()) {
                $this->conn->prepare("INSERT INTO DMWDMSTR1 (WIDTH) VALUES (:v)")->execute([':v' => $width]);
            }
        }
        if ($length > 0) {
            $stmt = $this->conn->prepare("SELECT LENGTH FROM LNGTMSTR1 WHERE LENGTH = :v");
            $stmt->execute([':v' => $length]);
            if (!$stmt->fetch()) {
                $this->conn->prepare("INSERT INTO LNGTMSTR1 (LENGTH) VALUES (:v)")->execute([':v' => $length]);
            }
        }
    }

    private function rc_insert_product_master(string $productNo, array $data, float $netWeight, float $netPiece, float $packageWeight, string $workProcess, string $nextProcess, string $statusId, string $operator): void {
        $recType = $data['receive_type'];
        $prodId  = $data['product_id'];
        $orgId   = $data['org_product_id'];
        $refNo   = trim($data['reference_no'] ?? '');

        if ($recType === 'RS') {
            $scrpFrom = 'XX';
            if ($orgId === 'CO') $scrpFrom = 'CO';
            elseif (in_array($orgId, ['CC', 'SH', 'NC'])) $scrpFrom = 'CS';
            elseif (in_array($orgId, ['SC', 'WR'])) $scrpFrom = 'SC';

            $sql = "INSERT INTO SCRPPROD1 (PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM, ALLOY,
                        SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR, SCRP_OPERATEDATE, SCRP_STATUS)
                    VALUES (:pno, :rdate, :ref, :matin, 'R', 'RCSC', :sfrom, :alloy, :netw, :rem, :lbl, :op, GETDATE(), :st)";
            $this->conn->prepare($sql)->execute([
                ':pno'   => $productNo,
                ':rdate' => $data['receive_date'],
                ':ref'   => $refNo,
                ':matin' => $data['material_in'],
                ':sfrom' => $scrpFrom,
                ':alloy' => $data['alloy'] ?? '',
                ':netw'  => $netWeight,
                ':rem'   => $data['remark'] ?? '',
                ':lbl'   => (int)($data['print_label'] ?? 0),
                ':op'    => $operator,
                ':st'    => $statusId
            ]);
            return;
        }

        if ($prodId === 'CO') {
            $lineProc = ($recType === 'DR') ? 'D' : 'R';
            $sql = "INSERT INTO COILPROD1 (COIL_NO, PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS,
                        PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, COUNTRY_ORIGIN, MP2_PARTNO, MATERIAL_IN,
                        COIL_TYPE, CSTMSPPL_ID, PRODUCT_ID, LINE_PROCESS, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS,
                        THICKNESS_FINAL, SUBCONTACT, LOCATION_ID, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS, F_WIDTH,
                        ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, COIL_UOMDIMENSION,
                        COIL_UOMWEIGHT, COIL_WORKPROCESS, COIL_NEXTPROCESS, COIL_TOPWEIGHT, COIL_BOTTOMWEIGHT,
                        COIL_COMBINEWEIGHT, COIL_RETURNWEIGHT, COIL_ACTUALWEIGHT, COIL_CALCULATEWEIGHT, COIL_FIRSTWEIGHT,
                        COIL_COLDMILLWEIGHT, COIL_BATCHANNEALWEIGHT, COIL_PRODUCEWEIGHT, COIL_SCRAPWEIGHT, COIL_ADJUSTWEIGHT,
                        COIL_SBPRODUCEWEIGHT, COIL_SBSCRAPWEIGHT, COIL_SBADJUSTWEIGHT, COIL_BALANCEWEIGHT, COIL_PACKWEIGHT,
                        COIL_DIMENSIONWIDTH, COIL_DIMENSIONLENGTH, COIL_DIMENSIONHIGH, COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE,
                        COIL_ENDTIME, PRODUCE_FLAG, COIL_REMARK, COIL_LABEL, COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS)
                    VALUES (:pno, :ref, :sono, :soitem, '', '', :psmelt, :ssmelt, :cmelt, :corigin, :mp2, :matin,
                        :ctype, :sup, :pid, :lproc, 0, 0, 0, 0, 'N', :loc, :falloy, :ftemp, :fgrade, :fthick, :fwidth,
                        :alloy, :temp, :grade, :sg, :mg, :thick, :width, 'mm.', 'kg.', :wproc, :nproc, 0, :pkgw,
                        0, 0, :actw, 0, :firstw, 0, 0, 0, 0, 0, 0, 0, 0, :balw, 0,
                        :dw, :dl, :dh, :sdate, GETDATE(), :edate, GETDATE(), 'N', :rem, :lbl, :op, GETDATE(), :st)";
            $this->conn->prepare($sql)->execute([
                ':pno'    => $productNo,
                ':ref'    => $refNo,
                ':sono'   => $data['saleorder_no'] ?? '',
                ':soitem' => $data['saleorder_item'] ?? '',
                ':psmelt' => $data['primary_smelt'] ?? '',
                ':ssmelt' => $data['secondary_smelt'] ?? '',
                ':cmelt'  => $data['country_melt'] ?? '',
                ':corigin'=> $data['country_origin'] ?? '',
                ':mp2'    => $data['mp2_partno'] ?? '',
                ':matin'  => $data['material_in'],
                ':ctype'  => $data['coil_type'] ?? '',
                ':sup'    => $data['supplier_id'],
                ':pid'    => $prodId,
                ':lproc'  => $lineProc,
                ':loc'    => $data['location_id'] ?? '',
                ':falloy' => $data['alloy'] ?? '',
                ':ftemp'  => $data['temper'] ?? '',
                ':fgrade' => $data['grade'] ?? '',
                ':fthick' => (float)$data['thickness'],
                ':fwidth' => (float)$data['width'],
                ':alloy'  => $data['alloy'] ?? '',
                ':temp'   => $data['temper'] ?? '',
                ':grade'  => $data['grade'] ?? '',
                ':sg'     => $data['sg'] ?? 'SG3',
                ':mg'     => $data['mg'] ?? 'MG3',
                ':thick'  => (float)$data['thickness'],
                ':width'  => (float)$data['width'],
                ':wproc'  => $workProcess,
                ':nproc'  => $nextProcess,
                ':pkgw'   => $packageWeight,
                ':actw'   => $netWeight,
                ':firstw' => $netWeight,
                ':balw'   => $netWeight,
                ':dw'     => (int)($data['dim_width'] ?? 0),
                ':dl'     => (int)($data['dim_length'] ?? 0),
                ':dh'     => (int)($data['dim_high'] ?? 0),
                ':sdate'  => $data['receive_date'],
                ':edate'  => $data['receive_date'],
                ':rem'    => $data['remark'] ?? '',
                ':lbl'    => (int)($data['print_label'] ?? 0),
                ':op'     => $operator,
                ':st'     => $statusId
            ]);

            if (!empty($refNo)) {
                $chkInsp = $this->conn->prepare("SELECT COIL_NO FROM COILINSP1 WHERE COIL_NO = :ref");
                $chkInsp->execute([':ref' => $refNo]);
                if (!$chkInsp->fetch()) {
                    $this->conn->prepare("INSERT INTO COILINSP1 (COIL_NO, INSPECTION_DATE, LINE_PROCESS, MDF_UPPER, MDF_LOWER, OIN1_OPERATOR, OIN1_OPERATEDATE, OIN1_STATUS)
                                          VALUES (:ref, GETDATE(), 'E', '', '', :op, GETDATE(), 'NN')")
                               ->execute([':ref' => $refNo, ':op' => $operator]);
                }
            }
        } else {
            // CC, SH, NC, RM -> CRSHPROD1
            $prodRefField = ($recType === 'RT' || ($recType === 'RC' && $orgId === 'SH')) ? $refNo : '';
            $coilNoField  = ($recType === 'RT' || ($recType === 'RC' && $orgId === 'SH')) ? ($data['coil_no'] ?? '') : $refNo;
            $lineProc     = ($recType === 'DR') ? 'D' : 'R';
            $orgLineProc  = ($recType === 'DR') ? 'N' : 'R';

            $sql = "INSERT INTO CRSHPROD1 (PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, LINE_PROCESS, ORG_LINEPROCESS, SALEORDER_NO,
                        SALEORDER_ITEM, JOB_ORDER, MP2_PARTNO, MATERIAL_IN, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE,
                        LOCATION_ID, F_ALLOY, F_TEMPER, F_WIDTH, F_LENGTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE,
                        METALLURGICAL_GRADE, THICKNESS, WIDTH, LENGTH, CRSH_UOMDIMENSION, CRSH_UOMWEIGHT, WEIGHT_PIECE,
                        CRSH_WORKPROCESS, CRSH_NEXTPROCESS, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT,
                        CRSH_PRODUCEPIECE, CRSH_BOTTOMWEIGHT, CRSH_DIMENSIONWIDTH, CRSH_DIMENSIONLENGTH, CRSH_DIMENSIONHIGH,
                        CRSH_STARTDATE, CRSH_STARTTIME, CRSH_ENDDATE, CRSH_ENDTIME, CRSH_REMARK, CRSH_LABEL, CRSH_OPERATOR1,
                        CRSH_OPERATEDATE, CRSH_STATUS)
                    VALUES (:pno, :pref, :cno, :lproc, :olproc, :sono, :soitem, '', :mp2, :matin, :pid, :pmodel, :area,
                        :loc, :falloy, :ftemp, :fwidth, :flen, :alloy, :temp, :grade, :sg, :mg, :thick, :width, :len,
                        'mm.', 'kg.', :wpiece, :wproc, :nproc, :actw, :actp, :prodw, :prodp, :pkgw,
                        :dw, :dl, :dh, :sdate, GETDATE(), :edate, GETDATE(), :rem, :lbl, :op, GETDATE(), :st)";
            $this->conn->prepare($sql)->execute([
                ':pno'    => $productNo,
                ':pref'   => $prodRefField,
                ':cno'    => $coilNoField,
                ':lproc'  => $lineProc,
                ':olproc' => $orgLineProc,
                ':sono'   => $data['saleorder_no'] ?? '',
                ':soitem' => $data['saleorder_item'] ?? '',
                ':mp2'    => $data['mp2_partno'] ?? '',
                ':matin'  => $data['material_in'],
                ':pid'    => $prodId,
                ':pmodel' => $data['product_model'] ?? '',
                ':area'   => (float)($data['area_size'] ?? 0),
                ':loc'    => $data['location_id'] ?? '',
                ':falloy' => $data['alloy'] ?? '',
                ':ftemp'  => $data['temper'] ?? '',
                ':fwidth' => (float)$data['width'],
                ':flen'   => (float)$data['length'],
                ':alloy'  => $data['alloy'] ?? '',
                ':temp'   => $data['temper'] ?? '',
                ':grade'  => $data['grade'] ?? '',
                ':sg'     => $data['sg'] ?? 'SG3',
                ':mg'     => $data['mg'] ?? 'MG3',
                ':thick'  => (float)$data['thickness'],
                ':width'  => (float)$data['width'],
                ':len'    => (float)$data['length'],
                ':wpiece' => (float)($data['weight_piece'] ?? 0),
                ':wproc'  => $workProcess,
                ':nproc'  => $nextProcess,
                ':actw'   => $netWeight,
                ':actp'   => $netPiece,
                ':prodw'  => $netWeight,
                ':prodp'  => $netPiece,
                ':pkgw'   => $packageWeight,
                ':dw'     => (int)($data['dim_width'] ?? 0),
                ':dl'     => (int)($data['dim_length'] ?? 0),
                ':dh'     => (int)($data['dim_high'] ?? 0),
                ':sdate'  => $data['receive_date'],
                ':edate'  => $data['receive_date'],
                ':rem'    => $data['remark'] ?? '',
                ':lbl'    => (int)($data['print_label'] ?? 0),
                ':op'     => $operator,
                ':st'     => $statusId
            ]);

            if ($recType === 'DR') {
                $this->rc_copy_inspection_for_dr($productNo, $prodId, $data['product_model'] ?? '', $orgId, $refNo, $operator);
            }
        }
    }

    private function rc_update_product_master(string $productNo, array $data, float $netWeight, float $netPiece, float $packageWeight, string $operator): void {
        $recType = $data['receive_type'];
        $prodId  = $data['product_id'];
        $refNo   = trim($data['reference_no'] ?? '');

        if ($recType === 'RS' && $prodId === 'SC') {
            $sql = "UPDATE SCRPPROD1 SET SCRP_DATE = :rdate, COIL_NO = :ref, ALLOY = :alloy, SCRP_WEIGHT = :netw,
                        SCRP_LABEL = :lbl, SCRP_UPDATE = :op, SCRP_UPDATEDATE = GETDATE()
                    WHERE PRODUCT_NO = :pno";
            $this->conn->prepare($sql)->execute([
                ':rdate' => $data['receive_date'],
                ':ref'   => $refNo,
                ':alloy' => $data['alloy'] ?? '',
                ':netw'  => $netWeight,
                ':lbl'   => (int)($data['print_label'] ?? 0),
                ':op'    => $operator,
                ':pno'   => $productNo
            ]);
            return;
        }

        if ($prodId === 'CO') {
            $sql = "UPDATE COILPROD1 SET PRODUCT_REFERENCE = :ref, SALEORDER_NO = :sono, SALEORDER_ITEM = :soitem, PRIMARY_SMELT = :psmelt,
                        SECONDARY_SMELT = :ssmelt, COUNTRY_MELT = :cmelt, COUNTRY_ORIGIN = :corigin, MP2_PARTNO = :mp2, COIL_TYPE = :ctype,
                        LOCATION_ID = :loc, ALLOY = :alloy, TEMPER = :temp, GRADE = :grade, SURFACE_GRADE = :sg, METALLURGICAL_GRADE = :mg,
                        THICKNESS = :thick, WIDTH = :width, COIL_BOTTOMWEIGHT = :pkgw, COIL_ACTUALWEIGHT = :actw, COIL_FIRSTWEIGHT = :firstw,
                        COIL_BALANCEWEIGHT = :balw, COIL_DIMENSIONWIDTH = :dw, COIL_DIMENSIONLENGTH = :dl, COIL_DIMENSIONHIGH = :dh,
                        COIL_STARTDATE = :sdate, COIL_ENDDATE = :edate, COIL_REMARK = :rem, COIL_LABEL = :lbl, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE()
                    WHERE COIL_NO = :pno";
            $this->conn->prepare($sql)->execute([
                ':ref'    => $refNo,
                ':sono'   => $data['saleorder_no'] ?? '',
                ':soitem' => $data['saleorder_item'] ?? '',
                ':psmelt' => $data['primary_smelt'] ?? '',
                ':ssmelt' => $data['secondary_smelt'] ?? '',
                ':cmelt'  => $data['country_melt'] ?? '',
                ':corigin'=> $data['country_origin'] ?? '',
                ':mp2'    => $data['mp2_partno'] ?? '',
                ':ctype'  => $data['coil_type'] ?? '',
                ':loc'    => $data['location_id'] ?? '',
                ':alloy'  => $data['alloy'] ?? '',
                ':temp'   => $data['temper'] ?? '',
                ':grade'  => $data['grade'] ?? '',
                ':sg'     => $data['sg'] ?? 'SG3',
                ':mg'     => $data['mg'] ?? 'MG3',
                ':thick'  => (float)$data['thickness'],
                ':width'  => (float)$data['width'],
                ':pkgw'   => $packageWeight,
                ':actw'   => $netWeight,
                ':firstw' => $netWeight,
                ':balw'   => (float)($data['balance_weight'] ?? $netWeight),
                ':dw'     => (int)($data['dim_width'] ?? 0),
                ':dl'     => (int)($data['dim_length'] ?? 0),
                ':dh'     => (int)($data['dim_high'] ?? 0),
                ':sdate'  => $data['receive_date'],
                ':edate'  => $data['receive_date'],
                ':rem'    => $data['remark'] ?? '',
                ':lbl'    => (int)($data['print_label'] ?? 0),
                ':op'     => $operator,
                ':pno'    => $productNo
            ]);
        } else {
            $sql = "UPDATE CRSHPROD1 SET SALEORDER_NO = :sono, SALEORDER_ITEM = :soitem, MP2_PARTNO = :mp2, LOCATION_ID = :loc,
                        PRODUCT_MODEL = :pmodel, AREA_SIZE = :area, ALLOY = :alloy, TEMPER = :temp, GRADE = :grade,
                        SURFACE_GRADE = :sg, METALLURGICAL_GRADE = :mg, THICKNESS = :thick, WIDTH = :width, LENGTH = :len,
                        WEIGHT_PIECE = :wpiece, CRSH_BOTTOMWEIGHT = :pkgw, CRSH_ACTUALWEIGHT = :actw, CRSH_PRODUCEWEIGHT = :prodw,
                        CRSH_DIMENSIONWIDTH = :dw, CRSH_DIMENSIONLENGTH = :dl, CRSH_DIMENSIONHIGH = :dh, CRSH_STARTDATE = :sdate,
                        CRSH_ENDDATE = :edate, CRSH_REMARK = :rem, CRSH_LABEL = :lbl, CRSH_UPDATE = :op, CRSH_UPDATEDATE = GETDATE()
                    WHERE PRODUCT_NO = :pno";
            $this->conn->prepare($sql)->execute([
                ':sono'   => $data['saleorder_no'] ?? '',
                ':soitem' => $data['saleorder_item'] ?? '',
                ':mp2'    => $data['mp2_partno'] ?? '',
                ':loc'    => $data['location_id'] ?? '',
                ':pmodel' => $data['product_model'] ?? '',
                ':area'   => (float)($data['area_size'] ?? 0),
                ':alloy'  => $data['alloy'] ?? '',
                ':temp'   => $data['temper'] ?? '',
                ':grade'  => $data['grade'] ?? '',
                ':sg'     => $data['sg'] ?? 'SG3',
                ':mg'     => $data['mg'] ?? 'MG3',
                ':thick'  => (float)$data['thickness'],
                ':width'  => (float)$data['width'],
                ':len'    => (float)$data['length'],
                ':wpiece' => (float)($data['weight_piece'] ?? 0),
                ':pkgw'   => $packageWeight,
                ':actw'   => $netWeight,
                ':prodw'  => $netWeight,
                ':dw'     => (int)($data['dim_width'] ?? 0),
                ':dl'     => (int)($data['dim_length'] ?? 0),
                ':dh'     => (int)($data['dim_high'] ?? 0),
                ':sdate'  => $data['receive_date'],
                ':edate'  => $data['receive_date'],
                ':rem'    => $data['remark'] ?? '',
                ':lbl'    => (int)($data['print_label'] ?? 0),
                ':op'     => $operator,
                ':pno'    => $productNo
            ]);
        }
    }

    private function rc_update_reference_and_so(array $data, float $varNetWeight, float $usageWeight, float $usagePiece, float $balanceWeight, string $operator): void {
        $refNo     = trim($data['reference_no'] ?? '');
        $refStatus = trim($data['ref_status'] ?? '');
        $orgId     = $data['org_product_id'];
        $recType   = $data['receive_type'];

        if (!empty($refNo)) {
            if ($refStatus === 'NN' && in_array($recType, ['RC', 'DR']) && $orgId === 'CO') {
                $chk = $this->conn->prepare("SELECT COIL_NO FROM COILPROD1 WHERE COIL_NO = :ref");
                $chk->execute([':ref' => $refNo]);
                if (!$chk->fetch()) {
                    $sql = "INSERT INTO COILPROD1 (COIL_NO, PRODUCT_REFERENCE, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, COUNTRY_ORIGIN,
                                MATERIAL_IN, COIL_TYPE, CSTMSPPL_ID, PRODUCT_ID, LINE_PROCESS, RECIPE_ITEM, TOTAL_PASS, CURRENT_PASS,
                                THICKNESS_FINAL, SUBCONTACT, LOCATION_ID, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS, F_WIDTH,
                                ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, THICKNESS, WIDTH, COIL_UOMDIMENSION, COIL_UOMWEIGHT,
                                COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE, COIL_ENDTIME, PRODUCE_FLAG, COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS)
                            VALUES (:ref, '', :ps, :ss, :cm, :co, :matin, :ct, :sup, 'CO', 'E', 0, 0, 0, 0, 'N', :loc,
                                :alloy, :temp, :grade, :thick, :width, :alloy, :temp, :grade, :sg, :mg, :thick, :width, 'mm.', 'kg.',
                                :rdate, GETDATE(), :rdate, GETDATE(), 'N', :op, GETDATE(), 'NN')";
                    $this->conn->prepare($sql)->execute([
                        ':ref'   => $refNo,
                        ':ps'    => $data['primary_smelt'] ?? '',
                        ':ss'    => $data['secondary_smelt'] ?? '',
                        ':cm'    => $data['country_melt'] ?? '',
                        ':co'    => $data['country_origin'] ?? '',
                        ':matin' => $data['material_in'],
                        ':ct'    => $data['coil_type'] ?? '',
                        ':sup'   => $data['supplier_id'],
                        ':loc'   => $data['location_id'] ?? '',
                        ':alloy' => $data['alloy'] ?? '',
                        ':temp'  => $data['temper'] ?? '',
                        ':grade' => $data['grade'] ?? '',
                        ':thick' => (float)$data['thickness'],
                        ':width' => (float)$data['width'],
                        ':sg'    => $data['sg'] ?? 'SG3',
                        ':mg'    => $data['mg'] ?? 'MG3',
                        ':rdate' => $data['receive_date'],
                        ':op'    => $operator
                    ]);
                }
            } elseif (in_array($refStatus, ['IN', 'SH'])) {
                if ($orgId === 'CO') {
                    if ($recType === 'RT') {
                        $this->conn->prepare("UPDATE COILPROD1 SET COIL_RETURNWEIGHT = :uw, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                   ->execute([':uw' => $usageWeight, ':op' => $operator, ':ref' => $refNo]);
                    } elseif (in_array($recType, ['RC', 'DR'])) {
                        $this->conn->prepare("UPDATE COILPROD1 SET COIL_SBPRODUCEWEIGHT = :uw, COIL_BALANCEWEIGHT = :bal, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                   ->execute([':uw' => $usageWeight, ':bal' => $balanceWeight, ':op' => $operator, ':ref' => $refNo]);
                    } elseif ($recType === 'RS') {
                        $this->conn->prepare("UPDATE COILPROD1 SET COIL_SBSCRAPWEIGHT = :uw, COIL_BALANCEWEIGHT = :bal, COIL_UPDATE = :op, COIL_UPDATEDATE = GETDATE() WHERE COIL_NO = :ref")
                                   ->execute([':uw' => $usageWeight, ':bal' => $balanceWeight, ':op' => $operator, ':ref' => $refNo]);
                    }
                } elseif (in_array($orgId, ['CC', 'SH', 'NC', 'RM'])) {
                    $this->conn->prepare("UPDATE CRSHPROD1 SET CRSH_RETURNWEIGHT = :uw, CRSH_RETURNPIECE = :up, CRSH_UPDATE = :op, CRSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :ref")
                               ->execute([':uw' => $usageWeight, ':up' => $usagePiece, ':op' => $operator, ':ref' => $refNo]);
                }
            }
        }

        $refSoNo   = trim($data['ref_saleorder_no'] ?? '');
        $refSoItem = trim($data['ref_saleorder_item'] ?? '');
        if ($recType === 'RT' && !empty($refSoNo) && !empty($refSoItem)) {
            $newRejW = (float)($data['ref_reject_weight_old'] ?? 0) + $varNetWeight;
            $wPiece  = (float)($data['weight_piece'] ?? 0);
            $newRejP = in_array($data['product_id'], ['CO', 'SC', 'RM']) 
                ? ((int)($data['ref_reject_piece_old'] ?? 0) + 1) 
                : (($wPiece > 0) ? round($newRejW / $wPiece) : 0);

            $this->conn->prepare("UPDATE CSTMORDR2 SET CTM2_REJECTWEIGHT = :w, CTM2_REJECTPIECE = :p WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item")
                       ->execute([':w' => $newRejW, ':p' => $newRejP, ':so' => $refSoNo, ':item' => $refSoItem]);
        }
    }

    private function rc_copy_inspection_for_dr(string $productNo, string $prodId, string $prodModel, string $orgId, string $refNo, string $operator): void {
        $ys = 0; $uts = 0; $el = 0; $ear = 0; $minT = 0; $maxT = 0; $app = ''; $dim = ''; $inspOp = $operator;
        if ($orgId === 'CO') {
            $stmt = $this->conn->prepare("SELECT * FROM COILINSP1 WHERE COIL_NO = :ref");
            $stmt->execute([':ref' => $refNo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ys = (float)$row['YIELD_STRENGTH']; $uts = (float)$row['UTS']; $el = (float)$row['ELONGATION'];
                $ear = (float)$row['EARING']; $minT = (float)$row['MINIMUM_THICKNESS']; $maxT = (float)$row['MAXIMUM_THICKNESS'];
                $app = $row['OIN1_APPEARANCE'] ?? ''; $dim = $row['OIN1_DIMENSION'] ?? ''; $inspOp = $row['OIN1_OPERATOR'] ?? $operator;
            }
        } elseif (in_array($orgId, ['CC', 'SH'])) {
            $stmt = $this->conn->prepare("SELECT * FROM CRSHINSP1 WHERE PRODUCT_NO = :ref");
            $stmt->execute([':ref' => $refNo]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ys = (float)$row['YIELD_STRENGTH']; $uts = (float)$row['UTS']; $el = (float)$row['ELONGATION'];
                $ear = (float)$row['EARING']; $minT = (float)$row['MINIMUM_THICKNESS']; $maxT = (float)$row['MAXIMUM_THICKNESS'];
                $app = $row['CIN1_APPEARANCE'] ?? ''; $dim = $row['CIN1_DIMENSION'] ?? ''; $inspOp = $row['CIN1_OPERATOR'] ?? $operator;
            }
        }

        $chk = $this->conn->prepare("SELECT PRODUCT_NO FROM CRSHINSP1 WHERE PRODUCT_NO = :pno");
        $chk->execute([':pno' => $productNo]);
        if (!$chk->fetch()) {
            $coilRef = ($orgId === 'CO') ? $refNo : '';
            $sql = "INSERT INTO CRSHINSP1 (PRODUCT_NO, PRODUCT_ID, PRODUCT_MODEL, COIL_NO, INSPECTION_DATE, YIELD_STRENGTH, UTS, ELONGATION,
                        EARING, MINIMUM_THICKNESS, MAXIMUM_THICKNESS, CIN1_OPERATOR, CIN1_OPERATEDATE, CIN1_STATUS, CIN1_APPEARANCE, CIN1_DIMENSION)
                    VALUES (:pno, :pid, :pmodel, :cno, GETDATE(), :ys, :uts, :el, :ear, :mint, :maxt, :op, GETDATE(), 'AC', :app, :dim)";
            $this->conn->prepare($sql)->execute([
                ':pno' => $productNo, ':pid' => $prodId, ':pmodel' => $prodModel, ':cno' => $coilRef,
                ':ys' => $ys, ':uts' => $uts, ':el' => $el, ':ear' => $ear, ':mint' => $minT, ':maxt' => $maxT,
                ':op' => $inspOp, ':app' => $app, ':dim' => $dim
            ]);
        }
    }

    // =========================================================================
    // 11. อัปโหลดไฟล์ EDI CSV (Upload Receive)
    // =========================================================================
    public function rc_upload_edi_csv(string $filePath, string $uploadDate, string $materialIn, string $supplierId, string $operator): array {
        if (!file_exists($filePath)) {
            return ['status' => 'error', 'message' => 'ไม่พบไฟล์ข้อมูล EDI'];
        }

        try {
            $this->conn->beginTransaction();
            $handle = fopen($filePath, 'r');
            $insertedCount = 0;

            while (($cols = fgetcsv($handle, 2000, ",")) !== false) {
                if (count($cols) < 41 || empty(trim($cols[0]))) continue;

                $invoiceNo  = trim($cols[0]);
                $poNo       = trim($cols[1]);
                $poItem     = str_pad((string)round((float)trim($cols[2])), 3, '0', STR_PAD_LEFT);
                $matPartNo  = trim($cols[3]);
                $splBatchNo = trim($cols[4]);
                $prodRef    = trim($cols[5]);
                $prodId     = trim($cols[6]);
                $orgProdId  = 'CO';
                $alloy      = trim($cols[7]);
                $temper     = trim($cols[8]);
                $sg         = trim($cols[9]);
                $mg         = trim($cols[10]);
                $grade      = trim($cols[11]);
                $thickness  = round((float)trim($cols[12]), 3);
                $width      = round((float)trim($cols[13]), 3);
                $length     = round((float)trim($cols[14]), 3);
                $wPiece     = round((float)trim($cols[15]), 4);
                $netW       = round((float)trim($cols[16]), 0);
                $grossW     = round((float)trim($cols[17]), 0);
                $pkgW       = $grossW - $netW;
                $castNo     = trim($cols[39]);
                $t5Temp     = trim($cols[40]);

                $chkEdi = $this->conn->prepare("SELECT INVOICE_NO, CSTMSPPL_ID FROM EDIPRECV1 WHERE PRODUCT_REFERENCE = :ref");
                $chkEdi->execute([':ref' => $prodRef]);
                if ($existEdi = $chkEdi->fetch(PDO::FETCH_ASSOC)) {
                    if ($existEdi['INVOICE_NO'] !== $invoiceNo || $existEdi['CSTMSPPL_ID'] !== $supplierId) {
                        throw new Exception("พบข้อมูลผลิตภัณฑ์ซ้ำ ({$prodRef}) แต่ต่าง Invoice หรือ Supplier ยกเลิกการอัปโหลด");
                    }
                } else {
                    $sqlEdi = "INSERT INTO EDIPRECV1 (PRODUCT_REFERENCE, INVOICE_NO, CSTMSPPL_ID, UPLOAD_DATE, PO_NO, PO_ITEM, MP2_PARTNO, SPL_BATCHNO, MAL_CASTNO,
                                    MATERIAL_IN, PRODUCT_ID, ORGPRODUCT_ID, ALLOY, TEMPER, SURFACE_GRADE, METALLURGICAL_GRADE, GRADE, T5_TEMPERATURE,
                                    THICKNESS, WIDTH, LENGTH, WEIGHT_PIECE, NET_WEIGHT, GROSS_WEIGHT)
                               VALUES (:ref, :inv, :sup, :udate, :po, :poi, :mp2, :batch, :cast, :matin, :pid, :orgid, :alloy, :temp, :sg, :mg, :grade, :t5,
                                    :thick, :width, :len, :wp, :netw, :grossw)";
                    $this->conn->prepare($sqlEdi)->execute([
                        ':ref' => $prodRef, ':inv' => $invoiceNo, ':sup' => $supplierId, ':udate' => $uploadDate,
                        ':po' => $poNo, ':poi' => $poItem, ':mp2' => $matPartNo, ':batch' => $splBatchNo, ':cast' => $castNo,
                        ':matin' => $materialIn, ':pid' => $prodId, ':orgid' => $orgProdId, ':alloy' => $alloy, ':temp' => $temper,
                        ':sg' => $sg, ':mg' => $mg, ':grade' => $grade, ':t5' => $t5Temp,
                        ':thick' => $thickness, ':width' => $width, ':len' => $length, ':wp' => $wPiece, ':netw' => $netW, ':grossw' => $grossW
                    ]);
                }

                $chkRec2 = $this->conn->prepare("SELECT PRODUCT_REFERENCE FROM RECVPROD2 WHERE PRODUCT_REFERENCE = :ref");
                $chkRec2->execute([':ref' => $prodRef]);
                if ($chkRec2->fetch()) {
                    throw new Exception("พบหมายเลขอ้างอิง {$prodRef} ซ้ำในฐานข้อมูลรับผลิตภัณฑ์ (RECVPROD2)");
                }

                $chkHead = $this->conn->prepare("SELECT INVOICE_NO FROM RECVPROD1 WHERE INVOICE_NO = :inv");
                $chkHead->execute([':inv' => $invoiceNo]);
                if (!$chkHead->fetch()) {
                    $this->conn->prepare("INSERT INTO RECVPROD1 (INVOICE_NO, CSTMSPPL_ID, RECEIVE_TYPE, PRODUCT_ID, ORGPRODUCT_ID, MATERIAL_IN, RECEIVE_DATE, REC1_OPERATOR, REC1_OPERATEDATE)
                                          VALUES (:inv, :sup, 'RC', :pid, :orgid, :matin, GETDATE(), :op, GETDATE())")
                               ->execute([':inv' => $invoiceNo, ':sup' => $supplierId, ':pid' => $prodId, ':orgid' => $orgProdId, ':matin' => $materialIn, ':op' => $operator]);
                }

                $productNo = $this->rc_generate_product_no($prodId);

                $this->conn->prepare("INSERT INTO RECVPROD2 (PRODUCT_NO, INVOICE_NO, RECEIVE_DATE, UPLOAD_DATE, RECEIVE_TYPE, PRODUCT_ID, ORGPRODUCT_ID, MATERIAL_IN,
                                        PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, REC2_NETWEIGHT, REC2_NETPIECE, REC2_PACKAGEWEIGHT, REC2_MATPONO, REC2_MATPOITEM, REC2_OPERATOR, REC2_OPERATEDATE)
                                      VALUES (:pno, :inv, GETDATE(), :udate, 'RC', :pid, :orgid, :matin, :ref, '', '', :netw, 1, :pkgw, :po, :poi, :op, GETDATE())")
                           ->execute([
                               ':pno' => $productNo, ':inv' => $invoiceNo, ':udate' => $uploadDate, ':pid' => $prodId, ':orgid' => $orgProdId,
                               ':matin' => $materialIn, ':ref' => $prodRef, ':netw' => $netW, ':pkgw' => $pkgW, ':po' => $poNo, ':poi' => $poItem, ':op' => $operator
                           ]);

                if ($prodId === 'CO') {
                    $this->conn->prepare("INSERT INTO COILPROD1 (COIL_NO, PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS, MP2_PARTNO, MATERIAL_IN,
                                            CSTMSPPL_ID, PRODUCT_ID, LINE_PROCESS, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS, F_WIDTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE,
                                            METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, WIDTH, ACTUAL_WIDTH, COIL_WORKPROCESS, COIL_NEXTPROCESS,
                                            COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, COIL_BALANCEWEIGHT, COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE, COIL_ENDTIME,
                                            COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS)
                                          VALUES (:pno, :ref, '', '', '', '', :mp2, :matin, :sup, 'CO', 'R', :alloy, :temp, :grade, :thick, :width,
                                            :alloy, :temp, :grade, :sg, :mg, :t5, :thick, :width, :width, 'RC>IS>WS', 'IS',
                                            :netw, :netw, :netw, GETDATE(), GETDATE(), GETDATE(), GETDATE(), :op, GETDATE(), 'OP')")
                               ->execute([
                                   ':pno' => $productNo, ':ref' => $prodRef, ':mp2' => $matPartNo, ':matin' => $materialIn, ':sup' => $supplierId,
                                   ':alloy' => $alloy, ':temp' => $temper, ':grade' => $grade, ':thick' => $thickness, ':width' => $width,
                                   ':sg' => $sg, ':mg' => $mg, ':t5' => $t5Temp, ':netw' => $netW, ':op' => $operator
                               ]);

                    $this->conn->prepare("INSERT INTO COILINSP1 (COIL_NO, INSPECTION_DATE, LINE_PROCESS, SPL_BATCHNO, MAL_CASTNO, YIELD_STRENGTH, UTS, ELONGATION,
                                            OIN1_OPERATOR, OIN1_OPERATEDATE, OIN1_STATUS)
                                          VALUES (:ref, GETDATE(), 'R', :batch, :cast, :ys, :uts, :el, :op, GETDATE(), 'AC')")
                               ->execute([
                                   ':ref' => $prodRef, ':batch' => $splBatchNo, ':cast' => $castNo,
                                   ':ys' => round((float)trim($cols[36]), 4), ':uts' => round((float)trim($cols[37]), 4), ':el' => round((float)trim($cols[38]), 4),
                                   ':op' => $operator
                               ]);
                }
                $insertedCount++;
            }
            fclose($handle);

            $this->conn->commit();
            return ['status' => 'success', 'message' => "นำเข้าข้อมูล EDI สำเร็จจำนวน {$insertedCount} รายการ"];
        } catch (Exception $e) {
            if (isset($handle) && is_resource($handle)) fclose($handle);
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

// =========================================================================
// ส่วนรับส่ง JSON API (ทำงานอัตโนมัติเมื่อถูกเรียกตรงผ่าน AJAX / fetch)
// =========================================================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['ID'])) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
        exit();
    }

    require_once '../dbcon_mats-new.php';

    $model    = new RcReceiveModel($conn);
    $userId   = trim($_SESSION['ID']);
    $userDept = strtoupper(trim($_SESSION['DEPARTMENT'] ?? 'WH'));

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? '';
    } else {
        $input  = $_POST;
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
    }

    switch ($action) {
        case 'rc_get_masters':
            echo json_encode(['status' => 'success', 'data' => $model->rc_get_master_dropdowns()]);
            break;

        case 'rc_get_list':
            $filters = [
                'search'       => $input['search'] ?? '',
                'date_from'    => $input['date_from'] ?? '',
                'date_to'      => $input['date_to'] ?? '',
                'receive_type' => $input['receive_type'] ?? '',
                'tab_status'   => $input['tab_status'] ?? 'PENDING'
            ];
            echo json_encode(['status' => 'success', 'data' => $model->rc_get_invoice_list($userDept, $filters)]);
            break;

        case 'rc_get_detail':
            $inv = trim($input['invoice_no'] ?? '');
            $detail = $model->rc_get_invoice_detail($inv);
            if ($detail) {
                $rtype = $detail['header']['RECEIVE_TYPE'];
                if (in_array($userDept, ['PP', 'MK']) && $rtype !== 'DR') {
                    echo json_encode(['status' => 'error', 'message' => 'แผนกของท่านสามารถดูได้เฉพาะรายการ DR เท่านั้น (BWRHS-01)']);
                    exit();
                }
                if ($userDept === 'WH' && $rtype === 'DR') {
                    echo json_encode(['status' => 'error', 'message' => 'แผนกคลังสินค้าไม่สามารถทำรายการ DR ได้ (BWRHS-01)']);
                    exit();
                }
            }
            echo json_encode(['status' => 'success', 'data' => $detail]);
            break;

        case 'rc_get_supplier':
            $supId = trim($input['supplier_id'] ?? '');
            echo json_encode(['status' => 'success', 'data' => $model->rc_get_supplier($supId)]);
            break;

        case 'rc_check_reference':
            $res = $model->rc_check_reference(
                trim($input['ref_no'] ?? ''),
                trim($input['org_product_id'] ?? ''),
                trim($input['receive_type'] ?? '')
            );
            echo json_encode($res);
            break;

        case 'rc_check_sale_order':
            $res = $model->rc_check_sale_order(
                trim($input['so_no'] ?? ''),
                trim($input['so_item'] ?? ''),
                $input['specs'] ?? []
            );
            echo json_encode($res);
            break;

        case 'rc_save_heading':
            echo json_encode($model->rc_save_heading($input['payload'] ?? $input, $userId));
            break;

        case 'rc_delete_heading':
            echo json_encode($model->rc_delete_heading(trim($input['invoice_no'] ?? '')));
            break;

        case 'rc_save_detail':
            echo json_encode($model->rc_save_detail($input['payload'] ?? $input, $userId));
            break;

        case 'rc_delete_detail':
            echo json_encode($model->rc_delete_detail(trim($input['product_no'] ?? ''), $userId));
            break;

        case 'rc_upload_edi':
            if (!in_array($userDept, ['WH', 'IT'])) {
                echo json_encode(['status' => 'error', 'message' => 'สิทธิ์เฉพาะแผนก WH และ IT เท่านั้น']);
                exit();
            }
            if (empty($_FILES['edi_file']['tmp_name'])) {
                echo json_encode(['status' => 'error', 'message' => 'กรุณาเลือกไฟล์ CSV ก่อนอัปโหลด']);
                exit();
            }
            $res = $model->rc_upload_edi_csv(
                $_FILES['edi_file']['tmp_name'],
                $input['upload_date'] ?? date('Y-m-d'),
                $input['material_in'] ?? 'BOI-IMP1',
                $input['supplier_id'] ?? 'MAL',
                $userId
            );
            echo json_encode($res);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
    exit();
}