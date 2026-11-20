<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class PoReceiveModel {
    private $conn_mats;
    private $conn_mp2;
    private $is_authorized;
    private $user_operate;

    public function __construct($conn_mats, $conn_mp2) {
        $this->conn_mats = $conn_mats;
        $this->conn_mp2  = $conn_mp2;

        $dept = strtoupper(trim($_SESSION['DEPARTMENT'] ?? ''));
        $func = strtoupper(trim($_SESSION['FUNC'] ?? ($_REQUEST['func'] ?? '')));
        $this->is_authorized = ($func === 'WH' || $dept === 'EM');
        $this->user_operate  = $_SESSION['USER_NAME'] ?? $_SESSION['ID'] ?? 'SYSTEM';
    }

    // 1. ดึงข้อมูล Master ใส่ Dropdown
    public function po_get_master_data() {
        $vendors = $this->conn_mp2->query("SELECT VENDORID, NAME FROM VENDOR ORDER BY NAME ASC")->fetchAll(PDO::FETCH_ASSOC);
        $uops    = $this->conn_mp2->query("SELECT UNIT FROM UOMUOP WHERE DESCRIPTION = '***USING' ORDER BY UNIT ASC")->fetchAll(PDO::FETCH_ASSOC);
        $reasons = $this->conn_mats->query("SELECT REASON_CODE FROM RESNMSTR1 ORDER BY REASON_CODE ASC")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'vendors' => $vendors,
            'uops'    => $uops,
            'reasons' => $reasons
        ];
    }

    // 2. ดึงรายการแสดงในหน้าหลัก (PENDING_PO, PENDING_RECV, COMPLETED)
    public function po_get_receive_list($filters = []) {
        $tab    = $filters['tab_status'] ?? 'PENDING_PO';
        $search = trim($filters['search'] ?? '');
        $dfrom  = trim($filters['date_from'] ?? '');
        $dto    = trim($filters['date_to'] ?? '');
        $result = trim($filters['result_filter'] ?? '');

        if ($tab === 'COMPLETED') {
            $sql = "SELECT TOP 300 RECORDNUM, PONUM, SEQNUM, ITEMNUM, DESCRIPTIONONPO, VENDORNAME,
                           CONVERT(VARCHAR(10), DATERECEIVED, 120) AS DATERECEIVED,
                           RECEIPTNUM, INVOICENUM, DELIVERYNUM, QTYREQUESTED, QTYRECEIVED, UOP, RECEIVEDRESULT, GATEPASS
                    FROM PRCHRCRD1 WHERE 1=1 ";
            $params = [];

            if ($search !== '') {
                $sql .= "AND (RECORDNUM LIKE ? OR PONUM LIKE ? OR ITEMNUM LIKE ? OR INVOICENUM LIKE ? OR VENDORNAME LIKE ?) ";
                $like = "%$search%";
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($dfrom !== '') {
                $sql .= "AND DATERECEIVED >= ? ";
                $params[] = $dfrom;
            }
            if ($dto !== '') {
                $sql .= "AND DATERECEIVED <= ? ";
                $params[] = $dto;
            }
            if ($result !== '') {
                $sql .= "AND RECEIVEDRESULT = ? ";
                $params[] = $result;
            }
            $sql .= "ORDER BY RECORDNUM DESC";

            $stmt = $this->conn_mats->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } elseif ($tab === 'PENDING_RECV') {
            // รายการที่รับเข้า MP2 แล้ว (PORECEIV JOIN PURREQ)
            $sql = "SELECT TOP 300 A.PONUM, A.SEQNUM, A.ITEMNUM, A.RECEIPTNUM, A.LINENUMBER, A.INVOICENUM,
                           A.RECEIVERNUM, A.QTYRECEIVED, CONVERT(VARCHAR(10), A.DATERECEIVED, 120) AS DATERECEIVED,
                           B.DESCRIPTIONONPO, B.QTYREQUESTED, B.VENDORID, B.VENDORNAME, B.UOP
                    FROM PORECEIV A
                    JOIN PURREQ B ON B.PONUM = A.PONUM AND B.ITEMNUM = A.ITEMNUM AND B.SEQNUM = A.SEQNUM
                    WHERE (A.PONUM BETWEEN 'PO0500001' AND 'PO5099999') ";
            $params = [];

            if ($search !== '') {
                $sql .= "AND (A.PONUM LIKE ? OR A.ITEMNUM LIKE ? OR A.INVOICENUM LIKE ? OR B.VENDORNAME LIKE ?) ";
                $like = "%$search%";
                array_push($params, $like, $like, $like, $like);
            }
            if ($dfrom !== '') {
                $sql .= "AND A.DATERECEIVED >= ? ";
                $params[] = $dfrom;
            }
            if ($dto !== '') {
                $sql .= "AND A.DATERECEIVED <= ? ";
                $params[] = $dto;
            }
            $sql .= "ORDER BY A.PONUM DESC, A.RECEIPTNUM DESC";

            $stmt = $this->conn_mp2->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // PENDING_PO: รายการ PO ค้างรับจาก PURREQ
            $sql = "SELECT TOP 300 PONUM, SEQNUM, LINENUMBER, ITEMNUM, DESCRIPTIONONPO, VENDORID, VENDORNAME,
                           QTYREQUESTED, ISNULL(QTYRECEIVED, 0) AS QTYRECEIVED, UOP, UOM,
                           CONVERT(VARCHAR(10), DUEDATE, 120) AS DUEDATE
                    FROM PURREQ
                    WHERE (PONUM BETWEEN 'PO0500001' AND 'PO5099999')
                      AND ((QTYRECEIVED < QTYREQUESTED) OR QTYRECEIVED IS NULL) ";
            $params = [];

            if ($search !== '') {
                $sql .= "AND (PONUM LIKE ? OR ITEMNUM LIKE ? OR DESCRIPTIONONPO LIKE ? OR VENDORNAME LIKE ?) ";
                $like = "%$search%";
                array_push($params, $like, $like, $like, $like);
            }
            if ($dfrom !== '') {
                $sql .= "AND DUEDATE >= ? ";
                $params[] = $dfrom;
            }
            if ($dto !== '') {
                $sql .= "AND DUEDATE <= ? ";
                $params[] = $dto;
            }
            $sql .= "ORDER BY PONUM DESC, SEQNUM ASC";

            $stmt = $this->conn_mp2->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // 3. ดึงรายละเอียดรายเอกสาร (record) หรือ ดึงตั้งต้นจาก PO (po / poreceiv)
    public function po_get_detail($mode, $params = []) {
        $data = [];
        $poNum      = trim($params['po_num'] ?? '');
        $seqNum     = floatval($params['seq_num'] ?? 0);
        $itemNum    = trim($params['item_num'] ?? '');
        $receiptNum = intval($params['receipt_num'] ?? 1);
        $recordNum  = trim($params['record_num'] ?? '');

        if ($mode === 'record') {
            $stmt = $this->conn_mats->prepare("SELECT *, CONVERT(VARCHAR(10), DATERECEIVED, 120) AS DATERECEIVED_FMT,
                                                      CONVERT(VARCHAR(8), TIMERECEIVED, 108) AS TIMERECEIVED_FMT,
                                                      CONVERT(VARCHAR(10), DUEDATE, 120) AS DUEDATE_FMT
                                               FROM PRCHRCRD1 WHERE RECORDNUM = ?");
            $stmt->execute([$recordNum]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return null;

            $poNum   = $data['PONUM'] ?? '';
            $seqNum  = floatval($data['SEQNUM'] ?? 0);
            $itemNum = $data['ITEMNUM'] ?? '';

        } elseif ($mode === 'po') {
            $stmt = $this->conn_mp2->prepare("SELECT *, CONVERT(VARCHAR(10), DUEDATE, 120) AS DUEDATE_FMT
                                              FROM PURREQ WHERE PONUM = ? AND SEQNUM = ? AND ITEMNUM = ?");
            $stmt->execute([$poNum, $seqNum, $itemNum]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return null;

            $data['QTYPREVIOUS'] = $data['QTYRECEIVED'] ?? 0;
            $data['QTYRECEIVED'] = 0;

        } elseif ($mode === 'poreceiv') {
            $sql = "SELECT A.PONUM, A.SEQNUM, A.ITEMNUM, CONVERT(VARCHAR(10), A.DATERECEIVED, 120) AS DATERECEIVED_FMT,
                           A.RECEIPTNUM, A.LINENUMBER, A.INVOICENUM, A.RECEIVERNUM, A.QTYRECEIVED,
                           A.DESTID, A.RECEIVEWAREHOUSE,
                           B.DESCRIPTIONONPO, B.QTYREQUESTED, B.VENDORID, B.VENDORNAME, B.UOP, B.UOM, B.ITEMTYPE,
                           CONVERT(VARCHAR(10), B.DUEDATE, 120) AS DUEDATE_FMT
                    FROM PORECEIV A
                    JOIN PURREQ B ON B.PONUM = A.PONUM AND B.ITEMNUM = A.ITEMNUM AND B.SEQNUM = A.SEQNUM
                    WHERE A.PONUM = ? AND A.ITEMNUM = ? AND A.RECEIPTNUM = ? AND A.SEQNUM = ?";
            $stmt = $this->conn_mp2->prepare($sql);
            $stmt->execute([$poNum, $itemNum, $receiptNum, $seqNum]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) return null;

            $data['QTYPREVIOUS'] = 0;
        }

        // ดึง WAREHOUSEINFO จาก MP2
        $whId = $data['RECEIVEWAREHOUSE'] ?? '';
        $whInfo = ['ABCCLASS' => '', 'REORDERPOINT' => '', 'REORDERQTY' => ''];
        if ($itemNum !== '' && $whId !== '') {
            $stmtWh = $this->conn_mp2->prepare("SELECT ABCCLASS, REORDERPOINT, REORDERQTY FROM WAREHOUSEINFO WHERE ITEMNUM = ? AND WAREHOUSEID = ?");
            $stmtWh->execute([$itemNum, $whId]);
            if ($rowWh = $stmtWh->fetch(PDO::FETCH_ASSOC)) {
                $whInfo = $rowWh;
            }
        }
        $data['WAREHOUSE_INFO'] = $whInfo;

        // ดึงประวัติการรับของ PO + SEQNUM + ITEMNUM นี้ทั้งหมด
        $history = [];
        if ($poNum !== '' && $itemNum !== '') {
            $stmtHist = $this->conn_mats->prepare("SELECT RECORDNUM, RECEIPTNUM, CONVERT(VARCHAR(10), DATERECEIVED, 120) AS DATERECEIVED,
                                                          INVOICENUM, DELIVERYNUM, QTYRECEIVED, UOP, RECEIVEDRESULT
                                                   FROM PRCHRCRD1
                                                   WHERE PONUM = ? AND SEQNUM = ? AND ITEMNUM = ?
                                                   ORDER BY RECEIPTNUM ASC, RECORDNUM ASC");
            $stmtHist->execute([$poNum, $seqNum, $itemNum]);
            $history = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
        }
        $data['HISTORY_LIST'] = $history;

        return $data;
    }

    // 4. บันทึกสร้างเอกสารใหม่ (Gen เลข RD-YY-XXXX จาก PRCHRCRD1)
    public function po_save_record($input) {
        if (!$this->is_authorized) {
            return ['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการสร้างรายการ (ต้องเป็น WH หรือ แผนก EM)'];
        }

        try {
            $this->conn_mats->beginTransaction();

            $yy     = date('y');
            $prefix = "RD-{$yy}-";
            $stmtMax = $this->conn_mats->prepare("SELECT TOP 1 RECORDNUM FROM PRCHRCRD1 WHERE RECORDNUM LIKE ? ORDER BY RECORDNUM DESC");
            $stmtMax->execute([$prefix . '%']);
            $lastRecord = $stmtMax->fetchColumn();

            $nextRun = $lastRecord ? (intval(substr($lastRecord, 6, 4)) + 1) : 1;
            $newRecordNum = $prefix . str_pad($nextRun, 4, '0', STR_PAD_LEFT);

            $dateReceived = !empty($input['date_received']) ? $input['date_received'] : date('Y-m-d');
            $timeReceived = !empty($input['time_received']) ? $input['time_received'] : date('H:i:s');
            $dateTimeStr  = $dateReceived . ' ' . $timeReceived;
            $dueDate      = !empty($input['due_date']) ? $input['due_date'] : $dateReceived;
            $gatePass     = !empty($input['print_gate_pass']) ? 1 : 0;

            $sql = "INSERT INTO PRCHRCRD1 (
                        RECORDNUM, PONUM, SEQNUM, ITEMNUM, DATERECEIVED, TIMERECEIVED, RECEIPTNUM, LINENUMBER,
                        INVOICENUM, DELIVERYNUM, RECEIVERNUM, VENDORID, VENDORNAME, DESCRIPTIONONPO, QTYREQUESTED,
                        QTYPREVIOUS, QTYRECEIVED, UOP, UOM, RECEIVEWAREHOUSE, DESTID, DUEDATE, ITEMTYPE, RECEIVEDRESULT,
                        RECEIVEDREASON, WAREHOUSECOMMENT, ENDUSERCOMMENT, PURCHASECOMMENT, GATEPASS
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )";

            $stmt = $this->conn_mats->prepare($sql);
            $stmt->execute([
                $newRecordNum,
                strtoupper(trim($input['po_num'] ?? '')),
                floatval($input['seq_num'] ?? 0),
                strtoupper(trim($input['item_num'] ?? '')),
                $dateReceived,
                $dateTimeStr,
                intval($input['receipt_num'] ?? 1),
                intval($input['line_number'] ?? 1),
                strtoupper(trim($input['invoice_num'] ?? '')),
                strtoupper(trim($input['delivery_num'] ?? '')),
                strtoupper(trim($input['receiver_num'] ?? '')),
                strtoupper(trim($input['vendor_id'] ?? '')),
                trim($input['vendor_name'] ?? ''),
                strtoupper(trim($input['description_on_po'] ?? '')),
                floatval($input['qty_requested'] ?? 0),
                floatval($input['qty_previous'] ?? 0),
                floatval($input['qty_received'] ?? 0),
                trim($input['uop'] ?? ''),
                trim($input['uom'] ?? ''),
                trim($input['receive_warehouse'] ?? ''),
                trim($input['dest_id'] ?? ''),
                $dueDate,
                strtoupper(trim($input['item_type'] ?? '')),
                trim($input['received_result'] ?? 'ACCEPT'),
                trim($input['received_reason'] ?? 'NONE'),
                trim($input['warehouse_comment'] ?? ''),
                trim($input['enduser_comment'] ?? ''),
                trim($input['purchase_comment'] ?? ''),
                $gatePass
            ]);

            $this->conn_mats->commit();

            // สร้างลิงก์หน้าพิมพ์
            $printUrls = [];
            if (!empty($input['print_label'])) {
                $form      = urlencode($input['label_form'] ?? '7x5');
                $copy      = max(1, intval($input['no_copy'] ?? 1));
                $container = urlencode(strtoupper(trim($input['container'] ?? '')));
                $printUrls[] = "print_po_label.php?record_num={$newRecordNum}&form={$form}&copy={$copy}&container={$container}";
            }
            if (!empty($input['print_gate_pass'])) {
                $printUrls[] = "print_po_gatepass.php?record_num={$newRecordNum}";
            }
            if (!empty($input['print_reject'])) {
                $printUrls[] = "print_po_reject.php?record_num={$newRecordNum}";
            }

            return [
                'status'     => 'success',
                'message'    => "บันทึกเอกสารเลขที่ {$newRecordNum} เรียบร้อยแล้ว",
                'record_num' => $newRecordNum,
                'print_urls' => $printUrls
            ];
        } catch (PDOException $e) {
            if ($this->conn_mats->inTransaction()) $this->conn_mats->rollBack();
            return ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }

    // 5. แก้ไขข้อมูลเดิม
    public function po_update_record($input) {
        if (!$this->is_authorized) {
            return ['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการแก้ไขข้อมูล'];
        }
        $recordNum = trim($input['record_num'] ?? '');
        if ($recordNum === '') {
            return ['status' => 'error', 'message' => 'ไม่พบเลขที่เอกสาร'];
        }

        try {
            $this->conn_mats->beginTransaction();

            $dateReceived = !empty($input['date_received']) ? $input['date_received'] : date('Y-m-d');
            $timeReceived = !empty($input['time_received']) ? $input['time_received'] : date('H:i:s');
            $dateTimeStr  = $dateReceived . ' ' . $timeReceived;
            $dueDate      = !empty($input['due_date']) ? $input['due_date'] : $dateReceived;
            $gatePass     = intval($input['gate_pass'] ?? 0);

            $sql = "UPDATE PRCHRCRD1 SET
                        PONUM = ?, SEQNUM = ?, ITEMNUM = ?, DATERECEIVED = ?, TIMERECEIVED = ?, RECEIPTNUM = ?, LINENUMBER = ?,
                        INVOICENUM = ?, DELIVERYNUM = ?, RECEIVERNUM = ?, VENDORID = ?, VENDORNAME = ?, DESCRIPTIONONPO = ?,
                        QTYREQUESTED = ?, QTYPREVIOUS = ?, QTYRECEIVED = ?, UOP = ?, UOM = ?, RECEIVEWAREHOUSE = ?, DESTID = ?, DUEDATE = ?,
                        ITEMTYPE = ?, RECEIVEDRESULT = ?, RECEIVEDREASON = ?, WAREHOUSECOMMENT = ?,
                        ENDUSERCOMMENT = ?, PURCHASECOMMENT = ?, GATEPASS = ?
                    WHERE RECORDNUM = ?";

            $stmt = $this->conn_mats->prepare($sql);
            $stmt->execute([
                strtoupper(trim($input['po_num'] ?? '')),
                floatval($input['seq_num'] ?? 0),
                strtoupper(trim($input['item_num'] ?? '')),
                $dateReceived,
                $dateTimeStr,
                intval($input['receipt_num'] ?? 1),
                intval($input['line_number'] ?? 1),
                strtoupper(trim($input['invoice_num'] ?? '')),
                strtoupper(trim($input['delivery_num'] ?? '')),
                strtoupper(trim($input['receiver_num'] ?? '')),
                strtoupper(trim($input['vendor_id'] ?? '')),
                trim($input['vendor_name'] ?? ''),
                strtoupper(trim($input['description_on_po'] ?? '')),
                floatval($input['qty_requested'] ?? 0),
                floatval($input['qty_previous'] ?? 0),
                floatval($input['qty_received'] ?? 0),
                trim($input['uop'] ?? ''),
                trim($input['uom'] ?? ''),
                trim($input['receive_warehouse'] ?? ''),
                trim($input['dest_id'] ?? ''),
                $dueDate,
                strtoupper(trim($input['item_type'] ?? '')),
                trim($input['received_result'] ?? 'ACCEPT'),
                trim($input['received_reason'] ?? 'NONE'),
                trim($input['warehouse_comment'] ?? ''),
                trim($input['enduser_comment'] ?? ''),
                trim($input['purchase_comment'] ?? ''),
                $gatePass,
                $recordNum
            ]);

            $this->conn_mats->commit();
            return ['status' => 'success', 'message' => "แก้ไขเอกสาร {$recordNum} เรียบร้อยแล้ว"];
        } catch (PDOException $e) {
            if ($this->conn_mats->inTransaction()) $this->conn_mats->rollBack();
            return ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }

    // 6. ลบข้อมูล
    public function po_delete_record($recordNum) {
        if (!$this->is_authorized) {
            return ['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ในการลบข้อมูล'];
        }
        try {
            $stmt = $this->conn_mats->prepare("DELETE FROM PRCHRCRD1 WHERE RECORDNUM = ?");
            $stmt->execute([trim($recordNum)]);
            return ['status' => 'success', 'message' => "ลบเอกสาร {$recordNum} เรียบร้อยแล้ว"];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'ไม่สามารถลบข้อมูลได้: ' . $e->getMessage()];
        }
    }
}

// =========================================================================
// ส่วนรับ Request แบบ AJAX / JSON (Endpoint)
// =========================================================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../dbcon_mats-new.php';
    $conn_mats = $conn;
    unset($conn);

    require_once __DIR__ . '/../dbcon_mp2_mats.php';
    $conn_mp2 = $conn;
    unset($conn);

    $model  = new PoReceiveModel($conn_mats, $conn_mp2);
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'po_save':
            echo json_encode($model->po_save_record($_POST), JSON_UNESCAPED_UNICODE);
            break;
        case 'po_update':
            echo json_encode($model->po_update_record($_POST), JSON_UNESCAPED_UNICODE);
            break;
        case 'po_delete':
            echo json_encode($model->po_delete_record($_POST['record_num'] ?? ''), JSON_UNESCAPED_UNICODE);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
            break;
    }
    exit();
}
?>