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

// ฟังก์ชันแปลงค่า datetime-local สำหรับ SQL Server smalldatetime (Y-m-d H:i:s)
function formatSqlDateTime($inputDt, $defaultNull = true) {
    if (empty($inputDt)) {
        return $defaultNull ? NULL : '1900-01-01 00:00:00';
    }
    $cleanDt = str_replace('T', ' ', $inputDt);
    $time = strtotime($cleanDt);
    if ($time === false) {
        return $defaultNull ? NULL : '1900-01-01 00:00:00';
    }
    return date('Y-m-d H:i:s', $time);
}

try {
    // 1. รับค่ารหัส Roll Set เดิม
    $old_rset_no = isset($_POST['rset_no']) ? trim($_POST['rset_no']) : '';

    if (empty($old_rset_no)) {
        $response['error'] = 'ไม่พบรหัส Roll Set เดิม (RSET_NO)';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. ดึงข้อมูลเดิมเพื่ออ้างอิง WORK_NO เก่า และตรวจสอบสถานะ OP
    $checkSql = "SELECT RSET_STATUS, TWR_WORKNO, BWR_WORKNO, TBR_WORKNO, BBR_WORKNO 
                 FROM RDMTMSTR3 
                 WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:rno))";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':rno' => $old_rset_no]);
    $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($oldData === false) {
        $response['error'] = "ไม่พบข้อมูล Roll Set No. ($old_rset_no) ในระบบ";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (trim($oldData['RSET_STATUS'] ?? '') !== 'OP') {
        $response['error'] = "ไม่อนุญาตให้แก้ไขเนื่องจาก Roll Set ($old_rset_no) มีสถานะไม่ใช่ 'OP'";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Work No เก่า
    $old_twr = trim($oldData['TWR_WORKNO'] ?? '');
    $old_bwr = trim($oldData['BWR_WORKNO'] ?? '');
    $old_tbr = trim($oldData['TBR_WORKNO'] ?? '');
    $old_bbr = trim($oldData['BBR_WORKNO'] ?? '');

    // 3. รับค่าตัวเลข และ Work No. ใหม่จากฟอร์ม
    $wrl_radius     = isset($_POST['wrl_radius']) && $_POST['wrl_radius'] !== '' ? round((float)$_POST['wrl_radius'], 2) : 0.00;
    $brl_radius     = isset($_POST['brl_radius']) && $_POST['brl_radius'] !== '' ? round((float)$_POST['brl_radius'], 2) : 0.00;

    // TWR
    $twr_workno     = isset($_POST['twr_workno']) ? trim($_POST['twr_workno']) : '';
    $twr_camber     = isset($_POST['twr_camber']) && $_POST['twr_camber'] !== '' ? round((float)$_POST['twr_camber'], 2) : 0.00;
    $twr_cambertype = isset($_POST['twr_cambertype']) ? trim($_POST['twr_cambertype']) : '';
    $twr_indatetime = formatSqlDateTime($_POST['twr_indatetime'] ?? '', true);
    $twr_outdatetime= formatSqlDateTime($_POST['twr_outdatetime'] ?? '', false);

    // BWR
    $bwr_workno     = isset($_POST['bwr_workno']) ? trim($_POST['bwr_workno']) : '';
    $bwr_camber     = isset($_POST['bwr_camber']) && $_POST['bwr_camber'] !== '' ? round((float)$_POST['bwr_camber'], 2) : 0.00;
    $bwr_cambertype = isset($_POST['bwr_cambertype']) ? trim($_POST['bwr_cambertype']) : '';
    $bwr_indatetime = formatSqlDateTime($_POST['bwr_indatetime'] ?? '', true);
    $bwr_outdatetime= formatSqlDateTime($_POST['bwr_outdatetime'] ?? '', false);

    // TBR
    $tbr_workno     = isset($_POST['tbr_workno']) ? trim($_POST['tbr_workno']) : '';
    $tbr_indatetime = formatSqlDateTime($_POST['tbr_indatetime'] ?? '', true);
    $tbr_outdatetime= formatSqlDateTime($_POST['tbr_outdatetime'] ?? '', false);

    // BBR
    $bbr_workno     = isset($_POST['bbr_workno']) ? trim($_POST['bbr_workno']) : '';
    $bbr_indatetime = formatSqlDateTime($_POST['bbr_indatetime'] ?? '', true);
    $bbr_outdatetime= formatSqlDateTime($_POST['bbr_outdatetime'] ?? '', false);

    $conn->beginTransaction();

    // 4. คำนวณรหัส RSET_NO ใหม่
    $parts = explode('-', $old_rset_no);
    if (count($parts) === 3) {
        $prefix = $parts[0] . '-' . $parts[1] . '-';
        $num_part = (int)$parts[2];
        
        $maxSql = "SELECT MAX(RSET_NO) FROM RDMTMSTR3 WHERE RSET_NO LIKE :prefix";
        $maxStmt = $conn->prepare($maxSql);
        $maxStmt->execute([':prefix' => $prefix . '%']);
        $maxRset = $maxStmt->fetchColumn();

        if ($maxRset) {
            $m_parts = explode('-', $maxRset);
            $nextNum = ((int)($m_parts[2] ?? $num_part)) + 1;
        } else {
            $nextNum = $num_part + 1;
        }

        $new_rset_no = $prefix . sprintf('%04d', $nextNum);
    } else {
        $new_rset_no = $old_rset_no . '-1';
    }

    // 5. ปรับสถานะ RSET_NO เดิมใน RDMTMSTR3 ให้กลายเป็น 'CL'
    $updateOldSql = "UPDATE RDMTMSTR3 SET RSET_STATUS = 'CL' WHERE LTRIM(RTRIM(RSET_NO)) = LTRIM(RTRIM(:old_rno))";
    $updateOldStmt = $conn->prepare($updateOldSql);
    $updateOldStmt->execute([':old_rno' => $old_rset_no]);

    // 6. INSERT Record ใหม่ลงตาราง RDMTMSTR3
    $insSql = "INSERT INTO RDMTMSTR3 (
                    RSET_NO, RSET_REFERENCE, WRL_RADIUS, BRL_RADIUS, RSET_STATUS,
                    TWR_WORKNO, TWR_CAMBER, TWR_CAMBERTYPE, TWR_INDATETIME, TWR_OUTDATETIME,
                    BWR_WORKNO, BWR_CAMBER, BWR_CAMBERTYPE, BWR_INDATETIME, BWR_OUTDATETIME,
                    TBR_WORKNO, TBR_INDATETIME, TBR_OUTDATETIME,
                    BBR_WORKNO, BBR_INDATETIME, BBR_OUTDATETIME
                ) VALUES (
                    :new_rno, :old_ref, :wrl, :brl, 'OP',
                    :twr_wn, :twr_cb, :twr_cbt, :twr_in, :twr_out,
                    :bwr_wn, :bwr_cb, :bwr_cbt, :bwr_in, :bwr_out,
                    :tbr_wn, :tbr_in, :tbr_out,
                    :bbr_wn, :bbr_in, :bbr_out
                )";

    $insStmt = $conn->prepare($insSql);
    $insStmt->execute([
        ':new_rno'  => $new_rset_no,
        ':old_ref'  => $old_rset_no,
        ':wrl'      => $wrl_radius,
        ':brl'      => $brl_radius,
        ':twr_wn'   => $twr_workno,
        ':twr_cb'   => $twr_camber,
        ':twr_cbt'  => $twr_cambertype,
        ':twr_in'   => $twr_indatetime,
        ':twr_out'  => $twr_outdatetime,
        ':bwr_wn'   => $bwr_workno,
        ':bwr_cb'   => $bwr_camber,
        ':bwr_cbt'  => $bwr_cambertype,
        ':bwr_in'   => $bwr_indatetime,
        ':bwr_out'  => $bwr_outdatetime,
        ':tbr_wn'   => $tbr_workno,
        ':tbr_in'   => $tbr_indatetime,
        ':tbr_out'  => $tbr_outdatetime,
        ':bbr_wn'   => $bbr_workno,
        ':bbr_in'   => $bbr_indatetime,
        ':bbr_out'  => $bbr_outdatetime
    ]);

    // 7. ปรับปรุงสถานะ WRK_STATUS ในตาราง RDMTMSTR2
    $updateWkStatus = "UPDATE RDMTMSTR2 SET WRK_STATUS = :status WHERE LTRIM(RTRIM(WORK_NO)) = LTRIM(RTRIM(:wn))";
    $upWkStmt = $conn->prepare($updateWkStatus);

    $old_new_pairs = [
        ['old' => $old_twr, 'new' => $twr_workno],
        ['old' => $old_bwr, 'new' => $bwr_workno],
        ['old' => $old_tbr, 'new' => $tbr_workno],
        ['old' => $old_bbr, 'new' => $bbr_workno]
    ];

    foreach ($old_new_pairs as $pair) {
        $old_wn = $pair['old'];
        $new_wn = $pair['new'];

        // กรณีเลือก Work No ใหม่
        if (!empty($new_wn)) {
            // ปรับรายการใหม่จาก OP เป็น WK
            $upWkStmt->execute([':status' => 'WK', ':wn' => $new_wn]);
        }

        // กรณีเปลี่ยน Work No (ตัวเก่าไม่เท่ากับตัวใหม่) ให้ปรับตัวเก่าจาก WK เป็น CL
        if (!empty($old_wn) && $old_wn !== $new_wn) {
            $upWkStmt->execute([':status' => 'CL', ':wn' => $old_wn]);
        }
    }

    $conn->commit();

    $response['status']      = 'success';
    $response['message']     = true;
    $response['new_rset_no'] = $new_rset_no;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['error'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;