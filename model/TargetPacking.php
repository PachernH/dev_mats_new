<?php
// model/TargetPacking.php
class TargetPacking {
    private $db;

    // ตัวคูณตามที่ระบุใน VB6
    private $multipliers = [
        'reroll' => 0.1014,
        'circle_o1a' => 0.56,
        'circle_o1b' => 0.36,
        'circle_o2a' => 0.51,
        'circle_o2b' => 0.30,
        'sheet' => 0.45,
        'reanneal' => 0.29,
        'srv_sheet' => 1.00
    ];

    public function __construct($db) {
        $this->db = $db;
    }

    public function getReportData($year, $targetParam, $excludeSunday) {
        // 1. ดึงข้อมูล Master (เป้าหมายปี และวันทำงาน)
        $masterData = $this->getMasterData($year, $targetParam, $excludeSunday);
        $intAmountYear = $masterData['amount_year'];
        $intWorkDayYear = $masterData['workday_year'];
        $dailyWorkDays = $masterData['daily_workdays']; // array [month][day] => boolean

        // 2. ดึงข้อมูลการผลิตทั้งหมดของปีนั้น (Query ครั้งเดียวต่อหมวด)
        $productionData = $this->getProductionData($year);

        // 3. คำนวณยอดรายวัน (Daily)
        $dailyResults = [];
        $accuBalance = 0;
        $intWorkCount = 0;
        $daysInYear = (date("L", mktime(0, 0, 0, 1, 1, $year)) == 1) ? 366 : 365;
        
        $today = date('Y-m-d');
        $isCurrentYear = ($year == date('Y'));

        for ($i = 1; $i <= $daysInYear; $i++) {
            $dateStr = date('Y-m-d', strtotime("$year-01-01 +" . ($i - 1) . " days"));
            $month = (int)date('m', strtotime($dateStr));
            $day = (int)date('d', strtotime($dateStr));

            // ดึงยอดของวันนั้น ๆ (ถ้าไม่มีให้เป็น 0)
            $raw = $productionData[$dateStr] ?? $this->getEmptyProduction();

            // คำนวณ Balance รายวัน (แปลง KG เป็น Ton โดยหาร 1000)
            $coil = ($raw['coil'] ?? 0) / 1000;
            $reroll = (($raw['reroll'] ?? 0) / 1000) * $this->multipliers['reroll'];
            $circle = ($raw['circle'] ?? 0) / 1000;
            $o1a = (($raw['o1a'] ?? 0) / 1000) * $this->multipliers['circle_o1a'];
            $o1b = (($raw['o1b'] ?? 0) / 1000) * $this->multipliers['circle_o1b'];
            $o2a = (($raw['o2a'] ?? 0) / 1000) * $this->multipliers['circle_o2a'];
            $o2b = (($raw['o2b'] ?? 0) / 1000) * $this->multipliers['circle_o2b'];
            $sheet = (($raw['sheet'] ?? 0) / 1000) * $this->multipliers['sheet'];
            $reanneal = (($raw['reanneal'] ?? 0) / 1000) * $this->multipliers['reanneal'];
            $srvSheet = (($raw['srv_sheet'] ?? 0) / 1000) * $this->multipliers['srv_sheet'];
            $reject = ($raw['reject'] ?? 0) / 1000;
            $forward = ($raw['forward'] ?? 0) / 1000; // Forward Order

            $balance = $coil + $reroll + $circle + $o1a + $o1b + $o2a + $o2b + $sheet + $reanneal + $srvSheet - $reject;

            // คำนวณ Target รายวัน
            $isWorkDay = $dailyWorkDays[$month][$day] ?? false;
            $targetDay = 0;

            if ($isWorkDay) {
                $intWorkCount++;
                $intRemainWorkDay = $intWorkDayYear - ($intWorkCount - 1);
                
                // Logic: (เป้าหมายปี - ยอดสะสม) / วันทำงานที่เหลือ
                if ($intRemainWorkDay > 0) {
                    $targetDay = ($intAmountYear - $accuBalance) / $intRemainWorkDay;
                }
                
                // ป้องกัน Target ติดลบ (แสดงเป็น 0 ตาม logic เดิม)
                if ($targetDay < 0) {
                    $targetDay = 0;
                }
            }

            // วันในอนาคต ให้ใช้ Target ของวันก่อนหน้า (Logic เดิม)
            if ($isCurrentYear && $dateStr > $today) {
                $targetDay = end($dailyResults)['target'] ?? 0;
            }

            $accuBalance += $balance;

            $dailyResults[] = [
                'date' => $dateStr,
                'day' => $day,
                'month' => $month,
                'target' => round($targetDay, 2),
                'balance' => round($balance, 2),
                'variance' => round($balance - $targetDay, 2),
                'actual_coil' => round($coil, 2),
                'actual_circle' => round($circle, 2),
                'forward_order' => round($forward, 2)
            ];
        }

        // 4. คำนวณสรุปรายเดือน (Monthly)
        $monthlyResults = $this->calculateMonthly($dailyResults);

        return [
            'summary' => [
                'target_year' => $intAmountYear,
                'workday_year' => $intWorkDayYear,
                'actual_ytd' => round($accuBalance, 2),
                'variance_ytd' => round($accuBalance - array_sum(array_column($dailyResults, 'target')), 2)
            ],
            'daily' => $dailyResults,
            'monthly' => $monthlyResults
        ];
    }

