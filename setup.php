<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
require_permission('academic.manage', $user);
page_start('setup', 'Reference setup', $user);
page_head('Reference setup', 'Enter official university reference information. No demonstration records are created.');
if(is_historical_term()){ echo '<section class="card panel"><p>Historical terms are read-only. Select the current semester before updating reference catalogs.</p></section>'; page_end(); exit; }
$collegeOptions = array_column(rows('SELECT id, name FROM colleges ORDER BY name'), 'name', 'id');
?>
<section class="card panel"><p class="muted">Start with a college, then programs, courses and rooms. Add a current term before scheduling classes or enrolling students. These reference records do not create user accounts. Existing references are preserved.</p></section>
<div class="grid grid-2">
<?php foreach (['college','program','course','room','term'] as $kind): ?>
<section class="card panel">
<h2>Add <?= e(ucfirst($kind)) ?></h2>
<form method="post" action="actions.php" class="f-grid">
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="catalog_save">
<input type="hidden" name="kind" value="<?= e($kind) ?>">
<input type="hidden" name="return" value="setup.php">
<?= field('Name', input('name', ['required'=>true, 'maxlength'=>in_array($kind, ['term','room']) ? 40 : 100]), '', 3) ?>
<?php if (in_array($kind, ['program','course','room'])): ?>
<?= field('College', select_input('college_id', $collegeOptions, ['required'=>true]), '', 3) ?>
<?php endif; ?>
<?php if (in_array($kind, ['college','program','course'])): ?>
<?= field('Code', input('code', ['required'=>true,'maxlength'=>$kind==='college' ? 10 : 20])) ?>
<?php endif; ?>
<?php if ($kind==='college'): ?>
<?= field('Short name', input('short_name', ['required'=>true,'maxlength'=>40])) ?>
<?= field('Building', input('building', ['required'=>true,'maxlength'=>80])) ?>
<?php elseif ($kind==='course'): ?>
<?= field('Units', input('units', ['type'=>'number','required'=>true,'min'=>1,'max'=>20])) ?>
<?= field('Kind', select_input('course_kind', ['Lecture','Lab','PE','NSTP','Practicum'])) ?>
<?php elseif ($kind==='room'): ?>
<?= field('Type', select_input('room_type', ['Lecture','Laboratory','Gym'])) ?>
<?= field('Capacity', input('capacity', ['type'=>'number','required'=>true,'min'=>1,'max'=>1000])) ?>
<?php elseif ($kind==='term'): ?>
<?= field('Starts', input('starts_on', ['type'=>'date','required'=>true])) ?>
<?= field('Ends', input('ends_on', ['type'=>'date','required'=>true])) ?>
<?= field('Grade deadline', input('grade_deadline', ['type'=>'date','required'=>true]), 'This term becomes the active term.') ?>
<?php endif; ?>
<div class="f f--3"><button type="submit" class="btn btn--primary btn--sm"<?= in_array($kind, ['program','course','room']) && !$collegeOptions ? ' disabled' : '' ?>>Save <?= e($kind) ?></button></div>
</form>
</section>
<?php endforeach; ?>
</div>
<?php page_end(); ?>
