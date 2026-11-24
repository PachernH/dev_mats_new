<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['success' => false, 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่']);
    exit();
}

require_once '../function_mats.php';
require_once '../dbcon_mats-new.php';

// รองรับทั้ง PDO และ sqlsrv จาก dbcon_mats-new.php
$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);

function db_begin($db) {
    if ($db instanceof PDO) { $db->beginTransaction(); }
    elseif (function_exists('sqlsrv_begin_transaction')) { sqlsrv_begin_transaction($db); }
}
function db_commit($db) {
    if ($db instanceof PDO) { $db->commit(); }
    elseif (function_exists('sqlsrv_commit')) { sqlsrv_commit($db); }
}
function db_rollback($db) {
    if ($db instanceof PDO) { if ($db->inTransaction()) $db->rollBack(); }
    elseif (function_exists('sqlsrv_rollback')) { sqlsrv_rollback($db); }
}
function db_fetch_one($db, $sql, $params = []) {
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (function_exists('sqlsrv_query')) {
        $stmt = sqlsrv_query($db, $sql, $params);
        if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    return null;
}
function db_fetch_all($db, $sql, $params = []) {
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (function_exists('sqlsrv_query')) {
        $stmt = sqlsrv_query($db, $sql, $params);
        if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if (isset($row['LME_DATE']) && $row['LME_DATE'] instanceof DateTime) {
                $row['LME_DATE'] = $row['LME_DATE']->format('Y-m-d');
            }
            $rows[] = $row;
        }
        return $rows;
    }
    return [];
}
function db_execute($db, $sql, $params = []) {
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } elseif (function_exists('sqlsrv_query')) {
        $stmt = sqlsrv_query($db, $sql, $params);
        if ($stmt === false) throw new Exception(print_r(sqlsrv_errors(), true));
        return true;
    }
    return false;
}

// ดาวน์โหลดไฟล์ตัวอย่าง CSV
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="LME3Month_Template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['LME_DATE', 'LME3M_DAILY', 'LME3M_WBPLME', 'LME3M_WBP', 'LME3M_MONTHLY', 'STDEV', 'FACTOR', 'STDXFACTOR', 'MJP']);
    fputcsv($out, [date('m/d/Y'), '2450.50', '2440.00', '2435.25', '2445.00', '15.20', '1.50', '22.80', '145.00']);
    fclose($out);
    exit();
}

// ดูข้อมูลล่าสุดใน LME3MNTH1
if (isset($_GET['action']) && $_GET['action'] === 'get_recent') {
    try {
        $recent = getRecentLmeRows($db);
        echo json_encode(['success' => true, 'recent_rows' => $recent]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// ฟังก์ชันแปลงวันที่จาก CSV (รองรับ YYYY-MM-DD, MM/DD/YYYY และ DD/MM/YYYY)
function normalizeLmeDate($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '00:00:00' || $raw === '01/01/2000') {
        return null;
    }
    if (preg_match('/^(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})\s+/', $raw, $tm)) {
        $raw = $tm[1];
    } elseif (preg_match('/^(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})\s+/', $raw, $tm)) {
        $raw = $tm[1];
    }

    if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $raw, $m)) {
        if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }
    if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
        $p1 = (int)$m[1];
        $p2 = (int)$m[2];
        $y  = (int)$m[3];
        if ($p1 > 12 && checkdate($p2, $p1, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $p2, $p1);
        }
        if (checkdate($p1, $p2, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $p1, $p2);
        }
        if (checkdate($p2, $p1, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $p2, $p1);
        }
        return null;
    }
    $ts = strtotime($raw);
    return ($ts !== false) ? date('Y-m-d', $ts) : null;
}

// ฟังก์ชันตรวจสอบและจัดรูปแบบตัวเลขทศนิยม 2 ตำแหน่ง
function parseLmeNumber($val, &$isValid) {
    $str = str_replace(',', '', trim((string)$val));
    if ($str === '') {
        return 0.00;
    }
    if (!is_numeric($str)) {
        $isValid = false;
        return 0.00;
    }
    return round((float)$str, 2);
}

