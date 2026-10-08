<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include('../dbcon_mats-new.php');

$rno = isset($_POST['rno']) ? trim($_POST['rno']) : '';
$user_update = $_SESSION['ID'] ?? 'SYSTEM';

if (empty($rno)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบระบุ REQUEST NO']);
    exit();
}

try {
    // คำสั่ง SQL Update ประมวลผล Condition ของ RESPONS_TYPE
    $sql = "UPDATE PRODRMLT2 
            SET 
                REMELT_STATUS = 'RM',
                REMELT_DATE = GETDATE(),
                RQT2_UPDATE = :user_update,
                RESPONS_TYPE = CASE 
                    WHEN ORIGINAL_STATUS IN ('ST', 'DS', 'TR') THEN 'FG. STOCK'
                    WHEN ORIGINAL_STATUS = 'PK' THEN 'PROD. PACK'
                    ELSE 
                        CASE 
                            WHEN PRODUCT_ID = 'CO' THEN 
                                CASE WHEN SUBSTRING(PRODUCT_NO, 9, 1) = 'R' THEN 'FG. RETURN' ELSE 'PROD. CUT TO LENGTH' END
                            WHEN PRODUCT_ID IN ('CC', 'NC') THEN 
                                CASE WHEN SUBSTRING(PRODUCT_NO, 9, 1) = 'R' THEN 'FG. RETURN' ELSE 'PROD. FLASH ANNEAL' END
                            WHEN PRODUCT_ID = 'SH' THEN 
                                CASE WHEN SUBSTRING(PRODUCT_NO, 9, 1) = 'R' THEN 'FG. RETURN' ELSE 'PROD. FLASH ANNEAL' END
                            ELSE RESPONS_TYPE
                        END
                END
            WHERE REQUEST_NO = :rno 
            AND (REMELT_STATUS IS NULL OR LTRIM(RTRIM(REMELT_STATUS)) = '')";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_update', $user_update, PDO::PARAM_STR);
    $stmt->bindParam(':rno', $rno, PDO::PARAM_STR);
    $stmt->execute();

    $affected_rows = $stmt->rowCount();

    echo json_encode([
        'status' => 'success', 
        'message' => "บันทึกข้อมูลเรียบร้อยแล้ว (อัปเดตทั้งหมด {$affected_rows} รายการ)"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>