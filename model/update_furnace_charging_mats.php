<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include('../dbcon_mats-new.php');

$response = array();

if (isset($_POST['data_tag1'])) {
    $data_arr = explode("*", $_POST['data_tag1']);
    
    $batchNo        = $data_arr[0];
    $chargingDate   = $data_arr[1];
    $furnaceNo      = $data_arr[2];
    $material       = $data_arr[3];
    $alloy          = $data_arr[4];
    $primarySmelt   = $data_arr[5];
    $secondaySmelt  = $data_arr[6];
    $remainForm     = (float)$data_arr[7];
    $chargeIngot    = (float)$data_arr[8];
    $chargeRemelt   = (float)$data_arr[9];
    $chargeCasting  = (float)$data_arr[10];
    $chargeOther    = (float)$data_arr[11];
    $chargeWire     = (float)$data_arr[12];
    $chargeRecycle  = (float)$data_arr[13];
    $chargeAlloy    = (float)$data_arr[14];
    $totalCharge    = (float)$data_arr[15];
    $totalDross     = (float)$data_arr[16];
    $balanceFurnace = (float)$data_arr[17];
    $remark         = isset($data_arr[18]) ? $data_arr[18] : '';
    
    $user_id = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';
    $update_date = date('Y-m-d H:i:s');

    try {
        $conn->beginTransaction();

        // 1. UPDATE ตาราง FRNCPRCS1
        $sql_frnc = "UPDATE FRNCPRCS1 SET 
                        PRIMARY_SMELT = :primarySmelt,
                        SECONDARY_SMELT = :secondaySmelt,
                        MATERIAL_IN = :material,
                        ALLOY = :alloy,
                        AL_INGOT = :chargeIngot,
                        COIL_REMELT = :chargeRemelt,
                        CAST_SCRAP = :chargeCasting,
                        OTHER_SCRAP = :chargeOther,
                        SCRAP_WIRE = :chargeWire,
                        RECYCLE_SCRAP = :chargeRecycle,
                        MASTER_ALLOY = :chargeAlloy,
                        AL_REMAIN = :remainForm,
                        TOTAL_CHARGE = :totalCharge,
                        TOTAL_DROSS = :totalDross,
                        BALANCE_BATCH = :balanceFurnace,
                        FRNC_UPDATE = :user_id,
                        FRNC_UPDATEDATE = :update_date
                    WHERE BATCH_NO = :batchNo";

        $stmt1 = $conn->prepare($sql_frnc);
        $stmt1->execute([
            ':primarySmelt'  => $primarySmelt,
            ':secondaySmelt' => $secondaySmelt,
            ':material'      => $material,
            ':alloy'         => $alloy,
            ':chargeIngot'   => $chargeIngot,
            ':chargeRemelt'  => $chargeRemelt,
            ':chargeCasting' => $chargeCasting,
            ':chargeOther'   => $chargeOther,
            ':chargeWire'    => $chargeWire,
            ':chargeRecycle' => $chargeRecycle,
            ':chargeAlloy'   => $chargeAlloy,
            ':remainForm'    => $remainForm,
            ':totalCharge'   => $totalCharge,
            ':totalDross'    => $totalDross,
            ':balanceFurnace'=> $balanceFurnace,
            ':user_id'       => $user_id,
            ':update_date'   => $update_date,
            ':batchNo'       => $batchNo
        ]);

        // 2. UPDATE ตาราง CHRGTRNS1
        $sql_chrg = "UPDATE CHRGTRNS1 SET 
                        ALLOY = :alloy,
                        ADD_INGOT = :chargeIngot,
                        ADD_COILREMELT = :chargeRemelt,
                        ADD_CASTSCRAP = :chargeCasting,
                        ADD_OTHERSCRAP = :chargeOther,
                        ADD_SCRAPWIRE = :chargeWire,
                        ADD_RECYCLESCRAP = :chargeRecycle,
                        ADD_MASTERALLOY = :chargeAlloy,
                        AL_DROSS = :totalDross,
                        CTRN_REMARK = :remark,
                        CTRN_UPDATE = :user_id,
                        CTRN_UPDATEDATE = :update_date
                    WHERE BATCH_NO = :batchNo";

        $stmt2 = $conn->prepare($sql_chrg);
        $stmt2->execute([
            ':alloy'         => $alloy,
            ':chargeIngot'   => $chargeIngot,
            ':chargeRemelt'  => $chargeRemelt,
            ':chargeCasting' => $chargeCasting,
            ':chargeOther'   => $chargeOther,
            ':chargeWire'    => $chargeWire,
            ':chargeRecycle' => $chargeRecycle,
            ':chargeAlloy'   => $chargeAlloy,
            ':totalDross'    => $totalDross,
            ':remark'        => $remark,
            ':user_id'       => $user_id,
            ':update_date'   => $update_date,
            ':batchNo'       => $batchNo
        ]);

        $conn->commit();
        $response['message'] = true;

    } catch (Exception $e) {
        $conn->rollBack();
        $response['message'] = false;
        $response['error'] = $e->getMessage();
    }
} else {
    $response['message'] = false;
    $response['error'] = "Invalid Data Tag";
}

echo json_encode($response);