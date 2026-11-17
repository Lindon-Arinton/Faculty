<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/seeder.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$_SESSION['csrf'] = 'qa-csrf-token';
foreach ([[], 1, true, null] as $badToken) check(!verify_csrf($badToken), 'Malformed CSRF input must be rejected safely');
check(verify_csrf('qa-csrf-token'), 'Valid CSRF token rejected');
$admin = ['role'=>'University-wide access']; $viewer = ['role'=>'Academic Viewer']; $faculty = ['role'=>'Faculty'];
foreach (['academic.view','academic.manage','sete.view','sete.manage','prediction.demo'] as $permission) check(can($permission,$admin), 'Existing administrator access lost');
check(can('academic.view',$viewer) && !can('academic.manage',$viewer) && !can('sete.manage',$viewer), 'Viewer mutation allowed');
check(can('prediction.demo',$faculty) && !can('academic.view',$faculty) && !can('sete.view',$faculty), 'Unlinked faculty data access');
check(!can('academic.view',['role'=>'Unknown']) && !can('academic.manage',['role'=>'University-wide access ']), 'Unknown role grants access');
foreach (['applicant_save','applicant_delete','student_save','student_delete','section_save','section_delete','grades_save','enrollment_add','enrollment_remove','intervention_save','intervention_delete','catalog_save'] as $action) check(action_permission($action)==='academic.manage', 'Unprotected action');
check(action_permission('faculty_save')==='sete.manage' && action_permission('unknown')==='denied', 'Action mapping failure');
check(str_contains(line_chart([]),'No trend data'), 'Empty chart failed');
check(str_contains(line_chart(['Nov'=>0]),'<svg'), 'Zero chart failed');
check(str_contains(donut_chart([['Empty',0,'red']], '0', 'none'),'<svg'), 'Empty donut failed');
check(pct(0,0)==='0','Zero denominator failed');
try { seed_schema(new PDO('sqlite::memory:'), true); throw new RuntimeException('Seeder allowed'); } catch (RuntimeException $e) { check(str_contains($e->getMessage(),'disabled'),'Seeder guard failed'); }
echo "PASS: permissions, action guards, empty charts, zero denominators and disabled seeding\n";
