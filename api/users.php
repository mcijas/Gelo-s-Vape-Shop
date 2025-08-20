<?php
// api/users.php - admin-only CRUD for employees (users)
session_start();
header('Content-Type: application/json');
require __DIR__ . '/db.php';

// Enforce admin role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Admin access required']);
  exit;
}

// Ensure table exists (redundant but safe)
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'DB init failed']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/users.php – list all users
if ($method === 'GET') {
  try {
    $stmt = $pdo->query("SELECT id, name, full_name, username, role, phone, employee_id, active, last_login, created_at FROM users ORDER BY created_at DESC");
    echo json_encode(['ok' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// POST /api/users.php – create new user
if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
  }
  $name = trim($input['name'] ?? '');
  $fullName = trim($input['full_name'] ?? '');
  $username = trim($input['username'] ?? '');
  $password = trim($input['password'] ?? '');
  $role = in_array($input['role'], ['admin','employee']) ? $input['role'] : 'employee';
  $phone = trim($input['phone'] ?? '');
  $employeeId = trim($input['employee_id'] ?? '');

  if (!$name || !$username || !$password) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name, username, password required']);
    exit;
  }

  // Auto-generate employee ID if missing
  if ($employeeId === '') {
    try {
      $employeeId = 'EMP-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
      $employeeId = 'EMP-' . date('Ymd') . '-0000';
    }
  }

  try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, full_name, username, password_hash, role, phone, employee_id, active) VALUES (?,?,?,?,?,?,?,1)");
    $stmt->execute([$name, $fullName, $username, $hash, $role, $phone, $employeeId]);
    $id = $pdo->lastInsertId();
    echo json_encode(['ok' => true, 'id' => (int)$id, 'employee_id' => $employeeId]);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'Username already taken']);
    } else {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
  }
  exit;
}

// PUT /api/users.php – update user (admin only)
if ($method === 'PUT') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id required']);
    exit;
  }
  $id = (int)$input['id'];

  // Allow lightweight toggle of active status without requiring full payload
  if (isset($input['active']) && !isset($input['name']) && !isset($input['username']) && !isset($input['role']) && !isset($input['phone']) && !isset($input['employee_id']) && !isset($input['full_name'])) {
    try {
      $stmt = $pdo->prepare("UPDATE users SET active=? WHERE id=?");
      $stmt->execute([(int)$input['active'], $id]);
      echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
  }

  $name = trim($input['name'] ?? '');
  $fullName = trim($input['full_name'] ?? '');
  $username = trim($input['username'] ?? '');
  $role = in_array($input['role'], ['admin','employee']) ? $input['role'] : 'employee';
  $phone = trim($input['phone'] ?? '');
  $employeeId = trim($input['employee_id'] ?? '');
  $active = isset($input['active']) ? (int)$input['active'] : 1;

  if (!$name || !$username) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name and username required']);
    exit;
  }

  try {
    $stmt = $pdo->prepare("UPDATE users SET name=?, full_name=?, username=?, role=?, phone=?, employee_id=?, active=? WHERE id=?");
    $stmt->execute([$name, $fullName, $username, $role, $phone, $employeeId, $active, $id]);
    echo json_encode(['ok' => true]);
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'Username already taken']);
    } else {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
  }
  exit;
}

// PATCH /api/users.php – reset password (admin only)
if ($method === 'PATCH') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input || !isset($input['id']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id and password required']);
    exit;
  }
  $id = (int)$input['id'];
  $password = trim($input['password']);
  if (strlen($password) < 4) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Password too short']);
    exit;
  }
  $hash = password_hash($password, PASSWORD_DEFAULT);
  try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
    $stmt->execute([$hash, $id]);
    echo json_encode(['ok' => true]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);