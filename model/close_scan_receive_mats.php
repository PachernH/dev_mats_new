<?php
// model/close_scan_receive_mats.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_GET['func']) ? htmlspecialchars($_GET['func'], ENT_QUOTES, 'UTF-8') : '';

if (empty($iduser_func)) {
    header("Location: ../index.php");
    exit();
}

// ตรวจสอบตำแหน่งไฟล์เชื่อมต่อฐานข้อมูล
if (file_exists('../dbcon_mats-new.php')) {
    require_once '../dbcon_mats-new.php';
} elseif (file_exists('dbcon_mats-new.php')) {
    require_once 'dbcon_mats-new.php';
} else {
    die("ไม่พบไฟล์เชื่อมต่อฐานข้อมูล dbcon_mats-new.php");
}

$gr_no = isset($_GET['gr_no']) ? trim($_GET['gr_no']) : '';

if (!empty($gr_no)) {
    try {
        // อัปเดตสถานะเป็น CL และบันทึกวันเวลาปัจจุบันลงคอลัมน์ update_at
        $sql = "UPDATE RECEIVING_HEADER 
                SET receive_status = 'CL', 
                    update_at = GETDATE() 
                WHERE gr_no = :gr_no";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':gr_no' => $gr_no]);

        // อัปเดตสำเร็จ ส่งกลับไปหน้ารายการ Packing List Active OP
        header("Location: ../packing_list_process_mats.php?func=" . urlencode($folder_func) . "&msg=closed");
        exit();

    } catch (Exception $e) {
        die("เกิดข้อผิดพลาดในการปิดการสแกน (SQL Error): " . htmlspecialchars($e->getMessage()));
    }
} else {
    header("Location: ../packing_list_process_mats.php?func=" . urlencode($folder_func));
    exit();
}