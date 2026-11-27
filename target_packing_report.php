<?php
// index.php
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Target & Packing Output Report</title>
    <?php include 'include/header.php';?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <!-- เพิ่ม SheetJS สำหรับ Export Excel -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; width: 150px; }
        
        .btn-search { background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px; letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }
        
        .btn-export { background-color: #059669; color: #ffffff; font-weight: 600; font-size: 13px; letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s; }
        .btn-export:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); }
        
        .kpi-card { text-align: center; padding: 20px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; }
        .kpi-title { font-size: 14px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 8px; }
        .kpi-value { font-size: 28px; font-weight: 700; color: #1e293b; }
        .kpi-value.success { color: #059669; }
        .kpi-value.danger { color: #dc2626; }
        
        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .tab-btn.active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
        
        /* CSS ตาราง Daily */
        .table-report { font-size: 12px; margin-top: 20px; border-collapse: collapse; width: 100%; white-space: nowrap; }
        .table-report th, .table-report td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: right; }
        .table-report td:first-child { text-align: left; background-color: #e2e8f0; font-weight: 700; color: #475569; width: 250px; }
        .table-report tr:hover td:not(:first-child) { background-color: #f0f9ff; }
        .table-report td.negative { color: #dc2626; }
        
        /* หัวตาราง Daily (ทั้ง 2 แถว) ให้เป็นสีฟ้าเทา */
        .table-report thead tr th, 
        .table-report thead tr td {
            background-color: #5b7db1 !important;
            color: white !important;
            font-weight: 700;
            text-align: center;
        }

        /* CSS ตาราง Monthly */
        .table-monthly th, .table-monthly td { border: 1px solid #cbd5e1 !important; }
        .table-monthly thead th { 
            background-color: #5b7db1 !important; 
            color: white !important; 
        }
        .table-monthly td { 
            background-color: #ffffff; 
            color: #333; 
        }
        .table-monthly td:first-child { 
            text-align: left; 
            font-weight: 700; 
            color: #475569; 
            width: 250px; 
        }

        .table-wrapper { overflow-x: auto; margin-top: 20px; border: 1px solid #cbd5e1; border-radius: 8px; }
    </style>
</head>

<body>
<div class="wrapper">
    <?php $menu = 'r_af'; ?>
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
                    <a class="navbar-brand" href="#">Target & Packing Output</a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="content">
            <div class="container-fluid">
                
                <!-- Filters -->
                <div class="filter-wrapper">
                    <div class="filter-item">
                        <label style="font-size: 12px; color: #64748b; font-weight: 700;">ปี (Year)</label>
                        <select id="year" class="form-control-minimal">
                            <option value="2026">2026</option>
                            <option value="2025">2025</option>
                            <option value="2024">2024</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label style="font-size: 12px; color: #64748b; font-weight: 700;">เป้าหมาย (Target)</label>
                        <select id="target" class="form-control-minimal" style="width: 160px;">
                            <option value="10000">10,000</option>
                            <option value="12000">12,000</option>
                            <option value="14000">14,000</option>
                            <option value="14300">14,000+300</option>
                            <option value="14600">14,000+600</option>
                            <option value="14900">14,000+900</option>
                            <option value="15200">14,000+1200</option>
                            <option value="15500">14,000+1500</option>
                            <option value="15800">14,000+1800</option>
                            <option value="16100">14,000+2100</option>
                        </select>
                    </div>
                    <!-- Checkbox Exclude Sunday -->
                    <div class="filter-item" style="flex-direction: row; align-items: center; height: 40px; gap: 8px; margin-bottom: 2px;">
                        <input type="checkbox" id="excludeSunday" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="excludeSunday" style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; cursor: pointer;">Exclude Sunday</label>
                    </div>
                    <!-- Checkbox Show Forward Loading (เอา checked ออกแล้ว) -->
                    <div class="filter-item" style="flex-direction: row; align-items: center; height: 40px; gap: 8px; margin-bottom: 2px;">
                        <input type="checkbox" id="showForward" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="showForward" style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; cursor: pointer;">Show Forward Loading</label>
                    </div>
                    
                    <button id="btnGenerate" class="btn-search">Generate Report</button>
                    <!-- ปุ่ม Export Excel -->
                    <button id="btnExport" class="btn-export" style="display: none;">Export to Excel</button>
                </div>

                <!-- KPI Cards -->
                <div class="row" id="kpiSection" style="display:none;">
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-title">YTD Target</div>
                            <div class="kpi-value" id="kpiTarget">0 Ton</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-title">YTD Actual</div>
                            <div class="kpi-value" id="kpiActual">0 Ton</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="kpi-card">
                            <div class="kpi-title">Variance</div>
                            <div class="kpi-value" id="kpiVariance">0 Ton</div>
                        </div>
                    </div>
                </div>

                <!-- Charts & Table -->
                <div class="dashboard-card" id="chartSection" style="display:none; margin-top: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="tab-btn-group mb-0">
                            <button class="tab-btn active" id="btnDaily">Daily View</button>
                            <button class="tab-btn" id="btnMonthly">Monthly View</button>
                        </div>
                        
                        <div id="monthSelectorWrapper" class="filter-item" style="display:none;">
                            <select id="selectMonth" class="form-control-minimal" style="width: 120px; height: 36px; font-size: 14px;">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                    </div>

                    <!-- Daily View -->
                    <div id="dailyView">
                        <canvas id="dailyChart" height="80"></canvas>
                        <div class="table-wrapper">
                            <table class="table-report" id="dailyDataTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Monthly View -->
                    <div id="monthlyView" style="display:none;">
                        <canvas id="monthlyChart" height="80"></canvas>
                        <div class="table-wrapper">
                            <table class="table-report table-monthly" id="monthlyDataTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<script>
Chart.register(ChartDataLabels);

let dailyChartInstance = null;
let monthlyChartInstance = null;
let globalData = null;
const currentMonth = new Date().getMonth() + 1;

// ตรวจสอบการเปลี่ยน Checkbox Forward Loading ให้วาดตารางใหม่ทันที
document.getElementById('showForward').addEventListener('change', function() {
    if(globalData) {
        if(document.getElementById('btnDaily').classList.contains('active')) {
            filterAndRenderDaily();
        } else {
            renderMonthlyTable(globalData.monthly);
        }
    }
});

document.getElementById('btnDaily').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('btnMonthly').classList.remove('active');
    document.getElementById('dailyView').style.display = 'block';
    document.getElementById('monthlyView').style.display = 'none';
    document.getElementById('monthSelectorWrapper').style.display = 'block';
    if(globalData) filterAndRenderDaily();
});

document.getElementById('btnMonthly').addEventListener('click', function() {
    this.classList.add('active');
    document.getElementById('btnDaily').classList.remove('active');
    document.getElementById('dailyView').style.display = 'none';
    document.getElementById('monthlyView').style.display = 'block';
    document.getElementById('monthSelectorWrapper').style.display = 'none';
    if(globalData) {
        renderMonthlyChart(globalData.monthly);
        renderMonthlyTable(globalData.monthly);
    }
});

document.getElementById('selectMonth').addEventListener('change', function() {
    if(globalData) filterAndRenderDaily();
});

// ปุ่ม Generate
document.getElementById('btnGenerate').addEventListener('click', function() {
    const year = document.getElementById('year').value;
    const target = document.getElementById('target').value;
    const excludeSunday = document.getElementById('excludeSunday').checked ? '1' : '0';

    const btn = this;
    btn.innerHTML = 'Loading...';
    btn.disabled = true;

    fetch(`model/get_report_data.php?year=${year}&target=${target}&exclude_sunday=${excludeSunday}`)
        .then(async response => {
            const text = await response.text();
            try { return JSON.parse(text); } 
            catch (e) { 
                console.error("Raw Response:", text);
                throw new Error("Server Error"); 
            }
        })
        .then(res => {
            btn.innerHTML = 'Generate Report';
            btn.disabled = false;

            if (res.status === 'success') {
                globalData = res.data;
                document.getElementById('kpiSection').style.display = 'flex';
                document.getElementById('chartSection').style.display = 'block';
                document.getElementById('btnExport').style.display = 'inline-block'; // แสดงปุ่ม Export

                document.getElementById('kpiTarget').innerText = `${Number(res.data.summary.target_year).toLocaleString()} Ton`;
                document.getElementById('kpiActual').innerText = `${Number(res.data.summary.actual_ytd).toLocaleString()} Ton`;
                
                const varVal = Number(res.data.summary.variance_ytd);
                const varEl = document.getElementById('kpiVariance');
                varEl.innerText = `${varVal.toLocaleString()} Ton`;
                varEl.className = `kpi-value ${varVal >= 0 ? 'success' : 'danger'}`;

                document.getElementById('selectMonth').value = (year == new Date().getFullYear()) ? currentMonth : 1;
                document.getElementById('btnDaily').click();
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btn.innerHTML = 'Generate Report';
            btn.disabled = false;
            alert('ดึงข้อมูลไม่สำเร็จ: ' + err.message);
            console.error(err);
        });
});

// ==========================================
// ฟังก์ชัน Export Excel
// ==========================================
document.getElementById('btnExport').addEventListener('click', function() {
    const isDaily = document.getElementById('btnDaily').classList.contains('active');
    let tableId = isDaily ? 'dailyDataTable' : 'monthlyDataTable';
    let fileName = isDaily ? 'Daily_Report' : 'Monthly_Report';
    
    const year = document.getElementById('year').value;
    const target = document.getElementById('target').value;
    
    if (isDaily) {
        const month = document.getElementById('selectMonth').options[document.getElementById('selectMonth').selectedIndex].text;
        fileName += `_${month}_${year}`;
    } else {
        fileName += `_${year}`;
    }

    // ดึงตาราง HTML ที่แสดงอยู่
    const table = document.getElementById(tableId);

    // ใช้ SheetJS แปลงตาราง HTML เป็น Workbook
    const wb = XLSX.utils.table_to_book(table, { sheet: "Report", raw: true });

    // ดาวน์โหลดไฟล์
    XLSX.writeFile(wb, `${fileName}.xlsx`);
});

function filterAndRenderDaily() {
    const selectedMonth = parseInt(document.getElementById('selectMonth').value);
    const filteredData = globalData.daily.filter(d => d.month === selectedMonth);
    renderDailyChart(filteredData);
    renderDailyTable(filteredData, selectedMonth);
}

function renderDailyChart(dailyData) {
    const ctx = document.getElementById('dailyChart').getContext('2d');
    if (dailyChartInstance) dailyChartInstance.destroy();
    
    const labels = dailyData.map(d => d.day);
    const targets = dailyData.map(d => d.target);
    const balances = dailyData.map(d => d.balance);

    dailyChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Target forward per day', data: targets, backgroundColor: '#3b82f6', order: 2, datalabels: { display: false } },
                { label: 'Balance Packing Output', data: balances, type: 'line', borderColor: '#059669', backgroundColor: '#059669', borderWidth: 2, fill: false, tension: 0.3, order: 1, pointRadius: 3,
                    datalabels: { display: true, align: 'top', anchor: 'end', offset: 5, backgroundColor: 'rgba(5, 150, 105, 1)', color: 'white', borderRadius: 4, padding: { top: 2, bottom: 2, left: 4, right: 4 }, font: { size: 9, weight: 'bold' }, formatter: function(value) { return value.toFixed(2); }, display: function(context) { return context.dataset.data[context.dataIndex] > 0; } }
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { 
                tooltip: { mode: 'index', intersect: false, callbacks: { label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) label += ': ';
                    if (context.parsed.y !== null) label += context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Ton';
                    return label;
                }}}, 
                legend: { position: 'top' }
            },
            scales: { 
                y: { beginAtZero: true, title: { display: true, text: 'Quantity (Ton)' } }, 
                x: { title: { display: true, text: 'Day' } } 
            }
        }
    });
}

function renderDailyTable(dailyData, monthNum) {
    const table = document.getElementById('dailyDataTable');
    const thead = table.querySelector('thead');
    const tbody = table.querySelector('tbody');
    const monthsName = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
    
    let headerRow = `<tr><th>MONTH / TARGET (TON)</th>`;
    dailyData.forEach(d => { headerRow += `<th>${d.day}</th>`; });
    headerRow += '<th>TOTAL</th></tr>';
    
    let headerRow2 = `<tr><td style="text-align: center !important;">${monthsName[monthNum-1]}</td>`;
    dailyData.forEach(d => { headerRow2 += `<td>${d.day}</td>`; });
    headerRow2 += '<td></td></tr>';
    
    thead.innerHTML = headerRow + headerRow2;

    const formatValue = (val) => {
        if (val === 0 || val === 0.00 || val === undefined || val === null) return '';
        return Number(val).toFixed(2);
    };
    const sum = (arr) => arr.reduce((a, b) => a + b, 0);

    const showForward = document.getElementById('showForward').checked;
    
    let rowData = [
        { name: 'Target forward per day', key: 'target', showZero: true },
        { name: 'Packing Coil output', key: 'coil' },
        { name: 'Packing Reroll Service output', key: 'reroll' },
        { name: 'Packing Circle or Sheet output', key: 'circle' },
        { name: 'Packing Circle of HK Coil - CM', key: 'o1a' }, 
        { name: 'Packing Circle of HK Coil - None CM', key: 'o2a' }, 
        { name: 'Packing Sheet output of HK Coil', key: 'sheet' },
        { name: 'Re-Anneal Circle or Sheet output', key: 'reanneal' },
        { name: 'Service Cut Sheet', key: 'srv_sheet' },
        { name: 'Reject from Customer', key: 'reject' },
        { name: 'Balance Packing Output', key: 'balance' },
        { name: 'Variance Target &Packing Output', key: 'variance' }
    ];

    if (showForward) {
        rowData.push({ name: 'Forward Loading', key: 'forward_order' });
    }

    let bodyHtml = '';
    rowData.forEach(row => {
        let tr = `<tr><td>${row.name}</td>`;
        let values = [];
        
        dailyData.forEach(d => {
            let val = 0;
            if (row.key === 'o1a') { val = Number(d.o1a || 0) + Number(d.o1b || 0); } 
            else if (row.key === 'o2a') { val = Number(d.o2a || 0) + Number(d.o2b || 0); } 
            else { val = d[row.key] !== undefined ? Number(d[row.key]) : 0; }
            
            values.push(val);
            let tdClass = (row.key === 'variance' && val < 0) ? 'negative' : '';
            
            let displayVal = (val === 0 && row.showZero !== true) ? '' : formatValue(val);
            tr += `<td class="${tdClass}">${displayVal}</td>`;
        });
        
        let totalVal = sum(values);
        let tdTotalClass = (row.key === 'variance' && totalVal < 0) ? 'negative' : '';
        tr += `<td class="${tdTotalClass}"><b>${formatValue(totalVal)}</b></td></tr>`;
        bodyHtml += tr;
    });

    tbody.innerHTML = bodyHtml;
}

function renderMonthlyChart(monthlyData) {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    if (monthlyChartInstance) monthlyChartInstance.destroy();
    
    const labels = monthlyData.map(m => m.month_name);
    const targets = monthlyData.map(m => m.target);
    const balances = monthlyData.map(m => m.balance);

    monthlyChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Target forward per month', data: targets, backgroundColor: '#3b82f6', order: 2, datalabels: { display: false } },
                { label: 'Balance Packing Output', data: balances, type: 'line', borderColor: '#059669', backgroundColor: '#059669', borderWidth: 2, fill: false, tension: 0.3, order: 1, pointRadius: 4,
                    datalabels: { display: true, align: 'top', anchor: 'end', offset: 5, backgroundColor: 'rgba(5, 150, 105, 1)', color: 'white', borderRadius: 4, font: { size: 10, weight: 'bold' }, formatter: function(value) { return value.toFixed(2); }, display: function(context) { return context.dataset.data[context.dataIndex] > 0; } }
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { 
                tooltip: { mode: 'index', intersect: false, callbacks: { label: function(context) {
                    let label = context.dataset.label || '';
                    if (label) label += ': ';
                    if (context.parsed.y !== null) label += context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' Ton';
                    return label;
                }}}, 
                legend: { position: 'top' }
            },
            scales: { 
                y: { beginAtZero: true, title: { display: true, text: 'Quantity (Ton)' } }, 
                x: { title: { display: true, text: 'Month' } } 
            }
        }
    });
}

