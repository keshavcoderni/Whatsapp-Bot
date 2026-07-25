<?php
// Enable error reporting for development (disable in production)
include '../functions/whatsapp.php';
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

// Set charset to prevent encoding issues
$conn->set_charset("utf8mb4");

// Initialize variables
$success = '';
$error = '';

/* =========================
   DELETE TICKET (SECURE)
========================= */

if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $id = intval($_GET['delete']);
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()){
        $success = "Ticket deleted successfully";
    } else {
        $error = "Failed to delete ticket";
    }
    $stmt->close();
    
    header("Location: tickets.php" . (isset($error) ? "?error=" . urlencode($error) : "?success=" . urlencode($success)));
    exit();
}

/* =========================
   CLOSE TICKET (SECURE)
========================= */

if(isset($_GET['close']) && is_numeric($_GET['close'])){

    $id = intval($_GET['close']);

    // Get ticket information
    $stmt = $conn->prepare("
        SELECT ticket_id, phone
        FROM tickets
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i",$id);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){

        $ticket = $result->fetch_assoc();

        $phone = $ticket['phone'];
        $ticketId = $ticket['ticket_id'];

        // Close ticket
        $update = $conn->prepare("
            UPDATE tickets
            SET status='closed'
            WHERE id=?
        ");

        $update->bind_param("i",$id);

        if($update->execute()){

            // WhatsApp message
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' =>
                    "✅ Complaint Resolved Successfully\n\n".
                    "🎫 Complaint ID: {$ticketId}\n\n".
                    "Your issue has been marked as solved by our support team.\n\n".
                    "If you still face any problem, simply contact support again.\n\n".
                    "Thank you for choosing InfoTag ❤️"
                ]
            ];

            sendMessage($payload);

            $success = "Ticket solved and customer notified.";
        }else{
            $error = "Failed to close ticket.";
        }

        $update->close();
    }

    $stmt->close();

    header("Location: tickets.php?success=" . urlencode($success));
    exit();
}

/* =========================
   FETCH TICKETS WITH OPTIMIZED QUERIES
========================= */

// Get all tickets with proper ordering
$tickets = $conn->query("
    SELECT id, ticket_id, phone, issue_type, status, created_at 
    FROM tickets 
    ORDER BY 
        CASE WHEN status != 'closed' THEN 0 ELSE 1 END,
        id DESC
");

if(!$tickets){
    die("Query failed: " . $conn->error);
}

// Get statistics in a single query for better performance
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status != 'closed' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM tickets
");

$stats = $statsQuery->fetch_assoc();
$totalTickets = $stats['total'] ?? 0;
$openTickets = $stats['open'] ?? 0;
$closedTickets = $stats['closed'] ?? 0;

// Get success/error messages
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Dashboard - InfoTag</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tickets.css">
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
            <a href="tickets.php" class="active"><i class="fas fa-ticket-alt"></i> Tickets</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
            <a href="chats.php"><i class="fas fa-comments"></i> Chats</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="page-heading">
                <p class="eyebrow">Support management</p>
                <h1>Tickets</h1>
                <p>Review customer requests and keep support work on track.</p>
            </div>
            <div class="top-actions">
                <a href="chats.php" class="secondary-btn"><i class="fas fa-comments"></i> Chats</a>
                <button class="theme-toggle-btn" type="button" onclick="toggleTheme()" aria-label="Toggle colour theme"><i class="fas fa-moon"></i></button>
            </div>
        </header>

        <section class="stats-grid" aria-label="Ticket statistics">
            <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-ticket-alt"></i></div><div><span>Total tickets</span><strong><?= number_format($totalTickets) ?></strong></div></div>
            <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div><span>Open tickets</span><strong><?= number_format($openTickets) ?></strong></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><span>Resolved tickets</span><strong><?= number_format($closedTickets) ?></strong></div></div>
        </section>

    <!-- Alert Messages -->
    <?php if($success): ?>
    <div class="alert alert-success" id="alertMessage">
        <i class="fas fa-check-circle"></i>
        <span><?= $success ?></span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div class="alert alert-error" id="alertMessage">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?= $error ?></span>
        <button onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Table Section -->
    <section class="table-box">
        <div class="table-header">
            <div>
                <h2>All support requests</h2>
                <p>Search, resolve, or remove customer tickets.</p>
            </div>
            <div class="table-controls">
                <?php if($tickets && $tickets->num_rows > 0): ?>
                <button class="export-btn" type="button" onclick="exportToCSV()"><i class="fas fa-download"></i> Export CSV</button>
                <?php endif; ?>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by ID, phone, or issue...">
                </div>
            </div>
        </div>

        <?php if($tickets && $tickets->num_rows > 0): ?>
        <table id="ticketTable">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Phone Number</th>
                    <th>Issue Type</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $tickets->fetch_assoc()): 
                    $statusClass = strtolower($row['status']);
                ?>
                <tr>
                    <td class="ticket-id">
                        <i class="fas fa-hashtag"></i> <?= htmlspecialchars($row['ticket_id']) ?>
                    </td>
                    <td class="phone">
                        <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($row['phone']) ?>
                    </td>
                    <td class="issue">
                        <i class="fas fa-question-circle"></i> <?= htmlspecialchars($row['issue_type']) ?>
                    </td>
                    <td>
                        <span class="status <?= $statusClass ?>">
                            <?= strtoupper($row['status']) ?>
                        </span>
                    </td>
                    <td class="date">
                        <i class="far fa-calendar-alt"></i> 
                        <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if($row['status'] != 'closed'): ?>
                            <a href="?close=<?= $row['id'] ?>" 
                               class="solve-btn"
                               onclick="return confirm('Mark this ticket as solved?')">
                                <i class="fas fa-check"></i> Solve
                            </a>
                            <?php endif; ?>
                            <a href="?delete=<?= $row['id'] ?>" 
                               class="delete-btn"
                               onclick="return confirm('⚠️ Warning: This will permanently delete this ticket!\nAre you sure?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No tickets found</p>
            <p class="empty-state-detail">When customers submit support tickets, they'll appear here</p>
        </div>
        <?php endif; ?>
    </section>

        <footer class="footer">Total tickets: <?= number_format($totalTickets) ?> · Last updated: <?= date('h:i A') ?></footer>
    </main>
</div>

<script src="../assets/js/infotickets.js"></script>

</body>
</html>
