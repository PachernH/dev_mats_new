<?php
// model/inlet_coil_coldmill_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบสิทธิ์การเข้าใช้งาน
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
    // 2. รับค่าช่องที่เลือก (ถ้ามีส่งมาจาก Frontend)
    $selected_slots = isset($_POST['selected_slots']) ? json_decode($_POST['selected_slots'], true) : null;

    // แปลง slot_index (1-7) ให้เป็น LOCATION_ROW (0-6)
    $target_rows = [];
    if (!empty($selected_slots) && is_array($selected_slots)) {
        foreach ($selected_slots as$slot) {
            $slot_int = intval($slot);
            if ($slot_int >= 1 &&$slot_int <= 7) {
                $target_rows[] =$slot_int - 1; // 1-based -> 0-based
            }
        }
    }

    // 3. ค้นหา Coil ใน Outlet Area (LOCATION = 'O')
    // วนจากช่องหลังสุดมาหน้าสุด (N = 6 To 0 Step -1 หรือ LOCATION_ROW DESC)
    if (!empty($target_rows)) {$placeholders = implode(',', array_fill(0, count($target_rows), '?'));$sql_find = "SELECT TOP 1 COIL_NO, LOCATION_ROW FROM CDMLMCHN1 WITH (NOLOCK) 
                     WHERE LOCATION = 'O' 
                       AND LOCATION_ROW IN ({$placeholders})
                       AND COIL_NO IS NOT NULL 
                       AND RTRIM(LTRIM(COIL_NO)) <> ''
                     ORDER BY LOCATION_ROW DESC";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->execute($target_rows);
    } else {
        $sql_find = "SELECT TOP 1 COIL_NO, LOCATION_ROW FROM CDMLMCHN1 WITH (NOLOCK) 
                     WHERE LOCATION = 'O' 
                       AND COIL_NO IS NOT NULL 
                       AND RTRIM(LTRIM(COIL_NO)) <> ''
                     ORDER BY LOCATION_ROW DESC";
        $stmt_find =$conn->prepare($sql_find);$stmt_find->execute();
    }

    $row =$stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $msg = !empty($target_rows) 
            ? 'ไม่พบข้อมูล Coil ในช่อง Outlet Area ที่เลือก' 
            : 'ไม่พบข้อมูล Coil ในพื้นที่ Outlet Area สำหรับย้ายไปยัง Inlet Area';
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit();
    }

    $strCoilNo    = trim($row['COIL_NO']);
    $old_loc_row  = intval($row['LOCATION_ROW']);
    $from_slot    =$old_loc_row + 1; // Slot Index ฝั่ง Outlet (1-7)

    // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
    $conn->beginTransaction();

    // 4. UPDATE CDMLMCHN1 SET LOCATION = 'I' WHERE COIL_NO = ?
    $sql_upd = "UPDATE CDMLMCHN1 SET LOCATION = ? WHERE COIL_NO = ?";
    $stmt_upd = $conn->prepare($sql_upd);
    $stmt_upd->execute(['I',$strCoilNo]);

    // 5. DELETE FROM CDMLMCHN0 WHERE COIL_NO = ?
    $sql_del = "DELETE FROM CDMLMCHN0 WHERE COIL_NO = ?";
    $stmt_del = $conn->prepare($sql_del);
    $stmt_del->execute([$strCoilNo]);

    // ยืนยันการทำคำสั่งกับ Database
    $conn->commit();

    // 6. ส่งผลลัพธ์กลับไปยัง Client
    echo json_encode([
        'status'     => 'success',
        'message'    => "ย้าย Coil [{$strCoilNo}] ไปยัง Inlet Area เรียบร้อยแล้ว",
        'coil_no'    => $strCoilNo,
        'from_slot'  => $from_slot, // ส่ง slot_index ฝั่ง Outlet ที่โดนดึงออกไป เพื่อเอาไปเคลียร์หน้าจอ JavaScript
        'to_slot'    => $from_slot  // ตำแหน่ง LOCATION_ROW เท่าเดิมแต่เปลี่ยน LOCATION = 'I'
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {$conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>