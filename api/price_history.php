<?php
// api/price_history.php - returns product price change history
header('Content-Type: application/json');
require __DIR__ . '/db.php';

try {
  // Ensure table exists
  $pdo->exec("CREATE TABLE IF NOT EXISTS product_price_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    old_price DECIMAL(10,2) NOT NULL,
    new_price DECIMAL(10,2) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(64) DEFAULT 'manual',
    user_id INT DEFAULT NULL,
    INDEX (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
  )");
} catch (Throwable $__) {}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  try {
    $from = isset($_GET['from']) ? $_GET['from'] : null;
    $to = isset($_GET['to']) ? $_GET['to'] : null;
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;

    $where = [];
    $params = [];
    if ($from) { $where[] = "changed_at >= ?"; $params[] = $from . " 00:00:00"; }
    if ($to) { $where[] = "changed_at <= ?"; $params[] = $to . " 23:59:59"; }
    if ($productId) { $where[] = "product_id = ?"; $params[] = $productId; }
    $sql = "SELECT h.id, h.product_id, p.name AS product_name, h.old_price, h.new_price, h.changed_at, h.reason, h.user_id,
                   u.full_name AS changed_by_name, u.username AS changed_by_username
            FROM product_price_history h
            LEFT JOIN products p ON p.id = h.product_id
            LEFT JOIN users u ON u.id = h.user_id";
    if (!empty($where)) { $sql .= " WHERE " . implode(" AND ", $where); }
    $sql .= " ORDER BY changed_at DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $rows]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
