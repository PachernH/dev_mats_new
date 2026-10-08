<?php
// เริ่ม session ก่อน ANY output
$name_func = isset($_SESSION['NAMEFUNC']) ? htmlspecialchars($_SESSION['NAMEFUNC'], ENT_QUOTES, 'UTF-8') : '';
$group_func = isset($_SESSION['GROUP']) ? htmlspecialchars($_SESSION['GROUP'], ENT_QUOTES, 'UTF-8') : '';
?>

<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <style>
        body {
            /* ใช้ฟอนต์มาตรฐานระบบอุตสาหกรรมในเครื่อง ไม่ต้องต่อเน็ตก็สวย */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Sarabun", "Tahoma", sans-serif;
        }

        ul, .nav {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }

        /* ปุ่มเมนูหลัก และ หัวข้อกลุ่มเมนู (เพิ่มความสูงแถวด้วย padding) */
        .menu-item, .box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px; /* เพิ่มพื้นที่กดให้กว้างและหนาขึ้น */
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.25s ease;
            border-radius: 8px;
            margin-bottom: 6px;  /* เพิ่มระยะห่างระหว่างเมนู */
            cursor: pointer;
            -webkit-user-select: none;
            user-select: none;
        }

        .menu-item:hover, .box:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            text-decoration: none;
        }

        .menu-link-content {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 16px !important; /* เพิ่มช่องว่างระหว่างไอคอนกับตัวหนังสือ */
            width: 100%;
        }

        /* จัดสไตล์ตัวครอบไอคอน SVG (ปรับขนาดไอคอนให้ใหญ่ขึ้นจากเดิม) */
        .menu-icon-wrapper {
            width: 24px;   /* เพิ่มความกว้างกล่องไอคอน */
            height: 24px;  /* เพิ่มความสูงกล่องไอคอน */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            fill: currentColor;
            color: inherit;
        }

        /* ปรับไอคอน SVG ด้านในให้ขยายตามกล่อง */
        .menu-icon-wrapper svg {
            width: 22px !important;  /* ขยายขนาดรูปไอคอนหลัก */
            height: 22px !important; /* ขยายขนาดรูปไอคอนหลัก */
        }

        /* ปรับขนาดตัวอักษรเมนูหลัก (เพิ่มจาก 16px เป็น 18px บลด์หนาขึ้น) */
        .menu-link-content p, .box small {
            font-size: 18px !important; /* ตัวอักษรใหญ่ขึ้นมองเห็นชัดเจน */
            margin: 0 !important;
            padding: 0 !important;
            font-weight: 600;           /* เน้นความหนาให้เด่นชัด */
            white-space: nowrap;
        }

        /* ลูกศรขวามือสำหรับเมนูกลุ่ม (ปรับให้ใหญ่ตามตัวอักษร) */
        .arrow-icon {
            width: 16px;
            height: 16px;
            transition: transform 0.25s ease;
            opacity: 0.7;
            flex-shrink: 0;
            fill: currentColor;
        }

        /* เมื่อเมนูกลุ่มถูกเปิด ให้ลูกศรหมุนกลับหัว */
        .check-box .arrow-icon {
            transform: rotate(180deg);
            opacity: 1;
            color: #38bdf8;
        }
        
        .box.check-box {
            color: #ffffff;
        }

        /* ส่วนของเมนูย่อย (Nested) */
        .nested {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding-left: 12px;
        }

        .nested.active {
            max-height: 1200px;
            transition: max-height 0.5s ease-in;
        }

        /* ปรับขนาดและพื้นที่เมนูย่อย */
        .nested li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px 12px 28px; /* เพิ่ม Padding บน-ล่าง ให้กดง่ายขึ้น */
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            font-size: 16px; /* ขยายตัวอักษรเมนูย่อยจาก 14px เป็น 16px */
            border-radius: 6px;
            transition: all 0.2s ease;
            margin-bottom: 4px;
            white-space: nowrap; /* บังคับไม่ให้ข้อความตกบรรทัด */
        }

        .nested li a:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.05);
        }

        .nested li.active a {
            color: #ffffff !important;
            font-weight: 600;
            background-color: #0284c7 !important;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .nested li.active a .menu-icon-wrapper {
            color: #38bdf8;
        }

        .sidebar-wrapper {
            padding: 16px 16px;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .logo-container img {
            max-width: 85%;
            height: auto;
            display: block;
            margin-bottom: 12px;
        }

        /* ส่วนหัวข้อระบบผลิต */
        .logo-container .system-title {
            font-size: 18px; /* ขยายหัวข้อระบบผลิตจาก 15px เป็น 18px */
            color: #ffffff;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0;
            text-align: center;
            opacity: 0.95;
        }
        
        .nav {
            margin-top: 0px; 
        }

        /* SVG วงกลมเล็กหน้าเมนูย่อย (ขยายขนาดจาก 8px เป็น 10px) */
        .dot-icon {
            width: 10px;
            height: 10px;
            fill: currentColor;
            flex-shrink: 0;
        }
    </style>
</head>

<body>
    <div class="sidebar" data-color="azure" data-image="assets/img/full-screen-mats.png" id="side_nav">
        <div class="sidebar-wrapper">
            
            <div class="logo-container">
                <a href="dashboard_mats.php">
                    <img src="assets/img/logo-mat.png" alt="Logo">
                </a>
                <h1 class="system-title"><?php echo $name_func ?></h1>
            </div>
            
            <ul class="nav">
                <li class="<?php echo ($menu == "TR") ? "active" : "" ?>">
                    <a href="coil_traceability_mats.php" class="menu-item">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/history_small.png">
                            </span>
                            <p>TRACEABILITY</p>
                        </div>
                    </a>
                </li>

                <li class="<?php echo ($menu == 'HI') ? "active" : "" ?>">
                    <a href="history_coil_log_mats.php" class="menu-item">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/search_small.png">
                            </span>
                            <p>HISTORY</p>
                        </div>
                    </a>
                </li>
<?php if($group_func == 'IT-MANAGER'){?>
                <li>
                    <div class="box <?php echo in_array($menu, ['compo', 'temper', 'mg_grade', 'wk_process']) ? 'check-box' : '' ?>">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/data_small.png">
                            </span>
                            <small>INITIAL</small>
                        </div>
                        <svg class="arrow-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <ul class="nested <?php echo in_array($menu, ['gp1', 'gp2', 'gp3', 'gp4', 'gp5', 'gp6', 'gp7']) ? 'active' : '' ?>">
                        <li class="<?php echo ($menu == "gp1") ? "active" : "" ?>">
                            <a href="group_data1_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 1</p>
                            </a>
                        </li>
                        <li class="<?php echo ($menu == "gp2") ? "active" : "" ?>">
                            <a href="group_data2_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 2</p>
                            </a>
                        </li>
                        <li class="<?php echo ($menu == "gp3") ? "active" : "" ?>">
                            <a href="group_data3_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 3</p>
                            </a>
                        </li>
                        <li class="<?php echo ($menu == "gp4") ? "active" : "" ?>">
                            <a href="group_data4_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 4</p>
                            </a>
                        </li>  
                        <li class="<?php echo ($menu == "gp5") ? "active" : "" ?>">
                            <a href="group_data5_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 5</p>
                            </a>
                        </li>       
                        <li class="<?php echo ($menu == "gp6") ? "active" : "" ?>">
                            <a href="group_data6_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 6</p>
                            </a>
                        </li>  
                        <li class="<?php echo ($menu == "gp7") ? "active" : "" ?>">
                            <a href="group_data7_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>MASTER DATA 7</p>
                            </a>
                        </li>  
                    </ul>    
                </li>
<?php }?>
                <li>
                    <?php $act_menus = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9']; ?>
                    <div class="box <?php echo in_array($menu, $act_menus) ? 'check-box' : '' ?>">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/pd_small.png">
                            </span>
                            <small>ACTIVITY</small>
                        </div>
                        <svg class="arrow-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <ul class="nested <?php echo in_array($menu, $act_menus) ? 'active' : '' ?>">
                        <li class="<?php echo ($menu == "A1") ? "active" : "" ?>"><a href="sale_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Sale Module</p></a></li>
                        <li class="<?php echo ($menu == "A2") ? "active" : "" ?>"><a href="planing_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Planing Module</p></a></li>
                        <li class="<?php echo ($menu == "A3") ? "active" : "" ?>"><a href="technical_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Technical Module</p></a></li>
                        <li class="<?php echo ($menu == "A4") ? "active" : "" ?>"><a href="production_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Production Module</p></a></li>
                        <li class="<?php echo ($menu == "A5") ? "active" : "" ?>"><a href="warehouse_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Warehouse Module</p></a></li>
                        <li class="<?php echo ($menu == "A6") ? "active" : "" ?>"><a href="shipping_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Shipping Module</p></a></li>
                        <li class="<?php echo ($menu == "A7") ? "active" : "" ?>"><a href="account_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Account Module</p></a></li>
                        <li class="<?php echo ($menu == "A8") ? "active" : "" ?>"><a href="engineer_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Engineer Module</p></a></li>
                        <li class="<?php echo ($menu == "A9") ? "active" : "" ?>"><a href="hr_module_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>HR Module</p></a></li>
                    </ul>    
                </li>                
<!---
                <li>
                    <?php $prod_menus = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12', 'T13', 'T14', 'T15', 'T16']; ?>
                    <div class="box <?php echo in_array($menu, $prod_menus) ? 'check-box' : '' ?>">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/pd_small.png">
                            </span>
                            <small>PRODUCTION</small>
                        </div>
                        <svg class="arrow-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <ul class="nested <?php echo in_array($menu, $prod_menus) ? 'active' : '' ?>">
                        <li class="<?php echo ($menu == "T1") ? "active" : "" ?>"><a href="issue_material_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Issue Material</p></a></li>
                        <li class="<?php echo ($menu == "T2") ? "active" : "" ?>"><a href="melt_furnace_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Melt Furnace</p></a></li>
                        <li class="<?php echo ($menu == "T3") ? "active" : "" ?>"><a href="casting_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Casting</p></a></li>
                        <li class="<?php echo ($menu == "T4") ? "active" : "" ?>"><a href="cold_rolling_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Cold Rolling</p></a></li>
                        <li class="<?php echo ($menu == "T5") ? "active" : "" ?>"><a href="annealing_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Annealing</p></a></li>
                        <li class="<?php echo ($menu == "T6") ? "active" : "" ?>"><a href="batch_annealing_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Batch Annealing</p></a></li>
                        <li class="<?php echo ($menu == "T7") ? "active" : "" ?>"><a href="blanking_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Blanking</p></a></li>
                        <li class="<?php echo ($menu == "T8") ? "active" : "" ?>"><a href="circle_shear_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Circle Shear</p></a></li>
                        <li class="<?php echo ($menu == "T9") ? "active" : "" ?>"><a href="xy-5_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>XY-5</p></a></li>
                        <li class="<?php echo ($menu == "T10") ? "active" : "" ?>"><a href="cut_to_length_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Cut to Length</p></a></li>
                        <li class="<?php echo ($menu == "T11") ? "active" : "" ?>"><a href="stretcher_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Stretcher</p></a></li>
                        <li class="<?php echo ($menu == "T12") ? "active" : "" ?>"><a href="a_press_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>A Press</p></a></li>
                        <li class="<?php echo ($menu == "T13") ? "active" : "" ?>"><a href="k_press_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>K Press</p></a></li>
                        <li class="<?php echo ($menu == "T14") ? "active" : "" ?>"><a href="swiss_slitter_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Swiss slitter</p></a></li>
                        <li class="<?php echo ($menu == "T15") ? "active" : "" ?>"><a href="inspection_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Inspection</p></a></li>
                        <li class="<?php echo ($menu == "T16") ? "active" : "" ?>"><a href="packing_mats.php"><svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg><p>Packing</p></a></li>
                    </ul>    
                </li>
--->
                <li>
                    <div class="box <?php echo in_array($menu, ['r_re', 'r_who']) ? 'check-box' : '' ?>">
                        <div class="menu-link-content">
                            <span class="menu-icon-wrapper">
                                <img src="assets/menu/report_small.png">
                            </span>
                            <small>REPORT & FORM</small>
                        </div>
                        <svg class="arrow-icon" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    <ul class="nested <?php echo in_array($menu, ['r_re', 'r_who']) ? 'active' : '' ?>">
                        <li class="<?php echo ($menu == "r_re") ? "active" : "" ?>">
                            <a href="template_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>Form</p>
                            </a>
                        </li>
                        <li class="<?php echo ($menu == "r_who") ? "active" : "" ?>">
                            <a href="template_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>Query</p>
                            </a>
                        </li>
                        <li class="<?php echo ($menu == "r_who") ? "active" : "" ?>">
                            <a href="template_mats.php">
                                <svg class="dot-icon" viewBox="0 0 512 512"><circle cx="256" cy="256" r="256"/></svg>
                                <p>Report</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
    
    <script>
        var toggler = document.getElementsByClassName("box");
        var i;

        for (i = 0; i < toggler.length; i++) {
            toggler[i].addEventListener("click", function() {
                this.classList.toggle("check-box");
                var nestedMenu = this.nextElementSibling;
                if (nestedMenu) {
                    nestedMenu.classList.toggle("active");
                }
            });
        }
    </script>
</body>
</html>