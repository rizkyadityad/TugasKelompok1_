<?php
require_once 'koneksi.php'; // pastikan file koneksi ini sesuai

// Ambil data JSON dari frontend
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data kosong"]);
    exit;
}

// Ambil data utama
$transaction_code  = $data['id'];
$customer_name     = $data['customer']['name'];
$customer_phone    = $data['customer']['phone'];
$customer_email    = $data['customer']['email'] ?? null;
$customer_address  = $data['customer']['address'];
$payment_method    = $data['paymentMethod'];
$subtotal          = $data['subtotal'];
$tax               = $data['tax'];
$shipping          = $data['shipping'];
$total             = $data['total'];
$notes             = $data['customer']['notes'] ?? null;
$created_at        = date("Y-m-d H:i:s", strtotime($data['timestamp']));

// Cek duplikat
$cek = $conn->prepare("SELECT id FROM transactions WHERE transaction_code = ?");
$cek->bind_param("s", $transaction_code);
$cek->execute();
$cek->store_result();

if ($cek->num_rows > 0) {
    echo json_encode(["status" => "duplicate", "message" => "Transaksi sudah ada"]);
    exit;
}

// Simpan ke tabel transactions
$stmt = $conn->prepare("INSERT INTO transactions (
    transaction_code, customer_name, customer_phone, customer_email, customer_address,
    subtotal, tax, shipping, total, payment_method, status, notes, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)");

$stmt->bind_param("ssssssdddsss", 
    $transaction_code, $customer_name, $customer_phone, $customer_email, $customer_address,
    $subtotal, $tax, $shipping, $total, $payment_method, $notes, $created_at
);

if ($stmt->execute()) {
    // Masukkan item satu per satu ke tabel transaction_items
    $item_stmt = $conn->prepare("INSERT INTO transaction_items (
        transaction_code, item_name, price, quantity, total_price
    ) VALUES (?, ?, ?, ?, ?)");

    foreach ($data['items'] as $item) {
        $item_name   = $item['name'];
        $price       = $item['price'];
        $quantity    = $item['quantity'];
        $total_price = $price * $quantity;

        $item_stmt->bind_param("ssdii", 
            $transaction_code, $item_name, $price, $quantity, $total_price
        );
        $item_stmt->execute();
    }

    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $stmt->error]);
}
?>
