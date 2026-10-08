<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    
    <script src="assets/js/tailwindcss.js"></script>

    <style>
        /* บังคับไม่ให้ตัว Dashboard ทะลุขอบ และปรับ Font ให้เข้ากับ Template */
        body {  font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;}
        .flow-card { @apply transition-all duration-200 hover:shadow-md hover:-translate-y-1; cursor: pointer; }
        /* ปรับแต่ง Scrollbar สำหรับส่วน Production ที่อาจจะยาวข้ามจอ */
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        
        /* แก้ไข Navbar ของ Template ไม่ให้ทับซ้อนกับ Tailwind */
        .main-panel .content { padding: 15px 15px !important; }
        
        /* ทำให้ปุ่มที่คลิกได้มี cursor pointer */
        .clickable { cursor: pointer; transition: all 0.2s ease; }
        .clickable:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'T2';?>

<?php   
    include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#"></a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
<?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>

</html>
