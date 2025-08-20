<?php
// api/auth/seed_admin.php - one-time utility to create an admin if none exists
require __DIR__ . '/../db.php';

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    full_name VARCHAR(255) DEFAULT NULL,
    username VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','employee') DEFAULT 'employee',
    phone VARCHAR(20) DEFAULT NULL,
    employee_id VARCHAR(50) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");

  $stmt = $pdo->query("SELECT COUNT(*) AS c FROM users");
  $count = (int)$stmt->fetch()['c'];
  if ($count === 0) {
    $name = 'Admin';
    $fullName = 'System Administrator';
    $username = 'admin';
    $pass = 'admin123';
    $employeeId = 'EMP001';
    $phone = '09123456789';
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (name, full_name, username, password_hash, role, phone, employee_id, active) VALUES (?, ?, ?, ?, 'admin', ?, ?, 1)");
    $ins->execute([$name, $fullName, $username, $hash, $phone, $employeeId]);
    echo "Seeded admin user: $username / $pass";
  } else {
    echo "Users already present.";
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error: ' . $e->getMessage();
}


