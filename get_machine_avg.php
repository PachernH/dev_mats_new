<?php
// get_machine_avg.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

include("dbcon_cm6_mats.php");

$db = null;
if (isset($conn6) && $conn6 instanceof PDO) {
    $db = $conn6;
} elseif (isset($conn_cm6) && $conn_cm6 instanceof PDO) {
    $db = $conn_cm6;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
}

if (!$db) {
    echo json_encode(['error' => 'ไม่พบตัวแปร Connection จากไฟล์ dbcon_cm6_mats.php']);
    exit();
}

$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end   = isset($_GET['end']) ? trim($_GET['end']) : '';

if (empty($start) || empty($end)) {
    echo json_encode([
        'STPTRZ1' => 0, 'ACTTRZ1' => 0,
        'STPTRZ2' => 0, 'ACTTRZ2' => 0,
        'STPTRZ3' => 0, 'ACTTRZ3' => 0,
        'ACTRFZ1' => 0, 'ACTRFZ2' => 0, 'ACTRFZ3' => 0,
        'COOLING_FAN' => 0, 'STPSROC' => 0, 'ACTSROC' => 0,
        'no_data' => true
    ]);
    exit();
}

try {
    // ใช้ NULLIF(column, 0) เพื่อตัดค่า 0 ออกจากการเฉลี่ย AVG() เลียนแบบเงื่อนไข <> 0 ใน VB6 (f8.txt)
    $sql = "SELECT 
                AVG(NULLIF(STP_TRZ_1, 0)) AS STPTRZ1, 
                AVG(NULLIF(ACT_TRZ_1, 0)) AS ACTTRZ1,
                AVG(NULLIF(STP_TRZ_2, 0)) AS STPTRZ2, 
                AVG(NULLIF(ACT_TRZ_2, 0)) AS ACTTRZ2,
                AVG(NULLIF(STP_TRZ_3, 0)) AS STPTRZ3, 
                AVG(NULLIF(ACT_TRZ_3, 0)) AS ACTTRZ3,
                AVG(NULLIF(ACT_RFZ_1, 0)) AS ACTRFZ1, 
                AVG(NULLIF(ACT_RFZ_2, 0)) AS ACTRFZ2, 
                AVG(NULLIF(ACT_RFZ_3, 0)) AS ACTRFZ3,
                AVG(NULLIF(STP_SRO_C, 0)) AS STPSROC, 
                AVG(NULLIF(ACT_SRO_C, 0)) AS ACTSROC
            FROM FAF2MCHN1 
            WHERE [DATETIME] >= CONVERT(datetime, :start_dt, 120) 
              AND [DATETIME] <= CONVERT(datetime, :end_dt, 120)";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':start_dt', $start, PDO::PARAM_STR);
    $stmt->bindValue(':end_dt', $end, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !is_null($result['STPTRZ1'])) {
        // ตาม f8.txt ใน GetAverageData() ค่า COOLING_FAN ไม่ได้ดึงจาก FAF2MCHN1 ให้เซ็ตเป็น 0 ตามหน้าจอเดิม
        $result['COOLING_FAN'] = 0;
        echo json_encode($result);
    } else {
        echo json_encode([
            'STPTRZ1' => 0, 'ACTTRZ1' => 0,
            'STPTRZ2' => 0, 'ACTTRZ2' => 0,
            'STPTRZ3' => 0, 'ACTTRZ3' => 0,
            'ACTRFZ1' => 0, 'ACTRFZ2' => 0, 'ACTRFZ3' => 0,
            'COOLING_FAN' => 0, 'STPSROC' => 0, 'ACTSROC' => 0,
            'no_data' => true
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'SQL Error: ' . $e->getMessage()]);
}
exit();
?>