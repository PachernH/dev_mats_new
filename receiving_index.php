<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <!-- ปรับ viewport ป้องกันการซูมแบบไม่ตั้งใจและฟิกซ์ขนาดสำหรับ Handheld -->
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
            /* ใช้ min-height แบบ dvh เพื่อรองรับแถบสถานะของ Android/Handheld */
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .menu-container {
            width: 100%;
            padding: 12px;
            display: flex;
            justify-content: center;
        }
        .menu-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 20px 16px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        /* Icon ปรับขนาดลงให้พอดีกับหน้าจอเล็ก */
        .header-icon {
            font-size: 2.2rem;
        }
        .menu-title {
            font-size: 1.25rem;
            margin-top: 8px;
            margin-bottom: 2px;
        }
        .menu-subtitle {
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        .btn-menu {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.15s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .btn-create {
            background-color: #2563eb;
            color: #ffffff;
            border: none;
        }
        .btn-create:active, .btn-create:hover {
            background-color: #1d4ed8;
            color: #ffffff;
        }
        .btn-list {
            background-color: #0f172a;
            color: #ffffff;
            border: none;
        }
        .btn-list:active, .btn-list:hover {
            background-color: #1e293b;
            color: #ffffff;
        }

        /* Responsive Tweak สำหรับหน้าจอ Handheld ขนาดเล็กมากๆ (น้อยกว่า 360px) */
        @media (max-height: 600px) {
            .menu-card {
                padding: 15px 12px;
            }
            .header-icon {
                font-size: 1.8rem;
            }
            .btn-menu {
                padding: 10px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="menu-container">
    <div class="menu-card">
        <div class="mb-3">            
            <h4 class="fw-bold text-dark menu-title">Material Receiving System</h4>
            <p class="text-muted menu-subtitle">ระบบจัดการรับเข้าวัตถุดิบ</p>
        </div>
        
        <div class="d-grid gap-2 pt-2">
            <a href="receiving_header.php" class="btn-menu btn-create">
                Create Packing List
            </a>
            
            <a href="packing_list.php" class="btn-menu btn-list">
                Packing List
            </a>
        </div>
    </div>
</div>

<script src="handheld/bootstrap.bundle.min.js"></script>
</body>
</html>