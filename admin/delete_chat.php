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

$phone = trim($_POST['phone'] ?? '');

if ($phone === '') {
    echo json_encode(['success' => false, 'error' => 'Phone required']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM messages WHERE phone = ?");
$stmt->bind_param("s", $phone);
$success = $stmt->execute();

echo json_encode(["success" => $success]);

$stmt->close();
$conn->close();

?>
