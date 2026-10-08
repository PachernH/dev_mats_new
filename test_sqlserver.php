<?php
// ***  Connection SQL_PDO
$serverName = "mssql"; 
$databaseName = "MATS";
$username = "sa";
$password = "P@ssw0rd@2025";


//$serverName = "DB1SERVER"; 
//$databaseName = "MATS";
//$username = "sa";
//$password = "DATASYSTEM";


try {
    $dsn = "sqlsrv:Server=$serverName;Database=$databaseName;Encrypt=no;TrustServerCertificate=yes;LoginTimeout=30";
    $conn = new PDO($dsn, $username, $password);
    echo "Connected successfully to SQL 2005!";
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>