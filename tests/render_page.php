<?php
declare(strict_types=1);
require __DIR__.'/test_boot.php';
$view=$argv[1]??'home';$locale=$argv[2]??'en';$id=(int)($argv[3]??0);$section=$argv[4]??'';
if (!in_array($view,['home','sell','market','buyers','login','verify','account','checkout','order','invoice','contact','admin-login','setup','legal','thanks','error','admin'],true)) {throw new RuntimeException('Invalid view');}
$GLOBALS['lang']=$locale;
$_SESSION['member_id']=getenv('HASAD_TEST_MEMBER')===false?2:(int)getenv('HASAD_TEST_MEMBER');$_SESSION['staff_id']=(int)(getenv('HASAD_TEST_STAFF')?:1);$_SESSION['otp']=['token'=>str_repeat('f',64),'name'=>'Test','type'=>'buyer'];
$_GET=['page'=>$view,'id'=>$id,'section'=>$section];
$data=['title'=>'Hasad','message'=>'Example message'];
if(in_array($view,['order','invoice'],true)){$data['o']=order_detail($id);}
if($view==='legal'){$data['legal_type']='privacy';}
if($view==='admin'){$data['section']=$section?:'dashboard';permit($data['section']);}
render($view,$data);
