<?php
/** Explicit local demonstration population; dry run by default. Never calls the legacy seeder. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/catalog.php';
$options=getopt('', ['execute','rollback','confirm:']);
if (DB_HOST!=='127.0.0.1' || DB_NAME!=='veritas_portal' || getenv('BAT405_TEST_DB')) throw new RuntimeException('LOCAL veritas_portal only');
$dir='/Users/johnlukereynon/Faculty-backups/bat405-phase6-prepopulation';
$manifest=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$baseline=json_decode(file_get_contents($dir.'/audit-before.json'),true,512,JSON_THROW_ON_ERROR);
if ($manifest['status']!=='PASS' || !hash_equals($manifest['sha256'],hash_file('sha256',$manifest['backup']))) throw new RuntimeException('Verified backup required');
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$batch='BAT405_2026_V1';
$schema=[];$fingerprints=[];
function fp(PDO $p,string $t):string { $h=[];foreach($p->query('SELECT * FROM `'.$t.'`')as$r)$h[]=hash('sha256',serialize($r));sort($h);return hash('sha256',implode('',$h)); }
function structural(string $s):string {return preg_replace('/ AUTO_INCREMENT=\d+/','',$s);}
foreach($baseline['tables'] as$t=>$expected){
 $schema[$t]=$pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];
 $fingerprints[$t]=fp($pdo,$t);
}
if(!hash_equals($baseline['tables']['users']['data_sha256'],$fingerprints['users']))throw new RuntimeException('Original account changed; stop');
$marker=$pdo->query("SELECT v FROM meta WHERE k='data_batch'")->fetchColumn();
$stateFile=$dir.'/population-state.json';
if(isset($options['rollback'])){
 if($marker!==$batch || !is_file($stateFile))throw new RuntimeException('No owned batch to roll back');
 $state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
 foreach($state['after']as$t=>$h)if(!hash_equals($h,$fingerprints[$t]))throw new RuntimeException('Dataset was edited after population; automatic rollback stopped: '.$t);
 echo json_encode(['mode'=>isset($options['execute'])?'ROLLBACK':'ROLLBACK DRY RUN','target'=>DB_NAME,'remove_owned_batch'=>$state['inserted'],'accounts_modified'=>0],JSON_PRETTY_PRINT),PHP_EOL;
 if(!isset($options['execute']))exit;
 if(($options['confirm']??'')!==DB_NAME)throw new RuntimeException('--confirm=veritas_portal required');
 $lock=fopen(__DIR__.'/../var/cleanup.lock','r+');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Active requests; retry when quiet');
 $pdo->beginTransaction();try{
  foreach($state['after']as$t=>$h){$pdo->query('SELECT * FROM `'.$t.'` FOR UPDATE')->fetchAll();if(fp($pdo,$t)!==$h)throw new RuntimeException('Concurrent change');}
  foreach($baseline['proposed_delete_order'] as$t)$pdo->exec('DELETE FROM `'.$t.'`');
  $stmt=$pdo->prepare('DELETE FROM meta WHERE k=?');foreach($state['meta_keys'] as$k)$stmt->execute([$k]);
  foreach($baseline['tables']as$t=>$v)if(fp($pdo,$t)!==$v['data_sha256'])throw new RuntimeException('Rollback does not match clean snapshot');
  $pdo->commit();echo "PASS: owned demonstration rows removed; original account and clean metadata restored. Auto-increment counters are not reset.\n";
 }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
 exit;
}
if($marker){
 if($marker!==$batch||!is_file($stateFile))throw new RuntimeException('Unknown demonstration batch');
 $state=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
 foreach($state['after']as$t=>$h)if($fingerprints[$t]!==$h)throw new RuntimeException('Existing batch changed; no duplicate insertion attempted: '.$t);
 echo json_encode(['status'=>'NO_OP','batch'=>$batch,'inserted'=>0,'reason'=>'Identical batch already exists; no duplicates'],JSON_PRETTY_PRINT),PHP_EOL;exit;
}
foreach($baseline['tables']as$t=>$v)if($fingerprints[$t]!==$v['data_sha256'])throw new RuntimeException('Expected clean pre-population snapshot: '.$t);
$next=[];foreach($pdo->query('SHOW TABLE STATUS')as$t)$next[$t['Name']]=max(1,(int)$t['Auto_increment']);
$data=[];
$add=function(string$t,array$r)use(&$data,&$next):int{if(isset($next[$t])&&!in_array($t,['meta','curriculum','enrollments','interventions','risk_snapshots','faculty_rating_history','applicant_requirements']))$r=['id'=>$next[$t]++]+$r;$data[$t][]=$r;return$r['id']??0;};
$r=new Random\Randomizer(new Random\Engine\Mt19937(4052026));
$terms=[];
foreach([
 ['S1 AY2025-26','2025-07-21','2025-11-14','2025-11-18',0],
 ['S2 AY2025-26','2026-01-12','2026-05-15','2026-05-20',0],
 ['S1 AY2026-27','2026-07-20','2026-11-13','2026-11-16',1],
]as$spec)$terms[]=$add('terms',array_combine(['name','starts_on','ends_on','grade_deadline','is_current'],$spec));
$collegeNames=['Computing','Engineering','Business','Education'];
$programNames=[['Computer Science','Information Technology'],['Civil Engineering','Computer Engineering'],['Business Administration','Accountancy'],['Secondary Education','Elementary Education']];
$programCodes=[['DCS','DIT'],['DCE','DCPE'],['DBA','DAC'],['DSED','DEED']];
$first=['Ana','Miguel','Bea','Carlo','Dina','Enzo','Faye','Gio','Hana','Ivan'];
$last=['Santos','Reyes','Cruz','Garcia','Mendoza','Ramos','Torres','Flores'];
$cohorts=[];$faculty=[];$rooms=[];$courses=[];$colleges=[];$programs=[];
for($c=0;$c<4;$c++){
 $cid=$add('colleges',['code'=>'D'.($c+1),'name'=>'College of '.$collegeNames[$c],'short_name'=>$collegeNames[$c],'building'=>($c+1).' Building']);$colleges[$c]=$cid;
 for($f=0;$f<8;$f++)$faculty[$c][$f]=$add('faculty',['college_id'=>$cid,'title'=>$f%3===0?'Dr.':'Prof.','first_name'=>$first[($c+$f)%10],'last_name'=>$last[$f].' '.($c+1),'rank'=>['Instructor','Assistant Professor','Associate Professor','Professor'][$f%4],'employment'=>$f===7?'Part-time':'Full-time','email'=>"faculty.$c.$f@example.invalid"]);
 for($q=0;$q<4;$q++)$rooms[$c][$q]=$add('rooms',['college_id'=>$cid,'name'=>($c+1)." Room ".($q+1),'type'=>$q===1?'Laboratory':'Lecture','capacity'=>$q===1?30:40]);
 for($y=1;$y<=4;$y++)for($q=0;$q<4;$q++)$courses[$c][$y][$q]=$add('courses',['college_id'=>$cid,'code'=>'D'.($c+1).'-'.$y.'0'.($q+1),'title'=>$collegeNames[$c].' '.['Foundations','Applied Laboratory','Professional Practice','Research Methods'][$q].' '.$y,'units'=>3,'kind'=>$q===1?'Lab':'Lecture']);
 for($p=0;$p<2;$p++){
  $pid=$add('programs',['college_id'=>$cid,'code'=>$programCodes[$c][$p],'name'=>'BS '.$programNames[$c][$p]]);$programs[]=$pid;
  for($y=1;$y<=4;$y++){
   foreach($courses[$c][$y]as$course)$add('curriculum',['program_id'=>$pid,'course_id'=>$course,'year_level'=>$y]);
   for($n=0;$n<24;$n++){
    $number=count($cohorts)+1;$key="$c:$p:$y";
    $sid=$add('students',['student_no'=>'2026-'.str_pad((string)$number,5,'0',STR_PAD_LEFT),'first_name'=>$first[($n+$p)%10],'middle_name'=>null,'last_name'=>$last[($n+$c)%8].' '.str_pad((string)$number,3,'0',STR_PAD_LEFT),'sex'=>$n%2?'M':'F','birthdate'=>(2008-$y).'-'.sprintf('%02d',($n%12)+1).'-'.sprintf('%02d',($n%26)+1),'email'=>"student.$number@example.invalid",'program_id'=>$pid,'year_level'=>$y,'block'=>'A','status'=>$n===2?'Irregular':'Regular','back_subjects'=>$n===2?3:0,'adviser_id'=>$faculty[$c][$p],'enrolled_at'=>(2027-$y).'-07-20 09:00:00']);
    $cohorts[]=['id'=>$sid,'c'=>$c,'p'=>$p,'year'=>$y,'n'=>$n,'program'=>$pid];
   }
  }
 }
}
$sectionMap=[];$eligible=[];$riskByCollege=[];
foreach($terms as$ti=>$tid){
 $sectionIndex=0;
 for($c=0;$c<4;$c++)for($p=0;$p<2;$p++)for($y=1;$y<=($ti===2?4:3);$y++)for($q=0;$q<4;$q++){
  $state=$ti===2?($sectionIndex++%4):0;
  $fid=$faculty[$c][$q*2+$p];$base=$ti===0?'2025-11-12':($ti===1?'2026-05-13':'2026-11-13');
  $x=$add('sections',['term_id'=>$tid,'course_id'=>$courses[$c][$y][$q],'program_id'=>$programs[$c*2+$p],'year_level'=>$y,'block'=>'A','faculty_id'=>$fid,'mode'=>'Onsite','platform'=>null,'grades_updated_at'=>$state===3?null:$base.' 10:00:00','grades_submitted_at'=>$state<2?$base.' 14:00:00':null]);
  $sectionMap["$ti:$c:$p:$y:$q"]=$x;
  $slot=(($y-1)*2+$p)+5*$q;$start=8+($slot%4)*3;
  $add('meetings',['section_id'=>$x,'day'=>array_keys(WEEK_DAYS)[intdiv($slot,4)],'start_time'=>sprintf('%02d:00:00',$start),'end_time'=>sprintf('%02d:00:00',$start+3),'room_id'=>$rooms[$c][$q]]);
  foreach($cohorts as$s){
   $historicYear=$ti===2?$s['year']:$s['year']-1;
   if($s['c']!==$c||$s['p']!==$p||$historicYear!==$y)continue;
   $grade=number_format(1.25+$r->getInt(0,6)*0.25,2);
   if($s['n']===0&&$q<2)$grade='5.00';
   if($s['n']===1&&$q===0)$grade='INC';
   if($s['n']===19&&$q===3)$grade='DRP';
   $final=($state===3||($state===2&&$s['n']>=10))?null:$grade;
   $add('enrollments',['section_id'=>$x,'student_id'=>$s['id'],'midterm_grade'=>$grade,'final_grade'=>$final]);
  }
  // Deterministic anonymous response counts never exceed the 24 invitations.
  $responseCount=16+(($c*7+$p+$y+$q+$ti)%8);
  for($j=0;$j<$responseCount;$j++){
   $baseScore=[3.2,3.7,4.2,4.7,4.4,4.1,4.8,3.9][$q*2+$p];$values=[];
   foreach(array_keys(EVAL_CATEGORIES)as$col)$values[$col]=max(1,min(5,(int)floor($baseScore)+($r->getInt(0,99)<($baseScore-floor($baseScore))*100?1:0)));
   $add('evaluation_responses',['section_id'=>$x,'submitted_at'=>$base.' '.sprintf('%02d:%02d:00',8+$j%8,$j%60)]+$values);
  }
 }
}
// Histories are computed from the inserted anonymous responses, not invented summary scores.
foreach(array_slice($terms,0,2)as$tid){
 foreach($data['sections']as$x)if($x['term_id']===$tid)$eligible[$tid][$x['id']]=$x['faculty_id'];
 $acc=[];foreach($data['evaluation_responses']as$e){$fid=$eligible[$tid][$e['section_id']]??null;if(!$fid)continue;$acc[$fid]['sum']=($acc[$fid]['sum']??0)+array_sum(array_intersect_key($e,EVAL_CATEGORIES))/5;$acc[$fid]['n']=($acc[$fid]['n']??0)+1;}
 foreach($acc as$fid=>$a)$add('faculty_rating_history',['faculty_id'=>$fid,'term_id'=>$tid,'rating'=>round($a['sum']/$a['n'],2),'responses'=>$a['n']]);
}
foreach($cohorts as$s){
 if($s['n']<3){$riskByCollege[$s['c']]=($riskByCollege[$s['c']]??0)+1;if(($s['id']%3)!==0)$add('interventions',['student_id'=>$s['id'],'status'=>['Contacted','Counseling scheduled','Improving'][$s['id']%3],'last_contact_at'=>'2026-11-16 10:00:00','notes'=>'Supportive advising; review grades and learning needs.']);}
}
// Each historical point counts a deterministic simulated flagged cohort. The current point equals live rules.
foreach([['2025-08-31','2025-09-30','2025-10-31'],['2026-02-28','2026-03-31','2026-04-30'],['2026-08-31','2026-09-30','2026-10-31']]as$ti=>$months)foreach($months as$monthIndex=>$month)for($c=0;$c<4;$c++){
 $flagged=0;foreach($cohorts as$s)if($s['c']===$c && ($ti===2||$s['year']>1) && $s['n']<($ti===2?5:4)-$monthIndex)$flagged++;
 $add('risk_snapshots',['month'=>$month,'college_id'=>$colleges[$c],'at_risk'=>$flagged]);
}
for($n=0;$n<160;$n++){
 $status=['For review','Not qualified','Qualified','Pending documents','Enrolled'][$n%5];$pid=$programs[$n%8];
 $aid=$add('applicants',['applicant_no'=>'2027-'.sprintf('%04d',$n+1),'first_name'=>$first[$n%10],'middle_name'=>null,'last_name'=>$last[$n%8].' Applicant '.($n+1),'sex'=>$n%2?'M':'F','email'=>"applicant.$n@example.invalid",'program_id'=>$pid,'type'=>$n%7===0?'Transferee':'Freshman','shs_strand'=>$n%7===0?null:'STEM','prior_gwa'=>$n%7===0?round(1.25+($n%8)*.25,2):80+$n%18,'exam_date'=>$status==='For review'?null:'2026-11-10','exam_score'=>$status==='For review'?null:($status==='Not qualified'?50:75+$n%20),'status'=>$status,'submitted_at'=>'2026-11-01 09:00:00','updated_at'=>'2026-11-'.sprintf('%02d',11+$n%6).' 10:00:00']);
 foreach(REQUIREMENTS as$i=>$req)$add('applicant_requirements',['applicant_id'=>$aid,'requirement'=>$req,'submitted_at'=>$status==='Pending documents'&&$i>2?null:($status==='For review'?null:'2026-11-11 09:00:00')]);
}
$meta=['data_batch'=>$batch,'data_label'=>'Fictional academic records','as_of'=>'2026-11-17 09:30:00','intake_label'=>'intake AY 2027–2028','seeded_at'=>'2026-11-17 09:30:00'];
foreach($meta as$k=>$v)$add('meta',['k'=>$k,'v'=>$v]);
$counts=array_map('count',$data);
$report=['mode'=>isset($options['execute'])?'EXECUTE':'DRY RUN','database'=>DB_NAME,'batch'=>$batch,'inserted'=>$counts,'total'=>array_sum($counts),'accounts_modified'=>0,'automatic_seeding'=>false,'terms'=>3,'students'=>count($cohorts),'backup_sha256'=>$manifest['sha256']];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
if(!isset($options['execute']))exit;
if(($options['confirm']??'')!==DB_NAME)throw new RuntimeException('--confirm=veritas_portal required');
$lock=fopen(__DIR__.'/../var/cleanup.lock','r+');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Active requests; retry when quiet');
$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$pdo->beginTransaction();
try{
 foreach($fingerprints as$t=>$h){$pdo->query('SELECT * FROM `'.$t.'` FOR UPDATE')->fetchAll();if(fp($pdo,$t)!==$h)throw new RuntimeException('Concurrent change: '.$t);}
 foreach($data as$t=>$records){$cols=array_keys($records[0]);$stmt=$pdo->prepare('INSERT INTO `'.$t.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');foreach($records as$row)$stmt->execute(array_values($row));}
 if(fp($pdo,'users')!==$fingerprints['users'])throw new RuntimeException('Account preservation failure');
 foreach($schema as$t=>$ddl)if(structural($ddl)!==structural($pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1]))throw new RuntimeException('Unexpected structure change');
 $fk=$pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL');$fk->execute([DB_NAME]);
 foreach($fk as$f){$sql='SELECT COUNT(*) FROM `'.$f['TABLE_NAME'].'` c LEFT JOIN `'.$f['REFERENCED_TABLE_NAME'].'` p ON c.`'.$f['COLUMN_NAME'].'`=p.`'.$f['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$f['COLUMN_NAME'].'` IS NOT NULL AND p.`'.$f['REFERENCED_COLUMN_NAME'].'` IS NULL';if($pdo->query($sql)->fetchColumn())throw new RuntimeException('Orphaned relationship');}
 $after=[];foreach($fingerprints as$t=>$unused)$after[$t]=fp($pdo,$t);
 $state=['batch'=>$batch,'inserted'=>$counts,'meta_keys'=>array_keys($meta),'after'=>$after,'backup_sha256'=>$manifest['sha256']];
 // Write rollback evidence before commit; failure leaves the database untouched.
 if(file_put_contents($stateFile,json_encode($state,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot save rollback state');chmod($stateFile,0600);
 $pdo->commit();
 foreach(glob(sys_get_temp_dir().'/veritas-*.ser')?:[]as$f)@unlink($f);
 echo "PASS: demonstration batch committed; account unchanged; structures and foreign keys valid.\n";
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
