<?php
session_start();
include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type         = $_POST['action_type'] ?? 'UPDATE';
    $product_no          = $_POST['product_no'] ?? '';
    $coil_no             = $_POST['coil_no'] ?? '';
    $job_order           = $_POST['job_order'] ?? '';
    $crsh_produce_weight = floatval($_POST['crsh_produce_weight'] ?? 0);
    $crsh_produce_piece  = floatval($_POST['crsh_produce_piece'] ?? 0);
    $crsh_bottom_weight  = floatval($_POST['crsh_bottom_weight'] ?? 0);
    $cut_width           = floatval($_POST['cut_width'] ?? 0);
    $cut_length          = floatval($_POST['cut_length'] ?? 0);
    $start_date          = $_POST['start_date'] ?? date('Y-m-d');
    $start_time          = $_POST['start_time'] ?? date('H:i:s');
    $end_date            = $_POST['end_date'] ?? date('Y-m-d');
    $end_time            = $_POST['end_time'] ?? date('H:i:s');
    $print_label         = isset($_POST['print_label']) ? $_POST['print_label'] : '0';
    $label_size          = $_POST['label_size'] ?? '29x21';
    $user_update         = $_SESSION['ID'] ?? 'SYSTEM';

    try {
        $conn->beginTransaction();

        if ($action_type === 'DELETE') {
            // 1. คืนค่าน้ำหนักกลับให้ Coil
            if (!empty($coil_no)) {
                $sql_coil = "UPDATE COILPROD1 SET COIL_PRODUCEWEIGHT = COIL_PRODUCEWEIGHT - :p_weight,
                             COIL_BALANCEWEIGHT = COIL_ACTUALWEIGHT - ((COIL_PRODUCEWEIGHT - :p_weight) + COIL_SCRAPWEIGHT + COIL_ADJUSTWEIGHT)
                             WHERE COIL_NO = :cno";
                $stmt_coil = $conn->prepare($sql_coil);
                $stmt_coil->execute([
                    ':p_weight' => $crsh_produce_weight,
                    ':cno'      => $coil_no
                ]);
            }

            // 2. ลบข้อมูลออกจากตาราง CRSHPROD1
            $sql_del = "DELETE FROM CRSHPROD1 WHERE PRODUCT_NO = :pno";
            $stmt_del = $conn->prepare($sql_del);
            $stmt_del->execute([':pno' => $product_no]);

            $conn->commit();
            echo "<script>alert('Delete Record Successfully!'); window.location.href='../maintain_blank_a_k_cut_process_mats.php';</script>";
            exit();

        } else {
            // 1. อัปเดตข้อมูลตาราง CRSHPROD1
            $sql1 = "UPDATE CRSHPROD1 SET 
                        CRSH_PRODUCEWEIGHT = :p_weight, 
                        CRSH_PRODUCEPIECE = :p_piece, 
                        CRSH_BOTTOMWEIGHT = :b_weight, 
                        CUT_WIDTH = :c_width, 
                        CUT_LENGTH = :c_length, 
                        CRSH_STARTDATE = :s_date, 
                        CRSH_STARTTIME = :s_time, 
                        CRSH_ENDDATE = :e_date, 
                        CRSH_ENDTIME = :e_time, 
                        CRSH_LABEL = CASE WHEN :p_chk = '1' THEN ISNULL(CRSH_LABEL, 0) + 1 ELSE CRSH_LABEL END,
                        CRSH_UPDATE = :u_user, 
                        CRSH_UPDATEDATE = GETDATE() 
                     WHERE PRODUCT_NO = :pno";
            
            $stmt1 = $conn->prepare($sql1);
            $stmt1->execute([
                ':p_weight' => $crsh_produce_weight,
                ':p_piece'  => $crsh_produce_piece,
                ':b_weight' => $crsh_bottom_weight,
                ':c_width'  => $cut_width,
                ':c_length' => $cut_length,
                ':s_date'   => $start_date,
                ':s_time'   => $start_time,
                ':e_date'   => $end_date,
                ':e_time'   => $end_time,
                ':p_chk'    => $print_label,
                ':u_user'   => $user_update,
                ':pno'      => $product_no
            ]);

            // 2. อัปเดตตาราง COILPROD1
            if (!empty($coil_no)) {
                $sql2 = "UPDATE COILPROD1 SET COIL_PRODUCEWEIGHT = :p_weight WHERE COIL_NO = :cno";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->execute([
                    ':p_weight' => $crsh_produce_weight,
                    ':cno'      => $coil_no
                ]);
            }

            $conn->commit();

            // 3. ตรวจสอบการพิมพ์ป้าย หากติ๊ก Print Label ให้สั่งเปิด Pop-up ไปยังไฟล์ที่กำหนด
            if ($print_label == '1') {
                $printUrl = "../print_circle_sheet_product_label_mats.php?productno=" . urlencode($product_no) . "&joborder=" . urlencode($job_order) . "&size=" . urlencode($label_size);
                echo "<script>
                    alert('Update Successfully!');
                    window.open('" . $printUrl . "', '_blank', 'width=800,height=600,resizable=yes,scrollbars=yes');
                    window.location.href='../maintain_blank_a_k_cut_process_mats.php';
                </script>";
            } else {
                echo "<script>
                    alert('Update Successfully!');
                    window.location.href='../maintain_blank_a_k_cut_process_mats.php';
                </script>";
            }
            exit();
        }

    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>