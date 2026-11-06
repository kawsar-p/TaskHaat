<?php
declare(strict_types=1);

function auth_login(PDO $pdo, string $email, string $password): bool {
    $st = $pdo->prepare('SELECT id,password_hash,status FROM users WHERE email=? LIMIT 1');
    $st->execute([lower_text(trim($email))]);
    $row = $st->fetch();

    // Verify a dummy bcrypt hash when the email is absent, reducing user-enumeration timing signals.
    $dummyHash = '$2y$12$0ZjK9SkiKss4K/7SXXI43.RWJkO7.u33tIe41o36SDBsn34u/34Rm';
    $hashToVerify = $row ? (string)$row['password_hash'] : $dummyHash;
    $passwordValid = password_verify($password, $hashToVerify);
    if (!$row || $row['status'] !== 'active' || !$passwordValid) return false;

    if (password_needs_rehash((string)$row['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int)$row['id']]);
    }

    session_regenerate_id(true);
    unset($_SESSION['_csrf']);
    $_SESSION['user_id'] = (int)$row['id'];
    $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
    return true;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}
