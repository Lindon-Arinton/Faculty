<?php
/**
 * Overview dashboard figures, derived from the database so they match the
 * detail pages. ?college=<name> narrows everything to one college.
 */
require_once __DIR__ . '/dataset.php';
require_once __DIR__ . '/helpers.php';

$collegeFilter = isset(colleges()[q('college')]) ? q('college') : '';
$inScope = fn(array $row) => $collegeFilter === '' || $row['college'] === $collegeFilter;
$now = app_now();

$studentsAll = array_values(array_filter(students(), $inScope));
$applicantsAll = array_values(array_filter(applicants(), $inScope));
$classesAll = array_values(array_filter(classes(), $inScope));
$riskAll = array_values(array_filter(at_risk(), $inScope));
$facultyAll = array_values(array_filter(evaluated_faculty(), $inScope));
$scopeColleges = $collegeFilter ? [$collegeFilter => colleges()[$collegeFilter]] : colleges();
$collegeQs = $collegeFilter ? 'college=' . urlencode($collegeFilter) : '';

// Enrollment breakdown: by college, or by program inside one college
$breakdownLabel = $collegeFilter ? 'Enrollment by program' : 'Enrollment by college';
$colleges = [];
foreach ($studentsAll as $s) {
    $key = $collegeFilter ? $s['program'] : $s['college'];
    $colleges[$key] = ($colleges[$key] ?? 0) + 1;
}
arsort($colleges);
$prevEnrollment = (int) meta('prev_term_enrollment', '0');
$enrollmentGrowth = !$collegeFilter && $prevEnrollment ? round((count($studentsAll) / $prevEnrollment - 1) * 100, 1) : null;

// Grade submission
$gradeCounts = array_count_values(array_column($classesAll, 'grade')) + ['Submitted' => 0, 'In progress' => 0, 'Not started' => 0];
$gradeStatus = [
    ['label' => 'Submitted',   'value' => $gradeCounts['Submitted'],   'color' => 'var(--blue-600)'],
    ['label' => 'In progress', 'value' => $gradeCounts['In progress'], 'color' => 'var(--orange-500)'],
    ['label' => 'Not started', 'value' => $gradeCounts['Not started'], 'color' => 'var(--slate-200)'],
];
$gradesPending = $gradeCounts['In progress'] + $gradeCounts['Not started'];
$deadline = strtotime(current_term()['grade_deadline']);
$daysToDeadline = (int) round((strtotime(date('Y-m-d', $deadline)) - strtotime(date('Y-m-d', $now))) / 86400);

// Grade progress rows: colleges with the most pending first, or year levels inside one college
$gradeGroups = [];
foreach ($classesAll as $c) {
    $key = $collegeFilter ? year_label(section_year($c['section'])) : $c['college'];
    $gradeGroups[$key][] = $c;
}
if ($collegeFilter) {
    ksort($gradeGroups);
}
$gradeByCollege = [];
foreach ($gradeGroups as $label => $list) {
    $gradeByCollege[] = [
        'college' => $label,
        'link'    => 'grades.php?college=' . urlencode($collegeFilter ?: $label),
        'classes' => count($list),
        'pct'     => (int) round(array_sum(array_column($list, 'encoded')) / max(1, count($list))),
        'pending' => count(array_filter($list, fn($c) => $c['grade'] !== 'Submitted')),
    ];
}
if (!$collegeFilter) {
    usort($gradeByCollege, fn($a, $b) => $b['pending'] <=> $a['pending']);
}
$gradeByCollege = array_slice($gradeByCollege, 0, 3);

$urgent = count(array_filter($riskAll, fn($r) => $r['priority'] === 'Urgent'));

$stats = [
    ['label' => 'Active classes',    'value' => number_format(count($classesAll)),  'note' => $collegeFilter ? 'Class sections this term' : 'Across ' . count(colleges()) . ' colleges', 'icon' => 'calendar', 'tone' => 'blue', 'href' => 'schedule.php' . ($collegeQs ? "?$collegeQs" : '')],
    ['label' => 'Enrolled students', 'value' => number_format(count($studentsAll)), 'note' => $collegeFilter ? 'College enrollment' : 'School-wide enrollment', 'icon' => 'users', 'tone' => 'purple', 'href' => 'enrollment.php' . ($collegeQs ? "?$collegeQs" : '')],
    ['label' => 'Grades pending',    'value' => number_format($gradesPending),      'note' => 'Classes not submitted', 'icon' => 'check-sq', 'tone' => 'orange', 'href' => 'grades.php?status=pending' . ($collegeQs ? "&$collegeQs" : '')],
    ['label' => 'At-risk students',  'value' => number_format(count($riskAll)),     'note' => $urgent . ' need urgent attention', 'icon' => 'alert', 'tone' => 'red', 'href' => 'at-risk.php' . ($collegeQs ? "?$collegeQs" : '')],
];

