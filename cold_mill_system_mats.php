<?php
// Start session before ANY output
session_start();
// Redirect if not logged in
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include 'dbcon_mats-new.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล PDO

// Sanitize & Validate input
$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');

// Session variables
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func   = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

// ----------------------------------------------------------------------------------
// ดึงข้อมูลจากฐานข้อมูล CDMLMCHN1 (อ้างอิง Logic จาก VB6)
// ----------------------------------------------------------------------------------
$inlet_coils  = array_fill(1, 7, ''); 
$outlet_coils = array_fill(1, 7, ''); 
$mill_data    = null; // ข้อมูล Coil ที่อยู่บน Mill Processing (LOCATION = 'R')

try {
    if (isset($conn)) {
        // SQL Query ดึงข้อมูลจาก CDMLMCHN1 ตารางเดียวตาม VB6
        $sql_mchn = "SELECT * FROM CDMLMCHN1 WITH (NOLOCK)";
        $stmt_mchn = $conn->prepare($sql_mchn);
        $stmt_mchn->execute();
        $mchn_rows = $stmt_mchn->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mchn_rows as $row) {
            $location     = strtoupper(trim($row['LOCATION'] ?? ''));
            $location_row = intval($row['LOCATION_ROW'] ?? 0);
            $coil_no      = trim($row['COIL_NO'] ?? '');

            // 1. LOCATION = 'I' (Inlet Area)
            if ($location === 'I') {
                $slot_index = $location_row + 1; // แปลง 0-based เป็น 1-based
                if ($slot_index >= 1 && $slot_index <= 7) {
                    $inlet_coils[$slot_index] = $coil_no;
                }
            } 
            // 2. LOCATION = 'O' (Outlet Area)
            else if ($location === 'O') {
                $slot_index = $location_row + 1;
                if ($slot_index >= 1 && $slot_index <= 7) {
                    $outlet_coils[$slot_index] = $coil_no;
                }
            }
            // 3. LOCATION = 'R' (Coil on Processing ใน Mill)
            else if ($location === 'R' && !empty($coil_no)) {
                $mill_data = $row;

                // Trim ค่าที่เป็น String ทั้งหมดใน Record
                foreach ($mill_data as $k => $v) {
                    if (is_string($v)) $mill_data[$k] = trim($v);
                }

                // ดึงค่า Tolerance เพิ่มเติมหากไม่มีการกำหนดค่ารายลูกค้า (อ้างอิง VB6 Logic)
                $max_cust = floatval($row['MAX_THICKCUSTOMER'] ?? 0);
                $min_cust = floatval($row['MIN_THICKCUSTOMER'] ?? 0);
                $thick_exit = floatval($row['THICKNESS_EXIT'] ?? 0);

                $max_tol = 0.0;
                $min_tol = 0.0;

                if ($max_cust == 0 && $min_cust == 0) {
                    $sg = trim($row['SURFACE_GRADE'] ?? '');
                    $mg = trim($row['METALLURGICAL_GRADE'] ?? '');
                    $product_type = ($sg === 'SGR' && $mg === 'MG6') ? 'BT' : 'NC';

                    $sql_tcps = "SELECT TOP 1 TOLERANCE_MAX, TOLERANCE_MIN FROM TCPS0301 WITH (NOLOCK)
                                 WHERE PRODUCT_TYPE = ? 
                                   AND THICKNESS_FROM <= ? 
                                   AND THICKNESS_TO >= ?";
                    $stmt_tcps = $conn->prepare($sql_tcps);
                    $stmt_tcps->execute([$product_type, $thick_exit, $thick_exit]);
                    $tcps = $stmt_tcps->fetch(PDO::FETCH_ASSOC);

                    if ($tcps) {
                        $max_tol = $thick_exit + floatval($tcps['TOLERANCE_MAX']);
                        $min_tol = $thick_exit - floatval($tcps['TOLERANCE_MIN']);
                    }
                } else {
                    $max_tol = $thick_exit + $max_cust;
                    $min_tol = $thick_exit - $min_cust;
                }

                $avg_thick = ($max_tol + $min_tol) / 2;

                // เพิ่มค่าที่คำนวณได้เข้าไปใน Array
                $mill_data['MAX_TOLERANCE'] = $max_tol;
                $mill_data['MIN_TOLERANCE'] = $min_tol;
                $mill_data['AVG_THICKNESS'] = $avg_thick;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Query CDMLMCHN1 Failed: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="en">
<head>
    <title>Cold Rolling Mill Coil Control [ ACDMLCTRL1 ]</title>
    <?php include 'include/header.php';?>
    
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <script src="assets/js/tailwindcss.js"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f8fafc; 
            color: #0f172a;
        }
        .main-panel .content { 
            padding: 10px !important; 
            margin: 0 !important;
            width: 100% !important;
        }
        
        .glass-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03);
            border-radius: 12px;
            width: 100%;
        }

        .val-display {
            background-color: #eff6ff;
            color: #1e3a8a;
            font-family: 'Consolas', 'Courier New', monospace;
            font-weight: 900;
            font-size: 2.25rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #bfdbfe;
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.03);
            min-height: 70px;
            padding: 0 16px;
        }

        .label-title {
            color: #1e293b;
            font-weight: 900;
            font-size: 1.65rem;
            line-height: 1.2;
        }

        .section-header-title {
            font-size: 1.85rem;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: 0.05em;
        }

/* Input Coil Box ปรับขนาดตัวอักษรให้อ่านง่าย ชัดเจน และพอดีกับกล่อง ไม่หลุดขอบ */
        .coil-input-box {
            background-color: #ffffff;
            color: #1e3a8a;
            font-family: 'Consolas', 'Courier New', monospace;
            font-weight: 900;
            font-size: 1.35rem; /* ลดขนาดลงมาให้พอดีกับการแสดงรหัสยาว */
            letter-spacing: -0.01em;
            text-align: center;
            border: 3.5px solid #cbd5e1;
            border-radius: 10px;
            width: 100%;
            height: 68px;
            padding: 0 4px;
            overflow: hidden;
            text-overflow: ellipsis; /* หากยาวเกินจะแสดง ... ป้องกันการหลุดขอบ */
            white-space: nowrap;
            transition: all 0.2s;
        }
        .coil-input-box:focus {
            outline: none;
            background-color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
        }

        .remark-area::-webkit-scrollbar { width: 10px; }
        .remark-area::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
        .remark-area::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 8px; }

        .btn-modern { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-modern:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); }
        .btn-modern:active:not(:disabled) { transform: translateY(0); }
        
        .btn-modern:disabled {
            background-color: #e2e8f0 !important;
            color: #94a3b8 !important;
            border-color: #cbd5e1 !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            transform: none !important;
            opacity: 0.6;
        }

        input[type="checkbox"]:disabled {
            cursor: not-allowed !important;
            opacity: 0.3 !important;
            background-color: #e2e8f0 !important;
        }
    </style>
</head>

<body>
<div class="wrapper">
<?php $menu = 'mats';?>

<?php include 'include/'.$folder_func.'/navigation.php'; ?>

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
                    <a class="navbar-brand font-black text-slate-900 text-3xl tracking-tight" href="#">COLD ROLLING MILL COIL CONTROL</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

