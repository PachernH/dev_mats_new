<?php
session_start();
include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_no   = isset($_POST['COIL_NO']) ? trim($_POST['COIL_NO']) : '';
    $strAlloy     = isset($_POST['ALLOY']) ? trim($_POST['ALLOY']) : '';
    $strSG        = isset($_POST['SURFACE_GRADE']) ? trim($_POST['SURFACE_GRADE']) : '';
    $strMG        = isset($_POST['METALLURGICAL_GRADE']) ? trim($_POST['METALLURGICAL_GRADE']) : '';
    $strLocation  = isset($_POST['LOCATION_ID']) ? trim($_POST['LOCATION_ID']) : '';
    $strCoilRemark= isset($_POST['COIL_REMARK']) ? trim($_POST['COIL_REMARK']) : '';
    $strStatusID  = isset($_POST['COIL_STATUS']) ? trim($_POST['COIL_STATUS']) : '';
    $folder_func  = isset($_POST['func']) ? trim($_POST['func']) : '';

    if (empty($product_no)) {
        echo "<script>alert('ไม่พบข้อมูล COIL NO'); window.history.back();</script>";
        exit;
    }

    try {
        // SQL สอดคล้องตามคำสั่ง VB:
        // UPDATE COILPROD1 SET ALLOY = ?, SURFACE_GRADE = ?, METALLURGICAL_GRADE = ?, LOCATION_ID = ?, COIL_REMARK = ?, COIL_STATUS = ? WHERE COIL_NO = ?
        $sql = "UPDATE COILPROD1 
                SET ALLOY = :alloy, 
                    SURFACE_GRADE = :sg, 
                    METALLURGICAL_GRADE = :mg, 
                    LOCATION_ID = :location, 
                    COIL_REMARK = :remark, 
                    COIL_STATUS = :status 
                WHERE COIL_NO = :product_no";

        $stmt = $conn->prepare($sql);

        // Bind Parameters ตามความยาวที่กำหนดใน VB
        // adVarChar 8
        $stmt->bindValue(':alloy', mb_substr($strAlloy, 0, 8, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 6
        $stmt->bindValue(':sg', mb_substr($strSG, 0, 6, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 6
        $stmt->bindValue(':mg', mb_substr($strMG, 0, 6, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 5
        $stmt->bindValue(':location', mb_substr($strLocation, 0, 5, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 100
        $stmt->bindValue(':remark', mb_substr($strCoilRemark, 0, 100, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 2
        $stmt->bindValue(':status', mb_substr($strStatusID, 0, 2, 'UTF-8'), PDO::PARAM_STR);
        // adVarChar 13
        $stmt->bindValue(':product_no', mb_substr($product_no, 0, 13, 'UTF-8'), PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo "<script>
                    alert('อัปเดตข้อมูลเรียบร้อยแล้ว');
                    window.location.href = '../maintain_coil_update_mats.php?COIL=" . urlencode($product_no) . "&func=" . urlencode($folder_func) . "';
                  </script>";
        } else {
            echo "<script>
                    alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                    window.history.back();
                  </script>";
        }

    } catch (PDOException $e) {
        // จัดการกรณี ErrorHandler สอดคล้องกับ VB
        error_log("Update Coil Error: " . $e->getMessage());
        echo "<script>
                alert('เกิดข้อผิดพลาดจากระบบฐานข้อมูล: " . addslashes($e->getMessage()) . "');
                window.history.back();
              </script>";
    }
} else {
    header("Location: ../maintain_coil_mats.php");
    exit;
}
?>