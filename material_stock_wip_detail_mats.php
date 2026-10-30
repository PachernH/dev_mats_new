<?php
// เริ่ม session ก่อน ANY output
session_start();
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

include 'function_mats.php';
require_once 'dbcon_mats-new.php';

$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';

$container_param = isset($_GET['container']) ? trim($_GET['container']) : '';

$headerInfo = null;
$detailList = [];
$error_msg = '';

if (!empty($container_param)) {
    try {
        // 1. ดึงข้อมูลส่วนหัว จาก REQUISITION_HEADER เชื่อมกับ REQUISITION_DETAIL
        $sql_head = "SELECT TOP 1 
                        h.req_id, 
                        h.req_no, 
                        h.req_date, 
                        h.department, 
                        h.issued_by, 
                        h.status AS header_status,
                        h.remarks,
                        h.ref_no,
                        d.country_of_origin
                     FROM REQUISITION_DETAIL d
                     LEFT JOIN REQUISITION_HEADER h ON LTRIM(RTRIM(d.req_no)) = LTRIM(RTRIM(h.req_no))
                     WHERE LTRIM(RTRIM(d.container_no)) = :container";
        $stmt_head = $conn->prepare($sql_head);
        $stmt_head->execute([':container' => $container_param]);
        $headerInfo = $stmt_head->fetch(PDO::FETCH_ASSOC);

        // 2. ดึงรายการ Packing No. ทั้งหมดจากตาราง REQUISITION_DETAIL โดยตรง
        // ไม่ JOIN ฟิลด์ remark ที่ทำให้เกิด Error เพื่อให้แสดงรายการได้ทันที
        $sql_detail = "SELECT 
                            d.req_detail_id,
                            d.req_no,
                            d.container_no,
                            d.packing_no,
                            d.net_weight_kg,
                            d.piece_count,
                            d.status,
                            d.country_of_origin,
                            d.update_at
                       FROM REQUISITION_DETAIL d
                       WHERE LTRIM(RTRIM(d.container_no)) = :container
                       ORDER BY d.req_detail_id ASC";
        $stmt_detail = $conn->prepare($sql_detail);
        $stmt_detail->execute([':container' => $container_param]);
        $detailList = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }

        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .header-meta-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            background-color: #f1f5f9;
            padding: 18px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .header-meta-item label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .header-meta-item span {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        /* Status Badge */
        .badge-status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            display: inline-block;
        }
        .status-issued {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-use {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #detail_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #detail_table thead th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
            padding: 14px 10px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        #detail_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            white-space: nowrap;
        }
        #detail_table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .header-meta-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            background-color: #f1f5f9;
            padding: 18px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .header-meta-item label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .header-meta-item span {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        /* เพิ่มส่วนนี้: ให้แสดงแยกออกมาอีกบรรทัดแบบเต็มความกว้าง */
        .full-width-item {
            grid-column: 1 / -1;
            border-top: 1px dashed #cbd5e1;
            padding-top: 12px;
            margin-top: 4px;
        }

        .btn-back {
            background-color: #64748b;
            color: #ffffff !important;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover {
            background-color: #475569;
        }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'A5';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size:20px;" href="#">🔍 WIP Stock Detail</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 20px;">
            <div class="dashboard-card">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">
                        Container: <?php echo htmlspecialchars($container_param, ENT_QUOTES, 'UTF-8'); ?>
                    </h4>
                    <a href="material_inventory_stock_wip_mats.php?func=<?php echo urlencode($folder_func); ?>" class="btn-back">
                        ⬅️ Back to WIP Stock
                    </a>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div style="background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 14px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                        ⚠️ <?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <!-- ข้อมูลส่วนหัวตู้ Requisition Header -->
                <div class="header-meta-box">
                    <div class="header-meta-item">
                        <label>Requisition No.</label>
                        <span><?php echo htmlspecialchars($headerInfo['req_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="header-meta-item">
                        <label>Department</label>
                        <span><?php echo htmlspecialchars($headerInfo['department'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="header-meta-item">
                        <label>Requisition Date</label>
                        <span><?php echo !empty($headerInfo['req_date']) ? date('Y-m-d H:i', strtotime($headerInfo['req_date'])) : '-'; ?></span>
                    </div>
                    <div class="header-meta-item">
                        <label>Issued By</label>
                        <span><?php echo htmlspecialchars($headerInfo['issued_by'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="header-meta-item">
                        <label>Country of Origin</label>
                        <span><?php echo htmlspecialchars($headerInfo['country_of_origin'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <!-- แยก Remarks ออกมาอยู่อีกบรรทัดเต็ม (Full width) -->
                    <div class="header-meta-item full-width-item">
                        <label>Remarks</label>
                        <span style="color: #475569; font-weight: 500;">
                            💬 <?php echo nl2br(htmlspecialchars($headerInfo['remarks'] ?? '-', ENT_QUOTES, 'UTF-8')); ?>
                        </span>
                    </div>
                </div>

                <!-- ตารางแสดงรายการ Packing No. ของตู้ -->
                <div class="table-responsive table-full-width">
                    <table id="detail_table" class="table">
                        <thead>
                            <tr>
                                <th style="text-align: center;">#</th>
                                <th style="text-align: center;">Packing No.</th>
                                <th style="text-align: center;">Piece Count</th>
                                <th style="text-align: center;">Net Weight (KG)</th>
                                <th style="text-align: center;">Status (สถานะ)</th>
                                <th style="text-align: center;">Used Status (การใช้งานของผลิต)</th>
                                <th style="text-align: center;">Last Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($detailList)): ?>
                                <?php foreach ($detailList as $idx => $d): 
                                    $st = trim($d['status'] ?? '');
                                    $is_used = (strcasecmp($st, 'Use') === 0);
                                    $update_fmt = !empty($d['update_at']) ? date('Y-m-d H:i:s', strtotime($d['update_at'])) : '-';
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $idx + 1; ?></td>
                                        <td align="center" style="font-weight: 700; color: #1e293b; font-size: 15px;">
                                            📦 <?php echo htmlspecialchars($d['packing_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td align="center"><?php echo number_format((float)($d['piece_count'] ?? 0)); ?></td>
                                        <td align="center" style="font-weight: 600;"><?php echo number_format((float)($d['net_weight_kg'] ?? 0), 2); ?></td>
                                        
                                        <!-- แสดงสถานะจาก Database -->
                                        <td align="center">
                                            <span class="badge-status <?php echo $is_used ? 'status-use' : 'status-issued'; ?>">
                                                <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- สรุปผลว่าผลิตนำไปใช้แล้วหรือยัง -->
                                        <td align="center">
                                            <?php if ($is_used): ?>
                                                <span style="color: #dc2626; font-weight: 700;">
                                                    ✔️ ผลิตนำไปใช้แล้ว (Used)
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #059669; font-weight: 600;">
                                                    ⏳ รอผลิตนำไปใช้ (Issued)
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td align="center" style="color: #64748b;"><?php echo htmlspecialchars($update_fmt, ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" align="center" style="color: #94a3b8; padding: 20px;">ไม่พบรายการใน Container นี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>

<script type="text/javascript">
$(document).ready(function() {
    $("#detail_table").DataTable({
        scrollY: "450px",
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        dom: 'Bfrtip',
        pageLength: 20,
        order: [[0, 'asc']],
        buttons: []
    });
});
</script>
</html>