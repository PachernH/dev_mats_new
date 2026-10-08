<?php
// กำหนดโฟลเดอร์หลักที่เก็บข้อมูล
$baseDataFolder = __DIR__ . '/data_log';

// ==========================================
// ส่วนของ API (AJAX) สำหรับส่งข้อมูลให้ฝั่งหน้าจอ
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // 1. API ดึงรายชื่อเดือน จากปีที่เลือก
    if ($action === 'get_months' && isset($_GET['year'])) {
        $year = sprintf("%04d", $_GET['year']);
        $dir = $baseDataFolder . '/' . $year;
        $months = [];
        if (is_dir($dir)) {
            $dirs = glob($dir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $d) { $months[] = basename($d); }
            rsort($months);
        }
        echo json_encode($months);
        exit;
    }

    // 2. API ดึงรายชื่อวัน จากปีและเดือนที่เลือก
    if ($action === 'get_days' && isset($_GET['year']) && isset($_GET['month'])) {
        $year = sprintf("%04d", $_GET['year']);
        $month = sprintf("%02d", $_GET['month']);
        $dir = $baseDataFolder . '/' . $year . '/' . $month;
        $days = [];
        if (is_dir($dir)) {
            $dirs = glob($dir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $d) { $days[] = basename($d); }
            rsort($days);
        }
        echo json_encode($days);
        exit;
    }

    // 3. API ดึงรายชื่อ Coil (Prefix) ทั้งหมดจาก วัน/เดือน/ปี ที่เลือก
    if ($action === 'get_coils' && isset($_GET['year']) && isset($_GET['month']) && isset($_GET['day'])) {
        $year = sprintf("%04d", $_GET['year']);
        $month = sprintf("%02d", $_GET['month']);
        $day = sprintf("%02d", $_GET['day']);
        $dir = $baseDataFolder . '/' . $year . '/' . $month . '/' . $day;
        
        $coils = [];
        if (is_dir($dir)) {
            $socFiles = glob($dir . '/*_SOC.csv');
            foreach ($socFiles as $file) {
                $baseName = basename($file);
                $prefix = str_replace('_SOC.csv', '', $baseName);
                
                // แอบอ่านไฟล์ SOC เพื่อหา Coil ID จริงมาแสดงบน UI
                $coilId = 'Unknown';
                if (($handle = fopen($file, "r")) !== FALSE) {
                    fgetcsv($handle, 5000, ","); // แถว 1
                    $headers = array_map('trim', fgetcsv($handle, 5000, ",")); // แถว 2
                    fgetcsv($handle, 5000, ","); // แถว 3
                    $data = fgetcsv($handle, 5000, ","); // แถว 4 (ข้อมูลจริง)
                    if ($headers && $data) {
                        $idx = array_search('SOC_CoilID', $headers);
                        if ($idx !== false && isset($data[$idx])) $coilId = trim($data[$idx]);
                    }
                    fclose($handle);
                }
                
                // ดึงเฉพาะส่วนเวลามาแสดงใน Dropdown
                $timePart = (strlen($prefix) > 11) ? str_replace('_', ':', substr($prefix, 11)) : $prefix;
                $coils[] = [
                    'prefix' => $prefix,
                    'label' => "📦 Coil: " . $coilId . " (เวลา " . $timePart . ")"
                ];
            }
            // เรียงให้ Coil ล่าสุดอยู่บนสุด
            usort($coils, function($a, $b) { return strcmp($b['prefix'], $a['prefix']); });
        }
        echo json_encode($coils);
        exit;
    }

    // 4. API ดึงข้อมูลไฟล์แบบละเอียดมารันกราฟและ Metadata Dashboard
    if ($action === 'get_chart_data' && isset($_GET['year']) && isset($_GET['month']) && isset($_GET['day']) && isset($_GET['prefix'])) {
        $year = sprintf("%04d", $_GET['year']);
        $month = sprintf("%02d", $_GET['month']);
        $day = sprintf("%02d", $_GET['day']);
        $prefix = trim($_GET['prefix']);
        
        $targetPath = $baseDataFolder . '/' . $year . '/' . $month . '/' . $day;
        $bocFile = $targetPath . '/' . $prefix . '_BOC.csv';
        $socFile = $targetPath . '/' . $prefix . '_SOC.csv';
        
        $metaData = [
            'coil_id' => 'N/A', 'alloy' => 'N/A', 'entry_gauge' => 'N/A',
            'exit_gauge' => 'N/A', 'width' => 'N/A', 'setup_id' => 'N/A'
        ];
        
        // อ่านข้อมูล Metadata จาก SOC
        if (file_exists($socFile) && ($handle = fopen($socFile, "r")) !== FALSE) {
            fgetcsv($handle, 5000, ",");
            $headers = array_map('trim', fgetcsv($handle, 5000, ","));
            fgetcsv($handle, 5000, ",");
            $data = fgetcsv($handle, 5000, ",");
            if ($data) {
                $c_id = array_search('SOC_CoilID', $headers);
                $c_al = array_search('SOC_AlloyID', $headers);
                $c_eg = array_search('SOC_IncomingGauge', $headers);
                $c_xg = array_search('SOC_OutgoingGauge', $headers);
                $c_wd = array_search('SOC_IncomingWidth', $headers);
                $c_st = array_search('SOC_SetupID', $headers);
                
                if ($c_id !== false && isset($data[$c_id])) $metaData['coil_id'] = trim($data[$c_id]);
                if ($c_al !== false && isset($data[$c_al])) $metaData['alloy'] = trim($data[$c_al]);
                if ($c_eg !== false && isset($data[$c_eg])) $metaData['entry_gauge'] = round((float)trim($data[$c_eg]) * 1000, 3) . " mm";
                if ($c_xg !== false && isset($data[$c_xg])) $metaData['exit_gauge'] = round((float)trim($data[$c_xg]) * 1000, 3) . " mm";
                if ($c_wd !== false && isset($data[$c_wd])) $metaData['width'] = round((float)trim($data[$c_wd]) * 1000, 1) . " mm";
                if ($c_st !== false && isset($data[$c_st])) $metaData['setup_id'] = trim($data[$c_st]);
            }
            fclose($handle);
        }
        
        // อ่านข้อมูลพล็อตกราฟจาก BOC
        if (!file_exists($bocFile)) {
            echo json_encode(['error' => 'ไม่พบไฟล์พล็อตกราฟในโฟลเดอร์ปลายทาง']);
            exit;
        }
        
        if (($handle = fopen($bocFile, "r")) !== FALSE) {
            $headers = array_map('trim', fgetcsv($handle, 20000, ",")); // แถวแรกของ BOC คือชื่อคอลัมน์ทันที
            $timeData = [];
            $numericSeries = [];
            
            while (($data = fgetcsv($handle, 20000, ",")) !== FALSE) {
                $fullTime = trim($data[0]);
                $timeOnly = (strlen($fullTime) > 11) ? substr($fullTime, 11, 12) : $fullTime; // เอาเฉพาะส่วนเวลาและมิลลิวินาที
                $timeData[] = $timeOnly;
                
                for ($i = 1; $i < count($data); $i++) {
                    if (isset($headers[$i]) && $headers[$i] !== '') {
                        $val = trim($data[$i]);
                        $numericSeries[$headers[$i]][] = (is_numeric($val)) ? (float)$val : 0;
                    }
                }
            }
            fclose($handle);
            
            echo json_encode(['meta' => $metaData, 'labels' => $timeData, 'series' => $numericSeries]);
        } else {
            echo json_encode(['error' => 'ไม่สามารถเปิดอ่านไฟล์ได้']);
        }
        exit;
    }
}

