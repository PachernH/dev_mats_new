<?php
// model/insert_package_mats.php
session_start();

// ป้องกันข้อความชั่วคราวหลุดไปแปดเปื้อน JSON
ob_start();

// ตั้งค่า Header ให้ส่งผลลัพธ์กลับเป็น JSON
header('Content-Type: application/json; charset=utf-8');

// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once '../dbcon_mats-new.php'; 

// ล้างข้อความ echo หรือ warning ใดๆ ที่อาจติดมาจากไฟล์ dbcon
ob_clean();

$response = [
    'status' => 'error',
    'message' => 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าจาก Form
    $code_top    = isset($_POST['ma_code_top']) ? trim($_POST['ma_code_top']) : '';
    $code_bottom = isset($_POST['ma_code_bottom']) ? trim($_POST['ma_code_bottom']) : '';
    
    $pkg_type    = isset($_POST['pkg_type']) ? trim($_POST['pkg_type']) : '';
    $treatment   = isset($_POST['treatment']) ? trim($_POST['treatment']) : '';
    $unit        = isset($_POST['unit_measure']) ? trim($_POST['unit_measure']) : 'inch';
    
    $raw_w       = isset($_POST['dim_w']) && $_POST['dim_w'] !== '' ? floatval($_POST['dim_w']) : 0;
    $raw_l       = isset($_POST['dim_l']) && $_POST['dim_l'] !== '' ? floatval($_POST['dim_l']) : 0;
    $raw_h       = isset($_POST['dim_h']) && $_POST['dim_h'] !== '' ? floatval($_POST['dim_h']) : 0;
    
    $dim_w_tol   = isset($_POST['dim_w_tol']) && $_POST['dim_w_tol'] !== '' ? floatval($_POST['dim_w_tol']) : 0;
    $dim_l_tol   = isset($_POST['dim_l_tol']) && $_POST['dim_l_tol'] !== '' ? floatval($_POST['dim_l_tol']) : 0;
    
    $pkg_weight  = isset($_POST['pkg_weight']) && $_POST['pkg_weight'] !== '' ? floatval($_POST['pkg_weight']) : 0;
    
    $ma_it       = isset($_POST['ma_it'])  ? trim($_POST['ma_it'])  : '';
    $ma_dec      = isset($_POST['ma_dec']) ? trim($_POST['ma_dec']) : '';
    $ma_ty       = isset($_POST['ma_ty'])  ? trim($_POST['ma_ty'])  : 'PK';
    $ma_qty      = isset($_POST['ma_qty']) && $_POST['ma_qty'] !== '' ? floatval($_POST['ma_qty']) : null;

    // จัดกลุ่มรายการ MATERIAL CODE ที่ต้องทำการบันทึก
    $codes_to_insert = array();
    if (!empty($code_top)) {
        $codes_to_insert[] = array('code' => $code_top, 'sub_type' => 'TP');
    }
    if (!empty($code_bottom)) {
        $codes_to_insert[] = array('code' => $code_bottom, 'sub_type' => 'BT');
    }

    // ตรวจสอบข้อมูลจำเป็น
    if (empty($codes_to_insert) || empty($ma_it)) {
        echo json_encode([
            'status' => 'error',
            'error'  => 'กรุณาระบุข้อมูลเพื่อสร้าง MATERIAL CODE และเลือก ITEMNUM MP2 ให้ครบถ้วน'
        ]);
        exit;
    }

    // คำนวณแปลงหน่วย Inch <-> MM สำหรับเตรียมบันทึกลง MTRLSPCT1
    if ($unit === 'inch') {
        $w_inch = round($raw_w);
        $l_inch = round($raw_l);
        $h_inch = round($raw_h);
        
        $w_mm   = round($raw_w * 25.4);
        $l_mm   = round($raw_l * 25.4);
        $h_mm   = round($raw_h * 25.4);
    } else {
        $w_mm   = round($raw_w);
        $l_mm   = round($raw_l);
        $h_mm   = round($raw_h);

        $w_inch = round($raw_w / 25.4);
        $l_inch = round($raw_l / 25.4);
        $h_inch = round($raw_h / 25.4);
    }

    // คำนวณ Plus/Minus %
    $w_plus  = round($dim_w_tol);
    $w_minus = round($dim_w_tol);
    $l_plus  = round($dim_l_tol);
    $l_minus = round($dim_l_tol);
    $weight  = round($pkg_weight);

    // ตัดความยาวให้พอดีกับสเปก Database
    $ma_it     = mb_substr($ma_it, 0, 25);
    $ma_dec    = mb_substr($ma_dec, 0, 50);
    $ma_ty     = mb_substr($ma_ty, 0, 2);
    $treatment = mb_substr($treatment, 0, 2);

    try {
        // เริ่มต้น Transaction
        $conn->beginTransaction();

        foreach ($codes_to_insert as $item) {
            $current_code = mb_substr($item['code'], 0, 20);
            $sub_type     = mb_substr($item['sub_type'], 0, 2);

            // 1. ตรวจสอบว่ามี MATERIAL_CODE ซ้ำใน MTRLMSTR1 หรือไม่
            $check_sql = "SELECT COUNT(*) FROM MTRLMSTR1 WHERE MATERIAL_CODE = :ma_code";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':ma_code', $current_code);
            $check_stmt->execute();

            if ($check_stmt->fetchColumn() > 0) {
                $conn->rollBack();
                echo json_encode([
                    'status' => 'error',
                    'error'  => 'รหัส MATERIAL CODE (' . $current_code . ') มีอยู่ในระบบแล้ว'
                ]);
                exit;
            }

            // 2. Insert เข้า Table 1: MTRLMSTR1
            $sql1 = "INSERT INTO MTRLMSTR1 (MATERIAL_CODE, ITEMNUM, DESCRIPTION, MATERIAL_TYPE, QTY_ONHAND) 
                     VALUES (:ma_code, :ma_it, :ma_dec, :ma_ty, :ma_qty)";
            
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bindParam(':ma_code', $current_code);
            $stmt1->bindParam(':ma_it', $ma_it);
            $stmt1->bindParam(':ma_dec', $ma_dec);
            $stmt1->bindParam(':ma_ty', $ma_ty);

            if ($ma_qty === null) {
                $stmt1->bindValue(':ma_qty', null, PDO::PARAM_NULL);
            } else {
                $stmt1->bindValue(':ma_qty', $ma_qty, PDO::PARAM_STR);
            }
            $stmt1->execute();

            // 3. Insert เข้า Table 2: MTRLSPCT1
            $sql2 = "INSERT INTO MTRLSPCT1 (
                        MATERIAL_CODE, SMATERIAL_CODE, PACKAGE_TYPE, PACKAGE_TREATMENT,
                        WIDTH_INCH, LENGTH_INCH, HIGH_INCH,
                        WIDTH_MM, WIDTH_PLUS, WIDTH_MINUS,
                        LENGTH_MM, LENGTH_PLUS, LENGTH_MINUS,
                        HIGH_MM, PACKAGE_WEIGHT
                     ) VALUES (
                        :ma_code, :smaterial_code, :pkg_type, :pkg_treatment,
                        :w_inch, :l_inch, :h_inch,
                        :w_mm, :w_plus, :w_minus,
                        :l_mm, :l_plus, :l_minus,
                        :h_mm, :pkg_weight
                     )";

            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':ma_code', $current_code);
            $stmt2->bindParam(':smaterial_code', $current_code);
            $stmt2->bindParam(':pkg_type', $sub_type);
            $stmt2->bindParam(':pkg_treatment', $treatment);
            
            $stmt2->bindParam(':w_inch', $w_inch, PDO::PARAM_INT);
            $stmt2->bindParam(':l_inch', $l_inch, PDO::PARAM_INT);
            $stmt2->bindParam(':h_inch', $h_inch, PDO::PARAM_INT);
            
            $stmt2->bindParam(':w_mm', $w_mm, PDO::PARAM_INT);
            $stmt2->bindParam(':w_plus', $w_plus, PDO::PARAM_INT);
            $stmt2->bindParam(':w_minus', $w_minus, PDO::PARAM_INT);
            
            $stmt2->bindParam(':l_mm', $l_mm, PDO::PARAM_INT);
            $stmt2->bindParam(':l_plus', $l_plus, PDO::PARAM_INT);
            $stmt2->bindParam(':l_minus', $l_minus, PDO::PARAM_INT);
            
            $stmt2->bindParam(':h_mm', $h_mm, PDO::PARAM_INT);
            $stmt2->bindParam(':pkg_weight', $weight, PDO::PARAM_INT);

            $stmt2->execute();
        }

        // ยืนยันการบันทึกข้อมูลสำเร็จทั้ง 2 ตาราง
        $conn->commit();

        $response = [
            'status' => 'success',
            'message' => true
        ];

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $response = [
            'status' => 'error',
            'error' => 'Database Error: ' . $e->getMessage()
        ];
    }

} else {
    $response = [
        'status' => 'error',
        'error' => 'Invalid Request Method'
    ];
}

echo json_encode($response);
exit;