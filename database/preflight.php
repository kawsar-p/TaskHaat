<?php
declare(strict_types=1);

/** Production/local deployment preflight. CLI only; no database writes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/config/config.php';

$checks = [];
$add = static function (string $status, string $label, string $detail = '') use (&$checks): void {
    $checks[] = [$status, $label, $detail];
};

$add(PHP_VERSION_ID >= 80100 ? 'PASS' : 'FAIL', 'PHP version', PHP_VERSION . ' (PHP 8.1+ required)');
foreach (['pdo_mysql', 'openssl', 'fileinfo', 'session'] as $extension) {
    $add(extension_loaded($extension) ? 'PASS' : 'FAIL', 'PHP extension: ' . $extension, extension_loaded($extension) ? 'loaded' : 'not loaded');
}

$envFile = dirname(__DIR__) . '/.env';
$add(is_file($envFile) ? 'PASS' : 'WARN', '.env configuration file', is_file($envFile) ? 'found' : 'not found; local defaults/process environment are active');
$add(APP_ENV !== 'local' ? (!APP_DEBUG ? 'PASS' : 'FAIL') : 'INFO', 'Debug display setting', 'APP_ENV=' . APP_ENV . '; APP_DEBUG=' . (APP_DEBUG ? 'true' : 'false'));
$placeholderKeys = ['taskhaat-local-development-key-change-before-deployment', 'change-this-to-a-random-64-character-key-before-production', 'PASTE_YOUR_64_CHARACTER_RANDOM_KEY_HERE', 'replace-with-a-unique-random-64-character-hex-key'];
$productionKeyValid = strlen(APP_KEY) >= 48 && !in_array(APP_KEY, $placeholderKeys, true);
$productionConfigValid = str_starts_with(APP_URL, 'https://') && $productionKeyValid && DB_PASS !== '' && strtolower(DB_USER) !== 'root' && !APP_DEBUG;
$add(APP_ENV === 'local' || $productionConfigValid ? 'PASS' : 'FAIL', 'Production secrets and URL', 'Staging/production require HTTPS APP_URL, a unique retained APP_KEY, APP_DEBUG=false, a non-root DB user and a non-empty DB password.');

$directories = [
    'logs' => LOG_DIR,
    'submissions' => UPLOAD_DIR,
    'deposits' => DEPOSIT_UPLOAD_DIR,
    'campaign images' => CAMPAIGN_UPLOAD_DIR,
    'backups' => BACKUP_DIR,
];
foreach ($directories as $label => $directory) {
    if (!is_dir($directory)) {
        $created = @mkdir($directory, 0700, true);
        if (!$created && !is_dir($directory)) {
            $add('FAIL', 'Storage directory: ' . $label, 'cannot create ' . $directory);
            continue;
        }
    }
    $writable = is_writable($directory);
    $add($writable ? 'PASS' : 'FAIL', 'Storage directory: ' . $label, $writable ? 'exists and writable' : 'not writable: ' . $directory);
}

$uploadMax = (string)ini_get('upload_max_filesize');
$postMax = (string)ini_get('post_max_size');
$iniBytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return (int)($number * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 });
};
$uploadLimitsOk = $iniBytes($uploadMax) > MAX_UPLOAD_BYTES && $iniBytes($postMax) > MAX_UPLOAD_BYTES;
$add($uploadLimitsOk ? 'PASS' : 'WARN', 'PHP upload limits', 'upload_max_filesize=' . $uploadMax . '; post_max_size=' . $postMax . '; TaskHaat application limit=' . number_format(MAX_UPLOAD_BYTES / 1048576, 1) . ' MB. Increase PHP limits if proof uploads fail.');

try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('pdo_mysql is not enabled.');
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $add('PASS', 'Database connection', DB_HOST . ':' . DB_PORT . '/' . DB_NAME . ' — ' . $version);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
    $add($count === 19 ? 'PASS' : 'WARN', 'TaskHaat table count', $count . ' tables found; Phase 10 schema expects 19.');

    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE user_type='admin' AND status='active'")->fetchColumn();
    $add($adminCount > 0 ? 'PASS' : 'FAIL', 'Active Admin account', $adminCount . ' active Admin account(s)');
} catch (Throwable $e) {
    $add('FAIL', 'Database connection/schema', APP_ENV === 'local' ? $e->getMessage() : 'Check database credentials and schema.');
}

$failed = 0;
$warnings = 0;
echo "TaskHaat deployment preflight\n==============================\n";
foreach ($checks as [$status, $label, $detail]) {
    echo '[' . $status . '] ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    if ($status === 'FAIL') $failed++;
    if ($status === 'WARN') $warnings++;
}
echo "\nResult: " . count($checks) . ' checks; ' . $failed . ' failed; ' . $warnings . " warning(s).\n";
exit($failed === 0 ? 0 : 1);
