<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();

$college = isset(colleges()[q('college')]) ? q('college') : '';
$year = q('year');
$status = q('status');
$program = q('program');
$search = q('q');
$riskIds = at_risk_ids();

$scope = filter_rows(students(), ['college' => $college]);

$rows = filter_rows($scope, ['year' => $year, 'program' => $program]);
$rows = search_rows($rows, $search, ['name', 'id', 'program', 'program_name', 'section']);
$statusCounts = array_count_values(array_column($rows, 'status'));
$rows = filter_rows($rows, ['status' => $status]);

maybe_export_csv($rows, [
    'Student no.' => 'id', 'Name' => 'name', 'Program' => 'program_name', 'Section' => 'section', 'College' => 'college',
    'Year level' => fn($r) => year_label($r['year']), 'Status' => 'status', 'Units' => 'units',
    'At risk' => fn($r) => isset($riskIds[$r['id']]) ? 'Yes' : '',
], 'students');

$page = paginate($rows, 20);

// Summary
$total = count($scope);
$counts = array_count_values(array_column($scope, 'status'));
$activeUnits = array_column(array_filter($scope, fn($s) => $s['units'] > 0), 'units');
$avgUnits = $activeUnits ? array_sum($activeUnits) / count($activeUnits) : 0;

// College x year level table
$matrix = [];
foreach ($scope as $s) {
    $key = $college ? $s['program_name'] : $s['college'];
    $matrix[$key] ??= [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $matrix[$key][$s['year']]++;
}
uasort($matrix, fn($a, $b) => array_sum($b) <=> array_sum($a));
$yearTotals = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
foreach ($matrix as $m) {
    foreach ($m as $y => $n) $yearTotals[$y] += $n;
}

$programCounts = [];
foreach ($scope as $s) {
    $programCounts[$s['program']] = ($programCounts[$s['program']] ?? 0) + 1;
}
arsort($programCounts);

$programOptions = [];
foreach ($college ? [$college => colleges()[$college]] : colleges() as $c) {
    $programOptions += $c['programs'];
}

$statusOptions = ['' => 'All', 'Regular' => 'Regular', 'Irregular' => 'Irregular', 'On leave' => 'On leave'];
$chipCounts = ['' => array_sum($statusCounts)] + $statusCounts;

page_start('enrollment', 'Enrollment', $user);
page_head(
    'Enrollment',
    e(current_term()['name']) . ' · ' . number_format($total) . ' enrolled students' . ($college ? ' in ' . e($college) : ' across ' . count(colleges()) . ' colleges'),
    '<form method="get" class="filters">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
    . modal_button('studentModal', icon('plus', 16) . ' Add student')
);
?>
    <section class="grid grid-4">
        <?= stat_card('Enrolled students', number_format($total), ($college ? pct($total, count(students())) . '% of all students' : icon('arrow-up', 11) . ' ' . pct($total - (int) meta('prev_term_enrollment', '0'), (int) meta('prev_term_enrollment', '1'), 1) . '% from last term'), 'users', 'blue') ?>
        <?= stat_card('Regular', number_format($counts['Regular'] ?? 0), pct($counts['Regular'] ?? 0, $total) . '% full curriculum load', 'check-sq', 'green', url_with(['status' => 'Regular']) . '#list') ?>
        <?= stat_card('Irregular', number_format($counts['Irregular'] ?? 0), pct($counts['Irregular'] ?? 0, $total) . '% reduced or mixed load', 'clock', 'orange', url_with(['status' => 'Irregular']) . '#list') ?>
        <?= stat_card('On leave', number_format($counts['On leave'] ?? 0), 'Average load ' . number_format($avgUnits, 1) . ' units', 'user-x', 'purple', url_with(['status' => 'On leave']) . '#list') ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Students by <?= $college ? 'program' : 'college' ?> and year level</h2>
                    <p class="muted small">Click a number to see those students</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= $college ? 'Program' : 'College' ?></th><th class="num">1st</th><th class="num">2nd</th><th class="num">3rd</th><th class="num">4th</th><th class="num">Total</th><th>Share</th></tr></thead>
                    <tbody>
                    <?php foreach ($matrix as $name => $m): $sum = array_sum($m);
                        $base = $college ? ['program' => array_search($name, $programOptions, true) ?: ''] : ['college' => $name]; ?>
                        <tr>
                            <td><strong><?= e($name) ?></strong></td>
                            <?php foreach ($m as $y => $n): ?>
                                <td class="num"><a class="cell-link" href="<?= e(url_with($base + ['year' => $y]) . '#list') ?>"><?= number_format($n) ?></a></td>
                            <?php endforeach; ?>
                            <td class="num"><a class="cell-link" href="<?= e(url_with($base) . '#list') ?>"><strong><?= number_format($sum) ?></strong></a></td>
                            <td class="nowrap"><div class="mini-bar"><span style="--w: <?= $total ? round($sum / $total * 100 * 2.5, 1) : 0 ?>%"></span></div><span class="mini-bar-num"><?= pct($sum, $total, 1) ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td>Total</td><?php foreach ($yearTotals as $n): ?><td class="num"><?= number_format($n) ?></td><?php endforeach; ?><td class="num"><?= number_format($total) ?></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head"><div><h2>Year level</h2><p class="muted small">Share of enrolled students</p></div></div>
                <?= bar_list(array_combine(array_map('year_label', array_keys($yearTotals)), $yearTotals), null, 'number', 'purple',
                    array_combine(array_map('year_label', array_keys($yearTotals)), array_map(fn($y) => url_with(['year' => $y]) . '#list', array_keys($yearTotals)))) ?>
            </div>
            <div class="card panel">
                <div class="panel-head"><div><h2>Largest programs</h2><p class="muted small">Top <?= min(6, count($programCounts)) ?> by enrollment</p></div></div>
                <?= bar_list(array_slice($programCounts, 0, 6, true), null, 'number', '', array_map(fn($code) => url_with(['program' => $code]) . '#list', array_combine(array_keys($programCounts), array_keys($programCounts)))) ?>
            </div>
        </div>
    </section>

    <section class="card panel panel--flush" id="list">
        <div class="table-toolbar">
            <div>
                <h2>Student directory</h2>
                <p class="muted small">Sorted by name</p>
            </div>
            <form method="get" class="toolbar-form" action="enrollment.php#list">
                <input type="hidden" name="college" value="<?= e($college) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, student no. or section"></label>
                <?= select_filter('program', $programOptions, $program, 'All programs') ?>
                <?= select_filter('year', [1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year'], $year, 'All years') ?>
            </form>
        </div>
        <div class="toolbar-chips"><?= chip_links('status', $statusOptions, $status, $chipCounts) ?></div>
        <div class="table-wrap">
            <table class="data data--rows">
                <thead><tr><th>Student</th><th>Program</th><th>Section</th><th>Year</th><th class="num">Units</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($page['items'] as $s): ?>
                    <tr>
                        <td><div class="cell-person"><?= avatar($s['name']) ?><div><strong><?= e($s['name']) ?></strong><span><?= e($s['id']) ?></span></div></div></td>
                        <td><div class="cell-stack"><strong><?= e($s['program_name']) ?></strong><span><?= e($s['college']) ?></span></div></td>
                        <td><?= e($s['section']) ?></td>
                        <td class="nowrap"><?= e(year_label($s['year'])) ?></td>
                        <td class="num"><?= $s['units'] ?: '<span class="muted">—</span>' ?></td>
                        <td class="nowrap">
                            <?= tag($s['status']) ?>
                            <?php if (isset($riskIds[$s['id']])): ?><a href="at-risk.php?q=<?= e(urlencode($s['id'])) ?>" class="tag tag--red">At risk</a><?php endif; ?>
                        </td>
                        <td><?= row_actions('studentModal', [
                            'id' => $s['sid'], 'first_name' => $s['first'], 'middle_name' => $s['middle'], 'last_name' => $s['last'], 'sex' => $s['sex'], 'birthdate' => $s['birthdate'],
                            'email' => $s['email'], 'program' => $s['program'], 'year' => $s['year'], 'block' => $s['block'], 'status' => $s['status'], 'back_subjects' => $s['back'],
                        ], 'Edit student ' . $s['id'], 'student_delete', $s['sid'], 'Delete student ' . $s['name'] . ' (' . $s['id'] . ')? Their class enrollments and grades are deleted too.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$page['items']): ?><tr><td colspan="7" class="empty">No students match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, 'students') ?>
    </section>
    <?= student_modal() ?>
<?php page_end(); ?>
