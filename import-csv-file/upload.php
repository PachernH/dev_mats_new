<?php
// เริ่ม session ก่อน ANY output
session_start();

// หลังจากนี้สามารถมี output ได้

//upload.php


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
	
	
if(isset($_POST['hidden_field']))
{
	$error = '';
	$total_line = '';
	
	if($_FILES['file']['name'] != '')
	{
		$allowed_extension = array('csv');
		$file_array = explode(".", $_FILES["file"]["name"]);
		$extension = end($file_array);
		if(in_array($extension, $allowed_extension))
		{
			$new_file_name = rand() . '.' . $extension;
			$_SESSION['csv_file_name'] = $new_file_name;
			move_uploaded_file($_FILES['file']['tmp_name'], 'file/'.$new_file_name);
			$file_content = file('file/'. $new_file_name, FILE_SKIP_EMPTY_LINES);
			$total_line = count($file_content);
		}
		else
		{
			$error = 'Only CSV file format is allowed';
		}
	}
	else
	{
		$error = 'Please Select File';
	}

	if($error != '')
	{
		$output = array(
			'error'		=>	$error
		);
	}	
	else
	{
		$output = array(
			'success'		=>	true,
			'total_line'	=>	(($total_line - 1)+$total_page_item_all)
		);
	}

	echo json_encode($output);
}

?>