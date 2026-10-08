<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include('../dbcon_mats-new.php');

$response = array('message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // รับค่าคีย์หลัก (ทั้งเก่าและใหม่) เป็นแบบข้อความดึงค่าดิบ (String) ทั้งหมด เพื่อรองรับโครงสร้างตาราง nvarchar
        $old_cust      = isset($_POST['old_cust']) ? trim($_POST['old_cust']) : '';
        $old_pid       = isset($_POST['old_pid']) ? trim($_POST['old_pid']) : '';
        $old_alloy     = isset($_POST['old_alloy']) ? trim($_POST['old_alloy']) : '';
        $old_temper    = isset($_POST['old_temper']) ? trim($_POST['old_temper']) : '';
        $old_thickness = isset($_POST['old_thickness']) ? trim($_POST['old_thickness']) : '';
        $old_width     = isset($_POST['old_width']) ? trim($_POST['old_width']) : '';
        $old_length    = isset($_POST['old_length']) ? trim($_POST['old_length']) : '';

        $inp_cust      = isset($_POST['inp_cust']) ? trim($_POST['inp_cust']) : '';
        $inp_pid       = isset($_POST['inp_pid']) ? trim($_POST['inp_pid']) : '';
        $inp_alloy     = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
        $inp_temper    = isset($_POST['inp_temper']) ? trim($_POST['inp_temper']) : '';
        $inp_thickness = isset($_POST['inp_thickness']) ? trim($_POST['inp_thickness']) : '';
        $inp_width     = isset($_POST['inp_width']) ? trim($_POST['inp_width']) : '';
        $inp_length    = isset($_POST['inp_length']) ? trim($_POST['inp_length']) : '';

        // ข้อมูลสเปกทศนิยมในส่วนล่าง (Data type: real ใน Database) ใช้ floatval ได้ปกติ
        $th_p = isset($_POST['th_p']) ? floatval($_POST['th_p']) : 0.0;
        $th_m = isset($_POST['th_m']) ? floatval($_POST['th_m']) : 0.0;
        $wi_p = isset($_POST['wi_p']) ? floatval($_POST['wi_p']) : 0.0;
        $wi_m = isset($_POST['wi_m']) ? floatval($_POST['wi_m']) : 0.0;
        $le_p = isset($_POST['le_p']) ? floatval($_POST['le_p']) : 0.0;
        $le_m = isset($_POST['le_m']) ? floatval($_POST['le_m']) : 0.0;
        $fl   = isset($_POST['fl']) ? floatval($_POST['fl']) : 0.0;
        $et   = isset($_POST['et']) ? trim($_POST['et']) : '';
        $ep   = isset($_POST['ep']) ? floatval($_POST['ep']) : 0.0;
        $fxp  = isset($_POST['fxp']) ? trim($_POST['fxp']) : 'NO';

        // ปรับปรุงการตรวจสอบค่าว่าง: เนื่องจาก 0.00 อาจถูกมองเป็นค่าว่าง ให้ตรวจสอบเจาะจง String
        if ($old_cust === '' || $old_pid === '' || $old_alloy === '' || $old_temper === '' || $old_thickness === '' || $old_width === '' || $old_length === '') {
            $response['error'] = 'ข้อมูลหลัก (Key) เพื่อทำรายการอัปเดตไม่ครบถ้วน';
            echo json_encode($response);
            exit;
        }

        $sql = "UPDATE STNDMSTR6 SET 
                    CSTMSPPL_ID = :inp_cust,
                    PRODUCT_ID = :inp_pid,
                    ALLOY = :inp_alloy,
                    TEMPER = :inp_temper,
                    THICKNESS = :inp_thickness,
                    WIDTH = :inp_width,
                    LENGTH = :inp_length,
                    THICKNESS_PTOLERANCE = :th_p,
                    THICKNESS_MTOLERANCE = :th_m,
                    WIDTH_PTOLERANCE = :wi_p,
                    WIDTH_MTOLERANCE = :wi_m,
                    LENGTH_PTOLERANCE = :le_p,
                    LENGTH_MTOLERANCE = :le_m,
                    FLATNESS = :fl,
                    EARING_TYPE = :et,
                    EARING_PERCENT = :ep,
                    FIXED_PROCESS = :fxp
                WHERE CSTMSPPL_ID = :old_cust 
                  AND PRODUCT_ID = :old_pid 
                  AND ALLOY = :old_alloy 
                  AND TEMPER = :old_temper 
                  AND THICKNESS = :old_thickness 
                  AND WIDTH = :old_width 
                  AND LENGTH = :old_length";

        $stmt = $conn->prepare($sql);

        // Bind คีย์หลักทั้งหมดในฐานะข้อความ (PDO::PARAM_STR) เพื่อให้ตรงกับโครงสร้าง nvarchar ของ SQL Server
        $stmt->bindParam(':inp_cust', $inp_cust, PDO::PARAM_STR);
        $stmt->bindParam(':inp_pid', $inp_pid, PDO::PARAM_STR);
        $stmt->bindParam(':inp_alloy', $inp_alloy, PDO::PARAM_STR);
        $stmt->bindParam(':inp_temper', $inp_temper, PDO::PARAM_STR);
        $stmt->bindParam(':inp_thickness', $inp_thickness, PDO::PARAM_STR);
        $stmt->bindParam(':inp_width', $inp_width, PDO::PARAM_STR);
        $stmt->bindParam(':inp_length', $inp_length, PDO::PARAM_STR);
        
        // Bind ฟิลด์มาตรฐานทั่วไป
        $stmt->bindParam(':th_p', $th_p);
        $stmt->bindParam(':th_m', $th_m);
        $stmt->bindParam(':wi_p', $wi_p);
        $stmt->bindParam(':wi_m', $wi_m);
        $stmt->bindParam(':le_p', $le_p);
        $stmt->bindParam(':le_m', $le_m);
        $stmt->bindParam(':fl', $fl);
        $stmt->bindParam(':et', $et, PDO::PARAM_STR);
        $stmt->bindParam(':ep', $ep);
        $stmt->bindParam(':fxp', $fxp, PDO::PARAM_STR);

        // Bind เงื่อนไข WHERE คีย์ดั้งเดิม (เปรียบเทียบข้อความชนข้อความ)
        $stmt->bindParam(':old_cust', $old_cust, PDO::PARAM_STR);
        $stmt->bindParam(':old_pid', $old_pid, PDO::PARAM_STR);
        $stmt->bindParam(':old_alloy', $old_alloy, PDO::PARAM_STR);
        $stmt->bindParam(':old_temper', $old_temper, PDO::PARAM_STR);
        $stmt->bindParam(':old_thickness', $old_thickness, PDO::PARAM_STR);
        $stmt->bindParam(':old_width', $old_width, PDO::PARAM_STR);
        $stmt->bindParam(':old_length', $old_length, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $response['message'] = true; 
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['error'] = 'เกิดข้อผิดพลาดจาก SQL: ' . $errorInfo[2];
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Connect Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid Request Method';
}

echo json_encode($response);
exit;
?>