// ค้นหารายชื่อปีทั้งหมดที่มีในโฟลเดอร์เริ่มต้นทำ Dropdown แถวแรก
$years = [];
if (is_dir($baseDataFolder)) {
    $dirs = glob($baseDataFolder . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $d) { $years[] = basename($d); }
    rsort($years);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
</head>
<body class="bg-gray-950 text-gray-100 font-sans min-h-screen">

    <div class="container mx-auto px-4 py-6">
        
        <header class="mb-6 bg-gray-900 border border-gray-800 rounded-xl p-5 shadow-xl">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-800 pb-4 mb-4 gap-2">
                <div>
                    <h1 class="text-2xl font-black text-emerald-400 tracking-wide">HRG GRAPH PLATFORM</h1>
                    <p class="text-xs text-gray-500 font-mono">Root Location: /data_log</p>
                </div>
                <div class="text-xs bg-gray-950 px-3 py-1.5 rounded border border-gray-800 text-gray-400">
                    📶 กราฟสถานะ: <span id="dataStatus" class="text-amber-400 font-bold">กรุณาเลือกโฟลเดอร์ข้อมูลทำงาน</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1">📅 1. เลือกปี:</label>
                    <select id="selectYear" onchange="loadMonths()" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs font-semibold text-gray-200 focus:outline-none focus:border-emerald-500 cursor-pointer">
                        <option value="">-- เลือกปี --</option>
                        <?php foreach($years as $y): ?>
                            <option value="<?= $y ?>"><?= $y ?> ค.ศ.</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1">📆 2. เลือกเดือน:</label>
                    <select id="selectMonth" onchange="loadDays()" disabled class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs font-semibold text-gray-200 focus:outline-none focus:border-emerald-500 cursor-pointer disabled:opacity-40">
                        <option value="">-- ต้องเลือกปีแรกก่อน --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 mb-1">🗓️ 3. เลือกวัน:</label>
                    <select id="selectDay" onchange="loadCoils()" disabled class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs font-semibold text-gray-200 focus:outline-none focus:border-emerald-500 cursor-pointer disabled:opacity-40">
                        <option value="">-- ต้องเลือกเดือนก่อน --</option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-bold text-amber-400 mb-1">📦 4. เลือกหมายเลข Coil / ช่วงเวลา:</label>
                    <select id="selectCoil" disabled class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-xs font-semibold text-amber-400 focus:outline-none focus:border-emerald-500 cursor-pointer disabled:opacity-40">
                        <option value="">-- ต้องเลือกวันก่อน --</option>
                    </select>
                </div>
                <div class="sm:col-span-4 lg:col-span-5 flex justify-end mt-2">
                    <button onclick="loadChartData()" class="px-6 py-2 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-black text-sm rounded-lg transition shadow-md cursor-pointer whitespace-nowrap">
                        📊 ดึงข้อมูลพล็อตกราฟเชิงลึก
                    </button>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6">
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">Coil ID</div>
                <div id="metaCoilId" class="text-md font-black text-emerald-400 mt-1 truncate">-</div>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">Alloy Code</div>
                <div id="metaAlloy" class="text-md font-black text-amber-400 mt-1">-</div>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">Setup ID</div>
                <div id="metaSetup" class="text-md font-black text-blue-400 mt-1">-</div>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">ความหนาขาเข้า</div>
                <div id="metaEntryG" class="text-md font-black text-gray-200 mt-1">-</div>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">เป้าหมายขาออก</div>
                <div id="metaExitG" class="text-md font-black text-gray-200 mt-1">-</div>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-lg p-3 text-center shadow">
                <div class="text-gray-500 text-[10px] font-bold uppercase tracking-wider">ความกว้างหน้าแผ่น</div>
                <div id="metaWidth" class="text-md font-black text-gray-200 mt-1">-</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            
            <div class="lg:col-span-1 bg-gray-900 rounded-xl p-4 border border-gray-800 shadow-md max-h-[600px] flex flex-col">
                <div class="mb-3">
                    <h3 class="font-bold text-gray-200 text-sm">🎛️ สัญญาณพารามิเตอร์</h3>
                    <input type="text" id="searchSignal" placeholder="🔍 ค้นหาชื่อสัญญาณ..." 
                        class="w-full bg-gray-950 border border-gray-700 rounded-md px-3 py-1.5 text-xs mt-2 focus:outline-none focus:border-emerald-500">
                </div>
                
                <div class="overflow-y-auto flex-1 pr-1 space-y-1" id="signalContainer">
                    <p class="text-xs text-gray-500 text-center py-8">โปรดกดเลือกคอยล์ให้เรียบร้อยเพื่อโหลดสัญญาณ</p>
                </div>
            </div>

            <div class="lg:col-span-3 bg-gray-900 rounded-xl p-5 border border-gray-800 shadow-md flex flex-col min-h-[500px]">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-xs text-gray-400">วิธีดู: หมุนลูกกลิ้งเมาส์กลางเพื่อขยาย (Zoom) หรือคลิกลากเมาส์ไปมาเพื่อเลื่อนกราฟ (Pan)</span>
                    <button onclick="resetZoom()" class="px-3 py-1.5 bg-gray-800 hover:bg-gray-700 text-xs font-bold rounded transition">
                        🔄 รีเซ็ตมุมมองซูม
                    </button>
                </div>
                
                <div id="loadingOverlay" class="hidden text-center py-24 text-emerald-400 font-bold text-sm animate-pulse">
                    ⏳ กำลังประมวลผลแตกไฟล์ข้อมูลดิบความถี่สูงลงกราฟเส้น...
                </div>

                <div class="relative w-full flex-1" id="chartWrapper">
                    <canvas id="hrgChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
        let myChart = null;
        let globalChartData = null;
        let selectedSignals = new Set();

        // ฟังก์ชันควบคุมการล็อคสีกราฟจากตัวอักษรของสัญญาณ
        function getColorFromString(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) { hash = str.charCodeAt(i) + ((hash << 5) - hash); }
            let color = '#';
            for (let i = 0; i < 3; i++) {
                let value = (hash >> (i * 8)) & 0xFF;
                value = Math.floor((value + 160) / 2); // คุมโทนสว่าง
                color += ('00' + value.toString(16)).substr(-2);
            }
            return color;
        }

        // --- ระบบควบคุม Dropdown ผูกสัมพันธ์กัน (Cascading AJAX Dropdowns) ---
        async function loadMonths() {
            const year = document.getElementById('selectYear').value;
            const mSelect = document.getElementById('selectMonth');
            const dSelect = document.getElementById('selectDay');
            const cSelect = document.getElementById('selectCoil');
            
            mSelect.innerHTML = '<option value="">-- เลือกเดือน --</option>';
            dSelect.innerHTML = '<option value="">-- ต้องเลือกเดือนก่อน --</option>';
            cSelect.innerHTML = '<option value="">-- ต้องเลือกวันก่อน --</option>';
            dSelect.disabled = cSelect.disabled = true;

            if(!year) { mSelect.disabled = true; return; }
            
            const res = await fetch(`?action=get_months&year=${year}`);
            const months = await res.json();
            months.forEach(m => { mSelect.innerHTML += `<option value="${m}">เดือน ${m}</option>`; });
            mSelect.disabled = false;
        }

        async function loadDays() {
            const year = document.getElementById('selectYear').value;
            const month = document.getElementById('selectMonth').value;
            const dSelect = document.getElementById('selectDay');
            const cSelect = document.getElementById('selectCoil');
            
            dSelect.innerHTML = '<option value="">-- เลือกวัน --</option>';
            cSelect.innerHTML = '<option value="">-- ต้องเลือกวันก่อน --</option>';
            cSelect.disabled = true;

            if(!month) { dSelect.disabled = true; return; }

            const res = await fetch(`?action=get_days&year=${year}&month=${month}`);
            const days = await res.json();
            days.forEach(d => { dSelect.innerHTML += `<option value="${d}">วันที่ ${d}</option>`; });
            dSelect.disabled = false;
        }

        async function loadCoils() {
            const year = document.getElementById('selectYear').value;
            const month = document.getElementById('selectMonth').value;
            const day = document.getElementById('selectDay').value;
            const cSelect = document.getElementById('selectCoil');
            
            cSelect.innerHTML = '<option value="">-- เลือกคอยล์ชิ้นงาน --</option>';
            if(!day) { cSelect.disabled = true; return; }

            const res = await fetch(`?action=get_coils&year=${year}&month=${month}&day=${day}`);
            const coils = await res.json();
            if(coils.length === 0) {
                cSelect.innerHTML = '<option value="">❌ ไม่พบไฟล์ข้อมูลชุดความเร็วในวันนี้</option>';
            } else {
                coils.forEach(c => { cSelect.innerHTML += `<option value="${c.prefix}">${c.label}</option>`; });
                cSelect.disabled = false;
            }
        }

        // --- ฟังก์ชันหลักดึงค่าพล็อตกราฟเส้น ---
        async function loadChartData() {
            const year = document.getElementById('selectYear').value;
            const month = document.getElementById('selectMonth').value;
            const day = document.getElementById('selectDay').value;
            const prefix = document.getElementById('selectCoil').value;

            if (!prefix) return alert('กรุณาเลือกโฟลเดอร์ปี/เดือน/วัน และเลือก Coil ชิ้นงานให้ครบก่อนครับน้า');

            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('chartWrapper').classList.add('opacity-10');

            try {
                const res = await fetch(`?action=get_chart_data&year=${year}&month=${month}&day=${day}&prefix=${prefix}`);
                const data = await res.json();

                if (data.error) { alert(data.error); return; }

                // อัปเดตข้อมูลเมตาในบอร์ดด้านบน
                document.getElementById('metaCoilId').innerText = data.meta.coil_id;
                document.getElementById('metaAlloy').innerText = data.meta.alloy;
                document.getElementById('metaSetup').innerText = data.meta.setup_id;
                document.getElementById('metaEntryG').innerText = data.meta.entry_gauge;
                document.getElementById('metaExitG').innerText = data.meta.exit_gauge;
                document.getElementById('metaWidth').innerText = data.meta.width;
                
                document.getElementById('dataStatus').innerText = `วิเคราะห์พล็อตเรียบร้อย (${data.labels.length} แถวข้อมูล)`;

                globalChartData = data;
                selectedSignals.clear();
                
                generateSignalCheckboxes(Object.keys(data.series));
                updateChart();

            } catch (err) {
                alert('เกิดข้อผิดพลาดทางเทคนิคในการอ่านไฟล์');
                console.error(err);
            } finally {
                document.getElementById('loadingOverlay').classList.add('hidden');
                document.getElementById('chartWrapper').classList.remove('opacity-10');
            }
        }

        function generateSignalCheckboxes(signals) {
            const container = document.getElementById('signalContainer');
            container.innerHTML = '';

            signals.forEach((signal, idx) => {
                if (idx < 3) selectedSignals.add(signal); // ติ๊กให้ 3 ช่องแรกเปิดโชว์อัตโนมัติ

                const isChecked = selectedSignals.has(signal) ? 'checked' : '';
                const label = document.createElement('label');
                label.className = 'flex items-center gap-2 p-1.5 bg-gray-950/60 hover:bg-gray-800/80 rounded cursor-pointer transition text-[11px] border border-transparent hover:border-gray-700';
                label.innerHTML = `
                    <input type="checkbox" value="${signal}" ${isChecked} class="signal-checkbox accent-emerald-400 shrink-0">
                    <span class="text-gray-400 truncate" title="${signal}">${signal}</span>
                `;
                
                label.querySelector('input').addEventListener('change', function(e) {
                    if (e.target.checked) { selectedSignals.add(e.target.value); } 
                    else { selectedSignals.delete(e.target.value); }
                    updateChart();
                });
                container.appendChild(label);
            });
        }

        function updateChart() {
            if (!globalChartData) return;

            const datasets = Array.from(selectedSignals).map(signal => {
                if (!globalChartData.series[signal]) return null;
                const color = getColorFromString(signal);
                return {
                    label: signal,
                    data: globalChartData.series[signal],
                    borderColor: color,
                    backgroundColor: color + '05',
                    borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 4, tension: 0.01
                };
            }).filter(d => d !== null);

            const ctx = document.getElementById('hrgChart').getContext('2d');
            if (myChart) myChart.destroy();

            myChart = new Chart(ctx, {
                type: 'line',
                data: { labels: globalChartData.labels, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { grid: { color: '#1e293b' }, ticks: { color: '#64748b', maxTicksLimit: 10, font: { size: 9 } } },
                        y: { grid: { color: '#1e293b' }, ticks: { color: '#64748b', font: { size: 9 } } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#e2e8f0', font: { size: 10 } } },
                        zoom: {
                            pan: { enabled: true, mode: 'x' },
                            zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'x' }
                        }
                    }
                }
            });
        }

        function resetZoom() { if (myChart) myChart.resetZoom(); }

        // ค้นหาพารามิเตอร์แบบพิมพ์ Real-time
        document.getElementById('searchSignal').addEventListener('input', function(e) {
            const keyword = e.target.value.toLowerCase();
            const labels = document.querySelectorAll('#signalContainer label');
            labels.forEach(label => {
                if (label.textContent.toLowerCase().includes(keyword)) label.style.display = 'flex';
                else label.style.display = 'none';
            });
        });
    </script>
</body>
</html>