<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TaskHaat Database Verification
|--------------------------------------------------------------------------
| CLI-only database integrity checker.
| Run: php database/verify.php
|--------------------------------------------------------------------------
*/

if (!function_exists('money')) {
    function money($amount): string
    {
        return '৳' . number_format((float) $amount, 2);
    }
}

// CLI access only
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once dirname(__DIR__) . '/config/config.php';

// Database connection
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET time_zone = '" . DB_TIMEZONE_OFFSET . "'");

} catch (Throwable $e) {
    fwrite(
        STDERR,
        "FAIL: Could not connect to TaskHaat MySQL database. Check config/config.php and make sure XAMPP MySQL is running.\n"
    );

    fwrite(
        STDERR,
        APP_ENV === 'local'
        ? $e->getMessage() . "\n"
        : "Database connection failed.\n"
    );

    exit(2);
}

$checks = [];

/*
|--------------------------------------------------------------------------
| 1. Expected Database Tables
|--------------------------------------------------------------------------
*/

$expectedTables = [
    'users',
    'login_attempts',
    'categories',
    'wallets',
    'campaigns',
    'claims',
    'submissions',
    'submission_files',
    'wallet_transactions',
    'platform_transactions',
    'withdrawals',
    'notifications',
    'settings',
    'audit_logs',
    'support_messages',
    'manual_deposit_requests'
];

