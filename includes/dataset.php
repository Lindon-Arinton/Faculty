<?php
/**
 * Read-side queries for the dashboard. Each function returns plain arrays
 * that the pages filter, sort and paginate. Results are memoized per request.
 */
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/db.php';

function rows(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function meta(string $key, ?string $default = null): ?string
{
    static $all = null;
    $all ??= array_column(rows('SELECT k, v FROM meta'), 'v', 'k');
    return $all[$key] ?? $default;
}

/**
 * Changes whenever any table in the database is written to, so cached
 * results are rebuilt as soon as the records change.
 */
function data_version(): string
{
    static $version = null;
    return $version ??= md5(json_encode(rows(
        'SELECT MAX(UPDATE_TIME) u, COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [DB_NAME]
    )) . DB_NAME . current_term()['id'] . filemtime(__FILE__));
}

/** Stores a computed list in the temp folder until the data changes. */
function cached(string $name, callable $build): array
{
    $dir = sys_get_temp_dir();
    $file = "$dir/veritas-$name-" . data_version() . '.ser';
    if (is_file($file)) {
        $data = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if (is_array($data)) {
            return $data;
        }
    }
    $data = $build();
    foreach (glob("$dir/veritas-$name-*.ser") ?: [] as $old) {
        @unlink($old);
    }
    @file_put_contents($file, serialize($data), LOCK_EX);
    return $data;
}

/** Drops every cached list. Called after any record is added, changed or deleted. */
function purge_cache(): void
{
    foreach (glob(sys_get_temp_dir() . '/veritas-*.ser') ?: [] as $file) {
        @unlink($file);
    }
}

/**
 * "Now" for the dashboard. The sample records are a snapshot, so this is the
 * snapshot time. With live data, delete the as_of row in `meta` and this
 * becomes the real time.
 */
function app_now(): int
{
    $asOf = meta('as_of');
    return $asOf ? strtotime($asOf) : time();
}

function academic_year(array $term): string
{
    $year=(int)substr($term['starts_on'],0,4); if ((int)substr($term['starts_on'],5,2)<6) $year--;
    return $year . '-' . ($year+1);
}
function active_term(): array
{
    static $term=null;
    return $term ??= rows('SELECT * FROM terms WHERE is_current=1 LIMIT 1')[0]
        ?? ['id'=>0,'name'=>'No active term','starts_on'=>date('Y-m-d'),'ends_on'=>date('Y-m-d'),'grade_deadline'=>date('Y-m-d')];
}
function current_term(): array
{
    static $term=null;
    if($term!==null)return $term;
    $terms=rows('SELECT * FROM terms ORDER BY starts_on DESC');
    $requested=isset($_GET['term'])&&is_string($_GET['term'])&&ctype_digit($_GET['term'])?(int)$_GET['term']:($_SESSION['academic_term']??active_term()['id']);
    $ay=isset($_GET['ay'])&&is_string($_GET['ay'])?$_GET['ay']:'';
    $scope=array_values(array_filter($terms,fn($t)=>$ay===''||academic_year($t)===$ay));
    foreach($scope as$t)if((int)$t['id']===(int)$requested)$term=$t;
    $term??=$scope[0]??active_term();
    if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['academic_term']=(int)$term['id'];
    return $term;
}
function is_historical_term(): bool
{
    return (int)current_term()['id']!==(int)active_term()['id'];
}

function previous_term(): ?array
{
    static $term = false;
    if ($term === false) {
        $term = rows('SELECT * FROM terms WHERE starts_on < ? ORDER BY starts_on DESC LIMIT 1', [current_term()['starts_on']])[0] ?? null;
    }
    return $term;
}

function intake_label(): string
{
    return meta('intake_label', 'Incoming academic year');
}

/** [short name => ['code', 'name', 'building', 'programs' => [code => name], 'rooms' => [names]]] */
function colleges(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = [];
    foreach (rows('SELECT id, code, name, short_name, building FROM colleges ORDER BY id') as $c) {
        $list[$c['short_name']] = ['id' => (int) $c['id'], 'code' => $c['code'], 'name' => $c['name'], 'building' => $c['building'], 'programs' => [], 'rooms' => []];
    }
    $byId = array_column(array_map(fn($k, $v) => ['id' => $v['id'], 'k' => $k], array_keys($list), $list), 'k', 'id');
    foreach (rows('SELECT college_id, code, name FROM programs ORDER BY id') as $p) {
        $list[$byId[$p['college_id']]]['programs'][$p['code']] = $p['name'];
    }
    foreach (rows('SELECT college_id, name FROM rooms ORDER BY id') as $rm) {
        $list[$byId[$rm['college_id']]]['rooms'][] = $rm['name'];
    }
    return $list;
}

function person_name(string $first, ?string $middle, string $last): string
{
    return $first . ($middle ? ' ' . mb_substr($middle, 0, 1) . '.' : '') . ' ' . $last;
}

/** "BSCS 3A" -> 3 */
function section_year(string $section): int
{
    return preg_match('/(\d)[A-Z]$/', $section, $m) ? (int) $m[1] : 1;
}

function year_label(int $year): string
{
    return match ($year) { 1 => '1st year', 2 => '2nd year', 3 => '3rd year', default => $year . 'th year' };
}

/* ------------------------------------------------------------------ */
/* Students                                                            */
/* ------------------------------------------------------------------ */

function students(): array
{
    static $list = null;
    return $list ??= cached('students', 'build_students');
}

function build_students(): array
{
    $term = current_term()['id'];
    $list = [];
    foreach (rows(
        'SELECT s.id, s.student_no, s.first_name, s.middle_name, s.last_name, s.sex, s.birthdate, s.email, CASE WHEN ?=1 THEN COALESCE(u.historical_year,s.year_level) ELSE s.year_level END year_level, CASE WHEN ?=1 THEN COALESCE(u.historical_block,s.block) ELSE s.block END block, s.status, s.back_subjects,
                UNIX_TIMESTAMP(s.enrolled_at) enrolled_at, p.code program, p.name program_name, c.short_name college,
                CONCAT(f.title, " ", f.first_name, " ", f.last_name) adviser,
                COALESCE(u.units, 0) units, COALESCE(u.subjects, 0) subjects
         FROM students s
         JOIN programs p ON p.id = s.program_id
         JOIN colleges c ON c.id = p.college_id
         LEFT JOIN faculty f ON f.id = s.adviser_id
         LEFT JOIN (
             SELECT e.student_id, SUM(co.units) units, COUNT(*) subjects, MIN(x.year_level) historical_year, MIN(x.block) historical_block
             FROM enrollments e JOIN sections x ON x.id = e.section_id JOIN courses co ON co.id = x.course_id
             WHERE x.term_id = ? GROUP BY e.student_id
         ) u ON u.student_id = s.id
         WHERE u.student_id IS NOT NULL OR ? = 1
         ORDER BY s.last_name, s.first_name, s.middle_name',
        [is_historical_term()?1:0, is_historical_term()?1:0, $term, is_historical_term()?0:1]
    ) as $s) {
        $list[] = [
            'sid'          => (int) $s['id'],
            'id'           => $s['student_no'],
            'name'         => person_name($s['first_name'], $s['middle_name'], $s['last_name']),
            'first'        => $s['first_name'],
            'middle'       => (string) $s['middle_name'],
            'last'         => $s['last_name'],
            'sex'          => $s['sex'],
            'birthdate'    => $s['birthdate'],
            'block'        => $s['block'],
            'search'       => $s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name'] . ' ' . $s['last_name'] . ', ' . $s['first_name'],
            'email'        => $s['email'],
            'program'      => $s['program'],
            'program_name' => $s['program_name'],
            'college'      => $s['college'],
            'year'         => (int) $s['year_level'],
            'section'      => $s['program'] . ' ' . $s['year_level'] . $s['block'],
            'status'       => $s['status'],
            'back'         => (int) $s['back_subjects'],
            'units'        => (int) $s['units'],
            'subjects'     => (int) $s['subjects'],
            'adviser'      => $s['adviser'],
            'enrolled_at'  => $s['enrolled_at'] ? (int) $s['enrolled_at'] : null,
        ];
    }
    return $list;
}

function student_by_id(string $studentNo): ?array
{
    static $index = null;
    $index ??= array_column(students(), null, 'id');
    return $index[$studentNo] ?? null;
}

/* ------------------------------------------------------------------ */
/* At-risk students (derived from current-term grades)                 */
/* ------------------------------------------------------------------ */

function at_risk(): array
{
    static $list = null;
    return $list ??= cached('at_risk', 'build_at_risk');
}

function build_at_risk(): array
{
    $byId = array_column(students(), null, 'sid');
    $list = [];
    foreach (rows(
        'SELECT s.id, COALESCE(g.failing, 0) failing, COALESCE(g.inc, 0) inc, g.gwa, s.status, s.back_subjects,
                i.status intervention, UNIX_TIMESTAMP(i.last_contact_at) last_contact, i.notes
         FROM students s
         LEFT JOIN (
             SELECT e.student_id,
                    SUM(COALESCE(e.final_grade, e.midterm_grade) = "5.00") failing,
                    SUM(COALESCE(e.final_grade, e.midterm_grade) = "INC") inc,
                    AVG(CASE WHEN COALESCE(e.final_grade, e.midterm_grade) REGEXP "^[0-9]" THEN COALESCE(e.final_grade, e.midterm_grade) + 0 END) gwa
             FROM enrollments e JOIN sections x ON x.id = e.section_id
             WHERE x.term_id = ?
             GROUP BY e.student_id
         ) g ON g.student_id = s.id
         LEFT JOIN interventions i ON i.student_id = s.id
         WHERE g.failing > 0 OR g.inc > 0 OR (s.status = "Irregular" AND s.back_subjects >= ? AND ? = 1)',
        [current_term()['id'], RISK_BACK_SUBJECTS, is_historical_term()?0:1]
    ) as $row) {
        if(!isset($byId[(int)$row['id']]))continue;
        $s = $byId[(int) $row['id']];
        $failing = (int) $row['failing'];
        $inc = (int) $row['inc'];
        $back = is_historical_term()?0:(int) $row['back_subjects'];
        $type = $failing ? 'Failing' : ($inc ? 'INC' : 'Behind curriculum');
        $details = [];
        if ($failing) $details[] = $failing . ' failing subject' . ($failing > 1 ? 's' : '');
        if ($inc) $details[] = $inc . ' incomplete' . ($inc > 1 ? 's' : '');
        if ($back >= RISK_BACK_SUBJECTS) $details[] = $back . ' back subjects';

        $list[] = $s + [
            'type'         => $type,
            'detail'       => implode(' · ', $details),
            'failing'      => $failing,
            'inc'          => $inc,
            'gwa'          => $row['gwa'] !== null ? round((float) $row['gwa'], 2) : null,
            'priority'     => ($failing >= 2 || ($failing && $inc) || $back >= 5) ? 'Urgent' : 'Monitor',
            'intervention' => is_historical_term() ? 'Not archived for term' : ($row['intervention'] ?? 'Not contacted'),
            'last_contact' => !is_historical_term() && $row['last_contact'] ? (int) $row['last_contact'] : null,
            'notes'        => $row['notes'],
        ];
    }
    usort($list, fn($a, $b) => [$a['priority'] !== 'Urgent', -$a['failing'], $a['name']] <=> [$b['priority'] !== 'Urgent', -$b['failing'], $b['name']]);
    return $list;
}

function at_risk_ids(): array
{
    static $ids = null;
    return $ids ??= array_flip(array_column(at_risk(), 'id'));
}

/** Coverage check: no grades is unavailable evidence, not evidence of zero risk. */
function grade_evidence_count(string $college = ''): int
{
    static $counts = [];
    if (isset($counts[$college])) return $counts[$college];
    $params = [current_term()['id']];
    $where = '';
    if ($college !== '') { $where = ' AND c.short_name = ?'; $params[] = $college; }
    return $counts[$college] = (int) rows('SELECT COUNT(*) n FROM enrollments e
        JOIN sections x ON x.id=e.section_id JOIN programs p ON p.id=x.program_id
        JOIN colleges c ON c.id=p.college_id
        WHERE x.term_id=? AND (e.midterm_grade IS NOT NULL OR e.final_grade IS NOT NULL)' . $where, $params)[0]['n'];
}

/** Month-end at-risk counts for the current term, plus the live count for this month. */
function risk_history(string $college = ''): array
{
    $params = [current_term()['starts_on'],current_term()['ends_on']];
    $where = 'WHERE r.month BETWEEN ? AND ?';
    if ($college !== '') {
        $where .= ' AND c.short_name = ?';
        $params[] = $college;
    }
    $out = [];
    foreach (rows("SELECT r.month, SUM(r.at_risk) n FROM risk_snapshots r JOIN colleges c ON c.id = r.college_id $where GROUP BY r.month ORDER BY r.month", $params) as $m) {
        $out[date('M Y', strtotime($m['month']))] = (int) $m['n'];
    }
    $live = count($college === '' ? at_risk() : array_filter(at_risk(), fn($r) => $r['college'] === $college));
    if ($live > 0 || grade_evidence_count($college) > 0) $out[date('M Y', is_historical_term()?strtotime(current_term()['ends_on']):app_now())] = $live;
    return $out;
}

/* ------------------------------------------------------------------ */
/* Applicants                                                          */
/* ------------------------------------------------------------------ */

function applicants(): array
{
    static $list = null;
    return $list ??= cached('applicants', 'build_applicants');
}

function build_applicants(): array
{
    $missing = [];
    foreach (rows('SELECT applicant_id, requirement FROM applicant_requirements WHERE submitted_at IS NULL') as $m) {
        $missing[$m['applicant_id']][] = $m['requirement'];
    }
    $list = [];
    foreach (rows(
        'SELECT a.id, a.applicant_no, a.first_name, a.middle_name, a.last_name, a.sex, a.email, a.type, a.shs_strand, a.prior_gwa, a.exam_score, a.status,
                UNIX_TIMESTAMP(a.submitted_at) submitted, UNIX_TIMESTAMP(a.updated_at) updated, p.code program, p.name program_name, c.short_name college
         FROM applicants a JOIN programs p ON p.id = a.program_id JOIN colleges c ON c.id = p.college_id
         ORDER BY a.updated_at DESC, a.id DESC'
    ) as $a) {
        $list[] = [
            'aid'          => (int) $a['id'],
            'id'           => $a['applicant_no'],
            'name'         => person_name($a['first_name'], $a['middle_name'], $a['last_name']),
            'first'        => $a['first_name'],
            'middle'       => (string) $a['middle_name'],
            'last'         => $a['last_name'],
            'sex'          => $a['sex'],
            'email'        => $a['email'],
            'program'      => $a['program'],
            'program_name' => $a['program_name'],
            'college'      => $a['college'],
            'status'       => $a['status'],
            'type'         => $a['type'],
            'strand'       => $a['shs_strand'],
            'prior'        => $a['prior_gwa'] !== null ? (float) $a['prior_gwa'] : null,
            'score'        => $a['exam_score'] !== null ? (int) $a['exam_score'] : null,
            'missing'      => $missing[$a['id']] ?? [],
            'submitted'    => (int) $a['submitted'],
            'updated'      => (int) $a['updated'],
        ];
    }
    return $list;
}

/* ------------------------------------------------------------------ */
/* Faculty + evaluations                                               */
/* ------------------------------------------------------------------ */

function faculty(): array
{
    static $list = null;
    return $list ??= cached('faculty', 'build_faculty');
}

function build_faculty(): array
{
    $term = current_term()['id'];
    $prev = previous_term()['id'] ?? 0;
    $cols = implode(', ', array_map(fn($c) => "AVG(r.$c) $c", array_keys(EVAL_CATEGORIES)));
    $list = [];
    foreach (rows(
        "SELECT f.id, f.title, f.first_name, f.last_name, f.email, f.rank, f.employment, c.short_name college,
                COALESCE(sec.sections, 0) sections, COALESCE(sec.units, 0) units, COALESCE(inv.invited, 0) invited,
                ev.responses, ev.teaching, ev.organization, ev.communication, ev.fairness, ev.punctuality,
                h.rating previous
         FROM faculty f
         JOIN colleges c ON c.id = f.college_id
         LEFT JOIN (SELECT x.faculty_id, COUNT(*) sections, SUM(co.units) units FROM sections x JOIN courses co ON co.id = x.course_id WHERE x.term_id = ? GROUP BY x.faculty_id) sec ON sec.faculty_id = f.id
         LEFT JOIN (SELECT x.faculty_id, COUNT(*) invited FROM enrollments e JOIN sections x ON x.id = e.section_id WHERE x.term_id = ? AND x.mode <> 'Off-campus' GROUP BY x.faculty_id) inv ON inv.faculty_id = f.id
         LEFT JOIN (SELECT x.faculty_id, COUNT(*) responses, $cols FROM evaluation_responses r JOIN sections x ON x.id = r.section_id WHERE x.term_id = ? GROUP BY x.faculty_id) ev ON ev.faculty_id = f.id
         LEFT JOIN faculty_rating_history h ON h.faculty_id = f.id AND h.term_id = ?
         ORDER BY f.last_name, f.first_name",
        [$term, $term, $term, $prev]
    ) as $f) {
        $categories = [];
        foreach (EVAL_CATEGORIES as $col => $label) {
            $categories[$label] = $f[$col] !== null ? round((float) $f[$col], 2) : 0.0;
        }
        $rating = $f['responses'] ? round(array_sum($categories) / count($categories), 2) : 0.0;
        $list[] = [
            'fid'        => (int) $f['id'],
            'name'       => $f['title'] . ' ' . $f['first_name'] . ' ' . $f['last_name'],
            'title'      => $f['title'],
            'first'      => $f['first_name'],
            'last'       => $f['last_name'],
            'email'      => $f['email'],
            'college'    => $f['college'],
            'rank'       => $f['rank'],
            'employment' => $f['employment'],
            'sections'   => (int) $f['sections'],
            'units'      => (int) $f['units'],
            'responses'  => (int) $f['responses'],
            'enrolled'   => (int) $f['invited'],
            'rating'     => $rating,
            'previous'   => $f['previous'] !== null ? (float) $f['previous'] : $rating,
            'has_previous' => $f['previous'] !== null,
            'categories' => $categories,
        ];
    }
    return $list;
}

/** Faculty who received at least one evaluation this term. */
function evaluated_faculty(): array
{
    return array_values(array_filter(faculty(), fn($f) => $f['responses'] > 0));
}

/* ------------------------------------------------------------------ */
/* Class sections, meetings and rooms                                  */
/* ------------------------------------------------------------------ */

function minutes(string $time): int
{
    [$h, $m] = array_map('intval', explode(':', $time));
    return $h * 60 + $m;
}

function classes(): array
{
    static $list = null;
    return $list ??= cached('classes', 'build_classes');
}

function build_classes(): array
{
    $term = current_term()['id'];

    $meetings = [];
    foreach (rows(
        'SELECT m.section_id, m.day, m.start_time, m.end_time, r.name room
         FROM meetings m JOIN sections x ON x.id = m.section_id LEFT JOIN rooms r ON r.id = m.room_id
         WHERE x.term_id = ? ORDER BY FIELD(m.day, "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"), m.start_time',
        [$term]
    ) as $m) {
        $meetings[$m['section_id']][] = ['day' => $m['day'], 'start' => minutes($m['start_time']), 'end' => minutes($m['end_time']), 'room' => $m['room']];
    }

    $list = [];
    foreach (rows(
        'SELECT x.id, x.course_id, x.faculty_id, co.code, co.title, co.units, co.kind, p.code program, x.year_level, x.block, c.short_name college,
                CONCAT(f.title, " ", f.first_name, " ", f.last_name) faculty, x.mode, x.platform,
                UNIX_TIMESTAMP(x.grades_updated_at) updated, UNIX_TIMESTAMP(x.grades_submitted_at) submitted,
                COUNT(e.student_id) students, SUM(e.final_grade IS NOT NULL) encoded
         FROM sections x
         JOIN courses co ON co.id = x.course_id
         JOIN colleges c ON c.id = co.college_id
         JOIN programs p ON p.id = x.program_id
         LEFT JOIN faculty f ON f.id = x.faculty_id
         LEFT JOIN enrollments e ON e.section_id = x.id
         WHERE x.term_id = ?
         GROUP BY x.id
         ORDER BY co.code, p.code, x.year_level, x.block',
        [$term]
    ) as $x) {
        $students = (int) $x['students'];
        $encoded = (int) $x['encoded'];
        $grade = $x['submitted'] ? 'Submitted' : ($encoded > 0 ? 'In progress' : 'Not started');
        $section = $x['program'] . ' ' . $x['year_level'] . $x['block'];
        $mtgs = $meetings[$x['id']] ?? [];
        $list[] = [
            'id'       => (int) $x['id'],
            'key'      => $x['code'] . ' · ' . $section,
            'code'     => $x['code'],
            'title'    => $x['title'],
            'units'    => (int) $x['units'],
            'kind'     => $x['kind'],
            'section'  => $section,
            'course_id'  => (int) $x['course_id'],
            'faculty_id' => $x['faculty_id'] !== null ? (int) $x['faculty_id'] : null,
            'program'  => $x['program'],
            'year'     => (int) $x['year_level'],
            'block'    => $x['block'],
            'platform' => $x['platform'],
            'college'  => $x['college'],
            'faculty'  => $x['faculty'] ?? 'TBA',
            'students' => $students,
            'mode'     => $x['mode'],
            'room'     => $x['mode'] === 'Online' ? $x['platform'] : ($mtgs[0]['room'] ?? ($x['mode'] === 'Off-campus' ? 'Partner site' : 'TBA')),
            'meetings' => $mtgs,
            'days'     => array_values(array_unique(array_column($mtgs, 'day'))),
            'schedule' => schedule_text($mtgs),
            'grade'    => $grade,
            'encoded'  => $students ? (int) round($encoded / $students * 100) : 0,
            'updated'  => $x['updated'] ? (int) $x['updated'] : null,
        ];
    }
    return $list;
}

/** "MWF 8:00–9:00 AM · Sat 1:00–4:00 PM" */
function schedule_text(array $meetings): string
{
    $groups = [];
    foreach ($meetings as $m) {
        $key = $m['start'] . '-' . $m['end'];
        $groups[$key]['days'][] = $m['day'] === 'Thu' ? 'Th' : substr($m['day'], 0, $m['day'] === 'Sat' ? 3 : 1);
        $groups[$key]['start'] = $m['start'];
        $groups[$key]['end'] = $m['end'];
    }
    $parts = [];
    foreach ($groups as $g) {
        $parts[] = implode('', $g['days']) . ' ' . clock($g['start']) . '–' . clock($g['end'], true);
    }
    return $parts ? implode(' · ', $parts) : 'No scheduled meetings';
}

/** Every class meeting on a given day ("Mon".."Sat"), earliest first. */
function sessions_on(string $day): array
{
    $list = [];
    foreach (classes() as $c) {
        foreach ($c['meetings'] as $m) {
            if ($m['day'] === $day) {
                $list[] = ['start' => $m['start'], 'end' => $m['end'], 'room' => $c['mode'] === 'Online' ? $c['room'] : ($m['room'] ?? 'TBA')] + $c;
            }
        }
    }
    usort($list, fn($a, $b) => [$a['start'], $a['code'], $a['section']] <=> [$b['start'], $b['code'], $b['section']]);
    return $list;
}

/** Pairs of class sections booked into the same room at overlapping times. */
function room_conflicts(): array
{
    static $list = null;
    return $list ??= cached('room_conflicts', 'build_room_conflicts');
}

function build_room_conflicts(): array
{
    $byId = array_column(classes(), null, 'id');
    $pairs = [];
    foreach (rows(
        'SELECT a.section_id a, b.section_id b, r.name room, a.day,
                GREATEST(a.start_time, b.start_time) s, LEAST(a.end_time, b.end_time) e
         FROM meetings a
         JOIN meetings b ON b.room_id = a.room_id AND b.day = a.day AND b.section_id > a.section_id
              AND a.start_time < b.end_time AND b.start_time < a.end_time
         JOIN rooms r ON r.id = a.room_id
         JOIN sections x ON x.id = a.section_id
         JOIN sections y ON y.id = b.section_id AND y.term_id=x.term_id
         WHERE x.term_id = ?
         ORDER BY FIELD(a.day, "Mon", "Tue", "Wed", "Thu", "Fri", "Sat")',
        [current_term()['id']]
    ) as $row) {
        $key = $row['a'] . '-' . $row['b'];
        $pairs[$key] ??= ['room' => $row['room'], 'days' => [], 'start' => minutes($row['s']), 'end' => minutes($row['e']), 'a' => $byId[$row['a']], 'b' => $byId[$row['b']]];
        $pairs[$key]['days'][] = $row['day'];
    }
    return $list = array_values($pairs);
}

/* ------------------------------------------------------------------ */
/* Choices for the add / edit forms                                    */
/* ------------------------------------------------------------------ */

/** [college => [program code => "CODE · Program name"]] */
function program_choices(): array
{
    $out = [];
    foreach (colleges() as $college => $c) {
        foreach ($c['programs'] as $code => $name) {
            $out[$college][$code] = $code . ' · ' . $name;
        }
    }
    return $out;
}

/** [college => [faculty id => name]] */
function faculty_choices(): array
{
    $out = [];
    foreach (faculty() as $f) {
        $out[$f['college']][$f['fid']] = $f['name'];
    }
    ksort($out);
    return $out;
}

/** [course id => "CODE · Title"] */
function course_choices(): array
{
    return array_column(rows('SELECT id, CONCAT(code, " · ", title) label FROM courses ORDER BY code'), 'label', 'id');
}

/** [college => [room name => "Room (type, seats)"]] */
function room_choices(): array
{
    $out = [];
    foreach (rows('SELECT r.name, r.type, r.capacity, c.short_name college FROM rooms r JOIN colleges c ON c.id = r.college_id ORDER BY c.id, r.name') as $r) {
        $out[$r['college']][$r['name']] = $r['name'] . ' (' . $r['type'] . ', ' . $r['capacity'] . ' seats)';
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Sidebar badges                                                      */
/* ------------------------------------------------------------------ */

function count_for_review(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM applicants WHERE status = 'For review'")->fetchColumn();
}

/** Required reference catalogs; no fallback sample data is ever supplied. */
function setup_missing(): array
{
    static $missing = null;
    if ($missing !== null) return $missing;
    $missing = [];
    foreach (['colleges', 'programs', 'courses', 'rooms'] as $table) {
        if (!(int) db()->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn()) $missing[] = $table;
    }
    if (!(int) current_term()['id']) $missing[] = 'active term';
    return $missing;
}

function setup_missing_for_action(string $action): array
{
    $needed = match ($action) {
        'applicant_save' => ['colleges', 'programs'],
        'student_save' => ['colleges', 'programs', 'active term'],
        'faculty_save' => ['colleges'],
        'section_save' => ['colleges', 'programs', 'courses', 'rooms', 'active term'],
        'grades_save', 'enrollment_add', 'enrollment_remove' => ['active term'],
        default => [],
    };
    return array_values(array_intersect($needed, setup_missing()));
}
