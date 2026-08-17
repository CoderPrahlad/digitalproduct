-- =====================================================================
-- DevStore migration v3 — run this once in phpMyAdmin / mysql CLI
-- against your `digital` database AFTER migration_v2.sql
-- Safe to re-run (uses IF NOT EXISTS / IF column exists guards).
--
-- Features covered:
--   6. Sell link navbar         -> no DB change
--   7. Order cancel/refund      -> already done in v2 (wallet_balance, refund_reason)
--   8. Newsletter subscribe box -> newsletter_subscribers table
--   9. Affiliate/Referral system-> referral_codes, referral_commissions tables
--                                  + users.referral_code, users.referred_by
--  10. Google Login             -> users.google_id, users.avatar columns
-- =====================================================================

-- ---- Newsletter subscribers ----
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `name` VARCHAR(100) DEFAULT NULL,
  `subscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
  `token` VARCHAR(64) NOT NULL COMMENT 'unsubscribe token',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Users: referral code + who referred them + google login ----
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(12) DEFAULT NULL COMMENT 'unique code for this user to share' AFTER `payout_note`,
  ADD COLUMN IF NOT EXISTS `referred_by` INT(11) DEFAULT NULL COMMENT 'user_id of referrer' AFTER `referral_code`,
  ADD COLUMN IF NOT EXISTS `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'store-credit / referral balance' AFTER `referred_by`,
  ADD COLUMN IF NOT EXISTS `google_id` VARCHAR(30) DEFAULT NULL AFTER `wallet_balance`,
  ADD COLUMN IF NOT EXISTS `avatar` VARCHAR(255) DEFAULT NULL AFTER `google_id`;

-- unique index on referral_code (safe to re-run)
ALTER TABLE `users` ADD UNIQUE KEY IF NOT EXISTS `uk_referral_code` (`referral_code`);
-- unique index on google_id
ALTER TABLE `users` ADD UNIQUE KEY IF NOT EXISTS `uk_google_id` (`google_id`);

-- ---- Referral commissions log ----
CREATE TABLE IF NOT EXISTS `referral_commissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `referrer_id` INT(11) NOT NULL COMMENT 'user who gets the commission',
  `buyer_id` INT(11) NOT NULL,
  `order_amount` DECIMAL(10,2) NOT NULL,
  `commission_pct` DECIMAL(5,2) NOT NULL,
  `commission_amount` DECIMAL(10,2) NOT NULL,
  `credited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_referral` (`order_id`),
  KEY `referrer_id` (`referrer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- wallet_transactions: make sure it exists (may already be there from v2) ----
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `type` ENUM('credit','debit') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ref_id` VARCHAR(50) DEFAULT NULL,
  `balance_after` DECIMAL(10,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Add referral_commission_pct to settings (default 5%) ----
INSERT IGNORE INTO `settings` (key_name, key_value)
VALUES ('referral_commission_pct', '5');

-- =====================================================================
-- NOTE: Google OAuth also needs a Google Client ID set in Admin -> Settings.
-- Add it there after running this SQL. The code reads it as 'google_client_id'.
-- =====================================================================
