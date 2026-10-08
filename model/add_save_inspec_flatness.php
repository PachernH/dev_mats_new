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
    $pd_no        = isset($_POST['PRODUCT_NO']) ? trim($_POST['PRODUCT_NO']) : '';
    $pass_no      = isset($_POST['PRODUCT_SIDE']) ? trim($_POST['PRODUCT_SIDE']) : 'T'; // PRODUCT_SIDE
    $product_item = isset($_POST['PRODUCT_ITEM']) ? (int)$_POST['PRODUCT_ITEM'] : 0;     // PRODUCT_ITEM

    $length_a     = isset($_POST['LENGTH_A']) ? (float)$_POST['LENGTH_A'] : 0.00;
    $length_b     = isset($_POST['LENGTH_B']) ? (float)$_POST['LENGTH_B'] : 0.00;

    $squareness_a     = isset($_POST['SQUARENESS_A']) ? (float)$_POST['SQUARENESS_A'] : 0.00;
    $squareness_b     = isset($_POST['SQUARENESS_B']) ? (float)$_POST['SQUARENESS_B'] : 0.00;
    $squareness_ab     = isset($_POST['SQUARENESS_AB']) ? (float)$_POST['SQUARENESS_AB'] : 0.00;

    $flatness_w1     = isset($_POST['FLATNESS_W1']) ? (float)$_POST['FLATNESS_W1'] : 0.00;
    $flatness_w2     = isset($_POST['FLATNESS_W2']) ? (float)$_POST['FLATNESS_W2'] : 0.00;

    $flatness_l1     = isset($_POST['FLATNESS_L1']) ? (float)$_POST['FLATNESS_L1'] : 0.00;
    $flatness_l2     = isset($_POST['FLATNESS_L2']) ? (float)$_POST['FLATNESS_L2'] : 0.00;


    $flatness_a    = isset($_POST['FLATNESS_A']) ? (float)$_POST['FLATNESS_A'] : 0.00; 
    $flatness_b    = isset($_POST['FLATNESS_B']) ? (float)$_POST['FLATNESS_B'] : 0.00;
    $flatness_c    = isset($_POST['FLATNESS_C']) ? (float)$_POST['FLATNESS_C'] : 0.00;
    $flatness_d    = isset($_POST['FLATNESS_D']) ? (float)$_POST['FLATNESS_D'] : 0.00;

    $flatness_e    = isset($_POST['FLATNESS_E']) ? (float)$_POST['FLATNESS_E'] : 0.00;
    $flatness_f    = isset($_POST['FLATNESS_F']) ? (float)$_POST['FLATNESS_F'] : 0.00;
    $flatness_g    = isset($_POST['FLATNESS_G']) ? (float)$_POST['FLATNESS_G'] : 0.00;
    $flatness_h    = isset($_POST['FLATNESS_H']) ? (float)$_POST['FLATNESS_H'] : 0.00;


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
        $sql_check = "SELECT COUNT(*) FROM CRSHINSP3 
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
            $sql_update = "UPDATE CRSHINSP3 
                           SET PRODUCT_ITEM = :product_item, 
                               PRODUCT_SIDE   = :pass_no, 
                               LENGTH_A   = :length_a, 
                               LENGTH_B   = :length_b, 
                               SQUARENESS_A  = :squareness_a,
                               SQUARENESS_B  = :squareness_b,
                               SQUARENESS_AB  = :squareness_ab,
                               FLATNESS_W1  = :flatness_w1,
                               FLATNESS_W2  = :flatness_w2,
                               FLATNESS_L1  = :flatness_l1,
                               FLATNESS_L2  = :flatness_l2,
                               FLATNESS_A  = :flatness_a,
                               FLATNESS_B  = :flatness_b,
                               FLATNESS_C  = :flatness_c,
                               FLATNESS_D  = :flatness_d,
                               FLATNESS_E  = :flatness_e,
                               FLATNESS_F  = :flatness_f,
                               FLATNESS_G  = :flatness_g,
                               FLATNESS_H  = :flatness_h
                           WHERE PRODUCT_NO   = :pd_no 
                            AND PRODUCT_ITEM = :product_item 
                            AND PRODUCT_SIDE = :product_side";

            $stmt_update = $conn->prepare($sql_update);
            $result = $stmt_update->execute([
                ':product_item'    => $product_item,
                ':pass_no'       => $pass_no,
                ':length_a'      => $length_a,
                ':length_b'      => $length_b,
                ':squareness_a'  => $squareness_a,
                ':squareness_b'  => $squareness_b,
                ':squareness_ab' => $squareness_ab,
                ':flatness_w1' => $flatness_w1,
                ':flatness_w2' => $flatness_w2,
                ':flatness_l1' => $flatness_l1,
                ':flatness_l2' => $flatness_l2,
                ':flatness_a' => $flatness_a,
                ':flatness_b' => $flatness_b,
                ':flatness_c' => $flatness_c,
                ':flatness_d' => $flatness_d,
                ':flatness_e' => $flatness_e,
                ':flatness_f' => $flatness_f,                                
                ':flatness_g' => $flatness_g,
                ':flatness_h'   => $flatness_h
            ]);

            $action_text = 'updated';
        } else {
            // 3. กรณีไม่มีข้อมูล -> INSERT
            $sql_insert = "INSERT INTO CRSHINSP3 (
                                PRODUCT_NO, 
                                PRODUCT_ITEM, 
                                PRODUCT_SIDE, 
                                LENGTH_A, 
                                LENGTH_B, 
                                SQUARENESS_A, 
                                SQUARENESS_B, 
                                SQUARENESS_AB, 
                                FLATNESS_W1, 
                                FLATNESS_W2, 
                                FLATNESS_L1, 
                                FLATNESS_L2, 
                                FLATNESS_A, 
                                FLATNESS_B, 
                                FLATNESS_C, 
                                FLATNESS_D, 
                                FLATNESS_E, 
                                FLATNESS_F, 
                                FLATNESS_G, 
                                FLATNESS_H
                            ) VALUES (
                                :pd_no, 
                                :product_item, 
                                :product_side, 
                                :length_a, 
                                :length_b, 
                                :squareness_a, 
                                :squareness_b, 
                                :squareness_ab, 
                                :flatness_w1, 
                                :flatness_w2, 
                                :flatness_l1, 
                                :flatness_l2, 
                                :flatness_a, 
                                :flatness_b, 
                                :flatness_c, 
                                :flatness_d, 
                                :flatness_e, 
                                :flatness_f, 
                                :flatness_g, 
                                :flatness_h
                            )";

            $stmt_insert = $conn->prepare($sql_insert);
            $result = $stmt_insert->execute([
                ':pd_no'        => $pd_no,
                ':product_item' => $product_item,
                ':product_side' => $pass_no,
                ':length_a'    => $length_a,
                ':length_b'      => $length_b,
                ':squareness_a'      => $squareness_a,
                ':squareness_b'      => $squareness_b,
                ':squareness_ab'      => $squareness_ab,
                ':flatness_w1'      => $flatness_w1,
                ':flatness_w2'      => $flatness_w2,
                ':flatness_l1'      => $flatness_l1,
                ':flatness_l2'      => $flatness_l2,
                ':flatness_a'      => $flatness_a,
                ':flatness_b'      => $flatness_b,
                ':flatness_c'      => $flatness_c,
                ':flatness_d'      => $flatness_d,
                ':flatness_e'      => $flatness_e,
                ':flatness_f'      => $flatness_f,
                ':flatness_g'      => $flatness_g,
                ':flatness_h'     => $flatness_h
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