<?php
$serverName = "DB1SERVER"; 
$databaseName = "MP2";
$username = "MP2";
$password = "MP2554315";

try {
    // เปลี่ยนมาใช้ dsn แบบ dblib สำหรับ SQL Server 2005
    $dsn = "dblib:host=$serverName;dbname=$databaseName;charset=utf8";
    $conn = new PDO($dsn, $username, $password);
    
    // ตั้งค่าเพิ่มสิทธิ์การจัดการ Error ของ PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    //echo "Connected successfully to SQL 2005 via DBLIB!";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
    
?>
