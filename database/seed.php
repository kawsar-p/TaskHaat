<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',DB_HOST,DB_PORT,DB_NAME);
$pdo=new PDO($dsn,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$email='admin@taskhaat.local';
$st=$pdo->prepare('SELECT id FROM users WHERE email=?');$st->execute([$email]);
if($st->fetch()) { echo "Admin already exists: {$email}\n"; exit; }
$hash=password_hash('Admin@12345',PASSWORD_DEFAULT);
$pdo->beginTransaction();
$st=$pdo->prepare("INSERT INTO users(first_name,last_name,email,password_hash,user_type,status,email_verified_at) VALUES('TaskHaat','Admin',?,?,'admin','active',NOW())");$st->execute([$email,$hash]);$id=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO wallets(user_id) VALUES(?)')->execute([$id]);
$pdo->commit();
echo "Admin created. Email: {$email}\nPassword: Admin@12345\nChange this password immediately after first login.\n";
