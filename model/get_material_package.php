<?php
session_start();
header('Content-Type: application/json');
include("../dbcon_mats-new.php");

$action = $_GET['action'] ?? '';

// 1. ดึงรายการสำหรับ Modal Lookup (ตามเงื่อนไข cmdMaterialLookup_Click ใน pk1.txt)
if ($action === 'lookup') {
    $product_id        = trim($_POST['product_id'] ?? '');
    $width             = (float)($_POST['width'] ?? 0);
    $length            = (float)($_POST['length'] ?? 0);
    $customer_location = trim($_POST['customer_location'] ?? 'L');

    $strMaterialWidth  = ($width < 1000) ? sprintf('%08.3f', $width) : sprintf('%08.3f', $width);
    $strMaterialLength = ($length < 1000) ? sprintf('%08.3f', $length) : sprintf('%08.3f', $length);
    $treatment         = ($customer_location === 'O') ? 'HT' : 'NL';

    $where_sql = "WHERE PRODUCT_ID = :pid AND PACKAGE_TREATMENT = :treatment AND PACKAGE_TYPE = 'BT' ";
    $params = [':pid' => $product_id, ':treatment' => $treatment];

    if ($product_id === 'CC') {
        $where_sql .= "AND WIDTH_FROM <= :w_val AND WIDTH_TO >= :w_val ";
        $params[':w_val'] = $strMaterialWidth;
    } else {
        if ($width >= $length && $product_id === 'NC') {
            $where_sql .= "AND (WIDTH_FROM <= :l_val AND WIDTH_TO >= :l_val) AND (LENGTH_FROM <= :w_val AND LENGTH_TO >= :w_val) ";
            $params[':l_val'] = $strMaterialLength;
            $params[':w_val'] = $strMaterialWidth;
        } else {
            $where_sql .= "AND (WIDTH_FROM <= :w_val AND WIDTH_TO >= :w_val) AND (LENGTH_FROM <= :l_val AND LENGTH_TO >= :l_val) ";
            $params[':w_val'] = $strMaterialWidth;
            $params[':l_val'] = $strMaterialLength;
        }
    }

    $sql = "SELECT PACKAGE_PRIORITY, MATERIAL_CODE, WIDTH_FROM, WIDTH_TO, LENGTH_FROM, LENGTH_TO, 
                   STACK_ROW, STACK_COLUMN, PACKAGE_WEIGHT, SMATERIAL_CODE 
            FROM Material_PackageMaster1 
            {$where_sql} 
            ORDER BY PACKAGE_PRIORITY ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $r) {
        $stack_row = (int)($r['STACK_ROW'] ?? 0);
        $stack_col = (int)($r['STACK_COLUMN'] ?? 0);
        $stack_total = ($stack_col == 0) ? $stack_row : ($stack_row * $stack_col);

        $results[] = [
            'PACKAGE_PRIORITY' => $r['PACKAGE_PRIORITY'],
            'MATERIAL_CODE'    => trim($r['MATERIAL_CODE']),
            'WIDTH_FROM'       => $r['WIDTH_FROM'],
            'WIDTH_TO'         => $r['WIDTH_TO'],
            'LENGTH_FROM'      => $r['LENGTH_FROM'],
            'LENGTH_TO'        => $r['LENGTH_TO'],
            'STACK_TOTAL'      => $stack_total,
            'PACKAGE_WEIGHT'   => (float)$r['PACKAGE_WEIGHT'],
            'SMATERIAL_CODE'   => trim($r['SMATERIAL_CODE'] ?? '')
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);
    exit;
}

// 2. ดึงรายละเอียดพาเลท + คำนวณฝาบน MTRLSPCT1 (ตาม GetMaterialPackage ใน pk1.txt)
if ($action === 'details') {
    $material_code = trim($_POST['material_code'] ?? '');
    $product_id    = trim($_POST['product_id'] ?? '');
    $width         = (float)($_POST['width'] ?? 0);
    $length        = (float)($_POST['length'] ?? 0);

    $strWidth  = ($width < 1000) ? sprintf('%08.3f', $width) : sprintf('%08.3f', $width);
    $strLength = ($length < 1000) ? sprintf('%08.3f', $length) : sprintf('%08.3f', $length);

    $sql = "SELECT TOP 1 * FROM Material_PackageMaster1 
            WHERE PRODUCT_ID = :pid AND MATERIAL_CODE = :mcode 
              AND (WIDTH_FROM <= :w_val AND WIDTH_TO >= :w_val) 
              AND (LENGTH_FROM <= :l_val AND LENGTH_TO >= :l_val)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pid'   => $product_id,
        ':mcode' => $material_code,
        ':w_val' => $strWidth,
        ':l_val' => $strLength
    ]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลพาเลทชนิดนี้ในระบบ']);
        exit;
    }

    $stack_row = (int)($p['STACK_ROW'] ?? 0);
    $stack_col = (int)($p['STACK_COLUMN'] ?? 0);
    $stack_number = ($stack_col == 0) ? $stack_row : ($stack_row * $stack_col);

    $package_width  = (int)($p['WIDTH_MM'] ?? 0);
    $width_plus     = (int)($p['WIDTH_PLUS'] ?? 30);
    $width_minus    = (int)($p['WIDTH_MINUS'] ?? 30);

    $package_length = (int)($p['LENGTH_MM'] ?? 0);
    $length_plus    = (int)($p['LENGTH_PLUS'] ?? 30);
    $length_minus   = (int)($p['LENGTH_MINUS'] ?? 30);

    $package_high          = (int)($p['HIGH_MM'] ?? 0);
    $bottom_package_weight = (int)($p['PACKAGE_WEIGHT'] ?? 0);
    $top_package_code      = trim($p['SMATERIAL_CODE'] ?? '');
    $top_package_weight    = 0;

    // คำนวณฝาบนจาก MTRLSPCT1 ตามที่คุณอ้างอิง
    if (!empty($top_package_code)) {
        $stmt_top = $conn->prepare("SELECT TOP 1 HIGH_MM, PACKAGE_WEIGHT FROM MTRLSPCT1 WHERE PACKAGE_TYPE = 'TP' AND MATERIAL_CODE = :top_code");
        $stmt_top->execute([':top_code' => $top_package_code]);
        $top = $stmt_top->fetch(PDO::FETCH_ASSOC);

        if ($top) {
            $package_high       += (int)($top['HIGH_MM'] ?? 0);
            $top_package_weight  = (int)($top['PACKAGE_WEIGHT'] ?? 0);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'material_code'         => $material_code,
            'stack_number'          => $stack_number,
            'package_width'         => $package_width,
            'width_plus'            => $width_plus,
            'width_minus'           => $width_minus,
            'package_length'        => $package_length,
            'length_plus'           => $length_plus,
            'length_minus'          => $length_minus,
            'package_high'          => $package_high,
            'bottom_package_weight' => $bottom_package_weight,
            'top_package_code'      => $top_package_code,
            'top_package_weight'    => $top_package_weight
        ]
    ]);
    exit;
}