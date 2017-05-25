<?php

return [
    // For some long messages (like SQL queries), we add line break to them for better display.
    // This value set the max length of each line.
    'column_length_limit' => 100,
    
    // [ message level => style ]
    // for styles, 'null' is no style, 'info' is green text, 'error' is red background color and white text
    // you can set other styles, see http://symfony.com/doc/current/console/coloring.html
    'debug_message_styles' => [
        'debug' => null,
        'info' => 'info',
        'notice' => 'info',
        'warning' => 'fg=yellow',
        "error" => 'error',
        "critical" => 'error',
        "alert" => 'error',
        "emergency" => 'error'
    ],
];