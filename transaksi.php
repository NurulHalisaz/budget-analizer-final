<?php

include 'db_connect.php'; 
// Cek autentikasi & Ambil User ID dari simulasi sesi
session_start();
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User'; 
$currentUserId = $_POST['user_id'] ?? $user_id; 
$currentUsername = $_POST['username'] ?? $username;

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

//Mengambil Kategori dari DB (Untuk Dropdown)
function getCategoriesFromDB($conn, $userId) {
    $categories = ['needs' => [], 'wants' => []];

    $stmt = $conn->prepare("SELECT category_id AS id, name, parent_allocation FROM Categories WHERE user_id IS NULL OR user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $allocation = strtolower($row['parent_allocation']);
        if (isset($categories[$allocation])) {
             // KOREKSI: Tambahkan ID kategori
            $categories[$allocation][] = ['id' => $row['id'], 'name' => $row['name']];
        }
    }
    $stmt->close();
    return $categories;
}

//Mengambil Transaksi dari DB (Untuk Log)
function getTransactionsFromDB($conn, $userId, $monthKey = null) {
    $transactions = [];
    $sql = "SELECT txn_id AS id, nominal, description, date, allocation, subcategory FROM Transactions WHERE user_id = ? ORDER BY date DESC";
    
    if ($monthKey) {
        $sql = "SELECT txn_id AS id, nominal, description, date, allocation, subcategory FROM Transactions WHERE user_id = ? AND DATE_FORMAT(date, '%Y%m') = ? ORDER BY date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $monthKey);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['nominal'] = (float)$row['nominal'];
        $transactions[] = $row;
    }
    $stmt->close();
    return $transactions;
}
// LOGIKA HANDLER AJAX
if ($action) {
    header('Content-Type: application/json');
    // Matikan error reporting di frontend agar tidak bocor (setelah pengecekan berhasil)
    error_reporting(0);
    
    //Hentikan jika ID pengguna tidak valid (0 atau null)
    if (!$currentUserId || $currentUserId === 0) {
        $response['message'] = 'ID pengguna tidak valid. Harap login ulang.';
        echo json_encode($response);
        exit();
    }
    //load kategori (READ)
    if ($action === 'load_categories') {
        $categories = getCategoriesFromDB($conn, $currentUserId);
        $response['success'] = true;
        $response['categories'] = $categories;
    }
    //load transaksi (READ)
    elseif ($action === 'load_transactions') {
        // memuat semua transaksi untuk saat ini
        $transactions = getTransactionsFromDB($conn, $currentUserId); 
        $response['success'] = true;
        $response['transactions'] = $transactions;
    }
    // simpan transaksi (CREATE/UPDATE)
    elseif ($action === 'save_transaction') {
        $nominal = (float)($_POST['nominal'] ?? 0);
        $description = $_POST['description'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        $allocation = $_POST['allocation'] ?? '';
        $subcategory = $_POST['subcategory'] ?? '';
        $editId = $_POST['edit_id'] ?? null;
        
        if ($editId) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE Transactions SET nominal=?, description=?, date=?, allocation=?, subcategory=? WHERE txn_id=? AND user_id=?");
            $stmt->bind_param("dsssiis", $nominal, $description, $date, $allocation, $subcategory, $editId, $currentUserId); 
        } else {
            // CREATE
            $stmt = $conn->prepare("INSERT INTO Transactions (user_id, nominal, description, date, allocation, subcategory) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idssss", $currentUserId, $nominal, $description, $date, $allocation, $subcategory); 
        }

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = $editId ? 'Transaksi berhasil diperbarui.' : 'Transaksi berhasil dicatat.';
        } else {
            // menampikan jika gagal
            $response['message'] = 'Gagal menyimpan transaksi: ' . $conn->error; 
        }
        $stmt->close();
    }
    // hapus transaksi
    elseif ($action === 'delete_transaction') {
        $id = $_POST['id'] ?? 0;
        $txnDate = $_POST['txn_date'] ?? null; 

        $stmt = $conn->prepare("DELETE FROM Transactions WHERE txn_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $currentUserId);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Transaksi berhasil dihapus.';
            $response['message'] = 'Gagal menghapus transaksi: ' . $conn->error;
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
    <title>Transaksi - Budget Analyzer</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
            <h2 class="app-title">Budget Analyzer</h2>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="transaksi.php" class="nav-item active"><i class="fas fa-edit"></i> Transaksi</a>
                <a href="laporan.php" class="nav-item"><i class="fas fa-chart-pie"></i> Laporan</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
            <a href="logout.php" id="logout-link" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1>Pencatatan Transaksi</h1>
                <div class="user-info">
                    <span>Selamat Datang, <span id="username-display"><?= htmlspecialchars($currentUsername) ?></span></span>
                </div>
            </header>

            <section class="card-container" id="transaction-input-area">
                <h3 id="form-title">Tambah Transaksi Baru</h3>
                <form id="transaction-form">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="transaksi-nominal">Nominal (Rp)</label>
                            <input type="number" id="transaksi-nominal" name="nominal" required min="100">
                        </div>
                        <div class="input-group">
                            <label for="transaksi-deskripsi">Deskripsi</label>
                            <input type="text" id="transaksi-deskripsi" name="description" placeholder="Misal: Beli Kopi" required>
                        </div>
                        <div class="input-group">
                            <label for="transaksi-tanggal">Tanggal</label>
                            <input type="date" id="transaksi-tanggal" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="transaksi-alokasi">Alokasi Budget</label>
                            <select id="transaksi-alokasi" name="allocation" required>
                                <option value="" disabled selected>Pilih Kategori Induk</option>
                                <option value="needs">Kebutuhan</option>
                                <option value="wants">Keinginan</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="transaksi-subkategori">Sub-Kategori</label>
                            <select id="transaksi-subkategori" name="subcategory" required>
                                <option value="" disabled selected>Pilih Sub-Kategori</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="gradient-btn" id="submit-btn">Catat Transaksi</button>
                    <input type="hidden" id="transaksi-id-edit" name="edit_id"> 
                </form>
            </section>

            <section class="card-container" id="transaction-list-area">
                <h2>Log Pengeluaran Bulan Ini</h2>
                <table id="transaction-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Deskripsi</th>
                            <th>Nominal</th>
                            <th>Alokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="transaction-list">
                    </tbody>
                </table>
                <div class="total-spent">
                    <strong>Total Pengeluaran:</strong> <span id="total-spent-display">Rp 0</span>
                </div>
            </section>
        </main>
    </div>

    <script src="js/transaksi.js"></script>
</body>
</html>