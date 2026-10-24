<?php
// 1. ปิดการแสดง Error/Warning ออกทาง HTML หน้าจอ
error_reporting(0);
ini_set('display_errors', 0);

// เริ่ม Buffer เพื่อล้างข้อความขยะก่อนหน้าทั้งหมด
ob_start();

// เริ่ม session
session_start();

header('Content-Type: application/json; charset=utf-8');

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';

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

require_once "../dbcon_mats-new.php";

// รับค่าจาก Form POST[cite: 1]
$job_order      = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
$coil_no        = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
$produce_piece  = isset($_POST['produce_piece']) ? (float)$_POST['produce_piece'] : 0;
$produce_weight = isset($_POST['produce_weight']) ? (float)$_POST['produce_weight'] : 0;
$pallet_weight  = isset($_POST['pallet_weight']) ? (float)$_POST['pallet_weight'] : 0;
$skeleton_scrap_weight = isset($_POST['skeleton_scrap_weight']) ? (float)$_POST['skeleton_scrap_weight'] : 0;
$start_datetime = isset($_POST['start_datetime']) ? $_POST['start_datetime'] : '';
$end_datetime   = isset($_POST['end_datetime']) ? $_POST['end_datetime'] : '';

if (empty($job_order) || empty($coil_no) || $produce_piece <= 0) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน หรือจำนวนชิ้นงานต้องมากกว่า 0']);
    exit();
}

// -----------------------------------------------------------------
// เพิ่ม: ตรวจสอบวันเวลาจบ ต้องไม่น้อยกว่าวันเวลาเริ่ม
// -----------------------------------------------------------------
if (!empty($start_datetime) && !empty($end_datetime)) {
    if (strtotime($end_datetime) <= strtotime($start_datetime)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'วัน/เวลา สิ้นสุดการผลิต ต้องไม่อยู่ก่อนวัน/เวลา เริ่มต้น']);
        exit();
    }
}

// ดึงค่าสเปก Release Weight, Produce Weight สะสมเดิม และ Max Tolerance %
    $release_wt     = (float)($job['JOB_RELEASEWEIGHT'] ?? 0);
    $curr_prod_wt   = (float)($job['JOB_PRODUCEWEIGHT'] ?? 0);
    $max_tol_pct    = (float)($job['JOB_MAXTOLERANCE'] ?? 0);
    $max_allowed_wt = $release_wt + (($release_wt * $max_tol_pct) / 100);

    // เช็ก Hard Block: ถ้ายอดรวมใหม่ เกิน Max Allowed Weight
    if ($max_allowed_wt > 0 && ($curr_prod_wt + $produce_weight) > $max_allowed_wt) {
        ob_clean();
        echo json_encode([
            'status'  => 'error', 
            'message' => 'ยอดผลิตสะสมรวมพาเลทนี้ (' . ($curr_prod_wt + $produce_weight) . ' kg.) เกินพิกัดสูงสุดที่อนุญาต (' . number_format($max_allowed_wt, 2) . ' kg. [+' . $max_tol_pct . '%]) กรุณาติดต่อฝ่ายวางแผน'
        ]);
        exit();
    }

