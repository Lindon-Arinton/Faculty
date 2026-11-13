<?php
/**
 * Saves every add / edit / delete made from the portal's forms.
 * Each form posts an `action` here; we save, leave a message for the next
 * page and send the user back to where they were.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/dataset.php';

$user = require_login();

const GRADE_VALUES = ['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '5.00', 'INC', 'DRP'];

/** A problem with what the user typed; shown to them as-is. */
class InputError extends RuntimeException {}

function back(): never
{
    $to = (string) ($_POST['return'] ?? '');
    if (!preg_match('~^[a-z-]+\.php(\?[^\s#]*)?(#[\w-]*)?$~', $to)) {
        $to = 'index.php';
    }
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

/* ---------- Reading the form ---------- */

function in(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function need(string $key, string $label, int $max = 120): string
{
    $v = in($key);
    if ($v === '') {
        throw new InputError("$label is required.");
    }
    if (mb_strlen($v) > $max) {
        throw new InputError("$label is too long (max $max characters).");
    }
    return $v;
}

function one_of(string $key, array $allowed, string $label): string
{
    $v = in($key);
    if (!in_array($v, $allowed, true)) {
        throw new InputError("Choose a valid $label.");
    }
    return $v;
}

function whole(string $key, int $min, int $max, string $label): int
{
    $v = in($key);
    if (!preg_match('/^\d+$/', $v) || (int) $v < $min || (int) $v > $max) {
        throw new InputError("$label must be a number from $min to $max.");
    }
    return (int) $v;
}

function email_in(string $key, bool $required): ?string
{
    $v = in($key);
    if ($v === '') {
        if ($required) {
            throw new InputError('Email is required.');
        }
        return null;
    }
    if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 120) {
        throw new InputError('Enter a valid email address.');
    }
    return strtolower($v);
}

function date_in(string $key, string $label): string
{
    $v = in($key);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        throw new InputError("Enter a valid $label.");
    }
    return $v;
}

function now_sql(): string
{
    return date('Y-m-d H:i:s', app_now());
}

function run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function value(string $sql, array $params = []): mixed
{
    return run($sql, $params)->fetchColumn();
}

function program_id(): int
{
    $id = value('SELECT id FROM programs WHERE code = ?', [in('program')]);
    if (!$id) {
        throw new InputError('Choose a valid program.');
    }
    return (int) $id;
}

function slug(string $text): string
{
    return preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text)) ?: 'x';
}

/** Class section in the current term, or an error. */
function section_row(int $id): array
{
    $row = run('SELECT x.*, co.code, p.code program FROM sections x JOIN courses co ON co.id = x.course_id JOIN programs p ON p.id = x.program_id WHERE x.id = ? AND x.term_id = ?', [$id, current_term()['id']])->fetch();
    if (!$row) {
        throw new InputError('That class no longer exists.');
    }
    return $row;
}

function section_label(array $row): string
{
    return $row['code'] . ' · ' . $row['program'] . ' ' . $row['year_level'] . $row['block'];
}

/** Puts a student into every class of their block this term. */
function enroll_in_block(int $studentId, int $programId, int $year, string $block): void
{
    run('INSERT IGNORE INTO enrollments (section_id, student_id)
         SELECT id, ? FROM sections WHERE term_id = ? AND program_id = ? AND year_level = ? AND block = ?',
        [$studentId, current_term()['id'], $programId, $year, $block]);
}

/* ---------- Applicants ---------- */

