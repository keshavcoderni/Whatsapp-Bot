<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    http_response_code(403);
    die("Unauthorized");
}

require_once __DIR__ . '/../config/env.php';
$imageId = $_GET['id'] ?? ''; //[cite: 3]

if(empty($imageId) || !preg_match('/^[A-Za-z0-9_-]+$/', $imageId)){ //[cite: 3]
    die("No Image ID"); //[cite: 3]
}

if (ACCESS_TOKEN === '') {
    http_response_code(500);
    die("WhatsApp access token is not configured");
}

/* =========================================
   GET MEDIA URL
========================================= */

$url = "https://graph.facebook.com/v22.0/" . $imageId; //[cite: 3]

$ch = curl_init($url); //[cite: 3]

curl_setopt_array($ch, [ //[cite: 3]    
    CURLOPT_RETURNTRANSFER => true, //[cite: 3]
    CURLOPT_HTTPHEADER => [ //[cite: 3]
        "Authorization: Bearer " . ACCESS_TOKEN //[cite: 3]
    ] //[cite: 3]
]); //[cite: 3]
$response = curl_exec($ch); //[cite: 3]
curl_close($ch); //[cite: 3]

$data = json_decode($response, true); //[cite: 3]
$mediaUrl = $data['url'] ?? ''; //[cite: 3]

if(empty($mediaUrl)){ 
    // Helpful debugging fallback to show what Facebook actually responded with
    die("Unable to fetch image URL. API Response: " . htmlspecialchars($response));
    
}

/* =========================================
   DOWNLOAD IMAGE
========================================= */

$ch = curl_init($mediaUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . ACCESS_TOKEN
    ]
]);

$image = curl_exec($ch); //[cite: 3]
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); //[cite: 3]
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch); //[cite: 3]

// Check if the image download actually succeeded
if ($httpCode !== 200 || empty($image)) {

    header("Content-Type: text/plain");

    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response:\n";
    echo $image;

    exit;
}

header("Content-Type: $contentType"); //[cite: 3]
echo $image; //[cite: 3]
?>
