<?php
// packing_list_process_mats.php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func       = isset($_SESSION['LINE']) ? strtoupper(htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8')) : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$allowed_departments = ['PD', 'IT', 'TC', 'MG', 'WH', 'RC'];
if (!in_array($department_func, $allowed_departments)) {
    echo "<script>
            alert('เตือน: แผนกของคุณไม่มีสิทธิ์เข้าใช้งานกระบวนการนี้');
            window.location.href = 'dashboard_mats.php';
          </script>";
    exit();
}

require_once 'dbcon_mats-new.php';

$message = "";
$msgType = "";

// ตรรกะการลบใบรับสินค้า (Delete Header)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['del_gr'])) {
    $del_gr = trim($_GET['del_gr']);
    try {
        // ตรวจสอบความปลอดภัยอีกครั้งว่าไม่มีการรับเข้าจริง ๆ
        $chkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM receiving_details WHERE gr_no = ?");
        $chkStmt->execute([$del_gr]);
        $chkRow = $chkStmt->fetch(PDO::FETCH_ASSOC);

        if ($chkRow && (int)$chkRow['cnt'] === 0) {
            $delStmt = $conn->prepare("DELETE FROM RECEIVING_HEADER WHERE gr_no = ?");
            $delStmt->execute([$del_gr]);

            header("Location: packing_list_process_mats.php?func=" . urlencode($folder_func) . "&msg=deleted");
            exit();
        } else {
            $message = "ไม่สามารถลบได้ เนื่องจากมีรายการรับเข้าวัตถุดิบแล้ว";
            $msgType = "danger";
        }
    } catch (Exception $e) {
        $message = "เกิดข้อผิดพลาดในการลบ: " . $e->getMessage();
        $msgType = "danger";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "ลบรายการเรียบร้อยแล้ว";
    $msgType = "success";
}

