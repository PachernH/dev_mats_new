<?php
// ปิด Output Buffering เพื่อป้องกัน Warning ปะปนกับ JSON Response
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

// ดึงค่า Session ผู้ใช้งาน และ Line Process (ล็อกเป็น Line 5 สำหรับ XY Circle Shear)
$iduser_func = isset($_SESSION['ID']) ? trim($_SESSION['ID']) : '';
$line_func   = isset($_SESSION['LINE']) ? strtoupper(trim($_SESSION['LINE'])) : '5';

if (empty($iduser_func)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request Method']);
    exit();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once "../dbcon_mats-new.php";

// รับค่าจาก Form POST
$job_order             = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$coil_no               = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$produce_piece         = isset($_POST['produce_piece']) ? (float)$_POST['produce_piece'] : 0;
$produce_weight        = isset($_POST['produce_weight']) ? (float)$_POST['produce_weight'] : 0;
$pallet_weight         = isset($_POST['pallet_weight']) ? (float)$_POST['pallet_weight'] : 0;
$skeleton_scrap_weight = isset($_POST['skeleton_scrap_weight']) ? (float)$_POST['skeleton_scrap_weight'] : 0;
$start_datetime        = isset($_POST['start_datetime']) ? $_POST['start_datetime'] : '';
$end_datetime          = isset($_POST['end_datetime']) ? $_POST['end_datetime'] : '';

if (empty($job_order) || empty($coil_no)) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน กรุณาระบุ Job Order และ Coil No.']);
    exit();
}

// 1. Validation Check: เวลาเริ่มต้นและสิ้นสุด
if (!empty($start_datetime) && !empty($end_datetime)) {
    $time_start_sec = strtotime($start_datetime);
    $time_end_sec   = strtotime($end_datetime);
    
    if ($time_end_sec <= $time_start_sec) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลเวลาไม่ถูกต้อง เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้นผลิต']);
        exit();
    }
    $produce_time_min = round(($time_end_sec - $time_start_sec) / 60);
} else {
    $produce_time_min = 0;
}

// 2. Validation Check: น้ำหนักก้นคอยล์ (Bottom Weight) ห้ามเกิน 200 kg ตาม f7[cite: 1]
if ($pallet_weight > 200) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'น้ำหนักก้นคอยล์ (Bottom Weight) ต้องไม่เกิน 200 kg.']);
    exit();
}

