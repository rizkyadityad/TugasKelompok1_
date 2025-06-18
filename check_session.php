<?php
// session_check.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.html');
        exit;
    }
}

function getUserData() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email']
        ];
    }
    return null;
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

// Redirect to login if accessing protected page without login
function checkAuth() {
    if (!isLoggedIn()) {
        // Allow access to login page and auth handler
        $allowed_pages = ['login.html', 'auth.php'];
        $current_page = basename($_SERVER['PHP_SELF']);
        
        if (!in_array($current_page, $allowed_pages)) {
            header('Location: login.html');
            exit;
        }
    }
}
?>