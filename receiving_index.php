<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับเข้าวัตถุดิบ - หน้าแรก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }
        .menu-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .menu-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            padding: 30px;
            width: 100%;
            max-width: 450px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .btn-menu {
            display: block;
            width: 100%;
            padding: 18px 20px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-create {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            margin-bottom: 20px;
        }
        .btn-create:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            transform: translateY(-2px);
        }
        .btn-list {
            background-color: #0f172a;
            color: #ffffff;
            border: none;
        }
        .btn-list:hover {
            background-color: #1e293b;
            color: #ffffff;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="menu-container">
    <div class="menu-card">
        <div class="mb-4">
            <i class="fa-solid fa-boxes-stacked fa-3x text-primary mb-3"></i>
            <h3 class="fw-bold text-dark">Material Receiving System</h3>
            <p class="text-muted small">ระบบจัดการรับเข้าวัตถุดิบ</p>
        </div>
        
        <div class="d-grid gap-3">
            <a href="receiving_header.php" class="btn-menu btn-create">
                <i class="fa-solid fa-file-circle-plus me-2"></i> Create Packing List
            </a>
            <a href="packing_list.php" class="btn-menu btn-list">
                <i class="fa-solid fa-clipboard-list me-2"></i> Packing List
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>