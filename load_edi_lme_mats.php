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
    <title>Meyer Aluminium - LME 3-Month Data Upload (LME3MNTH1)</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Sarabun', 'Tahoma', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .main-container { padding: 24px 30px; max-width: 1680px; margin: 0 auto; }
        
        /* Stepper Header (3 Steps) */
        .stepper-wrapper { display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 18px 28px; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .step-item { display: flex; align-items: center; gap: 10px; color: #94a3b8; font-weight: 600; font-size: 13.5px; }
        .step-item.active { color: #0f172a; }
        .step-item.done { color: #059669; }
        .step-circle { width: 30px; height: 30px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13.5px; border: 2px solid #cbd5e1; transition: all 0.3s; }
        .step-item.active .step-circle { background: #1e293b; color: #ffffff; border-color: #1e293b; box-shadow: 0 0 0 4px rgba(30, 41, 59, 0.12); }
        .step-item.done .step-circle { background: #059669; color: #ffffff; border-color: #059669; }
        .step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 14px; }
        .step-line.done { background: #059669; }

        /* Card Layout */
        .modern-card { background: #ffffff; border-radius: 16px; padding: 26px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04); margin-bottom: 24px; }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }

        /* Upload Grid */
        .upload-grid { display: grid; grid-template-columns: 310px 1fr; gap: 24px; align-items: stretch; }
        @media (max-width: 900px) { .upload-grid { grid-template-columns: 1fr; } }

        .date-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .date-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }

        /* Drag & Drop Zone */
        .drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc; padding: 28px 24px; text-align: center; cursor: pointer; transition: all 0.25s ease; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 180px; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #2563eb; background: #eff6ff; }
        .drop-zone.has-file { border-style: solid; border-color: #059669; background: #ecfdf5; }
        .drop-icon { width: 48px; height: 48px; border-radius: 12px; background: #ffffff; color: #2563eb; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .drop-zone.has-file .drop-icon { color: #059669; }
        .drop-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .drop-subtitle { font-size: 13px; color: #64748b; }
        .file-badge { display: inline-flex; align-items: center; gap: 8px; background: #ffffff; padding: 6px 14px; border-radius: 20px; border: 1px solid #a7f3d0; font-size: 13px; font-weight: 700; color: #065f46; margin-top: 10px; }

        /* Buttons */
        .btn-primary-modern { background: #1e293b; color: #ffffff; border: none; height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: all 0.2s; width: 100%; }
        .btn-primary-modern:hover:not(:disabled) { background: #0f172a; transform: translateY(-1px); }
        .btn-primary-modern:disabled { background: #94a3b8; cursor: not-allowed; }

        .btn-outline-modern { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; height: 38px; padding: 0 16px; border-radius: 8px; font-weight: 600; font-size: 12.5px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; width: 100%; margin-top: 8px; }
        .btn-outline-modern:hover { background: #f1f5f9; }

        .btn-add-row { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; height: 40px; padding: 0 14px; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .btn-add-row:hover { background: #dbeafe; }

        .btn-calc-std { background: #7c3aed; color: #ffffff; border: none; height: 40px; padding: 0 14px; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .btn-calc-std:hover { background: #6d28d9; }

        .btn-revalidate { background: #2563eb; color: #ffffff; border: none; height: 40px; padding: 0 16px; border-radius: 10px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.2s; }
        .btn-revalidate:hover { background: #1d4ed8; }
        .btn-revalidate.pulse-alert { animation: pulseOrange 1.5s infinite; background: #ea580c; }
        @keyframes pulseOrange { 0% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.5); } 70% { box-shadow: 0 0 0 10px rgba(234, 88, 12, 0); } 100% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0); } }

        .btn-success-modern { background: #059669; color: #ffffff; border: none; height: 40px; padding: 0 20px; border-radius: 10px; font-weight: 700; font-size: 13.5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.2s; }
        .btn-success-modern:hover:not(:disabled) { background: #047857; transform: translateY(-1px); }
        .btn-success-modern:disabled { background: #cbd5e1; color: #64748b; cursor: not-allowed; }

        /* KPI Summary Cards (4 Columns) */
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
        @media (max-width: 1100px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        .kpi-card { padding: 14px 18px; border-radius: 12px; border: 1px solid #e2e8f0; background: #ffffff; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.2s; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.04); }
        .kpi-card.active-filter { border-color: #1e293b; box-shadow: 0 0 0 2px rgba(30, 41, 59, 0.15); }
        .kpi-label { font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
        .kpi-value { font-size: 22px; font-weight: 800; line-height: 1; }

        /* Editable Table Styling */
        .table-container { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .table-scroll { overflow-x: auto; max-height: 550px; }
        .table-modern { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .table-modern thead th { background: #f8fafc; color: #475569; font-weight: 700; padding: 11px 10px; text-align: left; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .table-modern tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; vertical-align: middle; }
        .table-modern tbody tr:hover { background-color: #f8fafc; }
        .row-error { background-color: #fef2f2 !important; }
        .row-dup { background-color: #fffbeb !important; }

        /* Seamless Inline Inputs */
        .cell-input { border: 1px solid transparent; background: transparent; padding: 5px 8px; border-radius: 6px; font-size: 12.5px; font-weight: 600; color: #1e293b; transition: all 0.15s; text-align: right; }
        .cell-input.date-input { text-align: left; }
        .cell-input:hover { border-color: #cbd5e1; background: #ffffff; }
        .cell-input:focus { border-color: #2563eb; background: #ffffff; outline: none; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15); }
        .row-error .cell-input { border-color: #fca5a5; background: #ffffff; }
        .cell-input.is-edited { background-color: #eff6ff !important; border-color: #93c5fd !important; color: #1d4ed8; }

        .btn-del-row { background: #fee2e2; color: #dc2626; border: none; width: 26px; height: 26px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; }
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
                    <a class="navbar-brand" href="#" style="font-weight:700; color:#1e293b;">EDI LME 3-Month Upload</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="main-container">
            <!-- 3-Step Progress Bar -->
            <div class="stepper-wrapper">
                <div class="step-item active" id="step1">
                    <div class="step-circle">1</div>
                    <span>เลือกไฟล์ CSV (LME 3-Month)</span>
                </div>
                <div class="step-line" id="line1"></div>
                <div class="step-item" id="step2">
                    <div class="step-circle">2</div>
                    <span>ตรวจสอบข้อมูล & แก้ไข</span>
                </div>
                <div class="step-line" id="line2"></div>
                <div class="step-item" id="step3">
                    <div class="step-circle">3</div>
                    <span>บันทึกลงตาราง LME3MNTH1</span>
                </div>
            </div>

            <!-- Step 1: Upload Card -->
            <div class="modern-card">
                <div class="card-header-flex">
                    <h3 class="card-title">
                        <svg width="22" height="22" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        นำเข้าข้อมูลราคาโลหะ LME 3-Month จากไฟล์ CSV
                    </h3>
                    <!---
                    <a href="model/api_edi_lme_upload.php?action=template" style="font-size:12.5px; color:#2563eb; text-decoration:underline; font-weight:600;">
                        📥 ดาวน์โหลดไฟล์ตัวอย่าง (LME3Month_Template.csv)
                    </a>
                    --->
                </div>

                <div class="upload-grid">
                    <div class="date-box">
                        <div>
                            <span class="date-label">โครงสร้างคอลัมน์ CSV (9 คอลัมน์)</span>
                            <p style="font-size:12px; color:#475569; margin-top:4px; line-height:1.5;">
                                1. <b>LME_DATE</b> (วันที่)<br>
                                2. <b>LME3M_DAILY</b> | 3. <b>LME3M_WBPLME</b><br>
                                4. <b>LME3M_WBP</b> | 5. <b>LME3M_MONTHLY</b><br>
                                6. <b>STDEV</b> | 7. <b>FACTOR</b><br>
                                8. <b>STDXFACTOR</b> | 9. <b>MJP</b>
                            </p>
                        </div>
                        <div style="margin-top: 12px;">
                            <button type="button" id="btnPreview" class="btn-primary-modern" disabled>
                                🔍 ตรวจสอบข้อมูลในไฟล์ CSV
                            </button>
                            <button type="button" id="btnManualNew" class="btn-outline-modern">
                                ➕ เพิ่มรายการใหม่ (ไม่ต้องใช้ไฟล์)
                            </button>
                            <button type="button" id="btnViewRecent" class="btn-outline-modern">
                                📋 ดูข้อมูลล่าสุดในตาราง LME 3 MONTH
                            </button>
                        </div>
                    </div>

                    <div class="drop-zone" id="dropZone">
                        <input type="file" id="csvFile" accept=".csv" style="display:none;">
                        <div class="drop-icon">
                            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <div class="drop-title" id="dropTitle">ลากไฟล์ LME3Month.csv มาวางที่นี่ หรือ คลิกเพื่อเลือกไฟล์</div>
                        <div class="drop-subtitle" id="dropSubtitle">แสดงข้อมูลใหม่ทั้งหมด และรายการเก่าย้อนหลังล่าสุด 50 รายการ เพื่อตรวจสอบและแก้ไขก่อนบันทึก</div>
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
                            ตรวจสอบและแก้ไขข้อมูลก่อนบันทึกลง LME3MNTH1
                        </h3>
                        <span style="font-size:12.5px; color:#64748b;">💡 คลิกแก้ตัวเลขหรือวันที่ในตารางได้ทันที หากมีการแก้ไขกรุณากดปุ่ม <b>"ตรวจสอบอีกครั้ง"</b> ก่อนบันทึก</span>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <button type="button" id="btnAddRow" class="btn-add-row">
                            ➕ เพิ่มแถว
                        </button>
                        <button type="button" id="btnCalcStdFactor" class="btn-calc-std" title="คำนวณ STDXFACTOR = STDEV × FACTOR ทุกแถว">
                            🧮 คำนวณ STDXFACTOR
                        </button>
                        <label class="switch-wrap">
                            <input type="checkbox" id="chkOverwriteDup" checked>
                            <span>อัปเดตทับข้อมูลเดิม (Old Record)</span>
                        </label>
                        <button type="button" id="btnRevalidate" class="btn-revalidate">
                            🔄 ตรวจสอบอีกครั้ง
                        </button>
                        <button type="button" id="btnCommit" class="btn-success-modern" disabled>
                            💾 ยืนยันบันทึกลง LME3MNTH1
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
                                    <th style="text-align:center;">ลบ</th>
                                    <th>แถว</th>
                                    <th>สถานะ</th>
                                    <th>วันที่ (LME_DATE)</th>
                                    <th style="text-align:right;">LME3M_DAILY</th>
                                    <th style="text-align:right;">LME3M_WBPLME</th>
                                    <th style="text-align:right;">LME3M_WBP</th>
                                    <th style="text-align:right;">LME3M_MONTHLY</th>
                                    <th style="text-align:right;">STDEV</th>
                                    <th style="text-align:right;">FACTOR</th>
                                    <th style="text-align:right;">STDXFACTOR</th>
                                    <th style="text-align:right;">MJP</th>
                                    <th>หมายเหตุ / ข้อมูลเดิมในระบบ</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Data in LME3MNTH1 Card -->
            <div class="modern-card" id="recentSection" style="display:none; border-top: 4px solid #059669;">
                <div class="card-header-flex">
                    <div>
                        <h3 class="card-title">
                            <svg width="22" height="22" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
                            ข้อมูลปัจจุบันในตาราง LME3MNTH1 (ล่าสุด 50 รายการ)
                        </h3>
                        <span id="recentSubtitle" style="font-size:12.5px; color:#64748b;">เรียงตามวันที่ LME_DATE ล่าสุด</span>
                    </div>
                </div>
                <div class="table-container">
                    <div class="table-scroll">
                        <table class="table-modern" id="recentTable">
                            <thead>
                                <tr>
                                    <th>LME_DATE</th>
                                    <th style="text-align:right;">LME3M_DAILY</th>
                                    <th style="text-align:right;">LME3M_WBPLME</th>
                                    <th style="text-align:right;">LME3M_WBP</th>
                                    <th style="text-align:right;">LME3M_MONTHLY</th>
                                    <th style="text-align:right;">STDEV</th>
                                    <th style="text-align:right;">FACTOR</th>
                                    <th style="text-align:right;">STDXFACTOR</th>
                                    <th style="text-align:right;">MJP</th>
                                </tr>
                            </thead>
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
let fileFilterMeta = { file_total: 0, file_dup_total: 0 };

const dropZone = document.getElementById('dropZone');
const csvFileInput = document.getElementById('csvFile');
const btnPreview = document.getElementById('btnPreview');
const btnRevalidate = document.getElementById('btnRevalidate');
const btnCommit = document.getElementById('btnCommit');

function esc(val) {
    if (val === null || val === undefined) return '';
    return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function fmt2(val) {
    const n = parseFloat(val);
    return isNaN(n) ? '0.00' : n.toFixed(2);
}

// Drag & Drop Events
dropZone.addEventListener('click', () => csvFileInput.click());
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        csvFileInput.files = e.dataTransfer.files;
        handleFileSelected(e.dataTransfer.files[0]);
    }
});

csvFileInput.addEventListener('change', function() {
    if (this.files.length) handleFileSelected(this.files[0]);
});

function handleFileSelected(file) {
    if (!file.name.toLowerCase().endsWith('.csv')) {
        alert('กรุณาเลือกไฟล์นามสกุล .csv เท่านั้นครับ');
        csvFileInput.value = '';
        return;
    }
    const sizeKB = (file.size / 1024).toFixed(1);
    dropZone.classList.add('has-file');
    document.getElementById('dropTitle').innerText = 'พร้อมตรวจสอบไฟล์เรียบร้อยแล้ว';
    document.getElementById('dropSubtitle').innerText = 'คลิกที่กล่องนี้หากต้องการเปลี่ยนไฟล์ใหม่';
    const badgeBox = document.getElementById('fileBadgeContainer');
    badgeBox.style.display = 'block';
    badgeBox.innerHTML = `<div class="file-badge">📄 ${esc(file.name)} (${sizeKB} KB)</div>`;

    btnPreview.disabled = false;
    document.getElementById('previewSection').style.display = 'none';
    updateStepper(1);
}

function updateStepper(step) {
    for (let i = 1; i <= 3; i++) {
        const s = document.getElementById('step' + i);
        const l = document.getElementById('line' + i);
        s.classList.remove('active', 'done');
        if (l) l.classList.remove('done');

        if (i < step) {
            s.classList.add('done');
            if (l) l.classList.add('done');
        } else if (i === step) {
            s.classList.add('active');
        }
    }
}

// 1. ตรวจสอบไฟล์ CSV เริ่มต้น (ดึงเฉพาะข้อมูลใหม่ทั้งหมด + ข้อมูลเดิมย้อนหลังล่าสุด 50 รายการ)
btnPreview.addEventListener('click', async function() {
    if (!csvFileInput.files.length) return;

    const formData = new FormData();
    formData.append('action', 'validate');
    formData.append('csv_file', csvFileInput.files[0]);

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังอ่านและคัดกรองข้อมูล...';

    try {
        const res = await fetch('model/api_edi_lme_upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        validatedRows = data.rows;
        fileFilterMeta = {
            file_total: data.summary.file_total || 0,
            file_dup_total: data.summary.file_dup_total || 0
        };
        currentFilter = 'ALL';
        needsRevalidate = false;
        btnRevalidate.classList.remove('pulse-alert');
        renderPreviewSection(data.summary);
        updateStepper(2);
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
        console.error(err);
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

// เพิ่มรายการใหม่ด้วยตนเอง (ไม่ต้องอัปโหลด CSV)
document.getElementById('btnManualNew').addEventListener('click', function() {
    validatedRows = [{
        row_num: 1,
        status: 'VALID',
        lme_date: '<?php echo $d; ?>',
        lme3m_daily: '0.00',
        lme3m_wbplme: '0.00',
        lme3m_wbp: '0.00',
        lme3m_monthly: '0.00',
        stdev: '0.00',
        factor: '0.00',
        stdxfactor: '0.00',
        mjp: '0.00',
        edited: true,
        messages: ['กรุณากด "ตรวจสอบอีกครั้ง" เพื่อเช็กวันที่ในฐานข้อมูล']
    }];
    fileFilterMeta = { file_total: 0, file_dup_total: 0 };
    currentFilter = 'ALL';
    needsRevalidate = true;
    btnRevalidate.classList.add('pulse-alert');
    renderPreviewSection(recalcSummary());
    updateStepper(2);
});

// เพิ่มแถวใหม่ในตาราง
document.getElementById('btnAddRow').addEventListener('click', function() {
    const nextNum = validatedRows.length ? Math.max(...validatedRows.map(r => r.row_num)) + 1 : 1;
    validatedRows.push({
        row_num: nextNum,
        status: 'VALID',
        lme_date: '<?php echo $d; ?>',
        lme3m_daily: '0.00',
        lme3m_wbplme: '0.00',
        lme3m_wbp: '0.00',
        lme3m_monthly: '0.00',
        stdev: '0.00',
        factor: '0.00',
        stdxfactor: '0.00',
        mjp: '0.00',
        edited: true,
        messages: ['แถวเพิ่มใหม่ - กรุณากดตรวจสอบอีกครั้ง']
    });
    needsRevalidate = true;
    btnRevalidate.classList.add('pulse-alert');
    renderPreviewSection(recalcSummary());
});

// คำนวณ STDXFACTOR = STDEV * FACTOR ทุกแถว
document.getElementById('btnCalcStdFactor').addEventListener('click', function() {
    validatedRows.forEach(r => {
        const s = parseFloat(r.stdev) || 0;
        const f = parseFloat(r.factor) || 0;
        r.stdxfactor = (s * f).toFixed(2);
        r.edited = true;
    });
    needsRevalidate = true;
    btnRevalidate.classList.add('pulse-alert');
    renderPreviewSection(recalcSummary());
});

// 2. ตรวจสอบข้อมูลซ้ำหลังจากแก้ไข (Re-validate)
btnRevalidate.addEventListener('click', async function() {
    if (!validatedRows.length) return;

    const payload = {
        action: 'revalidate',
        rows: validatedRows
    };

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังตรวจสอบ...';

    try {
        const res = await fetch('model/api_edi_lme_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        validatedRows = data.rows;
        needsRevalidate = false;
        this.classList.remove('pulse-alert');
        renderPreviewSection({
            ...data.summary,
            file_total: fileFilterMeta.file_total,
            file_dup_total: fileFilterMeta.file_dup_total
        });
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการตรวจสอบข้อมูลซ้ำ');
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

function recalcSummary() {
    const summary = {
        total: validatedRows.length,
        valid: 0,
        duplicate: 0,
        error: 0,
        file_total: fileFilterMeta.file_total,
        file_dup_total: fileFilterMeta.file_dup_total
    };
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

    const fileTotalNote = (summary.file_total && summary.file_total > summary.total)
        ? `<div style="font-size:11px; color:#64748b; margin-top:4px;">คัดกรองจากในไฟล์ ${summary.file_total} แถว</div>`
        : '';

    const dupTotalNote = (summary.file_dup_total && summary.file_dup_total > summary.duplicate)
        ? `<div style="font-size:11px; color:#b45309; margin-top:4px;">แสดงล่าสุด 50 จาก ${summary.file_dup_total} รายการ</div>`
        : '';

    document.getElementById('summaryBox').innerHTML = `
        <div class="kpi-card ${currentFilter === 'ALL' ? 'active-filter' : ''}" onclick="filterTable('ALL')" style="border-left: 4px solid #334155;">
            <div>
                <div class="kpi-label">รายการที่แสดงทั้งหมด</div>
                <div class="kpi-value" style="color:#0f172a;">${summary.total}</div>
                ${fileTotalNote}
            </div>
        </div>
        <div class="kpi-card ${currentFilter === 'VALID' ? 'active-filter' : ''}" onclick="filterTable('VALID')" style="border-left: 4px solid #059669;">
            <div>
                <div class="kpi-label">ข้อมูลใหม่ (New Record)</div>
                <div class="kpi-value" style="color:#059669;">${summary.valid}</div>
            </div>
        </div>
        <div class="kpi-card ${currentFilter === 'DUPLICATE' ? 'active-filter' : ''}" onclick="filterTable('DUPLICATE')" style="border-left: 4px solid #d97706;">
            <div>
                <div class="kpi-label">ข้อมูลเดิมย้อนหลัง (ล่าสุด 50)</div>
                <div class="kpi-value" style="color:#d97706;">${summary.duplicate}</div>
                ${dupTotalNote}
            </div>
        </div>
        <div class="kpi-card ${currentFilter === 'ERROR' ? 'active-filter' : ''}" onclick="filterTable('ERROR')" style="border-left: 4px solid #dc2626;">
            <div>
                <div class="kpi-label">ข้อมูลไม่ถูกต้อง (Error)</div>
                <div class="kpi-value" style="color:#dc2626;">${summary.error}</div>
            </div>
        </div>
    `;

    renderTableRows();
    btnCommit.disabled = (needsRevalidate || summary.error > 0 || summary.total === 0);
}

function filterTable(statusFilter) {
    currentFilter = statusFilter;
    renderPreviewSection(recalcSummary());
}

// เงื่อนไขเดียวกับ VB6 LostFocus: ถ้าว่างให้เติม "0.00" และจัดรูปแบบทศนิยม 2 ตำแหน่ง (#0.00)
function onCellEdit(index, field, inputEl) {
    let val = inputEl.value.trim();
    if (field !== 'lme_date') {
        if (val === '' || isNaN(val)) {
            val = '0.00';
        } else {
            val = parseFloat(val).toFixed(2);
        }
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
    if (!confirm(`ต้องการลบแถวที่ #${validatedRows[index].row_num} ออกจากรายการนำเข้าใช่หรือไม่?`)) return;
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
        let badge = '<span class="badge-pill badge-ok">● ข้อมูลใหม่</span>';
        if (r.status === 'ERROR') {
            trClass = 'row-error';
            badge = '<span class="badge-pill badge-err">✕ ไม่ผ่าน</span>';
        } else if (r.status === 'DUPLICATE') {
            trClass = 'row-dup';
            badge = '<span class="badge-pill badge-dup">! ข้อมูลเดิม</span>';
        }

        const editedClass = r.edited ? 'is-edited' : '';
        const msgText = (r.messages && r.messages.length) ? esc(r.messages.join(' | ')) : '<span style="color:#94a3b8;">- ปกติ -</span>';

        tbody.innerHTML += `
            <tr class="${trClass}">
                <td style="text-align:center;">
                    <button type="button" class="btn-del-row" title="ลบแถวนี้" onclick="deleteRow(${idx})">✕</button>
                </td>
                <td style="font-weight:600; color:#64748b;">#${r.row_num}</td>
                <td>${badge}</td>
                <td><input type="date" class="cell-input date-input ${editedClass}" style="width:130px;" value="${esc(r.lme_date)}" onchange="onCellEdit(${idx}, 'lme_date', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:105px;" value="${fmt2(r.lme3m_daily)}" onchange="onCellEdit(${idx}, 'lme3m_daily', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:105px;" value="${fmt2(r.lme3m_wbplme)}" onchange="onCellEdit(${idx}, 'lme3m_wbplme', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:105px;" value="${fmt2(r.lme3m_wbp)}" onchange="onCellEdit(${idx}, 'lme3m_wbp', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:105px;" value="${fmt2(r.lme3m_monthly)}" onchange="onCellEdit(${idx}, 'lme3m_monthly', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:90px;" value="${fmt2(r.stdev)}" onchange="onCellEdit(${idx}, 'stdev', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:90px;" value="${fmt2(r.factor)}" onchange="onCellEdit(${idx}, 'factor', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:95px;" value="${fmt2(r.stdxfactor)}" onchange="onCellEdit(${idx}, 'stdxfactor', this)"></td>
                <td><input type="number" step="0.01" class="cell-input ${editedClass}" style="width:90px;" value="${fmt2(r.mjp)}" onchange="onCellEdit(${idx}, 'mjp', this)"></td>
                <td style="color:${r.status === 'ERROR' ? '#dc2626' : (r.status === 'DUPLICATE' ? '#b45309' : '#059669')}; font-weight:600;">${msgText}</td>
            </tr>
        `;
    });

    if (visibleCount === 0) {
        tbody.innerHTML = `<tr><td colspan="13" style="text-align:center; padding:24px; color:#64748b;">ไม่พบรายการในหมวดหมู่นี้</td></tr>`;
    }
}

// 3. ยืนยันบันทึกลงตาราง LME3MNTH1
btnCommit.addEventListener('click', async function() {
    if (needsRevalidate) {
        alert('คุณมีการแก้ไขข้อมูล กรุณากดปุ่ม "ตรวจสอบอีกครั้ง" ก่อนบันทึกครับ');
        return;
    }
    if (!confirm('ยืนยันการบันทึกข้อมูลทั้งหมดลงตาราง LME3MNTH1 ใช่หรือไม่?')) return;

    updateStepper(3);
    const payload = {
        action: 'commit',
        overwrite_dup: document.getElementById('chkOverwriteDup').checked,
        rows: validatedRows
    };

    const originalHTML = this.innerHTML;
    this.disabled = true;
    this.innerHTML = 'กำลังบันทึกข้อมูล...';

    try {
        const res = await fetch('model/api_edi_lme_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        alert(result.message);
        if (result.success) {
            document.getElementById('previewSection').style.display = 'none';
            if (result.recent_rows) {
                renderRecentSection(result.recent_rows);
            }
        } else {
            updateStepper(2);
        }
    } catch (err) {
        alert('เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล');
        updateStepper(2);
    } finally {
        this.disabled = false;
        this.innerHTML = originalHTML;
    }
});

// ดูข้อมูลล่าสุดในตาราง LME3MNTH1
document.getElementById('btnViewRecent').addEventListener('click', async function() {
    try {
        const res = await fetch('model/api_edi_lme_upload.php?action=get_recent');
        const data = await res.json();
        if (!data.success) {
            alert(data.message);
            return;
        }
        renderRecentSection(data.recent_rows);
    } catch (err) {
        alert('ไม่สามารถดึงข้อมูลจากตาราง LME3MNTH1 ได้');
    }
});

function renderRecentSection(rows) {
    const sec = document.getElementById('recentSection');
    sec.style.display = 'block';
    const tbody = document.querySelector('#recentTable tbody');
    tbody.innerHTML = '';

    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px; color:#64748b;">ยังไม่มีข้อมูลในตาราง LME3MNTH1</td></tr>`;
        return;
    }

    rows.forEach(r => {
        const dStr = r.LME_DATE ? String(r.LME_DATE).substring(0, 10) : '';
        tbody.innerHTML += `
            <tr>
                <td style="font-weight:700; color:#0f172a;">${esc(dStr)}</td>
                <td style="text-align:right;">${fmt2(r.LME3M_DAILY)}</td>
                <td style="text-align:right;">${fmt2(r.LME3M_WBPLME)}</td>
                <td style="text-align:right;">${fmt2(r.LME3M_WBP)}</td>
                <td style="text-align:right;">${fmt2(r.LME3M_MONTHLY)}</td>
                <td style="text-align:right;">${fmt2(r.STDEV)}</td>
                <td style="text-align:right;">${fmt2(r.FACTOR)}</td>
                <td style="text-align:right;">${fmt2(r.STDXFACTOR)}</td>
                <td style="text-align:right; font-weight:700; color:#2563eb;">${fmt2(r.MJP)}</td>
            </tr>
        `;
    });

    sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
</body>
</html>