<?php
// เริ่ม session ก่อน ANY output
session_start();

// ตรวจสอบ Login / Session
if (!isset($_SESSION['ID'])) {
    header("Location: ../index.php");
    exit();
}

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once "../dbcon_mats-new.php";

// รับค่าจาก Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ดึงและกรองข้อมูลนำเข้า (Sanitize Input)
    $coil_no         = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $use_forprocess  = isset($_POST['use_forprocess']) ? trim($_POST['use_forprocess']) : '';
    $coil_remark     = isset($_POST['coil_remark']) ? trim($_POST['coil_remark']) : '';
    $folder_func     = isset($_POST['func']) ? trim($_POST['func']) : '';

    // ตรวจสอบค่า COIL_NO ว่ามีหรือไม่
    if (!empty($coil_no)) {
        try {
            // คำสั่ง SQL Update
            $sql = "UPDATE COILPROD1 
                    SET USE_FORPROCESS = :use_forprocess, 
                        COIL_REMARK = :coil_remark 
                    WHERE COIL_NO = :coil_no";

            $stmt = $conn->prepare($sql);
            
            // ผูกตัวแปร
            $stmt->bindParam(':use_forprocess', $use_forprocess, PDO::PARAM_STR);
            $stmt->bindParam(':coil_remark', $coil_remark, PDO::PARAM_STR);
            $stmt->bindParam(':coil_no', $coil_no, PDO::PARAM_STR);

            // ประมวลผลการ Update
            if ($stmt->execute()) {
                // บันทึกสำเร็จ Redirect กลับไปยังหน้าเดิมพร้อมแจ้งเตือน
                echo "<script>
                        alert('✅ Updated Coil Remark & Use For Process successfully!');
                        window.location.href = '../coil_remark_mats.php?func=" . urlencode($folder_func) . "&COIL=" . urlencode($coil_no) . "';
                      </script>";
                exit();
            } else {
                echo "<script>
                        alert('❌ Failed to update data. Please try again.');
                        window.history.back();
                      </script>";
                exit();
            }

        } catch (PDOException $e) {
            // กรณีเกิดข้อผิดพลาดจาก Database
            echo "<script>
                    alert('⚠️ Database Error: " . addslashes($e->getMessage()) . "');
                    window.history.back();
                  </script>";
            exit();
        }
    } else {
        echo "<script>
                alert('⚠️ Invalid Coil Number!');
                window.history.back();
              </script>";
        exit();
    }
} else {
    // หากไม่ได้มาด้วยวิธี POST
    header("Location: ../coil_remark_process_mats.php");
    exit();
}
?>