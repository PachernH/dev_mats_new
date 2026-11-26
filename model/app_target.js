let dailyChartInstance = null;
let monthlyChartInstance = null;

document.getElementById('btnGenerate').addEventListener('click', function() {
    const year = document.getElementById('year').value;
    const target = document.getElementById('target').value;
    const excludeSunday = document.getElementById('excludeSunday').checked ? '1' : '0';

    const btn = this;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
    btn.disabled = true;

    fetch(`model/get_report_data.php?year=${year}&target=${target}&exclude_sunday=${excludeSunday}`)
        .then(response => response.json())
        .then(res => {
            btn.innerHTML = 'Generate Report';
            btn.disabled = false;

            if (res.status === 'success') {
                const data = res.data;
                document.getElementById('kpiSection').style.display = 'flex';
                document.getElementById('chartSection').style.display = 'block';

                // อัปเดต KPI
                document.getElementById('kpiTarget').innerText = `${data.summary.target_year.toLocaleString()} Ton`;
                document.getElementById('kpiActual').innerText = `${data.summary.actual_ytd.toLocaleString()} Ton`;
                
                const varEl = document.getElementById('kpiVariance');
                varEl.innerText = `${data.summary.variance_ytd.toLocaleString()} Ton`;
                varEl.className = `kpi-value ${data.summary.variance_ytd >= 0 ? 'success' : 'danger'}`;

                // วาดกราฟ
                renderDailyChart(data.daily);
                renderMonthlyChart(data.monthly);
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btn.innerHTML = 'Generate Report';
            btn.disabled = false;
            alert('เกิดข้อผิดพลาดในการดึงข้อมูล');
            console.error(err);
        });
});

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
                {
                    label: 'Target forward per day',
                    data: targets,
                    backgroundColor: '#4e9af1',
                    order: 2
                },
                {
                    label: 'Balance Packing Output',
                    data: balances,
                    type: 'line',
                    borderColor: '#28a745',
                    backgroundColor: '#28a745',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: { mode: 'index', intersect: false },
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Quantity (Ton)' } },
                x: { title: { display: true, text: 'Day' } }
            }
        }
    });
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
                {
                    label: 'Target forward per month',
                    data: targets,
                    backgroundColor: '#4e9af1',
                    order: 2
                },
                {
                    label: 'Balance Packing Output',
                    data: balances,
                    type: 'line',
                    borderColor: '#28a745',
                    backgroundColor: '#28a745',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: { mode: 'index', intersect: false },
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Quantity (Ton)' } },
                x: { title: { display: true, text: 'Month' } }
            }
        }
    });
}