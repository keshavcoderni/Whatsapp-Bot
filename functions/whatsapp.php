
<?php

/* =========================================
   INFOTAG APP SUPPORT BOT
========================================= */


/* =========================================
   CONFIG
========================================= */

require_once __DIR__ . '/../config/env.php';

define(
    'GRAPH_API_VERSION',
    'v22.0'
);

define(
    'GRAPH_API_BASE',   
    'https://graph.facebook.com'
);

define(
    'LOG_FILE',
    __DIR__ . '/log.txt'
);



/* =========================================
   SEND MESSAGE
========================================= */

function sendMessage($payload)
{
    if (ACCESS_TOKEN === '' || PHONE_NUMBER_ID === '') {
        error_log('WhatsApp credentials are not configured.');
        return false;
    }

    $url =

    GRAPH_API_BASE .

    '/' .

    GRAPH_API_VERSION .

    '/' .

    PHONE_NUMBER_ID .

    '/messages';



    $headers =

    "Content-Type: application/json\r\n" .

    "Authorization: Bearer " .

    ACCESS_TOKEN .

    "\r\n";



    $options = [

        'http' => [

            'method' => 'POST',

            'header' => $headers,

            'content' => json_encode($payload),

            'ignore_errors' => true
        ]
    ];



    $context =
    stream_context_create($options);



    $response =
    file_get_contents(

        $url,

        false,

        $context
    );



    file_put_contents(

        __DIR__ . '/debug.txt',

        $response
    );



    return $response;
}



/* =========================================
   SHOW TYPING INDICATOR
========================================= */

function sendTypingIndicator($phone)
{
    if (ACCESS_TOKEN === '' || PHONE_NUMBER_ID === '') {
        error_log('WhatsApp credentials are not configured.');
        return false;
    }

    $url =

    GRAPH_API_BASE .

    '/' .

    GRAPH_API_VERSION .

    '/' .

    PHONE_NUMBER_ID .

    '/messages';



    $payload = [

        "messaging_product" => "whatsapp",

        "recipient_type" => "individual",

        "to" => $phone,

        "type" => "action",

        "action" => [

            "typing" => "on"
        ]
    ];



    $headers =

    "Content-Type: application/json\r\n" .

    "Authorization: Bearer " .

    ACCESS_TOKEN .

    "\r\n";



    $options = [

        'http' => [

            'method' => 'POST',

            'header' => $headers,

            'content' => json_encode($payload),

            'ignore_errors' => true
        ]
    ];



    $context =
    stream_context_create($options);



    file_get_contents(
        $url,
        false,
        $context
    );
}



/* =========================================
   MARK MESSAGE AS READ
========================================= */

function markAsRead($messageId)
{
    if (ACCESS_TOKEN === '' || PHONE_NUMBER_ID === '' || $messageId === '') {
        error_log('WhatsApp credentials or message ID are missing.');
        return false;
    }

    $url =

    GRAPH_API_BASE .

    '/' .

    GRAPH_API_VERSION .

    '/' .

    PHONE_NUMBER_ID .

    '/messages';



    $payload = [

        "messaging_product" => "whatsapp",

        "status" => "read",

        "message_id" => $messageId
    ];



    $headers =

    "Content-Type: application/json\r\n" .

    "Authorization: Bearer " .

    ACCESS_TOKEN .

    "\r\n";



    $options = [

        'http' => [

            'method' => 'POST',

            'header' => $headers,

            'content' => json_encode($payload),

            'ignore_errors' => true
        ]
    ];



    $context =
    stream_context_create($options);



    file_get_contents(
        $url,
        false,
        $context
    );
}

?>
