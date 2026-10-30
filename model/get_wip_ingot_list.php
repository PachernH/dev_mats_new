<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../dbcon_mats-new.php';

try {
    // ดึงรายการ Packing No จาก WIP ที่สถานะเป็น 'Issued' (รอผลิตนำไปใช้)
    $sql = "SELECT 
                d.req_detail_id,
                d.req_no,
                d.container_no,
                d.packing_no,
                d.net_weight_kg,
                d.piece_count,
                d.country_of_origin,
                h.req_date
            FROM REQUISITION_DETAIL d
            LEFT JOIN REQUISITION_HEADER h ON LTRIM(RTRIM(d.req_no)) = LTRIM(RTRIM(h.req_no))
            WHERE LTRIM(RTRIM(d.status)) = 'Issued'
            ORDER BY d.container_no ASC, d.packing_no ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
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