function applicant_save(): string
{
    $id = (int) in('id');
    $prior = in('prior_gwa');
    $score = in('exam_score');
    if ($prior !== '' && (!is_numeric($prior) || $prior < 1 || $prior > 100)) {
        throw new InputError('SHS average / GWA must be between 1 and 100.');
    }
    $data = [
        'first_name'  => need('first_name', 'First name', 60),
        'middle_name' => in('middle_name') !== '' ? mb_substr(in('middle_name'), 0, 60) : null,
        'last_name'   => need('last_name', 'Last name', 60),
        'sex'         => one_of('sex', ['F', 'M'], 'sex'),
        'email'       => email_in('email', true),
        'program_id'  => program_id(),
        'type'        => one_of('type', ['Freshman', 'Transferee'], 'applicant type'),
        'shs_strand'  => in('shs_strand') !== '' ? one_of('shs_strand', ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL'], 'SHS strand') : null,
        'prior_gwa'   => $prior !== '' ? round((float) $prior, 2) : null,
        'exam_score'  => $score !== '' ? whole('exam_score', 0, 100, 'Exam score') : null,
        'status'      => one_of('status', ['For review', 'Qualified', 'Pending documents', 'Enrolled', 'Not qualified'], 'status'),
        'updated_at'  => now_sql(),
    ];
    $name = $data['first_name'] . ' ' . $data['last_name'];

    db()->beginTransaction();
    if ($id) {
        if (!value('SELECT id FROM applicants WHERE id = ?', [$id])) {
            throw new InputError('That applicant no longer exists.');
        }
        run('UPDATE applicants SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($data))) . ' WHERE id = ?', [...array_values($data), $id]);
    } else {
        $next = (int) value('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(applicant_no, "-", -1) AS UNSIGNED)), 0) + 1 FROM applicants');
        $data['applicant_no'] = 'APP-' . date('Y', app_now()) . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        $data['submitted_at'] = now_sql();
        run('INSERT INTO applicants (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
        $id = (int) db()->lastInsertId();
    }
    if ($data['exam_score'] !== null) {
        run('UPDATE applicants SET exam_date = COALESCE(exam_date, ?) WHERE id = ?', [date('Y-m-d', app_now()), $id]);
    }
    $have = array_map('intval', is_array($_POST['req'] ?? null) ? $_POST['req'] : []);
    foreach (REQUIREMENTS as $i => $requirement) {
        $given = in_array($i, $have, true);
        run('INSERT INTO applicant_requirements (applicant_id, requirement, submitted_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE submitted_at = ' . ($given ? 'COALESCE(submitted_at, VALUES(submitted_at))' : 'NULL'),
            [$id, $requirement, $given ? now_sql() : null]);
    }
    db()->commit();
    return in('id') ? "Applicant $name updated." : "Applicant $name added as {$data['applicant_no']}.";
}

function applicant_delete(): string
{
    $row = run('SELECT applicant_no, first_name, last_name FROM applicants WHERE id = ?', [(int) in('id')])->fetch();
    if (!$row) {
        throw new InputError('That applicant no longer exists.');
    }
    run('DELETE FROM applicants WHERE id = ?', [(int) in('id')]);
    return "Applicant {$row['first_name']} {$row['last_name']} ({$row['applicant_no']}) deleted.";
}

/* ---------- Students ---------- */

function student_save(): string
{
    $id = (int) in('id');
    $data = [
        'first_name'    => need('first_name', 'First name', 60),
        'middle_name'   => in('middle_name') !== '' ? mb_substr(in('middle_name'), 0, 60) : null,
        'last_name'     => need('last_name', 'Last name', 60),
        'sex'           => one_of('sex', ['F', 'M'], 'sex'),
        'birthdate'     => date_in('birthdate', 'birthdate'),
        'email'         => email_in('email', false),
        'program_id'    => program_id(),
        'year_level'    => whole('year', 1, 4, 'Year level'),
        'block'         => one_of('block', ['A', 'B', 'C', 'D', 'E'], 'block'),
        'status'        => one_of('status', ['Regular', 'Irregular', 'On leave'], 'status'),
        'back_subjects' => whole('back_subjects', 0, 20, 'Back subjects'),
    ];
    $name = $data['first_name'] . ' ' . $data['last_name'];
    $adviser = value('SELECT adviser_id FROM students WHERE program_id = ? AND year_level = ? AND block = ? AND adviser_id IS NOT NULL LIMIT 1',
        [$data['program_id'], $data['year_level'], $data['block']]) ?: null;

    db()->beginTransaction();
    if ($id) {
        $old = run('SELECT program_id, year_level, block, email FROM students WHERE id = ?', [$id])->fetch();
        if (!$old) {
            throw new InputError('That student no longer exists.');
        }
        $data['email'] ??= $old['email'];
        $moved = [(int) $old['program_id'], (int) $old['year_level'], $old['block']] !== [$data['program_id'], $data['year_level'], $data['block']];
        if ($moved) {
            $data['adviser_id'] = $adviser;
        }
        run('UPDATE students SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($data))) . ' WHERE id = ?', [...array_values($data), $id]);
        if ($moved) {
            run('DELETE e FROM enrollments e JOIN sections x ON x.id = e.section_id WHERE e.student_id = ? AND x.term_id = ?', [$id, current_term()['id']]);
            if ($data['status'] !== 'On leave') {
                enroll_in_block($id, $data['program_id'], $data['year_level'], $data['block']);
            }
        }
        $message = "Student $name updated." . ($moved ? ' Moved to the new block’s classes.' : '');
    } else {
        $year = date('Y', app_now());
        $next = (int) value('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(student_no, "-", -1) AS UNSIGNED)), 10000) + 1 FROM students WHERE student_no LIKE ?', ["$year-%"]);
        $data['student_no'] = "$year-$next";
        $data['email'] ??= slug($data['first_name']) . '.' . slug($data['last_name']) . $next . '@student.veritas.edu.ph';
        $data['adviser_id'] = $adviser;
        $data['enrolled_at'] = now_sql();
        run('INSERT INTO students (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
        $id = (int) db()->lastInsertId();
        if ($data['status'] !== 'On leave') {
            enroll_in_block($id, $data['program_id'], $data['year_level'], $data['block']);
        }
        $classes = (int) value('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$id]);
        $message = "Student $name added as {$data['student_no']} and enrolled in $classes class" . ($classes === 1 ? '' : 'es') . '.';
    }
    db()->commit();
    return $message;
}

function student_delete(): string
{
    $row = run('SELECT student_no, first_name, last_name FROM students WHERE id = ?', [(int) in('id')])->fetch();
    if (!$row) {
        throw new InputError('That student no longer exists.');
    }
    run('DELETE FROM students WHERE id = ?', [(int) in('id')]);
    return "Student {$row['first_name']} {$row['last_name']} ({$row['student_no']}) deleted, along with their class enrollments.";
}

/* ---------- Faculty ---------- */

function faculty_save(): string
{
    $id = (int) in('id');
    $collegeId = value('SELECT id FROM colleges WHERE short_name = ?', [in('college')]);
    if (!$collegeId) {
        throw new InputError('Choose a valid college.');
    }
    $data = [
        'title'      => one_of('title', ['Prof.', 'Dr.', 'Engr.'], 'title'),
        'first_name' => need('first_name', 'First name', 60),
        'last_name'  => need('last_name', 'Last name', 60),
        'college_id' => (int) $collegeId,
        '`rank`'     => one_of('rank', ['Instructor', 'Lecturer', 'Assistant Professor', 'Associate Professor', 'Professor'], 'academic rank'),
        'employment' => one_of('employment', ['Full-time', 'Part-time'], 'employment type'),
        'email'      => email_in('email', false),
    ];
    $name = $data['title'] . ' ' . $data['first_name'] . ' ' . $data['last_name'];

    if ($id) {
        $old = value('SELECT email FROM faculty WHERE id = ?', [$id]);
        if ($old === false) {
            throw new InputError('That faculty member no longer exists.');
        }
        $data['email'] ??= $old;
        run('UPDATE faculty SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($data))) . ' WHERE id = ?', [...array_values($data), $id]);
        return "$name updated.";
    }
    $next = (int) value('SELECT COALESCE(MAX(id), 0) + 1 FROM faculty');
    $data['email'] ??= substr(slug($data['first_name']), 0, 1) . slug($data['last_name']) . $next . '@veritas.edu.ph';
    run('INSERT INTO faculty (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')', array_values($data));
    return "$name added to the faculty list.";
}

function faculty_delete(): string
{
    $id = (int) in('id');
    $row = run('SELECT title, first_name, last_name FROM faculty WHERE id = ?', [$id])->fetch();
    if (!$row) {
        throw new InputError('That faculty member no longer exists.');
    }
    db()->beginTransaction();
    $classes = run('UPDATE sections SET faculty_id = NULL WHERE faculty_id = ?', [$id])->rowCount();
    run('UPDATE students SET adviser_id = NULL WHERE adviser_id = ?', [$id]);
    run('DELETE FROM faculty_rating_history WHERE faculty_id = ?', [$id]);
    run('DELETE FROM faculty WHERE id = ?', [$id]);
    db()->commit();
    return "{$row['title']} {$row['first_name']} {$row['last_name']} deleted." . ($classes ? " $classes class" . ($classes === 1 ? ' is' : 'es are') . ' now without a teacher (TBA).' : '');
}

/* ---------- Class sections ---------- */

function section_save(): string
{
    $id = (int) in('id');
    $facultyId = in('faculty_id') !== '' ? (int) in('faculty_id') : null;
    if ($facultyId && !value('SELECT id FROM faculty WHERE id = ?', [$facultyId])) {
        throw new InputError('Choose a valid faculty member.');
    }
    $mode = one_of('mode', ['Onsite', 'Online', 'Off-campus'], 'mode');
    $platform = $mode === 'Online' ? one_of('platform', ['Google Meet', 'MS Teams'], 'online platform') : null;

    // Weekly meetings
    $meetings = [];
    if ($mode !== 'Off-campus') {
        $post = fn(string $k) => is_array($_POST[$k] ?? null) ? array_values($_POST[$k]) : [];
        [$days, $starts, $ends, $rooms] = [$post('meet_day'), $post('meet_start'), $post('meet_end'), $post('meet_room')];
        foreach ($days as $i => $day) {
            $start = substr((string) ($starts[$i] ?? ''), 0, 5);
            $end = substr((string) ($ends[$i] ?? ''), 0, 5);
            if (!isset(WEEK_DAYS[$day]) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                throw new InputError('Each meeting needs a day, a start time and an end time.');
            }
            if ($start >= $end) {
                throw new InputError('A meeting must end after it starts.');
            }
            $roomId = null;
            $roomName = trim((string) ($rooms[$i] ?? ''));
            if ($mode === 'Onsite' && $roomName !== '') {
                $roomId = value('SELECT id FROM rooms WHERE name = ?', [$roomName]);
                if (!$roomId) {
                    throw new InputError("Room $roomName does not exist.");
                }
            }
            $meetings[] = [$day, $start . ':00', $end . ':00', $roomId ?: null];
        }
        if (count($meetings) > 12) {
            throw new InputError('A class can have at most 12 weekly meetings.');
        }
    }

    db()->beginTransaction();
    if ($id) {
        $row = section_row($id);
        run('UPDATE sections SET faculty_id = ?, mode = ?, platform = ? WHERE id = ?', [$facultyId, $mode, $platform, $id]);
        $message = 'Class ' . section_label($row) . ' updated.';
    } else {
        $courseId = (int) in('course_id');
        $code = value('SELECT code FROM courses WHERE id = ?', [$courseId]);
        if (!$code) {
            throw new InputError('Choose a valid course.');
        }
        $programId = program_id();
        $year = whole('year', 1, 4, 'Year level');
        $block = one_of('block', ['A', 'B', 'C', 'D', 'E'], 'block');
        $term = current_term()['id'];
        if (value('SELECT id FROM sections WHERE term_id = ? AND course_id = ? AND program_id = ? AND year_level = ? AND block = ?', [$term, $courseId, $programId, $year, $block])) {
            throw new InputError("$code already has a class for " . in('program') . " $year$block this term.");
        }
        run('INSERT INTO sections (term_id, course_id, program_id, year_level, block, faculty_id, mode, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$term, $courseId, $programId, $year, $block, $facultyId, $mode, $platform]);
        $id = (int) db()->lastInsertId();
        $enrolled = run('INSERT INTO enrollments (section_id, student_id)
                         SELECT ?, id FROM students WHERE program_id = ? AND year_level = ? AND block = ? AND status <> "On leave"',
            [$id, $programId, $year, $block])->rowCount();
        $message = "Class $code · " . in('program') . " $year$block added with $enrolled student" . ($enrolled === 1 ? '' : 's') . ' enrolled.';
    }
    run('DELETE FROM meetings WHERE section_id = ?', [$id]);
    foreach ($meetings as $m) {
        run('INSERT INTO meetings (section_id, day, start_time, end_time, room_id) VALUES (?, ?, ?, ?, ?)', [$id, ...$m]);
    }
    db()->commit();
    return $message;
}

function section_delete(): string
{
    $row = section_row((int) in('id'));
    run('DELETE FROM sections WHERE id = ?', [$row['id']]);
    return 'Class ' . section_label($row) . ' deleted, with its meetings, roster and grades.';
}

/* ---------- Grades and class rosters ---------- */

function grades_save(): string
{
    $row = section_row((int) in('id'));
    $do = one_of('do', ['save', 'submit', 'reopen'], 'action');
    $label = section_label($row);

    if ($do === 'reopen') {
        run('UPDATE sections SET grades_submitted_at = NULL WHERE id = ?', [$row['id']]);
        return "Grades for $label reopened for editing.";
    }
    if ($row['grades_submitted_at']) {
        throw new InputError("Grades for $label are already submitted. Reopen them to make changes.");
    }

    $mid = is_array($_POST['mid'] ?? null) ? $_POST['mid'] : [];
    $fin = is_array($_POST['fin'] ?? null) ? $_POST['fin'] : [];
    $clean = function ($v) {
        $v = is_string($v) ? trim($v) : '';
        if ($v !== '' && !in_array($v, GRADE_VALUES, true)) {
            throw new InputError("“{$v}” is not a valid grade.");
        }
        return $v === '' ? null : $v;
    };
    db()->beginTransaction();
    $update = db()->prepare('UPDATE enrollments SET midterm_grade = ?, final_grade = ? WHERE section_id = ? AND student_id = ?');
    foreach (rows('SELECT student_id FROM enrollments WHERE section_id = ?', [$row['id']]) as $e) {
        $sid = $e['student_id'];
        if (array_key_exists($sid, $mid) || array_key_exists($sid, $fin)) {
            $update->execute([$clean($mid[$sid] ?? ''), $clean($fin[$sid] ?? ''), $row['id'], $sid]);
        }
    }
    run('UPDATE sections SET grades_updated_at = ? WHERE id = ?', [now_sql(), $row['id']]);
    db()->commit();

    if ($do === 'submit') {
        $total = (int) value('SELECT COUNT(*) FROM enrollments WHERE section_id = ?', [$row['id']]);
        $missing = (int) value('SELECT COUNT(*) FROM enrollments WHERE section_id = ? AND final_grade IS NULL', [$row['id']]);
        if (!$total) {
            throw new InputError('This class has no students, so there are no grades to submit.');
        }
        if ($missing) {
            throw new InputError("Draft saved, but not submitted: $missing student" . ($missing === 1 ? ' still has' : 's still have') . ' no final grade.');
        }
        run('UPDATE sections SET grades_submitted_at = ? WHERE id = ?', [now_sql(), $row['id']]);
        return "Final grades for $label submitted.";
    }
    return "Grades for $label saved as a draft.";
}

function enrollment_add(): string
{
    $row = section_row((int) in('id'));
    $no = need('student_no', 'Student number', 12);
    $student = run('SELECT id, first_name, last_name FROM students WHERE student_no = ?', [$no])->fetch();
    if (!$student) {
        throw new InputError("No student has the number $no.");
    }
    $added = run('INSERT IGNORE INTO enrollments (section_id, student_id) VALUES (?, ?)', [$row['id'], $student['id']])->rowCount();
    if (!$added) {
        throw new InputError("{$student['first_name']} {$student['last_name']} is already in this class.");
    }
    return "{$student['first_name']} {$student['last_name']} added to " . section_label($row) . '.';
}

function enrollment_remove(): string
{
    $row = section_row((int) in('id'));
    $student = run('SELECT id, first_name, last_name FROM students WHERE id = ?', [(int) in('student')])->fetch();
    $removed = $student ? run('DELETE FROM enrollments WHERE section_id = ? AND student_id = ?', [$row['id'], $student['id']])->rowCount() : 0;
    if (!$removed) {
        throw new InputError('That student is not in this class.');
    }
    return "{$student['first_name']} {$student['last_name']} removed from " . section_label($row) . '.';
}

/* ---------- At-risk interventions ---------- */

function intervention_save(): string
{
    $id = (int) in('id');
    $student = run('SELECT first_name, last_name FROM students WHERE id = ?', [$id])->fetch();
    if (!$student) {
        throw new InputError('That student no longer exists.');
    }
    $name = $student['first_name'] . ' ' . $student['last_name'];
    $status = one_of('status', INTERVENTION_STATUSES, 'intervention status');
    if ($status === 'Not contacted') {
        run('DELETE FROM interventions WHERE student_id = ?', [$id]);
        return "Intervention record for $name cleared.";
    }
    $today = date('Y-m-d', app_now());
    $date = in('last_contact') !== '' ? date_in('last_contact', 'contact date') : $today;
    if ($date > $today) {
        throw new InputError('The contact date can’t be in the future.');
    }
    $when = $date === $today ? now_sql() : $date . ' 12:00:00';
    $notes = in('notes') !== '' ? mb_substr(in('notes'), 0, 255) : null;
    run('INSERT INTO interventions (student_id, status, last_contact_at, notes) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), last_contact_at = VALUES(last_contact_at), notes = VALUES(notes)',
        [$id, $status, $when, $notes]);
    return "Intervention for $name saved: $status.";
}

function intervention_delete(): string
{
    $id = (int) in('id');
    $removed = run('DELETE FROM interventions WHERE student_id = ?', [$id])->rowCount();
    if (!$removed) {
        throw new InputError('There is no intervention record to delete.');
    }
    return 'Intervention record deleted. The student is back to “Not contacted”.';
}

/* ---------- Dispatch ---------- */

const ACTIONS = [
    'applicant_save', 'applicant_delete', 'student_save', 'student_delete', 'faculty_save', 'faculty_delete',
    'section_save', 'section_delete', 'grades_save', 'enrollment_add', 'enrollment_remove',
    'intervention_save', 'intervention_delete',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (!verify_csrf($_POST['csrf'] ?? null)) {
    flash('error', 'Your session expired. Please try again.');
    back();
}
$action = in('action');
if (!in_array($action, ACTIONS, true)) {
    flash('error', 'Unknown action.');
    back();
}

try {
    flash('ok', $action());
} catch (InputError $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', $ex->getMessage());
} catch (PDOException $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', str_contains($ex->getMessage(), 'Duplicate entry')
        ? 'That email address is already used by another record.'
        : 'The change could not be saved. Please try again.');
}
purge_cache();
back();
