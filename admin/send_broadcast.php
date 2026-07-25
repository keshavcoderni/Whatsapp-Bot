<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_login'])) exit(json_encode(['success' => false, 'error' => 'Unauthorized Access']));

require_once __DIR__ . '/../config/db.php';

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
if (empty($message)) exit(json_encode(['success' => false, 'error' => 'Empty message']));

$successCount = 0;

// 1. Get every unique phone number
$users = $conn->query("SELECT DISTINCT phone FROM messages");

if ($users && $users->num_rows > 0) {
    $stmt = $conn->prepare("INSERT INTO messages (phone, message, type, is_read, created_at) VALUES (?, ?, 'admin', 0, NOW())");
    
    // 2. Loop through every user
    while ($row = $users->fetch_assoc()) {
        $phone = $row['phone'];
        $stmt->bind_param("ss", $phone, $message);
        
        if ($stmt->execute()) {
            // 3. THE MISSING LINK: Actually trigger the WhatsApp API to send the text
            $apiResult = sendWhatsAppMessage($phone, $message);
            
            if ($apiResult) {
                $successCount++;
            }
        }
    }
    
    echo json_encode(['success' => true, 'notified' => $successCount]);
} else {
    echo json_encode(['success' => false, 'error' => 'No users found.']);
}

/* =======================================
   WHATSAPP API DISPATCHER FUNCTION
======================================= */
/* =======================================
   WHATSAPP API DISPATCHER FUNCTION
======================================= */
function sendWhatsAppMessage($phoneNumber, $messageText) {
    // 1. Load your credentials
    require_once __DIR__ . '/../config/env.php';
    
    // 2. Construct the official Meta API URL using your defined PHONE_NUMBER_ID constant.
    // (Ensure 'v19.0' or 'v20.0' matches your active Meta App API version)
    $url = "https://graph.facebook.com/v19.0/" . PHONE_NUMBER_ID . "/messages";
    
    // 3. Official Meta WhatsApp Cloud API Payload Structure
    $data = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $phoneNumber,
        "type" => "text",
        "text" => [
            "preview_url" => false,
            "body" => $messageText
        ]
    ];
    
    // 4. Initialize cURL with the dynamic URL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // 5. Inject the Authorization header using your defined ACCESS_TOKEN constant
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json', 
        'Authorization: Bearer ' . ACCESS_TOKEN
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // 6. Execute and evaluate the response
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Returns true if Meta accepted it (HTTP 200 OK), false otherwise
    return ($httpcode == 200);
}
