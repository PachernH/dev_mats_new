<?php
	$path = dirname(__FILE__);

	include($path."/../config/config.inc.php");
	$table = 'tbl_Rack';

	$primaryKey = 'RackID';
	 
	$columns = array(
	    array( 'db' => 'RackID', 'dt' => 0 )
	);

	$conn = new PDO( "sqlsrv:server=".$GLOBALS["db_server_name"]." ; Database=".$GLOBALS["db_name"], "", "");  
	$conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	 
	require( 'ssp.class.php' );
	 
	echo json_encode(
	    SSP::simple( $_GET, $conn, $table, $primaryKey, $columns )
	);
?>