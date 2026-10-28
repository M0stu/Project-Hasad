<?php
declare(strict_types=1);
if (!defined('HASAD_BOOT')) { http_response_code(404); exit; }
return [
    'app_env' => 'production', // production | local. local OTPs work ONLY on loopback.
    'app_url' => 'https://YOUR-DOMAIN.example',
    'app_key' => 'REPLACE_WITH_64_RANDOM_HEX_CHARACTERS',
    'setup_token' => 'REPLACE_WITH_A_DIFFERENT_64_RANDOM_HEX_VALUE',
    'require_https' => true,
    'db' => ['host' => 'sqlXXX.infinityfree.com', 'port' => 3306,
             'name' => 'if0_XXXXXX_hasad', 'user' => 'if0_XXXXXX', 'password' => ''],
    'otp' => ['driver' => 'twilio', // twilio | local
              'account_sid' => '', 'auth_token' => '', 'verify_service_sid' => ''],
    'proof_max_bytes' => 1500000,
    'session_seconds' => 7200,
];
