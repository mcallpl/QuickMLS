<?php
/**
 * QuickMLS — One-time Database Setup
 * Run: php setup_db.php
 * Creates the quickmls database, users table, shares table, and admin user.
 */

require_once __DIR__ . '/config.php';

// Connect without database (to create it)
$db = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($db->connect_errno) {
    die("Connection failed: " . $db->connect_error . "\n");
}

// Create database
$dbName = $db->real_escape_string(DB_NAME);
$db->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database '$dbName' ready.\n";

$db->select_db(DB_NAME);

// Create users table
$db->query("
    CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','user') NOT NULL DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");
echo "Table 'users' ready.\n";

// Create shares table
$db->query("
    CREATE TABLE IF NOT EXISTS shares (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token VARCHAR(64) NOT NULL UNIQUE,
        address TEXT NOT NULL,
        hero_listing_key VARCHAR(50) DEFAULT NULL,
        radius_miles DECIMAL(6,4) NOT NULL DEFAULT 0.1250,
        created_by INT UNSIGNED,
        client_phone VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB
");
echo "Table 'shares' ready.\n";

// Insert admin user (mcallpl / amazing)
$hash = password_hash('amazing', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')
                       ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin'");
$stmt->bind_param('ss', $username, $hash);
$username = 'mcallpl';
$stmt->execute();
$stmt->close();
echo "Admin user 'mcallpl' ready.\n";

$db->close();
echo "\nSetup complete!\n";
