<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();

$days = WEEK_DAYS;
$now = app_now();
$todayKey = date('D', $now) === 'Sun' ? 'Mon' : date('D', $now);
$inSession = $now <= strtotime(current_term()['ends_on'] . ' 23:59:59');
$day = isset($days[q('day')]) ? q('day') : $todayKey;
$college = isset(colleges()[q('college')]) ? q('college') : '';
$mode = q('mode');
$search = q('q');

$inCollege = fn($c) => $college === '' || $c['college'] === $college;
$allClasses = array_values(array_filter(classes(), $inCollege));

$daySessions = array_values(array_filter(sessions_on($day), $inCollege));
$rows = search_rows($daySessions, $search, ['code', 'title', 'section', 'faculty', 'room']);
$rows = filter_rows($rows, ['mode' => $mode]);

maybe_export_csv($rows, [
    'Day' => fn() => $days[$day], 'Start' => fn($r) => clock($r['start'], true), 'End' => fn($r) => clock($r['end'], true),
    'Code' => 'code', 'Title' => 'title', 'Section' => 'section', 'Faculty' => 'faculty', 'College' => 'college',
    'Room' => 'room', 'Mode' => 'mode', 'Students' => 'students',
], 'schedule-' . strtolower($day));

$page = paginate($rows, 40);

// Group this page of sessions by start time
$slots = [];
foreach ($page['items'] as $s) {
    $slots[$s['start']][] = $s;
}
ksort($slots);

$scopeColleges = $college ? [$college => colleges()[$college]] : colleges();
$totalRooms = array_sum(array_map(fn($c) => count($c['rooms']), $scopeColleges));
$onsiteToday = array_filter($daySessions, fn($s) => $s['mode'] === 'Onsite');
$roomsToday = array_unique(array_column($onsiteToday, 'room'));
$onlineToday = count(array_filter($daySessions, fn($s) => $s['mode'] === 'Online'));
$conflicts = array_values(array_filter(room_conflicts(), fn($x) => $inCollege($x['a']) || $inCollege($x['b'])));
$offCampus = count(array_filter($allClasses, fn($c) => $c['mode'] === 'Off-campus'));

// Room use per building on the selected day
$utilization = [];
foreach ($scopeColleges as $name => $c) {
    $used = count(array_intersect($c['rooms'], $roomsToday));
    $share = count($c['rooms']) ? $used / count($c['rooms']) * 100 : 0;
    $utilization[$name] = [$share, $used . ' of ' . count($c['rooms']) . ' rooms · ' . round($share) . '%'];
}

// Sessions per day this week
$weekly = [];
$dayCounts = [];
foreach ($days as $k => $label) {
    $dayCounts[$k] = count(array_filter(sessions_on($k), $inCollege));
    $weekly[$label] = $dayCounts[$k];
}


