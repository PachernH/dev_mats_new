<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please login first']);
    exit();
}

include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no             = trim($_POST['coil_no'] ?? '');
    $new_coldmillweight  = floatval($_POST['coil_coldmillweight'] ?? 0);
    $new_f_thickness     = floatval($_POST['f_thickness'] ?? 0);
    $new_thickness       = floatval($_POST['thickness'] ?? 0);
    $new_width           = floatval($_POST['width'] ?? 0);
    $new_actual_width    = floatval($_POST['actual_width'] ?? 0);
    $new_coil_remark     = trim($_POST['coil_remark'] ?? '');
    $coil_status = isset($_POST['coil_status']) ? trim($_POST['coil_status']) : 'AC';

    if (empty($coil_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Coil No. is required']);
        exit();
    }

    try {
        $conn->beginTransaction();

        // ----------------------------------------------------
        // 1. ดึงข้อมูลเดิมจาก COILPROD1
        // ----------------------------------------------------
        $stmt_old = $conn->prepare("SELECT * FROM COILPROD1 WHERE COIL_NO = :coil_no");
        $stmt_old->execute([':coil_no' => $coil_no]);
        $old_coil = $stmt_old->fetch(PDO::FETCH_ASSOC);

        if (!$old_coil) {
            throw new Exception("ไม่พบข้อมูล Coil No: " . $coil_no);
        }

        $old_coldmillweight = floatval($old_coil['COIL_COLDMILLWEIGHT'] ?? 0);
        
        // Validation: น้ำหนักแก้ไขใหม่ ต้อง ≤ น้ำหนักเดิม
        if ($new_coldmillweight > $old_coldmillweight) {
            throw new Exception("น้ำหนักใหม่ ({$new_coldmillweight}) ต้องน้อยกว่าหรือเท่ากับน้ำหนักเดิม ({$old_coldmillweight})");
        }

        // คำนวณส่วนต่างน้ำหนักที่เป็น Scrap เพิ่มขึ้นในรอบนี้
        $diff_reject_weight = $old_coldmillweight - $new_coldmillweight;
        $scrap_weight = $diff_reject_weight > 0 ? $diff_reject_weight : 0;

        $scrap_no = "-"; // กำหนดค่าเริ่มต้นกรณีไม่มีการสร้าง Scrap

        // ----------------------------------------------------
        // 2. สร้าง Auto Scrap ใน SCRPPROD1 (ทำเฉพาะเมื่อ Scrap Weight > 0)
        // ----------------------------------------------------
        if ($scrap_weight > 0) {
            $prefix = "S"; 
            $yy = date('y');
            $mm = date('m');
            $dd = date('d');
            
            // ตัดตัวอักษร Line จาก Coil No (เช่น C260814-M-11 ได้ Line = "M")
            $coil_parts = explode('-', $coil_no);
            if (isset($coil_parts[1]) && !empty($coil_parts[1])) {
                $line = strtoupper(trim($coil_parts[1]));
            } else {
                $line = !empty($old_coil['LINE_PROCESS']) ? strtoupper($old_coil['LINE_PROCESS']) : "M";
            }
            
            $search_pattern = $prefix . $yy . $mm . $dd . "-" . $line . "-%";

            // Running Number
            $stmt_seq = $conn->prepare("SELECT COUNT(*) AS cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :pattern");
            $stmt_seq->execute([':pattern' => $search_pattern]);
            $row_seq = $stmt_seq->fetch(PDO::FETCH_ASSOC);
            $next_num = str_pad(($row_seq['cnt'] + 1), 2, '0', STR_PAD_LEFT);

            $scrap_no = "{$prefix}{$yy}{$mm}{$dd}-{$line}-{$next_num}";

            // Insert ลงใน SCRPPROD1
            $sql_scrap = "INSERT INTO SCRPPROD1 (
                            PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, 
                            PROCESS, SCRP_FROM, ALLOY, SCRP_WEIGHT, SCRP_OPERATOR, 
                            SCRP_OPERATEDATE, SCRP_STATUS, SCRP_UPDATE, SCRP_UPDATEDATE
                          ) VALUES (
                            :product_no, GETDATE(), :coil_no, :material_in, :line_process, 
                            :process, :scrp_from, :alloy, :scrp_weight, :scrp_operator, 
                            GETDATE(), 'OP', :scrp_update, GETDATE()
                          )";

            $stmt_scrap = $conn->prepare($sql_scrap);
            $stmt_scrap->execute([
                ':product_no'   => $scrap_no,
                ':coil_no'      => $old_coil['COIL_NO'],
                ':material_in'  => $old_coil['MATERIAL_IN'] ?? '',
                ':line_process' => $line,
                ':process'      => !empty($old_coil['USE_FORPROCESS']) ? $old_coil['USE_FORPROCESS'] : 'CDML',
                ':scrp_from'    => !empty($old_coil['PRODUCT_ID']) ? $old_coil['PRODUCT_ID'] : 'CM',
                ':alloy'        => $old_coil['ALLOY'] ?? '',
                ':scrp_weight'  => $scrap_weight,
                ':scrp_operator'=> $_SESSION['ID'],
                ':scrp_update'  => $_SESSION['ID']
            ]);
        }

        // ----------------------------------------------------
        // 3. Update ใน Table : COILPROD1
        // ----------------------------------------------------
        $sql_update_coil = "UPDATE COILPROD1 SET 
                                COIL_ACTUALWEIGHT = :w1,
                                COIL_COLDMILLWEIGHT = :w2,
                                COIL_BALANCEWEIGHT = :w3,
                                F_THICKNESS = :f_thick,
                                THICKNESS = :thick,
                                WIDTH = :width,
                                ACTUAL_WIDTH = :act_width,
                                COIL_STATUS = :coil_status,
                                COIL_REMARK = :remark
                            WHERE COIL_NO = :coil_no";

        $stmt_u_coil = $conn->prepare($sql_update_coil);
        $stmt_u_coil->execute([
            ':w1'        => $new_coldmillweight,
            ':w2'        => $new_coldmillweight,
            ':w3'        => $new_coldmillweight,
            ':f_thick'   => $new_f_thickness,
            ':thick'     => $new_thickness,
            ':width'     => $new_width,
            ':act_width' => $new_actual_width,
            ':coil_status' => $coil_status,
            ':remark'    => $new_coil_remark,
            ':coil_no'   => $coil_no
        ]);

        // ----------------------------------------------------
        // 4. Update ตาราง CDMLPROD1 (บวกสะสมยอด Reject Weight เพิ่ม)
        // ----------------------------------------------------
        $stmt_cdml_old = $conn->prepare("SELECT CDML_REJECTWEIGHT FROM CDMLPROD1 WHERE COIL_NO = :coil_no");
        $stmt_cdml_old->execute([':coil_no' => $coil_no]);
        $row_cdml_old = $stmt_cdml_old->fetch(PDO::FETCH_ASSOC);

        $exist_reject_weight = floatval($row_cdml_old['CDML_REJECTWEIGHT'] ?? 0);
        $accumulated_reject = $exist_reject_weight + $diff_reject_weight;

        $sql_update_cdml = "UPDATE CDMLPROD1 SET 
                                CDML_ACCEPTWEIGHT = :accept_w,
                                THICKNESS = :thick,
                                CDML_REJECTWEIGHT = :accumulated_reject,
                                CDML_UPDATE = :update_by,
                                CDML_UPDATEDATE = GETDATE()
                            WHERE COIL_NO = :coil_no";

        $stmt_u_cdml = $conn->prepare($sql_update_cdml);
        $stmt_u_cdml->execute([
            ':accept_w'           => $new_coldmillweight,
            ':thick'              => $new_thickness,
            ':accumulated_reject' => $accumulated_reject,
            ':update_by'          => $_SESSION['ID'],
            ':coil_no'            => $coil_no
        ]);

        $conn->commit();

        // แจ้งข้อความตอบกลับตามเงื่อนไขว่ามีการสร้าง Scrap หรือไม่
        $msg_scrap = ($scrap_weight > 0) ? "สร้าง Auto Scrap No: {$scrap_no}" : "ไม่มีส่วนต่างน้ำหนัก (ไม่มีการสร้าง Scrap)";

        echo json_encode([
            'status'   => 'success',
            'scrap_no' => $scrap_no,
            'message'  => "บันทึกข้อมูลเรียบร้อยแล้ว ({$msg_scrap})"
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>