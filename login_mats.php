<?php
ob_start();
session_start(); // ต้องเปิด session_start() เพื่อใช้งาน $_SESSION

include('dbcon_mats-new.php');

if(isset($_POST['username']) && isset($_POST['pw'])){
    $username     = $_POST['username'];
    $pw           = $_POST['pw'];
    $func         = $_POST['func'] ?? '';
    $processGroup = $_POST['process_group'] ?? '';
    $line         = $_POST['machine_line'] ?? '';

    $sql = "SELECT USER_ID,USER_PASSWORD,FIRST_NAME,LAST_NAME,USER_POSITION ,USER_DEPARTMENT,USER_SECTION,MENU_GROUP FROM USERMSTR1 
    WHERE USER_ID= :user and USER_PASSWORD = :pass";        

    try {
        $stmt = $conn->prepare($sql);
        // Binding ค่าจากตัวแปรเข้าไปใน Query
        $stmt->execute(['user' => $username, 'pass' => $pw]);
        
        // ตรวจสอบว่าพบข้อมูลหรือไม่
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            
            // เก็บค่าลง Session
            $_SESSION['ID']            = strtoupper($row['USER_ID']);
            $_SESSION['POSITION']      = $row['USER_POSITION'];
            $_SESSION['SECTION']       = $row['USER_SECTION'];
            $_SESSION['DEPARTMENT']    = $row['USER_DEPARTMENT'];
            $_SESSION['NAME']          = $row['FIRST_NAME']." ".$row['LAST_NAME'];
            $_SESSION['PASSWORD']      = $row['USER_PASSWORD'];
            $_SESSION['GROUP']         = strtoupper($row['MENU_GROUP']);
            $_SESSION['FUNC']          = strtolower($func);
            $_SESSION['PROCESS_GROUP'] = strtoupper($processGroup);
            $_SESSION['LINE']          = strtoupper($line);

            switch (strtolower($func)) {
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

            $folder_name = strtolower($func);
            $target_dir = "include/$folder_name/" . strtolower($row['MENU_GROUP']);
            $target_file = $target_dir . '/navigation.php';

            $source_file = 'include/navigation_temp.php';

            if (!file_exists($target_file)) {
                 copy($source_file, $target_file);
            }
            
            header('location:dashboard_mats.php?func=' . $func);
                
        } else {
            header('location:index.php?id=97&error=invalid_credentials');
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
    
    // ปิดการเชื่อมต่อ (PDO)
    $conn = null;
}
?>