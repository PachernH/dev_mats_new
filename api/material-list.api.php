<?php
require_once(dirname(__FILE__)."/../config/material.inc.php");

if(isset($_POST['op']) && isset($_POST['data'])){
   $listdata = new MATERIAL(); 
    $resp = array();
    if($_POST['op'] == "sum_palette"){
        //$listdata->GET_PALETTE_QTY($_POST['data']);
        $resp['data'] = $listdata->GET_PALETTE_QTY($_POST['data']);;
        //$resp=$listdata;
//    } else if($_POST['op'] == "add-item"){
//        $listdata->save_item($_POST['data']);
//        $resp['success'] = true;
//    } 
//    }else if($_POST['op'] == "print"){
//        $listdata->save_print($_POST['data']);
//        $resp['data'] = true;
    }

echo json_encode($resp);
}

?>