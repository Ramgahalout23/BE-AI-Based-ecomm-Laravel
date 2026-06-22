<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Socket.io Server (Node.js bridge)
    |--------------------------------------------------------------------------
    |
    | Configuration for the Node.js socket.io server that handles real-time
    | WebSocket connections. Laravel POSTs events here via the SocketService.
    |
    | server_url: Full URL of the Node.js server (e.g. https://api.example.com)
    | internal_key: Shared secret key for inter-server authentication
    |
    */
    'socket' => [
        'server_url' => env('SOCKET_SERVER_URL'),
        'internal_key' => env('INTERNAL_SOCKET_KEY'),
    ],

];
