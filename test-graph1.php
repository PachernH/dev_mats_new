<?php
// ตั้งค่าความปลอดภัยและขนาดไฟล์ที่อนุญาตให้อัปโหลด (เนื่องจากไฟล์ Data มักมีขนาดใหญ่)
ini_set('upload_max_filesize', '40M');
ini_set('post_max_size', '40M');
ini_set('memory_limit', '512M');

$chartData = null;
$headers = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $filePath = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // อ่านบรรทัดแรกเพื่อเอาชื่อคอลัมน์ (Headers)
            $headers = fgetcsv($handle, 10000, ",");
            
            // ล้างช่องว่างในชื่อ Header
            $headers = array_map('trim', $headers);
            
            // เตรียมโครงสร้างข้อมูลสำหรับทำกราฟ
            $timeData = [];
            $numericSeries = [];
            
            // วนลูปอ่านข้อมูลบรรทัดที่เหลือ
            // จำกัดไว้ที่ 2000 บรรทัดแรก เพื่อไม่ให้เบราว์เซอร์ค้างตอนวาดกราฟ (ปรับเพิ่มได้)
            $maxRows = 2000; 
            $rowCount = 0;
            
            while (($data = fgetcsv($handle, 10000, ",")) !== FALSE && $rowCount < $maxRows) {
                // สมมุติตามมาตรฐานไฟล์ BOC: คอลัมน์แรกมักจะเป็น Time หรือ Timestamp
                $timeData[] = trim($data[0]);
                
                // เก็บข้อมูลตัวเลขคอลัมน์อื่นๆ
                for ($i = 1; $i < count($data); $i++) {
                    if (isset($headers[$i]) && $headers[$i] !== '') {
                        $val = trim($data[$i]);
                        $numericSeries[$headers[$i]][] = is_numeric($val) ? (float)$val : 0;
                    }
                }
                $rowCount++;
            }
            fclose($handle);
            
            // แพ็กข้อมูลส่งให้ JavaScript นำไปวาดกราฟ
            $chartData = [
                'labels' => $timeData,
                'series' => $numericSeries
            ];
        } else {
            $error = "ไม่สามารถเปิดไฟล์ CSV ได้";
        }
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
    }
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
<body class="bg-gray-900 text-gray-100 font-sans min-h-screen">

    <div class="container mx-auto px-4 py-8">
        <header class="mb-8 border-b border-gray-800 pb-4 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-emerald-400">HRG Analytics</h1>
                <p class="text-gray-400 text-sm mt-1">High Resolution Graph Visualizer for Rolling Mill Data</p>
            </div>
            <div class="text-right text-xs text-gray-500">
                <p>รองรับไฟล์: BOC, SOC, SOP, EOP, EOC</p>
            </div>
        </header>

        <div class="bg-gray-800 rounded-xl p-6 shadow-lg mb-8 border border-gray-700">
            <h2 class="text-lg font-semibold mb-4 text-gray-200 flex items-center gap-2">
                📂 อัปโหลดไฟล์ข้อมูลดิบ (.csv)
            </h2>
            <form action="" method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-sm font-medium text-gray-400 mb-2">เลือกไฟล์จากเครื่องจักรของคุณ</label>
                    <input type="file" name="csv_file" accept=".csv" required
                        class="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500 cursor-pointer bg-gray-900 rounded-lg p-2 border border-gray-700">
                </div>
                <button type="submit" class="w-full md:w-auto px-6 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-bold rounded-lg transition shadow-md cursor-pointer">
                    📈 วิเคราะห์และสร้างกราฟ
                </button>
            </form>
            
            <?php if ($error): ?>
                <div class="mt-4 p-3 bg-red-900/50 border border-red-700 text-red-200 rounded-lg text-sm"><?= $error ?></div>
            <?php endif; ?>
        </div>

        <?php if ($chartData): ?>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                
                <div class="lg:col-span-1 bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-md max-h-[700px] flex flex-col">
                    <div class="mb-3">
                        <h3 class="font-bold text-gray-200 text-md">เลือกสัญญาณ (Signals)</h3>
                        <p class="text-xs text-gray-400 mt-1">ติ๊กเลือกเพื่อแสดงหรือซ่อนเส้นบนกราฟ</p>
                    </div>
                    <input type="text" id="searchSignal" placeholder="ค้นหาชื่อสัญญาณ..." 
                        class="w-full bg-gray-900 border border-gray-700 rounded-md px-3 py-1.5 text-sm mb-3 focus:outline-none focus:border-emerald-500">
                    
                    <div class="overflow-y-auto flex-1 pr-2 space-y-2" id="signalContainer">
                        <?php 
                        $index = 0;
                        foreach ($chartData['series'] as $headerName => $values): 
                            // เปิดให้แสดง 3 เส้นแรกเป็นค่าเริ่มต้น
                            $checked = ($index < 3) ? 'checked' : '';
                            $index++;
                        ?>
                            <label class="flex items-start gap-3 p-2 bg-gray-900/50 hover:bg-gray-700/50 rounded-lg cursor-pointer transition text-sm">
                                <input type="checkbox" value="<?= htmlspecialchars($headerName) ?>" <?= $checked ?> class="signal-checkbox mt-1 accent-emerald-400">
                                <span class="text-gray-300 break-all select-none"><?= htmlspecialchars($headerName) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="lg:col-span-3 bg-gray-800 rounded-xl p-6 border border-gray-700 shadow-md flex flex-col">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                        <span class="text-sm bg-gray-900 px-3 py-1.5 rounded-md text-gray-400 border border-gray-700">
                            📊 แสดงผลทั้งหมด <span class="text-emerald-400 font-bold"><?= count($chartData['labels']) ?></span> จุดข้อมูล (Data Points)
                        </span>
                        <button onclick="resetZoom()" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-xs rounded-md font-medium text-white transition">
                            🔄 รีเซ็ตการซูม (Reset Zoom)
                        </button>
                    </div>
                    
                    <div class="relative w-full flex-1 min-h-[450px]">
                        <canvas id="hrgChart"></canvas>
                    </div>
                    
                    <div class="mt-4 text-xs text-gray-500 flex justify-between">
                        <p>💡 คำแนะนำ: ใช้สกรอลล์เมาส์ (Scroll) เพื่อซูมเข้า/ออก และคลิกลาก (Click & Drag) เพื่อแพนดูข้อมูลตามช่วงเวลา</p>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div class="text-center py-24 bg-gray-800/30 rounded-2xl border border-dashed border-gray-700">
                <p class="text-gray-500 text-lg">ยังไม่มีข้อมูลสำหรับประมวลผล กรุณาเลือกไฟล์และกดปุ่มวิเคราะห์ด้านบน</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($chartData): ?>
    <script>
        // ดึงข้อมูลมหาศาลจากฝั่ง PHP แปลงเป็น JSON Object ใน JS
        const rawChartData = <?= json_encode($chartData); ?>;
        let myChart = null;

        // สุ่มสีกราฟให้ไม่ซ้ำกันสำหรับแต่ละสัญญาณ
        function getRandomColor() {
            const letters = '0123456789ABCDEF';
            let color = '#';
            for (let i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }

        // ฟังก์ชันหลักในการวาด/อัปเดตกราฟ
        function renderChart() {
            // ค้นหาว่ามีตัวเลือกไหนถูกติ๊กบ้าง
            const activeSignals = Array.from(document.querySelectorAll('.signal-checkbox:checked')).map(cb => cb.value);
            
            // ประกอบชิ้นส่วน Datasets ให้เข้าฟอร์แมต Chart.js
            const datasets = activeSignals.map(signal => {
                const color = getRandomColor();
                return {
                    label: signal,
                    data: rawChartData.series[signal],
                    borderColor: color,
                    backgroundColor: color + '22', // ใส่ความโปร่งใสให้พื้นหลังเส้น
                    borderWidth: 1.5,
                    pointRadius: 0, // ปิดจุดเพื่อเพิ่มความลื่นไหล (High Resolution)
                    pointHoverRadius: 5,
                    tension: 0.1
                };
            });

            const ctx = document.getElementById('hrgChart').getContext('2d');
            
            // ถ้ามีกราฟเก่าอยู่แล้ว ให้ทำลายทิ้งก่อนสร้างใหม่เพื่อไม่ให้ทับซ้อน
            if (myChart) {
                myChart.destroy();
            }

            // สร้างกราฟดึงความสามารถ Chart.js ออกมาเต็มพิกัด
            myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: rawChartData.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            grid: { color: '#334155' },
                            ticks: { color: '#94a3b8', maxTicksLimit: 15 }
                        },
                        y: {
                            grid: { color: '#334155' },
                            ticks: { color: '#94a3b8' }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: '#f1f5f9', font: { size: 11 } }
                        },
                        // เปิดใช้งานความสามารถ Zoom และ Pan
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x',
                            },
                            zoom: {
                                wheel: { enabled: true },
                                pinch: { enabled: true },
                                mode: 'x',
                            }
                        }
                    }
                }
            });
        }

        // ผูก Event ให้กับ Checkbox ทุกตัว เมื่อติ๊กเลือก/เอาออก ให้รีโหลดกราฟใหม่ทันที
        document.querySelectorAll('.signal-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', renderChart);
        });

        // ฟังก์ชันรีเซ็ตมุมมองซูม
        function resetZoom() {
            if (myChart) myChart.resetZoom();
        }

        // ระบบค้นหาชื่อสัญญาณฝั่งซ้ายมือ
        document.getElementById('searchSignal').addEventListener('input', function(e) {
            const keyword = e.target.value.toLowerCase();
            const labels = document.querySelectorAll('#signalContainer label');
            labels.forEach(label => {
                const text = label.textContent.toLowerCase();
                if (text.includes(keyword)) {
                    label.style.display = 'flex';
                } else {
                    label.style.display = 'none';
                }
            });
        });

        // สั่งให้กราฟวาดครั้งแรกตอนโหลดหน้าเว็บเสร็จ
        window.addEventListener('DOMContentLoaded', renderChart);
    </script>
    <?php endif; ?>

</body>
</html>