<?php
	// เริ่ม session ก่อน ANY output
	session_start();

	// หลังจากนี้สามารถมี output ได้

	$path = dirname(__FILE__);
	require_once($path."/../config/su.inc.php");
	require_once($path."/../config/pickList.inc.php");
    
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
    $userId = $_SESSION[$User->sessionName]["userId"];

    $PickList = new PickList();
    $json = json_decode($param);
	if ($json->operation == "update-box"){
		$RackID = $json->RackID;
		$ProductionDate = $json->ProductionDate;
		$partCode = $json->partCode;
		$ppuCount = $json->ppuCount;

		$isExist = $PickList->isExist($RackID, $ProductionDate, $partCode);
		if ($isExist){
			$PickList->updatePickList($RackID, $ProductionDate, $partCode, $ppuCount);
		} else {
			$PickList->insertPickList($RackID, $ProductionDate, $partCode, $ppuCount);
		}
		$response["success"] = true;
	    $response["message"] = "Box Info Updated";
	} else {
		$response["success"] = false;
	    $response["message"] = "Required parameter(s) is missing";
	}
	echo json_encode($response);
?>