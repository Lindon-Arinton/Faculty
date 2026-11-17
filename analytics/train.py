#!/usr/bin/env python3
"""Train a first-semester-only UCI 697 Decision Tree; never connects to university databases."""
import argparse
import csv
from datetime import datetime, timezone
import hashlib
import io
import json
from pathlib import Path
import platform
import urllib.request
import zipfile

import joblib
import numpy as np
import sklearn
from sklearn.dummy import DummyClassifier
from sklearn.metrics import classification_report, confusion_matrix, f1_score, make_scorer, recall_score
from sklearn.model_selection import GridSearchCV, StratifiedKFold, train_test_split
from sklearn.tree import DecisionTreeClassifier, export_text

ROOT = Path(__file__).resolve().parent
SOURCE = 'https://archive.ics.uci.edu/static/public/697/predict%2Bstudents%2Bdropout%2Band%2Bacademic%2Bsuccess.zip'
DATASET_PAGE = 'https://archive.ics.uci.edu/dataset/697/predict+students+dropout+and+academic+success'
FEATURES = [
    {'key':'enrolled', 'column':'Curricular units 1st sem (enrolled)', 'label':'First-semester units enrolled', 'integer':True},
    {'key':'approved', 'column':'Curricular units 1st sem (approved)', 'label':'First-semester units passed', 'integer':True},
    {'key':'grade', 'column':'Curricular units 1st sem (grade)', 'label':'First-semester average grade', 'integer':False},
    {'key':'evaluations', 'column':'Curricular units 1st sem (evaluations)', 'label':'First-semester evaluations', 'integer':True},
]
LABELS = ['Dropout','Enrolled','Graduate']
SEED = 405


def counts(labels):
    return {label:int(np.sum(np.asarray(labels)==label)) for label in LABELS}


def evaluate(y, predictions):
    return {'dropout_recall':float(recall_score(y,predictions,labels=['Dropout'],average='macro',zero_division=0)),
            'macro_f1':float(f1_score(y,predictions,labels=LABELS,average='macro',zero_division=0)),
            'confusion_matrix':confusion_matrix(y,predictions,labels=LABELS).tolist(),
            'classification_report':classification_report(y,predictions,labels=LABELS,output_dict=True,zero_division=0)}


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--download', action='store_true', help='Download the official archive if not cached')
args=parser.parse_args()
data_dir=ROOT/'data'; artifacts=ROOT/'artifacts'
data_dir.mkdir(exist_ok=True); artifacts.mkdir(exist_ok=True)
archive=data_dir/'uci697.zip'
if not archive.exists():
    if not args.download: raise SystemExit('Dataset archive missing; pass --download to fetch it from UCI')
    with urllib.request.urlopen(SOURCE,timeout=60) as response:
        if response.geturl().split('/')[2] != 'archive.ics.uci.edu': raise ValueError('Unexpected download origin')
        payload=response.read(5_000_001)
    if len(payload)>5_000_000: raise ValueError('Unexpected archive size')
    archive.write_bytes(payload)
archive_hash=hashlib.sha256(archive.read_bytes()).hexdigest()
checksum_file=ROOT/'dataset-checksum.txt'
if checksum_file.exists() and checksum_file.read_text().split()[0] != archive_hash:
    raise ValueError('Dataset checksum changed; review official source before training')
with zipfile.ZipFile(archive) as bundle:
    members=[n for n in bundle.namelist() if n.endswith('data.csv')]
    if len(members)!=1: raise ValueError('Expected exactly one official data.csv')
    content=bundle.read(members[0])
reader=csv.DictReader(io.StringIO(content.decode('utf-8-sig')),delimiter=';')
rows=list(reader)
if len(rows)!=4424 or len(reader.fieldnames)!=37 or 'Target' not in reader.fieldnames:
    raise ValueError('Official dataset shape mismatch')
if any(any(v is None or v.strip()=='' for v in row.values()) for row in rows):
    raise ValueError('Missing values detected; no silent imputation allowed')
columns=[f['column'] for f in FEATURES]
if any(c not in reader.fieldnames or '2nd' in c for c in columns): raise ValueError('Feature allowlist invalid')
X=np.asarray([[float(row[c]) for c in columns] for row in rows],dtype=np.float64)
y=np.asarray([row['Target'] for row in rows])
if not np.isfinite(X).all() or (X<0).any() or set(y)!=set(LABELS): raise ValueError('Invalid data values or labels')
for i,f in enumerate(FEATURES):
    if f['integer'] and not np.equal(X[:,i],np.floor(X[:,i])).all(): raise ValueError('Expected integer academic counts')
    f['min']=float(X[:,i].min()); f['max']=float(X[:,i].max())
if (X[:,1]>X[:,0]).any() or (X[:,2]>20).any(): raise ValueError('Invalid passed-unit count or grade scale')
full_duplicates=len(rows)-len({tuple(row[c] for c in reader.fieldnames) for row in rows})
if full_duplicates: raise ValueError('Exact full-record duplicates require review before independent splitting')
checksum_file.write_text(archive_hash+'  uci697.zip\n')
(data_dir/'data.csv').write_bytes(content)
with (data_dir/'first-semester.csv').open('w', newline='') as prepared:
    writer=csv.writer(prepared)
    writer.writerow(columns+['Target'])
    writer.writerows([row[c] for c in columns]+[row['Target']] for row in rows)
