<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="Container.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-dark bg-primary">
            <img src="Container.png">
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Container Settings -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>ตั้งค่าตู้คอนเทนเนอร์</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">ประเภทตู้</label>
                            <select class="form-select" id="containerType">
                                <option value="20ft">20FT Dry Container</option>
                                <option value="40ft">40FT Dry Container</option>
                                <option value="40hq">40FT High Cube</option>
                                <option value="custom">กำหนดเอง</option>
                            </select>
                        </div>

                        <div id="customContainer" class="d-none">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label small">ยาว (cm)</label>
                                    <input type="number" class="form-control form-control-sm" id="customLength" value="589">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">กว้าง (cm)</label>
                                    <input type="number" class="form-control form-control-sm" id="customWidth" value="235">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small">สูง (cm)</label>
                                    <input type="number" class="form-control form-control-sm" id="customHeight" value="239">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">น้ำหนักสูงสุด (kg)</label>
                            <input type="number" class="form-control" id="maxWeight" value="28000">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">อัลกอริทึม</label>
                            <select class="form-select" id="algorithmType">
                                <option value="simple">แบบง่าย</option>
                                <!---
                                <option value="guillotine">Guillotine</option>
                                <option value="maximalRectangles">Maximal Rectangles</option>
                                <option value="genetic">Genetic Algorithm</option>
                                --->
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>เงื่อนไขการจัดเรียง</h6>
                    </div>
                    <div class="card-body">
                        
                        <!-- แก้ไขในส่วนเงื่อนไขการจัดเรียง -->
                        <div class="mb-3">
                            <label class="form-label">ลำดับการจัดเรียง</label>
                            <select class="form-select" id="sortingOrder">
                                <option value="area">พื้นที่ฐานมากไปน้อย (ยาว×กว้าง)</option>
                                <!---
                                <option value="weight">น้ำหนักมากไปน้อย</option>
                                <option value="length">ความยาวมากไปน้อย</option>
                                <option value="width">ความกว้างมากไปน้อย</option>
                                <option value="random">แบบสุ่ม</option>
                                --->
                            </select>
                        </div>

                        <!-- เงื่อนไขความปลอดภัย -->
                        <div class="mb-3">
                            <label class="form-label">ข้อจำกัดความปลอดภัย</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="stackingLimit" checked>
                                <label class="form-check-label small" for="stackingLimit">
                                    ไม่จำกัดการซ้อนกัน (จัดเรียงตามฐานสินค้า)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="weightDistribution" checked>
                                <label class="form-check-label small" for="weightDistribution">
                                    การกระจายน้ำหนักด้านล่าง
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="fragileTop" checked>
                                <label class="form-check-label small" for="fragileTop">
                                    สินค้าแตกหักง่ายไว้ด้านบน
                                </label>
                            </div>
                        </div>

                        <div class="row mb-4" id="statistics" style="display: none;">
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title">ใช้ปริมาตร</h6>
                                                <h3 id="spaceUsed">0%</h3>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="fas fa-chart-pie fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                        <!-- เงื่อนไขพิเศษ -->
                        <div class="mb-3">
                            <label class="form-label">เงื่อนไขพิเศษ</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowRotation">
                                <label class="form-check-label small" for="allowRotation">
                                    อนุญาตให้หมุนสินค้าได้
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="fillCorners" disabled>
                                <label class="form-check-label small" for="fillCorners">
                                    เติมมุมให้เต็ม
                                </label>
                            </div>
                        </div>

                        <!-- ระยะห่าง -->
                        <div class="mb-3">
                            <label class="form-label">ระยะห่างระหว่างสินค้า (cm)</label>
                            <input type="number" class="form-control" id="itemSpacing" value="1" min="0" max="10">
                        </div>

                        <!-- กลุ่มสินค้า -->
                        <div class="mb-3">
                            <label class="form-label">การจัดกลุ่มสินค้า</label>
                            <select class="form-select" id="groupingMethod">
                                <option value="none">จัดกลุ่มปกติ</option>
                                <!---
                                <option value="sameType">กลุ่มตามประเภท</option>
                                <option value="weightClass">กลุ่มตามระดับน้ำหนัก</option>
                                --->
                            </select>
                        </div>
                    </div>
                </div>


                <!-- Product Input -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-box me-2"></i>เพิ่มสินค้า</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-3">
                                <input type="number" class="form-control form-control-sm" id="itemLength" placeholder="ยาว (mm)">
                            </div>
                            <div class="col-3">
                                <input type="number" class="form-control form-control-sm" id="itemWidth" placeholder="กว้าง (mm)">
                            </div>
                            <div class="col-3">
                                <input type="number" class="form-control form-control-sm" id="itemHeight" placeholder="สูง (mm)">
                            </div>
                            <div class="col-3">
                                <input type="number" class="form-control form-control-sm" id="itemWeight" placeholder="น้ำหนัก (kg)">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" id="itemQty" placeholder="จำนวน" value="1">
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control form-control-sm" id="itemName" placeholder="ชื่อสินค้า">
                            </div>
                        </div>
                        <button class="btn btn-success w-100 btn-sm" onclick="addItem()">
                            <i class="fas fa-plus me-1"></i>เพิ่มสินค้า
                        </button>
                    </div>
                </div>
                    <!-- อ่านจาก file csv -->
                    <div class="card shadow-sm mb-4 mt-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-file-csv me-2"></i>โหลดสินค้าจากไฟล์ CSV</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">เลือกไฟล์ CSV</label>
                                <input type="file" class="form-control" id="csvFile" accept=".csv" style="display: none;">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="csvFileName" placeholder="เลือกไฟล์ CSV..." readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('csvFile').click()">
                                        <i class="fas fa-folder-open me-1"></i>เลือกไฟล์
                                    </button>
                                </div>
                                <div class="form-text small">
                                    รองรับไฟล์ CSV ที่มาจากโปรแกรม load_packinglist ที่มีคอลัมน์: No, Plate No, Length, Width, Height, Weight 
                                </div>
                            </div>

                            <button class="btn btn-primary w-100" onclick="loadItemsFromCSV()" id="loadCSVBtn">
                                <i class="fas fa-upload me-1"></i>โหลดข้อมูลจาก CSV
                            </button>

                            <div id="csvResults" class="d-none mt-3">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAllCSVItems" checked></th>
                                                <th>ลำดับ</th>
                                                <th>Plate No.</th>
                                                <th>ขนาด (mm)</th>
                                                <th>น้ำหนัก (kg)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="csvItemsList">
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-success w-100 mt-2" onclick="addSelectedCSVItems()">
                                    <i class="fas fa-plus me-1"></i>เพิ่มสินค้าที่เลือกทั้งหมด
                                </button>
                            </div>
                            
                            <div id="csvLoading" class="text-center d-none">
                                <div class="spinner-border text-primary mb-2" role="status"></div>
                                <p class="small text-muted">กำลังประมวลผลไฟล์ CSV...</p>
                            </div>
                            
                            <div id="csvError" class="alert alert-danger d-none"></div>
                        </div>
                    </div>

                <!-- Items List -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>รายการสินค้า</h6>
                        <span class="badge bg-primary" id="itemsCountBadge">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="itemsList" style="max-height: 300px; overflow-y: auto;"></div>
                        <div class="p-3 border-top">
                            <button class="btn btn-primary w-100" onclick="calculateLoading()" id="calculateBtn">
                                <i class="fas fa-calculator me-1"></i>คำนวณการจัดเรียง
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ เพิ่ม modal พื้นฐานใน index.html -->
            <div class="modal fade" id="itemDetailModal" tabindex="-1" aria-labelledby="itemDetailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title" id="itemDetailModalLabel">📦 รายละเอียดสินค้า</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                        </div>
                        <div class="modal-body" id="itemDetailModalBody">
                            <!-- เนื้อหาจะถูกเพิ่มผ่าน JavaScript -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="itemsGroupModal" tabindex="-1" aria-labelledby="itemsGroupModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title" id="itemsGroupModalLabel">📁 กลุ่มสินค้า</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                        </div>
                        <div class="modal-body" id="itemsGroupModalBody">
                            <!-- เนื้อหาจะถูกเพิ่มผ่าน JavaScript -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
                            <small class="text-muted ms-2">สินค้าเหล่านี้อยู่ในตำแหน่งใกล้เคียงกัน</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Statistics -->
                <div class="row mb-4" id="statistics" style="display: none;">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">ใช้พื้นที่</h6>
                                        <h3 id="spaceUsed">0%</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-pie fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">ใช้น้ำหนัก</h6>
                                        <h3 id="weightUsed">0%</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-weight-hanging fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">จำนวนสินค้า</h6>
                                        <h3 id="itemsCount">0</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">ประสิทธิภาพ</h6>
                                        <h3 id="efficiency">0%</h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-bolt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visualization -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center position-relative">
                        <h6 class="mb-0"><i class="fas fa-cube me-2"></i>3D Visualization</h6>
                        <div class="btn-group" role="group" style="z-index: 1000;">
                            <!-- ปุ่มมุมมอง 3D -->
                            <button class="btn btn-sm btn-outline-primary" onclick="setViewPreset('top')" title="มุมมองด้านบน">
                                <i class="fas fa-download"></i> บน
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="setViewPreset('front')" title="มุมมองด้านหน้า">
                                <i class="fas fa-arrow-up"></i> หน้า
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="setViewPreset('side')" title="มุมมองด้านข้าง">
                                <i class="fas fa-arrow-right"></i> ข้าง
                            </button>
                            <!-- ปุ่มสลับมุมมอง 2D/3D -->
                            <button class="btn btn-sm btn-outline-success" onclick="toggleViewMode()" title="สลับเป็นมุมมอง 2D" id="toggleViewBtn">
                                <i class="fas fa-map me-1"></i> 2D
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="💾 SaveCurrentView()" title="บันทึกภาพมุมมองปัจจุบัน">
                                <i class="fas fa-sync-alt"></i> บันทึกภาพ
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="visualization" style="height: 500px; background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%); position: relative;">
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-cube fa-3x mb-3" style="color: #3498db;"></i><br>
                                <h5>3D Visualization</h5>
                                <p class="small">ผลลัพธ์การจัดเรียงจะแสดงที่นี่</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ในส่วนแผนการจัดเรียง -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-table me-2"></i>แผนการจัดเรียง</h6>
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToExcel()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>ชื่อสินค้า</th>
                                        <th>ตำแหน่ง X</th>
                                        <th>ตำแหน่ง Y</th>
                                        <th>ตำแหน่ง Z</th>
                                        <th>ขนาด</th>
                                        <th>น้ำหนัก</th>
                                        <th>ชั้นที่จัดเรียง</th> <!-- ✅ เปลี่ยนจาก "สถานะ" -->
                                    </tr>
                                </thead>
                                <tbody id="loadingPlan">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted p-4">
                                            ยังไม่มีข้อมูลการจัดเรียง
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="modal fade" id="loadingModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h6>กำลังคำนวณการจัดเรียง...</h6>
                    <p class="small text-muted mb-0" id="loadingText">กรุณารอสักครู่</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="js/calculations.js"></script>
    <script src="js/app.js"></script>
</body>
</html>