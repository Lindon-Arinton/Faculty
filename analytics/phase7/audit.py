#!/usr/bin/env python3
"""Read-only Phase 7 verification; writes evidence only beside this script. Never fits a model."""
import csv, hashlib, json, subprocess, re, urllib.request, urllib.error, urllib.parse, http.cookiejar
from pathlib import Path
from collections import Counter
import joblib, numpy as np
from sklearn.tree import DecisionTreeClassifier
from sklearn.metrics import recall_score,f1_score,confusion_matrix,classification_report
from sklearn.model_selection import train_test_split,StratifiedKFold
R=Path(__file__).resolve().parents[2]; E=R/'analytics/phase7/evidence'; PHP='/Applications/XAMPP/xamppfiles/bin/php'
def save(n,v): (E/n).write_text(json.dumps(v,indent=2,default=lambda x:x.item() if isinstance(x,np.generic) else str(x))+'\n')
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def manifest():return {str(p.relative_to(R)):sha(p) for p in R.rglob('*') if p.is_file() and not any(x in p.relative_to(R).parts for x in ['.git','.DS_Store','phase7'])}
def phpfile(f):return json.loads(subprocess.check_output([PHP,f],cwd=R))
before=manifest(); db=phpfile('tools/database_audit.php'); save('database-before.json',db)
state=json.loads(Path('/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/population-state.json').read_text())
pre=json.loads(Path('/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/audit-before.json').read_text())
# State contains per-table fingerprints, not raw student/account values.
prov={}
for t,v in db['tables'].items():
 s=state['after'][t]
 prov[t]={'rows':v['rows'],'matches_phase6_final':v['data_sha256']==(s['data_sha256'] if isinstance(s,dict) else s),'data_sha256':v['data_sha256'],'phase6_inserted':state['inserted'].get(t,0)}
assert all(v['matches_phase6_final'] for v in prov.values())
assert db['tables']['users']['data_sha256']==pre['tables']['users']['data_sha256']
assert not any(f['orphan_rows'] for f in db['foreign_keys'])
save('provenance.json',{'tables':prov,'total_rows':sum(v['rows'] for v in prov.values()),'inserted':sum(state['inserted'].values()),'account_matches_prepopulation':True,'foreign_keys':db['foreign_keys'],'quality_corrections':state['quality_corrections']})
m=json.loads((R/'analytics/artifacts/metrics.json').read_text()); a=R/'analytics/artifacts'; d=R/'analytics/data'
assert sha(a/'decision_tree.joblib')==m['artifacts']['joblib_sha256']; assert sha(a/'tree.json')==m['artifacts']['tree_json_sha256']
model=joblib.load(a/'decision_tree.joblib'); assert isinstance(model,DecisionTreeClassifier)
rows=list(csv.DictReader((d/'data.csv').open(),delimiter=';')); fs=m['method']['features']; keys=[f['key'] for f in fs]
X=np.array([[float(r[f['column']]) for f in fs] for r in rows]); y=np.array([r['Target'] for r in rows]); split=json.loads((d/'split-indices.json').read_text());tr,te=split['train'],split['test']
a1,a2=train_test_split(np.arange(len(y)),test_size=.2,random_state=405,stratify=y);assert list(a1)==tr and list(a2)==te
pred=model.predict(X[te]); actual={'dropout_recall':recall_score(y[te],pred,labels=['Dropout'],average='macro'),'macro_f1':f1_score(y[te],pred,average='macro'),'confusion_matrix':confusion_matrix(y[te],pred,labels=model.classes_).tolist(),'classification_report':classification_report(y[te],pred,output_dict=True)}
assert abs(actual['dropout_recall']-m['test']['dropout_recall'])<1e-12 and abs(actual['macro_f1']-m['test']['macro_f1'])<1e-12 and actual['confusion_matrix']==m['test']['confusion_matrix']
tree=json.loads((a/'tree.json').read_text())
for k in ['children_left','children_right','feature','threshold']:assert np.array_equal(np.array(tree[k]),getattr(model.tree_,k))
assert tree['classes']==model.classes_.tolist(); assert model.n_features_in_==4
assert np.allclose(tree['probabilities'],model.tree_.value[:,0,:]/model.tree_.value[:,0,:].sum(axis=1,keepdims=True),atol=1e-15)
payload=[dict(zip(keys,row.tolist())) for row in X]
def batch(p):return json.loads(subprocess.run([PHP,'tests/prediction_batch.php'],cwd=R,input=json.dumps(p),capture_output=True,text=True,check=True).stdout)
def parity(p):
 out=batch(p);xx=np.array([[v[k] for k in keys] for v in p]);assert [v['outcome'] for v in out]==model.predict(xx).tolist();assert np.allclose([[v['probabilities'][c] for c in model.classes_] for v in out],model.predict_proba(xx),atol=1e-12,rtol=0);return out
parity(payload);edges=[]
for i,f in enumerate(model.tree_.feature):
 if f==2:
  t=np.float32(model.tree_.threshold[i])
  for v in [np.nextafter(t,np.float32(-np.inf)),t,np.nextafter(t,np.float32(np.inf))]:
   if fs[2]['min']<=v<=fs[2]['max']:edges.append(dict(enrolled=6,approved=4,grade=float(v),evaluations=8))
parity(edges)
folds=[]
for ft,fv in StratifiedKFold(5,shuffle=True,random_state=405).split(X[tr],y[tr]):
 assert not set(np.array(tr)[fv])&set(te)
 folds.append({'train':len(ft),'validation':len(fv),'validation_classes':dict(Counter(y[np.array(tr)[fv]]))})
