<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include('../dbcon_mats-new.php');

$response = array('message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ดึงค่า Composite Keys ชุดเดิม (เงื่อนไขสำหรับ WHERE clause)
        $old_alloy     = isset($_POST['old_alloy']) ? trim($_POST['old_alloy']) : '';
        $old_temper    = isset($_POST['old_temper']) ? trim($_POST['old_temper']) : '';
        $old_thickness = isset($_POST['old_thickness']) ? floatval($_POST['old_thickness']) : 0.0;
        $old_width     = isset($_POST['old_width']) ? floatval($_POST['old_width']) : 0.0;
        $old_length    = isset($_POST['old_length']) ? floatval($_POST['old_length']) : 0.0;

        // รับค่าข้อมูลใหม่ที่ถูกแก้ไข
        $inp_alloy     = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
        $inp_temper    = isset($_POST['inp_temper']) ? trim($_POST['inp_temper']) : '';
        $inp_thickness = isset($_POST['inp_thickness']) ? floatval($_POST['inp_thickness']) : 0.0;
        $inp_width     = isset($_POST['inp_width']) ? floatval($_POST['inp_width']) : 0.0;
        $inp_length    = isset($_POST['inp_length']) ? floatval($_POST['inp_length']) : 0.0;
        $inp_desc      = isset($_POST['inp_desc']) ? trim($_POST['inp_desc']) : null;

        if (empty($old_alloy) || empty($old_temper) || empty($inp_alloy) || empty($inp_temper)) {
            $response['error'] = 'ข้อมูลรหัส Key หลักเพื่อใช้แก้ไขไม่ครบถ้วน';
            echo json_encode($response);
            exit;
        }

        // จัดการเตรียม SQL Update อ้างอิงชื่อฟิลด์จาก table_2.jpg
        $sql = "UPDATE STNDMSTR1 SET 
                    ALLOY = :inp_alloy,
                    TEMPER = :inp_temper,
                    THICKNESS = :inp_thickness,
                    WIDTH = :inp_width,
                    LENGTH = :inp_length,
                    DESCRIPTION = :inp_desc,
                    STND_MINTHICKNESS = :th_min, STND_MAXTHICKNESS = :th_max,
                    STND_MINWIDTH = :wi_min, STND_MAXWIDTH = :wi_max,
                    STND_MINLENGTH = :le_min, STND_MAXLENGTH = :le_max,
                    STND_MINUTS = :uts_min, STND_MAXUTS = :uts_max,
                    STND_MINYIELDSTRENGTH = :ys_min, STND_MAXYIELDSTRENGTH = :ys_max,
                    STND_MINELONGATION = :el_min, STND_MAXELONGATION = :el_max,
                    STND_MINEARING = :ea_min, STND_MAXEARING = :ea_max
                WHERE ALLOY = :old_alloy 
                  AND TEMPER = :old_temper 
                  AND THICKNESS = :old_thickness 
                  AND WIDTH = :old_width 
                  AND LENGTH = :old_length";

        $stmt = $conn->prepare($sql);

        // Bind ค่าข้อมูลหลักและขนาดผลิตภัณฑ์
        $stmt->bindParam(':inp_alloy', $inp_alloy);
        $stmt->bindParam(':inp_temper', $inp_temper);
        $stmt->bindParam(':inp_thickness', $inp_thickness);
        $stmt->bindParam(':inp_width', $inp_width);
        $stmt->bindParam(':inp_length', $inp_length);
        $stmt->bindParam(':inp_desc', $inp_desc);

        // ลูปดึงค่าและ Bind Parameters ข้อกำหนดช่วงมาตรฐาน
        $elements = array('th', 'wi', 'le', 'uts', 'ys', 'el', 'ea');
        foreach ($elements as $el) {
            $min_val = isset($_POST[$el.'_min']) ? floatval($_POST[$el.'_min']) : 0.0;
            $max_val = isset($_POST[$el.'_max']) ? floatval($_POST[$el.'_max']) : 0.0;
            
            $stmt->bindValue(':'.$el.'_min', $min_val);
            $stmt->bindValue(':'.$el.'_max', $max_val);
        }

        // Bind ค่าเงื่อนไขการค้นหาชุดเดิม (WHERE Clause)
        $stmt->bindParam(':old_alloy', $old_alloy);
        $stmt->bindParam(':old_temper', $old_temper);
        $stmt->bindParam(':old_thickness', $old_thickness);
        $stmt->bindParam(':old_width', $old_width);
        $stmt->bindParam(':old_length', $old_length);

        if ($stmt->execute()) {
            $response['message'] = true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['error'] = 'เกิดข้อผิดพลาดในการรัน SQL: ' . $errorInfo[2];
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Connect Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid Request HTTP Method';
}

echo json_encode($response);
exit;
?>