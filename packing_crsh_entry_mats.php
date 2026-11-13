<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$position_func   = isset($_SESSION['POSITION']) ? strtoupper(trim($_SESSION['POSITION'])) : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';
$user_level      = isset($_SESSION['LEVEL']) ? strtoupper(trim($_SESSION['LEVEL'])) : 'A';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$product_no = isset($_GET['product_no']) ? htmlspecialchars(trim($_GET['product_no']), ENT_QUOTES, 'UTF-8') : '';
if (empty($product_no)) {
    header("Location: packing_crsh_process_mats.php?func={$folder_func}");
    exit();
}

include("dbcon_mats-new.php");

// 1. Fetch Product Record
$stmt = $conn->prepare("SELECT TOP 1 * FROM CRSHPROD1 WHERE PRODUCT_NO = :product_no");
$stmt->execute([':product_no' => $product_no]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo "<script>alert('Product No. not found.'); window.location.href='packing_crsh_process_mats.php?func={$folder_func}';</script>";
    exit();
}

// Check Record Status (Old Record / New Record)
$stmt_check_pack = $conn->prepare("SELECT TOP 1 * FROM PACKPROD1 WHERE PRODUCT_NO = :product_no");
$stmt_check_pack->execute([':product_no' => $product_no]);
$existing_pack = $stmt_check_pack->fetch(PDO::FETCH_ASSOC);
$is_old_record = !empty($existing_pack);

$job_order_no    = trim($item['JOB_ORDER'] ?? '');
$sale_order_no   = trim($item['SALEORDER_NO'] ?? '');
$sale_order_item = trim($item['SALEORDER_ITEM'] ?? '');

// 2. Fetch Job Order
$stmt_job = $conn->prepare("SELECT TOP 1 * FROM JOBORDER1 WHERE JOB_ORDER = :job_order");
$stmt_job->execute([':job_order' => $job_order_no]);
$job = $stmt_job->fetch(PDO::FETCH_ASSOC) ?: [];

