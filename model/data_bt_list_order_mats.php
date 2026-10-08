<?php

		$searchdata = $_GET['BATCH'];
		$i = 0;
		$search = $_GET['search']['value'];
		$offset = $_GET['start'];
		$size = $_GET['length'];
		$draw = intval($_GET['draw']);
		$data = array();

		include("../dbcon_mats-new.php");
		$sql="SELECT TOP(20) A.BATCH_NO, A.BATCH_DATE, A.MATERIAL_IN, A.ALLOY, A.LINE_PROCESS, A.AL_INGOT, A.COIL_REMELT, A.CAST_SCRAP, A.OTHER_SCRAP, A.SCRAP_WIRE, A.RECYCLE_SCRAP, A.MASTER_ALLOY, A.AL_REMAIN, A.TOTAL_CHARGE, A.TOTAL_TRANSFER, A.TOTAL_CASTSCRAP, A.TOTAL_DROSS, A.TOTAL_PRODUCE, A.BALANCE_BATCH, 
        	  B.CAR_CHARGE, B.TRANSFER_DEGREE, B.CTRN_REMARK FROM FRNCPRCS1 AS A INNER JOIN CHRGTRNS1 AS B ON A.BATCH_NO = B.BATCH_NO where A.BATCH_NO like '%".$searchdata."%' ORDER BY A.BATCH_DATE desc";			
		$stmt = $conn->prepare($sql);
        $stmt->execute();

			while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$data[$i][0] = $row['BATCH_NO']; 
					$data[$i][1] = $row['AL_REMAIN'];
					$data[$i][2] = $row['AL_INGOT'];
					$data[$i][3] = $row['COIL_REMELT'];
					$data[$i][4] = $row['CAST_SCRAP'];
					$data[$i][5] = $row['OTHER_SCRAP'];
                    $data[$i][6] = $row['SCRAP_WIRE'];
					$data[$i][7] = $row['RECYCLE_SCRAP'];
					$data[$i][8] = $row['MASTER_ALLOY'];
					$i++;
			}

           $stmt = $conn->prepare($sql);
		   $x = 0;
		   while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$x++;
		   }
			 
$total = $x;
$json_data = array(
"draw"            => intval( $_REQUEST['draw'] ), #
"recordsTotal"    =>  intval($total), #total number of records
"recordsFiltered" =>  intval($total), #Total number of filtered results after search. no search = number will be same for recordsTotal
"data"            => $data #fetched record data in multidimensional array
);
echo json_encode($json_data);

?>