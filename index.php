<!doctype html>
<html lang="en">
<head>
<title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets/css/documentation.css" rel="stylesheet" />
    <link href="assets/css/prettify.css" rel="stylesheet" type="text/css">
    
    <script>
    function checkFunction(selectElement) {
        var processGroup = document.getElementById("process-group-wrapper");
        var processSelect = document.getElementById("process_group");
        
        if (selectElement.value === 'pd') {
            processGroup.style.display = 'block';
            processSelect.required = true;
        } else {
            processGroup.style.display = 'none';
            processSelect.required = false;
            processSelect.value = '';
            resetLineDropdowns();
        }
    }

    function checkProcessGroup(selectElement) {
        resetLineDropdowns();
        var selectedVal = selectElement.value;
        
        if (selectedVal) {
            var targetLineGroup = document.getElementById("line-group-" + selectedVal);
            if (targetLineGroup) {
                targetLineGroup.style.display = 'block';
                var select = targetLineGroup.querySelector('select');
                if (select) select.required = true;
            }
        }
    }

    function resetLineDropdowns() {
        var groups = document.querySelectorAll('.line-sub-group');
        groups.forEach(function(group) {
            group.style.display = 'none';
            var select = group.querySelector('select');
            if (select) {
                select.required = false;
                select.value = '';
            }
        });
    }
    </script>
</head>
<body>
    <div class="header-wrapper">
        <div class="header" style="background-image: url('assets/img/full-screen-mats.png');"> 
            <div class="filter"></div>
            <div class="title-container text-center">

                <div class="col-md-8">
                <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
                <video id="bg-video" width="1000" height="500" preload="auto" autoplay muted loop playsinline webkit-playsinline>
                    <source src="assets/img/video.mp4" type="video/mp4">
                </video>
                </div>

                <div class="col-md-4" style="padding-right: 7%; padding-top: 12%;">
                <br><br><br><br>
                <img src="assets/img/logo-login-mats.png" style="width: 30%; height: 30%;">
                <p>&nbsp;</p>
                <p class="description text-center"><img src="assets/img/mobile.jpg" style="width: 5%; height: 10%">&nbsp;&nbsp;Enter username and password</p>
                    <form role="form" method="post" action="login_mats.php">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" placeholder="Username" name="username" autofocus required>
                            </div>

                            <div class="form-group">
                                <input class="form-control" placeholder="Password" name="pw" type="password" value="" required>
                            </div>

                            <!-- 1. เลือกระบบงาน -->
                            <div class="form-group">
                                <select class="form-control" name="func" onchange="checkFunction(this)" required>
                                    <option value="" selected disabled>Choose a work system.</option>
                                    <option value='pd'>Production (PD)</option>
                                    <option value='qac'>Quality Assurance (QA&QC)</option>
                                    <option value='wh'>Warehouse (WH&FG)</option>               
                                    <option value='st'>Store (STORE)</option>
                                    <option value='bi'>Business Intelligence (BI)</option>
                                    <option value='sale'>Sales (Sale)</option>
                                    <option value='root'>Administrator (Admin)</option>
                                </select>
                            </div>

                            <!-- 2. เลือกกลุ่มงานผลิต -->
                            <div class="form-group" id="process-group-wrapper" style="display: none;">
                                <select class="form-control" name="process_group" id="process_group" onchange="checkProcessGroup(this)">
                                    <option value="" selected disabled>-- Select a production group. --</option>
                                    <option value="BLANKING">BLANKING</option>
                                    <option value="CASTER">CASTER</option>
                                    <option value="XY_SHEAR">XY CIRCLE SHEAR</option>
                                    <option value="A_PRESS">A PRESS</option>
                                    <option value="K_PRESS">K PRESS</option>
                                    <option value="CTL">CUT TO LENGTH</option>
                                    <option value="COLD_MILL">COLD MILL</option>
                                    <option value="SLITTER">SWISS SLITTER</option>
                                </select>
                            </div>

                            <!-- 3. เลือก Line เครื่องจักร (แสดงตามกลุ่มงานที่เลือก) -->
                            
                            <!-- กลุ่ม BLANKING -->
                            <div class="form-group line-sub-group" id="line-group-BLANKING" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="1">Line 1</option>
                                    <option value="2">Line 2</option>
                                    <option value="3">Line 3</option>
                                    <option value="4">Line 4</option>
                                    <option value="5">Line 5</option>
                                </select>
                            </div>

                            <!-- กลุ่ม CASTER -->
                            <div class="form-group line-sub-group" id="line-group-CASTER" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="1">Line 1</option>
                                    <option value="2">Line 2</option>
                                </select>
                            </div>

                            <!-- กลุ่ม XY CIRCLE SHEAR -->
                            <div class="form-group line-sub-group" id="line-group-XY_SHEAR" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="5">Line 5</option>
                                </select>
                            </div>

                            <!-- กลุ่ม A PRESS -->
                            <div class="form-group line-sub-group" id="line-group-A_PRESS" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="F">Line F</option>
                                    <option value="G">Line G</option>
                                </select>
                            </div>

                            <!-- กลุ่ม K PRESS -->
                            <div class="form-group line-sub-group" id="line-group-K_PRESS" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="K">Line K</option>
                                </select>
                            </div>

                            <!-- กลุ่ม CUT TO LENGTH -->
                            <div class="form-group line-sub-group" id="line-group-CTL" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="A">Line A</option>
                                </select>
                            </div>

                            <!-- กลุ่ม COLD MILL -->
                            <div class="form-group line-sub-group" id="line-group-COLD_MILL" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="M">Line M</option>
                                </select>
                            </div>

                            <!-- กลุ่ม SWISS SLITTER -->
                            <div class="form-group line-sub-group" id="line-group-SLITTER" style="display: none;">
                                <select class="form-control" name="machine_line">
                                    <option value="" selected disabled>-- Select Line --</option>
                                    <option value="T">Line T</option>
                                </select>
                            </div>
                                                        
                            <input type="submit" class="btn btn-success btn-block btn-fill" value="Login">
                        </fieldset>
                    </form> 
                </div>
            </div>
        </div>
    </div>
</body>
<?php include 'include/footer.php';?>
</html>