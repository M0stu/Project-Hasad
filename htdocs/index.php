<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$page=is_string($_GET['page']??null)?$_GET['page']:'home';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $action=post('action',80);
    switch ($action) {
        case 'phone-start': start_phone_login();
        case 'phone-check': finish_phone_login();
        case 'admin-login': login_staff();
        case 'setup-admin': setup_admin();
        case 'logout': if (post('area',20)==='staff') {unset($_SESSION['staff_id']);} else {unset($_SESSION['member_id'],$_SESSION['otp'],$_SESSION['local_code']);} session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('home');
        case 'supply': create_supply();flash(t('استلمنا طلبك. فريق حصاد سيراجع التفاصيل ويتواصل معك.','Your request is received. Hasad will review the details and contact you.'));redirect('thanks');
        case 'order': $id=create_order();redirect('order',['id'=>$id]);
        case 'payment': submit_payment();flash(t('تم إرسال بيانات التحويل للمراجعة. لم يتم تأكيد الدفع بعد.','Transfer details were submitted for review. Payment is not confirmed yet.'));redirect('order',['id'=>(int)$_POST['order_id']]);
        case 'ticket': create_ticket();flash(t('استلمنا رسالتك. تابع الرد من حسابك.','Your message was received. Follow the reply in your account.'));redirect(member()?'account':'contact');
        default: handle_admin_action($action);
    }
}
if (in_array($page,['account','order','checkout','proof','invoice','admin','export'],true)) {header('Cache-Control: no-store');}
if ($page==='proof') {
    $id=max(0,(int)($_GET['id']??0));$r=Db::one('SELECT p.*,o.user_id FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.id=?',[$id]);
    if (!$r || !$r['proof_path'] || !(can('payments') || (member() && (int)$r['user_id']===(int)member()['id']))) {http_response_code(404);exit;}
    $filename=basename($r['proof_path']);$path=HASAD_ROOT.'/storage/proofs/'.$filename;
    if (!preg_match('/^[a-f0-9]{48}\.bin$/',$filename) || !is_file($path)) {http_response_code(404);exit;}
    header('Content-Type: '.$r['proof_mime']);header('Content-Disposition: inline; filename="receipt.'.($r['proof_mime']==='image/png'?'png':'jpg').'"');header('Cache-Control: no-store');readfile($path);exit;
}
if ($page==='export') {
    permit('reports');$which=is_string($_GET['type']??null)?$_GET['type']:'stock';
    $exports=['stock'=>"SELECT b.code,m.name_en AS material,p.name AS owner,h.name_en AS hub,b.grade,b.quantity_g,b.reserved_g,b.price_per_tonne,b.status FROM batches b JOIN materials m ON m.id=b.material_id JOIN parties p ON p.id=b.supplier_id JOIN hubs h ON h.id=b.hub_id ORDER BY b.id DESC LIMIT 5000",'orders'=>'SELECT code,quantity_g,subtotal,shipping,service_fee,total,status,created_at FROM orders ORDER BY id DESC LIMIT 5000','trial-balance'=>'SELECT a.code,a.name_en,a.type,COALESCE(SUM(l.debit),0) AS debit_piastres,COALESCE(SUM(l.credit),0) AS credit_piastres FROM accounts a LEFT JOIN journal_lines l ON l.account_id=a.id GROUP BY a.id,a.code,a.name_en,a.type ORDER BY a.id','stock-ledger'=>'SELECT b.code,s.kind,s.quantity_delta_g,s.reserved_delta_g,s.reference,s.created_at FROM stock_movements s JOIN batches b ON b.id=s.batch_id ORDER BY s.id DESC LIMIT 5000'];
    if (!isset($exports[$which])) {http_response_code(404);exit;}
    $rows=Db::all($exports[$which]); audit('report_export','reports',0,['type'=>$which]);
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="hasad-'.$which.'-'.date('Y-m-d').'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    if ($rows) {fputcsv($out,array_keys($rows[0]),',','"','');foreach($rows as $row){fputcsv($out,array_map('csv_cell',$row),',','"','');}}fclose($out);exit;
}
switch ($page) {
    case 'home': render('home',['title'=>t('حصاد — من المخلفات إلى الفرص','Hasad — From residues to opportunity')]);break;
    case 'market': render('market',['title'=>t('سوق المواد','Materials marketplace')]);break;
    case 'sell': render('sell',['title'=>t('بيع مخلفاتك الزراعية','Sell your agricultural residues')]);break;
    case 'buyers': render('buyers',['title'=>t('توريد يناسب مصنعك','Supply that fits your factory')]);break;
    case 'login': render('login',['title'=>t('الدخول برقم الموبايل','Sign in with your phone')]);break;
    case 'verify': render('verify',['title'=>t('تأكيد رقم الموبايل','Verify your phone')]);break;
    case 'account': member_required();render('account',['title'=>t('حسابي','My account')]);break;
    case 'checkout': member_required();render('checkout',['title'=>t('راجع طلبك','Review your order')]);break;
    case 'order': $u=member_required();$o=order_detail(max(0,(int)($_GET['id']??0)));if ((int)$o['user_id']!==(int)$u['id']){http_response_code(404);throw new DomainException(t('الطلب غير موجود.','Order not found.'));}render('order',['title'=>t('تفاصيل الطلب','Order details'),'o'=>$o]);break;
    case 'invoice': $internal=can('orders');$u=$internal?null:member_required();$o=order_detail(max(0,(int)($_GET['id']??0)));if((!$internal && (int)$o['user_id']!==(int)$u['id']) || !in_array($o['status'],['paid','dispatching','delivered','refund_pending','refunded'],true)){throw new DomainException(t('البيان غير متاح لهذا الحساب أو قبل تأكيد الدفع.','Statement unavailable for this account or before payment approval.'));}render('invoice',['title'=>t('بيان الطلب والسداد','Order & payment statement'),'o'=>$o]);break;
    case 'admin-login': render('admin-login',['title'=>t('دخول الإدارة','Administration login')]);break;
    case 'setup': render('setup',['title'=>t('إعداد حصاد','Set up Hasad')]);break;
    case 'admin': staff_required();$section=is_string($_GET['section']??null)?$_GET['section']:'dashboard';if (!isset(admin_sections()[$section])){$section='dashboard';}permit($section);if(in_array($section,['dashboard','orders'],true)){expire_holds();}render('admin',['title'=>admin_sections()[$section][0],'section'=>$section]);break;
    case 'contact': render('contact',['title'=>t('نساعدك في كل خطوة','We help at every step')]);break;
    case 'thanks': render('thanks',['title'=>t('تم استلام طلبك','Request received')]);break;
    case 'privacy': render('legal',['title'=>t('سياسة الخصوصية','Privacy policy'),'legal_type'=>'privacy']);break;
    case 'terms': render('legal',['title'=>t('شروط الاستخدام','Terms of use'),'legal_type'=>'terms']);break;
    default: http_response_code(404);render('error',['title'=>t('الصفحة غير موجودة','Page not found'),'message'=>t('الرابط الذي فتحته غير موجود.','The page you opened does not exist.')]);
}
