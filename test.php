<?php
	//mkdir("include/Root/Test");

    //phpinfo();

    $t=time();
    echo $t;
   
    echo "<br>";
    $code = substr($t,6,4);
    
    echo $code;

    $d=date('Y-m-d');

    $y=date('Y');
    $m=date('m');
    $dc=date('d');
    $dl=date('t');


    echo "<br>";
    echo "<br>";
    echo "<br>";

    echo $y;
    echo "<br>";
    echo $m;
    echo "<br>";
    echo $dc;
    echo "<br>";
    echo $dl;

    echo "<br>";
    echo "<br>";
    echo "<br>";

    $datef2 = date('Y-m-01',strtotime($d))." 00:00:00";
    $datet2 = date('Y-m-t',strtotime($d))." 23:59:59";

    echo "<br>";
    echo $datef2;
    echo "<br>";
    echo $datet2;
    
    echo "<br>";
    echo "<br>";
    echo "<br>";

    $dates = '2023-02-01';
    //$dates = $y."-".$m."-01";

    $datef = date('Y-m-01',strtotime($dates))." 00:00:00";
    $datet = date('Y-m-t',strtotime($dates))." 23:59:59";


    echo "<br>";
    echo $datef;
    echo "<br>";
    echo $datet;
    echo "<br>";
    echo $dates;
    echo "<br>";
    echo "<br>";
    $datetime = date('H:i:s');
    echo $datetime;

    echo "<br>";
    echo "<br>";
    echo $dates.' '.strtotime($d);

    echo "<br>";
    echo "<br>";
    echo date("Y-m-d H:i:s", time());
?>