<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$response = array('status' => 'error', 'message' => false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists('../dbcon_mats-new.php')) {
    $response['error'] = 'ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php)';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

include('../dbcon_mats-new.php');

try {
    // 1. รับค่า Header Keys รวมถึง COOLANT_RECIPE, SPOOL และ RSET_NO
    $recipe_no          = isset($_POST['recipe_no']) ? trim($_POST['recipe_no']) : '';
    $rset_no            = isset($_POST['rset_no']) ? trim($_POST['rset_no']) : '';
    $alloy              = isset($_POST['alloy']) ? trim($_POST['alloy']) : '';
    $width              = isset($_POST['width']) && $_POST['width'] !== '' ? (float)$_POST['width'] : 0;
    $thickness_original = isset($_POST['thickness_original']) && $_POST['thickness_original'] !== '' ? (float)$_POST['thickness_original'] : 0;
    $thickness_final    = isset($_POST['thickness_final']) && $_POST['thickness_final'] !== '' ? (float)$_POST['thickness_final'] : 0;
    $coolant_recipe     = isset($_POST['coolant_recipe']) ? trim($_POST['coolant_recipe']) : '';
    $spool              = isset($_POST['spool']) && $_POST['spool'] !== '' ? (int)$_POST['spool'] : 0;

    if (empty($recipe_no)) {
        $response['error'] = 'ไม่พบรหัส Recipe No.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($rset_no)) {
        $response['error'] = 'กรุณาเลือก Roll Set (RSET_NO)';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. ดึงข้อมูล Roll Details ล่าสุดจากตาราง RDMTMSTR3 ตาม RSET_NO ที่เลือก
    $rollSql = "SELECT WRL_RADIUS, TWR_CAMBER, BWR_CAMBER, BRL_RADIUS 
                FROM RDMTMSTR3 
                WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:rset_no))";
    $rollStmt = $conn->prepare($rollSql);
    $rollStmt->execute([':rset_no' => $rset_no]);
    $rollData = $rollStmt->fetch(PDO::FETCH_ASSOC);

    // กำหนดค่า Roll Parameters (ถ้าไม่พบข้อมูลให้ default เป็น 0)
    $stp_wrl_rdn = $rollData ? (float)$rollData['WRL_RADIUS'] : 0;
    $stp_twr_cmb = $rollData ? (float)$rollData['TWR_CAMBER'] : 0;
    $stp_bwr_cmb = $rollData ? (float)$rollData['BWR_CAMBER'] : 0;
    $stp_brl_rdn = $rollData ? (float)$rollData['BRL_RADIUS'] : 0;

    // 3. รับค่ารายการลูก (Arrays)
    $recipe_items          = $_POST['recipe_item'] ?? [];
    $temper_finishes       = $_POST['temper_finish'] ?? [];
    $thickness_entries     = $_POST['thickness_entry'] ?? [];
    $thickness_exits       = $_POST['thickness_exit'] ?? [];
    $thickness_telorances  = $_POST['thickness_telorance'] ?? [];
    $work_hardening1s      = $_POST['work_hardening1'] ?? [];
    $work_hardening2s      = $_POST['work_hardening2'] ?? [];
    $coefficient_frictions = $_POST['coefficient_friction'] ?? [];
    $yield_stresses        = $_POST['yield_stress'] ?? [];
    $empirical_value1s     = $_POST['empirical_value1'] ?? [];

    if (count($recipe_items) === 0) {
        $response['error'] = 'กรุณาระบุรายการ Recipe Item อย่างน้อย 1 รายการ';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn->beginTransaction();

    // 4. ลบรายการเดิมทั้งหมดของ RECIPE_NO นี้ใน CMRCMSTR1
    $delSql = "DELETE FROM CMRCMSTR1 WHERE LTRIM(RTRIM(RECIPE_NO)) = LTRIM(RTRIM(:rno))";
    $delStmt = $conn->prepare($delSql);
    $delStmt->execute([':rno' => $recipe_no]);

    // 5. บันทึกข้อมูลรายการชุดใหม่ลงตาราง CMRCMSTR1 (รวม RSET_NO และ Roll Parameters เข้าไปด้วย)
    $insSql = "INSERT INTO CMRCMSTR1 (
                    RECIPE_NO, RECIPE_ITEM, ALLOY, WIDTH, THICKNESS_ORIGINAL, THICKNESS_FINAL,
                    COOLANT_RECIPE, SPOOL, TEMPER_FINISH, THICKNESS_ENTRY, THICKNESS_EXIT, 
                    THICKNESS_TELORANCE, TOTAL_PASS, WORK_HARDENING1, WORK_HARDENING2, 
                    COEFFICIENT_FRICTION, YIELD_STRESS, EMPIRICAL_VALUE1,
                    RSET_NO, STP_WRL_RDN, STP_TWR_CMB, STP_BWR_CMB, STP_BRL_RDN
                ) VALUES (
                    :rno, :item, :al, :wi, :thor, :thfn,
                    :coolant, :spool, :temper, :thent, :thext, 
                    :thtol, :totpass, :wh1, :wh2, 
                    :coeff, :yield, :emp1,
                    :rset_no, :stp_wrl_rdn, :stp_twr_cmb, :stp_bwr_cmb, :stp_brl_rdn
                )";

    $insStmt = $conn->prepare($insSql);

    for ($i = 0; $i < count($recipe_items); $i++) {
        $item = trim($recipe_items[$i]);
        if ($item === '') continue;

        $insStmt->execute([
            ':rno'         => $recipe_no,
            ':item'        => (int)$item,
            ':al'          => $alloy,
            ':wi'          => $width,
            ':thor'        => $thickness_original,
            ':thfn'        => $thickness_final,
            ':coolant'     => $coolant_recipe,
            ':spool'       => $spool,
            ':temper'      => isset($temper_finishes[$i]) ? trim($temper_finishes[$i]) : '',
            ':thent'       => isset($thickness_entries[$i]) && $thickness_entries[$i] !== '' ? (float)$thickness_entries[$i] : 0,
            ':thext'       => isset($thickness_exits[$i]) && $thickness_exits[$i] !== '' ? (float)$thickness_exits[$i] : 0,
            ':thtol'       => isset($thickness_telorances[$i]) && $thickness_telorances[$i] !== '' ? (int)$thickness_telorances[$i] : 0,
            ':totpass'     => 0,
            ':wh1'         => isset($work_hardening1s[$i]) && $work_hardening1s[$i] !== '' ? (float)$work_hardening1s[$i] : 0,
            ':wh2'         => isset($work_hardening2s[$i]) && $work_hardening2s[$i] !== '' ? (float)$work_hardening2s[$i] : 0,
            ':coeff'       => isset($coefficient_frictions[$i]) && $coefficient_frictions[$i] !== '' ? (float)$coefficient_frictions[$i] : 0,
            ':yield'       => isset($yield_stresses[$i]) && $yield_stresses[$i] !== '' ? (float)$yield_stresses[$i] : 0,
            ':emp1'        => isset($empirical_value1s[$i]) && $empirical_value1s[$i] !== '' ? (float)$empirical_value1s[$i] : 0,
            ':rset_no'     => $rset_no,
            ':stp_wrl_rdn' => $stp_wrl_rdn,
            ':stp_twr_cmb' => $stp_twr_cmb,
            ':stp_bwr_cmb' => $stp_bwr_cmb,
            ':stp_brl_rdn' => $stp_brl_rdn
        ]);
    }

    $conn->commit();

    $response['status']  = 'success';
    $response['message'] = true;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;