-- TaskHaat Phase 9: targeted composite indexes for marketplace sorting,
-- worker task history, audit filters and login-attempt retention.
-- Safe to run once; this script checks information_schema before adding each index.
-- Select the taskhaat database in phpMyAdmin before importing this file.

SET @taskhaat_schema = DATABASE();

SET @taskhaat_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@taskhaat_schema AND TABLE_NAME='login_attempts' AND INDEX_NAME='idx_login_attempt_retention');
SET @taskhaat_sql = IF(@taskhaat_exists=0, 'ALTER TABLE login_attempts ADD INDEX idx_login_attempt_retention (attempted_at)', 'SELECT ''idx_login_attempt_retention already exists'' AS phase9_status');
PREPARE taskhaat_stmt FROM @taskhaat_sql; EXECUTE taskhaat_stmt; DEALLOCATE PREPARE taskhaat_stmt;

SET @taskhaat_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@taskhaat_schema AND TABLE_NAME='campaigns' AND INDEX_NAME='idx_campaign_public_deadline_created');
SET @taskhaat_sql = IF(@taskhaat_exists=0, 'ALTER TABLE campaigns ADD INDEX idx_campaign_public_deadline_created (status, deadline, created_at, id)', 'SELECT ''idx_campaign_public_deadline_created already exists'' AS phase9_status');
PREPARE taskhaat_stmt FROM @taskhaat_sql; EXECUTE taskhaat_stmt; DEALLOCATE PREPARE taskhaat_stmt;

SET @taskhaat_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@taskhaat_schema AND TABLE_NAME='campaigns' AND INDEX_NAME='idx_campaign_category_public_deadline');
SET @taskhaat_sql = IF(@taskhaat_exists=0, 'ALTER TABLE campaigns ADD INDEX idx_campaign_category_public_deadline (status, category_id, deadline, created_at, id)', 'SELECT ''idx_campaign_category_public_deadline already exists'' AS phase9_status');
PREPARE taskhaat_stmt FROM @taskhaat_sql; EXECUTE taskhaat_stmt; DEALLOCATE PREPARE taskhaat_stmt;

SET @taskhaat_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@taskhaat_schema AND TABLE_NAME='claims' AND INDEX_NAME='idx_claim_worker_claimed_at');
SET @taskhaat_sql = IF(@taskhaat_exists=0, 'ALTER TABLE claims ADD INDEX idx_claim_worker_claimed_at (worker_id, claimed_at, id)', 'SELECT ''idx_claim_worker_claimed_at already exists'' AS phase9_status');
PREPARE taskhaat_stmt FROM @taskhaat_sql; EXECUTE taskhaat_stmt; DEALLOCATE PREPARE taskhaat_stmt;

SET @taskhaat_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@taskhaat_schema AND TABLE_NAME='audit_logs' AND INDEX_NAME='idx_audit_entity_created');
SET @taskhaat_sql = IF(@taskhaat_exists=0, 'ALTER TABLE audit_logs ADD INDEX idx_audit_entity_created (entity_type, created_at, id)', 'SELECT ''idx_audit_entity_created already exists'' AS phase9_status');
PREPARE taskhaat_stmt FROM @taskhaat_sql; EXECUTE taskhaat_stmt; DEALLOCATE PREPARE taskhaat_stmt;

SELECT 'TaskHaat Phase 9 performance indexes are present.' AS result;
