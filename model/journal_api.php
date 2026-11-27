<?php
// model/journal_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ Session
if (empty($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit();
}

$folder_func     = isset($_SESSION['FUNC']) ? strtoupper(trim($_SESSION['FUNC'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';
$is_authorized   = ($department_func === 'FN' || $department_func === 'IT');

// เชื่อมต่อ Database MATS-NEWII
require_once __DIR__ . '/../dbcon_mats-new.php';
$conn_mats = $conn;

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

try {
    switch ($action) {
        // =========================================================================
        // 1. LIST & SEARCH JOURNAL (Default TOP 200, Full Search when filtered)
        // =========================================================================
        case 'list':
            $journal_no   = trim($_GET['journal_no'] ?? '');
            $journal_type = trim($_GET['journal_type'] ?? '');
            $date_from    = trim($_GET['date_from'] ?? '');
            $date_to      = trim($_GET['date_to'] ?? '');

            // ตรวจสอบว่ามีการระบุเงื่อนไขค้นหาหรือไม่
            $is_filtered = ($journal_no !== '' || $date_from !== '' || $date_to !== '');
            $top_clause  = $is_filtered ? "" : "TOP 200";

            $sql = "SELECT {$top_clause}
                        A.JOURNAL_TYPE,
                        A.JOURNAL_NO,
                        A.JOURNAL_PERIOD,
                        A.TAXDEDUCT_NO,
                        A.CSTMSPPL_ID,
                        B.EN_COMPANY,
                        B.TH_COMPANY,
                        CONVERT(VARCHAR(10), A.TRANSACTION_DATE, 120) AS TRANSACTION_DATE,
                        CONVERT(VARCHAR(10), A.TRANSACTION_DATE, 103) AS TRANSACTION_DATE_FMT,
                        CONVERT(VARCHAR(10), A.DUE_DATE, 120) AS DUE_DATE,
                        CONVERT(VARCHAR(10), A.DUE_DATE, 103) AS DUE_DATE_FMT,
                        ISNULL(A.TOTAL_DEBITAMOUNT, 0) AS TOTAL_DEBITAMOUNT,
                        ISNULL(A.TOTAL_CREDITAMOUNT, 0) AS TOTAL_CREDITAMOUNT,
                        ISNULL(A.TOTAL_EXCLUDEVATAMOUNT, 0) AS TOTAL_EXCLUDEVATAMOUNT,
                        ISNULL(A.TOTAL_TAXAMOUNT, 0) AS TOTAL_TAXAMOUNT,
                        A.REMARK
                    FROM JRNLVCHR10 A
                    LEFT JOIN CSSPMSTR22 B ON A.CSTMSPPL_ID = B.CSTMSPPL_ID
                    WHERE 1=1 ";
            $params = [];

            if ($journal_no !== '') {
                $sql .= " AND A.JOURNAL_NO LIKE :journal_no ";
                $params[':journal_no'] = '%' . $journal_no . '%';
            }
            if ($journal_type !== '') {
                $sql .= " AND A.JOURNAL_TYPE = :journal_type ";
                $params[':journal_type'] = $journal_type;
            }
            if ($date_from !== '') {
                $sql .= " AND A.TRANSACTION_DATE >= :date_from ";
                $params[':date_from'] = $date_from . ' 00:00:00';
            }
            if ($date_to !== '') {
                $sql .= " AND A.TRANSACTION_DATE <= :date_to ";
                $params[':date_to'] = $date_to . ' 23:59:59';
            }

            $sql .= " ORDER BY A.TRANSACTION_DATE DESC, A.JOURNAL_NO DESC";

            $stmt = $conn_mats->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'      => 'success',
                'is_filtered' => $is_filtered,
                'data'        => $rows
            ]);
            break;
        // =========================================================================
        // 2. GET JOURNAL DETAIL (HEADING + ITEMS)
        // =========================================================================
        case 'detail':
            $journal_type   = trim($_GET['journal_type'] ?? '');
            $journal_period = trim($_GET['journal_period'] ?? '');
            $journal_no     = trim($_GET['journal_no'] ?? '');

            if ($journal_no === '') {
                throw new Exception('กรุณาระบุเลขที่เอกสาร (JOURNAL_NO)');
            }

            $sqlHead = "SELECT 
                            A.*,
                            CONVERT(VARCHAR(10), A.TRANSACTION_DATE, 120) AS TRANSACTION_DATE_ISO,
                            CONVERT(VARCHAR(10), A.TRANSACTION_DATE, 103) AS TRANSACTION_DATE_FMT,
                            CONVERT(VARCHAR(10), A.DUE_DATE, 120) AS DUE_DATE_ISO,
                            CONVERT(VARCHAR(10), A.DUE_DATE, 103) AS DUE_DATE_FMT,
                            B.EN_COMPANY,
                            B.TH_COMPANY,
                            B.TAX_REGISTRATION
                        FROM JRNLVCHR10 A
                        LEFT JOIN CSSPMSTR22 B ON A.CSTMSPPL_ID = B.CSTMSPPL_ID
                        WHERE A.JOURNAL_NO = :journal_no ";
            $paramsHead = [':journal_no' => $journal_no];

            if ($journal_type !== '') {
                $sqlHead .= " AND A.JOURNAL_TYPE = :journal_type ";
                $paramsHead[':journal_type'] = $journal_type;
            }
            if ($journal_period !== '') {
                $sqlHead .= " AND A.JOURNAL_PERIOD = :journal_period ";
                $paramsHead[':journal_period'] = $journal_period;
            }

            $stmtHead = $conn_mats->prepare($sqlHead);
            $stmtHead->execute($paramsHead);
            $heading = $stmtHead->fetch(PDO::FETCH_ASSOC);

            if (!$heading) {
                throw new Exception('ไม่พบข้อมูลเอกสารเลขที่ ' . $journal_no);
            }

            $sqlDet = "SELECT * FROM JRNLVCHR20 
                       WHERE JOURNAL_TYPE = :j_type 
                         AND JOURNAL_PERIOD = :j_period 
                         AND JOURNAL_NO = :j_no 
                       ORDER BY JOURNAL_ITEM ASC";
            $stmtDet = $conn_mats->prepare($sqlDet);
            $stmtDet->execute([
                ':j_type'   => $heading['JOURNAL_TYPE'],
                ':j_period' => $heading['JOURNAL_PERIOD'],
                ':j_no'     => $heading['JOURNAL_NO']
            ]);
            $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'heading' => $heading,
                    'items'   => $items
                ]
            ]);
            break;

        // =========================================================================
        // 3. GET MASTER DATA (ดึงจาก MATS-NEWII และประวัติที่เคยบันทึกใน JRNLVCHR)
        // =========================================================================
        case 'master_data':
            // 1) Suppliers from MATS-NEWII
            $stmtSup = $conn_mats->query("SELECT CSTMSPPL_ID, ACCOUNT_CODE, EN_COMPANY, TH_COMPANY, TAX_CATEGORY, TAX_REGISTRATION 
                                          FROM CSSPMSTR22 ORDER BY CSTMSPPL_ID ASC");
            $suppliers = $stmtSup->fetchAll(PDO::FETCH_ASSOC);

            // 2) ดึงประวัติรหัสบัญชีที่เคยใช้จาก JRNLVCHR20 มาเป็นตัวช่วยพิมพ์ (Autocomplete)
            $stmtAcc = $conn_mats->query("SELECT ACCOUNT_CODE AS ACCNT_CODE, MAX(ACCOUNT_NAME) AS ACCNT_NAME 
                                          FROM JRNLVCHR20 
                                          WHERE ACCOUNT_CODE <> '' 
                                          GROUP BY ACCOUNT_CODE 
                                          ORDER BY ACCOUNT_CODE ASC");
            $accounts = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

            // 3) ดึงประวัติ Department / Cost Center ที่เคยใช้
            $stmtT1 = $conn_mats->query("SELECT DISTINCT COST_CENTER AS CODE FROM JRNLVCHR20 WHERE COST_CENTER <> '' 
                                         UNION 
                                         SELECT DISTINCT DEPARTMENT AS CODE FROM JRNLVCHR10 WHERE DEPARTMENT <> '' 
                                         ORDER BY CODE ASC");
            $t1_codes = $stmtT1->fetchAll(PDO::FETCH_ASSOC);

            // 4) ดึงประวัติ Cash Flow ที่เคยใช้
            $stmtT7 = $conn_mats->query("SELECT DISTINCT CASH_FLOW AS CODE FROM JRNLVCHR10 WHERE CASH_FLOW <> '' ORDER BY CODE ASC");
            $t7_codes = $stmtT7->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'suppliers'    => $suppliers,
                    'accounts'     => $accounts,
                    'departments'  => $t1_codes,
                    'cost_centers' => $t1_codes,
                    'cash_flows'   => $t7_codes
                ]
            ]);
            break;

        // =========================================================================
        // 4. SAVE (CREATE NEW OR UPDATE EXISTING JOURNAL)
        // =========================================================================
        case 'save':
            if (!$is_authorized) {
                throw new Exception('คุณไม่มีสิทธิ์ในการบันทึกหรือแก้ไขข้อมูล (เฉพาะแผนก FN / IT)');
            }

            $mode           = $input['mode'] ?? 'create'; // 'create' or 'edit'
            $journal_type   = strtoupper(trim($input['journal_type'] ?? 'PV'));
            $journal_period = trim($input['journal_period'] ?? '');
            $journal_no     = strtoupper(trim($input['journal_no'] ?? ''));
            $cstmsppl_id    = trim($input['cstmsppl_id'] ?? '');
            $department     = trim($input['department'] ?? '');
            $cash_flow      = trim($input['cash_flow'] ?? '');
            $cheque_no      = strtoupper(trim($input['cheque_no'] ?? ''));
            $tax_category   = trim($input['tax_category'] ?? '');
            $trans_date     = trim($input['transaction_date'] ?? '');
            $due_date       = trim($input['due_date'] ?? '');
            $pay_condition  = intval($input['pay_condition'] ?? 0);
            $remark         = trim($input['remark'] ?? '');
            $items          = $input['items'] ?? [];

            if (!in_array($journal_type, ['PV', 'PC', 'JV'])) {
                throw new Exception('ประเภทเอกสารไม่ถูกต้อง (ต้องเป็น PV, PC หรือ JV)');
            }
            if (strlen($journal_period) !== 4) {
                throw new Exception('งวดบัญชี (Period) ต้องมี 4 หลัก (YYMM)');
            }
            if ($trans_date === '' || $due_date === '') {
                throw new Exception('กรุณาระบุวันที่ทำรายการ และวันครบกำหนด');
            }
            if (empty($items)) {
                throw new Exception('กรุณเพิ่มรายการบัญชีอย่างน้อย 1 รายการ');
            }

            $conn_mats->beginTransaction();

            // -----------------------------------------------------------------
            // A. จัดการเลขที่เอกสาร (JOURNAL_NO) กรณี Create และไม่ได้คีย์เลขเอง
            // -----------------------------------------------------------------
            if ($mode === 'create') {
                if ($journal_no === '') {
                    $prefix = $journal_type . $journal_period; // เช่น PV2611
                    $sqlMaxNo = "SELECT MAX(CAST(RIGHT(JOURNAL_NO, 3) AS INT)) AS MAX_RUN 
                                 FROM JRNLVCHR10 WITH (UPDLOCK, HOLDLOCK)
                                 WHERE JOURNAL_TYPE = :j_type 
                                   AND JOURNAL_PERIOD = :j_period 
                                   AND JOURNAL_NO LIKE :prefix 
                                   AND LEN(JOURNAL_NO) = 9";
                    $stmtMax = $conn_mats->prepare($sqlMaxNo);
                    $stmtMax->execute([
                        ':j_type'   => $journal_type,
                        ':j_period' => $journal_period,
                        ':prefix'   => $prefix . '%'
                    ]);
                    $maxRun = intval($stmtMax->fetchColumn());
                    $nextRun = str_pad($maxRun + 1, 3, '0', STR_PAD_LEFT);
                    $journal_no = $prefix . $nextRun;
                } else {
                    // ตรวจสอบรูปแบบกรณีกรอกเลขเอง (6 หลัก หรือ 9 หลัก)
                    if (strlen($journal_no) === 6) {
                        $firstDigit = ['PV' => '3', 'PC' => '4', 'JV' => '1'][$journal_type];
                        if (substr($journal_no, 0, 1) !== $firstDigit || substr($journal_no, 1, 2) !== substr($journal_period, 0, 2)) {
                            throw new Exception("เลขที่เอกสาร 6 หลักไม่ตรงตามเงื่อนไขของ {$journal_type} ในปี " . substr($journal_period, 0, 2));
                        }
                    } elseif (strlen($journal_no) === 9) {
                        if (substr($journal_no, 0, 2) !== $journal_type || substr($journal_no, 2, 4) !== $journal_period) {
                            throw new Exception("เลขที่เอกสาร 9 หลักต้องขึ้นต้นด้วย {$journal_type}{$journal_period}");
                        }
                    } else {
                        throw new Exception('เลขที่เอกสารต้องมีความยาว 6 หลัก หรือ 9 หลักเท่านั้น');
                    }

                    // เช็กซ้ำ
                    $stmtDup = $conn_mats->prepare("SELECT COUNT(*) FROM JRNLVCHR10 WHERE JOURNAL_TYPE = ? AND JOURNAL_PERIOD = ? AND JOURNAL_NO = ?");
                    $stmtDup->execute([$journal_type, $journal_period, $journal_no]);
                    if ($stmtDup->fetchColumn() > 0) {
                        throw new Exception("เลขที่เอกสาร {$journal_no} มีอยู่ในระบบแล้ว");
                    }
                }
            }

            // -----------------------------------------------------------------
            // B. คำนวณยอดรวม และตรวจสอบรายการภาษีหัก ณ ที่จ่าย
            // -----------------------------------------------------------------
            $total_debit       = 0.0;
            $total_credit      = 0.0;
            $total_exclude_vat = 0.0;
            $total_tax         = 0.0;
            $has_tax_deduct    = false;

            foreach ($items as $idx => $item) {
                $acc_code = trim($item['account_code'] ?? '');
                if (strlen($acc_code) <= 3) {
                    throw new Exception("รายการที่ " . ($idx + 1) . ": รหัสบัญชีต้องมากกว่า 3 ตัวอักษร");
                }

                $dc          = strtoupper(trim($item['debit_credit'] ?? 'C'));
                $exp_cat     = trim($item['expense_category'] ?? '');
                $ex_vat_amt  = abs(floatval($item['excludevat_amount'] ?? 0));
                $tax_percent = floatval($item['tax_percent'] ?? 0);

                if ($exp_cat === '') {
                    $tax_percent = 0;
                    $amount = $ex_vat_amt;
                } else {
                    $has_tax_deduct = true;
                    $amount = round($ex_vat_amt * ($tax_percent / 100), 2);
                    $total_exclude_vat += $ex_vat_amt;
                    $total_tax         += $amount;
                }

                if ($dc === 'D') {
                    $total_debit += $amount;
                } else {
                    $total_credit += $amount;
                }

                $items[$idx]['computed_amount']      = $amount;
                $items[$idx]['computed_tax_percent'] = $tax_percent;
                $items[$idx]['computed_ex_vat']      = $ex_vat_amt;
                $items[$idx]['computed_dc']          = $dc;
            }

            $balance = round($total_debit - $total_credit, 2);

            // -----------------------------------------------------------------
            // C. จัดการเลขที่หนังสือรับรองหักภาษี ณ ที่จ่าย (TAXDEDUCT_NO)
            // -----------------------------------------------------------------
            $existing_tax_no = trim($input['taxdeduct_no'] ?? '');
            $taxdeduct_no    = '';

            if ($has_tax_deduct) {
                if ($existing_tax_no !== '') {
                    $taxdeduct_no = $existing_tax_no;
                } else {
                    // รันเลขใหม่จาก JRNLVCHR10 แยกตามปี (YY) และรหัสภาษี (03 / 53)
                    $yy = substr($journal_period, 0, 2);
                    $tax_cat_code = ($tax_category === '3' || $tax_category === '03') ? '03' : '53';
                    $tax_prefix   = "{$yy}-{$tax_cat_code}-"; // เช่น 26-53-

                    $sqlMaxTax = "SELECT MAX(CAST(RIGHT(TAXDEDUCT_NO, 4) AS INT)) AS MAX_TAX 
                                  FROM JRNLVCHR10 WITH (UPDLOCK, HOLDLOCK)
                                  WHERE TAXDEDUCT_NO LIKE :tax_prefix 
                                    AND LEN(TAXDEDUCT_NO) = 10";
                    $stmtTax = $conn_mats->prepare($sqlMaxTax);
                    $stmtTax->execute([':tax_prefix' => $tax_prefix . '%']);
                    $maxTaxRun = intval($stmtTax->fetchColumn());
                    $taxdeduct_no = $tax_prefix . str_pad($maxTaxRun + 1, 4, '0', STR_PAD_LEFT);
                }
            }

            // -----------------------------------------------------------------
            // D. บันทึกส่วนหัว (JRNLVCHR10)
            // -----------------------------------------------------------------
            if ($mode === 'create') {
                $sqlInsertHead = "INSERT INTO JRNLVCHR10 (
                                    JOURNAL_TYPE, JOURNAL_PERIOD, JOURNAL_NO, TAXDEDUCT_NO, CSTMSPPL_ID, 
                                    DEPARTMENT, CASH_FLOW, CHEQUE_NO, TAX_CATEGORY, TRANSACTION_DATE, 
                                    DUE_DATE, TOTAL_DEBITAMOUNT, TOTAL_CREDITAMOUNT, TOTAL_EXCLUDEVATAMOUNT, 
                                    TOTAL_TAXAMOUNT, PAY_CONDITION, REMARK
                                  ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                $stmtInsH = $conn_mats->prepare($sqlInsertHead);
                $stmtInsH->execute([
                    $journal_type, $journal_period, $journal_no, $taxdeduct_no, $cstmsppl_id,
                    $department, $cash_flow, $cheque_no, $tax_category, $trans_date,
                    $due_date, $total_debit, $total_credit, $total_exclude_vat,
                    $total_tax, $pay_condition, $remark
                ]);
            } else {
                $sqlUpdateHead = "UPDATE JRNLVCHR10 SET 
                                    TAXDEDUCT_NO = ?, CSTMSPPL_ID = ?, DEPARTMENT = ?, CASH_FLOW = ?, 
                                    CHEQUE_NO = ?, TAX_CATEGORY = ?, TRANSACTION_DATE = ?, DUE_DATE = ?, 
                                    TOTAL_DEBITAMOUNT = ?, TOTAL_CREDITAMOUNT = ?, TOTAL_EXCLUDEVATAMOUNT = ?, 
                                    TOTAL_TAXAMOUNT = ?, PAY_CONDITION = ?, REMARK = ?
                                  WHERE JOURNAL_TYPE = ? AND JOURNAL_PERIOD = ? AND JOURNAL_NO = ?";
                $stmtUpdH = $conn_mats->prepare($sqlUpdateHead);
                $stmtUpdH->execute([
                    $taxdeduct_no, $cstmsppl_id, $department, $cash_flow,
                    $cheque_no, $tax_category, $trans_date, $due_date,
                    $total_debit, $total_credit, $total_exclude_vat,
                    $total_tax, $pay_condition, $remark,
                    $journal_type, $journal_period, $journal_no
                ]);

                // ลบรายการย่อยเก่าออกก่อนเพื่อบันทึกใหม่ทั้งหมดให้ลำดับ ITEM ถูกต้อง
                $stmtDelOld = $conn_mats->prepare("DELETE FROM JRNLVCHR20 WHERE JOURNAL_TYPE = ? AND JOURNAL_PERIOD = ? AND JOURNAL_NO = ?");
                $stmtDelOld->execute([$journal_type, $journal_period, $journal_no]);
            }

            // -----------------------------------------------------------------
            // E. บันทึกรายการย่อย (JRNLVCHR20)
            // -----------------------------------------------------------------
            $sqlInsertDet = "INSERT INTO JRNLVCHR20 (
                                JOURNAL_TYPE, JOURNAL_PERIOD, JOURNAL_NO, JOURNAL_ITEM, TAXDEDUCT_NO, 
                                INVOICE_NO, ACCOUNT_CODE, ACCOUNT_NAME, COST_CENTER, TAX_CATEGORY, 
                                EXPENSE_CATEGORY, DEBIT_CREDIT, EXCLUDEVAT_AMOUNT, TAX_PERCENT, AMOUNT, CHECK_ITEM
                             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmtInsD = $conn_mats->prepare($sqlInsertDet);

            foreach ($items as $idx => $item) {
                $item_no   = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);
                $acc_code  = strtoupper(trim($item['account_code']));
                $acc_name  = trim($item['account_name'] ?? '');
                $inv_no    = trim($item['invoice_no'] ?? '');
                $cost_ctr  = trim($item['cost_center'] ?? '');
                $exp_cat   = trim($item['expense_category'] ?? '');
                $item_tax_no = ($exp_cat !== '') ? $taxdeduct_no : '';

                // เงื่อนไข CHECK_ITEM: หากรหัสบัญชีขึ้นต้นด้วย '61' และ Balance = 0 ให้เป็น 'C'
                $check_item = 'N';
                if (substr($acc_code, 0, 2) === '61' && $balance == 0) {
                    $check_item = 'C';
                }

                $stmtInsD->execute([
                    $journal_type, $journal_period, $journal_no, $item_no, $item_tax_no,
                    $inv_no, $acc_code, $acc_name, $cost_ctr, $tax_category,
                    $exp_cat, $item['computed_dc'], $item['computed_ex_vat'], $item['computed_tax_percent'],
                    $item['computed_amount'], $check_item
                ]);
            }

            $conn_mats->commit();

            echo json_encode([
                'status'  => 'success',
                'message' => "บันทึกข้อมูลเอกสารเลขที่ {$journal_no} เรียบร้อยแล้ว",
                'data'    => [
                    'journal_type'   => $journal_type,
                    'journal_period' => $journal_period,
                    'journal_no'     => $journal_no,
                    'taxdeduct_no'   => $taxdeduct_no
                ]
            ]);
            break;

        // =========================================================================
        // 5. DELETE JOURNAL (HEADING + DETAILS)
        // =========================================================================
        case 'delete':
            if (!$is_authorized) {
                throw new Exception('คุณไม่มีสิทธิ์ในการลบข้อมูล (เฉพาะแผนก FN / IT)');
            }

            $journal_type   = trim($input['journal_type'] ?? '');
            $journal_period = trim($input['journal_period'] ?? '');
            $journal_no     = trim($input['journal_no'] ?? '');

            if ($journal_type === '' || $journal_period === '' || $journal_no === '') {
                throw new Exception('ข้อมูลอ้างอิงเอกสารไม่ครบถ้วน');
            }

            $conn_mats->beginTransaction();

            $stmtDelDet = $conn_mats->prepare("DELETE FROM JRNLVCHR20 WHERE JOURNAL_TYPE = ? AND JOURNAL_PERIOD = ? AND JOURNAL_NO = ?");
            $stmtDelDet->execute([$journal_type, $journal_period, $journal_no]);

            $stmtDelHead = $conn_mats->prepare("DELETE FROM JRNLVCHR10 WHERE JOURNAL_TYPE = ? AND JOURNAL_PERIOD = ? AND JOURNAL_NO = ?");
            $stmtDelHead->execute([$journal_type, $journal_period, $journal_no]);

            $conn_mats->commit();

            echo json_encode([
                'status'  => 'success',
                'message' => "ลบเอกสารเลขที่ {$journal_no} เรียบร้อยแล้ว"
            ]);
            break;

        default:
            throw new Exception('Invalid API Action');
    }
} catch (Exception $e) {
    if (isset($conn_mats) && $conn_mats->inTransaction()) {
        $conn_mats->rollBack();
    }
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}