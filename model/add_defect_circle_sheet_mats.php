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
$pd_no         = isset($_POST['pd_no']) ? trim($_POST['pd_no']) : '';
$defect_id       = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
$defect_qty      = isset($_POST['defect_qty']) ? intval($_POST['defect_qty']) : 1;

// 3. Validation
if (empty($pd_no) || empty($defect_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

try {
    $sql_check = "SELECT DEFECT_QTY FROM CRSHINSP4 
                  WHERE PRODUCT_NO = :pd_no 
                  AND DEFECT_ID = :defect_id";
                  
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([
        ':pd_no'   => $pd_no,
        ':defect_id' => $defect_id
    ]);

    $existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing_record) {
        // กรณีมีรายการเดิมอยู่แล้ว -> ให้ UPDATE เพิ่มจำนวน QTY และปรับ Position
        $new_qty = $existing_record['DEFECT_QTY'] + $defect_qty;

        $sql_update = "UPDATE CRSHINSP4 
                       SET DEFECT_QTY = :defect_qty
                       WHERE PRODUCT_NO = :pd_no 
                       AND DEFECT_ID = :defect_id";

        $stmt_update = $conn->prepare($sql_update);
        $result = $stmt_update->execute([
            ':defect_qty'      => $new_qty,
            ':pd_no'         => $pd_no,
            ':defect_id'       => $defect_id
        ]);

        $message = 'Defect quantity updated successfully';
    } else {
        // กรณีเป็นรายการใหม่ -> ให้ INSERT เข้าตารางตามปกติ
        $sql_insert = "INSERT INTO CRSHINSP4 
                       (PRODUCT_NO, DEFECT_ID, DEFECT_QTY) 
                       VALUES 
                       (:pd_no, :defect_id, :defect_qty)";

        $stmt_insert = $conn->prepare($sql_insert);
        $result = $stmt_insert->execute([
            ':pd_no'         => $pd_no,
            ':defect_id'       => $defect_id,
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