-- Final updated schema + migrated data for Trikut Restaurant
-- Run in phpMyAdmin or mysql CLI to recreate / update the database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop old tables if present (safe for re-import)
DROP TABLE IF EXISTS `gallery`;
DROP TABLE IF EXISTS `menu`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `admins`;

-- Admins
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- preserve existing admin (use existing hash)
INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'trikut', '$2y$10$fc2mxeIdiAn.xPfEZnrsa.76osgD4F4nxCPmpxFEX4MNDoFjtXKxS', NOW());

ALTER TABLE `admins` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Bookings (normalized)
CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) NULL,
  `date` DATETIME NOT NULL,
  `size` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`phone`),
  INDEX (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migrate existing bookings from old dump (mapping booking_time -> date, guests -> size)
INSERT INTO `bookings` (`id`, `name`, `phone`, `date`, `size`)
VALUES
(1, 'santosh', '934974', '2026-01-10 04:06:00', 2),
(2, 'santosh', '7389742', '2026-01-11 00:11:00', 5),
(3, 'dgs', '634634', '2026-01-01 23:20:00', 2),
(4, 'sdgsdhng', '237325325327', '2026-01-16 12:46:00', 2);

ALTER TABLE `bookings` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- Gallery (new structure)
CREATE TABLE `gallery` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `img` VARCHAR(1000) NOT NULL,
  `caption` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If older dump had gallery.image rows, migrate them here manually after import.
ALTER TABLE `gallery` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Menu (replace old menu_items)
CREATE TABLE `menu` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `img` VARCHAR(1000) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`active`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `menu` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- If you had rows in old `menu_items`, migrate them into `menu`:
-- Example migration SQL (uncomment and adjust if needed):
-- INSERT INTO `menu` (id, name, description, price, img, created_at)
-- SELECT id, name, description, CAST(price AS DECIMAL(10,2)), image, NOW() FROM `menu_items`;

-- Orders (normalized)
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(40) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `items` TEXT NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`phone`),
  INDEX (`created_at`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migrate existing orders (mapping customer_name/customer_phone -> name/phone; set status='new')
INSERT INTO `orders` (`id`, `name`, `phone`, `items`, `total`, `created_at`, `status`)
SELECT `id`, `customer_name`, `customer_phone`, `items`, `total`, `created_at`, 'new' FROM (
  -- inline old orders data from dump
  SELECT 1 AS id, 'soag gha' AS customer_name, '235787572375' AS customer_phone,
         'Paneer Wrap x1 = ₹160, Cappuccino x3 = ₹270' AS items, 430 AS total, '2026-01-10 03:59:44' AS created_at
) AS old_orders;

ALTER TABLE `orders` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
