<?php
	require_once(dirname(__FILE__)."/../config/rack.inc.php");
	
	$search = $_GET['search']['value'];
	$offset = $_GET['start'];
	$size = $_GET['length'];
	$draw = intval($_GET['draw']);

	$Rack = new Rack();
	$racks = $Rack->getListDT($search, $offset, $size);
	$total = $Rack->getSizeDT($search);

    $output = array(
    	"draw" => $draw,
        "recordsTotal" => $total,
        "recordsFiltered" => $total,
        "data" => $racks
    );

    echo json_encode( $output );
?>