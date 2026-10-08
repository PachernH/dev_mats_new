<?php
// เริ่ม session ก่อน ANY output
session_start();

// หลังจากนี้สามารถมี output ได้

header('Content-type: text/html; charset=utf-8');
header("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

set_time_limit(0);

ob_implicit_flush(1);

if(isset($_SESSION['csv_file_name']))
{
	$serverName = "192.168.1.15\SQLEXPRESS";
	$db_name = "DBJKT";
	$conn = "";
	
	$conn = new PDO("sqlsrv:server=".$serverName." ; Database=".$db_name, "sa", "Blitzkrieg0803");
	
	//$connect = new PDO("mysql:host=localhost; dbname=testing", "root", "");

	$file_data = fopen('file/' . $_SESSION['csv_file_name'], 'r');

	fgetcsv($file_data);

	while($row = fgetcsv($file_data))
	{
		$data = array(
			':first_name'	=>	$row[0],
			':last_name'	=>	$row[1]
		);

		$query = "
		INSERT INTO tbl_sample (first_name, last_name) 
    	VALUES (:first_name, :last_name)
		";

		$statement = $conn->prepare($query);

		$statement->execute($data);

		sleep(1);

		if(ob_get_level() > 0)
		{
			ob_end_flush();
		}
	}

	unset($_SESSION['csv_file_name']);
}

?>