<?php
declare(strict_types=1);

/**
 * Creates a consistent SQL dump and copies private/public uploads to storage/backups.
 * CLI only: php database/backup.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/config/config.php';

function backupIdentifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function backupWrite($handle, string $content): void
{
    if (fwrite($handle, $content) === false) {
        throw new RuntimeException('Failed writing the SQL backup file. Check disk space and folder permissions.');
    }
}

function backupCopyDirectory(string $source, string $destination): int
{
    if (!is_dir($source)) {
        return 0;
    }
    if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
        throw new RuntimeException('Could not create backup media directory: ' . $destination);
    }

    $copied = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) {
                throw new RuntimeException('Could not create backup directory: ' . $target);
            }
            continue;
        }
        if ($item->isFile()) {
            $targetDirectory = dirname($target);
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('Could not create backup directory: ' . $targetDirectory);
            }
            if (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Could not copy upload file into backup: ' . $item->getFilename());
            }
            @chmod($target, 0600);
            $copied++;
        }
    }
    return $copied;
}

$pdo = null;
$backupDirectory = '';
$handle = null;
try {
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('The pdo_mysql PHP extension is required. Enable it in php.ini.');
    }
    if (!is_dir(BACKUP_DIR) && !mkdir(BACKUP_DIR, 0700, true) && !is_dir(BACKUP_DIR)) {
        throw new RuntimeException('Could not create storage/backups. Check folder permissions.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

    $stamp = date('Ymd_His');
    $backupDirectory = BACKUP_DIR . '/taskhaat_' . $stamp;
    if (!mkdir($backupDirectory, 0700, true)) {
        throw new RuntimeException('Could not create timestamped backup directory.');
    }
    $sqlPath = $backupDirectory . '/database.sql';
    $handle = fopen($sqlPath, 'xb');
    if (!$handle) {
        throw new RuntimeException('Could not create the SQL backup file.');
    }
    @chmod($sqlPath, 0600);

    backupWrite($handle, "-- TaskHaat database backup\n-- Created: " . date(DATE_ATOM) . "\n-- Database: " . DB_NAME . "\n-- Restore only into a database you intend to replace.\n\n");
    backupWrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';\nUSE " . backupIdentifier(DB_NAME) . ";\n\n");

    $tableQuery = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
    $tableQuery->execute([DB_NAME]);
    $tables = $tableQuery->fetchAll(PDO::FETCH_COLUMN);
    if (!$tables) {
        throw new RuntimeException('No application tables were found in the configured database.');
    }

    // Read all DDL first, then drop/create, then stream data in bounded batches.
    $definitions = [];
    foreach ($tables as $table) {
        $show = $pdo->query('SHOW CREATE TABLE ' . backupIdentifier((string)$table))->fetch(PDO::FETCH_ASSOC);
        $createSql = $show['Create Table'] ?? array_values($show ?: [])[1] ?? null;
        if (!is_string($createSql) || $createSql === '') {
            throw new RuntimeException('Could not read the table definition for ' . $table . '.');
        }
        $definitions[(string)$table] = $createSql;
    }

    foreach (array_keys($definitions) as $table) {
        backupWrite($handle, 'DROP TABLE IF EXISTS ' . backupIdentifier($table) . ";\n");
    }
    backupWrite($handle, "\n");
    foreach ($definitions as $createSql) {
        backupWrite($handle, $createSql . ";\n\n");
    }

    $rowCount = 0;
    foreach (array_keys($definitions) as $table) {
        $statement = $pdo->query('SELECT * FROM ' . backupIdentifier($table));
        $columnRows = $pdo->query('SHOW COLUMNS FROM ' . backupIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_map(static fn(array $column): string => (string)$column['Field'], $columnRows);
        if (!$columns) {
            continue;
        }
        $columnSql = implode(',', array_map('backupIdentifier', $columns));
        $batch = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $quoted = $pdo->quote((string)$value);
                    if ($quoted === false) {
                        throw new RuntimeException('Could not safely serialize a value from table ' . $table . '.');
                    }
                    $values[] = $quoted;
                }
            }
            $batch[] = '(' . implode(',', $values) . ')';
            $rowCount++;
            if (count($batch) >= 100) {
                backupWrite($handle, 'INSERT INTO ' . backupIdentifier($table) . ' (' . $columnSql . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            backupWrite($handle, 'INSERT INTO ' . backupIdentifier($table) . ' (' . $columnSql . ") VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        backupWrite($handle, "\n");
    }

    $pdo->commit();
    backupWrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    $handle = null;

    $mediaCopied = 0;
    $mediaCopied += backupCopyDirectory(dirname(__DIR__) . '/storage/submissions', $backupDirectory . '/media/storage/submissions');
    $mediaCopied += backupCopyDirectory(dirname(__DIR__) . '/storage/deposits', $backupDirectory . '/media/storage/deposits');
    $mediaCopied += backupCopyDirectory(dirname(__DIR__) . '/public/uploads/campaigns', $backupDirectory . '/media/public/uploads/campaigns');

    echo "TaskHaat backup completed.\n";
    echo "Database tables: " . count($tables) . "\n";
    echo "Database rows: {$rowCount}\n";
    echo "Media files copied: {$mediaCopied}\n";
    echo "Backup folder: {$backupDirectory}\n";
    echo "SQL file: {$sqlPath}\n";
    echo "Keep this backup outside the public web directory and copy it to a separate safe location.\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_resource($handle)) {
        fclose($handle);
    }
    if ($backupDirectory !== '' && is_dir($backupDirectory)) {
        // Do not leave a partial SQL file that could be mistaken for a valid backup.
        $partialSql = $backupDirectory . '/database.sql';
        if (is_file($partialSql)) {
            @unlink($partialSql);
        }
    }
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(1);
}
