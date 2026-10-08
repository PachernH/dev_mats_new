<?php
	// เริ่ม session ก่อน ANY output
	session_start();

	// หลังจากนี้สามารถมี output ได้

	$path = dirname(__FILE__);
	require_once($path."/../config/su.inc.php");
	require_once($path."/../config/rack.inc.php");
	require_once($path."/../config/viewrackdetails.inc.php");

    header('Content-Type: application/json');
    $response = array();

    $User = new User();
    if( !$User->logged_in ) {
        $response["success"] = false;
	    $response["message"] = "Session timeout";
		echo json_encode($response);
		return;
    } else {
    	$param = "";
		if (ISSET($_GET['param'])){
			$param = $_GET['param'];
		} else {
			$param = file_get_contents("php://input");
		}
		if ( strlen($param) == 0 ) {
		    $response["success"] = false;
		    $response["message"] = "Required parameter(s) is missing";
			echo json_encode($response);
			return;
		}
    }
    $ViewRackDetails = new ViewRackDetails();
    $Rack = new Rack();

    $json = json_decode($param);
    if ($json->operation == "delete-rack"){
		$deleteRackID = $json->RackID;
		$Rack->deleteRack($deleteRackID);
		$response["success"] = true;
	    $response["message"] = "Delete rack successfull";
	} else if ($json->operation == "update-rack"){
		$upOldRackID = $json->OldRackID;
		$upRackID = $json->RackID;

		if (!$Rack->getSingleRack($upOldRackID)){
			$response["success"] = false;
	    	$response["message"] = "RackID not found.";
		} else {
			if (!$Rack->getSingleRack($upRackID)){
				$Rack->updateRack($upRackID, $upOldRackID);
				$response["success"] = true;
	    		$response["message"] = "Success";
			} else {
				$response["success"] = false;
	    		$response["message"] = "RackID ".$upRackID." already exists.";
			}
		}
	} else if ($json->operation == "create-rack"){
		$crRackID = $json->RackID;
		if (!$Rack->getSingleRack($crRackID)){
			$Rack->createRack($crRackID);
			$response["success"] = true;
    		$response["message"] = "Success";
		} else {
			$response["success"] = false;
	    	$response["message"] = "RackID already exists.";
		}
	} else if ($json->operation == "get-data"){
		$RackID = $json->RackID;
		$ret = $Rack->getSingleRack($RackID);
		if (!$ret){
			$response["success"] = false;
	    	$response["message"] = "RackID not found";
		} else {
			$response["success"] = true;
		    $response["message"] = "";
		    $response["data"] = $ret;
		}
	} else {
		$response["success"] = false;
	    $response["message"] = "Required parameter(s) is missing";
	}
	echo json_encode($response);
?>