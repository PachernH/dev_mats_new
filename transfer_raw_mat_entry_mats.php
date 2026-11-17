<?php
session_start();

if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : 'mats';
$job_order   = isset($_GET['job']) ? htmlspecialchars(trim($_GET['job']), ENT_QUOTES, 'UTF-8') : '';

if (empty($job_order)) {
    header("Location: transfer_raw_mat_process_mats.php");
    exit();
}

include("dbcon_mats-new.php");

$sql_job = "SELECT A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MATERIAL_IN, A.PRODUCT_ID, A.ALLOY, 
                   A.JOB_RELEASEWEIGHT, A.JOB_RELEASEPIECE, A.JOB_PRODUCEWEIGHT, A.JOB_PRODUCEPIECE, 
                   A.JOB_WORKPROCESS, A.JOB_STATUS, 
                   B.CTM2_PONO, B.CTM2_POITEM, B.CSTMSPPL_ID, B.CTM2_ORDERWEIGHT, B.CTM2_ORDERPIECE, 
                   B.CTM2_PRODUCEWEIGHT, B.CTM2_PRODUCEPIECE 
            FROM JOBORDER1 AS A 
            INNER JOIN CSTMORDR2 AS B ON B.SALEORDER_NO = A.SALEORDER_NO AND B.SALEORDER_ITEM = A.SALEORDER_ITEM 
            WHERE A.JOB_ORDER = :job";
$stmt_job = $conn->prepare($sql_job);
$stmt_job->execute([':job' => $job_order]);
$jobData = $stmt_job->fetch(PDO::FETCH_ASSOC);

if (!$jobData || $jobData['JOB_STATUS'] !== 'OP') {
    echo "<script>alert('ไม่พบข้อมูล Job Order หรือสถานะไม่ใช่ OP (OPEN)'); window.location.href='transfer_raw_mat_process_mats.php';</script>";
    exit();
}

$sql_lookup = "SELECT PRODUCT_NO, PRODUCT_REFERENCE, COIL_NO, SALEORDER_NO, SALEORDER_ITEM, JOB_ORDER, 
                      REQUISITION_NO, MP2_PARTNO, SERIAL_NO, CRSH_PRODUCEWEIGHT, CRSH_BOTTOMWEIGHT, 
                      CRSH_LABEL, CRSH_STATUS 
               FROM CRSHPROD1 
               WHERE PRODUCT_NO LIKE '%-0-%' 
                 AND (PRODUCT_REFERENCE = '' OR PRODUCT_REFERENCE IS NULL) 
                 AND JOB_ORDER = :job 
               ORDER BY PRODUCT_NO DESC";
$stmt_lookup = $conn->prepare($sql_lookup);
$stmt_lookup->execute([':job' => $job_order]);
$lookupProducts = $stmt_lookup->fetchAll(PDO::FETCH_ASSOC);

