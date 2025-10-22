<?php
header('Content-Type: application/json');
require __DIR__ . '/api/db.php';

try {
    // Get shifts data
    $shifts_stmt = $pdo->query("SELECT * FROM shifts ORDER BY started_at DESC LIMIT 3");
    $shifts = $shifts_stmt->fetchAll();
    
    // Get transactions data
    $txn_stmt = $pdo->query("SELECT * FROM transactions ORDER BY date DESC LIMIT 10");
    $transactions = $txn_stmt->fetchAll();
    
    // Get counts
    $shift_count = $pdo->query("SELECT COUNT(*) as count FROM shifts")->fetch()['count'];
    $txn_count = $pdo->query("SELECT COUNT(*) as count FROM transactions")->fetch()['count'];
    $linked_txn_count = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE shift_id IS NOT NULL")->fetch()['count'];
    
    echo json_encode([
        'shifts_count' => $shift_count,
        'transactions_count' => $txn_count,
        'linked_transactions_count' => $linked_txn_count,
        'recent_shifts' => $shifts,
        'recent_transactions' => $transactions
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>