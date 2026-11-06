-- =====================================================================
-- TASKHAAT COMPLETE DATABASE + DEMO DATA
-- MySQL 8.0.16+ / MariaDB 10.5+
-- Database: taskhaat
--
-- CLEAN INSTALL / RESET SCRIPT:
--   * Creates the taskhaat database if it does not exist.
--   * DROPS and recreates all TaskHaat tables.
--   * Inserts 19 tables, default categories/settings, admin + demo users,
--     campaigns, claims, submissions, wallet ledger, withdrawals, manual deposit
--     requests, notifications, audit activity and support inbox messages.
--
-- IMPORTANT:
--   Existing TaskHaat database records will be DELETED when imported.
--   Export a backup first if you need to keep anything.
--   No separate seed.php run is required after importing this file.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS taskhaat
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE taskhaat;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+06:00';

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS site_announcements;
DROP TABLE IF EXISTS site_faqs;
DROP TABLE IF EXISTS site_content;
DROP TABLE IF EXISTS support_messages;
DROP TABLE IF EXISTS manual_deposit_requests;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS withdrawals;
DROP TABLE IF EXISTS platform_transactions;
DROP TABLE IF EXISTS wallet_transactions;
DROP TABLE IF EXISTS submission_files;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USERS
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'Bangladesh',
  district VARCHAR(100) NULL,
  city VARCHAR(120) NULL,
  occupation VARCHAR(120) NULL,
  skills TEXT NULL,
  organization VARCHAR(180) NULL,
  website VARCHAR(255) NULL,
  referral_code VARCHAR(50) NULL,
  terms_accepted_at DATETIME NULL,
  password_hash VARCHAR(255) NOT NULL,
  user_type ENUM('worker','publisher','both','admin') NOT NULL DEFAULT 'worker',
  status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_status_type (status, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. LOGIN ATTEMPTS (privacy-preserving HMAC identifiers only)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_email_time (email_hash, was_successful, attempted_at),
  KEY idx_login_ip_time (ip_hash, was_successful, attempted_at),
  KEY idx_login_attempt_retention (attempted_at),
  CONSTRAINT chk_login_attempt_success CHECK (was_successful IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. WALLETS
-- ---------------------------------------------------------------------
CREATE TABLE wallets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  available_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  deposit_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reserved_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  withdrawal_hold_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallets_user (user_id),
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_wallet_available CHECK (available_balance >= 0),
  CONSTRAINT chk_wallet_deposit CHECK (deposit_balance >= 0),
  CONSTRAINT chk_wallet_reserved CHECK (reserved_balance >= 0),
  CONSTRAINT chk_wallet_withdrawal_hold CHECK (withdrawal_hold_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. CAMPAIGNS
-- ---------------------------------------------------------------------
CREATE TABLE campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  publisher_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  reward_per_worker DECIMAL(10,2) NOT NULL,
  worker_slots INT UNSIGNED NOT NULL,
  claimed_slots INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_time VARCHAR(50) NULL,
  deadline DATETIME NULL,
  proof_type VARCHAR(80) NOT NULL DEFAULT 'Text',
  proof_instructions TEXT NOT NULL,
  cover_image VARCHAR(190) NULL,
  status ENUM('pending','approved','rejected','paused','completed','cancelled') NOT NULL DEFAULT 'pending',
  publisher_fee_rate DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  worker_fee_rate DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  budget_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  budget_remaining DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  review_note TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_campaign_status_deadline (status, deadline),
  KEY idx_campaign_public_deadline_created (status, deadline, created_at, id),
  KEY idx_campaign_category_public_deadline (status, category_id, deadline, created_at, id),
  KEY idx_campaign_publisher (publisher_id, created_at),
  KEY idx_campaign_category (category_id),
  KEY idx_campaign_reviewer (reviewed_by),
  CONSTRAINT fk_campaign_publisher FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_campaign_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_campaign_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_campaign_reward_positive CHECK (reward_per_worker > 0),
  CONSTRAINT chk_campaign_slots_positive CHECK (worker_slots > 0),
  CONSTRAINT chk_campaign_claims_within_slots CHECK (claimed_slots <= worker_slots),
  CONSTRAINT chk_campaign_budget_nonnegative CHECK (budget_total >= 0 AND budget_remaining >= 0),
  CONSTRAINT chk_campaign_remaining_within_total CHECK (budget_remaining <= budget_total),
  CONSTRAINT chk_campaign_fee_rates CHECK (publisher_fee_rate BETWEEN 0 AND 30 AND worker_fee_rate BETWEEN 0 AND 30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. CLAIMS
-- ---------------------------------------------------------------------
CREATE TABLE claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  worker_id BIGINT UNSIGNED NOT NULL,
  status ENUM('claimed','submitted','approved','rejected','cancelled') NOT NULL DEFAULT 'claimed',
  claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_campaign_worker (campaign_id, worker_id),
  KEY idx_claim_worker_status (worker_id, status),
  KEY idx_claim_worker_claimed_at (worker_id, claimed_at, id),
  KEY idx_claim_campaign_status (campaign_id, status),
  CONSTRAINT fk_claim_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_claim_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. SUBMISSIONS
-- ---------------------------------------------------------------------
CREATE TABLE submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_id BIGINT UNSIGNED NOT NULL,
  notes TEXT NOT NULL,
  evidence_url VARCHAR(1000) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  feedback TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  gross_reward DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  worker_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  net_reward DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_claim (claim_id),
  KEY idx_submission_status (status, created_at),
  KEY idx_submission_reviewer (reviewed_by),
  CONSTRAINT fk_submission_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_submission_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_submission_rewards_nonnegative CHECK (gross_reward >= 0 AND worker_fee >= 0 AND net_reward >= 0),
  CONSTRAINT chk_submission_fee_within_reward CHECK (worker_fee <= gross_reward AND net_reward <= gross_reward)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. SUBMISSION FILES
-- ---------------------------------------------------------------------
CREATE TABLE submission_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id BIGINT UNSIGNED NOT NULL,
  storage_name VARCHAR(190) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_submission_files_submission (submission_id),
  CONSTRAINT fk_file_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. WALLET TRANSACTIONS
-- ---------------------------------------------------------------------
CREATE TABLE wallet_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  bucket ENUM('available','deposit','reserved','withdrawal_hold') NOT NULL,
  direction ENUM('credit','debit') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  transaction_type VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  idempotency_key CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_idempotency (idempotency_key),
  KEY idx_wallet_tx_user_date (user_id, created_at),
  KEY idx_wallet_tx_reference (reference_type, reference_id),
  CONSTRAINT fk_wallet_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_wallet_tx_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. PLATFORM TRANSACTIONS
-- ---------------------------------------------------------------------
CREATE TABLE platform_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  amount DECIMAL(12,2) NOT NULL,
  transaction_type VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL,
  reference_type VARCHAR(50) NULL,
  reference_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_platform_tx_date (created_at),
  KEY idx_platform_tx_reference (reference_type, reference_id),
  CONSTRAINT chk_platform_tx_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. WITHDRAWALS
-- ---------------------------------------------------------------------
CREATE TABLE withdrawals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('bKash','Nagad','Rocket','Bank transfer') NOT NULL,
  account_reference VARCHAR(500) NOT NULL,
  account_reference_last4 CHAR(4) NULL,
  status ENUM('pending','approved','processing','paid','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  payment_reference VARCHAR(120) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_withdrawals_status_date (status, created_at),
  KEY idx_withdrawals_user (user_id, created_at),
  KEY idx_withdrawals_reviewer (reviewed_by),
  KEY idx_withdrawal_last4 (account_reference_last4),
  CONSTRAINT fk_withdrawal_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_withdrawal_admin FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_withdrawal_amount_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. MANUAL DEPOSIT REQUESTS (offline verification; no gateway)
-- ---------------------------------------------------------------------
CREATE TABLE manual_deposit_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  publisher_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('bKash','Nagad','Rocket','Bank transfer') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  sender_reference VARCHAR(120) NOT NULL,
  reference_fingerprint CHAR(64) NOT NULL,
  proof_storage_name VARCHAR(190) NULL,
  proof_original_name VARCHAR(255) NULL,
  proof_mime_type VARCHAR(100) NULL,
  proof_file_size INT UNSIGNED NULL,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  wallet_transaction_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_deposit_reference_fingerprint (reference_fingerprint),
  UNIQUE KEY uq_deposit_wallet_tx (wallet_transaction_id),
  KEY idx_deposit_publisher_date (publisher_id, created_at),
  KEY idx_deposit_status_date (status, created_at),
  KEY idx_deposit_reviewer (reviewed_by),
  CONSTRAINT fk_deposit_publisher FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_deposit_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_deposit_wallet_transaction FOREIGN KEY (wallet_transaction_id) REFERENCES wallet_transactions(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_deposit_amount_positive CHECK (amount > 0),
  CONSTRAINT chk_deposit_proof_metadata CHECK ((proof_storage_name IS NULL AND proof_original_name IS NULL AND proof_mime_type IS NULL AND proof_file_size IS NULL) OR (proof_storage_name IS NOT NULL AND proof_original_name IS NOT NULL AND proof_mime_type IS NOT NULL AND proof_file_size IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. NOTIFICATIONS
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'general',
  link VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_read (user_id, is_read, created_at),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. SETTINGS
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. AUDIT LOGS
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  meta_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_date (created_at),
  KEY idx_audit_actor (actor_id),
  KEY idx_audit_entity_created (entity_type, created_at, id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. SUPPORT MESSAGES
-- ---------------------------------------------------------------------
CREATE TABLE support_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  sender_name VARCHAR(120) NOT NULL,
  sender_email VARCHAR(190) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  handled_by BIGINT UNSIGNED NULL,
  handled_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_support_status_date (status, created_at),
  KEY idx_support_email_date (sender_email, created_at),
  KEY idx_support_user (user_id),
  CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_support_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DEFAULT CATEGORIES & SETTINGS
-- =====================================================================

-- Phase 10 website management tables (additive).
CREATE TABLE IF NOT EXISTS site_content (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_key VARCHAR(120) NOT NULL,
  label VARCHAR(160) NOT NULL,
  content_group ENUM('branding','homepage','navigation','footer','general') NOT NULL DEFAULT 'homepage',
  content_type ENUM('text','textarea','url','boolean') NOT NULL DEFAULT 'text',
  content_value LONGTEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_content_key (content_key),
  KEY idx_site_content_group_order (content_group,is_public,sort_order),
  CONSTRAINT fk_site_content_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_content_public CHECK (is_public IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_faqs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_faq_active_order (is_active,sort_order,id),
  CONSTRAINT fk_site_faq_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_faq_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(1200) NOT NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(500) NULL,
  style ENUM('info','success','warning') NOT NULL DEFAULT 'info',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_announcement_schedule (is_active,starts_at,ends_at,sort_order),
  CONSTRAINT fk_site_announcement_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_announcement_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_content
(content_key,label,content_group,content_type,content_value,is_public,sort_order)
VALUES
('site_brand_name','Website brand name','branding','text','TaskHaat',1,1),
('topbar_message','Topbar message','navigation','text','Legitimate tasks. Clear proof. Fair rewards.',1,2),
('footer_description','Footer description','footer','textarea','A practical microtask marketplace for legitimate short work, transparent proof review and clear rewards.',1,1),
('home_hero_visible','Show hero section','homepage','boolean','1',1,1),
('home_hero_eyebrow','Hero eyebrow','homepage','text','A smarter way to get small work done',1,10),
('home_hero_title_primary','Hero title — first line','homepage','text','Micro tasks.',1,11),
('home_hero_title_accent','Hero title — accent line','homepage','text','Real progress.',1,12),
('home_hero_description','Hero description','homepage','textarea','Find flexible online tasks, submit clear proof, and earn rewards when your work is approved. Or publish a campaign and reach many workers at once.',1,13),
('home_stats_visible','Show live statistics','homepage','boolean','1',1,20),
('home_categories_visible','Show categories section','homepage','boolean','1',1,30),
('home_categories_eyebrow','Categories eyebrow','homepage','text','Explore by category',1,31),
('home_categories_title','Categories heading','homepage','text','Find a task that fits your skills',1,32),
('home_categories_description','Categories description','homepage','textarea','Short, clearly explained work across research, testing, data, content and more.',1,33),
('home_features_visible','Show features section','homepage','boolean','1',1,40),
('home_features_eyebrow','Features eyebrow','homepage','text','Built for everyday work',1,41),
('home_features_title','Features heading','homepage','text','A simple system, from start to finish',1,42),
('home_features_description','Features description','homepage','textarea','Keep task instructions, proof, decisions and rewards visible in one place.',1,43),
('home_how_visible','Show how-it-works section','homepage','boolean','1',1,50),
('home_how_eyebrow','How-it-works eyebrow','homepage','text','How TaskHaat works',1,51),
('home_how_title','How-it-works heading','homepage','text','Two sides. One clear workflow.',1,52),
('home_how_description','How-it-works description','homepage','textarea','Workers complete tasks; publishers distribute small jobs to multiple people and review the evidence.',1,53),
('home_tasks_visible','Show fresh tasks section','homepage','boolean','1',1,60),
('home_tasks_eyebrow','Fresh tasks eyebrow','homepage','text','Fresh opportunities',1,61),
('home_tasks_title','Fresh tasks heading','homepage','text','Tasks ready to explore',1,62),
('home_tasks_description','Fresh tasks description','homepage','textarea','Each campaign has a fixed reward per approved worker and a visible number of available slots.',1,63),
('home_mobile_visible','Show mobile showcase section','homepage','boolean','1',1,70),
('home_stories_visible','Show community stories section','homepage','boolean','1',1,80),
('home_stories_eyebrow','Stories eyebrow','homepage','text','Community stories',1,81),
('home_stories_title','Stories heading','homepage','text','Small tasks can make useful progress',1,82),
('home_stories_description','Stories description','homepage','textarea','Illustrative preview testimonials. Real reviews will come from verified completed tasks.',1,83),
('home_guides_visible','Show guides section','homepage','boolean','1',1,90),
('home_guides_eyebrow','Guides eyebrow','homepage','text','Guides & resources',1,91),
('home_guides_title','Guides heading','homepage','text','Know the rules before you begin',1,92),
('home_guides_description','Guides description','homepage','textarea','Short guides to help workers and publishers use the marketplace responsibly.',1,93),
('home_safety_visible','Show trust and safety section','homepage','boolean','1',1,100),
('home_safety_eyebrow','Safety eyebrow','homepage','text','Trust and safety',1,101),
('home_safety_title','Safety heading','homepage','text','Clear work. Responsible participation.',1,102),
('home_safety_description','Safety description','homepage','textarea','TaskHaat is intended for legitimate surveys, usability testing, research, data entry and similar tasks that respect privacy and platform rules.',1,103),
('home_faq_visible','Show FAQ section','homepage','boolean','1',1,110),
('home_faq_eyebrow','FAQ eyebrow','homepage','text','Need a little clarity?',1,111),
('home_faq_title','FAQ heading','homepage','text','Frequently asked questions',1,112),
('home_faq_description','FAQ description','homepage','textarea','Quick answers about task slots, proof review, rewards and publishing campaigns.',1,113),
('home_cta_visible','Show final call-to-action section','homepage','boolean','1',1,120),
('home_cta_title','Final CTA heading','homepage','text','Ready to make progress, one task at a time?',1,121),
('home_cta_description','Final CTA description','homepage','textarea','Join as a worker or bring a small campaign to a wider group.',1,122);

INSERT IGNORE INTO site_faqs (question,answer,sort_order,is_active) VALUES
('Can multiple workers complete the same task?','Yes. A publisher sets the number of worker slots. Each worker claims one available slot and submits a separate proof. The same worker cannot claim the same campaign more than once.',10,1),
('When do workers receive rewards?','After a submission is approved, the configured platform fee is deducted and the net reward is credited to the worker''s Earned Wallet. The live platform will show the fee before work is claimed.',20,1),
('What proof can a worker submit?','Depending on the campaign: written answers, a public URL, a screenshot or an allowed attachment. Never submit passwords, OTPs, private account credentials or sensitive personal information.',30,1),
('How does a publisher set a campaign budget?','Budget is calculated from reward per worker multiplied by the total worker slots, plus the platform service fee. The amount should be reserved before the campaign becomes available.',40,1),
('Which tasks are prohibited?','TaskHaat does not allow fake views, likes, follows, reviews, bulk Gmail/account creation, credential handover, OTP collection, spam or deceptive activity that manipulates third-party platforms.',50,1);

INSERT INTO categories (id,name,slug,description,is_active) VALUES
 (1,'Surveys & Research','surveys-research','Feedback, questionnaires and public research',1),
 (2,'Website & App Testing','website-app-testing','Usability testing, QA and bug reports',1),
 (3,'Data & Verification','data-verification','Data entry, checking and labelling',1),
 (4,'Transcription','transcription','Audio and video transcription',1),
 (5,'Content Review','content-review','Quality review and content checks',1),
 (6,'Local Research','local-research','Public information and local research',1);

INSERT INTO settings (setting_key,setting_value) VALUES
 ('publisher_fee_rate','8.00'),
 ('worker_fee_rate','8.00'),
 ('min_withdrawal','200.00'),
 ('max_withdrawal','25000.00'),
 ('min_deposit','100.00'),
 ('max_deposit','1000000.00'),
 ('manual_bkash_account_name',''),('manual_bkash_number',''),
 ('manual_nagad_account_name',''),('manual_nagad_number',''),
 ('manual_rocket_account_name',''),('manual_rocket_number',''),
 ('manual_bank_name',''),('manual_bank_account_name',''),('manual_bank_account_number',''),('manual_bank_branch',''),
 ('manual_deposit_instructions',''),
 ('platform_name','TaskHaat'),
 ('maintenance_mode','0');

-- =====================================================================
-- DEMO ACCOUNTS
-- All passwords use PHP password_hash() bcrypt hashes.
-- Admin:      admin@taskhaat.local      / Admin@12345
-- Publisher:  publisher@taskhaat.local  / Publisher@12345
-- Publisher2: publisher2@taskhaat.local / Publisher2@12345
-- Worker:     worker@taskhaat.local     / Worker@12345
-- Worker2:    worker2@taskhaat.local    / Worker2@12345
-- Worker3:    worker3@taskhaat.local    / Worker3@12345
-- Worker4:    worker4@taskhaat.local    / Worker4@12345
-- =====================================================================
INSERT INTO users
 (id,first_name,last_name,email,phone,country,district,city,occupation,skills,organization,website,referral_code,terms_accepted_at,password_hash,user_type,status,email_verified_at,last_login_at,created_at)
VALUES
 (1,'TaskHaat','Admin','admin@taskhaat.local','+8800000000001','Bangladesh','Dhaka','Dhaka','Platform Administrator','Platform operations, moderation, finance','TaskHaat','https://example.com/taskhaat-admin','DEMO-ADMIN',NOW(), '$2y$12$tJuB7MLuE85sT/Xs.FtkGuizRZXQSN1eyD7sYx7YhtxZcjxi0PY7q','admin','active',NOW(),NOW(),NOW()-INTERVAL 40 DAY),
 (2,'Ayesha','Rahman','publisher@taskhaat.local','+8800000000002','Bangladesh','Dhaka','Mirpur','Research Coordinator','User research, surveys, usability testing','Demo Research Lab','https://example.com/research-lab','DEMO-PUB-01',NOW(), '$2y$12$HkD11x3jcbn/3Clo9Xv.XubHyYlT0nYmTVkmyXE8/FlyaZSjpgxNK','publisher','active',NOW(),NOW()-INTERVAL 1 HOUR,NOW()-INTERVAL 30 DAY),
 (3,'Tanvir','Hasan','publisher2@taskhaat.local','+8800000000003','Bangladesh','Chattogram','Chattogram','Product Manager','Product QA, data quality, app testing','Demo Digital Studio','https://example.com/digital-studio','DEMO-PUB-02',NOW(), '$2y$12$re3pviPcx9.t8IFHP5FO6OrxCZDNVDyw00HH6BhzkHgQrdCmE9xym','publisher','active',NOW(),NOW()-INTERVAL 3 HOUR,NOW()-INTERVAL 24 DAY),
 (4,'Nusrat','Jahan','worker@taskhaat.local','+8800000000004','Bangladesh','Dhaka','Uttara','University Student','Survey participation, usability testing, research','IUBAT Demo Profile','https://example.com/nusrat-portfolio','DEMO-WRK-01',NOW(), '$2y$12$rnOjJckO6X9bTHtfSx9ts.aOY4.xJEoqiUmLIoKTYAcGpf2zBYN92','worker','active',NOW(),NOW()-INTERVAL 2 HOUR,NOW()-INTERVAL 18 DAY),
 (5,'Sabbir','Ahmed','worker2@taskhaat.local','+8800000000005','Bangladesh','Gazipur','Tongi','Freelance Research Assistant','Data verification, transcription, surveys','',NULL,'DEMO-WRK-02',NOW(), '$2y$12$UbRFO2V743jANe0rw4CnjekQ3PR3G8nKrGdftbsZxzi1CsyB8cc3q','worker','active',NOW(),NOW()-INTERVAL 5 HOUR,NOW()-INTERVAL 15 DAY),
 (6,'Maliha','Akter','worker3@taskhaat.local','+8800000000006','Bangladesh','Narayanganj','Narayanganj','Content Reviewer','Content review, local research, data entry','',NULL,'DEMO-WRK-03',NOW(), '$2y$12$7PU2FIMOLUT.WqTTs/2c7uyM52lrdC4yjwjdl7phLtbyixETdwZFC','worker','active',NOW(),NOW()-INTERVAL 1 DAY,NOW()-INTERVAL 12 DAY),
 (7,'Rafi','Islam','worker4@taskhaat.local','+8800000000007','Bangladesh','Cumilla','Cumilla','Web Tester','Website testing, bug reporting, screenshots','',NULL,'DEMO-WRK-04',NOW(), '$2y$12$GPR6i/xadipVbBZWV01zMevUv36mS9s2OOtpyNuyZQd2le/etUUFG','worker','active',NOW(),NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 9 DAY);

-- Every account receives exactly one wallet.
INSERT INTO wallets (id,user_id,available_balance,deposit_balance,reserved_balance,withdrawal_hold_balance) VALUES
 (1,1,0.00,0.00,0.00,0.00),
 (2,2,0.00,4880.80,4579.20,0.00),
 (3,3,0.00,1456.60,1895.40,0.00),
 (4,4,436.00,0.00,0.00,300.00),
 (5,5,76.00,0.00,0.00,0.00),
 (6,6,0.00,0.00,0.00,0.00),
 (7,7,0.00,0.00,0.00,0.00);

-- =====================================================================
-- DEMO CAMPAIGNS
-- Campaign budget_total = reward * slots * (1 + publisher fee rate).
-- budget_remaining reflects reserve not yet settled/refunded.
-- =====================================================================
INSERT INTO campaigns
 (id,publisher_id,category_id,title,description,reward_per_worker,worker_slots,claimed_slots,estimated_time,deadline,proof_type,proof_instructions,cover_image,status,publisher_fee_rate,worker_fee_rate,budget_total,budget_remaining,review_note,reviewed_by,reviewed_at,created_at)
VALUES
 (1,2,2,'Test a mobile e-commerce checkout flow','Explore the demo storefront on a mobile browser. Add one product to the cart, move through the checkout screens and report any confusing step or visual issue. Do not enter real payment details or personal information.',500.00,8,3,'15–20 min',NOW()+INTERVAL 12 DAY,'Text + screenshot','Submit the device/browser used, the steps completed, a short usability note and one screenshot of the final demo checkout state. Never submit passwords, OTPs or payment details.',NULL,'approved',8.00,8.00,4320.00,3780.00,NULL,1,NOW()-INTERVAL 6 DAY,NOW()-INTERVAL 8 DAY),
 (2,2,1,'Share feedback on a public transport survey','Complete a short demo questionnaire about commuting preferences, public transport usability and travel information. All answers should be based on your own general experience; no sensitive personal information is requested.',50.00,10,0,'5–7 min',NOW()+INTERVAL 18 DAY,'Structured answers','Submit the survey completion code and a short note confirming that every required question was answered. Do not include private account information.',NULL,'pending',8.00,8.00,540.00,540.00,NULL,NULL,NULL,NOW()-INTERVAL 1 DAY),
 (3,2,3,'Verify public information on a local service directory','Review a small set of public business-directory entries for formatting consistency and broken links. Use only information that is publicly visible and do not contact businesses.',80.00,4,2,'8–12 min',NOW()+INTERVAL 9 DAY,'URL + notes','Submit the reviewed entry references, identify any broken URL and describe the correction needed. Do not collect private contact details.',NULL,'paused',8.00,8.00,345.60,259.20,'Paused by publisher while reviewing the remaining worker slot.',1,NOW()-INTERVAL 5 DAY,NOW()-INTERVAL 7 DAY),
 (4,3,3,'Check a sample dataset for duplicate records','Review a provided sample dataset and identify duplicate rows using the supplied matching rules. This is a demonstration data-quality task with fictional records only.',300.00,2,2,'30–45 min',NOW()-INTERVAL 1 DAY,'Structured answers','Submit the duplicate record IDs and a brief explanation of the matching rule used. Attachments are not required for this demo.',NULL,'completed',8.00,8.00,648.00,0.00,NULL,1,NOW()-INTERVAL 4 DAY,NOW()-INTERVAL 9 DAY),
 (5,3,5,'Review short product descriptions for clarity','Read a set of short fictional product descriptions and flag grammar, clarity or consistency issues. Do not rewrite protected brand copy or use external customer data.',60.00,6,0,'8–12 min',NOW()+INTERVAL 20 DAY,'Text + reference','Provide the item number, the sentence needing attention and a concise suggested improvement.',NULL,'pending',8.00,8.00,388.80,388.80,NULL,NULL,NULL,NOW()-INTERVAL 8 HOUR),
 (6,3,4,'Transcribe a short sample audio clip','Create a short transcript from the provided public-domain sample audio and mark any uncertain words with timestamps. This is a demonstration task.',50.00,3,0,'15–20 min',NOW()+INTERVAL 5 DAY,'Text + reference','Submit the transcript text, timestamps for uncertain words and the sample reference code.',NULL,'rejected',8.00,8.00,162.00,0.00,'Please clarify the allowed source and provide a more specific acceptance checklist before resubmitting.',1,NOW()-INTERVAL 3 DAY,NOW()-INTERVAL 5 DAY);

-- Additional approved campaigns keep the public marketplace populated for demo browsing.
INSERT INTO campaigns
 (id,publisher_id,category_id,title,description,reward_per_worker,worker_slots,claimed_slots,estimated_time,deadline,proof_type,proof_instructions,cover_image,status,publisher_fee_rate,worker_fee_rate,budget_total,budget_remaining,review_note,reviewed_by,reviewed_at,created_at)
VALUES
 (7,3,6,'Research public library opening information','Check a short list of public library pages and record opening hours and service notices that are visible to everyone. Use only official public web pages.',90.00,8,0,'8–12 min',NOW()+INTERVAL 14 DAY,'URL + notes','Provide the official page URL and the opening information displayed. Do not call or message any library staff.',NULL,'approved',8.00,8.00,777.60,777.60,NULL,1,NOW()-INTERVAL 4 DAY,NOW()-INTERVAL 6 DAY),
 (8,3,2,'Try a demo account creation journey','Explore the public registration journey using the demo-only site. Note unclear field labels and identify any layout issue on desktop or mobile. Never use real personal data.',45.00,15,0,'5–7 min',NOW()+INTERVAL 16 DAY,'Text + screenshot','List the page and field where you noticed an issue. Include a screenshot with any personal information hidden.',NULL,'approved',8.00,8.00,729.00,729.00,NULL,1,NOW()-INTERVAL 3 DAY,NOW()-INTERVAL 5 DAY);

-- =====================================================================
-- DEMO CLAIMS
-- =====================================================================
INSERT INTO claims (id,campaign_id,worker_id,status,claimed_at) VALUES
 (1,1,4,'approved',NOW()-INTERVAL 6 DAY),
 (2,1,5,'submitted',NOW()-INTERVAL 1 DAY),
 (3,1,6,'claimed',NOW()-INTERVAL 5 HOUR),
 (4,3,7,'rejected',NOW()-INTERVAL 4 DAY),
 (5,3,6,'claimed',NOW()-INTERVAL 2 DAY),
 (6,4,4,'approved',NOW()-INTERVAL 7 DAY),
 (7,4,5,'approved',NOW()-INTERVAL 6 DAY);

-- =====================================================================
-- DEMO SUBMISSIONS
-- Approved reward uses 8% worker fee: net = gross - worker fee.
-- =====================================================================
INSERT INTO submissions
 (id,claim_id,notes,evidence_url,status,feedback,reviewed_by,reviewed_at,gross_reward,worker_fee,net_reward,created_at)
VALUES
 (1,1,'Completed the mobile checkout walkthrough. The cart summary was clear and the order review screen displayed the expected total.','https://example.com/demo-evidence/checkout-review','approved','Good evidence and clear steps. Thank you for the detailed usability note.',2,NOW()-INTERVAL 5 DAY,500.00,40.00,460.00,NOW()-INTERVAL 6 DAY),
 (2,2,'Completed the checkout flow on Android Chrome. I found one label that could be clearer and attached the requested demo evidence link.','https://example.com/demo-evidence/checkout-mobile','pending',NULL,NULL,NULL,0.00,0.00,0.00,NOW()-INTERVAL 1 DAY),
 (3,4,'Reviewed the assigned directory entries. One reference did not open and another entry had inconsistent spacing.','https://example.com/demo-evidence/directory-check','rejected','Please include the exact entry IDs and a screenshot showing the broken link. The submitted notes were not specific enough.',2,NOW()-INTERVAL 3 DAY,0.00,0.00,0.00,NOW()-INTERVAL 4 DAY),
 (4,6,'Identified duplicate records 104 and 118 using the provided email + date matching rule.','https://example.com/demo-evidence/duplicates-a','approved','Accepted. The duplicate rule and record references are clear.',3,NOW()-INTERVAL 6 DAY,300.00,24.00,276.00,NOW()-INTERVAL 7 DAY),
 (5,7,'Found duplicate rows 205 and 207. The name and timestamp matched the supplied duplicate criteria.','https://example.com/demo-evidence/duplicates-b','approved','Accepted. Good work.',3,NOW()-INTERVAL 5 DAY,300.00,24.00,276.00,NOW()-INTERVAL 6 DAY);

-- =====================================================================
-- DEMO WITHDRAWALS
-- Worker 1: pending ৳300, therefore ৳300 is held and not available.
-- Worker 2: paid ৳200, no remaining hold for this request.
-- =====================================================================
INSERT INTO withdrawals
 (id,user_id,amount,method,account_reference,account_reference_last4,status,admin_note,payment_reference,reviewed_by,reviewed_at,created_at)
VALUES
 (1,4,300.00,'bKash','01700000004','0004','pending',NULL,NULL,NULL,NULL,NOW()-INTERVAL 3 HOUR),
 (2,5,200.00,'Nagad','01800000005','0005','paid',NULL,'DEMO-PAYOUT-2026-0002',1,NOW()-INTERVAL 2 DAY,NOW()-INTERVAL 3 DAY);

-- =====================================================================
-- DEMO WALLET LEDGER
-- The balances above reconcile with the ledger entries below.
-- =====================================================================
INSERT INTO wallet_transactions
 (id,user_id,bucket,direction,amount,transaction_type,description,reference_type,reference_id,created_at)
VALUES
 -- Publisher 1: demo deposit and three campaign reserves.
 (1,2,'deposit','credit',10000.00,'admin_credit','Initial demo publisher deposit · DEMO-PUB-001','admin_credit',1,NOW()-INTERVAL 10 DAY),
 (2,2,'deposit','debit',4320.00,'campaign_reserve','Campaign budget reserved: Test a mobile e-commerce checkout flow','campaign',1,NOW()-INTERVAL 8 DAY),
 (3,2,'reserved','credit',4320.00,'campaign_reserve','Campaign budget reserved: Test a mobile e-commerce checkout flow','campaign',1,NOW()-INTERVAL 8 DAY),
 (4,2,'reserved','debit',540.00,'campaign_settlement','Approved task settlement: Test a mobile e-commerce checkout flow','submission',1,NOW()-INTERVAL 5 DAY),
 (5,2,'deposit','debit',540.00,'campaign_reserve','Campaign budget reserved: Share feedback on a public transport survey','campaign',2,NOW()-INTERVAL 1 DAY),
 (6,2,'reserved','credit',540.00,'campaign_reserve','Campaign budget reserved: Share feedback on a public transport survey','campaign',2,NOW()-INTERVAL 1 DAY),
 (7,2,'deposit','debit',345.60,'campaign_reserve','Campaign budget reserved: Verify public information on a local service directory','campaign',3,NOW()-INTERVAL 7 DAY),
 (8,2,'reserved','credit',345.60,'campaign_reserve','Campaign budget reserved: Verify public information on a local service directory','campaign',3,NOW()-INTERVAL 7 DAY),
 (9,2,'reserved','debit',86.40,'submission_refund','Rejected proof refund: Verify public information on a local service directory','submission',3,NOW()-INTERVAL 3 DAY),
 (10,2,'deposit','credit',86.40,'submission_refund','Rejected proof refund: Verify public information on a local service directory','submission',3,NOW()-INTERVAL 3 DAY),

 -- Publisher 2: initial deposit, completed campaign, pending campaign, rejected campaign refund.
 (11,3,'deposit','credit',3000.00,'admin_credit','Initial demo publisher deposit · DEMO-PUB-002','admin_credit',1,NOW()-INTERVAL 12 DAY),
 (12,3,'deposit','debit',648.00,'campaign_reserve','Campaign budget reserved: Check a sample dataset for duplicate records','campaign',4,NOW()-INTERVAL 9 DAY),
 (13,3,'reserved','credit',648.00,'campaign_reserve','Campaign budget reserved: Check a sample dataset for duplicate records','campaign',4,NOW()-INTERVAL 9 DAY),
 (14,3,'reserved','debit',324.00,'campaign_settlement','Approved task settlement: Check a sample dataset for duplicate records','submission',4,NOW()-INTERVAL 6 DAY),
 (15,3,'reserved','debit',324.00,'campaign_settlement','Approved task settlement: Check a sample dataset for duplicate records','submission',5,NOW()-INTERVAL 5 DAY),
 (16,3,'deposit','debit',388.80,'campaign_reserve','Campaign budget reserved: Review short product descriptions for clarity','campaign',5,NOW()-INTERVAL 8 HOUR),
 (17,3,'reserved','credit',388.80,'campaign_reserve','Campaign budget reserved: Review short product descriptions for clarity','campaign',5,NOW()-INTERVAL 8 HOUR),
 (18,3,'deposit','debit',162.00,'campaign_reserve','Campaign budget reserved: Transcribe a short sample audio clip','campaign',6,NOW()-INTERVAL 5 DAY),
 (19,3,'reserved','credit',162.00,'campaign_reserve','Campaign budget reserved: Transcribe a short sample audio clip','campaign',6,NOW()-INTERVAL 5 DAY),
 (20,3,'reserved','debit',162.00,'campaign_refund','Campaign moderation refund: Transcribe a short sample audio clip','campaign',6,NOW()-INTERVAL 3 DAY),
 (21,3,'deposit','credit',162.00,'campaign_refund','Campaign moderation refund: Transcribe a short sample audio clip','campaign',6,NOW()-INTERVAL 3 DAY),

 -- Worker 1: approved rewards and pending withdrawal.
 (22,4,'available','credit',460.00,'worker_reward','Approved reward for: Test a mobile e-commerce checkout flow','submission',1,NOW()-INTERVAL 5 DAY),
 (23,4,'available','credit',276.00,'worker_reward','Approved reward for: Check a sample dataset for duplicate records','submission',4,NOW()-INTERVAL 6 DAY),
 (24,4,'available','debit',300.00,'withdrawal_request','Withdrawal request submitted','withdrawal',1,NOW()-INTERVAL 3 HOUR),
 (25,4,'withdrawal_hold','credit',300.00,'withdrawal_hold','Amount held for withdrawal','withdrawal',1,NOW()-INTERVAL 3 HOUR),

 -- Worker 2: approved reward, paid withdrawal.
 (26,5,'available','credit',276.00,'worker_reward','Approved reward for: Check a sample dataset for duplicate records','submission',5,NOW()-INTERVAL 5 DAY),
 (27,5,'available','debit',200.00,'withdrawal_request','Withdrawal request submitted','withdrawal',2,NOW()-INTERVAL 3 DAY),
 (28,5,'withdrawal_hold','credit',200.00,'withdrawal_hold','Amount held for withdrawal','withdrawal',2,NOW()-INTERVAL 3 DAY),
 (29,5,'withdrawal_hold','debit',200.00,'withdrawal_paid','Withdrawal marked paid by Admin; reference: DEMO-PAYOUT-2026-0002','withdrawal',2,NOW()-INTERVAL 2 DAY),
 (30,3,'deposit','debit',777.60,'campaign_reserve','Campaign budget reserved: Research public library opening information','campaign',7,NOW()-INTERVAL 6 DAY),
 (31,3,'reserved','credit',777.60,'campaign_reserve','Campaign budget reserved: Research public library opening information','campaign',7,NOW()-INTERVAL 6 DAY),
 (32,3,'deposit','debit',729.00,'campaign_reserve','Campaign budget reserved: Try a demo account creation journey','campaign',8,NOW()-INTERVAL 5 DAY),
 (33,3,'reserved','credit',729.00,'campaign_reserve','Campaign budget reserved: Try a demo account creation journey','campaign',8,NOW()-INTERVAL 5 DAY),
 (34,3,'deposit','credit',1000.00,'manual_deposit','Manual deposit approved · Nagad · DEMO-NAGAD-DP-002','deposit_request',2,NOW()-INTERVAL 2 DAY);


-- =====================================================================
-- DEMO MANUAL DEPOSIT REQUESTS
-- One pending request, one approved request (linked to wallet ledger #34),
-- and one rejected request. Demo proof files are intentionally not bundled.
-- =====================================================================
INSERT INTO manual_deposit_requests
 (id,publisher_id,payment_method,amount,sender_reference,reference_fingerprint,proof_storage_name,proof_original_name,proof_mime_type,proof_file_size,status,admin_note,reviewed_by,reviewed_at,wallet_transaction_id,created_at)
VALUES
 (1,2,'bKash',1500.00,'DEMO-BKASH-DP-001',SHA2('bkash|DEMO-BKASH-DP-001',256),NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NOW()-INTERVAL 2 HOUR),
 (2,3,'Nagad',1000.00,'DEMO-NAGAD-DP-002',SHA2('nagad|DEMO-NAGAD-DP-002',256),NULL,NULL,NULL,NULL,'approved','Demo deposit approved after offline verification.',1,NOW()-INTERVAL 2 DAY,34,NOW()-INTERVAL 3 DAY),
 (3,3,'Bank transfer',500.00,'DEMO-BANK-DP-003',SHA2('bank transfer|DEMO-BANK-DP-003',256),NULL,NULL,NULL,NULL,'rejected','Demo rejection: bank reference could not be confirmed.',1,NOW()-INTERVAL 1 DAY,NULL,NOW()-INTERVAL 2 DAY);

INSERT INTO platform_transactions (id,amount,transaction_type,description,reference_type,reference_id,created_at) VALUES
 (1,80.00,'commission','Publisher + worker commission for Test a mobile e-commerce checkout flow','submission',1,NOW()-INTERVAL 5 DAY),
 (2,48.00,'commission','Publisher + worker commission for Check a sample dataset for duplicate records','submission',4,NOW()-INTERVAL 6 DAY),
 (3,48.00,'commission','Publisher + worker commission for Check a sample dataset for duplicate records','submission',5,NOW()-INTERVAL 5 DAY);

-- =====================================================================
-- DEMO NOTIFICATIONS
-- =====================================================================
INSERT INTO notifications (id,user_id,title,body,type,link,is_read,created_at) VALUES
 (1,1,'Campaign awaiting review','Test a mobile e-commerce checkout flow is ready for moderation.','campaign','index.php?route=dashboard&tab=moderation&as=admin',0,NOW()-INTERVAL 8 DAY),
 (2,1,'New task proof submitted','A worker submitted proof for Test a mobile e-commerce checkout flow.','submission','index.php?route=dashboard&tab=submissions&as=admin',0,NOW()-INTERVAL 1 DAY),
 (3,1,'Withdrawal request received','Nusrat Jahan requested ৳300.00 via bKash.','wallet','index.php?route=dashboard&tab=finance&as=admin',0,NOW()-INTERVAL 3 HOUR),
 (4,1,'New support request','Cannot find my submission feedback · from Demo Visitor.','support','index.php?route=dashboard&tab=support&as=admin',0,NOW()-INTERVAL 2 HOUR),
 (5,2,'Campaign approved','Your campaign “Test a mobile e-commerce checkout flow” is now visible to workers.','campaign','index.php?route=dashboard&tab=campaigns&as=publisher',1,NOW()-INTERVAL 6 DAY),
 (6,2,'A worker claimed your campaign','Sabbir Ahmed claimed a slot in your campaign.','claim','index.php?route=dashboard&tab=campaign_view&id=1&as=publisher',0,NOW()-INTERVAL 1 DAY),
 (7,2,'Submission rejected','A rejected slot has been returned to your deposit balance.','submission','index.php?route=dashboard&tab=campaign_view&id=3&as=publisher',0,NOW()-INTERVAL 3 DAY),
 (8,2,'Campaign awaiting review','Your transport survey campaign is waiting for Admin review.','campaign','index.php?route=dashboard&tab=campaigns&as=publisher',0,NOW()-INTERVAL 1 DAY),
 (9,3,'Campaign completed','Both worker submissions were approved for your duplicate-record task.','campaign','index.php?route=dashboard&tab=campaign_view&id=4&as=publisher',1,NOW()-INTERVAL 4 DAY),
 (10,3,'Campaign not approved','Your transcription campaign needs a clearer acceptance checklist.','campaign','index.php?route=dashboard&tab=campaigns&as=publisher',0,NOW()-INTERVAL 3 DAY),
 (11,4,'Proof approved — reward credited','Your net reward of ৳460.00 has been added to your wallet.','wallet','index.php?route=dashboard&tab=wallet',1,NOW()-INTERVAL 5 DAY),
 (12,4,'Withdrawal request submitted','Your ৳300.00 bKash withdrawal request is waiting for review.','wallet','index.php?route=dashboard&tab=wallet',0,NOW()-INTERVAL 3 HOUR),
 (13,5,'Proof approved — reward credited','Your net reward of ৳276.00 has been added to your wallet.','wallet','index.php?route=dashboard&tab=wallet',1,NOW()-INTERVAL 5 DAY),
 (14,5,'Withdrawal marked paid','Your withdrawal was marked paid. Demo payment reference: DEMO-PAYOUT-2026-0002.','wallet','index.php?route=dashboard&tab=wallet',1,NOW()-INTERVAL 2 DAY),
 (15,6,'Task slot claimed','You have claimed a slot in Test a mobile e-commerce checkout flow.','claim','index.php?route=task&id=1',0,NOW()-INTERVAL 5 HOUR),
 (16,7,'Submission needs attention','Please include the exact entry IDs and a screenshot showing the broken link.','submission','index.php?route=dashboard&tab=submissions',0,NOW()-INTERVAL 3 DAY),
 (17,3,'Campaign approved','Your public library research campaign is now visible to workers.','campaign','index.php?route=dashboard&tab=campaigns&as=publisher',1,NOW()-INTERVAL 4 DAY),
 (18,3,'Campaign approved','Your demo account creation task is now visible to workers.','campaign','index.php?route=dashboard&tab=campaigns&as=publisher',1,NOW()-INTERVAL 3 DAY),
 (19,1,'Publisher deposit request received','Ayesha Rahman submitted a demo deposit request of ৳1,500.00 via bKash.','wallet','index.php?route=dashboard&tab=finance&as=admin&deposit_filter=pending',0,NOW()-INTERVAL 2 HOUR),
 (20,2,'Deposit request submitted','Your demo bKash deposit request is waiting for verification.','wallet','index.php?route=dashboard&tab=wallet&as=publisher',0,NOW()-INTERVAL 2 HOUR),
 (21,3,'Deposit approved','Your demo Nagad deposit of ৳1,000.00 has been credited to your wallet.','wallet','index.php?route=dashboard&tab=wallet&as=publisher',1,NOW()-INTERVAL 2 DAY);

-- =====================================================================
-- DEMO AUDIT LOGS
-- =====================================================================
INSERT INTO audit_logs (id,actor_id,action,entity_type,entity_id,meta_json,ip_address,created_at) VALUES
 (1,2,'campaign.created','campaign',1,'{"demo":true,"budget":4320.00,"slots":8}','127.0.0.1',NOW()-INTERVAL 8 DAY),
 (2,1,'campaign.approve','campaign',1,'{"demo":true,"decision":"approve"}','127.0.0.1',NOW()-INTERVAL 6 DAY),
 (3,4,'campaign.claimed','claim',1,'{"demo":true,"campaign_id":1}','127.0.0.1',NOW()-INTERVAL 6 DAY),
 (4,5,'submission.created','submission',2,'{"demo":true,"claim_id":2}','127.0.0.1',NOW()-INTERVAL 1 DAY),
 (5,2,'submission.reject','submission',3,'{"demo":true,"feedback":"Please include exact entry IDs and screenshot."}','127.0.0.1',NOW()-INTERVAL 3 DAY),
 (6,3,'campaign.created','campaign',4,'{"demo":true,"budget":648.00,"slots":2}','127.0.0.1',NOW()-INTERVAL 9 DAY),
 (7,1,'withdrawal.paid','withdrawal',2,'{"demo":true,"reference":"DEMO-PAYOUT-2026-0002"}','127.0.0.1',NOW()-INTERVAL 2 DAY),
 (8,1,'wallet.admin_credit','wallet',2,'{"demo":true,"amount":10000.00,"reference":"DEMO-PUB-001"}','127.0.0.1',NOW()-INTERVAL 10 DAY),
 (9,3,'campaign.created','campaign',7,'{"demo":true,"budget":777.60,"slots":8}','127.0.0.1',NOW()-INTERVAL 6 DAY),
 (10,3,'campaign.created','campaign',8,'{"demo":true,"budget":729.00,"slots":15}','127.0.0.1',NOW()-INTERVAL 5 DAY),
 (11,2,'deposit.requested','deposit_request',1,'{"demo":true,"amount":1500.00,"method":"bKash"}','127.0.0.1',NOW()-INTERVAL 2 HOUR),
 (12,1,'deposit.approved','deposit_request',2,'{"demo":true,"amount":1000.00,"method":"Nagad","wallet_transaction_id":34}','127.0.0.1',NOW()-INTERVAL 2 DAY),
 (13,1,'deposit.rejected','deposit_request',3,'{"demo":true,"amount":500.00,"method":"Bank transfer"}','127.0.0.1',NOW()-INTERVAL 1 DAY);

-- =====================================================================
-- DEMO SUPPORT INBOX
-- =====================================================================
INSERT INTO support_messages
 (id,user_id,sender_name,sender_email,subject,message,status,admin_note,handled_by,handled_at,created_at)
VALUES
 (1,NULL,'Demo Visitor','visitor@example.com','Cannot find my submission feedback','I submitted a demo task yesterday but I am not sure where the reviewer feedback will appear. Could you guide me to the correct dashboard section?','new',NULL,NULL,NULL,NOW()-INTERVAL 2 HOUR),
 (2,5,'Sabbir Ahmed','worker2@taskhaat.local','Question about withdrawal timing','My demo withdrawal is marked paid in the history. I would like to understand where the payment reference is displayed and how I can verify it.','in_progress','Demo ticket: explain that payout records are manually entered and no real payment is sent in this sample database.',1,NULL,NOW()-INTERVAL 2 DAY),
 (3,3,'Tanvir Hasan','publisher2@taskhaat.local','How does campaign budgeting work?','Please explain how the publisher fee is added to the total reward pool and how the reserved balance changes when submissions are approved.','resolved','Demo response: the publisher fee is added to the reward pool; approved submissions settle the gross reward plus publisher fee from reserved funds.',1,NOW()-INTERVAL 4 DAY,NOW()-INTERVAL 5 DAY);

-- =====================================================================
-- VERIFICATION
-- Expected: 19 tables, 7 users, 8 campaigns, 7 claims, 5 submissions, 3 manual deposit requests.
-- Wallet balances are pre-reconciled with the demonstration ledger.
-- =====================================================================
SELECT COUNT(*) AS taskhaat_table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='taskhaat' AND TABLE_TYPE='BASE TABLE';
SELECT COUNT(*) AS manual_deposit_request_count FROM manual_deposit_requests;
SELECT 'deposit_wallet_credit_mismatches' AS check_name,COUNT(*) AS issue_count FROM manual_deposit_requests dr LEFT JOIN wallet_transactions wt ON wt.id=dr.wallet_transaction_id WHERE (dr.status='approved' AND (wt.id IS NULL OR wt.user_id<>dr.publisher_id OR wt.reference_type<>'deposit_request' OR wt.reference_id<>dr.id OR wt.amount<>dr.amount OR wt.direction<>'credit' OR wt.bucket<>'deposit')) OR (dr.status<>'approved' AND dr.wallet_transaction_id IS NOT NULL);

SELECT user_type,COUNT(*) AS account_count FROM users GROUP BY user_type ORDER BY user_type;
SELECT COUNT(*) AS missing_wallets FROM users u LEFT JOIN wallets w ON w.user_id=u.id WHERE w.id IS NULL;
SELECT 'wallet_ledger_balance_mismatches' AS check_name, COUNT(*) AS issue_count
FROM wallets w
CROSS JOIN (
  SELECT 'available' AS bucket UNION ALL SELECT 'deposit' UNION ALL
  SELECT 'reserved' UNION ALL SELECT 'withdrawal_hold'
) b
LEFT JOIN (
  SELECT user_id,bucket,SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END) AS ledger_balance
  FROM wallet_transactions GROUP BY user_id,bucket
) l ON l.user_id=w.user_id AND l.bucket=b.bucket
WHERE ABS(COALESCE(l.ledger_balance,0)-CASE
  WHEN b.bucket='available' THEN w.available_balance
  WHEN b.bucket='deposit' THEN w.deposit_balance
  WHEN b.bucket='reserved' THEN w.reserved_balance
  WHEN b.bucket='withdrawal_hold' THEN w.withdrawal_hold_balance
  ELSE 0 END)>0.01;
SELECT 'publisher_reserve_mismatches' AS check_name,COUNT(*) AS issue_count
FROM wallets w JOIN users u ON u.id=w.user_id
LEFT JOIN (SELECT publisher_id,SUM(budget_remaining) AS reserve_total FROM campaigns GROUP BY publisher_id) c ON c.publisher_id=u.id
WHERE u.user_type IN ('publisher','both') AND ABS(w.reserved_balance-COALESCE(c.reserve_total,0))>0.01;
SELECT 'campaign_claim_count_mismatches' AS check_name,COUNT(*) AS issue_count
FROM campaigns c LEFT JOIN (SELECT campaign_id,COUNT(*) AS claim_count FROM claims GROUP BY campaign_id) cl ON cl.campaign_id=c.id
WHERE c.claimed_slots<>COALESCE(cl.claim_count,0);
SELECT 'withdrawal_hold_mismatches' AS check_name,COUNT(*) AS issue_count
FROM wallets w LEFT JOIN (SELECT user_id,SUM(amount) AS hold_total FROM withdrawals WHERE status IN ('pending','approved','processing') GROUP BY user_id) wd ON wd.user_id=w.user_id
WHERE ABS(w.withdrawal_hold_balance-COALESCE(wd.hold_total,0))>0.01;
SELECT status,COUNT(*) AS campaign_count FROM campaigns GROUP BY status ORDER BY status;
SELECT status,COUNT(*) AS submission_count FROM submissions GROUP BY status ORDER BY status;

SELECT u.email,w.available_balance,w.deposit_balance,w.reserved_balance,w.withdrawal_hold_balance
FROM wallets w JOIN users u ON u.id=w.user_id ORDER BY u.id;

SELECT 'DEMO DATA IMPORT COMPLETE' AS import_status,
       (SELECT COUNT(*) FROM users) AS users,
       (SELECT COUNT(*) FROM campaigns) AS campaigns,
       (SELECT COUNT(*) FROM claims) AS claims,
       (SELECT COUNT(*) FROM submissions) AS submissions,
       (SELECT COUNT(*) FROM notifications) AS notifications,
       (SELECT COUNT(*) FROM support_messages) AS support_tickets,
       (SELECT COUNT(*) FROM manual_deposit_requests) AS manual_deposit_requests,
       (SELECT COUNT(*) FROM login_attempts) AS login_attempt_rows;