<!-- COIL DETAIL MODAL POPUP -->
<div id="coilDetailModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 sm:p-6 transition-all">
    <div class="bg-white rounded-3xl shadow-2xl border-4 border-slate-400 w-full max-w-6xl overflow-hidden transform transition-all scale-95 opacity-0 flex flex-col max-h-[92vh]" id="modalContainer">
        
        <div class="bg-slate-900 text-white px-8 py-5 flex items-center justify-between border-b-4 border-slate-700 shrink-0">
            <div class="flex items-center space-x-4">
                <span class="w-6 h-6 bg-emerald-400 rounded-full animate-pulse shadow-lg shadow-emerald-400/50"></span>
                <h3 class="text-3xl sm:text-4xl font-black uppercase tracking-wider">Coil Detail Specification</h3>
            </div>
            <button type="button" onclick="closeCoilModal()" class="text-slate-400 hover:text-white text-6xl font-bold leading-none transition cursor-pointer">&times;</button>
        </div>

        <div class="p-8 space-y-6 bg-slate-100 overflow-y-auto grow">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white border-3 border-slate-300 p-5 rounded-2xl shadow-sm">
                    <span class="text-sm font-black text-slate-500 uppercase tracking-widest block mb-1">Coil No.</span>
                    <div id="modal_coil_no" class="text-4xl sm:text-5xl font-black text-emerald-600 font-mono tracking-tight leading-tight break-all">-</div>
                </div>
                <div class="bg-white border-3 border-slate-300 p-5 rounded-2xl shadow-sm">
                    <span class="text-sm font-black text-slate-500 uppercase tracking-widest block mb-1">Job Process</span>
                    <div id="modal_job_process" class="text-4xl sm:text-5xl font-black text-slate-800 font-mono tracking-tight leading-tight break-all">-</div>
                </div>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="bg-white border-3 border-slate-300 p-5 rounded-2xl shadow-sm col-span-12 md:col-span-5">
                    <span class="text-sm font-black text-slate-500 uppercase tracking-widest block mb-1">Recipe No / Item</span>
                    <div id="modal_recipe" class="text-3xl font-black text-slate-800 font-mono leading-tight break-all">-</div>
                </div>
                <div class="bg-white border-3 border-slate-300 p-5 rounded-2xl shadow-sm col-span-12 md:col-span-4">
                    <span class="text-sm font-black text-slate-500 uppercase tracking-widest block mb-1">Total / Complete Pass</span>
                    <div id="modal_pass" class="text-4xl font-black text-indigo-600 font-mono leading-tight">-</div>
                </div>
                <div class="bg-white border-3 border-slate-300 p-5 rounded-2xl shadow-sm col-span-12 md:col-span-3">
                    <span class="text-sm font-black text-slate-500 uppercase tracking-widest block mb-1">Alloy</span>
                    <div id="modal_alloy" class="text-4xl font-black text-slate-800 font-mono leading-tight">-</div>
                </div>
            </div>

            <div class="bg-white border-3 border-slate-300 p-6 rounded-2xl shadow-sm">
                <span class="text-base font-black text-slate-500 uppercase tracking-widest mb-3 block">Thickness (Original / Current / Final)</span>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-slate-100 border-2 border-slate-300 py-4 px-2 rounded-xl font-mono font-black text-slate-800 text-3xl sm:text-4xl shadow-inner" id="modal_thick_org">0.000</div>
                    <div class="bg-amber-100 border-2 border-amber-300 py-4 px-2 rounded-xl font-mono font-black text-amber-700 text-3xl sm:text-4xl shadow-inner" id="modal_thick_curr">0.000</div>
                    <div class="bg-emerald-100 border-2 border-emerald-300 py-4 px-2 rounded-xl font-mono font-black text-emerald-700 text-3xl sm:text-4xl shadow-inner" id="modal_thick_final">0.000</div>
                </div>
            </div>

            <div class="space-y-6 pt-2">
                <div class="bg-amber-50/90 border-3 border-amber-300 p-6 rounded-2xl shadow-sm">
                    <span class="text-xl font-black text-amber-900 uppercase tracking-widest block mb-3 flex items-center space-x-2">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Coil Remark</span>
                    </span>
                    <div id="modal_coil_remark" class="text-2xl sm:text-3xl font-black text-slate-900 font-mono min-h-[90px] max-h-[200px] overflow-y-auto whitespace-pre-line leading-relaxed tracking-wide bg-white p-5 rounded-xl border-2 border-amber-300 shadow-inner">-</div>
                </div>

                <div class="bg-blue-50/90 border-3 border-blue-300 p-6 rounded-2xl shadow-sm">
                    <span class="text-xl font-black text-blue-900 uppercase tracking-widest block mb-3 flex items-center space-x-2">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Job Remark</span>
                    </span>
                    <div id="modal_job_remark" class="text-2xl sm:text-3xl font-black text-slate-900 font-mono min-h-[90px] max-h-[200px] overflow-y-auto whitespace-pre-line leading-relaxed tracking-wide bg-white p-5 rounded-xl border-2 border-blue-300 shadow-inner">-</div>
                </div>
            </div>
        </div>

        <div class="bg-slate-200 px-8 py-5 border-t-2 border-slate-300 text-right shrink-0">
            <button type="button" onclick="closeCoilModal()" class="bg-slate-900 hover:bg-black text-white font-black text-2xl px-12 py-4 rounded-2xl transition shadow-xl uppercase tracking-wider cursor-pointer">CLOSE</button>
        </div>
    </div>
</div>


<!-- NEW COIL INPUT MODAL POPUP -->
<div id="newCoilModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center hidden p-4 transition-all">
    <div class="bg-white rounded-3xl shadow-2xl border-4 border-emerald-500 w-full max-w-2xl overflow-hidden transform transition-all scale-95 opacity-0 flex flex-col" id="newCoilModalContainer">
        
        <!-- Header -->
        <div class="bg-emerald-700 text-white px-8 py-6 flex items-center justify-between border-b-4 border-emerald-800">
            <div class="flex items-center space-x-4">
                <span class="w-5 h-5 bg-emerald-300 rounded-full animate-pulse shadow-lg"></span>
                <h3 class="text-3xl font-black uppercase tracking-wider" id="newCoilModalTitle">ENTER NEW COIL NO</h3>
            </div>
            <button type="button" onclick="closeNewCoilModal()" class="text-emerald-200 hover:text-white text-5xl font-bold leading-none transition cursor-pointer">&times;</button>
        </div>

        <!-- Body -->
        <div class="p-8 space-y-6 bg-slate-50">
            <div class="space-y-3">
                <label class="block text-slate-800 text-2xl font-black uppercase tracking-wide">Coil No.</label>
                <input type="text" 
                       id="modal_input_coil_no" 
                       placeholder="กรอกหมายเลข COIL..." 
                       class="w-full bg-white border-4 border-slate-300 text-slate-900 font-mono font-black text-4xl p-5 rounded-2xl text-center focus:outline-none focus:border-emerald-600 focus:ring-4 focus:ring-emerald-200 uppercase shadow-inner tracking-widest"
                       oninput="this.value = this.value.toUpperCase();"
                       onkeydown="if(event.key==='Enter'){ confirmNewCoil(); }">
            </div>
            <input type="hidden" id="modal_target_area">
            <input type="hidden" id="modal_target_slot">
        </div>

        <!-- Footer -->
        <div class="bg-slate-100 px-8 py-5 border-t-2 border-slate-200 flex justify-end space-x-4">
            <button type="button" onclick="closeNewCoilModal()" class="bg-slate-500 hover:bg-slate-600 text-white font-black text-2xl px-8 py-4 rounded-2xl transition uppercase cursor-pointer shadow-md">CANCEL</button>
            <button type="button" onclick="confirmNewCoil()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-2xl px-12 py-4 rounded-2xl transition shadow-xl uppercase cursor-pointer">OK</button>
        </div>
    </div>
