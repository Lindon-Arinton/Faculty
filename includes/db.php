<?php
require_once __DIR__ . '/../config.php';

/** Connections never seed, create accounts, create databases or alter schemas. */
function db(): PDO
{
    static $pdo = null;
    static $maintenanceLock = null;
    if ($maintenanceLock === null) {
        $maintenanceLock = fopen(__DIR__ . '/../var/cleanup.lock', 'r');
        if (!$maintenanceLock || !flock($maintenanceLock, LOCK_SH | LOCK_NB)) {
            http_response_code(503);
            exit('Database maintenance in progress. Please retry shortly.');
        }
    }
    return $pdo ??= new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
