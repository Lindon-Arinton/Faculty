<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
require_permission('academic.view', $user);

$all = applicants();
$college = isset(colleges()[q('college')]) ? q('college') : '';
$status = q('status');
$type = q('type');
$search = q('q');

// Applicants in the selected college (used by the summary cards and charts)
$scope = filter_rows($all, ['college' => $college]);

// Applicants shown in the table
$rows = filter_rows($scope, ['type' => $type]);
$rows = search_rows($rows, $search, ['name', 'id', 'program', 'program_name']);
$statusCounts = array_count_values(array_column($rows, 'status'));
$rows = filter_rows($rows, ['status' => $status]);
usort($rows, fn($a, $b) => $b['submitted'] <=> $a['submitted']);

maybe_export_csv($rows, [
    'Applicant ID' => 'id', 'Name' => 'name', 'Program' => 'program_name', 'College' => 'college', 'Type' => 'type', 'SHS strand' => fn($r) => $r['strand'] ?? '', 'SHS average / GWA' => fn($r) => $r['prior'] ?? '',
    'Exam score' => fn($r) => $r['score'] ?? '', 'Status' => 'status',
    'Missing requirements' => fn($r) => implode('; ', $r['missing']),
    'Submitted' => fn($r) => date('Y-m-d H:i', $r['submitted']),
], 'applicants');

$page = paginate($rows, 15);

// Summary
$counts = array_count_values(array_column($scope, 'status'));
$total = count($scope);
$forReview = $counts['For review'] ?? 0;
$enrolled = $counts['Enrolled'] ?? 0;
$qualified = $enrolled + ($counts['Qualified'] ?? 0) + ($counts['Pending documents'] ?? 0);
$reviewed = $total - $forReview;
// Applicants who passed the exam but still lack documents
$incomplete = array_filter($scope, fn($a) => $a['status'] === 'Pending documents');

$funnel = [
    ['Applications', $total, 'p1', null, url_with(['status' => '']) . '#list'],
    ['Examined', $reviewed, 'p2', pct($reviewed, $total, 1) . '% of applications', null],
    ['Qualified', $qualified, 'p3', pct($qualified, $reviewed, 1) . '% passed the exam', url_with(['status' => 'Qualified']) . '#list'],
    ['Enrolled', $enrolled, 'p4', pct($enrolled, $qualified, 1) . '% of qualified', url_with(['status' => 'Enrolled']) . '#list'],
];

// By college (or by program when one college is selected)
$groupKey = $college ? 'program_name' : 'college';
$groups = [];
foreach ($scope as $a) {
    $g = &$groups[$a[$groupKey]];
    $g ??= ['total' => 0, 'qualified' => 0, 'enrolled' => 0, 'review' => 0];
    $g['total']++;
    if (in_array($a['status'], ['Qualified', 'Pending documents', 'Enrolled'], true)) $g['qualified']++;
    if ($a['status'] === 'Enrolled') $g['enrolled']++;
    if ($a['status'] === 'For review') $g['review']++;
    unset($g);
}
uasort($groups, fn($a, $b) => $b['total'] <=> $a['total']);

$missingCounts = array_fill_keys(REQUIREMENTS, 0);
foreach ($incomplete as $a) {
    foreach ($a['missing'] as $m) $missingCounts[$m]++;
}
arsort($missingCounts);

$typeCounts = array_count_values(array_column($scope, 'type'));
$scores = array_filter(array_column($scope, 'score'));
$avgScore = $scores ? round(array_sum($scores) / count($scores), 1) : 0;

$statusOptions = ['' => 'All', 'For review' => 'For review', 'Qualified' => 'Qualified', 'Pending documents' => 'Pending documents', 'Enrolled' => 'Enrolled', 'Not qualified' => 'Not qualified'];
$chipCounts = ['' => array_sum($statusCounts)] + $statusCounts;

