<?php
// Konfigurasi Database
$servername = "localhost";
$username_db = "root"; 
$password_db = ""; 
$dbname = "budget_analyzer_db"; 

// Membuat koneksi
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>