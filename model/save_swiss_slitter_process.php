<?php
session_start();
require_once "../dbcon_mats-new.php"; 

$iduser_func  = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func  = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func    = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : 'K';
$group_func   = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

if (empty($iduser_func)) {
    header("Location: ../index.php");
    exit();
}

$allowed_lines_kp = ['T'];
if (!in_array(strtoupper($line_func), $allowed_lines_kp)) {
    echo "<script>
            alert('เตือน: Line การทำงานของคุณ (" . addslashes($line_func) . ") ไม่ตรงกับกระบวนการ Swiss Slitter Process (Line T)');
            window.location.href = '../dashboard_mats.php';
          </script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no_master      = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $input_width         = isset($_POST['coil_slit_width']) ? (float)$_POST['coil_slit_width'] : 0;
    $num_stands          = isset($_POST['num_coil_slit']) ? (int)$_POST['num_coil_slit'] : 1;
    $input_actual_weight = isset($_POST['actual_weight']) ? (float)$_POST['actual_weight'] : 0;
    $input_remark        = isset($_POST['coil_remark']) ? trim($_POST['coil_remark']) : '';
    $func                = isset($_POST['func']) ? trim($_POST['func']) : '';

    $start_dt = !empty($_POST['start_datetime_slit']) ? str_replace('T', ' ', $_POST['start_datetime_slit']) . ':00' : null;
    $end_dt   = !empty($_POST['end_datetime_slit']) ? str_replace('T', ' ', $_POST['end_datetime_slit']) . ':00' : null;
    $user_id  = $iduser_func;

    if (empty($coil_no_master) || $input_width <= 0 || $input_actual_weight <= 0) {
        die("Invalid parameters.");
    }

    try {
        $conn->beginTransaction();

        // 1. ดึง Master Coil
        $sql_select = "SELECT * FROM COILPROD1 WHERE COIL_NO = :coil_no";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->execute([':coil_no' => $coil_no_master]);
        $master = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$master) {
            throw new Exception("Master Coil not found.");
        }

        $master_width  = (float)($master['WIDTH'] ?? 0);
        $master_actual = (float)($master['COIL_ACTUALWEIGHT'] ?? 0);
        $weight_per_mm = ($master_width > 0) ? ($master_actual / $master_width) : 0;

        // ดึง Inspection Data
        $sql_insp = "SELECT * FROM COILINSP1 WHERE COIL_NO = :coil_no";
        $stmt_insp = $conn->prepare($sql_insp);
        $stmt_insp->execute([':coil_no' => $coil_no_master]);
        $master_insp = $stmt_insp->fetch(PDO::FETCH_ASSOC);

        $prefix = "C" . date('ymd') . "-" . strtoupper($line_func) . "-";

        // 2. สร้าง Coil Slit ย่อย
        for ($i = 0; $i < $num_stands; $i++) {
            
            $sql_seq = "SELECT COUNT(*) as cnt FROM COILPROD1 WHERE COIL_NO LIKE :prefix";
            $stmt_seq = $conn->prepare($sql_seq);
            $stmt_seq->execute([':prefix' => $prefix . '%']);
            $count = $stmt_seq->fetch(PDO::FETCH_ASSOC)['cnt'];

            $seq_no = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
            $new_coil_no = $prefix . $seq_no;

            // บันทึก COILPROD1 (ไม่ผูก Job Order)
            $new_coil = [
                'COIL_NO'             => $new_coil_no,
                'PRODUCT_REFERENCE'   => $coil_no_master,               
                'SALEORDER_NO'        => '',
                'SALEORDER_ITEM'      => '',
                'JOB_ORDER'           => '',      
                'JOB_PROCESS'         => $master['JOB_PROCESS'] ?? '',
                'BATCH_NO'            => $master['BATCH_NO'] ?? '',
                'PRIMARY_SMELT'       => $master['PRIMARY_SMELT'] ?? '',
                'SECONDARY_SMELT'     => $master['SECONDARY_SMELT'] ?? '',
                'COUNTRY_MELT'        => $master['COUNTRY_MELT'] ?? '',
                'COUNTRY_ORIGIN'      => $master['COUNTRY_ORIGIN'] ?? '',
                'MP2_PARTNO'          => $master['MP2_PARTNO'] ?? '',
                'MATERIAL_IN'         => $master['MATERIAL_IN'] ?? '',
                'COIL_TYPE'           => $master['COIL_TYPE'] ?? '',
                'CSTMSPPL_ID'         => $master['CSTMSPPL_ID'] ?? '',
                'PRODUCT_ID'          => 'CO',
                'LINE_PROCESS'        => strtoupper($line_func),                              
                'LOCATION_ID'         => $master['LOCATION_ID'] ?? '',
                'F_ALLOY'             => $master['F_ALLOY'] ?? '',
                'F_TEMPER'            => $master['F_TEMPER'] ?? '',
                'F_GRADE'             => $master['F_GRADE'] ?? '',
                'F_THICKNESS'         => $master['F_THICKNESS'] ?? '',
                'F_WIDTH'             => $input_width,                      
                'ALLOY'               => $master['ALLOY'] ?? '',
                'TEMPER'              => $master['TEMPER'] ?? '',
                'GRADE'               => $master['GRADE'] ?? '',
                'SURFACE_GRADE'       => $master['SURFACE_GRADE'] ?? '',
                'METALLURGICAL_GRADE' => $master['METALLURGICAL_GRADE'] ?? '',
                'T5_TEMPERATURE'      => $master['T5_TEMPERATURE'] ?? '',
                'THICKNESS'           => $master['THICKNESS'] ?? '',
                'WIDTH'               => $input_width,                      
                'ACTUAL_WIDTH'        => $input_width,                      
                'COIL_WORKPROCESS'    => $master['COIL_WORKPROCESS'] ?? '',
                'COIL_NEXTPROCESS'    => 'WS',                             
                'COIL_ACTUALWEIGHT'   => $input_actual_weight,               
                'COIL_FIRSTWEIGHT'    => $input_actual_weight,               
                'COIL_BALANCEWEIGHT'  => $input_actual_weight,               
                'COIL_STARTDATE'      => $start_dt ? date('Y-m-d', strtotime($start_dt)) : null,
                'COIL_STARTTIME'      => $start_dt ? date('H:i:s', strtotime($start_dt)) : null,
                'COIL_ENDDATE'        => $end_dt ? date('Y-m-d', strtotime($end_dt)) : null,
                'COIL_ENDTIME'        => $end_dt ? date('H:i:s', strtotime($end_dt)) : null,
                'COIL_REMARK'         => $input_remark,                    
                'COIL_OPERATOR1'      => $user_id,
                'COIL_OPERATEDATE'    => date('Y-m-d H:i:s'),
                'COIL_STATUS'         => 'AC',
                'USE_FORPROCESS'      => ''                                
            ];

            $columns = array_keys($new_coil);
            $placeholders = array_map(function($col) { return ":" . $col; }, $columns);
            $sql_insert = "INSERT INTO COILPROD1 (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt_insert = $conn->prepare($sql_insert);
            $insert_params = [];
            foreach ($new_coil as $col => $val) { $insert_params[':' . $col] = $val; }
            $stmt_insert->execute($insert_params);

            // คัดลอก COILINSP1
            if ($master_insp) {
                $new_insp = $master_insp;
                $new_insp['COIL_NO'] = $new_coil_no;
                $new_insp['LINE_PROCESS'] = strtoupper($line_func);
                $insp_cols = array_keys($new_insp);
                $insp_placeholders = array_map(function($col) { return ":" . $col; }, $insp_cols);
                $sql_insert_insp = "INSERT INTO COILINSP1 (" . implode(', ', $insp_cols) . ") VALUES (" . implode(', ', $insp_placeholders) . ")";
                $stmt_insert_insp = $conn->prepare($sql_insert_insp);
                $insp_params = [];
                foreach ($new_insp as $col => $val) { $insp_params[':' . $col] = $val; }
                $stmt_insert_insp->execute($insp_params);
            }

            // บันทึก SWSLPROD1
            $sql_swsl = "INSERT INTO SWSLPROD1 (
                            COIL_NO, NUMBER_COILSLIT, SLIT_WIDTH, SLIT_WEIGHTPIECE, 
                            SWSL_STARTDATE, SWSL_STARTTIME, SWSL_ENDDATE, SWSL_ENDTIME,
                            SWSL_ACCEPTWEIGHT, SWSL_REJECTWEIGHT, SWSL_OPERATOR1, SWSL_OPERATEDATE
                        ) VALUES (
                            :coil_no, :num_slit, :slit_width, :slit_weightpiece,
                            :start_date, :start_time, :end_date, :end_time,
                            :accept_weight, :reject_weight, :operator1, :operate_date
                        )";
            $stmt_swsl = $conn->prepare($sql_swsl);
            $stmt_swsl->execute([
                ':coil_no'          => $new_coil_no,
                ':num_slit'         => 1,
                ':slit_width'       => $input_width,
                ':slit_weightpiece' => round($weight_per_mm, 4),
                ':start_date'       => $start_dt ? date('Y-m-d', strtotime($start_dt)) : null,
                ':start_time'       => $start_dt ? date('H:i:s', strtotime($start_dt)) : null,
                ':end_date'         => $end_dt ? date('Y-m-d', strtotime($end_dt)) : null,
                ':end_time'         => $end_dt ? date('H:i:s', strtotime($end_dt)) : null,
                ':accept_weight'    => $input_actual_weight,
                ':reject_weight'    => 0,
                ':operator1'        => $user_id,
                ':operate_date'     => date('Y-m-d H:i:s')
            ]);
        }

        // 3. คำนวณตัดยอดม้วนแม่
        $total_slit_width  = $input_width * $num_stands;
        $batch_actual_weight = $input_actual_weight * $num_stands;

        $current_produce = (float)($master['COIL_PRODUCEWEIGHT'] ?? 0);
        $produce_weight  = round($current_produce + $batch_actual_weight, 2);

        $balance_weight_master = (float)($master['COIL_BALANCEWEIGHT'] ?? 0);
        $new_bal_weight        = round($balance_weight_master - $batch_actual_weight, 2);
        
        $acc_slitwidth = (float)($master['ACC_SLITWIDTH'] ?? 0) + $total_slit_width;

        $current_bln_slitwidth = (float)($master['BLN_SLITWIDTH'] ?? 0);
        $bln_slitwidth = ($current_bln_slitwidth <= 0) ? ($master_width - $total_slit_width) : ($current_bln_slitwidth - $total_slit_width);

        // ตรวจสอบ Scrap
        $sql_stnd = "SELECT VOLUM FROM STNDMSTR14 WHERE PROCESS = 'SS'";
        $stmt_stnd = $conn->prepare($sql_stnd);
        $stmt_stnd->execute();
        $stnd_data = $stmt_stnd->fetch(PDO::FETCH_ASSOC);
        $scrap_limit_width = (!empty($stnd_data) && is_numeric($stnd_data['VOLUM'])) ? (float)$stnd_data['VOLUM'] : 200;

        $new_scrap_no = '';
        if ($bln_slitwidth > 0 && $bln_slitwidth <= $scrap_limit_width) {
            $scrap_prefix = "S" . date('ymd') . "-" . strtoupper($line_func) . "-";
            $sql_scrp_seq = "SELECT COUNT(*) as cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :prefix";
            $stmt_scrp_seq = $conn->prepare($sql_scrp_seq);
            $stmt_scrp_seq->execute([':prefix' => $scrap_prefix . '%']);
            $scrp_count = $stmt_scrp_seq->fetch(PDO::FETCH_ASSOC)['cnt'];

            $scrp_seq_no  = str_pad($scrp_count + 1, 2, '0', STR_PAD_LEFT);
            $new_scrap_no = $scrap_prefix . $scrp_seq_no;
            $scrap_weight = round($weight_per_mm * $bln_slitwidth, 2);
            $current_now  = date('Y-m-d H:i:s');

            $sql_insert_scrap = "INSERT INTO SCRPPROD1 (
                                    PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, 
                                    PROCESS, SCRP_FROM, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, 
                                    SCRP_LABEL, SCRP_OPERATOR, SCRP_OPERATEDATE, SCRP_STATUS
                                 ) VALUES (
                                    :product_no, :scrp_date, :coil_no, :material_in, :line_process,
                                    'SS01', 'CO', :alloy, :scrp_width, :scrp_weight,
                                    1, :scrp_operator, :scrp_operatedate, 'OP'
                                 )";
            $stmt_scrp = $conn->prepare($sql_insert_scrap);
            $stmt_scrp->execute([
                ':product_no'     => $new_scrap_no,
                ':scrp_date'       => $current_now,
                ':coil_no'        => $master['COIL_NO'] ?? '',
                ':material_in'    => $master['MATERIAL_IN'] ?? '',
                ':line_process'   => strtoupper($line_func),
                ':alloy'          => $master['ALLOY'] ?? '',
                ':scrp_width'     => $bln_slitwidth,
                ':scrp_weight'    => $scrap_weight,
                ':scrp_operator'  => $user_id,
                ':scrp_operatedate' => $current_now
            ]);

            $acc_slitwidth += $bln_slitwidth;
            $produce_weight = round($produce_weight + $scrap_weight, 2);
            $bln_slitwidth = 0;
            $new_bal_weight = 0;
        }

        // กำหนดสถานะปิดม้วนแม่เฉพาะเมื่อน้ำหนักหมดม้วนจริงๆ หรือความกว้างคงเหลือ <= 0
        $next_process = ($bln_slitwidth <= 0 || $new_bal_weight <= 0) ? 'NN' : $master['COIL_NEXTPROCESS'];
        $coil_status  = ($bln_slitwidth <= 0 || $new_bal_weight <= 0) ? 'SS' : $master['COIL_STATUS'];

        // UPDATE COILPROD1 Master Coil
        $sql_update_master = "UPDATE COILPROD1 
                              SET COIL_PRODUCEWEIGHT = :produce_weight, 
                                  COIL_BALANCEWEIGHT = :bal_weight, 
                                  ACC_SLITWIDTH = :acc_width, 
                                  BLN_SLITWIDTH = :bln_width, 
                                  COIL_NEXTPROCESS = :next_process, 
                                  COIL_STATUS = :status,
                                  COIL_REMARK = :remark
                              WHERE COIL_NO = :coil_no";
        $stmt_update = $conn->prepare($sql_update_master);
        $stmt_update->execute([
            ':produce_weight' => $produce_weight,
            ':bal_weight'     => $new_bal_weight,
            ':acc_width'       => $acc_slitwidth,
            ':bln_width'       => $bln_slitwidth,
            ':next_process'   => $next_process,
            ':status'         => $coil_status,
            ':remark'         => $input_remark,
            ':coil_no'        => $coil_no_master
        ]);

        $conn->commit();

        $alert_msg = "Saved Slitter Process Successfully!";
        if (!empty($new_scrap_no)) {
            $alert_msg .= "\\n\\n⚠️ Scrap created successfully: " . $new_scrap_no;
        }

        echo "<script>
                alert('" . $alert_msg . "');
                window.location.href = '../swiss_slitter_coil_mats.php?COIL=" . urlencode($coil_no_master) . "&func=" . urlencode($func) . "';
              </script>";

    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>
                alert('Error saving data: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
    }
}
?>