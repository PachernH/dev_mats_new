<?php
// model/maintain_change_status_mats.php
session_start();
header('Content-Type: application/json; charset=utf-8');

include_once __DIR__ . '/../function_mats.php';
include_once __DIR__ . '/../dbcon_mats-new.php';

$iduser_func = isset($_SESSION['ID']) ? trim((string)$_SESSION['ID']) : '';
if ($iduser_func === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Session หมดอายุ กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

$current_dept = strtoupper(trim((string)($_SESSION['DEPARTMENT'] ?? '')));
$current_func = strtoupper(trim((string)($_SESSION['FUNC'] ?? '')));

$is_wh_authorized = ($current_dept === 'WH');
$is_dr_authorized = in_array($current_dept, ['MK', 'PP'], true);
$is_it_authorized = ($current_dept === 'IT' || $current_func === 'IT');
$can_maintain_status = ($is_wh_authorized || $is_dr_authorized || $is_it_authorized);

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$action = strtoupper(trim((string)($_REQUEST['action'] ?? ($jsonInput['action'] ?? ''))));

if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
    exit;
}

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    switch ($action) {
        case 'LIST':
            handleList($conn, $_GET);
            break;

        case 'DETAIL':
            $pno = strtoupper(trim((string)($_GET['pno'] ?? '')));
            if ($pno === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'message' => 'กรุณาระบุ Product No']);
                exit;
            }
            handleDetail($conn, $pno);
            break;

        case 'UPDATE_STATUS':
            if (!$can_maintain_status) {
                http_response_code(403);
                echo json_encode([
                    'ok' => false,
                    'message' => 'คุณไม่มีสิทธิ์เปลี่ยนสถานะสินค้า (อนุญาตเฉพาะ WH, MK, PP และ IT)'
                ]);
                exit;
            }
            handleUpdateStatus($conn, $jsonInput);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'ไม่พบ action ที่ร้องขอ']);
            break;
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}

function handleList(PDO $conn, array $params): void
{
    $q = strtoupper(trim((string)($params['q'] ?? '')));
    $statusFilter = strtoupper(trim((string)($params['status'] ?? '')));

    $sql = "SELECT TOP 500
                p.PRODUCT_NO,
                p.SALEORDER_NO,
                p.SALEORDER_ITEM,
                p.JOB_ORDER,
                p.MATERIAL_IN,
                p.PRODUCT_ID,
                p.PACK_NETWEIGHT,
                p.PACK_NETPIECE,
                p.PACK_REMARK,
                p.VMI_STATUS,
                p.PACK_STATUS,
                COALESCE(c.ALLOY, co.ALLOY) AS ALLOY,
                COALESCE(c.TEMPER, co.TEMPER) AS TEMPER,
                COALESCE(c.THICKNESS, co.THICKNESS) AS THICKNESS,
                COALESCE(c.WIDTH, co.WIDTH) AS WIDTH,
                c.LENGTH AS LENGTH
            FROM PACKPROD1 p
            LEFT JOIN CRSHPROD1 c ON p.PRODUCT_NO = c.PRODUCT_NO
            LEFT JOIN COILPROD1 co ON p.PRODUCT_NO = co.COIL_NO
            WHERE (p.PACK_STATUS = 'ST' OR p.PACK_STATUS = 'DS')";

    $bind = [];
    if (in_array($statusFilter, ['ST', 'DS'], true)) {
        $sql .= " AND p.PACK_STATUS = :status";
        $bind[':status'] = $statusFilter;
    }

    if ($q !== '') {
        $sql .= " AND (p.PRODUCT_NO LIKE :q OR p.JOB_ORDER LIKE :q OR p.SALEORDER_NO LIKE :q)";
        $bind[':q'] = '%' . $q . '%';
    }

    $sql .= " ORDER BY p.PRODUCT_NO ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'count' => count($rows),
        'data' => $rows
    ]);
}

