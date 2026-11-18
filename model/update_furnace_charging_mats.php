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

    if (isset($_POST['data_tag1']) && !empty($_POST['data_tag1'])) {
        $data_arr = explode("*", $_POST['data_tag1']);
        
        if (count($data_arr) >= 18) {
            $batchNo        = trim($data_arr[0]);
            $chargingDate   = trim($data_arr[1]);
            $furnaceNo      = trim($data_arr[2]);
            $material       = trim($data_arr[3]);
            $alloy          = trim($data_arr[4]);
            $primarySmelt   = trim($data_arr[5]);
            $secondaySmelt  = trim($data_arr[6]);
            
            $remainForm     = floatval($data_arr[7]);
            $chargeIngot    = floatval($data_arr[8]);
            $chargeRemelt   = floatval($data_arr[9]);
            $chargeCasting  = floatval($data_arr[10]);
            $chargeOther    = floatval($data_arr[11]);
            $chargeWire     = floatval($data_arr[12]);
            $chargeRecycle  = floatval($data_arr[13]);
            $chargeAlloy    = floatval($data_arr[14]);
            $totalCharge    = floatval($data_arr[15]);
            $totalDross     = floatval($data_arr[16]);
            $balanceFurnace = floatval($data_arr[17]);
            $remark         = isset($data_arr[18]) ? trim($data_arr[18]) : '';
            
            $user_id        = isset($_SESSION['ID']) ? $_SESSION['ID'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 'SYSTEM');
            $update_date    = date('Y-m-d H:i:s');

            $ingot_items_raw = isset($_POST['ingot_items']) ? trim($_POST['ingot_items']) : '';
            $ingot_ids = [];
            if (!empty($ingot_items_raw)) {
                $decoded = json_decode($ingot_items_raw, true);
                if (is_array($decoded)) {
                    $ingot_ids = array_map('intval', $decoded);
                }
            }

            $conn->beginTransaction();

            // STEP 1: คืนสถานะ REQUISITION_DETAIL และลบ WIP_ISSUE_TRANSACTIONS เก่า
            $sqlOldIssues = "SELECT container_no, packing_no FROM WIP_ISSUE_TRANSACTIONS WHERE issue_no = :batchNo";
            $stmtOld = $conn->prepare($sqlOldIssues);
            $stmtOld->execute([':batchNo' => $batchNo]);
            $oldItems = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($oldItems)) {
                $sqlUpdateRevert = "UPDATE REQUISITION_DETAIL 
                                    SET status = 'Issued', 
                                        update_at = :update_date 
                                    WHERE LTRIM(RTRIM(container_no)) = LTRIM(RTRIM(:container_no)) 
                                      AND LTRIM(RTRIM(packing_no)) = LTRIM(RTRIM(:packing_no))";
                $stmtRevert = $conn->prepare($sqlUpdateRevert);

                foreach ($oldItems as $old) {
                    $stmtRevert->execute([
                        ':update_date'  => $update_date,
                        ':container_no' => $old['container_no'],
                        ':packing_no'   => $old['packing_no']
                    ]);
                }
            }

            $sqlDeleteOldIssue = "DELETE FROM WIP_ISSUE_TRANSACTIONS WHERE issue_no = :batchNo";
            $stmtDelIssue = $conn->prepare($sqlDeleteOldIssue);
            $stmtDelIssue->execute([':batchNo' => $batchNo]);

            // STEP 2: บันทึก WIP Ingot ชุดใหม่
            if (!empty($ingot_ids)) {
                $inQuery = implode(',', array_fill(0, count($ingot_ids), '?'));
                $sqlGetWip = "SELECT 
                                d.req_detail_id, 
                                d.container_no, 
                                d.packing_no, 
                                d.net_weight_kg, 
                                d.piece_count,
                                rh.material_id, 
                                rh.materials_no, 
                                rh.pack_list_no, 
                                rh.invoice_no, 
                                rh.supplier_id, 
                                rh.country_of_origin
                              FROM REQUISITION_DETAIL d
                              LEFT JOIN RECEIVING_HEADER rh 
                                     ON LTRIM(RTRIM(d.container_no)) = LTRIM(RTRIM(rh.container_no))
                              WHERE d.req_detail_id IN ($inQuery)";
                $stmtGetWip = $conn->prepare($sqlGetWip);
                $stmtGetWip->execute($ingot_ids);
                $wipItems = $stmtGetWip->fetchAll(PDO::FETCH_ASSOC);

                $sqlIssueNew = "INSERT INTO WIP_ISSUE_TRANSACTIONS (
                                    issue_no, container_no, packing_no, material_id, materials_no, 
                                    pack_list_no, invoice_no, supplier_id, country_of_origin,
                                    net_weight_kg, piece_count, issued_by, issued_date, remarks, 
                                    is_checked_out, checked_out_date
                                ) VALUES (
                                    :issue_no, :container_no, :packing_no, :material_id, :materials_no, 
                                    :pack_list_no, :invoice_no, :supplier_id, :country_of_origin,
                                    :net_weight_kg, :piece_count, :issued_by, :issued_date, :remarks, 
                                    :is_checked_out, :checked_out_date
                                )";
                $stmtIssueNew = $conn->prepare($sqlIssueNew);

                $sqlUpdateReqNew = "UPDATE REQUISITION_DETAIL 
                                    SET status = 'Use', 
                                        update_at = :update_date 
                                    WHERE req_detail_id = :req_detail_id";
                $stmtUpdateReqNew = $conn->prepare($sqlUpdateReqNew);

                foreach ($wipItems as $item) {
                    $issue_remark = "Updated Charge into Furnace: " . $batchNo;
                    
                    $stmtIssueNew->execute([
                        ':issue_no'          => $batchNo,
                        ':container_no'      => $item['container_no'],
                        ':packing_no'        => $item['packing_no'],
                        ':material_id'       => !empty($item['material_id']) ? $item['material_id'] : null,
                        ':materials_no'      => !empty($item['materials_no']) ? $item['materials_no'] : null,
                        ':pack_list_no'      => !empty($item['pack_list_no']) ? $item['pack_list_no'] : null,
                        ':invoice_no'        => !empty($item['invoice_no']) ? $item['invoice_no'] : null,
                        ':supplier_id'       => !empty($item['supplier_id']) ? $item['supplier_id'] : null,
                        ':country_of_origin' => !empty($item['country_of_origin']) ? $item['country_of_origin'] : null,
                        ':net_weight_kg'     => $item['net_weight_kg'],
                        ':piece_count'       => $item['piece_count'],
                        ':issued_by'         => $user_id,
                        ':issued_date'       => $update_date,
                        ':remarks'           => $issue_remark,
                        ':is_checked_out'    => $update_date,
                        ':checked_out_date'  => $update_date
                    ]);

                    $stmtUpdateReqNew->execute([
                        ':update_date'   => $update_date,
                        ':req_detail_id' => $item['req_detail_id']
                    ]);
                }
            }

            // STEP 3: UPDATE ข้อมูลตารางหลัก FRNCPRCS1 และ CHRGTRNS1 (รวม SECONDARY_SMELT)
            $sql_frnc = "UPDATE FRNCPRCS1 SET 
                            PRIMARY_SMELT   = :primarySmelt,
                            SECONDARY_SMELT = :secondaySmelt,
                            MATERIAL_IN     = :material,
                            ALLOY           = :alloy,
                            AL_INGOT        = :chargeIngot,
                            COIL_REMELT     = :chargeRemelt,
                            CAST_SCRAP      = :chargeCasting,
                            OTHER_SCRAP     = :chargeOther,
                            SCRAP_WIRE      = :chargeWire,
                            RECYCLE_SCRAP   = :chargeRecycle,
                            MASTER_ALLOY    = :chargeAlloy,
                            AL_REMAIN       = :remainForm,
                            TOTAL_CHARGE    = :totalCharge,
                            TOTAL_DROSS     = :totalDross,
                            BALANCE_BATCH   = :balanceFurnace,
                            FRNC_UPDATE     = :user_id,
                            FRNC_UPDATEDATE = :update_date
                        WHERE BATCH_NO = :batchNo";

            $stmt1 = $conn->prepare($sql_frnc);
            $stmt1->execute([
                ':primarySmelt'   => $primarySmelt,
                ':secondaySmelt'  => $secondaySmelt,
                ':material'       => $material,
                ':alloy'          => $alloy,
                ':chargeIngot'    => $chargeIngot,
                ':chargeRemelt'   => $chargeRemelt,
                ':chargeCasting'  => $chargeCasting,
                ':chargeOther'    => $chargeOther,
                ':chargeWire'     => $chargeWire,
                ':chargeRecycle'  => $chargeRecycle,
                ':chargeAlloy'    => $chargeAlloy,
                ':remainForm'     => $remainForm,
                ':totalCharge'    => $totalCharge,
                ':totalDross'     => $totalDross,
                ':balanceFurnace' => $balanceFurnace,
                ':user_id'        => $user_id,
                ':update_date'    => $update_date,
                ':batchNo'        => $batchNo
            ]);

            $sql_chrg = "UPDATE CHRGTRNS1 SET 
                            ALLOY            = :alloy,
                            ADD_INGOT        = :chargeIngot,
                            ADD_COILREMELT   = :chargeRemelt,
                            ADD_CASTSCRAP    = :chargeCasting,
                            ADD_OTHERSCRAP   = :chargeOther,
                            ADD_SCRAPWIRE    = :chargeWire,
                            ADD_RECYCLESCRAP = :chargeRecycle,
                            ADD_MASTERALLOY  = :chargeAlloy,
                            CAR_CHARGE       = :carCharge,
                            AL_DROSS         = :totalDross,
                            AL_CASTSCRAP     = :alCastScrap,
                            CTRN_REMARK      = :remark,
                            CTRN_UPDATE      = :user_id,
                            CTRN_UPDATEDATE  = :update_date
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
                ':carCharge'     => $totalCharge,
                ':totalDross'    => $totalDross,
                ':alCastScrap'   => $chargeCasting,
                ':remark'        => $remark,
                ':user_id'       => $user_id,
                ':update_date'   => $update_date,
                ':batchNo'        => $batchNo
            ]);

            $conn->commit();
            $response['message'] = true;

        } else {
            $response['error'] = 'Invalid data format. Expected at least 18 fields.';
        }
    } else {
        $response['error'] = 'No data received.';
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['message'] = false;
    $response['error'] = 'Database Error: ' . $e->getMessage();
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['message'] = false;
    $response['error'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>