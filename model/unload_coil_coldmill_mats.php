<?php
// model/unload_coil_coldmill_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    // 1. ค้นหา Coil ที่อยู่ในกระบวนการ Mill (LOCATION = 'R') จาก CDMLMCHN1
    $sql_find_r = "SELECT TOP 1 COIL_NO, LOCATION_ROW FROM CDMLMCHN1 WITH (NOLOCK) 
                   WHERE LOCATION = 'R' 
                     AND COIL_NO IS NOT NULL 
                     AND RTRIM(LTRIM(COIL_NO)) <> ''";
    $stmt_find_r = $conn->prepare($sql_find_r);
    $stmt_find_r->execute();
    $mchn1 = $stmt_find_r->fetch(PDO::FETCH_ASSOC);

    if (!$mchn1) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Coil ที่กำลังประมวลผล (Coil on Processing) ในระบบ']);
        exit();
    }

    $coil_no      = trim($mchn1['COIL_NO']);
    $location_row = intval($mchn1['LOCATION_ROW']);

    // 2. ค้นหาข้อมูลรายละเอียดกระบวนการล่าสุดจาก CDMLMCHN0
    $sql_mchn0 = "SELECT TOP 1 * FROM CDMLMCHN0 WITH (NOLOCK) WHERE COIL_NO = ?";
    $stmt_mchn0 = $conn->prepare($sql_mchn0);
    $stmt_mchn0->execute([$coil_no]);
    $mchn0 = $stmt_mchn0->fetch(PDO::FETCH_ASSOC);

    if (!$mchn0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Coil ในตาราง CDMLMCHN0 หรือข้อมูลไม่สมบูรณ์']);
        exit();
    }

    $conn->beginTransaction();

    // 3. UPDATE ข้อมูลกลับไปยัง CDMLMCHN1 (เปลี่ยน LOCATION เป็น 'I') อ้างอิงตาม Logic VB6
    $sql_upd1 = "UPDATE CDMLMCHN1 SET 
                    LOCATION = 'I', 
                    RECIPE_ITEM = ?, 
                    THICKNESS = ?, 
                    CURRENT_PASS = ?, 
                    THICKNESS_ENTRY = ?, 
                    THICKNESS_EXIT = ?, 
                    THICKNESS_TELORANCE = ?, 
                    CDML_STARTDATETIME = ?, 
                    CDML_ENDDATETIME = ?, 
                    COUNT_PROCESS = ? 
                WHERE COIL_NO = ?";
    
    $stmt_upd1 = $conn->prepare($sql_upd1);
    $stmt_upd1->execute([
        intval($mchn0['RECIPE_ITEM'] ?? 0),
        floatval($mchn0['THICKNESS'] ?? 0),
        intval($mchn0['CURRENT_PASS'] ?? 0),
        floatval($mchn0['THICKNESS_ENTRY'] ?? 0),
        floatval($mchn0['THICKNESS_EXIT'] ?? 0),
        floatval($mchn0['THICKNESS_TELORANCE'] ?? 0),
        $mchn0['CDML_STARTDATETIME'] ?? '1999-01-01 00:00:00',
        $mchn0['CDML_ENDDATETIME'] ?? '1999-01-01 00:00:00',
        intval($mchn0['COUNT_PROCESS'] ?? 0),
        $coil_no
    ]);

    // 4. ลบข้อมูลออกจากตาราง CDMLMCHN0
    $sql_del0 = "DELETE FROM CDMLMCHN0 WHERE COIL_NO = ?";
    $stmt_del0 = $conn->prepare($sql_del0);
    $stmt_del0->execute([$coil_no]);

    $conn->commit();

    // แปลง LOCATION_ROW (0-based) เป็น Slot Index (1-based)
    $slot_index = $location_row + 1;

    echo json_encode([
        'status'     => 'success',
        'message'    => "UNLOAD Coil [{$coil_no}] กลับเข้าสู่ Inlet Area ช่อง {$slot_index} เรียบร้อยแล้ว",
        'slot_index' => $slot_index,
        'coil_no'    => $coil_no
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}