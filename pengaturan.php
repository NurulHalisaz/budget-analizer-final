<?php
include 'db_connect.php'; 
include 'ceklogin.php'; 

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

$currentUserId = $user_id; 
$currentUsername = $username; 

//Mengambil Kategori dari DB 
function getCategoriesFromDB($conn, $userId) {
    $categories = ['needs' => [], 'wants' => []];

    $stmt = $conn->prepare("SELECT category_id AS id, name, parent_allocation FROM Categories WHERE user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $allocation = strtolower($row['parent_allocation']);
        if (isset($categories[$allocation])) {
            $categories[$allocation][] = $row;
        }
    }
    $stmt->close();
    return $categories;
}

// LOGIKA HANDLER AJAX
if ($action) {
    header('Content-Type: application/json');
    error_reporting(0);
    
    // Hentikan jika ID pengguna tidak valid (0 atau null)
    if (!$currentUserId || $currentUserId === 0) {
        $response['message'] = 'ID pengguna tidak valid. Harap login ulang.';
        echo json_encode($response);
        exit();
    }
    // kategori(READ)
    if ($action === 'load_categories') {
        $categories = getCategoriesFromDB($conn, $currentUserId);
        $response['success'] = true;
        $response['categories'] = $categories;
    }
    // ubah pass (UPDATE)
    elseif ($action === 'change_password') {
        $oldPass = $_POST['old_pass'] ?? '';
        $newPass = $_POST['new_pass'] ?? '';
        
        // 1. Ambil hash password lama dari DB
        $stmt = $conn->prepare("SELECT password_hash FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $currentUserId);
        $stmt->execute();
        $stmt->bind_result($passwordHash);
        $stmt->fetch();
        $stmt->close();
        
        if ($passwordHash && password_verify($oldPass, $passwordHash)) {
            // 2. Hash password baru dan update
            $newPasswordHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE Users SET password_hash = ? WHERE user_id = ?");
            $stmt_update->bind_param("si", $newPasswordHash, $currentUserId);

            if ($stmt_update->execute()) {
                $response['success'] = true;
                $response['message'] = 'Kata sandi berhasil diubah!';
            } else {
                $response['message'] = 'Gagal menyimpan kata sandi baru: ' . $conn->error;
            }
            $stmt_update->close();
        } else {
            $response['message'] = 'Kata sandi lama salah!';
        }
    }
    //tambah kategori TAMBAH (CREATE)
    elseif ($action === 'add_category') {
        $categoryName = $_POST['category_name'] ?? '';
        $parent = $_POST['parent'] ?? '';
        
        // 1. Cek duplikasi
        $stmt_check = $conn->prepare("SELECT category_id FROM Categories WHERE user_id = ? AND name = ?");
        $stmt_check->bind_param("is", $currentUserId, $categoryName);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $response['message'] = 'Kategori sudah ada!';
        } else {
            // 2. Insert baru
            $stmt_insert = $conn->prepare("INSERT INTO Categories (user_id, name, parent_allocation) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iss", $currentUserId, $categoryName, $parent);
            
            if ($stmt_insert->execute()) {
                $response['success'] = true;
                $response['message'] = 'Kategori berhasil ditambahkan.';
            } else {
                $response['message'] = 'Gagal menyimpan kategori: ' . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    // hapus kategori (DELETE)
    elseif ($action === 'delete_category') {
        $id = $_POST['id'] ?? 0;

        $stmt = $conn->prepare("DELETE FROM Categories WHERE category_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $currentUserId);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Kategori berhasil dihapus.';
        } else {
            $response['message'] = 'Gagal menghapus kategori: ' . $conn->error;
        }
        $stmt->close();
    }
    //hapus akun (DELETE)
    elseif ($action === 'delete_account') {
        $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $currentUserId);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Akun dan semua data terkait berhasil dihapus.';
        } else {
            $response['message'] = 'Gagal menghapus akun: ' . $conn->error;
        }
        $stmt->close();
    }
    echo json_encode($response);
    exit(); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Budget Analyzer</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
            <h2 class="app-title">Budget Analyzer</h2>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="transaksi.php" class="nav-item"><i class="fas fa-edit"></i> Transaksi</a>
                <a href="laporan.php" class="nav-item"><i class="fas fa-chart-pie"></i> Laporan</a>
                <a href="pengaturan.php" class="nav-item active"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
            <a href="logout.php" id="logout-link" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1>Pengaturan Aplikasi</h1>
                <div class="user-info">
                    <span>Selamat Datang, <span id="username-display"><?= htmlspecialchars($currentUsername) ?></span></span>
                </div>
            </header>

            <section class="card-container" id="account-settings">
                <h2>1. Pengaturan Profil & Keamanan</h2>
                
                <form id="change-password-form" class="setting-form">
                    <h3>Ubah Kata Sandi</h3>
                    <div class="input-group"><label>Password Lama</label><input type="password" id="old-pass" name="old_pass" required></div>
                    <div class="input-group"><label>Password Baru</label><input type="password" id="new-pass" name="new_pass" required></div>
                    <button type="submit" class="gradient-btn small-btn">Simpan Password</button>
                </form>
                
                <button id="delete-account-btn" class="gradient-btn danger-btn" style="margin-top: 20px;">Hapus Akun Permanen</button>
            </section>

            <section class="card-container" id="category-management">
                <h2>2. Manajemen Sub-Kategori</h2>
                
                <form id="add-category-form" class="setting-form form-row">
                    <div class="input-group"><label>Nama Kategori Baru</label><input type="text" id="new-category-name" name="category_name" required></div>
                    <div class="input-group">
                        <label>Alokasi Induk</label>
                        <select id="new-category-parent" name="parent" required>
                            <option value="needs">Kebutuhan</option>
                            <option value="wants">Keinginan</option>
                        </select>
                    </div>
                    <button type="submit" class="gradient-btn small-btn">Tambah</button>
                </form>

                <h3>Daftar Sub-Kategori Anda</h3>
                <div id="category-list-display">
                    <p>Memuat daftar kategori...</p>
                </div>
            </section>
            
        </main>
    </div>

    <script src="js/pengaturan.js"></script>
</body>
</html>