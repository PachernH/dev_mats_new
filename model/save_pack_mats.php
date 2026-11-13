<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include("../dbcon_mats-new.php");

$folder_func        = trim($_POST['func'] ?? '');
$user_id            = $_SESSION['ID'] ?? 'SYSTEM';
$line_process       = $_SESSION['LINE_PROCESS'] ?? '1';

$product_no         = trim($_POST['product_no'] ?? '');
$material_code      = trim($_POST['material_code'] ?? '');
$pack_stack_number  = (int)($_POST['pack_stack_number'] ?? 1);
$top_weight         = (float)($_POST['top_weight'] ?? 0);
$bottom_weight      = (float)($_POST['bottom_weight'] ?? 0);
$gross_weight       = (float)($_POST['gross_weight'] ?? 0);
$dim_width          = (int)($_POST['dim_width'] ?? 0);
$dim_length         = (int)($_POST['dim_length'] ?? 0);
$dim_high           = (int)($_POST['dim_high'] ?? 0);
$paper_interleave = (isset($_POST['paper_interleave']) && $_POST['paper_interleave'] === 'Y') ? 'Y' : 'N';

if (empty($product_no)) {
    echo json_encode(['success' => false, 'message' => 'Product No. is required.']);
    exit();
}

try {
    // ดึงข้อมูลสินค้า CRSHPROD1
    $stmt_item = $conn->prepare("SELECT TOP 1 * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_item->execute([':pno' => $product_no]);
    $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Product No. {$product_no} not found in CRSHPROD1.");
    }

    $product_id      = trim($item['PRODUCT_ID'] ?? '');
    $job_order_no    = trim($item['JOB_ORDER'] ?? '');
    $sale_order_no   = trim($item['SALEORDER_NO'] ?? '');
    $sale_order_item = trim($item['SALEORDER_ITEM'] ?? '');
    $material_in     = trim($item['MATERIAL_IN'] ?? '');
    $vmi_status      = trim($item['VMI_STATUS'] ?? 'NV');
    $weight_piece    = (float)($item['WEIGHT_PIECE'] ?? 0);
    $actual_weight   = (float)($item['CRSH_ACTUALWEIGHT'] ?? 0);
    $combine_weight  = (float)($item['CRSH_COMBINEWEIGHT'] ?? 0);
    $spec_width      = (float)($item['WIDTH'] ?? 0);
    $spec_length     = (float)($item['LENGTH'] ?? 0);
    $area_size       = (float)($item['AREA_SIZE'] ?? 0);

    // คำนวณ Net Weight
    $pack_net_weight = $gross_weight - ($top_weight + $bottom_weight);
    if ($pack_net_weight <= 0) {
        throw new Exception("Product Net Weight must be greater than 0 kg.");
    }

    $pack_net_piece = ($weight_piece > 0) ? round($pack_net_weight / $weight_piece) : 0;
    if ($pack_net_piece <= 0) {
        throw new Exception("Product Net Pieces must be greater than 0.");
    }

    // ดึงข้อมูลสเปกพาเลท
    $strWidth  = ($spec_width < 1000) ? sprintf('%08.3f', $spec_width) : sprintf('%08.3f', $spec_width);
    $strLength = ($spec_length < 1000) ? sprintf('%08.3f', $spec_length) : sprintf('%08.3f', $spec_length);

    $stmt_pkg = $conn->prepare("SELECT TOP 1 * FROM Material_PackageMaster1 
                               WHERE PRODUCT_ID = :pid AND MATERIAL_CODE = :mcode 
                                 AND (WIDTH_FROM <= :w_val AND WIDTH_TO >= :w_val) 
                                 AND (LENGTH_FROM <= :l_val AND LENGTH_TO >= :l_val)");
    $stmt_pkg->execute([
        ':pid'   => $product_id,
        ':mcode' => $material_code,
        ':w_val' => $strWidth,
        ':l_val' => $strLength
    ]);
    $pkg = $stmt_pkg->fetch(PDO::FETCH_ASSOC);

    if (!$pkg) {
        throw new Exception("Pallet specification for code '{$material_code}' not found.");
    }

    $intPackageWidth        = (int)($pkg['WIDTH_MM'] ?? 0);
    $intWidthPlus           = (int)($pkg['WIDTH_PLUS'] ?? 30);
    $intWidthMinus          = (int)($pkg['WIDTH_MINUS'] ?? 30);
    $intPackageLength       = (int)($pkg['LENGTH_MM'] ?? 0);
    $intLengthPlus          = (int)($pkg['LENGTH_PLUS'] ?? 30);
    $intLengthMinus         = (int)($pkg['LENGTH_MINUS'] ?? 30);
    $intPackageHigh         = (int)($pkg['HIGH_MM'] ?? 0);
    $intBottomPackageWeight = (int)($pkg['PACKAGE_WEIGHT'] ?? 0);
    $strTopPackage          = trim($pkg['SMATERIAL_CODE'] ?? '');

    // ตรวจสอบข้อมูลฝาบน MTRLSPCT1
    $intTopPackageWeight = 0;
    if (!empty($strTopPackage)) {
        $stmt_top = $conn->prepare("SELECT TOP 1 HIGH_MM, PACKAGE_WEIGHT FROM MTRLSPCT1 WHERE PACKAGE_TYPE = 'TP' AND MATERIAL_CODE = :tcode");
        $stmt_top->execute([':tcode' => $strTopPackage]);
        $top = $stmt_top->fetch(PDO::FETCH_ASSOC);
        if ($top) {
            $intPackageHigh      += (int)($top['HIGH_MM'] ?? 0);
            $intTopPackageWeight  = (int)($top['PACKAGE_WEIGHT'] ?? 0);
        }
    }

    // Validation ตามกฎ pk1.txt
    if ($product_id !== 'WR' && substr($product_no, 8, 1) !== 'R') {
        $maxAllowWeight = $actual_weight + ($actual_weight * 0.05);
        $minAllowWeight = $actual_weight - ($actual_weight * 0.15);
        if ($pack_net_weight > $maxAllowWeight) {
            throw new Exception("Net Weight ({$pack_net_weight} kg) exceeds Actual Weight +5% (" . round($maxAllowWeight) . " kg).");
        }
        if ($pack_net_weight < $minAllowWeight) {
            throw new Exception("Net Weight ({$pack_net_weight} kg) is below Actual Weight -15% (" . round($minAllowWeight) . " kg).");
        }

        $intMaxPackageWidth = $intPackageWidth + $intWidthPlus;
        $intMinPackageWidth = $intPackageWidth - $intWidthMinus;
        if ($dim_width > $intMaxPackageWidth || $dim_width < $intMinPackageWidth) {
            throw new Exception("Dimension Width ({$dim_width} mm) exceeds tolerance ({$intMinPackageWidth} - {$intMaxPackageWidth} mm).");
        }

        $intMaxPackageLength = $intPackageLength + $intLengthPlus;
        $intMinPackageLength = $intPackageLength - $intLengthMinus;
        if ($dim_length > $intMaxPackageLength || $dim_length < $intMinPackageLength) {
            throw new Exception("Dimension Length ({$dim_length} mm) exceeds tolerance ({$intMinPackageLength} - {$intMaxPackageLength} mm).");
        }

        $netHigh = $dim_high - $intPackageHigh;
        if ($netHigh < 100 || $netHigh > 1000) {
            throw new Exception("Product Net Height ({$netHigh} mm) must be between 100 and 1000 mm.");
        }

        // Density 2.71
        $intWeightPerPallet = 0;
        if ($product_id === 'CC') {
            $intWeightPerPallet = round(((((((22 / 7) * ($spec_width * $spec_width)) / 4) * $netHigh) / 1000000) * 2.71) * $pack_stack_number);
        } elseif ($product_id === 'SH') {
            $intWeightPerPallet = round(((($spec_width * $spec_length * $netHigh) / 1000000) * 2.71) * $pack_stack_number);
        } elseif ($product_id === 'NC') {
            $intWeightPerPallet = round(((($area_size * $netHigh) / 1000000) * 2.71) * $pack_stack_number);
        }

        $dblMaxTheo = $intWeightPerPallet + ($intWeightPerPallet * 0.03);
        $dblMinTheo = $intWeightPerPallet - ($intWeightPerPallet * 0.03);
        if ($pack_net_weight > $dblMaxTheo || $pack_net_weight < $dblMinTheo) {
            throw new Exception("Net Weight ({$pack_net_weight} kg) is outside theoretical calculation ±3% (" . round($dblMinTheo) . " - " . round($dblMaxTheo) . " kg).");
        }
    }

    // คำนวณสะสมยอด Job Order และ Sales Order
    $stmt_job = $conn->prepare("SELECT TOP 1 * FROM JOBORDER1 WHERE JOB_ORDER = :job");
    $stmt_job->execute([':job' => $job_order_no]);
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    $stmt_so = $conn->prepare("SELECT TOP 1 * FROM CSTMORDR2 WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
    $stmt_so->execute([':so' => $sale_order_no, ':item' => $sale_order_item]);
    $so = $stmt_so->fetch(PDO::FETCH_ASSOC);

    $old_pack_net_weight = 0;
    $old_pack_net_piece  = 0;

    $stmt_chk = $conn->prepare("SELECT TOP 1 * FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
    $stmt_chk->execute([':pno' => $product_no]);
    $chk_pack = $stmt_chk->fetch(PDO::FETCH_ASSOC);
    $is_update = !empty($chk_pack);

    if ($is_update) {
        $old_pack_net_weight = (float)($chk_pack['PACK_NETWEIGHT'] ?? 0);
        $old_pack_net_piece  = (float)($chk_pack['PACK_NETPIECE'] ?? 0);
    }

    $var_net_weight = $pack_net_weight - $old_pack_net_weight;
    $var_net_piece  = $pack_net_piece - $old_pack_net_piece;

    $job_pack_weight  = (float)($job['JOB_PACKWEIGHT'] ?? 0) + $var_net_weight;
    $job_pack_piece   = (float)($job['JOB_PACKPIECE'] ?? 0) + $var_net_piece;
    $job_stock_weight = (float)($job['JOB_STOCKWEIGHT'] ?? 0) + $var_net_weight;
    $job_stock_piece  = (float)($job['JOB_STOCKPIECE'] ?? 0) + $var_net_piece;

    $ctm2_pack_weight  = (float)($so['CTM2_PACKWEIGHT'] ?? 0) + $var_net_weight;
    $ctm2_pack_piece   = (float)($so['CTM2_PACKPIECE'] ?? 0) + $var_net_piece;
    $ctm2_stock_weight = (float)($so['CTM2_STOCKWEIGHT'] ?? 0) + $var_net_weight;
    $ctm2_stock_piece  = (float)($so['CTM2_STOCKPIECE'] ?? 0) + $var_net_piece;

    $job_rel_wt  = (float)($job['JOB_RELEASEWEIGHT'] ?? 0);
    $job_min_tol = (float)($job['JOB_MINTOLERANCE'] ?? 0);
    $job_min_wt  = $job_rel_wt - ($job_rel_wt * $job_min_tol / 100);

    $job_status = trim($job['JOB_STATUS'] ?? 'OP');
    if ($job_pack_weight >= $job_min_wt && $job_min_wt > 0) {
        $job_status = 'CP';
    }

    // Running Label Number
    $current_label = (int)($item['PACK_LABEL'] ?? 0) + 1;

    // Database Transaction
    $conn->beginTransaction();

    if (!$is_update) {
        $stmt_ins = $conn->prepare("INSERT INTO PACKPROD1 (
            PRODUCT_NO, JOB_ORDER, SALEORDER_NO, SALEORDER_ITEM, MATERIAL_IN, PACK_PONO, PACK_POITEM,
            TRANSFER_NO, LINE_PROCESS, PRODUCT_ID, TRANSFER_DATE, RECEIVE_DATE, PACK_DATE, PACK_COMBINEWEIGHT,
            PACK_PACKAGEWEIGHT, PACK_NETWEIGHT, PACK_NETPIECE, PACK_MATERIALCODE, PACK_STACKNUMBER,
            PACK_STACKHIGH, PACK_LABEL, PACK_OPERATOR1, PACK_OPERATOR2, PACK_OPERATEDATE, VMI_STATUS, PACK_STATUS
        ) VALUES (
            :pno, :job, :so, :so_item, :mat_in, :pono, :poitem,
            '', :line, :pid, GETDATE(), GETDATE(), GETDATE(), :cmb_wt,
            :pkg_wt, :net_wt, :net_pc, :mcode, :stk_num,
            :stk_hi, :lbl, :op1, '', GETDATE(), :vmi, 'ST'
        )");
        $stmt_ins->execute([
            ':pno'     => $product_no,
            ':job'     => $job_order_no,
            ':so'      => $sale_order_no,
            ':so_item' => $sale_order_item,
            ':mat_in'  => $material_in,
            ':pono'    => trim($so['CTM2_PONO'] ?? ''),
            ':poitem'  => trim($so['CTM2_POITEM'] ?? ''),
            ':line'    => $line_process,
            ':pid'     => $product_id,
            ':cmb_wt'  => $combine_weight,
            ':pkg_wt'  => ($top_weight + $bottom_weight),
            ':net_wt'  => $pack_net_weight,
            ':net_pc'  => $pack_net_piece,
            ':mcode'   => $material_code,
            ':stk_num' => $pack_stack_number,
            ':stk_hi'  => ($dim_high - $intPackageHigh),
            ':lbl'     => $current_label,
            ':op1'     => $user_id,
            ':vmi'     => $vmi_status
        ]);
    } else {
        $stmt_upd = $conn->prepare("UPDATE PACKPROD1 SET 
            PACK_PACKAGEWEIGHT = :pkg_wt, 
            PACK_NETWEIGHT     = :net_wt, 
            PACK_NETPIECE      = :net_pc, 
            PACK_MATERIALCODE  = :mcode, 
            PACK_STACKNUMBER   = :stk_num, 
            PACK_STACKHIGH     = :stk_hi, 
            PACK_LABEL         = :lbl, 
            PACK_UPDATE        = :usr, 
            PACK_UPDATEDATE    = GETDATE(), 
            VMI_STATUS         = :vmi 
        WHERE PRODUCT_NO = :pno");
        $stmt_upd->execute([
            ':pkg_wt'  => ($top_weight + $bottom_weight),
            ':net_wt'  => $pack_net_weight,
            ':net_pc'  => $pack_net_piece,
            ':mcode'   => $material_code,
            ':stk_num' => $pack_stack_number,
            ':stk_hi'  => ($dim_high - $intPackageHigh),
            ':lbl'     => $current_label,
            ':usr'     => $user_id,
            ':vmi'     => $vmi_status,
            ':pno'     => $product_no
        ]);
    }

    // UPDATE CRSHPROD1
    $stmt_crsh = $conn->prepare("UPDATE CRSHPROD1 SET 
        CRSH_PACKWEIGHT       = :net_wt, 
        CRSH_PACKPIECE        = :net_pc, 
        CRSH_TOPWEIGHT        = :top_wt, 
        CRSH_BOTTOMWEIGHT     = :bot_wt, 
        CRSH_DIMENSIONWIDTH   = :dw, 
        CRSH_DIMENSIONLENGTH  = :dl, 
        CRSH_DIMENSIONHIGH    = :dh, 
        CRSH_STATUS           = 'ST', 
        MATERIAL_CODE         = :mcode, 
        CRSH_STACKNUMBER      = :stk_num, 
        CRSH_STACKHIGH        = :stk_hi, 
        PAPER_INTERLEAVE      = :pi,
        CRSH_UPDATE           = :usr,
        CRSH_UPDATEDATE       = GETDATE()
    WHERE PRODUCT_NO = :pno");
    $stmt_crsh->execute([
        ':net_wt'  => $pack_net_weight,
        ':net_pc'  => $pack_net_piece,
        ':top_wt'  => $top_weight,
        ':bot_wt'  => $bottom_weight,
        ':dw'      => $dim_width,
        ':dl'      => $dim_length,
        ':dh'      => $dim_high,
        ':mcode'   => $material_code,
        ':stk_num' => $pack_stack_number,
        ':stk_hi'  => ($dim_high - $intPackageHigh),
        ':pi'      => $paper_interleave,
        ':usr'     => $user_id,
        ':pno'     => $product_no
    ]);

    // UPDATE JOBORDER1
    if (!empty($job_order_no)) {
        $stmt_job_upd = $conn->prepare("UPDATE JOBORDER1 SET 
            JOB_PACKWEIGHT  = :jpw, 
            JOB_PACKPIECE   = :jpp, 
            JOB_STOCKWEIGHT = :jsw, 
            JOB_STOCKPIECE  = :jsp, 
            JOB_STATUS      = :jstatus 
        WHERE JOB_ORDER = :job");
        $stmt_job_upd->execute([
            ':jpw'     => $job_pack_weight,
            ':jpp'     => $job_pack_piece,
            ':jsw'     => $job_stock_weight,
            ':jsp'     => $job_stock_piece,
            ':jstatus' => $job_status,
            ':job'     => $job_order_no
        ]);
    }

    // UPDATE CSTMORDR2
    if (!empty($sale_order_no)) {
        $stmt_so_upd = $conn->prepare("UPDATE CSTMORDR2 SET 
            CTM2_PACKWEIGHT  = :cpw, 
            CTM2_PACKPIECE   = :cpp, 
            CTM2_STOCKWEIGHT = :csw, 
            CTM2_STOCKPIECE  = :csp 
        WHERE SALEORDER_NO = :so AND SALEORDER_ITEM = :item");
        $stmt_so_upd->execute([
            ':cpw'  => $ctm2_pack_weight,
            ':cpp'  => $ctm2_pack_piece,
            ':csw'  => $ctm2_stock_weight,
            ':csp'  => $ctm2_stock_piece,
            ':so'   => $sale_order_no,
            ':item' => $sale_order_item
        ]);
    }

    $conn->commit();

    echo json_encode([
        'success'    => true, 
        'message'    => 'Packaging data saved successfully.',
        'product_no' => $product_no
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>