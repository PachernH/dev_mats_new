<?php
// เริ่ม session เพื่อใช้ $_SESSION ก่อน ANY output
session_start();

// กำหนด header ส่ง JSON response
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// เปลี่ยนมาใช้ไฟล์เชื่อมต่อ DB เดียวกันกับหน้าแสดงผล (dbcon_mats-new.php)
include('../function_mats.php');
include('../dbcon_mats-new.php');

date_default_timezone_set("Asia/Bangkok");

$response = array();
$response['message'] = false;

try {
    if (isset($conn)) {
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    if (isset($_POST['data_tag']) && !empty($_POST['data_tag'])) {
        // แยกค่า PROCESS, DEFECT_ID, DESCRIPTION จาก data_tag
        $data1 = explode("*", $_POST['data_tag']);

        $defect_pro = isset($data1[0]) ? trim($data1[0]) : '';
        $defect_no  = isset($data1[1]) ? trim($data1[1]) : '';

        if (!empty($defect_pro) && !empty($defect_no)) {
            
            // ลบข้อมูลจากตาราง DFCTMSTR1 โดยตัด Whitespace ทั้งฝั่ง DB และ Parameter
            $sql1 = "DELETE FROM DFCTMSTR1 
                    WHERE LTRIM(RTRIM(PROCESS)) = :defect_pro 
                    AND LTRIM(RTRIM(DEFECT_ID)) = :defect_no";
                    
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bindValue(':defect_pro', $defect_pro, PDO::PARAM_STR);
            $stmt1->bindValue(':defect_no', $defect_no, PDO::PARAM_STR);
            $stmt1->execute();

            // ตรวจสอบจำนวนแถวที่ลบสำเร็จ
            if ($stmt1->rowCount() > 0) {
                $response['message'] = true;
            } else {
                $response['error'] = 'ไม่พบข้อมูลในระบบ (PROCESS: ' . $defect_pro . ', ID: ' . $defect_no . ')';
            }
        } else {
            $response['error'] = 'ข้อมูลที่ส่งมาไม่ถูกต้อง';
        }
    } else {
        $response['error'] = 'No data received';
    }
} catch (PDOException $e) {
    $response['error'] = 'Database Error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['error'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>