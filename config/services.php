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
    | Pusher Configuration (alternative to Node.js WebSocket server)
    |--------------------------------------------------------------------------
    |
    | Used when the admin selects 'Pusher' as the realtime driver.
    | Works on shared hosting — no Node.js server needed.
    | Create a free account at https://pusher.com → Channels → Create App
    |
    */
    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'useTLS' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Social Login)
    |--------------------------------------------------------------------------
    |
    | Credentials for "Continue with Google" login. Two ways to configure:
    |
    | 1. Env vars below (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET) — these take
    |    priority and are read by OAuthSettingsService.
    | 2. Admin Panel → Settings → Integrations → Social Login (stored in the
    |    `settings` table as googleClientId / googleClientSecret).
    |
    | GOOGLE_REDIRECT_URI is optional. When set, it is used verbatim as the
    |    OAuth callback URL — useful behind HTTPS-terminating proxies where the
    |    auto-detected scheme/host may not match Google Console's registered URI.
    |
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook OAuth (Social Login)
    |--------------------------------------------------------------------------
    |
    | Same pattern as Google. Env vars take priority over the admin panel
    | settings (facebookAppId / facebookAppSecret).
    |
    */
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Socket.io Server (Node.js bridge)
    |--------------------------------------------------------------------------
    |
    | Configuration for the Node.js socket.io server that handles real-time
    | WebSocket connections. Laravel POSTs events here via the SocketService.
    | Used when the admin selects 'WebSocket' as the realtime driver.
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
