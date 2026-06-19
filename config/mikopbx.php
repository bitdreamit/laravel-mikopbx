<?php

return [

    /*|--------------------------------------------------------------------------
    | MikoPBX REST API
    |--------------------------------------------------------------------------*/
    'url'         => env('MIKOPBX_URL', 'https://127.0.0.1'),
    'api_key'     => env('MIKOPBX_API_KEY', ''),
    'verify_ssl'  => env('MIKOPBX_VERIFY_SSL', false),
    'timeout'     => env('MIKOPBX_TIMEOUT', 30),

    /*|--------------------------------------------------------------------------
    | Asterisk AMI
    |--------------------------------------------------------------------------*/
    'ami_host'     => env('MIKOPBX_AMI_HOST', '127.0.0.1'),
    'ami_port'     => env('MIKOPBX_AMI_PORT', 5038),
    'ami_username' => env('MIKOPBX_AMI_USER', 'admin'),
    'ami_secret'   => env('MIKOPBX_AMI_SECRET', ''),

    /*|--------------------------------------------------------------------------
    | ARI (Asterisk REST Interface)
    |--------------------------------------------------------------------------*/
    'ari_url'      => env('MIKOPBX_ARI_URL', 'http://127.0.0.1:8088'),
    'ari_username' => env('MIKOPBX_ARI_USER', 'ari_admin'),
    'ari_secret'   => env('MIKOPBX_ARI_SECRET', ''),

    /*|--------------------------------------------------------------------------
    | Call Settings
    |--------------------------------------------------------------------------*/
    'default_context'             => env('MIKOPBX_CONTEXT', 'from-internal'),
    'default_timeout'             => env('MIKOPBX_CALL_TIMEOUT', 30000),
    'default_callback_extension'  => env('MIKOPBX_CALLBACK_EXT', '100'),
    'recording_path'              => env('MIKOPBX_RECORDING_PATH', '/var/spool/mikopbx/storage/ast/'),
    'max_retry_attempts'          => env('MIKOPBX_MAX_RETRY', 3),
    'retry_delay_minutes'         => env('MIKOPBX_RETRY_DELAY', 5),

    /*|--------------------------------------------------------------------------
    | Campaign Settings
    |--------------------------------------------------------------------------*/
    'campaign_max_channels' => env('MIKOPBX_CAMPAIGN_CHANNELS', 5),
    'campaign_dial_prefix'  => env('MIKOPBX_DIAL_PREFIX', ''),

    /*|--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------*/
    'webhook_secret' => env('MIKOPBX_WEBHOOK_SECRET', ''),

    /*|--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------*/
    'routes' => [
        'enabled'    => env('MIKOPBX_ROUTES_ENABLED', true),
        'prefix'     => env('MIKOPBX_ROUTES_PREFIX', 'mikopbx'),
        'middleware' => ['api'],
    ],
    'route_api_key' => env('MIKOPBX_ROUTE_API_KEY', ''),

    /*|--------------------------------------------------------------------------
    | SMS Notifications
    |--------------------------------------------------------------------------*/
    'sms' => [
        'driver'         => env('MIKOPBX_SMS_DRIVER', 'custom'), // twilio|vonage|ssl_bd|custom
        'twilio_sid'     => env('TWILIO_SID', ''),
        'twilio_token'   => env('TWILIO_TOKEN', ''),
        'twilio_from'    => env('TWILIO_FROM', ''),
        'vonage_key'     => env('VONAGE_KEY', ''),
        'vonage_secret'  => env('VONAGE_SECRET', ''),
        'vonage_from'    => env('VONAGE_FROM', ''),
        'ssl_bd_url'     => env('SSL_BD_SMS_URL', ''),
        'ssl_bd_api_key' => env('SSL_BD_API_KEY', ''),
        'ssl_bd_sender'  => env('SSL_BD_SENDER', ''),
        'custom_url'     => env('MIKOPBX_SMS_URL', ''),
        'custom_params'  => [],
    ],

    /*|--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------*/
    'notifications' => [
        'channels'         => ['mail', 'database'],
        'missed_call'      => env('MIKOPBX_NOTIFY_MISSED', true),
        'voicemail'        => env('MIKOPBX_NOTIFY_VOICEMAIL', true),
        'campaign_complete'=> env('MIKOPBX_NOTIFY_CAMPAIGN', true),
    ],

    /*|--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------*/
    'queue' => env('MIKOPBX_QUEUE', 'default'),

];
