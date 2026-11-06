-- TaskHaat Phase 7 read-only QA checks
-- Safe to run in phpMyAdmin against the imported taskhaat database.
-- This file only runs SELECT statements; it does not change data.

-- 1) Schema inventory: expected to show 15 tables.
SELECT 'schema_table_count' AS check_name,
       COUNT(*) AS actual_value,
       15 AS expected_value,
       IF(COUNT(*) = 15, 'PASS', 'REVIEW') AS result
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';

-- 2) Wallet balances must equal the sum of their ledger entries.
SELECT 'wallet_ledger_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM wallets w
CROSS JOIN (
  SELECT 'available' AS bucket UNION ALL
  SELECT 'deposit' UNION ALL
  SELECT 'reserved' UNION ALL
  SELECT 'withdrawal_hold'
) b
LEFT JOIN (
  SELECT user_id, bucket,
         SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END) AS ledger_balance
  FROM wallet_transactions GROUP BY user_id, bucket
) l ON l.user_id=w.user_id AND l.bucket=b.bucket
WHERE ABS(COALESCE(l.ledger_balance,0)-CASE
  WHEN b.bucket='available' THEN w.available_balance
  WHEN b.bucket='deposit' THEN w.deposit_balance
  WHEN b.bucket='reserved' THEN w.reserved_balance
  WHEN b.bucket='withdrawal_hold' THEN w.withdrawal_hold_balance
END) > 0.01;

-- 3) Publisher reserved balances must equal all unspent campaign reserves.
SELECT 'publisher_reserve_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM wallets w JOIN users u ON u.id=w.user_id
LEFT JOIN (
  SELECT publisher_id, SUM(budget_remaining) AS reserve_total
  FROM campaigns GROUP BY publisher_id
) c ON c.publisher_id=u.id
WHERE u.user_type IN ('publisher','both')
  AND ABS(w.reserved_balance-COALESCE(c.reserve_total,0)) > 0.01;

-- 4) Campaign claimed_slots must equal the number of claim records.
SELECT 'campaign_slot_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM campaigns c
LEFT JOIN (
  SELECT campaign_id, COUNT(*) AS claim_count
  FROM claims GROUP BY campaign_id
) cl ON cl.campaign_id=c.id
WHERE c.claimed_slots <> COALESCE(cl.claim_count,0);

-- 5) Claim and submission states must agree.
SELECT 'claim_submission_status_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM claims cl
LEFT JOIN submissions s ON s.claim_id=cl.id
WHERE (cl.status='claimed' AND s.id IS NOT NULL)
   OR (cl.status='submitted' AND (s.id IS NULL OR s.status<>'pending'))
   OR (cl.status='approved' AND (s.id IS NULL OR s.status<>'approved'))
   OR (cl.status='rejected' AND (s.id IS NULL OR s.status<>'rejected'))
   OR (cl.status='cancelled' AND s.id IS NOT NULL);

-- 6) Campaign budget totals must agree with the fee rates saved at creation/edit time.
SELECT 'campaign_budget_formula_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM campaigns c
WHERE ABS(c.budget_total-ROUND((c.reward_per_worker + ROUND(c.reward_per_worker*c.publisher_fee_rate/100,2))*c.worker_slots,2)) > 0.01;

-- 7) Approved submission reward snapshots must agree with campaign terms.
SELECT 'approved_reward_snapshot_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM submissions s
JOIN claims cl ON cl.id=s.claim_id
JOIN campaigns c ON c.id=cl.campaign_id
WHERE s.status='approved'
  AND (ABS(s.gross_reward-c.reward_per_worker)>0.01
    OR ABS(s.worker_fee-ROUND(s.gross_reward*c.worker_fee_rate/100,2))>0.01
    OR ABS(s.net_reward-(s.gross_reward-s.worker_fee))>0.01);

-- 8) Worker reward ledger credits must equal approved net rewards.
SELECT 'worker_reward_ledger_difference' AS check_name,
       ROUND(ABS(
         COALESCE((SELECT SUM(amount) FROM wallet_transactions
                   WHERE transaction_type='worker_reward' AND bucket='available' AND direction='credit'),0)
         - COALESCE((SELECT SUM(net_reward) FROM submissions WHERE status='approved'),0)
       ),2) AS difference_bdt;

-- 9) Platform commission ledger must equal publisher fee + worker fee on approved submissions.
SELECT 'platform_commission_difference' AS check_name,
       ROUND(ABS(
         COALESCE((SELECT SUM(amount) FROM platform_transactions
                   WHERE transaction_type='commission' AND reference_type='submission'),0)
         - COALESCE((SELECT SUM(ROUND(ROUND(s.gross_reward*c.publisher_fee_rate/100,2)+s.worker_fee,2))
                     FROM submissions s JOIN claims cl ON cl.id=s.claim_id
                     JOIN campaigns c ON c.id=cl.campaign_id WHERE s.status='approved'),0)
       ),2) AS difference_bdt;

-- 10) Pending/approved/processing withdrawals must equal the withdrawal hold balance.
SELECT 'withdrawal_hold_mismatches' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM wallets w
LEFT JOIN (
  SELECT user_id, SUM(amount) AS hold_total
  FROM withdrawals WHERE status IN ('pending','approved','processing')
  GROUP BY user_id
) wd ON wd.user_id=w.user_id
WHERE ABS(w.withdrawal_hold_balance-COALESCE(wd.hold_total,0)) > 0.01;

-- 11) Wallet buckets must never be negative.
SELECT 'negative_wallet_balances' AS check_name, COUNT(*) AS issue_count,
       IF(COUNT(*) = 0, 'PASS', 'FAIL') AS result
FROM wallets
WHERE available_balance<0 OR deposit_balance<0 OR reserved_balance<0 OR withdrawal_hold_balance<0;

-- 12) Demo account inventory.
SELECT user_type, status, COUNT(*) AS account_count
FROM users GROUP BY user_type, status ORDER BY user_type, status;

-- 13) Core workflow data inventory.
SELECT
  (SELECT COUNT(*) FROM campaigns) AS campaigns,
  (SELECT COUNT(*) FROM claims) AS claims,
  (SELECT COUNT(*) FROM submissions) AS submissions,
  (SELECT COUNT(*) FROM withdrawals) AS withdrawals,
  (SELECT COUNT(*) FROM notifications) AS notifications,
  (SELECT COUNT(*) FROM support_messages) AS support_tickets;
