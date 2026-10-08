<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqNo = $_POST['req_no'] ?? '';

    if (empty($reqNo)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบ REQUEST_NO ที่ต้องการลบ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. ลบจาก PRODRMLT2 (Detail)
        $sql2 = "DELETE FROM PRODRMLT2 WHERE REQUEST_NO = :req_no";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([':req_no' => $reqNo]);

        // 2. ลบจาก PRODRMLT1 (Header)
        $sql1 = "DELETE FROM PRODRMLT1 WHERE REQUEST_NO = :req_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([':req_no' => $reqNo]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'ลบรายการสำเร็จเรียบร้อยแล้ว!'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}