#!/usr/bin/env python3
"""HTTP regression tests on the cleaned isolated verification schema only."""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
PHP = '/Applications/XAMPP/xamppfiles/bin/php'
parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
parser.add_argument('--output', required=True)
parser.add_argument('--with-model', action='store_true')
parser.add_argument('--browser-qa', action='store_true')
args = parser.parse_args()
if not re.fullmatch(r'bat405_verify_[0-9_]+', args.database):
    raise SystemExit('Integration fixtures require an isolated verification database')
env = dict(os.environ, BAT405_TEST_DB=args.database)
checks = []


def check(ok, message):
    if not ok:
        raise AssertionError(message)
    checks.append(message)


def fixture(payload):
    result = subprocess.run([PHP, 'tests/fixtures.php'], cwd=ROOT, env=env,
                            input=json.dumps(payload), capture_output=True, text=True, check=True)
    return json.loads(result.stdout)


def audit():
    result = subprocess.run([PHP, 'tools/database_audit.php', args.database], cwd=ROOT,
                            env=env, capture_output=True, text=True, check=True)
    return json.loads(result.stdout)


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, raw=None, headers=None):
        if data is not None:
            raw = urllib.parse.urlencode(data, doseq=True).encode()
        request = urllib.request.Request(base + '/' + path, data=raw, headers=headers or {})
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode()
            check(not re.search(r'(Fatal error|Warning:|Deprecated:|Notice:|Uncaught)', body), 'No PHP errors: ' + path)
            return response.status, body, response.geturl()

    def login(self, email, password, expected=200):
        code, body, _ = self.request('login.php')
        token = re.search(r'name="csrf" value="([^"]+)"', body).group(1)
        code, body, url = self.request('login.php', {'email':email,'password':password,'csrf':token})
        check(code == expected and ('Incorrect email' not in body) and not url.endswith('login.php'), 'Login outcome correct')
        return body

    @staticmethod
    def csrf(body):
        return re.search(r'name="csrf" value="([^"]+)"', body).group(1)


