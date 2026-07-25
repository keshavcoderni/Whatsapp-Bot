<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "infotag_bot");
if($conn->connect_error){
    echo json_encode(['success' => false]);
    exit;
}

$result = $conn->query("
    SELECT phone, COUNT(*) as count 
    FROM messages 
    WHERE type='user' AND is_read = 0 
    GROUP BY phone
");

$counts = [];
while($row = $result->fetch_assoc()){
    $counts[$row['phone']] = (int)$row['count'];
}

echo json_encode([
    'success' => true,
    'counts' => $counts
]);

$conn->close();
?>
