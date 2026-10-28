<?php
declare(strict_types=1);
function cfg(string $key, mixed $default = null): mixed { return $GLOBALS['config'][$key] ?? $default; }
function lang(): string { return $GLOBALS['lang'] ?? 'ar'; }
function t(string $ar, string $en): string { return lang() === 'ar' ? $ar : $en; }
function e(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443; }
function base_path(): string { $p = parse_url((string) cfg('app_url', ''), PHP_URL_PATH); return rtrim(is_string($p) ? $p : '', '/'); }
function url(string $route = 'home', array $params = []): string { return (base_path() ?: '') . '/index.php?' . http_build_query(['page' => $route] + $params); }
function asset(string $file): string { return (base_path() ?: '') . '/assets/' . $file; }
function redirect(string $route, array $params = []): never { header('Location: ' . url($route, $params), true, 303); exit; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(403); throw new DomainException(t('انتهت صلاحية النموذج. افتح الصفحة مرة أخرى.','The form expired. Please reopen the page.'));
    }
}
function post(string $key, int $max = 500): string {
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || mb_strlen($value) > $max) { throw new DomainException(t('يرجى مراجعة الحقول المطلوبة.','Please check the required fields.')); }
    return trim($value);
}
function required(string $key, int $max = 500): string { $v = post($key, $max); if ($v === '') { throw new DomainException(t('أكمل جميع الحقول المطلوبة.','Complete all required fields.')); } return $v; }
function id_input(string $key): int { $v = post($key, 20); if (!ctype_digit($v) || (int) $v < 1) { throw new DomainException(t('اختيار غير صالح.','Invalid selection.')); } return (int) $v; }
function choice(string $key, array $allowed): string { $v = required($key, 80); if (!in_array($v, $allowed, true)) { throw new DomainException(t('اختيار غير صالح.','Invalid selection.')); } return $v; }
function date_input(string $key, bool $optional = false): ?string { $v = post($key, 10); if (!$v && $optional) { return null; } $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v); if (!$d || $d->format('Y-m-d') !== $v) { throw new DomainException(t('تاريخ غير صالح.','Invalid date.')); } return $v; }
function datetime_input(string $key): string { $v = post($key, 16); $d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v); if (!$d || $d->format('Y-m-d\TH:i') !== $v) { throw new DomainException(t('موعد غير صالح.','Invalid date and time.')); } return $d->format('Y-m-d H:i:s'); }
function normalize_digits(string $value): string { return strtr($value, ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.','۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); }
function phone(string $value): string {
    $v = preg_replace('/[\s()\-]/', '', normalize_digits($value));
    if (str_starts_with($v, '0020')) { $v = '+20' . substr($v, 4); }
    if (preg_match('/^01[0125]\d{8}$/', $v)) { $v = '+20' . substr($v, 1); }
    if (!preg_match('/^\+201[0125]\d{8}$/', $v)) { throw new DomainException(t('اكتب رقم موبايل مصري صحيح، مثل 01012345678.','Enter a valid Egyptian mobile number, such as 01012345678.')); }
    return $v;
}
function decimal_scaled(string $value, int $precision, int $max): int {
    $v = normalize_digits(trim($value));
    if (!preg_match('/^\d{1,12}(?:\.\d{1,' . $precision . '})?$/', $v)) { throw new DomainException(t('اكتب رقماً موجباً بدقة مناسبة.','Enter a positive number with the permitted precision.')); }
    [$whole, $fraction] = array_pad(explode('.', $v, 2), 2, '');
    $factor = 10 ** $precision;
    if ((int) $whole > intdiv($max, $factor)) { throw new DomainException(t('القيمة أكبر من الحد المسموح.','Value exceeds the allowed limit.')); }
    $n = (int) $whole * $factor + (int) str_pad($fraction, $precision, '0');
    if ($n > $max) { throw new DomainException(t('القيمة أكبر من الحد المسموح.','Value exceeds the allowed limit.')); }
    return $n;
}
function money_input(string $key, bool $allowZero = false): int { $v = decimal_scaled(required($key, 30), 2, 100000000000); if (!$allowZero && $v === 0) { throw new DomainException(t('المبلغ يجب أن يكون أكبر من صفر.','Amount must be greater than zero.')); } return $v; }
function quantity_input(string $key = 'quantity', string $unitKey = 'unit'): int {
    $unit = choice($unitKey, ['kg','tonne']);
    $v = decimal_scaled(required($key, 30), 3, $unit === 'kg' ? 1000000000 : 1000000);
    $g = $unit === 'kg' ? $v : $v * 1000;
    if ($g < 1000 || $g > 1000000000) { throw new DomainException(t('الكمية من ١ كجم إلى ١٠٠٠ طن.','Quantity must be between 1 kg and 1,000 tonnes.')); }
    return $g;
}
function round_ratio(int $n, int $d): int { if ($n < 0 || $d < 1) { throw new LogicException('Invalid rounding input'); } return intdiv($n + intdiv($d, 2), $d); }
function quantity_cost(int $grams,int $pricePerTonne): int {
    $v=intdiv($grams,1000000)*$pricePerTonne + round_ratio(($grams%1000000)*$pricePerTonne,1000000);
    if ($v>100000000000) {throw new DomainException(t('قيمة الطلب تتجاوز الحد المسموح. تواصل مع حصاد لترتيب التوريد.','The order value exceeds the permitted limit. Contact Hasad to coordinate supply.'));}
    return $v;
}
function money(int $p): string { return number_format($p / 100, 2) . ' ' . t('ج.م','EGP'); }
function qty(int $g, string $unit = 'tonne'): string { return number_format($g / ($unit === 'kg' ? 1000 : 1000000), $unit === 'kg' ? 1 : 3) . ' ' . ($unit === 'kg' ? t('كجم','kg') : t('طن','t')); }
function positive(int $v): int { if ($v < 1) { throw new DomainException(t('القيمة يجب أن تكون أكبر من صفر.','Value must be greater than zero.')); } return $v; }
function setting(string $key, string $default = ''): string { if (!ready()) { return $default; } $r = Db::one('SELECT setting_value FROM settings WHERE setting_key=?', [$key]); return $r ? $r['setting_value'] : $default; }
function ready(): bool { if(!empty($GLOBALS['error_rendering'])){return false;}static $known=null;if($known!==null){return $known;}try { return $known= !empty(cfg('db')) && Db::one('SELECT setting_key FROM settings LIMIT 1') !== null; } catch (Throwable) { return $known=false; } }
function live_required(): void { if (!ready() || setting('launch_open','0') !== '1') { throw new DomainException(t('المنصة قيد تجهيز الإطلاق. استقبال الطلبات سيفتح بعد تفعيل الإدارة.','The platform is preparing to launch. Requests open after administrator activation.')); } }
function code(string $kind): string { return 'HS-' . $kind . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4))); }
function flash(string $message): void { $_SESSION['flash'] = $message; }
function render(string $view, array $data = []): void { extract($data, EXTR_SKIP); $viewFile = HASAD_ROOT . '/views/' . $view . '.php'; require HASAD_ROOT . '/views/layout.php'; }
function governorates(): array { return ['Sharqia'=>['الشرقية','Sharkia'],'Dakahlia'=>['الدقهلية','Dakahlia'],'Beheira'=>['البحيرة','Beheira'],'Gharbia'=>['الغربية','Gharbia'],'Kafr El Sheikh'=>['كفر الشيخ','Kafr El Sheikh'],'Menoufia'=>['المنوفية','Menoufia'],'Qalyubia'=>['القليوبية','Qalyubia'],'Alexandria'=>['الإسكندرية','Alexandria'],'Cairo'=>['القاهرة','Cairo'],'Giza'=>['الجيزة','Giza'],'Fayoum'=>['الفيوم','Fayoum'],'Beni Suef'=>['بني سويف','Beni Suef'],'Minya'=>['المنيا','Minya'],'Asyut'=>['أسيوط','Asyut'],'Sohag'=>['سوهاج','Sohag'],'Qena'=>['قنا','Qena'],'Luxor'=>['الأقصر','Luxor'],'Aswan'=>['أسوان','Aswan'],'Damietta'=>['دمياط','Damietta'],'Port Said'=>['بورسعيد','Port Said'],'Ismailia'=>['الإسماعيلية','Ismailia'],'Suez'=>['السويس','Suez'],'North Sinai'=>['شمال سيناء','North Sinai'],'South Sinai'=>['جنوب سيناء','South Sinai'],'Red Sea'=>['البحر الأحمر','Red Sea'],'New Valley'=>['الوادي الجديد','New Valley'],'Matrouh'=>['مطروح','Matrouh']]; }
function gov_label(string $g): string { $a = governorates()[$g] ?? [$g,$g]; return t($a[0],$a[1]); }
function gov_options(string $selected = 'Sharqia'): string { $s = ''; foreach (governorates() as $key => $v) { $s .= '<option value="'.e($key).'" '.($key === $selected ? 'selected' : '').'>'.e(t($v[0],$v[1])).'</option>'; } return $s; }
function localized(array $r, string $key = 'name'): string { return (string) ($r[$key . '_' . lang()] ?? $r[$key] ?? ''); }
function state_label(string $s): string { $labels = ['new'=>['جديد','New'],'scheduled'=>['مجدول','Scheduled'],'inspected'=>['تم الفحص','Inspected'],'rejected'=>['مرفوض','Rejected'],'cancelled'=>['ملغى','Cancelled'],'published'=>['متاح','Available'],'quarantined'=>['موقوف للفحص','Quarantined'],'depleted'=>['نفدت الكمية','Depleted'],'quoted'=>['بانتظار الدفع','Awaiting payment'],'payment_review'=>['الدفع قيد المراجعة','Payment under review'],'paid'=>['تم تأكيد الدفع','Payment confirmed'],'dispatching'=>['جارٍ التوصيل','Dispatching'],'delivered'=>['تم التسليم','Delivered'],'refund_pending'=>['بانتظار رد المبلغ','Refund pending'],'refunded'=>['تم رد المبلغ','Refunded'],'pending'=>['قيد المراجعة','Pending'],'confirmed'=>['مؤكد','Confirmed'],'planned'=>['مخطط','Planned'],'active'=>['نشط','Active'],'completed'=>['مكتمل','Completed'],'in_transit'=>['في الطريق','In transit'],'on_hold'=>['معلق','On hold'],'draft'=>['مسودة','Draft'],'approved'=>['معتمد','Approved'],'todo'=>['للعمل','To do'],'doing'=>['قيد التنفيذ','In progress'],'done'=>['منجز','Done'],'open'=>['مفتوح','Open'],'in_progress'=>['قيد المعالجة','In progress'],'closed'=>['مغلق','Closed'],'seller'=>['بائع','Seller'],'buyer'=>['مشتري','Buyer'],'support'=>['دعم','Support'],'admin'=>['مدير','Admin'],'super_admin'=>['مدير النظام','Super admin'],'vendor'=>['مورد تشغيل','Operating vendor'],'transporter'=>['ناقل','Transporter'],'member'=>['مستخدم','Member'],'pickup'=>['استلام من المزرعة','Farm pickup'],'dropoff'=>['تسليم للمركز','Hub drop-off']]; return isset($labels[$s]) ? t(...$labels[$s]) : $s; }
function badge(string $s): string { return '<span class="badge badge-' . e($s) . '">' . e(state_label($s)) . '</span>'; }
function icon(string $name, string $class = ''): string {
    $paths = ['arrow'=>'M5 12h14m-6-6 6 6-6 6','leaf'=>'M20 4C8 3 3 9 6 17c8 3 14-2 14-13ZM6 17l9-9','wheat'=>'M12 22V3m0 7C6 10 6 5 6 5c6 0 6 5 6 5Zm0 5c-6 0-6-5-6-5 6 0 6 5 6 5Zm0-7c6 0 6-5 6-5-6 0-6 5-6 5Zm0 7c6 0 6-5 6-5-6 0-6 5-6 5Z','sprout'=>'M12 22V11M12 13C5 13 3 8 3 4c6 0 9 3 9 9Zm0-4c0-4 4-6 9-6 0 5-3 8-9 8','truck'=>'M2 6h12v11H2ZM14 10h4l4 4v3h-8M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z','check'=>'m5 12 4 4L19 6','shield'=>'M12 2 3 6v6c0 5 9 10 9 10s9-5 9-10V6ZM8 12l3 3 5-6','layers'=>'m12 3-10 5 10 5 10-5-10-5ZM2 12l10 5 10-5M2 17l10 5 10-5','phone'=>'M6 3h4l1 5-3 2c2 4 3 5 6 6l2-3 5 1v4c0 7-19-6-15-15Z','chart'=>'M4 3v18h17M8 16v-5m5 5V7m5 9V4','box'=>'m12 2-9 5v10l9 5 9-5V7ZM3 7l9 5 9-5M12 12v10','wallet'=>'M3 6h17v14H3ZM3 6V3h14v3M16 12h5v4h-5Z','users'=>'M16 21v-3c0-4-12-4-12 0v3M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-8c4 1 4 7 0 8m1 4c3 1 3 3 3 6','clock'=>'M12 8v5l3 2M22 12a10 10 0 1 0-20 0 10 10 0 0 0 20 0Z','search'=>'M16 16l5 5M18 10a8 8 0 1 0-16 0 8 8 0 0 0 16 0Z','play'=>'m9 5 10 7-10 7V5Z','menu'=>'M3 6h18M3 12h18M3 18h18','close'=>'m6 6 12 12M6 18 18 6','globe'=>'M2 12h20M12 2c7 8 7 12 0 20-7-8-7-12 0-20Zm10 10a10 10 0 1 0-20 0 10 10 0 0 0 20 0Z','settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0-6v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2','recycle'=>'m8 3 4-1 4 7h-5l1-3M3 13l-2 3 4 6h8l-2-4H6m13-6 4 1 1 7h-8l2-4h3'];
    return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name] ?? $paths['leaf']).'"/></svg>';
}
function field(string $label, string $name, string $type = 'text', string $value = '', string $extra = ''): void { echo '<label class="field"><span>'.e($label).'</span><input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" '.$extra.'></label>'; }
function select_field(string $label, string $name, array $options, string $selected = ''): void { echo '<label class="field"><span>'.e($label).'</span><select name="'.e($name).'" required>'; foreach ($options as $k => $v) { echo '<option value="'.e($k).'" '.((string)$k === $selected ? 'selected' : '').'>'.e($v).'</option>'; } echo '</select></label>'; }
function csv_cell(mixed $v): string { $s = (string)$v; return preg_match('/^[\s]*[=+@\-]/u',$s) ? "'" . $s : $s; }
