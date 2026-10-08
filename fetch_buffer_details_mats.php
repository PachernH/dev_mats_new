<?php
session_start();
include("dbcon_mats-new.php");

$process = isset($_POST['process']) ? trim($_POST['process']) : '';

if ($process === '') {
    echo "<tr><td colspan='19' class='text-center text-warning'>ไม่ระบุรหัสกระบวนการ (Process Code)</td></tr>";
    exit;
}

try {
    // ใช้ Subquery LTRIM/RTRIM เพื่อลบช่องว่างส่วนเกินที่ติดมาจาก Database 100%
    $sql = "
    WITH PreparedData AS (
        SELECT 
            COIL_NO,
            PRODUCT_REFERENCE,
            JOB_PROCESS,
            ALLOY,
            TEMPER,
            GRADE,
            SURFACE_GRADE,
            THICKNESS,
            ACTUAL_WIDTH,
            COIL_NEXTPROCESS,
            CSTMSPPL_ID,
            COIL_TYPE,
            USE_FORPROCESS,
            LINE_PROCESS,
            COIL_WORKPROCESS,
            COIL_BALANCEWEIGHT,
            COIL_REMARK,
            COIL_STATUS,
            COIL_STARTTIME,
            CASE 
                WHEN LTRIM(RTRIM(ISNULL(COIL_NEXTPROCESS, ''))) = 'WS' 
                THEN LTRIM(RTRIM(ISNULL(USE_FORPROCESS, '')))
                
                WHEN LTRIM(RTRIM(ISNULL(COIL_NEXTPROCESS, ''))) = 'BA' 
                AND LTRIM(RTRIM(ISNULL(COIL_WORKINPROCESS, ''))) = 'BA'
                THEN LTRIM(RTRIM(ISNULL(USE_FORPROCESS, '')))

                ELSE LTRIM(RTRIM(ISNULL(COIL_NEXTPROCESS, '')))
            END AS Raw_Process
        FROM COILPROD1
        WHERE COIL_STATUS IN ('AC', 'OP')
    ),
    MappedData AS (
        SELECT *,
            CASE 
                WHEN Raw_Process = '' OR Raw_Process = ' ' THEN 'ST'
                WHEN Raw_Process = 'CTL' THEN 'CL'
                WHEN Raw_Process = 'PKC' THEN 'PK'
                WHEN Raw_Process = 'CUT' THEN 'CT'
                ELSE Raw_Process
            END AS Target_Process
        FROM PreparedData
    )
    SELECT *
    FROM MappedData
    WHERE Target_Process = :process
    ORDER BY COIL_STARTTIME DESC;";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':process', $process, PDO::PARAM_STR);
    $stmt->execute();
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $icount = 1;
    
    if (count($rows) > 0) {
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($icount, ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['COIL_NO'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['PRODUCT_REFERENCE'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['JOB_PROCESS'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['ALLOY'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['TEMPER'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['GRADE'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['SURFACE_GRADE'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars(number_format(floatval($row['THICKNESS'] ?? 0), 2), ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['ACTUAL_WIDTH'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            
            echo "<td>" . htmlspecialchars($row['CSTMSPPL_ID'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['COIL_TYPE'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            
            echo "<td>" . htmlspecialchars($row['LINE_PROCESS'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['COIL_WORKPROCESS'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['COIL_NEXTPROCESS'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row['USE_FORPROCESS'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";

            echo "<td class='text-end'>" . number_format(floatval($row['COIL_BALANCEWEIGHT'] ?? 0), 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['COIL_REMARK'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td class='text-center'><span class='label label-info'>" . htmlspecialchars($row['COIL_STATUS'] ?? '', ENT_QUOTES, 'UTF-8') . "</span></td>";
            echo "</tr>";
            $icount++;
        }
    } else {
        echo "<tr><td colspan='19' class='text-center text-muted'>No coil data was found in this process.</td></tr>";
    }

} catch (PDOException $e) {
    echo "<tr><td colspan='19' class='text-center text-danger'>Query Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</td></tr>";
}
?>