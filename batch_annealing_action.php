<?php
session_start();
include 'dbcon_mats-new.php';

header('Content-Type: application/json; charset=utf-8');

$act = isset($_GET['act']) ? $_GET['act'] : '';
$user_id = $_SESSION['ID'] ?? 'SYSTEM';

// -------------------------------------------------------------------------
// 1. ดึงรายการชิ้นงานที่พร้อมอบเข้าเตา (Modal Lookup)
// -------------------------------------------------------------------------
if ($act === 'get_available_products') {
    $result = [];
    try {
        // 1.1 ดึงรายการ Coil (CO)[cite: 1]
        $sql_c = "SELECT COIL_NO AS PRODUCT_NO, 'CO' AS PRODUCT_TYPE, ALLOY, TEMPER, 
                         THICKNESS, WIDTH, COIL_BALANCEWEIGHT AS WEIGHT, COIL_NEXTPROCESS AS NEXTPROCESS 
                  FROM COILPROD1 
                  WHERE COIL_STATUS = 'AC' 
                    AND COIL_NEXTPROCESS IN ('BA','B1','B2','B3') 
                    AND (COIL_WORKINPROCESS = '' OR COIL_WORKINPROCESS IS NULL)";
        $stmt_c = $conn->query($sql_c);
        while ($r = $stmt_c->fetch(PDO::FETCH_ASSOC)) { 
            $result[] = $r; 
        }

        // 1.2 ดึงรายการ Circle / Sheet (CC, SH, NC)[cite: 1]
        $sql_p = "SELECT PRODUCT_NO, 'CC' AS PRODUCT_TYPE, ALLOY, TEMPER, 
                         THICKNESS, WIDTH, CRSH_ACTUALWEIGHT AS WEIGHT, CRSH_NEXTPROCESS AS NEXTPROCESS 
                  FROM CRSHPROD1 
                  WHERE CRSH_STATUS IN ('OP','AC') 
                    AND CRSH_NEXTPROCESS = 'BA' 
                    AND (CRSH_WORKINPROCESS = '' OR CRSH_WORKINPROCESS IS NULL)";
        $stmt_p = $conn->query($sql_p);
        while ($r = $stmt_p->fetch(PDO::FETCH_ASSOC)) { 
            $result[] = $r; 
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit();
}

// -------------------------------------------------------------------------
// 2. เพิ่มชิ้นงานลงใน Batch พร้อม Validations เทียบสเปกเตา[cite: 1]
// -------------------------------------------------------------------------
// -------------------------------------------------------------------------
// 2. เพิ่มชิ้นงานลงใน Batch พร้อม Validations เทียบสเปกเตา
// -------------------------------------------------------------------------
if ($act === 'add_item') {
    $bno    = $_POST['bno'] ?? '';
    $pno    = $_POST['pno'] ?? '';
    $ptype  = $_POST['ptype'] ?? '';
    $pallet = $_POST['pallet'] ?? '01';
    $force  = (int)($_POST['force'] ?? 0);

    try {
        // ดึงข้อมูล Header ของ Batch ปัจจุบันมาใช้เป็นค่าเริ่มต้น
        $stmt_h = $conn->prepare("SELECT TOP 1 * FROM BTCHPROD0 WHERE BTCH_NO = :bno");
        $stmt_h->execute([':bno' => $bno]);
        $header = $stmt_h->fetch(PDO::FETCH_ASSOC);

        // ดึงสเปกของชิ้นงานใหม่
        if ($ptype === 'CO') {
            $stmt_p = $conn->prepare("SELECT * FROM COILPROD1 WHERE COIL_NO = :pno");
        } else {
            $stmt_p = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
        }
        $stmt_p->execute([':pno' => $pno]);
        $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลชิ้นงาน']);
            exit();
        }

        // แจ้งเตือนสเปกเบี่ยงเบน (Alloy หรือ Temper ไม่ตรงกับชิ้นงานเดิมใน Batch)
        if (!empty($header['ALLOY']) && !$force) {
            if ($header['ALLOY'] !== $prod['ALLOY']) {
                echo json_encode(['status' => 'warn', 'message' => "ชิ้นงานนี้มี Alloy: {$prod['ALLOY']} ต่างจากรายการอื่นในเตา ({$header['ALLOY']}) ยืนยันที่จะอบรวมกันหรือไม่?"]);
                exit();
            }
            if (!empty($header['TEMPER_INITIAL']) && $header['TEMPER_INITIAL'] !== $prod['TEMPER']) {
                echo json_encode(['status' => 'warn', 'message' => "ชิ้นงานนี้มีค่าความแข็งเริ่มต้น Temper: {$prod['TEMPER']} ต่างจากรายการอื่นในเตา ({$header['TEMPER_INITIAL']}) ยืนยันที่จะอบรวมกันหรือไม่?"]);
                exit();
            }
        }

        // ดึงเป้าหมาย Temper จาก Job Order
        $job_order = $prod['JOB_PROCESS'] ?? $prod['JOB_ORDER'] ?? '';
        $job_temper = '';
        if (!empty($job_order)) {
            $stmt_j = $conn->prepare("SELECT TEMPER FROM JOBORDER1 WHERE JOB_ORDER = :job");
            $stmt_j->execute([':job' => $job_order]);
            $row_j = $stmt_j->fetch(PDO::FETCH_ASSOC);
            $job_temper = $row_j['TEMPER'] ?? '';
        }

        $conn->beginTransaction();

        // ดึงสูตรการอบจาก Technical Practice (TPMAT1001)
        $thick = (float)($prod['THICKNESS'] ?? 0);
        $width = (float)($prod['WIDTH'] ?? 0);
        
        $sql_tp = "SELECT TOP 1 * FROM TPMAT1001 
                   WHERE ALLOY = :alloy 
                     AND RANGE_FROM <= :thick AND RANGE_TO >= :thick
                     AND TEMPER_INITIAL = :t_init";
        $params_tp = [
            ':alloy'  => $prod['ALLOY'],
            ':thick'  => $thick,
            ':t_init' => $prod['TEMPER']
        ];
        if (!empty($job_temper)) {
            $sql_tp .= " AND TEMPER_TARGET = :t_target";
            $params_tp[':t_target'] = $job_temper;
        }
        $stmt_tp = $conn->prepare($sql_tp);
        $stmt_tp->execute($params_tp);
        $tp = $stmt_tp->fetch(PDO::FETCH_ASSOC);

        // คำนวณน้ำหนักและจำนวน
        $input_wt = (float)($prod['COIL_BALANCEWEIGHT'] ?? $prod['CRSH_ACTUALWEIGHT'] ?? 0);
        $input_pc = ($ptype === 'CO') ? 1 : (float)($prod['CRSH_ACTUALPIECE'] ?? 1);

        // วันที่และเวลาปัจจุบัน
        $now_date = date('Y-m-d');
        $now_time = date('Y-m-d H:i:s');

        // ค่าเวลาเริ่ม/คาดว่าจะเปิดเตา (ใช้อิงจาก Header เดิมถ้ามี)
        $start_date  = !empty($header['BTCH_STARTDATE'])  ? date('Y-m-d', strtotime($header['BTCH_STARTDATE'])) : $now_date;
        $start_time  = !empty($header['BTCH_STARTTIME'])  ? date('Y-m-d H:i:s', strtotime($header['BTCH_STARTTIME'])) : $now_time;
        $expect_date = !empty($header['BTCH_EXPECTDATE']) ? date('Y-m-d', strtotime($header['BTCH_EXPECTDATE'])) : $now_date;
        $expect_time = !empty($header['BTCH_EXPECTTIME']) ? date('Y-m-d H:i:s', strtotime($header['BTCH_EXPECTTIME'])) : $now_time;

        // คำสั่ง INSERT ระบุคอลัมน์ Non-Null ทั้งหมดให้ครบถ้วน
        $sql_ins = "INSERT INTO BTCHPROD0 (
                        BTCH_NO, PRODUCT_NO, PALLET_ITEM, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER,
                        PRODUCT_ID, ALLOY, TEMPER_INITIAL, TEMPER_TARGET, THICKNESS, WIDTH, LENGTH,
                        BTCH_INPUTWEIGHT, BTCH_INPUTPIECE, BTCH_TOTALWEIGHT,
                        BTCH_INPUTDATE, BTCH_INPUTTIME, CONTROL_BY,
                        RANGE_FROM, RANGE_TO,
                        TP_HEATTIME, TP_HEATTEMPERATURE, TP_SOAKTIME, TP_SOAKTEMPERATURE,
                        TP_WPHEATTEMPERATURE, TP_WPSOAKTEMPERATUREFROM, TP_WPSOAKTEMPERATURETO,
                        AS_HEATTIME, AS_HEATTEMPERATURE, AS_SOAKTIME, AS_SOAKTEMPERATURE,
                        AS_WPHEATTEMPERATURE, AS_WPSOAKTEMPERATURE,
                        N2_STORAGE, N2_START,
                        BTCH_STARTDATE, BTCH_STARTTIME, BTCH_EXPECTDATE, BTCH_EXPECTTIME,
                        BTCH_PRODREMARK, BTCH_TECHREMARK, BTCH_PRINTNOTE,
                        BTCH_OPERATOR, BTCH_OPERATEDATE, BTCH_STATUS
                    ) VALUES (
                        :bno, :pno, :pallet, :sono, :soitem, :job,
                        :pid, :alloy, :t_init, :t_target, :thick, :width, :length,
                        :in_wt, :in_pc, :tot_wt,
                        :in_date, :in_time, :ctrl,
                        :r_from, :r_to,
                        :tp_htime, :tp_htemp, :tp_stime, :tp_stemp,
                        :tp_wp_htemp, :tp_wp_sfrom, :tp_wp_sto,
                        :as_htime, :as_htemp, :as_stime, :as_stemp,
                        :as_wp_htemp, :as_wp_stemp,
                        :n2_store, :n2_start,
                        :sdate, :stime, :edate, :etime,
                        :premark, :tremark, '',
                        :op, GETDATE(), 'IN'
                    )";
        
        $stmt_ins = $conn->prepare($sql_ins);
        $stmt_ins->execute([
            ':bno'         => $bno,
            ':pno'         => $pno,
            ':pallet'      => $pallet,
            ':sono'        => $prod['SALEORDER_NO'] ?? '',
            ':soitem'      => $prod['SALEORDER_ITEM'] ?? '',
            ':job'         => $job_order,
            ':pid'         => $ptype,
            ':alloy'       => $prod['ALLOY'],
            ':t_init'      => $prod['TEMPER'],
            ':t_target'    => $job_temper,
            ':thick'       => $thick,
            ':width'       => $width,
            ':length'      => (float)($prod['LENGTH'] ?? 0),
            ':in_wt'       => $input_wt,
            ':in_pc'       => $input_pc,
            ':tot_wt'      => $input_wt,
            ':in_date'     => $now_date,
            ':in_time'     => $now_time,
            ':ctrl'        => $header['CONTROL_BY'] ?? 'T',
            ':r_from'      => (float)($tp['RANGE_FROM'] ?? 0),
            ':r_to'        => (float)($tp['RANGE_TO'] ?? 0),
            ':tp_htime'    => (int)($tp['HEATING_TIME'] ?? 0),
            ':tp_htemp'    => (int)($tp['HEATING_TEMPERATURE'] ?? 0),
            ':tp_stime'    => (int)($tp['SOAKING_TIME'] ?? 0),
            ':tp_stemp'    => (int)($tp['SOAKING_TEMPERATURE'] ?? 0),
            ':tp_wp_htemp' => (int)($tp['WORKPIECE_HEATING'] ?? 0),
            ':tp_wp_sfrom' => (int)($tp['WORKPIECE_SOAKINGFROM'] ?? 0),
            ':tp_wp_sto'   => (int)($tp['WORKPIECE_SOAKINGTO'] ?? 0),
            ':as_htime'    => (int)($header['AS_HEATTIME'] ?? 0),
            ':as_htemp'    => (int)($header['AS_HEATTEMPERATURE'] ?? 0),
            ':as_stime'    => (int)($header['AS_SOAKTIME'] ?? 0),
            ':as_stemp'    => (int)($header['AS_SOAKTEMPERATURE'] ?? 0),
            ':as_wp_htemp' => (int)($header['AS_WPHEATTEMPERATURE'] ?? 0),
            ':as_wp_stemp' => (int)($header['AS_WPSOAKTEMPERATURE'] ?? 0),
            ':n2_store'    => (float)($header['N2_STORAGE'] ?? 0),
            ':n2_start'    => (float)($header['N2_START'] ?? 0),
            ':sdate'       => $start_date,
            ':stime'       => $start_time,
            ':edate'       => $expect_date,
            ':etime'       => $expect_time,
            ':premark'     => $header['BTCH_PRODREMARK'] ?? '',
            ':tremark'     => $header['BTCH_TECHREMARK'] ?? '',
            ':op'          => $user_id
        ]);

        // อัปเดตชิ้นงานเป็น WORKINPROCESS = 'BA'
        if ($ptype === 'CO') {
            $conn->prepare("UPDATE COILPROD1 SET COIL_WORKINPROCESS = 'BA' WHERE COIL_NO = :pno")->execute([':pno' => $pno]);
        } else {
            $conn->prepare("UPDATE CRSHPROD1 SET CRSH_WORKINPROCESS = 'BA' WHERE PRODUCT_NO = :pno")->execute([':pno' => $pno]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------------
// 3. บันทึกข้อมูล Master Batch Header (f10 & f11)[cite: 1, 7]
// -------------------------------------------------------------------------
if ($act === 'save_master') {
    $bno = $_POST['bno'] ?? '';
    try {
        $sql_up = "UPDATE BTCHPROD0 SET 
                   BTCH_STARTDATE = :sdate, BTCH_STARTTIME = :stime,
                   BTCH_EXPECTDATE = :edate, BTCH_EXPECTTIME = :etime,
                   N2_STORAGE = :n2_store, N2_START = :n2_start, BTCH_PRODREMARK = :premark,
                   CONTROL_BY = :ctrl, AS_HEATTEMPERATURE = :htemp, AS_SOAKTEMPERATURE = :stemp,
                   AS_WPHEATTEMPERATURE = :wp_htemp, AS_WPSOAKTEMPERATURE = :wp_stemp,
                   AS_HEATTIME = :htime, AS_SOAKTIME = :stime_mins, BTCH_TECHREMARK = :tremark,
                   AC_PURGETIME = :ac_pg, AC_PREHEATTEMPERATURE = :ac_phtemp, AC_PREHEATTIME = :ac_phtime,
                   AC_HEATTEMPERATURE = :ac_htemp, AC_HEATTIME = :ac_htime,
                   AC_SOAKTEMPERATURE = :ac_stemp, AC_SOAKTIME = :ac_stime,
                   AC_WPHEATTEMPERATURE = :ac_wp_htemp, AC_WPSOAKTEMPERATURE = :ac_wp_stemp,
                   N2_END = :n2_end, BTCH_STATUS = :status,
                   BTCH_UPDATE = :op, BTCH_UPDATEDATE = GETDATE()
                   WHERE BTCH_NO = :bno";
        $stmt_up = $conn->prepare($sql_up);
        $stmt_up->execute([
            ':sdate'        => $_POST['start_date'],
            ':stime'        => $_POST['start_time'],
            ':edate'        => $_POST['expect_date'],
            ':etime'        => $_POST['expect_time'],
            ':n2_store'     => (float)($_POST['n2_storage'] ?? 0),
            ':n2_start'     => (float)($_POST['n2_start'] ?? 0),
            ':premark'      => $_POST['prod_remark'] ?? '',
            ':ctrl'         => $_POST['control_by'] ?? 'T',
            ':htemp'        => (int)($_POST['as_heat_temp'] ?? 0),
            ':stemp'        => (int)($_POST['as_soak_temp'] ?? 0),
            ':wp_htemp'     => (int)($_POST['as_wp_heat_temp'] ?? 0),
            ':wp_stemp'     => (int)($_POST['as_wp_soak_temp'] ?? 0),
            ':htime'        => (int)($_POST['as_heat_time'] ?? 0),
            ':stime_mins'   => (int)($_POST['as_soak_time'] ?? 0),
            ':tremark'      => $_POST['tech_remark'] ?? '',
            ':ac_pg'        => (int)($_POST['ac_purge_time'] ?? 0),
            ':ac_phtemp'    => (int)($_POST['ac_ph_temp'] ?? 0),
            ':ac_phtime'    => (int)($_POST['ac_ph_time'] ?? 0),
            ':ac_htemp'     => (int)($_POST['ac_heat_temp'] ?? 0),
            ':ac_htime'     => (int)($_POST['ac_heat_time'] ?? 0),
            ':ac_stemp'     => (int)($_POST['ac_soak_temp'] ?? 0),
            ':ac_stime'     => (int)($_POST['ac_soak_time'] ?? 0),
            ':ac_wp_htemp'  => (int)($_POST['ac_wp_heat_temp'] ?? 0),
            ':ac_wp_stemp'  => (int)($_POST['ac_wp_soak_temp'] ?? 0),
            ':n2_end'       => (float)($_POST['n2_end'] ?? 0),
            ':status'       => $_POST['btch_status'] ?? 'IN',
            ':op'           => $user_id,
            ':bno'          => $bno
        ]);

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------------
// 4. ตรวจสอบค่าความแข็งจากผลทดสอบเชิงกล (CheckOutputTemper จาก f11)[cite: 7]
// -------------------------------------------------------------------------
if ($act === 'check_output_temper') {
    $pno   = $_POST['pno'] ?? '';
    $uts   = (float)($_POST['uts'] ?? 0);
    $ys    = (float)($_POST['ys'] ?? 0);
    $elong = (int)($_POST['el'] ?? 0);

    try {
        $stmt_p = $conn->prepare("SELECT TOP 1 ALLOY, THICKNESS FROM BTCHPROD0 WHERE PRODUCT_NO = :pno");
        $stmt_p->execute([':pno' => $pno]);
        $prod = $stmt_p->fetch(PDO::FETCH_ASSOC);

        $match_temper = '';
        if ($prod && $uts > 0) {
            $alloy = $prod['ALLOY'];
            $thick = (float)$prod['THICKNESS'];

            // Query ตรวจสอบกับช่วงสเปกในตาราง TCPS0201_6[cite: 7]
            $sql_tc = "SELECT TEMPER_TARGET, UTSFROM_KSI, UTSTO_KSI, YSFROM_KSI, YSTO_KSI, ELONGFROM, ELONGTO 
                       FROM TCPS0201_6 
                       WHERE ALLOY = :alloy 
                         AND THICKNESS_FROM <= :thick AND THICKNESS_TO >= :thick";
            $stmt_tc = $conn->prepare($sql_tc);
            $stmt_tc->execute([':alloy' => $alloy, ':thick' => $thick]);

            while ($r = $stmt_tc->fetch(PDO::FETCH_ASSOC)) {
                $uts_pass = ($uts >= $r['UTSFROM_KSI'] && ($r['UTSTO_KSI'] == 99 || $uts <= $r['UTSTO_KSI']));
                $ys_pass  = ($r['YSFROM_KSI'] == 0 || ($ys >= $r['YSFROM_KSI'] && ($r['YSTO_KSI'] == 99 || $ys <= $r['YSTO_KSI'])));
                $el_pass  = ($elong >= $r['ELONGFROM'] && ($r['ELONGTO'] == 99 || $elong <= $r['ELONGTO']));

                if ($uts_pass && $ys_pass && $el_pass) {
                    $match_temper = $r['TEMPER_TARGET'];
                    break;
                }
            }
        }

        echo json_encode(['status' => 'success', 'suggested_temper' => $match_temper]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------------
// 5. บันทึกผลทดสอบคุณภาพชิ้นงาน (UTS/YS/Result) ลง BTCHPROD1 (f11)
// -------------------------------------------------------------------------
if ($act === 'save_item_quality') {
    $bno        = $_POST['bno'] ?? '';
    $pno        = $_POST['pno'] ?? '';
    $out_temper = $_POST['out_temper'] ?? '';
    $uts        = (float)($_POST['uts'] ?? 0);
    $ys         = (float)($_POST['ys'] ?? 0);
    $el         = (int)($_POST['el'] ?? 0);
    $res        = $_POST['res'] ?? '';
    $acc_wt     = (float)($_POST['acc_wt'] ?? 0);

    try {
        $conn->beginTransaction();

        // 1. ดึงข้อมูลเดิมจาก BTCHPROD0 เพื่อนำมาใส่วันที่และค่าเริ่มต้น
        $stmt_b0 = $conn->prepare("SELECT TOP 1 * FROM BTCHPROD0 WHERE BTCH_NO = :bno AND PRODUCT_NO = :pno");
        $stmt_b0->execute([':bno' => $bno, ':pno' => $pno]);
        $b0 = $stmt_b0->fetch(PDO::FETCH_ASSOC);

        if (!$b0) {
            throw new Exception("ไม่พบข้อมูลชิ้นงานใน Batch นี้");
        }

        $input_wt = (float)($b0['BTCH_INPUTWEIGHT'] ?? 0);
        $input_pc = (float)($b0['BTCH_INPUTPIECE'] ?? 1);
        $rej_wt   = max(0, $input_wt - $acc_wt);
        $acc_pc   = $input_pc;
        $rej_pc   = ($rej_wt > 0) ? 1 : 0;

        // 2. กำหนดค่าวันที่และเวลาเริ่ม-จบการอบ (ป้องกันค่า NULL)
        $now_date   = date('Y-m-d');
        $now_time   = date('Y-m-d H:i:s');
        
        $start_date = !empty($b0['BTCH_STARTDATE']) ? date('Y-m-d', strtotime($b0['BTCH_STARTDATE'])) : $now_date;
        $start_time = !empty($b0['BTCH_STARTTIME']) ? date('Y-m-d H:i:s', strtotime($b0['BTCH_STARTTIME'])) : $now_time;
        
        // วันที่จบการอบ (ถ้ายังไม่ได้ระบุ ให้ใช้วันที่คาดว่าจะเปิดเตา หรือวันปัจจุบัน)
        $end_date   = !empty($b0['BTCH_EXPECTDATE']) ? date('Y-m-d', strtotime($b0['BTCH_EXPECTDATE'])) : $now_date;
        $end_time   = !empty($b0['BTCH_EXPECTTIME']) ? date('Y-m-d H:i:s', strtotime($b0['BTCH_EXPECTTIME'])) : $now_time;

        // 3. ตรวจสอบว่าเคยมีข้อมูลใน BTCHPROD1 แล้วหรือไม่
        $stmt_c = $conn->prepare("SELECT COUNT(*) FROM BTCHPROD1 WHERE BTCH_NO = :bno AND PRODUCT_NO = :pno");
        $stmt_c->execute([':bno' => $bno, ':pno' => $pno]);
        $has_item = $stmt_c->fetchColumn() > 0;

        if ($has_item) {
            $sql_q = "UPDATE BTCHPROD1 SET 
                      TEMPER = :temper, 
                      BTCH_UTS = :uts, 
                      BTCH_YS = :ys, 
                      BTCH_ELONGATION = :el,
                      BTCH_RESULT = :res, 
                      BTCH_ACCEPTWEIGHT = :acc_wt, 
                      BTCH_ACCEPTPIECE = :acc_pc,
                      BTCH_REJECTWEIGHT = :rej_wt,
                      BTCH_REJECTPIECE = :rej_pc,
                      BTCH_STARTDATE = :sdate,
                      BTCH_STARTTIME = :stime,
                      BTCH_ENDDATE = :edate,
                      BTCH_ENDTIME = :etime,
                      BTCH_UPDATE = :op, 
                      BTCH_UPDATEDATE = GETDATE()
                      WHERE BTCH_NO = :bno AND PRODUCT_NO = :pno";
            $stmt_q = $conn->prepare($sql_q);
            $stmt_q->execute([
                ':temper' => $out_temper,
                ':uts'    => $uts,
                ':ys'     => $ys,
                ':el'     => $el,
                ':res'    => $res,
                ':acc_wt' => $acc_wt,
                ':acc_pc' => $acc_pc,
                ':rej_wt' => $rej_wt,
                ':rej_pc' => $rej_pc,
                ':sdate'  => $start_date,
                ':stime'  => $start_time,
                ':edate'  => $end_date,
                ':etime'  => $end_time,
                ':op'     => $user_id,
                ':bno'    => $bno,
                ':pno'    => $pno
            ]);
        } else {
            // เพิ่มฟิลด์ BTCH_STARTDATE, BTCH_STARTTIME, BTCH_ENDDATE, BTCH_ENDTIME ครบถ้วน
            $sql_q = "INSERT INTO BTCHPROD1 (
                        BTCH_NO, PRODUCT_NO, PALLET_ITEM, PRODUCT_ID, TEMPER, 
                        BTCH_UTS, BTCH_YS, BTCH_ELONGATION, BTCH_RESULT, 
                        BTCH_STARTDATE, BTCH_STARTTIME, BTCH_ENDDATE, BTCH_ENDTIME,
                        BTCH_ACCEPTWEIGHT, BTCH_ACCEPTPIECE, BTCH_REJECTWEIGHT, BTCH_REJECTPIECE,
                        BTCH_OPERATOR1, BTCH_OPERATEDATE
                      ) VALUES (
                        :bno, :pno, :pallet, :pid, :temper, 
                        :uts, :ys, :el, :res, 
                        :sdate, :stime, :edate, :etime,
                        :acc_wt, :acc_pc, :rej_wt, :rej_pc,
                        :op, GETDATE()
                      )";
            $stmt_q = $conn->prepare($sql_q);
            $stmt_q->execute([
                ':bno'    => $bno,
                ':pno'    => $pno,
                ':pallet' => $b0['PALLET_ITEM'] ?? '01',
                ':pid'    => $b0['PRODUCT_ID'] ?? 'CO',
                ':temper' => $out_temper,
                ':uts'    => $uts,
                ':ys'     => $ys,
                ':el'     => $el,
                ':res'    => $res,
                ':sdate'  => $start_date,
                ':stime'  => $start_time,
                ':edate'  => $end_date,
                ':etime'  => $end_time,
                ':acc_wt' => $acc_wt,
                ':acc_pc' => $acc_pc,
                ':rej_wt' => $rej_wt,
                ':rej_pc' => $rej_pc,
                ':op'     => $user_id
            ]);
        }

        // 4. คำนวณหาขั้นตอนถัดไป (Next Process)
        $next_proc = 'BA';
        if ($res === 'PASS') {
            $stmt_rt = $conn->prepare("SELECT CRSH_WORKPROCESS FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
            $stmt_rt->execute([':pno' => $pno]);
            $wproc = $stmt_rt->fetchColumn();
            $pos = strpos($wproc, 'BA');
            if ($pos !== false) {
                $next_proc = substr($wproc, $pos + 3, 2);
            }
        }

        // 5. อัปเดตข้อมูลกลับไปยังตารางหลัก (Coil / Circle)
        if (($b0['PRODUCT_ID'] ?? '') === 'CO') {
            $sql_co = "UPDATE COILPROD1 SET 
                       COIL_BATCHANNEALWEIGHT = :acc_wt, 
                       TEMPER = :temper, 
                       COIL_NEXTPROCESS = :next_p, 
                       COIL_WORKINPROCESS = '' 
                       WHERE COIL_NO = :pno";
            $conn->prepare($sql_co)->execute([
                ':acc_wt' => $acc_wt,
                ':temper' => $out_temper,
                ':next_p' => $next_proc,
                ':pno'    => $pno
            ]);

            $sql_insp = "UPDATE COILINSP1 SET 
                         UTS = :uts, 
                         YIELD_STRENGTH = :ys, 
                         ELONGATION = :el, 
                         BTCH_RESULT = :res, 
                         BTCH_OUTPUTTEMPER = :temper 
                         WHERE COIL_NO = :pno";
            $conn->prepare($sql_insp)->execute([
                ':uts'    => $uts,
                ':ys'     => $ys,
                ':el'     => $el,
                ':res'    => $res,
                ':temper' => $out_temper,
                ':pno'    => $pno
            ]);
        } else {
            $sql_cr = "UPDATE CRSHPROD1 SET 
                       CRSH_BATCHANNEALWEIGHT = :acc_wt, 
                       TEMPER = :temper, 
                       UTS = :uts, 
                       YIELD_STRENGTH = :ys, 
                       ELONGATION = :el,
                       BTCH_RESULT = :res, 
                       BTCH_OUTPUTTEMPER = :temper,
                       CRSH_NEXTPROCESS = :next_p, 
                       CRSH_WORKINPROCESS = '' 
                       WHERE PRODUCT_NO = :pno";
            $conn->prepare($sql_cr)->execute([
                ':acc_wt' => $acc_wt,
                ':temper' => $out_temper,
                ':uts'    => $uts,
                ':ys'     => $ys,
                ':el'     => $el,
                ':res'    => $res,
                ':next_p' => $next_proc,
                ':pno'    => $pno
            ]);

            $sql_insp = "UPDATE CRSHINSP1 SET 
                         UTS = :uts, 
                         YIELD_STRENGTH = :ys, 
                         ELONGATION = :el 
                         WHERE PRODUCT_NO = :pno";
            $conn->prepare($sql_insp)->execute([
                ':uts' => $uts,
                ':ys'  => $ys,
                ':el'  => $el,
                ':pno' => $pno
            ]);
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------------
// 6. ลบชิ้นงานออกจาก Batch พร้อมตรวจสอบกระบวนการถัดไป (Safety Guard)[cite: 1, 7]
// -------------------------------------------------------------------------
if ($act === 'del_item') {
    $bno = $_POST['bno'] ?? '';
    $pno = $_POST['pno'] ?? '';

    try {
        // ตรวจสอบกระบวนการถัดไป: ห้ามลบหากมีการนำชิ้นงานไปทำต่อแล้ว[cite: 1, 7]
        $blocked_tables = [
            'CMBNPROD1' => 'Combine', //[cite: 1, 7]
            'STCHPROD1' => 'Stretcher (SC)', //[cite: 1, 7]
            'CTSHPROD1' => 'Cut Sheet (CT)', //[cite: 1, 7]
            'SHRSPROD1' => 'Shear (SH)', //[cite: 1, 7]
            'PCHLPROD1' => 'Punch Hole (PH)', //[cite: 1, 7]
            'SORTPROD1' => 'Sort (SR)', //[cite: 1, 7]
            'TPTPPROD1' => 'Two Piece (TW)', //[cite: 1, 7]
            'PACKPROD1' => 'Packing (IS)' //[cite: 1, 7]
        ];

        foreach ($blocked_tables as $tbl => $proc_name) {
            $stmt_chk = $conn->prepare("SELECT TOP 1 PRODUCT_NO FROM {$tbl} WHERE PRODUCT_NO = :pno");
            $stmt_chk->execute([':pno' => $pno]);
            if ($stmt_chk->fetch()) {
                echo json_encode([
                    'status' => 'error', 
                    'message' => "ไม่สามารถลบได้ เนื่องจากชิ้นงานนี้ผ่านกระบวนการ {$proc_name} ไปแล้ว"
                ]);
                exit();
            }
        }

        $conn->beginTransaction();

        // ปลดล็อกคืนสถานะ WORKINPROCESS = ''[cite: 1, 7]
        $conn->prepare("UPDATE COILPROD1 SET COIL_WORKINPROCESS = '' WHERE COIL_NO = :pno")->execute([':pno' => $pno]);
        $conn->prepare("UPDATE CRSHPROD1 SET CRSH_WORKINPROCESS = '' WHERE PRODUCT_NO = :pno")->execute([':pno' => $pno]);

        // ลบออกจาก BTCHPROD0 และ BTCHPROD1[cite: 1, 7]
        $conn->prepare("DELETE FROM BTCHPROD0 WHERE BTCH_NO = :bno AND PRODUCT_NO = :pno")->execute([':bno' => $bno, ':pno' => $pno]);
        $conn->prepare("DELETE FROM BTCHPROD1 WHERE BTCH_NO = :bno AND PRODUCT_NO = :pno")->execute([':bno' => $bno, ':pno' => $pno]);

        $conn->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}