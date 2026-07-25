<?php
// Secure session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   ADMIN LOGIN CHECK
========================= */
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

/* =========================
   DATABASE CONNECTION
========================= */
// Recommended to wrap inside a try-catch for enterprise stability
try {
    $conn = new mysqli("localhost", "root", "", "infotag_bot");
    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("System Error: Please contact an administrator."); // Friendly error obfuscation
}

/* =======================================
   1. FETCH BOT STATUS FOR THE TOGGLE SWITCH
======================================= */
$botStatus = 'ONLINE';
$statusCheck = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bot_status'");
if ($statusCheck && $row = $statusCheck->fetch_assoc()) {
    $botStatus = strtoupper($row['setting_value']);
}

/* =======================================
   2. FETCH LAST 7 DAYS ANALYTICS FOR CHART
======================================= */
$chartDays = [];
$chartCounts = [];
// Queries the count of messages grouped by day for the last 7 days
$chartQuery = "
    SELECT DATE(created_at) as date_label, COUNT(*) as msg_count 
    FROM messages 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
";
$chartResult = $conn->query($chartQuery);
if ($chartResult) {
    while ($row = $chartResult->fetch_assoc()) {
        $chartDays[] = date('D (d M)', strtotime($row['date_label']));
        $chartCounts[] = (int)$row['msg_count'];
    }
}
// Fallback if data is empty so the chart doesn't break
if(empty($chartDays)) {
    $chartDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $chartCounts = [0, 0, 0, 0, 0, 0, 0];
}

/* =========================
   OPTIMIZED DASHBOARD STATS
========================= */
$totalMessages = 0;
$totalUsers = 0;
$totalTickets = 0;
$unreadMessages = 0;    
$openTickets = 0;

// Optimized approach: Querying structured application telemetry indexes 
// If tables grow past 100k rows, consider creating a dedicated cached 'system_stats' table.
$statsQuery = "
    SELECT 
        COUNT(CASE WHEN msg_source = 'messages' THEN 1 END) as total_messages,
        COUNT(CASE WHEN msg_source = 'users' THEN 1 END) as total_users,
        COUNT(CASE WHEN msg_source = 'tickets' THEN 1 END) as total_tickets,
        SUM(CASE WHEN msg_source = 'messages' AND is_read = 0 AND type = 'user' THEN 1 ELSE 0 END) as unread_messages,
        SUM(CASE WHEN msg_source = 'tickets' AND status != 'closed' THEN 1 ELSE 0 END) as open_tickets
    FROM (
        SELECT 'messages' as msg_source, is_read, type, NULL as status FROM messages
        UNION ALL
        SELECT 'users' as msg_source, NULL, NULL, NULL FROM users
        UNION ALL
        SELECT 'tickets' as msg_source, NULL, NULL, status FROM tickets
    ) as combined_telemetry
";

$statsResult = $conn->query($statsQuery);
if ($statsResult && $row = $statsResult->fetch_assoc()) {
    $totalMessages   = (int)$row['total_messages'];
    $totalUsers      = (int)$row['total_users'];
    $totalTickets    = (int)$row['total_tickets'];
    $unreadMessages  = (int)$row['unread_messages'];
    $openTickets     = (int)$row['open_tickets'];
}

