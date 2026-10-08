<?php
// เริ่ม session เพื่อใช้ $_SESSION ก่อน ANY output
session_start();

// กำหนด header และระบบปิด error output ส่ง JSON response
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('../dbcon_mats-new.php');
date_default_timezone_set("Asia/Bangkok");

$response = array();
$response['message'] = false;

try {
    // บังคับให้ PDO โยน Exception เสมอเมื่อเกิดข้อผิดพลาด
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ดึง operator จาก session
    $operator = isset($_SESSION['id']) ? $_SESSION['id'] : 'unknown';
    $operateDate = date('Y-m-d H:i:s');
    $furnace = 'MELT';

    if(isset($_POST['data_tag']) && !empty($_POST['data_tag'])){
        $data1 = explode("*", $_POST['data_tag']);

            // ป้องกันปัญหา Data Type Mismatch: ล้างค่าช่องว่างให้เป็นค่าตัวเลขที่ถูกต้อง
            $product_no = trim($data1[0]);
            $alloy_no = trim($data1[1]);
            $range_f = trim($data1[2]);
            $range_t = trim($data1[3]);
            $temper_i = trim($data1[4]);
            $temper_t = trim($data1[5]);

            // เริ่ม Transaction
            $conn->beginTransaction();
            $sql1 = "DELETE FROM TPMAT1001 WHERE PRODUCT_ID = :product_no AND ALLOY = :alloy_no AND RANGE_FROM = :range_f AND RANGE_TO = :range_t AND TEMPER_INITIAL = :temper_i AND TEMPER_TARGET = :temper_t";    
            $stmt1 = $conn->prepare($sql1);
            
            // ผูกตัวแปรแบบระบุประเภท เพื่อป้องกันปัญหาระบบฐานข้อมูลตีความชนิดข้อมูลผิดเพี้ยน
            $stmt1->bindParam(':product_no', $product_no, PDO::PARAM_STR);
            $stmt1->bindParam(':alloy_no', $alloy_no, PDO::PARAM_STR);
            $stmt1->bindParam(':range_f', $range_f, PDO::PARAM_STR);
            $stmt1->bindParam(':range_t', $range_t, PDO::PARAM_STR);
            $stmt1->bindParam(':temper_i', $temper_i, PDO::PARAM_STR);
            $stmt1->bindParam(':temper_t', $temper_t, PDO::PARAM_STR);
                    
            $result1 = $stmt1->execute();
            
            // Commit transaction ถ้าทั้งสองกระบวนการทำงานได้สำเร็จ
            if($result1){
                $conn->commit();
                $response['message'] = true;
            } else {
                $conn->rollBack();
                $response['error'] = 'Failed to insert data into one or both tables';
                $response['error_detail'] = [
                    'result1' => $result1,
                    'error1' => $stmt1->errorInfo(),
                ];
             }
    } else {
        $response['error'] = 'No data received';
    }
} catch(PDOException $e) { // แยกดักข้อผิดพลาดจากฝั่งฐานข้อมูล (SQL Error)
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
} catch(Exception $e) { // ดักจับข้อผิดพลาดของระบบทั่วไป
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'System Error: ' . $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>