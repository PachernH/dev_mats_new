<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['message' => false, 'error' => 'Invalid Request Method']);
    exit;
}

include('../dbcon_mats-new.php');

try {
    // รับค่าที่เป็นเงื่อนไขในการ Update (ดึงค่า String แบบรักษารูปแบบดั้งเดิมของ Database ไว้)
    $alloy          = isset($_POST['inp_alloy']) ? trim($_POST['inp_alloy']) : '';
    $temper         = isset($_POST['inp_temper']) ? trim($_POST['inp_temper']) : '';
    $thickness_from = isset($_POST['inp_thick_from']) ? trim($_POST['inp_thick_from']) : '';
    $thickness_to   = isset($_POST['inp_thick_to']) ? trim($_POST['inp_thick_to']) : '';

    if ($alloy == '' || $temper == '' || $thickness_from == '') {
        echo json_encode(['message' => false, 'error' => 'Missing primary criteria data.']);
        exit;
    }

    // รับค่าเชิงกล (หากผู้ใช้ลบจนว่าง ให้ส่งศูนย์กลับเข้าไปตามตาราง Default = 0)
    $utsfrom_ksi = (isset($_POST['uts_i_f']) && $_POST['uts_i_f'] !== '') ? (float)$_POST['uts_i_f'] : 0;
    $utsto_ksi   = (isset($_POST['uts_i_t']) && $_POST['uts_i_t'] !== '') ? (float)$_POST['uts_i_t'] : 0;
    $utsfrom_kgm = (isset($_POST['uts_k_f']) && $_POST['uts_k_f'] !== '') ? (float)$_POST['uts_k_f'] : 0;
    $utsto_kgm   = (isset($_POST['uts_k_t']) && $_POST['uts_k_t'] !== '') ? (float)$_POST['uts_k_t'] : 0;
    
    $ysfrom_ksi  = (isset($_POST['yis_i_f']) && $_POST['yis_i_f'] !== '') ? (float)$_POST['yis_i_f'] : 0;
    $ysto_ksi    = (isset($_POST['yis_i_t']) && $_POST['yis_i_t'] !== '') ? (float)$_POST['yis_i_t'] : 0;
    $ysfrom_kgm  = (isset($_POST['yis_k_f']) && $_POST['yis_k_f'] !== '') ? (float)$_POST['yis_k_f'] : 0;
    $ysto_kgm    = (isset($_POST['yis_k_t']) && $_POST['yis_k_t'] !== '') ? (float)$_POST['yis_k_t'] : 0;
    
    $elongfrom   = (isset($_POST['el_i_f']) && $_POST['el_i_f'] !== '') ? (float)$_POST['el_i_f'] : 0;
    $elongto     = (isset($_POST['el_i_t']) && $_POST['el_i_t'] !== '') ? (float)$_POST['el_i_t'] : 0;

    // แก้ไขคิวรี: ใช้ LTRIM(RTRIM()) ครอบฝั่ง SQL Server ป้องกันปัญหาช่องว่างแฝงในฟิลด์แบบ nvarchar
    $sql = "UPDATE TCPS0201_6 
            SET UTSFROM_KSI   = :utsfrom_ksi, 
                UTSTO_KSI     = :utsto_ksi, 
                UTSFROM_KGM   = :utsfrom_kgm, 
                UTSTO_KGM     = :utsto_kgm, 
                YSFROM_KSI    = :ysfrom_ksi, 
                YSTO_KSI      = :ysto_ksi, 
                YSFROM_KGM    = :ysfrom_kgm, 
                YSTO_KGM      = :ysto_kgm, 
                ELONGFROM     = :elongfrom, 
                ELONGTO       = :elongto
            WHERE LTRIM(RTRIM(ALLOY))          = LTRIM(RTRIM(:alloy))
              AND LTRIM(RTRIM(TEMPER_TARGET))  = LTRIM(RTRIM(:temper))
              AND LTRIM(RTRIM(THICKNESS_FROM)) = LTRIM(RTRIM(:thickness_from))
              AND LTRIM(RTRIM(THICKNESS_TO))   = LTRIM(RTRIM(:thickness_to))";

    $stmt = $conn->prepare($sql);

    // Bind Value
    $stmt->bindValue(':utsfrom_ksi', $utsfrom_ksi);
    $stmt->bindValue(':utsto_ksi', $utsto_ksi);
    $stmt->bindValue(':utsfrom_kgm', $utsfrom_kgm);
    $stmt->bindValue(':utsto_kgm', $utsto_kgm);
    $stmt->bindValue(':ysfrom_ksi', $ysfrom_ksi);
    $stmt->bindValue(':ysto_ksi', $ysto_ksi);
    $stmt->bindValue(':ysfrom_kgm', $ysfrom_kgm);
    $stmt->bindValue(':ysto_kgm', $ysto_kgm);
    $stmt->bindValue(':elongfrom', $elongfrom);
    $stmt->bindValue(':elongto', $elongto);
    
    $stmt->bindValue(':alloy', $alloy);
    $stmt->bindValue(':temper', $temper);
    $stmt->bindValue(':thickness_from', $thickness_from);
    $stmt->bindValue(':thickness_to', $thickness_to);

    if ($stmt->execute()) {
        echo json_encode(['message' => true]);
    } else {
        echo json_encode(['message' => false, 'error' => 'No records updated. Please check criteria.']);
    }

} catch (PDOException $e) {
    echo json_encode(['message' => false, 'error' => 'Database failure: ' . $e->getMessage()]);
}