</div>


        <div class="content">
            <div class="w-full space-y-4">

<!-- SECTION 1: COIL ON PROCESSING -->
                <div class="glass-card p-6">
                    <div class="flex items-center space-x-3 border-b-2 border-slate-200 pb-3 mb-6">
                        <span class="w-6 h-6 bg-red-500 rounded-full animate-pulse"></span>
                        <h3 class="section-header-title uppercase">Coil on Processing</h3>
                    </div>

                    <!-- Specs & Recipe Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 mb-6">
                        <!-- Left Panel: Specifications -->
                        <div class="lg:col-span-6 space-y-4">
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Coil No.</label>
                                <div class="w-3/5 val-display text-emerald-600 text-3xl" id="disp_coil_no">-</div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Job Process</label>
                                <div class="w-3/5 val-display text-3xl" id="disp_job_process">-</div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Alloy - Temper</label>
                                <div class="w-3/5 grid grid-cols-2 gap-2">
                                    <div class="val-display text-3xl" id="disp_alloy">-</div>
                                    <div class="val-display text-3xl" id="disp_temper">-</div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">SG - MG</label>
                                <div class="w-3/5 grid grid-cols-2 gap-2">
                                    <div class="val-display text-3xl" id="disp_sg">-</div>
                                    <div class="val-display text-3xl" id="disp_mg">-</div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Thickness (Orig - Curr - Final)</label>
                                <div class="w-3/5 grid grid-cols-3 gap-2">
                                    <div class="val-display text-3xl" id="disp_thick_orig">0.000</div>
                                    <div class="val-display text-3xl text-amber-600" id="disp_thick_curr">0.000</div>
                                    <div class="val-display text-3xl text-emerald-600" id="disp_thick_final">0.000</div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Width</label>
                                <div class="w-3/5 val-display text-3xl" id="disp_width">0.000</div>
                            </div>
                            <div class="flex items-center justify-between space-x-4">
                                <label class="w-2/5 label-title">Coil Weight(Kg)</label>
                                <div class="w-3/5 val-display text-3xl" id="disp_weight">0.000</div>
                            </div>
                        </div>

                        <!-- Right Panel: Recipe & Pass Info -->
                        <div class="lg:col-span-6 space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Recipe No</label>
                                    <div class="w-3/5 val-display text-emerald-600 text-3xl" id="disp_recipe_no">-</div>
                                </div>
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Item (Rolling Pass)</label>
                                    <div class="w-3/5 val-display text-3xl" id="disp_item">0</div>
                                </div>                                                                
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Total Pass</label>
                                    <div class="w-3/5 val-display text-3xl" id="disp_total_pass">0</div>
                                </div>
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Number of Pass Complete</label>
                                    <div class="w-3/5 val-display text-emerald-600 text-3xl" id="disp_pass_complete">0</div>
                                </div>
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Thickness (Entry - Exit - Tol.)</label>
                                    <div class="w-3/5 grid grid-cols-3 gap-2">
                                        <div class="val-display text-3xl" id="disp_entry">0.000</div>
                                        <div class="val-display text-3xl" id="disp_exit">0.000</div>
                                        <div class="val-display text-3xl text-amber-600" id="disp_tol">0.000</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between space-x-4">
                                    <label class="w-2/5 label-title">Temper After Mill</label>
                                    <div class="w-3/5 val-display text-3xl" id="disp_temper_after">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Remark -->
                    <div class="w-full space-y-5 pt-4 border-t-2 border-slate-100">
                        <div class="flex flex-col space-y-2">
                            <label class="label-title">Coil Remark</label>
                            <textarea class="w-full bg-slate-50 border-2 border-slate-300 text-slate-800 p-4 text-2xl rounded-xl h-28 focus:outline-none resize-y font-bold remark-area leading-relaxed tracking-wide" id="disp_coil_remark" readonly></textarea>
                        </div>

                        <div class="flex flex-col space-y-2">
                            <label class="label-title">Job Remark</label>
                            <textarea class="w-full bg-slate-50 border-2 border-slate-300 text-slate-800 p-4 text-2xl rounded-xl h-28 focus:outline-none resize-y font-bold remark-area leading-relaxed tracking-wide" id="disp_job_remark" readonly></textarea>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: COIL MOVEMENT CONTROL -->
                <div class="glass-card p-6">
                    <div class="flex items-center justify-between border-b-2 border-slate-200 pb-3 mb-6">
                        <div class="flex items-center space-x-3">
                            <span class="w-6 h-6 bg-indigo-600 rounded-full"></span>
                            <h3 class="section-header-title uppercase">Coil Movement Control</h3>
                        </div>
                    </div>

                    <!-- Top Limits Display -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-xl">
                            <div class="text-xl font-black text-slate-800 mb-2 uppercase">Max Thick. Customer</div>
                            <div class="val-display text-3xl" id="disp_cust_max">0.000</div>
                        </div>
                        <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-xl">
                            <div class="text-xl font-black text-slate-800 mb-2 uppercase">Min Thick. Customer</div>
                            <div class="val-display text-3xl" id="disp_cust_min">0.000</div>
                        </div>
                        <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-xl">
                            <div class="text-xl font-black text-slate-800 mb-2 uppercase">Max Thick / Min Thick</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="val-display text-3xl" id="disp_max_thick">0.000</div>
                                <div class="val-display text-3xl" id="disp_min_thick">0.000</div>
                            </div>
                        </div>
                        <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-xl">
                            <div class="text-xl font-black text-slate-800 mb-2 uppercase">Avg. Thickness</div>
                            <div class="val-display text-3xl" id="disp_avg_thick">0.000</div>
                        </div>
                    </div>

                    <!-- MACHINE VISUAL GRAPHIC -->
                    <div class="bg-slate-800 rounded-2xl p-6 mb-6 text-white border-2 border-slate-700 shadow-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
                            
                            <!-- Coiler -->
                            <div class="bg-slate-900 border-2 border-slate-700 p-6 rounded-xl text-center flex flex-col items-center space-y-4">
                                <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/50 text-2xl px-8 py-2 font-black rounded-full uppercase tracking-wider">Coiler</span>
                                <div class="w-28 h-28 bg-slate-700 border-4 border-slate-500 rounded-full flex items-center justify-center shadow-lg my-1">
                                    <div class="w-12 h-12 border-2 border-slate-400 rounded-full bg-slate-900"></div>
                                </div>
                                <div class="flex space-x-3 w-full pt-2">
                                    <button type="button" onclick="returnCoilToMill()" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-lg py-3.5 rounded-xl font-black btn-modern uppercase">Return</button>
                                    <button type="button" onclick="exitCoilFromMill()" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white text-lg py-3.5 rounded-xl font-black btn-modern uppercase">Exit</button>
                                </div>
                            </div>

                            <!-- Mill Center Status -->
                            <div class="text-center space-y-3">
                                <div class="bg-slate-900 border-2 border-slate-700 p-4 rounded-xl inline-flex flex-col items-center justify-center min-w-[220px] shadow-inner">
                                    <div class="h-48 w-full flex items-center justify-center bg-slate-800/80 rounded-lg p-1.5 border border-slate-700 mb-2">
                                        <img src="assets/img/coil.png" 
                                            id="img_mill_status" 
                                            class="h-full object-contain filter opacity-30 transition-all duration-300" 
                                            alt="Mill Coil Status">
                                    </div>
                                    <div class="text-xs font-bold text-slate-400 tracking-widest uppercase mb-0.5">MILL STATUS</div>
                                    <div class="text-2xl font-black text-amber-400 tracking-wider font-mono uppercase" id="disp_mill_status">READY</div>
                                </div>

                                <div>
                                    <button type="button" 
                                            onclick="moveToInlet()" 
                                            class="inline-flex items-center space-x-3 bg-emerald-500 hover:bg-emerald-400 active:scale-95 text-slate-950 font-black text-2xl px-8 py-3 rounded-full shadow-lg uppercase tracking-wider transition-all duration-150 cursor-pointer border-none outline-none">
                                        <span>Inlet</span>
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Decoiler -->
                            <div class="bg-slate-900 border-2 border-slate-700 p-6 rounded-xl text-center flex flex-col items-center space-y-4">
                                <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/50 text-2xl px-8 py-2 font-black rounded-full uppercase tracking-wider">Decoiler</span>
                                <div class="w-28 h-28 bg-slate-700 border-4 border-slate-500 rounded-full flex items-center justify-center shadow-lg my-1">
                                    <div class="w-12 h-12 border-2 border-slate-400 rounded-full bg-slate-900"></div>
                                </div>
                                <div class="flex space-x-3 w-full pt-2">
                                    <button type="button" id="btn_load_coil" onclick="loadCoilToMill()" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-lg py-3.5 rounded-xl font-black btn-modern uppercase">Load</button>
                                    <button type="button" id="btn_unload_coil" onclick="unloadCoilFromMill()" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white text-lg py-3.5 rounded-xl font-black btn-modern uppercase">Unload</button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- SLOTS CONTROLS -->
                    <form id="coilControlForm" method="POST" onsubmit="return false;">
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            
                            <!-- OUTLET AREA -->
                            <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-2xl">
                                <div class="bg-slate-800 text-white px-4 py-3.5 rounded-xl font-black text-2xl mb-5 text-center tracking-widest uppercase">
                                    Outlet Area
                                </div>
                                <div class="grid grid-cols-7 gap-2 text-center">
                                    <?php for($i=1; $i<=7; $i++): ?>
                                        <?php $outlet_val = htmlspecialchars($outlet_coils[$i], ENT_QUOTES, 'UTF-8'); ?>
                                        <div class="bg-white border-2 border-slate-200 p-2.5 rounded-xl flex flex-col justify-between shadow-sm min-h-[390px]">
                                            <div class="h-48 w-full flex items-center justify-center bg-slate-100 rounded-xl p-1.5 border-2 border-slate-200">
                                                <img src="assets/img/coil.png" 
                                                    id="img_outlet_<?php echo $i; ?>" 
                                                    onclick="showCoilDetail('outlet', <?php echo $i; ?>)"
                                                    class="h-full object-contain filter <?php echo !empty($outlet_val) ? 'opacity-100 cursor-pointer hover:scale-105' : 'opacity-30'; ?> transition-all duration-300" 
                                                    alt="Coil" 
                                                    onerror="this.src='https://cdn-icons-png.flaticon.com/512/8621/8621805.png';">
                                            </div>
                                            <div class="font-black text-2xl text-slate-800 border-b-2 pb-1 my-1.5 bg-slate-100 rounded-lg"><?php echo $i; ?></div>
                                            <input type="text" 
                                                id="outlet_coil_<?php echo $i; ?>" 
                                                value="<?php echo $outlet_val; ?>" 
                                                placeholder="-" 
                                                class="coil-input-box mb-2.5"
                                                onkeydown="handleCoilEnter(event, 'outlet', <?php echo $i; ?>)"
                                                oninput="toggleCoilImage('outlet', <?php echo $i; ?>)">
                                            <button type="button" 
                                                    id="btn_eject_outlet_<?php echo $i; ?>" 
                                                    onclick="takeOut('outlet', <?php echo $i; ?>)" 
                                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-base py-3.5 rounded-xl font-black transition mb-2.5 btn-modern uppercase tracking-wider shadow">EJECT <?php echo $i; ?></button>
                                            <div class="my-1.5 flex items-center justify-center">
                                                <input type="checkbox" name="outlet_select[<?php echo $i; ?>]" value="1" id="chk_outlet_<?php echo $i; ?>" class="w-8 h-8 rounded text-indigo-600 border-slate-400 cursor-pointer" checked>
                                            </div>
                                            <button type="button" 
                                                    id="btn_new_outlet_<?php echo $i; ?>" 
                                                    onclick="createNew('outlet', <?php echo $i; ?>)" 
                                                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-base py-3.5 rounded-xl font-black transition btn-modern uppercase tracking-wider shadow">NEW</button>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- INLET AREA -->
                            <div class="bg-slate-50 border-2 border-slate-200 p-4 rounded-2xl">
                                <div class="bg-emerald-700 text-white px-4 py-3.5 rounded-xl font-black text-2xl mb-5 text-center tracking-widest uppercase">
                                    Inlet Area
                                </div>
                                <div class="grid grid-cols-7 gap-2 text-center">
                                    <?php for($i=1; $i<=7; $i++): ?>
                                        <?php $inlet_val = htmlspecialchars($inlet_coils[$i], ENT_QUOTES, 'UTF-8'); ?>
                                        <div class="bg-white border-2 border-slate-200 p-2.5 rounded-xl flex flex-col justify-between shadow-sm min-h-[390px]">
                                            <div class="h-48 w-full flex items-center justify-center bg-slate-100 rounded-xl p-1.5 border-2 border-slate-200">
                                                <img src="assets/img/coil.png" 
                                                    id="img_inlet_<?php echo $i; ?>" 
                                                    onclick="showCoilDetail('inlet', <?php echo $i; ?>)"
                                                    class="h-full object-contain filter <?php echo !empty($inlet_val) ? 'opacity-100 cursor-pointer hover:scale-105' : 'opacity-30'; ?> transition-all duration-300" 
                                                    alt="Coil">
                                            </div>
                                            <div class="font-black text-2xl text-slate-800 border-b-2 pb-1 my-1.5 bg-slate-100 rounded-lg"><?php echo $i; ?></div>
                                            <input type="text" 
                                                id="inlet_coil_<?php echo $i; ?>" 
                                                value="<?php echo $inlet_val; ?>" 
                                                placeholder="-" 
                                                class="coil-input-box mb-2.5"
                                                onkeydown="handleCoilEnter(event, 'inlet', <?php echo $i; ?>)"
                                                oninput="toggleCoilImage('inlet', <?php echo $i; ?>)">
                                            <button type="button" 
                                                    id="btn_eject_inlet_<?php echo $i; ?>" 
                                                    onclick="takeOut('inlet', <?php echo $i; ?>)" 
                                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-base py-3.5 rounded-xl font-black transition mb-2.5 btn-modern uppercase tracking-wider shadow">EJECT <?php echo $i; ?></button>
                                            <div class="my-1.5 flex items-center justify-center">
                                                <input type="checkbox" name="inlet_select[<?php echo $i; ?>]" value="1" id="chk_inlet_<?php echo $i; ?>" class="w-8 h-8 rounded text-indigo-600 border-slate-400 cursor-pointer" checked>
                                            </div>
                                            <button type="button" 
                                                    id="btn_new_inlet_<?php echo $i; ?>" 
                                                    onclick="createNew('inlet', <?php echo $i; ?>)" 
                                                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-base py-3.5 rounded-xl font-black transition btn-modern uppercase tracking-wider shadow">NEW</button>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                        </div>
                    </form>

                </div>
            </div>
        </div>

