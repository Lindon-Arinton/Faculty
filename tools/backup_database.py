#!/usr/bin/env python3
"""Back up the configured local database and restore into a new verification schema.
Never imports into, deletes from, or changes rows in the source database.
The verification database is retained for review; nothing is dropped.
"""
import argparse
import collections
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PHP = '/Applications/XAMPP/xamppfiles/bin/php'
MYSQL = '/Applications/XAMPP/xamppfiles/bin/mysql'
DUMP = '/Applications/XAMPP/xamppfiles/bin/mysqldump'


def run(args, **kwargs):
    return subprocess.run(args, cwd=ROOT, check=True, **kwargs)


def audit(database=None):
    args = [PHP, str(ROOT / 'tools/database_audit.php')]
    if database:
        args.append(database)
    return json.loads(run(args, stdout=subprocess.PIPE).stdout)


def signatures(report):
    return {name: {k: row[k] for k in ('rows', 'data_sha256', 'schema_sha256')}
            for name, row in report['tables'].items()}


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output-dir', required=True)
args = parser.parse_args()
os.umask(0o077)
output = Path(args.output_dir).expanduser().resolve()
output.mkdir(parents=True, exist_ok=False, mode=0o700)
config = json.loads(run([PHP, '-r', 'require "config.php"; echo json_encode([DB_HOST, DB_NAME, DB_USER, DB_PASS]);'], stdout=subprocess.PIPE).stdout)
host, database, user, password = config
if host not in ('127.0.0.1', 'localhost', '::1'):
    raise SystemExit('This tool is restricted to local databases. Review environment first.')
if not re.fullmatch(r'[A-Za-z0-9_]+', database):
    raise SystemExit('Invalid source database name')
env = dict(os.environ, MYSQL_PWD=password)
connection = ['--host=' + host, '--user=' + user, '--protocol=TCP']
before = audit()
(output / 'audit-before.json').write_text(json.dumps(before, indent=2) + '\n')
dump = output / (database + '.sql')
with dump.open('wb') as stream:
    run([DUMP, *connection, '--single-transaction', '--quick', '--routines', '--events', '--triggers',
         '--hex-blob', '--complete-insert', '--skip-extended-insert', '--skip-add-drop-table',
         '--default-character-set=utf8mb4', database], env=env, stdout=stream)
after = audit()
(output / 'audit-after.json').write_text(json.dumps(after, indent=2) + '\n')
if signatures(before) != signatures(after) or before['objects'] != after['objects']:
    raise SystemExit('Source changed during backup. Backup is NOT verified; rerun during a quiet period.')
counts = collections.Counter()
with dump.open() as stream:
    for line in stream:
        match = re.match(r'INSERT INTO `([^`]+)`', line)
        if match:
            counts[match.group(1)] += 1
if any(counts[name] != entry['rows'] for name, entry in before['tables'].items()):
    raise SystemExit('Backup INSERT record counts do not match audit; verification stopped.')
if before['blockers']:
    raise SystemExit('Audit blockers require review before restoring the backup: ' + '; '.join(before['blockers']))
verify_db = 'bat405_verify_' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S_%f')
run([MYSQL, *connection, '--execute=CREATE DATABASE `' + verify_db + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'], env=env, stdout=subprocess.PIPE)
with dump.open('rb') as stream:
    run([MYSQL, *connection, verify_db], env=env, stdin=stream, stdout=subprocess.PIPE)
restored = audit(verify_db)
(output / 'audit-restored.json').write_text(json.dumps(restored, indent=2) + '\n')
if signatures(before) != signatures(restored) or before['objects'] != restored['objects'] or restored['blockers']:
    raise SystemExit('Restore verification failed. Keep the source unchanged and inspect audits.')
final = audit()
if signatures(before) != signatures(final):
    raise SystemExit('Source changed during restore verification. Review before cleanup.')
manifest = {'source_database': database, 'backup': str(dump), 'bytes': dump.stat().st_size,
            'sha256': hashlib.sha256(dump.read_bytes()).hexdigest(),
            'verification_database': verify_db, 'verification': 'PASS: full restore; every table row count, data fingerprint and schema fingerprint match; object counts match',
            'source_unchanged': True, 'table_count': len(before['tables']),
            'preserved_account_count': before['account_checks']['count'],
            'proposed_deletions': before['total_proposed_deletions'],
            'cleanup_executed': False}
(output / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
print(json.dumps(manifest, indent=2))
