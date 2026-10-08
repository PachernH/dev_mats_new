<?php
// เริ่ม session
session_start();

date_default_timezone_set("Asia/Bangkok");

// ปิด error output สำหรับ Production (แต่ถ้าจะ Debug ให้เปลี่ยนเป็น 1)
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = array();
$response['message'] = false;

try {
    if(isset($_POST['data_tag']) && !empty($_POST['data_tag'])) {
        $batch_no = trim($_POST['data_tag']);
        
        include('../dbcon_mats-new.php');
        
        // บังคับให้ PDO โยน Exception เสมอเมื่อเกิดข้อผิดพลาดทางฐานข้อมูล
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // เริ่ม Transaction
        $conn->beginTransaction();
        
        // 1. ลบข้อมูลจากตาราง CHRGTRNS1 ก่อน (เพราะ Foreign Key เผื่อกรณีมีการผูกข้อจำกัดไว้)
        $sql1 = "DELETE FROM CHRGTRNS1 WHERE BATCH_NO = :batch_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bindParam(':batch_no', $batch_no, PDO::PARAM_STR);
        $result1 = $stmt1->execute();
        
        // 2. ลบข้อมูลจากตาราง FRNCPRCS1 (ตารางหลัก)
        $sql2 = "DELETE FROM FRNCPRCS1 WHERE BATCH_NO = :batch_no";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bindParam(':batch_no', $batch_no, PDO::PARAM_STR);
        $result2 = $stmt2->execute();
        
        // ตรวจสอบว่าคำสั่งทำงานได้ปกติหรือไม่
        if($result1 !== false && $result2 !== false) {
            $rowCount1 = $stmt1->rowCount();
            $rowCount2 = $stmt2->rowCount();
            
            // ปรับปรุง Logic: ต้องลบข้อมูลจากตารางหลัก (FRNCPRCS1) สำเร็จ ถึงจะยอมให้ลบ
            if($rowCount2 > 0) {
                $conn->commit();
                $response['message'] = true;
                $response['deleted_rows'] = [
                    'CHRGTRNS1' => $rowCount1,
                    'FRNCPRCS1' => $rowCount2
                ];
            } else {
                $conn->rollBack();
                $response['error'] = 'ไม่พบข้อมูลในระบบ หรือข้อมูลหลักถูกลบไปก่อนหน้านี้แล้ว';
                $response['batch_no'] = $batch_no;
            }
        } else {
            $conn->rollBack();
            $response['error'] = 'เกิดข้อผิดพลาดในการประมวลผลคำสั่งลบ';
            
            $error1 = $stmt1->errorInfo();
            $error2 = $stmt2->errorInfo();
            $response['error_detail'] = [
                'sql1_error' => $error1[2] ?? 'unknown error',
                'sql2_error' => $error2[2] ?? 'unknown error'
            ];
        }
    } else {
        $response['error'] = 'ไม่พบรหัส Batch No ที่ต้องการลบ';
    }
} catch(PDOException $e) { // ดักจับ Error จากฐานข้อมูลโดยเฉพาะ
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
} catch(Exception $e) { // ดักจับ Error ทั่วไปอื่นๆ
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>