<?php
/**
 * Rebuilds the sample university records (users are kept).
 *
 *   php database/seed.php
 *
 * The app also runs this automatically the first time it finds no records.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line: php database/seed.php');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/seeder.php';

set_time_limit(0);
ini_set('memory_limit', '1G');

$start = microtime(true);
echo "Seeding " . DB_NAME . " (snapshot as of " . SEED_AS_OF . ")...\n";
$stats = seed_database(db(), fn(string $m) => print("  $m\n"));
foreach ($stats as $k => $v) {
    printf("  %-22s %s\n", str_replace('_', ' ', $k), number_format($v));
}
printf("Done in %.1fs\n", microtime(true) - $start);
