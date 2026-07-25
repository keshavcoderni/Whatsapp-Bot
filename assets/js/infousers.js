// Search with debounce
let searchTimeout;
const searchInput = document.getElementById('searchInput');
const searchForm = document.getElementById('searchForm');

if(searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchForm.submit();
        }, 500);
    });
}

// Delete user function
function deleteUser(userId) {
    if(confirm('⚠️ Warning: This will permanently delete this user and all their messages!\n\nAre you absolutely sure?')) {
        const params = new URLSearchParams(window.location.search);
        params.set('delete', userId);
        window.location.href = 'users.php?' + params.toString();
    }
}

// Theme toggle
function toggleTheme() {
    const isLight = window.InfoTagTheme ? window.InfoTagTheme.toggle() : document.body.classList.toggle('light-mode');
    
    const themeIcon = document.querySelector('.theme-toggle-btn i');
    if(themeIcon) {
        themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Initialize theme
const savedTheme = window.InfoTagTheme ? window.InfoTagTheme.current() : localStorage.getItem("theme");
if(savedTheme === "light") {
    const themeIcon = document.querySelector('.theme-toggle-btn i');
    if(themeIcon) {
        themeIcon.className = 'fas fa-sun';
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

function exportToCSV() {
    const rows = document.querySelectorAll('#userTable tbody tr');
    const csvValue = (value) => `"${String(value).replace(/"/g, '""')}"`;
    const lines = ['Phone Number,Name,Language,State,Joined Date'];

    rows.forEach((row) => {
        const value = (selector) => row.querySelector(selector)?.innerText.trim() || '';
        lines.push([
            value('.phone'), value('.name'), value('.language'), value('.state'), value('.date')
        ].map(csvValue).join(','));
    });

    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `users_export_${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F to focus search
    if((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        searchInput?.focus();
    }

    if((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'e') {
        e.preventDefault();
        exportToCSV();
    }
    
    // Escape to clear search
    if(e.key === 'Escape' && searchInput && searchInput.value) {
        searchInput.value = '';
        searchForm.submit();
    }
});

