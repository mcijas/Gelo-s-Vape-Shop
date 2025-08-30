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

// Ensure columns exist on legacy tables (ignore errors if they already exist)
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN categories TEXT DEFAULT NULL"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN status ENUM('active','deleted') DEFAULT 'active'"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN email VARCHAR(255) DEFAULT NULL"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN phone VARCHAR(100) DEFAULT NULL"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN address TEXT DEFAULT NULL"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN notes TEXT DEFAULT NULL"); } catch (Throwable $__) {}
// New: last_order_date for showing Last Order in Suppliers page
try { $pdo->exec("ALTER TABLE suppliers ADD COLUMN last_order_date DATETIME DEFAULT NULL"); } catch (Throwable $__) {}

// Extra safety: ensure missing columns exist at runtime (covers legacy DBs without migrations)
if (!function_exists('ensureColumnExists')) {
  function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition) {
    try {
      $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
      $stmt->execute([$column]);
      if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
      }
    } catch (Throwable $__){ /* ignore; handled later by SQL errors */ }
  }
}
ensureColumnExists($pdo, 'suppliers', 'email', 'VARCHAR(255) DEFAULT NULL');
ensureColumnExists($pdo, 'suppliers', 'phone', 'VARCHAR(100) DEFAULT NULL');
ensureColumnExists($pdo, 'suppliers', 'address', 'TEXT DEFAULT NULL');
ensureColumnExists($pdo, 'suppliers', 'categories', 'TEXT DEFAULT NULL');
ensureColumnExists($pdo, 'suppliers', 'notes', 'TEXT DEFAULT NULL');
ensureColumnExists($pdo, 'suppliers', 'status', "ENUM('active','deleted') DEFAULT 'active'");
ensureColumnExists($pdo, 'suppliers', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
ensureColumnExists($pdo, 'suppliers', 'last_order_date', 'DATETIME DEFAULT NULL');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $status = isset($_GET['status']) ? $_GET['status'] : 'active';
  if ($status !== 'active' && $status !== 'deleted') $status = 'active';
  try {
    // Prefer dynamic last_order_date from stock_movements (IN) and fall back to stored column
    $sql = "SELECT s.id, s.name, s.email, s.phone, s.address, s.categories, s.notes, s.status, s.created_at,
                   COALESCE(s.last_order_date, sm.last_order_date) AS last_order_date
            FROM suppliers s
            LEFT JOIN (
              SELECT supplier, MAX(date) AS last_order_date
              FROM stock_movements
              WHERE supplier IS NOT NULL AND type = 'IN'
              GROUP BY supplier
            ) sm ON sm.supplier = s.name
            WHERE s.status = ?
            ORDER BY s.name";
    $stmt = $pdo->prepare($sql);
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
    // Fallback if stock_movements table is missing or join fails
    try {
      $stmt = $pdo->prepare("SELECT id, name, email, phone, address, categories, notes, status, created_at, last_order_date FROM suppliers WHERE status = ? ORDER BY name");
      $stmt->execute([$status]);
      $rows = $stmt->fetchAll();
      foreach ($rows as &$r) {
        if (isset($r['categories']) && $r['categories']) {
          $decoded = json_decode($r['categories'], true);
          if (json_last_error() === JSON_ERROR_NONE) $r['categories'] = $decoded;
        }
      }
      echo json_encode(['ok' => true, 'data' => $rows]);
    } catch (Throwable $e2) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => $e2->getMessage()]);
    }
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
    if (isset($input['last_order_date'])) { $updates[] = 'last_order_date = ?'; $params[] = $input['last_order_date'] ? date('Y-m-d H:i:s', strtotime($input['last_order_date'])) : null; }
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


