<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/helpers.php';

// Runtime and secret checks happen before the application starts handling requests.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('TaskHaat requires PHP 8.1 or newer.');
}

$requiredExtensions = ['pdo_mysql', 'openssl', 'fileinfo', 'session'];
$missingExtensions = array_values(array_filter($requiredExtensions, static fn(string $extension): bool => !extension_loaded($extension)));
if ($missingExtensions) {
    http_response_code(500);
    exit('TaskHaat is missing required PHP extensions: ' . e(implode(', ', $missingExtensions)) . '. Enable them in php.ini and restart PHP.');
}

if (!is_dir(LOG_DIR) && !@mkdir(LOG_DIR, 0700, true) && !is_dir(LOG_DIR)) {
    http_response_code(500);
    exit('TaskHaat cannot create its private log directory. Check storage folder permissions.');
}

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . '/php-error.log');
error_reporting(E_ALL);

if (APP_ENV !== 'local') {
    $placeholderKeys = [
        'taskhaat-local-development-key-change-before-deployment',
        'change-this-to-a-random-64-character-key-before-production',
        'PASTE_YOUR_64_CHARACTER_RANDOM_KEY_HERE',
        'replace-with-a-unique-random-64-character-hex-key',
    ];
    if (in_array(APP_KEY, $placeholderKeys, true) || strlen(APP_KEY) < 48) {
        http_response_code(500);
        exit('TaskHaat configuration error: set a unique APP_KEY of at least 48 characters before production use.');
    }
    if (!str_starts_with(APP_URL, 'https://')) {
        http_response_code(500);
        exit('TaskHaat configuration error: APP_URL must use https:// in production.');
    }
    if (DB_PASS === '') {
        http_response_code(500);
        exit('TaskHaat configuration error: configure a non-empty database password in production.');
    }
    if (strtolower(DB_USER) === 'root') {
        http_response_code(500);
        exit('TaskHaat configuration error: use a dedicated least-privilege MySQL account instead of root in production.');
    }
}

// Harden PHP sessions before session_start().
$secureRequest = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', '7200');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https://images.unsplash.com; script-src 'self' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; connect-src 'self'");
    if ($secureRequest && APP_ENV !== 'local') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $rememberLogin = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
        && (($_POST['action'] ?? '') === 'login') && isset($_POST['remember']);
    session_set_cookie_params([
        'lifetime' => $rememberLogin ? 60 * 60 * 24 * 30 : 0,
        'path' => '/',
        'secure' => $secureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (PDOException $e) {
    error_log('TaskHaat database connection failed: ' . $e->getMessage());
    http_response_code(500);
    $message = APP_DEBUG ? e($e->getMessage()) : 'Database connection failed.';
    exit('<h1>TaskHaat database connection failed</h1><p>' . $message . '</p><p>Check the .env database settings. DB_PORT is the MySQL port (usually 3306), not the phpMyAdmin web port.</p>');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/actions.php';
