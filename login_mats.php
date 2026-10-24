<?php
ob_start();
session_start();

include('dbcon_mats-new.php');

if(isset($_POST['username']) && isset($_POST['pw'])){
    $username     = trim($_POST['username']);
    $pw           = trim($_POST['pw']);
    $func         = strtolower(trim($_POST['func'] ?? ''));
    $processGroup = $_POST['process_group'] ?? '';
    $line         = $_POST['machine_line'] ?? '';

    // ตรวจสอบ Username, Password และคัดกรองพนักงานที่ลาออก (MENU_GROUP <> 'resign')
    $sql = "SELECT USER_ID, USER_PASSWORD, FIRST_NAME, LAST_NAME, USER_POSITION, USER_DEPARTMENT, USER_SECTION, MENU_GROUP 
            FROM [MATS-NEWII].dbo.USERMSTR1 
            WHERE USER_ID = :user 
              AND USER_PASSWORD = :pass 
              AND MENU_GROUP <> 'resign'";        

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute(['user' => $username, 'pass' => $pw]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_dept = strtoupper(trim($row['USER_DEPARTMENT'] ?? ''));

            // =========================================================================
            // ตรวจสอบสิทธิ์การเข้าใช้งาน Production (PD):
            // หากเลือก func = 'pd' ต้องมี USER_DEPARTMENT เป็น 'PD', 'MG', 'IT', 'HK', 'TC' เท่านั้น
            // =========================================================================
            $allowed_pd_departments = ['PD', 'MG', 'IT', 'HK', 'TC'];
            if ($func === 'pd' && !in_array($user_dept, $allowed_pd_departments)) {
                header('location:index.php?id=97&error=dept_access_denied');
                exit();
            }

            // จัดเก็บ Session เป็นตัวพิมพ์ใหญ่เพื่อความแม่นยำในการเช็กสิทธิ์
            $_SESSION['ID']            = strtoupper($row['USER_ID']);
            $_SESSION['POSITION']      = strtoupper(trim($row['USER_POSITION'] ?? ''));
            $_SESSION['DEPARTMENT']    = $user_dept;
            $_SESSION['SECTION']       = $row['USER_SECTION'];
            $_SESSION['NAME']          = $row['FIRST_NAME']." ".$row['LAST_NAME'];
            $_SESSION['PASSWORD']      = $row['USER_PASSWORD'];
            $_SESSION['GROUP']         = strtoupper($row['MENU_GROUP']);
            $_SESSION['FUNC']          = $func;
            $_SESSION['PROCESS_GROUP'] = strtoupper($processGroup);
            $_SESSION['LINE']          = strtoupper($line);

            switch ($func) {
                case "pd":
                    $_SESSION['NAMEFUNC'] = "Production (PD)";
                    break; 
                case "qac":
                    $_SESSION['NAMEFUNC'] = "Quality Assurance (QA&QC)";
                    break;
                case "wh":
                    $_SESSION['NAMEFUNC'] = "Warehouse (WH&FG)";
                    break;
                case "st":
                    $_SESSION['NAMEFUNC'] = "Store (STORE)";
                    break;    
                case "bi":
                    $_SESSION['NAMEFUNC'] = "Business Intelligence (BI)";
                    break;    
                case "sale":
                    $_SESSION['NAMEFUNC'] = "Sales (Sale)";
                    break;     
                case "root":
                    $_SESSION['NAMEFUNC'] = "Administrator (Admin)";
                    break;
                default:
                    $_SESSION['NAMEFUNC'] = "Not found data";
                    break; 
            }

            $folder_name = $func;
            $target_dir = "include/$folder_name/" . strtolower($row['MENU_GROUP']);
            $target_file = $target_dir . '/navigation.php';
            $source_file = 'include/navigation_temp.php';

            if (!file_exists($target_file)) {
                 copy($source_file, $target_file);
            }
            
            header('location:dashboard_mats.php?func=' . $func);
            exit();
                
        } else {
            header('location:index.php?id=97&error=invalid_credentials');
            exit();
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
    
    $conn = null;
}
?>