before = audit()
check(not before['blockers'], 'Pretest integrity audit clear')
check(all(t['rows'] == 0 for n,t in before['tables'].items() if n not in ('meta','users')), 'Academic tables start empty')
fixtures = None
with tempfile.TemporaryDirectory(prefix='bat405-http-') as temp:
    session_dir = Path(temp) / 'sessions'; session_dir.mkdir(mode=0o700)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
    base = 'http://127.0.0.1:' + str(port)
    log = open(Path(temp) / 'server.log', 'w+')
    server = subprocess.Popen([PHP,'-d','display_errors=1','-d','session.save_path='+str(session_dir),'-S','127.0.0.1:'+str(port),'-t',str(ROOT)], cwd=ROOT, env=env, stdout=log, stderr=log)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1',port), timeout=.2): break
            except OSError: time.sleep(.1)
        anon = Client()
        code, body, url = anon.request('index.php')
        check(code==200 and url.endswith('login.php'), 'Unauthenticated dashboard redirects to login')
        fixtures = fixture({'operation':'create'})
        admin = Client()
        body = admin.login(fixtures['original_email'],fixtures['original_password'])
        check('Academic setup required' in body, 'Empty dashboard shows setup state')
        for path in ['index.php','index.php?college=Computing','incoming.php','schedule.php','enrollment.php','grades.php','at-risk.php','evaluations.php','setup.php','search.php?q=Example','class.php?id=999999']:
            code,body,url = admin.request(path)
            check(code==200,'Authenticated empty page renders: '+path)
        code,body,_ = admin.request('enrollment.php')
        check('Setup required before adding records' in body,'Unavailable academic entry is disabled')
        token=admin.csrf(body)
        code,body,_=admin.request('actions.php',{'csrf':token,'action':'student_save','return':'enrollment.php'})
        check(code==200 and 'Setup required:' in body,'Direct form POST blocked without catalogs')
        code,body,_=admin.request('actions.php',{'csrf':'invalid','action':'catalog_save','return':'setup.php'})
        check('Your session expired' in body,'CSRF rejects unauthorized form submission')
        code,body,_=admin.request('actions.php',{'csrf[]':'invalid','action':'catalog_save','return[]':'invalid'})
        check(code==200 and 'Your session expired' in body,'Malformed CSRF and return arrays rejected without errors')
        malformed=Client()
        code,body,_=malformed.request('login.php',{'csrf[]':'invalid','email[]':'invalid','password[]':'invalid'})
        check(code==200 and 'Your session expired' in body,'Malformed login fields rejected without warnings')
        viewer,faculty,unknown=fixtures['fixtures']
        read=Client(); body=read.login(viewer['email'],viewer['password'])
        check('data-modal="studentModal"' not in read.request('enrollment.php')[1], 'Viewer edit button hidden')
        code,_,_=read.request('setup.php'); check(code==403,'Viewer setup denied')
        token=read.csrf(body)
        for action in ['student_save','faculty_save','catalog_save','grades_save','intervention_delete']:
            code,_,_=read.request('actions.php',{'csrf':token,'action':action})
            check(code==403,'Viewer server-side action denied: '+action)
        # Faculty and unknown roles have no institutional data access. Their landing
        # page is tested once the demo prediction page is implemented in phase 3.
        restricted_clients=[]
        for account in [faculty,unknown]:
            client=Client()
            if args.with_model:
                client.login(account['email'],account['password'],200 if account is faculty else 403)
            else:
                code,body,_=client.request('login.php')
                token=client.csrf(body)
                client.request('login.php',{'email':account['email'],'password':account['password'],'csrf':token})
            restricted_clients.append(client)
            for path in ['index.php','enrollment.php','evaluations.php','setup.php','search.php?q=Example']:
                check(client.request(path)[0]==403,'Restricted role denied institutional route: '+path)
        # Exercise real form handlers with disposable academic fixtures on the copy.
        # Their results are never used for model training or accuracy claims.
        token=admin.csrf(admin.request('setup.php')[1])
        def post_action(action, **fields):
            code,body,url=admin.request('actions.php',dict(csrf=token,action=action,**fields))
            check(code==200 and 'toast--error' not in body,'Authorized form succeeds: '+action)
            return body
        post_action('catalog_save',kind='college',name='Test College',code='TST',short_name='Test College',building='Test Building',**{'return':'setup.php'})
        setup=admin.request('setup.php')[1]
        college_id=re.search(r'name="college_id"[^>]*><option value="(\d+)"',setup).group(1)
        post_action('catalog_save',kind='program',name='Test Program',code='TSTP',college_id=college_id,**{'return':'setup.php'})
        post_action('catalog_save',kind='course',name='Test Course',code='TST101',college_id=college_id,units=3,course_kind='Lecture',**{'return':'setup.php'})
        post_action('catalog_save',kind='room',name='Test Room',college_id=college_id,room_type='Lecture',capacity=40,**{'return':'setup.php'})
        post_action('catalog_save',kind='term',name='Test Term',starts_on='2026-08-01',ends_on='2026-12-01',grade_deadline='2026-12-15',**{'return':'setup.php'})
        check('Academic setup required' not in admin.request('index.php')[1],'Setup state clears after references are configured')
        post_action('faculty_save',title='Prof.',first_name='Test',last_name='Instructor',college='Test College',rank='Instructor',employment='Full-time',email='instructor@example.invalid',**{'return':'evaluations.php'})
        post_action('student_save',first_name='Test',middle_name='',last_name='Student',sex='F',birthdate='2005-01-01',email='student@example.invalid',program='TSTP',year=1,block='A',status='Regular',back_subjects=0,**{'return':'enrollment.php'})
        schedule=admin.request('schedule.php')[1]
        course_id=re.search(r'name="course_id"[^>]*>.*?<option value="(\d+)"',schedule,re.S).group(1)
        faculty_id=re.search(r'name="faculty_id"[^>]*>.*?<option value="(\d+)"',schedule,re.S).group(1)
        post_action('section_save',id='',course_id=course_id,program='TSTP',year=1,block='A',faculty_id=faculty_id,mode='Onsite',**{'meet_day[]':['Mon'],'meet_start[]':['08:00'],'meet_end[]':['09:00'],'meet_room[]':['Test Room'],'return':'schedule.php'})
        grades=admin.request('grades.php')[1]
        section_id=re.search(r'class.php\?id=(\d+)',grades).group(1)
        class_body=admin.request('class.php?id='+section_id)[1]
        student_id=re.search(r'name="mid\[(\d+)\]"',class_body).group(1)
        post_action('grades_save',id=section_id,do='submit',**{'mid['+student_id+']':'5.00','fin['+student_id+']':'5.00','return':'class.php?id='+section_id})
        check('Test Student' in admin.request('at-risk.php')[1],'Existing grade-based risk analytics still work')
        post_action('intervention_save',id=student_id,status='Contacted',last_contact='2026-11-17',notes='Isolated integration fixture',**{'return':'at-risk.php'})
        post_action('applicant_save',first_name='Test',middle_name='',last_name='Applicant',sex='M',email='applicant@example.invalid',program='TSTP',type='Freshman',shs_strand='STEM',prior_gwa=85,exam_score=80,status='Qualified',**{'return':'incoming.php'})
        check(read.request('class.php?id='+section_id)[0]==200,'Viewer can read a configured class')
        fixture({'operation':'evaluation','section_id':section_id})
        # Raw isolated fixture creation bypasses handlers, so explicitly invalidate caches.
        subprocess.run([PHP,'-r','require "includes/dataset.php"; purge_cache();'],cwd=ROOT,env=env,check=True)
        evaluations=admin.request('evaluations.php')[1]
        check('4.2' in evaluations and 'Test Instructor' in evaluations,'SETE descriptive ratings reflect anonymous responses')
        code,csv,_=read.request('enrollment.php?export=csv')
        check(code==200 and 'Test Student' in csv,'Authorized filtered export works')
        fixture({'operation':'sete_denominator'})
        subprocess.run([PHP,'-r','require "includes/dataset.php"; purge_cache();'],cwd=ROOT,env=env,check=True)
        evaluations=admin.request('evaluations.php')[1]
        check('50%' in evaluations and '2 students invited' in evaluations,'SETE response rate includes faculty with zero responses')
        check('50%' in admin.request('index.php')[1],'Overview SETE denominator includes all eligible invitations')
        if args.browser_qa:
            fixture({'operation':'browser_state','section_id':section_id})
            subprocess.run([PHP,'-r','require "includes/dataset.php"; purge_cache();'],cwd=ROOT,env=env,check=True)
            browser_env=dict(env,QA_STAGE='fixtures',QA_BASE_URL=base+'/',QA_CLASS_ID=section_id)
            subprocess.run(['node','tests/visual_qa.cjs'],cwd=ROOT,env=browser_env,check=True,stdout=subprocess.DEVNULL)
        if args.with_model:
            for client in [admin, read]:
                code,body,_=client.request('predictions.php')
                check(code==200 and 'Demonstration' in body,'Prediction page labeled demonstration')
                token=client.csrf(body)
                payload={'csrf':token,'features':{'enrolled':6,'approved':4,'grade':12,'evaluations':8}}
                code,body,_=client.request('api/predict.php',raw=json.dumps(payload).encode(),headers={'Content-Type':'application/json'})
                prediction=json.loads(body)
                check(code==200 and prediction['outcome'] in ('Dropout','Enrolled','Graduate'),'Authenticated prediction API returns class')
                check(prediction['demonstration'] is True and len(prediction['probabilities'])==3,'Prediction provenance explicit')
                check(client.request('api/predict.php')[0]==405,'Prediction endpoint enforces POST')
                check(client.request('api/predict.php',raw=b'{}',headers={'Content-Type':'text/plain'})[0]==415,'Prediction endpoint enforces JSON')
                check(client.request('api/predict.php',raw=b'{',headers={'Content-Type':'application/json'})[0]==400,'Malformed JSON rejected')
                extra=dict(payload,student_id='do-not-send-identities')
                check(client.request('api/predict.php',raw=json.dumps(extra).encode(),headers={'Content-Type':'application/json'})[0]==422,'Student identity payload rejected')
                extra={'csrf':token,'features':dict(payload['features'],second_semester_grade=15)}
                check(client.request('api/predict.php',raw=json.dumps(extra).encode(),headers={'Content-Type':'application/json'})[0]==422,'Second-semester input rejected')
                check(client.request('api/predict.php',raw=b'x'*4097,headers={'Content-Type':'application/json'})[0]==413,'Oversized request rejected')
                payload['features']['grade']=500
                check(client.request('api/predict.php',raw=json.dumps(payload).encode(),headers={'Content-Type':'application/json'})[0]==422,'Invalid model input rejected')
                payload['csrf']='invalid'
                check(client.request('api/predict.php',raw=json.dumps(payload).encode(),headers={'Content-Type':'application/json'})[0]==403,'Prediction CSRF required')
            faculty_client,unknown_client=restricted_clients
            body=faculty_client.request('predictions.php')[1]
            faculty_token=faculty_client.csrf(body)
            code,_,_=faculty_client.request('api/predict.php',raw=json.dumps({'csrf':faculty_token,'features':{'enrolled':6,'approved':4,'grade':12,'evaluations':8}}).encode(),headers={'Content-Type':'application/json'})
            check(code==200,'Unlinked faculty may use public demonstration prediction')
            check(unknown_client.request('api/predict.php',raw=b'{}',headers={'Content-Type':'application/json'})[0]==403,'Unknown role prediction API denied')
            check(anon.request('api/predict.php',raw=b'{}',headers={'Content-Type':'application/json'})[0]==401,'Unauthenticated prediction API denied')
        fixture({'operation':'revoke','id':viewer['id']})
        check(read.request('index.php')[0]==403,'Persisted role revocation overrides existing session permissions')
        # Login path never changes any original account field.
        fixture({'operation':'remove','ids':[f['id'] for f in fixtures['fixtures']]})
        fixtures=None
        after=audit()
        check(before['tables']['users']['data_sha256']==after['tables']['users']['data_sha256'],'Original account fields unchanged after login tests')
        check(not after['blockers'] and all(t['rows']==0 for n,t in after['tables'].items() if n not in ('meta','users')), 'No academic data recreated; foreign keys valid')
        check(after['tables']['users']['rows']==1,'Only original account remains in verification copy')
        report={'status':'PASS','database':args.database,'checks_passed':len(checks),'checks':checks,'account_fields_unchanged':True,'with_model':args.with_model}
        Path(args.output).write_text(json.dumps(report,indent=2)+'\n')
        print(json.dumps({k:v for k,v in report.items() if k!='checks'},indent=2))
    finally:
        if fixtures:
            fixture({'operation':'remove','ids':[f['id'] for f in fixtures['fixtures']]})
        server.terminate(); server.wait(timeout=10); log.close()
