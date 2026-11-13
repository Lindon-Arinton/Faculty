<?php
/**
 * Page shell: sidebar, top bar and the opening/closing of <main>.
 * Every page calls page_start() first and page_end() last.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/dataset.php';
require_once __DIR__ . '/forms.php';

function nav_items(): array
{
    $forReview = count_for_review();
    return [
        'overview'    => ['label' => 'Overview',            'icon' => 'grid',      'href' => 'index.php'],
        'incoming'    => ['label' => 'Incoming enrollment', 'icon' => 'user-plus', 'href' => 'incoming.php',    'badge' => $forReview],
        'schedule'    => ['label' => 'Campus schedule',     'icon' => 'calendar',  'href' => 'schedule.php'],
        'enrollment'  => ['label' => 'Enrollment',          'icon' => 'users',     'href' => 'enrollment.php'],
        'grades'      => ['label' => 'Grade submissions',   'icon' => 'check-sq',  'href' => 'grades.php'],
        'risk'        => ['label' => 'At-risk students',    'icon' => 'alert',     'href' => 'at-risk.php',     'badge' => count(at_risk())],
        'evaluations' => ['label' => 'Faculty evaluations', 'icon' => 'chart',     'href' => 'evaluations.php'],
    ];
}

/** Things that need attention, for the bell menu. */
function notifications(): array
{
    $risk = at_risk();
    $urgent = count(array_filter($risk, fn($r) => $r['priority'] === 'Urgent' && $r['intervention'] === 'Not contacted'));
    $pending = count(array_filter(classes(), fn($c) => $c['grade'] !== 'Submitted'));
    $review = count_for_review();
    $conflicts = count(room_conflicts());
    $items = [
        [$urgent,    'alert',     'red',    "$urgent urgent at-risk student" . ($urgent === 1 ? '' : 's') . ' not yet contacted', 'at-risk.php?priority=Urgent&intervention=Not+contacted#list'],
        [$pending,   'check-sq',  'orange', "$pending class" . ($pending === 1 ? '' : 'es') . ' still need final grades', 'grades.php?status=pending#list'],
        [$review,    'user-plus', 'blue',   "$review applicant" . ($review === 1 ? '' : 's') . ' waiting for review', 'incoming.php?status=For+review#list'],
        [$conflicts, 'building',  'purple', "$conflicts room conflict" . ($conflicts === 1 ? '' : 's') . ' flagged', 'schedule.php#conflicts'],
    ];
    return array_values(array_filter($items, fn($i) => $i[0] > 0));
}

function page_start(string $active, string $title, array $user): void
{
    $notes = notifications();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $initials = initials($user['name']);
    $nav = nav_items();
    $searchValue = basename($_SERVER['SCRIPT_NAME']) === 'search.php' ? q('q') : '';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a class="brand" href="index.php">
                <div class="brand-mark" role="img" aria-label="<?= e(APP_NAME) ?> logo"></div>
                <div>
                    <div class="brand-name"><?= e(APP_NAME) ?></div>
                    <div class="brand-sub"><?= e(APP_TAGLINE) ?></div>
                </div>
            </a>
            <button class="icon-btn sidebar-close" id="sidebarClose" aria-label="Close menu"><?= icon('x') ?></button>
        </div>

        <div class="nav-label">Workspace</div>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e($item['href']) ?>" class="nav-item<?= $key === $active ? ' is-active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?><span class="badge badge--red"><?= (int) $item['badge'] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="access-card">
            <div class="access-title"><?= icon('shield', 14) ?> School-wide access</div>
            <p>Institutional records are role-protected and evaluation responses stay anonymous.</p>
        </div>

        <div class="sidebar-user">
            <span class="avatar avatar--gradient"><?= e($initials) ?></span>
            <div class="sidebar-user-text">
                <strong><?= e($user['name']) ?></strong>
                <span><?= e($user['role']) ?></span>
            </div>
            <button class="icon-btn" data-menu-toggle="userMenu" aria-label="Account menu"><?= icon('more', 18) ?></button>
        </div>
    </aside>
    <div class="scrim" id="scrim"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu') ?></button>
            <form class="search" action="search.php" method="get" role="search">
                <?= icon('search', 18) ?>
                <input type="search" name="q" id="globalSearch" value="<?= e($searchValue) ?>" placeholder="Search school-wide records" autocomplete="off">
                <kbd>Ctrl K</kbd>
            </form>
            <div class="topbar-actions">
                <div class="menu-wrap">
                    <button class="icon-btn bell" data-menu-toggle="noteMenu" aria-label="Notifications (<?= count($notes) ?>)"><?= icon('bell') ?><?php if ($notes): ?><span class="dot"></span><?php endif; ?></button>
                    <div class="menu menu--wide" id="noteMenu" hidden>
                        <div class="menu-head"><strong>Notifications</strong><span><?= $notes ? count($notes) . ' item' . (count($notes) === 1 ? '' : 's') . ' need attention' : 'You’re all caught up' ?></span></div>
                        <?php foreach ($notes as [$n, $ic, $tone, $text, $href]): ?>
                            <a class="menu-item menu-note" href="<?= e($href) ?>"><span class="stat-icon tone-<?= $tone ?>"><?= icon($ic, 16) ?></span><span><?= e($text) ?></span><?= icon('chev-r', 14) ?></a>
                        <?php endforeach; ?>
                        <?php if (!$notes): ?><p class="empty">Nothing needs attention right now.</p><?php endif; ?>
                    </div>
                </div>
                <div class="menu-wrap">
                    <button class="avatar avatar--gradient avatar--btn" data-menu-toggle="userMenu" aria-label="Account menu"><?= e($initials) ?></button>
                    <div class="menu" id="userMenu" hidden>
                        <div class="menu-head">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($user['email']) ?></span>
                        </div>
                        <form method="post" action="logout.php">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <button type="submit" class="menu-item menu-item--danger"><?= icon('logout', 16) ?> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            <?php if ($flash): ?>
                <div class="toast toast--<?= e($flash['type']) ?>" id="toast" role="status">
                    <?= icon($flash['type'] === 'ok' ? 'check' : 'alert', 18) ?><span><?= e($flash['text']) ?></span>
                    <button type="button" class="icon-btn" data-toast-close aria-label="Dismiss"><?= icon('x', 16) ?></button>
                </div>
            <?php endif; ?>
    <?php
}

