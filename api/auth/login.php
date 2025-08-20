<?php
// api/auth/login.php
session_start();
header('Content-Type: application/json');
require __DIR__ . '/../db.php';

// Ensure users table exists and seed a default admin if empty (dev convenience)
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
  // Migrate legacy tables to include new fields if they don't exist
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS employee_id VARCHAR(50) DEFAULT NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS active TINYINT(1) DEFAULT 1"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'"); } catch (Throwable $__) {}

  $stmtChk = $pdo->query("SELECT COUNT(*) AS c FROM users");
  $count = (int)($stmtChk->fetch()['c'] ?? 0);
  if ($count === 0) {
    $name = 'Admin';
    $fullName = 'System Administrator';
    $usernameSeed = 'admin';
    $passSeed = 'admin123';
    $employeeId = 'EMP001';
    $phone = '09123456789';
    $hashSeed = password_hash($passSeed, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (name, full_name, username, password_hash, role, phone, employee_id, active) VALUES (?, ?, ?, ?, 'admin', ?, ?, 1)");
    $ins->execute([$name, $fullName, $usernameSeed, $hashSeed, $phone, $employeeId]);
  }
} catch (Throwable $e) {
  // Ignore; proper error will be handled below during login
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
  exit;
}

$username = trim((string)($input['username'] ?? $input['email'] ?? ''));
$password = (string)($input['password'] ?? '');
$remember = !empty($input['remember']);

if (!$username || !$password) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Username and password are required']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id, name, full_name, username, password_hash, role, phone, employee_id, active, status FROM users WHERE username = ? LIMIT 1");
  $stmt->execute([$username]);
  $user = $stmt->fetch();
  if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
    exit;
  }
  if ((isset($user['status']) && $user['status'] === 'disabled') || (isset($user['active']) && (int)$user['active'] !== 1)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Account disabled']);
    exit;
  }

  $hash = $user['password_hash'] ?? '';
  if (!password_verify($password, $hash)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
    exit;
  }

  $_SESSION['user'] = [
    'id' => (int)$user['id'],
    'name' => $user['name'] ?: 'Admin',
    'full_name' => $user['full_name'] ?? null,
    'username' => $user['username'] ?? $username,
    'role' => $user['role'] ?: 'admin',
    'phone' => $user['phone'] ?? null,
    'employee_id' => $user['employee_id'] ?? null,
  ];

  // Update last_login timestamp
  try {
    $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $upd->execute([ (int)$user['id'] ]);
  } catch (Throwable $__) { /* ignore */ }

  // If remember me checked, extend session cookie lifetime (e.g., 30 days)
  if ($remember) {
    $params = session_get_cookie_params();
    // recreate cookie with longer duration
    setcookie(
      session_name(),
      session_id(),
      time() + (86400 * 30), // 30 days
      $params['path'] ?: '/',
      $params['domain'] ?? '',
      isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
      true
    );
  }

  echo json_encode(['ok' => true, 'user' => $_SESSION['user']]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}


