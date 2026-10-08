<?php
// เริ่ม session ก่อน ANY output
session_start();
// ถ้าไม่มี Session หรือไม่ได้ Login ให้กลับไปหน้า index.php
if (!isset($_SESSION['ID'])) {
    header("Location: index.php");
    exit();
}

// ดึงค่าและตรวจสอบข้อมูลนำเข้าเพื่อความปลอดภัย (Sanitize Input)
if(!isset($_GET['d'])){
    $d = date('Y-m-d');
} else {
    $d = $_GET['d'];
}

// รับค่า so (รองรับ SALEORDER_NO)
$so_search = !isset($_GET['so']) ? '' : htmlspecialchars(trim($_GET['so']), ENT_QUOTES, 'UTF-8');

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func  = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
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
        .main-panel {
            background-color: #f8fafc !important;
        }
        .main-panel .content { padding: 20px 20px !important; }
        
        /* Dashboard Card Container */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }

        .tab-menu-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #ffffff;
            padding: 12px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }        

        /* Filter Section Layout */
        .filter-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
            background: #ffffff;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-item label {
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Form Styling Control */
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            height: 42px;
            padding: 8px 12px;
            font-size: 15px;
            color: #334155;
            transition: all 0.2s ease;
            box-shadow: none;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }

        /* ช่องป้อน ค้นหาแบบไร้ขอบสี่เหลี่ยม */
        .form-control-minimal {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 4px !important;
            padding-right: 4px !important;
            height: 42px;
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            transition: border-color 0.2s ease;
            box-shadow: none !important;
        }
        .form-control-minimal:focus {
            border-bottom-color: #2563eb !important;
            outline: none;
        }

        /* Buttons Styling */
        .btn-search {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            height: 42px;
            padding: 0 24px;
            border-radius: 8px;
            border: none;
            transition: all 0.2s;
        }
        .btn-search:hover {
            background-color: #0f172a;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Modern Table Styles */
        .table-responsive {
            border: none !important;
            margin-top: 15px;
        }
        #user_table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }
        #user_table thead th {
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
        #user_table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            line-height: 1.5;
            color: #334155;
            white-space: nowrap;
        }
        
        #user_table tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }
        #user_table tbody tr:hover td {
            background-color: #f0f9ff !important;
        }

        .btn-change-id {
            background-color: #3b82f6 !important;
            color: #ffffff !important;
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-block;
            text-decoration: none !important;
        }
        .btn-change-id:hover {
            background-color: #2563eb !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }

        .btn-tab-item {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-radius: 8px;
            border: none;
            background: transparent;
            transition: all 0.2s;
        }
        .btn-tab-item:hover {
            background-color: #f1f5f9;
            color: #334155;
        }
        .btn-tab-item.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }        

        /* MODAL STYLES */
        .modal-custom-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 18px 24px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-custom-header h4 {
            margin: 0;
            font-weight: 700;
            font-size: 20px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-custom-header .close {
            color: #ffffff !important;
            opacity: 0.9 !important;
            font-size: 28px !important;
            text-shadow: none;
            transition: all 0.2s;
            outline: none;
        }
        .modal-custom-header .close:hover {
            opacity: 1 !important;
            transform: scale(1.1);
        }

        .btn-save-modal {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            height: 46px !important;
            padding: 0 28px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(29, 78, 216, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        .btn-save-modal:hover {
            background-color: #1e40af !important;
            box-shadow: 0 4px 6px rgba(30, 64, 175, 0.4) !important;
            transform: translateY(-1px);
        }

        .btn-cancel-modal {
            background-color: #ffffff !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 15px !important;
            height: 46px !important;
            padding: 0 22px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            transition: all 0.2s ease !important;
        }
        .btn-cancel-modal:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }
    </style>
</head>
<body>

<div class="wrapper">
    
<?php $menu = 'A1';?>

<?php   
include 'include/'.$folder_func.'/navigation.php';
?>

   <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navigation-example-2">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="maintain_cust_id_saleorder_mats.php?func=<?php echo $folder_func ?>">Maintain Customer ID of Customer Order (Sale Order)</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">

            <div class="tab-menu-wrapper">
                <button onclick="customer_order()" class="btn-tab-item">Customer Order (Sale Order)</button>
                <button onclick="maintain_cust_id_saleorder()" class="btn-tab-item active">Maintain Customer ID of Customer Order (Sale Order)</button>
                <button onclick="maintain_month_cal_price()" class="btn-tab-item">Maintain Monthly Calculate Pricing</button>
                <button onclick="maintain_base_cost()" class="btn-tab-item">Maintain Base Cost and Preminum</button>
            </div>
            
            <div class="filter-wrapper">
                <div class="filter-item" style="flex: 1; min-width: 240px;">
                    <label for="so_no">Sale Order No</label>
                    <input class="form-control-minimal" name="so_no" id="so_no" type="text" placeholder="Enter Sale Order No. to search..." value="<?php echo htmlspecialchars($so_search, ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.toUpperCase()" onchange="search_batch_no()"/>
                </div>
                <div class="filter-item" style="width: 190px;">
                    <label for="pick_date">Select Data Date</label>
                    <input type="date" class="form-control" onchange="window.location.assign(window.location.pathname+'?func=<?php echo $folder_func; ?>&d='+this.value)" id="pick_date"/>
                </div>
                <div class="filter-item">
                    <button class="btn btn-search" id="btn_search_coil" onclick="search_batch_no()">
                        SEARCH
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="dashboard-card">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin:0; font-weight:700; color:#1e293b; font-size:18px;">📋 Sale Order Records (Excluding Invoiced)</h4>
                        </div>
                        
                        <div class="table-responsive table-full-width">
                            <table id="user_table" class="table coil-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th>Sale Order No</th>
                                        <th>Sale Order Date</th>
                                        <th>Customer ID</th>
                                        <th>Customer Name</th>
                                        <th>PO No</th>
                                        <th>Currency ID</th>
                                        <th>Total Amount</th>
                                        <th>Operator</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                <?php
                                    include 'function_mats.php';
                                    include("dbcon_mats-new.php");
                                    ini_set('max_execution_time', 300);

                                    // 1. ดึงรายชื่อลูกค้าทั้งหมดจาก CSSPMSTR1 เพื่อนำไปสร้าง Dropdown Options
                                    $sql_cust = "SELECT CSTMSPPL_ID, CONSIGNEE_COMPANY 
                                                 FROM CSSPMSTR1 
                                                 ORDER BY CSTMSPPL_ID ASC";
                                    $stmt_cust = $conn->prepare($sql_cust);
                                    $stmt_cust->execute();
                                    $customer_options = $stmt_cust->fetchAll(PDO::FETCH_ASSOC);

                                    // 2. ดึงข้อมูลรายการ Sale Order
                                    $params = [];
                                    $extra_where = "";

                                    if (!empty($so_search)) {
                                        $extra_where .= " AND a.SALEORDER_NO LIKE :so";
                                        $params[':so'] = '%' . $so_search . '%';
                                    }

                                    $sql = "SELECT TOP(100)
                                                a.SALEORDER_NO,
                                                a.SALEORDER_DATE,
                                                a.CSTMSPPL_ID,
                                                c.CONSIGNEE_COMPANY,
                                                a.CTM1_PONO,
                                                a.CURRENCY_ID,
                                                a.CTM1_TOTALAMOUNT,
                                                a.CTM1_OPERATOR1        
                                            FROM CSTMORDR1 as a 
                                            LEFT JOIN INVCPRCS2 i ON a.SALEORDER_NO = i.SALEORDER_NO 
                                            LEFT JOIN CSSPMSTR1 c ON a.CSTMSPPL_ID = c.CSTMSPPL_ID
                                            WHERE i.SALEORDER_NO IS NULL {$extra_where}
                                            GROUP BY 
                                                a.SALEORDER_NO,
                                                a.SALEORDER_DATE,
                                                a.CSTMSPPL_ID,
                                                c.CONSIGNEE_COMPANY,
                                                a.CTM1_PONO,
                                                a.CURRENCY_ID,
                                                a.CTM1_TOTALAMOUNT,
                                                a.CTM1_OPERATOR1 
                                            ORDER BY a.SALEORDER_DATE DESC";

                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $order_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>

                                <?php foreach ($order_list as $index => $row): 
                                    $so_no       = htmlspecialchars($row['SALEORDER_NO'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $so_date     = !empty($row['SALEORDER_DATE']) ? date('Y-m-d', strtotime($row['SALEORDER_DATE'])) : '-';
                                    $cstm_id     = htmlspecialchars($row['CSTMSPPL_ID'] ?? '-', ENT_QUOTES, 'UTF-8');
                                    $cstm_name   = htmlspecialchars($row['CONSIGNEE_COMPANY'] ?? '-', ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td align="center"><?php echo $index + 1; ?></td>
                                        <td align="center"><strong><?php echo $so_no; ?></strong></td>
                                        <td align="center"><?php echo $so_date; ?></td>
                                        <td align="center"><span class="badge" style="background-color: #e0f2fe; color: #0369a1; font-weight:700;"><?php echo $cstm_id; ?></span></td>
                                        <td><?php echo $cstm_name; ?></td>
                                        <td><?php echo htmlspecialchars($row['CTM1_PONO'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['CURRENCY_ID'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td align="right"><?php echo number_format((float)($row['CTM1_TOTALAMOUNT'] ?? 0), 2); ?></td>
                                        <td align="center"><?php echo htmlspecialchars($row['CTM1_OPERATOR1'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                        
                                        <td align="center">
                                            <button type="button" class="btn-change-id" 
                                                    onclick="open_change_id_modal('<?php echo addslashes($so_no); ?>', '<?php echo addslashes($cstm_id); ?>', '<?php echo addslashes($cstm_name); ?>')">
                                                ✏️ Change ID
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input style="width: 100%;" class="form-control" id="func" name="func" value="<?php echo $folder_func;?>" type="hidden"/>
        <?php include 'include/content-footer.php';?>
    </div>
</div>

<!-- ==========================================
     POP-UP MODAL: CHANGE CUSTOMER ID (DROPDOWN SELECT)
=========================================== -->
<div class="modal fade" id="changeIdModal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document" style="margin-top: 80px;">
    <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); overflow: hidden;">
      
      <div class="modal-custom-header">
        <h4>✏️ Change Customer ID</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>

      <div class="modal-body" style="padding: 24px; background-color: #f8fafc;">
        <form id="form_change_id">
            <input type="hidden" id="modal_raw_old_cust_id" />

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; color: #475569;">Sale Order No:</label>
                <input type="text" id="modal_so_no" class="form-control" readonly style="background-color: #e2e8f0; font-weight: 700;" />
            </div>

            <!-- แสดงผล Current Customer ID ในรูปแบบ [DOMU] : DOMOD ALUMINIUM LTD -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 700; color: #475569;">Current Customer ID:</label>
                <input type="text" id="modal_old_cust_id" class="form-control" readonly style="background-color: #e2e8f0; font-weight: 600;" />
            </div>

            <!-- แสดงผล Select New Customer ID ในรูปแบบ [DOMU] : DOMOD ALUMINIUM LTD -->
            <div class="form-group" style="margin-bottom: 0;">
                <label style="font-weight: 700; color: #1e293b;">Select New Customer ID:</label>
                <select id="modal_new_cust_id" class="form-control" style="font-weight: 600;" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customer_options as $cust): 
                        $cid   = htmlspecialchars(trim($cust['CSTMSPPL_ID']), ENT_QUOTES, 'UTF-8');
                        $cname = htmlspecialchars(trim($cust['CONSIGNEE_COMPANY']), ENT_QUOTES, 'UTF-8');
                        $display_text = "[{$cid}]" . ($cname !== '' ? " : {$cname}" : "");
                    ?>
                        <option value="<?php echo $cid; ?>">
                            <?php echo $display_text; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
      </div>

      <div class="modal-footer" style="background-color: #ffffff; border-top: 1px solid #e2e8f0; padding: 18px 24px; display: flex; justify-content: flex-end; gap: 12px;">
        <button type="button" class="btn btn-cancel-modal" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-save-modal" onclick="submit_change_id()">💾 Save Changes</button>
      </div>

    </div>
  </div>
</div>

</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">       
var select_date = '<?php echo $d;?>';
pick_date.value = select_date;   

$("#user_table").DataTable({
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    paging: true,
    dom: 'Bfrtip',
    pageLength: 20,
    order:[[2, 'desc']], // เรียงตาม Sale Order Date (index 2) วันล่าสุด
    buttons: []
});

function search_batch_no(){   
    var data_fun = document.getElementById("func").value;
    var data_so  = document.getElementById("so_no").value; 
    window.location.assign('maintain_cust_id_saleorder_mats.php?func='+encodeURIComponent(data_fun)+'&so='+encodeURIComponent(data_so)); 
}    

function maintain_cust_id_saleorder(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_cust_id_saleorder_mats.php?func='+encodeURIComponent(data_fun)); 
}

function customer_order(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('customer_order_mats.php?func='+encodeURIComponent(data_fun)); 
}

function maintain_month_cal_price(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_month_cal_price_mats.php?func='+encodeURIComponent(data_fun)); 
}

function maintain_base_cost(){   
    var data_fun = document.getElementById("func").value;
    window.location.assign('maintain_base_cost_mats.php?func='+encodeURIComponent(data_fun)); 
}

/* ========================================================
   FUNCTIONS FOR CHANGE CUSTOMER ID POP-UP MODAL
======================================================== */
function open_change_id_modal(so_no, cust_id, cust_name) {
    $('#modal_so_no').val(so_no);
    $('#modal_raw_old_cust_id').val(cust_id);
    
    // จัดรูปแบบแสดงผล [DOMU] : DOMOD ALUMINIUM LTD
    var display_current = '[' + cust_id + ']' + (cust_name && cust_name !== '-' ? ' : ' + cust_name : '');
    $('#modal_old_cust_id').val(display_current);
    
    // ตั้งค่า Dropdown ให้เลือกค่าปัจจุบันเริ่มต้น
    $('#modal_new_cust_id').val(cust_id);
    
    $('#changeIdModal').modal('show');
}

function submit_change_id() {
    var so_no       = $('#modal_so_no').val();
    var old_cust_id = $('#modal_raw_old_cust_id').val();
    var new_cust_id = $('#modal_new_cust_id').val();

    if (!new_cust_id || new_cust_id === '') {
        alert('กรุณาเลือก New Customer ID');
        return;
    }

    if (new_cust_id === old_cust_id) {
        alert('Customer ID ใหม่ตรงกับ Customer ID เดิม กรุณาเลือกรายการอื่น');
        return;
    }

    if (confirm('คุณต้องการเปลี่ยน Customer ID ของ Sale Order: ' + so_no + ' เป็น ' + new_cust_id + ' ใช่หรือไม่?')) {
        $.ajax({
            url: 'model/save_change_cust_id_mats.php',
            type: 'POST',
            data: {
                saleorder_no: so_no,
                old_customer_id: old_cust_id,
                new_customer_id: new_cust_id
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert('บันทึกเปลี่ยน Customer ID เรียบร้อยแล้ว');
                    $('#changeIdModal').modal('hide');
                    location.reload();
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (response.message || 'ไม่สามารถบันทึกข้อมูลได้'));
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
            }
        });
    }
}
</script>
</html>