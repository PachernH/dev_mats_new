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
$raw_month = isset($_POST['target_month']) ? trim($_POST['target_month']) : '';

if (!preg_match('/^\d{4}$/', $target_year)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบปี Target Year ไม่ถูกต้อง (ต้องเป็นตัวเลข 4 หลัก)'
    ]);
    exit();
}

if (!preg_match('/^(0[1-9]|1[0-2]|[1-9])$/', $raw_month)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบเดือน Target Month ไม่ถูกต้อง (01-12)'
    ]);
    exit();
}

$target_month = str_pad((string)(int)$raw_month, 2, '0', STR_PAD_LEFT);

function clampSmallInt($val, $min = 0, $max = 32767) {
    $num = (int)$val;
    if ($num < $min) return $min;
    if ($num > $max) return $max;
    return $num;
}

function buildDailyPayload($prefix, PDO $conn, $tableName, $targetYear, $targetMonth, $mode) {
    $stmt = $conn->prepare("SELECT TARGET_YEAR FROM {$tableName} WHERE TARGET_YEAR = :year AND TARGET_MONTH = :month");
    $stmt->execute([
        ':year'  => (string)$targetYear,
        ':month' => (string)$targetMonth
    ]);
    $exists = ($stmt->fetch(PDO::FETCH_ASSOC) !== false);

    if ($mode === 'new' && $exists) {
        throw new Exception("มีข้อมูลปี {$targetYear} เดือน {$targetMonth} ในตาราง {$tableName} อยู่แล้ว กรุณาใช้โหมด Edit");
    }

    $amount_month = isset($_POST['amount_month' . $prefix])
        ? clampSmallInt($_POST['amount_month' . $prefix], 0, 32767)
        : 0;

    $workday_month = isset($_POST['workday_month' . $prefix])
        ? clampSmallInt($_POST['workday_month' . $prefix], 0, 32767)
        : 0;

    $dayValues = [];
    for ($d = 1; $d <= 31; $d++) {
        $dCode = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
        $fieldKey = 'amt' . $prefix . '_' . $dCode;
        $colName = 'AMOUNT_' . $dCode;

        $rawAmt = isset($_POST[$fieldKey]) ? (float)$_POST[$fieldKey] : 0.0;
        if ($rawAmt < 0) {
            $rawAmt = 0.0;
        }
        $dayValues[$colName] = round($rawAmt, 2);
    }

    return [
        'exists' => $exists,
        'values' => array_merge([
            'TARGET_YEAR'   => (string)$targetYear,
            'TARGET_MONTH'  => (string)$targetMonth,
            'AMOUNT_MONTH'  => $amount_month,
            'WORKDAY_MONTH' => $workday_month,
        ], $dayValues)
    ];
}

function saveDailyTargetTable(PDO $conn, $tableName, array $prepared) {
    $v = $prepared['values'];
    $dayCols = [];
    for ($d = 1; $d <= 31; $d++) {
        $dayCols[] = 'AMOUNT_' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
    }

    if ($prepared['exists']) {
        $setSql = [
            "AMOUNT_MONTH = :AMOUNT_MONTH",
            "WORKDAY_MONTH = :WORKDAY_MONTH"
        ];
        foreach ($dayCols as $col) {
            $setSql[] = "{$col} = :{$col}";
        }
        $sql = "UPDATE {$tableName} SET " . implode(', ', $setSql)
             . " WHERE TARGET_YEAR = :TARGET_YEAR AND TARGET_MONTH = :TARGET_MONTH";
    } else {
        $cols = array_merge(['TARGET_YEAR', 'TARGET_MONTH', 'AMOUNT_MONTH', 'WORKDAY_MONTH'], $dayCols);
        $params = array_map(function($c) { return ':' . $c; }, $cols);
        $sql = "INSERT INTO {$tableName} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $params) . ")";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':TARGET_YEAR', $v['TARGET_YEAR'], PDO::PARAM_STR);
    $stmt->bindValue(':TARGET_MONTH', $v['TARGET_MONTH'], PDO::PARAM_STR);
    $stmt->bindValue(':AMOUNT_MONTH', $v['AMOUNT_MONTH'], PDO::PARAM_INT);
    $stmt->bindValue(':WORKDAY_MONTH', $v['WORKDAY_MONTH'], PDO::PARAM_INT);

    foreach ($dayCols as $col) {
        $stmt->bindValue(':' . $col, (string)$v[$col], PDO::PARAM_STR);
    }

    $stmt->execute();
}

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception('ไม่พบการเชื่อมต่อฐานข้อมูล PDO ($conn)');
    }

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $prep3 = buildDailyPayload('3', $conn, 'TRGTMSTR3', $target_year, $target_month, $mode);
    $prep5 = buildDailyPayload('5', $conn, 'TRGTMSTR5', $target_year, $target_month, $mode);

    $conn->beginTransaction();

    saveDailyTargetTable($conn, 'TRGTMSTR3', $prep3);
    saveDailyTargetTable($conn, 'TRGTMSTR5', $prep5);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "บันทึกข้อมูล Target รายวัน ปี {$target_year} เดือน {$target_month} เรียบร้อยแล้ว"
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