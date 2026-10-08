<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบ Session การเข้าใช้งาน
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include '../function_mats.php'; // ไฟล์เชื่อมต่อ Database ($conn)

include '../dbcon_mats-new.php';

// 2. รับและทำความสะอาดข้อมูล Input
$coil_no         = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$defect_id       = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
$defect_position = isset($_POST['defect_position']) ? trim($_POST['defect_position']) : 'T';
$defect_qty      = isset($_POST['defect_qty']) ? intval($_POST['defect_qty']) : 1;
$pass_no         = 0; // Fix ค่าตามเงื่อนไขของ Process CD

// 3. Validation
if (empty($coil_no) || empty($defect_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

try {
    // 4. ตรวจสอบว่ามี Primary Key (COIL_NO, DEFECT_ID, PASS_NO) ซ้ำในระบบหรือไม่
    $sql_check = "SELECT DEFECT_QTY FROM COILINSP2 
                  WHERE COIL_NO = :coil_no 
                  AND DEFECT_ID = :defect_id 
                  AND PASS_NO = :pass_no 
                  AND PROCESS = 'CD'";
                  
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([
        ':coil_no'   => $coil_no,
        ':defect_id' => $defect_id,
        ':pass_no'   => $pass_no
    ]);

    $existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_record) {
        // กรณีมีรายการเดิมอยู่แล้ว -> ให้ UPDATE เพิ่มจำนวน QTY และปรับ Position
        $new_qty = $existing_record['DEFECT_QTY'] + $defect_qty;

        $sql_update = "UPDATE COILINSP2 
                       SET DEFECT_QTY = :defect_qty, 
                           DEFECT_POSITION = :defect_position 
                       WHERE COIL_NO = :coil_no 
                       AND DEFECT_ID = :defect_id 
                       AND PASS_NO = :pass_no 
                       AND PROCESS = 'CD'";

        $stmt_update = $conn->prepare($sql_update);
        $result = $stmt_update->execute([
            ':defect_qty'      => $new_qty,
            ':defect_position' => $defect_position,
            ':coil_no'         => $coil_no,
            ':defect_id'       => $defect_id,
            ':pass_no'         => $pass_no
        ]);

        $message = 'Defect quantity updated successfully';
    } else {
        // กรณีเป็นรายการใหม่ -> ให้ INSERT เข้าตารางตามปกติ
        $sql_insert = "INSERT INTO COILINSP2 
                       (COIL_NO, DEFECT_ID, PASS_NO, PROCESS, DEFECT_POSITION, DEFECT_QTY, ACTION_HEAD, ACTION_MID, ACTION_TAIL, ACTION_WHOLE) 
                       VALUES 
                       (:coil_no, :defect_id, :pass_no, 'CD', :defect_position, :defect_qty, '0', '0', '0', '0')";

        $stmt_insert = $conn->prepare($sql_insert);
        $result = $stmt_insert->execute([
            ':coil_no'         => $coil_no,
            ':defect_id'       => $defect_id,
            ':pass_no'         => $pass_no,
            ':defect_position' => $defect_position,
            ':defect_qty'      => $defect_qty
        ]);

        $message = 'Defect added successfully';
    }

    if ($result) {
        echo json_encode(['status' => 'success', 'message' => $message]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to process request']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>