<?php
// api/init_db.php - Database initialization and seeding script

// Database connection parameters
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL server without selecting a database
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS gelo_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "Database 'gelo_pos' created or already exists.\n";
    
    // Connect to the gelo_pos database
    $pdo = new PDO("mysql:host=$host;dbname=gelo_pos;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Create tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(100) DEFAULT NULL,
        status ENUM('active','deleted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        transaction_id BIGINT NOT NULL,
        code VARCHAR(64),
        product VARCHAR(255) NOT NULL,
        category VARCHAR(80),
        qty INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(80) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock INT NOT NULL DEFAULT 0,
        image_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "Tables created successfully.\n";
    
    // Check if we already have products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $productCount = $stmt->fetch()['count'];
    
    // Database initialized with empty tables - ready for your data
    echo "Database initialized successfully with empty tables.\n";
    
    echo "Database initialization completed successfully.\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}