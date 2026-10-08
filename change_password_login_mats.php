<?php
// เริ่ม session ก่อน ANY output
session_start();

// ดึงค่า ID จาก Session มาตรง ๆ
$userid = isset($_SESSION['ID']) ? $_SESSION['ID'] : '';

include("dbcon_mats-new.php");

// ตรวจสอบว่ามีการส่งค่า npw มาจริงและไม่ใช่ค่าว่าง
$npw = isset($_POST['npw']) ? trim($_POST['npw']) : '';

if ($npw != '' && $userid != '') {
    
    $sql = "UPDATE USERMSTR1 SET USER_PASSWORD = :pass WHERE USER_ID = :user";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'user' => $userid, 
            'pass' => $npw 
        ]);

        // เคลียร์ session หลังจากเปลี่ยนรหัสผ่านสำเร็จ
        session_destroy();
        
        // ใช้ JavaScript แสดง Alert และเด้งไปหน้า index.php หลังจากกด OK
        echo "<script>
                alert('Password changed successfully! Please log in again.');
                window.location.href = 'index.php';
              </script>";
        exit();
        
    } catch (PDOException $e) {
        // หากฐานข้อมูลมีปัญหา
        echo "<script>
                alert('An error occurred in the database system. The password cannot be changed.');
                window.location.href = 'index.php';
              </script>";
        exit();
    }
    
} else {
    // กรณีข้อมูลไม่ถูกต้อง หรือกดเข้ามาดื้อๆ โดยไม่มี session/ข้อมูล
    echo "<script>
            alert('The information is incorrect or the session has expired. Please try again.');
            window.location.href = 'index.php';
          </script>";
    exit();
}
?>