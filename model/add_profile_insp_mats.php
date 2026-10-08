<?php
// ปิด Warning ป้องกัน HTML หลุดแทรกไปใน JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล (PDO)
include '../dbcon_mats-new.php';

// รับค่าหลัก COIL_NO
$coil_no = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';

if (empty($coil_no)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ COIL_NO']);
    exit();
}

// รับค่า Thickness Profile และ Average Thickness จากหน้า Front-end
$thickness_profile = isset($_POST['THICKNESS_PROFILE']) ? floatval($_POST['THICKNESS_PROFILE']) : 0.0000;
$thickness_average = isset($_POST['THICKNESS_AVERAGE']) ? floatval($_POST['THICKNESS_AVERAGE']) : 0.0000;

// เตรียมข้อมูล S_THICKNESS01-33 และ F_THICKNESS01-33
$s_vals = [];
$f_vals = [];

for ($i = 1; $i <= 33; $i++) {
    $idx = sprintf("%02d", $i);
    $s_vals[$idx] = isset($_POST["S_THICKNESS{$idx}"]) ? floatval($_POST["S_THICKNESS{$idx}"]) : 0;
    $f_vals[$idx] = isset($_POST["F_THICKNESS{$idx}"]) ? floatval($_POST["F_THICKNESS{$idx}"]) : 0;
}

try {
    // 1. ตรวจสอบว่ามีข้อมูล COIL_NO ใน COILINSP4 แล้วหรือไม่
    $sql_check = "SELECT COUNT(*) FROM COILINSP4 WHERE COIL_NO = :coil_no";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':coil_no' => $coil_no]);
    $exists = ($stmt_check->fetchColumn() > 0);

    if ($exists) {
        // 2. กรณีมีข้อมูลอยู่แล้ว -> UPDATE ค่า THICKNESS_PROFILE, THICKNESS_AVERAGE และค่า Thickness ทั้งหมด
        $update_fields = [
            "THICKNESS_PROFILE = :THICKNESS_PROFILE", 
            "THICKNESS_AVERAGE = :THICKNESS_AVERAGE"
        ];
        
        $params = [
            ':THICKNESS_PROFILE' => $thickness_profile,
            ':THICKNESS_AVERAGE' => $thickness_average,
            ':coil_no' => $coil_no
        ];

        for ($i = 1; $i <= 33; $i++) {
            $idx = sprintf("%02d", $i);
            $update_fields[] = "S_THICKNESS{$idx} = :S{$idx}";
            $params[":S{$idx}"] = $s_vals[$idx];
        }
        for ($i = 1; $i <= 33; $i++) {
            $idx = sprintf("%02d", $i);
            $update_fields[] = "F_THICKNESS{$idx} = :F{$idx}";
            $params[":F{$idx}"] = $f_vals[$idx];
        }

        $sql_execute = "UPDATE COILINSP4 SET " . implode(', ', $update_fields) . " WHERE COIL_NO = :coil_no";
        $stmt_execute = $conn->prepare($sql_execute);
        $stmt_execute->execute($params);

    } else {
        // 3. กรณีไม่มีข้อมูล -> INSERT เรคคอร์ดใหม่พร้อมบันทึก THICKNESS_PROFILE และ THICKNESS_AVERAGE
        $cols = ['COIL_NO', 'THICKNESS_PROFILE', 'THICKNESS_AVERAGE'];
        $placeholders = [':coil_no', ':THICKNESS_PROFILE', ':THICKNESS_AVERAGE'];
        
        $params = [
            ':coil_no' => $coil_no,
            ':THICKNESS_PROFILE' => $thickness_profile,
            ':THICKNESS_AVERAGE' => $thickness_average
        ];

        for ($i = 1; $i <= 33; $i++) {
            $idx = sprintf("%02d", $i);
            $cols[] = "S_THICKNESS{$idx}";
            $placeholders[] = ":S{$idx}";
            $params[":S{$idx}"] = $s_vals[$idx];
        }
        for ($i = 1; $i <= 33; $i++) {
            $idx = sprintf("%02d", $i);
            $cols[] = "F_THICKNESS{$idx}";
            $placeholders[] = ":F{$idx}";
            $params[":F{$idx}"] = $f_vals[$idx];
        }

        $sql_execute = "INSERT INTO COILINSP4 (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt_execute = $conn->prepare($sql_execute);
        $stmt_execute->execute($params);
    }

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>