$acProducts = array_filter($lookupProducts, function($item) {
    return ($item['CRSH_STATUS'] === 'AC');
});
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - Transfer Raw Material Entry</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .dashboard-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card-header-title { font-size: 16px; font-weight: 700; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; }
        
        .info-field-group { margin-bottom: 15px; }
        .info-field-group label { font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.3px; }
        .info-display-box { height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; background-color: #f8fafc; font-size: 14px; font-weight: 600; color: #1e293b; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; }
        .info-display-box.highlight { background-color: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .info-display-box.success-highlight { background-color: #f0fdf4; border-color: #bbf7d0; color: #047857; font-size: 15px; }

        .form-group label { font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; }
        .form-control-custom { height: 42px; border-radius: 8px; border: 1.5px solid #cbd5e1; font-size: 14.5px; font-weight: 600; color: #1e293b; padding: 0 12px; transition: 0.2s; }
        .form-control-custom:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
        .form-control-custom[readonly] { background-color: #f1f5f9; color: #475569; cursor: not-allowed; }
        .input-weight { font-size: 18px; color: #1d4ed8; text-align: right; }

        .btn-save { background-color: #10b981; color: #ffffff; font-weight: 700; font-size: 14px; padding: 10px 28px; border-radius: 8px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background-color: #059669; }
        .btn-cancel { background-color: #ef4444; color: #ffffff; font-weight: 700; font-size: 14px; padding: 10px 24px; border-radius: 8px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background-color: #dc2626; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 600; font-size: 13px; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; }
        .btn-back:hover { background-color: #475569; color: #fff; }
        
        .btn-edit-row { background-color: #f59e0b; color: #fff; font-weight: 600; font-size: 12px; padding: 5px 12px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-edit-row:hover { background-color: #d97706; }
        .btn-lookup { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 0 14px; border-radius: 8px; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; }
        .btn-lookup:hover { background-color: #1d4ed8; }

        .badge-mode-new { background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid #86efac; }
        .badge-mode-old { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid #fcd34d; }
    </style>
</head>
<body>
<div class="wrapper">
<?php $menu = 'A4'; include 'include/'.$folder_func.'/navigation.php';?>
   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="#">
                        Raw Material Entry (Job: <?php echo $job_order; ?>)
                    </a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            
            <div class="dashboard-card">
                <div class="card-header-title">
                    <span>📌 Job Order & Sale Order Information</span>
                    <a href="transfer_raw_mat_process_mats.php?func=<?php echo $folder_func; ?>" class="btn-back">⬅️ BACK TO LIST</a>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Job Order / Status</label>
                            <div class="info-display-box highlight">
                                <span><?php echo $jobData['JOB_ORDER']; ?></span>
                                <span class="label label-success" style="background:#10b981; font-size:11px; padding:3px 8px;"><?php echo $jobData['JOB_STATUS']; ?> - <?php echo $jobData['PRODUCT_ID']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Sale Order / Item</label>
                            <div class="info-display-box">
                                <span><?php echo $jobData['SALEORDER_NO'] . ' / ' . $jobData['SALEORDER_ITEM']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>PO No. / Item</label>
                            <div class="info-display-box">
                                <span><?php echo ($jobData['CTM2_PONO'] ?: '-') . ' / ' . ($jobData['CTM2_POITEM'] ?: '-'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Customer</label>
                            <div class="info-display-box">
                                <span><?php echo $jobData['CSTMSPPL_ID'] ?: '-'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 5px;">
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Material In / Alloy</label>
                            <div class="info-display-box">
                                <span><?php echo ($jobData['MATERIAL_IN'] ?: '-') . ' / ' . ($jobData['ALLOY'] ?: '-'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Work Process</label>
                            <div class="info-display-box" style="color:#d97706; font-weight:700;">
                                <span><?php echo $jobData['JOB_WORKPROCESS'] ?: '-'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Order (Wt. - Pc.)</label>
                            <div class="info-display-box">
                                <span><?php echo number_format($jobData['CTM2_ORDERWEIGHT'], 2) . ' kg / ' . number_format($jobData['CTM2_ORDERPIECE'], 0) . ' pc'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-field-group">
                            <label>Order Produce (Wt. - Pc.)</label>
                            <div class="info-display-box highlight" id="disp_ctm2_produce">
                                <span><?php echo number_format($jobData['CTM2_PRODUCEWEIGHT'], 2) . ' kg / ' . number_format($jobData['CTM2_PRODUCEPIECE'], 0) . ' pc'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 5px;">
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <label>Job Release (Wt. - Pc.)</label>
                            <div class="info-display-box">
                                <span><?php echo number_format($jobData['JOB_RELEASEWEIGHT'], 2) . ' kg / ' . number_format($jobData['JOB_RELEASEPIECE'], 0) . ' pc'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-field-group">
                            <label>Job Produce (Wt. - Pc. สะสมปัจจุบัน)</label>
                            <div class="info-display-box success-highlight" id="disp_job_produce">
                                <span><?php echo number_format($jobData['JOB_PRODUCEWEIGHT'], 2) . ' kg / ' . number_format($jobData['JOB_PRODUCEPIECE'], 0) . ' pc'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header-title">
                    <div>
                        <span>📝 บันทึกข้อมูล Raw Material Product</span>
                        <span id="badge_record_status" class="badge-mode-new" style="margin-left: 10px;">New Record</span>
                    </div>
                </div>

                <form id="frm_rw_entry">
                    <input type="hidden" id="record_status" name="record_status" value="New Record">
                    <input type="hidden" id="job_order" name="job_order" value="<?php echo $jobData['JOB_ORDER']; ?>">
                    <input type="hidden" id="saleorder_no" name="saleorder_no" value="<?php echo $jobData['SALEORDER_NO']; ?>">
                    <input type="hidden" id="saleorder_item" name="saleorder_item" value="<?php echo $jobData['SALEORDER_ITEM']; ?>">
                    <input type="hidden" id="po_no" name="po_no" value="<?php echo $jobData['CTM2_PONO']; ?>">
                    <input type="hidden" id="po_item" name="po_item" value="<?php echo $jobData['CTM2_POITEM']; ?>">
                    <input type="hidden" id="material_in" name="material_in" value="<?php echo $jobData['MATERIAL_IN']; ?>">
                    <input type="hidden" id="alloy" name="alloy" value="<?php echo $jobData['ALLOY']; ?>">
                    <input type="hidden" id="work_process" name="work_process" value="<?php echo $jobData['JOB_WORKPROCESS']; ?>">
                    <input type="hidden" id="next_process" name="next_process" value="IS">
                    <input type="hidden" id="product_id" name="product_id" value="RM">
                    <input type="hidden" id="crsh_status" name="crsh_status" value="AC">
                    
                    <input type="hidden" id="base_job_wt" value="<?php echo (float)$jobData['JOB_PRODUCEWEIGHT']; ?>">
                    <input type="hidden" id="base_job_pc" value="<?php echo (float)$jobData['JOB_PRODUCEPIECE']; ?>">
                    <input type="hidden" id="base_ctm2_wt" value="<?php echo (float)$jobData['CTM2_PRODUCEWEIGHT']; ?>">
                    <input type="hidden" id="base_ctm2_pc" value="<?php echo (float)$jobData['CTM2_PRODUCEPIECE']; ?>">
                    <input type="hidden" id="old_produce_wt" value="0">
                    <input type="hidden" id="old_label_count" value="0">

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Raw Material Product No.</label>
                                <div style="display: flex; gap: 6px;">
                                    <input type="text" class="form-control form-control-custom" id="product_no" name="product_no" placeholder="Auto Generate (R...)" readonly>
                                    <button type="button" class="btn-lookup" id="btn_old_lookup" onclick="openLookupModal()" title="Look Up Old Product (CRSHPROD1)">🔍</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Requisition No.</label>
                                <input type="text" class="form-control form-control-custom" id="requisition_no" name="requisition_no" maxlength="15" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>MP2 Part No.</label>
                                <input type="text" class="form-control form-control-custom" id="mp2_partno" name="mp2_partno" maxlength="15" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Serial No.</label>
                                <input type="text" class="form-control form-control-custom" id="serial_no" name="serial_no" maxlength="20" oninput="this.value = this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 10px;">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Produce Weight (kg) <span style="color:red;">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom input-weight" id="produce_weight" name="produce_weight" value="0.00" onfocus="this.select()" onchange="calculateWeights()" onkeyup="calculateWeights()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Pallet / Bottom Weight (kg)</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-custom input-weight" id="bottom_weight" name="bottom_weight" value="0.00" onfocus="this.select()" onchange="formatBottomWeight()">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Label Size & Print Option</label>
                                <div style="display: flex; align-items: center; gap: 12px; height: 42px;">
                                    <label style="margin:0; cursor:pointer; display:flex; align-items:center; gap:6px;">
                                        <input type="checkbox" id="chk_print_label" name="chk_print_label" value="1" checked style="width:18px; height:18px;"> Print Label
                                    </label>
                                    <select class="form-control form-control-custom" id="label_size" name="label_size" style="width: 110px;">
                                        <option value="21x15">21x15</option>
                                        <option value="10x15">10x15</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Status / Label Count</label>
                                <div style="display: flex; align-items: center; gap: 10px; height: 42px;">
                                    <span id="disp_status_badge" class="label label-info" style="font-size: 13px; padding: 6px 12px; background:#0284c7;">Status: AC</span>
                                    <span id="disp_label_count" class="label label-default" style="font-size: 13px; padding: 6px 12px;">Printed: 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 16px; display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" class="btn-cancel" onclick="resetToNewRecord()">❌ ยกเลิก (Cancel)</button>
                        <button type="button" class="btn-save" id="btn_save" onclick="saveData()">💾 บันทึกข้อมูล (SAVE)</button>
                    </div>
                </form>
            </div>

            <div class="dashboard-card">
                <div class="card-header-title">
                    <span>📦 รายการ Raw Material ใน Job นี้ (สถานะ AC - สามารถกดแก้ไขได้)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="existing_table">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="text-align: center;">Product No.</th>
                                <th style="text-align: center;">Requisition No.</th>
                                <th style="text-align: center;">MP2 Part No.</th>
                                <th style="text-align: center;">Serial No.</th>
                                <th style="text-align: right;">Produce Weight</th>
                                <th style="text-align: right;">Bottom Weight</th>
                                <th style="text-align: center;">Label Count</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($acProducts) > 0): ?>
                                <?php foreach ($acProducts as $prod): ?>
                                    <tr>
                                        <td align="center" style="font-weight:700; color:#1d4ed8;"><?php echo htmlspecialchars($prod['PRODUCT_NO']); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['REQUISITION_NO'] ?: '-'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['MP2_PARTNO'] ?: '-'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['SERIAL_NO'] ?: '-'); ?></td>
                                        <td align="right" style="font-weight:700;"><?php echo number_format((float)$prod['CRSH_PRODUCEWEIGHT'], 2); ?></td>
                                        <td align="right"><?php echo number_format((float)$prod['CRSH_BOTTOMWEIGHT'], 2); ?></td>
                                        <td align="center"><?php echo (int)$prod['CRSH_LABEL']; ?></td>
                                        <td align="center"><span class="label label-warning" style="background:#f59e0b;"><?php echo htmlspecialchars($prod['CRSH_STATUS']); ?></span></td>
                                        <td align="center">
                                            <button type="button" class="btn-edit-row" onclick='editProduct(<?php echo json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                ✏️ เลือกแก้ไข
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" align="center" style="color:#94a3b8; padding: 20px;">ยังไม่มีรายการที่รอคีย์น้ำหนัก (สถานะ AC) ใน Job Order นี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <input type="hidden" id="func" value="<?php echo $folder_func;?>">
        <?php include 'include/content-footer.php';?>
   </div>
</div>

<div class="modal fade" id="lookupModal" tabindex="-1" role="dialog" aria-labelledby="lookupModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width: 90%; max-width: 1050px;">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #1e293b; color: #fff; border-top-left-radius: 12px; border-top-right-radius: 12px; padding: 15px 20px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="lookupModalLabel" style="font-weight: 700; font-size: 16px;">🔍 Look Up Data: CRSHPROD1 (Job Order: <?php echo $job_order; ?>)</h4>
            </div>
            <div class="modal-body" style="padding: 20px; background: #f8fafc;">
                <div style="background: #e2e8f0; padding: 10px 15px; border-radius: 6px; font-family: monospace; font-size: 12px; color: #334155; margin-bottom: 15px;">
                    <strong>Condition:</strong> WHERE PRODUCT_NO LIKE '%-0-%' AND PRODUCT_REFERENCE = '' AND JOB_ORDER = '<?php echo $job_order; ?>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <table class="table table-hover" id="lookup_table" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 1;">
                                <th style="text-align: center;">Product No.</th>
                                <th style="text-align: center;">Sale Order / Item</th>
                                <th style="text-align: center;">Job Order</th>
                                <th style="text-align: center;">Requisition No.</th>
                                <th style="text-align: center;">MP2 Part No.</th>
                                <th style="text-align: center;">Serial No.</th>
                                <th style="text-align: right;">Produce Weight</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center; width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lookupProducts) > 0): ?>
                                <?php foreach ($lookupProducts as $prod): ?>
                                    <tr>
                                        <td align="center" style="font-weight: 700; color: #1d4ed8;"><?php echo htmlspecialchars($prod['PRODUCT_NO']); ?></td>
                                        <td align="center"><?php echo htmlspecialchars(($prod['SALEORDER_NO'] ?? '') . ' / ' . ($prod['SALEORDER_ITEM'] ?? '')); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['JOB_ORDER'] ?? ''); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['REQUISITION_NO'] ?: '-'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['MP2_PARTNO'] ?: '-'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($prod['SERIAL_NO'] ?: '-'); ?></td>
                                        <td align="right" style="font-weight: 700;"><?php echo number_format((float)$prod['CRSH_PRODUCEWEIGHT'], 2); ?></td>
                                        <td align="center">
                                            <?php if ($prod['CRSH_STATUS'] === 'AC'): ?>
                                                <span class="label label-success" style="background:#10b981;">AC</span>
                                            <?php else: ?>
                                                <span class="label label-default"><?php echo htmlspecialchars($prod['CRSH_STATUS']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td align="center">
                                            <button type="button" class="btn btn-xs" style="background: #2563eb; color: #fff; font-weight: 600; padding: 4px 10px; border-radius: 4px;" onclick='selectLookupProduct(<?php echo json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                Select
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" align="center" style="color:#94a3b8; padding: 20px;">ไม่พบข้อมูลตามเงื่อนไขใน Job Order นี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="background: #ffffff; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; padding: 12px 20px;">
                <button type="button" class="btn btn-default" data-dismiss="modal" style="font-weight: 600; border-radius: 6px;">Cancel</button>
            </div>
        </div>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>
<script type="text/javascript">
function openLookupModal() {
    $('#lookupModal').modal('show');
}

function selectLookupProduct(prod) {
    // ตรวจสอบเงื่อนไขสถานะ AC ตาม GetProduct 
    if (prod.CRSH_STATUS !== 'AC') {
        Swal.fire({
            title: 'BCRSH-06',
            text: 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' + prod.CRSH_STATUS + ' ไม่เท่ากับสถานะภาพ : AC: ACCEPT',
            icon: 'error',
            confirmButtonText: 'ตกลง'
        });
        return;
    }
    editProduct(prod);
    $('#lookupModal').modal('hide');
}

function calculateWeights() {
    let recordStatus = document.getElementById('record_status').value;
    let produceWt = parseFloat(document.getElementById('produce_weight').value) || 0;
    
    if (produceWt < 0) {
        produceWt = 0;
        document.getElementById('produce_weight').value = "0.00";
    }

    let baseJobWt = parseFloat(document.getElementById('base_job_wt').value) || 0;
    let baseJobPc = parseFloat(document.getElementById('base_job_pc').value) || 0;
    let baseCtm2Wt = parseFloat(document.getElementById('base_ctm2_wt').value) || 0;
    let baseCtm2Pc = parseFloat(document.getElementById('base_ctm2_pc').value) || 0;
    let oldProduceWt = parseFloat(document.getElementById('old_produce_wt').value) || 0;

    let newJobWt = baseJobWt;
    let newJobPc = baseJobPc;
    let newCtm2Wt = baseCtm2Wt;
    let newCtm2Pc = baseCtm2Pc;

    if (recordStatus === 'New Record') {
        if (produceWt > 0) {
            newJobWt = baseJobWt + produceWt;
            newJobPc = baseJobPc + 1;
            newCtm2Wt = baseCtm2Wt + produceWt;
            newCtm2Pc = baseCtm2Pc + 1;
        }
    } else {
        let diffWt = produceWt - oldProduceWt;
        newJobWt = baseJobWt + diffWt;
        newCtm2Wt = baseCtm2Wt + diffWt;
    }

    let status = (produceWt > 0) ? 'ST' : 'AC';
    document.getElementById('crsh_status').value = status;
    
    let badgeStatus = document.getElementById('disp_status_badge');
    badgeStatus.innerText = 'Status: ' + status;
    badgeStatus.style.background = (status === 'ST') ? '#10b981' : '#0284c7';

    document.getElementById('disp_job_produce').innerHTML = '<span>' + newJobWt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg / ' + newJobPc + ' pc</span>';
    document.getElementById('disp_ctm2_produce').innerHTML = '<span>' + newCtm2Wt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg / ' + newCtm2Pc + ' pc</span>';
}

function formatBottomWeight() {
    let val = parseFloat(document.getElementById('bottom_weight').value) || 0;
    if (val < 0) val = 0;
    document.getElementById('bottom_weight').value = val.toFixed(2);
}

function editProduct(prod) {
    if (prod.CRSH_STATUS !== 'AC') {
        Swal.fire({
            title: 'BCRSH-06',
            text: 'ผลิตภัณฑ์มีสถานะภาพเป็น : ' + prod.CRSH_STATUS + ' ไม่เท่ากับสถานะภาพ : AC: ACCEPT',
            icon: 'error',
            confirmButtonText: 'ตกลง'
        });
        return;
    }

    document.getElementById('record_status').value = 'Old Record';
    let badge = document.getElementById('badge_record_status');
    badge.className = 'badge-mode-old';
    badge.innerText = 'Old Record (กำลังแก้ไข: ' + prod.PRODUCT_NO + ')';

    document.getElementById('product_no').value = prod.PRODUCT_NO;
    document.getElementById('requisition_no').value = prod.REQUISITION_NO || '';
    document.getElementById('mp2_partno').value = prod.MP2_PARTNO || '';
    document.getElementById('serial_no').value = prod.SERIAL_NO || '';
    
    let pWt = parseFloat(prod.CRSH_PRODUCEWEIGHT) || 0;
    let bWt = parseFloat(prod.CRSH_BOTTOMWEIGHT) || 0;
    let lblCount = parseInt(prod.CRSH_LABEL) || 0;

    document.getElementById('produce_weight').value = pWt.toFixed(2);
    document.getElementById('bottom_weight').value = bWt.toFixed(2);
    document.getElementById('old_produce_wt').value = pWt;
    document.getElementById('old_label_count').value = lblCount;
    document.getElementById('disp_label_count').innerText = 'Printed: ' + lblCount;

    document.getElementById('requisition_no').readOnly = true;
    document.getElementById('mp2_partno').readOnly = true;
    document.getElementById('serial_no').readOnly = true;
    document.getElementById('btn_old_lookup').disabled = true;

    calculateWeights();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetToNewRecord() {
    document.getElementById('record_status').value = 'New Record';
    let badge = document.getElementById('badge_record_status');
    badge.className = 'badge-mode-new';
    badge.innerText = 'New Record';

    document.getElementById('product_no').value = '';
    document.getElementById('requisition_no').value = '';
    document.getElementById('mp2_partno').value = '';
    document.getElementById('serial_no').value = '';
    
    document.getElementById('requisition_no').readOnly = false;
    document.getElementById('mp2_partno').readOnly = false;
    document.getElementById('serial_no').readOnly = false;
    document.getElementById('btn_old_lookup').disabled = false;

    document.getElementById('produce_weight').value = '0.00';
    document.getElementById('bottom_weight').value = '0.00';
    document.getElementById('old_produce_wt').value = '0';
    document.getElementById('old_label_count').value = '0';
    document.getElementById('disp_label_count').innerText = 'Printed: 0';

    calculateWeights();
}

function saveData() {
    let recordStatus = document.getElementById('record_status').value;
    let productNo = document.getElementById('product_no').value;

    // ตรวจสอบเงื่อนไขตาม ถือว่าเป็น Old Record แต่ไม่มี Product No. ให้แจ้งเตือนทันที
    if (recordStatus === 'Old Record' && !productNo) {
        Swal.fire('BCRSH-06', 'กรุณาเลือก Raw Material Product No. (สถานะ AC) ก่อนทำการบันทึก', 'error');
        return;
    }

    let produceWt = parseFloat(document.getElementById('produce_weight').value) || 0;
    let bottomWt = parseFloat(document.getElementById('bottom_weight').value) || 0;
    
    if (produceWt < 0 || bottomWt < 0) {
        Swal.fire('ข้อผิดพลาด', 'น้ำหนักต้องไม่ติดลบ', 'warning');
        return;
    }

    let payload = {
        record_status: recordStatus,
        product_no: productNo,
        job_order: document.getElementById('job_order').value,
        saleorder_no: document.getElementById('saleorder_no').value,
        saleorder_item: document.getElementById('saleorder_item').value,
        po_no: document.getElementById('po_no').value,
        po_item: document.getElementById('po_item').value,
        material_in: document.getElementById('material_in').value,
        alloy: document.getElementById('alloy').value,
        work_process: document.getElementById('work_process').value,
        next_process: document.getElementById('next_process').value,
        product_id: document.getElementById('product_id').value,
        requisition_no: document.getElementById('requisition_no').value.trim(),
        mp2_partno: document.getElementById('mp2_partno').value.trim(),
        serial_no: document.getElementById('serial_no').value.trim(),
        produce_weight: produceWt,
        bottom_weight: bottomWt,
        crsh_status: document.getElementById('crsh_status').value,
        chk_print_label: document.getElementById('chk_print_label').checked ? 1 : 0,
        label_size: document.getElementById('label_size').value,
        old_produce_wt: parseFloat(document.getElementById('old_produce_wt').value) || 0,
        old_label_count: parseInt(document.getElementById('old_label_count').value) || 0
    };

    Swal.fire({
        title: 'ยืนยันการบันทึกข้อมูล?',
        text: "Product No: " + (payload.product_no || 'สร้างรหัสใหม่อัตโนมัติ') + " | น้ำหนัก: " + produceWt.toFixed(2) + " kg",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'ยืนยันบันทึก',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังบันทึกข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch('model/save_transfer_raw_mat_mats.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'บันทึกสำเร็จ!',
                        text: 'รหัส Product No: ' + data.product_no,
                        icon: 'success',
                        confirmButtonText: 'ตกลง'
                    }).then(() => {
                        if (payload.chk_print_label === 1 && data.print_url) {
                            window.open(data.print_url, '_blank');
                        }
                        location.reload();
                    });
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            })
            .catch(err => {
                Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err, 'error');
            });
        }
    });
}
</script>
</html>