<?php
// Debug script for Z-Reports data issue
header('Content-Type: text/plain');
require __DIR__ . '/api/db.php';

echo "=== Z-REPORTS DEBUG SCRIPT ===\n\n";

try {
    // 1. Check database schema
    echo "1. CHECKING DATABASE SCHEMA:\n";
    echo "----------------------------\n";
    
    // Check shifts table structure
    $stmt = $pdo->query("DESCRIBE shifts");
    $shifts_columns = $stmt->fetchAll();
    echo "SHIFTS TABLE COLUMNS:\n";
    foreach ($shifts_columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
    
    echo "\n";
    
    // Check transactions table structure
    $stmt = $pdo->query("DESCRIBE transactions");
    $transactions_columns = $stmt->fetchAll();
    echo "TRANSACTIONS TABLE COLUMNS:\n";
    foreach ($transactions_columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Key']} {$col['Default']}\n";
    }
    
    echo "\n\n";
    
    // 2. Check data counts
    echo "2. CHECKING DATA COUNTS:\n";
    echo "------------------------\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM shifts");
    $shifts_count = $stmt->fetch()['count'];
    echo "Total shifts: {$shifts_count}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions");
    $transactions_count = $stmt->fetch()['count'];
    echo "Total transactions: {$transactions_count}\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE shift_id IS NOT NULL");
    $linked_transactions = $stmt->fetch()['count'];
    echo "Transactions with shift_id: {$linked_transactions}\n";
    
    echo "\n\n";
    
    // 3. Show recent shifts
    echo "3. RECENT SHIFTS DATA:\n";
    echo "----------------------\n";
    
    $stmt = $pdo->query("SELECT * FROM shifts ORDER BY started_at DESC LIMIT 5");
    $recent_shifts = $stmt->fetchAll();
    
    if (empty($recent_shifts)) {
        echo "No shifts found!\n";
    } else {
        foreach ($recent_shifts as $shift) {
            echo "Shift ID: {$shift['id']}\n";
            echo "  Employee: {$shift['employee_name']}\n";
            echo "  Started: {$shift['started_at']}\n";
            echo "  Ended: " . ($shift['ended_at'] ?? 'Still open') . "\n";
            echo "  Status: {$shift['status']}\n";
            echo "  Opening Cash: ₱{$shift['opening_cash']}\n";
            echo "  Closing Cash: " . ($shift['closing_cash'] ?? 'N/A') . "\n";
            echo "  Sales Total: ₱{$shift['sales_total']}\n";
            echo "  Variance: ₱{$shift['variance']}\n";
            echo "  ---\n";
        }
    }
    
    echo "\n\n";
    
    // 4. Show recent transactions
    echo "4. RECENT TRANSACTIONS DATA:\n";
    echo "----------------------------\n";
    
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY date DESC LIMIT 5");
    $recent_transactions = $stmt->fetchAll();
    
    if (empty($recent_transactions)) {
        echo "No transactions found!\n";
    } else {
        foreach ($recent_transactions as $txn) {
            echo "Transaction ID: {$txn['id']}\n";
            echo "  Ref: {$txn['ref']}\n";
            echo "  Date: {$txn['date']}\n";
            echo "  Cashier: {$txn['cashier']}\n";
            echo "  Payment Method: {$txn['payment_method']}\n";
            echo "  Total: ₱{$txn['total']}\n";
            echo "  Status: " . ($txn['status'] ?? 'completed') . "\n";
            echo "  Shift ID: " . ($txn['shift_id'] ?? 'NULL') . "\n";
            echo "  ---\n";
        }
    }
    
    echo "\n\n";
    
    // 5. Test shift-transaction linking
    echo "5. TESTING SHIFT-TRANSACTION LINKING:\n";
    echo "-------------------------------------\n";
    
    if (!empty($recent_shifts)) {
        $test_shift = $recent_shifts[0];
        echo "Testing with Shift ID: {$test_shift['id']}\n";
        echo "Shift Employee: {$test_shift['employee_name']}\n";
        echo "Shift Period: {$test_shift['started_at']} to " . ($test_shift['ended_at'] ?? 'NOW') . "\n\n";
        
        // Method 1: Direct shift_id linking
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM transactions WHERE shift_id = ? AND (status IS NULL OR status = 'completed')");
        $stmt->execute([$test_shift['id']]);
        $direct_link = $stmt->fetch();
        echo "Method 1 (Direct shift_id link): {$direct_link['count']} transactions, ₱{$direct_link['total']} total\n";
        
        // Method 2: Time window + cashier matching (fallback)
        $end_time = $test_shift['ended_at'] ?? date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM transactions WHERE cashier = ? AND date BETWEEN ? AND ? AND (status IS NULL OR status = 'completed')");
        $stmt->execute([$test_shift['employee_name'], $test_shift['started_at'], $end_time]);
        $time_window = $stmt->fetch();
        echo "Method 2 (Time window + cashier): {$time_window['count']} transactions, ₱{$time_window['total']} total\n";
        
        // Method 3: Combined approach (what the code actually uses)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
            FROM transactions 
            WHERE (status IS NULL OR status = 'completed') 
            AND (
                (shift_id = ?) 
                OR (shift_id IS NULL AND cashier = ? AND date BETWEEN ? AND ?)
            )
        ");
        $stmt->execute([$test_shift['id'], $test_shift['employee_name'], $test_shift['started_at'], $end_time]);
        $combined = $stmt->fetch();
        echo "Method 3 (Combined approach): {$combined['count']} transactions, ₱{$combined['total']} total\n";
        
        // Cash vs Non-cash breakdown
        echo "\nPayment method breakdown for this shift:\n";
        $stmt = $pdo->prepare("
            SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total), 0) as total 
            FROM transactions 
            WHERE (status IS NULL OR status = 'completed') 
            AND (
                (shift_id = ?) 
                OR (shift_id IS NULL AND cashier = ? AND date BETWEEN ? AND ?)
            )
            GROUP BY payment_method
        ");
        $stmt->execute([$test_shift['id'], $test_shift['employee_name'], $test_shift['started_at'], $end_time]);
        $payment_breakdown = $stmt->fetchAll();
        
        if (empty($payment_breakdown)) {
            echo "  No transactions found for payment method breakdown\n";
        } else {
            foreach ($payment_breakdown as $payment) {
                echo "  {$payment['payment_method']}: {$payment['count']} transactions, ₱{$payment['total']} total\n";
            }
        }
    }
    
    echo "\n\n";
    
    // 6. Check for common issues
    echo "6. CHECKING FOR COMMON ISSUES:\n";
    echo "------------------------------\n";
    
    // Check for transactions without shift_id
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE shift_id IS NULL");
    $unlinked_count = $stmt->fetch()['count'];
    echo "Transactions without shift_id: {$unlinked_count}\n";
    
    // Check for voided/refunded transactions
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
    $status_breakdown = $stmt->fetchAll();
    echo "Transaction status breakdown:\n";
    foreach ($status_breakdown as $status) {
        $status_name = $status['status'] ?? 'NULL (treated as completed)';
        echo "  {$status_name}: {$status['count']}\n";
    }
    
    // Check for open shifts
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM shifts WHERE status = 'open'");
    $open_shifts = $stmt->fetch()['count'];
    echo "Open shifts: {$open_shifts}\n";
    
    echo "\n=== DEBUG COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>