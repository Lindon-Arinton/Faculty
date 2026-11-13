<?php
/**
 * Pop-up forms for adding, editing and deleting records.
 * Every form posts to actions.php, which saves and sends the user back.
 */

/** Where actions.php should return to: the current page with its filters. */
function return_url(string $anchor = '#list'): string
{
    $params = $_GET;
    unset($params['export']);
    return basename($_SERVER['SCRIPT_NAME']) . ($params ? '?' . http_build_query($params) : '') . $anchor;
}

function attrs(array $attrs): string
{
    $html = '';
    foreach ($attrs as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $html .= ' ' . e($k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    }
    return $html;
}

/** A labelled form control. $span is 1, 2 or 3 columns of the form grid. */
function field(string $label, string $control, string $hint = '', int $span = 1): string
{
    return '<label class="f f--' . $span . '"><span class="f-label">' . e($label) . '</span>' . $control
        . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</label>';
}

function input(string $name, array $attrs = []): string
{
    return '<input class="f-input"' . attrs($attrs + ['type' => 'text', 'name' => $name]) . '>';
}

/** $options = [value => label] or [group => [value => label]]. A list (0,1,2…) uses the label as the value. */
function select_input(string $name, array $options, array $attrs = [], ?string $blank = null): string
{
    $render = function (array $opts) {
        $html = '';
        $isList = array_is_list($opts);
        foreach ($opts as $value => $label) {
            $html .= '<option value="' . e((string) ($isList ? $label : $value)) . '">' . e((string) $label) . '</option>';
        }
        return $html;
    };
    $html = '<select class="f-input"' . attrs($attrs + ['name' => $name]) . '>';
    if ($blank !== null) {
        $html .= '<option value="">' . e($blank) . '</option>';
    }
    if ($options && is_array(reset($options))) {
        foreach ($options as $group => $opts) {
            $html .= '<optgroup label="' . e((string) $group) . '">' . $render($opts) . '</optgroup>';
        }
    } else {
        $html .= $render($options);
    }
    return $html . '</select>';
}

function modal_start(string $id, string $title, string $action, string $sub = '', string $class = ''): string
{
    return '<dialog class="modal' . ($class ? ' ' . e($class) : '') . '" id="' . e($id) . '">'
        . '<form method="post" action="actions.php" class="modal-card">'
        . '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="return" value="' . e(return_url()) . '">'
        . '<input type="hidden" name="id" value="">'
        . '<header class="modal-head"><div><h2 data-modal-title data-default="' . e($title) . '">' . e($title) . '</h2>'
        . ($sub !== '' ? '<p class="muted small">' . e($sub) . '</p>' : '') . '</div>'
        . '<button type="button" class="icon-btn" data-close aria-label="Close">' . icon('x', 18) . '</button></header>'
        . '<div class="modal-body">';
}

function modal_end(string $submit = 'Save'): string
{
    return '</div><footer class="modal-foot">'
        . '<button type="button" class="btn btn--ghost btn--sm" data-close>Cancel</button>'
        . '<button type="submit" class="btn btn--primary btn--sm">' . icon('check', 16) . ' ' . e($submit) . '</button>'
        . '</footer></form></dialog>';
}

/** Button that opens a modal. With $fill it opens in "edit" mode with those values. */
function modal_button(string $modal, string $label, string $class = 'btn btn--primary btn--sm', ?array $fill = null, string $title = '', string $aria = ''): string
{
    return '<button type="button" class="' . e($class) . '" data-modal="' . e($modal) . '"'
        . ($fill !== null ? ' data-fill="' . e(json_encode($fill, JSON_UNESCAPED_UNICODE)) . '"' : '')
        . ($title !== '' ? ' data-title="' . e($title) . '"' : '')
        . ($aria !== '' ? ' aria-label="' . e($aria) . '" title="' . e($aria) . '"' : '') . '>' . $label . '</button>';
}

/** Button that asks "are you sure?" and then posts a delete to actions.php. */
function confirm_button(string $action, int|string $id, string $text, string $label = '', string $class = 'icon-btn icon-btn--danger', array $extra = [], string $return = '', string $aria = 'Delete'): string
{
    return '<button type="button" class="' . e($class) . '" data-confirm="' . e($action) . '" data-id="' . e((string) $id) . '" data-text="' . e($text) . '"'
        . ($extra ? ' data-extra="' . e(json_encode($extra)) . '"' : '')
        . ' data-return="' . e($return !== '' ? $return : return_url()) . '" aria-label="' . e($aria) . '" title="' . e($aria) . '">'
        . ($label !== '' ? $label : icon('trash', 16)) . '</button>';
}

/** Edit + delete icons at the end of a table row. */
function row_actions(string $modal, array $fill, string $editTitle, string $deleteAction, int $id, string $confirmText): string
{
    return '<div class="row-actions">'
        . modal_button($modal, icon('edit', 16), 'icon-btn', $fill, $editTitle, 'Edit')
        . confirm_button($deleteAction, $id, $confirmText)
        . '</div>';
}

/* ------------------------------------------------------------------ */
/* The forms                                                           */
/* ------------------------------------------------------------------ */

function applicant_modal(): string
{
    $reqs = '';
    foreach (REQUIREMENTS as $i => $r) {
        $reqs .= '<label class="check"><input type="checkbox" name="req[]" value="' . $i . '"><span>' . e($r) . '</span></label>';
    }
    return modal_start('applicantModal', 'Add applicant', 'applicant_save', intake_label() . ' admissions')
        . '<div class="f-grid">'
        . field('First name', input('first_name', ['required' => true, 'maxlength' => 60]))
        . field('Middle name', input('middle_name', ['maxlength' => 60]))
        . field('Last name', input('last_name', ['required' => true, 'maxlength' => 60]))
        . field('Sex', select_input('sex', ['F' => 'Female', 'M' => 'Male']))
        . field('Email', input('email', ['type' => 'email', 'required' => true, 'maxlength' => 120]), '', 2)
        . field('Program applied for', select_input('program', program_choices(), ['required' => true]), '', 2)
        . field('Applicant type', select_input('type', ['Freshman', 'Transferee']))
        . field('SHS strand', select_input('shs_strand', ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL'], [], 'None (transferee)'))
        . field('SHS average / GWA', input('prior_gwa', ['type' => 'number', 'step' => '0.01', 'min' => 1, 'max' => 100]), '75–100 for SHS, 1.00–5.00 for college GWA')
        . field('Entrance exam score', input('exam_score', ['type' => 'number', 'min' => 0, 'max' => 100]), 'Leave blank if not yet examined')
        . field('Status', select_input('status', ['For review', 'Qualified', 'Pending documents', 'Enrolled', 'Not qualified']), '', 3)
        . '<fieldset class="f f--3"><legend class="f-label">Requirements submitted</legend><div class="check-grid">' . $reqs . '</div></fieldset>'
        . '</div>'
        . modal_end('Save applicant');
}

function student_modal(): string
{
    return modal_start('studentModal', 'Add student', 'student_save', current_term()['name'])
        . '<div class="f-grid">'
        . field('First name', input('first_name', ['required' => true, 'maxlength' => 60]))
        . field('Middle name', input('middle_name', ['maxlength' => 60]))
        . field('Last name', input('last_name', ['required' => true, 'maxlength' => 60]))
        . field('Sex', select_input('sex', ['F' => 'Female', 'M' => 'Male']))
        . field('Birthdate', input('birthdate', ['type' => 'date', 'required' => true]))
        . field('Email', input('email', ['type' => 'email', 'maxlength' => 120]), 'Blank = school address is created')
        . field('Program', select_input('program', program_choices(), ['required' => true]), '', 3)
        . field('Year level', select_input('year', [1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year']))
        . field('Block', select_input('block', ['A', 'B', 'C', 'D', 'E']))
        . field('Status', select_input('status', ['Regular', 'Irregular', 'On leave']))
        . field('Back subjects', input('back_subjects', ['type' => 'number', 'min' => 0, 'max' => 20, 'value' => 0]), RISK_BACK_SUBJECTS . ' or more on an irregular student counts as behind curriculum')
        . '</div>'
        . '<p class="f-note">' . icon('alert', 14) . ' A new student is enrolled in every class of the chosen block. Moving a student to another block re-enrolls them there and clears this term’s grades.</p>'
        . modal_end('Save student');
}

function faculty_modal(): string
{
    return modal_start('facultyModal', 'Add faculty member', 'faculty_save')
        . '<div class="f-grid">'
        . field('Title', select_input('title', ['Prof.', 'Dr.', 'Engr.']))
        . field('First name', input('first_name', ['required' => true, 'maxlength' => 60]))
        . field('Last name', input('last_name', ['required' => true, 'maxlength' => 60]))
        . field('College', select_input('college', array_keys(colleges())))
        . field('Academic rank', select_input('rank', ['Instructor', 'Lecturer', 'Assistant Professor', 'Associate Professor', 'Professor']))
        . field('Employment', select_input('employment', ['Full-time', 'Part-time']))
        . field('Email', input('email', ['type' => 'email', 'maxlength' => 120]), 'Blank = school address is created', 3)
        . '</div>'
        . modal_end('Save faculty member');
}

function intervention_modal(): string
{
    return modal_start('interventionModal', 'Log intervention', 'intervention_save', '', 'modal--sm')
        . '<div class="f-grid f-grid--1">'
        . '<div class="f-person"><strong data-fill-text="name"></strong><span data-fill-text="detail"></span></div>'
        . field('Intervention status', select_input('status', INTERVENTION_STATUSES))
        . field('Last contact', input('last_contact', ['type' => 'date', 'max' => date('Y-m-d', app_now())]), 'Not needed for “Not contacted”')
        . field('Notes', '<textarea class="f-input" name="notes" rows="3" maxlength="255" placeholder="What was discussed, next steps…"></textarea>')
        . '</div>'
        . modal_end('Save intervention');
}

function class_modal(): string
{
    $days = '';
    foreach (WEEK_DAYS as $k => $label) {
        $days .= '<option value="' . $k . '">' . e($label) . '</option>';
    }
    $rooms = '<option value="">Room to be assigned</option>';
    foreach (room_choices() as $college => $list) {
        $rooms .= '<optgroup label="' . e($college) . '">';
        foreach ($list as $name => $label) {
            $rooms .= '<option value="' . e($name) . '">' . e($label) . '</option>';
        }
        $rooms .= '</optgroup>';
    }
    $row = '<div class="meet-row">'
        . '<select class="f-input" name="meet_day[]" aria-label="Day">' . $days . '</select>'
        . '<input class="f-input" type="time" name="meet_start[]" value="08:00" step="900" aria-label="Start time" required>'
        . '<input class="f-input" type="time" name="meet_end[]" value="09:00" step="900" aria-label="End time" required>'
        . '<select class="f-input" name="meet_room[]" aria-label="Room">' . $rooms . '</select>'
        . '<button type="button" class="icon-btn icon-btn--danger" data-meet-remove aria-label="Remove meeting">' . icon('x', 16) . '</button>'
        . '</div>';

    return modal_start('classModal', 'Add class', 'section_save', current_term()['name'], 'modal--lg')
        . '<div class="f-grid">'
        . field('Course', select_input('course_id', course_choices(), ['required' => true, 'data-lock-on-edit' => true]), '', 3)
        . field('Program', select_input('program', program_choices(), ['required' => true, 'data-lock-on-edit' => true]))
        . field('Year level', select_input('year', [1 => '1st year', 2 => '2nd year', 3 => '3rd year', 4 => '4th year'], ['data-lock-on-edit' => true]))
        . field('Block', select_input('block', ['A', 'B', 'C', 'D', 'E'], ['data-lock-on-edit' => true]))
        . field('Faculty', select_input('faculty_id', faculty_choices(), [], 'To be assigned (TBA)'), '', 2)
        . field('Mode', select_input('mode', ['Onsite', 'Online', 'Off-campus'], ['data-mode-select' => true]))
        . '<div class="f f--3" data-show-mode="Online" hidden>' . '<span class="f-label">Online platform</span>' . select_input('platform', ['Google Meet', 'MS Teams']) . '</div>'
        . '<fieldset class="f f--3" data-hide-mode="Off-campus"><legend class="f-label">Weekly meetings</legend>'
        . '<div class="meet-rows" id="meetRows"></div>'
        . '<template id="meetRowTpl">' . $row . '</template>'
        . '<button type="button" class="btn btn--ghost btn--xs" id="meetAdd">' . icon('plus', 14) . ' Add meeting</button>'
        . '</fieldset>'
        . '</div>'
        . '<p class="f-note" data-edit-only hidden>' . icon('alert', 14) . ' Course and block can’t be changed once students are enrolled. Delete the class and add a new one instead.</p>'
        . '<p class="f-note" data-add-only>' . icon('alert', 14) . ' Students of the chosen block are enrolled in the new class automatically.</p>'
        . modal_end('Save class');
}

/** Values for the class form's edit mode. */
function class_fill(array $c): array
{
    $hhmm = fn(int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    return [
        'id' => $c['id'], 'course_id' => $c['course_id'], 'program' => $c['program'], 'year' => $c['year'], 'block' => $c['block'],
        'faculty_id' => $c['faculty_id'] ?? '', 'mode' => $c['mode'], 'platform' => $c['platform'] ?? 'Google Meet',
        'meetings' => array_map(fn($m) => ['day' => $m['day'], 'start' => $hhmm($m['start']), 'end' => $hhmm($m['end']), 'room' => $m['room'] ?? ''], $c['meetings']),
    ];
}
