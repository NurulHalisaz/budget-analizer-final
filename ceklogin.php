<?php
// Pastikan sesi dimulai di awal setiap request
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Cek apakah user_id ada di sesi
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Jika tidak ada sesi, redirect ke halaman login
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User'; 
?>