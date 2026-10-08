<?php

require_once(dirname(__FILE__)."/../config/pack_list.inc.php");

if(isset($_POST['op']) && isset($_POST['data'])){
   $packlist = new PACK_LIST(); 
    $resp = array();
    if($_POST['op'] == "create-document"){
        $packlist->create_document($_POST['data']);
        $resp['success'] = true;
    } else if($_POST['op'] == "add-item"){
        $packlist->save_item($_POST['data']);
        $resp['success'] = true;
    } else if($_POST['op'] == "delete-item"){
        $packlist->delete_item($_POST['data']);
        $resp['success'] = true;
    } else if($_POST['op'] == "delete-document"){
        $packlist->delete_document($_POST['data']);
        $resp['success'] = true;
    } else if($_POST['op'] == "edit-item"){
        $resp['success'] = true;
        $resp['data'] = $packlist->fetch_single_item($_POST['data']);
    } else if($_POST['op'] == "save-edit"){
        $packlist->update_item($_POST['data']);
        $resp['data'] = true;
    }else if($_POST['op'] == "save-edit-lot"){
        $packlist->update_item_lot($_POST['data']);
        $resp['data'] = true;
    } else if($_POST['op'] == "change-capacity"){
        $packlist->update_location($_POST['data']);
        $resp['data'] = true;
    } else if($_POST['op'] == "adj-inve"){
        $packlist->adj_update_inve($_POST['data']);
        $resp['data'] = true;    
    }else if($_POST['op'] == "print"){
        $packlist->save_print($_POST['data']);
        $resp['data'] = true;
    }





echo json_encode($resp);
}





?>