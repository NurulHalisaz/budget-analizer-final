<?php
include 'db_connect.php';
include 'ceklogin.php'; 

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

$currentUserId = $user_id; 
$currentUsername = $username; 

// FUNGSI UTILITY PHP 
function getTransactionsFromDB_ACTUAL($conn, $userId, $monthKey) {
    $transactions = []; 
    
    // Query DB nyata untuk transaksi di bulan spesifik
    $sql = "SELECT nominal, allocation FROM Transactions WHERE user_id = ? AND DATE_FORMAT(date, '%Y%m') = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $monthKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['nominal'] = (float)$row['nominal']; 
        $transactions[] = $row;
    }
    $stmt->close();
    
    return $transactions;
}

// UTILITY: MENGHITUNG SISA DANA (CALCULATION CORE)
function calculateActualBudget($budget, $transactions) {
    $totalSpent = ['needs' => 0.0, 'wants' => 0.0];
    
    foreach ($transactions as $t) {
        $nominal = (float)$t['nominal'];
        if ($t['allocation'] === 'needs') {
            $totalSpent['needs'] += $nominal;
        } elseif ($t['allocation'] === 'wants') {
            $totalSpent['wants'] += $nominal;
        }
    }
    
    $budget['sisa']['needs'] = $budget['nominal']['needs'] - $totalSpent['needs'];
    $budget['sisa']['wants'] = $budget['nominal']['wants'] - $totalSpent['wants'];
    
    return $budget;
}
// LOGIKA HANDLER AJAX 

if ($action) {
    header('Content-Type: application/json');

    // KOREKSI GUARD UTAMA: Jika user ID tidak valid, hentikan
    if (!$user_id || $user_id === 0) {
         $response['message'] = 'Sesi tidak valid. Harap login kembali.';
         echo json_encode($response);
         exit();
    }

    //menyimpan budget(CREATE/UPDATE)
    if ($action === 'save_budget') {
        $income = (float)($_POST['income'] ?? 0);
        $needsP = (float)($_POST['needsP'] ?? 0);
        $wantsP = (float)($_POST['wantsP'] ?? 0);
        $savingsP = (float)($_POST['savingsP'] ?? 0);
        $monthKey = $_POST['monthKey'] ?? '';
        
        $needs = $income * ($needsP / 100);
        $wants = $income * ($wantsP / 100);
        $savings = $income * ($savingsP / 100);
        
        // Simpan ke DB
        $stmt = $conn->prepare("INSERT INTO Budget_Target (user_id, month_key, income, needs_nominal, wants_nominal, savings_nominal) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE income=?, needs_nominal=?, wants_nominal=?, savings_nominal=?");
        
        $stmt->bind_param("isdddddddd", 
            $user_id, $monthKey, $income, $needs, $wants, $savings, 
            $income, $needs, $wants, $savings
        );

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Budget berhasil disimpan ke database.';
        } else {
            $response['message'] = 'Gagal menyimpan budget: ' . $conn->error; 
        }
        $stmt->close();
    } 

    // Menampilkan budget (READ)
    elseif ($action === 'load_budget') {
        $monthKey = $_POST['monthKey'] ?? '';
        
        // Ambil Target Budget dari DB
        $stmt = $conn->prepare("SELECT income, needs_nominal, wants_nominal, savings_nominal FROM Budget_Target WHERE user_id = ? AND month_key = ?");
        $stmt->bind_param("is", $user_id, $monthKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Data Target Persentase 
            $totalIncome = (float)$row['income'];
            
            $budgetData = [
                'income' => $totalIncome,
                'perc' => [
                    'needs' => ($totalIncome > 0) ? round(($row['needs_nominal'] / $totalIncome) * 100) : 0,
                    'wants' => ($totalIncome > 0) ? round(($row['wants_nominal'] / $totalIncome) * 100) : 0,
                    'savings' => ($totalIncome > 0) ? round(($row['savings_nominal'] / $totalIncome) * 100) : 0,
                ],
                'nominal' => [
                    'needs' => (float)$row['needs_nominal'],
                    'wants' => (float)$row['wants_nominal'],
                    'savings' => (float)$row['savings_nominal'],
                ],
                'sisa' => ['needs' => 0.0, 'wants' => 0.0, 'savings' => (float)$row['savings_nominal']], 
                'monthKey' => $monthKey
            ];

            // Hitung Sisa Aktual 
            $transactions = getTransactionsFromDB_ACTUAL($conn, $currentUserId, $monthKey);
            $finalBudget = calculateActualBudget($budgetData, $transactions);
            
            $response['success'] = true;
            $response['budget'] = $finalBudget;
            
        } else {
            $response['message'] = 'Budget bulan ini belum diatur.';
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
    <title>Dashboard - Budget Analyzer</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        
        <aside class="sidebar">
            <h2 class="app-title">Budget Analyzer</h2>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="transaksi.php" class="nav-item">
                    <i class="fas fa-edit"></i> Transaksi
                </a>
                <a href="laporan.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i> Laporan
                </a>
                <a href="pengaturan.php" class="nav-item">
                    <i class="fas fa-cog"></i> Pengaturan
                </a>
            </nav>
            <a href="logout.php" id="logout-link" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1>Dashboard Analisis Budget</h1>
                
                <div class="period-control">
                    <label for="active-month-select">Periode Aktif:</label>
                    <select id="active-month-select" class="form-control">
                        </select>
                </div>
                <div class="user-info">
                    <span>Selamat Datang, <span id="username-display">User!</span></span>
                    <i class="fas fa-user-circle"></i>
                </div>
            </header>

            <section class="summary-cards" id="target-summary">
            </section>
            
            <section class="setup-area card-container" id="budget-setup-area">
                <h2 id="setup-title">Setup Budget Bulan Ini</h2> 
                <form id="budget-setup-form">
                    <div class="input-group">
                        <label>Penghasilan Bersih (Rp):</label>
                        <input type="number" id="income" required min="1000">
                    </div>
                    
                    <div class="percentage-inputs">
                        <label>Kebutuhan (Needs, %):</label>
                        <input type="number" id="needs" value="50" min="0" max="100" required>%
                        <label>Keinginan (Wants, %):</label>
                        <input type="number" id="wants" value="30" min="0" max="100" required>%
                        <label>Tabungan (Savings, %):</label>
                        <input type="number" id="savings" value="20" min="0" max="100" required>%
                    </div>
                    <p id="percentage-error" class="error-message" style="display:none;">Total persentase harus 100%!</p>
                    <button type="submit" class="gradient-btn">Atur & Hitung Budget</button>
                </form>
            </section>
            
        </main>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>