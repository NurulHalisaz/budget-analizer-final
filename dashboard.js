
// Data pengguna yang diambil dari localStorage 
const USER_ID = localStorage.getItem('currentUserId');
const USERNAME = localStorage.getItem('currentUsername');

const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];

function formatDateKey(dateKey) {
    if (!dateKey || dateKey.length !== 6) return 'Bulan Tidak Valid';
    const year = dateKey.substring(0, 4);
    const monthIndex = parseInt(dateKey.substring(4, 6)) - 1;
    return `${monthNames[monthIndex]} ${year}`;
}

// Mengisi Dropdown dengan Semua Bulan
function populateMonthSelector(selectorElement) {
    const today = new Date();
    const currentYear = today.getFullYear();
    const currentMonth = today.getMonth(); 
    
    let optionsHtml = '';
    
    let startYear = 2025; 
    
    for (let year = startYear; year <= currentYear; year++) {
        let maxMonth = (year === currentYear) ? currentMonth : 11;
        let minMonth = 0; 
        
        for (let monthIndex = minMonth; monthIndex <= maxMonth; monthIndex++) {
            const monthKey = year.toString() + (monthIndex + 1).toString().padStart(2, '0');
            const monthName = monthNames[monthIndex];
            const display = `${monthName} ${year}`;
            
            const isCurrent = (year === currentYear && monthIndex === currentMonth);
            const selectedAttr = isCurrent ? 'selected' : '';
            const label = isCurrent ? ' (Bulan Ini)' : '';

            optionsHtml += `<option value="${monthKey}" ${selectedAttr}>${display}${label}</option>`;
        }
    }

    selectorElement.innerHTML = optionsHtml;
    return currentYear.toString() + (currentMonth + 1).toString().padStart(2, '0');
}

//Mengambil semua transaksi pengguna saat ini 
function getCurrentTransactions() {
    return '[]'; 
}
// display username
function displayUsername() {
    const displayElement = document.getElementById('username-display');
    if (USERNAME && displayElement) {
        displayElement.textContent = USERNAME;
    }
}
// Fungsi interaksi dengan DB
// FUNGSI SAVE BUDGET 
async function saveBudgetToDB(income, needsP, wantsP, savingsP, monthKey) {
    if (!USER_ID) {
        return { success: false, message: 'ID pengguna hilang. Harap login ulang.' };
    }

    const formData = new URLSearchParams();
    formData.append('action', 'save_budget');
    formData.append('user_id', USER_ID);
    formData.append('username', USERNAME); 
    formData.append('monthKey', monthKey);
    formData.append('income', income);
    formData.append('needsP', needsP);
    formData.append('wantsP', wantsP);
    formData.append('savingsP', savingsP);
    
    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        
        if (!response.ok) {
            console.error('Server responded with an error:', response.statusText);
            return { success: false, message: `Kesalahan HTTP ${response.status}: Periksa koneksi DB/query.` };
        }
        
        const data = await response.json();
        return data;

    } catch (error) {
        console.error("Error saving budget (catch block):", error);
        return { success: false, message: 'Gagal koneksi ke server saat menyimpan budget. (Jaringan atau Fatal Error PHP).' };
    }
}

