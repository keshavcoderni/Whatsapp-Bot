<?php
// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "infotag_bot"
);

if($conn->connect_error){
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20; // Users per page
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query
$baseQuery = "FROM users WHERE 1=1";
$params = [];
$types = "";

if($search !== '') {
    $baseQuery .= " AND (phone LIKE ? OR name LIKE ?)";
    $searchParam = "%$search%"; 
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Get total users count
$countQuery = "SELECT COUNT(*) as total $baseQuery";
$stmt = $conn->prepare($countQuery);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalUsers = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);
$stmt->close();

// Get users for current page
$query = "SELECT id, phone, name, language, state, created_at $baseQuery ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get statistics
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT language) as languages,
        COUNT(DISTINCT state) as states
    FROM users
");
$stats = $statsQuery->fetch_assoc();

// Get recent activity (last 7 days)
$recentQuery = $conn->query("
    SELECT COUNT(*) as recent 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$recentUsers = $recentQuery->fetch_assoc()['recent'];

// Handle user deletion (if implemented)
$deleteSuccess = '';
$deleteError = '';
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        $deleteSuccess = "User deleted successfully";
        // Redirect to refresh page without delete param
        header("Location: users.php?page=$page" . ($search ? "&search=" . urlencode($search) : ""));
        exit();
    } else {
        $deleteError = "Failed to delete user";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Dashboard - InfoTag</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/users.css">
    <script src="../assets/js/theme.js"></script>
</head>
<body>

<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="logo-area">
            <img src="Info-bot.png" class="bot-icon-img" alt="InfoTag Bot">
            <h2>Infotag<span>Bot</span></h2>
        </div>
        <nav>
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="tickets.php"><i class="fas fa-ticket-alt"></i> Tickets</a>
            <a href="users.php" class="active"><i class="fas fa-users"></i> Users</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chats</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="page-heading">
                <p class="eyebrow">User management</p>
                <h1>Users</h1>
                <p>View and manage people registered through your chatbot.</p>
            </div>
            <div class="top-actions">
                <a href="chats.php" class="secondary-btn"><i class="fas fa-comments"></i> Chats</a>
                <button class="theme-toggle-btn" type="button" onclick="toggleTheme()" aria-label="Toggle colour theme">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>

        <section class="stats-grid" aria-label="User statistics">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div><span>Total users</span><strong><?= number_format($totalUsers) ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-plus"></i></div>
                <div><span>Joined this week</span><strong><?= number_format($recentUsers) ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-language"></i></div>
                <div><span>Languages</span><strong><?= number_format($stats['languages'] ?? 0) ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-location-dot"></i></div>
                <div><span>States</span><strong><?= number_format($stats['states'] ?? 0) ?></strong></div>
            </div>
        </section>

    <!-- Alert Messages -->
    <?php if($deleteSuccess): ?>
    <div class="alert alert-success" id="alertMessage">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($deleteSuccess) ?></span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if($deleteError): ?>
    <div class="alert alert-error" id="alertMessage">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?= htmlspecialchars($deleteError) ?></span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Table Section -->
    <section class="table-box">
        <div class="table-header">
            <div>
                <h2>Registered users</h2>
                <p>
                <?php if($search): ?>
                    Results for “<?= htmlspecialchars($search) ?>”
                <?php else: ?>
                    Search, review, or open a user conversation.
                <?php endif; ?>
                </p>
            </div>
            <div class="table-controls">
                <?php if($users && $users->num_rows > 0): ?>
                <button class="export-btn" type="button" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <?php endif; ?>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <form method="GET" action="" id="searchForm">
                        <input type="text" 
                               id="searchInput" 
                               name="search" 
                               placeholder="Search by phone or name..."
                               value="<?= htmlspecialchars($search) ?>"
                               autocomplete="off">
                    </form>
                </div>
            </div>
        </div>

        <?php if($users && $users->num_rows > 0): ?>
        <table id="userTable">
            <thead>
                <tr>
                    <th>Phone Number</th>
                    <th>Name</th>
                    <th>Language</th>
                    <th>State</th>
                    <th>Joined Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $users->fetch_assoc()): ?>
                <tr data-user-id="<?= $row['id'] ?>">
                    <td class="phone">
                        <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($row['phone']) ?>
                    </td>
                    <td class="name">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($row['name'] ?: 'Not set') ?>
                    </td>
                    <td>
                        <span class="language">
                            <i class="fas fa-language"></i> <?= strtoupper(htmlspecialchars($row['language'] ?: 'en')) ?>
                        </span>
                    </td>
                    <td>
                        <span class="state">
                            <i class="fas fa-map-pin"></i> <?= htmlspecialchars($row['state'] ?: 'Unknown') ?>
                        </span>
                    </td>
                    <td class="date">
                        <i class="far fa-calendar-alt"></i> 
                        <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="chats.php?phone=<?= urlencode($row['phone']) ?>" class="view-btn" title="View Chat">
                                <i class="fas fa-comment-dots"></i> Chat
                            </a>
                            <button onclick="deleteUser(<?= $row['id'] ?>)" class="delete-btn" title="Delete User">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
            <a href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if($startPage > 1): ?>
            <a href="?page=1<?= $search ? '&search='.urlencode($search) : '' ?>">1</a>
            <?php if($startPage > 2): ?> <span>...</span> <?php endif; ?>
            <?php endif;
            
            for($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor;
            
            if($endPage < $totalPages): ?>
            <?php if($endPage < $totalPages - 1): ?> <span>...</span> <?php endif; ?>
            <a href="?page=<?= $totalPages ?><?= $search ? '&search='.urlencode($search) : '' ?>"><?= $totalPages ?></a>
            <?php endif; ?>
            
            <?php if($page < $totalPages): ?>
            <a href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php else: ?>
            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <p>No users found</p>
            <?php if($search): ?>
            <p style="font-size: 13px; margin-top: 8px;">Try a different search term or <a href="users.php" style="color: #3b82f6;">clear search</a></p>
            <?php else: ?>
            <p style="font-size: 13px; margin-top: 8px;">When users register through the chatbot, they'll appear here</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

        <footer class="footer">
            Showing <?= $users->num_rows ?> of <?= $totalUsers ?> users · Page <?= $page ?> of <?= max(1, $totalPages) ?>
        </footer>
    </main>
</div>

<script src="../assets/js/infousers.js"></script>

</body>
</html>
