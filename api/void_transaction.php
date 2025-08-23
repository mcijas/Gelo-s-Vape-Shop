<?php
// api/void_transaction.php - void/cancel a transaction
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

// make sure table exists (should already via init_db)
$pdo->exec("CREATE TABLE IF NOT EXISTS voided_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT NOT NULL,
  employee_name VARCHAR(150) NOT NULL,
  reason TEXT,
  voided_at DATETIME NOT NULL,
  FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
)");

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: list recent voided/cancelled transactions
if ($method === 'GET') {
  try {
    $stmt = $pdo->prepare("SELECT id, transaction_id, employee_name, reason, voided_at FROM voided_transactions ORDER BY voided_at DESC LIMIT 100");
    $stmt->execute();
    echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
  }
  exit;
}

// Handle POST: void a transaction
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if(!$input || empty($input['transaction_id']) || empty($input['reason'])){
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'transaction_id and reason required']);
  exit;
}
$tid = (int)$input['transaction_id'];
$reason = trim($input['reason']);
$employee = $_SESSION['user']['name'] ?? 'Admin';

try{
  $pdo->beginTransaction();
  // fetch transaction exists
  $stmt=$pdo->prepare('SELECT * FROM transactions WHERE id=?');
  $stmt->execute([$tid]);
  $txn=$stmt->fetch();
  if(!$txn){ throw new Exception('Transaction not found'); }

  // restore stock for each item
  $items=$pdo->prepare('SELECT product, qty FROM transaction_items WHERE transaction_id=?');
  $items->execute([$tid]);
  foreach($items as $it){
    $pdo->prepare('UPDATE products SET stock = stock + ? WHERE name=?')->execute([$it['qty'], $it['product']]);
  }

  // delete stock movements related to this txn
  $pdo->prepare('DELETE FROM stock_movements WHERE type="OUT" AND date=? AND product IS NOT NULL')->execute([$txn['date']]);

  // remove the transaction and items (ON DELETE CASCADE for items already) or mark as void; easier: delete
  $pdo->prepare('DELETE FROM transactions WHERE id=?')->execute([$tid]);

  // log void action
  $ins=$pdo->prepare('INSERT INTO voided_transactions (transaction_id, employee_name, reason, voided_at) VALUES (?,?,?,NOW())');
  $ins->execute([$tid,$employee,$reason]);

  $pdo->commit();
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}