page_start('schedule', 'Campus schedule', $user);
page_head(
    'Campus schedule',
    'Weekly timetable · ' . e(current_term()['name']) . ' · ' . number_format(count($allClasses) - $offCampus) . ' class sections meet each week' . ($college ? ' in ' . e($college) : '') . ($inSession ? '' : ' · Classes ended ' . e(date('M j', strtotime(current_term()['ends_on'])))),
    '<form method="get" class="filters"><input type="hidden" name="day" value="' . e($day) . '">' . select_filter('college', array_keys(colleges()), $college, 'All colleges') . '</form>' . export_button()
    . modal_button('classModal', icon('plus', 16) . ' Add class', 'btn btn--primary btn--sm', ['meetings' => [['day' => $day, 'start' => '08:00', 'end' => '09:00', 'room' => '']]])
);
?>
    <nav class="day-tabs" aria-label="Day of week">
        <?php foreach ($days as $k => $label): ?>
            <a href="<?= e(url_with(['day' => $k])) ?>" class="day-tab<?= $k === $day ? ' is-active' : '' ?>"<?= $k === $day ? ' aria-current="true"' : '' ?>>
                <span><?= e($k) ?><?= $k === $todayKey && $inSession ? ' <em>Today</em>' : '' ?></span>
                <strong><?= $dayCounts[$k] ?></strong>
                <small>sessions</small>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="grid grid-4">
        <?= stat_card('Sessions', number_format(count($daySessions)), 'Scheduled on ' . e($days[$day]), 'calendar', 'blue') ?>
        <?= stat_card('Rooms in use', count($roomsToday) . ' / ' . $totalRooms, pct(count($roomsToday), $totalRooms) . '% of campus rooms', 'building', 'purple') ?>
        <?= stat_card('Online sessions', number_format($onlineToday), 'Google Meet and MS Teams', 'video', 'green', url_with(['mode' => 'Online']) . '#list') ?>
        <?= stat_card('Room conflicts', (string) count($conflicts), count($conflicts) ? 'Double-booked rooms this week' : 'No double bookings', 'alert', count($conflicts) ? 'red' : 'green', '#conflicts') ?>
    </section>

    <?php if ($conflicts): ?>
    <section class="card panel panel--warn" id="conflicts">
        <div class="panel-head">
            <div>
                <h2><?= count($conflicts) ?> room conflict<?= count($conflicts) === 1 ? '' : 's' ?> flagged</h2>
                <p class="muted small">Two classes are booked in the same room at the same time. Move one of them to a free room.</p>
            </div>
        </div>
        <ul class="conflicts">
            <?php foreach ($conflicts as $x): ?>
                <li>
                    <div class="conflict-where">
                        <strong><?= icon('pin', 14) ?> <?= e($x['room']) ?></strong>
                        <span><?= e(implode(', ', $x['days'])) ?> · <?= e(clock($x['start'], true)) ?>–<?= e(clock($x['end'], true)) ?></span>
                    </div>
                    <?php foreach ([$x['a'], $x['b']] as $c): ?>
                        <div class="conflict-class">
                            <span class="code code--red"><?= e($c['code']) ?></span>
                            <div><strong><?= e($c['title']) ?></strong><span><?= e($c['section']) ?> · <?= e($c['faculty']) ?></span></div>
                            <?= modal_button('classModal', icon('edit', 14) . ' Move', 'btn btn--ghost btn--xs', class_fill($c), 'Edit class ' . $c['key']) ?>
                        </div>
                    <?php endforeach; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="grid grid-2-1">
        <div class="card panel panel--flush" id="list">
            <div class="table-toolbar">
                <div>
                    <h2><?= e($days[$day]) ?> timetable</h2>
                    <p class="muted small"><?= number_format(count($rows)) ?> of <?= number_format(count($daySessions)) ?> sessions match</p>
                </div>
                <form method="get" class="toolbar-form" action="schedule.php#list">
                    <input type="hidden" name="day" value="<?= e($day) ?>">
                    <input type="hidden" name="college" value="<?= e($college) ?>">
                    <label class="search-field"><?= icon('search', 16) ?><input type="search" name="q" value="<?= e($search) ?>" placeholder="Class, section, faculty or room"></label>
                    <?= select_filter('mode', ['Onsite', 'Online'], $mode, 'All modes') ?>
                </form>
            </div>

            <?php foreach ($slots as $start => $list): ?>
                <div class="slot-head">
                    <strong><?= e(clock($start, true)) ?></strong>
                    <span><?= count($list) ?> class<?= count($list) === 1 ? '' : 'es' ?></span>
                </div>
                <ul class="sched sched--dense">
                    <?php foreach ($list as $cls): $tone = status_tone($cls['mode']); ?>
                        <li class="sched-row">
                            <span class="sched-bar tone-bar-<?= $tone === 'green' ? 'green' : 'blue' ?>"></span>
                            <div class="sched-main">
                                <div class="sched-title"><span class="code code--<?= $tone === 'green' ? 'green' : 'blue' ?>"><?= e($cls['code']) ?></span><strong><?= e($cls['title']) ?></strong></div>
                                <div class="sched-meta">
                                    <span><?= icon('users', 14) ?> <?= e($cls['section']) ?> · <?= e($cls['faculty']) ?></span>
                                    <span><?= icon($cls['mode'] === 'Online' ? 'video' : 'pin', 14) ?> <?= e($cls['room']) ?></span>
                                    <span><?= icon('building', 14) ?> <?= e($cls['college']) ?></span>
                                </div>
                            </div>
                            <div class="sched-side">
                                <?= tag($cls['mode'], null, 'tag--sm') ?>
                                <span class="muted xsmall"><?= e(clock($cls['start'])) ?>–<?= e(clock($cls['end'], true)) ?> · <?= (int) $cls['students'] ?> students</span>
                            </div>
                            <div class="row-actions">
                                <a class="icon-btn" href="class.php?id=<?= (int) $cls['id'] ?>" aria-label="Open class roster" title="Roster and grades"><?= icon('users', 16) ?></a>
                                <?= modal_button('classModal', icon('edit', 16), 'icon-btn', class_fill($cls), 'Edit class ' . $cls['key'], 'Edit') ?>
                                <?= confirm_button('section_delete', $cls['id'], 'Delete class ' . $cls['key'] . '? Its meetings, roster of ' . $cls['students'] . ' students and their grades are deleted too.') ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <?php if (!$slots): ?><p class="empty">No sessions match these filters.</p><?php endif; ?>
            <?= pagination($page, 'sessions') ?>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head">
                    <div>
                        <h2>Room use by building</h2>
                        <p class="muted small">Rooms with at least one class on <?= e($days[$day]) ?></p>
                    </div>
                </div>
                <?= bar_list($utilization, 100, 'pct', 'block', $college ? [] : array_combine(array_keys($utilization), array_map(fn($c) => url_with(['college' => $c]) . '#list', array_keys($utilization)))) ?>
            </div>
            <div class="card panel">
                <div class="panel-head">
                    <div>
                        <h2>Weekly load</h2>
                        <p class="muted small">Class sessions per day</p>
                    </div>
                </div>
                <?= bar_list($weekly, null, 'number', 'purple', array_combine(array_values($days), array_map(fn($k) => url_with(['day' => $k]) . '#list', array_keys($days)))) ?>
                <p class="note"><?= icon('book', 14) ?> <?= $offCampus ?> practicum and internship sections are off-campus, with no scheduled meetings.</p>
            </div>
        </div>
    </section>
    <?= class_modal() ?>
<?php page_end(); ?>
