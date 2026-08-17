-- =====================================================================
-- DevStore — newsletter_fix.sql
-- Run this in phpMyAdmin if newsletter subscribe is not working.
-- Also fixes wallet_transactions type column for referral support.
-- Safe to re-run (uses IF NOT EXISTS / MODIFY).
-- =====================================================================

-- 1. Rate-limit table (required for newsletter subscribe)
CREATE TABLE IF NOT EXISTS `rate_limit_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(128) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`),
  KEY `attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Newsletter subscribers (in case migration_v3.sql was not run)
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `name` VARCHAR(100) DEFAULT NULL,
  `subscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
  `token` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Fix wallet_transactions type column to allow referral_commission type
ALTER TABLE `wallet_transactions`
  MODIFY COLUMN `type` ENUM('credit','debit','referral_commission','refund','withdrawal') NOT NULL DEFAULT 'credit';

-- 4. Add reference_id column for referral tracking (if not exists)
ALTER TABLE `wallet_transactions`
  ADD COLUMN IF NOT EXISTS `reference_id` INT(11) DEFAULT NULL COMMENT 'buyer user_id for referral_commission type' AFTER `ref_id`;

-- 5. Make sure referral columns exist on users
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(12) DEFAULT NULL AFTER `payout_note`,
  ADD COLUMN IF NOT EXISTS `referred_by` INT(11) DEFAULT NULL AFTER `referral_code`,
  ADD COLUMN IF NOT EXISTS `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `referred_by`;

-- Unique index on referral_code
ALTER TABLE `users` ADD UNIQUE KEY IF NOT EXISTS `uk_referral_code` (`referral_code`);

-- 6. referral_commission_pct setting
INSERT IGNORE INTO `settings` (key_name, key_value) VALUES ('referral_commission_pct', '5');