<?php include 'include/content-footer.php';?>

    </div>
</div>

<script>
    // ดึงข้อมูล Coil on Processing (LOCATION = 'R') จาก PHP
    const millCoilData = <?php echo json_encode($mill_data); ?>;

    document.addEventListener("DOMContentLoaded", function() {
        checkInitialSlotStatus();

        // เมื่อเปิดหน้าเว็บ ถ้าพบ Coil ที่มี LOCATION = 'R' ให้แสดงผลทันที
        if (millCoilData && millCoilData.COIL_NO) {
            renderCoilProcessingData(millCoilData);
        }
    });

// แสดงผลข้อมูลลงในหน้าจอ (ตามฟิลด์จาก VB6)
    function renderCoilProcessingData(d) {
        document.getElementById('disp_coil_no').innerText = d.COIL_NO || '-';
        document.getElementById('disp_job_process').innerText = d.JOB_PROCESS || '-';
        document.getElementById('disp_alloy').innerText = d.ALLOY || '-';
        document.getElementById('disp_temper').innerText = d.TEMPER_ORIGINAL || d.TEMPER || '-';
        document.getElementById('disp_sg').innerText = d.SURFACE_GRADE || '-';
        document.getElementById('disp_mg').innerText = d.METALLURGICAL_GRADE || '-';
        
        document.getElementById('disp_thick_orig').innerText = parseFloat(d.THICKNESS_ORIGINAL || 0).toFixed(3);
        document.getElementById('disp_thick_curr').innerText = parseFloat(d.THICKNESS || 0).toFixed(3);
        document.getElementById('disp_thick_final').innerText = parseFloat(d.THICKNESS_FINAL || 0).toFixed(3);
        
        document.getElementById('disp_width').innerText = parseFloat(d.WIDTH || 0).toFixed(3);
        document.getElementById('disp_weight').innerText = parseFloat(d.CDML_ACTUALWEIGHT || 0).toFixed(0);
        document.getElementById('disp_recipe_no').innerText = d.RECIPE_NO || '-';
        document.getElementById('disp_item').innerText = d.RECIPE_ITEM ?? '0';
        document.getElementById('disp_total_pass').innerText = d.TOTAL_PASS ?? '0';
        document.getElementById('disp_pass_complete').innerText = d.CURRENT_PASS ?? '0';
        
        document.getElementById('disp_entry').innerText = parseFloat(d.THICKNESS_ENTRY || 0).toFixed(3);
        document.getElementById('disp_exit').innerText = parseFloat(d.THICKNESS_EXIT || 0).toFixed(3);
        document.getElementById('disp_tol').innerText = parseFloat(d.THICKNESS_TELORANCE || 0).toFixed(0);
        document.getElementById('disp_temper_after').innerText = d.TEMPER_FINISH || '-';

        document.getElementById('disp_coil_remark').value = d.COIL_REMARK || '';
        document.getElementById('disp_job_remark').value = d.JOB_REMARK || '';

        // =========================================================================
        // [จุดที่อัปเดต] แสดงผลส่วน Limits Control (เมื่อกด LOAD หรือเปิดหน้าเว็บ)
        // =========================================================================
        if (document.getElementById('disp_cust_max')) document.getElementById('disp_cust_max').innerText = parseFloat(d.MAX_THICKCUSTOMER || 0).toFixed(3);
        if (document.getElementById('disp_cust_min')) document.getElementById('disp_cust_min').innerText = parseFloat(d.MIN_THICKCUSTOMER || 0).toFixed(3);
        if (document.getElementById('disp_max_thick')) document.getElementById('disp_max_thick').innerText = parseFloat(d.MAX_TOLERANCE || 0).toFixed(3);
        if (document.getElementById('disp_min_thick')) document.getElementById('disp_min_thick').innerText = parseFloat(d.MIN_TOLERANCE || 0).toFixed(3);
        if (document.getElementById('disp_avg_thick')) document.getElementById('disp_avg_thick').innerText = parseFloat(d.AVG_THICKNESS || 0).toFixed(3);

        // อัปเดตรูปภาพ Coil และข้อความหมายเลข Coil ตรงตำแหน่ง MILL STATUS
        let millStatusText = document.getElementById('disp_mill_status');
        let millStatusImg  = document.getElementById('img_mill_status');

        if (millStatusText) {
            millStatusText.innerText = d.COIL_NO || 'READY';
            millStatusText.classList.remove('text-amber-400');
            millStatusText.classList.add('text-emerald-400');
        }

        if (millStatusImg) {
            millStatusImg.classList.remove('opacity-30');
            millStatusImg.classList.add('opacity-100');
        }
    }

    function checkInitialSlotStatus() {
        let hasInletCoil = checkAnyInletHasCoil();
        for (let i = 1; i <= 7; i++) {
            initSlotState('inlet', i);
            initSlotState('outlet', i, hasInletCoil);
        }
    }

    function checkAnyInletHasCoil() {
        for (let i = 1; i <= 7; i++) {
            let val = document.getElementById(`inlet_coil_${i}`).value.trim();
            if (val !== "" && val !== "-") {
                return true;
            }
        }
        return false;
    }

    function initSlotState(type, slotIndex, hasInletData = false) {
        let inputElem = document.getElementById(`${type}_coil_${slotIndex}`);
        let inputVal = inputElem.value.trim();
        let hasData = (inputVal !== "" && inputVal !== "-");

        if (type === 'inlet') {
            setSlotState('inlet', slotIndex, hasData);
        } else if (type === 'outlet') {
            setOutletInputState(slotIndex, hasInletData);
            setSlotState('outlet', slotIndex, hasData);
        }
    }

    function setOutletInputState(slotIndex, canEnable) {
        let outletInput = document.getElementById(`outlet_coil_${slotIndex}`);
        if (!outletInput) return;

        if (canEnable) {
            outletInput.disabled = false;
            outletInput.readOnly = true;
        } else {
            outletInput.disabled = true;
            outletInput.readOnly = false;
        }
    }

    function handleCoilEnter(event, areaType, slotIndex) {
        if (event.key === 'Enter') {
            event.preventDefault();
            let coilNo = event.target.value.trim();

            if (coilNo === "") {
                alert("กรุณากรอกหมายเลข Coil No.");
                setSlotState(areaType, slotIndex, false);
                updateOutletStateAfterInletChange();
                return;
            }

            saveCoilToDatabase(areaType, slotIndex, coilNo);
        }
    }

    function saveCoilToDatabase(areaType, slotIndex, coilNo) {
        let formData = new FormData();
        formData.append('area_type', areaType);
        formData.append('slot_index', slotIndex);
        formData.append('coil_no', coilNo);

        fetch('model/create_in_coldmill_mats.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                setSlotState(areaType, slotIndex, true);
                updateOutletStateAfterInletChange();
            } else {
                alert(`ERROR: ${data.message}`);
                document.getElementById(`${areaType}_coil_${slotIndex}`).value = '';
                setSlotState(areaType, slotIndex, false);
                updateOutletStateAfterInletChange();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
            setSlotState(areaType, slotIndex, false);
        });
    }

    function updateOutletStateAfterInletChange() {
        let hasInletCoil = checkAnyInletHasCoil();
        for (let i = 1; i <= 7; i++) {
            setOutletInputState(i, hasInletCoil);
        }
    }

