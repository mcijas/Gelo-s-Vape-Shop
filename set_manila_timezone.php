<?php
/**
 * Set Manila timezone for both PHP and MySQL connection
 * Include this file at the top of any PHP page
 */

// 1. Set PHP timezone
date_default_timezone_set('Asia/Manila');

// 2. Ensure MySQL connection uses Manila timezone
// This assumes $pdo is already created from api/db.php
try {
    if (isset($GLOBALS['pdo']) || isset($pdo)) {
        $db = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : $pdo;
        
        // Try named timezone first
        $db->exec("SET time_zone = 'Asia/Manila'");
    }
} catch (Throwable $e) {
    // Fallback to numeric offset if timezone tables not available
    $offset = '+08:00'; // Manila is GMT+8
    if (isset($GLOBALS['pdo']) || isset($pdo)) {
        $db = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : $pdo;
        $db->exec("SET time_zone = '$offset'");
    }
}

// Optional: Quick verification
// echo "PHP Time: " . date('Y-m-d H:i:s') . "<br>";
// echo "MySQL Time: " . $db->query("SELECT NOW()")->fetchColumn();