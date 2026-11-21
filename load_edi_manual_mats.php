<?php
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
include 'dbcon_mats-new.php';

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars($_GET['d'], ENT_QUOTES, 'UTF-8');
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="th">
<head>
    <title>Meyer Aluminium - Manual Excel EDI Upload</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', 'Tahoma', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .main-container { padding: 24px 30px; max-width: 1750px; margin: 0 auto; }
        
        /* Stepper Header */
        .stepper-wrapper { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 18px 28px; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .step-item { display: flex; align-items: center; gap: 10px; color: #94a3b8; font-weight: 600; font-size: 13.5px; }
        .step-item.active { color: #0f172a; }
        .step-item.done { color: #059669; }
        .step-circle { width: 30px; height: 30px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13.5px; border: 2px solid #cbd5e1; transition: all 0.3s; }
        .step-item.active .step-circle { background: #0f766e; color: #ffffff; border-color: #0f766e; box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.14); }
        .step-item.done .step-circle { background: #059669; color: #ffffff; border-color: #059669; }
        .step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 14px; }
        .step-line.done { background: #059669; }

        /* Card Layout */
        .modern-card { background: #ffffff; border-radius: 16px; padding: 26px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04); margin-bottom: 24px; }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }

        /* Upload Grid */
        .upload-grid { display: grid; grid-template-columns: 290px 1fr; gap: 24px; align-items: stretch; }
        @media (max-width: 900px) { .upload-grid { grid-template-columns: 1fr; } }

        .date-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .date-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        .input-date-modern { width: 100%; height: 42px; padding: 0 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 15px; font-weight: 600; color: #0f172a; background: #ffffff; }

        /* Drag & Drop Zone */
        .drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; padding: 28px 24px; text-align: center; cursor: pointer; transition: all 0.25s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #0f766e; background: #f0fdfa; }
        .drop-zone.has-file { border-style: solid; border-color: #059669; background: #ecfdf5; }
        .drop-icon { width: 48px; height: 48px; border-radius: 12px; background: #ffffff; color: #0f766e; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .drop-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .drop-subtitle { font-size: 13px; color: #64748b; }
        .file-badge { display: inline-flex; align-items: center; gap: 8px; background: #ffffff; padding: 6px 14px; border-radius: 20px; border: 1px solid #a7f3d0; font-size: 13px; font-weight: 700; color: #065f46; margin-top: 10px; }

        /* Buttons */
        .btn-primary-modern { background: #0f766e; color: #ffffff; border: none; height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: all 0.2s; width: 100%; }
        .btn-primary-modern:hover:not(:disabled) { background: #115e59; transform: translateY(-1px); }
        .btn-primary-modern:disabled { background: #94a3b8; cursor: not-allowed; }

        .btn-outline-modern { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; height: 38px; padding: 0 16px; border-radius: 8px; font-weight: 600; font-size: 12.5px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; width: 100%; margin-top: 8px; }
        .btn-outline-modern:hover { background: #f1f5f9; }

        .btn-revalidate { background: #2563eb; color: #ffffff; border: none; height: 40px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.2s; }
        .btn-revalidate:hover { background: #1d4ed8; }
        .btn-revalidate.pulse-alert { animation: pulseOrange 1.5s infinite; background: #ea580c; }
        @keyframes pulseOrange { 0% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.5); } 70% { box-shadow: 0 0 0 10px rgba(234, 88, 12, 0); } 100% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0); } }

        .btn-success-modern { background: #059669; color: #ffffff; border: none; height: 40px; padding: 0 20px; border-radius: 10px; font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s; }
        .btn-success-modern:hover:not(:disabled) { background: #047857; transform: translateY(-1px); }
        .btn-success-modern:disabled { background: #cbd5e1; color: #64748b; cursor: not-allowed; }

        .btn-genso { background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%); color: #ffffff; border: none; height: 46px; padding: 0 26px; border-radius: 12px; font-weight: 700; font-size: 14.5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25); transition: all 0.2s; }
        .btn-genso:hover:not(:disabled) { transform: translateY(-1px); }
        .btn-genso:disabled { background: #94a3b8; box-shadow: none; cursor: not-allowed; }

        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        @media (max-width: 900px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        .kpi-card { padding: 14px 18px; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.04); }
        .kpi-card.active-filter { border-color: #0f766e; box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.18); }
        .kpi-label { font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
        .kpi-value { font-size: 22px; font-weight: 800; line-height: 1; }

        /* Editable Table */
        .table-container { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .table-scroll { overflow-x: auto; max-height: 560px; }
        .table-modern { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .table-modern thead th { background: #f8fafc; color: #475569; font-weight: 700; padding: 11px 8px; text-align: left; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .table-modern tbody td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; vertical-align: middle; }
        .table-modern tbody tr:hover { background-color: #f8fafc; }
        .row-error { background-color: #fef2f2 !important; }
        .row-dup { background-color: #fffbeb !important; }

        /* Inline Inputs */
        .cell-input { border: 1px solid transparent; background: transparent; padding: 4px 6px; border-radius: 5px; font-size: 12.5px; font-weight: 600; color: #1e293b; transition: all 0.15s; }
        .cell-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .cell-input:focus { border-color: #0f766e; background: #ffffff; outline: none; box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15); }
        .row-error .cell-input { border-color: #fca5a5; background: #ffffff; }
        .cell-input.is-edited { background-color: #eff6ff !important; border-color: #93c5fd !important; color: #1d4ed8; }
        .mat-input-col { background-color: #f0fdfa; }

        .btn-del-row { background: #fee2e2; color: #dc2626; border: none; width: 25px; height: 25px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; }
        .btn-del-row:hover { background: #ef4444; color: #ffffff; }

        .badge-pill { padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-dup { background: #fef3c7; color: #92400e; }
        .badge-err { background: #fee2e2; color: #991b1b; }

        .switch-wrap { display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; padding: 8px 14px; border-radius: 10px; border: 1px solid #e2e8f0; cursor: pointer; font-size: 13px; font-weight: 600; color: #334155; }
        .switch-wrap input { width: 16px; height: 16px; accent-color: #059669; cursor: pointer; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'A1'; ?>
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
                    <a class="navbar-brand" href="#" style="font-weight:700; color:#0f766e;">EDI Sale Order Upload & Generate SO / JOB</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="main-container">
            <!-- 4-Step Progress Bar -->
            <div class="stepper-wrapper">
                <div class="step-item active" id="step1">
                    <div class="step-circle">1</div>
                    <span>เลือกไฟล์ Excel (.xlsx)</span>
                </div>
                <div class="step-line" id="line1"></div>
                <div class="step-item" id="step2">
                    <div class="step-circle">2</div>
                    <span>ตรวจสอบราคา & แก้ไข</span>
                </div>
                <div class="step-line" id="line2"></div>
                <div class="step-item" id="step3">
                    <div class="step-circle">3</div>
                    <span>บันทึก EDIORDER1</span>
                </div>
                <div class="step-line" id="line3"></div>
                <div class="step-item" id="step4">
                    <div class="step-circle">4</div>
                    <span>สร้าง SO & Job Order</span>
                </div>
            </div>

            <!-- Step 1: Upload Excel Card -->
            <div class="modern-card">
                <div class="card-header-flex">
                    <h3 class="card-title">
                        <svg width="22" height="22" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        นำเข้าคำสั่งซื้อจากไฟล์ Excel
                    </h3>  
                    <!---                  
                    <h3 class="card-title">
                        <svg width="22" height="22" fill="none" stroke="#0f766e" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        นำเข้าคำสั่งซื้อจากไฟล์ Excel 
                    </h3>
                    <span style="font-size:12.5px; color:#64748b; background:#f1f5f9; padding:5px 12px; border-radius:8px;">
                        โหมด: <b>MANUAL_EXCEL</b> (ดึง Remark จาก STNDMSTR6 อัตโนมัติหากเว้นว่าง)
                    </span>
                    -->
                </div>

                <div class="upload-grid">
                    <div class="date-box">
                        <div>
                            <label class="date-label" for="uploadDate">วันที่นำเข้าข้อมูล (Upload Date)</label>
                            <input type="date" id="uploadDate" class="input-date-modern" value="<?php echo $d; ?>">
                            <p style="font-size:12px; color:#64748b; margin-top:8px; line-height:1.45;">
                                * ระบบจะอ่านข้อมูลจาก Sheet <b>master</b> ข้ามแถวว่างอัตโนมัติ และดึง Currency / UOM จากลูกค้าหากไม่ได้กรอก
                            </p>
                        </div>
                        <div style="margin-top: 12px;">
                            <button type="button" id="btnPreview" class="btn-primary-modern" disabled>
                                🔍 ตรวจสอบข้อมูลในไฟล์ Excel
                            </button>
                            <button type="button" id="btnCheckPending" class="btn-outline-modern">
                                📋 ดูรายการค้างสร้าง SO/Job 
                            </button>
                        </div>
                    </div>

                    <div class="drop-zone" id="dropZone">
                        <input type="file" id="excelFile" accept=".xlsx" style="display:none;">
                        <div class="drop-icon">
                            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <!---
                            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            --->
                        </div>
                        <div class="drop-title" id="dropTitle">ลากไฟล์ Excel (.xlsx) มาวางที่นี่ หรือ คลิกเพื่อเลือกไฟล์</div>
                        <div class="drop-subtitle" id="dropSubtitle">รองรับไฟล์ .xlsx (เช่น EDIManual.xlsx, EDI test.xlsx) สามารถแก้ไขค่าในตารางได้ก่อนบันทึก</div>
                        <div id="fileBadgeContainer" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Step 2 & 3: Preview & Inline Edit Card -->
            <div class="modern-card" id="previewSection" style="display:none;">
                <div class="card-header-flex">
                    <div>
                        <h3 class="card-title">
                            <svg width="22" height="22" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            ตรวจสอบและแก้ไขข้อมูลก่อนบันทึกลง EDIORDER1
                        </h3>
                        <span style="font-size:12.5px; color:#64748b;">💡 ช่องพื้นสีเขียวอ่อนคือค่าสเปกผลิต (MAT Input) สามารถคลิกแก้ไขได้ทุกช่อง แล้วกด <b>"ตรวจสอบอีกครั้ง"</b></span>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label class="switch-wrap">
                            <input type="checkbox" id="chkOverwriteDup" checked>
                            <span>อัปเดตทับรายการซ้ำ</span>
                        </label>
                        <button type="button" id="btnRevalidate" class="btn-revalidate">
                            🔄 ตรวจสอบอีกครั้ง
                        </button>
                        <button type="button" id="btnCommit" class="btn-success-modern" disabled>
                            💾 ยืนยันบันทึกลง EDIORDER1
                        </button>
                    </div>
                </div>

                <!-- 4 KPI Cards -->
                <div class="kpi-grid" id="summaryBox"></div>

                <!-- Editable Data Table -->
                <div class="table-container">
                    <div class="table-scroll">
                        <table class="table-modern" id="previewTable">
                            <thead>
                                <tr>
                                    <th>ลบ</th>
                                    <th>แถว</th>
                                    <th>สถานะ</th>
                                    <th>ลูกค้า</th>
                                    <th>PO No</th>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th>Material In</th>
                                    <th>Schedule Date</th>
                                    <th>Request Date</th>
                                    <th>Part No</th>
                                    <th>Product</th>
                                    <th>Alloy</th>
                                    <th>Temper</th>
                                    <th>P.Temp1</th>
                                    <th>P.Temp2</th>
                                    <th>Grade</th>
                                    <th>P.Grade1</th>
                                    <th>P.Grade2</th>
                                    <th>SG</th>
                                    <th>MG</th>
                                    <th>Flatness</th>
                                    <th>หนา (T)</th>
                                    <th>กว้าง (W)</th>
                                    <th>ยาว (L)</th>
                                    <th>หน่วยขนาด</th>
                                    <th>Work Process</th>
                                    <th>น้ำหนัก</th>
                                    <th>หน่วย นน.</th>
                                    <th>Max Coil Wt</th>
                                    <th>ราคา</th>
                                    <th>สกุลเงิน</th>
                                    <th>Ex.Rate</th>
                                    <th>LME Type</th>
                                    <th>LME</th>
                                    <th>Coil From</th>
                                    <th>Job Remark</th>
                                    <th>ผลการตรวจสอบ</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Step 4: Generate Sale Order & Job Order -->
            <div class="modern-card" id="genSoJobSection" style="display:none; border-top: 4px solid #0f766e;">
                <div class="card-header-flex">
                    <div>
                        <h3 class="card-title">
                            <svg width="24" height="24" fill="none" stroke="#0f766e" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            ขั้นตอนที่ 4: สร้าง Sale Order (SO / TT) และ Job Order อัตโนมัติ
                        </h3>
                        <span id="pendingSubtitle" style="font-size:13px; color:#64748b;">รายการ Manual Excel สถานะ OP ที่พร้อมสร้างเอกสาร</span>
                    </div>
                    <div>
                        <button type="button" id="btnGenSoJob" class="btn-genso">
                            ⚡ สร้าง Sale Order & Job Order ทันที
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-scroll">
                        <table class="table-modern" id="soJobTable">
                            <thead id="soJobThead"></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>

<?php include 'include/footer.php';?>
<script>
let validatedRows = [];
let currentFilter = 'ALL';
let needsRevalidate = false;

const dropZone = document.getElementById('dropZone');
const excelFileInput = document.getElementById('excelFile');
const btnPreview = document.getElementById('btnPreview');
const btnRevalidate = document.getElementById('btnRevalidate');
const btnCommit = document.getElementById('btnCommit');

function esc(val) {
    if (val === null || val === undefined) return '';
    return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function pad3(val) {
    const s = String(val || '').trim();
    return s ? s.padStart(3, '0') : '';
}

// Drag & Drop Events
dropZone.addEventListener('click', () => excelFileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        excelFileInput.files = e.dataTransfer.files;
        handleFileSelected(e.dataTransfer.files[0]);
    }
});

excelFileInput.addEventListener('change', function() {
    if (this.files.length) handleFileSelected(this.files[0]);
});

function handleFileSelected(file) {
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        alert('กรุณาเลือกไฟล์นามสกุล .xlsx เท่านั้นครับ');
        excelFileInput.value = '';
        return;
    }
    const sizeKB = (file.size / 1024).toFixed(1);
    dropZone.classList.add('has-file');
    document.getElementById('dropTitle').innerText = 'พร้อมตรวจสอบไฟล์ Excel เรียบร้อยแล้ว';
    document.getElementById('dropSubtitle').innerText = 'คลิกที่กล่องนี้หากต้องการเปลี่ยนไฟล์ใหม่';
    const badgeBox = document.getElementById('fileBadgeContainer');
    badgeBox.style.display = 'block';
    badgeBox.innerHTML = `<div class="file-badge">📗 ${esc(file.name)} (${sizeKB} KB)</div>`;

    btnPreview.disabled = false;
    document.getElementById('previewSection').style.display = 'none';
    updateStepper(1);
}

function updateStepper(step) {
    for (let i = 1; i <= 4; i++) {
        const s = document.getElementById('step' + i);
        const l = document.getElementById('line' + i);
        s.classList.remove('active', 'done');
        if (l) l.classList.remove('done');
        if (i < step) { s.classList.add('done'); if (l) l.classList.add('done'); }
        else if (i === step) s.classList.add('active');
    }
}

// 1. Validate Excel
btnPreview.addEventListener('click', async function() {
    const uploadDate = document.getElementById('uploadDate').value;
    if (!excelFileInput.files.length) return;

    const formData = new FormData();
    formData.append('action', 'validate_manual');
    formData.append('upload_date', uploadDate);
    formData.append('excel_file', excelFileInput.files[0]);

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังอ่านและตรวจสอบไฟล์ Excel...';

    try {
        const res = await fetch('model/api_edi_upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        validatedRows = data.rows;
        currentFilter = 'ALL';
        needsRevalidate = false;
        btnRevalidate.classList.remove('pulse-alert');
        renderPreviewSection(data.summary);
        updateStepper(2);
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการอ่านไฟล์ Excel');
        console.error(err);
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

// 2. Re-validate Edited Rows
btnRevalidate.addEventListener('click', async function() {
    if (!validatedRows.length) return;

    const payload = {
        action: 'revalidate_manual',
        upload_date: document.getElementById('uploadDate').value,
        rows: validatedRows
    };

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังตรวจสอบ...';

    try {
        const res = await fetch('model/api_edi_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        validatedRows = data.rows;
        needsRevalidate = false;
        this.classList.remove('pulse-alert');
        renderPreviewSection(data.summary);
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ');
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

function recalcSummary() {
    const summary = { total: validatedRows.length, valid: 0, duplicate: 0, error: 0 };
    validatedRows.forEach(r => {
        if (r.status === 'ERROR') summary.error++;
        else if (r.status === 'DUPLICATE') summary.duplicate++;
        else summary.valid++;
    });
    return summary;
}

function renderPreviewSection(summary) {
    const previewSec = document.getElementById('previewSection');
    previewSec.style.display = 'block';

    document.getElementById('summaryBox').innerHTML = `
        <div class="kpi-card ${currentFilter === 'ALL' ? 'active-filter' : ''}" onclick="filterTable('ALL')" style="border-left: 4px solid #334155;">
            <div><div class="kpi-label">รายการทั้งหมด</div><div class="kpi-value" style="color:#0f172a;">${summary.total}</div></div>
        </div>
        <div class="kpi-card ${currentFilter === 'VALID' ? 'active-filter' : ''}" onclick="filterTable('VALID')" style="border-left: 4px solid #059669;">
            <div><div class="kpi-label">ผ่านพร้อมนำเข้า</div><div class="kpi-value" style="color:#059669;">${summary.valid}</div></div>
        </div>
        <div class="kpi-card ${currentFilter === 'DUPLICATE' ? 'active-filter' : ''}" onclick="filterTable('DUPLICATE')" style="border-left: 4px solid #d97706;">
            <div><div class="kpi-label">พบข้อมูลซ้ำใน EDI</div><div class="kpi-value" style="color:#d97706;">${summary.duplicate}</div></div>
        </div>
        <div class="kpi-card ${currentFilter === 'ERROR' ? 'active-filter' : ''}" onclick="filterTable('ERROR')" style="border-left: 4px solid #dc2626;">
            <div><div class="kpi-label">ไม่ผ่านเงื่อนไข</div><div class="kpi-value" style="color:#dc2626;">${summary.error}</div></div>
        </div>
    `;

    renderTableRows();
    btnCommit.disabled = (needsRevalidate || summary.error > 0 || summary.total === 0);
}

function filterTable(statusFilter) {
    currentFilter = statusFilter;
    renderPreviewSection(recalcSummary());
}

function onCellEdit(index, field, inputEl) {
    let val = inputEl.value;
    if (field === 'po_item') {
        val = pad3(val);
        inputEl.value = val;
    }
    validatedRows[index][field] = val;
    validatedRows[index].edited = true;
    inputEl.classList.add('is-edited');

    needsRevalidate = true;
    btnCommit.disabled = true;
    btnRevalidate.classList.add('pulse-alert');
}

function deleteRow(index) {
    if (!confirm(`ต้องการลบแถว Excel ที่ #${validatedRows[index].row_num} ออกจากรายการนำเข้าใช่หรือไม่?`)) return;
    validatedRows.splice(index, 1);
    renderPreviewSection(recalcSummary());
}

function renderTableRows() {
    const tbody = document.querySelector('#previewTable tbody');
    tbody.innerHTML = '';
    let visibleCount = 0;

    validatedRows.forEach((r, idx) => {
        if (currentFilter !== 'ALL' && r.status !== currentFilter) return;
        visibleCount++;

        let trClass = '';
        let badge = '<span class="badge-pill badge-ok">● ผ่าน</span>';
        if (r.status === 'ERROR') {
            trClass = 'row-error';
            badge = '<span class="badge-pill badge-err">✕ ไม่ผ่าน</span>';
        } else if (r.status === 'DUPLICATE') {
            trClass = 'row-dup';
            badge = '<span class="badge-pill badge-dup">! ซ้ำ</span>';
        }

        const ed = r.edited ? 'is-edited' : '';
        const msgText = r.messages.length ? esc(r.messages.join(' | ')) : '<span style="color:#94a3b8;">- ปกติ -</span>';

        tbody.innerHTML += `
            <tr class="${trClass}">
                <td style="text-align:center;"><button type="button" class="btn-del-row" onclick="deleteRow(${idx})">✕</button></td>
                <td style="font-weight:600; color:#64748b;">#${r.row_num}</td>
                <td>${badge}</td>
                <td><input type="text" class="cell-input ${ed}" style="width:65px;" value="${esc(r.customer)}" onchange="onCellEdit(${idx}, 'customer', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:105px;" value="${esc(r.po_no)}" onchange="onCellEdit(${idx}, 'po_no', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:45px; text-align:center;" value="${esc(r.po_item)}" onchange="onCellEdit(${idx}, 'po_item', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:55px;" value="${esc(r.order_category)}" onchange="onCellEdit(${idx}, 'order_category', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:82px;" value="${esc(r.material_in)}" onchange="onCellEdit(${idx}, 'material_in', this)"></td>
                <td><input type="date" class="cell-input ${ed}" style="width:118px;" value="${esc(r.schedule_date)}" onchange="onCellEdit(${idx}, 'schedule_date', this)"></td>
                <td><input type="date" class="cell-input ${ed}" style="width:118px;" value="${esc(r.request_date)}" onchange="onCellEdit(${idx}, 'request_date', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:115px;" value="${esc(r.customer_partno)}" onchange="onCellEdit(${idx}, 'customer_partno', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:42px;" value="${esc(r.product)}" onchange="onCellEdit(${idx}, 'product', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:50px;" value="${esc(r.alloy)}" onchange="onCellEdit(${idx}, 'alloy', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:45px;" value="${esc(r.temper)}" onchange="onCellEdit(${idx}, 'temper', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:48px;" value="${esc(r.produce_temper1)}" onchange="onCellEdit(${idx}, 'produce_temper1', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:48px;" value="${esc(r.produce_temper2)}" onchange="onCellEdit(${idx}, 'produce_temper2', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:48px;" value="${esc(r.grade)}" onchange="onCellEdit(${idx}, 'grade', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:52px;" value="${esc(r.produce_grade1)}" onchange="onCellEdit(${idx}, 'produce_grade1', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:52px;" value="${esc(r.produce_grade2)}" onchange="onCellEdit(${idx}, 'produce_grade2', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:45px;" value="${esc(r.sg)}" onchange="onCellEdit(${idx}, 'sg', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:45px;" value="${esc(r.mg)}" onchange="onCellEdit(${idx}, 'mg', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:45px;" value="${esc(r.flatness_grade)}" onchange="onCellEdit(${idx}, 'flatness_grade', this)"></td>
                <td><input type="number" step="0.001" class="cell-input ${ed}" style="width:62px;" value="${esc(r.thickness)}" onchange="onCellEdit(${idx}, 'thickness', this)"></td>
                <td><input type="number" step="0.001" class="cell-input ${ed}" style="width:68px;" value="${esc(r.width)}" onchange="onCellEdit(${idx}, 'width', this)"></td>
                <td><input type="number" step="0.001" class="cell-input ${ed}" style="width:68px;" value="${esc(r.length)}" onchange="onCellEdit(${idx}, 'length', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:45px;" value="${esc(r.uom_dim)}" onchange="onCellEdit(${idx}, 'uom_dim', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:130px;" value="${esc(r.work_process)}" onchange="onCellEdit(${idx}, 'work_process', this)"></td>
                <td><input type="number" class="cell-input ${ed}" style="width:68px;" value="${esc(r.order_weight)}" onchange="onCellEdit(${idx}, 'order_weight', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:42px;" value="${esc(r.uom_weight)}" onchange="onCellEdit(${idx}, 'uom_weight', this)"></td>
                <td><input type="number" class="cell-input ${ed}" style="width:65px;" value="${esc(r.coil_max_weight)}" onchange="onCellEdit(${idx}, 'coil_max_weight', this)"></td>
                <td><input type="number" step="0.0001" class="cell-input ${ed}" style="width:78px;" value="${esc(r.unit_price)}" onchange="onCellEdit(${idx}, 'unit_price', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:50px;" value="${esc(r.currency)}" onchange="onCellEdit(${idx}, 'currency', this)"></td>
                <td><input type="number" step="0.00001" class="cell-input ${ed}" style="width:62px;" value="${esc(r.exchange_rate)}" onchange="onCellEdit(${idx}, 'exchange_rate', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:88px;" value="${esc(r.lme_type)}" onchange="onCellEdit(${idx}, 'lme_type', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${ed}" style="width:68px;" value="${esc(r.lme)}" onchange="onCellEdit(${idx}, 'lme', this)"></td>
                <td><input type="text" class="cell-input ${ed}" style="width:48px;" value="${esc(r.coil_from)}" onchange="onCellEdit(${idx}, 'coil_from', this)"></td>
                <td><input type="text" class="cell-input mat-input-col ${ed}" style="width:220px;" value="${esc(r.job_remark)}" onchange="onCellEdit(${idx}, 'job_remark', this)" title="${esc(r.job_remark)}"></td>
                <td style="color:${r.status === 'ERROR' ? '#dc2626' : '#b45309'}; font-weight:600;">${msgText}</td>
            </tr>
        `;
    });

    if (visibleCount === 0) {
        tbody.innerHTML = `<tr><td colspan="38" style="text-align:center; padding:24px; color:#64748b;">ไม่พบรายการในหมวดหมู่นี้</td></tr>`;
    }
}

// 3. Commit to EDIORDER1
btnCommit.addEventListener('click', async function() {
    if (needsRevalidate) {
        alert('คุณมีการแก้ไขข้อมูล กรุณากดปุ่ม "ตรวจสอบอีกครั้ง" ก่อนบันทึกครับ');
        return;
    }
    if (!confirm('ยืนยันการนำเข้าข้อมูลทั้งหมดลงตาราง EDIORDER1 ใช่หรือไม่?')) return;

    updateStepper(3);
    const payload = {
        action: 'commit_manual',
        upload_date: document.getElementById('uploadDate').value,
        overwrite_dup: document.getElementById('chkOverwriteDup').checked,
        rows: validatedRows
    };

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังบันทึกข้อมูล...';

    try {
        const res = await fetch('model/api_edi_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            document.getElementById('previewSection').style.display = 'none';
            showPendingSoJobSection(result.pending_rows);
            updateStepper(4);
        } else {
            updateStepper(2);
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
        updateStepper(2);
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

// ดูรายการค้างสร้าง SO/Job ของโหมด MANUAL_EXCEL
document.getElementById('btnCheckPending').addEventListener('click', async function() {
    const uploadDate = document.getElementById('uploadDate').value;
    try {
        const res = await fetch(`model/api_edi_upload.php?action=get_pending&source=MANUAL_EXCEL&upload_date=${encodeURIComponent(uploadDate)}`);
        const data = await res.json();
        if (data.pending_count === 0) {
            alert(`ไม่พบรายการ Manual Excel ค้างสร้าง SO / Job Order ของวันที่ ${uploadDate}`);
            return;
        }
        showPendingSoJobSection(data.pending_rows);
        updateStepper(4);
    } catch (err) {
        alert('ไม่สามารถดึงข้อมูลรายการค้างสร้าง SO/Job ได้');
    }
});

function showPendingSoJobSection(rows) {
    const sec = document.getElementById('genSoJobSection');
    sec.style.display = 'block';
    document.getElementById('pendingSubtitle').innerHTML = `พบรายการ Manual Excel สถานะ <b>OP</b> จำนวน <b>${rows.length}</b> รายการ ที่พร้อมออกเลข SO (SO/TT) และ Job Order`;
    document.getElementById('btnGenSoJob').disabled = (rows.length === 0);

    document.getElementById('soJobThead').innerHTML = `
        <tr>
            <th>ลูกค้า</th>
            <th>เลขที่ PO</th>
            <th>Item</th>
            <th>Category</th>
            <th>Material In</th>
            <th>สินค้า</th>
            <th>Alloy / Temper</th>
            <th>ขนาด (T x W x L)</th>
            <th>Work Process</th>
            <th>น้ำหนักสั่งซื้อ</th>
            <th>ราคา</th>
            <th>Schedule Date</th>
            <th>สถานะ</th>
        </tr>
    `;

    const tbody = document.querySelector('#soJobTable tbody');
    tbody.innerHTML = '';
    rows.forEach(r => {
        const etd = r.ETD_DATE ? String(r.ETD_DATE).substring(0, 10) : '';
        tbody.innerHTML += `
            <tr>
                <td style="font-weight:700;">${esc(r.CSTMSPPL_ID)}</td>
                <td style="font-weight:700;">${esc(r.PO_NO)}</td>
                <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:6px; font-weight:700;">${esc(pad3(r.PO_ITEM))}</span></td>
                <td>${esc(r.ORDER_CATEGORY)}</td>
                <td>${esc(r.MATERIAL_IN)}</td>
                <td><b>${esc(r.PRODUCT_ID)}</b> (${esc(r.PRODUCT_MODEL)})</td>
                <td>${esc(r.ALLOY)} / ${esc(r.TEMPER)}</td>
                <td>${r.THICKNESS} x ${r.WIDTH} x ${r.LENGTH} ${esc(r.UOM_DIMENSION)}</td>
                <td><code>${esc(r.WORK_PROCESS)}</code></td>
                <td style="font-weight:700;">${Number(r.ORDER_WEIGHT).toLocaleString()} ${esc(r.UOM_WEIGHT)}</td>
                <td>${Number(r.UNIT_PRICE).toFixed(4)} ${esc(r.CURRENCY_ID)}</td>
                <td>${esc(etd)}</td>
                <td><span class="badge-pill badge-dup">OP (รอออก SO/Job)</span></td>
            </tr>
        `;
    });

    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// 4. Execute GenSoJob for MANUAL_EXCEL
document.getElementById('btnGenSoJob').addEventListener('click', async function() {
    const uploadDate = document.getElementById('uploadDate').value;
    if (!confirm(`ยืนยันการสร้าง Sale Order และ Job Order สำหรับรายการ Manual Excel วันที่ ${uploadDate} ใช่หรือไม่?`)) return;

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังสร้าง Sale Order & Job Order...';

    try {
        const res = await fetch('model/api_edi_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'gen_so_job', source: 'MANUAL_EXCEL', upload_date: uploadDate })
        });
        const result = await res.json();
        alert(result.message);

        if (result.success && result.generated) {
            renderGeneratedResults(result.generated);
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดระหว่างสร้าง Sale Order / Job Order');
        console.error(err);
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

function renderGeneratedResults(generated) {
    document.getElementById('pendingSubtitle').innerHTML = `<span style="color:#059669; font-weight:700;">✓ สร้าง Sale Order และ Job Order เรียบร้อยแล้วทั้งหมด ${generated.length} รายการ (อัปเดตสถานะ EDI เป็น CL)</span>`;
    document.getElementById('btnGenSoJob').disabled = true;

    document.getElementById('soJobThead').innerHTML = `
        <tr>
            <th>ลูกค้า</th>
            <th>เลขที่ PO - Item</th>
            <th>Sale Order No</th>
            <th>SO Item</th>
            <th>Job Order No</th>
            <th>สินค้า / Alloy</th>
            <th>น้ำหนัก (kg.)</th>
            <th>จำนวนชิ้น (Pcs)</th>
            <th>เส้นทางผลิต (Work Process)</th>
            <th>สถานะ</th>
        </tr>
    `;

    const tbody = document.querySelector('#soJobTable tbody');
    tbody.innerHTML = '';
    generated.forEach(g => {
        tbody.innerHTML += `
            <tr style="background:#f0fdf4;">
                <td style="font-weight:700;">${esc(g.customer)}</td>
                <td><b>${esc(g.po_no)}</b> - ${esc(g.po_item)}</td>
                <td><span style="background:#ccfbf1; color:#0f766e; padding:4px 10px; border-radius:8px; font-weight:800;">${esc(g.saleorder_no)}</span></td>
                <td style="font-weight:700;">${esc(g.saleorder_item)}</td>
                <td><span style="background:#dcfce7; color:#15803d; padding:4px 10px; border-radius:8px; font-weight:800;">${esc(g.job_order)}</span></td>
                <td>${esc(g.product)} / ${esc(g.alloy)}</td>
                <td style="font-weight:700;">${Number(g.order_weight).toLocaleString()}</td>
                <td style="font-weight:700;">${Number(g.order_piece).toLocaleString()}</td>
                <td><code style="background:#f1f5f9; padding:3px 8px; border-radius:5px; color:#334155;">${esc(g.work_process)}</code></td>
                <td><span class="badge-pill badge-ok">✓ สำเร็จ (CL)</span></td>
            </tr>
        `;
    });
}
</script>
</body>
</html>