// รับค่าค้นหา
$search_keyword = !isset($_GET['search']) ? '' : htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
    <title>Meyer Aluminium - Active Packing Lists (Status: OP)</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .dashboard-card { background: #ffffff; border-radius: 12px; padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .filter-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; background: #ffffff; padding: 18px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .form-control-minimal { border: none !important; border-bottom: 2px solid #cbd5e1 !important; border-radius: 0 !important; background-color: transparent !important; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; outline: none; }
        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 24px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }        
        .badge-job { background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px; padding: 5px 12px; border-radius: 20px; border: 1px solid #bfdbfe; }
        .badge-prod { background-color: #fef3c7; color: #92400e; font-weight: 700; font-size: 12px; padding: 4px 8px; border-radius: 6px; }
        
        .btn-action-work { background-color: #059669; color: #ffffff; font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action-work:hover { background-color: #047857; color: #ffffff; }

        .btn-action-del { background-color: #ef4444; color: #ffffff; font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action-del:hover { background-color: #dc2626; color: #ffffff; }

        .btn-action-disabled { background-color: #cbd5e1; color: #94a3b8; font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 6px; border: none; cursor: not-allowed; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }

        .tab-btn-group { display: flex; gap: 8px; margin-bottom: 15px; }
        .tab-btn { padding: 9px 20px; font-weight: 700; font-size: 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .tab-btn.active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'RC_PACK'; ?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        📦 Packing List Process
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="tab-btn-group">
                <a href="packing_list_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn active">
                   📋 Active OP Lists
                </a>
                <a href="receiving_header_process_mats.php?func=<?php echo $folder_func; ?>" 
                   class="tab-btn">
                   ➕ สร้างใหม่ (Create New)
                </a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $msgType; ?> shadow-sm mb-3" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label style="font-size:12px; font-weight:700; color:#64748b;">SEARCH GR NO. / PACK LIST / INVOICE / CONTAINER</label>
                    <input class="form-control-minimal" name="search_kw" id="search_kw" type="text" placeholder="ระบุเลขที่เอกสารหรือข้อความ..." value="<?php echo $search_keyword; ?>" onchange="search_data()"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" onclick="search_data()">🔍 SEARCH</button>
                </div>
            </div>

            <div class="dashboard-card">
                <h4 style="margin:0 0 15px 0; font-weight:700; color:#1e293b;">
                    📋 รายการใบรับสินค้าและ Packing List ที่กำลังดำเนินการ (Status: OP)
                </h4>

                <div class="table-responsive">
                    <table id="user_table" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="text-align: center;">GR No.</th>
                                <th style="text-align: center;">Invoice No</th>
                                <th style="text-align: center;">Pack List No</th>
                                <th style="text-align: center;">Container No</th>
                                <th style="text-align: left;">Supplier</th>
                                <th style="text-align: left;">Material</th>
                                <th style="text-align: right;">Total Packing</th>
                                <th style="text-align: right;">Total Pieces</th>
                                <th style="text-align: right;">Total Net Wt (kg)</th>
                                <th style="text-align: right;">Total Gross Wt (kg)</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center; width: 160px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $params = [];
                            
                            // Query สรุปยอดจาก RECEIVING_DETAILS โดยตรง
                            $sql = "SELECT 
                                        h.header_id,
                                        h.gr_no,
                                        h.pack_list_no,
                                        h.invoice_no,
                                        h.container_no,
                                        h.supplier_name,
                                        h.description_fg,
                                        h.receive_status,
                                        COALESCE(d.total_pk, 0) AS sum_packing,
                                        COALESCE(d.total_pcs, 0) AS sum_pieces,
                                        COALESCE(d.total_net, 0) AS sum_netweight,
                                        COALESCE(d.total_gross, 0) AS sum_grossweight,
                                        COALESCE(d.rec_count, 0) AS rec_count
                                    FROM RECEIVING_HEADER h
                                    LEFT JOIN (
                                        SELECT 
                                            gr_no,
                                            COUNT(receive_id) AS total_pk,
                                            SUM(piece_count) AS total_pcs,
                                            SUM(net_weight_kg) AS total_net,
                                            SUM(net_weight_kg + 0.5) AS total_gross,
                                            COUNT(receive_id) AS rec_count
                                        FROM receiving_details
                                        GROUP BY gr_no
                                    ) d ON h.gr_no = d.gr_no
                                    WHERE h.receive_status = 'OP'";

                            if (!empty($search_keyword)) {
                                $sql .= " AND (h.gr_no LIKE :kw1 
                                            OR h.pack_list_no LIKE :kw2 
                                            OR h.invoice_no LIKE :kw3 
                                            OR h.container_no LIKE :kw4 
                                            OR h.supplier_name LIKE :kw5)";
                                $params[':kw1'] = '%' . $search_keyword . '%';
                                $params[':kw2'] = '%' . $search_keyword . '%';
                                $params[':kw3'] = '%' . $search_keyword . '%';
                                $params[':kw4'] = '%' . $search_keyword . '%';
                                $params[':kw5'] = '%' . $search_keyword . '%';
                            }
                            $sql .= " ORDER BY h.header_id DESC";

                            try {
                                $stmt = $conn->prepare($sql);
                                $stmt->execute($params);

                                while ($h = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $safe_gr   = htmlspecialchars($h['gr_no'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $rec_count = (int)($h['rec_count'] ?? 0);
                                    ?>
                                    <tr>
                                        <td align="center" style="font-weight:600;"><?php echo $safe_gr; ?></td>
                                        <td align="center"><?php echo htmlspecialchars($h['invoice_no'] ?? ''); ?></td>                                        
                                        <td align="center" style="font-weight:600;"><?php echo htmlspecialchars($h['pack_list_no'] ?? ''); ?></td>

                                        <td align="center"><span class="badge-prod"><?php echo htmlspecialchars($h['container_no'] ?? ''); ?></span></td>
                                        <td align="left"><?php echo htmlspecialchars($h['supplier_name'] ?? ''); ?></td>
                                        <td align="left"><?php echo htmlspecialchars($h['description_fg'] ?? ''); ?></td>
                                        
                                        <!-- สรุปยอดจาก RECEIVING_DETAILS -->
                                        <td align="right" style="font-weight:600;"><?php echo number_format($h['sum_packing']); ?></td>
                                        <td align="right" style="font-weight:600;"><?php echo number_format($h['sum_pieces']); ?></td>
                                        <td align="right" style="font-weight:700; color:#059669;"><?php echo number_format((float)$h['sum_netweight'], 2); ?></td>
                                        <td align="right" style="font-weight:700; color:#d97706;"><?php echo number_format((float)$h['sum_grossweight'], 2); ?></td>
                                        
                                        <td align="center"><span class="badge" style="background-color:#059669;"><?php echo htmlspecialchars($h['receive_status'] ?? ''); ?></span></td>
                                        <td align="center">
                                            <div style="display: inline-flex; gap: 6px;">
                                                <a href="receiving_list_process_mats.php?func=<?php echo urlencode($folder_func); ?>&gr_no=<?php echo urlencode($h['gr_no'] ?? ''); ?>" class="btn-action-work" title="สแกนรับเข้า">
                                                    📷 Scan
                                                </a>

                                                <?php if ($rec_count == 0): ?>
                                                    <!-- หากยังไม่มีการรับเข้า สามารถลบได้ -->
                                                    <a href="packing_list_process_mats.php?func=<?php echo urlencode($folder_func); ?>&action=delete&del_gr=<?php echo urlencode($h['gr_no'] ?? ''); ?>" 
                                                       class="btn-action-del" 
                                                       onclick="return confirm('ยืนยันที่จะลบเอกสาร <?php echo $safe_gr; ?> หรือไม่?');"
                                                       title="ลบเอกสาร (ยังไม่มีการรับเข้า)">
                                                        🗑️ Del
                                                    </a>
                                                <?php else: ?>
                                                    <!-- หากมีการรับเข้าแล้ว ปุ่มจะไม่ทำงาน -->
                                                    <button type="button" class="btn-action-disabled" title="ไม่สามารถลบได้ เนื่องจากมีรายการรับเข้าแล้ว" disabled>
                                                        🔒 Del
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } catch (Exception $e) {
                                echo "<tr><td colspan='12' class='text-center text-danger'>เกิดข้อผิดพลาดในการโหลดข้อมูล: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <input class="form-control" id="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
$("#user_table").DataTable({
    scrollY: "480px", 
    scrollCollapse: true, 
    paging: true, 
    pageLength: 20, 
    order: [] 
});

function search_data(){   
    var f = document.getElementById("func").value;
    var kw = document.getElementById("search_kw").value; 
    window.location.assign('packing_list_process_mats.php?func='+encodeURIComponent(f)+'&search='+encodeURIComponent(kw)); 
}
</script>
</html>