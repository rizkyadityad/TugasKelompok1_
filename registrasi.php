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
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm-password'] ?? '';

// Validasi input
$errors = [];

if (empty($name)) {
    $errors[] = 'Nama harus diisi';
}

if (empty($email)) {
    $errors[] = 'Email harus diisi';
} elseif (!isValidEmail($email)) {
    $errors[] = 'Format email tidak valid';
}

if (empty($phone)) {
    $errors[] = 'Nomor telepon harus diisi';
} elseif (strlen($phone) < 10) {
    $errors[] = 'Nomor telepon minimal 10 digit';
}

if (empty($password)) {
    $errors[] = 'Password harus diisi';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password minimal 6 karakter';
}

if (empty($confirmPassword)) {
    $errors[] = 'Konfirmasi password harus diisi';
} elseif ($password !== $confirmPassword) {
    $errors[] = 'Password tidak cocok';
}

// Jika ada error, return error
if (!empty($errors)) {
    sendResponse(false, implode(', ', $errors));
}

// Cek apakah email sudah terdaftar
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    sendResponse(false, 'Email sudah terdaftar');
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user baru ke database
$insertStmt = $conn->prepare("INSERT INTO users (name, email, phone, password, created_at, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
$insertStmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);

if ($insertStmt->execute()) {
    $userId = $conn->insert_id;
    
    sendResponse(true, 'Registrasi berhasil! Silakan login dengan akun baru Anda.', [
        'user_id' => $userId,
        'redirect' => 'login.html'
    ]);
} else {
    sendResponse(false, 'Terjadi kesalahan saat mendaftar: ' . $conn->error);
}

// Tutup koneksi
$conn->close();
?>