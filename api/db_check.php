<?php
header('Content-Type: application/json');

// Database connection parameters
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbName = 'gelo_pos';

$response = [
    'ok' => true,
    'mysql_connected' => false,
    'database_exists' => false,
    'tables_exist' => false,
    'has_data' => false,
    'tables' => [],
    'error' => null
];

try {
    // Try to connect to MySQL server without selecting a database
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $response['mysql_connected'] = true;
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    $dbExists = $stmt->fetchColumn();
    
    if ($dbExists) {
        $response['database_exists'] = true;
        
        // Connect to the database
        $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Check required tables
        $requiredTables = ['transactions', 'transaction_items', 'stock_movements', 'products'];
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if (!$stmt->fetchColumn()) {
                $missingTables[] = $table;
            } else {
                // Count records in the table
                $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $countStmt->fetchColumn();
                $response['tables'][] = ['name' => $table, 'count' => $count];
            }
        }
        
        if (empty($missingTables)) {
            $response['tables_exist'] = true;
            
            // Check if we have sample data
            $stmt = $pdo->query("SELECT COUNT(*) FROM products");
            $productCount = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
            $transactionCount = $stmt->fetchColumn();
            
            if ($productCount > 0 && $transactionCount > 0) {
                $response['has_data'] = true;
            }
        } else {
            $response['missing_tables'] = $missingTables;
        }
    }
    
} catch (PDOException $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);