$riskTrend = risk_history($collegeFilter);

// Admissions
$appCounts = array_count_values(array_column($applicantsAll, 'status'));
$appTotal = count($applicantsAll);
$forReview = $appCounts['For review'] ?? 0;
$enrolledNew = $appCounts['Enrolled'] ?? 0;
$qualified = $enrolledNew + ($appCounts['Qualified'] ?? 0) + ($appCounts['Pending documents'] ?? 0);
$examined = $appTotal - $forReview;
$incomplete = count(array_filter($applicantsAll, fn($a) => $a['missing'] && $a['status'] === 'Pending documents'));
$prevIntake = (int) meta('prev_intake_applications', '0');

$admissions = [
    ['label' => 'Total applications', 'value' => number_format($appTotal),
     'note' => $collegeFilter ? pct($appTotal, count(applicants())) . '% of all applications' : pct($appTotal - $prevIntake, $prevIntake, 1) . '% from last intake', 'up' => !$collegeFilter],
    ['label' => 'For review',         'value' => number_format($forReview),   'note' => pct($forReview, $appTotal) . '% awaiting exam results'],
    ['label' => 'Qualified',          'value' => number_format($qualified),   'note' => pct($qualified, $examined) . '% of examinees passed'],
    ['label' => 'Fully enrolled',     'value' => number_format($enrolledNew), 'note' => pct($enrolledNew, $qualified) . '% of qualified students'],
];

$pipeline = [
    ['label' => 'Applications', 'value' => $appTotal,    'tone' => 'p1'],
    ['label' => 'Examined',     'value' => $examined,    'tone' => 'p2'],
    ['label' => 'Qualified',    'value' => $qualified,   'tone' => 'p3'],
    ['label' => 'Enrolled',     'value' => $enrolledNew, 'tone' => 'p4'],
];

// Most recent admissions activity
$applicants = array_slice($applicantsAll, 0, 4);

// Timetable for this weekday: one class at each of three times of day
$todayKey = date('D', $now) === 'Sun' ? 'Mon' : date('D', $now);
$daySessions = array_values(array_filter(sessions_on($todayKey), $inScope));
$schedule = [];
foreach ([480, 600, 780] as $from) {
    foreach ($daySessions as $s) {
        if ($s['start'] >= $from && !in_array($s, $schedule, true)) {
            $schedule[] = $s;
            break;
        }
    }
}
$totalRooms = array_sum(array_map(fn($c) => count($c['rooms']), $scopeColleges));
$conflictCount = count(array_filter(room_conflicts(), fn($x) => $inScope($x['a']) || $inScope($x['b'])));
$termOver = $now > strtotime(current_term()['ends_on'] . ' 23:59:59');

// Latest enrollees and the most urgent at-risk cases
$students = $studentsAll;
usort($students, fn($a, $b) => ($b['enrolled_at'] ?? 0) <=> ($a['enrolled_at'] ?? 0));
$students = array_slice($students, 0, 4);
$atRisk = array_slice($riskAll, 0, 3);

// Top-rated faculty with enough responses to be meaningful
$evaluations = array_values(array_filter($facultyAll, fn($f) => $f['responses'] >= 60));
usort($evaluations, fn($a, $b) => [$b['rating'], $b['responses']] <=> [$a['rating'], $a['responses']]);
$evaluations = array_slice($evaluations, 0, 4);
$responses = array_sum(array_column($facultyAll, 'responses'));
$evalSummary = [
    ['value' => number_format(count($facultyAll)), 'label' => 'Faculty evaluated'],
    ['value' => number_format($responses ? array_sum(array_map(fn($f) => $f['rating'] * $f['responses'], $facultyAll)) / $responses : 0, 2), 'label' => 'Average rating'],
    ['value' => pct($responses, array_sum(array_column($facultyAll, 'enrolled'))) . '%', 'label' => 'Response rate'],
];
