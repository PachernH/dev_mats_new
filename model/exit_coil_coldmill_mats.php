<?php
// model/exit_coil_coldmill_mats.php
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
    // 1. ค้นหา Coil ที่กำลังประมวลผลอยู่บน Mill (LOCATION = 'R') จาก CDMLMCHN1
    $sql_find_r = "SELECT TOP 1 * FROM CDMLMCHN1 WITH (NOLOCK) 
                   WHERE LOCATION = 'R' 
                     AND COIL_NO IS NOT NULL 
                     AND RTRIM(LTRIM(COIL_NO)) <> ''";
    $stmt_find_r = $conn->prepare($sql_find_r);
    $stmt_find_r->execute();
    $mchn1 = $stmt_find_r->fetch(PDO::FETCH_ASSOC);

    if (!$mchn1) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Coil ที่กำลังประมวลผลในระบบ']);
        exit();
    }

    $coil_no = trim($mchn1['COIL_NO']);

    // 2. ดึงค่า TOTAL_PASS จาก CDMLMCHN1 โดยตรง
    $total_pass = intval($mchn1['TOTAL_PASS'] ?? 0);

    // 3. ถอด Logic การคำนวณ Pass และ Recipe Item ตาม VB6 ตรงๆ
    $curr_pass   = intval($mchn1['CURRENT_PASS'] ?? 0);
    $recipe_item = intval($mchn1['RECIPE_ITEM'] ?? 0);
    $recipe_no   = trim($mchn1['RECIPE_NO'] ?? '');

    $intCurrentPass = 0;
    $intRecipeItem  = 0;

    if (($curr_pass + 1) >= $total_pass) {
        $intCurrentPass = $total_pass;
        $intRecipeItem  = $intCurrentPass;
    } else {
        $intCurrentPass = $curr_pass + 1;
        if (($recipe_item + 1) >= $total_pass) {
            $intRecipeItem = $total_pass;
        } else {
            $intRecipeItem = $recipe_item + 1;
        }
    }

    $dblThickness    = floatval($mchn1['THICKNESS_EXIT'] ?? 0);
    $intCountProcess = intval($mchn1['COUNT_PROCESS'] ?? 0) + 1;
    $now             = date('Y-m-d H:i:s');

    // 4. ดึงข้อมูลจากตาราง CMRCMSTR1 (ตามคำสั่ง VB6)
    $sql_rcm = "SELECT TOP 1 THICKNESS_ENTRY, THICKNESS_EXIT, THICKNESS_TELORANCE 
                FROM CMRCMSTR1 WITH (NOLOCK) 
                WHERE RECIPE_NO = ? AND RECIPE_ITEM = ?";
    $stmt_rcm = $conn->prepare($sql_rcm);
    $stmt_rcm->execute([$recipe_no, $intRecipeItem]);
    $row_rcm = $stmt_rcm->fetch(PDO::FETCH_ASSOC);

    if (!$row_rcm) {
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ไม่พบข้อมูล หรือข้อมูลไม่สมบูรณ์ของตารางข้อมูล CMRCMSTR1'
        ]);
        exit();
    }

    $dblThicknessEntry     = floatval($row_rcm['THICKNESS_ENTRY'] ?? 0);
    $dblThicknessExit      = floatval($row_rcm['THICKNESS_EXIT'] ?? 0);
    $dblThicknessTelorance = floatval($row_rcm['THICKNESS_TELORANCE'] ?? 0);

    $conn->beginTransaction();

    // 5. UPDATE ข้อมูลตาราง CDMLMCHN1 เปลี่ยน LOCATION เป็น 'O' (Outlet Area) เพียงตารางเดียวตาม VB6
    $sql_upd1 = "UPDATE CDMLMCHN1 SET 
                    LOCATION = 'O', 
                    RECIPE_ITEM = ?, 
                    THICKNESS = ?, 
                    CURRENT_PASS = ?, 
                    THICKNESS_ENTRY = ?, 
                    THICKNESS_EXIT = ?, 
                    THICKNESS_TELORANCE = ?, 
                    CDML_ENDDATETIME = ?, 
                    COUNT_PROCESS = ? 
                WHERE COIL_NO = ?";
    
    $stmt_upd1 = $conn->prepare($sql_upd1);
    $stmt_upd1->execute([
        $intRecipeItem,
        $dblThickness,
        $intCurrentPass,
        $dblThicknessEntry,
        $dblThicknessExit,
        $dblThicknessTelorance,
        $now,
        $intCountProcess,
        $coil_no
    ]);

    $conn->commit();

    $slot_index = intval($mchn1['LOCATION_ROW'] ?? 0) + 1;

    echo json_encode([
        'status'     => 'success',
        'message'    => "EXIT Coil [{$coil_no}] ไปยัง Outlet Area ช่อง {$slot_index} เรียบร้อยแล้ว",
        'slot_index' => $slot_index,
        'coil_no'    => $coil_no
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}