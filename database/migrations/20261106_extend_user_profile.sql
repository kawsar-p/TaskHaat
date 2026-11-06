-- TaskHaat reference sign-up profile fields (run once on an existing installation).
-- This migration only adds nullable/defaulted columns; it does not delete or rewrite user rows.
ALTER TABLE users
  ADD COLUMN country VARCHAR(100) NOT NULL DEFAULT 'Bangladesh' AFTER phone,
  ADD COLUMN district VARCHAR(100) NULL AFTER country,
  ADD COLUMN city VARCHAR(120) NULL AFTER district,
  ADD COLUMN occupation VARCHAR(120) NULL AFTER city,
  ADD COLUMN skills TEXT NULL AFTER occupation,
  ADD COLUMN organization VARCHAR(180) NULL AFTER skills,
  ADD COLUMN website VARCHAR(255) NULL AFTER organization,
  ADD COLUMN referral_code VARCHAR(50) NULL AFTER website,
  ADD COLUMN terms_accepted_at DATETIME NULL AFTER referral_code;