$st = $pdo->prepare("
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = ?
    AND TABLE_TYPE = 'BASE TABLE'
");

$st->execute([DB_NAME]);

$actualTables = $st->fetchAll(PDO::FETCH_COLUMN);

$missingTables = array_values(
    array_diff($expectedTables, $actualTables)
);

$checks[] = [
    '16-table schema',
    count($missingTables) === 0 && count($actualTables) === 16,
    $missingTables
    ? 'Missing: ' . implode(', ', $missingTables)
    : count($actualTables) . ' tables found'
];

/*
|--------------------------------------------------------------------------
| 2. Every Account Must Have a Wallet
|--------------------------------------------------------------------------
*/

$missingWallets = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users u
    LEFT JOIN wallets w ON w.user_id = u.id
    WHERE w.id IS NULL
")->fetchColumn();

$checks[] = [
    'Every account has a wallet',
    $missingWallets === 0,
    'Missing wallet count: ' . $missingWallets
];

/*
|--------------------------------------------------------------------------
| 3. Wallet Balance vs Transaction Ledger
|--------------------------------------------------------------------------
*/

$ledgerMismatchSql = "
SELECT COUNT(*)
FROM wallets w

CROSS JOIN (
    SELECT 'available' AS bucket
    UNION ALL SELECT 'deposit'
    UNION ALL SELECT 'reserved'
    UNION ALL SELECT 'withdrawal_hold'
) b

LEFT JOIN (
    SELECT
        user_id,
        bucket,
        SUM(
            CASE
                WHEN direction = 'credit' THEN amount
                ELSE -amount
            END
        ) AS ledger_balance

    FROM wallet_transactions
    GROUP BY user_id, bucket

) l ON l.user_id = w.user_id
    AND l.bucket = b.bucket

WHERE ABS(
    COALESCE(l.ledger_balance, 0) -
    CASE
        WHEN b.bucket = 'available'
            THEN w.available_balance

        WHEN b.bucket = 'deposit'
            THEN w.deposit_balance

        WHEN b.bucket = 'reserved'
            THEN w.reserved_balance

        WHEN b.bucket = 'withdrawal_hold'
            THEN w.withdrawal_hold_balance
    END
) > 0.01
";

$ledgerMismatches = (int) $pdo
    ->query($ledgerMismatchSql)
    ->fetchColumn();

$checks[] = [
    'Wallet balances match ledger',
    $ledgerMismatches === 0,
    $ledgerMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 4. Publisher Reserve vs Campaign Budget
|--------------------------------------------------------------------------
*/

$reserveMismatches = (int) $pdo->query("
    SELECT COUNT(*)
    FROM wallets w

    JOIN users u ON u.id = w.user_id

    LEFT JOIN (
        SELECT
            publisher_id,
            SUM(budget_remaining) AS reserve_total
        FROM campaigns
        GROUP BY publisher_id
    ) c ON c.publisher_id = u.id

    WHERE u.user_type IN ('publisher', 'both')

    AND ABS(
        w.reserved_balance -
        COALESCE(c.reserve_total, 0)
    ) > 0.01
")->fetchColumn();

$checks[] = [
    'Publisher reserve matches campaigns',
    $reserveMismatches === 0,
    $reserveMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 5. Campaign Claimed Slots
|--------------------------------------------------------------------------
*/

$claimMismatches = (int) $pdo->query("
    SELECT COUNT(*)
    FROM campaigns c

    LEFT JOIN (
        SELECT
            campaign_id,
            COUNT(*) AS claim_count
        FROM claims
        GROUP BY campaign_id
    ) cl ON cl.campaign_id = c.id

    WHERE c.claimed_slots <> COALESCE(cl.claim_count, 0)
")->fetchColumn();

$checks[] = [
    'Campaign slot counts match claims',
    $claimMismatches === 0,
    $claimMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 6. Withdrawal Hold Verification
|--------------------------------------------------------------------------
*/

$holdMismatches = (int) $pdo->query("
    SELECT COUNT(*)
    FROM wallets w

    LEFT JOIN (
        SELECT
            user_id,
            SUM(amount) AS hold_total
        FROM withdrawals
        WHERE status IN ('pending', 'approved', 'processing')
        GROUP BY user_id
    ) wd ON wd.user_id = w.user_id

    WHERE ABS(
        w.withdrawal_hold_balance -
        COALESCE(wd.hold_total, 0)
    ) > 0.01
")->fetchColumn();

$checks[] = [
    'Withdrawal holds match open requests',
    $holdMismatches === 0,
    $holdMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 7. Approved Deposit Ledger Verification
|--------------------------------------------------------------------------
*/

$depositMismatches = (int) $pdo->query("
    SELECT COUNT(*)

    FROM manual_deposit_requests dr

    LEFT JOIN wallet_transactions wt
        ON wt.id = dr.wallet_transaction_id

    WHERE
        (
            dr.status = 'approved'
            AND (
                wt.id IS NULL
                OR wt.user_id <> dr.publisher_id
                OR wt.reference_type <> 'deposit_request'
                OR wt.reference_id <> dr.id
                OR ABS(wt.amount - dr.amount) > 0.01
                OR wt.direction <> 'credit'
                OR wt.bucket <> 'deposit'
            )
        )

        OR (
            dr.status <> 'approved'
            AND dr.wallet_transaction_id IS NOT NULL
        )
")->fetchColumn();

$checks[] = [
    'Approved deposits match publisher wallet ledger',
    $depositMismatches === 0,
    $depositMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 8. Duplicate Deposit References
|--------------------------------------------------------------------------
*/

$duplicateDepositRefs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM (
        SELECT reference_fingerprint
        FROM manual_deposit_requests
        GROUP BY reference_fingerprint
        HAVING COUNT(*) > 1
    ) d
")->fetchColumn();

$checks[] = [
    'Deposit transaction references are unique',
    $duplicateDepositRefs === 0,
    $duplicateDepositRefs . ' duplicate reference group(s)'
];

/*
|--------------------------------------------------------------------------
| 9. Campaign Budget and Slot Validation
|--------------------------------------------------------------------------
*/

$campaignMismatches = (int) $pdo->query("
    SELECT COUNT(*)
    FROM campaigns

    WHERE claimed_slots > worker_slots
    OR budget_remaining < 0
    OR budget_remaining > budget_total
")->fetchColumn();

$checks[] = [
    'Campaign budgets and slots valid',
    $campaignMismatches === 0,
    $campaignMismatches . ' invalid campaign(s)'
];

/*
|--------------------------------------------------------------------------
| 10. Campaign Budget Formula
|--------------------------------------------------------------------------
*/

$budgetFormulaMismatches = (int) $pdo->query("
    SELECT COUNT(*)
    FROM campaigns c

    WHERE ABS(
        c.budget_total -
        ROUND(
            (
                c.reward_per_worker +
                ROUND(
                    c.reward_per_worker *
                    c.publisher_fee_rate / 100,
                    2
                )
            ) * c.worker_slots,
            2
        )
    ) > 0.01
")->fetchColumn();

$checks[] = [
    'Campaign original budget matches saved fee terms',
    $budgetFormulaMismatches === 0,
    $budgetFormulaMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 11. Claim and Submission Status
|--------------------------------------------------------------------------
*/

$claimSubmissionMismatches = (int) $pdo->query("
    SELECT COUNT(*)

    FROM claims cl

    LEFT JOIN submissions s
        ON s.claim_id = cl.id

    WHERE
        (
            cl.status = 'claimed'
            AND s.id IS NOT NULL
        )

        OR (
            cl.status = 'submitted'
            AND (
                s.id IS NULL
                OR s.status <> 'pending'
            )
        )

        OR (
            cl.status = 'approved'
            AND (
                s.id IS NULL
                OR s.status <> 'approved'
            )
        )

        OR (
            cl.status = 'rejected'
            AND (
                s.id IS NULL
                OR s.status <> 'rejected'
            )
        )

        OR (
            cl.status = 'cancelled'
            AND s.id IS NOT NULL
        )
")->fetchColumn();

$checks[] = [
    'Claim and submission statuses agree',
    $claimSubmissionMismatches === 0,
    $claimSubmissionMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 12. Approved Reward Snapshot
|--------------------------------------------------------------------------
*/

$settlementMismatches = (int) $pdo->query("
    SELECT COUNT(*)

    FROM submissions s

    JOIN claims cl ON cl.id = s.claim_id

    JOIN campaigns c ON c.id = cl.campaign_id

    WHERE s.status = 'approved'

    AND (
        ABS(
            s.gross_reward -
            c.reward_per_worker
        ) > 0.01

        OR ABS(
            s.worker_fee -
            ROUND(
                s.gross_reward *
                c.worker_fee_rate / 100,
                2
            )
        ) > 0.01

        OR ABS(
            s.net_reward -
            (s.gross_reward - s.worker_fee)
        ) > 0.01
    )
")->fetchColumn();

$checks[] = [
    'Approved reward snapshots match campaign fees',
    $settlementMismatches === 0,
    $settlementMismatches . ' mismatch(es)'
];

/*
|--------------------------------------------------------------------------
| 13. Worker Reward Ledger
|--------------------------------------------------------------------------
*/

$workerRewardMismatch = (float) $pdo->query("
    SELECT ABS(
        COALESCE(
            (
                SELECT SUM(amount)
                FROM wallet_transactions
                WHERE transaction_type = 'worker_reward'
                AND bucket = 'available'
                AND direction = 'credit'
            ),
            0
        )

        -

        COALESCE(
            (
                SELECT SUM(net_reward)
                FROM submissions
                WHERE status = 'approved'
            ),
            0
        )
    )
")->fetchColumn();

$checks[] = [
    'Worker reward ledger matches approved submissions',
    $workerRewardMismatch <= 0.01,
    money($workerRewardMismatch) . ' difference'
];

/*
|--------------------------------------------------------------------------
| 14. Platform Commission Ledger
|--------------------------------------------------------------------------
*/

$platformFeeMismatch = (float) $pdo->query("
    SELECT ABS(

        COALESCE(
            (
                SELECT SUM(amount)
                FROM platform_transactions
                WHERE transaction_type = 'commission'
                AND reference_type = 'submission'
            ),
            0
        )

        -

        COALESCE(
            (
                SELECT SUM(
                    ROUND(
                        ROUND(
                            s.gross_reward *
                            c.publisher_fee_rate / 100,
                            2
                        ) + s.worker_fee,
                        2
                    )
                )

                FROM submissions s

                JOIN claims cl
                    ON cl.id = s.claim_id

                JOIN campaigns c
                    ON c.id = cl.campaign_id

                WHERE s.status = 'approved'
            ),
            0
        )
    )
")->fetchColumn();

$checks[] = [
    'Platform commission ledger matches approved submissions',
    $platformFeeMismatch <= 0.01,
    money($platformFeeMismatch) . ' difference'
];

/*
|--------------------------------------------------------------------------
| 15. Negative Wallet Balance Check
|--------------------------------------------------------------------------
*/

$negativeWallets = (int) $pdo->query("
    SELECT COUNT(*)
    FROM wallets

    WHERE available_balance < 0
    OR deposit_balance < 0
    OR reserved_balance < 0
    OR withdrawal_hold_balance < 0
")->fetchColumn();

$checks[] = [
    'Wallet balances are never negative',
    $negativeWallets === 0,
    $negativeWallets . ' invalid wallet(s)'
];

/*
|--------------------------------------------------------------------------
| Final Verification Report
|--------------------------------------------------------------------------
*/

$failed = 0;

echo "\n";
echo "============================================\n";
echo "       TASKHAAT DATABASE VERIFICATION\n";
echo "============================================\n\n";

foreach ($checks as [$label, $passed, $detail]) {

    echo ($passed ? '[PASS] ' : '[FAIL] ')
        . $label
        . ' — '
        . $detail
        . "\n";

    if (!$passed) {
        $failed++;
    }
}

/*
|--------------------------------------------------------------------------
| Database Record Counts
|--------------------------------------------------------------------------
*/

$counts = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM campaigns) AS campaigns,
        (SELECT COUNT(*) FROM claims) AS claims,
        (SELECT COUNT(*) FROM submissions) AS submissions,
        (SELECT COUNT(*) FROM notifications) AS notifications,
        (SELECT COUNT(*) FROM support_messages) AS support_tickets,
        (SELECT COUNT(*) FROM manual_deposit_requests) AS deposit_requests
")->fetch();

echo "\n";
echo "--------------------------------------------\n";
echo "DATABASE RECORD SUMMARY\n";
echo "--------------------------------------------\n";

foreach ($counts as $key => $value) {
    echo ucfirst(str_replace('_', ' ', $key))
        . ': '
        . $value
        . "\n";
}

echo "\n";
echo "--------------------------------------------\n";

if ($failed === 0) {

    echo "RESULT: ALL DATABASE CHECKS PASSED.\n";

} else {

    echo "RESULT: {$failed} DATABASE CHECK(S) FAILED.\n";
    echo "Review the mismatches before financial operations.\n";
}

echo "--------------------------------------------\n\n";

exit($failed === 0 ? 0 : 1);
