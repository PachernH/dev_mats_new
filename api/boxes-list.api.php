<?php

require_once(dirname(__FILE__)."/../config/boxes_list.inc.php");

if(isset($_POST['op']) && isset($_POST['data'])){
   $boxeslist = new BOXES_LIST(); 
    $resp = array();
    if($_POST['op'] == "save-new-boxes"){
        $boxeslist->save_cap_boxes($_POST['data']);
        $resp['data'] = true;
    } else if($_POST['op'] == "save-del-boxes"){
        $boxeslist->save_del_cap_boxes($_POST['data']);
        $resp['data'] = true;
    }

echo json_encode($resp);
}





?>