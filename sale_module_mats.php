<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$coil_no = isset($_GET['coilno']) ? htmlspecialchars(trim($_GET['coilno']), ENT_QUOTES, 'UTF-8') : '';

include 'function_mats.php'; // ไฟล์เชื่อมต่อ DB ($conn) และ Helper Functions[cite: 1]

include 'dbcon_mats-new.php';

// ดึงค่าและตรวจสอบข้อมูลนำเข้า (Sanitize & Validate)
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning[cite: 1]
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

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
<?php $menu = 'A1';?>

<?php   
    include 'include/'.$folder_func.'/navigation.php';
?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#"> ✏️ Sale Marketing Module</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            
            <!-- Content Area -->
                    <div class="lg:col-span-4 space-y-4">
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 relative">
                            <div class="absolute -top-3 left-4 bg-blue-600 text-white px-3 py-0.5 rounded-full text-[14px] font-bold uppercase tracking-wider">
                                Module
                            </div>
                            &nbsp;
                            <div class="flex flex-col gap-3 mt-2">
                                <a href="customer_order_mats.php?func=<?php echo $folder_func; ?>&type=sale" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Customer Order (Sale Order)</div>
                                        <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                    </div>
                                </a>

                                <div class="flex justify-center text-slate-300 py-1">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </div>
                                
                                <a href="maintain_cust_id_saleorder_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Maintain Customer ID of Customer Order (Sale Order)</div>
                                        <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                    </div>
                                </a>
                                
                                <div class="flex justify-center text-slate-300 py-1">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </div>
                                
                                <a href="maintain_month_cal_price_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Maintain Monthly Calculate Pricing</div>
                                        <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                    </div>
                                </a>

                                <div class="flex justify-center text-slate-300 py-1">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                </div>
                                
                                <a href="maintain_base_cost_mats.php?func=<?php echo $folder_func; ?>&type=job" class="flow-card p-3 bg-blue-50 border border-blue-100 rounded-lg flex items-center gap-3 no-underline hover:no-underline">
                                    <div class="w-8 h-8 bg-blue-500 rounded flex items-center justify-center text-white shadow-sm"></div>
                                    <div>
                                        <div class="text-bg font-bold text-blue-900">Maintain Base Cost and Preminum</div>
                                        <div class="text-[10px] text-blue-600 uppercase">Function</div>
                                    </div>
                                </a>
                                &nbsp;
                            </div>
                        </div>

                    </div>        
                    &nbsp;    

        </div>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>
</html>