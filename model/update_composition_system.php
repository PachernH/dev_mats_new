<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
include('../dbcon_mats-new.php');

$response = array('message' => false, 'error' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // รับค่า Keys เดิมสำหรับเป็นเงื่อนไขในการค้นหาเพื่ออัปเดต (WHERE clause)
        $old_alloy = isset($_POST['old_alloy']) ? trim($_POST['old_alloy']) : '';
        $old_cstmsppl = isset($_POST['old_cstmsppl']) ? trim($_POST['old_cstmsppl']) : '';

        // ค่าใหม่ที่ทำการแก้ไข
        $inp_alloy = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
        $inp_cstmsppl = isset($_POST['inp_cstmsppl']) ? trim($_POST['inp_cstmsppl']) : '';
        $inp_gravity = isset($_POST['inp_gravity']) ? floatval($_POST['inp_gravity']) : 0.0;
        $inp_al = isset($_POST['inp_al']) ? floatval($_POST['inp_al']) : 0.0;
        $inp_ti = isset($_POST['inp_ti']) ? floatval($_POST['inp_ti']) : 0.0;

        if (empty($old_alloy) || empty($old_cstmsppl) || empty($inp_alloy) || empty($inp_cstmsppl)) {
            $response['error'] = 'ข้อมูล Key หลักไม่ครบถ้วน';
            echo json_encode($response);
            exit;
        }

        // เตรียมคำสั่ง SQL UPDATE (อิงชื่อคอลัมน์จากตารางภาพ table.jpg)
        $sql = "UPDATE CMPSMSTR1 SET 
                    ALLOY = :inp_alloy,
                    CSTMSPPL_ID = :inp_cstmsppl,
                    DENSITY = :density,
                    AL = :al,
                    TI = :ti,
                    AL_MIN = :al_min, AL_MAX = :al_max,
                    FE_MIN = :fe_min, FE_AVG = :fe_avg, FE_MAX = :fe_max,
                    SI_MIN = :si_min, SI_AVG = :si_avg, SI_MAX = :si_max,
                    MN_MIN = :mn_min, MN_AVG = :mn_avg, MN_MAX = :mn_max,
                    MG_MIN = :mg_min, MG_AVG = :mg_avg, MG_MAX = :mg_max,
                    CR_MIN = :cr_min, CR_AVG = :cr_avg, CR_MAX = :cr_max,
                    CU_MIN = :cu_min, CU_AVG = :cu_avg, CU_MAX = :cu_max,
                    ZN_MIN = :zn_min, ZN_AVG = :zn_avg, ZN_MAX = :zn_max,
                    PB_MIN = :pb_min, PB_AVG = :pb_avg, PB_MAX = :pb_max,
                    AS_MAX = :as_max, NI_MAX = :ni_max, SN_MAX = :sn_max,
                    SB_MAX = :sb_max, BE_MAX = :be_max, BI_MAX = :bi_max, CD_MAX = :cd_max
                WHERE ALLOY = :old_alloy AND CSTMSPPL_ID = :old_cstmsppl";

        $stmt = $conn->prepare($sql);

        // Bind Parameters ข้อมูลหลัก
        $stmt->bindParam(':inp_alloy', $inp_alloy);
        $stmt->bindParam(':inp_cstmsppl', $inp_cstmsppl);
        $stmt->bindParam(':density', $inp_gravity);
        $stmt->bindParam(':al', $inp_al);
        $stmt->bindParam(':ti', $inp_ti);
        
        // Bind Parameters กลุ่มที่มี Min, Avg, Max
        $elements = array('al', 'fe', 'si', 'mn', 'mg', 'cr', 'cu', 'zn', 'pb');
        foreach ($elements as $el) {
            $min_val = isset($_POST[$el.'_min']) ? floatval($_POST[$el.'_min']) : 0.0;
            $avg_val = isset($_POST[$el.'_avg']) ? floatval($_POST[$el.'_avg']) : 0.0;
            $max_val = isset($_POST[$el.'_max']) ? floatval($_POST[$el.'_max']) : 0.0;

            if($el == 'al') {
                // อ้างอิงจาก table.jpg ตารางนี้ไม่มีคอลัมน์ AL_AVG มีแค่ MIN กับ MAX เท่านั้น
                $stmt->bindValue(':al_min', $min_val);
                $stmt->bindValue(':al_max', $max_val);
            } else {
                $stmt->bindValue(':'.$el.'_min', $min_val);
                $stmt->bindValue(':'.$el.'_avg', $avg_val);
                $stmt->bindValue(':'.$el.'_max', $max_val);
            }
        }

        // Bind Parameters กลุ่มที่มีเฉพาะ Max อย่างเดียว
        $max_elements = array('as', 'ni', 'sn', 'sb', 'be', 'bi', 'cd');
        foreach ($max_elements as $el) {
            $max_val = isset($_POST[$el.'_max']) ? floatval($_POST[$el.'_max']) : 0.0;
            $stmt->bindValue(':'.$el.'_max', $max_val);
        }

        // Bind WHERE Clause Keys เดิม
        $stmt->bindParam(':old_alloy', $old_alloy);
        $stmt->bindParam(':old_cstmsppl', $old_cstmsppl);

        if ($stmt->execute()) {
            $response['message'] = true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $response['error'] = 'ไม่สามารถแก้ไขข้อมูลได้: ' . $errorInfo[2];
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Error: ' . $e->getMessage();
    }
} else {
    $response['error'] = 'Invalid Request Method';
}

echo json_encode($response);
exit;
?>