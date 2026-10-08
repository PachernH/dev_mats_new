<?php
header('Content-Type: application/json');

$searchdata = isset($_GET['CPID']) ? trim($_GET['CPID']) : '';
$response = ['status' => 'error', 'data' => null];

if (!empty($searchdata)) {
    include("../dbcon_mats-new.php");
    
    // ค้นหาแบบตรงตัวเฉพาะดึงบริษัทนั้นๆ ขึ้นมาแถวเดียว
    $sql = "SELECT COMPANY_ID, COMPANY, ADDRESS1, ADDRESS2, ADDRESS3, TELEPHONE, FAX, EMAIL, CONTACT_PERSON, CONTACT_POSITION 
            FROM CMPNMSTR1 
            WHERE COMPANY_ID = :searchdata";		
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':searchdata' => $searchdata]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $response['status'] = 'success';
            // เก็บข้อมูลเป็น Array 1 มิติ เรียงตามลำดับดัชนี 0 ถึง 9 เพื่อให้หน้าบ้านวนลูปง่าย
            $response['data'] = [
                $row['COMPANY_ID'] ?? '',
                $row['COMPANY'] ?? '',
                $row['ADDRESS1'] ?? '',
                $row['ADDRESS2'] ?? '',
                $row['ADDRESS3'] ?? '',
                $row['TELEPHONE'] ?? '',
                $row['FAX'] ?? '',
                $row['EMAIL'] ?? '',
                $row['CONTACT_PERSON'] ?? '',
                $row['CONTACT_POSITION'] ?? ''
            ];
        }
    } catch (PDOException $e) {
        $response['error'] = $e->getMessage();
    }
}

echo json_encode($response);
exit;
?>