report={'status':'PASS','model_class':type(model).__module__+'.'+type(model).__name__,'parameters':model.get_params(),'features':fs,'classes':model.classes_.tolist(),'rows':len(rows),'columns':len(rows[0]),'missing':sum(v is None or v=='' for r in rows for v in r.values()),'full_duplicates':len(rows)-len({tuple(r.values()) for r in rows}),'class_counts':dict(Counter(y)),'train_rows':len(tr),'test_rows':len(te),'saved_split_reproduced':True,'cv_folds':folds,'cv_score_reproduced':False,'cv_note':'No refitting permitted; historical selected scores remain saved training evidence only.','root_training_samples':int(model.tree_.n_node_samples[0]),'nodes':model.tree_.node_count,'depth':model.get_depth(),'leaves':model.get_n_leaves(),'test':actual,'php_parity_rows':len(payload),'threshold_cases':len(edges),'test_feature_vectors_seen_in_train':sum(tuple(v) in {tuple(w) for w in X[tr]} for v in X[te]),'artifacts_sha256':{p.name:sha(p) for p in a.iterdir() if p.is_file()}}
save('model-results.json',report)
# Read configured local credentials into process memory; never emit them.
creds=json.loads(subprocess.check_output([PHP,'-r',"require 'config.php'; echo json_encode([DEFAULT_USER_EMAIL,DEFAULT_USER_PASS]);"],cwd=R))
base='http://localhost/Faculty/'; jar=http.cookiejar.CookieJar();op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def req(path,body=None,ctype=None,client=op):
 rq=urllib.request.Request(base+path,data=body,headers={'Content-Type':ctype} if ctype else {})
 try:
  with client.open(rq,timeout=30) as r:return r.status,r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.read().decode()
status,html=req('login.php');token=re.search(r'name="csrf" value="([^"]+)"',html)[1]
status,html=req('login.php',urllib.parse.urlencode({'csrf':token,'email':creds[0],'password':creds[1]}).encode(),'application/x-www-form-urlencoded');assert 'Sign out' in html or 'Logout' in html or 'logout.php' in html
status,html=req('predictions.php');token=re.search(r'name="csrf"[^>]*value="([^"]+)"',html)
if token:token=token[1]
else:token=re.search(r'"csrf"\s*:\s*"([^"]+)"',html)[1]
def api(features):return req('api/predict.php',json.dumps({'csrf':token,'features':features}).encode(),'application/json')
for idx in te:
 status,b=api(payload[idx]);assert status==200,(status,b);v=json.loads(b);expected=model.predict(X[idx:idx+1])[0];assert v['outcome']==expected; assert np.allclose([v['probabilities'][c] for c in model.classes_],model.predict_proba(X[idx:idx+1])[0],atol=1e-12,rtol=0)
representatives=[]
for cls in model.classes_:
 idx=next(i for i in te if model.predict(X[i:i+1])[0]==cls);_,b=api(payload[idx]);representatives.append({'public_uci_row':idx+1,'features':payload[idx],'actual_label':str(y[idx]),'response':json.loads(b)})
save('class-examples.json',representatives)
checks=[]
def check(n,status,expected):checks.append({'test':n,'status':status,'expected':expected,'pass':status==expected});assert status==expected,(n,status)
check('unauthenticated',req('api/predict.php',client=urllib.request.build_opener())[0],401)
check('GET method',req('api/predict.php')[0],405)
check('content type',req('api/predict.php',b'{}','text/plain')[0],415)
check('invalid JSON',req('api/predict.php',b'{','application/json')[0],400)
check('oversize',req('api/predict.php',b' '*4100,'application/json')[0],413)
check('CSRF',req('api/predict.php',b'{"csrf":"wrong","features":{}}','application/json')[0],403)
for name,p in [('missing',{}),('identity',{**payload[0],'student_id':'test'}),('second_semester',{**payload[0],'second_semester_grade':10}),('negative',{**payload[0],'enrolled':-1}),('fractional_count',{**payload[0],'evaluations':1.5}),('approved_exceeds_enrolled',{**payload[0],'approved':26,'enrolled':1}),('range',{**payload[0],'grade':20}),('type',{**payload[0],'grade':True})]:check(name,api(p)[0],422)
save('api-results.json',{'status':'PASS','heldout_predictions_and_probabilities_matched':len(te),'checks':checks,'model_unavailable_503':'NOT VERIFIED in Phase 7: requires modifying artifact/service state; historical Phase 6 simulated test exists.'})
# Fresh official archive fetched in memory; no cached dataset is overwritten.
try:
 with urllib.request.urlopen(m['dataset']['download'],timeout=45) as r:raw=r.read(5000001);url=r.geturl()
 h=hashlib.sha256(raw).hexdigest();assert h==m['dataset']['archive_sha256'];save('official-source.json',{'status':'PASS','url':url,'bytes':len(raw),'sha256':h,'matches_cached':h==sha(d/'uci697.zip')})
except Exception as e:save('official-source.json',{'status':'NOT VERIFIED','reason':str(e),'cached_archive_sha256':sha(d/'uci697.zip')})
after=manifest();assert before==after,'Existing project files changed'
db2=phpfile('tools/database_audit.php');save('database-after.json',db2);assert db['tables']==db2['tables'];assert db['foreign_keys']==db2['foreign_keys']
save('preservation.json',{'status':'PASS','existing_project_files_checked':len(before),'existing_files_unchanged':before==after,'database_tables_unchanged':db['tables']==db2['tables'],'foreign_keys_unchanged':True,'accounts_unchanged':True,'baseline_existing_files':before})
print(json.dumps({'status':'PASS','tables':len(prov),'rows':sum(v['rows'] for v in prov.values()),'php_rows':len(payload),'api_rows':len(te),'edge_cases':len(edges),'dropout_recall':actual['dropout_recall'],'macro_f1':actual['macro_f1']}))
