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

<style type="text/tailwindcss">
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
<?php $menu = 'mats';?>

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
                    <?php if ($folder_func=='pd') { ?>
                    <a class="navbar-brand" href="#">USER : <?php echo($iduser_func) ?> (<?php echo($line_func) ?>)</a>
                    <?php }else{ ?>
                    <a class="navbar-brand" href="#">USER : <?php echo($iduser_func) ?> </a>
                    <?php }?>
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
                    
                    <div class="lg:col-span-4 space-y-4">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                            <div class="absolute -top-3 left-4 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                Sales & Planning
                            </div>
                            
                            <div class="flex flex-col gap-3 mt-2">
                                <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Sale Order</div>
                                        <div class="text-[10px] text-blue-600 uppercase">12 Pending Tasks</div>
                                    </div>
                                </a>

                                <div class="flex justify-center text-slate-300 py-1">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </div>
                                
                                <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Job Order</div>
                                        <div class="text-[10px] text-blue-600 uppercase">12 Pending Tasks</div>
                                    </div>
                                </a>
                                
                                <div class="flex justify-center text-slate-300 py-1">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </div>
                                
                                <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Plan Order</div>
                                        <div class="text-[10px] text-blue-600 uppercase">Production Planning and Sale Planning</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                            <h3 class="text-slate-400 text-[14px] font-bold uppercase tracking-wider mb-3">Price</h3>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="p-2 bg-emerald-50 text-emerald-700 rounded-md text-center text-bg font-medium hover:bg-emerald-100 transition-colors border border-emerald-100 no-underline hover:no-underline">Product Price</a>
                                <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="p-2 bg-emerald-50 text-emerald-700 rounded-md text-center text-bg font-medium hover:bg-emerald-100 transition-colors border border-emerald-100 no-underline hover:no-underline">Product Specification</a>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-8">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative h-full">
                            <div class="absolute -top-3 left-4 bg-orange-500 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                Logistics & Warehouse
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">Input</h4>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Receive Material</div>
                                    </a>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-white border border-slate-200 rounded-lg text-bg text-slate-700 font-medium no-underline hover:no-underline">
                                        Quality Inspection
                                    </a>
                                </div>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">Processing</h4>
                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                            <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Inventory Stock</div>
                                            <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Coil</span>
                                            </div>
                                        </a>
                                        
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                            <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Inventory Stock</div>
                                            <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Sheet</span>
                                            </div>
                                        </a>
                                    </div>
                                </div>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">Output</h4>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-slate-900 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Booking</div>
                                    </a>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-white border border-slate-200 rounded-lg text-bg text-slate-700 font-medium no-underline hover:no-underline">
                                        Generate Invoice
                                    </a>
                                </div>
                            </div>
                            
                            <div class="flex justify-center text-slate-300 py-1">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-2">
                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>
                                </div>

                                <div class="space-y-3 text-center">
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable">
                                        <div class="flex justify-around gap-1">
                                            <span class="px-2 py-1 bg-white rounded text-[14px] shadow-sm font-semibold border border-orange-100 flex-1">Return Production</span>
                                        </div>
                                    </a>
                                </div>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center text-slate-300 py-1">                                   
                    </div> 

                    <div class="lg:col-span-12">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                            <div class="absolute -top-3 left-4 bg-emerald-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                Production Line
                            </div>

                            <div class="grid grid-cols-3 gap-4 mt-2">

                                <?php /*****  Line 1  *****/ ?>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>
                                    <a href="furnance_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Furanace Charging Material</div>
                                    </a> 
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="coil_production_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Casting Process</div>
                                    </a>
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="coil_production_inspec_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Coil Inspection</div>
                                    </a>
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="cold_mill_system_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Cold Rolling Mill</div>
                                    </a> 
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Inventory Coil</div>
                                    </a>                                    
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="sheet_production_inspec_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Circle or Sheet Inspection</div>
                                    </a>
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Packing</div>
                                    </a>    
                                    <div class="flex justify-center text-slate-300 py-1">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                    </div>
                                    <a href="template_mats.php?func=<?php echo $folder_func; ?>" class="flow-card block p-4 bg-indigo-600 text-white rounded-xl shadow-sm no-underline hover:no-underline">
                                        <div class="text-bg font-bold leading-tight">Maintain System</div>
                                    </a>                                                                        
                                </div>

                                <?php /*****  Line 2  *****/ ?>

                                <div class="space-y-3 text-center">
                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Flash annealing</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>     

                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Batch annealing</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Blanking</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>   

                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Circle shear</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">XY-5</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                        
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Cut to Length</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Stretcher</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                        
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">A Press</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">K Press</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                        
                                        <a href="swiss_slitter_process_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Swiss slitter</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">CUT SHEET</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                        
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">SHEARING</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=coil" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">PUNCH HOLD</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                        
                                        <a href="split_process_mats.php?func=<?php echo $folder_func; ?>&type=sheet" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                        <div class="text-orange-600 text-bg font-bold mb-2 uppercase">SPLIT</div>
                                        <div class="flex justify-center">
                                            <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Input</span>
                                        </div>
                                        </a>
                                    </div>

                                </div>

                                <?php /*****  Line 3  *****/ ?>

                                <div class="space-y-3 text-center">

                                    <h4 class="text-[14px] font-bold text-slate-400 uppercase">&nbsp;</h4>

                                    <a href="coil_buffer_mats.php?func=<?php echo $folder_func; ?>" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Buffer Coil Caster Data</div>
                                        <div class="text-[10px] text-blue-600 uppercase"><b><?php echo $all_buffer ?> Coil</b></div>
                                    </div>
                                    </a>

                                    <div class="flex justify-center text-slate-300 py-1">
                                        
                                    </div>

                                    <a href="coil_working_mats.php?func=<?php echo $folder_func; ?>" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Coil Working Data</div>
                                        <div class="text-[10px] text-blue-600 uppercase"><b><?php echo $all_working ?> Coil</b></div>
                                    </div>
                                    </a>

                                    <div class="flex justify-center text-slate-300 py-1">
                                        
                                    </div>

                                    <a href="coil_traceability_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Traceability Data</div>
                                        <div class="text-[10px] text-blue-600 uppercase">&nbsp;</div>
                                    </div>
                                    </a>

                                    <div class="flex justify-center text-slate-300 py-1">
                                        
                                    </div>

                                    <a href="recovery_process_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Recovery Report Data</div>
                                        <div class="text-[10px] text-blue-600 uppercase">&nbsp;</div>
                                    </div>
                                    </a>

                                    <div class="flex justify-center text-slate-300 py-1">
                                        
                                    </div>

                                </div>

                            </div>

                            <div class="flex justify-center text-slate-300 py-1">   
                                                            
                            </div>

                            <div class="space-y-3 text-center">

                                <h4 class="text-[14px] font-bold text-slate-400 uppercase">Print Product Label</h4>
                                <div class="p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl">
                                    <div class="flex items-center gap-3 mt-4 overflow-x-auto pb-4 custom-scrollbar">
                                        
                                        <div class="grid grid-cols-2 gap-2">
                                            <a href="circle_sheet_product_label_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                                <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Circle or Sheet Product</div>
                                                <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Label</span>
                                            </div>
                                            </a>
                                            
                                            <a href="coil_product_label_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                                <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Coil Product</div>
                                                <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Label</span>
                                            </div>
                                            </a>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <a href="scrap_product_label_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                                <div class="text-orange-600 text-bg font-bold mb-2 uppercase">&nbsp;&nbsp;&nbsp; Scrap Product &nbsp;&nbsp;&nbsp;</div>
                                                <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Label</span>
                                            </div>
                                            </a>

                                            <a href="ticket_label_mats.php?func=<?php echo $folder_func; ?>" class="block p-4 bg-orange-50 border-2 border-dashed border-orange-200 rounded-xl no-underline hover:no-underline clickable transition-all hover:bg-orange-100">
                                                <div class="text-orange-600 text-bg font-bold mb-2 uppercase">Packing Coil (Product Ticket)</div>
                                                <div class="flex justify-center">
                                                <span class="px-3 py-1.5 bg-white rounded-lg text-[14px] shadow-sm font-semibold border border-orange-200 w-full">Label</span>
                                            </div>
                                            </a>
                
                                        </div>

                                    </div>
                                </div>    

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