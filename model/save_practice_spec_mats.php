<?php
// 1. ตั้งค่า Header ให้ส่งคืนข้อมูลเป็น JSON และรองรับภาษาไทย (UTF-8)
header('Content-Type: application/json; charset=utf-8');

// 2. เริ่ม Session เพื่อความเสถียร
session_start();

// 3. ตรวจสอบว่ามีการส่งข้อมูลแบบ POST มาจริงหรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array("status" => "error", "error" => "Method not allowed."));
    exit();
}

// 4. เชื่อมต่อฐานข้อมูล
include("../dbcon_mats-new.php");

try {
    // 5. รับค่าและล้างข้อมูลนำเข้า (ตรงตามชื่อ input ในฟอร์มหน้าบ้าน)
    $product_id = isset($_POST['in_pro']) ? trim($_POST['in_pro']) : '';
    $alloy      = isset($_POST['in_alloy']) ? trim($_POST['in_alloy']) : '';
    $temper_in  = isset($_POST['in_temper']) ? trim($_POST['in_temper']) : '';
    $temper_tg  = isset($_POST['in_temper2']) ? trim($_POST['in_temper2']) : '';
    $range_f    = isset($_POST['in_range_f']) ? trim($_POST['in_range_f']) : '';
    $range_t    = isset($_POST['in_range_t']) ? trim($_POST['in_range_t']) : '';
    
    // [แก้ไขจุดที่ 1] MIN_WEIGHT ในตารางจริงเป็น int จึงต้องใช้ intval()
    $mw = (isset($_POST['mw']) && $_POST['mw'] !== '') ? intval($_POST['mw']) : 0;
    
    // คอลัมน์อื่นๆ ที่เป็น int ในตารางแปลงเป็น intval() ให้หมดเพื่อความปลอดภัย
    $h_tmc = (isset($_POST['h_tmc']) && $_POST['h_tmc'] !== '') ? intval($_POST['h_tmc']) : 0;
    $h_tdc = (isset($_POST['h_tdc']) && $_POST['h_tdc'] !== '') ? intval($_POST['h_tdc']) : 0;
    $s_tmc = (isset($_POST['s_tmc']) && $_POST['s_tmc'] !== '') ? intval($_POST['s_tmc']) : 0;
    $s_tdc = (isset($_POST['s_tdc']) && $_POST['s_tdc'] !== '') ? intval($_POST['s_tdc']) : 0;
    
    $o2p_f = (isset($_POST['o2p_f']) && $_POST['o2p_f'] !== '') ? intval($_POST['o2p_f']) : 0;
    $o2p_t = (isset($_POST['o2p_t']) && $_POST['o2p_t'] !== '') ? intval($_POST['o2p_t']) : 0;
    
    $wph   = (isset($_POST['wph']) && $_POST['wph'] !== '') ? intval($_POST['wph']) : 0;
    $wps_f = (isset($_POST['wps_f']) && $_POST['wps_f'] !== '') ? intval($_POST['wps_f']) : 0;
    $wps_t = (isset($_POST['wps_t']) && $_POST['wps_t'] !== '') ? intval($_POST['wps_t']) : 0;

    // ส่วนที่เป็น nvarchar (String)
    $rt    = isset($_POST['rt']) ? trim($_POST['rt']) : '';
    $pg    = isset($_POST['pg']) ? trim($_POST['pg']) : '';
    
    // [แก้ไขจุดที่ 2] O2 ในตารางเป็น ชนิด real (ทศนิยม) แปลงเป็น floatval เพื่อรองรับทศนิยม 2 ตำแหน่ง
    $o2_f  = (isset($_POST['o2_f']) && $_POST['o2_f'] !== '') ? floatval($_POST['o2_f']) : 0.00;
    $o2_t  = (isset($_POST['o2_t']) && $_POST['o2_t'] !== '') ? floatval($_POST['o2_t']) : 0.00;

    // 6. ตรวจสอบข้อมูลจำเป็นเบื้องต้น
    if ($product_id == "" || $alloy == "" || $temper_in == "" || $temper_tg == "" || $range_f == "" || $range_t == "") {
        echo json_encode(array("status" => "error", "error" => "กรุณากรอกข้อมูลในช่องที่จำเป็นให้ครบถ้วน"));
        exit();
    }

    // 7. จัดเตรียมคำสั่ง SQL INSERT
    $sql = "INSERT INTO TPMAT1001 (
                PRODUCT_ID, ALLOY, RANGE_FROM, RANGE_TO, TEMPER_INITIAL, TEMPER_TARGET, 
                RANGE_TYPE, MIN_WEIGHT, O2PURING_FROM, O2PURING_TO, HEATING_TEMPERATURE, 
                HEATING_TIME, SOAKING_TEMPERATURE, SOAKING_TIME, WORKPIECE_HEATING, 
                WORKPIECE_SOAKINGFROM, WORKPIECE_SOAKINGTO, O2_PERCENTAGEFROM, O2_PERCENTAGETO, PROGRAM
            ) VALUES (
                :product_id, :alloy, :range_f, :range_t, :temper_in, :temper_tg, 
                :rt, :mw, :o2p_f, :o2p_t, :h_tdc, 
                :h_tmc, :s_tdc, :s_tmc, :wph, 
                :wps_f, :wps_t, :o2_f, :o2_t, :pg
            )";

    $stmt = $conn->prepare($sql);

    // 8. ผูกตัวแปร (Bind Parameters) ตามชนิดข้อมูลจริงในตาราง
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_STR);
    $stmt->bindValue(':alloy', $alloy, PDO::PARAM_STR);
    $stmt->bindValue(':range_f', $range_f, PDO::PARAM_STR);
    $stmt->bindValue(':range_t', $range_t, PDO::PARAM_STR);
    $stmt->bindValue(':temper_in', $temper_in, PDO::PARAM_STR);
    $stmt->bindValue(':temper_tg', $temper_tg, PDO::PARAM_STR);
    $stmt->bindValue(':rt', $rt, PDO::PARAM_STR);
    $stmt->bindValue(':pg', $pg, PDO::PARAM_STR);
    
    // ผูกค่ากลุ่มที่เป็น Integer (int)
    $stmt->bindValue(':mw', $mw, PDO::PARAM_INT);
    $stmt->bindValue(':o2p_f', $o2p_f, PDO::PARAM_INT);
    $stmt->bindValue(':o2p_t', $o2p_t, PDO::PARAM_INT);
    $stmt->bindValue(':h_tdc', $h_tdc, PDO::PARAM_INT);
    $stmt->bindValue(':h_tmc', $h_tmc, PDO::PARAM_INT);
    $stmt->bindValue(':s_tdc', $s_tdc, PDO::PARAM_INT);
    $stmt->bindValue(':s_tmc', $s_tmc, PDO::PARAM_INT);
    $stmt->bindValue(':wph', $wph, PDO::PARAM_INT);
    $stmt->bindValue(':wps_f', $wps_f, PDO::PARAM_INT);
    $stmt->bindValue(':wps_t', $wps_t, PDO::PARAM_INT);
    
    // ผูกค่ากลุ่มที่เป็น Real/Float
    $stmt->bindValue(':o2_f', $o2_f);
    $stmt->bindValue(':o2_t', $o2_t);

    // 9. ประมวลผล
    if ($stmt->execute()) {
        echo json_encode(array("status" => "success", "message" => "บันทึกข้อมูลสำเร็จ"));
    } else {
        // ดึง Error ลึกๆ จากระบบฐานข้อมูลมาแสดงกรณีบันทึกไม่เข้า
        $errorInfo = $stmt->errorInfo();
        echo json_encode(array("status" => "error", "error" => "SQL Error: " . $errorInfo[2]));
    }

} catch (PDOException $e) {
    echo json_encode(array("status" => "error", "error" => "Database Error: " . $e->getMessage()));
}
exit();
?>