// ฟังก์ชันตรวจสอบข้อมูลทุกแถวเทียบกับตาราง LME3MNTH1 (ดึงข้อมูลเดิมแบบ Batch เพื่อความรวดเร็ว)
function validateLmeRows($db, $rawRows) {
    $validated = [];
    $seenDates = [];
    $summary = ['total' => 0, 'valid' => 0, 'duplicate' => 0, 'error' => 0];

    $numFields = [
        'lme3m_daily'   => 'LME3M_DAILY',
        'lme3m_wbplme'  => 'LME3M_WBPLME',
        'lme3m_wbp'     => 'LME3M_WBP',
        'lme3m_monthly' => 'LME3M_MONTHLY',
        'stdev'         => 'STDEV',
        'factor'        => 'FACTOR',
        'stdxfactor'    => 'STDXFACTOR',
        'mjp'           => 'MJP'
    ];

    // รวบรวมวันที่ทั้งหมดเพื่อค้นหาใน LME3MNTH1 แบบชุด (ลดจำนวน Round-trip SQL)
    $allDates = [];
    foreach ($rawRows as $item) {
        $nd = normalizeLmeDate($item['lme_date'] ?? '');
        if ($nd !== null) {
            $allDates[$nd] = true;
        }
    }

    $existingMap = [];
    $uniqueDates = array_keys($allDates);
    if (!empty($uniqueDates)) {
        foreach (array_chunk($uniqueDates, 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT CONVERT(varchar(10), LME_DATE, 120) AS LME_DATE_STR, LME3M_DAILY, MJP 
                    FROM LME3MNTH1 
                    WHERE CONVERT(varchar(10), LME_DATE, 120) IN ({$placeholders})";
            $foundRows = db_fetch_all($db, $sql, $chunk);
            foreach ($foundRows as $fr) {
                $dKey = trim((string)($fr['LME_DATE_STR'] ?? ''));
                if ($dKey !== '') {
                    $existingMap[$dKey] = $fr;
                }
            }
        }
    }

    foreach ($rawRows as $idx => $item) {
        $rowNum = isset($item['row_num']) ? (int)$item['row_num'] : ($idx + 1);
        $messages = [];
        $hasError = false;

        $normDate = normalizeLmeDate($item['lme_date'] ?? '');
        if ($normDate === null) {
            $hasError = true;
            $messages[] = 'รูปแบบวันที่ไม่ถูกต้อง (' . ($item['lme_date'] ?? 'ว่าง') . ')';
        }

        $cleanRow = [
            'row_num'  => $rowNum,
            'lme_date' => $normDate ?? ($item['lme_date'] ?? ''),
            'edited'   => !empty($item['edited'])
        ];

        foreach ($numFields as $key => $colLabel) {
            $fieldOk = true;
            $val = parseLmeNumber($item[$key] ?? '', $fieldOk);
            $cleanRow[$key] = number_format($val, 2, '.', '');
            if (!$fieldOk) {
                $hasError = true;
                $messages[] = "ค่า {$colLabel} ไม่ใช่ตัวเลข";
            }
        }

        // เช็กวันที่ซ้ำภายในไฟล์เดียวกัน
        if ($normDate !== null) {
            if (isset($seenDates[$normDate])) {
                $hasError = true;
                $messages[] = "วันที่ {$normDate} ซ้ำกับแถวที่ #{$seenDates[$normDate]} ในไฟล์เดียวกัน";
            } else {
                $seenDates[$normDate] = $rowNum;
            }
        }

        // เช็กวันที่ในตาราง LME3MNTH1 จาก Map ที่ดึงไว้
        $isDuplicateDb = false;
        if (!$hasError && $normDate !== null && isset($existingMap[$normDate])) {
            $isDuplicateDb = true;
            $existing = $existingMap[$normDate];
            $oldDaily = number_format((float)($existing['LME3M_DAILY'] ?? 0), 2);
            $oldMjp   = number_format((float)($existing['MJP'] ?? 0), 2);
            $messages[] = "พบข้อมูลเดิมใน LME3MNTH1 (Dailyเดิม: {$oldDaily}, MJPเดิม: {$oldMjp})";
        }

        if ($hasError) {
            $cleanRow['status'] = 'ERROR';
            $summary['error']++;
        } elseif ($isDuplicateDb) {
            $cleanRow['status'] = 'DUPLICATE';
            $summary['duplicate']++;
        } else {
            $cleanRow['status'] = 'VALID';
            $summary['valid']++;
        }

        $cleanRow['messages'] = $messages;
        $summary['total']++;
        $validated[] = $cleanRow;
    }

    return ['rows' => $validated, 'summary' => $summary];
}

function getRecentLmeRows($db) {
    return db_fetch_all(
        $db,
        "SELECT TOP 50 CONVERT(varchar(10), LME_DATE, 120) AS LME_DATE, 
                LME3M_DAILY, LME3M_WBPLME, LME3M_WBP, LME3M_MONTHLY, 
                STDEV, FACTOR, STDXFACTOR, MJP 
         FROM LME3MNTH1 
         ORDER BY LME_DATE DESC"
    );
}

// จัดการ Request แบบ JSON (revalidate / commit) หรือ FormData (validate)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonInput = (stripos($contentType, 'application/json') !== false)
    ? json_decode(file_get_contents('php://input'), true)
    : null;

$action = $jsonInput['action'] ?? ($_POST['action'] ?? '');

try {
    // 1. อ่านไฟล์ CSV และคัดกรองเฉพาะ "ข้อมูลใหม่ทั้งหมด + ข้อมูลเดิมย้อนหลังล่าสุด 50 รายการ"
    if ($action === 'validate') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ไม่พบไฟล์ CSV หรืออัปโหลดไฟล์ไม่สำเร็จ');
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('ไม่สามารถเปิดอ่านไฟล์ CSV ได้');
        }

        $rawRows = [];
        $lineIndex = 0;

        while (($cols = fgetcsv($handle, 2000, ',')) !== false && count($rawRows) < 5000) {
            $lineIndex++;
            if ($lineIndex === 1 && isset($cols[0])) {
                $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cols[0]);
            }

            $colDate = trim($cols[0] ?? '');
            if ($colDate === '' && implode('', $cols) === '') {
                break;
            }

            // ข้ามแถวหัวตาราง (Header) หากบรรทัดแรกไม่ใช่รูปแบบวันที่
            if ($lineIndex === 1 && normalizeLmeDate($colDate) === null && preg_match('/[a-zA-Z]/', $colDate)) {
                continue;
            }

            $rawRows[] = [
                'row_num'       => $lineIndex,
                'lme_date'      => $colDate,
                'lme3m_daily'   => $cols[1] ?? '0.00',
                'lme3m_wbplme'  => $cols[2] ?? '0.00',
                'lme3m_wbp'     => $cols[3] ?? '0.00',
                'lme3m_monthly' => $cols[4] ?? '0.00',
                'stdev'         => $cols[5] ?? '0.00',
                'factor'        => $cols[6] ?? '0.00',
                'stdxfactor'    => $cols[7] ?? '0.00',
                'mjp'           => $cols[8] ?? '0.00',
                'edited'        => false
            ];
        }
        fclose($handle);

        if (empty($rawRows)) {
            throw new Exception('ไม่พบข้อมูลรายการในไฟล์ CSV');
        }

        $result = validateLmeRows($db, $rawRows);
        $fileTotalRows = $result['summary']['total'];
        $fileTotalDup  = $result['summary']['duplicate'];

        // แยกกลุ่มข้อมูลเพื่อคัดกรอง
        $errorRows = [];
        $validRows = [];
        $dupRows   = [];

        foreach ($result['rows'] as $r) {
            if ($r['status'] === 'ERROR') {
                $errorRows[] = $r;
            } elseif ($r['status'] === 'VALID') {
                $validRows[] = $r;
            } else {
                $dupRows[] = $r;
            }
        }

        // เรียงข้อมูลใหม่ (VALID) จากวันที่ล่าสุด -> เก่าสุด
        usort($validRows, function ($a, $b) {
            return strcmp($b['lme_date'], $a['lme_date']);
        });

        // เรียงข้อมูลเดิม (DUPLICATE) จากวันที่ล่าสุด -> เก่าสุด แล้วตัดเอาเฉพาะ 50 รายการล่าสุด
        usort($dupRows, function ($a, $b) {
            return strcmp($b['lme_date'], $a['lme_date']);
        });
        $latest50DupRows = array_slice($dupRows, 0, 50);

        // รวมรายการที่จะแสดงผล: Error (ถ้ามี) + ข้อมูลใหม่ทั้งหมด + ข้อมูลเดิมล่าสุด 50 รายการ
        $filteredRows = array_merge($errorRows, $validRows, $latest50DupRows);

        $filteredSummary = [
            'total'          => count($filteredRows),
            'valid'          => count($validRows),
            'duplicate'      => count($latest50DupRows),
            'error'          => count($errorRows),
            'file_total'     => $fileTotalRows,
            'file_dup_total' => $fileTotalDup
        ];

        echo json_encode([
            'success' => true,
            'rows'    => $filteredRows,
            'summary' => $filteredSummary
        ]);
        exit();
    }

    // 2. ตรวจสอบข้อมูลซ้ำหลังจาก User แก้ไขในตาราง
    if ($action === 'revalidate') {
        $rows = $jsonInput['rows'] ?? [];
        $result = validateLmeRows($db, $rows);
        echo json_encode([
            'success' => true,
            'rows'    => $result['rows'],
            'summary' => $result['summary']
        ]);
        exit();
    }

    // 3. บันทึกลงตาราง LME3MNTH1 ภายใต้ Transaction (INSERT / UPDATE)
    if ($action === 'commit') {
        $rows = $jsonInput['rows'] ?? [];
        $overwriteDup = !empty($jsonInput['overwrite_dup']);

        if (empty($rows)) {
            throw new Exception('ไม่มีรายการข้อมูลสำหรับบันทึก');
        }

        $check = validateLmeRows($db, $rows);
        if ($check['summary']['error'] > 0) {
            throw new Exception('พบข้อมูลที่ไม่ถูกต้องจำนวน ' . $check['summary']['error'] . ' รายการ กรุณาแก้ไขก่อนบันทึก');
        }

        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        db_begin($db);
        try {
            foreach ($check['rows'] as $r) {
                $lmeDate      = $r['lme_date'];
                $lmeDateTime  = $lmeDate . ' 00:00:00';
                $lme3mDaily   = round((float)$r['lme3m_daily'], 2);
                $lme3mWbplme  = round((float)$r['lme3m_wbplme'], 2);
                $lme3mWbp     = round((float)$r['lme3m_wbp'], 2);
                $lme3mMonthly = round((float)$r['lme3m_monthly'], 2);
                $stdev        = round((float)$r['stdev'], 2);
                $factor       = round((float)$r['factor'], 2);
                $stdxfactor   = round((float)$r['stdxfactor'], 2);
                $mjp          = round((float)$r['mjp'], 2);

                $existing = db_fetch_one(
                    $db,
                    "SELECT LME_DATE FROM LME3MNTH1 WHERE CONVERT(varchar(10), LME_DATE, 120) = ?",
                    [$lmeDate]
                );

                if ($existing) {
                    if ($overwriteDup) {
                        $sqlUpdate = "UPDATE LME3MNTH1 SET 
                                        LME3M_DAILY = ?, LME3M_WBPLME = ?, LME3M_WBP = ?, LME3M_MONTHLY = ?, 
                                        STDEV = ?, FACTOR = ?, STDXFACTOR = ?, MJP = ? 
                                      WHERE CONVERT(varchar(10), LME_DATE, 120) = ?";
                        db_execute($db, $sqlUpdate, [
                            $lme3mDaily, $lme3mWbplme, $lme3mWbp, $lme3mMonthly,
                            $stdev, $factor, $stdxfactor, $mjp, $lmeDate
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $sqlInsert = "INSERT INTO LME3MNTH1 (
                                    LME_DATE, LME3M_DAILY, LME3M_WBPLME, LME3M_WBP, LME3M_MONTHLY, 
                                    STDEV, FACTOR, STDXFACTOR, MJP
                                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    db_execute($db, $sqlInsert, [
                        $lmeDateTime, $lme3mDaily, $lme3mWbplme, $lme3mWbp, $lme3mMonthly,
                        $stdev, $factor, $stdxfactor, $mjp
                    ]);
                    $inserted++;
                }
            }

            db_commit($db);
        } catch (Exception $txEx) {
            db_rollback($db);
            throw $txEx;
        }

        $recentRows = getRecentLmeRows($db);
        echo json_encode([
            'success'     => true,
            'message'     => "บันทึกข้อมูลลงตาราง LME3MNTH1 สำเร็จ!\n• เพิ่มข้อมูลใหม่ (Insert): {$inserted} รายการ\n• อัปเดตข้อมูลเดิม (Update): {$updated} รายการ\n• ข้ามรายการซ้ำ (Skip): {$skipped} รายการ",
            'recent_rows' => $recentRows
        ]);
        exit();
    }

    throw new Exception('Invalid Action');

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}