<?php
// model/add_thicknet_diameter_defect_mats.php

session_start();
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบ Session หากไม่ได้ Login ให้ตอบกลับ error
if (!isset($_SESSION['ID'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit();
}

require_once '../dbcon_mats-new.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าและ Sanitize ข้อมูลจาก Form
    $pd_no        = isset($_POST['pd_no']) ? trim($_POST['pd_no']) : '';
    $pass_no      = isset($_POST['pass_no']) ? trim($_POST['pass_no']) : 'T'; // PRODUCT_SIDE
    $product_item = isset($_POST['item_no']) ? (int)$_POST['item_no'] : 0;     // PRODUCT_ITEM
    $thickness    = isset($_POST['DF_THICKNESS']) ? (float)$_POST['DF_THICKNESS'] : 0.00;
    $diameter1    = isset($_POST['DF_DIMAETER1']) ? (float)$_POST['DF_DIMAETER1'] : 0.00; // WIDTH_A
    $diameter2    = isset($_POST['DF_DIMAETER2']) ? (float)$_POST['DF_DIMAETER2'] : 0.00; // WIDTH_B
    $diameter3    = isset($_POST['DF_DIMAETER3']) ? (float)$_POST['DF_DIMAETER3'] : 0.00; // WIDTH_C
    $flatness     = isset($_POST['DF_DIMAETER4']) ? (float)$_POST['DF_DIMAETER4'] : 0.00; // FLATNESS

    // Validate ข้อมูลเบื้องต้น
    if (empty($pd_no) || $product_item <= 0 || empty($pass_no)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please select Product Side and Item correctly.'
        ]);
        exit();
    }

    try {
        // 1. ตรวจสอบว่ามีข้อมูลตาม PRODUCT_NO + PRODUCT_ITEM + PRODUCT_SIDE นี้อยู่แล้วหรือไม่
        $sql_check = "SELECT COUNT(*) FROM CRSHINSP2 
                      WHERE PRODUCT_NO = :pd_no 
                        AND PRODUCT_ITEM = :product_item 
                        AND PRODUCT_SIDE = :product_side";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([
            ':pd_no'        => $pd_no,
            ':product_item' => $product_item,
            ':product_side' => $pass_no
        ]);
        
        $exists = $stmt_check->fetchColumn() > 0;

        if ($exists) {
            // 2. กรณีมีข้อมูลอยู่แล้ว -> UPDATE
            $sql_update = "UPDATE CRSHINSP2 
                           SET THICKNESS = :thickness, 
                               WIDTH_A   = :width_a, 
                               WIDTH_B   = :width_b, 
                               WIDTH_C   = :width_c, 
                               FLATNESS  = :flatness
                           WHERE PRODUCT_NO   = :pd_no 
                             AND PRODUCT_ITEM = :product_item 
                             AND PRODUCT_SIDE = :product_side";

            $stmt_update = $conn->prepare($sql_update);
            $result = $stmt_update->execute([
                ':thickness'    => $thickness,
                ':width_a'      => $diameter1,
                ':width_b'      => $diameter2,
                ':width_c'      => $diameter3,
                ':flatness'     => $flatness,
                ':pd_no'        => $pd_no,
                ':product_item' => $product_item,
                ':product_side' => $pass_no
            ]);

            $action_text = 'updated';
        } else {
            // 3. กรณีไม่มีข้อมูล -> INSERT
            $sql_insert = "INSERT INTO CRSHINSP2 (
                                PRODUCT_NO, 
                                PRODUCT_ITEM, 
                                PRODUCT_SIDE, 
                                THICKNESS, 
                                WIDTH_A, 
                                WIDTH_B, 
                                WIDTH_C, 
                                FLATNESS
                            ) VALUES (
                                :pd_no, 
                                :product_item, 
                                :product_side, 
                                :thickness, 
                                :width_a, 
                                :width_b, 
                                :width_c, 
                                :flatness
                            )";

            $stmt_insert = $conn->prepare($sql_insert);
            $result = $stmt_insert->execute([
                ':pd_no'        => $pd_no,
                ':product_item' => $product_item,
                ':product_side' => $pass_no,
                ':thickness'    => $thickness,
                ':width_a'      => $diameter1,
                ':width_b'      => $diameter2,
                ':width_c'      => $diameter3,
                ':flatness'     => $flatness
            ]);

            $action_text = 'added';
        }

        if ($result) {
            echo json_encode([
                'status'  => 'success',
                'message' => "Record {$action_text} successfully.",
                'item_no' => $product_item,
                'side'    => $pass_no
            ]);
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Failed to 💾 Save record into database.'
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid request method.'
    ]);
}