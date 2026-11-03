<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../combining_process_mats.php");
    exit();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include("../dbcon_mats-new.php");

$user_operator = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'SYSTEM';
$folder_func   = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : 'pd';

$job_order      = trim($_POST['job_order'] ?? '');
$saleorder_no   = trim($_POST['saleorder_no'] ?? '');
$saleorder_item = trim($_POST['saleorder_item'] ?? '');
$material_in    = trim($_POST['material_in'] ?? '');
$alloy          = trim($_POST['alloy'] ?? '');
$work_process   = trim($_POST['work_process'] ?? '');
$combine_no     = trim($_POST['combine_no'] ?? '');
$product_no     = trim($_POST['product_no'] ?? '');

$scrap_weight   = floatval($_POST['scrap_weight'] ?? 0);
$bottom_weight  = floatval($_POST['bottom_weight'] ?? 0);
$gross_weight   = floatval($_POST['gross_weight'] ?? 0);
$net_weight     = floatval($_POST['net_weight'] ?? 0);

$crsh_status    = ($net_weight > 0) ? 'ST' : 'AC';
$product_id     = 'WR';
$product_model  = 'WRAP';
$location_id    = '1'; 

if (empty($job_order) || empty($combine_no)) {
    echo "<script>alert('Error: กรุณาระบุข้อมูล Scrap ให้ครบถ้วน'); window.history.back();</script>";
    exit();
}

