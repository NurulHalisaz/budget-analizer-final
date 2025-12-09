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
// muat data(READ dari DB VIA FETCH)
async function fetchReportData(monthKey) {
    if (!USER_ID) {
        return null;
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'load_report');
    formData.append('user_id', USER_ID);
    formData.append('monthKey', monthKey);
    
    try {
        const response = await fetch('laporan.php', {
            method: 'POST',
            body: formData
        });
        
        // Cek apakah server mengembalikan HTML (yang berarti PHP redirect gagal)
        if (response.headers.get('Content-Type') && !response.headers.get('Content-Type').includes('application/json')) {
             console.warn('Respons bukan JSON. Mungkin sesi PHP berakhir.');
             return null;
        }

        const data = await response.json();
        
        if (data.success) {
            return { analysis: data.analysis, periodName: data.periodName };
        } else {
            alert(data.message || 'Gagal memuat laporan.');
            return null;
        }
    } catch (error) {
        // Tangkap kegagalan koneksi umum (tidak ada internet/server down)
        alert('Error koneksi server saat memuat laporan.');
        console.error('Fetch error:', error);
        return null;
    }
}

//logika rendering hasil
const renderReport = (analysis, periodName) => {
    const summaryDiv = document.getElementById('deviation-summary');
    const categoryList = document.getElementById('category-list');
    const chartArea = document.getElementById('deviation-chart-area');
    const analysisTitle = document.getElementById('analysis-title'); 

    analysisTitle.textContent = `Analisis Deviasi (${periodName})`;
    
    if (!analysis) {
        chartArea.innerHTML = "<p>Tidak ada data budget atau transaksi untuk periode ini.</p>";
        summaryDiv.innerHTML = '<div></div>';
        categoryList.innerHTML = '<li>Tidak ada pengeluaran yang tercatat.</li>';
        return;
    }
    
    const { deviationData, nominalDeviation, subCategoryTotals, totalActualSpent } = analysis;
    const formatRupiah = (num) => `Rp ${Math.abs(num).toLocaleString('id-ID')}`;
    const isOverspent = (value) => value < 0;

    // Render Visualisasi Deviasi (Progress)
    chartArea.innerHTML = `
        <h3>Kebutuhan: Target ${deviationData.needs.target}% | Aktual ${deviationData.needs.actual.toFixed(1)}%</h3>
        <div class="progress-bar-container">
            <div class="progress-bar" 
                 style="width: ${Math.min(100, deviationData.needs.actual)}%; 
                 background-color: ${deviationData.needs.actual > deviationData.needs.target ? '#e74c3c' : '#2ecc71'};">
                 ${deviationData.needs.actual.toFixed(1)}%
            </div>
        </div>
        
        <h3>Keinginan: Target ${deviationData.wants.target}% | Aktual ${deviationData.wants.actual.toFixed(1)}%</h3>
        <div class="progress-bar-container">
            <div class="progress-bar" 
                 style="width: ${Math.min(100, deviationData.wants.actual)}%; 
                 background-color: ${deviationData.wants.actual > deviationData.wants.target ? '#e74c3c' : '#2ecc71'};">
                 ${deviationData.wants.actual.toFixed(1)}%
            </div>
        </div>
    `;

    // Render Deviasi Nominal dan Total Pengeluaran
    summaryDiv.innerHTML = `
        <div>
            <p><strong>Deviasi Kebutuhan:</strong> <span class="nominal-value ${isOverspent(nominalDeviation.needs) ? 'overspent' : ''}">${isOverspent(nominalDeviation.needs) ? 'Defisit' : 'Sisa'} ${formatRupiah(nominalDeviation.needs)}</span></p>
            <p><strong>Deviasi Keinginan:</strong> <span class="nominal-value ${isOverspent(nominalDeviation.wants) ? 'overspent' : ''}">${isOverspent(nominalDeviation.wants) ? 'Defisit' : 'Sisa'} ${formatRupiah(nominalDeviation.wants)}</span></p>
        </div>
        <p style="text-align:right;"><strong>Total Pengeluaran ${periodName}:</strong> <span class="nominal-value" style="color:black; font-size: 1.2em;">${formatRupiah(totalActualSpent)}</span></p>
    `;

    //Render Detail Sub-Kategori (Top 5)
    const sortedCategories = Object.entries(subCategoryTotals).sort(([, a], [, b]) => b - a);

    categoryList.innerHTML = '';
    if (sortedCategories.length > 0) {
        sortedCategories.slice(0, 5).forEach(([cat, total]) => {
            categoryList.innerHTML += `<li><span class="category-name">${cat}:</span> <span class="category-nominal">${formatRupiah(total)}</span></li>`;
        });
    } else {
        categoryList.innerHTML = '<li>Tidak ada pengeluaran yang tercatat di bulan ini.</li>';
    }
};
// MAIN EXECUTION
document.addEventListener('DOMContentLoaded', async () => {
    const reportForm = document.getElementById('report-filter-form');
    const monthSelect = document.getElementById('periode-select');
    
    //inisiasi bulan
    const currentMonthKey = populateMonthSelector(monthSelect);

    reportForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const selectedMonthKey = document.getElementById('periode-select').value;
        
        const result = await fetchReportData(selectedMonthKey);
        
        if (result && result.analysis) {
            renderReport(result.analysis, result.periodName);
        } else {
            // Reset tampilan jika gagal
            renderReport(null, formatDateKey(selectedMonthKey));
        }
    });
    
    // Setup Logout 
    document.getElementById('logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUsername');
        localStorage.removeItem('currentUserId');
        window.location.href = 'logout.php';
    });
    // Jalankan render awal untuk Bulan Ini saat halaman dimuat
    reportForm.dispatchEvent(new Event('submit')); 
});