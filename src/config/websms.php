<?php
return [
    'wsdl_file'     => "https://www.websms.com.cy/webservices/websms.wsdl",
    'username'      => env('WEBSMS_USERNAME', config("services.websms.username")),
    'password'      => env('WEBSMS_PASSWORD', config("services.websms.password")),
    'session_path'  => sys_get_temp_dir() . " /websms.sess",
    'session_ttl'   => 25 * 60
];
