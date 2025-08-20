<?php
// Complete reset script - wipes all data and creates empty tables
$host = '127.0.0.1';
$user = 'root';
$pass = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset All Data - Gelo's Vape Shop</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a1a; color: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #262626; padding: 30px; border-radius: 12px; }
        .btn { padding: 12px 24px; margin: 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #3a3a3a; color: white; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Reset All Data</h1>
        <p class="warning">⚠️ This will permanently delete ALL data including:</p>
        <ul>
            <li>All products and inventory</li>
            <li>All customers</li>
            <li>All transactions and sales history</li>
            <li>All stock movements</li>
            <li>All supplier information</li>
        </ul>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL data? This cannot be undone.');">
            <button type="submit" name="reset" class="btn btn-danger">Yes, Reset Everything</button>
            <a href="index.html" class="btn btn-secondary">Cancel</a>
        </form>

        <?php
        if (isset($_POST['reset'])) {
            try {
                // Connect to MySQL server
                $pdo = new PDO("mysql:host=$host", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                // Drop existing database completely
                $pdo->exec("DROP DATABASE IF EXISTS gelo_pos");
                echo "<p class='success'>✅ Database 'gelo_pos' deleted.</p>";
                
                // Create fresh database
                $pdo->exec("CREATE DATABASE gelo_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "<p class='success'>✅ Fresh database 'gelo_pos' created.</p>";
                
                // Connect to the new database
                $pdo = new PDO("mysql:host=$host;dbname=gelo_pos;charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                // Create empty tables
                $pdo->exec("CREATE TABLE customers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) DEFAULT NULL,
                    phone VARCHAR(100) DEFAULT NULL,
                    status ENUM('active','deleted') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $pdo->exec("CREATE TABLE products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    category VARCHAR(80) DEFAULT NULL,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    stock INT NOT NULL DEFAULT 0,
                    image_url VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $pdo->exec("CREATE TABLE transactions (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    ref VARCHAR(64) NOT NULL,
                    date DATETIME NOT NULL,
                    customer_name VARCHAR(255),
                    customer_id INT DEFAULT NULL,
                    cashier VARCHAR(150),
                    payment_method VARCHAR(40),
                    total DECIMAL(12,2) NOT NULL DEFAULT 0,
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
                )");
                
                $pdo->exec("CREATE TABLE transaction_items (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    transaction_id BIGINT NOT NULL,
                    code VARCHAR(64),
                    product VARCHAR(255) NOT NULL,
                    category VARCHAR(80),
                    qty INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
                )");
                
                $pdo->exec("CREATE TABLE stock_movements (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    date DATETIME NOT NULL,
                    code VARCHAR(40),
                    product VARCHAR(255),
                    category VARCHAR(80),
                    type ENUM('IN','OUT') NOT NULL,
                    qty INT NOT NULL,
                    unit_price DECIMAL(10,2),
                    payment_method ENUM('Cash','GCash')
                )");
                
                $pdo->exec("CREATE TABLE suppliers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    contact_person VARCHAR(255),
                    phone VARCHAR(100),
                    email VARCHAR(255),
                    address TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                echo "<p class='success'>✅ All tables created successfully.</p>";
                echo "<p class='success'>✅ Database is now completely empty and ready for your data.</p>";
                echo "<p><a href='index.html' class='btn btn-secondary'>Go to Dashboard</a></p>";
                
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
            }
        }
        ?>
    </div>
</body>
</html>