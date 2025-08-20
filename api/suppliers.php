<?php
// api/suppliers.php
header('Content-Type: application/json');
require __DIR__ . '/db.php';

// Ensure table exists
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    categories TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
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
  $status = isset($_GET['status']) ? $_GET['status'] : 'active';
  if ($status !== 'active' && $status !== 'deleted') $status = 'active';
  try {
    $stmt = $pdo->prepare("SELECT id, name, email, phone, address, categories, notes, status FROM suppliers WHERE status = ? ORDER BY name");
    $stmt->execute([$status]);
    $rows = $stmt->fetchAll();
    // Attempt to decode categories JSON to array when possible
    foreach ($rows as &$r) {
      if (isset($r['categories']) && $r['categories']) {
        $decoded = json_decode($r['categories'], true);
        if (json_last_error() === JSON_ERROR_NONE) $r['categories'] = $decoded;
      }
    }
    echo json_encode(['ok' => true, 'data' => $rows]);
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
  if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
  $email = trim($input['email'] ?? '');
  $phone = trim($input['phone'] ?? '');
  $address = trim($input['address'] ?? '');
  $categories = $input['categories'] ?? [];
  if (!is_array($categories)) $categories = [];
  $notes = trim($input['notes'] ?? '');
  try {
    $stmt = $pdo->prepare('INSERT INTO suppliers (name, email, phone, address, categories, notes, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
    $stmt->execute([$name, $email ?: null, $phone ?: null, $address ?: null, json_encode($categories), $notes ?: null]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

if ($method === 'PATCH' || $method === 'PUT') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid supplier ID required']); exit; }
  try {
    $updates = [];
    $params = [];
    if (isset($input['name'])) { $updates[] = 'name = ?'; $params[] = trim($input['name']); }
    if (isset($input['email'])) { $updates[] = 'email = ?'; $params[] = trim($input['email']); }
    if (isset($input['phone'])) { $updates[] = 'phone = ?'; $params[] = trim($input['phone']); }
    if (isset($input['address'])) { $updates[] = 'address = ?'; $params[] = trim($input['address']); }
    if (isset($input['categories'])) { $updates[] = 'categories = ?'; $params[] = json_encode(is_array($input['categories']) ? $input['categories'] : []); }
    if (isset($input['notes'])) { $updates[] = 'notes = ?'; $params[] = trim($input['notes']); }
    if (isset($input['status'])) { $updates[] = 'status = ?'; $params[] = ($input['status'] === 'deleted' ? 'deleted' : 'active'); }
    if (empty($updates)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No fields to update']); exit; }
    $params[] = $id;
    $stmt = $pdo->prepare('UPDATE suppliers SET '.implode(', ',$updates).' WHERE id = ?');
    $stmt->execute($params);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

if ($method === 'DELETE') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Valid supplier ID required']); exit; }
  try {
    $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok'=>true]);
  } catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);


