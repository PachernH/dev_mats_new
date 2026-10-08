<?php

if(isset($_POST['op'])){
    require_once("../config/material.inc.php");
    require_once("../config/pack_list.inc.php");
    require_once("../config/rack.inc.php");
    $packlist = new PACK_LIST();
    $matl = new MATERIAL();
    if($_POST['op'] == "MATL_GET"){
      $resp = array();
      $resp[0] = $matl->MATERIAL_DETAILS($_POST['code']);
      $resp[1] = $matl->GENERATE_PID("VIRGIN");
      echo json_encode($resp);
    } else if($_POST['op'] == "MATL_GET_RETURN"){
      $resp = array();
      $resp[0] = ($matl->MATERIAL_DETAILS($_POST['code']));
      $resp[1] = ($matl->GENERATE_PID("RETURN"));
      echo json_encode($resp);
    } else if($_POST['op'] == "save"){
      /* $DOCUMENT = json_decode($_POST['DOCUMENT_DATA'], true); */ //ARRAY OF DOCUMENT DETAILS OF THE DELIVERY AND BEA CUKAI. DOCUMENT['X']
      $ITEMS = json_decode($_POST['ITEM_DATA'], true); //ARRAY OF ITEMS RECEIVED. ITEMS[0..n]['X']
      for($x = 0; $x <= sizeof($ITEMS)-1; $x++){
        $packlist->save_RECEIVED_ITEMS($_POST['DOCUMENT_DATA'], json_encode($ITEMS[$x]));
      }
      echo json_encode(true);
    } else if($_POST['op'] == "save_RETURN"){
      $inputs = json_decode($_POST['data'], true);
      $matl->INSERT_ORDER($_POST['data']);
      echo json_encode(true);
    } else if($_POST['op'] == "DELETE_DATA"){
      $matl->DELETE_PALETTE_DATA($_POST['data']);
      echo json_encode(true);
    } else if($_POST['op'] == "LOCATION_DATA"){
      echo json_encode($matl->MATERIAL_LOCATION_DATA($_POST['loc_id']));
    } else if($_POST['op'] == "VALIDATE_QR"){
      echo json_encode($matl->VALIDATE_INSTRUCTION_QR($_POST['data']));
    } else if($_POST['op'] == "CHECK_LOCATION"){
      echo json_encode($matl->GET_RACK_STATUS($_POST['data']));
    } else if($_POST['op'] == "SHIPMENT_STATUS"){
      echo json_encode($matl->GET_INSTRUCTION_MATL($_POST['d']));
    } else if($_POST['op'] == "GET_MANU_ITEM"){
      echo json_encode($matl->GET_MATERIAL_D($_POST['d']));
    }
  
  } else {
    echo json_encode(1);
  }

?>