function setSlotState(type, slotIndex, isActive) {
    let imgElem  = document.getElementById(`img_${type}_${slotIndex}`);
    let btnEject = document.getElementById(`btn_eject_${type}_${slotIndex}`);
    let btnNew   = document.getElementById(`btn_new_${type}_${slotIndex}`);
    let chkElem  = document.getElementById(`chk_${type}_${slotIndex}`);

    // อัปเดตรูปภาพ Coil
    if (imgElem) {
        if (isActive) {
            imgElem.classList.remove('opacity-30');
            imgElem.classList.add('opacity-100');
        } else {
            imgElem.classList.remove('opacity-100');
            imgElem.classList.add('opacity-30');
        }
    }

    // จัดการสถานะปุ่ม EJECT
    if (btnEject) btnEject.disabled = !isActive;
    
    // จัดการสถานะปุ่ม NEW
    if (btnNew) {
        if (type === 'outlet') {
            btnNew.disabled = true; // ฝั่ง Outlet ปิดการใช้งานปุ่ม NEW ทุกกรณี
        } else {
            btnNew.disabled = isActive; // ฝั่ง Inlet กด NEW ได้เฉพาะช่องที่ยังไม่มีข้อมูล
        }
    }

    // -------------------------------------------------------------
    // [จุดที่แก้ไข] กำหนดให้ Checkbox ติ๊กถูกไว้เสมอเป็นค่าเริ่มต้น (Default Checked)
    // -------------------------------------------------------------
    if (chkElem) {
        chkElem.disabled = !isActive; // ปิดไม่ให้กดติ๊กถ้าช่องว่าง
        chkElem.checked  = true;      // ติ๊กถูกไว้เสมอไม่ว่าช่องจะมีข้อมูลอยู่แล้ว หรือเพิ่งเพิ่มเข้ามาใหม่
    }
}

