<?php
// model/get_report_data.php
ob_start(); 
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
ini_set('display_errors', 0); 

// ตรวจสอบ Path ให้ตรงกับไฟล์เชื่อมต่อของคุณ
require_once '../dbcon_mats.php';

try {
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    $targetParam = isset($_GET['target']) ? $_GET['target'] : '10000';
    $excludeSunday = isset($_GET['exclude_sunday']) && $_GET['exclude_sunday'] == '1' ? true : false;

    if (!$conn) throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้");

    // ==========================================
    // 1. ดึง Master Target
    // ==========================================
    $tableYear = $excludeSunday ? 'TRGTMSTR4' : 'TRGTMSTR2';
    $tableMonth = $excludeSunday ? 'TRGTMSTR5' : 'TRGTMSTR3';

    $stmt = $conn->prepare("SELECT * FROM $tableYear WHERE TARGET_YEAR = ?");
    $stmt->execute([$year]);
    $masterYear = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$masterYear) throw new Exception("ไม่พบข้อมูลเป้าหมายสำหรับปี $year");

    $baseAmount = (int)trim($masterYear['AMOUNT_YEAR']); 
    $intAmountYear = $baseAmount;
    
    // ==========================================
    // แก้ไข Logic Target ให้เหมือน VB6 100%
    // ==========================================
    if ($targetParam == '10000') {
        $intAmountYear = $baseAmount;
    } elseif ($targetParam == '12000') {
        $intAmountYear = $baseAmount + 2000;
    } elseif ($targetParam == '14000') {
        $intAmountYear = $baseAmount + 4000;
    } elseif ($targetParam == '14300') {
        $intAmountYear = $baseAmount + 4300;
    } elseif ($targetParam == '14600') {
        $intAmountYear = $baseAmount + 4600;
    } elseif ($targetParam == '14900') {
        $intAmountYear = $baseAmount + 4900;
    } elseif ($targetParam == '15200') {
        $intAmountYear = $baseAmount + 5200;
    } elseif ($targetParam == '15500') {
        $intAmountYear = $baseAmount + 5500;
    } elseif ($targetParam == '15800') {
        $intAmountYear = $baseAmount + 5800;
    } elseif ($targetParam == '16100') {
        $intAmountYear = $baseAmount + 6100;
    } elseif (is_numeric($targetParam)) {
        $intAmountYear = (int)$targetParam;
    }

    // ดึง Master รายเดือน
    $stmt = $conn->prepare("SELECT * FROM $tableMonth WHERE TARGET_YEAR = ?");
    $stmt->execute([$year]);
    $masterMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $staticTargets = []; 
    $monthlyWorkDays = array_fill(1, 12, 0); 
    $totalWorkDaysYear = 0;

    foreach ($masterMonths as $m) {
        $month = (int)trim($m['TARGET_MONTH']);
        for ($d = 1; $d <= 31; $d++) {
            $col = sprintf("AMOUNT_%02d", $d);
            if (isset($m[$col]) && (float)$m[$col] > 0) {
                $staticTargets[$month][$d] = true;
                $monthlyWorkDays[$month]++;
                $totalWorkDaysYear++;
            }
        }
    }

    // คำนวณ Target รายวันใหม่
    $newDailyTargetValue = ($totalWorkDaysYear > 0) ? ($intAmountYear / $totalWorkDaysYear) : 0;
    
    // คำนวณ Target รายเดือนใหม่
    $monthlyTargets = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthlyTargets[$m] = round($monthlyWorkDays[$m] * $newDailyTargetValue, 2);
    }

    // ==========================================
    // 2. ดึงข้อมูลผลิต (Production Data)
    // ==========================================
    $data = [];
    $startDate = "$year-01-01";
    $endDate = "$year-12-31 23:59:59";
    $dateFormat = "CONVERT(VARCHAR(10), %s, 120)";

    $getEmptyProduction = function() {
        return ['coil'=>0, 'reroll'=>0, 'circle'=>0, 'o1a'=>0, 'o1b'=>0, 'o2a'=>0, 'o2b'=>0, 'sheet'=>0, 'reanneal'=>0, 'srv_sheet'=>0, 'reject'=>0, 'forward'=>0];
    };

    // 2.1 Coil & Reroll
    $sql = "SELECT " . sprintf($dateFormat, 'A.PACK_DATE') . " as p_date, 
            SUM(CASE WHEN B.ORDER_CATEGORY <> 'ORSV' THEN A.PACK_NETWEIGHT ELSE 0 END) as coil,
            SUM(CASE WHEN B.ORDER_CATEGORY = 'ORSV' THEN A.PACK_NETWEIGHT ELSE 0 END) as reroll
            FROM PACKPROD1 A INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
            WHERE A.PACK_DATE BETWEEN ? AND ? AND A.PRODUCT_ID = 'CO' AND A.LINE_PROCESS <> 'D'
            GROUP BY " . sprintf($dateFormat, 'A.PACK_DATE');
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = trim($row['p_date']);
        if (!isset($data[$d])) $data[$d] = $getEmptyProduction();
        $data[$d]['coil'] += (float)$row['coil'];
        $data[$d]['reroll'] += (float)$row['reroll'];
    }

    // 2.2 Circle
    $sql = "SELECT " . sprintf($dateFormat, 'A.PACK_DATE') . " as p_date,
            SUM(CASE WHEN A.MATERIAL_IN NOT IN ('NONE-BOI','BOI-IMP1','BOI-IMP2') THEN A.PACK_NETWEIGHT ELSE 0 END) as circle,
            SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY <> '1100' AND (C.COIL_NO LIKE '%-M-%' OR C.COIL_NO LIKE '%-S-%') THEN A.PACK_NETWEIGHT ELSE 0 END) as o1a,
            SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY = '1100' AND (C.COIL_NO LIKE '%-M-%' OR C.COIL_NO LIKE '%-S-%') THEN A.PACK_NETWEIGHT ELSE 0 END) as o1b,
            SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY <> '1100' AND C.COIL_NO LIKE '%-R-%' THEN A.PACK_NETWEIGHT ELSE 0 END) as o2a,
            SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY = '1100' AND C.COIL_NO LIKE '%-R-%' THEN A.PACK_NETWEIGHT ELSE 0 END) as o2b
            FROM PACKPROD1 A INNER JOIN CRSHPROD1 C ON A.PRODUCT_NO = C.PRODUCT_NO
            INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
            WHERE A.PACK_DATE BETWEEN ? AND ? AND (A.PRODUCT_ID = 'CC' OR A.PRODUCT_ID = 'NC') AND A.LINE_PROCESS <> 'D' AND B.ORDER_CATEGORY <> 'ORSV'
            GROUP BY " . sprintf($dateFormat, 'A.PACK_DATE');
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = trim($row['p_date']);
        if (!isset($data[$d])) $data[$d] = $getEmptyProduction();
        $data[$d]['circle'] += (float)$row['circle'];
        $data[$d]['o1a'] += (float)$row['o1a'];
        $data[$d]['o1b'] += (float)$row['o1b'];
        $data[$d]['o2a'] += (float)$row['o2a'];
        $data[$d]['o2b'] += (float)$row['o2b'];
    }

    // 2.3 Sheet, Reanneal, SrvSheet
    $sql = "SELECT " . sprintf($dateFormat, 'A.PACK_DATE') . " as p_date,
            SUM(CASE WHEN A.PRODUCT_ID = 'SH' AND B.ORDER_CATEGORY <> 'ORSV' AND C.MATERIAL_IN IN ('BOI-IMP1','BOI-IMP2') THEN A.PACK_NETWEIGHT ELSE 0 END) as sheet,
            SUM(CASE WHEN A.PRODUCT_ID IN ('CC','SH','NC') AND B.ORDER_CATEGORY = 'ORSV' AND (C.CRSH_WORKPROCESS LIKE 'RC>AN>%' OR C.CRSH_WORKPROCESS LIKE 'RC>BA>%') THEN A.PACK_NETWEIGHT ELSE 0 END) as reanneal,
            SUM(CASE WHEN A.PRODUCT_ID = 'SH' AND B.ORDER_CATEGORY = 'ORSV' AND C.CRSH_WORKPROCESS LIKE 'CL>%' THEN A.PACK_NETWEIGHT ELSE 0 END) as srv_sheet
            FROM PACKPROD1 A INNER JOIN CRSHPROD1 C ON A.PRODUCT_NO = C.PRODUCT_NO
            INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
            WHERE A.PACK_DATE BETWEEN ? AND ? AND A.LINE_PROCESS <> 'D'
            GROUP BY " . sprintf($dateFormat, 'A.PACK_DATE');
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = trim($row['p_date']);
        if (!isset($data[$d])) $data[$d] = $getEmptyProduction();
        $data[$d]['sheet'] += (float)$row['sheet'];
        $data[$d]['reanneal'] += (float)$row['reanneal'];
        $data[$d]['srv_sheet'] += (float)$row['srv_sheet'];
    }

    // 2.4 Reject
    $sql = "SELECT CONVERT(VARCHAR(10), RETURN_DATE, 120) as p_date, SUM(RETURN_WEIGHT) as reject
            FROM CSTMRTRN1 WHERE RETURN_DATE BETWEEN ? AND ?
            GROUP BY CONVERT(VARCHAR(10), RETURN_DATE, 120)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = trim($row['p_date']);
        if (!isset($data[$d])) $data[$d] = $getEmptyProduction();
        $data[$d]['reject'] += (float)$row['reject'];
    }

    // 2.5 Forward Order
    $sql = "SELECT " . sprintf($dateFormat, 'REQUEST_DATE') . " as p_date, SUM(CTM2_ORDERWEIGHT) as forward
            FROM CSTMORDR2 WHERE REQUEST_DATE BETWEEN ? AND ? AND CSTMSPPL_ID <> 'MAT' AND ORDER_CATEGORY IN ('ORDR','ORD1','ORD2','ORSP')
            GROUP BY " . sprintf($dateFormat, 'REQUEST_DATE');
    $stmt = $conn->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $d = trim($row['p_date']);
        if (!isset($data[$d])) $data[$d] = $getEmptyProduction();
        $data[$d]['forward'] += (float)$row['forward'];
    }

    // ==========================================
    // 3. คำนวณและประกอบข้อมูล
    // ==========================================
    $multipliers = [
        'reroll' => 0.1014, 'circle_o1a' => 0.56, 'circle_o1b' => 0.36,
        'circle_o2a' => 0.51, 'circle_o2b' => 0.30, 'sheet' => 0.45,
        'reanneal' => 0.29, 'srv_sheet' => 1.00
    ];

    $dailyResults = [];
    $accuBalance = 0;
    $totalTargetYTD = 0; 
    $daysInYear = (date("L", mktime(0, 0, 0, 1, 1, $year)) == 1) ? 366 : 365;

    for ($i = 1; $i <= $daysInYear; $i++) {
        $dateStr = date('Y-m-d', strtotime("$year-01-01 +" . ($i - 1) . " days"));
        $month = (int)date('m', strtotime($dateStr));
        $day = (int)date('d', strtotime($dateStr));

        $raw = $data[$dateStr] ?? $getEmptyProduction();

        $coil = round((float)($raw['coil'] ?? 0) / 1000, 2);
        $reroll = round(((float)($raw['reroll'] ?? 0) / 1000) * $multipliers['reroll'], 2);
        $circle = round((float)($raw['circle'] ?? 0) / 1000, 2);
        $o1a = round(((float)($raw['o1a'] ?? 0) / 1000) * $multipliers['circle_o1a'], 2);
        $o1b = round(((float)($raw['o1b'] ?? 0) / 1000) * $multipliers['circle_o1b'], 2);
        $o2a = round(((float)($raw['o2a'] ?? 0) / 1000) * $multipliers['circle_o2a'], 2);
        $o2b = round(((float)($raw['o2b'] ?? 0) / 1000) * $multipliers['circle_o2b'], 2);
        $sheet = round(((float)($raw['sheet'] ?? 0) / 1000) * $multipliers['sheet'], 2);
        $reanneal = round(((float)($raw['reanneal'] ?? 0) / 1000) * $multipliers['reanneal'], 2);
        $srvSheet = round(((float)($raw['srv_sheet'] ?? 0) / 1000) * $multipliers['srv_sheet'], 2);
        $reject = round((float)($raw['reject'] ?? 0), 2);
        $forward = round((float)($raw['forward'] ?? 0), 2);

        $balance = round(($coil + $reroll + $circle + $o1a + $o1b + $o2a + $o2b + $sheet + $reanneal + $srvSheet) - $reject, 2);

        $isWorkDay = isset($staticTargets[$month][$day]);
        $targetDay = $isWorkDay ? round($newDailyTargetValue, 2) : 0;
        
        $totalTargetYTD = round($totalTargetYTD + $targetDay, 2);
        $accuBalance = round($accuBalance + $balance, 2);

        $dailyResults[] = [
            'date' => $dateStr, 'day' => $day, 'month' => $month,
            'target' => $targetDay, 
            'balance' => $balance, 
            'variance' => round($balance - $targetDay, 2),
            'coil' => $coil, 'reroll' => $reroll, 'circle' => $circle,
            'o1a' => $o1a, 'o1b' => $o1b, 'o2a' => $o2a, 'o2b' => $o2b,
            'sheet' => $sheet, 'reanneal' => $reanneal, 'srv_sheet' => $srvSheet, 
            'reject' => $reject, 
            'forward_order' => $forward
        ];
    }

    // คำนวณ Onhand Dead Stock
    $onhandDS = [];
    for ($m = 1; $m <= 12; $m++) {
        $lastDay = date('t', strtotime("$year-$m-01"));
        $strPackDate = "$year-" . sprintf("%02d", $m) . "-$lastDay";
        
        $stmt = $conn->prepare("SELECT SUM(PACK_NETWEIGHT) as total FROM ONHANDDEADSTOCK WHERE PACK_DATE <= ?");
        $stmt->execute([$strPackDate . " 23:59:59"]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $onhandDS[$m] = round((float)($row['total'] ?? 0) / 1000, 2);
    }

    $monthlyResults = [];
    $monthsName = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    for ($m = 1; $m <= 12; $m++) {
        $bal = 0; $fwd = 0;
        $m_coil = 0; $m_reroll = 0; $m_circle = 0; 
        $m_o1 = 0; $m_o2 = 0; $m_sheet = 0; $m_reanneal = 0; $m_srv_sheet = 0; $m_reject = 0;

        foreach ($dailyResults as $d) {
            if ($d['month'] == $m) {
                $bal += $d['balance']; $fwd += $d['forward_order'];
                $m_coil += $d['coil']; $m_reroll += $d['reroll']; $m_circle += $d['circle'];
                $m_o1 += ($d['o1a'] + $d['o1b']); 
                $m_o2 += ($d['o2a'] + $d['o2b']); 
                $m_sheet += $d['sheet']; $m_reanneal += $d['reanneal']; $m_srv_sheet += $d['srv_sheet'];
                $m_reject += $d['reject'];
            }
        }
        
        $tgtMonth = $monthlyTargets[$m];

        $monthlyResults[] = [
            'month_num' => $m, 'month_name' => $monthsName[$m-1],
            'workday' => $monthlyWorkDays[$m],
            'target' => round($tgtMonth, 2), 
            'balance' => round($bal, 2), 'forward_order' => round($fwd, 2),
            'coil' => round($m_coil, 2), 'reroll' => round($m_reroll, 2), 'circle' => round($m_circle, 2),
            'o1' => round($m_o1, 2), 'o2' => round($m_o2, 2), 'sheet' => round($m_sheet, 2), 
            'reanneal' => round($m_reanneal, 2), 'srv_sheet' => round($m_srv_sheet, 2), 
            'reject' => round($m_reject, 2),
            'variance' => round($bal - $tgtMonth, 2),
            'onhand_ds' => $onhandDS[$m]
        ];
    }

    ob_clean(); 
    echo json_encode([
        "status" => "success", 
        "data" => [
            'summary' => [
                'target_year' => $intAmountYear, 
                'workday_year' => $totalWorkDaysYear,
                'actual_ytd' => round($accuBalance, 2),
                'variance_ytd' => round($accuBalance - $totalTargetYTD, 2) 
            ],
            'daily' => $dailyResults, 
            'monthly' => $monthlyResults
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) { 
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "API Error: " . $e->getMessage() . " (Line: " . $e->getLine() . ")"], JSON_UNESCAPED_UNICODE);
}
ob_end_flush();
?>