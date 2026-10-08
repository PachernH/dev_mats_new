<?php
session_start();

include("../dbcon_mats-new.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $strProductNo    = isset($_POST['coil_no']) ? trim($_POST['coil_no']) : '';
    $strJobOrder     = isset($_POST['job_order']) ? trim($_POST['job_order']) : '';
    $dblTopWeight    = isset($_POST['top_weight']) ? (float)$_POST['top_weight'] : 0.0;
    $dblBottomWeight = isset($_POST['bottom_weight']) ? (float)$_POST['bottom_weight'] : 0.0;
    $dblGrossWeight  = isset($_POST['gross_weight']) ? (float)$_POST['gross_weight'] : 0.0;
    
    $dblPackNetWeight   = $dblGrossWeight - ($dblTopWeight + $dblBottomWeight);
    $dblCombineWeight   = $dblGrossWeight;
    $intDimensionWidth  = isset($_POST['dim_w']) ? (float)$_POST['dim_w'] : 0;
    $intDimensionLength = isset($_POST['dim_l']) ? (float)$_POST['dim_l'] : 0;
    $intDimensionHigh   = isset($_POST['dim_h']) ? (float)$_POST['dim_h'] : 0;

    $strUserOperator1 = isset($_SESSION['ID']) ? $_SESSION['ID'] : 'SYSTEM';
    $strUserOperator2 = '';
    $strLineProcess   = isset($_SESSION['LINE']) ? $_SESSION['LINE'] : '1';
    $intPrintLabel    = 1;

    if (empty($strProductNo) || empty($strJobOrder)) {
        echo "<script>alert('ข้อมูลไม่ครบถ้วน!'); window.history.back();</script>";
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. ดึงข้อมูล Master เพิ่มเติมจาก JOBORDER1
        $sql_job = "SELECT SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, PRODUCT_ID FROM JOBORDER1 WHERE JOB_ORDER = :job_order";
        $stmt_job = $conn->prepare($sql_job);
        $stmt_job->bindParam(':job_order', $strJobOrder, PDO::PARAM_STR);
        $stmt_job->execute();
        $job_info = $stmt_job->fetch(PDO::FETCH_ASSOC);

        $strSaleOrderNo   = $job_info['SALEORDER_NO'] ?? '';
        $strSaleOrderItem = $job_info['SALEORDER_ITEM'] ?? '';
        $strMaterialIN    = $job_info['MATERIAL_IN'] ?? '';
        $strProductID     = $job_info['PRODUCT_ID'] ?? '';

        // 2. ดึงข้อมูล PO จาก CSTMORDR2
        $strPONo   = '';
        $strPOItem = '';
        if (!empty($strSaleOrderNo) && !empty($strSaleOrderItem)) {
            $sql_so = "SELECT CTM2_PONO, CTM2_POITEM FROM CSTMORDR2 WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
            $stmt_so = $conn->prepare($sql_so);
            $stmt_so->bindParam(':so_no', $strSaleOrderNo, PDO::PARAM_STR);
            $stmt_so->bindParam(':so_item', $strSaleOrderItem, PDO::PARAM_STR);
            $stmt_so->execute();
            $so_info = $stmt_so->fetch(PDO::FETCH_ASSOC);
            
            $strPONo   = $so_info['CTM2_PONO'] ?? '';
            $strPOItem = $so_info['CTM2_POITEM'] ?? '';
        }

        $nowDate = date('Y-m-d H:i:s');

        // Command 1: INSERT INTO PACKPROD1
        $sql_ins_pack = "INSERT INTO PACKPROD1 (PRODUCT_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, PACK_PONO, PACK_POITEM, 
                                                TRANSFER_NO, LINE_PROCESS, PRODUCT_ID, PACK_DATE, TRANSFER_DATE, RECEIVE_DATE, PACK_COMBINEWEIGHT, 
                                                PACK_PACKAGEWEIGHT, PACK_NETWEIGHT, PACK_LABEL, PACK_OPERATOR1, PACK_OPERATOR2, PACK_OPERATEDATE, 
                                                PACK_STATUS) 
                         VALUES (:product_no, :job_order, :saleorder_no, :saleorder_item, :material_in, :pack_pono, :pack_poitem, 
                                 '', :line_process, :product_id, :pack_date, :transfer_date, :receive_date, :combine_weight, 
                                 :pkg_weight, :net_weight, :pack_label, :operator1, :operator2, :operatedate, 'ST')";

        $stmt_ins = $conn->prepare($sql_ins_pack);
        $pkg_weight = $dblTopWeight + $dblBottomWeight;
        
        $stmt_ins->bindParam(':product_no', $strProductNo);
        $stmt_ins->bindParam(':job_order', $strJobOrder);
        $stmt_ins->bindParam(':saleorder_no', $strSaleOrderNo);
        $stmt_ins->bindParam(':saleorder_item', $strSaleOrderItem);
        $stmt_ins->bindParam(':material_in', $strMaterialIN);
        $stmt_ins->bindParam(':pack_pono', $strPONo);
        $stmt_ins->bindParam(':pack_poitem', $strPOItem);
        $stmt_ins->bindParam(':line_process', $strLineProcess);
        $stmt_ins->bindParam(':product_id', $strProductID);
        $stmt_ins->bindParam(':pack_date', $nowDate);
        $stmt_ins->bindParam(':transfer_date', $nowDate);
        $stmt_ins->bindParam(':receive_date', $nowDate);
        $stmt_ins->bindParam(':combine_weight', $dblCombineWeight);
        $stmt_ins->bindParam(':pkg_weight', $pkg_weight);
        $stmt_ins->bindParam(':net_weight', $dblPackNetWeight);
        $stmt_ins->bindParam(':pack_label', $intPrintLabel);
        $stmt_ins->bindParam(':operator1', $strUserOperator1);
        $stmt_ins->bindParam(':operator2', $strUserOperator2);
        $stmt_ins->bindParam(':operatedate', $nowDate);
        $stmt_ins->execute();

        // Command 2: UPDATE COILPROD1
        $sql_upd_coil = "UPDATE COILPROD1 
                         SET SALEORDER_NO = :so_no, SALEORDER_ITEM = :so_item, JOB_ORDER = :job_order, 
                             COIL_PACKWEIGHT = :pack_weight, COIL_TOPWEIGHT = :top_weight, COIL_BOTTOMWEIGHT = :bottom_weight, 
                             COIL_DIMENSIONWIDTH = :dim_w, COIL_DIMENSIONLENGTH = :dim_l, COIL_DIMENSIONHIGH = :dim_h, 
                             COIL_UPDATE = :operator1, COIL_UPDATEDATE = :updatedate, COIL_STATUS = 'ST' 
                         WHERE COIL_NO = :coil_no";

        $stmt_upd_coil = $conn->prepare($sql_upd_coil);
        $stmt_upd_coil->bindParam(':so_no', $strSaleOrderNo);
        $stmt_upd_coil->bindParam(':so_item', $strSaleOrderItem);
        $stmt_upd_coil->bindParam(':job_order', $strJobOrder);
        $stmt_upd_coil->bindParam(':pack_weight', $dblPackNetWeight);
        $stmt_upd_coil->bindParam(':top_weight', $dblTopWeight);
        $stmt_upd_coil->bindParam(':bottom_weight', $dblBottomWeight);
        $stmt_upd_coil->bindParam(':dim_w', $intDimensionWidth);
        $stmt_upd_coil->bindParam(':dim_l', $intDimensionLength);
        $stmt_upd_coil->bindParam(':dim_h', $intDimensionHigh);
        $stmt_upd_coil->bindParam(':operator1', $strUserOperator1);
        $stmt_upd_coil->bindParam(':updatedate', $nowDate);
        $stmt_upd_coil->bindParam(':coil_no', $strProductNo);
        $stmt_upd_coil->execute();

        // Command 3: UPDATE JOBORDER1 (ตรวจสอบเงื่อนไข JOB_RELEASEPIECE == JOB_PACKPIECE)
        if (!empty($strJobOrder)) {
            $sql_get_job_curr = "SELECT JOB_RELEASEPIECE, JOB_PACKWEIGHT, JOB_PACKPIECE, JOB_STOCKWEIGHT, JOB_STOCKPIECE, JOB_STATUS 
                                FROM JOBORDER1 
                                WHERE JOB_ORDER = :job_order";
            $stmt_job_curr = $conn->prepare($sql_get_job_curr);
            $stmt_job_curr->bindParam(':job_order', $strJobOrder);
            $stmt_job_curr->execute();
            $curr_job = $stmt_job_curr->fetch(PDO::FETCH_ASSOC);

            $dblJobPackWeight  = ((float)($curr_job['JOB_PACKWEIGHT'] ?? 0)) + $dblPackNetWeight;
            $intJobPackPiece   = ((int)($curr_job['JOB_PACKPIECE'] ?? 0)) + 1;
            
            $dblJobStockWeight = ((float)($curr_job['JOB_STOCKWEIGHT'] ?? 0)) + $dblPackNetWeight;
            $intJobStockPiece  = ((int)($curr_job['JOB_STOCKPIECE'] ?? 0)) + 1;
            
            $intJobReleasePiece = (int)($curr_job['JOB_RELEASEPIECE'] ?? 0);

            if ($intJobReleasePiece > 0 && $intJobReleasePiece === $intJobPackPiece) {
                $strJobStatus = 'CL';
            } else {
                $strJobStatus = $curr_job['JOB_STATUS'] ?? '';
            }

            $sql_upd_job = "UPDATE JOBORDER1 
                            SET MATERIAL_IN = :mat_in, 
                                JOB_PACKWEIGHT = :pack_weight, JOB_PACKPIECE = :pack_piece, 
                                JOB_STOCKWEIGHT = :stock_weight, JOB_STOCKPIECE = :stock_piece, 
                                JOB_STATUS = :job_status 
                            WHERE JOB_ORDER = :job_order";

            $stmt_upd_job = $conn->prepare($sql_upd_job);
            $stmt_upd_job->bindParam(':mat_in', $strMaterialIN);
            $stmt_upd_job->bindParam(':pack_weight', $dblJobPackWeight);
            $stmt_upd_job->bindParam(':pack_piece', $intJobPackPiece);
            $stmt_upd_job->bindParam(':stock_weight', $dblJobStockWeight);
            $stmt_upd_job->bindParam(':stock_piece', $intJobStockPiece);
            $stmt_upd_job->bindParam(':job_status', $strJobStatus);
            $stmt_upd_job->bindParam(':job_order', $strJobOrder);
            $stmt_upd_job->execute();
        }

        // Command 4: UPDATE CSTMORDR2 (บวกเพิ่มทั้ง PACK และ STOCK)
        if (!empty($strSaleOrderNo) && !empty($strSaleOrderItem)) {
            $sql_get_so_curr = "SELECT CTM2_PACKWEIGHT, CTM2_PACKPIECE, CTM2_STOCKWEIGHT, CTM2_STOCKPIECE 
                                FROM CSTMORDR2 
                                WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";
            $stmt_so_curr = $conn->prepare($sql_get_so_curr);
            $stmt_so_curr->bindParam(':so_no', $strSaleOrderNo);
            $stmt_so_curr->bindParam(':so_item', $strSaleOrderItem);
            $stmt_so_curr->execute();
            $curr_so = $stmt_so_curr->fetch(PDO::FETCH_ASSOC);

            $dblOrderPackWeight  = ((float)($curr_so['CTM2_PACKWEIGHT'] ?? 0)) + $dblPackNetWeight;
            $intOrderPackPiece   = ((int)($curr_so['CTM2_PACKPIECE'] ?? 0)) + 1;
            
            $dblOrderStockWeight = ((float)($curr_so['CTM2_STOCKWEIGHT'] ?? 0)) + $dblPackNetWeight;
            $intOrderStockPiece  = ((int)($curr_so['CTM2_STOCKPIECE'] ?? 0)) + 1;

            $sql_upd_so = "UPDATE CSTMORDR2 
                           SET CTM2_PACKWEIGHT = :pack_weight, CTM2_PACKPIECE = :pack_piece, 
                               CTM2_STOCKWEIGHT = :stock_weight, CTM2_STOCKPIECE = :stock_piece 
                           WHERE SALEORDER_NO = :so_no AND SALEORDER_ITEM = :so_item";

            $stmt_upd_so = $conn->prepare($sql_upd_so);
            $stmt_upd_so->bindParam(':pack_weight', $dblOrderPackWeight);
            $stmt_upd_so->bindParam(':pack_piece', $intOrderPackPiece);
            $stmt_upd_so->bindParam(':stock_weight', $dblOrderStockWeight);
            $stmt_upd_so->bindParam(':stock_piece', $intOrderStockPiece);
            $stmt_upd_so->bindParam(':so_no', $strSaleOrderNo);
            $stmt_upd_so->bindParam(':so_item', $strSaleOrderItem);
            $stmt_upd_so->execute();
        }

        $conn->commit();
        // ส่งตัวแปร print_no เพื่อแจ้งเตือนให้หน้า UI เปิด Pop-up สั่งพิมพ์
        header("Location: ../coil_packing_update_mats.php?jno=" . urlencode($strJobOrder) . "&print_no=" . urlencode($strProductNo));
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    }
}
?>