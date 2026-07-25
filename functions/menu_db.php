<?php

function buildMenu($conn, $to, $parentMenu, $header, $body)
{
    $stmt = $conn->prepare(
        "SELECT * FROM menu_items
         WHERE parent_menu = ?
         ORDER BY sort_order"
    );

    $stmt->bind_param("s", $parentMenu);
    $stmt->execute();

    $result = $stmt->get_result();

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => $row['item_id'],
            'title' => $row['title'],
            'description' => $row['description']
        ];
    }

    return [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'interactive',
        'interactive' => [

    'type' => 'list',

    'header' => [
        'type' => 'text',
        'text' => $header
    ],

    'body' => [
        'text' => $body
    ],

    'footer' => [
        'text' => 'Infotag Support Team'
    ],

    'action' => [
        'button' => 'Open Menu',
        'sections' => [[
            'title' => 'Support Categories',
            'rows' => $rows
        ]]
    ]
]
    ];
}

function solutionButtons($to, $title, $message)
{
    return [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'button',
            'body' => [
                'text' => $message
            ],
            'action' => [
                'buttons' => [
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'issue_solved',
                            'title' => '✅ Solved'
                        ]
                    ],
                    [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'issue_not_solved',
                            'title' => '❌ Not Solved'
                        ]
                    ]
                ]
            ]
        ]
    ];
}