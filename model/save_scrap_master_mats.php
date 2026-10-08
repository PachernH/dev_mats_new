<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

include('../dbcon_mats-new.php');

$response = array('status' => 'error', 'message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = isset($_POST['data_id']) ? trim($_POST['data_id']) : '';
    $process = isset($_POST['data_process']) ? trim($_POST['data_process']) : '';
    $volum   = isset($_POST['data_volum']) && $_POST['data_volum'] !== '' ? floatval($_POST['data_volum']) : 0;
    $unit    = isset($_POST['data_unit']) ? trim($_POST['data_unit']) : '';
    $comment = isset($_POST['data_comment']) ? trim($_POST['data_comment']) : '';

    if (empty($process)) {
        $response['error'] = 'Process name is required.';
        echo json_encode($response);
        exit;
    }

    try {
        if (!empty($id)) {
            // --- กรณีแก้ไขข้อมูล (UPDATE) ---
            $sql = "UPDATE STNDMSTR14 
                    SET PROCESS = :process, 
                        VOLUM = :volum, 
                        UNIT = :unit, 
                        COMMENT = :comment 
                    WHERE ID = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        } else {
            // --- กรณีเพิ่มข้อมูลใหม่ (INSERT) ---
            // 1. หาค่า ID สูงสุดปัจจุบัน แล้ว +1
            $sqlMax = "SELECT ISNULL(MAX(ID), 0) + 1 AS new_id FROM STNDMSTR14";
            $stmtMax = $conn->prepare($sqlMax);
            $stmtMax->execute();
            $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
            $new_id = $rowMax['new_id'];

            // 2. บันทึกข้อมูล
            $sql = "INSERT INTO STNDMSTR14 (ID, PROCESS, VOLUM, UNIT, COMMENT) 
                    VALUES (:id, :process, :volum, :unit, :comment)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $new_id, PDO::PARAM_INT);
        }

        $stmt->bindParam(':process', $process, PDO::PARAM_STR);
        $stmt->bindParam(':volum', $volum);
        $stmt->bindParam(':unit', $unit, PDO::PARAM_STR);
        $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = true;
        } else {
            $response['error'] = 'Failed to save data.';
        }
    } catch (PDOException $e) {
        $response['error'] = 'Database Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid Request Method.';
}

echo json_encode($response);
exit;