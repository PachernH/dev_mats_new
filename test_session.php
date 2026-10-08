<?php


// ฟังก์ชันสำหรับตรวจสอบ session
function checkSessionSetup() {
    echo "<h2>Session Configuration Check</h2>";
    
    $checks = [
        'Session save Path' => ini_get('session.save_path'),
        'Session save Handler' => ini_get('session.save_handler'),
        'Session GC Probability' => ini_get('session.gc_probability'),
        'Session GC Max Lifetime' => ini_get('session.gc_maxlifetime'),
    ];
    
    foreach ($checks as $key => $value) {
        echo "<b>$key:</b> $value<br>";
    }
    
    // ตรวจสอบ directory
    $sessionPath = ini_get('session.save_path');
    echo "<b>Session Directory Exists:</b> " . (file_exists($sessionPath) ? 'Yes ✅' : 'No ❌') . "<br>";
    echo "<b>Session Directory Writable:</b> " . (is_writable($sessionPath) ? 'Yes ✅' : 'No ❌') . "<br>";
    
    if (file_exists($sessionPath) && is_writable($sessionPath)) {
        echo "<b>Files in Session Directory:</b><br>";
        $files = scandir($sessionPath);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- $file (" . date('Y-m-d H:i:s', filemtime($sessionPath . '/' . $file)) . ")<br>";
            }
        }
    }
}

// เริ่ม session ก่อน ANY output
session_start();

// หลังจากนี้สามารถมี output ได้
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meyer Aluminium Thailand Co.,LTD.</title>
    <style>
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Session Test - Fixed Version</h1>
    
    <?php
    //checkSessionSetup();
    echo "<hr>";
    
    // ทดสอบ session
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo '<p class="success">✓ Session is ACTIVE</p>';
        
        // ตั้งค่า session variables
        if (!isset($_SESSION['visit_count'])) {
            $_SESSION['visit_count'] = 1;
            $_SESSION['first_visit'] = date('Y-m-d H:i:s');
        } else {
            $_SESSION['visit_count']++;
        }
        
        $_SESSION['last_visit'] = date('Y-m-d H:i:s');
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        
        echo "<h3>Session Data:</h3>";
        echo "<ul>";
        foreach ($_SESSION as $key => $value) {
            echo "<li><b>$key:</b> $value</li>";
        }
        echo "</ul>";
        echo $_SESSION['func'];
          echo "<br>";
        echo $_SESSION['id'];
          echo "<br>";
        echo $_SESSION['user'];
        echo "<br>";
        echo $_SESSION['keyBranch'];
        echo "<br>";
        echo "<p><b>Session ID:</b> " . session_id() . "</p>";
        
    } else {
        echo '<p class="error">✗ Session is NOT active</p>';
        echo "<p>Session status: " . session_status() . "</p>";
    }
    
    // ตรวจสอบ errors
    $error = error_get_last();
    if ($error) {
        echo "<h3>Last Error:</h3>";
        echo "<pre>";
        print_r($error);
        echo "</pre>";
    }
    ?>
</body>
</html>