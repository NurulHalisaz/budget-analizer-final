<?php

include 'db_connect.php'; 
include 'ceklogin.php'; 

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

$currentUserId = $user_id; 
$currentUsername = $username; 

function formatDateKey($dateKey) {
    $monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    if (!isset($dateKey[5])) return 'Bulan Tidak Valid';
    $year = substr($dateKey, 0, 4);
    $monthIndex = (int)substr($dateKey, 4, 2) - 1;
    return $monthNames[$monthIndex] . ' ' . $year;
}

// Mengambil Transaksi dari DB 
function getTransactionsFromDB($conn, $userId, $monthKey) {
    $transactions = [];
    $sql = "SELECT nominal, allocation, subcategory FROM Transactions WHERE user_id = ? AND DATE_FORMAT(date, '%Y%m') = ?";
    
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

// Mengambil Target Budget dari DB
function getBudgetTargetFromDB($conn, $userId, $monthKey) {
    $stmt = $conn->prepare("SELECT income, needs_nominal, wants_nominal, savings_nominal FROM Budget_Target WHERE user_id = ? AND month_key = ?");
    $stmt->bind_param("is", $userId, $monthKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $totalIncome = (float)$row['income'];
        
        // Membangun struktur budgetData yang lengkap
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
            'monthKey' => $monthKey
        ];
        return $budgetData;
    }
    $stmt->close();
    return null;
}

// analisis deviasi 
function analyzeReport($budget, $transactions) {
    if (!$budget || $budget['income'] === 0) return null;

    $totalIncome = $budget['income'];
    $actualSpent = ['needs' => 0.0, 'wants' => 0.0];
    $subCategoryTotals = [];

    foreach ($transactions as $t) {
        $nominal = (float)$t['nominal'];
        if ($t['allocation'] === 'needs') { 
            $actualSpent['needs'] += $nominal;
        } elseif ($t['allocation'] === 'wants') {
             $actualSpent['wants'] += $nominal;
        }
        $subCategoryTotals[$t['subcategory']] = ($subCategoryTotals[$t['subcategory']] ?? 0.0) + $nominal;
    }

    $nominalDeviation = [
        'needs' => $budget['nominal']['needs'] - $actualSpent['needs'],
        'wants' => $budget['nominal']['wants'] - $actualSpent['wants'],
    ];
    
    $deviationData = [
        'needs' => [
            'target' => $budget['perc']['needs'],
            'actual' => $totalIncome ? ($actualSpent['needs'] / $totalIncome) * 100 : 0
        ],
        'wants' => [
            'target' => $budget['perc']['wants'],
            'actual' => $totalIncome ? ($actualSpent['wants'] / $totalIncome) * 100 : 0
        ]
    ];
    
    $totalActualSpent = $actualSpent['needs'] + $actualSpent['wants'];

    return [
        'deviationData' => $deviationData, 
        'nominalDeviation' => $nominalDeviation, 
        'subCategoryTotals' => $subCategoryTotals, 
        'totalActualSpent' => $totalActualSpent
    ];
}

// LOGIKA HANDLER AJAX (load_report)
if ($action === 'load_report') {
    header('Content-Type: application/json');
    error_reporting(0);
    ini_set('display_errors', 0);
    
    $monthKey = $_POST['monthKey'] ?? '';

    // 1. Ambil Target Budget
    $budget = getBudgetTargetFromDB($conn, $currentUserId, $monthKey);

    if (!$budget) {
        $response['message'] = 'Budget untuk bulan ini belum diatur.';
        echo json_encode($response);
        exit();
    }

    // 2. Ambil Transaksi yang Difilter
    $transactions = getTransactionsFromDB($conn, $currentUserId, $monthKey);

    // 3. Analisis
    $analysisResults = analyzeReport($budget, $transactions);
    
    $response['success'] = true;
    $response['analysis'] = $analysisResults;
    $response['periodName'] = formatDateKey($monthKey);

    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Budget Analyzer</title>
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
                <a href="laporan.php" class="nav-item active"><i class="fas fa-chart-pie"></i> Laporan</a>
                <a href="pengaturan.php" class="nav-item"><i class="fas fa-cog"></i> Pengaturan</a>
            </nav>
            <a href="logout.php" id="logout-link" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <h1>Laporan & Analisis Budget</h1>
                <div class="user-info">
                    <span id="report-period-display">Analisis Bulan Ini</span> 
                </div>
            </header>

            <section class="card-container" id="report-controls">
                <form id="report-filter-form" class="form-row">
                    <div class="input-group">
                        <label for="periode-select">Pilih Periode Laporan</label>
                        <select id="periode-select">
                            </select>
                    </div>
                    <button type="submit" class="gradient-btn small-btn">Tampilkan Laporan</button>
                </form>
            </section>

            <section class="card-container" id="deviation-analysis">
                <h2 id="analysis-title">Analisis Deviasi (Target vs. Aktual)</h2> 
                <div id="deviation-chart-area" class="chart-area">
                    <p>Pilih periode dan tampilkan laporan...</p>
                </div>
                <div id="deviation-summary" class="summary-details">
                </div>
            </section>
            
            <section class="card-container" id="top-categories">
                <h2>Detail Pengeluaran (Sub-Kategori)</h2>
                <ul id="category-list">
                    <li>Memuat data...</li>
                </ul>
            </section>
        </main>
    </div>

    <script src="js/laporan.js"></script>
</body>
</html>