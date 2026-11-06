<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/config/config.php';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+06:00'");
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: Could not connect to TaskHaat MySQL database. Check config/config.php and make sure XAMPP MySQL is running.\n");
    fwrite(STDERR, APP_ENV === 'local' ? $e->getMessage() . "\n" : "Database connection failed.\n");
    exit(2);
}

$checks = [];
$expectedTables = [
    'users','login_attempts','categories','wallets','campaigns','claims','submissions',
    'submission_files','wallet_transactions','platform_transactions','withdrawals',
    'notifications','settings','audit_logs','support_messages'
];
$st = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'");
$st->execute([DB_NAME]);
$actualTables = $st->fetchAll(PDO::FETCH_COLUMN);
$missingTables = array_values(array_diff($expectedTables, $actualTables));
$checks[] = ['15-table schema', count($missingTables) === 0 && count($actualTables) === 15, $missingTables ? 'Missing: ' . implode(', ', $missingTables) : count($actualTables) . ' tables found'];

$checks[] = ['Every account has a wallet', (int)$pdo->query('SELECT COUNT(*) FROM users u LEFT JOIN wallets w ON w.user_id=u.id WHERE w.id IS NULL')->fetchColumn() === 0, 'Missing wallet count must be 0'];

$ledgerMismatchSql = "SELECT COUNT(*) FROM wallets w
CROSS JOIN (SELECT 'available' AS bucket UNION ALL SELECT 'deposit' UNION ALL SELECT 'reserved' UNION ALL SELECT 'withdrawal_hold') b
LEFT JOIN (SELECT user_id,bucket,SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END) ledger_balance FROM wallet_transactions GROUP BY user_id,bucket) l
  ON l.user_id=w.user_id AND l.bucket=b.bucket
WHERE ABS(COALESCE(l.ledger_balance,0)-CASE
  WHEN b.bucket='available' THEN w.available_balance
  WHEN b.bucket='deposit' THEN w.deposit_balance
  WHEN b.bucket='reserved' THEN w.reserved_balance
  WHEN b.bucket='withdrawal_hold' THEN w.withdrawal_hold_balance END)>0.01";
$ledgerMismatches = (int)$pdo->query($ledgerMismatchSql)->fetchColumn();
$checks[] = ['Wallet balances match ledger', $ledgerMismatches === 0, $ledgerMismatches . ' mismatch(es)'];

