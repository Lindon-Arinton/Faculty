"""Read existing files only; add data-preparation and account lineage evidence."""
from pathlib import Path
import json,csv,hashlib,zipfile
P=Path(__file__).parent;R=P.parents[1];E=P/'evidence'
m=json.loads((R/'analytics/artifacts/metrics.json').read_text());d=R/'analytics/data'
raw=list(csv.DictReader((d/'data.csv').open(),delimiter=';'));cols=[f['column'] for f in m['method']['features']]
prepared=list(csv.reader((d/'first-semester.csv').open()));assert prepared[0]==cols+['Target'];assert prepared[1:]==[[r[k] for k in cols]+[r['Target']] for r in raw]
with zipfile.ZipFile(d/'uci697.zip') as z:
 members=[n for n in z.namelist() if n.endswith('data.csv')];assert len(members)==1;assert z.read(members[0])==(d/'data.csv').read_bytes()
assert hashlib.sha256((d/'data.csv').read_bytes()).hexdigest()==m['dataset']['csv_sha256']
before=json.loads((E/'database-before.json').read_text());old=json.loads(Path('/Users/johnlukereynon/Faculty-backups/bat405-20261117-approved/audit-before.json').read_text());assert before['tables']['users']['data_sha256']==old['tables']['users']['data_sha256']
php=__import__('subprocess');samples=json.loads((R/'analytics/artifacts/demo-samples.json').read_text());out=json.loads(php.run(['/Applications/XAMPP/xamppfiles/bin/php','tests/prediction_batch.php'],cwd=R,input=json.dumps([s['features'] for s in samples]),capture_output=True,text=True,check=True).stdout)
counts={c:sum(v['outcome']==c for v in out) for c in ['Dropout','Enrolled','Graduate']}
result={'status':'PASS','prepared_four_columns_and_target_match_original':True,'cached_csv_matches_archive_member':True,'csv_checksum_matches_saved_training_report':True,'account_matches_original_precleanup_backup_all_fields':True,'public_demo_sample_count':len(samples),'public_demo_model_predictions':counts}
(E/'supplement.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))
