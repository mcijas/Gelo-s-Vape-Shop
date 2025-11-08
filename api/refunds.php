<?php
// api/refunds.php - process and list refunds/returns
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

try {
  // Create refunds table (auditable)
  $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
    refund_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT,
    product_id INT NULL,
    product_name VARCHAR(255) NULL,
    quantity INT NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    refund_date DATETIME NOT NULL,
    cashier_id INT NULL,
    reason TEXT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Helpful indexes
  try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_refunds_txn ON refunds(transaction_id)"); } catch (Throwable $__) {}
  try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_refunds_date ON refunds(refund_date)"); } catch (Throwable $__) {}
  // Soft foreign keys (best-effort)
  try { $pdo->exec("ALTER TABLE refunds ADD CONSTRAINT fk_refunds_txn FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL"); } catch (Throwable $__) {}
  try { $pdo->exec("ALTER TABLE refunds ADD CONSTRAINT fk_refunds_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL"); } catch (Throwable $__) {}

  // Ensure transactions table has status column
  try { $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS status ENUM('completed','voided','refunded') NOT NULL DEFAULT 'completed'"); } catch (Throwable $__) {}
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB init failed: '.$e->getMessage()]);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // List recent refunds, optionally filter by transaction_id
  $tid = isset($_GET['transaction_id']) ? (int)$_GET['transaction_id'] : null;
  try {
    if ($tid) {
      $stmt = $pdo->prepare("SELECT refund_id, transaction_id, product_id, product_name, quantity, refund_amount, refund_date, cashier_id, reason FROM refunds WHERE transaction_id = ? ORDER BY refund_date DESC");
      $stmt->execute([$tid]);
    } else {
      $stmt = $pdo->query("SELECT refund_id, transaction_id, product_id, product_name, quantity, refund_amount, refund_date, cashier_id, reason FROM refunds ORDER BY refund_date DESC LIMIT 500");
    }
    echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  }
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['transaction_id']) || empty($input['items']) || !is_array($input['items'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'transaction_id and items required']);
  exit;
}

$transaction_id = (int)$input['transaction_id'];
$reason = isset($input['reason']) ? trim((string)$input['reason']) : null;
$items = $input['items']; // array of { product (or code), qty }
$cashier_id = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

try {
  $pdo->beginTransaction();

  // Load transaction and items
  $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
  $stmt->execute([$transaction_id]);
  $txn = $stmt->fetch();
  if (!$txn) { throw new Exception('Transaction not found'); }

  $itStmt = $pdo->prepare('SELECT id, code, product, category, qty, price FROM transaction_items WHERE transaction_id = ?');
  $itStmt->execute([$transaction_id]);
  $soldItems = $itStmt->fetchAll();
  if (!$soldItems) { throw new Exception('Transaction has no items'); }

  $soldMap = [];
  foreach ($soldItems as $si) {
    $key = $si['code'] ? ('CODE:'.strval($si['code'])) : ('NAME:'.strval($si['product']));
    $soldMap[$key] = $si;
  }

  // Helper: get already refunded qty per item (by product_name if product_id not stored)
  $prevRefundQtyByName = [];
  $pr = $pdo->prepare('SELECT product_name, SUM(quantity) as qty FROM refunds WHERE transaction_id = ? GROUP BY product_name');
  $pr->execute([$transaction_id]);
  foreach ($pr as $row) { $prevRefundQtyByName[$row['product_name']] = (int)$row['qty']; }

  $insRefund = $pdo->prepare('INSERT INTO refunds (transaction_id, product_id, product_name, quantity, refund_amount, refund_date, cashier_id, reason) VALUES (?,?,?,?,?,NOW(),?,?)');
  $incStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
  $incStockByName = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE name = ?');
  $findProductByCode = $pdo->prepare('SELECT id, name, category FROM products WHERE barcode = ? LIMIT 1');
  $findProductByName = $pdo->prepare('SELECT id, name, category FROM products WHERE name = ? LIMIT 1');
  $insMove = $pdo->prepare("INSERT INTO stock_movements (date, code, product, category, type, qty, unit_price, payment_method, user_id) VALUES (NOW(), ?, ?, ?, 'IN', ?, ?, NULL, ?)");

  $totalRefundAmount = 0.0;
  $anyRefunded = false;

  foreach ($items as $req) {
    $reqCode = isset($req['code']) ? trim((string)$req['code']) : null;
    $reqProduct = isset($req['product']) ? trim((string)$req['product']) : null;
    $reqQty = max(0, (int)($req['qty'] ?? 0));
    if ($reqQty <= 0) { continue; }

    // locate sold item
    $si = null;
    if ($reqCode) {
      $k = 'CODE:'.strval($reqCode);
      if (isset($soldMap[$k])) $si = $soldMap[$k];
    }
    if (!$si && $reqProduct) {
      $k = 'NAME:'.strval($reqProduct);
      if (isset($soldMap[$k])) $si = $soldMap[$k];
    }
    if (!$si) { throw new Exception('Item not found in original transaction: '.($reqProduct ?: $reqCode ?: 'unknown')); }

    $productName = $si['product'];
    $price = (float)$si['price'];
    $soldQty = (int)$si['qty'];
    $alreadyRefunded = (int)($prevRefundQtyByName[$productName] ?? 0);
    $remaining = max(0, $soldQty - $alreadyRefunded);
    if ($reqQty > $remaining) {
      throw new Exception("Refund quantity for $productName exceeds remaining ($remaining)");
    }

    // Resolve product_id and category (best-effort)
    $prodId = null; $category = $si['category'] ?? null; $code = $si['code'] ?? null;
    if ($code) {
      $findProductByCode->execute([$code]);
      $p = $findProductByCode->fetch();
      if ($p) { $prodId = (int)$p['id']; if (!$category) $category = $p['category'] ?? null; }
    }
    if (!$prodId) {
      $findProductByName->execute([$productName]);
      $p = $findProductByName->fetch();
      if ($p) { $prodId = (int)$p['id']; if (!$category) $category = $p['category'] ?? null; }
    }

    $amount = round($price * $reqQty, 2);

    // Insert refund row
    $insRefund->execute([$transaction_id, $prodId, $productName, $reqQty, $amount, $cashier_id, $reason]);

    // Update inventory
    if ($prodId) { $incStock->execute([$reqQty, $prodId]); }
    else { $incStockByName->execute([$reqQty, $productName]); }

    // Log stock movement (IN) for traceability
    $insMove->execute([$code, $productName, $category, $reqQty, $price, $cashier_id]);

    $totalRefundAmount += $amount;
    $anyRefunded = true;

    // Track refunded qty to prevent over-refunds within this request
    $prevRefundQtyByName[$productName] = ($prevRefundQtyByName[$productName] ?? 0) + $reqQty;
  }

  if (!$anyRefunded) { throw new Exception('No items were refunded'); }

  // Mark transaction status as refunded (even if partial)
  try {
    $pdo->prepare("UPDATE transactions SET status='refunded' WHERE id = ?")->execute([$transaction_id]);
  } catch (Throwable $__) {}

  $pdo->commit();
  echo json_encode(['ok'=>true, 'transaction_id'=>$transaction_id, 'refund_total'=>$totalRefundAmount]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}