<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lower_text(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function text_length(string $value): int { if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8'); $count = preg_match_all('/./us', $value, $matches); return $count === false ? strlen($value) : $count; }
function short_text(string $value, int $width, string $suffix='…'): string { return function_exists('mb_strimwidth') ? mb_strimwidth($value, 0, $width, $suffix, 'UTF-8') : (strlen($value)>$width ? substr($value,0,max(0,$width-strlen($suffix))).$suffix : $value); }
function url(string $route = 'home', array $params = []): string {
    $params = ['route' => $route] + $params;
    return 'index.php?' . http_build_query($params);
}
function asset_url(string $relativePath): string {
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($relativePath === '' || str_contains($relativePath, '..')) {
        throw new InvalidArgumentException('Invalid asset path.');
    }
    $absolutePath = dirname(__DIR__) . '/public/' . $relativePath;
    $version = is_file($absolutePath) ? (string)filemtime($absolutePath) : '1';
    return $relativePath . '?v=' . rawurlencode($version);
}
function redirect(string $route = 'home', array $params = []): never { header('Location: ' . url($route, $params)); exit; }
function money(float|int|string|null $amount): string { return '৳' . number_format((float)$amount, 2); }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
        http_response_code(419); exit('Your form session expired. Go back, refresh the page and try again.');
    }
}
function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type'=>$type, 'message'=>$message]; }
function pull_flashes(): array { $items = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $items; }
function current_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cached = null;
    if ($cached !== null && (int)$cached['id'] === (int)$_SESSION['user_id']) return $cached;
    $st = $pdo->prepare('SELECT id, first_name, last_name, email, phone, country, district, city, occupation, skills, organization, website, user_type, status, created_at FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$_SESSION['user_id']]);
    $user = $st->fetch();
    if (!$user || $user['status'] !== 'active') { unset($_SESSION['user_id']); return null; }
    $cached = $user;
    return $user;
}
function has_role(?array $user, string $role): bool {
    if (!$user) return false;
    if ($user['user_type'] === 'admin') return $role === 'admin';
    return $user['user_type'] === $role || ($user['user_type'] === 'both' && in_array($role, ['worker','publisher'], true));
}
function require_login(PDO $pdo): array { $user=current_user($pdo); if(!$user){flash('warning','Please log in to continue.');redirect('login');} return $user; }
function require_role(PDO $pdo, string $role): array {
    $user=require_login($pdo);
    if (!has_role($user,$role)) { http_response_code(403); flash('error','You do not have permission to access that area.'); redirect('dashboard'); }
    return $user;
}
function lock_active_account(PDO $pdo, int $userId): array {
    $st = $pdo->prepare('SELECT id,user_type,status FROM users WHERE id=? FOR UPDATE');
    $st->execute([$userId]);
    $account = $st->fetch();
    if (!$account || $account['status'] !== 'active') throw new RuntimeException('This account is no longer active. Please contact TaskHaat support.');
    return $account;
}
function get_setting(PDO $pdo, string $key, string $default=''): string {
    $st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1'); $st->execute([$key]); $v=$st->fetchColumn(); return $v===false?$default:(string)$v;
}
function set_setting(PDO $pdo, string $key, string $value): void {
    $st=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'); $st->execute([$key,$value]);
}
function notify(PDO $pdo, int $userId, string $title, string $body, string $type='general', ?string $link=null): void {
    $st=$pdo->prepare('INSERT INTO notifications(user_id,title,body,type,link) VALUES(?,?,?,?,?)'); $st->execute([$userId,$title,$body,$type,$link]);
}
function notify_admins(PDO $pdo, string $title, string $body, string $type='admin'): void {
    foreach ($pdo->query("SELECT id FROM users WHERE user_type='admin' AND status='active'")->fetchAll() as $admin) notify($pdo,(int)$admin['id'],$title,$body,$type,url('dashboard',['tab'=>'moderation']));
}
function audit(PDO $pdo, ?int $actorId, string $action, string $entity, ?int $entityId, array $meta=[]): void {
    $st=$pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,meta_json,ip_address) VALUES(?,?,?,?,?,?)');
    $st->execute([$actorId,$action,$entity,$entityId,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR']??null]);
}
function record_wallet(PDO $pdo, int $userId, string $bucket, string $direction, float $amount, string $type, string $description, ?string $refType=null, ?int $refId=null, ?string $idempotencyKey=null): void {
    if ($amount <= 0) return;
    $st=$pdo->prepare('INSERT INTO wallet_transactions(user_id,bucket,direction,amount,transaction_type,description,reference_type,reference_id,idempotency_key) VALUES(?,?,?,?,?,?,?,?,?)');
    $st->execute([$userId,$bucket,$direction,number_format($amount,2,'.',''),$type,$description,$refType,$refId,$idempotencyKey]);
}

/** Encrypt payout account references before storing them. Legacy plaintext values remain readable. */
function encrypt_account_reference(string $plainText): string {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to securely store payout details.');
    $key = hash('sha256', APP_KEY, true);
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'TaskHaat withdrawal v1');
    if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('Could not securely store the payout account reference.');
    return 'enc:v1:' . base64_encode($nonce . $tag . $cipher);
}

