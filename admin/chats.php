<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict'
    ]);
}

if(!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true){
    header("Location: login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$conn = new mysqli("localhost", "root", "", "infotag_bot");
if($conn->connect_error){
    die(json_encode(['error' => 'Database Connection Failed']));
}
$conn->set_charset("utf8mb4");

$selectedPhone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

if($selectedPhone !== ''){
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE phone = ? AND type = 'user' AND is_read = 0");
    $stmt->bind_param("s", $selectedPhone);
    $stmt->execute();
    $stmt->close();
}

$users = $conn->query("
    SELECT u.phone, u.last_time, u.unread_count 
    FROM (
        SELECT phone, MAX(created_at) as last_time,
               SUM(CASE WHEN is_read = 0 AND type = 'user' THEN 1 ELSE 0 END) as unread_count
        FROM messages
        GROUP BY phone
    ) AS u
    ORDER BY u.last_time DESC
    LIMIT 100
");

if(!$users) {
    die("Query failed: " . $conn->error);
}

$messages = null;
$lastId = 0;
$hasMore = false;
$perPage = 50; 
$firstId = 0;

if($selectedPhone !== ''){
    $idStmt = $conn->prepare("SELECT MAX(id) as last_id FROM messages WHERE phone = ?");
    $idStmt->bind_param("s", $selectedPhone);
    $idStmt->execute();
    $lastId = $idStmt->get_result()->fetch_assoc()['last_id'] ?? 0;
    $idStmt->close();

    $stmt = $conn->prepare("
        SELECT * FROM (
            SELECT id, message, type, created_at
            FROM messages
            WHERE phone = ?
            ORDER BY id DESC
            LIMIT ?
        ) sub ORDER BY id ASC
    ");
    $stmt->bind_param("si", $selectedPhone, $perPage);
    $stmt->execute();
    $messages = $stmt->get_result();
    $stmt->close();
    
    $checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE phone = ?");
    $checkStmt->bind_param("s", $selectedPhone);
    $checkStmt->execute();
    $totalMsgs = $checkStmt->get_result()->fetch_assoc()['total'] ?? 0;
    $hasMore = $totalMsgs > $perPage;
    $checkStmt->close();
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    if (basename($dir) === 'admin' || basename($dir) === 'pages') {
        $dir = dirname($dir);
    }
    return rtrim($protocol . $_SERVER['HTTP_HOST'] . $dir, '/');
}
$projectRoot = getBaseUrl();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Infotag Chats - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/chats.css">
    <script src="../assets/js/theme.js"></script>
    <script>
        if (localStorage.getItem('theme') === 'light' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.add('light-mode');
        } else {
            document.documentElement.classList.remove('light-mode');
        }
    </script>
</head>
<body>

<div class="chat-container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a href="dashboard.php" class="dashboard-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <h1><i class="fas fa-comments"></i> Conversations</h1>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchUser" placeholder="Search users...">
            </div>
        </div>
        <div class="user-list" id="userList">
            <?php if($users && $users->num_rows > 0): ?>
                <?php while($user = $users->fetch_assoc()): ?>
                <a href="?phone=<?= urlencode($user['phone']) ?>" class="user <?= $selectedPhone == $user['phone'] ? 'active':'' ?>" data-phone="<?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="user-info">
                        <div class="user-phone"><?= htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="user-status"><?= date('d M h:i A', strtotime($user['last_time'])) ?></div>
                    </div>
                    <?php if($user['unread_count'] > 0): ?>
                        <div class="unread-badge"><?= (int)$user['unread_count'] ?></div>
                    <?php endif; ?>
                </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-chat">No chats found</div>
            <?php endif; ?>
        </div>
    </aside>
    
    <!-- Virtual backdrop layer for structural execution dismissals -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <main class="chat-area">
        <div class="chat-header">
            <div class="header-left">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Open Sidebar menu"><i class="fas fa-bars"></i></button>
                <h2><?= $selectedPhone !== '' ? htmlspecialchars($selectedPhone, ENT_QUOTES, 'UTF-8') : 'Select Conversation' ?></h2>
            </div>
            <div class="header-right">
                <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" title="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
                <?php if($selectedPhone !== ''): ?>
                    <div class="live">● LIVE</div>
                    <button class="delete-btn delete-chat" onclick="deleteChat()" title="Delete conversation logs"><i class="fas fa-trash"></i></button>
                    <button class="delete-btn delete-user" onclick="deleteUser()" title="Remove client metadata"><i class="fas fa-user-times"></i></button>
                <?php endif; ?>
            </div>
        </div>

        <?php if($selectedPhone !== ''): ?>
        <div class="chat-body" id="chatBody">
            <?php if($hasMore): ?> 
                <button class="load-history-btn" id="loadMoreBtn" onclick="loadOlderMessages()">Load Older Messages</button>
            <?php endif; ?>

            <div id="messageAnchor"></div>

            <?php 
            if($messages && $messages->num_rows > 0):
                while($msg = $messages->fetch_assoc()): 
                    $class = match($msg['type']) { 'user' => 'user-msg', 'admin' => 'admin-msg', default => 'bot-msg' };
                    $wrapperClass = ($msg['type'] === 'admin') ? 'admin-msg-wrapper' : '';
                    if (!isset($firstId) || $firstId === 0) { $firstId = $msg['id']; }
                    
                    $rawText = trim($msg['message']);
                    if (str_starts_with($rawText, 'IMAGE:')) {

    $imageId = trim(str_replace('IMAGE:', '', $rawText));

    
    $finalUrl = 'get_image.php?id=' . urlencode($imageId);
    $isImageUrl = true;

} else {

    $isImageUrl =
        preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $rawText) ||
        (filter_var($rawText, FILTER_VALIDATE_URL) &&   
         preg_match('/(images|uploads|attachments)/i', $rawText));

    $finalUrl = $rawText;

    if ($isImageUrl && !filter_var($rawText, FILTER_VALIDATE_URL)) {
        $finalUrl = $projectRoot . '/' . ltrim($rawText, '/');
    }
}
            ?>
                <div class="message-wrapper <?= $wrapperClass ?>" data-msg-id="<?= (int)$msg['id'] ?>">
                    <button class="msg-delete" onclick="deleteMessage(<?= (int)$msg['id'] ?>)" title="Delete message"><i class="fas fa-trash-alt"></i></button>
                    <div class="message <?= $class ?>">
                        <div class="msg-text">
                            <?php if ($isImageUrl): ?>
                                <a href="<?= htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8') ?>" class="chat-media-attachment" alt="Uploaded Content" onerror="this.onerror=null; this.parentNode.innerHTML='<i class=\'fas fa-file-image\'></i> Broken Image Link';">
                                </a>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile;
            endif; 
            ?>
        </div>

        <div class="reply-box">
            <textarea id="adminMessage" placeholder="Response... (Enter to send)"></textarea>
            <button onclick="sendReply()" id="sendBtn"><i class="fas fa-paper-plane"></i></button>
        </div>
        <?php else: ?>
        <div class="empty-chat">
            <div>
                <i class="fas fa-comments" style="font-size: 44px; opacity: 0.25; margin-bottom: 12px; display:block;"></i>
                Select an active conversation thread to begin.
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
    window.chatConfig = {
        currentPhone: <?= json_encode($selectedPhone, JSON_UNESCAPED_UNICODE) ?>,
        lastMsgId: <?= (int)$lastId ?>,
        earliestMsgId: <?= isset($firstId) ? (int)$firstId : 0 ?>,
        projectRootUrl: <?= json_encode($projectRoot, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="../assets/js/infochats.js"></script>
</body>
</html>
