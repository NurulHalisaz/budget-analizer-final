<?php
include 'db_connect.php'; 

// 1. Tabel USERS (untuk Login/Registrasi)
$sql_users = "
CREATE TABLE IF NOT EXISTS Users (
    user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// 2. Tabel CATEGORIES (untuk Pengaturan)
$sql_categories = "
CREATE TABLE IF NOT EXISTS Categories (
    category_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11),
    name VARCHAR(100) NOT NULL,
    parent_allocation ENUM('needs', 'wants') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);";

// 3. Tabel BUDGET_TARGET (untuk Dashboard Setup)
$sql_budget = "
CREATE TABLE IF NOT EXISTS Budget_Target (
    budget_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    month_key VARCHAR(6) NOT NULL, -- Format YYYYMM (misal: 202512)
    income DECIMAL(10, 2) NOT NULL,
    needs_nominal DECIMAL(10, 2) NOT NULL,
    wants_nominal DECIMAL(10, 2) NOT NULL,
    savings_nominal DECIMAL(10, 2) NOT NULL,
    UNIQUE KEY user_month_unique (user_id, month_key),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);";

// 4. Tabel TRANSACTIONS (untuk Transaksi)
$sql_transactions = "
CREATE TABLE IF NOT EXISTS Transactions (
    txn_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    date DATE NOT NULL,
    nominal DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255),
    allocation ENUM('needs', 'wants', 'savings') NOT NULL,
    subcategory VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);";


// eksekusi semua query
$queries = [
    'Users' => $sql_users,
    'Categories' => $sql_categories,
    'Budget_Target' => $sql_budget,
    'Transactions' => $sql_transactions
];

echo "<h2>Status Pembuatan Tabel:</h2>";

foreach ($queries as $tableName => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p>Tabel **$tableName** berhasil dibuat atau sudah ada.</p>";
    } else {
        echo "<p>Error saat membuat tabel $tableName: " . $conn->error . "</p>";
    }
}

$conn->close();
?>