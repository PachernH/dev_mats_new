<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include("../dbcon_mats-new.php");

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

try {
    switch ($action) {

        case 'get_initial_masters':
            $res = [];
            
            // 1. Standard Products (ตาม J1)
            $res['products'] = [
                ['PRODUCT_ID' => 'CC', 'PRODUCT_NAME' => 'CC: CIRCLE'],
                ['PRODUCT_ID' => 'CO', 'PRODUCT_NAME' => 'CO: COIL'],
                ['PRODUCT_ID' => 'SH', 'PRODUCT_NAME' => 'SH: SHEET'],
                ['PRODUCT_ID' => 'NC', 'PRODUCT_NAME' => 'NC: CIRCLE'],
                ['PRODUCT_ID' => 'WR', 'PRODUCT_NAME' => 'WR: WIRE'],
                ['PRODUCT_ID' => 'RM', 'PRODUCT_NAME' => 'RM: REMELT']
            ];

            // 2. ป้องกัน Exception แยกตาราง ไม่ให้ส่งผลกระทบกัน
            try {
                $res['alloys'] = $conn->query("SELECT DISTINCT RTRIM(ALLOY) AS ALLOY FROM CMPSMSTR1 WHERE ALLOY IS NOT NULL AND ALLOY <> '' ORDER BY ALLOY")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['alloys'] = []; }

            try {
                $res['tempers'] = $conn->query("SELECT DISTINCT RTRIM(TEMPER) AS TEMPER FROM TMPRMSTR1 WHERE TEMPER IS NOT NULL AND TEMPER <> '' ORDER BY TEMPER")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['tempers'] = []; }

            try {
                $res['grades'] = $conn->query("SELECT DISTINCT RTRIM(GRADE) AS GRADE FROM GRDEMSTR1 WHERE GRADE IS NOT NULL AND GRADE <> '' ORDER BY GRADE")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['grades'] = []; }

            try {
                $res['surface_grades'] = $conn->query("SELECT DISTINCT RTRIM(SURFACE_GRADE) AS SURFACE_GRADE FROM SGRDMSTR1 WHERE SURFACE_GRADE IS NOT NULL AND SURFACE_GRADE <> '' ORDER BY SURFACE_GRADE")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['surface_grades'] = []; }

            try {
                $res['metallurgical_grades'] = $conn->query("SELECT DISTINCT RTRIM(METALLURGICAL_GRADE) AS METALLURGICAL_GRADE FROM MGRDMSTR1 WHERE METALLURGICAL_GRADE IS NOT NULL AND METALLURGICAL_GRADE <> '' ORDER BY METALLURGICAL_GRADE")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['metallurgical_grades'] = []; }

            try {
                $res['flatness_grades'] = $conn->query("SELECT DISTINCT RTRIM(FLATNESS_GRADE) AS FLATNESS_GRADE FROM STNDMSTR13 WHERE FLATNESS_GRADE IS NOT NULL AND FLATNESS_GRADE <> '' ORDER BY FLATNESS_GRADE")->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) { $res['flatness_grades'] = []; }

            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        case 'get_sale_order_item':
            $so_no   = trim($_GET['so_no'] ?? '');
            $so_item = trim($_GET['so_item'] ?? '');

            if (empty($so_no) || empty($so_item)) {
                echo json_encode(['status' => 'error', 'message' => 'Please provide both Sales Order No and Item No.']);
                exit();
            }

            $sql = "SELECT RTRIM(a.SALEORDER_NO) AS SALEORDER_NO, 
                           RTRIM(a.SALEORDER_ITEM) AS SALEORDER_ITEM, 
                           RTRIM(a.CSTMSPPL_ID) AS CSTMSPPL_ID, 
                           RTRIM(a.PRODUCT_ID) AS PRODUCT_ID, 
                           a.PRODUCT_MODEL,
                           RTRIM(a.ALLOY) AS ALLOY, 
                           RTRIM(a.TEMPER) AS TEMPER, 
                           RTRIM(a.SURFACE_GRADE) AS SURFACE_GRADE, 
                           RTRIM(a.METALLURGICAL_GRADE) AS METALLURGICAL_GRADE, 
                           a.T5_TEMPERATURE,
                           ISNULL(a.THICKNESS, 0) AS THICKNESS, 
                           ISNULL(a.WIDTH, 0) AS WIDTH, 
                           ISNULL(a.LENGTH, 0) AS LENGTH,
                           ISNULL(a.AREA_SIZE, 0) AS AREA_SIZE, 
                           ISNULL(a.WEIGHT_PIECE, 0) AS WEIGHT_PIECE, 
                           RTRIM(a.CTM2_STATUS) AS CTM2_STATUS, 
                           RTRIM(a.MATERIAL_IN) AS MATERIAL_IN, 
                           RTRIM(a.CTM2_PONO) AS CTM2_PONO, 
                           RTRIM(a.CTM2_POITEM) AS CTM2_POITEM, 
                           a.CTM2_PODATE,
                           a.REQUEST_DATE,
                           a.CONFIRM_DATE,
                           ISNULL(a.CTM2_ORDERWEIGHT, 0) AS CTM2_ORDERWEIGHT, 
                           ISNULL(a.CTM2_ORDERPIECE, 0) AS CTM2_ORDERPIECE,
                           ISNULL(a.CTM2_REJECTWEIGHT, 0) AS CTM2_REJECTWEIGHT, 
                           ISNULL(a.CTM2_REJECTPIECE, 0) AS CTM2_REJECTPIECE,
                           ISNULL(a.CTM2_REPLACEWEIGHT, 0) AS CTM2_REPLACEWEIGHT, 
                           ISNULL(a.CTM2_REPLACEPIECE, 0) AS CTM2_REPLACEPIECE,
                           ISNULL(a.CTM2_MAXTOLERANCE, 0) AS CTM2_MAXTOLERANCE, 
                           ISNULL(a.CTM2_MINTOLERANCE, 0) AS CTM2_MINTOLERANCE,
                           RTRIM(a.CTM2_CUSTOMERPARTNO) AS CTM2_CUSTOMERPARTNO, 
                           RTRIM(a.ORDER_CATEGORY) AS ORDER_CATEGORY, 
                           RTRIM(a.COIL_FROM) AS COIL_FROM,
                           ISNULL(a.SUGGEST_WIDTH, 0) AS SUGGEST_WIDTH, 
                           ISNULL(a.RECOVERY, 0) AS RECOVERY,
                           ISNULL(a.CTM2_PACKWEIGHT, 0) AS CTM2_PACKWEIGHT, 
                           ISNULL(a.CTM2_INVOICEWEIGHT, 0) AS CTM2_INVOICEWEIGHT,
                           b.SALEORDER_DATE,
                           c.CONSIGNEE_COMPANY
                    FROM CSTMORDR2 a
                    LEFT JOIN CSTMORDR1 b ON a.SALEORDER_NO = b.SALEORDER_NO
                    LEFT JOIN CSSPMSTR1 c ON a.CSTMSPPL_ID = c.CSTMSPPL_ID
                    WHERE a.SALEORDER_NO = :so_no AND a.SALEORDER_ITEM = :so_item";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':so_no' => $so_no, ':so_item' => $so_item]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $is_locked = ($item['CTM2_PACKWEIGHT'] > 0 || $item['CTM2_INVOICEWEIGHT'] > 0);
                echo json_encode([
                    'status'    => 'success', 
                    'data'      => $item,
                    'is_locked' => $is_locked,
                    'lock_msg'  => $is_locked ? "Modification disabled for Sale Order No. {$so_no}/{$so_item}: Order has already progressed to Packing or Invoicing." : ""
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sales Order item not found.']);
            }
            break;

        // ดึง Work Process Dropdown ตามกลุ่ม Product ID (ตาม J1 ControlField)
        case 'get_work_processes':
            $product_id = strtoupper(trim($_GET['product_id'] ?? ''));
            $sub_pid = substr($product_id, 0, 2);

            $proc_list = [];

            if ($sub_pid === 'WR' || $sub_pid === 'RM') {
                $proc_list = ['PK>ST'];
            } else {
                $group = 'COIL/CIRCLE/SHEET';
                if ($sub_pid === 'CO') {
                    $group = 'COIL';
                } elseif ($sub_pid === 'CC' || $sub_pid === 'NC') {
                    $group = 'CIRCLE';
                } elseif ($sub_pid === 'SH') {
                    $group = 'SHEET';
                }

                try {
                    // ตรวจสอบโครงสร้างว่ามีคอลัมน์แบ่งกลุ่ม (PROCESS_GROUP หรือ WORK_PROCESS)
                    // หากตารางมีคอลัมน์ WORK_PROCESS ดึงมาตามเงื่อนไข
                    $stmt = $conn->prepare("SELECT DISTINCT RTRIM(WORK_PROCESS) AS WORK_PROCESS 
                                           FROM WKPCMSTR1 
                                           WHERE WORK_PROCESS IS NOT NULL AND WORK_PROCESS <> ''
                                           ORDER BY WORK_PROCESS");
                    $stmt->execute();
                    $proc_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (Exception $e) {
                    $proc_list = [];
                }
            }

            echo json_encode([
                'status' => 'success',
                'data'   => $proc_list
            ]);
            break;

        case 'get_job_order':
            $job_no = trim($_GET['job_no'] ?? '');

            if (empty($job_no)) {
                echo json_encode(['status' => 'error', 'message' => 'Job Order No is required.']);
                exit();
            }

            $sql_job = "SELECT RTRIM(j.JOB_ORDER) AS JOB_ORDER,
                               RTRIM(j.SALEORDER_NO) AS SALEORDER_NO,
                               RTRIM(j.SALEORDER_ITEM) AS SALEORDER_ITEM,
                               RTRIM(j.MATERIAL_IN) AS MATERIAL_IN,
                               j.JOBORDER_DATE,
                               j.SCHEDULE_DATE,
                               j.EXPECT_STARTDATE,
                               j.EXPECT_FINISHDATE,
                               ISNULL(j.JOB_PRIORITY, 1) AS JOB_PRIORITY,
                               RTRIM(j.PRODUCT_ID) AS PRODUCT_ID,
                               RTRIM(j.PRODUCT_MODEL) AS PRODUCT_MODEL,
                               ISNULL(j.AREA_SIZE, 0) AS AREA_SIZE,
                               RTRIM(j.ALLOY) AS ALLOY,
                               RTRIM(j.TEMPER) AS TEMPER,
                               RTRIM(j.PRODUCE_TEMPER1) AS PRODUCE_TEMPER1,
                               RTRIM(j.PRODUCE_TEMPER2) AS PRODUCE_TEMPER2,
                               RTRIM(j.GRADE) AS GRADE,
                               RTRIM(j.PRODUCE_GRADE1) AS PRODUCE_GRADE1,
                               RTRIM(j.PRODUCE_GRADE2) AS PRODUCE_GRADE2,
                               RTRIM(j.SURFACE_GRADE) AS SURFACE_GRADE,
                               RTRIM(j.METALLURGICAL_GRADE) AS METALLURGICAL_GRADE,
                               RTRIM(j.T5_TEMPERATURE) AS T5_TEMPERATURE,
                               RTRIM(j.FLATNESS_GRADE) AS FLATNESS_GRADE,
                               ISNULL(j.THICKNESS, 0) AS THICKNESS,
                               ISNULL(j.WIDTH, 0) AS WIDTH,
                               ISNULL(j.LENGTH, 0) AS LENGTH,
                               ISNULL(j.WEIGHT_PIECE, 0) AS WEIGHT_PIECE,
                               RTRIM(j.JOB_WORKPROCESS) AS JOB_WORKPROCESS,
                               RTRIM(j.FIXED_PROCESS) AS FIXED_PROCESS,
                               RTRIM(j.USE_FORPROCESS) AS USE_FORPROCESS,
                               ISNULL(j.SUGGEST_WIDTH, 0) AS SUGGEST_WIDTH,
                               ISNULL(j.RECOVERY, 0) AS RECOVERY,
                               RTRIM(j.OTHER_COILWIDTH) AS OTHER_COILWIDTH,
                               ISNULL(j.CUT_WIDTH, 0) AS CUT_WIDTH,
                               ISNULL(j.CUT_LENGTH, 0) AS CUT_LENGTH,
                               ISNULL(j.CUT_WEIGHTPIECE, 0) AS CUT_WEIGHTPIECE,
                               ISNULL(j.CUST_MAXTOLERANCE, 0) AS CUST_MAXTOLERANCE,
                               ISNULL(j.CUST_MINTOLERANCE, 0) AS CUST_MINTOLERANCE,
                               ISNULL(j.JOB_RELEASEWEIGHT, 0) AS JOB_RELEASEWEIGHT,
                               ISNULL(j.JOB_RELEASEPIECE, 0) AS JOB_RELEASEPIECE,
                               ISNULL(j.JOB_MAXTOLERANCE, 0) AS JOB_MAXTOLERANCE,
                               ISNULL(j.JOB_MINTOLERANCE, 0) AS JOB_MINTOLERANCE,
                               j.JOB_REMARK,
                               RTRIM(j.JOB_STATUS) AS JOB_STATUS
                        FROM JOBORDER1 j
                        WHERE j.JOB_ORDER = :job_no";

            $stmt_job = $conn->prepare($sql_job);
            $stmt_job->execute([':job_no' => $job_no]);
            $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                echo json_encode(['status' => 'error', 'message' => "Job Order No. '{$job_no}' not found."]);
                exit();
            }

            $so_spec = null;
            if (!empty($job['SALEORDER_NO']) && !empty($job['SALEORDER_ITEM'])) {
                $sql_so = "SELECT RTRIM(a.SALEORDER_NO) AS SALEORDER_NO, 
                                  RTRIM(a.SALEORDER_ITEM) AS SALEORDER_ITEM, 
                                  RTRIM(a.CSTMSPPL_ID) AS CSTMSPPL_ID, 
                                  RTRIM(a.PRODUCT_ID) AS PRODUCT_ID, 
                                  RTRIM(a.ALLOY) AS ALLOY, 
                                  RTRIM(a.TEMPER) AS TEMPER, 
                                  RTRIM(a.SURFACE_GRADE) AS SURFACE_GRADE, 
                                  RTRIM(a.METALLURGICAL_GRADE) AS METALLURGICAL_GRADE, 
                                  a.T5_TEMPERATURE,
                                  ISNULL(a.THICKNESS, 0) AS THICKNESS, 
                                  ISNULL(a.WIDTH, 0) AS WIDTH, 
                                  ISNULL(a.LENGTH, 0) AS LENGTH,
                                  ISNULL(a.WEIGHT_PIECE, 0) AS WEIGHT_PIECE, 
                                  RTRIM(a.CTM2_STATUS) AS CTM2_STATUS, 
                                  RTRIM(a.MATERIAL_IN) AS MATERIAL_IN, 
                                  RTRIM(a.CTM2_PONO) AS CTM2_PONO, 
                                  RTRIM(a.CTM2_POITEM) AS CTM2_POITEM,
                                  a.CTM2_PODATE,
                                  a.REQUEST_DATE,
                                  a.CONFIRM_DATE,
                                  ISNULL(a.CTM2_ORDERWEIGHT, 0) AS CTM2_ORDERWEIGHT, 
                                  ISNULL(a.CTM2_ORDERPIECE, 0) AS CTM2_ORDERPIECE,
                                  ISNULL(a.CTM2_REJECTWEIGHT, 0) AS CTM2_REJECTWEIGHT, 
                                  ISNULL(a.CTM2_REJECTPIECE, 0) AS CTM2_REJECTPIECE,
                                  ISNULL(a.CTM2_REPLACEWEIGHT, 0) AS CTM2_REPLACEWEIGHT, 
                                  ISNULL(a.CTM2_REPLACEPIECE, 0) AS CTM2_REPLACEPIECE,
                                  ISNULL(a.CTM2_MAXTOLERANCE, 0) AS CTM2_MAXTOLERANCE, 
                                  ISNULL(a.CTM2_MINTOLERANCE, 0) AS CTM2_MINTOLERANCE,
                                  RTRIM(a.CTM2_CUSTOMERPARTNO) AS CTM2_CUSTOMERPARTNO, 
                                  RTRIM(a.ORDER_CATEGORY) AS ORDER_CATEGORY, 
                                  RTRIM(a.COIL_FROM) AS COIL_FROM,
                                  ISNULL(a.SUGGEST_WIDTH, 0) AS SUGGEST_WIDTH, 
                                  ISNULL(a.RECOVERY, 0) AS RECOVERY,
                                  ISNULL(a.CTM2_PACKWEIGHT, 0) AS CTM2_PACKWEIGHT,
                                  ISNULL(a.CTM2_INVOICEWEIGHT, 0) AS CTM2_INVOICEWEIGHT,
                                  b.SALEORDER_DATE,
                                  c.CONSIGNEE_COMPANY
                           FROM CSTMORDR2 a 
                           LEFT JOIN CSTMORDR1 b ON a.SALEORDER_NO = b.SALEORDER_NO 
                           LEFT JOIN CSSPMSTR1 c ON a.CSTMSPPL_ID = c.CSTMSPPL_ID
                           WHERE a.SALEORDER_NO = :so AND a.SALEORDER_ITEM = :item";
                $stmt_so = $conn->prepare($sql_so);
                $stmt_so->execute([
                    ':so'   => $job['SALEORDER_NO'],
                    ':item' => $job['SALEORDER_ITEM']
                ]);
                $so_spec = $stmt_so->fetch(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'status'          => 'success',
                'data'            => $job,
                'sale_order_spec' => $so_spec
            ]);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => "Invalid action: '{$action}'"]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>