<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
require_permission('academic.view', $user);

$term = q('q');
$groups = [];

if (mb_strlen($term) >= 2) {
    $groups = [
        [
            'title' => 'Students', 'icon' => 'users', 'page' => 'enrollment.php',
            'rows'  => search_rows(students(), $term, ['name', 'id', 'section']),
            'show'  => fn($r) => [$r['name'], $r['id'] . ' · ' . $r['program_name'], tag($r['status'])],
        ],
        [
            'title' => 'Applicants', 'icon' => 'user-plus', 'page' => 'incoming.php',
            'rows'  => search_rows(applicants(), $term, ['name', 'id']),
            'show'  => fn($r) => [$r['name'], $r['id'] . ' · ' . $r['program_name'], tag($r['status'])],
        ],
        [
            'title' => 'At-risk students', 'icon' => 'alert', 'page' => 'at-risk.php',
            'rows'  => search_rows(at_risk(), $term, ['name', 'id']),
            'show'  => fn($r) => [$r['name'], $r['id'] . ' · ' . $r['detail'], tag($r['type'])],
        ],
        [
            'title' => 'Faculty', 'icon' => 'chart', 'page' => 'evaluations.php',
            'rows'  => search_rows(faculty(), $term, ['name']),
            'show'  => fn($r) => [$r['name'], $r['college'] . ' · ' . $r['rank'], '<span class="cell-rating"><strong>' . number_format($r['rating'], 1) . '</strong>' . stars($r['rating']) . '</span>'],
        ],
        [
            'title' => 'Classes', 'icon' => 'calendar', 'page' => 'grades.php',
            'rows'  => search_rows(classes(), $term, ['code', 'title', 'section']),
            'show'  => fn($r) => [$r['code'] . ' · ' . $r['title'], $r['section'] . ' · ' . $r['faculty'], tag($r['grade'])],
        ],
    ];
}
$found = array_sum(array_map(fn($g) => count($g['rows']), $groups));

page_start('', 'Search', $user);
?>
    <section class="page-head page-head--detail">
        <div>
            <a class="crumb" href="index.php"><?= icon('chev-l', 14) ?> Overview</a>
            <h1>Search</h1>
            <p class="muted">
                <?php if ($term === ''): ?>Search students, applicants, faculty and classes across all colleges.
                <?php elseif (mb_strlen($term) < 2): ?>Type at least 2 characters.
                <?php else: ?><?= number_format($found) ?> result<?= $found === 1 ? '' : 's' ?> for “<?= e($term) ?>”<?php endif; ?>
            </p>
        </div>
    </section>

    <?php if ($term !== '' && mb_strlen($term) >= 2 && !$found): ?>
        <section class="card panel"><p class="empty">Nothing matched. Try a last name, student number (e.g. 2023-10482), applicant ID or class code.</p></section>
    <?php endif; ?>

    <div class="grid grid-2 search-results">
        <?php foreach ($groups as $g): if (!$g['rows']) continue; $more = count($g['rows']); ?>
            <section class="card panel panel--list">
                <div class="panel-head panel-pad">
                    <div class="search-group-title"><span class="stat-icon tone-blue"><?= icon($g['icon'], 18) ?></span><div><h2><?= e($g['title']) ?></h2><p class="muted small"><?= number_format($more) ?> match<?= $more === 1 ? '' : 'es' ?></p></div></div>
                    <a class="link-strong" href="<?= e($g['page'] . '?q=' . urlencode($term)) ?>#list">View all <?= icon('chev-r', 16) ?></a>
                </div>
                <ul class="list list--padded">
                    <?php foreach (array_slice($g['rows'], 0, 5) as $r): [$main, $sub, $side] = ($g['show'])($r); ?>
                        <li class="list-row" data-href="<?= e($g['page'] . '?q=' . urlencode($r['id'] ?? $r['key'] ?? preg_replace('/^(Dr|Prof|Engr)\.\s+/', '', $r['name']))) ?>#list">
                            <?= avatar($main) ?>
                            <div class="list-main"><strong><?= e($main) ?></strong><span><?= e($sub) ?></span></div>
                            <?= $side ?>
                            <?= icon('chev-r', 16, 'chev') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php page_end(); ?>
