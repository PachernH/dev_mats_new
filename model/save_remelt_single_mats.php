<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

include('../dbcon_mats-new.php');

$pno = isset($_POST['pno']) ? trim($_POST['pno']) : '';
$user_update = $_SESSION['ID'] ?? 'SYSTEM';

if (empty($pno)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูล PRODUCT NO']);
    exit();
}

try {
    // คำสั่ง SQL Update ประมวลผล Condition ของ RESPONS_TYPE เฉพาะ PRODUCT_NO นั้นๆ
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
            WHERE PRODUCT_NO = :pno 
            AND (REMELT_STATUS IS NULL OR LTRIM(RTRIM(REMELT_STATUS)) = '')";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_update', $user_update, PDO::PARAM_STR);
    $stmt->bindParam(':pno', $pno, PDO::PARAM_STR);
    $stmt->execute();

    $affected_rows = $stmt->rowCount();

    if ($affected_rows > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => "อัปเดตรายการ {$pno} เรียบร้อยแล้ว"
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'ไม่สามารถทำรายการได้ รายการนี้อาจถูก Remelt ไปแล้ว'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database Error: ' . $e->getMessage()
    ]);
}
?>