indices=np.arange(len(rows))
train_idx,test_idx=train_test_split(indices,test_size=.2,random_state=SEED,stratify=y)
cv=StratifiedKFold(n_splits=5,shuffle=True,random_state=SEED)
scoring={'macro_f1':'f1_macro','dropout_recall':make_scorer(recall_score,labels=['Dropout'],average='macro',zero_division=0)}
grid=GridSearchCV(DecisionTreeClassifier(random_state=SEED),
    {'max_depth':[2,3,4,5,6], 'min_samples_leaf':[10,20,40,80], 'class_weight':[None,'balanced']},
    scoring=scoring,refit='macro_f1',cv=cv,n_jobs=1,return_train_score=False,error_score='raise')
grid.fit(X[train_idx],y[train_idx])
model=grid.best_estimator_
predicted=model.predict(X[test_idx])
baseline=DummyClassifier(strategy='most_frequent').fit(X[train_idx],y[train_idx])
selected=grid.best_index_
report={
    'dataset':{'id':697,'title':"Predict Students' Dropout and Academic Success",'page':DATASET_PAGE,'download':SOURCE,
               'license':'CC BY 4.0','archive_sha256':archive_hash,'csv_sha256':hashlib.sha256(content).hexdigest(),
               'records':len(rows),'raw_input_features':36,'missing_values':0,'exact_full_record_duplicates':full_duplicates,
               'class_counts':counts(y)},
    'method':{'model':'sklearn.tree.DecisionTreeClassifier','prediction_time':'After the first semester',
              'features':FEATURES,'excluded':'All second-semester, demographic, financial and SETE variables',
              'split':'stratified 80/20','seed':SEED,'train_records':len(train_idx),'test_records':len(test_idx),
              'train_class_counts':counts(y[train_idx]),'test_class_counts':counts(y[test_idx]),
              'cv':'5-fold stratified, shuffled; training set only', 'selection_metric':'macro F1',
              'candidates':len(grid.cv_results_['params']),'best_parameters':grid.best_params_,
              'selected_cv_macro_f1':float(grid.best_score_),
              'selected_cv_macro_f1_std':float(grid.cv_results_['std_test_macro_f1'][selected]),
              'selected_cv_dropout_recall':float(grid.cv_results_['mean_test_dropout_recall'][selected]),
              'cv_note':'Selection scores, not independent performance estimates. Held-out test results below were not used for selection.'},
    'test':evaluate(y[test_idx],predicted),'baseline_test':evaluate(y[test_idx],baseline.predict(X[test_idx])),
    'confusion_matrix_labels':LABELS,'confusion_matrix_orientation':'rows=actual; columns=predicted',
    'tree':{'depth':int(model.get_depth()),'leaves':int(model.get_n_leaves())},
    'runtime':{'python':platform.python_version(),'scikit_learn':sklearn.__version__,'numpy':np.__version__},
    'trained_at':datetime.now(timezone.utc).isoformat(),
    'limitations':['External Portuguese university data; local performance has not been validated.',
                   'Outcomes are eventual Dropout/Enrolled/Graduate, not subject failure or causes.',
                   'Only end-of-first-semester inputs are supported; this is not an admission-time model.',
                   'Leaf class shares are not calibrated individual risk probabilities.',
                   'SETE and personnel decisions are outside this model.'],
}
joblib.dump(model,artifacts/'decision_tree.joblib')
tree=model.tree_
values=tree.value[:,0,:]
values=values/values.sum(axis=1,keepdims=True)
export={'format_version':1,'model_type':report['method']['model'],'features':FEATURES,
        'classes':model.classes_.tolist(),'children_left':tree.children_left.tolist(),'children_right':tree.children_right.tolist(),
        'feature':tree.feature.tolist(),'threshold':tree.threshold.tolist(),'probabilities':values.tolist(),
        'dataset_sha256':archive_hash,'split_seed':SEED,'trained_at':report['trained_at']}
(artifacts/'tree.json').write_text(json.dumps(export,indent=2)+'\n')
report['artifacts']={'joblib_sha256':hashlib.sha256((artifacts/'decision_tree.joblib').read_bytes()).hexdigest(),
                     'tree_json_sha256':hashlib.sha256((artifacts/'tree.json').read_bytes()).hexdigest()}
(artifacts/'metrics.json').write_text(json.dumps(report,indent=2)+'\n')
(artifacts/'tree-rules.txt').write_text(export_text(model,feature_names=columns)+'\n')
# Save only four public academic inputs in a small explicit demonstration sample.
# Never import these rows into the university database or assign university IDs.
samples=[]
for label in LABELS:
    candidates=[i for i in test_idx if y[i]==label]
    for index in candidates[:4]:
        samples.append({'id':'UCI697-row-'+str(index+1),'features':dict(zip([f['key'] for f in FEATURES],X[index].tolist()))})
(artifacts/'demo-samples.json').write_text(json.dumps(samples,indent=2)+'\n')
# Public held-out feature vectors are used only for cross-language inference parity.
parity={'features':X[test_idx].tolist(),'expected':predicted.tolist(),
        'probabilities':model.predict_proba(X[test_idx]).tolist()}
(data_dir/'parity.json').write_text(json.dumps(parity)+'\n')
(data_dir/'split-indices.json').write_text(json.dumps({'train':train_idx.tolist(),'test':test_idx.tolist()})+'\n')
print(json.dumps({'dataset_records':len(rows),'best_parameters':grid.best_params_,
                  'test':report['test'],'selected_cv_macro_f1':grid.best_score_},indent=2))
