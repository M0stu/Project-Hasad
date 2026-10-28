<?php
declare(strict_types=1);
define('HASAD_BOOT', true);
define('HASAD_ROOT', dirname(__DIR__));
date_default_timezone_set('Africa/Cairo');
$configFile = HASAD_ROOT . '/config/config.php';
$GLOBALS['config'] = is_file($configFile) ? require $configFile : [];
require __DIR__ . '/helpers.php';
if(PHP_SAPI!=='cli' && cfg('app_env','production')==='production'){ini_set('display_errors','0');error_reporting(E_ALL);}
require __DIR__ . '/database.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/domain.php';
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    if (is_https()) { header('Strict-Transport-Security: max-age=31536000'); }
    if (cfg('require_https', true) && $GLOBALS['config'] && !is_https()) {
        http_response_code(400);
        exit('Please open this website using HTTPS. / يرجى فتح الموقع عبر HTTPS.');
    }
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('hasad_session');
session_set_cookie_params(['lifetime' => 0, 'path' => base_path() ?: '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > (int) cfg('session_seconds', 7200)) { $_SESSION = []; session_regenerate_id(true); }
$_SESSION['last_seen'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$chosenLanguage = $_GET['lang'] ?? null;
if (in_array($chosenLanguage, ['ar', 'en'], true)) { $_SESSION['lang'] = $chosenLanguage; }
$GLOBALS['lang'] = $_SESSION['lang'] ?? 'ar';
set_exception_handler(function (Throwable $e): void {
    if (Db::inTransaction()) { Db::rollback(); }
    $expected = $e instanceof DomainException;
    $duplicate = $e instanceof PDOException && $e->getCode() === '23000';
    $message = $expected ? $e->getMessage() : ($duplicate ? t('تم تسجيل هذا المرجع من قبل. تحقق من البيانات.','This reference is already recorded. Please check the details.') : t('تعذر إتمام العملية الآن. حاول لاحقاً أو تواصل مع الدعم.','We could not complete this request. Try again later or contact support.'));
    if (!$expected) { error_log('Hasad ' . get_class($e) . ': ' . ($e instanceof PDOException ? $e->getCode() : $e->getMessage())); }
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $message . "\n"); exit(1); }
    $priorStatus=(int)http_response_code();
    http_response_code($expected && $priorStatus>=400 && $priorStatus<500 ? $priorStatus : ($expected || $duplicate ? 422 : 503));
    $GLOBALS['error_message'] = $message;
    $GLOBALS['error_rendering'] = true;
    render('error', ['title' => t('تعذر إتمام الطلب','Request could not be completed'), 'message' => $message]);
});

if ($GLOBALS['config'] && (!preg_match('/^[a-fA-F0-9]{64}$/',(string)cfg('app_key','')) || !filter_var((string)cfg('app_url',''),FILTER_VALIDATE_URL))) {throw new DomainException(t('إعداد المنصة غير مكتمل. يرجى التواصل مع الإدارة.','Platform configuration is incomplete. Please contact the administrator.'));}
