<?php
// auth.php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'reset_email':
        handleResetEmail();
        break;
    case 'verify_code':
        handleVerifyCode();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function handleLogin() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validasi input
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email dan password harus diisi']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        return;
    }
    
    try {
        // Cek user di database
        $stmt = $pdo->prepare("SELECT id, nama, email, password, phone FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nama'];
            $_SESSION['user_email'] = $user['email'];
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login berhasil',
                'redirect' => 'index.html'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Email atau password salah']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
}

function handleRegister() {
    global $pdo;
    
    $nama = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validasi input
    if (empty($nama) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
        return;
    }
    
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Password tidak cocok']);
        return;
    }
    
    try {
        // Cek apakah email sudah terdaftar
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar']);
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user baru
        $stmt = $pdo->prepare("INSERT INTO users (nama, email, phone, password, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([$nama, $email, $phone, $hashedPassword]);
        
        echo json_encode(['success' => true, 'message' => 'Registrasi berhasil! Silakan login dengan akun baru Anda.']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
}

function handleResetEmail() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        return;
    }
    
    try {
        // Cek apakah email terdaftar
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email tidak terdaftar']);
            return;
        }
        
        // Generate verification code
        $verificationCode = sprintf("%06d", mt_rand(100000, 999999));
        
        // Simpan kode verifikasi ke database
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, verification_code, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW()) ON DUPLICATE KEY UPDATE verification_code = ?, expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE), created_at = NOW()");
        $stmt->execute([$email, $verificationCode, $verificationCode]);
        
        // Simpan di session untuk verifikasi
        $_SESSION['reset_email'] = $email;
        $_SESSION['verification_code'] = $verificationCode;
        
        // Dalam implementasi nyata, kirim email di sini
        // sendResetEmail($email, $verificationCode);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Kode verifikasi telah dikirim ke email Anda',
            'debug_code' => $verificationCode // Hapus ini di production
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
}

function handleVerifyCode() {
    $code = trim($_POST['code'] ?? '');
    $sessionCode = $_SESSION['verification_code'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Kode verifikasi harus diisi']);
        return;
    }
    
    if ($code !== $sessionCode) {
        echo json_encode(['success' => false, 'message' => 'Kode verifikasi tidak valid']);
        return;
    }
    
    // Verifikasi berhasil
    $_SESSION['code_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Kode verifikasi valid']);
}

function handleResetPassword() {
    global $pdo;
    
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    $codeVerified = $_SESSION['code_verified'] ?? false;
    
    if (!$codeVerified) {
        echo json_encode(['success' => false, 'message' => 'Verifikasi kode diperlukan']);
        return;
    }
    
    if (empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password baru harus diisi']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Password tidak cocok']);
        return;
    }
    
    try {
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        
        // Hapus data reset password
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        // Clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['verification_code']);
        unset($_SESSION['code_verified']);
        
        echo json_encode(['success' => true, 'message' => 'Password berhasil diubah']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem']);
    }
}
?>