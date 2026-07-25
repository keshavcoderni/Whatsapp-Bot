// toggle password visibility
    function togglePassword() {
        const pwd = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('loginBtn');
        btn.classList.add('loading');
        btn.innerHTML = '<span><i class="fas fa-circle-notch fa-spin"></i> Verifying…</span>';
    });

    // remember me (localStorage)
    const remember = document.getElementById('remember');
    const adminInput = document.getElementById('admin_id');

    if (localStorage.getItem('infotag_remember')) {
        adminInput.value = localStorage.getItem('infotag_remember');
        remember.checked = true;
    }

    remember.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('infotag_remember', adminInput.value);
        } else {
            localStorage.removeItem('infotag_remember');
        }
    });

    adminInput.addEventListener('input', function() {
        if (remember.checked) {
            localStorage.setItem('infotag_remember', this.value);
        }
    });

    // forgot password handler
    document.getElementById('forgotBtn').addEventListener('click', function(e) {
        e.preventDefault();
        alert('🔐 Admin password reset requires manual authorization.\nContact IT Operations: admin@infotag.com');
    });

    // auto-dismiss alerts after 6s
    setTimeout(() => {
        const alertEl = document.getElementById('errorAlert');
        if (alertEl) {
            alertEl.style.transition = 'opacity 0.4s';
            alertEl.style.opacity = '0';
            setTimeout(() => alertEl.remove(), 400);
        }
    }, 6000);