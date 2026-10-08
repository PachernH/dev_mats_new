<?php
// เริ่ม session ก่อน ANY output
session_start();

include 'function_mats.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$bno = !isset($_GET['bno']) ? '' : htmlspecialchars(trim($_GET['bno']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$all_buffer = RT_BufferCoil();
$all_working = RT_CoilWorking();
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
<?php $menu = 'gp7';?>

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
                    <a class="navbar-brand" href="#"> GROUP DATA 7 </a>
                </div>
                    <ul class="nav navbar-nav navbar-right">
                      <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                  <p>
                                      Account
                                      <b class="caret"></b>
                                  </p>
                            </a>
                            <ul class="dropdown-menu">
                              <li><a href="change_password_mats.php" id="cp-btn">Change Password</a></li>
                              <li class="divider"></li>
                              <li><a href="logout.php">Log out</a></li>
                            </ul>
                      </li>
                      <li class="separator hidden-lg"></li>
                  </ul>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        
                    </div>

                    <div class="flex justify-center text-slate-300 py-1">      

                    </div> 

                    <div class="lg:col-span-12">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                            <div class="absolute -top-3 left-4 bg-emerald-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                Master Data : (CONFIGURATION)
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div class="space-y-3 text-center">
                                     <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>


                                    <a href="user_master_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                    <div class="text-orange-600 text-bg font-bold mb-2 uppercase">User Master Data </div>
                                    <div class="flex justify-center">
                                        <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                    </div>
                                    </a>

                                    <a href="change_password_master_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                    <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Change Password Data </div>
                                    <div class="flex justify-center">
                                        <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                    </div>
                                    </a>                                   
 
                                    <a href="function_master_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                    <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Screen Function Master Data </div>
                                    <div class="flex justify-center">
                                        <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                    </div>
                                    </a>                                                                         

                                </div>

                                <div class="space-y-3 text-center">
                                     <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>


                                

                                </div>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>



  
                                </div>
                            </div>

                            <div class="flex justify-center text-slate-300 py-1">                                   
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
</html>