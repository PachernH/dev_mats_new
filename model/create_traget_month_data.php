<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['ID'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.'
    ]);
    exit();
}

require_once '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$mode = isset($_POST['mode']) ? strtolower(trim($_POST['mode'])) : 'new';
$target_year = isset($_POST['target_year']) ? trim($_POST['target_year']) : '';

if (!preg_match('/^\d{4}$/', $target_year)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบปี Target Year ไม่ถูกต้อง (ต้องเป็นตัวเลข 4 หลัก)'
    ]);
    exit();
}

$months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

function clampSmallInt($val, $min = 0, $max = 32767) {
    $num = (int)$val;
    if ($num < $min) return $min;
    if ($num > $max) return $max;
    return $num;
}

function buildPayloadFromPost($prefix, array $months, PDO $conn, $tableName, $targetYear, $mode) {
    $stmt = $conn->prepare("SELECT TARGET_YEAR FROM {$tableName} WHERE TARGET_YEAR = :year");
    $stmt->execute([':year' => (string)$targetYear]);
    $exists = ($stmt->fetch(PDO::FETCH_ASSOC) !== false);

    if ($mode === 'new' && $exists) {
        throw new Exception("มีข้อมูล Target ของปี {$targetYear} ในตาราง {$tableName} อยู่แล้ว กรุณาเปลี่ยนเป็นโหมด Edit");
    }

    $workday_year = isset($_POST['workday_year' . $prefix])
        ? clampSmallInt($_POST['workday_year' . $prefix], 0, 32767)
        : 0;

    $amount_year = isset($_POST['amount_year' . $prefix])
        ? clampSmallInt($_POST['amount_year' . $prefix], 0, 32767)
        : 0;

    $rawAvgDay = isset($_POST['amount_day' . $prefix])
        ? (float)$_POST['amount_day' . $prefix]
        : 0.0;
    if ($rawAvgDay < 0) {
        $rawAvgDay = 0.0;
    }
    $amount_day = number_format(round($rawAvgDay, 2), 2, '.', '');

    $monthData = [];
    foreach ($months as $m) {
        $mLow = strtolower($m);

        $wdVal = isset($_POST['wd' . $prefix . '_' . $mLow])
            ? clampSmallInt($_POST['wd' . $prefix . '_' . $mLow], 0, 32767)
            : 0;

        $amtVal = isset($_POST['amount' . $prefix . '_' . $mLow])
            ? clampSmallInt($_POST['amount' . $prefix . '_' . $mLow], 0, 32767)
            : 0;

        $monthData['WORKDAY_' . $m] = $wdVal;
        $monthData['AMOUNT_' . $m] = $amtVal;
    }

    return [
        'exists' => $exists,
        'values' => array_merge([
            'TARGET_YEAR'  => (string)$targetYear,
            'WORKDAY_YEAR' => $workday_year,
            'AMOUNT_YEAR'  => $amount_year,
            'AMOUNT_DAY'   => $amount_day,
        ], $monthData)
    ];
}

function saveToTargetTable(PDO $conn, $tableName, array $prepared, array $months) {
    $v = $prepared['values'];

    if ($prepared['exists']) {
        $setSql = [
            "WORKDAY_YEAR = :WORKDAY_YEAR",
            "AMOUNT_YEAR = :AMOUNT_YEAR",
            "AMOUNT_DAY = :AMOUNT_DAY"
        ];
        foreach ($months as $m) {
            $setSql[] = "WORKDAY_{$m} = :WORKDAY_{$m}";
            $setSql[] = "AMOUNT_{$m} = :AMOUNT_{$m}";
        }

        $sql = "UPDATE {$tableName} SET " . implode(', ', $setSql) . " WHERE TARGET_YEAR = :TARGET_YEAR";
    } else {
        $cols = ['TARGET_YEAR', 'WORKDAY_YEAR', 'AMOUNT_YEAR', 'AMOUNT_DAY'];
        $placeholders = [':TARGET_YEAR', ':WORKDAY_YEAR', ':AMOUNT_YEAR', ':AMOUNT_DAY'];

        foreach ($months as $m) {
            $cols[] = "WORKDAY_{$m}";
            $cols[] = "AMOUNT_{$m}";
            $placeholders[] = ":WORKDAY_{$m}";
            $placeholders[] = ":AMOUNT_{$m}";
        }

        $sql = "INSERT INTO {$tableName} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':TARGET_YEAR', $v['TARGET_YEAR'], PDO::PARAM_STR);
    $stmt->bindValue(':WORKDAY_YEAR', $v['WORKDAY_YEAR'], PDO::PARAM_INT);
    $stmt->bindValue(':AMOUNT_YEAR', $v['AMOUNT_YEAR'], PDO::PARAM_INT);
    $stmt->bindValue(':AMOUNT_DAY', $v['AMOUNT_DAY'], PDO::PARAM_STR);

    foreach ($months as $m) {
        $stmt->bindValue(':WORKDAY_' . $m, $v['WORKDAY_' . $m], PDO::PARAM_INT);
        $stmt->bindValue(':AMOUNT_' . $m, $v['AMOUNT_' . $m], PDO::PARAM_INT);
    }

    $stmt->execute();
}

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception('ไม่พบการเชื่อมต่อฐานข้อมูล PDO ($conn)');
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $prep2 = buildPayloadFromPost('2', $months, $conn, 'TRGTMSTR2', $target_year, $mode);
    $prep4 = buildPayloadFromPost('4', $months, $conn, 'TRGTMSTR4', $target_year, $mode);

    $conn->beginTransaction();

    saveToTargetTable($conn, 'TRGTMSTR2', $prep2, $months);
    saveToTargetTable($conn, 'TRGTMSTR4', $prep4, $months);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "บันทึกข้อมูล Target ปี {$target_year} ลง TRGTMSTR2 และ TRGTMSTR4 เรียบร้อยแล้ว"
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
