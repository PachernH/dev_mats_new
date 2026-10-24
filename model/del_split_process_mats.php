<?php
// model/del_split_process_mats.php

// 1. เปิดการแสดง Error เพื่อป้องกันหน้าขาวขณะ Debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ตรวจสอบ Session
if (!isset($_SESSION['ID'])) {
    header("Location: ../index.php");
    exit();
}

// 2. ตรวจสอบและดึงไฟล์เชื่อมต่อฐานข้อมูล (เช็กทั้งแบบในโฟลเดอร์และนอกโฟลเดอร์)
if (file_exists("../dbcon_mats-new.php")) {
    require_once "../dbcon_mats-new.php";
} elseif (file_exists("dbcon_mats-new.php")) {
    require_once "dbcon_mats-new.php";
} else {
    die("<div style='color:red; font-weight:bold; padding:20px;'>Error: ไม่พบไฟล์เชื่อมต่อฐานข้อมูล (dbcon_mats-new.php) กรุณาตรวจสอบ Path ไฟล์</div>");
}

// รับค่า Parameters
$split_no    = isset($_GET['split_no']) ? trim($_GET['split_no']) : '';
$parent_no   = isset($_GET['parent_no']) ? trim($_GET['parent_no']) : '';
$folder_func = isset($_GET['func']) ? trim($_GET['func']) : '';
$user_op     = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';

if (empty($split_no) || empty($parent_no)) {
    echo "<script>
            alert('Error: ไม่พบข้อมูลรหัสคอยล์ (Missing parameters)');
            window.history.back();
          </script>";
    exit();
}

try {
    // ตรวจสอบว่าตัวแปรเชื่อมต่อฐานข้อมูล $conn สมบูรณ์หรือไม่
    if (!isset($conn) || !$conn) {
        throw new Exception("ไม่สามารถเชื่อมต่อฐานข้อมูลได้ (Database Connection Failed)");
    }

    // 1. ดึงข้อมูลค่าน้ำหนักของคอยล์ลูกที่จะถูกลบ
    $stmtSplit = $conn->prepare("SELECT COIL_ACTUALWEIGHT, COIL_FIRSTWEIGHT, COIL_COLDMILLWEIGHT, COIL_BATCHANNEALWEIGHT, COIL_BALANCEWEIGHT 
                                 FROM COILPROD1 WHERE COIL_NO = :split_no");
    $stmtSplit->execute([':split_no' => $split_no]);
    $splitData = $stmtSplit->fetch(PDO::FETCH_ASSOC);

    if (!$splitData) {
        throw new Exception("ไม่พบข้อมูลคอยล์ย่อยที่ต้องการลบ (Split Coil: {$split_no})");
    }

    $splitActual  = (float)($splitData['COIL_ACTUALWEIGHT'] ?? 0);
    $splitFirst   = (float)($splitData['COIL_FIRSTWEIGHT'] ?? 0);
    $splitCdml    = (float)($splitData['COIL_COLDMILLWEIGHT'] ?? 0);
    $splitBtch    = (float)($splitData['COIL_BATCHANNEALWEIGHT'] ?? 0);
    $splitBalance = (float)($splitData['COIL_BALANCEWEIGHT'] ?? 0);

    // เริ่มต้น Transaction
    $conn->beginTransaction();

    // 2. คืนค่าน้ำหนักให้คอยล์แม่ ใน COILPROD1
    $sqlUpdParent = "UPDATE COILPROD1 SET 
                        COIL_ACTUALWEIGHT = COIL_ACTUALWEIGHT + :split_actual,
                        COIL_FIRSTWEIGHT = COIL_FIRSTWEIGHT + :split_first,
                        COIL_COLDMILLWEIGHT = COIL_COLDMILLWEIGHT + :split_cdml,
                        COIL_BATCHANNEALWEIGHT = COIL_BATCHANNEALWEIGHT + :split_btch,
                        COIL_BALANCEWEIGHT = COIL_BALANCEWEIGHT + :split_bal,
                        COIL_UPDATE = :user_op,
                        COIL_UPDATEDATE = GETDATE()
                     WHERE COIL_NO = :parent_no";
    
    $stmtUpdParent = $conn->prepare($sqlUpdParent);
    $stmtUpdParent->execute([
        ':split_actual' => $splitActual,
        ':split_first'  => $splitFirst,
        ':split_cdml'   => $splitCdml,
        ':split_btch'   => $splitBtch,
        ':split_bal'    => $splitBalance > 0 ? $splitBalance : $splitActual,
        ':user_op'      => $user_op,
        ':parent_no'    => $parent_no
    ]);

    // 3. คืนค่าน้ำหนัก Cold Mill ใน CDMLPROD1
    if ($splitCdml > 0) {
        $sqlUpdCdml = "UPDATE CDMLPROD1 SET 
                        CDML_ACCEPTWEIGHT = CDML_ACCEPTWEIGHT + :split_actual,
                        CDML_UPDATE = :user_op,
                        CDML_UPDATEDATE = GETDATE()
                       WHERE COIL_NO = :parent_no";
        $stmtUpdCdml = $conn->prepare($sqlUpdCdml);
        $stmtUpdCdml->execute([
            ':split_actual' => $splitActual,
            ':user_op'      => $user_op,
            ':parent_no'    => $parent_no
        ]);
    }

    // 4. คืนค่าน้ำหนัก Batch Anneal ใน BTCHPROD1
    if ($splitBtch > 0) {
        $sqlUpdBtch = "UPDATE BTCHPROD1 SET 
                        BTCH_ACCEPTWEIGHT = BTCH_ACCEPTWEIGHT + :split_actual,
                        BTCH_UPDATE = :user_op,
                        BTCH_UPDATEDATE = GETDATE()
                       WHERE PRODUCT_NO = :parent_no";
        $stmtUpdBtch = $conn->prepare($sqlUpdBtch);
        $stmtUpdBtch->execute([
            ':split_actual' => $splitActual,
            ':user_op'      => $user_op,
            ':parent_no'    => $parent_no
        ]);
    }

    // 5. ลบข้อมูลคอยล์ลูกออกจากตารางที่เกี่ยวข้องทั้งหมด
    $conn->prepare("DELETE FROM COILPROD1 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM CDMLPROD1 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM BTCHPROD1 WHERE PRODUCT_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM COILINSP1 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM COILINSP2 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM COILINSP3 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM COILINSP5 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);
    $conn->prepare("DELETE FROM COILINSP6 WHERE COIL_NO = :split_no")->execute([':split_no' => $split_no]);

    // Commit Transaction
    $conn->commit();

    // เคลียร์ Output Buffer ก่อนสั่ง Redirect
    if (ob_get_length()) ob_end_clean();

    echo "<script>
            alert('ลบข้อมูลคอยล์ย่อย (" . $split_no . ") เรียบร้อยแล้ว');
            window.location.href = '../split_coil_mats.php?func=" . urlencode($folder_func) . "&COIL=" . urlencode($parent_no) . "';
          </script>";
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "<div style='color:red; font-family:sans-serif; padding:20px; border:1px solid red; background:#fff0f0;'>";
    echo "<h3>เกิดข้อผิดพลาดในการลบข้อมูล (Error Exception):</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='javascript:history.back()'>← ย้อนกลับ</a></p>";
    echo "</div>";
    exit();
}