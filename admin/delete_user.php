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

$conn->begin_transaction();

try {
    $tables = ['messages', 'screenshots', 'tickets', 'users'];

    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM {$table} WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
}

$conn->close();

?>
