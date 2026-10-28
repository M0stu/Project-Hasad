<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {exit('CLI only');}
ini_set('session.save_path',sys_get_temp_dir());
ob_start();
$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__).'/htdocs/app/bootstrap.php';
$db=getenv('HASAD_TEST_DB')?:'hasad_test';
if (!preg_match('/^[a-zA-Z0-9_]+_test$/',$db)) {throw new RuntimeException('Tests only run on a dedicated database ending in _test');}
$GLOBALS['config']=['app_env'=>'local','app_url'=>'http://localhost','app_key'=>str_repeat('a',64),'setup_token'=>str_repeat('b',64),'require_https'=>false,'db'=>['host'=>getenv('HASAD_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('HASAD_TEST_PORT')?:3306),'name'=>$db,'user'=>getenv('HASAD_TEST_USER')?:'root','password'=>getenv('HASAD_TEST_PASSWORD')?:''],'otp'=>['driver'=>'local'],'session_seconds'=>7200];
$GLOBALS['lang']='en';
