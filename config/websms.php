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
    | Default sender ID
    |--------------------------------------------------------------------------
    |
    | The default "from" used by the websms notification channel when the
    | notification message does not specify its own sender.
    |
    */

    'from' => env('WEBSMS_FROM'),

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    |
    | Settings for the WebSmsRestClient, an alternative transport that
    | authenticates with an API key (generated in your WebSMS account area)
    | instead of a username/password session. The REST API covers sending to
    | a single recipient per request, credits, and batch status.
    |
    */

    'rest' => [
        'url' => env('WEBSMS_REST_URL', 'https://websms.com.cy/api'),
        'key' => env('WEBSMS_API_KEY'),
    ],

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
