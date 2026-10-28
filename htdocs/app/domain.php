<?php
declare(strict_types=1);
function catalogue_materials(): array {
    if (ready()) { return Db::all('SELECT * FROM materials WHERE active=1 ORDER BY id'); }
    return [['id'=>1,'name_ar'=>'قش الأرز','name_en'=>'Rice straw','icon'=>'wheat','slug'=>'rice-straw'],['id'=>2,'name_ar'=>'تبن القمح','name_en'=>'Wheat straw','icon'=>'wheat','slug'=>'wheat-straw'],['id'=>3,'name_ar'=>'حطب الذرة','name_en'=>'Corn stalks','icon'=>'sprout','slug'=>'corn-stalks'],['id'=>4,'name_ar'=>'مخلفات النخيل','name_en'=>'Palm residues','icon'=>'leaf','slug'=>'palm-residues'],['id'=>5,'name_ar'=>'مصاصة القصب','name_en'=>'Sugarcane bagasse','icon'=>'layers','slug'=>'sugarcane-bagasse']];
}
function batch_detail(int $id, bool $lock=false): array {
    $r=Db::one('SELECT b.*,m.name_ar,m.name_en,m.icon,h.name_ar AS hub_ar,h.name_en AS hub_en,h.governorate FROM batches b JOIN materials m ON m.id=b.material_id JOIN hubs h ON h.id=b.hub_id WHERE b.id=?'.($lock?' FOR UPDATE':''),[$id]);
    if (!$r) { throw new DomainException(t('الدفعة غير موجودة.','Batch not found.')); } return $r;
}
function order_detail(int $id, bool $lock=false): array {
    $r=Db::one('SELECT o.*,b.supplier_id,b.hub_id,b.grade,b.code AS batch_code,m.name_ar,m.name_en,p.name AS buyer_name FROM orders o JOIN batches b ON b.id=o.batch_id JOIN materials m ON m.id=b.material_id JOIN parties p ON p.id=o.buyer_id WHERE o.id=?'.($lock?' FOR UPDATE':''),[$id]);
    if (!$r) { throw new DomainException(t('الطلب غير موجود.','Order not found.')); } return $r;
}
function stock_move(int $batch, string $kind, int $qty, int $reserved, string $reference): void {
    Db::run('INSERT INTO stock_movements(batch_id,kind,quantity_delta_g,reserved_delta_g,reference,created_by) VALUES(?,?,?,?,?,?)',[$batch,$kind,$qty,$reserved,$reference,staff()['id']??member()['id']??null]);
}
function release_reservation(array $order, string $kind): void {
    $s=Db::run('UPDATE batches SET reserved_g=reserved_g-? WHERE id=? AND reserved_g>=?',[$order['quantity_g'],$order['batch_id'],$order['quantity_g']]);
    if ($s->rowCount()!==1) { throw new LogicException('Reservation invariant broken'); }
    stock_move((int)$order['batch_id'],$kind,0,-(int)$order['quantity_g'],$order['code']);
}
function expire_holds(): void {
    if (!ready()) { return; }
    $ids=Db::all("SELECT o.id FROM orders o WHERE o.status='quoted' AND o.expires_at<UTC_TIMESTAMP() AND NOT EXISTS(SELECT 1 FROM tickets t WHERE t.order_id=o.id AND t.subject='Expired reservation review') ORDER BY o.id LIMIT 25");
    foreach ($ids as $row) {
        Db::tx(function () use ($row): void {
            $o=Db::one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$row['id']]);
            if ($o['status']==='quoted' && strtotime($o['expires_at'].' UTC')<time()) {
                // An operator checks the destination account before cancellation. Automatic expiry
                // never frees possibly paid stock; it only places an operational ticket once.
                if (!Db::one("SELECT id FROM tickets WHERE order_id=? AND subject='Expired reservation review'",[$o['id']])) {
                    Db::run("INSERT INTO tickets(user_id,order_id,contact_phone,subject,message,response) VALUES(?,?,?,'Expired reservation review',?,'')",[$o['user_id'],$o['id'],'',t('انتهت مهلة الطلب. راجع وصول أي تحويل قبل الإلغاء أو تمديد الحجز.','The hold expired. Check for any received transfer before cancellation or extension.')]);
                }
            }
        });
    }
}
function create_supply(): int {
    live_required(); rate_limit('supply:'.($_SERVER['REMOTE_ADDR']??''),8,3600);
    if (post('website',80)!=='') { throw new DomainException(t('تعذر قبول الطلب.','Request could not be accepted.')); }
    $u=member(); $name=required('name',160); $p=phone(required('phone',30)); $material=id_input('material_id');
    if (!Db::one('SELECT id FROM materials WHERE id=? AND active=1',[$material])) { throw new DomainException(t('اختر مادة متاحة.','Select an active material.')); }
    $q=quantity_input(); $gov=choice('governorate',array_keys(governorates())); $location=required('location',500); $handover=choice('handover',['pickup','dropoff']); $date=date_input('preferred_date',true);
    if ($date && $date<date('Y-m-d')) { throw new DomainException(t('اختر موعداً اليوم أو لاحقاً.','Choose today or a later date.')); }
    if ($handover==='pickup' && $q < (int)setting('pickup_min_kg','1000')*1000) { throw new DomainException(t('الكمية أقل من حد الاستلام من المزرعة. اختر التسليم للمركز أو تواصل لتجميع الكميات.','This quantity is below the farm-pickup threshold. Select hub drop-off or contact us to aggregate quantities.')); }
    if (post('consent',4)!=='yes') { throw new DomainException(t('الموافقة على شروط الخدمة مطلوبة.','Agreement to the service terms is required.')); }
    $verified=$u && $u['phone']===$p;
    $c=code('S'); $id=Db::insert('INSERT INTO supply_requests(public_code,user_id,contact_name,contact_phone,material_id,quantity_g,governorate,location,handover,preferred_date,notes,contact_verified) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$c,$verified?$u['id']:null,$name,$p,$material,$q,$gov,$location,$handover,$date,post('notes',2000),$verified?1:0]);
    audit('supply_submitted','supply_requests',$id,['contact_verified'=>(bool)$verified]); $_SESSION['last_supply_code']=$c;
    return $id;
}
function inspect_supply(): void {
    $u=permit('inspection'); $id=id_input('request_id'); $q=quantity_input(); $hub=id_input('hub_id'); $grade=choice('grade',['A','B','C']); $price=money_input('price'); $moisture=decimal_scaled(required('moisture',10),2,10000); $notes=required('inspection_notes',2000);
    if (post('verified',4)!=='yes' || post('authorization',4)!=='yes') { throw new DomainException(t('تحقق من هوية البائع وموافقته على العرض والوساطة أولاً.','Confirm seller identity and permission to list and broker the material first.')); }
    Db::tx(function () use ($u,$id,$q,$hub,$grade,$price,$moisture,$notes): void {
        $r=Db::one('SELECT * FROM supply_requests WHERE id=? FOR UPDATE',[$id]);
        if (!$r || !in_array($r['status'],['new','scheduled'],true)) { throw new DomainException(t('تمت معالجة هذا الطلب بالفعل.','This request has already been processed.')); }
        if (!Db::one('SELECT id FROM hubs WHERE id=? AND active=1',[$hub])) { throw new DomainException(t('اختر مركزاً نشطاً.','Select an active hub.')); }
        $party=Db::one("SELECT id FROM parties WHERE kind='seller' AND phone=? ORDER BY id LIMIT 1",[$r['contact_phone']]);
        $supplier=$party?(int)$party['id']:Db::insert("INSERT INTO parties(kind,name,phone,governorate,address,verified,user_id) VALUES('seller',?,?,?,?,1,?)",[$r['contact_name'],$r['contact_phone'],$r['governorate'],$r['location'],$r['user_id']]);
        Db::run('UPDATE parties SET verified=1 WHERE id=?',[$supplier]);
        $batch=Db::insert("INSERT INTO batches(code,request_id,supplier_id,hub_id,material_id,grade,quantity_g,price_per_tonne,moisture_bp,inspection_notes,inspected_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)",[code('B'),$id,$supplier,$hub,$r['material_id'],$grade,$q,$price,$moisture,$notes,$u['id']]);
        Db::run("UPDATE supply_requests SET status='inspected',contact_verified=1 WHERE id=?",[$id]);
        stock_move($batch,'inspection_received',$q,0,$r['public_code']); audit('inspection_accepted','batches',$batch,['seller_authorization'=>true]);
    });
}
function quote_totals(int $quantity, int $price, string $mode, string $origin, string $destination): array {
    $subtotal=quantity_cost($quantity,$price);
    $shipping=0;
    if ($mode==='hasad') { $rate=(int)setting($origin===$destination?'shipping_local_per_tonne':'shipping_other_per_tonne'); $shipping=max((int)setting('shipping_minimum'),quantity_cost($quantity,$rate)); }
    $bp=(int)setting('service_fee_bp','0'); $flat=(int)setting('service_fee_flat','0');
    $fee=round_ratio($subtotal*$bp,10000)+$flat;
    if($subtotal+$shipping+$fee>100000000000){throw new DomainException(t('قيمة الطلب تتجاوز الحد المسموح. تواصل مع حصاد.','The order exceeds the permitted value. Contact Hasad.'));}
    return ['subtotal'=>$subtotal,'shipping'=>$shipping,'fee_bp'=>$bp,'fee_flat'=>$flat,'service_fee'=>$fee,'total'=>$subtotal+$shipping+$fee];
}
function create_order(): int {
    live_required(); $u=member_required(); rate_limit('order-user:'.$u['id'],10,3600);
    $batch=id_input('batch_id'); $q=quantity_input(); $shipping=choice('shipping_mode',['hasad','buyer']); $destination=required('destination',500); $gov=choice('governorate',array_keys(governorates())); $org=required('organization',190);
    if (post('accept',4)!=='yes') { throw new DomainException(t('وافق على إجمالي العرض والشروط قبل تأكيد الطلب.','Accept the quote total and terms before confirming.')); }
    if (setting('instapay_account')==='' && setting('wallet_number')==='') { throw new DomainException(t('طرق الدفع لم تفعّل بعد. تواصل مع الدعم.','Payment destinations are not configured yet. Please contact support.')); }
    return Db::tx(function () use ($u,$batch,$q,$shipping,$destination,$gov,$org): int {
        $b=batch_detail($batch,true);
        if ($b['status']!=='published' || $q>((int)$b['quantity_g']-(int)$b['reserved_g'])) { throw new DomainException(t('الكمية المطلوبة غير متاحة الآن.','The requested quantity is no longer available.')); }
        $supplier=Db::one('SELECT user_id FROM parties WHERE id=?',[$b['supplier_id']]);
        if ((int)($supplier['user_id']??0)===(int)$u['id']) { throw new DomainException(t('لا يمكنك شراء المواد المسجلة باسمك.','You cannot buy your own material.')); }
        $party=Db::one("SELECT id FROM parties WHERE kind='buyer' AND user_id=? ORDER BY id LIMIT 1",[$u['id']]);
        $buyer=$party?(int)$party['id']:Db::insert("INSERT INTO parties(kind,name,phone,organization,governorate,address,user_id,verified) VALUES('buyer',?,?,?,?,?,?,1)",[$u['name'],$u['phone'],$org,$gov,$destination,$u['id']]);
        $t=quote_totals($q,(int)$b['price_per_tonne'],$shipping,$b['governorate'],$gov);
        // The submitted snapshot is checked against a fresh server-side quote to prevent surprise price changes.
        if (money_input('expected_total',true)!==$t['total']) { throw new DomainException(t('تغير العرض. أعد فتح صفحة الشراء لمراجعة الإجمالي الجديد.','The quote changed. Reopen checkout to review the updated total.')); }
        $id=Db::insert('INSERT INTO orders(code,user_id,buyer_id,batch_id,quantity_g,price_per_tonne,subtotal,shipping,fee_bp,fee_flat,service_fee,total,shipping_mode,destination,governorate,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[code('O'),$u['id'],$buyer,$batch,$q,$b['price_per_tonne'],$t['subtotal'],$t['shipping'],$t['fee_bp'],$t['fee_flat'],$t['service_fee'],$t['total'],$shipping,$destination,$gov,gmdate('Y-m-d H:i:s',time()+(int)setting('order_hold_hours','48')*3600)]);
        Db::run('UPDATE batches SET reserved_g=reserved_g+? WHERE id=?',[$q,$batch]); stock_move($batch,'reserved',0,$q,'Order #'.$id); audit('order_created','orders',$id);
        return $id;
    });
}
function save_proof(): array {
    $f=$_FILES['proof']??null;
    if (!$f || (int)$f['error']===UPLOAD_ERR_NO_FILE) { return [null,null]; }
    if ((int)$f['error']!==UPLOAD_ERR_OK || (int)$f['size']>(int)cfg('proof_max_bytes',1500000) || !is_uploaded_file($f['tmp_name'])) { throw new DomainException(t('ارفع صورة إيصال JPG أو PNG بحجم أقل من ١٫٥ ميجابايت.','Upload a JPG or PNG receipt under 1.5 MB.')); }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $meta=@getimagesize($f['tmp_name']);
    if (!in_array($mime,['image/jpeg','image/png'],true) || !$meta || $meta[0]*$meta[1]>20000000) { throw new DomainException(t('ملف الإيصال يجب أن يكون صورة JPG أو PNG صحيحة.','The receipt must be a valid JPG or PNG image.')); }
    $filename=bin2hex(random_bytes(24)).'.bin';
    if (!move_uploaded_file($f['tmp_name'],HASAD_ROOT.'/storage/proofs/'.$filename)) { throw new RuntimeException('Receipt storage unavailable'); }
    return [$filename,$mime];
}
function submit_payment(): void {
    $u=member_required(); $id=id_input('order_id'); $method=choice('method',['instapay','wallet']); $reference=required('reference',100); $amount=money_input('amount');
    if ($method==='instapay' && setting('instapay_account')==='' || $method==='wallet' && setting('wallet_number')==='') { throw new DomainException(t('طريقة الدفع المختارة غير مفعلة.','The selected payment method is not configured.')); }
    rate_limit('payment-user:'.$u['id'],15,3600);
    [$path,$mime]=save_proof();
    try { Db::tx(function () use ($u,$id,$method,$reference,$amount,$path,$mime): void {
        $o=order_detail($id,true);
        if ((int)$o['user_id']!==(int)$u['id'] || !in_array($o['status'],['quoted','payment_review'],true)) { throw new DomainException(t('لا يمكن تقديم تحويل لهذا الطلب.','A transfer cannot be submitted for this order.')); }
        if ($amount!==(int)$o['total']) { throw new DomainException(t('المبلغ يجب أن يساوي إجمالي الطلب. تواصل مع الدعم عند اختلافه.','The amount must match the order total. Contact support if it differs.')); }
        if (Db::one("SELECT id FROM payments WHERE order_id=? AND status='pending'",[$id])) { throw new DomainException(t('يوجد تحويل قيد المراجعة لهذا الطلب.','This order already has a transfer under review.')); }
        Db::run('INSERT INTO payments(order_id,method,reference,amount,proof_path,proof_mime) VALUES(?,?,?,?,?,?)',[$id,$method,$reference,$amount,$path,$mime]);
        Db::run("UPDATE orders SET status='payment_review' WHERE id=?",[$id]); audit('transfer_submitted','orders',$id);
    }); } catch (Throwable $e) { if ($path) { @unlink(HASAD_ROOT.'/storage/proofs/'.$path); } throw $e; }
}
function post_journal(string $source,int $id,string $event,string $description,array $lines,int $actor): int {
    if (!Db::inTransaction()) { throw new LogicException('Journal must be posted in a transaction'); }
    $debit=0;$credit=0;
    foreach ($lines as $l) { $d=(int)($l['debit']??0);$c=(int)($l['credit']??0); if ($d<0 || $c<0 || (($d===0)===($c===0))) { throw new LogicException('Invalid journal line'); } $debit+=$d;$credit+=$c; }
    if ($debit<1 || $debit!==$credit) { throw new DomainException(t('القيد يجب أن يكون متوازناً وغير صفري.','The journal must be balanced and nonzero.')); }
    $jid=Db::insert('INSERT INTO journals(source,source_id,event,description,created_by) VALUES(?,?,?,?,?)',[$source,$id,$event,$description,$actor]);
    foreach ($lines as $l) { Db::run('INSERT INTO journal_lines(journal_id,account_id,debit,credit,party_id) VALUES(?,?,?,?,?)',[$jid,$l['account'], $l['debit']??0,$l['credit']??0,$l['party']??null]); }
    return $jid;
}
function register_transfer(string $method,string $reference,string $direction,int $amount,string $source,int $sourceId,int $actor): void {
    if (!Db::inTransaction()) {throw new LogicException('Transfer registration must be transactional');}
    Db::run('INSERT INTO transfer_register(reference,method,direction,amount,source,source_id,created_by) VALUES(?,?,?,?,?,?,?)',[$reference,$method,$direction,$amount,$source,$sourceId,$actor]);
}
function confirm_payment(): void {
    $u=permit('payments');$id=id_input('payment_id');$decision=choice('decision',['confirmed','rejected']); $note=required('review_note',500); $bankRef=post('bank_reference',100);
    if ($decision==='confirmed' && (post('bank_checked',4)!=='yes' || $bankRef==='')) { throw new DomainException(t('تحقق من وصول التحويل في الحساب الفعلي وسجل مرجع كشف الحساب.','Check the actual receiving account and record the statement reference.')); }
    Db::tx(function () use ($u,$id,$decision,$note,$bankRef): void {
        // All payment/order paths lock order before payment to avoid lock-order deadlocks.
        $preview=Db::one('SELECT order_id FROM payments WHERE id=?',[$id]); if (!$preview) { throw new DomainException(t('التحويل غير موجود.','Transfer not found.')); }
        $o=order_detail((int)$preview['order_id'],true);$p=Db::one('SELECT * FROM payments WHERE id=? FOR UPDATE',[$id]);
        if ($p['status']!=='pending' || $o['status']!=='payment_review') { throw new DomainException(t('تمت مراجعة هذا التحويل بالفعل.','This transfer has already been reviewed.')); }
        if ($decision==='confirmed') {
            if ((int)$p['amount']!==(int)$o['total']) { throw new LogicException('Payment total invariant broken'); }
            post_journal('order',(int)$o['id'],'collection','Online transfer received '.$o['code'],[['account'=>1010,'debit'=>(int)$o['total']],['account'=>2010,'credit'=>(int)$o['total'],'party'=>$o['buyer_id']]],(int)$u['id']);
            register_transfer($p['method'],$bankRef,'incoming',(int)$o['total'],'payment',$id,(int)$u['id']);
            Db::run("UPDATE orders SET status='paid' WHERE id=?",[$o['id']]);
        } else { Db::run("UPDATE orders SET status='quoted' WHERE id=?",[$o['id']]); }
        Db::run('UPDATE payments SET status=?,bank_reference=?,reviewed_by=?,review_note=? WHERE id=?',[$decision,$decision==='confirmed'?$bankRef:null,$u['id'],$note,$id]); audit('payment_'.$decision,'payments',$id);
    });
}
function fulfill_order(): void {
    $u=permit('orders');$id=id_input('order_id');$next=choice('status',['dispatching','delivered','cancelled']);
    $note=required('note',500);
    Db::tx(function () use ($u,$id,$next,$note): void {
        $o=order_detail($id,true);
        if ($next==='cancelled') {
            if (!in_array($o['status'],['quoted','payment_review','paid'],true)) { throw new DomainException(t('الإلغاء غير متاح في هذه المرحلة.','Cancellation is unavailable at this stage.')); }
            if (post('bank_checked',4)!=='yes') { throw new DomainException(t('راجع الحساب الفعلي قبل إلغاء أي طلب للتأكد من التحويلات المتأخرة.','Check the actual receiving account before cancellation for any delayed transfer.')); }
            if ($o['status']==='payment_review' || Db::one("SELECT id FROM payments WHERE order_id=? AND status='pending'",[$id])) { throw new DomainException(t('راجع التحويل المقدم قبل الإلغاء.','Review the submitted transfer before cancellation.')); }
            release_reservation($o,'cancelled');
            Db::run('UPDATE orders SET status=? WHERE id=?',[$o['status']==='paid'?'refund_pending':'cancelled',$id]);
        } elseif ($next==='dispatching') {
            if ($o['status']!=='paid') { throw new DomainException(t('يجب تأكيد الدفع أولاً.','Payment must be confirmed first.')); }
            Db::run("UPDATE orders SET status='dispatching' WHERE id=?",[$id]);
        } else {
            if ($o['status']!=='dispatching' || post('delivered_checked',4)!=='yes') { throw new DomainException(t('أكد بدء الشحن واستلام المشتري للكمية أولاً.','Confirm dispatch and buyer receipt of the material first.')); }
            $s=Db::run('UPDATE batches SET quantity_g=quantity_g-?,reserved_g=reserved_g-? WHERE id=? AND quantity_g>=? AND reserved_g>=?',[$o['quantity_g'],$o['quantity_g'],$o['batch_id'],$o['quantity_g'],$o['quantity_g']]);
            if ($s->rowCount()!==1) { throw new LogicException('Stock fulfillment invariant broken'); }
            Db::run("UPDATE batches SET status='depleted' WHERE id=? AND quantity_g=0",[$o['batch_id']]);stock_move((int)$o['batch_id'],'delivered',-(int)$o['quantity_g'],-(int)$o['quantity_g'],$o['code']);
            $lines=[['account'=>2010,'debit'=>(int)$o['total'],'party'=>$o['buyer_id']],['account'=>2020,'credit'=>(int)$o['subtotal'],'party'=>$o['supplier_id']]];
            if ((int)$o['shipping']>0) { $lines[]=['account'=>2030,'credit'=>(int)$o['shipping']]; }
            if ((int)$o['service_fee']>0) { $lines[]=['account'=>4010,'credit'=>(int)$o['service_fee']]; }
            post_journal('order',$id,'fulfillment','Brokered transaction completed '.$o['code'],$lines,(int)$u['id']);
            Db::run("UPDATE orders SET status='delivered' WHERE id=?",[$id]);
        }
        audit('order_'.$next,'orders',$id,['note'=>$note]);
    });
}
function settle_order(): void {
    $u=permit('finance');$id=id_input('order_id');$type=choice('type',['seller','shipping','refund']);$amount=money_input('amount');$method=choice('method',['instapay','wallet']);$ref=required('reference',100);
    if (post('transfer_checked',4)!=='yes') { throw new DomainException(t('نفذ التحويل خارج المنصة وتحقق منه أولاً.','Complete and verify the outgoing transfer outside the platform first.')); }
    Db::tx(function () use ($u,$id,$type,$amount,$method,$ref): void {
        $o=order_detail($id,true);
        if (($type==='refund' && $o['status']!=='refund_pending') || ($type!=='refund' && $o['status']!=='delivered')) { throw new DomainException(t('حالة الطلب لا تسمح بالتسوية.','Order status does not allow this settlement.')); }
        $cap=(int)$o[$type==='seller'?'subtotal':($type==='shipping'?'shipping':'total')];
        $paid=(int)Db::scalar('SELECT COALESCE(SUM(amount),0) FROM settlements WHERE order_id=? AND type=?',[$id,$type]);
        if ($amount>$cap-$paid) { throw new DomainException(t('المبلغ يتجاوز المستحق المتبقي.','Amount exceeds the outstanding balance.')); }
        $sid=Db::insert('INSERT INTO settlements(order_id,type,amount,method,reference,created_by) VALUES(?,?,?,?,?,?)',[$id,$type,$amount,$method,$ref,$u['id']]);
        register_transfer($method,$ref,'outgoing',$amount,'settlement',$sid,(int)$u['id']);
        $account=['seller'=>2020,'shipping'=>2030,'refund'=>2010][$type];
        post_journal('settlement',$sid,'payout','Verified online '.$type.' transfer '.$o['code'],[['account'=>$account,'debit'=>$amount,'party'=>$type==='seller'?$o['supplier_id']:($type==='refund'?$o['buyer_id']:null)],['account'=>1010,'credit'=>$amount]],(int)$u['id']);
        if ($type==='refund' && $amount+$paid===$cap) { Db::run("UPDATE orders SET status='refunded' WHERE id=?",[$id]); }
        audit('settlement_recorded','settlements',$sid,['type'=>$type]);
    });
}
function create_production(): void {
    $u=permit('production'); $input=id_input('input_batch_id'); $q=quantity_input('input_quantity','input_unit'); $out=quantity_input('output_quantity','output_unit'); $material=id_input('material_id'); $partner=required('partner',190); $authorization=required('authorization',190); $notes=required('inspection_notes',2000); $price=money_input('price');
    if ($out>$q || post('authorized',4)!=='yes') { throw new DomainException(t('الكمية الناتجة لا تتجاوز المدخلات، وموافقة مالك المادة مطلوبة.','Output cannot exceed input, and material-owner authorization is required.')); }
    Db::tx(function () use ($u,$input,$q,$out,$material,$partner,$authorization,$notes,$price): void {
        $b=batch_detail($input,true);
        if (!in_array($b['status'],['published','inspected'],true) || $q>(int)$b['quantity_g']-(int)$b['reserved_g']) { throw new DomainException(t('المدخلات غير متاحة أو محجوزة لطلبات أخرى.','Input stock is unavailable or reserved for other orders.')); }
        if (!Db::one('SELECT id FROM materials WHERE id=? AND active=1',[$material])) { throw new DomainException(t('اختر مادة نشطة.','Select an active material.')); }
        $output=Db::insert('INSERT INTO batches(code,supplier_id,hub_id,material_id,grade,quantity_g,price_per_tonne,inspection_notes,inspected_by) VALUES(?,?,?,?,?,?,?,?,?)',[code('B'),$b['supplier_id'],$b['hub_id'],$material,$b['grade'],$out,$price,$notes,$u['id']]);
        Db::run('UPDATE batches SET quantity_g=quantity_g-? WHERE id=?',[$q,$input]);
        Db::run("UPDATE batches SET status='depleted' WHERE id=? AND quantity_g=0",[$input]);
        $job=Db::insert('INSERT INTO production_jobs(input_batch_id,output_batch_id,partner,authorization,input_g,output_g,loss_g,created_by) VALUES(?,?,?,?,?,?,?,?)',[$input,$output,$partner,$authorization,$q,$out,$q-$out,$u['id']]);
        stock_move($input,'partner_processing_input',-$q,0,'Production #'.$job); stock_move($output,'partner_processing_output',$out,0,'Production #'.$job); audit('partner_processing','production_jobs',$job,['authorization'=>$authorization]);
    });
}
function create_ticket(): void {
    live_required(); $u=member(); rate_limit('ticket-ip:'.($_SERVER['REMOTE_ADDR']??''),6,3600);
    $p=$u?$u['phone']:phone(required('phone',30)); $subject=required('subject',190); $message=required('message',3000); $order=post('order_id',20);
    if ($order!=='' && (!$u || !ctype_digit($order) || !Db::one('SELECT id FROM orders WHERE id=? AND user_id=?',[(int)$order,$u['id']]))) { throw new DomainException(t('الطلب غير موجود في حسابك.','Order not found in your account.')); }
    $id=Db::insert("INSERT INTO tickets(user_id,order_id,contact_phone,subject,message,response) VALUES(?,?,?,?,?,'')",[$u['id']??null,$order!==''?(int)$order:null,$p,$subject,$message]); audit('ticket_opened','tickets',$id);
}
function save_settings(): void {
    permit('settings');
    $amounts=['service_fee_flat','shipping_local_per_tonne','shipping_other_per_tonne','shipping_minimum'];$updates=[];
    foreach ($amounts as $key) { $updates[$key]=(string)money_input($key,true); }
    $updates['service_fee_bp']=(string)decimal_scaled(required('service_fee_percent',10),2,10000);
    foreach (['pickup_min_kg'=>[1,1000000],'order_hold_hours'=>[1,168]] as $key=>$range) { $v=required($key,10); if (!ctype_digit($v) || (int)$v<$range[0] || (int)$v>$range[1]) { throw new DomainException(t('راجع حدود القيم في الإعدادات.','Check the settings value limits.')); } $updates[$key]=(string)(int)$v; }
    foreach (['instapay_account'=>190,'contact_name'=>160,'legal_name'=>190,'legal_address'=>500] as $key=>$max) { $updates[$key]=post($key,$max); }
    foreach (['wallet_number','contact_phone'] as $key) { $v=post($key,30);$updates[$key]=$v!==''?phone($v):''; }
    $v=post('instapay_url',500);
    if ($v!=='') { $parts=parse_url($v); if (!filter_var($v,FILTER_VALIDATE_URL) || ($parts['scheme']??'')!=='https' || !in_array(strtolower($parts['host']??''),['ipn.eg','instapay.eg','www.instapay.eg'],true) || isset($parts['user']) || isset($parts['pass'])) { throw new DomainException(t('استخدم رابط HTTPS رسمي من InstaPay أو ipn.eg.','Use an official HTTPS InstaPay or ipn.eg link.')); } }
    $updates['instapay_url']=$v; $updates['pilot_governorate']=choice('pilot_governorate',array_keys(governorates()));$updates['launch_open']=post('launch_open',4)==='yes'?'1':'0';
    if ($updates['launch_open']==='1') {
        if ($updates['contact_phone']==='' || $updates['legal_name']==='' || $updates['legal_address']==='' || ($updates['wallet_number']==='' && $updates['instapay_account']==='')) { throw new DomainException(t('قبل الإطلاق أضف بيانات النشاط والدعم ووجهة دفع واحدة على الأقل.','Before launch, add business details, a support number, and at least one payment destination.')); }
        $otp=cfg('otp',[]); if (!local_otp_allowed() && (empty($otp['account_sid']) || empty($otp['auth_token']) || empty($otp['verify_service_sid']) || ($otp['driver']??'')!=='twilio')) { throw new DomainException(t('فعّل مزود رموز الدخول قبل فتح المنصة للجمهور.','Configure the login-code provider before opening the platform.')); }
        if (setting('demo_data','0')==='1' && cfg('app_env')==='production') { throw new DomainException(t('قاعدة البيانات تحتوي بيانات تدريبية. أنشئ قاعدة نظيفة قبل الإطلاق.','The database contains training records. Use a clean database before launch.')); }
    }
    Db::tx(function () use ($updates): void { foreach ($updates as $key=>$v) { Db::run('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$v]); } audit('settings_updated','settings',0,['keys'=>array_keys($updates)]); });
}
function create_staff(): void {
    permit('staff');$name=required('name',160);$email=strtolower(required('email',190));$password=required('password',512);$role=choice('role',['support','admin','super_admin']);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) { throw new DomainException(t('بريد صحيح وكلمة مرور من ١٢ حرفاً على الأقل مطلوبان.','A valid email and a password of at least 12 characters are required.')); }
    Db::tx(function () use ($name,$email,$password,$role): void {$id=Db::insert('INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);audit('staff_created','users',$id,['role'=>$role]);});
}
function handle_admin_action(string $action): void {
    $return=$_POST['return_section']??'dashboard';
    switch ($action) {
        case 'inspect': inspect_supply(); break;
        case 'confirm-payment': confirm_payment(); break;
        case 'order-status': fulfill_order(); break;
        case 'settle': settle_order(); break;
        case 'production': create_production(); break;
        case 'settings': save_settings(); break;
        case 'create-staff': create_staff(); break;
        case 'supply-status':
            $u=permit('supply');$id=id_input('request_id');$state=choice('status',['scheduled','rejected','cancelled']);
            if ($state!=='scheduled' && $u['role']==='support') { throw new DomainException(t('الإلغاء والرفض يحتاجان إلى مدير.','Cancellation and rejection require an administrator.')); }
            Db::tx(function () use ($id,$state): void { $r=Db::one('SELECT * FROM supply_requests WHERE id=? FOR UPDATE',[$id]); if (!$r || !in_array($r['status'],['new','scheduled'],true)) { throw new DomainException(t('لا يمكن تغيير هذه الحالة.','This status cannot be changed.')); } Db::run('UPDATE supply_requests SET status=? WHERE id=?',[$state,$id]);audit('supply_'.$state,'supply_requests',$id); });break;
        case 'batch-status':
            permit('inventory');$id=id_input('batch_id');$state=choice('status',['published','quarantined','inspected']);$note=required('note',500);
            Db::tx(function () use ($id,$state,$note): void { $b=batch_detail($id,true); if ((int)$b['reserved_g']>0 || (int)$b['quantity_g']===0) { throw new DomainException(t('لا يمكن تغيير دفعة محجوزة أو فارغة.','Reserved or empty batches cannot be changed.')); } if ($state==='published' && (!Db::one("SELECT id FROM hubs WHERE id=? AND active=1 AND address<>''",[$b['hub_id']]) || $b['status']!=='inspected')) { throw new DomainException(t('أعد اعتماد الفحص قبل نشر الدفعة.','Reapprove inspection before publishing the batch.')); } Db::run('UPDATE batches SET status=? WHERE id=?',[$state,$id]);audit('batch_'.$state,'batches',$id,['note'=>$note]); });break;
        case 'stock-adjust':
            permit('inventory');$id=id_input('batch_id');$q=quantity_input();$direction=choice('direction',['add','remove']);$note=required('note',500);
            Db::tx(function () use ($id,$q,$direction,$note): void { $b=batch_detail($id,true); if ($direction==='remove' && $q>(int)$b['quantity_g']-(int)$b['reserved_g']) { throw new DomainException(t('التعديل يتجاوز الكمية غير المحجوزة.','Adjustment exceeds unreserved stock.')); } $delta=$direction==='add'?$q:-$q; if ((int)$b['quantity_g']+$delta>1000000000) { throw new DomainException(t('الكمية تتجاوز حد الدفعة.','Quantity exceeds the batch limit.')); } Db::run("UPDATE batches SET quantity_g=quantity_g+?,status='inspected' WHERE id=?",[$delta,$id]);stock_move($id,'adjustment',$delta,0,$note);audit('stock_adjusted','batches',$id,['delta_g'=>$delta,'note'=>$note]); });break;
        case 'extend-order':
            permit('orders');$id=id_input('order_id');$note=required('note',500);
            Db::tx(function () use ($id,$note): void {$o=order_detail($id,true);if (!in_array($o['status'],['quoted','payment_review'],true)) {throw new DomainException(t('الحجز لا يحتاج تمديداً في هذه الحالة.','The reservation cannot be extended in this state.'));}Db::run('UPDATE orders SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()+(int)setting('order_hold_hours','48')*3600),$id]);audit('reservation_extended','orders',$id,['note'=>$note]);});break;
        case 'party':
            permit('parties');$kind=choice('kind',['seller','buyer','transporter','vendor']);$name=required('name',160);$p=phone(required('phone',30));$org=post('organization',190);$gov=choice('governorate',array_keys(governorates()));$address=post('address',500);
            Db::tx(function () use ($kind,$name,$p,$org,$gov,$address): void {$id=Db::insert('INSERT INTO parties(kind,name,phone,organization,governorate,address) VALUES(?,?,?,?,?,?)',[$kind,$name,$p,$org,$gov,$address]);audit('party_created','parties',$id);});break;
        case 'shipment':
            $u=permit('logistics');$target=required('target',60);if(!preg_match('/^(order|supply):([1-9][0-9]{0,18})$/',$target,$targetParts)){throw new DomainException(t('اختر الطلب المرتبط بالرحلة.','Select the shipment request.'));}$link=$targetParts[1];$id=(int)$targetParts[2];$name=required('driver_name',160);$p=phone(required('driver_phone',30));$vehicle=required('vehicle',100);$when=datetime_input('scheduled_at');$notes=post('notes',500);
            Db::tx(function () use ($u,$link,$id,$name,$p,$vehicle,$when,$notes): void {
                if ($link==='order') { $r=Db::one("SELECT id FROM orders WHERE id=? AND shipping_mode='hasad' AND status IN ('paid','dispatching') FOR UPDATE",[$id]); }
                else { $r=Db::one("SELECT id FROM supply_requests WHERE id=? AND handover='pickup' AND status IN ('new','scheduled') FOR UPDATE",[$id]); }
                if (!$r) { throw new DomainException(t('الطلب غير مؤهل لرحلة نقل.','This request is not eligible for transport.')); }
                if (Db::one('SELECT id FROM shipments WHERE '.($link==='order'?'order_id':'request_id')."=? AND status IN ('planned','in_transit')",[$id])) { throw new DomainException(t('توجد رحلة نشطة بالفعل.','There is already an active shipment.')); }
                $sid=Db::insert('INSERT INTO shipments(order_id,request_id,driver_name,driver_phone,vehicle,scheduled_at,notes,created_by) VALUES(?,?,?,?,?,?,?,?)',[$link==='order'?$id:null,$link==='supply'?$id:null,$name,$p,$vehicle,(new DateTimeImmutable($when,new DateTimeZone('Africa/Cairo')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$notes,$u['id']]);
                if ($link==='supply') { Db::run("UPDATE supply_requests SET status='scheduled' WHERE id=?",[$id]); }
                audit('shipment_created','shipments',$sid);
            });break;
        case 'shipment-status':
            permit('logistics');$id=id_input('shipment_id');$state=choice('status',['in_transit','completed','cancelled']);
            Db::tx(function () use ($id,$state): void {$r=Db::one('SELECT * FROM shipments WHERE id=? FOR UPDATE',[$id]);$valid=$r && (($r['status']==='planned' && in_array($state,['in_transit','cancelled'],true)) || ($r['status']==='in_transit' && in_array($state,['completed','cancelled'],true)));if (!$valid) {throw new DomainException(t('انتقال حالة غير صالح.','Invalid status transition.'));}Db::run('UPDATE shipments SET status=? WHERE id=?',[$state,$id]);audit('shipment_'.$state,'shipments',$id);});break;
        case 'procurement':
            $u=permit('procurement');$supplier=id_input('supplier_id');$desc=required('description',500);$amount=money_input('amount');$due=date_input('due_date');
            if (!Db::one("SELECT id FROM parties WHERE id=? AND kind='vendor'",[$supplier])) {throw new DomainException(t('اختر مورد تشغيل.','Select an operating vendor.'));}
            Db::tx(function () use ($u,$supplier,$desc,$amount,$due): void {$id=Db::insert('INSERT INTO procurement(supplier_id,description,amount,due_date,created_by) VALUES(?,?,?,?,?)',[$supplier,$desc,$amount,$due,$u['id']]);audit('procurement_created','procurement',$id);});break;
        case 'procurement-status':
            $u=permit('procurement');$id=id_input('procurement_id');$state=choice('status',['approved','paid','cancelled']);
            Db::tx(function () use ($u,$id,$state): void {
                $r=Db::one('SELECT * FROM procurement WHERE id=? FOR UPDATE',[$id]);
                if (!$r || !(($r['status']==='draft' && in_array($state,['approved','cancelled'],true)) || ($r['status']==='approved' && $state==='paid'))) {throw new DomainException(t('انتقال حالة غير صالح.','Invalid status transition.'));}
                if ($state==='approved') {post_journal('procurement',$id,'approved',$r['description'],[['account'=>5010,'debit'=>(int)$r['amount']],['account'=>2040,'credit'=>(int)$r['amount'],'party'=>$r['supplier_id']]],(int)$u['id']);}
                if ($state==='paid') { $method=choice('method',['instapay','wallet']);$ref=required('reference',100);if (post('transfer_checked',4)!=='yes') {throw new DomainException(t('تحقق من التحويل أولاً.','Verify the outgoing transfer first.'));}Db::run('UPDATE procurement SET payment_method=?,payment_reference=? WHERE id=?',[$method,$ref,$id]);register_transfer($method,$ref,'outgoing',(int)$r['amount'],'procurement',$id,(int)$u['id']);post_journal('procurement',$id,'paid',$r['description'],[['account'=>2040,'debit'=>(int)$r['amount'],'party'=>$r['supplier_id']],['account'=>1010,'credit'=>(int)$r['amount']]],(int)$u['id']);}
                Db::run('UPDATE procurement SET status=? WHERE id=?',[$state,$id]);audit('procurement_'.$state,'procurement',$id);
            });break;
        case 'project':
            $u=permit('projects');$name=required('name',190);$budget=money_input('budget',true);$state=choice('status',['planned','active','completed','on_hold']);$due=date_input('due_date',true);$notes=post('notes',500);
            Db::tx(function () use ($u,$name,$budget,$state,$due,$notes): void {$id=Db::insert('INSERT INTO projects(name,budget,status,due_date,notes,created_by) VALUES(?,?,?,?,?,?)',[$name,$budget,$state,$due,$notes,$u['id']]);audit('project_created','projects',$id);});break;
        case 'project-status':
            permit('projects');$id=id_input('project_id');$state=choice('status',['planned','active','completed','on_hold']);Db::tx(function () use ($id,$state): void {Db::run('UPDATE projects SET status=? WHERE id=?',[$state,$id]);audit('project_status','projects',$id,['status'=>$state]);});break;
        case 'expense':
            $u=permit('finance');$description=required('description',500);$amount=money_input('amount');$method=choice('method',['instapay','wallet']);$ref=required('reference',100);$project=post('project_id',20);
            if (post('transfer_checked',4)!=='yes') {throw new DomainException(t('تحقق من التحويل أولاً.','Verify the outgoing transfer first.'));}
            Db::tx(function () use ($u,$description,$amount,$method,$ref,$project): void {$id=Db::insert('INSERT INTO expenses(project_id,description,amount,method,reference,created_by) VALUES(?,?,?,?,?,?)',[$project!==''?(int)$project:null,$description,$amount,$method,$ref,$u['id']]);register_transfer($method,$ref,'outgoing',$amount,'expense',$id,(int)$u['id']);post_journal('expense',$id,'paid',$description,[['account'=>5010,'debit'=>$amount],['account'=>1010,'credit'=>$amount]],(int)$u['id']);audit('expense_recorded','expenses',$id);});break;
        case 'journal':
            $u=permit('finance');$debit=id_input('debit_account');$credit=id_input('credit_account');$amount=money_input('amount');$desc=required('description',500);
            if ($debit===$credit) {throw new DomainException(t('اختر حسابين مختلفين.','Choose different debit and credit accounts.'));}
            Db::tx(function () use ($u,$debit,$credit,$amount,$desc): void {$id=post_journal('manual',random_int(1,PHP_INT_MAX),'adjustment',$desc,[['account'=>$debit,'debit'=>$amount],['account'=>$credit,'credit'=>$amount]],(int)$u['id']);audit('manual_journal','journals',$id);});break;
        case 'task':
            $u=permit('tasks');$title=required('title',190);$assignee=post('assignee_id',20);$project=post('project_id',20);$due=date_input('due_date',true);$priority=choice('priority',['normal','high']);
            if ($assignee!=='' && !Db::one("SELECT id FROM users WHERE id=? AND active=1 AND role<>'member'",[(int)$assignee])) {throw new DomainException(t('اختر موظفاً نشطاً.','Select an active staff member.'));}
            if ($u['role']==='support' && ($assignee!=='' && (int)$assignee!==(int)$u['id'] || $project!=='')) {throw new DomainException(t('الدعم يمكنه إنشاء مهام لنفسه فقط.','Support can create tasks only for themselves.'));}
            Db::tx(function () use ($u,$title,$assignee,$project,$due,$priority): void {$id=Db::insert('INSERT INTO tasks(title,assignee_id,project_id,due_date,priority,created_by) VALUES(?,?,?,?,?,?)',[$title,$assignee!==''?(int)$assignee:null,$project!==''?(int)$project:null,$due,$priority,$u['id']]);audit('task_created','tasks',$id);});break;
        case 'task-status':
            $u=permit('tasks');$id=id_input('task_id');$state=choice('status',['todo','doing','done']);$r=Db::one('SELECT * FROM tasks WHERE id=?',[$id]);if (!$r || ($u['role']==='support' && (int)$r['assignee_id']!==(int)$u['id'])) {throw new DomainException(t('لا يمكنك تعديل هذه المهمة.','You cannot change this task.'));}Db::tx(function () use ($id,$state): void {Db::run('UPDATE tasks SET status=? WHERE id=?',[$state,$id]);audit('task_status','tasks',$id,['status'=>$state]);});break;
        case 'attendance':
            permit('people');$id=id_input('user_id');$date=date_input('work_date');$minutes=required('minutes',5);$notes=post('notes',500);if (!ctype_digit($minutes) || (int)$minutes>1440 || !Db::one("SELECT id FROM users WHERE id=? AND role<>'member'",[$id])) {throw new DomainException(t('راجع الموظف وعدد دقائق العمل.','Check the staff member and work minutes.'));}Db::tx(function () use ($id,$date,$minutes,$notes): void {Db::run('INSERT INTO attendance(user_id,work_date,minutes,notes) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE minutes=VALUES(minutes),notes=VALUES(notes)',[$id,$date,(int)$minutes,$notes]);audit('attendance_recorded','users',$id,['work_date'=>$date]);});break;
        case 'staff-active':
            $u=permit('staff');$id=id_input('user_id');$state=choice('active',['0','1']);Db::tx(function () use ($u,$id,$state): void {
                // Serialize changes to administrator access and preserve a usable super-admin account.
                Db::one("SELECT * FROM settings WHERE setting_key='launch_open' FOR UPDATE");
                $r=Db::one("SELECT * FROM users WHERE id=? AND role<>'member' FOR UPDATE",[$id]);if (!$r || (int)$u['id']===$id || ($r['role']==='super_admin' && $state==='0' && (int)Db::scalar("SELECT COUNT(*) FROM users WHERE role='super_admin' AND active=1")<=1)) {throw new DomainException(t('لا يمكن تعطيل هذا الحساب.','This account cannot be disabled.'));}Db::run('UPDATE users SET active=? WHERE id=?',[(int)$state,$id]);audit('staff_access','users',$id,['active'=>$state]);});break;
        case 'password':
            $u=staff_required();$old=required('current_password',512);$new=required('new_password',512);if (!password_verify($old,$u['password_hash']) || strlen($new)<12) {throw new DomainException(t('تحقق من كلمة المرور الحالية، والجديدة من ١٢ حرفاً على الأقل.','Check the current password. The new password needs at least 12 characters.'));}Db::tx(function () use ($u,$new): void {Db::run('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);audit('password_changed','users',(int)$u['id']);});break;
        case 'ticket-response':
            permit('support');$id=id_input('ticket_id');$state=choice('status',['open','in_progress','closed']);$response=required('response',3000);Db::tx(function () use ($id,$state,$response): void {Db::run('UPDATE tickets SET status=?,response=? WHERE id=?',[$state,$response,$id]);audit('ticket_responded','tickets',$id);});break;
        case 'hub-update':
            permit('settings');$id=id_input('hub_id');$ar=required('name_ar',160);$en=required('name_en',160);$gov=choice('governorate',array_keys(governorates()));$address=required('address',500);$active=choice('active',['0','1']);
            Db::tx(function () use ($id,$ar,$en,$gov,$address,$active): void {Db::run('UPDATE hubs SET name_ar=?,name_en=?,governorate=?,address=?,active=? WHERE id=?',[$ar,$en,$gov,$address,(int)$active,$id]);audit('hub_updated','hubs',$id);});break;
        case 'hub':
            permit('settings');$ar=required('name_ar',160);$en=required('name_en',160);$gov=choice('governorate',array_keys(governorates()));$address=required('address',500);Db::tx(function () use ($ar,$en,$gov,$address): void {$id=Db::insert('INSERT INTO hubs(name_ar,name_en,governorate,address) VALUES(?,?,?,?)',[$ar,$en,$gov,$address]);audit('hub_created','hubs',$id);});break;
        case 'material':
            permit('settings');$ar=required('name_ar',160);$en=required('name_en',160);$slug=required('slug',80);$dar=required('description_ar',2000);$den=required('description_en',2000);if (!preg_match('/^[a-z0-9-]{2,80}$/',$slug)) {throw new DomainException(t('معرّف المادة يستخدم حروفاً إنجليزية وأرقاماً وشرطات.','Material slug uses lowercase letters, numbers and hyphens.'));}Db::tx(function () use ($ar,$en,$slug,$dar,$den): void {$id=Db::insert('INSERT INTO materials(name_ar,name_en,slug,description_ar,description_en) VALUES(?,?,?,?,?)',[$ar,$en,$slug,$dar,$den]);audit('material_created','materials',$id);});break;
        default: throw new DomainException(t('عملية غير معروفة.','Unknown action.'));
    }
    flash(t('تم حفظ العملية بنجاح.','The operation was saved successfully.'));
    redirect('admin',['section'=>in_array($return,array_keys(admin_sections()),true)?$return:'dashboard']);
}
function admin_sections(): array {
    return ['dashboard'=>[t('نظرة عامة','Overview'),'chart'], 'supply'=>[t('طلبات البائعين','Seller intake'),'wheat'], 'inspection'=>[t('الفحص والجودة','Inspection & quality'),'shield'], 'inventory'=>[t('المخزون والأمانات','Consignment stock'),'box'], 'orders'=>[t('المبيعات والطلبات','Sales & orders'),'layers'], 'payments'=>[t('مراجعة التحويلات','Transfer review'),'wallet'], 'logistics'=>[t('النقل والتجميع','Logistics'),'truck'], 'parties'=>[t('العلاقات والعملاء','CRM & partners'),'users'], 'procurement'=>[t('مشتريات التشغيل','Procurement'),'box'], 'production'=>[t('عمليات التدوير','Partner processing'),'recycle'], 'finance'=>[t('المالية والحسابات','Finance & accounting'),'wallet'], 'projects'=>[t('المشروعات','Projects'),'layers'], 'tasks'=>[t('المهام','Tasks'),'check'], 'people'=>[t('الفريق والحضور','Team & attendance'),'users'], 'support'=>[t('الدعم','Support'),'phone'], 'reports'=>[t('التقارير','Reports'),'chart'], 'settings'=>[t('الإعدادات','Settings'),'settings'], 'staff'=>[t('صلاحيات الإدارة','Admin access'),'shield'], 'audit'=>[t('سجل العمليات','Audit trail'),'clock']];
}
