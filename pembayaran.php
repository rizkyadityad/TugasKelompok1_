<?php
require_once 'koneksi.php';

// Set header untuk API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Fungsi untuk generate ID unik
function generateUniqueId($prefix = '') {
    return $prefix . date('YmdHis') . rand(1000, 9999);
}

// Fungsi untuk generate payment code
function generatePaymentCode($method) {
    switch($method) {
        case 'E-Wallet':
            return 'EW' . date('Ymd') . rand(100000, 999999);
        case 'transfer':
            return 'TF' . date('Ymd') . rand(100000, 999999);
        case 'QRIS':
            return 'QR' . date('Ymd') . rand(100000, 999999);
        default:
            return 'PY' . date('Ymd') . rand(100000, 999999);
    }
}

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/payment_api.php', '', $path);

try {
    switch($method . ':' . $path) {
        case 'POST:/create_order':
            createOrder();
            break;
        case 'POST:/create_payment':
            createPayment();
            break;
        case 'POST:/confirm_payment':
            confirmPayment();
            break;
        case 'POST:/cancel_payment':
            cancelPayment();
            break;
        case 'GET:/get_payment':
            getPayment();
            break;
        case 'GET:/get_order':
            getOrder();
            break;
        case 'GET:/get_order_history':
            getOrderHistory();
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// Fungsi untuk membuat pesanan baru
function createOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    // Validasi input
    $required_fields = ['customerName', 'customerPhone', 'customerAddress', 'items', 'totalAmount', 'deliveryTime'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field $field is required"]);
            return;
        }
    }
    
    $conn->begin_transaction();
    
    try {
        // Generate order ID
        $orderId = generateUniqueId('ORD');
        
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (order_id, customer_name, customer_phone, customer_address, order_date, delivery_time, status, total_amount, notes) VALUES (?, ?, ?, ?, NOW(), ?, 'pending', ?, ?)");
        
        $deliveryTime = date('Y-m-d H:i:s', strtotime($input['deliveryTime']));
        $notes = isset($input['notes']) ? $input['notes'] : '';
        
        $stmt->bind_param("sssssds", 
            $orderId, 
            $input['customerName'], 
            $input['customerPhone'], 
            $input['customerAddress'], 
            $deliveryTime, 
            $input['totalAmount'], 
            $notes
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create order');
        }
        
        // Insert order items
        $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, product_name, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($input['items'] as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $stmt_items->bind_param("ssidd", 
                $orderId, 
                $item['name'], 
                $item['quantity'], 
                $item['price'], 
                $subtotal
            );
            
            if (!$stmt_items->execute()) {
                throw new Exception('Failed to add order item: ' . $item['name']);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'orderId' => $orderId,
            'message' => 'Order created successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk membuat pembayaran
function createPayment() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        return;
    }
    
    // Validasi input
    $required_fields = ['orderId', 'paymentMethod', 'amount'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field $field is required"]);
            return;
        }
    }
    
    $conn->begin_transaction();
    
    try {
        // Cek apakah order exists
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $input['orderId']);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Generate payment ID dan code
        $paymentId = generateUniqueId('PAY');
        $paymentCode = generatePaymentCode($input['paymentMethod']);
        
        // Set expiry time (24 jam dari sekarang)
        $expiryTime = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert payment
        $stmt = $conn->prepare("INSERT INTO payments (payment_id, order_id, payment_method, payment_code, amount, status, expiry_time, bank_name, account_number, account_name) VALUES (?, ?, ?, ?, ?, 'waiting_confirmation', ?, ?, ?, ?)");
        
        $bankName = isset($input['bankName']) ? $input['bankName'] : 'Bank Wedang Indonesia';
        $accountNumber = isset($input['accountNumber']) ? $input['accountNumber'] : '1234-5678-9012-3456';
        $accountName = isset($input['accountName']) ? $input['accountName'] : 'Wedang Ronde Official';
        
        $stmt->bind_param("ssssdsss", 
            $paymentId, 
            $input['orderId'], 
            $input['paymentMethod'], 
            $paymentCode, 
            $input['amount'], 
            $expiryTime,
            $bankName,
            $accountNumber,
            $accountName
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create payment');
        }
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'waiting_payment' WHERE order_id = ?");
        $stmt->bind_param("s", $input['orderId']);
        $stmt->execute();
        
        // Insert payment history
        $stmt = $conn->prepare("INSERT INTO payment_history (payment_id, status, notes) VALUES (?, 'waiting_confirmation', 'Payment created')");
        $stmt->bind_param("s", $paymentId);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'paymentId' => $paymentId,
            'paymentCode' => $paymentCode,
            'expiryTime' => $expiryTime,
            'bankName' => $bankName,
            'accountNumber' => $accountNumber,
            'accountName' => $accountName,
            'message' => 'Payment created successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk konfirmasi pembayaran
function confirmPayment() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['paymentId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get payment info
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ?");
        $stmt->bind_param("s", $input['paymentId']);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] === 'confirmed') {
            throw new Exception('Payment already confirmed');
        }
        
        if ($payment['status'] === 'expired' || $payment['status'] === 'cancelled') {
            throw new Exception('Payment is ' . $payment['status']);
        }
        
        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = 'confirmed', confirmation_date = NOW(), payment_date = NOW() WHERE payment_id = ?");
        $stmt->bind_param("s", $input['paymentId']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to confirm payment');
        }
        
        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status = 'paid' WHERE order_id = ?");
        $stmt->bind_param("s", $payment['order_id']);
        $stmt->execute();
        
        // Insert payment history
        $stmt = $conn->prepare("INSERT INTO payment_history (payment_id, status, notes) VALUES (?, 'confirmed', 'Payment confirmed by customer')");
        $stmt->bind_param("s", $input['paymentId']);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment confirmed successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk membatalkan pembayaran
function cancelPayment() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['paymentId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID is required']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get payment info
        $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ?");
        $stmt->bind_param("s", $input['paymentId']);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        $reason = isset($input['reason']) ? $input['reason'] : 'cancelled';
        $status = ($reason === 'expired') ? 'expired' : 'cancelled';
        
        // Update payment status
        $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE payment_id = ?");
        $stmt->bind_param("ss", $status, $input['paymentId']);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to cancel payment');
        }
        
        // Update order status
        $orderStatus = ($reason === 'expired') ? 'cancelled' : 'cancelled';
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("ss", $orderStatus, $payment['order_id']);
        $stmt->execute();
        
        // Insert payment history
        $notes = ($reason === 'expired') ? 'Payment expired' : 'Payment cancelled by user';
        $stmt = $conn->prepare("INSERT INTO payment_history (payment_id, status, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $input['paymentId'], $status, $notes);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment ' . $status . ' successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk mendapatkan data pembayaran
function getPayment() {
    global $conn;
    
    $paymentId = isset($_GET['paymentId']) ? $_GET['paymentId'] : null;
    $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : null;
    
    if (!$paymentId && !$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID or Order ID is required']);
        return;
    }
    
    try {
        if ($paymentId) {
            $stmt = $conn->prepare("SELECT p.*, o.customer_name, o.customer_phone, o.customer_address, o.order_date, o.delivery_time FROM payments p JOIN orders o ON p.order_id = o.order_id WHERE p.payment_id = ?");
            $stmt->bind_param("s", $paymentId);
        } else {
            $stmt = $conn->prepare("SELECT p.*, o.customer_name, o.customer_phone, o.customer_address, o.order_date, o.delivery_time FROM payments p JOIN orders o ON p.order_id = o.order_id WHERE p.order_id = ?");
            $stmt->bind_param("s", $orderId);
        }
        
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        
        if (!$payment) {
            http_response_code(404);
            echo json_encode(['error' => 'Payment not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'payment' => $payment
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk mendapatkan data pesanan
function getOrder() {
    global $conn;
    
    $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : null;
    
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID is required']);
        return;
    }
    
    try {
        // Get order info
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            return;
        }
        
        // Get order items
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $order['items'] = $items;
        
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Fungsi untuk mendapatkan riwayat pesanan
function getOrderHistory() {
    global $conn;
    
    $customerPhone = isset($_GET['customerPhone']) ? $_GET['customerPhone'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    try {
        if ($customerPhone) {
            $stmt = $conn->prepare("SELECT o.*, p.payment_method, p.status as payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id WHERE o.customer_phone = ? ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("sii", $customerPhone, $limit, $offset);
        } else {
            $stmt = $conn->prepare("SELECT o.*, p.payment_method, p.status as payment_status FROM orders o LEFT JOIN payments p ON o.order_id = p.order_id ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
            $stmt->bind_param("ii", $limit, $offset);
        }
        
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

$conn->close();
?>