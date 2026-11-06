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
    <!-- ป้องกันการซูมและรองรับหน้าจอ Handheld -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <link rel="icon" type="image/png" href="assets/img/MATS.ico">
    <link href="handheld/bootstrap.min.css" rel="stylesheet">
    <link href="handheld/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            margin: 0;
            padding: 0;
            min-height: 100dvh;
        }
        .navbar-custom {
            padding: 10px 14px;
            background-color: #0f172a !important;
        }
        .navbar-title {
            font-size: 0.95rem;
            font-weight: 600;
        }
        .main-card { 
            background: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            padding: 12px; 
            margin-bottom: 20px; 
            border: 1px solid #e2e8f0; 
        }
        .card-title-sub { 
            font-size: 14px; 
            font-weight: 600; 
            color: #2563eb; 
            margin-bottom: 10px; 
            padding-bottom: 6px; 
            border-bottom: 2px solid #f1f5f9; 
            display: flex; 
            align-items: center; 
        }
        .custom-table-header { 
            background-color: #1e293b !important; 
            color: #ffffff !important; 
            font-weight: 600; 
            font-size: 13px;
        }
        .table td {
            font-size: 13px;
            vertical-align: middle;
        }
        .badge-total { 
            background-color: #e2e8f0; 
            color: #1e293b; 
            font-weight: 600; 
            padding: 4px 8px; 
            border-radius: 6px; 
            font-size: 12px;
        }
        .btn-scan {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom border-bottom border-secondary mb-3 px-2">
    <div class="container-fluid p-0 d-flex justify-content-between align-items-center flex-nowrap">
        <div class="d-flex align-items-center overflow-hidden">
            <a href="receiving_index.php" class="btn btn-outline-light btn-sm me-1 py-1 px-2 text-nowrap" style="font-size: 12px;">
                หน้าแรก
            </a>
            <span class="navbar-brand navbar-title text-truncate m-0" style="font-size: 13px;" title="ACTIVE PACKING LISTS (OP)">
                PACKING LISTS (OP)
            </span>
        </div>
        <a href="receiving_header.php" class="btn btn-primary btn-sm py-1 px-2 text-nowrap ms-1" style="font-size: 12px;">
            สร้างใหม่
        </a>
    </div>
</nav>

<div class="container-fluid px-2 pb-4">
    <div class="main-card">
        <div class="card-title-sub">
            รายการที่กำลังดำเนินการ (Status: OP)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle text-center mb-0">
                    <thead>
                        <tr class="custom-table-header">
                            <th class="py-2">Invoice / Pack List</th>
                            <th class="py-2">Container</th>
                            <th class="py-2">Total</th>
                            <th class="py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($headers) > 0): ?>
                            <?php foreach ($headers as $h): ?>
                                <tr>
                                    <td class="text-start py-2">
                                        <div class="fw-bold text-dark" style="font-size: 12px;"><?php echo htmlspecialchars($h['invoice_no'] ?: '-'); ?></div>
                                        <div class="text-primary font-monospace" style="font-size: 12px;"><?php echo htmlspecialchars($h['pack_list_no']); ?></div>
                                    </td>
                                    <td class="py-2" style="font-size: 12px;"><?php echo htmlspecialchars($h['container_no'] ?: '-'); ?></td>
                                    <td class="py-2"><span class="badge-total"><?php echo htmlspecialchars($h['total_packing']); ?></span></td>
                                    <td class="py-2">
                                        <a href="receiving.php?gr_no=<?php echo urlencode($h['gr_no']); ?>" class="btn btn-success btn-sm btn-scan">
                                            Scan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-muted py-4 small">ไม่พบรายการ Packing List ที่มีสถานะ OP ในขณะนี้</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="handheld/bootstrap.bundle.min.js"></script>
</body>
</html>