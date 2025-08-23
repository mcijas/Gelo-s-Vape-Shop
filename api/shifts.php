<?php
// api/shifts.php - Shift/Z-Report endpoint
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

// Ensure table exists (safety for legacy DBs)
$pdo->exec("CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_name VARCHAR(150) NOT NULL,
  opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
  closing_cash DECIMAL(12,2) DEFAULT NULL,
  sales_total DECIMAL(12,2) DEFAULT 0,
  variance DECIMAL(12,2) DEFAULT 0,
  started_at DATETIME NOT NULL,
  ended_at DATETIME DEFAULT NULL,
  duration_minutes INT DEFAULT NULL,
  status ENUM('open','closed') DEFAULT 'open'
)");

$method = $_SERVER['REQUEST_METHOD'];

// Helper to fetch open shift (optionally by employee)
function getOpenShift($pdo, $employee) {
  $stmt = $pdo->prepare("SELECT * FROM shifts WHERE status='open' AND employee_name=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$employee]);
  return $stmt->fetch();
}

$employeeName = $_SESSION['user']['name'] ?? 'Admin';

try {
  if ($method === 'GET') {
    // Optional: ?status=open/closed
    $status = $_GET['status'] ?? null;
    if ($status === 'open') {
      $data = getOpenShift($pdo, $employeeName);
      echo json_encode(['ok' => true, 'data' => $data]);
    } else {
      // Return recent shifts (limit 100)
      $stmt = $pdo->prepare("SELECT * FROM shifts ORDER BY started_at DESC LIMIT 100");
      $stmt->execute();
      echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
    }
    exit;
  }

  if ($method === 'POST') {
    // Start shift
    $input = json_decode(file_get_contents('php://input'), true);
    $openingCash = isset($input['opening_cash']) ? (float)$input['opening_cash'] : 0;
    // Close any previous open shift for same employee
    $pdo->prepare("UPDATE shifts SET status='closed', ended_at=NOW() WHERE status='open' AND employee_name=?")->execute([$employeeName]);
    $stmt = $pdo->prepare("INSERT INTO shifts (employee_name, opening_cash, started_at) VALUES (?, ?, NOW())");
    $stmt->execute([$employeeName, $openingCash]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
  }

  if ($method === 'PATCH') {
    // End shift
    $input = json_decode(file_get_contents('php://input'), true);
    $shiftId = isset($input['id']) ? (int)$input['id'] : 0;
    $closingCash = isset($input['closing_cash']) ? (float)$input['closing_cash'] : 0;
    // Fetch shift
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id=?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch();
    if (!$shift) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Shift not found']); exit; }
    if ($shift['status'] === 'closed') { echo json_encode(['ok'=>false,'error'=>'Shift already closed']); exit; }

    // Calculate sales total during shift
    $salesStmt = $pdo->prepare("SELECT SUM(total) as sales FROM transactions WHERE date BETWEEN ? AND NOW()");
    $salesStmt->execute([$shift['started_at']]);
    $salesTotal = (float)($salesStmt->fetch()['sales'] ?? 0);
    $variance = $closingCash - ($shift['opening_cash'] + $salesTotal);
    // Duration
    $durationMinutesStmt = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, '{$shift['started_at']}', NOW()) AS diff");
    $duration = (int)$durationMinutesStmt->fetch()['diff'];

    $upd = $pdo->prepare("UPDATE shifts SET closing_cash=?, sales_total=?, variance=?, ended_at=NOW(), duration_minutes=?, status='closed' WHERE id=?");
    $upd->execute([$closingCash, $salesTotal, $variance, $duration, $shiftId]);

    echo json_encode(['ok'=>true]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}