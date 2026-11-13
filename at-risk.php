<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();

$college = isset(colleges()[q('college')]) ? q('college') : '';
$type = q('type');
$priority = q('priority');
$intervention = q('intervention');
$search = q('q');

$scope = filter_rows(at_risk(), ['college' => $college]);

$rows = filter_rows($scope, ['priority' => $priority, 'intervention' => $intervention]);
$rows = search_rows($rows, $search, ['name', 'id', 'program', 'section', 'adviser']);
$typeCounts = array_count_values(array_column($rows, 'type'));
$rows = filter_rows($rows, ['type' => $type]);

maybe_export_csv($rows, [
    'Student no.' => 'id', 'Name' => 'name', 'Program' => 'program_name', 'Section' => 'section', 'College' => 'college',
    'Risk' => 'type', 'Detail' => 'detail', 'Priority' => 'priority', 'Adviser' => 'adviser',
    'Intervention' => 'intervention', 'Last contact' => fn($r) => $r['last_contact'] ? date('Y-m-d', $r['last_contact']) : '',
], 'at-risk-students');

$page = paginate($rows, 20);

// Summary
$total = count($scope);
$byType = array_count_values(array_column($scope, 'type')) + array_fill_keys(RISK_TYPES, 0);
$urgent = count(array_filter($scope, fn($r) => $r['priority'] === 'Urgent'));
$notContacted = count(array_filter($scope, fn($r) => $r['intervention'] === 'Not contacted'));
$urgentNotContacted = count(array_filter($scope, fn($r) => $r['priority'] === 'Urgent' && $r['intervention'] === 'Not contacted'));

// Risk mix by college (or by program inside one college)
$mix = [];
foreach ($scope as $r) {
    $key = $college ? $r['program'] : $r['college'];
    $mix[$key] ??= array_fill_keys(RISK_TYPES, 0);
    $mix[$key][$r['type']]++;
}
uasort($mix, fn($a, $b) => array_sum($b) <=> array_sum($a));
$mixMax = $mix ? max(array_map('array_sum', $mix)) : 1;

// Share of each college's students who are at risk
$studentsPer = [];
foreach (students() as $s) {
    if (!$college || $s['college'] === $college) {
        $key = $college ? $s['program'] : $s['college'];
        $studentsPer[$key] = ($studentsPer[$key] ?? 0) + 1;
    }
}

$interventions = array_count_values(array_column($scope, 'intervention'));
$interventions = array_merge(array_fill_keys(INTERVENTION_STATUSES, 0), $interventions);

$trend = risk_history($college);

$typeColors = ['Failing' => 'var(--red-500)', 'INC' => 'var(--orange-500)', 'Behind curriculum' => 'var(--purple-500)'];
$typeOptions = ['' => 'All'] + array_combine(RISK_TYPES, RISK_TYPES);
$chipCounts = ['' => array_sum($typeCounts)] + $typeCounts;