try {
    $conn->beginTransaction();

    $start_date = !empty($start_datetime) ? date('Y-m-d', strtotime($start_datetime)) : date('Y-m-d');
    $start_time = !empty($start_datetime) ? date('H:i:s', strtotime($start_datetime)) : date('H:i:s');
    $end_date   = !empty($end_datetime) ? date('Y-m-d', strtotime($end_datetime)) : date('Y-m-d');
    $end_time   = !empty($end_datetime) ? date('H:i:s', strtotime($end_datetime)) : date('H:i:s');
    $now_time   = date('Y-m-d H:i:s');

    // 3. ดึงข้อมูล Job Order
    $sql_job = "SELECT 
                    j.PRODUCT_ID, j.PRODUCT_MODEL, j.AREA_SIZE, j.JOB_WORKPROCESS,
                    j.ALLOY, j.TEMPER, j.GRADE, j.SURFACE_GRADE, j.METALLURGICAL_GRADE, j.T5_TEMPERATURE,
                    j.THICKNESS, j.WIDTH, j.LENGTH, j.WEIGHT_PIECE, j.CUT_WIDTH, j.CUT_LENGTH, j.CUT_WEIGHTPIECE,
                    j.JOB_RELEASEWEIGHT, j.JOB_PRODUCEWEIGHT, j.JOB_MAXTOLERANCE,
                    j.SALEORDER_NO, j.SALEORDER_ITEM
                FROM JOBORDER1 j
                WHERE j.JOB_ORDER = :job_order";
    
    $stmt_job = $conn->prepare($sql_job);
    $stmt_job->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job->execute();
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("ไม่พบข้อมูล Job Order: " . $job_order);
    }

    $product_id     = !empty($job['PRODUCT_ID']) ? trim($job['PRODUCT_ID']) : 'CC';
    $work_process   = !empty($job['JOB_WORKPROCESS']) ? trim($job['JOB_WORKPROCESS']) : '';
    $saleorder_no   = !empty($job['SALEORDER_NO']) ? trim($job['SALEORDER_NO']) : '';
    $saleorder_item = !empty($job['SALEORDER_ITEM']) ? trim($job['SALEORDER_ITEM']) : '';

    // เลือก Weight per Piece ตาม Logic f7[cite: 1]
    $pos_sh = strpos($work_process, 'SH>');
    $pos_sc = strpos($work_process, 'SC>');
    $pos_ct = strpos($work_process, 'CT>');

    if (($pos_sh !== false && $pos_sh >= 2) || 
        ($pos_sc !== false && $pos_sc >= 2) || 
        ($pos_ct !== false && $pos_ct >= 2)) {
        $wt_per_piece = (float)($job['CUT_WEIGHTPIECE'] ?? 0);
    } else {
        $wt_per_piece = (float)($job['WEIGHT_PIECE'] ?? 0);
    }

    // คำนวณชิ้นหรือน้ำหนักให้อัตโนมัติหากเป็น 0
    if ($product_id === 'SH') {
        if ($produce_piece <= 0) { throw new Exception("กรุณาระบุจำนวนชิ้นงานผลิต (Pcs)"); }
        if ($produce_weight <= 0 && $wt_per_piece > 0) { $produce_weight = round($produce_piece * $wt_per_piece); }
    } else {
        if ($produce_weight <= 0) { throw new Exception("กรุณาระบุน้ำหนักผลิต (kg.)"); }
        if ($produce_piece <= 0 && $wt_per_piece > 0) { $produce_piece = round($produce_weight / $wt_per_piece); }
    }

    // 4. Hard Block หากผลิตเกิน Max Target Weight (+Tolerance %)
    $release_wt     = (float)($job['JOB_RELEASEWEIGHT'] ?? 0);
    $curr_prod_wt   = (float)($job['JOB_PRODUCEWEIGHT'] ?? 0);
    $max_tol_pct    = (float)($job['JOB_MAXTOLERANCE'] ?? 0);
    $max_allowed_wt = $release_wt + (($release_wt * $max_tol_pct) / 100);

    if ($max_allowed_wt > 0 && ($curr_prod_wt + $produce_weight) > $max_allowed_wt) {
        throw new Exception("ยอดผลิตสะสมรวม (" . number_format($curr_prod_wt + $produce_weight, 2) . " kg.) เกินพิกัดสูงสุดที่อนุญาต (" . number_format($max_allowed_wt, 2) . " kg. [+" . $max_tol_pct . "%])");
    }

    // 5. ดึงข้อมูล Coil
    $sql_coil = "SELECT 
                    ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE,
                    THICKNESS, WIDTH, COIL_ACTUALWEIGHT, COIL_PRODUCEWEIGHT, COIL_BALANCEWEIGHT, LOCATION_ID, MATERIAL_IN
                 FROM COILPROD1
                 WHERE COIL_NO = :coil_no";
    
    $stmt_coil = $conn->prepare($sql_coil);
    $stmt_coil->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_coil->execute();
    $coil = $stmt_coil->fetch(PDO::FETCH_ASSOC);

    if (!$coil) {
        throw new Exception("ไม่พบข้อมูล Coil: " . $coil_no);
    }

    $coil_actual_wt  = (float)($coil['COIL_ACTUALWEIGHT'] ?? 0);
    $coil_prod_wt    = (float)($coil['COIL_PRODUCEWEIGHT'] ?? 0);
    $curr_balance_wt = (float)($coil['COIL_BALANCEWEIGHT'] ?? 0);
    $material_in     = $coil['MATERIAL_IN'] ?? '';
    $location_id     = !empty($coil['LOCATION_ID']) ? $coil['LOCATION_ID'] : 'NONE';

    // 6. ตรวจสอบ Coil Yield Limit (85% หรือ 95%) ตาม f7[cite: 1]
    $total_coil_prod_wt = $coil_prod_wt + $produce_weight;
    if ($product_id === 'SH' || strpos($work_process, 'CL>') === 0) {
        if ($coil_actual_wt > 0 && $total_coil_prod_wt > ($coil_actual_wt * 0.95)) {
            throw new Exception("น้ำหนักผลิตรวมสะสมของ Coil (" . number_format($total_coil_prod_wt, 2) . " kg.) เกินขีดจำกัด 95% ของน้ำหนักคอยล์จริง (" . number_format($coil_actual_wt * 0.95, 2) . " kg.)");
        }
    } else {
        if ($coil_actual_wt > 0 && $total_coil_prod_wt > ($coil_actual_wt * 0.85)) {
            throw new Exception("น้ำหนักผลิตรวมสะสมของ Coil (" . number_format($total_coil_prod_wt, 2) . " kg.) เกินขีดจำกัด 85% ของน้ำหนักคอยล์จริง (" . number_format($coil_actual_wt * 0.85, 2) . " kg.)");
        }
    }

    $total_used_weight = $produce_weight + $skeleton_scrap_weight;
    $new_balance_wt    = $curr_balance_wt - $total_used_weight;

    if ($new_balance_wt < 0) {
        throw new Exception("น้ำหนักผลิตรวม (" . number_format($total_used_weight, 2) . " kg.) เกินกว่าน้ำหนักคงเหลือของ Coil (" . number_format($curr_balance_wt, 2) . " kg.)");
    }

    // คำนวณ Next Process ตาม f7 สำหรับ XY Circle Shear Process (XC>...)[cite: 1]
    $next_process = 'SC';
    if (strpos($work_process, 'XC>') !== false) {
        $parts = explode('XC>', $work_process);
        if (isset($parts[1]) && !empty($parts[1])) { $next_process = substr($parts[1], 0, 2); }
    }

    // 7. รันเลข PRODUCT_NO ตัวใหม่ (รูปแบบ PYYMMDD-5-XX)
    $prefix = "P" . date('ymd') . "-" . $line_func . "-";
    $sql_run = "SELECT COUNT(*) as run_cnt FROM CRSHPROD1 WHERE PRODUCT_NO LIKE :prefix";
    $stmt_run = $conn->prepare($sql_run);
    $search_prefix = $prefix . "%";
    $stmt_run->bindParam(':prefix', $search_prefix, PDO::PARAM_STR);
    $stmt_run->execute();
    $row_run = $stmt_run->fetch(PDO::FETCH_ASSOC);
    $next_no = ((int)($row_run['run_cnt'] ?? 0)) + 1;
    $product_no = $prefix . sprintf("%02d", $next_no);

    // 8. บันทึกข้อมูลลงในตาราง CRSHPROD1[cite: 1, 7]
    $sql_crsh = "INSERT INTO CRSHPROD1 (
                    PRODUCT_NO, COIL_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, MOULDSETUP_NO,
                    MAINTENANCE_NO1, DIESET_NO, MAINTENANCE_NO2, DIE_NO, MAINTENANCE_NO3, PUNCH_NO1, MAINTENANCE_NO4,
                    PUNCH_NO2, CLEARANCE, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE, LINE_PROCESS, ORG_LINEPROCESS,
                    CRSH_WORKPROCESS, CRSH_NEXTPROCESS, LOCATION_ID, F_ALLOY, F_TEMPER, F_WIDTH, F_LENGTH, ALLOY, TEMPER,
                    GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, CRSH_UOMDIMENSION,
                    CRSH_UOMWEIGHT, WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, XYB_SPEED, PC_MINUTE, PRODUCE_TIME,
                    CRSH_RETURNWEIGHT, CRSH_COMBINEWEIGHT, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT,
                    CRSH_PRODUCEPIECE, CRSH_STRETCHERWEIGHT, CRSH_CUTSHEETWEIGHT, CRSH_SHEARWEIGHT, CRSH_PUNCHWEIGHT,
                    CRSH_BATCHANNEALWEIGHT, CRSH_ANNEALWEIGHT, CRSH_LEVELERWEIGHT, CRSH_SORTWEIGHT, CRSH_PACKWEIGHT,
                    CRSH_TOPWEIGHT, CRSH_BOTTOMWEIGHT, CRSH_DIMENSIONWIDTH, CRSH_DIMENSIONLENGTH, CRSH_DIMENSIONHIGH,
                    CRSH_STARTDATE, CRSH_STARTTIME, CRSH_ENDDATE, CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1, CRSH_OPERATEDATE, CRSH_STATUS
                ) VALUES (
                    :product_no, :coil_no, :job_order, :saleorder_no, :saleorder_item, :material_in, '',
                    '', '', '', '', '', '', '',
                    '', 0, :product_id, :product_model, :area_size, :line_process, :org_lineprocess,
                    :work_process, :next_process, :location_id, :f_alloy, :f_temper, :f_width, :f_length, :alloy, :temper,
                    :grade, :sg, :mg, :t5_temp, :thickness, :width, :length, 'mm.',
                    'kg.', :weight_piece, :cut_width, :cut_length, :cut_weightpiece, 0, 0, :produce_time,
                    0, 0, :actual_weight, :actual_piece, :produce_weight,
                    :produce_piece, 0, 0, 0, 0,
                    0, 0, 0, 0, 0,
                    0, :bottom_weight, 0, 0, 0,
                    :start_date, :start_time, :end_date, :end_time, 0, :operator1, :operatedate, 'OP'
                )";

    $stmt_crsh = $conn->prepare($sql_crsh);
    $stmt_crsh->bindValue(':product_no', $product_no, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':saleorder_no', $saleorder_no, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':saleorder_item', $saleorder_item, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':material_in', $material_in, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':product_id', $product_id, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':product_model', $job['PRODUCT_MODEL'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':area_size', (float)($job['AREA_SIZE'] ?? 0));
    $stmt_crsh->bindValue(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':org_lineprocess', $line_func, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':work_process', $work_process, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':next_process', $next_process, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':location_id', $location_id, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_alloy', $coil['ALLOY'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_temper', $coil['TEMPER'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_width', (float)($coil['WIDTH'] ?? 0));
    $stmt_crsh->bindValue(':f_length', (float)($job['LENGTH'] ?? 0));
    $stmt_crsh->bindValue(':alloy', $coil['ALLOY'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':temper', $coil['TEMPER'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':grade', $coil['GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':sg', $coil['SURFACE_GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':mg', $coil['METALLURGICAL_GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':t5_temp', $coil['T5_TEMPERATURE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':thickness', (float)($coil['THICKNESS'] ?? 0));
    $stmt_crsh->bindValue(':width', (float)($job['WIDTH'] ?? 0));
    $stmt_crsh->bindValue(':length', (float)($job['LENGTH'] ?? 0));
    $stmt_crsh->bindValue(':weight_piece', (float)($job['WEIGHT_PIECE'] ?? 0));
    $stmt_crsh->bindValue(':cut_width', (float)($job['CUT_WIDTH'] ?? 0));
    $stmt_crsh->bindValue(':cut_length', (float)($job['CUT_LENGTH'] ?? 0));
    $stmt_crsh->bindValue(':cut_weightpiece', (float)($job['CUT_WEIGHTPIECE'] ?? 0));
    $stmt_crsh->bindValue(':produce_time', $produce_time_min);
    $stmt_crsh->bindValue(':actual_weight', $produce_weight);
    $stmt_crsh->bindValue(':actual_piece', $produce_piece);
    $stmt_crsh->bindValue(':produce_weight', $produce_weight);
    $stmt_crsh->bindValue(':produce_piece', $produce_piece);
    $stmt_crsh->bindValue(':bottom_weight', $pallet_weight);
    $stmt_crsh->bindValue(':start_date', $start_date);
    $stmt_crsh->bindValue(':start_time', $start_time);
    $stmt_crsh->bindValue(':end_date', $end_date);
    $stmt_crsh->bindValue(':end_time', $end_time);
    $stmt_crsh->bindValue(':operator1', $iduser_func, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':operatedate', $now_time);
    $stmt_crsh->execute();

    // 9. บันทึกเศษโครงปั๊ม (Skeleton Scrap) ลงตาราง SCRPPROD1 หากมีน้ำหนัก[cite: 7]
    if ($skeleton_scrap_weight > 0) {
        $scrp_sk_prefix = "S" . date('ymd') . "-" . $line_func . "-";
        $sql_sk_run = "SELECT COUNT(*) as run_cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :prefix";
        $stmt_sk_run = $conn->prepare($sql_sk_run);
        $search_sk_prefix = $scrp_sk_prefix . "%";
        $stmt_sk_run->bindParam(':prefix', $search_sk_prefix, PDO::PARAM_STR);
        $stmt_sk_run->execute();
        $row_sk_run = $stmt_sk_run->fetch(PDO::FETCH_ASSOC);
        $next_sk_no = ((int)($row_sk_run['run_cnt'] ?? 0)) + 1;
        $scrap_sk_no = $scrp_sk_prefix . sprintf("%02d", $next_sk_no);

        $sql_scrp_sk = "INSERT INTO SCRPPROD1 (
                            PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM,
                            LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR,
                            SCRP_OPERATEDATE, SCRP_STATUS
                        ) VALUES (
                            :product_no, :scrp_date, :coil_no, :material_in, :line_process, 'XC01', 'SK',
                            :location_id, :alloy, 0, :scrp_weight, 'SKELETON SCRAP FROM XY CIRCLE SHEAR', 0, :operator,
                            :operatedate, 'OP'
                        )";
        $stmt_scrp_sk = $conn->prepare($sql_scrp_sk);
        $stmt_scrp_sk->bindParam(':product_no', $scrap_sk_no, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':scrp_date', $end_date);
        $stmt_scrp_sk->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':material_in', $material_in, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':line_process', $line_func, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':location_id', $location_id, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':alloy', $coil['ALLOY'], PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':scrp_weight', $skeleton_scrap_weight);
        $stmt_scrp_sk->bindParam(':operator', $iduser_func, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':operatedate', $now_time);
        $stmt_scrp_sk->execute();
    }

    // 10. อัปเดตยอดสะสมไปยัง JOBORDER1 และ CSTMORDR2[cite: 7]
    $sql_job_up = "UPDATE JOBORDER1 SET 
                        JOB_PRODUCEWEIGHT = ISNULL(JOB_PRODUCEWEIGHT, 0) + :produce_weight,
                        JOB_PRODUCEPIECE  = ISNULL(JOB_PRODUCEPIECE, 0) + :produce_piece
                    WHERE JOB_ORDER = :job_order";
    $stmt_job_up = $conn->prepare($sql_job_up);
    $stmt_job_up->bindParam(':produce_weight', $produce_weight);
    $stmt_job_up->bindParam(':produce_piece', $produce_piece);
    $stmt_job_up->bindParam(':job_order', $job_order, PDO::PARAM_STR);
    $stmt_job_up->execute();

    if (!empty($saleorder_no) && !empty($saleorder_item)) {
        $sql_ctm = "UPDATE CSTMORDR2 SET 
                        CTM2_PRODUCEWEIGHT = ISNULL(CTM2_PRODUCEWEIGHT, 0) + :produce_weight,
                        CTM2_PRODUCEPIECE  = ISNULL(CTM2_PRODUCEPIECE, 0) + :produce_piece
                    WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
        $stmt_ctm = $conn->prepare($sql_ctm);
        $stmt_ctm->bindParam(':produce_weight', $produce_weight);
        $stmt_ctm->bindParam(':produce_piece', $produce_piece);
        $stmt_ctm->bindParam(':so_no', $saleorder_no, PDO::PARAM_STR);
        $stmt_ctm->bindParam(':so_item', $saleorder_item, PDO::PARAM_STR);
        $stmt_ctm->execute();
    }

    // 11. อัปเดตตาราง COILPROD1 ตัดทอน Balance Weight[cite: 7]
    $sql_coil_up = "UPDATE COILPROD1 SET 
                        COIL_PRODUCEWEIGHT = ISNULL(COIL_PRODUCEWEIGHT, 0) + :produce_weight,
                        COIL_SCRAPWEIGHT   = ISNULL(COIL_SCRAPWEIGHT, 0) + :sk_scrap_weight,
                        COIL_BALANCEWEIGHT = :new_balance
                     WHERE COIL_NO = :coil_no";
    $stmt_coil_up = $conn->prepare($sql_coil_up);
    $stmt_coil_up->bindParam(':produce_weight', $produce_weight);
    $stmt_coil_up->bindParam(':sk_scrap_weight', $skeleton_scrap_weight);
    $stmt_coil_up->bindParam(':new_balance', $new_balance_wt);
    $stmt_coil_up->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
    $stmt_coil_up->execute();

    $conn->commit();

    ob_clean();
    echo json_encode([
        'status'       => 'success',
        'message'      => 'บันทึก Pallet ผลผลิตสำเร็จ (Product No: ' . $product_no . ')',
        'pno'          => $product_no,
        'jno'          => $job_order,
        'new_balance'  => $new_balance_wt > 0 ? $new_balance_wt : 0
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดจากระบบหลังบ้าน: ' . $e->getMessage()]);
}
?>