#!/usr/bin/env python3
"""Verify the prior restore-tested backup and snapshot only LOCAL veritas_portal.
No restore, schema creation, or writes to any database are performed.
"""
import collections, hashlib, json, os, re, subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PHP='/Applications/XAMPP/xamppfiles/bin/php'
OUT=Path('/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation')
os.umask(0o077)
prior=Path('/Users/johnlukereynon/Faculty-backups/bat405-20261117-approved')
m=json.loads((prior/'manifest.json').read_text())
assert hashlib.sha256(Path(m['backup']).read_bytes()).hexdigest()==m['sha256']
assert m['verification'].startswith('PASS: full restore;')
def run(args,**kw):return subprocess.run(args,cwd=ROOT,check=True,**kw)
def audit():return json.loads(run([PHP,'tools/database_audit.php'],stdout=subprocess.PIPE).stdout)
before=audit()
assert not before['blockers'] and before['database']=='veritas_portal'
assert all(t['rows']==0 for n,t in before['tables'].items() if n not in ['users','meta'])
original=json.loads((prior/'audit-before.json').read_text())
assert before['tables']['users']['data_sha256']==original['tables']['users']['data_sha256']
OUT.mkdir(mode=0o700,exist_ok=False)
config=json.loads(run([PHP,'-r','require "config.php"; echo json_encode([DB_HOST,DB_NAME,DB_USER,DB_PASS]);'],stdout=subprocess.PIPE).stdout)
host,database,user,password=config
assert host in ['127.0.0.1','localhost'] and database=='veritas_portal'
backup=OUT/'veritas_portal.sql'
with backup.open('wb') as stream:
 run(['/Applications/XAMPP/xamppfiles/bin/mysqldump','--host='+host,'--user='+user,'--protocol=TCP','--single-transaction','--quick','--routines','--events','--triggers','--hex-blob','--complete-insert','--skip-extended-insert','--skip-add-drop-table',database],env=dict(os.environ,MYSQL_PWD=password),stdout=stream)
after=audit();assert before['tables']==after['tables']
counts=collections.Counter(re.findall(r'^INSERT INTO `([^`]+)`',backup.read_text(),re.M))
assert all(counts[n]==t['rows'] for n,t in before['tables'].items())
assert len(re.findall(r'^CREATE TABLE ',backup.read_text(),re.M))==19
(OUT/'audit-before.json').write_text(json.dumps(before,indent=2)+'\n')
result={'status':'PASS','database':database,'backup':str(backup),'sha256':hashlib.sha256(backup.read_bytes()).hexdigest(),'bytes':backup.stat().st_size,'prior_restore_tested_backup_verified':True,'current_snapshot_count_checks':True,'schema_definitions':19,'source_unchanged':True,'other_databases_modified':False,
 'verification_note':'Prior full restore evidence and its SQL checksum verified; current clean snapshot validated by complete INSERT counts, 19 schema definitions and unchanged source fingerprints. No new restore into another database was performed.'}
(OUT/'manifest.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
