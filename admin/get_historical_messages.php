<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$phone = trim($_GET['phone'] ?? '');
$beforeId = (int)($_GET['before_id'] ?? 0);
$limit = 50;

if ($phone === '' || $beforeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM (
        SELECT id, message, type, created_at
        FROM messages
        WHERE phone = ? AND id < ?
        ORDER BY id DESC
        LIMIT ?
    ) older_messages
    ORDER BY id ASC
");
$stmt->bind_param("sii", $phone, $beforeId, $limit);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

$hasMore = false;
if (!empty($messages)) {
    $oldestId = (int)$messages[0]['id'];
    $check = $conn->prepare("SELECT id FROM messages WHERE phone = ? AND id < ? LIMIT 1");
    $check->bind_param("si", $phone, $oldestId);
    $check->execute();
    $hasMore = $check->get_result()->num_rows > 0;
    $check->close();
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'has_more' => $hasMore
]);

$conn->close();

?>