/** Title block for detail pages, with a "back to overview" crumb. */
function page_head(string $title, string $subtitle, string $actions = ''): void
{
    ?>
    <section class="page-head page-head--detail">
        <div>
            <a class="crumb" href="index.php"><?= icon('chev-l', 14) ?> Overview</a>
            <h1><?= e($title) ?></h1>
            <p class="muted"><?= $subtitle ?></p>
        </div>
        <?php if ($actions): ?><div class="filters"><?= $actions ?></div><?php endif; ?>
    </section>
    <?php
}

function export_button(): string
{
    return '<a class="btn btn--ghost btn--sm" href="' . e(url_with(['export' => 'csv'])) . '">' . icon('download', 16) . ' Export CSV</a>';
}

function page_end(): void
{
    ?>
            <footer class="foot muted xsmall">© <?= date('Y', app_now()) ?> <?= e(APP_NAME) ?> · Faculty Portal · <?= e(current_term()['name']) ?></footer>
        </main>
    </div>
</div>
<button class="help-fab" data-modal="helpModal" aria-label="Help" title="Help"><?= icon('help', 22) ?></button>

<dialog class="modal" id="helpModal">
    <div class="modal-card">
        <header class="modal-head"><div><h2>Help</h2><p class="muted small">How to get around the faculty portal</p></div>
            <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></header>
        <div class="modal-body">
            <ul class="help-list">
                <li><span class="stat-icon tone-blue"><?= icon('search', 16) ?></span><div><strong>Search anything</strong><p>Press <kbd>Ctrl</kbd> + <kbd>K</kbd>, then type a name, student number, applicant ID or class code.</p></div></li>
                <li><span class="stat-icon tone-green"><?= icon('plus', 16) ?></span><div><strong>Add records</strong><p>Use the blue “Add” button at the top of Incoming enrollment, Campus schedule, Enrollment and Faculty evaluations.</p></div></li>
                <li><span class="stat-icon tone-purple"><?= icon('edit', 16) ?></span><div><strong>Edit or delete</strong><p>Every table row ends with a pencil (edit) and a bin (delete). Deleting always asks first.</p></div></li>
                <li><span class="stat-icon tone-orange"><?= icon('check-sq', 16) ?></span><div><strong>Encode grades</strong><p>Open Grade submissions and click a class to edit its roster, save a draft or submit final grades.</p></div></li>
                <li><span class="stat-icon tone-red"><?= icon('alert', 16) ?></span><div><strong>Log interventions</strong><p>On At-risk students, use the pencil on a row to record contact, counseling and notes.</p></div></li>
                <li><span class="stat-icon tone-blue"><?= icon('chart', 16) ?></span><div><strong>Explore charts</strong><p>Hover any chart for exact figures. Click bars, donut slices and funnel steps to filter the list; click a legend entry to hide or show a series.</p></div></li>
                <li><span class="stat-icon tone-green"><?= icon('download', 16) ?></span><div><strong>Export</strong><p>“Export CSV” downloads the list exactly as currently filtered.</p></div></li>
            </ul>
        </div>
        <footer class="modal-foot"><button type="button" class="btn btn--primary btn--sm" data-close>Got it</button></footer>
    </div>
</dialog>

<dialog class="modal modal--sm" id="confirmModal">
    <form method="post" action="actions.php" class="modal-card">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="">
        <input type="hidden" name="id" value="">
        <input type="hidden" name="return" value="">
        <div id="confirmExtra"></div>
        <div class="modal-body confirm-body">
            <span class="stat-icon tone-red"><?= icon('trash', 20) ?></span>
            <div><h2>Are you sure?</h2><p class="muted" id="confirmText"></p></div>
        </div>
        <footer class="modal-foot">
            <button type="button" class="btn btn--ghost btn--sm" data-close>Cancel</button>
            <button type="submit" class="btn btn--danger btn--sm"><?= icon('trash', 16) ?> <span id="confirmLabel">Delete</span></button>
        </footer>
    </form>
</dialog>
<div class="tip" id="tip" hidden></div>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
    <?php
}