$reserveMismatches = (int)$pdo->query("SELECT COUNT(*) FROM wallets w JOIN users u ON u.id=w.user_id
LEFT JOIN (SELECT publisher_id,SUM(budget_remaining) reserve_total FROM campaigns GROUP BY publisher_id) c ON c.publisher_id=u.id
WHERE u.user_type IN ('publisher','both') AND ABS(w.reserved_balance-COALESCE(c.reserve_total,0))>0.01")->fetchColumn();
$checks[] = ['Publisher reserve matches campaigns', $reserveMismatches === 0, $reserveMismatches . ' mismatch(es)'];

$claimMismatches = (int)$pdo->query("SELECT COUNT(*) FROM campaigns c
LEFT JOIN (SELECT campaign_id,COUNT(*) claim_count FROM claims GROUP BY campaign_id) cl ON cl.campaign_id=c.id
WHERE c.claimed_slots<>COALESCE(cl.claim_count,0)")->fetchColumn();
$checks[] = ['Campaign slot counts match claims', $claimMismatches === 0, $claimMismatches . ' mismatch(es)'];

$holdMismatches = (int)$pdo->query("SELECT COUNT(*) FROM wallets w
LEFT JOIN (SELECT user_id,SUM(amount) hold_total FROM withdrawals WHERE status IN ('pending','approved','processing') GROUP BY user_id) wd ON wd.user_id=w.user_id
WHERE ABS(w.withdrawal_hold_balance-COALESCE(wd.hold_total,0))>0.01")->fetchColumn();
$checks[] = ['Withdrawal holds match open requests', $holdMismatches === 0, $holdMismatches . ' mismatch(es)'];

$campaignMismatches = (int)$pdo->query('SELECT COUNT(*) FROM campaigns WHERE claimed_slots>worker_slots OR budget_remaining<0 OR budget_remaining>budget_total')->fetchColumn();
$checks[] = ['Campaign budgets and slots valid', $campaignMismatches === 0, $campaignMismatches . ' invalid campaign(s)'];

$budgetFormulaMismatches = (int)$pdo->query("SELECT COUNT(*) FROM campaigns c WHERE ABS(c.budget_total-ROUND((c.reward_per_worker+ROUND(c.reward_per_worker*c.publisher_fee_rate/100,2))*c.worker_slots,2))>0.01")->fetchColumn();
$checks[] = ['Campaign original budget matches saved fee terms', $budgetFormulaMismatches === 0, $budgetFormulaMismatches . ' mismatch(es)'];

$claimSubmissionMismatches = (int)$pdo->query("SELECT COUNT(*) FROM claims cl LEFT JOIN submissions s ON s.claim_id=cl.id WHERE (cl.status='claimed' AND s.id IS NOT NULL) OR (cl.status='submitted' AND (s.id IS NULL OR s.status<>'pending')) OR (cl.status='approved' AND (s.id IS NULL OR s.status<>'approved')) OR (cl.status='rejected' AND (s.id IS NULL OR s.status<>'rejected')) OR (cl.status='cancelled' AND s.id IS NOT NULL)")->fetchColumn();
$checks[] = ['Claim and submission statuses agree', $claimSubmissionMismatches === 0, $claimSubmissionMismatches . ' mismatch(es)'];

$settlementMismatches = (int)$pdo->query("SELECT COUNT(*) FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE s.status='approved' AND (ABS(s.gross_reward-c.reward_per_worker)>0.01 OR ABS(s.worker_fee-ROUND(s.gross_reward*c.worker_fee_rate/100,2))>0.01 OR ABS(s.net_reward-(s.gross_reward-s.worker_fee))>0.01)")->fetchColumn();
$checks[] = ['Approved reward snapshots match campaign fees', $settlementMismatches === 0, $settlementMismatches . ' mismatch(es)'];

$workerRewardMismatch = (float)$pdo->query("SELECT ABS(COALESCE((SELECT SUM(amount) FROM wallet_transactions WHERE transaction_type='worker_reward' AND bucket='available' AND direction='credit'),0)-COALESCE((SELECT SUM(net_reward) FROM submissions WHERE status='approved'),0))")->fetchColumn();
$checks[] = ['Worker reward ledger matches approved submissions', $workerRewardMismatch <= 0.01, money($workerRewardMismatch) . ' difference'];

$platformFeeMismatch = (float)$pdo->query("SELECT ABS(COALESCE((SELECT SUM(amount) FROM platform_transactions WHERE transaction_type='commission' AND reference_type='submission'),0)-COALESCE((SELECT SUM(ROUND(ROUND(s.gross_reward*c.publisher_fee_rate/100,2)+s.worker_fee,2)) FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE s.status='approved'),0))")->fetchColumn();
$checks[] = ['Platform commission ledger matches approved submissions', $platformFeeMismatch <= 0.01, money($platformFeeMismatch) . ' difference'];

$negativeWallets = (int)$pdo->query('SELECT COUNT(*) FROM wallets WHERE available_balance<0 OR deposit_balance<0 OR reserved_balance<0 OR withdrawal_hold_balance<0')->fetchColumn();
$checks[] = ['Wallet balances are never negative', $negativeWallets === 0, $negativeWallets . ' invalid wallet(s)'];

$failed = 0;
echo "TaskHaat database verification\n==============================\n";
foreach ($checks as [$label, $passed, $detail]) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . ' — ' . $detail . "\n";
    if (!$passed) $failed++;
}
$counts = $pdo->query("SELECT (SELECT COUNT(*) FROM users) users,(SELECT COUNT(*) FROM campaigns) campaigns,(SELECT COUNT(*) FROM claims) claims,(SELECT COUNT(*) FROM submissions) submissions,(SELECT COUNT(*) FROM notifications) notifications,(SELECT COUNT(*) FROM support_messages) support_tickets")->fetch();
echo "\nRecords: " . implode(' | ', array_map(static fn($k,$v) => $k . ': ' . $v, array_keys($counts), array_values($counts))) . "\n";
echo $failed === 0 ? "\nDatabase checks passed.\n" : "\nDatabase checks found {$failed} issue(s). Review the mismatches before using financial workflows.\n";
exit($failed === 0 ? 0 : 1);
