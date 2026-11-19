<?php
declare(strict_types=1);
if (!defined('HASAD_BOOT')) { http_response_code(404); exit; }
$env=static fn(string $key,string $default=''): string => (($v=getenv($key))!==false?$v:$default);
return [
    'app_env' => $env('HASAD_APP_ENV','production'), // production | local | demo
    'app_url' => $env('HASAD_APP_URL','https://YOUR-DOMAIN.example'),
    'app_key' => $env('HASAD_APP_KEY','REPLACE_WITH_64_RANDOM_HEX_CHARACTERS'),
    'setup_token' => $env('HASAD_SETUP_TOKEN','REPLACE_WITH_A_DIFFERENT_64_RANDOM_HEX_VALUE'),
    'require_https' => $env('HASAD_REQUIRE_HTTPS','1')==='1',
    'db' => ['host'=>$env('HASAD_DB_HOST','sqlXXX.infinityfree.com'),'port'=>(int)$env('HASAD_DB_PORT','3306'),'name'=>$env('HASAD_DB_NAME','if0_XXXXXX_hasad'),'user'=>$env('HASAD_DB_USER','if0_XXXXXX'),'password'=>$env('HASAD_DB_PASSWORD')],
    'otp' => ['driver'=>$env('HASAD_OTP_DRIVER','twilio'),'account_sid'=>$env('HASAD_TWILIO_ACCOUNT_SID'),'auth_token'=>$env('HASAD_TWILIO_AUTH_TOKEN'),'verify_service_sid'=>$env('HASAD_TWILIO_VERIFY_SERVICE_SID')],
    'demo_mode' => $env('HASAD_DEMO_MODE','0')==='1',
    'demo_personas' => [
        'supplier'=>['phone'=>$env('HASAD_DEMO_SUPPLIER_PHONE')],
        'buyer'=>['phone'=>$env('HASAD_DEMO_BUYER_PHONE')],
        'admin'=>['email'=>$env('HASAD_DEMO_ADMIN_EMAIL')],
    ],
    'proof_max_bytes'=>1500000,'session_seconds'=>7200,
];
