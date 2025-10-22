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
// Add columns for detailed sales breakdown if not present
try { $pdo->exec("ALTER TABLE shifts ADD COLUMN IF NOT EXISTS cash_sales DECIMAL(12,2) DEFAULT 0"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE shifts ADD COLUMN IF NOT EXISTS noncash_sales DECIMAL(12,2) DEFAULT 0"); } catch (Throwable $__) {}
try { $pdo->exec("ALTER TABLE shifts ADD COLUMN IF NOT EXISTS duration_minutes INT DEFAULT NULL"); } catch (Throwable $__) {}

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
      // If an open shift exists, augment with live sales so Operational Reports show running totals
      if ($data && ($data['status'] ?? '') === 'open') {
        $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method='Cash'");
        $cashStmt->execute([$data['id']]);
        $liveCash = (float)($cashStmt->fetch()['s'] ?? 0);
        $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method<>'Cash'");
        $nonCashStmt->execute([$data['id']]);
        $liveNonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
        // Fallback for legacy transactions without shift_id: use cashier/time window
        if (($liveCash + $liveNonCash) <= 0.00001) {
          $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method='Cash' AND date BETWEEN ? AND NOW()");
          $cashStmt->execute([$data['employee_name'], $data['started_at']]);
          $liveCash = (float)($cashStmt->fetch()['s'] ?? 0);
          $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method<>'Cash' AND date BETWEEN ? AND NOW()");
          $nonCashStmt->execute([$data['employee_name'], $data['started_at']]);
          $liveNonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
        }
        $data['cash_sales'] = $liveCash;
        $data['noncash_sales'] = $liveNonCash;
        $data['sales_total'] = $liveCash + $liveNonCash;
      }
      echo json_encode(['ok' => true, 'data' => $data]);
    } else {
      // Return recent shifts (limit 100)
      $stmt = $pdo->prepare("SELECT * FROM shifts ORDER BY started_at DESC LIMIT 100");
      $stmt->execute();
      $rows = $stmt->fetchAll();
      // For any open shifts, compute live sales so Operational Reports show non-zero Sales
      foreach ($rows as &$row) {
        if (($row['status'] ?? '') === 'open') {
          $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method='Cash'");
          $cashStmt->execute([$row['id']]);
          $liveCash = (float)($cashStmt->fetch()['s'] ?? 0);
          $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method<>'Cash'");
          $nonCashStmt->execute([$row['id']]);
          $liveNonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
          // Fallback for legacy transactions without shift_id
          if (($liveCash + $liveNonCash) <= 0.00001) {
            $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method='Cash' AND date BETWEEN ? AND NOW()");
            $cashStmt->execute([$row['employee_name'], $row['started_at']]);
            $liveCash = (float)($cashStmt->fetch()['s'] ?? 0);
            $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method<>'Cash' AND date BETWEEN ? AND NOW()");
            $nonCashStmt->execute([$row['employee_name'], $row['started_at']]);
            $liveNonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
          }
          $row['cash_sales'] = $liveCash;
          $row['noncash_sales'] = $liveNonCash;
          $row['sales_total'] = $liveCash + $liveNonCash;
        } else if (($row['status'] ?? '') === 'closed' && ((float)($row['sales_total'] ?? 0) === 0.0)) {
          // Auto-heal historical closed shifts missing sales_total: recompute via session linkage
          $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method='Cash'");
          $cashStmt->execute([$row['id']]);
          $cash = (float)($cashStmt->fetch()['s'] ?? 0);
          $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method<>'Cash'");
          $nonCashStmt->execute([$row['id']]);
          $nonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
          // Fallback for legacy transactions without shift_id
          if (($cash + $nonCash) <= 0.00001 && !empty($row['started_at'])) {
            $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method='Cash' AND date BETWEEN ? AND COALESCE(?, NOW())");
            $cashStmt->execute([$row['employee_name'], $row['started_at'], $row['ended_at']]);
            $cash = (float)($cashStmt->fetch()['s'] ?? 0);
            $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method<>'Cash' AND date BETWEEN ? AND COALESCE(?, NOW())");
            $nonCashStmt->execute([$row['employee_name'], $row['started_at'], $row['ended_at']]);
            $nonCash = (float)($nonCashStmt->fetch()['s'] ?? 0);
          }
          $sum = $cash + $nonCash;
          $expected = (float)($row['opening_cash'] ?? 0) + $cash;
          $recomputedClosing = (float)($row['opening_cash'] ?? 0) + $cash;
          $variance = isset($row['closing_cash']) && $row['closing_cash'] !== null ? ((float)$row['closing_cash'] - $expected) : null;
          // Persist backfill to DB; also correct closing_cash if it equals opening without sales
          $upd = $pdo->prepare("UPDATE shifts SET sales_total=?, cash_sales=?, noncash_sales=?, variance=COALESCE(?, variance), closing_cash=COALESCE(closing_cash, ?), ended_at=COALESCE(ended_at, NOW()) WHERE id=?");
          // If closing_cash equals opening but we have cash sales, set closing_cash to opening+cash
          $closingToPersist = ((float)($row['closing_cash'] ?? 0) === (float)($row['opening_cash'] ?? 0) && $cash > 0) ? $recomputedClosing : ($row['closing_cash'] ?? null);
          $upd->execute([$sum, $cash, $nonCash, $variance, $closingToPersist, $row['id']]);
          // Reflect in response
          $row['sales_total'] = $sum;
          $row['cash_sales'] = $cash;
          $row['noncash_sales'] = $nonCash;
          if ($variance !== null) { $row['variance'] = $variance; }
          if ($closingToPersist !== null) { $row['closing_cash'] = $closingToPersist; }
        }
      }
      unset($row);
      echo json_encode(['ok' => true, 'data' => $rows]);
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
    // End shift (automated closing cash; no manual entry required)
    $input = json_decode(file_get_contents('php://input'), true);
    $shiftId = isset($input['id']) ? (int)$input['id'] : 0;

    // Fetch shift
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id=?");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch();
    if (!$shift) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Shift not found']); exit; }
    if ($shift['status'] === 'closed') { echo json_encode(['ok'=>false,'error'=>'Shift already closed']); exit; }

    // Calculate sales totals during shift by session linkage (voided sales are removed from transactions)
    $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method='Cash'");
    $cashStmt->execute([$shift['id']]);
    $cashSales = (float)$cashStmt->fetch()['s'];
    $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE shift_id=? AND status='completed' AND payment_method<>'Cash'");
    $nonCashStmt->execute([$shift['id']]);
    $nonCashSales = (float)$nonCashStmt->fetch()['s'];
    // Fallback for legacy transactions without shift_id
    if (($cashSales + $nonCashSales) <= 0.00001) {
      $cashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method='Cash' AND date BETWEEN ? AND NOW()");
      $cashStmt->execute([$shift['employee_name'], $shift['started_at']]);
      $cashSales = (float)$cashStmt->fetch()['s'];
      $nonCashStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS s FROM transactions WHERE cashier=? AND status='completed' AND payment_method<>'Cash' AND date BETWEEN ? AND NOW()");
      $nonCashStmt->execute([$shift['employee_name'], $shift['started_at']]);
      $nonCashSales = (float)$nonCashStmt->fetch()['s'];
    }
    $salesTotal = $cashSales + $nonCashSales;

    // Auto closing cash: opening cash + net cash sales (returns/payouts not separately modeled)
    $expectedDrawer = (float)$shift['opening_cash'] + $cashSales;
    $closingCash = $expectedDrawer; // fully automated
    $variance = $closingCash - $expectedDrawer; // 0 with automation

    // Duration
    $durationMinutesStmt = $pdo->query("SELECT TIMESTAMPDIFF(MINUTE, '{$shift['started_at']}', NOW()) AS diff");
    $duration = (int)$durationMinutesStmt->fetch()['diff'];

    $upd = $pdo->prepare("UPDATE shifts SET closing_cash=?, sales_total=?, variance=?, ended_at=NOW(), duration_minutes=?, status='closed', cash_sales=?, noncash_sales=? WHERE id=?");
    $upd->execute([$closingCash, $salesTotal, $variance, $duration, $cashSales, $nonCashSales, $shiftId]);

    echo json_encode(['ok'=>true, 'id'=>$shiftId, 'closing_cash'=>$closingCash, 'sales_total'=>$salesTotal, 'cash_sales'=>$cashSales, 'noncash_sales'=>$nonCashSales, 'variance'=>$variance, 'duration_minutes'=>$duration]);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method Not Allowed']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}