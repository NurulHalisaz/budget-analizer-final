const USER_ID = localStorage.getItem('currentUserId');
const USERNAME = localStorage.getItem('currentUsername');

function displayUsername() {
    const displayElement = document.getElementById('username-display');
    if (USERNAME && displayElement) {
        displayElement.textContent = USERNAME;
    }
}
// FUNGSI MUAT & RENDER KATEGORI (READ)

//Mengambil Kategori dari DB untuk list
async function getCategoriesData() {
    // Tambahkan pengecekan User ID di sini 
    if (!USER_ID) {
        return { needs: [], wants: [] };
    }
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'load_categories');
        formData.append('user_id', USER_ID);

        const response = await fetch('pengaturan.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            return data.categories;
        }
        return { needs: [], wants: [] };
    } catch (error) {
        console.error('Error koneksi saat memuat kategori:', error);
        return { needs: [], wants: [] };
    }
}

//Merender Daftar Kategori ke HTML
const renderCategoryList = (categories) => {
    const displayDiv = document.getElementById('category-list-display');
    displayDiv.innerHTML = '';

    ['needs', 'wants'].forEach(parent => {
        const list = categories[parent];
        let html = `<h3>${parent.toUpperCase()} (${parent === 'needs' ? 'Kebutuhan' : 'Keinginan'})</h3>`;
        
        if (list.length === 0) {
            html += `<p>Belum ada sub-kategori.</p>`;
        } else {
            html += `<ul>`;
            list.forEach(cat => {
                html += `
                    <li>
                        <span>${cat.name}</span>
                        <div class="category-actions">
                            <button onclick="editCategory(${cat.id}, '${parent}', '${cat.name}')" class="aksi-btn edit-btn small-btn"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteCategory(${cat.id})" class="aksi-btn delete-btn small-btn"><i class="fas fa-trash"></i></button>
                        </div>
                    </li>
                `;
            });
            html += `</ul>`;
        }
        displayDiv.innerHTML += html;
    });
};
// FUNGSI CRUD LOGIC (DB INTERACTION)

// Menambah Kategori Baru
async function addCategory(formData) {
    if (!USER_ID) return alert('Sesi berakhir. Harap login ulang.');
    formData.append('action', 'add_category');
    formData.append('user_id', USER_ID);

    //Kirim FormData langsung
    const response = await fetch('pengaturan.php', { method: 'POST', body: formData });
    const data = await response.json();
    
    alert(data.message);
    if (data.success) {
        const categories = await getCategoriesData();
        renderCategoryList(categories);
    }
}

//Menghapus Kategori
window.deleteCategory = async (id) => {
    if (!confirm('Yakin ingin menghapus kategori ini?')) return;
    if (!USER_ID) return alert('Sesi berakhir. Harap login ulang.');
    
    // Kirim menggunakan URLSearchParams karena hanya sedikit data
    const formData = new URLSearchParams();
    formData.append('action', 'delete_category');
    formData.append('user_id', USER_ID);
    formData.append('id', id);

    const response = await fetch('pengaturan.php', { method: 'POST', body: formData });
    const data = await response.json();
    
    alert(data.message);
    if (data.success) {
        const categories = await getCategoriesData();
        renderCategoryList(categories);
    }
};

//Memuat data ke form untuk Edit
window.editCategory = async (id, parent, oldName) => {
    const newName = prompt(`Ubah nama kategori "${oldName}" (Hanya Nama yang Bisa Diubah):`);
    
    if (newName && newName.trim() !== "" && newName.trim() !== oldName) {
        alert("Fungsi Edit (Update) belum didukung secara penuh di backend ini. Harap hapus dan buat ulang.");
    }
};

// Ubah Password
async function changePassword(formData) {
    if (!USER_ID) return alert('Sesi berakhir. Harap login ulang.');
    formData.append('action', 'change_password');
    formData.append('user_id', USER_ID);

    //Kirim FormData langsung
    const response = await fetch('pengaturan.php', { method: 'POST', body: formData });
    const data = await response.json();
    
    alert(data.message);
    if (data.success) {
        document.getElementById('change-password-form').reset();
    }
}

//Hapus Akun Permanen
async function deleteAccount() {
    if (!confirm('PERINGATAN! Tindakan ini akan menghapus semua data Anda secara PERMANEN. Lanjutkan?')) return;
    if (!USER_ID) return alert('Sesi berakhir. Harap login ulang.');
    
    const formData = new URLSearchParams();
    formData.append('action', 'delete_account');
    formData.append('user_id', USER_ID);
    
    const response = await fetch('pengaturan.php', { method: 'POST', body: formData });
    const data = await response.json();

    alert(data.message);
    
    if (data.success) {
        // Hapus simulasi sesi dari client
        localStorage.clear();
        window.location.href = 'logout.php'; 
    }
}
//MAIN EXECUTION

document.addEventListener('DOMContentLoaded', async () => {
    const categoryForm = document.getElementById('add-category-form');
    const passwordForm = document.getElementById('change-password-form');
    const deleteAccountBtn = document.getElementById('delete-account-btn');
    
    displayUsername();

    // Muat Kategori Awal
    const categories = await getCategoriesData();
    renderCategoryList(categories);

    // Tambah Kategori (MENGGUNAKAN FORMDATA LANGSUNG)
    categoryForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(categoryForm);
        await addCategory(formData);
        categoryForm.reset();
    });

    //Ubah Password (MENGGUNAKAN FORMDATA LANGSUNG)
    passwordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(passwordForm);
        await changePassword(formData);
    });

    //Event Listener: Hapus Akun
    deleteAccountBtn.addEventListener('click', deleteAccount);
    
    // Setup Logout 
    document.getElementById('logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUsername');
        localStorage.removeItem('currentUserId');
        window.location.href = 'logout.php';
    });
});