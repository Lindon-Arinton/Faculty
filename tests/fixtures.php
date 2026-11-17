<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/db.php';
if (!preg_match('/^bat405_verify_[0-9_]+$/D', DB_NAME)) throw new RuntimeException('Fixtures may only run in the isolated verification database.');
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$pdo = db();
if ($input['operation'] === 'create') {
    $before = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    if (count($before) !== 1 || !password_verify(DEFAULT_USER_PASS, $before[0]['password_hash'])) throw new RuntimeException('Existing account login cannot be verified with the available configured credential. Stop without changing it.');
    $result = ['original_email'=>$before[0]['email'], 'original_password'=>DEFAULT_USER_PASS, 'fixtures'=>[]];
    $pdo->beginTransaction();
    foreach (['Academic Viewer','Faculty','Unmapped role'] as $role) {
        $email = 'bat405-test-' . bin2hex(random_bytes(12)) . '@example.invalid';
        $password = bin2hex(random_bytes(20));
        $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')->execute(['Isolated test fixture',$email,password_hash($password,PASSWORD_DEFAULT),$role]);
        $result['fixtures'][] = ['id'=>(int)$pdo->lastInsertId(),'email'=>$email,'password'=>$password,'role'=>$role];
    }
    $pdo->commit();
    echo json_encode($result, JSON_THROW_ON_ERROR);
} elseif ($input['operation'] === 'revoke') {
    $stmt=$pdo->prepare("UPDATE users SET role='Unmapped role' WHERE id=? AND name='Isolated test fixture' AND email LIKE 'bat405-test-%@example.invalid'");
    $stmt->execute([(int)$input['id']]);
    if ($stmt->rowCount()!==1) throw new RuntimeException('Only a disposable fixture may be revoked');
    echo '{"status":"fixture role revoked"}';
} elseif ($input['operation'] === 'sete_denominator') {
    $x=$pdo->query('SELECT * FROM sections ORDER BY id LIMIT 1')->fetch();
    if (!$x) throw new RuntimeException('Disposable class required');
    $college=$pdo->query('SELECT college_id FROM programs WHERE id=' . (int)$x['program_id'])->fetchColumn();
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO faculty (college_id,title,first_name,last_name,`rank`,employment,email) VALUES (?,"Prof.","QA","Nonrespondent","Instructor","Full-time","qa-nonrespondent@example.invalid")')->execute([$college]);
    $fid=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO students (student_no,first_name,last_name,sex,birthdate,email,program_id,year_level,block,status,back_subjects,enrolled_at) VALUES ("2099-99999","QA","Nonrespondent","F","2005-01-01","qa-student@example.invalid",?,1,"B","Regular",0,NOW())')->execute([$x['program_id']]);
    $sid=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO sections (term_id,course_id,program_id,year_level,block,faculty_id) VALUES (?,?,?,1,"B",?)')->execute([$x['term_id'],$x['course_id'],$x['program_id'],$fid]);
    $section=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO enrollments (section_id,student_id) VALUES (?,?)')->execute([$section,$sid]);
    $pdo->commit();
    echo '{"status":"second eligible invitation with zero responses created"}';
} elseif ($input['operation'] === 'browser_state') {
    if ($pdo->query("SELECT COUNT(*) FROM terms WHERE name='Test Term'")->fetchColumn() != 1) throw new RuntimeException('Expected disposable test term');
    $pdo->exec("UPDATE terms SET ends_on='2026-11-01', grade_deadline='2026-11-02' WHERE name='Test Term'");
    $pdo->prepare('UPDATE sections SET grades_submitted_at=NULL WHERE id=?')->execute([(int)$input['section_id']]);
    echo '{"status":"disposable overdue-grade warning prepared"}';
} elseif ($input['operation'] === 'evaluation') {
    $pdo->prepare('INSERT INTO evaluation_responses (section_id,submitted_at,teaching,organization,communication,fairness,punctuality) VALUES (?,NOW(),4,5,3,4,5)')->execute([(int)$input['section_id']]);
    echo '{"status":"anonymous evaluation fixture created"}';
} elseif ($input['operation'] === 'remove') {
    $pdo->beginTransaction();
    // All academic tables were empty before these isolated workflow fixtures.
    foreach (['applicant_requirements','applicants','faculty_rating_history','evaluation_responses','risk_snapshots','interventions','enrollments','meetings','sections','students','curriculum','courses','faculty','rooms','programs','colleges','terms'] as $table) {
        $pdo->exec('DELETE FROM `' . $table . '`');
    }
    foreach ($input['ids'] as $id) {
        $pdo->prepare("DELETE FROM users WHERE id=? AND name='Isolated test fixture' AND email LIKE 'bat405-test-%@example.invalid'")->execute([(int)$id]);
    }
    $pdo->commit();
    echo '{"status":"fixtures removed"}';
} else { throw new RuntimeException('Unknown fixture operation'); }
