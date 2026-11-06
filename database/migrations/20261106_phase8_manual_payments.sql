-- TaskHaat Phase 8 migration: manual publisher deposits and payment references.
-- Run ONCE against an existing Phase 7/Phase 6 database. Do not run after importing
-- the refreshed full database/schema.sql because the columns/table already exist.
USE taskhaat;

ALTER TABLE withdrawals
  ADD COLUMN payment_reference VARCHAR(120) NULL AFTER admin_note;

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

INSERT INTO settings(setting_key,setting_value) VALUES
 ('min_deposit','100.00'),('max_deposit','1000000.00'),
 ('manual_bkash_account_name',''),('manual_bkash_number',''),
 ('manual_nagad_account_name',''),('manual_nagad_number',''),
 ('manual_rocket_account_name',''),('manual_rocket_number',''),
 ('manual_bank_name',''),('manual_bank_account_name',''),('manual_bank_account_number',''),('manual_bank_branch',''),
 ('manual_deposit_instructions','')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

-- Existing paid withdrawal references used to be stored in admin_note.
-- Move only records with a known demo/legacy paid state where the note looks like a transfer reference.
UPDATE withdrawals
SET payment_reference=admin_note,admin_note=NULL
WHERE status='paid' AND payment_reference IS NULL AND admin_note REGEXP '^[A-Za-z0-9._/-]{4,120}$';
