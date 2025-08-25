<?php
// api/transactions.php
header('Content-Type: application/json');
// Start session to get current user id for auditing
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

// Ensure necessary tables exist
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(64) NOT NULL,
    date DATETIME NOT NULL,
    customer_name VARCHAR(255),
    customer_id INT DEFAULT NULL,
    cashier VARCHAR(150),
    payment_method VARCHAR(40),
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    user_id INT DEFAULT NULL,
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
    payment_method ENUM('Cash','GCash'),
    user_id INT DEFAULT NULL
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255) DEFAULT NULL,
    barcode VARCHAR(128) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )");
  // Add user_id and discount columns for existing tables if they don't exist (MySQL 8+)
  try { $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Throwable $__ ) {}
  try { $pdo->exec("ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Throwable $__ ) {}
  try { $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) NOT NULL DEFAULT 0"); } catch (Throwable $__ ) {}
  // Link transactions to shifts/sessions and explicit cashier_id for analytics
  try { $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS shift_id INT DEFAULT NULL"); } catch (Throwable $__ ) {}
  try { $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS cashier_id INT DEFAULT NULL"); } catch (Throwable $__ ) {}
  // Ensure barcode exists on legacy products table
  try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS barcode VARCHAR(128) UNIQUE"); } catch (Throwable $__ ) {}
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'DB init failed: '.$e->getMessage()]);
  exit;
}

// Handle GET: return transactions with items
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Content-Type: application/json');
  try {
    $txStmt = $pdo->query("SELECT t.id, t.ref, t.date, t.customer_name, t.customer_id, t.cashier, t.payment_method, t.total, t.discount, c.name as customer FROM transactions t LEFT JOIN customers c ON t.customer_id = c.id ORDER BY t.date DESC LIMIT 1000");
    $txns = $txStmt->fetchAll();
    $itemsStmt = $pdo->query("SELECT transaction_id, code, product, category, qty, price FROM transaction_items");
    $items = $itemsStmt->fetchAll();
    $map = [];
    foreach ($txns as $t) {
      $t['items'] = [];
      // Use actual customer name from customers table if customer_id is set
      if ($t['customer_id'] && $t['customer']) {
        $t['customer_name'] = $t['customer'];
      }
      unset($t['customer']); // Remove the joined customer field
      $map[$t['id']] = $t;
    }
    foreach ($items as $it) {
      $tid = (int)$it['transaction_id'];
      if (isset($map[$tid])) { $map[$tid]['items'][] = [
        'code' => $it['code'], 'product' => $it['product'], 'category' => $it['category'], 'qty' => (int)$it['qty'], 'price' => (float)$it['price']
      ]; }
    }
    echo json_encode(['ok' => true, 'data' => array_values($map)]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// Handle DELETE: remove all transactions and corresponding OUT stock movements
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
  header('Content-Type: application/json');
  try {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM transaction_items");
    $pdo->exec("DELETE FROM transactions");
    $pdo->exec("DELETE FROM stock_movements WHERE type = 'OUT'");
    $pdo->commit();
    echo json_encode(['ok' => true]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
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

$ref = $input['id'] ?? ('TXN-' . time());
$date = isset($input['date']) ? date('Y-m-d H:i:s', strtotime($input['date'])) : date('Y-m-d H:i:s');
$customer = $input['customer'] ?? 'Walk-in';
$cashier = $input['cashier'] ?? ($_SESSION['user']['name'] ?? 'Admin');
$method = $input['paymentMethod'] ?? 'Cash';
$total = (float)($input['total'] ?? 0);
$discount = (float)($input['discount'] ?? 0);
$items = $input['items'] ?? [];
$userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
$shiftId = isset($input['shift_id']) ? (int)$input['shift_id'] : null;

// Ensure non-negative values
if ($total < 0) { $total = 0; }
if ($discount < 0) { $discount = 0; }
$netTotal = max(0, $total - $discount);

try {
  $pdo->beginTransaction();

  $customer_id = isset($input['customer_id']) && $input['customer_id'] > 0 ? (int)$input['customer_id'] : null;
  $stmt = $pdo->prepare("INSERT INTO transactions (ref, date, customer_name, customer_id, cashier, payment_method, total, discount, user_id, shift_id, cashier_id)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" );
  $stmt->execute([$ref, $date, $customer, $customer_id, $cashier, $method, $netTotal, $discount, $userId, $shiftId, $userId]);
  $txnId = (int)$pdo->lastInsertId();

  if (!empty($items)) {
    $itemStmt = $pdo->prepare("INSERT INTO transaction_items (transaction_id, code, product, category, qty, price)
                               VALUES (?, ?, ?, ?, ?, ?)");
    $moveStmt = $pdo->prepare("INSERT INTO stock_movements (date, code, product, category, type, qty, unit_price, payment_method, user_id)
                               VALUES (?, ?, ?, ?, 'OUT', ?, ?, ?, ?)");
    $decStockStmt = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE name = ?");
    foreach ($items as $it) {
      $code = $it['code'] ?? null;
      $product = $it['product'] ?? '';
      $category = $it['category'] ?? '';
      $qty = (int)($it['qty'] ?? 0);
      $price = (float)($it['price'] ?? 0);

      $itemStmt->execute([$txnId, $code, $product, $category, $qty, $price]);
      $moveStmt->execute([$date, $code, $product, $category, $qty, $price, $method, $userId]);
      // decrement product stock by product name
      $decStockStmt->execute([$qty, $product]);
    }
  }

  $pdo->commit();
  echo json_encode(['ok' => true, 'ref' => $ref, 'id' => $txnId]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}


