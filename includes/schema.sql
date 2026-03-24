-- Schema additions for Trikut Restaurant (includes gallery table)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ...existing schema definitions...

DROP TABLE IF EXISTS gallery;
CREATE TABLE gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  img VARCHAR(1000) NOT NULL,
  caption VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;