try {
    $conn->beginTransaction();

    // 1. ตรวจสอบ Scrap ซ้ำ
    $chk_cmbn = $conn->prepare("SELECT PRODUCT_NO FROM CMBNPROD1 WHERE PRODUCT_REFERENCE = ?");
    $chk_cmbn->execute([$combine_no]);
    if ($chk_cmbn->fetch()) {
        throw new Exception("Scrap รายการนี้ถูก Combine ไปแล้ว ไม่สามารถทำซ้ำได้");
    }

    // 2. ดึงข้อมูล Scrap และ PO No / PO Item จาก CSTMORDR2 (ถ้ามี)
    $stmt_scrap = $conn->prepare("SELECT COIL_NO, ALLOY, SCRP_STATUS, SCRP_WEIGHT FROM SCRPPROD1 WHERE PRODUCT_NO = ?");
    $stmt_scrap->execute([$combine_no]);
    $scrap_data = $stmt_scrap->fetch(PDO::FETCH_ASSOC);

    if (!$scrap_data || $scrap_data['SCRP_STATUS'] !== 'OP') {
        throw new Exception("สถานะของ Scrap ไม่ถูกต้อง (ต้องเป็น OP เท่านั้น)");
    }

    $coil_no = $scrap_data['COIL_NO'] ?? '';
    if ($scrap_weight <= 0) {
        $scrap_weight = floatval($scrap_data['SCRP_WEIGHT'] ?? 0);
    }

    // ดึง PO No และ PO Item
    $pack_pono = '';
    $pack_poitem = '';
    if (!empty($saleorder_no)) {
        $stmt_po = $conn->prepare("SELECT CTM2_PONO, CTM2_POITEM FROM CSTMORDR2 WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?");
        $stmt_po->execute([$saleorder_no, $saleorder_item]);
        $po_data = $stmt_po->fetch(PDO::FETCH_ASSOC);
        if ($po_data) {
            $pack_pono = $po_data['CTM2_PONO'] ?? '';
            $pack_poitem = $po_data['CTM2_POITEM'] ?? '';
        }
    }

    // 3. Gen Product No. สดๆ ภายใน Transaction (ป้องกันผู้ใช้งานพร้อมกันหลายคน)
    if (empty($product_no)) {
        $date_prefix = 'P' . date('ymd') . '-W-';
        $sql_gen = "SELECT TOP 1 PRODUCT_NO FROM CRSHPROD1 WITH (UPDLOCK, HOLDLOCK) WHERE PRODUCT_NO LIKE :prefix ORDER BY PRODUCT_NO DESC";
        $stmt_gen = $conn->prepare($sql_gen);
        $stmt_gen->execute([':prefix' => $date_prefix . '%']);
        $row_gen = $stmt_gen->fetch(PDO::FETCH_ASSOC);

        $next_running = 1;
        if ($row_gen && !empty($row_gen['PRODUCT_NO'])) {
            $parts = explode('-', $row_gen['PRODUCT_NO']);
            $last_seq = end($parts);
            if (is_numeric($last_seq)) {
                $next_running = intval($last_seq) + 1;
            }
        }
        $product_no = $date_prefix . str_pad($next_running, 2, '0', STR_PAD_LEFT);
    }

    // 4. บันทึก CMBNPROD1[cite: 10]
    $sql_cmbn = "INSERT INTO CMBNPROD1 (PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, MATERIAL_IN, CMBN_DATE, CMBN_PIECE, CMBN_WEIGHT, CMBN_OPERATOR) 
                 VALUES (?, ?, ?, ?, GETDATE(), 1, ?, ?)";
    $conn->prepare($sql_cmbn)->execute([
        $product_no, $combine_no, $coil_no, $material_in, $scrap_weight, $user_operator
    ]);

    // 5. บันทึกหรืออัปเดต CRSHPROD1[cite: 15, 16, 17, 18, 19]
    $chk_crsh = $conn->prepare("SELECT PRODUCT_NO FROM CRSHPROD1 WHERE PRODUCT_NO = ?");
    $chk_crsh->execute([$product_no]);
    if ($chk_crsh->fetch()) {
        $conn->prepare("UPDATE CRSHPROD1 SET 
            CRSH_ACTUALWEIGHT = ISNULL(CRSH_ACTUALWEIGHT,0) + ?, 
            CRSH_PRODUCEWEIGHT = ISNULL(CRSH_PRODUCEWEIGHT,0) + ?, 
            CRSH_PACKWEIGHT = ISNULL(CRSH_PACKWEIGHT,0) + ?, 
            CRSH_BOTTOMWEIGHT = ?, 
            CRSH_STATUS = ? 
            WHERE PRODUCT_NO = ?")
             ->execute([$scrap_weight, $scrap_weight, $net_weight, $bottom_weight, $crsh_status, $product_no]);
    } else {
        $sql_crsh = "INSERT INTO CRSHPROD1 (
            PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, 
            PRODUCT_ID, PRODUCT_MODEL, LOCATION_ID, ORG_LINEPROCESS, LINE_PROCESS, 
            F_ALLOY, F_TEMPER, F_WIDTH, F_LENGTH, ALLOY, TEMPER, GRADE, SURFACE_GRADE, METALLURGICAL_GRADE,
            CRSH_WORKPROCESS, CRSH_NEXTPROCESS, CRSH_ACTUALWEIGHT, CRSH_ACTUALPIECE, CRSH_PRODUCEWEIGHT, 
            CRSH_PRODUCEPIECE, CRSH_BOTTOMWEIGHT, CRSH_PACKWEIGHT, CRSH_STARTDATE, CRSH_STARTTIME, 
            CRSH_ENDDATE, CRSH_ENDTIME, CRSH_LABEL, CRSH_OPERATOR1, CRSH_OPERATEDATE, CRSH_STATUS
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, 'W', 'W', 
            ?, '', 0, 0, ?, '', '', '', '',
            ?, 'IS', ?, 1, ?, 
            1, ?, ?, GETDATE(), GETDATE(), 
            GETDATE(), GETDATE(), 1, ?, GETDATE(), ?
        )";
        $conn->prepare($sql_crsh)->execute([
            $product_no, $combine_no, $coil_no, $saleorder_no, $saleorder_item, $job_order, $material_in,
            $product_id, $product_model, $location_id, 
            $alloy, $alloy, $work_process, $scrap_weight, $scrap_weight, $bottom_weight, $net_weight,
            $user_operator, $crsh_status
        ]);
    }

    // 6. อัปเดต SCRPPROD1 เป็น 'CB'
    $conn->prepare("UPDATE SCRPPROD1 SET SCRP_STATUS = 'CB', SCRP_UPDATE = ?, SCRP_UPDATEDATE = GETDATE() WHERE PRODUCT_NO = ?")
         ->execute([$user_operator, $combine_no]);

    // 7. อัปเดต JOBORDER1
    $conn->prepare("UPDATE JOBORDER1 SET 
        JOB_ACTUALWEIGHT = ISNULL(JOB_ACTUALWEIGHT,0) + ?, 
        JOB_ACTUALPIECE = ISNULL(JOB_ACTUALPIECE,0) + 1, 
        JOB_PRODUCEWEIGHT = ISNULL(JOB_PRODUCEWEIGHT,0) + ?, 
        JOB_PRODUCEPIECE = ISNULL(JOB_PRODUCEPIECE,0) + 1, 
        JOB_PACKWEIGHT = ISNULL(JOB_PACKWEIGHT,0) + ?, 
        JOB_PACKPIECE = ISNULL(JOB_PACKPIECE,0) + 1, 
        JOB_STOCKWEIGHT = ISNULL(JOB_STOCKWEIGHT,0) + ?, 
        JOB_STOCKPIECE = ISNULL(JOB_STOCKPIECE,0) + 1 
        WHERE JOB_ORDER = ?")
         ->execute([$scrap_weight, $scrap_weight, $net_weight, $net_weight, $job_order]);

    // 8. อัปเดต CSTMORDR2 (ถ้ามี Sale Order)
    if (!empty($saleorder_no)) {
        $conn->prepare("UPDATE CSTMORDR2 SET 
            CTM2_ACTUALWEIGHT = ISNULL(CTM2_ACTUALWEIGHT,0) + ?, 
            CTM2_ACTUALPIECE = ISNULL(CTM2_ACTUALPIECE,0) + 1, 
            CTM2_PRODUCEWEIGHT = ISNULL(CTM2_PRODUCEWEIGHT,0) + ?, 
            CTM2_PRODUCEPIECE = ISNULL(CTM2_PRODUCEPIECE,0) + 1, 
            CTM2_PACKWEIGHT = ISNULL(CTM2_PACKWEIGHT,0) + ?, 
            CTM2_PACKPIECE = ISNULL(CTM2_PACKPIECE,0) + 1, 
            CTM2_STOCKWEIGHT = ISNULL(CTM2_STOCKWEIGHT,0) + ?, 
            CTM2_STOCKPIECE = ISNULL(CTM2_STOCKPIECE,0) + 1 
            WHERE SALEORDER_NO = ? AND SALEORDER_ITEM = ?")
             ->execute([$scrap_weight, $scrap_weight, $net_weight, $net_weight, $saleorder_no, $saleorder_item]);
    }

    // 9. จัดการ PACKPROD1 ตาม Logic f13 (ถ้า Net Weight > 0 และ Status เป็น ST)
    if ($net_weight > 0 && $crsh_status === 'ST') {
        $chk_pack = $conn->prepare("SELECT PRODUCT_NO FROM PACKPROD1 WHERE PRODUCT_NO = ?");
        $chk_pack->execute([$product_no]);
        if ($chk_pack->fetch()) {
            // อัปเดตน้ำหนักแพ็กเกจถ้ามีอยู่แล้ว
            $conn->prepare("UPDATE PACKPROD1 SET PACK_NETWEIGHT = ?, PACK_PACKAGEWEIGHT = ?, PACK_OPERATEDATE = GETDATE() WHERE PRODUCT_NO = ?")
                 ->execute([$net_weight, $bottom_weight, $product_no]);
        } else {
            // Insert ใหม่ตามโครงสร้าง f13 สมบูรณ์
            $sql_pack = "INSERT INTO PACKPROD1 (
                PRODUCT_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, MATERIAL_IN, TRANSFER_DATE, 
                RECEIVE_DATE, LINE_PROCESS, PRODUCT_ID, PRODUCT_MODEL, PACK_PONO, PACK_POITEM, PACK_DATE, 
                PACK_PACKAGEWEIGHT, PACK_NETPIECE, PACK_NETWEIGHT, PACK_LABEL, PACK_OPERATOR1, 
                PACK_OPERATEDATE, PACK_STATUS, ORG_STATUS
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $conn->prepare($sql_pack)->execute([
                $product_no, $saleorder_no, $saleorder_item, $job_order, $material_in, date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'), '1', $product_id, $product_model, $pack_pono, $pack_poitem, date('Y-m-d H:i:s'),
                $bottom_weight, 1, $net_weight, 1, $user_operator, date('Y-m-d H:i:s'), $crsh_status, $crsh_status
            ]);
        }
    }

    $conn->commit();

    $redirect_url = "../combining_entry_mats.php?func=" . urlencode($folder_func) . "&jno=" . urlencode($job_order) . "&pno=" . urlencode($product_no);
    header("Location: " . $redirect_url);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h3 style='color:red;'>Database Error / Exception:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<br><a href='javascript:history.back()'>[ ย้อนกลับ ]</a>";
}
?>