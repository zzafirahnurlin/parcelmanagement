<?php
if (!defined('BASE_URL')) define('BASE_URL', '/parcel/');
if (!defined('APP_NAME')) define('APP_NAME', 'Koperasi KPMB');
date_default_timezone_set('Asia/Kuala_Lumpur');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '', 'parcel_db');  
$conn->set_charset('utf8mb4');
