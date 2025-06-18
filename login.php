<?php
session_start();
require_once 'koneksi.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Fungsi untuk mengirim response JSON
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Fungsi untuk validasi email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Cek method request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method tidak diizinkan');
}

// Ambil data dari POST
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input
if (empty($email) || empty($password)) {
    sendResponse(false, 'Email dan password harus diisi');
}

if (!isValidEmail($email)) {
    sendResponse(false, 'Format email tidak valid');
}

// Query untuk mencari user
$stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ? AND status = 'active'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse(false, 'Email atau password salah');
}

$user = $result->fetch_assoc();

// Verifikasi password
if (!password_verify($password, $user['password'])) {
    sendResponse(false, 'Email atau password salah');
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['logged_in'] = true;

// Update last login
$updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$updateStmt->bind_param("i", $user['id']);
$updateStmt->execute();

sendResponse(true, 'Login berhasil', [
    'redirect' => 'index.html',
    'user' => [
        'name' => $user['name'],
        'email' => $user['email']
    ]
]);

// Tutup koneksi
$conn->close();
?>