function fetchProductDetailPayload(PDO $conn, string $pno): ?array
{
    $sqlStock = "SELECT
                    A.PRODUCT_NO, A.SALEORDER_NO, A.SALEORDER_ITEM, A.JOB_ORDER, A.MATERIAL_IN,
                    A.PRODUCT_ID, A.PACK_NETWEIGHT, A.PACK_NETPIECE, A.PACK_REMARK, A.VMI_STATUS, A.PACK_STATUS,
                    B.SCHEDULE_DATE, B.JOB_MAXTOLERANCE, B.JOB_MINTOLERANCE,
                    B.JOB_RELEASEPIECE, B.JOB_RELEASEWEIGHT, B.JOB_ACTUALPIECE, B.JOB_ACTUALWEIGHT,
                    B.JOB_PACKPIECE, B.JOB_PACKWEIGHT, B.JOB_STOCKPIECE, B.JOB_STOCKWEIGHT,
                    C.CTM2_PONO, C.CTM2_POITEM, C.CTM2_PODATE, C.CONFIRM_DATE,
                    C.CTM2_MAXTOLERANCE, C.CTM2_MINTOLERANCE,
                    C.CTM2_ORDERPIECE, C.CTM2_ORDERWEIGHT,
                    C.CTM2_REJECTPIECE, C.CTM2_REJECTWEIGHT,
                    C.CTM2_REPLACEPIECE, C.CTM2_REPLACEWEIGHT,
                    C.CTM2_ACTUALPIECE, C.CTM2_ACTUALWEIGHT,
                    C.CTM2_PACKPIECE, C.CTM2_PACKWEIGHT, C.CTM2_STOCKPIECE, C.CTM2_STOCKWEIGHT
                 FROM PACKPROD1 A
                 INNER JOIN JOBORDER1 B ON A.JOB_ORDER = B.JOB_ORDER
                 INNER JOIN CSTMORDR2 C ON B.SALEORDER_NO = C.SALEORDER_NO AND B.SALEORDER_ITEM = C.SALEORDER_ITEM
                 WHERE A.PRODUCT_NO = :pno";

    $stmt = $conn->prepare($sqlStock);
    $stmt->execute([':pno' => $pno]);
    $stockRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$stockRow) {
        return null;
    }

    $prodId = strtoupper(trim((string)($stockRow['PRODUCT_ID'] ?? '')));

    if ($prodId === 'CO') {
        $stmtSpec = $conn->prepare("SELECT * FROM COILPROD1 WHERE COIL_NO = :pno");
        $stmtSpec->execute([':pno' => $pno]);
        $specRow = $stmtSpec->fetch(PDO::FETCH_ASSOC) ?: [];
    } else {
        $stmtSpec = $conn->prepare("SELECT * FROM CRSHPROD1 WHERE PRODUCT_NO = :pno");
        $stmtSpec->execute([':pno' => $pno]);
        $specRow = $stmtSpec->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $netWt = (float)($stockRow['PACK_NETWEIGHT'] ?? 0);
    $netPc = (float)($stockRow['PACK_NETPIECE'] ?? 0);

    $jobRelWt = (float)($stockRow['JOB_RELEASEWEIGHT'] ?? 0);
    $jobRelPc = (float)($stockRow['JOB_RELEASEPIECE'] ?? 0);
    $jobMaxTol = (float)($stockRow['JOB_MAXTOLERANCE'] ?? 0);
    $jobMinTol = (float)($stockRow['JOB_MINTOLERANCE'] ?? 0);

    $ctmOrdWt = (float)($stockRow['CTM2_ORDERWEIGHT'] ?? 0);
    $ctmRepWt = (float)($stockRow['CTM2_REPLACEWEIGHT'] ?? 0);
    $ctmOrdPc = (float)($stockRow['CTM2_ORDERPIECE'] ?? 0);
    $ctmRepPc = (float)($stockRow['CTM2_REPLACEPIECE'] ?? 0);
    $ctmMaxTol = (float)($stockRow['CTM2_MAXTOLERANCE'] ?? 0);
    $ctmMinTol = (float)($stockRow['CTM2_MINTOLERANCE'] ?? 0);

    $wtPiece = (float)($specRow['WEIGHT_PIECE'] ?? 0);

    $ctmTotalWt = round($ctmOrdWt + $ctmRepWt);
    $ctmTotalPc = round($ctmOrdPc + $ctmRepPc);

    $calc = [
        'job_max_weight'   => 0,
        'job_min_weight'   => 0,
        'job_max_piece'    => 0,
        'job_min_piece'    => 0,
        'job_rel_piece'    => round($jobRelPc),
        'ctm2_total_weight'=> $ctmTotalWt,
        'ctm2_total_piece' => $ctmTotalPc,
        'ctm2_max_weight'  => 0,
        'ctm2_min_weight'  => 0,
        'ctm2_max_piece'   => 0,
        'ctm2_min_piece'   => 0,
    ];

    if (in_array($prodId, ['CC', 'SH', 'WR', 'NC'], true)) {
        $jobMaxWt = $jobRelWt + ($jobRelWt * $jobMaxTol) / 100.0;
        $jobMinWt = $jobRelWt - ($jobRelWt * $jobMinTol) / 100.0;
        $ctmMaxWt = $ctmTotalWt + ($ctmTotalWt * $ctmMaxTol) / 100.0;
        $ctmMinWt = $ctmTotalWt - ($ctmTotalWt * $ctmMinTol) / 100.0;

        $calc['job_max_weight']  = round($jobMaxWt);
        $calc['job_min_weight']  = round($jobMinWt);
        $calc['ctm2_max_weight'] = round($ctmMaxWt);
        $calc['ctm2_min_weight'] = round($ctmMinWt);

        if ($prodId !== 'WR' && $wtPiece > 0) {
            $calc['job_max_piece']  = round($jobMaxWt / $wtPiece);
            $calc['job_min_piece']  = round($jobMinWt / $wtPiece);
            $calc['ctm2_max_piece'] = round($ctmMaxWt / $wtPiece);
            $calc['ctm2_min_piece'] = round($ctmMinWt / $wtPiece);
        } else {
            $calc['ctm2_total_piece'] = 0;
        }
    } elseif ($prodId === 'CO') {
        $calc['ctm2_total_piece'] = 0;
    }

    $jobPackWt = (float)($stockRow['JOB_PACKWEIGHT'] ?? 0);
    $jobPackPc = (float)($stockRow['JOB_PACKPIECE'] ?? 0);
    $jobStockWt = (float)($stockRow['JOB_STOCKWEIGHT'] ?? 0);
    $jobStockPc = (float)($stockRow['JOB_STOCKPIECE'] ?? 0);

    $ctmPackWt = (float)($stockRow['CTM2_PACKWEIGHT'] ?? 0);
    $ctmPackPc = (float)($stockRow['CTM2_PACKPIECE'] ?? 0);
    $ctmStockWt = (float)($stockRow['CTM2_STOCKWEIGHT'] ?? 0);
    $ctmStockPc = (float)($stockRow['CTM2_STOCKPIECE'] ?? 0);

    $deductPc = ($prodId === 'CO') ? 1 : $netPc;
    $acPreview = [
        'job_pack_weight'   => $jobPackWt - $netWt,
        'job_pack_piece'    => $jobPackPc - $deductPc,
        'job_stock_weight'  => $jobStockWt - $netWt,
        'job_stock_piece'   => $jobStockPc - $deductPc,
        'ctm2_pack_weight'  => $ctmPackWt - $netWt,
        'ctm2_pack_piece'   => $ctmPackPc - $deductPc,
        'ctm2_stock_weight' => $ctmStockWt - $netWt,
        'ctm2_stock_piece'  => $ctmStockPc - $deductPc,
    ];

    return [
        'stock'      => $stockRow,
        'spec'       => $specRow,
        'calculated' => $calc,
        'ac_preview' => $acPreview
    ];
}

