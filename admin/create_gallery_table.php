
<?php
require __DIR__ . '/../includes/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  img VARCHAR(1000) NOT NULL,
  caption VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Gallery table created or already exists.\n";
} catch (PDOException $e) {
    echo "Error creating gallery table: " . $e->getMessage() . "\n";
}