function decrypt_account_reference(string $storedValue): string {
    if (!str_starts_with($storedValue, 'enc:v1:')) return $storedValue; // legacy demo rows
    $payload = base64_decode(substr($storedValue, 7), true);
    if ($payload === false || strlen($payload) < 29) throw new RuntimeException('A stored payout reference could not be decrypted. Check APP_KEY configuration.');
    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipher = substr($payload, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA, $nonce, $tag, 'TaskHaat withdrawal v1');
    if ($plain === false) throw new RuntimeException('A stored payout reference could not be decrypted. Confirm that the original APP_KEY is configured.');
    return $plain;
}

function payout_reference_last4(string $reference): string {
    $clean = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($reference)) ?? '';
    return substr($clean, -4);
}

function mask_account_reference(string $reference): string {
    $clean = preg_replace('/\s+/', '', $reference) ?? '';
    if ($clean === '') return '••••';
    $tail = substr($clean, -4);
    return str_repeat('•', max(4, min(10, strlen($clean) - strlen($tail)))) . $tail;
}
function upload_submission_file(array $file): ?string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Evidence files exceed the server upload limit. Configure upload_max_filesize and post_max_size, then try again.');
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('The evidence file could not be uploaded.');
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Evidence files must be between 1 byte and 5 MB.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf','text/plain'=>'txt'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Allowed evidence formats: JPG, PNG, WEBP, PDF and TXT.');
    if (str_starts_with((string)$mime, 'image/')) {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || ($image['mime'] ?? '') !== $mime) throw new RuntimeException('The uploaded image is invalid or does not match its file type.');
        $pixels = (int)$image[0] * (int)$image[1];
        if ((int)$image[0] > 8000 || (int)$image[1] > 8000 || $pixels > 40000000) throw new RuntimeException('Evidence images must be no larger than 8,000 × 8,000 pixels.');
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0700, true) && !is_dir(UPLOAD_DIR)) throw new RuntimeException('Could not prepare private evidence storage.');
    $name = bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Could not store the uploaded evidence file.');
    return $name;
}

function upload_campaign_image(array $file): ?string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Campaign image exceeds the server upload limit. Configure upload_max_filesize and post_max_size, then try again.');
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('Campaign image upload failed.');
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Campaign images must be between 1 byte and 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Campaign images must be JPG, PNG or WEBP.');
    $image = @getimagesize($file['tmp_name']);
    if (!$image || ($image['mime'] ?? '') !== $mime) throw new RuntimeException('The uploaded campaign image is invalid or does not match its file type.');
    $pixels = (int)$image[0] * (int)$image[1];
    if ((int)$image[0] > 8000 || (int)$image[1] > 8000 || $pixels > 40000000) throw new RuntimeException('Campaign images must be no larger than 8,000 × 8,000 pixels.');
    if (!is_dir(CAMPAIGN_UPLOAD_DIR) && !mkdir(CAMPAIGN_UPLOAD_DIR, 0755, true) && !is_dir(CAMPAIGN_UPLOAD_DIR)) throw new RuntimeException('Could not prepare campaign image storage.');
    $name = bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], CAMPAIGN_UPLOAD_DIR . '/' . $name)) throw new RuntimeException('Could not store campaign image.');
    return $name;
}


