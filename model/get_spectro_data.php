<?php
// ล้าง Output Buffer ป้องกันไม่ให้มีข้อความ เช่น "Connected ..." ปนออกไปกับ JSON[cite: 9]
ob_start(); //[cite: 9]

header('Content-Type: application/json; charset=utf-8'); //[cite: 9]

// ถอยออกไป 1 ชั้นเพื่อเรียกไฟล์ dbcon ที่อยู่ด้านนอก[cite: 9]
include("../dbcon_spectro_mats.php"); //[cite: 9]

// เคลียร์ข้อความ echo หรือ warning จากไฟล์ include ด้านบนทิ้งทั้งหมด[cite: 9]
ob_clean(); //[cite: 9]

$sampleno = isset($_GET['sampleno']) ? trim($_GET['sampleno']) : ''; //[cite: 9]

if (empty($sampleno)) { //[cite: 9]
    echo json_encode(['success' => false, 'message' => 'Sample No / Cast No is required']); //[cite: 9]
    exit; //[cite: 9]
}

try {
    // SQL Query ดึงทั้งค่า ItemValue และ CalibrationFlag[cite: 9]
    $sql = "SELECT a.SampleNo, b.ItemName, b.CalibrationFlag, b.ItemValue
            FROM ANALYSISHEADER AS a 
            INNER JOIN ANALYSISVALUES AS b ON a.AnalysisNo = b.AnalysisNo
            WHERE a.SampleNo = :sampleno"; //[cite: 9]

    $stmt = $conn->prepare($sql); //[cite: 9]
    $stmt->bindParam(':sampleno', $sampleno, PDO::PARAM_STR); //[cite: 9]
    $stmt->execute(); //[cite: 9]
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); //[cite: 9]

    if (empty($rows)) { //[cite: 9]
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล Spectro สำหรับ Sample No: ' . $sampleno]); //[cite: 9]
        exit; //[cite: 9]
    }

    $data  = [];
    $flags = [];

    foreach ($rows as $row) { //[cite: 9]
        $element = strtoupper(trim($row['ItemName'])); //[cite: 9]
        
        // แปลงค่า ItemValue เป็น Float และ Format ให้เป็นทศนิยม 4 ตำแหน่ง
        $val = floatval($row['ItemValue']);
        $data[$element] = number_format($val, 4, '.', '');

        // จัดเก็บค่าสัญลักษณ์ CalibrationFlag
        $flag_val = isset($row['CalibrationFlag']) ? trim($row['CalibrationFlag']) : '';
        $flags[$element] = $flag_val;
    }

    // ส่ง JSON กลับไปยัง JavaScript หน้าบ้าน
    echo json_encode([
        'success' => true, 
        'data'    => $data,
        'flags'   => $flags
    ]);

} catch (PDOException $e) { //[cite: 9]
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]); //[cite: 9]
}
?>