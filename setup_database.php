<?php
// Database setup script - run this to initialize the database
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS gelo_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database 'gelo_pos' created or already exists.<br>";
    
    // Connect to the gelo_pos database
    $pdo = new PDO("mysql:host=$host;dbname=gelo_pos;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Drop existing tables to recreate with new schema
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS transaction_items");
    $pdo->exec("DROP TABLE IF EXISTS stock_movements");
    $pdo->exec("DROP TABLE IF EXISTS transactions");
    $pdo->exec("DROP TABLE IF EXISTS products");
    $pdo->exec("DROP TABLE IF EXISTS customers");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Existing tables dropped.<br>";
    
    // Create tables in correct order
    $pdo->exec("CREATE TABLE customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(100) DEFAULT NULL,
        status ENUM('active','deleted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Customers table created.<br>";
    
    $pdo->exec("CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(80) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock INT NOT NULL DEFAULT 0,
        image_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Products table created.<br>";
    
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
    echo "Transactions table created.<br>";
    
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
    echo "Transaction_items table created.<br>";
    
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
    echo "Stock_movements table created.<br>";
    
    // Insert sample data
    $pdo->exec("INSERT INTO customers (name, email, phone) VALUES 
        ('John Doe', 'john@example.com', '09123456789'),
        ('Jane Smith', 'jane@example.com', '09876543210')");
    echo "Sample customers added.<br>";
    
    $pdo->exec("INSERT INTO products (name, category, price, stock) VALUES 
        ('Vape Pen Starter Kit', 'Units', 1200.00, 25),
        ('Pod System', 'Units', 1500.00, 15),
        ('Vape Mod Kit', 'Units', 2500.00, 10),
        ('Disposable Vape Pen', 'Disposable', 350.00, 40),
        ('Disposable Pod Kit', 'Disposable', 450.00, 30),
        ('Premium E-Liquid 60ml', 'E-juice', 350.00, 40),
        ('Nicotine Salt E-Liquid', 'E-juice', 400.00, 35),
        ('Fruit Flavor E-Liquid', 'E-juice', 300.00, 45),
        ('Dessert Flavor E-Liquid', 'E-juice', 320.00, 38),
        ('Replacement Coils (5-pack)', 'Hardware', 450.00, 30),
        ('Vape Battery', 'Hardware', 600.00, 20),
        ('Cotton Wicks', 'Hardware', 150.00, 50)");
    echo "Sample products added.<br>";
    
    $pdo->exec("INSERT INTO transactions (ref, date, customer_name, customer_id, cashier, payment_method, total) VALUES 
        ('TXN-20241231-001', NOW() - INTERVAL 2 HOUR, 'John Doe', 1, 'Admin', 'Cash', 1550.00),
        ('TXN-20241231-002', NOW() - INTERVAL 1 HOUR, 'Jane Smith', 2, 'Admin', 'GCash', 2100.00),
        ('TXN-20241231-003', NOW(), 'Walk-in Customer', NULL, 'Admin', 'Cash', 750.00)");
    echo "Sample transactions added.<br>";
    
    $pdo->exec("INSERT INTO transaction_items (transaction_id, code, product, category, qty, price) VALUES 
        (1, 'VP001', 'Vape Pen Starter Kit', 'Units', 1, 1200.00),
        (1, 'EL001', 'Premium E-Liquid 60ml', 'E-juice', 1, 350.00),
        (2, 'VM001', 'Vape Mod Kit', 'Units', 1, 2500.00),
        (2, 'RC001', 'Replacement Coils (5-pack)', 'Hardware', 1, 450.00),
        (2, 'EL002', 'Nicotine Salt E-Liquid', 'E-juice', 1, 400.00),
        (3, 'EL003', 'Fruit Flavor E-Liquid', 'E-juice', 1, 300.00),
        (3, 'EL004', 'Dessert Flavor E-Liquid', 'E-juice', 1, 320.00),
        (3, 'CW001', 'Cotton Wicks', 'Hardware', 1, 150.00)");
    echo "Sample transaction items added.<br>";
    
    $pdo->exec("INSERT INTO stock_movements (date, code, product, category, type, qty, unit_price, payment_method) VALUES 
        (NOW() - INTERVAL 2 HOUR, 'VP001', 'Vape Pen Starter Kit', 'Units', 'OUT', 1, 1200.00, 'Cash'),
        (NOW() - INTERVAL 2 HOUR, 'EL001', 'Premium E-Liquid 60ml', 'E-juice', 'OUT', 1, 350.00, 'Cash'),
        (NOW() - INTERVAL 1 HOUR, 'VM001', 'Vape Mod Kit', 'Units', 'OUT', 1, 2500.00, 'GCash'),
        (NOW() - INTERVAL 1 HOUR, 'RC001', 'Replacement Coils (5-pack)', 'Hardware', 'OUT', 1, 450.00, 'GCash'),
        (NOW() - INTERVAL 1 HOUR, 'EL002', 'Nicotine Salt E-Liquid', 'E-juice', 'OUT', 1, 400.00, 'GCash'),
        (NOW(), 'EL003', 'Fruit Flavor E-Liquid', 'E-juice', 'OUT', 1, 300.00, 'Cash'),
        (NOW(), 'EL004', 'Dessert Flavor E-Liquid', 'E-juice', 'OUT', 1, 320.00, 'Cash'),
        (NOW(), 'CW001', 'Cotton Wicks', 'Hardware', 'OUT', 1, 150.00, 'Cash')");
    echo "Sample stock movements added.<br>";
    
    echo "<h2 style='color: green;'>Database setup completed successfully!</h2>";
    echo "<p>You can now access the dashboard at <a href='dashboard.html'>dashboard.html</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Database setup failed: " . $e->getMessage() . "</h2>";
}
?>