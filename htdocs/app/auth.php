<?php
declare(strict_types=1);
function member(): ?array { $id=(int)($_SESSION['member_id'] ?? 0); return $id && ready() ? Db::one("SELECT * FROM users WHERE id=? AND active=1 AND role='member'",[$id]) : null; }
function staff(): ?array { $id=(int)($_SESSION['staff_id'] ?? 0); return $id && ready() ? Db::one("SELECT * FROM users WHERE id=? AND active=1 AND role IN ('support','admin','super_admin')",[$id]) : null; }
function member_required(): array { $u=member(); if (!$u) { redirect('login'); } return $u; }
function staff_required(): array { $u=staff(); if (!$u) { redirect('admin-login'); } return $u; }
function can(string $permission): bool {
    $u=staff(); if (!$u) { return false; }
    if ($u['role']==='super_admin') { return true; }
    $permissions=['admin'=>['dashboard','supply','inspection','inventory','parties','orders','payments','logistics','finance','procurement','production','projects','tasks','people','support','reports'], 'support'=>['dashboard','supply','logistics','tasks','support']];
    return in_array($permission,$permissions[$u['role']] ?? [],true);
}
function permit(string $permission): array { $u=staff_required(); if (!can($permission)) { if(PHP_SAPI!=='cli'){http_response_code(403);} throw new DomainException(t('لا تملك صلاحية هذه العملية.','You do not have permission for this action.')); } return $u; }
function local_otp_allowed(): bool { return cfg('app_env')==='local' && (cfg('otp',[])['driver'] ?? '')==='local' && in_array($_SERVER['REMOTE_ADDR'] ?? '',['127.0.0.1','::1'],true); }
function twilio_verify(string $action, array $fields): array {
    $c=cfg('otp',[]);
    if (!function_exists('curl_init') || !preg_match('/^VA[a-fA-F0-9]{32}$/',$c['verify_service_sid'] ?? '') || !preg_match('/^AC[a-fA-F0-9]{32}$/',$c['account_sid'] ?? '') || empty($c['auth_token'])) { throw new DomainException(t('الدخول بالموبايل غير مفعّل بعد. تواصل مع الدعم.','Phone login has not been activated yet. Please contact support.')); }
    $ch=curl_init('https://verify.twilio.com/v2/Services/'.$c['verify_service_sid'].'/'.$action);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_USERPWD=>$c['account_sid'].':'.$c['auth_token'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
    $res=is_string($raw)?json_decode($raw,true):null;
    if ($status < 200 || $status >= 300 || !is_array($res)) { throw new DomainException($action==='VerificationCheck' ? t('الرمز غير صحيح أو انتهت صلاحيته.','The code is incorrect or expired.') : t('تعذر إرسال الرمز. حاول لاحقاً.','We could not send the code. Please try later.')); }
    return $res;
}
function start_phone_login(): void {
    live_required();
    $p=phone(required('phone',30)); $name=required('name',160); $type=choice('account_type',['seller','buyer']);
    if (post('consent',4)!=='yes') { throw new DomainException(t('وافق على استخدام رقمك لإرسال رمز الدخول.','Consent to receiving a login code on your phone is required.')); }
    $ip=$_SERVER['REMOTE_ADDR'] ?? 'unknown';
    rate_limit('otp-send-ip:'.$ip,10,3600); rate_limit('otp-send-phone:'.$p,3,900);
    $dev=local_otp_allowed();
    if (!$dev && (cfg('otp',[])['driver'] ?? '')!=='twilio') { throw new DomainException(t('خدمة الدخول غير مفعلة بعد.','Login service is not activated yet.')); }
    $code=$dev?(string)random_int(100000,999999):null;
    if (!$dev) { $r=twilio_verify('Verifications',['To'=>$p,'Channel'=>'sms','Locale'=>lang()]); if (($r['status'] ?? '')!=='pending') { throw new DomainException(t('تعذر بدء التحقق.','Verification could not be started.')); } }
    $token=bin2hex(random_bytes(32));
    Db::run('INSERT INTO otp_challenges(token,phone,code_hash,expires_at) VALUES(?,?,?,?)',[$token,$p,$code?password_hash($code,PASSWORD_DEFAULT):null,gmdate('Y-m-d H:i:s',time()+600)]);
    $_SESSION['otp']=['token'=>$token,'name'=>$name,'type'=>$type];
    if ($dev) { $_SESSION['local_code']=$code; }
    redirect('verify');
}
function finish_phone_login(): void {
    live_required(); $pending=$_SESSION['otp'] ?? null;
    if (!$pending) { throw new DomainException(t('اطلب رمزاً جديداً أولاً.','Request a new code first.')); }
    $entered=normalize_digits(required('code',10));
    if (!preg_match('/^\d{6}$/',$entered)) { throw new DomainException(t('اكتب الرمز المكوّن من ٦ أرقام.','Enter the six-digit code.')); }
    rate_limit('otp-check-ip:'.($_SERVER['REMOTE_ADDR'] ?? ''),30,900);
    $u=Db::tx(function () use ($pending,$entered): array {
        $c=Db::one('SELECT * FROM otp_challenges WHERE token=? FOR UPDATE',[$pending['token']]);
        if (!$c || $c['status']!=='pending' || strtotime($c['expires_at'].' UTC')<time() || (int)$c['attempts']>=5) { throw new DomainException(t('انتهت صلاحية الرمز. اطلب رمزاً جديداً.','The code expired. Request a new one.')); }
        // Attempt counts survive incorrect verifications by committing the count before raising an error.
        Db::run('UPDATE otp_challenges SET attempts=attempts+1 WHERE id=?',[$c['id']]);
        if (local_otp_allowed()) { $ok=$c['code_hash'] && password_verify($entered,$c['code_hash']); }
        else { try { $r=twilio_verify('VerificationCheck',['To'=>$c['phone'],'Code'=>$entered]); $ok=($r['status'] ?? '')==='approved'; } catch (DomainException) { $ok=false; } }
        if (!$ok) { return ['failed'=>true]; }
        $u=Db::one('SELECT * FROM users WHERE phone=? FOR UPDATE',[$c['phone']]);
        if ($u && ($u['role']!=='member' || !$u['active'])) { return ['blocked'=>true]; }
        if (!$u) { $id=Db::insert("INSERT INTO users(name,phone,role,account_type,verified_at) VALUES(?,?,'member',?,UTC_TIMESTAMP())",[$pending['name'],$c['phone'],$pending['type']]); $u=Db::one('SELECT * FROM users WHERE id=?',[$id]); }
        Db::run("UPDATE otp_challenges SET status='used' WHERE id=?",[$c['id']]);
        Db::run('UPDATE supply_requests SET user_id=?,contact_verified=1 WHERE contact_phone=? AND user_id IS NULL',[$u['id'],$c['phone']]);
        Db::run("UPDATE parties SET user_id=? WHERE phone=? AND kind='seller' AND verified=1 AND user_id IS NULL",[$u['id'],$c['phone']]);
        Db::run('UPDATE tickets SET user_id=? WHERE contact_phone=? AND user_id IS NULL',[$u['id'],$c['phone']]);
        return $u;
    });
    if (!empty($u['failed'])) { throw new DomainException(t('الرمز غير صحيح.','The code is incorrect.')); }
    if (!empty($u['blocked'])) { throw new DomainException(t('هذا الحساب غير متاح. تواصل مع الإدارة.','This account is unavailable. Please contact an administrator.')); }
    session_regenerate_id(true); $_SESSION['member_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); unset($_SESSION['otp'],$_SESSION['local_code']);
    redirect('account');
}
function login_staff(): void {
    if (!ready()) { throw new DomainException(t('يجب إتمام إعداد قاعدة البيانات أولاً.','Complete database setup first.')); }
    $email=strtolower(required('email',190)); $password=required('password',512);
    rate_limit('admin-ip:'.($_SERVER['REMOTE_ADDR'] ?? ''),15,900); rate_limit('admin-email:'.$email,5,900);
    $u=Db::one("SELECT * FROM users WHERE email=? AND role IN ('support','admin','super_admin')",[$email]);
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    $ok=password_verify($password,$u['password_hash'] ?? $dummy);
    if (!$u || !$ok || !$u['active']) { throw new DomainException(t('بيانات الدخول غير صحيحة.','Login details are incorrect.')); }
    if (password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT)) { Db::run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]); }
    session_regenerate_id(true); $_SESSION['staff_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); audit('login','users',(int)$u['id']); redirect('admin');
}
function setup_admin(): void {
    if (!ready()) { throw new DomainException(t('استورد ملف schema.sql واضبط config.php أولاً.','Import schema.sql and configure config.php first.')); }
    $token=required('setup_token',128); $configured=(string)cfg('setup_token','');
    if (strlen($configured)<32 || str_contains($configured,'REPLACE') || !hash_equals($configured,$token)) { throw new DomainException(t('رمز الإعداد غير صحيح.','Setup token is incorrect.')); }
    $email=strtolower(required('email',190)); $password=required('password',512); $name=required('name',160);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) { throw new DomainException(t('اكتب بريداً صحيحاً وكلمة مرور من ١٢ حرفاً على الأقل.','Use a valid email and a password with at least 12 characters.')); }
    Db::tx(function () use ($email,$password,$name): void {
        Db::one("SELECT * FROM settings WHERE setting_key='launch_open' FOR UPDATE");
        if ((int)Db::scalar("SELECT COUNT(*) FROM users WHERE role='super_admin'")>0) { throw new DomainException(t('تم الإعداد بالفعل.','Setup is already complete.')); }
        $id=Db::insert("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'super_admin')",[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $_SESSION['staff_id']=$id;
        audit('initial_setup','users',$id);
    });
    session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32)); redirect('admin',['section'=>'settings']);
}
