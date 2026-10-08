<?php
	require_once(dirname(__FILE__)."/../config/rack.inc.php");
	
	$RackID = "";
	if (ISSET($_GET['RackID'])){
		$RackID = $_GET['RackID'];
	}

	$search = $_GET['search']['value'];
	$offset = $_GET['start'];
	$size = $_GET['length'];
	$draw = intval($_GET['draw']);

	$Rack = new Rack();
	$racks = $Rack->getDetailListDT($RackID, $search, $offset, $size);
	$total = $Rack->getDetailSizeDT($RackID, $search);

    $output = array(
    	"draw" => $draw,
        "recordsTotal" => $total,
        "recordsFiltered" => $total,
        "data" => $racks
    );

    echo json_encode( $output );
?>