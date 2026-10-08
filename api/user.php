<?php
	require_once(dirname(__FILE__)."/../config/su.inc.php");
	
	$search = $_GET['search']['value'];
	$offset = $_GET['start'];
	$size = $_GET['length'];
	$draw = intval($_GET['draw']);

	$User = new User();
	$datas = $User->getListDT($search, $offset, $size);
	$total = $User->getSizeDT($search);

    $output = array(
    	"draw" => $draw,
        "recordsTotal" => $total,
        "recordsFiltered" => $total,
        "data" => $datas
    );

    echo json_encode( $output );
?>