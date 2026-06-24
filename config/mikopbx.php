<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MikoPBX REST API
    |--------------------------------------------------------------------------
    */
    'url'     => env('MIKOPBX_URL', 'https://192.168.1.100'),
    'api_key' => env('MIKOPBX_API_KEY', ''),
    'timeout' => env('MIKOPBX_TIMEOUT', 10),
    'verify_ssl' => env('MIKOPBX_VERIFY_SSL', false),

    /*
    |--------------------------------------------------------------------------
    | Asterisk Manager Interface (AMI)
    |--------------------------------------------------------------------------
    */
    'ami' => [
        'host'     => env('MIKOPBX_AMI_HOST', '192.168.1.100'),
        'port'     => env('MIKOPBX_AMI_PORT', 5038),
        'username' => env('MIKOPBX_AMI_USER', 'admin'),
        'secret'   => env('MIKOPBX_AMI_SECRET', ''),
        'timeout'  => env('MIKOPBX_AMI_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asterisk REST Interface (ARI) — WebSocket
    |--------------------------------------------------------------------------
    */
    'ari' => [
        'url'      => env('MIKOPBX_ARI_URL', 'http://192.168.1.100:8088'),
        'username' => env('MIKOPBX_ARI_USER', 'admin'),
        'password' => env('MIKOPBX_ARI_PASSWORD', ''),
        'app'      => env('MIKOPBX_ARI_APP', 'laravel-mikopbx'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Dialer (WebRTC/SIP.js softphone)
    |--------------------------------------------------------------------------
    */
    'dialer' => [
        'enabled'         => env('MIKOPBX_DIALER_ENABLED', true),
        'sip_server'      => env('MIKOPBX_SIP_SERVER', '192.168.1.100'),
        'sip_ws_port'     => env('MIKOPBX_SIP_WS_PORT', 8088),
        'sip_wss_enabled' => env('MIKOPBX_SIP_WSS', false),
        'stun_server'     => env('MIKOPBX_STUN', 'stun:stun.l.google.com:19302'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing — prefix and middleware
    |--------------------------------------------------------------------------
    */
    'route_prefix'     => env('MIKOPBX_ROUTE_PREFIX', 'pbx'),
    'route_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Database table prefix
    |--------------------------------------------------------------------------
    */
    'table_prefix' => 'mikopbx_',

    /*
    |--------------------------------------------------------------------------
    | Features toggle
    |--------------------------------------------------------------------------
    */
    'features' => [
        'campaigns'   => true,
        'auto_dialer' => true,
        'recordings'  => true,
        'blacklist'   => true,
        'callbacks'   => true,
        'conference'  => true,
        'ivr_builder' => true,
        'analytics'   => true,
        'health_check'=> true,
        'sms_alerts'  => env('MIKOPBX_SMS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway (for missed call alerts)
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver'  => env('MIKOPBX_SMS_DRIVER', 'ssl_wireless'), // ssl_wireless | twilio | vonage
        'api_key' => env('MIKOPBX_SMS_API_KEY', ''),
        'from'    => env('MIKOPBX_SMS_FROM', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Call recording storage
    |--------------------------------------------------------------------------
    */
    'recordings' => [
        'disk'    => env('MIKOPBX_RECORDING_DISK', 'local'),
        'path'    => env('MIKOPBX_RECORDING_PATH', 'mikopbx/recordings'),
        'proxy'   => env('MIKOPBX_RECORDING_PROXY', true), // proxy through Laravel for security
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Dialer defaults
    |--------------------------------------------------------------------------
    */
    'dialer_defaults' => [
        'max_channels'   => 5,
        'retry_attempts' => 3,
        'retry_delay'    => 300, // seconds
        'dial_timeout'   => 30,  // seconds per call attempt
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent / Extension defaults
    |--------------------------------------------------------------------------
    */
    'agent' => [
        'ring_time'    => 20,   // seconds before failover
        'wrap_up_time' => 30,   // seconds after call ends
        'max_calls'    => 1,    // concurrent calls per agent
    ],

    /*
    |--------------------------------------------------------------------------
    | Health check schedule (minutes)
    |--------------------------------------------------------------------------
    */
    'health_check_interval' => env('MIKOPBX_HEALTH_INTERVAL', 5),

];
