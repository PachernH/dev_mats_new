<?php

if(isset($_POST['OP'])){
  require_once("../config/material.inc.php");
  $matl = new MATERIAL();
  $resp = array();
  if($_POST['OP'] == "ASSIGN_PALETTE"){
    $palette_qr = explode("*", $_POST['p_qr']);

    if(sizeof($palette_qr) != 4){
      $resp[0] = false;
      $resp[1] = 'Invalid Palette QR Code';
      echo json_encode($resp);
      exit();
    } //add also checking for validity of location input

    if($_POST['cancel'] == "true"){ // cancel if true
      //cancel
      $matl->ASSIGN_PALETTE_LOCATION($palette_qr[0], $_POST['l_qr'], true);
      $resp[0] = true;
      $resp[1] = "Cancel Successful";
    } else {
      if($matl->PALETTE_CHECK($palette_qr[0])){ //check if palette_id exists AND have location_id
        $matl->ASSIGN_PALETTE_LOCATION($palette_qr[0], $_POST['l_qr'], false);
        $resp[0] = true;
        $resp[1] = "Palette successfully assigned";
      } else {
        //else, throw error. Must cancel scan first if want to change location
        $resp[0] = false;
        $resp[1] = "Palette Already Assigned or cancel first if you want to change the location";
      }
    }

  } else if($_POST['OP'] == "ISSUE_MATL"){
    //N00000199141*PFU051D*Toyolac 700-X01 SHE8969T*TOYOLAC 700-X01*BL Gray*ABS*15-Jul-19*#04
    //N00000199138*IK012Y*Cosmoplene AZ564*Cosmoplene*Natural *PP*15-Jul-19*12
    //$d[0] = ID
    //$d[1] = PART_CODE
    //$d[2] = MATL_NAME
    //$d[3] = MATL_CODE
    //$d[4] = COLOR
    //$d[5] = TYPE
    //$d[6] = DATE_CREATE
    //$d[7] = MC_NO

    //PID-1907000019*ABS028*25*950
    //$d[0] = PALETTE_ID
    //$d[1] = MATL_CODE
    //$d[2] = WKG
    //$d[3] = TOTAL_WT

    if((sizeof(explode("*", $_POST['p_qr'])) != 4) || (sizeof(explode("*", $_POST['m_qr'])) != 8)){
      $resp[0] = false;
      $resp[1] = "Invalid QR Codes";
      echo json_encode($resp);
      exit();
    } 

    if($matl->MATL_ISSUE_CHECK($_POST['p_qr'], $_POST['m_qr'])){
       if($matl->ISSUE_MATL($_POST['p_qr'], $_POST['m_qr'], $_POST['cancel'])){
        //update sukses
        $resp[0] = true;
        $resp[1] = "ISSUING/CANCEL SUCCESSFUL";
       } else {
        //update fail
        $resp[0] = false;
        $resp[1] = "ISSUING/CANCEL FAIL";
       }
    } else {
      $resp[0] = false;
      $resp[1] = "QR CODE MISMATCH";
    }

  
  } else if($_POST['OP'] == "RETURN_SCAN"){

    if((sizeof(explode("*", $_POST['lbl_qr'])) != 8)){
      $resp[0] = false;
      $resp[1] = "Invalid QR Codes";
      echo json_encode($resp);
      exit();
    }

    if($matl->MATL_ISSUE_CHECK("1*".$_POST['matl_code'], $_POST['lbl_qr'])){
      if($matl->RETURN_MATERIAL($_POST['lbl_qr'], $_POST['RID'], $_POST['location_id'])){
        $resp[0] = true;
        $resp[1] = "RETURN SUKSES";
      } else {
        $resp[0] = false;
        $resp[1] = "LABEL NOT ISSUED/ALREADY SCANNED";
      }
    } else {
      $resp[0] = false;
      $resp[1] = "MATERIAL MISMATCH";
    }


  }

  echo json_encode($resp);


} else {
  echo json_encode("Invalid Operation");
}



?>