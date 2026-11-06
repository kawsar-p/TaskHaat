<?php
declare(strict_types=1);

/**
 * TaskHaat Phase 7 source-level QA.
 * Run with: php tests/qa_static_test.php
 * This test does not connect to or modify MySQL.
 */
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

$schema = $read('database/schema.sql');
$actionSource = $read('app/actions.php');
$router = $read('public/index.php');
$bootstrap = $read('app/bootstrap.php');
$helpers = $read('app/helpers.php');
$config = $read('config/config.php');

preg_match_all('/CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $schema, $tableMatches);
$tables = array_values(array_unique($tableMatches[1] ?? []));
$assert(count($tables) === 19, 'Consolidated schema defines exactly 19 tables including website CMS tables');
$assert(substr_count(strtoupper($schema), 'ENGINE=INNODB') === 19, 'All 19 tables use InnoDB transactions');
$assert(str_contains($schema, 'uq_campaign_worker') && str_contains($schema, 'uq_submission_claim'), 'Campaign claims and submissions have uniqueness constraints');
$assert(str_contains($schema, 'uq_wallet_idempotency'), 'Wallet ledger has an idempotency constraint');
$assert(str_contains($schema, 'uq_deposit_reference_fingerprint') && str_contains($schema, 'fk_deposit_wallet_transaction'), 'Manual deposit references are unique and approved credits link to the wallet ledger');

preg_match_all('/case\s+\'([^\']+)\'\s*:/', $actionSource, $handlerMatches);
$handlers = array_values(array_unique($handlerMatches[1] ?? []));
$formActions = [];
$formsWithoutCsrf = [];
// RecursiveIterator is used because glob's ** support is platform-dependent.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $source = (string)file_get_contents($file->getPathname());
    preg_match_all('/<form\b[\\s\\S]*?<\/form>/i', $source, $formMatches);
    foreach ($formMatches[0] ?? [] as $form) {
        if (!preg_match('/\bmethod\s*=\s*["\']post["\']/i', $form)) continue;
        if (!str_contains($form, 'csrf_field()') && !str_contains($form, 'csrf_token')) {
            $formsWithoutCsrf[] = $file->getPathname();
        }
        if (preg_match('/\bname\s*=\s*["\']action["\'][^>]*\bvalue\s*=\s*["\']([^"\']+)["\']/i', $form, $m)
            || preg_match('/\bvalue\s*=\s*["\']([^"\']+)["\'][^>]*\bname\s*=\s*["\']action["\']/i', $form, $m)) {
            $formActions[] = $m[1];
        }
    }
}
$unhandled = array_values(array_diff(array_unique($formActions), $handlers));
$assert(count($unhandled) === 0, 'Every rendered POST form action has a PHP handler' . ($unhandled ? ': ' . implode(', ', $unhandled) : ''));
$assert(count($formsWithoutCsrf) === 0, 'Every rendered POST form contains a CSRF token' . ($formsWithoutCsrf ? ': ' . implode(', ', array_unique($formsWithoutCsrf)) : ''));

$roleRequirements = [
    'create_campaign' => "require_role(\$pdo,'publisher')",
    'update_campaign' => "require_role(\$pdo,'publisher')",
    'claim_task' => "require_role(\$pdo,'worker')",
    'submit_proof' => "require_role(\$pdo,'worker')",
    'review_campaign' => "require_role(\$pdo,'admin')",
    'request_withdrawal' => "require_role(\$pdo,'worker')",
    'admin_withdrawal' => "require_role(\$pdo,'admin')",
    'admin_add_funds' => "require_role(\$pdo,'admin')",
    'submit_deposit_request' => "require_role(\$pdo,'publisher')",
    'cancel_deposit_request' => "require_role(\$pdo,'publisher')",
    'admin_deposit_request' => "require_role(\$pdo,'admin')",
    'admin_update_support_message' => "require_role(\$pdo,'admin')",
    'admin_toggle_user' => "require_role(\$pdo,'admin')",
];
$roleFailures = [];
foreach ($roleRequirements as $action => $required) {
    $start = strpos($actionSource, "case '$action':");
    if ($start === false) { $roleFailures[] = $action . ' handler missing'; continue; }
    $next = strpos($actionSource, "case '", $start + 7);
    $block = substr($actionSource, $start, $next === false ? null : $next - $start);
    if (!str_contains($block, $required)) $roleFailures[] = $action . ' missing role guard';
}
$assert(count($roleFailures) === 0, 'Critical POST actions enforce their required role' . ($roleFailures ? ': ' . implode('; ', $roleFailures) : ''));