try {
    $conn->beginTransaction();

    $start_date = !empty($start_datetime) ? date('Y-m-d', strtotime($start_datetime)) : date('Y-m-d');
    $start_time = !empty($start_datetime) ? date('H:i:s', strtotime($start_datetime)) : date('H:i:s');
    $end_date   = !empty($end_datetime) ? date('Y-m-d', strtotime($end_datetime)) : date('Y-m-d');
    $end_time   = !empty($end_datetime) ? date('H:i:s', strtotime($end_datetime)) : date('H:i:s');
    $now_time   = date('Y-m-d H:i:s');

    // 1. เจนเนอเรต PRODUCT_NO ตัวใหม่สำหรับ Pallet นี้ (รูปแบบ PYYMMDD-A-XX)[cite: 1]
    $prefix = "P" . date('ymd') . "-" . strtoupper($line_func) . "-";
    $sql_run = "SELECT COUNT(*) as run_cnt FROM CRSHPROD1 WHERE PRODUCT_NO LIKE :prefix";
    $stmt_run = $conn->prepare($sql_run);
    $search_prefix = $prefix . "%";
    $stmt_run->bindParam(':prefix', $search_prefix, PDO::PARAM_STR);
    $stmt_run->execute();
    $row_run = $stmt_run->fetch(PDO::FETCH_ASSOC);
    $next_no = ((int)($row_run['run_cnt'] ?? 0)) + 1;
    $product_no = $prefix . sprintf("%02d", $next_no);

    // 2. ดึงข้อมูล Job Order สเปก จาก JOBORDER1[cite: 1]
    $sql_job = "SELECT 
                    j.PRODUCT_ID, j.PRODUCT_MODEL, j.AREA_SIZE, j.JOB_WORKPROCESS,
                    j.ALLOY, j.TEMPER, j.GRADE, j.SURFACE_GRADE, j.METALLURGICAL_GRADE, j.T5_TEMPERATURE,
                    j.THICKNESS, j.WIDTH, j.LENGTH, j.WEIGHT_PIECE, j.CUT_WIDTH, j.CUT_LENGTH, j.CUT_WEIGHTPIECE,
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

    $saleorder_no   = $job['SALEORDER_NO'] ?? '';
    $saleorder_item = $job['SALEORDER_ITEM'] ?? '';
    $work_process   = $job['JOB_WORKPROCESS'] ?? '';

    // === LOGIC f4: เช็ก WorkProcess เพื่อเลือก Weight per Piece ในการคำนวณสำรอง ===[cite: 1]
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

    // กรณีน้ำหนักผลิตส่งมาเป็น 0 คำนวณให้อัตโนมัติ[cite: 1]
    if ($produce_weight <= 0 && $produce_piece > 0 && $wt_per_piece > 0) {
        $produce_weight = round($produce_piece * $wt_per_piece);
    }

    // คำนวณหา Next Process
    $next_process = 'SC';
    if (strpos($work_process, 'CL>') !== false) {
        $parts = explode('CL>', $work_process);
        if (isset($parts[1]) && !empty($parts[1])) {
            $next_process = substr($parts[1], 0, 2);
        }
    }

    // 3. ดึงข้อมูล Coil จาก COILPROD1[cite: 1]
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

    $curr_balance_wt = (float)($coil['COIL_BALANCEWEIGHT'] ?? 0);
    $material_in     = $coil['MATERIAL_IN'] ?? '';
    $alloy           = $coil['ALLOY'] ?? '';
    $location_id     = $coil['LOCATION_ID'] ?? 'NONE';

    // คำนวณน้ำหนักคงเหลือใหม่หลังตัด[cite: 1]
    $new_balance_wt = $curr_balance_wt - ($produce_weight+$skeleton_scrap_weight);

    // 4. บันทึกข้อมูล Pallet ผลผลิตลง CRSHPROD1 ครบทุกฟิลด์ตามโครงสร้าง f4[cite: 1]
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
                    'kg.', :weight_piece, :cut_width, :cut_length, :cut_weightpiece, 0, 0, 0,
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
    $stmt_crsh->bindValue(':product_id', $job['PRODUCT_ID'] ?? 'SH', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':product_model', $job['PRODUCT_MODEL'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':area_size', $job['AREA_SIZE'] ?? 0);
    $stmt_crsh->bindValue(':line_process', $line_func, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':org_lineprocess', $line_func, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':work_process', $work_process, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':next_process', $next_process, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':location_id', $location_id, PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_alloy', $coil['ALLOY'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_temper', $coil['TEMPER'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':f_width', $coil['WIDTH'] ?? 0);
    $stmt_crsh->bindValue(':f_length', $job['LENGTH'] ?? 0);
    $stmt_crsh->bindValue(':alloy', $coil['ALLOY'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':temper', $coil['TEMPER'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':grade', $coil['GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':sg', $coil['SURFACE_GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':mg', $coil['METALLURGICAL_GRADE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':t5_temp', $coil['T5_TEMPERATURE'] ?? '', PDO::PARAM_STR);
    $stmt_crsh->bindValue(':thickness', $coil['THICKNESS'] ?? 0);
    $stmt_crsh->bindValue(':width', $job['WIDTH'] ?? 0);
    $stmt_crsh->bindValue(':length', $job['LENGTH'] ?? 0);
    $stmt_crsh->bindValue(':weight_piece', $job['WEIGHT_PIECE'] ?? 0);
    $stmt_crsh->bindValue(':cut_width', $job['CUT_WIDTH'] ?? 0);
    $stmt_crsh->bindValue(':cut_length', $job['CUT_LENGTH'] ?? 0);
    $stmt_crsh->bindValue(':cut_weightpiece', $job['CUT_WEIGHTPIECE'] ?? 0);
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

    // 5. อัปเดตยอดสะสมผลผลิตลงใน JOBORDER1[cite: 1]
    $sql_job_up = "UPDATE JOBORDER1 SET 
                        JOB_PRODUCEWEIGHT = ISNULL(JOB_PRODUCEWEIGHT, 0) + :produce_weight,
                        JOB_PRODUCEPIECE  = ISNULL(JOB_PRODUCEPIECE, 0) + :produce_piece
                    WHERE JOB_ORDER = :job_order";
    $stmt_job_up = $conn->prepare($sql_job_up);
    $stmt_job_up->bindParam(':produce_weight', $produce_weight);
    $stmt_job_up->bindParam(':produce_piece', $produce_piece);
    $stmt_job_up->bindParam(':job_order', $job_order);
    $stmt_job_up->execute();

    // 6. อัปเดตยอดสะสมผลผลิตลงใน CSTMORDR2[cite: 1]
    if (!empty($saleorder_no) && !empty($saleorder_item)) {
        $sql_ctm = "UPDATE CSTMORDR2 SET 
                        CTM2_PRODUCEWEIGHT = ISNULL(CTM2_PRODUCEWEIGHT, 0) + :produce_weight,
                        CTM2_PRODUCEPIECE  = ISNULL(CTM2_PRODUCEPIECE, 0) + :produce_piece
                    WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
        $stmt_ctm = $conn->prepare($sql_ctm);
        $stmt_ctm->bindParam(':produce_weight', $produce_weight);
        $stmt_ctm->bindParam(':produce_piece', $produce_piece);
        $stmt_ctm->bindParam(':so_no', $saleorder_no);
        $stmt_ctm->bindParam(':so_item', $saleorder_item);
        $stmt_ctm->execute();
    }

    // บันทึกเศษโครงปั๊ม (Skeleton Scrap) ลงใน SCRPPROD1 หากมีน้ำหนัก
    if ($skeleton_scrap_weight > 0) {
        $scrp_sk_prefix = "S" . date('ymd') . "-" . strtoupper($line_func) . "-";
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
                            :product_no, :scrp_date, :coil_no, :material_in, :line_process, 'BK01', 'SK',
                            :location_id, :alloy, 0, :scrp_weight, 'SKELETON SCRAP FROM PRESS', 0, :operator,
                            :operatedate, 'OP'
                        )";
        $stmt_scrp_sk = $conn->prepare($sql_scrp_sk);
        $stmt_scrp_sk->bindParam(':product_no', $scrap_sk_no, PDO::PARAM_STR);
        $stmt_scrp_sk->bindParam(':scrp_date', $end_date);
        $stmt_scrp_sk->bindParam(':coil_no', $coil_no);
        $stmt_scrp_sk->bindParam(':material_in', $material_in);
        $stmt_scrp_sk->bindParam(':line_process', $line_func);
        $stmt_scrp_sk->bindParam(':location_id', $location_id);
        $stmt_scrp_sk->bindParam(':alloy', $alloy);
        $stmt_scrp_sk->bindParam(':scrp_weight', $skeleton_scrap_weight);
        $stmt_scrp_sk->bindParam(':operator', $iduser_func);
        $stmt_scrp_sk->bindParam(':operatedate', $now_time);
        $stmt_scrp_sk->execute();
    }

    // 7. ตรวจสอบเงื่อนไข AUTO CLOSE COIL & AUTO SCRAP[cite: 1]
    $is_auto_closed = false;
    if ($new_balance_wt < $wt_per_piece || $new_balance_wt < 50) {
        $is_auto_closed = true;
        $scrap_weight   = $new_balance_wt > 0 ? $new_balance_wt : 0;

        // 7.1 บันทึกเศษเข้า SCRPPROD1 (ตาม f4 AddScrap)
        if ($scrap_weight > 0) {
            // === เพิ่ม: รันเลข SCRAP_NO ใหม่ รูปแบบ SYYMMDD-Line-Running ===
            $scrp_prefix = "S" . date('ymd') . "-" . strtoupper($line_func) . "-";
            $sql_scrp_run = "SELECT COUNT(*) as run_cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :scrp_prefix";
            $stmt_scrp_run = $conn->prepare($sql_scrp_run);
            $search_scrp_prefix = $scrp_prefix . "%";
            $stmt_scrp_run->bindParam(':scrp_prefix', $search_scrp_prefix, PDO::PARAM_STR);
            $stmt_scrp_run->execute();
            $row_scrp_run = $stmt_scrp_run->fetch(PDO::FETCH_ASSOC);
            $next_scrp_no = ((int)($row_scrp_run['run_cnt'] ?? 0)) + 1;
            $scrap_no = $scrp_prefix . sprintf("%02d", $next_scrp_no);

            $sql_scrp = "INSERT INTO SCRPPROD1 (
                            PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, PROCESS, SCRP_FROM,
                            LOCATION_ID, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, SCRP_REMARK, SCRP_LABEL, SCRP_OPERATOR,
                            SCRP_OPERATEDATE, SCRP_STATUS
                        ) VALUES (
                            :product_no, :scrp_date, :coil_no, :material_in, :line_process, 'CL01', 'CO',
                            :location_id, :alloy, 0, :scrp_weight, 'AUTO SCRAP CLOSE COIL', 0, :operator,
                            :operatedate, 'OP'
                        )";
            $stmt_scrp = $conn->prepare($sql_scrp);
            
            // === แก้ไข: เปลี่ยนค่าที่ส่งเข้า :product_no เป็น $scrap_no ===
            $stmt_scrp->bindParam(':product_no', $scrap_no, PDO::PARAM_STR);
            $stmt_scrp->bindParam(':scrp_date', $end_date);
            $stmt_scrp->bindParam(':coil_no', $coil_no);
            $stmt_scrp->bindParam(':material_in', $material_in);
            $stmt_scrp->bindParam(':line_process', $line_func);
            $stmt_scrp->bindParam(':location_id', $location_id);
            $stmt_scrp->bindParam(':alloy', $alloy);
            $stmt_scrp->bindParam(':scrp_weight', $scrap_weight);
            $stmt_scrp->bindParam(':operator', $iduser_func);
            $stmt_scrp->bindParam(':operatedate', $now_time);
            $stmt_scrp->execute();
        }

        // 7.2 อัปเดต COILPROD1 เป็น Close Coil (STATUS = 'CL', BALANCE = 0)[cite: 1]
        $sql_coil_up = "UPDATE COILPROD1 SET 
                            COIL_PRODUCEWEIGHT = ISNULL(COIL_PRODUCEWEIGHT, 0) + :produce_weight,
                            COIL_SCRAPWEIGHT   = ISNULL(COIL_SCRAPWEIGHT, 0) + :scrap_weight,
                            COIL_BALANCEWEIGHT = 0,
                            COIL_STATUS        = 'CL'
                         WHERE COIL_NO = :coil_no";
        $stmt_coil_up = $conn->prepare($sql_coil_up);
        $stmt_coil_up->bindParam(':produce_weight', $produce_weight);
        $stmt_coil_up->bindParam(':scrap_weight', $scrap_weight);
        $stmt_coil_up->bindParam(':coil_no', $coil_no);
        $stmt_coil_up->execute();

        // 7.3 ลบรายการค้างใน COILWORK1[cite: 1]
        $sql_del_cw = "DELETE FROM COILWORK1 WHERE COIL_NO = :coil_no AND LINE_PROCESS = :line_process";
        $stmt_del_cw = $conn->prepare($sql_del_cw);
        $stmt_del_cw->bindParam(':coil_no', $coil_no);
        $stmt_del_cw->bindParam(':line_process', $line_func);
        $stmt_del_cw->execute();

    } else {
        // กรณีตัดแล้วม้วนยังเหลือมากกว่า 50 kg. อัปเดต Balance ตามปกติ[cite: 1]
        $sql_coil_up = "UPDATE COILPROD1 SET 
                            COIL_PRODUCEWEIGHT = ISNULL(COIL_PRODUCEWEIGHT, 0) + :produce_weight,
                            COIL_SCRAPWEIGHT   = ISNULL(COIL_SCRAPWEIGHT, 0) + :sk_scrap_weight,
                            COIL_BALANCEWEIGHT = :new_balance
                        WHERE COIL_NO = :coil_no";
        $stmt_coil_up = $conn->prepare($sql_coil_up);
        $stmt_coil_up->bindParam(':produce_weight', $produce_weight);
        $stmt_coil_up->bindParam(':sk_scrap_weight', $skeleton_scrap_weight);
        $stmt_coil_up->bindParam(':new_balance', $new_balance_wt);
        $stmt_coil_up->bindParam(':coil_no', $coil_no);
        $stmt_coil_up->execute();
    }

    $conn->commit();

    $msg = 'บันทึก Pallet ผลผลิตสำเร็จ (Product No: ' . $product_no . ')';
    if ($is_auto_closed) {
        $msg .= ' - ระบบได้ทำการปิดม้วน Close Coil & Auto Scrap อัตโนมัติเนื่องจากน้ำหนักเหลือน้อยกว่า 50 kg.';
    }

    // ล้าง Output Buffer ก่อนส่ง JSON
    ob_clean();
    echo json_encode([
        'status'       => 'success',
        'message'      => $msg,
        'pno'          => $product_no,
        'jno'          => $job_order,
        'new_balance'  => $new_balance_wt > 0 ? $new_balance_wt : 0,
        'auto_closed'  => $is_auto_closed
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>