page_start('risk', 'At-risk students', $user);
page_head(
    'At-risk students',
    number_format($total) . ' students' . ($college ? ' in ' . e($college) : ' across all colleges') . ' who may need academic support',
    '<form method="get" class="filters">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
);
?>
    <section class="grid grid-4">
        <?= stat_card('Urgent cases', number_format($urgent), $urgentNotContacted . ' not yet contacted', 'alert', 'red', url_with(['priority' => 'Urgent']) . '#list') ?>
        <?= stat_card('Failing', number_format($byType['Failing']), 'One or more failing subjects', 'trend-up', 'orange', url_with(['type' => 'Failing']) . '#list') ?>
        <?= stat_card('Incomplete (INC)', number_format($byType['INC']), 'Missing course requirements', 'file', 'blue', url_with(['type' => 'INC']) . '#list') ?>
        <?= stat_card('Behind curriculum', number_format($byType['Behind curriculum']), 'Subjects left to complete', 'book', 'purple', url_with(['type' => 'Behind curriculum']) . '#list') ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Risk by <?= $college ? 'program' : 'college' ?></h2>
                    <p class="muted small">Number of at-risk students and their share of enrollment</p>
                </div>
                <div class="legend legend--row">
                    <?php foreach ($typeColors as $label => $color): ?><button type="button" class="legend-toggle" data-series-toggle="<?= e($label) ?>" aria-pressed="true" title="Click to hide or show"><i class="swatch" style="background: <?= $color ?>"></i><?= e($label) ?></button><?php endforeach; ?>
                </div>
            </div>
            <ul class="stacked" data-stacked>
                <?php foreach ($mix as $name => $counts): $sum = array_sum($counts); ?>
                    <li>
                        <a href="<?= e(($college ? url_with(['q' => $name]) : url_with(['college' => $name])) . '#list') ?>">
                            <span class="stacked-label"><?= e($name) ?></span>
                            <span class="stacked-track" style="--w: <?= round($sum / $mixMax * 100, 1) ?>%">
                                <?php foreach ($counts as $t => $n): if (!$n) continue; ?>
                                    <span style="flex: <?= $n ?>; background: <?= $typeColors[$t] ?>" data-series="<?= e($t) ?>" data-tip="<?= e($name . '|' . $t . ': ' . $n . ' student' . ($n === 1 ? '' : 's') . ' · ' . pct($n, $sum) . '% of this group') ?>"></span>
                                <?php endforeach; ?>
                            </span>
                            <span class="stacked-num"><strong><?= $sum ?></strong> <em><?= pct($sum, $studentsPer[$name] ?? 0, 1) ?>%</em></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head">
                    <div><h2>Monthly trend</h2><p class="muted small">At-risk students this semester</p></div>
                    <span class="pill pill--green"><?= icon('arrow-dn', 12) ?> <?= pct(reset($trend) - $total, reset($trend), 1) ?>%</span>
                </div>
                <?= line_chart($trend, 'var(--red-500)', 120) ?>
            </div>
            <div class="card panel">
                <div class="panel-head"><div><h2>Intervention status</h2><p class="muted small"><?= $notContacted ?> students not yet contacted</p></div></div>
                <?= bar_list($interventions, null, 'number', 'orange block', array_combine(INTERVENTION_STATUSES, array_map(fn($s) => url_with(['intervention' => $s]) . '#list', INTERVENTION_STATUSES))) ?>
            </div>
        </div>
    </section>

    <section class="card panel panel--flush" id="list">
        <div class="table-toolbar">
            <div>
                <h2>Students needing support</h2>
                <p class="muted small">Urgent cases first</p>
            </div>
            <form method="get" class="toolbar-form" action="at-risk.php#list">
                <input type="hidden" name="college" value="<?= e($college) ?>">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Student, section or adviser"></label>
                <?= select_filter('priority', ['Urgent', 'Monitor'], $priority, 'All priorities') ?>
                <?= select_filter('intervention', INTERVENTION_STATUSES, $intervention, 'All interventions') ?>
            </form>
        </div>
        <div class="toolbar-chips"><?= chip_links('type', $typeOptions, $type, $chipCounts) ?></div>
        <div class="table-wrap">
            <table class="data data--rows">
                <thead><tr><th>Student</th><th>Program</th><th>Risk</th><th>Priority</th><th>Adviser</th><th>Intervention</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($page['items'] as $r): ?>
                    <tr>
                        <td><div class="cell-person"><?= avatar($r['name'], 'slate') ?><div><strong><?= e($r['name']) ?></strong><span><?= e($r['id']) ?></span></div></div></td>
                        <td><div class="cell-stack"><strong><?= e($r['section']) ?></strong><span><?= e($r['college']) ?></span></div></td>
                        <td class="nowrap"><div class="cell-stack"><strong class="text-<?= e(status_tone($r['type'])) ?>"><?= e($r['type']) ?></strong><span><?= e($r['detail']) ?></span></div></td>
                        <td><?= tag($r['priority']) ?></td>
                        <td class="nowrap"><?= e($r['adviser']) ?></td>
                        <td><div class="cell-stack"><?= tag($r['intervention']) ?><span>Last contact: <?= e(days_ago($r['last_contact'])) ?></span></div></td>
                        <td><div class="row-actions">
                            <?php $logged = $r['intervention'] !== 'Not contacted'; ?>
                            <?= modal_button('interventionModal', icon('edit', 16), 'icon-btn', [
                                'id' => $r['sid'], 'name' => $r['name'] . ' · ' . $r['id'], 'detail' => $r['type'] . ' — ' . $r['detail'],
                                'status' => $logged ? $r['intervention'] : 'Contacted',
                                'last_contact' => date('Y-m-d', $r['last_contact'] ?? app_now()), 'notes' => $r['notes'] ?? '',
                            ], $logged ? 'Edit intervention' : 'Log intervention', $logged ? 'Edit intervention' : 'Log intervention') ?>
                            <?php if ($logged): ?>
                                <?= confirm_button('intervention_delete', $r['sid'], 'Delete the intervention record for ' . $r['name'] . '? They go back to “Not contacted”.', '', 'icon-btn icon-btn--danger', [], '', 'Delete intervention') ?>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$page['items']): ?><tr><td colspan="7" class="empty">No students match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, 'students') ?>
    </section>
    <?= intervention_modal() ?>
<?php page_end(); ?>
