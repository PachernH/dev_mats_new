<?php
// model/create_in_coldmill_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

include '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no    = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $slot_index = isset($_POST['slot_index']) ? intval($_POST['slot_index']) : 0;
    $location_row = $slot_index - 1; 

    if (empty($coil_no)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกหมายเลข Coil No']);
        exit();
    }

    try {
        // --- ตรวจสอบว่ามี Coil นี้อยู่ใน CDMLMCHN1 แล้วหรือยัง ---
        $sql_chk_exist = "SELECT COIL_NO FROM CDMLMCHN1 WHERE COIL_NO = :coil_no";
        $stmt_chk_exist = $conn->prepare($sql_chk_exist);
        $stmt_chk_exist->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_chk_exist->execute();

        if ($stmt_chk_exist->fetch()) {
            echo json_encode(['status' => 'error', 'message' => "Coil No [{$coil_no}] มีอยู่ในระบบ CDMLMCHN1 เรียบร้อยแล้ว"]);
            exit();
        }

        // --- ดึงข้อมูลจาก COILPROD1 ร่วมกับ CMRCMSTR1 ---
        $sql_fetch = "SELECT 
                        A.COIL_NO, A.JOB_PROCESS, A.RECIPE_NO, A.RECIPE_ITEM, A.TOTAL_PASS, 
                        A.CURRENT_PASS, A.THICKNESS_FINAL, A.F_TEMPER, A.F_THICKNESS, A.ALLOY, 
                        A.TEMPER, A.SURFACE_GRADE, A.METALLURGICAL_GRADE, A.THICKNESS, A.WIDTH, 
                        A.COIL_BALANCEWEIGHT, A.COIL_REMARK, A.COIL_STATUS, 
                        B.TEMPER_FINISH, B.THICKNESS_ENTRY, B.THICKNESS_EXIT, B.THICKNESS_TELORANCE, B.SPOOL 
                      FROM COILPROD1 AS A 
                      LEFT JOIN CMRCMSTR1 AS B ON A.RECIPE_NO = B.RECIPE_NO AND A.RECIPE_ITEM = B.RECIPE_ITEM 
                      WHERE A.COIL_NO = :coil_no";
        
        $stmt_fetch = $conn->prepare($sql_fetch);
        $stmt_fetch->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);
        $stmt_fetch->execute();
        $data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            echo json_encode(['status' => 'error', 'message' => "ไม่พบข้อมูล Coil No [{$coil_no}] ในระบบ COILPROD1"]);
            exit();
        }

        // --- ตรวจสอบ 5 เงื่อนไข (Validation Rules) ---
        // 1. JOB_PROCESS มีหรือยัง
        if (empty(trim($data['JOB_PROCESS']))) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล JOB_PROCESS ใน Coil นี้']);
            exit();
        }

        // 2. RECIPE_NO มีหรือยัง
        if (empty(trim($data['RECIPE_NO']))) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล RECIPE_NO ใน Coil นี้']);
            exit();
        }

        // 3. RECIPE_ITEM ต้องเท่ากับ 1 ถึงจะผ่าน (ถ้าไม่เท่ากับ 1 ให้ Error)
        /*
        if (intval($data['RECIPE_ITEM']) !== 1) {
            echo json_encode(['status' => 'error', 'message' => "RECIPE_ITEM ไม่ถูกต้อง (ปัจจุบันคือ {$data['RECIPE_ITEM']} ต้องเท่ากับ 1 เท่านั้น)"]);
            exit();
        }
        */

        // 4. COIL_STATUS ต้องเท่ากับ AC ถึงจะผ่าน (ถ้าไม่เท่ากับ AC ให้ Error)
        if (strtoupper(trim($data['COIL_STATUS'])) !== 'AC') {
            echo json_encode(['status' => 'error', 'message' => "COIL_STATUS ไม่ถูกต้อง (ปัจจุบันคือ {$data['COIL_STATUS']} ต้องเป็น 'AC' เท่านั้น)"]);
            exit();
        }

        // 5. THICKNESS_FINAL = F_THICKNESS ให้ Error
        if (floatval($data['THICKNESS_FINAL']) == floatval($data['F_THICKNESS'])) {
            echo json_encode(['status' => 'error', 'message' => 'THICKNESS_FINAL มีค่าเท่ากับ F_THICKNESS']);
            exit();
        }

        // --- ดึงข้อมูลจาก JOBORDER1 ---
        $job_process = $data['JOB_PROCESS'];
        $sql_job = "SELECT CUST_MAXTOLERANCE, CUST_MINTOLERANCE, JOB_REMARK 
                    FROM JOBORDER1 
                    WHERE JOB_ORDER = :job_process";
        
        $stmt_job = $conn->prepare($sql_job);
        $stmt_job->bindParam(':job_process', $job_process, PDO::PARAM_STR);
        $stmt_job->execute();
        $job_data = $stmt_job->fetch(PDO::FETCH_ASSOC);

        $cust_max   = $job_data ? $job_data['CUST_MAXTOLERANCE'] : null;
        $cust_min   = $job_data ? $job_data['CUST_MINTOLERANCE'] : null;
        $job_remark = $job_data ? $job_data['JOB_REMARK'] : null;
        $now        = date('Y-m-d H:i:s');
        $location   = 'I';
        $count_proc = 0;

        // ลบข้อมูลเก่าใน Slot นี้ก่อนบันทึก
        $sql_del = "DELETE FROM CDMLMCHN1 WHERE LOCATION = 'I' AND LOCATION_ROW = :loc_row";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bindParam(':loc_row', $location_row, PDO::PARAM_INT);
        $stmt_del->execute();

        // --- บันทึกข้อมูลลง CDMLMCHN1 ---
        $sql_insert = "INSERT INTO CDMLMCHN1 (
                        COIL_NO, LOCATION, LOCATION_ROW, JOB_PROCESS, ALLOY, 
                        SURFACE_GRADE, METALLURGICAL_GRADE, TEMPER_ORIGINAL, TEMPER, THICKNESS_ORIGINAL, 
                        THICKNESS, THICKNESS_FINAL, WIDTH, TOTAL_PASS, CURRENT_PASS, 
                        RECIPE_NO, RECIPE_ITEM, SPOOL, TEMPER_FINISH, THICKNESS_ENTRY, 
                        THICKNESS_EXIT, THICKNESS_TELORANCE, CDML_ACTUALWEIGHT, CDML_STARTDATETIME, CDML_ENDDATETIME, 
                        COIL_REMARK, JOB_REMARK, MAX_THICKCUSTOMER, MIN_THICKCUSTOMER, COUNT_PROCESS
                       ) VALUES (
                        ?,?,?,?,?,
                        ?,?,?,?,?,
                        ?,?,?,?,?,
                        ?,?,?,?,?,
                        ?,?,?,?,?,
                        ?,?,?,?,?
                       )";

        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->execute([
            $data['COIL_NO'],
            $location,
            $location_row,
            $data['JOB_PROCESS'],
            $data['ALLOY'],
            $data['SURFACE_GRADE'],
            $data['METALLURGICAL_GRADE'],
            $data['F_TEMPER'],
            $data['TEMPER'],
            $data['F_THICKNESS'],
            $data['THICKNESS'],
            $data['THICKNESS_FINAL'],
            $data['WIDTH'],
            $data['TOTAL_PASS'],
            $data['CURRENT_PASS'],
            $data['RECIPE_NO'],
            $data['RECIPE_ITEM'],
            $data['SPOOL'],
            $data['TEMPER_FINISH'],
            $data['THICKNESS_ENTRY'],
            $data['THICKNESS_EXIT'],
            $data['THICKNESS_TELORANCE'],
            $data['COIL_BALANCEWEIGHT'],
            $now,
            $now,
            $data['COIL_REMARK'],
            $job_remark,
            $cust_max,
            $cust_min,
            $count_proc
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => "บันทึก Coil [{$coil_no}] ลงระบบเรียบร้อยแล้ว"
        ]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}