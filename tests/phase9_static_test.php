<?php
declare(strict_types=1);

/** Phase 9 source checks. This script does not connect to or modify MySQL. */
$root = dirname(__DIR__);
$checks = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$condition) $failed++;
};
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string)file_get_contents($path) : '';
};

$config = $read('config/config.php');
$bootstrap = $read('app/bootstrap.php');
$helpers = $read('app/helpers.php');
$layout = $read('views/layout.php');
$publicHtaccess = $read('public/.htaccess');
$rootHtaccess = $read('.htaccess');
$migration = $read('database/migrations/20261106_phase9_performance_indexes.sql');
$backup = $read('database/backup.php');
$preflight = $read('database/preflight.php');
$deployment = $read('DEPLOYMENT.md');

$assert(str_contains($config, "'/.env'") && str_contains($config, 'getenv($name) !== false') && str_contains($config, 'APP_ENV must be local, staging or production'), '.env configuration is loaded, process variables take precedence and environment typos fail closed');
$assert(is_file($root . '/.env.example') && str_contains($read('.gitignore'), '/.env'), '.env example exists and real secrets are git-ignored');
$assert(str_contains($bootstrap, "APP_URL must use https://") && str_contains($bootstrap, 'DB_PASS ===') && str_contains($bootstrap, "APP_ENV !== 'local'") && str_contains($bootstrap, "strtolower(DB_USER) === 'root'"), 'Production configuration requires HTTPS and a non-empty DB password');
$assert(str_contains($bootstrap, "ini_set('display_errors', APP_DEBUG ? '1' : '0')") && str_contains($bootstrap, "ini_set('error_log', LOG_DIR . '/php-error.log')"), 'Debug display is environment-controlled and errors are logged privately');
$assert(str_contains($bootstrap, 'DB_TIMEZONE_OFFSET') && str_contains($bootstrap, 'PDO::ATTR_TIMEOUT'), 'Database timezone and connection timeout are configurable');
$assert(str_contains($helpers, 'function asset_url(') && str_contains($layout, 'asset_url('), 'Static assets have file-version query strings for cache invalidation');
$assert(str_contains($publicHtaccess, 'mod_deflate') && str_contains($publicHtaccess, 'mod_expires') && str_contains($publicHtaccess, 'max-age=3600'), 'Apache compression and static asset caching are configured');
$assert(str_contains($rootHtaccess, 'storage') && str_contains($rootHtaccess, 'database') && str_contains($rootHtaccess, 'config'), 'Project-root Apache rules block private application paths');
$assert(str_contains($migration, 'information_schema.STATISTICS') && !preg_match('/\b(DROP\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $migration), 'Performance index migration is idempotent and non-destructive');
foreach (['idx_login_attempt_retention','idx_campaign_public_deadline_created','idx_campaign_category_public_deadline','idx_claim_worker_claimed_at','idx_audit_entity_created'] as $index) {
    $assert(str_contains($migration, $index), 'Performance migration includes ' . $index);
}
$assert(str_contains($backup, "PHP_SAPI !== 'cli'") && str_contains($backup, 'START TRANSACTION WITH CONSISTENT SNAPSHOT'), 'Database backup is CLI-only and reads a consistent InnoDB snapshot');
$assert(str_contains($backup, "storage/submissions") && str_contains($backup, "storage/deposits") && str_contains($backup, "uploads/campaigns"), 'Backup includes private evidence, deposit proofs and campaign images');
$assert(str_contains($preflight, 'information_schema.TABLES') && str_contains($preflight, 'Storage directory: '), 'Deployment preflight checks live database schema and storage permissions');
$assert(str_contains($preflight, '$productionKeyValid') && str_contains($preflight, '$uploadLimitsOk'), 'Deployment preflight validates production secrets and PHP upload limits');
$assert(str_contains($deployment, 'DocumentRoot') && str_contains($deployment, '/public'), 'Deployment guide requires public/ as the web document root');
$assert(str_contains($deployment, 'database/RESTORE.md') && is_file($root . '/database/RESTORE.md'), 'Backup restoration steps are documented');
$assert(str_contains($backup, 'BACKUP_DIR') && str_contains($read('storage/backups/.htaccess'), 'Require all denied'), 'Backup destination is outside public and Apache-protected');
$assert(str_contains($deployment, 'SMTP') && str_contains($deployment, 'payment gateway'), 'Deployment documentation preserves deferred SMTP and gateway scope');

$schema = $read('database/schema.sql');
$assert(substr_count($schema, 'idx_campaign_public_deadline_created') === 1
    && substr_count($schema, 'idx_campaign_category_public_deadline') === 1
    && substr_count($schema, 'idx_claim_worker_claimed_at') === 1
    && substr_count($schema, 'idx_audit_entity_created') === 1
    && substr_count($schema, 'idx_login_attempt_retention') === 1, 'Fresh-install schema includes each Phase 9 performance index exactly once');

$gatewayMarkers = ['sslcommerz', 'stripe.com', 'api.bkash.com', 'nagad.com.bd/api'];
$runtime = $read('app/actions.php') . $bootstrap . $helpers;
$gatewayFound = array_values(array_filter($gatewayMarkers, static fn(string $marker): bool => stripos($runtime, $marker) !== false));
$assert(count($gatewayFound) === 0, 'No payment gateway code has been introduced');
$assert(!str_contains($runtime, 'smtp.gmail.com') && !str_contains($runtime, 'PHPMailer\\PHPMailer'), 'SMTP remains deferred');

$checksTotal = $checks;
echo PHP_EOL . "{$checksTotal} Phase 9 static checks; {$failed} failed." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
