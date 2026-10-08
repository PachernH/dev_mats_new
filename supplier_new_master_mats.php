<?php
// เริ่ม session ก่อน ANY output เพื่อความเสถียรของระบบ
session_start();

include 'function_mats.php';
include 'dbcon_mats-new.php'; // เพิ่มการเชื่อมต่อฐานข้อมูล

$d = !isset($_GET['d']) ? date('Y-m-d') : htmlspecialchars(trim($_GET['d']), ENT_QUOTES, 'UTF-8');
$cs = isset($_GET['CID']) ? htmlspecialchars(trim($_GET['CID']), ENT_QUOTES, 'UTF-8') : '';

$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$folder_func = isset($_SESSION['FUNC']) ? htmlspecialchars($_SESSION['FUNC'], ENT_QUOTES, 'UTF-8') : '';
$line_func = isset($_SESSION['LINE']) ? htmlspecialchars($_SESSION['LINE'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets-graph/styles.css" rel="stylesheet" />
    <style>
        body { font-family: 'Segoe UI', 'Tahoma', 'Sarabun', sans-serif; background-color: #f8fafc; color: #334155; }
        .main-panel { background-color: #f8fafc !important; }
        .main-panel .content { padding: 20px 20px !important; }
        .display-card { background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; border: 1px solid #e2e8f0; }
        .card-title-sub { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
        .info-label { font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; height: 42px; padding: 8px 12px; font-size: 14px; margin-bottom: 15px; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); outline: none; }
        input[readonly] { background-color: #f1f5f9 !important; cursor: not-allowed; }
        .mb-4 { margin-bottom: 1.5rem; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php $menu = 'gp1';?>
    <?php include 'include/'.$folder_func.'/navigation.php';?>

    <div class="main-panel">
        <nav class="navbar navbar-default navbar-fixed">
            <div class="container-fluid">
                <div class="navbar-header"><a class="navbar-brand" style="font-weight:700; color:#1e293b;" href="#">New Supplier Master</a></div>
                <?php include 'include/navbar.php';?>
            </div>
        </nav>

        <div class="container-fluid" style="padding-top: 15px;">
            <div class="row mb-4">
                <div class="col-xs-12">
                    <button class="btn btn-default" onclick="window.location.assign('supplier_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">⬅️ กลับไปหน้าหลัก</button>
                </div>
            </div>
            
            <form id="form_supp" method="POST">
            <div class="row">
                <div class="col-lg-7 col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">🏢 Company Profile & Identity</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-label">Company ID</div>
                                <input type="text" class="form-control" name="CSTMSPPL_ID" id="CSTMSPPL_ID" style="font-weight:600;"/>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Account Code</div>
                                <input type="text" class="form-control" name="ACCOUNT_CODE" id="ACCOUNT_CODE" />
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Tax Registration</div>
                                <input type="text" class="form-control" name="TAX_REGISTRATION" id="TAX_REGISTRATION"/>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">ประเภทภาษีเงินได้หัก ณ ที่จ่าย (ภงด)</div>
                                <select class="form-control" name="TAX_CATEGORY" id="TAX_CATEGORY">
                                    <option value="53">53</option>
                                    <option value="3">3</option>
                                </select>
                                </div>

                            <div class="col-md-12">
                                <div class="info-label">Company Name</div>
                                <input type="text" class="form-control" name="EN_COMPANY" id="EN_COMPANY" style="font-weight:600;" />
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Address (Line 1, 2, 3)</div>
                                <input type="text" class="form-control" name="EN_ADDRESS1" id="EN_ADDRESS1" />
                                <input type="text" class="form-control" name="EN_ADDRESS2" id="EN_ADDRESS2" />
                            </div>
    
                            <div class="col-md-12">
                                <div class="info-label">ชื่อบริษัท</div>
                                <input type="text" class="form-control" name="TH_COMPANY" id="TH_COMPANY" style="font-weight:600;" />
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">ที่อยู่</div>
                                <input type="text" class="form-control" name="TH_ADDRESS1" id="TH_ADDRESS1" />
                                <input type="text" class="form-control" name="TH_ADDRESS2" id="TH_ADDRESS2" />
                            </div>


                        </div>
                    </div>

                    
                </div>

                <div class="col-lg-5 col-md-12">
                    <div class="display-card">
                        <h4 class="card-title-sub">📍 Notify Information</h4>
                        <div class="row">
                            <div class="col-md-6"><div class="info-label">คำนำหน้าชื่อ</div>
                            <select class="form-control" name="TH_TITLE_COMPANY" id="TH_TITLE_COMPANY">
                                <option value=""></option>
                                <option value="บริษัท">บริษัท</option>
                                <option value="บจก">บจก</option>
                                <option value="บจก">บมจ</option>
                                <option value="ห้างหุ้นส่วนจำกัด">บมจ</option>
                                <option value="บจก">หจก</option>
                                <option value="บจก">สำนักงาน</option>
                                <option value="บจก">สนง</option>
                                <option value="บจก">ธนาคาร</option>
                                <option value="บจก">มหาวิทยาลัย</option>
                                <option value="บจก">สถานบัน</option>
                                <option value="บจก">ร้าน</option>
                                <option value="บจก">นาย</option>
                                <option value="บจก">นาง</option>
                                <option value="บจก">นางสาว</option>
                                <option value="บจก">สมาคม</option>
                            </select>
                        </div>

                            <div class="col-md-6"><div class="info-label">สาขา</div>
                            <select class="form-control" name="BRANCH" id="BRANCH">
                                <option value=""></option>
                                <option value="0000">0000</option>
                                <option value="0001">0001</option>
                                <option value="0002">0002</option>
                                <option value="0003">0003</option>
                                <option value="0004">0004</option>
                                <option value="0005">0005</option>
                                <option value="0006">0006</option>
                                <option value="0007">0007</option>
                                <option value="0008">0008</option>
                                <option value="0009">0009</option>
                                <option value="0010">0010</option>
                                <option value="0011">0011</option>
                                <option value="0012">0012</option>
                                <option value="0013">0013</option>
                                <option value="0014">0014</option>
                                <option value="0015">0015</option>
                                <option value="0016">0016</option>
                                <option value="0017">0017</option>
                                <option value="0018">0018</option>
                                <option value="0019">0019</option>
                                <option value="0020">0020</option>                                
                            </select>                            
                            </div>

                            <div class="col-md-12"><div class="info-label">ชื่อบริษัท</div><input type="text" class="form-control" name="TH_COMPANY_NAME" id="TH_COMPANY_NAME" /></div>
                            <div class="col-md-12">
                                <div class="info-label">หมู่บ้าน/อาคาร/นิคม</div>
                                <input type="text" class="form-control" name="TH_NAME_ADDRESS" id="TH_NAME_ADDRESS" />
                            </div>
                            <div class="col-md-6"><div class="info-label">ห้องที่</div><input type="text" class="form-control" name="TH_ROOM_ADDRESS" id="TH_ROOM_ADDRESS" /></div>
                            <div class="col-md-6"><div class="info-label">ชั้นที่</div><input type="text" class="form-control" name="TH_CLASS_ADDRESS" id="TH_CLASS_ADDRESS" /></div>
                            <div class="col-md-6"><div class="info-label">เลขที่</div><input type="text" class="form-control" name="TH_NUM_ADDRESS" id="TH_NUM_ADDRESS" /></div>
                            <div class="col-md-6"><div class="info-label">หมู่ที่</div><input type="text" class="form-control" name="TH_MOO" id="TH_MOO" /></div>
                            
                            <div class="col-md-6"><div class="info-label">ตรอก/ซอย</div><input type="text" class="form-control" name="TH_SOI" id="TH_SOI" /></div>
                            <div class="col-md-6"><div class="info-label">ถนน</div><input type="text" class="form-control" name="TH_ROAD" id="TH_ROAD" /></div>
                            <div class="col-md-6"><div class="info-label">ตำบล/แขวง</div><input type="text" class="form-control" name="TH_TUMBON" id="TH_TUMBON" /></div>
                            <div class="col-md-6"><div class="info-label">อำเภอ/เขต</div><input type="text" class="form-control" name="TH_AUMPER" id="TH_AUMPER" /></div>      
                            
                            <div class="col-md-6"><div class="info-label">จังหวัด (Province)</div>
                            <select class="form-control" id="TH_CITY" name="TH_CITY" required>
                                    <option value="" selected disabled>-- เลือกจังหวัด --</option>
                                    <?php
                                    $provinces = [
                                        "กระบี่", "กรุงเทพมหานคร", "กาญจนบุรี", "กาฬสินธุ์", "กำแพงเพชร", 
                                        "ขอนแก่น", "จันทบุรี", "ฉะเชิงเทรา", "ชลบุรี", "ชัยนาท", 
                                        "ชัยภูมิ", "ชุมพร", "เชียงราย", "เชียงใหม่", "ตรัง", 
                                        "ตราด", "ตาก", "นครนายก", "นครปฐม", "นครพนม", 
                                        "นครราชสีมา", "นครศรีธรรมราช", "นครสวรรค์", "นนทบุรี", "นราธิวาส", 
                                        "น่าน", "บึงกาฬ", "บุรีรัมย์", "ปทุมธานี", "ประจวบคีรีขันธ์", 
                                        "ปราจีนบุรี", "ปัตตานี", "พระนครศรีอยุธยา", "พะเยา", "พังงา", 
                                        "พัทลุง", "พิจิตร", "พิษณุโลก", "เพชรบุรี", "เพชรบูรณ์", 
                                        "แพร่", "ภูเก็ต", "มหาสารคาม", "มุกดาหาร", "แม่ฮ่องสอน", 
                                        "ยโสธร", "ยะลา", "ร้อยเอ็ด", "ระนอง", "ระยอง", 
                                        "ราชบุรี", "ลพบุรี", "ลำปาง", "ลำพูน", "เลย", 
                                        "ศรีสะเกษ", "สกลนคร", "สงขลา", "สตูล", "สมุทรปราการ", 
                                        "สมุทรสงคราม", "สมุทรสาคร", "สระแก้ว", "สระบุรี", "สิงห์บุรี", 
                                        "สุโขทัย", "สุพรรณบุรี", "สุราษฎร์ธานี", "สุรินทร์", "หนองคาย", 
                                        "หนองบัวลำภู", "อ่างทอง", "อำนาจเจริญ", "อุดรธานี", "อุตรดิตถ์", 
                                        "อุทัยธานี", "อุบลราชธานี"
                                    ];

                                    foreach ($provinces as $province) {
                                        // โค้ดนี้จะช่วยให้คงค่าที่ผู้ใช้เลือกไว้ (Selected) หลังจากกด Submit ฟอร์ม
                                        $selected = (isset($_POST['province']) && $_POST['province'] == $province) ? 'selected' : '';
                                        echo "<option value='".htmlspecialchars($province, ENT_QUOTES, 'UTF-8')."' $selected>$province</option>";
                                    }
                                    ?>
                                </select>                            
                            </div>
                            <div class="col-md-6"><div class="info-label">รหัสไปรษณีย์</div><input type="text" class="form-control" name="TH_POSTCODE" id="TH_POSTCODE"/></div>                              
                        </div>
                    </div>
                    
                </div>
            </div>

            <hr style="border-top: 1px solid #cbd5e1; margin: 30px 0;">
                                    
            <div class="row">
                <div class="col-md-12 text-right">
                    <input type="hidden" id="func" name="func" value="<?php echo htmlspecialchars($folder_func, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <button type="button" class="btn btn-default" style="margin-right: 10px; width: 150px; height: 42px; border-radius: 8px;" onclick="window.location.assign('supplier_master_mats.php?func=<?php echo urlencode($folder_func); ?>')">Cancel</button>
                    <button type="button" class="btn btn-success" style="width: 180px; height: 42px; border-radius: 8px; background-color: #22c55e; border: none; color: white; font-weight: 600;" onclick="submit_supp_data()">💾 Save Data</button>
                </div>
            </div><br>
            </form>
        </div>

        <?php include 'include/content-footer.php';?>
    </div>
</div>
</body>

<?php include 'include/footer.php';?>

<script type="text/javascript">
    
function submit_supp_data() {
    var data_fun = document.getElementById("func").value;

    // ดึงค่ามา .trim() เพื่อตัดช่องว่างหน้า-หลัง
    var id = document.getElementById('CSTMSPPL_ID').value.trim();
    var company = document.getElementById('EN_COMPANY').value.trim();

    // เช็ค ID
    if (id === "") {
        alert("Please fill in the Company ID field");
        document.getElementById('CSTMSPPL_ID').focus(); // เลื่อนเมาส์ไปโฟกัสที่ช่องนั้น
        return;
    }

    // เช็ค Company Name
    if (company === "") {
        alert("Please fill in the Company Name (EN) field");
        document.getElementById('EN_COMPANY').focus();
        return;
    }

    var formData = $("#form_supp").serialize();

    $.ajax({
        url: "model/insert_supplier_mats.php",
        type: "POST",
        data: formData,
        dataType: "json",
        success: function(resp) {
            if(resp.status === "success"){
                alert("Data has been successfully saved.");
                window.location.assign('supplier_master_mats.php?func=' + encodeURIComponent(data_fun));
            } else {
                alert("An error occurred: " + (resp.error || "Failed to save data"));
            }
        },
        error: function(xhr, status, error) {
            alert("An error occurred while submitting data: " + error);
        }
    });
}
</script>
</html>