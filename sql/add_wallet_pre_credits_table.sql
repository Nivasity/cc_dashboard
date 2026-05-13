--
-- Add wallet_pre_credits table for manual wallet pre-credit records
-- This migration creates the audit table used to track manual wallet credits
-- while waiting for delayed Paystack confirmation or dispute resolution.
--

CREATE TABLE IF NOT EXISTS `wallet_pre_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_number` varchar(30) NOT NULL,
  `provider_customer_code` varchar(100) DEFAULT NULL,
  `provider_reference` varchar(100) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `confirmed_amount` int(11) DEFAULT NULL,
  `receipt_path` varchar(255) NOT NULL,
  `receipt_name` varchar(255) NOT NULL,
  `receipt_mime_type` varchar(100) DEFAULT NULL,
  `receipt_size` int(11) NOT NULL DEFAULT 0,
  `created_by_admin_id` int(11) NOT NULL,
  `funding_transaction_id` int(11) DEFAULT NULL,
  `ledger_entry_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `confirmed_provider_transaction_id` varchar(100) DEFAULT NULL,
  `confirmation_source` varchar(30) DEFAULT NULL,
  `status` enum('pending_confirmation','confirmed','amount_disputed') NOT NULL DEFAULT 'pending_confirmation',
  `admin_note` text DEFAULT NULL,
  `reconciliation_note` text DEFAULT NULL,
  `confirmed_payload` longtext DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_pre_credits_reference` (`provider_reference`),
  KEY `idx_wallet_pre_credits_status_created` (`status`, `created_at`),
  KEY `idx_wallet_pre_credits_wallet_created` (`wallet_id`, `created_at`),
  KEY `idx_wallet_pre_credits_user_created` (`user_id`, `created_at`),
  KEY `idx_wallet_pre_credits_account_number` (`account_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;