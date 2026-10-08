<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบการ Login
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

require_once '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no      = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $defect_id    = isset($_POST['defect_id']) ? trim($_POST['defect_id']) : '';
    $pass_no      = isset($_POST['pass_no']) ? intval($_POST['pass_no']) : 1;
    $action_head  = isset($_POST['action_head']) && $_POST['action_head'] !== '' ? trim($_POST['action_head']) : '0';
    $action_mid   = isset($_POST['action_mid']) && $_POST['action_mid'] !== '' ? trim($_POST['action_mid']) : '0';
    $action_tail  = isset($_POST['action_tail']) && $_POST['action_tail'] !== '' ? trim($_POST['action_tail']) : '0';
    $action_whole = isset($_POST['action_whole']) && $_POST['action_whole'] !== '' ? trim($_POST['action_whole']) : '0';

    $process         = 'CM';
    $defect_position = 'T';
    $defect_qty      = 0;

    if (!empty($coil_no) && !empty($defect_id)) {
        try {
            $sql = "INSERT INTO COILINSP2 
                    (COIL_NO, DEFECT_ID, PASS_NO, PROCESS, DEFECT_POSITION, DEFECT_QTY, ACTION_HEAD, ACTION_MID, ACTION_TAIL, ACTION_WHOLE) 
                    VALUES 
                    (:coil_no, :defect_id, :pass_no, :process, :defect_position, :defect_qty, :action_head, :action_mid, :action_tail, :action_whole)";

            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
            $stmt->bindParam(':defect_id', $defect_id, PDO::PARAM_STR);
            $stmt->bindParam(':pass_no', $pass_no, PDO::PARAM_INT);
            $stmt->bindParam(':process', $process, PDO::PARAM_STR);
            $stmt->bindParam(':defect_position', $defect_position, PDO::PARAM_STR);
            $stmt->bindParam(':defect_qty', $defect_qty, PDO::PARAM_INT);
            $stmt->bindParam(':action_head', $action_head, PDO::PARAM_STR);
            $stmt->bindParam(':action_mid', $action_mid, PDO::PARAM_STR);
            $stmt->bindParam(':action_tail', $action_tail, PDO::PARAM_STR);
            $stmt->bindParam(':action_whole', $action_whole, PDO::PARAM_STR);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Record added successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to 💾 Save record']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in required fields']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}