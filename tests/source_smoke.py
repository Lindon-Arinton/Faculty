#!/usr/bin/env python3
"""Read-only HTTP smoke checks on LOCAL Apache; login preserves all account fields."""
import argparse
import http.cookiejar
import json
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT=Path(__file__).resolve().parents[1]
PHP='/Applications/XAMPP/xamppfiles/bin/php'
BASE='http://localhost/Faculty/'
parser=argparse.ArgumentParser();parser.add_argument('--demo',action='store_true');args=parser.parse_args()
checks=[]


def check(condition,message):
    if not condition: raise AssertionError(message)
    checks.append(message)


def audit():
    return json.loads(subprocess.run([PHP,'tools/database_audit.php'],cwd=ROOT,capture_output=True,text=True,check=True).stdout)


opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path,data=None,json_body=None):
    headers={}
    raw=None
    if data is not None: raw=urllib.parse.urlencode(data).encode()
    if json_body is not None:
        raw=json.dumps(json_body).encode(); headers['Content-Type']='application/json'
    req=urllib.request.Request(BASE+path,data=raw,headers=headers)
    try: response=opener.open(req,timeout=20)
    except urllib.error.HTTPError as error: response=error
    with response:
        body=response.read().decode()
        check(not re.search(r'(Fatal error|Warning:|Deprecated:|Notice:|Uncaught)',body),'No PHP error: '+path)
        return response.status,body,response.geturl()

before=audit()
check(before['database']=='veritas_portal' and before['account_checks']['count']==1,'Only approved local database and original account are present')
check(not before['blockers'],'No database integrity blockers')
for name,table in before['tables'].items():
    if args.demo:
        expected=json.loads(Path('/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation/population-state.json').read_text())['inserted'].get(name,0)+(1 if name in ('meta','users') else 0)
    else: expected=1 if name in ('meta','users') else 0
    check(table['rows']==expected,'Expected record count: '+name)
code,body,url=request('index.php')
check(code==200 and url.endswith('login.php'),'Unauthenticated Apache request reaches login')
token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
# Credentials stay in process memory and are sent only to the authorized local portal.
credentials=json.loads(subprocess.run([PHP,'-r','require "config.php"; echo json_encode([DEFAULT_USER_EMAIL,DEFAULT_USER_PASS]);'],cwd=ROOT,capture_output=True,text=True,check=True).stdout)
code,body,url=request('login.php',data={'csrf':token,'email':credentials[0],'password':credentials[1]})
check(code==200 and url.endswith('index.php'),'Original account logs into actual Apache portal')
check(('DEMONSTRATION DATA' in body if args.demo else 'Academic setup required' in body) and 'Student outcome prediction' in body,'Dashboard shows data provenance/state and predictive panel')
for path in ['index.php?college=Computing','incoming.php','enrollment.php','schedule.php','grades.php','at-risk.php','evaluations.php','setup.php','search.php?q=Example','predictions.php','assets/js/predictions.js']:
    code,body,_=request(path); check(code==200,'Apache route works: '+path)
code,body,_=request('predictions.php')
token=re.search(r'name="csrf" value="([^"]+)"',body).group(1)
code,body,_=request('api/predict.php',json_body={'csrf':token,'features':{'enrolled':6,'approved':4,'grade':12,'evaluations':8}})
result=json.loads(body)
check(code==200 and result['demonstration'] and result['outcome'] in ['Dropout','Enrolled','Graduate'],'Actual Apache API returns labeled prediction')
code,body,_=request('predictions.php',data={'csrf':token,'features[enrolled]':6,'features[approved]':4,'features[grade]':12,'features[evaluations]':8})
check(code==200 and result['outcome'] in body,'Server-rendered prediction fallback works')
for path in ['database/veritas_portal.sql','analytics/artifacts/decision_tree.joblib','analytics/artifacts/tree.json','analytics/data/data.csv','tools/database_audit.php','tests/fixtures.php','var/cleanup.lock','.git/config']:
    code,_,_=request(path); check(code in [403,404],'Private files blocked by Apache: '+path)
after=audit()
check(before['tables']==after['tables'],'HTTP login and predictions preserve every database row and schema')
check(not after['blockers'],'Final foreign key integrity verified')
report={'status':'PASS','base_url':BASE,'checks_passed':len(checks),'checks':checks,'account_fields_unchanged':True,
        'source_database':'veritas_portal','academic_records':sum(t['rows'] for n,t in before['tables'].items() if n not in ['meta','users']),'user_accounts':1,'orphan_rows':0,
        'browser_visual_check':('Not part of this HTTP suite; see analytics/phase6/PHASE6_REPORT.md for browser verification.' if args.demo else 'Not part of this HTTP suite; see analytics/qa/PHASE5_REPORT.md for browser verification.')}
(ROOT/('analytics/phase6/source-http-results.json' if args.demo else 'analytics/artifacts/source-validation.json')).write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps({k:v for k,v in report.items() if k!='checks'},indent=2))
