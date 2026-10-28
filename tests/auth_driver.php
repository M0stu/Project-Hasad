<?php
declare(strict_types=1);
require __DIR__.'/test_boot.php';
$mode=$argv[1]??'';
if($mode==='otp'){
 $_SESSION['otp']=['token'=>str_repeat('f',64),'name'=>'Test','type'=>'buyer'];$_POST=['code'=>'654321'];finish_phone_login();
}elseif($mode==='staff'){
 $_POST=['email'=>'admin@example.test','password'=>$argv[2]??''];login_staff();
}else{throw new RuntimeException('Unknown test action');}