page_start('incoming', 'Incoming enrollment', $user);
page_head(
    'Incoming enrollment',
    e(intake_label()) . ' admissions' . ($college ? ' · ' . e($college) : ' across all colleges'),
    '<form method="get" class="filters">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
    . modal_button('applicantModal', icon('plus', 16) . ' Add applicant')
);
?>
    <section class="grid grid-4">
        <?= stat_card('Total applications', number_format($total), $college ? pct($total, count($all)) . '% of all applications' : icon('arrow-up', 11) . ' ' . pct($total - (int) meta('prev_intake_applications', '0'), (int) meta('prev_intake_applications', '1'), 1) . '% from last intake', 'file', 'blue') ?>
        <?= stat_card('For review', number_format($forReview), pct($forReview, $total) . '% of applications', 'clock', 'purple', url_with(['status' => 'For review'])) ?>
        <?= stat_card('Qualified', number_format($qualified), pct($qualified, $reviewed) . '% of examinees passed', 'check-sq', 'orange', url_with(['status' => 'Qualified'])) ?>
        <?= stat_card('Fully enrolled', number_format($enrolled), pct($enrolled, $qualified) . '% of qualified students', 'graduation', 'green', url_with(['status' => 'Enrolled'])) ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Applications by <?= $college ? 'program' : 'college' ?></h2>
                    <p class="muted small">Where applicants are in the process</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= $college ? 'Program' : 'College' ?></th><th class="num">Applied</th><th class="num">For review</th><th class="num">Qualified</th><th class="num">Enrolled</th><th>Conversion</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $name => $g): $conv = $g['total'] ? $g['enrolled'] / $g['total'] * 100 : 0; ?>
                        <tr>
                            <td><strong><?= e($name) ?></strong></td>
                            <td class="num"><?= number_format($g['total']) ?></td>
                            <td class="num"><?= number_format($g['review']) ?></td>
                            <td class="num"><?= number_format($g['qualified']) ?></td>
                            <td class="num"><?= number_format($g['enrolled']) ?></td>
                            <td class="nowrap"><div class="mini-bar mini-bar--sm"><span style="--w: <?= round(min(100, $conv * 2), 1) ?>%"></span></div><span class="mini-bar-num"><?= number_format($conv, 1) ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Enrollment pipeline</h2>
                    <p class="muted small"><?= pct($enrolled, $total, 1) ?>% overall conversion</p>
                </div>
            </div>
            <div class="funnel">
                <?php foreach ($funnel as [$label, $value, $tone, $note, $href]): ?>
                    <div class="funnel-step funnel-<?= $tone ?><?= $href ? ' is-link' : '' ?>" style="--w: <?= $total ? round($value / $total * 100, 1) : 0 ?>%"
                         data-tip="<?= e($label . '|' . number_format($value) . ' applicants · ' . pct($value, $total, 1) . '% of all applications' . ($note ? '|' . $note : '') . ($href ? '|Click to list them' : '')) ?>"<?= $href ? ' data-href="' . e($href) . '" tabindex="0" role="link"' : '' ?>>
                        <span><?= e($label) ?></span><strong><?= number_format($value) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <ul class="kv">
                <?php foreach (array_slice($funnel, 1) as [$label, , , $note]): // link not needed here ?>
                    <li><span><?= e($label) ?></span><strong><?= e($note) ?></strong></li>
                <?php endforeach; ?>
                <li><span>Average entrance exam score</span><strong><?= e((string) $avgScore) ?></strong></li>
                <li><span>Freshmen · Transferees</span><strong><?= number_format($typeCounts['Freshman'] ?? 0) ?> · <?= number_format($typeCounts['Transferee'] ?? 0) ?></strong></li>
            </ul>
        </div>
    </section>

    <section class="card panel">
        <div class="panel-head">
            <div>
                <h2>Missing requirements</h2>
                <p class="muted small"><?= number_format(count($incomplete)) ?> applicants still have incomplete requirements. Each bar counts applicants missing that document.</p>
            </div>
            <a class="link-strong" href="<?= e(url_with(['status' => 'Pending documents'])) ?>">Show these applicants <?= icon('chev-r', 16) ?></a>
        </div>
        <?= bar_list($missingCounts, null, 'number', 'orange') ?>
    </section>

    <section class="card panel panel--flush" id="list">
        <div class="table-toolbar">
            <div>
                <h2>Applicants</h2>
                <p class="muted small">Newest applications first</p>
            </div>
            <form method="get" class="toolbar-form" action="incoming.php#list">
                <input type="hidden" name="college" value="<?= e($college) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, applicant ID or program"></label>
                <?= select_filter('type', ['Freshman', 'Transferee'], $type, 'All types') ?>
            </form>
        </div>
        <div class="toolbar-chips"><?= chip_links('status', $statusOptions, $status, $chipCounts) ?></div>
        <div class="table-wrap">
            <table class="data data--rows">
                <thead><tr><th>Applicant</th><th>Program</th><th>Type</th><th class="num">Exam</th><th>Requirements</th><th>Status</th><th>Applied</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($page['items'] as $a): $have = count(REQUIREMENTS) - count($a['missing']); ?>
                    <tr>
                        <td><div class="cell-person"><?= avatar($a['name'], status_tone($a['status'])) ?><div><strong><?= e($a['name']) ?></strong><span><?= e($a['id']) ?></span></div></div></td>
                        <td class="col-wide"><div class="cell-stack"><strong><?= e($a['program_name']) ?></strong><span><?= e($a['college']) ?></span></div></td>
                        <td><div class="cell-stack"><strong><?= e($a['type']) ?></strong><span class="nowrap"><?= $a['strand'] ? e($a['strand']) . ' · ' . number_format($a['prior'], 1) : 'GWA ' . number_format((float) $a['prior'], 2) ?></span></div></td>
                        <td class="num"><?= $a['score'] === null ? '<span class="muted">—</span>' : (int) $a['score'] ?></td>
                        <td>
                            <div class="req" title="<?= e($a['missing'] ? 'Missing: ' . implode(', ', $a['missing']) : 'All requirements submitted') ?>">
                                <?php for ($i = 0; $i < count(REQUIREMENTS); $i++): ?><span class="<?= $i < $have ? 'on' : '' ?>"></span><?php endfor; ?>
                                <em><?= $have ?>/<?= count(REQUIREMENTS) ?></em>
                            </div>
                        </td>
                        <td><?= tag($a['status']) ?></td>
                        <td class="nowrap muted"><?= e(rel_time($a['submitted'])) ?></td>
                        <td><?= row_actions('applicantModal', [
                            'id' => $a['aid'], 'first_name' => $a['first'], 'middle_name' => $a['middle'], 'last_name' => $a['last'], 'sex' => $a['sex'], 'email' => $a['email'],
                            'program' => $a['program'], 'type' => $a['type'], 'shs_strand' => $a['strand'] ?? '', 'prior_gwa' => $a['prior'] ?? '', 'exam_score' => $a['score'] ?? '',
                            'status' => $a['status'], 'req' => array_keys(array_diff(REQUIREMENTS, $a['missing'])),
                        ], 'Edit applicant ' . $a['id'], 'applicant_delete', $a['aid'], 'Delete applicant ' . $a['name'] . ' (' . $a['id'] . ')? This can’t be undone.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$page['items']): ?><tr><td colspan="8" class="empty">No applicants match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, 'applicants') ?>
    </section>
    <?= applicant_modal() ?>
<?php page_end(); ?>
