<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../dbcon_mats-new.php';

try {
    // รับค่า Batch No (bno) ที่ส่งมาจากหน้า Edit (ถ้ามี)
    $bno = isset($_GET['bno']) ? trim($_GET['bno']) : '';

    // ปรับ Query ให้ดึงทั้งสถานะ Issued และรายการที่ผูกอยู่กับ Batch ปัจจุบันนี้แล้ว
    $sql = "SELECT 
                d.req_detail_id,
                d.req_no,
                d.container_no,
                d.packing_no,
                d.net_weight_kg,
                d.piece_count,
                d.country_of_origin,
                h.req_date,
                w.issue_no
            FROM REQUISITION_DETAIL d
            LEFT JOIN REQUISITION_HEADER h ON LTRIM(RTRIM(d.req_no)) = LTRIM(RTRIM(h.req_no))
            LEFT JOIN WIP_ISSUE_TRANSACTIONS w 
                ON LTRIM(RTRIM(d.container_no)) = LTRIM(RTRIM(w.container_no)) 
                AND LTRIM(RTRIM(d.packing_no)) = LTRIM(RTRIM(w.packing_no))
            WHERE LTRIM(RTRIM(d.status)) = 'Issued' 
               OR (w.issue_no = :bno AND :bno <> '')
            ORDER BY d.container_no ASC, d.packing_no ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':bno' => $bno]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
?>