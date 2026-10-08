<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reqNo    = $_POST['req_no'] ?? '';
    $prodNo   = $_POST['prod_no'] ?? '';
    $coilNo   = $_POST['coil_no'] ?? '';

    if (empty($reqNo)) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล REQUEST_NO'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn->beginTransaction();

        // ลบเฉพาะแถวรายการ (By Line) ใน PRODRMLT2
        $sql = "DELETE FROM PRODRMLT2 
                WHERE REQUEST_NO = :req_no AND PRODUCT_NO = :prod_no AND COIL_NO = :coil_no";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':req_no'  => $reqNo,
            ':prod_no' => $prodNo,
            ':coil_no' => $coilNo
        ]);

        // เช็คว่าถ้า PRODRMLT2 ไม่เหลือรายการใดๆ แล้ว ให้ลบ Header ใน PRODRMLT1 ด้วย
        $checkSql = "SELECT COUNT(*) FROM PRODRMLT2 WHERE REQUEST_NO = :req_no";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([':req_no' => $reqNo]);
        if ($checkStmt->fetchColumn() == 0) {
            $delHeader = "DELETE FROM PRODRMLT1 WHERE REQUEST_NO = :req_no";
            $stmtH = $conn->prepare($delHeader);
            $stmtH->execute([':req_no' => $reqNo]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'ลบรายการเรียบร้อยแล้ว!'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}