/* =========================
   RECENT CHATS - OPTIMIZED QUERY
========================= */
// Added error mitigation step using an explicit fallback assignment
// SQL to fetch ONLY the absolute latest message snippet for each unique phone number
$recentMessages = $conn->query("
    SELECT m1.id, m1.phone, m1.message, m1.type, m1.created_at 
    FROM messages m1
    INNER JOIN (
        SELECT phone, MAX(id) as max_id 
        FROM messages 
        GROUP BY phone
    ) m2 ON m1.phone = m2.phone AND m1.id = m2.max_id
    ORDER BY m1.id DESC 
    LIMIT 5
") ?: null;

// Get current admin name securely
$adminName = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infotag Dashboard - Admin Panel</title>
    <!-- Performance Optimization: Preloading crucial UI resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        // CRITICAL FIX: Inline theme injection prevents background screen flicker on slow connection loads
        (function() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "light") {
                document.documentElement.classList.add("light-theme-vars");
            }
        })();
    </script>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="../assets/js/theme.js"></script>
    
    <!-- Include Chart.js via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Navigation Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="logo-area">
            <h2>Infotag<span>Bot</span></h2>
            <img src="Info-bot.png" class="bot-icon-img" alt="Bot Logo">
        </div>
        
        <nav>
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="tickets.php">
                <i class="fas fa-ticket-alt"></i> Tickets
                <?php if($openTickets > 0): ?>
                    <span style="background: #ef4444; padding: 2px 8px; border-radius: 20px; font-size: 11px; margin-left: auto; color: white;">
                        <?= $openTickets ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
            <a href="chats.php">
                <i class="fas fa-comments"></i> Chats
                <?php if($unreadMessages > 0): ?>
                    <span style="background: #ef4444; padding: 2px 8px; border-radius: 20px; font-size: 11px; margin-left: auto; color: white;">
                        <?= $unreadMessages ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div class="greeting">
                <h1>Welcome back, <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p>Manage your chatbot, users, and support system from one place</p>
            </div>
            <div class="right-actions">
                <div class="live-clock" id="liveClock">--:--:--</div>
                <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle UI Theme">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Updated Stats Grid reflecting dynamic status classes -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='chats.php'">
                <div class="stat-icon purple"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <h3>Total Messages</h3>
                    <div class="stat-number"><?= number_format($totalMessages) ?></div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='users.php'">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?= number_format($totalUsers) ?></div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='tickets.php'">
                <div class="stat-icon pink"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-info">
                    <h3>Total Tickets</h3>
                    <div class="stat-number"><?= number_format($totalTickets) ?></div>
                </div>
            </div>

            <!-- Dynamic Bot Status Card -->
            <div class="stat-card status-card-wrapper <?= $botStatus === 'ONLINE' ? 'is-online' : 'is-maintenance' ?>">
                <div class="stat-icon <?= $botStatus === 'ONLINE' ? 'green' : 'pink' ?>" id="statusCardIcon"><i class="fas fa-robot"></i></div>
                <div class="stat-info">
                    <h3>Bot Status</h3>
                    <div class="status-pill" id="botStatusText">
                        <i class="fas fa-circle"></i> <?= $botStatus ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW: TWO COLUMN INTERACTIVE ROW (Analytics & Control Center) -->
        <div class="dashboard-split-row">
            <!-- 1. Visual Analytics Chart Card -->
            <div class="glass-card chart-container-card">
                <div class="section-title">
                    <span><i class="fas fa-chart-line"></i> Traffic Analytics</span>
                    <span class="section-meta">Last 7 days</span>
                </div>
                <div class="chart-wrapper">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>

            <!-- 2. Quick Actions Control Center Panel -->
            <div class="glass-card control-panel-card">
                <div class="section-title">
                    <span><i class="fas fa-sliders-h"></i> Control Center</span>
                </div>
                
                <!-- Toggle Switch Action -->
                <div class="control-action-row">
                    <div class="action-details">
                        <h4>Bot Emergency Kill-Switch</h4>
                        <p>Instantly shifts the bot to maintenance mode.</p>
                        <span class="control-status <?= $botStatus === 'ONLINE' ? 'online' : 'maintenance' ?>" id="controlStatusLabel">
                            <?= $botStatus === 'ONLINE' ? 'Live and responding' : 'Maintenance active' ?>
                        </span>
                    </div>
                    <label class="switch-toggle">
                        <input type="checkbox" id="killSwitchCheckbox" <?= $botStatus === 'ONLINE' ? 'checked' : '' ?> onchange="toggleBotStatus(this)">
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <hr class="control-divider">

                <!-- System Broadcast Action -->
                <div class="control-action-field">
                    <h4>System Broadcast Alert</h4>
                    <p>Blast an announcement message to all active user sessions.</p>
                    <div class="input-action-group">
                        <input type="text" id="broadcastInput" placeholder="Type global announcement here...">
                        <button class="broadcast-submit-btn" onclick="sendBroadcastAlert()">
                            <i class="fas fa-paper-plane"></i> Blast
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="section-title">
                <span><i class="fas fa-history"></i> Recent Messages</span>
                <a href="chats.php" class="small-link">View All Chats <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="recent-chats-list">
                <?php if($recentMessages && $recentMessages->num_rows > 0): ?>
                    <?php while($row = $recentMessages->fetch_assoc()): 
                        $phone = htmlspecialchars($row['phone'], ENT_QUOTES, 'UTF-8');
                        $rawMessage = $row['message'];
                        
                        $truncated = mb_strlen($rawMessage, 'UTF-8') > 60 ? mb_substr($rawMessage, 0, 60, 'UTF-8') . '...' : $rawMessage;
                        $messagePreview = htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8');
                        
                        $time = date('d M, h:i A', strtotime($row['created_at']));
                        // Grabs the last 2 digits of the phone number to make individual chat rows uniquely scannable
                        $avatar = htmlspecialchars(substr($phone, -2), ENT_QUOTES, 'UTF-8');
                        $typeClass = htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="chat-row">
                        <div class="chat-avatar"><?= $avatar ?></div>
                        <div class="chat-details">
                            <div class="phone"><i class="fas fa-phone-alt" style="font-size: 11px;"></i> <?= $phone ?></div>
                            <div class="msg-preview"><?= $messagePreview ?></div>
                            <span class="msg-type <?= $typeClass ?>">
                                <i class="fas <?= $row['type'] == 'user' ? 'fa-user' : 'fa-user-tie' ?>"></i>
                                <?= ucfirst($typeClass) ?>
                            </span>
                         </div>
                        <div class="chat-meta">
                        <div class="chat-time"><i class="far fa-clock"></i> <?= $time ?></div>
                            <a class="chat-open-link" href="chats.php?phone=<?= urlencode($phone) ?>" aria-label="Open chat with <?= $phone ?>">
                                Open <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <p>No messages yet</p>
                        <p style="font-size: 13px; margin-top: 8px;">Messages will appear here once users start chatting</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        <footer>
          <div class="container">
            <p>© 2025 <span>Infotag Admin</span> — Built with precision · Jaipur, India 🇮🇳</p>
          </div>
        </footer>
    </main>
</div>

<!-- Send PHP array values safely into JS ecosystem variables before loading your framework script -->
<script>
    const chartLabels = <?= json_encode($chartDays) ?>;
    const chartDataValues = <?= json_encode($chartCounts) ?>;
</script>
<script src="../assets/js/infodashboard.js"></script>

</body>
</html>
