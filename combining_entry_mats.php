<?php
session_start();
include("dbcon_mats-new.php");

$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$jno = isset($_GET['jno']) ? trim($_GET['jno']) : '';
$edit_pno = isset($_GET['pno']) ? trim($_GET['pno']) : '';

if (empty($jno)) {
    header("Location: combining_process_mats.php?func=" . $folder_func);
    exit();
}

$sql = "SELECT A.JOB_ORDER, A.SALEORDER_NO, A.SALEORDER_ITEM, A.MATERIAL_IN, A.PRODUCT_ID, A.ALLOY, 
               A.JOB_RELEASEWEIGHT, A.JOB_RELEASEPIECE, A.JOB_PRODUCEWEIGHT, A.JOB_PRODUCEPIECE, 
               A.JOB_PACKWEIGHT, A.JOB_PACKPIECE, A.JOB_STOCKWEIGHT, A.JOB_STOCKPIECE, A.JOB_WORKPROCESS, A.JOB_STATUS,
               B.CSTMSPPL_ID, B.CTM2_PONO, B.CTM2_POITEM, B.CTM2_ORDERWEIGHT, B.CTM2_ORDERPIECE, 
               B.CTM2_PRODUCEWEIGHT, B.CTM2_PRODUCEPIECE, B.CTM2_PACKWEIGHT, B.CTM2_PACKPIECE, 
               B.CTM2_STOCKWEIGHT, B.CTM2_STOCKPIECE 
        FROM JOBORDER1 AS A 
        LEFT JOIN CSTMORDR2 AS B ON B.SALEORDER_NO = A.SALEORDER_NO AND B.SALEORDER_ITEM = A.SALEORDER_ITEM 
        WHERE A.JOB_ORDER = :jno";
$stmt = $conn->prepare($sql);
$stmt->execute([':jno' => $jno]);
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$job_alloy = $data['ALLOY'] ?? '';
$job_accumulate_weight = floatval($data['JOB_PRODUCEWEIGHT'] ?? 0);

// ถ้ามี pno ส่งมาแปลว่ากำลัง Add รายการเพิ่มใน Product No. เดิม ถ้าไม่มีจะเป็นค่าว่างเพื่อให้ไป Gen ตอนกด Save
$current_product_no = !empty($edit_pno) ? $edit_pno : '';

// ดึงรายการ Scrap ที่พร้อมเลือก (สถานะ OP และ Alloy ตรงกัน เรียงจากใหม่ไปเก่า)
$sql_scrap = "SELECT PRODUCT_NO, COIL_NO, ALLOY, SCRP_WEIGHT, MATERIAL_IN 
              FROM SCRPPROD1 
              WHERE SCRP_STATUS = 'OP' AND Alloy = :alloy 
              ORDER BY PRODUCT_NO DESC";
