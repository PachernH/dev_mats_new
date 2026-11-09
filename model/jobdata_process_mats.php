<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include("../dbcon_mats-new.php");

$iduser_func = $_SESSION['ID'] ?? 'SYSTEM';
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input data.']);
    exit;
}

$action = $input['action'] ?? '';

function calculateWeightPerPiece($productId, $width, $length, $thickness, $areaSize) {
    if ($width <= 0 || $thickness <= 0) return 0.0;
    $density = 2.71;

    switch ($productId) {
        case 'CC':
        case 'CR':
            return round(((((22.0 / 7.0 * ($width * $width)) / 4.0) * $thickness) / 1000000.0) * $density, 4);
        case 'SH':
            if ($length > 0) {
                return round((($width * $length * $thickness) / 1000000.0) * $density, 4);
            }
            break;
        case 'NC':
            if ($areaSize > 0) {
                return round((($areaSize * $thickness) / 1000000.0) * $density, 4);
            }
            break;
    }
    return 0.0;
}

try {
    if ($action === 'save') {
        $job_order_no = trim($input['job_order_no'] ?? '');
        $so_no        = trim($input['sale_order_no'] ?? '');
        $so_item      = trim($input['sale_order_item'] ?? '');
        $product_id   = strtoupper(trim($input['product_id'] ?? ''));
        $alloy        = trim($input['alloy'] ?? '');
        $temper       = trim($input['temper'] ?? '');
        $thickness    = (float)($input['thickness'] ?? 0);
        $width        = (float)($input['width'] ?? 0);
        $length       = (float)($input['length'] ?? 0);
        $area_size    = (float)($input['area_size'] ?? 0);
        $work_process = strtoupper(trim($input['work_process'] ?? ''));
        $fixed_process= strtoupper(trim($input['fixed_process'] ?? 'NO'));
        $release_weight = (float)($input['release_weight'] ?? 0);
        $release_piece  = (float)($input['release_piece'] ?? 0);
        $cut_width    = (float)($input['cut_width'] ?? 0);
        $cut_length   = (float)($input['cut_length'] ?? 0);

        // -------------------------------------------------------------
        // เงื่อนไขตรวจสอบความถูกต้องก่อนบันทึกตาม J1 (cmdSave_Click)
        // -------------------------------------------------------------

        // 1. ตรวจสอบเมื่อมีการอ้างอิง Sale Order
        if (!empty($so_no) && !empty($so_item)) {
            $stmt_so = $conn->prepare("SELECT ALLOY, TEMPER, THICKNESS, WIDTH, LENGTH FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
            $stmt_so->execute([':so' => $so_no, ':item' => $so_item]);
            $so_spec = $stmt_so->fetch(PDO::FETCH_ASSOC);

            if ($so_spec) {
                if (trim($so_spec['ALLOY']) !== $alloy) {
                    throw new Exception("The Job Order alloy does not match the Sale Order alloy.");
                }
                if (trim($so_spec['TEMPER']) !== $temper) {
                    throw new Exception("The Job Order temper does not match the Sale Order temper.");
                }
                if (abs((float)$so_spec['THICKNESS'] - $thickness) > 0.0001) {
                    throw new Exception("The Job Order thickness does not match the Sale Order thickness.");
                }
                if (abs((float)$so_spec['WIDTH'] - $width) > 0.0001) {
                    throw new Exception("The Job Order width does not match the Sale Order width.");
                }
                if (($product_id === 'SH' || $product_id === 'NC') && abs((float)$so_spec['LENGTH'] - $length) > 0.0001) {
                    throw new Exception("The Job Order length does not match the Sale Order length.");
                }
            }
        } else {
            // กรณีเปิด Job เดี่ยว (ไม่มี SO) บังคับสเปกพื้นฐาน
            if (empty($alloy)) throw new Exception("Alloy is required. Please select or enter an alloy.");
            if (empty($temper)) throw new Exception("Temper is required. Please select or enter a temper.");
            if ($thickness <= 0) throw new Exception("Thickness is required. Please enter a valid thickness.");
            if ($width <= 0) throw new Exception("Width is required. Please enter a valid width.");
        }

        // 2. ตรวจสอบมิติงานตัดแผ่นตาม Work Process
        $sub_proc = substr($work_process, 0, 2);
        if ($sub_proc === 'CL') {
            if (str_contains($work_process, 'SH') || str_contains($work_process, 'SC') || str_contains($work_process, 'CT')) {
                if ($cut_width <= 0 || $cut_length <= 0) {
                    throw new Exception("The selected work process involves Stretcher, Cut Sheet, or Shear. Cut Width, Cut Length, and Cut Weight per Piece must be specified.");
                }
            }
        } elseif (in_array($sub_proc, ['BK', 'AP', 'KP'])) {
            if (str_contains($work_process, 'SH') && $cut_width <= 0) {
                throw new Exception("The selected work process involves Shear. Cut Width must be specified for Blanking.");
            }
        }

        // 3. ตรวจสอบ Fixed Process
        if ($fixed_process !== 'NO' && !str_contains($work_process, $fixed_process)) {
            throw new Exception("Fixed process '{$fixed_process}' is enforced. Please select the correct Work Process route.");
        }

        // 4. คำนวณน้ำหนักต่อชิ้นและน้ำหนักแผ่นตัด
        $weight_piece = calculateWeightPerPiece($product_id, $width, $length, $thickness, $area_size);
        $cut_weight_piece = 0;
        if ($cut_width > 0) {
            $cut_weight_piece = ($cut_length > 0) 
                ? calculateWeightPerPiece('SH', $cut_width, $cut_length, $thickness, 0)
                : calculateWeightPerPiece('CC', $cut_width, 0, $thickness, 0);
        }

        // 5. จัดการ Remark 3 บรรทัด (บรรทัดละ 65 ตัวอักษร)
        $r1 = str_replace(["\r", "\n"], '', trim($input['remark1'] ?? ''));
        $r2 = str_replace(["\r", "\n"], '', trim($input['remark2'] ?? ''));
        $r3 = str_replace(["\r", "\n"], '', trim($input['remark3'] ?? ''));
        $full_remark = str_pad(substr($r1, 0, 65), 65, ' ') .
                       str_pad(substr($r2, 0, 65), 65, ' ') .
                       str_pad(substr($r3, 0, 65), 65, ' ');

        $conn->beginTransaction();

        $is_new = empty($job_order_no);

        // -------------------------------------------------------------
        // รันเลขที่ Job Order รูปแบบ JB-YY-XXXX จากตาราง JOBORDER1
        // -------------------------------------------------------------
        if ($is_new) {
            $year_suffix = date('y'); // เช่น '26'
            $prefix = "JB-" . $year_suffix . "-"; // 'JB-26-' (ความยาว 6 หลัก)

            // ค้นหา Job Order สูงสุดของปีปัจจุบันที่มีขีดคั่น
            $stmt = $conn->prepare("SELECT MAX(JOB_ORDER) FROM JOBORDER1 WHERE JOB_ORDER LIKE :pfx AND LEN(JOB_ORDER) = 10");
            $stmt->execute([':pfx' => $prefix . '%']);
            $max_job = $stmt->fetchColumn();

            $next_run = 1;
            if ($max_job && strlen($max_job) === 10) {
                // ดึง 4 หลักท้าย (ตำแหน่งที่ 7 เป็นต้นไป)
                $next_run = (int)substr($max_job, 6, 4) + 1;
            }
            $job_order_no = $prefix . str_pad($next_run, 4, '0', STR_PAD_LEFT); // ได้ 'JB-26-XXXX'

            // บันทึกขนาดลง Master ตาราง THCKMSTR1, DMWDMSTR1, LNGTMSTR1 หากยังไม่เคยมี
            $conn->exec("IF NOT EXISTS (SELECT 1 FROM THCKMSTR1 WHERE THICKNESS = {$thickness}) INSERT INTO THCKMSTR1 (THICKNESS) VALUES ({$thickness})");
            $conn->exec("IF NOT EXISTS (SELECT 1 FROM DMWDMSTR1 WHERE WIDTH = {$width}) INSERT INTO DMWDMSTR1 (WIDTH) VALUES ({$width})");
            if ($length > 0) {
                $conn->exec("IF NOT EXISTS (SELECT 1 FROM LNGTMSTR1 WHERE LENGTH = {$length}) INSERT INTO LNGTMSTR1 (LENGTH) VALUES ({$length})");
            }

            // INSERT ลงตารางหลัก JOBORDER1
            $sql = "INSERT INTO JOBORDER1 (
                        JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, JOBORDER_DATE, SCHEDULE_DATE,
                        EXPECT_STARTDATE, EXPECT_FINISHDATE, JOB_PRIORITY, PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE, 
                        ALLOY, TEMPER, PRODUCE_TEMPER1, PRODUCE_TEMPER2, GRADE, PRODUCE_GRADE1, PRODUCE_GRADE2,
                        SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, FLATNESS_GRADE,
                        THICKNESS, WIDTH, LENGTH, JOB_UOMDIMENSION, WEIGHT_PIECE,
                        CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, JOB_WORKPROCESS, FIXED_PROCESS, USE_FORPROCESS,
                        OTHER_COILWIDTH, SUGGEST_WIDTH, RECOVERY, CUST_MAXTOLERANCE, CUST_MINTOLERANCE,
                        JOB_MAXTOLERANCE, JOB_MINTOLERANCE, JOB_RELEASEWEIGHT, JOB_RELEASEPIECE,
                        JOB_UOMWEIGHT, JOB_REMARK, JOB_OPERATOR, JOB_OPERATEDATE, JOB_STATUS
                    ) VALUES (
                        :job_no, :so_no, :so_item, :mat_in, :job_date, :sch_date,
                        :st_date, :fn_date, :priority, :prod_id, :prod_model, :area_size,
                        :alloy, :temper, :ptemp1, :ptemp2, :grade, :pgrade1, :pgrade2,
                        :sg, :mg, :t5, :flatness,
                        :thickness, :width, :length, 'mm.', :weight_piece,
                        :c_w, :c_l, :c_wp, :work_proc, :fixed_proc, :use_proc,
                        :other_w, :sug_w, :recov, :c_max_tol, :c_min_tol,
                        :max_tol, :min_tol, :rel_w, :rel_p,
                        'kg.', :remark, :operator, GETDATE(), :job_status
                    )";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':job_no' => $job_order_no, ':so_no' => $so_no, ':so_item' => $so_item,
                ':mat_in' => $input['material_in'] ?? '',
                ':job_date' => !empty($input['job_order_date']) ? $input['job_order_date'] : date('Y-m-d'),
                ':sch_date' => !empty($input['schedule_date']) ? $input['schedule_date'] : date('Y-m-d'),
                ':st_date' => !empty($input['expect_start_date']) ? $input['expect_start_date'] : null,
                ':fn_date' => !empty($input['expect_finish_date']) ? $input['expect_finish_date'] : null,
                ':priority' => (int)($input['priority'] ?? 1), ':prod_id' => $product_id,
                ':prod_model' => $input['product_model'] ?? '', ':area_size' => $area_size,
                ':alloy' => $alloy, ':temper' => $temper,
                ':ptemp1' => $input['produce_temper1'] ?? 'NONE', ':ptemp2' => $input['produce_temper2'] ?? 'NONE',
                ':grade' => $input['grade'] ?? 'NONE', ':pgrade1' => $input['produce_grade1'] ?? 'NONE',
                ':pgrade2' => $input['produce_grade2'] ?? 'NONE', ':sg' => $input['surface_grade'] ?? '',
                ':mg' => $input['metallurgical_grade'] ?? '', ':t5' => $input['t5_temperature'] ?? '',
                ':flatness' => $input['flatness_grade'] ?? '', ':thickness' => $thickness,
                ':width' => $width, ':length' => $length, ':weight_piece' => $weight_piece,
                ':c_w' => $cut_width, ':c_l' => $cut_length, ':c_wp' => $cut_weight_piece,
                ':work_proc' => $work_process, ':fixed_proc' => $fixed_process,
                ':use_proc' => $input['use_for_process'] ?? '',
                ':other_w' => (!empty($input['other_coil_width']) && $input['other_coil_width']) ? 'Y' : 'N',
                ':sug_w' => (float)($input['suggest_width'] ?? 0),
                ':recov' => (float)($input['recovery'] ?? 0),
                ':c_max_tol' => (float)($input['cust_max_tolerance'] ?? 0),
                ':c_min_tol' => (float)($input['cust_min_tolerance'] ?? 0),
                ':max_tol' => (int)($input['max_tolerance'] ?? 0),
                ':min_tol' => (int)($input['min_tolerance'] ?? 0),
                ':rel_w' => $release_weight, ':rel_p' => $release_piece,
                ':remark' => $full_remark, ':operator' => $iduser_func,
                ':job_status' => $input['job_status'] ?? 'OP'
            ]);

            // อัปเดตยอด Release สะสมใน CSTMORDR2
            if (!empty($so_no) && !empty($so_item)) {
                $upd_so = "UPDATE CSTMORDR2 
                           SET CTM2_RELEASEWEIGHT = ISNULL(CTM2_RELEASEWEIGHT, 0) + :rw,
                               CTM2_RELEASEPIECE  = ISNULL(CTM2_RELEASEPIECE, 0) + :rp,
                               CTM2_WORKPROCESS   = :wp,
                               CUT_WEIGHTPIECE    = :cwp
                           WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
                $stmt = $conn->prepare($upd_so);
                $stmt->execute([
                    ':rw' => $release_weight, ':rp' => $release_piece,
                    ':wp' => $work_process, ':cwp' => $cut_weight_piece,
                    ':so_no' => $so_no, ':so_item' => $so_item
                ]);
            }
        } else {
            // UPDATE กรณี Job เดิม
            $sql = "UPDATE JOBORDER1 SET 
                        MATERIAL_IN = :mat_in, JOBORDER_DATE = :job_date, SCHEDULE_DATE = :sch_date, 
                        EXPECT_STARTDATE = :st_date, EXPECT_FINISHDATE = :fn_date, JOB_PRIORITY = :priority,
                        PRODUCE_TEMPER1 = :ptemp1, PRODUCE_TEMPER2 = :ptemp2, PRODUCE_GRADE1 = :pgrade1, PRODUCE_GRADE2 = :pgrade2,
                        SURFACE_GRADE = :sg, METALLURGICAL_GRADE = :mg, T5_TEMPERATURE = :t5, FLATNESS_GRADE = :flatness,
                        CUT_WIDTH = :c_w, CUT_LENGTH = :c_l, CUT_WEIGHTPIECE = :c_wp,
                        JOB_WORKPROCESS = :work_proc, FIXED_PROCESS = :fixed_proc, USE_FORPROCESS = :use_proc,
                        OTHER_COILWIDTH = :other_w, SUGGEST_WIDTH = :sug_w, RECOVERY = :recov,
                        JOB_MAXTOLERANCE = :max_tol, JOB_MINTOLERANCE = :min_tol,
                        JOB_RELEASEWEIGHT = :rel_w, JOB_RELEASEPIECE = :rel_p,
                        JOB_REMARK = :remark, JOB_UPDATE = :operator, JOB_UPDATEDATE = GETDATE(),
                        JOB_STATUS = :job_status
                    WHERE JOB_ORDER = :job_no";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':mat_in' => $input['material_in'] ?? '',
                ':job_date' => !empty($input['job_order_date']) ? $input['job_order_date'] : date('Y-m-d'),
                ':sch_date' => !empty($input['schedule_date']) ? $input['schedule_date'] : date('Y-m-d'),
                ':st_date' => !empty($input['expect_start_date']) ? $input['expect_start_date'] : null,
                ':fn_date' => !empty($input['expect_finish_date']) ? $input['expect_finish_date'] : null,
                ':priority' => (int)($input['priority'] ?? 1),
                ':ptemp1' => $input['produce_temper1'] ?? 'NONE', ':ptemp2' => $input['produce_temper2'] ?? 'NONE',
                ':pgrade1' => $input['produce_grade1'] ?? 'NONE', ':pgrade2' => $input['produce_grade2'] ?? 'NONE',
                ':sg' => $input['surface_grade'] ?? '', ':mg' => $input['metallurgical_grade'] ?? '',
                ':t5' => $input['t5_temperature'] ?? '', ':flatness' => $input['flatness_grade'] ?? '',
                ':c_w' => $cut_width, ':c_l' => $cut_length, ':c_wp' => $cut_weight_piece,
                ':work_proc' => $work_process, ':fixed_proc' => $fixed_process,
                ':use_proc' => $input['use_for_process'] ?? '',
                ':other_w' => (!empty($input['other_coil_width']) && $input['other_coil_width']) ? 'Y' : 'N',
                ':sug_w' => (float)($input['suggest_width'] ?? 0),
                ':recov' => (float)($input['recovery'] ?? 0),
                ':max_tol' => (int)($input['max_tolerance'] ?? 0),
                ':min_tol' => (int)($input['min_tolerance'] ?? 0),
                ':rel_w' => $release_weight, ':rel_p' => $release_piece,
                ':remark' => $full_remark, ':operator' => $iduser_func,
                ':job_status' => $input['job_status'] ?? 'OP',
                ':job_no' => $job_order_no
            ]);
        }

        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => "Job Order {$job_order_no} saved successfully.", 
            'job_order_no' => $job_order_no
        ]);
        exit;
    }

    if ($action === 'delete') {
        $job_order_no = trim($input['job_order_no'] ?? '');

        // ตรวจสอบ Dependency ก่อนลบตาม J1
        $stmt = $conn->prepare("SELECT 1 FROM CRSHPROD1 WHERE JOB_ORDER = :j");
        $stmt->execute([':j' => $job_order_no]);
        if ($stmt->fetch()) throw new Exception("Cannot delete Job Order: Process records already exist in production tracking (CRSHPROD1).");

        $stmt = $conn->prepare("SELECT 1 FROM COILPROD1 WHERE JOB_ORDER = :j OR JOB_PROCESS = :j2");
        $stmt->execute([':j' => $job_order_no, ':j2' => $job_order_no]);
        if ($stmt->fetch()) throw new Exception("Cannot delete Job Order: Process records already exist in production tracking (COILPROD1).");

        $stmt = $conn->prepare("SELECT 1 FROM PACKPROD1 WHERE JOB_ORDER = :j");
        $stmt->execute([':j' => $job_order_no]);
        if ($stmt->fetch()) throw new Exception("Cannot delete Job Order: Process records already exist in production tracking (PACKPROD1).");

        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT SALEORDER_NO, SALEORDER_ITEM, JOB_RELEASEWEIGHT, JOB_RELEASEPIECE FROM JOBORDER1 WHERE JOB_ORDER = :j");
        $stmt->execute([':j' => $job_order_no]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job && !empty($job['SALEORDER_NO'])) {
            $upd = "UPDATE CSTMORDR2 
                    SET CTM2_RELEASEWEIGHT = CTM2_RELEASEWEIGHT - :rw,
                        CTM2_RELEASEPIECE  = CTM2_RELEASEPIECE - :rp
                    WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
            $stmt = $conn->prepare($upd);
            $stmt->execute([
                ':rw' => (float)$job['JOB_RELEASEWEIGHT'],
                ':rp' => (float)$job['JOB_RELEASEPIECE'],
                ':so_no' => $job['SALEORDER_NO'],
                ':so_item' => $job['SALEORDER_ITEM']
            ]);
        }

        $stmt = $conn->prepare("DELETE FROM JOBORDER1 WHERE JOB_ORDER = :j");
        $stmt->execute([':j' => $job_order_no]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Job Order deleted successfully.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action command.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>