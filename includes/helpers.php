<?php
/**
 * View helpers shared by every page: query params, filtering, pagination,
 * CSV export, status colors and small chart builders.
 */

/* ---------- Query string ---------- */

function q(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Current page URL with some params changed. Changing filters resets the page number. */
function url_with(array $changes = [], ?string $page = null): string
{
    $params = array_merge($_GET, $changes);
    if (!array_key_exists('p', $changes)) {
        unset($params['p']);
    }
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 'all');
    $base = $page ?? basename($_SERVER['SCRIPT_NAME']);
    return $base . ($params ? '?' . http_build_query($params) : '');
}

/* ---------- Filtering + pagination ---------- */

function search_rows(array $rows, string $term, array $fields): array
{
    if ($term === '') {
        return $rows;
    }
    $needle = mb_strtolower($term);
    return array_values(array_filter($rows, function ($row) use ($needle, $fields) {
        foreach ($fields as $f) {
            if (str_contains(mb_strtolower((string) ($row[$f] ?? '')), $needle)) {
                return true;
            }
        }
        return false;
    }));
}

/** Keeps rows where each given field equals the value. Empty / "all" values are ignored. */
function filter_rows(array $rows, array $equals): array
{
    $equals = array_filter($equals, fn($v) => $v !== '' && $v !== 'all');
    if (!$equals) {
        return $rows;
    }
    return array_values(array_filter($rows, function ($row) use ($equals) {
        foreach ($equals as $field => $value) {
            if ((string) ($row[$field] ?? '') !== (string) $value) {
                return false;
            }
        }
        return true;
    }));
}

function paginate(array $rows, int $perPage = 20): array
{
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) q('p', '1')));
    $offset = ($page - 1) * $perPage;
    return [
        'items' => array_slice($rows, $offset, $perPage),
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
        'from'  => $total ? $offset + 1 : 0,
        'to'    => min($total, $offset + $perPage),
    ];
}

function pagination(array $p, string $noun = 'records'): string
{
    $html = '<div class="pager"><span class="pager-info">Showing <strong>' . number_format($p['from']) . '–' . number_format($p['to'])
        . '</strong> of <strong>' . number_format($p['total']) . '</strong> ' . e($noun) . '</span>';
    if ($p['pages'] > 1) {
        $html .= '<nav class="pager-links" aria-label="Pagination">';
        $html .= $p['page'] > 1
            ? '<a href="' . e(url_with(['p' => $p['page'] - 1]) . '#list') . '" aria-label="Previous page">' . icon('chev-l', 16) . '</a>'
            : '<span class="is-disabled">' . icon('chev-l', 16) . '</span>';

        $window = array_unique(array_filter(
            [1, $p['page'] - 1, $p['page'], $p['page'] + 1, $p['pages']],
            fn($n) => $n >= 1 && $n <= $p['pages']
        ));
        sort($window);
        $prev = 0;
        foreach ($window as $n) {
            if ($n - $prev > 1) {
                $html .= '<span class="pager-gap">…</span>';
            }
            $html .= $n === $p['page']
                ? '<span class="is-current" aria-current="page">' . $n . '</span>'
                : '<a href="' . e(url_with(['p' => $n]) . '#list') . '">' . $n . '</a>';
            $prev = $n;
        }

        $html .= $p['page'] < $p['pages']
            ? '<a href="' . e(url_with(['p' => $p['page'] + 1]) . '#list') . '" aria-label="Next page">' . icon('chev-r', 16) . '</a>'
            : '<span class="is-disabled">' . icon('chev-r', 16) . '</span>';
        $html .= '</nav>';
    }
    return $html . '</div>';
}

