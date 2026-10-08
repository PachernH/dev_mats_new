<?php
header('Content-Type: application/json; charset=utf-8');
include '../dbcon_mats-new.php';

$source  = $_GET['source'] ?? '';
$item_no = $_GET['item_no'] ?? '';

// Helper function จัดการค่า null / empty และแปลงเลขเป็น ทศนิยม 2 ตำแหน่ง
function formatValue($val, $isNumeric = false) {
    if ($isNumeric) {
        return ($val !== null && $val !== '') ? number_format((float)$val, 2, '.', '') : '0.00';
    }
    return (!empty(trim($val))) ? trim($val) : '-';
}

if ($source && !$item_no) {
    if ($source === 'FG') {
        $sql = "SELECT p.PRODUCT_NO AS id, 
                    p.PRODUCT_NO + ' [' + p.PACK_STATUS + '] (' + CAST(p.PACK_NETWEIGHT AS VARCHAR) + ' Kg)' AS text 
                FROM PACKPROD1 AS p 
                WHERE (p.PACK_STATUS = 'DS' OR p.PACK_STATUS = 'ST')
                AND NOT EXISTS (
                    SELECT 1 FROM PRODRMLT2 r2 WHERE r2.PRODUCT_NO = p.PRODUCT_NO
                )";
    } elseif ($source === 'CC') {
        $sql = "SELECT c.PRODUCT_NO AS id, 
                    c.PRODUCT_NO + ' [' + c.CRSH_STATUS + '] (' + CAST(c.CRSH_ACTUALWEIGHT AS VARCHAR) + ' Kg)' AS text 
                FROM CRSHPROD1 AS c 
                WHERE c.CRSH_STATUS IN ('AC','OP','RJ','RM')
                AND NOT EXISTS (
                    SELECT 1 FROM PRODRMLT2 r2 WHERE r2.PRODUCT_NO = c.PRODUCT_NO
                )";
    } elseif ($source === 'CO') {
        $sql = "SELECT c.COIL_NO AS id, 
                    c.COIL_NO + ' [' + c.COIL_STATUS + '] (' + CAST(c.COIL_BALANCEWEIGHT AS VARCHAR) + ' Kg)' AS text 
                FROM COILPROD1 AS c 
                WHERE c.COIL_STATUS IN ('AC','OP','RJ','RM')
                AND NOT EXISTS (
                    SELECT 1 FROM PRODRMLT2 r2 WHERE r2.COIL_NO = c.COIL_NO
                )";
    }
    
    $stmt = $conn->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($source && $item_no) {
    $data = [];

    if ($source === 'FG') {
        $sql1 = "SELECT p.PRODUCT_NO, p.PRODUCT_ID, p.PACK_PONO, p.JOB_ORDER, j.ALLOY, j.TEMPER, j.GRADE, 
                        j.THICKNESS, j.SURFACE_GRADE, j.METALLURGICAL_GRADE, j.[LENGTH], j.WIDTH, p.PACK_STATUS, p.PACK_NETWEIGHT 
                 FROM PACKPROD1 AS p 
                 LEFT JOIN JOBORDER1 j ON p.JOB_ORDER = j.JOB_ORDER 
                 WHERE p.PRODUCT_NO = :item_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([':item_no' => $item_no]);
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

        if ($row1) {
            $data = [
                'product_no'          => formatValue($row1['PRODUCT_NO']),
                'coil_no'             => '-',
                'product_id_or_type'  => formatValue($row1['PRODUCT_ID']),
                'job_order'           => formatValue($row1['JOB_ORDER']),
                'alloy'               => formatValue($row1['ALLOY']),
                'temper'              => formatValue($row1['TEMPER']),
                'grade'               => formatValue($row1['GRADE']),
                'surface_grade'       => formatValue($row1['SURFACE_GRADE']),
                'metallurgical_grade' => formatValue($row1['METALLURGICAL_GRADE']),
                'thickness'           => formatValue($row1['THICKNESS'], true),
                'width'               => formatValue($row1['WIDTH'], true),
                'length'              => formatValue($row1['LENGTH'], true),
                'product_weight'      => formatValue($row1['PACK_NETWEIGHT'], true),
                'original_status'     => formatValue($row1['PACK_STATUS'])
            ];
        }
    } elseif ($source === 'CC') {
        $sql1 = "SELECT c.PRODUCT_NO, c.PRODUCT_REFERENCE, c.SALEORDER_NO, c.JOB_ORDER, c.PRODUCT_ID, c.ALLOY, c.TEMPER, c.GRADE, 
                        c.THICKNESS, c.SURFACE_GRADE, c.METALLURGICAL_GRADE, c.WIDTH, c.[LENGTH], c.CRSH_ACTUALWEIGHT, c.CRSH_STATUS 
                 FROM CRSHPROD1 AS c 
                 WHERE c.PRODUCT_NO = :item_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([':item_no' => $item_no]);
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

        if ($row1) {
            $data = [
                'product_no'          => formatValue($row1['PRODUCT_NO']),
                'coil_no'             => formatValue($row1['PRODUCT_REFERENCE']),
                'product_id_or_type'  => formatValue($row1['PRODUCT_ID']),
                'job_order'           => formatValue($row1['JOB_ORDER']),
                'alloy'               => formatValue($row1['ALLOY']),
                'temper'              => formatValue($row1['TEMPER']),
                'grade'               => formatValue($row1['GRADE']),
                'surface_grade'       => formatValue($row1['SURFACE_GRADE']),
                'metallurgical_grade' => formatValue($row1['METALLURGICAL_GRADE']),
                'thickness'           => formatValue($row1['THICKNESS'], true),
                'width'               => formatValue($row1['WIDTH'], true),
                'length'              => formatValue($row1['LENGTH'], true),
                'product_weight'      => formatValue($row1['CRSH_ACTUALWEIGHT'], true),
                'original_status'     => formatValue($row1['CRSH_STATUS'])
            ];
        }
    } elseif ($source === 'CO') {
        $sql1 = "SELECT c.COIL_NO, c.PRODUCT_REFERENCE, c.SALEORDER_NO, c.JOB_ORDER, c.COIL_TYPE, c.ALLOY, c.TEMPER, c.GRADE, 
                        c.THICKNESS, c.SURFACE_GRADE, c.METALLURGICAL_GRADE, c.WIDTH, c.COIL_BALANCEWEIGHT, c.COIL_STATUS 
                 FROM COILPROD1 AS c 
                 WHERE c.COIL_NO = :item_no";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([':item_no' => $item_no]);
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

        if ($row1) {
            $data = [
                'product_no'          => formatValue($row1['COIL_NO']),
                'coil_no'             => formatValue($row1['PRODUCT_REFERENCE']),
                'product_id_or_type'  => formatValue($row1['COIL_TYPE']),
                'job_order'           => formatValue($row1['JOB_ORDER']),
                'alloy'               => formatValue($row1['ALLOY']),
                'temper'              => formatValue($row1['TEMPER']),
                'grade'               => formatValue($row1['GRADE']),
                'surface_grade'       => formatValue($row1['SURFACE_GRADE']),
                'metallurgical_grade' => formatValue($row1['METALLURGICAL_GRADE']),
                'thickness'           => formatValue($row1['THICKNESS'], true),
                'width'               => formatValue($row1['WIDTH'], true),
                'length'              => '0.00',
                'product_weight'      => formatValue($row1['COIL_BALANCEWEIGHT'], true),
                'original_status'     => formatValue($row1['COIL_STATUS'])
            ];
        }
    }

    $jobOrder = ($data['job_order'] ?? '-') !== '-' ? $data['job_order'] : '';

    if (!empty($jobOrder)) {
        $sql2 = "SELECT TOP(1) c.CSTMSPPL_ID, c.CTM2_PONO, j.JOB_ORDER, 
                        j.JOB_RELEASEWEIGHT, c.SALEORDER_NO, c.CTM2_ORDERWEIGHT   
                 FROM JOBORDER1 AS j 
                 JOIN CSTMORDR2 c ON j.SALEORDER_NO = c.SALEORDER_NO  
                 WHERE j.JOB_ORDER = :job_order";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([':job_order' => $jobOrder]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($row2) {
            $data['cstm_id']      = formatValue($row2['CSTMSPPL_ID']);
            $data['po_no']        = formatValue($row2['CTM2_PONO']);
            $data['job_weight']   = formatValue($row2['JOB_RELEASEWEIGHT'], true);
            $data['saleorder_no'] = formatValue($row2['SALEORDER_NO']);
            $data['order_weight'] = formatValue($row2['CTM2_ORDERWEIGHT'], true);
        } else {
            setGroup2Default($data);
        }
    } else {
        setGroup2Default($data);
    }

    echo json_encode($data);
    exit;
}

function setGroup2Default(&$data) {
    $data['cstm_id']      = '-';
    $data['po_no']        = '-';
    $data['job_weight']   = '0.00';
    $data['saleorder_no'] = '-';
    $data['order_weight'] = '0.00';
}