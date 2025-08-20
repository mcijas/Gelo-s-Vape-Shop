<?php
// Set Manila timezone for this API endpoint
require_once __DIR__ . '/../timezone_helper.php';
// api/stock_movements.php - get all stock movements (both IN and OUT)
header('Content-Type: application/json');
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  try {
    $stmt = $pdo->query('SELECT id, date, code, product, category, type, qty, unit_price as unitCost, payment_method as paymentMethod, user_id FROM stock_movements ORDER BY date DESC LIMIT 1000');
    $movements = $stmt->fetchAll();
    
    // Format the response to match expected structure
    $formattedMovements = array_map(function($movement) {
      return [
        'id' => $movement['id'],
        'date' => $movement['date'],
        'code' => $movement['code'],
        'product' => $movement['product'],
        'category' => $movement['category'],
        'type' => $movement['type'],
        'qty' => (int)$movement['qty'],
        'unitCost' => (float)$movement['unitCost'],
        'paymentMethod' => $movement['paymentMethod'],
        'userId' => isset($movement['user_id']) ? (int)$movement['user_id'] : null,
      ];
    }, $movements);
    
    echo json_encode(['ok' => true, 'data' => $formattedMovements]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
?>