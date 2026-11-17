<?php
/**
 * One class section: its details, roster and grades.
 * Grades are edited here, saved as a draft or submitted as final.
 */
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
require_permission('academic.view', $user);

const GRADE_OPTIONS = ['1.00', '1.25', '1.50', '1.75', '2.00', '2.25', '2.50', '2.75', '3.00', '5.00', 'INC', 'DRP'];

$class = array_column(classes(), null, 'id')[(int) q('id')] ?? null;
if (!$class) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'That class could not be found. It may have been deleted.'];
    header('Location: grades.php');
    exit;
}

$roster = rows(
    'SELECT s.id, s.student_no, s.first_name, s.middle_name, s.last_name, s.status, e.midterm_grade, e.final_grade
     FROM enrollments e JOIN students s ON s.id = e.student_id
     WHERE e.section_id = ? ORDER BY s.last_name, s.first_name, s.middle_name',
    [$class['id']]
);
$submitted = $class['grade'] === 'Submitted';
$locked = $submitted || !can('academic.manage') || is_historical_term();
$encoded = count(array_filter($roster, fn($r) => $r['final_grade'] !== null));

maybe_export_csv($roster, [
    'Student no.' => 'student_no', 'Name' => fn($r) => $r['last_name'] . ', ' . $r['first_name'] . ($r['middle_name'] ? ' ' . $r['middle_name'] : ''),
    'Midterm' => fn($r) => $r['midterm_grade'] ?? '', 'Final' => fn($r) => $r['final_grade'] ?? '',
], 'grades-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $class['key'])));

// Final grade distribution
$buckets = ['1.00–1.50' => 0, '1.75–2.25' => 0, '2.50–3.00' => 0, '5.00 (failed)' => 0, 'INC' => 0, 'DRP' => 0, 'Not encoded' => 0];
$numeric = [];
foreach ($roster as $r) {
    $g = $r['final_grade'];
    $key = match (true) {
        $g === null => 'Not encoded',
        $g === 'INC', $g === 'DRP' => $g,
        $g === '5.00' => '5.00 (failed)',
        (float) $g <= 1.5 => '1.00–1.50',
        (float) $g <= 2.25 => '1.75–2.25',
        default => '2.50–3.00',
    };
    $buckets[$key]++;
    if (is_numeric($g)) {
        $numeric[] = (float) $g;
    }
}
$passed = count(array_filter($numeric, fn($g) => $g <= 3.0));

$gradeSelect = function (string $name, ?string $current, bool $locked): string {
    $html = '<select class="f-input f-input--grade" name="' . e($name) . '"' . ($locked ? ' disabled' : '') . ' aria-label="Grade">';
    $html .= '<option value="">—</option>';
    foreach (GRADE_OPTIONS as $g) {
        $html .= '<option' . ($g === $current ? ' selected' : '') . '>' . $g . '</option>';
    }
    return $html . '</select>';
};

$self = 'class.php?id=' . $class['id'];

page_start('grades', $class['key'], $user);
?>
    <section class="page-head page-head--detail">
        <div>
            <a class="crumb" href="grades.php"><?= icon('chev-l', 14) ?> Grade submissions</a>
            <h1><?= e($class['title']) ?></h1>
            <p class="muted"><?= e($class['code']) ?> · <?= e($class['section']) ?> · <?= e($class['college']) ?> · <?= (int) $class['units'] ?> units · <?= e(current_term()['name']) ?></p>
        </div>
        <div class="filters">
            <?= export_button() ?>
            <?= modal_button('classModal', icon('edit', 16) . ' Edit class', 'btn btn--ghost btn--sm', class_fill($class), 'Edit class ' . $class['key']) ?>
            <?= confirm_button('section_delete', $class['id'], 'Delete class ' . $class['key'] . '? Its meetings, roster of ' . count($roster) . ' students and their grades are deleted too.',
                icon('trash', 16) . ' Delete class', 'btn btn--ghost btn--sm btn--ghost-danger', [], 'schedule.php', 'Delete class') ?>
        </div>
    </section>

    <section class="grid grid-4">
        <?= stat_card('Students', (string) count($roster), 'On the class roster', 'users', 'blue') ?>
        <?= stat_card('Final grades encoded', $encoded . ' / ' . count($roster), pct($encoded, count($roster)) . '% complete', 'check-sq', $encoded === count($roster) && $roster ? 'green' : 'orange') ?>
        <?= stat_card('Class average', $numeric ? number_format(array_sum($numeric) / count($numeric), 2) : '—', $numeric ? pct($passed, count($numeric)) . '% passing' : 'No numeric grades yet', 'star', 'purple') ?>
        <?= stat_card('Status', $class['grade'], $class['updated'] ? 'Updated ' . e(rel_time($class['updated'])) : 'No grades saved yet', 'clock', status_tone($class['grade']) === 'green' ? 'green' : (status_tone($class['grade']) === 'red' ? 'red' : 'orange')) ?>
    </section>

    <section class="grid grid-2-1">
        <div class="card panel panel--flush" id="list">
            <form method="post" action="actions.php" id="gradesForm">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="grades_save">
                <input type="hidden" name="id" value="<?= (int) $class['id'] ?>">
                <input type="hidden" name="return" value="<?= e($self) ?>#list">
                <div class="table-toolbar">
                    <div>
                        <h2>Roster and grades</h2>
                        <p class="muted small"><?= $submitted ? 'Final grades are submitted and locked. Reopen to change them.' : 'Scale 1.00 (highest) to 3.00 (passing) · 5.00 failed · INC incomplete · DRP dropped' ?></p>
                    </div>
                    <div class="toolbar-form">
                        <?php if (!can('academic.manage') || is_historical_term()): ?><span class="muted small">Read-only access</span>
                        <?php elseif ($submitted): ?>
                            <?= tag('Submitted') ?>
                            <button type="submit" name="do" value="reopen" class="btn btn--ghost btn--sm"><?= icon('edit', 16) ?> Reopen for editing</button>
                        <?php else: ?>
                            <span class="unsaved" id="unsaved" hidden><?= icon('alert', 14) ?> Unsaved changes</span>
                            <button type="submit" name="do" value="save" class="btn btn--ghost btn--sm" <?= $roster ? '' : 'disabled' ?>>Save draft</button>
                            <button type="submit" name="do" value="submit" class="btn btn--primary btn--sm" <?= $roster ? '' : 'disabled' ?>><?= icon('check', 16) ?> Submit final grades</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data data--rows">
                        <thead><tr><th>#</th><th>Student</th><th>Status</th><th>Midterm</th><th>Final</th><th><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($roster as $i => $r): $name = person_name($r['first_name'], $r['middle_name'], $r['last_name']); ?>
                            <tr>
                                <td class="muted"><?= $i + 1 ?></td>
                                <td><div class="cell-person"><?= avatar($name) ?><div><strong><?= e($r['last_name'] . ', ' . $r['first_name']) ?></strong><span><a class="link-plain" href="enrollment.php?q=<?= e(urlencode($r['student_no'])) ?>#list"><?= e($r['student_no']) ?></a></span></div></div></td>
                                <td><?= tag($r['status']) ?></td>
                                <td><?= $gradeSelect('mid[' . $r['id'] . ']', $r['midterm_grade'], $locked) ?></td>
                                <td><?= $gradeSelect('fin[' . $r['id'] . ']', $r['final_grade'], $locked) ?></td>
                                <td><div class="row-actions"><?= confirm_button('enrollment_remove', $class['id'], 'Remove ' . $name . ' from ' . $class['key'] . '? Their grades in this class are deleted.', '', 'icon-btn icon-btn--danger', ['student' => $r['id']], $self . '#list', 'Remove from class') ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$roster): ?><tr><td colspan="6" class="empty">No students are enrolled in this class yet. Add one with the form on the right.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

        <div class="stack">
            <div class="card panel">
                <div class="panel-head"><div><h2>Class details</h2><p class="muted small"><?= e($class['kind']) ?> · <?= e($class['mode']) ?></p></div><?= tag($class['mode']) ?></div>
                <ul class="kv kv--flush">
                    <li><span>Faculty</span><strong><?= e($class['faculty']) ?></strong></li>
                    <li><span>Schedule</span><strong><?= e($class['schedule']) ?></strong></li>
                    <li><span><?= $class['mode'] === 'Online' ? 'Platform' : 'Room' ?></span><strong><?= e($class['room']) ?></strong></li>
                </ul>
            </div>
            <div class="card panel">
                <div class="panel-head"><div><h2>Final grade distribution</h2><p class="muted small">Number of students in each range</p></div></div>
                <?= bar_list($buckets, null, 'number', 'purple') ?>
            </div>
            <?php if (can('academic.manage') && !is_historical_term()): ?><div class="card panel">
                <div class="panel-head"><div><h2>Add a student</h2><p class="muted small">For irregular students taking this class</p></div></div>
                <form method="post" action="actions.php" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="enrollment_add">
                    <input type="hidden" name="id" value="<?= (int) $class['id'] ?>">
                    <input type="hidden" name="return" value="<?= e($self) ?>#list">
                    <input class="f-input" name="student_no" placeholder="Student no., e.g. 2023-10482" pattern="\d{4}-\d{5}" maxlength="12" required aria-label="Student number">
                    <button type="submit" class="btn btn--primary btn--sm"><?= icon('plus', 16) ?> Add</button>
                </form>
            </div><?php endif; ?>
        </div>
    </section>
    <?= class_modal() ?>
<?php page_end(); ?>
