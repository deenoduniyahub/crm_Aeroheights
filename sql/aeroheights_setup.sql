-- Aeroheights CRM: changes on top of the AST schema (run on an EMPTY database built from sql/schema.sql's source).
-- Kept for reference; sql/schema.sql already includes everything below.

DROP TABLE IF EXISTS `nusuk_payments`;
DROP TABLE IF EXISTS `nusuk_records`;

-- Sign in with username OR email; password-reset codes go to this address.
ALTER TABLE `users` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `username`, ADD UNIQUE KEY `uq_users_email` (`email`);

-- Emailed 6-digit codes for "Forgot password" / "Change password" (stored hashed, 10-minute expiry).
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `request_ip` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pr_user` (`user_id`, `used_at`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