$assert(str_contains($actionSource, 'Only the campaign publisher or an Admin can review this proof.'), 'Submission reviews enforce publisher ownership or Admin access');
$assert(str_contains($actionSource, 'lock_active_account'), 'Financial and task mutations lock and re-check active accounts');
$assert(str_contains($helpers, 'hash_equals'), 'CSRF validation uses constant-time token comparison');
$assert(str_contains($helpers, 'openssl_encrypt') && str_contains($helpers, 'aes-256-gcm'), 'Withdrawal account references are encrypted at rest');
$assert(str_contains($helpers, 'upload_deposit_proof') && str_contains($helpers, 'manual_deposit_methods') && str_contains($helpers, 'manual_deposit_reference_fingerprint'), 'Offline deposit methods and private proof uploads are available');
$assert(str_contains($actionSource, 'deposit.approved') && str_contains($actionSource, 'manual_deposit') && str_contains($actionSource, 'payment_reference'), 'Deposit approval and manual withdrawal payment references are recorded');
$assert(str_contains($actionSource, 'This request has no stored payment proof'), 'Admin cannot approve a deposit request without stored proof');
$assert(str_contains($router, "route==='deposit_proof'") && str_contains($router, 'DEPOSIT_UPLOAD_DIR') && str_contains($router, '[a-f0-9]{36}'), 'Deposit proof files use an authenticated private download route with safe storage names');
$assert(str_contains($bootstrap, 'session.use_strict_mode') && str_contains($bootstrap, 'session.cookie_httponly'), 'Session hardening is enabled');
$assert(str_contains($bootstrap, 'Content-Security-Policy') && str_contains($bootstrap, 'X-Content-Type-Options'), 'Security response headers are emitted by PHP');
$assert(str_contains($bootstrap, "APP_ENV !== 'local'") && str_contains($bootstrap, 'APP_KEY'), 'Production refuses the development APP_KEY');
$assert(str_contains($read('storage/submissions/.htaccess'), 'Require all denied'), 'Worker evidence storage is not publicly served');
$assert(str_contains($read('public/uploads/campaigns/.htaccess'), 'Options -Indexes'), 'Campaign image upload directory disables indexing');
$assert(str_contains($read('database/verify.php'), "PHP_SAPI !== 'cli'"), 'Database verifier is CLI-only');

$requiredViews = ['home.php','browse.php','task.php','fees.php','help.php','guide.php','safety.php','contact.php','legal.php','auth/login.php','auth/register.php','dashboard.php','dashboard/admin.php','dashboard/worker.php'];
$missingViews = array_values(array_filter($requiredViews, static fn($path) => !is_file($root . '/views/' . $path)));
$assert(count($missingViews) === 0, 'All public, auth and workspace views exist' . ($missingViews ? ': ' . implode(', ', $missingViews) : ''));

$runtimePhp = $actionSource . $helpers . $bootstrap . $router;
$paymentGatewayMarkers = ['sslcommerz', 'stripe.com', 'checkout.com', 'api.bkash.com', 'nagad.com.bd/api'];
$gatewayFound = [];
foreach ($paymentGatewayMarkers as $marker) if (stripos($runtimePhp, $marker) !== false) $gatewayFound[] = $marker;
$assert(count($gatewayFound) === 0, 'No online payment gateway integration is present' . ($gatewayFound ? ': ' . implode(', ', $gatewayFound) : ''));
$assert(!str_contains(strtolower($runtimePhp), 'smtp.gmail.com') && !str_contains($runtimePhp, 'PHPMailer\\PHPMailer'), 'SMTP/email delivery remains deferred');

$demoEmails = ['admin@taskhaat.local','publisher@taskhaat.local','publisher2@taskhaat.local','worker@taskhaat.local','worker2@taskhaat.local','worker3@taskhaat.local','worker4@taskhaat.local'];
$missingDemo = array_values(array_filter($demoEmails, static fn($email) => !str_contains($schema, $email)));
$assert(count($missingDemo) === 0, 'Schema includes the seven documented demo accounts' . ($missingDemo ? ': ' . implode(', ', $missingDemo) : ''));

$testCount = $checks;
echo PHP_EOL . "{$testCount} source-level QA checks; {$failed} failed." . PHP_EOL;
exit($failed === 0 ? 0 : 1);

$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_content') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_faqs') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_announcements'), 'Phase 10 CMS tables are present');

$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_content') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_faqs') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS site_announcements'), 'Website content, FAQ and announcement tables are defined');
$assert(str_contains($actionSource, "case 'site_content_save':") && str_contains($actionSource, "case 'site_faq_save':") && str_contains($actionSource, "case 'site_announcement_save':"), 'Admin website-management CRUD actions are present');
$assert(str_contains($router, "'website'") && str_contains($router, "'faqs'") && str_contains($router, "'announcements'"), 'Admin website-management tabs are authorized by the router');
