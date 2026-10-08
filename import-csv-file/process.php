<?php

//process.php

$serverName = "192.168.1.15\SQLEXPRESS";
$db_name = "DBJKT";
$conn = "";

$conn = new PDO("sqlsrv:server=".$serverName." ; Database=".$db_name, "sa", "Blitzkrieg0803");


$total_page_item_all = 0; 
$arr_data_set=array(array());

$sql = "SELECT * FROM tbl_sample";
	$stmt = $conn->prepare($sql);
	$stmt->execute();
	$i = 1;
	while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
		$arr_data_set['first_name'][$i]=$row['first_name'];		
		$i++;
	}		
    $total_page_item_all = ($i-1); // จำนวนรายการทั้งหมด

    echo $total_page_item_all;
?>