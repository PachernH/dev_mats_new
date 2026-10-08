<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include('../dbcon_mats-new.php');

$response = array('message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 📌 ปรับปรุง: รับค่า Old Keys ที่เป็นทศนิยมเป็นแบบ String เพื่อให้ SQL นำไปเปรียบเทียบใน WHERE ได้แม่นยำขึ้น
        $old_cust      = isset($_POST['old_cust']) ? trim($_POST['old_cust']) : '';
        $old_pid       = isset($_POST['old_pid']) ? trim($_POST['old_pid']) : '';
        $old_alloy     = isset($_POST['old_alloy']) ? trim($_POST['old_alloy']) : '';
        $old_temper    = isset($_POST['old_temper']) ? trim($_POST['old_temper']) : '';
        $old_thickness = isset($_POST['old_thickness']) ? trim($_POST['old_thickness']) : '0.000';
        $old_width     = isset($_POST['old_width']) ? trim($_POST['old_width']) : '0.000';
        $old_length    = isset($_POST['old_length']) ? trim($_POST['old_length']) : '0.000';

        $inp_cust      = isset($_POST['inp_cust']) ? trim($_POST['inp_cust']) : '';
        $inp_pid       = isset($_POST['inp_pid']) ? trim($_POST['inp_pid']) : '';
        $inp_alloy     = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
        $inp_temper    = isset($_POST['inp_temper']) ? trim($_POST['inp_temper']) : '';
        $inp_thickness = isset($_POST['inp_thickness']) ? floatval($_POST['inp_thickness']) : 0.0;
        $inp_width     = isset($_POST['inp_width']) ? floatval($_POST['inp_width']) : 0.0;
        $inp_length    = isset($_POST['inp_length']) ? floatval($_POST['inp_length']) : 0.0;

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

        if (empty($old_cust) || empty($old_pid) || empty($old_alloy) || empty($old_temper) || empty($old_thickness) || empty($old_width) || empty($old_length)) {
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

        $stmt->bindParam(':inp_cust', $inp_cust);
        $stmt->bindParam(':inp_pid', $inp_pid);
        $stmt->bindParam(':inp_alloy', $inp_alloy);
        $stmt->bindParam(':inp_temper', $inp_temper);
        $stmt->bindParam(':inp_thickness', $inp_thickness);
        $stmt->bindParam(':inp_width', $inp_width);
        $stmt->bindParam(':inp_length', $inp_length);
        
        $stmt->bindParam(':th_p', $th_p);
        $stmt->bindParam(':th_m', $th_m);
        $stmt->bindParam(':wi_p', $wi_p);
        $stmt->bindParam(':wi_m', $wi_m);
        $stmt->bindParam(':le_p', $le_p);
        $stmt->bindParam(':le_m', $le_m);
        $stmt->bindParam(':fl', $fl);
        $stmt->bindParam(':et', $et);
        $stmt->bindParam(':ep', $ep);
        $stmt->bindParam(':fxp', $fxp);

        $stmt->bindParam(':old_cust', $old_cust);
        $stmt->bindParam(':old_pid', $old_pid);
        $stmt->bindParam(':old_alloy', $old_alloy);
        $stmt->bindParam(':old_temper', $old_temper);
        $stmt->bindParam(':old_thickness', $old_thickness);
        $stmt->bindParam(':old_width', $old_width);
        $stmt->bindParam(':old_length', $old_length);

        // 📌 ปรับปรุง: เปลี่ยนจากการเช็ค rowCount() > 0 มาเช็คแค่สถานะการทำงานของ SQL
        if ($stmt->execute()) {
            // ไม่ว่าข้อมูลจะถูกบันทึกทับค่าเดิม หรือแก้ค่าใหม่ หาก SQL รันผ่านถือว่าสำเร็จ
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