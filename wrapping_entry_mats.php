<?php
// เริ่ม session และเชื่อมต่อฐานข้อมูล
session_start();
include("dbcon_mats-new.php");

$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$user_operator = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : 'SYSTEM';
$jno = isset($_GET['jno']) ? trim($_GET['jno']) : '';

// ดึงข้อมูล Job Order และ Sale Order
$data = [];
if (!empty($jno)) {
    $sql = "SELECT A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MATERIAL_IN, A.PRODUCT_ID, A.ALLOY, 
                   A.JOB_RELEASEWEIGHT, A.JOB_RELEASEPIECE, A.JOB_PRODUCEWEIGHT, A.JOB_PRODUCEPIECE, A.JOB_WORKPROCESS, A.JOB_STATUS,
                   B.CSTMSPPL_ID, B.CTM2_ORDERWEIGHT, B.CTM2_ORDERPIECE, B.CTM2_PRODUCEWEIGHT, B.CTM2_PRODUCEPIECE 
            FROM JOBORDER1 AS A 
            LEFT JOIN CSTMORDR2 AS B ON B.SALEORDER_NO = A.SALEORDER_NO AND B.SALEORDER_ITEM = A.SALEORDER_ITEM 
            WHERE A.JOB_ORDER = :jno";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':jno' => $jno]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', Tahoma, 'Sarabun', sans-serif; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px !important; }
        .card-custom { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .section-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        .info-group { margin-bottom: 14px; }
        .info-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .info-value-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 14px; font-weight: 600; color: #1e293b; min-height: 38px; display: flex; align-items: center; }
        .form-control-input { border-radius: 8px; border: 1px solid #cbd5e1; height: 40px; font-size: 15px; font-weight: 600; color: #1e293b; padding: 6px 12px; }
        .form-control-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); outline: none; }
        .badge-status { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; padding: 4px 10px; border-radius: 6px; font-size: 12px; display: inline-block; }
        .badge-ac { background-color: #dcfce7; color: #166534; }
        .btn-action-save { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 10px 24px; border-radius: 8px; border: none; transition: all 0.2s; cursor: pointer; }
        .btn-action-save:hover { background-color: #1d4ed8; transform: translateY(-1px); }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 600; padding: 10px 20px; border-radius: 8px; border: none; text-decoration: none; display: inline-block; }
        .btn-back:hover { color: #fff; background-color: #475569; text-decoration: none; }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'Wrapping';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Wrapping Production Entry (Line <?php echo $_SESSION['LINE'] ?? '1'; ?>)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <div class="card-custom">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="margin:0; font-weight: 700; color: #1e293b; font-size: 18px;">📋 บันทึกข้อมูลกระบวนการห่อ (Wrapping Entry)</h4>
                    <a href="wrapping_process_mats.php?func=<?php echo $folder_func; ?>" class="btn-back">⬅ ย้อนกลับ</a>
                </div>

                <form id="wrappingForm" method="POST" action="model/wrapping_save_action.php">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                    <input type="hidden" name="saleorder_no" value="<?php echo htmlspecialchars($data['SALEORDER_NO'] ?? ''); ?>">
                    <input type="hidden" name="saleorder_item" value="<?php echo htmlspecialchars($data['SALEORDER_ITEM'] ?? ''); ?>">
                    <input type="hidden" name="alloy" value="<?php echo htmlspecialchars($data['ALLOY'] ?? ''); ?>">
                    <input type="hidden" name="material_in" value="<?php echo htmlspecialchars($data['MATERIAL_IN'] ?? ''); ?>">
                    <input type="hidden" name="work_process" value="<?php echo htmlspecialchars($data['JOB_WORKPROCESS'] ?? ''); ?>">

                    <!-- ส่วนที่ 1: ข้อมูลหลัก (Identifiers) -->
                    <div class="section-title">🔍 รายละเอียดหมายเลขผลิตและใบสั่งงาน</div>
                    <div class="row">
                        <div class="col-md-5 info-group">
                            <div class="info-label">Wrap Product No.</div>
                            <div class="info-value-box" style="background: #eff6ff; color: #1d4ed8; font-weight: 700;">
                                📦 <em>(ระบบจะสร้างรหัสอัตโนมัติปลอดภัยเมื่อกดบันทึก)</em>
                            </div>
                        </div>
                        <div class="col-md-3 info-group">
                            <div class="info-label">Status / Product ID</div>
                            <div style="display: flex; gap: 6px; align-items: center; height: 38px;">
                                <span class="badge-status badge-ac">AC: ACCEPT</span>
                                <span class="badge-status">WR</span>
                            </div>
                        </div>
                        <div class="col-md-4 info-group">
                            <div class="info-label">Job Order</div>
                            <div class="info-value-box" style="background: #fef3c7; color: #92400e;">
                                🏷️ <?php echo htmlspecialchars($jno); ?>
                                <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($jno); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: รายละเอียดออเดอร์และวัสดุ -->
                    <div class="section-title" style="margin-top: 10px;">📑 ข้อมูลคำสั่งซื้อและวัสดุ</div>
                    <div class="row">
                        <div class="col-md-4 info-group">
                            <div class="info-label">Sale Order No. / Item</div>
                            <div class="info-value-box">
                                <?php echo htmlspecialchars(($data['SALEORDER_NO'] ?? '-') . ' / ' . ($data['SALEORDER_ITEM'] ?? '-')); ?>
                            </div>
                        </div>
                        <div class="col-md-4 info-group">
                            <div class="info-label">Customer</div>
                            <div class="info-value-box"><?php echo htmlspecialchars($data['CSTMSPPL_ID'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4 info-group">
                            <div class="info-label">Material In</div>
                            <div class="info-value-box"><?php echo htmlspecialchars($data['MATERIAL_IN'] ?? '-'); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 info-group">
                            <div class="info-label">Alloy</div>
                            <div class="info-value-box"><?php echo htmlspecialchars($data['ALLOY'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4 info-group">
                            <div class="info-label">Work Process</div>
                            <div class="info-value-box" style="color: #d97706; font-weight: 700;"><?php echo htmlspecialchars($data['JOB_WORKPROCESS'] ?? '-'); ?></div>
                        </div>
                        <div class="col-md-4 info-group">
                            <div class="info-label">Job Status</div>
                            <div class="info-value-box"><span class="badge-status">OP: OPEN</span></div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 3: สรุปน้ำหนักและจำนวนชิ้น -->
                    <div class="section-title" style="margin-top: 10px;">⚖️ สรุปน้ำหนักและจำนวนชิ้น (Weight & Piece)</div>
                    <div class="row">
                        <div class="col-md-6 info-group">
                            <div class="info-label">Order (Weight - Pc.)</div>
                            <div style="display: flex; gap: 10px;">
                                <div class="info-value-box" style="flex: 2; justify-content: flex-end;"><?php echo number_format((float)($data['CTM2_ORDERWEIGHT'] ?? 0), 0); ?> kg</div>
                                <div class="info-value-box" style="flex: 1; justify-content: center;"><?php echo number_format((float)($data['CTM2_ORDERPIECE'] ?? 0), 0); ?> pcs</div>
                            </div>
                        </div>
                        <div class="col-md-6 info-group">
                            <div class="info-label">Job Release (Weight - Pc.)</div>
                            <div style="display: flex; gap: 10px;">
                                <div class="info-value-box" style="flex: 2; justify-content: flex-end;"><?php echo number_format((float)($data['JOB_RELEASEWEIGHT'] ?? 0), 0); ?> kg</div>
                                <div class="info-value-box" style="flex: 1; justify-content: center;"><?php echo number_format((float)($data['JOB_RELEASEPIECE'] ?? 0), 0); ?> pcs</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 info-group">
                            <div class="info-label">Current Produce (Weight - Pc.)</div>
                            <div style="display: flex; gap: 10px;">
                                <div class="info-value-box" style="flex: 2; justify-content: flex-end; color: #059669; font-weight: 700;"><?php echo number_format((float)($data['CTM2_PRODUCEWEIGHT'] ?? 0), 0); ?> kg</div>
                                <div class="info-value-box" style="flex: 1; justify-content: center; color: #059669; font-weight: 700;"><?php echo number_format((float)($data['CTM2_PRODUCEPIECE'] ?? 0), 0); ?> pcs</div>
                            </div>
                        </div>
                        <div class="col-md-6 info-group">
                            <div class="info-label">Job Produce (Weight - Pc.)</div>
                            <div style="display: flex; gap: 10px;">
                                <div class="info-value-box" style="flex: 2; justify-content: flex-end; color: #059669; font-weight: 700;"><?php echo number_format((float)($data['JOB_PRODUCEWEIGHT'] ?? 0), 0); ?> kg</div>
                                <div class="info-value-box" style="flex: 1; justify-content: center; color: #059669; font-weight: 700;"><?php echo number_format((float)($data['JOB_PRODUCEPIECE'] ?? 0), 0); ?> pcs</div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 4: ฟอร์มกรอกข้อมูลการผลิตจริง -->
                    <div class="section-title" style="margin-top: 15px; color: #2563eb;">✍️ บันทึกข้อมูลการผลิตจริง (Actual Data Input)</div>
                    <div class="row">
                        <div class="col-md-6 info-group">
                            <label class="info-label" style="color: #2563eb;">Produce Weight (kg) *</label>
                            <input type="number" step="any" class="form-control form-control-input" name="produce_weight" id="produce_weight" required placeholder="0">
                        </div>
                        <div class="col-md-6 info-group">
                            <label class="info-label" style="color: #2563eb;">Pallet / Bottom Weight (kg)</label>
                            <input type="number" step="any" class="form-control form-control-input" name="bottom_weight" id="bottom_weight" value="0">
                        </div>
                    </div>

                    <!-- ปุ่มบันทึก -->
                    <div style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                        <button type="submit" name="action_save" value="save" class="btn-action-save">💾 บันทึกข้อมูลการผลิตและพิมพ์ป้าย Label</button>
                    </div>
                </form>
            </div>
        </div>
        <?php include 'include/content-footer.php';?>
   </div>
</div>
</body>
<?php include 'include/footer.php';?>
</html>