// ส่วนของการ EJECT COIL  ออกจากระบบ 

function takeOut(type, slotIndex) {
    let inputElem = document.getElementById(`${type}_coil_${slotIndex}`);
    if (!inputElem) return;

    let coilNo = inputElem.value.trim();
    if (coilNo === "" || coilNo === "-") return;

    // 1. ถามยืนยันขั้นแรกว่าต้องการนำ Coil ออกหรือไม่
    if (!confirm(`คุณต้องการนำ Coil [${coilNo}] ออกจาก ${type.toUpperCase()} Slot ${slotIndex} ใช่หรือไม่?`)) {
        return;
    }

    // 2. ดึงข้อมูลเพื่อเช็ค COUNT_PROCESS และ PASS จาก Backend ก่อน
    let formDataCheck = new FormData();
    formDataCheck.append('coil_no', coilNo);

    fetch('model/eject_in_coldmill_mats.php', {
        method: 'POST',
        body: formDataCheck
    })
    .then(response => response.json())
    .then(resCheck => {
        let isNewCoil = 0;
        
        // หากพบว่ารีดครบ Pass แล้ว (CURRENT_PASS >= TOTAL_PASS) -> บังคับสร้าง Coil M
        if (resCheck.is_pass_completed) {
            alert(`Coil [${coilNo}] รีดครบตามจำนวน Pass แล้ว (CURRENT_PASS = TOTAL_PASS)\nระบบจะทำการ EJECT และสร้าง Coil M ให้อัตโนมัติ`);
            isNewCoil = 1;
        } 
        // หากยังรีดไม่ครบ Pass แต่มีการเริ่มรีดแล้ว (COUNT_PROCESS >= 1) -> ให้ผู้ใช้เลือกระหว่างสร้าง/ไม่สร้าง Coil M
        else if (resCheck.require_choice) {
            let isFinished = confirm(
                `Coil [${coilNo}] ผ่านกระบวนการรีดมาแล้ว (COUNT_PROCESS >= 1)\n\n` +
                `กรุณาเลือกประเภทการ EJECT:\n` +
                `[ OK ] = ต้องการสร้าง Coil M ใหม่\n` +
                `[ Cancel ] = ไม่ต้องการสร้าง Coil M`
            );
            isNewCoil = isFinished ? 1 : 0;
        }

        // 3. ส่งคำสั่ง EJECT ทำงานจริง
        let formData = new FormData();
        formData.append('coil_no', coilNo);
        formData.append('slot_index', slotIndex);
        formData.append('area_type', type === 'outlet' ? 'O' : 'I');
        formData.append('is_new_coil', isNewCoil);
        formData.append('confirm_eject', '1');

        return fetch('model/eject_in_coldmill_mats.php', {
            method: 'POST',
            body: formData
        });
    })
    .then(response => response ? response.json() : null)
    .then(data => {
        if (data && data.status === 'success') {
            alert(data.message);
            inputElem.value = "";
            setSlotState(type, slotIndex, false);
            
            if (type === 'inlet') {
                updateOutletStateAfterInletChange();
            }

            // สั่งพิมพ์ Label เป็นลำดับสุดท้าย เฉพาะกรณีที่มีการสร้าง Coil M ใหม่
            if (data.product_no && data.product_no !== "") {
                let printUrl = `print_coil_product_label_mats.php?coilno=${encodeURIComponent(data.product_no)}`;
                window.open(printUrl, '_blank', 'width=1000,height=800,scrollbars=yes,resizable=yes');
            }

        } else if (data) {
            alert(`ERROR: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}

// ตัวแปรเก็บตำแหน่งเป้าหมาย
let currentNewArea = '';
let currentNewSlot = null;

// 1. ฟังก์ชันเมื่อกดปุ่ม NEW
function createNew(type, slotIndex) {
    // ป้องกันไม่ให้ฝั่ง Outlet Area กด NEW ได้ทุกกรณี
    if (type === 'outlet') {
        return;
    }

    let inputElem = document.getElementById(`${type}_coil_${slotIndex}`);
    let inputVal = inputElem ? inputElem.value.trim() : '';

    // ถ้าช่องใน Inlet Area มีข้อมูลอยู่แล้ว จะไม่ยอมให้กดสร้างใหม่
    if (inputVal !== '' && inputVal !== '-') {
        alert(`ช่อง ${slotIndex} มีข้อมูล Coil อยู่แล้ว ไม่สามารถสร้างใหม่ได้`);
        return;
    }

    currentNewArea = type;
    currentNewSlot = slotIndex;

    document.getElementById('modal_target_area').value = type;
    document.getElementById('modal_target_slot').value = slotIndex;
    
    document.getElementById('newCoilModalTitle').innerText = `ENTER COIL NO (SLOT ${slotIndex})`;
    
    let inputModal = document.getElementById('modal_input_coil_no');
    inputModal.value = '';

    openNewCoilModal();
}

// 2. ฟังก์ชันเปิด Modal
function openNewCoilModal() {
    let modal = document.getElementById('newCoilModal');
    let container = document.getElementById('newCoilModalContainer');
    modal.classList.remove('hidden');
    setTimeout(() => {
        container.classList.remove('scale-95', 'opacity-0');
        container.classList.add('scale-100', 'opacity-100');
        let inputModal = document.getElementById('modal_input_coil_no');
        inputModal.focus();
    }, 10);
}

// 3. ฟังก์ชันปิด Modal
function closeNewCoilModal() {
    let modal = document.getElementById('newCoilModal');
    let container = document.getElementById('newCoilModalContainer');
    container.classList.remove('scale-100', 'opacity-100');
    container.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 200);
}

// 4. ฟังก์ชันย้อนกลับเพื่อบันทึกค่าลงช่อง coil-input-box เมื่อกด OK หรือ Enter ใน Modal
function confirmNewCoil() {
    let coilNoInput = document.getElementById('modal_input_coil_no').value.trim().toUpperCase();

    if (coilNoInput === '') {
        alert('กรุณากรอกหมายเลข Coil No.');
        document.getElementById('modal_input_coil_no').focus();
        return;
    }

    let areaType = document.getElementById('modal_target_area').value;
    let slotIndex = document.getElementById('modal_target_slot').value;

    // นำค่าที่ได้ใส่ลงในช่อง input ในหน้าจอหลัก
    let targetInput = document.getElementById(`${areaType}_coil_${slotIndex}`);
    if (targetInput) {
        targetInput.value = coilNoInput;
    }

    closeNewCoilModal();

    // ทำการบันทึกลงฐานข้อมูลทันทีผ่านฟังก์ชันเดิมที่มีอยู่
    saveCoilToDatabase(areaType, slotIndex, coilNoInput);
}




    function showCoilDetail(type, slotIndex) {
        let coilInput = document.getElementById(`${type}_coil_${slotIndex}`);
        if (!coilInput) return;

        let coilNo = coilInput.value.trim();
        if (coilNo === "" || coilNo === "-") return;

        let formData = new FormData();
        formData.append('coil_no', coilNo);
        formData.append('area_type', type === 'outlet' ? 'O' : 'I');

        fetch('model/get_coil_coldmill_detail_mats.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                let d = res.data;

                document.getElementById('modal_coil_no').innerText = d.COIL_NO || '-';
                document.getElementById('modal_job_process').innerText = d.JOB_PROCESS || '-';
                document.getElementById('modal_recipe').innerText = `${d.RECIPE_NO || '-'} / ${d.RECIPE_ITEM ?? '-'}`;
                document.getElementById('modal_pass').innerText = `${d.TOTAL_PASS ?? '-'} / ${d.CURRENT_PASS ?? '-'}`;
                document.getElementById('modal_alloy').innerText = d.ALLOY || '-';
                
                document.getElementById('modal_thick_org').innerText = parseFloat(d.THICKNESS_ORIGINAL || 0).toFixed(3);
                document.getElementById('modal_thick_curr').innerText = parseFloat(d.THICKNESS || 0).toFixed(3);
                document.getElementById('modal_thick_final').innerText = parseFloat(d.THICKNESS_FINAL || 0).toFixed(3);

                document.getElementById('modal_coil_remark').innerText = d.COIL_REMARK || '-';
                document.getElementById('modal_job_remark').innerText = d.JOB_REMARK || '-';

                openCoilModal();
            } else {
                alert(`ERROR: ${res.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('เกิดข้อผิดพลาดในการดึงข้อมูล Coil Detail');
        });
    }

    function openCoilModal() {
        let modal = document.getElementById('coilDetailModal');
        let container = document.getElementById('modalContainer');
        modal.classList.remove('hidden');
        setTimeout(() => {
            container.classList.remove('scale-95', 'opacity-0');
            container.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeCoilModal() {
        let modal = document.getElementById('coilDetailModal');
        let container = document.getElementById('modalContainer');
        container.classList.remove('scale-100', 'opacity-100');
        container.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }    

// ฟังก์ชัน Load Coil จาก Inlet Area เข้าสู่ Mill Processing
function loadCoilToMill() {
    // 1. ค้นหา Checkbox ฝั่ง Inlet Area ที่ถูกติ๊กไว้ทั้งหมด
    let checkedSlots = [];
    for (let i = 1; i <= 7; i++) {
        let chk = document.getElementById(`chk_inlet_${i}`);
        if (chk && chk.checked) {
            checkedSlots.push(i);
        }
    }

    // 2. กำหนดเงื่อนไข slotsToSend:
    // - ติ๊กบางช่อง (1-6 ช่อง): ส่งสล็อตที่ติ๊กไปประมวลผลก่อน
    // - ติ๊กทุกช่อง (7 ช่อง) หรือไม่ได้ติ๊กเลย: ส่ง null เพื่อให้ Backend ทำตามลำดับมาตรฐาน (1 ไป 7)
    let slotsToSend = null;
    if (checkedSlots.length > 0 && checkedSlots.length < 7) {
        slotsToSend = checkedSlots;
    }

    if (!confirm("คุณต้องการ LOAD Coil จาก Inlet Area เข้าสู่ Mill Processing ใช่หรือไม่?")) {
        return;
    }

    let formData = new FormData();
    if (slotsToSend) {
        formData.append('selected_slots', JSON.stringify(slotsToSend));
    }

    fetch('model/load_coil_coldmill_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);
            renderCoilProcessingData(res.data);

            // เคลียร์ค่าช่องที่ถูก LOAD ออกไป
            let inletInput = document.getElementById(`inlet_coil_${res.slot_index}`);
            if (inletInput) {
                inletInput.value = "";
                setSlotState('inlet', res.slot_index, false);
            }
            
            if (typeof updateOutletStateAfterInletChange === 'function') {
                updateOutletStateAfterInletChange();
            }
        } else {
            alert(`ERROR: ${res.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}

// ฟังก์ชันเรียก Unload Coil ออกจาก Mill Processing
function unloadCoilFromMill() {
    let currentCoilNo = document.getElementById('disp_coil_no').innerText.trim();

    if (currentCoilNo === '-' || currentCoilNo === '') {
        alert("ไม่มี Coil อยู่ในกระบวนการ Coil on Processing");
        return;
    }

    if (!confirm(`คุณต้องการ UNLOAD Coil [${currentCoilNo}] กลับไปยังตำแหน่ง Inlet Area ใช่หรือไม่?`)) {
        return;
    }

    fetch('model/unload_coil_coldmill_mats.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);

            // 1. เคลียร์ข้อมูลในส่วน Coil on Processing & Movement Control
            clearCoilProcessingData();

            // 2. คืนค่า Coil กลับไปยังช่อง Inlet Area ที่ระบุ
            let targetSlot = res.slot_index;
            let inletInput = document.getElementById(`inlet_coil_${targetSlot}`);
            if (inletInput) {
                inletInput.value = res.coil_no;
                setSlotState('inlet', targetSlot, true);
            }

            // 3. อัปเดตสถานะของช่อง Outlet ควบคู่กัน
            if (typeof updateOutletStateAfterInletChange === 'function') {
                updateOutletStateAfterInletChange();
            }

        } else {
            alert(`ERROR: ${res.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}

// ฟังก์ชันล้างค่าแสดงผลหน้าจอในส่วน Coil on Processing ให้เป็นค่าว่าง/ค่าเริ่มต้น
function clearCoilProcessingData() {
    document.getElementById('disp_coil_no').innerText = '-';
    document.getElementById('disp_job_process').innerText = '-';
    document.getElementById('disp_alloy').innerText = '-';
    document.getElementById('disp_temper').innerText = '-';
    document.getElementById('disp_sg').innerText = '-';
    document.getElementById('disp_mg').innerText = '-';
    
    document.getElementById('disp_thick_orig').innerText = '0.000';
    document.getElementById('disp_thick_curr').innerText = '0.000';
    document.getElementById('disp_thick_final').innerText = '0.000';
    
    document.getElementById('disp_width').innerText = '0.000';
    document.getElementById('disp_weight').innerText = '0.000';
    document.getElementById('disp_recipe_no').innerText = '-';
    document.getElementById('disp_item').innerText = '0';
    document.getElementById('disp_total_pass').innerText = '0';
    document.getElementById('disp_pass_complete').innerText = '0';
    
    document.getElementById('disp_entry').innerText = '0.000';
    document.getElementById('disp_exit').innerText = '0.000';
    document.getElementById('disp_tol').innerText = '0.000';
    document.getElementById('disp_temper_after').innerText = '-';

    document.getElementById('disp_coil_remark').value = '';
    document.getElementById('disp_job_remark').value = '';

    // ล้างค่า Limit Controls
    if (document.getElementById('disp_cust_max')) document.getElementById('disp_cust_max').innerText = '0.000';
    if (document.getElementById('disp_cust_min')) document.getElementById('disp_cust_min').innerText = '0.000';
    if (document.getElementById('disp_max_thick')) document.getElementById('disp_max_thick').innerText = '0.000';
    if (document.getElementById('disp_min_thick')) document.getElementById('disp_min_thick').innerText = '0.000';
    if (document.getElementById('disp_avg_thick')) document.getElementById('disp_avg_thick').innerText = '0.000';

    // รีเซ็ตการแสดงผลรูปภาพและสถานะตรง MILL STATUS
    let millStatusText = document.getElementById('disp_mill_status');
    let millStatusImg  = document.getElementById('img_mill_status');

    if (millStatusText) {
        millStatusText.innerText = 'READY';
        millStatusText.classList.remove('text-emerald-400');
        millStatusText.classList.add('text-amber-400');
    }

    if (millStatusImg) {
        millStatusImg.classList.remove('opacity-100');
        millStatusImg.classList.add('opacity-30');
    }
}

// ฟังก์ชันย้าย Coil จาก Mill Processing ไปยัง Outlet Area
function exitCoilFromMill() {
    let currentCoilNo = document.getElementById('disp_coil_no').innerText.trim();

    if (currentCoilNo === '-' || currentCoilNo === '') {
        alert("ไม่มี Coil อยู่ในกระบวนการ Coil on Processing");
        return;
    }

    if (!confirm(`คุณต้องการ EXIT Coil [${currentCoilNo}] ไปยัง Outlet Area ใช่หรือไม่?`)) {
        return;
    }

    fetch('model/exit_coil_coldmill_mats.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);

            // 1. เคลียร์ข้อมูลในส่วน Coil on Processing
            if (typeof clearCoilProcessingData === 'function') {
                clearCoilProcessingData();
            }

            // 2. ย้ายข้อมูล Coil ไปยังช่อง Outlet Area ที่สอดคล้อง
            let targetSlot = res.slot_index;
            let outletInput = document.getElementById(`outlet_coil_${targetSlot}`);
            if (outletInput) {
                outletInput.value = res.coil_no;
                setSlotState('outlet', targetSlot, true);
            }

        } else {
            alert(`ERROR: ${res.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}

// ฟังก์ชันเรียก RETURN Coil จาก Outlet Area กลับเข้าสู่ Mill Processing
function returnCoilToMill() {
    // 1. ตรวจสอบ Checkbox ฝั่ง Outlet Area ที่ถูกติ๊กไว้
    let checkedSlots = [];
    for (let i = 1; i <= 7; i++) {
        let chk = document.getElementById(`chk_outlet_${i}`);
        if (chk && chk.checked) {
            checkedSlots.push(i);
        }
    }

    // 2. กำหนดเงื่อนไข slotsToSend: 
    // - ถ้าไม่มีการติ๊กเลย หรือ ติ๊กครบทุกอัน (7 ช่อง) ให้ส่งเป็น null หรือ array ว่าง เพื่อให้ Backend ทำตามลำดับปกติ
    // - ถ้าติ๊กบางอัน (1-6 ช่อง) ให้ส่งเฉพาะช่องที่ถูกติ๊ก
    let slotsToSend = null;
    if (checkedSlots.length > 0 && checkedSlots.length < 7) {
        slotsToSend = checkedSlots;
    }

    if (!confirm("คุณต้องการ RETURN Coil จาก Outlet Area กลับเข้าสู่ Coil on Processing ใช่หรือไม่?")) {
        return;
    }

    let formData = new FormData();
    if (slotsToSend) {
        formData.append('selected_slots', JSON.stringify(slotsToSend));
    }

    fetch('model/return_coil_coldmill_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);

            // 1. นำข้อมูลไป Render ในส่วน Coil on Processing
            if (typeof renderCoilProcessingData === 'function') {
                renderCoilProcessingData(res.data);
            }

            // 2. เคลียร์ช่อง Outlet Area ที่ถูกดึงกลับ
            let targetSlot = res.slot_index;
            let outletInput = document.getElementById(`outlet_coil_${targetSlot}`);
            if (outletInput) {
                outletInput.value = "";
                setSlotState('outlet', targetSlot, false);
            }

        } else {
            alert(`ERROR: ${res.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}

// ฟังก์ชันเรียกย้าย Coil จาก Outlet Area ไปยัง Inlet Area เมื่อกดปุ่ม INLET >>
function moveToInlet() {
    // 1. ตรวจสอบ Checkbox ฝั่ง Outlet Area ที่ถูกติ๊กไว้
    let checkedSlots = [];
    for (let i = 1; i <= 7; i++) {
        let chk = document.getElementById(`chk_outlet_${i}`);
        if (chk && chk.checked) {
            checkedSlots.push(i);
        }
    }

    // กำหนดเงื่อนไข: หากติ๊ก 1-6 ช่อง จะส่งสล็อตเฉพาะที่ติ๊ก
    // หากไม่ติ๊กเลย หรือ ติ๊กครบ 7 ช่อง ให้ส่ง null เพื่อให้ Backend ค้นหาจากช่องหลังสุดมาหน้าสุด (7 ไป 1)
    let slotsToSend = null;
    if (checkedSlots.length > 0 && checkedSlots.length < 7) {
        slotsToSend = checkedSlots;
    }

    if (!confirm("คุณต้องการย้าย Coil จาก Outlet Area ไปยัง Inlet Area ใช่หรือไม่?")) {
        return;
    }

    let formData = new FormData();
    if (slotsToSend) {
        formData.append('selected_slots', JSON.stringify(slotsToSend));
    }

    fetch('model/inlet_coil_coldmill_mats.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(res => {
        if (res.status === 'success') {
            alert(res.message);

            let slotIndex = res.from_slot; // Slot index (1-7) ที่ย้าย
            let coilNo = res.coil_no;

            // 1. ลบข้อมูลและรีเซ็ตสถานะฝั่ง Outlet ช่องที่ถูกย้ายออก
            let outletInput = document.getElementById(`outlet_coil_${slotIndex}`);
            if (outletInput) {
                outletInput.value = "";
                setSlotState('outlet', slotIndex, false);
            }

            // 2. ใส่ข้อมูล Coil และอัปเดตสถานะฝั่ง Inlet ในตำแหน่งสล็อตเดียวกัน
            let inletInput = document.getElementById(`inlet_coil_${slotIndex}`);
            if (inletInput) {
                inletInput.value = coilNo;
                setSlotState('inlet', slotIndex, true);
            }

            // 3. อัปเดตการเปิด/ปิดช่อง Input ของ Outlet ตามสถานะ Inlet ใหม่
            if (typeof updateOutletStateAfterInletChange === 'function') {
                updateOutletStateAfterInletChange();
            }

        } else {
            alert(`ERROR: ${res.message}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
}
</script>

</body>
<?php include 'include/footer.php';?>
</html>