$stmt_scrap = $conn->prepare($sql_scrap);
$stmt_scrap->execute([':alloy' => $job_alloy]);
$scrap_list = $stmt_scrap->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายการที่ Combine แล้วใน Product No. นี้ (ถ้ามี)
$combined_items = [];
if (!empty($current_product_no)) {
    $sql_list = "SELECT M.PRODUCT_REFERENCE AS PRODUCT_REF, M.COIL_NO, M.CMBN_DATE, M.CMBN_WEIGHT, M.CMBN_OPERATOR 
                 FROM CMBNPROD1 AS M 
                 WHERE M.PRODUCT_NO = ?";
    $stmt_list = $conn->prepare($sql_list);
    $stmt_list->execute([$current_product_no]);
    $combined_items = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
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
        .card-custom { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 12px; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; }
        .info-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
        .info-value-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; font-size: 13px; font-weight: 600; color: #1e293b; min-height: 34px; display: flex; align-items: center; }
        .form-control-input { border-radius: 6px; border: 1px solid #cbd5e1; height: 36px; font-size: 13px; font-weight: 600; color: #1e293b; padding: 4px 10px; width: 100%; }
        .badge-status { background-color: #dbeafe; color: #1d4ed8; font-weight: 700; padding: 3px 8px; border-radius: 4px; font-size: 11px; display: inline-block; }
        .badge-ac { background-color: #dcfce7; color: #166534; }
        .btn-action-save { background-color: #2563eb; color: #ffffff; font-weight: 700; padding: 8px 20px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-action-save:hover { background-color: #1d4ed8; }
        .btn-lookup { background-color: #1e293b; color: #fff; border: none; border-radius: 6px; padding: 0 12px; font-weight: 600; font-size: 12px; height: 36px; cursor: pointer; }
        .btn-lookup:hover { background-color: #334155; }
        .btn-back { background-color: #64748b; color: #ffffff; font-weight: 600; padding: 8px 16px; border-radius: 6px; border: none; text-decoration: none; display: inline-block; }
        .btn-back:hover { color: #fff; background-color: #475569; text-decoration: none; }
        .grid-table th { background-color: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 8px; }
        .grid-table td { padding: 8px; font-size: 12.5px; border-bottom: 1px solid #f1f5f9; }
        .modal-lookup { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content-custom { background: #fff; border-radius: 12px; width: 700px; max-width: 90%; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
    </style>
</head>
<body>

<div class="wrapper">
<?php $menu = 'Combining';?>
<?php include 'include/'.$folder_func.'/navigation.php';?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 18px;" href="#">Combine Wrap Product Entry</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <form id="combiningForm" method="POST" action="model/combining_save_action.php">
                <input type="hidden" name="func" value="<?php echo $folder_func; ?>">
                <input type="hidden" name="job_order" value="<?php echo htmlspecialchars($jno); ?>">
                <input type="hidden" name="saleorder_no" value="<?php echo htmlspecialchars($data['SALEORDER_NO'] ?? ''); ?>">
                <input type="hidden" name="saleorder_item" value="<?php echo htmlspecialchars($data['SALEORDER_ITEM'] ?? ''); ?>">
                <input type="hidden" name="material_in" id="material_in_input" value="<?php echo htmlspecialchars($data['MATERIAL_IN'] ?? ''); ?>">
                <input type="hidden" name="alloy" id="job_alloy" value="<?php echo htmlspecialchars($job_alloy); ?>">
                <input type="hidden" name="work_process" value="<?php echo htmlspecialchars($data['JOB_WORKPROCESS'] ?? ''); ?>">
                <input type="hidden" id="base_accumulate_weight" value="<?php echo $job_accumulate_weight; ?>">
                <input type="hidden" name="product_no" value="<?php echo htmlspecialchars($current_product_no); ?>">

                <div class="row">
                    <div class="col-md-7">
                        <div class="card-custom">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin:0; font-weight: 700; color: #1e293b; font-size: 15px;">⚙️ Combining Waro Product Control</h4>
                                <a href="combining_process_mats.php?func=<?php echo $folder_func; ?>" class="btn-back">Back Combine Warp Product</a>
                            </div>

                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-6">
                                    <div class="info-label">Wrap Product No.</div>
                                    <div class="info-value-box" style="background: #eff6ff; color: #1d4ed8; font-weight: 700;">
                                        📦 <?php echo !empty($current_product_no) ? htmlspecialchars($current_product_no) : 'Auto Gen'; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Status / Product ID</div>
                                    <div style="display: flex; gap: 6px; align-items: center; height: 34px;">
                                        <span class="badge-status badge-ac" id="status_badge">AC</span>
                                        <span class="badge-status">WR</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-12">
                                    <label class="info-label" style="color: #2563eb;">Combine Scrap No. (txtCombine) * - Alloy: <?php echo htmlspecialchars($job_alloy); ?></label>
                                    <div style="display: flex; gap: 8px;">
                                        <input type="text" class="form-control form-control-input" name="combine_no" id="combine_no" required readonly placeholder="คลิกปุ่มเพื่อเลือก Scrap...">
                                        <button type="button" class="btn-lookup" onclick="openScrapModal()">Search</button>
                                    </div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-6">
                                    <div class="info-label">Coil No</div>
                                    <div class="info-value-box" id="coil_no_display">-</div>
                                    <input type="hidden" name="coil_no" id="coil_no_input">
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Material In</div>
                                    <div class="info-value-box" id="material_in_display"><?php echo htmlspecialchars($data['MATERIAL_IN'] ?? '-'); ?></div>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-6">
                                    <div class="info-label">Alloy</div>
                                    <div class="info-value-box"><?php echo htmlspecialchars($job_alloy); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Scrap Weight</div>
                                    <input type="number" step="any" class="form-control form-control-input" name="scrap_weight" id="scrap_weight" readonly style="background: #f1f5f9;">
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-md-6">
                                    <div class="info-label">Scrap accumulate Weight (น้ำหนักสะสม)</div>
                                    <input type="text" class="form-control form-control-input" id="scrap_accumulate_weight" readonly style="background: #f1f5f9; font-weight: bold; color: #059669;" value="<?php echo number_format($job_accumulate_weight, 0); ?>">
                                </div>
                                <div class="col-md-6">
                                    <div class="info-label">Pallet / Bottom Weight</div>
                                    <input type="number" step="any" class="form-control form-control-input" name="bottom_weight" id="bottom_weight" value="0" oninput="calculateNetWeight()">
                                </div>
                            </div>

                            <div class="section-title" style="color: #2563eb; margin-top: 15px;">📦 Packing Control</div>
                            <div class="row" style="margin-bottom: 15px;">
                                <div class="col-md-6">
                                    <label class="info-label">Gross Weight</label>
                                    <input type="number" step="any" class="form-control form-control-input" name="gross_weight" id="gross_weight" value="0" oninput="calculateNetWeight()">
                                </div>
                                <div class="col-md-6">
                                    <label class="info-label">Net Weight</label>
                                    <input type="number" step="any" class="form-control form-control-input" name="net_weight" id="net_weight" readonly style="background: #f1f5f9; font-weight: bold; color: #2563eb;" value="0">
                                </div>
                            </div>

                            <div style="border-top: 1px solid #e2e8f0; padding-top: 15px;">
                                <button type="submit" class="btn-action-save">💾 Add Scrap & Save Combine</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="card-custom">
                            <div class="section-title">📋 Sale Order and Job Order Info</div>
                            
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Job Order</div>
                                <div class="col-md-7 info-value-box" style="background: #fef3c7; color: #92400e; font-weight:700;"><?php echo htmlspecialchars($jno); ?></div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Material In - Alloy</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2;"><?php echo htmlspecialchars($data['MATERIAL_IN'] ?? '-'); ?></div>
                                    <div class="info-value-box" style="flex: 1;"><?php echo htmlspecialchars($job_alloy); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Job Release (Wt. - Pc.)</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2; justify-content: flex-end;"><?php echo number_format((float)($data['JOB_RELEASEWEIGHT'] ?? 0), 0); ?></div>
                                    <div class="info-value-box" style="flex: 1; justify-content: center;"><?php echo number_format((float)($data['JOB_RELEASEPIECE'] ?? 0), 0); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Produce (Wt. - Pc.)</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2; justify-content: flex-end; color:#059669;"><?php echo number_format((float)($data['JOB_PRODUCEWEIGHT'] ?? 0), 0); ?></div>
                                    <div class="info-value-box" style="flex: 1; justify-content: center; color:#059669;"><?php echo number_format((float)($data['JOB_PRODUCEPIECE'] ?? 0), 0); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Packing Qty.</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2; justify-content: flex-end;"><?php echo number_format((float)($data['JOB_PACKWEIGHT'] ?? 0), 0); ?></div>
                                    <div class="info-value-box" style="flex: 1; justify-content: center;"><?php echo number_format((float)($data['JOB_PACKPIECE'] ?? 0), 0); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Stock Qty.</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2; justify-content: flex-end;"><?php echo number_format((float)($data['JOB_STOCKWEIGHT'] ?? 0), 0); ?></div>
                                    <div class="info-value-box" style="flex: 1; justify-content: center;"><?php echo number_format((float)($data['JOB_STOCKPIECE'] ?? 0), 0); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 12px;">
                                <div class="col-md-5 info-label">Work Process</div>
                                <div class="col-md-7 info-value-box" style="color: #d97706; font-weight: 700;"><?php echo htmlspecialchars($data['JOB_WORKPROCESS'] ?? '-'); ?></div>
                            </div>

                            <hr style="margin: 10px 0;">

                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Sale Order No. / Item</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2;"><?php echo htmlspecialchars($data['SALEORDER_NO'] ?? '-'); ?></div>
                                    <div class="info-value-box" style="flex: 1;"><?php echo htmlspecialchars($data['SALEORDER_ITEM'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">PO No. / Item</div>
                                <div class="col-md-7" style="display: flex; gap: 4px;">
                                    <div class="info-value-box" style="flex: 2;"><?php echo htmlspecialchars($data['CTM2_PONO'] ?? '-'); ?></div>
                                    <div class="info-value-box" style="flex: 1;"><?php echo htmlspecialchars($data['CTM2_POITEM'] ?? '-'); ?></div>
                                </div>
                            </div>
                            <div class="row" style="margin-bottom: 8px;">
                                <div class="col-md-5 info-label">Customer</div>
                                <div class="col-md-7 info-value-box"><?php echo htmlspecialchars($data['CSTMSPPL_ID'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COMBINE WRAP PRODUCT LIST TABLE -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card-custom">
                            <div class="section-title" style="color: #dc2626;">Combine Wrap Product List (<?php echo !empty($current_product_no) ? $current_product_no : 'New Wrap Product'; ?>)</div>
                            <div class="table-responsive">
                                <table class="table grid-table">
                                    <thead>
                                        <tr>
                                            <th>Product Ref.</th>
                                            <th>Coil No.</th>
                                            <th>Combine Date</th>
                                            <th style="text-align: right;">Combine Wt.</th>
                                            <th>Operator</th>
                                            <th style="text-align: center;">Action / Delete</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($combined_items)): ?>
                                            <tr><td colspan="6" align="center" style="color:#94a3b8; padding:15px;">There are no scrap entries for this Wrap Product yet (the Product No. will be generated automatically upon saving)</td></tr>
                                        <?php else: ?>
                                            <?php foreach($combined_items as $item): ?>
                                                <tr>
                                                    <td><b><?php echo htmlspecialchars($item['PRODUCT_REF']); ?></b></td>
                                                    <td><?php echo htmlspecialchars($item['COIL_NO']); ?></td>
                                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($item['CMBN_DATE']))); ?></td>
                                                    <td align="right" style="font-weight:600; color:#059669;"><?php echo number_format($item['CMBN_WEIGHT'], 0); ?> kg</td>
                                                    <td><?php echo htmlspecialchars($item['CMBN_OPERATOR']); ?></td>
                                                    <td align="center">
                                                        <a href="model/combining_delete_action.php?productno=<?php echo urlencode($current_product_no); ?>&ref=<?php echo urlencode($item['PRODUCT_REF']); ?>&jno=<?php echo urlencode($jno); ?>&func=<?php echo urlencode($folder_func); ?>" 
                                                           class="btn btn-danger" style="padding: 2px 8px; font-size:11px;" onclick="return confirm('⚠️ ต้องการลบรายการนี้ออกจาก Wrap Product และคืนสถานะ Scrap ใช่หรือไม่?');">🗑️ Del</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'include/content-footer.php';?>
   </div>
</div>

<!-- Modal Lookup เลือก Scrap -->
<div id="scrapModal" class="modal-lookup">
    <div class="modal-content-custom">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h4 style="margin: 0; font-weight: 700; color: #1e293b; font-size: 16px;">🔍 ค้นหา Scrap Product No. (สถานะ OP - Alloy: <?php echo htmlspecialchars($job_alloy); ?>)</h4>
            <button type="button" onclick="closeScrapModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; font-weight: bold;">✕</button>
        </div>
        <div style="margin-bottom: 10px;">
            <input type="text" id="modalSearchScrap" class="form-control-input" placeholder="พิมพ์ค้นหา Scrap No. หรือ Coil No..." onkeyup="filterScrapTable()">
        </div>
        <div style="max-height: 350px; overflow-y: auto;">
            <table class="table grid-table" id="modalScrapTable">
                <thead>
                    <tr>
                        <th>Scrap Product No</th>
                        <th>Coil No</th>
                        <th>Alloy</th>
                        <th style="text-align: right;">Weight</th>
                        <th style="text-align: center;">Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($scrap_list)): ?>
                        <tr><td colspan="5" align="center" style="color:#94a3b8; padding:20px;">ไม่พบรายการ Scrap ที่มีสถานะ OP และ Alloy ตรงกัน</td></tr>
                    <?php else: ?>
                        <?php foreach($scrap_list as $sc): ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($sc['PRODUCT_NO']); ?></b></td>
                                <td><?php echo htmlspecialchars($sc['COIL_NO'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($sc['ALLOY'] ?? '-'); ?></td>
                                <td align="right"><?php echo number_format((float)$sc['SCRP_WEIGHT'], 0); ?> kg</td>
                                <td align="center">
                                    <button type="button" class="btn" style="background:#2563eb; color:#fff; padding:3px 10px; font-size:11px; border-radius:4px;"
                                            onclick="pickScrap('<?php echo htmlspecialchars($sc['PRODUCT_NO']); ?>', '<?php echo htmlspecialchars($sc['COIL_NO'] ?? ''); ?>', '<?php echo htmlspecialchars($sc['SCRP_WEIGHT'] ?? 0); ?>', '<?php echo htmlspecialchars($sc['MATERIAL_IN'] ?? ''); ?>')">
                                        SELECT
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>
<script>
function openScrapModal() { document.getElementById('scrapModal').style.display = 'flex'; }
function closeScrapModal() { document.getElementById('scrapModal').style.display = 'none'; }

function pickScrap(pno, coil, weight, matIn) {
    document.getElementById('combine_no').value = pno;
    document.getElementById('coil_no_display').innerText = coil;
    document.getElementById('coil_no_input').value = coil;
    document.getElementById('material_in_display').innerText = matIn;
    document.getElementById('material_in_input').value = matIn;
    document.getElementById('scrap_weight').value = weight;
    document.getElementById('gross_weight').value = weight;

    var baseAccumulate = parseFloat(document.getElementById('base_accumulate_weight').value) || 0;
    var totalAccumulate = baseAccumulate + weight;
    document.getElementById('scrap_accumulate_weight').value = totalAccumulate.toFixed(0);

    calculateNetWeight();
    closeScrapModal();
}

function filterScrapTable() {
    var input = document.getElementById("modalSearchScrap").value.toUpperCase();
    var tr = document.getElementById("modalScrapTable").getElementsByTagName("tr");
    for (var i = 1; i < tr.length; i++) {
        var tdPno = tr[i].getElementsByTagName("td")[0];
        var tdCoil = tr[i].getElementsByTagName("td")[1];
        if (tdPno || tdCoil) {
            var txtPno = tdPno.textContent || tdPno.innerText;
            var txtCoil = tdCoil.textContent || tdCoil.innerText;
            tr[i].style.display = (txtPno.toUpperCase().indexOf(input) > -1 || txtCoil.toUpperCase().indexOf(input) > -1) ? "" : "none";
        }
    }
}

function calculateNetWeight() {
    var gross = parseFloat(document.getElementById('gross_weight').value) || 0;
    var bottom = parseFloat(document.getElementById('bottom_weight').value) || 0;
    var net = gross - bottom;
    if (net < 0) net = 0;
    document.getElementById('net_weight').value = net.toFixed(0);

    var badge = document.getElementById('status_badge');
    if (net > 0) {
        badge.innerText = "ST";
        badge.style.backgroundColor = "#e0e7ff";
        badge.style.color = "#4338ca";
    } else {
        badge.innerText = "AC";
        badge.style.backgroundColor = "#dcfce7";
        badge.style.color = "#166534";
    }
}
</script>
</html>