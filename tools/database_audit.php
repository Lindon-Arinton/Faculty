<?php
/** Read-only audit: deliberately bypasses db(), schema creation and automatic seeding. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config.php';
$database = $argv[1] ?? DB_NAME;
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $database)) {
    throw new RuntimeException('Invalid database identifier');
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $database . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$quote = fn(string $s): string => '`' . str_replace('`', '``', $s) . '`';
$deletions = ['applicant_requirements', 'applicants', 'faculty_rating_history', 'evaluation_responses',
    'risk_snapshots', 'interventions', 'enrollments', 'meetings', 'sections', 'students',
    'curriculum', 'courses', 'faculty', 'rooms', 'programs', 'colleges', 'terms'];
$demoKeys = ['as_of', 'intake_label', 'prev_intake_applications', 'prev_term_enrollment', 'enrolled_active', 'seeded_at'];
$report = ['database' => $database, 'captured_at' => date(DATE_ATOM), 'mode' => 'READ ONLY / DRY RUN',
    'tables' => [], 'foreign_keys' => [], 'blockers' => [], 'proposed_delete_order' => $deletions,
    'proposed_meta_keys_to_delete' => $demoKeys];
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction();
$tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
foreach ($tables as [$name, $type]) {
    if ($type !== 'BASE TABLE') {
        $report['blockers'][] = 'Review view: ' . $name;
        continue;
    }
    $engine = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $engine->execute([$database, $name]);
    $engine = $engine->fetchColumn();
    $schema = $pdo->query('SHOW CREATE TABLE ' . $quote($name))->fetch(PDO::FETCH_NUM)[1];
    $hashes = [];
    foreach ($pdo->query('SELECT * FROM ' . $quote($name)) as $row) {
        // Hash only; do not print names, email addresses, passwords or student records.
        $hashes[] = hash('sha256', serialize($row));
    }
    sort($hashes, SORT_STRING);
    $entry = ['rows' => count($hashes), 'engine' => $engine, 'data_sha256' => hash('sha256', implode('', $hashes)),
        'schema_sha256' => hash('sha256', $schema), 'proposed_delete' => 0, 'action' => 'PRESERVE'];
    if (in_array($name, $deletions, true)) {
        $entry['action'] = 'DELETE records; retain structure';
        $entry['proposed_delete'] = $entry['rows'];
    } elseif ($name === 'meta') {
        $keys = $pdo->query('SELECT k FROM meta ORDER BY k')->fetchAll(PDO::FETCH_COLUMN);
        $entry['keys'] = $keys;
        $entry['proposed_delete'] = count(array_intersect($keys, $demoKeys));
        $entry['action'] = 'DELETE known demo metadata only; preserve other settings; add clean-state marker';
    } elseif ($name !== 'users') {
        $report['blockers'][] = 'Unclassified table must be reviewed: ' . $name;
    }
    if ($engine !== 'InnoDB') {
        $report['blockers'][] = 'Nontransactional table: ' . $name;
    }
    $report['tables'][$name] = $entry;
}
$fks = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
$fks->execute([$database]);
foreach ($fks as $fk) {
    $child = $fk['TABLE_NAME']; $parent = $fk['REFERENCED_TABLE_NAME'];
    $sql = 'SELECT COUNT(*) FROM ' . $quote($child) . ' c LEFT JOIN ' . $quote($fk['REFERENCED_TABLE_SCHEMA']) . '.' . $quote($parent) . ' p ON c.' . $quote($fk['COLUMN_NAME']) . '=p.' . $quote($fk['REFERENCED_COLUMN_NAME']) . ' WHERE c.' . $quote($fk['COLUMN_NAME']) . ' IS NOT NULL AND p.' . $quote($fk['REFERENCED_COLUMN_NAME']) . ' IS NULL';
    $fk['orphan_rows'] = (int) $pdo->query($sql)->fetchColumn();
    $report['foreign_keys'][] = $fk;
    if ($fk['orphan_rows']) {
        $report['blockers'][] = 'Broken foreign key: ' . $fk['CONSTRAINT_NAME'];
    }
    if ($fk['REFERENCED_TABLE_SCHEMA'] !== $database || ($child === 'users' && in_array($parent, $deletions, true))) {
        $report['blockers'][] = 'Protected or external account relationship requires review: ' . $fk['CONSTRAINT_NAME'];
    }
}
$external = $pdo->prepare('SELECT TABLE_SCHEMA, TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=? AND TABLE_SCHEMA<>?');
$external->execute([$database, $database]);
foreach ($external as $fk) {
    $report['blockers'][] = 'External incoming relationship: ' . $fk['TABLE_SCHEMA'] . '.' . $fk['TABLE_NAME'];
}
$report['account_checks'] = ['count' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'empty_credentials' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email='' OR password_hash='' OR role=''")->fetchColumn(),
    'password_hashes_recognized' => true, 'role_counts' => $pdo->query('SELECT role, COUNT(*) accounts FROM users GROUP BY role')->fetchAll(),
    'user_rows_will_be_modified' => false, 'login_test' => 'Not performed: authenticating updates last_login_at and may rehash passwords.'];
foreach ($pdo->query('SELECT password_hash FROM users') as $row) {
    if (password_get_info($row['password_hash'])['algoName'] === 'unknown') {
        $report['account_checks']['password_hashes_recognized'] = false;
        $report['blockers'][] = 'Unrecognized account password hash';
    }
}
foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $object => $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.$object WHERE $column=?");
    $stmt->execute([$database]);
    $report['objects'][strtolower($object)] = (int) $stmt->fetchColumn();
}
if ($report['objects']['triggers'] || $report['objects']['events']) {
    $report['blockers'][] = 'Review triggers/events before cleanup';
}
$pdo->commit();
$report['total_proposed_deletions'] = array_sum(array_column($report['tables'], 'proposed_delete'));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
