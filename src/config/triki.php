<?php

return [
    'auth' => [
        'enabled'           => true,
        'auth_key'          => env('TRIKI_AUTH_KEY', 'web-mavens'),
        'authorized_emails' => [
            'you@example.com',
            // Add more emails
        ],
    ],
];
