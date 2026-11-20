<?php
session_start();

$iduser_func     = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func     = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$department_func = isset($_SESSION['DEPARTMENT']) ? strtoupper(trim($_SESSION['DEPARTMENT'])) : '';

if (empty($iduser_func)) {
    header("Location: index.php");
    exit();
}

$current_func  = strtoupper(trim(!empty($folder_func) ? $folder_func : ($_GET['func'] ?? '')));
$is_authorized = ($current_func === 'WH' || $department_func === 'EM' || $department_func === 'IT');

// Filter parameters
$po_search     = isset($_GET['po_search']) ? htmlspecialchars(trim($_GET['po_search']), ENT_QUOTES, 'UTF-8') : '';
$date_from     = isset($_GET['date_from']) ? htmlspecialchars(trim($_GET['date_from']), ENT_QUOTES, 'UTF-8') : '';
$date_to       = isset($_GET['date_to']) ? htmlspecialchars(trim($_GET['date_to']), ENT_QUOTES, 'UTF-8') : '';
$result_filter = isset($_GET['result_filter']) ? htmlspecialchars(trim($_GET['result_filter']), ENT_QUOTES, 'UTF-8') : '';
$tab_status    = in_array($_GET['tab'] ?? '', ['PENDING_PO', 'PENDING_RECV', 'COMPLETED']) ? $_GET['tab'] : 'COMPLETED';
?>
<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD. - PO Receiving (MATS)</title>
    <?php include 'include/header.php'; ?>
    <link href="assets-graph/styles.css" rel="stylesheet" />

    <style>
        body { 
            font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif;
            background-color: #f8fafc; color: #334155; -webkit-font-smoothing: antialiased;
        }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        
        .dashboard-card {
            background: #ffffff; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.08);
            padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0;
        }

        .filter-wrapper {
            display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;
            background: #ffffff; padding: 18px 24px; border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 20px;
        }
        .filter-item { display: flex; flex-direction: column; gap: 6px; }
        .filter-item label {
            font-size: 11.5px; font-weight: 700; color: #64748b; margin: 0;
            letter-spacing: 0.5px; text-transform: uppercase;
        }

        .form-control-minimal {
            border: none !important; border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important; background-color: transparent !important;
            padding-left: 4px !important; padding-right: 4px !important;
            height: 40px; font-size: 14px; font-weight: 600; color: #1e293b;
            transition: border-color 0.2s ease; box-shadow: none !important;
        }
        .form-control-minimal:focus { border-bottom-color: #2563eb !important; outline: none; }

        .btn-search {
            background-color: #1e293b; color: #ffffff; font-weight: 600; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
        }
        .btn-search:hover { background-color: #0f172a; color: #ffffff; transform: translateY(-1px); }

        .btn-create-rc {
            background-color: #059669; color: #ffffff; font-weight: 700; font-size: 13px;
            letter-spacing: 0.5px; height: 40px; padding: 0 20px; border-radius: 8px; border: none; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        }
        .btn-create-rc:hover { background-color: #047857; color: #ffffff; transform: translateY(-1px); text-decoration: none; }

        /* Status Tabs */
        .status-tabs {
            display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;
        }
        .tab-btn {
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 700;
            text-decoration: none; color: #64748b; background: #f1f5f9; border: 1px solid transparent;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { background: #e2e8f0; color: #1e293b; text-decoration: none; }
        .tab-btn.active-pending { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .tab-btn.active-completed { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }

        /* Badges */
        .badge-inv {
            display: inline-block; background-color: #ecfdf5; color: #047857; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #a7f3d0; transition: all 0.2s ease;
        }
        .badge-inv:hover { background-color: #047857; color: #ffffff; }

        .badge-po {
            display: inline-block; background-color: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12.5px;
            padding: 4px 10px; border-radius: 6px; border: 1px solid #bfdbfe;
        }

        .badge-status { font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 4px; display: inline-block; }
        .badge-status-ac { background-color: #dcfce7; color: #15803d; }
        .badge-status-rj { background-color: #fee2e2; color: #991b1b; }
        .badge-status-op { background-color: #dbeafe; color: #1d4ed8; }

        /* Table Alignment */
        #po_table { width: 100% !important; margin: 0 !important; border-collapse: collapse; }
        #po_table th {
            background-color: #f8fafc; color: #475569; font-weight: 700; font-size: 11.5px;
            text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 8px !important;
            border-bottom: 2px solid #e2e8f0; white-space: nowrap;
        }
        #po_table td {
            padding: 10px 8px !important; vertical-align: middle; font-size: 13px;
            border-bottom: 1px solid #f1f5f9; white-space: nowrap;
        }
        #po_table tbody tr { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        #po_table tbody tr:hover td { background-color: #f0f9ff !important; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'A5'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="purchase_order_receive_mats.php?func=<?php echo $folder_func; ?>">
                        Purchase Order Receiving Management (MATS)
                        <span class="badge" style="background-color:<?php echo $is_authorized ? '#059669' : '#64748b'; ?>; font-size:11px; margin-left:8px;">
                            <?php echo $is_authorized ? 'AUTHORIZED (WH/EM)' : 'VIEW ONLY'; ?>
                        </span>
                    </a>
                </div>
                <?php include 'include/navbar.php'; ?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <!-- Filter Bar -->
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1.5; min-width: 220px;">
                    <label for="po_search">Search Record No / PO / Item / Invoice / Vendor</label>
                    <input class="form-control-minimal" name="po_search" id="po_search" type="text" 
                           placeholder="Enter RD-26-XXXX, PO No, Item No..." value="<?php echo $po_search; ?>" 
                           oninput="this.value = this.value.toUpperCase()" onchange="search_po()"/>
                </div>
                <?php if ($tab_status === 'COMPLETED'): ?>
                <div class="filter-item" style="flex: 0.9; min-width: 140px;">
                    <label for="result_filter">Inspect Result</label>
                    <select class="form-control" name="result_filter" id="result_filter" onchange="search_po()" style="height:40px; font-weight:600;">
                        <option value="">-- ALL RESULTS --</option>
                        <option value="ACCEPT" <?php echo $result_filter === 'ACCEPT' ? 'selected' : ''; ?>>ACCEPT</option>
                        <option value="REJECT" <?php echo $result_filter === 'REJECT' ? 'selected' : ''; ?>>REJECT</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-item" style="flex: 0.9; min-width: 140px;">
                    <label for="date_from"><?php echo $tab_status === 'PENDING_PO' ? 'Due Date From' : 'Receive From'; ?></label>
                    <input class="form-control" name="date_from" id="date_from" type="date" value="<?php echo $date_from; ?>" onchange="search_po()" style="height:40px;"/>
                </div>
                <div class="filter-item" style="flex: 0.9; min-width: 140px;">
                    <label for="date_to"><?php echo $tab_status === 'PENDING_PO' ? 'Due Date To' : 'Receive To'; ?></label>
                    <input class="form-control" name="date_to" id="date_to" type="date" value="<?php echo $date_to; ?>" onchange="search_po()" style="height:40px;"/>
                </div>
                <div class="filter-item" style="display: flex; flex-direction: row; gap: 8px;">
                    <button class="btn btn-search" onclick="search_po()">SEARCH</button>
                    <?php if ($is_authorized): ?>
                        <a href="purchase_order_entry_mats.php?func=<?php echo $folder_func; ?>" class="btn-create-rc">
                            + NEW RECEIVE (RD)
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Table Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        <!-- Status Tabs -->
                        <div class="status-tabs">
                            <a href="javascript:void(0)" onclick="switch_tab('COMPLETED')" 
                               class="tab-btn <?php echo $tab_status === 'COMPLETED' ? 'active-completed' : ''; ?>">
                                <span>✅ รายการที่บันทึกเสร็จแล้ว (Completed - PRCHRCRD1)</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switch_tab('PENDING_PO')" 
                               class="tab-btn <?php echo $tab_status === 'PENDING_PO' ? 'active-pending' : ''; ?>">
                                <span>⏳ รอดำเนินการ - PO ค้างรับ (PURREQ)</span>
                            </a>
                            <a href="javascript:void(0)" onclick="switch_tab('PENDING_RECV')" 
                               class="tab-btn <?php echo $tab_status === 'PENDING_RECV' ? 'active-pending' : ''; ?>">
                                <span>🚚 รอดำเนินการ - รับเข้า MP2 แล้ว (PORECEIV)</span>
                            </a>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:16px;">
                                <?php
                                    if ($tab_status === 'COMPLETED') echo 'Completed PO Receiving Records (ประวัติการตรวจรับสินค้า)';
                                    elseif ($tab_status === 'PENDING_PO') echo 'Pending Purchase Orders (รายการ PO ที่ยังรับไม่ครบจาก MP2)';
                                    else echo 'MP2 Received Items (รายการที่รับเข้า MP2 แล้ว รอบันทึกเข้า MATS)';
                                ?>
                            </h4>
                            <span style="font-size:12px; color:#64748b;">
                                <?php echo $tab_status === 'COMPLETED' ? '* คลิกที่รายการเพื่อดูรายละเอียด แก้ไข หรือลบข้อมูล' : '* คลิกที่รายการเพื่อดึงข้อมูลไปสร้างเอกสารรับเข้า'; ?>
                            </span>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="po_table" class="table">
                                <?php
                                    include("dbcon_mats-new.php");
                                    $conn_mats = $conn; unset($conn);
                                    include("dbcon_mp2_mats.php");
                                    $conn_mp2 = $conn; unset($conn);

                                    require_once("model/po_receive_model.php");
                                    $poModel = new PoReceiveModel($conn_mats, $conn_mp2);
                                    $rows = $poModel->po_get_receive_list([
                                        'tab_status'    => $tab_status,
                                        'search'        => $po_search,
                                        'date_from'     => $date_from,
                                        'date_to'       => $date_to,
                                        'result_filter' => $result_filter
                                    ]);
                                ?>

                                <?php if ($tab_status === 'COMPLETED'): ?>
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Record No.</th>
                                            <th style="text-align: center;">Receive Date</th>
                                            <th style="text-align: center;">PO No.</th>
                                            <th style="text-align: center;">Item No.</th>
                                            <th style="text-align: left;">Description</th>
                                            <th style="text-align: left;">Vendor</th>
                                            <th style="text-align: center;">Invoice No.</th>
                                            <th style="text-align: center;">Receipt #</th>
                                            <th style="text-align: right;">Qty Received</th>
                                            <th style="text-align: center;">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rows as $row): 
                                        $recNo   = htmlspecialchars(trim($row['RECORDNUM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $rawDate = !empty($row['DATERECEIVED']) ? date('Y-m-d', strtotime($row['DATERECEIVED'])) : '';
                                        $recDate = !empty($row['DATERECEIVED']) ? date('d/m/Y', strtotime($row['DATERECEIVED'])) : '-';
                                        $poNo    = htmlspecialchars(trim($row['PONUM'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $itemNo  = htmlspecialchars(trim($row['ITEMNUM'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $desc    = htmlspecialchars(trim($row['DESCRIPTIONONPO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $vendor  = htmlspecialchars(trim($row['VENDORNAME'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $invNo   = htmlspecialchars(trim($row['INVOICENUM'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $rcpNum  = (int)$row['RECEIPTNUM'];
                                        $qty     = number_format((float)$row['QTYRECEIVED'], 2);
                                        $uop     = htmlspecialchars(trim($row['UOP'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $resText = strtoupper(trim($row['RECEIVEDRESULT'] ?? 'ACCEPT'));
                                        $resCls  = ($resText === 'REJECT') ? 'badge-status-rj' : 'badge-status-ac';
                                    ?>
                                        <tr onclick="go_to_detail('<?php echo $recNo; ?>')">
                                            <td align="center"><span class="badge-inv"><?php echo $recNo; ?></span></td>
                                            <td align="center" data-order="<?php echo $rawDate; ?>" style="font-weight:600; color:#475569;"><?php echo $recDate; ?></td>
                                            <td align="center" style="font-weight:700;"><?php echo $poNo; ?></td>
                                            <td align="center" style="font-weight:600;"><?php echo $itemNo; ?></td>
                                            <td align="left"><?php echo $desc; ?></td>
                                            <td align="left"><?php echo $vendor; ?></td>
                                            <td align="center"><?php echo $invNo; ?></td>
                                            <td align="center"><?php echo $rcpNum; ?></td>
                                            <td align="right" style="font-weight:700; color:#0f172a;"><?php echo "{$qty} {$uop}"; ?></td>
                                            <td align="center"><span class="badge-status <?php echo $resCls; ?>"><?php echo $resText; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>

                                <?php elseif ($tab_status === 'PENDING_PO'): ?>
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">PO No.</th>
                                            <th style="text-align: center;">Seq</th>
                                            <th style="text-align: center;">Item No.</th>
                                            <th style="text-align: left;">Description</th>
                                            <th style="text-align: left;">Vendor</th>
                                            <th style="text-align: right;">Requested</th>
                                            <th style="text-align: right;">Received</th>
                                            <th style="text-align: right;">Balance</th>
                                            <th style="text-align: center;">Due Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rows as $row):
                                        $poNo    = htmlspecialchars(trim($row['PONUM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $seqNo   = (float)$row['SEQNUM'];
                                        $itemNo  = htmlspecialchars(trim($row['ITEMNUM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $desc    = htmlspecialchars(trim($row['DESCRIPTIONONPO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $vendor  = htmlspecialchars(trim($row['VENDORNAME'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $reqQty  = (float)$row['QTYREQUESTED'];
                                        $rcvQty  = (float)$row['QTYRECEIVED'];
                                        $balQty  = $reqQty - $rcvQty;
                                        $uop     = htmlspecialchars(trim($row['UOP'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $dueDate = !empty($row['DUEDATE']) ? date('d/m/Y', strtotime($row['DUEDATE'])) : '-';
                                        $clickFn = $is_authorized ? "onclick=\"go_to_entry_po('po', '{$poNo}', '{$seqNo}', '{$itemNo}', 1)\"" : "";
                                    ?>
                                        <tr <?php echo $clickFn; ?>>
                                            <td align="center"><span class="badge-po"><?php echo $poNo; ?></span></td>
                                            <td align="center"><?php echo $seqNo; ?></td>
                                            <td align="center" style="font-weight:700;"><?php echo $itemNo; ?></td>
                                            <td align="left"><?php echo $desc; ?></td>
                                            <td align="left"><?php echo $vendor; ?></td>
                                            <td align="right"><?php echo number_format($reqQty, 2); ?></td>
                                            <td align="right"><?php echo number_format($rcvQty, 2); ?></td>
                                            <td align="right" style="font-weight:700; color:#dc2626;"><?php echo number_format($balQty, 2) . " {$uop}"; ?></td>
                                            <td align="center"><?php echo $dueDate; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>

                                <?php else: ?>
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">PO No.</th>
                                            <th style="text-align: center;">Receipt #</th>
                                            <th style="text-align: center;">Item No.</th>
                                            <th style="text-align: left;">Description</th>
                                            <th style="text-align: left;">Vendor</th>
                                            <th style="text-align: center;">Invoice No.</th>
                                            <th style="text-align: right;">Qty in MP2</th>
                                            <th style="text-align: center;">MP2 Recv Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rows as $row):
                                        $poNo    = htmlspecialchars(trim($row['PONUM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $seqNo   = (float)$row['SEQNUM'];
                                        $rcpNum  = (int)$row['RECEIPTNUM'];
                                        $itemNo  = htmlspecialchars(trim($row['ITEMNUM'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $desc    = htmlspecialchars(trim($row['DESCRIPTIONONPO'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $vendor  = htmlspecialchars(trim($row['VENDORNAME'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $invNo   = htmlspecialchars(trim($row['INVOICENUM'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $qty     = number_format((float)$row['QTYRECEIVED'], 2);
                                        $uop     = htmlspecialchars(trim($row['UOP'] ?? ''), ENT_QUOTES, 'UTF-8');
                                        $recDate = !empty($row['DATERECEIVED']) ? date('d/m/Y', strtotime($row['DATERECEIVED'])) : '-';
                                        $clickFn = $is_authorized ? "onclick=\"go_to_entry_po('poreceiv', '{$poNo}', '{$seqNo}', '{$itemNo}', '{$rcpNum}')\"" : "";
                                    ?>
                                        <tr <?php echo $clickFn; ?>>
                                            <td align="center"><span class="badge-po"><?php echo $poNo; ?></span></td>
                                            <td align="center" style="font-weight:700;"><?php echo $rcpNum; ?></td>
                                            <td align="center" style="font-weight:700;"><?php echo $itemNo; ?></td>
                                            <td align="left"><?php echo $desc; ?></td>
                                            <td align="left"><?php echo $vendor; ?></td>
                                            <td align="center"><?php echo $invNo; ?></td>
                                            <td align="right" style="font-weight:700; color:#059669;"><?php echo "{$qty} {$uop}"; ?></td>
                                            <td align="center"><?php echo $recDate; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="func" value="<?php echo $folder_func; ?>"/>
        <input type="hidden" id="tab_status" value="<?php echo $tab_status; ?>"/>
        <?php include 'include/content-footer.php'; ?>
    </div>
</div>

<?php include 'include/footer.php'; ?>

<script type="text/javascript">
$(document).ready(function() {
    var table = $("#po_table").DataTable({
        autoWidth: false,
        scrollX: true,
        scrollCollapse: true,
        paging: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        order: []
    });

    $(window).on('resize', function () { table.columns.adjust(); });
    setTimeout(function() { table.columns.adjust(); }, 200);
});

function switch_tab(tabName) {
    document.getElementById("tab_status").value = tabName;
    search_po();
}

function search_po() {
    var func  = document.getElementById("func").value;
    var kw    = document.getElementById("po_search").value;
    var dfrom = document.getElementById("date_from").value;
    var dto   = document.getElementById("date_to").value;
    var tab   = document.getElementById("tab_status").value;
    var resEl = document.getElementById("result_filter");
    var rfilt = resEl ? resEl.value : '';
    
    var url = 'purchase_order_receive_mats.php?func=' + encodeURIComponent(func) +
              '&po_search=' + encodeURIComponent(kw) +
              '&result_filter=' + encodeURIComponent(rfilt) +
              '&date_from=' + encodeURIComponent(dfrom) +
              '&date_to=' + encodeURIComponent(dto) +
              '&tab=' + encodeURIComponent(tab);
    window.location.assign(url);
}

function go_to_detail(recordNo) {
    var func = document.getElementById("func").value;
    window.location.assign('purchase_order_detail_mats.php?func=' + encodeURIComponent(func) + '&record_num=' + encodeURIComponent(recordNo));
}

function go_to_entry_po(mode, poNo, seqNo, itemNo, receiptNum) {
    var func = document.getElementById("func").value;
    var url = 'purchase_order_entry_mats.php?func=' + encodeURIComponent(func) +
              '&mode=' + encodeURIComponent(mode) +
              '&po_num=' + encodeURIComponent(poNo) +
              '&seq_num=' + encodeURIComponent(seqNo) +
              '&item_num=' + encodeURIComponent(itemNo) +
              '&receipt_num=' + encodeURIComponent(receiptNum);
    window.location.assign(url);
}
</script>
</body>
</html>