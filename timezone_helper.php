<?php
/**
 * Manila Timezone Helper
 * Include this file at the top of any PHP page to set Manila timezone
 * Usage: require_once __DIR__ . '/timezone_helper.php';
 */

// Set PHP timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Set MySQL timezone if database connection exists
if (isset($pdo) || isset($GLOBALS['pdo'])) {
    $db = isset($pdo) ? $pdo : $GLOBALS['pdo'];
    
    try {
        // Try named timezone first
        $db->exec("SET time_zone = 'Asia/Manila'");
    } catch (Throwable $e) {
        // Fallback to numeric offset
        $db->exec("SET time_zone = '+08:00'");
    }
}

// Function to get Manila timezone offset as string
function get_manila_timezone_offset() {
    return '+08:00';
}

// Function to format datetime in Manila timezone
function format_manila_datetime($datetime = 'now') {
    $tz = new DateTimeZone('Asia/Manila');
    $dt = new DateTime($datetime, $tz);
    return $dt->format('Y-m-d H:i:s');
}
?>