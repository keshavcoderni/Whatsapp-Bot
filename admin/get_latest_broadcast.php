<?php
// Start session to remember which broadcasts the user has already seen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "", "infotag_bot");

if ($conn->connect_error) {
    exit(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Check what the last broadcast ID this specific user saw was. Default to 0 if new.
$lastSeenId = isset($_SESSION['last_seen_broadcast_id']) ? (int)$_SESSION['last_seen_broadcast_id'] : 0;

// Fetch the newest broadcast that has an ID greater than the one the user last saw
$stmt = $conn->prepare("SELECT id, message FROM system_broadcasts WHERE id > ? ORDER BY id ASC LIMIT 1");

if ($stmt) {
    $stmt->bind_param("i", $lastSeenId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update the user's session so they don't get spammed with the same message every 5 seconds
        $_SESSION['last_seen_broadcast_id'] = $row['id'];
        
        echo json_encode(['success' => true, 'message' => $row['message']]);
    } else {
        // No new messages
        echo json_encode(['success' => true, 'message' => null]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Query preparation failed']);
}
?>