/** Stable normalized fingerprint for a manual payment reference; references are not authentication secrets. */
function manual_deposit_reference_fingerprint(string $method, string $reference): string {
    $normalizedMethod = lower_text(trim($method));
    $normalizedReference = strtoupper((string)(preg_replace('/\s+/u', '', trim($reference)) ?? trim($reference)));
    return hash('sha256', $normalizedMethod . '|' . $normalizedReference);
}

/** Configured offline deposit destinations. Blank methods are hidden from publishers. */
function manual_deposit_methods(PDO $pdo): array {
    $methods = [
        'bKash' => [
            'account_name' => get_setting($pdo, 'manual_bkash_account_name'),
            'account_number' => get_setting($pdo, 'manual_bkash_number'),
            'label' => 'bKash',
        ],
        'Nagad' => [
            'account_name' => get_setting($pdo, 'manual_nagad_account_name'),
            'account_number' => get_setting($pdo, 'manual_nagad_number'),
            'label' => 'Nagad',
        ],
        'Rocket' => [
            'account_name' => get_setting($pdo, 'manual_rocket_account_name'),
            'account_number' => get_setting($pdo, 'manual_rocket_number'),
            'label' => 'Rocket',
        ],
        'Bank transfer' => [
            'bank_name' => get_setting($pdo, 'manual_bank_name'),
            'account_name' => get_setting($pdo, 'manual_bank_account_name'),
            'account_number' => get_setting($pdo, 'manual_bank_account_number'),
            'branch' => get_setting($pdo, 'manual_bank_branch'),
            'label' => 'Bank transfer',
        ],
    ];
    foreach ($methods as $key => $details) {
        $configured = $key === 'Bank transfer'
            ? ($details['bank_name'] !== '' && $details['account_name'] !== '' && $details['account_number'] !== '')
            : ($details['account_name'] !== '' && $details['account_number'] !== '');
        if (!$configured) unset($methods[$key]);
    }
    return $methods;
}

function upload_deposit_proof(array $file): ?array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Upload a payment screenshot or bank deposit slip as proof.');
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Deposit proof exceeds the server upload limit. Configure upload_max_filesize and post_max_size, then try again.');
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new RuntimeException('The deposit proof could not be uploaded.');
    if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('Deposit proof must be between 1 byte and 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Deposit proof must be JPG, PNG, WEBP or PDF.');
    if (str_starts_with((string)$mime, 'image/')) {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || ($image['mime'] ?? '') !== $mime) throw new RuntimeException('The deposit proof image is invalid or its type does not match.');
        $pixels = (int)$image[0] * (int)$image[1];
        if ((int)$image[0] > 8000 || (int)$image[1] > 8000 || $pixels > 40000000) throw new RuntimeException('Deposit proof images must be no larger than 8,000 × 8,000 pixels.');
    }
    if (!is_dir(DEPOSIT_UPLOAD_DIR) && !mkdir(DEPOSIT_UPLOAD_DIR, 0700, true) && !is_dir(DEPOSIT_UPLOAD_DIR)) throw new RuntimeException('Could not prepare private deposit proof storage.');
    $storageName = bin2hex(random_bytes(18)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], DEPOSIT_UPLOAD_DIR . '/' . $storageName)) throw new RuntimeException('Could not store the deposit proof.');
    return [
        'storage_name' => $storageName,
        'original_name' => basename((string)($file['name'] ?? 'deposit-proof')),
        'mime_type' => $mime,
        'file_size' => (int)$file['size'],
        'path' => DEPOSIT_UPLOAD_DIR . '/' . $storageName,
    ];
}

