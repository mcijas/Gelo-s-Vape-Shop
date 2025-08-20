<?php
// Test file to verify Manila timezone is working
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/timezone_helper.php';

$phpTime = date('Y-m-d H:i:s');
$mysqlTime = $pdo->query("SELECT NOW() as mysql_time")->fetch()['mysql_time'];
$mysqlTz = $pdo->query("SELECT @@session.time_zone as mysql_tz")->fetch()['mysql_tz'];

header('Content-Type: application/json');
echo json_encode([
    'php_time' => $phpTime,
    'mysql_time' => $mysqlTime,
    'mysql_timezone' => $mysqlTz,
    'php_timezone' => date_default_timezone_get()
]);
?>