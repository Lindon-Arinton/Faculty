<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();

const RATING_BANDS = [
    'Excellent'     => [4.5, 5.01],
    'Very good'     => [4.0, 4.5],
    'Good'          => [3.5, 4.0],
    'Needs support' => [0, 3.5],
];
const NOT_EVALUATED = 'Not evaluated';

function rating_band(float $rating): string
{
    foreach (RATING_BANDS as $band => [$lo, $hi]) {
        if ($rating >= $lo && $rating < $hi) {
            return $band;
        }
    }
    return 'Needs support';
}

$college = isset(colleges()[q('college')]) ? q('college') : '';
$rank = q('rank');
$band = q('band');
$search = q('q');
$sort = q('sort');

// Summary cards and charts use faculty who were evaluated; the table lists everyone.
$everyone = array_map(fn($f) => $f + ['band' => $f['responses'] ? rating_band($f['rating']) : NOT_EVALUATED], filter_rows(faculty(), ['college' => $college]));
$scope = array_values(array_filter($everyone, fn($f) => $f['responses'] > 0));

$rows = filter_rows($everyone, ['rank' => $rank]);
$rows = search_rows($rows, $search, ['name', 'college', 'rank']);
$bandCounts = array_count_values(array_column($rows, 'band'));
$rows = filter_rows($rows, ['band' => $band]);
usort($rows, match ($sort) {
    'responses' => fn($a, $b) => [$b['responses'], $b['rating']] <=> [$a['responses'], $a['rating']],
    'change'    => fn($a, $b) => ($b['rating'] - $b['previous']) <=> ($a['rating'] - $a['previous']),
    'name'      => fn($a, $b) => strcmp(preg_replace('/^\S+\s/', '', $a['name']), preg_replace('/^\S+\s/', '', $b['name'])),
    default     => fn($a, $b) => [$b['rating'], $b['responses']] <=> [$a['rating'], $a['responses']],
});

maybe_export_csv($rows, [
    'Faculty' => 'name', 'College' => 'college', 'Rank' => 'rank', 'Responses' => 'responses',
    'Response rate %' => fn($r) => pct($r['responses'], $r['enrolled']), 'Rating' => 'rating', 'Previous term' => 'previous',
] + array_combine(EVAL_CATEGORIES, array_map(fn($cat) => fn($r) => $r['categories'][$cat], EVAL_CATEGORIES)), 'faculty-evaluations');

$page = paginate($rows, 20);

// Summary
$total = count($scope);
$avg = $total ? array_sum(array_column($scope, 'rating')) / $total : 0;
$prevAvg = $total ? array_sum(array_column($scope, 'previous')) / $total : 0;
$responses = array_sum(array_column($scope, 'responses'));
$enrolled = array_sum(array_column($scope, 'enrolled'));

$categoryAvg = [];
foreach (EVAL_CATEGORIES as $cat) {
    $categoryAvg[$cat] = $total ? array_sum(array_map(fn($f) => $f['categories'][$cat], $scope)) / $total : 0;
}

$bands = array_merge(array_fill_keys(array_keys(RATING_BANDS), 0), array_count_values(array_column($scope, 'band')));

$byCollege = [];
foreach ($scope as $f) {
    $key = $college ? $f['rank'] : $f['college'];
    $byCollege[$key][] = $f;
}
uasort($byCollege, fn($a, $b) => (array_sum(array_column($b, 'rating')) / count($b)) <=> (array_sum(array_column($a, 'rating')) / count($a)));

$bandOptions = ['' => 'All'] + array_combine(array_keys(RATING_BANDS), array_keys(RATING_BANDS)) + [NOT_EVALUATED => NOT_EVALUATED];
$bandLinks = array_map(fn($b) => url_with(['band' => $b]) . '#list', array_combine(array_keys(RATING_BANDS), array_keys(RATING_BANDS)));
$chipCounts = ['' => array_sum($bandCounts)] + $bandCounts;

