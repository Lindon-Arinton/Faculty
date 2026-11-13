<?php
require_once __DIR__ . '/../config.php';

/**
 * Shared PDO connection. Creates the database, tables and default
 * account on first use so the app works right after copying it into htdocs.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // 1049 = unknown database: create it, then connect again.
        if ((int) $e->getCode() !== 1049 && !str_contains($e->getMessage(), '1049')) {
            throw $e;
        }
        $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
    }

    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(120) NOT NULL,
            email         VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role          VARCHAR(120) NOT NULL DEFAULT "Faculty",
            last_login_at DATETIME NULL,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            DEFAULT_USER_NAME,
            DEFAULT_USER_EMAIL,
            password_hash(DEFAULT_USER_PASS, PASSWORD_DEFAULT),
            DEFAULT_USER_ROLE,
        ]);
    }

    ensure_records($pdo);
}

/**
 * Builds the sample university records the first time the app runs
 * (takes a few seconds). Rebuild later with: php database/seed.php
 */
function ensure_records(PDO $pdo): void
{
    $ready = (bool) $pdo->query("SHOW TABLES LIKE 'meta'")->fetchColumn()
        && (int) $pdo->query('SELECT COUNT(*) FROM meta')->fetchColumn() > 0;
    if ($ready) {
        return;
    }
    // Only one request seeds; others wait for it.
    $pdo->query("SELECT GET_LOCK('veritas_seed', 120)")->fetchColumn();
    try {
        $ready = (bool) $pdo->query("SHOW TABLES LIKE 'meta'")->fetchColumn()
            && (int) $pdo->query('SELECT COUNT(*) FROM meta')->fetchColumn() > 0;
        if (!$ready) {
            require_once __DIR__ . '/seeder.php';
            set_time_limit(300);
            ini_set('memory_limit', '1G');
            seed_database($pdo);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('veritas_seed')");
    }
}
