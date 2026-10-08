<?php
// model/return_coil_coldmill_mats.php
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
    // 1. ตรวจสอบว่ามี Coil อยู่บน Mill Processing (LOCATION = 'R') หรือไม่ (ตรงตาม Len(txtMillCoilNo) = 0 ใน VB6)
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
            'message' => "ไม่สามารถ RETURN ได้เนื่องจากมี Coil [{$active_coil}] อยู่ในกระบวนการ Coil on Processing"
        ]);
        exit();
    }

    // 2. รับค่าช่องที่ผู้ใช้ติ๊กเลือก (ถ้ามี)
    $selected_slots = isset($_POST['selected_slots']) ? json_decode($_POST['selected_slots'], true) : null;
    
    // แปลง slot_index (1-7) จาก Frontend ให้เป็น LOCATION_ROW (0-6)
    $target_rows = [];
    if (!empty($selected_slots) && is_array($selected_slots)) {
        foreach ($selected_slots as $slot) {
            $slot_int = intval($slot);
            if ($slot_int >= 1 && $slot_int <= 7) {
                $target_rows[] = $slot_int - 1; // แปลง 1-based เป็น 0-based
            }
        }
    }

    // 3. สร้าง Query ค้นหา Coil ใน Outlet Area
    // - หากมีการระบุช่องที่ติ๊ก ให้ค้นหาเฉพาะช่องเหล่านั้น
    // - หากไม่ได้ระบุ หรือ ติ๊กทุกช่อง ให้ค้นหาตามลำดับหลังไปหน้า (LOCATION_ROW DESC)
    if (!empty($target_rows)) {
        // สร้าง Placeholders (?, ?, ...) สำหรับ IN clause
        $placeholders = implode(',', array_fill(0, count($target_rows), '?'));
        
        $sql_find_outlet = "SELECT TOP 1 COIL_NO, LOCATION_ROW FROM CDMLMCHN1 WITH (NOLOCK) 
                            WHERE LOCATION = 'O' 
                              AND LOCATION_ROW IN ({$placeholders})
                              AND COIL_NO IS NOT NULL 
                              AND RTRIM(LTRIM(COIL_NO)) <> ''
                            ORDER BY LOCATION_ROW DESC";
        $stmt_find_outlet = $conn->prepare($sql_find_outlet);
        $stmt_find_outlet->execute($target_rows);
    } else {
        // ทำงานตามลำดับปกติ (ไล่จาก LOCATION_ROW DESC)
        $sql_find_outlet = "SELECT TOP 1 COIL_NO, LOCATION_ROW FROM CDMLMCHN1 WITH (NOLOCK) 
                            WHERE LOCATION = 'O' 
                              AND COIL_NO IS NOT NULL 
                              AND RTRIM(LTRIM(COIL_NO)) <> ''
                            ORDER BY LOCATION_ROW DESC";
        $stmt_find_outlet = $conn->prepare($sql_find_outlet);
        $stmt_find_outlet->execute();
    }

    $outlet_row = $stmt_find_outlet->fetch(PDO::FETCH_ASSOC);

    if (!$outlet_row) {
        $msg = !empty($target_rows) 
            ? 'ไม่พบข้อมูล Coil ในช่อง Outlet Area ที่เลือก' 
            : 'ไม่พบข้อมูล Coil ในพื้นที่ Outlet Area สำหรับย้ายกลับ';
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit();
    }

    $strCoilNo    = trim($outlet_row['COIL_NO']);
    $location_row = intval($outlet_row['LOCATION_ROW']);

    // 4. อ่านข้อมูลอ้างอิงเดิมจาก CDMLMCHN0 ตามหมายเลข Coil ที่พบ (ตามคำสั่ง VB6)
    $sql_mchn0 = "SELECT TOP 1 * FROM CDMLMCHN0 WITH (NOLOCK) WHERE COIL_NO = ?";
    $stmt_mchn0 = $conn->prepare($sql_mchn0);
    $stmt_mchn0->execute([$strCoilNo]);
    $mchn0 = $stmt_mchn0->fetch(PDO::FETCH_ASSOC);

    if (!$mchn0) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล หรือข้อมูลไม่สมบูรณ์ของตาราง CDMLMCHN0']);
        exit();
    }

    $conn->beginTransaction();

    // 5. UPDATE CDMLMCHN1 คืนค่า LOCATION เป็น 'R' และคืนสเปกเดิมตาม CDMLMCHN0 (ตาม Logic VB6)
    $sql_upd1 = "UPDATE CDMLMCHN1 SET 
                    LOCATION = ?, 
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
        'R',
        intval($mchn0['RECIPE_ITEM'] ?? 0),
        floatval($mchn0['THICKNESS'] ?? 0),
        intval($mchn0['CURRENT_PASS'] ?? 0),
        floatval($mchn0['THICKNESS_ENTRY'] ?? 0),
        floatval($mchn0['THICKNESS_EXIT'] ?? 0),
        floatval($mchn0['THICKNESS_TELORANCE'] ?? 0),
        $mchn0['CDML_STARTDATETIME'] ?? '1999-01-01 00:00:00',
        $mchn0['CDML_ENDDATETIME'] ?? '1999-01-01 00:00:00',
        intval($mchn0['COUNT_PROCESS'] ?? 0),
        $strCoilNo
    ]);

    $conn->commit();

    // 6. ดึงข้อมูลรายละเอียดฉบับเต็มของ Coil เพื่อส่งกลับไปแสดงผลบนหน้าเว็บ
    $sql_full = "SELECT TOP 1 
                    M1.*, 
                    C.ALLOY, C.TEMPER, C.SURFACE_GRADE, C.METALLURGICAL_GRADE, 
                    M1.THICKNESS_ORIGINAL, M1.THICKNESS_FINAL, C.WIDTH, M1.CDML_ACTUALWEIGHT, C.COIL_REMARK,
                    J.JOB_REMARK, M1.TOTAL_PASS,
                    ISNULL(RD.TEMPER_FINISH, C.TEMPER) AS TEMPER_FINISH
                 FROM CDMLMCHN1 M1 WITH (NOLOCK)
                 LEFT JOIN COILPROD1 C WITH (NOLOCK) 
                    ON M1.COIL_NO COLLATE DATABASE_DEFAULT = C.COIL_NO COLLATE DATABASE_DEFAULT
                 LEFT JOIN JOBORDER1 J WITH (NOLOCK) 
                    ON M1.JOB_PROCESS COLLATE DATABASE_DEFAULT = J.JOB_ORDER COLLATE DATABASE_DEFAULT
                 LEFT JOIN CMRCMSTR1 RD WITH (NOLOCK) 
                    ON M1.RECIPE_NO COLLATE DATABASE_DEFAULT = RD.RECIPE_NO COLLATE DATABASE_DEFAULT 
                   AND M1.RECIPE_ITEM = RD.RECIPE_ITEM
                 WHERE M1.COIL_NO = ?";
    $stmt_full = $conn->prepare($sql_full);
    $stmt_full->execute([$strCoilNo]);
    $full_data = $stmt_full->fetch(PDO::FETCH_ASSOC);

    // คำนวณค่า Tolerance Limits
    $max_cust   = floatval($full_data['MAX_THICKCUSTOMER'] ?? 0);
    $min_cust   = floatval($full_data['MIN_THICKCUSTOMER'] ?? 0);
    $thick_exit = floatval($full_data['THICKNESS_EXIT'] ?? 0);

    $max_tol = 0.0;
    $min_tol = 0.0;

    if ($max_cust == 0 && $min_cust == 0) {
        $sg = trim($full_data['SURFACE_GRADE'] ?? '');
        $mg = trim($full_data['METALLURGICAL_GRADE'] ?? '');
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

    $full_data['MAX_TOLERANCE'] = $max_tol;
    $full_data['MIN_TOLERANCE'] = $min_tol;
    $full_data['AVG_THICKNESS'] = ($max_tol + $min_tol) / 2;

    $slot_index = $location_row + 1;

    echo json_encode([
        'status'     => 'success',
        'message'    => "RETURN Coil [{$strCoilNo}] กลับเข้าสู่ Coil on Processing เรียบร้อยแล้ว",
        'slot_index' => $slot_index,
        'data'       => $full_data
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}