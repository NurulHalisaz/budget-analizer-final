<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika ada sesi aktif, langsung arahkan ke Dashboard 
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Sertakan koneksi database
include 'db_connect.php'; 

$action = $_POST['action'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';

$response = ['success' => false, 'message' => ''];

// Fungsi untuk mendapatkan hash password yang aman
function get_secure_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Hanya proses jika ada aksi yang dikirim dari JavaScript
if ($action) {
    
    // Matikan error reporting agar tidak bocor ke frontend saat AJAX
    error_reporting(0);
    // registrasi
    if ($action === 'register') {
        if (empty($username) || empty($password)) {
            $response['message'] = 'Username dan password harus diisi.';
        } else {
            // Cek duplikasi username
            $stmt = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $response['message'] = 'Username sudah terdaftar!';
            } else {
                $passwordHash = get_secure_password($password);
                $stmt_insert = $conn->prepare("INSERT INTO Users (username, password_hash) VALUES (?, ?)");
                $stmt_insert->bind_param("ss", $username, $passwordHash);

                if ($stmt_insert->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Registrasi berhasil! Silakan login.';
                } else {
                    $response['message'] = 'Gagal menyimpan data ke database: ' . $conn->error;
                }
                $stmt_insert->close();
            }
            $stmt->close();
        }
    } 
    // login
    elseif ($action === 'login') {
        if (empty($username) || empty($password)) {
            $response['message'] = 'Username dan password harus diisi.';
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM Users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($user_id, $db_username, $db_password_hash);
            $stmt->fetch();

            if ($db_password_hash && password_verify($password, $db_password_hash)) {
                // SET SESI PHP
                $_SESSION['user_id'] = $user_id; 
                $_SESSION['username'] = $db_username;
                
                // Login Berhasil!
                $response['success'] = true;
                $response['user_id'] = $user_id; 
                $response['username'] = $db_username; 
            } else {
                $response['message'] = 'Login Gagal. Username atau Password salah.';
            }
            $stmt->close();
        }
    }
    // lupa pass
    elseif ($action === 'reset_check') {
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Username ditemukan.';
        } else {
            $response['message'] = 'Username tidak ditemukan.';
        }
        $stmt->close();

    } elseif ($action === 'reset_update') {
        if (empty($username) || empty($newPassword) || strlen($newPassword) < 4) {
            $response['message'] = 'Kata sandi baru minimal harus 4 karakter.';
        } else {
            $passwordHash = get_secure_password($newPassword);
            $stmt = $conn->prepare("UPDATE Users SET password_hash = ? WHERE username = ?");
            $stmt->bind_param("ss", $passwordHash, $username);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Kata sandi berhasil diatur ulang.';
            } else {
                $response['message'] = 'Gagal memperbarui kata sandi.';
            }
            $stmt->close();
        }
    }
    $conn->close();
    echo json_encode($response);
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Registrasi - Budget Analyzer</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h1>Budget Analizer</h1>
            
            <div class="tab-header">
                <button id="tab-login" class="tab-button active" onclick="showForm('login')">Login</button>
                <button id="tab-register" class="tab-button" onclick="showForm('register')">Registrasi</button>
            </div>
            
            <form id="login-form" class="auth-form active-form" method="POST">
                <div class="input-group">
                    <input type="text" id="login-username" name="username" placeholder="Masukkan Username" required autofocus>
                </div>
                <div class="input-group">
                    <input type="password" id="login-password" name="password" placeholder="Masukkan Password" required>
                </div>
                
                <div class="form-options">
                    <a href="#" onclick="showResetForm(event)">Lupa password?</a>
                </div>

                <button type="submit" class="gradient-btn">LOGIN</button>
                
                <p class="belum-punya-akun">
                    Belum Punya Akun? <a href="#" onclick="showForm('register')">Daftar Sekarang</a>
                </p>
            </form>
            
            <form id="register-form" class="auth-form" method="POST">
                <div class="input-group">
                    <input type="text" id="reg-username" name="username" placeholder="Pilih Username" required>
                </div>
                <div class="input-group">
                    <input type="password" id="reg-password" name="password" placeholder="Buat Password" required>
                </div>
                 <div class="input-group">
                    <input type="password" id="reg-confirm-password" name="confirm_password" placeholder="Konfirmasi Password" required>
                </div>
                
                <button type="submit" class="gradient-btn">DAFTAR</button>

                <p class="belum-punya-akun">
                    Sudah Punya Akun? <a href="#" onclick="showForm('login')">Login Sekarang</a>
                </p>
            </form>
        </div>
    </div>
    
    <script src="js/login.js"></script>
</body>
</html>