    private function getMasterData($year, $targetParam, $excludeSunday) {
        // เลือกตาราง Master ตาม Checkbox
        $tableYear = $excludeSunday ? 'TRGTMSTR4' : 'TRGTMSTR2';
        $tableMonth = $excludeSunday ? 'TRGTMSTR5' : 'TRGTMSTR3';

        // ดึง Master ปี
        $stmt = $this->db->prepare("SELECT * FROM $tableYear WHERE TARGET_YEAR = ?");
        $stmt->execute([$year]);
        $masterYear = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$masterYear) {
            throw new Exception("ไม่พบข้อมูลเป้าหมายสำหรับปี $year");
        }

        $baseAmount = (int)$masterYear['AMOUNT_YEAR'];
        
        // คำนวณเป้าหมายตาม Dropdown (Logic เดิม)
        $intAmountYear = $baseAmount;
        if (is_numeric($targetParam)) {
            $intAmountYear = (int)$targetParam; // ถ้าส่งมาเป็นตัวเลขตรงๆ
        } else {
            // เพิ่ม Logic แปลง String เป็นตัวเลข (เช่น 14000, 14000+300)
            if (strpos($targetParam, '+') !== false) {
                list($base, $add) = explode('+', $targetParam);
                $intAmountYear = $baseAmount + (int)$add;
            } elseif ($targetParam === '10000') $intAmountYear = $baseAmount;
            elseif ($targetParam === '12000') $intAmountYear = $baseAmount + 2000;
            elseif ($targetParam === '14000') $intAmountYear = $baseAmount + 4000;
            // เพิ่มเงื่อนไขอื่นๆ ตาม VB6
        }

        // ดึงวันทำงานรายเดือน
        $stmt = $this->db->prepare("SELECT * FROM $tableMonth WHERE TARGET_YEAR = ?");
        $stmt->execute([$year]);
        $masterMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dailyWorkDays = [];
        foreach ($masterMonths as $m) {
            $month = (int)$m['TARGET_MONTH'];
            for ($d = 1; $d <= 31; $d++) {
                $col = sprintf("AMOUNT_%02d", $d);
                if (isset($m[$col]) && $m[$col] > 0) {
                    $dailyWorkDays[$month][$d] = true;
                }
            }
        }

