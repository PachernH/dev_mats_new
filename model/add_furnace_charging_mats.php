<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('../dbcon_mats-new.php');
date_default_timezone_set("Asia/Bangkok");

$response = array();
$response['message'] = false;

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // แก้ไขเรื่อง Case Sensitive ของ Session ID
    $operator = isset($_SESSION['ID']) ? $_SESSION['ID'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 'unknown');
    $operateDate = date('Y-m-d H:i:s');
    $furnace = 'MELT';

    if(isset($_POST['data_tag1']) && !empty($_POST['data_tag1'])){
        $data1 = explode("*", $_POST['data_tag1']);
        
        // รับข้อมูลอย่างน้อย 19 ฟิลด์ขึ้นไป
        if(count($data1) >= 19) {
            
            $batch_no       = trim($data1[0]);
            $batch_date     = trim($data1[1]);
            $line_process   = trim($data1[2]);
            $material_in    = trim($data1[3]);
            $alloy          = trim($data1[4]);
            $primary_smelt  = trim($data1[5]);
            $secondary_smelt= trim($data1[6]);
            
            $al_remain      = floatval($data1[7]);
            $al_ingot       = floatval($data1[8]);
            $coil_remelt    = floatval($data1[9]);
            $cast_scrap     = floatval($data1[10]);
            $other_scrap    = floatval($data1[11]);
            $scrap_wire     = floatval($data1[12]);
            $recycle_scrap  = floatval($data1[13]);
            $master_alloy   = floatval($data1[14]);
            $total_charge   = floatval($data1[15]);
            $total_dross    = floatval($data1[16]);
            $balance_batch  = floatval($data1[17]);
            $ctrn_remark    = trim($data1[18]);
            
            // รับ Product No ที่ส่งมาในฟิลด์ที่ 20 (Index 19)
            $product_no     = isset($data1[19]) ? trim($data1[19]) : '';
            
            $conn->beginTransaction();
            
            // 1. Insert FRNCPRCS1
            $sql1 = "INSERT INTO FRNCPRCS1 (
                        BATCH_NO, BATCH_ITEM, BATCH_DATE, LINE_PROCESS, FURNACE, 
                        MATERIAL_IN, ALLOY, PRIMARY_SMELT, SECONDARY_SMELT, 
                        AL_REMAIN, AL_INGOT, COIL_REMELT, CAST_SCRAP, OTHER_SCRAP, 
                        SCRAP_WIRE, RECYCLE_SCRAP, MASTER_ALLOY, TOTAL_CHARGE, 
                        TOTAL_DROSS, BALANCE_BATCH, FRNC_OPERATOR1, FRNC_OPERATEDATE
                    ) VALUES (
                        :batch_no, '00', :batch_date, :line_process, :furnace,
                        :material_in, :alloy, :primary_smelt, :secondary_smelt,
                        :al_remain, :al_ingot, :coil_remelt, :cast_scrap, :other_scrap,
                        :scrap_wire, :recycle_scrap, :master_alloy, :total_charge,
                        :total_dross, :balance_batch, :operator, :operate_date
                    )";
            
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bindParam(':batch_no', $batch_no, PDO::PARAM_STR);
            $stmt1->bindParam(':batch_date', $batch_date, PDO::PARAM_STR);
            $stmt1->bindParam(':line_process', $line_process, PDO::PARAM_STR);  
            $stmt1->bindParam(':furnace', $furnace, PDO::PARAM_STR);       
            $stmt1->bindParam(':material_in', $material_in, PDO::PARAM_STR);
            $stmt1->bindParam(':alloy', $alloy, PDO::PARAM_STR);
            $stmt1->bindParam(':primary_smelt', $primary_smelt, PDO::PARAM_STR);
            $stmt1->bindParam(':secondary_smelt', $secondary_smelt, PDO::PARAM_STR);
            $stmt1->bindParam(':al_remain', $al_remain);
            $stmt1->bindParam(':al_ingot', $al_ingot);
            $stmt1->bindParam(':coil_remelt', $coil_remelt);
            $stmt1->bindParam(':cast_scrap', $cast_scrap);
            $stmt1->bindParam(':other_scrap', $other_scrap);
            $stmt1->bindParam(':scrap_wire', $scrap_wire);
            $stmt1->bindParam(':recycle_scrap', $recycle_scrap);
            $stmt1->bindParam(':master_alloy', $master_alloy);
            $stmt1->bindParam(':total_charge', $total_charge);
            $stmt1->bindParam(':total_dross', $total_dross);
            $stmt1->bindParam(':balance_batch', $balance_batch);
            $stmt1->bindParam(':operator', $operator, PDO::PARAM_STR);
            $stmt1->bindParam(':operate_date', $operateDate, PDO::PARAM_STR);
            $result1 = $stmt1->execute();
            
            // 2. Insert CHRGTRNS1
            $sql2 = "INSERT INTO CHRGTRNS1 (
                        BATCH_NO, BATCH_ITEM, CHARGE_DATE, ALLOY, 
                        ADD_INGOT, ADD_COILREMELT, ADD_CASTSCRAP, ADD_OTHERSCRAP, 
                        ADD_SCRAPWIRE, ADD_RECYCLESCRAP, ADD_MASTERALLOY, 
                        CAR_CHARGE, AL_PRODUCE, AL_DROSS, AL_CASTSCRAP, 
                        AL_TRANSFER, TRANSFER_DEGREE, CTRN_REMARK, 
                        CTRN_OPERATOR1, CTRN_OPERATEDATE
                    ) VALUES (
                        :batch_no, '00', :charge_date, :alloy,
                        :add_ingot, :add_coilremelt, :add_castscrap, :add_otherscrap,
                        :add_scrapwire, :add_recyclescrap, :add_masteralloy,
                        :car_charge, 0, :al_dross, :al_castscrap,
                        0, '', :ctrn_remark,
                        :ctrn_operator1, :ctrn_operatedate
                    )";
            
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':batch_no', $batch_no, PDO::PARAM_STR);
            $stmt2->bindParam(':charge_date', $batch_date, PDO::PARAM_STR);
            $stmt2->bindParam(':alloy', $alloy, PDO::PARAM_STR);
            $stmt2->bindParam(':add_ingot', $al_ingot);
            $stmt2->bindParam(':add_coilremelt', $coil_remelt);
            $stmt2->bindParam(':add_castscrap', $cast_scrap);
            $stmt2->bindParam(':add_otherscrap', $other_scrap);
            $stmt2->bindParam(':add_scrapwire', $scrap_wire);
            $stmt2->bindParam(':add_recyclescrap', $recycle_scrap);
            $stmt2->bindParam(':add_masteralloy', $master_alloy);
            $stmt2->bindParam(':car_charge', $total_charge);
            $stmt2->bindParam(':al_dross', $total_dross);
            $stmt2->bindParam(':al_castscrap', $cast_scrap);
            $stmt2->bindParam(':ctrn_remark', $ctrn_remark, PDO::PARAM_STR);
            $stmt2->bindParam(':ctrn_operator1', $operator, PDO::PARAM_STR);
            $stmt2->bindParam(':ctrn_operatedate', $operateDate, PDO::PARAM_STR);
            $result2 = $stmt2->execute();
            
            // -------------------------------------------------------------
            // 3. ตรวจสอบและ UPDATE 4 ตารางย่อย
            // -------------------------------------------------------------
            if(!empty($product_no)) {
                
                // 3.1 PRODRMLT2 (ใช้ MELT_STATUS, MELT_DATE, RQT2_UPDATE)
                $uSql1 = "UPDATE PRODRMLT2 
                          SET MELT_STATUS = 'MT', 
                              MELT_DATE = :operate_date, 
                              RQT2_UPDATE = :operator 
                          WHERE PRODUCT_NO = :product_no";
                $uStmt1 = $conn->prepare($uSql1);
                $uStmt1->execute([
                    ':operate_date' => $operateDate,
                    ':operator'     => $operator,
                    ':product_no'   => $product_no
                ]);

                // 3.2 COILPROD1
                $uSql2 = "UPDATE COILPROD1 
                          SET COIL_STATUS = 'MT', 
                              COIL_UPDATEDATE = :operate_date, 
                              COIL_UPDATE = :operator 
                          WHERE COIL_NO = :product_no";
                $uStmt2 = $conn->prepare($uSql2);
                $uStmt2->execute([
                    ':operate_date' => $operateDate,
                    ':operator'     => $operator,
                    ':product_no'   => $product_no
                ]);

                // 3.3 CRSHPROD1
                $uSql3 = "UPDATE CRSHPROD1 
                          SET CRSH_STATUS = 'MT', 
                              CRSH_UPDATEDATE = :operate_date, 
                              CRSH_UPDATE = :operator 
                          WHERE PRODUCT_NO = :product_no";
                $uStmt3 = $conn->prepare($uSql3);
                $uStmt3->execute([
                    ':operate_date' => $operateDate,
                    ':operator'     => $operator,
                    ':product_no'   => $product_no
                ]);

                // 3.4 PACKPROD1
                $uSql4 = "UPDATE PACKPROD1 
                          SET PACK_STATUS = 'MT', 
                              PACK_UPDATEDATE = :operate_date, 
                              PACK_UPDATE = :operator 
                          WHERE PRODUCT_NO = :product_no";
                $uStmt4 = $conn->prepare($uSql4);
                $uStmt4->execute([
                    ':operate_date' => $operateDate,
                    ':operator'     => $operator,
                    ':product_no'   => $product_no
                ]);
            }
            
            if($result1 && $result2) {
                $conn->commit();
                $response['message'] = true;
                $response['batch_no'] = $batch_no;
            } else {
                $conn->rollBack();
                $response['error'] = 'Failed to insert data into main tables';
            }
        } else {
            $response['error'] = 'Invalid data format. Expected at least 19 fields, got ' . count($data1);
        }
    } else {
        $response['error'] = 'No data received';
    }
} catch(PDOException $e) {
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
} catch(Exception $e) {
    if(isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>