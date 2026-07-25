<?php

include 'config/db.php';
include 'functions/whatsapp.php';
include 'functions/menu_db.php';
include 'functions/replies.php';

/* =========================================
   VERIFY WEBHOOK (Meta handshake)
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($token === VERIFY_TOKEN) {
        echo $challenge;
    } else {
        echo 'Verification failed';
    }
    exit;
}

/* =========================================
   HANDLE INCOMING WHATSAPP MESSAGE
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    // Extract core message objects safely
    $msg = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    // Maintain local logging for execution audits
    file_put_contents('debug.txt', print_r($msg, true));

    if (!$msg) {
        echo 'EVENT_RECEIVED';
        exit;
    }

    /* =========================================
       USER METADATA EXTRACTION
    ========================================= */
    $from = preg_replace('/\D/', '', $msg['from']);
    $name = $data['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? 'User';
    $selected = $msg['interactive']['list_reply']['id'] ?? $msg['interactive']['button_reply']['id'] ?? null;
    $text = strtolower(trim($msg['text']['body'] ?? ''));
    $messageId = $msg['id'] ?? '';
    $messageType = $msg['type'] ?? '';
    
    $payload = [];

    /* =========================================
       DEDUPLICATION CHECK (Avoid Double Processing)
    ========================================= */
    if (!empty($messageId)) {
        $stmt = $conn->prepare("SELECT id FROM messages WHERE whatsapp_message_id = ? LIMIT 1");
        $stmt->bind_param("s", $messageId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo 'EVENT_RECEIVED';
            exit;
        }
        $stmt->close();
    }

    // Capture explicit text or default to a standard fallback identifier
    $userMessage = $selected ?? $text ?? 'Message';
// Capture explicit text or default to a standard fallback identifier
    $userMessage = $selected ?? $text ?? 'Message';

    /* =========================================
       EMERGENCY KILL-SWITCH GATEKEEPER
    ========================================= */
    $botStatus = 'ONLINE'; 
    $statusCheck = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'bot_status'");

    if ($statusCheck && $row = $statusCheck->fetch_assoc()) {
        $botStatus = strtoupper($row['setting_value']);
    }

    if ($botStatus === 'MAINTENANCE') {
        
        $maintenanceText = "🛠️ *InfoTag Support is Currently Offline*\n\nOur system is undergoing quick maintenance. We will be back online shortly!";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' => $maintenanceText
            ]
        ];

        // Log the user's message so you still see it in the dashboard
        saveChat($conn, $from, $userMessage, 'user', $messageId);
        
        // Use your native functions to send the alert
        markAsRead($messageId);
        sendTypingIndicator($from);
        sendMessage($payload);
        
        // Log the bot's maintenance reply
        saveChat($conn, $from, $maintenanceText, 'bot');

        // Tell Meta the message was received and KILL the script
        echo 'EVENT_RECEIVED';
        exit;
    }

    /*
========================================= */

// Get current state BEFORE image processing
  
    /*
========================================= */

// Get current state BEFORE image processing
$stmt = $conn->prepare("SELECT state FROM users WHERE phone = ?");
$stmt->bind_param("s", $from);
$stmt->execute();
$stateData = $stmt->get_result()->fetch_assoc();
$currentState = $stateData['state'] ?? '';
$stmt->close();

