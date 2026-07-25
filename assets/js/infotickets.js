// Client-side ticket search
const searchInput = document.getElementById("searchInput");
const ticketTable = document.getElementById("ticketTable");

if(searchInput) {
    searchInput.addEventListener("input", function() {
        let value = this.value.toLowerCase().trim();
        let rows = ticketTable?.querySelectorAll("tbody tr");
        
        if(!rows) return;
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            let isVisible = text.includes(value);
            row.style.display = isVisible ? "" : "none";
            if(isVisible) visibleCount++;
        });
        
        if(rows.length > 0)  {
            if(visibleCount === 0 && value !== '') {
                if(!document.getElementById('noResultsMsg')) {
                    const noResults = document.createElement('div');
                    noResults.id = 'noResultsMsg';
                    noResults.className = 'empty-state';
                    noResults.innerHTML = '<i class="fas fa-search"></i><p>No tickets match "' + value + '"</p>';
                    ticketTable.parentNode.appendChild(noResults);
                }
            } else {
                const noResults = document.getElementById('noResultsMsg');
                if(noResults) noResults.remove();
            }
        }
    });
}

// Theme toggle functionality
function toggleTheme() {
    const isLight = window.InfoTagTheme ? window.InfoTagTheme.toggle() : document.body.classList.toggle('light-mode');
    
    const themeIcon = document.querySelector('.theme-toggle-btn i');
    if(themeIcon) {
        themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Initialize theme from localStorage
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

// Add loading state to action buttons
document.querySelectorAll('.solve-btn, .delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const originalText = this.innerHTML;
        this.innerHTML = '<div class="loading"></div>';
        this.style.opacity = '0.7';
        
        setTimeout(() => {
            this.innerHTML = originalText;
            this.style.opacity = '1';
        }, 2000);
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F to focus search
    if((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        searchInput?.focus();
    }
    
    // Escape to clear search
    if(e.key === 'Escape' && searchInput) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
    }
});

// Export the currently visible tickets.
function exportToCSV() {
    const rows = document.querySelectorAll("#ticketTable tbody tr");
    const csvValue = (value) => `"${String(value).replace(/"/g, '""')}"`;
    const lines = ['Ticket ID,Phone Number,Issue Type,Status,Created At'];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll("td");
        if(cols.length && row.style.display !== 'none') {
            const rowData = [
                cols[0]?.innerText.trim() || '',
                cols[1]?.innerText.trim() || '',
                cols[2]?.innerText.trim() || '',
                cols[3]?.innerText.trim() || '',
                cols[4]?.innerText.trim() || ''
            ];
            lines.push(rowData.map(csvValue).join(','));
        }
    });
    
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `tickets_export_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

