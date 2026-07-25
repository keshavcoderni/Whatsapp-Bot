<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_login'])) exit(json_encode(['success' => false, 'error' => 'Unauthorized Access']));

$conn = new mysqli("localhost", "root", "", "infotag_bot");
$status = isset($_POST['status']) && $_POST['status'] === 'MAINTENANCE' ? 'MAINTENANCE' : 'ONLINE';

$stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'bot_status'");
$stmt->bind_param("s", $status);
$success = $stmt->execute();

echo json_encode(['success' => $success]);