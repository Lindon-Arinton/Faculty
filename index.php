<?php
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
require_permission('academic.view', $user);
require __DIR__ . '/includes/data.php';

$briefView = q('view') === 'brief';

$hour = (int) date('G', $now);
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$collegeTotal = array_sum($colleges);
$collegeMax = max($colleges ?: [1]);
$gradeTotal = array_sum(array_column($gradeStatus, 'value'));
$gradePct = (int) round($gradeStatus[0]['value'] / max(1, $gradeTotal) * 100);
$trendVals = array_values($riskTrend);
$trendChange = ($trendVals[0] ?? 0) > 0 ? round((1 - end($trendVals) / $trendVals[0]) * 100, 1) : null;
$pipelineMax = max(1, $pipeline[0]['value']);
$conversion = round(end($pipeline)['value'] / $pipelineMax * 100, 1);
$tones = ['blue', 'purple', 'green'];

// Keeps the selected college when linking to a detail page.
$scoped = fn(string $url) => $collegeFilter ? $url . (str_contains($url, '?') ? '&' : '?') . $collegeQs : $url;

page_start('overview', 'Overview', $user);
?>
            <div class="dashboard-view<?= $briefView ? ' dashboard-view--brief' : '' ?>">
            <!-- Greeting -->
            <section class="page-head">
                <div>
                    <div class="eyebrow"><?= e(strtoupper(date('l, F j, Y', $now))) ?></div>
                    <h1><?= $briefView ? 'Academic dashboard' : e($greeting) . ', ' . e($user['name']) . '.' ?></h1>
                    <p class="muted">Academic monitoring for the authorized academic administrator.</p>
                </div>
                <form class="filters" method="get" action="index.php">
                    <?php if ($briefView): ?><input type="hidden" name="view" value="brief"><?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url_with(['view'=>$briefView ? null : 'brief'])) ?>"><?= $briefView ? 'Full overview' : 'Dashboard brief' ?></a>
                    <div class="select">
                        <select name="college" aria-label="College" data-autosubmit>
                            <option value="">All colleges</option>
                            <?php foreach (array_keys(colleges()) as $c): ?><option value="<?= e($c) ?>"<?= $c === $collegeFilter ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                        <?= icon('chev-d', 16) ?>
                    </div>

                </form>
            </section>

            <!-- Stat cards -->
            <section class="grid grid-5" id="bat405Kpis" aria-label="Five institutional KPI cards">
                <?php foreach ($stats as $s): ?>
                    <?= stat_card($s['label'], $s['value'], e($s['note']), $s['icon'], $s['tone'], $s['href']) ?>
                <?php endforeach; ?>
            </section>

            <?php foreach ($dashboardWarnings as $warning): ?>
            <section class="notice" role="alert" data-dashboard-warning><span><strong><?= e($warning['label']) ?></strong> · <?= e($warning['text']) ?></span></section>
            <?php endforeach; ?>
            <!-- Institutional KPIs -->
            <section class="card panel">
                <div class="panel-head">
                    <div>
                        <h2>Institutional KPIs</h2>
                        <p class="muted small">School-wide academic performance at a glance</p>
                    </div>
                    <span class="live"><span class="live-dot"></span>Updated today, <?= e(date('g:i A', $now)) ?></span>
                </div>

                <div class="grid grid-3 kpis">
                    <a class="kpi kpi--link" href="enrollment.php<?= $collegeQs ? '?' . e($collegeQs) : '' ?>">
                        <div class="kpi-head">
                            <span class="kpi-title"><?= e($breakdownLabel) ?></span>
                            <?php if ($enrollmentGrowth !== null): ?><span class="pill pill--green"><?= icon('arrow-up', 12) ?> <?= e((string) $enrollmentGrowth) ?>%</span><?php endif; ?>
                        </div>
                        <div class="kpi-value"><?= number_format($collegeTotal) ?></div>
                        <ul class="bars" data-chart="enrollment">
                            <?php foreach ($colleges as $name => $count): ?>
                                <li data-tip="<?= e($name . '|' . number_format($count) . ' students · ' . pct($count, $collegeTotal, 1) . '% of enrollment') ?>">
                                    <span class="bars-label"><?= e($name) ?></span>
                                    <span class="bars-track"><span class="bars-fill" style="--w: <?= round($count / $collegeMax * 88, 1) ?>%"></span></span>
                                    <span class="bars-num"><?= number_format($count) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$colleges): ?><p class="empty">No enrollment records available.</p><?php endif; ?>
                    </a>

                    <a class="kpi kpi--link" href="<?= e($scoped('grades.php')) ?>">
                        <div class="kpi-head">
                            <span class="kpi-title">Grade submission rate</span>
                            <span class="pill pill--grey"><?= !$hasCurrentTerm ? 'No active term' : ($daysToDeadline >= 0 ? 'Due ' . e(date('M j', $deadline)) : 'Past due') ?></span>
                        </div>
                        <div class="kpi-value"><?= $gradeTotal ? $gradePct . '%' : '—' ?></div>
                        <div data-chart="grade-submission"><?= $gradeTotal ? donut_chart(array_map(fn($g) => [$g['label'], $g['value'], $g['color']], $gradeStatus), $gradePct . '%', 'submitted') : '<p class="empty">No class sections available to calculate a submission rate.</p>' ?></div>
                        <?php if ($gradesPending > 0): ?><div class="kpi-note tone-orange-soft"><?= icon('alert', 14) ?> <?= $gradesPending ?> classes still need final grades</div><?php endif; ?>
                    </a>

                    <a class="kpi kpi--link" data-chart="risk-trend" href="<?= e($scoped('at-risk.php')) ?>">
                        <div class="kpi-head">
                            <span class="kpi-title">At-risk student trend</span>
                            <span class="pill pill--grey"><?= $trendChange === null ? '' : icon('arrow-dn', 12) ?> <?= $trendChange === null ? 'No prior baseline' : e((string) $trendChange) . '%' ?></span>
                        </div>
                        <div class="kpi-value"><?= $trendVals ? (int) end($trendVals) : '—' ?></div>
                        <?= line_chart($riskTrend) ?>
                    </a>
                </div>
            </section>

            <div class="dashboard-extended">
            <?php require __DIR__ . '/includes/prediction-panel.php'; ?>

            <!-- Incoming enrollment -->
            <section class="card panel panel--flush">
                <div class="panel-head panel-pad">
                    <div>
                        <div class="eyebrow eyebrow--blue"><?= e(intake_label()) ?></div>
                        <h2>Incoming student enrollment</h2>
                        <p class="muted small"><?= $collegeFilter ? e($collegeFilter) . ' admissions and enrollment progress' : 'School-wide admissions and enrollment progress across all colleges' ?></p>
                    </div>
                    <a href="<?= e($scoped('incoming.php')) ?>" class="link-strong">View admissions report <?= icon('chev-r', 16) ?></a>
                </div>

                <div class="metric-row">
                    <?php foreach ($admissions as $a): ?>
                        <div class="metric">
                            <div class="metric-label"><?= e($a['label']) ?></div>
                            <div class="metric-value"><?= e($a['value']) ?></div>
                            <div class="metric-note<?= !empty($a['up']) ? ' up' : '' ?>"><?= !empty($a['up']) ? icon('arrow-up', 11) . ' ' : '' ?><?= e($a['note']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="split">
                    <div class="split-col">
                        <div class="sub-head">
                            <div>
                                <h3>Enrollment pipeline</h3>
                                <p class="muted xsmall">Conversion from application to enrollment</p>
                            </div>
                            <span class="conv"><?= $appTotal ? e((string) $conversion) . '% overall conversion' : 'Conversion unavailable: no applications' ?></span>
                        </div>
                        <?php if ($appTotal): ?><div class="funnel">
                            <?php foreach ($pipeline as $step): ?>
                                <div class="funnel-step funnel-<?= e($step['tone']) ?>" style="--w: <?= round($step['value'] / $pipelineMax * 100, 1) ?>%" data-tip="<?= e($step['label'] . '|' . number_format($step['value']) . ' applicants · ' . pct($step['value'], $pipelineMax, 1) . '% of applications') ?>">
                                    <span><?= e($step['label']) ?></span><strong><?= number_format($step['value']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?><p class="empty">No admissions data available.</p><?php endif; ?>
                        <?php if ($incomplete > 0): ?><div class="notice">
                            <?= icon('alert', 16) ?>
                            <span><strong><?= number_format($incomplete) ?> applicants</strong> still have incomplete requirements</span>
                            <a href="<?= e($scoped('incoming.php?status=Pending+documents')) ?>" class="notice-link">Review</a>
                        </div><?php endif; ?>
                    </div>

                    <div class="split-col">
                        <div class="sub-head">
                            <div>
                                <h3>Recent applicants</h3>
                                <p class="muted xsmall">Latest admissions activity · <span id="applicantCount"><?= count($applicants) ?></span> shown</p>
                            </div>
                        </div>
                        <div class="chips" role="tablist" aria-label="Filter applicants">
                            <?php foreach (['All', 'For review', 'Qualified', 'Pending documents', 'Enrolled'] as $i => $chip): ?>
                                <button class="chip<?= $i === 0 ? ' is-active' : '' ?>" data-filter="<?= e($chip) ?>"><?= e($chip) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <ul class="list" id="applicantList">
                            <?php foreach ($applicants as $ap): ?>
                                <li class="list-row" data-status="<?= e($ap['status']) ?>" data-href="incoming.php?q=<?= e(urlencode($ap['id'])) ?>">
                                    <?= avatar($ap['name'], status_tone($ap['status']), 'avatar--lg') ?>
                                    <div class="list-main">
                                        <strong><?= e($ap['name']) ?></strong>
                                        <span><?= e($ap['id']) ?> · <?= e($ap['program_name']) ?></span>
                                    </div>
                                    <?= tag($ap['status']) ?>
                                    <span class="list-time"><?= e(rel_time($ap['updated'])) ?></span>
                                    <?= icon('chev-r', 16, 'chev') ?>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-empty"<?= $applicants ? ' hidden' : '' ?>>No recent applicants with this status. <a class="link" href="incoming.php">See all applicants</a></li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Schedule + grade submission -->
            <section class="grid grid-2-1">
                <div id="schedule" class="card panel">
                    <div class="panel-head">
                        <div>
                            <h2>Campus class schedule</h2>
                            <p class="muted small"><?= e(date('l', $now)) ?> timetable · <?= e(current_term()['name']) ?><?= $termOver ? ' (classes ended)' : '' ?></p>
                        </div>
                        <a href="<?= e($scoped('schedule.php?day=' . $todayKey)) ?>" class="link-strong">View full schedule <?= icon('chev-r', 16) ?></a>
                    </div>
                    <ul class="sched">
                        <?php foreach ($schedule as $i => $cls): $tone = $tones[$i % 3]; ?>
                            <li class="sched-row" data-href="schedule.php?day=<?= e($todayKey) ?>&amp;q=<?= e(urlencode($cls['code'])) ?>">
                                <div class="sched-time"><strong><?= e(clock($cls['start'])) ?></strong><span><?= e(clock($cls['end'], true)) ?></span></div>
                                <span class="sched-bar tone-bar-<?= $tone ?>"></span>
                                <div class="sched-main">
                                    <div class="sched-title"><span class="code code--<?= $tone ?>"><?= e($cls['code']) ?></span><strong><?= e($cls['title']) ?></strong></div>
                                    <div class="sched-meta">
                                        <span><?= icon('users', 14) ?> <?= e($cls['section']) ?> · <?= e($cls['faculty']) ?></span>
                                        <span><?= icon($cls['mode'] === 'Online' ? 'video' : 'pin', 14) ?> <?= e($cls['room']) ?></span>
                                        <?= tag($cls['mode'], null, 'tag--sm') ?>
                                    </div>
                                </div>
                                <?= icon('chev-r', 16, 'chev') ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$schedule): ?><li class="list-empty">No classes scheduled today.</li><?php endif; ?>
                    </ul>
                    <div class="panel-foot">
                        <span><?= icon('building', 16) ?> Campus utilization <strong><?= $totalRooms ?> rooms · <?= number_format(count($classesAll)) ?> class sections</strong></span>
                        <a class="<?= $conflictCount ? 'warn' : 'ok' ?>" href="schedule.php#conflicts"><?= icon('alert', 14) ?> <?= $conflictCount ?> room conflict<?= $conflictCount === 1 ? '' : 's' ?> flagged</a>
                    </div>
                </div>

                <div id="grades" class="card panel">
                    <div class="panel-head">
                        <div>
                            <h2><?= $collegeFilter ? e($collegeFilter) . ' grade submission' : 'School-wide grade submission' ?></h2>
                            <p class="muted small"><?= $collegeFilter ? 'Encoding progress by year level' : 'College-level encoding progress' ?></p>
                        </div>
                        <a class="icon-btn" href="<?= e($scoped('grades.php')) ?>" aria-label="Open grade submissions"><?= icon('more', 18) ?></a>
                    </div>
                    <?php if (!$gradeByCollege): ?><p class="empty">No class sections available.</p><?php endif; ?>
                    <ul class="progress-list">
                        <?php foreach ($gradeByCollege as $g): $done = $g['pending'] === 0; ?>
                            <li>
                                <a href="<?= e($g['link']) ?>">
                                    <div class="progress-head">
                                        <span><strong><?= e($g['college']) ?></strong> <span class="muted xsmall"><?= (int) $g['classes'] ?> classes</span></span>
                                        <span class="tag tag--sm <?= $done ? 'tag--green' : 'tag--orange' ?>"><?= $done ? icon('check', 12) . ' Submitted' : 'In progress' ?></span>
                                    </div>
                                    <div class="progress" data-tip="<?= e($g['college'] . '|' . (int) $g['pct'] . '% of grades encoded|' . ($g['classes'] - $g['pending']) . ' of ' . $g['classes'] . ' classes submitted') ?>"><span style="--w: <?= (int) $g['pct'] ?>%"></span></div>
                                    <div class="progress-foot">
                                        <span><?= (int) $g['pct'] ?>% encoded</span>
                                        <span><?= $done ? 'All submitted' : (int) $g['pending'] . ' pending' ?></span>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= e($scoped('grades.php?status=pending')) ?>" class="btn btn--primary btn--block">Review all pending submissions <?= icon('chev-r', 16) ?></a>
                </div>
            </section>

            <!-- Student enrollment + risk -->
            <section class="grid grid-2">
                <div class="card panel panel--list">
                    <div class="panel-head panel-pad">
                        <div>
                            <h2>Student enrollment</h2>
                            <p class="muted small">Latest enrollees · <?= number_format($collegeTotal) ?> students</p>
                        </div>
                        <a href="<?= e($scoped('enrollment.php')) ?>" class="link-strong">View all students <?= icon('chev-r', 16) ?></a>
                    </div>
                    <ul class="list list--padded">
                        <?php foreach ($students as $st): ?>
                            <li class="list-row" data-href="enrollment.php?q=<?= e(urlencode($st['id'])) ?>">
                                <?= avatar($st['name'], null, 'avatar--lg') ?>
                                <div class="list-main">
                                    <strong><?= e($st['name']) ?></strong>
                                    <span><?= e($st['id']) ?></span>
                                </div>
                                <div class="list-right">
                                    <strong><?= e($st['section']) ?> · <?= e($st['college']) ?></strong>
                                    <span>Enrolled <?= e(rel_time($st['enrolled_at'])) ?></span>
                                </div>
                                <?= icon('chev-r', 16, 'chev') ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$students): ?><li class="list-empty">No student records loaded.</li><?php endif; ?>
                    </ul>
                </div>

                <div class="card panel panel--list">
                    <div class="panel-head panel-pad">
                        <div>
                            <h2>School-wide risk monitoring</h2>
                            <p class="muted small">Students across all colleges who may need support</p>
                        </div>
                        <?= tag($hasRiskEvidence ? count($riskAll) . ' observed cases' : 'Assessment unavailable', $hasRiskEvidence ? 'red' : 'slate') ?>
                    </div>
                    <ul class="list list--padded">
                        <?php foreach ($atRisk as $r): ?>
                            <li class="list-row" data-href="at-risk.php?q=<?= e(urlencode($r['id'])) ?>">
                                <?= avatar($r['name'], 'slate', 'avatar--lg') ?>
                                <div class="list-main">
                                    <strong><?= e($r['name']) ?></strong>
                                    <span><?= e($r['college']) ?> · <?= e($r['section']) ?></span>
                                </div>
                                <div class="list-right">
                                    <strong class="text-<?= e(status_tone($r['type'])) ?>"><?= e($r['type']) ?></strong>
                                    <span><?= e($r['detail']) ?></span>
                                </div>
                                <?= icon('chev-r', 16, 'chev') ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$atRisk): ?><li class="list-empty"><?= $hasRiskEvidence ? 'No flags in the available evidence.' : 'No grade or backlog evidence available.' ?></li><?php endif; ?>
                    </ul>
                    <div class="panel-pad panel-pad--bottom">
                        <a href="<?= e($scoped('at-risk.php')) ?>" class="btn btn--ghost btn--block"><?= $hasRiskEvidence ? 'Review ' . count($riskAll) . ' observed at-risk students' : 'Open academic risk monitoring' ?></a>
                    </div>
                </div>
            </section>

            <!-- Faculty evaluation -->
            <section class="eval">
                <div class="eval-left">
                    <div class="eyebrow eyebrow--light">Faculty evaluation</div>
                    <h2>University-wide evaluation results</h2>
                    <p>Review anonymous, aggregate student feedback for every faculty member across all colleges. Individual student responses remain private.</p>
                    <a href="<?= e($scoped('evaluations.php')) ?>" class="btn btn--white">View complete report <?= icon('chev-r', 16) ?></a>
                    <div class="eval-stats">
                        <?php foreach ($evalSummary as $es): ?>
                            <div><strong><?= e($es['value']) ?></strong><span><?= e($es['label']) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="eval-right">
                    <div class="eval-cols"><span>Faculty member</span><span>Rating</span></div>
                    <ul>
                        <?php foreach ($evaluations as $ev): ?>
                            <li class="eval-row" data-href="evaluations.php?q=<?= e(urlencode(preg_replace('/^(Dr|Prof|Engr)\.\s+/', '', $ev['name']))) ?>">
                                <?= avatar($ev['name'], 'glass', 'avatar--lg') ?>
                                <div class="list-main">
                                    <strong><?= e($ev['name']) ?></strong>
                                    <span><?= e($ev['college']) ?> · <?= (int) $ev['responses'] ?> responses</span>
                                </div>
                                <div class="eval-rating">
                                    <strong><?= number_format($ev['rating'], 1) ?></strong>
                                    <?= stars($ev['rating']) ?>
                                </div>
                                <?= icon('chev-r', 16, 'chev') ?>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$evaluations): ?><li class="list-empty"><?= $responses ? 'No faculty meet the 60-response display threshold.' : 'No faculty evaluation responses recorded.' ?></li><?php endif; ?>
                    </ul>
                </div>
            </section>
            </div>
            <section class="card panel" aria-label="SETE dimension visualization">
                <div class="panel-head"><div><h2>SETE evaluation dimensions</h2><p class="muted small">Response-weighted average scores out of 5 · <?= e(current_term()['name']) ?></p></div><a class="link-strong" href="<?= e($scoped('evaluations.php')) ?>">View SETE summaries</a></div>
                <div data-chart="sete-dimensions"><?= $responses ? bar_list($seteCategories, 5, 'rating', 'orange block') : '<p class="empty">No evaluation responses available. Teaching effectiveness cannot be assessed yet.</p>' ?></div>
                <p class="muted small">Consider participation, sample size, course context and other evidence. SETE alone must not determine personnel decisions. External predictions do not affect these scores.</p>
            </section>
            <?php if ($briefView): ?><p class="muted small">BAT 405 · Institutional records only. External demonstration predictions: <a href="predictions.php">Outcome prediction</a>. Proposed review thresholds: any overdue grade submission, urgent support case, or SETE below 3.5/5 with at least 30 responses. Check participation and context.</p><?php endif; ?>
            </div>
<?php page_end(); ?>