        return [
            'amount_year' => $intAmountYear,
            'workday_year' => (int)$masterYear['WORKDAY_YEAR'],
            'daily_workdays' => $dailyWorkDays
        ];
    }

    private function getProductionData($year) {
        $data = [];
        $startDate = "$year-01-01";
        $endDate = "$year-12-31 23:59:59";

        // ฟังก์ชันช่วยจัดการข้อมูลที่ได้จาก SQL
        $processQuery = function($sql, $key) use (&$data) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $date = $row['p_date'];
                if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
                $data[$date][$key] += $row['total_weight'];
            }
        };

        // Query 1: Coil & Reroll (GROUP BY Date)
        $sqlCoil = "SELECT CONVERT(VARCHAR(10), A.PACK_DATE, 120) as p_date, 
                           SUM(CASE WHEN B.ORDER_CATEGORY <> 'ORSV' THEN A.PACK_NETWEIGHT ELSE 0 END) as coil,
                           SUM(CASE WHEN B.ORDER_CATEGORY = 'ORSV' THEN A.PACK_NETWEIGHT ELSE 0 END) as reroll
                    FROM PACKPROD1 A
                    INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                    WHERE A.PACK_DATE BETWEEN ? AND ? AND A.PRODUCT_ID = 'CO' AND A.LINE_PROCESS <> 'D'
                    GROUP BY CONVERT(VARCHAR(10), A.PACK_DATE, 120)";
        
        $stmt = $this->db->prepare($sqlCoil);
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['p_date'];
            if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
            $data[$date]['coil'] += $row['coil'];
            $data[$date]['reroll'] += $row['reroll'];
        }

        // Query 2: Circle & O1A, O1B, O2A, O2B (Join CRSHPROD1)
        $sqlCircle = "SELECT CONVERT(VARCHAR(10), A.PACK_DATE, 120) as p_date,
                             SUM(CASE WHEN A.MATERIAL_IN NOT IN ('NONE-BOI','BOI-IMP1','BOI-IMP2') THEN A.PACK_NETWEIGHT ELSE 0 END) as circle,
                             SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY <> '1100' AND (C.COIL_NO LIKE '%-M-%' OR C.COIL_NO LIKE '%-S-%') THEN A.PACK_NETWEIGHT ELSE 0 END) as o1a,
                             SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY = '1100' AND (C.COIL_NO LIKE '%-M-%' OR C.COIL_NO LIKE '%-S-%') THEN A.PACK_NETWEIGHT ELSE 0 END) as o1b,
                             SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY <> '1100' AND C.COIL_NO LIKE '%-R-%' THEN A.PACK_NETWEIGHT ELSE 0 END) as o2a,
                             SUM(CASE WHEN C.MATERIAL_IN IN ('BOI-IMP1','NONE-BOI') AND C.ALLOY = '1100' AND C.COIL_NO LIKE '%-R-%' THEN A.PACK_NETWEIGHT ELSE 0 END) as o2b
                      FROM PACKPROD1 A
                      INNER JOIN CRSHPROD1 C ON A.PRODUCT_NO = C.PRODUCT_NO
                      INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                      WHERE A.PACK_DATE BETWEEN ? AND ? AND (A.PRODUCT_ID = 'CC' OR A.PRODUCT_ID = 'NC') AND A.LINE_PROCESS <> 'D' AND B.ORDER_CATEGORY <> 'ORSV'
                      GROUP BY CONVERT(VARCHAR(10), A.PACK_DATE, 120)";
        
        $stmt = $this->db->prepare($sqlCircle);
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['p_date'];
            if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
            $data[$date]['circle'] += $row['circle'];
            $data[$date]['o1a'] += $row['o1a'];
            $data[$date]['o1b'] += $row['o1b'];
            $data[$date]['o2a'] += $row['o2a'];
            $data[$date]['o2b'] += $row['o2b'];
        }

        // Query 3: Sheet, Reanneal, SrvSheet
        $sqlSheet = "SELECT CONVERT(VARCHAR(10), A.PACK_DATE, 120) as p_date,
                            SUM(CASE WHEN A.PRODUCT_ID = 'SH' AND B.ORDER_CATEGORY <> 'ORSV' AND C.MATERIAL_IN IN ('BOI-IMP1','BOI-IMP2') THEN A.PACK_NETWEIGHT ELSE 0 END) as sheet,
                            SUM(CASE WHEN A.PRODUCT_ID IN ('CC','SH','NC') AND B.ORDER_CATEGORY = 'ORSV' AND (C.CRSH_WORKPROCESS LIKE 'RC>AN>%' OR C.CRSH_WORKPROCESS LIKE 'RC>BA>%') THEN A.PACK_NETWEIGHT ELSE 0 END) as reanneal,
                            SUM(CASE WHEN A.PRODUCT_ID = 'SH' AND B.ORDER_CATEGORY = 'ORSV' AND C.CRSH_WORKPROCESS LIKE 'CL>%' THEN A.PACK_NETWEIGHT ELSE 0 END) as srv_sheet
                     FROM PACKPROD1 A
                     INNER JOIN CRSHPROD1 C ON A.PRODUCT_NO = C.PRODUCT_NO
                     INNER JOIN CSTMORDR1 B ON A.SALEORDER_NO = B.SALEORDER_NO
                     WHERE A.PACK_DATE BETWEEN ? AND ? AND A.LINE_PROCESS <> 'D'
                     GROUP BY CONVERT(VARCHAR(10), A.PACK_DATE, 120)";
                     
        $stmt = $this->db->prepare($sqlSheet);
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['p_date'];
            if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
            $data[$date]['sheet'] += $row['sheet'];
            $data[$date]['reanneal'] += $row['reanneal'];
            $data[$date]['srv_sheet'] += $row['srv_sheet'];
        }

        // Query 4: Reject
        $sqlReject = "SELECT CONVERT(VARCHAR(10), RETURN_DATE, 120) as p_date, SUM(RETURN_WEIGHT) as reject
                      FROM CSTMRTRN1 WHERE RETURN_DATE BETWEEN ? AND ?
                      GROUP BY CONVERT(VARCHAR(10), RETURN_DATE, 120)";
        $stmt = $this->db->prepare($sqlReject);
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['p_date'];
            if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
            $data[$date]['reject'] += $row['reject'];
        }

        // Query 5: Forward Order
        $sqlForward = "SELECT CONVERT(VARCHAR(10), REQUEST_DATE, 120) as p_date, SUM(CTM2_ORDERWEIGHT) as forward
                       FROM CSTMORDR2 WHERE REQUEST_DATE BETWEEN ? AND ? AND CSTMSPPL_ID <> 'MAT' AND ORDER_CATEGORY IN ('ORDR','ORD1','ORD2','ORSP')
                       GROUP BY CONVERT(VARCHAR(10), REQUEST_DATE, 120)";
        $stmt = $this->db->prepare($sqlForward);
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $date = $row['p_date'];
            if (!isset($data[$date])) $data[$date] = $this->getEmptyProduction();
            $data[$date]['forward'] += $row['forward'];
        }

        return $data;
    }

    private function getEmptyProduction() {
        return ['coil'=>0, 'reroll'=>0, 'circle'=>0, 'o1a'=>0, 'o1b'=>0, 'o2a'=>0, 'o2b'=>0, 'sheet'=>0, 'reanneal'=>0, 'srv_sheet'=>0, 'reject'=>0, 'forward'=>0];
    }

    private function calculateMonthly($dailyResults) {
        $monthly = [];
        $monthsName = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        
        for ($m = 1; $m <= 12; $m++) {
            $target = 0;
            $balance = 0;
            $forward = 0;
            
            foreach ($dailyResults as $d) {
                if ($d['month'] == $m) {
                    $target += $d['target'];
                    $balance += $d['balance'];
                    $forward += $d['forward_order'];
                }
            }
            
            $monthly[] = [
                'month_num' => $m,
                'month_name' => $monthsName[$m-1],
                'target' => round($target, 2),
                'balance' => round($balance, 2),
                'forward_order' => round($forward, 2)
            ];
        }
        return $monthly;
    }
}
?>