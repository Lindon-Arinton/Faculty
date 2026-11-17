<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
require_permission('academic.view', $user);


$college = isset(colleges()[q('college')]) ? q('college') : '';
$status = q('status');
$search = q('q');
$sort = q('sort', 'progress');

$scope = filter_rows(classes(), ['college' => $college]);

$rows = search_rows($scope, $search, ['key', 'code', 'title', 'section', 'faculty']);
$statusCounts = array_count_values(array_column($rows, 'grade'));
$statusCounts['pending'] = ($statusCounts['In progress'] ?? 0) + ($statusCounts['Not started'] ?? 0);
$rows = match ($status) {
    'pending' => array_values(array_filter($rows, fn($c) => $c['grade'] !== 'Submitted')),
    default   => filter_rows($rows, ['grade' => $status]),
};
usort($rows, match ($sort) {
    'faculty' => fn($a, $b) => strcmp($a['faculty'], $b['faculty']),
    'code'    => fn($a, $b) => [$a['code'], $a['section']] <=> [$b['code'], $b['section']],
    default   => fn($a, $b) => [$a['encoded'], $a['code']] <=> [$b['encoded'], $b['code']],
});

maybe_export_csv($rows, [
    'Code' => 'code', 'Title' => 'title', 'Section' => 'section', 'Faculty' => 'faculty', 'College' => 'college',
    'Students' => 'students', 'Encoded %' => 'encoded', 'Status' => 'grade',
    'Last update' => fn($r) => $r['updated'] ? date('Y-m-d H:i', $r['updated']) : '',
], 'grade-submissions');

$page = paginate($rows, 20);

// Summary
$counts = array_count_values(array_column($scope, 'grade')) + ['Submitted' => 0, 'In progress' => 0, 'Not started' => 0];
$total = count($scope);
$pending = $counts['In progress'] + $counts['Not started'];
$deadline = strtotime(current_term()['grade_deadline']);
$daysLeft = (int) round(($deadline - strtotime(date('Y-m-d', app_now()))) / 86400);
$studentsWaiting = array_sum(array_column(array_filter($scope, fn($c) => $c['grade'] !== 'Submitted'), 'students'));

// Progress by college (or by year level inside one college)
$groups = [];
foreach ($scope as $c) {
    $key = $college ? year_label(section_year($c['section'])) : $c['college'];
    $groups[$key][] = $c;
}
if ($college) {
    ksort($groups);
}

// Faculty with the most unsubmitted classes
$byFaculty = [];
foreach ($scope as $c) {
    if ($c['grade'] !== 'Submitted') {
        $byFaculty[$c['faculty']] = ($byFaculty[$c['faculty']] ?? 0) + 1;
    }
}
arsort($byFaculty);

$statusOptions = ['' => 'All', 'pending' => 'Pending', 'In progress' => 'In progress', 'Not started' => 'Not started', 'Submitted' => 'Submitted'];
$chipCounts = ['' => array_sum(array_intersect_key($statusCounts, array_flip(['Submitted', 'In progress', 'Not started'])))] + $statusCounts;