// FUNGSI LOAD BUDGET 
async function fetchAndLoadDashboardData(monthKey) {
    // Pengecekan USER_ID harus ada 
    if (!USER_ID) {
        // Alihkan ke logout untuk cleanup sesi server jika klien gagal
        window.location.href = 'logout.php'; 
        return;
    }
    
    const targetSummary = document.getElementById('target-summary');
    const form = document.getElementById('budget-setup-form');

    const formData = new URLSearchParams();
    formData.append('action', 'load_budget');
    formData.append('user_id', USER_ID);
    formData.append('username', USERNAME);
    formData.append('monthKey', monthKey);
    try {
        const response = await fetch('dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await response.json();

        document.getElementById('setup-title').textContent = `Setup Budget Bulan ${formatDateKey(monthKey)}`;

        if (data.success && data.budget) {
            const budget = data.budget;
            
            renderSummaryCards(budget); 
            
            document.getElementById('income').value = budget.income;
            document.getElementById('needs').value = budget.perc.needs;
            document.getElementById('wants').value = budget.perc.wants;
            document.getElementById('savings').value = budget.perc.savings;

        } else {
            // Data tidak ada di DB
            targetSummary.innerHTML = `<p style="grid-column: 1 / span 3; text-align: center;">Silakan atur budget Anda untuk ${formatDateKey(monthKey)} di bagian Setup Budget.</p>`;
            form.reset(); 
            
            document.getElementById('needs').value = 50;
            document.getElementById('wants').value = 30;
            document.getElementById('savings').value = 20;
        }
    } catch (error) {
        console.error("Error fetching dashboard data:", error);
        targetSummary.innerHTML = `<p style="grid-column: 1 / span 3; text-align: center; color: red;">Gagal memuat data. Periksa koneksi server.</p>`;
    }
}
// LOGIKA RENDERING JAVASCRIPT 

// FUNGSI Rendering Visualisasi Summary Cards 
function renderSummaryCards(data) {
    const targetSummary = document.getElementById('target-summary');
    
    const formatRupiah = (number) => {
        const formatted = Math.abs(number).toLocaleString('id-ID');
        return (number < 0 ? `- Rp ${formatted}` : `Rp ${formatted}`);
    };

    targetSummary.innerHTML = `
        <div class="summary-card card-needs" style="background-color: ${data.sisa.needs < 0 ? '#e74c3c' : ''};">
            <h3>Kebutuhan (${data.perc.needs}%)</h3>
            <p>Target Awal: ${formatRupiah(data.nominal.needs)}</p>
            <p>Sisa Dana: ${formatRupiah(data.sisa.needs)}</p>
        </div>
        <div class="summary-card card-wants" style="background-color: ${data.sisa.wants < 0 ? '#e74c3c' : ''};">
            <h3>Keinginan (${data.perc.wants}%)</h3>
            <p>Target Awal: ${formatRupiah(data.nominal.wants)}</p>
            <p>Sisa Dana: ${formatRupiah(data.sisa.wants)}</p>
        </div>
        <div class="summary-card card-savings">
            <h3>Tabungan (${data.perc.savings}%)</h3>
            <p>Total Alokasi: ${formatRupiah(data.nominal.savings)}</p>
            <p>Progress: Belum Dicatat</p>
        </div>
    `;
}

// FUNGSI Logika Utama Dashboard (Mengatur Event Listeners)
function setupDashboardLogic() {
    const form = document.getElementById('budget-setup-form');
    const errorMsg = document.getElementById('percentage-error');
    const monthSelect = document.getElementById('active-month-select');
    
    // inisiasi bulan
    const currentMonthKey = populateMonthSelector(monthSelect);
    
    //untuk perubahan bulan
    monthSelect.addEventListener('change', function() {
        fetchAndLoadDashboardData(this.value);
    });

    // load bulan ini
    fetchAndLoadDashboardData(currentMonthKey);
    
    // untuk form submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const monthKey = monthSelect.value; 
        const income = parseFloat(document.getElementById('income').value);
        const needsPerc = parseFloat(document.getElementById('needs').value);
        const wantsPerc = parseFloat(document.getElementById('wants').value);
        const savingsPerc = parseFloat(document.getElementById('savings').value);

        if (needsPerc + wantsPerc + savingsPerc !== 100) {
            errorMsg.style.display = 'block';
            return;
        }

        errorMsg.style.display = 'none';
        
        // Simpan ke DB
        const result = await saveBudgetToDB(income, needsPerc, wantsPerc, savingsPerc, monthKey);
        
        if (result.success) {
            // Muat ulang tampilan (Summary Cards)
            await fetchAndLoadDashboardData(monthKey);
            alert(`Budget untuk ${formatDateKey(monthKey)} berhasil diatur dan disimpan!`);
        } else {
            alert(`Gagal menyimpan budget: ${result.message}`);
        }
    });
    
    // logout
    document.getElementById('logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUsername');
        localStorage.removeItem('currentUserId');
        window.location.href = 'logout.php'; 
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Panggil fungsi display username
    displayUsername(); 
    
    if (document.querySelector('.dashboard-wrapper')) {
        setupDashboardLogic();
    }
});