<?php
include('../dbcon_mats-new.php');

$furnace_no = $_POST['furnace_no'];
$charging_date = $_POST['charging_date'];

$date_st = $charging_date." 00:00:00";
$date_end = $charging_date." 23:59:59";

// แก้ไข: ตรวจสอบว่ามีข้อมูลหรือไม่
if(empty($furnace_no) || empty($charging_date)) {
    echo json_encode(['last_sequence' => 0, 'error' => 'Missing parameters']);
    exit;
}

// แปลงวันที่เป็นรูปแบบ YYMMDD เช่น 2025-01-15 -> 250115
$dateObj = new DateTime($charging_date);
$dateCode = $dateObj->format('ymd'); // ได้ 250115
$pattern = 'M' . $dateCode . '-' . $furnace_no . '-%';

// แก้ไข: ใช้ชื่อฟิลด์ที่ถูกต้อง (FURNACE และ BATCH_DATE)
$sql = "SELECT BATCH_NO 
        FROM FRNCPRCS1 
        WHERE LINE_PROCESS = :furnace_no 
        AND BATCH_DATE between :date_st and :date_end
        ORDER BY BATCH_NO DESC";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':furnace_no', $furnace_no);
$stmt->bindParam(':date_st', $date_st);
$stmt->bindParam(':date_end', $date_end);

$stmt->execute();

$result = $stmt->fetch(PDO::FETCH_ASSOC);
$last_seq = 0;

if($result && isset($result['BATCH_NO'])) {
    $parts = explode('-', $result['BATCH_NO']);
    if(count($parts) >= 3) {
        $last_seq = intval($parts[2]);
    }
}

echo json_encode(['last_sequence' => $last_seq]);
?>