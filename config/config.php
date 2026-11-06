<?php
declare(strict_types=1);

/*
 * TaskHaat environment configuration.
 * Process environment variables take precedence over values in the project .env file.
 * Keep .env outside the public web root and never commit it.
 */
$taskhaatRoot = dirname(__DIR__);
$taskhaatEnvFile = $taskhaatRoot . '/.env';

if (is_file($taskhaatEnvFile) && is_readable($taskhaatEnvFile)) {
    $lines = file($taskhaatEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            continue;
        }

        $name = $matches[1];
        if (getenv($name) !== false) {
            continue;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    $value = stripcslashes($value);
                }
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
                $value = trim($value);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === false ? $default : (string)$value;
};

$environment = strtolower($env('APP_ENV', 'local'));
if (!in_array($environment, ['local', 'staging', 'production'], true)) {
    throw new RuntimeException('APP_ENV must be local, staging or production.');
}

$debugDefault = $environment === 'local' ? 'true' : 'false';
$debugValue = strtolower($env('APP_DEBUG', $debugDefault));
$debug = in_array($debugValue, ['1', 'true', 'yes', 'on'], true);

$timezone = $env('APP_TIMEZONE', 'Asia/Dhaka');
try {
    new DateTimeZone($timezone);
} catch (Throwable) {
    $timezone = 'Asia/Dhaka';
}
date_default_timezone_set($timezone);

$timezoneOffset = $env('DB_TIMEZONE_OFFSET', '+06:00');
if (!preg_match('/^[+-](?:0[0-9]|1[0-3]):[0-5][0-9]$|^[+-]14:00$/', $timezoneOffset)) {
    $timezoneOffset = '+06:00';
}

$maxUploadBytes = filter_var($env('MAX_UPLOAD_BYTES', (string)(5 * 1024 * 1024)), FILTER_VALIDATE_INT);
if ($maxUploadBytes === false || $maxUploadBytes < 1024 * 1024 || $maxUploadBytes > 20 * 1024 * 1024) {
    $maxUploadBytes = 5 * 1024 * 1024;
}

if (!defined('APP_NAME')) define('APP_NAME', $env('APP_NAME', 'TaskHaat'));
if (!defined('APP_ENV')) define('APP_ENV', $environment);
if (!defined('APP_DEBUG')) define('APP_DEBUG', $debug);
if (!defined('APP_KEY')) define('APP_KEY', $env('APP_KEY', 'taskhaat-local-development-key-change-before-deployment'));
if (!defined('APP_URL')) define('APP_URL', rtrim($env('APP_URL', 'http://127.0.0.1:8000'), '/'));
if (!defined('DB_HOST')) define('DB_HOST', $env('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', $env('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', $env('DB_NAME', 'taskhaat'));
if (!defined('DB_USER')) define('DB_USER', $env('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', $env('DB_PASS', ''));
if (!defined('DB_TIMEZONE_OFFSET')) define('DB_TIMEZONE_OFFSET', $timezoneOffset);
if (!defined('MAX_UPLOAD_BYTES')) define('MAX_UPLOAD_BYTES', $maxUploadBytes);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', $taskhaatRoot . '/storage/submissions');
if (!defined('DEPOSIT_UPLOAD_DIR')) define('DEPOSIT_UPLOAD_DIR', $taskhaatRoot . '/storage/deposits');
if (!defined('CAMPAIGN_UPLOAD_DIR')) define('CAMPAIGN_UPLOAD_DIR', $taskhaatRoot . '/public/uploads/campaigns');
if (!defined('BACKUP_DIR')) define('BACKUP_DIR', $taskhaatRoot . '/storage/backups');
if (!defined('LOG_DIR')) define('LOG_DIR', $taskhaatRoot . '/storage/logs');
