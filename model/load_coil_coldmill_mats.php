<?php
// model/load_coil_coldmill_mats.php
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
    // 0. ตรวจสอบว่ามี Coil ที่กำลังประมวลผลอยู่บน Mill Processing (LOCATION = 'R') หรือไม่
    $sql_check_r = "SELECT TOP 1 COIL_NO FROM CDMLMCHN1 WITH (NOLOCK) 
                    WHERE LOCATION = 'R' 
                      AND COIL_NO IS NOT NULL 
                      AND RTRIM(LTRIM(COIL_NO)) <> ''";
    $stmt_check_r = $conn->prepare($sql_check_r);
    $stmt_check_r->execute();
    $exist_r = $stmt_check_r->fetch(PDO::FETCH_ASSOC);

    if ($exist_r) {
        $active_coil = trim($exist_r['COIL_NO']);
        echo json_encode([
            'status'  => 'error', 
            'message' => "ไม่สามารถ LOAD ได้เนื่องจากมี Coil [{$active_coil}] อยู่ในสถานะ Coil on Processing"
        ]);
        exit();
    }

    // 1. รับค่าสล็อตที่ถูกติ๊กเลือก หรือสล็อตเดี่ยวส่งมาจากหน้าบ้าน
    $selected_slots = isset($_POST['selected_slots']) ? json_decode($_POST['selected_slots'], true) : null;
    $target_slot    = isset($_POST['slot_index']) ? intval($_POST['slot_index']) : 0;
    $target_coil_no = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';

    // แปลง slot_index (1-7) ให้เป็น LOCATION_ROW (0-6)
    $target_rows = [];
    if (!empty($selected_slots) && is_array($selected_slots)) {
        foreach ($selected_slots as $slot) {
            $slot_int = intval($slot);
            if ($slot_int >= 1 && $slot_int <= 7) {
                $target_rows[] = $slot_int - 1; // 1-based -> 0-based
            }
        }
    }

    // 2. ค้นหา Coil ใน Inlet Area (LOCATION = 'I')
    if (!empty($target_rows)) {
        // กรณีมีการติ๊กเลือกบางช่อง (1-6 ช่อง): ค้นหาเฉพาะในช่องที่ติ๊กไว้ เรียงลำดับจากหน้าไปหลัง (LOCATION_ROW ASC)
        $placeholders = implode(',', array_fill(0, count($target_rows), '?'));
        $sql_find = "SELECT TOP 1 * FROM CDMLMCHN1 WITH (NOLOCK)
                     WHERE LOCATION = 'I' 
                       AND LOCATION_ROW IN ({$placeholders})
                       AND COIL_NO IS NOT NULL 
                       AND RTRIM(LTRIM(COIL_NO)) <> ''
                     ORDER BY LOCATION_ROW ASC";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute($target_rows);
    } else if ($target_slot > 0) {
        // กรณีส่ง slot_index เดี่ยวมา
        $location_row = $target_slot - 1;
        $sql_find = "SELECT TOP 1 * FROM CDMLMCHN1 WITH (NOLOCK)
                     WHERE LOCATION = 'I' 
                       AND LOCATION_ROW = ? 
                       AND COIL_NO IS NOT NULL 
                       AND RTRIM(LTRIM(COIL_NO)) <> ''";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute([$location_row]);
    } else if (!empty($target_coil_no)) {
        // กรณีส่ง coil_no ระบุมา
        $sql_find = "SELECT TOP 1 * FROM CDMLMCHN1 WITH (NOLOCK)
                     WHERE LOCATION = 'I' 
                       AND RTRIM(LTRIM(COIL_NO)) = ?";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute([$target_coil_no]);
    } else {
        // กรณีไม่ได้เลือกช่อง หรือติ๊กทุกช่อง ให้ดึงช่องแรกสุดที่มีข้อมูลตามลำดับปกติ (LOCATION_ROW ASC)
        $sql_find = "SELECT TOP 1 * FROM CDMLMCHN1 WITH (NOLOCK)
                     WHERE LOCATION = 'I' 
                       AND COIL_NO IS NOT NULL 
                       AND RTRIM(LTRIM(COIL_NO)) <> '' 
                     ORDER BY LOCATION_ROW ASC";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute();
    }

    $mchn = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$mchn) {
        $msg = !empty($target_rows) 
            ? 'ไม่พบข้อมูล Coil ในช่อง Inlet Area ที่เลือก' 
            : 'ไม่พบข้อมูล Coil ในช่อง Inlet Area สำหรับ LOAD';
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit();
    }

    $coil_no      = trim($mchn['COIL_NO']);
    $location_row = intval($mchn['LOCATION_ROW']);
    $now          = date('Y-m-d H:i:s');

    $conn->beginTransaction();

    // 3. INSERT ข้อมูลลง CDMLMCHN0
    $sql_ins0 = "INSERT INTO CDMLMCHN0 (
                    COIL_NO, LOCATION, LOCATION_ROW, JOB_PROCESS, RECIPE_NO, 
                    RECIPE_ITEM, THICKNESS, CURRENT_PASS, THICKNESS_ENTRY, THICKNESS_EXIT, 
                    THICKNESS_TELORANCE, CDML_STARTDATETIME, CDML_ENDDATETIME, COUNT_PROCESS
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_ins0 = $conn->prepare($sql_ins0);
    $stmt_ins0->execute([
        $coil_no,
        'R',
        $location_row,
        $mchn['JOB_PROCESS'] ?? '',
        $mchn['RECIPE_NO'] ?? '',
        intval($mchn['RECIPE_ITEM'] ?? 0),
        floatval($mchn['THICKNESS'] ?? 0),
        intval($mchn['CURRENT_PASS'] ?? 0),
        floatval($mchn['THICKNESS_ENTRY'] ?? 0),
        floatval($mchn['THICKNESS_EXIT'] ?? 0),
        floatval($mchn['THICKNESS_TELORANCE'] ?? 0),
        $mchn['CDML_STARTDATETIME'] ?? '1999-01-01 00:00:00',
        $mchn['CDML_ENDDATETIME'] ?? '1999-01-01 00:00:00',
        intval($mchn['COUNT_PROCESS'] ?? 0)
    ]);

    // 4. UPDATE CDMLMCHN1 เปลี่ยน LOCATION เป็น 'R'
    $sql_upd1 = "UPDATE CDMLMCHN1 SET LOCATION = 'R', CDML_STARTDATETIME = ? WHERE COIL_NO = ?";
    $stmt_upd1 = $conn->prepare($sql_upd1);
    $stmt_upd1->execute([$now, $coil_no]);

    $conn->commit();

    // 5. คำนวณ Tolerance Limits
    $max_cust   = floatval($mchn['MAX_THICKCUSTOMER'] ?? 0);
    $min_cust   = floatval($mchn['MIN_THICKCUSTOMER'] ?? 0);
    $thick_exit = floatval($mchn['THICKNESS_EXIT'] ?? 0);

    $max_tol = 0.0;
    $min_tol = 0.0;

    if ($max_cust == 0 && $min_cust == 0) {
        $sg = trim($mchn['SURFACE_GRADE'] ?? '');
        $mg = trim($mchn['METALLURGICAL_GRADE'] ?? '');
        $product_type = ($sg === 'SGR' && $mg === 'MG6') ? 'BT' : 'NC';

        $sql_tcps = "SELECT TOP 1 TOLERANCE_MAX, TOLERANCE_MIN FROM TCPS0301 WITH (NOLOCK)
                     WHERE PRODUCT_TYPE = ? 
                       AND THICKNESS_FROM <= ? 
                       AND THICKNESS_TO >= ?";
        $stmt_tcps = $conn->prepare($sql_tcps);
        $stmt_tcps->execute([$product_type, $thick_exit, $thick_exit]);
        $tcps = $stmt_tcps->fetch(PDO::FETCH_ASSOC);

        if ($tcps) {
            $max_tol = $thick_exit + floatval($tcps['TOLERANCE_MAX']);
            $min_tol = $thick_exit - floatval($tcps['TOLERANCE_MIN']);
        }
    } else {
        $max_tol = $thick_exit + $max_cust;
        $min_tol = $thick_exit - $min_cust;
    }

    $avg_thick = ($max_tol + $min_tol) / 2;

    $mchn['MAX_TOLERANCE'] = $max_tol;
    $mchn['MIN_TOLERANCE'] = $min_tol;
    $mchn['AVG_THICKNESS'] = $avg_thick;

    $slot_index = $location_row + 1;

    echo json_encode([
        'status' => 'success',
        'message' => "Load Coil [{$coil_no}] (ช่อง {$slot_index}) เข้าสู่กระบวนการเรียบร้อยแล้ว",
        'slot_index' => $slot_index,
        'data' => $mchn
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}