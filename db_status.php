<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Status</title>
    <link rel="stylesheet" href="global.css">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #111;
            color: white;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #262626;
            border-radius: 10px;
            padding: 20px;
        }
        h1 {
            color: #4ade80;
            margin-bottom: 20px;
        }
        .status-card {
            background: #1f1f1f;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-title {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .success {
            background-color: #4ade80;
        }
        .error {
            background-color: #ef4444;
        }
        .warning {
            background-color: #f59e0b;
        }
        .message {
            margin-left: 22px;
            font-size: 14px;
        }
        .action-btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 10px;
            font-size: 14px;
        }
        .action-btn:hover {
            background: #2563eb;
        }
        pre {
            background: #111;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Status</h1>
        
        <?php
        // Check if XAMPP/MySQL is running
        function isServiceRunning($service) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $output = [];
                exec('sc query ' . $service, $output);
                return strpos(implode(' ', $output), 'RUNNING') !== false;
            } else {
                // Linux/Mac
                $output = [];
                exec('ps aux | grep -v grep | grep ' . $service, $output);
                return !empty($output);
            }
        }
        
        // Check MySQL connection
        function checkDatabaseConnection() {
            $host = '127.0.0.1';
            $user = 'root';
            $pass = '';
            
            try {
                $pdo = new PDO("mysql:host=$host", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                return [true, 'Connected to MySQL server successfully'];
            } catch (PDOException $e) {
                return [false, 'MySQL connection failed: ' . $e->getMessage()];
            }
        }
        
        // Check if database exists
        function checkDatabaseExists() {
            $host = '127.0.0.1';
            $user = 'root';
            $pass = '';
            $dbname = 'gelo_pos';
            
            try {
                $pdo = new PDO("mysql:host=$host", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    return [true, "Database '$dbname' exists"];
                } else {
                    return [false, "Database '$dbname' does not exist"];
                }
            } catch (PDOException $e) {
                return [false, 'Failed to check database: ' . $e->getMessage()];
            }
        }
        
        // Check if tables exist and have data
        function checkTablesAndData() {
            $host = '127.0.0.1';
            $user = 'root';
            $pass = '';
            $dbname = 'gelo_pos';
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                $tables = ['products', 'transactions', 'transaction_items', 'stock_movements'];
                $results = [];
                
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    $tableExists = $stmt->rowCount() > 0;
                    
                    if ($tableExists) {
                        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                        $results[$table] = [true, "Table '$table' exists with $count records"];
                    } else {
                        $results[$table] = [false, "Table '$table' does not exist"];
                    }
                }
                
                return $results;
            } catch (PDOException $e) {
                return ['error' => [false, 'Failed to check tables: ' . $e->getMessage()]];
            }
        }
        
        // Check MySQL service
        $mysqlRunning = isServiceRunning('mysql');
        ?>
        
        <div class="status-card">
            <div class="status-title">
                <span class="status-indicator <?php echo $mysqlRunning ? 'success' : 'error'; ?>"></span>
                MySQL Service
            </div>
            <div class="message">
                <?php echo $mysqlRunning ? 'MySQL service is running' : 'MySQL service is not running'; ?>
                <?php if (!$mysqlRunning): ?>
                    <p>You need to start MySQL service through XAMPP Control Panel</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($mysqlRunning): ?>
            <?php 
            list($dbConnected, $dbConnMessage) = checkDatabaseConnection();
            list($dbExists, $dbExistsMessage) = checkDatabaseExists();
            ?>
            
            <div class="status-card">
                <div class="status-title">
                    <span class="status-indicator <?php echo $dbConnected ? 'success' : 'error'; ?>"></span>
                    MySQL Connection
                </div>
                <div class="message"><?php echo $dbConnMessage; ?></div>
            </div>
            
            <div class="status-card">
                <div class="status-title">
                    <span class="status-indicator <?php echo $dbExists ? 'success' : 'warning'; ?>"></span>
                    Database Status
                </div>
                <div class="message">
                    <?php echo $dbExistsMessage; ?>
                    <?php if (!$dbExists): ?>
                        <p>The database needs to be created</p>
                        <a href="api/init_db.php" class="action-btn">Initialize Database</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($dbExists): ?>
                <?php $tableResults = checkTablesAndData(); ?>
                
                <div class="status-card">
                    <div class="status-title">
                        <span class="status-indicator <?php echo !isset($tableResults['error']) ? 'success' : 'error'; ?>"></span>
                        Tables Status
                    </div>
                    <div class="message">
                        <?php if (isset($tableResults['error'])): ?>
                            <?php echo $tableResults['error'][1]; ?>
                        <?php else: ?>
                            <?php foreach ($tableResults as $table => $result): ?>
                                <p>
                                    <span class="status-indicator <?php echo $result[0] ? 'success' : 'warning'; ?>"></span>
                                    <?php echo $result[1]; ?>
                                </p>
                            <?php endforeach; ?>
                            
                            <?php 
                            // Check if any table is missing or empty
                            $needsInit = false;
                            foreach ($tableResults as $result) {
                                if (!$result[0] || strpos($result[1], 'with 0 records') !== false) {
                                    $needsInit = true;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if ($needsInit): ?>
                                <p>Some tables are missing or empty</p>
                                <a href="api/init_db.php" class="action-btn">Initialize Database</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="status-card">
            <div class="status-title">
                <span class="status-indicator info"></span>
                Dashboard Status
            </div>
            <div class="message">
                <p>After ensuring the database is properly set up, you can view the dashboard:</p>
                <a href="Pages/dashboard.html" class="action-btn">Go to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>