if (
    ($messageType === 'image' || $messageType === 'document')
    && $currentState === 'waiting_screenshot'
) {

    $imageId = '';

    if ($messageType === 'image') {
        $imageId = $msg['image']['id'] ?? '';
    }

    if (
        $messageType === 'document'
        && str_starts_with(
            $msg['document']['mime_type'] ?? '',
            'image/'
        )
    ) {
        $imageId = $msg['document']['id'] ?? '';
    }

    if (!empty($imageId)) {

        // Save image reference in chat history
        $userMessage = 'IMAGE:' . $imageId;

        saveChat(
            $conn,
            $from,
            $userMessage,
            'user',
            $messageId
        );
 $ticket =
            'INF-' .
            date('YmdHis') .
            rand(100,999);

        // Save screenshot record
        $stmt = $conn->prepare("
            INSERT INTO screenshots
            (ticket_id, phone, image_id, status)
            VALUES (?, ?, ?, 'pending')
        ");

        $stmt->bind_param(
    "sss",
    $ticket,
    $from,
    $imageId
);
       if(!$stmt->execute()){
    die($stmt->error);
}
        $stmt->close();


        $issue = 'Screenshot Support';

        $stmt = $conn->prepare("
            INSERT INTO tickets
            (ticket_id, phone, issue_type)
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "sss",
            $ticket,
            $from,
            $issue
        );

        $stmt->execute();
        $stmt->close();

        // Reset state
        updateState(
            $conn,
            $from,
            'verified'
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' =>
                    "✅ Screenshot received successfully.\n\n" .
                    "🎫 Ticket ID: {$ticket}\n\n" .
                    "Our support team will review your issue shortly."
            ]
        ];

        markAsRead($messageId);
        sendTypingIndicator($from);
        sleep(2);
        sendMessage($payload);

        saveChat(
            $conn,
            $from,
            $payload['text']['body'],
            'bot'
        );

        echo 'EVENT_RECEIVED';
        exit;
    }
}


    /* =========================================
       STANDARD TEXT CHAT FALLBACK ROUTES
    ========================================= */
    saveChat($conn, $from, $userMessage, 'user', $messageId);
    saveUser($conn, $from, $name);

    // Read current user runtime states
    $stmt = $conn->prepare("SELECT state FROM users WHERE phone = ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $stateData = $stmt->get_result()->fetch_assoc();
    $currentState = $stateData['state'] ?? '';
    $stmt->close();

    // Verify registration metrics
    $stmt = $conn->prepare("SELECT id FROM app_users WHERE phone = ? LIMIT 1");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $isRegistered = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    /* =========================================
       ROUTING ENGINE / RESPONSE TREE
    ========================================= */
    if ($selected === null && $text === 'menu') {
        $payload = buildMenu(
    $conn,
    $from,
    'main',
    '📱Infotag Support',
    "👋 Hello $name\n\nPlease select a category:"
);
    } 
    elseif ($selected === null && $isRegistered) {
        updateState($conn, $from, 'verified');
        $appUser = getAppUser($conn, $from);
        $vehicles = "";
        $customerName = "Customer";
        $count = 1;
        $totalVehicles = $appUser->num_rows;

        while ($row = $appUser->fetch_assoc()) {
            $customerName = $row['customer_name'] ?? 'Customer';
            $vehicles .= $count . ". 🚗 " . $row['car_name'] . "\n";
            $vehicles .= "   📌 " . $row['car_number'] . "\n\n";
            $count++;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' => "👋 Welcome back, {$customerName}!\n\n✅ Your account is verified.\n\n🚘 Registered Vehicles ({$totalVehicles})\n\n" . $vehicles . "💬 Type *menu* to access support services."
            ]
        ];
    } 
    elseif ($selected === null) {
        updateState($conn, $from, 'guest');
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' => "👋 Welcome to InfoTag Support!\n\nWe're excited to help you get started with your smart QR product 🚀\n\n📱 STEP 1 — Install Our App\n\nAndroid:\nhttps://play.google.com/store/apps/details?id=com.infotagnn\n\n🍎 iOS:\nhttps://apps.apple.com/app/infotag/id1234567890\n\n🧾 STEP 2 — Create Your Account\n\n• Open the app\n• Sign up using your mobile number\n• Verify your account with OTP\n\n🛍️ STEP 3 — Purchase Your QR Product\n\nhttps://amzn.in/d/0dAsob46\n\n✅ Already Purchased?\n\nSend your Order ID or Registered Mobile Number."
            ]
        ];
    } 
    elseif ($selected === 'main_menu') {
       $payload = buildMenu(
    $conn,
    $from,
    'main',
    '📱 Infotag App Support',
    "👋 Hello $name\n\nWelcome to Infotag Support Bot\n\nPlease select a category below:"
);
    } 
    elseif ($selected === 'technical_support') {
        $payload = buildMenu(
    $conn,
    $from,
    'technical_support',
    '🛠 Technical Support',
    'Select your account issue:'
);
        updateState($conn, $from, 'technical_support');
    } 
    elseif ($selected === 'account_issues') {
        $payload = buildMenu(
    $conn,
    $from,
    'account_issues',
    '🔐 Account Support',
    'Select your account issue:'
);
        updateState($conn, $from, 'account_issues');
    } 
    elseif ($selected === 'payment_billing') {
        $payload = buildMenu(
    $conn,
    $from,
    'payment_billing',
    '💳 Payment Support',
    'Select your issue:'
);
        updateState($conn, $from, 'payment_billing');
    } 
   elseif ($selected === 'app_crash') {

    $reply = getReply($conn,'app_crash');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
} 
   elseif ($selected === 'login_issue') {

    $reply = getReply($conn,'login_issue');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
}
   elseif ($selected === 'notification_issue') {

    $reply = getReply($conn,'notification_issue');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
}
    elseif ($selected === 'payment_issue') {

    $reply = getReply($conn,'payment_issue');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
} 
    elseif ($selected === 'slow_app') {

    $reply = getReply($conn,'slow_app');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
} 
    elseif ($selected === 'forgot_password') {

    $reply = getReply($conn,'forgot_password');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
} 
    elseif ($selected === 'security_issue') {

    $reply = getReply($conn,'security_issue');

    $payload = solutionButtons(
        $from,
        $reply['title'],
        $reply['message']
    );
} 
    elseif ($selected === 'issue_not_solved') {
        updateState($conn, $from, 'waiting_screenshot');
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' => "📸 Please upload a screenshot of the issue.\n\nThis will help our support team assist you faster."
            ]
        ];
    } 
    elseif ($selected === 'contact_agent') {
        $ticket = 'INF-' . rand(1000, 9999);
        $stmt = $conn->prepare("INSERT INTO tickets (ticket_id, phone, issue_type) VALUES (?, ?, ?)");
        $issue = 'Support Request';
        $stmt->bind_param("sss", $ticket, $from, $issue);
        $stmt->execute();
        $stmt->close();

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $from,
            'type' => 'text',
            'text' => [
                'body' => "👨‍💻 Complaint ID Generated successfully\n\n🎫 Complaint ID: $ticket\n\nOur support team will contact you soon."
            ]
        ];
    } 
    elseif ($selected === 'issue_solved') {

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $from,
        'type' => 'text',
        'text' => [
            'body' =>
                "🎉 Great!\n\n" .
                "We're happy your issue has been resolved.\n\n" .
                "🙏 Thank you for contacting Infotag Support.\n\n" .
                "Type *menu* whenever you need support again."
        ]
    ];
}

    /* =========================================
       POST EXECUTION DISPATCH METRICS
    ========================================= */
    markAsRead($messageId);
    sendTypingIndicator($from);
    sleep(rand(2, 4));

    file_put_contents('payload_log.txt', print_r($payload, true) . PHP_EOL, FILE_APPEND);
    sendMessage($payload);

    $botText = $payload['text']['body'] ?? $payload['interactive']['body']['text'] ?? 'Interactive Menu';
    saveChat($conn, $from, $botText, 'bot');

    echo 'EVENT_RECEIVED';
    exit;
}

/* =========================================
   CORE FUNCTIONS
========================================= */
function getAppUser($conn, $phone) {
    $stmt = $conn->prepare("SELECT * FROM app_users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    return $stmt->get_result();
}

function saveChat($conn, $phone, $message, $type, $messageId = null) {
    $stmt = $conn->prepare("INSERT INTO messages (phone, message, type, is_read, whatsapp_message_id) VALUES (?, ?, ?, ?, ?)");
    $isRead = ($type == 'user') ? 0 : 1;
    $stmt->bind_param("sssis", $phone, $message, $type, $isRead, $messageId);
    $stmt->execute();
    $stmt->close();
}

function saveUser($conn, $phone, $name) {
    $check = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $check->bind_param("s", $phone);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO users (phone, name) VALUES (?, ?)");
        $stmt->bind_param("ss", $phone, $name);
        $stmt->execute();
        $stmt->close();
    }
    $check->close();
}

function updateState($conn, $phone, $state) {
    $stmt = $conn->prepare("UPDATE users SET state = ? WHERE phone = ?");
    $stmt->bind_param("ss", $state, $phone);
    $stmt->execute();
    $stmt->close();
}
?>