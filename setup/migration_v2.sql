-- =====================================================================
-- DevStore migration v2 — run this once in phpMyAdmin / mysql CLI
-- against your `digital` database before using the new features below.
-- Safe to re-run except the enum ALTER — if it errors with something
-- like "Duplicate column" it just means it's already applied, ignore it.
--
-- Covers:
--   1. Root .htaccess hardening        -> no DB change needed
--   2. Custom 404 page                 -> no DB change needed
--   3. Contact form spam protection    -> rate_limit_attempts table
--   4. Order confirmation email fix    -> orders.email_sent
--   5. Product view count              -> products.view_count
--   (bonus) Order cancel/refund        -> orders.status/refund_reason,
--                                          users.wallet_balance
-- =====================================================================

-- ---- Orders: cancel/refund support + email delivery tracking ----
ALTER TABLE `orders`
  MODIFY `status` ENUM('pending','paid','rejected','delivered','refunded') DEFAULT 'pending';

ALTER TABLE `orders`
  ADD COLUMN `refund_reason` VARCHAR(255) DEFAULT NULL AFTER `status`,
  ADD COLUMN `email_sent` TINYINT(1) DEFAULT 0 AFTER `refund_reason`;

-- ---- Products: view counter ----
ALTER TABLE `products`
  ADD COLUMN `view_count` INT(11) NOT NULL DEFAULT 0 AFTER `sort_order`;

-- ---- Users: wallet balance (used for store-credit refunds) ----
ALTER TABLE `users`
  ADD COLUMN `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `payout_note`;

-- ---- Contact form / generic rate limiting (same shape as login_attempts) ----
CREATE TABLE IF NOT EXISTS `rate_limit_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(190) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
