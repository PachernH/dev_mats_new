<?php
session_start();
require_once "../dbcon_mats-new.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coil_no_master = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $input_width    = isset($_POST['coil_slit_width']) ? (float)$_POST['coil_slit_width'] : 0;
    $num_stands     = isset($_POST['num_coil_slit']) ? (int)$_POST['num_coil_slit'] : 1;
    $input_remark   = isset($_POST['coil_remark']) ? trim($_POST['coil_remark']) : '';
    $func           = isset($_POST['func']) ? trim($_POST['func']) : '';

    // ⭐ รับค่า Custom Scrap Width จาก Modal หน้าบ้าน (ถ้ามี)
    $custom_scrap_width = isset($_POST['custom_scrap_width']) && $_POST['custom_scrap_width'] !== '' ? (float)$_POST['custom_scrap_width'] : null;

    $start_dt = !empty($_POST['start_datetime_slit']) ? str_replace('T', ' ', $_POST['start_datetime_slit']) . ':00' : null;
    $end_dt   = !empty($_POST['end_datetime_slit']) ? str_replace('T', ' ', $_POST['end_datetime_slit']) . ':00' : null;
    $user_id  = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';

    if (empty($coil_no_master) || $input_width <= 0) {
        die("Invalid parameters.");
    }

    try {
        $conn->beginTransaction();

        // 1. SELECT เฉพาะคอลัมน์ที่กำหนดจาก Coil หลัก
        $sql_select = "SELECT 
                        COIL_NO, PRODUCT_REFERENCE, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, JOB_PROCESS,
                        BATCH_NO, PRIMARY_SMELT, SECONDARY_SMELT, COUNTRY_MELT, COUNTRY_ORIGIN, MP2_PARTNO, MATERIAL_IN,
                        COIL_TYPE, CSTMSPPL_ID, PRODUCT_ID, LINE_PROCESS, LOCATION_ID, F_ALLOY, F_TEMPER, F_GRADE, F_THICKNESS,
                        F_WIDTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, T5_TEMPERATURE, THICKNESS, 
                        WIDTH, ACTUAL_WIDTH, COIL_WORKPROCESS, COIL_NEXTPROCESS, COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, 
                        COIL_BALANCEWEIGHT, COIL_PRODUCEWEIGHT, COIL_STARTDATE, COIL_STARTTIME, COIL_ENDDATE, COIL_ENDTIME, COIL_REMARK, 
                        COIL_OPERATOR1, COIL_OPERATEDATE, COIL_STATUS, BLN_SLITWIDTH, ACC_SLITWIDTH
                       FROM COILPROD1 
                       WHERE COIL_NO = :coil_no";
                       
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->execute([':coil_no' => $coil_no_master]);
        $master = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$master) {
            throw new Exception("Master Coil not found.");
        }

        // ⭐ คำนวณ WeightPerMM จาก CDbl(COIL_ACTUALWEIGHT) / CDbl(WIDTH)
        $master_width  = (float)($master['WIDTH'] ?? 0);
        $actual_weight = (float)($master['COIL_ACTUALWEIGHT'] ?? 0);
        $weight_per_mm = ($master_width > 0) ? ($actual_weight / $master_width) : 0;

        // คำนวณน้ำหนักสำหรับ Coil Slit 1 ม้วน (ปัดทศนิยม 2 ตำแหน่ง)
        $calculated_weight = round($weight_per_mm * $input_width, 2);

        // ⭐ คำนวณ SLIT_WEIGHTPIECE (ทศนิยม 4 ตำแหน่ง) และ SWSL_ACCEPTWEIGHT (ทศนิยม 2 ตำแหน่ง)
        $slit_weightpiece  = round($weight_per_mm, 4);
        $swsl_acceptweight = round($input_width * $slit_weightpiece, 2);

        // ดึงข้อมูล Inspection ของ Coil หลัก
        $sql_insp = "SELECT * FROM COILINSP1 WHERE COIL_NO = :coil_no";
        $stmt_insp = $conn->prepare($sql_insp);
        $stmt_insp->execute([':coil_no' => $coil_no_master]);
        $master_insp = $stmt_insp->fetch(PDO::FETCH_ASSOC);

        // รูปแบบ Prefix CYYMMDD-T-
        $prefix = "C" . date('ymd') . "-T-";

        // 2. ลูปสร้าง Coil Slit ใหม่ตามจำนวน Stand
        for ($i = 0; $i < $num_stands; $i++) {
            
            // 2.1 รันเลข Auto ประจำวัน 2 หลัก (XX)
            $sql_seq = "SELECT COUNT(*) as cnt FROM COILPROD1 WHERE COIL_NO LIKE :prefix";
            $stmt_seq = $conn->prepare($sql_seq);
            $stmt_seq->execute([':prefix' => $prefix . '%']);
            $count = $stmt_seq->fetch(PDO::FETCH_ASSOC)['cnt'];

            $seq_no = str_pad($count + 1, 2, '0', STR_PAD_LEFT);
            $new_coil_no = $prefix . $seq_no;

            // 2.2 กำหนดค่า Array สำหรับ Coil Slit ใหม่ (COILPROD1)
            $new_coil = [
                'COIL_NO'             => $new_coil_no,
                'PRODUCT_REFERENCE'   => $coil_no_master,               
                'SALEORDER_NO'        => $master['SALEORDER_NO'] ?? '',
                'SALEORDER_ITEM'      => $master['SALEORDER_ITEM'] ?? '',
                'JOB_ORDER'           => $master['JOB_ORDER'] ?? '',       
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
                'PRODUCT_ID'          => $master['PRODUCT_ID'] ?? '',
                'LINE_PROCESS'        => 'T',                              
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
                'COIL_ACTUALWEIGHT'   => $calculated_weight,               
                'COIL_FIRSTWEIGHT'    => $calculated_weight,               
                'COIL_BALANCEWEIGHT'  => $calculated_weight,               
                'COIL_STARTDATE'      => $start_dt ? date('Y-m-d', strtotime($start_dt)) : null,
                'COIL_STARTTIME'      => $start_dt ? date('H:i:s', strtotime($start_dt)) : null,
                'COIL_ENDDATE'        => $end_dt ? date('Y-m-d', strtotime($end_dt)) : null,
                'COIL_ENDTIME'        => $end_dt ? date('H:i:s', strtotime($end_dt)) : null,
                'COIL_REMARK'         => $input_remark,                    
                'COIL_OPERATOR1'      => $user_id,
                'COIL_OPERATEDATE'    => date('Y-m-d H:i:s'),
                'COIL_STATUS'         => $master['COIL_STATUS'] ?? 'OP',
                'USE_FORPROCESS'      => ''                                
            ];

            // 2.3 INSERT COILPROD1
            $columns = array_keys($new_coil);
            $placeholders = array_map(function($col) { return ":" . $col; }, $columns);
            
            $sql_insert = "INSERT INTO COILPROD1 (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt_insert = $conn->prepare($sql_insert);
            
            $insert_params = [];
            foreach ($new_coil as $col => $val) {
                $insert_params[':' . $col] = $val;
            }
            $stmt_insert->execute($insert_params);

            // 2.4 คัดลอกข้อมูลลง COILINSP1
            if ($master_insp) {
                $new_insp = $master_insp;
                $new_insp['COIL_NO'] = $new_coil_no;

                $insp_cols = array_keys($new_insp);
                $insp_placeholders = array_map(function($col) { return ":" . $col; }, $insp_cols);

                $sql_insert_insp = "INSERT INTO COILINSP1 (" . implode(', ', $insp_cols) . ") VALUES (" . implode(', ', $insp_placeholders) . ")";
                $stmt_insert_insp = $conn->prepare($sql_insert_insp);

                $insp_params = [];
                foreach ($new_insp as $col => $val) {
                    $insp_params[':' . $col] = $val;
                }
                $stmt_insert_insp->execute($insp_params);
            }

            // 2.5 INSERT ข้อมูลลง SWSLPROD1
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
                ':num_slit'         => $num_stands,
                ':slit_width'       => $input_width,
                ':slit_weightpiece' => $slit_weightpiece,
                ':start_date'       => $start_dt ? date('Y-m-d', strtotime($start_dt)) : null,
                ':start_time'       => $start_dt ? date('H:i:s', strtotime($start_dt)) : null,
                ':end_date'         => $end_dt ? date('Y-m-d', strtotime($end_dt)) : null,
                ':end_time'         => $end_dt ? date('H:i:s', strtotime($end_dt)) : null,
                ':accept_weight'    => $swsl_acceptweight,
                ':reject_weight'    => 0,
                ':operator1'        => $user_id,
                ':operate_date'     => date('Y-m-d H:i:s')
            ]);
        }

        // 3. คำนวณความกว้างและน้ำหนักรวมจากการ Slit ในรอบนี้
        $total_slit_width = $input_width * $num_stands;

        // คำนวณจากผลรวมน้ำหนักลูก Slit จริงที่ปัดเศษแล้ว
        $batch_produce_weight = $calculated_weight * $num_stands;

        // สะสมน้ำหนัก COIL_PRODUCEWEIGHT เพิ่มจากค่าเดิมใน Database (ปัดทศนิยม 2 ตำแหน่ง)
        $current_produce_weight = (float)($master['COIL_PRODUCEWEIGHT'] ?? 0);
        $produce_weight = round($current_produce_weight + $batch_produce_weight, 2);

        $balance_weight_master = (float)($master['COIL_BALANCEWEIGHT'] ?? 0);
        $new_bal_weight = round($balance_weight_master - $batch_produce_weight, 2);
        
        $acc_slitwidth = (float)($master['ACC_SLITWIDTH'] ?? 0) + $total_slit_width;

        // คำนวณ BLN_SLITWIDTH เบื้องต้น
        $current_bln_slitwidth = (float)($master['BLN_SLITWIDTH'] ?? 0);
        if ($current_bln_slitwidth <= 0) {
            $bln_slitwidth = $master_width - $total_slit_width;
        } else {
            $bln_slitwidth = $current_bln_slitwidth - $total_slit_width;
        }

        // ⭐ 3.1 ตรวจสอบกรณีสร้าง Scrap โดยเช็คความกว้างจากค่า VOLUM ของ STNDMSTR14 (PROCESS = 'SS')
        // 🔍 ดึงค่า VOLUM เพื่อใช้เป็นเกณฑ์ความกว้างขั้นสูงในการเกิด Scrap
        $sql_stnd = "SELECT PROCESS, VOLUM, UNIT FROM STNDMSTR14 WHERE PROCESS = 'SS'";
        $stmt_stnd = $conn->prepare($sql_stnd);
        $stmt_stnd->execute();
        $stnd_data = $stmt_stnd->fetch(PDO::FETCH_ASSOC);

        // กำหนด Limit ความกว้างสำหรับ Scrap จากค่า VOLUM (หากดึงไม่พบให้ fallback ใช้ 100)
        $scrap_limit_width = (!empty($stnd_data) && is_numeric($stnd_data['VOLUM'])) ? (float)$stnd_data['VOLUM'] : 100;

        $new_scrap_no = '';
        // เช็คกรณี BALANCE SLIT WIDTH มากกว่า 0 และ น้อยกว่าค่า VOLUM ที่ดึงมาจาก DB
        if ($bln_slitwidth > 0 && $bln_slitwidth < $scrap_limit_width) {
            
            // ⭐ หากมีการส่งค่า custom_scrap_width มาจากหน้าบ้าน ให้ใช้ค่านั้นแทนการคำนวณอัตโนมัติ
            $final_scrap_width = ($custom_scrap_width !== null && $custom_scrap_width > 0) ? $custom_scrap_width : $bln_slitwidth;

            // รันเลข Auto Scrap ประจำวัน รูปแบบ SYYMMDD-T-XX
            $scrap_prefix = "S" . date('ymd') . "-T-";
            $sql_scrp_seq = "SELECT COUNT(*) as cnt FROM SCRPPROD1 WHERE PRODUCT_NO LIKE :prefix";
            $stmt_scrp_seq = $conn->prepare($sql_scrp_seq);
            $stmt_scrp_seq->execute([':prefix' => $scrap_prefix . '%']);
            $scrp_count = $stmt_scrp_seq->fetch(PDO::FETCH_ASSOC)['cnt'];

            $scrp_seq_no  = str_pad($scrp_count + 1, 2, '0', STR_PAD_LEFT);
            $new_scrap_no = $scrap_prefix . $scrp_seq_no;

            // คำนวณน้ำหนักที่เหลือสำหรับ Scrap จาก final_scrap_width
            $scrap_weight = round($weight_per_mm * $final_scrap_width, 2);
            $current_now  = date('Y-m-d H:i:s');

            // INSERT ข้อมูลลง SCRPPROD1
            $sql_insert_scrap = "INSERT INTO SCRPPROD1 (
                                    PRODUCT_NO, SCRP_DATE, COIL_NO, MATERIAL_IN, LINE_PROCESS, 
                                    PROCESS, SCRP_FROM, ALLOY, SCRP_WIDTH, SCRP_WEIGHT, 
                                    SCRP_LABEL, SCRP_OPERATOR, SCRP_OPERATEDATE, SCRP_STATUS
                                 ) VALUES (
                                    :product_no, :scrp_date, :coil_no, :material_in, :line_process,
                                    :process, :scrp_from, :alloy, :scrp_width, :scrp_weight,
                                    :scrp_label, :scrp_operator, :scrp_operatedate, :scrp_status
                                 )";

            $stmt_scrp = $conn->prepare($sql_insert_scrap);
            $stmt_scrp->execute([
                ':product_no'     => $new_scrap_no,
                ':scrp_date'       => $current_now,
                ':coil_no'        => $master['COIL_NO'] ?? '',
                ':material_in'    => $master['MATERIAL_IN'] ?? '',
                ':line_process'   => 'T',
                ':process'        => 'SS01',
                ':scrp_from'      => 'CO',
                ':alloy'          => $master['ALLOY'] ?? '',
                ':scrp_width'     => $final_scrap_width,
                ':scrp_weight'    => $scrap_weight,
                ':scrp_label'     => 1,
                ':scrp_operator'  => $user_id,
                ':scrp_operatedate' => $current_now,
                ':scrp_status'    => 'OP'
            ]);

            // ⭐ 3.2 UPDATE ค่าของ Coil หลักเพิ่มเติมเมื่อนำเศษไปทำ Scrap (ปัดทศนิยม 2 ตำแหน่ง)
            $acc_slitwidth += $final_scrap_width;                  // สะสมความกว้าง Scrap เข้า ACC_SLITWIDTH
            $produce_weight = round($produce_weight + $scrap_weight, 2); // รวมน้ำหนัก Scrap เข้า COIL_PRODUCEWEIGHT สะสม
            $bln_slitwidth = 0;                                     // เซ็ต BLN_SLITWIDTH เป็น 0
            $new_bal_weight = 0;                                    // เซ็ต COIL_BALANCEWEIGHT เป็น 0
        }

        // กำหนด COIL_NEXTPROCESS และ COIL_STATUS เมื่อ BLN_SLITWIDTH <= 0[cite: 3]
        $next_process = ($bln_slitwidth <= 0) ? 'NN' : $master['COIL_NEXTPROCESS'];
        $coil_status  = ($bln_slitwidth <= 0) ? 'SS' : $master['COIL_STATUS'];

        // 4. UPDATE COILPROD1 (Coil Master)
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

        // แสดงผลแจ้งเตือนเพิ่มเติมหากมีการบันทึก Scrap[cite: 3]
        $alert_msg = "Saved Slitter Process Successfully!";
        if (!empty($new_scrap_no)) {
            $alert_msg .= "\\n\\n⚠️ Scrap created successfully: " . $new_scrap_no . "\\nMaster Coil status set to SS / NN.";
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