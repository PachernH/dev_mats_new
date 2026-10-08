<?php
header('Content-Type: application/json; charset=utf-8');

// รับค่า COIL จาก GET parameter
$searchdata = isset($_GET['COIL']) ? trim($_GET['COIL']) : '';
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 0;

$data = array();

include("../dbcon_mats-new.php");

if (!empty($searchdata)) {
    // ดึงข้อมูล 14 คอลัมน์ตามคำสั่ง SQL ที่กำหนด
    $sql = "SELECT 
                COIL_NO, 
                PRODUCT_REFERENCE, 
                COIL_TYPE, 
                ALLOY, 
                TEMPER, 
                GRADE, 
                THICKNESS, 
                WIDTH, 
                SURFACE_GRADE, 
                METALLURGICAL_GRADE, 
                COIL_ACTUALWEIGHT, 
                COIL_BALANCEWEIGHT, 
                COIL_WORKPROCESS, 
                COIL_NEXTPROCESS, 
                COIL_STATUS 
            FROM COILPROD1 
            WHERE COIL_NO = :coil";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':coil', $searchdata, PDO::PARAM_STR);
    $stmt->execute();

    $i = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[$i][0]  = $row['COIL_NO'] ?? '';
        $data[$i][1]  = $row['PRODUCT_REFERENCE'] ?? '';
        $data[$i][2]  = $row['COIL_TYPE'] ?? '';
        $data[$i][3]  = $row['ALLOY'] ?? '';
        $data[$i][4]  = $row['TEMPER'] ?? '';
        $data[$i][5]  = $row['GRADE'] ?? '';
        $data[$i][6]  = number_format((float)($row['THICKNESS'] ?? 0), 2);
        $data[$i][7]  = number_format((float)($row['WIDTH'] ?? 0), 2);
        $data[$i][8]  = $row['SURFACE_GRADE'] ?? '';
        $data[$i][9]  = $row['METALLURGICAL_GRADE'] ?? '';
        $data[$i][10] = number_format((float)($row['COIL_ACTUALWEIGHT'] ?? 0), 2);
        $data[$i][11] = number_format((float)($row['COIL_BALANCEWEIGHT'] ?? 0), 2);
        $data[$i][12] = $row['COIL_WORKPROCESS'] ?? '';
        $data[$i][13] = $row['COIL_NEXTPROCESS'] ?? '';
        $data[$i][14] = $row['COIL_STATUS'] ?? '';

        $i++;
    }
}

$total = count($data);

$json_data = array(
    "draw"            => $draw,
    "recordsTotal"    => $total,
    "recordsFiltered" => $total,
    "data"            => $data
);

echo json_encode($json_data);
?>