<?php
/**
 * Model: Drop Coil Save Materials Service
 * Path: model/drop_coil_save_mats.php
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/../dbcon_mats-new.php';

class DropCoilModel
{
    private $db;

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;
    }

    /**
     * ดึง Running Production No รูปแบบ PYYMMDD-P-XX จากตาราง CRSHPROD1
     */
    public function generateProductNo()
    {
        $prefix = "P" . date("ymd") . "-P-"; // รูปแบบ P261009-P-
        
        $sql = "SELECT MAX(PRODUCT_NO) AS max_no 
                FROM CRSHPROD1 
                WHERE PRODUCT_NO LIKE :prefix";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['max_no'])) {
            $lastSeq = (int)substr($row['max_no'], -2);
            $newSeq = $lastSeq + 1;
        } else {
            $newSeq = 1;
        }

        return $prefix . str_pad($newSeq, 2, '0', STR_PAD_LEFT);
    }

    /**
     * ดึงข้อมูล Spec ของ COIL จาก COILPROD1 ตาม COIL_NO
     */
    public function getCoilInfo($coilNo)
    {
        $sql = "SELECT MATERIAL_IN, F_ALLOY, F_TEMPER, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE 
                FROM COILPROD1 
                WHERE COIL_NO = :coil_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':coil_no' => $coilNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * แยกค่า Product ID ออกจาก ComboBox Value (เช่น "CC" จาก "CC|CIRCLE")
     */
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
     * บันทึกข้อมูล Drop Process ลงฐานข้อมูล (AddDrop)
     */
    public function addDrop($data)
    {
        try {
            $this->db->beginTransaction();

            // ดึงข้อมูล Spec ของ COIL มาใช้อัตโนมัติ
            $coilSpec = $this->getCoilInfo($data['strCoilNo']);

            $materialIn = !empty($data['strMaterialIn']) ? $data['strMaterialIn'] : ($coilSpec['MATERIAL_IN'] ?? '');
            $fAlloy     = !empty($coilSpec['F_ALLOY']) ? $coilSpec['F_ALLOY'] : ($data['strAlloy'] ?? '');
            $fTemper    = !empty($coilSpec['F_TEMPER']) ? $coilSpec['F_TEMPER'] : ($data['strTemper'] ?? '');
            $alloy      = !empty($coilSpec['ALLOY']) ? $coilSpec['ALLOY'] : ($data['strAlloy'] ?? '');
            $temper     = !empty($coilSpec['TEMPER']) ? $coilSpec['TEMPER'] : ($data['strTemper'] ?? '');
            $grade      = !empty($coilSpec['GRADE']) ? $coilSpec['GRADE'] : ($data['strGrade'] ?? '');
            $sg         = !empty($coilSpec['SURFACE_GRADE']) ? $coilSpec['SURFACE_GRADE'] : ($data['strSG'] ?? '');
            $mg         = !empty($coilSpec['METALLURGICAL_GRADE']) ? $coilSpec['METALLURGICAL_GRADE'] : ($data['strMG'] ?? '');

            // 1. INSERT INTO CRSHPROD1
            $sqlInsert = "INSERT INTO CRSHPROD1 (
                PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, JOB_REFERENCE, MATERIAL_IN, 
                PRODUCT_ID, PRODUCT_MODEL, AREA_SIZE, ORG_LINEPROCESS, LINE_PROCESS, 
                LOCATION_ID, F_ALLOY, F_TEMPER, F_WIDTH, F_LENGTH, 
                ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE, 
                T5_TEMPERATURE, THICKNESS, WIDTH, LENGTH, CRSH_UOMDIMENSION, 
                WEIGHT_PIECE, CUT_WIDTH, CUT_LENGTH, CUT_WEIGHTPIECE, CRSH_WORKPROCESS, 
                CRSH_NEXTPROCESS, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT, CRSH_PRODUCEPIECE, 
                CRSH_BOTTOMWEIGHT, CRSH_CUTSHEETWEIGHT, CRSH_UOMWEIGHT, CRSH_STARTDATE, CRSH_STARTTIME, 
                CRSH_ENDDATE, CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1, CRSH_OPERATEDATE, CRSH_STATUS
            ) VALUES (
                :product_no, :reference_no, :coil_no, :job_reference, :material_in,
                :product_id, :product_model, :area_size, 'P', 'P',
                '', :f_alloy, :f_temper, :width, :length,
                :alloy, :temper, :grade, :sg, :mg,
                :t5_temp, :thickness, :width, :length, 'mm.',
                :weight_piece, :cut_width, :cut_length, :cut_weight_piece, :work_process,
                :next_process, :produce_weight, :produce_piece, :produce_weight, :produce_piece,
                :bottom_weight, 0, 'kg.', :start_date, :start_time,
                :end_date, :end_time, :label, :operator1, GETDATE(), 'OP'
            )";

            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->execute([
                ':product_no'       => $data['strProductNo'],
                ':reference_no'     => $data['strReferenceNo'],
                ':coil_no'          => $data['strCoilNo'],
                ':job_reference'    => $data['strJobReference'],
                ':material_in'      => $materialIn,
                ':product_id'       => $data['strProductID'],
                ':product_model'    => $data['strProductModel'],
                ':area_size'        => (float)$data['dblAreaSize'],
                ':f_alloy'          => $fAlloy,
                ':f_temper'         => $fTemper,
                ':width'            => (float)$data['dblWidth'],
                ':length'           => (float)$data['dblLength'],
                ':alloy'            => $alloy,
                ':temper'           => $temper,
                ':grade'            => $grade,
                ':sg'               => $sg,
                ':mg'               => $mg,
                ':t5_temp'          => $data['strT5Temperature'],
                ':thickness'        => (float)$data['dblThickness'],
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
                ':operator1'        => $data['strUserOperator1']
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

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("AddDrop Error: " . $e->getMessage());
            return false;
        }
    }
}

// ==============================================================================
// ส่วนการรับค่า REQUEST POST
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($conn)) {
        die(json_encode(["status" => "error", "message" => "Database connection failed"]));
    }

    $model = new DropCoilModel($conn);

    $txtWeightPiece     = (float)($_POST['txtWeightPiece'] ?? 0);
    $txtCutWeightPiece  = (float)($_POST['txtCutWeightPiece'] ?? 0);
    $txtProduceWeight   = (float)($_POST['txtProduceWeight'] ?? 0);

    // ตรวจสอบเงื่อนไข: WeightPiece > 0 AND CutWeightPiece > 0 AND ProduceWeight > 0
    if ($txtWeightPiece > 0 && $txtCutWeightPiece > 0 && $txtProduceWeight > 0) {
        
        $strProductNo = $model->generateProductNo();
        $coilNo       = $_POST['coil_no'] ?? '';
        $folderFunc   = $_SESSION['FUNC'] ?? 'pd';

        $startDT = !empty($_POST['dtpStartDate']) ? new DateTime($_POST['dtpStartDate']) : new DateTime();
        $endDT   = !empty($_POST['dtpEndDate']) ? new DateTime($_POST['dtpEndDate']) : new DateTime();

        $userSession = $_SESSION['ID'] ?? $_SESSION['USER'] ?? 'SYSTEM';

        $dropData = [
            'strProductNo'          => $strProductNo,
            'strReferenceNo'        => $_POST['txtReferenceNo'] ?? '',
            'strCoilNo'             => $coilNo,
            'strJobReference'       => $_POST['txtJobReference'] ?? '',
            'strMaterialIn'         => $_POST['txtMaterialIN'] ?? '',
            'strProductID'          => $model->subStringComboBox($_POST['cmbProductID'] ?? ''),
            'strProductModel'       => $_POST['txtProductModel'] ?? '',
            'dblAreaSize'           => $_POST['dblAreaSize'] ?? 0,
            'strAlloy'              => $_POST['txtCOILAlloy'] ?? '',
            'strTemper'             => $_POST['txtCoilTemper'] ?? '',
            'strGrade'              => $_POST['txtCoilGrade'] ?? '',
            'strSG'                 => $_POST['txtCOILSG'] ?? '',
            'strMG'                 => $_POST['txtCOILMG'] ?? '',
            'strT5Temperature'      => $_POST['txtCOILT5Temperature'] ?? '',
            'dblThickness'          => $_POST['txtCOILThickness'] ?? 0,
            'dblWidth'              => $_POST['txtWidth'] ?? 0,
            'dblLength'             => $_POST['txtLength'] ?? 0,
            'dblWeightPiece'        => $txtWeightPiece,
            'dblCutWidth'           => $_POST['txtCutWidth'] ?? 0,
            'dblCutLength'          => $_POST['txtCutLength'] ?? 0,
            'dblCutWeightPiece'     => $txtCutWeightPiece,
            'strWorkProcess'        => $_POST['cmbWorkProcess'] ?? 'CL>DP',
            'strNextProcess'        => $_POST['txtNextProcess'] ?? 'DP',
            'dblProduceWeight'      => $txtProduceWeight,
            'dblProducePiece'       => $_POST['txtProducePiece'] ?? 0,
            'dblBottomWeight'       => $_POST['txtBottomWeight'] ?? 0,
            'dateStartDate'         => $startDT->format('Y-m-d'),
            'dateStartTime'         => $startDT->format('H:i:s'),
            'dateEndDate'           => $endDT->format('Y-m-d'),
            'dateEndTime'           => $endDT->format('H:i:s'),
            'intLabel'              => $_POST['txtCRSHLabel'] ?? 1,
            'dblCoilProduceWeight'  => $_POST['txtCOILProduceWeight'] ?? 0, // ดึงจากหน้า drop_coil_process_mats
            'dblCoilBalanceWeight'  => $_POST['txtCOILBalanceWeight'] ?? 0, // ดึงจากหน้า drop_coil_process_mats
            'strUserOperator1'      => $userSession
        ];

        $result = $model->addDrop($dropData);

        $redirectUrl = "../drop_product_result_mats.php?func=" . urlencode($folderFunc) . "&search_no=" . urlencode($strProductNo);

        if ($result) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode([
                    "status"     => "success", 
                    "message"    => "บันทึกข้อมูลสำเร็จ", 
                    "product_no" => $strProductNo,
                    "redirect"   => $redirectUrl
                ]);
            } else {
                header("Location: " . $redirectUrl);
                exit();
            }
        } else {
            echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล"]);
        }

    } else {
        echo json_encode([
            "status"  => "error", 
            "message" => "เงื่อนไขไม่ผ่าน: WeightPiece, CutWeightPiece และ ProduceWeight ต้องมีค่ามากกว่า 0"
        ]);
    }
}