page_start('evaluations', 'Faculty evaluations', $user);
page_head(
    'Faculty evaluations',
    'Anonymous student feedback · ' . e(current_term()['name']) . ($college ? ' · ' . e($college) : ''),
    '<form method="get" class="filters">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
    . modal_button('facultyModal', icon('plus', 16) . ' Add faculty')
);
?>
    <section class="grid grid-4">
        <?= stat_card('Faculty evaluated', number_format($total), $college ? e($college) : 'Across ' . count(colleges()) . ' colleges', 'users', 'blue') ?>
        <?= stat_card('Average rating', number_format($avg, 2), ($avg >= $prevAvg ? icon('arrow-up', 11) . ' ' : icon('arrow-dn', 11) . ' ') . number_format(abs($avg - $prevAvg), 2) . ' vs last term', 'star', 'orange') ?>
        <?= stat_card('Response rate', pct($responses, $enrolled) . '%', number_format($enrolled) . ' students invited', 'check-sq', 'green') ?>
        <?= stat_card('Total responses', number_format($responses), 'Individual answers stay private', 'shield', 'purple') ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2>Ratings by <?= $college ? 'academic rank' : 'college' ?></h2>
                    <p class="muted small">Average overall rating out of 5</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th><?= $college ? 'Rank' : 'College' ?></th><th class="num">Faculty</th><th class="num">Responses</th><th class="num">Response rate</th><th>Average rating</th></tr></thead>
                    <tbody>
                    <?php foreach ($byCollege as $name => $list): $a = array_sum(array_column($list, 'rating')) / count($list); ?>
                        <tr>
                            <td><a class="link-plain" href="<?= e(url_with($college ? ['rank' => $name] : ['college' => $name]) . '#list') ?>"><strong><?= e($name) ?></strong></a></td>
                            <td class="num"><?= count($list) ?></td>
                            <td class="num"><?= number_format(array_sum(array_column($list, 'responses'))) ?></td>
                            <td class="num"><?= pct(array_sum(array_column($list, 'responses')), array_sum(array_column($list, 'enrolled'))) ?>%</td>
                            <td><div class="cell-rating"><strong><?= number_format($a, 2) ?></strong><?= stars($a) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head"><div><h2>By category</h2><p class="muted small">Average score out of 5</p></div></div>
                <?= bar_list($categoryAvg, 5, 'rating', 'orange block') ?>
            </div>
            <div class="card panel">
                <div class="panel-head"><div><h2>Rating bands</h2><p class="muted small">Number of faculty in each band</p></div></div>
                <?= bar_list($bands, null, 'number', 'purple', $bandLinks) ?>
            </div>
        </div>
    </section>

    <section class="card panel panel--flush" id="list">
        <div class="table-toolbar">
            <div>
                <h2>Faculty results</h2>
                <p class="muted small">Everyone on the faculty list. Only totals and averages are shown; student responses stay anonymous.</p>
            </div>
            <form method="get" class="toolbar-form" action="evaluations.php#list">
                <input type="hidden" name="college" value="<?= e($college) ?>">
                <input type="hidden" name="band" value="<?= e($band) ?>">
                <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Faculty name"></label>
                <?= select_filter('rank', ['Instructor', 'Lecturer', 'Assistant Professor', 'Associate Professor', 'Professor'], $rank, 'All ranks') ?>
                <?= select_filter('sort', ['responses' => 'Sort: most responses', 'change' => 'Sort: most improved', 'name' => 'Sort: name'], $sort, 'Sort: highest rated') ?>
            </form>
        </div>
        <div class="toolbar-chips"><?= chip_links('band', $bandOptions, $band, $chipCounts) ?></div>
        <div class="table-wrap">
            <table class="data data--rows">
                <thead><tr><th>Faculty member</th><th>College</th><th class="num">Responses</th><th>Rating</th><th>Change</th><th>Weakest area</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($page['items'] as $f):
                    $change = round($f['rating'] - $f['previous'], 1);
                    $weak = array_keys($f['categories'], min($f['categories']))[0];
                    $rated = $f['responses'] > 0; ?>
                    <tr>
                        <td><div class="cell-person"><?= avatar($f['name']) ?><div><strong><?= e($f['name']) ?></strong><span><?= e($f['rank']) ?></span></div></div></td>
                        <td class="nowrap"><?= e($f['college']) ?></td>
                        <td class="num"><div class="cell-stack cell-stack--end"><strong><?= (int) $f['responses'] ?></strong><span><?= pct($f['responses'], $f['enrolled']) ?>% responded</span></div></td>
                        <?php if ($rated): ?>
                        <td><div class="cell-rating"><strong><?= number_format($f['rating'], 1) ?></strong><?= stars($f['rating']) ?></div></td>
                        <td class="nowrap"><span class="delta <?= $change > 0 ? 'up' : ($change < 0 ? 'down' : '') ?>"><?= $change > 0 ? '▲ ' : ($change < 0 ? '▼ ' : '') ?><?= $change == 0 ? 'No change' : number_format(abs($change), 1) ?></span></td>
                        <td><div class="cell-stack"><strong><?= e($weak) ?></strong><span><?= number_format($f['categories'][$weak], 1) ?> / 5</span></div></td>
                        <?php else: ?>
                        <td colspan="3"><?= tag(NOT_EVALUATED, 'slate') ?> <span class="muted xsmall"><?= $f['sections'] ? $f['sections'] . ' class' . ($f['sections'] === 1 ? '' : 'es') . ', no responses yet' : 'No classes this term' ?></span></td>
                        <?php endif; ?>
                        <td><?= row_actions('facultyModal', [
                            'id' => $f['fid'], 'title' => $f['title'], 'first_name' => $f['first'], 'last_name' => $f['last'], 'college' => $f['college'],
                            'rank' => $f['rank'], 'employment' => $f['employment'], 'email' => $f['email'],
                        ], 'Edit ' . $f['name'], 'faculty_delete', $f['fid'], 'Delete ' . $f['name'] . '?' . ($f['sections'] ? ' Their ' . $f['sections'] . ' class' . ($f['sections'] === 1 ? '' : 'es') . ' will be left without a teacher (TBA).' : '') . ' This can’t be undone.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$page['items']): ?><tr><td colspan="7" class="empty">No faculty match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($page, 'faculty') ?>
    </section>
    <?= faculty_modal() ?>
<?php page_end(); ?>
