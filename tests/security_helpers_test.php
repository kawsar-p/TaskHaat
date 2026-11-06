<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/helpers.php';

$tests = 0;
$failed = 0;
$assert = static function (bool $ok, string $name) use (&$tests, &$failed): void {
    $tests++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
    if (!$ok) $failed++;
};

$plain = '01712345678';
$encrypted = encrypt_account_reference($plain);
$assert(str_starts_with($encrypted, 'enc:v1:'), 'Payout account is stored with an encryption prefix');
$assert($encrypted !== $plain && !str_contains($encrypted, $plain), 'Encrypted payout value does not reveal the original account');
$assert(decrypt_account_reference($encrypted) === $plain, 'AES-GCM payout value decrypts to the original account');
$assert(decrypt_account_reference('01700000004') === '01700000004', 'Legacy demo payout references remain readable');
$assert(mask_account_reference($plain) === '•••••••5678', 'Worker payout view masks all but the last four characters');
$assert(payout_reference_last4('01712-345678') === '5678', 'Last-four lookup value removes punctuation');

$fingerprintOne = manual_deposit_reference_fingerprint('bKash', 'DEMO-Ref 001');
$assert($fingerprintOne === manual_deposit_reference_fingerprint('BKASH', 'demo-ref001'), 'Manual deposit fingerprint normalizes method case and reference whitespace/case');
$assert($fingerprintOne !== manual_deposit_reference_fingerprint('Nagad', 'demo-ref001'), 'Manual deposit fingerprint is scoped to the payment method');
$assert($fingerprintOne === hash('sha256', 'bkash|DEMO-REF001'), 'Manual deposit fingerprint matches normalized SQL seed convention');

$testsLine = "\n{$tests} helper checks; {$failed} failed.\n";
echo $testsLine;
exit($failed === 0 ? 0 : 1);
