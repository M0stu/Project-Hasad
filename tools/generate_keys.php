<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
echo "Use these different values in config.php. Do not publish this output.\n";
echo "app_key: ",bin2hex(random_bytes(32)),"\n";
echo "setup_token: ",bin2hex(random_bytes(32)),"\n";
