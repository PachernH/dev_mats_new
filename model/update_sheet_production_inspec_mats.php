<?php
// เปิด Debug กรณีต้องการดู Error (ปิดไว้สำหรับการใช้งานจริง)
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

// 1. ตรวจสอบไฟล์ DB
$db_path = __DIR__ . '/../dbcon_mats-new.php';
if (!file_exists($db_path)) {
    $db_path = __DIR__ . '/dbcon_mats-new.php';
}

if (file_exists($db_path)) {
    include_once($db_path);
} else {
    die("Error: Connection file not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจาก Form
    $product_no    = isset($_POST['PRODUCT_NO']) ? trim($_POST['PRODUCT_NO']) : '';
    $product_id    = isset($_POST['PRODUCT_ID']) ? trim($_POST['PRODUCT_ID']) : '';
    $product_model = isset($_POST['PRODUCT_MODEL']) ? trim($_POST['PRODUCT_MODEL']) : '';
    $coil_no       = isset($_POST['COIL_NO']) ? trim($_POST['COIL_NO']) : '';
    $func          = isset($_POST['func']) ? trim($_POST['func']) : '';

    // ปรับ $user_id ให้เป็นตัวพิมพ์ใหญ่ทั้งหมด
    $raw_user_id   = $_SESSION['ID'] ?? 'SYSTEM';
    $user_id       = strtoupper($raw_user_id);
    
    $current_datetime = date('Y-m-d H:i:s');

    if (empty($product_no)) {
        echo "<script>alert('Error: Missing Product Number!'); window.history.back();</script>";
        exit;
    }

    // ตรวจสอบและปัดเศษ CRSH_TAKEOUTWEIGHT เป็นจำนวนเต็ม
    $takeout_weight_raw = $_POST['CRSH_TAKEOUTWEIGHT'] ?? '';
    $crsh_takeoutweight = ($takeout_weight_raw !== '') ? round((float)$takeout_weight_raw) : 0;
    $actual_weight = $_POST['CRSH_ACTUALWEIGHT'] ?? '';
    $crsh_actualweight = ($actual_weight !== '') ? round((float)$actual_weight) : 0;

    // รับค่า Result of Inspection
    $cin1_status = $_POST['CIN1_STATUS'] ?? '';

    try {
        if (isset($conn) && $conn instanceof PDO) {
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();

            // -------------------------------------------------------------
            // 1. UPDATE ตาราง CRSHPROD1
            // -------------------------------------------------------------
            $sql_prod = "UPDATE [CRSHPROD1] SET
                            TEMPER = :temper,
                            CRSH_STATUS = :crsh_status,
                            CRSH_ACTUALPIECE = :crsh_actualpiece,
                            CRSH_ACTUALWEIGHT = :crsh_actualweight,
                            CRSH_TAKEOUTPIECE = :crsh_takeoutpiece,
                            CRSH_TAKEOUTWEIGHT = :crsh_takeoutweight,
                            CRSH_UOMWEIGHT = :crsh_oumweight, 
                            CRSH_UPDATE = :crsh_update,
                            CRSH_UPDATEDATE = :crsh_updatedate
                        WHERE PRODUCT_NO = :product_no";

            $stmt_prod = $conn->prepare($sql_prod);
            $stmt_prod->execute([
                ':temper'             => (isset($_POST['TEMPER']) && $_POST['TEMPER'] !== '') ? $_POST['TEMPER'] : '',
                ':crsh_status'        => $cin1_status,
                ':crsh_actualpiece'   => (isset($_POST['CRSH_ACTUALPIECE']) && $_POST['CRSH_ACTUALPIECE'] !== '') ? $_POST['CRSH_ACTUALPIECE'] : 0,
                ':crsh_actualweight'  => $crsh_actualweight,
                ':crsh_takeoutpiece'  => (isset($_POST['CRSH_TAKEOUTPIECE']) && $_POST['CRSH_TAKEOUTPIECE'] !== '') ? $_POST['CRSH_TAKEOUTPIECE'] : 0,
                ':crsh_takeoutweight' => $crsh_takeoutweight,
                ':crsh_oumweight'     => 'kg.',                
                ':crsh_update'        => $user_id,
                ':crsh_updatedate'    => $current_datetime,
                ':product_no'         => $product_no
            ]);

            // -------------------------------------------------------------
            // 2. CHECK / UPDATE / INSERT ตาราง CRSHINSP1
            // -------------------------------------------------------------
            $chk_sql = "SELECT COUNT(*) FROM [CRSHINSP1] WHERE PRODUCT_NO = :product_no";
            $chk_stmt = $conn->prepare($chk_sql);
            $chk_stmt->execute([':product_no' => $product_no]);
            $exists = $chk_stmt->fetchColumn();

            $params_insp = [
                ':inspection_date' => !empty($_POST['INSPECTION_DATE']) ? $_POST['INSPECTION_DATE'] : date('Y-m-d'),
                ':uts'             => (isset($_POST['UTS']) && $_POST['UTS'] !== '') ? $_POST['UTS'] : 0,
                ':uts1'            => (isset($_POST['UTS1']) && $_POST['UTS1'] !== '') ? $_POST['UTS1'] : 0,
                ':yield_strength'  => (isset($_POST['YIELD_STRENGTH']) && $_POST['YIELD_STRENGTH'] !== '') ? $_POST['YIELD_STRENGTH'] : 0,
                ':yield_strength1' => (isset($_POST['YIELD_STRENGTH1']) && $_POST['YIELD_STRENGTH1'] !== '') ? $_POST['YIELD_STRENGTH1'] : 0,
                ':elongation'      => (isset($_POST['ELONGATION']) && $_POST['ELONGATION'] !== '') ? $_POST['ELONGATION'] : 0,
                ':elongation1'     => (isset($_POST['ELONGATION1']) && $_POST['ELONGATION1'] !== '') ? $_POST['ELONGATION1'] : 0,
                ':earing'          => (isset($_POST['EARING']) && $_POST['EARING'] !== '') ? $_POST['EARING'] : 0,
                ':earing1'         => (isset($_POST['EARING1']) && $_POST['EARING1'] !== '') ? $_POST['EARING1'] : 0,
                ':earing_angle'    => $_POST['EARING_ANGLE'] ?? '',
                ':earing_angle1'   => $_POST['EARING_ANGLE1'] ?? '',
                ':grain_size'      => (isset($_POST['GRAIN_SIZE']) && $_POST['GRAIN_SIZE'] !== '') ? $_POST['GRAIN_SIZE'] : 0,
                ':bending'         => $_POST['BENDING'] ?? '',
                ':bulge_grade'     => $_POST['BULGE_GRADE'] ?? '',
                ':etch_burr'       => $_POST['ETCH_BURR'] ?? '',
                ':etch_top'        => $_POST['ETCH_TOP'] ?? '',
                ':use_side'        => $_POST['USE_SIDE'] ?? '',
                ':flatness_grade'  => $_POST['FLATNESS_GRADE'] ?? '',
                ':cin1_status'     => $cin1_status,
                ':cin1_appearance' => $_POST['CIN1_APPEARANCE'] ?? '',
                ':cin1_dimension'  => $_POST['CIN1_DIMENSION'] ?? '',
                ':cin1_remark'     => $_POST['CIN1_REMARK'] ?? '',
                ':cin1_update'     => $user_id,
                ':cin1_updatedate' => $current_datetime,
                ':product_no'      => $product_no
            ];

            if ($exists > 0) {
                $sql_insp = "UPDATE [CRSHINSP1] SET
                                INSPECTION_DATE = :inspection_date,
                                UTS = :uts,
                                UTS1 = :uts1,
                                YIELD_STRENGTH = :yield_strength,
                                YIELD_STRENGTH1 = :yield_strength1,
                                ELONGATION = :elongation,
                                ELONGATION1 = :elongation1,
                                EARING = :earing,
                                EARING1 = :earing1,
                                EARING_ANGLE = :earing_angle,
                                EARING_ANGLE1 = :earing_angle1,
                                GRAIN_SIZE = :grain_size,
                                BENDING = :bending,
                                BULGE_GRADE = :bulge_grade,
                                ETCH_BURR = :etch_burr,
                                ETCH_TOP = :etch_top,
                                USE_SIDE = :use_side,
                                FLATNESS_GRADE = :flatness_grade,
                                CIN1_STATUS = :cin1_status,
                                CIN1_APPEARANCE = :cin1_appearance,
                                CIN1_DIMENSION = :cin1_dimension,
                                CIN1_REMARK = :cin1_remark,
                                CIN1_UPDATE = :cin1_update,
                                CIN1_UPDATEDATE = :cin1_updatedate
                            WHERE PRODUCT_NO = :product_no";
            } else {
                $sql_insp = "INSERT INTO [CRSHINSP1] (
                                PRODUCT_NO, PRODUCT_ID, PRODUCT_MODEL, COIL_NO, INSPECTION_DATE, 
                                UTS, UTS1, YIELD_STRENGTH, YIELD_STRENGTH1, ELONGATION, ELONGATION1, 
                                EARING, EARING1, EARING_ANGLE, EARING_ANGLE1, GRAIN_SIZE, BENDING, 
                                BULGE_GRADE, ETCH_BURR, ETCH_TOP, USE_SIDE, FLATNESS_GRADE, 
                                CIN1_COMBINE, CLOSE_INSPECTION, CIN1_STATUS, CIN1_APPEARANCE, CIN1_DIMENSION, 
                                CIN1_REMARK, CIN1_OPERATOR, CIN1_OPERATEDATE, CIN1_UPDATE, CIN1_UPDATEDATE
                            ) VALUES (
                                :product_no, :product_id, :product_model, :coil_no, :inspection_date, 
                                :uts, :uts1, :yield_strength, :yield_strength1, :elongation, :elongation1, 
                                :earing, :earing1, :earing_angle, :earing_angle1, :grain_size, :bending, 
                                :bulge_grade, :etch_burr, :etch_top, :use_side, :flatness_grade, 
                                :cin1_combine, :close_inspection, :cin1_status, :cin1_appearance, :cin1_dimension, 
                                :cin1_remark, :cin1_operator, :cin1_operatedate, :cin1_update, :cin1_updatedate
                            )";
                
                $params_insp[':product_id']       = $product_id;
                $params_insp[':product_model']    = $product_model;
                $params_insp[':coil_no']          = $coil_no;
                $params_insp[':cin1_combine']     = '';
                $params_insp[':close_inspection'] = 'OP';
                $params_insp[':cin1_operator']    = $user_id;
                $params_insp[':cin1_operatedate'] = $current_datetime;
            }

            $stmt_insp = $conn->prepare($sql_insp);
            $stmt_insp->execute($params_insp);

            // -------------------------------------------------------------
            // 3. UPDATE ตาราง STCHPROD1 (ถ้ามี)
            // -------------------------------------------------------------
            if (isset($_POST['FLATNESS_HIGH']) || isset($_POST['FLATNESS_LENGTH'])) {
                $chk_stch = "SELECT COUNT(*) FROM [STCHPROD1] WHERE PRODUCT_NO = :product_no";
                $stmt_chk_stch = $conn->prepare($chk_stch);
                $stmt_chk_stch->execute([':product_no' => $product_no]);
                
                if ($stmt_chk_stch->fetchColumn() > 0) {
                    $sql_stch = "UPDATE [STCHPROD1] SET
                                    FLATNESS_HIGH = :flatness_high,
                                    FLATNESS_LENGTH = :flatness_length,
                                    STCH_UPDATE = :stch_update,
                                    STCH_UPDATEDATE = :stch_updatedate
                                WHERE PRODUCT_NO = :product_no";
                    $stmt_stch = $conn->prepare($sql_stch);
                    $stmt_stch->execute([
                        ':flatness_high'   => (isset($_POST['FLATNESS_HIGH']) && $_POST['FLATNESS_HIGH'] !== '') ? $_POST['FLATNESS_HIGH'] : 0,
                        ':flatness_length' => (isset($_POST['FLATNESS_LENGTH']) && $_POST['FLATNESS_LENGTH'] !== '') ? $_POST['FLATNESS_LENGTH'] : 0,
                        ':stch_update'     => $user_id,
                        ':stch_updatedate' => $current_datetime,
                        ':product_no'      => $product_no
                    ]);
                }
            }

            $conn->commit();

            // [เพิ่มการแจ้งเตือน alert ว่าบันทึกสำเร็จ ก่อนย้ายหน้า]
            $target_url = "../sheet_production_inspec_detail_mats.php?func=" . urlencode($func) . "&pno=" . urlencode($product_no) . "&status=success";
            echo "<script>
                    alert('บันทึกข้อมูลสำเร็จเรียบร้อยแล้ว');
                    window.location.href = '" . $target_url . "';
                  </script>";
            exit;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        echo "<script>alert('Error SQL: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: ../sheet_production_inspec_mats.php");
    exit;
}
?>