function site_content_value(PDO $pdo, string $key, string $default=''): string {
    static $content = null;
    if ($content === null) {
        $content = [];
        try {
            $rows = $pdo->query('SELECT content_key,content_value FROM site_content WHERE is_public=1')->fetchAll();
            foreach ($rows as $row) $content[(string)$row['content_key']] = (string)$row['content_value'];
        } catch (Throwable) {
            $content = [];
        }
    }
    return array_key_exists($key, $content) ? $content[$key] : $default;
}
function site_faq_rows(PDO $pdo): array {
    try { return $pdo->query('SELECT question,answer FROM site_faqs WHERE is_active=1 ORDER BY sort_order,id')->fetchAll(); }
    catch (Throwable) { return []; }
}
function active_site_announcement(PDO $pdo): ?array {
    try {
        $st = $pdo->query("SELECT title,message,cta_label,cta_url,style FROM site_announcements WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY sort_order,id DESC LIMIT 1");
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) { return null; }
}
function display_datetime(?string $value): string { if(!$value)return '—'; try{return (new DateTime($value))->format('d M Y, h:i A');}catch(Throwable){return e($value);} }
function status_pill(string $status): string {
    $map=['approved'=>'green','active'=>'green','completed'=>'green','paid'=>'green','pending'=>'amber','submitted'=>'amber','pending_review'=>'amber','rejected'=>'rose','cancelled'=>'gray','paused'=>'gray','claimed'=>'blue','processing'=>'blue'];
    return '<span class="pill '.($map[$status]??'gray').'">'.e(ucwords(str_replace('_',' ',$status))).'</span>';
}
function task_card(array $task): string {
    $left=max(0,(int)$task['worker_slots']-(int)$task['claimed_slots']);
    $pct=(int)$task['worker_slots']>0?min(100,(int)round(((int)$task['claimed_slots']/(int)$task['worker_slots'])*100)):0;
    $image=!empty($task['cover_image'])?'uploads/campaigns/'.rawurlencode($task['cover_image']):'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=640&q=75';
    $deadline=$task['deadline']?display_datetime($task['deadline']):'Flexible';
    ob_start(); ?>
    <article class="task-card task-row">
      <a class="task-art" href="<?=e(url('task',['id'=>$task['id']]))?>"><img src="<?=e($image)?>" alt="Task campaign" loading="lazy"><span class="task-kind"><?=e($task['category_name']??'Microtask')?></span></a>
      <div class="task-body"><div class="task-meta"><span class="publisher-name"><span class="avatar"><?=e(strtoupper(substr((string)($task['publisher_name']??'T'),0,1)))?></span><?=e($task['publisher_name']??'TaskHaat Publisher')?></span><?=status_pill($task['status'])?></div>
      <h3><a class="task-title-link" href="<?=e(url('task',['id'=>$task['id']]))?>"><?=e($task['title'])?></a></h3><p><?=e(short_text((string)$task['description'],160,'…'))?></p>
      <div class="task-info"><span><i data-lucide="clock-3"></i><?=e($task['estimated_time']?:'Flexible')?></span><span><i data-lucide="calendar"></i><?=e($deadline)?></span><span><i data-lucide="users"></i><?=number_format($left)?> slots left</span></div>
      <div class="slots-row"><span>Campaign progress</span><span><?=e($task['claimed_slots'])?> / <?=e($task['worker_slots'])?> claimed</span></div><div class="progress"><span style="width:<?=$pct?>%"></span></div>
      <div class="task-card-foot"><div class="reward"><small>Reward per approved worker</small><strong><?=money($task['reward_per_worker'])?></strong></div><a class="btn red sm" href="<?=e(url('task',['id'=>$task['id']]))?>">View task <i data-lucide="arrow-right"></i></a></div></div>
    </article><?php return (string)ob_get_clean();
}
function dashboard_link(string $tab, string $label, string $icon, string $active): string { return '<a class="side-btn '.($active===$tab?'active':'').'" href="'.e(url('dashboard',['tab'=>$tab])).'"><i data-lucide="'.e($icon).'"></i>'.e($label).'</a>'; }
