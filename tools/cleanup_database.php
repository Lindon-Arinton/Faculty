<?php
/** Approved local-only cleanup; defaults to dry run. Never drops/truncates tables. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
$options = getopt('', ['database:', 'backup-dir:', 'execute']);
$database = $options['database'] ?? '';
$backupDir = $options['backup-dir'] ?? '';
if (!in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true)
    || !($database === 'veritas_portal' || preg_match('/^bat405_verify_[0-9_]+$/D', $database))
    || !$backupDir) throw new RuntimeException('Only the approved local database or its isolated verification copy is allowed.');
$manifest = json_decode((string) file_get_contents($backupDir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$baseline = json_decode((string) file_get_contents($backupDir . '/audit-before.json'), true, 512, JSON_THROW_ON_ERROR);
if ($manifest['source_database'] !== 'veritas_portal' || $manifest['proposed_deletions'] !== 88668
    || !$manifest['source_unchanged'] || !str_starts_with($manifest['verification'], 'PASS: full restore;')
    || !hash_equals($manifest['sha256'], hash_file('sha256', $manifest['backup']))) {
    throw new RuntimeException('Verified backup evidence does not match the approved cleanup.');
}
$lock = fopen(__DIR__ . '/../var/cleanup.lock', 'r+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Active application requests: stop and retry during a quiet period.');
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . $database . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$auditProcess = proc_open([PHP_BINARY, __DIR__ . '/database_audit.php', $database], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
$auditText = stream_get_contents($pipes[1]); $auditError = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($auditProcess) !== 0) throw new RuntimeException('Preflight audit failed: ' . $auditError);
$audit = json_decode($auditText, true, 512, JSON_THROW_ON_ERROR);
if ($audit['blockers'] || $audit['tables'] !== $baseline['tables'] || $audit['total_proposed_deletions'] !== 88668
    || $audit['account_checks'] !== $baseline['account_checks']) {
    throw new RuntimeException('Unexpected source changes or integrity issue. Cleanup stopped; no records deleted.');
}
$report = ['database'=>$database, 'mode'=>isset($options['execute']) ? 'EXECUTE' : 'DRY RUN',
    'approved_deletions'=>88668, 'proposed_tables'=>array_map(fn($t)=>$t['proposed_delete'], $audit['tables']),
    'accounts_preserved'=>$audit['account_checks']['count'], 'backup_sha256'=>$manifest['sha256']];
if (!isset($options['execute'])) { echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL; exit; }
if ($database === 'veritas_portal') {
    $rehearsal = json_decode((string) file_get_contents($backupDir . '/rehearsal.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($rehearsal['status'] ?? '') !== 'PASS' || ($rehearsal['deleted_total'] ?? 0) !== 88668
        || ($rehearsal['accounts_unchanged'] ?? false) !== true || ($rehearsal['schemas_unchanged'] ?? false) !== true
        || ($rehearsal['database'] ?? '') !== $manifest['verification_database']) {
        throw new RuntimeException('Successful rehearsal evidence required before source cleanup.');
    }
    $tests = json_decode((string) file_get_contents($backupDir . '/precleanup-tests.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($tests['status'] ?? '') !== 'PASS' || ($tests['database'] ?? '') !== $manifest['verification_database']) {
        throw new RuntimeException('Passing isolated integration tests required before source cleanup.');
    }
}
$quote = fn(string $s): string => '`' . str_replace('`', '``', $s) . '`';
$fingerprint = function(string $table, bool $forUpdate = false) use ($pdo, $quote): string {
    $hashes = [];
    foreach ($pdo->query('SELECT * FROM ' . $quote($table) . ($forUpdate ? ' FOR UPDATE' : '')) as $row) $hashes[] = hash('sha256', serialize($row));
    sort($hashes, SORT_STRING);
    return hash('sha256', implode('', $hashes));
};
$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
$pdo->beginTransaction();
try {
    foreach ($baseline['tables'] as $table=>$data) {
        if (!hash_equals($data['data_sha256'], $fingerprint($table, true))) throw new RuntimeException('Concurrent record change detected: ' . $table);
    }
    $deleted = [];
    foreach ($baseline['proposed_delete_order'] as $table) {
        $deleted[$table] = $pdo->exec('DELETE FROM ' . $quote($table));
        if ($deleted[$table] !== $baseline['tables'][$table]['proposed_delete']) throw new RuntimeException('Unexpected delete count: ' . $table);
    }
    $keys = $baseline['proposed_meta_keys_to_delete'];
    $stmt = $pdo->prepare('DELETE FROM meta WHERE k IN (' . implode(',', array_fill(0, count($keys), '?')) . ')');
    $stmt->execute($keys); $deleted['meta'] = $stmt->rowCount();
    if (array_sum($deleted) !== 88668) throw new RuntimeException('Approved total mismatch');
    $pdo->prepare('INSERT INTO meta (k,v) VALUES (?,?)')->execute(['case_study_cleaned', date(DATE_ATOM)]);
    foreach ($baseline['tables'] as $table=>$data) {
        $schema = $pdo->query('SHOW CREATE TABLE ' . $quote($table))->fetch(PDO::FETCH_NUM)[1];
        if (!hash_equals($data['schema_sha256'], hash('sha256', $schema))) throw new RuntimeException('Schema changed: ' . $table);
        $expected = $table === 'meta' ? $data['rows'] - $data['proposed_delete'] + 1 : $data['rows'] - $data['proposed_delete'];
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $quote($table))->fetchColumn() !== $expected) throw new RuntimeException('Unexpected final count: ' . $table);
    }
    if (!hash_equals($baseline['tables']['users']['data_sha256'], $fingerprint('users'))) throw new RuntimeException('Account preservation failure');
    foreach ($audit['foreign_keys'] as $fk) {
        $sql = 'SELECT COUNT(*) FROM ' . $quote($fk['TABLE_NAME']) . ' c LEFT JOIN ' . $quote($fk['REFERENCED_TABLE_NAME']) . ' p ON c.' . $quote($fk['COLUMN_NAME']) . '=p.' . $quote($fk['REFERENCED_COLUMN_NAME']) . ' WHERE c.' . $quote($fk['COLUMN_NAME']) . ' IS NOT NULL AND p.' . $quote($fk['REFERENCED_COLUMN_NAME']) . ' IS NULL';
        if ((int) $pdo->query($sql)->fetchColumn()) throw new RuntimeException('Foreign key integrity failure');
    }
    $pdo->commit();
    $report += ['status'=>'PASS', 'deleted'=>$deleted, 'deleted_total'=>array_sum($deleted),
        'accounts_unchanged'=>true, 'schemas_unchanged'=>true, 'orphan_rows'=>0, 'foreign_keys_checked'=>count($audit['foreign_keys']),
        'completed_at'=>date(DATE_ATOM)];
    // Separate database-specific caches; purge both old and current cache versions.
    foreach (glob(sys_get_temp_dir() . '/veritas-*.ser') ?: [] as $file) @unlink($file);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
} finally { flock($lock, LOCK_UN); fclose($lock); }
