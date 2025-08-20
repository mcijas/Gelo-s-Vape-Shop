<?php
// Simple database connection test
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "MySQL connection successful\n";
    
    // Check if gelo_pos database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'gelo_pos'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Database 'gelo_pos' exists\n";
        
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=gelo_pos;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check tables
        $tables = ['customers', 'products', 'transactions', 'transaction_items', 'stock_movements'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "Table '$table' exists with $count records\n";
            } else {
                echo "Table '$table' does not exist\n";
            }
        }
        
    } else {
        echo "Database 'gelo_pos' does not exist\n";
    }
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>