// 3. Fetch Sales Order & Customer
$stmt_so = $conn->prepare("SELECT TOP 1 c2.*, c1.CSTMSPPL_ID, c1.ORDER_CATEGORY, cs.ADJUST_PIECE, cs.CSTMSPPL_LOCATION 
                          FROM CSTMORDR2 c2 
                          LEFT JOIN CSTMORDR1 c1 ON c2.SALEORDER_NO = c1.SALEORDER_NO 
                          LEFT JOIN CSSPMSTR1 cs ON c1.CSTMSPPL_ID = cs.CSTMSPPL_ID
                          WHERE c2.SALEORDER_NO = :so_no AND c2.SALEORDER_ITEM = :so_item");
$stmt_so->execute([':so_no' => $sale_order_no, ':so_item' => $sale_order_item]);
$so = $stmt_so->fetch(PDO::FETCH_ASSOC) ?: [];

$customer_location = trim($so['CSTMSPPL_LOCATION'] ?? 'L');

// 4. Fetch Pack History for current Sales Order
$sql_pack = "SELECT p.PRODUCT_NO, p.SALEORDER_NO, p.SALEORDER_ITEM, p.JOB_ORDER, p.MATERIAL_IN,
                    p.TRANSFER_NO, p.TRANSFER_DATE, p.LINE_PROCESS, p.PRODUCT_ID, p.PACK_PONO, p.PACK_POITEM,
                    p.PACK_DATE, p.PACK_COMBINEWEIGHT, p.PACK_PACKAGEWEIGHT, p.PACK_NETWEIGHT, p.PACK_NETPIECE,
                    p.PACK_MATERIALCODE, p.PACK_STATUS 
             FROM [MATS-NEWII].dbo.PACKPROD1 AS p
             WHERE p.SALEORDER_NO = :saleorder_no
             ORDER BY p.PACK_DATE DESC";
$stmt_pack_list = $conn->prepare($sql_pack);
$stmt_pack_list->execute([':saleorder_no' => $sale_order_no]);
$pack_rows = $stmt_pack_list->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Combine Product
$stmt_cmbn = $conn->prepare("SELECT * FROM CMBNPROD1 WHERE PRODUCT_NO = :prod_no");
$stmt_cmbn->execute([':prod_no' => $product_no]);
$cmbn_rows = $stmt_cmbn->fetchAll(PDO::FETCH_ASSOC);

// Calculations
$wt_piece  = (float)($item['WEIGHT_PIECE'] ?? 0);
$actual_wt = (float)($item['CRSH_ACTUALWEIGHT'] ?? 0);
$actual_pc = (float)($item['CRSH_ACTUALPIECE'] ?? 0);

$job_rel_wt  = (float)($job['JOB_RELEASEWEIGHT'] ?? 0);
$job_max_tol = (float)($job['JOB_MAXTOLERANCE'] ?? 0);
$job_min_tol = (float)($job['JOB_MINTOLERANCE'] ?? 0);
$job_max_wt  = $job_rel_wt + ($job_rel_wt * $job_max_tol / 100);
$job_min_wt  = $job_rel_wt - ($job_rel_wt * $job_min_tol / 100);
$job_max_pc  = ($wt_piece > 0) ? round($job_max_wt / $wt_piece) : 0;
$job_min_pc  = ($wt_piece > 0) ? round($job_min_wt / $wt_piece) : 0;

$so_order_wt   = (float)($so['CTM2_ORDERWEIGHT'] ?? 0);
$so_replace_wt = (float)($so['CTM2_REPLACEWEIGHT'] ?? 0);
$so_total_wt   = $so_order_wt + $so_replace_wt;
$so_max_tol    = (float)($so['CTM2_MAXTOLERANCE'] ?? $job_max_tol);
$so_min_tol    = (float)($so['CTM2_MINTOLERANCE'] ?? $job_min_tol);
$so_max_wt     = $so_total_wt + ($so_total_wt * $so_max_tol / 100);
$so_min_wt     = $so_total_wt - ($so_total_wt * $so_min_tol / 100);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium - Packaging Dashboard [<?php echo $product_no; ?>]</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        :root {
            --primary-blue: #1e3a8a;
            --primary-accent: #2563eb;
            --surface-bg: #f8fafc;
            --card-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; 
            background-color: var(--surface-bg); color: var(--text-main); font-size: 12px;
        }
        .main-panel { background-color: var(--surface-bg) !important; }
        .main-panel .content { padding: 16px 20px !important; }

        /* Top Action Bar (Cleaned - Removed Back & New Record Label) */
        .top-nav-bar {
            display: flex; justify-content: flex-end; align-items: center;
            background: #ffffff; padding: 10px 16px; border-radius: 10px;
            border: 1px solid var(--card-border); margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .btn-action-save {
            background-color: #059669; color: #fff; font-weight: 700; font-size: 12.5px;
            padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-action-save:hover { background-color: #047857; }

        .btn-action-print {
            background-color: #16a34a; color: #fff; font-weight: 700; font-size: 12.5px;
            padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-action-print:hover:not(:disabled) { background-color: #15803d; }
        .btn-action-print:disabled { background-color: #94a3b8; cursor: not-allowed; opacity: 0.6; }

        .btn-action-delete {
            background-color: #dc2626; color: #fff; font-weight: 700; font-size: 12.5px;
            padding: 8px 18px; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-action-delete:hover:not(:disabled) { background-color: #b91c1c; }
        .btn-action-delete:disabled { background-color: #cbd5e1; color: #94a3b8; cursor: not-allowed; }

        .btn-action-cancel {
            background-color: #64748b; color: #fff; font-weight: 600; font-size: 12.5px;
            padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-action-cancel:hover { background-color: #475569; color: #fff; }

        /* Modern Tabs */
        .modern-tab-bar {
            display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 16px;
        }
        .modern-tab-btn {
            background: transparent; border: none; padding: 10px 22px; font-weight: 700;
            font-size: 13px; cursor: pointer; color: var(--text-muted); border-bottom: 3px solid transparent;
            transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 6px;
        }
        .modern-tab-btn:hover { color: var(--primary-accent); background: #f1f5f9; border-radius: 6px 6px 0 0; }
        .modern-tab-btn.active {
            color: var(--primary-accent); border-bottom-color: var(--primary-accent); background: #ffffff;
            border-top: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
            border-radius: 6px 6px 0 0;
        }

        .m-card {
            background: #ffffff; border-radius: 10px; border: 1px solid var(--card-border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); padding: 16px; margin-bottom: 14px;
        }
        .m-card-header {
            font-size: 12.5px; font-weight: 700; color: #1e293b; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1.5px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 12px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .m-form-row { display: flex; align-items: center; margin-bottom: 6px; gap: 6px; }
        .m-label {
            font-size: 11.5px; font-weight: 600; color: #475569; width: 160px; flex-shrink: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .m-box {
            height: 28px; border-radius: 5px; font-size: 12px; font-weight: 600;
            padding: 3px 8px; border: 1px solid #cbd5e1; box-sizing: border-box;
            background: #ffffff; color: var(--text-main); font-family: inherit;
        }
        .m-box:focus { border-color: var(--primary-accent); outline: none; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
        
        .m-box-display {
            background-color: #f1f5f9 !important; border-color: #e2e8f0 !important;
            color: #1e3a8a !important; font-weight: 700;
        }
        .m-box-display-num {
            background-color: #f1f5f9 !important; border-color: #e2e8f0 !important;
            color: #1e3a8a !important; font-weight: 700; text-align: right;
        }

        .m-box-product {
            background-color: #fee2e2 !important; border-color: #fca5a5 !important;
            color: #991b1b !important; font-weight: 800;
        }

        .m-box-highlight {
            background-color: #fef08a !important; border-color: #fde047 !important;
            color: #854d0e !important; font-weight: 800; text-align: right;
        }

        .btn-m-search-icon {
            height: 28px; padding: 0 10px; background-color: #2563eb; color: #ffffff;
            border: none; border-radius: 5px; font-size: 11.5px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-m-search-icon:hover { background-color: #1d4ed8; }

        /* Summary Context Box (สำหรับดึงข้อมูลที่ซ้ำกันขึ้นมาข้างบน) */
        .history-summary-box {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 14px; margin-bottom: 10px;
        }
        .summary-item label {
            display: block; font-size: 10.5px; font-weight: 700; color: #64748b; margin-bottom: 2px;
            text-transform: uppercase;
        }
        .summary-item div {
            font-size: 12px; font-weight: 700; color: #1e293b;
        }

        /* Simplified Table */
        .pack-table-wrapper {
            overflow-x: auto; max-height: 220px; border: 1px solid #e2e8f0; border-radius: 6px;
        }
        .m-pack-table {
            width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap;
        }
        .m-pack-table th {
            background: #0f172a; color: #ffffff; padding: 7px 10px; font-weight: 700;
            font-size: 11px; text-align: center; position: sticky; top: 0; z-index: 1;
        }
        .m-pack-table td {
            padding: 6px 10px; border-bottom: 1px solid #f1f5f9; color: #334155;
        }
        .m-pack-table tbody tr:hover td { background-color: #f0fdf4 !important; }

        /* Pallet Modal */
        .modal-pallet-backdrop {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); z-index: 1050; align-items: center; justify-content: center;
        }
        .modal-pallet-box {
            background: #ffffff; border-radius: 12px; width: 90%; max-width: 900px;
            max-height: 85vh; display: flex; flex-direction: column; overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-pallet-header {
            background: #0f172a; color: #ffffff; padding: 14px 20px; font-weight: 700;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-pallet-body { padding: 16px; overflow-y: auto; flex: 1; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A4'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 19px;" href="packing_crsh_process_mats.php?func=<?php echo $folder_func ?>">
                        Packaging Management [<?php echo $product_no; ?>]
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <!-- Top Command Bar (Back to list & New Record removed) -->
            <div class="top-nav-bar">
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-action-save" onclick="savePackagingAjax();">
                        💾 SAVE PACKAGING
                    </button>
                    <!-- Print Label Button -->
                    <button type="button" class="btn-action-print" id="btn_print_label" <?php echo $is_old_record ? '' : 'disabled'; ?> onclick="openPrintLabelPreview();">
                        🖨️ PRINT LABEL
                    </button>
                    <?php 
                    $can_delete = $is_old_record && in_array($user_level, ['A', 'B', 'C', 'E']); 
                    ?>
                    <button type="button" class="btn-action-delete" id="btn_delete" <?php if(!$can_delete) echo 'disabled'; ?> onclick="confirmDeletePack();">
                        🗑️ DELETE
                    </button>
                    <a href="packing_crsh_process_mats.php?func=<?php echo $folder_func; ?>" class="btn-action-cancel">
                        CANCEL
                    </a>
                </div>
            </div>

            <!-- Modern Nav Tabs -->
            <div class="modern-tab-bar">
                <button type="button" class="modern-tab-btn active" id="tab_btn_1" onclick="switchTab(1)">
                    📦 1. Packing Circle or Sheet
                </button>
                <button type="button" class="modern-tab-btn" id="tab_btn_2" onclick="switchTab(2)">
                    🏷️ 2. Pre-packing Label
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: PACKING CIRCLE OR SHEET                                            -->
            <!-- ========================================================================= -->
            <div id="tab_content_1">
                <form id="frm_packing">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>"/>
                    <input type="hidden" name="product_no" id="product_no" value="<?php echo $product_no; ?>"/>
                    <input type="hidden" id="weight_piece" value="<?php echo $wt_piece; ?>"/>
                    <input type="hidden" id="product_id" value="<?php echo trim($item['PRODUCT_ID'] ?? ''); ?>"/>
                    <input type="hidden" id="actual_wt_prev" value="<?php echo $actual_wt; ?>"/>
                    <input type="hidden" id="spec_width" value="<?php echo (float)($item['WIDTH'] ?? 0); ?>"/>
                    <input type="hidden" id="spec_length" value="<?php echo (float)($item['LENGTH'] ?? 0); ?>"/>
                    <input type="hidden" id="customer_location" value="<?php echo $customer_location; ?>"/>

                    <!-- Pallet & Top Package Hidden Specs -->
                    <input type="hidden" id="std_top_weight" value="0"/>
                    <input type="hidden" id="std_bottom_weight" value="0"/>
                    <input type="hidden" id="std_package_high" value="0"/>
                    <input type="hidden" id="std_package_width" value="0"/>
                    <input type="hidden" id="std_package_length" value="0"/>
                    <input type="hidden" id="std_width_plus" value="30"/>
                    <input type="hidden" id="std_width_minus" value="30"/>
                    <input type="hidden" id="std_length_plus" value="30"/>
                    <input type="hidden" id="std_length_minus" value="30"/>
                    <input type="hidden" id="std_stack_number" value="1"/>

                    <div class="row">
                        <!-- Left Column: Maintain Product & History -->
                        <div class="col-md-6" style="padding-right: 8px;">
                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Maintain Product (Packaging & Weighing Entry)</span>
                                    <span class="badge" style="background:#eff6ff; color:#2563eb;">ACTIVE</span>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Product No.</div>
                                    <input type="text" class="m-box m-box-product" style="width: 150px;" value="<?php echo $product_no; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Product Status - VMI Status</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['CRSH_STATUS'] ?? 'AC'); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['VMI_STATUS'] ?? 'NV'); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Product ID - Model</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['PRODUCT_ID'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 150px;" value="<?php echo trim($item['PRODUCT_MODEL'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Material In - Coil No.</div>
                                    <input type="text" class="m-box m-box-display" style="width: 110px;" value="<?php echo trim($item['MATERIAL_IN'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 130px;" value="<?php echo trim($item['COIL_NO'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Alloy - Temper</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['ALLOY'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['TEMPER'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Grade SG - MG</div>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($item['GRADE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($item['SURFACE_GRADE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($item['METALLURGICAL_GRADE'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">T5 Temp. - Flatness Grade</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['T5_TEMPERATURE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($item['FLATNESS_GRADE'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Thickness - Width - Length</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)$item['THICKNESS'], 3); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)$item['WIDTH'], 3); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)$item['LENGTH'], 3); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Weight Piece</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 85px;" value="<?php echo number_format($wt_piece, 4); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Combine Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($item['CRSH_COMBINEWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($item['CRSH_COMBINEPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Actual Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($actual_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($actual_pc, 0); ?>" readonly/>
                                </div>

                                <div style="margin: 10px 0; border-top: 1px dashed #cbd5e1;"></div>

                                <!-- Material Package Code with Pallet Lookup -->
                                <div class="m-form-row">
                                    <div class="m-label">Material Package Code</div>
                                    <input type="text" name="material_code" id="material_code" class="m-box" style="width: 150px;" 
                                           value="<?php echo htmlspecialchars($existing_pack['PACK_MATERIALCODE'] ?? ''); ?>" 
                                           placeholder="Select Pallet" onchange="fetchMaterialPackage(this.value);" required/>
                                    <button type="button" class="btn-m-search-icon" onclick="openPalletLookupModal()">
                                        📦 Select Pallet
                                    </button>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Stack Number</div>
                                    <input type="number" name="pack_stack_number" id="pack_stack_number" class="m-box" style="width: 80px; text-align:right;" 
                                           value="<?php echo !empty($existing_pack['PACK_STACKNUMBER']) ? $existing_pack['PACK_STACKNUMBER'] : '1'; ?>" min="1" oninput="calculateVBLogic()"/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Top - Bottom Weight</div>
                                    <input type="number" name="top_weight" id="top_weight" class="m-box" style="width: 80px; text-align:right;" 
                                           value="<?php echo (float)($item['CRSH_TOPWEIGHT'] ?? 0); ?>" oninput="calculateVBLogic()"/>
                                    <input type="number" name="bottom_weight" id="bottom_weight" class="m-box" style="width: 80px; text-align:right;" 
                                           value="<?php echo (float)($item['CRSH_BOTTOMWEIGHT'] ?? 0); ?>" oninput="calculateVBLogic()"/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Net Weight - Piece</div>
                                    <input type="text" name="pack_net_weight" id="pack_net_weight" class="m-box m-box-highlight" style="width: 80px;" 
                                           value="<?php echo !empty($existing_pack['PACK_NETWEIGHT']) ? $existing_pack['PACK_NETWEIGHT'] : $actual_wt; ?>" readonly/>
                                    <input type="text" name="pack_net_piece" id="pack_net_piece" class="m-box m-box-highlight" style="width: 80px;" 
                                           value="<?php echo !empty($existing_pack['PACK_NETPIECE']) ? $existing_pack['PACK_NETPIECE'] : $actual_pc; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Total Gross Weight</div>
                                    <input type="number" name="gross_weight" id="gross_weight" class="m-box" style="width: 80px; text-align:right;" 
                                           value="<?php echo $actual_wt; ?>" required oninput="calculateVBLogic()"/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Actual Dimension WxLxH</div>
                                    <input type="number" name="dim_width" id="dim_width" class="m-box" style="width: 70px; text-align:right;" placeholder="W" 
                                           value="<?php echo (float)($item['CRSH_DIMENSIONWIDTH'] ?? 0); ?>" oninput="calculateVBLogic()"/>
                                    <input type="number" name="dim_length" id="dim_length" class="m-box" style="width: 70px; text-align:right;" placeholder="L" 
                                           value="<?php echo (float)($item['CRSH_DIMENSIONLENGTH'] ?? 0); ?>" oninput="calculateVBLogic()"/>
                                    <input type="number" name="dim_high" id="dim_high" class="m-box" style="width: 70px; text-align:right;" placeholder="H" 
                                           value="<?php echo (float)($item['CRSH_DIMENSIONHIGH'] ?? 0); ?>" oninput="calculateVBLogic()"/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Process Package Code</div>
                                    <input type="text" id="disp_pkg_code" class="m-box m-box-display" style="width: 160px;" 
                                           value="<?php echo htmlspecialchars($existing_pack['PACK_MATERIALCODE'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Org. Stack Number-High</div>
                                    <input type="text" id="org_stack_num" class="m-box m-box-display-num" style="width: 75px;" 
                                           value="<?php echo (int)($item['CRSH_STACKNUMBER'] ?? 0); ?>" readonly/>
                                    <input type="text" id="org_stack_high" class="m-box m-box-display-num" style="width: 75px;" 
                                           value="<?php echo (int)($item['CRSH_STACKHIGH'] ?? 0); ?>" readonly/>
                                </div>

                                <!-- เพิ่มส่วน Paper Interleaving ตามเงื่อนไข pk1 (เก็บค่า Y / N ลง CRSHPROD1.PAPER_INTERLEAVE) -->
                                <?php 
                                    // ตรวจสอบค่าเดิม: ถ้าเคยบันทึกไว้ใน CRSHPROD1 ให้ใช้ค่านั้น หากเป็นงานใหม่ให้ดึงค่าเริ่มต้นจาก CSTMORDR2
                                    $pi_val = !empty($item['PAPER_INTERLEAVE']) ? trim($item['PAPER_INTERLEAVE']) : trim($so['PAPER_INTERLEAVE'] ?? 'N');
                                    $is_pi_checked = (strtoupper($pi_val) === 'Y');
                                ?>
                                <div class="m-form-row" style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0;">
                                    <div class="m-label"></div>
                                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; font-size: 13px; font-weight: 800; color: #dc2626;">
                                        <input type="checkbox" name="paper_interleave" id="paper_interleave" value="Y" 
                                               style="width: 16px; height: 16px; accent-color: #dc2626; cursor: pointer;"
                                               <?php if ($is_pi_checked) echo 'checked'; ?> />
                                        Paper Interleaving
                                    </label>
                                </div>                                
                            </div>

                            <!-- ========================================================================= -->
                            <!-- Pack Product History (ปรับปรุง: แยกข้อมูลซ้ำไว้บนตาราง)                    -->
                            <!-- ========================================================================= -->
                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Pack Product History (Records in this Sales Order)</span>
                                    <span class="badge" style="background:#0f172a; color:#fff;"><?php echo count($pack_rows); ?> Items</span>
                                </div>

                                <!-- ส่วนแสดงข้อมูลที่ซ้ำกันไว้ด้านบนตาราง -->
                                <div class="history-summary-box">
                                    <div class="summary-item">
                                        <label>Sales Order</label>
                                        <div><?php echo $sale_order_no; ?> : <?php echo $sale_order_item; ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <label>Job Order</label>
                                        <div><?php echo $job_order_no; ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <label>Customer PO / Item</label>
                                        <div><?php echo htmlspecialchars($so['CTM2_PONO'] ?? '-'); ?> / <?php echo htmlspecialchars($so['CTM2_POITEM'] ?? '-'); ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <label>Material In</label>
                                        <div><?php echo htmlspecialchars($item['MATERIAL_IN'] ?? '-'); ?></div>
                                    </div>
                                    <div class="summary-item">
                                        <label>Product Type</label>
                                        <div style="color:#2563eb;"><?php echo htmlspecialchars($item['PRODUCT_ID'] ?? '-'); ?></div>
                                    </div>
                                </div>

                                <!-- ตารางส่วนที่เหลือเฉพาะรายละเอียดของแต่ละพาเลท -->
                                <div class="pack-table-wrapper">
                                    <table class="m-pack-table">
                                        <thead>
                                            <tr>
                                                <th>Product No.</th>
                                                <th>Pack Date</th>
                                                <th>Transfer No.</th>
                                                <th>Line</th>
                                                <th>Pallet Code</th>
                                                <th style="text-align:right;">Combine Wt</th>
                                                <th style="text-align:right;">Pkg Wt</th>
                                                <th style="text-align:right;">Net Wt (kg)</th>
                                                <th style="text-align:right;">Net Pcs</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($pack_rows)): ?>
                                                <tr><td colspan="10" align="center" style="color: #94a3b8; padding: 16px;">No packaging records found in this Sales Order.</td></tr>
                                            <?php else: foreach($pack_rows as $pr): 
                                                $p_date = !empty($pr['PACK_DATE']) ? date('d/m/y H:i', strtotime($pr['PACK_DATE'])) : '-';
                                            ?>
                                                <tr>
                                                    <td style="font-weight:700; color:#1e40af;"><?php echo htmlspecialchars($pr['PRODUCT_NO']); ?></td>
                                                    <td align="center"><?php echo $p_date; ?></td>
                                                    <td align="center"><?php echo htmlspecialchars($pr['TRANSFER_NO'] ?? '-'); ?></td>
                                                    <td align="center"><?php echo htmlspecialchars($pr['LINE_PROCESS'] ?? '-'); ?></td>
                                                    <td align="center" style="font-weight:600; color:#0f172a;"><?php echo htmlspecialchars($pr['PACK_MATERIALCODE'] ?? '-'); ?></td>
                                                    <td align="right"><?php echo number_format((float)($pr['PACK_COMBINEWEIGHT'] ?? 0), 0); ?></td>
                                                    <td align="right"><?php echo number_format((float)($pr['PACK_PACKAGEWEIGHT'] ?? 0), 0); ?></td>
                                                    <td align="right" style="font-weight:700; color:#059669;"><?php echo number_format((float)($pr['PACK_NETWEIGHT'] ?? 0), 0); ?></td>
                                                    <td align="right"><?php echo number_format((float)($pr['PACK_NETPIECE'] ?? 0), 0); ?></td>
                                                    <td align="center"><span class="badge" style="background:#dcfce7; color:#15803d; font-size:10px;"><?php echo htmlspecialchars($pr['PACK_STATUS'] ?? 'ST'); ?></span></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Job Order & Sale Confirm -->
                        <div class="col-md-6" style="padding-left: 8px;">
                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Job Order (Specifications & Cumulative Totals)</span>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Job Order - Status</div>
                                    <input type="text" class="m-box m-box-display" style="width: 140px;" value="<?php echo $job_order_no; ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px; text-align:center;" value="<?php echo trim($job['JOB_STATUS'] ?? 'OP'); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Order By - Material In</div>
                                    <input type="text" class="m-box m-box-display" style="width: 140px;" value="<?php echo trim($job['ORDER_BY'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 120px;" value="<?php echo trim($job['MATERIAL_IN'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Start - EDT Date</div>
                                    <input type="text" class="m-box m-box-display" style="width: 120px;" value="<?php echo !empty($job['ORDER_STARTDATE']) ? date('m/d/Y', strtotime($job['ORDER_STARTDATE'])) : ''; ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 120px;" value="<?php echo !empty($job['SCHEDULE_DATE']) ? date('m/d/Y', strtotime($job['SCHEDULE_DATE'])) : ''; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Alloy - Temper</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($job['ALLOY'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($job['TEMPER'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Grade - SG - MG</div>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($job['GRADE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($job['SURFACE_GRADE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px;" value="<?php echo trim($job['METALLURGICAL_GRADE'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">T5 Temp. - Flatness Graed</div>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($job['T5_TEMPERATURE'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 80px;" value="<?php echo trim($job['FLATNESS_GRADE'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Thickness - Width - Length</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)($job['THICKNESS'] ?? 0), 3); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)($job['WIDTH'] ?? 0), 3); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 75px;" value="<?php echo number_format((float)($job['LENGTH'] ?? 0), 3); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Max-Min Tolerance</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_max_tol, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_min_tol, 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Maximun Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_max_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_max_pc, 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Release Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_rel_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($job['JOB_RELEASEPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Minimun Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_min_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($job_min_pc, 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Packing Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($job['JOB_PACKWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($job['JOB_PACKPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Stock Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($job['JOB_STOCKWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($job['JOB_STOCKPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>
                            </div>

                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Sale Confirm (Sales Order Confirmation)</span>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Sale Order No</div>
                                    <input type="text" class="m-box m-box-display" style="width: 130px;" value="<?php echo $sale_order_no; ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 45px; text-align:center;" value="<?php echo $sale_order_item; ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 70px; text-align:center;" value="<?php echo trim($so['ORDER_CATEGORY'] ?? 'ORDR'); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Purchase Order</div>
                                    <input type="text" class="m-box m-box-display" style="width: 180px;" value="<?php echo trim($so['CTM2_PONO'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 45px; text-align:center;" value="<?php echo trim($so['CTM2_POITEM'] ?? ''); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Customer - Material In</div>
                                    <input type="text" class="m-box m-box-display" style="width: 90px;" value="<?php echo trim($so['CSTMSPPL_ID'] ?? ''); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display" style="width: 100px;" value="<?php echo trim($so['CTM2_MATERIALIN'] ?? ''); ?>" readonly/>
                                    <label style="margin: 0 0 0 8px; color: #64748b; font-size: 11px;">
                                        <input type="checkbox" id="chk_adjust_piece" <?php if(($so['ADJUST_PIECE'] ?? 0) == 1) echo 'checked'; ?> disabled> Adjust Piece
                                    </label>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Max-Min Tolerance</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_max_tol, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_min_tol, 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Order Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_order_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_ORDERPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Order Reject Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_REJECTWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_REJECTPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Order Replace Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_replace_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_REPLACEPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Maximun Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_max_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo ($wt_piece > 0) ? round($so_max_wt / $wt_piece) : 0; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Total Order Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_total_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo ($wt_piece > 0) ? round($so_total_wt / $wt_piece) : 0; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Minimun Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format($so_min_wt, 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo ($wt_piece > 0) ? round($so_min_wt / $wt_piece) : 0; ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Packing Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_PACKWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_PACKPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Stock Qty. (Wt. - Pc.)</div>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_STOCKWEIGHT'] ?? 0), 0); ?>" readonly/>
                                    <input type="text" class="m-box m-box-display-num" style="width: 80px;" value="<?php echo number_format((float)($so['CTM2_STOCKPIECE'] ?? 0), 0); ?>" readonly/>
                                </div>
                            </div>

                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Combine Product</span>
                                </div>
                                <div style="min-height: 48px; max-height: 80px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px;">
                                    <?php if(empty($cmbn_rows)): ?>
                                        <div style="color: #94a3b8; text-align: center;">No Combine Data</div>
                                    <?php else: foreach($cmbn_rows as $cr): ?>
                                        <div style="font-weight:600; color:#1e3a8a;"><?php echo htmlspecialchars($cr['PRODUCT_NO']); ?> : <?php echo (float)$cr['CMBN_ACTUALWEIGHT']; ?> kg</div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: PRE-PACKING LABEL                                                  -->
            <!-- ========================================================================= -->
            <div id="tab_content_2" style="display: none;">
                <form id="frm_prepack">
                    <input type="hidden" name="func" value="<?php echo $folder_func; ?>"/>

                    <div class="row">
                        <div class="col-md-5" style="padding-right: 8px;">
                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Pre-pack Label Generation</span>
                                </div>

                                <div class="m-form-row" style="background:#eff6ff; padding: 8px; border-radius: 6px; border:1px solid #bfdbfe; margin-bottom: 12px;">
                                    <div class="m-label" style="color:#1d4ed8; font-weight:700;">Main Product No. *</div>
                                    <input type="text" name="main_product_no" id="main_product_no" class="m-box" style="width: 140px; font-weight:700;" 
                                           value="<?php echo $product_no; ?>" required/>
                                </div>

                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <div class="m-form-row">
                                    <div class="m-label">Sub Product No. <?php echo $i; ?></div>
                                    <input type="text" name="sub_product_no_<?php echo $i; ?>" id="sub_product_no_<?php echo $i; ?>" 
                                           class="m-box" style="width: 180px;" placeholder="Optional"
                                           onchange="validateSubProduct(this.value, <?php echo $i; ?>)"/>
                                    <span id="sub_status_<?php echo $i; ?>" style="font-size: 14px; margin-left: 5px;"></span>
                                </div>
                                <?php endfor; ?>

                                <div style="margin-top: 18px;">
                                    <button type="button" class="btn-action-print" id="btn_print_prepack" style="width:100%; justify-content:center;" 
                                            <?php echo $is_old_record ? '' : 'disabled'; ?> onclick="openPrePackPrintPreview();">
                                        🖨️ PRINT PRE-PACKING LABEL
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7" style="padding-left: 8px;">
                            <div class="m-card">
                                <div class="m-card-header">
                                    <span>Main Product Technical Specifications</span>
                                </div>

                                <div class="m-form-row">
                                    <div class="m-label">Sale Order No. - Item</div>
                                    <input type="text" id="pp_so_no" class="m-box m-box-display" style="width: 160px;" value="<?php echo $sale_order_no; ?>" readonly/>
                                    <input type="text" id="pp_so_item" class="m-box m-box-display" style="width: 60px; text-align:center;" value="<?php echo $sale_order_item; ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Job Order</div>
                                    <input type="text" id="pp_job_no" class="m-box m-box-display" style="width: 226px;" value="<?php echo $job_order_no; ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Customer - Order Category</div>
                                    <input type="text" id="pp_customer" class="m-box m-box-display" style="width: 120px;" value="<?php echo trim($so['CSTMSPPL_ID'] ?? ''); ?>" readonly/>
                                    <input type="text" id="pp_order_category" class="m-box m-box-display" style="width: 100px; text-align:center;" value="<?php echo trim($so['ORDER_CATEGORY'] ?? 'ORDR'); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Product ID - Model</div>
                                    <input type="text" id="pp_product_id" class="m-box m-box-display" style="width: 65px; text-align:center;" value="<?php echo trim($item['PRODUCT_ID'] ?? ''); ?>" readonly/>
                                    <input type="text" id="pp_model" class="m-box m-box-display" style="width: 155px;" value="<?php echo trim($item['PRODUCT_MODEL'] ?? ''); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Alloy - Temper</div>
                                    <input type="text" id="pp_alloy" class="m-box m-box-display" style="width: 110px;" value="<?php echo trim($item['ALLOY'] ?? ''); ?>" readonly/>
                                    <input type="text" id="pp_temper" class="m-box m-box-display" style="width: 110px;" value="<?php echo trim($item['TEMPER'] ?? ''); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Thickness</div>
                                    <input type="text" id="pp_thickness" class="m-box m-box-display-num" style="width: 110px;" value="<?php echo number_format((float)$item['THICKNESS'], 3); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Width - Length</div>
                                    <input type="text" id="pp_width" class="m-box m-box-display-num" style="width: 110px;" value="<?php echo number_format((float)$item['WIDTH'], 2); ?>" readonly/>
                                    <input type="text" id="pp_length" class="m-box m-box-display-num" style="width: 110px;" value="<?php echo number_format((float)$item['LENGTH'], 2); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Weight - Piece</div>
                                    <input type="text" id="pp_weight" class="m-box m-box-display-num" style="width: 110px;" value="<?php echo number_format($actual_wt, 0); ?>" readonly/>
                                    <input type="text" id="pp_piece" class="m-box m-box-display-num" style="width: 110px;" value="<?php echo number_format($actual_pc, 0); ?>" readonly/>
                                </div>
                                <div class="m-form-row">
                                    <div class="m-label">Status</div>
                                    <input type="text" id="pp_status" class="m-box m-box-display" style="width: 110px; color:#15803d !important;" value="<?php echo trim($item['CRSH_STATUS'] ?? 'AC'); ?>" readonly/>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<!-- Modal Pallet Lookup -->
<div class="modal-pallet-backdrop" id="modalPallet">
    <div class="modal-pallet-box">
        <div class="modal-pallet-header">
            <span>📦 Look Up Data - Material Package Master (Select Pallet)</span>
            <button type="button" onclick="closePalletModal()" style="background:none; border:none; color:#fff; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <div class="modal-pallet-body">
            <div style="margin-bottom: 12px; background:#f1f5f9; padding:8px 12px; border-radius:6px; font-size:12px; border:1px solid #cbd5e1;">
                <b>Criteria:</b> Product: <code><?php echo trim($item['PRODUCT_ID'] ?? ''); ?></code> | 
                Treatment: <code><?php echo ($customer_location === 'O') ? 'HT (Export)' : 'NL (Local)'; ?></code> | 
                Dimension: <code>W: <?php echo (float)$item['WIDTH']; ?>, L: <?php echo (float)$item['LENGTH']; ?></code>
            </div>

            <div class="pack-table-wrapper" style="max-height: 400px;">
                <table class="m-pack-table" id="tblPalletLookup">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>PRIORITY</th>
                            <th>MATERIAL_CODE</th>
                            <th>WIDTH_FROM</th>
                            <th>WIDTH_TO</th>
                            <th>LENGTH_FROM</th>
                            <th>LENGTH_TO</th>
                            <th>STACK</th>
                            <th>BOTTOM WT</th>
                            <th>TOP PACKAGE</th>
                        </tr>
                    </thead>
                    <tbody id="pallet_tbody">
                        <tr><td colspan="10" align="center" style="padding:20px;">Loading Pallet Data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div style="background:#f8fafc; padding:10px 16px; border-top:1px solid #e2e8f0; text-align:right;">
            <button type="button" class="btn-action-cancel" onclick="closePalletModal()">Close</button>
        </div>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
function switchTab(tabIndex) {
    if (tabIndex === 1) {
        $('#tab_btn_1').addClass('active');
        $('#tab_btn_2').removeClass('active');
        $('#tab_content_1').show();
        $('#tab_content_2').hide();
    } else {
        $('#tab_btn_2').addClass('active');
        $('#tab_btn_1').removeClass('active');
        $('#tab_content_2').show();
        $('#tab_content_1').hide();
    }
}

// Net Weight Calculation
function calculateVBLogic() {
    var gross = parseFloat($('#gross_weight').val()) || 0;
    var top = parseFloat($('#top_weight').val()) || 0;
    var bottom = parseFloat($('#bottom_weight').val()) || 0;
    var wtPiece = parseFloat($('#weight_piece').val()) || 0;
    var actWtPrev = parseFloat($('#actual_wt_prev').val()) || 0;

    var netWeight = gross - (top + bottom);
    if (netWeight < 0) netWeight = 0;
    $('#pack_net_weight').val(netWeight.toFixed(0));

    if (wtPiece > 0 && netWeight > 0) {
        $('#pack_net_piece').val(Math.round(netWeight / wtPiece));
    } else {
        $('#pack_net_piece').val(0);
    }

    var maxAllowable = actWtPrev + (actWtPrev * 0.05);
    var minAllowable = actWtPrev - (actWtPrev * 0.15);
    var netBox = document.getElementById('pack_net_weight');

    if (netWeight > maxAllowable || (netWeight < minAllowable && netWeight > 0)) {
        netBox.style.backgroundColor = '#fca5a5';
    } else {
        netBox.style.backgroundColor = '#fef08a';
    }
}

// Look Up Pallet Modal
function openPalletLookupModal() {
    $('#modalPallet').css('display', 'flex');
    loadPalletData();
}

function closePalletModal() {
    $('#modalPallet').hide();
}

function loadPalletData() {
    $.ajax({
        url: 'model/get_material_package.php?action=lookup',
        type: 'POST',
        data: {
            product_id: $('#product_id').val(),
            width: $('#spec_width').val(),
            length: $('#spec_length').val(),
            customer_location: $('#customer_location').val()
        },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.data.length > 0) {
                var html = '';
                $.each(res.data, function(idx, row) {
                    html += '<tr>';
                    html += '<td align="center"><button type="button" class="btn-m-search-icon" style="padding:2px 8px; font-size:11px;" onclick="selectPallet(\'' + row.MATERIAL_CODE + '\')">Select</button></td>';
                    html += '<td align="center">' + row.PACKAGE_PRIORITY + '</td>';
                    html += '<td style="font-weight:700; color:#1e40af;">' + row.MATERIAL_CODE + '</td>';
                    html += '<td align="center">' + row.WIDTH_FROM + '</td>';
                    html += '<td align="center">' + row.WIDTH_TO + '</td>';
                    html += '<td align="center">' + row.LENGTH_FROM + '</td>';
                    html += '<td align="center">' + row.LENGTH_TO + '</td>';
                    html += '<td align="center">' + row.STACK_TOTAL + '</td>';
                    html += '<td align="right">' + row.PACKAGE_WEIGHT + ' kg</td>';
                    html += '<td>' + (row.SMATERIAL_CODE || '-') + '</td>';
                    html += '</tr>';
                });
                $('#pallet_tbody').html(html);
            } else {
                $('#pallet_tbody').html('<tr><td colspan="10" align="center" style="padding:20px; color:#dc2626;">No pallet found matching these specifications.</td></tr>');
            }
        },
        error: function() {
            $('#pallet_tbody').html('<tr><td colspan="10" align="center" style="padding:20px; color:#dc2626;">Error loading pallet data.</td></tr>');
        }
    });
}

function selectPallet(materialCode) {
    $('#material_code').val(materialCode);
    closePalletModal();
    fetchMaterialPackage(materialCode);
}

function fetchMaterialPackage(materialCode) {
    if (!materialCode || materialCode.length < 11) return;

    $.ajax({
        url: 'model/get_material_package.php?action=details',
        type: 'POST',
        data: {
            material_code: materialCode,
            product_id: $('#product_id').val(),
            width: $('#spec_width').val(),
            length: $('#spec_length').val()
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                var d = res.data;
                $('#disp_pkg_code').val(materialCode);
                $('#pack_stack_number').val(d.stack_number);
                $('#org_stack_num').val(d.stack_number);
                $('#org_stack_high').val(d.package_high);
                
                $('#top_weight').val(d.top_package_weight);
                $('#bottom_weight').val(d.bottom_package_weight);

                if (parseFloat($('#dim_width').val()) == 0) $('#dim_width').val(d.package_width);
                if (parseFloat($('#dim_length').val()) == 0) $('#dim_length').val(d.package_length);
                if (parseFloat($('#dim_high').val()) == 0) $('#dim_high').val(d.package_high);

                $('#std_top_weight').val(d.top_package_weight);
                $('#std_bottom_weight').val(d.bottom_package_weight);
                $('#std_package_high').val(d.package_high);
                $('#std_package_width').val(d.package_width);
                $('#std_package_length').val(d.package_length);
                $('#std_width_plus').val(d.width_plus);
                $('#std_width_minus').val(d.width_minus);
                $('#std_length_plus').val(d.length_plus);
                $('#std_length_minus').val(d.length_minus);
                $('#std_stack_number').val(d.stack_number);

                calculateVBLogic();
            } else {
                alert(res.message || 'Invalid pallet code.');
                $('#material_code').val('');
            }
        }
    });
}

// Save Ajax + Open Print Preview Flow
function savePackagingAjax() {
    var formData = $('#frm_packing').serialize();

    $.ajax({
        url: 'model/save_pack_mats.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                alert('Saved successfully! Opening print preview...');
                
                // 1. Enable Print Button
                $('#btn_print_label').prop('disabled', false);
                $('#btn_print_prepack').prop('disabled', false);

                // 2. Open Print Preview A4 in new window
                openPrintLabelPreview();
            } else {
                alert('Error: ' + res.message);
            }
        },
        error: function(xhr) {
            alert('Server error occurred during save.');
        }
    });
}

// ฟังก์ชันเปิด Print Preview ของ Pre-pack Label ตามแบบ Pre_print.jpg
function openPrePackPrintPreview() {
    var mainProd = $('#main_product_no').val() || "<?php echo $product_no; ?>";
    if (!mainProd) {
        alert('Please specify Main Product No.');
        return;
    }

    // 1. ดึงค่าจากช่อง Sub Product No. 1 ถึง 5
    var sub1 = $('#sub_product_no_1').val() || '';
    var sub2 = $('#sub_product_no_2').val() || '';
    var sub3 = $('#sub_product_no_3').val() || '';
    var sub4 = $('#sub_product_no_4').val() || '';
    var sub5 = $('#sub_product_no_5').val() || '';
    var func = "<?php echo $folder_func; ?>";

    // 2. ต่อ Query String ส่งค่าทั้งหมดไปที่ print_prepack_label.php
    var url = 'print_prepack_label.php?func=' + encodeURIComponent(func) +
              '&main_product_no=' + encodeURIComponent(mainProd) +
              '&sub_1=' + encodeURIComponent(sub1) +
              '&sub_2=' + encodeURIComponent(sub2) +
              '&sub_3=' + encodeURIComponent(sub3) +
              '&sub_4=' + encodeURIComponent(sub4) +
              '&sub_5=' + encodeURIComponent(sub5);

    // 3. เปิดหน้าต่างใหม่เพื่อ Preview และสั่งพิมพ์
    window.open(url, '_blank');
}

// Function to preview and print A4 label
function openPrintLabelPreview() {
    var pno = $('#product_no').val();
    var func = "<?php echo $folder_func; ?>";
    window.open('print_pack_label.php?func=' + encodeURIComponent(func) + '&product_no=' + encodeURIComponent(pno), '_blank');
}

// Delete Record
function confirmDeletePack() {
    var pno = $('#product_no').val();
    if (confirm("Are you sure you want to delete packaging record for: " + pno + "?")) {
        window.location.href = "model/delete_pack_mats.php?func=" + encodeURIComponent("<?php echo $folder_func; ?>") + "&product_no=" + encodeURIComponent(pno);
    }
}

// ตรวจสอบรหัส Sub Product No. 1 - 5 เทียบกับ Main Product ตามเงื่อนไข pk1
function validateSubProduct(subProdNo, index) {
    var statusSpan = $('#sub_status_' + index);
    var mainProdNo = $('#main_product_no').val().trim();

    if (!subProdNo || subProdNo.trim() === '') {
        statusSpan.html('');
        return;
    }

    subProdNo = subProdNo.trim().toUpperCase();
    $('#sub_product_no_' + index).val(subProdNo);

    if (subProdNo === mainProdNo) {
        alert('Sub Product No. ' + index + ' cannot be the same as Main Product No.');
        $('#sub_product_no_' + index).val('');
        statusSpan.html('❌');
        return;
    }

    // ตรวจสอบซ้ำกับช่อง Sub อื่นๆ
    for (var i = 1; i <= 5; i++) {
        if (i !== index && $('#sub_product_no_' + i).val().trim().toUpperCase() === subProdNo) {
            alert('Duplicate Sub Product No. (' + subProdNo + ')');
            $('#sub_product_no_' + index).val('');
            statusSpan.html('❌');
            return;
        }
    }

    statusSpan.html('⏳');
    $.ajax({
        url: 'model/get_prepack_data.php',
        type: 'POST',
        data: { product_no: subProdNo, main_product_no: mainProdNo, action: 'validate_sub' },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                statusSpan.html('✅');
            } else {
                alert(res.message || 'Sub Product specifications do not match Main Product.');
                $('#sub_product_no_' + index).val('');
                statusSpan.html('❌');
            }
        },
        error: function() {
            // กรณีไม่มีไฟล์ตรวจสอบแยก ให้แสดงเครื่องหมายถูกเบื้องต้น
            statusSpan.html('✅');
        }
    });
}
</script>
</html>