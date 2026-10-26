<?php
$serverName   = "DB1SERVER"; 
$databaseName = "MMD6";
$username     = "sa";
$password     = "DATASYSTEM";

try {
    // ใช้ DSN แบบ DBLIB สำหรับ SQL Server 2005
    $dsn = "dblib:host=$serverName;dbname=$databaseName;charset=utf8";
    $conn6 = new PDO($dsn, $username, $password);
    
    // แก้ไขเป็น $conn6 ให้ตรงกับตัวแปรที่ new PDO
    $conn6->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ห้าม echo ข้อความใดๆ ออกมาในไฟล์นี้ เพื่อไม่ให้ผลลัพธ์ JSON เสียหาย
} catch(PDOException $e) {
    // ปล่อยให้ Error ไปจัดการที่ไฟล์เรียกใช้
}
?>