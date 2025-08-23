<?php
// api/staff_hours.php - Clock In / Clock Out endpoint
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/db.php';

// Ensure table exists (safety)
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_hours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_name VARCHAR(150) NOT NULL,
  clock_in DATETIME NOT NULL,
  clock_out DATETIME DEFAULT NULL,
  total_minutes INT DEFAULT NULL,
  shift_id INT DEFAULT NULL,
  status ENUM('in','out') DEFAULT 'in'
)");

$method = $_SERVER['REQUEST_METHOD'];
$employeeName = $_SESSION['user']['name'] ?? 'Admin';

function getOpenClock($pdo, $employee) {
  $stmt = $pdo->prepare("SELECT * FROM staff_hours WHERE status='in' AND employee_name=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$employee]);
  return $stmt->fetch();
}

try {
  if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    if ($status === 'open') {
      echo json_encode(['ok'=>true,'data'=>getOpenClock($pdo,$employeeName)]);
    } else {
      $stmt = $pdo->prepare("SELECT * FROM staff_hours ORDER BY clock_in DESC LIMIT 100");
      $stmt->execute();
      echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    exit;
  }

  if ($method === 'POST') {
    // Clock In
    // Close any lingering open record
    $pdo->prepare("UPDATE staff_hours SET status='out', clock_out=NOW(), total_minutes=TIMESTAMPDIFF(MINUTE, clock_in, NOW()) WHERE status='in' AND employee_name=?")->execute([$employeeName]);
    $shiftId = (int)($_POST['shift_id'] ?? 0);
    $stmt = $pdo->prepare("INSERT INTO staff_hours (employee_name, clock_in, shift_id) VALUES (?, NOW(), ?)");
    $stmt->execute([$employeeName, $shiftId ?: null]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);
    exit;
  }

  if ($method === 'PATCH') {
    // Clock Out
    $input = json_decode(file_get_contents('php://input'), true);
    $clockId = isset($input['id']) ? (int)$input['id'] : 0;
    $stmt = $pdo->prepare("SELECT * FROM staff_hours WHERE id=?");
    $stmt->execute([$clockId]);
    $rec = $stmt->fetch();
    if(!$rec){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Record not found']); exit; }
    if($rec['status']==='out'){ echo json_encode(['ok'=>false,'error'=>'Already clocked out']); exit; }
    $diffStmt = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, '{$rec['clock_in']}', NOW()) AS diff");
    $minutes = (int)$diffStmt->fetch()['diff'];
    $upd = $pdo->prepare("UPDATE staff_hours SET clock_out=NOW(), total_minutes=?, status='out' WHERE id=?");
    $upd->execute([$minutes, $clockId]);
    echo json_encode(['ok'=>true]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}