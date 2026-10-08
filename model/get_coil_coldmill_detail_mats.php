<?php
// model/get_coil_detail.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';

    if (empty($coil_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Coil No is required.']);
        exit();
    }

    try {
        $sql = "SELECT COIL_NO, JOB_PROCESS, RECIPE_NO, RECIPE_ITEM, ALLOY, 
                       THICKNESS_ORIGINAL, THICKNESS, THICKNESS_FINAL, 
                       TOTAL_PASS, CURRENT_PASS, COIL_REMARK, JOB_REMARK 
                FROM CDMLMCHN1 
                WHERE COIL_NO = :coil_no";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Coil ในระบบ CDMLMCHN1']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}