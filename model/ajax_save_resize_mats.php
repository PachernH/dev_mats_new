<?php
// model/ajax_save_resize_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
    exit();
}

require_once '../dbcon_mats-new.php'; // ไฟล์เชื่อมต่อ PDO Database ($conn)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// 1. รับค่าและแปลงข้อมูล Input
$product_no       = trim($_POST['product_no'] ?? '');
$product_id       = trim($_POST['product_id'] ?? 'SH');
$product_model    = trim($_POST['product_model'] ?? '');
$resize_width     = (float)($_POST['resize_width'] ?? 0);
$resize_length    = (float)($_POST['resize_length'] ?? 0);
$weight_piece     = (float)($_POST['weight_piece'] ?? 0);
$cut_width        = (float)($_POST['cut_width'] ?? 0);
$cut_length       = (float)($_POST['cut_length'] ?? 0);
$cut_weight_piece = (float)($_POST['cut_weight_piece'] ?? 0);

$accept_weight    = (float)($_POST['accept_weight'] ?? 0);
$accept_piece     = (float)($_POST['accept_piece'] ?? 0);
$reject_weight    = (float)($_POST['reject_weight'] ?? 0);
$reject_piece     = (float)($_POST['reject_piece'] ?? 0);
$bottom_weight    = (float)($_POST['bottom_weight'] ?? 0);

$start_date       = trim($_POST['start_date'] ?? date('Y-m-d'));
$start_time       = trim($_POST['start_time'] ?? date('H:i:s'));
$end_date         = trim($_POST['end_date'] ?? date('Y-m-d'));
$end_time         = trim($_POST['end_time'] ?? date('H:i:s'));

$user_operator    = $_SESSION['ID'] ?? 'SYSTEM';

if (empty($product_no)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุ Product No']);
    exit();
}

