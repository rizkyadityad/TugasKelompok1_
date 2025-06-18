<?php
session_start();
require_once 'auth.php';

// Logout user
logout();

// Redirect ke halaman login
header('Location: login.html');
exit();
?>