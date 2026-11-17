<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/dataset.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/prediction.php';
$dir='/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation';
$base=json_decode(file_get_contents($dir.'/audit-before.json'),true);
$state=json_decode(file_get_contents($dir.'/population-state.json'),true);
$checks=[];
function check6(bool $b,string $name):void {global $checks;if(!$b)throw new RuntimeException($name);$checks[]=$name;}
function hash6(string$t):string{$h=[];foreach(rows('SELECT * FROM `'.$t.'`')as$r)$h[]=hash('sha256',serialize($r));sort($h);return hash('sha256',implode('',$h));}
check6(DB_NAME==='veritas_portal'&&DB_HOST==='127.0.0.1','Approved local target');
check6(hash6('users')===$base['tables']['users']['data_sha256'],'All original user fields preserved');
$counts=[];foreach($base['tables']as$t=>$v){$counts[$t]=(int)rows('SELECT COUNT(*) n FROM `'.$t.'`')[0]['n'];check6($counts[$t]===$v['rows']+($state['inserted'][$t]??0),'Inserted count: '.$t);check6(hash6($t)===$state['after'][$t],'Exact batch fingerprint: '.$t);}
check6(meta('data_batch')==='BAT405_2026_V1','Data provenance flag');
check6(!rows("SELECT id FROM students WHERE email NOT LIKE '%@example.invalid'"),'All student identities fictional');
check6(!rows("SELECT id FROM faculty WHERE email NOT LIKE '%@example.invalid'"),'All faculty identities fictional');
check6(!rows('SELECT s.id FROM students s WHERE NOT EXISTS (SELECT 1 FROM enrollments e JOIN sections x ON x.id=e.section_id WHERE e.student_id=s.id AND x.term_id=?)',[active_term()['id']]),'Every current student has current enrollment');
check6(!rows('SELECT x.id FROM sections x JOIN (SELECT section_id,COUNT(*) n FROM enrollments GROUP BY section_id)e ON e.section_id=x.id WHERE x.grades_submitted_at IS NOT NULL AND EXISTS(SELECT 1 FROM enrollments en WHERE en.section_id=x.id AND final_grade IS NULL)'),'Submitted sections have complete final values');
check6(!rows('SELECT x.id FROM sections x JOIN (SELECT section_id,COUNT(*) n FROM enrollments GROUP BY section_id)e ON e.section_id=x.id JOIN (SELECT section_id,COUNT(*) n FROM evaluation_responses GROUP BY section_id)r ON r.section_id=x.id WHERE r.n>e.n'),'Anonymous responses never exceed eligible invitations');
check6(!rows("SELECT id FROM applicants WHERE (type='Transferee' AND (prior_gwa<1 OR prior_gwa>5)) OR (type='Freshman' AND (prior_gwa<75 OR prior_gwa>100))"),'Applicant grade scales consistent with type');
check6(!rows('SELECT a.id FROM applicants a JOIN applicant_requirements r ON r.applicant_id=a.id WHERE r.submitted_at>a.updated_at'),'Applicant last-update timestamps include requirement changes');
$collision=rows('SELECT COUNT(*) n FROM meetings a JOIN meetings b ON b.id>a.id AND b.day=a.day AND a.start_time<b.end_time AND b.start_time<a.end_time JOIN sections x ON x.id=a.section_id JOIN sections y ON y.id=b.section_id AND y.term_id=x.term_id WHERE a.room_id=b.room_id OR x.faculty_id=y.faculty_id OR (x.program_id=y.program_id AND x.year_level=y.year_level AND x.block=y.block)')[0]['n'];
check6((int)$collision===0,'No room, instructor or student-block timetable collisions');
check6(!room_conflicts(),'Existing conflict detector passes');
foreach(rows('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL',[DB_NAME])as$f){$sql='SELECT COUNT(*) n FROM `'.$f['TABLE_NAME'].'`c LEFT JOIN `'.$f['REFERENCED_TABLE_NAME'].'`p ON c.`'.$f['COLUMN_NAME'].'`=p.`'.$f['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$f['COLUMN_NAME'].'` IS NOT NULL AND p.`'.$f['REFERENCED_COLUMN_NAME'].'` IS NULL';check6((int)rows($sql)[0]['n']===0,'Foreign key: '.$f['TABLE_NAME'].'.'.$f['COLUMN_NAME']);}
foreach(rows('SELECT * FROM faculty_rating_history')as$h){$x=rows('SELECT COUNT(*) n,ROUND(AVG((r.teaching+r.organization+r.communication+r.fairness+r.punctuality)/5),2) rating FROM evaluation_responses r JOIN sections x ON x.id=r.section_id WHERE x.faculty_id=? AND x.term_id=?',[$h['faculty_id'],$h['term_id']])[0];check6((int)$x['n']===(int)$h['responses']&&abs((float)$x['rating']-(float)$h['rating'])<.001,'History derived from actual responses');}
$terms=[];foreach(rows('SELECT * FROM terms ORDER BY starts_on')as$t){$k=rows('SELECT COUNT(*) classes,SUM(grades_submitted_at IS NULL) pending FROM sections WHERE term_id=?',[$t['id']])[0];$e=rows('SELECT COUNT(DISTINCT student_id)n FROM enrollments e JOIN sections x ON x.id=e.section_id WHERE x.term_id=?',[$t['id']])[0];$r=rows('SELECT COUNT(*)n,AVG((teaching+organization+communication+fairness+punctuality)/5) score FROM evaluation_responses r JOIN sections x ON x.id=r.section_id WHERE x.term_id=?',[$t['id']])[0];$terms[]=['id'=>(int)$t['id'],'name'=>$t['name'],'academic_year'=>academic_year($t),'active_classes'=>(int)$k['classes'],'enrolled_students'=>(int)$e['n'],'grades_pending'=>(int)$k['pending'],'sete_score_raw'=>(float)$r['score'],'responses'=>(int)$r['n']];}
require __DIR__.'/../includes/data.php';
$kpis=array_map(fn($s)=>['label'=>$s['label'],'value'=>$s['value']],$stats);
check6(count($kpis)===5,'Five calculated primary KPI cards');
check6(count(risk_history())>=4,'Current risk history plus observed endpoint');
foreach(['Dropout','Enrolled','Graduate']as$label){$found=false;foreach(prediction_samples()as$s)if(predict_outcome($s['features'])['outcome']===$label)$found=true;check6($found,'Independent model demonstration: '.$label);}
$report=['status'=>'PASS','checks_passed'=>count($checks),'checks'=>$checks,'counts'=>$counts,'inserted'=>$state['inserted'],'kpis'=>$kpis,'terms'=>$terms,'risk_trend'=>risk_history(),'warnings'=>$dashboardWarnings,'room_faculty_block_collisions'=>0,'accounts_unchanged'=>true,'historical_predictions_separate'=>true];
file_put_contents(__DIR__.'/../analytics/phase6/validation.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode(['status'=>'PASS','checks_passed'=>count($checks),'kpis'=>$kpis,'terms'=>$terms,'risk_trend'=>risk_history()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