function renderMonthlyTable(monthlyData) {
    const table = document.getElementById('monthlyDataTable');
    const thead = table.querySelector('thead');
    const tbody = table.querySelector('tbody');
    const monthNames = monthlyData.map(m => m.month_name);
    const selectedYear = document.getElementById('year').value;
    const showForward = document.getElementById('showForward').checked;

    let headerRow1 = `<tr><th>MONTH OF YEAR ${selectedYear}</th>`;
    monthNames.forEach(m => { headerRow1 += `<th>${m}</th>`; });
    headerRow1 += '<th>TOTAL</th></tr>';

    let headerRow2 = `<tr><td style="background-color: #5b7db1; color: white; text-align: center; font-weight: bold;">WORK DAY</td>`;
    let totalWorkDay = 0;
    monthlyData.forEach(m => {
        headerRow2 += `<td style="background-color: #5b7db1; color: white; text-align: center; font-weight: bold;">${m.workday}</td>`;
        totalWorkDay += m.workday;
    });
    headerRow2 += `<td style="background-color: #5b7db1; color: white; text-align: center; font-weight: bold;">${totalWorkDay}</td></tr>`;

    thead.innerHTML = headerRow1 + headerRow2;

    const formatValue = (val) => {
        if (val === 0 || val === 0.00 || val === undefined || val === null) return '';
        return Number(val).toFixed(2);
    };
    const sum = (arr) => arr.reduce((a, b) => a + b, 0);

    let rowData = [
        { name: 'Target forward per month', key: 'target', showZero: true },
        { name: 'Packing Coil output', key: 'coil' },
        { name: 'Packing Reroll Service output', key: 'reroll' },
        { name: 'Packing Circle or Sheet output', key: 'circle' },
        { name: 'Packing Circle of HK Coil - CM', key: 'o1' },
        { name: 'Packing Circle of HK Coil - None CM', key: 'o2' },
        { name: 'Packing Sheet output of HK Coil', key: 'sheet' },
        { name: 'Re-Anneal Circle or Sheet output', key: 'reanneal' },
        { name: 'Service Cut Sheet', key: 'srv_sheet' },
        { name: 'Reject from Customer', key: 'reject' },
        { name: 'Balance Packing Output', key: 'balance' },
        { name: 'Variance Target &Packing Output', key: 'variance' },
        { name: 'Onhand Dead Stock', key: 'onhand_ds', showZero: true }
    ];

    if (showForward) {
        rowData.push({ name: 'Forward Loading', key: 'forward_order', showZero: true });
    }

    let bodyHtml = '';
    rowData.forEach(row => {
        let tr = `<tr><td>${row.name}</td>`;
        let total = 0;
        
        monthlyData.forEach(m => {
            let val = m[row.key] !== undefined ? Number(m[row.key]) : 0;
            total += val;
            let tdClass = (row.key === 'variance' && val < 0) ? 'negative' : '';
            
            let displayVal = (val === 0 && row.showZero !== true) ? '' : formatValue(val);
            tr += `<td class="${tdClass}">${displayVal}</td>`;
        });
        
        let tdTotalClass = (row.key === 'variance' && total < 0) ? 'negative' : '';
        if(row.key === 'onhand_ds') {
            tr += `<td>-</td></tr>`;
        } else {
            tr += `<td class="${tdTotalClass}"><b>${formatValue(total)}</b></td></tr>`;
        }
        bodyHtml += tr;
    });

    tbody.innerHTML = bodyHtml;
}
</script>

</body>
<?php include 'include/footer.php';?>
</html>