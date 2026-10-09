<?php
/**
 * Model: Drop Coil Update Materials Service
 * Path: model/drop_coil_update_mats.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../dbcon_mats-new.php';

class DropCoilUpdateModel
{
    private $db;

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;
    }

    public function subStringComboBox($cmbValue)
    {
        if (empty($cmbValue)) return "";
        $parts = explode('|', $cmbValue);
        if (count($parts) > 0) {
            $subParts = explode(':', $parts[0]);
            return trim($subParts[0]);
        }
        return trim($cmbValue);
    }

    /**
     * อัปเดตข้อมูล Drop Process ลงใน CRSHPROD1, COILPROD1 และ CTSHPROD1 (UpdateDrop)
     */
    public function updateDrop($data)
    {
        try {
            $this->db->beginTransaction();

            // 1. UPDATE CRSHPROD1
            $sqlUpdateCrsh = "UPDATE CRSHPROD1 
                              SET JOB_REFERENCE = :job_reference, 
                                  PRODUCT_ID = :product_id, 
                                  PRODUCT_MODEL = :product_model, 
                                  AREA_SIZE = :area_size, 
                                  WIDTH = :width, 
                                  LENGTH = :length, 
                                  WEIGHT_PIECE = :weight_piece, 
                                  CUT_WIDTH = :cut_width, 
                                  CUT_LENGTH = :cut_length, 
                                  CUT_WEIGHTPIECE = :cut_weight_piece, 
                                  CRSH_WORKPROCESS = :work_process, 
                                  CRSH_NEXTPROCESS = :next_process, 
                                  CRSH_ACTUALWEIGHT = :produce_weight, 
                                  CRSH_ACTUALPIECE = :produce_piece, 
                                  CRSH_PRODUCEWEIGHT = :produce_weight, 
                                  CRSH_PRODUCEPIECE = :produce_piece, 
                                  CRSH_BOTTOMWEIGHT = :bottom_weight, 
                                  CRSH_STARTDATE = :start_date, 
                                  CRSH_STARTTIME = :start_time, 
                                  CRSH_ENDDATE = :end_date, 
                                  CRSH_ENDTIME = :end_time, 
                                  CRSH_LABEL = :label, 
                                  CRSH_UPDATE = :user_operator, 
                                  CRSH_UPDATEDATE = GETDATE() 
                              WHERE PRODUCT_NO = :product_no";

            $stmtCrsh = $this->db->prepare($sqlUpdateCrsh);
            $stmtCrsh->execute([
                ':job_reference'    => $data['strJobReference'],
                ':product_id'       => $data['strProductID'],
                ':product_model'    => $data['strProductModel'],
                ':area_size'        => (float)$data['dblAreaSize'],
                ':width'            => (float)$data['dblWidth'],
                ':length'           => (float)$data['dblLength'],
                ':weight_piece'     => (float)$data['dblWeightPiece'],
                ':cut_width'        => (float)$data['dblCutWidth'],
                ':cut_length'       => (float)$data['dblCutLength'],
                ':cut_weight_piece' => (float)$data['dblCutWeightPiece'],
                ':work_process'     => $data['strWorkProcess'],
                ':next_process'     => $data['strNextProcess'],
                ':produce_weight'   => (float)$data['dblProduceWeight'],
                ':produce_piece'    => (float)$data['dblProducePiece'],
                ':bottom_weight'    => (float)$data['dblBottomWeight'],
                ':start_date'       => $data['dateStartDate'],
                ':start_time'       => $data['dateStartTime'],
                ':end_date'         => $data['dateEndDate'],
                ':end_time'         => $data['dateEndTime'],
                ':label'            => (int)$data['intLabel'],
                ':user_operator'    => $data['strUserOperator1'],
                ':product_no'       => $data['strProductNo']
            ]);

            // 2. UPDATE COILPROD1 (ใช้ค่าจาก txtCOILProduceWeight และ txtCOILBalanceWeight)
            $sqlUpdateCoil = "UPDATE COILPROD1 
                              SET COIL_PRODUCEWEIGHT = :coil_produce_weight, 
                                  COIL_BALANCEWEIGHT = :coil_balance_weight 
                              WHERE COIL_NO = :coil_no";

            $stmtCoil = $this->db->prepare($sqlUpdateCoil);
            $stmtCoil->execute([
                ':coil_produce_weight' => (float)$data['dblCoilProduceWeight'],
                ':coil_balance_weight' => (float)$data['dblCoilBalanceWeight'],
                ':coil_no'             => $data['strCoilNo']
            ]);

            // 3. UPDATE CTSHPROD1 (กรณี CRSH_WORKPROCESS มีคำว่า CT)
            if (strpos($data['strWorkProcess'], 'CT') !== false) {
                $sqlUpdateCtsh = "UPDATE CTSHPROD1 
                                  SET PRODUCT_ID = :product_id, 
                                      CTSH_ACCEPTWEIGHT = :produce_weight, 
                                      CTSH_ACCEPTPIECE = :produce_piece, 
                                      CTSH_STARTDATE = :start_date, 
                                      CTSH_STARTTIME = :start_time, 
                                      CTSH_ENDDATE = :end_date, 
                                      CTSH_ENDTIME = :end_time 
                                  WHERE PRODUCT_NO = :product_no";

                $stmtCtsh = $this->db->prepare($sqlUpdateCtsh);
                $stmtCtsh->execute([
                    ':product_id'     => $data['strProductID'],
                    ':produce_weight' => (float)$data['dblProduceWeight'],
                    ':produce_piece'  => (float)$data['dblProducePiece'],
                    ':start_date'     => $data['dateStartDate'],
                    ':start_time'     => $data['dateStartTime'],
                    ':end_date'       => $data['dateEndDate'],
                    ':end_time'       => $data['dateEndTime'],
                    ':product_no'     => $data['strProductNo']
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("UpdateDrop Error: " . $e->getMessage());
            return false;
        }
    }
}

// ==============================================================================
// ส่วนรับค่า POST Request
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($conn)) {
        die(json_encode(["status" => "error", "message" => "Database connection failed"]));
    }

    $model = new DropCoilUpdateModel($conn);

    $txtWeightPiece    = (float)($_POST['txtWeightPiece'] ?? 0);
    $txtCutWeightPiece = (float)($_POST['txtCutWeightPiece'] ?? 0);
    $txtProduceWeight  = (float)($_POST['txtProduceWeight'] ?? 0);

    // ตรวจสอบเงื่อนไข Validation: WeightPiece > 0 AND CutWeightPiece > 0 AND ProduceWeight > 0
    if ($txtWeightPiece > 0 && $txtCutWeightPiece > 0 && $txtProduceWeight > 0) {

        $strProductNo = $_POST['txtProductNo'] ?? '';
        $coilNo       = $_POST['coil_no'] ?? '';
        $folderFunc   = $_SESSION['FUNC'] ?? 'pd';

        $startDT = !empty($_POST['dtpStartDate']) ? new DateTime($_POST['dtpStartDate']) : new DateTime();
        $endDT   = !empty($_POST['dtpEndDate']) ? new DateTime($_POST['dtpEndDate']) : new DateTime();

        $userSession = $_SESSION['ID'] ?? $_SESSION['USER'] ?? 'SYSTEM';

        $updateData = [
            'strProductNo'         => $strProductNo,
            'strReferenceNo'       => $_POST['txtReferenceNo'] ?? '',
            'strCoilNo'            => $coilNo,
            'strJobReference'      => $_POST['txtJobReference'] ?? '',
            'strProductID'         => $model->subStringComboBox($_POST['cmbProductID'] ?? ''),
            'strProductModel'      => $_POST['txtProductModel'] ?? '',
            'dblAreaSize'          => $_POST['dblAreaSize'] ?? 0,
            'dblWidth'             => $_POST['txtWidth'] ?? 0,
            'dblLength'            => $_POST['txtLength'] ?? 0,
            'dblWeightPiece'       => $txtWeightPiece,
            'dblCutWidth'          => $_POST['txtCutWidth'] ?? 0,
            'dblCutLength'         => $_POST['txtCutLength'] ?? 0,
            'dblCutWeightPiece'    => $txtCutWeightPiece,
            'strWorkProcess'       => $_POST['cmbWorkProcess'] ?? 'CL>DP',
            'strNextProcess'       => $_POST['txtNextProcess'] ?? 'DP',
            'dblProduceWeight'     => $txtProduceWeight,
            'dblProducePiece'      => $_POST['txtProducePiece'] ?? 0,
            'dblBottomWeight'      => $_POST['txtBottomWeight'] ?? 0,
            'dateStartDate'        => $startDT->format('Y-m-d'),
            'dateStartTime'        => $startDT->format('H:i:s'),
            'dateEndDate'          => $endDT->format('Y-m-d'),
            'dateEndTime'          => $endDT->format('H:i:s'),
            'intLabel'             => $_POST['txtCRSHLabel'] ?? 1,
            // ดึงข้อมูล Coil Total Produce Weight และ Coil Balance Weight จากฟิลด์หน้าบ้าน
            'dblCoilProduceWeight' => $_POST['txtCOILProduceWeight'] ?? 0, 
            'dblCoilBalanceWeight' => $_POST['txtCOILBalanceWeight'] ?? 0, 
            'strUserOperator1'     => $userSession
        ];

        $result = $model->updateDrop($updateData);

        $redirectUrl = "../drop_product_result_mats.php?func=" . urlencode($folderFunc) . "&search_no=" . urlencode($strProductNo);

        if ($result) {
            echo json_encode([
                "status"     => "success",
                "message"    => "อัปเดตข้อมูล Drop Process สำเร็จ",
                "product_no" => $strProductNo,
                "redirect"   => $redirectUrl
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการอัปเดตข้อมูลลงฐานข้อมูล"]);
        }

    } else {
        echo json_encode([
            "status"  => "error",
            "message" => "เงื่อนไขไม่ผ่าน: WeightPiece, CutWeightPiece และ ProduceWeight ต้องมีค่ามากกว่า 0"
        ]);
    }
}