try {
    // 2. ตรวจสอบข้อมูลก่อนทำรายการ (Validation Checks เหมือน f3)

    // Check 2.1: ห้ามแก้ไขหากมีการบันทึกข้อมูลใน Combine แล้ว (CHECK-CB)
    $stmt_cb = $conn->prepare("SELECT PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_cb->execute([':pno' => $product_no]);
    if ($stmt_cb->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่อนุญาตให้แก้ไข เนื่องจากมีการทำข้อมูลในกระบวนการ Combine แล้ว']);
        exit();
    }

    // Check 2.2: ดึงข้อมูล CRSHPROD1 เพื่อเช็คสถานะ
    $stmt_crsh = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_crsh->execute([':pno' => $product_no]);
    $crsh_data = $stmt_crsh->fetch(PDO::FETCH_ASSOC);

    if (!$crsh_data) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล Product No ในระบบ']);
        exit();
    }

    if (trim($crsh_data['CRSH_STATUS'] ?? '') !== 'OP') {
        echo json_encode(['status' => 'error', 'message' => 'สถานะชิ้นงานต้องเป็น OP เท่านั้นจึงจะแก้ไขได้']);
        exit();
    }

    if (!empty(trim($crsh_data['JOB_ORDER'] ?? ''))) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถแก้ไขได้ เนื่องจากมี Job Order ผูกอยู่']);
        exit();
    }

    // Check 2.3: ตรวจสอบข้อจำกัดขนาดและน้ำหนัก (Dimension & Weight Checks)
    if ($resize_width > 1600) {
        echo json_encode(['status' => 'error', 'message' => 'Resize Width ห้ามเกิน 1,600 mm']);
        exit();
    }

    if ($product_id === 'SH' && $resize_length > 4000) {
        echo json_encode(['status' => 'error', 'message' => 'Resize Length สำหรับ SHEET ห้ามเกิน 4,000 mm']);
        exit();
    }

    if ($product_id === 'CC' && $resize_length > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Resize Length สำหรับ CIRCLE ต้องเป็น 0']);
        exit();
    }

    if ($bottom_weight >= 200) {
        echo json_encode(['status' => 'error', 'message' => 'Bottom Weight ต้องน้อยกว่า 200 kg']);
        exit();
    }

    $dbl_actual_piece = (float)($crsh_data['CRSH_ACTUALPIECE'] ?? 0);
    if ($accept_piece > $dbl_actual_piece || $accept_piece <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'จำนวน Accept Piece ไม่ถูกต้อง (ต้องมากกว่า 0 และไม่เกิน ' . $dbl_actual_piece . ' Pcs)']);
        exit();
    }

    // 3. ตรวจสอบว่าชิ้นงานนี้เคยบันทึกใน RESZPROD1 หรือยัง (แยกแยะ ADD / UPDATE)
    $stmt_resz_old = $conn->prepare("SELECT * FROM RESZPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_resz_old->execute([':pno' => $product_no]);
    $resz_old_data = $stmt_resz_old->fetch(PDO::FETCH_ASSOC);

    $is_new_record = !$resz_old_data; // true = ADD (INSERT), false = UPDATE
    $dbl_old_accept_piece = $is_new_record ? $dbl_actual_piece : (float)($resz_old_data['RESZ_ACCEPTPIECE'] ?? 0);

    // คำนวณผลต่างชิ้นงานเพื่อปรับปรุงค่าน้ำหนักย้อนกลับ
    $var_accept_piece = round($accept_piece - $dbl_old_accept_piece);

    // ดึงค่าน้ำหนักและจำนวนชิ้นเดิมจากกระบวนการผลิตต่างๆ
    $dbl_produce_weight      = (float)($crsh_data['CRSH_PRODUCEWEIGHT'] ?? 0);
    $dbl_produce_piece       = (float)($crsh_data['CRSH_PRODUCEPIECE'] ?? 0);
    $dbl_stretcher_weight    = (float)($crsh_data['CRSH_STRETCHERWEIGHT'] ?? 0);
    $dbl_stretcher_piece     = (float)($crsh_data['CRSH_STRETCHERPIECE'] ?? 0);
    $dbl_cutsheet_weight     = (float)($crsh_data['CRSH_CUTSHEETWEIGHT'] ?? 0);
    $dbl_cutsheet_piece      = (float)($crsh_data['CRSH_CUTSHEETPIECE'] ?? 0);
    $dbl_shear_weight        = (float)($crsh_data['CRSH_SHEARWEIGHT'] ?? 0);
    $dbl_shear_piece         = (float)($crsh_data['CRSH_SHEARPIECE'] ?? 0);
    $dbl_punch_weight        = (float)($crsh_data['CRSH_PUNCHWEIGHT'] ?? 0);
    $dbl_punch_piece         = (float)($crsh_data['CRSH_PUNCHPIECE'] ?? 0);
    $dbl_batchanneal_weight  = (float)($crsh_data['CRSH_BATCHANNEALWEIGHT'] ?? 0);
    $dbl_batchanneal_piece   = (float)($crsh_data['CRSH_BATCHANNEALPIECE'] ?? 0);
    $dbl_transfer_weight     = (float)($crsh_data['CRSH_TRANSFERWEIGHT'] ?? 0);
    $dbl_transfer_piece      = (float)($crsh_data['CRSH_TRANSFERPIECE'] ?? 0);
    $dbl_anneal_weight       = (float)($crsh_data['CRSH_ANNEALWEIGHT'] ?? 0);
    $dbl_anneal_piece        = (float)($crsh_data['CRSH_ANNEALPIECE'] ?? 0);
    $dbl_sort_weight         = (float)($crsh_data['CRSH_SORTWEIGHT'] ?? 0);
    $dbl_sort_piece          = (float)($crsh_data['CRSH_SORTPIECE'] ?? 0);

    // คำนวณค่าน้ำหนักและจำนวนชิ้นงานใหม่
    $actual_piece_new  = $dbl_actual_piece + $var_accept_piece;
    $actual_weight_new = round($actual_piece_new * $weight_piece);

    $produce_piece_new  = $dbl_produce_piece + $var_accept_piece;
    $produce_weight_new = round($produce_piece_new * ($cut_weight_piece > 0 ? $cut_weight_piece : $weight_piece));

    $stretcher_weight_new   = $dbl_stretcher_weight > 0 ? round(($dbl_stretcher_piece + $var_accept_piece) * ($cut_weight_piece > 0 ? $cut_weight_piece : $weight_piece)) : 0;
    $stretcher_piece_new    = $dbl_stretcher_weight > 0 ? ($dbl_stretcher_piece + $var_accept_piece) : 0;

    $cutsheet_weight_new    = $dbl_cutsheet_weight > 0 ? round(($dbl_cutsheet_piece + $var_accept_piece) * $weight_piece) : 0;
    $cutsheet_piece_new     = $dbl_cutsheet_weight > 0 ? ($dbl_cutsheet_piece + $var_accept_piece) : 0;

    $shear_weight_new       = $dbl_shear_weight > 0 ? round(($dbl_shear_piece + $var_accept_piece) * $weight_piece) : 0;
    $shear_piece_new        = $dbl_shear_weight > 0 ? ($dbl_shear_piece + $var_accept_piece) : 0;

    $punch_weight_new       = $dbl_punch_weight > 0 ? round(($dbl_punch_piece + $var_accept_piece) * $weight_piece) : 0;
    $punch_piece_new        = $dbl_punch_weight > 0 ? ($dbl_punch_piece + $var_accept_piece) : 0;

    $batchanneal_weight_new = $dbl_batchanneal_weight > 0 ? round(($dbl_batchanneal_piece + $var_accept_piece) * $weight_piece) : 0;
    $batchanneal_piece_new  = $dbl_batchanneal_weight > 0 ? ($dbl_batchanneal_piece + $var_accept_piece) : 0;

    $transfer_weight_new   = $dbl_transfer_weight > 0 ? round(($dbl_transfer_piece + $var_accept_piece) * $weight_piece) : 0;
    $transfer_piece_new    = $dbl_transfer_weight > 0 ? ($dbl_transfer_piece + $var_accept_piece) : 0;

    $anneal_weight_new     = $dbl_anneal_weight > 0 ? round(($dbl_anneal_piece + $var_accept_piece) * $weight_piece) : 0;
    $anneal_piece_new      = $dbl_anneal_weight > 0 ? ($dbl_anneal_piece + $var_accept_piece) : 0;

    $sort_weight_new       = $dbl_sort_weight > 0 ? round(($dbl_sort_piece + $var_accept_piece) * $weight_piece) : 0;
    $sort_piece_new        = $dbl_sort_piece > 0 ? ($dbl_sort_piece + $var_accept_piece) : 0;

    // 4. เริ่ม Transaction (ADO Transaction)
    $conn->beginTransaction();

    // Step 4.1: บันทึกเข้า RESZPROD1 (ADD หรือ UPDATE)
    if ($is_new_record) {
        $sql_resz = "INSERT INTO RESZPROD1 (
                        PRODUCT_NO, RESZ_STARTDATE, RESZ_STARTTIME, RESZ_ENDDATE, RESZ_ENDTIME,
                        RESZ_ACCEPTWEIGHT, RESZ_ACCEPTPIECE, RESZ_REJECTWEIGHT, RESZ_REJECTPIECE,
                        RESZ_OPERATOR1, RESZ_OPERATEDATE
                     ) VALUES (
                        :pno, :st_date, :st_time, :en_date, :en_time,
                        :acc_wt, :acc_pc, :rej_wt, :rej_pc,
                        :op1, GETDATE()
                     )";
        $stmt_resz = $conn->prepare($sql_resz);
        $stmt_resz->execute([
            ':pno'     => $product_no,
            ':st_date' => $start_date,
            ':st_time' => $start_time,
            ':en_date' => $end_date,
            ':en_time' => $end_time,
            ':acc_wt'  => $accept_weight,
            ':acc_pc'  => $accept_piece,
            ':rej_wt'  => $reject_weight,
            ':rej_pc'  => $reject_piece,
            ':op1'     => $user_operator
        ]);
    } else {
        $sql_resz = "UPDATE RESZPROD1 SET 
                        RESZ_STARTDATE = :st_date, RESZ_STARTTIME = :st_time, 
                        RESZ_ENDDATE = :en_date, RESZ_ENDTIME = :en_time, 
                        RESZ_ACCEPTWEIGHT = :acc_wt, RESZ_ACCEPTPIECE = :acc_pc, 
                        RESZ_REJECTWEIGHT = :rej_wt, RESZ_REJECTPIECE = :rej_pc, 
                        RESZ_UPDATE = :op1, RESZ_UPDATEDATE = GETDATE() 
                     WHERE PRODUCT_NO = :pno";
        $stmt_resz = $conn->prepare($sql_resz);
        $stmt_resz->execute([
            ':st_date' => $start_date,
            ':st_time' => $start_time,
            ':en_date' => $end_date,
            ':en_time' => $end_time,
            ':acc_wt'  => $accept_weight,
            ':acc_pc'  => $accept_piece,
            ':rej_wt'  => $reject_weight,
            ':rej_pc'  => $reject_piece,
            ':op1'     => $user_operator,
            ':pno'     => $product_no
        ]);
    }

    // Step 4.2: อัปเดตข้อมูลหลักใน CRSHPROD1
    $sql_crsh_update = "UPDATE CRSHPROD1 SET 
                            PRODUCT_ID = :pid, PRODUCT_MODEL = :pmodel, WIDTH = :rw, LENGTH = :rl, 
                            WEIGHT_PIECE = :wp, CUT_WIDTH = :cw, CUT_LENGTH = :cl, CUT_WEIGHTPIECE = :cwp, 
                            CRSH_ACTUALWEIGHT = :act_wt, CRSH_ACTUALPIECE = :act_pc, 
                            CRSH_PRODUCEWEIGHT = :prd_wt, CRSH_PRODUCEPIECE = :prd_pc, 
                            CRSH_STRETCHERWEIGHT = :stc_wt, CRSH_STRETCHERPIECE = :stc_pc, 
                            CRSH_CUTSHEETWEIGHT = :cts_wt, CRSH_CUTSHEETPIECE = :cts_pc, 
                            CRSH_SHEARWEIGHT = :shr_wt, CRSH_SHEARPIECE = :shr_pc, 
                            CRSH_PUNCHWEIGHT = :pch_wt, CRSH_PUNCHPIECE = :pch_pc, 
                            CRSH_BATCHANNEALWEIGHT = :ba_wt, CRSH_BATCHANNEALPIECE = :ba_pc, 
                            CRSH_TRANSFERWEIGHT = :tw_wt, CRSH_TRANSFERPIECE = :tw_pc, 
                            CRSH_ANNEALWEIGHT = :an_wt, CRSH_ANNEALPIECE = :an_pc, 
                            CRSH_SORTWEIGHT = :sr_wt, CRSH_SORTPIECE = :sr_pc, 
                            CRSH_BOTTOMWEIGHT = :bot_wt, CRSH_UPDATE = :op1, CRSH_UPDATEDATE = GETDATE() 
                        WHERE PRODUCT_NO = :pno";

    $stmt_crsh_up = $conn->prepare($sql_crsh_update);
    $stmt_crsh_up->execute([
        ':pid'    => $product_id,
        ':pmodel' => $product_model,
        ':rw'     => $resize_width,
        ':rl'     => $resize_length,
        ':wp'     => $weight_piece,
        ':cw'     => $cut_width,
        ':cl'     => $cut_length,
        ':cwp'    => $cut_weight_piece,
        ':act_wt' => $actual_weight_new,
        ':act_pc' => $actual_piece_new,
        ':prd_wt' => $produce_weight_new,
        ':prd_pc' => $produce_piece_new,
        ':stc_wt' => $stretcher_weight_new,
        ':stc_pc' => $stretcher_piece_new,
        ':cts_wt' => $cutsheet_weight_new,
        ':cts_pc' => $cutsheet_piece_new,
        ':shr_wt' => $shear_weight_new,
        ':shr_pc' => $shear_piece_new,
        ':pch_wt' => $punch_weight_new,
        ':pch_pc' => $punch_piece_new,
        ':ba_wt'  => $batchanneal_weight_new,
        ':ba_pc'  => $batchanneal_piece_new,
        ':tw_wt'  => $transfer_weight_new,
        ':tw_pc'  => $transfer_piece_new,
        ':an_wt'  => $anneal_weight_new,
        ':an_pc'  => $anneal_piece_new,
        ':sr_wt'  => $sort_weight_new,
        ':sr_pc'  => $sort_piece_new,
        ':bot_wt' => $bottom_weight,
        ':op1'    => $user_operator,
        ':pno'    => $product_no
    ]);

    // Step 4.3: อัปเดตตารางประจำกระบวนการอื่นๆ ย้อนกลับ
    if ($stretcher_weight_new > 0) {
        $stmt_stch = $conn->prepare("UPDATE STCHPROD1 SET STCH_ACCEPTWEIGHT = :w, STCH_ACCEPTPIECE = :p, STCH_UPDATE = :op, STCH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_stch->execute([':w' => $stretcher_weight_new, ':p' => $stretcher_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($cutsheet_weight_new > 0) {
        $stmt_ctsh = $conn->prepare("UPDATE CTSHPROD1 SET CTSH_ACCEPTWEIGHT = :w, CTSH_ACCEPTPIECE = :p, CTSH_UPDATE = :op, CTSH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_ctsh->execute([':w' => $cutsheet_weight_new, ':p' => $cutsheet_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($shear_weight_new > 0) {
        $stmt_shrs = $conn->prepare("UPDATE SHRSPROD1 SET SHRS_ACCEPTWEIGHT = :w, SHRS_ACCEPTPIECE = :p, SHRS_UPDATE = :op, SHRS_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_shrs->execute([':w' => $shear_weight_new, ':p' => $shear_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($punch_weight_new > 0) {
        $stmt_pchl = $conn->prepare("UPDATE PCHLPROD1 SET PCHL_ACCEPTWEIGHT = :w, PCHL_ACCEPTPIECE = :p, PCHL_UPDATE = :op, PCHL_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_pchl->execute([':w' => $punch_weight_new, ':p' => $punch_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($batchanneal_weight_new > 0) {
        $stmt_btch0 = $conn->prepare("UPDATE BTCHPROD0 SET BTCH_INPUTWEIGHT = :w, BTCH_INPUTPIECE = :p, BTCH_UPDATE = :op, BTCH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_btch0->execute([':w' => $batchanneal_weight_new, ':p' => $batchanneal_piece_new, ':op' => $user_operator, ':pno' => $product_no]);

        $stmt_btch1 = $conn->prepare("UPDATE BTCHPROD1 SET BTCH_ACCEPTWEIGHT = :w, BTCH_ACCEPTPIECE = :p, BTCH_UPDATE = :op, BTCH_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_btch1->execute([':w' => $batchanneal_weight_new, ':p' => $batchanneal_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($transfer_weight_new > 0) {
        $stmt_tptp = $conn->prepare("UPDATE TPTPPROD1 SET TPTP_ACCEPTWEIGHT = :w, TPTP_ACCEPTPIECE = :p, TPTP_UPDATE = :op, TPTP_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_tptp->execute([':w' => $transfer_weight_new, ':p' => $transfer_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($anneal_weight_new > 0) {
        $stmt_annl = $conn->prepare("UPDATE ANNLPROD1 SET ANNL_ACCEPTWEIGHT = :w, ANNL_ACCEPTPIECE = :p, ANNL_UPDATE = :op, ANNL_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_annl->execute([':w' => $anneal_weight_new, ':p' => $anneal_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    if ($sort_weight_new > 0) {
        $stmt_sort = $conn->prepare("UPDATE SORTPROD1 SET SORT_ACCEPTWEIGHT = :w, SORT_ACCEPTPIECE = :p, SORT_UPDATE = :op, SORT_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = :pno");
        $stmt_sort->execute([':w' => $sort_weight_new, ':p' => $sort_piece_new, ':op' => $user_operator, ':pno' => $product_no]);
    }

    // ยืนยันการบันทึก Transaction
    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูล Re-Size เรียบร้อยแล้ว']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()]);
}