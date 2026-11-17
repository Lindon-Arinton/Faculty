<?php
/** CLI-only read-only calculation evidence; no actions or fixtures. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../includes/data.php';
$term=(int)current_term()['id'];
$strict=rows('SELECT COUNT(DISTINCT e.student_id) n FROM enrollments e JOIN sections s ON s.id=e.section_id WHERE s.term_id=?',[$term])[0]['n'];
$raw=rows('SELECT COUNT(*) n,AVG((r.teaching+r.organization+r.communication+r.fairness+r.punctuality)/5) mean FROM evaluation_responses r JOIN sections s ON s.id=r.section_id WHERE s.term_id=?',[$term])[0];
echo json_encode(['term'=>current_term()['label']??current_term()['name']??$term,'term_id'=>$term,'snapshot'=>date(DATE_ATOM,app_now()),'stats'=>$stats,'grades'=>$gradeCounts,'strict_term_enrolled'=>(int)$strict,'risk_types'=>array_count_values(array_column($riskAll,'type')),'urgent'=>$urgent,'sete_average_unrounded'=>$seteAverage,'sete_responses'=>$responses,'sete_invitations'=>array_sum(array_column($facultyScope,'enrolled')),'sete_raw_mean'=>(float)$raw['mean'],'sete_categories'=>$seteCategories,'warnings'=>$dashboardWarnings,'days_to_deadline'=>$daysToDeadline],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
