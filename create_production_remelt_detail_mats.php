<?php
session_start();
$iduser_func = $_SESSION['ID'] ?? 'SYSTEM';
$folder_func = $_SESSION['FUNC'] ?? 'mats';

// รับค่า pno หรือ rno จาก URL ป้องกัน Undefined Key และ null warning
$raw_url = $_GET['pno'] ?? $_GET['rno'] ?? '';
$req_no_url = htmlspecialchars(trim($raw_url), ENT_QUOTES, 'UTF-8');

// ถ้าค่าที่ส่งมาเป็นคำว่า 'undefined' หรือ 'null' ให้ล้างเป็นค่าว่าง
if ($req_no_url === 'undefined' || $req_no_url === 'null') {
    $req_no_url = '';
}
?>

<!doctype html>
<html lang="en">
<head>
    <title>Create Remelt Requisition</title>
    <?php include 'include/header.php';?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .card-custom { background: #ffffff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-title-source { color: #0284c7; border-bottom: 2px solid #bae6fd; padding-bottom: 8px; font-weight: 700; font-size: 16px; margin-bottom: 16px; }
        .card-title-g1 { color: #1e40af; border-bottom: 2px solid #bfdbfe; padding-bottom: 8px; font-weight: 700; font-size: 16px; margin-bottom: 16px; }
        .card-title-g2 { color: #0369a1; border-bottom: 2px solid #bae6fd; padding-bottom: 8px; font-weight: 700; font-size: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .form-control-custom { width: 100%; height: 38px; padding: 6px 12px; font-size: 14px; font-weight: 600; color: #0f172a; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; }
        .form-control-readonly { background-color: #f1f5f9; font-weight: bold; color: #1e293b; }
        .btn-submit { background-color: #2563eb; color: #fff; font-weight: 600; padding: 10px 24px; border-radius: 6px; border: none; }
        .btn-submit:hover { background-color: #1d4ed8; }
        .btn-back { background-color: #64748b; color: #fff; font-weight: 600; padding: 10px 20px; border-radius: 6px; border: none; }
        .source-radio-group label { margin-right: 20px; font-weight: 600; cursor: pointer; }
        #tblRemeltDetails thead th {
            color: #ffffff !important;
            background-color: #1e40af !important;
            vertical-align: middle;
            text-align: center;
            font-weight: 700;
        }
        #tblRemeltDetails tbody td {
            vertical-align: middle;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <?php $menu = 'mats'; ?>
    <?php include 'include/'.$folder_func.'/navigation.php'; ?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header">
                    <a class="navbar-brand" style="font-weight:700; color:#1e293b; font-size: 20px;" href="remelt_production_mats.php?func=<?php echo $folder_func ?>">Product Remelt Requisition</a>
                </div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>
        
        <div class="container-fluid" style="padding-top: 20px;">
            <form id="formRemelt">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin:0; font-weight:700;">📝 Create Remelt Requisition</h3>
                    <div>
                        <button type="button" class="btn btn-back" onclick="window.history.back();">⬅️ Back</button>
                        <button type="submit" class="btn btn-submit" id="btnSave"><i class="fa-solid fa-floppy-disk"></i> 💾 Save Data</button>
                    </div>
                </div>

                <!-- Source Selection -->
                <div class="card-custom">
                    <h4 class="card-title-source">🔍 Source Selection & Document No.</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Request No (Auto Generate)</label>
                                <input type="text" id="request_no" name="request_no" class="form-control-custom form-control-readonly" value="RM-<?php echo date('y'); ?>-XXXX (Auto Generate)" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Select Source Origin *</label>
                                <div class="source-radio-group" style="padding-top: 6px;">
                                    <label><input type="radio" name="source_type" value="FG" checked> STOCK (FG)</label>
                                    <label><input type="radio" name="source_type" value="CC"> PRODUCTION (CC)</label>
                                    <label><input type="radio" name="source_type" value="CO"> CASTER (CO)</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label id="label_select_item">Select Item No (FG) *</label>
                                <select id="select_item" name="select_item" class="form-control-custom" style="width: 100%;">
                                    <option value="">-- Choose Item --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 1: Product Information -->
                <div class="card-custom">
                    <h4 class="card-title-g1">📦 Product Information</h4>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Product No</label>
                                <input type="text" id="product_no" name="product_no" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Coil No</label>
                                <input type="text" id="coil_no" name="coil_no" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label id="label_product_id">Product ID</label>
                                <input type="text" id="product_id" name="product_id" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Job Order</label>
                                <input type="text" id="job_order" name="job_order" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Alloy</label>
                                <input type="text" id="alloy" name="alloy" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Temper</label>
                                <input type="text" id="temper" name="temper" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Grade</label>
                                <input type="text" id="grade" name="grade" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Surface Grade</label>
                                <input type="text" id="surface_grade" name="surface_grade" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Metallurgical Grade</label>
                                <input type="text" id="metallurgical_grade" name="metallurgical_grade" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Thickness</label>
                                <input type="number" step="0.01" id="thickness" name="thickness" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Width</label>
                                <input type="number" step="0.01" id="width" name="width" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Length</label>
                                <input type="number" step="0.01" id="length" name="length" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Product Weight (Kg)</label>
                                <input type="number" step="0.01" id="product_weight" name="product_weight" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group">
                                <label>Remelt Reason *</label>
                                <input type="text" id="remelt_reason" name="remelt_reason" class="form-control-custom" placeholder="กรอกเหตุผลการ Remelt..." required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GROUP 2: Sale Order & Job Order Information -->
                <div class="card-custom">
                    <h4 class="card-title-g2">📑 Sale Order & Job Order Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer ID (CSTMSPPL_ID)</label>
                                <input type="text" id="cstm_id" name="cstm_id" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer PO No (CTM2_PONO)</label>
                                <input type="text" id="po_no" name="po_no" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Job Order</label>
                                <input type="text" id="job_order_g2" name="job_order_g2" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Job Release Weight (Kg)</label>
                                <input type="number" step="0.01" id="job_weight" name="job_weight" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Sale Order No</label>
                                <input type="text" id="saleorder_no" name="saleorder_no" class="form-control-custom" value="-" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Order Weight (Kg)</label>
                                <input type="number" step="0.01" id="order_weight" name="order_weight" class="form-control-custom" value="0.00" readonly style="background-color: #f8fafc;">
                            </div>
                        </div>
                    </div>
                </div>     
                <input type="hidden" id="original_status" name="original_status" value="">
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>

</body>
<?php include 'include/footer.php';?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {

    $('#select_item').select2();
    loadSourceItems('FG');

    function clearFormInputs() {
        $('#product_no, #coil_no, #product_id, #job_order, #job_order_g2, #alloy, #temper, #grade, #surface_grade, #metallurgical_grade').val('-');
        $('#cstm_id, #po_no, #saleorder_no').val('-');
        $('#thickness, #width, #length, #product_weight, #job_weight, #order_weight').val('0.00');
        $('#original_status').val('');
        $('#remelt_reason').val('');
        $('#select_item').val('').trigger('change.select2');
    }

    function loadSourceItems(source) {
        $.ajax({
            url: 'model/get_source_items.php',
            type: 'GET',
            data: { source: source },
            dataType: 'json',
            success: function(data) {
                // เก็บ รายการ Primary Key ที่มีอยู่แล้วบน HTML Table/List ในหน้าจอ
                let usedItems = [];
                $('#tblRemeltDetails tbody tr').each(function() {
                    let itemVal = $(this).find('.item-key').text().trim();
                    if (itemVal) usedItems.push(itemVal);
                });

                let options = '<option value="">-- Choose Item --</option>';
                $.each(data, function(i, item) {
                    // ถ้าค่า item.id ไม่ได้อยู่ใน usedItems จึงค่อยเอามาสร้าง <option>
                    if (!usedItems.includes(item.id)) {
                        options += `<option value="${item.id}">${item.text}</option>`;
                    }
                });
                $('#select_item').html(options).trigger('change.select2');
            }
        });
    }

    $('input[name="source_type"]').change(function() {
        let sourceType = $(this).val();
        clearFormInputs();

        if (sourceType === 'CO') {
            $('#label_select_item').text('Select Coil No (CO) *');
            $('#label_product_id').text('Coil Type');
        } else {
            $('#label_select_item').text('Select Item No (' + sourceType + ') *');
            $('#label_product_id').text('Product ID');
        }

        loadSourceItems(sourceType);
    });

    $('#select_item').change(function() {
        let itemNo = $(this).val();
        let source = $('input[name="source_type"]:checked').val();

        if (itemNo) {
            $.ajax({
                url: 'model/get_source_items.php',
                type: 'GET',
                data: { source: source, item_no: itemNo },
                dataType: 'json',
                success: function(res) {
                    if (res) {
                        $('#product_no').val(res.product_no);
                        $('#coil_no').val(res.coil_no);
                        $('#product_id').val(res.product_id_or_type);
                        $('#job_order').val(res.job_order);
                        $('#alloy').val(res.alloy);
                        $('#temper').val(res.temper);
                        $('#grade').val(res.grade);
                        $('#surface_grade').val(res.surface_grade);
                        $('#metallurgical_grade').val(res.metallurgical_grade);
                        
                        $('#thickness').val(res.thickness);
                        $('#width').val(res.width);
                        $('#length').val(res.length);
                        $('#product_weight').val(res.product_weight);

                        $('#original_status').val(res.original_status || '');

                        $('#job_order_g2').val(res.job_order);
                        $('#cstm_id').val(res.cstm_id);
                        $('#po_no').val(res.po_no);
                        $('#job_weight').val(res.job_weight);
                        $('#saleorder_no').val(res.saleorder_no);
                        $('#order_weight').val(res.order_weight);
                    }
                }
            });
        }
    });

    // Submit Form
    $('#formRemelt').on('submit', function(e) {
        e.preventDefault();

        let selectedItem = $('#select_item').val();
        let sourceType   = $('input[name="source_type"]:checked').val();
        let reason       = $('#remelt_reason').val().trim();

        if (!selectedItem || selectedItem === '') {
            let itemLabelText = (sourceType === 'CO') ? 'Select Coil No (CO)' : 'Select Item No (' + sourceType + ')';
            alert('กรุณาเลือก ' + itemLabelText + ' ก่อนสั่งบันทึกข้อมูล!');
            $('#select_item').select2('open');
            return false;
        }

        if (!reason || reason === '-') {
            alert('กรุณากรอก Remelt Reason (เหตุผลการ Remelt) ก่อนสั่งบันทึกข้อมูล!');
            $('#remelt_reason').focus();
            return false;
        }

        $.ajax({
            url: 'model/add_production_remelt_detail_mats.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            beforeSend: function() {
                $('#btnSave').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');
            },
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.href = 'remelt_production_detail_mats.php?func=<?php echo $folder_func; ?>&rno=' + response.request_no;
                } else {
                    alert('เกิดข้อผิดพลาด: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์!');
            },
            complete: function() {
                $('#btnSave').prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Data');
            }
        });
    });

});
</script>
</html>