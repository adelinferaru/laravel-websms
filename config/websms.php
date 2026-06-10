<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WSDL endpoint
    |--------------------------------------------------------------------------
    |
    | The WSDL describing the WebSMS.com.cy SOAP web service.
    |
    */

    'wsdl' => env('WEBSMS_WSDL', 'https://www.websms.com.cy/webservices/websms.wsdl'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Your WebSMS.com.cy account credentials, used to open a gateway session.
    |
    */

    'username' => env('WEBSMS_USERNAME'),

    'password' => env('WEBSMS_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Session caching
    |--------------------------------------------------------------------------
    |
    | The gateway session ID is cached between requests. "store" selects the
    | cache store to use (null = your default cache store), "ttl" is the
    | session lifetime in seconds and matches the gateway's own timeout.
    |
    */

    'session' => [
        'store' => env('WEBSMS_SESSION_STORE'),
        'key' => 'websms.session_id',
        'ttl' => 25 * 60,
    ],

];
