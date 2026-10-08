<?php
// เริ่ม session ก่อน ANY output
session_start();

include 'function_mats.php';

// ป้องกันกรณีไม่มี Session เพื่อไม่ให้เกิด Undefined array key Warning
$iduser_func = isset($_SESSION['ID']) ? htmlspecialchars($_SESSION['ID'], ENT_QUOTES, 'UTF-8') : '';
$password_func = isset($_SESSION['PASSWORD']) ? htmlspecialchars($_SESSION['PASSWORD'], ENT_QUOTES, 'UTF-8') : '';
?>

<!doctype html>
<html lang="en">
<head>
<title>Meyer Aluminium Thailand Co.,LTD.</title>
    <?php include 'include/header.php';?>
    <link href="assets/css/documentation.css" rel="stylesheet" />
    <link href="assets/css/prettify.css" rel="stylesheet" type="text/css">
</head>
<body>
    <div class="header-wrapper">
        <!--- <div class="header" style="background-image: url('assets/img/full-screen-kw-pd.jpg');"> ---> 
        <div class="header" style="background-image: url('assets/img/full-screen-mats.png');"> 
        <!--- <div class="header" style="background-image: url('assets/img/a.jpg');"> --->
            <div class="filter"></div>
            <div class="title-container text-center">


                <div class="col-md-8">
                <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
                <video id="bg-video" width="1000" height="500" preload="auto" autoplay muted loop playsinline webkit-playsinline>
				<source src="assets/img/video.mp4" type="video/mp4">
			    </video>
                </div>

                <div class="col-md-4" style="padding-right: 7%; padding-top: 12%;">
                <br><br><br><br><br><br><br><br>
                <img src="assets/img/logo-login-mats.png" style="width: 30%; height: 30%;">
                <p>&nbsp;</p>
                <p class="description text-center">Change Password</p>
                    <form role="form" method="post" action="change_password_login_mats.php">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" name="nuser" type="text" value="<?php echo $iduser_func ?>" disabled>
                            </div>

                            <div class="form-group">
                                <input class="form-control" name="pw" type="password" value="<?php echo $password_func?>" disabled>
                            </div>

							<div class="form-group">
                                <input class="form-control" placeholder="New Password" name="npw" type="password" value="" autofocus>
                            </div>
                          
                            <input type="submit" class="btn btn-success btn-block btn-fill" value="Change Password">
							
                        </fieldset>
                    </form> 
                </div>
            </div>
        </div>
    </div>
</body>
<?php include 'include/footer.php';?>
</html>