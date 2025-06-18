<?php
// test_transaction.php - File untuk test koneksi dan class Transaction
require_once 'koneksi.php';
require_once 'Transaction.php';

// Test koneksi database
echo "<h2>Testing Database Connection</h2>";
try {
    $transaction = new Transaction($db);
    $connectionTest = $transaction->testConnection();
    
    if ($connectionTest['success']) {
        echo "<p style='color: green;'>✓ " . $connectionTest['message'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ " . $connectionTest['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test create transaction
echo "<h2>Testing Create Transaction</h2>";
try {
    $testData = [
        'customer_id' => 1, // Pastikan customer dengan ID 1 ada di database
        'subtotal' => 50000,
        'tax' => 5000,
        'shipping' => 10000,
        'total' => 65000,
        'payment_method' => 'cash',
        'status' => 'pending',
        'notes' => 'Test transaction from PHP',
        'items' => [
            [
                'product_id' => 1,
                'name' => 'Wedang Ronde Original',
                'price' => 15000,
                'quantity' => 2
            ],
            [
                'product_id' => 2,
                'name' => 'Wedang Jahe',
                'price' => 10000,
                'quantity' => 2
            ]
        ]
    ];
    
    $result = $transaction->create($testData);
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ " . $result['message'] . "</p>";
        echo "<p>Transaction ID: " . $result['transaction_id'] . "</p>";
        echo "<p>Transaction Code: " . $result['transaction_code'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ " . $result['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error creating transaction: " . $e->getMessage() . "</p>";
}

// Test get all transactions
echo "<h2>Testing Get All Transactions</h2>";
try {
    $result = $transaction->getAll(['limit' => 5]);
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ Retrieved " . count($result['data']) . " transactions</p>";
        
        if (!empty($result['data'])) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Code</th><th>Customer</th><th>Total</th><th>Status</th><th>Created</th></tr>";
            
            foreach ($result['data'] as $trans) {
                echo "<tr>";
                echo "<td>" . $trans['id'] . "</td>";
                echo "<td>" . $trans['transaction_code'] . "</td>";
                echo "<td>" . ($trans['customer_name'] ?? 'N/A') . "</td>";
                echo "<td>Rp " . number_format($trans['total'], 0, ',', '.') . "</td>";
                echo "<td>" . $trans['status'] . "</td>";
                echo "<td>" . $trans['created_at'] . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>✗ " . $result['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error getting transactions: " . $e->getMessage() . "</p>";
}

// Test get statistics
echo "<h2>Testing Get Statistics</h2>";
try {
    $result = $transaction->getStatistics();
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ Statistics retrieved successfully</p>";
        echo "<ul>";
        echo "<li>Total Transactions: " . $result['data']['total_transactions'] . "</li>";
        echo "<li>Total Revenue: Rp " . number_format($result['data']['total_revenue'], 0, ',', '.') . "</li>";
        echo "<li>Today's Transactions: " . $result['data']['today_transactions'] . "</li>";
        echo "<li>Average Order Value: Rp " . number_format($result['data']['avg_order_value'], 0, ',', '.') . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ " . $result['message'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error getting statistics: " . $e->getMessage() . "</p>";
}
?>