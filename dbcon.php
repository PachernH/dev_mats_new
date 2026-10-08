<?php

//*** Docker (Containers system sql server on windows system)
//$serverName = "mssql"; 
//$databaseName = "MATS";
//$username = "sa";
//$password = "P@ssw0rd@2025";


//$serverName = "DB1SERVER"; 
//$databaseName = "MATS-NEW";
//$username = "sa";
//$password = "DATASYSTEM";


//try {
//    $dsn = "sqlsrv:Server=$serverName;Database=$databaseName;Encrypt=no;TrustServerCertificate=yes;LoginTimeout=30";
//    $conn = new PDO($dsn, $username, $password);
    //echo "Connected successfully to SQL 2005!";
//} catch(PDOException $e) {
//    echo "Connection failed: " . $e->getMessage();
//}

//***Connect MYSQL SERVER 8.1
//$servername = "localhost";
//$username = "root";
//$password = "Kwr@M4s#01";
//$dbname = "db_kwankamon";

//*** Docker (Containers system php-mysql on windows system)
$servername = "mysql";
$username = "pachern";
//$password = "Kwr@M4s#01";
//$dbname = "db_kwankamon";
$password = "d@ta#system26";
$dbname = "db_mats";

// Google Maps API Key
//define('GMAPS_API_KEY', 'AIzaSyB5lwpr3kurLlr5uwbH0xyKbI7ZfP8bQlg');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

?>