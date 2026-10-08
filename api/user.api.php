<?php
	// เริ่ม session ก่อน ANY output
	session_start();

	// หลังจากนี้สามารถมี output ได้

	$path = dirname(__FILE__);
	require_once($path."/../config/su.inc.php");

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

    $json = json_decode($param);
	if ($json->operation == "delete-user"){
		$deleteUserID = $json->user_id;
		if ($deleteUserID == $userId){
			$response["success"] = false;
	    	$response["message"] = "You can't delete your UserID";
		} else {
			$User->deleteUser($deleteUserID);
			$response["success"] = true;
		    $response["message"] = "Delete user successfull";
		}
	} else if ($json->operation == "update-user"){
		$upUsername = $json->user_id;
		$upPassword = $json->password;
		$upRole = $json->role;

		if (!$User->getSingleUser($upUsername)){
			$response["success"] = false;
	    	$response["message"] = "UserID not found.";
		} else {
			$User->updateUser($upUsername, $upPassword, $upRole);
			$response["success"] = true;
    		$response["message"] = "Success";
		}
	} else if ($json->operation == "create-user"){
		$crUsername = $json->user_id;
		$crPassword = $json->password;
		$crRole = $json->role;
		if (!$User->getSingleUser($crUsername)){
			$User->createUser($crUsername, $crPassword, $crRole);
			$response["success"] = true;
    		$response["message"] = "Success";
		} else {
			$response["success"] = false;
	    	$response["message"] = "UserID already exists.";
		}
	} else if ($json->operation == "change-password"){
		$oldPassword = $json->old_password;
		$upPassword = $json->new_password;
		if (!$User->getUserByIDPass($userId, $oldPassword)){
			$response["success"] = false;
	    	$response["message"] = "UserID and old password are mismatch.";
		} else {
			$User->setPassword($upPassword, null);
			$response["success"] = true;
	    	$response["message"] = "Success";
		}
	} else {
		$response["success"] = false;
	    $response["message"] = "Required parameter(s) is missing";
	}
	echo json_encode($response);
?>