<?php
require_once 'dbcon_mats-new.php';

$headers = [];
try {
    // กรองแสดงเฉพาะรายการที่มี Status เป็น 'OP' เท่านั้น
    $stmt = $conn->query("SELECT h.gr_no,h.invoice_no,h.container_no,h.pack_list_no,h.receive_status,(SELECT COUNT(d.packing_no) FROM RECEIVING_DETAILS d WHERE d.gr_no = h.gr_no) AS total_packing FROM RECEIVING_HEADER h WHERE h.receive_status = 'OP' ORDER BY h.header_id DESC");
    $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <link rel="icon" type="image/png" href="assets/img/MATS.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; display: flex; align-items: center; }
        .custom-table-header { background-color: #1e293b !important; color: #ffffff !important; font-weight: 600; }
        .badge-total { background-color: #e2e8f0; color: #1e293b; font-weight: 600; padding: 6px 12px; border-radius: 6px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark border-bottom border-secondary mb-4 py-3">
    <div class="container-fluid px-4">
        <a href="receiving_index.php" class="btn btn-outline-light btn-sm me-2"><i class="fa-solid fa-home"></i> หน้าแรก</a>
        <span class="navbar-brand fs-5"><i class="fa-solid fa-clipboard-list me-2"></i> ACTIVE PACKING LISTS (STATUS: OP)</span>
        <a href="receiving_header.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i> สร้างใหม่</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <div class="main-card">
        <div class="card-title-sub text-primary">
            <i class="fa-solid fa-list-check me-2"></i> รายการ Packing List ที่กำลังดำเนินการ (Status: OP)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle text-center mb-0">
                    <thead>
                        <tr class="custom-table-header">
                            <th class="py-3">Invoice No</th>
                            <th class="py-3">Pack List No</th>
                            <th class="py-3">Container</th>
                            <th class="py-3">Total Packing</th>
                            <th class="py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($headers) > 0): ?>
                            <?php foreach ($headers as $h): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($h['invoice_no']); ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($h['pack_list_no']); ?></td>
                                    <td><?php echo htmlspecialchars($h['container_no']); ?></td>
                                    <td><span class="badge-total"><?php echo htmlspecialchars($h['total_packing']); ?></span></td>
                                    <td>
                                        <a href="receiving.php?gr_no=<?php echo urlencode($h['gr_no']); ?>" class="btn btn-success btn-sm fw-bold px-3">
                                            <i class="fa-solid fa-barcode me-1"></i> เริ่ม Scan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-muted py-4">ไม่พบรายการ Packing List ที่มีสถานะ OP ในขณะนี้</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>