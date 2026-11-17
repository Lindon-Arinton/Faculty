#!/usr/bin/env python3
"""Verify saved-model metrics and PHP inference against the fitted sklearn artifact."""
import csv
import hashlib
import json
from pathlib import Path
import subprocess
import joblib
import numpy as np
from sklearn.metrics import f1_score, recall_score, confusion_matrix

ROOT=Path(__file__).resolve().parents[1]
a=ROOT/'analytics/artifacts'
r=json.loads((a/'metrics.json').read_text())
assert hashlib.sha256((a/'decision_tree.joblib').read_bytes()).hexdigest()==r['artifacts']['joblib_sha256']
assert hashlib.sha256((a/'tree.json').read_bytes()).hexdigest()==r['artifacts']['tree_json_sha256']
model=joblib.load(a/'decision_tree.joblib')  # Only this locally trained, checksum-verified artifact.
rows=list(csv.DictReader((ROOT/'analytics/data/data.csv').open(),delimiter=';'))
features=r['method']['features']; keys=[f['key'] for f in features]
assert len(features)==4 and all('1st sem' in f['column'] for f in features)
X=np.asarray([[float(row[f['column']]) for f in features] for row in rows])
y=np.asarray([row['Target'] for row in rows])
split=json.loads((ROOT/'analytics/data/split-indices.json').read_text())
train,test=split['train'],split['test']
assert len(train)==3539 and len(test)==885 and not set(train)&set(test)
assert set(train)|set(test)==set(range(4424))
pred=model.predict(X[test])
assert abs(recall_score(y[test],pred,labels=['Dropout'],average='macro')-r['test']['dropout_recall'])<1e-12
assert abs(f1_score(y[test],pred,average='macro')-r['test']['macro_f1'])<1e-12
assert confusion_matrix(y[test],pred,labels=r['confusion_matrix_labels']).tolist()==r['test']['confusion_matrix']
assert sum(sum(row) for row in r['test']['confusion_matrix'])==885
# Independent runtime agreement on every source row; these are inference checks,
# not a second performance estimate or additional training.
payload=[dict(zip(keys,row.tolist())) for row in X]
php=subprocess.run(['/Applications/XAMPP/xamppfiles/bin/php','tests/prediction_batch.php'],cwd=ROOT,
                   input=json.dumps(payload),capture_output=True,text=True,check=True)
actual=json.loads(php.stdout)
assert [p['outcome'] for p in actual]==model.predict(X).tolist()
assert np.allclose([[p['probabilities'][c] for c in model.classes_] for p in actual],model.predict_proba(X),atol=1e-12,rtol=0)
# Probe adjacent valid floating-point grade values around continuous split thresholds.
edges=[]
for i,feature in enumerate(model.tree_.feature):
    if feature!=2: continue
    threshold=np.float32(model.tree_.threshold[i])
    for grade in [np.nextafter(threshold,np.float32(-np.inf)),threshold,np.nextafter(threshold,np.float32(np.inf))]:
        if features[2]['min']<=grade<=features[2]['max']:
            edges.append({'enrolled':6,'approved':4,'grade':float(grade),'evaluations':8})
php=subprocess.run(['/Applications/XAMPP/xamppfiles/bin/php','tests/prediction_batch.php'],cwd=ROOT,
                   input=json.dumps(edges),capture_output=True,text=True,check=True)
edge_result=json.loads(php.stdout)
assert [p['outcome'] for p in edge_result]==model.predict(np.asarray([[p[k] for k in keys] for p in edges])).tolist()
report={'status':'PASS','parity_records':len(rows),'threshold_edge_cases':len(edges),'metrics_reproduced':True,
        'split_disjoint':True,'first_semester_features_only':True}
(Path('/private/tmp')/'faculty-model-validation.json').write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
