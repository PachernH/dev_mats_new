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
<?php $menu = 'A4';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

<div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#"> ✏️ Production Module</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="content">    
            <div class="w-full mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    
                                <div class="lg:col-span-4 space-y-4">
                                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                                        <div class="absolute -top-3 left-4 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                            MODULE
                                        </div>
                                        
                                        <div class="flex flex-col gap-3 mt-2">
                                            <a href="furnance_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Furanace Charging Material</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="cold_mill_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Select Coil Product for Cold Rolling Mill Process + Other Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="batch_swiss_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Select Coil Product for Batch Annealing Process or Swiss Slitter Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="coil_production_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Casting Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="cold_mill_coil_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Cold Rolling Mill Coil Data</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="swiss_slitter_process_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Swiss Slitter Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="coil_packing_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Coil Packing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>

                                            <a href="combine_coil_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Combine Coil Product</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>

                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Blanking Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Annealing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Input Batch Annealing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Output Batch Annealing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="split_process_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Coil Split at Cold Mill</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                        </div>
                                    </div>
                                </div>

                                <div class="lg:col-span-4 space-y-4">
                                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                                        <div class="absolute -top-3 left-4 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                          MODULE  
                                        </div>
                                        
                                        <div class="flex flex-col gap-3 mt-2">
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Shearing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">12 Pending Tasks</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Punch Center Hole Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Transfer Product to Pallet</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Cut to length Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="drop_product_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Drop Product</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Stretcher Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Cut Sheet Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">A Press Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">K Press Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">XY Circle Shear Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Circle of Sheet Packing Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>     

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Mould Setup Status</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                        </div>
                                    </div>
                                </div>
                    
                                <div class="lg:col-span-4 space-y-4">
                                    <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                                        <div class="absolute -top-3 left-4 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                           MODULE 
                                        </div>
                                        
                                        <div class="flex flex-col gap-3 mt-2">
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Scrap from Produce</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">12 Pending Tasks</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Combine Wrap Product</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Wrap Product</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Transter Raw Material to Product</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Product Re-Size</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="split_pallet_process_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Product Split Pallet</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Product Combine Pallet</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Blanking, A Press, K Press or Cut to Length Data</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Circle or Sheet Work Process</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Batch Annealing Number</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="maintain_coil_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Coil Information</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>     
                                            
                                            <div class="flex justify-center text-slate-300 py-1">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                            </div>
                                            
                                            <a href="template_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                                <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                                <div>
                                                    <div class="text-bg font-bold text-blue-900">Maintain Job Order Data of Production</div>
                                                    <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                                </div>
                                            </a>

                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-center text-slate-300 py-1">                                   
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