function handleDetail(PDO $conn, string $pno): void
{
    $detail = fetchProductDetailPayload($conn, $pno);
    if ($detail === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => "ไม่พบข้อมูลสินค้า {$pno} ใน PACKPROD1"]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'data' => $detail
    ]);
}

function handleUpdateStatus(PDO $conn, array $input): void
{
    $pno = strtoupper(trim((string)($input['product_no'] ?? '')));
    $newStatus = strtoupper(trim((string)($input['new_status'] ?? '')));
    $remark = substr(strtoupper(trim((string)($input['pack_remark'] ?? ''))), 0, 50);

    if ($pno === '' || !in_array($newStatus, ['ST', 'DS', 'AC'], true)) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'message' => 'ข้อมูลไม่ถูกต้อง: กรุณาระบุ Product No และสถานะที่อนุญาต (ST, DS, AC)'
        ]);
        exit;
    }

    $detail = fetchProductDetailPayload($conn, $pno);
    if ($detail === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => "ไม่พบข้อมูลสินค้า {$pno}"]);
        exit;
    }

    $stockRow = $detail['stock'];
    $currentPackStatus = strtoupper(trim((string)($stockRow['PACK_STATUS'] ?? '')));
    if (!in_array($currentPackStatus, ['ST', 'DS'], true)) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'message' => "สถานะปัจจุบัน ({$currentPackStatus}) ไม่ใช่ ST หรือ DS ไม่สามารถดำเนินการได้"
        ]);
        exit;
    }

    $prodId = strtoupper(trim((string)($stockRow['PRODUCT_ID'] ?? '')));
    $allowedProdIds = ['CC', 'CS', 'SH', 'SS', 'CO', 'OS', 'NC', 'WR'];
    if (!in_array($prodId, $allowedProdIds, true)) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => "ประเภทรหัสสินค้า ({$prodId}) ไม่อยู่ในเงื่อนไขที่รองรับ"
        ]);
        exit;
    }

    $vmiStatus = strtoupper(trim((string)($stockRow['VMI_STATUS'] ?? '')));
    if ($newStatus === 'AC') {
        if ($vmiStatus === 'ST') {
            $vmiStatus = 'PK';
        } elseif (in_array($vmiStatus, ['PP', 'CL'], true)) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => 'ผลิตภัณฑ์นี้เป็นแบบ VMI ที่ได้ทำการแจ้งสินค้าอยู่ใน Stock กับ MIL แล้ว ไม่สามารถเปลี่ยนสถานะผลิตภัณฑ์เป็น AC ให้ติดต่อแผนก Marketing หรือ Planning เพื่อแก้ไขข้อมูล VMI จึงกลับมาเปลี่ยนสถานะ'
            ]);
            exit;
        }
    }

    $jobOrder = trim((string)($stockRow['JOB_ORDER'] ?? ''));
    $soNo = trim((string)($stockRow['SALEORDER_NO'] ?? ''));
    $soItem = trim((string)($stockRow['SALEORDER_ITEM'] ?? ''));

    $conn->beginTransaction();

    if (in_array($newStatus, ['ST', 'DS'], true)) {
        if (in_array($prodId, ['CC', 'CS', 'SH', 'SS', 'WR', 'NC'], true)) {
            $stmt1 = $conn->prepare("UPDATE CRSHPROD1 SET CRSH_STATUS = :status WHERE PRODUCT_NO = :pno");
            $stmt1->execute([':status' => $newStatus, ':pno' => $pno]);
        } elseif (in_array($prodId, ['CO', 'OS'], true)) {
            $stmt1 = $conn->prepare("UPDATE COILPROD1 SET COIL_STATUS = :status WHERE COIL_NO = :pno");
            $stmt1->execute([':status' => $newStatus, ':pno' => $pno]);
        }

        $stmt4 = $conn->prepare("UPDATE PACKPROD1
                                SET VMI_STATUS = :vmi, PACK_STATUS = :status, PACK_REMARK = :remark
                                WHERE PRODUCT_NO = :pno");
        $stmt4->execute([
            ':vmi'    => $vmiStatus,
            ':status' => $newStatus,
            ':remark' => $remark,
            ':pno'    => $pno
        ]);
    } elseif ($newStatus === 'AC') {
        $ac = $detail['ac_preview'];

        if (in_array($prodId, ['CC', 'CS', 'SH', 'SS', 'WR', 'NC'], true)) {
            $stmt1 = $conn->prepare("UPDATE CRSHPROD1
                                    SET CRSH_PACKWEIGHT = 0, CRSH_PACKPIECE = 0, CRSH_STATUS = :status
                                    WHERE PRODUCT_NO = :pno");
            $stmt1->execute([':status' => $newStatus, ':pno' => $pno]);
        } elseif (in_array($prodId, ['CO', 'OS'], true)) {
            $stmt1 = $conn->prepare("UPDATE COILPROD1
                                    SET SALEORDER_NO = '', SALEORDER_ITEM = '', JOB_ORDER = '',
                                        COIL_PACKWEIGHT = 0, COIL_STATUS = :status
                                    WHERE COIL_NO = :pno");
            $stmt1->execute([':status' => $newStatus, ':pno' => $pno]);
        }

        $stmt2 = $conn->prepare("UPDATE JOBORDER1
                                SET JOB_PACKWEIGHT = :pw, JOB_PACKPIECE = :pp,
                                    JOB_STOCKWEIGHT = :sw, JOB_STOCKPIECE = :sp
                                WHERE JOB_ORDER = :job");
        $stmt2->execute([
            ':pw'  => $ac['job_pack_weight'],
            ':pp'  => $ac['job_pack_piece'],
            ':sw'  => $ac['job_stock_weight'],
            ':sp'  => $ac['job_stock_piece'],
            ':job' => $jobOrder
        ]);

        $stmt3 = $conn->prepare("UPDATE CSTMORDR2
                                SET CTM2_PACKWEIGHT = :pw, CTM2_PACKPIECE = :pp,
                                    CTM2_STOCKWEIGHT = :sw, CTM2_STOCKPIECE = :sp
                                WHERE SALEORDER_NO = :sono AND SALEORDER_ITEM = :soitem");
        $stmt3->execute([
            ':pw'     => $ac['ctm2_pack_weight'],
            ':pp'     => $ac['ctm2_pack_piece'],
            ':sw'     => $ac['ctm2_stock_weight'],
            ':sp'     => $ac['ctm2_stock_piece'],
            ':sono'   => $soNo,
            ':soitem' => $soItem
        ]);

        $stmtDel = $conn->prepare("DELETE FROM PACKPROD1 WHERE PRODUCT_NO = :pno");
        $stmtDel->execute([':pno' => $pno]);
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'message' => "อัปเดตสถานะสินค้า {$pno} เป็น {$newStatus} เรียบร้อยแล้ว",
        'product_no' => $pno,
        'new_status' => $newStatus
    ]);
}