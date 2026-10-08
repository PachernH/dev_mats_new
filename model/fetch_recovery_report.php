<?php
header('Content-Type: application/json; charset=utf-8');

include '../dbcon_mats-new.php';

// รับค่า Parameters และทำความสะอาดข้อมูล
$startDate  = isset($_GET['start_date']) && trim($_GET['start_date']) !== '' ? trim($_GET['start_date']) : null;
$endDate    = isset($_GET['end_date']) && trim($_GET['end_date']) !== '' ? trim($_GET['end_date']) : null;
$supplierId = isset($_GET['supplier_id']) && trim($_GET['supplier_id']) !== '' ? trim($_GET['supplier_id']) : null;

try {
    // ===================================================================
    // SQL Root Coil Recovery Report (ปรับปรุงการหา Root Coil ครอบคลุม -R- และ Ref ภายนอก):
    // 1. RootCoils: ค้นหาคอยล์แม่ตั้งต้น (-R-, -1-, -2- หรือไม่มี Parent ในระบบ)
    // 2. DownwardTree: แตกกิ่งลงมาหาคอยล์ลูกหลานในสายการผลิต
    // 3. ProductWeightSummary: สรุปยอดน้ำหนักสินค้า CRSHPROD1 (สถานะ SH, IN, ST)
    // ===================================================================
    $sql = "WITH RootCoils AS (
                SELECT DISTINCT 
                    CAST(c.COIL_NO AS VARCHAR(100)) AS Root_Coil,
                    CAST(c.PRODUCT_REFERENCE AS VARCHAR(100)) AS PRODUCT_REFERENCE,
                    c.COIL_OPERATEDATE AS COIL_STARTDATE,
                    c.COIL_ENDDATE,
                    CAST(c.CSTMSPPL_ID AS VARCHAR(50)) AS CSTMSPPL_ID,
                    CAST(c.ALLOY AS VARCHAR(50)) AS ALLOY,
                    CAST(c.TEMPER AS VARCHAR(50)) AS TEMPER,
                    CAST(c.GRADE AS VARCHAR(50)) AS GRADE,
                    CAST(c.METALLURGICAL_GRADE AS VARCHAR(50)) AS MG,
                    CAST(c.SURFACE_GRADE AS VARCHAR(50)) AS SG,
                    CAST(c.THICKNESS AS FLOAT) AS THICKNESS,
                    CAST(c.WIDTH AS FLOAT) AS WIDTH,
                    CAST(c.COIL_ACTUALWEIGHT AS FLOAT) AS COIL_ACTUALWEIGHT
                FROM COILPROD1 c
                WHERE (
                    -- เงื่อนไข 1: รหัส COIL_NO มีรูปแบบของ Root Coil (-R-, -1-, -2-)
                    c.COIL_NO LIKE '%-R-%' OR c.COIL_NO LIKE '%-1-%' OR c.COIL_NO LIKE '%-2-%'
                    
                    -- เงื่อนไข 2: PRODUCT_REFERENCE ว่างเปล่า หรืออ้างอิงตัวเอง
                    OR c.PRODUCT_REFERENCE IS NULL 
                    OR LTRIM(RTRIM(c.PRODUCT_REFERENCE)) = '' 
                    OR c.PRODUCT_REFERENCE = c.COIL_NO
                    
                    -- เงื่อนไข 3: PRODUCT_REFERENCE เป็นรหัสภายนอกที่ไม่มีอยู่ในคอลัมน์ COIL_NO (เช่น CP25-000842)
                    OR NOT EXISTS (
                        SELECT 1 FROM COILPROD1 p WHERE p.COIL_NO = c.PRODUCT_REFERENCE
                    )
                )
            ";

    $params = [];

    // Filter ช่วงเวลา COIL_STARTDATE
    if (!empty($startDate)) {
        $sql .= " AND CONVERT(VARCHAR(10), c.COIL_OPERATEDATE, 120) >= :start_date";
        $params[':start_date'] = date('Y-m-d', strtotime($startDate));
    }
    if (!empty($endDate)) {
        $sql .= " AND CONVERT(VARCHAR(10), c.COIL_OPERATEDATE, 120) <= :end_date";
        $params[':end_date'] = date('Y-m-d', strtotime($endDate));
    }

    // Filter ตาม Supplier ID
    if (!empty($supplierId)) {
        $sql .= " AND c.CSTMSPPL_ID = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    $sql .= "
            ),
            DownwardTree AS (
                -- แตกกิ่งลงมาหาคอยล์ลูกหลานทั้งหมด
                SELECT 
                    r.Root_Coil,
                    r.Root_Coil AS Child_Coil
                FROM RootCoils r

                UNION ALL

                SELECT 
                    dt.Root_Coil,
                    CAST(child.COIL_NO AS VARCHAR(100)) AS Child_Coil
                FROM DownwardTree dt
                INNER JOIN COILPROD1 child ON dt.Child_Coil = child.PRODUCT_REFERENCE
                WHERE child.PRODUCT_REFERENCE IS NOT NULL 
                  AND LTRIM(RTRIM(child.PRODUCT_REFERENCE)) <> ''
                  AND child.PRODUCT_REFERENCE <> child.COIL_NO
            ),
            ProductWeightSummary AS (
                -- สรุปยอดน้ำหนัก Product เฉพาะสถานะ SH, IN, ST
                SELECT 
                    dt.Root_Coil,
                    SUM(ISNULL(CAST(crsh.CRSH_ACTUALWEIGHT AS FLOAT), 0)) AS TOTAL_PRODUCT_WEIGHT
                FROM DownwardTree dt
                INNER JOIN CRSHPROD1 crsh ON dt.Child_Coil = crsh.COIL_NO
                WHERE crsh.CRSH_STATUS IN ('SH', 'IN', 'ST')
                GROUP BY dt.Root_Coil
            )
            SELECT 
                r.Root_Coil AS COIL_NO,
                DATEDIFF(DAY, r.COIL_STARTDATE, GETDATE()) AS AgedDay,
                ISNULL(r.PRODUCT_REFERENCE, '-') AS PRODUCT_REFERENCE,
                ISNULL(CONVERT(VARCHAR(10), r.COIL_STARTDATE, 120), '-') AS COIL_STARTDATE,
                ISNULL(CONVERT(VARCHAR(10), r.COIL_ENDDATE, 120), '-') AS COIL_ENDDATE,
                ISNULL(r.CSTMSPPL_ID, '-') AS CSTMSPPL_ID,
                ISNULL(r.ALLOY, '-') AS ALLOY,
                ISNULL(r.TEMPER, '-') AS TEMPER,
                ISNULL(r.GRADE, '-') AS GRADE,
                ISNULL(r.MG, '-') AS MG,
                ISNULL(r.SG, '-') AS SG,
                ISNULL(r.THICKNESS, 0) AS THICKNESS,
                ISNULL(r.WIDTH, 0) AS WIDTH,
                ISNULL(r.COIL_ACTUALWEIGHT, 0) AS COIL_ACTUALWEIGHT,
                ISNULL(p.TOTAL_PRODUCT_WEIGHT, 0) AS TOTAL_PRODUCT_WEIGHT,
                CASE 
                    WHEN ISNULL(r.COIL_ACTUALWEIGHT, 0) > 0 
                    THEN ROUND((ISNULL(p.TOTAL_PRODUCT_WEIGHT, 0) / r.COIL_ACTUALWEIGHT) * 100, 2)
                    ELSE 0 
                END AS Recovery_Pct
            FROM RootCoils r
            LEFT JOIN ProductWeightSummary p ON r.Root_Coil = p.Root_Coil
            ORDER BY r.COIL_STARTDATE DESC, r.Root_Coil ASC
            OPTION (MAXRECURSION 100);";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $data], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>