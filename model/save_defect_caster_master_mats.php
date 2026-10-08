<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบ Session การเข้าใช้งาน
if (!isset($_SESSION['ID'])) {
    echo json_encode(['message' => false, 'error' => 'Unauthorized access']);
    exit();
}

include '../function_mats.php';
include "../dbcon_mats-new.php"; // ปรับ Path DB Connection ให้ตรงกับระบบ

// 2. รับและจัดระเบียบข้อมูลจาก Form
$process     = isset($_POST['data_defect']) ? trim(strtoupper($_POST['data_defect'])) : '';
$description = isset($_POST['data_desc']) ? trim($_POST['data_desc']) : '';

// Validation เบื้องต้น
if (empty($process) || empty($description)) {
    echo json_encode(['message' => false, 'error' => 'กรุณากรอกข้อมูล Process และ Description ให้ครบถ้วน']);
    exit();
}

try {
    // 3. คำนวณหา DEFECT_ID ถัดไปแบบ Running Number แยกตาม PROCESS
    $new_defect_id = '';

    if ($process === 'CM') {
        // สำหรับ CM: ตรวจสอบตัวเลขสูงสุดของกลุ่ม 'A' เป็นหลัก
        $sql_max = "SELECT MAX(CAST(SUBSTRING(DEFECT_ID, 2, 10) AS INT)) AS max_num 
                    FROM DFCTMSTR1 
                    WHERE PROCESS = 'CM' AND ISNUMERIC(SUBSTRING(DEFECT_ID, 2, 10)) = 1";
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->execute();
        $row_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

        $next_num = (!empty($row_max['max_num'])) ? intval($row_max['max_num']) + 1 : 1;
        $new_defect_id = 'A' . $next_num; // รันต่อเป็น A1, A2, A3...

    } else {
        // สำหรับ CD, CS และ Process อื่นๆ: รันตัวเลขต่อกัน เช่น 01, 02... 33 (ยกเว้นรหัสพิเศษ เช่น 99)
        $sql_max = "SELECT MAX(CAST(DEFECT_ID AS INT)) AS max_num 
                    FROM DFCTMSTR1 
                    WHERE PROCESS = :process 
                    AND ISNUMERIC(DEFECT_ID) = 1 
                    AND DEFECT_ID NOT IN ('99')";
                    
        $stmt_max = $conn->prepare($sql_max);
        $stmt_max->execute([':process' => $process]);
        $row_max = $stmt_max->fetch(PDO::FETCH_ASSOC);

        $next_num = (!empty($row_max['max_num'])) ? intval($row_max['max_num']) + 1 : 1;
        // ฟอร์แมตเป็นเลข 2 หลัก (เช่น 01, 02, ..., 34)
        $new_defect_id = sprintf("%02d", $next_num);
    }

    // 4. บันทึกข้อมูลลงตาราง DFCTMSTR1
    $sql_insert = "INSERT INTO DFCTMSTR1 (PROCESS, DEFECT_ID, DESCRIPTION, DESCRIPTIONTH, ABBREVIATION) 
                   VALUES (:process, :defect_id, :description, '', '')";
                   
    $stmt_insert = $conn->prepare($sql_insert);
    $result = $stmt_insert->execute([
        ':process'     => $process,
        ':defect_id'   => $new_defect_id,
        ':description' => $description
    ]);

    if ($result) {
        // ตอบกลับ JSON ให้สอดคล้องกับ AJAX submitData() ฝั่ง Front-End
        echo json_encode(['message' => true, 'new_id' => $new_defect_id]);
    } else {
        echo json_encode(['message' => false, 'error' => 'ไม่สามารถบันทึกข้อมูลลงฐานข้อมูลได้']);
    }

} catch (PDOException $e) {
    echo json_encode(['message' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>