<?php
// api/stock_in.php - log supplier deliveries as IN stock movements and update product stock
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

// Ensure tables exist
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    code VARCHAR(40),
    product VARCHAR(255),
    supplier VARCHAR(255),
    category VARCHAR(80),
    type ENUM('IN','OUT') NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(10,2),
    payment_method ENUM('Cash','GCash'),
    user_id INT DEFAULT NULL
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    reorder_point INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  // Ensure columns exist on legacy tables
  try { $pdo->exec("ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS supplier VARCHAR(255)"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS reorder_point INT NOT NULL DEFAULT 0"); } catch (Throwable $__) {}
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  try {
    $stmt = $pdo->query('SELECT id, date, code, product, supplier, category, type, qty, unit_price as unitCost, payment_method as paymentMethod, user_id FROM stock_movements ORDER BY date DESC LIMIT 1000');
    echo json_encode(['ok'=>true, 'data'=>$stmt->fetchAll()]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']); exit; }

// Handle both JSON and FormData
$input = [];
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
} else {
  // Handle FormData
  $input = $_POST;
}

$date = isset($input['date']) ? date('Y-m-d', strtotime($input['date'])) . ' 00:00:00' : date('Y-m-d H:i:s');
$supplier = trim($input['supplier'] ?? '');
$product = trim($input['product'] ?? '');
$category = trim($input['category'] ?? '');
$qty = (int)($input['qty'] ?? 0);
$unitCost = (float)($input['unitCost'] ?? 0);
$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

if ($product === '' || $qty <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Product and positive qty required']); exit; }

error_log("Stock IN request: product=$product, category=$category, qty=$qty, unitCost=$unitCost, supplier=$supplier");

try {
  $pdo->beginTransaction();

  // Upsert product by name
  $sel = $pdo->prepare('SELECT id, stock FROM products WHERE name = ? LIMIT 1');
  $sel->execute([$product]);
  $row = $sel->fetch();
  if ($row) {
    $newStock = (int)$row['stock'] + $qty;
    $upd = $pdo->prepare('UPDATE products SET stock = ?, category = COALESCE(NULLIF(?, ""), category) WHERE id = ?');
    $upd->execute([$newStock, $category, (int)$row['id']]);
    $productId = (int)$row['id'];
  } else {
    $ins = $pdo->prepare('INSERT INTO products (name, category, price, stock) VALUES (?, ?, 0, ?)');
    $ins->execute([$product, $category, $qty]);
    $productId = (int)$pdo->lastInsertId();
  }

  // Record IN movement and attach user + supplier
  $mv = $pdo->prepare('INSERT INTO stock_movements (date, code, product, supplier, category, type, qty, unit_price, payment_method, user_id) VALUES (?, ?, ?, ?, ?, "IN", ?, ?, NULL, ?)');
  $mv->execute([$date, $productId, $product, $supplier, $category, $qty, $unitCost, $userId]);

  error_log("Stock IN movement recorded successfully for product=$product");

  $pdo->commit();
  echo json_encode(['ok'=>true, 'productId'=>$productId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}


