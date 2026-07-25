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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$phone = $_GET['phone'] ?? '';
$lastId = (int)($_GET['last_id'] ?? 0);

if($phone == ''){
    echo json_encode(['success' => false, 'error' => 'Phone required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE phone = ? AND id > ? 
    ORDER BY id ASC
");
$stmt->bind_param("si", $phone, $lastId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while($row = $result->fetch_assoc()){
    $messages[] = $row;
}

echo json_encode([
    'success' => true,
    'messages' => $messages
]);

$stmt->close();
$conn->close();
?>
