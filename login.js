function showForm(formName) {
    const forms = document.querySelectorAll('.auth-form');
    forms.forEach(form => {
        form.classList.remove('active-form');
    });
    const targetForm = document.getElementById(formName + '-form');
    if (targetForm) {
        targetForm.classList.add('active-form');
    }

    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(button => {
        button.classList.remove('active');
    });
    const targetTab = document.getElementById('tab-' + formName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
}

// FUNGSI LUPA PASSWORD 
function showResetForm(e) {
    if (e) e.preventDefault(); 
    
    const usernameToReset = prompt("Masukkan Username Anda untuk mengatur ulang kata sandi:");

    if (usernameToReset) {
        //Cek apakah user ada di DB
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=reset_check&username=${usernameToReset}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const newPassword = prompt(`Username ${usernameToReset} ditemukan. Masukkan Kata Sandi BARU (min. 4 karakter):`);
                if (newPassword) {
                    updatePassword(usernameToReset, newPassword);
                }
            } else {
                alert(data.message);
            }
        })
        .catch(() => alert('Terjadi kesalahan koneksi server.'));
    }
}

function updatePassword(username, newPassword) {
      fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=reset_update&username=${username}&new_password=${newPassword}`
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            showForm('login');
        }
    });
}

window.showForm = showForm;
window.showResetForm = showResetForm;
// LOGIKA UTAMA 
document.addEventListener('DOMContentLoaded', () => {
    showForm('login');

    // REGISTRASI
    document.getElementById('register-form').addEventListener('submit', handleRegister);

    function handleRegister(e) {
        e.preventDefault();
        
        const username = document.getElementById('reg-username').value.trim();
        const password = document.getElementById('reg-password').value;
        const confirmPass = document.getElementById('reg-confirm-password').value;

        if (password !== confirmPass) {
            alert('Konfirmasi password tidak cocok!');
            return;
        }

        // KIRIM DATA KE PHP (DB)
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=register&username=${username}&password=${password}`
        })
        .then(res => res.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                showForm('login');
            }
        })
        .catch(() => alert('Terjadi kesalahan koneksi saat registrasi.'));
    }

    //LOGIN
    document.getElementById('login-form').addEventListener('submit', handleLogin);

    function handleLogin(e) {
        e.preventDefault();

        const username = document.getElementById('login-username').value.trim();
        const password = document.getElementById('login-password').value;

        // KIRIM DATA KE PHP (DB)
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&username=${username}&password=${password}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                //bersihkan sesi lama sebelum menetapkan yang baru
                localStorage.clear(); 

                // SIMPAN ID DAN USERNAME
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('currentUsername', data.username);
                localStorage.setItem('currentUserId', data.user_id); 
                
                window.location.href = 'dashboard.php'; // ALihkan ke .php!
            }
        })
        .catch(() => alert('Terjadi kesalahan koneksi saat login.'));
    }
});