page_start('grades', 'Grade submissions', $user);
page_head(
    'Grade submissions',
    'End-of-term encoding for ' . number_format($total) . ' class sections' . ($college ? ' in ' . e($college) : '') . ((int) current_term()['id'] ? ' · Deadline ' . e(date('l, F j', $deadline)) : ' · No active term or deadline configured'),
    '<form method="get" class="filters">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
);
?>
    <section class="grid grid-4">
        <?= stat_card('Submitted', number_format($counts['Submitted']), $total ? pct($counts['Submitted'], $total) . '% of class sections' : 'No class sections available', 'check-sq', 'green', url_with(['status' => 'Submitted']) . '#list') ?>
        <?= stat_card('In progress', number_format($counts['In progress']), 'Encoding started', 'clock', 'orange', url_with(['status' => 'In progress']) . '#list') ?>
        <?= stat_card('Not started', number_format($counts['Not started']), 'No grades encoded yet', 'alert', 'red', url_with(['status' => 'Not started']) . '#list') ?>
        <?= stat_card('Days to deadline', (int) current_term()['id'] ? (string) max(0, $daysLeft) : '—', (int) current_term()['id'] ? number_format($studentsWaiting) . ' students waiting on grades' : 'No active term configured', 'calendar', 'blue') ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Progress by <?= $college ? 'year level' : 'college' ?></h2>
                    <p class="muted small">Average share of grades encoded per class section</p>
                </div>
            </div>
            <ul class="progress-list progress-list--wide">
                <?php foreach ($groups as $name => $list):
                    $n = count($list);
                    $avg = (int) round(array_sum(array_column($list, 'encoded')) / max(1, $n));
                    $sub = count(array_filter($list, fn($c) => $c['grade'] === 'Submitted'));
                    $done = $sub === $n; ?>
                    <li>
                        <a href="<?= e($college ? '#list' : url_with(['college' => $name])) ?>">
                            <div class="progress-head">
                                <span><strong><?= e($name) ?></strong> <span class="muted xsmall"><?= $n ?> classes · <?= $sub ?> submitted</span></span>
                                <span class="tag tag--sm <?= $done ? 'tag--green' : 'tag--orange' ?>"><?= $done ? icon('check', 12) . ' Submitted' : ($n - $sub) . ' pending' ?></span>
                            </div>
                            <div class="progress" data-tip="<?= e($name . '|' . $avg . '% of grades encoded|' . $sub . ' of ' . $n . ' classes submitted') ?>"><span style="--w: <?= $avg ?>%"></span></div>
                            <div class="progress-foot"><span><?= $avg ?>% encoded</span><span><?= pct($sub, $n) ?>% of classes submitted</span></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head"><div><h2>Submission rate</h2><p class="muted small">All class sections in scope</p></div></div>
                <?= donut_chart([
                    ['Submitted', $counts['Submitted'], 'var(--blue-600)', url_with(['status' => 'Submitted']) . '#list'],
                    ['In progress', $counts['In progress'], 'var(--orange-500)', url_with(['status' => 'In progress']) . '#list'],
                    ['Not started', $counts['Not started'], 'var(--slate-200)', url_with(['status' => 'Not started']) . '#list'],
                ], pct($counts['Submitted'], $total) . '%', 'submitted') ?>
            </div>
            <div class="card panel">
                <div class="panel-head"><div><h2>Most classes pending</h2><p class="muted small">Faculty with unsubmitted grades</p></div></div>
                <?php if ($byFaculty): ?>
                    <ul class="kv">
                        <?php foreach (array_slice($byFaculty, 0, 5, true) as $name => $n): ?>
                            <li><a class="link-plain" href="<?= e(url_with(['q' => $name, 'status' => 'pending']) . '#list') ?>"><?= e($name) ?></a><strong><?= $n ?> class<?= $n === 1 ? '' : 'es' ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="empty">Every class has submitted grades.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="card panel panel--flush" id="list">
        <div class="table-toolbar">
            <div>
                <h2>Class sections</h2>
                <p class="muted small"><?= $pending ?> of <?= $total ?> sections still pending · click a class to encode its grades</p>
            </div>
            <form method="get" class="toolbar-form" action="grades.php#list">
                <input type="hidden" name="college" value="<?= e($college) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Class code, title or faculty"></label>
                <?= select_filter('sort', ['code' => 'Sort: class code', 'faculty' => 'Sort: faculty'], $sort === 'progress' ? '' : $sort, 'Sort: least encoded') ?>
            </form>
        </div>
        <div class="toolbar-chips"><?= chip_links('status', $statusOptions, $status, $chipCounts) ?></div>
        <div class="table-wrap">
            <table class="data data--rows">
                <thead><tr><th>Class</th><th>Section</th><th>Faculty</th><th class="num">Students</th><th>Encoded</th><th>Status</th><th>Last update</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($page['items'] as $c): ?>
                    <tr data-href="class.php?id=<?= (int) $c['id'] ?>" class="is-link">
                        <td class="col-wide"><div class="cell-stack"><strong><?= e($c['title']) ?></strong><span><?= e($c['code']) ?> · <?= e($c['college']) ?></span></div></td>
                        <td class="nowrap"><?= e($c['section']) ?></td>
                        <td class="nowrap"><?= e($c['faculty']) ?></td>
                        <td class="num"><?= (int) $c['students'] ?></td>
                        <td class="nowrap"><div class="mini-bar mini-bar--<?= e(status_tone($c['grade'])) ?>"><span style="--w: <?= (int) $c['encoded'] ?>%"></span></div><span class="mini-bar-num"><?= (int) $c['encoded'] ?>%</span></td>
                        <td><?= tag($c['grade']) ?></td>
                        <td class="nowrap muted"><?= e(rel_time($c['updated'])) ?></td>
                        <td><a class="btn btn--ghost btn--xs" href="class.php?id=<?= (int) $c['id'] ?>"><?= icon('edit', 14) ?> <?= $c['grade'] === 'Submitted' ? 'View' : 'Encode' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$page['items']): ?><tr><td colspan="8" class="empty">No class sections match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, 'class sections') ?>
    </section>
<?php page_end(); ?>