/** Sends the rows as a CSV download when ?export=csv is present. */
function maybe_export_csv(array $rows, array $columns, string $filename): void
{
    if (q('export') !== 'csv') {
        return;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
    fputcsv($out, array_keys($columns), ',', '"', '');
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $get) {
            $value = is_callable($get) ? $get($row) : ($row[$get] ?? '');
            // Stop spreadsheet apps from treating cell text as a formula.
            if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                $value = "'" . $value;
            }
            $line[] = $value;
        }
        fputcsv($out, $line, ',', '"', '');
    }
    fclose($out);
    exit;
}

/* ---------- Form controls ---------- */

function select_filter(string $name, array $options, string $current, string $allLabel): string
{
    $html = '<div class="select select--sm"><select name="' . e($name) . '" data-autosubmit aria-label="' . e($allLabel) . '">';
    $html .= '<option value="">' . e($allLabel) . '</option>';
    foreach ($options as $value => $label) {
        $value = is_int($value) ? $label : $value;
        $html .= '<option value="' . e((string) $value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . e((string) $label) . '</option>';
    }
    return $html . '</select>' . icon('chev-d', 14) . '</div>';
}

/** Filter chips that link to the same page with one param changed. */
function chip_links(string $param, array $options, string $current, array $counts = []): string
{
    $html = '<div class="chips">';
    foreach ($options as $value => $label) {
        $active = (string) $value === $current;
        $count = isset($counts[$value]) ? ' <span class="chip-count">' . number_format($counts[$value]) . '</span>' : '';
        $html .= '<a class="chip' . ($active ? ' is-active' : '') . '" href="' . e(url_with([$param => $value]) . '#list') . '">' . e($label) . $count . '</a>';
    }
    return $html . '</div>';
}

/* ---------- Display helpers ---------- */

function tone_for(string $seed): string
{
    $tones = ['purple', 'blue', 'orange', 'green', 'slate'];
    return $tones[crc32($seed) % count($tones)];
}

function status_tone(string $status): string
{
    return match ($status) {
        'Qualified', 'Behind curriculum', 'Off-campus', 'Transferee'      => 'purple',
        'For review', 'Onsite', 'Contacted', 'Counseling scheduled'       => 'blue',
        'Pending documents', 'In progress', 'INC', 'Irregular'            => 'orange',
        'Enrolled', 'Submitted', 'Online', 'Regular', 'Improving'         => 'green',
        'Not qualified', 'Failing', 'Urgent', 'Not started'               => 'red',
        default                                                           => 'slate',
    };
}

function tag(string $label, ?string $tone = null, string $extra = ''): string
{
    return '<span class="tag tag--' . e($tone ?? status_tone($label)) . ($extra ? ' ' . e($extra) : '') . '">' . e($label) . '</span>';
}

function avatar(string $name, ?string $tone = null, string $extra = ''): string
{
    $clean = preg_replace('/^(Dr|Prof|Engr)\.\s+/', '', $name);
    return '<span class="avatar avatar--' . e($tone ?? tone_for($name)) . ($extra ? ' ' . e($extra) : '') . '">' . e(initials($clean)) . '</span>';
}

/** 480 -> "8:00"; with $meridiem -> "8:00 AM". */
function clock(?int $minutes, bool $meridiem = false): string
{
    if ($minutes === null) {
        return '—';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $out = (($h % 12) ?: 12) . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    return $meridiem ? $out . ($h < 12 ? ' AM' : ' PM') : $out;
}

function rel_time(?int $ts): string
{
    if (!$ts) {
        return '—';
    }
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d', app_now())) {
        return 'Today, ' . date('g:i A', $ts);
    }
    if ($day === date('Y-m-d', app_now() - 86400)) {
        return 'Yesterday, ' . date('g:i A', $ts);
    }
    return date('Y', $ts) === date('Y', app_now()) ? date('M j, g:i A', $ts) : date('M j, Y', $ts);
}

function days_ago(?int $ts): string
{
    if (!$ts) {
        return 'Never';
    }
    $d = (int) round((strtotime(date('Y-m-d', app_now())) - strtotime(date('Y-m-d', $ts))) / 86400);
    return match (true) {
        $d <= 0 => 'Today',
        $d === 1 => 'Yesterday',
        default => $d . ' days ago',
    };
}

function pct(float $part, float $whole, int $decimals = 0): string
{
    return $whole > 0 ? number_format($part / $whole * 100, $decimals) : '0';
}

function stars(float $rating): string
{
    return '<span class="stars" style="--r: ' . round($rating / 5 * 100, 1) . '%" aria-label="' . $rating . ' out of 5">★★★★★</span>';
}

/* ---------- Charts ---------- */

/**
 * Horizontal bars. $items = [label => value] or [label => [value, displayText]].
 * $style is space-separated modifiers: a color (orange, purple) and/or "block"
 * (label on its own line, for narrow cards).
 */
function bar_list(array $items, ?float $max = null, string $format = 'number', string $style = '', array $links = []): string
{
    $values = array_map(fn($v) => is_array($v) ? $v[0] : $v, $items);
    $max ??= max($values ?: [1]);
    $sum = array_sum($values);
    $mods = array_filter(explode(' ', $style));
    $html = '<ul class="bars' . implode('', array_map(fn($m) => ' bars--' . e($m), $mods)) . '">';
    foreach ($items as $label => $item) {
        $value = is_array($item) ? $item[0] : $item;
        $shown = is_array($item) ? e($item[1]) : match ($format) {
            'rating' => number_format($value, 2),
            'pct'    => round($value) . '%',
            default  => number_format($value),
        };
        $tip = $label . '|' . strip_tags($shown) . match (true) {
            is_array($item)      => '',
            $format === 'rating' => ' out of 5',
            $format === 'number' => $sum > 0 ? ' · ' . pct($value, $sum, 1) . '% of total' : '',
            default              => '',
        } . (isset($links[$label]) ? '|Click to filter' : '');
        $html .= '<li data-tip="' . e($tip) . '"' . (isset($links[$label]) ? ' class="is-link" data-href="' . e($links[$label]) . '" tabindex="0" role="link"' : '') . '>'
            . '<span class="bars-label">' . e((string) $label) . '</span>'
            . '<span class="bars-track"><span class="bars-fill" style="--w: ' . ($max > 0 ? round($value / $max * 100, 1) : 0) . '%"></span></span>'
            . '<span class="bars-num">' . $shown . '</span></li>';
    }
    return $html . '</ul>';
}

/** Donut chart. $segments = [[label, value, cssColor, optional link], ...]. Hover, click and legend toggling are in app.js. */
function donut_chart(array $segments, string $center, string $sub, int $size = 132): string
{
    $total = array_sum(array_column($segments, 1)) ?: 1;
    $offset = 0;
    $circles = '';
    foreach ($segments as $i => $seg) {
        [$label, $value, $color] = $seg;
        $len = $value / $total * 100;
        $circles .= '<circle cx="21" cy="21" r="15.915" fill="none" stroke="' . e($color) . '" stroke-width="6" stroke-dasharray="'
            . round($len, 3) . ' ' . round(100 - $len, 3) . '" stroke-dashoffset="' . round(25 - $offset, 3) . '" data-i="' . $i . '" data-label="' . e($label)
            . '" data-value="' . (float) $value . '"' . (isset($seg[3]) ? ' data-href="' . e($seg[3]) . '"' : '') . '/>';
        $offset += $len;
    }
    $legend = '';
    foreach ($segments as $i => [$label, $value, $color]) {
        $legend .= '<li data-i="' . $i . '" role="button" tabindex="0" title="Click to hide or show"><span class="swatch" style="background: ' . e($color) . '"></span>' . e($label) . '<strong>' . number_format($value) . '</strong></li>';
    }
    return '<div class="donut-wrap" data-donut><div class="donut" style="width:' . $size . 'px;height:' . $size . 'px">'
        . '<svg viewBox="0 0 42 42" role="img" aria-label="' . e($center . ' ' . $sub) . '">' . $circles . '</svg>'
        . '<div class="donut-center"><strong data-default="' . e($center) . '">' . e($center) . '</strong><span data-default="' . e($sub) . '">' . e($sub) . '</span></div></div>'
        . '<ul class="legend">' . $legend . '</ul></div>';
}

/** Small area line chart. $series = [label => value]. */
function line_chart(array $series, string $color = 'var(--red-500)', int $height = 140): string
{
    $vals = array_values($series);
    $labels = array_keys($series);
    $lo = min($vals) - (max($vals) - min($vals)) * 0.6 - 1;
    $hi = max($vals) + (max($vals) - min($vals)) * 0.3 + 1;
    $w = 300; $h = 140; $px = 12; $py = 12;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $px + $i * (($w - 2 * $px) / max(1, count($vals) - 1));
        $y = $py + ($hi - $v) / ($hi - $lo) * ($h - 2 * $py);
        $pts[] = [round($x, 1), round($y, 1), $v];
    }
    $line = 'M' . implode(' L', array_map(fn($p) => $p[0] . ' ' . $p[1], $pts));
    $area = $line . ' L' . end($pts)[0] . ' ' . $h . ' L' . $pts[0][0] . ' ' . $h . ' Z';
    $id = 'g' . substr(md5($line), 0, 6);

    $grid = '';
    for ($g = 1; $g <= 3; $g++) {
        $grid .= '<line x1="0" x2="' . $w . '" y1="' . ($g * $h / 4) . '" y2="' . ($g * $h / 4) . '" class="grid-line"/>';
    }
    $dots = '';
    foreach ($pts as $i => $p) {
        $change = $i ? $p[2] - $pts[$i - 1][2] : null;
        $dots .= '<span class="line-dot" style="left:' . round($p[0] / $w * 100, 2) . '%;top:' . round($p[1] / $h * 100, 2) . '%;border-color:' . e($color) . '" data-x="' . round($p[0] / $w * 100, 2)
            . '" data-label="' . e((string) $labels[$i]) . '" data-value="' . e(number_format($p[2])) . '" data-change="' . ($change === null ? '' : ($change > 0 ? '+' : '') . $change . ' vs ' . e((string) $labels[$i - 1])) . '"></span>';
    }
    return '<div class="line-chart" data-line style="height:' . $height . 'px"><span class="line-guide" hidden></span><svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="Trend from '
        . e((string) $labels[0]) . ' to ' . e((string) end($labels)) . '"><defs><linearGradient id="' . $id . '" x1="0" x2="0" y1="0" y2="1">'
        . '<stop offset="0%" stop-color="' . e($color) . '" stop-opacity=".22"/><stop offset="100%" stop-color="' . e($color) . '" stop-opacity="0"/></linearGradient></defs>'
        . $grid . '<path d="' . $area . '" fill="url(#' . $id . ')"/><path d="' . $line . '" fill="none" stroke="' . e($color) . '" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>'
        . $dots . '</div><div class="line-labels">' . implode('', array_map(fn($l) => '<span>' . e((string) $l) . '</span>', $labels)) . '</div>';
}

function stat_card(string $label, string $value, string $note, string $icon, string $tone, ?string $href = null, ?string $tooltip = null): string
{
    $tag = $href ? 'a' : 'div';
    $description = $tooltip ?? trim(html_entity_decode(strip_tags($note), ENT_QUOTES, 'UTF-8'));
    $tip = $label . '|' . $description . ($href ? '|Click to view the matching records' : '');
    return '<' . $tag . ' class="card stat" data-tip="' . e($tip) . '"' . ($href ? ' href="' . e($href) . '"' : ' tabindex="0"') . '>'
        . '<span class="stat-icon tone-' . e($tone) . '">' . icon($icon, 20) . '</span><div>'
        . '<div class="stat-label">' . e($label) . '</div><div class="stat-value">' . e($value) . '</div>'
        . '<div class="stat-note">' . $note . '</div></div></' . $tag . '>';
}
