const USER_ID = localStorage.getItem('currentUserId');
const USERNAME = localStorage.getItem('currentUsername');

function fillSubCategoryDropdown(categories, selectedType) {
    const subKategoriSelect = document.getElementById('transaksi-subkategori');
    const list = categories[selectedType] || [];
    subKategoriSelect.innerHTML = '<option value="" disabled selected>Pilih Sub-Kategori</option>';
    
    list.forEach(cat => {
        subKategoriSelect.innerHTML += `<option value="${cat.name}">${cat.name}</option>`;
    });
}
// FUNGSI MUAT DATA (READ dari DB)

// Mengambil Kategori dari DB untuk Dropdown
async function loadCategories() {
    // Tambahkan pengecekan cepat di awal
    if (!USER_ID) return { needs: [], wants: [] }; 

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'load_categories');
        formData.append('user_id', USER_ID);

        const response = await fetch('transaksi.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            return data.categories;
        } else {
            console.error('Gagal memuat kategori:', data.message);
            return { needs: [], wants: [] };
        }
    } catch (error) {
        console.error('Error koneksi saat memuat kategori:', error);
        return { needs: [], wants: [] };
    }
}
//Mengambil semua Transaksi dari DB
async function getCurrentTransactions() {
    if (!USER_ID) {
        return [];
    }

    try {
        const formData = new URLSearchParams();
        formData.append('action', 'load_transactions');
        formData.append('user_id', USER_ID);

        const response = await fetch('transaksi.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            return data.transactions;
        } else {
            console.error('Gagal memuat transaksi:', data.message);
            return [];
        }
    } catch (error) {
        console.error('Error koneksi saat memuat transaksi:', error);
        return [];
    }
}
//FUNGSI CRUD LOGIC (DB INTERACTION)

//Render Transaksi ke Tabel
async function renderTransactions() {
    const transactions = await getCurrentTransactions();
    const transactionList = document.getElementById('transaction-list');
    transactionList.innerHTML = '';
    let totalSpent = 0;

    transactions.sort((a, b) => new Date(b.date) - new Date(a.date)).forEach(t => {
        totalSpent += parseFloat(t.nominal) || 0; 
        const row = transactionList.insertRow();
        row.innerHTML = `
            <td>${t.date}</td>
            <td>${t.description} (${t.subcategory})</td>
            <td>Rp ${parseFloat(t.nominal).toLocaleString('id-ID')}</td>
            <td><span class="badge ${t.allocation}">${t.allocation.toUpperCase()}</span></td>
            <td>
                <button onclick="editHandler(${t.id})" class="aksi-btn edit-btn"><i class="fas fa-edit"></i></button>
                <button onclick="deleteHandler(${t.id}, '${t.date}')" class="aksi-btn delete-btn"><i class="fas fa-trash"></i></button>
            </td>
        `;
    });

    document.getElementById('total-spent-display').textContent = `Rp ${totalSpent.toLocaleString('id-ID')}`;
}

// Simpan Transaksi (CREATE/UPDATE) ke DB
async function saveTransaction(formData) {
    if (!USER_ID) {
        alert('Sesi pengguna hilang. Harap login ulang.');
        window.location.href = 'logout.php'; // Arahkan ke logout
        return;
    }
    
    formData.append('action', 'save_transaction');
    formData.append('user_id', USER_ID);
    
    try {
        const response = await fetch('transaksi.php', {
            method: 'POST',
            body: formData 
        });
        const data = await response.json();

        if (data.success) {
            alert(data.message);
            renderTransactions(); 
        } else {
            alert('Gagal menyimpan: ' + data.message);
        }
    } catch (error) {
        alert('Gagal koneksi server saat menyimpan transaksi.');
    }
}

// Mengirim permintaan Hapus ke DB
window.deleteHandler = async (id, date) => {
    if (!confirm('Yakin ingin menghapus transaksi ini?')) return;

    if (!USER_ID) {
        alert('Sesi pengguna hilang. Harap login ulang.');
        window.location.href = 'logout.php'; // Arahkan ke logout
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('action', 'delete_transaction');
    formData.append('id', id);
    formData.append('user_id', USER_ID);
    formData.append('txn_date', date); 

    try {
        const response = await fetch('transaksi.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            renderTransactions(); 
        } else {
            alert('Gagal menghapus: ' + data.message);
        }
    } catch (error) {
        alert('Gagal koneksi server saat menghapus transaksi.');
    }
};

//Update setelah edit
window.editHandler = async (id) => {
    const transactions = await getCurrentTransactions();
    const t = transactions.find(t => t.id === id);
    if (!t) return;
    
    const categories = await loadCategories(); 
    
    document.getElementById('transaksi-nominal').value = t.nominal;
    document.getElementById('transaksi-deskripsi').value = t.description;
    document.getElementById('transaksi-tanggal').value = t.date;
    document.getElementById('transaksi-alokasi').value = t.allocation;
    
    setTimeout(() => {
        fillSubCategoryDropdown(categories, t.allocation);
        document.getElementById('transaksi-subkategori').value = t.subcategory;
    }, 50);

    document.getElementById('transaksi-id-edit').value = t.id;

    document.getElementById('form-title').textContent = 'Edit Transaksi';
    document.getElementById('submit-btn').textContent = 'Simpan Perubahan';
    
    document.getElementById('transaction-input-area').scrollIntoView({ behavior: 'smooth' });
};
// MAIN EXECUTION

document.addEventListener('DOMContentLoaded', async () => {
    if (!USER_ID) {
        window.location.href = 'logout.php'; 
        return;
    }
    
    const form = document.getElementById('transaction-form');
    const subKategoriSelect = document.getElementById('transaksi-subkategori');
    const transaksiAlokasi = document.getElementById('transaksi-alokasi');

    //Muat kategori dari DB secara Asinkron
    const categories = await loadCategories();
    
    // Setup Event Listener untuk Dropdown Alokasi
    transaksiAlokasi.addEventListener('change', (e) => {
        const selectedType = e.target.value;
        fillSubCategoryDropdown(categories, selectedType);
    });

    // Setup Event Listener untuk Form Submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        await saveTransaction(formData); 

        form.reset(); 
        document.getElementById('form-title').textContent = 'Tambah Transaksi Baru';
        document.getElementById('submit-btn').textContent = 'Catat Transaksi';
        document.getElementById('transaksi-id-edit').value = '';
        
        // Reset dropdown subkategori
        subKategoriSelect.innerHTML = '<option value="" disabled selected>Pilih Sub-Kategori</option>';
    });
    
    //Setup Logout Handler
    document.getElementById('logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUsername');
        localStorage.removeItem('currentUserId');
        window.location.href = 'logout.php';
    });
    //Jalankan render saat halaman dimuat
    renderTransactions();
});