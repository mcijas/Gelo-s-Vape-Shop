<?php
// api/customers.php
header('Content-Type: application/json');
require __DIR__ . '/db.php';

// Ensure table exists
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(100) DEFAULT NULL,
    status ENUM('active','deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  try {
    $stmt = $pdo->query("SELECT id, name, email, phone FROM customers WHERE status='active' ORDER BY name");
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

if ($method === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
  $name = trim($input['name'] ?? '');
  $email = trim($input['email'] ?? '');
  $phone = trim($input['phone'] ?? '');
  if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
  try {
    $stmt = $pdo->prepare('INSERT INTO customers (name, email, phone, status) VALUES (?, ?, ?, "active")');
    $stmt->execute([$name, $email ?: null, $phone ?: null]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

if ($method === 'PATCH' || $method === 'PUT') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid customer ID required']); exit; }
  try {
    $updates = [];
    $params = [];
    if (isset($input['name'])) { $updates[] = 'name = ?'; $params[] = trim($input['name']); }
    if (isset($input['email'])) { $updates[] = 'email = ?'; $params[] = trim($input['email']); }
    if (isset($input['phone'])) { $updates[] = 'phone = ?'; $params[] = trim($input['phone']); }
    if (isset($input['status'])) { $updates[] = 'status = ?'; $params[] = ($input['status'] === 'deleted' ? 'deleted' : 'active'); }
    if (empty($updates)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No fields to update']); exit; }
    $params[] = $id;
    $stmt = $pdo->prepare('UPDATE customers SET '.implode(', ',$updates).' WHERE id = ?');
    $stmt->execute($params);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

if ($method === 'DELETE') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid customer ID required']); exit; }
  try {
    // soft delete by default
    $stmt = $pdo->prepare('UPDATE customers SET status = "deleted" WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);


