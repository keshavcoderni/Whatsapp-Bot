<?php

require_once __DIR__ . '/../config/env.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "infotag_bot");

if($conn->connect_error){
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}



/* =========================================
   INPUT VALIDATION
========================================= */

$phone = $_POST['phone'] ?? '';
$message = trim($_POST['message'] ?? '');

if(empty($phone) || empty($message)){
    echo json_encode(['success' => false, 'error' => 'Phone number and message are required']);
    exit;
}

// Validate phone number (basic validation)
if(!preg_match('/^\d+$/', $phone)){
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format']);
    exit;
}

// Limit message length (WhatsApp has limits)
if(strlen($message) > 4096){
    $message = substr($message, 0, 4093) . '...';
}

/* =========================================
   SEND WHATSAPP MESSAGE
========================================= */

$url = "https://graph.facebook.com/v22.0/" . PHONE_NUMBER_ID . "/messages";

$payload = [
    'messaging_product' => 'whatsapp',
    'to' => $phone,
    'type' => 'text',
    'text' => ['body' => $message]
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . ACCESS_TOKEN
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle cURL errors
if($curlError){
    echo json_encode(['success' => false, 'error' => "cURL Error: {$curlError}"]);
    exit;
}

// Parse WhatsApp API response
$responseData = json_decode($response, true);

// Check if message was sent successfully
if($httpCode !== 200 || isset($responseData['error'])){
    $errorMsg = $responseData['error']['message'] ?? "WhatsApp API error (HTTP {$httpCode})";
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

/* =========================================
   SAVE MESSAGE TO DATABASE
========================================= */

$stmt = $conn->prepare("INSERT INTO messages (phone, message, type, is_read, created_at) VALUES (?, ?, ?, 1, NOW())");

if(!$stmt){
    echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
    exit;
}

$type = 'admin';
$stmt->bind_param("sss", $phone, $message, $type);

if($stmt->execute()){
    $messageId = $stmt->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save message: ' . $stmt->error]);
}

$stmt->close();
$conn->close();

?>
