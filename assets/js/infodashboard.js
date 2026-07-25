// Live System Clock logic
function updateClock() {
    const now = new Date();
    const options = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
    const element = document.getElementById("liveClock");
    if (element) element.innerHTML = now.toLocaleTimeString('en-US', options);
}
setInterval(updateClock, 1000);
updateClock();

// UI Elements configuration shortcuts
const mobileToggle = document.getElementById("mobileToggle");
const sidebar = document.querySelector(".sidebar");
const overlay = document.getElementById("sidebarOverlay");

if(mobileToggle && sidebar && overlay) {
    const toggleMenu = () => {
        sidebar.classList.toggle("active");
        overlay.classList.toggle("active");
        document.body.style.overflow = sidebar.classList.contains("active") ? "hidden" : "";
    };

    mobileToggle.addEventListener("click", toggleMenu);
    overlay.addEventListener("click", toggleMenu);
}

// Global variable to hold the chart instance so we can update it
let trafficChartInstance = null;

// Optimized Theme Swapping utilizing unified CSS Var mapping classes
function toggleTheme() {
    const isLightNow = window.InfoTagTheme ? window.InfoTagTheme.toggle() : document.documentElement.classList.toggle('light-theme-vars');
    updateThemeIcon(isLightNow);
    
    // NEW: Update Chart colors dynamically when theme changes
    if (trafficChartInstance) {
        const gridColor = isLightNow ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';
        const textColor = isLightNow ? '#475569' : '#94a3b8';
        
        trafficChartInstance.options.scales.x.grid.color = gridColor;
        trafficChartInstance.options.scales.x.ticks.color = textColor;
        trafficChartInstance.options.scales.y.grid.color = gridColor;
        trafficChartInstance.options.scales.y.ticks.color = textColor;
        trafficChartInstance.update();
    }
}

function updateThemeIcon(isLight) {
    const icon = document.querySelector('#themeToggleBtn i');
    if (icon) icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
}

// Syncing matching configuration variables for Icons upon UI frame load completion
document.addEventListener("DOMContentLoaded", () => {
    const isLight = document.documentElement.classList.contains("light-theme-vars");
    updateThemeIcon(isLight);
    
    /* =======================================
       1. CHART.JS INITIALIZATION ARCHITECTURE
    ======================================= */
    const ctx = document.getElementById('trafficChart');
    if (ctx) {
        const gridColor = isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';
        const textColor = isLight ? '#475569' : '#94a3b8';

        trafficChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels, // Pulled from PHP
                datasets: [{
                    label: 'Messages Exchanged',
                    data: chartDataValues, // Pulled from PHP
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#8b5cf6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        titleFont: { family: 'Inter', size: 13, weight: '700' },
                        bodyFont: { family: 'Inter', size: 12 }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { family: 'Inter' } } },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { family: 'Inter' }, precision: 0 },
                        beginAtZero: true
                    }
                }
            }
        });
    }
});

// Dynamic Route Keys
document.addEventListener('keydown', function(e) {
    if(e.altKey) {
        const routes = { d: 'dashboard.php', c: 'chats.php', u: 'users.php', t: 'tickets.php' };
        const route = routes[e.key.toLowerCase()];
        if(route) {
            e.preventDefault();
            window.location.href = route;
        }
    }
});

/* =======================================
   2. EMERGENCY KILL-SWITCH AJAX LOGIC
======================================= */
function toggleBotStatus(checkbox) {
    const statusText = document.getElementById("botStatusText");
    const statusIcon = document.getElementById("statusCardIcon");
    const statusCard = document.querySelector(".status-card-wrapper");
    const controlStatusLabel = document.getElementById("controlStatusLabel");
    const newStatus = checkbox.checked ? 'ONLINE' : 'MAINTENANCE';

    if (newStatus === 'ONLINE') {
        statusText.innerHTML = `<i class="fas fa-circle"></i> ONLINE`;
        statusIcon.className = "stat-icon green";
        statusCard?.classList.add("is-online");
        statusCard?.classList.remove("is-maintenance");
        if (controlStatusLabel) {
            controlStatusLabel.textContent = "Live and responding";
            controlStatusLabel.className = "control-status online";
        }
    } else {
        statusText.innerHTML = `<i class="fas fa-circle"></i> MAINTENANCE`;
        statusIcon.className = "stat-icon pink";
        statusCard?.classList.add("is-maintenance");
        statusCard?.classList.remove("is-online");
        if (controlStatusLabel) {
            controlStatusLabel.textContent = "Maintenance active";
            controlStatusLabel.className = "control-status maintenance";
        }
    }

    fetch('update_bot_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `status=${newStatus}`
    })
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error("Server Error: " + text); // Exposes PHP errors
        }
    })
    .then(data => {
        if(!data.success) alert("Failed to save: " + (data.error || "Unknown error"));
    })
    .catch(err => {
        console.error(err);
        alert(err.message);
    });
}

/* =======================================
   3. SYSTEM BROADCAST API ACTION
======================================= */
function sendBroadcastAlert() {
    const input = document.getElementById("broadcastInput");
    const message = input.value.trim();

    if (!message) {
        alert("Please type an alert string phrase before launching broadcast alerts.");
        return;
    }

    fetch('send_broadcast.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message=${encodeURIComponent(message)}`
    })
    .then(async res => {
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error("Server Error: " + text); // Exposes PHP errors
        }
    })
    .then(data => {
        if(data.success) {
            alert("Broadcast alert launched successfully!");
            input.value = ""; 
        } else {
            alert("System error firing broadcast: " + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert(err.message);
    });
}
